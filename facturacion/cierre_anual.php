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
    
    // ==================== CONTROL DE ROLES (Bloque de Seguridad) ====================
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
    
    // Roles permitidos: Admin(1), Super(4), Soft(5)
    if ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 4 && $usuario['rol_id'] != 5) {
        $rol_actual = $usuario['rol_nombre'] ?? 'Usuario';
        
        // UI de Acceso Denegado
        echo '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Acceso Denegado</title>
            <script src="js/sweetalert211.js"></script>
            <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
            <style>body { background: #0f0f1a; font-family: sans-serif; }</style>
        </head>
        <body>
            <script>
            const darkTheme = Swal.mixin({
                background: "#1e1e2d", color: "#e1e1e6",
                confirmButtonColor: "#dc3545",
                customClass: { popup: "animated fadeInDown" }
            });
            darkTheme.fire({
                icon: "error",
                title: "Acceso Restringido",
                html: `No tienes permisos para realizar Cierres Anuales.<br>Rol actual: <b>' . $rol_actual . '</b>`,
                allowOutsideClick: false
            }).then(() => { window.location.href = "facturas.php"; });
            </script>
        </body></html>';
        exit();
    }
    
} catch (Exception $e) {
    header('Location: index.php');
    exit();
}

// ==================== CONFIGURACIÓN INICIAL ====================
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';

$periodo_actual = obtenerPeriodoOperaciones($db);
$mes_actual = $periodo_actual['mes'];
$anio_actual = $periodo_actual['anio'];
$fecha_inicio = $periodo_actual['fecha_inicio'];

$meses_completos = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$mes_actual_nombre = $meses_completos[$mes_actual];

// Variables
$success = '';
$error = '';
$anios_disponibles = [];
$anio_seleccionado = null;
$verificacion = null;
$estadisticas = null;
$cierre_realizado = false;
$meses_estado = [];
$sin_operaciones = false;
$info_cierre = null;

