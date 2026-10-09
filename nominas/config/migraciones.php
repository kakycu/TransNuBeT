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
                    ? 'Tarifa fija por hora del turno de 19:00 a 23:00 ($/h) - Res. 15/2026 MTSS, QUINTO.2'
                    : 'Tarifa fija por hora del turno de 23:00 a 07:00 ($/h) - Res. 15/2026 MTSS, QUINTO.2';
                $ins->execute([$p, $default, 'decimal', $desc]);
            }
        }
    } catch (PDOException $e) {}
}

/**
 * Asegura que existan los parámetros de correo en configuracion_general.
 */
/**
 * Asegura los parametros de remuneracion del trabajo extraordinario y nocturno.
 *
 * Ley 189/2026 "Codigo de Trabajo", art. 230: incremento del 25 % por cada hora
 * en exceso, es decir, 1.25. El art. 227 considera el doble turno como una forma
 * de trabajo extraordinario, por lo que comparte el mismo multiplicador.
 *
 * Res. 15/2026 MTSS, QUINTO.2: la nocturnidad se paga con tarifa fija por hora,
 * no con un recargo sobre el salario.
 *
 * Los parametros anteriores (recargo_nocturno, recargo_extra_diurna,
 * recargo_extra_nocturna y recargo_doble_turno) quedaron sin efecto, asi que se
 * eliminan de la configuracion. Solo se borran filas de configuracion_general:
 * las nominas ya contabilizadas conservan sus importes y no se recalculan.
 */
function asegurarRecargosExtra($pdo) {
    try {
        $vigentes = [
            'recargo_trabajo_extraordinario' => [
                'valor'      => '1.25',
                'tipo_dato'  => 'decimal',
                'descripcion'=> 'Multiplicador del trabajo extraordinario (horas extras y doble turno) - Ley 189/2026, art. 230: 1.25 = 25% de incremento',
            ],
            'tarifa_nocturnidad_temprana' => [
                'valor'      => '0.60',
                'tipo_dato'  => 'decimal',
                'descripcion'=> 'Tarifa fija por hora del turno de 19:00 a 23:00 ($/h) - Res. 15/2026 MTSS, QUINTO.2',
            ],
            'tarifa_nocturnidad_tardia' => [
                'valor'      => '1.15',
                'tipo_dato'  => 'decimal',
                'descripcion'=> 'Tarifa fija por hora del turno de 23:00 a 07:00 ($/h) - Res. 15/2026 MTSS, QUINTO.2',
            ],
            'cess_tasa_exceso' => [
                'valor'      => '10.00',
                'tipo_dato'  => 'decimal',
                'descripcion'=> 'Tasa de CESS sobre el exceso (porcentaje) para la regla progresiva PDL: 5% hasta 15000, exceso al 10%.',
            ],
            'cess_limite_progresivo' => [
                'valor'      => '15000.00',
                'tipo_dato'  => 'decimal',
                'descripcion'=> 'Límite de la regla progresiva de la CESS (CUP): aplica tasa base hasta este monto.',
            ],
            'tope_he_anual' => [
                'valor'      => '160',
                'tipo_dato'  => 'entero',
                'descripcion'=> 'Tope de horas extraordinarias anuales por trabajador (Ley 189/2026, art. 229.2): 160 h al año por defecto.',
            ],
        ];

        $check = $pdo->prepare("SELECT parametro FROM configuracion_general WHERE parametro = ?");
        $ins   = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion) VALUES (?, ?, ?, ?)");

        foreach ($vigentes as $parametro => $cfg) {
            $check->execute([$parametro]);
            if (!$check->fetch()) {
                $ins->execute([$parametro, $cfg['valor'], $cfg['tipo_dato'], $cfg['descripcion']]);
            }
        }

        $obsoletos = [
            'recargo_nocturno',
            'recargo_extra_diurna',
            'recargo_extra_nocturna',
            'recargo_doble_turno',
        ];

        $del = $pdo->prepare("DELETE FROM configuracion_general WHERE parametro = ?");
        foreach ($obsoletos as $parametro) {
            $del->execute([$parametro]);
        }
    } catch (PDOException $e) {}
}

/**
 * Asegura la funcionalidad de tarifas pactadas por Convenio Colectivo de Trabajo
 * (Empleador - Colectivo de Trabajadores) para la nómina extraordinaria:
 *   - columna `usar_convenio` en `nominas` (1 = las filas del lote se calcularon
 *     con los valores pactados; 0 = con la Ley 189/2026),
 *   - 4 parámetros en `configuracion_general` con los valores fijos (CUP/h)
 *     pactados por el convenio: horas extras, doble turno, nocturnidad temprana
 *     y nocturnidad tardía.
 *
 * Las nóminas ya contabilizadas conservan sus importes; el flag solo se usa al
 * recalcular/guardar e identificar la tarifa empleada en reportes y exportaciones.
 */
