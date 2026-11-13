<?php
/**
 * snc225_reporte.php - Modelo oficial SC-4-08
 * -----------------------------------------------------------------
 * Genera el "Registro de Salarios y Tiempo de Servicios" rellenado con
 * los datos reales del trabajador, a partir del modelo descrito en código
 * (includes/snc225_modelo.php); no se lee ninguna plantilla .xlsx.
 *
 * Se usa el mismo método que el Anexo 14 de empleados.php:
 *   - PhpSpreadsheet construye el libro en memoria.
 *   - La vista previa se sirve como HTML (Writer\Html) con barra de
 *     acciones (Exportar Excel / Imprimir / Cerrar).
 *
 * Parámetros GET (los usa snc225.php):
 *   snc225_reporte = ver      -> vista previa
 *                    imprimir -> vista previa con impresión automática
 *                    pdf      -> descarga directa del modelo en PDF (una
 *                                página Letter, generada con jsPDF)
 *                    xlsx     -> descarga directa del modelo en Excel
 *   id             = id del trabajador
 *   corte          = fecha final de cálculo (Y-m-d), opcional
 *   obs            = observación escrita por el usuario para el modelo
 *                    (si falta, se usa "Corte al … — Generado el …")
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/snc225_modelo.php';

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
 * Parte un importe en [pesos, centavos]. El modelo SC-4-08 usa dos
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
function snc225_datos(PDO $pdo, int $id, string $corte, array $aniosFiltro = []): ?array
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
    // Años elegidos en la tarjeta: el reporte se emite solo con esos años.
    $aniosFiltro = array_values(array_unique(array_filter(array_map('intval', $aniosFiltro), function ($valor) {
        return $valor >= 1900 && $valor <= 2100;
    })));
    sort($aniosFiltro);
    if ($aniosFiltro) {
        $sql .= " AND YEAR(periodo_hasta) IN (" . implode(',', array_fill(0, count($aniosFiltro), '?')) . ")";
        foreach ($aniosFiltro as $valor) {
            $params[] = $valor;
        }
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

    // Con años elegidos se emiten exactamente esos, aunque alguno no tenga
    // nóminas (sale con sus totales en cero).
    $anios = $aniosFiltro ?: array_keys($meses);
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
function snc225_celda_modelo($hoja, string $coord, string $etiqueta, string $valor, float $tamValor = 11, float $tamEtiqueta = 12, bool $centrado = false): void
{
    $rich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();

    $runEtiqueta = $rich->createTextRun($etiqueta);
    $runEtiqueta->getFont()->setName('Calibri')->setSize($tamEtiqueta)->setBold(true);

    $runValor = $rich->createTextRun("\n" . ($valor !== '' ? $valor : '—'));
    $runValor->getFont()->setName('Calibri')->setSize($tamValor)->setBold(true);

    $hoja->setCellValue($coord, $rich);
    $hoja->getStyle($coord)->getAlignment()
        ->setWrapText(true)
        ->setHorizontal($centrado
            ? \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            : \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
        ->setVertical($centrado
            ? \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            : \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
}

/**
 * Rellena una hoja (una página del modelo) con los datos del trabajador.
 *
 * La plantilla trae dos tablas de 5 columnas por año: filas 12-24 y 31-43.
 * Cada página admite por tanto 10 años de servicio.
 * @param string $obs Observación escrita por el usuario; si viene vacía se
 *                    usa la nota por defecto ("Corte al … — Generado el …").
 */
/**
 * Combina un rango sin dejar combinados solapados: cuando dos rangos se
 * pisan, Excel repara el archivo y quita los combinados, así que se omite
 * el nuevo (el existente manda).
 */
function snc225_fusionar($hoja, string $rango): void
{
    $nuevo = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($rango);
    foreach (array_keys($hoja->getMergeCells()) as $existente) {
        if ($existente === $rango) {
            return;
        }
        $otro = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($existente);
        if ($nuevo[0][0] <= $otro[1][0] && $nuevo[1][0] >= $otro[0][0]
            && $nuevo[0][1] <= $otro[1][1] && $nuevo[1][1] >= $otro[0][1]) {
            return;
        }
    }
    $hoja->mergeCells($rango);
}

