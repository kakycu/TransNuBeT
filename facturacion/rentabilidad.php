<!DOCTYPE html>
<?php
/**
 * calculo_rentabilidad.php - SISFACT PDL VISIONES
 * SOLUCIÓN INTEGRAL: Estilo Windows 11, Codificación UTF-8 y Ficha MFP Cuba
 * CON FILTROS POR MESES/AÑOS PARA CÁLCULO DE PÉRDIDAS Y GANANCIAS REALES
 * INCLUYE: Múltiples impresoras con fechas de adquisición individuales,
 * gastos fijos (salario + electricidad + transportación + líneas móviles + depreciación + seguridad social),
 * impuestos (ventas 10%, contribución local 1%), gastos indirectos configurables,
 * categorías específicas (1,2,3,4,6) y VISTA CATÁLOGO DE SERVICIOS (sin facturación)
 */
header('Content-Type: text/html; charset=utf-8');
require_once 'config/init.php';

date_default_timezone_set('America/New_York'); 

// 1. VERIFICACIÓN DE AUTENTICACIÓN
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}


// --- RESETEAR VALORES POR DEFECTO ---
if (isset($_GET['reset_valores'])) {
    // Limpiar todas las variables de sesión relacionadas con gastos
    unset($_SESSION['gasto_salario']);
    unset($_SESSION['gasto_electricidad']);
    unset($_SESSION['gasto_transportacion']);
    unset($_SESSION['gasto_movil']);
    unset($_SESSION['tasa_seguridad_social']);
    unset($_SESSION['tasa_impuesto_ventas']);
    unset($_SESSION['tasa_contribucion_local']);
    unset($_SESSION['porcentaje_indirectos']);
    
    // Opcional: Limpiar variables específicas de sesión que no quieras persistir
    unset($_SESSION['filtros_insumos']);
    unset($_SESSION['filtros_gastos']);
    unset($_SESSION['filtros_hoja_ruta']);

    // Redirigir para quitar el parámetro de la URL
    header("Location: ?tipo_reporte={$_GET['tipo_reporte']}&mes_desde={$_GET['mes_desde']}&ano_desde={$_GET['ano_desde']}&mes_hasta={$_GET['mes_hasta']}&ano_hasta={$_GET['ano_hasta']}");
    exit;
}

// =============================================================================
// 1. CONFIGURACIÓN DE FECHA BASADA EN CIERRE DE OPERACIONES
// =============================================================================

// Obtener la fecha maestra de la base de datos (usando tus funciones globales)
$mes_cierre_num = obtenerMesCierreOperaciones();
$anio_cierre_num = obtenerAnioCierreOperaciones();
// Si por error devuelve null, usamos la fecha actual como fallback
$fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d'); 
$timestamp_op = strtotime($fecha_op_raw);

// Variables de tiempo Maestras (Sincronizadas con la BD)
$anio_actual = date('Y', $timestamp_op);
$mes_actual  = date('n', $timestamp_op);
$dia_actual  = date('j');//date('j', $timestamp_op);
$total_dias_mes = date('t', $timestamp_op);
$hoy = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_actual);
$timestamp_combinado = strtotime($hoy);

$dia_semana = date('N', $timestamp_combinado);

// Calcular Trimestre de la fecha de cierre
$trimestre_actual = ceil($mes_actual / 3);


// 2. OBTENCIÓN DE DATOS Y ESTADÍSTICAS
try {
    $db = Database::getConnection();
    
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) { header('Location: login.php'); exit(); }
    
    // Conteos para Sidebar
    $total_facturas = $db->query("SELECT COUNT(*) FROM tbl_fact")->fetchColumn();
    $total_clientes = $db->query("SELECT COUNT(*) FROM clasif_clientes")->fetchColumn();
    $total_serv_sidebar = $db->query("SELECT COUNT(*) FROM clasif_serv")->fetchColumn();
    $total_usuarios = $db->query("SELECT COUNT(*) FROM clasif_usuarios")->fetchColumn();
    $total_categorias = $db->query("SELECT COUNT(*) FROM clasif_cat_de_serv")->fetchColumn();
    $estadisticas['total'] = $db->query("SELECT COUNT(*) FROM historico_operaciones")->fetchColumn();

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: dashboard.php'); exit();
}

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Colores del tema
$temas_windows = [
    'dark' => [
        'nombre' => 'Windows Dark',
        'bg_primary' => '#0d0d0d',
        'bg_secondary' => '#1f1f1f',
        'bg_tertiary' => '#2d2d2d',
        'text_primary' => '#ffffff',
        'text_secondary' => '#a6a6a6',
        'border_color' => '#3d3d3d',
        'accent_color' => $color_accent
    ],
    'light' => [
        'nombre' => 'Windows Light',
        'bg_primary' => '#f3f3f3',
        'bg_secondary' => '#ffffff',
        'bg_tertiary' => '#fafafa',
        'text_primary' => '#000000',
        'text_secondary' => '#666666',
        'border_color' => '#e5e5e5',
        'accent_color' => $color_accent
    ]
];

$tema_actual = $temas_windows[$tema_windows];

// Colores de acento disponibles
$colores_accent = [
    '#0078d4' => 'Azul Windows',
    '#107c10' => 'Verde',
    '#5c2d91' => 'Morado',
    '#e81123' => 'Rojo',
    '#ff8c00' => 'Naranja',
    '#0099bc' => 'Cian',
    '#e3008c' => 'Rosa',
    '#8764b8' => 'Lila'
];

$servicios_count = $db->query("SELECT COUNT(*) FROM clasif_serv WHERE activo = 1")->fetchColumn();

// --- MODELOS DE IMPRESORAS (CATÁLOGO COMPLETO) ---
$catalogo_impresoras = [
    'Epson EcoTank (Tinta Continua)' => [
        'l3250'  => ['nombre' => 'Epson L3250 / L3210 / L3110', 'rend_n' => 4500, 'rend_c' => 7500],
        'l4260'  => ['nombre' => 'Epson L4260 (Dúplex)', 'rend_n' => 7500, 'rend_c' => 6000],
        'et2800' => ['nombre' => 'Epson EcoTank ET-2800', 'rend_n' => 4500, 'rend_c' => 7500],
        'et3950' => ['nombre' => 'Epson EcoTank ET-3950', 'rend_n' => 7500, 'rend_c' => 6000],
        'et4800' => ['nombre' => 'Epson EcoTank ET-4800', 'rend_n' => 4500, 'rend_c' => 7500],
        'l8180'  => ['nombre' => 'Epson EcoTank L8180 (6 Colores)', 'rend_n' => 6700, 'rend_c' => 6200],
    ],
    'HP LaserJet (Tóner Monocromático)' => [
        'hp_m15'  => ['nombre' => 'HP LaserJet M15w / M110w', 'rend_n' => 1000, 'rend_c' => 1],
        'hp_m127' => ['nombre' => 'HP LaserJet Pro MFP M127fw / 125', 'rend_n' => 1500, 'rend_c' => 1],
        'hp_m404' => ['nombre' => 'HP LaserJet Pro M404dw / M402', 'rend_n' => 3000, 'rend_c' => 1],
        'br_mono' => ['nombre' => 'Brother HL-L2300 / 2360', 'rend_n' => 2600, 'rend_c' => 1],
    ],
    'Laser / Toner (Color)' => [
        'hp_color' => ['nombre' => 'HP LaserJet Pro Color M255', 'rend_n' => 1350, 'rend_c' => 1250],
        'br_color' => ['nombre' => 'Brother HL-L3270CDW', 'rend_n' => 3000, 'rend_c' => 2300],
    ],
    'Canon G-Series (MegaTank)' => [
        'can_g2100' => ['nombre' => 'Canon Pixma G2100 / G3100', 'rend_n' => 6000, 'rend_c' => 7000],
    ],
    'Matriciales (Cinta)' => [
        'lx350' => ['nombre' => 'Epson LX-310 / LX-350 (Cinta)', 'rend_n' => 3000, 'rend_c' => 1],
		'lx890' => ['nombre' => 'Epson LX-890 (Cinta)', 'rend_n' => 5000, 'rend_c' => 1],
        'lq590' => ['nombre' => 'Epson LQ-590 (24 pines)', 'rend_n' => 5000, 'rend_c' => 1],
    ],
    'Otros' => [
        'custom' => ['nombre' => 'Personalizado / Manual', 'rend_n' => 0, 'rend_c' => 0]
    ]
];

// --- DEFINIR CATEGORÍAS A INCLUIR EN EL CÁLCULO ---
$categorias_incluidas = [];//1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]; // Solo estas categorías serán consideradas

// --- VARIABLES PARA GASTOS FIJOS (TODOS CONFIGURABLES) ---
if (isset($_POST['gasto_salario'])) {
    $_SESSION['gasto_salario'] = floatval($_POST['gasto_salario']);
}
if (isset($_POST['gasto_electricidad'])) {
    $_SESSION['gasto_electricidad'] = floatval($_POST['gasto_electricidad']);
}
if (isset($_POST['gasto_transportacion'])) {
    $_SESSION['gasto_transportacion'] = floatval($_POST['gasto_transportacion']);
}
if (isset($_POST['gasto_movil'])) {
    $_SESSION['gasto_movil'] = floatval($_POST['gasto_movil']);
}
if (isset($_POST['tasa_seguridad_social'])) {
    $_SESSION['tasa_seguridad_social'] = floatval($_POST['tasa_seguridad_social']);
}
if (isset($_POST['tasa_impuesto_ventas'])) {
    $_SESSION['tasa_impuesto_ventas'] = floatval($_POST['tasa_impuesto_ventas']);
}
if (isset($_POST['tasa_contribucion_local'])) {
    $_SESSION['tasa_contribucion_local'] = floatval($_POST['tasa_contribucion_local']);
}
if (isset($_POST['porcentaje_indirectos'])) {
    $_SESSION['porcentaje_indirectos'] = floatval($_POST['porcentaje_indirectos']);
}

$gasto_salario_mensual = floatval($_POST['gasto_salario'] ?? $_SESSION['gasto_salario'] ?? 29830.00);
$gasto_electricidad_mensual = floatval($_POST['gasto_electricidad'] ?? $_SESSION['gasto_electricidad'] ?? 2000.00);
$gasto_transportacion_mensual = floatval($_POST['gasto_transportacion'] ?? $_SESSION['gasto_transportacion'] ?? 16500.00);
$gasto_movil_mensual = floatval($_POST['gasto_movil'] ?? $_SESSION['gasto_movil'] ?? 9870.00);
$tasa_seguridad_social = floatval($_POST['tasa_seguridad_social'] ?? $_SESSION['tasa_seguridad_social'] ?? 14);
$tasa_impuesto_ventas = floatval($_POST['tasa_impuesto_ventas'] ?? $_SESSION['tasa_impuesto_ventas'] ?? 10);
$tasa_contribucion_local = floatval($_POST['tasa_contribucion_local'] ?? $_SESSION['tasa_contribucion_local'] ?? 0);
$porcentaje_indirectos = floatval($_POST['porcentaje_indirectos'] ?? $_SESSION['porcentaje_indirectos'] ?? 40);

// Calcular seguridad social patronal mensual
$seguridad_social_mensual = $gasto_salario_mensual * ($tasa_seguridad_social / 100);

// Factor indirectos
$factor_indirectos = $porcentaje_indirectos / 100;

// --- PROCESAR MÚLTIPLES IMPRESORAS CON FECHAS INDIVIDUALES ---
$impresoras_config = [];

// Procesar impresoras del formulario
if (isset($_POST['impresoras']) && is_array($_POST['impresoras'])) {
    foreach ($_POST['impresoras'] as $index => $imp_data) {
        if (!empty($imp_data['id'])) {
            $impresora = [
                'id' => $imp_data['id'],
                'cantidad' => intval($imp_data['cantidad'] ?? 1),
                'fecha_adquisicion' => $imp_data['fecha_adquisicion'] ?? date('Y-m-d', strtotime('25-09-29')),//date('Y-m-d', strtotime('-3 years')),
                'valor_unitario' => floatval($imp_data['valor_unitario'] ?? 294700),
                'rend_n' => 4500,
                'rend_c' => 7500
            ];
            
            // Buscar rendimientos en el catálogo
            if ($imp_data['id'] === 'custom') {
                $impresora['rend_n'] = max(1, intval($imp_data['rend_n'] ?? 4500));
                $impresora['rend_c'] = max(1, intval($imp_data['rend_c'] ?? 7500));
            } else {
                foreach ($catalogo_impresoras as $categoria => $modelos) {
                    if (isset($modelos[$imp_data['id']])) {
                        $impresora['rend_n'] = $modelos[$imp_data['id']]['rend_n'];
                        $impresora['rend_c'] = $modelos[$imp_data['id']]['rend_c'];
                        break;
                    }
                }
            }
            
            $impresoras_config[] = $impresora;
        }
    }
}

// Si no hay impresoras configuradas, agregar una por defecto
if (empty($impresoras_config)) {
    $impresoras_config = [
        [
            'id' => 'l3250',
            'cantidad' => 1,
            'fecha_adquisicion' => date('Y-m-d', strtotime('25-09-29')),
            'valor_unitario' => 294700,
            'rend_n' => 4500,
            'rend_c' => 7500
        ]
    ];
}

// --- CALCULAR DEPRECIACIÓN TOTAL POR IMPRESORAS ---
$tasa_depreciacion = 20; // Fija 20% anual
$fecha_hoy = new DateTime();
$depreciacion_total_mensual = 0;
$valor_total_equipos = 0;
$meses_transcurridos_global = 0;
$vida_util_meses_global = (1 / ($tasa_depreciacion / 100)) * 12; // 60 meses

foreach ($impresoras_config as $imp) {
    $fecha_inicio = new DateTime($imp['fecha_adquisicion']);
    $diferencia = $fecha_inicio->diff($fecha_hoy);
    
    // Cálculo corregido de meses transcurridos
    $meses_trans = ($diferencia->y * 12) + $diferencia->m;
    
    // Si el día actual es menor que el día de inicio y hay al menos un mes transcurrido,
    // significa que no hemos completado el último mes
    if ($fecha_hoy->format('d') < $fecha_inicio->format('d') && $meses_trans > 0) {
        // No restamos, solo indicamos que es parcial
        // Los meses completos ya están bien calculados por diff()
    }
    
    $meses_trans = max(0, $meses_trans);
    
    $valor_equipo = $imp['valor_unitario'] * $imp['cantidad'];
    $valor_total_equipos += $valor_equipo;
    
    if ($meses_trans < $vida_util_meses_global) {
        $dep_anual = $valor_equipo * ($tasa_depreciacion / 100);
        $dep_mensual = $dep_anual / 12;
        $depreciacion_total_mensual += $dep_mensual;
    }
    
    $meses_transcurridos_global += $meses_trans * $valor_equipo;
}

// Calcular promedios ponderados
if ($valor_total_equipos > 0) {
    $meses_transcurridos_global = round($meses_transcurridos_global / $valor_total_equipos);
} else {
    $meses_transcurridos_global = 0;
}

$vida_util_meses = $vida_util_meses_global;
$meses_restantes = max(0, $vida_util_meses - $meses_transcurridos_global);
$depreciacion_mensual = $depreciacion_total_mensual;

if ($depreciacion_mensual > 0) {
    $estado_depreciacion = "En proceso";
} else {
    $estado_depreciacion = "Completada (equipos pagados)";
}

// --- PROCESAR PARÁMETROS DE INSUMOS ---
$precio_resma = floatval($_POST['precio_resma'] ?? 2500.00);
$uds_resma    = intval($_POST['uds_resma'] ?? 500); 
$precio_cart  = floatval($_POST['precio_cart'] ?? 4500.00);
$uds_cart     = intval($_POST['uds_cart'] ?? 100);
$precio_foto  = floatval($_POST['precio_foto'] ?? 3500.00);
$uds_foto     = intval($_POST['uds_foto'] ?? 20);
$precio_kit   = floatval($_POST['precio_kit'] ?? 12000.00);


// --- PROCESAR MÁRGENES DINÁMICOS POR CATEGORÍA ---
if (isset($_POST['margen_cat8_general'])) {
    $_SESSION['margen_cat8_general'] = floatval($_POST['margen_cat8_general']);
}
if (isset($_POST['margen_cat8_cunos'])) {
    $_SESSION['margen_cat8_cunos'] = floatval($_POST['margen_cat8_cunos']);
}
if (isset($_POST['margen_cat8_tinta'])) {
    $_SESSION['margen_cat8_tinta'] = floatval($_POST['margen_cat8_tinta']);
}
if (isset($_POST['margen_cat9_general'])) {
    $_SESSION['margen_cat9_general'] = floatval($_POST['margen_cat9_general']);
}
if (isset($_POST['margen_cat10_general'])) {
    $_SESSION['margen_cat10_general'] = floatval($_POST['margen_cat10_general']);
}
if (isset($_POST['margen_cat11_general'])) {
    $_SESSION['margen_cat11_general'] = floatval($_POST['margen_cat11_general']);
}
if (isset($_POST['margen_cat12_general'])) {
    $_SESSION['margen_cat12_general'] = floatval($_POST['margen_cat12_general']);
}

// Valores por defecto (si no existen en sesión)
$margen_cat8_general = $_SESSION['margen_cat8_general'] ?? 75;
$margen_cat8_cunos = $_SESSION['margen_cat8_cunos'] ?? 40;
$margen_cat8_tinta = $_SESSION['margen_cat8_tinta'] ?? 70;
$margen_cat9_general = $_SESSION['margen_cat9_general'] ?? 75;
$margen_cat10_general = $_SESSION['margen_cat10_general'] ?? 80;
$margen_cat11_general = $_SESSION['margen_cat11_general'] ?? 75;
$margen_cat12_general = $_SESSION['margen_cat12_general'] ?? 70;

// NUEVOS: MICAS
$precio_mica_carta = floatval($_POST['precio_mica_carta'] ?? 400.00);
$uds_mica_carta = intval($_POST['uds_mica_carta'] ?? 100);
$precio_mica_carnet = floatval($_POST['precio_mica_carnet'] ?? 200.00);
$uds_mica_carnet = intval($_POST['uds_mica_carnet'] ?? 100);


// Calcular costos unitarios de insumos
$costo_h_bond = $uds_resma > 0 ? $precio_resma / $uds_resma : 0;
$costo_h_cart = $uds_cart > 0 ? $precio_cart / $uds_cart : 0;
$costo_h_foto = $uds_foto > 0 ? $precio_foto / $uds_foto : 0;

$costo_mica_carta = $uds_mica_carta > 0 ? $precio_mica_carta / $uds_mica_carta : 0;
$costo_mica_carnet = $uds_mica_carnet > 0 ? $precio_mica_carnet / $uds_mica_carnet : 0;

// Calcular costo de tinta considerando múltiples impresoras
$total_impresoras = 0;
$total_rend_n = 0;
$total_rend_c = 0;

foreach ($impresoras_config as $imp) {
    $total_impresoras += $imp['cantidad'];
    $total_rend_n += $imp['rend_n'] * $imp['cantidad'];
    $total_rend_c += $imp['rend_c'] * $imp['cantidad'];
}

// Costo de tinta promedio ponderado por cantidad de impresoras
$costo_t_bn = $total_rend_n > 0 ? ($precio_kit / 4) / ($total_rend_n / $total_impresoras) : 0;
$costo_t_cl = $total_rend_c > 0 ? $costo_t_bn + (($precio_kit * 0.75) / ($total_rend_c / $total_impresoras)) : 0;

// TOTAL GASTOS FIJOS MENSUALES (sin incluir depreciación aquí porque ya se calculó)
$gastos_fijos_mensuales = $gasto_salario_mensual + $gasto_electricidad_mensual + $gasto_transportacion_mensual + $gasto_movil_mensual + $depreciacion_mensual + $seguridad_social_mensual;

// --- FUNCIÓN PARA OBTENER COPIAS POR HOJA ---
function obtenerCopiasPorHoja($descripcion) {
    $desc = mb_strtolower($descripcion);
    if (strpos($desc, '1x1') !== false) return 48; 
    if (strpos($desc, 'pasaporte') !== false || strpos($desc, 'visa') !== false) return 20; 
    if (strpos($desc, '5x7') !== false) return 4;
    if (strpos($desc, '4x6') !== false || strpos($desc, '10x15') !== false) return 4;
    return 1;
}

// --- FUNCIÓN PARA CALCULAR COSTO DE UN SERVICIO ESPECÍFICO (PARA CATÁLOGO) ---
function calcularCostoServicioCatalogo($servicio, $costo_h_bond, $costo_h_cart, $costo_h_foto, $costo_t_bn, $costo_t_cl, $costo_mica_carta = 0, $costo_mica_carnet = 0) {
    $desc = mb_strtolower($servicio['descripcion']);
    $cat = $servicio['categoria_id'];
    $id = $servicio['id'];
    
    // ============================================
    // CASO ESPECIAL: MICAS PLÁSTICAS (IDs 29 y 30)
    // ============================================
    if ($id == 29 || $id == 30 || strpos($desc, 'mica') !== false) {
        $es_carta = (strpos($desc, 'carta') !== false || $id == 30);
        $costo_mica = $es_carta ? $costo_mica_carta : $costo_mica_carnet;
        
        return [
            'costo_directo' => $costo_mica,
            'papel' => 0,
            'tinta' => 0,
            'detalle' => 'Mica plástica ' . ($es_carta ? 'tamaño carta' : 'tamaño carnet')
        ];
    }
    
    // ============================================
    // CASO: SERVICIOS MANUALES (sin costo de insumos)
    // ============================================
    $es_manual = (strpos($desc, 'encuadernado') !== false || 
                 strpos($desc, 'diseño') !== false || 
                 strpos($desc, 'foliadora') !== false || 
                 strpos($desc, 'taza') !== false);
    
    if ($es_manual) {
        return [
            'costo_directo' => 0,
            'papel' => 0,
            'tinta' => 0,
            'detalle' => 'Servicio manual (sin costo de insumos)'
        ];
    }
    
    // ============================================
    // CASO: SERVICIOS DE IMPRESIÓN (lógica existente)
    // ============================================
    // Determinar tipo de papel
    if (strpos($desc, 'fotocopia') !== false || strpos($desc, 'copia') !== false) {
        $papel = $costo_h_bond; 
        $tipo_papel = 'Bond';
    } elseif (strpos($desc, 'foto') !== false || strpos($desc, 'pasaporte') !== false) {
        $copias_por_hoja = obtenerCopiasPorHoja($servicio['descripcion']);
        $papel = $copias_por_hoja > 0 ? $costo_h_foto / $copias_por_hoja : $costo_h_foto;
        $tipo_papel = 'Fotográfico';
    } elseif (strpos($desc, 'cartulina') !== false || $cat == 4) {
        $papel = $costo_h_cart;
        $tipo_papel = 'Cartulina';
    } else {
        $papel = $costo_h_bond;
        $tipo_papel = 'Bond';
    }
    
    // Determinar si es color
    $es_color = (strpos($desc, 'color') !== false || 
                strpos($desc, 'full') !== false || 
                strpos($desc, 'foto') !== false);
    $tinta = $es_color ? $costo_t_cl : $costo_t_bn;
    $tipo_tinta = $es_color ? 'Color' : 'Blanco/Negro';
    
    // Determinar si es doble cara
    $es_doble_cara = (strpos($desc, 'doble cara') !== false || 
                     strpos($desc, 'd/c') !== false ||
                     strpos($desc, 'ambos lados') !== false);
    $caras = $es_doble_cara ? 2 : 1;
    $tipo_cara = $es_doble_cara ? 'Doble cara' : 'Una cara';
    
    $costo_directo = $papel + ($tinta * $caras);
    
    return [
        'costo_directo' => $costo_directo,
        'papel' => $papel,
        'tinta' => $tinta * $caras,
        'detalle' => "Papel: $tipo_papel, Tinta: $tipo_tinta, $tipo_cara"
    ];
}


// --- PROCESAR FILTROS DE FECHA PARA RENTABILIDAD REAL ---
$mes_desde = $_POST['mes_desde'] ?? $mes_actual;
$ano_desde = $_POST['ano_desde'] ?? $anio_actual;
$mes_hasta = $_POST['mes_hasta'] ?? $mes_actual;
$ano_hasta = $_POST['ano_hasta'] ?? $anio_actual;
$tipo_reporte = $_POST['tipo_reporte'] ?? 'consolidado';
$tipo_visualizacion = $_POST['tipo_visualizacion'] ?? 'todos';

// --- PROCESAR FILTROS DE CATÁLOGO Y LIMPIEZA ---
// Valores por defecto para filtros de catálogo
$f_cat = $_GET['filtro_categoria'] ?? $_POST['filtro_categoria'] ?? '';
$f_txt = $_GET['filtro_texto'] ?? $_POST['filtro_texto'] ?? '';

// Procesar limpieza de filtros individuales (GET requests)
if (isset($_GET['quitar_categoria'])) {
    $f_cat = '';
    // Redirigir para actualizar la URL sin el parámetro de limpieza
    $url = "?tipo_reporte=catalogo&mes_desde=$mes_desde&ano_desde=$ano_desde&mes_hasta=$mes_hasta&ano_hasta=$ano_hasta";
    if (!empty($f_txt)) {
        $url .= "&filtro_texto=" . urlencode($f_txt);
    }
    header("Location: $url");
    exit;
}

if (isset($_GET['quitar_texto'])) {
    $f_txt = '';
    // Redirigir para actualizar la URL sin el parámetro de limpieza
    $url = "?tipo_reporte=catalogo&mes_desde=$mes_desde&ano_desde=$ano_desde&mes_hasta=$mes_hasta&ano_hasta=$ano_hasta";
    if (!empty($f_cat)) {
        $url .= "&filtro_categoria=" . urlencode($f_cat);
    }
    header("Location: $url");
    exit;
}

// Procesar limpieza de todos los filtros
if (isset($_GET['limpiar_filtros'])) {
    $f_cat = '';
    $f_txt = '';
    // Redirigir para actualizar la URL sin el parámetro de limpieza
    header("Location: ?tipo_reporte=catalogo&mes_desde=$mes_desde&ano_desde=$ano_desde&mes_hasta=$mes_hasta&ano_hasta=$ano_hasta");
    exit;
}

// Construir fechas para el filtro
$fecha_desde = "$ano_desde-$mes_desde-01 00:00:00";
$fecha_hasta = date('Y-m-t 23:59:59', strtotime("$ano_hasta-$mes_hasta-01"));

// Calcular número de meses en el período
$datetime1 = new DateTime("$ano_desde-$mes_desde-01");
$datetime2 = new DateTime("$ano_hasta-$mes_hasta-01");
$interval = $datetime1->diff($datetime2);
$num_meses = ($interval->y * 12) + $interval->m + 1;


// Gastos fijos totales para el período (acumulado)
$gastos_fijos_acumulados = $gastos_fijos_mensuales * $num_meses;

