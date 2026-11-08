<?php
/**
 * snc225_reporte.php - Modelo oficial SNC 2-25
 * -----------------------------------------------------------------
 * Genera el "Registro de Salarios y Tiempo de Servicios" rellenado con
 * los datos reales del trabajador, usando la plantilla del modelo
 * (assets/plantillas/Registro_snc_2_25.xlsx).
 *
 * Se usa el mismo método que el Anexo 14 de empleados.php:
 *   - PhpSpreadsheet construye el libro a partir de la plantilla.
 *   - La vista previa se sirve como HTML (Writer\Html) con barra de
 *     acciones (Exportar Excel / Imprimir / Cerrar).
 *
 * Parámetros GET (los usa snc225.php):
 *   snc225_reporte = ver      -> vista previa
 *                    imprimir -> vista previa con impresión automática
 *                    pdf      -> vista previa con impresión automática y
 *                                aviso para "Guardar como PDF"
 *                    xlsx     -> descarga directa del modelo en Excel
 *   id             = id del trabajador
 *   corte          = fecha final de cálculo (Y-m-d), opcional
 *   obs            = observación escrita por el usuario para el modelo
 *                    (si falta, se usa "Corte al … — Generado el …")
 * -----------------------------------------------------------------
 */

/**
 * Ruta de la plantilla del modelo SNC 2-25.
 */
function snc225_plantilla_path(): string
{
    return __DIR__ . '/../assets/plantillas/Registro_snc_2_25.xlsx';
}

/**
 * Carga PhpSpreadsheet desde el vendor del proyecto.
 */
function snc225_cargar_autoload(): bool
{
    $vendor = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendor)) {
        return false;
    }
    require_once $vendor;
    return true;
}

/**
 * Fecha en formato dd/mm/aaaa (o '—' si no hay fecha válida).
 */
function snc225_fecha_txt(?string $fecha): string
{
    $fecha = trim((string)$fecha);
    if ($fecha === '' || strpos($fecha, '0000-00-00') === 0) {
        return '—';
    }
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/**
 * Parte un importe en [pesos, centavos]. El modelo SNC 2-25 usa dos
 * columnas para el salario devengado: la parte entera y los centavos.
 *
 * @return array{0:int,1:int}
 */
function snc225_partir_salario(float $importe): array
{
    $centavosTotales = (int)round($importe * 100);
    return [intdiv($centavosTotales, 100), $centavosTotales % 100];
}

/**
 * Datos del trabajador y matriz [año][mes] = días / salario devengado.
 *
 * Los días se toman de la nómina automática (horas laboradas / 8); bonos y
 * extraordinarias repiten horas de referencia, por eso solo se usan como
 * respaldo cuando el mes no tiene nómina automática. El salario sí suma
 * todas las nóminas del mes, igual que el total devengado del listado.
 */
function snc225_datos(PDO $pdo, int $id, string $corte): ?array
{
    $st = $pdo->prepare(
        "SELECT t.id, t.codigo, t.ci, t.nombres, t.primer_apellido, t.segundo_apellido,
                t.nombre_completo, t.fecha_alta, t.fecha_baja,
                cc.codigo AS c_costo_codigo, cc.nombre AS c_costo_nombre,
                a.nombre_area, e.salario_mensual
           FROM trabajadores t
           LEFT JOIN centros_costo cc ON cc.id = t.centro_costo_id
           LEFT JOIN areas a ON a.id = t.area_id
           LEFT JOIN escalas_salariales e ON e.id = t.escala_salarial_id
          WHERE t.id = ?"
    );
    $st->execute([$id]);
    $trabajador = $st->fetch(PDO::FETCH_ASSOC);
    if (!$trabajador) {
        return null;
    }

    $sql = "SELECT periodo_hasta, horas_laboradas, total_salario_devengado, tipo_nomina
              FROM nominas
             WHERE trabajador_id = ? AND total_salario_devengado > 0";
    $params = [$id];
    if ($corte !== '') {
        $sql .= " AND periodo_hasta <= ?";
        $params[] = $corte;
    }
    $sql .= " ORDER BY periodo_hasta";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $meses = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $ph = substr((string)$n['periodo_hasta'], 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ph, $m)) {
            continue;
        }
        $anio = (int)$m[1];
        $mes  = (int)$m[2];
        if (!isset($meses[$anio][$mes])) {
            $meses[$anio][$mes] = ['dias' => 0.0, 'salario' => 0.0, 'dias_max' => 0.0];
        }

        $meses[$anio][$mes]['salario'] += (float)$n['total_salario_devengado'];

        $horas = (float)$n['horas_laboradas'];
        $dias  = $horas > 0 ? round($horas / 8, 2) : 0.0;
        if ($dias > $meses[$anio][$mes]['dias_max']) {
            $meses[$anio][$mes]['dias_max'] = $dias;
        }
        if (($n['tipo_nomina'] ?? '') === 'automatica') {
            $meses[$anio][$mes]['dias'] += $dias;
        }
    }

    foreach ($meses as $anio => $filas) {
        foreach ($filas as $mes => $valores) {
            if ($valores['dias'] <= 0 && $valores['dias_max'] > 0) {
                $meses[$anio][$mes]['dias'] = $valores['dias_max'];
            }
            unset($meses[$anio][$mes]['dias_max']);
        }
    }
    ksort($meses);

    $anios = array_keys($meses);
    if (!$anios) {
        $anioAlta = !empty($trabajador['fecha_alta']) ? (int)substr((string)$trabajador['fecha_alta'], 0, 4) : (int)date('Y');
        $anios = [$anioAlta];
    }

    return [
        'trabajador' => $trabajador,
        'meses'      => $meses,
        'anios'      => $anios,
    ];
}

