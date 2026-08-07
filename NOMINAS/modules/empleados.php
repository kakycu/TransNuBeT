<?php
// modules/empleados.php - Refactorizado con diseño Windows 11 y Correcciones
require_once '../config.php';
require_once '../includes/funciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Detectar si es una petición AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// Ruta de tu logo
$ruta_logo = '../../images/logocorto.png';
$logo_base64 = '';

// Verificamos si el archivo existe para no romper el código
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';
$user_email = $_SESSION['usuario_email'] ?? $_SESSION['user_email'] ?? '';


// Verificar si se solicita exportar Anexo 14
if (isset($_GET['exportar_anexo14']) && $_GET['exportar_anexo14'] == '1') {
    exportar_anexo_14($pdo);
    exit;
}


// Configuración empresa
$config_empresa = ['nombre_empresa' => 'PDL TransNuBeT', 'jefe_proyecto' => 'Dainelys León Reyes', 'especialista_gestion' => 'Mailén Pérez García'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Obtener días laborables mensuales desde configuración
$stmt = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'param_dias_mensuales'");
$dias_mensuales_config = $stmt->fetchColumn();
$dias_mensuales = $dias_mensuales_config ? (int)$dias_mensuales_config : 24;

// Función para procesar y guardar foto como archivo desde base64
function procesarFotoArchivoDesdeBase64($base64Data, $id_trabajador = null, $ruta_actual = null) {
    if (empty($base64Data)) {
        return $ruta_actual;
    }
    
    if (strpos($base64Data, 'base64,') !== false) {
        $base64Data = explode('base64,', $base64Data)[1];
    }
    $imagen_decodificada = base64_decode($base64Data);
    if (!$imagen_decodificada) {
        return $ruta_actual;
    }
    
    $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/assets/imagenes/trabajadores/';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    
    $nombre_archivo = ($id_trabajador ? $id_trabajador : 'temp_' . time()) . '_' . time() . '.jpg';
    $ruta_completa = $carpeta . $nombre_archivo;
    $ruta_relativa = 'assets/imagenes/trabajadores/' . $nombre_archivo;
    
    if (file_put_contents($ruta_completa, $imagen_decodificada)) {
        if ($ruta_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $ruta_actual)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $ruta_actual);
        }
        return $ruta_relativa;
    }
    return $ruta_actual;
}

/**
 * Exporta la plantilla Anexo 14
 */

function exportar_anexo_14($pdo) {
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
    
    $nombre_empresa = $config['nombre_empresa'] ?? 'PDL TransNuBet';
    $jefe_proyecto = $config['jefe_proyecto'] ?? 'Dainelys León Reyes';
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
	$fecha_cliente = isset($_GET['fecha_cliente']) ? $_GET['fecha_cliente'] : date('d/m/Y-h:iA');
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

    // Salida
    $filename = 'ANEXO_14_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Obtener motivos de baja desde la base de datos
$stmt = $pdo->query("SELECT * FROM motivos_baja WHERE activo = 1 ORDER BY codigo");
$motivos_baja = $stmt->fetchAll();

// Procesar acciones CRUD (AJAX y Normales)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => '', 'id' => null, 'data' => null];
    
    if ($action === 'verificar_expediente') {
        $expediente = $_POST['expediente'] ?? '';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE codigo = ?");
        $stmt->execute([$expediente]);
        $existe = $stmt->fetchColumn() > 0;
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['existe' => $existe]);
        exit;
    }
    
if ($action === 'crear') {
    if (empty($_POST['area_id'])) {
        $response['message'] = "Error: Debe seleccionar un Área para el empleado.";
    } elseif (empty($_POST['codigo'])) {
        $response['message'] = "Error: El expediente es obligatorio.";
    } elseif (empty($_POST['nombres'])) {
        $response['message'] = "Error: Los nombres son obligatorios.";
    } elseif (empty($_POST['primer_apellido'])) {
        $response['message'] = "Error: El primer apellido es obligatorio.";
    } elseif (empty($_POST['segundo_apellido'])) {
        $response['message'] = "Error: El segundo apellido es obligatorio.";
    } elseif (empty($_POST['fecha_alta'])) {
        $response['message'] = "Error: La fecha de alta es obligatoria.";
    } elseif (empty($_POST['centro_costo_id'])) {
        $response['message'] = "Error: Debe seleccionar un Centro de Costo.";
    } elseif (empty($_POST['categoria_id'])) {
        $response['message'] = "Error: Debe seleccionar una Categoría Ocupacional.";
    } elseif (empty($_POST['escala_id'])) {
        $response['message'] = "Error: Debe seleccionar una Escala Salarial.";
    } elseif (empty($_POST['cargo_id'])) {
        $response['message'] = "Error: Debe seleccionar un Cargo.";
    } else {
        $ci_limpio = preg_replace('/\D/', '', $_POST['ci']);
        if (strlen($ci_limpio) !== 11) {
            $response['message'] = "Error: El CI debe tener exactamente 11 dígitos.";
        } else {
            // VALIDAR FECHA DE NACIMIENTO EN EL CI
            $anio = substr($ci_limpio, 0, 2);
            $mes = substr($ci_limpio, 2, 2);
            $dia = substr($ci_limpio, 4, 2);
            $anio_completo = ($anio < 24) ? (2000 + $anio) : (1900 + $anio);
            
            if (!checkdate((int)$mes, (int)$dia, (int)$anio_completo)) {
                $response['message'] = "Error: La fecha de nacimiento en el CI es inválida (formato: AAMMDD). Verifique día y mes.";
            } else {
                $cuenta_bancaria = preg_replace('/\D/', '', $_POST['cuentabanc'] ?? '');
                if (!empty($cuenta_bancaria) && !in_array(strlen($cuenta_bancaria), [14, 16])) {
                    $response['message'] = "Error: La Cuenta debe tener 14 o 16 dígitos.";
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE ci = ?");
                    $stmt->execute([$ci_limpio]);
                    if ($stmt->fetchColumn() > 0) {
                        $response['message'] = "Error: El Carnet de Identidad ya está registrado.";
                    } else {
                        $expediente = $_POST['codigo'];
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE codigo = ?");
                        $stmt->execute([$expediente]);
                        if ($stmt->fetchColumn() > 0) {
                            $response['message'] = "Error: El expediente ya está registrado.";
                        } else {
                            // CONTINUAR CON LA INSERCIÓN...
                            $stmt = $pdo->prepare("INSERT INTO trabajadores (codigo, ci, nombres, primer_apellido, segundo_apellido, 
                                             direccion_particular, telefono_contacto, email, cuentabanc, area_id, 
                                             centro_costo_id, categoria_ocupacional_id, escala_salarial_id, fecha_alta, cargo_id,
                                             tipo_contrato, vacaciones_acumuladas, no_acumular_vacaciones, fecha_baja, motivo_baja, activo)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $expediente, $ci_limpio, $_POST['nombres'], $_POST['primer_apellido'], $_POST['segundo_apellido'],
                                $_POST['direccion'], $_POST['telefono'], $_POST['email'], $cuenta_bancaria, $_POST['area_id'],
                                $_POST['centro_costo_id'] ?: null, $_POST['categoria_id'], $_POST['escala_id'], $_POST['fecha_alta'], 
                                $_POST['cargo_id'] ?: null,
                                $_POST['tipo_contrato'] ?? 'Indeterminado',
                                $_POST['vacaciones_acumuladas'] ?? 0, isset($_POST['no_acumular_vacaciones']) ? 1 : 0,
                                $_POST['fecha_baja'] ?: null, $_POST['motivo_baja'] ?: null, isset($_POST['activo']) ? 1 : 0
                            ]);
                            $id_nuevo = $pdo->lastInsertId();
                            
                            $foto_ruta = null;
                            if (isset($_POST['imagen_recortada']) && !empty($_POST['imagen_recortada'])) {
                                $foto_ruta = procesarFotoArchivoDesdeBase64($_POST['imagen_recortada'], $id_nuevo, null);
                                if ($foto_ruta) {
                                    $stmt = $pdo->prepare("UPDATE trabajadores SET foto_ruta = ? WHERE id = ?");
                                    $stmt->execute([$foto_ruta, $id_nuevo]);
                                }
                            }
                            
                            $response['success'] = true;
                            $response['message'] = "Trabajador agregado satisfactoriamente";
                            $response['id'] = $id_nuevo;
                            
                            $stmt = $pdo->prepare("
                                SELECT t.*, a.nombre_area, c.nombre as categoria_nombre, c.factor_incidencia, 
                                       e.salario_mensual, e.escala_numero, e.salario_hora_ordinaria,
                                       cc.nombre as centro_costo_nombre, cc.codigo as centro_costo_codigo,
                                       (COALESCE(t.vacaciones_acumuladas, 0) * (e.salario_mensual / $dias_mensuales)) as valor_vacaciones,
                                       (e.salario_mensual / $dias_mensuales) as valor_por_dia
                                FROM trabajadores t
                                LEFT JOIN areas a ON t.area_id = a.id
                                LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
                                LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
                                JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                                WHERE t.id = ?
                            ");
                            $stmt->execute([$id_nuevo]);
                            $response['data'] = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    }
                }
            }
        }
    }
} elseif ($action === 'editar') {
    $id = $_POST['id'];
    if (empty($_POST['area_id'])) {
        $response['message'] = "Error: Debe seleccionar un Área.";
    } elseif (empty($_POST['codigo'])) {
        $response['message'] = "Error: El expediente es obligatorio.";
    } elseif (empty($_POST['nombres'])) {
        $response['message'] = "Error: Los nombres son obligatorios.";
    } elseif (empty($_POST['primer_apellido'])) {
        $response['message'] = "Error: El primer apellido es obligatorio.";
    } elseif (empty($_POST['segundo_apellido'])) {
        $response['message'] = "Error: El segundo apellido es obligatorio.";
    } elseif (empty($_POST['fecha_alta'])) {
        $response['message'] = "Error: La fecha de alta es obligatoria.";
    } elseif (empty($_POST['centro_costo_id'])) {
        $response['message'] = "Error: Debe seleccionar un Centro de Costo.";
    } elseif (empty($_POST['categoria_id'])) {
        $response['message'] = "Error: Debe seleccionar una Categoría Ocupacional.";
    } elseif (empty($_POST['escala_id'])) {
        $response['message'] = "Error: Debe seleccionar una Escala Salarial.";
    } elseif (empty($_POST['cargo_id'])) {
        $response['message'] = "Error: Debe seleccionar un Cargo.";
    } else {
        $ci_limpio = preg_replace('/\D/', '', $_POST['ci']);
        if (strlen($ci_limpio) !== 11) {
            $response['message'] = "Error: El CI debe tener 11 dígitos.";
        } else {
            // VALIDAR FECHA DE NACIMIENTO EN EL CI
            $anio = substr($ci_limpio, 0, 2);
            $mes = substr($ci_limpio, 2, 2);
            $dia = substr($ci_limpio, 4, 2);
            $anio_completo = ($anio < 24) ? (2000 + $anio) : (1900 + $anio);
            
            if (!checkdate((int)$mes, (int)$dia, (int)$anio_completo)) {
                $response['message'] = "Error: La fecha de nacimiento en el CI es inválida (formato: AAMMDD). Verifique día y mes.";
            } else {
                $cuenta_bancaria = preg_replace('/\D/', '', $_POST['cuentabanc'] ?? '');
                if (!empty($cuenta_bancaria) && !in_array(strlen($cuenta_bancaria), [14, 16])) {
                    $response['message'] = "Error: La Cuenta/Tarjeta debe tener 14 o 16 dígitos.";
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE ci = ? AND id != ?");
                    $stmt->execute([$ci_limpio, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $response['message'] = "Error: El CI ya está registrado en otro empleado.";
                    } else {
                        $expediente = $_POST['codigo'];
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE codigo = ? AND id != ?");
                        $stmt->execute([$expediente, $id]);
                        if ($stmt->fetchColumn() > 0) {
                            $response['message'] = "Error: El expediente ya existe en otro empleado.";
                        } else {
                            // CONTINUAR CON LA ACTUALIZACIÓN...
                            $stmt = $pdo->prepare("SELECT foto_ruta FROM trabajadores WHERE id = ?");
                            $stmt->execute([$id]);
                            $foto_actual = $stmt->fetchColumn();
                            
                            $foto_ruta = $foto_actual;
                            if (isset($_POST['imagen_recortada']) && !empty($_POST['imagen_recortada'])) {
                                $foto_ruta = procesarFotoArchivoDesdeBase64($_POST['imagen_recortada'], $id, $foto_actual);
                            } elseif (isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1') {
                                if ($foto_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_actual)) {
                                    unlink($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_actual);
                                }
                                $foto_ruta = null;
                            }
                            
                            $stmt = $pdo->prepare("
                                UPDATE trabajadores SET 
                                    codigo = ?, ci = ?, nombres = ?, primer_apellido = ?, segundo_apellido = ?,
                                    direccion_particular = ?, telefono_contacto = ?, email = ?, cuentabanc = ?, area_id = ?,
                                    centro_costo_id = ?, categoria_ocupacional_id = ?, escala_salarial_id = ?, fecha_alta = ?, cargo_id = ?,
                                    tipo_contrato = ?, fecha_baja = ?, motivo_baja = ?, activo = ?, vacaciones_acumuladas = ?, 
                                    no_acumular_vacaciones = ?, foto_ruta = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $expediente, $ci_limpio, $_POST['nombres'], $_POST['primer_apellido'], $_POST['segundo_apellido'],
                                $_POST['direccion'], $_POST['telefono'], $_POST['email'], $cuenta_bancaria, $_POST['area_id'],
                                $_POST['centro_costo_id'] ?: null, $_POST['categoria_id'], $_POST['escala_id'], $_POST['fecha_alta'], 
                                $_POST['cargo_id'] ?: null,
                                $_POST['tipo_contrato'] ?? 'Indeterminado', $_POST['fecha_baja'] ?: null, $_POST['motivo_baja'] ?: null, isset($_POST['activo']) ? 1 : 0,
                                $_POST['vacaciones_acumuladas'] ?? 0, isset($_POST['no_acumular_vacaciones']) ? 1 : 0, $foto_ruta, $id
                            ]);
                            
                            $response['success'] = true;
                            $response['message'] = "Cambios actualizados correctamente";
                            $response['id'] = $id;
                            
                            $stmt = $pdo->prepare("
                                SELECT t.*, a.nombre_area, c.nombre as categoria_nombre, c.factor_incidencia, 
                                       e.salario_mensual, e.escala_numero, e.salario_hora_ordinaria,
                                       cc.nombre as centro_costo_nombre, cc.codigo as centro_costo_codigo,
                                       (COALESCE(t.vacaciones_acumuladas, 0) * (e.salario_mensual / $dias_mensuales)) as valor_vacaciones,
                                       (e.salario_mensual / $dias_mensuales) as valor_por_dia
                                FROM trabajadores t
                                LEFT JOIN areas a ON t.area_id = a.id
                                LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
                                LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
                                JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                                WHERE t.id = ?
                            ");
                            $stmt->execute([$id]);
                            $response['data'] = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    }
                }
            }
        }
    }
} elseif ($action === 'eliminar') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE trabajador_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $response['message'] = "No se puede eliminar porque tiene nóminas asociadas.";
        } else {
            $stmt = $pdo->prepare("SELECT foto_ruta FROM trabajadores WHERE id = ?");
            $stmt->execute([$id]);
            $foto_ruta = $stmt->fetchColumn();
            if ($foto_ruta && file_exists($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_ruta)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_ruta);
            }
            $stmt = $pdo->prepare("DELETE FROM trabajadores WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
            $response['message'] = "Empleado eliminado correctamente";
        }
    }
    
    // Si es AJAX, retornar JSON
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Si no es AJAX, redirigir
    if ($response['success']) {
        $_SESSION['flash_message'] = $response['message'];
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = $response['message'];
        $_SESSION['flash_type'] = "error";
    }
    header("Location: empleados.php");
    exit;
}

// Flash Messages
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Obtener datos auxiliares
$areas = getAreas($pdo);
$categorias = getCategoriasOcupacionales($pdo);
$escalas = getEscalas($pdo);
$centros_costo = getCentrosCosto($pdo);

// Obtener empleados
$empleados = $pdo->query("
    SELECT t.*, cp.nombre_cargo as cargo, a.nombre_area, c.nombre as categoria_nombre, c.factor_incidencia, 
           e.salario_mensual, e.escala_numero, e.salario_hora_ordinaria,
           cc.nombre as centro_costo_nombre, cc.codigo as centro_costo_codigo,
           (COALESCE(t.vacaciones_acumuladas, 0) * (e.salario_mensual / $dias_mensuales)) as valor_vacaciones,
           (e.salario_mensual / $dias_mensuales) as valor_por_dia
    FROM trabajadores t
    LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
    LEFT JOIN areas a ON t.area_id = a.id
    LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
    LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
")->fetchAll();

// Variable para abrir automáticamente el modal
$abrir_modal_con_id = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_buscado = intval($_GET['editar']);
    foreach ($empleados as $emp) {
        if ($emp['id'] == $id_buscado) {
            $abrir_modal_con_id = $id_buscado;
            break;
        }
    }
}

$total_trabajadores = count($empleados);

$lista_empleados = [];
foreach ($empleados as $emp) {
    $lista_empleados[] = [
        'id' => $emp['id'],
        'texto' => $emp['codigo'] . ' - ' . $emp['nombre_completo']
    ];
}

// ============================================
// TODOS LOS CUMPLEAÑOS (DESDE EL CI)
// ============================================
$cumpleaneros = [];
try {
    $stmt = $pdo->query("
        SELECT id, nombre_completo, ci, foto_ruta
        FROM trabajadores
        WHERE ci IS NOT NULL
          AND ci != ''
          AND ci != '00000000000'
          AND LENGTH(ci) >= 6
    ");
    $trabajadores_cumple = $stmt->fetchAll();

    $hoy = new DateTime();
    foreach ($trabajadores_cumple as $t) {
        $ci_limpio = preg_replace('/\D/', '', $t['ci']);
        if (strlen($ci_limpio) < 6) continue;

        $anio = substr($ci_limpio, 0, 2);
        $mes  = substr($ci_limpio, 2, 2);
        $dia  = substr($ci_limpio, 4, 2);

        if (!checkdate((int)$mes, (int)$dia, 2000)) continue;

        $anio_completo = ($anio < 24) ? (2000 + $anio) : (1900 + $anio);
        if ($anio_completo > $hoy->format('Y')) {
            $anio_completo -= 100;
        }

        $fecha_nacimiento = DateTime::createFromFormat('Y-m-d', $anio_completo . '-' . $mes . '-' . $dia);
        if (!$fecha_nacimiento) continue;

        $proximo = DateTime::createFromFormat('Y-m-d', $hoy->format('Y') . '-' . $fecha_nacimiento->format('m-d'));
        if (!$proximo) continue;
        if ($proximo < $hoy) {
            $proximo->modify('+1 year');
        }

        $dias = $hoy->diff($proximo)->days;
        $edad = $proximo->format('Y') - $fecha_nacimiento->format('Y');
        $cumpleaneros[] = [
            'id'        => $t['id'],
            'nombre'    => $t['nombre_completo'],
            'foto'      => $t['foto_ruta'] ? '../' . $t['foto_ruta'] : '',
            'fecha'     => $proximo->format('d/m/Y'),
            'edad'      => $edad,
            'dias'      => $dias
        ];
    }
    usort($cumpleaneros, function($a, $b) {
        return $a['dias'] <=> $b['dias'];
    });
} catch (PDOException $e) {
    $cumpleaneros = [];
    error_log("Error en cumpleaños (CI): " . $e->getMessage());
}

// ============================================
// ESTADÍSTICAS DE EMPLEADOS (activos, bajas, salario, vac. excedidas, género, edades)
// ============================================
$total_activos = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE())")->fetchColumn();

$bajas_del_anio = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE (activo = 0 OR (fecha_baja IS NOT NULL AND fecha_baja <= CURDATE())) AND YEAR(fecha_baja) = YEAR(CURDATE())")->fetchColumn();

$salario_promedio = $pdo->query("SELECT AVG(e.salario_mensual) FROM trabajadores t JOIN escalas_salariales e ON t.escala_salarial_id = e.id WHERE t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())")->fetchColumn() ?: 0;

$vacaciones_excedidas = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE()) AND vacaciones_acumuladas > 20")->fetchColumn();

$trabajadores_data = $pdo->query("SELECT id, ci, activo, fecha_baja FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE())")->fetchAll();
$hombres = 0;
$mujeres = 0;
foreach ($trabajadores_data as $t) {
    $ci = preg_replace('/\D/', '', $t['ci']);
    if (strlen($ci) >= 10) {
        $digito_gen = intval($ci[9]);
        if ($digito_gen % 2 == 0) $hombres++;
        else $mujeres++;
    }
}
$total_genero = $hombres + $mujeres;
$pct_hombres = $total_genero > 0 ? round(($hombres / $total_genero) * 100, 1) : 0;
$pct_mujeres = $total_genero > 0 ? round(($mujeres / $total_genero) * 100, 1) : 0;