try {
    // 1. Consulta ÚNICA de todas las líneas de factura del período
    $sql_facturas = "
        SELECT 
            f.id as factura_id, f.no_fact, f.fecha_emision, f.total_general,
            fd.servicio_id, fd.cantidad, fd.precio_unitario, fd.total_linea as total_facturado,
            s.descripcion as servicio_desc, s.codigo as servicio_cod, s.costo as precio_venta,
            cat.descripcion as categoria_nombre, cat.id as categoria_id
        FROM tbl_fact f
        INNER JOIN tbl_fact_detalle fd ON f.id = fd.factura_id
        INNER JOIN clasif_serv s ON fd.servicio_id = s.id
        LEFT JOIN clasif_cat_de_serv cat ON s.categoria_id = cat.id
        WHERE f.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
        AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA')
        -- AND s.categoria_id IN (" . implode(',', $categorias_incluidas) . ")
        ORDER BY f.fecha_emision DESC
    ";
    
    $stmt = $db->prepare($sql_facturas);
    $stmt->execute([':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta]);
    $todas_las_lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Inicializar acumuladores limpios
    $stats_servicios = [];     // CONSOLIDADO (Clave: ID de servicio)
    $facturas_agrupadas = [];  // DETALLADO (Clave: No. Factura)
    $detalle_mensual = [];     // MENSUAL (Clave: Año-Mes)
    
    $total_facturado_periodo = 0;
    $total_costos_directos_periodo = 0;
    $total_ganancia_bruta_periodo = 0;

    // 3. PROCESAR TODAS LAS LÍNEAS EN UN SOLO BUCLE
    foreach ($todas_las_lineas as $linea) {
        $id_serv = (int)$linea['servicio_id'];
        $n_fact = $linea['no_fact'];
        $mes_key = date('Y-m', strtotime($linea['fecha_emision']));

        // --- CÁLCULO DE COSTO DIRECTO ---
        $desc_low = mb_strtolower($linea['servicio_desc']);
        $cat_id = $linea['categoria_id'];
        
        // Lógica de materiales
        $costo_u = 0;
        $es_m = (strpos($desc_low, 'encuadernado') !== false || strpos($desc_low, 'diseño') !== false || strpos($desc_low, 'foliadora') !== false || strpos($desc_low, 'taza') !== false);
        
        if (!$es_m) {
            if (strpos($desc_low, 'fotocopia') !== false || strpos($desc_low, 'copia') !== false) { $p = $costo_h_bond; } 
            elseif (strpos($desc_low, 'foto') !== false || strpos($desc_low, 'pasaporte') !== false) {
                $cph = obtenerCopiasPorHoja($linea['servicio_desc']);
                $p = $cph > 0 ? $costo_h_foto / $cph : $costo_h_foto;
            } 
            elseif (strpos($desc_low, 'cartulina') !== false || $cat_id == 4) { $p = $costo_h_cart; } 
            else { $p = $costo_h_bond; }
            
            $col = (strpos($desc_low, 'color') !== false || strpos($desc_low, 'full') !== false || strpos($desc_low, 'foto') !== false);
            $t = $col ? $costo_t_cl : $costo_t_bn;
            $car = (strpos($desc_low, 'doble cara') !== false || strpos($desc_low, 'd/c') !== false) ? 2 : 1;
            $costo_u = $p + ($t * $car);
        }
        
        $costo_t_linea = $costo_u * $linea['cantidad'];
        $g_bruta_linea = $linea['total_facturado'] - $costo_t_linea;

        // --- A. AGRUPACIÓN PARA EL CONSOLIDADO (EVITA DUPLICADOS) ---
        if (!isset($stats_servicios[$id_serv])) {
            $stats_servicios[$id_serv] = [
                'servicio_id' => $id_serv,
                'codigo' => $linea['servicio_cod'],
                'descripcion' => $linea['servicio_desc'],
                'categoria' => $linea['categoria_nombre'] ?? 'N/A',
                'categoria_id' => $linea['categoria_id'],
                'cantidad' => 0, 'facturado' => 0, 'costo_directo' => 0, 'ganancia_bruta' => 0,
                'precio_unitario' => $linea['precio_venta']
            ];
        }
        // SUMAMOS a la fila existente
        $stats_servicios[$id_serv]['cantidad'] += $linea['cantidad'];
        $stats_servicios[$id_serv]['facturado'] += $linea['total_facturado'];
        $stats_servicios[$id_serv]['costo_directo'] += $costo_t_linea;
        $stats_servicios[$id_serv]['ganancia_bruta'] += $g_bruta_linea;

// --- B. AGRUPACIÓN PARA EL DETALLADO (POR FACTURA) ---
        if (!isset($facturas_agrupadas[$n_fact])) {
            $facturas_agrupadas[$n_fact] = [
                'no_fact' => $n_fact, 
                'fecha_emision' => $linea['fecha_emision'],
                'items' => [], 
                'total_facturado_items' => 0, 
                'total_costo_items' => 0, 
                'total_ganancia_items' => 0
            ];
        }
        // Agregamos todas las llaves que la tabla detallada pide
        $facturas_agrupadas[$n_fact]['items'][] = [
            'servicio_id' => $id_serv,          // Falta esta
            'servicio_cod' => $linea['servicio_cod'], // Falta esta
            'servicio_desc' => $linea['servicio_desc'],
            'cantidad' => $linea['cantidad'], 
            'precio_unitario' => $linea['precio_unitario'], // Falta esta
            'total_facturado' => $linea['total_facturado'], 
            'costo_total' => $costo_t_linea, 
            'ganancia' => $g_bruta_linea
        ];
        $facturas_agrupadas[$n_fact]['total_facturado_items'] += $linea['total_facturado'];
        $facturas_agrupadas[$n_fact]['total_costo_items'] += $costo_t_linea;
        $facturas_agrupadas[$n_fact]['total_ganancia_items'] += $g_bruta_linea;
		
        // --- C. AGRUPACIÓN MENSUAL ---
        if (!isset($detalle_mensual[$mes_key])) {
            $detalle_mensual[$mes_key] = ['facturado' => 0, 'costo_directo' => 0, 'ganancia_bruta' => 0];
        }
        $detalle_mensual[$mes_key]['facturado'] += $linea['total_facturado'];
        $detalle_mensual[$mes_key]['costo_directo'] += $costo_t_linea;
        $detalle_mensual[$mes_key]['ganancia_bruta'] += $g_bruta_linea;

        // TOTALES DEL PERÍODO
        $total_facturado_periodo += $linea['total_facturado'];
        $total_costos_directos_periodo += $costo_t_linea;
        $total_ganancia_bruta_periodo += $g_bruta_linea;
    }

    // 4. CÁLCULOS DE IMPUESTOS Y REPARTO PROPORCIONAL
    $impuesto_ventas_periodo = $total_facturado_periodo * ($tasa_impuesto_ventas / 100);
    $contribucion_local_periodo = $total_facturado_periodo * ($tasa_contribucion_local / 100);
    $total_impuestos_periodo = $impuesto_ventas_periodo + $contribucion_local_periodo;
    $total_ganancia_neta_periodo = $total_ganancia_bruta_periodo - $gastos_fijos_acumulados - $total_impuestos_periodo;

    foreach ($stats_servicios as &$s_item) {
        $prop = ($total_facturado_periodo > 0) ? ($s_item['facturado'] / $total_facturado_periodo) : 0;
        $s_item['gastos_fijos_asignados'] = $gastos_fijos_acumulados * $prop;
        $s_item['impuestos_asignados'] = $total_impuestos_periodo * $prop;
        $s_item['ganancia_neta'] = $s_item['ganancia_bruta'] - $s_item['gastos_fijos_asignados'] - $s_item['impuestos_asignados'];
    }

    $facturas_unicas = count($facturas_agrupadas);
    $stats_filtrados = $stats_servicios; // Este array ya no tiene repetidos
    $facturas_periodo = $todas_las_lineas; // Para compatibilidad de otros conteos

} catch (Exception $e) {

   // Calcular impuestos sobre ingresos (configurables)
    $impuesto_ventas_periodo = $total_facturado_periodo * ($tasa_impuesto_ventas / 100);
    $contribucion_local_periodo = $total_facturado_periodo * ($tasa_contribucion_local / 100);
    $total_impuestos_periodo = $impuesto_ventas_periodo + $contribucion_local_periodo;
    
    // Calcular ganancia neta (restando gastos fijos acumulados e impuestos)
    $total_ganancia_neta_periodo = $total_ganancia_bruta_periodo - $gastos_fijos_acumulados - $total_impuestos_periodo;
    
    // Distribuir gastos fijos e impuestos proporcionalmente entre servicios
    foreach ($stats_servicios as $serv_id => &$stat) {
        if ($total_facturado_periodo > 0) {
            $proporcion = $stat['facturado'] / $total_facturado_periodo;
            $stat['gastos_fijos_asignados'] = $gastos_fijos_acumulados * $proporcion;
            $stat['impuestos_asignados'] = $total_impuestos_periodo * $proporcion;
            $stat['ganancia_neta'] = $stat['ganancia_bruta'] - $stat['gastos_fijos_asignados'] - $stat['impuestos_asignados'];
        } else {
            $stat['gastos_fijos_asignados'] = 0;
            $stat['impuestos_asignados'] = 0;
            $stat['ganancia_neta'] = $stat['ganancia_bruta'];
        }
    }
    
    // Ordenar servicios por ganancia neta
    uasort($stats_servicios, function($a, $b) {
        return $a['ganancia_neta'] <=> $b['ganancia_neta'];
    });
    
    // Ordenar meses cronológicamente
    ksort($detalle_mensual);
    
    // Calcular total de facturas únicas
    $facturas_unicas = count(array_unique(array_column($facturas_periodo, 'id')));
    
} catch (Exception $e) {
    error_log("Error en consulta de facturas: " . $e->getMessage());
    $facturas_periodo = [];
    $stats_servicios = [];
    $detalle_mensual = [];
    $total_facturado_periodo = 0;
    $total_costos_directos_periodo = 0;
    $total_ganancia_bruta_periodo = 0;
    $total_ganancia_neta_periodo = 0;
    $impuesto_ventas_periodo = 0;
    $contribucion_local_periodo = 0;
    $total_impuestos_periodo = 0;
    $facturas_unicas = 0;
}

$f_cat = $_POST['filtro_categoria'] ?? '';
$f_txt = $_POST['filtro_texto'] ?? '';

// Obtener años disponibles
try {
    $sql_anos = "SELECT DISTINCT YEAR(fecha_emision) as ano FROM tbl_fact ORDER BY ano DESC";
    $anos_disponibles = $db->query($sql_anos)->fetchAll(PDO::FETCH_COLUMN);
    if (empty($anos_disponibles)) {
        $anos_disponibles = [date('Y')];
    }
} catch (Exception $e) {
    $anos_disponibles = [date('Y')];
}

// Obtener servicios para el combo de fichas - SIN FILTROS
try {
    $q = "SELECT s.id, s.codigo, s.descripcion, s.costo, s.categoria_id,
                 c.descripcion as cat_nombre 
          FROM clasif_serv s 
          INNER JOIN clasif_cat_de_serv c ON s.categoria_id = c.id 
          -- WHERE s.activo = 1 
          -- AND s.categoria_id IN (" . implode(',', $categorias_incluidas) . ") 
          ORDER BY c.descripcion, s.descripcion";
    
    $servicios = $db->query($q)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) { 
    error_log("Error en consulta de servicios: " . $e->getMessage());
    $servicios = []; 
}

// ============================================
// PROCESAR CATÁLOGO DE SERVICIOS
// ============================================
$servicios_catalogo = [];
$total_precio_venta_catalogo = 0;
$total_costo_directo_catalogo = 0;
$total_utilidad_bruta_catalogo = 0;
$servicios_con_perdida_catalogo = 0;

if ($tipo_reporte == 'catalogo') {
    try {
        $sql_catalogo = "SELECT s.*, c.descripcion as cat_nombre
                        FROM clasif_serv s
                        INNER JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                        -- WHERE s.activo = 1
                        -- AND s.categoria_id IN (" . implode(',', $categorias_incluidas) . ")";
        
        if (!empty($f_cat)) {
            $sql_catalogo .= " AND s.categoria_id = " . intval($f_cat);
        }
        
        if (!empty($f_txt)) {
            $txt = $db->quote("%$f_txt%");
            $sql_catalogo .= " AND (s.descripcion LIKE $txt OR s.codigo LIKE $txt)";
        }
        
        $sql_catalogo .= " ORDER BY c.descripcion, s.descripcion";
        
        $servicios_db = $db->query($sql_catalogo)->fetchAll(PDO::FETCH_ASSOC);
        
        // ============================================
        // PROCESAR CADA SERVICIO CON SU LÓGICA ESPECÍFICA
        // ============================================
        foreach ($servicios_db as $serv) {
            $id = $serv['id'];
            $precio_venta = floatval($serv['costo']);
            $desc_low = mb_strtolower($serv['descripcion']);
            $cat_id = $serv['categoria_id'];
            
            // ===== 1. CASO ESPECIAL: MICAS PLÁSTICAS (IDs 29 y 30) =====
            if ($id == 29 || $id == 30) {
                $es_carta = ($id == 30); // ID 30 es mica carta
                $costo_mica = $es_carta ? ($costo_mica_carta ?? 4.00) : ($costo_mica_carnet ?? 1.00);
                
                $costo_directo = $costo_mica;
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = $es_carta ? 'Mica tamaño carta' : 'Mica tamaño carnet/solapín';
                $tipo_servicio = 'Mica plástica';
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => 'Mica plástica'
                ];
            }
            
            // ===== 2. CASO ESPECIAL: CREDENCIALES (IDs 1 y 2) =====
            elseif ($id == 1 || $id == 2) {
                $costo_mica = $costo_mica_carnet ?? 1.00;
                $costo_colgante = 50.00; // Costo del colgante
                
                if ($id == 2) {
                    $costo_directo = $costo_mica + $costo_colgante;
                    $detalle_completo = "Mica carnet + Colgante";
                } else {
                    $costo_directo = $osto_mica;
                    $detalle_completo = "Mica carnet";
                }
                
                $utilidad_bruta = $precio_venta - $costo_directo;
                $tipo_servicio = "Credencial";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 3. CASO ESPECIAL: DIPLOMAS (ID 3) =====
            elseif ($id == 3) {
                $costo_cartulina = $costo_h_cart;
                $costo_vinilo = 80.00; // Costo del vinilo para forrar
                
                $costo_directo = $costo_cartulina + $costo_vinilo;
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = "Cartulina + Vinilo forro";
                $tipo_servicio = "Diploma";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => $costo_cartulina,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 4. CASO ESPECIAL: ENCUADERNADO (ID 5) =====
            elseif ($id == 5) {
                $costo_anillado = 120.00;
                $costo_tapa = 200.00;
                
                $costo_directo = $costo_anillado + $costo_tapa;
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = "Anillado + Tapa dura";
                $tipo_servicio = "Encuadernado";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 5. CASO ESPECIAL: GRABADO DE TAZA (ID 12) =====
            elseif ($id == 12) {
                $costo_taza = 1200.00;
                $costo_tinta_sublimacion = 400.00;
                
                $costo_directo = $costo_taza + $costo_tinta_sublimacion;
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = "Taza blanca + Tinta sublimación";
                $tipo_servicio = "Grabado";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 6. CASO ESPECIAL: TARJETAS (IDs 65,66) =====
            elseif ($id == 65 || $id == 66) {
                // Tarjetas pequeñas - asumimos papel bond (varias por hoja)
                $costo_directo = $costo_h_bond / 10; // Aprox 10 tarjetas por hoja
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = "Papel Bond (10 tarjetas/hoja)";
                $tipo_servicio = "Tarjeta";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => $costo_h_bond / 10,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 7. CASO ESPECIAL: CARNÉS DE VACUNACIÓN (IDs 67,68) =====
            elseif ($id == 67 || $id == 68) {
                $costo_directo = $costo_h_cart; // Cartulina
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = "Cartulina impresa";
                $tipo_servicio = "Carné";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => $costo_h_cart,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 8. PRODUCTOS DE VENTA (Categorías 8, 9, 10, 11, 12) CON MÁRGENES DINÁMICOS =====
            elseif (in_array($cat_id, [8, 9, 10, 11, 12])) {
                
                // Determinar margen según categoría y excepciones
                switch($cat_id) {
                    case 8: // Utiles/Papeleria/Oficina 
                        if (strpos($desc_low, 'cuño') !== false || 
                            $id == 60 || $id == 63 || $id == 64 || $id == 69 || $id == 70) {
                            $margen_costo = ($_SESSION['margen_cat8_cunos'] ?? 40) / 100;
                            $tipo_servicio = "Manufactura (Cuño)";
                            $detalle_completo = "Costo fabricación - " . ($_SESSION['margen_cat8_cunos'] ?? 40) . "%";
                        }
                        elseif ($id == 59) { // Tinta ID 59
                            $margen_costo = ($_SESSION['margen_cat8_tinta'] ?? 70) / 100;
                            $tipo_servicio = "Insumo (Tinta)";
                            $detalle_completo = "Costo adquisición - " . ($_SESSION['margen_cat8_tinta'] ?? 70) . "%";
                        }
                        else {
                            $margen_costo = ($_SESSION['margen_cat8_general'] ?? 75) / 100;
                            $tipo_servicio = "Producto Retail";
                            $detalle_completo = "Costo general cat.8 - " . ($_SESSION['margen_cat8_general'] ?? 75) . "%";
                        }
                        break;
                        
                    case 9: // Alimentos/Bebidas
                        $margen_costo = ($_SESSION['margen_cat9_general'] ?? 75) / 100;
                        $tipo_servicio = "Producto Retail";
                        $detalle_completo = "Costo general cat.9 - " . ($_SESSION['margen_cat9_general'] ?? 75) . "%";
                        break;
                        
                    case 10: // Tecnología
                        $margen_costo = ($_SESSION['margen_cat10_general'] ?? 80) / 100;
                        $tipo_servicio = "Equipo Electrónico";
                        $detalle_completo = "Costo general cat.10 - " . ($_SESSION['margen_cat10_general'] ?? 80) . "%";
                        break;
                        
                    case 11: // Piezas/Repuestos
                        $margen_costo = ($_SESSION['margen_cat11_general'] ?? 75) / 100;
                        $tipo_servicio = "Pieza/Repuesto";
                        $detalle_completo = "Costo general cat.11 - " . ($_SESSION['margen_cat11_general'] ?? 75) . "%";
                        break;
                        
                    case 12: // Artículos de Limpieza
                        $margen_costo = ($_SESSION['margen_cat12_general'] ?? 70) / 100;
                        $tipo_servicio = "Artículo Limpieza";
                        $detalle_completo = "Costo general cat.12 - " . ($_SESSION['margen_cat12_general'] ?? 70) . "%";
                        break;
                        
                    default:
                        $margen_costo = 0.75;
                        $tipo_servicio = "Producto General";
                        $detalle_completo = "Costo estimado 75%";
                }
                
                $costo_directo = $precio_venta * $margen_costo;
                $utilidad_bruta = $precio_venta - $costo_directo;
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== 9. SERVICIOS DE IMPRESIÓN (categorías 1,2,3,4,6) =====
            elseif (in_array($cat_id, [1, 2, 3, 4, 6]) || 
                    strpos($desc_low, 'impresión') !== false || 
                    strpos($desc_low, 'fotocopia') !== false ||
                    strpos($desc_low, 'copia') !== false ||
                    strpos($desc_low, 'foto') !== false) {
                
                $costos = calcularCostoServicioCatalogo(
                    $serv, 
                    $costo_h_bond, 
                    $costo_h_cart, 
                    $costo_h_foto, 
                    $costo_t_bn, 
                    $costo_t_cl
                );
                
                $costo_directo = $costos['costo_directo'];
                $utilidad_bruta = $precio_venta - $costo_directo;
                
                // Generar detalle según tipo de impresión
                if (strpos($desc_low, 'fotocopia') !== false || strpos($desc_low, 'copia') !== false) {
                    $tipo_servicio = "Fotocopia";
                    $detalle_completo = $costos['detalle'];
                } elseif (strpos($desc_low, 'foto') !== false) {
                    $tipo_servicio = "Fotografía";
                    $copias_por_hoja = obtenerCopiasPorHoja($serv['descripcion']);
                    $detalle_completo = "Papel Fotográfico | " . ($copias_por_hoja > 1 ? "$copias_por_hoja fotos/hoja" : "1 foto/hoja");
                } elseif (strpos($desc_low, 'cartulina') !== false) {
                    $tipo_servicio = "Impresión Cartulina";
                    $detalle_completo = $costos['detalle'];
                } else {
                    $tipo_servicio = "Impresión";
                    $detalle_completo = $costos['detalle'];
                }
            }
            
            // ===== 10. SERVICIOS MANUALES (sin costo de materiales) =====
            elseif (strpos($desc_low, 'encuadernado') !== false || 
                    strpos($desc_low, 'diseño') !== false || 
                    strpos($desc_low, 'foliadora') !== false || 
                    strpos($desc_low, 'taza') !== false ||
                    strpos($desc_low, 'grabar') !== false ||
                    strpos($desc_low, 'sublimación') !== false ||
                    strpos($desc_low, 'trámites') !== false ||
                    $id == 4 || $id == 35 || $id == 36) {
                
                $costo_directo = 0;
                $utilidad_bruta = $precio_venta;
                $detalle_completo = "Servicio manual (sin costo de insumos)";
                $tipo_servicio = "Manual";
                
                $costos = [
                    'costo_directo' => 0,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => 'Servicio manual'
                ];
            }
            
            // ===== 11. PRODUCTOS CON COSTO FIJO (por defecto) =====
            else {
                // Si no entra en ninguna categoría especial, asumimos margen del 70%
                $margen_costo = 0.70;
                $costo_directo = $precio_venta * $margen_costo;
                $utilidad_bruta = $precio_venta - $costo_directo;
                $detalle_completo = "Costo estimado 70% (por defecto)";
                $tipo_servicio = "Producto General";
                
                $costos = [
                    'costo_directo' => $costo_directo,
                    'papel' => 0,
                    'tinta' => 0,
                    'detalle' => $detalle_completo
                ];
            }
            
            // ===== AGREGAR A LA LISTA DEL CATÁLOGO =====
            $servicios_catalogo[] = [
                'id' => $serv['id'],
                'codigo' => $serv['codigo'],
                'descripcion' => $serv['descripcion'],
                'costo' => $precio_venta,
                'cat_nombre' => $serv['cat_nombre'],
                'costo_directo_calculado' => $costo_directo,
                'papel' => $costos['papel'] ?? 0,
                'tinta' => $costos['tinta'] ?? 0,
                'detalle_costo' => $costos['detalle'] ?? '',
                'detalle_completo' => $detalle_completo,
                'tipo_servicio' => $tipo_servicio,
                'utilidad_bruta' => $utilidad_bruta,
                'margen_bruto' => $precio_venta > 0 ? ($utilidad_bruta / $precio_venta) * 100 : 0
            ];
            
            $total_precio_venta_catalogo += $precio_venta;
            $total_costo_directo_catalogo += $costo_directo;
            $total_utilidad_bruta_catalogo += $utilidad_bruta;
            
            if ($utilidad_bruta < 0) {
                $servicios_con_perdida_catalogo++;
            }
        }
        
        // Ordenar por utilidad bruta (de menor a mayor)
        usort($servicios_catalogo, function($a, $b) {
            return $a['utilidad_bruta'] <=> $b['utilidad_bruta'];
        });
        
    } catch (Exception $e) {
        error_log("Error en catálogo: " . $e->getMessage());
        $servicios_catalogo = [];
    }
}

// Filtrar stats_servicios para asegurar solo categorías incluidas
$stats_filtrados = [];
foreach ($stats_servicios as $serv_id => $stat) {
    if (in_array($stat['categoria_id'], $categorias_incluidas)) {
        $stats_filtrados[$serv_id] = $stat;
    }
}

$logo_path = 'assets/logov.png';
$logo_base64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<html lang="es" data-theme="<?= $tema_windows; ?>" data-accent="<?= $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Rentabilidad y Costos - SISFACT PDL VISIONES</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <link rel="stylesheet" href="css/windows11.css">
	
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <style>
        :root {
            --win-bg-primary: <?php echo $tema_actual['bg_primary']; ?>;
            --win-bg-secondary: <?php echo $tema_actual['bg_secondary']; ?>;
            --win-bg-tertiary: <?php echo $tema_actual['bg_tertiary']; ?>;
            --win-text-primary: <?php echo $tema_actual['text_primary']; ?>;
            --win-text-secondary: <?php echo $tema_actual['text_secondary']; ?>;
            --win-border-color: <?php echo $tema_actual['border_color']; ?>;
            --win-accent: <?php echo $tema_actual['accent_color']; ?>;
            --win-accent-light: <?php echo $tema_actual['accent_color']; ?>20;
            --win-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            --win-radius: 8px;
            --win-radius-sm: 6px;
            --win-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Dropdown & Modal */
        .dropdown-menu { background-color: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius); }
        .dropdown-item { color: var(--win-text-primary); transition: var(--win-transition); border-radius: var(--win-radius-sm); margin: 2px 4px; }
        .dropdown-item:hover { background-color: var(--win-accent-light); color: var(--win-accent); }

        body { background-color: var(--win-bg-primary); color: var(--win-text-primary); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        .win-navbar { height: 48px; background: var(--win-bg-secondary); border-bottom: 1px solid var(--win-border-color); position: fixed; top: 0; width: 100%; z-index: 1000; display: flex; align-items: center; padding: 0 16px; gap: 12px; }
        .win-sidebar { width: 260px; background: var(--win-bg-secondary); border-right: 1px solid var(--win-border-color); height: calc(100vh - 48px); position: fixed; top: 48px; transition: 0.3s; z-index: 999; overflow-y: auto; padding: 16px 0; }
        .win-sidebar.mini { width: 68px; }
        .win-main-content { margin-left: 260px; margin-top: 48px; padding: 24px; transition: 0.3s; min-height: calc(100vh - 48px); }
        .win-main-content.sidebar-mini { margin-left: 68px; }
        .card { background: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: 8px; margin-bottom: 1.5rem; }
        .card-header { background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color); color: var(--win-text-primary); padding: 12px 16px; }
        .text-muted, .form-text, .small { color: var(--win-text-secondary) !important; opacity: 1 !important; }
        .mica-effect { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px) saturate(180%); }
        .win-nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--win-text-secondary); text-decoration: none; border-radius: 6px; margin: 2px 8px; transition: 0.2s; font-size: 14px; }
        .win-nav-link:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }
        .win-nav-link.active { background: var(--win-accent-light); color: var(--win-accent); border-left: 3px solid var(--win-accent); }
        .win-nav-badge { margin-left: auto; background: var(--win-accent); color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; }
        .fila-perdida { background-color: rgba(220, 53, 69, 0.1) !important; border-left: 4px solid #dc3545 !important; }
        .fila-ganancia { background-color: rgba(40, 167, 69, 0.05) !important; border-left: 4px solid #28a745 !important; }
        .valor-negativo { color: #ff8a8a !important; font-weight: bold; }
        .valor-positivo { color: #8aff8a !important; font-weight: bold; }
        .resumen-card { background: linear-gradient(145deg, var(--win-bg-tertiary), var(--win-bg-secondary)); border: 1px solid var(--win-border-color); border-radius: 10px; padding: 20px; }
        .stat-value { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 12px; color: var(--win-text-secondary); text-transform: uppercase; }
        .badge-perdida { background: #dc3545; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        .badge-ganancia { background: #28a745; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px; }
        .filtro-fecha { background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .nav-tabs .nav-link { color: var(--win-text-secondary); border: none; }
        .nav-tabs .nav-link.active { background: var(--win-accent-light); color: var(--win-accent); border-bottom: 2px solid var(--win-accent); }
        .btn-group-filtro { display: flex; gap: 5px; margin-bottom: 20px; }
        .btn-filtro { flex: 1; border-radius: 20px !important; }
        .impresora-item { background: var(--win-bg-tertiary); border-radius: 8px; padding: 10px; margin-bottom: 10px; border: 1px solid var(--win-border-color); }
        .form-label-header { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--win-text-secondary); margin-bottom: 4px; font-weight: 600; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 15px; padding-bottom: 5px; border-bottom-width: 2px; border-bottom-style: solid; }
        .input-group-text { background-color: var(--win-bg-tertiary); color: var(--win-text-primary); border-color: var(--win-border-color); }
        .bg-dark-custom { background-color: var(--win-bg-tertiary); }
        .catalogo-badge { background: #17a2b8; color: white; font-size: 10px; padding: 3px 8px; border-radius: 20px; }
        .detalle-costo { font-size: 10px; color: var(--win-text-secondary); }
        th[onclick] { cursor: pointer; transition: background-color 0.2s; position: relative; padding-right: 20px !important; }
        th[onclick]:hover { background-color: var(--win-accent-light) !important; }
        th.sort-asc::after, th.sort-desc::after { content: ''; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border-left: 5px solid transparent; border-right: 5px solid transparent; }
        th.sort-asc::after { border-bottom: 5px solid var(--win-accent); border-top: none; }
        th.sort-desc::after { border-top: 5px solid var(--win-accent); border-bottom: none; }
        @media (max-width: 992px) { .win-sidebar { transform: translateX(-100%); } .win-sidebar.open { transform: translateX(0); } .win-main-content { margin-left: 0; } }
        
        /* Efectos de parpadeo para alertas visuales */
        @keyframes blinkWarning {
            0% { opacity: 1; background-color: rgba(255, 193, 7, 0); }
            50% { opacity: 0.7; background-color: rgba(255, 193, 7, 0.2); }
            100% { opacity: 1; background-color: rgba(255, 193, 7, 0); }
        }

        @keyframes blinkDanger {
            0% { opacity: 1; background-color: rgba(220, 53, 69, 0); }
            25% { opacity: 0.8; background-color: rgba(220, 53, 69, 0.3); }
            50% { opacity: 1; background-color: rgba(220, 53, 69, 0.5); }
            75% { opacity: 0.8; background-color: rgba(220, 53, 69, 0.3); }
            100% { opacity: 1; background-color: rgba(220, 53, 69, 0); }
        }

        .blink-warning {
            animation: blinkWarning 1.5s ease-in-out infinite;
            border: 2px solid #ffc107 !important;
        }

        .blink-danger {
            animation: blinkDanger 1s ease-in-out infinite;
            border: 2px solid #dc3545 !important;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.5);
        }

        .blink-warning .stat-value,
        .blink-danger .stat-value {
            position: relative;
            z-index: 2;
        }

        .blink-warning .valor-negativo,
        .blink-danger .valor-negativo {
            color: #ff8a8a !important;
            font-weight: bold;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }
@media print {
    tr { page-break-inside: avoid !important; }
    .card { border: none !important; box-shadow: none !important; }
    /* Asegurar que el fondo oscuro se capture si usas tema oscuro */
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

/* Clase de ayuda para html2pdf */
.html2pdf__page-break {
    page-break-before: always;
}

    </style>
</head>
<body>
    <!-- Theme Panel -->
    <div class="win-theme-overlay" id="themeOverlay" onclick="cerrarPanelTemas()"></div>
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>
        <div class="win-theme-options mb-4">
            <div class="win-theme-option mb-2 <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" data-theme="dark"><i class="fas fa-moon mb-2"></i><div>Oscuro</div></div>
            <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" data-theme="light"><i class="fas fa-sun mb-2"></i><div>Claro</div></div>
        </div>
        <h6 class="mb-3" style="color: var(--win-text-primary);">Color de acento</h6>
        <div class="win-color-options mb-4">
            <?php foreach ($colores_accent as $color => $nombre): ?>
                <div class="win-color-option <?php echo $color_accent == $color ? 'active' : ''; ?>" style="background-color: <?php echo $color; ?>;" data-color="<?php echo $color; ?>" title="<?php echo $nombre; ?>"></div>
            <?php endforeach; ?>
        </div>
        <h6 class="mb-3" style="color: var(--win-text-primary);">Opciones de interfaz</h6>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini" <?php echo $sidebar_mini ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleSidebarMini" style="color: var(--win-text-primary);">Sidebar compacto</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleAnimations" checked>
            <label class="form-check-label" for="toggleAnimations" style="color: var(--win-text-primary);">Animaciones</label>
        </div>
        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()"><i class="fas fa-save"></i> Guardar cambios</button>
    </div>
    
    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">SISFACT PDL Visiones</span>
        </div>
        
        <!---<div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>--->
        
        <div style="flex: 1;">
		                <div class="input-group input-group-sm" style="width:540px;">
                    <select class="form-select bg-dark text-white border-secondary" id="selectFicha">
                        <option value="">Ficha de Costo para...</option>
                        <?php foreach($servicios as $s): ?>
                            <option value="<?= $s['id'] ?>" data-codigo="<?= $s['codigo'] ?>" data-descripcion="<?= htmlspecialchars($s['descripcion']) ?>" data-precio="<?= $s['costo'] ?>" data-categoria="<?= $s['cat_nombre'] ?>">
                                <?= $s['codigo'] ?> - <?= $s['descripcion'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-info text-white" title="Generar Ficha de Costo" data-bs-toggle="tooltip" onclick="imprimirFichaServicio()"><i class="fas fa-file-invoice-dollar"></i></button>
                </div>
		</div>
        
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
        <?= renderNotificationsDropdown() ?>
        
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="d-none d-md-inline" style="color: var(--win-text-primary);">
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-chevron-down ms-1 small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px;">
                <li class="dropdown-header px-3 py-2">
                    <div class="d-flex align-items-center">
                        <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0" style="color: var(--win-text-primary); font-size: 14px;">
                                <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 12px;">
                                <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?>
                            </small>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Dashboard</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Panel principal</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="perfil.php">
                        <i class="fas fa-user me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Mi Perfil</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Ver y editar tu información</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="configuracion.php">
                        <i class="fas fa-cog me-3 text-secondary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Configuración</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Preferencias del sistema</small>
                        </div>
                    </a>
                </li>
                
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 text-warning" href="bloquear_sesion.php">
                        <i class="fas fa-lock me-3" style="width: 20px;"></i>
                        <div>
                            <span class="d-block fw-bold">Bloquear Sesión</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Bloquear pantalla temporalmente</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i>
                        <div>
                            <span class="d-block fw-bold">Cerrar Sesión</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small>
                        </div>
                    </a>
                </li>
                
                <?php $ultimo_acceso_texto = obtenerUltimoAcceso($_SESSION['usuario_id'] ?? 0);?>
				<li class="dropdown-footer px-3 py-2 mt-1">
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-shield-alt me-1"></i> Sesión segura
					</small>
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-clock me-1"></i>Último acceso: 
						<span class="fw-bold text-info"><?php echo $ultimo_acceso_texto; ?></span>
					</small>
				</li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar inmersivo -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
        <a href="perfil.php" style="text-decoration: none; display: block;">
            <div class="win-sidebar-header">
                <div class="win-sidebar-user">
                    <div class="win-sidebar-user-avatar">
                        <?php if (!empty($usuario['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="win-sidebar-user-info">
                        <h6 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></h6>
                        <small style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></small>
                    </div>
                </div>
            </div>
        </a>
        <ul class="win-nav">
			<li class="win-nav-item">
				<a href="dashboard.php" class="win-nav-link">
					<i class="win-nav-icon fas fa-tachometer-alt"></i>
					<!-- Badge con F/Cierre y Tooltip -->
					Dashboard<span class="win-nav-badge" 
						  title="Fecha de Cierre Actual: <?php echo fechaInicioFormateada(13); ?>" 
						  data-bs-toggle="tooltip" 
						  data-bs-placement="auto"
						  style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
						F/Cierre: <?php echo fechaInicioFormateada(9); ?>
					</span>
				</a>
			</li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo $total_categorias; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $servicios_count; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo $total_usuarios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="reportes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-chart-bar"></i>
                    <span class="win-nav-text">Reportes</span>
					<span class="win-nav-badge">17</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="rentabilidad.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-money-bill-trend-up"></i>
                    <span class="win-nav-text">Rentabilidad y Costos</span>
                </a>
            </li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;"><?php echo $sidebar_mini ? '...' : 'Configuración'; ?></small></li>
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
					<span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="configuracion.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-cog"></i>
                    <span class="win-nav-text">Configuración</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="historico_view.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-history"></i>
                    <span class="win-nav-text">Histórico</span>
                    <span class="win-nav-badge"><?php echo $estadisticas['total']; ?></span>
                </a>
            </li>
        </ul>
        
        <?php
        // Obtener datos globales de facturación (CUP)
        $finanzas = Database::getProgresoFinanciero();
        ?>
        <div class="mt-4 px-3">
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo date('M Y');?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;">
                        <?php echo $finanzas['mensaje']; ?>
                    </small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                    <?php echo number_format($finanzas['porcentaje'], 1); ?>%
                </h5>
            </div>

            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>; transition: width 1s ease-in-out;" 
                     aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <small class="text-muted" style="font-size: 12px;">
                        <strong>$<?php echo number_format($finanzas['real'], 2); ?></strong>
                    </small>
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;">
                        <i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas
                    </small>
                </div>
                <small class="text-end text-success" style="font-size: 12px;">
                    Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?>
                </small>
            </div>
        </div>
    </aside>

    <main class="win-main-content <?= $sidebar_mini ? 'sidebar-mini' : ''; ?>">
<div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
    <div>
        <h2 class="fw-bold mb-0 text-white">Análisis de Rentabilidad y Costos</h2>
		<p class="text-muted mb-0">Fecha de Cierre Operaciones: <span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span></p>
        <p class="text-muted mb-0">
            Categorías incluidas: 1,2,3,4,6 | 
            Gastos fijos: Salario + Electricidad + Transportación + Móviles + Depreciación + Seg.Social | 
            Indirectos: <?= $porcentaje_indirectos ?>%
        </p>
    </div>
    <div class="d-flex gap-2">
        <div class="input-group input-group-sm" style="width:340px;">
            <!-- Este div está vacío en tu código actual -->
        </div>
        
        <!-- Dropdown de Exportación -->
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownExportar" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-download me-2"></i>Exportar e Imprimir
            </button>
<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownExportar">
    <li><h6 class="dropdown-header">Opciones de exportación/Impresión</h6></li>
    <li><a class="dropdown-item" href="#" onclick="imprimirReporteReal()"><i class="fas fa-print me-2 text-primary"></i>Imprimir Reporte</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item" href="#" onclick="exportarRentabilidad('pdf')"><i class="fas fa-file-pdf me-2 text-danger"></i>Exportar a PDF</a></li>
    <li><a class="dropdown-item" href="#" onclick="exportarRentabilidad('word')"><i class="fas fa-file-word me-2 text-primary"></i>Exportar a Word</a></li>
    <li><a class="dropdown-item" href="#" onclick="exportarRentabilidad('excel')"><i class="fas fa-file-excel me-2 text-success"></i>Exportar a Excel</a></li>
    <li><a class="dropdown-item" href="#" onclick="exportarRentabilidad('csv')"><i class="fas fa-file-csv me-2 text-info"></i>Exportar a CSV</a></li>
    <li><a class="dropdown-item" href="#" onclick="exportarRentabilidad('txt')"><i class="fas fa-file-alt me-2 text-secondary"></i>Exportar a TXT</a></li>
</ul>
        </div>
    </div>
</div>


        <!-- TARJETAS DE RESUMEN CON EFECTO DE PARPADEO -->
        <div class="row mb-4">
            <?php if ($tipo_reporte == 'catalogo'): ?>
                <!-- Resumen para Catálogo -->
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Total Servicios</div>
                        <div class="stat-value text-white"><?= count($servicios_catalogo) ?></div>
                        <small class="text-muted">En catálogo activo</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Precio Venta Total</div>
                        <div class="stat-value text-white">$ <?= number_format($total_precio_venta_catalogo, 2) ?></div>
                        <small class="text-muted">Suma de precios de venta</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Costo Directo Total</div>
                        <div class="stat-value text-white">$ <?= number_format($total_costo_directo_catalogo, 2) ?></div>
                        <small class="text-muted">Según insumos actuales</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Utilidad Bruta Total</div>
                        <div class="stat-value <?= $total_utilidad_bruta_catalogo >= 0 ? 'valor-positivo' : 'valor-negativo' ?>">
                            $ <?= number_format($total_utilidad_bruta_catalogo, 2) ?>
                        </div>
                        <small class="text-muted"><?= $total_precio_venta_catalogo > 0 ? number_format(($total_utilidad_bruta_catalogo/$total_precio_venta_catalogo)*100, 1) : 0 ?>% margen</small>
                    </div>
                </div>
            <?php else: ?>
                <!-- Resumen para reportes con facturación - CON EFECTO DE PARPADEO -->
                <?php 
                $margen_neto_calculado = $total_facturado_periodo > 0 ? ($total_ganancia_neta_periodo / $total_facturado_periodo) * 100 : 0;
                $debe_parpadear = ($total_ganancia_neta_periodo <= 0) || ($margen_neto_calculado <= 0);
                $clase_parpadeo = '';
                if ($debe_parpadear) {
                    $clase_parpadeo = ($total_ganancia_neta_periodo < 0) ? 'blink-danger' : 'blink-warning';
                }
                ?>
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Total Facturado</div>
                        <div class="stat-value text-white">$ <?= number_format($total_facturado_periodo, 2) ?></div>
                        <small class="text-muted">Período: <?= $num_meses ?> <?= $num_meses == 1 ? 'mes' : 'meses' ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Costos Directos</div>
                        <div class="stat-value text-white">$ <?= number_format($total_costos_directos_periodo, 2) ?></div>
                        <small class="text-muted">Insumos + Tinta</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="resumen-card">
                        <div class="stat-label">Gastos Fijos + Impuestos</div>
                        <div class="stat-value text-warning">$ <?= number_format($gastos_fijos_acumulados + $total_impuestos_periodo, 2) ?></div>
                        <small class="text-muted">Fijos: $<?= number_format($gastos_fijos_acumulados, 0) ?> + Imp: $<?= number_format($total_impuestos_periodo, 0) ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="resumen-card <?= $clase_parpadeo ?>" id="tarjetaGanancia">
                        <div class="stat-label">Ganancia/Pérdida Neta</div>
                        <div class="stat-value <?= $total_ganancia_neta_periodo >= 0 ? 'valor-positivo' : 'valor-negativo' ?>">
                            $ <?= number_format($total_ganancia_neta_periodo, 2) ?>
                        </div>
                        <small class="text-muted">
                            <?= number_format($margen_neto_calculado, 1) ?>% margen neto
                            <?php if ($debe_parpadear): ?>
                                <span class="badge bg-danger ms-2">¡ALERTA!</span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
		
		
<div class="row">
	<div class="col-md-12">
        <!-- FILTROS CON OPCIÓN DE COLAPSAR -->
        <div class="card bg-dark border-secondary mb-3">
            <div class="card-header bg-dark border-secondary py-2 d-flex justify-content-between align-items-center" 
                 data-bs-toggle="collapse" 
                 data-bs-target="#filtrosCollapse" 
                 style="cursor: pointer;">
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fas fa-filter me-2 text-primary"></i>Área de Filtros del Reporte
                </h6>
                <i class="fas fa-chevron-down text-secondary" id="toggleIcon"></i>
            </div>
            <div class="collapse" id="filtrosCollapse">
                <div class="card-body">
                    <!-- FILTRO DE FECHAS -->
                    <div class="filtro-fecha">
                        <form method="POST" id="formFechas" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Tipo Reporte</label>
                                <select name="tipo_reporte" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="consolidado" <?= $tipo_reporte == 'consolidado' ? 'selected' : '' ?>>Consolidado (con facturación)</option>
                                    <option value="mensual" <?= $tipo_reporte == 'mensual' ? 'selected' : '' ?>>Por Mes (con facturación)</option>
                                    <option value="detallado" <?= $tipo_reporte == 'detallado' ? 'selected' : '' ?>>Detallado (con facturación)</option>
                                    <option value="catalogo" <?= $tipo_reporte == 'catalogo' ? 'selected' : '' ?>>📋 CATÁLOGO DE SERVICIOS (sin facturación)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Desde Mes</label>
                                <select name="mes_desde" class="form-select form-select-sm">
                                    <?php
                                    $meses_espanol_select = [
                                        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
                                        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
                                        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                                    ];
                                    foreach($meses_espanol_select as $num_mes => $nombre_mes): 
                                    ?>
                                        <option value="<?= $num_mes ?>" <?= $mes_desde == $num_mes ? 'selected' : '' ?>>
                                            <?= $nombre_mes ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Desde Año</label>
                                <select name="ano_desde" class="form-select form-select-sm">
                                    <?php foreach($anos_disponibles as $ano): ?>
                                        <option value="<?= $ano ?>" <?= $ano_desde == $ano ? 'selected' : '' ?>><?= $ano ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Hasta Mes</label>
                                <select name="mes_hasta" class="form-select form-select-sm">
                                    <?php foreach($meses_espanol_select as $num_mes => $nombre_mes): ?>
                                        <option value="<?= $num_mes ?>" <?= $mes_hasta == $num_mes ? 'selected' : '' ?>>
                                            <?= $nombre_mes ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Hasta Año</label>
                                <select name="ano_hasta" class="form-select form-select-sm">
                                    <?php foreach($anos_disponibles as $ano): ?>
                                        <option value="<?= $ano ?>" <?= $ano_hasta == $ano ? 'selected' : '' ?>><?= $ano ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- FILTROS ADICIONALES (solo para catálogo) -->
                    <?php if ($tipo_reporte == 'catalogo'): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card bg-dark border-secondary">
                                <div class="card-body py-2">
                                    <form method="POST" id="formFiltrosCatalogo" class="row g-2 align-items-end">
                                        <input type="hidden" name="tipo_reporte" value="catalogo">
                                        <input type="hidden" name="mes_desde" value="<?= $mes_desde ?>">
                                        <input type="hidden" name="ano_desde" value="<?= $ano_desde ?>">
                                        <input type="hidden" name="mes_hasta" value="<?= $mes_hasta ?>">
                                        <input type="hidden" name="ano_hasta" value="<?= $ano_hasta ?>">
                                        
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">
                                                <i class="fas fa-tag me-1 text-info"></i>Filtrar por Categoría
                                                <?php if (!empty($f_cat)): ?>
                                                    <span class="badge bg-info ms-2">Filtro activo</span>
                                                <?php endif; ?>
                                            </label>
                                            <select name="filtro_categoria" class="form-select form-select-sm" id="filtroCategoria">
                                                <option value="">Todas las categorías</option>
                                                <?php
                                                try {
                                                    $cat_q = "SELECT id, descripcion FROM clasif_cat_de_serv WHERE id IN (" . implode(',', $categorias_incluidas) . ") ORDER BY descripcion";
                                                    $cat_stmt = $db->query($cat_q);
                                                    while ($cat = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
                                                        $selected = ($f_cat == $cat['id']) ? 'selected' : '';
                                                        echo "<option value='{$cat['id']}' $selected>{$cat['descripcion']}</option>";
                                                    }
                                                } catch (Exception $e) {}
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold">
                                                <i class="fas fa-search me-1 text-info"></i>Buscar por texto
                                                <?php if (!empty($f_txt)): ?>
                                                    <span class="badge bg-info ms-2">Filtro activo</span>
                                                <?php endif; ?>
                                            </label>
                                            <input type="text" name="filtro_texto" class="form-control form-control-sm" value="<?= htmlspecialchars($f_txt) ?>" placeholder="Código o descripción...">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small fw-bold">&nbsp;</label>
                                            <button type="submit" class="btn btn-info btn-sm w-100" title="Aplicar filtros">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">&nbsp;</label>
                                            <div class="btn-group w-100" role="group">
                                                <!-- Botón para limpiar filtros (solo visible cuando hay filtros activos) -->
                                                <?php if (!empty($f_cat) || !empty($f_txt)): ?>
                                                <a href="?tipo_reporte=catalogo&mes_desde=<?= $mes_desde ?>&ano_desde=<?= $ano_desde ?>&mes_hasta=<?= $mes_hasta ?>&ano_hasta=<?= $ano_hasta ?>&limpiar_filtros=1" 
                                                   class="btn btn-warning btn-sm" 
                                                   title="Limpiar todos los filtros"
                                                   onclick="return confirm('¿Eliminar todos los filtros aplicados?')">
                                                    <i class="fas fa-eraser me-2"></i>Limpiar
                                                </a>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm" disabled title="No hay filtros activos">
                                                    <i class="fas fa-eraser me-2"></i>Limpiar
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <!-- Indicadores de filtros activos (opcional) -->
                                    <?php if (!empty($f_cat) || !empty($f_txt)): ?>
                                    <div class="mt-2 pt-2 border-top border-secondary">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <small class="text-secondary me-2">Filtros activos:</small>
                                            <?php if (!empty($f_cat)): ?>
                                                <?php
                                                // Obtener nombre de la categoría seleccionada
                                                try {
                                                    $cat_nombre_q = "SELECT descripcion FROM clasif_cat_de_serv WHERE id = ?";
                                                    $cat_nombre_stmt = $db->prepare($cat_nombre_q);
                                                    $cat_nombre_stmt->execute([$f_cat]);
                                                    $cat_nombre = $cat_nombre_stmt->fetchColumn();
                                                } catch (Exception $e) {
                                                    $cat_nombre = 'Categoría ' . $f_cat;
                                                }
                                                ?>
                                                <span class="badge bg-info d-inline-flex align-items-center">
                                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($cat_nombre) ?>
                                                    <a href="?tipo_reporte=catalogo&mes_desde=<?= $mes_desde ?>&ano_desde=<?= $ano_desde ?>&mes_hasta=<?= $mes_hasta ?>&ano_hasta=<?= $ano_hasta ?>&filtro_texto=<?= urlencode($f_txt) ?>&quitar_categoria=1" 
                                                       class="text-white ms-2 text-decoration-none" 
                                                       style="opacity: 0.7;"
                                                       onclick="return confirm('¿Quitar filtro de categoría?')"
                                                       title="Quitar este filtro">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($f_txt)): ?>
                                                <span class="badge bg-info d-inline-flex align-items-center">
                                                    <i class="fas fa-search me-1"></i>"<?= htmlspecialchars($f_txt) ?>"
                                                    <a href="?tipo_reporte=catalogo&mes_desde=<?= $mes_desde ?>&ano_desde=<?= $ano_desde ?>&mes_hasta=<?= $mes_hasta ?>&ano_hasta=<?= $ano_hasta ?>&filtro_categoria=<?= $f_cat ?>&quitar_texto=1" 
                                                       class="text-white ms-2 text-decoration-none" 
                                                       style="opacity: 0.7;"
                                                       onclick="return confirm('¿Quitar búsqueda por texto?')"
                                                       title="Quitar este filtro">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Script para el icono del acordeón -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const collapseElement = document.getElementById('filtrosCollapse');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (collapseElement && toggleIcon) {
                collapseElement.addEventListener('show.bs.collapse', function () {
                    toggleIcon.className = 'fas fa-chevron-up text-secondary';
                });
                
                collapseElement.addEventListener('hide.bs.collapse', function () {
                    toggleIcon.className = 'fas fa-chevron-down text-secondary';
                });
            }
        });
        </script>
	</div>
</div>
		
		
        <!-- FORMULARIO DE INSUMOS Y GASTOS FIJOS -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card mica-effect">
                    <div class="card-header py-2">
						<div class="d-flex justify-content-between align-items-center">
							<h6 class="mb-0 fw-bold">
								<i class="fas fa-sliders-h me-2 text-primary"></i>
								Configuración Costos, Gastos, Insumos, Etc.
							</h6>
							<div class="d-flex gap-2">
								<button type="button" class="btn btn-sm btn-outline-warning" onclick="resetearValoresPorDefecto()" title="Restaurar valores por defecto">
									<i class="fas fa-undo me-1"></i> Resetear
								</button>
								<button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('formInsumos').submit();" title="Recalcular Rentabilidad con los nuevos valores">
									<i class="fas fa-sync-alt me-1"></i> Actualizar Reporte
								</button>
							</div>
						</div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formInsumos">
                            <input type="hidden" name="mes_desde" value="<?= $mes_desde ?>">
                            <input type="hidden" name="ano_desde" value="<?= $ano_desde ?>">
                            <input type="hidden" name="mes_hasta" value="<?= $mes_hasta ?>">
                            <input type="hidden" name="ano_hasta" value="<?= $ano_hasta ?>">
                            <input type="hidden" name="tipo_reporte" value="<?= $tipo_reporte ?>">
                            
                            <!-- SECCIÓN 1: IMPRESORAS CON FECHA DE ADQUISICIÓN INDIVIDUAL -->
                            <div class="mb-4">
                                <h6 class="text-info section-title border-info">
                                    <i class="fas fa-print me-2"></i>1. CONFIGURACIÓN DE IMPRESORAS <span class="text-warning">(Cada una con su fecha de adquisición)</span>
                                </h6>
                                
                                <div class="row g-2 mb-2 px-2">
                                    <div class="col-md-3"><div class="form-label-header">MODELO DE IMPRESORA</div></div>
                                    <div class="col-md-1"><div class="form-label-header">CANT.</div></div>
                                    <div class="col-md-2"><div class="form-label-header">FECHA ADQUISICIÓN</div></div>
                                    <div class="col-md-2"><div class="form-label-header">VALOR UNITARIO</div></div>
                                    <div class="col-md-3"><div class="form-label-header">RENDIMIENTO PERSONALIZADO</div></div>
                                    <div class="col-md-1"><div class="form-label-header">ACCIÓN</div></div>
                                </div>
                                
                                <div id="impresoras-container">
<?php foreach ($impresoras_config as $index => $imp): 
    // Calcular depreciación individual para esta impresora
    $fecha_imp = $imp['fecha_adquisicion'] ?? date('Y-m-d', strtotime('25-09-29'));
    $valor_imp = $imp['valor_unitario'] ?? 294700;
    $tasa_imp = 20;
    
    $fecha_inicio_imp = new DateTime($fecha_imp);
    $fecha_hoy_imp = new DateTime();
    $diferencia_imp = $fecha_inicio_imp->diff($fecha_hoy_imp);
    $meses_trans_imp = ($diferencia_imp->y * 12) + $diferencia_imp->m;
    if ($fecha_hoy_imp->format('d') < $fecha_inicio_imp->format('d')) {
        $meses_trans_imp--;
    }
    $meses_trans_imp = max(0, $meses_trans_imp);
    
    $vida_util_meses_imp = (1 / ($tasa_imp / 100)) * 12; // 60 meses
    
    if ($meses_trans_imp < $vida_util_meses_imp) {
        $dep_anual_imp = $valor_imp * ($tasa_imp / 100);
        $dep_mensual_imp = $dep_anual_imp / 12;
        $estado_imp = "En proceso";
    } else {
        $dep_mensual_imp = 0;
        $estado_imp = "Completada";
    }
    $meses_rest_imp = max(0, $vida_util_meses_imp - $meses_trans_imp);
?>
<div class="impresora-item row g-2 mb-3 align-items-start" data-index="<?= $index ?>" id="impresora-<?= $index ?>">
    <!-- Columna 1: Modelo -->
    <div class="col-md-3">
        <select name="impresoras[<?= $index ?>][id]" class="form-select form-select-sm" onchange="toggleManualRendImpresora(this, <?= $index ?>); actualizarDepreciacionGlobal();">
            <?php foreach($catalogo_impresoras as $categoria => $modelos): ?>
                <optgroup label="<?= $categoria ?>">
                    <?php foreach($modelos as $id => $datos): ?>
                        <option value="<?= $id ?>" <?= ($imp['id'] == $id) ? 'selected' : '' ?>>
                            <?= $datos['nombre'] ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- Columna 2: Cantidad -->
    <div class="col-md-1">
        <input type="number" name="impresoras[<?= $index ?>][cantidad]" class="form-control form-control-sm" value="<?= $imp['cantidad'] ?>" min="1" onchange="actualizarDepreciacionGlobal()">
    </div>

    <!-- Columna 3: Fecha Adquisición (con su info) -->
    <div class="col-md-2">
        <input type="date" name="impresoras[<?= $index ?>][fecha_adquisicion]" class="form-control form-control-sm" value="<?= $fecha_imp ?>" onchange="actualizarDepreciacionGlobal()">
        <small class="text-muted d-block" style="font-size:9px; line-height:1.2;" id="info-imp-<?= $index ?>">
            <?php 
            // --- CÁLCULO CORREGIDO PARA PHP ---
            $fecha_inicio_imp = new DateTime($fecha_imp);
            $diferencia_imp = $fecha_inicio_imp->diff($fecha_hoy);
            $meses_trans_imp = ($diferencia_imp->y * 12) + $diferencia_imp->m;
            
            // Si la fecha es futura, los meses son 0
            if ($fecha_inicio_imp > $fecha_hoy) {
                $meses_trans_imp = 0;
            }
            
            $vida_util_meses_imp = 60;
            $meses_rest_imp = max(0, $vida_util_meses_imp - $meses_trans_imp);
            ?>
            <?= date('d/m/Y', strtotime($fecha_imp)) ?> Mes <?= $meses_trans_imp ?>/60<br>Restan: <?= $meses_rest_imp ?> meses
        </small>
    </div>
    
    <!-- Columna 4: Valor Unitario (con su info) -->
    <div class="col-md-2">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-dark text-white border-secondary">$</span>
            <input type="number" step="0.01" name="impresoras[<?= $index ?>][valor_unitario]" class="form-control form-control-sm" value="<?= $valor_imp ?>" onchange="actualizarDepreciacionGlobal()">
        </div>
        <small class="text-muted d-block" style="font-size:9px; line-height:1.2;" id="dep-imp-<?= $index ?>">
            <?php 
            $dep_anual_imp = $valor_imp * 0.20;
            $dep_mensual_imp = $meses_trans_imp < 60 ? $dep_anual_imp / 12 : 0;
            ?>
            $<?= number_format($valor_imp, 2) ?> Dep: $<?= number_format($dep_mensual_imp * $imp['cantidad'], 2) ?>/mes
        </small>
    </div>

    <!-- Columna 5: Rendimiento Personalizado -->
    <div class="col-md-3 manual-rends-<?= $index ?> <?= ($imp['id'] != 'custom') ? 'd-none' : '' ?>">
        <div class="row g-1">
            <div class="col-6">
                <input type="number" name="impresoras[<?= $index ?>][rend_n]" class="form-control form-control-sm" value="<?= $imp['rend_n'] ?>" placeholder="Rend. Negro">
            </div>
            <div class="col-6">
                <input type="number" name="impresoras[<?= $index ?>][rend_c]" class="form-control form-control-sm" value="<?= $imp['rend_c'] ?>" placeholder="Rend. Color">
            </div>
        </div>
    </div>
    <!-- Columna 6: Acción -->
    <div class="col-md-1">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarImpresora(this)">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
<?php endforeach; ?>                                
</div>
                                <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="agregarImpresora()">
                                    <i class="fas fa-plus me-2"></i>Agregar Impresora
                                </button>
                            </div>
                            
							<!-- SECCIÓN 2: INSUMOS BÁSICOS Y MICAS -->
							<div class="mb-4">
								<h6 class="text-warning section-title border-warning">
									<i class="fas fa-tint me-2"></i>2. INSUMOS BÁSICOS Y MICAS
								</h6>
								
								<div class="row g-3">
									<!-- Kit Tinta (existente) -->
									<div class="col-md-6">
										<label class="form-label fw-bold text-white">💧 Kit Tinta Original</label>
										<div class="input-group">
											<span class="input-group-text bg-dark text-white border-secondary">$</span>
											<input type="number" step="0.01" name="precio_kit" class="form-control" value="<?= $precio_kit ?>">
										</div>
										<small class="text-muted">Precio del kit completo de tinta/toner</small>
									</div>
									
									<!-- Papel Bond (existente) -->
									<div class="col-md-6">
										<label class="form-label fw-bold text-white">📄 Papel Bond</label>
										<div class="row g-1">
											<div class="col-8">
												<div class="input-group">
													<span class="input-group-text bg-dark text-white border-secondary">$</span>
													<input type="number" step="0.01" name="precio_resma" class="form-control" value="<?= $precio_resma ?>">
												</div>
											</div>
											<div class="col-4">
												<select name="uds_resma" class="form-select">
													<option value="500" <?= ($uds_resma==500)?'selected':'' ?>>500 hojas</option>
													<option value="1000" <?= ($uds_resma==1000)?'selected':'' ?>>1000 hojas</option>
												</select>
											</div>
										</div>
										<small class="text-muted">Precio por resma</small>
									</div>
									
									<!-- Cartulina (existente) -->
									<div class="col-md-6">
										<label class="form-label fw-bold text-white">🖼️ Cartulina Bristol</label>
										<div class="row g-1">
											<div class="col-8">
												<div class="input-group">
													<span class="input-group-text bg-dark text-white border-secondary">$</span>
													<input type="number" step="0.01" name="precio_cart" class="form-control" value="<?= $precio_cart ?>">
												</div>
											</div>
											<div class="col-4">
												<input type="number" name="uds_cart" class="form-control" value="<?= $uds_cart ?>" placeholder="uds">
											</div>
										</div>
										<small class="text-muted">Precio por paquete de <?= $uds_cart ?> unidades</small>
									</div>
									
									<!-- Papel Fotográfico (existente) -->
									<div class="col-md-6">
										<label class="form-label fw-bold text-white">📸 Papel Fotográfico</label>
										<div class="row g-1">
											<div class="col-8">
												<div class="input-group">
													<span class="input-group-text bg-dark text-white border-secondary">$</span>
													<input type="number" step="0.01" name="precio_foto" class="form-control" value="<?= $precio_foto ?>">
												</div>
											</div>
											<div class="col-4">
												<input type="number" name="uds_foto" class="form-control" value="<?= $uds_foto ?>" placeholder="uds">
											</div>
										</div>
										<small class="text-muted">Precio por paquete de <?= $uds_foto ?> hojas</small>
									</div>
									
									<!-- NUEVA SECCIÓN: MICAS (2 tipos) -->
									<div class="col-12 mt-3">
										<h6 class="text-info border-bottom border-info pb-2">
											<i class="fas fa-id-card me-2"></i>MICAS PLASTIFICADORAS
										</h6>
									</div>
									
									<!-- Mica tamaño Carta (8½x11") -->
									<div class="col-md-6">
										<label class="form-label fw-bold text-white">📄 Mica Tamaño CARTA (8½x11")</label>
										<div class="row g-1">
											<div class="col-8">
												<div class="input-group">
													<span class="input-group-text bg-dark text-white border-secondary">$</span>
													<input type="number" step="0.01" name="precio_mica_carta" class="form-control" value="<?= $precio_mica_carta ?? 400.00 ?>">
												</div>
											</div>
											<div class="col-4">
												<input type="number" name="uds_mica_carta" class="form-control" value="<?= $uds_mica_carta ?? 100 ?>" placeholder="uds">
											</div>
										</div>
										<small class="text-muted">Precio por paquete de <span id="uds_mica_carta_display"><?= $uds_mica_carta ?? 100 ?></span> micas tamaño carta</small>
									</div>
									
									<!-- Mica tamaño Carnet / Pequeña (solapín) -->
									<div class="col-md-6">
										<label class="form-label fw-bold text-white">🪪 Mica Tamaño CARNET (solapín)</label>
										<div class="row g-1">
											<div class="col-8">
												<div class="input-group">
													<span class="input-group-text bg-dark text-white border-secondary">$</span>
													<input type="number" step="0.01" name="precio_mica_carnet" class="form-control" value="<?= $precio_mica_carnet ?? 100.00 ?>">
												</div>
											</div>
											<div class="col-4">
												<input type="number" name="uds_mica_carnet" class="form-control" value="<?= $uds_mica_carnet ?? 100 ?>" placeholder="uds">
											</div>
										</div>
										<small class="text-muted">Precio por paquete de <span id="uds_mica_carnet_display"><?= $uds_mica_carnet ?? 100 ?></span> micas tamaño carnet</small>
									</div>
								</div>
							</div>
														
<!-- SECCIÓN 2.5: MÁRGENES POR CATEGORÍA (DINÁMICOS) -->
<div class="col-12 mt-4">
    <h6 class="text-primary border-bottom border-primary pb-2 fw-bold text-uppercase">
        <i class="fas fa-percent me-2"></i>MÁRGENES DE PRODUCTOS POR CATEGORÍA (Dinámicos)
    </h6>
</div>

<div class="row g-3" id="margenesContainer">
    <!-- COLUMNA IZQUIERDA: CONFIGURACIONES (2/3 del ancho) -->
    <div class="col-md-8">
        <div class="row g-3">
            
            <!-- CATEGORÍA 8: Utiles/Papeleria/Oficina  (ARRIBA - ANCHO COMPLETO) -->
            <div class="col-12">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header py-2 bg-secondary bg-opacity-10 border-bottom border-secondary">
                        <h6 class="mb-0 fw-bold small"><i class="fas fa-pen-ruler me-2 text-info"></i>Categoría 8 - Útiles/Papelería/Oficina</h6>
                    </div>
                    <div class="card-body py-3">
                        <div class="row g-3">
                            <!-- General -->
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Costo General</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat8_general" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat8_general"
                                           data-resumen="resumen_cat8_general"
                                           value="<?= $_SESSION['margen_cat8_general'] ?? 75 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                                <small class="utilidad-texto" id="utilidad_cat8_general">Utilidad: <?= 100 - ($_SESSION['margen_cat8_general'] ?? 75) ?>%</small>
                            </div>
                            <!-- Excepción Cuños -->
                            <div class="col-md-4 border-start border-secondary border-opacity-50">
                                <label class="form-label small mb-1">Costo Cuños</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat8_cunos" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat8_cunos"
                                           data-resumen="resumen_cat8_cunos"
                                           value="<?= $_SESSION['margen_cat8_cunos'] ?? 40 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                                <small class="text-muted x-small">IDs: 60,63,64,69,70</small>
                                <small class="utilidad-texto d-block" id="utilidad_cat8_cunos">Utilidad: <?= 100 - ($_SESSION['margen_cat8_cunos'] ?? 40) ?>%</small>
                            </div>
                            <!-- Excepción Tinta -->
                            <div class="col-md-4 border-start border-secondary border-opacity-50">
                                <label class="form-label small mb-1">Costo Tinta (ID 59)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat8_tinta" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat8_tinta"
                                           data-resumen="resumen_cat8_tinta"
                                           value="<?= $_SESSION['margen_cat8_tinta'] ?? 70 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                                <small class="text-muted x-small">Tinta p/ Impresora 1LT</small>
                                <small class="utilidad-texto d-block" id="utilidad_cat8_tinta">Utilidad: <?= 100 - ($_SESSION['margen_cat8_tinta'] ?? 70) ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILA 1: CATEGORÍA 9 Y 10 -->
            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-header py-2">
                        <h6 class="mb-0 fw-bold small"><i class="fas fa-utensils me-2 text-warning"></i>Cat 9 - Alimentos/Bebidas</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat9_general" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat9_general"
                                           data-resumen="resumen_cat9_general"
                                           value="<?= $_SESSION['margen_cat9_general'] ?? 75 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                            </div>
                            <div class="col-5 text-end">
                                <span class="text-success small fw-bold" id="utilidad_cat9_general"><?= 100 - ($_SESSION['margen_cat9_general'] ?? 75) ?>% Util.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-header py-2">
                        <h6 class="mb-0 fw-bold small"><i class="fas fa-mobile-alt me-2 text-primary"></i>Cat 10 - Tecnología</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat10_general" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat10_general"
                                           data-resumen="resumen_cat10_general"
                                           value="<?= $_SESSION['margen_cat10_general'] ?? 80 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                            </div>
                            <div class="col-5 text-end">
                                <span class="text-success small fw-bold" id="utilidad_cat10_general"><?= 100 - ($_SESSION['margen_cat10_general'] ?? 80) ?>% Util.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILA 2: CATEGORÍA 11 Y 12 -->
            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-header py-2">
                        <h6 class="mb-0 fw-bold small"><i class="fas fa-tools me-2 text-danger"></i>Cat 11 - Piezas/Repuestos</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat11_general" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat11_general"
                                           data-resumen="resumen_cat11_general"
                                           value="<?= $_SESSION['margen_cat11_general'] ?? 75 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                            </div>
                            <div class="col-5 text-end">
                                <span class="text-success small fw-bold" id="utilidad_cat11_general"><?= 100 - ($_SESSION['margen_cat11_general'] ?? 75) ?>% Util.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-header py-2">
                        <h6 class="mb-0 fw-bold small"><i class="fas fa-broom me-2 text-success"></i>Cat 12 - Limpieza</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.1" name="margen_cat12_general" 
                                           class="form-control bg-dark border-secondary text-white margen-input" 
                                           data-utilidad="utilidad_cat12_general"
                                           data-resumen="resumen_cat12_general"
                                           value="<?= $_SESSION['margen_cat12_general'] ?? 70 ?>">
                                    <span class="input-group-text bg-secondary border-secondary text-white">%</span>
                                </div>
                            </div>
                            <div class="col-5 text-end">
                                <span class="text-success small fw-bold" id="utilidad_cat12_general"><?= 100 - ($_SESSION['margen_cat12_general'] ?? 70) ?>% Util.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- COLUMNA DERECHA: RESUMEN (1/3 del ancho) -->
<div class="col-md-4">
    <div class="card h-100 border-info bg-dark bg-opacity-50">
        <div class="card-header py-2 bg-info bg-opacity-25 border-bottom border-info">
            <h6 class="mb-0 fw-bold small text-info"><i class="fas fa-list-check me-2"></i>RESUMEN DE MÁRGENES</h6>
        </div>
        <div class="card-body py-2">
            <table class="table table-sm table-dark table-borderless mb-0" style="font-size: 0.85rem;">
                <tbody>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <td class="text-muted">Utiles/Papeleria/Oficina :</td>
                        <td class="text-end fw-bold text-success" id="resumen_cat8_general"><?= number_format(100 - ($_SESSION['margen_cat8_general'] ?? 75), 1) ?>%</td>
                    </tr>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <td class="text-muted">Cuños:</td>
                        <td class="text-end fw-bold text-success" id="resumen_cat8_cunos"><?= number_format(100 - ($_SESSION['margen_cat8_cunos'] ?? 40), 1) ?>%</td>
                    </tr>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <td class="text-muted">Tinta (ID 59):</td>
                        <td class="text-end fw-bold text-success" id="resumen_cat8_tinta"><?= number_format(100 - ($_SESSION['margen_cat8_tinta'] ?? 70), 1) ?>%</td>
                    </tr>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <td class="text-muted">Alimentos:</td>
                        <td class="text-end fw-bold text-success" id="resumen_cat9_general"><?= number_format(100 - ($_SESSION['margen_cat9_general'] ?? 75), 1) ?>%</td>
                    </tr>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <td class="text-muted">Tecnología:</td>
                        <td class="text-end fw-bold text-warning" id="resumen_cat10_general"><?= number_format(100 - ($_SESSION['margen_cat10_general'] ?? 80), 1) ?>%</td>
                    </tr>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <td class="text-muted">Piezas:</td>
                        <td class="text-end fw-bold text-success" id="resumen_cat11_general"><?= number_format(100 - ($_SESSION['margen_cat11_general'] ?? 75), 1) ?>%</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Limpieza:</td>
                        <td class="text-end fw-bold text-success" id="resumen_cat12_general"><?= number_format(100 - ($_SESSION['margen_cat12_general'] ?? 70), 1) ?>%</td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-3 p-2 bg-dark rounded border border-secondary border-opacity-50">
                <p class="x-small text-muted mb-0"><i class="fas fa-info-circle me-1 text-info"></i> Estos valores calculan automáticamente el costo de adquisición sobre el precio de venta en el catálogo.</p>
            </div>
        </div>
    </div>
</div>


</div>

<style>
    .x-small { font-size: 0.7rem; }
    .utilidad-texto { 
        font-size: 0.7rem; 
        color: #28a745;
        display: block;
        margin-top: 2px;
    }
</style>

<!-- JavaScript para actualización dinámica de TODAS las columnas -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para actualizar utilidad cuando cambia un input
    function actualizarUtilidad(input) {
        const valor = parseFloat(input.value) || 0;
        const nombre = input.getAttribute('name');
        const utilidad = (100 - valor).toFixed(1);
        
        // ===== 1. ACTUALIZAR ELEMENTOS DE LA COLUMNA IZQUIERDA =====
        
        // Categoría 8 - General
        if (nombre === 'margen_cat8_general') {
            const elemento = document.getElementById('utilidad_cat8_general');
            if (elemento) {
                elemento.textContent = 'Utilidad: ' + utilidad + '%';
                elemento.className = getColorClass(utilidad) + ' x-small d-block';
            }
        }
        
        // Categoría 8 - Cuños
        else if (nombre === 'margen_cat8_cunos') {
            const elemento = document.getElementById('utilidad_cat8_cunos');
            if (elemento) {
                elemento.textContent = 'Utilidad: ' + utilidad + '%';
                elemento.className = getColorClass(utilidad) + ' x-small d-block';
            }
        }
        
        // Categoría 8 - Tinta
        else if (nombre === 'margen_cat8_tinta') {
            const elemento = document.getElementById('utilidad_cat8_tinta');
            if (elemento) {
                elemento.textContent = 'Utilidad: ' + utilidad + '%';
                elemento.className = getColorClass(utilidad) + ' x-small d-block';
            }
        }
        
        // Categoría 9 - Alimentos
        else if (nombre === 'margen_cat9_general') {
            const elemento = document.getElementById('utilidad_cat9_general');
            if (elemento) {
                elemento.textContent = utilidad + '% Util.';
                elemento.className = getColorClass(utilidad) + ' small fw-bold';
            }
        }
        
        // Categoría 10 - Tecnología
        else if (nombre === 'margen_cat10_general') {
            const elemento = document.getElementById('utilidad_cat10_general');
            if (elemento) {
                elemento.textContent = utilidad + '% Util.';
                elemento.className = getColorClass(utilidad) + ' small fw-bold';
            }
        }
        
        // Categoría 11 - Piezas
        else if (nombre === 'margen_cat11_general') {
            const elemento = document.getElementById('utilidad_cat11_general');
            if (elemento) {
                elemento.textContent = utilidad + '% Util.';
                elemento.className = getColorClass(utilidad) + ' small fw-bold';
            }
        }
        
        // Categoría 12 - Limpieza
        else if (nombre === 'margen_cat12_general') {
            const elemento = document.getElementById('utilidad_cat12_general');
            if (elemento) {
                elemento.textContent = utilidad + '% Util.';
                elemento.className = getColorClass(utilidad) + ' small fw-bold';
            }
        }
        
        // ===== 2. ACTUALIZAR BADGES DE LA COLUMNA DERECHA (Márgenes por categoría) =====
        const sidebarId = 'sidebar_resumen_' + nombre.replace('margen_', '');
        const sidebarElement = document.getElementById(sidebarId);
        
        if (sidebarElement) {
            sidebarElement.textContent = utilidad + '%';
            
            // Aplicar color según el valor de utilidad
            const utilidadNum = parseFloat(utilidad);
            
            // Para Tecnología (categoría 10) mantener amarillo pero con el color adecuado
            if (nombre.includes('cat10')) {
                if (utilidadNum >= 50) {
                    sidebarElement.className = 'badge bg-success px-3 py-2';
                } else if (utilidadNum >= 30) {
                    sidebarElement.className = 'badge bg-warning px-3 py-2';
                    sidebarElement.style.color = '#000';
                } else {
                    sidebarElement.className = 'badge bg-danger px-3 py-2';
                }
            } 
            // Para los demás
            else {
                if (utilidadNum >= 50) {
                    sidebarElement.className = 'badge bg-success px-3 py-2';
                } else if (utilidadNum >= 30) {
                    sidebarElement.className = 'badge bg-warning px-3 py-2';
                    sidebarElement.style.color = '#000';
                } else {
                    sidebarElement.className = 'badge bg-danger px-3 py-2';
                }
            }
            
            sidebarElement.style.fontSize = '13px';
            sidebarElement.style.fontWeight = '500';
        }
        
        // ===== 3. ACTUALIZAR LA TABLA DE RESUMEN (COLUMNA DERECHA - PARTE SUPERIOR) =====
        actualizarTablaResumen(nombre, utilidad);
    }
    
    // Función para obtener la clase de color según la utilidad
    function getColorClass(utilidad) {
        const utilidadNum = parseFloat(utilidad);
        if (utilidadNum >= 50) return 'text-success';
        if (utilidadNum >= 30) return 'text-warning';
        return 'text-danger';
    }
    
    // Función para actualizar la tabla de resumen
    function actualizarTablaResumen(nombre, utilidad) {
        // Mapeo de nombres de inputs a IDs en la tabla de resumen
        const mapaIDs = {
            'margen_cat8_general': 'resumen_cat8_general',
            'margen_cat8_cunos': 'resumen_cat8_cunos',
            'margen_cat8_tinta': 'resumen_cat8_tinta',
            'margen_cat9_general': 'resumen_cat9_general',
            'margen_cat10_general': 'resumen_cat10_general',
            'margen_cat11_general': 'resumen_cat11_general',
            'margen_cat12_general': 'resumen_cat12_general'
        };
        
        const resumenId = mapaIDs[nombre];
        if (resumenId) {
            const resumenElement = document.getElementById(resumenId);
            if (resumenElement) {
                resumenElement.textContent = utilidad + '%';
                
                // Cambiar color del texto en la tabla de resumen
                resumenElement.className = 'text-end fw-bold ' + getColorClass(utilidad);
            }
        }
    }
    
    // Agregar event listeners a todos los inputs de margen
    const margenInputs = document.querySelectorAll('input[name^="margen_"]');
    margenInputs.forEach(input => {
        // Evento input para cambios en tiempo real
        input.addEventListener('input', function() {
            actualizarUtilidad(this);
        });
        
        // Evento change por si acaso
        input.addEventListener('change', function() {
            actualizarUtilidad(this);
        });
        
        // Inicializar todos los valores al cargar la página
        setTimeout(() => {
            actualizarUtilidad(input);
        }, 100);
    });
    
    console.log('Sistema de márgenes dinámicos inicializado correctamente');
});
</script>
							
							<!-- SECCIÓN 3: GASTOS FIJOS E INDIRECTOS -->
                            <div class="mb-4">
                                <h6 class="text-danger section-title border-danger">
                                    <i class="fas fa-money-bill-wave me-2"></i>3. GASTOS FIJOS, IMPUESTOS E INDIRECTOS
                                </h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-white">👥 Salario</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-dark text-white border-secondary">$</span>
                                            <input type="number" step="0.01" name="gasto_salario" class="form-control" value="<?= $gasto_salario_mensual ?>">
                                        </div>
                                        <small class="text-muted">Nómina mensual</small>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-white">⚡ Electricidad</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-dark text-white border-secondary">$</span>
                                            <input type="number" step="0.01" name="gasto_electricidad" class="form-control" value="<?= $gasto_electricidad_mensual ?>">
                                        </div>
                                        <small class="text-muted">Factura eléctrica</small>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-white">🚚 Transportación</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-dark text-white border-secondary">$</span>
                                            <input type="number" step="0.01" name="gasto_transportacion" class="form-control" value="<?= $gasto_transportacion_mensual ?>">
                                        </div>
                                        <small class="text-muted">Viajes para materiales</small>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-white">📱 Líneas Corpor.</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-dark text-white border-secondary">$</span>
                                            <input type="number" step="0.01" name="gasto_movil" class="form-control" value="<?= $gasto_movil_mensual ?>">
                                        </div>
                                        <small class="text-muted">Teléfonos corporativos</small>
                                    </div>
                                    
                                    <div class="col-12 mt-2">
                                        <h6 class="text-info border-bottom border-info pb-2">RESUMEN DEPRECIACIÓN TOTAL</h6>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div id="depreciacion_tip" class="alert alert-info mb-0 py-2 fw-bold" style="font-size: 14px;">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <span id="depreciacion_mensaje">🖨️ <?= $total_impresoras ?> impresoras activas | Depreciación total: $<?= number_format($depreciacion_mensual, 2) ?>/mes | Valor total: $<?= number_format($valor_total_equipos, 2) ?> | Mes promedio: <?= $meses_transcurridos_global ?>/60 | Restan: <?= $meses_restantes ?> meses</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 mt-2">
                                        <h6 class="text-warning border-bottom border-warning pb-2">CARGAS IMPOSITIVAS</h6>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-white">👥 Seg. Social Patronal</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="0" max="100" name="tasa_seguridad_social" class="form-control" value="<?= $tasa_seguridad_social ?>">
                                            <span class="input-group-text bg-dark text-white border-secondary">%</span>
                                        </div>
                                        <small class="text-muted">Sobre nómina</small>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-white">💵 Impuesto sobre Ventas</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="0" max="100" name="tasa_impuesto_ventas" class="form-control" value="<?= $tasa_impuesto_ventas ?>">
                                            <span class="input-group-text bg-dark text-white border-secondary">%</span>
                                        </div>
                                        <small class="text-muted">Sobre ingresos brutos</small>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-white">🏛️ Contribución Local</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="0" max="100" name="tasa_contribucion_local" class="form-control" value="<?= $tasa_contribucion_local ?>">
                                            <span class="input-group-text bg-dark text-white border-secondary">%</span>
                                        </div>
                                        <small class="text-muted">Desarrollo local</small>
                                    </div>
                                    
                                    <div class="col-md-12 mt-2">
                                        <h6 class="text-primary border-bottom border-primary pb-2">GASTOS INDIRECTOS</h6>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold text-white">📊 Porcentaje de Gastos Indirectos</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="0" max="100" name="porcentaje_indirectos" class="form-control" value="<?= $porcentaje_indirectos ?>">
                                            <span class="input-group-text bg-dark text-white border-secondary">%</span>
                                        </div>
                                        <small class="text-muted">
                                            Aplicado sobre costo directo (alquiler, mantenimiento, depreciación, etc.)
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- RESUMEN DE GASTOS TOTALES -->
                                <div class="alert alert-info mt-3 mb-0 py-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Período seleccionado: <?= $num_meses ?> <?= $num_meses == 1 ? 'mes' : 'meses' ?></strong><br>
                                    
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <strong>GASTOS FIJOS MENSUALES:</strong><br>
                                            - Salario: $<?= number_format($gasto_salario_mensual, 2) ?><br>
                                            - Electricidad: $<?= number_format($gasto_electricidad_mensual, 2) ?><br>
                                            - Transportación: $<?= number_format($gasto_transportacion_mensual, 2) ?><br>
                                            - Líneas Móviles: $<?= number_format($gasto_movil_mensual, 2) ?><br>
                                            - Depreciación: $<?= number_format($depreciacion_mensual, 2) ?><br>
                                            - Seg. Social: $<?= number_format($seguridad_social_mensual, 2) ?><br>
                                            <strong>TOTAL MENSUAL: $<?= number_format($gastos_fijos_mensuales, 2) ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>TOTAL PERÍODO (<?= $num_meses ?> meses):</strong><br>
                                            Gastos Fijos: $<?= number_format($gastos_fijos_acumulados, 2) ?><br>
                                            <?php if ($tipo_reporte != 'catalogo'): ?>
                                            Impuesto Ventas (<?= $tasa_impuesto_ventas ?>%): $<?= number_format($impuesto_ventas_periodo ?? 0, 2) ?><br>
                                            Contribución Local (<?= $tasa_contribucion_local ?>%): $<?= number_format($contribucion_local_periodo ?? 0, 2) ?><br>
                                            <strong>TOTAL CARGAS: $<?= number_format(($impuesto_ventas_periodo ?? 0) + ($contribucion_local_periodo ?? 0), 2) ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end border-top border-secondary pt-3 d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-warning" onclick="resetearValoresPorDefecto()">
                                    <i class="fas fa-undo me-2"></i>Resetear valores por defecto
                                </button>
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-sync-alt me-2"></i>RECALCULAR RENTABILIDAD
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
<!-- PANEL DE COSTOS UNITARIOS - VERSIÓN COMPLETA CON MICAS Y GASTOS -->
<div class="col-lg-4">
    <div class="card h-100" style="border-left: 4px solid var(--win-accent);">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="font-size: 15px;">
                <i class="fas fa-calculator me-2 text-primary"></i>
                💰 Costos Unitarios y Gastos
            </h6>
            <span class="badge bg-info" id="fecha-actual-costos"><?= date('d/m/Y') ?></span>
        </div>
        
        <div class="card-body">
            <!-- ===== SECCIÓN 1: COSTOS UNITARIOS BÁSICOS ===== -->
            <div class="mb-3">
                <h6 class="text-info border-bottom border-info pb-1 mb-2" style="font-size: 13px;">
                    <i class="fas fa-file-alt me-1"></i>INSUMOS DE IMPRESIÓN
                </h6>
                <table class="table table-sm table-borderless" style="font-size: 13px;">
                    <tr>
                        <td>📄 Hoja Papel Bond:</td>
                        <td class="text-end fw-bold text-white">$ <?= number_format($costo_h_bond, 2) ?></td>
                    </tr>
                    <tr>
                        <td>🖼️ Hoja Cartulina:</td>
                        <td class="text-end fw-bold text-info">$ <?= number_format($costo_h_cart, 2) ?></td>
                    </tr>
                    <tr>
                        <td>📸 Hoja Papel Foto:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($costo_h_foto, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr class="my-1 border-secondary"></td>
                    </tr>
                    <tr>
                        <td>⚫ Tinta B/N (página):</td>
                        <td class="text-end fw-bold text-white">$ <?= number_format($costo_t_bn, 2) ?></td>
                    </tr>
                    <tr>
                        <td>🔴 Tinta Color (página):</td>
                        <td class="text-end fw-bold text-white">$ <?= number_format($costo_t_cl, 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- ===== SECCIÓN 2: MICAS ===== -->
            <div class="mb-3">
                <h6 class="text-info border-bottom border-info pb-1 mb-2" style="font-size: 13px;">
                    <i class="fas fa-id-card me-1"></i>MICAS PLASTIFICADORAS
                </h6>
                <table class="table table-sm table-borderless" style="font-size: 13px;">
                    <tr>
                        <td>📄 Mica Tamaño CARTA:</td>
                        <td class="text-end fw-bold text-info">$ <?= number_format(($precio_mica_carta ?? 400) / ($uds_mica_carta ?? 100), 2) ?></td>
                    </tr>
                    <tr>
                        <td>🪪 Mica Tamaño CARNET:</td>
                        <td class="text-end fw-bold text-info">$ <?= number_format(($precio_mica_carnet ?? 100) / ($uds_mica_carnet ?? 100), 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- ===== SECCIÓN 3: DATOS GENERALES ===== -->
            <div class="mb-3">
                <h6 class="text-info border-bottom border-info pb-1 mb-2" style="font-size: 13px;">
                    <i class="fas fa-chart-line me-1"></i>DATOS DEL PERÍODO
                </h6>
                <table class="table table-sm table-borderless" style="font-size: 13px;">
                    <tr>
                        <td>🖨️ Impresoras activas:</td>
                        <td class="text-end fw-bold text-white"><?= $total_impresoras ?></td>
                    </tr>
                    <tr>
                        <td>📅 Período (meses):</td>
                        <td class="text-end fw-bold text-white"><?= $num_meses ?></td>
                    </tr>
                    <tr>
                        <td>📊 % Gastos Indirectos:</td>
                        <td class="text-end fw-bold text-info"><?= $porcentaje_indirectos ?>%</td>
                    </tr>
                    <tr>
                        <td>🧮 Factor indirecto:</td>
                        <td class="text-end fw-bold text-white">× <?= number_format(1 + $factor_indirectos, 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- ===== SECCIÓN 4: GASTOS FIJOS MENSUALES ===== -->
            <div class="mb-3">
                <h6 class="text-warning border-bottom border-warning pb-1 mb-2" style="font-size: 13px;">
                    <i class="fas fa-money-bill-wave me-1"></i>GASTOS FIJOS MENSUALES
                </h6>
                <table class="table table-sm table-borderless" style="font-size: 13px;">
                    <tr>
                        <td>👥 Salario:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($gasto_salario_mensual, 2) ?></td>
                    </tr>
                    <tr>
                        <td>⚡ Electricidad:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($gasto_electricidad_mensual, 2) ?></td>
                    </tr>
                    <tr>
                        <td>🚚 Transportación:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($gasto_transportacion_mensual, 2) ?></td>
                    </tr>
                    <tr>
                        <td>📱 Líneas Móviles:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($gasto_movil_mensual, 2) ?></td>
                    </tr>
                    <tr>
                        <td>📉 Depreciación:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($depreciacion_mensual, 2) ?></td>
                    </tr>
                    <tr>
                        <td>👥 Seg. Social:</td>
                        <td class="text-end fw-bold text-warning">$ <?= number_format($seguridad_social_mensual, 2) ?></td>
                    </tr>
                    <tr class="border-top border-secondary">
                        <td class="fw-bold text-white">TOTAL MENSUAL:</td>
                        <td class="text-end fw-bold text-info">$ <?= number_format($gastos_fijos_mensuales, 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- ===== SECCIÓN 5: DETALLE DE DEPRECIACIÓN POR IMPRESORAS ===== -->
            <div class="mb-3">
                <h6 class="text-info border-bottom border-info pb-1 mb-2" style="font-size: 13px;">
                    <i class="fas fa-print me-1"></i>DETALLE POR IMPRESORA
                </h6>
                <?php 
                $imp_idx = 1;
                foreach ($impresoras_config as $imp): 
                    $fecha_imp = $imp['fecha_adquisicion'] ?? date('Y-m-d', strtotime('-3 years'));
                    $valor_imp = $imp['valor_unitario'] ?? 294700;
                    $fecha_inicio_imp = new DateTime($fecha_imp);
                    $diferencia_imp = $fecha_inicio_imp->diff($fecha_hoy);
                    
                    $meses_trans_imp = ($diferencia_imp->y * 12) + $diferencia_imp->m;
                    if ($fecha_inicio_imp > $fecha_hoy) {
                        $meses_trans_imp = 0;
                    }
                    
                    $vida_util_meses_imp = 60;
                    $dep_anual_imp = $valor_imp * 0.20;
                    $dep_mensual_imp = $meses_trans_imp < 60 ? ($dep_anual_imp / 12) * $imp['cantidad'] : 0;
                    $meses_rest_imp = max(0, $vida_util_meses_imp - $meses_trans_imp);
                    
                    $fecha_formateada = date('d/m/Y', strtotime($fecha_imp));
                ?>
                <div class="mb-2 p-2 rounded" style="background-color: var(--win-bg-tertiary);">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-white"><strong>🖨️ Imp<?= $imp_idx ?> (<?= $imp['cantidad'] ?>u)</strong></span>
                        <span class="text-warning small">$<?= number_format($dep_mensual_imp, 2) ?>/mes</span>
                    </div>
                    <div class="d-flex justify-content-between" style="color: #a0a0a0; font-size: 11px;">
                        <span>📅 <?= $fecha_formateada ?></span>
                        <span>⏱️ Mes <?= $meses_trans_imp ?>/60</span>
                    </div>
                    <div class="d-flex justify-content-between" style="color: #17a2b8; font-size: 11px;">
                        <span>💰 $<?= number_format($valor_imp, 0) ?></span>
                        <span>📊 $<?= number_format($dep_anual_imp / 12, 2) ?>/mes</span>
                    </div>
                </div>
                <?php 
                    $imp_idx++;
                endforeach; 
                ?>
                
                <!-- RESUMEN DE DEPRECIACIÓN TOTAL - CORREGIDO CON FONDO OSCURO -->
                <div class="mt-2 p-2 rounded" style="background-color: #2d2d44; border-left: 3px solid #17a2b8;">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-white">DEPRECIACIÓN TOTAL:</span>
                        <span class="fw-bold text-info">$ <?= number_format($depreciacion_mensual, 2) ?>/mes</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-secondary">📊 Estado: <?= $estado_depreciacion ?></span>
                        <span class="text-secondary">⏱️ Prom: <?= $meses_transcurridos_global ?>/60</span>
                    </div>
                </div>
            </div>
            
            <!-- ===== SECCIÓN 6: IMPUESTOS (solo si no es catálogo) ===== -->
            <?php if ($tipo_reporte != 'catalogo'): ?>
            <div class="mb-3">
                <h6 class="text-danger border-bottom border-danger pb-1 mb-2" style="font-size: 13px;">
                    <i class="fas fa-file-invoice me-1"></i>IMPUESTOS DEL PERÍODO
                </h6>
                <table class="table table-sm table-borderless" style="font-size: 13px;">
                    <tr>
                        <td>💵 Impuesto Ventas (<?= $tasa_impuesto_ventas ?>%):</td>
                        <td class="text-end fw-bold text-danger">$ <?= number_format($impuesto_ventas_periodo ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td>🏛️ Contribución Local (<?= $tasa_contribucion_local ?>%):</td>
                        <td class="text-end fw-bold text-danger">$ <?= number_format($contribucion_local_periodo ?? 0, 2) ?></td>
                    </tr>
                    <tr class="border-top border-secondary">
                        <td class="fw-bold text-white">TOTAL IMPUESTOS:</td>
                        <td class="text-end fw-bold text-danger">$ <?= number_format(($impuesto_ventas_periodo ?? 0) + ($contribucion_local_periodo ?? 0), 2) ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
<!-- ===== SECCIÓN 7: RESUMEN DE MÁRGENES (INTEGRADO Y COMPACTO) ===== -->
<div class="mt-3">
    <h6 class="text-success border-bottom border-success pb-1 mb-2" style="font-size: 13px;">
        <i class="fas fa-percent me-1"></i>MÁRGENES POR CATEGORÍA
    </h6>
    
    <!-- VERSIÓN COMPACTA: UNA SOLA FILA POR CATEGORÍA -->
    <div class="row g-1" style="font-size: 12px;">
        <!-- Utiles/Papeleria/Oficina  -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">📋 Utiles/Papeleria/Oficina :</span>
                <span class="badge bg-success px-2 py-1" style="font-size: 11px; font-weight: 500;" id="sidebar_resumen_cat8_general"><?= number_format(100 - ($_SESSION['margen_cat8_general'] ?? 75), 1) ?>%</span>
            </div>
        </div>
        
        <!-- Cuños -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">🖊️ Cuños:</span>
                <span class="badge bg-warning px-2 py-1" style="font-size: 11px; font-weight: 500;" id="sidebar_resumen_cat8_cunos"><?= number_format(100 - ($_SESSION['margen_cat8_cunos'] ?? 40), 1) ?>%</span>
            </div>
        </div>
        
        <!-- Tinta ID 59 -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">💧 Tinta ID 59:</span>
                <span class="badge bg-info px-2 py-1" style="font-size: 11px; font-weight: 500;" id="sidebar_resumen_cat8_tinta"><?= number_format(100 - ($_SESSION['margen_cat8_tinta'] ?? 70), 1) ?>%</span>
            </div>
        </div>
        
        <!-- Alimentos -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">🍔 Alimentos:</span>
                <span class="badge bg-success px-2 py-1" style="font-size: 11px; font-weight: 500;" id="sidebar_resumen_cat9_general"><?= number_format(100 - ($_SESSION['margen_cat9_general'] ?? 75), 1) ?>%</span>
            </div>
        </div>
        
        <!-- Tecnología -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">💻 Tecnología:</span>
                <span class="badge bg-warning px-2 py-1" style="font-size: 11px; font-weight: 500; color: #000;" id="sidebar_resumen_cat10_general"><?= number_format(100 - ($_SESSION['margen_cat10_general'] ?? 80), 1) ?>%</span>
            </div>
        </div>
        
        <!-- Piezas -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">⚙️ Piezas:</span>
                <span class="badge bg-success px-2 py-1" style="font-size: 11px; font-weight: 500;" id="sidebar_resumen_cat11_general"><?= number_format(100 - ($_SESSION['margen_cat11_general'] ?? 75), 1) ?>%</span>
            </div>
        </div>
        
        <!-- Limpieza -->
        <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-white small">🧹 Limpieza:</span>
                <span class="badge bg-success px-2 py-1" style="font-size: 11px; font-weight: 500;" id="sidebar_resumen_cat12_general"><?= number_format(100 - ($_SESSION['margen_cat12_general'] ?? 70), 1) ?>%</span>
            </div>
        </div>
    </div>
    
    <!-- ===== SECCIÓN DE PORCENTAJES GENERALES APLICADOS (COMPACTA) ===== -->
    <div class="mt-2 p-2 rounded" style="background-color: #2d2d44; border-left: 3px solid #0078d4;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-white small fw-bold"><i class="fas fa-calculator me-1 text-info"></i>PORCENTAJES APLICADOS:</span>
        </div>
        
        <div class="row g-1">
            <div class="col-6">
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">💵 Imp. Ventas:</span>
                    <span class="text-white small fw-bold"><?= $tasa_impuesto_ventas ?>%</span>
                </div>
            </div>
            <div class="col-6">
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">🏛️ Cont. Local:</span>
                    <span class="text-white small fw-bold"><?= $tasa_contribucion_local ?>%</span>
                </div>
            </div>
            <div class="col-6">
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">📊 Gastos Indir.:</span>
                    <span class="text-white small fw-bold"><?= $porcentaje_indirectos ?>%</span>
                </div>
            </div>
            <div class="col-6">
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">👥 Seg. Social:</span>
                    <span class="text-white small fw-bold"><?= $tasa_seguridad_social ?>%</span>
                </div>
            </div>
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <span class="text-secondary small">📉 Depreciación Anual:</span>
                    <span class="text-white small fw-bold"><?= $tasa_depreciacion ?>%</span>
                </div>
            </div>
        </div>
        
        <!-- Total cargas impositivas -->
        <div class="mt-1 pt-1 border-top border-secondary border-opacity-25">
            <div class="d-flex justify-content-between">
                <span class="text-warning small fw-bold">💲 Total Impuestos:</span>
                <span class="text-warning small fw-bold"><?= $tasa_impuesto_ventas + $tasa_contribucion_local ?>% (s/ventas)</span>
            </div>
        </div>
    </div>
    
    <!-- ===== LEYENDA DE COLORES (COMPACTA) ===== -->
    <div class="mt-2 p-2 rounded" style="background-color: #2d2d2d;">
        <div class="d-flex flex-wrap gap-2 justify-content-between">
            <div class="d-flex align-items-center">
                <span class="badge bg-success px-2 py-1 me-1" style="font-size: 12px;">  </span>
                <span class="text-white small" style="font-size: 10px;">Utilidad ≥ 50%</span>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge bg-warning px-2 py-1 me-1" style="font-size: 12px; color: #000;">  </span>
                <span class="text-white small" style="font-size: 10px;">Utilidad 30-50%</span>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge bg-danger px-2 py-1 me-1" style="font-size: 12px;">  </span>
                <span class="text-white small" style="font-size: 10px;">Utilidad < 30%</span>
            </div>
        </div>
        <div class="mt-1">
            <small class="text-secondary" style="font-size: 12px;"><i class="fas fa-sync-alt me-1"></i>Los colores se actualizan automáticamente</small>
        </div>
    </div>
</div>
<!-- ===== SECCIÓN 8: PIE DE PÁGINA CON INFORMACIÓN (SEPARADO) ===== -->
<div class="mt-3 pt-2 border-top border-secondary">
    <div class="d-flex justify-content-between align-items-center">
        <small class="text-secondary">
            <i class="fas fa-sync-alt me-1"></i>Actualizado:
        </small>
        <small class="text-info">
            <?= date('d/m/Y h:i A') ?>
        </small>
    </div>
    <div class="d-flex justify-content-between mt-1">
        <small class="text-secondary">
            <i class="fas fa-database me-1"></i>Total servicios:
        </small>
        <small class="text-white">
            <?= count($servicios_catalogo ?? []) ?>
        </small>
    </div>
</div>
        </div>
    </div>
</div>
<!-- JavaScript adicional para actualizar los márgenes en el sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Extender la función actualizarUtilidad para también actualizar el sidebar
    const originalActualizarUtilidad = window.actualizarUtilidad || function() {};
    
    window.actualizarUtilidad = function(input) {
        // Llamar a la función original si existe
        if (typeof originalActualizarUtilidad === 'function') {
            originalActualizarUtilidad(input);
        }
        
        const valor = parseFloat(input.value) || 0;
        const nombre = input.getAttribute('name');
        const utilidad = (100 - valor).toFixed(1);
        
        // Actualizar los badges en el sidebar
        const sidebarId = 'sidebar_resumen_' + nombre.replace('margen_', '');
        const sidebarElement = document.getElementById(sidebarId);
        if (sidebarElement) {
            sidebarElement.textContent = utilidad + '%';
            
            // Cambiar color según el valor
            if (utilidad >= 60) {
                sidebarElement.className = 'badge bg-success';
            } else if (utilidad >= 40) {
                sidebarElement.className = 'badge bg-warning text-dark';
            } else {
                sidebarElement.className = 'badge bg-danger';
            }
        }
    };
    
    // Reinicializar todos los inputs
    const margenInputs = document.querySelectorAll('.margen-input');
    margenInputs.forEach(input => {
        window.actualizarUtilidad(input);
    });
});
</script>
		</div>
		

		<!-- TABLAS DE RESULTADOS -->
        <div class="card mt-2">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="fas fa-chart-line me-2 text-primary"></i>
                    <?php if ($tipo_reporte == 'catalogo'): ?>
                        CATÁLOGO DE SERVICIOS
                    <?php else: ?>
                        Resultados del Período: <?= date('d/m/Y', strtotime($fecha_desde)) ?> - <?= date('d/m/Y', strtotime($fecha_hasta)) ?>
                    <?php endif; ?>
                </h6>
                <?php if ($tipo_reporte != 'catalogo'): ?>
                <span class="badge bg-info"><?= count($facturas_periodo) ?> transacciones | <?= $facturas_unicas ?> facturas</span>
                <?php else: ?>
                <span class="catalogo-badge"><?= count($servicios_catalogo) ?> servicios en catálogo</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <?php if ($tipo_reporte == 'catalogo'): ?>
                    <!-- TABLA CATÁLOGO -->
<!-- TABLA CATÁLOGO -->
<table class="table table-hover align-middle mb-0" id="tablaCatalogo">
    <thead>
        <tr class="small text-uppercase opacity-75">
            <th class="ps-4" style="width: 50px;">#</th>
            <th class="ps-4">Código</th>
            <th>Servicio</th>
            <th>Categoría</th>
            <th class="text-end">Precio Venta</th>
            <th class="text-end">Costo Directo</th>
            <th class="text-end">Utilidad Bruta</th>
            <th class="text-center">Margen Bruto</th>
            <th class="text-center">Detalle</th>
            <th class="text-center">Ficha</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $contador = 1;
        foreach ($servicios_catalogo as $serv): 
            $margen = $serv['margen_bruto'];
        ?>
        <tr class="<?= $serv['utilidad_bruta'] < 0 ? 'fila-perdida' : 'fila-ganancia' ?>" data-id="<?= $serv['id'] ?>">
            <td class="ps-4 text-white opacity-50"><small><?= $contador++ ?></small></td>
            <td class="ps-4"><span class="fw-bold"><?= htmlspecialchars($serv['codigo']) ?></span></td>
            <td>
                <span class="d-block fw-bold"><?= htmlspecialchars($serv['descripcion']) ?></span>
                <small class="text-muted"><?= $serv['tipo_servicio'] ?></small>
            </td>
            <td><?= htmlspecialchars($serv['cat_nombre']) ?></td>
            <td class="text-end fw-bold text-white">$ <?= number_format($serv['costo'], 2) ?></td>
            <td class="text-end text-warning">$ <?= number_format($serv['costo_directo_calculado'], 2) ?></td>
            <td class="text-end fw-bold">
                <span class="<?= $serv['utilidad_bruta'] < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                    $ <?= number_format($serv['utilidad_bruta'], 2) ?>
                </span>
            </td>
            <td class="text-center">
                <span class="badge bg-<?= $margen < 0 ? 'danger' : ($margen < 25 ? 'warning' : 'success') ?>" style="min-width:65px;">
                    <?= number_format($margen, 1) ?>%
                </span>
            </td>
            <td class="text-center">
                <?php if (!empty($serv['detalle_completo'])): ?>
                <span class="detalle-costo" 
                      title="<?= htmlspecialchars($serv['detalle_completo'], ENT_QUOTES, 'UTF-8') ?>"
                      data-bs-toggle="tooltip" 
                      data-bs-placement="left">
                    <i class="fas fa-info-circle text-info"></i>
                </span>
                <?php else: ?>
                <span class="text-muted">
                    <i class="fas fa-minus"></i>
                </span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-info" onclick="imprimirFichaServicioId(<?= $serv['id'] ?>)">
                    <i class="fas fa-file-invoice-dollar"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot class="table-dark">
        <tr>
            <th colspan="4" class="text-end ps-4">TOTALES:</th>
            <th class="text-end">$ <?= number_format($total_precio_venta_catalogo, 2) ?></th>
            <th class="text-end">$ <?= number_format($total_costo_directo_catalogo, 2) ?></th>
            <th class="text-end <?= $total_utilidad_bruta_catalogo < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                $ <?= number_format($total_utilidad_bruta_catalogo, 2) ?>
            </th>
            <th class="text-center">
                <span class="badge bg-<?= $total_utilidad_bruta_catalogo < 0 ? 'danger' : 'success' ?>">
                    <?= $total_precio_venta_catalogo > 0 ? number_format(($total_utilidad_bruta_catalogo/$total_precio_venta_catalogo)*100, 1) : 0 ?>%
                </span>
            </th>
            <th colspan="2"></th>
        </tr>
    </tfoot>
</table>

			   <?php elseif ($tipo_reporte == 'mensual'): ?>
                    <!-- TABLA MENSUAL -->
                    <table class="table table-hover align-middle mb-0" id="tablaMensual">
                        <thead>
                            <tr class="small text-uppercase opacity-75">
                                <th class="ps-4" style="width: 50px;">#</th>
                                <th class="ps-4">Mes</th>
                                <th class="text-end">Facturado</th>
                                <th class="text-end">Costo Directo</th>
                                <th class="text-end">Gastos Fijos</th>
                                <th class="text-end">Impuestos</th>
                                <th class="text-end">Ganancia Neta</th>
                                <th class="text-center">Margen Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $meses_espanol = [
                                '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
                                '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
                                '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                            ];
                            
                            $total_facturado_mensual = 0;
                            $total_costo_directo_mensual = 0;
                            $total_gastos_fijos_mensual = 0;
                            $total_impuestos_mensual = 0;
                            $total_ganancia_neta_mensual = 0;
                            
                            $contador = 1;
                            foreach ($detalle_mensual as $mes => $data): 
                                $year = substr($mes, 0, 4);
                                $month = substr($mes, 5, 2);
                                $nombre_mes = $meses_espanol[$month] . " $year";
                                $ganancia_neta_mes = $data['ganancia_bruta'] - $gastos_fijos_mensuales - ($total_impuestos_periodo / $num_meses);
                                $impuestos_mes = $total_impuestos_periodo / $num_meses;
                                $margen_neto = $data['facturado'] > 0 ? ($ganancia_neta_mes / $data['facturado']) * 100 : 0;
                                
                                $total_facturado_mensual += $data['facturado'];
                                $total_costo_directo_mensual += $data['costo_directo'];
                                $total_gastos_fijos_mensual += $gastos_fijos_mensuales;
                                $total_impuestos_mensual += $impuestos_mes;
                                $total_ganancia_neta_mensual += $ganancia_neta_mes;
                            ?>
                            <tr class="<?= $ganancia_neta_mes < 0 ? 'fila-perdida' : 'fila-ganancia' ?>">
                                <td class="ps-4 text-white opacity-50"><small><?= $contador++ ?></small></td>
                                <td class="ps-4"><span class="fw-bold text-white"><?= $nombre_mes ?></span></td>
                                <td class="text-end fw-bold text-white">$ <?= number_format($data['facturado'], 2) ?></td>
                                <td class="text-end text-muted">$ <?= number_format($data['costo_directo'], 2) ?></td>
                                <td class="text-end text-warning">$ <?= number_format($gastos_fijos_mensuales, 2) ?></td>
                                <td class="text-end text-info">$ <?= number_format($impuestos_mes, 2) ?></td>
                                <td class="text-end fw-bold">
                                    <span class="<?= $ganancia_neta_mes < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                                        $ <?= number_format($ganancia_neta_mes, 2) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $margen_neto < 0 ? 'danger' : ($margen_neto < 25 ? 'warning' : 'success') ?>">
                                        <?= number_format($margen_neto, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="2" class="text-end ps-4">TOTALES:</th>
                                <th class="text-end">$ <?= number_format($total_facturado_mensual, 2) ?></th>
                                <th class="text-end">$ <?= number_format($total_costo_directo_mensual, 2) ?></th>
                                <th class="text-end">$ <?= number_format($total_gastos_fijos_mensual, 2) ?></th>
                                <th class="text-end">$ <?= number_format($total_impuestos_mensual, 2) ?></th>
                                <th class="text-end <?= $total_ganancia_neta_mensual < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                                    $ <?= number_format($total_ganancia_neta_mensual, 2) ?>
                                </th>
                                <th class="text-center">
                                    <span class="badge bg-<?= $total_ganancia_neta_mensual < 0 ? 'danger' : 'success' ?>">
                                        <?= $total_facturado_mensual > 0 ? number_format(($total_ganancia_neta_mensual/$total_facturado_mensual)*100, 1) : 0 ?>%
                                    </span>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
<?php elseif ($tipo_reporte == 'detallado'): ?>
    <!-- TABLA DETALLADA - AGRUPADA POR FACTURA -->
    <table class="table table-hover align-middle mb-0" id="tablaDetallada">
        <thead>
            <tr class="small text-uppercase opacity-75">
                <th class="ps-4" style="width: 50px;">#</th>
                <th class="ps-4">Fecha</th>
                <th>Factura #</th>
                <th>Servicio</th>
                <th class="text-end">Cant.</th>
                <th class="text-end">P.Unit</th>
                <th class="text-end">Facturado</th>
                <th class="text-end">Costo</th>
                <th class="text-end">Ganancia</th>
                <th class="text-center">Margen</th>
                <th class="text-center">Ficha</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_cantidad_detallado = 0;      // Suma de cantidades de productos
            $total_lineas_detallado = 0;        // Suma de líneas/renglones
            $total_facturado_detallado = 0;
            $total_costo_detallado = 0;
            $total_ganancia_detallado = 0;
            
            $contador = 1;
            foreach ($facturas_agrupadas as $factura): 
                $num_items = count($factura['items']);
                $margen_factura = $factura['total_facturado_items'] > 0 ? 
                    ($factura['total_ganancia_items'] / $factura['total_facturado_items']) * 100 : 0;
                
                $primer_item = true;
                $total_cantidad_factura = 0; // Variable para acumular cantidades de la factura
                
                foreach ($factura['items'] as $idx => $item):
                    $margen_item = $item['total_facturado'] > 0 ? 
                        ($item['ganancia'] / $item['total_facturado']) * 100 : 0;
                    
                    // Acumular cantidades de la factura
                    $total_cantidad_factura += $item['cantidad'];
                    
                    if ($primer_item) {
                        // Ya no acumulamos aquí, lo haremos después del ciclo
                    }
            ?>
            <tr class="<?= $item['ganancia'] < 0 ? 'fila-perdida' : '' ?>">
                <td class="ps-4 text-white opacity-50">
                    <?php if ($primer_item): ?>
                        <small><?= $contador++ ?></small>
                    <?php endif; ?>
                </td>
                <td class="ps-4">
                    <?php if ($primer_item): ?>
                        <?= date('d/m/Y', strtotime($factura['fecha_emision'])) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($primer_item): ?>
                        <a href="ver_factura.php?no_fact=<?php echo urlencode($factura['no_fact']); ?>" 
                           class="text-white" 
                           title="Ver esta factura" data-bs-toggle="tooltip">
                            <?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?>
                        </a>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="d-block"><?= htmlspecialchars($item['servicio_desc']) ?></span>
                    <small class="text-muted"><?= $item['servicio_cod'] ?></small>
                </td>
                <td class="text-end"><?= $item['cantidad'] ?></td>
                <td class="text-end">$ <?= number_format($item['precio_unitario'], 2) ?></td>
                <td class="text-end fw-bold">$ <?= number_format($item['total_facturado'], 2) ?></td>
                <td class="text-end text-muted">$ <?= number_format($item['costo_total'], 2) ?></td>
                <td class="text-end fw-bold">
                    <span class="<?= $item['ganancia'] < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                        $ <?= number_format($item['ganancia'], 2) ?>
                    </span>
                </td>
                <td class="text-center">
                    <?= number_format($margen_item, 1) ?>%
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-info" onclick="imprimirFichaServicioId(<?= $item['servicio_id'] ?>)">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </button>
                </td>
            </tr>
            <?php 
                    $primer_item = false;
                endforeach; 
                
                // Ahora acumulamos los totales de la factura DESPUÉS del ciclo
                $total_cantidad_detallado += $total_cantidad_factura;       // Suma cantidades de productos
                $total_lineas_detallado += $num_items;                      // Suma líneas/renglones
                $total_facturado_detallado += $factura['total_facturado_items'];
                $total_costo_detallado += $factura['total_costo_items'];
                $total_ganancia_detallado += $factura['total_ganancia_items'];
                
                // Fila de total de la factura (AHORA CON 2 COLUMNAS DE CANTIDAD)
                if ($num_items > 1):
            ?>
            <tr class="table-dark" style="border-top: 2px solid #0078D4;">
                <td colspan="4" class="text-end fw-bold ps-4">TOTAL FACTURA <?= $factura['no_fact'] ?>:</td>
                <td class="text-end fw-bold"><?= $total_cantidad_factura ?></td> <!-- Cantidad productos -->
                <td class="text-end fw-bold small">(<?= $num_items ?> líneas)</td> <!-- Líneas/renglones -->
                <td class="text-end fw-bold">$ <?= number_format($factura['total_facturado_items'], 2) ?></td>
                <td class="text-end fw-bold">$ <?= number_format($factura['total_costo_items'], 2) ?></td>
                <td class="text-end fw-bold <?= $factura['total_ganancia_items'] < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                    $ <?= number_format($factura['total_ganancia_items'], 2) ?>
                </td>
                <td class="text-center fw-bold"><?= number_format($margen_factura, 1) ?>%</td>
                <td></td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-dark">
            <tr>
                <th colspan="4" class="text-end ps-4">TOTALES GENERALES:</th>
                <th class="text-end"><?= $total_cantidad_detallado ?></th> <!-- Cantidad productos -->
                <th class="text-end small">(<?= $total_lineas_detallado ?> líneas)</th> <!-- Líneas/renglones -->
                <th class="text-end">$ <?= number_format($total_facturado_detallado, 2) ?></th>
                <th class="text-end">$ <?= number_format($total_costo_detallado, 2) ?></th>
                <th class="text-end <?= $total_ganancia_detallado < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                    $ <?= number_format($total_ganancia_detallado, 2) ?>
                </th>
                <th class="text-center">
                    <?= $total_facturado_detallado > 0 ? number_format(($total_ganancia_detallado/$total_facturado_detallado)*100, 1) : 0 ?>%
                </th>
                <th></th>
            </tr>
        </tfoot>
    </table>
                <?php else: ?>
                    <!-- TABLA CONSOLIDADA -->
                    <table class="table table-hover align-middle mb-0" id="tablaRentabilidad">
                        <thead>
                            <tr class="small text-uppercase opacity-75">
                                <th class="ps-4" style="width: 50px;">#</th>
                                <th class="ps-4">Servicio</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Facturado</th>
                                <th class="text-end">Costo Directo</th>
                                <th class="text-end">Gastos Fijos</th>
                                <th class="text-end">Impuestos</th>
                                <th class="text-end">Ganancia Neta</th>
                                <th class="text-center">Margen Neto</th>
                                <th class="text-center">Ficha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
							// Ordenamos por los que más dinero dejaron
							uasort($stats_servicios, function($a, $b) { return $b['facturado'] <=> $a['facturado']; });
foreach ($stats_servicios as $s): 
    // Cálculo de impuestos y gastos proporcional al peso de este servicio en las ventas totales
    $proporcion = ($total_facturado_periodo > 0) ? ($s['facturado'] / $total_facturado_periodo) : 0;
    $g_fijos_asig = $gastos_fijos_acumulados * $proporcion;
    $imp_asig = $total_impuestos_periodo * $proporcion;
    $ganancia_neta = $s['ganancia_bruta'] - $g_fijos_asig - $imp_asig;
    $margen_neto = $s['facturado'] > 0 ? ($ganancia_neta / $s['facturado']) * 100 : 0;
?>
                            <tr class="<?= $s['ganancia_neta'] < 0 ? 'fila-perdida' : 'fila-ganancia' ?>" data-id="<?= $s['servicio_id'] ?>">
                                <td class="ps-4 text-white opacity-50"><small><?= $contador++ ?></small></td>
                                <td class="ps-4">
                                    <span class="fw-bold text-white d-block"><?= htmlspecialchars($s['descripcion']) ?></span>
                                    <small class="text-muted fw-bold"><?= $s['codigo'] ?> | <?= $s['categoria'] ?></small>
                                </td>
                                <td class="text-end fw-bold"><?= $s['cantidad'] ?></td>
                                <td class="text-end fw-bold text-white">$ <?= number_format($s['facturado'], 2) ?></td>
                                <td class="text-end text-muted">$ <?= number_format($s['costo_directo'], 2) ?></td>
                                <td class="text-end text-warning">$ <?= number_format($s['gastos_fijos_asignados'], 2) ?></td>
                                <td class="text-end text-info">$ <?= number_format($s['impuestos_asignados'] ?? 0, 2) ?></td>
                                <td class="text-end fw-bold">
                                    <span class="<?= $s['ganancia_neta'] < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                                        $ <?= number_format($s['ganancia_neta'], 2) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $margen_neto < 0 ? 'danger' : ($margen_neto < 25 ? 'warning' : 'success') ?>" style="min-width:65px;">
                                        <?= number_format($margen_neto, 1) ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-info" onclick="imprimirFichaServicioId(<?= $s['servicio_id'] ?>)">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="2" class="text-end ps-4">TOTALES:</th>
                                <th class="text-end"><?= array_sum(array_column($stats_filtrados, 'cantidad')) ?></th>
                                <th class="text-end">$ <?= number_format($total_facturado_periodo, 2) ?></th>
                                <th class="text-end">$ <?= number_format($total_costos_directos_periodo, 2) ?></th>
                                <th class="text-end">$ <?= number_format($gastos_fijos_acumulados, 2) ?></th>
                                <th class="text-end">$ <?= number_format($total_impuestos_periodo, 2) ?></th>
                                <th class="text-end <?= $total_ganancia_neta_periodo < 0 ? 'valor-negativo' : 'valor-positivo' ?>">
                                    $ <?= number_format($total_ganancia_neta_periodo, 2) ?>
                                </th>
                                <th class="text-center">
                                    <span class="badge bg-<?= $total_ganancia_neta_periodo < 0 ? 'danger' : 'success' ?>">
                                        <?= $total_facturado_periodo > 0 ? number_format(($total_ganancia_neta_periodo/$total_facturado_periodo)*100, 1) : 0 ?>%
                                    </span>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                
				
				
				<?php endif; ?>
            </div>
        </div>
    </main>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    <!-- LIBRERÍAS DE EXPORTACIÓN -->	
	<script src="js/jspdf.umd.min.js"></script>
	<script src="js/jspdf.plugin.autotable.min.js"></script>
	<script src="js/html2pdf.bundle.min.js"></script>
	<script src="js/xlsx.full.min.js"></script>
    
    <script>
        const theme = document.documentElement.getAttribute('data-theme') || 'dark';
        const isDark = theme === 'dark';
		const bgColor = getComputedStyle(document.documentElement).getPropertyValue('--win-bg-secondary').trim();
		const textColor = getComputedStyle(document.documentElement).getPropertyValue('--win-text-primary').trim();
		const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--win-accent').trim();
        
        const swalConfig = {
            background: bgColor,
            color: textColor,
            width: '500px',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            customClass: { popup: 'mica-effect border-win' }
        };
        
        // Pasar variables de PHP a JavaScript
        const costosInsumos = {
            bond: <?= $costo_h_bond ?>,
            cart: <?= $costo_h_cart ?>,
            foto: <?= $costo_h_foto ?>,
            tbn: <?= $costo_t_bn ?>,
            tcl: <?= $costo_t_cl ?>
        };
		
const margenesDinamicos = {
    cat8_general: <?= $_SESSION['margen_cat8_general'] ?? 75 ?>,
    cat8_cunos: <?= $_SESSION['margen_cat8_cunos'] ?? 40 ?>,
    cat8_tinta: <?= $_SESSION['margen_cat8_tinta'] ?? 70 ?>,
    cat9_general: <?= $_SESSION['margen_cat9_general'] ?? 75 ?>,
    cat10_general: <?= $_SESSION['margen_cat10_general'] ?? 80 ?>,
    cat11_general: <?= $_SESSION['margen_cat11_general'] ?? 75 ?>,
    cat12_general: <?= $_SESSION['margen_cat12_general'] ?? 70 ?>
};

        const porcentajeIndirectos = <?= $factor_indirectos ?>;
        const catalogoImpresoras = <?= json_encode($catalogo_impresoras) ?>;
        let impresorasConfig = <?= json_encode($impresoras_config) ?>;
        let impresoraIndex = <?= count($impresoras_config) ?>;

        // Variables de depreciación
        let depreciacionMensualTotal = <?= $depreciacion_mensual ?>;
        let valorTotalEquipos = <?= $valor_total_equipos ?>;
        let mesesTranscurridosPromedio = <?= $meses_transcurridos_global ?>;
        let mesesRestantesPromedio = <?= $meses_restantes ?>;
        let totalImpresoras = <?= $total_impresoras ?>;
// ===== FUNCIÓN PARA CALCULAR COSTO DE UN SERVICIO EN JAVASCRIPT =====
function calcularCostoServicioJS(servicio, costosInsumos, margenes) {
    const id = servicio.id;
    const desc = servicio.descripcion.toLowerCase();
    const precio = parseFloat(servicio.precio);
    const cat_id = servicio.categoria_id;
    
    // ===== 1. MICAS (IDs 29 y 30) =====
    if (id == 29 || id == 30) {
        const es_carta = (id == 30);
        // Estos valores deberían venir de PHP también
        const costo_mica_carta = <?= ($precio_mica_carta ?? 400) / ($uds_mica_carta ?? 100) ?>;
        const costo_mica_carnet = <?= ($precio_mica_carnet ?? 100) / ($uds_mica_carnet ?? 100) ?>;
        const costo_mica = es_carta ? costo_mica_carta : costo_mica_carnet;
        
        return {
            costo_directo: costo_mica,
            utilidad_bruta: precio - costo_mica,
            margen: ((precio - costo_mica) / precio * 100).toFixed(1),
            tipo: 'Mica plástica',
            detalle: es_carta ? 'Mica tamaño carta' : 'Mica tamaño carnet'
        };
    }
    
    // ===== 2. PRODUCTOS DE VENTA (Categorías 8-12) =====
    else if ([8, 9, 10, 11, 12].includes(cat_id)) {
        let margen_costo = 0.75; // default
        
        switch(cat_id) {
            case 8: // Utiles/Papeleria/Oficina 
                if (desc.includes('cuño') || [60,63,64,69,70].includes(id)) {
                    margen_costo = (margenes.cat8_cunos || 40) / 100;
                } else if (id == 59) {
                    margen_costo = (margenes.cat8_tinta || 70) / 100;
                } else {
                    margen_costo = (margenes.cat8_general || 75) / 100;
                }
                break;
            case 9: // Alimentos
                margen_costo = (margenes.cat9_general || 75) / 100;
                break;
            case 10: // Tecnología
                margen_costo = (margenes.cat10_general || 80) / 100;
                break;
            case 11: // Piezas
                margen_costo = (margenes.cat11_general || 75) / 100;
                break;
            case 12: // Limpieza
                margen_costo = (margenes.cat12_general || 70) / 100;
                break;
        }
        
        const costo_directo = precio * margen_costo;
        return {
            costo_directo: costo_directo,
            utilidad_bruta: precio - costo_directo,
            margen: ((precio - costo_directo) / precio * 100).toFixed(1),
            tipo: 'Producto',
            detalle: `Costo ${(margen_costo*100).toFixed(0)}%`
        };
    }
    
    // ===== 3. SERVICIOS DE IMPRESIÓN =====
    else if ([1,2,3,4,6].includes(cat_id) || 
             desc.includes('impresión') || 
             desc.includes('fotocopia') ||
             desc.includes('copia') ||
             desc.includes('foto')) {
        
        // Usar la función existente de cálculo de impresión
        return calcularCostoImpresionJS(servicio, costosInsumos);
    }
    
    // ===== 4. SERVICIOS MANUALES =====
    else if (desc.includes('encuadernado') || 
             desc.includes('diseño') || 
             desc.includes('foliadora') || 
             desc.includes('taza') ||
             desc.includes('grabar') ||
             desc.includes('sublimación') ||
             desc.includes('trámites')) {
        
        return {
            costo_directo: 0,
            utilidad_bruta: precio,
            margen: '100',
            tipo: 'Manual',
            detalle: 'Servicio manual'
        };
    }
    
    // ===== 5. POR DEFECTO =====
    else {
        const costo_directo = precio * 0.70; // 70% por defecto
        return {
            costo_directo: costo_directo,
            utilidad_bruta: precio - costo_directo,
            margen: ((precio - costo_directo) / precio * 100).toFixed(1),
            tipo: 'General',
            detalle: 'Costo estimado 70%'
        };
    }
}

// ===== FUNCIÓN PARA CÁLCULO DE IMPRESIÓN (auxiliar) =====
function calcularCostoImpresionJS(servicio, costos) {
    const desc = servicio.descripcion.toLowerCase();
    let papel = 0, tinta = 0, caras = 1;
    
    if (desc.includes('fotocopia') || desc.includes('copia')) {
        papel = costos.bond;
    } else if (desc.includes('foto') || desc.includes('pasaporte')) {
        let copiasPorHoja = 1;
        if (desc.includes('1x1')) copiasPorHoja = 48;
        else if (desc.includes('pasaporte') || desc.includes('visa')) copiasPorHoja = 12;
        else if (desc.includes('5x7')) copiasPorHoja = 4;
        else if (desc.includes('4x6') || desc.includes('10x15')) copiasPorHoja = 4;
        
        papel = costos.foto / copiasPorHoja;
    } else if (desc.includes('cartulina')) {
        papel = costos.cart;
    } else {
        papel = costos.bond;
    }
    
    const esColor = desc.includes('color') || desc.includes('full') || desc.includes('foto');
    tinta = esColor ? costos.tcl : costos.tbn;
    
    if (desc.includes('doble cara') || desc.includes('d/c') || desc.includes('ambos lados')) {
        caras = 2;
    }
    
    const costo_directo = papel + (tinta * caras);
    const precio = parseFloat(servicio.precio);
    
    return {
        costo_directo: costo_directo,
        utilidad_bruta: precio - costo_directo,
        margen: ((precio - costo_directo) / precio * 100).toFixed(1),
        tipo: 'Impresión',
        detalle: `Papel: $${papel.toFixed(2)} + Tinta: $${(tinta*caras).toFixed(2)}`
    };
}


function agregarImpresora() {
    const container = document.getElementById('impresoras-container');
    const newIndex = impresoraIndex++;
    
    // Fecha por defecto: 3 años atrás
    const fechaDefault = new Date();
    fechaDefault.setFullYear(fechaDefault.getFullYear() - 3);
    const fechaStr = fechaDefault.toISOString().split('T')[0];
    
    // Calcular meses para la nueva impresora (para el HTML inicial)
    const fechaHoy = new Date();
    const fechaInicio = new Date(fechaStr);
    let añosTrans = fechaHoy.getFullYear() - fechaInicio.getFullYear();
    let mesesTrans = añosTrans * 12 + (fechaHoy.getMonth() - fechaInicio.getMonth());
    if (fechaInicio > fechaHoy) mesesTrans = 0;
    mesesTrans = Math.max(0, mesesTrans);
    const mesesRestantes = 60 - mesesTrans;
    
    const html = `
        <div class="impresora-item row g-2 mb-3 align-items-start" data-index="${newIndex}" id="impresora-${newIndex}">
            <div class="col-md-3">
                <select name="impresoras[${newIndex}][id]" class="form-select form-select-sm" onchange="toggleManualRendImpresora(this, ${newIndex}); actualizarDepreciacionGlobal();">
                    <?php foreach($catalogo_impresoras as $categoria => $modelos): ?>
                        <optgroup label="<?= $categoria ?>">
                            <?php foreach($modelos as $id => $datos): ?>
                                <option value="<?= $id ?>"><?= $datos['nombre'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <input type="number" name="impresoras[${newIndex}][cantidad]" class="form-control form-control-sm" value="1" min="1" onchange="actualizarDepreciacionGlobal()">
            </div>
            <div class="col-md-2">
                <input type="date" name="impresoras[${newIndex}][fecha_adquisicion]" class="form-control form-control-sm" value="${fechaStr}" onchange="actualizarDepreciacionGlobal()">
                <small class="text-muted d-block" style="font-size:9px; line-height:1.2;" id="info-imp-${newIndex}">
                    ${fechaDefault.toLocaleDateString('es-ES')} Mes ${mesesTrans}/60<br>Restan: ${mesesRestantes} meses
                </small>
            </div>
            <div class="col-md-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark text-white border-secondary">$</span>
                    <input type="number" step="0.01" name="impresoras[${newIndex}][valor_unitario]" class="form-control form-control-sm" value="294700" onchange="actualizarDepreciacionGlobal()">
                </div>
                <small class="text-muted d-block" style="font-size:9px; line-height:1.2;" id="dep-imp-${newIndex}">
                    $294,700.00 Dep: $0.00/mes
                </small>
            </div>
            <div class="col-md-3 manual-rends-${newIndex} d-none">
                <div class="row g-1">
                    <div class="col-6">
                        <input type="number" name="impresoras[${newIndex}][rend_n]" class="form-control form-control-sm" value="4500" placeholder="Rend. Negro">
                    </div>
                    <div class="col-6">
                        <input type="number" name="impresoras[${newIndex}][rend_c]" class="form-control form-control-sm" value="7500" placeholder="Rend. Color">
                    </div>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarImpresora(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    actualizarDepreciacionGlobal();
}
		
		
		function eliminarImpresora(btn) {
            const item = btn.closest('.impresora-item');
            if (document.querySelectorAll('.impresora-item').length > 1) {
                item.remove();
                actualizarDepreciacionGlobal();
            } else {
                Swal.fire({
                    ...swalConfig,
                    icon: 'warning',
                    title: '¡Atención!',
                    text: 'Debe haber al menos una impresora en funcionamiento.',
                    timer: 4000,
                    showConfirmButton: false
                });
            }
        }

        function toggleManualRendImpresora(select, index) {
            const manualDiv = document.querySelector(`.manual-rends-${index}`);
            if (select.value === 'custom') {
                manualDiv.classList.remove('d-none');
            } else {
                manualDiv.classList.add('d-none');
            }
        }

function actualizarDepreciacionGlobal() {
    let depreciacionTotal = 0;
    let valorTotal = 0;
    let sumaMesesPonderada = 0;
    let impresorasActivas = 0;
    const fechaHoy = new Date();
    const tasaDepreciacion = 20;
    const vidaUtilMeses = 60;
    
    document.querySelectorAll('.impresora-item').forEach((item, idx) => {
        const cantidad = parseInt(item.querySelector('input[name*="[cantidad]"]').value) || 1;
        const fechaAdq = item.querySelector('input[name*="[fecha_adquisicion]"]').value;
        const valorUnitario = parseFloat(item.querySelector('input[name*="[valor_unitario]"]').value) || 0;
        
        if (fechaAdq && valorUnitario > 0) {
            const fechaInicio = new Date(fechaAdq);
            
            // --- CÁLCULO CORREGIDO DE MESES TRANSCURRIDOS ---
            let añosTrans = fechaHoy.getFullYear() - fechaInicio.getFullYear();
            let mesesTrans = añosTrans * 12 + (fechaHoy.getMonth() - fechaInicio.getMonth());
            
            // SOLUCIÓN: Si el día actual es menor que el día de inicio,
            // significa que no hemos completado el último mes, entonces restamos 1
            // PERO SOLO SI los meses son positivos
            if (fechaHoy.getDate() < fechaInicio.getDate() && mesesTrans > 0) {
                mesesTrans--;
            }
            
            // Si la fecha es futura, los meses transcurridos son 0
            if (fechaInicio > fechaHoy) {
                mesesTrans = 0;
            }
            
            mesesTrans = Math.max(0, mesesTrans);
            
            // Calcular depreciación mensual
            let depMensual = 0;
            if (mesesTrans < vidaUtilMeses) {
                const depAnual = valorUnitario * (tasaDepreciacion / 100);
                depMensual = (depAnual / 12) * cantidad;
            }
            
            depreciacionTotal += depMensual;
            valorTotal += valorUnitario * cantidad;
            impresorasActivas += cantidad;
            sumaMesesPonderada += mesesTrans * (valorUnitario * cantidad);
            
            // --- Actualizar información de la impresora ---
            const infoSpan = document.getElementById(`info-imp-${idx}`);
            const depSpan = document.getElementById(`dep-imp-${idx}`);
            
            if (infoSpan) {
                const fechaFormateada = fechaInicio.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                const mesesRestantes = Math.max(0, vidaUtilMeses - mesesTrans);
                const parcial = (fechaHoy.getDate() < fechaInicio.getDate() && fechaHoy > fechaInicio) ? " (parcial)" : "";
                infoSpan.innerHTML = `${fechaFormateada} | Mes ${mesesTrans}${parcial}/${vidaUtilMeses}<br>⏱️ Restan: ${mesesRestantes} meses`;
            }
            if (depSpan) {
                depSpan.innerHTML = `💰 $${valorUnitario.toFixed(2)} | 📊 $${depMensual.toFixed(2)}/mes`;
            }
        }
    });
    
    const mesesPromedio = valorTotal > 0 ? Math.round(sumaMesesPonderada / valorTotal) : 0;
    const mesesRestantes = Math.max(0, vidaUtilMeses - mesesPromedio);
    
    depreciacionMensualTotal = depreciacionTotal;
    valorTotalEquipos = valorTotal;
    mesesTranscurridosPromedio = mesesPromedio;
    mesesRestantesPromedio = mesesRestantes;
    totalImpresoras = impresorasActivas;
    
    const tipMensaje = document.getElementById('depreciacion_mensaje');
    if (tipMensaje) {
        const estado = depreciacionTotal > 0 ? 'En proceso' : 'Completada (equipos pagados)';
        tipMensaje.innerHTML = `🖨️ ${impresorasActivas} impresoras activas | Depreciación total: $${depreciacionTotal.toFixed(2)}/mes | Valor total: $${valorTotal.toFixed(2)} | Mes promedio: ${mesesPromedio}/60 | Restan: ${mesesRestantes} meses | ${estado}`;
    }
}
		
		function calcularCostoServicio(servicio) {
            const desc = servicio.descripcion.toLowerCase();
            let papel = 0, tinta = 0, caras = 1;
            
            if (desc.includes('encuadernado') || desc.includes('diseño') || 
                desc.includes('foliadora') || desc.includes('taza')) {
                return { material: 0, papel: 0, tinta: 0, total: 0 };
            }
            
            if (desc.includes('fotocopia') || desc.includes('copia')) {
                papel = costosInsumos.bond;
            } else if (desc.includes('foto') || desc.includes('pasaporte')) {
                let copiasPorHoja = 1;
                if (desc.includes('1x1')) copiasPorHoja = 48;
                else if (desc.includes('pasaporte') || desc.includes('visa')) copiasPorHoja = 12;
                else if (desc.includes('5x7')) copiasPorHoja = 4;
                else if (desc.includes('4x6') || desc.includes('10x15')) copiasPorHoja = 4;
                
                papel = costosInsumos.foto / copiasPorHoja;
            } else if (desc.includes('cartulina')) {
                papel = costosInsumos.cart;
            } else {
                papel = costosInsumos.bond;
            }
            
            const esColor = desc.includes('color') || desc.includes('full') || desc.includes('foto');
            tinta = esColor ? costosInsumos.tcl : costosInsumos.tbn;
            
            if (desc.includes('doble cara') || desc.includes('d/c') || desc.includes('ambos lados')) {
                caras = 2;
            }
            
            const material = papel + (tinta * caras);
            
            return { material, papel, tinta: tinta * caras, total: material };
        }

        function imprimirFichaServicio() {
            const select = document.getElementById('selectFicha');
            const selectedOption = select.options[select.selectedIndex];
            
            if (!selectedOption.value) {
                Swal.fire({
                    ...swalConfig,
                    icon: 'warning',
                    title: '¡Atención!',
                    text: 'Seleccione un servicio del Listado.',
                    timer: 4000,
                    showConfirmButton: false
                });
                return;
            }
            
            const servicio = {
                id: selectedOption.value,
                codigo: selectedOption.dataset.codigo,
                descripcion: selectedOption.dataset.descripcion,
                precio: parseFloat(selectedOption.dataset.precio),
                categoria: selectedOption.dataset.categoria
            };
            
            generarFichaPDF(servicio);
        }

        function imprimirFichaServicioId(id) {
            const select = document.getElementById('selectFicha');
            let servicio = null;
            
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value == id) {
                    const opt = select.options[i];
                    servicio = {
                        id: opt.value,
                        codigo: opt.dataset.codigo,
                        descripcion: opt.dataset.descripcion,
                        precio: parseFloat(opt.dataset.precio),
                        categoria: opt.dataset.categoria
                    };
                    break;
                }
            }
            
            if (!servicio) {
                Swal.fire({
                    ...swalConfig,
                    icon: 'error',
                    title: '¡ERROR!',
                    text: 'No se encontró el servicio seleccionado.',
                    timer: 4000,
                    showConfirmButton: false
                });
                return;
            }
            
            generarFichaPDF(servicio);
        }

        function generarFichaPDF(servicio) {
            const costos = calcularCostoServicio(servicio);
            const costoTotal = costos.total;
            const costoConIndirectos = costoTotal * (1 + porcentajeIndirectos);
            const utilidad = servicio.precio - costoConIndirectos;
            
            const gastosIndirectosTotales = costoTotal * porcentajeIndirectos;
            const gastosGenerales = gastosIndirectosTotales * 0.3;
            const gastosDistribucion = gastosIndirectosTotales * 0.3;
            const gastosTributarios = gastosIndirectosTotales * 0.4;
            
            const gastoElectricidadMensual = <?= $gasto_electricidad_mensual ?>;
            
            // --- LÓGICA DE DEPRECIACIÓN CON VALORES ACTUALIZADOS ---
            const depreciacionMensualCalculada = depreciacionMensualTotal;
            const valorEquipos = valorTotalEquipos;
            const mesesTranscurridos = mesesTranscurridosPromedio;
            const vidaUtilMeses = 60;
            const mesesRestantes = mesesRestantesPromedio;
            const estadoDepreciacion = depreciacionMensualCalculada > 0 ? "En proceso" : "Completada";
			
            
            // Calcular factor de uso del servicio (proporción de impresoras utilizadas)
            let factorUsoImpresoras = 1.0;
            const descripcion = servicio.descripcion.toLowerCase();
            
            if (descripcion.includes('color') || descripcion.includes('full') || descripcion.includes('foto')) {
                factorUsoImpresoras = 1.0;
            } else if (descripcion.includes('blanco') || descripcion.includes('negro') || descripcion.includes('b/n')) {
                // Estimación simple: 70% de las impresoras son B/N
                factorUsoImpresoras = 0.7;
            } else if (descripcion.includes('copia') || descripcion.includes('fotocopia')) {
                factorUsoImpresoras = 0.5;
            }
            
            factorUsoImpresoras = Math.max(0, Math.min(1, factorUsoImpresoras));
            
            // Calcular depreciación proporcional al uso
            const depreciacionPorUnidad = depreciacionMensualCalculada / 1000;
            const costoDepreciacionProporcional = depreciacionPorUnidad * factorUsoImpresoras;
            
            // --- LÓGICA DE COSTO BASE (30% MENOS) ---
            const fBase = 0.70;
            const porcentajeReduccion = ((1 - fBase) * 100).toFixed(0);
            
            const bMaterial = costos.material * fBase;
            const bEnergia = (gastoElectricidadMensual / 1000) * fBase;
            const bSalario = (<?= $gasto_salario_mensual ?> / 1000) * fBase;
            const bDepre = costoDepreciacionProporcional * fBase;
            const bProduccion = bMaterial + bSalario + bDepre + bEnergia;
            const bGenerales = gastosGenerales * fBase;
            const bDistribucion = gastosDistribucion * fBase;
            const bTributarios = gastosTributarios * fBase;
            const bIndirectos = gastosIndirectosTotales * fBase;
            const bTotal = costoConIndirectos * fBase;
            const bUtilidad = utilidad * fBase;
            const bPrecio = servicio.precio * fBase;

            const fechaEmision = new Date();
            const fechaVigencia = new Date();
            fechaVigencia.setFullYear(fechaVigencia.getFullYear() + 5);
            
            const formatoFecha = { day: '2-digit', month: '2-digit', year: 'numeric' };
            const fechaEmisionStr = fechaEmision.toLocaleDateString('es-ES', formatoFecha);
            const fechaVigenciaStr = fechaVigencia.toLocaleDateString('es-ES', formatoFecha);
            
            const costoEnergiaUnitario = gastoElectricidadMensual / 1000;
            const costoSalarioUnitario = <?= $gasto_salario_mensual ?> / 1000;
            const costoDepreciacionUnitario = costoDepreciacionProporcional;
            const costoTotalProduccion = costos.material + costoSalarioUnitario + costoDepreciacionUnitario + costoEnergiaUnitario;
            
            const win = window.open('', '_blank');
            win.document.write(`
                <html><head>
                <style>
                    @page { size: letter; margin: 0.5in 0.5in; }
                    @media print {
                        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        table { border-collapse: collapse !important; }
                        th, td { border: 1pt solid black !important; }
                    }
                    body { 
                        font-family: 'Calibri', 'Arial', sans-serif; 
                        font-size: 10pt; 
                        margin: 0;
                        padding: 0;
                        color: #000;
                        line-height: 1;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse !important;
                        border: 1.2pt solid black !important;
                        table-layout: fixed;
                    }
                    colgroup col.col-concepto { width: 270px; }
                    colgroup col.col-fila { width: 45px; }
                    colgroup col.col-costo-base { width: 100px; }
                    colgroup col.col-costo-nuevo { width: 100px; }
                    th, td {
                        border: 1pt solid black !important;
                        padding: 2px 4px;
                        vertical-align: middle;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                    }
                    th {
                        background-color: #E0E0E0 !important;
                        font-weight: bold;
                        text-align: center;
                        font-size: 10pt;
                    }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .bold { font-weight: bold; }
                    .bg-light { background-color: #F2F2F2 !important; }
                    .firma-line {
                        border-bottom: 0.8pt solid black;
                        display: inline-block;
                        width: 200px;
                        margin: 0 2px;
                        height: 10px;
                    }
                    .header-ficha {
                        font-size: 11pt;
                        background-color: #D9D9D9 !important;
                        padding: 3px;
                        text-align: center;
                    }
                    .subheader-ficha {
                        font-size: 11pt;
                        background-color: #F2F2F2 !important;
                        text-align: center;
                        padding: 1px;
                    }
                    .indent-1 { padding-left: 20px !important; }
                    .indent-2 { padding-left: 30px !important; }
                    .concepto-texto {
                        white-space: normal;
                        word-break: break-word;
                    }
                    .nota-depreciacion {
                        font-size: 12pt;
                        color: #555;
                        font-style: italic;
                    }
                </style>
                </head>
                <body>
                
                <table>
                    <colgroup>
                        <col class="col-concepto">
                        <col class="col-fila">
                        <col class="col-costo-base">
                        <col class="col-costo-nuevo">
                    </colgroup>
                    
                    <tr><th colspan="4" class="header-ficha">MINISTERIO DE FINANZAS Y PRECIOS</th></tr>
                    <tr><td colspan="4" class="subheader-ficha bold">FICHA DE COSTOS Y GASTOS DE PRODUCTOS Y SERVICIOS - EVALUACIÓN DE PRECIOS Y TARIFAS</td></tr>
                    <tr>
                        <td colspan="4" style="background-color: #F9F9F9; padding: 2px 4px;">
                            <strong>Resolución:</strong> 148/2023 MFP &nbsp;|&nbsp; <strong>Emisión:</strong> ${fechaEmisionStr} &nbsp;|&nbsp; <strong>Vigencia:</strong> ${fechaVigenciaStr}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3"><strong>Producto o Servicio:</strong> ${servicio.descripcion}</td>
                        <td><strong>Código:</strong> ${servicio.codigo}</td>
                    </tr>
                    
                    <tr>
                        <td colspan="4">
                            <strong>UM:</strong> Unidad &nbsp;&nbsp;|&nbsp;&nbsp; 
                            <strong>Nivel Producción:</strong> 1 - Prod. Terminada &nbsp;&nbsp;|&nbsp;&nbsp; 
                            <strong>% Capacidad:</strong> 100% - Máximo Rendimiento &nbsp;&nbsp;<br> 
                            <strong>Categoría:</strong> ${servicio.categoria}
                        </td>
                    </tr>
                    
                    <tr>
                        <th style="text-align: left;">CONCEPTOS</th>
                        <th>Fila</th>
                        <th style="text-align: right;">Costo Base</th>
                        <th style="text-align: right;">Costo Nuevo</th>
                    </tr>
                    
                    <tr><td class="concepto-texto">Gasto Material</td><td class="text-center">1</td><td class="text-right">$${bMaterial.toFixed(2)}</td><td class="text-right">$${costos.material.toFixed(2)}</td></tr>
                    <tr><td class="indent-1 concepto-texto">De ello: Insumos (Materias primas)</td><td class="text-center">1.1</td><td class="text-right">$${bMaterial.toFixed(2)}</td><td class="text-right">$${costos.material.toFixed(2)}</td></tr>
                    <tr><td class="indent-1 concepto-texto">Combustibles y lubricantes</td><td class="text-center">1.2</td><td class="text-right">$0.00</td><td class="text-right">$0.00</td></tr>
                    <tr><td class="indent-1 concepto-texto">Energía</td><td class="text-center">1.3</td><td class="text-right">$${bEnergia.toFixed(2)}</td><td class="text-right">$${costoEnergiaUnitario.toFixed(2)}</td></tr>
                    <tr><td class="indent-1 concepto-texto">Agua</td><td class="text-center">1.4</td><td class="text-right">$0.00</td><td class="text-right">$0.00</td></tr>
                    <tr><td class="concepto-texto">Salario Directo o retribución</td><td class="text-center">2</td><td class="text-right">$${bSalario.toFixed(2)}</td><td class="text-right">$${costoSalarioUnitario.toFixed(2)}</td></tr>
                    <tr><td class="concepto-texto">Otros Gastos Directos</td><td class="text-center">3</td><td class="text-right">$0.00</td><td class="text-right">$0.00</td></tr>
                    
                    <tr>
                        <td class="concepto-texto">
                            Gastos asociados a la producción (Depreciación)
                            <div class="nota-depreciacion">
                                📅 Depreciación total: $${depreciacionMensualCalculada.toFixed(2)}/mes | Factor uso: ${(factorUsoImpresoras * 100).toFixed(0)}% | Mes promedio: ${mesesTranscurridos}/60 | Restan: ${mesesRestantes} meses
                            </div>
                        </td>
                        <td class="text-center">4</td>
                        <td class="text-right">$${bDepre.toFixed(2)}</td>
                        <td class="text-right">$${costoDepreciacionUnitario.toFixed(2)}</td>
                    </tr>
                    
                    <tr><td class="indent-1 concepto-texto">De ello, salarios</td><td class="text-center">4.1</td><td class="text-right">$0.00</td><td class="text-right">$0.00</td></tr>
                    <tr class="bold bg-light">
                        <td class="concepto-texto">COSTO TOTAL (1+2+3+4)</td>
                        <td class="text-center">5</td>
                        <td class="text-right">$${bProduccion.toFixed(2)}</td>
                        <td class="text-right">$${costoTotalProduccion.toFixed(2)}</td>
                    </tr>
                    <tr><td class="concepto-texto">Gastos Generales y de Administración</td><td class="text-center">6</td><td class="text-right">$${bGenerales.toFixed(2)}</td><td class="text-right">$${gastosGenerales.toFixed(2)}</td></tr>
                    <tr><td class="indent-1 concepto-texto">De ello, salarios</td><td class="text-center">6.1</td><td class="text-right">$${(bGenerales * 0.5).toFixed(2)}</td><td class="text-right">$${(gastosGenerales * 0.5).toFixed(2)}</td></tr>
                    <tr><td class="concepto-texto">Gastos de Distribución y Venta</td><td class="text-center">7</td><td class="text-right">$${bDistribucion.toFixed(2)}</td><td class="text-right">$${gastosDistribucion.toFixed(2)}</td></tr>
                    <tr><td class="indent-1 concepto-texto">De ello, salarios</td><td class="text-center">7.1</td><td class="text-right">$${(bDistribucion * 0.3).toFixed(2)}</td><td class="text-right">$${(gastosDistribucion * 0.3).toFixed(2)}</td></tr>
                    <tr><td class="concepto-texto">Gastos Financieros</td><td class="text-center">8</td><td class="text-right">$0.00</td><td class="text-right">$0.00</td></tr>
                    <tr><td class="concepto-texto">Gastos Financiamiento OSDE</td><td class="text-center">9</td><td class="text-right">$0.00</td><td class="text-right">$0.00</td></tr>
                    <tr><td class="concepto-texto">Gastos Tributarios (Seg. Social e Impuestos)</td><td class="text-center">10</td><td class="text-right">$${bTributarios.toFixed(2)}</td><td class="text-right">$${gastosTributarios.toFixed(2)}</td></tr>
                    <tr class="bold">
                        <td class="concepto-texto">TOTAL DE GASTOS (6 al 10)</td>
                        <td class="text-center">11</td>
                        <td class="text-right">$${bIndirectos.toFixed(2)}</td>
                        <td class="text-right">$${gastosIndirectosTotales.toFixed(2)}</td>
                    </tr>
                    <tr class="bold bg-light">
                        <td class="concepto-texto">TOTAL DE COSTOS Y GASTOS (5+11)</td>
                        <td class="text-center">12</td>
                        <td class="text-right">$${bTotal.toFixed(2)}</td>
                        <td class="text-right">$${costoConIndirectos.toFixed(2)}</td>
                    </tr>
                    <tr><td class="concepto-texto">Utilidad</td><td class="text-center">13</td><td class="text-right">$${bUtilidad.toFixed(2)}</td><td class="text-right">$${utilidad.toFixed(2)}</td></tr>
                    <tr class="bold" style="background-color: #E0E0E0 !important;">
                        <td class="concepto-texto">PRECIO O TARIFA</td>
                        <td class="text-center">14</td>
                        <td class="text-right">$${bPrecio.toFixed(2)}</td>
                        <td class="text-right">$${servicio.precio.toFixed(2)}</td>
                    </tr>
                    <tr><td class="concepto-texto">PRECIO UNITARIO AJUSTADO</td><td class="text-center">15</td><td class="text-right">$${bPrecio.toFixed(2)}</td><td class="text-right">$${servicio.precio.toFixed(2)}</td></tr>
                    <tr><td class="concepto-texto">Datos sobre precios de referencia</td><td class="text-center">16</td><td class="text-right">Plan/Anterior</td><td class="text-right">Precio Actual</td></tr>
                    
                    <tr>
                        <td style="padding: 4px;">
                            <strong>Elaborado por:</strong> <span class="firma-line"></span><br>
                            <strong>Firma/Cargo:</strong> <span class="firma-line"></span><br>
                            <strong>Fecha:</strong> <span class="firma-line" style="width:160px;"></span>
                        </td>
                        <td colspan="3" style="padding: 4px;">
                            <strong>Aprobado por:</strong> <span class="firma-line" style="width:160px;"></span><br>
                            <strong>Firma/Cargo:</strong> <span class="firma-line"></span><br>
                            <strong>Fecha:</strong> <span class="firma-line" style="width:160px;"></span>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top: 4px; font-size: 11pt; text-align: center; border: 1pt solid #000; padding: 2px;">
                    SISFACT PDL Visiones - % Gastos Indirectos: <?= $porcentaje_indirectos ?>% | 
                    Res. 148/2023 MFP | Comparativa Base (${porcentajeReduccion}%) | 
                    Deprec: $${depreciacionMensualCalculada.toFixed(2)}/mes (${totalImpresoras} impresoras)
                </div>
                </body></html>
            `);
            win.document.close();
            
            setTimeout(() => { 
                win.print(); 
                win.close(); 
            }, 600);
        }

        // FUNCIÓN DE ORDENAMIENTO PARA TABLAS
        function sortTable(n, tableId, type = 'string') {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const headers = table.querySelectorAll('th');
            
            const isAscending = headers[n].classList.contains('sort-asc');
            
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            
            rows.sort((a, b) => {
                const aCols = a.querySelectorAll('td');
                const bCols = b.querySelectorAll('td');
                
                if (aCols.length <= n || bCols.length <= n) return 0;
                
                let aVal = aCols[n].innerText.trim();
                let bVal = bCols[n].innerText.trim();
                
                if (type === 'number') {
                    aVal = parseFloat(aVal.replace(/[$,%]/g, '')) || 0;
                    bVal = parseFloat(bVal.replace(/[$,%]/g, '')) || 0;
                    
                    return isAscending ? aVal - bVal : bVal - aVal;
                } else {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                    
                    if (isAscending) {
                        return aVal.localeCompare(bVal);
                    } else {
                        return bVal.localeCompare(aVal);
                    }
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
            
            headers[n].classList.add(isAscending ? 'sort-desc' : 'sort-asc');
        }

        // Inicializar headers clickeables
        document.addEventListener('DOMContentLoaded', function() {
			
		// Inicializar tooltips de Bootstrap
		var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
		var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
			return new bootstrap.Tooltip(tooltipTriggerEl);
		});
		
		// Inicializar tooltips personalizados para la columna Detalle
		var detalleSpans = document.querySelectorAll('.detalle-costo');
		detalleSpans.forEach(function(span) {
			// Asegurar que tengan el atributo title correcto
			if (span.getAttribute('title')) {
				// Opcional: puedes usar tooltip de Bootstrap o dejar el title nativo
				// Para Bootstrap:
				span.setAttribute('data-bs-toggle', 'tooltip');
				span.setAttribute('data-bs-placement', 'top');
				new bootstrap.Tooltip(span);
			}
		});
            const tablas = ['tablaCatalogo', 'tablaRentabilidad', 'tablaDetallada', 'tablaMensual'];
            
            tablas.forEach(tableId => {
                const table = document.getElementById(tableId);
                if (table) {
                    const headers = table.querySelectorAll('th');
                    headers.forEach((header, index) => {
                        if (index === 0) return;
                        
                        const headerText = header.innerText.toLowerCase();
                        let type = 'string';
                        
                        if (headerText.includes('precio') || headerText.includes('costo') || 
                            headerText.includes('utilidad') || headerText.includes('margen') || 
                            headerText.includes('facturado') || headerText.includes('cantidad') ||
                            headerText.includes('ganancia') || headerText.includes('impuestos') ||
                            headerText.includes('gastos')) {
                            type = 'number';
                        }
                        
                        header.style.cursor = 'pointer';
                        header.setAttribute('onclick', `sortTable(${index}, '${tableId}', '${type}')`);
                        header.setAttribute('title', 'Click para ordenar');
                    });
                }
            });
            
            // Inicializar el cálculo de depreciación
            if (document.getElementById('fecha_adquisicion')) {
                setTimeout(actualizarDepreciacionGlobal, 100);
            }
        });


function imprimirReporteReal() {
    const LOGO = <?= json_encode($logo_base64) ?>;
    const FECHA = <?= json_encode(date('d/m/Y H:i:s')) ?>;
    const FECHA_CORTA = "<?= date('d/m/Y') ?>";
    const HORA = "<?= date('h:i:s A') ?>";
    const PERIODO = "<?= date('d/m/Y', strtotime($fecha_desde)) ?> - <?= date('d/m/Y', strtotime($fecha_hasta)) ?>";
    const NUM_MESES = <?= $num_meses ?>;
    const PORCENTAJE_INDIRECTOS = <?= $porcentaje_indirectos ?>;
    const FACTOR_INDIRECTOS = <?= 1 + $factor_indirectos ?>;
    const TIPO_REPORTE = "<?= $tipo_reporte ?>";
    
    // Formatear texto de período con cantidad de meses
    const PERIODO_COMPLETO = NUM_MESES > 1 ? 
        `${PERIODO} (${NUM_MESES} meses)` : 
        `${PERIODO} (1 mes)`;
    
    // Costos unitarios
    const COSTS = { 
        bond: `<?= number_format($costo_h_bond, 2) ?>`, 
        cart: `<?= number_format($costo_h_cart, 2) ?>`, 
        foto: `<?= number_format($costo_h_foto, 2) ?>`, 
        tbn: `<?= number_format($costo_t_bn, 2) ?>`, 
        tcl: `<?= number_format($costo_t_cl, 2) ?>` 
    };

    // Costos de micas
    const COSTOS_MICAS = {
        carta: <?= ($precio_mica_carta ?? 400) / ($uds_mica_carta ?? 100) ?>,
        carnet: <?= ($precio_mica_carnet ?? 100) / ($uds_mica_carnet ?? 100) ?>
    };
    
    // Datos del usuario
    const USER = <?= json_encode($usuario['nombre'] . ' ' . ($usuario['apellidos'] ?? '')) ?>;
    
    // Totales del período
    const TOTAL_FACTURADO = <?= $total_facturado_periodo ?>;
    const TOTAL_COSTOS_DIRECTOS = <?= $total_costos_directos_periodo ?>;
    
    // Gastos fijos mensuales
    const GASTO_SALARIO_MENSUAL = <?= $gasto_salario_mensual ?>;
    const GASTO_ELECTRICIDAD_MENSUAL = <?= $gasto_electricidad_mensual ?>;
    const GASTO_TRANSPORTACION_MENSUAL = <?= $gasto_transportacion_mensual ?>;
    const GASTO_MOVIL_MENSUAL = <?= $gasto_movil_mensual ?>;
    const DEPRECIACION_MENSUAL = <?= $depreciacion_mensual ?>;
    const SEGURIDAD_SOCIAL_MENSUAL = <?= $seguridad_social_mensual ?>;
    const GASTOS_FIJOS_MENSUALES = <?= $gastos_fijos_mensuales ?>;
    
    // Totales acumulados
    const GASTOS_FIJOS_ACUMULADOS = <?= $gastos_fijos_acumulados ?>;
    const IMPUESTO_VENTAS = <?= $impuesto_ventas_periodo ?? 0 ?>;
    const CONTRIBUCION_LOCAL = <?= $contribucion_local_periodo ?? 0 ?>;
    const TOTAL_IMPUESTOS = <?= $total_impuestos_periodo ?? 0 ?>;
    const TOTAL_GANANCIA_NETA = <?= $total_ganancia_neta_periodo ?>;
    
    // Tasas y porcentajes - Objeto con TODOS los porcentajes
    const PORCENTAJES = {
        impuesto_ventas: <?= $tasa_impuesto_ventas ?>,
        contribucion_local: <?= $tasa_contribucion_local ?>,
        gastos_indirectos: <?= $porcentaje_indirectos ?>,
        seguridad_social: <?= $tasa_seguridad_social ?>,
        depreciacion_anual: <?= $tasa_depreciacion ?>
    };
    
    // Márgenes por categoría
    const MARGENES = {
        cat8_general: <?= $_SESSION['margen_cat8_general'] ?? 75 ?>,
        cat8_cunos: <?= $_SESSION['margen_cat8_cunos'] ?? 40 ?>,
        cat8_tinta: <?= $_SESSION['margen_cat8_tinta'] ?? 70 ?>,
        cat9_general: <?= $_SESSION['margen_cat9_general'] ?? 75 ?>,
        cat10_general: <?= $_SESSION['margen_cat10_general'] ?? 80 ?>,
        cat11_general: <?= $_SESSION['margen_cat11_general'] ?? 75 ?>,
        cat12_general: <?= $_SESSION['margen_cat12_general'] ?? 70 ?>
    };
    
    // Tasas individuales (para compatibilidad)
    const TASA_IMPUESTO_VENTAS = <?= $tasa_impuesto_ventas ?>;
    const TASA_CONTRIBUCION_LOCAL = <?= $tasa_contribucion_local ?>;
    const TASA_DEPRECIACION = <?= $tasa_depreciacion ?>;
    const TASA_SEGURIDAD_SOCIAL = <?= $tasa_seguridad_social ?>;
    
    // Otros datos
    const TOTAL_IMPRESORAS = <?= $total_impresoras ?>;
    const TOTAL_REGISTROS = <?= ($tipo_reporte == 'catalogo') ? count($servicios_catalogo) : count($facturas_periodo) ?>;
    const TOTAL_GANANCIA = <?= $total_ganancia_neta_periodo ?>;
    const MARGEN = <?= ($total_facturado_periodo > 0) ? ($total_ganancia_neta_periodo / $total_facturado_periodo) * 100 : 0 ?>;

    // Datos de depreciación por impresoras
    const FECHA_ADQUISICION = "<?= isset($fecha_adquisicion) ? $fecha_adquisicion : date('Y-m-d') ?>";
    const VALOR_EQUIPOS = <?= $valor_total_equipos ?? 0 ?>;
    const MESES_TRANSCURRIDOS = <?= $meses_transcurridos_global ?? 0 ?>;
    const VIDA_UTIL_MESES = 60;
    const MESES_RESTANTES = <?= $meses_restantes ?? 0 ?>;
    const ESTADO_DEPRECIACION = "<?= $estado_depreciacion ?? 'En proceso' ?>";
    const VALOR_RESTANTE = <?= max(0, ($valor_total_equipos ?? 0) - (($depreciacion_mensual ?? 0) * ($meses_transcurridos_global ?? 0))) ?>;

    // Datos mensuales para la tabla
    const DATOS_MENSUALES = <?php 
        $meses_data = [];
        $meses_espanol = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];
        foreach ($detalle_mensual as $mes => $data) {
            $year = substr($mes, 0, 4);
            $month = substr($mes, 5, 2);
            $nombre_mes = $meses_espanol[$month] . " $year";
            $ganancia_neta_mes = $data['ganancia_bruta'] - $gastos_fijos_mensuales;
            $impuestos_mes = $total_impuestos_periodo / $num_meses;
            $ganancia_neta_mes_con_impuestos = $ganancia_neta_mes - $impuestos_mes;
            $margen_neto = $data['facturado'] > 0 ? ($ganancia_neta_mes_con_impuestos / $data['facturado']) * 100 : 0;
            
            $meses_data[] = [
                'nombre' => $nombre_mes,
                'facturado' => $data['facturado'],
                'costo_directo' => $data['costo_directo'],
                'gastos_fijos' => $gastos_fijos_mensuales,
                'impuestos' => $impuestos_mes,
                'ganancia_neta' => $ganancia_neta_mes_con_impuestos,
                'margen' => $margen_neto
            ];
        }
        echo json_encode($meses_data);
    ?>;

    const FILAS_POR_PAGINA = 12;

    let titulo = (TIPO_REPORTE == 'catalogo') ? 'Catálogo de Servicios' : 'Reporte de Rentabilidad Real';
    let paginaActual = 1;
    let html = '';

// ===== CARÁTULA 1: LOGO + RESUMEN + COSTOS UNITARIOS (2 COLUMNAS) + PORCENTAJES =====
// VERSIÓN CON EL MISMO DISEÑO PERO LINE-HEIGHT: 1
html += `
    <div class="p-page horizontal">
        <!-- Encabezado con line-height:1 -->
        <div style="display: flex; align-items: center; margin-bottom: 10px; border-bottom: 2px solid #0078D4; padding-bottom: 5px;">
            <img src="${LOGO}" height="50" style="margin-right: 15px;">
            <div style="line-height: 1;">
                <h1 style="color:#0078D4; margin: 0; font-size: 18pt; line-height: 1;">PDL VISIONES - SISFACT</h1>
                <h2 style="font-size: 14pt; color: #333; margin: 2px 0 0 0; line-height: 1;">${titulo}</h2>
            </div>
        </div>
        
        <!-- PRIMERA FILA: RESUMEN DEL PERÍODO O CATÁLOGO -->
        <div style="display: flex; gap: 15px; margin-top: 5px;">`;

if (TIPO_REPORTE == 'catalogo') {
    html += `
            <!-- Columna Izquierda: Resumen Catálogo -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">Resumen del Catálogo</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                    <tr><td style="padding: 2px 4px;">Total Servicios:</td><td style="padding: 2px 4px;"><?= count($servicios_catalogo) ?></td></tr>
                    <tr><td style="padding: 2px 4px;">Precio Venta Total:</td><td style="padding: 2px 4px;">$<?= number_format($total_precio_venta_catalogo, 2) ?></td></tr>
                    <tr><td style="padding: 2px 4px;">Costo Directo Total:</td><td style="padding: 2px 4px;">$<?= number_format($total_costo_directo_catalogo, 2) ?></td></tr>
                    <tr><td style="padding: 2px 4px;">Utilidad Bruta Total:</td><td style="padding: 2px 4px; ${$total_utilidad_bruta_catalogo >= 0 ? '' : 'color:#d32f2f;'}">$<?= number_format($total_utilidad_bruta_catalogo, 2) ?></td></tr>
                </table>
            </div>`;
} else {
    html += `
            <!-- Columna Izquierda: Resumen Período -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">Resumen del Período</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                    <tr><td style="padding: 2px 4px;">Período:</td><td class="fw-bold" style="padding: 2px 4px;">${PERIODO_COMPLETO}</td></tr>
                    <tr><td style="padding: 2px 4px;">Total Facturado:</td><td style="padding: 2px 4px;">$${TOTAL_FACTURADO.toFixed(2)}</td></tr>
                    <tr><td style="padding: 2px 4px;">Costos Directos:</td><td style="padding: 2px 4px;">$${TOTAL_COSTOS_DIRECTOS.toFixed(2)}</td></tr>
                    <tr><td style="padding: 2px 4px; font-weight:bold;">Ganancia/Pérdida Neta:</td><td style="padding: 2px 4px; font-weight:bold; ${TOTAL_GANANCIA_NETA >= 0 ? '' : 'color:#d32f2f;'}">$${TOTAL_GANANCIA_NETA.toFixed(2)}</td></tr>
                </table>
            </div>`;
}

html += `
            <!-- Columna Derecha: Datos Generales -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">Datos Generales</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                    <tr><td style="padding: 2px 4px;">🖨️ Impresoras activas:</td><td class="text-right" style="padding: 2px 4px;">${TOTAL_IMPRESORAS}</td></tr>
                    <tr><td style="padding: 2px 4px;">📊 % Gastos Indirectos:</td><td class="text-right" style="padding: 2px 4px;">${PORCENTAJES.gastos_indirectos}%</td></tr>
                    <tr><td style="padding: 2px 4px;">🧮 Factor indirecto:</td><td class="text-right" style="padding: 2px 4px;">× ${FACTOR_INDIRECTOS.toFixed(2)}</td></tr>
                    <tr><td style="padding: 2px 4px;">📅 Período (meses):</td><td class="text-right" style="padding: 2px 4px;">${NUM_MESES}</td></tr>
                </table>
            </div>
        </div>
        
        <!-- SEGUNDA FILA: COSTOS UNITARIOS EN DOS COLUMNAS -->
        <div style="margin-top: 10px;">
            <h3 style="font-size: 12pt; margin:0 0 5px 0; color:#0078D4; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">💰 COSTOS UNITARIOS</h3>
            
            <div style="display: flex; gap: 15px;">
                <!-- Columna Izquierda: Hasta Tintas -->
                <div style="flex: 1; background-color: #f9f9f9; padding: 8px; border-radius: 5px;">
                    <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>📄 Papel Bond (hoja)</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTS.bond}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>🖼️ Cartulina (hoja)</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTS.cart}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>📸 Papel Fotográfico (hoja)</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTS.foto}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>⚫ Tinta B/N (página)</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTS.tbn}</td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 5px;"><strong>🔴 Tinta Color (página)</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTS.tcl}</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Columna Derecha: Micas y Otros -->
                <div style="flex: 1; background-color: #f9f9f9; padding: 8px; border-radius: 5px;">
                    <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>📄 Mica Tamaño CARTA</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTOS_MICAS.carta.toFixed(2)}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>🪪 Mica Tamaño CARNET</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$${COSTOS_MICAS.carnet.toFixed(2)}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td style="padding: 3px 5px;"><strong>💧 Kit Tinta Original</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold;">$<?= number_format($precio_kit, 2) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 5px;"><strong>📊 Costo indirecto + factor</strong></td>
                            <td style="padding: 3px 5px; text-align: right; font-weight: bold; color: #0078D4;">× ${FACTOR_INDIRECTOS.toFixed(2)}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- ===== SECCIÓN DE PORCENTAJES APLICADOS ===== -->
        <div style="margin-top: 10px;">
            <h3 style="font-size: 12pt; margin:0 0 5px 0; color:#0078D4; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">📊 PORCENTAJES APLICADOS EN EL CÁLCULO</h3>
            
            <div style="display: flex; gap: 15px;">
                <!-- Columna Izquierda: Porcentajes generales -->
                <div style="flex: 1; padding: 8px; border-radius: 5px; border-left: 4px solid #0078D4;">
                    <table style="width:100%; border-collapse: collapse; font-size: 10pt; line-height: 1;">
                        <tr style="border-bottom: 1px solid #99c2ff;">
                            <td style="padding: 3px 5px;"><strong>💵 Impuesto sobre Ventas</strong></td>
                            <td style="padding: 3px 5px; text-align: center; font-weight: bold; color: #0078D4;">${PORCENTAJES.impuesto_ventas}%</td>
                            <td style="padding: 3px 5px;">s/ventas</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #99c2ff; background-color: #f0f7ff;">
                            <td style="padding: 3px 5px;"><strong>🏛️ Contribución Local</strong></td>
                            <td style="padding: 3px 5px; text-align: center; font-weight: bold; color: #0078D4;">${PORCENTAJES.contribucion_local}%</td>
                            <td style="padding: 3px 5px;">s/ventas</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #99c2ff;">
                            <td style="padding: 3px 5px;"><strong>📊 Gastos Indirectos</strong></td>
                            <td style="padding: 3px 5px; text-align: center; font-weight: bold; color: #0078D4;">${PORCENTAJES.gastos_indirectos}%</td>
                            <td style="padding: 3px 5px;">s/costo directo</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #99c2ff; background-color: #f0f7ff;">
                            <td style="padding: 3px 5px;"><strong>👥 Seguridad Social Patronal</strong></td>
                            <td style="padding: 3px 5px; text-align: center; font-weight: bold; color: #0078D4;">${PORCENTAJES.seguridad_social}%</td>
                            <td style="padding: 3px 5px;">s/nómina</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #99c2ff;">
                            <td style="padding: 3px 5px;"><strong>📉 Depreciación Anual</strong></td>
                            <td style="padding: 3px 5px; text-align: center; font-weight: bold; color: #0078D4;">${PORCENTAJES.depreciacion_anual}%</td>
                            <td style="padding: 3px 5px;">s/equipos</td>
                        </tr>
                        <tr style="background-color: #0078D4; color: white; font-weight: bold;">
                            <td style="padding: 3px 5px;">TOTAL IMPUESTOS</td>
                            <td style="padding: 3px 5px; text-align: center;">${(PORCENTAJES.impuesto_ventas + PORCENTAJES.contribucion_local)}%</td>
                            <td style="padding: 3px 5px;">s/ventas</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Columna Derecha: Márgenes por categoría -->
                <div style="flex: 1; padding: 8px; border-radius: 5px; border-left: 4px solid #28a745;">
                    <h4 style="font-size: 11pt; margin:0 0 4px 0; color:#28a745; line-height: 1;">Márgenes por Categoría (Utilidad)</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">📋 Útiles/Papelería/Oficina:</span> 
                            <span style="font-weight:bold; float:right; color:#28a745;">${(100-MARGENES.cat8_general).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">🖊️ Cuños:</span> 
                            <span style="font-weight:bold; float:right; color:#ffc107;">${(100-MARGENES.cat8_cunos).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">💧 Tinta ID 59:</span> 
                            <span style="font-weight:bold; float:right; color:#17a2b8;">${(100-MARGENES.cat8_tinta).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">🍔 Alimentos:</span> 
                            <span style="font-weight:bold; float:right; color:#28a745;">${(100-MARGENES.cat9_general).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">💻 Tecnología:</span> 
                            <span style="font-weight:bold; float:right; color:#ffc107;">${(100-MARGENES.cat10_general).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">⚙️ Piezas:</span> 
                            <span style="font-weight:bold; float:right; color:#28a745;">${(100-MARGENES.cat11_general).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">🧹 Limpieza:</span> 
                            <span style="font-weight:bold; float:right; color:#28a745;">${(100-MARGENES.cat12_general).toFixed(1)}%</span>
                        </div>
                        <div style="width: 48%; background-color: white; padding: 2px 4px; border-radius: 3px; line-height: 1;">
                            <span style="color:#555;">🎯 Margen Promedio:</span> 
                            <span style="font-weight:bold; float:right; color:#0078D4;">
                                ${(((100-MARGENES.cat8_general) + (100-MARGENES.cat9_general) + (100-MARGENES.cat10_general) + (100-MARGENES.cat11_general) + (100-MARGENES.cat12_general)) / 5).toFixed(1)}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nota aclaratoria compacta -->
            <div style="font-weight:bold; margin-top: 5px; font-size: 12pt; color: #666; font-style: italic; text-align: center; padding: 3px; background-color: #f5f5f5; border-radius: 3px; line-height: 1;">
                <i class="fas fa-info-circle"></i> Total impuestos: ${(PORCENTAJES.impuesto_ventas + PORCENTAJES.contribucion_local)}% s/ventas | Depreciación: ${TASA_DEPRECIACION}% anual (5 años)
            </div>
        </div>
        
        <!-- FOOTER DE LA CARÁTULA 1 -->
        <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 5px; display: flex; justify-content: space-between; font-size: 9pt; line-height: 1;">
            <div style="text-align: left;">
                Página <span class="pagina-actual">1</span> de <span class="total-paginas"></span><br>
                Total Registros: ${TOTAL_REGISTROS}<br>
                <span style="${TOTAL_GANANCIA >= 0 ? '' : 'color:#d32f2f;'}">Total Ganancia/Pérdida: $${TOTAL_GANANCIA.toFixed(2)} - ${MARGEN.toFixed(1)}%</span>
            </div>
            <div style="text-align: center;">
                <strong>PDL Visiones</strong><br>
                www.pdlvisiones.com<br>
                "Donde tu visión toma forma"
            </div>
            <div style="text-align: right;">
                <strong>PDL VISIONES - SISFACT</strong><br>
                Sistema Integral de Facturación y Control<br>
                Reporte de Rentabilidad y Costos
            </div>
        </div>
        
        <div style="position: absolute; bottom: 0.3in; left: 0.5in; right: 0.5in; text-align: center; font-size: 8pt; color: #666; line-height: 1;">
            Generado por: ${USER} | ${FECHA_CORTA} ${HORA}
        </div>
    </div>`;
    paginaActual = 2;
    

    // ===== CARÁTULA 2: GASTOS FIJOS MENSUALES Y TOTALES DEL PERÍODO (SOLO PARA REPORTES CON FACTURACIÓN) =====
    if (TIPO_REPORTE != 'catalogo') {
        html += `
        <div class="p-page horizontal">
            <div style="display: flex; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #0078D4; padding-bottom: 10px;">
                <img src="${LOGO}" height="60" style="margin-right: 20px;">
                <div>
                    <h1 style="color:#0078D4; margin: 0; font-size: 18pt;">PDL VISIONES - SISFACT</h1>
                    <h2 style="font-size: 14pt; color: #333; margin: 5px 0 0 0;">Detalle de Gastos - ${PERIODO_COMPLETO}</h2>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px; margin-top: 10px;">
                <!-- Columna Izquierda: Gastos Fijos Mensuales -->
                <div style="flex: 1;">
                    <h3 style="font-size: 12pt; margin:0 0 10px 0; border-bottom:1px solid #0078D4; padding-bottom:5px;">Gastos Fijos Mensuales</h3>
                    <table style="width:100%; border-collapse: collapse; font-size: 11pt;">
                        <tr><td style="padding: 5px;">Salario:</td><td style="padding: 5px;">$${GASTO_SALARIO_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px;">Electricidad:</td><td style="padding: 5px;">$${GASTO_ELECTRICIDAD_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px;">Transportación:</td><td style="padding: 5px;">$${GASTO_TRANSPORTACION_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px;">Líneas Móviles:</td><td style="padding: 5px;">$${GASTO_MOVIL_MENSUAL.toFixed(2)}</td></tr>
                        
                        <!-- DEPRECIACIÓN CON DETALLES -->
                        <tr><td style="padding: 5px;">Depreciación (${TASA_DEPRECIACION}% anual):</td><td style="padding: 5px;">$${DEPRECIACION_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px; padding-left: 20px;">📅 Fecha Adq. promedio:</td><td style="padding: 5px;">${FECHA_ADQUISICION}</td></tr>
                        <tr><td style="padding: 5px; padding-left: 20px;">📅 Valor restante:</td><td style="padding: 5px; color: #17a2b8;">$${VALOR_RESTANTE.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px; padding-left: 20px;">⏱️ Meses transcurridos (prom):</td><td style="padding: 5px;">${MESES_TRANSCURRIDOS} de ${VIDA_UTIL_MESES}</td></tr>
                        <tr><td style="padding: 5px; padding-left: 20px;">📊 Meses restantes (prom):</td><td style="padding: 5px; color: ${MESES_TRANSCURRIDOS < VIDA_UTIL_MESES ? '#28a745' : '#dc3545'};">${MESES_RESTANTES} meses</td></tr>
                        <tr><td style="padding: 5px; padding-left: 20px;">📊 Estado:</td><td style="padding: 5px; color: ${MESES_TRANSCURRIDOS < VIDA_UTIL_MESES ? '#28a745' : '#dc3545'};">${ESTADO_DEPRECIACION}</td></tr>
                        
                        <tr><td style="padding: 5px;">Seguridad Social (${TASA_SEGURIDAD_SOCIAL}%):</td><td style="padding: 5px;">$${SEGURIDAD_SOCIAL_MENSUAL.toFixed(2)}</td></tr>
                        <tr style="border-top:2px solid #0078D4;"><td style="padding: 5px; font-weight:bold;">TOTAL MENSUAL:</td><td style="padding: 5px; font-weight:bold;">$${GASTOS_FIJOS_MENSUALES.toFixed(2)}</td></tr>
                    </table>
                </div>
                
                <!-- Columna Derecha: Totales del Período -->
                <div style="flex: 1;">
                    <h3 style="font-size: 12pt; margin:0 0 10px 0; border-bottom:1px solid #0078D4; padding-bottom:5px;">Totales del Período (${NUM_MESES} ${NUM_MESES == 1 ? 'mes' : 'meses'})</h3>
                    <table style="width:100%; border-collapse: collapse; font-size: 11pt;">
                        <tr><td style="padding: 5px;">Gastos Fijos Acumulados:</td><td style="padding: 5px;">$${GASTOS_FIJOS_ACUMULADOS.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px;">Impuesto Ventas (${TASA_IMPUESTO_VENTAS}%):</td><td style="padding: 5px;">$${IMPUESTO_VENTAS.toFixed(2)}</td></tr>
                        <tr><td style="padding: 5px;">Contribución Local (${TASA_CONTRIBUCION_LOCAL}%):</td><td style="padding: 5px;">$${CONTRIBUCION_LOCAL.toFixed(2)}</td></tr>
                        <tr style="border-top:2px solid #0078D4;"><td style="padding: 5px; font-weight:bold;">TOTAL CARGAS:</td><td style="padding: 5px; font-weight:bold;">$${TOTAL_IMPUESTOS.toFixed(2)}</td></tr>
                    </table>
                    
                    <div style="margin-top: 30px; padding: 15px; background: #f5f5f5; border: 1px solid #0078D4;">
                        <div style="font-size: 11pt;">GANANCIA/PÉRDIDA NETA</div>
                        <div style="font-size: 16pt; font-weight: bold; ${TOTAL_GANANCIA_NETA >= 0 ? '' : 'color: #d32f2f;'}">$${TOTAL_GANANCIA_NETA.toFixed(2)}</div>
                        <div style="font-size: 10pt;">Margen: ${MARGEN.toFixed(1)}%</div>
                    </div>
                </div>
            </div>
            
            <!-- FOOTER -->
            <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9pt;">
                <div style="text-align: left;">
                    Página <span class="pagina-actual">2</span> de <span class="total-paginas"></span><br>
                    Total Registros: ${TOTAL_REGISTROS}<br>
                    <span style="${TOTAL_GANANCIA >= 0 ? '' : 'color:#d32f2f;'}">Total Ganancia/Pérdida: $${TOTAL_GANANCIA.toFixed(2)} - ${MARGEN.toFixed(1)}%</span>
                </div>
                <div style="text-align: center;">
                    <strong>PDL Visiones</strong><br>
                    www.pdlvisiones.com<br>
                    "Donde tu visión toma forma"
                </div>
                <div style="text-align: right;">
                    <strong>PDL VISIONES - SISFACT</strong><br>
                    Sistema Integral de Facturación y Control<br>
                    Reporte de Rentabilidad y Costos
                </div>
            </div>
            
            <div style="position: absolute; bottom: 0.3in; left: 0.5in; right: 0.5in; text-align: center; font-size: 8pt; color: #666;">
                Generado por: ${USER} | ${FECHA_CORTA} ${HORA}
            </div>
        </div>`;
        
        paginaActual = 3;
    }

    // ===== TABLA DE RESULTADOS CON PAGINACIÓN Y FILTRO DE COLUMNAS =====
    if (TIPO_REPORTE == 'mensual') {
        // Construir tabla mensual manualmente con los datos
        const totalPaginasTabla = Math.ceil(DATOS_MENSUALES.length / FILAS_POR_PAGINA);
        const totalPaginasGeneral = paginaActual + totalPaginasTabla - 1;
        
        for (let paginaTabla = 0; paginaTabla < totalPaginasTabla; paginaTabla++) {
            const numPaginaActual = paginaActual + paginaTabla;
            const inicio = paginaTabla * FILAS_POR_PAGINA;
            const fin = Math.min(inicio + FILAS_POR_PAGINA, DATOS_MENSUALES.length);
            
            let tablaHTML = `
                <table style="width:100%; border-collapse: collapse; font-size: 10pt;">
                    <thead>
                        <tr>
                            <th style="width: 50px; background: #f0f0f0; border: 1px solid #999; padding: 6px;">#</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px;">Mes</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px; text-align: right;">Facturado</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px; text-align: right;">Costo Directo</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px; text-align: right;">Gastos Fijos</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px; text-align: right;">Impuestos</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px; text-align: right;">Ganancia Neta</th>
                            <th style="background: #f0f0f0; border: 1px solid #999; padding: 6px; text-align: center;">Margen Neto</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            let totalFacturado = 0;
            let totalCostoDirecto = 0;
            let totalGastosFijos = 0;
            let totalImpuestos = 0;
            let totalGananciaNeta = 0;
            
            for (let i = inicio; i < fin; i++) {
                const d = DATOS_MENSUALES[i];
                const filaClass = d.ganancia_neta < 0 ? 'fila-perdida' : 'fila-ganancia';
                const gananciaColor = d.ganancia_neta < 0 ? 'valor-negativo' : 'valor-positivo';
                
                tablaHTML += `
                    <tr class="${filaClass}">
                        <td style="border: 1px solid #ccc; padding: 5px;">${i - inicio + 1}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; font-weight: bold;">${d.nombre}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; text-align: right;">$${d.facturado.toFixed(2)}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; text-align: right;">$${d.costo_directo.toFixed(2)}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; text-align: right;">$${d.gastos_fijos.toFixed(2)}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; text-align: right;">$${d.impuestos.toFixed(2)}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; text-align: right; font-weight: bold;" class="${gananciaColor}">$${d.ganancia_neta.toFixed(2)}</td>
                        <td style="border: 1px solid #ccc; padding: 5px; text-align: center;">${d.margen.toFixed(1)}%</td>
                    </tr>`;
                
                totalFacturado += d.facturado;
                totalCostoDirecto += d.costo_directo;
                totalGastosFijos += d.gastos_fijos;
                totalImpuestos += d.impuestos;
                totalGananciaNeta += d.ganancia_neta;
            }
            
            tablaHTML += `</tbody>`;
            
            // Agregar pie de tabla en la última página
            if (paginaTabla === totalPaginasTabla - 1) {
                const totalMargen = totalFacturado > 0 ? (totalGananciaNeta / totalFacturado) * 100 : 0;
                tablaHTML += `
                    <tfoot style="background: #f0f0f0; font-weight: bold;">
                        <tr>
                            <td colspan="2" style="border: 1px solid #999; padding: 6px; text-align: right;">TOTALES:</td>
                            <td style="border: 1px solid #999; padding: 6px; text-align: right;">$${totalFacturado.toFixed(2)}</td>
                            <td style="border: 1px solid #999; padding: 6px; text-align: right;">$${totalCostoDirecto.toFixed(2)}</td>
                            <td style="border: 1px solid #999; padding: 6px; text-align: right;">$${totalGastosFijos.toFixed(2)}</td>
                            <td style="border: 1px solid #999; padding: 6px; text-align: right;">$${totalImpuestos.toFixed(2)}</td>
                            <td style="border: 1px solid #999; padding: 6px; text-align: right;" class="${totalGananciaNeta < 0 ? 'valor-negativo' : 'valor-positivo'}">$${totalGananciaNeta.toFixed(2)}</td>
                            <td style="border: 1px solid #999; padding: 6px; text-align: center;">${totalMargen.toFixed(1)}%</td>
                        </tr>
                    </tfoot>`;
            }
            
            tablaHTML += `</table>`;
            
            let tituloTabla = `DETALLE MENSUAL - ${PERIODO_COMPLETO}`;
            if (totalPaginasTabla > 1) {
                tituloTabla += ` (Página ${paginaTabla + 1} de ${totalPaginasTabla})`;
            }
            
            html += `
                <div class="p-page horizontal">
                    <div style="border-bottom:2px solid #0078D4; font-size: 12pt; margin-bottom:15px; padding-bottom:5px; font-weight:bold;">
                        ${tituloTabla}
                    </div>
                    ${tablaHTML}
                    
                    <!-- FOOTER -->
                    <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9pt;">
                        <div style="text-align: left;">
                            Página ${numPaginaActual} de ${totalPaginasGeneral}<br>
                            Total Registros: ${DATOS_MENSUALES.length}<br>
                            <span style="${TOTAL_GANANCIA >= 0 ? '' : 'color:#d32f2f;'}">Total Ganancia/Pérdida: $${TOTAL_GANANCIA.toFixed(2)} - ${MARGEN.toFixed(1)}%</span>
                        </div>
                        <div style="text-align: center;">
                            <strong>PDL Visiones</strong><br>
                            www.pdlvisiones.com<br>
                            "Donde tu visión toma forma"
                        </div>
                        <div style="text-align: right;">
                            <strong>PDL VISIONES - SISFACT</strong><br>
                            Sistema Integral de Facturación y Control<br>
                            Reporte de Rentabilidad y Costos
                        </div>
                    </div>
                    
                    <div style="position: absolute; bottom: 0.3in; left: 0.5in; right: 0.5in; text-align: center; font-size: 8pt; color: #666;">
                        Generado por: ${USER} | ${FECHA_CORTA} ${HORA}
                    </div>
                </div>`;
        }
        
        paginaActual += totalPaginasTabla;
    } else {
        // Para otros tipos de reporte (catalogo, consolidado, detallado)
        const tablaOriginal = document.querySelector('.table-responsive table');
        if (tablaOriginal) {
            const theadOriginal = tablaOriginal.querySelector('thead');
            const tfootOriginal = tablaOriginal.querySelector('tfoot');
            const tbodyOriginal = tablaOriginal.querySelector('tbody');
            
            if (tbodyOriginal) {
                const filas = Array.from(tbodyOriginal.querySelectorAll('tr'));
                const totalFilas = filas.length;
                const totalPaginasTabla = Math.ceil(totalFilas / FILAS_POR_PAGINA);
                const totalPaginasGeneral = paginaActual + totalPaginasTabla - 1;
                
                for (let paginaTabla = 0; paginaTabla < totalPaginasTabla; paginaTabla++) {
                    const numPaginaActual = paginaActual + paginaTabla;
                    
                    const tablaPagina = document.createElement('table');
                    tablaPagina.className = tablaOriginal.className;
                    tablaPagina.style.width = '100%';
                    tablaPagina.style.borderCollapse = 'collapse';
                    tablaPagina.style.fontSize = '10pt';
                    
                    // Determinar cuántas columnas eliminar según el tipo de reporte
                    let columnasAEliminar = 0;
                    if (TIPO_REPORTE == 'consolidado') {
                        columnasAEliminar = 2; // Eliminar Detalle y Ficha
                    } else if (TIPO_REPORTE == 'detallado') {
                        columnasAEliminar = 1; // Solo eliminar Ficha (la última columna)
                    } else if (TIPO_REPORTE == 'catalogo') {
                        columnasAEliminar = 2; // Eliminar Detalle y Ficha en catálogo también
                    }
                    
                    // Clonar y procesar THEAD
                    if (theadOriginal) {
                        const theadClone = theadOriginal.cloneNode(true);
                        const headers = theadClone.querySelectorAll('th');
                        
                        if (TIPO_REPORTE == 'catalogo') {
                            // Para catálogo, eliminar específicamente las columnas de Detalle e Ficha
                            const headersArray = Array.from(headers);
                            if (headersArray.length >= 10) {
                                headersArray[9].remove(); // Elimina Ficha
                                headersArray[8].remove(); // Elimina Detalle (columna del tooltip)
                            }
                        } else {
                            // Para otros reportes, eliminar las últimas N columnas
                            for (let i = 0; i < columnasAEliminar; i++) {
                                if (headers.length > 0) {
                                    headers[headers.length - 1].remove();
                                }
                            }
                        }
                        tablaPagina.appendChild(theadClone);
                    }
                    
                    // Procesar TBODY
                    const tbodyPagina = document.createElement('tbody');
                    const inicio = paginaTabla * FILAS_POR_PAGINA;
                    const fin = Math.min(inicio + FILAS_POR_PAGINA, totalFilas);
                    
                    for (let i = inicio; i < fin; i++) {
                        const filaClone = filas[i].cloneNode(true);
                        const celdas = filaClone.querySelectorAll('td');
                        
                        if (TIPO_REPORTE == 'catalogo') {
                            const celdasArray = Array.from(celdas);
                            if (celdasArray.length >= 10) {
                                celdasArray[9].remove(); // Elimina Ficha
                                celdasArray[8].remove(); // Elimina Detalle
                            }
                        } else {
                            for (let j = 0; j < columnasAEliminar; j++) {
                                if (celdas.length > 0) {
                                    celdas[celdas.length - 1].remove();
                                }
                            }
                        }
                        
                        // Alinear columnas según contenido
                        const celdasRestantes = filaClone.querySelectorAll('td');
                        celdasRestantes.forEach((celda, index) => {
                            const texto = celda.textContent;
                            if (texto.includes('$')) {
                                celda.style.textAlign = 'right';
                            } else if (texto.includes('%')) {
                                celda.style.textAlign = 'center';
                            }
                        });
                        
                        tbodyPagina.appendChild(filaClone);
                    }
                    tablaPagina.appendChild(tbodyPagina);
                    
                    // Procesar TFOOT (solo en última página)
                    if (tfootOriginal && paginaTabla === totalPaginasTabla - 1) {
                        const tfootClone = tfootOriginal.cloneNode(true);
                        const filasPie = tfootClone.querySelectorAll('tr');
                        
                        filasPie.forEach(fila => {
                            const celdasPie = fila.querySelectorAll('th, td');
                            
                            if (TIPO_REPORTE == 'catalogo') {
                                const celdasArray = Array.from(celdasPie);
                                if (celdasArray.length >= 10) {
                                    celdasArray[9].remove(); // Elimina Ficha
                                    celdasArray[8].remove(); // Elimina Detalle
                                }
                            } else {
                                for (let j = 0; j < columnasAEliminar; j++) {
                                    if (celdasPie.length > 0) {
                                        celdasPie[celdasPie.length - 1].remove();
                                    }
                                }
                            }
                        });
                        
                        tablaPagina.appendChild(tfootClone);
                    }
                    
                    // Título de la tabla según tipo de reporte
                    let tituloTabla;
                    if (TIPO_REPORTE == 'catalogo') {
                        tituloTabla = `CATÁLOGO DE SERVICIOS`;
                    } else if (TIPO_REPORTE == 'detallado') {
                        tituloTabla = `DETALLE DE FACTURACIÓN - ${PERIODO_COMPLETO}`;
                    } else {
                        tituloTabla = `CONSOLIDADO RENTABILIDAD POR SERVICIO - ${PERIODO_COMPLETO}`;
                    }
                    
                    if (totalPaginasTabla > 1) {
                        tituloTabla += ` (Página ${paginaTabla + 1} de ${totalPaginasTabla})`;
                    }
                    
                    html += `
                        <div class="p-page horizontal">
                            <div style="border-bottom:2px solid #0078D4; font-size: 12pt; margin-bottom:15px; padding-bottom:5px; font-weight:bold;">
                                ${tituloTabla}
                            </div>
                            ${tablaPagina.outerHTML}
                            
                            <!-- FOOTER -->
                            <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9pt;">
                                <div style="text-align: left;">
                                    Página ${numPaginaActual} de ${totalPaginasGeneral}<br>
                                    Total Registros: ${TOTAL_REGISTROS}<br>
                                    <span style="${TOTAL_GANANCIA >= 0 ? '' : 'color:#d32f2f;'}">Total Ganancia/Pérdida: $${TOTAL_GANANCIA.toFixed(2)} - ${MARGEN.toFixed(1)}%</span>
                                </div>
                                <div style="text-align: center;">
                                    <strong>PDL Visiones</strong><br>
                                    www.pdlvisiones.com<br>
                                    "Donde tu visión toma forma"
                                </div>
                                <div style="text-align: right;">
                                    <strong>PDL VISIONES - SISFACT</strong><br>
                                    Sistema Integral de Facturación y Control<br>
                                    Reporte de Rentabilidad y Costos
                                </div>
                            </div>
                            
                            <div style="position: absolute; bottom: 0.3in; left: 0.5in; right: 0.5in; text-align: center; font-size: 8pt; color: #666;">
                                Generado por: ${USER} | ${FECHA_CORTA} ${HORA}
                            </div>
                        </div>`;
                }
                
                paginaActual += totalPaginasTabla;
            }
        }
    }

    // Calcular el total de páginas
    const totalPaginasFinal = paginaActual - 1;
    
    // Reemplazar placeholders
    html = html.replace(/<span class="total-paginas"><\/span>/g, totalPaginasFinal);

    const win = window.open('', '_blank');
    win.document.write(`<html><head>
        <style>
            @page { 
                size: landscape;
                margin: 0.5in;
            }
            body { 
                font-family: 'Segoe UI', Arial, sans-serif; 
                background: #333; 
                margin:0; 
                padding:0; 
                font-size: 11pt;
            }
            .p-page { 
                background: white; 
                width: 11in;
                min-height: 8.5in;
                margin: 0 auto; 
                padding: 0.5in; 
                position: relative; 
                box-sizing: border-box; 
                page-break-after: always; 
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .p-page.horizontal {
                width: 11in;
                height: 8.5in;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                font-size: 10pt; 
            }
            th { 
                background: #f0f0f0; 
                border: 1px solid #999; 
                padding: 6px; 
                font-weight: bold; 
            }
            td { 
                border: 1px solid #ccc; 
                padding: 5px; 
            }
            .fila-perdida { 
                background-color: #ffebee !important; 
            }
            .fila-ganancia { 
                background-color: #e8f5e9 !important; 
            }
            .valor-negativo { 
                color: #d32f2f !important; 
                font-weight: bold; 
            }
            .valor-positivo { 
                color: #2e7d32 !important; 
                font-weight: bold; 
            }
            @media print { 
                body { 
                    background: none; 
                } 
                .p-page { 
                    margin: 0; 
                    box-shadow: none; 
                } 
            }
        </style>
    </head><body>${html}</body></html>`);
    
    win.document.close();
    setTimeout(() => { 
        win.print(); 
        setTimeout(() => { win.close(); }, 500);
    }, 700);
}




        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('open');
            } else {
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
            }
        }
        
        function abrirPanelTemas() {
            document.getElementById('themePanel').classList.add('open');
            document.getElementById('themeOverlay').classList.add('open');
            themePanelOpen = true;
        }
        
        function cerrarPanelTemas() {
            document.getElementById('themePanel').classList.remove('open');
            document.getElementById('themeOverlay').classList.remove('open');
            themePanelOpen = false;
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themePanelOpen) {
                cerrarPanelTemas();
            }
        });
        
        document.querySelectorAll('.win-theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-theme-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const theme = this.dataset.theme;
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('tema_windows', theme);
            });
        });
        
        document.querySelectorAll('.win-color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-color-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const color = this.dataset.color;
                document.documentElement.style.setProperty('--win-accent', color);
                document.documentElement.style.setProperty('--win-accent-light', color + '20');
                localStorage.setItem('color_accent', color);
            });
        });
        
        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('guardar_configuracion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tema_windows: tema,
                    color_accent: color,
                    sidebar_mini: sidebarMini
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Configuración guardada!',
                        text: 'Los cambios se han aplicado correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    Swal.fire('Error', 'No se pudo guardar la configuración', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Error', 'Error de conexión', 'error');
            });
            
            cerrarPanelTemas();
        }
		
function exportarRentabilidadPDF() {
    // --- 1. Definición de Constantes ---
    const TITULO_PRINCIPAL = "PDL VISIONES - SISFACT";
    const NOMBRE_SISTEMA = "Sistema Integral de Facturación y Control";
    const ESLOGAN = "PDL VISIONES - Donde tu visión toma forma";
    
    const LOGO_PROYECTO = '<?= $logo_base64; ?>';
    const TIPO_REPORTE_VAL = '<?= $tipo_reporte ?>';
    const USUARIO_ACTUAL = '<?= addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
    const PERIODO = '<?= date("d/m/Y", strtotime($fecha_desde)) ?> - <?= date("d/m/Y", strtotime($fecha_hasta)) ?>';
    const FECHA = <?= json_encode(date('d/m/Y h:i:s A')) ?>;
    const FECHA_CORTA = <?= json_encode(date('d/m/Y')) ?>;
    const HORA = "<?= date('h:i:s A') ?>";
    
    // ===== CONSTANTES PARA PORCENTAJES Y DATOS =====
    const NUM_MESES = <?= $num_meses ?>;
    const PERIODO_COMPLETO = NUM_MESES > 1 ? `${PERIODO} (${NUM_MESES} meses)` : `${PERIODO} (1 mes)`;
    
    // Costos unitarios (sin emojis, con texto plano)
    const COSTS = { 
        bond: '<?= number_format($costo_h_bond, 2) ?>', 
        cart: '<?= number_format($costo_h_cart, 2) ?>', 
        foto: '<?= number_format($costo_h_foto, 2) ?>', 
        tbn: '<?= number_format($costo_t_bn, 2) ?>', 
        tcl: '<?= number_format($costo_t_cl, 2) ?>' 
    };
    
    // Costos de micas
    const COSTOS_MICAS = {
        carta: <?= ($precio_mica_carta ?? 400) / ($uds_mica_carta ?? 100) ?>,
        carnet: <?= ($precio_mica_carnet ?? 100) / ($uds_mica_carnet ?? 100) ?>
    };
    
    // Gastos fijos mensuales
    const GASTO_SALARIO_MENSUAL = <?= (float)$gasto_salario_mensual ?>;
    const GASTO_ELECTRICIDAD_MENSUAL = <?= (float)$gasto_electricidad_mensual ?>;
    const GASTO_TRANSPORTACION_MENSUAL = <?= (float)$gasto_transportacion_mensual ?>;
    const GASTO_MOVIL_MENSUAL = <?= (float)$gasto_movil_mensual ?>;
    const DEPRECIACION_MENSUAL = <?= (float)$depreciacion_mensual ?>;
    const SEGURIDAD_SOCIAL_MENSUAL = <?= (float)$seguridad_social_mensual ?>;
    const GASTOS_FIJOS_MENSUALES = <?= (float)$gastos_fijos_mensuales ?>;
    
    // Totales
    const TOTAL_FACTURADO = <?= (float)$total_facturado_periodo ?>;
    const TOTAL_COSTOS_DIRECTOS = <?= (float)$total_costos_directos_periodo ?>;
    const TOTAL_GASTOS_FIJOS = <?= (float)$gastos_fijos_acumulados ?>;
    const IMPUESTO_VENTAS = <?= (float)$impuesto_ventas_periodo ?? 0 ?>;
    const CONTRIBUCION_LOCAL = <?= (float)$contribucion_local_periodo ?? 0 ?>;
    const TOTAL_IMPUESTOS = <?= (float)$total_impuestos_periodo ?? 0 ?>;
    const TOTAL_GANANCIA_NETA = <?= (float)$total_ganancia_neta_periodo ?>;
    const MARGEN = <?= ($total_facturado_periodo > 0) ? ($total_ganancia_neta_periodo / $total_facturado_periodo) * 100 : 0 ?>;
    
    // PORCENTAJES
    const PORCENTAJES = {
        impuesto_ventas: <?= (float)$tasa_impuesto_ventas ?>,
        contribucion_local: <?= (float)$tasa_contribucion_local ?>,
        gastos_indirectos: <?= (float)$porcentaje_indirectos ?>,
        seguridad_social: <?= (float)$tasa_seguridad_social ?>,
        depreciacion_anual: <?= (float)$tasa_depreciacion ?>
    };
    
    // Márgenes por categoría
    const MARGENES = {
        cat8_general: <?= $_SESSION['margen_cat8_general'] ?? 75 ?>,
        cat8_cunos: <?= $_SESSION['margen_cat8_cunos'] ?? 40 ?>,
        cat8_tinta: <?= $_SESSION['margen_cat8_tinta'] ?? 70 ?>,
        cat9_general: <?= $_SESSION['margen_cat9_general'] ?? 75 ?>,
        cat10_general: <?= $_SESSION['margen_cat10_general'] ?? 80 ?>,
        cat11_general: <?= $_SESSION['margen_cat11_general'] ?? 75 ?>,
        cat12_general: <?= $_SESSION['margen_cat12_general'] ?? 70 ?>
    };
    
    // Datos de depreciación
    const FECHA_ADQUISICION = "<?= isset($fecha_adquisicion) ? $fecha_adquisicion : date('Y-m-d') ?>";
    const VALOR_EQUIPOS = <?= (float)($valor_total_equipos ?? 0) ?>;
    const MESES_TRANSCURRIDOS = <?= (int)($meses_transcurridos_global ?? 0) ?>;
    const VIDA_UTIL_MESES = 60;
    const MESES_RESTANTES = <?= (int)($meses_restantes ?? 0) ?>;
    const ESTADO_DEPRECIACION = "<?= $estado_depreciacion ?? 'En proceso' ?>";
    const VALOR_RESTANTE = <?= max(0, ($valor_total_equipos ?? 0) - (($depreciacion_mensual ?? 0) * ($meses_transcurridos_global ?? 0))) ?>;
    const TOTAL_IMPRESORAS = <?= (int)($total_impresoras ?? 0) ?>;
    const FACTOR_INDIRECTOS = <?= 1 + $factor_indirectos ?>;
    
    // ===== CONSTANTES PARA EL FOOTER =====
    const TOTAL_REGISTROS = <?= ($tipo_reporte == 'catalogo') ? count($servicios_catalogo) : count($facturas_periodo) ?>;
    const TOTAL_GANANCIA = TOTAL_GANANCIA_NETA; 

    let nombreReporte = "REPORTE FINANCIERO";
    switch(TIPO_REPORTE_VAL) {
        case 'consolidado': nombreReporte = "ANÁLISIS DE RENTABILIDAD CONSOLIDADO POR SERVICIO"; break;
        case 'mensual': nombreReporte = "ANÁLISIS DE RENTABILIDAD MENSUAL"; break;
        case 'detallado': nombreReporte = "ANÁLISIS DE RENTABILIDAD DETALLADO CRONOLÓGICO DE FACTURACIÓN"; break;
        case 'catalogo': nombreReporte = "CATÁLOGO TÉCNICO DE COSTOS Y SERVICIOS"; break;
    }

    function limpiarTexto(texto) {
        if (!texto) return '';
        return texto
            .replace(/½/g, '1/2')
            .replace(/″/g, '"')
            .replace(/&nbsp;/g, ' ')
            .replace(/<[^>]*>/g, '')
            .replace(/\s+/g, ' ')
            .replace(/[^\x20-\x7E]/g, '') // Eliminar caracteres no ASCII
            .trim();
    }

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'p',
            unit: 'mm',
            format: 'letter',
            putOnlyUsedFonts: true,
            floatPrecision: 2
        });
        
        // Configurar fuente para caracteres especiales
        doc.setFont('helvetica', 'normal');
        
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 15;

        // ===== FUNCIÓN PARA DIBUJAR ENCABEZADO EN CADA PÁGINA =====
        function dibujarEncabezado(pagina, esCaratula = false) {
            doc.setFontSize(7).setTextColor(100).setFont('helvetica', 'normal');
            doc.text(`Generado por: ${USUARIO_ACTUAL} | ${FECHA_CORTA} ${HORA}`, pageWidth / 2, 8, { align: 'center' });
            
            if (LOGO_PROYECTO.includes('base64')) {
                if (!esCaratula) {
                    doc.addImage(LOGO_PROYECTO, 'PNG', margin, 12, 15, 15);
                }
            }
            doc.setDrawColor(200).line(margin, 20, pageWidth - margin, 20);
        }

        // ===== CARÁTULA 1: MISMO DISEÑO CON ICONOS FONTAWESOME =====
        dibujarEncabezado(1, true); 
        
        // Logo centrado
        if (LOGO_PROYECTO.includes('base64')) {
            doc.addImage(LOGO_PROYECTO, 'PNG', (pageWidth - 35) / 2, 10, 35, 35);
        }
        
        // Títulos
        doc.setFont('helvetica', 'bold').setFontSize(18).setTextColor(0, 120, 212);
        doc.text(TITULO_PRINCIPAL, pageWidth / 2, 50, { align: 'center' });
        doc.setFontSize(12).setTextColor(0).text(nombreReporte, pageWidth / 2, 55, { align: 'center' });
        doc.setFontSize(9).setFont('helvetica', 'normal').setTextColor(100).text(`Periodo: ${PERIODO_COMPLETO}`, pageWidth / 2, 59, { align: 'center' });
        
        // ===== PRIMERA FILA: RESUMEN DEL PERÍODO =====
        let yActual = 65;
        
        if (TIPO_REPORTE_VAL == 'catalogo') {
            // Resumen Catálogo
            doc.setFontSize(10).setFont('helvetica', 'bold').setTextColor(0, 120, 212);
            doc.text('Resumen del Catálogo', margin, yActual);
            yActual += 5;
            
            doc.autoTable({
                startY: yActual,
                margin: { left: margin, right: pageWidth - margin - 80 },
                tableWidth: 80,
                theme: 'plain',
                body: [
                    ['Total Servicios:', `<?= count($servicios_catalogo) ?>`],
                    ['Precio Venta Total:', `$<?= number_format($total_precio_venta_catalogo, 2) ?>`],
                    ['Costo Directo Total:', `$<?= number_format($total_costo_directo_catalogo, 2) ?>`],
                    ['Utilidad Bruta Total:', { content: `$<?= number_format($total_utilidad_bruta_catalogo, 2) ?>`, styles: { textColor: <?= $total_utilidad_bruta_catalogo >= 0 ? '[40, 167, 69]' : '[211, 47, 47]' ?> } }]
                ],
                styles: { fontSize: 9, cellPadding: 1, lineHeight: 1, font: 'helvetica' },
                columnStyles: { 0: { fontStyle: 'bold' }, 1: { halign: 'right', fontStyle: 'bold' } }
            });
        } else {
            // Resumen Período
            doc.setFontSize(10).setFont('helvetica', 'bold').setTextColor(0, 120, 212);
            doc.text('Resumen del Período', margin, yActual);
            yActual += 5;
            
            doc.autoTable({
                startY: yActual,
                margin: { left: margin, right: pageWidth - margin - 80 },
                tableWidth: 80,
                theme: 'plain',
                body: [
                    ['Período:', PERIODO_COMPLETO],
                    ['Total Facturado:', `$${TOTAL_FACTURADO.toFixed(2)}`],
                    ['Costos Directos:', `$${TOTAL_COSTOS_DIRECTOS.toFixed(2)}`],
                    ['Ganancia/Pérdida Neta:', { content: `$${TOTAL_GANANCIA_NETA.toFixed(2)}`, styles: { textColor: TOTAL_GANANCIA_NETA >= 0 ? [40, 167, 69] : [211, 47, 47], fontStyle: 'bold' } }]
                ],
                styles: { fontSize: 9, cellPadding: 1, lineHeight: 1, font: 'helvetica' },
                columnStyles: { 0: { fontStyle: 'bold' }, 1: { halign: 'right', fontStyle: 'bold' } }
            });
        }
        
        // Datos Generales (columna derecha)
        const lastY = doc.lastAutoTable.finalY || yActual + 30;
        doc.setFontSize(10).setFont('helvetica', 'bold').setTextColor(0, 120, 212);
        doc.text('Datos Generales', pageWidth - margin - 70, yActual - 5);
        
        doc.autoTable({
            startY: yActual,
            margin: { left: pageWidth - margin - 70, right: margin },
            tableWidth: 70,
            theme: 'plain',
            body: [
                ['Impresoras activas:', TOTAL_IMPRESORAS.toString()],
                ['% Gastos Indirectos:', `${PORCENTAJES.gastos_indirectos}%`],
                ['Factor indirecto:', `x ${FACTOR_INDIRECTOS.toFixed(2)}`],
                ['Período (meses):', `${NUM_MESES}`]
            ],
            styles: { fontSize: 9, cellPadding: 1, lineHeight: 1, font: 'helvetica' },
            columnStyles: { 0: { fontStyle: 'bold' }, 1: { halign: 'right', fontStyle: 'bold' } }
        });
        
        yActual = Math.max(lastY, doc.lastAutoTable.finalY) + 7;
        
        // ===== SEGUNDA FILA: COSTOS UNITARIOS EN DOS COLUMNAS =====
        doc.setFontSize(11).setFont('helvetica', 'bold').setTextColor(0, 120, 212);
        doc.text('COSTOS UNITARIOS', margin, yActual);
        yActual += 5;
        
        // Columna Izquierda: Hasta Tintas
        doc.autoTable({
            startY: yActual,
            margin: { left: margin, right: pageWidth / 2 + 5 },
            tableWidth: (pageWidth / 2) - margin - 10,
            theme: 'grid',
            head: [['Concepto', 'Valor']],
            body: [
                ['Papel Bond (hoja)', `$${COSTS.bond}`],
                ['Cartulina (hoja)', `$${COSTS.cart}`],
                ['Papel Fotografico (hoja)', `$${COSTS.foto}`],
                ['Tinta B/N (pagina)', `$${COSTS.tbn}`],
                ['Tinta Color (pagina)', `$${COSTS.tcl}`]
            ],
            styles: { fontSize: 9, cellPadding: 2, lineHeight: 1, font: 'helvetica' },
            headStyles: { fillColor: [240, 240, 240], textColor: 0, fontStyle: 'bold' },
            columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } }
        });
        
        // Columna Derecha: Micas y Otros
        doc.autoTable({
            startY: yActual,
            margin: { left: pageWidth / 2 + 5, right: margin },
            tableWidth: (pageWidth / 2) - margin - 10,
            theme: 'grid',
            head: [['Concepto', 'Valor']],
            body: [
                ['Mica Tamano CARTA', `$${COSTOS_MICAS.carta.toFixed(2)}`],
                ['Mica Tamano CARNET', `$${COSTOS_MICAS.carnet.toFixed(2)}`],
                ['Kit Tinta Original', `$<?= number_format($precio_kit, 2) ?>`],
                ['Costo indirecto + factor', { content: `x ${FACTOR_INDIRECTOS.toFixed(2)}`, styles: { textColor: [0, 120, 212], fontStyle: 'bold' } }]
            ],
            styles: { fontSize: 9, cellPadding: 2, lineHeight: 1, font: 'helvetica' },
            headStyles: { fillColor: [240, 240, 240], textColor: 0, fontStyle: 'bold' },
            columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } }
        });
        
        yActual = Math.max(doc.lastAutoTable.finalY, doc.lastAutoTable.finalY) + 15;
        
        // ===== SECCIÓN DE PORCENTAJES APLICADOS =====
        doc.setFontSize(11).setFont('helvetica', 'bold').setTextColor(0, 120, 212);
        doc.text('PORCENTAJES APLICADOS EN EL CALCULO', margin, yActual);
        yActual += 5;
        
        // Columna Izquierda: Porcentajes generales
        doc.autoTable({
            startY: yActual,
            margin: { left: margin, right: pageWidth / 2 + 5 },
            tableWidth: (pageWidth / 2) - margin - 10,
            theme: 'grid',
            head: [['Concepto', '%', 'Aplicacion']],
            body: [
                ['Impuesto sobre Ventas', `${PORCENTAJES.impuesto_ventas}%`, 's/ventas'],
                ['Contribucion Local', `${PORCENTAJES.contribucion_local}%`, 's/ventas'],
                ['Gastos Indirectos', `${PORCENTAJES.gastos_indirectos}%`, 's/costo directo'],
                ['Seguridad Social', `${PORCENTAJES.seguridad_social}%`, 's/nomina'],
                ['Depreciacion Anual', `${PORCENTAJES.depreciacion_anual}%`, 's/equipos'],
                ['TOTAL IMPUESTOS', { content: `${(PORCENTAJES.impuesto_ventas + PORCENTAJES.contribucion_local)}%`, styles: { fillColor: [0, 120, 212], textColor: 255, fontStyle: 'bold' } }, 's/ventas']
            ],
            styles: { fontSize: 8.5, cellPadding: 2, lineHeight: 1, font: 'helvetica' },
            headStyles: { fillColor: [0, 120, 212], textColor: 255, fontStyle: 'bold' },
            columnStyles: {
                0: { halign: 'left', cellWidth: 'auto' },
                1: { halign: 'center', cellWidth: 15, fontStyle: 'bold', textColor: [0, 120, 212] },
                2: { halign: 'left', cellWidth: 25 }
            }
        });
        
        // Columna Derecha: Márgenes por categoría
        doc.autoTable({
            startY: yActual,
            margin: { left: pageWidth / 2 + 5, right: margin },
            tableWidth: (pageWidth / 2) - margin - 10,
            theme: 'grid',
            head: [['Categoria', 'Utilidad']],
            body: [
                ['Utiles/Papeleria/Oficina', { content: `${(100-MARGENES.cat8_general).toFixed(1)}%`, styles: { textColor: [40, 167, 69] } }],
                ['Cuños', { content: `${(100-MARGENES.cat8_cunos).toFixed(1)}%`, styles: { textColor: [255, 193, 7] } }],
                ['Tinta ID 59', { content: `${(100-MARGENES.cat8_tinta).toFixed(1)}%`, styles: { textColor: [23, 162, 184] } }],
                ['Alimentos', { content: `${(100-MARGENES.cat9_general).toFixed(1)}%`, styles: { textColor: [40, 167, 69] } }],
                ['Tecnologia', { content: `${(100-MARGENES.cat10_general).toFixed(1)}%`, styles: { textColor: [255, 193, 7] } }],
                ['Piezas', { content: `${(100-MARGENES.cat11_general).toFixed(1)}%`, styles: { textColor: [40, 167, 69] } }],
                ['Limpieza', { content: `${(100-MARGENES.cat12_general).toFixed(1)}%`, styles: { textColor: [40, 167, 69] } }],
                ['Margen Promedio', { content: `${(((100-MARGENES.cat8_general) + (100-MARGENES.cat9_general) + (100-MARGENES.cat10_general) + (100-MARGENES.cat11_general) + (100-MARGENES.cat12_general)) / 5).toFixed(1)}%`, styles: { fillColor: [240, 240, 240], fontStyle: 'bold', textColor: [0, 120, 212] } }]
            ],
            styles: { fontSize: 8.5, cellPadding: 2, lineHeight: 1, font: 'helvetica' },
            headStyles: { fillColor: [40, 167, 69], textColor: 255, fontStyle: 'bold' },
            columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } }
        });
        
        yActual = Math.max(doc.lastAutoTable.finalY, doc.lastAutoTable.finalY) + 3;
        
        // Nota aclaratoria
        doc.setFontSize(12).setTextColor(100).setFont('helvetica', 'bolditalic');
        doc.text(`Total impuestos: ${(PORCENTAJES.impuesto_ventas + PORCENTAJES.contribucion_local)}% s/ventas | Depreciacion: ${PORCENTAJES.depreciacion_anual}% anual (5 años)`, pageWidth / 2, yActual, { align: 'center' });

        // ===== PÁGINA 2: GASTOS FIJOS MENSUALES (SOLO PARA REPORTES CON FACTURACIÓN) =====
        if (TIPO_REPORTE_VAL !== 'catalogo') {
            doc.addPage();
            dibujarEncabezado(2, false);
            
            let yGastos = 20;
            
            doc.setFont('helvetica', 'bold').setFontSize(14).setTextColor(0, 120, 212);
            doc.text(`Detalle de Gastos - ${PERIODO_COMPLETO}`, pageWidth / 2, yGastos, { align: 'center' });
            yGastos += 7;
            
            // Columna Izquierda: Gastos Fijos Mensuales
            doc.autoTable({
                startY: yGastos,
                margin: { left: margin, right: pageWidth / 2 + 5 },
                tableWidth: (pageWidth / 2) - margin - 10,
                theme: 'grid',
                head: [['Gastos Fijos Mensuales', 'Monto']],
                body: [
                    ['Salario', `$${GASTO_SALARIO_MENSUAL.toFixed(2)}`],
                    ['Electricidad', `$${GASTO_ELECTRICIDAD_MENSUAL.toFixed(2)}`],
                    ['Transportacion', `$${GASTO_TRANSPORTACION_MENSUAL.toFixed(2)}`],
                    ['Lineas Moviles', `$${GASTO_MOVIL_MENSUAL.toFixed(2)}`],
                    ['Depreciacion', `$${DEPRECIACION_MENSUAL.toFixed(2)}`],
                    ['Seguridad Social', `$${SEGURIDAD_SOCIAL_MENSUAL.toFixed(2)}`],
                    ['Fecha Adq. promedio', FECHA_ADQUISICION],
                    ['Valor restante', { content: `$${VALOR_RESTANTE.toFixed(2)}`, styles: { textColor: [23, 162, 184] } }],
                    ['Meses transcurridos', `${MESES_TRANSCURRIDOS} de ${VIDA_UTIL_MESES}`],
                    ['Meses restantes', { content: `${MESES_RESTANTES} meses`, styles: { textColor: MESES_TRANSCURRIDOS < VIDA_UTIL_MESES ? [40, 167, 69] : [220, 53, 69] } }],
                    ['Estado', ESTADO_DEPRECIACION],
                    ['TOTAL MENSUAL', { content: `$${GASTOS_FIJOS_MENSUALES.toFixed(2)}`, styles: { fontStyle: 'bold', fillColor: [240, 240, 240] } }]
                ],
                styles: { fontSize: 8, cellPadding: 1.5, lineHeight: 1, font: 'helvetica' },
                headStyles: { fillColor: [0, 120, 212], textColor: 255, fontStyle: 'bold' },
                columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } }
            });
            
            // Columna Derecha: Totales del Período
            doc.autoTable({
                startY: yGastos,
                margin: { left: pageWidth / 2 + 5, right: margin },
                tableWidth: (pageWidth / 2) - margin - 10,
                theme: 'grid',
                head: [[`Totales del Periodo (${NUM_MESES} ${NUM_MESES == 1 ? 'mes' : 'meses'})`, 'Monto']],
                body: [
                    ['Gastos Fijos Acumulados', `$${TOTAL_GASTOS_FIJOS.toFixed(2)}`],
                    ['Impuesto Ventas', `$${IMPUESTO_VENTAS.toFixed(2)}`],
                    ['Contribucion Local', `$${CONTRIBUCION_LOCAL.toFixed(2)}`],
                    ['TOTAL CARGAS', { content: `$${TOTAL_IMPUESTOS.toFixed(2)}`, styles: { fontStyle: 'bold', fillColor: [240, 240, 240] } }]
                ],
                styles: { fontSize: 8.5, cellPadding: 2, lineHeight: 1, font: 'helvetica' },
                headStyles: { fillColor: [0, 120, 212], textColor: 255, fontStyle: 'bold' },
                columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } }
            });
            
            const lastYGastos = Math.max(doc.lastAutoTable.finalY, doc.lastAutoTable.finalY) + 15;
            
            // Resumen Financiero
            doc.setDrawColor(0, 120, 212).setLineWidth(0.5);
            doc.roundedRect(margin, lastYGastos + 35, pageWidth - (margin * 2), 25, 2, 2, 'S');
            
            doc.setFontSize(12).setFont('helvetica', 'bold').setTextColor(0, 120, 212);
            doc.text('RESUMEN FINANCIERO CONSOLIDADO', margin + 5, lastYGastos + 41);
            
			// Texto "Total Ingresos:" en color negro
			doc.setTextColor(0);
			doc.text(`Total Ingresos:`, margin + 5, lastYGastos + 48);

			// Valor en azul
			doc.setTextColor(0, 0, 255); // RGB para azul
			doc.text(` $${TOTAL_FACTURADO.toFixed(2)}`, margin + 5 + doc.getTextWidth('Total Ingresos:'), lastYGastos + 48);

			// Texto "Gastos e Impuestos Totales:" en color negro
			doc.setTextColor(0);
			doc.text(`Gastos e Impuestos Totales:`, pageWidth / 2, lastYGastos + 48);

			// Valor en azul
			doc.setTextColor(0, 0, 255); // RGB para azul
			doc.text(` $${(TOTAL_GASTOS_FIJOS + TOTAL_IMPUESTOS).toFixed(2)}`, pageWidth / 2 + doc.getTextWidth('Gastos e Impuestos Totales:'), lastYGastos + 48);
            
            const colorNeta = TOTAL_GANANCIA_NETA >= 0 ? [40, 167, 69] : [220, 53, 69];
            doc.setTextColor(colorNeta[0], colorNeta[1], colorNeta[2]);
            doc.setFontSize(11).setFont('helvetica', 'bold');
            doc.text(`GANANCIA NETA DEL PERIODO: $${TOTAL_GANANCIA_NETA.toFixed(2)} (${MARGEN.toFixed(1)}%)`, margin + 5, lastYGastos + 55);
        }

        // ===== PÁGINA DE TABLA PRINCIPAL =====
        doc.addPage();
        dibujarEncabezado(3, false); 
        
        let headY = 20;
        doc.setFont('helvetica', 'bold').setFontSize(11).setTextColor(0, 120, 212).text('PDL VISIONES', margin + 18, headY);
        doc.setFontSize(12).setTextColor(100).setFont('helvetica', 'normal').text(NOMBRE_SISTEMA, margin + 18, headY + 4);
		// Línea 3: Reporte (primera parte)
		doc.setFontSize(11).setTextColor(100).setFont('helvetica', 'bold').text(`Reporte: ${nombreReporte}`, pageWidth - margin, headY + 8, { align: 'right' });

		// Línea 4: PERIODO_COMPLETO (debajo del reporte)
		doc.setFontSize(10).setTextColor(100).setFont('helvetica', 'normal').text(`Período: ${PERIODO_COMPLETO}`, pageWidth - margin, headY + 14, { align: 'right' });
        headY += 7;
        doc.setDrawColor(200).line(margin, headY + 8, pageWidth - margin, headY + 8);
        headY += 11;

        const tablaHTML = document.querySelector('.table-responsive table');
        
        // --- FILTRADO DINÁMICO DE COLUMNAS (DETALLE Y FICHA) ---
        const headersCompletos = Array.from(tablaHTML.querySelectorAll('thead th')).map(th => limpiarTexto(th.textContent)).slice(0, -1);
        const rowsCompletos = Array.from(tablaHTML.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).map(td => limpiarTexto(td.textContent)).slice(0, -1)
        );

        const indicesValidos = [];
        const headers = headersCompletos.filter((h, i) => {
            const txt = h.toUpperCase();
            if (txt.includes('DETALLE') || txt.includes('FICHA')) return false;
            indicesValidos.push(i);
            return true;
        });

        const rows = rowsCompletos.map(row => row.filter((_, i) => indicesValidos.includes(i)));
        
        // --- EXTRACCIÓN Y FILTRADO DE FOOTER ---
        let footerData = [];
        const tfootTr = tablaHTML.querySelector('tfoot tr');
        if (tfootTr) {
            const cellsCompletas = Array.from(tfootTr.querySelectorAll('th, td')).map(c => limpiarTexto(c.textContent)).slice(0, -1);
            const cells = cellsCompletas.filter((_, i) => indicesValidos.includes(i));
            let idxTotales = cells.findIndex(c => c.toUpperCase().includes('TOTAL'));

            if (TIPO_REPORTE_VAL === 'consolidado') {
                footerData = [[
                    { content: 'TOTALES:', colSpan: 2, styles: { halign: 'right', fontStyle: 'bold' } },
                    cells[idxTotales + 1] || '',
                    cells[idxTotales + 2] || '',
                    cells[idxTotales + 3] || '',
                    cells[idxTotales + 4] || '',
                    cells[idxTotales + 5] || '',
                    cells[idxTotales + 6] || '',
                    cells[idxTotales + 7] || ''
                ]];
			} else if (TIPO_REPORTE_VAL === 'mensual') {
				footerData = [[
					{ content: 'TOTALES:', colSpan: 2, styles: { halign: 'right', fontStyle: 'bold' } },
					{ content: cells[idxTotales + 1] || '', styles: { halign: 'left' } }, 
					{ content: cells[idxTotales + 2] || '', styles: { halign: 'left' } }, 
					{ content: cells[idxTotales + 3] || '', styles: { halign: 'left' } },  
					{ content: cells[idxTotales + 4] || '', styles: { halign: 'left' } },  
					{ content: cells[idxTotales + 5] || '', styles: { halign: 'left' } }, 
					{ content: cells[idxTotales + 6] || '', styles: { halign: 'left' } }  
				]];

            } else if (TIPO_REPORTE_VAL === 'catalogo') {
                footerData = [[
                    { content: 'TOTALES:', colSpan: 4, styles: { halign: 'right', fontStyle: 'bold' } },
                    cells[idxTotales + 1] || '', 
                    cells[idxTotales + 2] || '', 
                    cells[idxTotales + 3] || '', 
                    cells[idxTotales + 4] || ''  
                ]];
            } else if (TIPO_REPORTE_VAL === 'detallado') {
                footerData = [[
                    { content: 'TOTAL FACTURACION:', colSpan: 3, styles: { halign: 'right', fontStyle: 'bold' } },
                    cells[idxTotales + 1] || '',
                    cells[idxTotales + 2] || '',
                    cells[idxTotales + 3] || '',
                    cells[idxTotales + 4] || '',
                    cells[idxTotales + 5] || '',
                    cells[idxTotales + 6] || '',
                ]];
            } else {
                footerData = [cells];
            }
        }

        let colStyles = {};
        let tableFontSize = 7.5; 

        if (TIPO_REPORTE_VAL === 'consolidado') {
            colStyles = {
                0: { halign: 'center', cellWidth: 8 },
                1: { halign: 'left', cellWidth: 40 },
                2: { halign: 'center', cellWidth: 'auto' },
				8: { halign: 'center', cellWidth: 'auto' }
            };
        } else if (TIPO_REPORTE_VAL === 'mensual') {
            colStyles = {
                0: { halign: 'center', cellWidth: 10 },
                1: { halign: 'left', cellWidth: 25 },
                2: { halign: 'left', cellWidth: 25 },
                3: { halign: 'left', cellWidth: 25 },
                4: { halign: 'left', cellWidth: 25 },
                5: { halign: 'left', cellWidth: 25 },
                6: { halign: 'left', cellWidth: 25 },
            };
        } else if (TIPO_REPORTE_VAL === 'detallado') {
            tableFontSize = 6.2;
            colStyles = {
                0: { halign: 'center', cellWidth: 10 },
                1: { halign: 'center', cellWidth: 15 },
                2: { halign: 'left', cellWidth: 'auto' },
                3: { halign: 'left', cellWidth: 'auto' },
                4: { halign: 'center', cellWidth: 15 },
                5: { halign: 'right', cellWidth: 18 },
                6: { halign: 'right', cellWidth: 18 },
                7: { halign: 'right', cellWidth: 18 },
                8: { halign: 'right', cellWidth: 12, fontStyle: 'bold' },
                9: { halign: 'center', cellWidth: 12, fontStyle: 'bold' }
            };
        } else if (TIPO_REPORTE_VAL === 'catalogo') {
            colStyles = {
                0: { halign: 'center', cellWidth: 8 },
                1: { halign: 'center', cellWidth: 15 },
                2: { halign: 'left', cellWidth: 60 },
                3: { halign: 'left', cellWidth: 30 },
                4: { halign: 'right', cellWidth: 'auto' },
                5: { halign: 'right', cellWidth: 'auto' },
                6: { halign: 'right', cellWidth: 'auto', fontStyle: 'bold' },
                7: { halign: 'center', cellWidth: 'auto', fontStyle: 'bold' }
            };
        }

        doc.autoTable({
            head: [headers],
            body: rows,
            foot: footerData,
            startY: headY,
            margin: { left: margin, right: margin, bottom: 20 },
            theme: 'grid',
            styles: { fontSize: tableFontSize, cellPadding: 1.2, font: 'helvetica', overflow: 'linebreak', lineHeight: 1 },
            headStyles: { fillColor: [240, 240, 240], textColor: 0, fontStyle: 'bold', fontSize: tableFontSize },
            footStyles: { fillColor: [250, 250, 250], textColor: 0, fontStyle: 'bold', fontSize: tableFontSize },
            columnStyles: colStyles,
            
            didParseCell: function(data) {
                const colIdx = data.column.index;
                const val = data.cell.text[0];
                const section = data.section;

                if (colStyles[colIdx] && colStyles[colIdx].halign) {
                    data.cell.styles.halign = colStyles[colIdx].halign;
                }

                if (section === 'body') {
                    if (val && (val.includes('$') || val.includes('%'))) {
                        if (!colStyles[colIdx] || colStyles[colIdx].halign !== 'center') {
                            data.cell.styles.halign = 'right';
                        }
                    }
                }
                if (section === 'foot') {
                    data.cell.styles.fontStyle = 'bold';
                }
            },
            showFoot: 'lastPage'
        });

        // ===== PIE DE PÁGINA =====
        const totalPages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            doc.setPage(i);
            doc.setDrawColor(200).setLineWidth(0.5);
            doc.line(margin, pageHeight - 20, pageWidth - margin, pageHeight - 20);
            
            doc.setFontSize(8).setTextColor(100).setFont('helvetica', 'normal');
            doc.text(`Pagina ${i} de ${totalPages}`, margin, pageHeight - 12);
            doc.text(`Total Registros: ${TOTAL_REGISTROS}`, margin, pageHeight - 7);
            
            const colorGanancia = TOTAL_GANANCIA >= 0 ? [0, 92, 7] : [211, 47, 47];
            doc.setTextColor(colorGanancia[0], colorGanancia[1], colorGanancia[2]);
            doc.text(`Total Ganancia/Perdida: $${TOTAL_GANANCIA.toFixed(2)} - ${MARGEN.toFixed(1)}%`, pageWidth / 2, pageHeight - 10, { align: 'center' });
            
            doc.setTextColor(100).setFont('helvetica', 'normal');
            doc.text(ESLOGAN, pageWidth - margin, pageHeight - 7, { align: 'right' });
        }

        doc.save(`${nombreReporte.replace(/ /g, '_')}_${new Date().toLocaleDateString('es-ES').replace(/\//g, '-')}.pdf`);
        Swal.fire({ icon: 'success', title: 'PDF Exportado', timer: 1500, showConfirmButton: false });

    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'No se pudo generar el PDF. Revise la consola.', 'error');
    }
}

