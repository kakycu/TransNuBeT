<?php
require_once 'config/header.php';
require_once 'config/cierre_funciones.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $db = Database::getConnection();
    
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre,
                    r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    if ($usuario['rol_codigo'] != 'Admin' && $usuario['rol_codigo'] != 'Super'  && $usuario['rol_codigo'] != 'Soft') {
        $rol_actual = '';
        switch($usuario['rol_id']) {
            case 2: $rol_actual = 'Visualizador'; break;
            case 3: $rol_actual = 'Editor/Facturador'; break;
            case 1: $rol_actual = 'Administrador'; break;
            case 4: $rol_actual = 'Supervisor'; break;
            case 5: $rol_actual = 'Programador'; break;
            default: $rol_actual = 'Usuario';
        }
        
        echo '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>SISFACT PDL VISIONES - Acceso Denegado</title>
            <link rel="icon" type="image/x-icon" href="assets/logov.png">
            <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
            <!-- SweetAlert2 con tema oscuro -->
            <script src="js/sweetalert211.js"></script>
            <style>
                :root {
                    --swal2-background: #1e1e2d;
                    --swal2-color: #e1e1e6;
                    --swal2-title-color: #f8f9fa;
                }
                .swal2-popup {
                    border-radius: 12px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .swal2-title {
                    font-size: 1.5rem;
                    font-weight: 600;
                }
                .swal2-html-container {
                    font-size: 1.1rem;
                    line-height: 1.6;
                }
                .swal2-confirm {
                    font-weight: 600;
                    letter-spacing: 0.5px;
                    transition: all 0.3s ease;
                }
                .swal2-confirm:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
                }
                .swal2-icon {
                    border-width: 3px;
                }
            </style>
        </head>
        <body style="background: #0f0f1a; margin: 0; min-height: 100vh;">
            <script>
            // Configuración global de tema oscuro
            const darkTheme = Swal.mixin({
                background: "#1e1e2d",
                color: "#e1e1e6",
                color: "#e1e1e6",
                confirmButtonColor: "#dc3545",
                confirmButtonText: "<i class=\'fas fa-lock mr-2\'></i> Entendido",
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showClass: {
                    popup: "swal2-show animate__animated animate__fadeInDown"
                },
                hideClass: {
                    popup: "swal2-hide animate__animated animate__fadeOutUp"
                },
                customClass: {
                    container: "custom-swal-container",
                    popup: "custom-swal-popup",
                    header: "custom-swal-header",
                    title: "custom-swal-title",
                    closeButton: "custom-swal-close",
                    icon: "custom-swal-icon",
                    htmlContainer: "custom-swal-html",
                    actions: "custom-swal-actions",
                    confirmButton: "custom-swal-confirm"
                }
            });

            darkTheme.fire({
                icon: "error",
                iconColor: "#dc3545",
                title: "<i class=\'fas fa-ban mr-2\'></i> Acceso Restringido",
                html: `
                    <div style="text-align: left; padding: 10px 0;">
                        <p style="margin-bottom: 15px; color: #a8a8b3;">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            No tienes los permisos necesarios para acceder a esta sección.
                        </p>
                        <div style="background: #2d2d44; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
                            <p style="margin: 5px 0;">
                                <strong style="color: #a8a8b3;">Tu rol actual: </strong>
                                <span style="color: #ff6b6b; font-weight: 600; margin-left: 8px;">
                                    <i class="fas fa-user-tag me-1"></i>' . $rol_actual . '
                                </span>
                            </p>
                            <p style="margin: 5px 0;">
                                <strong style="color: #a8a8b3;">Roles permitidos:</strong>
                                <span style="color: #4cd964; font-weight: 600; margin-left: 8px;">
                                    <i class="fas fa-user-shield me-2"></i>Administrador, Supervisor o Programador
                                </span>
                            </p>
                        </div>
                        <p style="margin-top: 15px; font-size: 0.95rem; color: #8a8a9e;">
                            <i class="fas fa-info-circle mr-2"></i>
                            Contacta con el administrador del sistema si necesitas acceder a esta funcionalidad.
                        </p>
                    </div>`,
                width: "500px",
                padding: "2rem",
            }).then((result) => {
                if (result.isConfirmed) {
                    // Animación de salida antes de redirigir
                    darkTheme.fire({
                        title: "Redirigiendo...",
                        icon: "info",
                        timer: 1000,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    }).then(() => {
                        window.location.href = "dashboard.php";
                    });
                }
            });

            // Redirección automática después de 10 segundos (como fallback)
            setTimeout(() => {
                darkTheme.fire({
                    title: "Redirección automática",
                    text: "Serás redirigido al dashboard",
                    icon: "info",
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = "dashboard.php";
                });
            }, 10000);
            </script>
        </body>
        </html>';
        exit();
    } else {
        $es_admin = true;
    }
    
} catch (Exception $e) {
    error_log("Error al verificar usuario: " . $e->getMessage());
    header('Location: index.php');
    exit();
}

// ==================== CONFIGURACIÓN DE UI ====================
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';

// ==================== OBTENER DATOS PARA COMBOS ====================
$db = Database::getConnection();

// Array de meses para visualización
$meses_abrev = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

// OPCIÓN 1: Obtener meses con cierres mensuales (para combo de meses individuales)
// CAMBIO: tipo = 1 en lugar de 'MES'
$sql_meses = "SELECT DISTINCT periodo_mes, periodo_anio 
              FROM historico_cierres 
              WHERE tipo = 1 
              ORDER BY periodo_anio ASC, periodo_mes ASC";
$stmt_meses = $db->prepare($sql_meses);
$stmt_meses->execute();
$meses_cerrados = $stmt_meses->fetchAll(PDO::FETCH_ASSOC);

// Procesar meses individuales para el combo
$opciones_meses = [];
foreach ($meses_cerrados as $mes) {
    if ($mes['periodo_mes'] && $mes['periodo_anio']) {
        $clave = $mes['periodo_anio'] . '-' . str_pad($mes['periodo_mes'], 2, '0', STR_PAD_LEFT);
        $texto = $meses_abrev[$mes['periodo_mes']] . ' ' . $mes['periodo_anio'];
        $opciones_meses[$clave] = $texto;
    }
}