function snc225_llenar_hoja($hoja, array $datos, array $anios, string $corte, array $config, string $obs = ''): void
{
    $t = $datos['trabajador'];

    // La cabecera del modelo lleva solo el identificador del reporte; el
    // nombre del trabajador va en el título de la hoja del Excel.
    $hoja->setCellValue('G1', 'SNC - 225');

    // Cabecera: las tres líneas del bloque izquierdo van en una sola celda
    // (A1:F3) centrada, y "No. ______" ocupa todo el recuadro derecho
    // (O1:Q3) en lugar de quedar repartido en celdas sueltas de las 3 filas.
    $hoja->setCellValue('A1', "COMITÉ ESTATAL DE FINANZAS\nSISTEMA NACIONAL DE CONTABILIDAD\nDATOS DEL TRABAJADOR");
    $hoja->setCellValue('A2', null);
    $hoja->setCellValue('A3', null);
    snc225_fusionar($hoja, 'A1:F3');
    $hoja->getStyle('A1')->getFont()->setName('Calibri')->setSize(12)->setBold(true);
    $hoja->getStyle('A1')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $hoja->getStyle('A1')->getBorders()->getBottom()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );

    // "No. ______" estaba en P2:Q2; al fusionar O1:Q3 hay que liberar antes
    // esa fusión, que quedaría dentro del nuevo rango.
    $hoja->setCellValue('P2', null);
    try {
        $hoja->unmergeCells('P2:Q2');
    } catch (\Throwable $e) {
        // Si no estaba fusionado se ignora.
    }
    $hoja->setCellValue('O1', 'No. ______');
    snc225_fusionar($hoja, 'O1:Q3');
    $hoja->getStyle('O1')->getFont()->setSize(12)->setBold(true);
    $hoja->getStyle('O1')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(false);
    $hoja->getStyle('O1')->getBorders()->getRight()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );
    $hoja->getStyle('O1')->getBorders()->getBottom()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );

    // Recuadro completo de G1:I3 (cabecera del modelo): se marcan los cuatro
    // lados en todas las celdas del rango combinado para que Excel y Word
    // dibujen el recuadro entero y no solo un lado.
    foreach (['G1', 'H1', 'I1', 'G2', 'H2', 'I2', 'G3', 'H3', 'I3'] as $coordRecuadro) {
        $hoja->getStyle($coordRecuadro)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
    }

    // Divisoria vertical entre el recuadro "SNC - 225" (G1:I3) y el título
    // "REGISTRO DE SALARIOS Y TIEMPO DE SERVICIOS" (J1:N3).
    $hoja->getStyle('J1')->getBorders()->getLeft()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );

    // --- Datos del trabajador (filas 4-6) -------------------------------
    // Los recuadros de NOMBRES, EXP. LABORAL y C. IDENTIDAD no estaban
    // fusionados en la plantilla; se fusionan para poder mostrar la
    // etiqueta y el valor en dos líneas, igual que los dos primeros.
    $hoja->setCellValue('L6', null);
    $hoja->setCellValue('O6', null);
    foreach (['I4:K6', 'L4:N6', 'O4:Q6'] as $rango) {
        snc225_fusionar($hoja, $rango);
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
    snc225_celda_modelo($hoja, 'N8', 'ALTA', snc225_fecha_txt($t['fecha_alta'] ?? null), 9, 9, true);
    snc225_celda_modelo($hoja, 'P8', 'BAJA', snc225_fecha_txt($t['fecha_baja'] ?? null), 9, 9, true);

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
                $hoja->getStyle($cd . $fila)->getFont()->setBold(true);
                $hoja->getStyle($cd . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            if ($salario > 0) {
                [$pesos, $centavos] = snc225_partir_salario($salario);
                $hoja->setCellValue($cs . $fila, $pesos);
                $hoja->getStyle($cs . $fila)->getNumberFormat()->setFormatCode('#,##0');
                $hoja->getStyle($cs . $fila)->getFont()->setBold(true);
                $hoja->setCellValue($cc . $fila, $centavos);
                $hoja->getStyle($cc . $fila)->getNumberFormat()->setFormatCode('00');
                $hoja->getStyle($cc . $fila)->getFont()->setBold(true);
                $hoja->getStyle($cc . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }

            $totalDias += $dias;
            $totalSalario += $salario;
        }

        $hoja->setCellValue($cd . $filaTotal, $totalDias);
        $hoja->getStyle($cd . $filaTotal)->getNumberFormat()->setFormatCode('0');
        $hoja->getStyle($cd . $filaTotal)->getFont()->setBold(true);
        $hoja->getStyle($cd . $filaTotal)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        [$pesosTotal, $centavosTotal] = snc225_partir_salario($totalSalario);
        $hoja->setCellValue($cs . $filaTotal, $pesosTotal);
        $hoja->getStyle($cs . $filaTotal)->getNumberFormat()->setFormatCode('#,##0');
        $hoja->getStyle($cs . $filaTotal)->getFont()->setBold(true);
        $hoja->setCellValue($cc . $filaTotal, $centavosTotal);
        $hoja->getStyle($cc . $filaTotal)->getNumberFormat()->setFormatCode('00');
        $hoja->getStyle($cc . $filaTotal)->getFont()->setBold(true);
        $hoja->getStyle($cc . $filaTotal)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }

    // La fila 27 es la franja en blanco entre las firmas y la segunda tabla
    // de años: queda combinada de lado a lado (A:Q) como una sola banda.
    snc225_fusionar($hoja, 'A27:Q27');

    // --- Observaciones ---------------------------------------------------
    // El recuadro ocupa dos filas completas (46 y 47) combinadas en una sola
    // celda A46:Q47, con la etiqueta y la nota juntas.
    // Si el usuario escribió una observación se usa esa; si canceló el
    // diálogo se mantiene la nota por defecto con el corte y la generación.
    $fechaCorte = $corte !== '' ? snc225_fecha_txt($corte) : date('d/m/Y');
    $observacion = trim(preg_replace('/\s+/u', ' ', $obs));
    if ($observacion === '') {
        $observacion = 'Corte al ' . $fechaCorte . ' — Generado el ' . date('d/m/Y h:i A');
    }
    // La etiqueta va en su propia línea y debajo el texto de la observación.
    $hoja->setCellValue('A46', "OBSERVACIONES:\n" . $observacion);
    snc225_fusionar($hoja, 'A46:Q48');
    $hoja->getStyle('A46')->getFont()->setName('Calibri')->setSize(9)->setItalic(true);
    $hoja->getStyle('A46')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true)
        ->setShrinkToFit(false);
    // Al fusionar se conserva el estilo de A46: se reponen los bordes derecho
    // e inferior que cerraban la tabla en la última columna y en la fila 47.
    $hoja->getStyle('A46')->getBorders()->getRight()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );
    $hoja->getStyle('A46')->getBorders()->getBottom()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );

    // Pie del modelo: toda la fila (A49:Q49) combinada, con el texto a la
    // derecha en negrita e itálica. Se libera antes L49:Q49 del modelo para
    // no dejar dos combinados solapados (Excel repara el archivo si los hay).
    $textoPie = trim((string)$hoja->getCell('L49')->getValue());
    if ($textoPie === '') {
        $textoPie = 'Modelo 350.0 T. Impresiones UnicornioSoftware';
    } elseif (strpos($textoPie, 'Impresiones UnicornioSoftware') === false) {
        $textoPie = str_replace('UnicornioSoftware', 'Impresiones UnicornioSoftware', $textoPie);
    }
    $fuentePie = $hoja->getStyle('L49')->getFont();
    try {
        $hoja->unmergeCells('L49:Q49');
    } catch (\Throwable $e) {
        // Si no estaba combinado se ignora.
    }
    $hoja->setCellValue('L49', null);
    $hoja->setCellValue('A49', $textoPie);
    $hoja->getStyle('A49')->getFont()
        ->setName($fuentePie->getName())
        ->setSize($fuentePie->getSize())
        ->setBold(true)
        ->setItalic(true);
    snc225_fusionar($hoja, 'A49:Q49');
    $hoja->getStyle('A49')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(false);
}/**
 * Crea un libro con el modelo SC-4-08 vacío.
 *
 * Anchos de columna, altos de fila, combinados, estilos, textos fijos y
 * configuración de página salen de snc225_modelo_definicion(): no se lee
 * ninguna plantilla .xlsx.
 */