// ============================================
// FUNCIONES DE EXPORTACIÓN MEJORADAS
// Basadas en el patrón de reportes.php
// ============================================

// Objeto de configuración global para exportaciones
const ExportConfig = {
    titulo: 'Reporte de Rentabilidad',
    fecha: new Date().toLocaleDateString('es-ES'),
    hora: new Date().toLocaleTimeString('es-ES'),
    usuario: '<?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario') ?>',
    logoBase64: '<?= $logo_base64 ?>',
    periodo: '<?= date('d/m/Y', strtotime($fecha_desde)) ?> - <?= date('d/m/Y', strtotime($fecha_hasta)) ?>',
    totalRegistros: <?= ($tipo_reporte == 'catalogo') ? count($servicios_catalogo) : count($facturas_periodo) ?>,
    totalGanancia: <?= $total_ganancia_neta_periodo ?>,
    margen: <?= ($total_facturado_periodo > 0) ? ($total_ganancia_neta_periodo / $total_facturado_periodo) * 100 : 0 ?>,
    tipoReporte: '<?= $tipo_reporte ?>'
};

// Función principal de exportación (similar a exportarExcel, exportarPDF, etc. de reportes.php)
function exportarRentabilidad(formato) {
    const tabla = document.querySelector('.table-responsive table');
    if (!tabla) {
        Swal.fire({
            ...swalConfig,
            icon: 'error',
            title: 'Error',
            text: 'No hay datos para exportar',
            timer: 3000
        });
        return;
    }

    switch(formato) {
        case 'pdf':
            //imprimirReporteReal(); // Usa tu función existente
			exportarRentabilidadPDF();
            break;
        case 'excel':
            exportarExcelRentabilidad();
            break;
        case 'word':
            exportarWordRentabilidad();
            break;
        case 'csv':
            exportarCSVRentabilidad();
            break;
        case 'txt':
            exportarTXTRentabilidad();
            break;
    }
}

