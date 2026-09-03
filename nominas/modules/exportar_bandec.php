<?php
//exportar_bandec.php - EXPORTAR DATOS ACREDITACION
header('Content-Type: application/json');

// Incluir configuración de base de datos
require_once '../config/database.php';
require_once '../includes/funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Su sesión ha expirado o no está autenticado. Inicie sesión nuevamente para continuar.']);
    exit;
}

// Control de acceso por rol (exportación de nómina BANDEC)
if (!permiso_puede('bandecnom', 'exportar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tiene permisos suficientes para exportar la nómina BANDEC. Contacte al administrador del sistema.']);
    exit;
}

// ========================
// 1. OBTENER TIPOS DE NÓMINA DISPONIBLES
// ========================
if (isset($_GET['accion']) && $_GET['accion'] === 'tipos_nomina') {
    $sql = "SELECT DISTINCT tipo_nomina 
            FROM nominas 
            ORDER BY tipo_nomina";
    
    $stmt = $pdo->query($sql);
    $tipos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'tipos' => $tipos
    ]);
    exit;
}

// ========================
// OBTENER AÑOS DISPONIBLES
// ========================
if (isset($_GET['accion']) && $_GET['accion'] === 'anios') {
    $sql = "SELECT DISTINCT YEAR(periodo_desde) as anio
            FROM nominas 
            WHERE estado = 'contabilizado'
            ORDER BY anio DESC";
    
    $stmt = $pdo->query($sql);
    $anios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'anios' => $anios
    ]);
    exit;
}

// ========================
// 2. OBTENER PERÍODOS POR TIPO DE NÓMINA Y AÑO
// ========================
if (isset($_GET['accion']) && $_GET['accion'] === 'periodos') {
    $tipoNomina = $_GET['tipo_nomina'] ?? '';
    $anio = $_GET['anio'] ?? '';
    
    $sql = "SELECT 
                n.periodo_desde,
                n.periodo_hasta,
                MIN(n.numero_nomina) as numero_nomina
            FROM nominas n
            WHERE n.tipo_nomina = ?";
    
    $params = [$tipoNomina];
    
    if ($anio) {
        $sql .= " AND YEAR(n.periodo_desde) = ?";
        $params[] = $anio;
    }
    
    $sql .= " GROUP BY n.periodo_desde, n.periodo_hasta
              ORDER BY n.periodo_desde DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $periodos = $stmt->fetchAll();
    
    $periodosFormateados = [];
    
    foreach ($periodos as $p) {
        $desde = new DateTime($p['periodo_desde']);
        $hasta = new DateTime($p['periodo_hasta']);
        
        $label = $desde->format('d/m/Y') . " - " . $hasta->format('d/m/Y');
        if (!empty($p['numero_nomina'])) {
            $label .= " (" . $p['numero_nomina'] . ")";
        }
        
        $periodosFormateados[] = [
            'periodo_desde' => $p['periodo_desde'],
            'periodo_hasta' => $p['periodo_hasta'],
            'label' => $label
        ];
    }
    
    echo json_encode([
        'success' => true,
        'periodos' => $periodosFormateados
    ]);
    exit;
}

