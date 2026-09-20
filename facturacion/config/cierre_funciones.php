<?php
/**
 * Funciones para el manejo de cierres de operaciones
 * Ubicación: config/cierre_funciones.php
 */

// ==================== FUNCIONES PRINCIPALES ====================

/**
 * Obtiene el período actual de operaciones desde configuracion_sistema
 */
function obtenerPeriodoOperaciones($db) {
    try {
        $sql = "SELECT fecha_inicio_operaciones FROM configuracion_sistema WHERE id = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['fecha_inicio_operaciones']) {
            $fecha = DateTime::createFromFormat('Y-m-d', $result['fecha_inicio_operaciones']);
            if ($fecha) {
                return [
                    'fecha_inicio' => $result['fecha_inicio_operaciones'],
                    'anio' => (int)$fecha->format('Y'),
                    'mes' => (int)$fecha->format('m'),
                    'fecha_objeto' => $fecha
                ];
            }
        }
        
        // Si no hay fecha, usar fecha actual (primer día del mes actual)
        $hoy = new DateTime('first day of this month');
        return [
            'fecha_inicio' => $hoy->format('Y-m-d'),
            'anio' => (int)$hoy->format('Y'),
            'mes' => (int)$hoy->format('m'),
            'fecha_objeto' => $hoy
        ];
        
    } catch (Exception $e) {
        $hoy = new DateTime('first day of this month');
        return [
            'fecha_inicio' => $hoy->format('Y-m-d'),
            'anio' => (int)$hoy->format('Y'),
            'mes' => (int)$hoy->format('m'),
            'fecha_objeto' => $hoy
        ];
    }
}

/**
 * Actualiza el período de operaciones en la configuración
 */