/**
 * Escribe en una celda del modelo "etiqueta" + salto de línea + "valor",
 * respetando el estilo de la plantilla (Calibri negrita).
 */
function snc225_celda_modelo($hoja, string $coord, string $etiqueta, string $valor, float $tamValor = 11, float $tamEtiqueta = 12): void
{
    $rich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();

    $runEtiqueta = $rich->createTextRun($etiqueta);
    $runEtiqueta->getFont()->setName('Calibri')->setSize($tamEtiqueta)->setBold(true);

    $runValor = $rich->createTextRun("\n" . ($valor !== '' ? $valor : '—'));
    $runValor->getFont()->setName('Calibri')->setSize($tamValor)->setBold(true);

    $hoja->setCellValue($coord, $rich);
    $hoja->getStyle($coord)->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
}

/**
 * Rellena una hoja (una página del modelo) con los datos del trabajador.
 *
 * La plantilla trae dos tablas de 5 columnas por año: filas 12-24 y 31-43.
 * Cada página admite por tanto 10 años de servicio.
 * @param string $obs Observación escrita por el usuario; si viene vacía se
 *                    usa la nota por defecto ("Corte al … — Generado el …").
 */
function snc225_llenar_hoja($hoja, array $datos, array $anios, string $corte, array $config, string $obs = ''): void
{
    $t = $datos['trabajador'];

    // El modelo oficial es el 2-25 (la plantilla original trae "SNS 2-2.25").
    $hoja->setCellValue('G1', 'SNS 2-25');

    // --- Datos del trabajador (filas 4-6) -------------------------------
    // Los recuadros de NOMBRES, EXP. LABORAL y C. IDENTIDAD no estaban
    // fusionados en la plantilla; se fusionan para poder mostrar la
    // etiqueta y el valor en dos líneas, igual que los dos primeros.
    $hoja->setCellValue('L6', null);
    $hoja->setCellValue('O6', null);
    foreach (['I4:K6', 'L4:N6', 'O4:Q6'] as $rango) {
        try {
            $hoja->mergeCells($rango);
        } catch (\Throwable $e) {
            // Si el rango ya estuviera fusionado se ignora.
        }
    }

    snc225_celda_modelo($hoja, 'A4', 'PRIMER APELLIDO', (string)($t['primer_apellido'] ?? ''));
    snc225_celda_modelo($hoja, 'D4', 'SEGUNDO APELLIDO', (string)($t['segundo_apellido'] ?? ''));
    snc225_celda_modelo($hoja, 'I4', 'NOMBRES', (string)($t['nombres'] ?? ''));
    snc225_celda_modelo($hoja, 'L4', 'EXP. LABORAL', 'No. ' . (string)($t['codigo'] ?? ''));
    snc225_celda_modelo($hoja, 'O4', 'C. IDENTIDAD', 'No. ' . (string)($t['ci'] ?? ''));

    // --- Datos de la entidad (filas 7-8) --------------------------------
    $organismo = $config['organismo'] ?? 'COMITÉ ESTATAL DE FINANZAS';
    $empresa   = $config['nombre_empresa'] ?? '';
    $codigo    = (string)($config['nit'] ?? '');
    $direccion = $config['direccion_empresa'] ?? '';

    snc225_celda_modelo($hoja, 'A7', 'ORGANISMO', $organismo, 9);
    snc225_celda_modelo($hoja, 'C7', 'EMPRESA', $empresa, 10);
    snc225_celda_modelo($hoja, 'H7', 'CÓDIGO', $codigo, 10);
    snc225_celda_modelo($hoja, 'J7', 'DIRECCIÓN', $direccion, 7);

    // FECHA DE ALTA / BAJA: etiqueta arriba y fecha debajo (igual que el
    // resto de la cabecera), dentro de las celdas N8:O8 y P8:Q8.
    snc225_celda_modelo($hoja, 'N8', 'ALTA', snc225_fecha_txt($t['fecha_alta'] ?? null), 9, 9);
    snc225_celda_modelo($hoja, 'P8', 'BAJA', snc225_fecha_txt($t['fecha_baja'] ?? null), 9, 9);

    // --- Tabla meses / años ---------------------------------------------
    // Cada bloque de año tiene: Días Trab. | Salario (pesos) | centavos.
    // El modelo original parte el salario en dos columnas: parte entera y
    // centavos, por eso se rellenan por separado. En el 5.º grupo (último)
    // la plantilla trae fusionadas las celdas O:P para la parte entera, así
    // que los centavos van en Q.
    $colDias     = ['B', 'E', 'H', 'K', 'N'];
    $colSalario  = ['C', 'F', 'I', 'L', 'O'];
    $colCentavos = ['D', 'G', 'J', 'M', 'Q'];

    foreach (array_values($anios) as $i => $anio) {
        $banda = intdiv($i, 5);          // 0 = tabla superior, 1 = tabla inferior
        $slot  = $i % 5;                 // 5 columnas por tabla
        $filaAnio  = $banda === 0 ? 10 : 29;
        $filaIni   = $banda === 0 ? 12 : 31;
        $filaTotal = $banda === 0 ? 24 : 43;

        $cd  = $colDias[$slot];
        $cs  = $colSalario[$slot];
        $cc  = $colCentavos[$slot];

        $hoja->setCellValue($cd . $filaAnio, (int)$anio);
        $hoja->getStyle($cd . $filaAnio)->getFont()->setName('Calibri')->setSize(12)->setBold(true);
        $hoja->getStyle($cd . $filaAnio)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $totalDias = 0;
        $totalSalario = 0.0;
        for ($mes = 1; $mes <= 12; $mes++) {
            $fila = $filaIni + $mes - 1;
            $dias = (int)round((float)($datos['meses'][$anio][$mes]['dias'] ?? 0));
            $salario = (float)($datos['meses'][$anio][$mes]['salario'] ?? 0);

            if ($dias <= 0 && $salario <= 0) {
                // Mes sin datos dentro de un año con actividad: se sella con
                // "////" para que no se pueda escribir a mano al imprimir.
                foreach ([$cd, $cs, $cc] as $colSello) {
                    $hoja->setCellValue($colSello . $fila, '////');
                    $hoja->getStyle($colSello . $fila)->getFont()
                        ->setName('Calibri')->setSize(8)->setItalic(true)
                        ->getColor()->setRGB('111111');
                    $hoja->getStyle($colSello . $fila)->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }
                continue;
            }

            if ($dias > 0) {
                $hoja->setCellValue($cd . $fila, $dias);
                $hoja->getStyle($cd . $fila)->getNumberFormat()->setFormatCode('0');
                $hoja->getStyle($cd . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            if ($salario > 0) {
                [$pesos, $centavos] = snc225_partir_salario($salario);
                $hoja->setCellValue($cs . $fila, $pesos);
                $hoja->getStyle($cs . $fila)->getNumberFormat()->setFormatCode('#,##0');
                $hoja->setCellValue($cc . $fila, $centavos);
                $hoja->getStyle($cc . $fila)->getNumberFormat()->setFormatCode('00');
                $hoja->getStyle($cc . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }

            $totalDias += $dias;
            $totalSalario += $salario;
        }

        $hoja->setCellValue($cd . $filaTotal, $totalDias);
        $hoja->getStyle($cd . $filaTotal)->getNumberFormat()->setFormatCode('0');
        $hoja->getStyle($cd . $filaTotal)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        [$pesosTotal, $centavosTotal] = snc225_partir_salario($totalSalario);
        $hoja->setCellValue($cs . $filaTotal, $pesosTotal);
        $hoja->getStyle($cs . $filaTotal)->getNumberFormat()->setFormatCode('#,##0');
        $hoja->setCellValue($cc . $filaTotal, $centavosTotal);
        $hoja->getStyle($cc . $filaTotal)->getNumberFormat()->setFormatCode('00');
        $hoja->getStyle($cc . $filaTotal)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }

    // --- Observaciones ---------------------------------------------------
    // La fila 47 de la plantilla está libre (los bordes están en la 48), así
    // que se fusiona A47:Q47 para que la nota quede en una sola línea.
    // Si el usuario escribió una observación se usa esa; si canceló el
    // diálogo se mantiene la nota por defecto con el corte y la generación.
    $fechaCorte = $corte !== '' ? snc225_fecha_txt($corte) : date('d/m/Y');
    $observacion = trim(preg_replace('/\s+/u', ' ', $obs));
    if ($observacion === '') {
        $observacion = 'Corte al ' . $fechaCorte . ' — Generado el ' . date('d/m/Y h:i A');
    }
    try {
        $hoja->mergeCells('A47:Q47');
    } catch (\Throwable $e) {
        // Si ya estuviera fusionado se ignora.
    }
    $hoja->setCellValue('A47', $observacion);
    $hoja->getStyle('A47')->getFont()->setName('Calibri')->setSize(8)->setItalic(true);
    $hoja->getStyle('A47')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(false)
        ->setShrinkToFit(true);
    // El borde derecho de la tabla vive en Q47; al fusionar se conserva el
    // estilo de A47, por eso se vuelve a aplicar aquí.
    $hoja->getStyle('A47')->getBorders()->getRight()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );
}

/**
 * Construye el libro SNC 2-25 del trabajador.
 *
 * Devuelve ['spreadsheet' => Spreadsheet, 'datos' => array] o null si no
 * se pudo construir (sin trabajador, sin plantilla o sin PhpSpreadsheet).
 */
function snc225_construir(PDO $pdo, int $id, string $corte, array $config, string $obs = ''): ?array
{
    if (!snc225_cargar_autoload()) {
        return null;
    }
    $plantilla = snc225_plantilla_path();
    if (!file_exists($plantilla)) {
        return null;
    }

    $datos = snc225_datos($pdo, $id, $corte);
    if (!$datos) {
        return null;
    }

    // Cada página del modelo admite 10 años (dos tablas de 5 columnas).
    $paginas = array_chunk($datos['anios'], 10);
    if (!$paginas) {
        $paginas = [[]];
    }

    $libroFinal = null;
    foreach ($paginas as $i => $aniosPagina) {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $libro = $reader->load($plantilla);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($i === 0 ? 'SNC 2-25' : 'SNC 2-25 (' . ($i + 1) . ')');

        snc225_llenar_hoja($hoja, $datos, $aniosPagina, $corte, $config, $obs);

        if ($libroFinal === null) {
            $libroFinal = $libro;
        } else {
            $libroFinal->addExternalSheet($hoja);
        }
    }

    return ['spreadsheet' => $libroFinal, 'datos' => $datos];
}

/**
 * Error amigable (nunca deja la página a medias).
 */
function snc225_error(string $mensaje): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<title>SNC 2-25</title></head>'
        . '<body style="font-family:Arial,sans-serif;padding:2rem;text-align:center;color:#111;">'
        . '<h3>Reporte SNC 2-25</h3>'
        . '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><button type="button" onclick="window.close()" style="padding:.5rem 1rem;">Cerrar</button></p>'
        . '</body></html>';
    exit;
}

/**
 * Descarga el modelo en Excel (.xlsx).
 */
function snc225_exportar_xlsx(PDO $pdo, int $id, string $corte, array $config, string $obs = ''): void
{
    $res = snc225_construir($pdo, $id, $corte, $config, $obs);
    if (!$res) {
        snc225_error('No se pudo generar el reporte SNC 2-25.');
    }

    $trabajador = $res['datos']['trabajador'];
    $codigo = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)($trabajador['codigo'] ?? ''));
    $filename = 'SNC_225' . ($codigo !== '' ? '_' . $codigo : '') . '_' . date('Ymd_His') . '.xlsx';

    if (function_exists('logAction')) {
        logAction(
            'exportar_reporte',
            'snc225',
            'Exportación del modelo SNC 2-25 (XLSX)',
            ['archivo' => $filename, 'trabajador_id' => $id],
            null,
            'success',
            null,
            $_SESSION['auth_provider'] ?? 'local'
        );
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($res['spreadsheet']);
    $writer->save('php://output');
    exit;
}

