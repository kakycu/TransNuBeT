<?php
session_start(); // Asegurar sesión iniciada desde el principio
ob_start();
require_once 'config/cierre_funciones.php';
require_once 'config/database.php';


// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $db = Database::getConnection();
	

// ==================== OBTENER PERIODO ACTUAL ====================
$db = Database::getConnection();
$periodo_actual = obtenerPeriodoOperaciones($db);
$mes_actual = $periodo_actual['mes'];
$anio_actual = $periodo_actual['anio'];
$fecha_inicio = $periodo_actual['fecha_inicio'];

$meses_completos = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$mes_actual_nombre = $meses_completos[$mes_actual];
    
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

// ==================== GENERAR TOKEN PARA FORMULARIO ====================
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// ==================== CONFIGURACIÓN INICIAL ====================
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';

// ==================== VERIFICAR Y LIMPIAR DATOS DE SESIÓN ====================
$success = '';
$error = '';
$cierre_realizado = false;
$es_cierre_diciembre = false;

// Verificar si hay mensajes de éxito en sesión
if (isset($_SESSION['cierre_exitoso']) && $_SESSION['cierre_exitoso']['success']) {
    $cierre_exitoso = $_SESSION['cierre_exitoso'];
    
    // Verificar si fue cierre de diciembre
    if (isset($cierre_exitoso['es_fin_anio']) && $cierre_exitoso['es_fin_anio']) {
        $es_cierre_diciembre = true;
        // Mensaje específico para diciembre (no mostramos "Nuevo período" aún)
        $success = "✅ <strong>Cierre de Diciembre realizado.</strong><br>";
        $success .= "Se han cerrado las operaciones del año " . $anio_actual . ".<br>";
        $success .= "• Importe cerrado: <strong>$" . number_format($cierre_exitoso['resumen']['importe_total'], 2) . "</strong>";
    } else {
        // Mensaje estándar para meses 1-11
        $success = $cierre_exitoso['mensaje'] . "<br><br>";
        $success .= "<strong>Resumen:</strong><br>";
        $success .= "• Período: <strong>{$cierre_exitoso['resumen']['periodo']}</strong><br>";
        $success .= "• Nuevo período: <strong>{$cierre_exitoso['resumen']['nuevo_periodo']}</strong>";
    }
    
    // Limpiar la sesión inmediatamente
    unset($_SESSION['cierre_exitoso']);
    $cierre_realizado = true;
}

// Verificar si hay errores en sesión
if (isset($_SESSION['error_cierre'])) {
    $error = $_SESSION['error_cierre'];
    unset($_SESSION['error_cierre']);
}

// Verificar mensajes de contabilización
if (isset($_SESSION['success_contabilizacion'])) {
    $success = $_SESSION['success_contabilizacion'];
    unset($_SESSION['success_contabilizacion']);
}
if (isset($_SESSION['error_contabilizacion'])) {
    $error = $_SESSION['error_contabilizacion'];
    unset($_SESSION['error_contabilizacion']);
}



require_once 'config/header.php'; 

// ==================== VERIFICAR SI EL MES YA ESTÁ CERRADO ====================
$verificacion_cierre = verificarCierreMes($mes_actual, $anio_actual, $db);

// Verificamos si el mensaje indica que ya está cerrado
$mes_cerrado = !$verificacion_cierre['puede_cerrar'] && 
               strpos($verificacion_cierre['mensaje'], 'ya fue cerrado') !== false;

if ($mes_cerrado) {
    // Determinar si es diciembre
    $es_diciembre = ($mes_actual == 12);
    
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISFACT - Mes Cerrado</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    <script src="js/sweetalert211.js"></script>
    <style>
        body {
            background: #0f0f1a;
            margin: 0;
            min-height: 100vh;
        }
        .swal2-actions {
            flex-wrap: wrap;
            gap: 10px;
        }
        .swal2-cancel, .swal2-deny {
            margin-right: 10px !important;
        }
    </style>
</head>
<body>
    <script>
    const mesNombres = {
        1: "Enero", 2: "Febrero", 3: "Marzo", 4: "Abril", 
        5: "Mayo", 6: "Junio", 7: "Julio", 8: "Agosto",
        9: "Septiembre", 10: "Octubre", 11: "Noviembre", 12: "Diciembre"
    };
    
    const mesActual = ' . $mes_actual . ';
    const anioActual = ' . $anio_actual . ';
    const mesNombre = mesNombres[mesActual];
    const esDiciembre = ' . ($es_diciembre ? 'true' : 'false') . ';
    
    // Configuración base de SweetAlert
    const swalConfig = {
        title: esDiciembre ? "Cierre de Diciembre" : "Mes Cerrado",
        html: `
            <div style="text-align: center; padding: 15px;">
                <div style="font-size: 4rem; color: #6c757d; margin-bottom: 15px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 style="color: #fff; margin-bottom: 10px;">
                    ${mesNombre} ${anioActual}
                </h3>
                <p style="color: #a8a8b3; margin-bottom: 20px;">
                    ' . ($es_diciembre ? 
                        'El mes de <span class="h4 fw text-warning">diciembre</span> ha sido cerrado.<br>El año <span class="h4 fw text-warning">' . $anio_actual . '</span> está completo.' : 
                        'El período contable ya ha sido cerrado. No se pueden realizar operaciones.'
                    ) . '
                </p>
                <div style="background: rgba(108, 117, 125, 0.1); 
                         padding: 15px; 
                         border-radius: 10px;
                         border-left: 4px solid #6c757d;
                         margin: 20px 0;">
                    <p style="margin: 5px 0; color: #ccc;">
                        <i class="fas fa-info-circle me-2"></i>
                        ' . ($es_diciembre ? 
                            'Año contable finalizado. Contacte con el administrador para el cierre anual.' : 
                            'Para operar en este mes, contacte con el administrador.'
                        ) . '
                    </p>
                </div>
            </div>`,
        icon: "info",
        iconColor: "#6c757d",
        background: "#1e1e2d",
        color: "#e1e1e6",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showCancelButton: true,
        cancelButtonText: "<i class=\'fas fa-tachometer-alt me-2\'></i>Dashboard",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "<i class=\'fas fa-calendar-alt me-2\'></i>Ver Período Actual",
        confirmButtonColor: "#0078d4",
    };
    
    // Si es diciembre, agregar botón de cierre anual
    if (esDiciembre) {
        swalConfig.showDenyButton = true;
        swalConfig.denyButtonText = "<i class=\'fas fa-calendar-check me-2\'></i>Cierre Anual";
        swalConfig.denyButtonColor = "#dc3545";
    }
    
    Swal.fire(swalConfig).then((result) => {
        if (result.isConfirmed) {
            // Mostrar período actual antes de redirigir
            Swal.fire({
                title: "Período Activo",
                html: `
                    <div style="text-align: center;">
                        <div style="background: rgba(0, 120, 212, 0.1); 
                                 padding: 20px; 
                                 border-radius: 10px;
                                 margin: 15px 0;">
                            <h1 style="color: #0078d4; font-size: 2.5rem; margin: 0;">
                                ${mesNombre} ${anioActual}
                            </h1>
                            <p style="color: #a8a8b3; margin-top: 5px;">
                                Período contable actual
                            </p>
                        </div>
                        <p style="color: #a8a8b3; font-size: 0.9rem;">
                            <i class="fas fa-clock me-1"></i>
                            Inició el ' . date("d/m/Y", strtotime($fecha_inicio)) . '
                        </p>
                    </div>`,
                icon: "success",
                iconColor: "#0078d4",
                background: "#1e1e2d",
                color: "#e1e1e6",
                confirmButtonText: "<i class=\"fas fa-check me-2\"></i> Entendido",
                confirmButtonColor: "#28a745",
                timer: 5000,
                timerProgressBar: true,
                didOpen: () => {
                    const timer = Swal.getPopup().querySelector("b");
                    timerInterval = setInterval(() => {
                        timer.textContent = `${Swal.getTimerLeft()}`;
                    }, 100);
                }
            }).then(() => {
                window.location.href = "dashboard.php";
            });
        } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
            // Botón Dashboard
            window.location.href = "dashboard.php";
        } else if (result.isDenied) {
            // Botón Cierre Anual (solo en diciembre)
            window.location.href = "cierre_anual.php"; // Cambia esto por la URL correcta
        }
    });
    </script>
