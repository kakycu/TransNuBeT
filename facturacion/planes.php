<?php
session_start();
require_once 'config/database.php';
require_once __DIR__ . '/config/header.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// ==========================================
// 1. VALIDACIÓN DE PERMISOS (Rol)
// ==========================================
try {
    $db = Database::getConnection();
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) throw new Exception("Usuario no encontrado");
    
    // Roles permitidos: Admin, Super, Soft (Programador)
    $roles_permitidos = ['Admin', 'Super', 'Soft'];
    
    if (!in_array($usuario['rol_codigo'], $roles_permitidos)) {
        // Guardar mensaje para SweetAlert en sesión
        $_SESSION['swal_no_privilegios'] = [
            'titulo' => 'Acceso Restringido',
            'mensaje' => 'Solo usuarios con roles de <strong>Administrador, Supervisor o Programador</strong> pueden editar los planes de ingresos.',
            'tipo_usuario' => $usuario['rol_nombre'] ?? 'Usuario',
            'icono' => 'error',
            'rol_id' => $usuario['rol_id'] ?? 0
        ];
        
        // Redirigir a dashboard.php donde se mostrará el SweetAlert
        header('Location: dashboard.php');
        exit();
    }
    
} catch (Exception $e) {
    header('Location: index.php');
    exit();
}

// ==========================================
// 2. CONFIGURACIÓN DEL TEMA WINDOWS 11
// ==========================================
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