/**
 * Vista previa imprimible (mismo patrón que la vista del Anexo 14).
 *
 * @param string $modo ver       -> solo vista previa con barra de acciones
 *                     imprimir  -> igual, abriendo el diálogo de impresión
 *                     pdf       -> igual, con el aviso de "Guardar como PDF"
 */
function snc225_ver_reporte(PDO $pdo, int $id, string $corte, string $modo, array $config, string $obs = ''): void
{
    $autoPrint = in_array($modo, ['imprimir', 'pdf'], true);
    $esPdf     = $modo === 'pdf';
    $res = snc225_construir($pdo, $id, $corte, $config, $obs);
    if (!$res) {
        snc225_error('No se pudo generar el reporte SNC 2-25.');
    }

    $writerHtml = new \PhpOffice\PhpSpreadsheet\Writer\Html($res['spreadsheet']);
    $writerHtml->setDataFormula(false);
    $writerHtml->setGenerateSheetNavigationBlock(false);
    $estilos = '<style>' . $writerHtml->generateStyles(false) . '</style>';
    $tabla = $writerHtml->generateSheetData();
    if (trim((string)$tabla) === '') {
        $tabla = '<p style="text-align:center;">No se pudo generar la vista previa.</p>';
    }

    // El escritor HTML no emite la altura de las filas: el navegador las calcula
    // por contenido y el modelo se estira a dos páginas. Se inyectan las alturas
    // reales de la plantilla (suma 861.75 pt; al 84 % = 723.9 pt, cabe en Letter)
    // para que la vista y el PDF tengan la misma geometría que el Excel.
    $hojaVista   = $res['spreadsheet']->getActiveSheet();
    $altoDefecto = 15.0;
    $tabla = preg_replace_callback(
        '/<tr class="row(\d+)"/',
        function ($m) use ($hojaVista, $altoDefecto) {
            $alto = $hojaVista->getRowDimension((int)$m[1] + 1)->getRowHeight();
            if ($alto <= 0) {
                $alto = $altoDefecto;
            }
            return '<tr class="row' . $m[1] . '" style="height:' . $alto . 'pt"';
        },
        $tabla
    );

    $h = function ($texto) {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    };

    $trabajador = $res['datos']['trabajador'];
    $titulo = 'SNC 2-25 · ' . ($trabajador['nombre_completo'] ?? '');
    $enlaceExcel = 'snc225.php?snc225_reporte=xlsx&id=' . $id
        . ($corte !== '' ? '&corte=' . urlencode($corte) : '')
        . ($obs !== '' ? '&obs=' . urlencode($obs) : '');

    $css = '
        /* Misma configuración de página que la plantilla Excel: Letter vertical,
           márgenes 0.46 / 0.18 / 0.47 / 0.07 in y escala 84 % (fitToWidth). */
        @page { size: letter portrait; margin: 0.46in 0.07in 0.47in 0.18in; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #e5e7eb; font-family: Arial, sans-serif; }
        #tb-snc { position: fixed; top: 0; left: 0; right: 0; z-index: 99999; background: linear-gradient(135deg,#1e3a8a,#2563eb); padding: 0.625rem 1.25rem; display: flex; justify-content: center; align-items: center; gap: 0.6rem; flex-wrap: wrap; box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.35); border-bottom: 0.1875rem solid #1e40af; }
        #tb-snc .etiqueta { color: #e0e7ff; font-weight: bold; font-size: 0.8125rem; }
        #tb-snc button, #tb-snc a { padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; font-size: 0.75rem; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; color: #fff; }
        #btnExportarSnc { background: #16a34a; }
        #btnExportarSnc:hover { background: #15803d; }
        #btnImprimirSnc { background: #22c55e; }
        #btnImprimirSnc:hover { background: #16a34a; }
        #btnCerrarSnc { background: #ef4444; }
        #btnCerrarSnc:hover { background: #dc2626; }
        #avisoSnc { background: #fef3c7; border-bottom: 0.0625rem solid #f59e0b; color: #92400e; font-size: 0.75rem; font-weight: bold; text-align: center; padding: 0.4rem 1rem; }
        .snc-wrap { padding: 4.5rem 1rem 2rem; overflow-x: auto; }
        /* Ancho fijo = suma exacta de las columnas del Excel (698.25 pt = 9.7 in).
           Sin este ancho el navegador ensancha las columnas segun el contenido
           (auto layout) y el modelo deja de coincidir con la plantilla. */
        .snc-wrap table { table-layout: fixed; width: 698.25pt; border-collapse: collapse; margin: 0 auto; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.18); }
        .snc-wrap td, .snc-wrap th { overflow: hidden; }
        @media print {
            body { background: #fff; }
            #tb-snc, #avisoSnc { display: none !important; }
            .snc-wrap { padding: 0; overflow: visible; }
            .snc-wrap table { box-shadow: none; zoom: 0.84; }
        }';

    $aviso = $esPdf
        ? '<div id="avisoSnc">PDF: en el diálogo de impresión elija "Guardar como PDF" / "Microsoft Print to PDF".</div>'
        : '';
    $script = $autoPrint
        ? '<script>window.addEventListener("load", function(){ setTimeout(function(){ window.print(); }, 600); });</script>'
        : '';

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $h($titulo) . '</title>'
        . $estilos
        . '<style>' . $css . '</style></head><body>'
        . '<div id="tb-snc">'
        . '<span class="etiqueta">SNC 2-25 — REGISTRO DE SALARIOS Y TIEMPO DE SERVICIOS</span>'
        . '<a id="btnExportarSnc" href="' . $h($enlaceExcel) . '">📊 Exportar Excel</a>'
        . '<button type="button" id="btnImprimirSnc" onclick="window.print()">🖨️ Imprimir</button>'
        . '<button type="button" id="btnCerrarSnc" onclick="window.close()">✖ Cerrar</button>'
        . '</div>'
        . $aviso
        . '<div class="snc-wrap">'
        . $tabla
        . '</div>'
        . $script
        . '</body></html>';
    exit;
}

/**
 * Punto de entrada: atiende ?snc225_reporte=ver|imprimir|pdf|xlsx.
 */
function snc225_reporte_manejar(PDO $pdo, array $config): void
{
    if (!isset($_GET['snc225_reporte'])) {
        return;
    }

    $formato = strtolower(trim((string)$_GET['snc225_reporte']));
    if (!in_array($formato, ['ver', 'imprimir', 'pdf', 'xlsx'], true)) {
        $formato = 'ver';
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $corte = isset($_GET['corte']) ? trim((string)$_GET['corte']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte)) {
        $corte = '';
    }

    // Observación escrita por el usuario en el diálogo previo (opcional).
    $obs = isset($_GET['obs']) ? trim((string)$_GET['obs']) : '';
    $obs = preg_replace('/\s+/u', ' ', $obs);
    if (mb_strlen($obs, 'UTF-8') > 200) {
        $obs = mb_substr($obs, 0, 200, 'UTF-8');
    }

    if ($id <= 0) {
        snc225_error('Debe seleccionar un trabajador para generar el reporte.');
    }

    if ($formato === 'xlsx') {
        snc225_exportar_xlsx($pdo, $id, $corte, $config, $obs);
        return;
    }

    snc225_ver_reporte($pdo, $id, $corte, $formato, $config, $obs);
    exit;
}