// ========================
// 2b. VERIFICAR CUADRE EN LOTE (todos los períodos de un tipo+año)
// ========================
if (isset($_GET['accion']) && $_GET['accion'] === 'verificar_cuadre_lote') {
    $tipoNomina = $_GET['tipo_nomina'] ?? '';
    $anio = $_GET['anio'] ?? '';

    $cfg = [];
    try {
        $st = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('dias_mensuales','horas_jornada_diaria','recargo_nocturno')");
        while ($rw = $st->fetch()) { $cfg[$rw['parametro']] = $rw['valor']; }
    } catch (Exception $e) {}
    $dm = floatval($cfg['dias_mensuales'] ?? 0) ?: 24;
    $jn = floatval($cfg['horas_jornada_diaria'] ?? 0) ?: 8;
    try { $fc = floatval($pdo->query("SELECT factor_calculo FROM configuracion_vacaciones WHERE activo = 1 LIMIT 1")->fetchColumn()) ?: 0.0909; } catch (Exception $e) { $fc = 0.0909; }
    try { $ts = floatval($pdo->query("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1")->fetchColumn()) ?: 5; } catch (Exception $e) { $ts = 5; }
    try { $rg = $pdo->query("SELECT * FROM configuracion_rangos_impuesto ORDER BY desde")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $rg = []; }

    function _rxc($n, $p = 2) { $m = pow(10, $p); return floor($n * $m + 0.5) / $m; }
    function _cessc($s) { if ($s <= 15000) return _rxc($s * 0.05, 2); return _rxc(750 + (($s - 15000) * 0.10), 2); }
    function _impc($s, $r) { $b = max(0, $s - 10000); $i = 0; foreach ($r as $x) { $d = floatval($x['desde']); $h = floatval($x['hasta'] ?? PHP_INT_MAX); $t = floatval($x['tasa']); $tr = min($b, $h) - $d; if ($tr > 0) $i += $tr * ($t / 100); $b -= $tr; if ($b <= 0) break; } return _rxc($i, 2); }

    $sql = "SELECT periodo_desde, periodo_hasta FROM nominas WHERE tipo_nomina = ?";
    $params = [$tipoNomina];
    if ($anio) { $sql .= " AND YEAR(periodo_desde) = ?"; $params[] = $anio; }
    $sql .= " GROUP BY periodo_desde, periodo_hasta";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $periodos = $st->fetchAll();

    $resultado = [];
    foreach ($periodos as $p) {
        $pd = $p['periodo_desde']; $ph = $p['periodo_hasta'];
        $rows = $pdo->prepare("SELECT n.*, t.no_acumular_vacaciones, e.salario_hora_ordinaria, e.salario_mensual
                               FROM nominas n JOIN trabajadores t ON t.id = n.trabajador_id
                               LEFT JOIN escalas_salariales e ON e.id = t.escala_salarial_id
                               WHERE DATE(n.periodo_desde)=DATE(?) AND DATE(n.periodo_hasta)=DATE(?) AND n.tipo_nomina=? AND n.estado='contabilizado'");
        $rows->execute([$pd, $ph, $tipoNomina]);
        $data = $rows->fetchAll(PDO::FETCH_ASSOC);
        $err = 0;
        foreach ($data as $n) {
            $td = $n['tipo_descuento'] ?? 'solo_cess';
            $noac = intval($n['no_acumular_vacaciones'] ?? 0);
            $horas = floatval($n['horas_laboradas'] ?? 0);
            $sm = floatval($n['salario_mensual'] ?? 0);
            $descuentos = floatval($n['descuentos'] ?? 0);
            $od = floatval($n['otras_deducciones'] ?? 0);
            $dev = floatval($n['total_salario_devengado']);

            if ($tipoNomina == 'automatica') {
                $vaca = 0;
                if ($noac == 1) $vaca = _rxc(_rxc(($horas * $fc) / $jn, 2) * ($sm / $dm), 2);
                $dev_ok = _rxc(floatval($n['importe_salario_laboral']) + floatval($n['importe_horas_nocturnas']) + floatval($n['importe_dias_feriados']) + floatval($n['otros_salarios']) + $vaca, 2);
            } elseif ($tipoNomina == 'extraordinaria') {
                $dev_ok = _rxc(floatval($n['importe_salario_laboral']) + floatval($n['importe_horas_nocturnas']), 2);
            } elseif ($tipoNomina == 'vacaciones') {
                $dev_ok = floatval($n['importe_vacaciones']);
            } elseif ($tipoNomina == 'bono') {
                $dev_ok = floatval($n['pago_resultado']);
            } else {
                $dev_ok = _rxc(floatval($n['pago_resultado']) + floatval($n['otros_salarios']), 2);
            }
            $cc = ($td == 'solo_cess') ? _cessc($dev) : _rxc($dev * ($ts / 100), 2);
            $ic = ($td == 'solo_cess') ? 0 : _impc($dev, $rg);
            $nc = _rxc($dev - $cc - $ic - $descuentos - $od, 2);
            if (abs(round($dev, 2) - round($dev_ok, 2)) > 0.02 ||
                abs(round(floatval($n['contribucion_especial']), 2) - round($cc, 2)) > 0.001 ||
                abs(round(floatval($n['importe_neto']), 2) - round($nc, 2)) > 0.02) {
                $err++;
            }
        }
        $resultado[] = [
            'periodo_desde' => $pd,
            'periodo_hasta' => $ph,
            'con_errores' => $err > 0,
            'filas_error' => $err
        ];
    }

    // Verificación de cierres por período: si algún cierre del tipo en el período
    // descuadra, el período se marca como descuadrado para bloquear la exportación.
    foreach ($resultado as &$res) {
        try {
            $rc = verificarCuadreCierres($pdo, [
                'periodo_desde' => $res['periodo_desde'],
                'periodo_hasta' => $res['periodo_hasta'],
                'tipo' => $tipoNomina,
            ]);
            $res['cierres_total'] = intval($rc['cierres']);
            $res['cierres_con_error'] = intval($rc['cierres_con_error']);
            if ($res['cierres_con_error'] > 0) {
                $res['con_errores'] = true;
            }
        } catch (Exception $e) {
            $res['cierres_total'] = 0;
            $res['cierres_con_error'] = 0;
        }
    }
    unset($res);

    echo json_encode(['success' => true, 'resultados' => $resultado]);
    exit;
}

// ========================
// 3. SI ES SOLO PARA CONTAR (utilizado en el flujo original, se mantiene)
// ========================
if (isset($_POST['accion']) && $_POST['accion'] === 'contar') {
    $periodoDesde = $_POST['periodo_desde'] ?? '';
    $periodoHasta = $_POST['periodo_hasta'] ?? '';
    $tipoNomina = $_POST['tipo_nomina'] ?? '';
    
    $sqlCount = "SELECT COUNT(DISTINCT n.trabajador_id) as total
                 FROM nominas n
                 INNER JOIN trabajadores t ON n.trabajador_id = t.id
                 WHERE n.periodo_desde = ?
                 AND n.periodo_hasta = ?
                 AND n.tipo_nomina = ?
                 AND n.estado = 'contabilizado'
                 AND t.cuentabanc IS NOT NULL AND t.cuentabanc != ''
                 AND t.ci IS NOT NULL AND t.ci != ''";
    
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => ($total > 0),
        'total' => $total,
        'mensaje' => $total > 0 ? '' : 'No hay trabajadores con tarjeta (cuenta bancaria) y CI registrados para este período'
    ]);
    exit;
}