function snc225_crear_libro(): ?\PhpOffice\PhpSpreadsheet\Spreadsheet
{
    if (!snc225_cargar_autoload()) {
        return null;
    }

    $def = snc225_modelo_definicion();
    $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $hoja = $libro->getActiveSheet();

    foreach ($def['columnas'] as $columna => $ancho) {
        $hoja->getColumnDimension($columna)->setWidth((float)$ancho);
    }
    foreach ($def['filas'] as $fila => $alto) {
        $hoja->getRowDimension((int)$fila)->setRowHeight((float)$alto);
    }

    foreach ($def['valores'] as $coord => $valor) {
        $hoja->setCellValueExplicit(
            $coord,
            $valor[1],
            $valor[0] === 'n'
                ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
    }

    foreach ($def['merges'] as $rango) {
        snc225_fusionar($hoja, $rango);
    }

    // Las ~30 propiedades de cada celda se aplican en una sola llamada:
    // hacerlo con setters uno a uno costaba ~20 veces más (17 s de las 882
    // celdas del modelo, lo que provocaba el "maximum execution time").
    foreach ($def['celdas'] as $coord => $indice) {
        $e = $def['estilos'][$indice];

        $bordes = [];
        foreach (['i' => 'left', 'd' => 'right', 's' => 'top', 'n' => 'bottom'] as $lado => $nombre) {
            $bordes[$nombre] = [
                'borderStyle' => $e['bordes'][$lado][0],
                'color' => ['argb' => $e['bordes'][$lado][1]],
            ];
        }

        $estilo = [
            'font' => [
                'name' => $e['fuente']['n'],
                'size' => (float)$e['fuente']['t'],
                'bold' => (bool)$e['fuente']['b'],
                'italic' => (bool)$e['fuente']['i'],
                'underline' => $e['fuente']['s'],
                'color' => ['argb' => $e['fuente']['c']],
            ],
            'alignment' => [
                'horizontal' => $e['alinea']['h'],
                'vertical' => $e['alinea']['v'],
                'wrapText' => (bool)$e['alinea']['w'],
                'textRotation' => (int)$e['alinea']['g'],
            ],
            'borders' => $bordes,
            'numberFormat' => ['formatCode' => $e['fmt']],
        ];
        if ($e['relleno'][0] !== 'none') {
            $estilo['fill'] = [
                'fillType' => $e['relleno'][0],
                'endColor' => ['argb' => $e['relleno'][1]],
            ];
        }

        $hoja->getStyle($coord)->applyFromArray($estilo);
    }

    $pagina = $def['pagina'];
    $setup = $hoja->getPageSetup();
    $setup->setPaperSize((int)$pagina['papel']);
    $setup->setOrientation($pagina['orientacion']);
    $setup->setScale((int)$pagina['escala']);
    $setup->setFitToWidth((int)$pagina['ajustarAncho']);
    $setup->setFitToHeight((int)$pagina['ajustarAlto']);
    $setup->setFitToPage((bool)$pagina['ajustarPagina']);
    if ($pagina['area'] !== '') {
        $setup->setPrintArea($pagina['area']);
    }
    $setup->setHorizontalCentered((bool)$pagina['centrarH']);
    $setup->setVerticalCentered((bool)$pagina['centrarV']);
    if ($pagina['repetir'] !== '' && $pagina['repetir'] !== '0:0') {
        $setup->setRowsToRepeatAtTop($pagina['repetir']);
    }

    $margenes = $hoja->getPageMargins();
    $margenes->setLeft((float)$pagina['margenes']['izq']);
    $margenes->setRight((float)$pagina['margenes']['der']);
    $margenes->setTop((float)$pagina['margenes']['sup']);
    $margenes->setBottom((float)$pagina['margenes']['inf']);
    $margenes->setHeader((float)$pagina['margenes']['enc']);
    $margenes->setFooter((float)$pagina['margenes']['pie']);

    $hoja->getHeaderFooter()->setOddHeader($pagina['encabezado']);
    $hoja->getHeaderFooter()->setOddFooter($pagina['piePagina']);
    $hoja->setShowGridLines((bool)$pagina['cuadricula']);
    $hoja->getSheetView()->setZoomScale((int)$pagina['zoom']);

    // La columna R no forma parte del formulario (solo conserva estilos de la
    // plantilla original): se oculta para que el Excel se vea y se imprima con
    // las mismas 17 columnas que la vista previa, el PDF y el Word.
    $hoja->getColumnDimension('R')->setVisible(false);

    return $libro;
}

/**
 * Construye el libro SC-4-08 del trabajador.
 *
 * Devuelve ['spreadsheet' => Spreadsheet, 'datos' => array] o null si no
 * se pudo construir (sin trabajador o sin PhpSpreadsheet).
 */
function snc225_construir(PDO $pdo, int $id, string $corte, array $config, string $obs = '', array $anios = []): ?array
{
    if (!snc225_cargar_autoload()) {
        return null;
    }

    $datos = snc225_datos($pdo, $id, $corte, $anios);
    if (!$datos) {
        return null;
    }

    // Cada página del modelo admite 10 años (dos tablas de 5 columnas).
    $paginas = array_chunk($datos['anios'], 10);
    if (!$paginas) {
        $paginas = [[]];
    }

    // El título de la hoja (pestaña del Excel) lleva el nombre del trabajador;
    // Excel admite como máximo 31 caracteres.
    $corto = snc225_nombre_corto($datos['trabajador']);
    $tituloHoja = snc225_recortar('SC-4-08' . ($corto !== '' ? ' ' . $corto : ''), 31);

    $libroFinal = null;
    foreach ($paginas as $i => $aniosPagina) {
        $libro = snc225_crear_libro();
        if ($libro === null) {
            return null;
        }
        $hoja = $libro->getActiveSheet();
        if ($i === 0) {
            $hoja->setTitle($tituloHoja);
        } else {
            $sufijoHoja = ' (' . ($i + 1) . ')';
            $hoja->setTitle(snc225_recortar($tituloHoja, 31 - strlen($sufijoHoja)) . $sufijoHoja);
        }

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
        . '<title>SC-4-08 · Registro de Salarios y Tiempo de Servicio (SNC-225)</title></head>'
        . '<body style="font-family:Arial,sans-serif;padding:2rem;text-align:center;color:#111;">'
        . '<h3>SC-4-08 · Registro de Salarios y Tiempo de Servicio (SNC-225)</h3>'
        . '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><button type="button" onclick="window.close()" style="padding:.5rem 1rem;">Cerrar</button></p>'
        . '</body></html>';
    exit;
}

/**
 * Recorta un texto a un número de caracteres (para nombres de hoja).
 */
function snc225_recortar(string $texto, int $largo): string
{
    return function_exists('mb_substr') ? mb_substr($texto, 0, $largo) : substr($texto, 0, $largo);
}

/**
 * Nombre del trabajador para la cabecera del modelo y el título de la hoja.
 */
function snc225_nombre_corto(array $trabajador): string
{
    $nombre = trim((string)($trabajador['nombres'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string)($trabajador['nombre_completo'] ?? ''));
    }
    if ($nombre === '') {
        $nombre = trim(implode(' ', array_filter([
            (string)($trabajador['primer_apellido'] ?? ''),
            (string)($trabajador['segundo_apellido'] ?? ''),
        ])));
    }
    return $nombre;
}

/**
 * Prefijo del nombre de archivo de las exportaciones:
 * SC-4-08_Ariel_Rodriguez_Matos (sin acentos y con "_" en vez de espacios).
 */
function snc225_nombre_archivo(array $trabajador): string
{
    $nombre = trim((string)($trabajador['nombre_completo'] ?? ''));
    if ($nombre === '') {
        $nombre = trim(implode(' ', array_filter([
            (string)($trabajador['nombres'] ?? ''),
            (string)($trabajador['primer_apellido'] ?? ''),
            (string)($trabajador['segundo_apellido'] ?? ''),
        ])));
    }
    if ($nombre === '') {
        $nombre = 'Trabajador';
    }
    if (function_exists('iconv')) {
        $plano = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($plano !== false && $plano !== '') {
            $nombre = $plano;
        }
    }
    $nombre = trim((string)preg_replace('/[^A-Za-z0-9]+/', '_', $nombre), '_');
    return 'SC-4-08_' . ($nombre !== '' ? $nombre : 'Trabajador');
}

/**
 * Descarga el modelo en Excel (.xlsx).
 */
function snc225_exportar_xlsx(PDO $pdo, int $id, string $corte, array $config, string $obs = '', array $anios = []): void
{
    $res = snc225_construir($pdo, $id, $corte, $config, $obs, $anios);
    if (!$res) {
        snc225_error('No se pudo generar el reporte SC-4-08.');
    }

    $trabajador = $res['datos']['trabajador'];
    $filename = snc225_nombre_archivo($trabajador) . '_' . date('Y-m-d_H-i-s') . '.xlsx';

    if (function_exists('logAction')) {
        logAction(
            'exportar_reporte',
            'snc225',
            'Exportación del modelo SC-4-08 (XLSX)',
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
 * Descarga el modelo en Word.
 *
 * Con PhpWord instalado se genera un .docx real replicando la tabla del
 * modelo (18 columnas, combinados, anchos, bordes y alturas). Si la librería
 * no estuviera disponible se descarga el HTML que Word abre de forma nativa.
 */
function snc225_exportar_word(PDO $pdo, int $id, string $corte, array $config, string $obs = '', array $anios = []): void
{
    $res = snc225_construir($pdo, $id, $corte, $config, $obs, $anios);
    if (!$res) {
        snc225_error('No se pudo generar el reporte SC-4-08.');
    }

    $trabajador = $res['datos']['trabajador'];
    $base = snc225_nombre_archivo($trabajador) . '_' . date('Y-m-d_H-i-s');

    if (function_exists('logAction')) {
        logAction(
            'exportar_reporte',
            'snc225',
            'Exportación del modelo SC-4-08 (Word)',
            ['archivo' => $base . '.docx', 'trabajador_id' => $id],
            null,
            'success',
            null,
            $_SESSION['auth_provider'] ?? 'local'
        );
    }

    if (class_exists('\\PhpOffice\\PhpWord\\PhpWord')) {
        snc225_word_descargar_docx($res['spreadsheet'], $base);
    }

    snc225_word_descargar_doc($res, $base);
}

/**
 * Mapa de los combinados de la hoja para armar la tabla de Word: qué celda
 * inicia el combinado, cuáles se omiten y cuáles continúan la fusión vertical.
 */
function snc225_word_mapa_merges(iterable $merges): array
{
    $mapa = [];
    foreach ($merges as $rango) {
        $partes = explode(':', (string)$rango);
        if (count($partes) !== 2) {
            continue;
        }
        $ini = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($partes[0]);
        $fin = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($partes[1]);
        $c1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ini[0]);
        $r1 = (int)$ini[1];
        $c2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($fin[0]);
        $r2 = (int)$fin[1];
        $span = $c2 - $c1 + 1;
        $vspan = $r2 - $r1 + 1;
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) {
                $clave = $r . ':' . $c;
                if ($r === $r1 && $c === $c1) {
                    $mapa[$clave] = ['tipo' => 'ini', 'span' => $span, 'vspan' => $vspan];
                } elseif ($r > $r1 && $c === $c1) {
                    $mapa[$clave] = ['tipo' => 'cont', 'span' => $span, 'vspan' => 1];
                } else {
                    $mapa[$clave] = ['tipo' => 'skip', 'span' => 1, 'vspan' => 1];
                }
            }
        }
    }
    return $mapa;
}

/**
 * Alineación horizontal de PhpSpreadsheet traducida a la de PhpWord.
 */
function snc225_word_alineacion(?string $horizontal, string $defecto = 'left'): string
{
    switch ($horizontal) {
        case 'center':
            return 'center';
        case 'right':
            return 'right';
        case 'justify':
        case 'centerContinuous':
            return 'both';
        case 'left':
            return 'left';
    }
    return $defecto;
}

/**
 * Alineación vertical de PhpSpreadsheet traducida a la de PhpWord.
 */
function snc225_word_valign(?string $vertical): string
{
    switch ($vertical) {
        case 'center':
            return 'center';
        case 'bottom':
            return 'bottom';
    }
    return 'top';
}

/**
 * Fuente de una celda (o de un tramo de texto enriquecido) para PhpWord.
 */
function snc225_word_fuente($fuente, float $escala = 1.0): array
{
    $estilo = [];
    if (!$fuente) {
        return $estilo;
    }
    if ($fuente->getName()) {
        $estilo['name'] = $fuente->getName();
    }
    if ($fuente->getSize()) {
        $estilo['size'] = round((float)$fuente->getSize() * $escala, 1);
    }
    if ($fuente->getBold()) {
        $estilo['bold'] = true;
    }
    if ($fuente->getItalic()) {
        $estilo['italic'] = true;
    }
    // getUnderline() devuelve 'none' por defecto, que en PHP es verdadero.
    $subrayado = strtolower((string)$fuente->getUnderline());
    if ($subrayado === 'single' || $subrayado === 'singleaccounting') {
        $estilo['underline'] = 'single';
    } elseif ($subrayado === 'double' || $subrayado === 'doubleaccounting') {
        $estilo['underline'] = 'double';
    }
    $color = strtoupper((string)$fuente->getColor()->getRGB());
    if (strlen($color) === 8) {
        $color = substr($color, 2);
    }
    if ($color !== '' && $color !== '000000') {
        $estilo['color'] = $color;
    }
    return $estilo;
}

/**
 * Bordes visibles del contorno de una celda o de un combinado.
 *
 * En un combinado Excel dibuja el borde de CADA celda del contorno, así que
 * un lado está presente si cualquiera de las celdas de ese borde lo tiene.
 * Tomarlo de una sola celda (la primera para arriba/izquierda y la última
 * para derecha/abajo) dejaba líneas sin dibujar en la vista, la impresión, el
 * PDF y el Word.
 */
function snc225_bordes_bloque($hoja, int $r, int $c, int $span, int $vspan): array
{
    // Los objetos Style que devuelve getStyle() se reutilizan al consultar
    // otra celda (el guardado pasa a mostrar el estilo de la última leída),
    // así que cada borde se lee y se convierte en booleano en el acto.
    $visible = function (int $col, int $fila, string $lado) use ($hoja): bool {
        $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $fila;
        $getter = 'get' . ucfirst($lado);
        $tipo = $hoja->getStyle($coord)->getBorders()->{$getter}()->getBorderStyle();
        return $tipo !== null && $tipo !== '' && $tipo !== 'none';
    };

    $lados = ['top' => false, 'left' => false, 'right' => false, 'bottom' => false];
    for ($i = 0; $i < $span; $i++) {
        $lados['top'] = $lados['top'] || $visible($c + $i, $r, 'top');
        $lados['bottom'] = $lados['bottom'] || $visible($c + $i, $r + $vspan - 1, 'bottom');
    }
    for ($i = 0; $i < $vspan; $i++) {
        $lados['left'] = $lados['left'] || $visible($c, $r + $i, 'left');
        $lados['right'] = $lados['right'] || $visible($c + $span - 1, $r + $i, 'right');
    }
    return $lados;
}

/**
 * Bordes de una celda para el .docx, con el contorno del combinado completo.
 */
function snc225_word_bordes($hoja, int $r, int $c, int $span, int $vspan): array
{
    $estilo = [];
    foreach (snc225_bordes_bloque($hoja, $r, $c, $span, $vspan) as $lado => $visible) {
        $estilo['border' . ucfirst($lado) . 'Size'] = $visible ? 4 : 0;
        $estilo['border' . ucfirst($lado) . 'Color'] = '000000';
    }
    return $estilo;
}

/**
 * Escribe el contenido de una celda (texto, números formateados y texto
 * enriquecido con saltos de línea) conservando fuente y alineaciones.
 */
function snc225_word_celda($celdaWord, $celdaXlsx, $st, string $alineacionDefecto = 'left', float $escala = 1.0): void
{
    // En Word se aplica la misma alineación que en la vista: si la celda está
    // en "General", los números van a la derecha y el texto a la izquierda.
    $horiz = strtolower(trim((string)$st->getAlignment()->getHorizontal()));
    if ($horiz === '' || $horiz === 'general') {
        $alineacion = snc225_celda_numerica($celdaXlsx) ? 'right' : $alineacionDefecto;
    } else {
        $alineacion = snc225_word_alineacion($horiz, $alineacionDefecto);
    }

    $parrafo = [
        'alignment' => $alineacion,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
    ];

    $valor = $celdaXlsx->getValue();
    $lineas = [[]];
    if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
        foreach ($valor->getRichTextElements() as $elemento) {
            $texto = (string)$elemento->getText();
            $fuente = method_exists($elemento, 'getFont') ? $elemento->getFont() : null;
            $partes = preg_split('/\r\n|\r|\n/', $texto);
            foreach ($partes as $i => $parte) {
                if ($i > 0) {
                    $lineas[] = [];
                }
                if ($parte !== '') {
                    $lineas[count($lineas) - 1][] = [$parte, $fuente];
                }
            }
        }
    } else {
        $texto = (string)$celdaXlsx->getFormattedValue();
        if ($texto !== '') {
            $lineas[0][] = [$texto, null];
        }
    }

    foreach ($lineas as $segmentos) {
        $run = $celdaWord->addTextRun($parrafo);
        foreach ($segmentos as $segmento) {
            $run->addText($segmento[0], snc225_word_fuente($segmento[1] ?: $st->getFont(), $escala));
        }
    }
}

/**
 * Genera el .docx con PhpWord y lo envía al navegador.
 */
function snc225_word_descargar_docx($spreadsheet, string $base): void
{
    $hojas = $spreadsheet->getAllSheets();
    if (!$hojas) {
        snc225_error('No se pudo generar el reporte SC-4-08.');
    }

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName('Calibri');
    $phpWord->setDefaultFontSize(11);

    // Una sección (página) por hoja del libro: cada bloque de 10 años se
    // imprime en su propia hoja Letter, igual que en el Excel y el PDF.
    $rejillas = [];
    foreach ($hojas as $hoja) {
        $rejillas[] = snc225_word_agregar_hoja($phpWord, $hoja);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $base . '.docx"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $temporal = tempnam(sys_get_temp_dir(), 'snc225_');
    $writer->save($temporal);

    // PhpWord arma la rejilla (tblGrid) con las celdas de la fila más ancha,
    // que al tener combinados no coincide con las columnas del modelo; se
    // reescribe la de cada tabla con sus anchos reales para que Word no
    // deforme el modelo.
    $zip = new \ZipArchive();
    if ($temporal !== false && $zip->open($temporal) === true) {
        $xml = (string)$zip->getFromName('word/document.xml');

        $iGrid = 0;
        $xml = (string)preg_replace_callback(
            '/<w:tblGrid>.*?<\/w:tblGrid>/s',
            static function (array $m) use ($rejillas, &$iGrid): string {
                $info = $rejillas[$iGrid] ?? null;
                $iGrid++;
                if ($info === null) {
                    return $m[0];
                }
                $rejilla = '<w:tblGrid>';
                foreach ($info['anchos'] as $ancho) {
                    $rejilla .= '<w:gridCol w:w="' . $ancho . '" w:type="dxa"/>';
                }
                return $rejilla . '</w:tblGrid>';
            },
            $xml
        );

        $iTabla = 0;
        $xml = (string)preg_replace_callback(
            '/<w:tblW [^>]*\/>/',
            static function (array $m) use ($rejillas, &$iTabla): string {
                $info = $rejillas[$iTabla] ?? null;
                $iTabla++;
                if ($info === null) {
                    return $m[0];
                }
                return '<w:tblW w:w="' . $info['total'] . '" w:type="dxa"/>';
            },
            $xml
        );

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }

    if ($temporal !== false && is_file($temporal)) {
        readfile($temporal);
        @unlink($temporal);
    }
    exit;
}

/**
 * Agrega una hoja del modelo como una sección (página) del documento Word.
 *
 * @return array{anchos: int[], total: int} rejilla de anchos de la tabla
 */
function snc225_word_agregar_hoja(\PhpOffice\PhpWord\PhpWord $phpWord, $hoja): array
{
    // Letter vertical con los márgenes de la plantilla (twips: 1 in = 1440).
    $seccion = $phpWord->addSection([
        'pageSizeW' => 12240,
        'pageSizeH' => 15840,
        'orientation' => 'portrait',
        'marginLeft' => 101,
        'marginRight' => 259,
        'marginTop' => 662,
        'marginBottom' => 677,
    ]);

    // Columnas del modelo: la R va en blanco (sin datos ni bordes) y no forma
    // parte del documento. Se busca la última columna realmente usada.
    $maxFila = $hoja->getHighestRow();
    $ultimaCol = 1;
    for ($c = 18; $c >= 1; $c--) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $usada = false;
        for ($r = 1; $r <= $maxFila; $r++) {
            $coord = $col . $r;
            $valor = $hoja->getCell($coord)->getValue();
            if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $valor = $valor->getPlainText();
            }
            if ($valor !== null && $valor !== '') {
                $usada = true;
                break;
            }
            $bordes = $hoja->getStyle($coord)->getBorders();
            foreach (['getLeft', 'getRight', 'getTop', 'getBottom'] as $lado) {
                $tipo = $bordes->{$lado}()->getBorderStyle();
                if ($tipo !== null && $tipo !== '' && $tipo !== 'none') {
                    $usada = true;
                    break 2;
                }
            }
        }
        if ($usada) {
            $ultimaCol = $c;
            break;
        }
    }
    foreach ($hoja->getMergeCells() as $rango) {
        $partes = explode(':', (string)$rango);
        if (count($partes) === 2) {
            $fin = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($partes[1]);
            $ultimaCol = max($ultimaCol, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($fin[0]));
        }
    }
    $ultimaCol = min($ultimaCol, 18);

    // Anchos y altos naturales del modelo (pt), y el alto que exige el texto.
    $anchosPt = [];
    $anchoNatural = 0.0;
    for ($c = 1; $c <= $ultimaCol; $c++) {
        $ancho = (float)$hoja->getColumnDimensionByColumn($c)->getWidth();
        if ($ancho <= 0) {
            $ancho = 8.43;
        }
        $ancho = round($ancho * 5.25, 2);
        $anchosPt[] = $ancho;
        $anchoNatural += $ancho;
    }

    $altosPt = [];
    $altoNatural = 0.0;
    for ($r = 1; $r <= $maxFila; $r++) {
        $altoFila = (float)$hoja->getRowDimension($r)->getRowHeight();
        if ($altoFila <= 0) {
            $altoFila = 15.0;
        }
        for ($c = 1; $c <= $ultimaCol; $c++) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
            $celda = $hoja->getCell($coord);
            $valor = $celda->getValue();
            $texto = $valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText
                ? $valor->getPlainText()
                : (string)$celda->getFormattedValue();
            $lineas = max(1, count(preg_split('/\r\n|\r|\n/', $texto)));
            $tam = (float)$hoja->getStyle($coord)->getFont()->getSize();
            if ($tam <= 0) {
                $tam = 11.0;
            }
            $altoFila = max($altoFila, $lineas * $tam * 1.25);
        }
        $altosPt[$r] = $altoFila;
        $altoNatural += $altoFila;
    }

    // Escala única para que ancho y alto entren en una hoja Letter: mismo
    // criterio que el zoom a una hoja de la vista imprimible.
    $anchoUtil = (12240 - 101 - 259) / 20;
    $altoUtil = (15840 - 662 - 677) / 20;
    $escala = min(
        1.0,
        $anchoUtil * 0.99 / max($anchoNatural, 0.01),
        $altoUtil * 0.96 / max($altoNatural, 0.01)
    );
    $escala = round($escala, 4);

    $anchos = [];
    $total = 0;
    foreach ($anchosPt as $pt) {
        $anchoTwip = (int)round($pt * $escala * 20);
        $anchos[] = $anchoTwip;
        $total += $anchoTwip;
    }

    $tabla = $seccion->addTable([
        'unit' => 'dxa',
        'width' => $total,
        'layout' => 'fixed',
        'cellMargin' => 0,
        'borderSize' => 0,
    ]);

    $mapa = snc225_word_mapa_merges($hoja->getMergeCells());

    for ($r = 1; $r <= $maxFila; $r++) {
        $fila = $tabla->addRow((int)round($altosPt[$r] * $escala * 20));

        for ($c = 1; $c <= $ultimaCol; $c++) {
            $info = $mapa[$r . ':' . $c] ?? null;
            if ($info && $info['tipo'] === 'skip') {
                continue;
            }
            $span = $info['span'] ?? 1;
            $vspan = $info['vspan'] ?? 1;
            $anchoCelda = 0;
            for ($i = 0; $i < $span; $i++) {
                $anchoCelda += $anchos[$c - 1 + $i];
            }

            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;

            // Los bordes se calculan antes de leer el estilo: getStyle()
            // reutiliza sus objetos y si no quedarían apuntando a otra celda.
            $bordes = snc225_word_bordes($hoja, $r, $c, $span, $vspan);
            $st = $hoja->getStyle($coord);

            $estiloCelda = [
                'gridSpan' => $span,
                'valign' => snc225_word_valign($st->getAlignment()->getVertical()),
            ];
            if ($info && $info['tipo'] === 'cont') {
                $estiloCelda['vMerge'] = 'continue';
            } elseif ($vspan > 1) {
                $estiloCelda['vMerge'] = 'restart';
            }
            $estiloCelda += $bordes;

            $celdaWord = $fila->addCell($anchoCelda, $estiloCelda);
            snc225_word_celda($celdaWord, $hoja->getCell($coord), $st, 'left', $escala);
        }
    }

    return ['anchos' => $anchos, 'total' => $total];
}

