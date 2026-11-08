<?php
/**
 * config_audit.php - Punto unico de escritura y auditoria de la configuracion
 *
 * Todo cambio de valor en configuracion_general o en el estado de un
 * subsistema debe pasar por aqui. Asi el historico de operaciones
 * (audit_logs) registra siempre, con el valor anterior y el nuevo, cada
 * parametro que realmente cambio: no se registra nada cuando el valor se
 * reenvia igual, y no queda ningun UPDATE directo en las paginas.
 *
 * Los parametros sensibles (claves SMTP, secretos OAuth) se guardan cifrados
 * por el codigo que los produce, aqui solo se anota que cambiaron.
 */

if (!function_exists('config_parametros_sensibles')) {
    function config_parametros_sensibles() {
        return [
            'mail_password',
            'google_client_secret',
        ];
    }
}

if (!function_exists('config_ocultar_valor')) {
    function config_ocultar_valor($parametro) {
        return in_array($parametro, config_parametros_sensibles(), true) ? '(cifrado, cambiado)' : null;
    }
}

if (!function_exists('config_texto_auditable')) {
    function config_texto_auditable($valor) {
        $texto = is_bool($valor) ? ($valor ? '1' : '0') : (string)$valor;
        if (strlen($texto) > 200) {
            $texto = substr($texto, 0, 200) . '...';
        }
        return $texto;
    }
}

if (!function_exists('valor_configuracion_actual')) {
    function valor_configuracion_actual($pdo, $parametro) {
        try {
            $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = ? LIMIT 1");
            $stmt->execute([$parametro]);
            $valor = $stmt->fetchColumn();
            return ($valor === false) ? null : $valor;
        } catch (Exception $e) {
            error_log('[config_audit] no se pudo leer ' . $parametro . ': ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('estado_subsistema_actual')) {
    function estado_subsistema_actual($pdo, $codigo) {
        try {
            $stmt = $pdo->prepare("SELECT estado FROM subsistemas WHERE codigo = ? LIMIT 1");
            $stmt->execute([$codigo]);
            $estado = $stmt->fetchColumn();
            return ($estado === false) ? null : (int)$estado;
        } catch (Exception $e) {
            error_log('[config_audit] no se pudo leer el subsistema ' . $codigo . ': ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('registrar_cambio_configuracion')) {
    function registrar_cambio_configuracion($parametro, $valorAnterior, $valorNuevo, $accion = 'cambiar_configuracion') {
        if (!function_exists('logAction')) {
            require_once __DIR__ . '/logger.php';
        }
        if ($accion === 'cambiar_configuracion' && !isset(LOG_ACCIONES[$accion])) {
            $accion = 'guardar_configuracion_general';
        }

        $oculto = config_ocultar_valor($parametro);
        $detalles = [
            'parametro' => $parametro,
            'valor_anterior' => $oculto !== null ? $oculto : config_texto_auditable($valorAnterior),
            'valor_nuevo'     => $oculto !== null ? $oculto : config_texto_auditable($valorNuevo),
        ];

        $descripcion = 'Cambio de configuración: ' . $parametro
            . ' (' . $detalles['valor_anterior'] . ' -> ' . $detalles['valor_nuevo'] . ')';

        return logAction(
            $accion,
            'configuracion',
            $descripcion,
            $detalles,
            null,
            'success',
            null,
            ($_SESSION['auth_provider'] ?? 'local')
        );
    }
}

if (!function_exists('guardar_parametro_configuracion')) {
    /**
     * Guarda el valor de un parametro de configuracion_general dejando rastro
     * en el historico. Devuelve true si se guardo (o no habia nada que
     * cambiar) y false si hubo error.
     */
    function guardar_parametro_configuracion($pdo, $parametro, $valor, $descripcion = null) {
        if (!$pdo) {
            return false;
        }

        try {
            $anterior = valor_configuracion_actual($pdo, $parametro);
            $nuevo = is_bool($valor) ? ($valor ? '1' : '0') : (string)$valor;

            if ($anterior !== null && (string)$anterior === $nuevo) {
                return true;
            }

            $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
            $stmt->execute([$nuevo, $parametro]);

            if ($stmt->rowCount() === 0 && $anterior === null) {
                $ins = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion)
                                       VALUES (?, ?, 'booleano', ?)
                                       ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                $ins->execute([
                    $parametro,
                    $nuevo,
                    $descripcion !== null ? $descripcion : 'Parametro de configuracion del sistema',
                ]);
            }

            registrar_cambio_configuracion($parametro, $anterior, $nuevo);
            return true;
        } catch (Exception $e) {
            error_log('[config_audit] error al guardar ' . $parametro . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('guardar_estado_subsistema')) {
    function guardar_estado_subsistema($pdo, $codigo, $estado, $descripcion = null) {
        if (!$pdo) {
            return false;
        }

        try {
            $anterior = estado_subsistema_actual($pdo, $codigo);
            $nuevo = (int)$estado ? 1 : 0;

            if ($anterior !== null && $anterior === $nuevo) {
                return true;
            }

            $stmt = $pdo->prepare("UPDATE subsistemas SET estado = ? WHERE codigo = ?");
            $stmt->execute([$nuevo, $codigo]);

            registrar_cambio_configuracion(
                'subsistema_' . $codigo,
                $anterior === null ? null : ($anterior ? 'activo' : 'inactivo'),
                $nuevo ? 'activo' : 'inactivo',
                'cambiar_estado_subsistema'
            );
            return true;
        } catch (Exception $e) {
            error_log('[config_audit] error al guardar el subsistema ' . $codigo . ': ' . $e->getMessage());
            return false;
        }
    }
}
