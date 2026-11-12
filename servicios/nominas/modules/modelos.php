<?php
// modules/modelos.php - Selección de modelos del sistema
// Permite elegir uno o varios modelos (SNC 1-25, SNC 2-25, Anexo 14 y la
// plantilla para importar el pago de salarios) y continuar con sus acciones.
require_once '../config/database.php';
require_once '../includes/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

if (!permiso_puede('reportes', 'ver')) {
    permiso_denegar_acceso('Modelos');
}

/**
 * Genera y descarga la plantilla Excel para importar el pago de salarios en la
 * nómina automática. Los encabezados van en la fila 3 (igual que el modelo que
 * lee modules/nominas.php): B "No. Exped. *" y E "Horas Lab *" son
 * obligatorios; F, G y H son opcionales.
 */
function modelos_plantilla_salarios_descargar(): void
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No está disponible la librería para generar Excel (vendor/autoload.php).';
        return;
    }
    require_once $autoload;

    while (ob_get_level()) {
        ob_end_clean();
    }

    $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $libro->getProperties()
        ->setCreator(defined('COMPANY_NAME') ? COMPANY_NAME : 'SiGesNom')
        ->setTitle('Modelo para importar pago de salarios')
        ->setDescription('Plantilla para la nómina automática');

    $hoja = $libro->getActiveSheet();
    $hoja->setTitle('Tabla1');

    // ---- Encabezado del modelo -----------------------------------------
    $hoja->mergeCells('A1:H1');
    $hoja->setCellValue('A1', 'MODELO PARA IMPORTAR PAGO DE SALARIOS — NÓMINA AUTOMÁTICA');
    $hoja->getStyle('A1')->getFont()->setName('Calibri')->setSize(14)->setBold(true);
    $hoja->getStyle('A1')->getFont()->getColor()->setARGB('FF1F4E79');
    $hoja->getStyle('A1')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $hoja->mergeCells('A2:H2');
    $hoja->setCellValue('A2', 'Complete una fila por trabajador. Las columnas marcadas con * son obligatorias; las demás son opcionales (si se dejan vacías se toman como 0).');
    $hoja->getStyle('A2')->getFont()->setName('Calibri')->setSize(10)->setItalic(true);
    $hoja->getStyle('A2')->getFont()->getColor()->setARGB('FF595959');
    $hoja->getStyle('A2')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    // ---- Encabezados de columnas (fila 3) ------------------------------
    $encabezados = [
        'A' => 'No.',
        'B' => 'No. Exped. *',
        'C' => 'CI',
        'D' => 'Nombre Completo',
        'E' => 'Horas Lab *',
        'F' => 'Día Feriados',
        'G' => 'Otros Pagos',
        'H' => 'Retenciones',
    ];
    foreach ($encabezados as $col => $texto) {
        $hoja->setCellValue($col . '3', $texto);
    }

    $hoja->getStyle('A3:H3')->applyFromArray([
        'font' => [
            'name' => 'Calibri',
            'size' => 11,
            'bold' => true,
            'color' => ['argb' => 'FFFFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF1F4E79'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['argb' => 'FFBFD1E2'],
            ],
        ],
    ]);

    // ---- Filas de captura ----------------------------------------------
    $fila_inicial = 4;
    $fila_final = 53;
    $hoja->getStyle('A' . $fila_inicial . ':H' . $fila_final)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                'color' => ['argb' => 'FFD9D9D9'],
            ],
        ],
        'font' => ['name' => 'Calibri', 'size' => 11],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ]);
    // B como texto para que Excel conserve los ceros a la izquierda del expediente.
    $hoja->getStyle('B' . $fila_inicial . ':B' . $fila_final)->getNumberFormat()->setFormatCode('@');
    $hoja->getStyle('E' . $fila_inicial . ':E' . $fila_final)->getNumberFormat()->setFormatCode('0.00');
    $hoja->getStyle('F' . $fila_inicial . ':F' . $fila_final)->getNumberFormat()->setFormatCode('0.00');
    $hoja->getStyle('G' . $fila_inicial . ':H' . $fila_final)->getNumberFormat()->setFormatCode('#,##0.00');
    $hoja->getStyle('A' . $fila_inicial . ':A' . $fila_final)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // ---- Ancho de columnas / alto de filas ------------------------------
    $anchos = ['A' => 6, 'B' => 15, 'C' => 16, 'D' => 36, 'E' => 13, 'F' => 14, 'G' => 14, 'H' => 14];
    foreach ($anchos as $col => $ancho) {
        $hoja->getColumnDimension($col)->setWidth($ancho);
    }
    $hoja->getRowDimension(1)->setRowHeight(26);
    $hoja->getRowDimension(2)->setRowHeight(30);
    $hoja->getRowDimension(3)->setRowHeight(30);
    for ($f = $fila_inicial; $f <= $fila_final; $f++) {
        $hoja->getRowDimension($f)->setRowHeight(18);
    }

    // Inmovilizar encabezado y permitir filtrar por columna.
    $hoja->freezePane('A4');
    $hoja->setAutoFilter('A3:H3');
    $hoja->setSelectedCell('B4');

    // Impresión: horizontal y ajustada al ancho de una página.
    $hoja->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $hoja->getPageSetup()->setPrintArea('A1:H' . $fila_final);
    $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(3, 3);

    $libro->setActiveSheetIndex(0);

    $nombre_archivo = 'Modelo_Importar_Pago_Salarios.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro);
    $writer->save('php://output');
    $libro->disconnectWorksheets();
}

