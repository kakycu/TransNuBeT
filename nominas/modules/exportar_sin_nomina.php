<?php
// modules/exportar_sin_nomina.php - Trabajadores activos SIN nómina en un período (PDF, Word, Excel, CSV, TXT)
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/funciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'Su sesión ha expirado o no está autenticado. Inicie sesión nuevamente para continuar.']);
    exit;
}

// Control de acceso por rol
if (!permiso_puede('nominas', 'exportar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No tiene permisos suficientes para exportar. Contacte al administrador del sistema.']);
    exit;
}

// ========================
// CONFIGURACIÓN EMPRESA / USUARIO / LOGO
// ========================
$config_empresa = ['nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director', 'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas', 'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'especialista_gestionRRHH')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestionRRHH') $config_empresa['especialista_gestionRRHH'] = $row['valor'];
    }
} catch (PDOException $e) {}

$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';

$ruta_logo = __DIR__ . '/../../images/logocorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo_logo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $logo_base64 = 'data:image/' . $tipo_logo . ';base64,' . base64_encode(file_get_contents($ruta_logo));
}

// ========================
// PARÁMETROS
// ========================
$formato  = $_POST['formato'] ?? $_GET['formato'] ?? '';
$accion   = $_POST['accion'] ?? $_GET['accion'] ?? '';
$periodo  = $_POST['periodo'] ?? $_GET['periodo'] ?? date('Y-m');

// Validar formato del período YYYY-MM
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
    echo json_encode(['success' => false, 'mensaje' => 'Período no válido. Use el formato AAAA-MM.']);
    exit;
}

$anio = (int)substr($periodo, 0, 4);
$mes  = (int)substr($periodo, 5, 2);
$periodo_desde = $periodo . '-01';
$periodo_hasta = date('Y-m-t', strtotime($periodo_desde));

