<?php
// exportar_factura_xlsx.php

ob_start();
session_start();

require_once 'config/Database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// --------------------------------------------------------------
// Función para convertir número a letras
// --------------------------------------------------------------
function convertirNumeroALetras($numero) {
    $num = number_format($numero, 2, '.', '');
    $partes = explode('.', $num);
    $entero = $partes[0];
    $decimal = $partes[1] ?? '00';
    if (intval($entero) == 0) return "CERO PESOS CON {$decimal}/100";
    $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $diez_veinte = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
    $reversed = strrev($entero);
    $chunks = str_split($reversed, 3);
    $texto = [];
    foreach ($chunks as $index => $chunk) {
        $triada = strrev($chunk);
        $num_triada = intval($triada);
        if ($num_triada == 0) continue;
        $c = intval($triada[0] ?? 0);
        $resto = $num_triada % 100;
        $txt = '';
        if ($c > 0) {
            $txt .= ($c == 1 && $resto == 0) ? 'CIEN' : $centenas[$c];
            if ($resto > 0) $txt .= ' ';
        }
        if ($resto > 0) {
            if ($resto < 10) $txt .= $unidades[$resto];
            elseif ($resto < 20) $txt .= $diez_veinte[$resto - 10];
            else {
                $d = intval($resto / 10); $u = $resto % 10;
                $txt .= $decenas[$d];
                if ($u > 0) $txt .= ' Y ' . $unidades[$u];
            }
        }
        $sufijo = $index == 1 ? 'MIL' : ($index == 2 ? 'MILLÓN' : ($index == 3 ? 'MIL' : ''));
        if ($sufijo == 'MILLÓN' && $num_triada > 1) $sufijo .= 'ES';
        array_unshift($texto, trim($txt . ' ' . $sufijo));
    }
    return trim(implode(' ', $texto)) . " PESOS CON {$decimal}/100";
}

function meses() {
    return ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
}

// --------------------------------------------------------------
// Formulario Inicial con datos reales de la BD
// --------------------------------------------------------------
$mes = $_POST['mes'] ?? $_GET['mes'] ?? null;
$anio = $_POST['anio'] ?? $_GET['anio'] ?? null;

