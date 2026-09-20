<?php
// exportar_historico.php - Archivo separado para exportaciones
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si es una exportación
$tipo_exportacion = $_GET['exportar'] ?? '';
if (!in_array($tipo_exportacion, ['excel', 'word', 'pdf', 'csv'])) {
    die('Tipo de exportación no válido');
}

try {
    $db = Database::getConnection();
    
    // Filtros
    $filtro_usuario = $_GET['usuario'] ?? '';
    $filtro_tipo = $_GET['tipo'] ?? '';
    $filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
    $filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
    $filtro_busqueda = $_GET['busqueda'] ?? '';
    
    // Construir consulta con filtros
    $where_conditions = [];
    $params = [];

    if (!empty($filtro_usuario)) {
        $where_conditions[] = "h.usuario_id = :usuario_id";
        $params[':usuario_id'] = $filtro_usuario;
    }

    if (!empty($filtro_tipo)) {
        $where_conditions[] = "h.operacion = :tipo";
        $params[':tipo'] = $filtro_tipo;
    }

    if (!empty($filtro_fecha_desde)) {
        $where_conditions[] = "DATE(h.fecha_hora) >= :fecha_desde";
        $params[':fecha_desde'] = $filtro_fecha_desde;
    }

    if (!empty($filtro_fecha_hasta)) {
        $where_conditions[] = "DATE(h.fecha_hora) <= :fecha_hasta";
        $params[':fecha_hasta'] = $filtro_fecha_hasta;
    }

    if (!empty($filtro_busqueda)) {
        $where_conditions[] = "(h.operacion LIKE :busqueda_operacion OR h.descripcion LIKE :busqueda_descripcion OR h.usuario_nombre LIKE :busqueda_usuario)";
        $params[':busqueda_operacion'] = "%$filtro_busqueda%";
        $params[':busqueda_descripcion'] = "%$filtro_busqueda%";
        $params[':busqueda_usuario'] = "%$filtro_busqueda%";
    }

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Obtener total de registros
    $sql_count = "SELECT COUNT(*) as total FROM historico_operaciones h $where_sql";
    $stmt = $db->prepare($sql_count);
    $stmt->execute($params);
    $total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener todos los registros
    $sql_historico = "SELECT h.*, u.usuario as usuario_login
                      FROM historico_operaciones h
                      LEFT JOIN clasif_usuarios u ON h.usuario_id = u.id
                      $where_sql
                      ORDER BY h.fecha_hora DESC";
    
    $stmt = $db->prepare($sql_historico);
    $stmt->execute($params);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Configuración para exportación
    $configuracion = [
        'filtro_usuario' => $filtro_usuario,
        'filtro_tipo' => $filtro_tipo,
        'filtro_fecha_desde' => $filtro_fecha_desde,
        'filtro_fecha_hasta' => $filtro_fecha_hasta,
        'filtro_busqueda' => $filtro_busqueda,
        'total_registros' => $total_registros,
        'usuario_actual' => $_SESSION['usuario_nombre'] ?? 'Usuario'
    ];
    
    // Función para exportar histórico
    function exportarHistorico($datos, $tipo, $configuracion = []) {
        $fecha_exportacion = date('Y-m-d_H-i-s');
        $nombre_archivo = "historico_actividades_{$fecha_exportacion}";
        
        switch ($tipo) {
            case 'excel':
                exportarExcel($datos, $nombre_archivo, $configuracion);
                break;
            case 'word':
                exportarWord($datos, $nombre_archivo, $configuracion);
                break;
            case 'pdf':
                exportarPDF($datos, $nombre_archivo, $configuracion);
                break;
            case 'csv':
                exportarCSV($datos, $nombre_archivo, $configuracion);
                break;
        }
    }
    
    // Función para exportar a Excel
    function exportarExcel($datos, $nombre_archivo, $configuracion) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.xls"');
        header('Cache-Control: max-age=0');
        
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'table { border-collapse: collapse; width: 100%; }';
        echo 'th { background-color: #0078d4; color: white; padding: 8px; border: 1px solid #ddd; }';
        echo 'td { padding: 8px; border: 1px solid #ddd; }';
        echo '.titulo { font-size: 16px; font-weight: bold; margin-bottom: 10px; }';
        echo '.subtitulo { font-size: 14px; margin-bottom: 5px; }';
        echo '.info { font-size: 12px; color: #666; margin-bottom: 20px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // Encabezado del reporte
        echo '<div class="titulo">Histórico de Actividades - PDL Visiones</div>';
        echo '<div class="subtitulo">Fecha de exportación: ' . date('d/m/Y H:i:s') . '</div>';
        echo '<div class="subtitulo">Exportado por: ' . $configuracion['usuario_actual'] . '</div>';
        
        // Información de filtros
        if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
            !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
            echo '<div class="info">';
            echo '<strong>Filtros aplicados:</strong><br>';
            if (!empty($configuracion['filtro_usuario'])) echo '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
            if (!empty($configuracion['filtro_tipo'])) echo '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
            if (!empty($configuracion['filtro_fecha_desde'])) echo '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
            if (!empty($configuracion['filtro_fecha_hasta'])) echo '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
            if (!empty($configuracion['filtro_busqueda'])) echo '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
            echo '</div>';
        }
        
        echo '<div class="info">Total de registros: ' . $configuracion['total_registros'] . '</div>';
        
        // Tabla de datos
        echo '<table border="1">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Fecha/Hora</th>';
        echo '<th>Tipo</th>';
        echo '<th>Operación</th>';
        echo '<th>Usuario</th>';
        echo '<th>Descripción</th>';
        echo '<th>IP</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $contador = 1;
        foreach ($datos as $registro) {
            echo '<tr>';
            echo '<td>' . $contador++ . '</td>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) . '</td>';
            echo '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
            echo '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
            echo '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
            echo '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</body>';
        echo '</html>';
    }
    
    // Función para exportar a Word
    function exportarWord($datos, $nombre_archivo, $configuracion) {
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.doc"');
        header('Cache-Control: max-age=0');
        
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<style>';
        echo 'table { border-collapse: collapse; width: 100%; }';
        echo 'th { background-color: #0078d4; color: white; padding: 8px; border: 1px solid #ddd; }';
        echo 'td { padding: 8px; border: 1px solid #ddd; }';
        echo '.titulo { font-size: 16px; font-weight: bold; margin-bottom: 10px; }';
        echo '.subtitulo { font-size: 14px; margin-bottom: 5px; }';
        echo '.info { font-size: 12px; color: #666; margin-bottom: 20px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        
        // Encabezado del reporte
        echo '<div class="titulo">Histórico de Actividades - PDL Visiones</div>';
        echo '<div class="subtitulo">Fecha de exportación: ' . date('d/m/Y H:i:s') . '</div>';
        echo '<div class="subtitulo">Exportado por: ' . $configuracion['usuario_actual'] . '</div>';
        
        // Información de filtros
        if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
            !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
            echo '<div class="info">';
            echo '<strong>Filtros aplicados:</strong><br>';
            if (!empty($configuracion['filtro_usuario'])) echo '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
            if (!empty($configuracion['filtro_tipo'])) echo '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
            if (!empty($configuracion['filtro_fecha_desde'])) echo '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
            if (!empty($configuracion['filtro_fecha_hasta'])) echo '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
            if (!empty($configuracion['filtro_busqueda'])) echo '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
            echo '</div>';
        }
        
        echo '<div class="info">Total de registros: ' . $configuracion['total_registros'] . '</div>';
        
        // Tabla de datos
        echo '<table border="1">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Fecha/Hora</th>';
        echo '<th>Tipo</th>';
        echo '<th>Operación</th>';
        echo '<th>Usuario</th>';
        echo '<th>Descripción</th>';
        echo '<th>IP</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $contador = 1;
        foreach ($datos as $registro) {
            echo '<tr>';
            echo '<td>' . $contador++ . '</td>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) . '</td>';
            echo '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
            echo '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
            echo '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
            echo '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</body>';
        echo '</html>';
    }
    
    // Función para exportar a PDF usando TCPDF
    function exportarPDF($datos, $nombre_archivo, $configuracion) {
        // Verificar si TCPDF está disponible
        if (!class_exists('TCPDF')) {
            // Intentar cargar TCPDF
            $tcpdf_path = __DIR__ . '/lib/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once($tcpdf_path);
            } else {
                // Si no existe TCPDF, crear un PDF básico con HTML que el usuario puede guardar
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.pdf"');
                header('Cache-Control: max-age=0');
                
                // Generar HTML que puede ser guardado como PDF por el navegador
                echo '<!DOCTYPE html>';
                echo '<html>';
                echo '<head>';
                echo '<meta charset="UTF-8">';
                echo '<title>Histórico de Actividades</title>';
                echo '<style>';
                echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
                echo 'h1 { color: #0078d4; font-size: 18px; margin-bottom: 5px; }';
                echo 'h2 { font-size: 14px; margin-bottom: 10px; color: #666; }';
                echo 'table { border-collapse: collapse; width: 100%; font-size: 10px; margin-top: 20px; }';
                echo 'th { background-color: #0078d4; color: white; padding: 6px; border: 1px solid #ddd; text-align: left; }';
                echo 'td { padding: 6px; border: 1px solid #ddd; }';
                echo '.filtros { background-color: #f5f5f5; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 11px; }';
                echo '.footer { margin-top: 20px; font-size: 9px; color: #666; text-align: center; }';
                echo '@media print {';
                echo '  .page-break { page-break-after: always; }';
                echo '  body { margin: 15px; }';
                echo '}';
                echo '</style>';
                echo '</head>';
                echo '<body>';
                
                // Encabezado
                echo '<h1>Histórico de Actividades - PDL Visiones</h1>';
                echo '<h2>Fecha de exportación: ' . date('d/m/Y H:i:s') . '</h2>';
                echo '<h2>Exportado por: ' . $configuracion['usuario_actual'] . '</h2>';
                
                // Filtros aplicados
                if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
                    !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
                    echo '<div class="filtros">';
                    echo '<strong>Filtros aplicados:</strong><br>';
                    if (!empty($configuracion['filtro_usuario'])) echo '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
                    if (!empty($configuracion['filtro_tipo'])) echo '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
                    if (!empty($configuracion['filtro_fecha_desde'])) echo '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
                    if (!empty($configuracion['filtro_fecha_hasta'])) echo '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
                    if (!empty($configuracion['filtro_busqueda'])) echo '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
                    echo '</div>';
                }
                
                echo '<p><strong>Total de registros:</strong> ' . $configuracion['total_registros'] . '</p>';
                
                // Tabla de datos
                echo '<table>';
                echo '<thead>';
                echo '<tr>';
                echo '<th width="30">#</th>';
                echo '<th width="80">Fecha/Hora</th>';
                echo '<th width="80">Tipo</th>';
                echo '<th width="100">Operación</th>';
                echo '<th width="80">Usuario</th>';
                echo '<th width="150">Descripción</th>';
                echo '<th width="80">IP</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                $contador = 1;
                $registros_por_pagina = 35;
                $total_registros_export = count($datos);
                
                foreach ($datos as $index => $registro) {
                    echo '<tr>';
                    echo '<td>' . $contador++ . '</td>';
                    echo '<td>' . date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) . '</td>';
                    echo '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
                    echo '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
                    echo '</tr>';
                    
                    // Salto de página cada cierto número de registros
                    if ($index > 0 && $index % $registros_por_pagina == 0 && $index < $total_registros_export - 1) {
                        echo '</tbody></table>';
                        echo '<div class="page-break"></div>';
                        echo '<h1>Histórico de Actividades - PDL Visiones (Continuación)</h1>';
                        echo '<p>Página ' . (($index / $registros_por_pagina) + 1) . ' de ' . ceil($total_registros_export / $registros_por_pagina) . '</p>';
                        echo '<table>';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th width="30">#</th>';
                        echo '<th width="80">Fecha/Hora</th>';
                        echo '<th width="80">Tipo</th>';
                        echo '<th width="100">Operación</th>';
                        echo '<th width="80">Usuario</th>';
                        echo '<th width="150">Descripción</th>';
                        echo '<th width="80">IP</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                    }
                }
                
                echo '</tbody>';
                echo '</table>';
                
                // Pie de página
                echo '<div class="footer">';
                echo 'Exportado el ' . date('d/m/Y H:i:s') . ' | Página 1 de ' . ceil($total_registros_export / $registros_por_pagina);
                echo '</div>';
                
                echo '</body>';
                echo '</html>';
                return;
            }
        }
        
        // Si TCPDF está disponible, usarlo
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('PDL Visiones');
        $pdf->SetAuthor($configuracion['usuario_actual']);
        $pdf->SetTitle('Histórico de Actividades');
        $pdf->SetSubject('Reporte de histórico');
        
        // Eliminar header y footer por defecto
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Añadir página
        $pdf->AddPage();
        
        // Contenido
        $html = '<h1 style="color: #0078d4; font-size: 16px;">Histórico de Actividades - PDL Visiones</h1>';
        $html .= '<p style="font-size: 12px; color: #666;">Fecha de exportación: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<p style="font-size: 12px; color: #666;">Exportado por: ' . $configuracion['usuario_actual'] . '</p>';
        
        // Filtros aplicados
        if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
            !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
            $html .= '<div style="background-color: #f5f5f5; padding: 8px; margin: 10px 0; border-radius: 3px; font-size: 10px;">';
            $html .= '<strong>Filtros aplicados:</strong><br>';
            if (!empty($configuracion['filtro_usuario'])) $html .= '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
            if (!empty($configuracion['filtro_tipo'])) $html .= '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
            if (!empty($configuracion['filtro_fecha_desde'])) $html .= '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
            if (!empty($configuracion['filtro_fecha_hasta'])) $html .= '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
            if (!empty($configuracion['filtro_busqueda'])) $html .= '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
            $html .= '</div>';
        }
        
        $html .= '<p style="font-size: 11px;"><strong>Total de registros:</strong> ' . $configuracion['total_registros'] . '</p>';
        
        // Crear tabla
        $html .= '<table border="1" cellpadding="4" style="border-collapse: collapse; font-size: 9px;">';
        $html .= '<thead>';
        $html .= '<tr style="background-color: #0078d4; color: white;">';
        $html .= '<th width="25">#</th>';
        $html .= '<th width="60">Fecha/Hora</th>';
        $html .= '<th width="60">Tipo</th>';
        $html .= '<th width="80">Operación</th>';
        $html .= '<th width="70">Usuario</th>';
        $html .= '<th width="120">Descripción</th>';
        $html .= '<th width="60">IP</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $contador = 1;
        foreach ($datos as $registro) {
            $html .= '<tr>';
            $html .= '<td>' . $contador++ . '</td>';
            $html .= '<td>' . date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
            $html .= '<td>' . htmlspecialchars($registro['operacion']) . '</td>';
            $html .= '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
            $html .= '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        // Escribir HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Salida
        $pdf->Output($nombre_archivo . '.pdf', 'D');
    }
    
    // Función para exportar a CSV
    function exportarCSV($datos, $nombre_archivo, $configuracion) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.csv"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Encabezados informativos
        fputcsv($output, ['Histórico de Actividades - PDL Visiones']);
        fputcsv($output, ['Fecha de exportación:', date('d/m/Y H:i:s')]);
        fputcsv($output, ['Exportado por:', $configuracion['usuario_actual']]);
        fputcsv($output, ['Total de registros:', $configuracion['total_registros']]);
        fputcsv($output, []); // Línea en blanco
        
        // Información de filtros
        $filtros = ['Filtros aplicados:'];
        if (!empty($configuracion['filtro_usuario'])) $filtros[] = 'Usuario ID: ' . $configuracion['filtro_usuario'];
        if (!empty($configuracion['filtro_tipo'])) $filtros[] = 'Tipo: ' . $configuracion['filtro_tipo'];
        if (!empty($configuracion['filtro_fecha_desde'])) $filtros[] = 'Desde: ' . $configuracion['filtro_fecha_desde'];
        if (!empty($configuracion['filtro_fecha_hasta'])) $filtros[] = 'Hasta: ' . $configuracion['filtro_fecha_hasta'];
        if (!empty($configuracion['filtro_busqueda'])) $filtros[] = 'Búsqueda: ' . $configuracion['filtro_busqueda'];
        fputcsv($output, $filtros);
        fputcsv($output, []); // Línea en blanco
        
        // Encabezados de columnas
        fputcsv($output, ['#', 'Fecha/Hora', 'Tipo', 'Operación', 'Usuario', 'Descripción', 'IP']);
        
        // Datos
        $contador = 1;
        foreach ($datos as $registro) {
            $fila = [
                $contador++,
                date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])),
                $registro['operacion'],
                $registro['operacion'],
                $registro['usuario_nombre'] ?? 'Sistema',
                $registro['descripcion'] ?? '',
                $registro['ip_address'] ?? ''
            ];
            fputcsv($output, $fila);
        }
        
        fclose($output);
    }
    
    // Ejecutar exportación
    exportarHistorico($historico, $tipo_exportacion, $configuracion);
    
} catch (Exception $e) {
    error_log("Error al exportar histórico: " . $e->getMessage());
    die("Error al exportar el histórico. Por favor, intente nuevamente.");
}
?>