// ========================
// TRABAJADORES ACTIVOS SIN NÓMINA EN EL PERÍODO
// Se considera fecha_alta (fecha_alta <= fin del período) y
// fecha_baja (fecha_baja >= inicio del período o NULL) para el período.
// Se agregan los totales devengado y neto de TODAS sus nóminas no borrador.
// ========================
function obtenerSinNomina($pdo, $periodo_desde, $periodo_hasta) {
    $ini = $periodo_desde;
    $fin = $periodo_hasta;
    $mes = substr($periodo_desde, 5, 2);
    $anio = substr($periodo_desde, 0, 4);
    $stmt = $pdo->prepare("
        SELECT t.id, t.codigo, t.ci, t.nombre_completo,
               COALESCE(a.nombre_area, '') AS area,
               COALESCE(cc.nombre, '') AS centro_costo,
               COALESCE(t.fecha_alta, '') AS fecha_alta,
               COALESCE(t.fecha_baja, '') AS fecha_baja,
               COALESCE(MAX(CASE WHEN n2.estado != 'borrador' THEN n2.periodo_desde END), '') AS ultima_nomina,
               COUNT(CASE WHEN n2.estado != 'borrador' THEN 1 END) AS total_nominas,
               COALESCE(SUM(CASE WHEN n2.estado != 'borrador' THEN n2.total_salario_devengado ELSE 0 END), 0) AS total_devengado,
               COALESCE(SUM(CASE WHEN n2.estado != 'borrador' THEN n2.importe_neto ELSE 0 END), 0) AS total_neto
        FROM trabajadores t
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
        LEFT JOIN nominas n2 ON n2.trabajador_id = t.id
        WHERE t.activo = 1
          AND (t.fecha_alta IS NULL OR t.fecha_alta <= :fin)
          AND (t.fecha_baja IS NULL OR t.fecha_baja >= :ini)
          AND t.id NOT IN (
              SELECT DISTINCT n.trabajador_id
              FROM nominas n
              WHERE n.estado != 'borrador'
                AND MONTH(n.periodo_desde) = :mes
                AND YEAR(n.periodo_desde) = :anio
          )
        GROUP BY t.id, t.codigo, t.ci, t.nombre_completo, a.nombre_area, cc.nombre, t.fecha_alta, t.fecha_baja
        ORDER BY t.nombre_completo ASC
    ");
    $stmt->execute([':fin' => $fin, ':ini' => $ini, ':mes' => $mes, ':anio' => $anio]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];
    foreach ($filas as $f) {
        $resultado[] = [
            'id'             => (int)$f['id'],
            'codigo'         => $f['codigo'],
            'ci'             => $f['ci'],
            'nombre'         => $f['nombre_completo'],
            'area'           => $f['area'],
            'centro_costo'   => $f['centro_costo'],
            'fecha_alta'     => ($f['fecha_alta'] !== '') ? date('d/m/Y', strtotime($f['fecha_alta'])) : '—',
            'fecha_baja'     => ($f['fecha_baja'] !== '') ? date('d/m/Y', strtotime($f['fecha_baja'])) : '—',
            'ultima_nomina'  => ($f['ultima_nomina'] !== '') ? date('d/m/Y', strtotime($f['ultima_nomina'])) : 'Nunca',
            'total_nominas'  => (int)$f['total_nominas'],
            'total_devengado'=> (float)round($f['total_devengado'], 2),
            'total_neto'     => (float)round($f['total_neto'], 2)
        ];
    }
    return $resultado;
}

$trabajadores = obtenerSinNomina($pdo, $periodo_desde, $periodo_hasta);

// ========================
// MODO LISTA (para el modal)
// ========================
if ($accion === 'lista') {
    echo json_encode([
        'success' => true,
        'periodo' => $periodo,
        'periodo_label' => nombreMesEspanol($mes) . ' ' . $anio,
        'registros' => count($trabajadores),
        'trabajadores' => $trabajadores
    ]);
    exit;
}

// ========================
// FORMATOS DE EXPORTACIÓN
// ========================
$formatosPermitidos = ['pdf', 'word', 'excel', 'csv', 'txt'];
if (!in_array($formato, $formatosPermitidos)) {
    echo json_encode(['success' => false, 'mensaje' => 'Formato no soportado: ' . htmlspecialchars($formato)]);
    exit;
}

if (empty($trabajadores)) {
    echo json_encode(['success' => false, 'mensaje' => 'No hay trabajadores activos sin nómina en el período seleccionado.']);
    exit;
}

// ========================
// CONFIGURACIÓN INICIAL
// ========================
$carpeta = __DIR__ . '/exports/';
if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

$nombreEmpresa = $config_empresa['nombre_empresa'];
$titulo = 'TRABAJADORES SIN NÓMINA';
$subtitulo = $nombreEmpresa . ' - Período: ' . nombreMesEspanol($mes) . ' ' . $anio;
$meta1 = 'Generado por: ' . $user_nombre_completo . '  |  Emisión: ' . date('d/m/Y H:i');
$meta2 = 'Total de trabajadores: ' . count($trabajadores);

$nombreBase = 'trabajadores_sin_nomina_' . $periodo . '_' . date('Ymd_His');
$archivoSalida = '';
$success = false;

function sanitizar($valor, $codificacion = 'Windows-1252') {
    if ($valor === null) return '';
    return mb_convert_encoding((string)$valor, $codificacion, 'UTF-8');
}

function pdfEscape($texto) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$texto);
}

function pdfTruncar($texto, $maxChars) {
    $texto = (string)$texto;
    if (mb_strlen($texto, 'UTF-8') <= $maxChars) return $texto;
    return mb_substr($texto, 0, $maxChars - 1, 'UTF-8') . '…';
}

function formatoMoney($valor) {
    return number_format((float)$valor, 2);
}

function txtPad($texto, $len, $pad = ' ') {
    $texto = (string)$texto;
    $current = mb_strlen($texto, 'UTF-8');
    if ($current >= $len) return mb_substr($texto, 0, $len, 'UTF-8');
    return $texto . str_repeat($pad, $len - $current);
}