/**
 * Respaldo sin PhpWord: documento HTML que Word abre de forma nativa.
 */
function snc225_word_descargar_doc(array $res, string $base): void
{
    $spreadsheet = $res['spreadsheet'];
    $hojas = $spreadsheet->getAllSheets();
    if (!$hojas) {
        snc225_error('No se pudo generar el reporte SC-4-08.');
    }

    $writerHtml = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
    $writerHtml->setDataFormula(false);
    $writerHtml->setGenerateSheetNavigationBlock(false);
    $estilos = $writerHtml->generateStyles(false);

    // Ancho de cada columna en pt, aplicado al <td> (Word no siempre respeta
    // el ancho de los <col>, pero sí el de cada celda). Todas las hojas del
    // modelo comparten la misma geometría.
    $hoja = $hojas[0];
    $anchoTotal = 0.0;
    $anchosTd = '';
    $selectoresTabla = [];
    foreach ($hojas as $i => $_) {
        $selectoresTabla[] = 'table.sheet' . $i;
    }
    $indice = 0;
    foreach (range('A', 'R') as $col) {
        $ancho = (float)$hoja->getColumnDimension($col)->getWidth();
        if ($ancho <= 0) {
            $ancho = 8.43;
        }
        $pt = round($ancho * 5.25, 2);
        $anchoTotal += $pt;
        foreach (array_keys($hojas) as $i) {
            $anchosTd .= 'table.sheet' . $i . ' td.column' . $indice . ' { width: ' . $pt . 'pt; }';
        }
        $indice++;
    }

    // Una tabla por hoja del libro, con salto de página entre ellas.
    $bloques = [];
    foreach ($hojas as $i => $hojaDoc) {
        $writerHoja = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
        $writerHoja->setDataFormula(false);
        $writerHoja->setGenerateSheetNavigationBlock(false);
        $writerHoja->setSheetIndex($i);
        $salto = $i > 0 ? ' style="page-break-before: always;"' : '';
        $bloques[] = '<div' . $salto . '>' . $writerHoja->generateSheetData() . '</div>';
    }
    $tabla = implode('', $bloques);

    $filename = $base . '.doc';

    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:w="urn:schemas-microsoft-com:office:word"'
        . ' xmlns="http://www.w3.org/TR/REC-html40"><head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '<title>SC-4-08 · Registro de Salarios y Tiempo de Servicio (SNC-225)</title>'
        . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View>'
        . '<w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->'
        . '<style>'
        // Letter vertical con los márgenes de la plantilla (0.46 / 0.07 /
        // 0.47 / 0.18 in) y la tabla a su ancho exacto.
        . '@page WordSection1 { size: 21.59cm 27.94cm;'
        . ' margin: 1.17cm 0.46cm 1.19cm 0.18cm; mso-page-orientation: portrait; }'
        . 'div.WordSection1 { page: WordSection1; }'
        . 'body { font-family: Calibri, Arial, sans-serif; }'
        . implode(', ', $selectoresTabla) . ' { table-layout: fixed; width: ' . $anchoTotal . 'pt;'
        . ' border-collapse: collapse; }'
        . $anchosTd
        . '</style>'
        . '<style>' . $estilos . '</style>'
        . '</head><body><div class="WordSection1">'
        . $tabla
        . '</div></body></html>';
    exit;
}

