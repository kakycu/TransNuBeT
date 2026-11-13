<?php
/**
 * anexo14_reporte.php - Modelo Anexo 14 (plantilla de cargos y registros)
 * -----------------------------------------------------------------
 * Lo usa modules/empleados.php para la vista previa (?ver_anexo14) y la
 * exportación (?exportar_anexo14), y Reportes -> Modelos para descargar el
 * modelo en blanco (?plantilla=anexo14), así el modelo descargado es
 * exactamente el mismo que se exporta desde Empleados.
 *
 * @param PDO  $pdo      Conexión a la base de datos.
 * @param bool $enBlanco true para devolver la plantilla sin datos, con las
 *                       filas de captura vacías y el total en fórmulas.
 * -----------------------------------------------------------------
 */

function anexo14_construir($pdo, bool $enBlanco = false) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        header('Content-Type: text/html; charset=utf-8');
        die('Error: No se encontró la librería PhpSpreadsheet.');
    }
    
    require_once $vendorPath;
    
    // 1. OBTENER CONFIGURACIÓN
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'intendente')");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['parametro']] = $row['valor'];
    }
    
    $nombre_empresa = $config['nombre_empresa'] ?? COMPANY_NAME;
    $jefe_proyecto = $config['jefe_proyecto'] ?? JEFE_PROYECTO;
    $intendente = $config['intendente'] ?? 'Eladio Francisco Ávalos';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ANEXO 14-' . $nombre_empresa);

    // --- CONFIGURACIÓN DE PÁGINA (Hoja carta 8 1/2 x 11) ---
    $pageSetup = $sheet->getPageSetup();
    $pageSetup->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
    $pageSetup->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
    
    // Márgenes (en pulgadas)
    $margins = $sheet->getPageMargins();
    $margins->setTop(0.28);
    $margins->setHeader(0.3);
    $margins->setLeft(0.11);
    $margins->setRight(0.04);
    $margins->setBottom(0.25);
    $margins->setFooter(0.3);
    
    // Escala y ajuste
    $pageSetup->setScale(85); // Ajuste para que quepa en una página
    $pageSetup->setFitToWidth(1);
    $pageSetup->setFitToHeight(0); // 0 = automático según contenido

    // --- INMOVILIZAR PANELES ---
    // Inmoviliza desde la fila 11 (las filas 1-10 quedan fijas)
    $sheet->freezePane('A11');
    $sheet->setSelectedCell('A1'); // Opcional: seleccionar celda inicial

    // --- CONFIGURACIÓN DE COLUMNAS (Anchos aproximados a la imagen) ---
    $sheet->getColumnDimension('A')->setWidth(50); // Descripción
    $sheet->getColumnDimension('B')->setWidth(8);  // Categ.
    $sheet->getColumnDimension('C')->setWidth(8);  // Cant.
    $sheet->getColumnDimension('D')->setWidth(12); // Nivel Prep.
    $sheet->getColumnDimension('E')->setWidth(10); // Grupo Escala
    $sheet->getColumnDimension('F')->setWidth(12); // Salario Escala
    $sheet->getColumnDimension('G')->setWidth(12); // Salario Resol 29

    // --- ENCABEZADO SUPERIOR (Filas 1 a 9) ---
    // Fila 1
    $sheet->setCellValue('A1', 'ANEXO 14');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '002060']],
        'alignment' => ['horizontal' => 'center']
    ]);

    // Fila 2
    $sheet->setCellValue('A2', 'PLANTILLA DE CARGOS Y REGISTROS DE TRABAJADORES');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => 'center']
    ]);

    // Fila 3: Nombre de la empresa (combinada de ancho completo)
    $sheet->setCellValue('A3', $nombre_empresa);
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '002060']],
        'alignment' => ['horizontal' => 'center']
    ]);

    // Fila 4: Propuesto por
    $sheet->setCellValue('A4', 'Propuesto por Director de Proyecto:');
    $sheet->setCellValue('B4', $jefe_proyecto);
    $sheet->mergeCells('B4:D4');
    $sheet->getStyle('A4')->getFont()->setBold(true);
    $sheet->getStyle('B4')->getFont()->setBold(true)->getColor()->setRGB('002060');

    // Fila 5: Aprobado por
    $sheet->setCellValue('A5', 'Aprobado por Intendente:');
    $sheet->setCellValue('B5', $intendente);
    $sheet->mergeCells('B5:D5');
    $sheet->getStyle('A5')->getFont()->setBold(true);
    $sheet->getStyle('B5')->getFont()->setBold(true)->getColor()->setRGB('002060');

    // Fila 7: Plantilla, Año, Hoja
    $sheet->setCellValue('A7', 'Plantilla: ' . $nombre_empresa);
    $sheet->setCellValue('D7', 'Año: ' . date('Y'));
    $sheet->setCellValue('G7', 'Hoja No. 1');
    $sheet->getStyle('A7:G7')->getFont()->setBold(true);

    // Fila 8: Fecha
	$fecha_cliente = date('d/m/Y-h:iA');
	$sheet->setCellValue('D8', 'Fecha: ' . $fecha_cliente);
    $sheet->getStyle('D8')->getFont()->setBold(true);

    // --- FILA 10: CABECERAS DE TABLA (Diseño exacto) ---
    $headers = [
        'A10' => 'Descripción / Órgano / Cargo / Técnica',
        'B10' => 'Categ.',
        'C10' => 'Cant.',
        'D10' => 'Nivel Prep.',
        'E10' => 'Grupo Escala',
        'F10' => 'Salario Escala',
        'G10' => 'Resol. 14y15/2026'
    ];

    foreach ($headers as $celda => $texto) {
        $sheet->setCellValue($celda, $texto);
    }

    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2F5597'] // Azul oscuro de la imagen
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
        ]
    ];
    $sheet->getStyle('A10:G10')->applyFromArray($styleHeader);
    $sheet->getRowDimension('10')->setRowHeight(45);

    // --- MODELO EN BLANCO ---------------------------------------------
    // Sin datos: la plantilla con 20 filas de captura vacías y la fila de
    // total en fórmulas, para llenar a mano desde Reportes -> Modelos.
    if ($enBlanco) {
        $primera = 11;
        $ultima  = $primera + 19;

        // La plantilla se llena a mano: el año y la fecha quedan en blanco,
        // solo con su etiqueta.
        $sheet->setCellValue('D7', 'Año:');
        $sheet->setCellValue('D8', 'Fecha:');

        $sheet->getStyle('A' . $primera . ':G' . $ultima)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => 'center']
        ]);
        $sheet->getStyle('B' . $primera . ':E' . $ultima)->getAlignment()->setHorizontal('center');
        $sheet->getStyle('C' . $primera . ':C' . $ultima)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('F' . $primera . ':G' . $ultima)->getNumberFormat()->setFormatCode('#,##0.00');
        for ($f = $primera; $f <= $ultima; $f++) {
            $sheet->getRowDimension($f)->setRowHeight(18);
        }

        // Fila final: total de la plantilla (mismas fórmulas que el reporte).
        $fila = $ultima + 1;
        $sheet->setCellValue('A' . $fila, 'TOTAL DE LA PLANTILLA');
        $sheet->mergeCells('A' . $fila . ':B' . $fila);
        $sheet->setCellValue('C' . $fila, '=SUM(C' . $primera . ':C' . $ultima . ')');
        $sheet->setCellValue('F' . $fila, '=SUM(F' . $primera . ':F' . $ultima . ')');
        $sheet->setCellValue('G' . $fila, '=SUM(G' . $primera . ':G' . $ultima . ')');
        $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFC000']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
        $sheet->getStyle('C' . $fila)->getAlignment()->setHorizontal('center');
        $sheet->getStyle('F' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

        return $spreadsheet;
    }

    // --- PROCESAMIENTO DE DATOS ---
    $sql = "SELECT cp.id, cp.nombre_cargo, cp.organo_grupo, cp.nivel_preparacion, co.codigo as categ, 
                   e.escala_numero, e.salario_mensual, COUNT(t.id) as cant
            FROM cargos_plantilla cp
            LEFT JOIN trabajadores t ON t.cargo_id = cp.id AND t.activo = 1
            LEFT JOIN categorias_ocupacionales co ON cp.categoria_ocupacional_id = co.id
            LEFT JOIN escalas_salariales e ON cp.escala_salarial_id = e.id
            WHERE cp.activo = 1
            GROUP BY cp.id, cp.organo_grupo, cp.nombre_cargo
            ORDER BY cp.id";
    $cargos_db = $pdo->query($sql)->fetchAll();

    $estructura = [];
    foreach ($cargos_db as $c) {
        $g = mb_strtoupper(trim($c['organo_grupo']), 'UTF-8');
        $key = (stripos($g, 'DIRECC')!==false) ? 'DIRECCIÓN' : 
               ((stripos($g, 'TÉCNICO')!==false || stripos($g, 'TECNICO')!==false) ? 'GRUPO TÉCNICO PRODUCTIVO' : 
               ((stripos($g, 'CONSTRUC')!==false) ? 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO' : 
               ((stripos($g, 'INDIRECTO')!==false) ? 'INDIRECTOS A LA PRODUCCIÓN' : 'OTROS')));
        
        $estructura[$key][] = $c;
    }

    $fila = 11;
    $grupos_config = [
        'DIRECCIÓN' => '4B0082', // Índigo oscuro (clásico, muy elegante)
        'GRUPO TÉCNICO PRODUCTIVO' => '5B9BD5', // Azul claro
        'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO' => '70AD47', // Verde
        'INDIRECTOS A LA PRODUCCIÓN' => 'ED7D31' // Naranja
    ];

    $filas_subtotales = [];

    foreach ($grupos_config as $nombre_grupo => $color_hex) {
        if (!isset($estructura[$nombre_grupo])) continue;

        // Fila de Título de Grupo
        $sheet->setCellValue('A' . $fila, $nombre_grupo);
        $sheet->mergeCells('A' . $fila . ':G' . $fila);
        $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $color_hex]],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
        $fila++;

        $inicio_grupo = $fila;
        foreach ($estructura[$nombre_grupo] as $cargo) {
            $sheet->setCellValue('A' . $fila, $cargo['nombre_cargo']);
            $sheet->setCellValue('B' . $fila, $cargo['categ']);
            $sheet->setCellValue('C' . $fila, $cargo['cant']);
            $sheet->setCellValue('D' . $fila, $cargo['nivel_preparacion']);
            $sheet->setCellValue('E' . $fila, numeroRomano($cargo['escala_numero']));
            $sheet->setCellValue('F' . $fila, $cargo['salario_mensual']);
            $sheet->setCellValue('G' . $fila, "=F{$fila}*C{$fila}");

            // Formato de celdas de datos
            $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                'alignment' => ['vertical' => 'center']
            ]);
            $sheet->getStyle('B' . $fila . ':E' . $fila)->getAlignment()->setHorizontal('center');
            $sheet->getStyle('F' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
            $fila++;
        }
        $fin_grupo = $fila - 1;

        // Fila de Subtotal (Amarillo Suave)
        $sheet->setCellValue('A' . $fila, 'TOTAL ' . $nombre_grupo);
        $sheet->mergeCells('A' . $fila . ':B' . $fila);
        $sheet->setCellValue('C' . $fila, "=SUM(C{$inicio_grupo}:C{$fin_grupo})");
        $sheet->setCellValue('F' . $fila, "=SUM(F{$inicio_grupo}:F{$fin_grupo})");
        $sheet->setCellValue('G' . $fila, "=SUM(G{$inicio_grupo}:G{$fin_grupo})");

        $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFEB9C']], // Amarillo de la imagen
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
        $sheet->getStyle('C' . $fila)->getAlignment()->setHorizontal('center');
        $sheet->getStyle('F' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $filas_subtotales[] = $fila;
        $fila++;
    }

    // --- FILA FINAL: TOTAL DE LA PLANTILLA (Amarillo fuerte) ---
    $sheet->setCellValue('A' . $fila, 'TOTAL DE LA PLANTILLA');
    $sheet->mergeCells('A' . $fila . ':B' . $fila);
    
    if (!empty($filas_subtotales)) {
        $f_cant = "=" . implode('+', array_map(fn($f) => "C$f", $filas_subtotales));
        $f_esc  = "=" . implode('+', array_map(fn($f) => "F$f", $filas_subtotales));
        $f_resol = "=" . implode('+', array_map(fn($f) => "G$f", $filas_subtotales));
        
        $sheet->setCellValue('C' . $fila, $f_cant);
        $sheet->setCellValue('F' . $fila, $f_esc);
        $sheet->setCellValue('G' . $fila, $f_resol);
    }

    $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFC000']], // Amarillo oro
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);
    $sheet->getStyle('C' . $fila)->getAlignment()->setHorizontal('center');
    $sheet->getStyle('F' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

    return $spreadsheet;
}