// Descarga directa de la plantilla de importación (no requiere selección).
if (isset($_GET['descargar']) && $_GET['descargar'] === 'importacion_salarios') {
    modelos_plantilla_salarios_descargar();
    exit;
}

// Configuración empresa
$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SiGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas'
];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

$puede_snc225 = permiso_puede('snc225', 'ver');
$puede_empleados = permiso_puede('empleados', 'ver');

// Catálogo de modelos. "disponible" controla si la tarjeta se puede marcar;
// "motivo" explica al usuario por qué no está disponible.
$modelos = [
    [
        'id' => 'snc-1-25',
        'nombre' => 'SNC 1-25',
        'descripcion' => 'Modelo de declaración SNC 1-25 para el ejercicio fiscal correspondiente.',
        'icono' => 'fa-file-invoice',
        'tipo' => 'Declaración',
        'color' => 'blue',
        'disponible' => false,
        'motivo' => 'Este modelo todavía no tiene generador en el sistema. Se habilitará en cuanto se defina su contenido.',
        'acciones' => [],
    ],
    [
        'id' => 'snc-2-25',
        'nombre' => 'SNC 2-25',
        'descripcion' => 'Certificación de salarios y tiempo de servicios por trabajador, con vista previa, Excel y PDF.',
        'icono' => 'fa-file-invoice-dollar',
        'tipo' => 'Declaración',
        'color' => 'teal',
        'disponible' => (bool)$puede_snc225,
        'motivo' => 'No tiene permiso para acceder a la tarjeta SNC 2-25.',
        'acciones' => $puede_snc225 ? [
            ['label' => 'Abrir tarjeta SNC 2-25', 'url' => 'snc225.php', 'tipo' => 'url', 'icono' => 'fa-arrow-up-right-from-square'],
        ] : [],
    ],
    [
        'id' => 'anexo-14',
        'nombre' => 'Anexo 14',
        'descripcion' => 'Anexo 14 con la información complementaria de los trabajadores (vista previa y Excel).',
        'icono' => 'fa-paperclip',
        'tipo' => 'Anexo',
        'color' => 'amber',
        'disponible' => (bool)$puede_empleados,
        'motivo' => 'No tiene permiso para acceder a la gestión de empleados.',
        'acciones' => $puede_empleados ? [
            ['label' => 'Vista previa del Anexo 14', 'url' => 'empleados.php?ver_anexo14', 'tipo' => 'url', 'icono' => 'fa-eye'],
            ['label' => 'Descargar Anexo 14 (Excel)', 'url' => 'empleados.php?exportar_anexo14', 'tipo' => 'descarga', 'icono' => 'fa-file-excel'],
        ] : [],
    ],
    [
        'id' => 'importar-pago',
        'nombre' => 'Modelo para Importar Pago Salarios Automática',
        'descripcion' => 'Plantilla Excel para cargar horas, feriados, otros pagos y retenciones en la nómina automática.',
        'icono' => 'fa-money-bill-transfer',
        'tipo' => 'Automatización',
        'color' => 'purple',
        'disponible' => true,
        'motivo' => '',
        'acciones' => [
            ['label' => 'Descargar plantilla (Excel)', 'url' => '?descargar=importacion_salarios', 'tipo' => 'descarga', 'icono' => 'fa-download'],
        ],
    ],
];