// OPCIÓN 2: Obtener años con cierres (tanto mensuales como anuales)
$sql_anios = "SELECT DISTINCT periodo_anio 
              FROM historico_cierres 
              ORDER BY periodo_anio ASC";
$stmt_anios = $db->prepare($sql_anios);
$stmt_anios->execute();
$anios_con_cierres = $stmt_anios->fetchAll(PDO::FETCH_ASSOC);

// Para cada año, obtener qué meses tienen cierres y si tiene cierre anual
$opciones_anios_completos = [];
foreach ($anios_con_cierres as $anio_data) {
    $anio = $anio_data['periodo_anio'];
    
    // Verificar si existe cierre anual para este año
    // CAMBIO: tipo = 2 en lugar de 'ANIO'
    $sql_cierre_anual = "SELECT COUNT(*) as tiene_anual 
                         FROM historico_cierres 
                         WHERE tipo = 2 AND periodo_anio = :anio";
    $stmt_anual = $db->prepare($sql_cierre_anual);
    $stmt_anual->execute(['anio' => $anio]);
    $tiene_anual = $stmt_anual->fetch(PDO::FETCH_ASSOC)['tiene_anual'] > 0;
    
    // Obtener meses con cierres para este año
    // CAMBIO: tipo = 1 en lugar de 'MES'
    $sql_meses_anio = "SELECT DISTINCT periodo_mes 
                       FROM historico_cierres 
                       WHERE tipo = 1 AND periodo_anio = :anio 
                       ORDER BY periodo_mes";
    $stmt_meses_anio = $db->prepare($sql_meses_anio);
    $stmt_meses_anio->execute(['anio' => $anio]);
    $meses_con_cierre = $stmt_meses_anio->fetchAll(PDO::FETCH_COLUMN);
    
    // Construir descripción del año
    $descripcion = "Año " . $anio . " - ";
    
    if ($tiene_anual) {
        $descripcion .= "<span class='text-success'><i class='fas fa-calendar-star'></i> Cierre Anual</span>";
    }
    
    if (!empty($meses_con_cierre)) {
        if ($tiene_anual) {
            $descripcion .= " | ";
        }
        
        $meses_texto = array_map(function($mes) use ($meses_abrev) {
            return $meses_abrev[$mes];
        }, $meses_con_cierre);
        
        $descripcion .= "<span class='text-info'><i class='fas fa-calendar-alt'></i> Meses: " . implode(', ', $meses_texto) . "</span>";
    }
    
    $opciones_anios_completos[$anio] = [
        'texto' => $descripcion,
        'tiene_anual' => $tiene_anual,
        'meses_con_cierre' => $meses_con_cierre
    ];
}

// Obtener datos de cierres para mostrar
$limit = 50;
$cierres = obtenerCierresRealizados($db, null, $limit);

// ORDENAR LOS CIERRES: Primero por año descendente, luego por mes ascendente
usort($cierres, function($a, $b) {
    // Primero comparar por año (descendente)
    if ($a['periodo_anio'] != $b['periodo_anio']) {
        return $b['periodo_anio'] - $a['periodo_anio'];
    }
    
    // Si es el mismo año, ordenar por tipo (anual primero (2), luego mensual (1))
    if ($a['tipo'] != $b['tipo']) {
        return ($a['tipo'] == 2) ? -1 : 1;
    }
    
    // Si ambos son mensuales (tipo=1), ordenar por mes (ascendente)
    if ($a['tipo'] == 1 && $b['tipo'] == 1) {
        return $a['periodo_mes'] - $b['periodo_mes'];
    }
    
    // Si ambos son anuales (tipo=2), ordenar por fecha de ejecución
    return strtotime($b['fecha_ejecucion']) - strtotime($a['fecha_ejecucion']);
});

// Filtrar por tipo si se solicita
$tipo_filtro = $_GET['tipo'] ?? 'TODOS';
$mes_filtro = $_GET['mes'] ?? '';
$anio_filtro = $_GET['anio'] ?? '';

// CAMBIO: Convertir los filtros de tipo string a int para la comparación
$tipo_filtro_int = null;
if ($tipo_filtro === 'MES') {
    $tipo_filtro_int = 1;
} elseif ($tipo_filtro === 'ANIO') {
    $tipo_filtro_int = 2;
}