$rangos_edad = ['18-25' => 0, '26-35' => 0, '36-45' => 0, '46-55' => 0, '56-65' => 0, '65+' => 0];
foreach ($trabajadores_data as $t) {
    $ci = preg_replace('/\D/', '', $t['ci']);
    if (strlen($ci) >= 6) {
        $anio = intval(substr($ci, 0, 2));
        $mes = intval(substr($ci, 2, 2));
        $dia = intval(substr($ci, 4, 2));
        $anio_completo = $anio < 30 ? 2000 + $anio : 1900 + $anio;
        $fecha_nac = mktime(0, 0, 0, $mes, $dia, $anio_completo);
        $edad = date('Y') - date('Y', $fecha_nac);
        if (date('md') < date('md', $fecha_nac)) $edad--;

        if ($edad >= 18 && $edad <= 25) $rangos_edad['18-25']++;
        elseif ($edad >= 26 && $edad <= 35) $rangos_edad['26-35']++;
        elseif ($edad >= 36 && $edad <= 45) $rangos_edad['36-45']++;
        elseif ($edad >= 46 && $edad <= 55) $rangos_edad['46-55']++;
        elseif ($edad >= 56 && $edad <= 65) $rangos_edad['56-65']++;
        elseif ($edad > 65) $rangos_edad['65+']++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Empleados</title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/cropper.css">
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" type="text/css" href="../css/bootstrap5.3.0/buttons.dataTables.min.css">    
<style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: #0c0c0c; overflow-x: hidden; color: #ffffff; }

        /* Windows 11 Acrylic Background */
        .win11-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Glassmorphism Effect */
        .glass-card {
            background: rgba(28, 28, 35, 0.6); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { transform: translateY(-2px); background: rgba(35, 35, 45, 0.7); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }

        /* Sidebar Windows 11 Style */
        .win-sidebar {
            position: fixed; left: 0; top: 0; height: 100vh; width: 260px;
            background: rgba(20, 20, 25, 0.85); backdrop-filter: blur(30px);
            border-right: 1px solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width: 80px; }
        .win-sidebar.collapsed .sidebar-text, .win-sidebar.collapsed .sidebar-expand-only { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding: 12px; }
        .win-sidebar.collapsed .nav-item i { margin: 0; font-size: 1.5rem; }

        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 20px; text-align: center; }
        .sidebar-logo h3 { font-size: 1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin: 0; }
        .sidebar-logo small { font-size: 0.7rem; color: rgba(255, 255, 255, 0.5); }

        .sidebar-nav { flex: 1; padding: 0 12px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px;
            margin-bottom: 6px; border-radius: 12px;
            color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
            cursor: pointer; text-decoration: none;
        }

        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 3px solid #60a5fa; }
        .nav-item i { width: 24px; font-size: 1.2rem; text-align: center; }
        .nav-item span { font-size: 0.9rem; font-weight: 500; }

        /* Main Content */
        .main-container { margin-left: 260px; transition: all 0.3s ease; min-height: 100vh; padding: 20px; }
        .main-container.expanded { margin-left: 80px; }

        /* Top Bar Windows 11 */
        .win-topbar {
            background: rgba(20, 20, 25, 0.7); backdrop-filter: blur(20px); border-radius: 16px;
            padding: 12px 24px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100 !important; position: relative !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        .page-title p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 4px 0 0; }

        /* User Menu & Dropdowns */
        .user-menu { display: flex; align-items: center; gap: 16px; }

        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 1050 !important; }
        .user-avatar:hover { transform: scale(1.05); }

        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
        .dropdown-menu-win {
            background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 12px !important;
            padding: 8px !important; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
        }
        .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 8px !important; padding: 10px 16px !important; font-size: 0.9rem !important; }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(96, 165, 250, 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(255, 255, 255, 0.1) !important; }
        .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }
        .dropdown-menu-win .dropdown-item small { font-size: 0.65rem; color: rgba(255,255,255,0.6) !important; }
        .dropdown-menu-win .dropdown-item:hover small { color: #ffffff !important; }

        /* Text visibility overrides */
        .text-muted { color: rgba(255, 255, 255, 0.65) !important; }
        
        /* Buttons Windows 11 */
        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px;
            border-radius: 10px; color: white; font-size: 0.85rem; transition: all 0.2s; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-1px); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-1px); }
        .btn-win-danger { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-danger:hover { background: rgba(220, 53, 69, 0.4); border-color: #dc3545; }
        .btn-win-sm { padding: 4px 12px; font-size: 0.75rem; color: #ffffff !important; }
        .btn-win-sm:hover { color: #ffffff !important; }

        /* Formularios y Modal */
        .form-label { color: rgba(255, 255, 255, 0.85); font-size: 0.85rem; font-weight: 500; margin-bottom: 8px; }
        .form-select, .form-control { background: rgba(20, 20, 25, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; color: #ffffff; padding: 8px 12px; }
        .form-select:focus, .form-control:focus { background: rgba(20, 20, 25, 0.9); border-color: #60a5fa; outline: none; box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2); color: #ffffff; }
        
        /* MODAL PRINCIPAL */
        .modal-content-win { 
            background: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%); 
            backdrop-filter: blur(10px); 
            border: 2px solid #60a5fa; 
            border-radius: 20px; 
            color: #ffffff; 
            box-shadow: 0 0 30px rgba(96, 165, 250, 0.3);
        }
        
        @keyframes modalGlow {
            0% { box-shadow: 0 0 0 0 rgba(96, 165, 250, 0); border-color: rgba(96, 165, 250, 0.3); }
            100% { box-shadow: 0 0 30px 5px rgba(96, 165, 250, 0.4); border-color: #60a5fa; }
        }
        
        .modal.show .modal-content-win {
            animation: modalGlow 0.5s ease-out forwards;
        }
        
        /* HEADER DEL MODAL - VERSIÓN CORREGIDA */
        .modal-header-win {
            background: linear-gradient(90deg, #0f0f1a 0%, #1a1a2e 100%);
            border-bottom: 2px solid #60a5fa;
            border-radius: 18px 18px 0 0;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
        }
        
        .modal-header-win .modal-title {
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            white-space: nowrap;
        }
        
        /* Wrapper del nombre del empleado */
.modal-empleado-nombre-wrapper {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 45%;
    text-align: center;
}
        
        /* Contenedor del nombre del empleado */
.modal-empleado-nombre {
    background: rgba(96, 165, 250, 0.15);
    border: 1px solid rgba(96, 165, 250, 0.3);
    border-radius: 40px;
    padding: 8px 20px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #a78bfa;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
    max-width: 100%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    letter-spacing: 0.3px;
    transition: all 0.3s ease;
}

.modal-empleado-nombre:hover {
    background: rgba(96, 165, 250, 0.25);
    border-color: #60a5fa;
    box-shadow: 0 0 12px rgba(96, 165, 250, 0.3);
}

.modal-empleado-nombre i {
    margin-right: 10px;
    font-size: 1rem;
    color: #60a5fa;
}
        
/* Controles de navegación - TAMAÑO FIJO MEJORADO */
.nav-controls {
    background: rgba(20, 20, 25, 0.9);
    border-radius: 40px;
    padding: 4px 12px;
    border: 1px solid rgba(96, 165, 250, 0.3);
    display: flex;
    align-items: center;
    gap: 8px;
    width: auto;
    min-width: 320px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.btn-nav {
    background: rgba(96, 165, 250, 0.2);
    border: none;
    color: #ffffff;
    padding: 6px 10px;
    border-radius: 50%;
    transition: all 0.2s ease;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
}

.btn-nav:hover:not(:disabled) {
    background: #60a5fa;
    transform: scale(1.08);
    color: white;
    box-shadow: 0 2px 8px rgba(96, 165, 250, 0.4);
}

.btn-nav:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: rgba(96, 165, 250, 0.1);
}

/* Selector de navegación */
.nav-selector {
    margin: 0 4px;
}

.nav-selector select {
    background: rgba(20, 20, 25, 0.8);
    border: 1px solid rgba(96, 165, 250, 0.3);
    border-radius: 20px;
    color: #ffffff;
    font-size: 0.75rem;
    padding: 5px 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 180px;
}

.nav-selector select:hover {
    background: rgba(96, 165, 250, 0.2);
    border-color: #60a5fa;
}

.nav-selector select:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.3);
}

.nav-selector select option {
    background: #1a1a2e;
    color: #ffffff;
}

/* Contador de registros */
.nav-counter {
    min-width: 65px;
    text-align: center;
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 0.8rem;
    font-weight: 600;
    color: #60a5fa;
    background: rgba(0, 0, 0, 0.3);
    padding: 4px 8px;
    border-radius: 20px;
}

.nav-counter strong {
    color: #ffffff;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
    .nav-controls {
        min-width: 280px;
        padding: 3px 8px;
        gap: 4px;
    }
    
    .btn-nav {
        width: 28px;
        height: 28px;
        padding: 4px;
        font-size: 0.7rem;
    }
    
    .nav-selector select {
        min-width: 140px;
        font-size: 0.65rem;
        padding: 3px 8px;
    }
    
    .nav-counter {
        min-width: 50px;
        font-size: 0.7rem;
        padding: 2px 6px;
    }
}

@media (max-width: 576px) {
    .nav-selector {
        display: none;
    }
}
        
        .btn-close-white {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            background-size: 14px;
            opacity: 0.7;
            transition: all 0.2s;
        }
        
        .btn-close-white:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .modal-footer-win {
            background: linear-gradient(90deg, #0f0f1a 0%, #1a1a2e 100%);
            border-top: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 0 0 18px 18px;
        }
        
        /* Reducir espacio del modal */
        .modal-body-win { padding: 16px 20px !important; }
        
        .modal-body-win .row.g-3 { --bs-gutter-y: 0.5rem !important; margin-bottom: 0 !important; }
        .modal-body-win .mt-3 { margin-top: 0.5rem !important; }
        .modal-body-win .mt-4 { margin-top: 0.75rem !important; }
        .modal-body-win .mb-4 { margin-bottom: 0.5rem !important; }
        .modal-body-win .p-4 { padding: 0.75rem !important; }
        .modal-body-win .glass-card { margin-bottom: 0 !important; }
        .modal-body-win .glass-card.mt-4 { margin-top: 0.5rem !important; }
        .modal-body-win .glass-card .p-4 { padding: 0.75rem !important; }
        .modal-body-win .form-label { font-size: 0.75rem !important; margin-bottom: 0.25rem !important; }
        .modal-body-win .form-control, .modal-body-win .form-select { padding: 0.375rem 0.5rem !important; font-size: 0.8rem !important; }
        .modal-body-win .avatar-preview { width: 120px !important; height: 120px !important; }
        .modal-body-win .avatar-upload { max-width: 160px !important; margin-bottom: 0 !important; }
        .modal-body-win .text-center.mb-4 { margin-bottom: 0.5rem !important; }
        .modal-body-win .info-card { padding: 6px 10px !important; margin-bottom: 6px !important; }
        .modal-body-win .info-card-label { font-size: 0.6rem !important; }
        .modal-body-win .info-card-value { font-size: 0.75rem !important; }
        .modal-body-win .info-scroll { max-height: calc(80vh - 150px) !important; }
        .modal-footer-win .btn-win { padding: 4px 12px !important; font-size: 0.75rem !important; }

        /* Avatar y foto */
        .avatar-preview {
            width: 120px !important;
            height: 120px !important;
            border-radius: 50% !important;
            border: 3px solid #60a5fa;
            margin: 0 auto;
            overflow: hidden;
            background: rgba(20, 20, 25, 0.8);
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .avatar-preview img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
            display: block;
            transition: all 0.3s ease;
        }
        
        .avatar-preview img.logo-fallback {
            object-fit: contain !important;
            padding: 20px;
            background: rgba(96, 165, 250, 0.1);
        }
        
        .avatar-preview img[src*="base64"]:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 0 8px rgba(96, 165, 250, 0.5));
        }
        
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            border-radius: 50%;
            background: rgba(20, 20, 25, 0.8);
            flex-direction: column;
            font-size: 48px;
            color: rgba(255, 255, 255, 0.4);
            gap: 8px;
        }
        
        .avatar-placeholder i { font-size: 48px; }
        .avatar-placeholder span {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
            display: none;
        }
        
        .avatar-preview:hover .avatar-placeholder span { display: block; }
        
        .edit-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.75);
            color: white;
            text-align: center;
            padding: 8px;
            font-size: 12px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border-radius: 0 0 50% 50%;
        }
        
        .edit-overlay i { font-size: 12px; }
        .avatar-preview:hover .edit-overlay { opacity: 1 !important; }
        
        /* Info Cards en dos columnas */
        .info-sidebar { width: 100%; }
        .info-sidebar .row { margin: 0; }
        
        .info-card {
            background: rgba(20, 20, 25, 0.5);
            border-radius: 8px;
            padding: 6px 8px;
            margin-bottom: 6px;
            border-left: 2px solid #60a5fa;
            transition: all 0.2s ease;
        }
        
        .info-card:hover {
            background: rgba(30, 30, 40, 0.7);
            border-left-width: 3px;
        }
        
        .info-card-label {
            font-size: 0.6rem;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .info-card-value {
            font-size: 0.75rem;
            font-weight: 600;
            word-break: break-word;
        }
        
        /* Contenedor de la tabla */
        .data-table-wrapper { 
            position: relative !important; 
            max-height: 520px;
            overflow: auto !important;
            z-index: 1 !important;
            border-radius: 12px;
            background: rgba(20, 20, 25, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .data-table-wrapper table { 
            min-width: 1750px !important;
            width: 100%; 
            border-collapse: separate !important;
            border-spacing: 0;
            color: #ffffff; 
        }
        
        .data-table-wrapper th { 
            position: sticky !important; 
            top: 0 !important; 
            z-index: 10 !important; 
            background: rgba(22, 22, 30, 0.95) !important; 
            backdrop-filter: blur(5px);
            box-shadow: inset 0 -2px 0 rgba(255, 255, 255, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            font-weight: 600; 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            color: rgba(255, 255, 255, 0.85); 
            padding: 12px 16px; 
            border: none !important;
        }
        
        .data-table-wrapper tfoot td {
            position: sticky !important;
            bottom: 0 !important;
            z-index: 10 !important;
            background: rgba(22, 22, 30, 0.95) !important;
            backdrop-filter: blur(5px);
            box-shadow: inset 0 2px 0 rgba(255, 255, 255, 0.12), inset 0 -1px 0 rgba(255, 255, 255, 0.05);
            font-weight: bold;
            padding: 12px 16px;
            border: none !important;
        }
        
        .data-table-wrapper td { 
            padding: 10px 16px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.05); 
            background-color: transparent !important; 
        }
        .data-table-wrapper tr:hover td { background: rgba(255, 255, 255, 0.03) !important; }
        
        /* Filas seleccionables */
        #empleadosTable tbody tr.empleado-row {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        #empleadosTable tbody tr.empleado-row td:first-child,
        #empleadosTable tbody tr.empleado-row td:nth-child(2) {
            cursor: default;
        }
        
        /* DataTables */
        .dataTables_wrapper { color: #ffffff !important; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: rgba(255, 255, 255, 0.7) !important; }
        .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input { 
            background: rgba(20, 20, 25, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important; 
            border-radius: 8px !important; color: #ffffff !important; padding: 6px 12px !important; 
        }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: #60a5fa !important; outline: none !important; box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2) !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { 
            color: #ffffff !important; background: rgba(20, 20, 25, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important; 
            border-radius: 8px !important; padding: 6px 12px !important; margin: 0 3px !important; font-weight: 500 !important; 
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(96, 165, 250, 0.3) !important; border-color: #60a5fa !important; color: #ffffff !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: linear-gradient(135deg, #3b82f6, #8b5cf6) !important; border-color: #60a5fa !important; color: #ffffff !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { color: rgba(255, 255, 255, 0.4) !important; background: rgba(20, 20, 25, 0.4) !important; border-color: rgba(255, 255, 255, 0.08) !important; cursor: not-allowed !important; }
        
        table.dataTable, table.dataTable tbody tr { background: transparent !important; }
        table.dataTable tbody tr.even { background: rgba(255, 255, 255, 0.01) !important; }
        table.dataTable tbody tr.odd { background: transparent !important; }
        
        .data-table-wrapper table.dataTable tbody td, .data-table-wrapper table.dataTable tbody tr, 
        .data-table-wrapper table.dataTable tbody td a, .data-table-wrapper table.dataTable tbody td strong, 
        .data-table-wrapper table.dataTable tbody td span { color: #ffffff !important; }
        .data-table-wrapper table.dataTable tbody td.text-success { color: #4ade80 !important; }
        
        /* Alertas Vacaciones */
        #empleadosTable tbody tr.vacaciones-excedidas td { background-color: rgba(220, 53, 69, 0.12) !important; color: #ff8888 !important; }
        #empleadosTable tbody tr.vacaciones-excedidas td:first-child { border-left: 3px solid #dc3545; }
        .alerta-vacaciones-flotante { background: rgba(220, 53, 69, 0.15); border-left: 4px solid #ff4444; border-radius: 8px; padding: 12px 15px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; }
        .btn-cerrar-alerta { background: none; border: none; color: #ff8888; cursor: pointer; }
        #vacaciones_acumuladas.campo-vacaciones-alerta { background-color: rgba(220, 53, 69, 0.2) !important; border-color: #ff4444 !important; color: #ff8888 !important; }
        
        /* Cuenta bancaria */
        #digitosCuenta.valid { color: #10b981; font-weight: bold; }
        #digitosCuenta.invalid { color: #ef4444; font-weight: bold; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }
        
        /* Animaciones */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        
        /* Select personalizado */
        .form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            padding-right: 2.5rem !important;
        }
        
        /* Crop Modal */
        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            min-height: 400px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .preview-container {
            width: 180px;
            height: 180px;
            margin: 0 auto;
            overflow: hidden;
            border: 3px solid #60a5fa;
            border-radius: 50%;
            background: #2d2d2d;
        }
        
        #cropModal .modal-content {
            background: rgba(25, 25, 35, 0.98) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px !important;
        }
        
        /* Tooltips */
        .tooltip-inner {
            background-color: #1a1a2e !important;
            color: #ffffff !important;
            border: 1px solid #3b82f6 !important;
            font-size: 0.7rem !important;
            padding: 6px 10px !important;
            border-radius: 8px !important;
            max-width: 300px !important;
        }
        
        /* Foto en tabla */
        #empleadosTable tbody td:nth-child(2) img {
            width: 35px;
            height: 35px;
            object-fit: cover;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            border: 2px solid transparent;
        }
        
        #empleadosTable tbody td:nth-child(2) img:hover {
            transform: scale(1.8);
            border-color: #60a5fa;
            box-shadow: 0 0 15px rgba(96, 165, 250, 0.6);
            z-index: 100;
            position: relative;
        }
        
        /* Modal de foto */
        .foto-modal-win {
            border-radius: 20px !important;
            border: 1px solid rgba(96, 165, 250, 0.3) !important;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(10px) !important;
        }
        
        /* DataTables extras */
        .dt-buttons { display: none !important; gap: 8px; flex-wrap: wrap; }
        .dt-search .input-group-text { background-color: rgba(20, 20, 25, 0.8); border-color: rgba(255, 255, 255, 0.2); color: rgba(255, 255, 255, 0.7); }
        .dt-search input { background-color: rgba(20, 20, 25, 0.8); border-color: rgba(255, 255, 255, 0.2); color: #ffffff; }
        
        .dataTables_length {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            margin: 0 15px 0 0 !important;
        }
        
        .dataTables_length label {
            color: rgba(255, 255, 255, 0.7) !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 0 !important;
            font-size: 0.85rem !important;
        }
        
        .dataTables_length select {
            background: rgba(20, 20, 25, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 8px !important;
            color: #ffffff !important;
            padding: 4px 8px !important;
            width: auto !important;
            min-width: 70px !important;
        }
        
        div.dt-button-collection {
            background: rgba(32, 32, 40, 0.98) !important; 
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important; 
            border-radius: 12px !important;
            padding: 8px !important; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
        }
        
        .date-badge {
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
        }
        
        #liveClock {
            display: inline-block;
            min-width: 85px;
            text-align: center;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.5px;
        }
        
        /* Responsive */
        @media (max-width: 768px) { 
            .win-sidebar { transform: translateX(-100%); } 
            .main-container { margin-left: 0; }
            .dataTables_length { width: 100% !important; margin-bottom: 10px !important; }
            .info-card-label { font-size: 0.55rem; }
            .info-card-value { font-size: 0.65rem; }
            .info-card { padding: 4px 6px; margin-bottom: 4px; }
 .modal-empleado-nombre {
        font-size: 0.9rem;
        padding: 5px 12px;
    }
    
    .modal-empleado-nombre i {
        font-size: 0.8rem;
        margin-right: 6px;
    }
    
    .modal-empleado-nombre-wrapper {
        max-width: 40%;
    }
        }
        
        @media (max-width: 576px) {
            .info-sidebar .col-6 { width: 100%; }
            .modal-empleado-nombre { display: none; }
            .nav-controls { min-width: 130px; }
        }
/* Card tipo Solapín */
.solapin-card {
    background: linear-gradient(135deg, rgba(30, 30, 45, 0.9), rgba(20, 20, 35, 0.95));
    border-radius: 16px;
    border: 1px solid rgba(96, 165, 250, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.solapin-card:hover {
    transform: translateY(-2px);
    border-color: #60a5fa;
    box-shadow: 0 6px 16px rgba(96, 165, 250, 0.2);
}

.solapin-foto {
    position: relative;
    display: inline-block;
}

.solapin-foto img {
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.solapin-foto img:hover {
    transform: scale(1.05);
    border-color: #a78bfa !important;
}

.solapin-nombre {
    font-size: 0.9rem;
    color: #ffffff;
    border-bottom: 1px dashed rgba(96, 165, 250, 0.3);
    padding-bottom: 4px;
    display: inline-block;
}

.solapin-nombre i {
    color: #60a5fa;
}

.solapin-ci, .solapin-cargo {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.8);
    background: rgba(0, 0, 0, 0.2);
    padding: 4px 8px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.solapin-ci {
    border: 1px solid rgba(96, 165, 250, 0.3);
}

.solapin-cargo {
    border: 1px solid rgba(167, 139, 250, 0.3);
}

/* Responsive para móviles */
@media (max-width: 768px) {
    .solapin-nombre {
        font-size: 0.75rem;
    }
    .solapin-ci, .solapin-cargo {
        font-size: 0.6rem;
        padding: 2px 6px;
    }
    .solapin-foto img {
        width: 50px;
        height: 50px;
    }
}

/* Tarjetas de estadísticas (patrón común de la app) */
.stat-card {
    background: linear-gradient(135deg, rgba(28, 28, 35, 0.7), rgba(20, 20, 28, 0.8));
    border-radius: 16px; padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.3s ease; z-index: 1 !important;
}
.stat-card:hover { transform: translateY(-3px); border-color: rgba(96, 165, 250, 0.3); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
.stat-sub { font-size: 0.75rem; color: rgba(255, 255, 255, 0.55); margin-top: 2px; }
.progress-custom { height: 8px; background: rgba(255, 255, 255, 0.1); border-radius: 4px; overflow: hidden; }
.progress-custom-bar { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

/* Donut por género (conic-gradient) */
.donut-custom { position: relative; width: 170px; height: 170px; border-radius: 50%; margin: 0 auto; }
.donut-hole {
    position: absolute; top: 18px; left: 18px; width: 134px; height: 134px; border-radius: 50%;
    background: #14141c; display: flex; flex-direction: column; align-items: center; justify-content: center;
}
    </style>




</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    <!-- Top Bar Windows 11 -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1>Gestión de Empleados</h1>
                <p><i class="fas fa-users me-1"></i> Registro y control del personal - Ley 116</p>
            </div>
        </div>
        
<div class="user-menu">
    <button class="btn-win btn-win-primary" data-bs-toggle="modal" data-bs-target="#empleadoModal" onclick="clearFormAndStartNew()">
        <i class="fas fa-plus-circle"></i> Nuevo Empleado
    </button>

    <div class="dropdown">
        <button class="btn-win" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.08); border: none;">
            <i class="fas fa-cog me-1"></i> Opciones <i class="fas fa-chevron-down ms-2" style="color: rgba(255,255,255,0.6);"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="#" id="btnBackupManual">
                    <i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual
                    <small class="d-block text-muted">Crear copia de seguridad (SQL)</small>
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header text-light px-3 py-1">Personalizar Vista</h6></li>
            <li><a class="dropdown-item" href="#" id="menuColumnas"><i class="fas fa-columns me-2" style="color: #60a5fa;"></i> Mostrar/Ocultar Columnas</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header text-light px-3 py-1">Credenciales</h6></li>
            <li><a class="dropdown-item" href="#" id="menuExportTodosSolapines"><i class="fas fa-file-archive text-info me-2"></i> Exportar Todos los Solapines (ZIP)</a></li>
            <li><a class="dropdown-item" href="#" id="menuImprimirTodosSolapines"><i class="fas fa-print text-danger me-2"></i> Imprimir Todos los Solapines (Lote)</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header text-light px-3 py-1">Exportar y Reportes</h6></li>
            <li><a class="dropdown-item" href="#" id="menuExportPrint"><i class="fas fa-print text-warning me-2"></i> Imprimir Reporte</a></li>
            <li><a class="dropdown-item" href="#" id="menuExportPDF"><i class="fas fa-file-pdf text-danger me-2"></i> Exportar a PDF</a></li>
            <li><a class="dropdown-item" href="#" id="menuExportWord"><i class="fas fa-file-word text-primary me-2"></i> Exportar a Word</a></li>
            <li><a class="dropdown-item" href="#" id="menuExportExcel"><i class="fas fa-file-excel text-success me-2"></i> Exportar a Excel</a></li>
            <li><a class="dropdown-item" href="#" id="menuExportCSV"><i class="fas fa-file-csv text-info me-2"></i> Exportar a CSV</a></li>
            <li><a class="dropdown-item" href="#" id="menuExportTXT"><i class="fas fa-file-alt text-secondary me-2"></i> Exportar a TXT</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" id="menuExportAnexo14"><i class="fas fa-file-excel text-success me-2"></i> Exportar Anexo 14</a></li>
        </ul>
    </div>
    
    <?php include '../includes/user_menu.php'; ?>
</div>
	
	</div>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="row g-3 mb-4 fade-in-up">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-info"><?php echo $total_activos; ?></div>
                        <div class="stat-label">Trabajadores Activos</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(96, 165, 250, 0.15);"><i class="fas fa-user-check" style="color: #60a5fa;"></i></div>
                </div>
                <div class="progress-custom mt-2"><div class="progress-custom-bar" style="width: 100%; background: #60a5fa;"></div></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-danger"><?php echo $bajas_del_anio; ?></div>
                        <div class="stat-label">Bajas del Año</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15);"><i class="fas fa-user-minus" style="color: #ef4444;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-success">$<?php echo number_format($salario_promedio, 0); ?></div>
                        <div class="stat-label">Salario Promedio</div>
                        <div class="stat-sub">Escala salarial</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15);"><i class="fas fa-dollar-sign" style="color: #10b981;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-warning"><?php echo $vacaciones_excedidas; ?></div>
                        <div class="stat-label">Vac. Excedidas</div>
                        <div class="stat-sub">Más de 20 días acumulados</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15);"><i class="fas fa-umbrella-beach" style="color: #f59e0b;"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- GÉNERO Y RANGOS DE EDAD -->
    <div class="row g-3 mb-4 fade-in-up" style="animation-delay: 0.06s;">
        <div class="col-md-6">
            <div class="glass-card p-4 h-100">
                <h6 class="mb-3 text-light"><i class="fas fa-venus-mars me-2" style="color: #a78bfa;"></i> Distribución por Género</h6>
                <div class="row align-items-center">
                    <div class="col-md-5 text-center mb-3 mb-md-0">
                        <div class="donut-custom" style="background: conic-gradient(#3b82f6 0 <?php echo $pct_hombres; ?>%, #ec489a <?php echo $pct_hombres; ?>% 100%);">
                            <div class="donut-hole">
                                <div class="stat-value"><?php echo $total_genero; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="mb-3"><div class="d-flex justify-content-between"><span><i class="fas fa-mars me-2" style="color:#3b82f6;"></i> Masculino</span><span class="fw-bold"><?php echo $hombres; ?> (<?php echo $pct_hombres; ?>%)</span></div><div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $pct_hombres; ?>%; background: #3b82f6;"></div></div></div>
                        <div><div class="d-flex justify-content-between"><span><i class="fas fa-venus me-2" style="color:#ec489a;"></i> Femenino</span><span class="fw-bold"><?php echo $mujeres; ?> (<?php echo $pct_mujeres; ?>%)</span></div><div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $pct_mujeres; ?>%; background: #ec489a;"></div></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card p-4 h-100">
                <h6 class="mb-3 text-light"><i class="fas fa-chart-bar me-2" style="color: #60a5fa;"></i> Rangos de Edad</h6>
                <div class="row g-3">
                    <?php foreach ($rangos_edad as $rango => $cantidad): ?>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between"><span class="small"><?php echo $rango; ?> años</span><span class="fw-bold"><?php echo $cantidad; ?></span></div>
                        <div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_activos > 0 ? ($cantidad / $total_activos) * 100 : 0; ?>%; background: linear-gradient(90deg, #60a5fa, #a78bfa);"></div></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CUMPLEAÑOS + FILTROS -->
    <div class="row g-4 mb-4 fade-in-up" style="animation-delay: 0.1s;">
    
    <!-- CUMPLEAÑOS -->
    <div class="col-lg-6 col-12">
        <div class="glass-card h-100">
            <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold text-light">
                    <i class="fas fa-birthday-cake me-2"></i>Cumpleaños de los Empleados
                </h6>
                <span class="badge bg-success" id="cumpleCounter">0 de 0</span>
            </div>
            <div class="p-3">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="text-center" id="cumpleContainer">
                            <div id="cumpleContent">
                                <div class="py-5">
                                    <i class="fas fa-spinner fa-pulse fa-3x" style="color: rgba(255,255,255,0.3);"></i>
                                    <p class="mt-2" style="color: rgba(255,255,255,0.5);">Cargando cumpleañeros...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="glass-card p-2" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05);">
                            <h6 class="text-center mb-2" style="color: rgba(255,255,255,0.7); font-size: 0.8rem; letter-spacing: 0.5px;">
                                <i class="fas fa-list me-1"></i> Los 10 más próximos
                            </h6>
                            <div id="listaCumpleaneros" style="max-height: 350px; overflow-y: auto; padding-right: 5px;">
                                <!-- Se llena desde JavaScript -->
                                <div class="text-center py-3" style="color: rgba(255,255,255,0.3); font-size: 0.8rem;">
                                    <i class="fas fa-spinner fa-pulse me-1"></i> Cargando...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="col-lg-6 col-12">
    <div class="glass-card h-100">
        <div class="p-3 border-bottom border-white-10">
            <h6 class="mb-0 fw-semibold text-light"><i class="fas fa-filter me-2"></i> Filtros de Búsqueda</h6>
        </div>
        <div class="p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-user text-muted me-1"></i> Trabajador</label>
                    <select id="filtroEmpleado" class="form-select">
                        <option value="">-- Todos los empleados --</option>
                        <?php foreach ($lista_empleados as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['texto']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-file-signature text-muted me-1"></i> Tipo de Contrato</label>
                    <select id="filtroTipoContrato" class="form-select">
                        <option value="">-- Todos los tipos --</option>
                        <option value="Determinado">📅 Determinado</option>
                        <option value="Indeterminado">♾️ Indeterminado</option>
                        <option value="A Prueba">🔍 A Prueba</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-umbrella-beach text-muted me-1"></i> Pago Vacaciones 9.09%</label>
                    <select id="filtroPagoVacaciones" class="form-select">
                        <option value="">-- Todos --</option>
                        <option value="1">💲 Pagar 9.09% (No acumular)</option>
                        <option value="0">🏕️ Acumular vacaciones</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-building text-muted me-1"></i> Área</label>
                    <select id="filtroArea" class="form-select">
                        <option value="">-- Todas las áreas --</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['nombre_area']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-chart-pie text-muted me-1"></i> Centro de Costo</label>
                    <select id="filtroCentroCosto" class="form-select">
                        <option value="">-- Todos los centros --</option>
                        <?php foreach ($centros_costo as $cc): ?>
                            <option value="<?php echo $cc['id']; ?>"><?php echo htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-credit-card text-muted me-1"></i> Cuenta Bancaria</label>
                    <select id="filtroCuentaBancaria" class="form-select">
                        <option value="">-- Todos --</option>
                        <option value="con_cuenta">✅ Con cuenta bancaria</option>
                        <option value="sin_cuenta">❌ Sin cuenta bancaria</option>
                    </select>
                </div>
<div class="col-md-3">
    <label class="form-label"><i class="fas fa-camera text-muted me-1"></i> Foto de Perfil</label>
    <select id="filtroFoto" class="form-select">
        <option value="">-- Todos --</option>
        <option value="con_foto">📸 Con foto de perfil</option>
        <option value="sin_foto">👤 Sin foto de perfil</option>
    </select>
</div>
<div class="col-md-3">
    <label class="form-label"><i class="fas fa-calendar-times text-muted me-1"></i> Estado Laboral</label>
    <select id="filtroEstadoLaboral" class="form-select">
        <option value="">-- Todos --</option>
        <option value="activo">✅ Activos</option>
        <option value="inactivo">❌ Inactivos </option>
    </select>
</div>
            </div>
            <div class="mt-3 text-end">
                <button class="btn-win btn-win-sm" id="btnLimpiarFiltros"><i class="fas fa-eraser me-1"></i> Limpiar filtros</button>
            </div>
        </div>
    </div>
    </div>
    
    </div>
    
	
	
	
    <!-- TABLA DE EMPLEADOS -->
    <div class="glass-card fade-in-up" style="animation-delay: 0.2s;">
        <div class="data-table-wrapper p-4">
            <table class="table" id="empleadosTable">
                <thead>
                    <tr>
                        <th>Acciones</th>
                        <th>Foto</th>
                        <th>Expediente</th>
                        <th>CI</th>
                        <th>Nombre Completo</th>
                        <th>Área</th>
                        <th>Centro Costo</th>
                        <th>Categoría</th>
                        <th>Escala</th>
                        <th>Salario Mensual</th>
                        <th>Salario Hora</th>
                        <th>No. Cuenta/Tarjeta</th>
                        <th>Fecha Alta</th>
                        <th>Fecha Baja</th>
                        <th>Vac. Días</th>
                        <th>Vac. Impte.</th>
                        <th>Vac. a Pagar</th>
                        <th>Valor * Día</th>
                        <th>Estado</th>
                        <th>Tipo Contrato</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $emp): ?>
                    <?php 
                    $valor_a_pagar = 0;
                    $fila_roja = (($emp['vacaciones_acumuladas'] ?? 0) > 20);
                    if (($emp['no_acumular_vacaciones'] ?? 0) == 1) $valor_a_pagar = $emp['valor_vacaciones'];
                    ?>
                    <tr class="empleado-row <?php echo $fila_roja ? 'vacaciones-excedidas' : ''; ?>" data-id="<?php echo $emp['id']; ?>">
                        <td class="text-center">
                            <button class="btn-win btn-win-danger btn-win-sm" onclick="eliminarTrabajador(<?php echo $emp['id']; ?>, '<?php echo addslashes($emp['nombre_completo'] ?? ''); ?>')" title="Eliminar Empleado">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
<td class="text-center" data-tiene-foto="<?php echo (!empty($emp['foto_ruta']) && file_exists(__DIR__ . '/../' . $emp['foto_ruta'])) ? '1' : '0'; ?>">
    <?php if (!empty($emp['foto_ruta']) && file_exists(__DIR__ . '/../' . $emp['foto_ruta'])): ?>
        <img src="<?php echo '../' . $emp['foto_ruta']; ?>" 
             style="width: 35px; height: 35px; object-fit: cover; border-radius: 50%; cursor: pointer;" 
             onclick="verFotoGrande('<?php echo '../' . $emp['foto_ruta']; ?>', '<?php echo addslashes($emp['nombre_completo']); ?>', <?php echo $emp['id']; ?>)">
    <?php else: ?>
        <img src="<?php echo $logo_base64; ?>" 
             style="width: 35px; height: 35px; object-fit: contain; border-radius: 50%; cursor: pointer; background: rgba(96, 165, 250, 0.1); padding: 5px;" 
             onclick="verFotoGrande(null, '<?php echo addslashes($emp['nombre_completo']); ?>', <?php echo $emp['id']; ?>)"
             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'45\' fill=\'%233b82f6\'/%3E%3Ctext x=\'50\' y=\'65\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\' font-family=\'Arial\'%3E👤%3C/text%3E%3C/svg%3E'">
    <?php endif; ?>
</td>
                        <td><?php echo htmlspecialchars($emp['codigo'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($emp['ci'] ?? ''); ?></td>
                        <td>
                            <strong style="font-weight: 600; font-size: 0.95rem; color: #60a5fa;">
                                <?php echo htmlspecialchars($emp['nombre_completo'] ?? ''); ?>
                            </strong>
                        </td>
                        <td><?php echo htmlspecialchars($emp['nombre_area'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($emp['centro_costo_nombre'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($emp['categoria_nombre'] ?? ''); ?> (<small><?php echo ($emp['factor_incidencia'] ?? 1) * 100; ?>%</small>)</td>
                        <td class="text-center">Escala <?php echo numeroRomano($emp['escala_numero'] ?? 0); ?></td>
                        <td class="text-end"><?php echo formatearMoneda($emp['salario_mensual'] ?? 0); ?></td>
                        <td class="text-end"><?php echo formatearMoneda($emp['salario_hora_ordinaria'] ?? 0); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($emp['cuentabanc'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo $emp['fecha_alta'] ? date('d/m/Y', strtotime($emp['fecha_alta'])) : '-'; ?></td>
                        <td class="text-center"><?php echo $emp['fecha_baja'] ? date('d/m/Y', strtotime($emp['fecha_baja'])) : '-'; ?></td>
                        <td class="text-end fw-bold <?php echo $fila_roja ? 'text-danger' : 'text-info'; ?>">
                            <?php echo number_format((float)($emp['vacaciones_acumuladas'] ?? 0), 2); ?>
                        </td>
                        <td class="text-end text-success fw-bold"><?php echo formatearMoneda((float)($emp['valor_vacaciones'] ?? 0)); ?></td>
                        <td class="text-end fw-bold <?php echo ($emp['no_acumular_vacaciones'] ?? 0) == 1 ? 'text-warning' : 'text-secondary'; ?>">
                            <?php echo ($emp['no_acumular_vacaciones'] ?? 0) == 1 ? formatearMoneda((float)$valor_a_pagar) : '-'; ?>
                        </td>
                        <td class="text-end text-secondary"><?php echo formatearMoneda((float)($emp['valor_por_dia'] ?? 0)); ?></td>
                        <td class="text-center">
                            <?php if (($emp['activo'] ?? 0) && ($emp['fecha_baja'] === null || $emp['fecha_baja'] > date('Y-m-d'))): ?>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #4ade80;"><i class="fas fa-check-circle me-1"></i>Activo</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171;"><i class="fas fa-times-circle me-1"></i>Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                            $tipo_contrato = $emp['tipo_contrato'] ?? 'Indeterminado';
                            echo htmlspecialchars($tipo_contrato);
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="9" class="text-end" style="color: #60a5fa; padding: 12px;">
                            TOTALES:
                        </td>
                        <td class="text-end" style="color: #4ade80; padding: 12px; background: rgba(16, 185, 129, 0.1);">
                            <?php echo formatearMoneda((float)array_sum(array_column($empleados, 'salario_mensual') ?: [0])); ?>
                        </td>
                        <td class="text-end">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end" style="color: #4ade80; padding: 12px;">
                            <?php echo formatearMoneda((float)array_sum(array_column($empleados, 'valor_vacaciones') ?: [0])); ?>
                        </td>
                        <td class="text-end" style="color: #fbbf24; padding: 12px;">
                            <?php 
                            $total_pagar = 0;
                            foreach ($empleados as $emp) {
                                if (($emp['no_acumular_vacaciones'] ?? 0) == 1) $total_pagar += $emp['valor_vacaciones'];
                            }
                            echo formatearMoneda((float)$total_pagar);
                            ?>
                        </td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- MODAL PRINCIPAL: ENTRADA DE DATOS MEJORADO -->
<div class="modal fade" id="empleadoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-win">
<div class="modal-header modal-header-win py-2">
    <!-- Título fijo a la izquierda - ACORTADO -->
    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <i class="fas fa-user-edit" style="color: #60a5fa; font-size: 1.1rem;"></i>
        <h5 class="modal-title mb-0">Datos del Empleado</h5>
    </div>
    
    <!-- Nombre del empleado (centro) -->
    <div class="modal-empleado-nombre-wrapper">
        <div class="modal-empleado-nombre">
            <i class="fas fa-user-check"></i>
            <span id="modalEmpleadoNombre">Nuevo Empleado</span>
        </div>
    </div>
    
    <!-- Controles de navegación -->
    <div class="nav-controls" id="navControls" style="display: none;">
        <button type="button" class="btn-nav" id="btnPrimero" onclick="navegarRegistro('primero')" title="Primer registro">
            <i class="fas fa-angle-double-left"></i>
        </button>
        <button type="button" class="btn-nav" id="btnAnterior" onclick="navegarRegistro('anterior')" title="Anterior">
            <i class="fas fa-angle-left"></i>
        </button>
        
        <div class="nav-selector">
            <select id="navEmpleadoSelect" class="form-select form-select-sm" style="min-width: 140px; font-size: 0.7rem; padding: 4px 8px;" onchange="irAEmpleadoPorSelect(this.value)">
                <option value="">🔍 Buscar...</option>
            </select>
        </div>
        
        <span class="nav-counter">
            <strong id="registroActual">0</strong> / <strong id="totalRegistros">0</strong>
        </span>
        
        <button type="button" class="btn-nav" id="btnSiguiente" onclick="navegarRegistro('siguiente')" title="Siguiente">
            <i class="fas fa-angle-right"></i>
        </button>
        <button type="button" class="btn-nav" id="btnUltimo" onclick="navegarRegistro('ultimo')" title="Último registro">
            <i class="fas fa-angle-double-right"></i>
        </button>
    </div>
    
    <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="modal" onclick="cancelarNavegacion()"></button>
</div>
			
            <form method="POST" id="empleadoForm" enctype="multipart/form-data">
                <div class="modal-body modal-body-win py-2">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    <input type="hidden" name="id" id="empleadoId">
                    <input type="hidden" name="eliminar_foto" id="eliminarFoto" value="0">
                    <input type="hidden" name="imagen_recortada" id="imagen_recortada">
                    
                    <div class="row">
                        <!-- Columna Izquierda - Foto Compacta -->
<div class="col-md-3">
    <!-- Sección de foto -->
    <div class="text-center mb-2">
        <div class="avatar-preview mb-1" id="avatarPreviewContainer" onclick="abrirEditorFotoDesdePreview()" style="width: 100px; height: 100px;">
            <div id="fotoPlaceholder" class="avatar-placeholder"><i class="fas fa-camera" style="font-size: 32px;"></i></div>
            <img id="imagePreview" src="" style="display: none; width: 100%; height: 100%; object-fit: cover;">
            <div class="edit-overlay py-1" style="font-size: 9px;"><i class="fas fa-crop-alt me-1"></i> Editar</div>
        </div>
        
        <div class="d-flex gap-1 justify-content-center mt-1">
            <label class="btn-win btn-win-sm py-1" style="font-size: 0.7rem; margin: 0; flex: 1;">
                <i class="fas fa-upload me-1"></i> Subir Foto
                <input type="file" name="foto" id="imageUpload" accept="image/jpeg,image/png" hidden onchange="cargarImagenParaRecorte(this)">
            </label>
            <button type="button" class="btn-win btn-win-danger btn-win-sm py-1" id="btnEliminarFoto" style="display: none; font-size: 0.7rem; margin: 0; flex: 1;" onclick="eliminarFotoActual()">
                <i class="fas fa-trash-alt me-1"></i> Eliminar
            </button>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- CARD TIPO "SOLAPÍN" CON DATOS DEL EMPLEADO   -->
    <!-- ============================================ -->
	<div class="solapin-card glass-card mt-2 p-2 text-center" id="solapinCard">
		<!-- Foto pequeña en el solapín -->
		<div class="solapin-foto" id="solapinFoto">
			<img id="solapinFotoImg" src="<?php echo $logo_base64; ?>" alt="Foto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%; border: 2px solid #60a5fa;">
		</div>
		
		<!-- Nombre completo -->
		<div class="solapin-nombre mt-2" id="solapinNombre">
			<i class="fas fa-user me-1"></i>
			<strong id="solapinNombreSpan">Nuevo Empleado</strong>
		</div>
		
		<!-- Carnet de Identidad -->
		<div class="solapin-ci mt-1" id="solapinCI">
			<i class="fas fa-id-card me-1" style="color: #60a5fa;"></i>
			<span id="solapinCISpan">CI: --</span>
		</div>
		
		<!-- ID DEL TRABAJADOR (NUEVO) -->
		<div class="solapin-id mt-1" id="solapinID" style="font-size: 0.65rem; color: rgba(255, 255, 255, 0.5);">
			<i class="fas fa-hashtag me-1" style="color: #8b5cf6;"></i>
			<span id="solapinIDSpan">ID: --</span>
		</div>
		
		<!-- Cargo -->
		<div class="solapin-cargo mt-1" id="solapinCargo">
			<i class="fas fa-briefcase me-1" style="color: #a78bfa;"></i>
			<span id="solapinCargoSpan">Cargo: --</span>
		</div>
	</div>
<!-- NUEVOS BOTONES DE ACCIÓN RÁPIDA DEL SOLAPÍN DENTRO DEL MODAL -->
    <div class="d-flex flex-column gap-1 mt-2 w-100" id="solapinActionsContainer" style="display: none;">
        <button type="button" class="btn-win btn-win-primary btn-win-sm w-100 justify-content-center py-2" id="btnModalVerSolapin" style="font-size: 0.75rem; font-weight: 600;">
            <i class="fas fa-id-card me-2"></i> Ver Solapín
        </button>
        <div class="d-flex gap-1 w-100">
            <button type="button" class="btn-win btn-win-primary btn-win-sm flex-fill justify-content-center py-1.5" id="btnModalExportarSolapin" style="font-size: 0.72rem; font-weight: 600;" title="Exportar a PNG">
                <i class="fas fa-download me-1"></i> PNG
            </button>
            <button type="button" class="btn-win btn-win-primary btn-win-sm flex-fill justify-content-center py-1.5" id="btnModalExportarWordSolapin" style="font-size: 0.72rem; font-weight: 600;" title="Exportar a Word">
                <i class="fas fa-file-word me-1"></i> Word
            </button>
            <button type="button" class="btn-win btn-win-primary btn-win-sm flex-fill justify-content-center py-1.5" id="btnModalImprimirSolapin" style="font-size: 0.72rem; font-weight: 600;" title="Imprimir Solapín">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
        </div>
    </div>
    <!-- Info Cards en DOS COLUMNAS (datos adicionales) -->
    <div class="info-sidebar mt-2">
        <div class="row g-1">
            <!-- Columna 1 -->
            <div class="col-6">
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">ESTADO</div>
                    <div class="info-card-value small" id="infoEstado">
                        <span class="badge bg-secondary" style="font-size: 0.6rem;">No seleccionado</span>
                    </div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">ANTIGÜEDAD</div>
                    <div class="info-card-value small text-light" id="infoAntiguedad">--</div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">EDAD</div>
                    <div class="info-card-value small text-light" id="infoEdad">-- años</div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">SEXO</div>
                    <div class="info-card-value small text-light" id="infoSexo">--</div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">FECHA NAC.</div>
                    <div class="info-card-value small text-light" id="infoFechaNac">--</div>
                </div>
            </div>
            
            <!-- Columna 2 -->
            <div class="col-6">
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">SALARIO BASE</div>
                    <div class="info-card-value small text-success" id="infoSalarioBase">--</div>
                    <div class="info-card-value small" id="infoEscalaGrupo" style="font-size: 0.6rem; color: #60a5fa; margin-top: 4px; word-break: break-word;">--</div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">VALOR POR DÍA</div>
                    <div class="info-card-value small text-light" id="infoValorDia">--</div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">VACACIONES ACUM.</div>
                    <div class="info-card-value small text-info" id="infoVacDias">-- días</div>
                </div>
                
                <div class="info-card py-1 px-2">
                    <div class="info-card-label fs-10">VALOR VACACIONES</div>
                    <div class="info-card-value small text-warning" id="infoVacValor">--</div>
                </div>
            </div>
        </div>
    </div>
</div>
						
						
						<!-- Columna Derecha - Formulario con Tooltips -->
                        <div class="col-md-9">
                            <!-- Fila 1: Expediente, CI, Fecha Alta -->
                            <div class="row g-2">
								<div class="col-md-4">
									<label class="form-label small mb-0">
										<i class="fas fa-folder-open text-info me-1"></i>Expediente
										<i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Se genera automáticamente desde los últimos 6 dígitos del CI" style="cursor: help; font-size: 0.65rem;"></i>
									</label>
									<input type="text" class="form-control form-control-sm" name="codigo" id="codigo" readonly required
										   data-bs-toggle="tooltip" title="Expediente único del empleado - Solo lectura">
									<div id="workerIdDisplay" class="d-none" style="font-size: 0.95rem; margin-top: 2px;">
										<i class="fas fa-hashtag me-1" style="color: #60a5fa;"></i>
										ID: <span id="workerIdValue" style="color: #a78bfa; font-weight: 600;"></span>
									</div>
								</div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-id-card text-warning me-1"></i>CI <span class="text-danger">*</span>
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Carnet de Identidad: 11 dígitos numéricos (formato: AAMMDDXXXXX)" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="text" class="form-control form-control-sm" name="ci" id="ci" maxlength="11" oninput="actualizarExpedienteYInfo()" autofocus
                                           data-bs-toggle="tooltip" title="Ingrese los 11 dígitos del Carnet de Identidad">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-calendar-alt text-success me-1"></i>Fecha Alta <span class="text-danger">*</span>
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Fecha de ingreso del empleado a la empresa" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="date" class="form-control form-control-sm" name="fecha_alta" id="fecha_alta" onchange="actualizarInfoDesdeFormulario()"
                                           data-bs-toggle="tooltip" title="Seleccione la fecha de alta del empleado">
                                </div>
                            </div>
                            
                            <!-- Fila 2: Nombres, Primer Apellido, Segundo Apellido -->
                            <div class="row g-2 mt-2">
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-user text-primary me-1"></i>Nombres <span class="text-danger">*</span>
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Nombres completos del empleado (solo letras)" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="text" class="form-control form-control-sm" name="nombres" id="nombres" oninput="actualizarNombreModal()" onchange="actualizarInfoDesdeFormulario()"
                                           data-bs-toggle="tooltip" title="Ingrese los nombres completos">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-user-tag text-primary me-1"></i>Primer Apellido <span class="text-danger">*</span>
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Primer apellido del empleado (solo letras)" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="text" class="form-control form-control-sm" name="primer_apellido" id="primer_apellido" oninput="actualizarNombreModal()" onchange="actualizarInfoDesdeFormulario()"
                                           data-bs-toggle="tooltip" title="Ingrese el primer apellido">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-user-tag text-primary me-1"></i>Segundo Apellido <span class="text-danger">*</span>
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Segundo apellido del empleado (solo letras)" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="text" class="form-control form-control-sm" name="segundo_apellido" id="segundo_apellido" oninput="actualizarNombreModal()" onchange="actualizarInfoDesdeFormulario()"
                                           data-bs-toggle="tooltip" title="Ingrese el segundo apellido">
                                </div>
                            </div>
                            
                            <!-- Datos Contacto -->
                            <div class="glass-card mt-2 p-2">
                                <h6 class="text-light mb-1 fs-6"><i class="fas fa-address-card text-muted me-1"></i>Contacto y Banco</h6>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-home me-1"></i>Dirección
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Dirección particular del empleado" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <input type="text" class="form-control form-control-sm" name="direccion" id="direccion"
                                               data-bs-toggle="tooltip" title="Ej: Calle 5ta #123 e/ A y B, Reparto XYZ">
                                    </div>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-phone me-1"></i>Teléfono
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Número de contacto del empleado" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <input type="text" class="form-control form-control-sm" name="telefono" id="telefono"
                                               data-bs-toggle="tooltip" title="Ej: 53XXXXXXXXX">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-envelope me-1"></i>Email
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Correo electrónico del empleado" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <input type="email" class="form-control form-control-sm" name="email" id="email"
                                               data-bs-toggle="tooltip" title="Ej: nombre@empresa.com">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-credit-card me-1"></i>Cuenta Bancaria
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="14 o 16 dígitos numéricos (Cuenta de ahorro o Tarjeta magnética)" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-credit-card text-info"></i></span>
                                            <input type="text" class="form-control" name="cuentabanc" id="cuenta_bancaria" maxlength="16" oninput="validarCuentaBancaria(this)"
                                                   data-bs-toggle="tooltip" title="Ingrese 14 o 16 dígitos numéricos">
                                            <span class="input-group-text"><span id="digitosCuenta">0</span>/16</span>
                                        </div>
                                        <small id="cuentaHelp" class="text-muted d-block" style="font-size: 0.6rem;"><i class="fas fa-info-circle me-1"></i> 14 o 16 dígitos</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Datos Salariales -->
                            <div class="glass-card mt-2 p-2">
                                <h6 class="text-light mb-1 fs-6"><i class="fas fa-chart-line text-muted me-1"></i>Datos Salariales</h6>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-building me-1"></i>Área <span class="text-danger">*</span>
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Departamento o área donde labora el empleado" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <select class="form-select form-select-sm" name="area_id" id="area_id" onchange="actualizarInfoDesdeFormulario()"
                                                data-bs-toggle="tooltip" title="Seleccione el área de trabajo">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($areas as $area): ?>
                                                <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['nombre_area']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-chart-pie me-1"></i>Centro de Costo <span class="text-danger">*</span>
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Centro de costo para imputación de gastos" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <select class="form-select form-select-sm" name="centro_costo_id" id="centro_costo_id" onchange="actualizarInfoDesdeFormulario()"
                                                data-bs-toggle="tooltip" title="Seleccione el centro de costo">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($centros_costo as $cc): ?>
                                                <option value="<?php echo $cc['id']; ?>"><?php echo htmlspecialchars($cc['codigo'].' - '.$cc['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-chart-simple me-1"></i>Categoría <span class="text-danger">*</span>
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Categoría ocupacional del empleado (influye en factor de incidencia)" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <select class="form-select form-select-sm" name="categoria_id" id="categoria_id" onchange="actualizarInfoDesdeFormulario()"
                                                data-bs-toggle="tooltip" title="Seleccione la categoría ocupacional">
                                            <option value="">-- Seleccionar --</option>
											<?php foreach ($categorias as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-dollar-sign me-1"></i>Escala <span class="text-danger">*</span>
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Escala salarial según Gaceta Oficial" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <select class="form-select form-select-sm" name="escala_id" id="escala_id" onchange="actualizarSalarioDesdeEscala()"
                                                data-bs-toggle="tooltip" title="Seleccione la escala salarial (I a XXXII)">
                                            <option value="">-- Seleccionar Escala --</option>
                                            <?php foreach ($escalas as $esc): ?>
                                                <option value="<?php echo $esc['id']; ?>" 
                                                        data-salario="<?php echo $esc['salario_mensual']; ?>"
                                                        data-salario-hora="<?php echo $esc['salario_hora_ordinaria']; ?>"
                                                        data-escala-numero="<?php echo $esc['escala_numero']; ?>">
                                                    <?php echo 'Grupo: ' . numeroRomano($esc['escala_numero']) . ' ($' . number_format($esc['salario_mensual'], 2) . ' / $' . number_format($esc['salario_hora_ordinaria'], 2) . 'h)'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">
                                            <i class="fas fa-file-signature me-1"></i>Tipo de Contrato
                                            <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Según Ley 116: Determinado (plazo fijo), Indeterminado (sin fecha límite), A Prueba (período de prueba)" style="cursor: help; font-size: 0.65rem;"></i>
                                        </label>
                                        <select class="form-select form-select-sm" name="tipo_contrato" id="tipo_contrato" onchange="markFormDirty()">
											<option value="Indeterminado">♾️ Indeterminado (Sin fecha límite)</option>
											<option value="Determinado">📅 Determinado (Plazo fijo)</option>
                                            <option value="A Prueba">🔍 A Prueba (Período de prueba)</option>
                                        </select>
                                    </div>
<!-- Reemplazo del campo Cargo en el modal (Versión Corregida) -->
<div class="col-md-8">
    <label class="form-label small mb-0">
        <i class="fas fa-briefcase me-1 text-info"></i>Cargo / Técnica <span class="text-danger">*</span>
        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Seleccione el cargo. Completará de forma automática la escala y categoría" style="cursor: help; font-size: 0.65rem;"></i>
    </label>
    <select class="form-select form-select-sm" name="cargo_id" id="cargo_id" required onchange="vincularCargoPlantilla(this.value)">
        <option value="">-- Seleccionar Cargo --</option>
        <?php
        // CORRECCIÓN: Colocar 'organo_grupo' en primer lugar para que agrupe por texto de departamento, preservando el 'id' asociativo interno
        $stmt_cargos = $pdo->query("SELECT organo_grupo, id, nombre_cargo, categoria_ocupacional_id, escala_salarial_id FROM cargos_plantilla WHERE activo = 1 ORDER BY organo_grupo, nombre_cargo");
        $cargos_listado = $stmt_cargos->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
        
        foreach ($cargos_listado as $grupo => $cargos):
        ?>
            <optgroup label="<?php echo htmlspecialchars($grupo); ?>">
                <?php foreach ($cargos as $c): ?>
                    <option value="<?php echo $c['id']; ?>" 
                            data-categoria="<?php echo $c['categoria_ocupacional_id']; ?>"
                            data-escala="<?php echo $c['escala_salarial_id']; ?>">
                        <?php echo htmlspecialchars($c['nombre_cargo']); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
</div>    
<!-- NUEVO: Combo de Meses con Días y Horas Trabajados -->
<div class="col-md-4">
    <label class="form-label small mb-0">
        <i class="fas fa-calendar-check me-1 text-warning"></i>Mes - Días - Horas
        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Seleccione un mes para ver los días y horas trabajados en nóminas automáticas" style="cursor: help; font-size: 0.65rem;"></i>
    </label>
    <select class="form-select form-select-sm" id="mes_dias_trabajados" onchange="mostrarDiasTrabajados(this.value)">
        <option value="">-- Sin nóminas --</option>
    </select>
    <div id="detalle_dias_trabajados" class="mt-1" style="font-size: 0.75rem; color: rgba(255,255,255,0.7); display: none;">
        <i class="fas fa-clock me-1 text-info"></i>
        <span id="dias_trabajados_texto">0 días trabajados</span>
        <span id="horas_trabajadas_texto" style="margin-left: 10px; color: rgba(255,255,255,0.5);">(0 horas)</span>
    </div>
</div>
							   </div>
                            </div>
                            
                            <!-- Vacaciones -->
                            <div class="row g-2 mt-2 align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-umbrella-beach text-info me-1"></i>Vac. Acum. (días)
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Días de vacaciones acumulados según Ley 116 (máximo 22-24 días)" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" name="vacaciones_acumuladas" id="vacaciones_acumuladas" value="0" oninput="actualizarValoresVacaciones()"
                                           data-bs-toggle="tooltip" title="Ingrese la cantidad de días acumulados">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-0">
                                        <i class="fas fa-coins text-warning me-1"></i>Importe Vac. Acum.
                                        <i class="fas fa-question-circle text-muted" data-bs-toggle="tooltip" title="Valor monetario de las vacaciones acumuladas (Días × Valor por día)" style="cursor: help; font-size: 0.65rem;"></i>
                                    </label>
                                    <input type="text" class="form-control form-control-sm text-end" id="valor_vacaciones_calculado" readonly style="background-color: rgba(20,20,25,0.8); font-weight: bold;"
                                           data-bs-toggle="tooltip" title="Valor calculado automáticamente">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" class="form-check-input" style="width: 2em; height: 1em;" name="no_acumular_vacaciones" id="no_acumular_vacaciones" onchange="actualizarInfoDesdeFormulario()"
                                               data-bs-toggle="tooltip" title="Marque para que las vacaciones se paguen en nómina en lugar de acumularse">
                                        <label class="form-check-label small" for="no_acumular_vacaciones" style="color: #fbbf24;">
                                            <i class="fas fa-money-bill-wave me-1"></i> No acumular (Pagar en nómina)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FOOTER MODAL COMPACTO -->
                <div class="modal-footer modal-footer-win py-2">
                    <div class="row w-100 align-items-center m-0 g-1">
                        <div class="col-md-8 p-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="form-check d-flex align-items-center gap-1">
                                    <input type="checkbox" class="form-check-input mt-0" name="activo" id="activo" checked onchange="actualizarInfoDesdeFormulario()">
                                    <label class="form-check-label small" style="color: #4ade80;"><i class="fas fa-check-circle me-1"></i> Activo</label>
                                </div>
                                
                                <div class="input-group input-group-sm" style="max-width: 150px;">
                                    <span class="input-group-text bg-dark border-secondary text-light" style="font-size: 0.65rem;"><i class="fas fa-calendar-times"></i></span>
                                    <input type="date" class="form-control border-secondary" name="fecha_baja" id="fecha_baja" onchange="actualizarInfoDesdeFormulario()" style="font-size: 0.7rem;">
                                </div>
                                
                                <select class="form-select form-select-sm border-secondary" name="motivo_baja" id="motivo_baja" style="min-width: 160px; max-width: 400px; font-size: 0.7rem; width: auto;" disabled>
                                    <option value="">--MOTIVO DE LA RESCISIÓN LABORAL--</option>
                                    <?php 
                                    $motivos_por_categoria = [];
                                    foreach ($motivos_baja as $motivo) {
                                        $categoria = $motivo['categoria'] ?? 'Otros';
                                        if (!isset($motivos_por_categoria[$categoria])) {
                                            $motivos_por_categoria[$categoria] = [];
                                        }
                                        $motivos_por_categoria[$categoria][] = $motivo;
                                    }
                                    foreach ($motivos_por_categoria as $categoria => $motivos_cat): 
                                    ?>
                                        <optgroup label="<?php echo htmlspecialchars($categoria); ?>">
                                            <?php foreach ($motivos_cat as $motivo): ?>
                                                <option value="<?php echo $motivo['codigo']; ?>">
                                                    <?php 
                                                    echo '[' . str_pad($motivo['codigo'], 2, '0', STR_PAD_LEFT) . '] '; 
                                                    echo htmlspecialchars($motivo['nombre']); 
                                                    if (!empty($motivo['base_legal'])): 
                                                        echo ' (' . htmlspecialchars($motivo['base_legal']) . ')';
                                                    endif; 
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 p-0 text-end">
                            <button type="button" class="btn-win btn-sm py-1" data-bs-dismiss="modal" onclick="cancelarNavegacion()" style="font-size: 0.7rem;">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </button>
                            <button type="submit" class="btn-win btn-win-primary btn-sm py-1 ms-1" style="font-size: 0.7rem;">
                                <i class="fas fa-save me-1"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para recortar imagen -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: var(--win-bg-secondary); color: var(--win-text-primary);">
            <div class="modal-header" style="border-bottom-color: var(--win-border-color);">
                <h5 class="modal-title">
                    <i class="fas fa-crop-alt me-2" style="color: var(--win-accent);"></i>
                    Recortar imagen a 6x6cm
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropper()"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="img-container" style="max-height: 500px; overflow: hidden; background: #333; border-radius: 8px; padding: 10px;">
                            <img id="imageToCrop" src="" style="max-width: 100%; display: block;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <h6 class="mb-2">Vista previa 6x6cm</h6>
                            <div class="preview-container" style="width: 150px; height: 150px; margin: 0 auto; overflow: hidden; border: 3px solid var(--win-accent); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: #2d2d2d;">
                                <canvas id="previewCanvas" width="150" height="150" style="width: 100%; height: 100%; display: block;"></canvas>
                            </div>
                        </div>
                        
                        <div class="text-center mb-3">
                            <small class="text-muted d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                La imagen final será de <strong>500x500 píxeles (6x6cm)</strong>
                            </small>
                        </div>
                        
                        <div class="crop-controls p-3" style="background: var(--win-bg-tertiary); border-radius: 8px;">
                            <h6 class="mb-2">Controles</h6>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(-90)">
                                    <i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(90)">
                                    <i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetearCrop()">
                                    <i class="fas fa-sync-alt me-2"></i>Resetear recorte
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="zoomImagen(0.1)">
                                    <i class="fas fa-search-plus me-2"></i>Acercar
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="zoomImagen(-0.1)">
                                    <i class="fas fa-search-minus me-2"></i>Alejar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top-color: var(--win-border-color);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropper()">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="aplicarRecorte()">
                    <i class="fas fa-check me-2"></i>Aplicar recorte
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/cropper.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script src="../js/datatables/1.13.6/dataTables.buttons.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.html5.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.print.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.colVis.min.js"></script>
<script src="../js/jszip.min.js"></script>
<script src="../js/pdfmake.min.js"></script>
<script src="../js/vfs_fonts.js"></script>

<script>

// ==================== FUNCIÓN PARA CARGAR MESES CON NÓMINAS AUTOMÁTICAS ====================
function cargarMesesConNominas(trabajadorId) {
    const selectMes = document.getElementById('mes_dias_trabajados');
    const detalleDiv = document.getElementById('detalle_dias_trabajados');
    
    if (!selectMes) return;
    
    // Limpiar opciones (mantener solo la primera)
    while (selectMes.options.length > 1) {
        selectMes.remove(1);
    }
    
    // Ocultar detalle
    if (detalleDiv) detalleDiv.style.display = 'none';
    
    if (!trabajadorId) {
        // Mantener el mensaje por defecto
        return;
    }
    
    // Mostrar loading en el select
    const loadingOpt = document.createElement('option');
    loadingOpt.value = '';
    loadingOpt.textContent = '⏳ Cargando...';
    selectMes.appendChild(loadingOpt);
    
    fetch('../ajax/obtener_meses_nominas.php?trabajador_id=' + trabajadorId + '&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            // Limpiar select (mantener solo el primer option)
            while (selectMes.options.length > 1) {
                selectMes.remove(1);
            }
            
            if (data.success && data.meses.length > 0) {
                // Agregar opción por defecto
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '-- Seleccionar mes --';
                selectMes.appendChild(defaultOpt);
                
                // Agregar los meses con formato: "Mes Año - X días (X horas)"
                data.meses.forEach(mes => {
                    const opt = document.createElement('option');
                    opt.value = mes.periodo_id;
                    // Formato: "Mayo 2026 - 22.5 días (180h)"
                    const dias = parseFloat(mes.dias_trabajados).toFixed(1);
                    const horas = parseFloat(mes.horas_laboradas).toFixed(0);
                    opt.textContent = `${mes.periodo_texto} - ${dias} días (${horas}h)`;
                    opt.dataset.dias = dias;
                    opt.dataset.horas = horas;
                    opt.dataset.periodo = mes.periodo_texto;
                    opt.dataset.nomina_id = mes.nomina_id;
                    selectMes.appendChild(opt);
                });
            } else {
                const noDataOpt = document.createElement('option');
                noDataOpt.value = '';
                noDataOpt.textContent = '-- Sin nóminas automáticas --';
                selectMes.appendChild(noDataOpt);
            }
        })
        .catch(error => {
            console.error('Error al cargar meses:', error);
            const errorOpt = document.createElement('option');
            errorOpt.value = '';
            errorOpt.textContent = '⚠️ Error al cargar';
            selectMes.appendChild(errorOpt);
        });
}

function cargarMesesConNominas(trabajadorId) {
    const selectMes = document.getElementById('mes_dias_trabajados');
    const detalleDiv = document.getElementById('detalle_dias_trabajados');
    
    if (!selectMes) return;
    
    while (selectMes.options.length > 1) {
        selectMes.remove(1);
    }
    
    if (detalleDiv) detalleDiv.style.display = 'none';
    
    if (!trabajadorId) {
        return;
    }
    
    // Traducción de meses inglés -> español
    const meses = {
        'January': 'Enero', 'February': 'Febrero', 'March': 'Marzo',
        'April': 'Abril', 'May': 'Mayo', 'June': 'Junio',
        'July': 'Julio', 'August': 'Agosto', 'September': 'Septiembre',
        'October': 'Octubre', 'November': 'Noviembre', 'December': 'Diciembre'
    };
    
    const loadingOpt = document.createElement('option');
    loadingOpt.value = '';
    loadingOpt.textContent = '⏳ Cargando...';
    selectMes.appendChild(loadingOpt);
    
    fetch('../ajax/obtener_meses_nominas.php?trabajador_id=' + trabajadorId + '&t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            while (selectMes.options.length > 1) {
                selectMes.remove(1);
            }
            
            if (data.success && data.meses.length > 0) {
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '-- Seleccionar mes --';
                selectMes.appendChild(defaultOpt);
                
                data.meses.forEach(mes => {
                    const opt = document.createElement('option');
                    opt.value = mes.periodo_id;
                    
                    // Traducir mes si viene en inglés
                    let texto = mes.periodo_texto;
                    for (const [en, es] of Object.entries(meses)) {
                        if (texto.includes(en)) {
                            texto = texto.replace(en, es);
                            break;
                        }
                    }
                    
                    const dias = parseFloat(mes.dias_trabajados).toFixed(1);
                    const horas = parseFloat(mes.horas_laboradas).toFixed(0);
                    opt.textContent = `${texto} - ${dias} días (${horas}h)`;
                    opt.dataset.dias = dias;
                    opt.dataset.horas = horas;
                    opt.dataset.periodo = texto;
                    opt.dataset.nomina_id = mes.nomina_id;
                    selectMes.appendChild(opt);
                });
            } else {
                const noDataOpt = document.createElement('option');
                noDataOpt.value = '';
                noDataOpt.textContent = '-- Sin nóminas automáticas --';
                selectMes.appendChild(noDataOpt);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            while (selectMes.options.length > 1) {
                selectMes.remove(1);
            }
            const errorOpt = document.createElement('option');
            errorOpt.value = '';
            errorOpt.textContent = '⚠️ Error al cargar datos';
            selectMes.appendChild(errorOpt);
        });
}


// ==================== DASHBOARD UI LOGIC ====================
function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const clockSpan = document.getElementById('liveClock');
    if (clockSpan) clockSpan.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000); updateClock();

// Sidebar
const sidebar = document.getElementById('winSidebar');
const mainContainer = document.getElementById('mainContainer');
if (sidebar && mainContainer) {
    if (localStorage.getItem('winSidebarCollapsed') === 'true') { 
        sidebar.classList.add('collapsed'); 
        mainContainer.classList.add('expanded'); 
    }
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed'); 
            mainContainer.classList.toggle('expanded');
            localStorage.setItem('winSidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
}

// Config general SweetAlert
const swalDark = { background: '#1a1a2e', color: '#ffffff', confirmButtonColor: '#3b82f6', cancelButtonColor: '#6c757d' };

// Notificaciones Flash PHP a JS
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    <?php if ($flash_message): ?>
    Swal.fire({
        title: '<?php echo $flash_type === "success" ? "<i class=\"fas fa-check-circle text-success me-2\"></i> Éxito" : "<i class=\"fas fa-exclamation-triangle text-danger me-2\"></i> Error"; ?>',
        text: '<?php echo addslashes($flash_message); ?>',
        icon: '<?php echo $flash_type === "success" ? "success" : "error"; ?>',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
        background: '#1a1a2e',
        color: '#ffffff'
    });
    <?php endif; ?>
});

// Backup
// Función para Backup Manual de la Base de Datos
function realizarBackupManual() {
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: `
            <div style="text-align: left;">
                <p><i class="fas fa-info-circle me-2" style="color: #60a5fa;"></i> Se creará una copia de seguridad completa de la base de datos.</p>
                <p class="mt-2"><small class="text-muted">La copia incluirá:</small></p>
                <ul style="text-align: left;">
                    <li><i class="fas fa-users me-1"></i> Datos de empleados</li>
                    <li><i class="fas fa-calculator me-1"></i> Nóminas generadas</li>
                    <li><i class="fas fa-cog me-1"></i> Configuración del sistema</li>
                    <li><i class="fas fa-umbrella-beach me-1"></i> Historial de vacaciones</li>
                </ul>
                <div class="alert alert-info mt-2" style="background: rgba(59,130,246,0.1); border: 1px solid #3b82f6; border-radius: 8px; padding: 10px;">
                    <i class="fas fa-clock me-1"></i> El archivo se guardará con el formato: <strong>backup_YYYY-MM-DD_HH-MM-SS.sql</strong>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
                text: 'Por favor espere, esto puede tomar unos segundos',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: '#1a1a2e',
                color: '#ffffff'
            });
            
            fetch('../ajax/backup_db.php', {  // Nota: '../' porque empleados.php está en modules/
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Backup Completado',
                        html: `
                            <p>La copia de seguridad se ha generado correctamente.</p>
                            <p><strong>Archivo:</strong> ${data.filename}</p>
                            <p><strong>Tamaño:</strong> ${data.size}</p>
                            <div class="mt-3">
                                <a href="../${data.download_url}" class="btn btn-success" download>
                                    <i class="fas fa-download me-2"></i> Descargar Backup
                                </a>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i> Error',
                        text: data.message || 'No se pudo generar el backup',
                        icon: 'error',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i> Error',
                    text: 'Error de conexión al generar el backup: ' + error,
                    icon: 'error',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
            });
        }
    });
}

// Evento para el botón de Backup Manual
document.getElementById('btnBackupManual')?.addEventListener('click', (e) => {
    e.preventDefault();
    realizarBackupManual();
});
// ==================== LÓGICA DE EMPLEADOS ====================
let salarioMensualActual = 0;
let diasMensuales = <?php echo $dias_mensuales; ?>;
let cropper = null; 
let previewCanvas = null; 
let previewCtx = null;
let empleadosData = <?php echo json_encode($empleados); ?>;
let currentIndex = -1; 
let formDirty = false; 
let pendingNavigation = null;

function numeroRomano(num) { 
    const r = {1:'I',2:'II',3:'III',4:'IV',5:'V',6:'VI',7:'VII',8:'VIII',9:'IX',10:'X',11:'XI',12:'XII',13:'XIII',14:'XIV',15:'XV',16:'XVI'}; 
    return r[num] || num; 
}
function formatMoney(value) { 
    return new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP', minimumFractionDigits: 2 }).format(value); 
}
function markFormDirty() { formDirty = true; }
function resetFormDirty() { formDirty = false; }

// ACTUALIZAR NOMBRE EN EL MODAL
function actualizarNombreModal() {
    const spanNombre = document.getElementById('modalEmpleadoNombre');
    if (!spanNombre) return;
    
    const nombres = document.getElementById('nombres')?.value || '';
    const primerApellido = document.getElementById('primer_apellido')?.value || '';
    const segundoApellido = document.getElementById('segundo_apellido')?.value || '';
    
    const nombreCompleto = [nombres, primerApellido, segundoApellido].filter(n => n.trim()).join(' ');
    
    if (nombreCompleto.trim()) {
        spanNombre.textContent = nombreCompleto;
    } else {
        spanNombre.textContent = 'Nuevo Empleado';
    }
	actualizarSolapin();
}

// CI y Expediente AJAX
function validarCI(ci) {
    ci = ci.replace(/[\s-]/g, '');
    if (!/^\d{11}$/.test(ci)) {
        return { valido: false, mensaje: '⚠️ Debe tener exactamente 11 dígitos numéricos' };
    }
    
    const año = ci.substr(0, 2);
    const mes = ci.substr(2, 2);
    const dia = ci.substr(4, 2);
    
    // Validar mes
    if (mes < '01' || mes > '12') {
        return { valido: false, mensaje: '❌ Mes inválido (debe ser 01-12)' };
    }
    
    // Validar día según el mes
    const diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const mesNum = parseInt(mes);
    let maxDias = diasPorMes[mesNum - 1];
    
    // Validar año bisiesto para febrero
    if (mesNum === 2) {
        const añoCompleto = parseInt(año) < 50 ? 2000 + parseInt(año) : 1900 + parseInt(año);
        const esBisiesto = (añoCompleto % 4 === 0 && añoCompleto % 100 !== 0) || (añoCompleto % 400 === 0);
        if (esBisiesto) maxDias = 29;
    }
    
    const diaNum = parseInt(dia);
    if (diaNum < 1 || diaNum > maxDias) {
        return { valido: false, mensaje: `❌ Día inválido para el mes ${mes} (máx ${maxDias} días)` };
    }
    
    const digitoGenero = parseInt(ci.charAt(9));
    const genero = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
    const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    const añoCompleto = parseInt(año) < 50 ? `20${año}` : `19${año}`;
    
    return {
        valido: true,
        mensaje: `<i class="fas fa-check-circle me-1"></i> <span style="color: #28a745;">Válido</span> ${iconoGenero} ${genero} | ${dia}/${mes}/${añoCompleto}`,
        genero: genero
    };
}


function validarCampoCIEnTiempoReal() {
    const ciInput = document.getElementById('ci');
    if (!ciInput) return;
    
    const ciValor = ciInput.value.trim();
    const ciValidation = validarCI(ciValor);
    
    let ciFeedback = document.getElementById('ci-feedback');
    if (!ciFeedback) {
        ciFeedback = document.createElement('small');
        ciFeedback.id = 'ci-feedback';
        ciFeedback.className = 'd-block mt-1';
        ciInput.parentNode.appendChild(ciFeedback);
    }
    
    if (ciValor.length > 0) {
        if (ciValidation.valido) {
            ciFeedback.innerHTML = ciValidation.mensaje;
            ciFeedback.style.color = '#28a745';
            ciInput.style.borderColor = '#28a745';
            ciInput.style.backgroundColor = 'rgba(40, 167, 69, 0.1)';
        } else {
            ciFeedback.innerHTML = ` ${ciValidation.mensaje}`;
            ciFeedback.style.color = '#dc3545';
            ciInput.style.borderColor = '#dc3545';
            ciInput.style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
        }
    } else {
        ciFeedback.innerHTML = 'Ingrese los 11 dígitos del CI';
        ciFeedback.style.color = '#6c757d';
        ciInput.style.borderColor = '';
        ciInput.style.backgroundColor = '';
    }
}

async function actualizarExpedienteYInfo() {
    let ci = document.getElementById('ci').value.trim().replace(/\D/g, '');
    let exp = document.getElementById('codigo');
    if(ci.length >= 6) {
        let base = ci.slice(-6), gen = base, cont = 1, disp = false;
        try {
            while(!disp) {
                const r = await fetch(window.location.href, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body: 'action=verificar_expediente&expediente='+gen });
                const d = await r.json();
                if(!d.existe) { disp = true; exp.value = gen; } else { gen = base + cont; cont++; if(cont>99) break; }
            }
        } catch(e) { exp.value = base; }
    } else exp.value = '';
    validarCampoCIEnTiempoReal(); 
    actualizarInfoDesdeFormulario(); 
    markFormDirty();
}

function actualizarInfoSalarioBaseFormateado() {
    const selectEscala = document.getElementById('escala_id');
    const selectedOption = selectEscala.options[selectEscala.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const salarioMensual = parseFloat(selectedOption.getAttribute('data-salario') || 0);
        const salarioHora = parseFloat(selectedOption.getAttribute('data-salario-hora') || 0);
        const escalaNumero = selectedOption.getAttribute('data-escala-numero') || 
                             (selectedOption.textContent.match(/\d+/) || [0])[0];
        
        const numeroRomanoTexto = numeroRomano(parseInt(escalaNumero));
        const textFormateado = `Grupo: ${numeroRomanoTexto} ($${salarioMensual.toFixed(2)} / $${salarioHora.toFixed(2)}h)`;
        
        const infoEscalaGrupo = document.getElementById('infoEscalaGrupo');
        const infoSalarioBase = document.getElementById('infoSalarioBase');
        if (infoEscalaGrupo) infoEscalaGrupo.innerHTML = textFormateado;
        if (infoSalarioBase) infoSalarioBase.innerHTML = formatMoney(salarioMensual);
    } else {
        const infoEscalaGrupo = document.getElementById('infoEscalaGrupo');
        const infoSalarioBase = document.getElementById('infoSalarioBase');
        if (infoEscalaGrupo) infoEscalaGrupo.innerHTML = '--';
        if (infoSalarioBase) infoSalarioBase.innerHTML = '--';
    }
}

function actualizarInfoDesdeFormulario() {
    const activo = document.getElementById('activo');
    const fechaBaja = document.getElementById('fecha_baja');
    const infoEstado = document.getElementById('infoEstado');
    
    if (activo && fechaBaja && infoEstado) {
        infoEstado.innerHTML = activo.checked && !fechaBaja.value ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>';
    }
    
    const ci = document.getElementById('ci').value.replace(/\D/g, '');
    if(ci.length >= 6) {
        const a=parseInt(ci.substring(0,2)), m=parseInt(ci.substring(2,4)), d=parseInt(ci.substring(4,6));
        const fn = new Date(a+(a>new Date().getFullYear()%100?1900:2000), m-1, d), hoy = new Date();
        let ed = hoy.getFullYear() - fn.getFullYear();
        if(hoy.getMonth()<fn.getMonth() || (hoy.getMonth()===fn.getMonth() && hoy.getDate()<fn.getDate())) ed--;
        const infoEdad = document.getElementById('infoEdad');
        if (infoEdad) infoEdad.innerHTML = `${ed} años`;
        
        const datos = obtenerFechaNacimientoYSexoDesdeCI(ci);
        const infoSexo = document.getElementById('infoSexo');
        const infoFechaNac = document.getElementById('infoFechaNac');
        if (infoSexo) infoSexo.innerHTML = datos.sexo;
        if (infoFechaNac) infoFechaNac.innerHTML = datos.fecha;
    } else {
        const infoEdad = document.getElementById('infoEdad');
        const infoSexo = document.getElementById('infoSexo');
        const infoFechaNac = document.getElementById('infoFechaNac');
        if (infoEdad) infoEdad.innerHTML = '-- años';
        if (infoSexo) infoSexo.innerHTML = '--';
        if (infoFechaNac) infoFechaNac.innerHTML = '--';
    }
    
    const alta = document.getElementById('fecha_alta').value;
    if(alta) {
        let a = new Date().getFullYear() - new Date(alta).getFullYear(), m = new Date().getMonth() - new Date(alta).getMonth();
        if(m < 0) { a--; m+=12; } 
        const infoAntiguedad = document.getElementById('infoAntiguedad');
        if (infoAntiguedad) infoAntiguedad.innerHTML = `${a}a, ${m}m`;
    }
    
    actualizarInfoSalarioBaseFormateado();
	actualizarSolapin();
	
    const vDia = salarioMensualActual / diasMensuales;
    const infoValorDia = document.getElementById('infoValorDia');
    if (infoValorDia) infoValorDia.innerHTML = formatMoney(vDia);
    
    const vDias = parseFloat(document.getElementById('vacaciones_acumuladas').value)||0;
    const infoVacDias = document.getElementById('infoVacDias');
    const infoVacValor = document.getElementById('infoVacValor');
    if (infoVacDias) infoVacDias.innerHTML = `${vDias.toFixed(2)} d`;
    if (infoVacValor) infoVacValor.innerHTML = formatMoney(vDias * vDia);
}

function actualizarValoresVacaciones() {
    let dias = parseFloat(document.getElementById('vacaciones_acumuladas')?.value) || 0;
    let valorDia = salarioMensualActual / diasMensuales;
    let valorTotal = dias * valorDia;
    
    let importeInput = document.getElementById('valor_vacaciones_calculado');
    if (importeInput) {
        importeInput.value = formatMoney(valorTotal);
    }
    
    actualizarInfoDesdeFormulario();
    markFormDirty();
    
    const vacacionesInput = document.getElementById('vacaciones_acumuladas');
    const alertaExistente = document.querySelector('.alerta-vacaciones-flotante');
    if(dias > 20){
        if (vacacionesInput) vacacionesInput.classList.add('campo-vacaciones-alerta');
        if(!alertaExistente && vacacionesInput && vacacionesInput.closest('.row')) {
            const alertaDiv = document.createElement('div');
            alertaDiv.className = 'alerta-vacaciones-flotante';
            alertaDiv.innerHTML = `<div class="alerta-mensaje"><i class="fas fa-exclamation-triangle me-2"></i> ¡Atención! ${dias.toFixed(2)} días acumulados (máx 24). Comunique al Trabajador</div><button type="button" class="btn-cerrar-alerta" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
            vacacionesInput.closest('.row').insertAdjacentElement('beforebegin', alertaDiv);
        }
    } else {
        if (vacacionesInput) vacacionesInput.classList.remove('campo-vacaciones-alerta');
        if(alertaExistente) alertaExistente.remove();
    }
}

function actualizarSalarioDesdeEscala() {
    let sel = document.getElementById('escala_id');
    let selectedOption = sel.options[sel.selectedIndex];
    let salarioMensual = selectedOption?.getAttribute('data-salario');
    
    if (salarioMensual) {
        salarioMensualActual = parseFloat(salarioMensual);
    }
    
    actualizarInfoSalarioBaseFormateado();
    actualizarValoresVacaciones();
}

function validarCuentaBancaria(input) {
    let v = input.value.replace(/\D/g, ''); 
    input.value = v;
    let span = document.getElementById('digitosCuenta'), help = document.getElementById('cuentaHelp');
    if (span) span.textContent = v.length;
    if(v.length>0 && v.length!==14 && v.length!==16) { 
        if (span) span.className='invalid'; 
        if (help) help.innerHTML='<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Debe ser 14 o 16 dígitos</span>'; 
        input.style.borderColor='#ef4444'; 
    }
    else if(v.length===14 || v.length===16) { 
        if (span) span.className='valid'; 
        if (help) help.innerHTML='<span class="text-success"><i class="fas fa-check-circle me-1"></i> Cuenta válida</span>'; 
        input.style.borderColor='#10b981'; 
    }
    else { 
        if (span) span.className=''; 
        if (help) help.innerHTML='<i class="fas fa-info-circle me-1"></i> Solo números permitidos'; 
        input.style.borderColor=''; 
    }
    markFormDirty();
}

// Configs y Formulario Baja
const fechaBajaInput = document.getElementById('fecha_baja');
if (fechaBajaInput) {
    fechaBajaInput.addEventListener('change', function(){
        const activoCheck = document.getElementById('activo');
        const motivoBajaSelect = document.getElementById('motivo_baja');
        if (activoCheck) activoCheck.checked = !this.value;
        if (motivoBajaSelect) {
            motivoBajaSelect.disabled = !this.value;
            motivoBajaSelect.required = !!this.value;
        }
        actualizarInfoDesdeFormulario(); 
        markFormDirty();
    });
}

const activoCheckbox = document.getElementById('activo');
if (activoCheckbox) {
    activoCheckbox.addEventListener('change', function(){
        const fechaBajaInputEl = document.getElementById('fecha_baja');
        const motivoBajaSelect = document.getElementById('motivo_baja');
        if(this.checked){ 
            if (fechaBajaInputEl) fechaBajaInputEl.value=''; 
            if (motivoBajaSelect) {
                motivoBajaSelect.disabled=true; 
                motivoBajaSelect.value='';
            }
        }
        actualizarInfoDesdeFormulario(); 
        markFormDirty();
    });
}

function ocultarControlesNavegacion(hide) {
    const navControls = document.getElementById('navControls');
    if (navControls) {
        if (hide) {
            navControls.style.display = 'none';
        } else {
            navControls.style.display = 'flex';
            // Inicializar el selector si es necesario
            if (empleadosListaSelect.length === 0 && empleadosData.length > 0) {
                inicializarSelectorEmpleados();
            }
            actualizarControlesNavegacion();
        }
    }
}


function actualizarControlesNavegacion() {
    const registroActualSpan = document.getElementById('registroActual');
    const totalRegistrosSpan = document.getElementById('totalRegistros');
    const btnPrimero = document.getElementById('btnPrimero');
    const btnAnterior = document.getElementById('btnAnterior');
    const btnSiguiente = document.getElementById('btnSiguiente');
    const btnUltimo = document.getElementById('btnUltimo');
    
    if (registroActualSpan && totalRegistrosSpan) {
        registroActualSpan.textContent = currentIndex + 1;
        totalRegistrosSpan.textContent = empleadosData.length;
    }
    
    if (btnPrimero && btnAnterior) {
        const disabled = currentIndex <= 0;
        btnPrimero.disabled = disabled;
        btnAnterior.disabled = disabled;
    }
    
    if (btnSiguiente && btnUltimo) {
        const disabled = currentIndex >= empleadosData.length - 1;
        btnSiguiente.disabled = disabled;
        btnUltimo.disabled = disabled;
    }
    
    // Actualizar el selector de empleados
    actualizarSelectorEmpleado();
}

function ejecutarNavegacion(act) {
    if (!empleadosData.length) return;
    
    let nx = currentIndex;
    if (act === 'primero') nx = 0;
    else if (act === 'anterior') nx--;
    else if (act === 'siguiente') nx++;
    else if (act === 'ultimo') nx = empleadosData.length - 1;
    
    if (nx >= 0 && nx < empleadosData.length) {
        currentIndex = nx;
        cargarEmpleadoEnFormulario(empleadosData[nx]);
        actualizarControlesNavegacion();
    }
}

function navegarRegistro(act) {
    if (empleadosData.length === 0) return;
    
    if (formDirty) {
        pendingNavigation = act;  // Guardar como string (ej: 'siguiente', 'anterior')
        Swal.fire(Object.assign({
            title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Cambios sin guardar',
            text: '¿Desea guardar los cambios antes de navegar?',
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-save me-2"></i> Guardar',
            denyButtonText: '<i class="fas fa-trash-alt me-2"></i> Descartar',
            cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar'
        }, swalDark))
        .then(r => {
            if (r.isConfirmed) {
                document.getElementById('empleadoForm').dispatchEvent(new Event('submit'));
                // pendingNavigation ya está guardado
            } else if (r.isDenied) {
                resetFormDirty();
                ejecutarNavegacion(act);
                pendingNavigation = null;
            }
        });
    } else {
        ejecutarNavegacion(act);
    }
}


function cancelarNavegacion() { 
    pendingNavigation = null; 
    resetFormDirty(); 
}

function clearForm() {
    const form = document.getElementById('empleadoForm');
    if (form) form.reset();
    
    const formAction = document.getElementById('formAction');
    const empleadoId = document.getElementById('empleadoId');
    const fechaAlta = document.getElementById('fecha_alta');
    const motivoBaja = document.getElementById('motivo_baja');
    
    if (formAction) formAction.value = 'crear';
    if (empleadoId) empleadoId.value = '';
    if (fechaAlta) fechaAlta.value = new Date().toISOString().split('T')[0];
    if (motivoBaja) {
        motivoBaja.disabled = true;
        motivoBaja.value = '';
    }
    
    // ============================================
    // OCULTAR EL ID DEL TRABAJADOR (debajo de Expediente)
    // ============================================
    const workerIdDisplay = document.getElementById('workerIdDisplay');
    if (workerIdDisplay) {
        workerIdDisplay.classList.add('d-none');
    }
    const workerIdValue = document.getElementById('workerIdValue');
    if (workerIdValue) {
        workerIdValue.textContent = '';
    }
    
    // ============================================
    // OCULTAR ID EN EL SOLAPÍN PARA NUEVO EMPLEADO
    // ============================================
    const solapinIDSpan = document.getElementById('solapinIDSpan');
    if (solapinIDSpan) {
        solapinIDSpan.textContent = 'ID: --';
    }
    const solapinID = document.getElementById('solapinID');
    if (solapinID) {
        solapinID.style.display = 'block'; // Siempre visible, pero con "--"
    }
    
    // ============================================
    // MANEJO DE FOTO - MOSTRAR LOGO POR DEFECTO
    // ============================================
    const imagePreview = document.getElementById('imagePreview');
    const fotoPlaceholder = document.getElementById('fotoPlaceholder');
    const btnEliminarFoto = document.getElementById('btnEliminarFoto');
    const logoTnBase64 = '<?php echo $logo_base64; ?>';
    
    // Ocultar botones de acciones del solapín
    const solapinActions = document.getElementById('solapinActionsContainer');
    if (solapinActions) solapinActions.style.display = 'none';
    
    if (imagePreview) {
        imagePreview.src = logoTnBase64;
        imagePreview.style.display = 'block';
        imagePreview.style.objectFit = 'contain';
        imagePreview.style.padding = '20px';
        imagePreview.style.borderRadius = '50%';
        imagePreview.classList.add('logo-fallback');
    }
    
    if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
    if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
    
    const eliminarFoto = document.getElementById('eliminarFoto');
    const imagenRecortada = document.getElementById('imagen_recortada');
    if (eliminarFoto) eliminarFoto.value = '0';
    if (imagenRecortada) imagenRecortada.value = '';
    
    // ============================================
    // ACTUALIZAR SOLAPÍN Y OTROS ELEMENTOS
    // ============================================
    setTimeout(() => {
        actualizarSolapinFoto();
        actualizarSolapin();
    }, 100);
    
    // Eliminar feedback del CI si existe
    const ciFeedback = document.getElementById('ci-feedback');
    if (ciFeedback) ciFeedback.remove();
    
    // Resetear escala y salario
    const selectEscala = document.getElementById('escala_id');
    if (selectEscala && selectEscala.options.length) {
        salarioMensualActual = parseFloat(selectEscala.options[0]?.getAttribute('data-salario') || 0);
    }
    
    // Resetear valores de vacaciones
    actualizarValoresVacaciones();
    
    // Limpiar expediente
    const codigoInput = document.getElementById('codigo');
    if (codigoInput) codigoInput.value = '';
    
    // Actualizar información del formulario
    actualizarInfoDesdeFormulario();
    
    // Ocultar controles de navegación
    ocultarControlesNavegacion(true);
    
    // Resetear dirty flag
    resetFormDirty();
    
    // Actualizar nombre del modal
    actualizarNombreModal();

    // ============================================
    // REINICIAR COMBO DE MESES - DÍAS - HORAS
    // ============================================
    const selectMes = document.getElementById('mes_dias_trabajados');
    if (selectMes) {
        // Limpiar todas las opciones excepto la primera
        while (selectMes.options.length > 1) {
            selectMes.remove(1);
        }
        // Asegurar que el primer option sea el mensaje por defecto
        if (selectMes.options.length === 0) {
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '-- Sin nóminas --';
            selectMes.appendChild(defaultOpt);
        } else {
            // Reemplazar el primer option si tiene valor
            const firstOpt = selectMes.options[0];
            if (firstOpt.value !== '') {
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '-- Sin nóminas --';
                selectMes.insertBefore(defaultOpt, firstOpt);
                selectMes.remove(1);
            }
        }
        // Resetear selección
        selectMes.selectedIndex = 0;
    }
    
    // Ocultar detalle de días trabajados
    const detalleDiv = document.getElementById('detalle_dias_trabajados');
    if (detalleDiv) {
        detalleDiv.style.display = 'none';
    }
    
    // Resetear textos de detalle
    const diasTexto = document.getElementById('dias_trabajados_texto');
    const horasTexto = document.getElementById('horas_trabajadas_texto');
    if (diasTexto) diasTexto.textContent = '0 días trabajados';
    if (horasTexto) horasTexto.textContent = '(0 horas)';
    
    // ============================================
    // ENFOCAR EL CAMPO CI
    // ============================================
    setTimeout(() => {
        const ciInput = document.getElementById('ci');
        if (ciInput) {
            ciInput.focus();
            ciInput.select();
        }
    }, 100);
}

function clearFormAndStartNew() {
    if(formDirty) {
        Swal.fire(Object.assign({title:'<i class="fas fa-exclamation-triangle text-warning me-2"></i> Cambios sin guardar', text:'¿Descartar y crear nuevo?', icon:'warning', showCancelButton:true, confirmButtonColor: '#dc3545', confirmButtonText:'<i class="fas fa-trash-alt me-2"></i> Descartar', cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar'}, swalDark))
        .then(r => { 
            if(r.isConfirmed) { 
                clearForm(); 
                resetFormDirty(); 
                currentIndex = -1;
                // Actualizar selector
                actualizarSelectorEmpleado();
                // Enfocar el campo CI
                setTimeout(() => {
                    const ciInput = document.getElementById('ci');
                    if (ciInput) {
                        ciInput.focus();
                        ciInput.select();
                    }
                }, 500);
            } 
        });
    } else { 
        clearForm(); 
        currentIndex = -1;
        // Actualizar selector
        actualizarSelectorEmpleado();
        // Enfocar el campo CI
        setTimeout(() => {
            const ciInput = document.getElementById('ci');
            if (ciInput) {
                ciInput.focus();
                ciInput.select();
            }
        }, 500);
    }
}

function cargarEmpleadoEnFormulario(emp) {
    const formAction = document.getElementById('formAction');
    const empleadoId = document.getElementById('empleadoId');
	
	const solapinActions = document.getElementById('solapinActionsContainer');
    if (solapinActions) solapinActions.style.display = 'flex';
	
    if (formAction) formAction.value = 'editar';
    if (empleadoId) empleadoId.value = emp.id;
    
	const workerIdDisplay = document.getElementById('workerIdDisplay');
    const workerIdValue = document.getElementById('workerIdValue');
    if (emp.id) {
        workerIdDisplay.classList.remove('d-none');
        workerIdValue.textContent = emp.id;
    } else {
        workerIdDisplay.classList.add('d-none');
        workerIdValue.textContent = '';
    }
	const campos = {
        'codigo': emp.codigo,
        'ci': emp.ci,
        'nombres': emp.nombres,
        'primer_apellido': emp.primer_apellido,
        'segundo_apellido': emp.segundo_apellido,
        'direccion': emp.direccion_particular,
        'telefono': emp.telefono_contacto,
        'email': emp.email,
        'cuenta_bancaria': emp.cuentabanc,
        'area_id': emp.area_id,
        'centro_costo_id': emp.centro_costo_id,
        'categoria_id': emp.categoria_ocupacional_id,
        'cargo_id': emp.cargo_id, // <-- CAMBIO AQUÍ (Mapea el select estructurado)
        'escala_id': emp.escala_salarial_id,
        'fecha_alta': emp.fecha_alta,
        'fecha_baja': emp.fecha_baja,
        'motivo_baja': emp.motivo_baja,
        'vacaciones_acumuladas': emp.vacaciones_acumuladas
    };
    
    for (const [id, value] of Object.entries(campos)) {
        const el = document.getElementById(id);
        if (el) el.value = value || '';
    }
    
    const activoCheck = document.getElementById('activo');
    const noAcumularCheck = document.getElementById('no_acumular_vacaciones');
    if (activoCheck) activoCheck.checked = (emp.activo == 1);
    if (noAcumularCheck) noAcumularCheck.checked = (emp.no_acumular_vacaciones == 1);
    
    const motivoBajaSelect = document.getElementById('motivo_baja');
    if (motivoBajaSelect) motivoBajaSelect.disabled = !emp.fecha_baja;
    
    if (emp.cuentabanc) {
        const cuentaInput = document.getElementById('cuenta_bancaria');
        if (cuentaInput) validarCuentaBancaria(cuentaInput);
    }
    
    // ============================================
    // MANEJO DE LA FOTO CON LOGO POR DEFECTO - VERSIÓN CORREGIDA
    // ============================================
    const imagePreview = document.getElementById('imagePreview');
    const fotoPlaceholder = document.getElementById('fotoPlaceholder');
    const btnEliminarFoto = document.getElementById('btnEliminarFoto');
    const logoTnBase64 = '<?php echo $logo_base64; ?>';
    
    // Función para aplicar estilos correctos a la imagen
    function aplicarEstilosImagen(tipo) {
        if (tipo === 'foto') {
            imagePreview.style.objectFit = 'cover';
            imagePreview.style.padding = '0';
            imagePreview.style.borderRadius = '50%';
            imagePreview.classList.remove('logo-fallback');
        } else {
            imagePreview.style.objectFit = 'contain';
            imagePreview.style.padding = '20px';
            imagePreview.style.borderRadius = '50%';
            imagePreview.classList.add('logo-fallback');
        }
    }
	
	setTimeout(() => {
     actualizarSolapinFoto();
	}, 100);
    
    // Función para verificar si una imagen existe
    function verificarFotoExiste(url, callback) {
        const img = new Image();
        img.onload = function() { callback(true); };
        img.onerror = function() { callback(false); };
        img.src = url;
    }
    
    if (emp.foto_ruta && emp.foto_ruta !== 'null' && emp.foto_ruta !== '') {
        const fotoUrl = '../' + emp.foto_ruta;
        
        verificarFotoExiste(fotoUrl, function(existe) {
            if (existe) {
                // TIENE FOTO REAL - mostrar foto y botón eliminar
                imagePreview.src = fotoUrl;
                imagePreview.style.display = 'block';
                aplicarEstilosImagen('foto');
                fotoPlaceholder.style.display = 'none';
                btnEliminarFoto.style.display = 'inline-flex';  // Mostrar botón eliminar
                btnEliminarFoto.disabled = false;
            } else {
                // NO TIENE FOTO (ruta rota) - mostrar logo, ocultar eliminar
                imagePreview.src = logoTnBase64;
                imagePreview.style.display = 'block';
                aplicarEstilosImagen('logo');
                fotoPlaceholder.style.display = 'none';
                btnEliminarFoto.style.display = 'none';  // Ocultar botón eliminar
                emp.foto_ruta = null;
            }
        });
    } else {
        // NO TIENE FOTO REGISTRADA - mostrar logo, ocultar eliminar
        imagePreview.src = logoTnBase64;
        imagePreview.style.display = 'block';
        aplicarEstilosImagen('logo');
        fotoPlaceholder.style.display = 'none';
        btnEliminarFoto.style.display = 'none';  // Ocultar botón eliminar
    }
    
    salarioMensualActual = parseFloat(emp.salario_mensual) || 0;
    
    const selectEscala = document.getElementById('escala_id');
    if (selectEscala && emp.escala_salarial_id) {
        selectEscala.value = emp.escala_salarial_id;
    }
    
    const tipoContrato = document.getElementById('tipo_contrato');
    if (tipoContrato) tipoContrato.value = emp.tipo_contrato || 'Indeterminado';
    
    actualizarInfoSalarioBaseFormateado();
    actualizarValoresVacaciones();
    actualizarInfoDesdeFormulario();
    validarCampoCIEnTiempoReal();
    ocultarControlesNavegacion(false);
    actualizarNombreModal();
    resetFormDirty();
	cargarMesesConNominas(emp.id);
}

function editEmpleado(emp) {
    if (!emp || !emp.id) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Error',
            text: 'No se pudo cargar la información del empleado.',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    currentIndex = empleadosData.findIndex(e => e.id == emp.id);
    if (currentIndex === -1) currentIndex = 0;
    
    try {
        cargarEmpleadoEnFormulario(emp);
        
        if (empleadosData.length > 1) {
            ocultarControlesNavegacion(false);
            actualizarControlesNavegacion();
        } else {
            ocultarControlesNavegacion(true);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('empleadoModal'));
        modal.show();
        
        // Enfocar el campo CI después de que el modal se haya mostrado completamente
        modal._element.addEventListener('shown.bs.modal', function onShown() {
            const ciInput = document.getElementById('ci');
            if (ciInput) {
                ciInput.focus();
                ciInput.select();
            }
            modal._element.removeEventListener('shown.bs.modal', onShown);
        });
        
    } catch (error) {
        console.error('Error al cargar empleado:', error);
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Error',
            text: 'Ocurrió un error al cargar los datos del empleado.',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }
}

function cargarImagenParaRecorte(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'La imagen no debe superar los 5MB', background: '#1F1F1F', color: '#FFF' });
            input.value = '';
            return;
        }
        if (!file.type.match('image.*')) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Solo se permiten archivos de imagen', background: '#1F1F1F', color: '#FFF' });
            input.value = '';
            return;
        }
        
        Swal.fire({ title: 'Cargando imagen...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1F1F1F', color: '#FFF' });
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageToCrop = document.getElementById('imageToCrop');
            imageToCrop.src = e.target.result;
            imageToCrop.onload = function() {
                Swal.close();
                previewCanvas = document.getElementById('previewCanvas');
                previewCtx = previewCanvas.getContext('2d');
                previewCtx.fillStyle = '#2d2d2d';
                previewCtx.fillRect(0, 0, 150, 150);
                if (cropper) cropper.destroy();
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 1,
                    cropBoxResizable: true, cropBoxMovable: true, guides: true, center: true,
                    highlight: true, background: true, responsive: true, restore: true,
                    zoomable: true, rotatable: true, scalable: true, wheelZoomRatio: 0.1,
                    minContainerWidth: 500, minContainerHeight: 400,
                    crop: function(event) { actualizarPreview(event); },
                    ready: function() {
                        const containerData = cropper.getContainerData();
                        const cropBoxSize = Math.min(containerData.width, containerData.height) * 0.8;
                        cropper.setCropBoxData({ width: cropBoxSize, height: cropBoxSize, left: (containerData.width - cropBoxSize) / 2, top: (containerData.height - cropBoxSize) / 2 });
                        setTimeout(() => actualizarPreview({ detail: cropper.getData() }), 100);
                    }
                });
                new bootstrap.Modal(document.getElementById('cropModal')).show();
            };
        };
        reader.readAsDataURL(file);
    }
}

function actualizarPreview(event) {
    if (!cropper || !previewCtx) return;
    try {
        const canvas = cropper.getCroppedCanvas({ width: 150, height: 150, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
        if (canvas) previewCtx.drawImage(canvas, 0, 0, 150, 150);
    } catch (error) { console.error('Error actualizando preview:', error); }
}

function resetearCrop() {
    if (cropper) {
        cropper.reset();
        setTimeout(() => actualizarPreview({ detail: cropper.getData() }), 50);
    }
}

function rotarImagen(deg) { if(cropper) cropper.rotate(deg); }

function limpiarCropper() { if(cropper){cropper.destroy();cropper=null;} document.getElementById('imageUpload').value=''; }

function zoomImagen(factor) {
    if (cropper) {
        cropper.zoom(factor);
        setTimeout(() => actualizarPreview({ detail: cropper.getData() }), 50);
    }
}

function aplicarRecorte() {
    if(cropper) {
        const b64 = cropper.getCroppedCanvas({width:500,height:500}).toDataURL('image/jpeg', 0.9);
        document.getElementById('imagen_recortada').value = b64;
        
        const imagePreview = document.getElementById('imagePreview');
        const fotoPlaceholder = document.getElementById('fotoPlaceholder');
        const btnEliminarFoto = document.getElementById('btnEliminarFoto');
        
        if (imagePreview) {
            imagePreview.src = b64;
            imagePreview.style.display = 'block';
            imagePreview.style.objectFit = 'cover';
            imagePreview.style.padding = '0';
            imagePreview.style.borderRadius = '50%';
            imagePreview.classList.remove('logo-fallback');
        }
        if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
        if (btnEliminarFoto) {
            btnEliminarFoto.style.display = 'inline-flex';  // Mostrar botón eliminar después de cargar foto
            btnEliminarFoto.disabled = false;
        }
        
        document.getElementById('eliminarFoto').value = '0';
        markFormDirty();
        
        bootstrap.Modal.getInstance(document.getElementById('cropModal')).hide();
        limpiarCropper();
		
		// Actualizar la foto del solapín
        setTimeout(() => {
            actualizarSolapinFoto();
        }, 100);
    }
}

function eliminarFotoActual() {
    const logoTnBase64 = '<?php echo $logo_base64; ?>';
    
    Swal.fire(Object.assign({
        title: '<i class="fas fa-trash-alt text-danger me-2"></i> Eliminar foto',
        text: '¿Seguro que desea eliminar la foto del empleado?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar'
    }, swalDark)).then(r => {
        if (r.isConfirmed) {
            const imagePreview = document.getElementById('imagePreview');
            const fotoPlaceholder = document.getElementById('fotoPlaceholder');
            const btnEliminarFoto = document.getElementById('btnEliminarFoto');
            
            if (imagePreview) {
                imagePreview.src = logoTnBase64;
                imagePreview.style.display = 'block';
                imagePreview.style.objectFit = 'contain';
                imagePreview.style.padding = '20px';
                imagePreview.style.borderRadius = '50%';
                imagePreview.classList.add('logo-fallback');
            }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
            if (btnEliminarFoto) {
                btnEliminarFoto.style.display = 'none';  // Ocultar botón eliminar después de eliminar
            }
            
            document.getElementById('eliminarFoto').value = '1';
            document.getElementById('imagen_recortada').value = '';
            markFormDirty();
            
            Swal.fire({
                title: '<i class="fas fa-check-circle text-success me-2"></i> Foto eliminada',
                text: 'Se ha eliminado la foto. Se mostrará el logo por defecto.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                background: '#1a1a2e',
                color: '#ffffff'
            });
			// Actualizar solapín con el logo
			setTimeout(() => {
				actualizarSolapinFoto();
			}, 100);
        }
    });
}



function abrirEditorFotoDesdePreview() {
    const imagePreview = document.getElementById('imagePreview');
    const imageUpload = document.getElementById('imageUpload');
    
    if (!imageUpload) return;
    
    // Verificar si hay una foto actual válida
    if (imagePreview && imagePreview.style.display === 'block' && imagePreview.src && imagePreview.src !== '' && imagePreview.src !== window.location.href) {
        // Intentar cargar la foto actual para edición
        fetch(imagePreview.src, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    // La foto existe, cargarla para edición
                    fetch(imagePreview.src)
                        .then(res => res.blob())
                        .then(blob => {
                            const file = new File([blob], "foto_actual.jpg", { type: "image/jpeg" });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            imageUpload.files = dataTransfer.files;
                            cargarImagenParaRecorte(imageUpload);
                        })
                        .catch(() => {
                            // Error al cargar la foto, abrir selector de archivo
                            Swal.fire({
                                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Foto no disponible',
                                text: 'La foto actual no se puede editar. Puede subir una nueva.',
                                icon: 'warning',
                                confirmButtonText: '<i class="fas fa-upload me-2"></i> Subir Nueva',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            }).then(() => {
                                imageUpload.click();
                            });
                        });
                } else {
                    // La foto no existe, abrir selector de archivo
                    Swal.fire({
                        title: '<i class="fas fa-info-circle text-info me-2"></i> Sin foto',
                        html: 'No hay foto registrada o ha sido eliminada.<br>Puede subir una nueva.',
                        icon: 'info',
                        confirmButtonText: '<i class="fas fa-upload me-2"></i> Subir foto',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    }).then(() => {
                        imageUpload.click();
                    });
                }
            })
            .catch(() => {
                // Error de red, abrir selector de archivo
                imageUpload.click();
            });
    } else {
        // No hay foto, abrir selector de archivo directamente
        imageUpload.click();
    }
}

// GUARDADO Y ELIMINACIÓN AJAX
// Manejador de Guardado del Formulario sin cierre automático en ediciones
document.getElementById('empleadoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validación rápida de CI antes de enviar
    const ciInput = document.getElementById('ci');
    const ciLimpio = ciInput.value.replace(/\D/g, '');
    if (ciLimpio.length !== 11) {
        Swal.fire(Object.assign({
            icon: 'error',
            title: '<i class="fas fa-id-card text-danger me-2"></i> CI Inválido',
            html: `
                <div class="text-start">
                    <p class="mb-2">El Carnet de Identidad debe tener <strong class="text-info">11 dígitos</strong>.</p>
                    <div class="alert alert-success bg-info border-danger mt-2 p-2 rounded">
                        <i class="fas fa-info-circle me-1"></i> Ejemplo: <code>92010112345</code>
                    </div>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        }, swalDark));
        ciInput.focus();
        return;
    }
    
    // Mostrar loading
    Swal.fire(Object.assign({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Guardando...',
        text: 'Procesando la información del empleado',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    }, swalDark));
    
    fetch(window.location.href, { 
        method: 'POST', 
        body: new FormData(this), 
        headers: {'X-Requested-With': 'XMLHttpRequest'} 
    })
    .then(r => r.json())
    .then(d => {
        if(d.success) {
            resetFormDirty();
            
            if(d.data) {
                const idx = empleadosData.findIndex(e => e.id == d.data.id);
                if(idx !== -1) {
                    empleadosData[idx] = d.data;
                    actualizarFilaTablaHTML(d.data);
                    cargarEmpleadoEnFormulario(d.data);
                    Swal.fire(Object.assign({
                        icon: 'success',
                        title: '<i class="fas fa-check-circle text-success me-2"></i> Cambios Guardados',
                        html: `
                            <div class="text-center">
                                <p class="mb-1">${d.message || 'El registro del empleado se actualizó correctamente.'}</p>
                                <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                    <i class="fas fa-user-check me-1"></i> 
                                    <strong>${document.getElementById('nombres')?.value || ''} ${document.getElementById('primer_apellido')?.value || ''}</strong>
                                </div>
                            </div>
                        `,
                        timer: 2000,
                        showConfirmButton: false
                    }, swalDark));
                } else { 
                    empleadosData.push(d.data); 
                    currentIndex = empleadosData.length - 1;
                    Swal.fire(Object.assign({
                        icon: 'success',
                        title: '<i class="fas fa-user-plus text-success me-2"></i> Empleado Creado',
                        html: `
                            <div class="text-center">
                                <p class="mb-1">${d.message || 'El nuevo empleado ha sido registrado.'}</p>
                                <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                    <i class="fas fa-id-card me-1"></i> 
                                    <strong>Expediente: ${d.data.codigo}</strong>
                                </div>
                            </div>
                        `,
                        timer: 1500,
                        showConfirmButton: false
                    }, swalDark));
                    setTimeout(() => window.location.reload(), 1500);
                }
            }
        } else {
            // ============================================
            // MEJORA DE MENSAJES DE ERROR CON SweetAlert
            // ============================================
            const mensajeError = d.message || 'Ocurrió un error al procesar la solicitud.';
            
            // Detectar el tipo de error para mostrar un ícono y formato específico
            let icono = 'error';
            let titulo = '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Error';
            let htmlError = `<p class="mb-0">${mensajeError}</p>`;
            
            if (mensajeError.includes('CI') || mensajeError.includes('Carnet')) {
                titulo = '<i class="fas fa-id-card text-danger me-2"></i> Error de Identificación';
                htmlError = `
                    <div class="text-start">
                        <p class="mb-2">${mensajeError}</p>
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-light border-danger mt-2 p-2 rounded">
                            <i class="fas fa-info-circle me-1"></i> El CI debe tener <strong>11 dígitos</strong> (formato: AAMMDDXXXXX)
                        </div>
                    </div>
                `;
            } 
            else if (mensajeError.includes('expediente')) {
                titulo = '<i class="fas fa-folder-open text-danger me-2"></i> Error de Expediente';
                htmlError = `
                    <div class="text-start">
                        <p class="mb-2">${mensajeError}</p>
                        <div class="alert alert-warning bg-warning bg-opacity-10 border-warning mt-2 p-2 rounded">
                            <i class="fas fa-lightbulb me-1"></i> El expediente se genera automáticamente desde los últimos 6 dígitos del CI
                        </div>
                    </div>
                `;
            }
            else if (mensajeError.includes('Cuenta') || mensajeError.includes('cuenta')) {
                titulo = '<i class="fas fa-credit-card text-danger me-2"></i> Error de Cuenta Bancaria';
                htmlError = `
                    <div class="text-start">
                        <p class="mb-2">${mensajeError}</p>
                        <div class="alert alert-info bg-info bg-opacity-10 border-info mt-2 p-2 rounded">
                            <i class="fas fa-info-circle me-1"></i> La cuenta debe tener <strong>14 o 16 dígitos</strong> numéricos
                        </div>
                    </div>
                `;
            }
            else if (mensajeError.includes('Área') || mensajeError.includes('área')) {
                titulo = '<i class="fas fa-building text-danger me-2"></i> Error de Selección';
                htmlError = `<p class="mb-0">${mensajeError}</p>`;
            }
            else if (mensajeError.includes('Cargo')) {
                titulo = '<i class="fas fa-briefcase text-danger me-2"></i> Error de Selección';
                htmlError = `<p class="mb-0">${mensajeError}</p>`;
            }
            else if (mensajeError.includes('Categoría')) {
                titulo = '<i class="fas fa-chart-simple text-danger me-2"></i> Error de Selección';
                htmlError = `<p class="mb-0">${mensajeError}</p>`;
            }
            else if (mensajeError.includes('Escala')) {
                titulo = '<i class="fas fa-dollar-sign text-danger me-2"></i> Error de Selección';
                htmlError = `<p class="mb-0">${mensajeError}</p>`;
            }
            else if (mensajeError.includes('Centro de Costo')) {
                titulo = '<i class="fas fa-chart-pie text-danger me-2"></i> Error de Selección';
                htmlError = `<p class="mb-0">${mensajeError}</p>`;
            }
            else if (mensajeError.includes('fecha')) {
                titulo = '<i class="fas fa-calendar-alt text-danger me-2"></i> Error de Fecha';
                htmlError = `<p class="mb-0">${mensajeError}</p>`;
            }
            
            Swal.fire(Object.assign({
                icon: 'error',
                title: titulo,
                html: htmlError,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                confirmButtonColor: '#dc3545'
            }, swalDark));
        }
    })
    .catch(err => {
        Swal.fire(Object.assign({
            icon: 'error',
            title: '<i class="fas fa-wifi text-danger me-2"></i> Error de Conexión',
            html: `
                <div class="text-center">
                    <p class="mb-2">No se pudo conectar con el servidor.</p>
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger mt-2 p-2 rounded">
                        <i class="fas fa-exclamation-circle me-1"></i> ${err.toString()}
                    </div>
                    <button class="btn-win btn-win-primary btn-sm mt-2" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i> Reintentar
                    </button>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar',
            confirmButtonColor: '#6c757d',
            showConfirmButton: true
        }, swalDark));
    });
});


function eliminarTrabajador(id, nom) {
    Swal.fire(Object.assign({title:'¿Eliminar Empleado?', html:`¿Borrar permanentemente a <strong>${nom}</strong>?`, icon:'warning', showCancelButton:true, confirmButtonColor:'#dc3545', confirmButtonText:'<i class="fas fa-trash-alt me-2"></i> Sí, eliminar', cancelButtonText:'<i class="fas fa-times me-2"></i> Cancelar'}, swalDark))
    .then(r => { if(r.isConfirmed) { let f=document.createElement('form'); f.method='POST'; f.innerHTML=`<input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="${id}">`; document.body.appendChild(f); f.submit(); } });
}

// =====================================================================
// REPORTE DE EMPLEADOS (Imprimir / Word / PDF / Excel) — horizontal, Carta
// Diseño homologado al de las Nóminas (SC-4-06) en nominas.php
// =====================================================================
var REPORTE_EMP_EMPRESA = '<?php echo htmlspecialchars($config_empresa['nombre_empresa'] ?? "PDL TransNuBeT"); ?>';
var REPORTE_EMP_USUARIO  = '<?php echo htmlspecialchars($user_nombre_completo ?? "Usuario"); ?>';
var REPORTE_EMP_LOGO     = '<?php echo $logo_base64; ?>';
var REPORTE_EMP_JEFE     = '<?php echo htmlspecialchars($config_empresa['jefe_proyecto'] ?? "Dainelys León Reyes"); ?>';
var REPORTE_EMP_ESPECIALISTA = '<?php echo htmlspecialchars($config_empresa['especialista_gestion'] ?? "Mailén Pérez García"); ?>';
// Subíndices (columna exportada - 2) que van alineadas a la derecha: Salario, $/Hora, Vac. Días, Vac. Impte., Vac. a Pagar, Valor * Día
var REPORTE_EMP_COLS_DERECHA = [7, 8, 12, 13, 14, 15];
var REPORTE_EMP_FILAS_POR_PAGINA = 14;

function reporteEmp_escHtml(t) {
    if (t === null || t === undefined) return '';
    return $('<div>').text(String(t)).html();
}

function reporteEmp_fechaHora12h() {
    var now = new Date();
    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var year = now.getFullYear();
    var h24 = now.getHours();
    var ampm = h24 >= 12 ? 'PM' : 'AM';
    var h12 = h24 % 12; if (h12 === 0) h12 = 12;
    var min = String(now.getMinutes()).padStart(2, '0');
    var sec = String(now.getSeconds()).padStart(2, '0');
    return day + '/' + month + '/' + year + ' - ' + String(h12).padStart(2, '0') + ':' + min + ':' + sec + ' ' + ampm;
}

function reporteEmp_alcanceFiltros() {
    var partes = [];
    var $search = $('#customSearchInput');
    if ($search.length && String($search.val() || '').trim() !== '') {
        partes.push('Búsqueda: ' + String($search.val()).trim());
    }
    var mapa = [
        ['#filtroEmpleado', 'Empleado'],
        ['#filtroTipoContrato', 'Tipo Contrato'],
        ['#filtroPagoVacaciones', 'Pago Vacaciones'],
        ['#filtroArea', 'Área'],
        ['#filtroCentroCosto', 'Centro Costo'],
        ['#filtroCuentaBancaria', 'Cuenta Bancaria'],
        ['#filtroFoto', 'Foto Perfil'],
        ['#filtroEstadoLaboral', 'Estado Laboral']
    ];
    mapa.forEach(function(par) {
        var $el = $(par[0]);
        if ($el.length && String($el.val() || '') !== '') {
            var texto = $el.find('option:selected').text().replace(/^[\s\W_]+/, '').trim();
            partes.push(par[1] + ': ' + (texto || 'Seleccionado'));
        }
    });
    return partes.length ? partes.join('  |  ') : 'Sin filtros aplicados';
}

var REPORTE_EMP_CSS = `
    @page {
        size: letter landscape;
        margin: 12mm 10mm 20mm 10mm;
    }
    body {
        font-family: 'Arial', sans-serif !important;
        font-size: 9pt !important;
        color: #000000 !important;
        background-color: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .rep-emp-cabecera {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 10px !important;
        border: none !important;
    }
    .rep-emp-cabecera td {
        border: none !important;
        padding: 0 !important;
        vertical-align: middle !important;
        background-color: #ffffff !important;
    }
    .rep-emp-cabecera-logo { width: 90px !important; padding-right: 12px !important; }
    .rep-emp-cabecera-logo img { width: 75px !important; }
    .rep-emp-cabecera-titulo { border-bottom: 2px solid #004B87 !important; padding-bottom: 8px !important; }
    .rep-emp-empresa { font-size: 13pt !important; font-weight: bold !important; color: #004B87 !important; }
    .rep-emp-titulo { font-size: 11pt !important; font-weight: bold !important; color: #333333 !important; }
    .rep-emp-alcance { font-size: 8pt !important; color: #444444 !important; margin-top: 4px !important; }
    .rep-emp-cabecera-datos {
        width: 230px !important;
        text-align: right !important;
        font-size: 8pt !important;
        color: #444444 !important;
        line-height: 1.5 !important;
        border-bottom: 2px solid #004B87 !important;
        padding-bottom: 8px !important;
    }
    .rep-emp-total { color: #004B87 !important; font-weight: bold !important; }
    .rep-emp-tabla {
        width: 100% !important;
        border-collapse: collapse !important;
        page-break-inside: auto !important;
    }
    .rep-emp-tabla thead { display: table-header-group !important; }
    .rep-emp-tabla th {
        background-color: #004B87 !important;
        color: #ffffff !important;
        border: 0.5pt solid #000000 !important;
        padding: 4px 3px !important;
        font-size: 7.5pt !important;
        font-weight: bold !important;
        text-align: left !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .rep-emp-tabla td {
        border: 0.5pt solid #000000 !important;
        padding: 3px !important;
        font-size: 8pt !important;
    }
    .rep-emp-tabla tbody tr:nth-child(even) td {
        background-color: #f0f4f8 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .text-right { text-align: right !important; }
    .print-sheet { page-break-after: always !important; break-after: page !important; }
    .print-sheet-last { page-break-after: auto !important; }
    .print-sheet-footer {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        border-top: 1px solid #000000 !important;
        padding-top: 4px !important;
        margin-top: 6px !important;
        font-size: 8pt !important;
        font-weight: bold !important;
    }
    .print-sheet-footer-center { text-align: center !important; }
    .print-sheet-footer-right { text-align: right !important; }
    .rep-emp-firmas { margin-top: 40px !important; page-break-inside: avoid !important; break-inside: avoid !important; }
    .rep-emp-firmas-tabla { width: 100% !important; border: none !important; border-collapse: collapse !important; }
    .rep-emp-firmas-tabla td {
        width: 33.33% !important;
        text-align: center !important;
        border: none !important;
        padding: 8px !important;
        font-size: 8.5pt !important;
        vertical-align: top !important;
    }
    .rep-emp-firma-label { font-weight: bold !important; margin-bottom: 45px !important; }
    .rep-emp-firma-linea { border-top: 1px solid #000000 !important; width: 85% !important; margin: 0 auto !important; padding-top: 3px !important; }
    .rep-emp-firma-cargo { font-weight: bold !important; font-size: 8pt !important; margin-top: 3px !important; }
    .rep-emp-firma-subcargo { font-size: 7pt !important; color: #555555 !important; margin-top: 1px !important; }
`;

function reporteEmp_exportOptions() {
    return {
        columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
        format: {
            header: function(data) { return $('<div>').html(data).text().trim(); },
            body: function(data) {
                if (typeof data !== 'string') return data;
                return $('<div>').html(data).text().trim();
            }
        }
    };
}

function reporteEmp_construirContenido(data) {
    var fechaHora = reporteEmp_fechaHora12h();
    var total = data.body.length;
    var alcance = reporteEmp_alcanceFiltros();

    var thead = '<tr>';
    data.header.forEach(function(col) { thead += '<th>' + reporteEmp_escHtml(col) + '</th>'; });
    thead += '</tr>';
    var theadHtml = '<thead>' + thead + '</thead>';

    var html = '';

    html += `
        <table class="rep-emp-cabecera" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rep-emp-cabecera-logo">
                    ${REPORTE_EMP_LOGO ? '<img src="' + REPORTE_EMP_LOGO + '" alt="Logo">' : ''}
                </td>
                <td class="rep-emp-cabecera-titulo">
                    <div class="rep-emp-empresa">${reporteEmp_escHtml(REPORTE_EMP_EMPRESA)}</div>
                    <div class="rep-emp-titulo">LISTADO DE EMPLEADOS</div>
                    <div class="rep-emp-alcance"><strong>Alcance:</strong> ${reporteEmp_escHtml(alcance)}</div>
                </td>
                <td class="rep-emp-cabecera-datos">
                    <strong>Emisión:</strong> ${fechaHora}<br>
                    <strong>Generado por:</strong> ${reporteEmp_escHtml(REPORTE_EMP_USUARIO)}<br>
                    <strong>Total Trabajadores:</strong> <span class="rep-emp-total">${total}</span>
                </td>
            </tr>
        </table>`;

    var totalPaginas = Math.max(1, Math.ceil(data.body.length / REPORTE_EMP_FILAS_POR_PAGINA));
    for (var p = 0; p < totalPaginas; p++) {
        var filas = data.body.slice(p * REPORTE_EMP_FILAS_POR_PAGINA, (p + 1) * REPORTE_EMP_FILAS_POR_PAGINA);
        var tbody = '';
        filas.forEach(function(row) {
            tbody += '<tr>';
            row.forEach(function(celda, idx) {
                var clase = REPORTE_EMP_COLS_DERECHA.indexOf(idx) !== -1 ? ' class="text-right"' : '';
                tbody += '<td' + clase + '>' + reporteEmp_escHtml(celda) + '</td>';
            });
            tbody += '</tr>';
        });
        var ultima = p === totalPaginas - 1;
        html += `
            <div class="print-sheet ${ultima ? 'print-sheet-last' : ''}">
                <table class="rep-emp-tabla">
                    ${theadHtml}
                    <tbody>${tbody}</tbody>
                </table>
                <div class="print-sheet-footer">
                    <div>LISTADO DE EMPLEADOS - ${reporteEmp_escHtml(REPORTE_EMP_EMPRESA)}</div>
                    <div class="print-sheet-footer-center">Página ${p + 1} de ${totalPaginas}</div>
                    <div class="print-sheet-footer-right">Impresión: ${fechaHora}</div>
                </div>
            </div>`;
    }

    html += `
        <div class="rep-emp-firmas">
            <table class="rep-emp-firmas-tabla">
                <tr>
                    <td>
                        <p class="rep-emp-firma-label">Elaborado por:</p>
                        <p class="rep-emp-firma-linea"></p>
                        <p class="rep-emp-firma-cargo">${reporteEmp_escHtml(REPORTE_EMP_USUARIO)}</p>
                        <p class="rep-emp-firma-subcargo">Especialista de Nóminas</p>
                    </td>
                    <td>
                        <p class="rep-emp-firma-label">Revisado por:</p>
                        <p class="rep-emp-firma-linea"></p>
                        <p class="rep-emp-firma-cargo">${reporteEmp_escHtml(REPORTE_EMP_ESPECIALISTA)}</p>
                        <p class="rep-emp-firma-subcargo">Especialista en Gestión Económica</p>
                    </td>
                    <td>
                        <p class="rep-emp-firma-label">Aprobado por:</p>
                        <p class="rep-emp-firma-linea"></p>
                        <p class="rep-emp-firma-cargo">${reporteEmp_escHtml(REPORTE_EMP_JEFE)}</p>
                        <p class="rep-emp-firma-subcargo">Director de Proyecto</p>
                    </td>
                </tr>
            </table>
        </div>`;

    return html;
}

function reporteEmp_abrirImpresion(data) {
    var contenido = reporteEmp_construirContenido(data);
    var win = window.open('', '_blank');
    if (!win) {
        Swal.fire(Object.assign({
            title: '<i class="fas fa-external-link-alt me-2" style="color:#fbbf24;"></i> Permiso requerido',
            html: '<div class="text-center"><p>El navegador bloqueó la ventana emergente.</p><p class="text-muted small">Permita las ventanas emergentes para este sitio e inténtelo de nuevo.</p></div>',
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff'
        }, swalDark));
        return;
    }
    win.document.open();
    win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>LISTADO DE EMPLEADOS</title><style>' + REPORTE_EMP_CSS + '</style></head><body>' + contenido + '<script>window.onload = function() { setTimeout(function() { window.print(); }, 250); }<\/script></body></html>');
    win.document.close();
}

function reporteEmp_descargarWord(data) {
    var contenido = reporteEmp_construirContenido(data);
    var wordCss = REPORTE_EMP_CSS + `
        @page WordSection1 { size: 792pt 612pt; mso-page-orientation: landscape; margin: 0.75in 0.6in 0.75in 0.6in; }
        div.WordSection1 { page: WordSection1; }
        .print-sheet-footer { border-top: 1px solid #000000 !important; padding-top: 4px !important; margin-top: 6px !important; }
    `;
    var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>LISTADO DE EMPLEADOS</title><!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]--><style>' + wordCss + '</style></head><body><div class="WordSection1">' + contenido + '</div></body></html>';
    var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    var fecha = new Date();
    var fileName = 'Reporte_Empleados_' + fecha.getFullYear() + ('0' + (fecha.getMonth() + 1)).slice(-2) + ('0' + fecha.getDate()).slice(-2) + '.doc';
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}

function reporteEmp_parseNumero(celda) {
    if (celda === null || celda === undefined) return 0;
    var t = String(celda).replace(/[$,\s]/g, '').trim();
    if (t === '' || t === '-' || t === '—') return 0;
    var n = parseFloat(t);
    return isNaN(n) ? 0 : n;
}

function reporteEmp_construirTXT(data) {
    var fechaHora = reporteEmp_fechaHora12h();
    var alcance = reporteEmp_alcanceFiltros();
    var lineas = [];

    lineas.push(REPORTE_EMP_EMPRESA);
    lineas.push('LISTADO DE EMPLEADOS');
    lineas.push('Generado por: ' + REPORTE_EMP_USUARIO + '  |  Emisión: ' + fechaHora + '  |  Total trabajadores: ' + data.body.length);
    lineas.push('Alcance: ' + alcance);
    lineas.push('');

    lineas.push(data.header.map(function(h) { return (h || '').toString().trim(); }).join('\t'));

    data.body.forEach(function(row) {
        lineas.push(row.map(function(celda) { return (celda || '').toString().trim(); }).join('\t'));
    });

    var subSalario = 7, subHora = 8, subVacDias = 12, subVacImpte = 13, subVacPagar = 14, subValorDia = 15;
    var totalSalario = 0, totalHora = 0, totalVacDias = 0, totalVacImpte = 0, totalVacPagar = 0, totalValorDia = 0;
    data.body.forEach(function(row) {
        totalSalario  += reporteEmp_parseNumero(row[subSalario]);
        totalHora     += reporteEmp_parseNumero(row[subHora]);
        totalVacDias  += reporteEmp_parseNumero(row[subVacDias]);
        totalVacImpte += reporteEmp_parseNumero(row[subVacImpte]);
        totalVacPagar += reporteEmp_parseNumero(row[subVacPagar]);
        totalValorDia += reporteEmp_parseNumero(row[subValorDia]);
    });

    var ncols = data.header.length;
    var filaTotales = ['TOTALES'];
    for (var i = 1; i < ncols; i++) filaTotales.push('');
    if (subSalario < ncols)  filaTotales[subSalario]  = '$' + totalSalario.toFixed(2);
    if (subHora < ncols)     filaTotales[subHora]     = '$' + totalHora.toFixed(2);
    if (subVacDias < ncols)  filaTotales[subVacDias]  = totalVacDias.toFixed(2);
    if (subVacImpte < ncols) filaTotales[subVacImpte] = '$' + totalVacImpte.toFixed(2);
    if (subVacPagar < ncols) filaTotales[subVacPagar] = '$' + totalVacPagar.toFixed(2);
    if (subValorDia < ncols) filaTotales[subValorDia] = '$' + totalValorDia.toFixed(2);
    lineas.push(filaTotales.join('\t'));
    lineas.push('');
    lineas.push('FIN DEL REPORTE - ' + REPORTE_EMP_EMPRESA);

    return lineas.join('\r\n') + '\r\n';
}

// INICIALIZACIÓN DataTables Y Filtros
$(document).ready(function() {
    let table = $('#empleadosTable').DataTable({
        language: {
            "decimal": "",
            "emptyTable": "No hay datos disponibles",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ registros",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "No se encontraron registros coincidentes",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            },
            "aria": {
                "sortAscending": ": activar para ordenar ascendente",
                "sortDescending": ": activar para ordenar descendente"
            },
            buttons: {
                colvisRestore: 'Restaurar columnas'
            },
        },
        pageLength: 5,
        responsive: true, 
        order: [[4, 'asc']],
        lengthMenu: [[5, 10, 15, 20, 25, 50, 100, -1], [5, 10, 15, 20, 25, 50, 100, "Todos"]],
        dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-colvis"c><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        buttons: [
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns me-1"></i> Columnas',
                className: 'btn-win btn-sm',
                columns: ':not(:first-child):not(:nth-child(2))',
                postfixButtons: [ 'colvisRestore' ]
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf text-danger me-2"></i> PDF',
                className: 'btn-win btn-sm btn-export-pdf',
                orientation: 'landscape',
                pageSize: 'LETTER',
                exportOptions: {
                    columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
                    format: { 
						body: function(data) { 
							if (typeof data === 'string') {
								return $('<div>').html(data).text(); 
							}
							return data;
						} 
					}
                },
                customize: function(doc) {
                    var totalEmp = 0;
                    var dtEmp = $('#empleadosTable').DataTable();
                    if (dtEmp) totalEmp = dtEmp.rows({ search: 'applied' }).count();
                    doc.pageMargins = [22, 50, 22, 40];
                    doc.defaultStyle.fontSize = 7;
                    var cabeceraCols = [];
                    if (REPORTE_EMP_LOGO) cabeceraCols.push({ image: REPORTE_EMP_LOGO, width: 48 });
                    cabeceraCols.push({ width: '*', stack: [
                        { text: REPORTE_EMP_EMPRESA, style: 'repTitulo' },
                        { text: 'LISTADO DE EMPLEADOS', style: 'repSubtitulo' },
                        { text: 'Generado por: ' + REPORTE_EMP_USUARIO + '  |  Emisión: ' + reporteEmp_fechaHora12h() + '  |  Total trabajadores: ' + totalEmp, style: 'repMeta' }
                    ] });
                    doc.content.splice(0, 0, { columns: cabeceraCols, style: 'repCabecera' });
                    doc.styles.repTitulo = { fontSize: 13, bold: true, color: '#004B87', margin: [0, 0, 0, 2] };
                    doc.styles.repSubtitulo = { fontSize: 11, bold: true, margin: [0, 2, 0, 4] };
                    doc.styles.repMeta = { fontSize: 8, color: '#444444', margin: [0, 2, 0, 10] };
                    if (doc.styles.tableHeader) {
                        doc.styles.tableHeader.fillColor = '#004B87';
                        doc.styles.tableHeader.color = '#FFFFFF';
                        doc.styles.tableHeader.fontSize = 7;
                    }
                    doc.footer = function(currentPage, pageCount) {
                        return {
                            columns: [
                                { text: 'LISTADO DE EMPLEADOS - ' + REPORTE_EMP_EMPRESA, alignment: 'left', fontSize: 7 },
                                { text: 'Página ' + currentPage + ' de ' + pageCount, alignment: 'right', fontSize: 7 }
                            ],
                            margin: [22, 0, 22, 0]
                        };
                    };
                }
            },
            {
                text: '<i class="fas fa-file-word text-primary me-2"></i> Word',
                className: 'btn-win btn-sm btn-export-word',
                action: function(e, dt, node, config) {
                    var data = dt.buttons.exportData(reporteEmp_exportOptions());
                    reporteEmp_descargarWord(data);
                }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel text-success me-2"></i> Excel',
                className: 'btn-win btn-sm btn-export-excel',
                exportOptions: {
                    columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
                    format: { 
						body: function(data) { 
							if (typeof data === 'string') {
								return $('<div>').html(data).text(); 
							}
							return data;
						} 
					}
                },
                customize: function(xlsx) {
                    try {
                        var sheet = xlsx.xl.worksheets['sheet1.xml'];
                        var xml = sheet.getData();
                        if (xml.indexOf('<pageSetup') === -1) {
                            var pos = xml.indexOf('</worksheet>');
                            if (pos !== -1) {
                                sheet.setData(xml.slice(0, pos) + '<pageSetup orientation="landscape" paperSize="1" fitToWidth="1" fitToHeight="1"/>' + xml.slice(pos));
                            }
                        }
                    } catch (err) {}
                }
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv text-info me-2"></i> CSV',
                className: 'btn-win btn-sm btn-export-csv',
                exportOptions: {
                    columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
                    format: { 
						body: function(data) { 
							if (typeof data === 'string') {
								return $('<div>').html(data).text(); 
							}
							return data;
						} 
					}
                }
            },
            {
                text: '<i class="fas fa-print text-warning me-2"></i> Imprimir',
                className: 'btn-win btn-sm btn-export-print',
                action: function(e, dt, node, config) {
                    var data = dt.buttons.exportData(reporteEmp_exportOptions());
                    if (!data.body.length) {
                        Swal.fire(Object.assign({
                            title: '<i class="fas fa-inbox me-2" style="color: #60a5fa;"></i> Sin filas visibles',
                            html: '<div class="text-center"><p>No hay empleados visibles para generar el reporte.</p><p class="text-muted small">Revise la búsqueda y los filtros aplicados.</p></div>',
                            icon: 'info',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                            confirmButtonColor: '#3b82f6',
                            background: '#1a1a2e',
                            color: '#ffffff'
                        }, swalDark));
                        return;
                    }
                    reporteEmp_abrirImpresion(data);
                }
            },
{
                text: '<i class="fas fa-file-alt text-secondary me-2"></i> TXT',
                className: 'btn-win btn-sm btn-export-txt',
                action: function(e, dt, node, config) {
                    var data = dt.buttons.exportData(reporteEmp_exportOptions());
                    var txtContent = reporteEmp_construirTXT(data);
                    var blob = new Blob(['\ufeff', txtContent], { type: 'text/plain;charset=utf-8;' });
                    var fecha = new Date();
                    var fileName = 'Reporte_Empleados_' + fecha.getFullYear() + 
                                   ('0' + (fecha.getMonth() + 1)).slice(-2) + 
                                   ('0' + fecha.getDate()).slice(-2) + '.txt';
                    var link = document.createElement("a");
                    link.href = URL.createObjectURL(blob);
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                }
            }
        ],
        drawCallback: function() {
            $('#empleadosTable tbody td:nth-child(15)').each(function() {
                let vacDias = parseFloat($(this).text()) || 0;
                if (vacDias > 20) $(this).addClass('vacaciones-excedidas');
                else $(this).removeClass('vacaciones-excedidas');
            });
        }
    });
    
    // Clic en fila para editar
    $('#empleadosTable tbody').on('click', 'tr.empleado-row', function(e) {
        if ($(e.target).closest('button').length || $(e.target).closest('img').length || 
            $(e.target).closest('.fa-trash-alt').length || $(e.target).closest('.fa-user-circle').length) {
            return;
        }
        const empId = $(this).data('id');
        const emp = empleadosData.find(e => e.id == empId);
        if (emp) editEmpleado(emp);
    });

    // Personalizar buscador
    $('.dt-search').html(`
        <div class="input-group input-group-sm" style="width: 320px;">
            <span class="input-group-text bg-dark border-secondary"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Buscar empleado..." id="customSearchInput">
            <button class="btn btn-sm btn-outline-secondary" type="button" id="clearSearchBtn" style="background: rgba(20,20,25,0.8);"><i class="fas fa-times"></i></button>
        </div>
    `);

    $('#customSearchInput').on('keyup', function() { table.search(this.value).draw(); });
    $('#clearSearchBtn').on('click', function() { $('#customSearchInput').val(''); table.search('').draw(); });
    
    // Filtros
    $('#filtroEmpleado').on('change', function() {
        let texto = $(this).find('option:selected').text();
        let partes = texto.split(' - ');
        table.columns(4).search(partes.length > 1 ? partes[1] : texto).draw();
    });
    
// Filtro por Tipo de Contrato - SIN FILTROS PERSONALIZADOS
$('#filtroTipoContrato').on('change', function() {
    var valor = this.value;
    var table = $('#empleadosTable').DataTable();
    
    // Usar búsqueda en la columna 19 (Tipo Contrato)
    if (valor === '') {
        table.column(19).search('').draw();
    } else {
        // Búsqueda exacta con expresiones regulares
        table.column(19).search('^' + $.fn.dataTable.util.escapeRegex(valor) + '$', true, false).draw();
    }
});
    
    $('#filtroPagoVacaciones').on('change', function() {
        if (this.value === '1') {
            $.fn.dataTable.ext.search.push(function(settings, data) {
                let vacPagar = data[16] || '';
                return vacPagar.trim() !== '-' && vacPagar.trim() !== '';
            });
            table.draw();
            $.fn.dataTable.ext.search.pop();
        } else if (this.value === '0') {
            $.fn.dataTable.ext.search.push(function(settings, data) {
                let vacPagar = data[16] || '';
                return vacPagar.trim() === '-' || vacPagar.trim() === '';
            });
            table.draw();
            $.fn.dataTable.ext.search.pop();
        } else { table.draw(); }
    });
    
    $('#filtroArea').on('change', function() {
        let texto = $(this).find('option:selected').text();
        if (texto && texto !== '-- Todas las áreas --') table.columns(5).search('^' + $.fn.dataTable.util.escapeRegex(texto) + '$', true, false).draw();
        else table.columns(5).search('').draw();
    });
    
    $('#filtroCentroCosto').on('change', function() {
        let texto = $(this).find('option:selected').text();
        let partes = texto.split(' - ');
        table.columns(6).search(partes.length > 1 ? partes[1] : texto).draw();
    });
    
    $('#filtroCuentaBancaria').on('change', function() {
        if (this.value === 'con_cuenta') {
            $.fn.dataTable.ext.search.push(function(settings, data) {
                return (data[11] || '').trim() !== '-' && (data[11] || '').trim() !== '';
            });
            table.draw();
            $.fn.dataTable.ext.search.pop();
        } else if (this.value === 'sin_cuenta') {
            $.fn.dataTable.ext.search.push(function(settings, data) {
                return (data[11] || '').trim() === '-' || (data[11] || '').trim() === '';
            });
            table.draw();
            $.fn.dataTable.ext.search.pop();
        } else { table.draw(); }
    });

$('#filtroFoto').on('change', function() {
    let seleccion = this.value;
    
    // Limpiar filtros previos específicos de foto
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
        return fn.name !== 'filtroFotoCustom';
    });

    if (seleccion === 'con_foto') {
        $.fn.dataTable.ext.search.push(function filtroFotoCustom(settings, data, dataIndex) {
            let cell = table.cell(dataIndex, 1).node(); // Columna index 1 (Foto)
            return $(cell).attr('data-tiene-foto') === '1';
        });
    } else if (seleccion === 'sin_foto') {
        $.fn.dataTable.ext.search.push(function filtroFotoCustom(settings, data, dataIndex) {
            let cell = table.cell(dataIndex, 1).node(); // Columna index 1 (Foto)
            return $(cell).attr('data-tiene-foto') === '0';
        });
    }
    table.draw();
});
// Filtro por Estado Laboral (fecha_baja)
function filtrarPorEstadoLaboral(estado) {
    var table = $('#empleadosTable').DataTable();
    
    if (estado === 'activo') {
        // Limpiar cualquier filtro personalizado previo con el mismo nombre
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
            return fn.name !== 'filtroEstadoLaboral';
        });
        
        $.fn.dataTable.ext.search.push(function filtroEstadoLaboral(settings, data, dataIndex) {
            // 'data' es un array con los valores de las columnas
            // La fecha de baja está en el índice 13 (columna 14, base 0)
            var fechaBaja = data[13];
            return fechaBaja === '-' || fechaBaja === ''; // Activo si no tiene fecha de baja
        });
        table.draw();
    } 
    else if (estado === 'inactivo') {
        // Limpiar cualquier filtro personalizado previo con el mismo nombre
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
            return fn.name !== 'filtroEstadoLaboral';
        });
        
        $.fn.dataTable.ext.search.push(function filtroEstadoLaboral(settings, data, dataIndex) {
            var fechaBaja = data[13];
            return fechaBaja !== '-' && fechaBaja !== ''; // Inactivo si tiene fecha de baja
        });
        table.draw();
    } 
    else {
        // Limpiar el filtro personalizado
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
            return fn.name !== 'filtroEstadoLaboral';
        });
        table.draw();
    }
}

// Evento del nuevo filtro
$('#filtroEstadoLaboral').on('change', function() {
    filtrarPorEstadoLaboral(this.value);
});
$('#btnLimpiarFiltros').on('click', function() {
    // 1. Restablecer todos los selects a su valor por defecto
    $('#filtroEmpleado, #filtroTipoContrato, #filtroPagoVacaciones, #filtroArea, #filtroCentroCosto, #filtroCuentaBancaria, #filtroFoto, #filtroEstadoLaboral').val('');
    
    // 2. Eliminar TODOS los filtros personalizados de DataTables
    // (Esto incluye foto y estado laboral)
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
        // Conservar solo filtros nativos, eliminar los personalizados
        return fn.name !== 'filtroFotoCustom' && fn.name !== 'filtroEstadoLaboral';
    });
    
    // 3. Limpiar todas las búsquedas por columna
    table.columns().every(function() { 
        this.search(''); 
    });
    
    // 4. Limpiar la búsqueda global de DataTables
    table.search('').draw();
    
    // 5. Limpiar el campo de búsqueda personalizado
    $('#customSearchInput').val('');
});
	
	inicializarSelectorEmpleados();
});

// Conectar menú de TopBar a Datatables
$('#menuColumnas').on('click', function(e) { e.preventDefault(); $('.buttons-colvis').click(); });
$('#menuExportPDF').on('click', function(e) { e.preventDefault(); $('.btn-export-pdf').click(); });
$('#menuExportWord').on('click', function(e) { e.preventDefault(); $('.btn-export-word').click(); });
$('#menuExportExcel').on('click', function(e) { e.preventDefault(); $('.btn-export-excel').click(); });
$('#menuExportCSV').on('click', function(e) { e.preventDefault(); $('.btn-export-csv').click(); });
$('#menuExportTXT').on('click', function(e) { e.preventDefault(); $('.btn-export-txt').click(); });
$('#menuExportPrint').on('click', function(e) { e.preventDefault(); $('.btn-export-print').click(); });



// Ver Foto Grande con manejo de errores y logo por defecto
function verFotoGrande(url, nombre, empleadoId) {
    const tieneFoto = url && url !== 'null' && url !== '' && url !== '../' && url !== '..';
    const logoPorDefecto = '<?php echo $logo_base64; ?>';
    
    if (tieneFoto) {
        const testImg = new Image();
        testImg.onload = function() {
            mostrarModalFoto(url, nombre, empleadoId, true);
        };
        testImg.onerror = function() {
            console.warn('Foto no encontrada o corrupta:', url);
            mostrarModalFoto(logoPorDefecto, nombre, empleadoId, false, true);
        };
        testImg.src = url;
    } else {
        mostrarModalFoto(logoPorDefecto, nombre, empleadoId, false, true);
    }
}

function mostrarModalFoto(url, nombre, empleadoId, tieneFotoReal, esLogoPorDefecto = false) {
    const logoHtml = esLogoPorDefecto ? 
        `<div style="width: 250px; height: 250px; background: linear-gradient(135deg, #1a1a2e, #2d2d44); border-radius: 16px; border: 3px solid #60a5fa; margin: 10px auto; display: flex; align-items: center; justify-content: center; flex-direction: column;">
            <img src="${url}" style="width: 120px; height: auto; object-fit: contain; opacity: 0.9;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'45\' fill=\'%233b82f6\'/%3E%3Ctext x=\'50\' y=\'65\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\' font-family=\'Arial\'%3E👤%3C/text%3E%3C/svg%3E'">
            <span style="margin-top: 10px; font-size: 12px; color: #60a5fa;"><i class="fas fa-info-circle me-1"></i>Sin foto registrada</span>
        </div>` :
        (tieneFotoReal ? 
            `<img src="${url}" style="width: 250px; height: 250px; object-fit: cover; border-radius: 16px; border: 3px solid #60a5fa; margin: 10px auto; display: block;">` :
            `<div style="width: 250px; height: 250px; background: rgba(255,255,255,0.05); border-radius: 16px; border: 3px solid #60a5fa; margin: 10px auto; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                <i class="fas fa-user-circle" style="font-size: 80px; color: rgba(255,255,255,0.3);"></i>
                <span style="margin-top: 10px; font-size: 13px; color: rgba(255,255,255,0.5);">Sin foto</span>
            </div>`);
    
    Swal.fire({
        title: `<div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-user-circle" style="font-size: 1.3rem; color: #60a5fa;"></i>
                    <span style="font-size: 1.1rem;">${nombre}</span>
                </div>`,
        html: `
            <div style="text-align: center;">
                ${logoHtml}
                <div class="mt-4 d-flex gap-2 justify-content-center" style="display: flex; gap: 10px; justify-content: center; margin-top: 15px; flex-wrap: wrap;">
                    <button type="button" class="btn-win btn-win-primary btn-sm" id="btnVerSolapinModal" style="cursor: pointer; padding: 6px 16px;">
                        <i class="fas fa-id-card me-2"></i> Ver Solapín
                    </button>
                    <button type="button" class="btn-win btn-win-primary btn-sm" id="btnExportarSolapinModal" style="cursor: pointer; padding: 6px 16px;">
                        <i class="fas fa-download me-2"></i> Exportar Solapín
                    </button>
                    <button type="button" class="btn-win btn-win-primary btn-sm" id="btnExportarWordSolapinModal" style="cursor: pointer; padding: 6px 16px;">
                        <i class="fas fa-file-word me-2"></i> Exportar a Word
                    </button>
                    <button type="button" class="btn-win btn-win-primary btn-sm" id="btnImprimirSolapinModal" style="cursor: pointer; padding: 6px 16px;">
                        <i class="fas fa-print me-2"></i> Imprimir Solapín
                    </button>
                </div>
                ${!tieneFotoReal ? '<div class="mt-3"><small class="text-muted">Puede agregar una foto desde el formulario de edición</small></div>' : ''}
            </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        background: '#1a1a2e',
        color: '#ffffff',
        width: '520px',
        customClass: { popup: 'foto-modal-win' },
        didOpen: () => {
            // Botón Ver Solapín
            const btnVer = document.getElementById('btnVerSolapinModal');
            if (btnVer) {
                btnVer.onclick = () => {
                    Swal.close();
                    window.verSolapin(empleadoId);
                };
            }
            
// Botón Exportar Solapín con tamaño calibrado a 7cm x 20.5cm (265px x 775px)
            const btnExportar = document.getElementById('btnExportarSolapinModal');
            if (btnExportar) {
                btnExportar.onclick = () => {
                    Swal.close();
                    Swal.fire({
                        title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Generando Solapín</strong>',
                        html: '<div class="text-center"><p class="mb-0">Ajustando resolución de imagen a 7cm x 20.5cm. Por favor, espere...</p></div>',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    
                    fetch('solapines.php?ajax=1&id=' + empleadoId + '&t=' + Date.now())
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.url) {
                                // Forzar redimensión exacta mediante Canvas del navegador
                                const img = new Image();
                                img.onload = function() {
                                    Swal.close();
                                    
                                    const canvas = document.createElement('canvas');
                                    canvas.width = 827;   // 7cm a resolución de pantalla estándar (300 DPI)
                                    canvas.height = 2421;  // 20.5cm a resolución de pantalla estándar (300 DPI)
                                    const ctx = canvas.getContext('2d');
                                    
                                    // Dibujar la imagen adaptándose exactamente a la proporción métrica
                                    ctx.drawImage(img, 0, 0, 827, 2421);
                                    
                                    // Disparar la descarga del archivo procesado
                                    const link = document.createElement('a');
                                    link.download = obtenerNombreArchivoSolapin(empleadoId);
                                    link.href = canvas.toDataURL('image/png');
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                    
                                    Swal.fire({
                                        title: '<strong><i class="fas fa-check-circle text-success me-2"></i> Exportado</strong>',
                                        html: '<div class="text-center"><p class="mb-0">El archivo PNG se ha guardado con las dimensiones de 7cm x 20.5cm.</p></div>',
                                        icon: 'success',
                                        timer: 1800,
                                        showConfirmButton: false,
                                        background: '#1a1a2e',
                                        color: '#ffffff'
                                    });
                                };
                                img.onerror = function() {
                                    Swal.close();
                                    Swal.fire({
                                        title: '<strong><i class="fas fa-exclamation-triangle text-danger me-2"></i> Error de Procesamiento</strong>',
                                        html: '<div class="text-center"><p class="mb-0">No se pudo cargar la imagen para redimensionarla.</p></div>',
                                        icon: 'error',
                                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                                        background: '#1a1a2e',
                                        color: '#ffffff'
                                    });
                                };
                                img.src = data.url;
								} else {
									Swal.close();
									window.mostrarErrorPlantilla(data.message);
								}
                        })
                        .catch(error => {
                            Swal.close();
                            Swal.fire({
                                title: '<strong><i class="fas fa-exclamation-triangle text-danger me-2"></i> Error de Red</strong>',
                                html: `<div class="text-center"><p>${error}</p></div>`,
                                icon: 'error',
                                confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                        });
                };
            }

            // Botón Exportar Solapín a Word
            const btnExportarWord = document.getElementById('btnExportarWordSolapinModal');
            if (btnExportarWord) {
                btnExportarWord.onclick = () => {
                    Swal.close();
                    Swal.fire({
                        title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Generando Word</strong>',
                        html: '<div class="text-center"><p class="mb-0">Preparando documento compatible con Microsoft Word. Por favor espere...</p></div>',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });

                    fetch('solapines.php?ajax=1&id=' + empleadoId + '&t=' + Date.now())
                        .then(response => response.json())
                        .then(data => {
                            Swal.close();
                            if (data.success && data.url) {
                                window.exportarSolapinAWord(empleadoId, nombre, data.url);
							} else {
								Swal.close();
								window.mostrarErrorPlantilla(data.message);
							}
                        })
                        .catch(error => {
                            Swal.close();
                            Swal.fire({
                                title: '<strong><i class="fas fa-exclamation-triangle text-danger me-2"></i> Error de Conexión</strong>',
                                html: `<div class="text-center"><p>${error}</p></div>`,
                                icon: 'error',
                                confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                        });
                };
            }
            
            // Botón Imprimir Solapín
            const btnImprimir = document.getElementById('btnImprimirSolapinModal');
            if (btnImprimir) {
                btnImprimir.onclick = () => {
                    Swal.close();
                    
                    Swal.fire({
                        title: '<strong><i class="fas fa-print text-primary me-2"></i> Preparando Impresión</strong>',
                        html: '<div class="text-center"><p class="mb-0">Configurando el lienzo de impresión. Por favor, espere...</p></div>',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    
                    fetch('solapines.php?ajax=1&id=' + empleadoId + '&t=' + Date.now())
                        .then(response => response.json())
                        .then(data => {
                            Swal.close();
                            if (data.success && data.url) {
                                const ventana = window.open('', '_blank');
                                ventana.document.write(`
                                    <!DOCTYPE html>
                                    <html>
                                    <head>
                                        <meta charset="UTF-8">
                                        <title>Imprimir Solapín Oficial</title>
                                        <style>
                                            @page {
                                                size: letter portrait;
                                                margin: 0;
                                            }
                                            html, body {
                                                margin: 0;
                                                padding: 0;
                                                background-color: #ffffff;
                                            }
                                            .solapin-print-box {
                                                width: 7cm !important;
                                                height: 20.5cm !important;
                                                margin: 2.54cm 0 0 2.54cm;
                                                box-sizing: border-box;
                                                display: block;
                                                page-break-inside: avoid;
                                            }
                                            .solapin-print-box img {
                                                width: 100%;
                                                height: 100%;
                                                display: block;
                                                object-fit: fill;
                                            }
                                            @media print {
                                                html, body {
                                                    background: none;
                                                }
                                                .solapin-print-box {
                                                    width: 7cm !important;
                                                    height: 20.5cm !important;
                                                    margin: 2.54cm 0 0 2.54cm;
                                                }
                                            }
                                        </style>
                                    </head>
                                    <body>
                                        <div class="solapin-print-box">
                                            <img src="${data.url}" onload="window.print(); window.close();">
                                        </div>
                                    </body>
                                    </html>
                                `);
                                ventana.document.close();
                            } else {
                                Swal.fire({
                                    title: '<strong><i class="fas fa-exclamation-triangle text-danger me-2"></i> Error de Plantilla</strong>',
                                    html: `<div class="text-center"><p class="text-danger mt-2"><strong>${data.message || 'No se pudo generar el solapín'}</strong></p></div>`,
                                    icon: 'error',
                                    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                                    background: '#1a1a2e',
                                    color: '#ffffff'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.close();
                            Swal.fire({
                                title: '<strong><i class="fas fa-wifi text-danger me-2"></i> Error de Conexión</strong>',
                                html: `<div class="text-center"><p class="text-warning mt-2"><small>${error}</small></p></div>`,
                                icon: 'error',
                                confirmButtonText: '<i class="fas fa-redo me-2"></i> Reintentar',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                        });
                };
            }
        }
    });
}

function obtenerFechaNacimientoYSexoDesdeCI(ci) {
    ci = ci.replace(/[\s-]/g, '');
    if (!/^\d{11}$/.test(ci)) return { fecha: '--', sexo: '--' };
    
    const año = ci.substr(0, 2);
    const mes = ci.substr(2, 2);
    const dia = ci.substr(4, 2);
    const digitoGenero = parseInt(ci.charAt(9));
    const sexo = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
    const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    const añoCompleto = parseInt(año) < 30 ? `20${año}` : `19${año}`;
    
    return { fecha: `${dia}/${mes}/${añoCompleto}`, sexo: `${iconoGenero} ${sexo}` };
}
// Actualizar el solapín con los datos del empleado
function actualizarSolapin() {
    // Obtener valores del formulario
    const nombres = document.getElementById('nombres')?.value || '';
    const primerApellido = document.getElementById('primer_apellido')?.value || '';
    const segundoApellido = document.getElementById('segundo_apellido')?.value || '';
    const ci = document.getElementById('ci')?.value || '';
    const cargo = document.getElementById('cargo')?.value || '';
    const empleadoId = document.getElementById('empleadoId')?.value || '';
    
    // Nombre completo
    const nombreCompleto = [nombres, primerApellido, segundoApellido].filter(n => n.trim()).join(' ');
    const solapinNombreSpan = document.getElementById('solapinNombreSpan');
    if (solapinNombreSpan) {
        solapinNombreSpan.textContent = nombreCompleto.trim() || 'Nuevo Empleado';
    }
    
    // Carnet de Identidad
    const solapinCISpan = document.getElementById('solapinCISpan');
    if (solapinCISpan) {
        const ciLimpio = ci.replace(/\D/g, '');
        if (ciLimpio.length === 11) {
            const ciFormateado = `${ciLimpio.substring(0,2)} ${ciLimpio.substring(2,4)} ${ciLimpio.substring(4,6)} ${ciLimpio.substring(6,11)}`;
            solapinCISpan.textContent = `CI: ${ciFormateado}`;
        } else if (ciLimpio.length > 0) {
            solapinCISpan.textContent = `CI: ${ciLimpio}`;
        } else {
            solapinCISpan.textContent = 'CI: --';
        }
    }
    
    // ============================================
    // NUEVO: ID DEL TRABAJADOR EN EL SOLAPÍN
    // ============================================
    const solapinIDSpan = document.getElementById('solapinIDSpan');
    if (solapinIDSpan) {
        if (empleadoId && empleadoId !== '') {
            solapinIDSpan.textContent = `ID: ${empleadoId}`;
            // Mostrar el contenedor del ID
            const solapinID = document.getElementById('solapinID');
            if (solapinID) solapinID.style.display = 'block';
        } else {
            solapinIDSpan.textContent = 'ID: --';
            const solapinID = document.getElementById('solapinID');
            if (solapinID) solapinID.style.display = 'block';
        }
    }
    
    // Cargo / Técnica
    const selectCargo = document.getElementById('cargo_id');
    const selectedOption = selectCargo.options[selectCargo.selectedIndex];
    const cargoTexto = selectedOption && selectCargo.value ? selectedOption.text : '';

    const solapinCargoSpan = document.getElementById('solapinCargoSpan');
    if (solapinCargoSpan) {
        solapinCargoSpan.textContent = cargoTexto ? `Cargo: ${cargoTexto}` : 'Cargo: --';
    }
}
// Actualizar la foto del solapín
function actualizarSolapinFoto() {
    const imagePreview = document.getElementById('imagePreview');
    const solapinFotoImg = document.getElementById('solapinFotoImg');
    const logoTnBase64 = '<?php echo $logo_base64; ?>';
    
    if (solapinFotoImg) {
        if (imagePreview && imagePreview.style.display === 'block' && imagePreview.src && imagePreview.src !== '') {
            solapinFotoImg.src = imagePreview.src;
        } else {
            solapinFotoImg.src = logoTnBase64;
        }
        // Asegurar que la foto del solapín sea circular y tenga el estilo correcto
        solapinFotoImg.style.objectFit = 'cover';
        solapinFotoImg.style.borderRadius = '50%';
        solapinFotoImg.style.width = '60px';
        solapinFotoImg.style.height = '60px';
        solapinFotoImg.style.border = '2px solid #60a5fa';
    }
}
// Variables para el selector de empleados
let empleadosListaSelect = [];

// Inicializar el selector de empleados
function inicializarSelectorEmpleados() {
    const select = document.getElementById('navEmpleadoSelect');
    if (!select) return;
    
    // Limpiar opciones existentes (excepto la primera)
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Construir lista de empleados para el select
    empleadosListaSelect = empleadosData.map(emp => ({
        id: emp.id,
        nombre: emp.nombre_completo,
        codigo: emp.codigo,
        ci: emp.ci
    }));
    
    // Ordenar por nombre
    empleadosListaSelect.sort((a, b) => a.nombre.localeCompare(b.nombre));
    
    // Agregar opciones al select
    empleadosListaSelect.forEach(emp => {
        const option = document.createElement('option');
        option.value = emp.id;
        // Formato: "Nombre completo (Expediente - CI)"
        option.textContent = `${emp.nombre} (${emp.codigo} - ${emp.ci})`;
        select.appendChild(option);
    });
}

function irAEmpleadoPorSelect(empleadoId) {
    if (!empleadoId) return;
    
    const index = empleadosData.findIndex(emp => emp.id == empleadoId);
    
    if (index !== -1 && index !== currentIndex) {
        if (formDirty) {
            // Guardar como objeto con type 'select' y el id
            pendingNavigation = { type: 'select', id: empleadoId };
            
            Swal.fire(Object.assign({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Cambios sin guardar',
                text: 'Hay cambios sin guardar. ¿Desea guardarlos antes de cambiar de empleado?',
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i> Guardar',
                denyButtonText: '<i class="fas fa-trash-alt me-2"></i> Descartar',
                cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar'
            }, swalDark))
            .then(r => {
                if (r.isConfirmed) {
                    document.getElementById('empleadoForm').dispatchEvent(new Event('submit'));
                    // pendingNavigation ya está guardado como objeto
                } else if (r.isDenied) {
                    resetFormDirty();
                    currentIndex = index;
                    cargarEmpleadoEnFormulario(empleadosData[index]);
                    actualizarControlesNavegacion();
                    if (typeof actualizarSelectorEmpleado === 'function') {
                        actualizarSelectorEmpleado();
                    }
                    pendingNavigation = null;
                }
            });
        } else {
            currentIndex = index;
            cargarEmpleadoEnFormulario(empleadosData[currentIndex]);
            actualizarControlesNavegacion();
            if (typeof actualizarSelectorEmpleado === 'function') {
                actualizarSelectorEmpleado();
            }
        }
    } else if (index === currentIndex) {
        if (typeof actualizarSelectorEmpleado === 'function') {
            actualizarSelectorEmpleado();
        }
    }
    
    // Resetear el select después de la navegación
    setTimeout(() => {
        const select = document.getElementById('navEmpleadoSelect');
        if (select) select.value = '';
    }, 100);
}


// Actualizar la opción seleccionada en el selector según el empleado actual
function actualizarSelectorEmpleado() {
    const select = document.getElementById('navEmpleadoSelect');
    if (!select) return;
    
    if (currentIndex >= 0 && empleadosData[currentIndex]) {
        // Buscar la opción que coincide con el ID actual
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value == empleadosData[currentIndex].id) {
                select.selectedIndex = i;
                break;
            }
        }
    } else {
        select.selectedIndex = 0;
    }
}
// Función para ver solapín (con prevención de caché)
function verSolapin(empleadoId) {
    // Agregamos Date.now() para forzar al navegador a solicitar la imagen real actual
    window.open('solapines.php?id=' + empleadoId + '&t=' + Date.now(), '_blank');
}

// Función para generar y descargar solapín
function generarSolapin(empleadoId) {
    Swal.fire({
        title: '<i class="fas fa-id-card me-2"></i> Generar Solapín',
        text: '¿Desea generar el solapín para este empleado?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-download me-2"></i> Generar y Descargar',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando solapín...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#1a1a2e',
                color: '#ffffff'
            });
            
            // Enviamos un timestamp en la petición ajax
            fetch('solapines.php?ajax=1&id=' + empleadoId + '&t=' + Date.now())
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success && data.url) {
                        // Descargar el archivo físicamente
                        const link = document.createElement('a');
                        link.download = obtenerNombreArchivoSolapin(empleadoId);
                        link.href = data.url;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        Swal.fire({
                            title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Error',
                            text: data.message || 'No se pudo generar el solapín',
                            icon: 'error',
                            background: '#1a1a2e',
                            color: '#ffffff'
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
					window.mostrarErrorPlantilla(data.message);
                });
        }
    });
}

// Función para descargar solapín directamente (con prevención de caché)
function descargarSolapin(empleadoId) {
    window.location.href = 'solapines.php?descargar=1&id=' + empleadoId + '&t=' + Date.now();
}
// Función para exportar solapín a Word con proporciones métricas precisas (7cm x 20.5cm)
window.exportarSolapinAWord = function(empleadoId, empleadoNombre, fotoUrl) {
    let absoluteUrl = fotoUrl;
    
    // Si no es una imagen en Base64, convertir la ruta relativa del servidor a una URL absoluta
    if (fotoUrl && !fotoUrl.startsWith('data:') && !fotoUrl.startsWith('http://') && !fotoUrl.startsWith('https://')) {
        const parser = document.createElement('a');
        parser.href = fotoUrl;
        absoluteUrl = parser.href; // Convierte p.ej. "../assets/..." a "http://localhost/NOMINAS/assets/..."
    }

    let cleanLogo = absoluteUrl ? absoluteUrl.replace(/(\r\n|\n|\r)/gm, "") : "";
    let nombreArchivo = `Solapin_${empleadoNombre.replace(/[^a-zA-Z0-9]/g, '_')}.doc`;
    
    let html = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="utf-8">
        <title>Solapín Oficial - ${escapeHtml(empleadoNombre)}</title>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>100</w:Zoom>
                <w:DoNotOptimizeForBrowser/>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page Section1 {
                size: 8.5in 11.0in; /* Portrait */
                margin: 1.0in 1.0in 1.0in 1.0in; /* Margen estándar de Word de 1 pulgada */
            }
            div.Section1 { page: Section1; }
            body { font-family: Arial, sans-serif; text-align: left; }
            /* Definición exacta del contenedor del Solapín en 7cm x 20.5cm */
            .solapin-box {
                width: 7cm !important;
                height: 20.5cm !important;
                border: 0.5pt solid #cccccc;
            }
        </style>
    </head>
    <body>
        <div class="Section1">
            <h2 style="font-size:14pt; font-weight:bold; color:#004B87; margin-bottom:5px;">Solapín Oficial de Identificación</h2>
            <p style="font-size:10pt; color:#333333; margin-bottom:15px;">Empleado: <strong>${escapeHtml(empleadoNombre)}</strong></p>
            <!-- 265 x 775 píxeles corresponden exactamente a 7cm x 20.5cm a una resolución estándar de 300 DPI -->
            <img src="${cleanLogo}" width="265" height="775" class="solapin-box">
        </div>
    </body>
    </html>`;

    let blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nombreArchivo;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);

    Swal.fire({
        title: '<strong><i class="fas fa-check-circle text-success me-2"></i> ¡Completado!</strong>',
        html: '<div class="text-center"><p class="mb-0">El documento de Word se ha generado y descargado correctamente con los recursos enlazados.</p></div>',
        icon: 'success',
        timer: 1800,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#ffffff'
    });
};
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
// =========================================================================
// FUNCIONES AUXILIARES GLOBALES DE EXPORTACIÓN, VISUALIZACIÓN Y ERRORES
// =========================================================================

// 1. Función para mostrar el error de plantilla con estilo Windows 11 Dark (Consolidada)
window.mostrarErrorPlantilla = function(mensajeServidor) {
    let errorMsg = mensajeServidor || 'Error al generar el archivo del solapín.';
    
    // Separar dinámicamente el texto del servidor para resaltar la ruta en una etiqueta de código
    let partes = errorMsg.split('en:');
    let mainText = partes[0].trim();
    let pathText = partes[1] ? `assets/imagenes/${partes[1].split('assets/imagenes/')[1] || partes[1]}`.trim() : '';

    Swal.fire({
        title: '<strong><i class="fas fa-exclamation-triangle text-danger me-2"></i> Error de Recursos</strong>',
        html: `
            <div class="text-center px-2">
                <p class="mb-3" style="font-size: 1rem; font-weight: 600; color: #ffffff;">
                    No se pudo procesar el solapín
                </p>
                <div class="p-3 my-3 rounded text-start" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px;">
                    <div class="d-flex align-items-start gap-3">
                        <div style="flex-shrink: 0; margin-top: 3px;">
                            <i class="fas fa-file-invoice-dollar fa-2x text-danger"></i>
                        </div>
                        <div>
                            <strong class="text-light d-block mb-1" style="font-size: 0.85rem;">Plantilla no localizada</strong>
                            <span style="font-size: 0.78rem; color: rgba(255, 255, 255, 0.7); line-height: 1.45; display: block;">
                                ${mainText}.<br><br>
                                ${pathText ? `Ruta requerida en el servidor:<br><code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; color: #ff8888; font-family: monospace; display: inline-block; margin-top: 4px; word-break: break-all;">${pathText}</code>` : ''}
                            </span>
                        </div>
                    </div>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.72rem;">
                    <i class="fas fa-info-circle me-1 text-info"></i> Verifique que la imagen exista y que los permisos de lectura de la carpeta sean correctos.
                </p>
            </div>
        `,
        icon: 'error',
        confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
        confirmButtonColor: '#dc3545',
        background: '#1a1a2e',
        color: '#ffffff',
        customClass: {
            popup: 'foto-modal-win'
        }
    });
};

// 2. Función para visualizar el solapín de forma segura (con verificación previa de plantilla)
window.verSolapin = function(empleadoId) {
    Swal.fire({
        title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Cargando Solapín</strong>',
        html: '<div class="text-center"><p class="mb-0">Verificando recursos en el servidor. Por favor, espere...</p></div>',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: '#ffffff'
    });

    window.restablecerZoomSolapin();

    const urlSolapin = 'solapines.php?id=' + empleadoId + '&t=' + Date.now();

    fetch('solapines.php?ajax=1&id=' + empleadoId + '&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire({
                    title: '<strong><i class="fas fa-id-card text-info me-2"></i> Vista Previa del Solapín</strong>',
                    html: `
                        <div class="text-center">
                            <!-- Herramientas de Zoom -->
                            <div class="mb-3 d-flex justify-content-center gap-2">
                                <button type="button" class="btn-win btn-win-sm py-1" onclick="window.cambiarZoomSolapin(0.3)" title="Acercar">
                                    <i class="fas fa-search-plus me-1"></i> Acercar
                                </button>
                                <button type="button" class="btn-win btn-win-sm py-1" onclick="window.cambiarZoomSolapin(-0.3)" title="Alejar">
                                    <i class="fas fa-search-minus me-1"></i> Alejar
                                </button>
                                <button type="button" class="btn-win btn-win-sm py-1" onclick="window.restablecerZoomSolapin()" title="100%">
                                    <i class="fas fa-sync-alt me-1"></i> Resetear
                                </button>
                            </div>

                            <!-- Contenedor Vertical Corregido (Soporte perfecto para arrastre en 2 ejes) -->
                            <div id="solapinScrollContainer" style="display: inline-block; width: 320px; height: 560px; background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.6); overflow: hidden; cursor: grab; user-select: none; position: relative;">
                                <div style="display: inline-block; min-width: 100%; min-height: 100%; text-align: center; white-space: nowrap; vertical-align: middle;">
                                    <!-- Elemento fantasma para forzar el alineado vertical perfecto -->
                                    <span style="display: inline-block; height: 100%; vertical-align: middle;"></span>
                                    <!-- 1. Altura de la imagen a 1000px (200% de la altura base de 500px) -->
									<img id="previewSolapinImg" src="${urlSolapin}" draggable="false" style="display: inline-block; vertical-align: middle; width: auto; height: 1000px; border-radius: 6px; pointer-events: none; transition: height 0.12s ease-out;">
                                </div>
                            </div>

                            <!-- Porcentaje del zoom -->
							<!-- 2. Texto de escala inicial por defecto a 200% -->
							<div style="margin-top: 15px; font-size: 0.75rem; color: rgba(255, 255, 255, 0.65);">
								<i class="fas fa-info-circle me-1"></i> Escala: <strong id="zoomLevelIndicator" style="color: #60a5fa;">200%</strong>
							</div>
                        </div>
                    `,
                    showCloseButton: true,
                    showConfirmButton: true,
                    confirmButtonText: '<i class="fas fa-times me-2"></i> Cerrar',
                    confirmButtonColor: '#dc3545',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-print me-2"></i> Imprimir',
                    cancelButtonColor: '#0078d4',
                    reverseButtons: true,
                    background: '#1a1a2e',
                    color: '#ffffff',
                    width: '480px',
                    customClass: {
                        popup: 'foto-modal-win'
                    },
                    didOpen: () => {
                        const contenedor = document.getElementById('solapinScrollContainer');
                        if (!contenedor) return;

                        // 1. Sistema de Zoom con Rueda del Ratón (Mouse Scroll Wheel)
                        contenedor.addEventListener('wheel', (e) => {
                            e.preventDefault();
                            
                            const velocidadZoom = 0.25;
                            const direccion = e.deltaY < 0 ? velocidadZoom : -velocidadZoom;
                            
                            window.cambiarZoomSolapin(direccion);
                        }, { passive: false });

                        // 2. Sistema de Arrastre Manual Bidireccional (X / Y)
                        let isPressed = false;
                        let startX, startY;
                        let scrollLeft, scrollTop;

                        contenedor.addEventListener('mousedown', (e) => {
                            isPressed = true;
                            contenedor.style.cursor = 'grabbing';
                            
                            // Guardar posiciones relativas iniciales
                            startX = e.pageX - contenedor.offsetLeft;
                            startY = e.pageY - contenedor.offsetTop;
                            scrollLeft = contenedor.scrollLeft;
                            scrollTop = contenedor.scrollTop;
                        });

                        contenedor.addEventListener('mouseleave', () => {
                            isPressed = false;
                            contenedor.style.cursor = 'grab';
                        });

                        contenedor.addEventListener('mouseup', () => {
                            isPressed = false;
                            contenedor.style.cursor = 'grab';
                        });

                        contenedor.addEventListener('mousemove', (e) => {
                            if (!isPressed) return;
                            e.preventDefault();
                            
                            const x = e.pageX - contenedor.offsetLeft;
                            const y = e.pageY - contenedor.offsetTop;
                            
                            // Multiplicador de arrastre para respuesta rápida
                            const walkX = (x - startX) * 1.6;
                            const walkY = (y - startY) * 1.6;
                            
                            contenedor.scrollLeft = scrollLeft - walkX;
                            contenedor.scrollTop = scrollTop - walkY;
                        });
                    }
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        ejecutarImpresionDirecta(data.url);
                    }
                });
            } else {
                window.mostrarErrorPlantilla(data.message);
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                title: '<strong><i class="fas fa-wifi text-danger me-2"></i> Error de Red</strong>',
                html: `<div class="text-center"><p>${error}</p></div>`,
                icon: 'error',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                background: '#1a1a2e',
                color: '#ffffff'
            });
        });
};


// 2.5 Envía a imprimir el recurso solapín de forma limpia y transparente
function ejecutarImpresionDirecta(urlImagen) {
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Imprimir Solapín Oficial</title>
            <style>
                @page { size: letter portrait; margin: 0; }
                html, body { margin: 0; padding: 0; background-color: #ffffff; }
                .solapin-print-box {
                    width: 7cm !important;
                    height: 20.5cm !important;
                    margin: 2.54cm 0 0 2.54cm;
                    box-sizing: border-box;
                    display: block;
                    page-break-inside: avoid;
                }
                .solapin-print-box img { width: 100%; height: 100%; display: block; object-fit: fill; }
            </style>
        </head>
        <body>
            <div class="solapin-print-box">
                <img src="${urlImagen}" onload="window.print(); window.close();">
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
}

// 3. Función para exportar solapín a Word con proporciones métricas calibradas (7cm x 20.5cm)
window.exportarSolapinAWord = function(empleadoId, empleadoNombre, fotoUrl) {
    const img = new Image();
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.width = 827;   // Ancho exacto a 300 DPI (7cm)
        canvas.height = 2421;  // Alto exacto a 300 DPI (20.5cm)
        const ctx = canvas.getContext('2d');
        
        ctx.drawImage(img, 0, 0, 827, 2421);
        
        // Obtener el PNG procesado en base64 limpio
        const processedBase64 = canvas.toDataURL('image/png');
        let cleanLogo = processedBase64.replace(/(\r\n|\n|\r)/gm, "");
        let nombreArchivo = `Solapin_${empleadoNombre.replace(/[^a-zA-Z0-9]/g, '_')}.doc`;
        
        let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Solapín Oficial - ${escapeHtml(empleadoNombre)}</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>100</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
            </xml>
            <![endif]-->
            <style>
                @page Section1 {
                    size: 8.5in 11.0in; /* Portrait */
                    margin: 1.0in 1.0in 1.0in 1.0in; /* Margen estándar Word (1 pulgada) */
                }
                div.Section1 { page: Section1; }
                body { font-family: Arial, sans-serif; text-align: left; }
                /* Definición del contenedor del Solapín en 7cm x 20.5cm */
                .solapin-box {
                    width: 7.0cm !important;
                    height: 20.5cm !important;
                    border: 0.5pt solid #cccccc;
                }
            </style>
        </head>
        <body>
            <div class="Section1">
                <h2 style="font-size:14pt; font-weight:bold; color:#004B87; margin-bottom:5px;">Solapín Oficial de Identificación</h2>
                <p style="font-size:10pt; color:#333333; margin-bottom:15px;">Empleado: <strong>${escapeHtml(empleadoNombre)}</strong></p>
                <!-- Atributos HTML y estilo inline combinados para obligar a Word a respetar la escala métrica -->
                <img src="${cleanLogo}" width="265" height="775" style="width: 7.0cm; height: 20.5cm; display: block;" class="solapin-box">
            </div>
        </body>
        </html>`;

        let blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);

        Swal.fire({
            title: '<strong><i class="fas fa-check-circle text-success me-2"></i> ¡Completado!</strong>',
            html: '<div class="text-center"><p class="mb-0">El documento de Word se ha generado y descargado correctamente respetando las dimensiones de 7cm x 20.5cm.</p></div>',
            icon: 'success',
            timer: 1800,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    };
    img.src = fotoUrl;
};
// =========================================================================
// ENLACE DE EVENTOS PARA LOS BOTONES DEL SOLAPÍN DENTRO DEL MODAL
// =========================================================================
$(document).on('click', '#btnModalVerSolapin', function() {
    const id = document.getElementById('empleadoId').value;
    if (id) window.verSolapin(id);
});

$(document).on('click', '#btnModalExportarSolapin', function() {
    const id = document.getElementById('empleadoId').value;
    if (id) {
        Swal.fire({
            title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Generando Solapín</strong>',
            html: '<div class="text-center"><p class="mb-0">Ajustando resolución de imagen a 7cm x 20.5cm. Por favor, espere...</p></div>',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: '#1a1a2e',
            color: '#ffffff'
        });
        
        fetch('solapines.php?ajax=1&id=' + id + '&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.url) {
                    const img = new Image();
                    img.onload = function() {
                        Swal.close();
                        const canvas = document.createElement('canvas');
                        canvas.width = 827;
                        canvas.height = 2421;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, 827, 2421);
                        
                        const link = document.createElement('a');
                        link.download = obtenerNombreArchivoSolapin(id);
                        link.href = canvas.toDataURL('image/png');
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    };
                    img.src = data.url;
                } else {
                    Swal.close();
                    window.mostrarErrorPlantilla(data.message);
                }
            });
    }
});

$(document).on('click', '#btnModalExportarWordSolapin', function() {
    const id = document.getElementById('empleadoId').value;
    const nombre = document.getElementById('modalEmpleadoNombre').textContent;
    if (id) {
        Swal.fire({
            title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Generando Word</strong>',
            html: '<div class="text-center"><p class="mb-0">Preparando documento compatible con Microsoft Word. Por favor espere...</p></div>',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: '#1a1a2e',
            color: '#ffffff'
        });

        fetch('solapines.php?ajax=1&id=' + id + '&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success && data.url) {
                    window.exportarSolapinAWord(id, nombre, data.url);
                } else {
                    window.mostrarErrorPlantilla(data.message);
                }
            });
    }
});

$(document).on('click', '#btnModalImprimirSolapin', function() {
    const id = document.getElementById('empleadoId').value;
    if (id) {
        Swal.fire({
            title: '<strong><i class="fas fa-print text-primary me-2"></i> Preparando Impresión</strong>',
            html: '<div class="text-center"><p class="mb-0">Configurando el lienzo de impresión. Por favor, espere...</p></div>',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: '#1a1a2e',
            color: '#ffffff'
        });
        
        fetch('solapines.php?ajax=1&id=' + id + '&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success && data.url) {
                    const ventana = window.open('', '_blank');
                    ventana.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <title>Imprimir Solapín Oficial</title>
                            <style>
                                @page { size: letter portrait; margin: 0; }
                                html, body { margin: 0; padding: 0; background-color: #ffffff; }
                                .solapin-print-box {
                                    width: 7cm !important;
                                    height: 20.5cm !important;
                                    margin: 2.54cm 0 0 2.54cm;
                                    box-sizing: border-box;
                                    display: block;
                                    page-break-inside: avoid;
                                }
                                .solapin-print-box img { width: 100%; height: 100%; display: block; object-fit: fill; }
                            </style>
                        </head>
                        <body>
                            <div class="solapin-print-box">
                                <img src="${data.url}" onload="window.print(); window.close();">
                            </div>
                        </body>
                        </html>
                    `);
                    ventana.document.close();
                } else {
                    window.mostrarErrorPlantilla(data.message);
                }
            });
    }
});
// Función para dar formato DD/MM/AAAA a las fechas en la tabla
function formatearFechaTabla(dateStr) {
    if (!dateStr) return '-';
    let parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateStr;
}

// Actualiza los valores de la fila en el DOM y redibuja la tabla sin cerrarla
function actualizarFilaTablaHTML(emp) {
    let row = $(`#empleadosTable tbody tr[data-id="${emp.id}"]`);
    if (row.length) {
        // Actualizar celdas del DOM correspondientes a las columnas de la tabla
        row.find('td:eq(2)').text(emp.codigo || '');
        row.find('td:eq(3)').text(emp.ci || '');
        row.find('td:eq(4) strong').text(emp.nombre_completo || '');
        row.find('td:eq(5)').text(emp.nombre_area || '-');
        row.find('td:eq(6)').text(emp.centro_costo_codigo ? emp.centro_costo_codigo + ' - ' + emp.centro_costo_nombre : '-');
        row.find('td:eq(7)').html(`${emp.categoria_nombre || ''} (<small>${(parseFloat(emp.factor_incidencia) || 1) * 100}%</small>)`);
        row.find('td:eq(8)').text('Escala ' + numeroRomano(parseInt(emp.escala_numero) || 0));
        row.find('td:eq(9)').text(formatMoney(parseFloat(emp.salario_mensual) || 0));
        row.find('td:eq(10)').text(formatMoney(parseFloat(emp.salario_hora_ordinaria) || 0));
        row.find('td:eq(11)').text(emp.cuentabanc || '-');
        row.find('td:eq(12)').text(formatearFechaTabla(emp.fecha_alta));
        row.find('td:eq(13)').text(formatearFechaTabla(emp.fecha_baja));
        
        let vacDias = parseFloat(emp.vacaciones_acumuladas) || 0;
        let vacDiasCell = row.find('td:eq(14)');
        vacDiasCell.text(vacDias.toFixed(2));
        
        if (vacDias > 20) {
            row.addClass('vacaciones-excedidas');
            vacDiasCell.addClass('text-danger').removeClass('text-info');
        } else {
            row.removeClass('vacaciones-excedidas');
            vacDiasCell.addClass('text-info').removeClass('text-danger');
        }
        
        row.find('td:eq(15)').text(formatMoney(parseFloat(emp.valor_vacaciones) || 0));
        
        let vacPagar = emp.no_acumular_vacaciones == 1 ? formatMoney(parseFloat(emp.valor_vacaciones) || 0) : '-';
        row.find('td:eq(16)').text(vacPagar);
        row.find('td:eq(17)').text(formatMoney(parseFloat(emp.valor_por_dia) || 0));
        
        let hoyStr = new Date().toISOString().split('T')[0];
        let estadoBadge = emp.activo == 1 && (!emp.fecha_baja || emp.fecha_baja > hoyStr) ? 
            '<span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #4ade80;"><i class="fas fa-check-circle me-1"></i>Activo</span>' :
            '<span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171;"><i class="fas fa-times-circle me-1"></i>Inactivo</span>';
        row.find('td:eq(18)').html(estadoBadge);
        row.find('td:eq(19)').text(emp.tipo_contrato || 'Indeterminado');
        
        // Sincronizar los cambios con la instancia de DataTables sin alterar el paginado o filtro actual
        let table = $('#empleadosTable').DataTable();
        table.row(row).invalidate().draw(false);
    }
}
// Sincroniza la escala y categoría de forma inmediata al seleccionar un cargo
window.vincularCargoPlantilla = function(cargoId) {
    if (!cargoId) return;
    
    // Obtener la opción seleccionada y sus metadatos
    const selectCargo = document.getElementById('cargo_id');
    const selectedOption = selectCargo.options[selectCargo.selectedIndex];
    
    const categoriaAsociadaId = selectedOption.getAttribute('data-categoria');
    const escalaAsociadaId = selectedOption.getAttribute('data-escala');
    
    // 1. Sincronizar Categoría Ocupacional
    const selectCategoria = document.getElementById('categoria_id');
    if (selectCategoria && categoriaAsociadaId) {
        selectCategoria.value = categoriaAsociadaId;
    }
    
    // 2. Sincronizar Escala Salarial
    const selectEscala = document.getElementById('escala_id');
    if (selectEscala && escalaAsociadaId) {
        selectEscala.value = escalaAsociadaId;
        // Lanzar de forma programada los métodos de actualización del salario básico y vacaciones
        actualizarSalarioDesdeEscala();
    }
    
    markFormDirty();
};
// Manejador para compilar e imprimir todos los solapines en lote (3 por página Carta)
$('#menuImprimirTodosSolapines').on('click', function(e) {
    e.preventDefault();
    
    // Filtrar únicamente a los trabajadores activos
    const hoyStr = new Date().toISOString().split('T')[0];
    const activos = empleadosData.filter(emp => {
        return emp.activo == 1 && (!emp.fecha_baja || emp.fecha_baja > hoyStr);
    });
    
    if (activos.length === 0) {
        Swal.fire({
            title: '<strong>Atención</strong>',
            text: 'No se encontraron trabajadores activos en la base de datos para imprimir.',
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    Swal.fire({
        title: '<strong><i class="fas fa-print text-danger me-2"></i> Imprimir Solapines en Lote</strong>',
        html: `
            <div class="text-center">
                <p>Se enviarán a la cola de impresión las credenciales de <strong>${activos.length}</strong> trabajadores activos.</p>
                <p class="text-muted" style="font-size: 0.78rem;">
                    <i class="fas fa-info-circle me-1 text-info"></i> Formato calibrado: 3 solapines por página (Carta Vertical) con márgenes de seguridad de 2.9mm.
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-print me-2"></i> Preparar Páginas',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<strong><i class="fas fa-spinner fa-pulse text-danger me-2"></i> Renderizando Solapines</strong>',
                html: '<div class="text-center"><p class="mb-0">Construyendo el lienzo y cargando imágenes en memoria. Por favor, espere...</p></div>',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#1a1a2e',
                color: '#ffffff'
            });

            // Abrir una ventana limpia dedicada exclusivamente a la impresión
            const ventanaImpresion = window.open('', '_blank');
            
            let htmlPaginas = '';
            const limitePorPagina = 3;
            
            // Agrupar los empleados en grupos de 3
            for (let i = 0; i < activos.length; i += limitePorPagina) {
                const grupo = activos.slice(i, i + limitePorPagina);
                
                htmlPaginas += `<div class="print-page">`;
                
                grupo.forEach(emp => {
                    // Prevenir problemas de caché agregando un timestamp
                    const urlImagen = 'solapines.php?id=' + emp.id + '&t=' + Date.now();
                    htmlPaginas += `
                        <div class="solapin-print-item">
                            <img src="${urlImagen}" alt="Solapín de ${emp.nombre_completo}">
                        </div>
                    `;
                });
                
                htmlPaginas += `</div>`;
            }

            // Escribir el documento HTML con los estilos de impresión calibrados
            ventanaImpresion.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Impresión Masiva de Solapines - TransNuBet</title>
                    <style>
                        @page {
                            size: letter portrait;
                            margin: 0;
                        }
                        html, body {
                            margin: 0;
                            padding: 0;
                            background-color: #ffffff;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        /* Cada página Carta mide exactamente 21.59cm x 27.94cm */
                        .print-page {
                            width: 21.59cm;
                            height: 27.94cm;
                            page-break-after: always;
                            box-sizing: border-box;
                            
                            /* Centrado vertical de la fila (27.94cm alto de hoja - 20.5cm de solapín) / 2 = 3.72cm */
                            padding-top: 3.72cm; 
                            
                            /* Centrado horizontal de los 3 solapines (21.59cm ancho de hoja - 21.0cm de solapines) / 2 = 0.295cm de margen lateral */
                            padding-left: 0.295cm;
                            padding-right: 0.295cm;
                            
                            display: flex;
                            flex-direction: row;
                            justify-content: flex-start;
                            align-items: flex-start;
                            gap: 0;
                            overflow: hidden;
                        }
                        .print-page:last-child {
                            page-break-after: avoid;
                        }
                        /* Dimensiones oficiales de cada solapín en el papel */
.solapin-print-item {
    width: 7.0cm !important;
    height: 20.5cm !important;
    box-sizing: border-box;
    display: block;
    /* Línea discontinua de 0.3mm de grosor para guiar el corte */
    border: 0.3mm dashed #777777; 
}
/* Evita la doble línea discontinua entre solapines contiguos */
.solapin-print-item + .solapin-print-item {
    border-left: none;
}
                        .solapin-print-item img {
                            width: 100%;
                            height: 100%;
                            display: block;
                            object-fit: fill;
                        }
                        @media print {
                            html, body {
                                background: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${htmlPaginas}
                    <script>
                        // Esperar a que carguen completamente las imágenes antes de imprimir
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 1200);
                        };
                    <\/script>
                </body>
                </html>
            `);
            
            ventanaImpresion.document.close();
            Swal.close(); // Cerrar el diálogo de espera de SweetAlert2
        }
    });
});
$('#menuExportAnexo14').on('click', function(e) {
    e.preventDefault();
    
    var ahora = new Date();
    var dia = String(ahora.getDate()).padStart(2, '0');
    var mes = String(ahora.getMonth() + 1).padStart(2, '0');
    var anio = ahora.getFullYear();
    var horas = String(ahora.getHours() % 12 || 12).padStart(2, '0');
    var minutos = String(ahora.getMinutes()).padStart(2, '0');
    var ampm = ahora.getHours() >= 12 ? 'PM' : 'AM';
    
    var fechaCliente = dia + '/' + mes + '/' + anio + '-' + horas + ':' + minutos + ampm;
    
    window.location.href = 'empleados.php?exportar_anexo14=1&fecha_cliente=' + encodeURIComponent(fechaCliente);
});

// Manejador para exportar todos los solapines en formato ZIP
$('#menuExportTodosSolapines').on('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: '<strong><i class="fas fa-file-archive text-info me-2"></i> Exportar Lote de Solapines</strong>',
        text: 'Se generará un archivo comprimido ZIP con las credenciales de todos los trabajadores activos en el sistema. ¿Desea continuar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0078d4',
        confirmButtonText: '<i class="fas fa-download me-2"></i> Generar ZIP',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<strong><i class="fas fa-spinner fa-pulse text-info me-2"></i> Generando archivo ZIP</strong>',
                html: '<div class="text-center"><p class="mb-0">Procesando plantillas e imágenes del personal activo. Por favor espere...</p></div>',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#1a1a2e',
                color: '#ffffff'
            });

            // Disparar la petición de descarga al controlador de solapines
            window.location.href = 'solapines.php?exportar_todos=1';

            // Cerrar el indicador de carga transcurridos unos segundos de forma automática
            setTimeout(() => {
                Swal.close();
            }, 5000);
        }
    });
});

// Función para obtener un nombre de archivo limpio basado en el nombre del trabajador
function obtenerNombreArchivoSolapin(empleadoId) {
    const emp = empleadosData.find(e => e.id == empleadoId);
    if (emp && emp.nombre_completo) {
        // Remover acentos, eñes y caracteres especiales para asegurar compatibilidad
        let nombreLimpio = emp.nombre_completo
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // Remueve tildes
            .replace(/[^a-zA-Z0-9\s]/g, "")  // Remueve caracteres no alfanuméricos
            .trim()
            .replace(/\s+/g, '_');          // Reemplaza espacios por guiones bajos
        return nombreLimpio + '.png';
    }
    return 'solapin_' + empleadoId + '.png';
}
// Control físico de dimensiones del solapín en el visor (Soporta hasta 500%)
const alturaBaseSolapin = 500; 
let escalaZoomSolapin = 2.0;

window.cambiarZoomSolapin = function(factor) {
    const img = document.getElementById('previewSolapinImg');
    const indicador = document.getElementById('zoomLevelIndicator');
    
    if (img && indicador) {
        escalaZoomSolapin += factor;
        
        // Ajuste de límites: Mínimo 50% (0.5) - Máximo 500% (5.0)
        if (escalaZoomSolapin < 0.5) escalaZoomSolapin = 0.5;
        if (escalaZoomSolapin > 5.0) escalaZoomSolapin = 5.0;
        
        img.style.height = (alturaBaseSolapin * escalaZoomSolapin) + 'px';
        indicador.textContent = Math.round(escalaZoomSolapin * 100) + '%';
    }
};

window.restablecerZoomSolapin = function() {
    const img = document.getElementById('previewSolapinImg');
    const indicador = document.getElementById('zoomLevelIndicator');
    
    if (img && indicador) {
        escalaZoomSolapin = 2.0;
        img.style.height = (alturaBaseSolapin * 2.0) + 'px';
        indicador.textContent = '200%';
    }
};

// ============================================
// TODOS LOS CUMPLEAÑOS (CARRUSEL + LISTA)
// ============================================
var cumpleaneros = <?php echo json_encode($cumpleaneros); ?>;
var cumpleIndex = 0;
var cumpleContainer = document.getElementById('cumpleContent');
var cumpleCounter = document.getElementById('cumpleCounter');
var listaContainer = document.getElementById('listaCumpleaneros');

function abrirEmpleado(id) {
    const emp = empleadosData.find(function(e) { return e.id == id; });
    if (emp) editEmpleado(emp);
}

function renderCumpleanero(index) {
    if (!cumpleContainer) return;
    if (!cumpleaneros || cumpleaneros.length === 0) {
        cumpleContainer.innerHTML = `
            <div class="py-5">
                <i class="fas fa-birthday-cake fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i>
                <h5 class="mb-2">No hay trabajadores con CI válido</h5>
                <p class="mb-0" style="color: rgba(255,255,255,0.5);">Asegúrate de que los CI tengan 11 dígitos</p>
            </div>
        `;
        if (cumpleCounter) cumpleCounter.textContent = '0 de 0';
        if (listaContainer) listaContainer.innerHTML = '<div class="text-center py-3" style="color: rgba(255,255,255,0.3);">Sin datos</div>';
        return;
    }

    var total = cumpleaneros.length;
    if (cumpleCounter) cumpleCounter.textContent = (index + 1) + ' de ' + total;

    var item = cumpleaneros[index];

    var fotoSrc = item.foto && item.foto.trim() !== '' ? item.foto.trim() : null;
    var fotoHtml = fotoSrc
        ? `<img src="${fotoSrc}" alt="${item.nombre}" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid rgba(255,255,255,0.15);" onerror="this.onerror=null; this.outerHTML='<i class=\\'fas fa-birthday-cake\\' style=\\'font-size: 80px; color: #fbbf24;\\'></i>';">`
        : `<i class="fas fa-birthday-cake" style="font-size: 80px; color: #fbbf24;"></i>`;

    var html = `
        <div class="d-flex flex-column align-items-center">
            <div class="mb-3">
                ${fotoHtml}
            </div>
            <h5 class="mb-1" style="cursor: pointer; color: #60a5fa;" onclick="abrirEmpleado(${item.id})">${item.nombre}</h5>
            <p class="mb-0" style="color: rgba(255,255,255,0.6);">
                <i class="fas fa-calendar-alt me-1"></i> ${item.fecha}
            </p>
            <p class="mb-2" style="color: #fbbf24; font-weight: 600;">
                <i class="fas fa-star me-1"></i> Cumple ${item.edad} año${item.edad !== 1 ? 's' : ''}
                <span class="badge bg-success ms-2">${item.dias} día${item.dias !== 1 ? 's' : ''}</span>
            </p>
            <div class="d-flex gap-3 mt-2">
                <button class="btn-win btn-win-sm" id="prevCumple" ${index === 0 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn-win btn-win-sm" id="nextCumple" ${index === total - 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    `;

    cumpleContainer.innerHTML = html;

    document.getElementById('prevCumple')?.addEventListener('click', function() {
        if (cumpleIndex > 0) {
            cumpleIndex--;
            renderCumpleanero(cumpleIndex);
            renderLista();
        }
    });
    document.getElementById('nextCumple')?.addEventListener('click', function() {
        if (cumpleIndex < cumpleaneros.length - 1) {
            cumpleIndex++;
            renderCumpleanero(cumpleIndex);
            renderLista();
        }
    });

    renderLista();
}

function renderLista() {
    if (!listaContainer) return;
    if (!cumpleaneros || cumpleaneros.length === 0) {
        listaContainer.innerHTML = '<div class="text-center py-3" style="color: rgba(255,255,255,0.3);">Sin datos</div>';
        return;
    }

    var top10 = cumpleaneros.slice(0, 10);
    var html = '';
    top10.forEach(function(emp, idx) {
        var fotoMini = emp.foto && emp.foto.trim() !== ''
            ? `<img src="${emp.foto.trim()}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 1px solid rgba(255,255,255,0.1);" onerror="this.onerror=null; this.outerHTML='<i class=\\'fas fa-birthday-cake\\' style=\\'font-size: 16px; color: #fbbf24; width: 40px; text-align: center;\\'></i>';">`
            : `<i class="fas fa-birthday-cake" style="font-size: 22px; color: #fbbf24; width: 40px; text-align: center;"></i>`;

        var activeClass = (idx === cumpleIndex) ? 'border-left border-primary' : '';
        html += `
            <div class="d-flex align-items-center gap-2 p-1 mb-1 rounded ${activeClass}" style="cursor: pointer; background: ${idx === cumpleIndex ? 'rgba(96,165,250,0.15)' : 'transparent'}; transition: background 0.2s;" onclick="irACumpleanero(${idx})">
                <div style="flex-shrink: 0;">${fotoMini}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size: 0.95rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; color: #60a5fa;" onclick="abrirEmpleado(${emp.id})">${emp.nombre}</div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); display: flex; gap: 8px;">
                        <span><i class="fas fa-calendar-alt me-1"></i>${emp.fecha}</span>
                        <span style="color: #fbbf24;">${emp.edad} años</span>
                        <span class="badge bg-success" style="font-size: 0.8rem;">${emp.dias}d</span>
                    </div>
                </div>
            </div>
        `;
    });
    listaContainer.innerHTML = html;
}

function irACumpleanero(index) {
    if (index >= 0 && index < cumpleaneros.length) {
        cumpleIndex = index;
        renderCumpleanero(cumpleIndex);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    renderCumpleanero(0);
});
</script>

<?php
if (!function_exists('formatearMoneda')) {
    function formatearMoneda($valor) { return '$' . number_format($valor, 2, '.', ','); }
}
if (!function_exists('numeroRomano')) {
    function numeroRomano($numero) {
        $romanos = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII',13=>'XIII',14=>'XIV',15=>'XV',16=>'XVI'];
        return $romanos[$numero] ?? $numero;
    }
}

?>
<?php if ($abrir_modal_con_id !== null): ?>
<script>
$(document).ready(function() {
    var empId = <?php echo $abrir_modal_con_id; ?>;
    // Buscar el empleado en el array empleadosData (definido al inicio del JS)
    var emp = empleadosData.find(function(e) { return e.id == empId; });
    if (emp) {
        setTimeout(function() {
            editEmpleado(emp);
        }, 500);
    }
});
</script>
<?php endif; ?>

</body>
</html>