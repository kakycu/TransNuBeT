<?php
// config/migraciones.php - Migraciones automáticas idempotentes

/**
 * Agrega las columnas reset_token y reset_expira a clasif_usuarios si no existen.
 */
function asegurarColumnasResetToken($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE()
                               AND TABLE_NAME = 'clasif_usuarios'
                               AND COLUMN_NAME IN ('reset_token','reset_expira')");
        if ((int)$stmt->fetchColumn() < 2) {
            $pdo->exec("ALTER TABLE clasif_usuarios
                        ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL,
                        ADD COLUMN reset_expira DATETIME NULL DEFAULT NULL");
        }
    } catch (PDOException $e) {}
}

/**
 * Asegura que existan las tarifas de nocturnidad (Resolución 15/2026 MTSS).
 */
function asegurarTarifasNocturnidad($pdo) {
    $params = [
        'tarifa_nocturnidad_temprana' => '0.60',
        'tarifa_nocturnidad_tardia'   => '1.15',
    ];
    try {
        $check = $pdo->prepare("SELECT parametro FROM configuracion_general WHERE parametro = ?");
        $ins   = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion) VALUES (?, ?, ?, ?)");
        foreach ($params as $p => $default) {
            $check->execute([$p]);
            if (!$check->fetch()) {
                $desc = ($p === 'tarifa_nocturnidad_temprana')
                    ? 'Tarifa fija Nt 7-23h ($/h) - Res. 15/2026 MTSS'
                    : 'Tarifa fija Nt 23-7h ($/h) - Res. 15/2026 MTSS';
                $ins->execute([$p, $default, 'decimal', $desc]);
            }
        }
    } catch (PDOException $e) {}
}

/**
 * Asegura que existan los parámetros de correo en configuracion_general.
 */
function asegurarParamsMail($pdo) {
    $params = [
        'mail_activo'     => 'texto',
        'mail_proveedor'  => 'texto',
        'mail_host'       => 'texto',
        'mail_port'       => 'texto',
        'mail_encryption' => 'texto',
        'mail_usuario'    => 'texto',
        'mail_password'   => 'texto',
        'mail_from'       => 'texto',
        'mail_from_name'  => 'texto',
    ];
    try {
        $check = $pdo->prepare("SELECT parametro FROM configuracion_general WHERE parametro = ?");
        $ins   = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato) VALUES (?, ?, ?)");
        foreach ($params as $p => $tipo) {
            $check->execute([$p]);
            if (!$check->fetch()) {
                $valor = ($p === 'mail_port') ? '587' : '';
                $ins->execute([$p, $valor, $tipo]);
            }
        }
    } catch (PDOException $e) {}
}
