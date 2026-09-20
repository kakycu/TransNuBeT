<?php
session_start();
require_once 'config/database.php';

// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor inicie sesión.");
}

// 2. OBTENER PARÁMETROS
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$formato = isset($_GET['format']) ? strtolower($_GET['format']) : 'excel';
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$sistema_nombre = "SISFACT PDL Visiones"; // Subtítulo solicitado

// 3. OBTENER DATOS DE LA BASE DE DATOS
try {
    $db = Database::getConnection();
    
    // Obtener planes
    $planes = [];
    $sql = "SELECT * FROM tbl_planes WHERE anio = ? ORDER BY mes_plan ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$anio]);
    
    // Inicializar array completo (1-12)
    for ($i = 1; $i <= 12; $i++) {
        $planes[$i] = [
            'mes' => $i,
            'importe' => 0.00,
            'facturado' => 0.00,
            'observaciones' => ''
        ];
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $planes[$row['mes_plan']]['importe'] = floatval($row['importe']);
        $planes[$row['mes_plan']]['observaciones'] = $row['observaciones'];
    }

    // Obtener facturado real
    $sql_fact = "SELECT MONTH(fecha_emision) as mes, SUM(total_general) as total 
                 FROM tbl_fact 
                 WHERE YEAR(fecha_emision) = ? 
				 AND estado != 'ANULADA' 
                 GROUP BY MONTH(fecha_emision)";
    $stmt_fact = $db->prepare($sql_fact);
    $stmt_fact->execute([$anio]);
    
    while ($row = $stmt_fact->fetch(PDO::FETCH_ASSOC)) {
        if (isset($planes[$row['mes']])) {
            $planes[$row['mes']]['facturado'] = floatval($row['total']);
        }
    }

    // Calcular Totales Generales
    $total_plan = 0;
    $total_facturado = 0;
    foreach ($planes as $p) {
        $total_plan += $p['importe'];
        $total_facturado += $p['facturado'];
    }
    $porcentaje_cumplimiento = $total_plan > 0 ? ($total_facturado / $total_plan) * 100 : 0;

} catch (Exception $e) {
    die("Error de base de datos: " . $e->getMessage());
}

$meses_nombres = [
    1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio',
    7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
];

// ==========================================
// 4. GENERAR ARCHIVO SEGÚN FORMATO
// ==========================================

