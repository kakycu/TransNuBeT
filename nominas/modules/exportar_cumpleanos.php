<?php
// modules/exportar_cumpleanos.php - Exportar Listado de Cumpleaños (PDF, Word, Excel, CSV, TXT, DBF)
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
if (!permiso_puede('empleados', 'exportar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No tiene permisos suficientes para exportar. Contacte al administrador del sistema.']);
    exit;
}

// ========================
// PARÁMETROS
// ========================
$formato = $_POST['formato'] ?? $_GET['formato'] ?? '';
$tipo    = $_POST['tipo'] ?? $_GET['tipo'] ?? 'completo'; // 'completo' | '30'

$formatosPermitidos = ['pdf', 'word', 'excel', 'csv', 'txt', 'dbf'];
if (!in_array($formato, $formatosPermitidos)) {
    echo json_encode(['success' => false, 'mensaje' => 'Formato no soportado: ' . htmlspecialchars($formato)]);
    exit;
}

// ========================
// OBTENER CUMPLEAÑOS (misma lógica que empleados.php, desde el CI)
// ========================
function obtenerCumpleaneros($pdo, $tipo) {
    $stmt = $pdo->query("
        SELECT id, codigo, ci, nombre_completo, foto_ruta
        FROM trabajadores
        WHERE ci IS NOT NULL
          AND ci != ''
          AND ci != '00000000000'
          AND LENGTH(ci) >= 6
          AND (fecha_baja IS NULL OR fecha_baja = '')
    ");
    $trabajadores = $stmt->fetchAll();

    $hoy = new DateTime();
    $cumpleaneros = [];

    foreach ($trabajadores as $t) {
        $ci_limpio = preg_replace('/\D/', '', $t['ci']);
        if (strlen($ci_limpio) < 6) continue;

        $anio = substr($ci_limpio, 0, 2);
        $mes  = substr($ci_limpio, 2, 2);
        $dia  = substr($ci_limpio, 4, 2);

        if (!checkdate((int)$mes, (int)$dia, 2000)) continue;

        $anio_completo = ((int)$anio < 24) ? (2000 + (int)$anio) : (1900 + (int)$anio);
        if ($anio_completo > (int)$hoy->format('Y')) {
            $anio_completo -= 100;
        }

        $fecha_nacimiento = DateTime::createFromFormat('Y-m-d', $anio_completo . '-' . $mes . '-' . $dia);
        if (!$fecha_nacimiento) continue;

        $proximo = DateTime::createFromFormat('Y-m-d', $hoy->format('Y') . '-' . $fecha_nacimiento->format('m-d'));
        if (!$proximo) continue;
        if ($proximo->format('Y-m-d') < $hoy->format('Y-m-d')) {
            $proximo->modify('+1 year');
        }

        $dias = (int)$hoy->diff($proximo)->days;
        $edad = (int)$proximo->format('Y') - (int)$fecha_nacimiento->format('Y');

        $cumpleaneros[] = [
            'codigo' => $t['codigo'],
            'ci'     => $ci_limpio,
            'nombre' => $t['nombre_completo'],
            'fecha'  => $proximo->format('d/m/Y'),
            'fecha_ymd' => $proximo->format('Y-m-d'),
            'edad'   => $edad,
            'dias'   => $dias,
            'dias_txt' => ($dias === 0) ? 'HOY' : $dias
        ];
    }

    usort($cumpleaneros, function ($a, $b) {
        return $a['dias'] <=> $b['dias'];
    });

    if ($tipo === '30') {
        $cumpleaneros = array_values(array_filter($cumpleaneros, function ($c) {
            return $c['dias'] <= 30;
        }));
    }

    return $cumpleaneros;
}

$cumpleaneros = obtenerCumpleaneros($pdo, $tipo);

if (empty($cumpleaneros)) {
    echo json_encode(['success' => false, 'mensaje' => 'No se encontraron cumpleaños para el listado seleccionado.']);
    exit;
}

// ========================
// CONFIGURACIÓN INICIAL
// ========================
$carpeta = __DIR__ . '/exports/';
if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

$nombreEmpresa = defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom';
$titulo = ($tipo === '30') ? 'CUMPLEAÑOS PRÓXIMOS 30 DÍAS' : 'LISTADO COMPLETO DE CUMPLEAÑOS';
$subtitulo = $nombreEmpresa . ' - Generado el ' . date('d/m/Y H:i') . ' - Total: ' . count($cumpleaneros) . ' trabajador(es)';

$nombreBase = 'cumpleanos_' . ($tipo === '30' ? 'proximos30' : 'completo') . '_' . date('Ymd_His');
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

// ========================
// FUNCIÓN PARA GENERAR ARCHIVOS DBF (misma lógica que exportar_bandec.php)
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
        foreach ($fields as $field) {
            $nombre = $field[0];
            $tipoF  = $field[1];
            $largo  = $field[2];
            $dec    = $field[3];
            $valor  = $fila[$nombre] ?? '';

            if ($tipoF === 'N') {
                $texto = str_pad(number_format((float)$valor, $dec, '.', ''), $largo, ' ', STR_PAD_LEFT);
            } else {
                $texto = str_pad(substr(sanitizar($valor), 0, $largo), $largo, ' ');
            }
            fwrite($fp, $texto);
        }
    }
    fwrite($fp, pack('C', 0x1A));
    fclose($fp);
    return $archivoSalida;
}

