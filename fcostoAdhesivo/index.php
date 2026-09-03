<?php
/**
 * SISTEMA DE FICHA DE COSTO COMPLETO (Res. 148/2023 MFP)
 * Servicio: Impresión en papel adhesivo A4
 * El salario es un dato fijo por trabajador y la Seguridad Social se aplica por trabajador.
 * 
 * @author Franklin Ramos Lamadrid
 * @version 3.0 - Con persistencia de ocultar filas mediante cookie
 * @copyright TransNuBet 2026
 */

// ============================================
// 1. CONFIGURACIÓN INICIAL
// ============================================
header('Content-Type: text/html; charset=utf-8');
require_once '../nominas/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// ============================================
// 2. FUNCIÓN DE CÁLCULO DEL SALARIO
// ============================================
// NOTA: El SALARIO es un dato de entrada (fijo por trabajador). No se calcula
// como porcentaje de la utilidad neta. La Seguridad Social se aplica POR
// TRABAJADOR: 5% hasta el tope (5000) y 10% sobre el excedente.

// ============================================
// 3. VARIABLES DE ENTRADA
// ============================================
$periodo = isset($_POST['periodo']) ? $_POST['periodo'] : (isset($_GET['periodo']) ? $_GET['periodo'] : 'mensual');
$organismo = isset($_POST['organismo']) ? $_POST['organismo'] : 'OLPP';
$empresa = isset($_POST['empresa']) ? $_POST['empresa'] : 'PDL TransNuBeT';
$provincia = isset($_POST['provincia']) ? $_POST['provincia'] : 'CAMAGUEY';
$nombre_servicio = isset($_POST['nombre_servicio']) ? $_POST['nombre_servicio'] : 'IMPRESION DE STICKERS Y/O ETIQUETAS EN PAPEL ADHESIVO A4';
$codigo_servicio = isset($_POST['codigo_servicio']) ? $_POST['codigo_servicio'] : 'IMP-PAPEL-ADHESIVO';

$nivel_produccion = isset($_POST['nivel_produccion']) ? $_POST['nivel_produccion'] : '1 - Prod. Terminada';
$factor_capacidad = isset($_POST['factor_capacidad']) ? floatval($_POST['factor_capacidad']) : 1.00;
$porcentaje_capacidad = isset($_POST['porcentaje_capacidad']) ? $_POST['porcentaje_capacidad'] : '100% - Máximo Rendimiento';
$categoria = isset($_POST['categoria']) ? $_POST['categoria'] : 'IMPRESION DE DOCUMENTOS Y OTROS';

$periodo_texto = ($periodo == 'diario') ? 'DIARIA' : 'MENSUAL';

// Fechas
$fecha_emision_input = isset($_POST['fecha_emision']) ? $_POST['fecha_emision'] : date('Y-m-d');
$vigencia_valor = isset($_POST['vigencia_valor']) ? intval($_POST['vigencia_valor']) : 5;
$vigencia_unidad = isset($_POST['vigencia_unidad']) ? $_POST['vigencia_unidad'] : 'Años';

$fecha_emision_obj = new DateTime($fecha_emision_input);
$fecha_vigencia_obj = clone $fecha_emision_obj;

if (strtoupper($vigencia_unidad) == 'DIAS') {
    $fecha_vigencia_obj->modify("+$vigencia_valor days");
} elseif (strtoupper($vigencia_unidad) == 'MESES') {
    $fecha_vigencia_obj->modify("+$vigencia_valor months");
} elseif (strtoupper($vigencia_unidad) == 'ANOS') {
    $fecha_vigencia_obj->modify("+$vigencia_valor years");
}
$fecha_emision = $fecha_emision_obj->format('d/m/Y');
$fecha_vigencia = $fecha_vigencia_obj->format('d/m/Y');

// Variables numéricas
$hojas_por_dia_base = isset($_POST['capacidad']) ? intval($_POST['capacidad']) : 1250;
$hojas_por_dia = round($hojas_por_dia_base * $factor_capacidad);
$dias_trabajo = ($periodo == 'diario') ? 1 : (isset($_POST['dias_trabajo']) ? intval($_POST['dias_trabajo']) : 24);
$produccion_terminada = isset($_POST['produccion_terminada']) ? floatval($_POST['produccion_terminada']) : 30000;

// Insumos de impresión (Papel adhesivo transparente A4)
$precio_adhesivo_paquete = isset($_POST['precio_adhesivo']) ? floatval($_POST['precio_adhesivo']) : 8840.00;
$uds_adhesivo_paquete = isset($_POST['uds_adhesivo']) ? intval($_POST['uds_adhesivo']) : 20;
$precio_kit_tinta = isset($_POST['precio_tinta']) ? floatval($_POST['precio_tinta']) : 12000.00;
$duracion_kit_tinta = isset($_POST['duracion_kit_tinta']) ? floatval($_POST['duracion_kit_tinta']) : 12.00;
$gasto_energia_mes = isset($_POST['energia']) ? floatval($_POST['energia']) : 2356.85;
$gasto_agua_mes = isset($_POST['agua']) ? floatval($_POST['agua']) : 0.00;
$gasto_transportacion = isset($_POST['gasto_transportacion']) ? floatval($_POST['gasto_transportacion']) : 20360.00;

// Depreciación y porcentajes
$valor_activo_impresora = isset($_POST['valor_activo']) ? floatval($_POST['valor_activo']) : 294700.00;
$porcentaje_depreciacion_anual = isset($_POST['depreciacion']) ? floatval($_POST['depreciacion']) : 12.48;
$tasa_impuesto_ventas = isset($_POST['imp_ventas']) ? floatval($_POST['imp_ventas']) : 10.00;
$tasa_contribucion_local = isset($_POST['contrib_local']) ? floatval($_POST['contrib_local']) : 0.00;
$porcentaje_utilidad_gobierno = isset($_POST['imp_gobierno']) ? floatval($_POST['imp_gobierno']) : 20.00;
$porcentaje_otros_gastos = isset($_POST['otros_gastos']) ? floatval($_POST['otros_gastos']) : 11.00;
$porcentaje_vacaciones = isset($_POST['vacaciones']) ? floatval($_POST['vacaciones']) : 9.09;
$porcentaje_fuerza_trabajo = isset($_POST['fuerza_trabajo']) ? floatval($_POST['fuerza_trabajo']) : 5.00;
$tope_seguridad_social = isset($_POST['tope_ss']) ? floatval($_POST['tope_ss']) : 5000.00;
$num_trabajadores = isset($_POST['num_trabajadores']) ? intval($_POST['num_trabajadores']) : 3;

// ============================================
// 4. CÁLCULOS
// ============================================
$total_hojas_periodo = $dias_trabajo * $hojas_por_dia;
// Cantidad de producción terminada: si el usuario la introduce, se usa como cantidad total
// de hojas a producir y se deriva el ritmo diario (capacidad) para que todo cuadre.
if ($produccion_terminada > 0) {
    $total_hojas_periodo = ceil($produccion_terminada);
    if ($dias_trabajo > 0) $hojas_por_dia = ceil($total_hojas_periodo / $dias_trabajo);
}
if ($total_hojas_periodo == 0) $total_hojas_periodo = 1;

// Gasto de material: papel adhesivo + tinta + energía eléctrica
$paquetes_adhesivo = ceil($total_hojas_periodo / $uds_adhesivo_paquete);
$gasto_papel_adhesivo = $paquetes_adhesivo * $precio_adhesivo_paquete;

$duracion_kit_meses = ($periodo == 'diario') ? 1 / 24 : 1;
if ($duracion_kit_tinta > 0) {
    $costo_tinta = $precio_kit_tinta * ($duracion_kit_meses / $duracion_kit_tinta);
} else {
    $costo_tinta = 0;
}
$kits_tinta = ($duracion_kit_tinta > 0) ? $duracion_kit_meses / $duracion_kit_tinta : 0;

if($periodo == 'diario') {
    $depreciacion_periodo = ($valor_activo_impresora * ($porcentaje_depreciacion_anual / 100)) / 365;
    $gasto_energia_periodo = $gasto_energia_mes / 24;
    $gasto_agua_periodo = $gasto_agua_mes / 24;
    $gasto_transportacion_periodo = $gasto_transportacion / 24;
} else {
    $depreciacion_periodo = ($valor_activo_impresora * ($porcentaje_depreciacion_anual / 100)) / 12;
    $gasto_energia_periodo = $gasto_energia_mes;
    $gasto_agua_periodo = $gasto_agua_mes;
    $gasto_transportacion_periodo = $gasto_transportacion;
}

$gasto_material_total = $gasto_papel_adhesivo + $costo_tinta + $gasto_energia_periodo + $gasto_agua_periodo;

// SALARIO: dato de entrada fijo. (3 trabajadores x 9100 = 27300)
$salario_por_trabajador = isset($_POST['salario_trabajador']) ? floatval($_POST['salario_trabajador']) : 9100.00;
$salario_total = $salario_por_trabajador * $num_trabajadores;

// Seguridad Social POR TRABAJADOR: 5% hasta tope (5000) y 10% del excedente
if ($salario_por_trabajador <= $tope_seguridad_social) {
    $ss_por_trabajador = $salario_por_trabajador * 0.05;
} else {
    $ss_por_trabajador = $tope_seguridad_social * 0.05
        + ($salario_por_trabajador - $tope_seguridad_social) * 0.10;
}
$seguridad_social_total = $ss_por_trabajador * $num_trabajadores;
$vacaciones_total = $salario_total * ($porcentaje_vacaciones / 100);
$impuesto_fuerza_trabajo = $salario_total * ($porcentaje_fuerza_trabajo / 100);

// IMPORTES MANUALES: si se activa, se usan los totales introducidos a mano
$salario_total_ref = $salario_total;
$seguridad_social_total_ref = $seguridad_social_total;
$vacaciones_total_ref = $vacaciones_total;
$impuesto_fuerza_trabajo_ref = $impuesto_fuerza_trabajo;
$depreciacion_periodo_ref = $depreciacion_periodo;
$manual_totales = isset($_POST['manual_totales']) ? intval($_POST['manual_totales']) : 0;
if ($manual_totales == 1) {
    $salario_total = isset($_POST['salario_total_m']) ? floatval($_POST['salario_total_m']) : $salario_total;
    $seguridad_social_total = isset($_POST['ss_total_m']) ? floatval($_POST['ss_total_m']) : $seguridad_social_total;
    $vacaciones_total = isset($_POST['vacaciones_total_m']) ? floatval($_POST['vacaciones_total_m']) : $vacaciones_total;
    $impuesto_fuerza_trabajo = isset($_POST['fuerza_total_m']) ? floatval($_POST['fuerza_total_m']) : $impuesto_fuerza_trabajo;
    $depreciacion_periodo = isset($_POST['depreciacion_total_m']) ? floatval($_POST['depreciacion_total_m']) : $depreciacion_periodo;
}

$subtotal_gastos_directos = $gasto_material_total + $depreciacion_periodo
    + $salario_total + $seguridad_social_total + $vacaciones_total + $impuesto_fuerza_trabajo + $gasto_transportacion_periodo;
$costo_otros_gastos_total = $subtotal_gastos_directos * ($porcentaje_otros_gastos / 100);
$base_imponible = $subtotal_gastos_directos + $costo_otros_gastos_total;
$impuesto_ventas_total = $base_imponible * ($tasa_impuesto_ventas / 100);
$contribucion_local_total = $base_imponible * ($tasa_contribucion_local / 100);
$subtotal_gastos_con_impuestos = $base_imponible + $impuesto_ventas_total + $contribucion_local_total;
$total_gastos_final = $subtotal_gastos_con_impuestos;

// COSTO FIJO POR HOJA IMPRESA: escala todos los costos para que cada hoja
// cueste exactamente el valor fijado (por defecto 1,275.00) y todo cuadre.
$costo_fijo_hoja = isset($_POST['costo_fijo_hoja']) ? intval($_POST['costo_fijo_hoja']) : 0;
$costo_fijo_valor = isset($_POST['costo_fijo_valor']) ? floatval($_POST['costo_fijo_valor']) : 1275.00;
if ($costo_fijo_hoja == 1 && $costo_fijo_valor > 0) {
    $total_gastos_final = round($costo_fijo_valor * $total_hojas_periodo, 2);
    $factor_impuestos = 1 + $tasa_impuesto_ventas / 100 + $tasa_contribucion_local / 100;
    $factor_otros = 1 + $porcentaje_otros_gastos / 100;
    $nuevo_subtotal_directos = $total_gastos_final / $factor_impuestos / $factor_otros;
    // Gastos REALES del período que NO se reescalan en modo fijo:
    // transportación, energía, agua, salario (y sus derivados) y depreciación.
    $gastos_reales = $gasto_transportacion_periodo + $gasto_energia_periodo + $gasto_agua_periodo
        + $salario_total + $seguridad_social_total + $vacaciones_total + $impuesto_fuerza_trabajo
        + $depreciacion_periodo;
    $base_escalable = $subtotal_gastos_directos - $gastos_reales;
    $escala = ($base_escalable > 0) ? ($nuevo_subtotal_directos - $gastos_reales) / $base_escalable : 1;
    $gasto_papel_adhesivo *= $escala;
    $costo_tinta *= $escala;
    $gasto_material_total = $gasto_papel_adhesivo + $costo_tinta + $gasto_energia_periodo + $gasto_agua_periodo;
    $subtotal_gastos_directos = $nuevo_subtotal_directos;
    $costo_otros_gastos_total = $subtotal_gastos_directos * ($porcentaje_otros_gastos / 100);
    $base_imponible = $subtotal_gastos_directos + $costo_otros_gastos_total;
    $impuesto_ventas_total = $base_imponible * ($tasa_impuesto_ventas / 100);
    $contribucion_local_total = $base_imponible * ($tasa_contribucion_local / 100);
    $subtotal_gastos_con_impuestos = $base_imponible + $impuesto_ventas_total + $contribucion_local_total;
    $total_gastos_final = $subtotal_gastos_con_impuestos;
}

// PRECIOS UNITARIOS PARA DESAGREGACIÓN DE INSUMOS (derivados del importe para cuadrar en modo escalado)
$precio_papel_unit = ($paquetes_adhesivo > 0) ? $gasto_papel_adhesivo / $paquetes_adhesivo : 0;
$precio_tinta_unit = ($kits_tinta > 0) ? $costo_tinta / $kits_tinta : 0;

// PRECIO AUTO-COMPUTADO: costo por hoja + margen de utilidad configurable
$margen_utilidad_config = isset($_POST['margen_utilidad']) ? floatval($_POST['margen_utilidad']) : 40.00;
$costo_por_hoja = $total_gastos_final / $total_hojas_periodo;
$precio_equilibrio = $costo_por_hoja;
if ($costo_fijo_hoja == 1 && $costo_fijo_valor > 0) {
    // Con el costo fijo: el costo por hoja es exactamente el valor fijado y
    // el margen comercial configurado se aplica encima al precio de venta.
    $costo_por_hoja = $costo_fijo_valor;
    $precio_equilibrio = $costo_fijo_valor;
}
$precio_venta_final = $costo_por_hoja * (1 + $margen_utilidad_config / 100);
$ingreso_por_hoja = $precio_venta_final;
$ingresos_totales = $precio_venta_final * $total_hojas_periodo;

$utilidad_antes_gobierno = $ingresos_totales - $subtotal_gastos_con_impuestos;
$utilidad_para_gobierno = $utilidad_antes_gobierno * ($porcentaje_utilidad_gobierno / 100);
$utilidad_neta_total = $utilidad_antes_gobierno - $utilidad_para_gobierno;
$utilidad_por_hoja = ($total_hojas_periodo > 0) ? $utilidad_neta_total / $total_hojas_periodo : 0;

$margen_utilidad = ($ingresos_totales > 0) ? ($utilidad_neta_total / $ingresos_totales) * 100 : 0;

// ============================================
// 5. LECTURA DE LA PREFERENCIA 'OCULTAR CEROS'
// ============================================
$ocultar_ceros = 1;
if (isset($_POST['ocultar_ceros'])) {
    $ocultar_ceros = intval($_POST['ocultar_ceros']);
} elseif (isset($_COOKIE['ocultar_ceros'])) {
    $ocultar_ceros = intval($_COOKIE['ocultar_ceros']);
}

// ============================================
// 6. FUNCIÓN PARA VERIFICAR SI UNA FILA DEBE MOSTRARSE
// ============================================
function mostrarFila($valor, $ocultar_ceros) {
    if ($ocultar_ceros == 1 && (floatval($valor) == 0)) {
        return false;
    }
    return true;
}

// ============================================
// 7. PASAR DATOS A JAVASCRIPT PARA PDF
// ============================================
$pdfData = [
    'periodo_texto' => $periodo_texto,
    'fecha_emision' => $fecha_emision,
    'fecha_vigencia' => $fecha_vigencia,
    'vigencia_mostrar' => $vigencia_valor . ' ' . $vigencia_unidad,
    'empresa' => $empresa,
    'organismo' => $organismo,
    'nombre_servicio' => $nombre_servicio,
    'codigo_servicio' => $codigo_servicio,
    'categoria' => $categoria,
    'nivel_produccion' => $nivel_produccion,
    'porcentaje_capacidad' => $porcentaje_capacidad,
    'factor_capacidad' => $factor_capacidad,
    'hojas_por_dia_base' => $hojas_por_dia_base,
    'hojas_por_dia' => $hojas_por_dia,
    'produccion_terminada' => $total_hojas_periodo,
    'precio_venta_final' => $precio_venta_final,
    'ingreso_por_hoja' => $ingreso_por_hoja,
    'ingresos_totales' => $ingresos_totales,
    'total_hojas_periodo' => $total_hojas_periodo,
    'gasto_papel_adhesivo_total' => $gasto_papel_adhesivo,
    'costo_tinta_total' => $costo_tinta,
    'gasto_energia_periodo' => $gasto_energia_periodo,
    'gasto_agua_periodo' => $gasto_agua_periodo,
    'gasto_transportacion' => $gasto_transportacion_periodo,
    'gasto_material_total' => $gasto_material_total,
    'paquetes_adhesivo' => $paquetes_adhesivo,
    'kits_tinta' => $kits_tinta,
    'precio_papel_unit' => $precio_papel_unit,
    'precio_tinta_unit' => $precio_tinta_unit,
    'uds_adhesivo_paquete' => $uds_adhesivo_paquete,
    'duracion_kit_tinta' => $duracion_kit_tinta,
    'salario_por_trabajador' => $salario_por_trabajador,
    'salario_total' => $salario_total,
    'margen_utilidad_config' => $margen_utilidad_config,
    'seguridad_social_total' => $seguridad_social_total,
    'vacaciones_total' => $vacaciones_total,
    'impuesto_fuerza_trabajo' => $impuesto_fuerza_trabajo,
    'depreciacion_periodo' => $depreciacion_periodo,
    'gastos_asociados' => $seguridad_social_total + $vacaciones_total + $impuesto_fuerza_trabajo + $depreciacion_periodo,
    'subtotal_gastos_directos' => $subtotal_gastos_directos,
    'costo_otros_gastos_total' => $costo_otros_gastos_total,
    'base_imponible' => $base_imponible,
    'impuesto_ventas_total' => $impuesto_ventas_total,
    'contribucion_local_total' => $contribucion_local_total,
    'utilidad_antes_gobierno' => $utilidad_antes_gobierno,
    'utilidad_para_gobierno' => $utilidad_para_gobierno,
    'utilidad_neta_total' => $utilidad_neta_total,
    'total_gastos_final' => $total_gastos_final,
    'porcentaje_vacaciones' => $porcentaje_vacaciones,
    'porcentaje_fuerza_trabajo' => $porcentaje_fuerza_trabajo,
    'tope_seguridad_social' => $tope_seguridad_social,
    'num_trabajadores' => $num_trabajadores,
    'porcentaje_depreciacion_anual' => $porcentaje_depreciacion_anual,
    'tasa_impuesto_ventas' => $tasa_impuesto_ventas,
    'tasa_contribucion_local' => $tasa_contribucion_local,
    'porcentaje_utilidad_gobierno' => $porcentaje_utilidad_gobierno,
    'porcentaje_otros_gastos' => $porcentaje_otros_gastos,
    'costo_por_hoja' => $costo_por_hoja,
    'precio_equilibrio' => $precio_equilibrio,
    'utilidad_por_hoja' => $utilidad_por_hoja,
    'margen_utilidad' => $margen_utilidad,
    'dias_trabajo' => $dias_trabajo,
    'manual_totales' => $manual_totales
];