function actualizarPeriodoOperaciones($nueva_fecha, $usuario_id, $usuario_nombre, $db) {
    $transaccion_propia = false;

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $transaccion_propia = true;
        }
        
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $nueva_fecha);
        if (!$fecha_obj) {
            throw new Exception("Formato de fecha inválido. Use YYYY-MM-DD");
        }
        
        if ($fecha_obj->format('d') !== '01') {
            throw new Exception("La fecha de inicio debe ser el primer día del mes (YYYY-MM-01)");
        }
        
        $sql = "UPDATE configuracion_sistema 
                SET fecha_inicio_operaciones = :fecha 
                WHERE id = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['fecha' => $nueva_fecha]);
        
        $descripcion = "Período de operaciones actualizado. Nueva fecha inicio: $nueva_fecha";
        $sql_historico = "INSERT INTO historico_operaciones 
                         (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                         VALUES ('Actualización Periodo', :descripcion, :usuario_id, :usuario_nombre, :ip)";
        $stmt = $db->prepare($sql_historico);
        $stmt->execute([
            'descripcion' => $descripcion,
            'usuario_id' => $usuario_id,
            'usuario_nombre' => $usuario_nombre,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        $_SESSION['periodo_operaciones'] = [
            'fecha_inicio' => $nueva_fecha,
            'anio' => (int)$fecha_obj->format('Y'),
            'mes' => (int)$fecha_obj->format('m')
        ];
        
        if ($transaccion_propia) {
            $db->commit();
        }
        
        return [
            'success' => true,
            'mensaje' => "Período de operaciones actualizado a partir de $nueva_fecha",
            'nueva_fecha' => $nueva_fecha,
            'anio' => (int)$fecha_obj->format('Y'),
            'mes' => (int)$fecha_obj->format('m')
        ];
        
    } catch (Exception $e) {
        if ($transaccion_propia && $db->inTransaction()) {
            $db->rollBack();
        }

        return [
            'success' => false,
            'mensaje' => "Error: " . $e->getMessage()
        ];
    }
}

/**
 * CORREGIDA: Realiza el cierre de año con conteo preciso de facturas pagadas
 * Ahora usa la MISMA LÓGICA que el cierre de mes para mantener consistencia
 */
function realizarCierreAnio($anio, $usuario_id, $usuario_nombre, $db, $observaciones = '') {
    try {
        // ============ OBTENER ESTADÍSTICAS DEL AÑO ============
        // CORREGIDO: Usar la misma lógica simple que en cierre de mes
        $sql_stats_anio = "SELECT 
                            COUNT(*) as total_facturas,
                            COALESCE(SUM(total_general), 0) as importe_total,
                            
                            -- Facturas PAGADAS originalmente
                            COUNT(CASE WHEN estado = 'PAGADA' THEN 1 END) as cant_pagadas_original,
                            
                            -- Facturas CONTABILIZADAS originalmente
                            COUNT(CASE WHEN estado = 'CONTABILIZADA' THEN 1 END) as cant_contabilizadas_original,
                            
                            -- Facturas que YA ESTÁN CERRADAS (de meses anteriores)
                            COUNT(CASE WHEN estado = 'CERRADA' AND fecha_pago IS NOT NULL THEN 1 END) as cant_cerradas_pagadas,
                            COUNT(CASE WHEN estado = 'CERRADA' AND fecha_pago IS NULL THEN 1 END) as cant_cerradas_contabilizadas
                            
                          FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio 
                          AND estado != 'ANULADA'";
        
        $stmt_stats = $db->prepare($sql_stats_anio);
        $stmt_stats->execute(['anio' => $anio]);
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

        // Calcular totales incluyendo facturas ya cerradas de meses anteriores
        $total_facturas = (int)($stats['total_facturas'] ?? 0);
        $importe_anual = (float)($stats['importe_total'] ?? 0);
        
        // Sumar: Pagadas originales + Cerradas con pago
        $cant_pagadas = (int)($stats['cant_pagadas_original'] ?? 0) + (int)($stats['cant_cerradas_pagadas'] ?? 0);
        
        // Sumar: Contabilizadas originales + Cerradas sin pago
        $cant_contabilizadas = (int)($stats['cant_contabilizadas_original'] ?? 0) + (int)($stats['cant_cerradas_contabilizadas'] ?? 0);

        // DEBUG: Registrar los valores
        error_log("CIERRE AÑO {$anio}: Total={$total_facturas}, Pagadas={$cant_pagadas}, Contabilizadas={$cant_contabilizadas}, Importe={$importe_anual}");

        $db->beginTransaction();

        // ============ INSERTAR EN HISTORICO_CIERRES ============
        $sql_hist = "INSERT INTO historico_cierres 
                    (tipo, periodo_mes, periodo_anio, fecha_ejecucion, usuario_id, 
                     total_facturas, importe_total, cant_pagadas, cant_contabilizadas, observaciones) 
                    VALUES 
                    (2, NULL, :anio, NOW(), :user_id, 
                     :total_f, :importe, :pagadas, :contabilizadas, :obs)";
        
        $stmt_hist = $db->prepare($sql_hist);
        $stmt_hist->execute([
            'anio'            => $anio,
            'user_id'         => $usuario_id,
            'total_f'         => $total_facturas,
            'importe'         => $importe_anual,
            'pagadas'         => $cant_pagadas,
            'contabilizadas'  => $cant_contabilizadas,
            'obs'             => $observaciones
        ]);

        $cierre_id = $db->lastInsertId();

        // ============ ACTUALIZAR ESTADO DE FACTURAS ============
        // Cambiamos TODAS las facturas del año a estado 'CERRADA' (excepto anuladas)
        $sql_update = "UPDATE tbl_fact 
                       SET estado = 'CERRADA' 
                       WHERE YEAR(fecha_emision) = :anio 
                       AND estado != 'ANULADA'";
        $db->prepare($sql_update)->execute(['anio' => $anio]);

        // ============ ACTUALIZAR PERÍODO DE OPERACIONES ============
        $nuevo_anio = $anio + 1;
        $nueva_fecha = $nuevo_anio . "-01-01";
        
        $db->prepare("UPDATE configuracion_sistema SET fecha_inicio_operaciones = ? WHERE id = 1")
           ->execute([$nueva_fecha]);
        
        $_SESSION['periodo_operaciones'] = [
            'fecha_inicio' => $nueva_fecha, 
            'mes' => 1, 
            'anio' => $nuevo_anio
        ];

        // ============ REGISTRAR EN HISTÓRICO DE OPERACIONES ============
        $descripcion = "Cierre de año {$anio} realizado. Facturas: {$total_facturas}, Pagadas: {$cant_pagadas}, Contabilizadas: {$cant_contabilizadas}, Total: $" . number_format($importe_anual, 2);
        
        $sql_historico = "INSERT INTO historico_operaciones 
                         (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                         VALUES ('Cierre Anual', :descripcion, :usuario_id, :us_usuario_nombre, :ip)";
        $stmt_hist_op = $db->prepare($sql_historico);
        $stmt_hist_op->execute([
            'descripcion' => $descripcion,
            'usuario_id' => $usuario_id,
            'us_usuario_nombre' => $usuario_nombre,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $db->commit();
        
        return [
            'success' => true, 
            'cierre_id' => $cierre_id,
            'nuevo_periodo' => ['mes' => 1, 'anio' => $nuevo_anio], 
            'importe_anual' => $importe_anual,
            'estadisticas' => [
                'total_facturas' => $total_facturas,
                'pagadas' => $cant_pagadas,
                'contabilizadas' => $cant_contabilizadas
            ]
        ];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("ERROR en cierre de año: " . $e->getMessage());
        return [
            'success' => false, 
            'mensaje' => "Error en cierre de año: " . $e->getMessage()
        ];
    }
}

/**
 * CORREGIDA: Verifica si un año puede ser cerrado
 * Ahora cuenta correctamente las facturas pagadas basado en fecha_pago
 */
function verificarCierreAnio($anio, $db) {
    try {
        $periodo_actual = obtenerPeriodoOperaciones($db);
        if ($anio != $periodo_actual['anio']) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "Solo puede cerrar el año actual del período de operaciones (Año actual: " . $periodo_actual['anio'] . ")"
            ];
        }
        
        $sql = "SELECT id FROM historico_cierres 
                WHERE tipo = 2 
                AND periodo_anio = :anio";
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio' => $anio]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "Este año ya fue cerrado anteriormente"
            ];
        }
        
        // Verificar meses cerrados
        $meses_pendientes = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $sql = "SELECT id FROM historico_cierres 
                    WHERE tipo = 1 
                    AND periodo_mes = :mes 
                    AND periodo_anio = :anio";
            $stmt = $db->prepare($sql);
            $stmt->execute(['mes' => $mes, 'anio' => $anio]);
            
            if ($stmt->rowCount() == 0) {
                $meses_pendientes[] = $mes;
            }
        }
        
        if (!empty($meses_pendientes)) {
            $meses_nombres = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            ];
            
            $meses_lista = array_map(function($m) use ($meses_nombres) {
                return $meses_nombres[$m];
            }, $meses_pendientes);
            
            return [
                'puede_cerrar' => false,
                'mensaje' => "Debe cerrar todos los meses primero. Meses pendientes: " . implode(', ', $meses_lista)
            ];
        }
        
        // ============ CORREGIDO: Estadísticas precisas para vista previa ============
        $sql = "SELECT 
                COUNT(*) as total_facturas,
                COALESCE(SUM(total_general), 0) as importe_total,
                
                -- CORRECCIÓN: Facturas PAGADAS (tienen fecha_pago)
                COUNT(CASE WHEN 
                    (estado = 'PAGADA') OR 
                    (estado = 'CERRADA' AND fecha_pago IS NOT NULL)
                THEN 1 END) as cant_pagadas,
                
                -- CORRECCIÓN: Facturas CONTABILIZADAS pero NO PAGADAS
                COUNT(CASE WHEN 
                    (estado = 'CONTABILIZADA') OR 
                    (estado = 'CERRADA' AND fecha_pago IS NULL)
                THEN 1 END) as cant_contabilizadas
                
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) = :anio
                AND estado != 'ANULADA'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio' => $anio]);
        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // DEBUG
        error_log("VERIFICAR AÑO {$anio}: Total={$estadisticas['total_facturas']}, Pagadas={$estadisticas['cant_pagadas']}, Contabilizadas={$estadisticas['cant_contabilizadas']}");
        
        return [
            'puede_cerrar' => true,
            'estadisticas' => $estadisticas,
            'mensaje' => "Año listo para cierre"
        ];
        
    } catch (Exception $e) {
        error_log("Error verificando cierre de año: " . $e->getMessage());
        return [
            'puede_cerrar' => false,
            'mensaje' => "Error al verificar: " . $e->getMessage()
        ];
    }
}