// ========================
// CALCULAR TOTALES PARA EL RESUMEN (CORRECCIÓN DE SUMA DE COLUMNAS)
// ========================
if (isset($_POST['accion']) && $_POST['accion'] === 'calcular_totales') {
    $periodoDesde = $_POST['periodo_desde'] ?? '';
    $periodoHasta = $_POST['periodo_hasta'] ?? '';
    $tipoNomina = $_POST['tipo_nomina'] ?? '';

    // 1. TOTAL DEVENGADO GENERAL (Todos los trabajadores en esta nómina)
    $sqlTotalGeneral = "SELECT COALESCE(SUM(n.total_salario_devengado), 0) as total_devengado_general
                        FROM nominas n
                        INNER JOIN trabajadores t ON n.trabajador_id = t.id
                        WHERE DATE(n.periodo_desde) = DATE(?)
                          AND DATE(n.periodo_hasta) = DATE(?)
                          AND n.tipo_nomina = ?
                          AND n.estado = 'contabilizado'";
    $stmtGeneral = $pdo->prepare($sqlTotalGeneral);
    $stmtGeneral->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $total_devengado_general = $stmtGeneral->fetchColumn();

    // 2. TOTAL DEDUCCIONES (Corregido: COALESCE en cada columna para evitar el error de NULL)
    // Sumamos las deducciones de TODOS los trabajadores de la nómina
    $sqlTotalDeducciones = "SELECT COALESCE(SUM(
                                COALESCE(n.contribucion_especial, 0) + 
                                COALESCE(n.ingresos_personales, 0) + 
                                COALESCE(n.descuentos, 0)
                            ), 0) as total_deducciones_general
                            FROM nominas n
                            INNER JOIN trabajadores t ON n.trabajador_id = t.id
                            WHERE DATE(n.periodo_desde) = DATE(?)
                              AND DATE(n.periodo_hasta) = DATE(?)
                              AND n.tipo_nomina = ?
                              AND n.estado = 'contabilizado'";
    $stmtDed = $pdo->prepare($sqlTotalDeducciones);
    $stmtDed->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $total_deducciones_general = $stmtDed->fetchColumn();

    // 3. DATOS DE TRABAJADORES CON TARJETA (Lo que va al Banco)
    $sqlConTarjeta = "SELECT 
                        COUNT(DISTINCT n.trabajador_id) as con_tarjeta_count,
                        COALESCE(SUM(n.importe_neto), 0) as neto_banco
                      FROM nominas n
                      INNER JOIN trabajadores t ON n.trabajador_id = t.id
                      WHERE DATE(n.periodo_desde) = DATE(?)
                        AND DATE(n.periodo_hasta) = DATE(?)
                        AND n.tipo_nomina = ?
                        AND n.estado = 'contabilizado'
                        AND t.cuentabanc IS NOT NULL AND t.cuentabanc != ''
                        AND t.ci IS NOT NULL AND t.ci != ''";
    $stmtCon = $pdo->prepare($sqlConTarjeta);
    $stmtCon->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $rowCon = $stmtCon->fetch(PDO::FETCH_ASSOC);
    
    $con_tarjeta_count = $rowCon['con_tarjeta_count'];
    $importe_acreditar = $rowCon['neto_banco'];

    // 4. DATOS DE TRABAJADORES SIN TARJETA (Lo que se paga en efectivo/caja)
    $sqlSinTarjeta = "SELECT 
                        COUNT(DISTINCT n.trabajador_id) as sin_tarjeta_count,
                        COALESCE(SUM(n.importe_neto), 0) as neto_caja
                      FROM nominas n
                      INNER JOIN trabajadores t ON n.trabajador_id = t.id
                      WHERE DATE(n.periodo_desde) = DATE(?)
                        AND DATE(n.periodo_hasta) = DATE(?)
                        AND n.tipo_nomina = ?
                        AND n.estado = 'contabilizado'
                        AND (t.cuentabanc IS NULL OR t.cuentabanc = '' OR t.ci IS NULL OR t.ci = '')";
    $stmtSin = $pdo->prepare($sqlSinTarjeta);
    $stmtSin->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $rowSin = $stmtSin->fetch(PDO::FETCH_ASSOC);
    
    $sin_tarjeta_count = $rowSin['sin_tarjeta_count'];
    $total_sin_tarjeta_monto = $rowSin['neto_caja'];

    // 5. TOTAL TRABAJADORES
    $totalNominaCount = $con_tarjeta_count + $sin_tarjeta_count;

    echo json_encode([
        'success' => true,
        'total_devengado' => (float)$total_devengado_general,
        'total_deducciones' => (float)$total_deducciones_general,
        'total_sin_tarjeta_monto' => (float)$total_sin_tarjeta_monto,
        'importe_acreditar' => (float)$importe_acreditar,
        'con_tarjeta_count' => intval($con_tarjeta_count),
        'sin_tarjeta_count' => intval($sin_tarjeta_count),
        'total_nomina_count' => intval($totalNominaCount)
    ]);
    exit;
}

