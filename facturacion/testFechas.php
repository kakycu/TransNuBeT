<?php
// test_fechas.php - Ejemplo de uso completo
require_once 'config/init.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Funciones Fechas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .resultado { background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 4px solid #0078d4; }
        .error { background: #ffe6e6; padding: 10px; margin: 10px 0; border-left: 4px solid #ff3333; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>Test de Funciones de Fechas</h1>";

// 1. Fecha de inicio de operaciones
echo "<h2>1. Fecha de Inicio de Operaciones</h2>";
echo "<div class='resultado'>";
echo "Fecha obtenida: " . obtenerFechaInicioOperaciones() . "<br>";
echo "Variable global: " . $GLOBALS['fecha_inicio_operaciones'] . "<br>";
echo "Formato 1 (texto): " . fechaInicioFormateada(1) . "<br>";
echo "Formato 2 (numérico): " . fechaInicioFormateada(2) . "<br>";
echo "Formato 8 (Mes/Año): " . fechaInicioFormateada(8) . "<br>";
echo "Formato 9 (Mes/Año): " . fechaInicioFormateada(9) . "<br>";
echo "Formato 13 (Período): " . fechaInicioFormateada(13) . "<br>";
echo "</div>";

// 2. Último día del mes (funciones varias)
echo "<h2>2. Último Día del Mes</h2>";

// Último día de la fecha de inicio
echo "<div class='resultado'>";
echo "<strong>Último día del mes de inicio:</strong><br>";
echo "Formato 1: " . ultimoDiaMesFechaInicio(1) . "<br>";
echo "Formato 2: " . ultimoDiaMesFechaInicio(2) . "<br>";
echo "Formato SQL: " . ultimoDiaMesFechaInicio('Y-m-d') . "<br>";
echo "</div>";

// Último día del mes actual
echo "<div class='resultado'>";
echo "<strong>Último día del mes actual:</strong><br>";
echo "Formato 1: " . ultimoDiaMesActual(1) . "<br>";
echo "Formato 2: " . ultimoDiaMesActual(2) . "<br>";
echo "</div>";

// Último día de diferentes fechas
echo "<div class='resultado'>";
echo "<strong>Último día de diferentes fechas:</strong><br>";
echo "2024-02-15: " . ultimoDiaMes('2024-02-15', 1) . "<br>";
echo "2024-02-15 (numérico): " . ultimoDiaMes('2024-02-15', 2) . "<br>";
echo "15/02/2024: " . ultimoDiaMes('15/02/2024', 1) . "<br>";
echo "2023-12-01: " . ultimoDiaMes('2023-12-01', 1) . "<br>";
echo "</div>";

// 3. Primer día del mes
echo "<h2>3. Primer Día del Mes</h2>";
echo "<div class='resultado'>";
echo "Primer día de 2024-02-15: " . primerDiaMes('2024-02-15', 1) . "<br>";
echo "Primer día del mes actual: " . primerDiaMes(null, 1) . "<br>";
echo "</div>";

// 4. Otras funciones útiles
echo "<h2>4. Otras Funciones de Fechas</h2>";
echo "<div class='resultado'>";
echo "¿Es último día de mes hoy? " . (esUltimoDiaMes() ? 'Sí' : 'No') . "<br>";
echo "¿Es último día de 2024-02-29? " . (esUltimoDiaMes('2024-02-29') ? 'Sí' : 'No') . "<br>";
echo "Días en mes de 2024-02-15: " . diasEnMes('2024-02-15') . "<br>";
echo "Días en mes actual: " . diasEnMes() . "<br>";
echo "Nombre del mes de 2024-02-15: " . nombreMes('2024-02-15') . "<br>";
echo "Nombre abreviado: " . nombreMes('2024-02-15', true) . "<br>";
echo "Año de 2024-02-15: " . obtenerAnio('2024-02-15') . "<br>";
echo "Mes numérico: " . obtenerMes('2024-02-15') . "<br>";
echo "Diferencia días entre 2024-01-01 y 2024-01-31: " . diferenciaDias('2024-01-01', '2024-01-31') . "<br>";
echo "¿Es válida 2024-02-30? " . (esFechaValida('2024-02-30') ? 'Sí' : 'No') . "<br>";
echo "¿Es válida 2024-02-29? " . (esFechaValida('2024-02-29') ? 'Sí' : 'No') . "<br>";
echo "</div>";

// 5. Ejemplo práctico para reportes
echo "<h2>5. Ejemplo Práctico para Reportes</h2>";
$fecha_ejemplo = '2024-03-15';
echo "<div class='resultado'>";
echo "<strong>Reporte para: " . formatearFecha(strtotime($fecha_ejemplo), 13) . "</strong><br>";
echo "Período: " . primerDiaMes($fecha_ejemplo, 1) . " al " . ultimoDiaMes($fecha_ejemplo, 1) . "<br>";
echo "Días en el período: " . diasEnMes($fecha_ejemplo) . "<br>";
echo "Nombre del archivo: reporte_" . formatearFecha(strtotime($fecha_ejemplo), 10) . ".pdf<br>";
echo "Consultas SQL: SELECT * FROM ventas WHERE fecha BETWEEN '" . primerDiaMes($fecha_ejemplo, 'Y-m-d') . "' AND '" . ultimoDiaMes($fecha_ejemplo, 'Y-m-d') . "'";
echo "</div>";

echo "</body></html>";
?>