/**
 * Vista previa imprimible (mismo patrón que la vista del Anexo 14).
 *
 * @param string $modo ver       -> solo vista previa con barra de acciones
 *                     imprimir  -> igual, abriendo el diálogo de impresión
 *                     pdf       -> igual, con el aviso de "Guardar como PDF"
 */
/**
 * Última columna con contenido real del modelo (la R del modelo original
 * solo tiene estilos: ni datos ni bordes).
 */
function snc225_ultima_columna($hoja): int
{
    $maxFila = (int)$hoja->getHighestDataRow();
    $ultima = 1;
    for ($c = 18; $c >= 1; $c--) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $usada = false;
        for ($r = 1; $r <= $maxFila; $r++) {
            $coord = $col . $r;
            $valor = $hoja->getCell($coord)->getValue();
            if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $valor = $valor->getPlainText();
            }
            if ($valor !== null && $valor !== '') {
                $usada = true;
                break;
            }
            $bordes = $hoja->getStyle($coord)->getBorders();
            foreach (['getLeft', 'getRight', 'getTop', 'getBottom'] as $lado) {
                $tipo = $bordes->{$lado}()->getBorderStyle();
                if ($tipo !== null && $tipo !== '' && $tipo !== 'none') {
                    $usada = true;
                    break 2;
                }
            }
        }
        if ($usada) {
            $ultima = $c;
            break;
        }
    }
    foreach ($hoja->getMergeCells() as $rango) {
        $partes = explode(':', (string)$rango);
        if (count($partes) === 2) {
            $fin = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($partes[1]);
            $ultima = max($ultima, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($fin[0]));
        }
    }
    return min($ultima, 18);
}

