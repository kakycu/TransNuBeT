<?php
//exportar_bandec.php - EXPORTAR DATOS ACREDITACION
header('Content-Type: application/json');

// Incluir configuración de base de datos
require_once '../config/database.php';

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
                n.periodo_hasta
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
    
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    $periodosFormateados = [];
    
    foreach ($periodos as $p) {
        $desde = new DateTime($p['periodo_desde']);
        $hasta = new DateTime($p['periodo_hasta']);
        
        $mesNumDesde = (int)$desde->format('m');
        $mesNumHasta = (int)$hasta->format('m');
        $anoDesde = $desde->format('Y');
        $anoHasta = $hasta->format('Y');
        
        $label = "Desde " . $desde->format('d') . " de " . $meses[$mesNumDesde - 1] . " de " . $anoDesde . 
                 " hasta " . $hasta->format('d') . " de " . $meses[$mesNumHasta - 1] . " de " . $anoHasta;
        
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
                 AND t.ci IS NOT NULL AND t.ci != ''
                 AND t.activo = 1";
    
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => ($total > 0),
        'total' => $total,
        'mensaje' => $total > 0 ? '' : 'No hay registros válidos para exportar'
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
    
    // Registros CON TARJETA
    $sqlCon = "SELECT t.ci, t.cuentabanc, n.importe_neto as importe, t.nombre_completo
               FROM nominas n
               INNER JOIN trabajadores t ON n.trabajador_id = t.id
               WHERE DATE(n.periodo_desde) = DATE(?)
                 AND DATE(n.periodo_hasta) = DATE(?)
                 AND n.tipo_nomina = ?
                 AND n.estado = 'contabilizado'
                 AND t.cuentabanc IS NOT NULL AND t.cuentabanc != ''
               ORDER BY t.nombre_completo";
               
    $stmtCon = $pdo->prepare($sqlCon);
    $stmtCon->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
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
               ORDER BY t.nombre_completo";
               
    $stmtSin = $pdo->prepare($sqlSin);
    $stmtSin->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
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
        AND t.ci IS NOT NULL AND t.ci != ''
        AND t.activo = 1
        ORDER BY t.nombre_completo";

$stmt = $pdo->prepare($sql);
$stmt->execute([$periodoDesde, $periodoHasta, $tipoNomina]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($registros)) {
    echo json_encode(['success' => false, 'mensaje' => 'No hay registros válidos para exportar en este período']);
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
// 11. EXPORTAR A DBF
// ========================
if ($formato === 'dbf') {
    $archivoSalida = $carpeta . $nombreArchivo . '.dbf';
    
    $fields = [
        ['NID',     'C', 11, 0],
        ['CUENTA',  'C', 16, 0],
        ['IMPORTE', 'N', 12, 2]
    ];
    
    $recordLen = 1;
    foreach ($fields as $field) $recordLen += $field[2];
    
    $numRecords = count($registros);
    $headerLen = 32 + (count($fields) * 32) + 1;
    $version = 0x03;
    
    $header = '';
    $header .= pack('C', $version);
    $header .= pack('C', date('y'));
    $header .= pack('C', date('m'));
    $header .= pack('C', date('d'));
    $header .= pack('V', $numRecords);
    $header .= pack('v', $headerLen);
    $header .= pack('v', $recordLen);
    $header .= str_repeat("\x00", 20);
    
    $offset = 1;
    foreach ($fields as $field) {
        $header .= str_pad(substr($field[0], 0, 11), 11, "\x00");
        $header .= pack('C', ord($field[1]));
        $header .= pack('V', $offset);
        $header .= pack('C', $field[2]);
        $header .= pack('C', $field[3]);
        $header .= str_repeat("\x00", 14);
        $offset += $field[2];
    }
    $header .= pack('C', 0x0D);
    
    $fp = fopen($archivoSalida, 'wb');
    if ($fp) {
        fwrite($fp, $header);
        foreach ($registros as $fila) {
            fwrite($fp, pack('C', 0x20));
            fwrite($fp, str_pad(substr(sanitizar($fila['ci']), 0, 11), 11, ' '));
            fwrite($fp, str_pad(substr(sanitizar($fila['cuentabanc']), 0, 16), 16, ' '));
            $importe = str_pad(number_format((float)($fila['importe'] ?? 0), 2, '.', ''), 12, ' ', STR_PAD_LEFT);
            fwrite($fp, sanitizar($importe));
        }
        fwrite($fp, pack('C', 0x1A));
        fclose($fp);
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