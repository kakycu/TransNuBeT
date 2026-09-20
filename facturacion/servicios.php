<?php
// servicios.php - Windows 11 Dark Mode
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener fechas de operación desde init.php
$fecha_inicio_operaciones = obtenerFechaInicioOperaciones();
$mes_cierre_operaciones = obtenerMesCierreOperaciones();

// Formatear fecha de inicio para mostrarla
$fecha_inicio_formateada = '';
$año_inicio_operaciones = date('Y'); // Fallback
if ($fecha_inicio_operaciones) {
    $fecha_inicio_formateada = date('d/m/Y', strtotime($fecha_inicio_operaciones));
    $año_inicio_operaciones = date('Y', strtotime($fecha_inicio_operaciones));
}

// Obtener nombre del mes de cierre
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_cierre_nombre = $meses_completos[$mes_cierre_operaciones - 1] ?? 'Enero';

// ==================== VALIDACIÓN INICIAL CON SWEETALERT ====================
// Obtener parámetros de la URL
$categoria_id_filtro = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 0;
$servicio_id_individual = isset($_GET['id']) ? $_GET['id'] : '';
$modo_individual = false;

// Validar ID de servicio si se proporciona
if (!empty($servicio_id_individual)) {
    // Validar que sea numérico
    if (!is_numeric($servicio_id_individual)) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirigiendo...</title>
            <link rel="stylesheet" href="css/sweetalert2.min.css">
            <script src="js/sweetalert211.js"></script>
            <style>
                body { 
                    background: #1f1f1f; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0; 
                    font-family: Arial, sans-serif; 
                }
            </style>
        </head>
        <body>
            <script>
            Swal.fire({
                title: "Error",
                text: "El ID del servicio debe ser un número válido.",
                icon: "error",
                background: "#1f1f1f",
                color: "#fff",
                confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
                confirmButtonColor: "#3085d6",
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                window.location.href = "servicios.php";
            });
            </script>
        </body>
        </html>';
        exit();
    }
    
    // Convertir a entero
    $servicio_id_individual = (int)$servicio_id_individual;
    $modo_individual = true;
}
// ==================== FIN VALIDACIÓN INICIAL ====================

// Parámetros de paginación
$registros_por_pagina = isset($_GET['registros']) && in_array(intval($_GET['registros']), [5, 10, 15, 20, 25, 30, 50, 100, 99999]) ? intval($_GET['registros']) : 15;

$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Validar valores
$registros_por_pagina = max(5, min(99999, $registros_por_pagina)); // Mínimo 5, máximo 99999
$pagina_actual = max(1, $pagina_actual);

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

try {
    $db = Database::getConnection();

    // --- LÓGICA DE FECHAS OPERATIVAS ---
    // 1. Obtener datos maestros de la BD
    $mes_cierre_num = obtenerMesCierreOperaciones();
    $anio_cierre_num = obtenerAnioCierreOperaciones();
    $fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d');

    // 2. Definir el día actual (de la PC/Servidor)
    $dia_actual = date('j');

    // 3. Construir la fecha "Hoy Operativo" (Año BD + Mes BD + Día PC)
    $hoy_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_actual);
    $primer_dia_operativo = sprintf("%04d-%02d-01", $anio_cierre_num, $mes_cierre_num);

    // 4. Variables auxiliares para visualización
    $timestamp_combinado = strtotime($hoy_operativo);
    $dia_semana = date('N', $timestamp_combinado);

    // 5. Obtener nombre del mes OPERATIVO
    $meses_completos = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    // Ajustamos el índice
    $mes_actual_es = $meses_completos[$mes_cierre_num - 1];

    
    // Obtener usuario actual
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    $es_admin = false;
    if (isset($usuario['rol_id']) && $usuario['rol_id'] == 1) {
        $es_admin = true;
    }
    
    // Obtener estadísticas para los badges del sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_servicios_activos_total = "SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 1";
    $stmt = $db->prepare($sql_servicios_activos_total);
    $stmt->execute();
    $total_servicios_activos_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Obtener estadísticas usando el AÑO DE CIERRE ($anio_cierre_num)
    $sql_total_ventas_anual = "SELECT 
        COUNT(DISTINCT f.id) as total_ventas_general,
        SUM(f.total_general) as importe_total_general
    FROM tbl_fact f
    WHERE YEAR(f.fecha_emision) = :anio
    AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA', 'PENDIENTE') 
    AND f.fecha_emision IS NOT NULL"; 

    try {
        $stmt = $db->prepare($sql_total_ventas_anual);
        $stmt->execute(['anio' => $anio_cierre_num]);
        $total_general = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_ventas_general = $total_general['total_ventas_general'] ?? 0;
        $importe_total_general = $total_general['importe_total_general'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener total general de ventas: " . $e->getMessage());
        $total_ventas_general = 0;
        $importe_total_general = 0;
    }

    $sql_ingresos_anual = "SELECT 
        COALESCE(SUM(f.total_general), 0) as ingresos_anual
    FROM tbl_fact f
    WHERE YEAR(f.fecha_emision) = :anio
    AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA', 'PENDIENTE') 
    AND f.fecha_emision IS NOT NULL";

    try {
        $stmt_ingresos = $db->prepare($sql_ingresos_anual);
        $stmt_ingresos->execute(['anio' => $anio_cierre_num]);
        $ingresos_anual = $stmt_ingresos->fetch(PDO::FETCH_ASSOC);
        $ingresos_total_anual = $ingresos_anual['ingresos_anual'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener ingresos anuales: " . $e->getMessage());
        $ingresos_total_anual = 0;
    }

    // Servicio con más ventas en el AÑO DE CIERRE
    $sql_mas_ventas = "SELECT 
                            s.id,
                            s.codigo,
                            s.descripcion, 
                            ccs.descripcion as categoria,
                            COUNT(fd.servicio_id) as total_ventas,
                            SUM(fd.cantidad) as cantidad_unidades,
                            SUM(fd.cantidad * fd.precio_unitario) as importe_total
                       FROM clasif_serv s
                       INNER JOIN clasif_cat_de_serv ccs ON s.categoria_id = ccs.id
                       INNER JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
                       INNER JOIN tbl_fact f ON fd.factura_id = f.id
                       WHERE YEAR(f.fecha_emision) = :anio
                       AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                       AND f.fecha_emision IS NOT NULL
                       GROUP BY s.id
                       ORDER BY total_ventas DESC, importe_total DESC
                       LIMIT 1";

    $sql_menos_ventas = "SELECT 
                            s.id,
                            s.codigo,
                            s.descripcion, 
                            ccs.descripcion as categoria,
                            COUNT(fd.servicio_id) as total_ventas,
                            SUM(fd.cantidad) as cantidad_unidades,
                            SUM(fd.cantidad * fd.precio_unitario) as importe_total
                         FROM clasif_serv s
                         INNER JOIN clasif_cat_de_serv ccs ON s.categoria_id = ccs.id
                         INNER JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
                         INNER JOIN tbl_fact f ON fd.factura_id = f.id
                         WHERE YEAR(f.fecha_emision) = :anio
                         AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                         AND f.fecha_emision IS NOT NULL
                         GROUP BY s.id
                         HAVING total_ventas > 0
                         ORDER BY total_ventas ASC, importe_total ASC
                         LIMIT 1";
    try {
        $stmt_mas_ventas = $db->prepare($sql_mas_ventas);
        $stmt_mas_ventas->execute(['anio' => $anio_cierre_num]);
        $servicio_mas_ventas = $stmt_mas_ventas->fetch(PDO::FETCH_ASSOC);
        
        $stmt_menos_ventas = $db->prepare($sql_menos_ventas);
        $stmt_menos_ventas->execute(['anio' => $anio_cierre_num]);
        $servicio_menos_ventas = $stmt_menos_ventas->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas de servicios: " . $e->getMessage());
        $servicio_mas_ventas = null;
        $servicio_menos_ventas = null;
    }

// Obtener información de la categoría si se está filtrando
$categoria_info = null;
if ($categoria_id_filtro > 0) {
    $sql_categoria = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv WHERE id = :id";
    $stmt_categoria = $db->prepare($sql_categoria);
    $stmt_categoria->execute(['id' => $categoria_id_filtro]);
    $categoria_info = $stmt_categoria->fetch(PDO::FETCH_ASSOC);
    
    // Validar que la categoría exista
    if (!$categoria_info) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirigiendo...</title>
            <link rel="stylesheet" href="css/sweetalert2.min.css">
            <script src="js/sweetalert211.js"></script>
            <style>
                body { 
                    background: #1f1f1f; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0; 
                    font-family: Arial, sans-serif; 
                }
            </style>
        </head>
        <body>
            <script>
            Swal.fire({
                title: "Error",
                text: "La categoría con ID ' . $categoria_id_filtro . ' no existe en la base de datos.",
                icon: "error",
                background: "#1f1f1f",
                color: "#fff",
                confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
                confirmButtonColor: "#3085d6",
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                window.location.href = "servicios.php";
            });
            </script>
        </body>
        </html>';
        exit();
    }
}  
// Obtener información del servicio individual
$servicio_individual = null;
if ($modo_individual) {
    $sql_servicio = "SELECT s.*, 
                            c.descripcion as categoria_nombre,
                            c.codigo as categoria_codigo
                     FROM clasif_serv s
                     LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                     WHERE s.id = :servicio_id";
    
    $stmt_servicio = $db->prepare($sql_servicio);
    $stmt_servicio->execute(['servicio_id' => $servicio_id_individual]);
    $servicio_individual = $stmt_servicio->fetch(PDO::FETCH_ASSOC);
    
    if (!$servicio_individual) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirigiendo...</title>
            <link rel="stylesheet" href="css/sweetalert2.min.css">
            <script src="js/sweetalert211.js"></script>
            <style>
                body { 
                    background: #1f1f1f; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0; 
                    font-family: Arial, sans-serif; 
                }
            </style>
        </head>
        <body>
            <script>
            Swal.fire({
                title: "Error",
                text: "El servicio con ID ' . $servicio_id_individual . ' no existe en la base de datos.",
                icon: "error",
                background: "#1f1f1f",
                color: "#fff",
                confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
                confirmButtonColor: "#3085d6",
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                window.location.href = "servicios.php";
            });
            </script>
        </body>
        </html>';
        exit();
    }
    
        
        $stmt_servicio = $db->prepare($sql_servicio);
        $stmt_servicio->execute(['servicio_id' => $servicio_id_individual]);
        $servicio_individual = $stmt_servicio->fetch(PDO::FETCH_ASSOC);
        
        if (!$servicio_individual) {
            throw new Exception("Servicio no encontrado");
        }
        
        // Consulta separada para obtener estadísticas de ventas del AÑO DE CIERRE
        $sql_estadisticas = "SELECT 
                                COUNT(DISTINCT fd.factura_id) as total_ventas,
                                SUM(fd.cantidad) as total_unidades,
                                COALESCE(SUM(fd.cantidad * fd.precio_unitario), 0) as total_ingresos
                             FROM tbl_fact_detalle fd
                             INNER JOIN tbl_fact f ON fd.factura_id = f.id
                             WHERE fd.servicio_id = :servicio_id
                             AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                             AND f.fecha_emision IS NOT NULL
                             AND YEAR(f.fecha_emision) = :anio";
        
        $stmt_estadisticas = $db->prepare($sql_estadisticas);
        $stmt_estadisticas->execute([
            'servicio_id' => $servicio_id_individual,
            'anio' => $anio_cierre_num
        ]);
        $estadisticas = $stmt_estadisticas->fetch(PDO::FETCH_ASSOC);
        
        $servicio_individual['total_ventas'] = $estadisticas['total_ventas'] ?? 0;
        $servicio_individual['total_unidades'] = $estadisticas['total_unidades'] ?? 0;
        $servicio_individual['total_ingresos'] = $estadisticas['total_ingresos'] ?? 0;
    }
    
    // Lista paginada
    if (!$modo_individual) {
        $sql_count = "SELECT COUNT(DISTINCT s.id) as total 
                      FROM clasif_serv s
                      LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id";
        
        if ($categoria_id_filtro > 0) {
            $sql_count .= " WHERE s.categoria_id = :categoria_id";
        }
        
        $stmt_count = $db->prepare($sql_count);
        
        if ($categoria_id_filtro > 0) {
            $stmt_count->execute(['categoria_id' => $categoria_id_filtro]);
        } else {
            $stmt_count->execute();
        }
        
        $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        $total_paginas = ceil($total_registros / $registros_por_pagina);
        
        if ($pagina_actual > $total_paginas && $total_paginas > 0) {
            $pagina_actual = $total_paginas;
            $offset = ($pagina_actual - 1) * $registros_por_pagina;
        }
        
        // Obtener servicios paginados con estadísticas del AÑO DE CIERRE
$sql = "SELECT 
    s.*, 
    c.descripcion as categoria_nombre, 
    c.codigo as categoria_codigo,
    COALESCE(v.total_ventas, 0) as total_ventas,
    COALESCE(v.total_ingresos, 0) as total_ingresos
FROM clasif_serv s
LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
LEFT JOIN (
    SELECT 
        fd.servicio_id,
        COUNT(DISTINCT f.id) as total_ventas,
        SUM(fd.cantidad * fd.precio_unitario) as total_ingresos
    FROM tbl_fact_detalle fd
    INNER JOIN tbl_fact f ON fd.factura_id = f.id
    WHERE YEAR(f.fecha_emision) = :anio_cierre
        AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'PENDIENTE', 'CERRADA')
        AND f.fecha_emision IS NOT NULL
    GROUP BY fd.servicio_id
) v ON s.id = v.servicio_id
WHERE 1=1
";

if ($categoria_id_filtro > 0) {
    $sql .= " AND s.categoria_id = :categoria_id";
}