// ========================
// 5. VERIFICAR SI LA NÓMINA ESTÁ CONTABILIZADA
// ========================
if (isset($_POST['accion']) && $_POST['accion'] === 'verificar_contabilizada') {
    $periodoDesde = $_POST['periodo_desde'] ?? '';
    $periodoHasta = $_POST['periodo_hasta'] ?? '';
    $tipoNomina = $_POST['tipo_nomina'] ?? '';
    
    // Consulta SIMPLE - solo verificar si existe alguna nómina contabilizada
    $sql = "SELECT COUNT(*) as total
            FROM nominas n
            WHERE n.periodo_desde = ?
              AND n.periodo_hasta = ?
              AND n.tipo_nomina = ?
              AND n.estado = 'contabilizado'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result['total'];
    
    echo json_encode([
        'success' => true,
        'contabilizada' => ($total > 0),
        'total_registros' => intval($total),
        'debug' => [
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
            'tipo_nomina' => $tipoNomina
        ]
    ]);
    exit;
}

// ========================
// PREVISUALIZACIÓN DE DATOS (CORREGIDO)
// ========================
if (isset($_POST['accion']) && $_POST['accion'] === 'preview') {
    $periodoDesde = $_POST['periodo_desde'] ?? '';
    $periodoHasta = $_POST['periodo_hasta'] ?? '';
    $tipoNomina = $_POST['tipo_nomina'] ?? '';
    $idsExcluirRaw = $_POST['ids_excluir'] ?? '';
    $idsExcluir = [];
    if ($idsExcluirRaw !== '') {
        $idsExcluir = array_filter(array_map('intval', explode(',', $idsExcluirRaw)));
    }
    $excluirSql = '';
    $excluirParams = [];
    if (!empty($idsExcluir)) {
        $ph = implode(',', array_fill(0, count($idsExcluir), '?'));
        $excluirSql = " AND n.trabajador_id NOT IN ($ph)";
        $excluirParams = $idsExcluir;
    }
    
    // Registros CON TARJETA
    $sqlCon = "SELECT t.ci, t.cuentabanc, n.importe_neto as importe, t.nombre_completo
               FROM nominas n
               INNER JOIN trabajadores t ON n.trabajador_id = t.id
               WHERE DATE(n.periodo_desde) = DATE(?)
                 AND DATE(n.periodo_hasta) = DATE(?)
                 AND n.tipo_nomina = ?
                 AND n.estado = 'contabilizado'
                 AND t.cuentabanc IS NOT NULL AND t.cuentabanc != ''
                 $excluirSql
               ORDER BY t.nombre_completo";
               
    $stmtCon = $pdo->prepare($sqlCon);
    $stmtCon->execute(array_merge([$periodoDesde, $periodoHasta, $tipoNomina], $excluirParams));
    $registros = $stmtCon->fetchAll(PDO::FETCH_ASSOC);

    // Registros SIN TARJETA
    $sqlSin = "SELECT t.ci, n.total_salario_devengado as devengado, 
                      (n.contribucion_especial + n.ingresos_personales + n.descuentos) as deducciones, 
                      n.importe_neto as neto, t.nombre_completo
               FROM nominas n
               INNER JOIN trabajadores t ON n.trabajador_id = t.id
               WHERE DATE(n.periodo_desde) = DATE(?)
                 AND DATE(n.periodo_hasta) = DATE(?)
                 AND n.tipo_nomina = ?
                 AND n.estado = 'contabilizado'
                 AND (t.cuentabanc IS NULL OR t.cuentabanc = '')
                 $excluirSql
               ORDER BY t.nombre_completo";
               
    $stmtSin = $pdo->prepare($sqlSin);
    $stmtSin->execute(array_merge([$periodoDesde, $periodoHasta, $tipoNomina], $excluirParams));
    $registros_sin_tarjeta = $stmtSin->fetchAll(PDO::FETCH_ASSOC);
    
    $total_importe = 0;
    foreach($registros as $r) $total_importe += $r['importe'];

    echo json_encode([
        'success' => true,
        'total_registros' => count($registros),
        'total_importe' => (float)$total_importe,
        'registros' => $registros,
        'registros_sin_tarjeta' => $registros_sin_tarjeta
    ]);
    exit;
}