/**
 * CORREGIDA DEFINITIVAMENTE: Realiza el cierre de mes con conteo preciso de facturas pagadas
 * Ahora cuenta TODAS las facturas del mes, independientemente de su estado actual
 */
function realizarCierreMes($mes, $anio, $usuario_id, $usuario_nombre, $db, $observaciones = '') {
    try {
        // ============ OBTENER ESTADÍSTICAS DEL MES - CORREGIDO ============
        $sql_stats = "SELECT 
                        COUNT(*) as total_facturas,
                        COALESCE(SUM(total_general), 0) as importe_total,
                        
                        -- CORRECCIÓN RADICAL: Una factura es PAGADA si tiene fecha_pago NO vacía
                        COUNT(CASE WHEN 
                            fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00'
                        THEN 1 END) as cant_pagadas,
                        
                        -- CORRECCIÓN: Una factura es CONTABILIZADA si NO tiene fecha_pago
                        COUNT(CASE WHEN 
                            (fecha_pago IS NULL OR fecha_pago = '0000-00-00') 
                            AND estado != 'PENDIENTE'
                            AND estado != 'ANULADA'
                        THEN 1 END) as cant_contabilizadas
                        
                      FROM tbl_fact 
                      WHERE MONTH(fecha_emision) = :mes 
                      AND YEAR(fecha_emision) = :anio 
                      AND estado != 'ANULADA'";  // QUITAMOS el filtro de estados - tomamos TODAS las facturas no anuladas

        $stmt_stats = $db->prepare($sql_stats);
        $stmt_stats->execute(['mes' => $mes, 'anio' => $anio]);
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

        // Aseguramos que los valores sean numéricos
        $total_facturas = (int)($stats['total_facturas'] ?? 0);
        $importe_total = (float)($stats['importe_total'] ?? 0);
        $cant_pagadas = (int)($stats['cant_pagadas'] ?? 0);
        $cant_contabilizadas = (int)($stats['cant_contabilizadas'] ?? 0);

        // DEBUG: Registrar valores
        error_log("CIERRE MES {$anio}-{$mes}: Total={$total_facturas}, Pagadas={$cant_pagadas}, Contabilizadas={$cant_contabilizadas}, Importe={$importe_total}");

        $db->beginTransaction();

        // ============ INSERTAR EN HISTORICO_CIERRES ============
        $sql_hist = "INSERT INTO historico_cierres 
                    (tipo, periodo_mes, periodo_anio, fecha_ejecucion, usuario_id, 
                     total_facturas, importe_total, cant_pagadas, cant_contabilizadas, observaciones) 
                    VALUES 
                    (1, :mes, :anio, NOW(), :user_id, 
                     :total_f, :importe, :pagadas, :contabilizadas, :obs)";
        
        $stmt_hist = $db->prepare($sql_hist);
        $stmt_hist->execute([
            'mes'             => $mes,
            'anio'            => $anio,
            'user_id'         => $usuario_id,
            'total_f'         => $total_facturas,
            'importe'         => $importe_total,
            'pagadas'         => $cant_pagadas,
            'contabilizadas'  => $cant_contabilizadas,
            'obs'             => $observaciones
        ]);

        $cierre_id = $db->lastInsertId();

        // ============ ACTUALIZAR ESTADO DE FACTURAS ============
        // Actualizamos SOLO las facturas que están en CONTABILIZADA o PAGADA a CERRADA
        // Las que ya están CERRADA se quedan como están
        $sql_update_fact = "UPDATE tbl_fact 
                           SET estado = 'CERRADA' 
                           WHERE MONTH(fecha_emision) = :mes 
                           AND YEAR(fecha_emision) = :anio 
                           AND estado IN ('PAGADA', 'CONTABILIZADA')";
        $db->prepare($sql_update_fact)->execute(['mes' => $mes, 'anio' => $anio]);

        // ============ ACTUALIZAR CONFIGURACIÓN DE PERÍODO ============
        if ($mes == 12) {
            $nuevo_mes = 12;
            $nuevo_anio = $anio;
            $es_fin_anio = true;
        } else {
            $nuevo_mes = $mes + 1;
            $nuevo_anio = $anio;
            $es_fin_anio = false;
        }

        if (!$es_fin_anio) {
            $nueva_fecha = sprintf("%04d-%02d-01", $nuevo_anio, $nuevo_mes);
            $db->prepare("UPDATE configuracion_sistema SET fecha_inicio_operaciones = ? WHERE id = 1")
               ->execute([$nueva_fecha]);
            
            $_SESSION['periodo_operaciones'] = [
                'fecha_inicio' => $nueva_fecha,
                'mes' => $nuevo_mes,
                'anio' => $nuevo_anio
            ];
        }

        // ============ REGISTRAR EN HISTÓRICO DE OPERACIONES ============
        $descripcion = "Cierre de mes {$mes}/{$anio} realizado. Facturas: {$total_facturas}, Pagadas: {$cant_pagadas}, Contabilizadas: {$cant_contabilizadas}, Total: $" . number_format($importe_total, 2);
        
        $sql_historico = "INSERT INTO historico_operaciones 
                         (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                         VALUES ('Cierre Mensual', :descripcion, :usuario_id, :usuario_nombre, :ip)";
        $stmt_hist_op = $db->prepare($sql_historico);
        $stmt_hist_op->execute([
            'descripcion' => $descripcion,
            'usuario_id' => $usuario_id,
            'usuario_nombre' => $usuario_nombre,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $db->commit();
        
        return [
            'success' => true, 
            'cierre_id' => $cierre_id, 
            'es_fin_anio' => $es_fin_anio, 
            'proximo_periodo' => ['mes' => $nuevo_mes, 'anio' => $nuevo_anio],
            'estadisticas' => [
                'total_facturas' => $total_facturas, 
                'importe_total' => $importe_total,
                'pagadas' => $cant_pagadas,
                'contabilizadas' => $cant_contabilizadas
            ]
        ];

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("ERROR en cierre de mes: " . $e->getMessage());
        return [
            'success' => false, 
            'mensaje' => "Error en cierre de mes: " . $e->getMessage()
        ];
    }
}



/**
 * CORREGIDA: Verifica si un mes puede ser cerrado
 */
function verificarCierreMes($mes, $anio, $db) {
    try {
        $periodo_actual = obtenerPeriodoOperaciones($db);
        $anio_actual = $periodo_actual['anio'];
        $mes_actual = $periodo_actual['mes'];
        
        if ($anio > $anio_actual || ($anio == $anio_actual && $mes > $mes_actual)) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "No se puede cerrar un período futuro",
                'anio_actual' => $anio_actual,
                'mes_actual' => $mes_actual
            ];
        }
        
        // Verificar si el mes ya fue cerrado
        $sql = "SELECT id FROM historico_cierres 
                WHERE tipo = 1 
                AND periodo_mes = :mes 
                AND periodo_anio = :anio";
        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "Este mes ya fue cerrado anteriormente",
                'anio_actual' => $anio_actual,
                'mes_actual' => $mes_actual
            ];
        }
        
        // Verificar facturas pendientes
        $sql = "SELECT COUNT(*) as pendientes 
                FROM tbl_fact 
                WHERE MONTH(fecha_emision) = :mes 
                AND YEAR(fecha_emision) = :anio 
                AND estado = 'PENDIENTE'";
        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['pendientes'] > 0) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "Hay {$result['pendientes']} facturas pendientes de contabilizar",
                'anio_actual' => $anio_actual,
                'mes_actual' => $mes_actual
            ];
        }
        
        // ============ CORREGIDO: Estadísticas precisas del mes ============
        $sql = "SELECT 
                COUNT(*) as total_facturas,
                COALESCE(SUM(total_general), 0) as importe_total,
                
                -- CORRECCIÓN: Una factura es PAGADA si tiene fecha_pago
                COUNT(CASE WHEN 
                    fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00'
                THEN 1 END) as cant_pagadas,
                
                -- CORRECCIÓN: Una factura es CONTABILIZADA si NO tiene fecha_pago
                COUNT(CASE WHEN 
                    (fecha_pago IS NULL OR fecha_pago = '0000-00-00')
                    AND estado != 'PENDIENTE'
                    AND estado != 'ANULADA'
                THEN 1 END) as cant_contabilizadas
                
                FROM tbl_fact 
                WHERE MONTH(fecha_emision) = :mes 
                AND YEAR(fecha_emision) = :anio 
                AND estado != 'ANULADA'";  // QUITAMOS el filtro de estados
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes, 'anio' => $anio]);
        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // DEBUG
        error_log("VERIFICAR MES {$anio}-{$mes}: Total={$estadisticas['total_facturas']}, Pagadas={$estadisticas['cant_pagadas']}, Contabilizadas={$estadisticas['cant_contabilizadas']}");
        
        return [
            'puede_cerrar' => true,
            'estadisticas' => $estadisticas,
            'mensaje' => "Mes listo para cierre",
            'anio_actual' => $anio_actual,
            'mes_actual' => $mes_actual
        ];
        
    } catch (Exception $e) {
        error_log("Error verificando cierre de mes: " . $e->getMessage());
        return [
            'puede_cerrar' => false,
            'mensaje' => "Error al verificar: " . $e->getMessage(),
            'anio_actual' => date('Y'),
            'mes_actual' => date('m')
        ];
    }
}