// ==========================================
// 3. OBTENER ESTADÍSTICAS PARA EL SIDEBAR
// ==========================================
try {
    // Obtener estadísticas
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
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener datos para el histórico en el sidebar
    $sql_total_historico = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt_historico = $db->query($sql_total_historico);
    $total_historico = $stmt_historico->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    $total_facturas = $total_clientes = $total_categorias = $total_servicios = $total_usuarios = $total_historico = 0;
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
// ==========================================
// 4. PROCESAR GUARDADO (POST)
// ==========================================
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'guardar_planes') {
            try {
                $anio_seleccionado = intval($_POST['anio']);
                $planes_post = $_POST['planes']; // Array [mes => importe]

                $db->beginTransaction();

                foreach ($planes_post as $mes => $importe) {
                    $mes = intval($mes);
                    // Limpiar importe (quitar $ y ,)
                    $importe = str_replace(['$', ','], '', $importe);
                    $importe = floatval($importe);
                    
                    $observaciones = $_POST['obs'][$mes] ?? '';

                    // Verificar si existe el registro
                    $sql_check = "SELECT 1 FROM tbl_planes WHERE anio = ? AND mes_plan = ?";
                    $stmt_check = $db->prepare($sql_check);
                    $stmt_check->execute([$anio_seleccionado, $mes]);

                    if ($stmt_check->fetch()) {
                        // Actualizar
                        $sql_update = "UPDATE tbl_planes SET importe = ?, observaciones = ? WHERE anio = ? AND mes_plan = ?";
                        $stmt_update = $db->prepare($sql_update);
                        $stmt_update->execute([$importe, $observaciones, $anio_seleccionado, $mes]);
                    } else {
                        // Insertar
                        $sql_insert = "INSERT INTO tbl_planes (anio, mes_plan, importe, activo, observaciones) VALUES (?, ?, ?, 1, ?)";
                        $stmt_insert = $db->prepare($sql_insert);
                        $stmt_insert->execute([$anio_seleccionado, $mes, $importe, $observaciones]);
                    }
                }
                
                // Registrar en histórico
                $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) VALUES (?, ?, ?, ?, ?)";
                $stmt_log = $db->prepare($sql_log);
                $stmt_log->execute([
                    'ACTUALIZAR_PLANES',
                    "Se actualizaron los planes del año $anio_seleccionado",
                    $_SESSION['usuario_id'],
                    $_SESSION['usuario_nombre'],
                    $_SERVER['REMOTE_ADDR']
                ]);

                $db->commit();
                $mensaje = "Planes actualizados correctamente.";
                $tipo_mensaje = "success";

            } catch (Exception $e) {
                $db->rollBack();
                $mensaje = "Error al guardar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
        elseif ($_POST['action'] === 'nuevo_anio') {
            try {
                $nuevo_anio = intval($_POST['nuevo_anio']);
                
                // Verificar si el año ya existe
                $sql_check = "SELECT 1 FROM tbl_planes WHERE anio = ? LIMIT 1";
                $stmt_check = $db->prepare($sql_check);
                $stmt_check->execute([$nuevo_anio]);
                
                if ($stmt_check->fetch()) {
                    $mensaje = "El año $nuevo_anio ya existe en el sistema.";
                    $tipo_mensaje = "error";
                } else {
                    // Crear año base con valores en 0
                    $db->beginTransaction();
                    for ($mes = 1; $mes <= 12; $mes++) {
                        $sql_insert = "INSERT INTO tbl_planes (anio, mes_plan, importe, activo, observaciones) VALUES (?, ?, 0.00, 1, '')";
                        $stmt_insert = $db->prepare($sql_insert);
                        $stmt_insert->execute([$nuevo_anio, $mes]);
                    }
                    
                    // Registrar en histórico
                    $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) VALUES (?, ?, ?, ?, ?)";
                    $stmt_log = $db->prepare($sql_log);
                    $stmt_log->execute([
                        'CREAR_PLAN_AÑO',
                        "Se creó nuevo año de planificación: $nuevo_anio",
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nombre'],
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $db->commit();
                    $mensaje = "Año $nuevo_anio creado exitosamente.";
                    $tipo_mensaje = "success";
                    
                    // Redirigir al nuevo año
                    header("Location: planes.php?anio=$nuevo_anio");
                    exit();
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $mensaje = "Error al crear nuevo año: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } elseif ($_POST['action'] === 'eliminar_anio') {
            try {
                $anio_eliminar = intval($_POST['anio_eliminar']);
                
                // Evitar eliminar el año actual por seguridad (opcional, pero recomendado)
                if ($anio_eliminar == date('Y')) {
                    throw new Exception("No se puede eliminar el año actual en curso.");
                }

                $db->beginTransaction();
                
                // Eliminar registros
                $sql_del = "DELETE FROM tbl_planes WHERE anio = ?";
                $stmt_del = $db->prepare($sql_del);
                $stmt_del->execute([$anio_eliminar]);
                
                // Registrar en histórico
                $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) VALUES (?, ?, ?, ?, ?)";
                $stmt_log = $db->prepare($sql_log);
                $stmt_log->execute([
                    'ELIMINAR_PLAN_AÑO',
                    "Se eliminaron los planes del año: $anio_eliminar",
                    $_SESSION['usuario_id'],
                    $_SESSION['usuario_nombre'],
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $db->commit();
                
                // Redirigir al año actual para evitar errores visuales
                header("Location: planes.php?anio=" . $anio_actual . "&msg=deleted");
                exit();

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $mensaje = "Error al eliminar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }

		
    }
}


// ==========================================
// 5. OBTENER DATOS (GET)
// ==========================================
$anio_filtro = isset($_GET['anio']) ? intval($_GET['anio']) : $anio_actual;

// Obtener lista de años disponibles
$sql_anios = "SELECT DISTINCT anio FROM tbl_planes ORDER BY anio DESC";
$stmt_anios = $db->query($sql_anios);
$anios_disponibles = $stmt_anios->fetchAll(PDO::FETCH_COLUMN);

// Si no hay años disponibles, crear el año actual
if (empty($anios_disponibles)) {
    try {
        $db->beginTransaction();
        for ($mes = 1; $mes <= 12; $mes++) {
            $sql_insert = "INSERT INTO tbl_planes (anio, mes_plan, importe, activo, observaciones) VALUES (?, ?, 0.00, 1, '')";
            $stmt_insert = $db->prepare($sql_insert);
            $stmt_insert->execute([$anio_actual, $mes]);
        }
        $db->commit();
        $anios_disponibles[] = $anio_actual;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}

// Obtener planes del año seleccionado
$planes = [];
// Inicializar array vacío para los 12 meses
for ($i = 1; $i <= 12; $i++) {
    $planes[$i] = ['importe' => 0.00, 'observaciones' => ''];
}

$sql_planes = "SELECT * FROM tbl_planes WHERE anio = ? ORDER BY mes_plan ASC";
$stmt_planes = $db->prepare($sql_planes);
$stmt_planes->execute([$anio_filtro]);
while ($row = $stmt_planes->fetch(PDO::FETCH_ASSOC)) {
    $planes[$row['mes_plan']] = $row;
}

// Calcular total anual
$total_anual = 0;
foreach ($planes as $p) {
    $total_anual += floatval($p['importe']);
}

// Obtener total facturado del año para comparación
$total_facturado_anio = 0;
try {
    $sql_facturado = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact WHERE YEAR(`fecha_emision`) = ? AND estado IN ('PAGADA', 'CONTABILIZADA', 'CERRADA') ";
    $stmt_facturado = $db->prepare($sql_facturado);
    $stmt_facturado->execute([$anio_filtro]);
    $total_facturado_anio = $stmt_facturado->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $total_facturado_anio = 0;
}

// Calcular porcentaje de cumplimiento
$porcentaje_cumplimiento = $total_anual > 0 ? ($total_facturado_anio / $total_anual) * 100 : 0;

// ==========================================
// CÁLCULOS FALTANTES PARA LA TARJETA ANUAL
// ==========================================
$faltante_anual = $total_anual - $total_facturado_anio;

// Definir texto y color según si falta o sobra dinero
if ($faltante_anual > 0) {
    $texto_faltante_anual = 'Faltante:';
    $clase_faltante_anual = 'text-warning'; // Amarillo
} else {
    $texto_faltante_anual = 'Excedente:';
    $clase_faltante_anual = 'text-success'; // Verde
}

// Definir color del porcentaje anual
if ($porcentaje_cumplimiento >= 100) {
    $color_perc_anual = 'text-success';
} elseif ($porcentaje_cumplimiento >= 50) {
    $color_perc_anual = 'text-warning';
} else {
    $color_perc_anual = 'text-danger';
}

// Meses
$meses_nombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Obtener mes actual en español
$mes_actual_es = $meses_nombres[$mes_actual];
$avatar_placeholder = strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1));

// Datos del MES ACTUAL
$mes_en_curso_index = $mes_actual; 
$nombre_mes_en_curso = $meses_nombres[$mes_en_curso_index];

// Usamos tu función de base de datos para obtener lo real vs meta del mes
$datos_mes_actual = Database::getProgresoFinanciero($mes_en_curso_index, $anio_filtro);

$meta_mes_actual = $datos_mes_actual['meta'];
$facturado_mes_actual = $datos_mes_actual['real'];
$faltante_mes_actual = $meta_mes_actual - $facturado_mes_actual;

// Determinar color y texto si ya se cumplió la meta
$clase_faltante_mes = ($faltante_mes_actual > 0) ? 'text-warning' : 'text-success';
$texto_faltante_mes = ($faltante_mes_actual > 0) ? 'Faltante:' : 'Excedente:';
// Si es excedente, mostramos el número en positivo para que se entienda mejor
$monto_mostrar_mes = abs($faltante_mes_actual);

$porcentaje_mes = $datos_mes_actual['porcentaje'];

// Determinar color del porcentaje
if ($porcentaje_mes >= 100) {
    $color_porcentaje = 'text-success'; // Verde
} elseif ($porcentaje_mes >= 50) {
    $color_porcentaje = 'text-warning'; // Amarillo
} else {
    $color_porcentaje = 'text-danger';  // Rojo
}

// Obtener datos de progreso financiero
$finanzas = Database::getProgresoFinanciero();

// Estadísticas para el sidebar
$estadisticas = [];
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contar planes configurados para el año actual
$sql_planes_configurados = "SELECT COUNT(*) as total FROM tbl_planes WHERE anio = ? AND importe > 0";
$stmt_planes_config = $db->prepare($sql_planes_configurados);
$stmt_planes_config->execute([$anio_filtro]);
$planes_configurados = $stmt_planes_config->fetch(PDO::FETCH_ASSOC)['total'];

// Obtener estadísticas de años
$sql_estadisticas_anios = "SELECT 
    COUNT(DISTINCT anio) as total_anios,
    MIN(anio) as primer_anio,
    MAX(anio) as ultimo_anio,
    AVG(importe) as promedio_anual
    FROM tbl_planes";
$stmt_estadisticas = $db->query($sql_estadisticas_anios);
$estadisticas_anios = $stmt_estadisticas->fetch(PDO::FETCH_ASSOC);

// ==========================================
// FUNCIÓN AUXILIAR PARA OBTENER PROGRESO REAL
// ==========================================
function obtenerProgresoMensual($db, $mes, $anio) {
    try {
        // Obtener total facturado del mes
        $sql = "SELECT 
                    COALESCE(SUM(total_general), 0) as total,
                    COUNT(*) as cantidad
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) = :anio 
                  AND MONTH(fecha_emision) = :mes
                  AND estado IN ('PAGADA', 'CONTABILIZADA', 'CERRADA', 'PENDIENTE')";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio' => $anio, 'mes' => $mes]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $real = floatval($data['total']);
        $cantidad = intval($data['cantidad']);
        
        // Obtener meta del plan
        $sql_meta = "SELECT importe FROM tbl_planes 
                     WHERE anio = :anio AND mes_plan = :mes AND activo = 1";
        $stmt_meta = $db->prepare($sql_meta);
        $stmt_meta->execute(['anio' => $anio, 'mes' => $mes]);
        $meta = floatval($stmt_meta->fetchColumn() ?: 0);
        
        // Calcular porcentaje
        $porcentaje = $meta > 0 ? ($real / $meta) * 100 : 0;
        
        return [
            'real' => $real,
            'cantidad' => $cantidad,
            'meta' => $meta,
            'porcentaje' => $porcentaje,
            'color' => $porcentaje >= 100 ? '#28a745' : ($porcentaje >= 50 ? '#ffc107' : '#dc3545')
        ];
        
    } catch (Exception $e) {
        error_log("Error en obtenerProgresoMensual: " . $e->getMessage());
        return [
            'real' => 0,
            'cantidad' => 0,
            'meta' => 0,
            'porcentaje' => 0,
            'color' => '#6c757d'
        ];
    }
}


?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Ingresos - PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Windows 11 Styles -->
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

        [data-theme="light"] {
            --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            border-bottom: 1px solid var(--win-border-color);
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
            border-color: var(--win-border-color);
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

        .win-main-content .text-muted {
            color: var(--win-text-secondary) !important;
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

        .win-quick-actions-expanded .action-reporte {
            background: #0078d4;
        }

        .win-quick-actions-expanded .action-copiar {
            background: #107c10;
        }

        .win-quick-actions-expanded .action-anio {
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

        /* Mejorar visibilidad del texto de ayuda */
        .win-main-content .form-text {
            color: var(--win-text-secondary) !important;
            opacity: 0.9 !important;
            font-size: 0.85rem !important;
            margin-top: 0.25rem !important;
        }

        /* Para el tema oscuro, aumentar el contraste */
        [data-theme="dark"] .win-main-content .form-text {
            color: #a0a0a0 !important;
            opacity: 1 !important;
        }

        /* Hacer más visible el texto cuando el campo está enfocado */
        .form-control:focus + .form-text {
            color: var(--win-accent) !important;
            opacity: 1 !important;
            font-weight: 500;
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

        /* Ajuste específico para los placeholders de los inputs para que también se vean */
        ::placeholder {
            color: #cccccc !important;
            opacity: 0.7 !important;
        }
.stat-card.primary {
            border-left-color: #0d6efd;
        }
        .stat-card .text-primary {
            color: #0d6efd !important;
        }
/* Botón de exportación */
.btn-exportar {
    position: relative;
    overflow: hidden;
}

.btn-exportar:hover .export-dropdown {
    display: block;
}

.export-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 180px;
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius-sm);
    box-shadow: var(--win-shadow);
    z-index: 1000;
    padding: 8px 0;
}

.export-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    color: var(--win-text-primary);
    text-decoration: none;
    transition: var(--win-transition);
}

.export-dropdown a:hover {
    background: var(--win-accent-light);
    color: var(--win-accent);
}

.export-dropdown a i {
    width: 20px;
    text-align: center;
}

.export-dropdown .divider {
    height: 1px;
    background: var(--win-border-color);
    margin: 4px 0;
}
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
            <span style="color: var(--win-text-primary);">PLANES - SISFACT PDL Visiones</span>
        </div>
        
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
                <a href="servicios.php" class="win-nav-link">
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
            
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">CONFIGURACIÓN</small></li>
            
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
                    <span class="win-nav-badge"><?php echo $anio_filtro; ?></span>
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
        
        <!-- Alertas PHP -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo $tipo_mensaje == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-chart-line me-2" style="color: var(--win-accent);"></i>Plan de Ingresos
                </h1>
                <p class="text-muted mb-0">Gestión de Planes Mensuales y Presupuestos - Año de Operaciones Seleccionado: <span class="text-warning fw-bold"> <?php echo $anio_filtro; ?></span></p>
            </div>
            
            <!-- Selector de Año -->
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="dropdown me-2">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar-alt me-1"></i> Año: <strong><?php echo $anio_filtro; ?></strong>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="background: var(--win-bg-secondary); border-color: var(--win-border-color);">
                        <?php foreach ($anios_disponibles as $anio): ?>
                        <li>
                            <a class="dropdown-item <?php echo $anio == $anio_filtro ? 'active' : ''; ?>" 
                               href="?anio=<?php echo $anio; ?>"
                               style="color: var(--win-text-primary);">
                               <?php echo $anio; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-success" href="#" onclick="crearNuevoAnio()">
                                <i class="fas fa-plus-circle me-1"></i> Crear nuevo año
                            </a>
                        </li>
<!-- En el dropdown del selector de año, después del botón "Crear nuevo año" -->
<li><hr class="dropdown-divider"></li>
<li>
    <a class="dropdown-item text-info" href="#" onclick="mostrarExportarModal()">
        <i class="fas fa-download me-1"></i> Exportar Planes
    </a>
</li>
                    </ul>
                </div>
                
<div class="btn-group">
    <button type="button" class="btn btn-sm btn-primary" onclick="guardarCambios()">
        <i class="fas fa-save me-1"></i> Guardar Todo
    </button>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
        <i class="fas fa-sync-alt me-1"></i>
    </button>
</div>

<!-- Botón de exportación destacado -->
<button type="button" class="btn btn-sm btn-success ms-2" onclick="mostrarExportarModal()">
    <i class="fas fa-file-export me-1"></i> Exportar
</button>
            </div>
        </div>
<!-- Barra de Herramientas de Exportación -->
<div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded" 
     style="background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color);">
    <div>
        <h6 class="mb-0" style="color: var(--win-text-primary);">
            <i class="fas fa-download me-2"></i>Opciones de Exportación
        </h6>
        <small class="text-muted">Exporte los planes del año <?php echo $anio_filtro; ?> en múltiples formatos</small>
    </div>
    <button type="button" class="btn btn-outline-danger" onclick="imprimirPlanes()" title="Imprimir Plan">
    <i class="fas fa-print me-1"></i> Imprimir
</button>
    <div class="btn-group">
        <button type="button" class="btn btn-outline-success" onclick="exportarRapido('excel')">
            <i class="fas fa-file-excel me-1"></i> Excel
        </button>
        <button type="button" class="btn btn-outline-danger" onclick="exportarRapido('pdf')">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </button>
        <button type="button" class="btn btn-outline-primary" onclick="exportarRapido('word')">
            <i class="fas fa-file-word me-1"></i> Word
        </button>
        <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-ellipsis-h"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" onclick="exportarRapido('csv')"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
            <li><a class="dropdown-item" href="#" onclick="exportarRapido('xml')"><i class="fas fa-code me-2"></i>XML</a></li>
            <li><a class="dropdown-item" href="#" onclick="exportarRapido('json')"><i class="fas fa-file-code me-2"></i>JSON</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-success" href="#" onclick="mostrarExportarModal()"><i class="fas fa-sliders-h me-2"></i>Todos los formatos</a></li>
        </ul>
    </div>
</div>
        <!-- Estadísticas -->
        <div class="row mb-4">
<!-- Tarjeta Combinada: Plan Anual y Cumplimiento -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card success h-100 animate__animated animate__fadeIn" style="animation-delay: 0.1s; border-left-color: #198754;">
                    <div class="card-body">
                        <!-- Cabecera -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                    Plan Anual <?php echo $anio_filtro; ?>
                                </div>
                                <div class="h5 mb-0 fw-bold text-success">
                                    $<?php echo number_format($total_anual, 2); ?>
                                </div>
                                <div class="text-success small" style="opacity: 0.8;">
                                    <i class="fas fa-flag-checkered me-1"></i>Plan Global
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-chart-pie fa-2x text-warning opacity-50"></i>
                            </div>
                        </div>

                        <!-- Detalles -->
                        <div class="mt-3">
                            <!-- Facturado -->
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Facturado:</small>
                                <small class="fw-bold" style="color: var(--win-text-primary);">
                                    $<?php echo number_format($total_facturado_anio, 2); ?>
                                </small>
                            </div>
                            
                            <!-- Faltante/Excedente -->
                            <div class="d-flex justify-content-between mb-2">
                                <small class="text-muted"><?php echo $texto_faltante_anual; ?></small>
                                <small class="fw-bold <?php echo $clase_faltante_anual; ?>">
                                    $<?php echo number_format(abs($faltante_anual), 2); ?>
                                </small>
                            </div>

                            <!-- Barra de Progreso Integrada -->
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Progreso:</small>
                                <small class="fw-bold <?php echo $color_perc_anual; ?>">
                                    <?php echo number_format($porcentaje_cumplimiento, 1); ?>%
                                </small>
                            </div>
                            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary);">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo min($porcentaje_cumplimiento, 100); ?>%"
                                     aria-valuenow="<?php echo $porcentaje_cumplimiento; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<!-- Tarjeta Plan Mensual + Cumplimiento -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card primary h-100 animate__animated animate__fadeIn" style="animation-delay: 0.15s; border-left-color: #0d6efd;">
                    <div class="card-body">
                        <!-- Cabecera: Título y Meta -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                    Plan <?php echo $nombre_mes_en_curso; ?>
                                </div>
                                <div class="h5 mb-0 fw-bold text-primary">
                                    $<?php echo number_format($meta_mes_actual, 2); ?>
                                </div>
                                <div class="text-primary small" style="opacity: 0.8;">
                                    <i class="fas fa-calendar-day me-1"></i>Plan del mes
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-calendar-check fa-2x text-primary opacity-50"></i>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <!-- Fila 1: Facturado -->
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Facturado:</small>
                                <small class="fw-bold" style="color: var(--win-text-primary);">
                                    $<?php echo number_format($facturado_mes_actual, 2); ?>
                                </small>
                            </div>
                            
                            <!-- Fila 2: Faltante -->
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted"><?php echo $texto_faltante_mes; ?></small>
                                <small class="fw-bold <?php echo $clase_faltante_mes; ?>">
                                    $<?php echo number_format($monto_mostrar_mes, 2); ?>
                                </small>
                            </div>

                            <!-- Fila 3: Cumplimiento (NUEVO) -->
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Cumplimiento:</small>
                                <small class="fw-bold <?php echo $color_porcentaje; ?>">
                                    <?php echo number_format($porcentaje_mes, 1); ?>%
                                </small>
                            </div>
                            
                            <!-- Barra de progreso -->
                            <div class="progress mt-2" style="height: 6px; background-color: var(--win-bg-tertiary);">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                     style="width: <?php echo min($porcentaje_mes, 100); ?>%"
                                     aria-valuenow="<?php echo $porcentaje_mes; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card info h-100 animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Configuración</div>
                                <div class="h5 mb-0 fw-bold text-info"><?php echo $planes_configurados; ?> / 12</div>
                                <div class="text-info small">
                                    <i class="fas fa-cog me-1"></i>Meses con Planes
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-sliders-h fa-2x text-info opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Promedio mensual:</small>
                                <small class="fw-bold text-primary">$<?php echo number_format($total_anual / 12, 2); ?></small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo ($planes_configurados / 12) * 100; ?>%;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card h-100 animate__animated animate__fadeIn" style="animation-delay: 0.4s; border-left-color: var(--win-accent);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Histórico</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-accent);">
                                    <?php echo $estadisticas_anios['total_anios']; ?> años
                                </div>
                                <div style="color: var(--win-accent);" class="small">
                                    <i class="fas fa-history me-1"></i>Desde <?php echo $estadisticas_anios['primer_anio']; ?>
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-calendar-alt fa-2x opacity-50" style="color: var(--win-accent);"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Último año:</small>
                                <small class="fw-bold" style="color: var(--win-accent);"><?php echo $estadisticas_anios['ultimo_anio']; ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Promedio anual:</small>
                                <small class="fw-bold text-success">$<?php echo number_format($estadisticas_anios['promedio_anual'] * 12, 2); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Acciones Rápidas y Configuración COMBINADO -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-tools me-1"></i>Herramientas y Personalización
                        </h6>
                        <div>
                            <span class="badge me-2" style="background-color: var(--win-accent);">
                                <i class="fas fa-magic"></i>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Columna Izquierda: Acciones Rápidas -->
                            <div class="col-lg-6 border-end" style="border-color: var(--win-border-color) !important;">
                                <h6 class="text-muted mb-3 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Acciones Rápidas</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2" onclick="copiarAnterior()">
                                            <i class="fas fa-copy"></i>
                                            <span>Copiar Año Anterior</span>
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <button class="btn btn-outline-success w-100 d-flex align-items-center justify-content-center gap-2" onclick="incrementar10Porciento()">
                                            <i class="fas fa-chart-line"></i>
                                            <span>+10% en Todos</span>
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <button class="btn btn-outline-warning w-100 d-flex align-items-center justify-content-center gap-2" onclick="recalcularPromedio()">
                                            <i class="fas fa-calculator"></i>
                                            <span>Calcular Promedio</span>
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="reportes.php?tipo=planes" class="btn btn-outline-info w-100 d-flex align-items-center justify-content-center gap-2">
                                            <i class="fas fa-chart-bar"></i>
                                            <span>Ver Reportes</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Columna Derecha: Tema y Configuración -->
                            <div class="col-lg-6 ps-lg-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Apariencia</h6>
                                    <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="abrirPanelTemas()" style="color: var(--win-accent);">
                                        <i class="fas fa-sliders-h me-1"></i>Ajustes avanzados
                                    </button>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="p-2 rounded border d-flex align-items-center" style="border-color: var(--win-border-color) !important;">
                                            <div class="me-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: var(--win-bg-tertiary); border-radius: 8px;">
                                                <i class="fas <?php echo $tema_windows == 'dark' ? 'fa-moon' : 'fa-sun'; ?>" style="color: var(--win-text-primary);"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 10px;">Tema</small>
                                                <span class="fw-bold" style="color: var(--win-text-primary);"><?php echo $temas_windows[$tema_windows]['nombre']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-6">
                                        <div class="p-2 rounded border d-flex align-items-center" style="border-color: var(--win-border-color) !important;">
                                            <div class="me-3" style="width: 36px; height: 36px; border-radius: 8px; background: <?php echo $color_accent; ?>;"></div>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 10px;">Acento</small>
                                                <span class="fw-bold" style="color: var(--win-text-primary);">
                                                    <?php echo array_search($color_accent, array_keys($colores_accent)) !== false ? $colores_accent[$color_accent] : 'Personalizado'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2" onclick="crearNuevoAnio()">
                                            <i class="fas fa-calendar-plus"></i>
                                            <span>Crear Nuevo Año Fiscal</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form id="formPlanes" method="POST" action="">
            <input type="hidden" name="action" value="guardar_planes">
            <input type="hidden" name="anio" value="<?php echo $anio_filtro; ?>">

            <!-- Tarjeta Principal -->
            <div class="card mica-effect animate__animated animate__fadeInUp" style="border-top: 4px solid var(--win-accent);">
            <!-- Modificar esta sección dentro del formPlanes -->
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-3" style="color: var(--win-text-primary);">
                        <i class="fas fa-money-bill-wave me-2"></i>Desglose Mensual - <span class="text-warning fw-bold"><?php echo $anio_filtro; ?></span>
                    </h6>
                    
                    <!-- NUEVO: Botón Eliminar Año (Solo si no es el año actual) -->
                    <?php if($anio_filtro != $anio_cierre_num): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="eliminarAnio(<?php echo $anio_filtro; ?>)" 
                            title="Eliminar este año de la base de datos"
                            style="padding: 0px 6px; font-size: 0.8rem; border: none;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <div class="badge bg-success" style="font-size: 0.9em;">
                    Total Planificado Anual: $ <span id="totalAnualDisplay"><?php echo number_format($total_anual, 2); ?></span>
                </div>
            </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" style="color: var(--win-text-primary);">
                            <thead style="background: var(--win-bg-tertiary);">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th style="width: 150px;">Mes</th>
                                    <th style="width: 250px;">Importe Plan (CUP)</th>
                                    <th>Observaciones / Detalles</th>
                                    <th style="width: 150px;">Estado Actual</th>
                                </tr>
                            </thead>
<tbody>
    <?php 
    $total_facturado_por_mes = 0;
    foreach ($meses_nombres as $num => $nombre): 
        $importe = $planes[$num]['importe'] ?? 0;
        $obs = $planes[$num]['observaciones'] ?? '';
        
        // Usar la nueva función para obtener datos reales
        $progreso = obtenerProgresoMensual($db, $num, $anio_filtro);
        $total_facturado_por_mes += $progreso['real'];
    ?>
    <tr style="border-bottom: 1px solid var(--win-border-color);">
        <td class="text-center text-muted"><?php echo $num; ?></td>
        <td class="fw-bold">
            <span style="color: var(--win-accent);"><?php echo $nombre; ?></span>
            <?php if($num == $mes_cierre_num && $anio_filtro == $anio_cierre_num): ?>
                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.6em;">ACTUAL</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-end-0" style="color: var(--win-text-secondary); border-color: var(--win-border-color);">$</span>
                <input type="number" step="0.01" class="form-control input-importe" 
                       name="planes[<?php echo $num; ?>]" 
                       value="<?php echo $importe; ?>"
                       style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border-color: var(--win-border-color); font-weight: bold;"
                       onchange="recalcularTotal()">
            </div>
            <!-- Barra de progreso visual -->
            <div class="progress mt-1" style="height: 3px; background: var(--win-bg-tertiary);">
                <div class="progress-bar" role="progressbar" 
                     style="width: <?php echo min($progreso['porcentaje'], 100); ?>%; background-color: <?php echo $progreso['color']; ?>;">
                </div>
            </div>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="obs[<?php echo $num; ?>]" 
                   value="<?php echo htmlspecialchars($obs); ?>"
                   placeholder="Ej: Temporada alta, feriados..."
                   style="background: transparent; color: var(--win-text-secondary); border: none; border-bottom: 1px solid var(--win-border-color);">
        </td>
        <td>
            <div class="d-flex flex-column">
                <?php if ($progreso['real'] > 0): ?>
                    <div class="d-flex align-items-center">
                        <span class="badge me-2" style="background-color: <?php echo $progreso['color']; ?>; color: black; font-size: 0.7rem;">
                            <?php echo number_format($progreso['porcentaje'], 1); ?>%
                        </span>
                        <span class="fw-bold" style="color: <?php echo $progreso['color']; ?>;">
                            $<?php echo number_format($progreso['real'], 2); ?>
                        </span>
                    </div>
                    <small class="text-muted mt-1">
                        <i class="fas fa-file-invoice"></i> <?php echo $progreso['cantidad']; ?> facturas
                    </small>
                <?php else: ?>
                    <span class="badge bg-secondary" style="font-size: 0.7rem;">
                        <i class="fas fa-times-circle"></i> Sin facturas
                    </span>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
<!-- Fila de totales -->
<tfoot style="background: var(--win-bg-tertiary); font-weight: bold;">
    <tr>
        <td colspan="4" class="text-end pe-4">TOTAL FACTURADO <?php echo $anio_filtro; ?>:</td>
        <td class="text-success">$<?php echo number_format($total_facturado_por_mes, 2); ?></td>
    </tr>
    <?php if ($total_anual > 0): ?>
    <tr>
        <td colspan="4" class="text-end pe-4">CUMPLIMIENTO DEL PLAN:</td>
        <td>
            <?php 
            $cumplimiento_anual = ($total_facturado_por_mes / $total_anual) * 100;
            $color_cumplimiento = $cumplimiento_anual >= 100 ? 'success' : ($cumplimiento_anual >= 50 ? 'warning' : 'danger');
            ?>
            <span class="text-<?php echo $color_cumplimiento; ?> fw-bold">
                <?php echo number_format($cumplimiento_anual, 1); ?>%
            </span>
        </td>
    </tr>
    <?php endif; ?>
</tfoot>
</tbody>
						
						</table>
                    </div>
                </div>
                
                <div class="card-footer bg-transparent border-top p-3">
<div class="d-flex justify-content-between align-items-center">
    <div>
        <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Los cambios afectarán los reportes y gráficas del Dashboard.
        </small>
        <div class="mt-1">
            <small class="text-success">
                <i class="fas fa-check-circle me-1"></i>
                <?php echo $planes_configurados; ?> meses configurados
            </small>
        </div>
    </div>
    <div>
        <button type="button" class="btn btn-outline-info me-2" onclick="mostrarExportarModal()" title="Exportar planes">
            <i class="fas fa-file-export me-1"></i> Exportar
        </button>
        <button type="button" class="btn btn-secondary me-2" onclick="window.location.reload()">
            <i class="fas fa-undo me-1"></i> Cancelar
        </button>
        <button type="button" class="btn btn-primary" onclick="guardarCambios()">
            <i class="fas fa-save me-1"></i> Guardar Cambios
        </button>
    </div>
</div>
                </div>
            </div>
        </form>
        
        <!-- Formulario oculto para nuevo año -->
        <form id="formNuevoAnio" method="POST" action="" style="display: none;">
            <input type="hidden" name="action" value="nuevo_anio">
            <input type="hidden" name="nuevo_anio" id="nuevoAnioInput">
        </form>
    </main>

    
<!-- Formulario oculto para eliminar año -->
        <form id="formEliminarAnio" method="POST" action="" style="display: none;">
            <input type="hidden" name="action" value="eliminar_anio">
            <input type="hidden" name="anio_eliminar" id="inputAnioEliminar">
        </form>

  
    <!-- Quick Actions -->
    <div class="win-quick-actions">
        <!-- Acciones expandidas -->
        <div class="win-quick-actions-expanded" id="quickActionsExpanded">
            <button class="win-quick-action action-reporte" onclick="window.location.href='reportes.php?tipo=planes'" title="Ver Reportes">
                <i class="fas fa-chart-bar"></i>
            </button>
            <button class="win-quick-action action-copiar" onclick="copiarAnterior()" title="Copiar Año Anterior">
                <i class="fas fa-copy"></i>
            </button>
            <button class="win-quick-action action-anio" onclick="crearNuevoAnio()" title="Crear Nuevo Año">
                <i class="fas fa-calendar-plus"></i>
            </button>
        <button class="win-quick-action action-chatbot" onclick="toggleChatbot()" title="Abrir Asistente Virtual" id="chatbotQuickAction">
            <i class="fas fa-robot"></i>
        </button>
        </div>
        
        <!-- Botón principal -->
        <button class="win-quick-action" onclick="toggleQuickActions()" title="Acciones rápidas" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
// Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
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
        // Recalculadora de totales en tiempo real
        function recalcularTotal() {
            let total = 0;
            const inputs = document.querySelectorAll('.input-importe');
            
            inputs.forEach(input => {
                let val = parseFloat(input.value);
                if (isNaN(val)) val = 0;
                total += val;
            });
            
            // Animación simple del número
            const display = document.getElementById('totalAnualDisplay');
            const currentVal = parseFloat(display.innerText.replace(/,/g, ''));
            
            display.innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            if (total > currentVal) {
                display.style.color = '#28a745'; // Verde si sube
            } else if (total < currentVal) {
                display.style.color = '#ffc107'; // Amarillo si baja
            }
            
            setTimeout(() => {
                display.style.color = ''; // Restaurar
            }, 500);
        }

        // Guardar cambios con confirmación SweetAlert estilo Windows 11
        function guardarCambios() {
            const swalCustom = Swal.mixin({
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark',
                    cancelButton: 'sweetalert-cancel-dark'
                },
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                buttonsStyling: true
            });

            swalCustom.fire({
                title: '¿Guardar Planes?',
                text: "Se actualizarán las metas financieras para el año <?php echo $anio_filtro; ?>.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--win-accent)',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-save me-2"></i> Sí, Guardar',
                cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    swalCustom.fire({
                        title: 'Guardando...',
                        html: 'Actualizando base de datos',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    // Enviar formulario
                    document.getElementById('formPlanes').submit();
                }
            });
        }

        // Funciones para las acciones rápidas
        function copiarAnterior() {
            Swal.fire({
                title: '¿Copiar del año anterior?',
                text: 'Esta acción copiará los valores del año <?php echo $anio_filtro - 1; ?> al año actual.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--win-accent)',
                cancelButtonColor: '#6c757d',
				confirmButtonText: '<i class="fas fa-check-circle"></i> Sí, copiar',
				cancelButtonText: '<i class="fas fa-times-circle"></i> Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Copiando datos...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    // Realizar petición AJAX para copiar datos
                    fetch('ajax/copiar_planes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `anio_origen=<?php echo $anio_filtro - 1; ?>&anio_destino=<?php echo $anio_filtro; ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Datos copiados!',
                                text: data.message,
                                confirmButtonColor: 'var(--win-accent)'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudieron copiar los datos',
                                confirmButtonColor: '#dc3545',
								cancelButtonText: '<i class="fas fa-check me-2"></i> Entendido'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo completar la operación',
                            confirmButtonColor: '#dc3545',
							cancelButtonText: '<i class="fas fa-check me-2"></i> Entendido'
							
                        });
                    });
                }
            });
        }
        
        function incrementar10Porciento() {
            const inputs = document.querySelectorAll('.input-importe');
            let totalIncremento = 0;
            
            inputs.forEach(input => {
                let val = parseFloat(input.value);
                if (!isNaN(val) && val > 0) {
                    let nuevoValor = val * 1.10; // Incrementar 10%
                    input.value = nuevoValor.toFixed(2);
                    totalIncremento += (nuevoValor - val);
                }
            });
            
            recalcularTotal();
            
            // Mostrar notificación
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            
            Toast.fire({
                icon: 'success',
                title: `+10% aplicado`,
                text: `Incremento total: $${totalIncremento.toFixed(2)}`
            });
        }
        
        function recalcularPromedio() {
            Swal.fire({
                title: 'Calcular Promedio Mensual',
                text: '¿Desea calcular el promedio basado en el total anual?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--win-accent)',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-calculator me-2"></i>Calcular',
                cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const totalDisplay = document.getElementById('totalAnualDisplay');
                    const total = parseFloat(totalDisplay.innerText.replace(/,/g, ''));
                    const promedio = total / 12;
                    
                    const inputs = document.querySelectorAll('.input-importe');
                    inputs.forEach(input => {
                        input.value = promedio.toFixed(2);
                    });
                    
                    recalcularTotal();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Promedio calculado',
                        html: `Se ha establecido <strong>$${promedio.toFixed(2)}</strong> en todos los meses`,
                        confirmButtonColor: 'var(--win-accent)'
                    });
                }
            });
        }
        
        // Crear nuevo año
        function crearNuevoAnio() {
            const ultimoAnio = <?php echo max($anios_disponibles); ?>;
            const nuevoAnio = ultimoAnio + 1;
            
            Swal.fire({
                title: 'Crear Nuevo Año',
                html: `
                    <div class="text-start">
                        <p>¿Desea crear un nuevo año de planificación?</p>
                        <div class="alert alert-info p-2">
                            <i class="fas fa-info-circle me-2"></i>
                            Se creará el año <strong>${nuevoAnio}</strong> con valores iniciales en 0.00
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Año a crear:</label>
                            <input type="number" id="anioNuevoInput" class="form-control" value="${nuevoAnio}" min="${ultimoAnio + 1}" max="${nuevoAnio + 10}">
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--win-accent)',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-plus-circle me-2"></i> Crear Año',
                cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: { popup: 'sweetalert-dark' },
                preConfirm: () => {
                    const input = document.getElementById('anioNuevoInput');
                    const anio = parseInt(input.value);
                    
                    if (!anio || anio < ultimoAnio + 1) {
                        Swal.showValidationMessage('El año debe ser mayor al último año existente');
                        return false;
                    }
                    
                    return anio;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const anioSeleccionado = result.value;
                    
                    // Mostrar loading
                    Swal.fire({
                        title: 'Creando año...',
                        html: 'Configurando meses iniciales',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    // Enviar formulario para crear nuevo año
                    document.getElementById('nuevoAnioInput').value = anioSeleccionado;
                    document.getElementById('formNuevoAnio').submit();
                }
            });
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

        // Inicializar tooltips y eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Formatear inputs al perder foco para que se vean bonitos (0.00)
            const inputs = document.querySelectorAll('.input-importe');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if(this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            });
            
            // Inicializar tooltips de Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
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
            
            // Prevenir F5 y Ctrl+R
            document.addEventListener('keydown', function(e) {
                // 116 es el código de la tecla F5
                // 82 es la tecla R (para Ctrl + R)
                if (e.keyCode === 116 || (e.ctrlKey && e.keyCode === 82)) {
                    e.preventDefault(); // Detiene la acción por defecto del navegador
                    
                    // Opcional: Mostrar alerta con SweetAlert
                    Swal.fire({
                        icon: 'warning',
                        title: 'Actualización bloqueada',
                        text: 'Para evitar pérdida de datos, usa los botones de navegación del sistema.',
                        showConfirmButton: true,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                    
                    return false;
                }
            });
			
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
                                <strong>Privilegios requeridos:</strong> Administrador, Supervisor, Programador.
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
            
            // Mostrar mensaje de bienvenida si es el primer acceso
            const esPrimerAcceso = sessionStorage.getItem('planes_accessed') !== 'true';
            if (esPrimerAcceso) {
                Swal.fire({
                    title: 'Plan de Ingresos',
                    html: `
                        <div style="text-align: left;">
                            <p>Aquí puede configurar las metas mensuales de ingresos para el año <strong><?php echo $anio_filtro; ?></strong>.</p>
                            <ul style="padding-left: 20px; margin-top: 10px;">
                                <li>Modifique los importes según sus objetivos</li>
                                <li>Agregue observaciones para cada mes</li>
                                <li>Use las acciones rápidas para ajustes masivos</li>
                                <li>Use el botón <i class="fas fa-calendar-plus"></i> para crear nuevos años</li>
                                <li>Recuerde guardar los cambios al finalizar</li>
                            </ul>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonColor: 'var(--win-accent)',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
                });
                sessionStorage.setItem('planes_accessed', 'true');
            }
        });