// 1. EXPORTAR A EXCEL (CON FORMATO DE MONEDA)
function exportarExcelRentabilidad() {
    const TITULO_PRINCIPAL = "PDL VISIONES - SISFACT";
    const TIPO_REPORTE_VAL = '<?= $tipo_reporte ?>';
    const PERIODO = '<?= date("d/m/Y", strtotime($fecha_desde)) ?> - <?= date("d/m/Y", strtotime($fecha_hasta)) ?>';
    
    function limpiar(t) { return t.replace(/½/g, ' 1/2').replace(/″/g, '"').replace(/<[^>]*>/g, '').trim(); }

    const tablaHTML = document.querySelector('.table-responsive table');
    if (!tablaHTML) return;

    // 1. Preparar Headers (sin la última columna)
    const headers = Array.from(tablaHTML.querySelectorAll('thead th'))
        .map(th => limpiar(th.textContent)).slice(0, -1);

    // 2. Preparar Filas con Lógica de Agrupación
    let lastCategory = null;
    const rows = Array.from(tablaHTML.querySelectorAll('tbody tr')).map(tr => {
        return Array.from(tr.querySelectorAll('td')).map((td, idx) => {
            let val = limpiar(td.textContent);
            
            // Consolidado: Agrupar categorías
            if (TIPO_REPORTE_VAL === 'consolidado' && idx === 2) {
                if (val === lastCategory) return ""; 
                lastCategory = val;
            }
            
            // Convertir a número si es posible (quitar $ y ,)
            if (val.includes('$')) return parseFloat(val.replace(/[$,]/g, '')) || 0;
            if (val.includes('%')) return val; // Mantener % como texto o convertir a decimal
            return val;
        }).slice(0, -1);
    });

    // 3. Totales
    const footer = Array.from(tablaHTML.querySelectorAll('tfoot tr')).map(tr => 
        Array.from(tr.querySelectorAll('th, td')).map(c => limpiar(c.textContent)).slice(0, -1)
    );

    // Unir todo para el libro de Excel
    const dataFinal = [
        [TITULO_PRINCIPAL],
        [TIPO_REPORTE_VAL.toUpperCase()],
        [`Período: ${PERIODO}`],
        [],
        headers,
        ...rows,
        ...footer
    ];

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(dataFinal);

    // Estilo básico: Ancho de columnas
    ws['!cols'] = headers.map((_, i) => ({ wch: i === 1 || i === 2 ? 40 : 15 }));

    XLSX.utils.book_append_sheet(wb, ws, "Rentabilidad");
    XLSX.writeFile(wb, `Rentabilidad_${TIPO_REPORTE_VAL}_${new Date().getTime()}.xlsx`);
    Swal.fire({ icon: 'success', title: 'Excel Exportado', timer: 1000, showConfirmButton: false });
}