// ============================================
// 8. FUNCIÓN DE EXPORTACIÓN A EXCEL (COMPLETA)
// ============================================
function exportarFichaCostoExcel($data, $ocultar_ceros = 1) {
    $spreadsheet = new Spreadsheet();
    
    $spreadsheet->getProperties()
        ->setCreator('TransNuBet')
        ->setLastModifiedBy('TransNuBet')
        ->setTitle('Ficha de Costo - Res. 148/2023 MFP')
        ->setSubject('Ficha de Costo Completo')
        ->setDescription('Ficha de costo generada según Resolución 148/2023 MFP')
        ->setKeywords('ficha costo excel resolución 148 MFP')
        ->setCategory('Finanzas');

    function mostrarFilaExcel($valor, $ocultar_ceros) {
        if ($ocultar_ceros == 1 && (floatval($valor) == 0)) {
            return false;
        }
        return true;
    }

    // Estilos
    $styleTitle = [
        'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];
    $styleSubtitle = [
        'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];
    $styleHeader = [
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']]
    ];
    $styleConcepto = [
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $styleIndent = [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $styleIndent2 = [
        'font' => ['size' => 8, 'color' => ['rgb' => '0078D4'], 'name' => 'Arial', 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 2],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $styleNumber = [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleTotal = [
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleGreen = [
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '155724'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleGreenTitle = [
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '155724'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']]
    ];
    $styleBlue = [
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '004085'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleBlueTitle = [
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '004085'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']]
    ];
    $styleYellow = [
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '856404'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];

    // ================= HOJA 1: PARÁMETROS =================
    $hojaParams = $spreadsheet->getActiveSheet();
    $hojaParams->setTitle('Parámetros');
    
    $hojaParams->setCellValue('A1', 'PARÁMETROS DE ENTRADA Y CÁLCULOS PRELIMINARES');
    $hojaParams->mergeCells('A1:D1');
    $hojaParams->getStyle('A1:D1')->applyFromArray($styleTitle);
    $hojaParams->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1C2B3D');
    $hojaParams->getStyle('A1:D1')->getFont()->getColor()->setRGB('FFFFFF');
    
    $fila = 3;
    $hojaParams->setCellValue('A' . $fila, 'PARÁMETROS GENERALES');
    $hojaParams->mergeCells('A' . $fila . ':D' . $fila);
    $hojaParams->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleHeader);
    $fila++;
    
    $params = [
        ['Código', 'Concepto', 'Valor', 'Unidad/Descripción'],
        ['P1', 'Período', $data['periodo_texto'], 'DIARIO o MENSUAL'],
        ['P2', 'Fecha de Emisión', $data['fecha_emision'], 'dd/mm/aaaa'],
        ['P3', 'Vigencia (número)', $data['vigencia_valor'] ?? 5, 'Valor numérico'],
        ['P4', 'Vigencia (unidad)', $data['vigencia_unidad'] ?? 'years', 'días/meses/años'],
        ['P5', 'Producto o Servicio', $data['nombre_servicio'], 'Nombre del servicio'],
        ['P6', 'Código', $data['codigo_servicio'], 'Código de identificación'],
        ['P7', 'Hojas de Papel Adhesivo (por día)', $data['hojas_por_dia'], 'Hojas impresas diarias'],
        ['P8', 'Precio Unitario por Hoja ($) (auto)', $data['precio_venta_final'], 'Calculado por la ficha'],
        ['P9', 'Días Laborados', $data['dias_trabajo'], 'Días en el período'],
        ['P10', 'Total Hojas del Período (Producción Terminada)', $data['produccion_terminada'] ?? $data['total_hojas_periodo'], 'Introducida por el usuario o Hojas/día x Días'],
        ['P11', 'Precio Papel Adhesivo ($/paquete)', $data['precio_adhesivo_paquete'] ?? 438.75, 'Precio en MN'],
        ['P12', 'Hojas por Paquete', $data['uds_adhesivo_paquete'] ?? 20, 'Hojas A4 por paquete'],
        ['P13', 'Precio Kit de Tinta ($)', $data['precio_kit_tinta'] ?? 12000, 'Precio en MN'],
        ['P14', 'Duración del Kit de Tinta (meses)', $data['duracion_kit_tinta'] ?? 12, 'Meses que dura un kit'],
        ['P15', 'Gasto Energía Eléctrica ($)', $data['gasto_energia_periodo'] ?? 0, 'Energía en el período'],
        ['P16', 'Valor Activo Impresora ($)', $data['valor_activo_impresora'] ?? 294700, 'Valor en MN'],
        ['P17', '% Depreciación Anual', $data['porcentaje_depreciacion_anual'], 'Porcentaje anual'],
        ['P18', '% ISV (Impuesto Ventas)', $data['tasa_impuesto_ventas'], 'Porcentaje'],
        ['P19', '% Contribución Local', $data['tasa_contribucion_local'], 'Porcentaje'],
        ['P20', '% Utilidad Gobierno', $data['porcentaje_utilidad_gobierno'], 'Porcentaje'],
        ['P21', '% Otros Gastos', $data['porcentaje_otros_gastos'], 'Porcentaje'],
        ['P22', '% Vacaciones', $data['porcentaje_vacaciones'], 'Porcentaje'],
        ['P23', '% Fuerza de Trabajo', $data['porcentaje_fuerza_trabajo'], 'Porcentaje'],
        ['P24', 'Tope Seguridad Social', $data['tope_seguridad_social'] ?? 5000, 'Tope individual para SS'],
        ['P25', 'Margen de Utilidad (%)', $data['margen_utilidad_config'] ?? 40, 'Porcentaje de ganancia'],
        ['P26', 'Ocultar filas en cero', $ocultar_ceros == 1 ? 'Sí' : 'No', 'Opción de visualización'],
    ];
    
    foreach ($params as $param) {
        $hojaParams->setCellValue('A' . $fila, $param[0]);
        $hojaParams->setCellValue('B' . $fila, $param[1]);
        $hojaParams->setCellValue('C' . $fila, $param[2]);
        $hojaParams->setCellValue('D' . $fila, $param[3]);
        $hojaParams->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'font' => ['size' => 9, 'name' => 'Arial']
        ]);
        $fila++;
    }
    
    $fila += 2;
    $hojaParams->setCellValue('A' . $fila, 'CÁLCULOS PRELIMINARES');
    $hojaParams->mergeCells('A' . $fila . ':D' . $fila);
    $hojaParams->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleHeader);
    $fila++;
    
    $hojaParams->setCellValue('A' . $fila, 'Código');
    $hojaParams->setCellValue('B' . $fila, 'Concepto');
    $hojaParams->setCellValue('C' . $fila, 'Valor');
    $hojaParams->setCellValue('D' . $fila, 'Descripción');
    $hojaParams->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleHeader);
    $fila++;
    
    $calculos = [
        ['C1', 'Total Hojas del Período (Producción Terminada)', $data['produccion_terminada'] ?? $data['total_hojas_periodo'], 'Introducida por el usuario o Hojas/día x Días Laborados'],
        ['C2', 'Papel Adhesivo Total Período', $data['gasto_papel_adhesivo_total'], 'Hojas/20 x Precio paquete'],
        ['C3', 'Tinta Total Período', $data['costo_tinta_total'], 'Precio kit x (meses período / duración kit)'],
        ['C4', 'Depreciación Período', $data['depreciacion_periodo'], '(Valor Activo Impresora x %) / 12 (Mensual) o /365 (Diario)'],
        ['C5', 'Ingreso por Hoja', $data['ingreso_por_hoja'], 'Precio Unitario auto-computado'],
        ['C6', 'Ingresos Totales', $data['ingresos_totales'], 'Ingreso por Hoja x Total Hojas'],
    ];
    
    foreach ($calculos as $calc) {
        $hojaParams->setCellValue('A' . $fila, $calc[0]);
        $hojaParams->setCellValue('B' . $fila, $calc[1]);
        $hojaParams->setCellValue('C' . $fila, $calc[2]);
        $hojaParams->setCellValue('D' . $fila, $calc[3]);
        $hojaParams->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'font' => ['size' => 9, 'name' => 'Arial']
        ]);
        if (strpos($calc[0], 'C6') !== false) {
            $hojaParams->getStyle('C' . $fila)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '004085']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']]
            ]);
        }
        $fila++;
    }
    
    $fila += 2;
    $hojaParams->setCellValue('A' . $fila, 'RESUMEN DE SALARIOS Y APORTES');
    $hojaParams->mergeCells('A' . $fila . ':D' . $fila);
    $hojaParams->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleHeader);
    $fila++;
    
    $iterData = [
        ['Concepto', 'Valor', 'Descripción'],
        ['Salario por Trabajador', $data['salario_por_trabajador'] ?? 9100, 'Dato de entrada fijo'],
        ['Número de Trabajadores', $data['num_trabajadores'] ?? 3, 'Cantidad de salarios'],
        ['Salario Total', $data['salario_total'], 'Salario x Trabajadores'],
        ['Seguridad Social', $data['seguridad_social_total'], 'Por trabajador: 5% hasta $' . number_format($data['tope_seguridad_social'] ?? 5000, 0) . ' + 10% del excedente'],
        ['Vacaciones', $data['vacaciones_total'], 'Salario x ' . $data['porcentaje_vacaciones'] . '%'],
        ['Fuerza de Trabajo', $data['impuesto_fuerza_trabajo'], 'Salario x ' . $data['porcentaje_fuerza_trabajo'] . '%'],
        ['Costo Directo', $data['subtotal_gastos_directos'], 'Suma de Material + Depreciación + Salario + SS + Vac + Fza'],
        ['Otros Gastos', $data['costo_otros_gastos_total'], 'Costo Directo x ' . $data['porcentaje_otros_gastos'] . '%'],
        ['Base Imponible', $data['base_imponible'], 'Costo Directo + Otros Gastos'],
        ['ISV (Impuesto sobre Ventas)', $data['impuesto_ventas_total'], 'Base Imponible x ' . $data['tasa_impuesto_ventas'] . '%'],
        ['Contribución Local', $data['contribucion_local_total'], 'Base Imponible x ' . $data['tasa_contribucion_local'] . '%'],
        ['Utilidad antes Gobierno', $data['utilidad_antes_gobierno'], 'Ingresos Totales - (Base Imponible + ImpVentas + ContribLocal)'],
        ['Impuesto Gubernamental', $data['utilidad_para_gobierno'], 'Utilidad Antes Gob x ' . $data['porcentaje_utilidad_gobierno'] . '%'],
        ['UTILIDAD NETA', $data['utilidad_neta_total'], 'Utilidad Antes Gob - Impuesto Gubernamental'],
        ['PRECIO POR HOJA (auto)', $data['precio_venta_final'], 'Costo por Hoja + ' . ($data['margen_utilidad_config'] ?? 40) . '% de margen'],
    ];
    
    foreach ($iterData as $dataRow) {
        $hojaParams->setCellValue('A' . $fila, $dataRow[0]);
        $hojaParams->setCellValue('B' . $fila, $dataRow[1]);
        $hojaParams->setCellValue('C' . $fila, $dataRow[2]);
        $hojaParams->getStyle('A' . $fila . ':C' . $fila)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'font' => ['size' => 9, 'name' => 'Arial']
        ]);
        if (strpos($dataRow[0], 'UTILIDAD NETA') !== false) {
            $hojaParams->getStyle('B' . $fila)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '155724']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']]
            ]);
        }
        if (strpos($dataRow[0], 'SALARIO (10% Utilidad)') !== false) {
            $hojaParams->getStyle('B' . $fila)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '0078D4']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']]
            ]);
        }
        $fila++;
    }
    
    $hojaParams->getColumnDimension('A')->setWidth(12);
    $hojaParams->getColumnDimension('B')->setWidth(35);
    $hojaParams->getColumnDimension('C')->setWidth(20);
    $hojaParams->getColumnDimension('D')->setWidth(40);

    // ================= HOJA 2: FICHA DE COSTO =================
    $hojaFicha = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ficha de Costo');
    $spreadsheet->addSheet($hojaFicha, 1);
    $hojaFicha = $spreadsheet->getSheet(1);
    $hojaFicha->setTitle('Ficha de Costo');
    
    $fila = 1;
    $hojaFicha->setCellValue('A' . $fila, 'MINISTERIO DE FINANZAS Y PRECIOS');
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleTitle);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'FICHA DE COSTOS Y GASTOS DE PRODUCTOS Y SERVICIOS - EVALUACIÓN DE PRECIOS Y TARIFAS');
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleSubtitle);
    $fila++;
    
    $unidad_texto = isset($data['vigencia_unidad']) ? $data['vigencia_unidad'] : 'AÑOS';
    $vigencia_mostrar = $data['vigencia_valor'] . ' ' . $unidad_texto;
    $hojaFicha->setCellValue('A' . $fila, 'Resolución: 148/2023 MFP   |   Período: ' . $data['periodo_texto'] . '   |   Emisión: ' . $data['fecha_emision'] . '   |   Vigencia: ' . $data['fecha_vigencia'] . ' (' . $vigencia_mostrar . ')');
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $fila += 2;
    
    $hojaFicha->setCellValue('A' . $fila, 'Producto o Servicio: ' . $data['nombre_servicio'] . '   Código: ' . $data['codigo_servicio']);
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial']
    ]);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'UM: Hoja A4   Nivel Producción: ' . $data['nivel_produccion'] . '   % Capacidad: ' . $data['porcentaje_capacidad'] . '   Categoría: ' . $data['categoria']);
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial']
    ]);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'Cantidad de Producción Terminada: ' . number_format($data['produccion_terminada'] ?? $data['total_hojas_periodo'], 0) . ' hojas');
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1F4E78'], 'name' => 'Arial']
    ]);
    $fila += 2;
    
    $hojaFicha->setCellValue('A' . $fila, 'CONCEPTOS');
    $hojaFicha->setCellValue('B' . $fila, 'Fila');
    $hojaFicha->setCellValue('C' . $fila, 'Costo Base');
    $hojaFicha->setCellValue('D' . $fila, 'Costo Nuevo');
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleHeader);
    $fila++;
    
    // INGRESOS - siempre visibles
    $hojaFicha->setCellValue('A' . $fila, 'INGRESOS POR VENTAS');
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '155724'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']]
    ]);
    $hojaFicha->setCellValue('B' . $fila, '-');
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->setCellValue('C' . $fila, $data['ingresos_totales']);
    $hojaFicha->setCellValue('D' . $fila, $data['ingresos_totales']);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleGreen);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'Ingreso por hoja');
    $hojaFicha->setCellValue('B' . $fila, 'I.1');
    $hojaFicha->setCellValue('C' . $fila, $data['ingreso_por_hoja']);
    $hojaFicha->setCellValue('D' . $fila, $data['ingreso_por_hoja']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'Ingreso total');
    $hojaFicha->setCellValue('B' . $fila, 'I.2');
    $hojaFicha->setCellValue('C' . $fila, $data['ingresos_totales']);
    $hojaFicha->setCellValue('D' . $fila, $data['ingresos_totales']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    // GASTOS DIRECTOS
    $hojaFicha->setCellValue('A' . $fila, 'GASTOS DIRECTOS');
    $hojaFicha->mergeCells('A' . $fila . ':A' . $fila);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->setCellValue('B' . $fila, '-');
    $hojaFicha->setCellValue('C' . $fila, '-');
    $hojaFicha->setCellValue('D' . $fila, '-');
    $hojaFicha->getStyle('B' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $fila++;
    
    // FILA 1: GASTO MATERIAL - siempre visible
    $gastoMaterial = $data['gasto_material_total'];
    $hojaFicha->setCellValue('A' . $fila, 'Gasto Material');
    $hojaFicha->setCellValue('B' . $fila, '1');
    $hojaFicha->setCellValue('C' . $fila, $gastoMaterial);
    $hojaFicha->setCellValue('D' . $fila, $gastoMaterial);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    $hojaFicha->setCellValue('A' . $fila, 'De ello: Insumos (Materias primas)');
    $hojaFicha->setCellValue('B' . $fila, '1.1');
    $hojaFicha->setCellValue('C' . $fila, $gastoMaterial);
    $hojaFicha->setCellValue('D' . $fila, $gastoMaterial);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    $hojaFicha->setCellValue('A' . $fila, 'De ello, Papel adhesivo y tinta');
    $hojaFicha->setCellValue('B' . $fila, '1.2');
    $hojaFicha->setCellValue('C' . $fila, $gastoMaterial);
    $hojaFicha->setCellValue('D' . $fila, $gastoMaterial);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    // FILA 1.3 Energía - ocultable
    if (mostrarFilaExcel($data['gasto_energia_periodo'] ?? 0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Energía');
        $hojaFicha->setCellValue('B' . $fila, '1.3');
        $hojaFicha->setCellValue('C' . $fila, $data['gasto_energia_periodo'] ?? 0);
        $hojaFicha->setCellValue('D' . $fila, $data['gasto_energia_periodo'] ?? 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    // FILA 1.4 Agua - ocultable
    if (mostrarFilaExcel($data['gasto_agua_periodo'] ?? 0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Agua');
        $hojaFicha->setCellValue('B' . $fila, '1.4');
        $hojaFicha->setCellValue('C' . $fila, $data['gasto_agua_periodo'] ?? 0);
        $hojaFicha->setCellValue('D' . $fila, $data['gasto_agua_periodo'] ?? 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    
    // FILA 2: SALARIO - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'Salario Directo o retribución');
    $hojaFicha->setCellValue('B' . $fila, '2');
    $hojaFicha->setCellValue('C' . $fila, $data['salario_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['salario_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'De ello, salarios');
    $hojaFicha->setCellValue('B' . $fila, '2.1');
    $hojaFicha->setCellValue('C' . $fila, $data['salario_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['salario_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'Salario fijo por trabajador (' . number_format($data['salario_por_trabajador'] ?? 9100, 2) . ' x ' . ($data['num_trabajadores'] ?? 3) . ')');
    $hojaFicha->setCellValue('B' . $fila, '-');
    $hojaFicha->setCellValue('C' . $fila, '-');
    $hojaFicha->setCellValue('D' . $fila, '-');
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent2);
    $hojaFicha->getStyle('B' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 8, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $fila++;
    
    // FILA 3: OTROS GASTOS DIRECTOS (TRANSPORTACIÓN) - ocultable
    $transporte = floatval($data['gasto_transportacion'] ?? 0);
    if (mostrarFilaExcel($transporte, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Otros Gastos Directos (Transportación)');
        $hojaFicha->setCellValue('B' . $fila, '3');
        $hojaFicha->setCellValue('C' . $fila, $transporte);
        $hojaFicha->setCellValue('D' . $fila, $transporte);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
        $hojaFicha->setCellValue('A' . $fila, 'Transportación del período');
        $hojaFicha->setCellValue('B' . $fila, '3.1');
        $hojaFicha->setCellValue('C' . $fila, $transporte);
        $hojaFicha->setCellValue('D' . $fila, $transporte);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    
    // FILA 4: GASTOS ASOCIADOS - siempre visible
    $gastosAsociados = $data['seguridad_social_total'] + $data['vacaciones_total'] + 
                       $data['impuesto_fuerza_trabajo'] + $data['depreciacion_periodo'];
    $hojaFicha->setCellValue('A' . $fila, 'Gastos asociados a la producción (Depreciación)');
    $hojaFicha->setCellValue('B' . $fila, '4');
    $hojaFicha->setCellValue('C' . $fila, $gastosAsociados);
    $hojaFicha->setCellValue('D' . $fila, $gastosAsociados);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    $hojaFicha->setCellValue('A' . $fila, 'De ello, Seguridad Social');
    $hojaFicha->setCellValue('B' . $fila, '4.1');
    $hojaFicha->setCellValue('C' . $fila, $data['seguridad_social_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['seguridad_social_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, '5% hasta $5,000 por trabajador + 10% del excedente');
    $hojaFicha->setCellValue('B' . $fila, '-');
    $hojaFicha->setCellValue('C' . $fila, '-');
    $hojaFicha->setCellValue('D' . $fila, '-');
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent2);
    $hojaFicha->getStyle('B' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 8, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $fila++;
    
    $hojaFicha->setCellValue('A' . $fila, 'De ello, Vacaciones (' . number_format($data['porcentaje_vacaciones'], 2) . '%)');
    $hojaFicha->setCellValue('B' . $fila, '4.2');
    $hojaFicha->setCellValue('C' . $fila, $data['vacaciones_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['vacaciones_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    $hojaFicha->setCellValue('A' . $fila, 'De ello, Fuerza de Trabajo (' . number_format($data['porcentaje_fuerza_trabajo'], 2) . '%)');
    $hojaFicha->setCellValue('B' . $fila, '4.3');
    $hojaFicha->setCellValue('C' . $fila, $data['impuesto_fuerza_trabajo']);
    $hojaFicha->setCellValue('D' . $fila, $data['impuesto_fuerza_trabajo']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    $hojaFicha->setCellValue('A' . $fila, 'De ello, Depreciación (' . $data['porcentaje_depreciacion_anual'] . '% anual)');
    $hojaFicha->setCellValue('B' . $fila, '4.4');
    $hojaFicha->setCellValue('C' . $fila, $data['depreciacion_periodo']);
    $hojaFicha->setCellValue('D' . $fila, $data['depreciacion_periodo']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    // FILA 5: COSTO TOTAL - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'COSTO TOTAL (1+2+3+4)');
    $hojaFicha->setCellValue('B' . $fila, '5');
    $hojaFicha->setCellValue('C' . $fila, $data['subtotal_gastos_directos']);
    $hojaFicha->setCellValue('D' . $fila, $data['subtotal_gastos_directos']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleTotal);
    $fila++;
    
    // FILAS 6-10 - ocultables (todas valor 0)
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Gastos Generales y de Administración');
        $hojaFicha->setCellValue('B' . $fila, '6');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'De ello, salarios');
        $hojaFicha->setCellValue('B' . $fila, '6.1');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Gastos de Distribución y Venta');
        $hojaFicha->setCellValue('B' . $fila, '7');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'De ello, salarios');
        $hojaFicha->setCellValue('B' . $fila, '7.1');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Gastos Financieros');
        $hojaFicha->setCellValue('B' . $fila, '8');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Gastos Financiamiento OSDE');
        $hojaFicha->setCellValue('B' . $fila, '9');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    if (mostrarFilaExcel(0, $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Gastos Tributarios (Seg. Social e Impuestos)');
        $hojaFicha->setCellValue('B' . $fila, '10');
        $hojaFicha->setCellValue('C' . $fila, 0);
        $hojaFicha->setCellValue('D' . $fila, 0);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    
    // FILA 11: TOTAL GASTOS - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'TOTAL DE GASTOS (6 al 10)');
    $hojaFicha->setCellValue('B' . $fila, '11');
    $hojaFicha->setCellValue('C' . $fila, 0);
    $hojaFicha->setCellValue('D' . $fila, 0);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleTotal);
    $fila++;
    
    // FILA 12: TOTAL COSTOS Y GASTOS - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'TOTAL DE COSTOS Y GASTOS (5+11)');
    $hojaFicha->setCellValue('B' . $fila, '12');
    $hojaFicha->setCellValue('C' . $fila, $data['subtotal_gastos_directos']);
    $hojaFicha->setCellValue('D' . $fila, $data['subtotal_gastos_directos']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleTotal);
    $fila++;
    
    // FILA 13.1: OTROS GASTOS - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'Otros Gastos (Reparación y Mantenimiento)');
    $hojaFicha->setCellValue('B' . $fila, '13.1');
    $hojaFicha->setCellValue('C' . $fila, $data['costo_otros_gastos_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['costo_otros_gastos_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, number_format($data['porcentaje_otros_gastos'], 0) . '% sobre el costo total');
    $hojaFicha->setCellValue('B' . $fila, '13.1');
    $hojaFicha->setCellValue('C' . $fila, $data['costo_otros_gastos_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['costo_otros_gastos_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleIndent);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    // FILA 14: BASE IMPONIBLE - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'BASE IMPONIBLE (12+13.1)');
    $hojaFicha->setCellValue('B' . $fila, '14');
    $hojaFicha->setCellValue('C' . $fila, $data['base_imponible']);
    $hojaFicha->setCellValue('D' . $fila, $data['base_imponible']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleTotal);
    $fila++;
    
    // FILA 15: IMPUESTO VENTAS - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'ISV - Impuesto sobre Ventas (' . number_format($data['tasa_impuesto_ventas'], 0) . '%)');
    $hojaFicha->setCellValue('B' . $fila, '15');
    $hojaFicha->setCellValue('C' . $fila, $data['impuesto_ventas_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['impuesto_ventas_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
    $fila++;
    
    // FILA 16: CONTRIBUCIÓN LOCAL - ocultable si es 0
    if (mostrarFilaExcel($data['contribucion_local_total'], $ocultar_ceros)) {
        $hojaFicha->setCellValue('A' . $fila, 'Contribución Local (' . number_format($data['tasa_contribucion_local'], 0) . '%)');
        $hojaFicha->setCellValue('B' . $fila, '16');
        $hojaFicha->setCellValue('C' . $fila, $data['contribucion_local_total']);
        $hojaFicha->setCellValue('D' . $fila, $data['contribucion_local_total']);
        $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
        $hojaFicha->getStyle('B' . $fila)->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleNumber);
        $fila++;
    }
    
    // FILA 13: UTILIDAD ANTES IMPUESTO - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'Utilidad antes de Impuesto Gubernamental');
    $hojaFicha->setCellValue('B' . $fila, '13');
    $hojaFicha->setCellValue('C' . $fila, $data['utilidad_antes_gobierno']);
    $hojaFicha->setCellValue('D' . $fila, $data['utilidad_antes_gobierno']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleGreenTitle);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleGreen);
    $fila++;
    
    // FILA 18: IMPUESTO GUBERNAMENTAL - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'Impuesto Gubernamental (' . number_format($data['porcentaje_utilidad_gobierno'], 0) . '% de utilidades)');
    $hojaFicha->setCellValue('B' . $fila, '18');
    $hojaFicha->setCellValue('C' . $fila, $data['utilidad_para_gobierno']);
    $hojaFicha->setCellValue('D' . $fila, $data['utilidad_para_gobierno']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleConcepto);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleYellow);
    $fila++;
    
    // FILA 19: TOTAL COSTOS Y GASTOS - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'TOTAL DE COSTOS Y GASTOS (14+15+16+18)');
    $hojaFicha->setCellValue('B' . $fila, '19');
    $hojaFicha->setCellValue('C' . $fila, $data['total_gastos_final']);
    $hojaFicha->setCellValue('D' . $fila, $data['total_gastos_final']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleTotal);
    $fila++;
    
    // FILA 20: PRECIO TARIFA TOTAL - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'PRECIO O TARIFA TOTAL (14+15+16+17)');
    $hojaFicha->setCellValue('B' . $fila, '20');
    $hojaFicha->setCellValue('C' . $fila, $data['ingresos_totales']);
    $hojaFicha->setCellValue('D' . $fila, $data['ingresos_totales']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray($styleBlueTitle);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray($styleBlue);
    $fila++;
    
    // FILA 21: UTILIDAD NETA - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'UTILIDAD NETA (13 - 18)');
    $hojaFicha->setCellValue('B' . $fila, '21');
    $hojaFicha->setCellValue('C' . $fila, $data['utilidad_neta_total']);
    $hojaFicha->setCellValue('D' . $fila, $data['utilidad_neta_total']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '155724'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 14, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '155724'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ]);
    $fila++;
    
    // FILA 22: PRECIO UNITARIO - siempre visible
    $hojaFicha->setCellValue('A' . $fila, 'PRECIO UNITARIO POR HOJA');
    $hojaFicha->setCellValue('B' . $fila, '22');
    $hojaFicha->setCellValue('C' . $fila, $data['precio_venta_final']);
    $hojaFicha->setCellValue('D' . $fila, $data['precio_venta_final']);
    $hojaFicha->getStyle('A' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '004085'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCE5FF']]
    ]);
    $hojaFicha->getStyle('B' . $fila)->applyFromArray([
        'font' => ['size' => 14, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $hojaFicha->getStyle('C' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '004085'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ]);
    $fila += 2;
    
    // DATOS SOBRE PRECIOS DE REFERENCIA
    $hojaFicha->setCellValue('A' . $fila, 'Datos sobre precios de referencia');
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']]
    ]);
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, '');
    $hojaFicha->setCellValue('B' . $fila, 'Plan/Anterior');
    $hojaFicha->setCellValue('C' . $fila, 'Precio Actual');
    $hojaFicha->setCellValue('D' . $fila, '');
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray($styleHeader);
    $fila += 2;
    
    // FIRMAS
    $hojaFicha->setCellValue('A' . $fila, 'Elaborado por: ____________________');
    $hojaFicha->setCellValue('C' . $fila, 'Aprobado por: ____________________');
    $fila++;
    $hojaFicha->setCellValue('A' . $fila, 'Fecha: ' . $data['fecha_emision']);
    $hojaFicha->setCellValue('C' . $fila, 'Fecha: ' . $data['fecha_vigencia']);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);
    $fila += 2;
    $hojaFicha->setCellValue('A' . $fila, 'SISCOSTO PDL TransNuBeT - % Gastos Indirectos: 40% | Res. 148/2023 MFP');
    $hojaFicha->mergeCells('A' . $fila . ':D' . $fila);
    $hojaFicha->getStyle('A' . $fila . ':D' . $fila)->applyFromArray([
        'font' => ['size' => 8, 'color' => ['rgb' => '666666'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    $hojaFicha->getColumnDimension('A')->setWidth(50);
    $hojaFicha->getColumnDimension('B')->setWidth(10);
    $hojaFicha->getColumnDimension('C')->setWidth(18);
    $hojaFicha->getColumnDimension('D')->setWidth(18);

    // ================= HOJA 3: RESUMEN DE RENTABILIDAD =================
    $hojaResumen = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Resumen Rentabilidad');
    $spreadsheet->addSheet($hojaResumen, 2);
    $hojaResumen = $spreadsheet->getSheet(2);
    $hojaResumen->setTitle('Resumen Rentabilidad');
    
    $styleResumenTitle = [
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0078D4'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];
    $styleResumenLabel = [
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'B0C4DE'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1C2B3D']]
    ];
    $styleResumenValue = [
        'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleResumenValueGreen = [
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '28A745'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleResumenValueYellow = [
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFC107'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleResumenValueBlue = [
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '17A2B8'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    $styleResumenBadge = [
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '28A745']]
    ];
    
    $fila = 1;
    $hojaResumen->setCellValue('A' . $fila, 'RESUMEN DE RENTABILIDAD (' . $data['periodo_texto'] . ')');
    $hojaResumen->mergeCells('A' . $fila . ':B' . $fila);
    $hojaResumen->getStyle('A' . $fila . ':B' . $fila)->applyFromArray($styleResumenTitle);
    $fila += 2;
    
    $resumenDatos = [
        ['Hojas impresas por día:', number_format($data['hojas_por_dia']), 'success'],
        ['Producción terminada (período):', number_format($data['produccion_terminada']) . ' hojas', 'success'],
        ['Precio por Hoja (auto):', '$ ' . number_format($data['precio_venta_final'], 2), 'success'],
        ['Ingresos totales:', '$ ' . number_format($data['ingresos_totales'], 2), 'success'],
        ['', '', ''],
        ['Costo Total (1+2+3+4):', '$ ' . number_format($data['subtotal_gastos_directos'], 2), 'warning'],
        ['Base Imponible:', '$ ' . number_format($data['base_imponible'], 2), 'info'],
        ['Utilidad Neta (fila 21):', '$ ' . number_format($data['utilidad_neta_total'], 2), 'success'],
        ['', '', ''],
        ['Depreciación:', '$ ' . number_format($data['depreciacion_periodo'], 2), 'warning'],
        ['Impuesto Gobierno:', '$ ' . number_format($data['utilidad_para_gobierno'], 2), 'warning'],
        ['Margen de Utilidad:', number_format($data['margen_utilidad'], 1) . '%', 'badge'],
        ['', '', ''],
        ['Precio de Equilibrio:', '$ ' . number_format($data['precio_equilibrio'], 2), 'info'],
        ['Costo por Hoja:', '$ ' . number_format($data['costo_por_hoja'], 2), 'warning'],
        ['Utilidad por Hoja:', '$ ' . number_format($data['utilidad_por_hoja'], 2), 'success'],
        ['Días Laborados:', $data['dias_trabajo'], 'info'],
        ['Hojas por Día:', $data['hojas_por_dia'], 'info'],
        ['Producción Terminada:', $data['produccion_terminada'] . ' hojas', 'info'],
        ['Total Hojas del Período:', $data['total_hojas_periodo'], 'info'],
        ['', '', ''],
        ['Ocultar filas en cero:', $ocultar_ceros == 1 ? 'Sí' : 'No', 'info'],
    ];
    
    foreach ($resumenDatos as $dato) {
        if (empty($dato[0])) {
            $fila++;
            continue;
        }
        $hojaResumen->setCellValue('A' . $fila, $dato[0]);
        $hojaResumen->setCellValue('B' . $fila, $dato[1]);
        $hojaResumen->getStyle('A' . $fila)->applyFromArray($styleResumenLabel);
        if ($dato[2] == 'badge') {
            $hojaResumen->getStyle('B' . $fila)->applyFromArray($styleResumenBadge);
        } else {
            $estiloValue = $styleResumenValue;
            if ($dato[2] == 'success') $estiloValue = $styleResumenValueGreen;
            elseif ($dato[2] == 'warning') $estiloValue = $styleResumenValueYellow;
            elseif ($dato[2] == 'info') $estiloValue = $styleResumenValueBlue;
            $hojaResumen->getStyle('B' . $fila)->applyFromArray($estiloValue);
        }
        $fila++;
    }
    $fila++;
    $hojaResumen->setCellValue('A' . $fila, 'Emisión: ' . $data['fecha_emision'] . '  |  Vigencia: ' . $data['fecha_vigencia']);
    $hojaResumen->mergeCells('A' . $fila . ':B' . $fila);
    $hojaResumen->getStyle('A' . $fila . ':B' . $fila)->applyFromArray([
        'font' => ['size' => 10, 'color' => ['rgb' => 'B0C4DE'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1C2B3D']]
    ]);
    $hojaResumen->getColumnDimension('A')->setWidth(35);
    $hojaResumen->getColumnDimension('B')->setWidth(25);
    
    // ANEXO: DESAGREGACIÓN DE LOS INSUMOS
    $hojaInsumos = $spreadsheet->createSheet();
    $hojaInsumos->setTitle('Desagregación Insumos');
    
    $styleInsumosTitle = [
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2B7FFF']]
    ];
    $styleInsumosHeader = [
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1C2B3D']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $styleInsumosCelda = [
        'font' => ['size' => 10, 'name' => 'Arial'],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ];
    $styleInsumosNum = [
        'font' => ['size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'numberFormat' => ['formatCode' => '"$" #,##0.00']
    ];
    
    $total_h_mes = $data['total_hojas_periodo'];
    $paquetes_in = (int)ceil($total_h_mes / max(1, intval($data['uds_adhesivo_paquete'] ?? 20)));
    $duracion_in = floatval($data['duracion_kit_tinta'] ?? 12);
    $meses_in = (stripos($data['periodo_texto'] ?? '', 'DIA') !== false) ? 1 / 24 : 1;
    $kits_in = ($duracion_in > 0) ? $meses_in / $duracion_in : 0;
    $precio_papel_in = ($paquetes_in > 0) ? $data['gasto_papel_adhesivo_total'] / $paquetes_in : 0;
    $precio_tinta_in = ($kits_in > 0) ? $data['costo_tinta_total'] / $kits_in : 0;
    
    $fila = 1;
    $hojaInsumos->setCellValue('A' . $fila, 'ANEXO 2 — DESAGREGACIÓN DE LOS INSUMOS (Res. 148/2023 MFP)');
    $hojaInsumos->mergeCells('A' . $fila . ':F' . $fila);
    $hojaInsumos->getStyle('A' . $fila . ':F' . $fila)->applyFromArray($styleInsumosTitle);
    $fila++;
    $hojaInsumos->setCellValue('A' . $fila, 'Metodología para la elaboración de la ficha de costos y gastos de productos y servicios para la evaluación de precios y tarifas. Gaceta Oficial No. 64 (6-jul-2023). Período: ' . $data['periodo_texto']);
    $hojaInsumos->mergeCells('A' . $fila . ':F' . $fila);
    $hojaInsumos->getStyle('A' . $fila . ':F' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'color' => ['rgb' => '555555'], 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $fila += 2;
    
    $hojaInsumos->setCellValue('A' . $fila, 'No.');
    $hojaInsumos->setCellValue('B' . $fila, 'Denominación');
    $hojaInsumos->setCellValue('C' . $fila, 'UM');
    $hojaInsumos->setCellValue('D' . $fila, 'Cantidad (período)');
    $hojaInsumos->setCellValue('E' . $fila, 'Precio Unitario');
    $hojaInsumos->setCellValue('F' . $fila, 'Importe');
    $hojaInsumos->getStyle('A' . $fila . ':F' . $fila)->applyFromArray($styleInsumosHeader);
    $hojaInsumos->getRowDimension($fila)->setRowHeight(22);
    $fila++;
    
    $insumos = [
        [1, 'Papel adhesivo A4 (paquete de ' . $data['uds_adhesivo_paquete'] . ' hojas)', 'Paquete', $paquetes_in, $precio_papel_in, $data['gasto_papel_adhesivo_total']],
        [2, 'Tinta para impresión (dura ' . $data['duracion_kit_tinta'] . ' meses)', 'Kit', round($kits_in, 2), $precio_tinta_in, $data['costo_tinta_total']],
        [3, 'Energía eléctrica (período)', 'Mes', 1, $data['gasto_energia_periodo'], $data['gasto_energia_periodo']],
    ];
    if (floatval($data['gasto_agua_periodo'] ?? 0) > 0) {
        $insumos[] = [4, 'Agua (período)', 'Mes', 1, $data['gasto_agua_periodo'], $data['gasto_agua_periodo']];
    }
    foreach ($insumos as $item) {
        $hojaInsumos->setCellValue('A' . $fila, $item[0]);
        $hojaInsumos->setCellValue('B' . $fila, $item[1]);
        $hojaInsumos->setCellValue('C' . $fila, $item[2]);
        $hojaInsumos->setCellValue('D' . $fila, $item[3]);
        $hojaInsumos->setCellValue('E' . $fila, $item[4]);
        $hojaInsumos->setCellValue('F' . $fila, $item[5]);
        $hojaInsumos->getStyle('A' . $fila . ':F' . $fila)->applyFromArray($styleInsumosCelda);
        $hojaInsumos->getStyle('A' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hojaInsumos->getStyle('C' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hojaInsumos->getStyle('D' . $fila)->applyFromArray([
            'font' => ['size' => 10, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'numberFormat' => ['formatCode' => '#,##0.###']
        ]);
        $hojaInsumos->getStyle('E' . $fila . ':F' . $fila)->applyFromArray($styleInsumosNum);
        $fila++;
    }
    $hojaInsumos->setCellValue('A' . $fila, 'TOTAL INSUMOS (fila 1.1)');
    $hojaInsumos->mergeCells('A' . $fila . ':E' . $fila);
    $hojaInsumos->getStyle('A' . $fila . ':E' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']]
    ]);
    $hojaInsumos->setCellValue('F' . $fila, $data['gasto_material_total']);
    $hojaInsumos->getStyle('F' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'numberFormat' => ['formatCode' => '"$" #,##0.00'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']]
    ]);
    $fila++;
    $hojaInsumos->setCellValue('A' . $fila, 'Nota: Cantidades referidas al período ' . $data['periodo_texto'] . ' (' . number_format($total_h_mes, 0) . ' hojas).');
    $hojaInsumos->mergeCells('A' . $fila . ':F' . $fila);
    $hojaInsumos->getStyle('A' . $fila . ':F' . $fila)->applyFromArray([
        'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666'], 'name' => 'Arial']
    ]);
    $hojaInsumos->getColumnDimension('A')->setWidth(8);
    $hojaInsumos->getColumnDimension('B')->setWidth(46);
    $hojaInsumos->getColumnDimension('C')->setWidth(10);
    $hojaInsumos->getColumnDimension('D')->setWidth(12);
    $hojaInsumos->getColumnDimension('E')->setWidth(15);
    $hojaInsumos->getColumnDimension('F')->setWidth(15);
    
    // CONFIGURACIÓN DE IMPRESIÓN
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageMargins()
            ->setTop(0.5)->setRight(0.5)->setBottom(0.5)->setLeft(0.5);
    }
    $hojaParams->getPageSetup()->setPrintArea('A1:D' . ($fila));
    $hojaFicha->getPageSetup()->setPrintArea('A1:D' . ($fila + 10));
    $hojaResumen->getPageSetup()->setPrintArea('A1:B' . ($fila));
    $hojaInsumos->getPageSetup()->setPrintArea('A1:F' . $fila);
    
    // DESCARGAR
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Ficha_Costo_Completo_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    $writer->save('php://output');
    exit;
}

// ============================================
// 9. PROCESAMIENTO DE LA EXPORTACIÓN
// ============================================
if ((isset($_GET['action']) && $_GET['action'] == 'excel') || (isset($_POST['action']) && $_POST['action'] == 'excel')) {
    $ocultar_ceros = 1;
    if (isset($_POST['ocultar_ceros'])) {
        $ocultar_ceros = intval($_POST['ocultar_ceros']);
    } elseif (isset($_COOKIE['ocultar_ceros'])) {
        $ocultar_ceros = intval($_COOKIE['ocultar_ceros']);
    }
    
    $data = [
        'periodo_texto' => $periodo_texto,
        'fecha_emision' => $fecha_emision,
        'fecha_vigencia' => $fecha_vigencia,
        'fecha_emision_input' => $fecha_emision_input,
        'vigencia_valor' => $vigencia_valor,
        'vigencia_unidad' => $vigencia_unidad,
        'nombre_servicio' => $nombre_servicio,
        'codigo_servicio' => $codigo_servicio,
        'nivel_produccion' => $nivel_produccion,
        'porcentaje_capacidad' => $porcentaje_capacidad,
        'categoria' => $categoria,
        'hojas_por_dia' => $hojas_por_dia,
        'total_hojas_periodo' => $total_hojas_periodo,
        'produccion_terminada' => $total_hojas_periodo,
        'precio_venta_final' => $precio_venta_final,
        'ingreso_por_hoja' => $ingreso_por_hoja,
        'ingresos_totales' => $ingresos_totales,
        'dias_trabajo' => $dias_trabajo,
        'gasto_papel_adhesivo_total' => $gasto_papel_adhesivo,
        'costo_tinta_total' => $costo_tinta,
        'gasto_energia_periodo' => $gasto_energia_periodo,
    'gasto_agua_periodo' => $gasto_agua_periodo,
        'gasto_transportacion' => $gasto_transportacion_periodo,
        'gasto_material_total' => $gasto_material_total,
        'precio_adhesivo_paquete' => $precio_adhesivo_paquete,
        'uds_adhesivo_paquete' => $uds_adhesivo_paquete,
        'precio_kit_tinta' => $precio_kit_tinta,
'duracion_kit_tinta' => $duracion_kit_tinta,
        'valor_activo_impresora' => $valor_activo_impresora,
        'tope_seguridad_social' => $tope_seguridad_social,
        'num_trabajadores' => $num_trabajadores,
        'salario_total' => $salario_total,
        'seguridad_social_total' => $seguridad_social_total,
        'vacaciones_total' => $vacaciones_total,
        'impuesto_fuerza_trabajo' => $impuesto_fuerza_trabajo,
        'depreciacion_periodo' => $depreciacion_periodo,
        'subtotal_gastos_directos' => $subtotal_gastos_directos,
        'costo_otros_gastos_total' => $costo_otros_gastos_total,
        'base_imponible' => $base_imponible,
        'impuesto_ventas_total' => $impuesto_ventas_total,
        'contribucion_local_total' => $contribucion_local_total,
        'utilidad_antes_gobierno' => $utilidad_antes_gobierno,
        'utilidad_para_gobierno' => $utilidad_para_gobierno,
        'utilidad_neta_total' => $utilidad_neta_total,
        'total_gastos_final' => $total_gastos_final,
        'porcentaje_vacaciones' => $porcentaje_vacaciones,
        'porcentaje_fuerza_trabajo' => $porcentaje_fuerza_trabajo,
        'porcentaje_depreciacion_anual' => $porcentaje_depreciacion_anual,
        'tasa_impuesto_ventas' => $tasa_impuesto_ventas,
        'tasa_contribucion_local' => $tasa_contribucion_local,
        'porcentaje_utilidad_gobierno' => $porcentaje_utilidad_gobierno,
        'porcentaje_otros_gastos' => $porcentaje_otros_gastos,
        'costo_por_hoja' => $costo_por_hoja,
        'precio_equilibrio' => $precio_equilibrio,
        'margen_utilidad' => $margen_utilidad,
        'utilidad_por_hoja' => $utilidad_por_hoja,
    ];
    exportarFichaCostoExcel($data, $ocultar_ceros);
}

// ============================================
// 10. INTERFAZ HTML
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Sistema Ficha de Costo - Impresión</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="../nominas/css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../nominas/css/font-awesome6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../nominas/css/sweetalert2.min.css">
    <script src="../nominas/js/sweetalert211.js"></script>
<style>
    /* ============================================================
       CONFIGURACIÓN GENERAL Y RESET
       ============================================================ */
    @page {
        size: 8.5in 11in;            /* Hoja Carta VERTICAL */
        margin: 18mm 13mm 20mm 13mm; /* margen inferior reservado para el N° de página */
    }
    * { box-sizing: border-box; }
    body {
        background: linear-gradient(135deg, #0a0a0f 0%, #141425 30%, #0d0d1a 60%, #0a0a0f 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #e4e4e4;
    }
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        background-image: url('LogoTN.png');
        background-size: 100px 100px;
        background-repeat: repeat;
        opacity: 0.04;
        pointer-events: none;
        transform: rotate(-5deg) scale(1.2);
    }
    body::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        background-image: url('LogoTN.png');
        background-size: 140px 140px;
        background-repeat: repeat;
        opacity: 0.025;
        pointer-events: none;
        transform: rotate(8deg) scale(1.1);
    }
    #pantallaBienvenida {
        position: relative;
        overflow: hidden;
        max-width: 850px;
        width: 100%;
        background: rgba(13, 13, 13, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 3rem 2.5rem;
        text-align: center;
        border: 1px solid rgba(43, 127, 255, 0.15);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9), 0 0 80px rgba(43, 127, 255, 0.05);
        transition: opacity 0.4s ease, transform 0.4s ease;
        min-height: 650px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
        margin: 20px;
    }
    #pantallaBienvenida::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(ellipse at center, rgba(43, 127, 255, 0.06) 0%, transparent 70%);
        animation: brilloFondo 8s ease-in-out infinite alternate;
        z-index: 0;
    }
    @keyframes brilloFondo {
        0% { transform: translate(-10%, -10%) scale(1); opacity: 0.5; }
        100% { transform: translate(10%, 10%) scale(1.2); opacity: 1; }
    }
    .contenido-bienvenida { position: relative; z-index: 2; width: 100%; }
    #pantallaBienvenida h1 {
        font-weight: 700;
        font-size: 2.2rem;
        color: #f0f0f0;
        letter-spacing: -0.02em;
        text-shadow: 0 0 40px rgba(43, 127, 255, 0.1);
        position: relative;
        z-index: 2;
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }
    #pantallaBienvenida h1 span {
        background: linear-gradient(135deg, #60b0ff, #3d8bff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    #pantallaBienvenida .subtitle {
        color: #9a9a9a;
        font-size: 1rem;
        margin: 1.5rem 0 1.5rem 0;
        border-top: 1px solid rgba(43, 127, 255, 0.1);
        border-bottom: 1px solid rgba(43, 127, 255, 0.1);
        padding: 1rem 0;
        position: relative;
        z-index: 2;
    }
    #pantallaBienvenida .subtitle b { color: #60b0ff; }
    #pantallaBienvenida .version {
        color: #4a4a4a;
        font-size: 0.85rem;
        margin-top: 1.5rem;
        position: relative;
        z-index: 2;
    }
    .logo-fondo-animado {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        opacity: 0.06;
        overflow: hidden;
        pointer-events: none;
    }
    .logo-fondo-animado img {
        position: absolute;
        width: 280px;
        height: auto;
        opacity: 0.8;
        filter: grayscale(100%) brightness(1.8) blur(1px);
    }
    .logo-fondo-rotacion {
        position: absolute;
        top: -20%;
        left: -20%;
        width: 140%;
        height: 140%;
        animation: rotarLogo 60s linear infinite;
    }
    .logo-fondo-rotacion img { top: 5%; left: 5%; transform: rotate(15deg); width: 300px; }
    .logo-fondo-rotacion .logo-duplicado { top: 55%; left: 55%; transform: rotate(-10deg); width: 220px; }
    .logo-fondo-rotacion-inversa {
        position: absolute;
        top: -30%;
        left: -30%;
        width: 160%;
        height: 160%;
        animation: rotarLogoInversa 80s linear infinite;
    }
    .logo-fondo-rotacion-inversa img { top: 25%; left: 65%; transform: rotate(-25deg); width: 200px; }
    .logo-fondo-rotacion-inversa .logo-duplicado { top: 65%; left: 15%; transform: rotate(30deg); width: 240px; }
    @keyframes rotarLogo { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes rotarLogoInversa { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }
    .btn-w11-primary {
        background: linear-gradient(135deg, #2b7fff, #1a5fd0);
        border: none;
        color: #fff;
        font-weight: 500;
        padding: 0.85rem 3rem;
        border-radius: 12px;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(43, 127, 255, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 2;
        cursor: pointer;
    }
    .btn-w11-primary:hover { transform: scale(1.03); box-shadow: 0 8px 28px rgba(43, 127, 255, 0.45); color: #fff; }
    .btn-w11-secondary {
        background: #2a2a2a;
        border: 1px solid #3d3d3d;
        color: #ccc;
        border-radius: 10px;
        padding: 0.6rem 1.8rem;
        font-weight: 500;
        transition: 0.2s;
        cursor: pointer;
    }
    .btn-w11-secondary:hover { background: #333; color: #fff; border-color: #5a5a5a; }
    #contenidoPrincipal { display: none; max-width: 1200px; width: 100%; margin: 0 auto; }
    .card-main {
        background: #1c1c1c;
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.04);
        border: 1px solid #2a2a2a;
    }
    .header {
        border-bottom: 2px solid #2b7fff;
        padding-bottom: 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .header h2 { color: #60b0ff; font-weight: 600; letter-spacing: -0.02em; }
    .header .text-muted { color: #9a9a9a !important; }
    .header .text-muted strong { color: #60b0ff; }
    .btn-accion-header {
        background: #2a2a2a;
        border: 1px solid #3d3d3d;
        color: #ccc;
        border-radius: 10px;
        padding: 0.4rem 1.2rem;
        font-weight: 500;
        font-size: 0.85rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .btn-accion-header:hover { background: #333; color: #fff; border-color: #5a5a5a; }
    .btn-accion-header i { color: #60b0ff; }
    .btn-metodologia {
        background: #2a2a2a;
        border: 1px solid #3d3d3d;
        color: #ccc;
        border-radius: 10px;
        padding: 0.4rem 1.2rem;
        font-weight: 500;
        font-size: 0.85rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .btn-metodologia:hover { background: #333; color: #fff; border-color: #5a5a5a; }
    .btn-metodologia i { color: #ffc107; }
    .btn-success {
        background: #1e7e34 !important;
        border: 1px solid #1e7e34 !important;
        color: #fff !important;
        border-radius: 10px !important;
        font-weight: 500 !important;
        padding: 0.4rem 1.2rem !important;
        font-size: 0.85rem !important;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-success:hover { background: #2a9d4a !important; border-color: #2a9d4a !important; color: #fff !important; }
    .btn-success .dropdown-toggle::after { margin-left: 0.5rem; }
    .dropdown-menu {
        background: #1c1c1c !important;
        border: 1px solid #2a2a2a !important;
        border-radius: 12px !important;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6) !important;
        padding: 0.3rem 0 !important;
        min-width: 200px !important;
    }
    .dropdown-menu .dropdown-item {
        color: #e4e4e4 !important;
        padding: 0.5rem 1.2rem !important;
        font-size: 0.85rem !important;
        transition: 0.2s !important;
        cursor: pointer;
    }
    .dropdown-menu .dropdown-item:hover { background: #2a2a2a !important; color: #ffffff !important; }
    .dropdown-menu .dropdown-item i { width: 20px; text-align: center; }
    .dropdown-divider { border-color: #2a2a2a !important; margin: 0.2rem 0 !important; }
    .ficha-container {
        background: #1c1c1c;
        padding: 20px;
        border: 1px solid #2a2a2a;
        border-radius: 12px;
        margin-top: 30px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        color: #e4e4e4;
    }
    table.ficha {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5pt;
        color: #e4e4e4;
    }
    table.ficha th {
        background: #1f2a3a;
        border: 1px solid #3a4a5a;
        padding: 4px;
        text-align: center;
        color: #b0c4de;
    }
    table.ficha td {
        border: 1px solid #2a3a4a;
        padding: 4px;
    }
    .concepto { font-weight: bold; }
    .indent { padding-left: 20px !important; font-weight: normal; }
    .indent2 { padding-left: 35px !important; font-weight: normal; font-size: 8.5pt; color: #8a8a8a; }
    .bg-green-light { background-color: #0d2a1a !important; }
    .bg-blue-light { background-color: #0a1a2a !important; }
    .bg-yellow-light { background-color: #2a2a0a !important; }
    .bg-gray-light { background-color: #1a1a1a !important; }
    .bg-light { background-color: #1a1a1a !important; }
    .text-success-dark { color: #4a8a5a !important; }
    .text-blue-dark { color: #4a8aba !important; }
    .text-end { text-align: right !important; }
    .text-center { text-align: center !important; }
    .fw-bold { font-weight: bold !important; }
    .resumen-box-separado {
        background: #1c2b3d;
        color: #ffffff;
        border: 3px solid #2b7fff;
        border-radius: 12px;
        padding: 25px;
        margin-top: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    .resumen-box-separado .row { padding: 5px 0; border-bottom: 1px solid #3a506b; }
    .resumen-box-separado .row:last-child { border-bottom: none; }
    .resumen-box-separado .label { font-weight: bold; color: #b0c4de; }
    .resumen-box-separado .value { font-weight: bold; font-size: 13pt; }
    .resumen-box-separado h4 { color: #60b0ff; border-bottom: 2px solid #2b7fff; padding-bottom: 10px; margin-bottom: 20px; }
    .resumen-box-separado .badge.bg-success { background: #1a7a3a !important; font-size: 14px; padding: 0.35rem 0.75rem; border-radius: 20px; }
    .page-break { page-break-before: always; margin-top: 30px; border-top: 2px dashed #2b7fff; padding-top: 20px; }
    .footer { margin-top: 30px; border-top: 1px solid #2a2a2a; padding-top: 15px; font-size: 9pt; color: #6a6a6a; text-align: center; }
    .print-running-header, .print-page-number { display: none; }
    .modal-content { background: #1a1a1a; border: 1px solid #2d2d2d; border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9); color: #e8e8e8; }
    .modal-header { border-bottom: 2px solid #2b7fff; padding: 1rem 1.8rem; }
    .modal-header .modal-title { font-weight: 600; font-size: 1.3rem; color: #f0f0f0; }
    .modal-header .modal-title span { display: block; font-size: 0.7rem; color: #9a9a9a; font-weight: normal; margin-top: 2px; }
    .modal-header .btn-close { display: block !important; filter: invert(1) brightness(1.5); opacity: 0.7; transition: 0.2s; cursor: pointer; }
    .modal-header .btn-close:hover { opacity: 1; transform: rotate(90deg); }
    .nav-tabs { border-bottom: 2px solid #2a2a2a !important; }
    .nav-tabs .nav-link {
        color: #9a9a9a;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        font-weight: 600;
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        border-radius: 8px 8px 0 0;
    }
    .nav-tabs .nav-link:not(.active):hover {
        background: rgba(43, 127, 255, 0.16);
        color: #ffffff;
        border-color: transparent;
    }
    .nav-tabs .nav-link:hover i { transform: translateY(-2px) scale(1.05); }
    .nav-tabs .nav-link i { transition: transform 0.3s ease; color: #60b0ff; }
    .nav-tabs .nav-link.active { color: #60b0ff; background: transparent; border-bottom: 3px solid #2b7fff; }
    /* Checkbox "Fijar costo de la hoja impresa" */
    #modal_costo_fijo_hoja {
        width: 1.15em;
        height: 1.15em;
        margin-top: 1px;
        background-color: #17181c;
        border: 1px solid #4a5568;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.25s ease;
    }
    #modal_costo_fijo_hoja:hover {
        border-color: #60b0ff;
        box-shadow: 0 0 0 3px rgba(43, 127, 255, 0.18);
    }
    #modal_costo_fijo_hoja:checked {
        background-color: #2b7fff;
        border-color: #2b7fff;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 8.5l4 4 8-9'/%3e%3c/svg%3e");
        background-size: 1em 1em;
        background-position: center;
        background-repeat: no-repeat;
        box-shadow: 0 0 0 3px rgba(43, 127, 255, 0.22);
    }
    #modal_costo_fijo_hoja:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(43, 127, 255, 0.35);
    }
    .modal-body { padding: 0.5rem 1.8rem 1rem 1.8rem !important; max-height: 75vh; overflow-y: auto; }
    .modal-body .row { --bs-gutter-x: 0.5rem; --bs-gutter-y: 0.3rem; }
    .modal-body .form-label-detalle {
        font-size: 0.65rem !important;
        color: #9a9a9a !important;
        font-weight: 600;
        margin-bottom: 2px !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: block;
    }
    .modal-body .form-label-detalle i { color: #60b0ff; }
    .modal-body .form-control-sm, .modal-body .form-select-sm {
        height: 32px !important;
        font-size: 0.8rem !important;
        padding: 0.2rem 0.6rem !important;
        border-radius: 8px !important;
        background-color: #121212 !important;
        border: 1px solid #2a2a2a !important;
        color: #f0f0f0 !important;
        transition: all 0.3s ease;
    }
    .modal-body .form-control-sm:focus, .modal-body .form-select-sm:focus {
        background-color: #1a1a1a !important;
        border-color: #2b7fff !important;
        box-shadow: 0 0 0 3px rgba(43, 127, 255, 0.15) !important;
        color: #f0f0f0 !important;
    }
    .modal-body .form-control-sm:hover, .modal-body .form-select-sm:hover { border-color: #3a3a3a !important; }
    .modal-body .form-control-sm::placeholder { color: #555; font-size: 0.7rem; }
    .modal-body .form-control-sm[readonly] { background-color: #0a0a0a !important; color: #60b0ff !important; cursor: default; }
    .modal-body .input-group-text {
        background: #121212;
        border: 1px solid #2a2a2a;
        color: #9a9a9a;
        font-size: 0.7rem;
        border-radius: 8px 0 0 8px;
        padding: 0.1rem 0.5rem;
        height: 32px;
    }
    .modal-body .input-group .form-control-sm { border-radius: 0 8px 8px 0; }
    .modal-body .btn-outline-primary.btn-sm {
        font-size: 0.75rem !important;
        padding: 0.2rem 0.5rem !important;
        height: 32px !important;
        line-height: 1.2 !important;
        border-radius: 6px !important;
        color: #60b0ff;
        border-color: #2b4870;
        cursor: pointer;
    }
    .modal-body .btn-outline-primary.btn-sm:hover { background: #2b7fff; border-color: #2b7fff; color: #fff; }
    .modal-body .btn-check:checked+.btn-outline-primary.btn-sm { background: #2b7fff; border-color: #2b7fff; color: #fff; }
    .modal-body .btn-check:focus+.btn-outline-primary.btn-sm { box-shadow: 0 0 0 3px rgba(43, 127, 255, 0.25); }
    .modal-body .dias-indicador { font-size: 0.65rem !important; color: #60b0ff !important; display: block; margin-top: 3px; }
    .modal-body .dias-indicador i { margin-right: 3px; }
    .modal-body .nota-informativa {
        background: rgba(43, 127, 255, 0.05);
        border-left: 3px solid #2b7fff;
        border-radius: 8px;
        padding: 0.6rem 1rem;
        margin-top: 1rem;
    }
    .modal-body .nota-informativa p { font-size: 0.7rem; color: #9a9a9a; margin: 0; }
    .modal-body .nota-informativa strong { color: #60b0ff; }
    .modal-body .nota-informativa .destacar { color: #ffc107; }
    .tab-pane { animation: fadeSlide 0.3s ease forwards; }
    @keyframes fadeSlide { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .modal-footer { border-top: 1px solid #2a2a2a; padding: 0.8rem 1.8rem !important; }
    .btn-generar-modal {
        background: linear-gradient(135deg, #2b7fff, #1a5fd0);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 0.5rem 2.5rem;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(43, 127, 255, 0.25);
        cursor: pointer;
    }
    .btn-generar-modal:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(43, 127, 255, 0.4); color: #fff; }
    .btn-generar-modal:active { transform: translateY(0); }
    .btn-outline-secondary {
        background: transparent;
        border: 1px solid #333;
        color: #9a9a9a;
        transition: all 0.3s ease;
        border-radius: 8px;
        padding: 0.4rem 1.5rem;
        font-size: 0.8rem;
        cursor: pointer;
    }
    .btn-outline-secondary:hover { background: #2a2a2a; border-color: #444; color: #f0f0f0; }
    .modal-body::-webkit-scrollbar { width: 5px; }
    .modal-body::-webkit-scrollbar-track { background: #1a1a1a; border-radius: 10px; }
    .modal-body::-webkit-scrollbar-thumb { background: #2b7fff; border-radius: 10px; }
    .modal-body::-webkit-scrollbar-thumb:hover { background: #3d8bff; }
    .modal-metodologia .modal-content { max-width: 900px; margin: 0 auto; }
    .modal-metodologia .modal-body { max-height: 80vh; overflow-y: auto; }
    .modal-metodologia .table-metodologia { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .modal-metodologia .table-metodologia th {
        background: #1f2a3a;
        color: #b0c4de;
        padding: 8px;
        text-align: left;
        border: 1px solid #2a3a4a;
    }
    .modal-metodologia .table-metodologia td {
        padding: 6px 8px;
        border: 1px solid #2a3a4a;
        color: #d0d0d0;
    }
    .modal-metodologia .table-metodologia .fila { color: #60b0ff; font-weight: bold; }
    .modal-metodologia .table-metodologia .formula { color: #ffc107; font-family: 'Courier New', monospace; font-size: 0.8rem; }
    .modal-metodologia .table-metodologia .desc { color: #9a9a9a; }
    .swal2-popup { border: 1px solid #2a2a2a !important; border-radius: 16px !important; background: #1c1c1c !important; }
    .swal2-title { color: #f0f0f0 !important; }
    .swal2-html-container { color: #b0b0b0 !important; }
    .swal2-confirm { background: #2b7fff !important; border-radius: 10px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
    .swal2-confirm:hover { background: #3d8bff !important; }
    .swal2-cancel { background: #2a2a2a !important; border: 1px solid #3d3d3d !important; color: #ccc !important; border-radius: 10px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
    .swal2-cancel:hover { background: #333 !important; color: #fff !important; }
    .swal2-confirm i, .swal2-cancel i { font-size: 1rem; }
    @media print {
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body {
            background: #fff !important;
            margin: 0;
            padding: 0;
            display: block;
            color: #000;
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
        }
        body::before, body::after, #pantallaBienvenida { display: none !important; }
        .header, .no-print, .modal, .modal-backdrop, .swal2-container { display: none !important; }
        #contenidoPrincipal { display: block !important; max-width: 100% !important; margin: 0 !important; }
        .card-main {
            background: #fff !important;
            box-shadow: none;
            border: none;
            border-radius: 0;
            padding: 0;
            max-width: 100%;
        }
        .ficha-container {
            border: none;
            box-shadow: none;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-size: 9pt;
        }
        #fichaParaImprimir > div:first-child { margin-bottom: 4mm; }
        #fichaParaImprimir > div:first-child > div { font-size: 10pt; }
        #fichaParaImprimir > div { margin-bottom: 1.5mm; }

        /* Iconos de guiones no se imprimen sobre fondo blanco */
        #fichaParaImprimir i, .resumen-box-separado i, .anexo-container i { display: none !important; }

        /* ---------- TABLA PRINCIPAL ---------- */
        table.ficha {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            border: 1.5px solid #000;
            page-break-inside: auto;
            break-inside: auto;
        }
        table.ficha thead { display: table-header-group; }
        table.ficha tr { page-break-inside: avoid; break-inside: avoid; }
        table.ficha th {
            background: #1f4e78 !important;
            color: #fff !important;
            border: 1px solid #000 !important;
            padding: 1.4mm 2mm;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
        }
        table.ficha td {
            border: 1px solid #000 !important;
            padding: 1.2mm 2mm;
            color: #000 !important;
            font-size: 8pt;
        }
        table.ficha td.concepto { font-weight: bold; }
        .indent { padding-left: 7mm !important; }
        .indent2 { padding-left: 12mm !important; font-size: 7pt; color: #333 !important; }

        /* Colores de filas de resumen visual */
        .bg-green-light { background-color: #d4edda !important; }
        .bg-blue-light { background-color: #cce5ff !important; }
        .bg-yellow-light { background-color: #fff3cd !important; }
        .bg-gray-light { background-color: #eef0f2 !important; }
        .bg-light { background-color: #f3f5f7 !important; }
        .text-success-dark { color: #155724 !important; }
        .text-blue-dark { color: #004085 !important; }

        /* Pie de la ficha (firmas) */
        #fichaParaImprimir .footer { border-top: 1px solid #ccc !important; color: #333 !important; font-size: 8pt !important; }
        table.ficha tr td[colspan] { background: #f2f2f2 !important; color: #000 !important; }

        /* ---------- PÁGINA 2: RESUMEN (hoja independiente) ---------- */
        .page-break {
            page-break-before: always;
            break-before: page;
            margin: 0 !important;
            border: none !important;
            padding: 0 !important;
        }
        .resumen-box-separado {
            background: #fff !important;
            color: #000 !important;
            border: 2px solid #000 !important;
            border-radius: 0 !important;
            padding: 7mm !important;
            margin-top: 0 !important;
            box-shadow: none !important;
        }
        .resumen-box-separado h4 {
            color: #000 !important;
            border-bottom: 2px solid #000 !important;
            padding-bottom: 3mm !important;
            margin-bottom: 5mm !important;
            font-size: 12pt !important;
            font-weight: bold !important;
            text-align: center;
        }
        .resumen-box-separado .row { padding: 1.6mm 0; border-bottom: 1px solid #ccc !important; }
        .resumen-box-separado .row:last-child { border-bottom: none !important; }
        .resumen-box-separado .label { color: #000 !important; font-size: 8.5pt !important; }
        .resumen-box-separado .value { font-size: 9.5pt !important; color: #000 !important; }
        .resumen-box-separado .text-success, .resumen-box-separado .text-warning,
        .resumen-box-separado .text-info { color: #000 !important; }
        .resumen-box-separado .badge.bg-success { background: #d4edda !important; color: #155724 !important; }
        .resumen-box-separado > div:last-child { color: #333 !important; border-top-color: #000 !important; }

        /* ---------- PÁGINA 3: ANEXO (hoja independiente) ---------- */
        .anexo-container {
            page-break-before: always;
            break-before: page;
            margin-top: 0 !important;
            padding: 0 !important;
        }
        .anexo-container h4 {
            color: #000 !important;
            font-size: 11pt !important;
            text-align: center;
            margin-top: 0 !important;
            margin-bottom: 2mm;
        }
        .anexo-container > div > p { color: #222 !important; font-size: 8pt !important; }

        /* ---------- N° DE PÁGINA Y ENCABEZADO (se repiten en cada hoja) ---------- */
        .print-running-header, .print-page-number {
            display: block;
            position: fixed;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            z-index: 9999;
            pointer-events: none;
        }
        .print-running-header { top: 3mm; font-size: 7.5pt; color: #666; }
        .print-page-number { bottom: 4mm; font-size: 8.5pt; color: #333; }
        .print-page-number::after { content: "Página " counter(page); }
    }
    @media (max-width: 992px) { .modal-dialog { margin: 0.5rem !important; } }
    @media (max-width: 768px) {
        .modal-body { padding: 0.5rem 1rem !important; }
        .nav-tabs .nav-link { font-size: 0.65rem !important; padding: 0.4rem 0.5rem !important; }
        .nav-tabs .nav-link i { margin-right: 3px !important; }
        .modal-footer { flex-wrap: wrap; gap: 8px; }
        .modal-footer .btn { width: 100%; }
        .header { flex-direction: column; align-items: stretch; }
        .btn-accion-header, .btn-metodologia, .btn-success { justify-content: center; }
        .dropdown-menu { min-width: 100% !important; }
    }
    @media (max-width: 600px) {
        #pantallaBienvenida { padding: 2rem 1.5rem; min-height: 550px; margin: 10px; }
        #pantallaBienvenida h1 { font-size: 1.6rem; }
        body::before { background-size: 70px 70px !important; transform: rotate(-5deg) scale(1.1); }
        body::after { background-size: 100px 100px !important; transform: rotate(8deg) scale(1); }
        .logo-fondo-animado img { width: 150px !important; }
        .logo-fondo-rotacion { top: -40%; left: -40%; width: 180%; height: 180%; }
        .logo-fondo-rotacion-inversa { top: -50%; left: -50%; width: 200%; height: 200%; }
        #pantallaBienvenida .subtitle { font-size: 0.85rem; }
        .btn-w11-primary { padding: 0.65rem 2rem; font-size: 0.95rem; }
        .modal-body .form-control-sm, .modal-body .form-select-sm { height: 28px !important; font-size: 0.7rem !important; }
        .modal-body .form-label-detalle { font-size: 0.55rem !important; }
        .modal-body .btn-outline-primary.btn-sm { font-size: 0.65rem !important; padding: 0.1rem 0.3rem !important; height: 28px !important; }
        .modal-body .input-group-text { height: 28px !important; font-size: 0.6rem !important; }
        .btn-generar-modal { padding: 0.4rem 1.5rem !important; font-size: 0.8rem !important; }
    }
    @media (max-width: 480px) {
        #pantallaBienvenida h1 { font-size: 1.3rem; }
        #pantallaBienvenida .subtitle { font-size: 0.75rem; }
        body::before { background-size: 50px 50px !important; transform: rotate(-5deg) scale(1); }
        body::after { background-size: 70px 70px !important; transform: rotate(8deg) scale(1); }
        .nav-tabs { flex-wrap: nowrap; overflow-x: auto; gap: 2px; }
        .nav-tabs .nav-link { font-size: 0.55rem !important; padding: 0.3rem 0.4rem !important; white-space: nowrap; }
        .modal-body { padding: 0.3rem 0.5rem !important; }
        .modal-header { padding: 0.6rem 1rem !important; }
        .modal-header .modal-title { font-size: 1rem !important; }
        .modal-footer { padding: 0.5rem 1rem !important; }
    }
    .text-muted.small { color: #6a6a6a !important; font-size: 0.75rem; }
    .text-muted.small i { color: #60b0ff; }
    .modal-backdrop.show { opacity: 0.85 !important; }
    .card.bg-light { background: #141414 !important; border: 1px solid #2a2a2a !important; border-radius: 10px !important; }
    .card.bg-light .card-body { background: transparent; padding: 0.8rem 1rem !important; }
    .card.bg-light .fw-bold.text-primary { color: #60b0ff !important; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .btn-outline-primary { color: #60b0ff; border-color: #2b4870; font-size: 0.8rem; cursor: pointer; }
    .btn-outline-primary:hover { background: #2b7fff; border-color: #2b7fff; color: #fff; }
    .btn-check:checked+.btn-outline-primary { background: #2b7fff; border-color: #2b7fff; color: #fff; }
    .form-check-input:not(:checked) {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
    }
    .form-check-input:checked {
        background-color: #2b7fff !important;
        border-color: #2b7fff !important;
    }
    .form-check-input {
        transition: background-color 0.3s ease, border-color 0.3s ease;
        cursor: pointer;
    }
/* Botón Hogar (verde/sutil) */
.btn-home {
    background: #e8f5e9;
    color: #1e5a3a;
    border-color: #a5d6a7;
}

.btn-home:hover {
    background: #c8e6c9;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(46, 125, 50, 0.2);
    border-color: #66bb6a;
}

.btn-home:active {
    transform: translateY(0px);
    box-shadow: 0 2px 4px rgba(46, 125, 50, 0.1);
}
.btn-success i {
    margin-right: 6px;
}
</style>
</head>
<body>

<!-- ============================================================ -->
<!-- PANTALLA DE BIENVENIDA -->
<!-- ============================================================ -->
<div id="pantallaBienvenida">
    <div class="logo-fondo-animado">
        <div class="logo-fondo-rotacion">
            <img src="LogoTN.png" alt="Logo TransNuBet Fondo">
            <img src="LogoTN.png" alt="Logo TransNuBet Fondo" class="logo-duplicado">
        </div>
        <div class="logo-fondo-rotacion-inversa">
            <img src="LogoTN.png" alt="Logo TransNuBet Fondo">
            <img src="LogoTN.png" alt="Logo TransNuBet Fondo" class="logo-duplicado">
        </div>
    </div>
    <div class="contenido-bienvenida">
        <div style="display:flex; justify-content:center; align-items:center; gap:20px; margin-bottom:1.5rem; position:relative; z-index:2;">
            <img src="favicon.png" alt="TransNuBet" style="height:120px; width:auto; border-radius:12px; filter:drop-shadow(0 4px 20px rgba(43,127,255,0.3));">
            <img src="LogoTN.png" alt="Logo TransNuBet" style="height:120px; width:auto; border-radius:12px; filter:drop-shadow(0 4px 20px rgba(43,127,255,0.3));">
        </div>
        <h1 style="position:relative; z-index:2; font-size:2rem; line-height:1.3;">
            Sistema de Cálculo de la<br>
            <span style="color:#60b0ff;">Ficha de Costo</span>
        </h1>
        <div style="position:relative; z-index:2; margin: 0.5rem 0 1.5rem 0;">
            <div style="font-size:1.3rem; font-weight:bold; color:#f0f0f0; background: linear-gradient(135deg, rgba(43,127,255,0.15), rgba(43,127,255,0.05)); padding:0.8rem 1.5rem; border-radius:12px; border:1px solid rgba(43,127,255,0.2); display:inline-block;">
                para el cobro de los <span style="color:#60b0ff;">SERVICIOS DE IMPRESIÓN EN PAPEL ADHESIVO A4</span>
            </div>
        </div>
        <div class="subtitle" style="position:relative; z-index:2;">
            <i class="fas fa-file-invoice me-2" style="color:#60b0ff;"></i>
            Resolución 148/2023 MFP · Ministerio de Finanzas y Precios<br>
            PROYECTO DE DESARROLLO LOCAL <b style="color:#60b0ff;">TransNuBeT</b>
        </div>
        <p style="color:#8a8a8a; font-size:0.95rem; margin-bottom: 2rem; position:relative; z-index:2;">
            Complete todos los datos en el formulario para generar la ficha de costo<br>
        </p>
        <button class="btn-w11-primary" id="btnIniciarSistema" style="position:relative; z-index:2;">
            <i class="fas fa-play"></i> Configurar y generar
        </button>
<button class="btn btn-outline-success" onclick="window.location.href='/'">
    <i class="fas fa-home"></i> Regresar a Ecocistema Financiero
</button>
        <div class="version" style="position:relative; z-index:2;">
            <i class="fas fa-shield-alt me-1"></i> TransNuBet · v3.0 · Cálculos de Costos
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- CONTENIDO PRINCIPAL -->
<!-- ============================================================ -->
<div id="contenidoPrincipal">

    <div class="card-main">

<!-- HEADER -->
<div class="header">
    <div>
        <h2><i class="fas fa-print me-2"></i>SISTEMA DE FICHA DE COSTO</h2>
        <p class="text-muted mb-0">Ministerio de Finanzas y Precios - Resolución 148/2023 MFP</p>
        <p class="text-muted mb-0" style="font-size:0.9rem;"><strong>Sistema Elaborado por: TransNuBet - Costos</strong></p>
    </div>
    <div class="no-print d-flex gap-2 flex-wrap align-items-center">
        <button class="btn-metodologia" id="btnMetodologia" title="Ver metodología de cálculo">
            <i class="fas fa-book-open"></i> Metodología
        </button>
        <button class="btn-accion-header" id="btnRecalcular" title="Volver a configurar y recalcular">
            <i class="fas fa-sync-alt"></i> Recalcular
        </button>
        <div class="dropdown d-inline-block">
            <button class="btn btn-success dropdown-toggle" type="button" id="dropdownExportar" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius:10px; font-weight:500; padding:0.4rem 1.2rem; font-size:0.85rem;">
                <i class="fas fa-file-export me-1"></i> Exportar
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownExportar" style="background:#1c1c1c; border:1px solid #2a2a2a; min-width:200px; border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,0.6); padding:0.3rem 0;">
                <li>
                    <button class="dropdown-item" id="btnExportarExcel" style="color:#e4e4e4; padding:0.5rem 1.2rem; font-size:0.85rem; transition:0.2s;">
                        <i class="fas fa-file-excel me-2" style="color:#28a745; width:20px; text-align:center;"></i> Exportar a Excel
                    </button>
                </li>
                <li>
                    <button class="dropdown-item" id="btnExportarPdfOficial" style="color:#e4e4e4; padding:0.5rem 1.2rem; font-size:0.85rem; transition:0.2s;">
                        <i class="fas fa-file-pdf me-2" style="color:#dc3545; width:20px; text-align:center;"></i> Exportar a PDF
                    </button>
                </li>
                <li>
                    <button class="dropdown-item" id="btnImprimir" style="color:#e4e4e4; padding:0.5rem 1.2rem; font-size:0.85rem; transition:0.2s;">
                        <i class="fas fa-print me-2" style="color:#ffc107; width:20px; text-align:center;"></i> Imprimir
                    </button>
                </li>
                <li><hr class="dropdown-divider" style="border-color:#2a2a2a; margin:0.2rem 0;"></li>
                <li>
                    <button class="dropdown-item" id="btnExportarCSV" style="color:#e4e4e4; padding:0.5rem 1.2rem; font-size:0.85rem; transition:0.2s;">
                        <i class="fas fa-file-csv me-2" style="color:#17a2b8; width:20px; text-align:center;"></i> Exportar a CSV
                    </button>
                </li>
            </ul>
        </div>
        <button class="btn-accion-header" id="btnVolverInicio" title="Volver a la página de inicio">
            <i class="fas fa-undo-alt"></i> Volver al inicio
        </button>
    </div>
</div>

<!-- FORMULARIO OCULTO -->
<form method="POST" action="" id="formPrincipal" class="no-print">
    <input type="hidden" name="action" value="generate">
    <input type="hidden" name="periodo" id="hidden_periodo" value="<?= htmlspecialchars($periodo) ?>">
    <input type="hidden" name="organismo" id="hidden_organismo" value="<?= htmlspecialchars($organismo) ?>">
    <input type="hidden" name="empresa" id="hidden_empresa" value="<?= htmlspecialchars($empresa) ?>">
    <input type="hidden" name="provincia" id="hidden_provincia" value="<?= htmlspecialchars($provincia) ?>">
    <input type="hidden" name="nombre_servicio" id="hidden_nombre_servicio" value="<?= htmlspecialchars($nombre_servicio) ?>">
    <input type="hidden" name="codigo_servicio" id="hidden_codigo_servicio" value="<?= htmlspecialchars($codigo_servicio) ?>">
    <input type="hidden" name="categoria" id="hidden_categoria" value="<?= htmlspecialchars($categoria) ?>">
    <input type="hidden" name="nivel_produccion" id="hidden_nivel_produccion" value="<?= htmlspecialchars($nivel_produccion) ?>">
    <input type="hidden" name="porcentaje_capacidad" id="hidden_porcentaje_capacidad" value="<?= htmlspecialchars($porcentaje_capacidad) ?>">
    <input type="hidden" name="fecha_emision" id="hidden_fecha_emision" value="<?= htmlspecialchars($fecha_emision_input) ?>">
    <input type="hidden" name="vigencia_valor" id="hidden_vigencia_valor" value="<?= $vigencia_valor ?>">
    <input type="hidden" name="vigencia_unidad" id="hidden_vigencia_unidad" value="<?= htmlspecialchars($vigencia_unidad) ?>">
    <input type="hidden" name="capacidad" id="hidden_capacidad" value="<?= $hojas_por_dia_base ?>">
    <input type="hidden" name="produccion_terminada" id="hidden_produccion_terminada" value="<?= floatval($produccion_terminada) ?>">
    <input type="hidden" name="dias_trabajo" id="hidden_dias_trabajo" value="<?= $dias_trabajo ?>">
    <input type="hidden" name="salario_trabajador" id="hidden_salario_trabajador" value="<?= number_format($salario_por_trabajador, 2, '.', '') ?>">
    <input type="hidden" name="num_trabajadores" id="hidden_num_trabajadores" value="<?= $num_trabajadores ?>">
    <input type="hidden" name="tope_ss" id="hidden_tope_ss" value="<?= number_format($tope_seguridad_social, 2, '.', '') ?>">
    <input type="hidden" name="margen_utilidad" id="hidden_margen_utilidad" value="<?= number_format($margen_utilidad_config, 2, '.', '') ?>">
    <input type="hidden" name="costo_fijo_hoja" id="hidden_costo_fijo_hoja" value="<?= $costo_fijo_hoja ?>">
    <input type="hidden" name="costo_fijo_valor" id="hidden_costo_fijo_valor" value="<?= number_format($costo_fijo_valor, 2, '.', '') ?>">
    <input type="hidden" name="energia" id="hidden_energia" value="<?= number_format($gasto_energia_mes, 2, '.', '') ?>">
    <input type="hidden" name="agua" id="hidden_agua" value="<?= number_format($gasto_agua_mes, 2, '.', '') ?>">
    <input type="hidden" name="gasto_transportacion" id="hidden_gasto_transportacion" value="<?= number_format($gasto_transportacion, 2, '.', '') ?>">
    <input type="hidden" name="precio_adhesivo" id="hidden_precio_adhesivo" value="<?= number_format($precio_adhesivo_paquete, 2, '.', '') ?>">
    <input type="hidden" name="uds_adhesivo" id="hidden_uds_adhesivo" value="<?= $uds_adhesivo_paquete ?>">
    <input type="hidden" name="precio_tinta" id="hidden_precio_tinta" value="<?= number_format($precio_kit_tinta, 2, '.', '') ?>">
    <input type="hidden" name="duracion_kit_tinta" id="hidden_duracion_kit" value="<?= number_format($duracion_kit_tinta, 2, '.', '') ?>">
    <input type="hidden" name="valor_activo" id="hidden_valor_activo" value="<?= number_format($valor_activo_impresora, 2, '.', '') ?>">
    <input type="hidden" name="depreciacion" id="hidden_depreciacion" value="<?= number_format($porcentaje_depreciacion_anual, 2, '.', '') ?>">
    <input type="hidden" name="imp_ventas" id="hidden_imp_ventas" value="<?= number_format($tasa_impuesto_ventas, 2, '.', '') ?>">
    <input type="hidden" name="contrib_local" id="hidden_contrib_local" value="<?= number_format($tasa_contribucion_local, 2, '.', '') ?>">
    <input type="hidden" name="imp_gobierno" id="hidden_imp_gobierno" value="<?= number_format($porcentaje_utilidad_gobierno, 2, '.', '') ?>">
    <input type="hidden" name="otros_gastos" id="hidden_otros_gastos" value="<?= number_format($porcentaje_otros_gastos, 2, '.', '') ?>">
    <input type="hidden" name="vacaciones" id="hidden_vacaciones" value="<?= number_format($porcentaje_vacaciones, 2, '.', '') ?>">
    <input type="hidden" name="fuerza_trabajo" id="hidden_fuerza_trabajo" value="<?= number_format($porcentaje_fuerza_trabajo, 2, '.', '') ?>">
    <input type="hidden" name="manual_totales" id="hidden_manual_totales" value="<?= $manual_totales ?>">
    <input type="hidden" name="salario_total_m" id="hidden_salario_total_m" value="<?= isset($_POST['salario_total_m']) ? number_format($_POST['salario_total_m'], 2, '.', '') : number_format($salario_total_ref, 2, '.', '') ?>">
    <input type="hidden" name="ss_total_m" id="hidden_ss_total_m" value="<?= isset($_POST['ss_total_m']) ? number_format($_POST['ss_total_m'], 2, '.', '') : number_format($seguridad_social_total_ref, 2, '.', '') ?>">
    <input type="hidden" name="vacaciones_total_m" id="hidden_vacaciones_total_m" value="<?= isset($_POST['vacaciones_total_m']) ? number_format($_POST['vacaciones_total_m'], 2, '.', '') : number_format($vacaciones_total_ref, 2, '.', '') ?>">
    <input type="hidden" name="fuerza_total_m" id="hidden_fuerza_total_m" value="<?= isset($_POST['fuerza_total_m']) ? number_format($_POST['fuerza_total_m'], 2, '.', '') : number_format($impuesto_fuerza_trabajo_ref, 2, '.', '') ?>">
    <input type="hidden" name="depreciacion_total_m" id="hidden_depreciacion_total_m" value="<?= isset($_POST['depreciacion_total_m']) ? number_format($_POST['depreciacion_total_m'], 2, '.', '') : number_format($depreciacion_periodo_ref, 2, '.', '') ?>">
    <input type="hidden" name="factor_capacidad" id="hidden_factor_capacidad" value="<?= number_format($factor_capacidad, 2, '.', '') ?>">
    <input type="hidden" name="ocultar_ceros" id="hidden_ocultar_ceros" value="<?= $ocultar_ceros ?>">
    <div class="col-md-12 text-end mt-3" style="display:none;">
        <button type="submit" class="btn btn-primary btn-lg" id="btnSubmitOculto">
            <i class="fas fa-play me-2"></i>Generar Ficha de Costo
        </button>
    </div>
</form>

<!-- ============================================================ -->
<!-- RESULTADOS HTML -->
<!-- ============================================================ -->
<?php if (isset($_POST['action']) && $_POST['action'] == 'generate'): ?>
<div class="print-running-header" aria-hidden="true">Ficha de Costo — Res. 148/2023 MFP · SISCOSTO PDL TransNuBeT</div>
<div class="print-page-number" aria-hidden="true"></div>
<div class="ficha-container" id="fichaParaImprimir">
    <div style="text-align:center; margin-bottom:15px;">
        <div style="font-size:14pt; font-weight:bold; color:#f0f0f0;">MINISTERIO DE FINANZAS Y PRECIOS</div>
        <div style="font-size:12pt; font-weight:bold; color:#f0f0f0;">FICHA DE COSTOS Y GASTOS DE PRODUCTOS Y SERVICIOS - EVALUACIÓN DE PRECIOS Y TARIFAS</div>
        <div style="font-size:10pt; color:#b0b0b0;">
            Resolución: 148/2023 MFP   |   Período: <?= $periodo_texto ?>   |   
            Emisión: <?= $fecha_emision ?>   |   Vigencia: <?= $fecha_vigencia ?> 
            (<?= $vigencia_valor ?> <?= $vigencia_unidad ?>)
        </div>
    </div>
    <div style="font-size:10pt; margin-bottom:5px; color:#e4e4e4;">
        <strong>Producto o Servicio:</strong> <?= $nombre_servicio ?> &nbsp;&nbsp;
        <strong>Código:</strong> <?= $codigo_servicio ?>
    </div>
    <div style="font-size:9pt; margin-bottom:5px; color:#b0b0b0;">
        <strong>UM:</strong> Hoja A4 &nbsp;&nbsp;
        <strong>Nivel Producción:</strong> <?= $nivel_produccion ?> &nbsp;&nbsp;
        <strong>% Capacidad:</strong> <?= $porcentaje_capacidad ?> &nbsp;&nbsp;
        <strong>Categoría:</strong> <?= $categoria ?> &nbsp;&nbsp;
        <strong>Cantidad de Producción Terminada:</strong> <?= number_format($total_hojas_periodo, 0) ?> hojas
    </div>
    <div style="font-size:9pt; margin-bottom:5px; color:#b0b0b0;">
        <strong>Organismo:</strong> <?= $organismo ?> &nbsp;&nbsp;
        <strong>Empresa:</strong> <?= $empresa ?>
    </div>
</div>
<table class="ficha">
    <thead>
    <tr>
        <th style="width:45%; text-align:left;">CONCEPTOS</th>
        <th style="width:10%;">Fila</th>
        <th style="width:22.5%;">Costo Base</th>
        <th style="width:22.5%;">Costo Nuevo</th>
    </tr>
    </thead>
    <tbody>

    <!-- INGRESOS - SIEMPRE VISIBLES -->
    <tr class="bg-green-light">
        <td class="concepto text-success-dark">INGRESOS POR VENTAS</td>
        <td class="text-center">-</td>
        <td class="text-end fw-bold text-success-dark">$ <?= number_format($ingresos_totales, 2) ?></td>
        <td class="text-end fw-bold text-success-dark">$ <?= number_format($ingresos_totales, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">Ingreso por hoja</td>
        <td class="text-center">I.1</td>
        <td class="text-end">$ <?= number_format($ingreso_por_hoja, 2) ?></td>
        <td class="text-end">$ <?= number_format($ingreso_por_hoja, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">Ingreso total</td>
        <td class="text-center">I.2</td>
        <td class="text-end">$ <?= number_format($ingresos_totales, 2) ?></td>
        <td class="text-end">$ <?= number_format($ingresos_totales, 2) ?></td>
    </tr>

    <!-- GASTOS DIRECTOS - SIEMPRE VISIBLE -->
    <tr>
        <td class="concepto">GASTOS DIRECTOS</td>
        <td class="text-center">-</td>
        <td class="text-end">-</td>
        <td class="text-end">-</td>
    </tr>

    <!-- FILA 1: GASTO MATERIAL - SIEMPRE VISIBLE -->
    <tr>
        <td class="concepto">Gasto Material</td>
        <td class="text-center">1</td>
        <td class="text-end">$ <?= number_format($gasto_material_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_material_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">De ello: Insumos (Materias primas)</td>
        <td class="text-center">1.1</td>
        <td class="text-end">$ <?= number_format($gasto_material_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_material_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">De ello, Papel adhesivo y tinta</td>
        <td class="text-center">1.2</td>
        <td class="text-end">$ <?= number_format($gasto_papel_adhesivo + $costo_tinta, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_papel_adhesivo + $costo_tinta, 2) ?></td>
    </tr>
    
    <!-- FILA 1.3: ENERGÍA - OCULTABLE -->
    <?php if (mostrarFila($gasto_energia_periodo, $ocultar_ceros)): ?>
    <tr>
        <td class="indent">Energía</td>
        <td class="text-center">1.3</td>
        <td class="text-end">$ <?= number_format($gasto_energia_periodo, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_energia_periodo, 2) ?></td>
    </tr>
    <?php endif; ?>
    
    <!-- FILA 1.4: AGUA - OCULTABLE -->
    <?php if (mostrarFila($gasto_agua_periodo, $ocultar_ceros)): ?>
    <tr>
        <td class="indent">Agua</td>
        <td class="text-center">1.4</td>
        <td class="text-end">$ <?= number_format($gasto_agua_periodo, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_agua_periodo, 2) ?></td>
    </tr>
    <?php endif; ?>

    <!-- FILA 2: SALARIO - SIEMPRE VISIBLE -->
    <tr>
        <td class="concepto">Salario Directo o retribución</td>
        <td class="text-center">2</td>
        <td class="text-end">$ <?= number_format($salario_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($salario_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">De ello, salarios</td>
        <td class="text-center">2.1</td>
        <td class="text-end">$ <?= number_format($salario_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($salario_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent2" style="color:#60b0ff;"><?php if ($manual_totales): ?>Salario Total ingresado manualmente<?php else: ?>Salario fijo por trabajador (<?= number_format($salario_por_trabajador, 2) ?> x <?= $num_trabajadores ?>)<?php endif; ?></td>
        <td class="text-center">-</td>
        <td class="text-end">-</td>
        <td class="text-end">-</td>
    </tr>

    <!-- FILA 3: OTROS GASTOS DIRECTOS (TRANSPORTACIÓN) - OCULTABLE -->
    <?php if (mostrarFila($gasto_transportacion_periodo, $ocultar_ceros)): ?>
    <tr>
        <td class="concepto">Otros Gastos Directos (Transportación)</td>
        <td class="text-center">3</td>
        <td class="text-end">$ <?= number_format($gasto_transportacion_periodo, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_transportacion_periodo, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">Transportación del período</td>
        <td class="text-center">3.1</td>
        <td class="text-end">$ <?= number_format($gasto_transportacion_periodo, 2) ?></td>
        <td class="text-end">$ <?= number_format($gasto_transportacion_periodo, 2) ?></td>
    </tr>
    <?php endif; ?>

    <!-- FILA 4: GASTOS ASOCIADOS - SIEMPRE VISIBLE -->
    <tr>
        <td class="concepto">Gastos asociados a la producción (Depreciación)</td>
        <td class="text-center">4</td>
        <td class="text-end">$ <?= number_format($seguridad_social_total + $vacaciones_total + $impuesto_fuerza_trabajo + $depreciacion_periodo, 2) ?></td>
        <td class="text-end">$ <?= number_format($seguridad_social_total + $vacaciones_total + $impuesto_fuerza_trabajo + $depreciacion_periodo, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">De ello, Seguridad Social</td>
        <td class="text-center">4.1</td>
        <td class="text-end">$ <?= number_format($seguridad_social_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($seguridad_social_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent2"><?php if ($manual_totales): ?>Seguridad Social Total ingresada manualmente<?php else: ?>5% hasta $5,000 por trabajador + 10% del excedente<?php endif; ?></td>
        <td class="text-center">-</td>
        <td class="text-end">-</td>
        <td class="text-end">-</td>
    </tr>
    <tr>
        <td class="indent">De ello, Vacaciones (<?= number_format($porcentaje_vacaciones, 2) ?>%)</td>
        <td class="text-center">4.2</td>
        <td class="text-end">$ <?= number_format($vacaciones_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($vacaciones_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">De ello, Fuerza de Trabajo (<?= number_format($porcentaje_fuerza_trabajo, 2) ?>%)</td>
        <td class="text-center">4.3</td>
        <td class="text-end">$ <?= number_format($impuesto_fuerza_trabajo, 2) ?></td>
        <td class="text-end">$ <?= number_format($impuesto_fuerza_trabajo, 2) ?></td>
    </tr>
    <tr>
        <td class="indent">De ello, Depreciación (<?= $porcentaje_depreciacion_anual ?>% anual)</td>
        <td class="text-center">4.4</td>
        <td class="text-end">$ <?= number_format($depreciacion_periodo, 2) ?></td>
        <td class="text-end">$ <?= number_format($depreciacion_periodo, 2) ?></td>
    </tr>

    <!-- FILA 5: COSTO TOTAL - SIEMPRE VISIBLE -->
    <tr class="bg-gray-light">
        <td class="concepto">COSTO TOTAL (1+2+3+4)</td>
        <td class="text-center">5</td>
        <td class="text-end fw-bold">$ <?= number_format($subtotal_gastos_directos, 2) ?></td>
        <td class="text-end fw-bold">$ <?= number_format($subtotal_gastos_directos, 2) ?></td>
    </tr>

    <!-- FILAS 6-10: GASTOS INDIRECTOS - OCULTABLES (todas valor 0) -->
    <?php if (mostrarFila(0, $ocultar_ceros)): ?>
    <tr>
        <td class="concepto">Gastos Generales y de Administración</td>
        <td class="text-center">6</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <tr>
        <td class="indent">De ello, salarios</td>
        <td class="text-center">6.1</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <tr>
        <td class="concepto">Gastos de Distribución y Venta</td>
        <td class="text-center">7</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <tr>
        <td class="indent">De ello, salarios</td>
        <td class="text-center">7.1</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <tr>
        <td class="concepto">Gastos Financieros</td>
        <td class="text-center">8</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <tr>
        <td class="concepto">Gastos Financiamiento OSDE</td>
        <td class="text-center">9</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <tr>
        <td class="concepto">Gastos Tributarios (Seg. Social e Impuestos)</td>
        <td class="text-center">10</td>
        <td class="text-end">$ 0.00</td>
        <td class="text-end">$ 0.00</td>
    </tr>
    <?php endif; ?>

    <!-- FILA 11: TOTAL GASTOS - SIEMPRE VISIBLE -->
    <tr class="bg-gray-light">
        <td class="concepto">TOTAL DE GASTOS (6 al 10)</td>
        <td class="text-center">11</td>
        <td class="text-end fw-bold">$ 0.00</td>
        <td class="text-end fw-bold">$ 0.00</td>
    </tr>

    <!-- FILA 12: TOTAL COSTOS Y GASTOS - SIEMPRE VISIBLE -->
    <tr class="bg-light">
        <td class="concepto">TOTAL DE COSTOS Y GASTOS (5+11)</td>
        <td class="text-center">12</td>
        <td class="text-end fw-bold">$ <?= number_format($subtotal_gastos_directos, 2) ?></td>
        <td class="text-end fw-bold">$ <?= number_format($subtotal_gastos_directos, 2) ?></td>
    </tr>

    <!-- FILA 13.1: OTROS GASTOS - SIEMPRE VISIBLE -->
    <tr>
        <td class="concepto">Otros Gastos (Reparación y Mantenimiento)</td>
        <td class="text-center">13.1</td>
        <td class="text-end">$ <?= number_format($costo_otros_gastos_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($costo_otros_gastos_total, 2) ?></td>
    </tr>
    <tr>
        <td class="indent"><?= number_format($porcentaje_otros_gastos, 0) ?>% sobre el costo total</td>
        <td class="text-center">13.1</td>
        <td class="text-end">$ <?= number_format($costo_otros_gastos_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($costo_otros_gastos_total, 2) ?></td>
    </tr>

    <!-- FILA 14: BASE IMPONIBLE - SIEMPRE VISIBLE -->
    <tr class="bg-light">
        <td class="concepto">BASE IMPONIBLE (12+13.1)</td>
        <td class="text-center">14</td>
        <td class="text-end fw-bold">$ <?= number_format($base_imponible, 2) ?></td>
        <td class="text-end fw-bold">$ <?= number_format($base_imponible, 2) ?></td>
    </tr>

    <!-- FILA 15: IMPUESTO VENTAS - SIEMPRE VISIBLE -->
    <tr>
        <td class="concepto">ISV - Impuesto sobre Ventas (<?= number_format($tasa_impuesto_ventas, 0) ?>%)</td>
        <td class="text-center">15</td>
        <td class="text-end">$ <?= number_format($impuesto_ventas_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($impuesto_ventas_total, 2) ?></td>
    </tr>

    <!-- FILA 16: CONTRIBUCIÓN LOCAL - OCULTABLE (si es 0) -->
    <?php if (mostrarFila($contribucion_local_total, $ocultar_ceros)): ?>
    <tr>
        <td class="concepto">Contribución Local (<?= number_format($tasa_contribucion_local, 0) ?>%)</td>
        <td class="text-center">16</td>
        <td class="text-end">$ <?= number_format($contribucion_local_total, 2) ?></td>
        <td class="text-end">$ <?= number_format($contribucion_local_total, 2) ?></td>
    </tr>
    <?php endif; ?>

    <!-- FILA 13: UTILIDAD ANTES IMPUESTO - SIEMPRE VISIBLE -->
    <tr class="bg-green-light">
        <td class="concepto text-success-dark" style="font-size:11pt;">Utilidad antes de Impuesto Gubernamental</td>
        <td class="text-center">13</td>
        <td class="text-end fw-bold text-success-dark">$ <?= number_format($utilidad_antes_gobierno, 2) ?></td>
        <td class="text-end fw-bold text-success-dark">$ <?= number_format($utilidad_antes_gobierno, 2) ?></td>
    </tr>

    <!-- FILA 18: IMPUESTO GUBERNAMENTAL - SIEMPRE VISIBLE -->
    <tr class="bg-yellow-light">
        <td class="concepto" style="color:#c8a840;">Impuesto Gubernamental (<?= number_format($porcentaje_utilidad_gobierno, 0) ?>% de utilidades)</td>
        <td class="text-center">18</td>
        <td class="text-end fw-bold" style="color:#c8a840;">$ <?= number_format($utilidad_para_gobierno, 2) ?></td>
        <td class="text-end fw-bold" style="color:#c8a840;">$ <?= number_format($utilidad_para_gobierno, 2) ?></td>
    </tr>

    <!-- FILA 19: TOTAL COSTOS Y GASTOS - SIEMPRE VISIBLE -->
    <tr class="bg-gray-light">
        <td class="concepto">TOTAL DE COSTOS Y GASTOS (14+15+16+18)</td>
        <td class="text-center">19</td>
        <td class="text-end fw-bold">$ <?= number_format($total_gastos_final, 2) ?></td>
        <td class="text-end fw-bold">$ <?= number_format($total_gastos_final, 2) ?></td>
    </tr>

    <!-- FILA 20: PRECIO TARIFA TOTAL - SIEMPRE VISIBLE -->
    <tr class="bg-blue-light">
        <td class="concepto text-blue-dark" style="font-size:11pt;">PRECIO O TARIFA TOTAL (14+15+16+17)</td>
        <td class="text-center">20</td>
        <td class="text-end fw-bold text-blue-dark">$ <?= number_format($ingresos_totales, 2) ?></td>
        <td class="text-end fw-bold text-blue-dark">$ <?= number_format($ingresos_totales, 2) ?></td>
    </tr>

    <!-- FILA 21: UTILIDAD NETA - SIEMPRE VISIBLE -->
    <tr class="bg-green-light">
        <td class="concepto text-success-dark" style="font-size:14pt;">UTILIDAD NETA (13 - 18)</td>
        <td class="text-center">21</td>
        <td class="text-end fw-bold text-success-dark" style="font-size:14pt;">$ <?= number_format($utilidad_neta_total, 2) ?></td>
        <td class="text-end fw-bold text-success-dark" style="font-size:14pt;">$ <?= number_format($utilidad_neta_total, 2) ?></td>
    </tr>

    <!-- FILA 22: PRECIO UNITARIO - SIEMPRE VISIBLE -->
    <tr class="bg-blue-light">
        <td class="concepto text-blue-dark" style="font-size:14pt;">PRECIO UNITARIO POR HOJA</td>
        <td class="text-center">22</td>
        <td class="text-end fw-bold text-blue-dark" style="font-size:14pt;">$ <?= number_format($precio_venta_final, 2) ?></td>
        <td class="text-end fw-bold text-blue-dark" style="font-size:14pt;">$ <?= number_format($precio_venta_final, 2) ?></td>
    </tr>

    <!-- PIE DE TABLA - SIEMPRE VISIBLE -->
    <tr>
        <td colspan="4" style="text-align:center; font-weight:bold; background:#1a1a1a; color:#b0b0b0;">Datos sobre precios de referencia</td>
    </tr>
    <tr>
        <td></td>
        <td class="text-center" style="color:#b0b0b0;">Plan/Anterior</td>
        <td class="text-center" style="color:#b0b0b0;">Precio Actual</td>
        <td></td>
    </tr>
    </tbody>
</table>

<div style="margin-top:20px; display:flex; justify-content:space-between; border-top:1px solid #2a2a2a; padding-top:10px; font-size:9pt; color:#b0b0b0;">
    <div>
        <strong>Elaborado por:</strong> ____________________<br>
        <strong>Fecha:</strong> <?= $fecha_emision ?>
    </div>
    <div style="text-align:right;">
        <strong>Aprobado por:</strong> ____________________<br>
        <strong>Fecha:</strong> <?= $fecha_vigencia ?>
    </div>
</div>
<div class="footer">SISCOSTO PDL TransNuBeT - % Gastos Indirectos: 40% | Res. 148/2023 MFP</div>
</div>

<!-- RESUMEN DE RENTABILIDAD -->
<div class="page-break">
    <div class="resumen-box-separado" id="resumenParaImprimir">
        <h4><i class="fas fa-chart-pie me-2"></i>RESUMEN DE RENTABILIDAD (<?= strtoupper($periodo) ?>)</h4>
        <div class="row">
            <div class="col-md-4"><span class="label">📄 Hojas Impresas por Día:</span> <span class="value text-success"><?= number_format($hojas_por_dia) ?></span></div>
            <div class="col-md-4"><span class="label">📦 Producción Terminada (período):</span> <span class="value text-success"><?= number_format($total_hojas_periodo) ?> hojas</span></div>
            <div class="col-md-4"><span class="label">💰 Ingreso por Hoja:</span> <span class="value text-success">$ <?= number_format($ingreso_por_hoja, 2) ?></span></div>
        </div>
        <div class="row">
            <div class="col-md-4"><span class="label">📊 Costo Total (1+2+3+4):</span> <span class="value text-warning">$ <?= number_format($subtotal_gastos_directos, 2) ?></span></div>
            <div class="col-md-4"><span class="label">📊 Base Imponible:</span> <span class="value text-info">$ <?= number_format($base_imponible, 2) ?></span></div>
            <div class="col-md-4"><span class="label">📈 Utilidad Neta (fila 21):</span> <span class="value text-success">$ <?= number_format($utilidad_neta_total, 2) ?></span></div>
        </div>
        <div class="row">
            <div class="col-md-4"><span class="label">📊 Depreciación:</span> <span class="value text-warning">$ <?= number_format($depreciacion_periodo, 2) ?></span></div>
            <div class="col-md-4"><span class="label">🏛️ Impuesto Gobierno:</span> <span class="value text-warning">$ <?= number_format($utilidad_para_gobierno, 2) ?></span></div>
            <div class="col-md-4"><span class="label">📊 Margen de Utilidad:</span> <span class="value badge bg-success" style="font-size:14px;"><?= number_format($margen_utilidad, 1) ?>%</span></div>
        </div>
        <div class="row">
            <div class="col-md-4"><span class="label">💰 Precio de Equilibrio:</span> <span class="value text-info">$ <?= number_format($precio_equilibrio, 2) ?></span></div>
            <div class="col-md-4"><span class="label">📊 Costo por Hoja:</span> <span class="value text-warning">$ <?= number_format($costo_por_hoja, 2) ?></span></div>
            <div class="col-md-4"><span class="label">💵 Utilidad por Hoja:</span> <span class="value text-success">$ <?= number_format($utilidad_por_hoja, 2) ?></span></div>
        </div>
        <div style="margin-top:20px; border-top:2px solid #2b7fff; padding-top:15px; font-size:10pt; color:#b0c4de; text-align:center;">
            <strong>Emisión:</strong> <?= $fecha_emision ?> &nbsp;|&nbsp; <strong>Vigencia:</strong> <?= $fecha_vigencia ?>
        </div>
    </div>
</div>

<!-- ANEXO: DESAGREGACIÓN DE LOS INSUMOS (Res. 148/2023 MFP) -->
<div class="anexo-container">
    <div>
        <h4 style="color:#2b7fff; font-weight:bold; font-size:12pt; margin-bottom:4px; margin-top:25px;"><i class="fas fa-boxes me-2"></i>ANEXO 2 — DESAGREGACIÓN DE LOS INSUMOS (Res. 148/2023 MFP)</h4>
        <p style="font-size:8.5pt; color:#b0b0b0; margin-bottom:8px;">
            Concepto 1.1 de la Ficha de Costos. Resolución 148/2023 del Ministerio de Finanzas y Precios — Metodología para la elaboración de la ficha de costos y gastos de productos y servicios para la evaluación de precios y tarifas (Gaceta Oficial No. 64 del 6 de julio de 2023). Período: <?= strtoupper($periodo) ?>.
        </p>
        <table class="ficha">
            <tr>
                <th style="width:6%;">No.</th>
                <th style="width:44%; text-align:left;">Denominación</th>
                <th style="width:10%;">UM</th>
                <th style="width:13%;">Cantidad<small><br>(período)</small></th>
                <th style="width:13.5%;">Precio Unitario</th>
                <th style="width:13.5%;">Importe</th>
            </tr>
            <tr>
                <td class="text-center">1</td>
                <td class="indent">Papel adhesivo A4 (paquete de <?= number_format($uds_adhesivo_paquete, 0) ?> hojas)</td>
                <td class="text-center">Paquete</td>
                <td class="text-end"><?= number_format($paquetes_adhesivo, 0) ?></td>
                <td class="text-end">$ <?= number_format($precio_papel_unit, 2) ?></td>
                <td class="text-end">$ <?= number_format($gasto_papel_adhesivo, 2) ?></td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td class="indent">Tinta para impresión (dura <?= number_format($duracion_kit_tinta, 0) ?> meses)</td>
                <td class="text-center">Kit</td>
                <td class="text-end"><?= number_format($kits_tinta, 2) ?></td>
                <td class="text-end">$ <?= number_format($precio_tinta_unit, 2) ?></td>
                <td class="text-end">$ <?= number_format($costo_tinta, 2) ?></td>
            </tr>
            <tr>
                <td class="text-center">3</td>
                <td class="indent">Energía eléctrica (período)</td>
                <td class="text-center">Mes</td>
                <td class="text-end">1</td>
                <td class="text-end">$ <?= number_format($gasto_energia_periodo, 2) ?></td>
                <td class="text-end">$ <?= number_format($gasto_energia_periodo, 2) ?></td>
            </tr>
            <?php if (mostrarFila($gasto_agua_periodo, $ocultar_ceros)): ?>
            <tr>
                <td class="text-center">4</td>
                <td class="indent">Agua (período)</td>
                <td class="text-center">Mes</td>
                <td class="text-end">1</td>
                <td class="text-end">$ <?= number_format($gasto_agua_periodo, 2) ?></td>
                <td class="text-end">$ <?= number_format($gasto_agua_periodo, 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="bg-gray-light">
                <td class="text-end" colspan="5" style="font-weight:bold;">TOTAL INSUMOS (fila 1.1)</td>
                <td class="text-end fw-bold">$ <?= number_format($gasto_material_total, 2) ?></td>
            </tr>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
</div>

<!-- ============================================================ -->
<!-- MODAL DE CONFIGURACIÓN COMPLETO -->
<!-- ============================================================ -->
<div class="modal fade" id="modalCompleto" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 2px solid #2b7fff; padding: 1rem 1.8rem;">
                <h5 class="modal-title" id="modalLabel">
                    <i class="fas fa-print me-2" style="color:#60b0ff;"></i> 
                    <span style="color:#f0f0f0;">Configuración para el Cálculo del Costo</span>
                    <span style="display:block; font-size:0.7rem; color:#9a9a9a; font-weight:normal; margin-top:2px;">
                        <i class="fas fa-file-invoice me-1"></i> Resolución 148/2023 MFP
                    </span>
                </h5>
                <button type="button" class="btn-close" id="btnCerrarModal" aria-label="Cerrar" style="filter: invert(1) brightness(1.5); opacity:0.7;"></button>
            </div>
            <div class="modal-body" style="padding: 0.5rem 1.8rem 1rem 1.8rem; max-height: 75vh; overflow-y: auto;">
                <ul class="nav nav-tabs nav-fill mb-3" id="modalTabs" role="tablist" style="border-bottom: 2px solid #2a2a2a;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general" data-bs-toggle="tab" data-bs-target="#panel-general" type="button" role="tab" >
                            <i class="fas fa-cog me-2" style="color:#60b0ff;"></i> General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-operacion" data-bs-toggle="tab" data-bs-target="#panel-operacion" type="button" role="tab" >
                            <i class="fas fa-route me-2" style="color:#60b0ff;"></i> Operación
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-insumos" data-bs-toggle="tab" data-bs-target="#panel-insumos" type="button" role="tab" >
                            <i class="fas fa-gas-pump me-2" style="color:#60b0ff;"></i> Insumos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-porcentajes" data-bs-toggle="tab" data-bs-target="#panel-porcentajes" type="button" role="tab" >
                            <i class="fas fa-percent me-2" style="color:#60b0ff;"></i> Porcentajes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-importes" data-bs-toggle="tab" data-bs-target="#panel-importes" type="button" role="tab" >
                            <i class="fas fa-money-bill-wave me-2" style="color:#60b0ff;"></i> Importes Manuales
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- PESTAÑA GENERAL -->
                    <div class="tab-pane fade show active" id="panel-general" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-calendar-alt me-1" style="color:#60b0ff;"></i> Período</label>
                                <div class="btn-group w-100" role="group" style="height:32px;">
                                    <input type="radio" class="btn-check" name="modal_periodo" id="modal_periodo_diario" value="diario">
                                    <label class="btn btn-outline-primary btn-sm" for="modal_periodo_diario" style="font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:6px 0 0 6px;">
                                        <i class="fas fa-sun me-1"></i>Diario
                                    </label>
                                    <input type="radio" class="btn-check" name="modal_periodo" id="modal_periodo_mensual" value="mensual" checked>
                                    <label class="btn btn-outline-primary btn-sm" for="modal_periodo_mensual" style="font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:0 6px 6px 0;">
                                        <i class="fas fa-calendar-week me-1"></i>Mensual
                                    </label>
                                </div>
                                <span class="dias-indicador" id="modal_dias_indicador" style="font-size:0.65rem; color:#60b0ff; display:block; margin-top:3px;">
                                    <i class="fas fa-info-circle me-1"></i> 24 días laborados
                                </span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-calendar-check me-1" style="color:#60b0ff;"></i> Fecha Emisión</label>
                                <input type="date" class="form-control form-control-sm" id="modal_fecha_emision" value="<?= date('Y-m-d') ?>" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-clock me-1" style="color:#60b0ff;"></i> Vigencia</label>
                                <div class="d-flex gap-1">
                                    <select class="form-select form-select-sm" id="modal_vigencia_valor" style="width:60px; height:32px; font-size:0.8rem; padding:0.2rem 0.3rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                    </select>
                                    <select class="form-select form-select-sm" id="modal_vigencia_unidad" style="height:32px; font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0; flex:1;">
                                        <option value="DIAS">Días</option>
                                        <option value="MESES">Meses</option>
                                        <option value="ANOS" selected>Años</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-calendar-day me-1" style="color:#60b0ff;"></i> Días Laborados</label>
                                <input type="number" class="form-control form-control-sm" id="modal_dias_trabajo" value="24" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-chart-line me-1" style="color:#60b0ff;"></i> Capacidad de Producción</label>
                                <select class="form-select form-select-sm" id="modal_capacidad_produccion" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                    <option value="0.10">10% - Bajo Rendimiento</option>
                                    <option value="0.50">50% - Medio Rendimiento</option>
                                    <option value="1.00" selected>100% - Máximo Rendimiento</option>
                                </select>
                                <span style="font-size:0.6rem; color:#6a6a6a; display:block; margin-top:2px;">
                                    <i class="fas fa-info-circle me-1" style="color:#60b0ff;"></i> Afecta el nivel de producción y costos
                                </span>
                            </div>
                            <!-- CHECKBOX OCULTAR CEROS -->
                            <div class="col-md-4 mt-2">
                                <div class="form-check form-switch" style="padding-top: 8px;">
                                    <input class="form-check-input" type="checkbox" id="modal_ocultar_ceros" checked style="width: 40px; height: 20px; cursor: pointer; background-color: #2b7fff; border-color: #2b7fff;">
                                    <label class="form-check-label" for="modal_ocultar_ceros" style="color: #b0b0b0; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                        <i class="fas fa-eye-slash me-1" style="color: #60b0ff;"></i> Ocultar filas en cero
                                    </label>
                                    <span style="font-size: 0.6rem; color: #6a6a6a; display: block; margin-top: 2px; margin-left: 48px;">
                                        <i class="fas fa-info-circle me-1" style="color: #60b0ff;"></i> Las filas con valor $0.00 no se mostrarán
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-building me-1" style="color:#60b0ff;"></i> Organismo</label>
                                <input type="text" class="form-control form-control-sm" id="modal_organismo" value="OLPP" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-store me-1" style="color:#60b0ff;"></i> Empresa</label>
                                <input type="text" class="form-control form-control-sm" id="modal_empresa" value="PDL TransNuBeT" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-map-marker-alt me-1" style="color:#60b0ff;"></i> Provincia</label>
                                <input type="text" class="form-control form-control-sm" id="modal_provincia" value="CAMAGUEY" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-detalle"><i class="fas fa-tag me-1" style="color:#60b0ff;"></i> Producto o Servicio</label>
                                <input type="text" class="form-control form-control-sm" id="modal_nombre_servicio" value="IMPRESION DE STICKERS Y/O ETIQUETAS EN PAPEL ADHESIVO A4" style="height:32px; font-size:0.7rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-barcode me-1" style="color:#60b0ff;"></i> Código</label>
                                <input type="text" class="form-control form-control-sm" id="modal_codigo_servicio" value="IMP-PAPEL-ADHESIVO" style="height:32px; font-size:0.7rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-list me-1" style="color:#60b0ff;"></i> Categoría</label>
                                <input type="text" class="form-control form-control-sm" id="modal_categoria" value="IMPRESION DE DOCUMENTOS Y OTROS" style="height:32px; font-size:0.65rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-industry me-1" style="color:#60b0ff;"></i> Nivel Producción</label>
                                <input type="text" class="form-control form-control-sm" id="modal_nivel_produccion" value="1 - Prod. Terminada" style="height:32px; font-size:0.65rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                        </div>
                    </div>
                    <!-- PESTAÑA OPERACIÓN -->
                    <div class="tab-pane fade" id="panel-operacion" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-file-invoice me-1" style="color:#60b0ff;"></i> Hojas Impresas por Día</label>
                                <input type="number" class="form-control form-control-sm" id="modal_capacidad" value="1250" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">Hojas para las que se calcula la ficha</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-boxes me-1" style="color:#60b0ff;"></i> Cantidad Total de Hojas a Producir</label>
                                <input type="number" class="form-control form-control-sm" id="modal_produccion_terminada" value="30000" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">Producción terminada en el período (ej.: 30 000 al mes; 0 = calcular por capacidad × días)</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-user-tie me-1" style="color:#60b0ff;"></i> Salario por Trabajador ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_salario_trabajador" value="9100.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-users me-1" style="color:#60b0ff;"></i> Cantidad de Trabajadores</label>
                                <input type="number" class="form-control form-control-sm" id="modal_num_trabajadores" value="3" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-bolt me-1" style="color:#60b0ff;"></i> Electricidad ($/mes)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_energia" value="2356.85" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-water me-1" style="color:#60b0ff;"></i> Agua ($/mes)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_agua" value="0.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-truck me-1" style="color:#60b0ff;"></i> Transportación ($/mes)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_transporte" value="20360.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">Gasto de transportación del período</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-print me-1" style="color:#60b0ff;"></i> Valor Activo Impresora ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_valor_activo" value="294700.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-dollar-sign me-1" style="color:#60b0ff;"></i> Costo Fijo por Hoja ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_costo_fijo_valor" value="1275.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <div class="form-check mt-1 mb-0">
                                        <input type="checkbox" class="form-check-input" id="modal_costo_fijo_hoja">
                                        <label class="form-check-label" for="modal_costo_fijo_hoja" style="font-size:0.7rem; color:#60b0ff; cursor:pointer; user-select:none; line-height:1.3;">Fijar costo de la hoja impresa</label>
                                    </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-chart-line me-1" style="color:#60b0ff;"></i> Margen de Utilidad (%)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_margen_utilidad" value="40.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">Precio = Costo/Hoja × (1 + margen%). Se aplica también al costo fijado</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-hand-holding-usd me-1" style="color:#60b0ff;"></i> Tope Seguridad Social ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_tope_ss" value="5000.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">5% hasta el tope + 10% del excedente</span>
                            </div>
                        </div>
                    </div>
                    <!-- PESTAÑA INSUMOS - MATERIALES UTILIZADOS -->
                    <div class="tab-pane fade" id="panel-insumos" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-sticky-note me-1" style="color:#60b0ff;"></i> Papel Adhesivo A4 ($/paquete)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_precio_adhesivo" value="8840.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">732.98 c/u × 20 hojas</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-layer-group me-1" style="color:#60b0ff;"></i> Hojas por Paquete</label>
                                <input type="number" class="form-control form-control-sm" id="modal_uds_adhesivo" value="20" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-tint me-1" style="color:#60b0ff;"></i> Kit de Tinta ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_precio_tinta" value="12000.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-file-alt me-1" style="color:#60b0ff;"></i> Duración del Kit de Tinta (meses)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_duracion_kit" value="12.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">1 kit dura aprox. 12 meses (1 año)</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-fire me-1" style="color:#60b0ff;"></i> Papel Adhesivo Total</label>
                                <div class="input-group" style="height:32px;">
                                    <span class="input-group-text" style="background:#121212; border:1px solid #333; color:#9a9a9a; font-size:0.7rem; border-radius:8px 0 0 8px; padding:0.1rem 0.5rem;">
                                        <i class="fas fa-calculator"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-sm" id="modal_papel_total" value="Hojas/20 x Precio" readonly style="height:32px; font-size:0.7rem; padding:0.2rem 0.6rem; border-radius:0 8px 8px 0; background:#0a0a0a; border:1px solid #333; color:#60b0ff; cursor:default;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-oil-can me-1" style="color:#60b0ff;"></i> Tinta Total</label>
                                <div class="input-group" style="height:32px;">
                                    <span class="input-group-text" style="background:#121212; border:1px solid #333; color:#9a9a9a; font-size:0.7rem; border-radius:8px 0 0 8px; padding:0.1rem 0.5rem;">
                                        <i class="fas fa-calculator"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-sm" id="modal_tinta_total" value="Hojas/Rend x Precio kit" readonly style="height:32px; font-size:0.7rem; padding:0.2rem 0.6rem; border-radius:0 8px 8px 0; background:#0a0a0a; border:1px solid #333; color:#60b0ff; cursor:default;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-detalle"><i class="fas fa-boxes me-1" style="color:#60b0ff;"></i> Gasto Material (insumos)</label>
                                <div class="input-group" style="height:32px;">
                                    <span class="input-group-text" style="background:#121212; border:1px solid #333; color:#9a9a9a; font-size:0.7rem; border-radius:8px 0 0 8px; padding:0.1rem 0.5rem;">
                                        <i class="fas fa-calculator"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-sm" id="modal_gasto_material" value="Papel + Tinta + Energía + Agua" readonly style="height:32px; font-size:0.7rem; padding:0.2rem 0.6rem; border-radius:0 8px 8px 0; background:#0a0a0a; border:1px solid #333; color:#ffc107; cursor:default;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- PESTAÑA PORCENTAJES -->
                    <div class="tab-pane fade" id="panel-porcentajes" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> % Depreciación Anual</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_depreciacion" value="12.48" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                                <span style="font-size:0.6rem; color:#6a6a6a;">1.04% mensual × 12 meses</span>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> ISV - Impuesto sobre Ventas (%)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_imp_ventas" value="10.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> % Contribución Local</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_contrib_local" value="0.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> % Utilidad Gobierno</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_imp_gobierno" value="20.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> % Otros Gastos</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_otros_gastos" value="11.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> % Vacaciones</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_vacaciones" value="9.09" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-detalle"><i class="fas fa-percent me-1" style="color:#60b0ff;"></i> % Fuerza Trabajo</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_fuerza_trabajo" value="5.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                        </div>
                    </div>
                    <!-- PESTAÑA IMPORTES MANUALES -->
                    <div class="tab-pane fade" id="panel-importes" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="modal_manual_totales" style="width: 44px; height: 22px; cursor: pointer; background-color: #2b7fff; border-color: #2b7fff;">
                                    <label class="form-check-label" for="modal_manual_totales" style="color: #b0b0b0; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                        <i class="fas fa-hand-pointer me-1" style="color: #60b0ff;"></i> Introducir Total Importes Manualmente
                                    </label>
                                    <span style="font-size: 0.65rem; color: #6a6a6a; display: block; margin-top: 2px;">
                                        Al activarlo se usan los totales que escriba abajo (Salario, SS, Vacaciones, Fuerza de Trabajo, Depreciación) y se ignoran los campos de Operación/Porcentajes para esos conceptos.
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-user-tie me-1" style="color:#60b0ff;"></i> Salario Total</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_salario_total_m" value="27300.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-shield-alt me-1" style="color:#60b0ff;"></i> Seguridad Social Total</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_ss_total_m" value="1980.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-umbrella-beach me-1" style="color:#60b0ff;"></i> Vacaciones Total</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_vacaciones_total_m" value="2481.57" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-wrench me-1" style="color:#60b0ff;"></i> Fuerza de Trabajo Total</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_fuerza_total_m" value="1365.00" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-detalle"><i class="fas fa-print me-1" style="color:#60b0ff;"></i> Depreciación Total del Período</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="modal_depreciacion_total_m" value="3064.88" style="height:32px; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:8px; background:#121212; border:1px solid #333; color:#f0f0f0;">
                            </div>
                            <div class="col-md-2 mt-4">
                                <span style="font-size:0.65rem; color:#6a6a6a;">
                                    <i class="fas fa-info-circle me-1" style="color:#60b0ff;"></i> El ISV (10%), Otros Gastos (11%), etc. se siguen aplicando sobre estos totales como en la Metodología.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 p-2" style="background: rgba(43, 127, 255, 0.05); border-left: 3px solid #2b7fff; border-radius: 8px;">
                    <p style="font-size:0.7rem; color:#9a9a9a; margin:0;">
                        <i class="fas fa-info-circle me-1" style="color:#60b0ff;"></i> 
                        <strong style="color:#60b0ff;">Nota:</strong> El <strong style="color:#ffc107;">Salario</strong> es un dato fijo por trabajador. La Seguridad Social se aplica por trabajador: <strong style="color:#ffc107;">5% hasta el tope indicado y 10% sobre el excedente</strong>. El precio por hoja se calcula como Costo por Hoja + Margen de utilidad.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #2a2a2a; padding: 0.8rem 1.8rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCerrarModalFooter" style="border-radius:8px; padding:0.4rem 1.5rem; font-size:0.8rem; color:#9a9a9a; border-color:#333;">
                    <i class="fas fa-times me-2"></i> Cancelar
                </button>
                <button type="button" class="btn-generar-modal" id="btnAceptarConfiguracion" style="padding:0.5rem 2.5rem; font-size:0.9rem; border-radius:10px;">
                    <i class="fas fa-check me-2"></i> Aceptar configuración
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL METODOLOGÍA -->
<!-- ============================================================ -->
<div class="modal fade modal-metodologia" id="modalMetodologia" tabindex="-1" aria-labelledby="metodologiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="metodologiaLabel">
                    <i class="fas fa-book-open me-2" style="color:#ffc107;"></i> Metodología de Cálculo - Ficha de Costo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" style="filter: invert(1) brightness(1.5); opacity:0.7;"></button>
            </div>
            <div class="modal-body">
                <p style="color:#9a9a9a; margin-bottom:1rem;">
                    <i class="fas fa-info-circle me-1" style="color:#60b0ff;"></i> 
                    Fórmulas utilizadas en cada fila de la Ficha de Costo según Resolución 148/2023 MFP
                </p>
                <table class="table-metodologia">
                    <thead>
                        <tr>
                            <th style="width:10%;">Fila</th>
                            <th style="width:35%;">Concepto</th>
                            <th style="width:55%;">Fórmula de Cálculo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="fila">I.1</td><td>Ingreso por hoja</td><td class="formula">Precio por Hoja auto-computado (Costo por Hoja + % Margen)</td></tr>
                        <tr><td class="fila">I.2</td><td>Ingreso total</td><td class="formula">Ingreso por Hoja × Total de Hojas del período</td></tr>
                        <tr><td class="fila">1</td><td>Gasto Material</td><td class="formula">Insumos (1.1) + Energía (1.3) + Agua (1.4)</td></tr>
                        <tr><td class="fila">1.1</td><td>De ello: Insumos (Materias primas)</td><td class="formula">Papel Adhesivo + Tinta (ver ANEXO 2 – Desagregación de los Insumos)</td></tr>
                        <tr><td class="fila">1.2</td><td>De ello: Papel adhesivo y tinta</td><td class="formula">Papel: Paquetes × Precio por paquete + Tinta: (Precio del kit × meses del período ÷ duración del kit en meses)</td></tr>
                        <tr><td class="fila">1.3</td><td>De ello: Energía</td><td class="formula">Gasto de electricidad del período (Mensual: completo; Diario: ÷ 24)</td></tr>
                        <tr><td class="fila">1.4</td><td>De ello: Agua</td><td class="formula">Gasto de agua del período (Mensual: completo; Diario: ÷ 24)</td></tr>
                        <tr><td class="fila">2</td><td>Salario Directo o retribución</td><td class="formula">Salario por trabajador × Nº de trabajadores (dato fijo)</td></tr>
                        <tr><td class="fila">2.1</td><td>De ello: salarios</td><td class="formula">Idem fila 2</td></tr>
                        <tr><td class="fila">3</td><td>Otros Gastos Directos (Transportación)</td><td class="formula">Transportación del período (dato fijo por período)</td></tr>
                        <tr><td class="fila">3.1</td><td>Transportación del período</td><td class="formula">Mensual: valor completo; Diario: ÷ 24</td></tr>
                        <tr><td class="fila">4</td><td>Gastos asociados a la producción</td><td class="formula">Seguridad Social (4.1) + Vacaciones (4.2) + Fuerza de Trabajo (4.3) + Depreciación (4.4)</td></tr>
                        <tr><td class="fila">4.1</td><td>De ello: Seguridad Social</td><td class="formula">Por trabajador: Si Salario ≤ $5,000: × 5% <br>Si Salario &gt; $5,000: $5,000 × 5% + (Salario - $5,000) × 10%  →  × Nº de trabajadores</td></tr>
                        <tr><td class="fila">4.2</td><td>De ello: Vacaciones</td><td class="formula">Salario Total × % Vacaciones</td></tr>
                        <tr><td class="fila">4.3</td><td>De ello: Fuerza de Trabajo</td><td class="formula">Salario Total × % Fuerza de Trabajo</td></tr>
                        <tr><td class="fila">4.4</td><td>De ello: Depreciación</td><td class="formula">(Valor Activo × % Depreciación Anual) / 12 (Mensual) o /365 (Diario)</td></tr>
                        <tr><td class="fila">5</td><td>COSTO TOTAL (1+2+3+4)</td><td class="formula">Gasto Material + Salario + Transportación + Gastos asociados a la producción</td></tr>
                        <tr><td class="fila">6</td><td>Gastos Generales y de Administración</td><td class="formula">Valor de entrada (configurable)</td></tr>
                        <tr><td class="fila">6.1</td><td>De ello: salarios</td><td class="formula">Valor de entrada</td></tr>
                        <tr><td class="fila">7</td><td>Gastos de Distribución y Venta</td><td class="formula">Valor de entrada (configurable)</td></tr>
                        <tr><td class="fila">7.1</td><td>De ello: salarios</td><td class="formula">Valor de entrada</td></tr>
                        <tr><td class="fila">8</td><td>Gastos Financieros</td><td class="formula">Valor de entrada (configurable)</td></tr>
                        <tr><td class="fila">9</td><td>Gastos Financiamiento OSDE</td><td class="formula">Valor de entrada (configurable)</td></tr>
                        <tr><td class="fila">10</td><td>Gastos Tributarios (Seg. Social e Impuestos)</td><td class="formula">Valor de entrada (configurable)</td></tr>
                        <tr><td class="fila">11</td><td>TOTAL DE GASTOS (6 al 10)</td><td class="formula">6 + 7 + 8 + 9 + 10</td></tr>
                        <tr><td class="fila">12</td><td>TOTAL COSTOS Y GASTOS (5+11)</td><td class="formula">Costo Total + Total de Gastos Indirectos</td></tr>
                        <tr><td class="fila">13.1</td><td>Otros Gastos (Reparación y Mantenimiento)</td><td class="formula">Costo Total × % Otros Gastos</td></tr>
                        <tr><td class="fila">14</td><td>BASE IMPONIBLE (12+13.1)</td><td class="formula">Total Costos y Gastos + Otros Gastos</td></tr>
                        <tr><td class="fila">15</td><td>Impuesto sobre Ventas (ISV)</td><td class="formula">Base Imponible × % ISV</td></tr>
                        <tr><td class="fila">16</td><td>Contribución Local</td><td class="formula">Base Imponible × % Contribución Local</td></tr>
                        <tr><td class="fila">13</td><td>Utilidad antes de Impuesto Gubernamental</td><td class="formula">Ingresos Totales - (Base Imponible + Impuesto Ventas + Contribución Local)</td></tr>
                        <tr><td class="fila">18</td><td>Impuesto Gubernamental</td><td class="formula">Utilidad antes Gob × % Utilidad Gobierno</td></tr>
                        <tr><td class="fila">19</td><td>TOTAL COSTOS Y GASTOS (14+15+16+18)</td><td class="formula">Base Imponible + Impuesto Ventas + Contribución Local + Impuesto Gubernamental</td></tr>
                        <tr><td class="fila">20</td><td>PRECIO O TARIFA TOTAL</td><td class="formula">Base Imponible + Impuesto Ventas + Contribución Local + Utilidad del margen aplicado</td></tr>
                        <tr><td class="fila">21</td><td>UTILIDAD NETA</td><td class="formula">Utilidad antes Gob - Impuesto Gubernamental</td></tr>
                        <tr><td class="fila">22</td><td>PRECIO UNITARIO POR HOJA</td><td class="formula">Costo por Hoja × (1 + % Margen de Utilidad)</td></tr>
                    </tbody>
                </table>
                <div style="margin-top:1rem; padding:0.8rem; background:#1a1a1a; border-radius:8px; border-left:3px solid #ffc107;">
                    <p style="color:#b0b0b0; font-size:0.8rem; margin:0;">
                        <i class="fas fa-sync-alt me-1" style="color:#ffc107;"></i>
                        <strong>Nota:</strong> El Salario (Fila 2) y la Transportación (Fila 3) son datos fijos por período. La Seguridad Social (4.1) se aplica por trabajador (5% hasta el tope de $5,000 y 10% al excedente).
                        La tinta se imputa por la duración del kit en meses (1 kit = 12 000,00 CUP durante 12 meses). El Precio por Hoja (Fila 22) se auto-computa a partir del Costo por Hoja más el % de margen de utilidad.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-w11-primary" data-bs-dismiss="modal" style="padding:0.5rem 2rem; font-size:0.95rem;">
                    <i class="fas fa-check me-2"></i> Entendido
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
// DEFINIR DATOS PHP PARA PDF
const datosPdf = <?= json_encode($pdfData) ?>;
</script>

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../nominas/js/pdfmake.min.js"></script>
<script src="../nominas/js/vfs_fonts.js"></script>
<script>
    // =====================================================
    // FUNCIONES PARA COOKIES
    // =====================================================
    function getCookie(name) {
        let matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }

    function setCookie(name, value, days = 30) {
        let date = new Date();
        date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + date.toUTCString() + "; path=/";
    }

    // =====================================================
    // CONSTRUCCIÓN DEL DOCUMENTO PDF (REUTILIZABLE)
    // Devuelve { docDefinition, nombreArchivo }
    // =====================================================
    window.construirDocDefinitionPDF = function(data, ocultarCeros) {
        function mostrarFilaPDF(valor) {
            if (ocultarCeros == 1 && (parseFloat(valor) == 0)) return false;
            return true;
        }
        function formatMoney(valor) {
            if (valor === undefined || valor === null || isNaN(valor)) return '$ 0.00';
            return '$ ' + valor.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        function formatCantidad(valor) {
            if (valor === undefined || valor === null || isNaN(valor)) return '0';
            return valor.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        function formatNum2(valor) {
            if (valor === undefined || valor === null || isNaN(valor)) return '0.00';
            return valor.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        let now = new Date();
        let day = String(now.getDate()).padStart(2, '0');
        let month = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let nombreArchivo = `Ficha_Costo_${data.codigo_servicio}_${year}${month}${day}.pdf`;

            let docDefinition = {
                pageOrientation: 'landscape',
                pageSize: 'LETTER',
                pageMargins: [30, 30, 30, 40],
                footer: function(currentPage, pageCount) {
                    if (currentPage === 1) {
                        return {
                            margin: [30, 10, 30, 0],
                            columns: [
                                { text: 'SISCOSTO TransNuBeT - Generado según Res. 148/2023 MFP', fontSize: 7, alignment: 'left', color: '#777' },
                                { text: `Página ${currentPage} de ${pageCount}`, fontSize: 7, alignment: 'right', bold: true }
                            ]
                        };
                    }
                    return {
                        margin: [30, 10, 30, 0],
                        columns: [
                            { text: 'SISCOSTO TransNuBeT - Resumen de Rentabilidad', fontSize: 7, alignment: 'left', color: '#777' },
                            { text: `Página ${currentPage} de ${pageCount}`, fontSize: 7, alignment: 'right', bold: true }
                        ]
                    };
                },
                content: [
                    // HOJA 1: FICHA DE COSTO
                    {
                        table: {
                            widths: ['*'],
                            body: [
                                [{
                                    stack: [
                                        { text: 'MINISTERIO DE FINANZAS Y PRECIOS', fontSize: 14, bold: true, alignment: 'center', color: '#1c2b3d' },
                                        { text: 'FICHA DE COSTOS Y GASTOS DE PRODUCTOS Y SERVICIOS - EVALUACIÓN DE PRECIOS Y TARIFAS', fontSize: 11, bold: true, alignment: 'center', color: '#1c2b3d', margin: [0, 2, 0, 5] },
                                        { columns: [
                                            { text: `Resolución: 148/2023 MFP`, fontSize: 9, alignment: 'left', bold: true },
                                            { text: `Período: ${data.periodo_texto}`, fontSize: 9, alignment: 'center', bold: true },
                                            { text: `Emisión: ${data.fecha_emision} | Vigencia: ${data.fecha_vigencia} (${data.vigencia_mostrar || '5 ANOS'})`, fontSize: 9, alignment: 'right', bold: true }
                                        ], margin: [0, 0, 0, 8] },
                                        { columns: [
                                            { text: [{ text: 'Producto o Servicio: ', bold: true }, data.nombre_servicio], fontSize: 9 },
                                            { text: [{ text: 'Código: ', bold: true }, data.codigo_servicio], fontSize: 9, alignment: 'right' }
                                        ], margin: [0, 2, 0, 2] },
                                        { columns: [
                                            { text: [{ text: 'UM: ', bold: true }, 'Hoja A4'], fontSize: 8.5 },
                                            { text: [{ text: 'Nivel Producción: ', bold: true }, data.nivel_produccion || '1 - Prod. Terminada'], fontSize: 8.5 },
                                            { text: [{ text: '% Capacidad: ', bold: true }, data.porcentaje_capacidad || '100% - Máximo Rendimiento'], fontSize: 8.5 },
                                            { text: [{ text: 'Categoría: ', bold: true }, data.categoria], fontSize: 8.5 }
                                        ], margin: [0, 2, 0, 2] },
                                        { text: [{ text: 'Cantidad de Producción Terminada: ', bold: true }, formatCantidad(data.produccion_terminada || data.total_hojas_periodo) + ' hojas'], fontSize: 8.5, bold: true, color: '#1c2b3d', margin: [0, 2, 0, 2] },
                                        { columns: [
                                            { text: [{ text: 'Organismo: ', bold: true }, data.organismo || 'OLPP'], fontSize: 8.5 },
                                            { text: [{ text: 'Empresa: ', bold: true }, data.empresa || 'PDL TransNuBeT'], fontSize: 8.5, alignment: 'right' }
                                        ], margin: [0, 2, 0, 0] }
                                    ],
                                    alignment: 'left',
                                    margin: [0, 0, 0, 5]
                                }]
                            ]
                        },
                        layout: 'noBorders'
                    },
                    // TABLA DE CONCEPTOS
                    {
                        table: {
                            headerRows: 1,
                            widths: ['*', 40, 100, 100],
                            body: (function() {
                                let body = [];
                                // Encabezados
                                body.push([
                                    { text: 'CONCEPTOS', style: 'tableHeader', alignment: 'left' },
                                    { text: 'Fila', style: 'tableHeader', alignment: 'center' },
                                    { text: 'Costo Base', style: 'tableHeader', alignment: 'right' },
                                    { text: 'Costo Nuevo', style: 'tableHeader', alignment: 'right' }
                                ]);
                                // INGRESOS
                                body.push([
                                    { text: 'INGRESOS POR VENTAS', style: 'rowIngresos' },
                                    { text: '-', style: 'rowIngresos', alignment: 'center' },
                                    { text: formatMoney(data.ingresos_totales), style: 'rowIngresos', alignment: 'right' },
                                    { text: formatMoney(data.ingresos_totales), style: 'rowIngresos', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'Ingreso por hoja', style: 'rowIndent1' },
                                    { text: 'I.1', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.ingreso_por_hoja), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.ingreso_por_hoja), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'Ingreso total', style: 'rowIndent1' },
                                    { text: 'I.2', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.ingresos_totales), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.ingresos_totales), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                // GASTOS DIRECTOS
                                body.push([
                                    { text: 'GASTOS DIRECTOS', style: 'rowGastosDirectos' },
                                    { text: '-', style: 'rowGastosDirectos', alignment: 'center' },
                                    { text: '-', style: 'rowGastosDirectos', alignment: 'right' },
                                    { text: '-', style: 'rowGastosDirectos', alignment: 'right' }
                                ]);
                                let papelYTinta = (data.gasto_papel_adhesivo_total || 0) + (data.costo_tinta_total || 0);
                                let gastoMaterial = (data.gasto_material_total || 0);
                                body.push([
                                    { text: 'Gasto Material', style: 'rowConcepto' },
                                    { text: '1', style: 'rowConcepto', alignment: 'center' },
                                    { text: formatMoney(gastoMaterial), style: 'rowConcepto', alignment: 'right' },
                                    { text: formatMoney(gastoMaterial), style: 'rowConcepto', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'De ello: Insumos (Materias primas)', style: 'rowIndent1' },
                                    { text: '1.1', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(gastoMaterial), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(gastoMaterial), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'Papel adhesivo y tinta', style: 'rowIndent1' },
                                    { text: '1.2', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(papelYTinta), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(papelYTinta), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                if (mostrarFilaPDF(data.gasto_energia_periodo)) {
                                    body.push([
                                        { text: 'Energía', style: 'rowIndent1' },
                                        { text: '1.3', style: 'rowIndent1', alignment: 'center' },
                                        { text: formatMoney(data.gasto_energia_periodo), style: 'rowIndent1', alignment: 'right' },
                                        { text: formatMoney(data.gasto_energia_periodo), style: 'rowIndent1', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(data.gasto_agua_periodo)) {
                                    body.push([
                                        { text: 'Agua', style: 'rowIndent1' },
                                        { text: '1.4', style: 'rowIndent1', alignment: 'center' },
                                        { text: formatMoney(data.gasto_agua_periodo), style: 'rowIndent1', alignment: 'right' },
                                        { text: formatMoney(data.gasto_agua_periodo), style: 'rowIndent1', alignment: 'right' }
                                    ]);
                                }
                                body.push([
                                    { text: 'Salario Directo o retribución', style: 'rowConcepto' },
                                    { text: '2', style: 'rowConcepto', alignment: 'center' },
                                    { text: formatMoney(data.salario_total), style: 'rowConcepto', alignment: 'right' },
                                    { text: formatMoney(data.salario_total), style: 'rowConcepto', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'De ello, salarios', style: 'rowIndent1' },
                                    { text: '2.1', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.salario_total), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.salario_total), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: (data.manual_totales == 1) ? 'Salario total ingresado manualmente' : 'Salario fijo por trabajador', style: 'rowIndent2' },
                                    { text: '-', style: 'rowIndent2', alignment: 'center' },
                                    { text: '-', style: 'rowIndent2', alignment: 'right' },
                                    { text: '-', style: 'rowIndent2', alignment: 'right' }
                                ]);
                                if (mostrarFilaPDF(data.gasto_transportacion || 0)) {
                                    body.push([
                                        { text: 'Otros Gastos Directos (Transportación)', style: 'rowConcepto' },
                                        { text: '3', style: 'rowConcepto', alignment: 'center' },
                                        { text: formatMoney(data.gasto_transportacion || 0), style: 'rowConcepto', alignment: 'right' },
                                        { text: formatMoney(data.gasto_transportacion || 0), style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                    body.push([
                                        { text: 'Transportación del período', style: 'rowIndent1' },
                                        { text: '3.1', style: 'rowIndent1', alignment: 'center' },
                                        { text: formatMoney(data.gasto_transportacion || 0), style: 'rowIndent1', alignment: 'right' },
                                        { text: formatMoney(data.gasto_transportacion || 0), style: 'rowIndent1', alignment: 'right' }
                                    ]);
                                }
                                let gastosAsociados = (data.seguridad_social_total || 0) + (data.vacaciones_total || 0) + 
                                                       (data.impuesto_fuerza_trabajo || 0) + (data.depreciacion_periodo || 0);
                                body.push([
                                    { text: 'Gastos asociados a la producción (Depreciación)', style: 'rowConcepto' },
                                    { text: '4', style: 'rowConcepto', alignment: 'center' },
                                    { text: formatMoney(gastosAsociados), style: 'rowConcepto', alignment: 'right' },
                                    { text: formatMoney(gastosAsociados), style: 'rowConcepto', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'De ello, Seguridad Social', style: 'rowIndent1' },
                                    { text: '4.1', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.seguridad_social_total), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.seguridad_social_total), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: (data.manual_totales == 1) ? 'Seguridad Social total ingresada manualmente' : '5% hasta $5,000 por trabajador + 10% del excedente', style: 'rowIndent2' },
                                    { text: '-', style: 'rowIndent2', alignment: 'center' },
                                    { text: '-', style: 'rowIndent2', alignment: 'right' },
                                    { text: '-', style: 'rowIndent2', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: `De ello, Vacaciones (${(data.porcentaje_vacaciones || 9.09).toFixed(2)}%)`, style: 'rowIndent1' },
                                    { text: '4.2', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.vacaciones_total), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.vacaciones_total), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: `De ello, Fuerza de Trabajo (${(data.porcentaje_fuerza_trabajo || 5).toFixed(2)}%)`, style: 'rowIndent1' },
                                    { text: '4.3', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.impuesto_fuerza_trabajo), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.impuesto_fuerza_trabajo), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: `De ello, Depreciación (${data.porcentaje_depreciacion_anual || 12.48}% anual)`, style: 'rowIndent1' },
                                    { text: '4.4', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.depreciacion_periodo), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.depreciacion_periodo), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'COSTO TOTAL (1+2+3+4)', style: 'rowTotal' },
                                    { text: '5', style: 'rowTotal', alignment: 'center' },
                                    { text: formatMoney(data.subtotal_gastos_directos), style: 'rowTotal', alignment: 'right' },
                                    { text: formatMoney(data.subtotal_gastos_directos), style: 'rowTotal', alignment: 'right' }
                                ]);
                                // Gastos indirectos (ocultables)
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'Gastos Generales y de Administración', style: 'rowConcepto' },
                                        { text: '6', style: 'rowConcepto', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'De ello, salarios', style: 'rowIndent1' },
                                        { text: '6.1', style: 'rowIndent1', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowIndent1', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowIndent1', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'Gastos de Distribución y Venta', style: 'rowConcepto' },
                                        { text: '7', style: 'rowConcepto', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'De ello, salarios', style: 'rowIndent1' },
                                        { text: '7.1', style: 'rowIndent1', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowIndent1', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowIndent1', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'Gastos Financieros', style: 'rowConcepto' },
                                        { text: '8', style: 'rowConcepto', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'Gastos Financiamiento OSDE', style: 'rowConcepto' },
                                        { text: '9', style: 'rowConcepto', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                }
                                if (mostrarFilaPDF(0)) {
                                    body.push([
                                        { text: 'Gastos Tributarios (Seg. Social e Impuestos)', style: 'rowConcepto' },
                                        { text: '10', style: 'rowConcepto', alignment: 'center' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' },
                                        { text: '$ 0.00', style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                }
                                body.push([
                                    { text: 'TOTAL DE GASTOS (6 al 10)', style: 'rowTotal' },
                                    { text: '11', style: 'rowTotal', alignment: 'center' },
                                    { text: '$ 0.00', style: 'rowTotal', alignment: 'right' },
                                    { text: '$ 0.00', style: 'rowTotal', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'TOTAL DE COSTOS Y GASTOS (5+11)', style: 'rowTotal' },
                                    { text: '12', style: 'rowTotal', alignment: 'center' },
                                    { text: formatMoney(data.subtotal_gastos_directos), style: 'rowTotal', alignment: 'right' },
                                    { text: formatMoney(data.subtotal_gastos_directos), style: 'rowTotal', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'Otros Gastos (Reparación y Mantenimiento)', style: 'rowConcepto' },
                                    { text: '13.1', style: 'rowConcepto', alignment: 'center' },
                                    { text: formatMoney(data.costo_otros_gastos_total), style: 'rowConcepto', alignment: 'right' },
                                    { text: formatMoney(data.costo_otros_gastos_total), style: 'rowConcepto', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: `${data.porcentaje_otros_gastos || 11}% sobre el costo total`, style: 'rowIndent1' },
                                    { text: '13.1', style: 'rowIndent1', alignment: 'center' },
                                    { text: formatMoney(data.costo_otros_gastos_total), style: 'rowIndent1', alignment: 'right' },
                                    { text: formatMoney(data.costo_otros_gastos_total), style: 'rowIndent1', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'BASE IMPONIBLE (12+13.1)', style: 'rowTotal' },
                                    { text: '14', style: 'rowTotal', alignment: 'center' },
                                    { text: formatMoney(data.base_imponible), style: 'rowTotal', alignment: 'right' },
                                    { text: formatMoney(data.base_imponible), style: 'rowTotal', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: `ISV - Impuesto sobre Ventas (${data.tasa_impuesto_ventas || 10}%)`, style: 'rowConcepto' },
                                    { text: '15', style: 'rowConcepto', alignment: 'center' },
                                    { text: formatMoney(data.impuesto_ventas_total), style: 'rowConcepto', alignment: 'right' },
                                    { text: formatMoney(data.impuesto_ventas_total), style: 'rowConcepto', alignment: 'right' }
                                ]);
                                if (mostrarFilaPDF(data.contribucion_local_total)) {
                                    body.push([
                                        { text: `Contribución Local (${data.tasa_contribucion_local || 0}%)`, style: 'rowConcepto' },
                                        { text: '16', style: 'rowConcepto', alignment: 'center' },
                                        { text: formatMoney(data.contribucion_local_total), style: 'rowConcepto', alignment: 'right' },
                                        { text: formatMoney(data.contribucion_local_total), style: 'rowConcepto', alignment: 'right' }
                                    ]);
                                }
                                body.push([
                                    { text: 'Utilidad antes de Impuesto Gubernamental', style: 'rowUtilidadVerde' },
                                    { text: '13', style: 'rowUtilidadVerde', alignment: 'center' },
                                    { text: formatMoney(data.utilidad_antes_gobierno), style: 'rowUtilidadVerde', alignment: 'right' },
                                    { text: formatMoney(data.utilidad_antes_gobierno), style: 'rowUtilidadVerde', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: `Impuesto Gubernamental (${data.porcentaje_utilidad_gobierno || 20}% de utilidades)`, style: 'rowImpuestoGob' },
                                    { text: '18', style: 'rowImpuestoGob', alignment: 'center' },
                                    { text: formatMoney(data.utilidad_para_gobierno), style: 'rowImpuestoGob', alignment: 'right' },
                                    { text: formatMoney(data.utilidad_para_gobierno), style: 'rowImpuestoGob', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'TOTAL DE COSTOS Y GASTOS (14+15+16+18)', style: 'rowTotal' },
                                    { text: '19', style: 'rowTotal', alignment: 'center' },
                                    { text: formatMoney(data.total_gastos_final), style: 'rowTotal', alignment: 'right' },
                                    { text: formatMoney(data.total_gastos_final), style: 'rowTotal', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'PRECIO O TARIFA TOTAL (14+15+16+17)', style: 'rowPrecioAzul' },
                                    { text: '20', style: 'rowPrecioAzul', alignment: 'center' },
                                    { text: formatMoney(data.ingresos_totales), style: 'rowPrecioAzul', alignment: 'right' },
                                    { text: formatMoney(data.ingresos_totales), style: 'rowPrecioAzul', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'UTILIDAD NETA (13 - 18)', style: 'rowUtilidadGrande' },
                                    { text: '21', style: 'rowUtilidadGrande', alignment: 'center' },
                                    { text: formatMoney(data.utilidad_neta_total), style: 'rowUtilidadGrande', alignment: 'right' },
                                    { text: formatMoney(data.utilidad_neta_total), style: 'rowUtilidadGrande', alignment: 'right' }
                                ]);
                                body.push([
                                    { text: 'PRECIO UNITARIO POR HOJA', style: 'rowPrecioGrande' },
                                    { text: '22', style: 'rowPrecioGrande', alignment: 'center' },
                                    { text: formatMoney(data.precio_venta_final), style: 'rowPrecioGrande', alignment: 'right' },
                                    { text: formatMoney(data.precio_venta_final), style: 'rowPrecioGrande', alignment: 'right' }
                                ]);
                                return body;
                            })()
                        },
                        layout: {
                            hLineWidth: (i, node) => 0.5,
                            vLineWidth: (i, node) => 0.5,
                            hLineColor: (i, node) => '#aaaaaa',
                            vLineColor: (i, node) => '#aaaaaa'
                        }
                    },
                    // Precios de referencia
                    {
                        margin: [0, 15, 0, 10],
                        table: {
                            widths: ['*', 120, 120],
                            body: [
                                [{ text: 'Datos sobre precios de referencia', colSpan: 3, alignment: 'center', bold: true, fillColor: '#f0f0f0', fontSize: 9, margin: [0, 3] }],
                                [{ text: '', fontSize: 8, fillColor: '#e9ecef' }, { text: 'Plan/Anterior', alignment: 'center', fontSize: 8, bold: true, fillColor: '#e9ecef' }, { text: 'Precio Actual', alignment: 'center', fontSize: 8, bold: true, fillColor: '#e9ecef' }]
                            ]
                        },
                        layout: {
                            hLineWidth: (i, node) => 0.5,
                            vLineWidth: (i, node) => 0.5,
                            hLineColor: (i, node) => '#aaaaaa',
                            vLineColor: (i, node) => '#aaaaaa'
                        }
                    },
                    // Firmas
                    {
                        margin: [0, 20, 0, 0],
                        columns: [
                            { stack: [{ text: 'Elaborado por: ____________________', fontSize: 9 }, { text: `Fecha: ${data.fecha_emision}`, fontSize: 8, color: '#666' }], width: '50%' },
                            { stack: [{ text: 'Aprobado por: ____________________', fontSize: 9, alignment: 'right' }, { text: `Fecha: ${data.fecha_vigencia}`, fontSize: 8, color: '#666', alignment: 'right' }], width: '50%' }
                        ]
                    },
                    { margin: [0, 15, 0, 0], text: 'SISCOSTO PDL TransNuBeT - % Gastos Indirectos: 40% | Res. 148/2023 MFP', fontSize: 7, alignment: 'center', color: '#999' },
                    { text: '', pageBreak: 'after', margin: [0, 0, 0, 0] },
                    // HOJA 2: RESUMEN
                    {
                        margin: [0, 20, 0, 0],
                        table: {
                            widths: ['*'],
                            body: [
                                [{ text: 'RESUMEN DE RENTABILIDAD (' + data.periodo_texto + ')', fontSize: 18, bold: true, color: '#0078D4', alignment: 'center', margin: [0, 0, 0, 15] }],
                                [{
                                    stack: [
                                        { text: data.periodo_texto + ' - ' + data.nombre_servicio, fontSize: 11, bold: true, color: '#1c2b3d', margin: [0, 0, 0, 10] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Hojas Impresas por Día: ', bold: true }, data.hojas_por_dia + ' hojas'], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Ingreso por Hoja: ', bold: true }, formatMoney(data.ingreso_por_hoja)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Ingresos totales: ', bold: true }, formatMoney(data.ingresos_totales)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { text: '', margin: [0, 5, 0, 5] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Costo Total (1+2+3+4): ', bold: true }, formatMoney(data.subtotal_gastos_directos)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Base Imponible: ', bold: true }, formatMoney(data.base_imponible)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Utilidad Neta (fila 21): ', bold: true, color: '#28a745' }, formatMoney(data.utilidad_neta_total)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { text: '', margin: [0, 5, 0, 5] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Depreciación: ', bold: true }, formatMoney(data.depreciacion_periodo)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Impuesto Gobierno: ', bold: true }, formatMoney(data.utilidad_para_gobierno)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Margen de Utilidad: ', bold: true }, (data.margen_utilidad || 0).toFixed(1) + '%'], width: '*' }], margin: [0, 2, 0, 2] },
                                        { text: '', margin: [0, 5, 0, 5] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Precio de Equilibrio: ', bold: true }, formatMoney(data.precio_equilibrio || 0)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Costo por Hoja: ', bold: true }, formatMoney(data.costo_por_hoja || 0)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Utilidad por Hoja: ', bold: true, color: '#28a745' }, formatMoney(data.utilidad_por_hoja || 0)], width: '*' }], margin: [0, 2, 0, 2] },
                                        { columns: [{ text: '* ', width: 25 }, { text: [{ text: 'Ocultar filas en cero: ', bold: true }, ocultarCeros == 1 ? 'Sí' : 'No'], width: '*' }], margin: [0, 2, 0, 2] }
                                    ],
                                    alignment: 'left',
                                    margin: [0, 0, 0, 0]
                                }]
                            ]
                        },
                        layout: 'noBorders'
                    },
                    {
                        margin: [0, 25, 0, 0],
                        table: {
                            widths: ['*'],
                            body: [
                                [{ text: `Emisión: ${data.fecha_emision}  |  Vigencia: ${data.fecha_vigencia}`, fontSize: 10, bold: true, color: '#0078D4', alignment: 'center', fillColor: '#e9ecef', margin: [0, 8, 0, 8] }]
                            ]
                        },
                        layout: { hLineWidth: (i, node) => 0, vLineWidth: (i, node) => 0 }
                    },
                    // ANEXO 2: DESAGREGACIÓN DE LOS INSUMOS
                    (function () {
                        var anexoBody = [
                            [
                                { text: 'No.', style: 'tableHeader' },
                                { text: 'Denominación', style: 'tableHeader' },
                                { text: 'UM', style: 'tableHeader' },
                                { text: 'Cantidad', style: 'tableHeader' },
                                { text: 'Precio Unitario', style: 'tableHeader' },
                                { text: 'Importe', style: 'tableHeader' }
                            ],
                            [
                                { text: '1', alignment: 'center', fontSize: 8 },
                                { text: 'Papel adhesivo A4 (paquete de ' + data.uds_adhesivo_paquete + ' hojas)', fontSize: 8 },
                                { text: 'Paq.', alignment: 'center', fontSize: 8 },
                                { text: formatCantidad(data.paquetes_adhesivo || 0), alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.precio_papel_unit || 0), alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.gasto_papel_adhesivo_total || 0), alignment: 'right', fontSize: 8 }
                            ],
                            [
                                { text: '2', alignment: 'center', fontSize: 8 },
                                { text: 'Tinta para impresión (dura ' + formatCantidad(data.duracion_kit_tinta || 0) + ' meses)', fontSize: 8 },
                                { text: 'Kit', alignment: 'center', fontSize: 8 },
                                { text: formatNum2(data.kits_tinta || 0), alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.precio_tinta_unit || 0), alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.costo_tinta_total || 0), alignment: 'right', fontSize: 8 }
                            ],
                            [
                                { text: '3', alignment: 'center', fontSize: 8 },
                                { text: 'Energía eléctrica (período)', fontSize: 8 },
                                { text: 'Mes', alignment: 'center', fontSize: 8 },
                                { text: '1', alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.gasto_energia_periodo || 0), alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.gasto_energia_periodo || 0), alignment: 'right', fontSize: 8 }
                            ]
                        ];
                        if (Number(data.gasto_agua_periodo || 0) > 0) {
                            anexoBody.push([
                                { text: '4', alignment: 'center', fontSize: 8 },
                                { text: 'Agua (período)', fontSize: 8 },
                                { text: 'Mes', alignment: 'center', fontSize: 8 },
                                { text: '1', alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.gasto_agua_periodo), alignment: 'right', fontSize: 8 },
                                { text: formatMoney(data.gasto_agua_periodo), alignment: 'right', fontSize: 8 }
                            ]);
                        }
                        anexoBody.push([
                            { text: 'TOTAL INSUMOS (fila 1.1)', bold: true, alignment: 'right', fontSize: 8, fillColor: '#e9ecef', margin: [0, 3] },
                            { text: '', fillColor: '#e9ecef' },
                            { text: '', fillColor: '#e9ecef' },
                            { text: '', fillColor: '#e9ecef' },
                            { text: '', fillColor: '#e9ecef' },
                            { text: formatMoney(data.gasto_material_total || 0), bold: true, alignment: 'right', fontSize: 8, fillColor: '#e9ecef', margin: [0, 3] }
                        ]);
                        return {
                            pageBreak: 'before',
                            margin: [0, 40, 0, 0],
                            stack: [
                                { text: 'ANEXO 2 — DESAGREGACIÓN DE LOS INSUMOS (Res. 148/2023 MFP)', fontSize: 10, bold: true, color: '#2b7fff', margin: [0, 0, 0, 4] },
                                { text: 'Concepto 1.1 de la Ficha de Costos. Metodología para la elaboración de la ficha de costos y gastos de productos y servicios para la evaluación de precios y tarifas (Gaceta Oficial No. 64, 6-jul-2023).', fontSize: 7, color: '#666', margin: [0, 0, 0, 6] },
                                {
                                    table: {
                                        headerRows: 1,
                                        widths: ['6%', '40%', '9%', '12%', '16%', '17%'],
                                        body: anexoBody
                                    }
                                }
                            ]
                        };
                    })()
                ],
                styles: {
                    tableHeader: { fontSize: 9, bold: true, color: '#ffffff', fillColor: '#1c2b3d', alignment: 'center', margin: [0, 3] },
                    rowIngresos: { fontSize: 9, bold: true, fillColor: '#d4edda', color: '#155724' },
                    rowGastosDirectos: { fontSize: 9, bold: true, fillColor: '#f0f0f0', color: '#333' },
                    rowConcepto: { fontSize: 8, bold: true, fillColor: '#fafafa' },
                    rowIndent1: { fontSize: 8, fillColor: '#ffffff', margin: [0, 0, 0, 0] },
                    rowIndent2: { fontSize: 7, color: '#0078D4', fillColor: '#ffffff', margin: [0, 0, 0, 0] },
                    rowTotal: { fontSize: 8, bold: true, fillColor: '#f8f9fa' },
                    rowUtilidadVerde: { fontSize: 9, bold: true, fillColor: '#d4edda', color: '#155724' },
                    rowImpuestoGob: { fontSize: 8, bold: true, fillColor: '#fff3cd', color: '#856404' },
                    rowPrecioAzul: { fontSize: 9, bold: true, fillColor: '#cce5ff', color: '#004085' },
                    rowUtilidadGrande: { fontSize: 14, bold: true, fillColor: '#d4edda', color: '#155724' },
                    rowPrecioGrande: { fontSize: 14, bold: true, fillColor: '#cce5ff', color: '#004085' }
                }
            };

            return { docDefinition, nombreArchivo };
        };

    // =====================================================
    // EXPORTAR PDF (DESCARGA EL ARCHIVO)
    // =====================================================
    window.exportarPdfFichaCosto = function(data, ocultarCeros) {
        const { docDefinition, nombreArchivo } = window.construirDocDefinitionPDF(data, ocultarCeros);
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin text-primary me-2"></i> Generando PDF...',
            html: 'Estructurando la Ficha de Costo Oficial según <b>Res. 148/2023 MFP</b>.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); },
            background: '#1c1c1c',
            color: '#ffffff'
        });
        setTimeout(function() {
            pdfMake.createPdf(docDefinition).download(nombreArchivo);
            Swal.close();
            Swal.fire({
                title: '¡PDF Generado!',
                text: 'La ficha de costo se ha descargado correctamente.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                background: '#1c1c1c',
                color: '#ffffff'
            });
        }, 1000);
    };

    // =====================================================
    // IMPRIMIR (ABRE EL MISMO PDF EN EL DIÁLOGO DE IMPRESIÓN)
    // Queda idéntico a la exportación a PDF.
    // =====================================================
    window.imprimirPdfFichaCosto = function(data, ocultarCeros) {
        const { docDefinition } = window.construirDocDefinitionPDF(data, ocultarCeros);
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin text-primary me-2"></i> Preparando impresión...',
            html: 'Abriendo la vista de impresión de la Ficha de Costo (Res. 148/2023 MFP).',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); },
            background: '#1c1c1c',
            color: '#ffffff'
        });
        setTimeout(function() {
            pdfMake.createPdf(docDefinition).print();
            Swal.close();
        }, 600);
    };

    // =====================================================
    // INICIALIZACIÓN AL CARGAR LA PÁGINA
    // =====================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Leer cookie 'ocultar_ceros'
        let ocultar = getCookie('ocultar_ceros');
        if (ocultar !== undefined) {
            ocultar = parseInt(ocultar, 10);
            if (ocultar === 0 || ocultar === 1) {
                document.getElementById('modal_ocultar_ceros').checked = (ocultar === 1);
                document.getElementById('hidden_ocultar_ceros').value = ocultar;
            }
        } else {
            document.getElementById('modal_ocultar_ceros').checked = true;
            document.getElementById('hidden_ocultar_ceros').value = 1;
            setCookie('ocultar_ceros', 1);
        }

        // Evento change del checkbox
        document.getElementById('modal_ocultar_ceros').addEventListener('change', function() {
            let valor = this.checked ? 1 : 0;
            setCookie('ocultar_ceros', valor);
            document.getElementById('hidden_ocultar_ceros').value = valor;
        });

        // =====================================================
        // SISTEMA PRINCIPAL
        // =====================================================
        const bienvenida = document.getElementById('pantallaBienvenida');
        const principal = document.getElementById('contenidoPrincipal');
        const modalElement = document.getElementById('modalCompleto');
        const modal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });

        // Botón Iniciar Sistema
        document.getElementById('btnIniciarSistema').addEventListener('click', function() {
            modal.show();
        });

        // Cambio de período
        const diasInput = document.getElementById('modal_dias_trabajo');
        const diasIndicador = document.getElementById('modal_dias_indicador');
        function actualizarDiasSegunPeriodo() {
            const periodoSeleccionado = document.querySelector('input[name="modal_periodo"]:checked');
            if (periodoSeleccionado) {
                if (periodoSeleccionado.value === 'diario') {
                    diasInput.value = 1;
                    diasInput.readOnly = true;
                    diasIndicador.textContent = '📅 1 día laborado';
                } else {
                    diasInput.value = 24;
                    diasInput.readOnly = false;
                    diasIndicador.textContent = '📅 24 días laborados (puede editar)';
                }
            }
        }
        document.querySelectorAll('input[name="modal_periodo"]').forEach((radio) => {
            radio.addEventListener('change', actualizarDiasSegunPeriodo);
        });
        actualizarDiasSegunPeriodo();

        // Botón Aceptar Configuración
        document.getElementById('btnAceptarConfiguracion').addEventListener('click', function() {
            // Copiar valores al formulario oculto
            document.getElementById('hidden_periodo').value = document.querySelector('input[name="modal_periodo"]:checked').value;
            document.getElementById('hidden_organismo').value = document.getElementById('modal_organismo').value;
            document.getElementById('hidden_empresa').value = document.getElementById('modal_empresa').value;
            document.getElementById('hidden_provincia').value = document.getElementById('modal_provincia').value;
            document.getElementById('hidden_nombre_servicio').value = document.getElementById('modal_nombre_servicio').value;
            document.getElementById('hidden_codigo_servicio').value = document.getElementById('modal_codigo_servicio').value;
            document.getElementById('hidden_categoria').value = document.getElementById('modal_categoria').value;
            document.getElementById('hidden_nivel_produccion').value = document.getElementById('modal_nivel_produccion').value;
            document.getElementById('hidden_fecha_emision').value = document.getElementById('modal_fecha_emision').value;
            document.getElementById('hidden_vigencia_valor').value = document.getElementById('modal_vigencia_valor').value;
            document.getElementById('hidden_vigencia_unidad').value = document.getElementById('modal_vigencia_unidad').value;
            document.getElementById('hidden_capacidad').value = document.getElementById('modal_capacidad').value;
            document.getElementById('hidden_produccion_terminada').value = document.getElementById('modal_produccion_terminada').value;
            document.getElementById('hidden_dias_trabajo').value = document.getElementById('modal_dias_trabajo').value;
            document.getElementById('hidden_salario_trabajador').value = document.getElementById('modal_salario_trabajador').value;
            document.getElementById('hidden_num_trabajadores').value = document.getElementById('modal_num_trabajadores').value;
            document.getElementById('hidden_tope_ss').value = document.getElementById('modal_tope_ss').value;
            document.getElementById('hidden_margen_utilidad').value = document.getElementById('modal_margen_utilidad').value;
            document.getElementById('hidden_costo_fijo_hoja').value = document.getElementById('modal_costo_fijo_hoja').checked ? 1 : 0;
            document.getElementById('hidden_costo_fijo_valor').value = document.getElementById('modal_costo_fijo_valor').value;
            document.getElementById('hidden_energia').value = document.getElementById('modal_energia').value;
            document.getElementById('hidden_agua').value = document.getElementById('modal_agua').value;
            document.getElementById('hidden_precio_adhesivo').value = document.getElementById('modal_precio_adhesivo').value;
            document.getElementById('hidden_uds_adhesivo').value = document.getElementById('modal_uds_adhesivo').value;
            document.getElementById('hidden_precio_tinta').value = document.getElementById('modal_precio_tinta').value;
            document.getElementById('hidden_duracion_kit').value = document.getElementById('modal_duracion_kit').value;
            document.getElementById('hidden_gasto_transportacion').value = document.getElementById('modal_transporte').value;
            document.getElementById('hidden_valor_activo').value = document.getElementById('modal_valor_activo').value;
            document.getElementById('hidden_depreciacion').value = document.getElementById('modal_depreciacion').value;
            document.getElementById('hidden_imp_ventas').value = document.getElementById('modal_imp_ventas').value;
            document.getElementById('hidden_contrib_local').value = document.getElementById('modal_contrib_local').value;
            document.getElementById('hidden_imp_gobierno').value = document.getElementById('modal_imp_gobierno').value;
            document.getElementById('hidden_otros_gastos').value = document.getElementById('modal_otros_gastos').value;
            document.getElementById('hidden_vacaciones').value = document.getElementById('modal_vacaciones').value;
            document.getElementById('hidden_fuerza_trabajo').value = document.getElementById('modal_fuerza_trabajo').value;
            document.getElementById('hidden_manual_totales').value = document.getElementById('modal_manual_totales').checked ? 1 : 0;
            document.getElementById('hidden_salario_total_m').value = document.getElementById('modal_salario_total_m').value;
            document.getElementById('hidden_ss_total_m').value = document.getElementById('modal_ss_total_m').value;
            document.getElementById('hidden_vacaciones_total_m').value = document.getElementById('modal_vacaciones_total_m').value;
            document.getElementById('hidden_fuerza_total_m').value = document.getElementById('modal_fuerza_total_m').value;
            document.getElementById('hidden_depreciacion_total_m').value = document.getElementById('modal_depreciacion_total_m').value;
            
            // Capacidad de producción
            const selectCap = document.getElementById('modal_capacidad_produccion');
            document.getElementById('hidden_factor_capacidad').value = selectCap.value;
            document.getElementById('hidden_porcentaje_capacidad').value = selectCap.options[selectCap.selectedIndex].text;
            
            // Ocultar ceros
            let ocultar = document.getElementById('modal_ocultar_ceros').checked ? 1 : 0;
            document.getElementById('hidden_ocultar_ceros').value = ocultar;
            setCookie('ocultar_ceros', ocultar);

            modal.hide();
            bienvenida.style.opacity = '0';
            bienvenida.style.transform = 'scale(0.95)';
            setTimeout(() => {
                bienvenida.style.display = 'none';
                principal.style.display = 'block';
                principal.style.animation = 'fadeIn 0.5s ease';
                setTimeout(() => {
                    document.getElementById('formPrincipal').submit();
                }, 300);
            }, 400);
        });

        // Botones de cerrar modal
        document.getElementById('btnCerrarModal').addEventListener('click', function() {
            modal.hide();
            <?php if (isset($_POST['action']) && $_POST['action'] == 'generate'): ?>
            Swal.fire({
                title: '¿Volver al inicio?',
                text: 'Los datos de la ficha actual se perderán. ¿Desea continuar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2b7fff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, volver',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: '#1c1c1c',
                color: '#e4e4e4',
                iconColor: '#ffc107'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = window.location.pathname;
                else modal.show();
            });
            <?php else: ?>
            document.getElementById('pantallaBienvenida').style.display = 'block';
            document.getElementById('pantallaBienvenida').style.opacity = '1';
            document.getElementById('pantallaBienvenida').style.transform = 'scale(1)';
            document.getElementById('contenidoPrincipal').style.display = 'none';
            <?php endif; ?>
        });
        document.getElementById('btnCerrarModalFooter').addEventListener('click', function() {
            modal.hide();
        });

        // Botón Volver al Inicio
        document.getElementById('btnVolverInicio').addEventListener('click', function() {
            <?php if (isset($_POST['action']) && $_POST['action'] == 'generate'): ?>
            Swal.fire({
                title: '¿Volver al inicio?',
                text: 'Los datos de la ficha actual se perderán. ¿Desea continuar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2b7fff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, volver',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: '#1c1c1c',
                color: '#e4e4e4',
                iconColor: '#ffc107'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = window.location.pathname;
            });
            <?php else: ?>
            Swal.fire({
                title: '¿Volver al inicio?',
                text: 'Se cerrará la sesión actual.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#2b7fff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, volver',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: '#1c1c1c',
                color: '#e4e4e4',
                iconColor: '#60b0ff'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = window.location.pathname;
            });
            <?php endif; ?>
        });

        // Botón Recalcular
        document.getElementById('btnRecalcular').addEventListener('click', function() {
            const campos = {
                periodo: document.getElementById('hidden_periodo').value,
                organismo: document.getElementById('hidden_organismo').value,
                empresa: document.getElementById('hidden_empresa').value,
                provincia: document.getElementById('hidden_provincia').value,
                nombre_servicio: document.getElementById('hidden_nombre_servicio').value,
                codigo_servicio: document.getElementById('hidden_codigo_servicio').value,
                categoria: document.getElementById('hidden_categoria').value,
                nivel_produccion: document.getElementById('hidden_nivel_produccion').value,
                fecha_emision: document.getElementById('hidden_fecha_emision').value,
                vigencia_valor: document.getElementById('hidden_vigencia_valor').value,
                vigencia_unidad: document.getElementById('hidden_vigencia_unidad').value,
                capacidad: document.getElementById('hidden_capacidad').value,
                salario_trabajador: document.getElementById('hidden_salario_trabajador').value,
                num_trabajadores: document.getElementById('hidden_num_trabajadores').value,
                tope_ss: document.getElementById('hidden_tope_ss').value,
                margen_utilidad: document.getElementById('hidden_margen_utilidad').value,
                costo_fijo_hoja: document.getElementById('hidden_costo_fijo_hoja').value,
                costo_fijo_valor: document.getElementById('hidden_costo_fijo_valor').value,
                energia: document.getElementById('hidden_energia').value,
                agua: document.getElementById('hidden_agua').value,
                precio_adhesivo: document.getElementById('hidden_precio_adhesivo').value,
                uds_adhesivo: document.getElementById('hidden_uds_adhesivo').value,
                precio_tinta: document.getElementById('hidden_precio_tinta').value,
                duracion_kit_tinta: document.getElementById('hidden_duracion_kit').value,
                gasto_transportacion: document.getElementById('hidden_gasto_transportacion').value,
                valor_activo: document.getElementById('hidden_valor_activo').value,
                depreciacion: document.getElementById('hidden_depreciacion').value,
                imp_ventas: document.getElementById('hidden_imp_ventas').value,
                contrib_local: document.getElementById('hidden_contrib_local').value,
                imp_gobierno: document.getElementById('hidden_imp_gobierno').value,
                otros_gastos: document.getElementById('hidden_otros_gastos').value,
                vacaciones: document.getElementById('hidden_vacaciones').value,
                fuerza_trabajo: document.getElementById('hidden_fuerza_trabajo').value,
                manual_totales: document.getElementById('hidden_manual_totales').value,
                salario_total_m: document.getElementById('hidden_salario_total_m').value,
                ss_total_m: document.getElementById('hidden_ss_total_m').value,
                vacaciones_total_m: document.getElementById('hidden_vacaciones_total_m').value,
                fuerza_total_m: document.getElementById('hidden_fuerza_total_m').value,
                depreciacion_total_m: document.getElementById('hidden_depreciacion_total_m').value,
                factor_capacidad: document.getElementById('hidden_factor_capacidad').value,
                ocultar_ceros: document.getElementById('hidden_ocultar_ceros').value
            };
            // Período
            const periodoRadio = document.querySelector(`input[name="modal_periodo"][value="${campos.periodo}"]`);
            if (periodoRadio) periodoRadio.checked = true;
            // Capacidad de producción
            const selectCap = document.getElementById('modal_capacidad_produccion');
            if (selectCap) {
                for (let opt of selectCap.options) {
                    if (parseFloat(opt.value) === parseFloat(campos.factor_capacidad)) {
                        selectCap.value = opt.value;
                        break;
                    }
                }
            }
            // Checkbox
            const chk = document.getElementById('modal_ocultar_ceros');
            if (chk) chk.checked = (parseInt(campos.ocultar_ceros) === 1);
            const chkManual = document.getElementById('modal_manual_totales');
            if (chkManual) chkManual.checked = (parseInt(campos.manual_totales) === 1);
            const chkCostoFijo = document.getElementById('modal_costo_fijo_hoja');
            if (chkCostoFijo) chkCostoFijo.checked = (parseInt(campos.costo_fijo_hoja) === 1);
            // Resto
            const mapeo = {
                'modal_organismo': campos.organismo,
                'modal_empresa': campos.empresa,
                'modal_provincia': campos.provincia,
                'modal_nombre_servicio': campos.nombre_servicio,
                'modal_codigo_servicio': campos.codigo_servicio,
                'modal_categoria': campos.categoria,
                'modal_nivel_produccion': campos.nivel_produccion,
                'modal_fecha_emision': campos.fecha_emision,
                'modal_vigencia_valor': campos.vigencia_valor,
                'modal_vigencia_unidad': campos.vigencia_unidad,
                'modal_capacidad': campos.capacidad,
                'modal_salario_trabajador': campos.salario_trabajador,
                'modal_num_trabajadores': campos.num_trabajadores,
                'modal_tope_ss': campos.tope_ss,
                'modal_margen_utilidad': campos.margen_utilidad,
                'modal_costo_fijo_valor': campos.costo_fijo_valor,
                'modal_energia': campos.energia,
                'modal_agua': campos.agua,
                'modal_precio_adhesivo': campos.precio_adhesivo,
                'modal_uds_adhesivo': campos.uds_adhesivo,
                'modal_precio_tinta': campos.precio_tinta,
                'modal_duracion_kit': campos.duracion_kit_tinta,
                'modal_transporte': campos.gasto_transportacion,
                'modal_valor_activo': campos.valor_activo,
                'modal_depreciacion': campos.depreciacion,
                'modal_imp_ventas': campos.imp_ventas,
                'modal_contrib_local': campos.contrib_local,
                'modal_imp_gobierno': campos.imp_gobierno,
                'modal_otros_gastos': campos.otros_gastos,
                'modal_vacaciones': campos.vacaciones,
                'modal_fuerza_trabajo': campos.fuerza_trabajo,
                'modal_salario_total_m': campos.salario_total_m,
                'modal_ss_total_m': campos.ss_total_m,
                'modal_vacaciones_total_m': campos.vacaciones_total_m,
                'modal_fuerza_total_m': campos.fuerza_total_m,
                'modal_depreciacion_total_m': campos.depreciacion_total_m
            };
            for (let id in mapeo) {
                const el = document.getElementById(id);
                if (el) el.value = mapeo[id];
            }
            actualizarDiasSegunPeriodo();
            modal.show();
        });

        // Botón Metodología
        const modalMetodologia = new bootstrap.Modal(document.getElementById('modalMetodologia'), { backdrop: 'static', keyboard: true });
        document.getElementById('btnMetodologia').addEventListener('click', function() {
            modalMetodologia.show();
        });

        // Exportar Excel (usa los valores actuales del formulario oculto)
        document.getElementById('btnExportarExcel').addEventListener('click', function() {
            const formPrincipal = document.getElementById('formPrincipal');
            const inputAction = formPrincipal.querySelector('input[name="action"]');
            if (inputAction) inputAction.value = 'excel';
            formPrincipal.submit();
        });

        // Exportar CSV
        document.getElementById('btnExportarCSV').addEventListener('click', function() {
            const tabla = document.querySelector('.ficha');
            if (!tabla) {
                Swal.fire({
                    title: 'No hay datos',
                    text: 'Primero debe generar la ficha de costo para exportar a CSV.',
                    icon: 'info',
                    confirmButtonColor: '#2b7fff',
                    background: '#1c1c1c',
                    color: '#e4e4e4'
                });
                return;
            }
            let csv = '';
            const filas = tabla.querySelectorAll('tr');
            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('td, th');
                const filaCSV = [];
                celdas.forEach(celda => {
                    let texto = celda.textContent.trim().replace(/\s+/g, ' ').replace(/"/g, '""');
                    filaCSV.push(`"${texto}"`);
                });
                csv += filaCSV.join(',') + '\n';
            });
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Ficha_Costo_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);
            Swal.fire({ title: '¡Exportado!', text: 'CSV descargado correctamente.', icon: 'success', timer: 2000, showConfirmButton: false, background: '#1c1c1c', color: '#e4e4e4' });
        });

        // Exportar PDF
        document.getElementById('btnExportarPdfOficial').addEventListener('click', function() {
            const tabla = document.querySelector('.ficha');
            if (!tabla) {
                Swal.fire({
                    title: 'No hay datos',
                    text: 'Primero debe generar la ficha de costo para exportar a PDF.',
                    icon: 'info',
                    confirmButtonColor: '#2b7fff',
                    background: '#1c1c1c',
                    color: '#e4e4e4'
                });
                return;
            }
            if (typeof datosPdf === 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudieron cargar los datos para el PDF.',
                    icon: 'error',
                    confirmButtonColor: '#2b7fff',
                    background: '#1c1c1c',
                    color: '#e4e4e4'
                });
                return;
            }
            let ocultarCeros = 1;
            const chk = document.getElementById('modal_ocultar_ceros');
            if (chk) ocultarCeros = chk.checked ? 1 : 0;
            window.exportarPdfFichaCosto(datosPdf, ocultarCeros);
        });

        // Imprimir
        document.getElementById('btnImprimir').addEventListener('click', function() {
            const tabla = document.querySelector('.ficha');
            if (!tabla) {
                Swal.fire({
                    title: 'No hay datos',
                    text: 'Primero debe generar la ficha de costo para imprimir.',
                    icon: 'info',
                    confirmButtonColor: '#2b7fff',
                    background: '#1c1c1c',
                    color: '#e4e4e4'
                });
                return;
            }
            if (typeof datosPdf === 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudieron cargar los datos para la impresión.',
                    icon: 'error',
                    confirmButtonColor: '#2b7fff',
                    background: '#1c1c1c',
                    color: '#e4e4e4'
                });
                return;
            }
            let ocultarCeros = 1;
            const chk = document.getElementById('modal_ocultar_ceros');
            if (chk) ocultarCeros = chk.checked ? 1 : 0;
            window.imprimirPdfFichaCosto(datosPdf, ocultarCeros);
        });

        // Animación
        const styleAnim = document.createElement('style');
        styleAnim.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(styleAnim);

        // Si ya hay una ficha generada, ocultar bienvenida
        <?php if (isset($_POST['action']) && $_POST['action'] == 'generate'): ?>
        bienvenida.style.display = 'none';
        principal.style.display = 'block';
        <?php endif; ?>
    });
</script>
</body>
</html>