/**
 * ¿La celda contiene un número? Se usa para reproducir la alineación
 * "General" del Excel, que coloca los números a la derecha y el texto a la
 * izquierda cuando la celda no tiene alineación propia.
 */
function snc225_celda_numerica($celda): bool
{
    $valor = $celda->getValue();
    if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
        return false;
    }
    if ($valor === null || $valor === '') {
        return false;
    }
    if (is_int($valor) || is_float($valor)) {
        return true;
    }
    return is_string($valor) && is_numeric(str_replace(' ', '', $valor));
}

/**
 * Alineación horizontal efectiva de una celda: la del Excel si la tiene y, si
 * está en "General" (sin alineación definida), la que aplica Excel: números a
 * la derecha y texto a la izquierda. Así los importes, años, meses y días
 * quedan a la derecha en la vista, la impresión, el PDF y el Word, igual que
 * en el .xlsx.
 */
function snc225_alineacion_horizontal($alin, bool $numerica): string
{
    $horiz = strtolower(trim((string)$alin->getHorizontal()));
    if (in_array($horiz, ['center', 'right', 'justify'], true)) {
        return $horiz;
    }
    if ($horiz === 'left') {
        return 'left';
    }
    return $numerica ? 'right' : 'left';
}

/**
 * Estilo CSS en línea de una celda, leyendo el mismo estilo del Excel.
 */
function snc225_html_celda_css($st, array $bordes, int $c, int $span, array $anchos, bool $numerica = false): string
{

    $anchoCelda = 0.0;
    for ($i = 0; $i < $span; $i++) {
        $anchoCelda += $anchos[$c + $i] ?? 0.0;
    }
    $css = 'width:' . round($anchoCelda, 2) . 'pt;padding:0;margin:0;overflow:hidden;line-height:1.15;';

    $fuente = $st->getFont();
    $nombre = (string)($fuente->getName() ?: 'Calibri');
    $tam = (float)$fuente->getSize();
    if ($tam <= 0) {
        $tam = 11.0;
    }
    $css .= "font-family:'" . $nombre . "',Arial,sans-serif;font-size:" . $tam . 'pt;';
    if ($fuente->getBold()) {
        $css .= 'font-weight:bold;';
    }
    if ($fuente->getItalic()) {
        $css .= 'font-style:italic;';
    }
    $subrayado = $fuente->getUnderline();
    if ($subrayado !== null && $subrayado !== '' && $subrayado !== 'none') {
        $css .= 'text-decoration:underline;';
    }
    $color = (string)$fuente->getColor()->getRGB();
    if ($color !== '' && $color !== '000000' && $color !== 'FFFFFFFF') {
        $css .= 'color:#' . str_replace('FF', '', $color) . ';';
    }

    if ($st->getFill()->getFillType() === 'solid') {
        $fondo = (string)$st->getFill()->getStartColor()->getRGB();
        if ($fondo !== '') {
            $css .= 'background-color:#' . str_replace('FF', '', $fondo) . ';';
        }
    }

    $alin = $st->getAlignment();
    $css .= 'text-align:' . snc225_alineacion_horizontal($alin, $numerica) . ';';
    $vert = (string)$alin->getVertical();
    $css .= 'vertical-align:' . ($vert === 'center' ? 'middle' : ($vert === 'bottom' ? 'bottom' : 'top')) . ';';
    $css .= 'white-space:' . ($alin->getWrapText() ? 'normal' : 'nowrap') . ';word-wrap:break-word;';

    foreach ($bordes as $lado => $visible) {
        if ($visible) {
            $css .= 'border-' . $lado . ':1px solid #000;';
        }
    }

    return $css;
}