// ==========================================================
// 2. EXPORTAR A WORD (REPLICA EXACTA DEL PDF)
// ==========================================================
function exportarWordRentabilidad() {
    // --- 1. Definición de Constantes (Sincronizadas con PDF) ---
    const TITULO_PRINCIPAL = "PDL VISIONES - SISFACT";
    const NOMBRE_SISTEMA = "Sistema Integral de Facturación y Control";
    const ESLOGAN = "PDL VISIONES - Donde tu visión toma forma";
    const LOGO_BASE64 = '<?= $logo_base64; ?>';
    const TIPO_REPORTE_VAL = '<?= $tipo_reporte ?>';
    const USUARIO_ACTUAL = '<?= addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
    const PERIODO_TXT = '<?= date("d/m/Y", strtotime($fecha_desde)) ?> - <?= date("d/m/Y", strtotime($fecha_hasta)) ?>';
    const FECHA_CORTA = '<?= date("d/m/Y") ?>';
    const HORA_DOC = '<?= date("h:i:s A") ?>';
    const NUM_MESES = <?= (int)$num_meses ?>;
    const PERIODO_COMPLETO = NUM_MESES > 1 ? `${PERIODO_TXT} (${NUM_MESES} meses)` : `${PERIODO_TXT} (1 mes)`;

    // Parámetros de Insumos y Tasas
    const COSTS = { 
        bond: '<?= number_format($costo_h_bond, 2) ?>', 
        cart: '<?= number_format($costo_h_cart, 2) ?>', 
        foto: '<?= number_format($costo_h_foto, 2) ?>', 
        tbn: '<?= number_format($costo_t_bn, 2) ?>', 
        tcl: '<?= number_format($costo_t_cl, 2) ?>' 
    };

    const PORCENTAJES = {
        impuesto_ventas: <?= (float)$tasa_impuesto_ventas ?>,
        contribucion_local: <?= (float)$tasa_contribucion_local ?>,
        gastos_indirectos: <?= (float)$porcentaje_indirectos ?>,
        seguridad_social: <?= (float)$tasa_seguridad_social ?>,
        depreciacion_anual: <?= (float)$tasa_depreciacion ?>
    };

    // Datos Financieros
    const GASTO_SALARIO_MENSUAL = <?= (float)$gasto_salario_mensual ?>;
    const GASTO_ELECTRICIDAD_MENSUAL = <?= (float)$gasto_electricidad_mensual ?>;
    const GASTO_TRANSPORTACION_MENSUAL = <?= (float)$gasto_transportacion_mensual ?>;
    const GASTO_MOVIL_MENSUAL = <?= (float)$gasto_movil_mensual ?>;
    const DEPRECIACION_MENSUAL = <?= (float)$depreciacion_mensual ?>;
    const SEGURIDAD_SOCIAL_MENSUAL = <?= (float)$seguridad_social_mensual ?>;
    const GASTOS_FIJOS_MENSUALES = <?= (float)$gastos_fijos_mensuales ?>;

    const TOTAL_FACTURADO = <?= (float)$total_facturado_periodo ?>;
    const TOTAL_GASTOS_FIJOS = <?= (float)$gastos_fijos_acumulados ?>;
    const TOTAL_IMPUESTOS = <?= (float)$total_impuestos_periodo ?>;
    const TOTAL_NETO = <?= (float)$total_ganancia_neta_periodo ?>;
    const MARGEN = <?= ($total_facturado_periodo > 0) ? (($total_ganancia_neta_periodo / $total_facturado_periodo) * 100) : 0 ?>;
    const TOTAL_IMPRESORAS = <?= (int)$total_impresoras ?>;

    // Datos Depreciación
    const FECHA_ADQUISICION = "<?= isset($fecha_adquisicion) ? $fecha_adquisicion : date('Y-m-d') ?>";
    const VALOR_RESTANTE = <?= max(0, ($valor_total_equipos ?? 0) - (($depreciacion_mensual ?? 0) * ($meses_transcurridos_global ?? 0))) ?>;
    const MESES_TRANSCURRIDOS = <?= (int)($meses_transcurridos_global ?? 0) ?>;
    const MESES_RESTANTES = <?= (int)($meses_restantes ?? 0) ?>;
    const ESTADO_DEP = "<?= $estado_depreciacion ?? 'En proceso' ?>";

    let nombreReporte = "REPORTE FINANCIERO";
    if (TIPO_REPORTE_VAL === 'consolidado') nombreReporte = "ANÁLISIS DE RENTABILIDAD CONSOLIDADO POR SERVICIO";
    else if (TIPO_REPORTE_VAL === 'mensual') nombreReporte = "ANÁLISIS DE RENTABILIDAD MENSUAL";
    else if (TIPO_REPORTE_VAL === 'detallado') nombreReporte = "ANÁLISIS DE RENTABILIDAD DETALLADO CRONOLÓGICO";
    else if (TIPO_REPORTE_VAL === 'catalogo') nombreReporte = "CATÁLOGO TÉCNICO DE COSTOS Y SERVICIOS";

    // --- 2. Procesamiento de la Tabla de Datos ---
    const tablaOriginal = document.querySelector('.table-responsive table');
    if (!tablaOriginal) return;

    const tablaClon = tablaOriginal.cloneNode(true);
    
    // Limpieza de columnas (Igual que el PDF: eliminar Detalle y Ficha)
    const headers = Array.from(tablaClon.querySelectorAll('thead th'));
    const indicesAEliminar = [];
    headers.forEach((th, i) => {
        const txt = th.textContent.toUpperCase();
        if (txt.includes('DETALLE') || txt.includes('FICHA') || txt.includes('#') || th.querySelector('button')) {
            indicesAEliminar.push(i);
        }
    });

    const procesarFilas = (selector) => {
        tablaClon.querySelectorAll(selector).forEach(tr => {
            const celdas = Array.from(tr.querySelectorAll('th, td'));
            // Aplicar estilo de colores según ganancia (Igual que PDF)
            const txtGanancia = celdas[celdas.length-2] ? celdas[celdas.length-2].textContent : "";
            if(txtGanancia.includes('-')) tr.style.backgroundColor = "#ffebee";
            
            // Eliminar columnas no deseadas
            indicesAEliminar.sort((a,b) => b-a).forEach(idx => {
                if(celdas[idx]) celdas[idx].remove();
            });
        });
    };

    procesarFilas('thead tr');
    procesarFilas('tbody tr');
    procesarFilas('tfoot tr');

    // --- 3. Construcción del HTML Estructurado ---
    let html = `
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head><meta charset='utf-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; }
        .page-break { page-break-after: always; }
        .blue-text { color: #0078d4; }
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background-color: #f2f2f2; border: 1pt solid #999; padding: 5px; font-size: 9pt; }
        td { border: 1pt solid #ccc; padding: 5px; font-size: 9pt; }
        
        .header-main { text-align: center; margin-bottom: 30px; }
        .resumen-box { border: 1.5pt solid #0078d4; padding: 10px; border-radius: 5px; }
        .footer { font-size: 8pt; color: #666; border-top: 1pt solid #ccc; margin-top: 20px; padding-top: 5px; }
    </style>
    </head>
    <body>
        <!-- PÁGINA 1: CARÁTULA E INDICADORES -->
        <div class="header-main">
            <img src="${LOGO_BASE64}" width="80" height="80"><br>
            <h1 class="blue-text">${TITULO_PRINCIPAL}</h1>
            <h2>${nombreReporte}</h2>
            <p>Período: ${PERIODO_COMPLETO}</p>
        </div>

<table style="border:none;">
    <tr>
        <td style="border:none; width:48%; vertical-align:top;">
            <h3 class="blue-text">COSTOS UNITARIOS</h3>
            <table style="width:100%; border-collapse: collapse;">
                <tr><td>📄 Hoja Papel Bond</td><td class="text-right">$${COSTS.bond}</td></tr>
                <tr><td>🖼️ Hoja Cartulina</td><td class="text-right">$${COSTS.cart}</td></tr>
                <tr><td>📸 Hoja Papel Foto</td><td class="text-right">$${COSTS.foto}</td></tr>
                <tr><td>⚫ Tinta B/N (página)</td><td class="text-right">$${COSTS.tbn}</td></tr>
                <tr><td>🔴 Tinta Color (página)</td><td class="text-right">$${COSTS.tcl}</td></tr>
                <tr><td>🖨️ Impresoras Activas</td><td class="text-right">${TOTAL_IMPRESORAS}</td></tr>
            </table>
        </td>
        <td style="border:none; width:4%;"></td>
        <td style="border:none; width:48%; vertical-align:top;">
            <h3 class="blue-text">PORCENTAJES APLICADOS</h3>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color:#0078d4;">
                        <th style="color:black; padding:6px; text-align:left;">Concepto</th>
                        <th style="color:black; padding:6px; text-align:center;">%</th>
                        <th style="color:black; padding:6px; text-align:left;">Aplicación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:4px;">Impuesto Ventas</td>
                        <td style="padding:4px; text-align:center;">${PORCENTAJES.impuesto_ventas}%</td>
                        <td style="padding:4px;">Sobre facturado</td>
                    </tr>
                    <tr style="background-color:#f2f2f2;">
                        <td style="padding:4px;">Contrib. Local</td>
                        <td style="padding:4px; text-align:center;">${PORCENTAJES.contribucion_local}%</td>
                        <td style="padding:4px;">Sobre ventas</td>
                    </tr>
                    <tr>
                        <td style="padding:4px;">Gastos Indirectos</td>
                        <td style="padding:4px; text-align:center;">${PORCENTAJES.gastos_indirectos}%</td>
                        <td style="padding:4px;">Costo directo</td>
                    </tr>
                    <tr style="background-color:#f2f2f2;">
                        <td style="padding:4px;">Seguridad Social</td>
                        <td style="padding:4px; text-align:center;">${PORCENTAJES.seguridad_social}%</td>
                        <td style="padding:4px;">Sobre nómina</td>
                    </tr>
                    <tr>
                        <td style="padding:4px;">Depreciación Anual</td>
                        <td style="padding:4px; text-align:center;">${PORCENTAJES.depreciacion_anual}%</td>
                        <td style="padding:4px;">Equipos</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>
        <div class="page-break"></div>

        <!-- PÁGINA 2: GASTOS DETALLADOS Y RESUMEN FINANCIERO -->
        ${TIPO_REPORTE_VAL !== 'catalogo' ? `
        <h3 class="blue-text">DETALLE DE GASTOS Y RESUMEN</h3>
        <table style="border:none;">
            <tr>
                <td style="border:none; width:50%; vertical-align:top;">
                    <h4 class="blue-text">Gastos Fijos Mensuales</h4>
                    <table>
                        <tr><td>Salario Directo</td><td class="text-right">$${GASTO_SALARIO_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td>Electricidad</td><td class="text-right">$${GASTO_ELECTRICIDAD_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td>Depreciación</td><td class="text-right">$${DEPRECIACION_MENSUAL.toFixed(2)}</td></tr>
                        <tr><td>Seguridad Social</td><td class="text-right">$${SEGURIDAD_SOCIAL_MENSUAL.toFixed(2)}</td></tr>
                        <tr class="bold"><td>TOTAL MENSUAL</td><td class="text-right">$${GASTOS_FIJOS_MENSUALES.toFixed(2)}</td></tr>
                    </table>
                    <p style="font-size:8pt; color:#666;">
                        Equipos: $${VALOR_RESTANTE.toFixed(2)} restantes | Mes: ${MESES_TRANSCURRIDOS}/60 | ${ESTADO_DEP}
                    </p>
                </td>
                <td style="border:none; width:50%; vertical-align:top; padding-left:15px;">
                    <h4 class="blue-text">Totales del Período</h4>
                    <table>
                        <tr><td>Gastos Fijos (${NUM_MESES}m)</td><td class="text-right">$${TOTAL_GASTOS_FIJOS.toFixed(2)}</td></tr>
                        <tr><td>Impuestos Totales</td><td class="text-right">$${TOTAL_IMPUESTOS.toFixed(2)}</td></tr>
                        <tr class="bold" style="background-color:#f2f2f2;"><td>TOTAL CARGAS</td><td class="text-right">$${(TOTAL_GASTOS_FIJOS + TOTAL_IMPUESTOS).toFixed(2)}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="resumen-box">
            <h3 class="blue-text" style="margin-top:0;">RESUMEN FINANCIERO CONSOLIDADO</h3>
            <p><b>Total Ingresos:</b> $${TOTAL_FACTURADO.toLocaleString('en-US', {minimumFractionDigits:2})}</p>
            <p><b>Gastos e Impuestos Totales:</b> $${(TOTAL_GASTOS_FIJOS + TOTAL_IMPUESTOS).toLocaleString('en-US', {minimumFractionDigits:2})}</p>
            <p style="font-size:14pt;"><b>GANANCIA NETA: </b> 
               <span style="color:${TOTAL_NETO >= 0 ? '#2e7d32' : '#d32f2f'};">$${TOTAL_NETO.toLocaleString('en-US', {minimumFractionDigits:2})} (${MARGEN.toFixed(1)}%)</span>
            </p>
        </div>
        <div class="page-break"></div>
        ` : ''}

        <!-- PÁGINA 3: TABLA DE RESULTADOS -->
        <h3 class="blue-text">DETALLE DE RESULTADOS</h3>
        ${tablaClon.outerHTML}

        <br><br>
        <table style="border:none;">
            <tr>
                <td style="border:none; text-align:center;">
                    <div style="border-top:1pt solid black; width:200px; margin:0 auto;"></div>
                    <b>Elaborado por</b><br>${USUARIO_ACTUAL}
                </td>
                <td style="border:none; text-align:center;">
                    <div style="border-top:1pt solid black; width:200px; margin:0 auto;"></div>
                    <b>Aprobado por</b><br>Administración
                </td>
            </tr>
        </table>

        <div class="footer">
            <table style="border:none;">
                <tr>
                    <td style="border:none;">${NOMBRE_SISTEMA}</td>
                    <td style="border:none; text-align:right;">Generado el ${FECHA_CORTA} a las ${HORA_DOC}</td>
                </tr>
            </table>
        </div>
    </body>
    </html>`;

    // --- 4. Descarga del Documento ---
    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `Rentabilidad_${TIPO_REPORTE_VAL}_${new Date().getTime()}.doc`;
    
    Swal.fire({
        icon: 'success',
        title: 'Documento Word Generado',
        text: 'El reporte se ha exportado Satisfactoriamente.',
        timer: 2000,
        showConfirmButton: false
    });

    setTimeout(() => {
        link.click();
        URL.revokeObjectURL(url);
    }, 500);
}

// 3. EXPORTAR A CSV
function exportarCSVRentabilidad() {
    const TIPO_REPORTE_VAL = '<?= $tipo_reporte ?>';
    function limpiar(t) { return t.replace(/½/g, ' 1/2').replace(/″/g, '"').replace(/,/g, '').replace(/<[^>]*>/g, '').trim(); }

    const tabla = document.querySelector('.table-responsive table');
    const rows = Array.from(tabla.querySelectorAll('tr'));
    
    const csvContent = rows.map(tr => {
        const cells = Array.from(tr.querySelectorAll('th, td')).slice(0, -1);
        return cells.map(c => `"${limpiar(c.textContent)}"`).join(',');
    }).join('\n');

    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Rentabilidad_${TIPO_REPORTE_VAL}.csv`;
    link.click();
}


// 4. EXPORTAR A TXT
function exportarTXTRentabilidad() {
    const TIPO_REPORTE_VAL = '<?= $tipo_reporte ?>';
    const PERIODO = '<?= date("d/m/Y", strtotime($fecha_desde)) ?> - <?= date("d/m/Y", strtotime($fecha_hasta)) ?>';
    
    function limpiar(t) { return t.replace(/½/g, ' 1/2').replace(/″/g, '"').replace(/<[^>]*>/g, '').trim(); }

    const tabla = document.querySelector('.table-responsive table');
    const rows = Array.from(tabla.querySelectorAll('tr'));
    
    let txt = `PDL VISIONES - SISFACT\nREPORTE: ${TIPO_REPORTE_VAL.toUpperCase()}\nPERIODO: ${PERIODO}\n`;
    txt += "=".repeat(100) + "\n";

    rows.forEach(tr => {
        const cells = Array.from(tr.querySelectorAll('th, td')).slice(0, -1);
        let rowText = "";
        cells.forEach((c, i) => {
            let val = limpiar(c.textContent);
            // Prensado de columnas simple
            rowText += val.padEnd(i === 1 || i === 2 ? 40 : 15);
        });
        txt += rowText + "\n";
        if (tr.parentElement.tagName === 'THEAD') txt += "-".repeat(100) + "\n";
    });

    const blob = new Blob([txt], { type: 'text/plain' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Rentabilidad_${TIPO_REPORTE_VAL}.txt`;
    link.click();
}

function resetearValoresPorDefecto() {
    Swal.fire({
        title: '¿Resetear valores?',
        text: 'Los valores de insumos y gastos volverán a sus valores por defecto',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, Resetear',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirigir con un parámetro especial
            window.location.href = window.location.pathname + 
                '?reset_valores=1&tipo_reporte=<?= $tipo_reporte ?>' +
                '&mes_desde=<?= $mes_desde ?>&ano_desde=<?= $ano_desde ?>' +
                '&mes_hasta=<?= $mes_hasta ?>&ano_hasta=<?= $ano_hasta ?>';
        }
    });
}
    </script>
    <?php
    // Incluir footer
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
function getUTCOffset($timezone, $dospuntos=true, $ignoreDST=false)
	{
	  $timezone_identifiers = DateTimeZone::listIdentifiers();
	  $dtz = new DateTimeZone($timezone);
	  $format=($dospuntos)?'%+03d:%02u':'%+03d%02u';

		
	  if (!$ignoreDST)
		{
		  $offset = $dtz->getOffset(new DateTime());
		  return sprintf($format, $offset / 3600, abs($offset) % 3600 / 60).' '.$timezone_identifiers[114].' ('.date('T').')';
		}
	  else
		{
		  $transitions = $dtz->getTransitions(time());
		  foreach ($transitions as $transition)
		{
		  if (!$transition['isdst'])
			return sprintf($format, $transition['offset'] / 3600, abs($transition['offset']) % 3600 / 60).' '.$timezone_identifiers[114];
		}
		  return false;
		}
	}
    ?>
</body>
</html>