function eliminarAnio(anio) {
            Swal.fire({
                title: '¿Eliminar Año ' + anio + '?',
                html: `
                    <div class="text-start">
                        <p class="text-danger fw-bold">⚠️ Esta acción es irreversible.</p>
                        <p>Se eliminarán todos los registros de planes y metas para el año <strong>${anio}</strong>.</p>
                        <p class="small text-muted">Nota: Esto no elimina las facturas, solo la planificación.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí, Eliminar',
                cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
                confirmButtonColor: '#dc3545', // Rojo peligro
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: { popup: 'sweetalert-dark' }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Eliminando...',
                        html: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    // Enviar formulario
                    document.getElementById('inputAnioEliminar').value = anio;
                    document.getElementById('formEliminarAnio').submit();
                }
            });
        }
// Función para mostrar el modal de exportación
function mostrarExportarModal() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

// Función para exportación rápida
function exportarRapido(format) {
    Swal.fire({
        title: 'Exportando...',
        text: 'Preparando archivo para descarga',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Redirigir para descargar
    window.location.href = `exportar_planes.php?format=${format}&anio=<?php echo $anio_filtro; ?>`;
    
    // Ocultar SweetAlert después de 2 segundos
    setTimeout(() => {
        Swal.close();
    }, 2000);
}

// Exportación directa (botón alternativo)
function exportarPlanes() {
    const swalExport = Swal.mixin({
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        customClass: { popup: 'sweetalert-dark' }
    });
    
    swalExport.fire({
        title: 'Exportar Planes',
        html: `
            <div class="text-start">
                <p>Exportar planes del año <strong><?php echo $anio_filtro; ?></strong></p>
                <div class="row g-2 mt-3">
                    <div class="col-6">
                        <button class="btn btn-success w-100 py-2" onclick="exportarRapido('excel')">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-primary w-100 py-2" onclick="exportarRapido('pdf')">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-info w-100 py-2" onclick="exportarRapido('csv')">
                            <i class="fas fa-file-csv me-1"></i> CSV
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-warning w-100 py-2" onclick="exportarRapido('word')">
                            <i class="fas fa-file-word me-1"></i> Word
                        </button>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted">Más formatos en el menú completo</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-sliders-h me-1"></i> Ver todos los formatos',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        confirmButtonColor: 'var(--win-accent)',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            mostrarExportarModal();
        }
    });
}
// Función para imprimir planes - VERSIÓN CON CIERRE AUTOMÁTICO
function imprimirPlanes() {
    Swal.fire({
        title: 'Imprimir Plan de Ingresos',
        text: '¿Desea imprimir el plan del año <?php echo $anio_filtro; ?>?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0078d4',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-print me-2"></i> Imprimir',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.close();
            
            // Construir el contenido HTML para la nueva ventana
            let contenidoHTML = `
            <html>
            <head>
                <title>Plan de Ingresos <?php echo $anio_filtro; ?> - PDL Visiones</title>
                <style>
                    @page {
                        size: landscape;
                        margin: 1.5cm;
                    }
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 20px;
                        background: white;
                        color: black;
                    }
                    .print-header {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        margin-bottom: 20px;
                        border-bottom: 2px solid #0078d4;
                        padding-bottom: 15px;
                    }
                    .print-logo {
                        display: flex;
                        align-items: center;
                        gap: 15px;
                    }
                    .print-logo img {
                        width: 80px;
                        height: auto;
                    }
                    .print-title {
                        font-size: 24px;
                        font-weight: bold;
                        color: #0078d4;
                        margin: 0;
                    }
                    .print-subtitle {
                        color: #666;
                        font-size: 14px;
                        margin: 3px 0 0 0;
                    }
                    .print-info {
                        text-align: right;
                        font-size: 12px;
                    }
                    .print-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    .print-table th {
                        background: #f0f0f0;
                        color: black;
                        font-weight: bold;
                        padding: 10px;
                        border: 1px solid #ddd;
                        text-align: center;
                    }
                    .print-table td {
                        padding: 8px;
                        border: 1px solid #ddd;
                    }
                    .print-table td:first-child {
                        text-align: center;
                    }
                    .print-table td:nth-child(3) {
                        text-align: right;
                    }
                    .print-badge {
                        background: #e0e0e0;
                        padding: 2px 8px;
                        border-radius: 4px;
                        font-size: 11px;
                        margin-left: 5px;
                    }
                    .text-success-print { color: #28a745; font-weight: bold; }
                    .text-warning-print { color: #ffc107; font-weight: bold; }
                    .text-danger-print { color: #dc3545; font-weight: bold; }
                    tfoot tr {
                        background: #f5f5f5;
                        font-weight: bold;
                    }
                    .footer-info {
                        margin-top: 40px;
                        border-top: 2px solid #0078d4;
                        padding-top: 15px;
                    }
                    .footer-row {
                        display: flex;
                        justify-content: space-between;
                        font-size: 10px;
                        color: #666;
                    }
                    .footer-final {
                        margin-top: 10px;
                        padding-top: 10px;
                        border-top: 1px dotted #ccc;
                        text-align: center;
                        font-size: 9px;
                        color: #999;
                    }
                </style>
            </head>
            <body>`;
            
            // Obtener el contenido de print-section
            const printSection = document.getElementById('print-section');
            if (printSection) {
                let printContent = printSection.innerHTML;
                printContent = printContent.replace(/src="assets\//g, 'src="assets/');
                contenidoHTML += printContent;
            } else {
                contenidoHTML += '<p>Error: No se encontró el contenido para imprimir</p>';
            }
            
            contenidoHTML += `
            <script>
                // Auto-cerrar después de imprimir
                window.onafterprint = function() {
                    window.close();
                };
                
                // Fallback para algunos navegadores
                setTimeout(function() {
                    window.print();
                    // Cerrar después de un tiempo si afterprint no funciona
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                }, 500);
            <\/script>
            </body>
            </html>`;
            
            // Crear nueva ventana
            const printWindow = window.open('', '_blank');
            printWindow.document.write(contenidoHTML);
            printWindow.document.close();
        }
    });
}
// También puedes agregar un atajo de teclado (Ctrl+P)
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        imprimirPlanes();
    }
});
    </script>