try {
    // Obtener años disponibles
    $anios_disponibles = obtenerAniosDisponiblesCierre($db);
    
    // Procesar selección/consulta
    $anio_seleccionado = $_POST['anio_consultar'] ?? ($_POST['anio_cerrar'] ?? null);
    
    if ($anio_seleccionado) {
        
        if (!verificarOperacionesEnAnio($anio_seleccionado, $db)) {
            $sin_operaciones = true;
            $anio_provisional = $anio_seleccionado;
            $anio_seleccionado = null;
        } else {
            
            // VERIFICAR SI EL AÑO YA ESTÁ CERRADO
            $sql_ya_cerrado = "SELECT id, fecha_ejecucion FROM historico_cierres 
                              WHERE tipo = 2 AND periodo_anio = :anio 
                              LIMIT 1";
            $stmt_ya_cerrado = $db->prepare($sql_ya_cerrado);
            $stmt_ya_cerrado->execute(['anio' => $anio_seleccionado]);
            $ya_cerrado = $stmt_ya_cerrado->fetch(PDO::FETCH_ASSOC);
            
            if ($ya_cerrado) {
                $cierre_realizado = true;
                $verificacion = ['puede_cerrar' => false, 'mensaje' => "Este año ya fue cerrado."];
                
                // Obtener información del cierre para mostrarla
$sql_info = "SELECT h.*, 
                    CONCAT(u.nombre, ' ', u.apellidos) as usuario_completo
            FROM historico_cierres h
            LEFT JOIN clasif_usuarios u ON h.usuario_id = u.id
            WHERE h.id = :id";
                $stmt_info = $db->prepare($sql_info);
                $stmt_info->execute(['id' => $ya_cerrado['id']]);
                $info_cierre = $stmt_info->fetch(PDO::FETCH_ASSOC);
            } else {
                // 1. OBTENER ESTADÍSTICAS
                $sql_stats = "SELECT 
                            COUNT(*) as total_facturas,
                            COALESCE(SUM(total_general), 0) as importe_total,
                            COUNT(CASE 
                                WHEN fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00' THEN 1 
                                WHEN estado = 'PAGADA' THEN 1 
                                ELSE NULL 
                            END) as cant_pagadas,
                            COUNT(CASE 
                                WHEN estado IN ('CERRADA', 'PAGADA', 'CONTABILIZADA') THEN 1
                                WHEN fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00' THEN 1
                                ELSE NULL 
                            END) as cant_contabilizadas,
                            COUNT(CASE WHEN estado = 'CERRADA' THEN 1 END) as cant_cerradas
                            FROM tbl_fact 
                            WHERE YEAR(fecha_emision) = :anio 
                            AND estado != 'ANULADA'";
                
                $stmt_stats = $db->prepare($sql_stats);
                $stmt_stats->execute(['anio' => $anio_seleccionado]);
                $estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

                // 2. VERIFICAR SI SE PUEDE CERRAR
                $verificacion = verificarCierreAnio($anio_seleccionado, $db);
                
                // 3. OBTENER ESTADO POR MESES
                $sql_meses = "SELECT 
                            MONTH(fecha_emision) as mes,
                            COUNT(*) as total,
                            COALESCE(SUM(total_general), 0) as importe,
                            COUNT(CASE WHEN estado = 'CERRADA' THEN 1 END) as cerradas,
                            COUNT(CASE WHEN (estado = 'PAGADA' OR (fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00')) AND estado != 'CERRADA' THEN 1 END) as pagadas, 
                            COUNT(CASE WHEN estado = 'PENDIENTE' THEN 1 END) as pendientes
                            FROM tbl_fact 
                            WHERE YEAR(fecha_emision) = :anio
                            GROUP BY MONTH(fecha_emision)
                            ORDER BY MONTH(fecha_emision)";
                $stmt = $db->prepare($sql_meses);
                $stmt->execute(['anio' => $anio_seleccionado]);
                $meses_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // 4. PROCESAR EL FORMULARIO DE CIERRE
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['realizar_cierre']) && !$cierre_realizado) {
                $confirmado = $_POST['confirmado'] ?? false;
                $observaciones = trim($_POST['observaciones'] ?? '');
                
                if (!$confirmado) {
                    $error = "Debe confirmar el cierre marcando la casilla de verificación.";
                } else {
                    // VERIFICAR TOKEN CSRF
                    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token_cierre'] ?? '')) {
                        $error = "Error de seguridad: Token inválido. Por favor, recargue la página.";
                    } else {
                        // VERIFICAR SI YA SE REALIZÓ ESTE CIERRE (protección doble)
                        $sql_check = "SELECT id FROM historico_cierres 
                                    WHERE tipo = 2 AND periodo_anio = :anio 
                                    LIMIT 1";
                        $stmt_check = $db->prepare($sql_check);
                        $stmt_check->execute(['anio' => $anio_seleccionado]);
                        $cierre_existente = $stmt_check->fetch();
                        
                        if ($cierre_existente) {
                            $error = "El cierre anual del año $anio_seleccionado ya fue realizado anteriormente.";
                        } else {
                            // Realizar el cierre
                            $resultado = realizarCierreAnio(
                                $anio_seleccionado, 
                                $_SESSION['usuario_id'], 
                                $_SESSION['usuario_nombre'], 
                                $db,
                                $observaciones
                            );
                            
                            if ($resultado['success']) {
                                // GUARDAR EN SESIÓN QUE SE REALIZÓ EL CIERRE (para SweetAlert)
                                $_SESSION['cierre_anual_exitoso'] = [
                                    'anio' => $anio_seleccionado,
                                    'mensaje' => $resultado['mensaje'] ?? "Cierre anual realizado exitosamente"
                                ];
                                
                                // Limpiar el token después de usarlo
                                unset($_SESSION['csrf_token_cierre']);
                                
                                // Redirigir para evitar reenvío al refrescar
								echo '<script>
									window.location.replace("' . strtok($_SERVER["REQUEST_URI"], '?') . '?cierre_exitoso=1");
								</script>';
                                exit();
                            } else {
                                $error = $resultado['mensaje'];
                            }
                        }
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

$meses_nombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre Anual - SISFACT PDL VISIONES</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
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
            --win-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            --win-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        h1, h2, h3, h4, h5 { font-weight: 600; letter-spacing: -0.5px; }
        .text-muted { color: var(--win-text-secondary) !important; }
        .text-accent { color: var(--win-accent) !important; }

        /* --- Tarjetas Modernas --- */
        .win-card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: var(--win-transition);
        }
        
        .win-card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .win-card-body { padding: 1.5rem; }

        /* --- Widgets KPI --- */
        .stat-widget {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            height: 100%;
            transition: var(--win-transition);
        }
        .stat-widget:hover { transform: translateY(-3px); box-shadow: var(--win-shadow); }
        .stat-widget::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: currentColor;
        }
        .stat-widget-icon {
            position: absolute; right: -10px; bottom: -15px; font-size: 5rem; opacity: 0.05; transform: rotate(-15deg);
        }
        .stat-value { font-size: 2.2rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.85rem; color: var(--win-text-secondary); text-transform: uppercase; font-weight: 600; }

        .widget-primary { color: var(--win-accent); background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(var(--win-accent-rgb), 0.05)); }
        .widget-success { color: #28c76f; background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(40, 199, 111, 0.05)); }
        .widget-info { color: #00cfe8; background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(0, 207, 232, 0.05)); }
        .widget-neutral { color: var(--win-text-secondary); background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(255, 255, 255, 0.02)); }

        /* --- Grid de Meses --- */
        .mes-card {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            padding: 1rem;
            text-align: center;
            transition: var(--win-transition);
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        .mes-card:hover { transform: translateY(-2px); border-color: var(--win-text-secondary); }
        
        .mes-indicator {
            width: 100%; height: 4px; position: absolute; top: 0; left: 0;
        }
        
        .mes-cerrado .mes-indicator { background: #28c76f; }
        .mes-cerrado { border-color: rgba(40, 199, 111, 0.2); }
        
        .mes-pendiente .mes-indicator { background: #ff4d4f; }
        .mes-pendiente { border-color: rgba(255, 77, 79, 0.2); }
        
        .mes-vacio .mes-indicator { background: #4b4b4b; }
        
        /* --- Botones --- */
        .btn-win {
            border-radius: 8px; padding: 0.5rem 1.2rem; font-weight: 500; transition: var(--win-transition);
            border: 1px solid transparent;
        }
        .btn-win-primary { background: var(--win-accent); color: white; box-shadow: 0 4px 12px rgba(var(--win-accent-rgb), 0.3); }
        .btn-win-primary:hover { filter: brightness(110%); transform: translateY(-2px); color: white; }
        .btn-win-outline { background: transparent; border-color: var(--win-border-color); color: var(--win-text-primary); }
        .btn-win-outline:hover { background: var(--win-bg-elevated); border-color: var(--win-text-secondary); }

        /* --- Checklist --- */
        .checklist-item {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            padding: 1rem;
            border-radius: var(--win-radius);
            margin-bottom: 0.8rem;
            cursor: pointer;
            transition: var(--win-transition);
            display: flex; align-items: start;
        }
        .checklist-item:hover { border-color: var(--win-text-secondary); background: var(--win-bg-elevated); }
        .checklist-item .form-check-input {
            margin-top: 0.25em; margin-right: 1rem; background-color: transparent; border-color: var(--win-text-secondary);
            width: 1.2em; height: 1.2em;
        }
        .checklist-item .form-check-input:checked { background-color: var(--win-accent); border-color: var(--win-accent); }
        
        /* --- Badges --- */
        .badge-success-custom {
            background: rgba(40, 199, 111, 0.1);
            color: #28c76f;
            border: 1px solid rgba(40, 199, 111, 0.2);
        }
        
        /* --- Animaciones --- */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translate3d(0, -30px, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }
        
        .animated {
            animation-duration: 0.5s;
            animation-fill-mode: both;
        }
        
        .fadeInDown {
            animation-name: fadeInDown;
        }
/* Estilos adicionales para SweetAlert */
.swal2-container {
    z-index: 9999 !important;
}

.swal2-popup {
    transition: all 0.2s ease !important;
    border: 1px solid var(--win-border-color) !important;
    box-shadow: var(--win-shadow) !important;
}

.swal2-title {
    color: #fff !important;
    font-weight: 600 !important;
}

.swal2-html-container {
    color: #e1e1e6 !important;
}

.swal2-confirm.btn-danger {
    background: #dc3545 !important;
    color: white !important;
}

.swal2-confirm.btn-danger:hover {
    background: #c82333 !important;
    transform: translateY(-2px);
}

.swal2-cancel.btn-secondary {
    background: #4b4b4b !important;
    color: white !important;
}

.swal2-cancel.btn-secondary:hover {
    background: #5a5a5a !important;
    transform: translateY(-2px);
}

.btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%) !important;
}

/* Animaciones */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translate3d(0, -30px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.animated {
    animation-duration: 0.4s;
    animation-fill-mode: both;
}

.fadeInDown {
    animation-name: fadeInDown;
}

/* Spinner personalizado */
.spinner-border.text-primary {
    color: var(--win-accent) !important;
}

/* Hover effects */
.swal2-confirm:hover, .swal2-cancel:hover {
    transition: all 0.2s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 pb-3 border-bottom border-secondary" style="border-color: var(--win-border-color) !important;">
            <div>
                <h1 class="h2 mb-1">
                    <span class="text-accent me-2"><i class="fas fa-calendar"></i></span>
                    Cierre Anual
                </h1>
                <p class="text-muted mb-0">
                    Período: <strong class="text-white"><?php echo $mes_actual_nombre . ' ' . $anio_actual; ?></strong>
                    <span class="mx-2">•</span> 
                    Inicio: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?>
                    <span class="mx-2">•</span>
                    Año de Operaciones: <strong class="text-white"><?php echo $anio_actual; ?></strong>
                </p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="dashboard.php" class="btn btn-win btn-win-outline">
                    <i class="fas fa-dashboard me-2"></i>Dashboard
                </a>
                <a href="facturas.php" class="btn btn-win btn-win-outline">
                    <i class="fas fa-arrow-left me-2"></i>Facturas
                </a>
                <a href="cierre_mes.php" class="btn btn-win btn-win-outline">
                    <i class="fas fa-calendar-alt me-2"></i>Cierre Mensual
                </a>
                <a href="cierres_realizados.php" class="btn btn-win btn-win-outline">
                    <span class="text-accent me-2"><i class="fas fa-history"></i></span>Historial
                </a>
            </div>
        </div>

        <!-- Mensajes de Éxito/Error -->
        <?php if ($success): ?>
            <div class="win-card border-success" style="background: rgba(40, 199, 111, 0.05);">
                <div class="win-card-body d-flex align-items-center">
                    <div class="me-3 text-success"><i class="fas fa-check-circle fa-2x"></i></div>
                    <div>
                        <h5 class="text-success mb-1">Operación exitosa</h5>
                        <div class="text-muted"><?php echo $success; ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="win-card border-danger" style="background: rgba(255, 77, 79, 0.05);">
                <div class="win-card-body d-flex align-items-center">
                    <div class="me-3 text-danger"><i class="fas fa-exclamation-circle fa-2x"></i></div>
                    <div>
                        <h5 class="text-danger mb-1">Error</h5>
                        <p class="mb-0 text-muted"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Selección de Año -->
        <div class="win-card mb-4">
            <div class="win-card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label text-muted small text-uppercase fw-bold">Seleccionar Año Fiscal</label>
                        <select name="anio_consultar" class="form-select bg-dark text-white border-secondary" style="padding: 0.7rem;" required>
                            <option value="">-- Seleccione --</option>
                            <?php 
                            // Asegurar que el año actual esté en la lista
                            $anios_lista = array_column($anios_disponibles, 'anio');
                            
                            // Agregar el año actual del sistema (PC/server)
                            $anio_actual_sistema = date('Y');
                            $anio_periodo_actual = $anio_actual;
                            
                            if (!in_array($anio_actual_sistema, $anios_lista)) {
                                $anios_lista[] = $anio_actual_sistema;
                            }
                            if (!in_array($anio_periodo_actual, $anios_lista)) {
                                $anios_lista[] = $anio_periodo_actual;
                            }
                            
                            // Ordenar de mayor a menor
                            rsort($anios_lista);
                            
                            // Eliminar duplicados
                            $anios_lista = array_unique($anios_lista);
                            
                            // Mostrar opciones
                            foreach ($anios_lista as $anio_valor):
                                // Determinar qué etiqueta mostrar
                                if ($anio_valor == $anio_actual_sistema && $anio_valor == $anio_periodo_actual) {
                                    $etiqueta_extra = ' (Período Actual)';
                                } elseif ($anio_valor == $anio_actual_sistema) {
                                    $etiqueta_extra = ' (Año Actual Sistema)';
                                } elseif ($anio_valor == $anio_periodo_actual) {
                                    $etiqueta_extra = ' (Período Actual)';
                                } else {
                                    $etiqueta_extra = '';
                                }
                            ?>
                                <option value="<?php echo $anio_valor; ?>"
                                    <?php echo ($anio_seleccionado == $anio_valor) ? 'selected' : ''; ?>>
                                    Año Fiscal <?php echo $anio_valor; ?><?php echo $etiqueta_extra; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-win btn-win-primary w-100 py-2">
                            <i class="fas fa-search me-2"></i>Consultar
                        </button>
                    </div>
                </form>
            </div>
        </div>
<!-- ===== BOTÓN REGRESAR AL DASHBOARD (SIEMPRE VISIBLE AL CARGAR, SE OCULTA AL SELECCIONAR AÑO) ===== -->
<style>
    @keyframes pulse-glow {
        0% { box-shadow: 0 0 0 0 rgba(var(--win-accent-rgb), 0.7); }
        70% { box-shadow: 0 0 0 15px rgba(var(--win-accent-rgb), 0); }
        100% { box-shadow: 0 0 0 0 rgba(var(--win-accent-rgb), 0); }
    }
    
    .btn-success-glow {
        animation: pulse-glow 2s infinite;
        transition: all 0.3s ease;
    }
    
    .btn-success-glow:hover {
        transform: translateY(-5px) scale(1.05);
        animation: none;
    }
    
    .fade-out {
        opacity: 0;
        transform: translateY(-20px);
        pointer-events: none;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
</style>

<div id="botonRegresarContainer" class="text-center mb-5 position-relative">
    
    <div class="mb-3">
        <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-4 py-2 mb-2">
            <i class="fas fa-check-circle me-2"></i>¡Bienvenido a la página de Cierre Anual!
        </span>
    </div>
    
    <a href="dashboard.php" class="btn btn-lg btn-success btn-success-glow px-5 py-3 shadow" 
       style="border-radius: 50px; background: linear-gradient(145deg, #28c76f, #20a85f); border: none;">
        <i class="fas fa-home me-2 fa-lg"></i>
        <span class="fw-bold fs-5">Regresar al Dashboard</span>
        <i class="fas fa-arrow-right ms-2 fa-lg"></i>
    </a>
    
    <p class="text-muted mt-3 small">
        <i class="fas fa-info-circle me-1"></i>
        Este mensaje desaparecerá cuando seleccione un año del combo
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAnio = document.getElementById('anioSelect');
    const botonContainer = document.getElementById('botonRegresarContainer');
    
    // Función para ocultar el botón con animación
    function ocultarBoton() {
        if (botonContainer) {
            botonContainer.classList.add('fade-out');
            // Opcional: eliminar del DOM después de la animación
            setTimeout(() => {
                if (botonContainer) {
                    botonContainer.style.display = 'none';
                }
            }, 300);
        }
    }
    
    // Si ya hay un año seleccionado (por POST), ocultar el botón inmediatamente
    <?php if ($anio_seleccionado): ?>
        ocultarBoton();
    <?php endif; ?>
    
    // Evento cuando el select cambia
    if (selectAnio) {
        selectAnio.addEventListener('change', function() {
            if (this.value !== '') {
                ocultarBoton();
            }
        });
    }
    
    // Evento cuando se envía el formulario
    const formAnio = document.getElementById('formAnio');
    if (formAnio) {
        formAnio.addEventListener('submit', function() {
            ocultarBoton();
        });
    }
});
</script>

<!-- Información del Año -->
<?php if ($anio_seleccionado): ?>
    
    <!-- AÑO CERRADO - BANNER DE INFORMACIÓN -->
    <?php if ($cierre_realizado && isset($info_cierre)): ?>
        <div class="win-card mb-4" style="background: linear-gradient(145deg, rgba(40, 199, 111, 0.1), rgba(0,0,0,0.2)); border-color: #28c76f;">
            <div class="win-card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="rounded-circle bg-success bg-opacity-25 p-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check-circle fa-3x text-success"></i>
                        </div>
                    </div>
                    <div class="col">
                        <h3 class="text-success mb-2">Año Fiscal <?php echo $anio_seleccionado; ?> - Cerrado</h3>
                        <p class="text-muted mb-1">
                            <i class="fas fa-calendar-check me-2"></i> Cierre Realizado: 
                            <strong class="text-warning"><?php echo date('d/m/Y h:i:s A', strtotime($info_cierre['fecha_ejecucion'])); ?> - ID de Usuario: <?php echo htmlspecialchars($info_cierre['usuario_id'] ?? 'Sistema'); ?></strong>
                        </p>
                        <p class="text-muted mb-1">
                            <i class="fas fa-user me-2"></i> Realizado por: 
                            <strong class="text-warning"><?php echo htmlspecialchars($info_cierre['usuario_completo'] ?? 'Sistema'); ?></strong>
                        </p>
                        <p class="text-muted mb-1">
                            <i class="fas fa-money-bill-transfer me-2"></i> Total Facturado: 
                            <strong class=" text-warning">$ <?php echo htmlspecialchars($info_cierre['importe_total'] ?? '0.00'); ?></strong>
                        </p>
                        <p class="text-muted mb-1">
                            <i class="fas fa-id-card me-2"></i> Total de Facturas: 
                            <strong class="text-warning"><?php echo htmlspecialchars($info_cierre['total_facturas'] ?? 0); ?></strong>
                        </p>
                        <?php if (!empty($info_cierre['observaciones'])): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-comment me-2"></i> Observaciones: 
                                 <strong class="text-warning"><?php echo htmlspecialchars($info_cierre['observaciones']); ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-success px-4 py-3 fs-6 rounded-pill">
                            <i class="fas fa-lock me-2"></i> ARCHIVADO
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== BOTÓN REGRESAR AL DASHBOARD ===== -->
        <div class="text-center mb-5">
            <a href="dashboard.php" class="btn btn-lg btn-success px-5 py-3 shadow-lg" style="border-radius: 50px; background: linear-gradient(145deg, #28c76f, #20a85f); border: none;">
                <i class="fas fa-home me-2 fa-lg"></i>
                <span class="fw-bold fs-5">Regresar al Dashboard</span>
            </a>
            <p class="text-muted mt-2 small">
                <a href="cierres_realizados.php?tipo=ANIO" class="text-info text-decoration-none hover:text-accent">
                    <i class="fas fa-history me-1"></i>
                    Haga clic para ver Historial de Cierres Anuales
                </a>
            </p>
        </div>

        <?php endif; ?>
            
            <!-- KPIs - Solo mostrar si hay estadísticas -->
            <?php if (!$cierre_realizado && isset($estadisticas)): ?>
                <div class="row g-4 mb-5">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-widget widget-neutral">
                            <i class="fas fa-file-invoice stat-widget-icon"></i>
                            <div class="stat-value"><?php echo $estadisticas['total_facturas'] ?? 0; ?></div>
                            <div class="stat-label">Total Facturas</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-widget widget-primary">
                            <i class="fas fa-dollar-sign stat-widget-icon"></i>
                            <div class="stat-value">$<?php echo number_format($estadisticas['importe_total'] ?? 0, 2, ',', '.'); ?></div>
                            <div class="stat-label">Importe Total</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-widget widget-success">
                            <i class="fas fa-check-double stat-widget-icon"></i>
                            <div class="stat-value"><?php echo $estadisticas['cant_pagadas'] ?? 0; ?></div>
                            <div class="stat-label">Pagadas</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-widget widget-info">
                            <i class="fas fa-calculator stat-widget-icon"></i>
                            <div class="stat-value"><?php echo $estadisticas['cant_contabilizadas'] ?? 0; ?></div>
                            <div class="stat-label">Contabilizadas</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


<!-- ===== BOTÓN REGRESAR AL DASHBOARD CON EL MISMO ESTILO DE CIERRE_MES ===== -->
<?php if (isset($_GET['cierre_exitoso']) && $_GET['cierre_exitoso'] == 1): ?>
    <style>
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(var(--win-accent-rgb), 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(var(--win-accent-rgb), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--win-accent-rgb), 0); }
        }
        
        .btn-success-glow {
            animation: pulse-glow 2s infinite;
            transition: all 0.3s ease;
        }
        
        .btn-success-glow:hover {
            transform: translateY(-5px) scale(1.05);
            animation: none;
        }
    </style>
    
    <div class="text-center mb-5 position-relative">
        <!-- Efecto de confeti sutil -->
        <div class="position-absolute top-0 start-50 translate-middle-x" style="width: 100%; pointer-events: none;">
            <i class="fas fa-star text-warning opacity-25 position-absolute" style="top: -20px; left: 30%; font-size: 1.5rem;"></i>
            <i class="fas fa-star text-success opacity-25 position-absolute" style="top: -30px; right: 30%; font-size: 2rem;"></i>
            <i class="fas fa-star text-info opacity-25 position-absolute" style="top: -10px; left: 45%; font-size: 1rem;"></i>
        </div>
        
        <div class="mb-3">
            <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-4 py-2 mb-2">
                <i class="fas fa-check-circle me-2"></i>¡Cierre anual completado exitosamente!
            </span>
        </div>
        
        <a href="dashboard.php" class="btn btn-lg btn-success btn-success-glow px-5 py-3 shadow" 
           style="border-radius: 50px; background: linear-gradient(145deg, #28c76f, #20a85f); border: none;">
            <i class="fas fa-home me-2 fa-lg"></i>
            <span class="fw-bold fs-5">Regresar al Dashboard</span>
            <i class="fas fa-arrow-right ms-2 fa-lg"></i>
        </a>
        
        <div class="mt-3">
            <a href="cierres_realizados.php?tipo=ANUAL" class="text-muted text-decoration-none small hover-text-white">
                <i class="fas fa-history me-1"></i>Ver historial de cierres anuales
            </a>
        </div>
    </div>
<?php endif; ?>


            <!-- Grilla de Meses - Solo mostrar si hay datos y año no está cerrado -->
            <?php if (!$cierre_realizado && isset($meses_estado) && !empty($meses_estado)): ?>
                <div class="win-card mb-4">
                    <div class="win-card-header">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-layer-group me-2 text-accent"></i>Estado Mensual
                        </h5>
                        <span class="badge bg-secondary bg-opacity-25 text-white">12 Períodos</span>
                    </div>
                    <div class="win-card-body">
                        <div class="row g-3">
                            <?php 
                            $meses_cerrados = 0;
                            $lista_pendientes = [];

                            for ($mes = 1; $mes <= 12; $mes++):
                                $mes_data = array_filter($meses_estado, fn($m) => $m['mes'] == $mes);
                                $mes_data = $mes_data ? reset($mes_data) : null;
                                
                                $sql = "SELECT id FROM historico_cierres 
                                    WHERE tipo = 1 AND periodo_mes = :mes AND periodo_anio = :anio";
                                $stmt = $db->prepare($sql);
                                $stmt->execute(['mes' => $mes, 'anio' => $anio_seleccionado]);
                                $cerrado = $stmt->rowCount() > 0;
                                
                                if ($cerrado) {
                                    $meses_cerrados++;
                                } else if ($mes_data) {
                                    $lista_pendientes[] = $meses_nombres[$mes];
                                }

                                $clase_estado = $cerrado ? 'mes-cerrado' : ($mes_data ? 'mes-pendiente' : 'mes-vacio');
                            ?>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="mes-card <?php echo $clase_estado; ?>">
                                    <div class="mes-indicator"></div>
                                    <h6 class="text-white mb-2"><?php echo $meses_nombres[$mes]; ?></h6>
                                    
                                    <?php if ($mes_data): ?>
                                        <div class="mb-2">
                                            <div class="fw-bold fs-5 text-white"><?php echo $mes_data['total']; ?></div>
                                            <div class="text-muted small">facturas</div>
                                        </div>
                                        <div class="badge bg-dark bg-opacity-50 text-white-50 border border-secondary mb-2">
                                            $<?php echo number_format($mes_data['importe'], 2, ',', '.'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="py-3 text-muted small fst-italic">Sin movimiento</div>
                                    <?php endif; ?>

                                    <div class="mt-2">
                                        <?php if ($cerrado): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 rounded-pill px-3">CERRADO</span>
                                        <?php elseif($mes_data): ?>
                                            <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 rounded-pill px-3">PENDIENTE</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-muted border border-secondary border-opacity-25 rounded-pill px-3">VACÍO</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Zona de Acción de Cierre - Solo si se puede cerrar -->
            <?php if (!$cierre_realizado && isset($verificacion) && $verificacion['puede_cerrar']): ?>
                
                <?php 
                // Generar token CSRF único para este cierre
                $_SESSION['csrf_token_cierre'] = bin2hex(random_bytes(32));
                ?>
                
                <div class="win-card border-secondary position-relative overflow-hidden">
                    <div style="height: 4px; background: var(--win-accent); width: 100%; position: absolute; top: 0;"></div>
                    <div class="win-card-body">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <h3 class="mb-4 text-white"><i class="fas fa-lock me-2 text-accent"></i>Confirmación de Cierre Anual</h3>
                                
                                <form id="formCierreAnual" method="POST" action="">
                                    <input type="hidden" name="anio_cerrar" value="<?php echo $anio_seleccionado; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token_cierre']; ?>">
                                    <input type="hidden" name="realizar_cierre" value="1">
                                    <input type="hidden" name="confirmado" id="confirmado" value="0">
                                    
                                    <div class="mb-4">
                                        <label class="form-check-label checklist-item">
                                            <input class="form-check-input" type="checkbox" id="confirm1" required>
                                            <div>
                                                <span class="d-block fw-bold text-white">Validación de Meses</span>
                                                <small class="text-muted">Confirmo que los 12 meses del año <?php echo $anio_seleccionado; ?> están cerrados correctamente.</small>
                                            </div>
                                        </label>
                                        
                                        <label class="form-check-label checklist-item">
                                            <input class="form-check-input" type="checkbox" id="confirm2" required>
                                            <div>
                                                <span class="d-block fw-bold text-danger">Cierre Definitivo</span>
                                                <small class="text-muted">Entiendo que esta acción archiva el año fiscal y no es reversible.</small>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label text-muted small text-uppercase fw-bold">Observaciones (Opcional)</label>
                                        <textarea name="observaciones" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Ej: Cierre anual sin incidencias..."></textarea>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="col-lg-5 text-center ps-lg-5 border-start border-secondary">
                                <div class="mb-4">
                                    <div class="display-1 fw-bold text-warning mb-0 opacity-100"><?php echo $anio_seleccionado; ?></div>
                                    <div class="h4 text-accent mt-n3">Año Fiscal</div>
                                </div>
                                <button type="button" class="btn btn-win btn-win-primary btn-lg w-100 py-3 mb-3" onclick="confirmarCierreAnual()">
                                    <i class="fas fa-file-contract me-2"></i> EJECUTAR CIERRE ANUAL
                                </button>
                                <a href="cierre_anual.php" class="text-muted small text-decoration-none hover-text-white">Cancelar operación</a>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif (!$cierre_realizado && isset($verificacion) && !$verificacion['puede_cerrar'] && !empty($lista_pendientes)): ?>
                <!-- Alerta si no se puede cerrar por meses pendientes -->
                <div class="win-card border-danger" style="background: rgba(220, 53, 69, 0.05);">
                    <div class="win-card-body">
                        <div class="d-flex align-items-start">
                            <div class="me-4 text-danger mt-1"><i class="fas fa-ban fa-3x"></i></div>
                            <div>
                                <h5 class="text-danger mb-2">No es posible cerrar el año</h5>
                                
                                <?php if (!empty($lista_pendientes)): ?>
									<p class="mb-2 text-muted">
										Para realizar el cierre anual, es obligatorio cerrar todos los meses del período.<br>
										Se detect<?php echo count($lista_pendientes) == 1 ? 'ó' : 'aron'; ?>: 
										<strong><?php echo count($lista_pendientes); ?></strong> 
										mes<?php echo count($lista_pendientes) == 1 ? '' : 'es'; ?> pendiente<?php echo count($lista_pendientes) == 1 ? '' : 's'; ?>:
									</p>
                                    
                                    <!-- Lista visual de meses faltantes -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php foreach($lista_pendientes as $mes_nombre): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                                <i class="fas fa-times-circle me-1"></i> <?php echo $mes_nombre; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <a href="cierre_mes.php" class="btn btn-sm btn-win-outline text-danger border-danger mt-1">
                                        <i class="fas fa-arrow-right me-1"></i> Ir a Cierre Mensual
                                    </a>
                                <?php else: ?>
                                    <!-- Error genérico -->
                                    <p class="mb-0 text-muted"><?php echo $verificacion['mensaje']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    <script>
// =========================================================
// FUNCIONES PARA BACKUP ANTES DE CIERRE ANUAL
// =========================================================
let cierreAnualEnProceso = false;

function solicitarBackupAntesDeCierreAnual() {
    // Obtener el año seleccionado desde PHP
    const anioSeleccionado = <?php echo json_encode($anio_seleccionado); ?>;
    
    // Generar nombre del backup para mostrar en la advertencia
    const fecha = new Date();
    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, '0');
    const day = String(fecha.getDate()).padStart(2, '0');
    
    let hours = fecha.getHours();
    const minutes = String(fecha.getMinutes()).padStart(2, '0');
    const seconds = String(fecha.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const horaFormateada = String(hours).padStart(2, '0') + minutes + seconds + ampm;
    
    const fechaFormateada = `${year}${month}${day}`;
    const nombreBackupPreview = `SalvaAntesCierreAnual_${anioSeleccionado}_${fechaFormateada}-${horaFormateada}.sql`;
    
    Swal.fire({
        title: '⚠️ RESPALDO DE SEGURIDAD REQUERIDO',
        html: `
            <div class="text-start">
                <!-- ALERTA ROJA - IMPORTANTE -->
                <div style="background: rgba(220, 53, 69, 0.2); border-left: 4px solid #dc3545; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <i class="fas fa-exclamation-triangle" style="color: #ff6b6b; font-size: 1.2rem; margin-top: 2px;"></i>
                        <div>
                            <strong style="color: #ff6b6b; font-size: 1rem;">¡IMPORTANTE!</strong><br>
                            <span style="color: #e0e0e0;">Antes de realizar el cierre anual, es OBLIGATORIO realizar un respaldo de la base de datos.</span>
                        </div>
                    </div>
                </div>
                
                <!-- NOMBRE DEL BACKUP QUE SE CREARÁ -->
                <div style="background: rgba(13, 110, 253, 0.1); border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid rgba(13, 110, 253, 0.3);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                        <i class="fas fa-file-export" style="color: #3b82f6;"></i>
                        <strong style="color: #fff;">Archivo de respaldo a generar:</strong>
                    </div>
                    <div style="background: #1a1a2e; padding: 10px; border-radius: 6px; font-family: monospace; word-break: break-all;">
                        <code style="color: #ffaa33; font-size: 0.85rem;">${nombreBackupPreview}</code>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        El archivo se guardará en tu carpeta de descargas
                    </div>
                </div>
                
                <!-- CARD DE QUÉ SE RESPALDA -->
                <div style="background: rgba(13, 110, 253, 0.1); border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid rgba(13, 110, 253, 0.3);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                        <i class="fas fa-database" style="color: #3b82f6;"></i>
                        <strong style="color: #fff;">¿Qué se respaldará?</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 25px; color: #cbd5e1;">
                        <li style="margin-bottom: 5px;">Todas las tablas del sistema</li>
                        <li style="margin-bottom: 5px;">Facturas, clientes, servicios y configuraciones</li>
                        <li>Historial de operaciones y cierres previos</li>
                        <li>Todo el año fiscal que se va a cerrar</li>
                    </ul>
                </div>
                
                <!-- ALERTA AMARILLA - RECOMENDACIÓN -->
                <div style="background: rgba(255, 193, 7, 0.15); border-left: 4px solid #ffc107; padding: 12px; border-radius: 8px;">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <i class="fas fa-clock" style="color: #ffc107;"></i>
                        <div>
                            <strong style="color: #ffc107;">Recomendación:</strong>
                            <span style="color: #e0e0e0;"> Guarda el archivo en una ubicación segura (carpeta de respaldos, nube, disco externo).</span>
                        </div>
                    </div>
                </div>
            </div>
        `,
        icon: 'warning',
        iconColor: '#ffaa33',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-download me-2"></i> Realizar Backup AHORA',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar Cierre',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#dc3545',
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        reverseButtons: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: '550px'
    }).then((result) => {
        if (result.isConfirmed) {
            realizarBackupYAnual();
        }
    });
}

function realizarBackupYAnual() {
    // Obtener el año seleccionado desde PHP
    const anioSeleccionado = <?php echo json_encode($anio_seleccionado); ?>;
    
    // Generar nombre del backup con el formato solicitado
    const fecha = new Date();
    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, '0');
    const day = String(fecha.getDate()).padStart(2, '0');
    
    // Formato de hora 12h con AM/PM
    let hours = fecha.getHours();
    const minutes = String(fecha.getMinutes()).padStart(2, '0');
    const seconds = String(fecha.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const horaFormateada = String(hours).padStart(2, '0') + minutes + seconds + ampm;
    
    const fechaFormateada = `${year}${month}${day}`;
    const nombreBackup = `SalvaAntesCierreAnual_${anioSeleccionado}_${fechaFormateada}-${horaFormateada}.sql`;
    
    // Mostrar modal de progreso
    Swal.fire({
        title: '📦 Creando Respaldo...',
        html: `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mb-2">Generando archivo de respaldo...</p>
                <p class="text-muted small" id="backupFileName">${nombreBackup}</p>
                <div class="progress mt-3" style="height: 8px;">
                    <div id="backupProgressAnual" class="progress-bar" style="width: 0%; background-color: var(--win-accent);"></div>
                </div>
                <p id="backupStatusTextAnual" class="text-muted small mt-2">Iniciando...</p>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)'
    });
    
    // Simular progreso
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress >= 100) {
            progress = 95;
        }
        const progressBar = document.getElementById('backupProgressAnual');
        const statusText = document.getElementById('backupStatusTextAnual');
        if (progressBar) progressBar.style.width = progress + '%';
        if (statusText) {
            if (progress < 30) statusText.textContent = 'Preparando estructura de datos...';
            else if (progress < 60) statusText.textContent = 'Exportando tablas principales...';
            else if (progress < 90) statusText.textContent = 'Optimizando archivo de respaldo...';
            else statusText.textContent = 'Finalizando, descarga iniciará...';
        }
    }, 200);
    
    // Ejecutar backup con nombre personalizado
    const backupUrl = `export-import/download.php?type=backup&from_cierre=anual&custom_name=${encodeURIComponent(nombreBackup.replace('.sql', ''))}`;
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = backupUrl;
    document.body.appendChild(iframe);
    
    // Esperar a que se inicie la descarga
    setTimeout(() => {
        clearInterval(interval);
        
        const progressBar = document.getElementById('backupProgressAnual');
        if (progressBar) progressBar.style.width = '100%';
        
        setTimeout(() => {
            Swal.fire({
                title: '✅ Backup Completado',
                html: `
                    <div class="text-start">
                        <p>El respaldo se ha generado correctamente.</p>
                        <div class="alert alert-success p-3 mt-2">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Archivo guardado:</strong><br>
                            <code class="text-wrap" style="word-break: break-all;">${nombreBackup}</code>
                        </div>
                        <p class="text-muted small mt-2">
                            <i class="fas fa-folder-open me-1"></i>
                            Ubicación: Carpeta de descargas del navegador
                        </p>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: '<i class="fas fa-arrow-right me-2"></i> Continuar con el Cierre Anual',
                confirmButtonColor: 'var(--win-accent)',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                allowOutsideClick: false
            }).then(() => {
                // Después del backup, continuar con el cierre anual
                confirmarCierreAnualReal();
            });
        }, 1000);
    }, 3000);
}

function confirmarCierreAnualReal() {
    if (cierreAnualEnProceso) {
        Swal.fire({
            icon: 'info',
            title: 'Cierre en proceso',
            text: 'Por favor espere...',
            background: '#1e1e1e',
            color: '#fff'
        });
        return;
    }
    
    // Validar checkboxes
    if (!document.getElementById('confirm1').checked || !document.getElementById('confirm2').checked) {
        Swal.fire({
            icon: 'warning',
            title: 'Verificación incompleta',
            text: 'Debe marcar todas las casillas de confirmación para continuar.',
            background: '#1e1e2d',
            color: '#fff',
            confirmButtonColor: 'var(--win-accent)',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
        return;
    }
    
    cierreAnualEnProceso = true;
    
    Swal.fire({
        title: 'Procesando cierre anual...',
        html: `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="text-muted mt-2">Archivando registros y actualizando período</p>
                <p class="text-muted small">Por favor espere...</p>
            </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        background: '#1e1e2d',
        color: '#fff'
    });
    
    setTimeout(() => {
        document.getElementById('confirmado').value = '1';
        document.getElementById('formCierreAnual').submit();
    }, 500);
}

function confirmarCierreAnual() {
    // Validar checkboxes primero
    if (!document.getElementById('confirm1').checked || !document.getElementById('confirm2').checked) {
        Swal.close();
        setTimeout(function() {
            Swal.fire({
                icon: 'warning',
                title: 'Verificación incompleta',
                text: 'Debe marcar todas las casillas de confirmación para continuar.',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: 'var(--win-accent)',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
            });
        }, 100);
        return;
    }
    
    // ========== NUEVO: SOLICITAR BACKUP ANTES DEL CIERRE ANUAL ==========
    solicitarBackupAntesDeCierreAnual();
}
	
	// Función para limpiar modales residuales
    function limpiarModalesSweetAlert() {
        try {
            Swal.close();
        } catch(e) {}
        
        // Remover elementos del DOM
        const elementos = document.querySelectorAll('.swal2-container, .swal2-popup, .swal2-modal, .swal2-icon, .swal2-header, .swal2-title, .swal2-content, .swal2-html-container, .swal2-actions, .swal2-footer');
        
        // Limpiar clases del body
            elementos.forEach(el => {
                if (el && el.parentNode) {
                    el.remove();
                }
            });
            
            // Remover el backdrop específico de SweetAlert2
            const backdrop = document.querySelector('.swal2-container');
            if (backdrop) {
                backdrop.remove();
            }
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    

    
    // Event listener global para limpiar modales
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('swal2-container')) {
            limpiarModalesSweetAlert();
        }
    });
    
    // Prevenir que el modal se duplique al hacer clic repetidamente
    let modalAbierto = false;
    window.addEventListener('swal:before-open', function() {
        modalAbierto = true;
    });
    
    window.addEventListener('swal:after-close', function() {
        modalAbierto = false;
        limpiarModalesSweetAlert();
    });
    
    // Limpiar al cambiar de página
    window.addEventListener('beforeunload', function() {
        limpiarModalesSweetAlert();
    });

	<?php if ($sin_operaciones): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'info',
            title: 'Sin movimientos',
           html: `
                    <div class="text-center">
                        <i class="fas fa-file-invoice fa-4x text-info mb-3" style="opacity: 0.7;"></i>
                        <p style="color: #e1e1e6; font-size: 1.1rem;">
                            No se encontraron facturas o registros de operaciones 
                            <br>para el año fiscal <strong style="color: <?php echo $color_accent; ?>;"><?php echo $anio_provisional; ?></strong>.
                        </p>
                        <p class="text-muted small mt-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Seleccione otro año fiscal para consultar.
                        </p>
                    <button id="btnCerrarModal" class="btn btn-primary mt-3" style="background: <?php echo $color_accent; ?>; border: none; padding: 8px 25px;">
                        <i class="fas fa-check me-2"></i> Entendido
                    </button>
                    </div>
                `,
            background: '#1e1e2d',
            color: '#e1e1e6',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                document.getElementById('btnCerrarModal').addEventListener('click', function() {
                    Swal.close();
                    // Limpiar selección
                    const selectAnio = document.querySelector('select[name="anio_consultar"]');
                    if (selectAnio) selectAnio.value = '';
                });
            }
        });
    });
    <?php endif; ?>
        
        <?php
        // Verificar si hay un cierre exitoso en la sesión (por redirección)
        if (isset($_SESSION['cierre_anual_exitoso'])) {
            $cierre_info = $_SESSION['cierre_anual_exitoso'];
            ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '¡Cierre Anual Completado!',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4 class="text-white mb-3">Año <?php echo $cierre_info['anio']; ?> cerrado exitosamente</h4>
                            <p class="text-muted mb-0"><?php echo $cierre_info['mensaje']; ?></p>
                            <div class="mt-4 p-3 bg-dark bg-opacity-50 rounded">
                                <i class="fas fa-info-circle text-accent me-2"></i>
                                <span class="text-white">Año de operaciones actualizado a <?php echo ($cierre_info['anio'] + 1); ?></span>
                            </div>
                            <p class="text-muted small mt-3">
                                <i class="fas fa-clock me-1"></i> 
                                <?php echo date('d/m/Y H:i:s'); ?>
                            </p>
                        </div>
                    `,
                    background: '#1e1e2d',
                    color: '#e1e1e6',
                    confirmButtonColor: '<?php echo $color_accent; ?>',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showClass: {
                        popup: 'animated fadeInDown'
                    }
                }).then(() => {
                    window.location.href = 'cierre_anual.php?anio_consultar=<?php echo $cierre_info['anio']; ?>';
                });
            });
            <?php
            // Limpiar la sesión después de mostrar el mensaje
            unset($_SESSION['cierre_anual_exitoso']);
        }

        // Verificar si hay parámetro GET de cierre exitoso (respaldo)
        if (isset($_GET['cierre_exitoso']) && $_GET['cierre_exitoso'] == 1 && !isset($_SESSION['cierre_anual_exitoso']) && $anio_seleccionado) {
            ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '¡Cierre Anual Completado!',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4 class="text-white mb-3">Año <?php echo $anio_seleccionado; ?> cerrado</h4>
                            <p class="text-muted mb-0">El período fiscal ha sido archivado correctamente.</p>
                            <div class="mt-4 p-3 bg-dark bg-opacity-50 rounded">
                                <i class="fas fa-arrow-right text-accent me-2"></i>
                                <span class="text-white">Nuevo año de operaciones: <?php echo ($anio_seleccionado + 1); ?></span>
                            </div>
                        </div>
                    `,
                    background: '#1e1e2d',
                    color: '#e1e1e6',
                    confirmButtonColor: '<?php echo $color_accent; ?>',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                    allowOutsideClick: false,
                    showClass: {
                        popup: 'animated fadeInDown'
                    }
                }).then(() => {
                    // Limpiar URL y recargar con el año seleccionado
                    window.location.href = 'cierre_anual.php?anio_consultar=<?php echo $anio_seleccionado; ?>';
                });
            });
            <?php
        }
        ?>
        
        // Prevenir reenvío del formulario al recargar la página
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>