$modelos_json = json_encode($modelos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$total_modelos = count($modelos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Modelos</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">

    <style>
        * { margin:0; padding:0; box-sizing:border-box; -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale; }

        body {
            font-family:'Segoe UI Variable','Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;
            background:var(--bg); color:var(--txt); overflow-x:hidden; min-height:100vh;
        }

        /* Fondo tipo "Mica": capas suaves de luz con el acento del tema */
        .modelos-bg {
            position:fixed; inset:0; z-index:-2; pointer-events:none;
            background:
                radial-gradient(ellipse 80% 60% at 20% 0%, rgba(var(--accent-rgb), 0.10) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(168, 85, 247, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 0% 100%, rgba(var(--accent-rgb), 0.06) 0%, transparent 60%);
        }
        html[data-theme="light"] .modelos-bg,
        html[data-theme="orgullo"] .modelos-bg { opacity:.5; }

        .main-container { margin-left:16.25rem; transition:margin .3s ease; min-height:100vh; padding:1.25rem; position:relative; z-index:1; }
        .main-container.expanded { margin-left:5rem; }

        .win-topbar {
            background:var(--panel); backdrop-filter:blur(1.25rem); border-radius:1rem;
            padding:.75rem 1.5rem; margin-bottom:1.5rem; border:.0625rem solid var(--border);
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem;
            z-index:100 !important; position:relative !important;
        }
        .sidebar-toggle { background:rgba(255,255,255,.05); border:none; color:var(--txt); width:2.5rem; height:2.5rem; border-radius:.75rem; cursor:pointer; transition:all .2s; }
        .sidebar-toggle:hover { background:rgba(var(--accent-rgb),.15); }
        .page-title h1 { font-size:1.5rem; font-weight:600; margin:0; color:var(--txt); }
        .page-title p { font-size:.8rem; color:var(--muted); margin:.25rem 0 0; }
        html[data-theme="light"] .sidebar-toggle { background:rgba(0,0,0,.05); }
        html[data-theme="light"] .sidebar-toggle:hover { background:rgba(0,0,0,.1); }

        /* ============ CABECERA ============ */
        .modelos-hero { margin-bottom:1.75rem; }
        .brand-badge {
            display:inline-flex; align-items:center; gap:.5rem;
            background:rgba(var(--accent-rgb),.08); border:.0625rem solid var(--border);
            border-radius:6.25rem; padding:.4rem .9rem; font-size:.75rem; font-weight:500;
            color:var(--muted); letter-spacing:.02em; margin-bottom:1rem; backdrop-filter:blur(1.25rem);
        }
        .brand-badge i { font-size:.75rem; color:var(--accent); }
        .modelos-title { font-weight:600; font-size:2rem; letter-spacing:-.025em; line-height:1.15; margin-bottom:.5rem; color:var(--txt); }
        .modelos-subtitle { color:var(--muted); font-size:.95rem; font-weight:400; max-width:34rem; line-height:1.5; }

        /* ============ BARRA DE SELECCIÓN ============ */
        .modelos-toolbar {
            display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
            padding:.75rem .85rem .75rem 1.15rem;
            background:rgba(255,255,255,.035); border:.0625rem solid var(--border);
            border-radius:.625rem; backdrop-filter:blur(1.875rem) saturate(180%); margin-bottom:1.75rem;
        }
        .selection-counter { display:inline-flex; align-items:center; gap:.65rem; font-weight:500; font-size:.875rem; color:var(--muted); }
        .counter-pill {
            display:inline-flex; align-items:center; justify-content:center; min-width:1.625rem; height:1.625rem;
            padding:0 .55rem; background:var(--accent); color:#fff; border-radius:.375rem;
            font-weight:600; font-size:.78rem; transition:transform .2s ease, background .2s ease;
        }
        .counter-pill.bump { animation:bump .3s ease; }
        @keyframes bump { 0% { transform:scale(1); } 50% { transform:scale(1.12); } 100% { transform:scale(1); } }

        .btn-clear {
            display:inline-flex; align-items:center; gap:.45rem;
            background:rgba(255,255,255,.05); border:.0625rem solid var(--border); color:var(--muted);
            font-weight:500; font-size:.82rem; padding:.45rem .9rem; border-radius:.4375rem;
            transition:all .15s ease; cursor:pointer;
        }
        .btn-clear:hover:not(:disabled) { background:rgba(var(--accent-rgb),.12); border-color:var(--accent); color:var(--txt); }
        .btn-clear:active:not(:disabled) { transform:scale(.98); }
        .btn-clear:disabled { opacity:.35; cursor:default; }

        /* ============ TARJETAS ============ */
        .modelos-grid { display:grid; grid-template-columns:repeat(1, minmax(0,1fr)); gap:1rem; }
        @media (min-width:576px) { .modelos-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (min-width:992px) { .modelos-grid { grid-template-columns:repeat(4, minmax(0,1fr)); } }

        .model-card {
            position:relative; background:rgba(255,255,255,.04); border:.0625rem solid var(--border);
            border-radius:.625rem; padding:1.35rem 1.15rem 1.15rem; cursor:pointer; height:100%;
            display:flex; flex-direction:column; outline:none;
            transition:background .15s ease, border-color .15s ease, box-shadow .2s ease, transform .15s ease;
            backdrop-filter:blur(1.25rem);
        }
        .model-card:hover { background:rgba(var(--accent-rgb),.08); border-color:var(--border-2, rgba(255,255,255,.16)); box-shadow:var(--shadow, 0 .125rem .5rem rgba(0,0,0,.35)); transform:translateY(-1px); }
        .model-card:focus-visible { border-color:var(--accent); box-shadow:0 0 0 .125rem rgba(var(--accent-rgb),.35); }
        .model-card.selected { background:rgba(var(--accent-rgb),.12); border-color:var(--accent); box-shadow:0 .25rem 1.25rem rgba(var(--accent-rgb),.18); }
        .model-card.selected .model-icon { transform:scale(1.04); }
        .model-card.is-unavailable { border-style:dashed; cursor:help; }
        .model-card.is-unavailable:hover { transform:none; background:rgba(255,255,255,.05); border-color:var(--border-2, rgba(255,255,255,.16)); }
        .model-card.is-unavailable .model-name,
        .model-card.is-unavailable .model-desc { opacity:.72; }

        .card-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1rem; gap:.5rem; }
        .model-icon {
            width:2.625rem; height:2.625rem; border-radius:.5625rem; display:flex; align-items:center;
            justify-content:center; font-size:1.05rem; transition:transform .2s ease; flex-shrink:0;
        }
        .check-indicator {
            width:1.25rem; height:1.25rem; border-radius:50%; border:.09375rem solid var(--border-2, rgba(255,255,255,.16));
            display:flex; align-items:center; justify-content:center; font-size:.6rem; color:transparent;
            background:transparent; transition:all .2s ease; flex-shrink:0;
        }
        .model-card.selected .check-indicator { background:var(--accent); border-color:var(--accent); color:#fff; }
        .model-card.is-unavailable .check-indicator { color:var(--faint, #6b6b6b); border-color:var(--border-2, rgba(255,255,255,.16)); }

        .model-name { font-weight:600; font-size:.92rem; line-height:1.35; letter-spacing:-.005em; margin-bottom:.35rem; color:var(--txt); }
        .model-desc { font-size:.78rem; color:var(--muted); line-height:1.5; margin-bottom:.9rem; flex-grow:1; }
        .model-tags { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
        .model-type {
            display:inline-flex; align-items:center; gap:.35rem; font-size:.68rem; font-weight:500;
            letter-spacing:.02em; padding:.28rem .55rem; border-radius:.3125rem;
        }
        .model-flag {
            display:inline-flex; align-items:center; gap:.3rem; font-size:.68rem; font-weight:500;
            padding:.28rem .55rem; border-radius:.3125rem;
            background:rgba(251,191,36,.12); color:#d97706; border:.0625rem solid rgba(251,191,36,.25);
        }

        /* Paleta de íconos */
        .c-blue   { background:rgba(96,165,250,.15);  color:#60a5fa; }
        .c-teal   { background:rgba(45,212,191,.15);  color:#2dd4bf; }
        .c-amber  { background:rgba(251,191,36,.15);  color:#f59e0b; }
        .c-purple { background:rgba(167,139,250,.15); color:#a78bfa; }

        .t-blue   { background:rgba(96,165,250,.12);  color:#60a5fa; }
        .t-teal   { background:rgba(45,212,191,.12);  color:#2dd4bf; }
        .t-amber  { background:rgba(251,191,36,.12);  color:#f59e0b; }
        .t-purple { background:rgba(167,139,250,.12); color:#a78bfa; }

        /* ============ RESUMEN ============ */
        .summary-panel {
            background:rgba(255,255,255,.035); border:.0625rem solid var(--border); border-radius:.625rem;
            overflow:hidden; margin-top:2rem; backdrop-filter:blur(1.875rem) saturate(180%);
        }
        .summary-header {
            padding:1rem 1.25rem; border-bottom:.0625rem solid var(--border);
            display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap;
            background:rgba(var(--accent-rgb),.04);
        }
        .summary-title { font-weight:600; font-size:.875rem; display:flex; align-items:center; gap:.55rem; margin:0; color:var(--txt); }
        .summary-title .title-icon {
            width:1.625rem; height:1.625rem; border-radius:.375rem; background:rgba(var(--accent-rgb),.15);
            color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:.75rem;
        }
        .summary-total-badge {
            font-size:.72rem; font-weight:500; color:var(--muted); padding:.3rem .65rem;
            background:rgba(255,255,255,.04); border:.0625rem solid var(--border); border-radius:.3125rem;
        }
        .summary-total-badge strong { color:var(--accent); font-weight:600; }
        .summary-body { padding:.25rem 1.25rem 1rem; }

        .empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2rem 1rem; text-align:center; color:var(--muted); }
        .empty-state-icon {
            width:3rem; height:3rem; border-radius:.625rem; background:rgba(255,255,255,.04);
            border:.0625rem solid var(--border); display:flex; align-items:center; justify-content:center;
            font-size:1.15rem; color:var(--muted); margin-bottom:.85rem;
        }
        .empty-state p { margin:0; font-size:.83rem; }

        .selected-item { display:flex; align-items:center; gap:.85rem; padding:.75rem 0; border-bottom:.0625rem solid rgba(255,255,255,.05); animation:slideIn .25s ease; }
        .selected-item:last-child { border-bottom:none; }
        @keyframes slideIn { from { opacity:0; transform:translateX(-6px); } to { opacity:1; transform:translateX(0); } }
        .selected-item-icon { width:2rem; height:2rem; border-radius:.4375rem; display:flex; align-items:center; justify-content:center; font-size:.8rem; flex-shrink:0; }
        .selected-item-name { font-weight:500; font-size:.83rem; color:var(--txt); flex-grow:1; line-height:1.35; }
        .selected-item-type { font-size:.65rem; font-weight:500; letter-spacing:.02em; padding:.22rem .5rem; border-radius:.25rem; flex-shrink:0; }
        .remove-btn {
            background:transparent; border:none; color:var(--muted); width:1.625rem; height:1.625rem;
            border-radius:.3125rem; display:flex; align-items:center; justify-content:center;
            font-size:.7rem; transition:all .15s ease; flex-shrink:0; cursor:pointer;
        }
        .remove-btn:hover { background:rgba(239,68,68,.15); color:#f87171; }

        /* ============ ACCIÓN ============ */
        .action-footer { margin-top:1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .action-hint { margin:0; font-size:.78rem; color:var(--muted); display:flex; align-items:center; gap:.4rem; }
        .action-hint i { color:var(--accent); }
        .btn-primary-custom {
            display:inline-flex; align-items:center; gap:.55rem; background:var(--accent); color:#fff;
            border:.0625rem solid rgba(255,255,255,.1); border-radius:.4375rem; padding:.55rem 1.25rem;
            font-weight:500; font-size:.85rem; transition:all .15s ease; cursor:pointer;
        }
        .btn-primary-custom:hover:not(:disabled) { background:var(--accent-dark, var(--accent)); filter:brightness(1.08); border-color:rgba(255,255,255,.18); }
        .btn-primary-custom:active:not(:disabled) { transform:scale(.98); }
        .btn-primary-custom:disabled { opacity:.35; cursor:default; }

        /* ============ MODAL ============ */
        .modal-content { background:var(--panel); color:var(--txt); border:.0625rem solid var(--border); border-radius:.75rem; }
        .modal-header, .modal-footer { border-color:var(--border); }
        .modal-title { font-size:1rem; font-weight:600; color:var(--txt); }
        .modal-header .btn-close { filter:invert(1) grayscale(1) brightness(1.6); }
        html[data-theme="light"] .modal-header .btn-close,
        html[data-theme="orgullo"] .modal-header .btn-close { filter:none; }
        .modelo-accion-row { display:flex; align-items:center; gap:.85rem; padding:.75rem 0; border-bottom:.0625rem solid var(--border); flex-wrap:wrap; }
        .modelo-accion-row:last-child { border-bottom:none; }
        .modelo-accion-row .selected-item-name { min-width:9rem; }
        .modelo-accion-btns { display:flex; gap:.5rem; flex-wrap:wrap; margin-left:auto; }
        .btn-accion {
            display:inline-flex; align-items:center; gap:.45rem; text-decoration:none; font-size:.78rem; font-weight:500;
            padding:.4rem .8rem; border-radius:.375rem; border:.0625rem solid var(--border);
            color:var(--txt); background:rgba(var(--accent-rgb),.10); transition:all .15s ease; cursor:pointer;
        }
        .btn-accion:hover { background:rgba(var(--accent-rgb),.2); border-color:var(--accent); color:var(--txt); }
        .btn-accion.accion-descarga { background:rgba(45,212,191,.12); }
        .btn-accion.accion-descarga:hover { background:rgba(45,212,191,.22); border-color:#2dd4bf; }

        /* ============ TEMA CLARO ============ */
        html[data-theme="light"] .modelos-toolbar,
        html[data-theme="orgullo"] .modelos-toolbar,
        html[data-theme="light"] .summary-panel,
        html[data-theme="orgullo"] .summary-panel { background:rgba(0,0,0,.03); }
        html[data-theme="light"] .model-card,
        html[data-theme="orgullo"] .model-card { background:rgba(255,255,255,.72); }
        html[data-theme="light"] .model-card:hover,
        html[data-theme="orgullo"] .model-card:hover { background:#fff; }
        html[data-theme="light"] .model-card.selected,
        html[data-theme="orgullo"] .model-card.selected { background:rgba(var(--accent-rgb),.10); }
        html[data-theme="light"] .btn-clear,
        html[data-theme="orgullo"] .btn-clear { background:rgba(0,0,0,.03); }
        html[data-theme="light"] .summary-total-badge,
        html[data-theme="orgullo"] .summary-total-badge { background:rgba(0,0,0,.03); }
        html[data-theme="light"] .empty-state-icon,
        html[data-theme="orgullo"] .empty-state-icon { background:rgba(0,0,0,.04); }
        html[data-theme="light"] .selected-item,
        html[data-theme="orgullo"] .selected-item { border-bottom-color:rgba(0,0,0,.06); }
        html[data-theme="light"] .c-blue,
        html[data-theme="orgullo"] .c-blue { color:#2563eb; }
        html[data-theme="light"] .c-teal,
        html[data-theme="orgullo"] .c-teal { color:#0d9488; }
        html[data-theme="light"] .c-amber,
        html[data-theme="orgullo"] .c-amber { color:#b45309; }
        html[data-theme="light"] .c-purple,
        html[data-theme="orgullo"] .c-purple { color:#7c3aed; }
        html[data-theme="light"] .t-blue,
        html[data-theme="orgullo"] .t-blue { color:#2563eb; }
        html[data-theme="light"] .t-teal,
        html[data-theme="orgullo"] .t-teal { color:#0d9488; }
        html[data-theme="light"] .t-amber,
        html[data-theme="orgullo"] .t-amber { color:#b45309; }
        html[data-theme="light"] .t-purple,
        html[data-theme="orgullo"] .t-purple { color:#7c3aed; }
        html[data-theme="light"] .model-flag,
        html[data-theme="orgullo"] .model-flag { color:#92400e; }

        /* ============ SCROLLBAR ============ */
        ::-webkit-scrollbar { width:.75rem; height:.75rem; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:rgba(255,255,255,.15); border-radius:.375rem; border:.1875rem solid transparent; background-clip:padding-box; }
        ::-webkit-scrollbar-thumb:hover { background:rgba(255,255,255,.25); background-clip:padding-box; border:.1875rem solid transparent; }
        html[data-theme="light"] ::-webkit-scrollbar-thumb { background:rgba(0,0,0,.2); background-clip:padding-box; }
        html[data-theme="light"] ::-webkit-scrollbar-thumb:hover { background:rgba(0,0,0,.3); background-clip:padding-box; }

        @media (max-width:768px) {
            .main-container { margin-left:0; padding:1rem; }
            .modelos-title { font-size:1.55rem; }
            .modelos-toolbar { padding:.65rem .75rem .65rem 1rem; }
            .summary-body { padding:0 .9rem .9rem; }
            .summary-header { padding:.85rem .9rem; }
        }
    </style>
</head>
<body>

<div class="modelos-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">

    <!-- ============ TOPBAR ============ -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-layer-group me-2" style="color: var(--accent);"></i>Modelos</h1>
                <p><i class="fas fa-file-alt me-1"></i> Selección y descarga de modelos del sistema</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- ============ CABECERA ============ -->
    <div class="modelos-hero fade-in-up">
        <div class="brand-badge">
            <i class="fas fa-shield-halved"></i>
            <span>Sistema de Declaraciones</span>
        </div>
        <h2 class="modelos-title">Selección de modelos</h2>
        <p class="modelos-subtitle">Elige los modelos que deseas incluir en el proceso. Puedes seleccionar varios a la vez y continuar con sus acciones.</p>
    </div>

    <!-- ============ BARRA DE SELECCIÓN ============ -->
    <div class="modelos-toolbar fade-in-up">
        <div class="selection-counter">
            <span class="counter-pill" id="contadorModelos">0</span>
            <span id="contadorTexto">modelos seleccionados</span>
        </div>
        <button type="button" class="btn-clear" id="btnLimpiarModelos" disabled>
            <i class="fas fa-rotate-left"></i>
            <span>Limpiar selección</span>
        </button>
    </div>

    <!-- ============ GRID DE MODELOS ============ -->
    <div class="modelos-grid fade-in-up" id="gridModelos">
        <?php foreach ($modelos as $m): ?>
        <article class="model-card<?php echo $m['disponible'] ? '' : ' is-unavailable'; ?>"
                 data-id="<?php echo htmlspecialchars($m['id']); ?>"
                 role="checkbox"
                 tabindex="0"
                 aria-checked="false"
                 aria-disabled="<?php echo $m['disponible'] ? 'false' : 'true'; ?>"
                 aria-label="<?php echo htmlspecialchars($m['nombre']); ?>">
            <div class="card-top">
                <div class="model-icon c-<?php echo htmlspecialchars($m['color']); ?>">
                    <i class="fas <?php echo htmlspecialchars($m['icono']); ?>"></i>
                </div>
                <div class="check-indicator">
                    <i class="fas <?php echo $m['disponible'] ? 'fa-check' : 'fa-lock'; ?>"></i>
                </div>
            </div>
            <h3 class="model-name"><?php echo htmlspecialchars($m['nombre']); ?></h3>
            <p class="model-desc"><?php echo htmlspecialchars($m['descripcion']); ?></p>
            <div class="model-tags">
                <span class="model-type t-<?php echo htmlspecialchars($m['color']); ?>"><?php echo htmlspecialchars($m['tipo']); ?></span>
                <?php if (!$m['disponible']): ?>
                <span class="model-flag"><i class="fas fa-clock"></i> Próximamente</span>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- ============ RESUMEN ============ -->
    <div class="summary-panel fade-in-up">
        <div class="summary-header">
            <h5 class="summary-title">
                <span class="title-icon"><i class="fas fa-list-check"></i></span>
                Resumen de selección
            </h5>
            <span class="summary-total-badge"><strong id="badgeTotal">0</strong> de <?php echo (int)$total_modelos; ?></span>
        </div>
        <div class="summary-body" id="summaryBody">
            <div class="empty-state" id="emptyState">
                <div class="empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                <p>Aún no has seleccionado ningún modelo.</p>
            </div>
        </div>
    </div>

    <!-- ============ ACCIÓN ============ -->
    <div class="action-footer fade-in-up">
        <p class="action-hint"><i class="fas fa-circle-info"></i> Al continuar se abrirán las acciones de los modelos seleccionados.</p>
        <button type="button" class="btn-primary-custom" id="btnContinuarModelos" disabled>
            <span>Continuar</span>
            <i class="fas fa-arrow-right" style="font-size:.75rem;"></i>
        </button>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- ============ MODAL DE ACCIONES ============ -->
<div class="modal fade" id="modalAccionesModelos" tabindex="-1" aria-labelledby="modalAccionesModelosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAccionesModelosLabel"><i class="fas fa-list-check me-2" style="color:var(--accent);"></i>Modelos seleccionados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="listaAccionesModelos"></div>
            <div class="modal-footer">
                <button type="button" class="btn-clear" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i><span>Cerrar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>

<script>
(function () {
    'use strict';

    var MODELOS = <?php echo $modelos_json; ?>;
    var seleccionados = new Set();

    var grid = document.getElementById('gridModelos');
    var contadorEl = document.getElementById('contadorModelos');
    var contadorTexto = document.getElementById('contadorTexto');
    var badgeTotal = document.getElementById('badgeTotal');
    var summaryBody = document.getElementById('summaryBody');
    var btnLimpiar = document.getElementById('btnLimpiarModelos');
    var btnContinuar = document.getElementById('btnContinuarModelos');
    var listaAcciones = document.getElementById('listaAccionesModelos');
    var modalEl = document.getElementById('modalAccionesModelos');
    var modalAcciones = modalEl ? new bootstrap.Modal(modalEl) : null;

    function modeloPorId(id) {
        for (var i = 0; i < MODELOS.length; i++) {
            if (MODELOS[i].id === id) return MODELOS[i];
        }
        return null;
    }

    function tarjetaPorId(id) {
        return grid.querySelector('.model-card[data-id="' + id + '"]');
    }

    function avisarNoDisponible(m) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: m.nombre,
                text: m.motivo || 'Este modelo todavía no está disponible.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#3b82f6'
            });
        } else {
            alert(m.nombre + '\n\n' + (m.motivo || 'Este modelo todavía no está disponible.'));
        }
    }

    function alternar(id) {
        var m = modeloPorId(id);
        var card = tarjetaPorId(id);
        if (!m || !card) return;

        if (!m.disponible) {
            avisarNoDisponible(m);
            return;
        }

        if (seleccionados.has(id)) {
            seleccionados.delete(id);
            card.classList.remove('selected');
            card.setAttribute('aria-checked', 'false');
        } else {
            seleccionados.add(id);
            card.classList.add('selected');
            card.setAttribute('aria-checked', 'true');
        }
        actualizarUI(true);
    }

    function actualizarUI(animar) {
        var n = seleccionados.size;

        contadorEl.textContent = String(n);
        badgeTotal.textContent = String(n);
        contadorTexto.textContent = n === 1 ? 'modelo seleccionado' : 'modelos seleccionados';

        if (animar) {
            contadorEl.classList.remove('bump');
            void contadorEl.offsetWidth;
            contadorEl.classList.add('bump');
        }

        btnLimpiar.disabled = n === 0;
        btnContinuar.disabled = n === 0;

        summaryBody.innerHTML = '';

        if (n === 0) {
            var vacio = document.createElement('div');
            vacio.className = 'empty-state';
            vacio.innerHTML = '<div class="empty-state-icon"><i class="fa-regular fa-folder-open"></i></div><p>Aún no has seleccionado ningún modelo.</p>';
            summaryBody.appendChild(vacio);
            return;
        }

        MODELOS.forEach(function (m) {
            if (!seleccionados.has(m.id)) return;

            var item = document.createElement('div');
            item.className = 'selected-item';

            var icono = document.createElement('div');
            icono.className = 'selected-item-icon c-' + m.color;
            var i = document.createElement('i');
            i.className = 'fas ' + m.icono;
            icono.appendChild(i);

            var nombre = document.createElement('div');
            nombre.className = 'selected-item-name';
            nombre.textContent = m.nombre;

            var tipo = document.createElement('span');
            tipo.className = 'selected-item-type t-' + m.color;
            tipo.textContent = m.tipo;

            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'remove-btn';
            quitar.title = 'Quitar';
            quitar.setAttribute('aria-label', 'Quitar ' + m.nombre);
            quitar.innerHTML = '<i class="fas fa-xmark"></i>';
            quitar.addEventListener('click', function (ev) {
                ev.stopPropagation();
                alternar(m.id);
            });

            item.appendChild(icono);
            item.appendChild(nombre);
            item.appendChild(tipo);
            item.appendChild(quitar);
            summaryBody.appendChild(item);
        });
    }

    function limpiarSeleccion() {
        seleccionados.clear();
        grid.querySelectorAll('.model-card').forEach(function (c) {
            c.classList.remove('selected');
            c.setAttribute('aria-checked', 'false');
        });
        actualizarUI(false);
    }

    function ejecutarAccion(accion) {
        if (!accion || !accion.url) return;
        if (accion.tipo === 'descarga') {
            window.location.href = accion.url;
        } else {
            window.open(accion.url, '_blank', 'noopener');
        }
    }

    function continuar() {
        var elegidos = MODELOS.filter(function (m) {
            return seleccionados.has(m.id) && m.acciones && m.acciones.length;
        });

        if (elegidos.length === 0) return;

        var totalAcciones = elegidos.reduce(function (acc, m) { return acc + m.acciones.length; }, 0);

        // Un solo destino: se ejecuta directamente.
        if (totalAcciones === 1) {
            ejecutarAccion(elegidos[0].acciones[0]);
            return;
        }

        // Varios destinos: se muestran todos para que el usuario elija.
        listaAcciones.innerHTML = '';
        elegidos.forEach(function (m) {
            var fila = document.createElement('div');
            fila.className = 'modelo-accion-row';

            var icono = document.createElement('div');
            icono.className = 'selected-item-icon c-' + m.color;
            icono.innerHTML = '<i class="fas ' + m.icono + '"></i>';

            var nombre = document.createElement('div');
            nombre.className = 'selected-item-name';
            nombre.textContent = m.nombre;

            var botones = document.createElement('div');
            botones.className = 'modelo-accion-btns';

            m.acciones.forEach(function (a) {
                var btn = document.createElement(a.tipo === 'descarga' ? 'a' : 'a');
                btn.className = 'btn-accion' + (a.tipo === 'descarga' ? ' accion-descarga' : '');
                btn.href = a.url;
                if (a.tipo === 'url') {
                    btn.target = '_blank';
                    btn.rel = 'noopener';
                }
                btn.innerHTML = '<i class="fas ' + (a.icono || 'fa-arrow-right') + '"></i><span></span>';
                btn.querySelector('span').textContent = a.label;
                botones.appendChild(btn);
            });

            fila.appendChild(icono);
            fila.appendChild(nombre);
            fila.appendChild(botones);
            listaAcciones.appendChild(fila);
        });

        if (modalAcciones) modalAcciones.show();
    }

    // Eventos de las tarjetas
    grid.querySelectorAll('.model-card').forEach(function (card) {
        card.addEventListener('click', function () {
            alternar(card.dataset.id);
        });
        card.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                alternar(card.dataset.id);
            }
        });
    });

    btnLimpiar.addEventListener('click', limpiarSeleccion);
    btnContinuar.addEventListener('click', continuar);

    actualizarUI(false);
})();
</script>

</body>
</html>