// ========================
// 5b. EXPORTAR PLANTILLAS EN BLANCO (DBF / XLSX / XML)
// ========================
if (isset($_POST['accion']) && in_array($_POST['accion'], ['plantilla_dbf', 'plantilla_xlsx', 'plantilla_xml'])) {
    $accionPlantilla = $_POST['accion'];
    $carpeta = __DIR__ . '/exports/';
    if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

    if ($accionPlantilla === 'plantilla_dbf') {
        $fields = [
            ['NID',     'C', 11, 0],
            ['CUENTA',  'C', 16, 0],
            ['IMPORTE', 'N', 18, 2]
        ];
        $nombreArchivo = 'nomina_plantilla_' . date('Ymd_His');
        $archivoSalida = generarDbf($carpeta, $nombreArchivo, $fields, []);
        $extension = 'dbf';
    } elseif ($accionPlantilla === 'plantilla_xlsx') {
        if (!file_exists('../vendor/autoload.php')) {
            echo json_encode(['success' => false, 'mensaje' => 'PhpSpreadsheet no instalado. Ejecuta: composer require phpoffice/phpspreadsheet']);
            exit;
        }
        require_once '../vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('nomina');
        $sheet->setCellValue('A1', 'Nid');
        $sheet->setCellValue('B1', 'Cuenta');
        $sheet->setCellValue('C1', 'Importe');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(14);

        $nombreArchivo = 'nomina_plantilla_' . date('Ymd_His');
        $archivoSalida = $carpeta . $nombreArchivo . '.xlsx';
        try {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($archivoSalida);
        } catch (\Exception $e) {
            $archivoSalida = false;
        }
        $extension = 'xlsx';
    } else { // plantilla_xml
        $xml = '<acreditacion>' . "\n";
        $xml .= '  <registro  Nid="" Cuenta="" Importe="" />' . "\n";
        $xml .= '</acreditacion>' . "\n";

        $nombreArchivo = 'nomina_plantilla_' . date('Ymd_His');
        $archivoSalida = $carpeta . $nombreArchivo . '.xml';
        if (file_put_contents($archivoSalida, $xml) === false) {
            $archivoSalida = false;
        }
        $extension = 'xml';
    }

    if ($archivoSalida) {
        echo json_encode([
            'success' => true,
            'archivo' => basename($archivoSalida),
            'registros' => 0,
            'descarga' => 'exports/' . basename($archivoSalida)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'mensaje' => 'Error al generar la plantilla ' . strtoupper($extension)
        ]);
    }
    exit;
}