// --- CASO EXCEL (.xls) - HORIZONTAL ---
if ($formato === 'excel') {
    $filename = "Planes_Ingresos_{$anio}_" . date('Ymd_His') . ".xls";
    
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "\xEF\xBB\xBF"; // BOM
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <!-- Configuración XML para Orientación Horizontal en Excel -->
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Planes <?php echo $anio; ?></x:Name>
                        <x:WorksheetOptions>
                            <x:Print>
                                <x:ValidPrinterInfo/>
                                <x:PaperSizeIndex>1</x:PaperSizeIndex> <!-- 1 = Letter -->
                                <x:Scale>100</x:Scale>
                                <x:HorizontalResolution>600</x:HorizontalResolution>
                                <x:VerticalResolution>600</x:VerticalResolution>
                            </x:Print>
                            <x:Selected/>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            @page {
                mso-page-orientation: landscape;
                size: 27.94cm 21.59cm; /* Carta Landscape */
                margin: 1cm;
            }
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #0078D4; color: white; border: 1px solid #000; padding: 10px; }
            td { border: 1px solid #ccc; padding: 8px; vertical-align: middle; }
            .num { mso-number-format:"Currency"; text-align: right; }
            .perc { mso-number-format:"Percent"; text-align: center; }
            .header-row { background-color: #f2f2f2; font-weight: bold; }
            .text-center { text-align: center; }
            .subtitulo { font-size: 14px; color: #555; font-weight: bold; text-align: center; }
        </style>
    </head>
    <body>
        <table>
            <tr><td colspan="6" style="font-size: 18px; font-weight: bold; text-align: center; background-color: #e1e1e1;">PLANES DE INGRESOS - AÑO <?php echo $anio; ?></td></tr>
            <tr><td colspan="6" class="subtitulo"><?php echo $sistema_nombre; ?></td></tr>
            <tr><td colspan="6">Exportado por: <?php echo $usuario_nombre; ?> | Fecha: <?php echo date('d/m/Y H:i'); ?></td></tr>
            <tr><td colspan="6">&nbsp;</td></tr>
            
            <tr class="header-row">
                <td colspan="2">RESUMEN DEL AÑO</td>
                <td class="num">Plan: $<?php echo number_format($total_plan, 2); ?></td>
                <td class="num">Facturado: $<?php echo number_format($total_facturado, 2); ?></td>
                <td colspan="2" class="perc">Cumplimiento: <?php echo number_format($porcentaje_cumplimiento, 2); ?>%</td>
            </tr>
            <tr><td colspan="6">&nbsp;</td></tr>
            
            <tr>
                <th>Mes</th>
                <th>Importe Plan</th>
                <th>Facturado</th>
                <th>Diferencia</th>
                <th>% Cump.</th>
                <th>Observaciones</th>
            </tr>
            <?php foreach ($planes as $p): 
                $dif = $p['facturado'] - $p['importe'];
                $perc = $p['importe'] > 0 ? $p['facturado']/$p['importe'] : 0;
                $color_text = $dif < 0 ? 'red' : 'black';
            ?>
            <tr>
                <td class="text-center"><?php echo $meses_nombres[$p['mes']]; ?></td>
                <td class="num">$<?php echo number_format($p['importe'], 2); ?></td>
                <td class="num">$<?php echo number_format($p['facturado'], 2); ?></td>
                <td class="num" style="color:<?php echo $color_text; ?>;">$<?php echo number_format($dif, 2); ?></td>
                <td class="perc"><?php echo number_format($perc * 100, 2); ?>%</td>
                <td><?php echo htmlspecialchars($p['observaciones']); ?></td>
            </tr>
            <?php endforeach; ?>
			<tr style="background-color: #e1e1e1; font-weight: bold;">
				<td class="text-center"><strong>TOTALES</strong></td>
				<td class="num"><strong>$<?php echo number_format($total_plan, 2); ?></strong></td>
				<td class="num"><strong>$<?php echo number_format($total_facturado, 2); ?></strong></td>
				<td class="num" style="color:<?php echo ($total_facturado - $total_plan) < 0 ? 'red' : 'black'; ?>;">
					<strong>$<?php echo number_format($total_facturado - $total_plan, 2); ?></strong>
				</td>
				<td class="perc"><strong><?php echo number_format($porcentaje_cumplimiento, 2); ?>%</strong></td>
				<td></td>
			</tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// --- CASO WORD (.doc) - HORIZONTAL ---
elseif ($formato === 'word') {
    $filename = "Reporte_Planes_{$anio}.doc";
    
    header("Content-Type: application/vnd.ms-word");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("content-disposition: attachment;filename=$filename");
    
    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'>";
    echo "<style>
        @page Section1 {
            size: 27.94cm 21.59cm; /* Carta Landscape */
            margin: 2cm 2cm 2cm 2cm;
            mso-page-orientation: landscape;
        }
        div.Section1 { page: Section1; }
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 5px; }
    </style>";
    echo "</head><body><div class='Section1'>";
    
    echo "<h1 style='color:#0078D4; text-align:center; margin-bottom:5px;'>REPORTE DE INGRESOS $anio</h1>";
    echo "<h3 style='color:#555; text-align:center; margin-top:0;'>$sistema_nombre</h3>";
    echo "<p><strong>Generado por:</strong> $usuario_nombre<br><strong>Fecha:</strong> " . date('d/m/Y') . "</p>";
    echo "<hr>";
    
    echo "<p>Total Planificado: <strong>$" . number_format($total_plan, 2) . "</strong><br>";
    echo "Total Facturado: <strong>$" . number_format($total_facturado, 2) . "</strong><br>";
    echo "Cumplimiento: <strong>" . number_format($porcentaje_cumplimiento, 2) . "%</strong></p>";
    
    echo "<table>";
    echo "<tr style='background:#0078D4; color:white;'><th>Mes</th><th>Plan</th><th>Real</th><th>Dif</th><th>%</th><th>Obs</th></tr>";
    
    foreach ($planes as $p) {
        $dif = $p['facturado'] - $p['importe'];
        $perc = $p['importe'] > 0 ? ($p['facturado']/$p['importe'])*100 : 0;
        $color = $dif < 0 ? 'color:red;' : '';
        
        echo "<tr>";
        echo "<td>" . $meses_nombres[$p['mes']] . "</td>";
        echo "<td align='right'>$" . number_format($p['importe'], 2) . "</td>";
        echo "<td align='right'>$" . number_format($p['facturado'], 2) . "</td>";
        echo "<td align='right' style='$color'>$" . number_format($dif, 2) . "</td>";
        echo "<td align='center'>" . number_format($perc * 100, 1) . "%</td>";
        echo "<td>" . htmlspecialchars($p['observaciones']) . "</td>";
        echo "</tr>";
    }
		echo "<tr style='background-color: #e1e1e1; font-weight: bold;'>";
		echo "<td><strong>TOTALES</strong></td>";
		echo "<td align='right'><strong>$" . number_format($total_plan, 2) . "</strong></td>";
		echo "<td align='right'><strong>$" . number_format($total_facturado, 2) . "</strong></td>";
		$dif_total = $total_facturado - $total_plan;
		$color_total = $dif_total < 0 ? 'color:red;' : '';
		echo "<td align='right' style='$color_total'><strong>$" . number_format($dif_total, 2) . "</strong></td>";
		echo "<td align='center'><strong>" . number_format($porcentaje_cumplimiento, 1) . "%</strong></td>";
		echo "<td></td>";
		echo "</tr>";
    echo "</table>";
    echo "</div></body></html>";
    exit;
}

// --- CASO CSV ---
elseif ($formato === 'csv') {
    $filename = "planes_$anio.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
    
    fputcsv($output, [$sistema_nombre]); // Subtítulo
    fputcsv($output, ['Reporte de Planes', $anio]);
    fputcsv($output, ['Mes', 'Importe Plan', 'Facturado', 'Diferencia', '% Cumplimiento', 'Observaciones']);
    
    foreach ($planes as $p) {
        $dif = $p['facturado'] - $p['importe'];
        $perc = $p['importe'] > 0 ? ($p['facturado']/$p['importe'])*100 : 0;
        
        fputcsv($output, [
            $meses_nombres[$p['mes']],
            $p['importe'],
            $p['facturado'],
            $dif,
            number_format($perc * 100, 2) . '%',
            $p['observaciones']
        ]);
    }
$dif_total = $total_facturado - $total_plan;
fputcsv($output, [
    'TOTALES',
    $total_plan,
    $total_facturado,
    $dif_total,
    number_format($porcentaje_cumplimiento, 2) . '%',
    ''
]);
    fclose($output);
    exit;
}

// --- CASO JSON ---
elseif ($formato === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="planes_'.$anio.'.json"');
    
	$data = [
		'sistema' => $sistema_nombre,
		'anio' => $anio,
		'resumen' => [
			'total_plan' => $total_plan,
			'total_facturado' => $total_facturado,
			'diferencia' => $total_facturado - $total_plan,
			'cumplimiento' => $porcentaje_cumplimiento
		],
		'detalle' => $planes,
		'totales' => [
			'importe' => $total_plan,
			'facturado' => $total_facturado,
			'diferencia' => $total_facturado - $total_plan,
			'porcentaje' => $porcentaje_cumplimiento
		]
	];
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- CASO PDF - HORIZONTAL (LANDSCAPE) + CARTA (LETTER) ---
elseif ($formato === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Generando PDF...</title>
        <script src="js/jspdf.umd.min.js"></script>
        <script src="js/jspdf.plugin.autotable.min.js"></script>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; text-align: center; padding-top: 50px; color: #333; background-color: #f9f9f9; }
            .loader { border: 5px solid #f3f3f3; border-top: 5px solid #0078D4; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .btn-volver { margin-top: 20px; padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block;}
            .btn-volver:hover { background-color: #5a6268; }
        </style>
    </head>
    <body>
        <div class="loader"></div>
        <h3><?php echo $sistema_nombre; ?></h3>
        <p>Generando su reporte PDF...</p>
        <p id="mensaje_estado" style="font-size: 0.9em; color: #666;">La descarga comenzará automáticamente.</p>

        <!-- Botón manual por si falla el automático -->
        <a href="planes.php?anio=<?php echo $anio; ?>" class="btn-volver">Volver al Sistema</a>

        <script>
            window.onload = function() {
                try {
                    const { jsPDF } = window.jspdf;
                    
                    // CONFIGURACIÓN: Landscape (l) + Letter
                    // 'l' = landscape (horizontal)
                    // 'mm' = milímetros
                    // 'letter' = tamaño carta (215.9 x 279.4 mm)
                    const doc = new jsPDF('l', 'mm', 'letter');
                    
                    const anio = "<?php echo $anio; ?>";
                    const usuario = "<?php echo $usuario_nombre; ?>";
                    const sistema = "<?php echo $sistema_nombre; ?>";
                    const fecha = "<?php echo date('d/m/Y'); ?>";
                    const totalPlan = "<?php echo number_format($total_plan, 2); ?>";
                    const totalFact = "<?php echo number_format($total_facturado, 2); ?>";
                    
                    // Header
                    doc.setFontSize(18);
                    doc.setTextColor(0, 120, 212); 
                    doc.text(`PLANES DE INGRESOS - AÑO ${anio}`, 14, 20);
                    
                    doc.setFontSize(12);
                    doc.setTextColor(80);
                    doc.text(sistema, 14, 27);

                    doc.setFontSize(10);
                    doc.setTextColor(100);
                    doc.text(`Exportado por: ${usuario} | Fecha: ${fecha}`, 14, 34);
                    
                    // Tabla Resumen
                    doc.autoTable({
                        startY: 40,
                        head: [['RESUMEN FINANCIERO', 'VALORES']],
                        body: [
                            ['Total Planificado', `$ ${totalPlan}`],
                            ['Total Facturado', `$ ${totalFact}`],
                            ['Cumplimiento Global', '<?php echo number_format($porcentaje_cumplimiento, 2); ?>%']
                        ],
                        theme: 'plain',
                        styles: { fontSize: 10 },
                        columnStyles: { 0: { fontStyle: 'bold', width: 80 }, 1: { halign: 'right' } }
                    });

                    // Datos Tabla Principal
                    const dataBody = [
                        <?php foreach ($planes as $p): 
							$dif_total = $total_facturado - $total_plan;
                            $dif = $p['facturado'] - $p['importe'];
                            $perc = $p['importe'] > 0 ? ($p['facturado']/$p['importe'])*100 : 0;
                        ?>
                        [
                            "<?php echo $meses_nombres[$p['mes']]; ?>",
                            "$ <?php echo number_format($p['importe'], 2); ?>",
                            "$ <?php echo number_format($p['facturado'], 2); ?>",
                            "$ <?php echo number_format($dif, 2); ?>",
                            "<?php echo number_format($perc, 1); ?>%",
                            "<?php echo preg_replace( "/\r|\n/", " ", $p['observaciones']); ?>"
                        ],
                        <?php endforeach; ?>
						[
							"TOTALES",
							"$ <?php echo number_format($total_plan, 2); ?>",
							"$ <?php echo number_format($total_facturado, 2); ?>",
							"$ <?php echo number_format($dif_total, 2); ?>",
							"<?php echo number_format($porcentaje_cumplimiento, 1); ?>%"
						]
                    ];

                    // Configuración de Tabla Detallada para Horizontal
                    doc.autoTable({
                        startY: doc.lastAutoTable.finalY + 10,
                        head: [['Mes', 'Plan', 'Real', 'Diferencia', '%', 'Observaciones']],
                        body: dataBody,
                        theme: 'grid',
                        headStyles: { fillColor: [0, 120, 212] },
                        styles: { fontSize: 10 }, // Fuente un poco más grande gracias al espacio horizontal
                        // Ajustamos anchos relativos
                        columnStyles: {
                            0: { cellWidth: 25 }, // Mes
                            1: { halign: 'right', cellWidth: 40 }, // Plan
                            2: { halign: 'right', cellWidth: 40 }, // Real
                            3: { halign: 'right', cellWidth: 40 }, // Diferencia
                            4: { halign: 'center', cellWidth: 20 }, // %
                            5: { cellWidth: 'auto' } // Observaciones (resto del espacio)
                        },
                        didParseCell: function(data) {
                            if (data.section === 'body' && data.column.index === 3) {
                                let val = data.cell.raw.replace('$ ', '').replace(',', '');
                                if (parseFloat(val) < 0) {
                                    data.cell.styles.textColor = [255, 0, 0];
                                }
                            }
							// Resaltar fila de totales
							if (data.row.index === data.table.body.length - 1) {
								data.cell.styles.fontStyle = 'bold';
								data.cell.styles.fillColor = [225, 225, 225];
							}
                        }
                    });

                    // Descargar
                    doc.save(`Reporte_Ingresos_${anio}.pdf`);
                    
                    // === REDIRECCIÓN AUTOMÁTICA ===
                    document.getElementById('mensaje_estado').innerHTML = "Descarga iniciada. Redirigiendo...";
                    setTimeout(function() {
                        window.location.href = "planes.php?anio=<?php echo $anio; ?>";
                    }, 2000); 

                } catch (e) {
                    alert("Error al generar PDF: " + e.message);
                }
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

// --- CASO XML ---
elseif ($formato === 'xml') {
    header('Content-type: text/xml');
    header('Content-Disposition: attachment; filename="planes_'.$anio.'.xml"');
    
    $xml = new SimpleXMLElement('<planes_ingresos/>');
    $xml->addChild('sistema', $sistema_nombre);
    $xml->addChild('anio', $anio);
    $xml->addChild('generado_por', $usuario_nombre);
    
    $resumen = $xml->addChild('resumen');
    $resumen->addChild('total_plan', $total_plan);
    $resumen->addChild('total_facturado', $total_facturado);

	$resumen->addChild('diferencia', $total_facturado - $total_plan);
	$resumen->addChild('porcentaje_cumplimiento', $porcentaje_cumplimiento);

	$totales = $xml->addChild('totales');
	$totales->addChild('importe_total', $total_plan);
	$totales->addChild('facturado_total', $total_facturado);
	$totales->addChild('diferencia', $total_facturado - $total_plan);
	$totales->addChild('porcentaje', $porcentaje_cumplimiento);

    $detalle = $xml->addChild('detalle_mensual');
    foreach ($planes as $p) {
        $mes = $detalle->addChild('mes_item');
        $mes->addChild('nombre', $meses_nombres[$p['mes']]);
        $mes->addChild('importe', $p['importe']);
        $mes->addChild('facturado', $p['facturado']);
        $mes->addChild('observaciones', $p['observaciones']);
    }
    
    echo $xml->asXML();
    exit;
}

else {
    echo "Formato no válido.";
}
?>