/**
 * CORREGIDA: Obtiene los cierres realizados
 */

function obtenerCierresRealizados($db, $tipo = null, $limite = 20) {
    // Validar que $db sea válido
    if ($db === null) {
        error_log("Error: Conexión a BD es null en obtenerCierresRealizados");
        return [];
    }
    
    try {
        $sql = "SELECT hc.*, CONCAT(cu.nombre, ' ', cu.apellidos) as usuario_nombre, cu.usuario as usuario_login
                FROM historico_cierres hc
                JOIN clasif_usuarios cu ON hc.usuario_id = cu.id";
        
        $params = [];
        
        if ($tipo) {
            $sql .= " WHERE hc.tipo = :tipo";
            $params['tipo'] = (int)$tipo;
        }
        
        $sql .= " ORDER BY hc.fecha_ejecucion DESC LIMIT :limite";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error en obtenerCierresRealizados: " . $e->getMessage());
        return [];
    }
}

/**
 * CORREGIDA: Genera reporte de cierre
 */
function generarReporteCierre($tipo, $periodo_anio, $db, $periodo_mes = null) {
    try {
        if ($tipo == 1) {
            $sql = "SELECT 
                    f.no_fact,
                    c.nombre as cliente,
                    DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as fecha_emision,
                    f.total_general,
                    f.estado,
                    f.Ref_pago,
                    DATE_FORMAT(f.fecha_contabilizacion, '%d/%m/%Y %H:%i') as fecha_contabilizacion,
                    DATE_FORMAT(f.fecha_pago, '%d/%m/%Y') as fecha_pago,
                    GROUP_CONCAT(DISTINCT s.descripcion SEPARATOR ', ') as servicios
                    FROM tbl_fact f
                    LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                    LEFT JOIN tbl_fact_detalle fd ON f.id = fd.factura_id
                    LEFT JOIN clasif_serv s ON fd.servicio_id = s.id
                    WHERE MONTH(f.fecha_emision) = :mes 
                    AND YEAR(f.fecha_emision) = :anio
                    GROUP BY f.id
                    ORDER BY f.fecha_emision";
            
            $stmt = $db->prepare($sql);
            $stmt->execute(['mes' => $periodo_mes, 'anio' => $periodo_anio]);
            $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            $sql = "SELECT 
                    MONTH(f.fecha_emision) as mes,
                    COUNT(*) as cantidad_facturas,
                    COALESCE(SUM(f.total_general), 0) as importe_total,
                    COUNT(CASE WHEN f.estado = 'CERRADA' THEN 1 END) as cerradas,
                    COUNT(CASE WHEN f.estado = 'PAGADA' THEN 1 END) as pagadas
                    FROM tbl_fact f
                    WHERE YEAR(f.fecha_emision) = :anio
                    GROUP BY MONTH(f.fecha_emision)
                    ORDER BY MONTH(f.fecha_emision)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute(['anio' => $periodo_anio]);
            $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $sql_cierre = "SELECT * FROM historico_cierres 
                      WHERE tipo = :tipo 
                      AND periodo_anio = :anio";
        
        if ($tipo == 1) {
            $sql_cierre .= " AND periodo_mes = :mes";
        }
        
        $stmt = $db->prepare($sql_cierre);
        $params = ['tipo' => (int)$tipo, 'anio' => $periodo_anio];
        
        if ($tipo == 1) {
            $params['mes'] = $periodo_mes;
        }
        
        $stmt->execute($params);
        $cierre_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'tipo' => $tipo,
            'tipo_legible' => ($tipo == 1) ? 'Mensual' : 'Anual',
            'periodo_mes' => $periodo_mes,
            'periodo_anio' => $periodo_anio,
            'detalle' => $detalle,
            'cierre_info' => $cierre_info,
            'fecha_generacion' => date('d/m/Y H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

/**
 * CORREGIDA: Verifica si un período está cerrado
 */
function verificarPeriodoCerrado($tipo, $periodo_anio, $db, $periodo_mes = null) {
    try {
        $sql = "SELECT id, fecha_ejecucion, usuario_id 
                FROM historico_cierres 
                WHERE tipo = :tipo 
                AND periodo_anio = :anio";
        
        if ($tipo == 1) {
            $sql .= " AND periodo_mes = :mes";
        }
        
        $stmt = $db->prepare($sql);
        $params = ['tipo' => (int)$tipo, 'anio' => $periodo_anio];
        
        if ($tipo == 1) {
            $params['mes'] = $periodo_mes;
        }
        
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtiene los años disponibles para cierre
 */
function obtenerAniosDisponiblesCierre($db) {
    try {
        $periodo_actual = obtenerPeriodoOperaciones($db);
        
        $sql = "SELECT DISTINCT YEAR(fecha_emision) as anio 
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) <= :anio_actual 
                ORDER BY YEAR(fecha_emision) DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio_actual' => $periodo_actual['anio']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}
/**
 * Verifica si existen operaciones (facturas) en un año específico
 */
function verificarOperacionesEnAnio($anio, $db) {
    try {
        $sql = "SELECT COUNT(*) as total 
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) = :anio";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio' => $anio]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] > 0;
        
    } catch (Exception $e) {
        error_log("Error verificando operaciones: " . $e->getMessage());
        return false;
    }
}
?>