</body>
</html>';
exit();
}
// Variables
$verificacion = null;
$estadisticas = null;
$facturas_pendientes = [];
$facturas_a_cerrar = [];
$importe_a_cerrar = 0;

// ==================== PROCESAR POST ANTES DE CUALQUIER SALIDA ====================

// CASO 1: Contabilizar una SOLA factura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contabilizar_factura'])) {
    $factura_id = $_POST['factura_id'] ?? 0;
    $numero_factura = $_POST['numero_factura'] ?? '';
    
    if ($factura_id > 0) {
        $sql_factura = "SELECT no_fact, estado FROM tbl_fact WHERE id = :id";
        $stmt = $db->prepare($sql_factura);
        $stmt->execute(['id' => $factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($factura && $factura['estado'] == 'PENDIENTE') {
            $sql_update = "UPDATE tbl_fact 
                          SET estado = 'CONTABILIZADA', 
                              fecha_contabilizacion = NOW(),
                              usuario_contabilizacion = :usuario_id
                          WHERE id = :id";
            $stmt = $db->prepare($sql_update);
            $stmt->execute([
                'id' => $factura_id,
                'usuario_id' => $_SESSION['usuario_id']
            ]);
            
            // Registrar en histórico
            $descripcion = "Factura #{$factura['no_fact']} contabilizada desde página de cierre";
            $sql_historico = "INSERT INTO historico_operaciones 
                             (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                             VALUES ('Contabilización', :descripcion, :usuario_id, :usuario_nombre, :ip)";
            $stmt = $db->prepare($sql_historico);
            $stmt->execute([
                'descripcion' => $descripcion,
                'usuario_id' => $_SESSION['usuario_id'],
                'usuario_nombre' => $_SESSION['usuario_nombre'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['success_contabilizacion'] = "✅ Factura #{$factura['no_fact']} contabilizada exitosamente";
        } else {
            $_SESSION['error_contabilizacion'] = "La factura no existe o ya no está pendiente";
        }
    }
    header('Location: cierre_mes.php');
    exit();
}

// CASO 2: Contabilizar TODAS las facturas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contabilizar_todas'])) {
    try {
        $db->beginTransaction();

        $sql_update_all = "UPDATE tbl_fact 
                           SET estado = 'CONTABILIZADA', 
                               fecha_contabilizacion = NOW(),
                               usuario_contabilizacion = :usuario_id
                           WHERE estado = 'PENDIENTE'
                           AND MONTH(fecha_emision) = :mes 
                           AND YEAR(fecha_emision) = :anio";
        
        $stmt = $db->prepare($sql_update_all);
        $stmt->execute([
            'usuario_id' => $_SESSION['usuario_id'],
            'mes' => $mes_actual,
            'anio' => $anio_actual
        ]);
        
        $cantidad_actualizada = $stmt->rowCount();

        if ($cantidad_actualizada > 0) {
            $descripcion = "Contabilización masiva de $cantidad_actualizada facturas del mes $mes_actual/$anio_actual";
            $sql_historico = "INSERT INTO historico_operaciones 
                             (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                             VALUES ('Contabilización Masiva', :descripcion, :usuario_id, :usuario_nombre, :ip)";
            $stmt = $db->prepare($sql_historico);
            $stmt->execute([
                'descripcion' => $descripcion,
                'usuario_id' => $_SESSION['usuario_id'],
                'usuario_nombre' => $_SESSION['usuario_nombre'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $db->commit();
            $_SESSION['success_contabilizacion'] = "✅ Se contabilizaron exitosamente <strong>$cantidad_actualizada</strong> facturas pendientes.";
        } else {
            $db->rollBack();
            $_SESSION['error_contabilizacion'] = "No se encontraron facturas pendientes para procesar en este período.";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_contabilizacion'] = "Error en contabilización masiva: " . $e->getMessage();
    }
    header('Location: cierre_mes.php');
    exit();
}

// ==================== PROCESAR CIERRE (PRG Pattern) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['realizar_cierre'])) {
    // Verificar token
    if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        $_SESSION['error_cierre'] = "Token de formulario inválido. Por favor, recargue la página.";
        header('Location: cierre_mes.php');
        exit();
    }
    
    $confirmado = $_POST['confirmado'] ?? false;
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if (!$confirmado) {
        $_SESSION['error_cierre'] = "Debe confirmar el cierre marcando la casilla de verificación.";
        header('Location: cierre_mes.php');
        exit();
    } else {
        $resultado = realizarCierreMes(
		$mes_actual, 
		$anio_actual, 
		$_SESSION['usuario_id'], 
		$_SESSION['usuario_nombre'],
		$db,            // <--- El objeto de conexión debe ir aquí
		$observaciones  // <--- Las observaciones deben ir al final
	);
        
		if ($resultado['success']) {
            // Guardar datos en sesión
            $_SESSION['cierre_exitoso'] = [
                'success' => true,
                'mensaje' => "✅ Cierre mensual realizado exitosamente!",
                'es_fin_anio' => $resultado['es_fin_anio'] ?? false, // GUARDAMOS LA BANDERA AQUÍ
                'resumen' => [
                    'periodo' => "{$mes_actual_nombre} {$anio_actual}",
                    'facturas_cerradas' => $resultado['estadisticas']['total_facturas'],
                    'importe_total' => $resultado['estadisticas']['importe_total'],
                    'nuevo_periodo' => $meses_completos[$resultado['proximo_periodo']['mes']] . " {$resultado['proximo_periodo']['anio']}",
                    'cierre_id' => $resultado['cierre_id']
                ]
            ];
            
            // Regenerar token y redirigir
            unset($_SESSION['form_token']);
            $_SESSION['form_token'] = bin2hex(random_bytes(32));
            header('Location: cierre_mes.php?cierre=exitoso');
            exit();
        } else {
            $_SESSION['error_cierre'] = $resultado['mensaje'];
            header('Location: cierre_mes.php');
            exit();
        }
    }
}

// ==================== OBTENER DATOS ACTUALES ====================

// Obtener usuario actual y permisos
try {
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
    
    // Solo administradores y supervisores pueden realizar cierres
    $esAdmin = ($usuario['rol_id'] == 1);
    $esSuper = ($usuario['rol_id'] == 4);
    
    if (!$esAdmin && !$esSuper) {
        $_SESSION['sweet_alert'] = [
            'title' => 'Acceso denegado',
            'text' => 'Solo administradores y supervisores pueden realizar cierres.',
            'icon' => 'error',
            'confirmButtonText' => '<i class="fas fa-check me-2"></i> Aceptar'
        ];
        header('Location: facturas.php');
        exit();
    }
    
    // ==================== VERIFICAR ESTADO DEL MES ====================
    $verificacion = verificarCierreMes($mes_actual, $anio_actual, $db);
    $estadisticas = $verificacion['estadisticas'] ?? null;
    
    // Obtener facturas pendientes
    $sql_pendientes = "SELECT 
                  f.id, f.no_fact, c.nombre as cliente, f.total_general, 
                  DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as fecha,
                  f.Ref_pago, u.nombre as vendedor
                  FROM tbl_fact f
                  LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                  LEFT JOIN clasif_usuarios u ON f.usuario_id = u.id
                  WHERE f.estado = 'PENDIENTE'
                  AND MONTH(f.fecha_emision) = :mes 
                  AND YEAR(f.fecha_emision) = :anio
                  ORDER BY f.fecha_emision DESC";
    $stmt = $db->prepare($sql_pendientes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $facturas_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener facturas a cerrar (CONTABILIZADAS o PAGADAS)
	$sql_a_cerrar = "SELECT 
					f.id, f.no_fact, c.nombre as cliente, f.total_general,
					f.estado,
					f.Ref_pago,
					DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as fecha_emision,
					DATE_FORMAT(f.fecha_contabilizacion, '%d/%m/%Y %H:%i') as fecha_contabilizacion,
					DATE_FORMAT(f.fecha_pago, '%d/%m/%Y') as fecha_pago
					FROM tbl_fact f
					LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
					WHERE f.estado IN ('CONTABILIZADA', 'PAGADA')
					AND MONTH(f.fecha_emision) = :mes 
					AND YEAR(f.fecha_emision) = :anio
					ORDER BY f.no_fact";
    $stmt = $db->prepare($sql_a_cerrar);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $facturas_a_cerrar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular importe total a cerrar
    $importe_a_cerrar = array_sum(array_column($facturas_a_cerrar, 'total_general'));
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre Mensual - SISFACT</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <style>
        :root {
            --win-bg-primary: #121212; /* Fondo más suave que #000 */
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

        /* --- Tipografía y Utilidades --- */
        h1, h2, h3, h4, h5 { font-weight: 600; letter-spacing: -0.5px; }
        .text-muted { color: var(--win-text-secondary) !important; }
        .text-accent { color: var(--win-accent) !important; }
        .fw-bold { font-weight: 700 !important; }

        /* --- Tarjetas Principales --- */
        .win-card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
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

        /* --- Widgets de Estadísticas Modernos --- */
        .stat-widget {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            height: 100%;
            transition: var(--win-transition);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stat-widget:hover {
            transform: translateY(-3px);
            box-shadow: var(--win-shadow);
            border-color: rgba(255,255,255,0.1);
        }

        .stat-widget::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: currentColor;
        }

        .stat-widget-icon {
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 5rem;
            opacity: 0.05;
            transform: rotate(-15deg);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--win-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Colores específicos para widgets */
        .widget-danger { color: #ff4d4f; background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(220, 53, 69, 0.05)); }
        .widget-success { color: #28c76f; background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(40, 199, 111, 0.05)); }
        .widget-info { color: #00cfe8; background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(0, 207, 232, 0.05)); }
        .widget-neutral { color: var(--win-text-secondary); background: linear-gradient(145deg, var(--win-bg-tertiary), rgba(255, 255, 255, 0.02)); }

        /* --- Tablas Modernas --- */
        .table-responsive { border-radius: var(--win-radius); }
        
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem; /* Espacio entre filas */
            margin-top: -0.5rem;
        }

        .table-custom thead th {
            background: transparent;
            color: var(--win-text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 0 1rem 0.5rem 1rem;
        }

        .table-custom tbody tr {
            background: var(--win-bg-tertiary);
            transition: var(--win-transition);
        }

        .table-custom tbody tr:hover {
            background: var(--win-bg-elevated);
            transform: scale(1.005);
        }

        .table-custom td {
            border: none; /* Sin bordes */
            padding: 1rem;
            vertical-align: middle;
            color: var(--win-text-primary);
        }

        .table-custom td:first-child { border-radius: 8px 0 0 8px; }
        .table-custom td:last-child { border-radius: 0 8px 8px 0; }
        .table-custom tfoot td { background: transparent; padding-top: 1rem; }

        /* --- Badges y Botones --- */
        .badge-pill {
            padding: 0.4em 0.8em;
            border-radius: 50rem;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }

        .badge-pendiente { background: rgba(255, 193, 7, 0.1); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.2); }
        .badge-contabilizada { background: rgba(40, 199, 111, 0.1); color: #28c76f; border: 1px solid rgba(40, 199, 111, 0.2); }
        .badge-pagada { background: rgba(0, 207, 232, 0.1); color: #00cfe8; border: 1px solid rgba(0, 207, 232, 0.2); }
        .badge-cerrada { background: rgba(166, 166, 166, 0.1); color: #a6a6a6; border: 1px solid rgba(166, 166, 166, 0.2); }

        .btn-win {
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            border: 1px solid transparent;
            transition: var(--win-transition);
        }
        
        .btn-win-primary {
            background: var(--win-accent);
            color: #fff;
            box-shadow: 0 4px 12px rgba(var(--win-accent-rgb), 0.3);
        }
        
        .btn-win-primary:hover {
            filter: brightness(110%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(var(--win-accent-rgb), 0.4);
        }

        .btn-icon {
            width: 34px; height: 34px;
            display: inline-flex;
            align-items: center; justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-icon:hover { background: rgba(255,255,255,0.1); transform: scale(1.1); }

        /* --- Checklist personalizado para confirmación --- */
        .checklist-item {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            padding: 1rem;
            border-radius: var(--win-radius);
            margin-bottom: 0.8rem;
            cursor: pointer;
            transition: var(--win-transition);
            display: flex;
            align-items: start;
        }

        .checklist-item:hover { border-color: var(--win-text-secondary); background: var(--win-bg-elevated); }
        
        .checklist-item .form-check-input {
            margin-top: 0.25em;
            margin-right: 1rem;
            background-color: transparent;
            border-color: var(--win-text-secondary);
            cursor: pointer;
            width: 1.2em; height: 1.2em;
        }
        
        .checklist-item .form-check-input:checked {
            background-color: var(--win-accent);
            border-color: var(--win-accent);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header con mejor jerarquía -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 pb-3 border-bottom border-secondary" style="border-color: var(--win-border-color) !important;">
            <div>
                <h1 class="h2 mb-1">
                    <span class="text-accent me-2"><i class="fas fa-calendar-check"></i></span>
                    Cierre Mensual
                </h1>
                <p class="text-muted mb-0">
                    Período: <strong class="text-white"><?php echo $mes_actual_nombre . ' ' . $anio_actual; ?></strong>
                    <span class="mx-2">•</span> 
                    Inicio: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?>
					<span class="mx-2">•</span>
                    Año de Operacione: <strong class="text-white"><?php echo $anio_actual; ?></strong>	
                </p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-win">
                    <i class="fas fa-dashboard me-2"></i>Dashboard
                </a>
                <a href="facturas.php" class="btn btn-outline-secondary btn-win">
                    <i class="fas fa-arrow-left me-2"></i>Facturas
                </a>
                <a href="cierre_anual.php" class="btn btn-outline-secondary btn-win">
                    <i class="fas fa-calendar me-2"></i>Cierre Anual
                </a>
                <a href="cierres_realizados.php?tipo=MES" class="btn btn-outline-info btn-win">
                    <i class="fas fa-history me-2"></i>Historial
                </a>
            </div>
        </div>

<!-- Mensajes de Sistema -->
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
                <h5 class="text-danger mb-1">Error detectado</h5>
                <p class="mb-0 text-muted"><?php echo $error; ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- KPIs Modernos -->
<div class="row g-4 mb-5">
    <div class="col-md-3 col-sm-6">
        <div class="stat-widget widget-danger">
            <i class="fas fa-exclamation-triangle stat-widget-icon"></i>
            <div class="stat-value"><?php echo count($facturas_pendientes); ?></div>
            <div class="stat-label">Pendientes</div>
            <small class="text-white-50 mt-1 d-block">Requieren acción</small>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-widget widget-success">
            <i class="fas fa-check-double stat-widget-icon"></i>
            <div class="stat-value"><?php echo count($facturas_a_cerrar); ?></div>
            <div class="stat-label">Listas para Cerrar</div>
            <small class="text-white-50 mt-1 d-block">Contabilizadas</small>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-widget widget-info">
            <i class="fas fa-dollar-sign stat-widget-icon"></i>
            <div class="stat-value"><?php echo number_format($importe_a_cerrar, 0, ',', '.'); ?><small style="font-size:1rem">.<?php echo substr(number_format($importe_a_cerrar, 2), -2); ?></small></div>
            <div class="stat-label">Importe Total</div>
            <small class="text-white-50 mt-1 d-block">A procesar</small>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-widget widget-neutral">
            <i class="fas fa-file-invoice stat-widget-icon"></i>
            <div class="stat-value"><?php echo count($facturas_pendientes) + count($facturas_a_cerrar); ?></div>
            <div class="stat-label">Total Mes</div>
            <small class="text-muted mt-1 d-block">Facturas activas</small>
        </div>
    </div>
</div>

<!-- ===== BOTÓN REGRESAR AL INICIO DESPUÉS DE LAS ESTADÍSTICAS ===== -->
<?php if (isset($_GET['cierre']) && $_GET['cierre'] == 'exitoso'): ?>
    <div class="text-center my-4">
        <a href="dashboard.php" class="btn btn-outline-primary btn-win btn-lg px-5 py-3">
            <i class="fas fa-tachometer-alt me-2 fa-lg"></i>
            <span class="fw-bold">Ir al Dashboard</span>
        </a>
        <p class="text-muted mt-2">
            <i class="fas fa-clock me-1"></i>
            Puede continuar trabajando en el nuevo período o
        </p>
            <p class="text-muted mt-2 small">
                <a href="cierres_realizados.php?tipo=MES" class="text-info text-decoration-none hover:text-accent">
                    <i class="fas fa-history me-1"></i>
                    Haga clic para ver Historial de Cierres Mensuales
                </a>
            </p>
    </div>
<?php endif; ?>
        <!-- 1. Sección Pendientes (Prioridad Alta) -->
        <?php if (!empty($facturas_pendientes) && !$cierre_realizado): ?>
            <div class="win-card border-warning">
                <div class="win-card-header">
                    <h5 class="mb-0 text-warning">
                        <i class="fas fa-clock me-2"></i>Facturas Pendientes
                    </h5>
                    <span class="badge bg-warning text-dark rounded-pill"><?php echo count($facturas_pendientes); ?> Acción requerida</span>
                </div>
                <div class="win-card-body">
                    <p class="text-warning mb-4" style="font-weight: bold; animation: blink 1s infinite;">
    Debe contabilizar estas facturas antes de cerrar el mes.
</p>

<style>
    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0.3; }
        100% { opacity: 1; }
    }
</style>
                    
                    <div class="table-responsive">
<table class="table-custom">
    <thead>
        <tr>
            <th width="15%">Factura</th>
            <th width="30%">Cliente</th> <!-- Aumenté el ancho ya que quitaste servicio -->
            <th width="15%">Emitida</th>
            <th width="15%" class="text-end">Total</th>
            <th width="25%" class="text-center">Acciones</th> <!-- Aumenté el ancho -->
        </tr>
    </thead>
    <tbody>
        <?php foreach ($facturas_pendientes as $factura): ?>
            <tr>
                <td>
                    <div class="fw-bold text-white"><?php echo htmlspecialchars($factura['no_fact']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($factura['cliente']); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($factura['fecha']); ?></td>
                <td class="text-end fw-bold text-accent">
                    $<?php echo number_format($factura['total_general'], 2); ?>
                </td>
                <td class="text-center">
                    <div class="d-flex justify-content-center gap-1">
                        <a href="ver_factura.php?id=<?php echo $factura['id']; ?>" class="btn-icon text-primary" title="Ver" target="_blank"><i class="fas fa-eye"></i></a>
                        <a href="editar_factura.php?id=<?php echo $factura['id']; ?>&from_cierre=1" class="btn-icon text-warning" title="Editar"><i class="fas fa-pen"></i></a>
                        <form method="POST" action="" onsubmit="confirmContabilizar(event, '<?php echo htmlspecialchars($factura['no_fact']); ?>')">
                            <input type="hidden" name="contabilizar_factura" value="1">
                            <input type="hidden" name="factura_id" value="<?php echo $factura['id']; ?>">
                            <input type="hidden" name="numero_factura" value="<?php echo htmlspecialchars($factura['no_fact']); ?>">
                            <button type="submit" class="btn-icon text-success" title="Contabilizar Ahora"><i class="fas fa-check-circle fa-lg"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-end text-muted pt-3">Total Pendiente:</td>
            <td class="text-end fw-bold fs-5 pt-3">$<?php echo number_format(array_sum(array_column($facturas_pendientes, 'total_general')), 2); ?></td>
            <td class="pt-3">
                <form method="POST" action="" onsubmit="confirmContabilizarTodas(event, <?php echo count($facturas_pendientes); ?>)">
                    <input type="hidden" name="contabilizar_todas" value="1">
                    <button type="submit" class="btn btn-warning w-100 btn-sm fw-bold shadow-sm">
                        <i class="fas fa-bolt me-1"></i> Contabilizar Todo
                    </button>
                </form>
            </td>
        </tr>
    </tfoot>
</table>
					
					</div>
                </div>
            </div>
        <?php elseif (!$cierre_realizado): ?>
            <div class="win-card border-success" style="background: linear-gradient(to right, rgba(40, 199, 111, 0.05), transparent);">
                <div class="win-card-body d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="fas fa-check text-success fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 text-white">Todo al día<small class="text-muted"> - No hay facturas pendientes. Puede proceder al cierre.</small></h5>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. Sección Listas para Cerrar -->
        <?php if (!empty($facturas_a_cerrar)): ?>
            <div class="win-card">
                <div class="win-card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ul me-2 text-accent"></i>Detalle de Facturas a Cerrar
                    </h5>
                    <span class="text-muted small">Total: <strong class="text-white">$<?php echo number_format($importe_a_cerrar, 2); ?></strong></span>
                </div>
                <div class="win-card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
    <div class="win-card-body">
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Cliente</th>
                        <th>F.Emisión</th>
                        <th>F.Contab.</th>
                        <th>F.Pago</th>
                        <th>Ref. Pago</th>
                        <th>Estado</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facturas_a_cerrar as $factura): ?>
                        <tr>
                            <td class="fw-bold text-white"><?php echo htmlspecialchars($factura['no_fact']); ?></td>
                            <td><?php echo htmlspecialchars($factura['cliente']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($factura['fecha_emision']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($factura['fecha_contabilizacion'] ?? 'No registrada'); ?></td>
                            <td>
                                <?php if (!empty($factura['fecha_pago'])): ?>
                                        <?php echo htmlspecialchars($factura['fecha_pago']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($factura['Ref_pago'])): ?>
                                        <?php echo htmlspecialchars($factura['Ref_pago']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-pill badge-<?php echo strtolower($factura['estado']); ?>">
                                    <?php echo htmlspecialchars($factura['estado']); ?>
                                </span>
                            </td>
                            <td class="text-end">$<?php echo number_format($factura['total_general'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
					</div>
                </div>
            </div>
        <?php endif; ?>

<!-- 3. Zona de Cierre -->
<?php if ($verificacion['puede_cerrar'] && !$cierre_realizado): ?>
    <div class="win-card border-secondary position-relative overflow-hidden">
        <!-- Barra de acento superior -->
        <div style="height: 4px; background: var(--win-accent); width: 100%; position: absolute; top: 0;"></div>
        
        <div class="win-card-body">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h3 class="mb-4"><i class="fas fa-lock me-2 text-accent"></i>Confirmación de Cierre</h3>
                    
                    <form id="formCierre" method="POST" action="">
                        <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>">
                        
                        <div class="mb-4">
                            <label class="form-check-label checklist-item">
                                <input class="form-check-input" type="checkbox" id="confirm1" required>
                                <div>
                                    <span class="d-block fw-bold text-white">Verificación de Pendientes</span>
                                    <small class="text-muted">Confirmo que he revisado y no quedan facturas pendientes por procesar en <?php echo $mes_actual_nombre; ?>.</small>
                                </div>
                            </label>
                            
                            <label class="form-check-label checklist-item">
                                <input class="form-check-input" type="checkbox" id="confirm2" required>
                                <div>
                                    <span class="d-block fw-bold text-white">Cambio de Estado</span>
                                    <small class="text-muted">Las facturas 'Contabilizada' y 'Pagada' pasarán a estado 'CERRADA'.</small>
                                </div>
                            </label>
                            
                            <label class="form-check-label checklist-item">
                                <input class="form-check-input" type="checkbox" id="confirm3" required>
                                <div>
                                    <span class="d-block fw-bold text-danger">Operación Irreversible</span>
                                    <small class="text-muted">Entiendo que el sistema avanzará automáticamente al siguiente mes fiscal.</small>
                                </div>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Observaciones (Opcional)</label>
                            <textarea name="observaciones" class="form-control bg-dark text-white border-secondary" rows="2" 
                                      placeholder="Ej: Cierre realizado sin incidencias..."></textarea>
                        </div>

                        <input type="hidden" name="realizar_cierre" value="1">
                        <input type="hidden" name="confirmado" id="confirmado" value="0">
                        <!-- NUEVO: Campo para saber si es diciembre -->
                        <input type="hidden" name="es_diciembre" id="es_diciembre" value="<?php echo ($mes_actual == 12) ? '1' : '0'; ?>">
                    </form>
                </div>
                
                <div class="col-lg-5 text-center ps-lg-5 border-start border-secondary">
                    <div class="mb-4">
                        <div class="display-6 fw-bold text-white mb-2"><?php echo $mes_actual_nombre; ?></div>
                        <span class="badge bg-secondary rounded-pill">Período Actual</span>
                        
                        <?php if ($mes_actual == 12): ?>
                            <!-- Para diciembre, mostramos mensaje especial -->
                            <div class="my-3"><i class="h2 fas fa-star text-warning"></i></div>
                            <div class="h3 text-warning mb-1">Último Mes del Año</div>
                            <span class="badge bg-warning bg-opacity-25 text-warning rounded-pill">Cierre Especial</span>
                            <p class="small text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                La fecha de operaciones se mantendrá en diciembre hasta que realice el cierre anual.
                            </p>
                        <?php else: ?>
                            <!-- Para otros meses, flujo normal -->
                            <div class="my-3"><i class="h2 fas fa-arrow-down text-muted"></i></div>
                            <?php 
                                $nuevo_mes = ($mes_actual % 12) + 1;
                                $nuevo_anio = $mes_actual == 12 ? $anio_actual + 1 : $anio_actual;
                            ?>
                            <div class="h3 text-accent mb-1"><?php echo $meses_completos[$nuevo_mes] . ' ' . $nuevo_anio; ?></div>
                            <span class="badge bg-primary bg-opacity-25 text-primary rounded-pill">Próximo Período</span>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="btn btn-win-primary btn-lg w-100 py-3" onclick="confirmarCierre()">
                        <?php if ($mes_actual == 12): ?>
                            <i class="fas fa-calendar-star me-2"></i> CERRAR DICIEMBRE
                        <?php else: ?>
                            <i class="fas fa-key me-2"></i> EJECUTAR CIERRE
                        <?php endif; ?>
                    </button>
                    <div class="mt-3">
                        <a href="facturas.php" class="text-muted text-decoration-none small hover-text-white">Cancelar operación</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
    </div>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
<script>
    // Variables globales para control de estado
    let cierreEnProceso = false;
    
    // Funciones auxiliares para facturas individuales
    function confirmContabilizar(event, numeroFactura) {
        event.preventDefault();
        const form = event.target;
        Swal.fire({
            title: '¿Contabilizar?',
            text: `Factura: ${numeroFactura}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#4b4b4b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Sí, procesar',
			cancelButtonText: '<i class="fas fa-close me-2"></i>cancelar',
            background: '#1e1e1e',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    }
    
    function confirmContabilizarTodas(event, cantidad) {
        event.preventDefault();
        const form = event.target;
        Swal.fire({
            title: '¿Contabilización Masiva?',
            html: `Se procesarán <strong>${cantidad}</strong> facturas.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff9f43',
            cancelButtonColor: '#4b4b4b',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Sí, procesar todo',
			cancelButtonText: '<i class="fas fa-close me-2"></i>cancelar',
            background: '#1e1e1e',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    }

// Función de cierre modificada - SOLICITA BACKUP PRIMERO
function confirmarCierre() {
    // Prevenir doble clic
    if (cierreEnProceso) {
        Swal.fire({
            icon: 'info',
            title: 'Cierre en proceso',
            text: 'Por favor espere, el cierre ya se está procesando...',
            confirmButtonText: 'Entendido',
            background: '#1e1e1e',
            color: '#fff'
        });
        return;
    }
    
    // 1. OBTENER DATOS DE PHP PRIMERO
    const pendientes = <?php echo count($facturas_pendientes); ?>;
    const aCerrar = <?php echo count($facturas_a_cerrar); ?>;
    const importe = <?php echo $importe_a_cerrar; ?>;
    const nombreMes = "<?php echo $mes_actual_nombre . ' ' . $anio_actual; ?>"; 
    
    // 2. VALIDAR PENDIENTES (Bloqueante absoluto)
    if (pendientes > 0) {
        cierreEnProceso = false;
        if (botonCierre) {
            botonCierre.disabled = false;
            botonCierre.innerHTML = '<i class="fas fa-key me-2"></i> EJECUTAR CIERRE';
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Imposible Cerrar',
            html: `Existen <strong class="text-warning">${pendientes} facturas pendientes</strong>.<br>
                   Debe contabilizarlas o anularlas antes de proceder.`,
            confirmButtonText: '<i class="fas fa-clock me-2"></i> Ir a Pendientes',
            confirmButtonColor: 'var(--win-accent)',
            background: '#1e1e1e',
            color: '#fff'
        }).then(() => {
             document.querySelector('.border-warning')?.scrollIntoView({behavior: 'smooth'});
        });
        return;
    }
    
    // 3. VALIDAR CHECKBOXES ANTES DE CUALQUIER MODAL
    const checkboxes = document.querySelectorAll('.form-check-input');
    let allChecked = true;
    
    checkboxes.forEach(cb => {
        if (!cb.checked) allChecked = false;
    });
    
    if (!allChecked) {
        cierreEnProceso = false;
        if (botonCierre) {
            botonCierre.disabled = false;
            botonCierre.innerHTML = '<i class="fas fa-key me-2"></i> EJECUTAR CIERRE';
        }
        
        Swal.fire({
            icon: 'warning',
            title: 'Confirmación incompleta',
            text: 'Por favor, marque todas las casillas de verificación para continuar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: 'var(--win-accent)',
            background: '#1e1e1e',
            color: '#fff'
        });
        return;
    }
    
    // ========== NUEVO: SOLICITAR BACKUP ANTES DE CONTINUAR ==========
    // Llamar a la función que solicita backup primero
    solicitarBackupAntesDeCierre('mes');
}

// Función que ejecuta el cierre después del backup
function ejecutarCierreMes() {
    // Deshabilitar botón
    const botonCierre = document.querySelector('button[onclick="confirmarCierre()"]');
    if (botonCierre) {
        botonCierre.disabled = true;
        botonCierre.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
    }
    
    cierreEnProceso = true;
    
    const aCerrar = <?php echo count($facturas_a_cerrar); ?>;
    const importe = <?php echo $importe_a_cerrar; ?>;
    const nombreMes = "<?php echo $mes_actual_nombre . ' ' . $anio_actual; ?>"; 
    
    // CASO ESPECIAL: MES VACÍO (0 Facturas)
    if (aCerrar === 0) {
        Swal.fire({
            title: '¿Cerrar mes sin movimiento?',
            html: `
                <div class="text-start">
                    <div class="alert alert-secondary border-0 p-3 mb-3" style="background: rgba(255,255,255,0.1); color: #ccc;">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        No se han encontrado facturas <strong>Contabilizadas</strong> ni <strong>Pagadas</strong> en este mes.
                    </div>
                    
                    <p class="mb-2">¿Desea cerrar el periodo <strong>${nombreMes}</strong> de todas formas?</p>
                    
                    <ul class="text-start small text-muted" style="list-style: none; padding-left: 0;">
                        <li class="mb-1"><i class="fas fa-angle-right me-2 text-secondary"></i>Se generará un registro de cierre en <strong>$0.00</strong>.</li>
                        <li class="mb-1"><i class="fas fa-angle-right me-2 text-secondary"></i>El sistema <strong>avanzará al siguiente mes</strong>.</li>
                        <li class="mb-0"><i class="fas fa-angle-right me-2 text-secondary"></i>Esta operación es irreversible.</li>
                    </ul>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Sí, cerrar en cero',
            cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
            confirmButtonColor: '#6c757d',
            cancelButtonColor: '#d33',
            background: '#1e1e1e',
            color: '#fff',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                enviarFormularioCierre();
            } else {
                cierreEnProceso = false;
                if (botonCierre) {
                    botonCierre.disabled = false;
                    botonCierre.innerHTML = '<i class="fas fa-key me-2"></i> EJECUTAR CIERRE';
                }
            }
        });
        return;
    }
    
    // CONFIRMACIÓN FINAL ESTÁNDAR (solo si hay facturas a cerrar)
    Swal.fire({
        title: '¿Confirmar Cierre Mensual?',
        html: `
            <div class="text-start">
                <p class="mb-3">Se cerrará el mes de <strong class="text-accent">${nombreMes}</strong>.</p>
                
                <div class="d-flex justify-content-between p-2 rounded mb-1" style="background: rgba(255,255,255,0.05)">
                    <span>Facturas:</span>
                    <strong>${aCerrar}</strong>
                </div>
                <div class="d-flex justify-content-between p-2 rounded mb-3" style="background: rgba(255,255,255,0.05)">
                    <span>Importe Total:</span>
                    <strong class="text-success">$${importe.toFixed(2)}</strong>
                </div>

                <div class="alert alert-danger border-0 p-2 mb-0 bg-opacity-10">
                    <small><i class="fas fa-exclamation-triangle me-1"></i> Esta acción no se puede deshacer.</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-lock me-2"></i>Ejecutar Cierre',
        cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar',
        confirmButtonColor: 'var(--win-accent)',
        cancelButtonColor: '#4b4b4b',
        background: '#1e1e1e',
        color: '#fff',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            enviarFormularioCierre();
        } else {
            cierreEnProceso = false;
            if (botonCierre) {
                botonCierre.disabled = false;
                botonCierre.innerHTML = '<i class="fas fa-key me-2"></i> EJECUTAR CIERRE';
            }
        }
    });
}


// Función de envío
function enviarFormularioCierre() {
    Swal.fire({
        title: 'Procesando...',
        text: 'Realizando cierre y calculando nuevo período',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1e1e1e',
        color: '#fff'
    });
    
    document.getElementById('confirmado').value = '1';
    document.getElementById('formCierre').submit();
}
    // Script Específico para Cierre de Diciembre
    <?php if ($es_cierre_diciembre): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '¡Fin de Año Fiscal!',
            html: `
                <div class="text-start">
                    <div class="mb-3 text-center">
                        <i class="fas fa-calendar-check fa-4x text-success mb-3"></i>
                        <h4 class="text-white">Se ha cerrado Diciembre <?php echo $anio_actual; ?></h4>
                    </div>
                    <p class="text-muted">El mes de diciembre ha sido cerrado correctamente, pero el sistema <strong>no ha avanzado de fecha</strong> automáticamente.</p>
                    <div class="alert alert-info border-0 bg-opacity-10" style="background: rgba(13, 202, 240, 0.1);">
                        <i class="fas fa-info-circle me-2"></i>
                        Para habilitar el periodo de <strong>Enero <?php echo $anio_actual + 1; ?></strong>, se recomienda realizar el Cierre Anual ahora.
                    </div>
                    <p class="fw-bold mt-3 text-center text-white">¿Desea ir a la pantalla de Cierre Anual?</p>
                </div>
            `,
            icon: 'success', // Icono de éxito porque el cierre mensual salió bien
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-arrow-right me-2"></i>Ir a Cierre Anual',
            cancelButtonText: 'Permanecer aquí',
            confirmButtonColor: '#0078d4',
            cancelButtonColor: '#4b4b4b',
            background: '#1e1e1e',
            color: '#fff',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'cierre_anual.php';
            }
        });
    });
    <?php endif; ?>


// =========================================================
// FUNCIÓN PARA SOLICITAR BACKUP ANTES DEL CIERRE
// =========================================================
function solicitarBackupAntesDeCierre() {
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
                            <span style="color: #e0e0e0;">Antes de realizar el cierre mensual, es OBLIGATORIO realizar un respaldo de la base de datos.</span>
                        </div>
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
            
            <style>
                .text-start { text-align: left; }
                .swal2-popup { max-width: 550px !important; }
            </style>
        `,
        icon: 'warning',
        iconColor: '#ffaa33',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-download me-2"></i> Realizar Salva AHORA',
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
            realizarBackupYContinuar();
        }
    });
}

// Función para realizar backup y luego continuar con el cierre
function realizarBackupYContinuar() {
    // Obtener el nombre del mes actual desde PHP
    const nombreMes = "<?php echo $mes_actual_nombre; ?>";
    const anioActual = "<?php echo $anio_actual; ?>";
    
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
    const nombreBackup = `SalvaAntesCierreMes_${nombreMes}${anioActual}_${fechaFormateada}-${horaFormateada}.sql`;
    
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
                    <div id="backupProgress" class="progress-bar" style="width: 0%; background-color: var(--win-accent);"></div>
                </div>
                <p id="backupStatusText" class="text-muted small mt-2">Iniciando...</p>
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
        const progressBar = document.getElementById('backupProgress');
        const statusText = document.getElementById('backupStatusText');
        if (progressBar) progressBar.style.width = progress + '%';
        if (statusText) {
            if (progress < 30) statusText.textContent = 'Preparando estructura de datos...';
            else if (progress < 60) statusText.textContent = 'Exportando tablas principales...';
            else if (progress < 90) statusText.textContent = 'Optimizando archivo de respaldo...';
            else statusText.textContent = 'Finalizando, descarga iniciará...';
        }
    }, 200);
    
    // Ejecutar backup con nombre personalizado
    const backupUrl = `export-import/download.php?type=backup&from_cierre=mes&custom_name=${encodeURIComponent(nombreBackup.replace('.sql', ''))}`;
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = backupUrl;
    document.body.appendChild(iframe);
    
    // Esperar a que se inicie la descarga
    setTimeout(() => {
        clearInterval(interval);
        
        const progressBar = document.getElementById('backupProgress');
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
                confirmButtonText: '<i class="fas fa-arrow-right me-2"></i> Continuar con el Cierre',
                confirmButtonColor: 'var(--win-accent)',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                allowOutsideClick: false
            }).then(() => {
                // Después del backup, continuar con el cierre
                ejecutarCierreMes();
            });
        }, 1000);
    }, 3000);
}
// Función que ejecuta el cierre después del backup
function ejecutarCierreMes() {
    // Deshabilitar botón
    const botonCierre = document.querySelector('button[onclick="confirmarCierre()"]');
    if (botonCierre) {
        botonCierre.disabled = true;
        botonCierre.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
    }
    
    cierreEnProceso = true;
    
    const aCerrar = <?php echo count($facturas_a_cerrar); ?>;
    const importe = <?php echo $importe_a_cerrar; ?>;
    const nombreMes = "<?php echo $mes_actual_nombre . ' ' . $anio_actual; ?>"; 
    
    // CASO ESPECIAL: MES VACÍO (0 Facturas)
    if (aCerrar === 0) {
        Swal.fire({
            title: '¿Cerrar mes sin movimiento?',
            html: `
                <div class="text-start">
                    <div class="alert alert-secondary border-0 p-3 mb-3" style="background: rgba(255,255,255,0.1); color: #ccc;">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        No se han encontrado facturas <strong>Contabilizadas</strong> ni <strong>Pagadas</strong> en este mes.
                    </div>
                    
                    <p class="mb-2">¿Desea cerrar el periodo <strong>${nombreMes}</strong> de todas formas?</p>
                    
                    <ul class="text-start small text-muted" style="list-style: none; padding-left: 0;">
                        <li class="mb-1"><i class="fas fa-angle-right me-2 text-secondary"></i>Se generará un registro de cierre en <strong>$0.00</strong>.</li>
                        <li class="mb-1"><i class="fas fa-angle-right me-2 text-secondary"></i>El sistema <strong>avanzará al siguiente mes</strong>.</li>
                        <li class="mb-0"><i class="fas fa-angle-right me-2 text-secondary"></i>Esta operación es irreversible.</li>
                    </ul>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Sí, cerrar en cero',
            cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
            confirmButtonColor: '#6c757d',
            cancelButtonColor: '#d33',
            background: '#1e1e1e',
            color: '#fff',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                enviarFormularioCierre();
            } else {
                cierreEnProceso = false;
                if (botonCierre) {
                    botonCierre.disabled = false;
                    botonCierre.innerHTML = '<i class="fas fa-key me-2"></i> EJECUTAR CIERRE';
                }
            }
        });
        return;
    }
    
    // CONFIRMACIÓN FINAL ESTÁNDAR (solo si hay facturas a cerrar)
    Swal.fire({
        title: '¿Confirmar Cierre Mensual?',
        html: `
            <div class="text-start">
                <p class="mb-3">Se cerrará el mes de <strong class="text-accent">${nombreMes}</strong>.</p>
                
                <div class="d-flex justify-content-between p-2 rounded mb-1" style="background: rgba(255,255,255,0.05)">
                    <span>Facturas:</span>
                    <strong>${aCerrar}</strong>
                </div>
                <div class="d-flex justify-content-between p-2 rounded mb-3" style="background: rgba(255,255,255,0.05)">
                    <span>Importe Total:</span>
                    <strong class="text-success">$${importe.toFixed(2)}</strong>
                </div>

                <div class="alert alert-danger border-0 p-2 mb-0 bg-opacity-10">
                    <small><i class="fas fa-exclamation-triangle me-1"></i> Esta acción no se puede deshacer.</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-lock me-2"></i>Ejecutar Cierre',
        cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar',
        confirmButtonColor: 'var(--win-accent)',
        cancelButtonColor: '#4b4b4b',
        background: '#1e1e1e',
        color: '#fff',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            enviarFormularioCierre();
        } else {
            cierreEnProceso = false;
            if (botonCierre) {
                botonCierre.disabled = false;
                botonCierre.innerHTML = '<i class="fas fa-key me-2"></i> EJECUTAR CIERRE';
            }
        }
    });
}

// Función de cierre modificada - Primero valida, luego backup, luego cierre
function confirmarCierre() {
    // Prevenir doble clic
    if (cierreEnProceso) {
        Swal.fire({
            icon: 'info',
            title: 'Cierre en proceso',
            text: 'Por favor espere, el cierre ya se está procesando...',
            confirmButtonText: 'Entendido',
            background: '#1e1e1e',
            color: '#fff'
        });
        return;
    }
    
    const botonCierre = document.querySelector('button[onclick="confirmarCierre()"]');
    
    // 1. OBTENER DATOS DE PHP
    const pendientes = <?php echo count($facturas_pendientes); ?>;
    
    // 2. VALIDAR PENDIENTES (Bloqueante absoluto)
    if (pendientes > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Imposible Cerrar',
            html: `Existen <strong class="text-warning">${pendientes} facturas pendientes</strong>.<br>
                   Debe contabilizarlas o anularlas antes de proceder.`,
            confirmButtonText: '<i class="fas fa-clock me-2"></i> Ir a Pendientes',
            confirmButtonColor: 'var(--win-accent)',
            background: '#1e1e1e',
            color: '#fff'
        }).then(() => {
            document.querySelector('.border-warning')?.scrollIntoView({behavior: 'smooth'});
        });
        return;
    }
    
    // 3. VALIDAR CHECKBOXES
    const checkboxes = document.querySelectorAll('.form-check-input');
    let allChecked = true;
    
    checkboxes.forEach(cb => {
        if (!cb.checked) allChecked = false;
    });
    
    if (!allChecked) {
        Swal.fire({
            icon: 'warning',
            title: 'Confirmación incompleta',
            text: 'Por favor, marque todas las casillas de verificación para continuar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: 'var(--win-accent)',
            background: '#1e1e1e',
            color: '#fff'
        });
        return;
    }
    
    // 4. TODO OK - SOLICITAR BACKUP ANTES DE CONTINUAR
    solicitarBackupAntesDeCierre();
}

// Función para enviar el formulario de cierre
function enviarFormularioCierre() {
    Swal.fire({
        title: 'Procesando...',
        text: 'Realizando cierre y calculando nuevo período',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1e1e1e',
        color: '#fff'
    });
    
    document.getElementById('confirmado').value = '1';
    document.getElementById('formCierre').submit();
}

// Funciones auxiliares para facturas individuales
function confirmContabilizar(event, numeroFactura) {
    event.preventDefault();
    const form = event.target;
    Swal.fire({
        title: '¿Contabilizar?',
        text: `Factura: ${numeroFactura}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28c76f',
        cancelButtonColor: '#4b4b4b',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Sí, procesar',
        cancelButtonText: '<i class="fas fa-close me-2"></i>cancelar',
        background: '#1e1e1e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
}

function confirmContabilizarTodas(event, cantidad) {
    event.preventDefault();
    const form = event.target;
    Swal.fire({
        title: '¿Contabilización Masiva?',
        html: `Se procesarán <strong>${cantidad}</strong> facturas.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff9f43',
        cancelButtonColor: '#4b4b4b',
        confirmButtonText: '<i class="fas fa-check me-2"></i> Sí, procesar todo',
        cancelButtonText: '<i class="fas fa-close me-2"></i>cancelar',
        background: '#1e1e1e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
}



</script>
</body>
</html>