// Si no se ha enviado el formulario, mostramos el formulario con opciones dinámicas
if (!$mes || !$anio) {
    // Conectar a la BD para obtener años y meses disponibles
    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT DISTINCT YEAR(fecha_emision) as anio, MONTH(fecha_emision) as mes FROM tbl_fact ORDER BY anio DESC, mes ASC");
        $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $anios_disponibles = [];
        $meses_por_anio = [];
        foreach ($periodos as $p) {
            $anio_val = $p['anio'];
            $mes_val = $p['mes'];
            if (!in_array($anio_val, $anios_disponibles)) {
                $anios_disponibles[] = $anio_val;
            }
            if (!isset($meses_por_anio[$anio_val])) {
                $meses_por_anio[$anio_val] = [];
            }
            if (!in_array($mes_val, $meses_por_anio[$anio_val])) {
                $meses_por_anio[$anio_val][] = $mes_val;
            }
        }
        // Ordenar meses dentro de cada año
        foreach ($meses_por_anio as $a => $m_arr) {
            sort($m_arr);
            $meses_por_anio[$a] = $m_arr;
        }
    } catch (Exception $e) {
        // Fallback: mostrar todos los años y meses si hay error
        $anios_disponibles = range(date('Y')-2, date('Y'));
        $meses_por_anio = [];
        foreach ($anios_disponibles as $a) {
            $meses_por_anio[$a] = range(1, 12);
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Exportar Facturas por Períodos</title>
		    <!-- SweetAlert2 -->
			<link rel="stylesheet" href="css/sweetalert2.min.css">
			<script src="js/sweetalert211.js"></script>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #111827; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .form-container { background: #1f2937; padding: 35px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; width: 380px; border: 1px solid #374151; }
            h2 { color: #60a5fa; margin-bottom: 25px; font-weight: 600; }
            label { color: #e5e7eb; display: block; text-align: left; margin-top: 12px; font-weight: 500; }
            select, button { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #374151; border-radius: 6px; font-size: 15px; background: #111827; color: #e5e7eb; }
            button { background: #2563eb; border: none; cursor: pointer; font-weight: bold; color: white; transition: 0.2s; }
            button:hover { background: #1d4ed8; transform: translateY(-1px); }
        </style>
        <script>
            function actualizarMeses() {
                var anio = document.getElementById('anio').value;
                var mesSelect = document.getElementById('mes');
                for (var i = 0; i < mesSelect.options.length; i++) {
                    mesSelect.options[i].style.display = 'none';
                }
                var opciones = document.querySelectorAll('.mes-option-' + anio);
                for (var i = 0; i < opciones.length; i++) {
                    opciones[i].style.display = 'block';
                }
                for (var i = 0; i < mesSelect.options.length; i++) {
                    if (mesSelect.options[i].style.display !== 'none') {
                        mesSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        </script>
    </head>
    <body>
        <div class="form-container">
            <h2>📊 Exportar Facturas por Mes</h2>
            <form method="POST" action="">
                <label>📆 Año:</label>
                <select name="anio" id="anio" required onchange="actualizarMeses()">
                    <?php foreach ($anios_disponibles as $a): ?>
                    <option value="<?php echo $a; ?>"><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
                <label>📅 Mes:</label>
                <select name="mes" id="mes" required>
                    <?php foreach ($anios_disponibles as $a): ?>
                        <?php foreach ($meses_por_anio[$a] as $m): ?>
                        <option value="<?php echo $m; ?>" class="mes-option-<?php echo $a; ?>" style="<?php echo $a != $anios_disponibles[0] ? 'display:none' : ''; ?>">
                            <?php echo meses()[$m]; ?>
                        </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit">📥 Exportar a Excel</button>
            </form>
        </div>
        <script>actualizarMeses();</script>
    </body>
    </html>
    <?php
    exit();
}

// --------------------------------------------------------------
// Procesamiento y Generación de Excel
// --------------------------------------------------------------
try {
    $db = Database::getConnection();
    $stmt_config = $db->query("SELECT * FROM configuracion_sistema WHERE id = 1");
    $config_db = $stmt_config->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT f.*, c.nombre as cliente_nombre, c.direccion as cliente_direccion,
                                 c.CodReup as cliente_cod_reup, c.ContratoNo as cliente_contrato,
                                 c.NoCtaDeudor as cliente_cuenta, c.telefono as cliente_telefono,
                                 c.email as cliente_email, t.descripcion as tipo_pago,
                                 u.nombre as usuario_nombre, u.apellidos as usuario_apellidos
                          FROM tbl_fact f 
                          LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                          LEFT JOIN tipos_pago t ON f.tipo_pago_id = t.id
                          LEFT JOIN clasif_usuarios u ON f.usuario_id = u.id
                          WHERE MONTH(f.fecha_emision) = ? AND YEAR(f.fecha_emision) = ?
                          ORDER BY f.id ASC");
    $stmt->execute([$mes, $anio]);
    $facturas = $stmt->fetchAll();

    if (!$facturas) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Exportar Facturas por Períodos</title>
        <script src="js/sweetalert211.js"></script>
        <style>
            body { margin: 0; padding: 0; background: #111827; font-family: 'Segoe UI', Arial, sans-serif; }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                title: '📭 Sin facturas',
                html: 'No hay facturas en el período seleccionado<br><span style="color: #f39c12;font-weight:bold;">(<?php echo meses()[$mes] . ' ' . $anio; ?>)</span>',
                icon: 'info',
                background: '#1f2937',
                color: '#e5e7eb',
                confirmButtonColor: '#f39c12',
                confirmButtonText: '🔙 Volver'
            }).then(() => {
                //window.location.href = 'dashboard.php';
				window.close();
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

    $templateFile = 'assets/PlantillaExcelFacturas.xlsx';
if (!file_exists($templateFile)) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error</title>
        <script src="js/sweetalert211.js"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: '📭 Sin facturas',
                html: 'PLANTILLA NO ENCONTRADA!!!<br><span style="color: #f39c12;font-weight:bold;">(assets/PlantillaExcelFacturas.xlsx)</span>',
                icon: 'info',
                background: '#1f2937',
                color: '#e5e7eb',
                confirmButtonColor: '#f39c12',
                confirmButtonText: '🔙 Volver'
            }).then(() => {
                //window.location.href = 'dashboard.php';
				window.close();
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}


    $spreadsheet = IOFactory::load($templateFile);
    $baseSheet = $spreadsheet->getActiveSheet();

    // --------------------------------------------------------------
    // CREAR HOJA DE RESUMEN AL PRINCIPIO
    // --------------------------------------------------------------
    $summarySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Resumen');
    $spreadsheet->addSheet($summarySheet, 0); // Insertar al principio

    // Título
    $summarySheet->setCellValue('A1', 'RESUMEN FACTURACIÓN');
    $summarySheet->mergeCells('A1:F1');
    $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Fecha actual
    $summarySheet->setCellValue('A3', 'FECHA EXPORTACIÓN:');
    $summarySheet->setCellValue('B3', date('d/m/Y'));
    $summarySheet->getStyle('A3')->getFont()->setBold(true);
	
$summarySheet->setCellValue('A4', 'PERÍODO:');
$summarySheet->setCellValue('B4', meses()[$mes] . ' ' . $anio);
$summarySheet->getStyle('A4')->getFont()->setBold(true);

$usuario_exportador = $_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
$summarySheet->setCellValue('A5', 'EXPORTADO POR:');
$summarySheet->setCellValue('B5', $usuario_exportador);
$summarySheet->getStyle('A5')->getFont()->setBold(true);

    // Encabezados de tabla
    $headers = ['NO.', 'NO. FACTURA', 'FECHA EMISION', 'CLIENTE', 'ESTADO', 'IMPORTE'];
    $col = 'A';
    $rowHeaders = 6;
    foreach ($headers as $header) {
        $summarySheet->setCellValue($col . $rowHeaders, $header);
        $summarySheet->getStyle($col . $rowHeaders)->getFont()->setBold(true);
        $summarySheet->getStyle($col . $rowHeaders)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D3D3D3');
        $summarySheet->getStyle($col . $rowHeaders)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $col++;
    }

    // Llenar datos de facturas
    $rowData = 7;
    $totalImporte = 0;
    $contador = 1;
    foreach ($facturas as $factura) {
        // Determinar estado visual (misma lógica)
        if (!empty($factura['fecha_pago'])) {
            $estado_visual = 'PAGADA';
        } else {
            $estado_visual = $factura['estado'] ?? 'PENDIENTE';
        }
        
        // Obtener total de la factura (subtotal - descuento)
        $stmt_detalles_temp = $db->prepare("SELECT SUM(cantidad * precio_unitario) as total FROM tbl_fact_detalle WHERE factura_id = ?");
        $stmt_detalles_temp->execute([$factura['id']]);
        $subtotal = $stmt_detalles_temp->fetchColumn() ?? 0;
        $descuento = $factura['descuento'] ?? 0;
        $importe = $subtotal - $descuento;
        $totalImporte += $importe;

        $summarySheet->setCellValue('A' . $rowData, $contador++);
        $summarySheet->setCellValue('B' . $rowData, $factura['no_fact']);
        $summarySheet->setCellValue('C' . $rowData, date('d/m/Y', strtotime($factura['fecha_emision'])));
        $summarySheet->setCellValue('D' . $rowData, $factura['cliente_nombre']);
        $summarySheet->setCellValue('E' . $rowData, $estado_visual);
        $summarySheet->setCellValue('F' . $rowData, $importe);
        $summarySheet->getStyle('F' . $rowData)->getNumberFormat()->setFormatCode('$#,##0.00');

        // Aplicar bordes a cada celda
        for ($c = 'A'; $c <= 'F'; $c++) {
            $summarySheet->getStyle($c . $rowData)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        $rowData++;
    }

    // Totales al final
    $totalRow = $rowData + 1;
    $summarySheet->setCellValue('A' . $totalRow, 'TOTAL DE FACTURAS E IMPORTE:');
    $summarySheet->mergeCells('A' . $totalRow . ':D' . $totalRow);
    $summarySheet->getStyle('A' . $totalRow)->getFont()->setBold(true);
    $summarySheet->setCellValue('E' . $totalRow, '=COUNTA(D7:D' . ($rowData-1) . ')');
    $summarySheet->setCellValue('F' . $totalRow, '=SUM(F7:F' . ($rowData-1) . ')');
    $summarySheet->getStyle('E' . $totalRow)->getNumberFormat()->setFormatCode('0');
    $summarySheet->getStyle('F' . $totalRow)->getNumberFormat()->setFormatCode('$#,##0.00');
    $summarySheet->getStyle('E' . $totalRow . ':F' . $totalRow)->getFont()->setBold(true);

    // Ajustar ancho de columnas
    foreach (range('A', 'F') as $col) {
        $summarySheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --------------------------------------------------------------
    // PROCESAR CADA FACTURA (hojas individuales)
    // --------------------------------------------------------------
    foreach ($facturas as $factura) {
        $newSheet = clone $baseSheet;
        $newSheet->setTitle(substr($factura['no_fact'], 0, 31));
        $spreadsheet->addSheet($newSheet);

        // --- 1. NÚMERO DE FACTURA (F2:I2) ---
        $newSheet->mergeCells('F2:I2');
        $newSheet->setCellValue('F2', $factura['no_fact']);
        $newSheet->getStyle('F2')->getFont()->setBold(true);

        // --- 2. FECHA (F3:I3) con "Fecha:" normal y la fecha en negrita ---
        $newSheet->mergeCells('F3:I3');
        $richTextDate = new RichText();
        $normalLabel = $richTextDate->createTextRun('Fecha: ');
        $normalLabel->getFont()->setBold(false);
        $boldDate = $richTextDate->createTextRun(date('d/m/Y', strtotime($factura['fecha_emision'])));
        $boldDate->getFont()->setBold(true);
        $newSheet->setCellValue('F3', $richTextDate);
        $newSheet->getStyle('F3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- 2.5. ESTADO DE LA FACTURA (F4:I4) ---
        $newSheet->mergeCells('F4:I4');
        $estado_raw = $factura['estado'] ?? 'PENDIENTE';
        if (!empty($factura['fecha_pago'])) {
            $estado_visual = 'PAGADA';
        } else {
            $estado_visual = $estado_raw;
        }
        $newSheet->setCellValue('F4', $estado_visual);
        $newSheet->getStyle('F4')->getFont()->setBold(true);
        $newSheet->getStyle('F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- 3. DATOS BANCARIOS (J2..J5) ---
        $richTextJ2 = new RichText();
        $normalLabelJ2 = $richTextJ2->createTextRun('Cuenta: ');
        $normalLabelJ2->getFont()->setBold(false);
        $boldValueJ2 = $richTextJ2->createTextRun($config_db['cuenta_bancaria'] ?? '0657833000454818');
        $boldValueJ2->getFont()->setBold(true);
        $newSheet->setCellValue('J2', $richTextJ2);

        $richTextJ3 = new RichText();
        $normalLabelJ3 = $richTextJ3->createTextRun('Banco: ');
        $normalLabelJ3->getFont()->setBold(false);
        $boldValueJ3 = $richTextJ3->createTextRun($config_db['banco'] ?? 'BANDEC');
        $boldValueJ3->getFont()->setBold(true);
        $newSheet->setCellValue('J3', $richTextJ3);

        $richTextJ4 = new RichText();
        $normalLabelJ4 = $richTextJ4->createTextRun('Sucursal: ');
        $normalLabelJ4->getFont()->setBold(false);
        $boldValueJ4 = $richTextJ4->createTextRun($config_db['sucursal'] ?? '5783');
        $boldValueJ4->getFont()->setBold(true);
        $newSheet->setCellValue('J4', $richTextJ4);

        $richTextJ5 = new RichText();
        $normalLabelJ5 = $richTextJ5->createTextRun('Cód. REEUP: ');
        $normalLabelJ5->getFont()->setBold(false);
        $boldValueJ5 = $richTextJ5->createTextRun($config_db['cod_reeup'] ?? '319.2.5400');
        $boldValueJ5->getFont()->setBold(true);
        $newSheet->setCellValue('J5', $richTextJ5);

        // --- 4. DATOS DEL CLIENTE (C10..C16) ---
        $newSheet->setCellValue('C10', $factura['cliente_nombre']);
        $newSheet->setCellValue('C11', $factura['cliente_cuenta']);
        $newSheet->setCellValue('C12', $factura['cliente_cod_reup']);
        $newSheet->setCellValue('C13', $factura['cliente_direccion']);
        $newSheet->setCellValue('C14', $factura['cliente_contrato']);
        $newSheet->setCellValue('C15', $factura['cliente_telefono']);
        $newSheet->setCellValue('C16', $factura['cliente_email']);

        // --- 5. DATOS DE FACTURACIÓN ---
        $newSheet->setCellValue('J10', $factura['tipo_pago']);
        $usuario_completo = trim($factura['usuario_nombre'] . ' ' . ($factura['usuario_apellidos'] ?? ''));
        $newSheet->setCellValue('J11', $usuario_completo);
        $newSheet->setCellValue('J12', $factura['contabilizado_nombre'] ?? '');
        $fechaCont = !empty($factura['fecha_contabilizacion']) ? date('d/m/Y', strtotime($factura['fecha_contabilizacion'])) : '';
        $newSheet->setCellValue('J13', $fechaCont);
        $newSheet->setCellValue('I14', $config_db['banco'] ?? '-');
        $newSheet->setCellValue('J15', $config_db['sucursal'] ?? '-');
        $newSheet->setCellValue('J16', $config_db['cuenta_bancaria'] ?? '-');
        $newSheet->setCellValue('J17', $config_db['cod_reeup'] ?? '-');

        // --- 6. TABLA DE DETALLES (desde fila 20) ---
        $stmt_detalles = $db->prepare("SELECT d.*, s.descripcion, s.codigo FROM tbl_fact_detalle d LEFT JOIN clasif_serv s ON d.servicio_id = s.id WHERE d.factura_id = ?");
        $stmt_detalles->execute([$factura['id']]);
        $detalles = $stmt_detalles->fetchAll();

        $filaInicioDetalle = 20;
        $numDetalles = count($detalles);
        $filasMaxPlantilla = 13;
        $desplazamiento = 0;

        if ($numDetalles > $filasMaxPlantilla) {
            $desplazamiento = $numDetalles - $filasMaxPlantilla;
            $newSheet->insertNewRowBefore(33, $desplazamiento);
        }

        $total_subtotal = 0;
        foreach ($detalles as $i => $d) {
            $fila = $filaInicioDetalle + $i;
            $importe = $d['cantidad'] * $d['precio_unitario'];
            $total_subtotal += $importe;

            $newSheet->setCellValue('A' . $fila, $i + 1);
            $newSheet->setCellValue('B' . $fila, $d['codigo']);
            $newSheet->setCellValue('C' . $fila, $d['descripcion']);
            $newSheet->setCellValue('H' . $fila, 'UNO');
            $newSheet->setCellValue('I' . $fila, $d['cantidad']);
            $newSheet->setCellValue('J' . $fila, $d['precio_unitario']);
            $newSheet->setCellValue('K' . $fila, $importe);
        }

        // --- 7. TOTALES (C34, G34, K34) con desplazamiento ---
        $filaTotales = 34 + $desplazamiento;
        $descuento = $factura['descuento'] ?? 0;
        $total_final = $total_subtotal - $descuento;

        $newSheet->setCellValue('C' . $filaTotales, $total_subtotal);
        $newSheet->getStyle('C' . $filaTotales)->getNumberFormat()->setFormatCode('$#,##0.00');
        $newSheet->setCellValue('G' . $filaTotales, $descuento);
        $newSheet->getStyle('G' . $filaTotales)->getNumberFormat()->setFormatCode('$#,##0.00');
        $newSheet->setCellValue('K' . $filaTotales, $total_final);
        $newSheet->getStyle('K' . $filaTotales)->getFont()->setBold(true);
        $newSheet->getStyle('K' . $filaTotales)->getNumberFormat()->setFormatCode('$#,##0.00');

        // --- 8. IMPORTE EN LETRAS (fila 36 + desplazamiento) ---
        $filaLetras = 36 + $desplazamiento;
        $newSheet->setCellValue('D' . $filaLetras, convertirNumeroALetras($total_final));

        // --- 9. OBSERVACIONES (fila 37 + desplazamiento) ---
        $filaObservaciones = 37 + $desplazamiento;
        if (!empty($factura['observaciones'])) {
            $newSheet->setCellValue('A' . $filaObservaciones, $factura['observaciones']);
        }

        // --- 10. DATOS DEL FACTURADOR (nombre en A41, CI en A43) con desplazamiento ---
        $filaNombreFacturador = 41 + $desplazamiento;
        $filaCIFacturador = 43 + $desplazamiento;
        $newSheet->setCellValue('A' . $filaNombreFacturador, $config_db['facturador_nombre'] ?? 'Annia Guerra Laureiro');
        $newSheet->setCellValue('A' . $filaCIFacturador, 'CI: ' . ($config_db['facturador_ci'] ?? '71011003838'));
    }

    // Quitar la primera hoja (la plantilla vacía) que se cargó originalmente
    $spreadsheet->removeSheetByIndex(1); // Porque la hoja Resumen está en índice 0

    $filename = "Facturas_" . meses()[$mes] . "_{$anio}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    if (ob_get_length()) ob_end_clean();
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error - EXPORTAR FACTURAS A EXCEL</title>
        <script src="js/sweetalert211.js"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: '❌ Error crítico',
                text: '<?php echo addslashes($e->getMessage()); ?>',
                icon: 'error',
                background: '#1f2937',
                color: '#e5e7eb',
                confirmButtonColor: '#d63031',
                confirmButtonText: '🚪 Cerrar'
            }).then(() => {
                window.history.back();
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>