<!-- Modal para Exportar Planes -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--win-border-color);">
                <h5 class="modal-title" style="color: var(--win-text-primary);">
                    <i class="fas fa-file-export me-2"></i>Exportar Planes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">Seleccione el formato para exportar los planes del año <strong><?php echo $anio_filtro; ?></strong></p>
                
                <div class="row g-3">
                    <div class="col-6">
                        <a href="exportar_planes.php?format=excel&anio=<?php echo $anio_filtro; ?>" 
                           class="btn btn-outline-success w-100 h-100 py-3 d-flex flex-column align-items-center justify-content-center" 
                           target="_blank">
                            <i class="fas fa-file-excel fa-2x mb-2"></i>
                            <span>Excel (.xlsx)</span>
                            <small class="text-muted mt-1">Microsoft Excel</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="exportar_planes.php?format=word&anio=<?php echo $anio_filtro; ?>" 
                           class="btn btn-outline-primary w-100 h-100 py-3 d-flex flex-column align-items-center justify-content-center" 
                           target="_blank">
                            <i class="fas fa-file-word fa-2x mb-2"></i>
                            <span>Word (.docx)</span>
                            <small class="text-muted mt-1">Microsoft Word</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="exportar_planes.php?format=pdf&anio=<?php echo $anio_filtro; ?>" 
                           class="btn btn-outline-danger w-100 h-100 py-3 d-flex flex-column align-items-center justify-content-center" 
                           target="_blank">
                            <i class="fas fa-file-pdf fa-2x mb-2"></i>
                            <span>PDF (.pdf)</span>
                            <small class="text-muted mt-1">Documento PDF</small>
                        </a>
                    </div>
                    <div class="col-6">
                        <div class="dropdown h-100">
                            <button class="btn btn-outline-info w-100 h-100 py-3 d-flex flex-column align-items-center justify-content-center" 
                                    type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <span>Otros Formatos</span>
                                <small class="text-muted mt-1">CSV, XML, JSON</small>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" style="background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
                                <a class="dropdown-item" href="exportar_planes.php?format=csv&anio=<?php echo $anio_filtro; ?>" target="_blank">
                                    <i class="fas fa-file-csv me-2"></i>CSV (.csv)
                                </a>
                                <a class="dropdown-item" href="exportar_planes.php?format=xml&anio=<?php echo $anio_filtro; ?>" target="_blank">
                                    <i class="fas fa-code me-2"></i>XML (.xml)
                                </a>
                                <a class="dropdown-item" href="exportar_planes.php?format=json&anio=<?php echo $anio_filtro; ?>" target="_blank">
                                    <i class="fas fa-file-code me-2"></i>JSON (.json)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 p-3 rounded" style="background: var(--win-bg-tertiary);">
                    <h6 class="mb-2" style="color: var(--win-text-primary);">
                        <i class="fas fa-info-circle me-2"></i>Información de la Exportación
                    </h6>
                    <ul class="mb-0" style="color: var(--win-text-secondary); font-size: 0.85rem; padding-left: 1.5rem;">
                        <li>Año: <strong><?php echo $anio_filtro; ?></strong></li>
                        <li>Total de meses: <strong>12</strong></li>
                        <li>Plan anual total: <strong>$<?php echo number_format($total_anual, 2); ?></strong></li>
                        <li>Meses configurados: <strong><?php echo $planes_configurados; ?></strong></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--win-border-color);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-close me-1"></i>Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="exportarRapido('excel')">
                    <i class="fas fa-bolt me-1"></i> Exportar Rápido (Excel)
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
<!-- Sección para impresión (oculta normalmente) -->
<div id="print-section" style="display: none;">
    <div class="print-header">
        <div class="print-logo">
            <img src="assets/logov.png" alt="PDL Visiones Logo">
            <div>
                <div class="print-title">PDL Visiones</div>
                <div class="print-subtitle">Plan de Ingresos - Año <?php echo $anio_filtro; ?></div>
            </div>
        </div>
        <div class="print-info">
            <div><strong>Fecha de impresión:</strong> <?php echo date('d/m/Y H:i'); ?></div>
            <div><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></div>
            <div><strong>Rol:</strong> <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></div>
        </div>
    </div>
    
    <table class="print-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Mes</th>
                <th style="width: 20%;">Plan (CUP)</th>
                <th style="width: 35%;">Observaciones</th>
                <th style="width: 25%;">Estado Actual</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_plan_print = 0;
            $total_facturado_print = 0;
            foreach ($meses_nombres as $num => $nombre): 
                $importe = $planes[$num]['importe'] ?? 0;
                $obs = $planes[$num]['observaciones'] ?? '';
                $progreso = obtenerProgresoMensual($db, $num, $anio_filtro);
                $total_plan_print += $importe;
                $total_facturado_print += $progreso['real'];
            ?>
            <tr>
                <td style="text-align: center;"><?php echo $num; ?></td>
                <td>
                    <strong><?php echo $nombre; ?></strong>
                    <?php if($num == $mes_cierre_num && $anio_filtro == $anio_cierre_num): ?>
                        <span class="print-badge">ACTUAL</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">$ <?php echo number_format($importe, 2); ?></td>
                <td><?php echo htmlspecialchars($obs) ?: '-'; ?></td>
                <td>
                    <?php if ($progreso['real'] > 0): ?>
                        <strong class="<?php 
                            echo $progreso['porcentaje'] >= 100 ? 'text-success-print' : 
                                ($progreso['porcentaje'] >= 50 ? 'text-warning-print' : 'text-danger-print'); 
                        ?>">
                            <?php echo number_format($progreso['porcentaje'], 1); ?>%
                        </strong>
                        <br>
                        <span style="font-size: 11px;">
                            $<?php echo number_format($progreso['real'], 2); ?> 
                            (<?php echo $progreso['cantidad']; ?> facturas)
                        </span>
                    <?php else: ?>
                        <span style="color: #999;">Sin facturas</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f5f5f5; font-weight: bold;">
                <td colspan="2" style="text-align: right;">TOTALES:</td>
                <td style="text-align: right;">$ <?php echo number_format($total_plan_print, 2); ?></td>
                <td></td>
                <td style="text-align: right;">$ <?php echo number_format($total_facturado_print, 2); ?></td>
            </tr>
            <tr style="background: #f0f0f0;">
                <td colspan="4" style="text-align: right;">CUMPLIMIENTO DEL PLAN:</td>
                <td style="text-align: right;">
                    <?php 
                    $cumplimiento_print = $total_plan_print > 0 ? ($total_facturado_print / $total_plan_print) * 100 : 0;
                    $color_cumplimiento = $cumplimiento_print >= 100 ? 'text-success-print' : 
                        ($cumplimiento_print >= 50 ? 'text-warning-print' : 'text-danger-print');
                    ?>
                    <strong class="<?php echo $color_cumplimiento; ?>">
                        <?php echo number_format($cumplimiento_print, 1); ?>%
                    </strong>
                </td>
            </tr>
        </tfoot>
    </table>
    
        <!-- Footer mejorado con múltiples líneas de información -->
        <div style="margin-top: 40px; border-top: 2px solid #0078d4; padding-top: 15px;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 10px; color: #666;">
                    <p style="margin: 2px 0;"><strong>Fecha de Exportación:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                    <p style="margin: 2px 0;"><strong>Fecha de Operaciones:</strong> <?php echo $hoy; ?></p>
                    <p style="margin: 2px 0;"><strong>Mes de Cierre:</strong> <?php echo $mes_cierre_num; ?> - <?php echo $meses_nombres[$mes_cierre_num]; ?></p>
                </div>
                <div style="font-size: 10px; color: #666;">
                    <p style="margin: 2px 0;"><strong>Año Plan:</strong> <?php echo $anio_filtro; ?></p>
                    <p style="margin: 2px 0;"><strong>Año Operaciones:</strong> <?php echo $anio_cierre_num; ?></p>
                    <p style="margin: 2px 0;"><strong>Total Meses Configurados:</strong> <?php echo $planes_configurados; ?>/12</p>
                </div>
                <div style="font-size: 10px; color: #666; text-align: right;">
                    <p style="margin: 2px 0;"><strong>Generado por:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></p>
                    <p style="margin: 2px 0;"><strong>IP:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                    <p style="margin: 2px 0;"><strong>Versión:</strong> SISFACT 2.3.3</p>
                </div>
            </div>
            
            <!-- Línea decorativa -->
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dotted #ccc; text-align: center; font-size: 12px; color: #999;">
                <p style="margin: 2px 0;">Documento generado por SISFACT® PDL Visiones - Sistema de Planificación de Ingresos</p>
                <p style="margin: 2px 0;">Los valores están expresados en CUP (Peso Cubano) | Este es un documento informativo, no válido como factura fiscal</p>
            </div>
        </div>
</div>



</body>
</html>