// ========================
// VERIFICAR CUADRE DE NÓMINAS (DESCUADRES)
// ========================
if (isset($_POST['accion']) && $_POST['accion'] === 'verificar_cuadre') {
    $periodoDesde = $_POST['periodo_desde'] ?? '';
    $periodoHasta = $_POST['periodo_hasta'] ?? '';
    $tipoNomina = $_POST['tipo_nomina'] ?? '';

    $cfg = [];
    try {
        $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('dias_mensuales','horas_jornada_diaria','recargo_nocturno')");
        while ($r = $stmt->fetch()) { $cfg[$r['parametro']] = $r['valor']; }
    } catch (Exception $e) {}
    $dias_mensuales = floatval($cfg['dias_mensuales'] ?? 0) ?: 24;
    $jornada = floatval($cfg['horas_jornada_diaria'] ?? 0) ?: 8;

    try { $factor = floatval($pdo->query("SELECT factor_calculo FROM configuracion_vacaciones WHERE activo = 1 LIMIT 1")->fetchColumn()) ?: 0.0909; } catch (Exception $e) { $factor = 0.0909; }
    try { $tasa = floatval($pdo->query("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1")->fetchColumn()) ?: 5; } catch (Exception $e) { $tasa = 5; }
    try { $rangos = $pdo->query("SELECT * FROM configuracion_rangos_impuesto ORDER BY desde")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $rangos = []; }

    function _rexcel($number, $precision = 2) { $m = pow(10, $precision); return floor($number * $m + 0.5) / $m; }
    function _cess($salario) { if ($salario <= 15000) return _rexcel($salario * 0.05, 2); return _rexcel(750 + (($salario - 15000) * 0.10), 2); }
    function _impuesto($salario, $rangos) {
        $base = max(0, $salario - 10000); $imp = 0;
        foreach ($rangos as $r) { $desde = floatval($r['desde']); $hasta = floatval($r['hasta'] ?? PHP_INT_MAX); $tasa = floatval($r['tasa']); $tramo = min($base, $hasta) - $desde; if ($tramo > 0) $imp += $tramo * ($tasa / 100); $base -= $tramo; if ($base <= 0) break; }
        return _rexcel($imp, 2);
    }

    $stmt = $pdo->prepare("SELECT n.*, t.nombre_completo, t.no_acumular_vacaciones, e.salario_hora_ordinaria, e.salario_mensual
                           FROM nominas n JOIN trabajadores t ON t.id = n.trabajador_id
                           LEFT JOIN escalas_salariales e ON e.id = t.escala_salarial_id
                           WHERE DATE(n.periodo_desde) = DATE(?) AND DATE(n.periodo_hasta) = DATE(?) AND n.tipo_nomina = ? AND n.estado = 'contabilizado'
                           ORDER BY n.id");
    $stmt->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $errores = 0;
    $ids_error = [];
    $numeros_nomina = [];
    foreach ($rows as $n) {
        $td = $n['tipo_descuento'] ?? 'solo_cess';
        $dev = floatval($n['total_salario_devengado']);
        $sm = floatval($n['salario_mensual'] ?? 0);
        $noac = intval($n['no_acumular_vacaciones'] ?? 0);
        $horas = floatval($n['horas_laboradas'] ?? 0);
        $descuentos = floatval($n['descuentos'] ?? 0);
        $od = floatval($n['otras_deducciones'] ?? 0);

        if ($tipoNomina == 'automatica') {
            $vaca = 0;
            if ($noac == 1) $vaca = _rexcel(_rexcel(($horas * $factor) / $jornada, 2) * ($sm / $dias_mensuales), 2);
            $dev_ok = _rexcel(floatval($n['importe_salario_laboral']) + floatval($n['importe_horas_nocturnas']) + floatval($n['importe_dias_feriados']) + floatval($n['otros_salarios']) + $vaca, 2);
        } elseif ($tipoNomina == 'extraordinaria') {
            $dev_ok = _rexcel(floatval($n['importe_salario_laboral']) + floatval($n['importe_horas_nocturnas']), 2);
        } elseif ($tipoNomina == 'vacaciones') {
            $dev_ok = floatval($n['importe_vacaciones']);
        } elseif ($tipoNomina == 'bono') {
            $dev_ok = floatval($n['pago_resultado']);
        } else {
            $dev_ok = _rexcel(floatval($n['pago_resultado']) + floatval($n['otros_salarios']), 2);
        }

        $contrib_calc = ($td == 'solo_cess') ? _cess($dev) : _rexcel($dev * ($tasa / 100), 2);
        $imp_calc = ($td == 'solo_cess') ? 0 : _impuesto($dev, $rangos);
        $neto_calc = _rexcel($dev - $contrib_calc - $imp_calc - $descuentos - $od, 2);

        if (abs(round($dev, 2) - round($dev_ok, 2)) > 0.02 ||
            abs(round(floatval($n['contribucion_especial']), 2) - round($contrib_calc, 2)) > 0.001 ||
            abs(round(floatval($n['importe_neto']), 2) - round($neto_calc, 2)) > 0.02) {
            $errores++;
            $ids_error[] = intval($n['trabajador_id']);
            if (!empty($n['numero_nomina']) && !in_array($n['numero_nomina'], $numeros_nomina)) {
                $numeros_nomina[] = $n['numero_nomina'];
            }
        }
    }

    // Verificación de cierres del período/tipo: si algún cierre descuadra,
    // la exportación se bloquea y se reportan los números de nómina afectados.
    $reporte_cierres = verificarCuadreCierres($pdo, [
        'periodo_desde' => $periodoDesde,
        'periodo_hasta' => $periodoHasta,
        'tipo' => $tipoNomina,
    ]);
    foreach ($reporte_cierres['errores_cierre'] as $ce) {
        if (!in_array($ce['numero'], $numeros_nomina)) {
            $numeros_nomina[] = $ce['numero'];
        }
    }
    $cierres_con_error = intval($reporte_cierres['cierres_con_error']);

    echo json_encode([
        'success' => true,
        'con_errores' => $errores > 0 || $cierres_con_error > 0,
        'filas_con_error' => $errores,
        'total_filas' => count($rows),
        'ids_error' => $ids_error,
        'numeros_nomina' => $numeros_nomina,
        'cierres_total' => intval($reporte_cierres['cierres']),
        'cierres_con_error' => $cierres_con_error,
        'errores_cierre_total' => intval($reporte_cierres['errores_total']),
    ]);
    exit;
}

// ========================
// 6. DETECTAR PARÁMETROS PARA EXPORTACIÓN
// ========================
$formato = $_POST['formato'] ?? $_GET['formato'] ?? '';
$periodoId = $_POST['periodo_id'] ?? $_GET['periodo_id'] ?? '';
$tipoNomina = $_POST['tipo_nomina'] ?? $_GET['tipo_nomina'] ?? '';

// ========================
// 7. VALIDAR FORMATO
// ========================
$formatosPermitidos = ['dbf', 'xlsx', 'xml'];
if (!in_array($formato, $formatosPermitidos)) {
    echo json_encode([
        'success' => false, 
        'mensaje' => 'Formato no soportado: ' . htmlspecialchars($formato)
    ]);
    exit;
}

// ========================
// 8. CARGAR COMPOSER SOLO SI ES XLSX
// ========================
if ($formato === 'xlsx') {
    if (file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';
    } else {
        echo json_encode(['success' => false, 'mensaje' => 'PhpSpreadsheet no instalado. Ejecuta: composer require phpoffice/phpspreadsheet']);
        exit;
    }
}

// ========================
// 9. OBTENER REGISTROS DEL PERÍODO Y TIPO SELECCIONADO
// ========================
$periodoDesde = $_POST['periodo_desde'] ?? $_GET['periodo_desde'] ?? '';
$periodoHasta = $_POST['periodo_hasta'] ?? $_GET['periodo_hasta'] ?? '';
$tipoNomina = $_POST['tipo_nomina'] ?? $_GET['tipo_nomina'] ?? '';
$idsExcluirRaw = $_POST['ids_excluir'] ?? $_GET['ids_excluir'] ?? '';
$idsExcluir = [];
if ($idsExcluirRaw !== '') {
    $idsExcluir = array_filter(array_map('intval', explode(',', $idsExcluirRaw)));
}

$sql = "SELECT 
            t.ci, 
            t.cuentabanc, 
            n.importe_neto AS importe
        FROM nominas n
        INNER JOIN trabajadores t ON n.trabajador_id = t.id
        WHERE n.periodo_desde = ?
        AND n.periodo_hasta = ?
        AND n.tipo_nomina = ?
        AND n.estado = 'contabilizado'
        AND t.cuentabanc IS NOT NULL AND t.cuentabanc != ''
        AND t.ci IS NOT NULL AND t.ci != ''";
$params = [$periodoDesde, $periodoHasta, $tipoNomina];

if (!empty($idsExcluir)) {
    $placeholders = implode(',', array_fill(0, count($idsExcluir), '?'));
    $sql .= " AND n.trabajador_id NOT IN ($placeholders)";
    $params = array_merge($params, $idsExcluir);
}

$sql .= " ORDER BY t.nombre_completo";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($registros)) {
    // Determinar si la nómina existe contabilizada pero sin trabajadores con tarjeta
    $sqlExiste = "SELECT COUNT(DISTINCT n.trabajador_id) as total
                  FROM nominas n
                  INNER JOIN trabajadores t ON n.trabajador_id = t.id
                  WHERE n.periodo_desde = ?
                    AND n.periodo_hasta = ?
                    AND n.tipo_nomina = ?
                    AND n.estado = 'contabilizado'";
    $stmtEx = $pdo->prepare($sqlExiste);
    $stmtEx->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $totalNomina = (int)$stmtEx->fetchColumn();

    if ($totalNomina > 0) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'La nómina de tipo "' . $tipoNomina . '" del período seleccionado tiene ' . $totalNomina . ' trabajador(es), pero NINGUNO tiene número de tarjeta (cuenta bancaria) y CI registrados. No hay importes que acreditar al banco. Actualice la cuenta bancaria de los trabajadores en su ficha para poder exportar.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'mensaje' => 'No se encontró ninguna nómina contabilizada de tipo "' . $tipoNomina . '" para el período seleccionado.'
        ]);
    }
    exit;
}