$sql .= " GROUP BY s.id, c.descripcion, c.codigo
          ORDER BY s.descripcion
          LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':anio_cierre', $anio_cierre_num, PDO::PARAM_INT);

        
        if ($categoria_id_filtro > 0) {
            $stmt->bindValue(':categoria_id', $categoria_id_filtro, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $servicios = $stmt->fetchAll();
        
        // Calcular estadísticas
        $total_servicios_activos = 0;
        $total_servicios_inactivos = 0;
        $total_ingresos_general = $ingresos_total_anual;
        $costo_promedio = 0;
        $total_servicios_filtrados = count($servicios);
        $total_ventas_general_filtrado = 0;

        foreach ($servicios as $servicio) {
            if ($servicio['activo'] == 1) {
                $total_servicios_activos++;
            } else {
                $total_servicios_inactivos++;
            }
            $costo_promedio += floatval($servicio['costo']);
            $total_ventas_general_filtrado += intval($servicio['total_ventas']);
        }
    } else {
        $total_registros = 1;
        $total_paginas = 1;
        $pagina_actual = 1;
        $servicios = [$servicio_individual];
        $total_servicios_filtrados = 1;
        $total_servicios_activos = $servicio_individual['activo'] ? 1 : 0;
        $total_servicios_inactivos = $servicio_individual['activo'] ? 0 : 1;
        $total_ingresos_general = $ingresos_total_anual;
        $costo_promedio = floatval($servicio_individual['costo']);
        $total_ventas_general_filtrado = intval($servicio_individual['total_ventas']);
    }

    // Servicios con ventas en el AÑO DE CIERRE
    $sql_servicios_con_ventas = "SELECT COUNT(DISTINCT s.id) as servicios_con_ventas
                                 FROM clasif_serv s
                                 INNER JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
                                 INNER JOIN tbl_fact f ON fd.factura_id = f.id
                                 WHERE YEAR(f.fecha_emision) = :anio
                                 AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                                 AND f.fecha_emision IS NOT NULL";

    try {
        $stmt_servicios_ventas = $db->prepare($sql_servicios_con_ventas);
        $stmt_servicios_ventas->execute(['anio' => $anio_cierre_num]);
        $servicios_con_ventas = $stmt_servicios_ventas->fetch(PDO::FETCH_ASSOC);
        $total_servicios_con_ventas = $servicios_con_ventas['servicios_con_ventas'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener servicios con ventas: " . $e->getMessage());
        $total_servicios_con_ventas = 0;
    }
    
    // Categorías
    $sql_categorias = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv WHERE activo = 1 ORDER BY descripcion";
    $stmt_categorias = $db->query($sql_categorias);
    $categorias = $stmt_categorias->fetchAll();
    
    // Estadísticas por categoría en el AÑO DE CIERRE
    if ($categoria_id_filtro == 0 && !$modo_individual) {
        $sql_estadisticas_categoria = "SELECT c.descripcion as categoria, 
                                              COUNT(s.id) as total_servicios,
                                              COALESCE(SUM(fd.cantidad * fd.precio_unitario), 0) as total_ingresos
                                       FROM clasif_cat_de_serv c
                                       LEFT JOIN clasif_serv s ON c.id = s.categoria_id AND s.activo = 1
                                       LEFT JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
                                       LEFT JOIN tbl_fact f ON fd.factura_id = f.id
                                       WHERE c.activo = 1
                                       AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                                       AND YEAR(f.fecha_emision) = $anio_cierre_num
                                       AND f.fecha_emision IS NOT NULL
                                       GROUP BY c.id
                                       ORDER BY total_ingresos DESC";
        $stmt_estadisticas = $db->query($sql_estadisticas_categoria);
        $estadisticas_categoria = $stmt_estadisticas->fetchAll();
    } else {
        $estadisticas_categoria = [];
    }
    
} catch (Exception $e) {
    error_log("Error al cargar servicios: " . $e->getMessage());
    $error = "Error al cargar los servicios: " . $e->getMessage();
}

// Estadísticas para sidebar
$estadisticas = [];
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Logo base64 para exportaciones
$logo_path = 'assets/logov.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $mime_type = mime_content_type($logo_path);
    $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
} else {
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
            <rect width="50" height="50" fill="#0078D4" rx="5"/>
            <text x="25" y="28" font-family="Arial" font-size="16" fill="white" text-anchor="middle" font-weight="bold">PDL</text>
            <text x="25" y="40" font-family="Arial" font-size="9" fill="white" text-anchor="middle">VISIONES</text>
        </svg>
    ');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        if ($modo_individual && $servicio_individual) {
            echo 'Servicio: ' . htmlspecialchars($servicio_individual['descripcion']) . ' - SISFACT PDL Visiones';
        } elseif ($categoria_id_filtro > 0 && $categoria_info) {
            echo 'Servicios - ' . htmlspecialchars($categoria_info['descripcion']) . ' - SISFACT PDL Visiones';
        } else {
            echo 'Servicios - SISFACT PDL Visiones';
        }
    ?></title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Chart.js -->
    <script src="js/chart.js"></script>
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <!-- ===== LIBRERÍAS PARA EXPORTACIÓN ===== -->
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/html2pdf.bundle.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    
    <!-- Windows 11 Styles -->
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

        [data-theme="light"] {
            --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Placeholders personalizados para tema dark */
        [data-theme="dark"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="dark"] :-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="dark"] ::-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders personalizados para tema light */
        [data-theme="light"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] :-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] ::-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para formularios específicos */
        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] .form-select::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .form-control::placeholder,
        [data-theme="light"] .form-select::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para el buscador de navbar */
        [data-theme="dark"] .win-nav-search input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .win-nav-search input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para search-box en tarjetas */
        [data-theme="dark"] .search-box input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .search-box input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            transition: var(--win-transition);
            min-height: 100vh;
        }

        /* Efecto Mica (Windows 11) */
        .mica-effect {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        [data-theme="light"] .mica-effect {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        /* Navbar estilo Windows 11 */
        .win-navbar {
            height: 48px;
            background: var(--win-bg-secondary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 0 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .win-navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .win-navbar-brand i {
            color: var(--win-accent);
        }

        .win-nav-search {
            flex: 1;
            max-width: 400px;
            position: relative;
            margin-bottom: 5px;
            padding: 0 5px;
        }

        .win-nav-search input {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 8px 12px 8px 30px;
            font-size: 13px;
            width: 100%;
            transition: var(--win-transition);
        }

        .win-nav-search input:focus {
            outline: none;
            border-color: var(--win-accent);
            box-shadow: 0 0 0 2px var(--win-accent-light);
        }

        .win-nav-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Sidebar inmersivo */
        .win-sidebar {
            width: 260px;
            background: var(--win-bg-secondary);
            border-right: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            position: fixed;
            left: 0;
            top: 48px;
            z-index: 999;
            transition: var(--win-transition);
            overflow-y: auto;
            padding: 16px 0;
        }

        .win-sidebar.mini {
            width: 68px;
        }

        .win-sidebar-header {
            padding: 0 16px 16px;
            border-bottom: 1px solid var(--win-border-color);
            margin-bottom: 16px;
        }

        .win-sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .win-sidebar-user:hover {
            background: var(--win-bg-tertiary);
        }

        .win-sidebar-user-avatar {
            width: 36px;
            height: 36px;
            background: var(--win-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .win-sidebar-user-info h6 {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }

        .win-sidebar-user-info small {
            color: var(--win-text-secondary);
            font-size: 12px;
        }

        .win-sidebar.mini .win-sidebar-user-info {
            display: none;
        }

        /* Menú lateral */
        .win-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .win-nav-item {
            margin: 2px 8px;
        }

        .win-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--win-text-secondary);
            text-decoration: none;
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
            font-size: 14px;
            position: relative;
        }

        .win-nav-link:hover {
            background: var(--win-bg-tertiary);
            color: var(--win-text-primary);
        }

        .win-nav-link.active {
            background: var(--win-accent-light);
            color: var(--win-accent);
            font-weight: 500;
        }

        .win-nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 4px;
            bottom: 4px;
            width: 3px;
            background: var(--win-accent);
            border-radius: 0 2px 2px 0;
        }

        .win-nav-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .win-sidebar.mini .win-nav-text {
            display: none;
        }

        .win-nav-badge {
            margin-left: auto;
            background: var(--win-accent);
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
            font-weight: 600;
            transition: var(--win-transition);
        }

        .win-nav-link:hover .win-nav-badge {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
        }

        /* Contenido principal */
        .win-main-content {
            margin-left: 260px;
            margin-top: 48px;
            padding: 24px;
            transition: var(--win-transition);
            min-height: calc(100vh - 48px);
        }

        .win-main-content.sidebar-mini {
            margin-left: 68px;
        }

        /* Estilos para contenido */
        .win-main-content .card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            transition: var(--win-transition);
            margin-bottom: 1.5rem;
        }

        .win-main-content .card:hover {
            border-color: var(--win-accent);
            box-shadow: var(--win-shadow);
        }

        .win-main-content .card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border_color);
            padding: 1rem 1.25rem;
        }

        .win-main-content .card-body {
            padding: 1.25rem;
            color: var(--win-text-primary);
        }

        .win-main-content .table {
            color: var(--win-text-primary);
            border-color: var(--win-border-color);
        }

        .win-main-content .table th {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            font-weight: 600;
            color: var(--win-text-primary);
        }

        .win-main-content .table td {
            border-color: var(--win-border_color);
            color: var(--win-text-primary);
        }

        .win-main-content .table-hover tbody tr:hover {
            background: var(--win-bg-tertiary);
        }

        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .win-main-content .btn-primary {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-primary:hover {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
            border-color: color-mix(in srgb, var(--win-accent) 90%, black);
        }

        .win-main-content .btn-outline-primary {
            color: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-outline-primary:hover {
            background: var(--win-accent);
            color: white;
        }

        .win-main-content .border-bottom {
            border-bottom-color: var(--win-border-color) !important;
        }

        /* Ajustes para tema oscuro */
        [data-theme="dark"] .text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.9;
        }

        [data-theme="dark"] small.text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.9;
        }

        /* Ajustes para tema claro */
        [data-theme="light"] .text-muted {
            color: #6c757d !important;
            opacity: 1;
        }

        .win-main-content .bg-light {
            background-color: var(--win-bg-tertiary) !important;
        }

        .win-main-content .table-light {
            background-color: var(--win-bg-tertiary) !important;
        }

        .win-main-content .table-secondary {
            background-color: var(--win-bg-tertiary) !important;
        }

        /* Panel de temas */
        .win-theme-panel {
            position: fixed;
            top: 48px;
            right: 0;
            width: 300px;
            background: var(--win-bg-secondary);
            border-left: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            z-index: 1001;
            transform: translateX(100%);
            transition: var(--win-transition);
            padding: 24px;
            overflow-y: auto;
        }

        .win-theme-panel.open {
            transform: translateX(0);
        }

        .win-theme-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }

        .win-theme-overlay.open {
            display: block;
        }

        .win-theme-header {
            margin-bottom: 24px;
        }

        .win-theme-option {
            padding: 16px;
            border: 2px solid var(--win-border-color);
            border-radius: var(--win-radius);
            cursor: pointer;
            transition: var(--win-transition);
            text-align: center;
            color: var(--win-text-primary);
        }

        .win-theme-option:hover {
            border-color: var(--win-accent);
        }

        .win-theme-option.active {
            border-color: var(--win-accent);
            background: var(--win-accent-light);
        }

        .win-theme-option[data-theme="dark"] {
            background: #0d0d0d;
            color: white;
        }

        .win-theme-option[data-theme="light"] {
            background: #f3f3f3;
            color: #000000;
        }

        .win-color-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .win-color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--win-transition);
        }

        .win-color-option:hover {
            transform: scale(1.1);
        }

        .win-color-option.active {
            border-color: white;
            box-shadow: 0 0 0 2px var(--win-bg-secondary);
        }

        /* Quick Actions (Botones flotantes) */
        .win-quick-actions {
            position: fixed;
            bottom: 80px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
        }

        .win-quick-action {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--win-accent);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
            position: relative;
            z-index: 1001;
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        /* Quick Actions Expandibles */
        .win-quick-actions-expanded {
            position: absolute;
            bottom: 100%;
            right: 0;
            display: flex;
            flex-direction: column-reverse;
            gap: 10px;
            opacity: 0;
            transform: translateY(20px) scale(0.8);
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1002;
        }

        .win-quick-actions-expanded.show {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }

        /* Animación circular para los botones expandidos */
        .win-quick-actions-expanded .win-quick-action {
            width: 48px;
            height: 48px;
            font-size: 18px;
            transform: scale(0);
            animation: expandButton 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        .win-quick-actions-expanded.show .win-quick-action:nth-child(1) {
            animation-delay: 0.1s;
        }

        .win-quick-actions-expanded.show .win-quick-action:nth-child(2) {
            animation-delay: 0.2s;
        }

        .win-quick-actions-expanded.show .win-quick-action:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes expandButton {
            0% {
                transform: scale(0) rotate(-90deg);
                opacity: 0;
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .win-quick-actions-expanded .action-factura {
            background: #0078d4;
        }

        .win-quick-actions-expanded .action-cliente {
            background: #107c10;
        }

        .win-quick-actions-expanded .action-servicio {
            background: #5c2d91;
        }

        /* Estilos para el dropdown de usuario */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
        }
        
        .dropdown-header {
            background-color: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm) var(--win-radius-sm) 0 0;
        }
        
        .dropdown-footer {
            background-color: var(--win-bg-tertiary);
            border-radius: 0 0 var(--win-radius-sm) var(--win-radius-sm);
        }
        
        .dropdown-item {
            color: var(--win-text-primary);
            transition: var(--win-transition);
            border-radius: var(--win-radius-sm);
            margin: 2px 4px;
        }
        
        .dropdown-item:hover {
            background-color: var(--win-accent-light);
            color: var(--win-accent);
        }
        
        .dropdown-divider {
            border-color: var(--win-border-color);
        }
        
        .dropdown-item.text-danger:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545 !important;
        }

        /* Formularios */
        .win-main-content .form-control,
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }

        .win-main-content .form-control:focus,
        .win-main-content .form-select:focus {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-accent);
            color: var(--win-text-primary);
            box-shadow: 0 0 0 0.25rem var(--win-accent-light);
        }

        .win-main-content .form-label {
            color: var(--win-text-primary);
            font-weight: 500;
        }

        /* Stats cards */
        .stat-card {
            border: none;
            border-radius: var(--win-radius);
            border-left: 4px solid;
            background: var(--win-bg-secondary);
            transition: var(--win-transition);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--win-shadow);
        }

        .stat-card.success {
            border-left-color: #28a745;
        }

        .stat-card.warning {
            border-left-color: #ffc107;
        }

        .stat-card.info {
            border-left-color: #17a2b8;
        }

        .stat-card .text-success {
            color: #28a745 !important;
        }

        .stat-card .text-warning {
            color: #ffc107 !important;
        }

        .stat-card .text-info {
            color: #17a2b8 !important;
        }

        /* Búsqueda y filtros */
        .filtros-card {
            margin-bottom: 1.5rem;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding-right: 40px;
        }

        .search-box .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--win-text-secondary);
        }

        /* Badge de filtro activo */
        .filtro-activo-badge {
            background: var(--win-accent);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 10px;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-limpiar-filtro {
            background: transparent;
            border: 1px solid var(--win-border-color);
            color: var(--win-text-secondary);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 8px;
            transition: var(--win-transition);
        }

        .btn-limpiar-filtro:hover {
            background: var(--win-accent-light);
            color: var(--win-accent);
            border-color: var(--win-accent);
        }

        /* Paginación */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--win-bg-secondary);
            border-top: 1px solid var(--win-border-color);
            border-radius: 0 0 var(--win-radius) var(--win-radius);
        }

        .pagination-info {
            font-size: 0.9rem;
            color: var(--win-text-secondary);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pagination-controls .form-select {
            width: auto;
            min-width: 100px;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-link {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            padding: 0.375rem 0.75rem;
            border-radius: var(--win-radius-sm);
            text-decoration: none;
            transition: var(--win-transition);
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: var(--win-accent-light);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }

        .page-link.active {
            background: var(--win-accent);
            border-color: var(--win-accent);
            color: white;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--win-bg-tertiary);
        }

        .page-link.disabled:hover {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-secondary);
        }

        .page-goto {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-goto-input {
            width: 60px;
            text-align: center;
            padding: 0.25rem;
        }

        .page-dots {
            color: var(--win-text-secondary);
            padding: 0.375rem 0.25rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .win-sidebar {
                transform: translateX(-100%);
            }
            
            .win-sidebar.open {
                transform: translateX(0);
            }
            
            .win-main-content {
                margin-left: 0;
            }
            
            .win-quick-actions {
                bottom: 16px;
                right: 16px;
            }
        }

        @media (max-width: 768px) {
            .win-main-content {
                padding: 16px;
            }
            
            .win-nav-search {
                display: none;
            }
            
            .win-quick-action {
                width: 50px;
                height: 50px;
                font-size: 18px;
            }
            
            .win-quick-actions-expanded .win-quick-action {
                width: 44px;
                height: 44px;
                font-size: 16px;
            }
            
            .filtro-activo-badge {
                margin-left: 0;
                margin-top: 8px;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .pagination-controls {
                flex-direction: column;
                gap: 10px;
            }
            
            .page-goto {
                justify-content: center;
            }
        }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--win-bg-tertiary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--win-border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--win-accent);
        }
        
        /* Badges de roles */
        .badge-user {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
        }

        .badge-operator {
            background: linear-gradient(135deg, #0dcaf0, #0bb5d4);
            color: white;
            border: none;
        }

        .badge-admin {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }
        
        .badge-supervisor {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
        }
        
        .badge-editor {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        
        .badge-programador {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
        }
        
        .badge-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
        }

        /* Estilo para alertas en SweetAlert */
        .swal2-popup .alert {
            border-radius: 6px;
            margin: 10px 0;
        }

        .swal2-popup .badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        /* Estilos para el modal de no admin */
        .modal-content {
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
        }

        .modal-header {
            background: var(--win-bg-tertiary);
        }

        .modal-footer {
            background: var(--win-bg-tertiary);
        }

        /* Badges para el modal */
        .badge-admin {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .badge-user {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .badge-operator {
            background: linear-gradient(135deg, #0dcaf0, #0bb5d4);
            color: white;
            border: none;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* ==================== CORRECCIÓN DE LEGIBILIDAD TEXT-MUTED ==================== */
        /* Para el tema oscuro (por defecto) - Usamos un gris muy claro */
        .text-muted, 
        .win-main-content .text-muted,
        .form-text,
        small.text-muted {
            color: #e0e0e0 !important; /* Casi blanco (mucho más visible) */
            opacity: 0.9;
        }

        /* Para el tema claro - Usamos un gris oscuro */
        [data-theme="light"] .text-muted,
        [data-theme="light"] .win-main-content .text-muted,
        [data-theme="light"] .form-text,
        [data-theme="light"] small.text-muted {
            color: #555555 !important; /* Gris oscuro fuerte */
            opacity: 1;
        }
        
        /* Estilos para botones de exportación */
        .btn-group .btn-outline-danger:hover i { color: white; }
        .btn-group .btn-outline-success:hover i { color: white; }
        .btn-group .btn-outline-primary:hover i { color: white; }
        .btn-group .btn-outline-info:hover i { color: white; }
        .btn-group .btn-outline-secondary:hover i { color: white; }
        .btn-group .btn-outline-warning:hover i { color: white; }

    </style>
</head>
<body>
    <!-- Overlay para tema panel -->
    <div class="win-theme-overlay" id="themeOverlay"></div>

    <!-- Panel de configuración de temas -->
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" 
                     data-theme="dark">
                    <i class="fas fa-moon mb-2"></i>
                    <div>Oscuro</div>
                </div>
            </div>
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" 
                     data-theme="light">
                    <i class="fas fa-sun mb-2"></i>
                    <div>Claro</div>
                </div>
            </div>
        </div>
        
        <h6 class="mb-3" style="color: var(--win-text-primary);">Color de acento</h6>
        <div class="win-color-options mb-4">
            <?php foreach ($colores_accent as $color => $nombre): ?>
                <div class="win-color-option <?php echo $color_accent == $color ? 'active' : ''; ?>"
                     style="background-color: <?php echo $color; ?>;"
                     data-color="<?php echo $color; ?>"
                     title="<?php echo $nombre; ?>"></div>
            <?php endforeach; ?>
        </div>
        
        <h6 class="mb-3" style="color: var(--win-text-primary);">Opciones de interfaz</h6>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini" 
                   <?php echo $sidebar_mini ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleSidebarMini" style="color: var(--win-text-primary);">
                Sidebar compacto
            </label>
        </div>
        
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleAnimations" checked>
            <label class="form-check-label" for="toggleAnimations" style="color: var(--win-text-primary);">
                Animaciones
            </label>
        </div>
        
        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()">
            <i class="fas fa-save"></i> Guardar cambios
        </button>
    </div>

    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <!-- Botón hamburguesa para móvil -->
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">SERVICIOS - SISFACT PDL Visiones</span>
        </div>
        
        <!-- Buscador -->
        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Acciones del navbar -->
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
<?= renderNotificationsDropdown() ?>
        
        <!-- Perfil de usuario -->
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
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
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
                <a href="servicios.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $total_servicios; ?></span>
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
                <a href="rentabilidad.php" class="win-nav-link">
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
            <!-- Título y Estado -->
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . '/' . $anio_cierre_num?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;">
                        <?php echo $finanzas['mensaje']; ?>
                    </small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                    <?php echo number_format($finanzas['porcentaje'], 1); ?>%
                </h5>
            </div>

            <!-- Barra de progreso -->
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>; transition: width 1s ease-in-out;" 
                     aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            
            <!-- Datos numéricos: Dinero y Cantidad -->
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <!-- Dinero Real -->
                    <small class="text-muted" style="font-size: 12px;">
                        <strong>$<?php echo number_format($finanzas['real'], 2); ?></strong>
                    </small>
                    <!-- Cantidad de Facturas (Nuevo) -->
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;">
                        <i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas
                    </small>
                </div>
                
                <!-- Meta -->
                <small class="text-end text-success" style="font-size: 12px;">
                    Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?>
                </small>
            </div>
        </div>
    </aside>

    <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header con indicador de filtro activo -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-list me-2" style="color: var(--win-accent);"></i>
                    <?php if ($modo_individual && $servicio_individual): ?>
                        Detalles del Servicio
                    <?php else: ?>
                        Gestión de Servicios
                    <?php endif; ?>
                    
                    <?php if ($categoria_id_filtro > 0 && $categoria_info): ?>
                        <span class="filtro-activo-badge">
                            <i class="fas fa-filter"></i>
                            Filtrado por: <?php echo htmlspecialchars($categoria_info['descripcion']); ?>
                            <button class="btn-limpiar-filtro" onclick="window.location.href='servicios.php'" title="Limpiar filtro">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($modo_individual && $servicio_individual): ?>
                        <span class="filtro-activo-badge" style="background: #5c2d91;">
                            <i class="fas fa-eye"></i>
                            Vista Individual
                            <button class="btn-limpiar-filtro" onclick="window.location.href='servicios.php'" title="Ver todos">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="text-muted mb-0">
                    <?php if ($modo_individual && $servicio_individual): ?>
                        Detalles completos del servicio: <strong style="color: var(--win-accent);"><?php echo htmlspecialchars($servicio_individual['descripcion']); ?></strong>
                    <?php elseif ($categoria_id_filtro > 0 && $categoria_info): ?>
                        Mostrando servicios de la categoría: <strong><?php echo htmlspecialchars($categoria_info['descripcion']); ?></strong>
                    <?php else: ?>
                        Administre los servicios ofrecidos por el sistema<br>Fecha de Cierre Operaciones: <span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <?php if ($modo_individual && $servicio_individual): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="actualizarListaServicios()">
                            <i class="fas fa-sync-alt me-1"></i>Actualizar
                        </button>
                        <a href="editar_servicio.php?id=<?php echo $servicio_individual['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit me-1"></i>Editar Servicio
                        </a>
                        <a href="servicios.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-list me-1"></i>Ver Todos
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="actualizarListaServicios()">
                            <i class="fas fa-sync-alt me-1"></i>Actualizar
                        </button>
                        <a href="nuevo_servicio.php<?php echo $categoria_id_filtro > 0 ? '?categoria_id=' . $categoria_id_filtro : ''; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>Nuevo Servicio
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Vista Individual del Servicio -->
        <?php if ($modo_individual && $servicio_individual): ?>
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-info-circle me-1"></i>Información General
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <span class="badge bg-primary" style="font-size: 1.2rem; padding: 8px 16px;">
                                    <?php echo htmlspecialchars($servicio_individual['codigo']); ?>
                                </span>
                            </div>
                            <h4 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($servicio_individual['descripcion']); ?></h4>
                            <div class="mt-2">
                                <span class="badge <?php echo $servicio_individual['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $servicio_individual['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="small text-muted">Categoría:</label>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-secondary me-2">
                                    <?php echo htmlspecialchars($servicio_individual['categoria_codigo'] ?? 'N/A'); ?>
                                </span>
                                <span style="color: var(--win-text-primary);">
                                    <?php echo htmlspecialchars($servicio_individual['categoria_nombre'] ?? 'Sin categoría'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="small text-muted">Costo:</label>
                            <h5 class="text-success fw-bold">$<?php echo number_format($servicio_individual['costo'], 2); ?></h5>
                        </div>
                        
                        <div class="mb-3">
                            <label class="small text-muted">Fecha de creación:</label>
                            <div style="color: var(--win-text-primary);">
                                <?php echo date('d/m/Y', strtotime($servicio_individual['created_at'] ?? 'now')); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($servicio_individual['observaciones'])): ?>
                        <div class="mb-3">
                            <label class="small text-muted">Observaciones:</label>
                            <div class="border rounded p-2" style="color: var(--win-text-primary); background: var(--win-bg-tertiary);">
                                <?php echo nl2br(htmlspecialchars($servicio_individual['observaciones'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-chart-bar me-1"></i>Estadísticas de Ventas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card success h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Ventas</div>
                                                <div class="h3 mb-0 fw-bold text-warning"><?php echo $servicio_individual['total_ventas']; ?></div>
                                                <div class="text-success small">
                                                    <i class="fas fa-shopping-cart me-1"></i>Veces vendido
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <i class="fas fa-chart-line fa-2x text-success opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card warning h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Ingresos Generados</div>
                                                <div class="h3 mb-0 fw-bold text-success">$<?php echo number_format($servicio_individual['total_ingresos'], 2); ?></div>
                                                <div class="text-warning small">
                                                    <i class="fas fa-dollar-sign me-1"></i>Total acumulado
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <i class="fas fa-money-bill-wave fa-2x text-warning opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card info h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Unidades Vendidas</div>
                                                <div class="h3 mb-0 fw-bold text-warning"><?php echo $servicio_individual['total_unidades'] ?? 0; ?></div>
                                                <div class="text-info small">
                                                    <i class="fas fa-boxes me-1"></i>Total unidades
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <i class="fas fa-cubes fa-2x text-info opacity-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="mb-3" style="color: var(--win-text-primary);">Acciones Rápidas</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="facturas.php?servicio_id=<?php echo $servicio_individual['id']; ?>" 
                                   class="btn btn-outline-info">
                                    <i class="fas fa-file-invoice me-1"></i>Ver Facturas
                                </a>
                                <a href="editar_servicio.php?id=<?php echo $servicio_individual['id']; ?>" 
                                   class="btn btn-outline-warning">
                                    <i class="fas fa-edit me-1"></i>Editar
                                </a>
                                <?php if ($es_admin): ?>
                                    <button onclick="eliminarServicio(<?php echo $servicio_individual['id']; ?>)" 
                                            class="btn btn-outline-danger">
                                        <i class="fas fa-trash me-1"></i>Eliminar
                                    </button>
                                <?php else: ?>
                                    <button onclick="mostrarModalNoAdmin()" 
                                            class="btn btn-outline-secondary">
                                        <i class="fas fa-trash me-1"></i>Eliminar
                                    </button>
                                <?php endif; ?>
                                <a href="servicios.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Volver a la lista
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Últimas facturas relacionadas -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-history me-1"></i>Últimas Ventas
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Consultar últimas facturas de este servicio
                        $sql_ultimas_ventas = "SELECT f.id, f.no_fact, f.fecha_emision as fecha, f.total_general as total, 
                                                       c.nombre as cliente_nombre,
                                                       fd.cantidad, fd.total_linea
                                                FROM tbl_fact_detalle fd
                                                INNER JOIN tbl_fact f ON fd.factura_id = f.id
                                                LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                                                WHERE fd.servicio_id = :servicio_id
                                                AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                                                AND f.fecha_emision IS NOT NULL
                                                ORDER BY f.fecha_emision DESC
                                                LIMIT 5";
                        
                        $stmt_ventas = $db->prepare($sql_ultimas_ventas);
                        $stmt_ventas->execute(['servicio_id' => $servicio_id_individual]);
                        $ultimas_ventas = $stmt_ventas->fetchAll();
                        ?>
                        
                        <?php if (count($ultimas_ventas) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Factura</th>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Cantidad</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas_ventas as $venta): ?>
                                            <tr>
                                                <td>
                                                    <a href="ver_factura.php?id=<?php echo $venta['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($venta['no_fact']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></td>
                                                <td><?php echo $venta['cantidad']; ?></td>
                                                <td class="text-success fw-bold">$<?php echo number_format($venta['total_linea'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-2">
                                <a href="facturas.php?servicio_id=<?php echo $servicio_individual['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    Ver todas las ventas <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Este servicio no tiene ventas registradas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Estadísticas generales (mostradas solo si no hay filtro o con estadísticas del filtro) -->
        <?php if ($categoria_id_filtro == 0 && !$modo_individual): ?>
        <div class="row mb-4">
            <!-- Tarjeta 1: Total Servicios -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card success h-100 animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Servicios</div>
                                <div class="h5 mb-0 fw-bold text-warning"><?php echo $total_servicios; ?></div>
                                <div class="text-success small">
                                    <i class="fas fa-list-alt me-1"></i>Registrados en el sistema
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-concierge-bell fa-2x text-success opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Activos:</small>
                                <small class="fw-bold text-success"><?php echo $total_servicios_activos_total; ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Inactivos:</small>
                                <small class="fw-bold text-danger"><?php echo $total_servicios - $total_servicios_activos_total; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta 2: Ingresos Totales (Año Operativo) -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card warning h-100 animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Ingresos Totales (<?php echo $anio_cierre_num; ?>)</div>
                                <div class="h5 mb-0 fw-bold text-success">$<?php echo number_format($ingresos_total_anual, 2); ?></div>
                                <div class="text-warning small">
                                    <i class="fas fa-money-bill-wave me-1"></i>Total acumulado
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-chart-pie fa-2x text-warning opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Promedio por servicio:</small>
                                <small class="fw-bold text-info">
                                    $<?php echo $total_servicios_con_ventas > 0 ? number_format($ingresos_total_anual / $total_servicios_con_ventas, 2) : '0.00'; ?>
                                </small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Servicios con ventas:</small>
                                <small class="fw-bold text-success"><?php echo $total_servicios_con_ventas; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta 3: Servicio Más Vendido -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card info h-100 animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Más Vendido (<?php echo $anio_cierre_num; ?>)</div>
                                <?php if ($servicio_mas_ventas && $servicio_mas_ventas['total_ventas'] > 0): ?>
                                    <div class="h6 mb-0 fw-bold text-warning" title="<?php echo htmlspecialchars($servicio_mas_ventas['descripcion']); ?>">
                                        <?php echo htmlspecialchars($servicio_mas_ventas['descripcion']); ?>
                                    </div>
                                    <div class="text-info small">
                                        <i class="fas fa-chart-line me-1"></i><?php echo $servicio_mas_ventas['total_ventas']; ?> ventas
                                    </div>
                                <?php else: ?>
                                    <div class="h6 mb-0 fw-bold text-info">Sin ventas</div>
                                    <div class="text-info small">
                                        <i class="fas fa-chart-line me-1"></i>0 ventas
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-trophy fa-2x text-info opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <?php if ($servicio_mas_ventas && $servicio_mas_ventas['total_ventas'] > 0): ?>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Código:</small>
                                    <small class="fw-bold"><?php echo $servicio_mas_ventas['codigo']; ?></small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Categoría:</small>
                                    <small class="fw-bold text-truncate" style="max-width: 120px;"
                                           title="<?php echo htmlspecialchars($servicio_mas_ventas['categoria']); ?>">
                                        <?php echo htmlspecialchars($servicio_mas_ventas['categoria']); ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Unidades vendidas:</small>
                                    <small class="fw-bold text-warning">
                                        <?php echo isset($servicio_mas_ventas['cantidad_unidades']) ? number_format($servicio_mas_ventas['cantidad_unidades']) : '0'; ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Importe total:</small>
                                    <small class="fw-bold text-success">
                                        $<?php echo number_format($servicio_mas_ventas['importe_total'] ?? 0, 2); ?>
                                    </small>
                                </div>
                            <?php else: ?>
                                <small class="text-muted d-block">No hay ventas registradas este año</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta 4: Servicio Menos Vendido -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card h-100 animate__animated animate__fadeIn" style="animation-delay: 0.4s; border-left-color: var(--win-accent);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">
                                    Menos Vendido (<?php echo $anio_cierre_num; ?>)
                                </div>
                                <?php if ($servicio_menos_ventas && $servicio_menos_ventas['total_ventas'] > 0): ?>
                                    <div class="h6 mb-0 fw-bold text-warning" title="<?php echo htmlspecialchars($servicio_menos_ventas['descripcion']); ?>">
                                        <?php echo htmlspecialchars($servicio_menos_ventas['descripcion']); ?>
                                    </div>
                                    <div style="color: var(--win-accent);" class="small">
                                        <i class="fas fa-chart-line me-1"></i><?php echo $servicio_menos_ventas['total_ventas']; ?> ventas
                                    </div>
                                <?php else: ?>
                                    <div class="h6 mb-0 fw-bold" style="color: var(--win-accent);">Sin ventas</div>
                                    <div style="color: var(--win-accent);" class="small">
                                        <i class="fas fa-chart-line me-1"></i>0 ventas
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-chart-bar fa-2x opacity-50" style="color: var(--win-accent);"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <?php if ($servicio_menos_ventas && $servicio_menos_ventas['total_ventas'] > 0): ?>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Código:</small>
                                    <small class="fw-bold"><?php echo $servicio_menos_ventas['codigo']; ?></small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Categoría:</small>
                                    <small class="fw-bold text-truncate" style="max-width: 120px;"
                                           title="<?php echo htmlspecialchars($servicio_menos_ventas['categoria']); ?>">
                                        <?php echo htmlspecialchars($servicio_menos_ventas['categoria']); ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Unidades vendidas:</small>
                                    <small class="fw-bold text-warning">
                                        <?php echo isset($servicio_menos_ventas['cantidad_unidades']) ? number_format($servicio_menos_ventas['cantidad_unidades']) : '0'; ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Importe total:</small>
                                    <small class="fw-bold text-success">
                                        $<?php echo number_format($servicio_menos_ventas['importe_total'] ?? 0, 2); ?>
                                    </small>
                                </div>
                            <?php else: ?>
                                <small class="text-muted d-block">No hay ventas registradas este año</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Estadísticas del filtro por categoría -->
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card success h-100 animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Servicios en Categoría</div>
                                <div class="h5 mb-0 fw-bold text-success"><?php echo $total_servicios_filtrados; ?></div>
                                <div class="text-success small">
                                    <i class="fas fa-list-alt me-1"></i>Servicios encontrados
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-filter fa-2x text-success opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Activos:</small>
                                <small class="fw-bold text-success"><?php echo $total_servicios_activos; ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Inactivos:</small>
                                <small class="fw-bold text-danger"><?php echo $total_servicios_inactivos; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card warning h-100 animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Ingresos Totales</div>
                                <div class="h5 mb-0 fw-bold text-warning">$<?php echo number_format($total_ingresos_general, 2); ?></div>
                                <div class="text-warning small">
                                    <i class="fas fa-dollar-sign me-1"></i>Generados por estos servicios
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-money-bill-wave fa-2x text-warning opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Costo promedio:</small>
                                <small class="fw-bold text-info">$<?php echo $total_servicios_filtrados > 0 ? number_format($costo_promedio / $total_servicios_filtrados, 2) : '0.00'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card stat-card info h-100 animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Información</div>
                                <div class="h5 mb-0 fw-bold text-info">Categoría: <?php echo $categoria_info ? htmlspecialchars($categoria_info['codigo']) : 'N/A'; ?></div>
                                <div class="text-info small">
                                    <i class="fas fa-tags me-1"></i><?php echo $categoria_info ? htmlspecialchars($categoria_info['descripcion']) : 'Categoría no encontrada'; ?>
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-info-circle fa-2x text-info opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="window.location.href='categorias.php'">
                                <i class="fas fa-arrow-left me-1"></i>Ver todas las categorías
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Búsqueda y filtros -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-search me-1"></i>Buscar Servicios
                </h6>
                <!-- ===== BOTONES DE EXPORTACIÓN - TODOS LOS FORMATOS ===== -->
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="exportarExcel()" 
                            title="Exportar a Excel" data-bs-toggle="tooltip">
                        <i class="fas fa-file-excel"></i>
                    </button>          
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="exportarPDF()" 
                            title="Exportar a PDF" data-bs-toggle="tooltip">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportarWord()" 
                            title="Exportar a Word" data-bs-toggle="tooltip">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="exportarCSV()" 
                            title="Exportar a CSV" data-bs-toggle="tooltip">
                        <i class="fas fa-file-csv"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarTXT()" 
                            title="Exportar a TXT" data-bs-toggle="tooltip">
                        <i class="fas fa-file-alt"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="imprimirTabla()" 
                            title="Imprimir" data-bs-toggle="tooltip">
                        <i class="fas fa-print"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="exportarServicios()" 
                            title="Todos los formatos" data-bs-toggle="tooltip">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="search-box">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Buscar servicio por código, descripción o categoría...">
                            <div class="search-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus">
                            <option value="">Todos los estados</option>
                            <option value="activo">Solo activos</option>
                            <option value="inactivo">Solo inactivos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterCategoria">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo htmlspecialchars($categoria['id']); ?>" 
                                    <?php echo ($categoria_id_filtro > 0 && $categoria['id'] == $categoria_id_filtro) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Servicios -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <?php if ($categoria_id_filtro > 0 && $categoria_info): ?>
                        Servicios de: <?php echo htmlspecialchars($categoria_info['descripcion']); ?>
                    <?php else: ?>
                        Lista de Servicios
                    <?php endif; ?>
                </h6>
                <div>
                    <span class="badge bg-info me-2"><?php echo $total_servicios_filtrados; ?> servicios</span>
                    <?php if ($total_servicios_filtrados > 0): ?>
                        <span class="badge bg-success">Ingresos: $<?php echo number_format($total_ingresos_general, 2); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Paginación superior -->
            <?php if ($total_registros > 0 && !$modo_individual): ?>
            <div class="pagination-container" style="border-bottom: 1px solid var(--win-border-color);">
                <div class="pagination-info">
                    <?php 
                    $inicio = min($offset + 1, $total_registros);
                    $fin = min($offset + $registros_por_pagina, $total_registros);
                    ?>
                    Mostrando <?php echo $inicio; ?> - <?php echo $fin; ?> de <?php echo $total_registros; ?> servicios 
                    (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                </div>
                
                <div class="pagination-controls">
                    <div class="pagination-nav">
                        <!-- Botón Primera Página -->
                        <a href="<?php echo generarUrlPaginacion(1); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Primera página">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        
                        <!-- Botón Anterior -->
                        <a href="<?php echo generarUrlPaginacion(max(1, $pagina_actual - 1)); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Página anterior">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        
                        <!-- Números de página -->
                        <?php 
                        $inicio_pagina = max(1, $pagina_actual - 2);
                        $fin_pagina = min($total_paginas, $pagina_actual + 2);
                        
                        if ($inicio_pagina > 1): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio_pagina; $i <= $fin_pagina; $i++): ?>
                            <a href="<?php echo generarUrlPaginacion($i); ?>" class="page-link <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($fin_pagina < $total_paginas): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                        
                        <!-- Botón Siguiente -->
                        <a href="<?php echo generarUrlPaginacion(min($total_paginas, $pagina_actual + 1)); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Página siguiente">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        
                        <!-- Botón Última Página -->
                        <a href="<?php echo generarUrlPaginacion($total_paginas); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Última página">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
                    
                    <!-- Registros por página -->
                    <select class="form-select" id="registrosPorPaginaTop" onchange="cambiarRegistrosPorPagina(this.value)">
                        <option value="5" <?php echo $registros_por_pagina == 5 ? 'selected' : ''; ?>>5 por página</option>
                        <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
						<option value="15" <?php echo $registros_por_pagina == 15 ? 'selected' : ''; ?>>15 por página</option>
						<option value="20" <?php echo $registros_por_pagina == 20 ? 'selected' : ''; ?>>20 por página</option>
                        <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25 por página</option>
						<option value="30" <?php echo $registros_por_pagina == 30 ? 'selected' : ''; ?>>30 por página</option>
                        <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
						<option value="99999" <?php echo $registros_por_pagina == 99999 ? 'selected' : ''; ?>>Todos los Registros</option>
                    </select>
                    
                    <!-- Ir a página -->
                    <div class="page-goto">
                        <span style="color: var(--win-text-secondary);">Ir a:</span>
                        <input type="number" class="form-control page-goto-input" 
                               id="gotoPageTop" 
                               min="1" 
                               max="<?php echo $total_paginas; ?>" 
                               value="<?php echo $pagina_actual; ?>">
                        <button class="btn btn-outline-primary btn-sm" onclick="irAPaginaDesdeTop()">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="serviciosTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <?php if ($categoria_id_filtro == 0 && !$modo_individual): ?>
                                    <th>Categoría</th>
                                <?php endif; ?>
                                <th>Costo</th>
                                <th>Ventas</th>
                                <th>Ingresos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($servicios)): ?>
                                <tr>
                                    <td colspan="<?php echo ($categoria_id_filtro == 0 && !$modo_individual) ? '8' : '7'; ?>" class="text-center py-4">
                                        <i class="fas fa-list text-muted fa-2x mb-2 d-block"></i>
                                        <?php if ($categoria_id_filtro > 0): ?>
                                            No hay servicios en esta categoría
                                        <?php else: ?>
                                            No hay servicios registrados
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($servicios as $servicio): ?>
                                    <tr data-status="<?php echo $servicio['activo'] ? 'activo' : 'inactivo'; ?>" 
                                        data-categoria="<?php echo htmlspecialchars($servicio['categoria_id'] ?? ''); ?>">
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($servicio['codigo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: var(--win-text-primary);">
                                                <a href="servicios.php?id=<?php echo $servicio['id']; ?>" class="text-decoration-none" style="color: var(--win-text-primary);">
                                                    <?php echo htmlspecialchars($servicio['descripcion']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <?php if ($categoria_id_filtro == 0 && !$modo_individual): ?>
                                            <td>
                                                <?php if (!empty($servicio['categoria_nombre'])): ?>
                                                    <a href="servicios.php?categoria_id=<?php echo $servicio['categoria_id']; ?>" class="text-decoration-none">
                                                        <span class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($servicio['categoria_codigo']); ?>
                                                        </span>
                                                        <small class="d-block text-muted">
                                                            <?php echo htmlspecialchars($servicio['categoria_nombre']); ?>
                                                        </small>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin categoría</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="fw-bold text-success">
                                                $<?php echo number_format($servicio['costo'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $servicio['total_ventas']; ?> ventas
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?php echo $servicio['total_ingresos'] > 0 ? 'text-warning' : 'text-muted'; ?>">
                                                $<?php echo number_format($servicio['total_ingresos'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $servicio['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $servicio['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="editar_servicio.php?id=<?php echo $servicio['id']; ?>" 
                                                   class="btn btn-outline-warning" 
                                                   title="Editar" data-bs-toggle="tooltip">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="servicios.php?id=<?php echo $servicio['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Ver Detalles" data-bs-toggle="tooltip">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="facturas.php?servicio_id=<?php echo $servicio['id']; ?>" 
                                                   class="btn btn-outline-info" 
                                                   title="Ver Facturas" data-bs-toggle="tooltip">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                                
                                                <!-- Botón Activar/Desactivar -->
                                                <?php if ($es_admin): ?>
                                                    <button onclick="toggleEstadoServicio(<?php echo $servicio['id']; ?>, <?php echo $servicio['activo']; ?>)" 
                                                            class="btn <?php echo $servicio['activo'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                            title="<?php echo $servicio['activo'] ? 'Desactivar' : 'Activar'; ?>" 
                                                            data-bs-toggle="tooltip"
                                                            id="toggleBtn<?php echo $servicio['id']; ?>">
                                                        <i class="fas <?php echo $servicio['activo'] ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="mostrarModalNoAdmin()" 
                                                            class="btn btn-outline-secondary" 
                                                            title="Solo administradores" data-bs-toggle="tooltip">
                                                        <i class="fas <?php echo $servicio['activo'] ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($es_admin): ?>
                                                    <button onclick="eliminarServicio(<?php echo $servicio['id']; ?>)" 
                                                            class="btn btn-outline-danger" 
                                                            title="Eliminar" data-bs-toggle="tooltip">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="mostrarModalNoAdmin()" 
                                                            class="btn btn-outline-secondary" 
                                                            title="Solo administradores" data-bs-toggle="tooltip">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación inferior -->
                <?php if ($total_registros > 0 && !$modo_individual): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        <?php 
                        $inicio = min($offset + 1, $total_registros);
                        $fin = min($offset + $registros_por_pagina, $total_registros);
                        ?>
                        Mostrando <?php echo $inicio; ?> - <?php echo $fin; ?> de <?php echo $total_registros; ?> servicios 
                        (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                    </div>
                    
                    <div class="pagination-controls">
                        <div class="pagination-nav">
                            <!-- Botón Primera Página -->
                            <a href="<?php echo generarUrlPaginacion(1); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Primera página">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            
                            <!-- Botón Anterior -->
                            <a href="<?php echo generarUrlPaginacion(max(1, $pagina_actual - 1)); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Página anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                            
                            <!-- Números de página -->
                            <?php 
                            $inicio_pagina = max(1, $pagina_actual - 2);
                            $fin_pagina = min($total_paginas, $pagina_actual + 2);
                            
                            if ($inicio_pagina > 1): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            
                            <?php for ($i = $inicio_pagina; $i <= $fin_pagina; $i++): ?>
                                <a href="<?php echo generarUrlPaginacion($i); ?>" class="page-link <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($fin_pagina < $total_paginas): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            
                            <!-- Botón Siguiente -->
                            <a href="<?php echo generarUrlPaginacion(min($total_paginas, $pagina_actual + 1)); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Página siguiente">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            
                            <!-- Botón Última Página -->
                            <a href="<?php echo generarUrlPaginacion($total_paginas); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Última página">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>
                        
                        <!-- Registros por página -->
                        <select class="form-select" id="registrosPorPagina" onchange="cambiarRegistrosPorPagina(this.value)">
                        <option value="5" <?php echo $registros_por_pagina == 5 ? 'selected' : ''; ?>>5 por página</option>
                        <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
						<option value="15" <?php echo $registros_por_pagina == 15 ? 'selected' : ''; ?>>15 por página</option>
						<option value="20" <?php echo $registros_por_pagina == 20 ? 'selected' : ''; ?>>20 por página</option>
                        <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25 por página</option>
						<option value="30" <?php echo $registros_por_pagina == 30 ? 'selected' : ''; ?>>30 por página</option>
                        <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
						<option value="99999" <?php echo $registros_por_pagina == 99999 ? 'selected' : ''; ?>>Todos los Registros</option>
                        </select>
                        
                        <!-- Ir a página -->
                        <div class="page-goto">
                            <span style="color: var(--win-text-secondary);">Ir a:</span>
                            <input type="number" class="form-control page-goto-input" 
                                   id="gotoPage" 
                                   min="1" 
                                   max="<?php echo $total_paginas; ?>" 
                                   value="<?php echo $pagina_actual; ?>">
                            <button class="btn btn-outline-primary btn-sm" onclick="irAPagina()">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($categoria_id_filtro > 0 && !$modo_individual): ?>
                <div class="card-footer text-center">
                    <a href="servicios.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Ver todos los servicios
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Quick Actions -->
    <div class="win-quick-actions">
        <!-- Acciones expandidas -->
        <div class="win-quick-actions-expanded" id="quickActionsExpanded">
            <button class="win-quick-action action-factura" onclick="window.location.href='nueva_factura.php'" title="Nueva Factura">
                <i class="fas fa-file-invoice"></i>
            </button>
            <button class="win-quick-action action-cliente" onclick="window.location.href='nuevo_cliente.php'" title="Nuevo Cliente">
                <i class="fas fa-user-plus"></i>
            </button>
            <button class="win-quick-action action-servicio" onclick="window.location.href='nuevo_servicio.php<?php echo $categoria_id_filtro > 0 ? '?categoria_id=' . $categoria_id_filtro : ''; ?>'" title="Nuevo Servicio">
                <i class="fas fa-plus-circle"></i>
            </button>
        </div>
        
        <!-- Botón principal -->
        <button class="win-quick-action" onclick="toggleQuickActions()" title="Acciones rápidas" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- ===== SCRIPTS DE EXPORTACIÓN - AGREGADOS AL FINAL ===== -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // ============================================
        // VARIABLES GLOBALES PARA EXPORTACIÓN
        // ============================================
        const totalServiciosGlobal = <?php echo $total_servicios; ?>;
        const serviciosActivos = <?php echo $total_servicios_activos_total; ?>;
        const serviciosInactivos = <?php echo $total_servicios - $total_servicios_activos_total; ?>;
        const totalVentasGlobal = <?php echo $total_ventas_general; ?>;
        const totalIngresosGlobal = <?php echo $ingresos_total_anual; ?>;
        const usuarioNombre = "<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'SISFACT PDL VISIONES'); ?>";
        const logoBase64 = '<?php echo $logo_base64; ?>';
        const categoriaFiltroId = <?php echo $categoria_id_filtro; ?>;
        const servicioIndividualId = <?php echo isset($servicio_id_individual) && $servicio_id_individual !== '' ? (int)$servicio_id_individual : 'null'; ?>;
        const modoIndividual = <?php echo $modo_individual ? 'true' : 'false'; ?>;
        
        // ===== NUEVAS VARIABLES PARA FECHAS DE CIERRE DE OPERACIONES =====
        const fechaInicioOperaciones = "<?php echo $fecha_inicio_operaciones; ?>";
        const fechaInicioOperacionesFormateada = "<?php echo $fecha_inicio_formateada; ?>";
        const mesCierreOperaciones = <?php echo $mes_cierre_operaciones; ?>;
        const mesCierreOperacionesNombre = "<?php echo $mes_cierre_nombre; ?>";
        const añoInicioOperaciones = <?php echo $año_inicio_operaciones; ?>;
        const anioCierre = <?php echo $anio_cierre_num; ?>;
        const mesCierre = <?php echo $mes_cierre_num; ?>;
        const mesActualEs = "<?php echo $mes_actual_es; ?>";

        // ============================================
        // FECHA DE CIERRE DE OPERACIONES PARA EXPORTACIONES
        // ============================================
        function obtenerFechaOperacion() {
            if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
                // Dividir la fecha manualmente para evitar problemas de zona horaria
                const partes = fechaInicioOperaciones.split('-');
                const año = parseInt(partes[0]);
                const mes = parseInt(partes[1]) - 1; // Los meses en JS son 0-11
                const dia = parseInt(partes[2]);
                
                const fecha = new Date(año, mes, dia);
                const opciones = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                };
                return fecha.toLocaleDateString('es-ES', opciones);
            }
            
            // Fallback: usar fecha actual
            const fecha = new Date();
            const opciones = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            };
            return fecha.toLocaleDateString('es-ES', opciones);
        }

        function obtenerFechaOperacionCorta() {
            if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
                return fechaInicioOperaciones; // Formato YYYY-MM-DD
            }
            
            // Fallback: usar fecha actual
            const fecha = new Date();
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }

        function obtenerPeriodoCierre() {
            // Usar el año de la fecha de inicio de operaciones
            if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
                const partes = fechaInicioOperaciones.split('-');
                const año = partes[0];
                return `${mesCierreOperacionesNombre} / ${año}`;
            }
            return `${mesCierreOperacionesNombre} / ${new Date().getFullYear()}`;
        }
        
        function obtenerPeriodoOperativo() {
            return `${mesActualEs} / ${anioCierre}`;
        }

        // ============================================
        // FUNCIÓN PARA OBTENER DATOS DE LA TABLA
        // ============================================
function obtenerDatosTabla() {
    const tabla = document.getElementById('serviciosTable');
    if (!tabla) return { datos: [], totalFiltrado: 0 };
    
    const datos = [];
    const filas = Array.from(tabla.querySelectorAll('tbody tr'));
    
    // Determinar el número de columnas según el contexto
    const tieneCategoria = <?php echo ($categoria_id_filtro == 0 && !$modo_individual) ? 'true' : 'false'; ?>;
    
    filas.forEach(fila => {
        if (fila.querySelector('td[colspan]')) return;
        
        const celdas = fila.querySelectorAll('td');
        let filaDatos = [];
        
        if (tieneCategoria) {
            if (celdas.length >= 8) {
                // Extraer código
                const codigoElem = celdas[0]?.querySelector('.badge');
                const codigo = codigoElem ? codigoElem.textContent.trim() : '-';
                
                // Extraer descripción
                const descElem = celdas[1]?.querySelector('.fw-bold a, .fw-bold');
                const descripcion = descElem ? descElem.textContent.trim() : '-';
                
                // Extraer categoría
                let categoria = '';
                const catCodigo = celdas[2]?.querySelector('.badge');
                const catNombre = celdas[2]?.querySelector('small');
                if (catCodigo) categoria += catCodigo.textContent.trim() + ' ';
                if (catNombre) categoria += catNombre.textContent.trim();
                
                // Extraer costo - ELIMINAR símbolo $ y comas
                const costoText = celdas[3]?.textContent?.trim().replace('$', '').replace(/,/g, '') || '0';
                const costo = parseFloat(costoText) || 0;
                
                // Extraer ventas - SOLO EL NÚMERO
                const ventasElem = celdas[4]?.querySelector('.badge');
                let ventasText = ventasElem ? ventasElem.textContent.replace('ventas', '').trim() : '0';
                const ventas = parseInt(ventasText) || 0;
                
                // Extraer ingresos - ELIMINAR símbolo $ y comas
                const ingresosText = celdas[5]?.textContent?.trim().replace('$', '').replace(/,/g, '') || '0';
                const ingresos = parseFloat(ingresosText) || 0;
                
                // Extraer estado
                const estadoElem = celdas[6]?.querySelector('.badge');
                const estado = estadoElem ? estadoElem.textContent.trim() : '-';
                
                filaDatos = [codigo, descripcion, categoria, ventas, costo, ingresos, estado];
            }
        } else {
            if (celdas.length >= 7) {
                // Extraer código
                const codigoElem = celdas[0]?.querySelector('.badge');
                const codigo = codigoElem ? codigoElem.textContent.trim() : '-';
                
                // Extraer descripción
                const descElem = celdas[1]?.querySelector('.fw-bold a, .fw-bold');
                const descripcion = descElem ? descElem.textContent.trim() : '-';
                
                // Extraer costo - ELIMINAR símbolo $ y comas
                const costoText = celdas[2]?.textContent?.trim().replace('$', '').replace(/,/g, '') || '0';
                const costo = parseFloat(costoText) || 0;
                
                // Extraer ventas - SOLO EL NÚMERO
                const ventasElem = celdas[3]?.querySelector('.badge');
                let ventasText = ventasElem ? ventasElem.textContent.replace('ventas', '').trim() : '0';
                const ventas = parseInt(ventasText) || 0;
                
                // Extraer ingresos - ELIMINAR símbolo $ y comas
                const ingresosText = celdas[4]?.textContent?.trim().replace('$', '').replace(/,/g, '') || '0';
                const ingresos = parseFloat(ingresosText) || 0;
                
                // Extraer estado
                const estadoElem = celdas[5]?.querySelector('.badge');
                const estado = estadoElem ? estadoElem.textContent.trim() : '-';
                
                filaDatos = [codigo, descripcion, ventas, costo, ingresos, estado];
            }
        }
        
        if (filaDatos.length > 0) {
            datos.push(filaDatos);
        }
    });
    
    return {
        datos: datos,
        totalFiltrado: datos.length,
        totalGeneral: totalServiciosGlobal,
        tieneCategoria: tieneCategoria
    };
}
		
		// ============================================
        // FORMATEAR FECHA PARA ARCHIVOS
        // ============================================
        function formatearFechaParaArchivo() {
            const fecha = new Date();
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            const horas = String(fecha.getHours()).padStart(2, '0');
            const minutos = String(fecha.getMinutes()).padStart(2, '0');
            const segundos = String(fecha.getSeconds()).padStart(2, '0');
            return `${año}${mes}${dia}_${horas}${minutos}${segundos}`;
        }

// ============================================
// EXPORTAR A PDF - VERSIÓN CON TEXTO NEGRO GARANTIZADO
// ============================================
function exportarPDF() {
    try {
        Swal.fire({
            title: 'Generando PDF...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        const tablaDatos = obtenerDatosTabla();
        if (tablaDatos.datos.length === 0) {
            Swal.close();
            Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay servicios para exportar' });
            return;
        }

        const fechaOperacion = obtenerFechaOperacion();
        const periodoCierre = obtenerPeriodoCierre();
        
        // Determinar título según el contexto
        let tituloReporte = 'REPORTE DE SERVICIOS';
        let subtituloReporte = '';
        
        if (modoIndividual) {
            tituloReporte = 'DETALLE DE SERVICIO INDIVIDUAL';
        } else if (categoriaFiltroId > 0) {
            tituloReporte = 'SERVICIOS POR CATEGORÍA';
            const categoriaSelect = document.getElementById('filterCategoria');
            if (categoriaSelect && categoriaSelect.selectedOptions[0]) {
                subtituloReporte = categoriaSelect.selectedOptions[0].text;
            }
        }
        
        // Crear elemento para el PDF
        const element = document.createElement('div');
        element.style.fontFamily = 'Arial, sans-serif';
        element.style.padding = '20px';
        element.style.backgroundColor = 'white';
        element.style.color = '#000000'; // Forzar color negro para todo
        
        // Función para formatear moneda
        const formatoMoneda = (valor) => {
            return new Intl.NumberFormat('es-ES', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            }).format(valor);
        };
        
        // Construir HTML simplificado con colores forzados
        let htmlContent = `
            <div style="max-width: 1200px; margin: 0 auto; color: #000000;">
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #0078d4;">
                    <div style="display: flex; align-items: center;">
                        <div style="width: 60px; height: 60px; background: #0078d4; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <span style="color: white; font-size: 24px; font-weight: bold;">PDL</span>
                        </div>
                        <div>
                            <h1 style="color: #0078d4; margin: 0; font-size: 24px;">SISFACT PDL Visiones</h1>
                            <p style="color: #333333; margin: 5px 0 0 0; font-size: 12px;">Sistema de Facturación y Gestión</p>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <h2 style="color: #000000; margin: 0; font-size: 20px;">${tituloReporte}</h2>
                        ${subtituloReporte ? `<p style="color: #0078d4; margin: 5px 0 0 0; font-weight: bold;">${subtituloReporte}</p>` : ''}
                    </div>
                </div>
                
                <!-- Información de fechas -->
                <div style="display: flex; justify-content: space-between; background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="text-align: center; flex: 1;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Fecha Inicio Operaciones</div>
                        <div style="color: #0078d4; font-size: 14px; font-weight: bold;">${fechaOperacion}</div>
                    </div>
                    <div style="text-align: center; flex: 1;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Período de Cierre</div>
                        <div style="color: #000000; font-size: 14px; font-weight: bold;">${periodoCierre}</div>
                    </div>
                    <div style="text-align: center; flex: 1;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Fecha Generación</div>
                        <div style="color: #000000; font-size: 14px;">${new Date().toLocaleDateString('es-ES')}</div>
                    </div>
                </div>
                
                <!-- Tarjetas de estadísticas -->
                <div style="display: flex; gap: 15px; margin-bottom: 25px;">
                    <div style="flex: 1; background: white; border: 1px solid #dddddd; border-radius: 8px; padding: 15px;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Total Servicios</div>
                        <div style="color: #000000; font-size: 28px; font-weight: bold; margin: 5px 0;">${totalServiciosGlobal}</div>
                        <div style="color: #999999; font-size: 10px;">Registrados en sistema</div>
                    </div>
                    <div style="flex: 1; background: white; border: 1px solid #dddddd; border-radius: 8px; padding: 15px;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Servicios Activos</div>
                        <div style="color: #28a745; font-size: 28px; font-weight: bold; margin: 5px 0;">${serviciosActivos}</div>
                        <div style="color: #999999; font-size: 10px;">${((serviciosActivos/totalServiciosGlobal)*100).toFixed(1)}% del total</div>
                    </div>
                    <div style="flex: 1; background: white; border: 1px solid #dddddd; border-radius: 8px; padding: 15px;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Total Ventas</div>
                        <div style="color: #000000; font-size: 28px; font-weight: bold; margin: 5px 0;">${totalVentasGlobal}</div>
                        <div style="color: #999999; font-size: 10px;">Operaciones realizadas</div>
                    </div>
                    <div style="flex: 1; background: white; border: 1px solid #dddddd; border-radius: 8px; padding: 15px;">
                        <div style="color: #666666; font-size: 11px; text-transform: uppercase;">Total Ingresos</div>
                        <div style="color: #28a745; font-size: 28px; font-weight: bold; margin: 5px 0;">$${formatoMoneda(totalIngresosGlobal)}</div>
                        <div style="color: #999999; font-size: 10px;">Moneda nacional (CUP)</div>
                    </div>
                </div>
                
                <!-- Tabla de servicios -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px; color: #000000;">
                    <thead>
                        <tr style="background-color: #0078d4; color: white;">`;
        
        // Encabezados según el contexto
        if (tablaDatos.tieneCategoria) {
            htmlContent += `
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Código</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Descripción</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Categoría</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Ventas</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Costo</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Ingresos</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Estado</th>`;
        } else {
            htmlContent += `
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Código</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Descripción</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Ventas</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Costo</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Ingresos</th>
                            <th style="padding: 10px; border: 1px solid #0056b3; text-align: center; color: white;">Estado</th>`;
        }
        
        htmlContent += `</tr>
                    </thead>
                    <tbody>`;
        
        // Datos según el contexto
        tablaDatos.datos.forEach((fila) => {
            htmlContent += `<tr style="border-bottom: 1px solid #dddddd;">`;
            
            if (tablaDatos.tieneCategoria) {
                // [codigo, descripcion, categoria, ventas, costo, ingresos, estado]
                htmlContent += `
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: center; color: #000000;">${fila[0]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; color: #000000;">${fila[1]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; color: #000000;">${fila[2]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: center; font-weight: bold; color: #000000;">${fila[3]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: right; color: #000000;">$${formatoMoneda(fila[4])}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: right; font-weight: bold; color: ${fila[5] > 0 ? '#28a745' : '#000000'};">$${formatoMoneda(fila[5])}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: center; color: #000000;">
                        <span style="color: ${fila[6].toLowerCase() === 'activo' ? '#28a745' : '#dc3545'}; font-weight: bold;">${fila[6]}</span>
                    </td>`;
            } else {
                // [codigo, descripcion, ventas, costo, ingresos, estado]
                htmlContent += `
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: center; color: #000000;">${fila[0]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; color: #000000;">${fila[1]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: center; font-weight: bold; color: #000000;">${fila[2]}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: right; color: #000000;">$${formatoMoneda(fila[3])}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: right; font-weight: bold; color: ${fila[4] > 0 ? '#28a745' : '#000000'};">$${formatoMoneda(fila[4])}</td>
                    <td style="padding: 8px; border: 1px solid #dddddd; text-align: center; color: #000000;">
                        <span style="color: ${fila[5].toLowerCase() === 'activo' ? '#28a745' : '#dc3545'}; font-weight: bold;">${fila[5]}</span>
                    </td>`;
            }
            
            htmlContent += `</tr>`;
        });
        
        htmlContent += `
                    </tbody>
                </table>
                
                <!-- Footer -->
                <div style="margin-top: 30px; padding-top: 15px; border-top: 2px solid #0078d4; text-align: center; font-size: 10px; color: #666666;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span><strong style="color: #0078d4;">SISFACT PDL Visiones</strong> • Sistema de Gestión Empresarial</span>
                        <span style="color: #000000;">Generado por: ${usuarioNombre}</span>
                    </div>
                    <div style="color: #999999;">
                        Este reporte es generado automáticamente por el sistema. Los datos reflejan el período operativo indicado.
                    </div>
                </div>
            </div>
        `;
        
        element.innerHTML = htmlContent;
        
        // Usar html2pdf con configuración simplificada
        html2pdf().from(element).set({
            margin: [0.5, 0.5, 0.5, 0.5],
            filename: `Servicios_${formatearFechaParaArchivo()}.pdf`,
            image: { type: 'jpeg', quality: 0.95 },
            html2canvas: { 
                scale: 2,
                logging: false,
                allowTaint: false,
                useCORS: true,
                backgroundColor: '#ffffff' // Fondo blanco forzado
            },
            jsPDF: { 
                unit: 'in', 
                format: 'letter', 
                orientation: 'landscape'
            }
        }).save().then(() => {
            Swal.close();
            Swal.fire({ 
                icon: 'success', 
                title: 'PDF Descargado', 
                text: 'El reporte se ha generado correctamente',
                timer: 2000, 
                showConfirmButton: false 
            });
        }).catch(error => {
            Swal.close();
            console.error('Error en html2pdf:', error);
            Swal.fire({ 
                icon: 'error', 
                title: 'Error', 
                text: 'No se pudo generar el PDF. Intente nuevamente.'
            });
        });
        
    } catch (error) {
        Swal.close();
        console.error('Error general:', error);
        Swal.fire({ 
            icon: 'error', 
            title: 'Error', 
            text: 'Ha ocurrido un error al generar el PDF.'
        });
    }
}
        // ============================================
        // EXPORTAR A EXCEL
        // ============================================
        function exportarExcel() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay servicios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();
                const periodoOperativo = obtenerPeriodoOperativo();

                const wb = XLSX.utils.book_new();
                
                let ws_data = [
                    ['REPORTE DE SERVICIOS - SISFACT PDL VISIONES'],
                    [`Fecha de Inicio de Operaciones: ${fechaOperacion}`],
                    [`Período Operativo: ${periodoOperativo}`],
                    [`Fecha Generación: ${new Date().toLocaleString('es-ES')}`],
                    [`Usuario: ${usuarioNombre}`],
                    [],
                    ['ESTADÍSTICAS GENERALES'],
                    ['Total Servicios', totalServiciosGlobal],
                    ['Servicios Activos', serviciosActivos],
                    ['Servicios Inactivos', serviciosInactivos],
                    ['Total Ventas', totalVentasGlobal],
                    ['Total Ingresos', `$${totalIngresosGlobal.toFixed(2)}`],
                    []
                ];
                
                // Encabezados según el contexto
                let headers = [];
                if (tablaDatos.tieneCategoria) {
                    headers = ['Código', 'Descripción', 'Categoría', 'Ventas', 'Costo', 'Ingresos', 'Estado'];
                } else {
                    headers = ['Código', 'Descripción', 'Ventas', 'Costo', 'Ingresos', 'Estado'];
                }
                ws_data.push(headers);
                
                // Datos
                tablaDatos.datos.forEach(fila => {
                    let filaProcesada = [];
                    fila.forEach((celda, index) => {
                        if (tablaDatos.tieneCategoria) {
                            // Convertir tipos según la columna
                            if (index === 3) filaProcesada.push(parseInt(celda)); // Ventas
                            else if (index === 4) filaProcesada.push(parseFloat(celda)); // Costo
                            else if (index === 5) filaProcesada.push(parseFloat(celda)); // Ingresos
                            else filaProcesada.push(celda);
                        } else {
                            if (index === 2) filaProcesada.push(parseInt(celda)); // Ventas
                            else if (index === 3) filaProcesada.push(parseFloat(celda)); // Costo
                            else if (index === 4) filaProcesada.push(parseFloat(celda)); // Ingresos
                            else filaProcesada.push(celda);
                        }
                    });
                    ws_data.push(filaProcesada);
                });
                
                const ws = XLSX.utils.aoa_to_sheet(ws_data);
                
                // Ajustar anchos de columnas
                if (tablaDatos.tieneCategoria) {
                    ws['!cols'] = [
                        { wch: 12 }, { wch: 40 }, { wch: 30 }, { wch: 10 }, 
                        { wch: 15 }, { wch: 15 }, { wch: 12 }
                    ];
                } else {
                    ws['!cols'] = [
                        { wch: 12 }, { wch: 40 }, { wch: 10 }, 
                        { wch: 15 }, { wch: 15 }, { wch: 12 }
                    ];
                }
                
                let nombreArchivo = 'Servicios';
                if (modoIndividual) nombreArchivo = 'Servicio_Individual';
                else if (categoriaFiltroId > 0) nombreArchivo = 'Servicios_Por_Categoria';
                
                XLSX.utils.book_append_sheet(wb, ws, 'Servicios');
                XLSX.writeFile(wb, `${nombreArchivo}_${formatearFechaParaArchivo()}.xlsx`);
                
                Swal.fire({ icon: 'success', title: 'Excel Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

// ============================================
// EXPORTAR A WORD - VERSIÓN MEJORADA (IGUAL AL PDF)
// ============================================
function exportarWord() {
    try {
        const tablaDatos = obtenerDatosTabla();
        if (tablaDatos.datos.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay servicios para exportar' });
            return;
        }

        const fechaOperacion = obtenerFechaOperacion();
        const periodoCierre = obtenerPeriodoCierre();
        
        // Determinar título según el contexto
        let tituloReporte = 'REPORTE DE SERVICIOS';
        let subtituloReporte = '';
        
        if (modoIndividual) {
            tituloReporte = 'DETALLE DE SERVICIO INDIVIDUAL';
        } else if (categoriaFiltroId > 0) {
            tituloReporte = 'SERVICIOS POR CATEGORÍA';
            const categoriaSelect = document.getElementById('filterCategoria');
            if (categoriaSelect && categoriaSelect.selectedOptions[0]) {
                subtituloReporte = categoriaSelect.selectedOptions[0].text;
            }
        }
        
        // Función para formatear moneda
        const formatoMoneda = (valor) => {
            return new Intl.NumberFormat('es-ES', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            }).format(valor);
        };
        
        let html = `
            <html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns:m="http://schemas.microsoft.com/office/2004/12/omml" xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta charset="UTF-8">
                <title>${tituloReporte}</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        margin: 0.75in;
                        background: white;
                        color: #000000;
                    }
                    .container {
                        max-width: 100%;
                        margin: 0 auto;
                    }
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        padding-bottom: 15px;
                        border-bottom: 3px solid #0078d4;
                    }
                    .logo-section {
                        display: flex;
                        align-items: center;
                    }
                    .logo {
                        width: 60px;
                        height: 60px;
                        background: #0078d4;
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin-right: 15px;
                    }
                    .logo span {
                        color: white;
                        font-size: 24px;
                        font-weight: bold;
                    }
                    .company-info h1 {
                        color: #0078d4;
                        margin: 0;
                        font-size: 24px;
                    }
                    .company-info p {
                        color: #333333;
                        margin: 5px 0 0 0;
                        font-size: 12px;
                    }
                    .report-title {
                        text-align: right;
                    }
                    .report-title h2 {
                        color: #000000;
                        margin: 0;
                        font-size: 20px;
                    }
                    .report-title .subtitle {
                        color: #0078d4;
                        margin: 5px 0 0 0;
                        font-weight: bold;
                    }
                    .info-section {
                        display: flex;
                        justify-content: space-between;
                        background: #f5f5f5;
                        padding: 15px;
                        border-radius: 8px;
                        margin-bottom: 20px;
                    }
                    .info-item {
                        text-align: center;
                        flex: 1;
                    }
                    .info-label {
                        color: #666666;
                        font-size: 11px;
                        text-transform: uppercase;
                    }
                    .info-value {
                        color: #000000;
                        font-size: 14px;
                        font-weight: bold;
                    }
                    .info-value.highlight {
                        color: #0078d4;
                    }
                    .stats-grid {
                        display: flex;
                        gap: 15px;
                        margin-bottom: 25px;
                    }
                    .stat-card {
                        flex: 1;
                        background: white;
                        border: 1px solid #dddddd;
                        border-radius: 8px;
                        padding: 15px;
                    }
                    .stat-title {
                        color: #666666;
                        font-size: 11px;
                        text-transform: uppercase;
                    }
                    .stat-number {
                        color: #000000;
                        font-size: 28px;
                        font-weight: bold;
                        margin: 5px 0;
                    }
                    .stat-number.green {
                        color: #28a745;
                    }
                    .stat-sub {
                        color: #999999;
                        font-size: 10px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 30px;
                        font-size: 11px;
                        color: #000000;
                    }
                    th {
                        background-color: #0078d4;
                        color: white;
                        padding: 10px;
                        border: 1px solid #0056b3;
                        text-align: center;
                        font-size: 11px;
                    }
                    td {
                        padding: 8px;
                        border: 1px solid #dddddd;
                        color: #000000;
                    }
                    tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    .text-center {
                        text-align: center;
                    }
                    .text-right {
                        text-align: right;
                    }
                    .text-bold {
                        font-weight: bold;
                    }
                    .text-success {
                        color: #28a745;
                        font-weight: bold;
                    }
                    .text-danger {
                        color: #dc3545;
                        font-weight: bold;
                    }
                    .footer {
                        margin-top: 30px;
                        padding-top: 15px;
                        border-top: 2px solid #0078d4;
                        text-align: center;
                        font-size: 10px;
                    }
                    .footer-content {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 5px;
                    }
                    .footer .company {
                        color: #0078d4;
                        font-weight: bold;
                    }
                    .footer .generated-by {
                        color: #000000;
                    }
                    .footer-note {
                        color: #999999;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <!-- Header -->
                    <div class="header">
                        <div class="logo-section">
                            <div class="logo">
                                <span>PDL</span>
                            </div>
                            <div class="company-info">
                                <h1>SISFACT PDL Visiones</h1>
                                <p>Sistema de Facturación y Gestión</p>
                            </div>
                        </div>
                        <div class="report-title">
                            <h2>${tituloReporte}</h2>
                            ${subtituloReporte ? `<div class="subtitle">${subtituloReporte}</div>` : ''}
                        </div>
                    </div>
                    
                    <!-- Información de fechas -->
                    <div class="info-section">
                        <div class="info-item">
                            <div class="info-label">Fecha Inicio Operaciones</div>
                            <div class="info-value highlight">${fechaOperacion}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Período de Cierre</div>
                            <div class="info-value">${periodoCierre}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha Generación</div>
                            <div class="info-value">${new Date().toLocaleDateString('es-ES')}</div>
                        </div>
                    </div>
                    
                    <!-- Tarjetas de estadísticas -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-title">Total Servicios</div>
                            <div class="stat-number">${totalServiciosGlobal}</div>
                            <div class="stat-sub">Registrados en sistema</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Servicios Activos</div>
                            <div class="stat-number green">${serviciosActivos}</div>
                            <div class="stat-sub">${((serviciosActivos/totalServiciosGlobal)*100).toFixed(1)}% del total</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Total Ventas</div>
                            <div class="stat-number">${totalVentasGlobal}</div>
                            <div class="stat-sub">Operaciones realizadas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Total Ingresos</div>
                            <div class="stat-number green">$${formatoMoneda(totalIngresosGlobal)}</div>
                            <div class="stat-sub">Moneda nacional (CUP)</div>
                        </div>
                    </div>
                    
                    <!-- Tabla de servicios -->
                    <table>
                        <thead>
                            <tr>`;
        
        // Encabezados según el contexto
        if (tablaDatos.tieneCategoria) {
            html += `
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Ventas</th>
                                <th>Costo</th>
                                <th>Ingresos</th>
                                <th>Estado</th>`;
        } else {
            html += `
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Ventas</th>
                                <th>Costo</th>
                                <th>Ingresos</th>
                                <th>Estado</th>`;
        }
        
        html += `</tr>
                        </thead>
                        <tbody>`;
        
        // Datos según el contexto
        tablaDatos.datos.forEach((fila) => {
            html += `<tr>`;
            
            if (tablaDatos.tieneCategoria) {
                // [codigo, descripcion, categoria, ventas, costo, ingresos, estado]
                const estadoClass = fila[6].toLowerCase() === 'activo' ? 'text-success' : 'text-danger';
                const ingresosClass = fila[5] > 0 ? 'text-success' : '';
                
                html += `
                    <td class="text-center">${fila[0]}</td>
                    <td>${fila[1]}</td>
                    <td>${fila[2]}</td>
                    <td class="text-center text-bold">${fila[3]}</td>
                    <td class="text-right">$${formatoMoneda(fila[4])}</td>
                    <td class="text-right text-bold ${ingresosClass}">$${formatoMoneda(fila[5])}</td>
                    <td class="text-center">
                        <span class="${estadoClass}">${fila[6]}</span>
                    </td>`;
            } else {
                // [codigo, descripcion, ventas, costo, ingresos, estado]
                const estadoClass = fila[5].toLowerCase() === 'activo' ? 'text-success' : 'text-danger';
                const ingresosClass = fila[4] > 0 ? 'text-success' : '';
                
                html += `
                    <td class="text-center">${fila[0]}</td>
                    <td>${fila[1]}</td>
                    <td class="text-center text-bold">${fila[2]}</td>
                    <td class="text-right">$${formatoMoneda(fila[3])}</td>
                    <td class="text-right text-bold ${ingresosClass}">$${formatoMoneda(fila[4])}</td>
                    <td class="text-center">
                        <span class="${estadoClass}">${fila[5]}</span>
                    </td>`;
            }
            
            html += `</tr>`;
        });
        
        html += `
                        </tbody>
                    </table>
                    
                    <!-- Footer -->
                    <div class="footer">
                        <div class="footer-content">
                            <div>
                                <span class="company">SISFACT PDL Visiones</span> • Sistema de Gestión Empresarial
                            </div>
                            <div class="generated-by">Generado por: ${usuarioNombre}</div>
                        </div>
                        <div class="footer-note">
                            Este reporte es generado automáticamente por el sistema. Los datos reflejan el período operativo indicado.
                        </div>
                    </div>
                </div>
            </body>
            </html>
        `;

        // Crear y descargar el archivo Word
        const blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        let nombreArchivo = 'Servicios';
        if (modoIndividual) nombreArchivo = 'Servicio_Individual';
        else if (categoriaFiltroId > 0) nombreArchivo = 'Servicios_Por_Categoria';
        
        a.href = url;
        a.download = `${nombreArchivo}_${formatearFechaParaArchivo()}.doc`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        Swal.fire({ 
            icon: 'success', 
            title: 'Word Exportado', 
            text: 'El documento se ha generado correctamente',
            timer: 1500, 
            showConfirmButton: false 
        });
        
    } catch (error) {
        console.error('Error al exportar Word:', error);
        Swal.fire({ 
            icon: 'error', 
            title: 'Error', 
            text: 'Ha ocurrido un error al generar el documento Word.'
        });
    }
}
        // ============================================
        // EXPORTAR A CSV
        // ============================================
        function exportarCSV() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay servicios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();
                const periodoOperativo = obtenerPeriodoOperativo();

                let csv = '';
                csv += 'REPORTE DE SERVICIOS - SISFACT PDL VISIONES\n';
                csv += `Fecha de Inicio de Operaciones,${fechaOperacion}\n`;
                csv += `Período Operativo,${periodoOperativo}\n`;
                csv += `Período de Cierre,${periodoCierre}\n`;
                csv += `Usuario,${usuarioNombre}\n`;
                csv += `Total Servicios,${totalServiciosGlobal}\n`;
                csv += `Servicios Activos,${serviciosActivos}\n`;
                csv += `Servicios Inactivos,${serviciosInactivos}\n`;
                csv += `Total Ventas,${totalVentasGlobal}\n`;
                csv += `Total Ingresos,$${totalIngresosGlobal.toFixed(2)}\n`;
                csv += '\n';
                
                // Encabezados según el contexto
                if (tablaDatos.tieneCategoria) {
                    csv += 'Código,Descripción,Categoría,Ventas,Costo,Ingresos,Estado\n';
                } else {
                    csv += 'Código,Descripción,Ventas,Costo,Ingresos,Estado\n';
                }
                
                tablaDatos.datos.forEach(fila => {
                    const filaEscapada = fila.map(celda => {
                        if (typeof celda === 'string' && (celda.includes(',') || celda.includes('"'))) {
                            return `"${celda.replace(/"/g, '""')}"`;
                        }
                        return celda;
                    });
                    csv += filaEscapada.join(',') + '\n';
                });

                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                
                let nombreArchivo = 'Servicios';
                if (modoIndividual) nombreArchivo = 'Servicio_Individual';
                else if (categoriaFiltroId > 0) nombreArchivo = 'Servicios_Por_Categoria';
                
                a.href = url;
                a.download = `${nombreArchivo}_${formatearFechaParaArchivo()}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'CSV Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

// ============================================
// EXPORTAR A TXT - VERSIÓN CORREGIDA
// ============================================
function exportarTXT() {
    try {
        const tablaDatos = obtenerDatosTabla();
        if (tablaDatos.datos.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay servicios para exportar' });
            return;
        }

        const fechaOperacion = obtenerFechaOperacion();
        const periodoCierre = obtenerPeriodoCierre();
        const periodoOperativo = obtenerPeriodoOperativo();
        
        let tituloReporte = 'REPORTE DE SERVICIOS';
        if (modoIndividual) {
            tituloReporte = 'DETALLE DE SERVICIO INDIVIDUAL';
        } else if (categoriaFiltroId > 0) {
            tituloReporte = 'SERVICIOS POR CATEGORÍA';
        }
        
        // Función para formatear moneda
        const formatoMoneda = (valor) => {
            return new Intl.NumberFormat('es-ES', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            }).format(valor);
        };
        
        let contenido = '';
        const lineaSep = '='.repeat(140) + '\n';
        const lineaSub = '-'.repeat(140) + '\n';
        
        // Encabezado principal
        contenido += lineaSep;
        contenido += `${tituloReporte} - SISFACT PDL VISIONES\n`.padStart(70, ' ').padEnd(140, ' ') + '\n';
        contenido += lineaSep;
        contenido += '\n';
        
        // Información de fechas
        contenido += 'FECHA DE INICIO OPERACIONES: '.padEnd(30) + `${fechaOperacion}\n`;
        contenido += 'PERÍODO OPERATIVO: '.padEnd(30) + `${periodoOperativo}\n`;
        contenido += 'FECHA DE GENERACIÓN: '.padEnd(30) + `${new Date().toLocaleDateString('es-ES')}\n`;
        contenido += 'USUARIO: '.padEnd(30) + `${usuarioNombre}\n`;
        contenido += '\n';
        
        // Estadísticas
        contenido += 'ESTADÍSTICAS GENERALES:\n';
        contenido += lineaSub;
        contenido += `  Total Servicios:`.padEnd(25) + `${totalServiciosGlobal}\n`;
        contenido += `  Servicios Activos:`.padEnd(25) + `${serviciosActivos} (${((serviciosActivos/totalServiciosGlobal)*100).toFixed(1)}% del total)\n`;
        contenido += `  Servicios Inactivos:`.padEnd(25) + `${totalServiciosGlobal - serviciosActivos}\n`;
        contenido += `  Total Ventas:`.padEnd(25) + `${totalVentasGlobal}\n`;
        contenido += `  Total Ingresos:`.padEnd(25) + `$${formatoMoneda(totalIngresosGlobal)}\n`;
        contenido += '\n';
        
        // Encabezados de tabla según el contexto
        if (tablaDatos.tieneCategoria) {
            contenido += 'CÓDIGO'.padEnd(15) + 
                        'DESCRIPCIÓN'.padEnd(45) + 
                        'CATEGORÍA'.padEnd(35) + 
                        'VENTAS'.padEnd(10) + 
                        'COSTO'.padEnd(18) + 
                        'INGRESOS'.padEnd(18) + 
                        'ESTADO\n';
            contenido += lineaSub;
            
            // Datos
            tablaDatos.datos.forEach(fila => {
                // Convertir todo a strings para usar padEnd
                const codigo = String(fila[0] || '');
                const descripcion = String(fila[1] || '').substring(0, 40);
                const categoria = String(fila[2] || '').substring(0, 30);
                const ventas = String(fila[3] || '0');
                const costo = `$${formatoMoneda(fila[4] || 0)}`;
                const ingresos = `$${formatoMoneda(fila[5] || 0)}`;
                const estado = String(fila[6] || '');
                
                contenido += codigo.padEnd(15) + 
                            descripcion.padEnd(45) + 
                            categoria.padEnd(35) + 
                            ventas.padEnd(10) + 
                            costo.padEnd(18) + 
                            ingresos.padEnd(18) + 
                            estado + '\n';
            });
        } else {
            contenido += 'CÓDIGO'.padEnd(15) + 
                        'DESCRIPCIÓN'.padEnd(50) + 
                        'VENTAS'.padEnd(10) + 
                        'COSTO'.padEnd(18) + 
                        'INGRESOS'.padEnd(18) + 
                        'ESTADO\n';
            contenido += lineaSub;
            
            // Datos
            tablaDatos.datos.forEach(fila => {
                // Convertir todo a strings para usar padEnd
                const codigo = String(fila[0] || '');
                const descripcion = String(fila[1] || '').substring(0, 45);
                const ventas = String(fila[2] || '0');
                const costo = `$${formatoMoneda(fila[3] || 0)}`;
                const ingresos = `$${formatoMoneda(fila[4] || 0)}`;
                const estado = String(fila[5] || '');
                
                contenido += codigo.padEnd(15) + 
                            descripcion.padEnd(50) + 
                            ventas.padEnd(10) + 
                            costo.padEnd(18) + 
                            ingresos.padEnd(18) + 
                            estado + '\n';
            });
        }
        
        contenido += '\n' + lineaSep;
        contenido += 'FIN DEL REPORTE\n';
        contenido += lineaSep;

        // Crear y descargar el archivo
        const blob = new Blob(['\ufeff' + contenido], { type: 'text/plain;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        let nombreArchivo = 'Servicios';
        if (modoIndividual) nombreArchivo = 'Servicio_Individual';
        else if (categoriaFiltroId > 0) nombreArchivo = 'Servicios_Por_Categoria';
        
        a.href = url;
        a.download = `${nombreArchivo}_${formatearFechaParaArchivo()}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        Swal.fire({ 
            icon: 'success', 
            title: 'TXT Exportado', 
            text: 'El archivo se ha generado correctamente',
            timer: 1500, 
            showConfirmButton: false 
        });

    } catch (error) {
        console.error('Error al exportar TXT:', error);
        Swal.fire({ 
            icon: 'error', 
            title: 'Error', 
            text: 'Ha ocurrido un error al generar el archivo TXT: ' + error.message
        });
    }
}
// ============================================
// IMPRIMIR TABLA - VERSIÓN HORIZONTAL IGUAL AL PDF
// ============================================
function imprimirTabla() {
    try {
        const tablaDatos = obtenerDatosTabla();
        if (tablaDatos.datos.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay servicios para imprimir' });
            return;
        }

        const fechaOperacion = obtenerFechaOperacion();
        const periodoCierre = obtenerPeriodoCierre();
        
        // Determinar título según el contexto
        let tituloReporte = 'REPORTE DE SERVICIOS';
        let subtituloReporte = '';
        
        if (modoIndividual) {
            tituloReporte = 'DETALLE DE SERVICIO INDIVIDUAL';
        } else if (categoriaFiltroId > 0) {
            tituloReporte = 'SERVICIOS POR CATEGORÍA';
            const categoriaSelect = document.getElementById('filterCategoria');
            if (categoriaSelect && categoriaSelect.selectedOptions[0]) {
                subtituloReporte = categoriaSelect.selectedOptions[0].text;
            }
        }
        
        // Función para formatear moneda
        const formatoMoneda = (valor) => {
            return new Intl.NumberFormat('es-ES', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            }).format(valor);
        };

        const printWindow = window.open('', '_blank', 'width=1400,height=900,menubar=1,scrollbars=1,resizable=1,toolbar=1,location=1,status=1');
        
        let html = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>${tituloReporte}</title>
                <style>
                    /* Reset y estilos base */
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body { 
                        font-family: 'Arial', 'Helvetica', sans-serif;
                        background: white;
                        color: #000000;
                        margin: 0;
                        padding: 20px;
                    }
                    .print-container {
                        max-width: 100%;
                        margin: 0 auto;
                    }
                    
                    /* Header */
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        padding-bottom: 15px;
                        border-bottom: 3px solid #0078d4;
                    }
                    .logo-section {
                        display: flex;
                        align-items: center;
                        gap: 15px;
                    }
                    .logo {
                        width: 60px;
                        height: 60px;
                        background: #0078d4;
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .logo span {
                        color: white;
                        font-size: 24px;
                        font-weight: bold;
                    }
                    .company-info h1 { 
                        color: #0078d4; 
                        margin: 0 0 5px 0;
                        font-size: 24px;
                        font-weight: 600;
                    }
                    .company-info p {
                        color: #333333;
                        font-size: 12px;
                        margin: 2px 0;
                    }
                    .report-title {
                        text-align: right;
                    }
                    .report-title h2 {
                        color: #000000;
                        font-size: 20px;
                        margin: 0 0 5px 0;
                        font-weight: 500;
                    }
                    .report-title .badge {
                        background: #0078d4;
                        color: white;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 12px;
                        display: inline-block;
                    }
                    
                    /* Información de fechas */
                    .info-section {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 8px;
                        margin-bottom: 20px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .info-item {
                        text-align: center;
                        flex: 1;
                    }
                    .info-label {
                        color: #666666;
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        margin-bottom: 5px;
                    }
                    .info-value {
                        color: #333333;
                        font-size: 14px;
                        font-weight: 600;
                    }
                    .info-value.highlight {
                        color: #0078d4;
                        font-size: 16px;
                    }
                    
                    /* Grid de estadísticas */
                    .stats-grid {
                        display: flex;
                        gap: 15px;
                        margin-bottom: 25px;
                    }
                    .stat-card {
                        flex: 1;
                        background: white;
                        border: 1px solid #e0e0e0;
                        border-radius: 8px;
                        padding: 15px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    }
                    .stat-title {
                        color: #666666;
                        font-size: 11px;
                        text-transform: uppercase;
                        margin-bottom: 8px;
                    }
                    .stat-number {
                        color: #333333;
                        font-size: 24px;
                        font-weight: 700;
                        margin-bottom: 5px;
                    }
                    .stat-number.green {
                        color: #28a745;
                    }
                    .stat-sub {
                        color: #999999;
                        font-size: 10px;
                    }
                    
                    /* Tabla */
                    .table-container {
                        margin: 20px 0 30px 0;
                        overflow-x: auto;
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        font-size: 11px;
                        color: #000000;
                    }
                    th { 
                        background-color: #0078d4; 
                        color: white; 
                        padding: 10px 8px; 
                        border: 1px solid #0056b3;
                        font-weight: 500;
                        text-align: center;
                        font-size: 11px;
                        white-space: nowrap;
                    }
                    td { 
                        padding: 8px 6px; 
                        border: 1px solid #dddddd; 
                        color: #000000;
                        vertical-align: middle;
                    }
                    tr:nth-child(even) {
                        background-color: #f8f9fa;
                    }
                    .text-center {
                        text-align: center;
                    }
                    .text-right {
                        text-align: right;
                    }
                    .text-bold {
                        font-weight: 600;
                    }
                    .text-success {
                        color: #28a745;
                        font-weight: 600;
                    }
                    .text-danger {
                        color: #dc3545;
                        font-weight: 600;
                    }
                    
                    /* Footer */
                    .footer {
                        margin-top: 30px;
                        padding-top: 15px;
                        border-top: 2px solid #0078d4;
                        text-align: center;
                        font-size: 10px;
                    }
                    .footer-content {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 5px;
                    }
                    .footer .company {
                        color: #0078d4;
                        font-weight: 600;
                    }
                    .footer .generated-by {
                        color: #000000;
                    }
                    .footer-note {
                        color: #999999;
                        font-size: 9px;
                    }
                    
                    /* Marca de agua para impresión */
                    .watermark {
                        position: fixed;
                        bottom: 50px;
                        right: 30px;
                        opacity: 0.05;
                        font-size: 80px;
                        color: #0078d4;
                        transform: rotate(-15deg);
                        pointer-events: none;
                        z-index: -1;
                    }
                    
                    /* Configuración de impresión horizontal */
                    @media print {
                        @page {
                            size: landscape;
                            margin: 0.5in;
                        }
                        body {
                            padding: 0;
                            background: white;
                        }
                        .print-container {
                            width: 100%;
                        }
                        .header, .info-section, .stats-grid, .footer {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        th {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                            background-color: #0078d4 !important;
                        }
                        .logo {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                            background: #0078d4 !important;
                        }
                        .badge {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                            background: #0078d4 !important;
                        }
                        .watermark {
                            opacity: 0.03;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <!-- Header -->
                    <div class="header">
                        <div class="logo-section">
                            <div class="logo">
                                <span>PDL</span>
                            </div>
                            <div class="company-info">
                                <h1>SISFACT PDL Visiones</h1>
                                <p>Sistema de Facturación y Gestión</p>
                                <p>Reportes Profesionales</p>
                            </div>
                        </div>
                        <div class="report-title">
                            <h2>${tituloReporte}</h2>
                            ${subtituloReporte ? `<div class="badge">${subtituloReporte}</div>` : ''}
                        </div>
                    </div>
                    
                    <!-- Información operativa -->
                    <div class="info-section">
                        <div class="info-item">
                            <div class="info-label">Fecha de Inicio Operaciones</div>
                            <div class="info-value highlight">${fechaOperacion}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Período de Cierre</div>
                            <div class="info-value">${periodoCierre}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha de Generación</div>
                            <div class="info-value">${new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                        </div>
                    </div>
                    
                    <!-- Estadísticas -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-title">Total Servicios</div>
                            <div class="stat-number">${totalServiciosGlobal}</div>
                            <div class="stat-sub">Registrados en sistema</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Servicios Activos</div>
                            <div class="stat-number green">${serviciosActivos}</div>
                            <div class="stat-sub">${((serviciosActivos/totalServiciosGlobal)*100).toFixed(1)}% del total</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Total Ventas</div>
                            <div class="stat-number">${totalVentasGlobal}</div>
                            <div class="stat-sub">Operaciones realizadas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Total Ingresos</div>
                            <div class="stat-number green">$${formatoMoneda(totalIngresosGlobal)}</div>
                            <div class="stat-sub">Moneda nacional (CUP)</div>
                        </div>
                    </div>
                    
                    <!-- Tabla -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>`;
        
        // Encabezados según el contexto
        if (tablaDatos.tieneCategoria) {
            html += `
                                    <th width="10%">Código</th>
                                    <th width="25%">Descripción</th>
                                    <th width="20%">Categoría</th>
                                    <th width="10%">Ventas</th>
                                    <th width="12%">Costo</th>
                                    <th width="13%">Ingresos</th>
                                    <th width="10%">Estado</th>`;
        } else {
            html += `
                                    <th width="12%">Código</th>
                                    <th width="35%">Descripción</th>
                                    <th width="10%">Ventas</th>
                                    <th width="15%">Costo</th>
                                    <th width="18%">Ingresos</th>
                                    <th width="10%">Estado</th>`;
        }
        
        html += `</tr>
                            </thead>
                            <tbody>`;
        
        // Datos según el contexto
        tablaDatos.datos.forEach((fila) => {
            html += `<tr>`;
            
            if (tablaDatos.tieneCategoria) {
                // [codigo, descripcion, categoria, ventas, costo, ingresos, estado]
                const estadoClass = fila[6].toLowerCase() === 'activo' ? 'text-success' : 'text-danger';
                const ingresosClass = fila[5] > 0 ? 'text-success' : '';
                
                html += `
                    <td class="text-center"><strong>${fila[0]}</strong></td>
                    <td>${fila[1]}</td>
                    <td>${fila[2]}</td>
                    <td class="text-center text-bold">${fila[3]}</td>
                    <td class="text-right">$${formatoMoneda(fila[4])}</td>
                    <td class="text-right text-bold ${ingresosClass}">$${formatoMoneda(fila[5])}</td>
                    <td class="text-center"><span class="${estadoClass}">${fila[6]}</span></td>`;
            } else {
                // [codigo, descripcion, ventas, costo, ingresos, estado]
                const estadoClass = fila[5].toLowerCase() === 'activo' ? 'text-success' : 'text-danger';
                const ingresosClass = fila[4] > 0 ? 'text-success' : '';
                
                html += `
                    <td class="text-center"><strong>${fila[0]}</strong></td>
                    <td>${fila[1]}</td>
                    <td class="text-center text-bold">${fila[2]}</td>
                    <td class="text-right">$${formatoMoneda(fila[3])}</td>
                    <td class="text-right text-bold ${ingresosClass}">$${formatoMoneda(fila[4])}</td>
                    <td class="text-center"><span class="${estadoClass}">${fila[5]}</span></td>`;
            }
            
            html += `</tr>`;
        });
        
        html += `
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Footer -->
                    <div class="footer">
                        <div class="footer-content">
                            <div>
                                <span class="company">SISFACT PDL Visiones</span> • Sistema de Gestión Empresarial
                            </div>
                            <div class="generated-by">Generado por: ${usuarioNombre}</div>
                        </div>
                        <div class="footer-note">
                            Este reporte es generado automáticamente por el sistema. Los datos reflejan el período operativo indicado.
                        </div>
                    </div>
                    
                    <!-- Marca de agua -->
                    <div class="watermark">PDL VISIONES</div>
                </div>
            </body>
            </html>
        `;

        printWindow.document.write(html);
        printWindow.document.close();
        
        // Esperar a que se carguen los estilos y abrir diálogo de impresión
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
            // No cerramos la ventana automáticamente para que el usuario pueda ver el resultado
        }, 500);

    } catch (error) {
        console.error('Error al imprimir:', error);
        Swal.fire({ 
            icon: 'error', 
            title: 'Error', 
            text: 'Ha ocurrido un error al preparar la impresión.'
        });
    }
}
        // ============================================
        // EXPORTAR SERVICIOS - TODOS LOS FORMATOS CON ICONOS
        // ============================================
        function exportarServicios() {
            Swal.fire({
                title: '📊 Exportar Servicios',
                html: `
                    <div style="text-align: left; margin-top: 15px;">
                        <p style="margin-bottom: 15px; color: var(--win-text-primary);">
                            <i class="fas fa-download me-2"></i>Seleccione el formato de exportación:
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <button id="btnExcel" class="btn btn-success" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-excel me-2"></i> Microsoft Excel
                            </button>
                            <button id="btnPDF" class="btn btn-danger" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-pdf me-2"></i> Adobe PDF
                            </button>
                            <button id="btnWord" class="btn btn-primary" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-word me-2"></i> Microsoft Word
                            </button>
                            <button id="btnCSV" class="btn btn-info" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-csv me-2"></i> Archivo CSV
                            </button>
                            <button id="btnTXT" class="btn btn-secondary" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-alt me-2"></i> Archivo TXT
                            </button>
                            <button id="btnPrint" class="btn btn-warning" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-print me-2"></i> Imprimir
                            </button>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-download me-2"></i>Exportar Todo',
                confirmButtonColor: '#0078d4',
                showCloseButton: true,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                },
                didOpen: () => {
                    // Asignar eventos a los botones personalizados
                    document.getElementById('btnExcel').addEventListener('click', () => {
                        Swal.close();
                        exportarExcel();
                    });
                    document.getElementById('btnPDF').addEventListener('click', () => {
                        Swal.close();
                        exportarPDF();
                    });
                    document.getElementById('btnWord').addEventListener('click', () => {
                        Swal.close();
                        exportarWord();
                    });
                    document.getElementById('btnCSV').addEventListener('click', () => {
                        Swal.close();
                        exportarCSV();
                    });
                    document.getElementById('btnTXT').addEventListener('click', () => {
                        Swal.close();
                        exportarTXT();
                    });
                    document.getElementById('btnPrint').addEventListener('click', () => {
                        Swal.close();
                        imprimirTabla();
                    });
                },
                preConfirm: () => {
                    // Exportar todos los formatos (opcional)
                    exportarExcel();
                    exportarPDF();
                    exportarWord();
                    exportarCSV();
                    exportarTXT();
                    setTimeout(() => imprimirTabla(), 1000);
                }
            });
        }

        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        let categoriaIdFiltro = <?php echo $categoria_id_filtro; ?>;
        
        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                // Para móvil
                sidebar.classList.toggle('open');
            } else {
                // Para desktop (toggle mini)
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                
                // Guardar preferencia
                guardarPreferencia('sidebar_mini', sidebarMini);
            }
        }
        
        // Funciones del panel de temas
        function abrirPanelTemas() {
            document.getElementById('themePanel').classList.add('open');
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            document.getElementById('themeOverlay').classList.add('open');
            themePanelOpen = true;
        }
        
        function cerrarPanelTemas() {
            document.getElementById('themePanel').classList.remove('open');
            document.getElementById('themeOverlay').classList.remove('open');
            themePanelOpen = false;
        }
        
        // Cambiar tema
        document.querySelectorAll('.win-theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-theme-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const theme = this.dataset.theme;
                document.documentElement.setAttribute('data-theme', theme);
            });
        });
        
        // Cambiar color de acento
        document.querySelectorAll('.win-color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-color-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const color = this.dataset.color;
                document.documentElement.style.setProperty('--win-accent', color);
                document.documentElement.style.setProperty('--win-accent-light', color + '20');
            });
        });
        
        // Guardar configuración
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
            
            // Enviar al servidor
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
        
        // Guardar preferencia individual
        function guardarPreferencia(clave, valor) {
            fetch('guardar_preferencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `${clave}=${valor}`
            });
        }
        
        function eliminarServicio(id) {
            <?php if ($es_admin): ?>
                Swal.fire({
                    title: '¿Está seguro?',
                    html: `
                        <div class="text-start">
                            <p>Esta acción eliminará permanentemente el servicio del sistema.</p>
                            <div class="alert alert-warning p-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Advertencia:</strong> Verifique que el servicio no esté asociado a facturas activas.
                            </div>
                            <p class="small text-dark mb-0">
                                <i class="fas fa-user-shield me-1"></i>
                                Acción realizada como: <span class="badge badge-admin">ADMINISTRADOR</span>
                            </p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'eliminar_servicio.php?id=' + id;
                    }
                });
            <?php else: ?>
                mostrarModalNoAdmin();
            <?php endif; ?>
        }
        
        // Actualizar lista de servicios
        function actualizarListaServicios() {
            const btn = document.querySelector('button[onclick="actualizarListaServicios()"]');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
                btn.disabled = true;
                
                // Simular actualización
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
        
        // Filtrar y buscar servicios (solo en modo lista)
        function filtrarServicios() {
            if (modoIndividual) return;
            
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterStatus = document.getElementById('filterStatus').value;
            const filterCategoria = document.getElementById('filterCategoria').value;
            const rows = document.querySelectorAll('#serviciosTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return;
                
                const text = row.textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                const categoria = row.getAttribute('data-categoria');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesStatus = filterStatus === '' || status === filterStatus;
                const matchesCategoria = filterCategoria === '' || categoria === filterCategoria;
                
                if (matchesSearch && matchesStatus && matchesCategoria) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Actualizar contador
            const counter = document.querySelector('.badge.bg-info');
            if (counter) {
                counter.textContent = visibleCount + ' servicios';
            }
        }
        
        // Cambiar categoría desde el filtro
        function cambiarFiltroCategoria() {
            const select = document.getElementById('filterCategoria');
            const categoriaId = select.value;
            
            if (categoriaId) {
                // Si hay un filtro de categoría seleccionado, redirigir con el parámetro
                window.location.href = 'servicios.php?categoria_id=' + categoriaId;
            } else if (categoriaIdFiltro > 0) {
                // Si ya hay un filtro activo y se selecciona "Todas las categorías", limpiar filtro
                window.location.href = 'servicios.php';
            }
        }
        
        // Cambiar registros por página
        function cambiarRegistrosPorPagina(registros) {
            const url = new URL(window.location.href);
            url.searchParams.set('registros', registros);
            url.searchParams.set('pagina', 1); // Volver a la primera página
            window.location.href = url.toString();
        }
        
        // Ir a página específica
        function irAPagina() {
            const input = document.getElementById('gotoPage');
            let pagina = parseInt(input.value);
            const totalPaginas = parseInt('<?php echo $total_paginas; ?>');
            
            if (isNaN(pagina) || pagina < 1) {
                pagina = 1;
            } else if (pagina > totalPaginas) {
                pagina = totalPaginas;
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }
        
        // Ir a página específica desde el campo superior
        function irAPaginaDesdeTop() {
            const input = document.getElementById('gotoPageTop');
            let pagina = parseInt(input.value);
            const totalPaginas = parseInt('<?php echo $total_paginas; ?>');
            
            if (isNaN(pagina) || pagina < 1) {
                pagina = 1;
            } else if (pagina > totalPaginas) {
                pagina = totalPaginas;
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }
        
        // Toggle Quick Actions
        function toggleQuickActions() {
            const expandedActions = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (!expandedActions) return;
            
            const isExpanded = expandedActions.classList.contains('show');
            
            if (isExpanded) {
                expandedActions.classList.remove('show');
                mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                mainButton.title = 'Mostrar acciones rápidas';
                mainButton.style.transform = 'rotate(0deg)';
            } else {
                expandedActions.classList.add('show');
                mainButton.innerHTML = '<i class="fas fa-times"></i>';
                mainButton.title = 'Ocultar acciones rápidas';
                mainButton.style.transform = 'rotate(45deg)';
            }
        }
        
        // Función para cambiar estado (Activar/Desactivar)
        function toggleEstadoServicio(id, estadoActual) {
            const accion = estadoActual ? 'desactivar' : 'activar';
            const nuevoEstado = estadoActual ? 0 : 1;
            const colorBtn = estadoActual ? '#ffc107' : '#28a745'; // Amarillo para desactivar, Verde para activar
            
            Swal.fire({
                title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} servicio?`,
                text: `El servicio cambiará a estado ${nuevoEstado ? 'ACTIVO' : 'INACTIVO'}.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: colorBtn,
                cancelButtonColor: '#d33',
                confirmButtonText: `<i class="fas fa-check me-2"></i>Sí, ${accion}`,
                cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Procesando...',
                        html: 'Actualizando estado del servicio',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Enviar petición al servidor
                    fetch('cambiar_estado_servicio.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `id=${id}&estado=${nuevoEstado}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Actualizado!',
                                text: 'El estado del servicio ha sido modificado correctamente.',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                // Recargar la página para ver cambios
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudo cambiar el estado.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'Hubo un problema al comunicarse con el servidor.'
                        });
                    });
                }
            });
        }
        
        // Función para mostrar modal de no administrador
        function mostrarModalNoAdmin() {
            const modal = new bootstrap.Modal(document.getElementById('modalNoAdmin'));
            modal.show();
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips
            if (typeof bootstrap === 'undefined') {
                console.error("Error: Bootstrap no está cargado. Verifique la ruta del archivo js/bootstrap5.3.0/bootstrap.bundle.min.js");
            } else {
                // Inicializar tooltips con configuración robusta
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        container: 'body',
                        trigger: 'hover focus',
                        placement: 'auto',
                        boundary: 'clippingParents',
                        html: true,
                        fallbackPlacements: ['bottom', 'right', 'left'],
                        delay: { "show": 100, "hide": 100 }
                    });
                });
            }
            
            // Configurar búsqueda y filtros solo si no es modo individual
            if (!modoIndividual) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', filtrarServicios);
                }
                
                const filterStatus = document.getElementById('filterStatus');
                if (filterStatus) {
                    filterStatus.addEventListener('change', filtrarServicios);
                }
                
                // Configurar cambio de categoría en el filtro
                const filterCategoria = document.getElementById('filterCategoria');
                if (filterCategoria) {
                    filterCategoria.addEventListener('change', cambiarFiltroCategoria);
                }
                
                // Pre-seleccionar la categoría en el filtro si hay un filtro activo
                if (categoriaIdFiltro > 0 && filterCategoria) {
                    filterCategoria.value = categoriaIdFiltro;
                }
                
                // Configurar campo "Ir a página" para permitir Enter
                const gotoInput = document.getElementById('gotoPage');
                if (gotoInput) {
                    gotoInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            irAPagina();
                        }
                    });
                }
                
                const gotoInputTop = document.getElementById('gotoPageTop');
                if (gotoInputTop) {
                    gotoInputTop.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            irAPaginaDesdeTop();
                        }
                    });
                }
                
                // Sincronizar selects de registros por página
                const selectTop = document.getElementById('registrosPorPaginaTop');
                const selectBottom = document.getElementById('registrosPorPagina');
                
                if (selectTop && selectBottom) {
                    selectTop.addEventListener('change', function() {
                        selectBottom.value = this.value;
                        cambiarRegistrosPorPagina(this.value);
                    });
                    
                    selectBottom.addEventListener('change', function() {
                        selectTop.value = this.value;
                        cambiarRegistrosPorPagina(this.value);
                    });
                }
            }
            
            // Cerrar panel de temas
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            // Cerrar panel de temas con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && themePanelOpen) {
                    cerrarPanelTemas();
                }
            });
            
            // Manejar cambios de tamaño de ventana
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('open');
                }
            });
            
            // Cerrar Quick Actions al hacer clic fuera
            document.addEventListener('click', function(event) {
                const quickActions = document.querySelector('.win-quick-actions');
                const expandedActions = document.getElementById('quickActionsExpanded');
                const mainButton = document.getElementById('mainQuickAction');
                
                if (!quickActions || !expandedActions) return;
                
                const isClickInside = quickActions.contains(event.target);
                const isExpanded = expandedActions.classList.contains('show');
                
                if (!isClickInside && isExpanded) {
                    expandedActions.classList.remove('show');
                    if (mainButton) {
                        mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                        mainButton.style.transform = 'rotate(0deg)';
                    }
                }
            });
        });
        
        // Mostrar SweetAlert si hay mensaje de no privilegios
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['swal_no_privilegios'])): 
                $swal = $_SESSION['swal_no_privilegios'];
                unset($_SESSION['swal_no_privilegios']);
            ?>
            
            Swal.fire({
                title: '<?php echo $swal['titulo']; ?>',
                html: `
                    <div class="text-start">
                        <p><?php echo $swal['mensaje']; ?></p>
                        <div class="alert alert-danger p-3 mt-2">
                            <i class="fas fa-user-tag me-2"></i>
                            <strong>Tipo de Usuario Actual:</strong> 
                            <?php
                            // Determinar clase del badge según rol_id
                            switch($swal['rol_id']) {
                                case 1: $badgeClass = 'badge-admin'; $icon = 'user-shield'; break;
                                case 2: $badgeClass = 'badge-secondary'; $icon = 'eye'; break;
                                case 3: $badgeClass = 'badge-editor'; $icon = 'edit'; break;
                                case 4: $badgeClass = 'badge-supervisor'; $icon = 'code'; break;
                                case 5: $badgeClass = 'badge-programador'; $icon = 'code'; break;
                                default: $badgeClass = 'badge-secondary'; $icon = 'user'; break;
                            }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                <?php echo $swal['tipo_usuario']; ?>
                            </span>
                        </div>
                        <div class="alert alert-info p-2 mt-2">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                <strong>Privilegios requeridos:</strong> Administrador
                            </small>
                            <br>
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                <strong>Su privilegio actual:</strong> ROL = <?php echo $swal['rol_id']; ?>
                            </small>
                        </div>
                        <p class="mb-0 small mt-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Contacte al administrador del sistema si necesita acceder a esta función.
                        </p>
                    </div>
                `,
                icon: '<?php echo $swal['icono']; ?>',
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                backdrop: 'rgba(0,0,0,0.8)',
                allowOutsideClick: false,
                allowEscapeKey: false
            });
            <?php endif; ?>
        });
    </script>
    
    <!-- Modal para no administradores -->
    <div class="modal fade" id="modalNoAdmin" tabindex="-1" aria-labelledby="modalNoAdminLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background-color: var(--win-bg-secondary);">
                <div class="modal-header border-bottom" style="border-color: var(--win-border-color);">
                    <h5 class="modal-title" style="color: var(--win-text-primary);">
                        <i class="fas fa-user-shield me-2 text-danger"></i>Acceso Restringido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="me-3">
                            <div class="bg-danger text-white rounded-circle p-3">
                                <i class="fas fa-ban fa-2x"></i>
                            </div>
                        </div>
                        <div>
                            <h6 style="color: var(--win-text-primary);">Privilegios Insuficientes</h6>
                            <p class="text-muted mb-0">
                                Solo los administradores pueden eliminar y activar/desactivar servicios del sistema.
                            </p>
                        </div>
                    </div>
                    
                    <div class="alert alert-secondary mt-3 mb-0">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle me-3 fs-4"></i>
                            <div>
                                <small class="fw-bold d-block">Usuario Actual</small>
                                <div class="d-flex align-items-center mt-1">
                                    <span class="badge <?php echo $es_admin ? 'badge-admin' : 'badge-user'; ?> me-2">
                                        <?php echo $es_admin ? 'ADMINISTRADOR' : htmlspecialchars($usuario['rol_nombre'] ?? 'USUARIO'); ?>
                                    </span>
                                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top" style="border-color: var(--win-border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-tachometer-alt me-1"></i>Volver al Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
    ?>
</body>
</html>

<?php
// Función para generar URL de paginación
function generarUrlPaginacion($pagina) {
    global $categoria_id_filtro, $registros_por_pagina, $servicio_id_individual, $modo_individual;
    
    $params = [];
    
    if ($modo_individual && $servicio_id_individual > 0) {
        $params['id'] = $servicio_id_individual;
    } else if ($categoria_id_filtro > 0) {
        $params['categoria_id'] = $categoria_id_filtro;
    }
    
    $params['pagina'] = $pagina;
    $params['registros'] = $registros_por_pagina;
    
    return 'servicios.php?' . http_build_query($params);
}
?>