$firmasData = [
    ['Elaborado por:', ($config_empresa['especialista_gestionRRHH'] ?? '') !== '' ? $config_empresa['especialista_gestionRRHH'] : $user_nombre_completo, 'Especialista de Recursos Humanos'],
    ['Revisado por:', $config_empresa['especialista_gestion'], 'Especialista en Gestión Económica'],
    ['Aprobado por:', $config_empresa['jefe_proyecto'], 'Director de Proyecto']
];

// ========================
// GENERAR EL ARCHIVO SEGÚN FORMATO
// ========================
if ($formato === 'excel') {
    if (!file_exists('../vendor/autoload.php')) {
        echo json_encode(['success' => false, 'mensaje' => 'PhpSpreadsheet no instalado.']);
        exit;
    }
    require_once '../vendor/autoload.php';

    $archivoSalida = $carpeta . $nombreBase . '.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sin Nómina');

    $headers = ['No.', 'Expediente', 'CI', 'Nombre Completo', 'Área', 'Centro de Costo', 'Fecha Alta', 'Fecha Baja', 'Última Nómina', 'Total Nóminas', 'Total Devengado', 'Total Neto'];
    $anchors = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];

    // ---- Encabezado con logo ----
    $sheet->getRowDimension(1)->setRowHeight(62);
    if (!empty($logo_base64) && file_exists($ruta_logo)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo empresa');
        $drawing->setPath($ruta_logo);
        $drawing->setHeight(56);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(3);
        $drawing->getShadow()->setVisible(false);
        $drawing->setWorksheet($sheet);
    }
    $sheet->mergeCells('B1:L1');
    $sheet->setCellValue('B1', $nombreEmpresa);
    $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('1F2937');
    $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getRowDimension(2)->setRowHeight(18);
    $sheet->mergeCells('B2:L2');
    $sheet->setCellValue('B2', $titulo);
    $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('C0392B');

    $sheet->getRowDimension(3)->setRowHeight(16);
    $sheet->mergeCells('B3:L3');
    $sheet->setCellValue('B3', $subtitulo . '  |  ' . $meta1 . '  |  ' . $meta2);
    $sheet->getStyle('B3')->getFont()->setSize(9)->getColor()->setRGB('4B5563');

    $filaHeader = 5;
    foreach ($anchors as $i => $col) {
        $sheet->setCellValue($col . $filaHeader, $headers[$i]);
    }
    $sheet->getStyle('A' . $filaHeader . ':L' . $filaHeader)->getFont()->setBold(true);
    $sheet->getStyle('A' . $filaHeader . ':L' . $filaHeader)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('C0392B');
    $sheet->getStyle('A' . $filaHeader . ':L' . $filaHeader)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A' . $filaHeader . ':L' . $filaHeader)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($filaHeader)->setRowHeight(20);

    $fila = $filaHeader + 1;
    foreach ($trabajadores as $i => $t) {
        $sheet->setCellValue('A' . $fila, $i + 1);
        $sheet->setCellValue('B' . $fila, $t['codigo']);
        $sheet->setCellValue('C' . $fila, $t['ci']);
        $sheet->setCellValue('D' . $fila, $t['nombre']);
        $sheet->setCellValue('E' . $fila, $t['area']);
        $sheet->setCellValue('F' . $fila, $t['centro_costo']);
        $sheet->setCellValue('G' . $fila, $t['fecha_alta']);
        $sheet->setCellValue('H' . $fila, $t['fecha_baja']);
        $sheet->setCellValue('I' . $fila, $t['ultima_nomina']);
        $sheet->setCellValue('J' . $fila, $t['total_nominas']);
        $sheet->setCellValue('K' . $fila, formatoMoney($t['total_devengado']));
        $sheet->setCellValue('L' . $fila, formatoMoney($t['total_neto']));
        $sheet->getStyle('A' . $fila . ':L' . $fila)->getFont()->setSize(9);
        $sheet->getStyle('K' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('L' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        if ($i % 2 === 1) {
            $sheet->getStyle('A' . $fila . ':L' . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F3F4F6');
        }
        $fila++;
    }
    $ultimaFila = $fila - 1;

    // ---- Firmas ----
    $fila += 2;
    $bloques = [
        ['C', 'E', $firmasData[0]],
        ['F', 'H', $firmasData[1]],
        ['I', 'K', $firmasData[2]]
    ];
    foreach ($bloques as $bloque) {
        list($colIni, $colFin, $firma) = $bloque;
        $rango = $colIni . $fila . ':' . $colFin . $fila;
        $sheet->mergeCells($rango);
        $sheet->setCellValue($colIni . $fila, $firma[0]);
        $sheet->getStyle($colIni . $fila)->getFont()->setBold(true)->setSize(10);
        $sheet->mergeCells($colIni . ($fila + 1) . ':' . $colFin . ($fila + 1));
        $sheet->setCellValue($colIni . ($fila + 1), '');
        $sheet->getStyle($colIni . ($fila + 1) . ':' . $colFin . ($fila + 1))->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->mergeCells($colIni . ($fila + 2) . ':' . $colFin . ($fila + 2));
        $sheet->setCellValue($colIni . ($fila + 2), $firma[1]);
        $sheet->getStyle($colIni . ($fila + 2))->getFont()->setBold(true)->setSize(10);
        $sheet->mergeCells($colIni . ($fila + 3) . ':' . $colFin . ($fila + 3));
        $sheet->setCellValue($colIni . ($fila + 3), $firma[2]);
        $sheet->getStyle($colIni . ($fila + 3))->getFont()->setSize(8)->getColor()->setRGB('4B5563');
    }
    $fila += 5;
    $sheet->mergeCells('A' . $fila . ':L' . $fila);
    $sheet->setCellValue('A' . $fila, 'FIN DEL REPORTE - ' . $nombreEmpresa);
    $sheet->getStyle('A' . $fila)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(10);

    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(14);
    $sheet->getColumnDimension('D')->setWidth(42);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(24);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(12);
    $sheet->getColumnDimension('I')->setWidth(13);
    $sheet->getColumnDimension('J')->setWidth(10);
    $sheet->getColumnDimension('K')->setWidth(14);
    $sheet->getColumnDimension('L')->setWidth(14);
    $sheet->getStyle('A1:L' . $ultimaFila)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    try {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($archivoSalida);
        $success = true;
    } catch (\Exception $e) {
        $success = false;
    }

} elseif ($formato === 'csv') {
    $archivoSalida = $carpeta . $nombreBase . '.csv';
    $fp = fopen($archivoSalida, 'w');
    if ($fp) {
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, ['EMPRESA: ' . $nombreEmpresa]);
        fputcsv($fp, [$titulo . ' - Período: ' . nombreMesEspanol($mes) . ' ' . $anio]);
        fputcsv($fp, [$meta1 . '  |  ' . $meta2]);
        fputcsv($fp, []);
        fputcsv($fp, ['No.', 'Expediente', 'CI', 'Nombre Completo', 'Área', 'Centro de Costo', 'Fecha Alta', 'Fecha Baja', 'Última Nómina', 'Total Nóminas', 'Total Devengado', 'Total Neto']);
        foreach ($trabajadores as $i => $t) {
            fputcsv($fp, [$i + 1, $t['codigo'], $t['ci'], $t['nombre'], $t['area'], $t['centro_costo'], $t['fecha_alta'], $t['fecha_baja'], $t['ultima_nomina'], $t['total_nominas'], formatoMoney($t['total_devengado']), formatoMoney($t['total_neto'])]);
        }
        fputcsv($fp, []);
        foreach ($firmasData as $firma) {
            fputcsv($fp, [$firma[0] . ' ' . $firma[1] . ' (' . $firma[2] . ')']);
        }
        fputcsv($fp, ['FIN DEL REPORTE - ' . $nombreEmpresa]);
        fclose($fp);
        $success = true;
    }

} elseif ($formato === 'txt') {
    $archivoSalida = $carpeta . $nombreBase . '.txt';
    $lineas = [];
    $lineas[] = str_repeat('=', 163);
    $lineas[] = $titulo;
    $lineas[] = $subtitulo;
    $lineas[] = $meta1 . '  |  ' . $meta2;
    $lineas[] = str_repeat('=', 163);
    $lineas[] = '';
    $lineas[] = txtPad('No.', 4) . txtPad('Expediente', 10) . txtPad('CI', 12) . txtPad('Nombre Completo', 40) . txtPad('Área', 20) . txtPad('Centro de Costo', 26) . txtPad('F.Alta', 11) . txtPad('F.Baja', 11) . txtPad('Última Nómina', 14) . txtPad('Total', 6) . txtPad('Devengado', 13) . txtPad('Neto', 10);
    $lineas[] = str_repeat('-', 163);
    foreach ($trabajadores as $i => $t) {
        $lineas[] = txtPad($i + 1, 4)
            . txtPad($t['codigo'], 10)
            . txtPad($t['ci'], 12)
            . txtPad($t['nombre'], 40)
            . txtPad($t['area'], 20)
            . txtPad($t['centro_costo'], 26)
            . txtPad($t['fecha_alta'], 11)
            . txtPad($t['fecha_baja'], 11)
            . txtPad($t['ultima_nomina'], 14)
            . txtPad($t['total_nominas'], 6)
            . txtPad(formatoMoney($t['total_devengado']), 13)
            . txtPad(formatoMoney($t['total_neto']), 10);
    }
    $lineas[] = str_repeat('-', 163);
    $lineas[] = 'Total de trabajadores: ' . count($trabajadores);
    $lineas[] = '';
    foreach ($firmasData as $firma) {
        $lineas[] = $firma[0] . ' ' . $firma[1] . ' (' . $firma[2] . ')';
    }
    $lineas[] = '';
    $lineas[] = 'FIN DEL REPORTE - ' . $nombreEmpresa;
    if (file_put_contents($archivoSalida, mb_convert_encoding(implode("\r\n", $lineas), 'Windows-1252', 'UTF-8')) !== false) {
        $success = true;
    }

} elseif ($formato === 'word') {
    $archivoSalida = $carpeta . $nombreBase . '.doc';
    $filasHtml = '';
    foreach ($trabajadores as $i => $t) {
        $filasHtml .= '<tr>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:center;">' . ($i + 1) . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem;">' . htmlspecialchars($t['codigo'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem;">' . htmlspecialchars($t['ci'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem;">' . htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem;">' . htmlspecialchars($t['area'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem;">' . htmlspecialchars($t['centro_costo'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:center;">' . htmlspecialchars($t['fecha_alta'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:center;">' . htmlspecialchars($t['fecha_baja'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:center;">' . htmlspecialchars($t['ultima_nomina'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:center;">' . $t['total_nominas'] . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:right;">' . formatoMoney($t['total_devengado']) . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.1875rem; text-align:right;">' . formatoMoney($t['total_neto']) . '</td>'
            . '</tr>';
    }
    $firmasHtml = '<table style="width:100%; border-collapse:collapse; margin-top:1.25rem;">';
    $firmasHtml .= '<tr>';
    foreach ($firmasData as $firma) {
        $firmasHtml .= '<td style="padding:0.3125rem; text-align:center;">'
            . '<p style="margin:0; font-size:0.625rem;"><strong>' . htmlspecialchars($firma[0], ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p style="margin:0 0 0.125rem; border-bottom:0.0625rem solid #000; height:1.125rem;"></p>'
            . '<p style="margin:0; font-size:0.6875rem; font-weight:bold;">' . htmlspecialchars($firma[1], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0; font-size:0.5rem; color:#444;">' . htmlspecialchars($firma[2], ENT_QUOTES, 'UTF-8') . '</p>'
            . '</td>';
    }
    $firmasHtml .= '</tr></table>';

    $logoWord = (!empty($logo_base64)) ? '<img src="' . $logo_base64 . '" alt="Logo" style="width:3.75rem; height:3.5625rem; vertical-align:middle;">' : '';
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
        . '<head><meta charset="utf-8"><title>' . htmlspecialchars($titulo) . '</title>'
        . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>90</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->'
        . '<style>@page WordSection1 { size: 792pt 612pt; margin:0.5in; } div.WordSection1 { page: WordSection1; } body { font-family: Calibri, Arial, sans-serif; } table { border-collapse: collapse; width:100%; } th { border:0.0625rem solid #000; padding:0.25rem; background:#C0392B; color:#fff; font-size:0.5625rem; } </style>'
        . '</head><body><div class="WordSection1">'
        . '<table style="width:100%; border:none;"><tr>'
        . '<td style="width:4.375rem; border:none; vertical-align:middle;">' . $logoWord . '</td>'
        . '<td style="border:none; text-align:center;"><h2 style="margin:0; font-size:1.125rem;">' . htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<h3 style="margin:0.125rem 0 0; font-size:0.875rem; color:#C0392B;">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<p style="margin:0; font-size:0.625rem; color:#444;">' . htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8') . '</p></td>'
        . '<td style="width:10.625rem; border:none; vertical-align:middle; font-size:0.5625rem; text-align:right;">' . htmlspecialchars($meta1, ENT_QUOTES, 'UTF-8') . '<br>' . htmlspecialchars($meta2, ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr></table>'
        . '<table><tr>'
        . '<th>No.</th><th>Expediente</th><th>CI</th><th>Nombre Completo</th><th>Área</th><th>Centro de Costo</th><th>F. Alta</th><th>F. Baja</th><th>Últ. Nómina</th><th>Total</th><th>Devengado</th><th>Neto</th>'
        . '</tr>' . $filasHtml . '</table>'
        . $firmasHtml
        . '<p style="text-align:center; margin-top:0.9375rem; font-weight:bold;">FIN DEL REPORTE - ' . htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div></body></html>';
    if (file_put_contents($archivoSalida, "\xEF\xBB\xBF" . $html) !== false) {
        $success = true;
    }

} elseif ($formato === 'pdf') {
    $archivoSalida = $carpeta . $nombreBase . '.pdf';

    // Convertir logo PNG (con transparencia) a JPEG con fondo blanco para incrustar en el PDF
    $logo = null;
    if (function_exists('imagecreatefrompng') && file_exists($ruta_logo)) {
        $gd = @imagecreatefrompng($ruta_logo);
        if ($gd) {
            $w = imagesx($gd); $h = imagesy($gd);
            $sc = 76 / $h;
            $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
            $dst = imagecreatetruecolor($nw, $nh);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $gd, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 80);
            $jpegData = ob_get_clean();
            imagedestroy($gd); imagedestroy($dst);
            if ($jpegData !== '') {
                $logo = ['data' => $jpegData, 'w' => $nw, 'h' => $nh];
            }
        }
    }

    $w = 792; $h = 612;
    $mIzq = 36; $mSup = 55; $mInf = 42;
    $anchos = [20, 45, 50, 115, 65, 80, 55, 55, 55, 32, 55, 55];
    $totalAncho = array_sum($anchos);
    $x0 = ($w - $totalAncho) / 2;
    $altoCab = 18; $altoFila = 15;

    $headers = ['No.', 'Expediente', 'CI', 'Nombre Completo', 'Área', 'Centro Costo', 'F. Alta', 'F. Baja', 'Últ. Nómina', 'Total', 'Devengado', 'Neto'];

    $paginas = [];
    $contenido = '';
    $y = $h - $mSup;
    $numPag = 0;

    $tituloX = $mIzq;
    $logoW = 0;
    if ($logo) {
        $logoW = $logo['w'];
        $tituloX = $mIzq + $logoW + 14;
    }

    $iniciarPagina = function () use (&$paginas, &$contenido, &$y, &$numPag, $h, $mIzq, $mSup, $mInf, $x0, $totalAncho, $anchos, $headers, $altoCab, $logo, $tituloX, $titulo, $subtitulo, $meta1, $meta2) {
        $numPag++;
        $contenido = '';
        $y = $h - $mSup;

        if ($logo) {
            $contenido .= "q " . $logo['w'] . " 0 0 " . $logo['h'] . " $mIzq 496 cm /Im1 Do Q\n";
        }
        $contenido .= "BT /F2 13 Tf 0 0 0 rg 1 0 0 1 $tituloX 568 Tm (" . pdfEscape(sanitizar($titulo, 'Windows-1252')) . ") Tj ET\n";
        $contenido .= "BT /F1 8.5 Tf 0.35 0.35 0.35 rg 1 0 0 1 $tituloX 553 Tm (" . pdfEscape(sanitizar($subtitulo, 'Windows-1252')) . ") Tj ET\n";
        $contenido .= "BT /F1 8 Tf 0.4 0.4 0.4 rg 1 0 0 1 $tituloX 538 Tm (" . pdfEscape(sanitizar($meta1, 'Windows-1252')) . ") Tj ET\n";
        $contenido .= "BT /F1 8 Tf 0.4 0.4 0.4 rg 1 0 0 1 $tituloX 525 Tm (" . pdfEscape(sanitizar($meta2, 'Windows-1252')) . ") Tj ET\n";
        $y = 472;

        $cx = $x0;
        foreach ($headers as $i => $hd) {
            $an = $anchos[$i];
            $contenido .= sprintf("0.75 0.22 0.17 rg %.2f %.2f %.2f %.2f re f\n", $cx, $y - $altoCab, $an, $altoCab);
            $contenido .= "BT /F2 8 Tf 1 1 1 rg 1 0 0 1 " . ($cx + 3) . " " . ($y - 12) . " Tm (" . pdfEscape(sanitizar($hd, 'Windows-1252')) . ") Tj ET\n";
            $cx += $an;
        }
        $contenido .= sprintf("0 0 0 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $x0, $y - $altoCab, $x0 + $totalAncho, $y - $altoCab);
        $y -= $altoCab;
    };

    $iniciarPagina();

    $ind = 0;
    foreach ($trabajadores as $t) {
        if ($y - $altoFila < $mInf) {
            $paginas[] = $contenido;
            $iniciarPagina();
        }
        if ($ind % 2 === 1) {
            $contenido .= sprintf("0.93 0.94 0.97 rg %.2f %.2f %.2f %.2f re f\n", $x0, $y - $altoFila, $totalAncho, $altoFila);
        }

        $cx = $x0;
        $celdas = [($ind + 1), $t['codigo'], $t['ci'], $t['nombre'], $t['area'], $t['centro_costo'], $t['fecha_alta'], $t['fecha_baja'], $t['ultima_nomina'], $t['total_nominas'], formatoMoney($t['total_devengado']), formatoMoney($t['total_neto'])];
        $centro = [0, 2, 6, 7, 8, 9, 10, 11];
        foreach ($anchos as $i => $an) {
            $texto = (string)$celdas[$i];
            if (in_array($i, $centro)) {
                $texto = pdfTruncar($texto, (int)floor($an / 4.5));
            } else {
                $texto = pdfTruncar($texto, (int)floor(($an - 6) / 4.5));
            }
            $tx = $cx + 2;
            $contenido .= "BT /F1 8 Tf 0 0 0 rg 1 0 0 1 " . $tx . " " . ($y - 10.5) . " Tm (" . pdfEscape(sanitizar($texto, 'Windows-1252')) . ") Tj ET\n";
            $cx += $an;
        }
        $y -= $altoFila;
        $ind++;
    }

    // Cierre de tabla
    $contenido .= sprintf("0 0 0 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $x0, $y, $x0 + $totalAncho, $y);

    // Firmas
    if ($y - 100 < $mInf) {
        $paginas[] = $contenido;
        $iniciarPagina();
        $contenido .= sprintf("0 0 0 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $x0, $y, $x0 + $totalAncho, $y);
    }
    $fw = ($totalAncho - 60) / 3;
    foreach ($firmasData as $fi => $firma) {
        $bx = $x0 + $fi * ($fw + 30);
        $contenido .= "BT /F2 8.5 Tf 0 0 0 rg 1 0 0 1 $bx " . ($y - 10) . " Tm (" . pdfEscape(sanitizar($firma[0], 'Windows-1252')) . ") Tj ET\n";
        $contenido .= sprintf("0 0 0 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $bx, $y - 22, $bx + $fw, $y - 22);
        $contenido .= "BT /F2 9 Tf 0 0 0 rg 1 0 0 1 $bx " . ($y - 34) . " Tm (" . pdfEscape(sanitizar(pdfTruncar($firma[1], (int)floor(($fw - 4) / 4.5)), 'Windows-1252')) . ") Tj ET\n";
        $contenido .= "BT /F1 7.5 Tf 0.35 0.35 0.35 rg 1 0 0 1 $bx " . ($y - 46) . " Tm (" . pdfEscape(sanitizar($firma[2], 'Windows-1252')) . ") Tj ET\n";
    }
    $y -= 60;

    $contenido .= "BT /F1 8 Tf 0.3 0.3 0.3 rg 1 0 0 1 $x0 30 Tm (Página " . $numPag . ") Tj ET\n";
    $paginas[] = $contenido;

    $nPaginas = count($paginas);

    $objs = [];
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $pageObjs = [];
    for ($i = 0; $i < $nPaginas; $i++) $pageObjs[] = (3 + $i);
    $objs[2] = "<< /Type /Pages /Kids [" . implode(' 0 R ', $pageObjs) . " 0 R] /Count $nPaginas >>";
    $contentStart = 3 + $nPaginas;
    $imgObj = -1;
    if ($logo) {
        $imgObj = $contentStart + $nPaginas;
    }
    for ($i = 0; $i < $nPaginas; $i++) {
        $contentObj = $contentStart + $i;
        $resources = "<< /Font << /F1 " . ($contentStart + $nPaginas + ($logo ? 2 : 0)) . " 0 R /F2 " . ($contentStart + $nPaginas + ($logo ? 3 : 1)) . " 0 R >> >>";
        if ($logo) {
            $resources = "<< /Font << /F1 " . ($contentStart + $nPaginas + 2) . " 0 R /F2 " . ($contentStart + $nPaginas + 3) . " 0 R >> /XObject << /Im1 $imgObj 0 R >> >>";
        }
        $objs[3 + $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Resources $resources /Contents $contentObj 0 R >>";
    }
    for ($i = 0; $i < $nPaginas; $i++) {
        $objs[$contentStart + $i] = "<< /Length " . strlen($paginas[$i]) . " >>\nstream\n" . $paginas[$i] . "\nendstream";
    }
    $idx = $contentStart + $nPaginas;
    if ($logo) {
        $objs[$idx] = "<< /Type /XObject /Subtype /Image /Width " . $logo['w'] . " /Height " . $logo['h'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logo['data']) . " >>\nstream\n" . $logo['data'] . "\nendstream";
        $idx++;
    }
    $font1 = $idx;
    $font2 = $idx + 1;
    $objs[$font1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objs[$font2] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $maxObj = $font2;
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    for ($i = 1; $i <= $maxObj; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objs[$i] . "\nendobj\n";
    }
    $xrefStart = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObj; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xrefStart\n%%EOF\n";

    if (file_put_contents($archivoSalida, $pdf) !== false) {
        $success = true;
    }
}

// ========================
// RESPUESTA FINAL
// ========================
if ($success) {
    echo json_encode([
        'success' => true,
        'archivo' => pathinfo(basename($archivoSalida), PATHINFO_FILENAME),
        'registros' => count($trabajadores),
        'descarga' => 'exports/' . basename($archivoSalida)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al generar el archivo en formato: ' . $formato
    ]);
}