/**
 * Tabla HTML del modelo con la geometría exacta del Excel: las mismas 17
 * columnas con sus anchos, las filas con su alto, los combinados, bordes,
 * fuentes y alineaciones. La vista previa, la impresión y el PDF la usan tal
 * cual, así que salen idénticos al .xlsx.
 * Devuelve ['tabla' => string, 'ancho' => pt, 'alto' => pt].
 */
function snc225_html_tabla($hoja): array
{
    $maxFila = (int)$hoja->getHighestDataRow();
    $ultimaCol = snc225_ultima_columna($hoja);

    $anchos = [];
    $anchoTotal = 0.0;
    for ($c = 1; $c <= $ultimaCol; $c++) {
        $ancho = (float)$hoja->getColumnDimensionByColumn($c)->getWidth();
        if ($ancho <= 0) {
            $ancho = 8.43;
        }
        $ancho = round($ancho * 5.25, 2);
        $anchos[$c] = $ancho;
        $anchoTotal += $ancho;
    }

    $altos = [];
    $altoTotal = 0.0;
    for ($r = 1; $r <= $maxFila; $r++) {
        $alto = (float)$hoja->getRowDimension($r)->getRowHeight();
        if ($alto <= 0) {
            $alto = 15.0;
        }
        $altos[$r] = $alto;
        $altoTotal += $alto;
    }

    $html = '<table class="snc-tabla" style="table-layout:fixed;width:' . round($anchoTotal, 2)
        . 'pt;border-collapse:collapse;background:#fff;"><colgroup>';
    foreach ($anchos as $pt) {
        $html .= '<col style="width:' . $pt . 'pt">';
    }
    $html .= '</colgroup>';

    $mapa = snc225_word_mapa_merges($hoja->getMergeCells());

    for ($r = 1; $r <= $maxFila; $r++) {
        $html .= '<tr style="height:' . $altos[$r] . 'pt">';
        for ($c = 1; $c <= $ultimaCol; $c++) {
            $info = $mapa[$r . ':' . $c] ?? null;
            // "skip": celda tapada por un combinado. "cont": continuación
            // vertical de un rowspan, que en HTML ya cubre la celda de arriba.
            if ($info && ($info['tipo'] === 'skip' || $info['tipo'] === 'cont')) {
                continue;
            }
            $span = $info['span'] ?? 1;
            $vspan = $info['vspan'] ?? 1;
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
            // Bordes del bloque primero y estilo propio después: getStyle()
            // reutiliza sus objetos, así que se leen en el acto.
            $bordesCelda = snc225_bordes_bloque($hoja, $r, $c, $span, $vspan);
            $estiloCelda = $hoja->getStyle($coord);
            $celda = $hoja->getCell($coord);
            $esNumerica = snc225_celda_numerica($celda);
            $texto = (string)$celda->getFormattedValue();
            $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
            $texto = str_replace(["\r\n", "\r", "\n"], '<br>', $texto);
            if ($texto === '') {
                $texto = '&nbsp;';
            }

            // Alto fijo del Excel: cuando la fila (o todas las filas que cubre
            // un combinado vertical) tiene alto propio, el contenido se encierra
            // en una caja de ese alto. Así el navegador recorta el texto que no
            // cabe, en vez de estirar la fila como hacía en impresión y PDF.
            $altoCaja = 0.0;
            $altoFijo = true;
            for ($k = 0; $k < $vspan; $k++) {
                // getRowHeight() devuelve -1 cuando la fila tiene alto
                // automático (Excel la ajusta al contenido).
                if ((float)$hoja->getRowDimension($r + $k)->getRowHeight() <= 0) {
                    $altoFijo = false;
                    break;
                }
                $altoCaja += $altos[$r + $k];
            }
            if ($altoFijo && $altoCaja > 0) {
                $alinCelda = $estiloCelda->getAlignment();
                $horizCelda = snc225_alineacion_horizontal($alinCelda, $esNumerica);
                $vertCelda = (string)$alinCelda->getVertical();
                $justifica = $horizCelda === 'center' ? 'center' : ($horizCelda === 'right' ? 'flex-end' : 'flex-start');
                $alineaVert = $vertCelda === 'center' ? 'center' : ($vertCelda === 'bottom' ? 'flex-end' : 'flex-start');
                $texto = '<div style="height:' . round($altoCaja, 2) . 'pt;overflow:hidden;display:flex;align-items:'
                    . $alineaVert . ';justify-content:' . $justifica . ';">' . $texto . '</div>';
            }

            $html .= '<td colspan="' . $span . '" rowspan="' . $vspan . '" style="'
                . snc225_html_celda_css($estiloCelda, $bordesCelda, $c, $span, $anchos, $esNumerica) . '">' . $texto . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    return ['tabla' => $html, 'ancho' => round($anchoTotal, 2), 'alto' => round($altoTotal, 2)];
}

function snc225_ver_reporte(PDO $pdo, int $id, string $corte, string $modo, array $config, string $obs = '', array $anios = []): void
{
    $esPdf = $modo === 'pdf';
    $res = snc225_construir($pdo, $id, $corte, $config, $obs, $anios);
    if (!$res) {
        snc225_error('No se pudo generar el reporte SC-4-08.');
    }

    // Tabla HTML con la misma geometría que el .xlsx: 17 columnas con sus
    // anchos, filas con su alto, combinados, bordes y alineaciones. La vista,
    // la impresión y el PDF salen así idénticos al Excel. Cada hoja del libro
    // (cada bloque de 10 años) es una página del modelo.
    $paginasVista = [];
    $anchoTotal   = 0.0;
    $altoTotal    = 0.0;
    foreach ($res['spreadsheet']->getAllSheets() as $hojaVista) {
        $geometria = snc225_html_tabla($hojaVista);
        if (trim((string)$geometria['tabla']) === '') {
            continue;
        }
        $paginasVista[] = '<div class="snc-pagina">' . $geometria['tabla'] . '</div>';
        $anchoTotal = max($anchoTotal, (float)$geometria['ancho']);
        $altoTotal  = max($altoTotal, (float)$geometria['alto']);
    }
    $tabla = implode('', $paginasVista);
    if (trim((string)$tabla) === '') {
        $tabla = '<div class="snc-pagina"><p style="text-align:center;">No se pudo generar la vista previa.</p></div>';
    }

    // Zoom de impresión: cada página del modelo entra completa en una hoja
    // Letter, igual que el ajuste "una página de ancho" del Excel.
    $anchoPagina  = (8.5 - 0.07 - 0.18) * 96;
    $altoPagina   = (11 - 0.46 - 0.47) * 96;
    $zoomImpreso  = min(
        1.0,
        ($anchoPagina - 6) / max($anchoTotal * 4 / 3, 1),
        ($altoPagina - 12) / max($altoTotal * 4 / 3, 1)
    );
    $zoomImpreso = round(max($zoomImpreso, 0.1), 4);

    $h = function ($texto) {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    };

    $trabajador = $res['datos']['trabajador'];
    $titulo = 'SC-4-08 · ' . ($trabajador['nombre_completo'] ?? '');
    $enlaceExcel = 'snc225.php?snc225_reporte=xlsx&id=' . $id
        . ($corte !== '' ? '&corte=' . urlencode($corte) : '')
        . ($anios ? '&anios=' . urlencode(implode(',', $anios)) : '')
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
        /* Cada hoja del libro (bloque de 10 años) es una página del modelo. */
        .snc-pagina { page-break-after: always; break-after: page; }
        .snc-pagina:last-child { page-break-after: auto; break-after: auto; }
        /* Ancho fijo = suma exacta de las columnas del Excel (698.25 pt = 9.7 in).
           Sin este ancho el navegador ensancha las columnas segun el contenido
           (auto layout) y el modelo deja de coincidir con la plantilla. */
        .snc-wrap table { table-layout: fixed; width: ' . $anchoTotal . 'pt; border-collapse: collapse; margin: 0 auto; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.18); }
        /* El texto se parte dentro de su celda como en el Excel, así se ven
           completas etiquetas como "Días Trab.", y nunca ensancha la columna
           porque la tabla usa table-layout: fixed. */
        .snc-wrap td, .snc-wrap th { overflow: hidden; }
        /* La última fila (pie "Modelo 350.0 T. …") va en una sola línea, como
           en el Excel, donde el texto desborda a las celdas vacías de al lado. */
        .snc-wrap tr:last-child td { white-space: nowrap; overflow: visible; }
        @media print {
            body { background: #fff; }
            #tb-snc, #avisoSnc { display: none !important; }
            .snc-wrap { padding: 0; overflow: visible; }
            /* El zoom definitivo lo calcula sncImprimir() antes de imprimir;
               este valor es solo el respaldo si el script no corre. */
            .snc-wrap table { box-shadow: none; zoom: ' . $zoomImpreso . '; }
        }' ;

    $aviso = $esPdf
        ? '<div id="avisoSnc">Generando el PDF del modelo SC-4-08…</div>'
        : '';

    $nombrePdf = snc225_nombre_archivo($trabajador) . '_' . date('Y-m-d_H-i-s') . '.pdf';

    // PDF real (descarga directa): html2canvas rasteriza la misma tabla que se
    // ve en pantalla y jsPDF la coloca en una página Letter, así el PDF queda
    // idéntico a la vista web y no depende de que el usuario elija "Guardar
    // como PDF" en el diálogo de impresión.
    // Impresión: antes de abrir el diálogo se ajusta el zoom de la tabla al
    // alto real de la página Letter, así el modelo completo sale en una sola
    // hoja (ni una hoja en blanco encima ni las últimas filas en otra página).
    $scriptImprimir = '<script>function sncImprimir(){'
        . 'var ts = document.querySelectorAll(".snc-wrap table");'
        . 'var anchoPag = (8.5 - 0.07 - 0.18) * 96;'
        . 'var altoPag = (11 - 0.46 - 0.47) * 96;'
        . 'var z = 1;'
        . 'ts.forEach(function(t){ t.style.zoom = "1"; var r = t.getBoundingClientRect();'
        . 'if (r.width > 0 && r.height > 0) { z = Math.min(z, (anchoPag - 6) / r.width, (altoPag - 12) / r.height); } });'
        . 'ts.forEach(function(t){ t.style.zoom = z; });'
        . 'window.addEventListener("afterprint", function(){ ts.forEach(function(t){ t.style.zoom = ""; }); }, { once: true });'
        . 'window.print();'
        . '}</script>';

    if ($modo === 'pdf') {
        $script = $scriptImprimir
            . '<script src="../js/jspdf.umd.min.js"></script>'
            . '<script src="../js/html2canvas.min.js"></script>'
            . '<script>window.addEventListener("load", function(){ setTimeout(function(){'
            . 'var els = Array.prototype.slice.call(document.querySelectorAll(".snc-wrap table"));'
            . 'if (!els.length || !window.html2canvas || !window.jspdf) { window.print(); return; }'
            // PDF real: cada página del modelo se rasteriza y se añade como una
            // hoja Letter, con los mismos márgenes de la plantilla
            // (0.46 / 0.07 / 0.47 / 0.18 in). No se abre el diálogo de impresión.
            . 'var pdf = new window.jspdf.jsPDF({ unit: "in", format: "letter", orientation: "portrait", compress: true });'
            . 'var mIzq = 0.07, mDer = 0.18, mSup = 0.46, mInf = 0.47;'
            . 'var anchoUtil = 8.5 - mIzq - mDer, altoUtil = 11 - mSup - mInf;'
            . 'function siguiente(i){'
            . 'if (i >= els.length) { var aviso = document.getElementById("avisoSnc"); if (aviso) { aviso.style.display = "none"; }'
            . 'pdf.save(' . json_encode($nombrePdf) . '); return; }'
            . 'html2canvas(els[i], { scale: 2, useCORS: true, backgroundColor: "#ffffff" }).then(function(canvas){'
            . 'if (i > 0) { pdf.addPage("letter", "portrait"); }'
            . 'var w = anchoUtil, h = w * canvas.height / canvas.width;'
            . 'if (h > altoUtil) { h = altoUtil; w = h * canvas.width / canvas.height; }'
            . 'pdf.addImage(canvas.toDataURL("image/jpeg", 0.95), "JPEG", mIzq + (anchoUtil - w) / 2, mSup, w, h);'
            . 'siguiente(i + 1);'
            . '}).catch(function(){ window.print(); });'
            . '}'
            . 'siguiente(0);'
            . '}, 600); });</script>';
    } elseif ($modo === 'imprimir') {
        $script = $scriptImprimir
            . '<script>window.addEventListener("load", function(){ setTimeout(sncImprimir, 600); });</script>';
    } else {
        $script = $scriptImprimir;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $h($titulo) . '</title>'
        . '<style>' . $css . '</style></head><body' . ($esPdf ? ' class="snc-pdf"' : '') . '>'
        . '<div id="tb-snc">'
        . '<span class="etiqueta">SC-4-08 — Registro de Salarios y Tiempo de Servicio (SNC-225)</span>'
        . '<a id="btnExportarSnc" href="' . $h($enlaceExcel) . '">📊 Exportar Excel</a>'
        . '<button type="button" id="btnImprimirSnc" onclick="sncImprimir()">🖨️ Imprimir</button>'
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
 * Punto de entrada: atiende ?snc225_reporte=ver|imprimir|pdf|xlsx|word.
 */
function snc225_reporte_manejar(PDO $pdo, array $config): void
{
    if (!isset($_GET['snc225_reporte'])) {
        return;
    }

    $formato = strtolower(trim((string)$_GET['snc225_reporte']));
    if (!in_array($formato, ['ver', 'imprimir', 'pdf', 'xlsx', 'word'], true)) {
        $formato = 'ver';
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $corte = isset($_GET['corte']) ? trim((string)$_GET['corte']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte)) {
        $corte = '';
    }

    // Años elegidos en las tarjetas de la tarjeta SC-4-08 (vacío = todos).
    $anios = [];
    if (isset($_GET['anios'])) {
        foreach (explode(',', (string)$_GET['anios']) as $valorAnio) {
            $valorAnio = (int)trim($valorAnio);
            if ($valorAnio >= 1900 && $valorAnio <= 2100) {
                $anios[$valorAnio] = $valorAnio;
            }
        }
    } elseif (isset($_GET['anio'])) {
        $valorAnio = (int)$_GET['anio'];
        if ($valorAnio >= 1900 && $valorAnio <= 2100) {
            $anios[$valorAnio] = $valorAnio;
        }
    }
    $anios = array_values($anios);
    sort($anios);

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
        snc225_exportar_xlsx($pdo, $id, $corte, $config, $obs, $anios);
        return;
    }

    if ($formato === 'word') {
        snc225_exportar_word($pdo, $id, $corte, $config, $obs, $anios);
        return;
    }

    snc225_ver_reporte($pdo, $id, $corte, $formato, $config, $obs, $anios);
    exit;
}
