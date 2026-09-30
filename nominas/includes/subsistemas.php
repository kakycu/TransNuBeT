<?php
if (!function_exists('subsistema_activo')) {
    function subsistema_activo($pdo, $codigo = '0001') {
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT estado FROM subsistemas WHERE codigo = ? LIMIT 1");
            $stmt->execute([$codigo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && (int)$row['estado'] === 1;
        } catch (Exception $e) {
            error_log("subsistema_activo($codigo): " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('modo_mantenimiento_activo')) {
    function modo_mantenimiento_activo($pdo) {
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'modo_mantenimiento' LIMIT 1");
            $stmt->execute();
            return (int)$stmt->fetchColumn() === 1;
        } catch (Exception $e) {
            error_log("modo_mantenimiento_activo: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('establecer_modo_mantenimiento')) {
    function establecer_modo_mantenimiento($pdo, $activo) {
        if (!$pdo) {
            return false;
        }
        try {
            require_once __DIR__ . '/config_audit.php';
            return guardar_parametro_configuracion(
                $pdo,
                'modo_mantenimiento',
                $activo ? 1 : 0,
                'Bloquea el acceso al sistema para todos los usuarios excepto el rol 5 (Programador)'
            );
        } catch (Exception $e) {
            error_log("establecer_modo_mantenimiento: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('motivo_bloqueo')) {
    function motivo_bloqueo($pdo) {
        if (modo_mantenimiento_activo($pdo)) {
            return 'mantenimiento';
        }
        if (!subsistema_activo($pdo, '0001')) {
            return 'subsistema_inactivo';
        }
        return null;
    }
}

if (!function_exists('acceso_bloqueado')) {
    function acceso_bloqueado($pdo, $rol_id) {
        $motivo = motivo_bloqueo($pdo);
        if ($motivo === null || (int)$rol_id === 5) {
            return null;
        }
        return $motivo;
    }
}