// ========================
// 10. CONFIGURACIÓN INICIAL
// ========================
$carpeta = __DIR__ . '/exports/';
if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

// Obtener información del período para el nombre del archivo
$stmtPeriodo = $pdo->prepare("SELECT DATE_FORMAT(periodo_desde, '%Y%m') as periodo FROM nominas WHERE id = ?");
$stmtPeriodo->execute([$periodoId]);
$periodoInfo = $stmtPeriodo->fetch();
$periodoStr = $periodoInfo ? $periodoInfo['periodo'] : date('Ym');

$nombreArchivo = 'nomina_' . $tipoNomina . '_' . $periodoStr . '_' . date('Ymd_His');
$archivoSalida = '';
$success = false;

function sanitizar($valor, $codificacion = 'Windows-1252') {
    if ($valor === null) return '';
    return mb_convert_encoding((string)$valor, $codificacion, 'UTF-8');
}

// ========================
// FUNCIÓN PARA GENERAR ARCHIVOS DBF
// ========================
function generarDbf($carpeta, $nombreArchivo, $fields, $registros) {
    $archivoSalida = $carpeta . $nombreArchivo . '.dbf';

    $recordLen = 1;
    foreach ($fields as $field) $recordLen += $field[2];

    $numRecords = count($registros);
    $headerLen = 32 + (count($fields) * 32) + 1;
    $version = 0x03;

    $header = '';
    $header .= pack('C', $version);
    $header .= pack('C', date('Y') - 1900);
    $header .= pack('C', date('m'));
    $header .= pack('C', date('d'));
    $header .= pack('V', $numRecords);
    $header .= pack('v', $headerLen);
    $header .= pack('v', $recordLen);
    $header .= str_repeat("\x00", 17) . "\x03" . str_repeat("\x00", 2);

    foreach ($fields as $field) {
        $header .= str_pad(substr($field[0], 0, 11), 11, "\x00");
        $header .= pack('C', ord($field[1]));
        $header .= pack('V', 0);
        $header .= pack('C', $field[2]);
        $header .= pack('C', $field[3]);
        $header .= str_repeat("\x00", 14);
    }
    $header .= pack('C', 0x0D);

    $fp = fopen($archivoSalida, 'wb');
    if (!$fp) return false;

    fwrite($fp, $header);
    foreach ($registros as $fila) {
        fwrite($fp, pack('C', 0x20));
        fwrite($fp, str_pad(substr(sanitizar($fila['ci']), 0, 11), 11, ' '));
        fwrite($fp, str_pad(substr(sanitizar($fila['cuentabanc']), 0, 16), 16, ' '));
        $importe = str_pad(number_format((float)($fila['importe'] ?? 0), 2, '.', ''), 18, ' ', STR_PAD_LEFT);
        fwrite($fp, sanitizar($importe));
    }
    fwrite($fp, pack('C', 0x1A));
    fclose($fp);
    return $archivoSalida;
}