// ========================
// GENERAR EL ARCHIVO SEGÚN FORMATO
// ========================
if ($formato === 'dbf') {
    $fields = [
        ['NID',        'C', 11,  0],
        ['EXPEDIENTE', 'C', 10,  0],
        ['NOMBRE',     'C', 60,  0],
        ['FECHA',      'C', 10,  0],
        ['EDAD',       'N',  3,  0],
        ['DIAS',       'N',  3,  0]
    ];
    $registrosDbf = [];
    foreach ($cumpleaneros as $c) {
        $registrosDbf[] = [
            'NID'        => $c['ci'],
            'EXPEDIENTE' => $c['codigo'],
            'NOMBRE'     => $c['nombre'],
            'FECHA'      => $c['fecha'],
            'EDAD'       => $c['edad'],
            'DIAS'       => $c['dias']
        ];
    }
    $archivoSalida = generarDbf($carpeta, $nombreBase, $fields, $registrosDbf);
    $success = (bool)$archivoSalida;

} elseif ($formato === 'excel') {
    if (!file_exists('../vendor/autoload.php')) {
        echo json_encode(['success' => false, 'mensaje' => 'PhpSpreadsheet no instalado.']);
        exit;
    }
    require_once '../vendor/autoload.php';

    $archivoSalida = $carpeta . $nombreBase . '.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Cumpleaños');

    $headers = ['No.', 'Expediente', 'Nombre Completo', 'CI', 'Próximo Cumpleaños', 'Edad a Cumplir', 'Días Restantes'];
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('2F5597');
    $sheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $fila = 2;
    foreach ($cumpleaneros as $i => $c) {
        $sheet->setCellValue('A' . $fila, $i + 1);
        $sheet->setCellValue('B' . $fila, $c['codigo']);
        $sheet->setCellValue('C' . $fila, $c['nombre']);
        $sheet->setCellValue('D' . $fila, $c['ci']);
        $sheet->setCellValue('E' . $fila, $c['fecha']);
        $sheet->setCellValue('F' . $fila, $c['edad']);
        $sheet->setCellValue('G' . $fila, $c['dias_txt']);
        $fila++;
    }

    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(45);
    $sheet->getColumnDimension('D')->setWidth(14);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(14);
    $sheet->getColumnDimension('G')->setWidth(14);

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
        fputcsv($fp, ['No.', 'Expediente', 'Nombre Completo', 'CI', 'Próximo Cumpleaños', 'Edad a Cumplir', 'Días Restantes']);
        foreach ($cumpleaneros as $i => $c) {
            fputcsv($fp, [$i + 1, $c['codigo'], $c['nombre'], $c['ci'], $c['fecha'], $c['edad'], $c['dias_txt']]);
        }
        fclose($fp);
        $success = true;
    }

} elseif ($formato === 'txt') {
    $archivoSalida = $carpeta . $nombreBase . '.txt';
    $lineas = [];
    $lineas[] = str_repeat('=', 105);
    $lineas[] = $titulo;
    $lineas[] = $subtitulo;
    $lineas[] = str_repeat('=', 105);
    $lineas[] = '';
    $lineas[] = str_pad('No.', 5) . str_pad('Expediente', 12) . str_pad('CI', 12) . str_pad('Nombre Completo', 48) . str_pad('Próx. Cumpleaños', 20) . str_pad('Edad', 7) . 'Días';
    $lineas[] = str_repeat('-', 105);
    foreach ($cumpleaneros as $i => $c) {
        $lineas[] = str_pad($i + 1, 5) . str_pad($c['codigo'], 12) . str_pad($c['ci'], 12) . str_pad(mb_substr($c['nombre'], 0, 47, 'UTF-8'), 48) . str_pad($c['fecha'], 20) . str_pad($c['edad'], 7) . $c['dias_txt'];
    }
    $lineas[] = str_repeat('-', 105);
    $lineas[] = 'Total de trabajadores: ' . count($cumpleaneros);
    $lineas[] = '';
    $lineas[] = 'FIN DEL REPORTE - ' . $nombreEmpresa;
    if (file_put_contents($archivoSalida, mb_convert_encoding(implode("\r\n", $lineas), 'Windows-1252', 'UTF-8')) !== false) {
        $success = true;
    }

} elseif ($formato === 'word') {
    $archivoSalida = $carpeta . $nombreBase . '.doc';
    $filasHtml = '';
    foreach ($cumpleaneros as $i => $c) {
        $filasHtml .= '<tr>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem; text-align:center;">' . ($i + 1) . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem;">' . htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem;">' . htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem;">' . htmlspecialchars($c['ci'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem; text-align:center;">' . $c['fecha'] . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem; text-align:center;">' . $c['edad'] . '</td>'
            . '<td style="border:0.0625rem solid #000; padding:0.25rem; text-align:center;">' . $c['dias_txt'] . '</td>'
            . '</tr>';
    }
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
        . '<head><meta charset="utf-8"><title>' . htmlspecialchars($titulo) . '</title>'
        . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->'
        . '<style>@page WordSection1 { size: 8.5in 11in; margin:0.5in; } div.WordSection1 { page: WordSection1; } body { font-family: Arial, sans-serif; font-size:12pt; } h2 { text-align:center; font-family: Arial, sans-serif; font-size:12pt; } table { border-collapse: collapse; width:100%; font-size:12pt; } th { border:0.0625rem solid #000; padding:0.3125rem; background:#2F5597; color:#fff; font-size:12pt; } td { font-size:12pt; } </style>'
        . '</head><body><div class="WordSection1">'
        . '<h2>' . htmlspecialchars($titulo) . '</h2>'
        . '<p style="text-align:center;">' . htmlspecialchars($subtitulo) . '</p>'
        . '<table><tr>'
        . '<th>No.</th><th>Expediente</th><th>Nombre Completo</th><th>CI</th><th>Próximo Cumpleaños</th><th>Edad a Cumplir</th><th>Días Restantes</th>'
        . '</tr>' . $filasHtml . '</table>'
        . '<p style="text-align:center; margin-top:0.9375rem;">FIN DEL REPORTE - ' . htmlspecialchars($nombreEmpresa) . '</p>'
        . '</div></body></html>';
    if (file_put_contents($archivoSalida, "\xEF\xBB\xBF" . $html) !== false) {
        $success = true;
    }

} elseif ($formato === 'pdf') {
    $archivoSalida = $carpeta . $nombreBase . '.pdf';

    $w = 612; $h = 792; // Carta (Letter)
    $mIzq = 36; $mSup = 36; $mInf = 36; // Márgenes 0.5" en los 4 lados
    $anchos = [30, 55, 220, 75, 80, 40, 40];
    $totalAncho = array_sum($anchos);
    $x0 = ($w - $totalAncho) / 2;
    $altoCab = 20; $altoFila = 20;

    $headers = ['No.', 'Exp.', 'Nombre Completo', 'CI', 'Cumpleaños', 'Edad', 'Días'];

    $paginas = [];
    $contenido = '';
    $y = $h - $mSup;
    $numPag = 0;

    $iniciarPagina = function () use (&$paginas, &$contenido, &$y, &$numPag, $h, $mSup, $x0, $totalAncho, $anchos, $headers, $altoCab, $titulo, $subtitulo) {
        $numPag++;
        $contenido = '';
        $y = $h - $mSup;

        $contenido .= "BT /F2 12 Tf 0 0 0 rg 1 0 0 1 $x0 $y Tm (" . pdfEscape(sanitizar($titulo, 'Windows-1252')) . ") Tj ET\n";
        $y -= 16;
        $contenido .= "BT /F1 12 Tf 0.35 0.35 0.35 rg 1 0 0 1 $x0 $y Tm (" . pdfEscape(sanitizar($subtitulo, 'Windows-1252')) . ") Tj ET\n";
        $y -= 24;

        $cx = $x0;
        foreach ($headers as $i => $hd) {
            $an = $anchos[$i];
            $contenido .= sprintf("0.18 0.34 0.59 rg %.2f %.2f %.2f %.2f re f\n", $cx, $y - $altoCab, $an, $altoCab);
            $contenido .= "BT /F2 12 Tf 1 1 1 rg 1 0 0 1 " . ($cx + 3) . " " . ($y - 12) . " Tm (" . pdfEscape(sanitizar($hd, 'Windows-1252')) . ") Tj ET\n";
            $cx += $an;
        }
        $contenido .= sprintf("0 0 0 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $x0, $y - $altoCab, $x0 + $totalAncho, $y - $altoCab);
        $y -= $altoCab;
    };

    $iniciarPagina();

    $ind = 0;
    foreach ($cumpleaneros as $c) {
        if ($y - $altoFila < $mInf) {
            $paginas[] = $contenido;
            $iniciarPagina();
        }
        if ($ind % 2 === 1) {
            $contenido .= sprintf("0.93 0.94 0.97 rg %.2f %.2f %.2f %.2f re f\n", $x0, $y - $altoFila, $totalAncho, $altoFila);
        }

        $cx = $x0;
        $celdas = [($ind + 1), $c['codigo'], $c['nombre'], $c['ci'], $c['fecha'], $c['edad'], $c['dias_txt']];
        foreach ($anchos as $i => $an) {
            $texto = (string)$celdas[$i];
            if ($i === 0 || $i === 3 || $i === 5 || $i === 6) {
                $texto = pdfTruncar($texto, (int)floor($an / 6));
            } else {
                $texto = pdfTruncar($texto, (int)floor(($an - 6) / 6));
            }
            $tx = ($i === 0 || $i === 5 || $i === 6) ? $cx + 2 : $cx + 3;
            $contenido .= "BT /F1 12 Tf 0 0 0 rg 1 0 0 1 " . $tx . " " . ($y - 12) . " Tm (" . pdfEscape(sanitizar($texto, 'Windows-1252')) . ") Tj ET\n";
            $cx += $an;
        }
        $y -= $altoFila;
        $ind++;
    }

    $contenido .= sprintf("0 0 0 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $x0, $y, $x0 + $totalAncho, $y);
    $contenido .= "BT /F1 10 Tf 0.3 0.3 0.3 rg 1 0 0 1 $x0 30 Tm (Página " . $numPag . ") Tj ET\n";
    $paginas[] = $contenido;

    $nPaginas = count($paginas);

    $objs = [];
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $pageObjs = [];
    for ($i = 0; $i < $nPaginas; $i++) $pageObjs[] = (3 + $i);
    $objs[2] = "<< /Type /Pages /Kids [" . implode(' 0 R ', $pageObjs) . " 0 R] /Count $nPaginas >>";
    $contentStart = 3 + $nPaginas;
    for ($i = 0; $i < $nPaginas; $i++) {
        $contentObj = $contentStart + $i;
        $objs[3 + $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Resources << /Font << /F1 " . ($contentStart + $nPaginas) . " 0 R /F2 " . ($contentStart + $nPaginas + 1) . " 0 R >> >> /Contents $contentObj 0 R >>";
    }
    for ($i = 0; $i < $nPaginas; $i++) {
        $objs[$contentStart + $i] = "<< /Length " . strlen($paginas[$i]) . " >>\nstream\n" . $paginas[$i] . "\nendstream";
    }
    $font1 = $contentStart + $nPaginas;
    $font2 = $contentStart + $nPaginas + 1;
    $objs[$font1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Arial >>";
    $objs[$font2] = "<< /Type /Font /Subtype /Type1 /BaseFont /Arial-Bold >>";

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
        'archivo' => basename($archivoSalida),
        'registros' => count($cumpleaneros),
        'descarga' => 'exports/' . basename($archivoSalida)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al generar el archivo en formato: ' . $formato
    ]);
}
?>