if ($tipo_filtro_int) {
    // Filtrar por tipo general (1 o 2)
    $cierres = array_filter($cierres, fn($c) => $c['tipo'] == $tipo_filtro_int);
} elseif ($tipo_filtro === 'COMBO_MES' && $mes_filtro) {
    // Filtrar por mes específico del combo
    list($anio, $mes) = explode('-', $mes_filtro);
    $cierres = array_filter($cierres, function($c) use ($anio, $mes) {
        return $c['tipo'] == 1 && 
               $c['periodo_anio'] == $anio && 
               $c['periodo_mes'] == intval($mes);
    });
} elseif ($tipo_filtro === 'COMBO_ANIO' && $anio_filtro) {
    // Filtrar por año específico (mostrar TODOS los cierres de ese año)
    $cierres = array_filter($cierres, function($c) use ($anio_filtro) {
        return $c['periodo_anio'] == $anio_filtro;
    });
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cierres - SISFACT PDL VISIONES</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">

    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <style>
        :root {
            --win-bg-primary: #121212;
            --win-bg-secondary: #1e1e1e;
            --win-bg-tertiary: #252525;
            --win-bg-elevated: #2d2d2d;
            --win-text-primary: #e8eaed;
            --win-text-secondary: #a6a6a6;
            --win-border-color: #383838;
            --win-accent: <?php echo $color_accent; ?>;
            --win-accent-rgb: <?php echo hexdec(substr($color_accent, 1, 2)).', '.hexdec(substr($color_accent, 3, 2)).', '.hexdec(substr($color_accent, 5, 2)); ?>;
            --win-radius: 12px;
            --win-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        /* --- Utilidades --- */
        h1, h2, h3, h4, h5 { font-weight: 600; letter-spacing: -0.5px; }
        .text-muted { color: var(--win-text-secondary) !important; }
        .text-accent { color: var(--win-accent) !important; }
        a { text-decoration: none; }

        /* --- Tarjetas --- */
        .win-card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .win-card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .win-card-body { padding: 0; }

        /* --- Tabla Moderna --- */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
        }

        .table-custom thead th {
            background: var(--win-bg-tertiary);
            color: var(--win-text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--win-border-color);
            padding: 1rem 1.5rem;
            white-space: nowrap;
        }

        .table-custom tbody tr {
            background: var(--win-bg-secondary);
            transition: var(--win-transition);
        }

        .table-custom tbody tr:hover {
            background: var(--win-bg-elevated);
        }

        .table-custom td {
            border-bottom: 1px solid var(--win-border-color);
            padding: 1rem 1.5rem;
            vertical-align: middle;
            color: var(--win-text-primary);
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        /* --- Badges --- */
        .badge-pill {
            padding: 0.4em 0.8em;
            border-radius: 50rem;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }

        .badge-mes { background: rgba(13, 110, 253, 0.15); color: #3d8bfd; border: 1px solid rgba(13, 110, 253, 0.3); }
        .badge-anio { background: rgba(25, 135, 84, 0.15); color: #20c997; border: 1px solid rgba(25, 135, 84, 0.3); }

        /* --- Botones --- */
        .btn-win {
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            border: 1px solid transparent;
            transition: var(--win-transition);
            display: inline-flex;
            align-items: center;
        }

        .btn-win-primary {
            background: var(--win-accent);
            color: #fff;
            box-shadow: 0 4px 12px rgba(var(--win-accent-rgb), 0.3);
        }
        .btn-win-primary:hover {
            filter: brightness(110%);
            transform: translateY(-2px);
            color: white;
        }

        .btn-win-outline {
            background: transparent;
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }
        .btn-win-outline:hover {
            background: var(--win-bg-elevated);
            border-color: var(--win-text-secondary);
        }
        
        .btn-win-outline.active {
            background: rgba(var(--win-accent-rgb), 0.15);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }

        .btn-icon-sm {
            width: 32px; height: 32px;
            display: inline-flex;
            align-items: center; justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
            background: transparent;
            color: var(--win-text-secondary);
            border: 1px solid var(--win-border-color);
        }
        .btn-icon-sm:hover {
            background: var(--win-accent);
            color: white;
            border-color: var(--win-accent);
        }

        /* --- Filtros --- */
        .filter-bar {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            padding: 0.5rem;
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Combos */
        .combo-container {
            position: relative;
            display: inline-block;
        }
        
        .combo-select {
            background: var(--win-bg-elevated);
            border: 1px solid var(--win-border-color);
            border-radius: 8px;
            color: var(--win-text-primary);
            padding: 0.4rem 1rem 0.4rem 2rem;
            font-size: 0.875rem;
            min-width: 200px;
            cursor: pointer;
            transition: var(--win-transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23a6a6a6' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 0.75rem center;
            background-size: 12px;
        }
        
        .combo-select-anio {
            min-width: 280px;
        }
        
        .combo-select:hover {
            border-color: var(--win-text-secondary);
            background-color: var(--win-bg-tertiary);
        }
        
        .combo-select:focus {
            outline: none;
            border-color: var(--win-accent);
            box-shadow: 0 0 0 2px rgba(var(--win-accent-rgb), 0.2);
        }
        
        .combo-select option {
            background: var(--win-bg-secondary);
            color: var(--win-text-primary);
            padding: 8px;
        }
        
        .combo-select option:checked {
            background: var(--win-accent);
            color: white;
        }
        
        /* Estilos especiales para opciones con HTML */
        .option-anio {
            padding: 10px !important;
            border-bottom: 1px solid var(--win-border-color);
        }
        
        .option-anio i {
            margin-right: 5px;
        }
        
        .text-success { color: #20c997 !important; }
        .text-info { color: #3d8bfd !important; }
        .text-warning { color: #ffc107 !important; }
        
        /* Ajuste para el filtro activo del combo */
        .combo-container.active {
            background: rgba(var(--win-accent-rgb), 0.15);
            border-radius: 8px;
            padding: 2px;
        }
        
        .combo-container.active .combo-select {
            border-color: var(--win-accent);
            color: var(--win-accent);
        }
        
        /* Alertas de filtro */
        .alert-filtro {
            background: rgba(var(--win-accent-rgb), 0.05);
            border: 1px solid rgba(var(--win-accent-rgb), 0.2);
            border-left: 4px solid var(--win-accent);
            border-radius: var(--win-radius);
        }
        
        /* Estadísticas del año */
        .estadisticas-anio {
            background: rgba(var(--win-accent-rgb), 0.05);
            border: 1px solid rgba(var(--win-accent-rgb), 0.1);
            border-radius: var(--win-radius);
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .estadisticas-anio h5 {
            color: var(--win-accent);
            border-bottom: 1px solid rgba(var(--win-accent-rgb), 0.2);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .estadistica-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(var(--win-border-color), 0.3);
        }
        
        .estadistica-item:last-child {
            border-bottom: none;
        }
        
        /* Ajustes para responsive */
        @media (max-width: 1200px) {
            .filter-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }
            
            .combo-select {
                min-width: 100%;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .filter-bar {
                width: 100%;
            }
            
            .btn-win-outline {
                width: 100%;
                justify-content: center;
                margin-bottom: 0.5rem;
            }
            
            .combo-select-anio {
                min-width: 100%;
                font-size: 0.8rem;
            }
        }
/* --- Estilos adicionales para filtros mejorados --- */
.filter-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--win-transition);
}

.filter-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.filter-chip .remove {
    margin-left: 8px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.filter-chip .remove:hover {
    opacity: 1;
}

.combo-select option {
    padding: 12px !important;
    border-bottom: 1px solid var(--win-border-color);
}

.combo-select option:hover {
    background: var(--win-bg-elevated);
}

/* Animación para los filtros activos */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.win-card .badge-pill {
    animation: slideIn 0.3s ease;
}

/* Mejora para el responsive */
@media (max-width: 991px) {
    .win-card-body .row {
        flex-direction: column;
    }
    
    .col-lg-8, .col-lg-4 {
        width: 100%;
    }
    
    .col-lg-4 {
        margin-top: 1rem;
    }
    
    .d-flex.align-items-center {
        flex-wrap: wrap;
    }
    
    .text-muted.small.me-2 {
        min-width: 100%;
        margin-bottom: 0.5rem;
    }
}
/* Badge para meses en estadísticas */
.bg-info.bg-opacity-15 {
    background: rgba(13, 110, 253, 0.15) !important;
    border: 1px solid rgba(13, 110, 253, 0.3);
    color: #3d8bfd;
    font-size: 0.75rem;
    font-weight: 500;
}
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-secondary" style="border-color: var(--win-border-color) !important;">
            <div>
                <h1 class="h2 mb-1">
                    <span class="text-accent me-2"><i class="fas fa-history"></i></span>
                    Historial de Cierres
                </h1>
                <p class="text-muted mb-0">
                    Registro de todas las operaciones de cierre mensual y anual.
                </p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="facturas.php" class="btn btn-outline-secondary btn-win">
                    <i class="fas fa-arrow-left me-2"></i>Facturas
                </a>
                <a href="dashboard.php" class="btn btn-win btn-win-outline">
                    <i class="fas fa-dashboard me-2"></i>Dashboard
                </a>
                <a href="cierre_mes.php" class="btn btn-win btn-win-outline">
                    <i class="fas fa-calendar-alt me-2"></i>Cierre Mensual
                </a>
                <a href="cierre_anual.php" class="btn btn-win btn-win-outline">
                    <i class="fas fa-calendar me-2"></i>Cierre Anual
                </a>
            </div>
        </div>

<!-- ==================== CARD DE FILTROS MEJORADA ==================== -->
<div class="win-card mb-4">
    <div class="win-card-header">
        <div class="d-flex align-items-center">
            <i class="fas fa-filter me-2 text-accent"></i>
            <h5 class="mb-0 fw-semibold">Filtros de Búsqueda</h5>
        </div>
        <span class="badge bg-secondary bg-opacity-25 text-muted px-3 py-2">
            <i class="fas fa-database me-1"></i> Total: <?php echo count($cierres); ?> registros
        </span>
    </div>
    <div class="win-card-body p-4">
        <div class="row g-4">
            <!-- Columna izquierda: Botones principales -->
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="text-muted small me-2 align-self-center">
                        <i class="fas fa-sliders-h me-1"></i>Tipo:
                    </span>
                    <a href="?tipo=TODOS" 
                       class="btn btn-sm <?php echo ($tipo_filtro == 'TODOS') ? 'btn-win-primary' : 'btn-win-outline'; ?>" 
                       style="<?php echo ($tipo_filtro == 'TODOS') ? 'background: var(--win-accent); border-color: var(--win-accent);' : ''; ?>">
                        <i class="fas fa-asterisk me-1"></i>Todos
                    </a>
                    <a href="?tipo=MES" 
                       class="btn btn-sm <?php echo ($tipo_filtro == 'MES') ? 'btn-win-primary' : 'btn-win-outline'; ?>"
                       style="<?php echo ($tipo_filtro == 'MES') ? 'background: var(--win-accent); border-color: var(--win-accent);' : ''; ?>">
                        <i class="fas fa-calendar-alt me-1"></i>Mensuales
                    </a>
                    <a href="?tipo=ANIO" 
                       class="btn btn-sm <?php echo ($tipo_filtro == 'ANIO') ? 'btn-win-primary' : 'btn-win-outline'; ?>"
                       style="<?php echo ($tipo_filtro == 'ANIO') ? 'background: var(--win-accent); border-color: var(--win-accent);' : ''; ?>">
                        <i class="fas fa-calendar-day me-1"></i>Anuales
                    </a>
                </div>

                <div class="row g-3">
                    <!-- Combo Mes Específico -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <span class="text-muted small me-2" style="min-width: 100px;">
                                <i class="fas fa-calendar-alt me-1 text-info"></i>Mes específico:
                            </span>
                            <?php if (!empty($opciones_meses)): ?>
                            <div class="combo-container flex-grow-1 <?php echo ($tipo_filtro == 'COMBO_MES') ? 'active' : ''; ?>">
                                <form method="GET" action="" id="formComboMes" class="w-100">
                                    <input type="hidden" name="tipo" value="COMBO_MES">
                                    <select name="mes" class="combo-select w-100" onchange="this.form.submit()">
                                        <option value="">Seleccionar mes...</option>
                                        <?php foreach ($opciones_meses as $clave => $texto): ?>
                                            <option value="<?php echo $clave; ?>" 
                                                <?php echo ($mes_filtro == $clave) ? 'selected' : ''; ?>>
                                                🗓️ <?php echo $texto; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">No hay meses disponibles</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Combo Años Completos -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <span class="text-muted small me-2" style="min-width: 100px;">
                                <i class="fas fa-calendar me-1 text-success"></i>Años completos:
                            </span>
                            <?php if (!empty($opciones_anios_completos)): ?>
                            <div class="combo-container flex-grow-1 <?php echo ($tipo_filtro == 'COMBO_ANIO') ? 'active' : ''; ?>">
                                <form method="GET" action="" id="formComboAnio" class="w-100">
                                    <input type="hidden" name="tipo" value="COMBO_ANIO">
                                    <select name="anio" class="combo-select combo-select-anio w-100" onchange="this.form.submit()">
                                        <option value="">Seleccionar año...</option>
                                        <?php foreach ($opciones_anios_completos as $anio => $datos): ?>
                                            <option value="<?php echo $anio; ?>" 
                                                <?php echo ($anio_filtro == $anio) ? 'selected' : ''; ?>
                                                class="option-anio">
                                                <?php echo strip_tags($datos['texto']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">No hay años disponibles</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Info y acciones -->
            <div class="col-lg-4">
                <div class="h-100 d-flex flex-column justify-content-between">
                    <!-- Mostrando registros -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center p-3 rounded" style="background: rgba(var(--win-accent-rgb), 0.08); border: 1px solid rgba(var(--win-accent-rgb), 0.15);">
                            <div class="rounded-circle p-2 me-3" style="background: rgba(var(--win-accent-rgb), 0.15);">
                                <i class="fas fa-eye text-accent"></i>
                            </div>
                            <div>
                                <span class="text-muted small d-block">Mostrando</span>
                                <span class="fw-bold fs-6">
                                    <?php 
                                    if ($tipo_filtro == 'COMBO_MES' && $mes_filtro && isset($opciones_meses[$mes_filtro])) {
                                        echo "1 mes específico";
                                    } elseif ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro) {
                                        echo "año completo " . $anio_filtro;
                                    } elseif ($tipo_filtro == 'MES') {
                                        echo "todos los cierres mensuales";
                                    } elseif ($tipo_filtro == 'ANIO') {
                                        echo "todos los cierres anuales";
                                    } else {
                                        echo "últimos " . $limit . " registros";
                                    }
                                    ?>
                                </span>
                                <span class="text-muted small d-block">
                                    <i class="fas fa-file-alt me-1"></i><?php echo count($cierres); ?> resultados
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Botón limpiar filtros -->
                    <?php if ($tipo_filtro != 'TODOS' || $mes_filtro || $anio_filtro): ?>
                    <div class="text-end">
                        <a href="cierres_realizados.php" class="btn btn-win-outline" style="border-color: var(--win-accent); color: var(--win-accent);">
                            <i class="fas fa-times-circle me-2"></i>Limpiar todos los filtros
                            <span class="badge bg-accent bg-opacity-25 text-accent ms-2 px-2 py-1">
                                <i class="fas fa-filter"></i>
                            </span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros activos (chips) -->
        <?php if ($tipo_filtro != 'TODOS' || $mes_filtro || $anio_filtro): ?>
        <div class="mt-4 pt-3 border-top border-secondary" style="border-color: var(--win-border-color) !important;">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="text-muted small me-2">
                    <i class="fas fa-tags me-1"></i>Filtros activos:
                </span>
                
                <?php if ($tipo_filtro == 'MES'): ?>
                <span class="badge-pill px-3 py-2" style="background: rgba(13, 110, 253, 0.15); color: #3d8bfd; border: 1px solid rgba(13, 110, 253, 0.3);">
                    <i class="fas fa-calendar-alt me-1"></i>Tipo: Mensuales
                    <a href="?tipo=TODOS" class="ms-2 text-decoration-none" style="color: #3d8bfd;">×</a>
                </span>
                <?php endif; ?>
                
                <?php if ($tipo_filtro == 'ANIO'): ?>
                <span class="badge-pill px-3 py-2" style="background: rgba(25, 135, 84, 0.15); color: #20c997; border: 1px solid rgba(25, 135, 84, 0.3);">
                    <i class="fas fa-calendar-day me-1"></i>Tipo: Anuales
                    <a href="?tipo=TODOS" class="ms-2 text-decoration-none" style="color: #20c997;">×</a>
                </span>
                <?php endif; ?>
                
                <?php if ($tipo_filtro == 'COMBO_MES' && $mes_filtro && isset($opciones_meses[$mes_filtro])): ?>
                <span class="badge-pill px-3 py-2" style="background: rgba(var(--win-accent-rgb), 0.15); color: var(--win-accent); border: 1px solid rgba(var(--win-accent-rgb), 0.3);">
                    <i class="fas fa-calendar-alt me-1"></i>Mes: <?php echo $opciones_meses[$mes_filtro]; ?>
                    <a href="cierres_realizados.php" class="ms-2 text-decoration-none" style="color: var(--win-accent);">×</a>
                </span>
                <?php endif; ?>
                
                <?php if ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro && isset($opciones_anios_completos[$anio_filtro])): ?>
                <span class="badge-pill px-3 py-2" style="background: rgba(25, 135, 84, 0.15); color: #20c997; border: 1px solid rgba(25, 135, 84, 0.3);">
                    <i class="fas fa-calendar me-1"></i>Año: <?php echo $anio_filtro; ?>
                    <?php if ($opciones_anios_completos[$anio_filtro]['tiene_anual']): ?>
                        <span class="ms-1">(incluye anual)</span>
                    <?php endif; ?>
                    <a href="cierres_realizados.php" class="ms-2 text-decoration-none" style="color: #20c997;">×</a>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reemplaza TODO el bloque de filtros actual (desde "Filtros y Estadísticas rápidas" hasta antes de "Estadísticas del año") 
     con este nuevo código -->
        
<!-- Estadísticas del año cuando se filtra por año -->
<?php if ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro && isset($opciones_anios_completos[$anio_filtro])): 
    $datos_anio = $opciones_anios_completos[$anio_filtro];
    
    // ============ CORREGIDO: NO sumar el cierre anual como facturas adicionales ============
    $cierres_mensuales = 0;
    $total_facturas_anio = 0;
    $total_importe_anio = 0;
    $tiene_cierre_anual = false;
    
    foreach ($cierres as $cierre) {
        if ($cierre['periodo_anio'] == $anio_filtro) {
            if ($cierre['tipo'] == 1) { // Solo sumar cierres mensuales
                $total_facturas_anio += $cierre['total_facturas'];
                $total_importe_anio += $cierre['importe_total'];
                $cierres_mensuales++;
            } elseif ($cierre['tipo'] == 2) { // Detectar si hay cierre anual
                $tiene_cierre_anual = true;
                // NO SUMAR el cierre anual al total de facturas
                // $total_facturas_anio += $cierre['total_facturas']; ← ELIMINADO
                // $total_importe_anio += $cierre['importe_total']; ← ELIMINADO
            }
        }
    }
    
    // Obtener el importe del cierre anual por separado (solo para mostrar)
    $importe_cierre_anual = 0;
    if ($tiene_cierre_anual) {
        foreach ($cierres as $cierre) {
            if ($cierre['periodo_anio'] == $anio_filtro && $cierre['tipo'] == 2) {
                $importe_cierre_anual = $cierre['importe_total'];
                break;
            }
        }
    }
?>
<div class="estadisticas-anio">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar me-2 text-accent"></i>
            Resumen del Año <?php echo $anio_filtro; ?>
        </h5>
        <?php if ($tiene_cierre_anual): ?>
        <span class="badge bg-success bg-opacity-25 text-success px-3 py-2">
            <i class="fas fa-check-circle me-1"></i> Cierre Anual Realizado
        </span>
        <?php else: ?>
        <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-2">
            <i class="fas fa-exclamation-triangle me-1"></i> Cierre Anual Pendiente
        </span>
        <?php endif; ?>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <div class="estadistica-item">
                <span><i class="fas fa-calendar-alt text-info me-2"></i>Cierres Mensuales:</span>
                <span class="fw-bold"><?php echo $cierres_mensuales; ?> / <?php echo count($datos_anio['meses_con_cierre']); ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="estadistica-item">
                <span><i class="fas fa-calendar-star text-success me-2"></i>Cierre Anual:</span>
                <span class="fw-bold <?php echo $datos_anio['tiene_anual'] ? 'text-success' : 'text-warning'; ?>">
                    <?php echo $datos_anio['tiene_anual'] ? 'Sí' : 'No'; ?>
                </span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="estadistica-item">
                <span><i class="fas fa-file-invoice-dollar text-accent me-2"></i>Total Facturas:</span>
                <span class="fw-bold"><?php echo number_format($total_facturas_anio); ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="estadistica-item">
                <span><i class="fas fa-money-bill-wave text-accent me-2"></i>Importe Total:</span>
                <span class="fw-bold">$<?php echo number_format($total_importe_anio, 2); ?></span>
            </div>
        </div>
    </div>
    
    <?php if ($tiene_cierre_anual): ?>
    <div class="row mt-2">
        <div class="col-12">
            <div class="estadistica-item" style="border-top: 1px dashed rgba(var(--win-accent-rgb), 0.3); padding-top: 10px;">
                <span class="text-success">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Cierre Anual Consolidado:</strong>
                </span>
                <span class="fw-bold text-success">
                    $<?php echo number_format($importe_cierre_anual, 2); ?>
                    <small class="text-muted ms-2">(suma de todos los meses)</small>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($datos_anio['meses_con_cierre'])): ?>
    <div class="mt-3 pt-2 border-top border-secondary border-opacity-25">
        <span class="text-muted small">
            <i class="fas fa-calendar-check me-1 text-info"></i>
            Meses cerrados: 
            <?php 
            $meses_nombres = array_map(function($mes) use ($meses_abrev) {
                return '<span class="badge bg-info bg-opacity-15 text-info me-1 px-2 py-1" style="border-radius: 4px;">' . $meses_abrev[$mes] . '</span>';
            }, $datos_anio['meses_con_cierre']);
            echo implode(' ', $meses_nombres);
            ?>
        </span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
        
        <!-- Indicador de filtro activo -->
        <?php if (($tipo_filtro == 'COMBO_MES' && $mes_filtro && isset($opciones_meses[$mes_filtro])) || 
                  ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro && isset($opciones_anios_completos[$anio_filtro]))): ?>
        <div class="alert alert-filtro py-2 px-3 mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-calendar-check me-2 text-accent"></i>
            <div>
                <strong>Filtro aplicado:</strong> 
                <?php if ($tipo_filtro == 'COMBO_MES' && $mes_filtro && isset($opciones_meses[$mes_filtro])): ?>
                    Cierres del mes de 
                    <span class="badge bg-accent bg-opacity-25 text-accent ms-1">
                        <?php echo $opciones_meses[$mes_filtro]; ?>
                    </span>
                <?php elseif ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro && isset($opciones_anios_completos[$anio_filtro])): ?>
                    Todos los cierres del año 
                    <span class="badge bg-accent bg-opacity-25 text-accent ms-1">
                        <?php echo $anio_filtro; ?>
                    </span>
                    <?php 
                    $datos_anio = $opciones_anios_completos[$anio_filtro];
                    if ($datos_anio['tiene_anual']): ?>
                        <span class="badge bg-success bg-opacity-25 text-success ms-1">
                            <i class="fas fa-calendar-star me-1"></i>Incluye cierre anual
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($datos_anio['meses_con_cierre'])): ?>
                        <span class="badge bg-info bg-opacity-25 text-info ms-1">
                            <i class="fas fa-calendar-alt me-1"></i><?php echo count($datos_anio['meses_con_cierre']); ?> meses
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="cierres_realizados.php" class="ms-2 text-decoration-none small">
                    <i class="fas fa-times me-1"></i>Quitar filtro
                </a>
            </div>
        </div>
        <?php endif; ?>
<!-- Tabla de Datos -->
<?php if (!empty($cierres)): 
    // Calcular totales separados
    $total_facturas_mensual = 0;
    $total_importe_mensual = 0;
    $total_facturas_anual = 0;
    $total_importe_anual = 0;
    $total_registros_mensual = 0;
    $total_registros_anual = 0;
    
    $es_filtro_solo_anual = ($tipo_filtro_int == 2);
    $es_filtro_solo_mensual = ($tipo_filtro_int == 1);
    $es_filtro_todos = ($tipo_filtro == 'TODOS' || $tipo_filtro == '');
    $es_combo_anio = ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro);
    $es_combo_mes = ($tipo_filtro == 'COMBO_MES' && $mes_filtro);
    
    // Separar los totales por tipo
    foreach ($cierres as $cierre) {
        if ($cierre['tipo'] == 1) { // CAMBIO: Comparar con 1 en lugar de 'MES'
            $total_facturas_mensual += $cierre['total_facturas'];
            $total_importe_mensual += $cierre['importe_total'];
            $total_registros_mensual++;
        } else { // tipo == 2 (anual)
            $total_facturas_anual += $cierre['total_facturas'];
            $total_importe_anual += $cierre['importe_total'];
            $total_registros_anual++;
        }
    }
    
    // Determinar qué totales mostrar según el filtro
    $mostrar_totales_separados = $es_filtro_todos;
    $mostrar_solo_mensual = $es_filtro_solo_mensual || $es_combo_mes;
    $mostrar_solo_anual = $es_filtro_solo_anual;
    $mostrar_combo_anio_ajuste = $es_combo_anio;
?>
    <div class="win-card">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Período</th>
                        <th>Usuario</th>
                        <th class="text-center">Facturas</th>
                        <th class="text-end">Importe Total</th>
                        <th>Fecha Ejecución</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cierres as $cierre): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-accent">#<?php echo $cierre['id']; ?></span>
                            </td>
                            <td>
                                <?php if ($cierre['tipo'] == 1): // CAMBIO: Comparar con 1 en lugar de 'MES' ?>
                                    <span class="badge-pill badge-mes"><i class="fas fa-calendar-day me-1"></i> Mensual</span>
                                <?php else: ?>
                                    <span class="badge-pill badge-anio"><i class="fas fa-calendar me-1"></i> Anual</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold">
                                <?php if ($cierre['tipo'] == 1): // CAMBIO: Comparar con 1 en lugar de 'MES' ?>
                                    <?php echo $meses_abrev[$cierre['periodo_mes']] . ' ' . $cierre['periodo_anio']; ?>
                                <?php else: ?>
                                    <?php if ($es_filtro_solo_anual || $es_filtro_todos): ?>
                                        Año <?php echo $cierre['periodo_anio']; ?>
                                    <?php else: ?>
                                        <span class="text-success">Año <?php echo $cierre['periodo_anio']; ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center me-2" style="width:24px; height:24px;">
                                        <i class="fas fa-user fa-xs"></i>
                                    </div>
                                    <?php echo htmlspecialchars($cierre['usuario_nombre']); ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill border border-secondary border-opacity-25 px-3">
                                    <?php echo $cierre['total_facturas']; ?>
                                </span>
                            </td>
                            <td class="text-end text-white">
                                <?php if ($cierre['tipo'] == 2 && !$es_filtro_solo_anual && !$es_filtro_todos): // CAMBIO: Comparar con 2 en lugar de 'ANIO' ?>
                                    <span class="text-success fw-bold">$<?php echo number_format($cierre['importe_total'], 2); ?></span>
                                <?php else: ?>
                                    $<?php echo number_format($cierre['importe_total'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                    <?php 
                                        $fecha = new DateTime($cierre['fecha_ejecucion']);
                                        echo $fecha->format('d/m/Y h:i:s A');
                                    ?>
                            </td>
                            <td class="text-center">
                                <a href="reporte_cierre.php?id=<?php echo $cierre['id']; ?>" 
                                   class="btn-icon-sm"
                                   title="Ver Reporte Detallado"
                                   target="_blank">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- ============ FILTRO: TODOS ============ -->
                    <?php if ($mostrar_totales_separados): ?>
                    <!-- Fila TOTAL MENSUAL -->
                    <tr style="background: rgba(13, 110, 253, 0.1); border-top: 2px solid #3d8bfd;">
                        <td colspan="4" class="text-end fw-bold text-info" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar-alt me-2"></i>TOTAL MENSUAL:
                        </td>
                        <td class="text-center fw-bold text-info" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-info bg-opacity-25 text-info rounded-pill border border-info border-opacity-50 px-3 py-2">
                                <?php echo $total_facturas_mensual; ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-info" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format($total_importe_mensual, 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center text-info" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar-day me-1"></i><?php echo $total_registros_mensual; ?> cierres mensuales
                        </td>
                    </tr>
                    
                    <!-- Fila TOTAL ANUAL -->
                    <tr style="background: rgba(25, 135, 84, 0.1);">
                        <td colspan="4" class="text-end fw-bold text-success" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar-star me-2"></i>TOTAL ANUAL CONSOLIDADO:
                        </td>
                        <td class="text-center fw-bold text-success" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-success bg-opacity-25 text-success rounded-pill border border-success border-opacity-50 px-3 py-2">
                                <?php echo $total_facturas_anual; ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-success" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format($total_importe_anual, 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center text-success" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar me-1"></i><?php echo $total_registros_anual; ?> cierres anuales
                        </td>
                    </tr>
                    
                    <!-- ============ FILTRO: SOLO MENSUAL ============ -->
                    <?php elseif ($mostrar_solo_mensual): ?>
                    <tr style="background: var(--win-bg-tertiary); border-top: 2px solid var(--win-accent);">
                        <td colspan="4" class="text-end fw-bold" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar-alt me-2 text-accent"></i>TOTAL MENSUAL:
                        </td>
                        <td class="text-center fw-bold" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-accent bg-opacity-25 text-accent rounded-pill border border-accent border-opacity-50 px-3 py-2">
                                <?php echo $total_facturas_mensual; ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-white" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format($total_importe_mensual, 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center" style="padding: 1rem 1.5rem;">
                            <?php echo $total_registros_mensual; ?> cierres mensuales
                        </td>
                    </tr>
                    
                    <!-- ============ FILTRO: SOLO ANUAL ============ -->
                    <?php elseif ($mostrar_solo_anual): ?>
                    <tr style="background: var(--win-bg-tertiary); border-top: 2px solid var(--win-accent);">
                        <td colspan="4" class="text-end fw-bold" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar-star me-2 text-success"></i>TOTAL ANUAL:
                        </td>
                        <td class="text-center fw-bold" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-success bg-opacity-25 text-success rounded-pill border border-success border-opacity-50 px-3 py-2">
                                <?php echo $total_facturas_anual; ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-success" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format($total_importe_anual, 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center" style="padding: 1rem 1.5rem;">
                            <?php echo $total_registros_anual; ?> cierres anuales
                        </td>
                    </tr>
                    
                    <!-- ============ FILTRO: COMBO AÑO (con ajuste) ============ -->
                    <?php elseif ($mostrar_combo_anio_ajuste): ?>
                    <!-- Fila TOTAL MENSUAL (ajustado) -->
                    <tr style="background: var(--win-bg-tertiary); border-top: 2px solid var(--win-accent);">
                        <td colspan="4" class="text-end fw-bold" style="padding: 1rem 1.5rem;">
                            <span class="text-warning" title="El cierre anual incluye la suma de todos los meses">
                                <i class="fas fa-exclamation-triangle me-1"></i>TOTAL MENSUAL:
                            </span>
                        </td>
                        <td class="text-center fw-bold" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-accent bg-opacity-25 text-accent rounded-pill border border-accent border-opacity-50 px-3 py-2">
                                <?php echo $total_facturas_mensual; ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-white" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format($total_importe_mensual, 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center" style="padding: 1rem 1.5rem;">
                            <?php 
                            if ($total_registros_anual > 0) {
                                echo $total_registros_mensual . ' meses + ' . $total_registros_anual . ' anual';
                            } else {
                                echo $total_registros_mensual . ' meses';
                            }
                            ?>
                        </td>
                    </tr>
                    
                    <!-- Fila adicional para mostrar el total anual si existe -->
                    <?php if ($total_registros_anual > 0): ?>
                    <tr style="background: rgba(25, 135, 84, 0.1);">
                        <td colspan="4" class="text-end fw-bold text-success" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-calendar-star me-2"></i>TOTAL ANUAL CONSOLIDADO:
                        </td>
                        <td class="text-center fw-bold text-success" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-success bg-opacity-25 text-success rounded-pill border border-success border-opacity-50 px-3 py-2">
                                <?php echo $total_facturas_anual; ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-success" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format($total_importe_anual, 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center text-success" style="padding: 1rem 1.5rem;">
                            <i class="fas fa-info-circle me-1"></i>Incluye todos los meses
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- ============ OTROS FILTROS ============ -->
                    <?php else: ?>
                    <tr style="background: var(--win-bg-tertiary); border-top: 2px solid var(--win-accent);">
                        <td colspan="4" class="text-end fw-bold" style="padding: 1rem 1.5relativo;">
                            <i class="fas fa-calculator me-2 text-accent"></i>TOTALES:
                        </td>
                        <td class="text-center fw-bold" style="padding: 1rem 1.5rem;">
                            <span class="badge bg-accent bg-opacity-25 text-accent rounded-pill border border-accent border-opacity-50 px-3 py-2">
                                <?php echo ($total_facturas_mensual + $total_facturas_anual); ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-white" style="padding: 1rem 1.5rem;">
                            $<?php echo number_format(($total_importe_mensual + $total_importe_anual), 2); ?>
                        </td>
                        <td colspan="2" class="text-muted small text-center" style="padding: 1rem 1.5rem;">
                            <?php 
                            $total_registros = count($cierres);
                            if ($total_registros_anual > 0 && $total_registros_mensual > 0) {
                                echo $total_registros_mensual . ' meses + ' . $total_registros_anual . ' anuales';
                            } elseif ($total_registros_anual > 0) {
                                echo $total_registros_anual . ' cierres anuales';
                            } elseif ($total_registros_mensual > 0) {
                                echo $total_registros_mensual . ' cierres mensuales';
                            } else {
                                echo $total_registros . ' registros';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="win-card text-center py-5">
        <div class="mb-3">
            <i class="fas fa-history fa-4x text-muted opacity-25"></i>
        </div>
        <h4 class="text-muted">No se encontraron cierres</h4>
        <p class="text-muted small">
            <?php if ($tipo_filtro == 'COMBO_MES' && $mes_filtro): ?>
                No hay cierres mensuales registrados para el mes seleccionado.
            <?php elseif ($tipo_filtro == 'COMBO_ANIO' && $anio_filtro): ?>
                No hay cierres registrados para el año <?php echo $anio_filtro; ?>.
            <?php elseif ($tipo_filtro == 'ANIO'): ?>
                No hay cierres anuales registrados.
            <?php elseif ($tipo_filtro == 'MES'): ?>
                No hay cierres mensuales registrados.
            <?php else: ?>
                No hay registros que coincidan con el filtro seleccionado.
            <?php endif; ?>
        </p>
        <div class="d-flex justify-content-center gap-2 mt-3">
            <a href="cierre_mes.php" class="btn btn-win btn-win-primary">
                <i class="fas fa-calendar-alt me-2"></i>Cierre Mensual
            </a>
            <a href="cierre_anual.php" class="btn btn-win btn-win-outline">
                <i class="fas fa-calendar me-2"></i>Cierre Anual
            </a>
        </div>
    </div>
<?php endif; ?>
    </div>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script>
        // Script para mejorar la experiencia de los combos
        document.addEventListener('DOMContentLoaded', function() {
            const comboSelects = document.querySelectorAll('.combo-select');
            
            comboSelects.forEach(function(select) {
                // Cambiar estilo cuando se enfoca
                select.addEventListener('focus', function() {
                    this.parentElement.classList.add('active');
                });
                
                select.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('active');
                    }
                });
                
                // Si ya tiene valor al cargar, marcar como activo
                if (select.value) {
                    select.parentElement.classList.add('active');
                }
            });
            
            // Detectar cambios en los combos para manejar conflictos
            const formComboMes = document.getElementById('formComboMes');
            const formComboAnio = document.getElementById('formComboAnio');
            
            if (formComboMes && formComboAnio) {
                const mesSelect = formComboMes.querySelector('select');
                const anioSelect = formComboAnio.querySelector('select');
                
                // Cuando se selecciona un mes, limpiar el año
                mesSelect.addEventListener('change', function() {
                    if (this.value) {
                        anioSelect.value = '';
                    }
                });
                
                // Cuando se selecciona un año, limpiar el mes
                anioSelect.addEventListener('change', function() {
                    if (this.value) {
                        mesSelect.value = '';
                    }
                });
            }
            
            // Actualizar automáticamente el título de la página con el filtro activo
            updatePageTitle();
            
            function updatePageTitle() {
                const urlParams = new URLSearchParams(window.location.search);
                const tipo = urlParams.get('tipo');
                const mes = urlParams.get('mes');
                const anio = urlParams.get('anio');
                
                let filterText = '';
                
                if (tipo === 'COMBO_MES' && mes) {
                    const mesParts = mes.split('-');
                    if (mesParts.length === 2) {
                        const mesesNombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                                             'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                        const mesNum = parseInt(mesParts[1]);
                        filterText = ` - ${mesesNombres[mesNum]} ${mesParts[0]}`;
                    }
                } else if (tipo === 'COMBO_ANIO' && anio) {
                    filterText = ` - Año ${anio}`;
                } else if (tipo === 'MES') {
                    filterText = ' - Mensuales';
                } else if (tipo === 'ANIO') {
                    filterText = ' - Anuales';
                }
                
                if (filterText) {
                    document.title = `Historial de Cierres${filterText} - SISFACT PDL VISIONES`;
                }
            }
        });
// Mejorar la experiencia de los combos
document.addEventListener('DOMContentLoaded', function() {
    // Auto-resaltar el combo activo
    const urlParams = new URLSearchParams(window.location.search);
    const tipo = urlParams.get('tipo');
    
    if (tipo === 'COMBO_MES' || tipo === 'COMBO_ANIO') {
        const activeCombo = document.querySelector('.combo-container.active');
        if (activeCombo) {
            activeCombo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // Prevenir envío doble de formularios
    const forms = document.querySelectorAll('form[id^="formCombo"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const select = this.querySelector('select');
            if (!select.value) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Agregar tooltips personalizados
    const filterChips = document.querySelectorAll('.badge-pill a');
    filterChips.forEach(chip => {
        chip.setAttribute('title', 'Eliminar este filtro');
    });
});
    </script>
</body>
</html>