// ========================
// 11. EXPORTAR A DBF
// ========================
if ($formato === 'dbf') {
    $fields = [
        ['NID',     'C', 11, 0],
        ['CUENTA',  'C', 16, 0],
        ['IMPORTE', 'N', 18, 2]
    ];

    $archivoSalida = generarDbf($carpeta, $nombreArchivo, $fields, $registros);
    if ($archivoSalida) {
        $success = true;
    }
}

// ========================
// 12. EXPORTAR A XLSX
// ========================
elseif ($formato === 'xlsx') {
    $archivoSalida = $carpeta . $nombreArchivo . '.xlsx';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setTitle('nomina');
    
    $sheet->setCellValue('A1', 'Nid');
    $sheet->setCellValue('B1', 'Cuenta');
    $sheet->setCellValue('C1', 'Importe');
    
    $fila = 2;
    foreach ($registros as $reg) {
        $sheet->setCellValue('A' . $fila, sanitizar($reg['ci'], 'UTF-8'));
        $sheet->setCellValue('B' . $fila, sanitizar($reg['cuentabanc'], 'UTF-8'));
        $sheet->setCellValue('C' . $fila, (float)($reg['importe'] ?? 0));
        $fila++;
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($archivoSalida);
    $success = true;
}

// ========================
// 13. EXPORTAR A XML
// ========================
elseif ($formato === 'xml') {
    $archivoSalida = $carpeta . $nombreArchivo . '.xml';
    
    $xml = '<acreditacion>' . "\n";
    
    foreach ($registros as $reg) {
        $nid = htmlspecialchars(sanitizar($reg['ci'], 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $cuenta = htmlspecialchars(sanitizar($reg['cuentabanc'], 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $importe = number_format((float)($reg['importe'] ?? 0), 2, '.', '');
        
        $xml .= '  <registro  Nid="' . $nid . '" Cuenta="' . $cuenta . '" Importe="' . $importe . '" />' . "\n";
    }
    
    $xml .= '</acreditacion>';
    
    if (file_put_contents($archivoSalida, $xml) !== false) {
        $success = true;
    }
}

// ========================
// 14. RESPUESTA FINAL
// ========================
if ($success) {
    echo json_encode([
        'success' => true,
        'archivo' => basename($archivoSalida),
        'registros' => count($registros),
        'descarga' => 'exports/' . basename($archivoSalida)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al generar el archivo en formato: ' . $formato
    ]);
}
?>