function asegurarConvenioExtra($pdo) {
    try {
        $colexists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                                  WHERE TABLE_SCHEMA = DATABASE()
                                    AND TABLE_NAME = 'nominas'
                                    AND COLUMN_NAME = 'usar_convenio'")->fetchColumn();
        if ((int)$colexists < 1) {
            $pdo->exec("ALTER TABLE nominas
                        ADD COLUMN usar_convenio TINYINT(1) NOT NULL DEFAULT 0 AFTER importe_doble_turno");
        }
    } catch (PDOException $e) {}

    $params = [
        'convenio_valor_he' => [
            'valor'      => '10.25',
            'tipo_dato'  => 'decimal',
            'descripcion'=> 'Valor pactado por Convenio Colectivo de Trabajo para las horas extras ($/h)',
        ],
        'convenio_valor_doble_turno' => [
            'valor'      => '20.05',
            'tipo_dato'  => 'decimal',
            'descripcion'=> 'Valor pactado por Convenio Colectivo de Trabajo para el doble turno ($/h)',
        ],
        'convenio_valor_nocturnidad_temprana' => [
            'valor'      => '8.00',
            'tipo_dato'  => 'decimal',
            'descripcion'=> 'Valor pactado por Convenio Colectivo de Trabajo para el turno 19:00-23:00 ($/h)',
        ],
        'convenio_valor_nocturnidad_tardia' => [
            'valor'      => '14.75',
            'tipo_dato'  => 'decimal',
            'descripcion'=> 'Valor pactado por Convenio Colectivo de Trabajo para el turno 23:00-07:00 ($/h)',
        ],
    ];

    try {
        $check = $pdo->prepare("SELECT parametro FROM configuracion_general WHERE parametro = ?");
        $ins   = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion) VALUES (?, ?, ?, ?)");
        foreach ($params as $parametro => $cfg) {
            $check->execute([$parametro]);
            if (!$check->fetch()) {
                $ins->execute([$parametro, $cfg['valor'], $cfg['tipo_dato'], $cfg['descripcion']]);
            }
        }
    } catch (PDOException $e) {}
}

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

/**
 * Crea la tabla de cierres de periodo de nomina si no existe.
 *
 * No reutiliza `cierres_nomina` porque esa tabla es un snapshot por LOTE de
 * nomina (periodo + tipo + numero_nomina) y admite varias filas para un mismo
 * mes. Aqui se congela el PERIODO completo (mes o anio), que es otra cosa.
 *
 * `periodo_mes` usa 0 para el cierre de anio en lugar de NULL: en MySQL los
 * NULL no se consideran iguales en un indice UNIQUE, por lo que con NULL se
 * podrian duplicar los cierres anuales.
 */
function asegurarTablaCierresPeriodo($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cierres_periodo_nomina (
            id                  INT(11)      NOT NULL AUTO_INCREMENT,
            tipo                TINYINT(4)   NOT NULL DEFAULT 1,
            periodo_anio        SMALLINT(6)  NOT NULL,
            periodo_mes         TINYINT(4)   NOT NULL DEFAULT 0,
            periodo_desde       DATE         NULL DEFAULT NULL,
            periodo_hasta       DATE         NULL DEFAULT NULL,
            estado              ENUM('cerrado','revertido') NOT NULL DEFAULT 'cerrado',
            total_lotes         INT(11)      NULL DEFAULT NULL,
            total_trabajadores  INT(11)      NULL DEFAULT NULL,
            total_devengado     DECIMAL(14,2) NULL DEFAULT NULL,
            total_deducciones   DECIMAL(14,2) NULL DEFAULT NULL,
            total_neto          DECIMAL(14,2) NULL DEFAULT NULL,
            total_contribucion  DECIMAL(14,2) NULL DEFAULT NULL,
            total_vacaciones    DECIMAL(14,2) NULL DEFAULT NULL,
            desglose_tipos      TEXT         NULL DEFAULT NULL,
            motivo_reapertura   TEXT         NULL DEFAULT NULL,
            observaciones       TEXT         NULL DEFAULT NULL,
            fecha_cierre        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            usuario_cierre      VARCHAR(100) NULL DEFAULT NULL,
            fecha_reapertura    DATETIME     NULL DEFAULT NULL,
            usuario_reapertura  VARCHAR(100) NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_periodo (tipo, periodo_anio, periodo_mes),
            KEY idx_tipo (tipo),
            KEY idx_anio (periodo_anio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        error_log('[migraciones] no se pudo crear cierres_periodo_nomina: ' . $e->getMessage());
    }
}

/**
 * Asegura que exista el parametro que guarda el periodo de nomina en curso.
 *
 * Es el equivalente a `configuracion_sistema.fecha_inicio_operaciones` de
 * facturacion: el mes abierto mas antiguo. Se avanza al cerrar un mes y se
 * retrocede al reabrirlo.
 *
 * Si el parametro no existe se siembra con el ultimo periodo que tenga nominas
 * generadas, para no arrancar en un mes vacio; si la tabla `nominas` estuviera
 * vacia cae al mes calendario actual.
 */
function asegurarPeriodoNominasEnCurso($pdo) {
    $param = 'periodo_nominas_en_curso';
    try {
        $check = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = ?");
        $check->execute([$param]);
        $existente = $check->fetchColumn();

        if ($existente !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$existente)) {
            return;
        }

        $valor = null;
        try {
            $ultimo = $pdo->query("SELECT MAX(periodo_desde) FROM nominas WHERE periodo_desde IS NOT NULL")->fetchColumn();
            if ($ultimo && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$ultimo)) {
                $valor = substr((string)$ultimo, 0, 7) . '-01';
            }
        } catch (PDOException $e) {
            $valor = null;
        }

        if ($valor === null) {
            $valor = date('Y-m-01');
        }

        $upd = $pdo->prepare("UPDATE configuracion_general SET valor = ?, tipo_dato = 'fecha' WHERE parametro = ?");
        $upd->execute([$valor, $param]);

        if ($upd->rowCount() === 0) {
            $ins = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion)
                                  VALUES (?, ?, 'fecha', ?)
                                  ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo_dato = 'fecha'");
            $ins->execute([
                $param,
                $valor,
                'Mes de nomina en curso (YYYY-MM-01). Se avanza al cerrar un mes y se retrocede al reabrirlo',
            ]);
        }
    } catch (PDOException $e) {
        error_log('[migraciones] no se pudo asegurar ' . $param . ': ' . $e->getMessage());
    }
}
