<?php
//editar_factura.php - Windows 11 Dark Mode con TODAS las funcionalidades de facturas.php
ob_start();
require_once 'config/header.php';

//Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

//LIMPIAR VARIABLES DE ALERTA SI NO VENIMOS DE UN POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $mensajes_importantes = [];
    
    if (isset($_SESSION['swal_error']) && strpos($_SESSION['swal_error']['text'] ?? '', 'Consulte al administrador') !== false) {
        $mensajes_importantes['error'] = $_SESSION['swal_error'];
    }
    
    if (isset($_SESSION['swal_warning']) && strpos($_SESSION['swal_warning']['text'] ?? '', 'Consulte al administrador') !== false) {
        $mensajes_importantes['warning'] = $_SESSION['swal_warning'];
    }
    
    unset($_SESSION['swal_success'], $_SESSION['swal_error'], $_SESSION['swal_warning']);
    
    if (isset($mensajes_importantes['error'])) {
        $_SESSION['swal_error'] = $mensajes_importantes['error'];
    }
    
    if (isset($mensajes_importantes['warning'])) {
        $_SESSION['swal_warning'] = $mensajes_importantes['warning'];
    }
}

//Obtener configuración del tema
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

//Colores del tema
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

//Colores de acento disponibles (de facturas.php)
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

$id = $_GET['id'] ?? 0;

//Inicializar variables
$factura = [];
$detalles = [];
$clientes = [];
$servicios = [];
$tipos_pago = [];
$errores = [];
$total_facturas = 0;
$total_clientes = 0;
$total_categorias = 0;
$total_servicios = 0;
$total_usuarios = 0;
$estadisticas = ['total' => 0];
$error = '';

// ===== NUEVO: VARIABLE PARA CONTROLAR SI SE MUESTRA EL MODAL =====
$mostrar_modal_no_permiso = false;
// Inicialización de variables que se usan en la vista
$es_factura_historica = false;
$mostrar_alerta_periodo = false;
$solo_eliminar = false;
$formulario_deshabilitado = false;
$alerta_periodo_tipo = '';
$alerta_periodo_mensaje = '';
$fecha_min = '';
$fecha_max = '';
$fecha_readonly = false;

// ==================== OBTENER CONFIGURACIÓN DE CIERRE ====================
// Obtener la fecha maestra de la base de datos
$mes_cierre_num = obtenerMesCierreOperaciones();
$anio_cierre_num = obtenerAnioCierreOperaciones();
// Si por error devuelve null, usamos la fecha actual como fallback
$fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d'); 
$timestamp_op = strtotime($fecha_op_raw);

// Variables de tiempo Maestras (Sincronizadas con la BD)
$anio_actual = date('Y', $timestamp_op);
$mes_actual  = date('n', $timestamp_op);
$dia_actual  = date('j'); // Día actual del sistema
$total_dias_mes = date('t', $timestamp_op);

// Fechas límite según cierre configurado
$primer_dia_mes_cierre = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, 1);
$ultimo_dia_mes_cierre = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, 
                                 min($total_dias_mes, date('t', strtotime($primer_dia_mes_cierre))));

$hoy_cierre = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, 
                      min($dia_actual, $total_dias_mes));
$timestamp_combinado = strtotime($hoy_cierre);

// Mes en español para display
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[$mes_actual - 1];

try {
    $db = Database::getConnection();
    
    //Configurar zona horaria para Cuba
    date_default_timezone_set('America/New_York'); 

    //Obtener usuario actual
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
    
    //Verificar si el usuario tiene permisos especiales (rol_id = 1,3,4)
    $permiso_especial = false;
    if (isset($usuario['rol_id']) && ($usuario['rol_id'] == 1 || $usuario['rol_id'] == 3 || $usuario['rol_id'] == 4)) {
        $permiso_especial = true;
    }
    
    // ===== NUEVO: SI NO TIENE PERMISOS ESPECIALES, ACTIVAR MODAL =====
if (!$permiso_especial) {
	$mostrar_modal_no_permiso = true;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Acceso Denegado</title>
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        <script src="js/sweetalert211.js"></script>
    </head>
    <body>
    <script>
        Swal.fire({
            title: 'Acceso Denegado',
            html: 'No tiene permisos para editar facturas.<br><br><strong>Consulte al administrador del sistema</strong> si necesita acceso.',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-arrow-left me-2"></i>Volver a Facturas',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            window.location.href = 'facturas.php';
        });
    </script>
    </body>
    </html>
    <?php
    exit();
} else {
        // Solo continuamos con el resto del código si tiene permisos
        
        //Determinar permisos según rol 
        $esAdmin = ($usuario['rol_id'] == 1);
        $esEditor = ($usuario['rol_id'] == 3);
        $esSuper = ($usuario['rol_id'] == 4);
        
        $esSoloLectura = false; // Ya verificamos que tiene permisos
        
        //Permisos específicos (para Quick Actions)
        $puedeEditarFacturas = true; // Ya tiene permisos si llegó aquí
        $puedeContabilizar = true;
        $puedeAnular = true;
        
        // ==================== OBTENER ESTADÍSTICAS PARA SIDEBAR (de facturas.php) ====================
        try {
            $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
            $stmt = $db->prepare($sql_facturas_total);
            $stmt->execute();
            $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al contar facturas: " . $e->getMessage());
            $total_facturas = 0;
        }
        
        try {
            $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
            $stmt = $db->prepare($sql_clientes_total);
            $stmt->execute();
            $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al contar clientes: " . $e->getMessage());
            $total_clientes = 0;
        }
        
        try {
            $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
            $stmt = $db->prepare($sql_categorias_total);
            $stmt->execute();
            $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al contar categorías: " . $e->getMessage());
            $total_categorias = 0;
        }
        
        try {
            $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
            $stmt = $db->prepare($sql_servicios_total);
            $stmt->execute();
            $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al contar servicios: " . $e->getMessage());
            $total_servicios = 0;
        }
        
        try {
            $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
            $stmt = $db->prepare($sql_usuarios_total);
            $stmt->execute();
            $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al contar usuarios: " . $e->getMessage());
            $total_usuarios = 0;
        }
        
        //Total registros para histórico
        try {
            $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
            $stmt = $db->query($sql_total);
            $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al contar histórico: " . $e->getMessage());
            $estadisticas['total'] = 0;
        }
        // ==================== FIN ESTADÍSTICAS SIDEBAR ====================
        
        //Obtener datos de la factura CON información de usuarios (nombre completo)
        $sql = "SELECT f.*, 
                c.nombre as cliente_nombre, 
                t.descripcion as tipo_pago_desc,
                CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre_completo,
                CONCAT(uc.nombre, ' ', uc.apellidos) as usuario_contabilizacion_nombre_completo,
                f.fecha_emision,
                f.ref_pago,
                f.fecha_pago  -- Aquí agregas la fecha de pago
                FROM tbl_fact f 
                LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                LEFT JOIN tipos_pago t ON f.tipo_pago_id = t.id
                LEFT JOIN clasif_usuarios u ON f.usuario_id = u.id
                LEFT JOIN clasif_usuarios uc ON f.usuario_contabilizacion = uc.id
                WHERE f.id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
		
		        // 1. OBTENER FECHA INICIO Y VALIDAR SI ES HISTÓRICA
        $fecha_inicio_operaciones = obtenerFechaInicioOperaciones(); // Asegúrate que esta función exista en tu header/config
        
        $es_factura_historica = false;
        $fecha_factura_timestamp = strtotime($factura['fecha_emision']);
        $fecha_inicio_timestamp = strtotime($fecha_inicio_operaciones);

        // Si es anterior al inicio de operaciones -> BLOQUEO TOTAL
        if ($fecha_factura_timestamp < $fecha_inicio_timestamp) {
            $es_factura_historica = true;
            $solo_eliminar = true; 
            $formulario_deshabilitado = true;
        }

// Verificar si la factura ya tiene fecha de pago
$tiene_fecha_pago = !empty($factura['fecha_pago']);

        // 2. CONFIGURACIÓN VISUAL (Colores para PAGADA vs ANULADA)
        $estado_actual = strtoupper($factura['estado']);
        
        // Variables por defecto
        $bloqueo_estilo = 'danger'; // Rojo
        $bloqueo_icono = 'fa-ban';
        $bloqueo_titulo = 'EDICIÓN BLOQUEADA';
        $bloqueo_mensaje = '';

        if ($estado_actual == 'PAGADA') {
            $bloqueo_estilo = 'primary'; // Azul (Info/Éxito)
            $bloqueo_icono = 'fa-check-circle';
            $bloqueo_titulo = 'FACTURA PAGADA - CICLO CERRADO';
            $bloqueo_mensaje = 'El ciclo financiero ha concluido exitosamente. Por integridad contable, la edición está deshabilitada.';
        } elseif ($estado_actual == 'ANULADA') {
            $bloqueo_estilo = 'danger'; // Rojo (Error)
            $bloqueo_icono = 'fa-ban';
            $bloqueo_titulo = 'FACTURA ANULADA';
            $bloqueo_mensaje = 'El documento ha sido anulado y no admite modificaciones.';
        }
		
        
        if (!$factura) {
            $_SESSION['swal_error'] = [
                'title' => 'Factura no encontrada',
                'text' => 'La factura solicitada no existe en el sistema.',
                'icon' => 'error',
                'confirmButtonText' => '<i class="fas fa-file me-2"></i>Volver a facturas',
                'redirect' => 'facturas.php'
            ];
            header('Location: facturas.php');
            exit();
        }


$es_factura_historica = false;
$fecha_factura_timestamp = strtotime($factura['fecha_emision']);
$fecha_inicio_timestamp = strtotime($fecha_inicio_operaciones);

if ($fecha_factura_timestamp < $fecha_inicio_timestamp) {
    $es_factura_historica = true;
    // Forzamos modo solo lectura/eliminar para que no se vea el formulario
    $solo_eliminar = true; 
    $formulario_deshabilitado = true;
}

        //Debug temporal
        
        //Verificar si la factura está contabilizada - Mostrar SweetAlert si no puede editarla
        if ($factura['estado'] == 'CONTABILIZADA' && !$permiso_especial) {
            $_SESSION['swal_warning'] = [
                'title' => 'Factura Contabilizada',
                'text' => 'Esta factura ya está CONTABILIZADA y no puede ser modificada.<br><br><strong>Consulte al administrador general del sistema</strong> si necesita realizar cambios.',
                'icon' => 'warning',
                'confirmButtonText' => '<i class="fas fa-check me-2"></i>Entendido',
                'redirect' => 'ver_factura.php?id=' . $id
            ];
            header('Location: ver_factura.php?id=' . $id);
            exit();
        }
        
        //Si está anulada, también mostrar SweetAlert si no puede editarla
        if ($factura['estado'] == 'ANULADA' && !$permiso_especial) {
            $_SESSION['swal_error'] = [
                'title' => 'Factura Anulada',
                'text' => 'No se puede editar una factura ANULADA.<br><br><strong>Consulte al administrador general del sistema</strong> si necesita realizar cambios.',
                'icon' => 'error',
                'confirmButtonText' => '<i class="fas fa-check me-2"></i>Entendido',
                'redirect' => 'ver_factura.php?id=' . $id
            ];
            header('Location: ver_factura.php?id=' . $id);
            exit();
        }
        
        //Obtener detalles de la factura
        $sql_detalles = "SELECT d.*, s.descripcion as servicio_desc, s.codigo as servicio_codigo
                        FROM tbl_fact_detalle d
                        LEFT JOIN clasif_serv s ON d.servicio_id = s.id
                        WHERE d.factura_id = ?
                        ORDER BY d.id";
        $stmt_detalles = $db->prepare($sql_detalles);
        $stmt_detalles->execute([$id]);
        $detalles = $stmt_detalles->fetchAll();
        
        //Obtener clientes
        $sql_clientes = "SELECT id, codigo, ContratoNo, nombre, NIT FROM clasif_clientes ORDER BY nombre";
        $stmt_clientes = $db->query($sql_clientes);
        $clientes = $stmt_clientes->fetchAll();
        
        //Obtener servicios activos
        $sql_servicios = "SELECT s.*, c.descripcion as categoria 
                        FROM clasif_serv s 
                        LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id 
                        WHERE s.activo = 1 
                        ORDER BY s.descripcion";
        $stmt_servicios = $db->query($sql_servicios);
        $servicios = $stmt_servicios->fetchAll();
        
        //Obtener tipos de pago
        $sql_tipos_pago = "SELECT * FROM tipos_pago ORDER BY descripcion";
        $stmt_tipos_pago = $db->query($sql_tipos_pago);
        $tipos_pago = $stmt_tipos_pago->fetchAll();
        
        // ==================== LÓGICA DE CONTROL DE FECHAS MEJORADA ====================
        // Determinar período de la factura basado en fecha maestra
        $fecha_emision_db = date('Y-m-d', strtotime($factura['fecha_emision']));
        $mes_factura = date('n', strtotime($fecha_emision_db));
        $ano_factura = date('Y', strtotime($fecha_emision_db));
        
        // Determinar si la factura está en periodo de cierre actual
        $es_mes_cierre_actual = ($mes_factura == $mes_cierre_num && $ano_factura == $anio_cierre_num);
        $es_mes_anterior = false;
        $es_mes_posterior = false;
        
        // Determinar si es mes anterior o posterior
        $timestamp_factura = strtotime($fecha_emision_db);
        if ($timestamp_factura < strtotime($primer_dia_mes_cierre)) {
            $es_mes_anterior = true;
        } elseif ($timestamp_factura > strtotime($ultimo_dia_mes_cierre)) {
            $es_mes_posterior = true;
        }
        
        // Variables de control
        $solo_eliminar = false;
        $fecha_readonly = false;
        $fecha_min = '';
        $fecha_max = '';
        $formulario_deshabilitado = false;
        $mensaje_restriccion_fecha = '';
        $mostrar_alerta_periodo = false;
        $alerta_periodo_tipo = ''; // 'warning', 'danger', 'info'
        $alerta_periodo_mensaje = '';
        
        // Obtener estado actual
        $estado_actual = strtoupper($factura['estado']);
        
        // ==================== APLICAR REGLAS SEGÚN FECHA MAESTRA ====================
        if ($estado_actual == 'PAGADA' || $estado_actual == 'ANULADA') {
            // CASO 1: Factura PAGADA/ANULADA - Solo eliminar
            $solo_eliminar = true;
            $fecha_readonly = true;
            $formulario_deshabilitado = true;
            
        } elseif ($estado_actual == 'PENDIENTE') {
            // CASO 2: Factura PENDIENTE
            if ($es_mes_cierre_actual) {
                // Factura del mes de cierre ACTUAL - EDITABLE
                $fecha_readonly = false;
                $fecha_min = $primer_dia_mes_cierre;
                $fecha_max = $ultimo_dia_mes_cierre;
                $mensaje_restriccion_fecha = "Período de cierre actual: " . 
                                            $mes_actual_es . " " . $anio_cierre_num;
            } elseif ($es_mes_anterior) {
                // Factura de mes ANTERIOR al cierre - NO EDITABLE
                $fecha_readonly = true;
                $formulario_deshabilitado = true;
                $mostrar_alerta_periodo = true;
                $alerta_periodo_tipo = 'danger';
                $alerta_periodo_mensaje = "Factura del mes anterior al período de cierre actual.";
            } elseif ($es_mes_posterior) {
                // Factura de mes POSTERIOR al cierre - EDITABLE CON RESTRICCIONES
                $fecha_readonly = true;
                $mostrar_alerta_periodo = true;
                $alerta_periodo_tipo = 'warning';
                $alerta_periodo_mensaje = "Factura de mes posterior al período de cierre. " .
                                        "Consulte al administrador si necesita modificar.";
            }
            
        } elseif ($estado_actual == 'CONTABILIZADA') {
            // CASO 3: Factura CONTABILIZADA
            if ($permiso_especial) {
                // Usuario con permisos especiales
                if ($es_mes_cierre_actual) {
                    // Contabilizada en mes de cierre ACTUAL - EDITABLE con restricciones
                    $fecha_readonly = false;
                    $fecha_min = $primer_dia_mes_cierre;
                    $fecha_max = $ultimo_dia_mes_cierre;
                    $mensaje_restriccion_fecha = "Factura contabilizada - Edición restringida al período de cierre actual";
                    $mostrar_alerta_periodo = true;
                    $alerta_periodo_tipo = 'warning';
                    $alerta_periodo_mensaje = "Modo administrador: Editando factura contabilizada.";
                } elseif ($es_mes_anterior) {
                    // Contabilizada en mes ANTERIOR - NO EDITABLE
                    $fecha_readonly = true;
                    $formulario_deshabilitado = true;
                    $mostrar_alerta_periodo = true;
                    $alerta_periodo_tipo = 'danger';
                    $alerta_periodo_mensaje = "Factura contabilizada en período anterior. No editable.";
                } elseif ($es_mes_posterior) {
                    // Contabilizada en mes POSTERIOR - CONSULTAR ADMIN
                    $fecha_readonly = true;
                    $formulario_deshabilitado = true;
                    $mostrar_alerta_periodo = true;
                    $alerta_periodo_tipo = 'danger';
                    $alerta_periodo_mensaje = "Factura contabilizada en período posterior. " .
                                            "Consulte al administrador principal.";
                }
            } else {
                // Usuario sin permisos especiales - NO EDITABLE
                $fecha_readonly = true;
                $formulario_deshabilitado = true;
                $mostrar_alerta_periodo = true;
                $alerta_periodo_tipo = 'danger';
                $alerta_periodo_mensaje = "Factura contabilizada. Sin permisos para editar.";
            }
        }
        
        
        //Procesar actualización (solo si tiene permisos)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $db->beginTransaction();
                
                //Validar datos básicos
                $cliente_id = $_POST['cliente_id'] ?? 0;
                $tipo_pago_id = $_POST['tipo_pago_id'] ?? 0;
                $fecha_emision = $_POST['fecha_emision'] ?? '';
                $observaciones = $_POST['observaciones'] ?? '';
				$observaciones = preg_replace('/[\x00-\x1F\x7F]/', ' ', $observaciones);
				$observaciones = trim($observaciones);
                
                // Validar que la fecha esté dentro del período de cierre permitido
                if (empty($fecha_emision)) {
                    $errores[] = 'La fecha de emisión es obligatoria';
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_emision)) {
                    $errores[] = 'La fecha de emisión tiene un formato inválido';
                } elseif (!$fecha_readonly && !empty($fecha_min) && !empty($fecha_max)) {
                    $fecha_seleccionada = strtotime($fecha_emision);
                    $fecha_min_ts = strtotime($fecha_min);
                    $fecha_max_ts = strtotime($fecha_max);
                    
                    if ($fecha_seleccionada < $fecha_min_ts || $fecha_seleccionada > $fecha_max_ts) {
                        $errores[] = 'La fecha de emisión debe estar dentro del período de cierre (' . 
                                    date('d/m/Y', $fecha_min_ts) . ' - ' . date('d/m/Y', $fecha_max_ts) . ')';
                    }
                }
                
                if (empty($cliente_id)) {
                    $errores[] = 'Debe seleccionar un cliente';
                }
                
                if (empty($tipo_pago_id)) {
                    $errores[] = 'Debe seleccionar un tipo de pago';
                }
                
                //Validar detalles
                $detalles_post = $_POST['detalles'] ?? [];
                $detalles_validos = [];
                
                if (empty($detalles_post)) {
                    $errores[] = 'Debe agregar al menos un servicio';
                } else {
                    foreach ($detalles_post as $detalle) {
                        $servicio_id = $detalle['servicio_id'] ?? 0;
                        $cantidad = floatval($detalle['cantidad'] ?? 0);
                        $precio_unitario = floatval($detalle['precio_unitario'] ?? 0);
                        
                        if ($servicio_id && $cantidad > 0 && $precio_unitario > 0) {
                            $detalles_validos[] = [
                                'servicio_id' => $servicio_id,
                                'cantidad' => $cantidad,
                                'precio_unitario' => $precio_unitario,
                                'total_linea' => $cantidad * $precio_unitario
                            ];
                        }
                    }
                    
                    if (empty($detalles_validos)) {
                        $errores[] = 'Debe agregar al menos un servicio válido';
                    }
                }
                
                //Si no hay errores, proceder con la actualización
                if (empty($errores)) {
                    //Calcular totales (SIN IVA)
                    $subtotal = 0;
                    foreach ($detalles_validos as $detalle) {
                        $subtotal += $detalle['total_linea'];
                    }
                    $total_general = $subtotal; //Total general = subtotal (sin IVA)
                    
                    //Verificar nuevamente que la factura no esté contabilizada o anulada
                    //SOLO si el usuario NO tiene permisos especiales
                    if (!$permiso_especial) {
                        $sql_check_state = "SELECT estado FROM tbl_fact WHERE id = ? FOR UPDATE";
                        $stmt_check_state = $db->prepare($sql_check_state);
                        $stmt_check_state->execute([$id]);
                        $current_state = $stmt_check_state->fetchColumn();
                        
                        if ($current_state == 'CONTABILIZADA') {
                            throw new Exception("La factura fue contabilizada mientras se editaba. No se pueden guardar cambios.");
                        }
                        
                        if ($current_state == 'ANULADA') {
                            throw new Exception("La factura fue anulada mientras se editaba. No se pueden guardar cambios.");
                        }
                    }
                    
                    //Preparar la consulta de actualización - SIEMPRE actualizar usuario_contabilizacion
                    $sql_update = "UPDATE tbl_fact SET 
                      cliente_id = ?, 
                      tipo_pago_id = ?, 
                      fecha_emision = ?, 
                      observaciones = ?, 
                      subtotal = ?, 
                      total_general = ?,
                      usuario_id = ?,
                      usuario_contabilizacion = ?";
                    
                    $params = [
                        $cliente_id,
                        $tipo_pago_id,
                        $fecha_emision,  // <-- Agregar aquí
                        $observaciones,
                        $subtotal,
                        $total_general,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_id']
                    ];
                    
                    //Si el estado es CONTABILIZADA, actualizar fecha_contabilizacion
                    if ($factura['estado'] == 'CONTABILIZADA') {
                        $sql_update .= ", fecha_contabilizacion = ?";
                        $params[] = date('Y-m-d H:i:s');
                    }
                    
                    $sql_update .= " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt_update = $db->prepare($sql_update);
                    $result = $stmt_update->execute($params);
                    
                    if (!$result) {
                        throw new Exception("Error al actualizar la factura en la base de datos");
                    }
                    
                    //Eliminar detalles antiguos
                    $sql_delete_detalles = "DELETE FROM tbl_fact_detalle WHERE factura_id = ?";
                    $stmt_delete = $db->prepare($sql_delete_detalles);
                    $stmt_delete->execute([$id]);
                    
                    //Insertar nuevos detalles
                    $sql_insert_detalle = "INSERT INTO tbl_fact_detalle 
                                          (factura_id, servicio_id, cantidad, precio_unitario, total_linea) 
                                          VALUES (?, ?, ?, ?, ?)";
                    
                    $stmt_insert = $db->prepare($sql_insert_detalle);
                    foreach ($detalles_validos as $detalle) {
                        $stmt_insert->execute([
                            $id,
                            $detalle['servicio_id'],
                            $detalle['cantidad'],
                            $detalle['precio_unitario'],
                            $detalle['total_linea']
                        ]);
                    }
                    
                    //Registrar actividad
                    $descripcion_log = 'Factura ' . $factura['no_fact'] . ' actualizada - Total: $' . number_format($total_general, 2);
                    
                    if ($factura['estado'] == 'CONTABILIZADA') {
                        $descripcion_log .= ' - Fecha de contabilización actualizada a: ' . date('d/m/Y H:i:s');
                    }
                    
                    if ($permiso_especial) {
                        $descripcion_log .= ' (Editada con permisos especiales)';
                    }
                    
                    $sql_log = "INSERT INTO historico_operaciones 
                               (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                               VALUES (?, ?, ?, ?, ?)";
                    $stmt_log = $db->prepare($sql_log);
                    $stmt_log->execute([
                        'EDITAR_FACTURA',
                        $descripcion_log,
                        $_SESSION['usuario_id'],
                        $_SESSION['usuario_nombre'] ?? 'Usuario',
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $db->commit();
                    
                    //Mensaje de éxito
                    $mensaje_exito = 'Factura ' . $factura['no_fact'] . ' actualizada correctamente.';
                    
                    if ($factura['estado'] == 'CONTABILIZADA') {
                        $mensaje_exito .= '<br><small>Fecha de contabilización actualizada automáticamente.</small>';
                    }
                    
                    if ($permiso_especial) {
                        $mensaje_exito .= '<br><small>Operación realizada con permisos especiales de administrador.</small>';
                    }

// Verificar si hay que redirigir sin mensaje (para contabilizar) - ESTO DEBE IR PRIMERO Y DENTRO DEL TRY
$redirect_sin_mensaje = isset($_POST['redirect_to_contabilizar']) && $_POST['redirect_to_contabilizar'] == '1';

if ($redirect_sin_mensaje) {
    // Redirigir a editar_factura.php con un parámetro especial
    header('Location: editar_factura.php?id=' . $id . '&guardado=1&contabilizar=1');
    exit();
}


                    $_SESSION['swal_success'] = [
                        'title' => '¡Éxito!',
                        'html' => $mensaje_exito,
                        'icon' => 'success',
                        'confirmButtonText' => '<i class="fas fa-arrow-right me-1"></i>Continuar',
                        'redirect' => 'ver_factura.php?id=' . $id
                    ];
                    header('Location: ver_factura.php?id=' . $id);
                    exit();
                }
				
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error al actualizar factura ID {$id}: " . $e->getMessage());
                $errores[] = 'Error al actualizar la factura: ' . $e->getMessage();
                
                $_SESSION['swal_error'] = [
                    'title' => 'Error al guardar',
                    'text' => 'Error al actualizar la factura: ' . $e->getMessage() . '<br><br><strong>Consulte al administrador general del sistema</strong> si el problema persiste.',
                    'icon' => 'error',
                    'confirmButtonText' => '<i class="fas fa-check me-1"></i>Entendido'
                ];
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error al cargar datos: " . $e->getMessage());
    $_SESSION['swal_error'] = [
        'title' => 'Error del sistema',
        'text' => "Error al cargar los datos de la factura.<br><br><strong>Consulte al administrador general del sistema</strong>.",
        'icon' => 'error',
        'confirmButtonText' => '<i class="fas fa-arrow-left me-2"></i>Volver',
        'redirect' => 'facturas.php'
    ];
    // No salimos aquí, dejamos que se muestre el modal
}

$mostrarQuickActionsEdicion = false;
if ($permiso_especial && !$solo_eliminar && !$formulario_deshabilitado) {
    $mostrarQuickActionsEdicion = true;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Factura - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Select2 -->
    <link href="css/select2.min.css" rel="stylesheet" />
    
    <!-- Chart.js -->
    <script src="js/chart.js"></script>
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>

<!-- MDTimePicker para el reloj analógico -->
<link rel="stylesheet" href="css/mdtimepicker.css">
<script src="js/mdtimepicker.min.js"></script>
    <!-- Windows 11 Styles (estilos compartidos con facturas.php) -->
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

        /* Estilos para tarjetas y formularios */
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

        .win-main-content .form-control, 
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
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
            margin-bottom: 0.5rem;
        }

        .win-main-content .table {
            color: var(--win-text-primary);
            border-color: var(--win-border_color);
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

        /* Botones */
        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .win-main-content .btn-primary {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-primary:hover {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
            border-color: color-mix(in srgb, var(--win-accent) 90%, black);
            transform: translateY(-1px);
        }

        .win-main-content .btn-outline-primary {
            color: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-outline-primary:hover {
            background: var(--win-accent);
            color: white;
        }

        .win-main-content .btn-outline-secondary {
            color: var(--win-text-secondary);
            border-color: var(--win-border-color);
        }

        .win-main-content .btn-outline-secondary:hover {
            background: var(--win-bg-tertiary);
            color: var(--win-text-primary);
        }

        /* Badges */
        .badge {
            border-radius: var(--win-radius-sm);
            font-weight: 500;
            padding: 0.35em 0.65em;
            font-size: 0.85em; /* Tamaño de fuente más pequeño */
        }

        /* Estados - Usar la misma clase .badge pero con modificadores */
        .badge.estado {
            padding: 0.4em 0.8em;
            font-size: 0.9em;
        }

        .estado-pendiente {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .estado-contabilizada {
            background: rgba(25, 135, 84, 0.15);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }

        .estado-anulada {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .estado-pagada {
            background: rgba(13, 110, 253, 0.15);
            color: #0d6efd;
            border: 1px solid rgba(13, 110, 253, 0.3);
        }

        /* Progress bars */
        .progress {
            background-color: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm);
            overflow: hidden;
        }

        .progress-bar {
            background-color: var(--win-accent);
        }

        /* Ajustes para tema oscuro */
        [data-theme="dark"] .text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.8;
        }

        [data-theme="dark"] small.text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.8;
        }

        /* Ajustes para tema claro */
        [data-theme="light"] .text-muted {
            color: #6c757d !important;
            opacity: 1;
        }

        /* Ajustes para botones de cerrar en tema oscuro */
        [data-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) !important;
            opacity: 0.8 !important;
        }

        [data-theme="dark"] .btn-close:hover {
            opacity: 1 !important;
        }

        /* Ajustes específicos para modales en tema oscuro */
        [data-theme="dark"] .modal-header .btn-close {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        /* Ajustes para botones de cerrar en alertas - CORREGIDO */
        [data-theme="dark"] .alert .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        [data-theme="dark"] .alert-danger .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) sepia(100%) saturate(500%) hue-rotate(300deg) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        [data-theme="dark"] .alert-warning .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) sepia(100%) saturate(500%) hue-rotate(40deg) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        [data-theme="dark"] .alert-info .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) sepia(100%) saturate(500%) hue-rotate(180deg) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        [data-theme="dark"] .alert-success .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) sepia(100%) saturate(500%) hue-rotate(120deg) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        /* Ajustes para tema claro (para que se vea bien también) */
        [data-theme="light"] .btn-close {
            opacity: 0.8 !important;
        }

        [data-theme="light"] .btn-close:hover {
            opacity: 1 !important;
        }

        [data-theme="light"] .alert-danger .btn-close {
            filter: sepia(100%) saturate(500%) hue-rotate(300deg) !important;
        }

        [data-theme="light"] .alert-warning .btn-close {
            filter: sepia(100%) saturate(500%) hue-rotate(40deg) !important;
        }

        [data-theme="light"] .alert-info .btn-close {
            filter: sepia(100%) saturate(500%) hue-rotate(180deg) !important;
        }

        [data-theme="light"] .alert-success .btn-close {
            filter: sepia(100%) saturate(500%) hue-rotate(120deg) !important;
        }

        /* Estilos específicos para esta página */
        .filtros-resumen-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .filtros-card {
            flex: 2;
        }

        .resumen-card {
            flex: 1;
            min-width: 300px;
        }

        .resumen-estado-item {
            padding: 8px 0;
            border-bottom: 1px solid var(--win-border-color);
        }

        .resumen-estado-item:last-child {
            border-bottom: none;
        }

        .estado-badge-small {
            font-size: 0.7em;
            padding: 3px 8px;
        }

        .totales-row {
            background-color: rgba(var(--win-accent-rgb), 0.1);
            font-weight: 600;
        }

        .totales-row td {
            border-top: 2px solid var(--win-accent) !important;
        }

        .resumen-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .resumen-total {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--win-accent);
        }

        /* Tooltips */
        .tooltip {
            --bs-tooltip-bg: var(--win-bg-tertiary);
            --bs-tooltip-color: var(--win-text-primary);
			z-index: 10000 !important; /* Asegura que flote sobre todo */
			opacity: 1 !important;
        }
.tooltip-inner {
    background-color: var(--win-accent) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3); /* Sombra más pronunciada tipo Win11 */
    font-size: 0.85rem;
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.2); /* Borde sutil */
}

/* Ajuste para que la flecha coincida con el color de acento */
.bs-tooltip-top .tooltip-arrow::before { border-top-color: var(--win-accent) !important; }
.bs-tooltip-bottom .tooltip-arrow::before { border-bottom-color: var(--win-accent) !important; }
.bs-tooltip-start .tooltip-arrow::before { border-left-color: var(--win-accent) !important; }
.bs-tooltip-end .tooltip-arrow::before { border-right-color: var(--win-accent) !important; }

/* La flechita del tooltip */
.tooltip-arrow::before {
    border-top-color: var(--win-accent) !important;
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

        /* Quick Actions (Botones flotantes) - MODIFICADO */
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
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        /* Acciones específicas para edición */
        .win-quick-actions-expanded .action-agregar {
            background: #28a745; /* Verde para agregar */
        }

        .win-quick-actions-expanded .action-guardar {
            background: #0078d4; /* Azul para guardar */
        }

        /* Animación para el icono del botón principal */
        #mainQuickAction i {
            transition: transform 0.3s ease;
        }

        #mainQuickAction.rotated i {
            transform: rotate(45deg);
        }

        /* Quick Actions Expandibles con animación circular */
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

        /* Dropdown estilos (EXACTAMENTE IGUAL A FACTURAS.PHP) */
        .dropdown-menu {
            background-color: var(--win-bg-secondary) !important;
            border: 1px solid var(--win-border_color) !important;
            border-radius: var(--win-radius) !important;
            box-shadow: var(--win-shadow) !important;
        }
        
        .dropdown-item {
            color: var(--win-text-primary) !important;
            transition: var(--win-transition) !important;
            border-radius: var(--win-radius-sm) !important;
            margin: 2px 4px !important;
            padding: 10px 16px !important;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: var(--win-accent-light) !important;
            color: var(--win-accent) !important;
        }
        
        .dropdown-header {
            color: var(--win-text-secondary) !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
        }
        
        .dropdown-divider {
            border-color: var(--win-border-color) !important;
            opacity: 0.5 !important;
        }
        
        .dropdown-footer {
            background-color: var(--win-bg-tertiary) !important;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .filtros-resumen-container {
                flex-direction: column;
            }
            .filtros-card, .resumen-card {
                width: 100%;
            }
        }

        @media (max-width: 992px) {
            .win-sidebar {
                transform: translateX(-100%);
            }
            
            .win-sidebar.open {
                transform: translateX(0);
            }
            
            .win-main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .win-theme-panel {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
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
            
            .table-responsive {
                font-size: 14px;
            }
            
            .detalle-row td {
                padding: 8px 4px;
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

        /* Estilos para Select2 (MEJORADOS) */
        .select2-container {
            width: 100% !important;
            z-index: 1055 !important;
        }

        .select2-container--open {
            z-index: 1060 !important;
        }

        .select2-container--default .select2-selection--single {
            background-color: var(--win-bg-tertiary) !important;
            border: 1px solid var(--win-border-color) !important;
            border-radius: var(--win-radius-sm) !important;
            height: 38px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--win-text-primary) !important;
            line-height: 36px !important;
            padding-left: 12px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            right: 8px !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--win-accent) !important;
            box-shadow: 0 0 0 0.25rem var(--win-accent-light) !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--win-accent) !important;
            color: white !important;
        }

        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: var(--win-bg-tertiary) !important;
            color: var(--win-text-primary) !important;
        }

        .select2-dropdown {
            background-color: var(--win-bg-secondary) !important;
            border: 1px solid var(--win-border-color) !important;
            color: var(--win-text-primary) !important;
            z-index: 1061 !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: var(--win-bg-tertiary) !important;
            border: 1px solid var(--win-border-color) !important;
            color: var(--win-text-primary) !important;
        }

        /* Estilos específicos para la página de edición */
        .detalle-row:hover {
            background-color: var(--win-bg-tertiary) !important;
        }

        .eliminar-detalle {
            color: #dc3545 !important;
            cursor: pointer !important;
            transition: color 0.3s !important;
        }

        .eliminar-detalle:hover {
            color: #c82333 !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--win-accent) !important;
            box-shadow: 0 0 0 0.2rem var(--win-accent-light) !important;
        }

        .total-box {
            background-color: var(--win-bg-tertiary) !important;
            border-radius: var(--win-radius) !important;
            padding: 20px !important;
            border-left: 4px solid var(--win-accent) !important;
            margin-bottom: 20px !important;
        }

        .cantidad-input, .precio-input {
            text-align: right !important;
        }

        .total-linea {
            font-weight: bold !important;
            text-align: right !important;
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

        /* Estilo para formulario deshabilitado */
        .formulario-deshabilitado {
            position: relative;
        }

        .formulario-deshabilitado::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.05);
            z-index: 10;
            border-radius: 8px;
        }

        /* PERMITIR que los botones funcionen incluso cuando el formulario está deshabilitado */
        .formulario-deshabilitado button,
        .formulario-deshabilitado a.btn,
        .formulario-deshabilitado input[type="button"],
        .formulario-deshabilitado input[type="submit"] {
            pointer-events: auto !important;
            opacity: 1 !important;
            position: relative;
            z-index: 20;
        }

        /* Deshabilitar solo los campos del formulario */
        .formulario-deshabilitado input:not([type="button"]):not([type="submit"]),
        .formulario-deshabilitado select,
        .formulario-deshabilitado textarea {
            pointer-events: none;
            background-color: var(--win-bg-tertiary);
            cursor: not-allowed;
        }

        /* Select2 para Bootstrap 5 theme */
        .select2-container--bootstrap-5 .select2-selection {
            background-color: var(--win-bg-tertiary) !important;
            border: 1px solid var(--win-border-color) !important;
            color: var(--win-text-primary) !important;
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            background-color: var(--win-bg-secondary) !important;
            border: 1px solid var(--win-border-color) !important;
            z-index: 1060 !important;
        }

        .select2-container--bootstrap-5 .select2-results__option {
            color: var(--win-text-primary) !important;
        }

        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--win-accent) !important;
            color: white !important;
        }

        .select2-container--bootstrap-5 .select2-search {
            background-color: var(--win-bg-tertiary) !important;
        }

        .select2-container--bootstrap-5 .select2-search__field {
            background-color: var(--win-bg-tertiary) !important;
            color: var(--win-text-primary) !important;
            border: 1px solid var(--win-border-color) !important;
        }

        /* Asegurar que Select2 se cierra correctamente */
        .select2-container--open .select2-dropdown--below {
            border-top: 1px solid var(--win-border-color) !important;
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
        
        <div class="win-theme-options">
            <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" 
                 data-theme="dark">
                <i class="fas fa-moon mb-2"></i>
                <div>Oscuro</div>
            </div>
            <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" 
                 data-theme="light">
                <i class="fas fa-sun mb-2"></i>
                <div>Claro</div>
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

    <!-- Navbar principal (EXACTAMENTE IGUAL A FACTURAS.PHP) -->
    <nav class="win-navbar mica-effect">
        <!-- Botón hamburguesa para móvil -->
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">EDITAR FACTURA - SISFACT PDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Acciones del navbar -->
        <a href="facturas.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Volver
        </a>
        
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
<?= renderNotificationsDropdown() ?>
        
        <!-- Perfil de usuario (EXACTAMENTE IGUAL A FACTURAS.PHP) -->
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

    <!-- Sidebar inmersivo (ACTUALIZADO con badges como facturas.php) -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
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
        
        <ul class="win-nav">
            <li class="win-nav-item">
                <a href="dashboard.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tachometer-alt"></i>
                    Dashboard<span class="win-nav-badge" 
              title="Período de Cierre Actual: <?php echo date('t', strtotime($primer_dia_mes_cierre)) . ' de ' . $mes_actual_es . ' ' . $anio_cierre_num; ?>" 
              style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
            P/Cierre: <?php echo date('d', strtotime($ultimo_dia_mes_cierre)); ?> / 
            <?php echo substr(($mes_actual_es ?? '-'), 0, 3) . ' ' . substr($anio_cierre_num, -2); ?>
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
                <a href="editar_factura.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Editar Facturas</span>
                    <span class="win-nav-badge">Fac No.<?php 
                        echo ($id ?? 0) . ' - ' . 
                             htmlspecialchars($factura['no_fact'] ?? 'N/A'); 
                    ?></span>
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
                    <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small>
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
    </nav>
<!-- ========================================== -->
<!-- MODAL DE FACTURA HISTÓRICA
<!-- ========================================== -->
<?php if ($es_factura_historica && !$mostrar_modal_no_permiso): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const facturaId = <?php echo json_encode($id); ?>;
        const noFactura = <?php echo json_encode($factura['no_fact'] ?? 'N/A'); ?>;
        const clienteNombre = <?php echo json_encode($factura['cliente_nombre'] ?? 'N/A'); ?>;
        const totalGeneral = <?php echo json_encode($factura['total_general'] ?? 0); ?>;
        const fechaEmision = <?php echo json_encode($factura['fecha_emision'] ?? ''); ?>;
        const tipoPagoDesc = <?php echo json_encode($factura['tipo_pago_desc'] ?? 'TRANSFERENCIA BANCARIA'); ?>;
        const tieneFechaPago = <?php echo $tiene_fecha_pago ? 'true' : 'false'; ?>;
        const fechaInicioOperaciones = <?php echo json_encode(date('d/m/Y', strtotime($fecha_inicio_operaciones))); ?>;
        const fechaFactura = <?php echo json_encode(date('d/m/Y', strtotime($factura['fecha_emision']))); ?>;
        
        let confirmButtonText = '<i class="fas fa-arrow-left me-2"></i>Volver al listado';
        let showCancelButton = false;
        let cancelButtonText = '';
        
        if (!tieneFechaPago) {
            showCancelButton = true;
            cancelButtonText = '<i class="fas fa-money-bill-wave me-2"></i>Registrar Pago';
        }
        
        Swal.fire({
            title: '⚠️ IMPOSIBLE EDITAR',
            html: `
                <div class="text-start mt-2" style="color: #e0e0e0;">
                    <div class="alert alert-dark border-secondary d-flex align-items-center" style="background: #2b2b2b; border-color: #444;">
                        <i class="fas fa-history fa-2x me-3 text-warning"></i>
                        <div>
                            <strong style="color: #ffc107;">Registro Histórico Cerrado</strong><br>
                            <span class="text-muted small">Esta factura es anterior al inicio de operaciones del sistema.</span>
                        </div>
                    </div>
                    <ul class="text-muted small mb-2" style="list-style: none; padding-left: 0;">
                        <li><i class="fas fa-calendar me-2 text-danger"></i> <strong>Fecha Factura:</strong> <span class="text-white">${fechaFactura}</span></li>
                        <li class="mt-1"><i class="fas fa-play-circle me-2 text-success"></i> <strong>Inicio Operaciones:</strong> <span class="text-warning">${fechaInicioOperaciones}</span></li>
                        ${!tieneFechaPago ? `
                        <li class="mt-2 text-info-emphasis">
                            <i class="fas fa-info-circle me-1 text-info"></i>
                            <span class="text-info">Esta factura aún no tiene registrado su pago.</span>
                        </li>
                        ` : `
                        <li class="mt-2 text-success-emphasis">
                            <i class="fas fa-check-circle me-1 text-success"></i>
                            <span class="text-success">Esta factura ya tiene registrado su pago.</span>
                        </li>
                        `}
                    </ul>
                    ${!tieneFechaPago ? `
                    <div class="alert alert-info mt-2 mb-0 py-2" style="background: #0d6efd20; border: 1px solid #0d6efd40; border-radius: 8px;">
                        <i class="fas fa-lightbulb me-2 text-info"></i>
                        <small class="text-info-emphasis">Puede registrar el pago de esta factura histórica si ya fue cobrada.</small>
                    </div>
                    ` : ''}
                </div>
            `,
            icon: 'error',
            background: '#151515',
            color: '#e0e0e0',
            confirmButtonText: confirmButtonText,
            confirmButtonColor: '#3d3d3d',
            showCancelButton: showCancelButton,
            cancelButtonText: cancelButtonText,
            cancelButtonColor: '#28a745',
            showCloseButton: true,           // ← MUESTRA LA "X" ARRIBA
            allowOutsideClick: false,        // ← NO CIERRA AL CLICK FUERA
            allowEscapeKey: true,            // ← ESC SÍ CIERRA (pero redirige)
            reverseButtons: false,
            backdrop: 'rgba(0,0,0,0.85)',
            width: '500px'
        }).then((result) => {
            // Si se confirma (click en "Volver al listado")
            if (result.isConfirmed) {
                window.location.href = 'facturas.php';
            } 
            // Si se cancela (click en "Registrar Pago")
            else if (result.dismiss === Swal.DismissReason.cancel) {
                if (typeof marcarComoPagada === 'function') {
                    marcarComoPagada(facturaId, noFactura, clienteNombre, parseFloat(totalGeneral), fechaEmision, tipoPagoDesc, true);
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: 'No se puede registrar el pago en este momento.',
                        icon: 'error',
                        background: '#151515',
                        color: '#e0e0e0',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
                    }).then(() => {
                        window.location.href = 'facturas.php';
                    });
                }
            }
            // Si se cierra con la X (dismiss === 'close') o con ESC (dismiss === 'esc')
            else if (result.dismiss === 'close' || result.dismiss === 'esc') {
                window.location.href = 'facturas.php';
            }
        });
    }, 100);
});
</script>
<?php endif; ?>
    
        <!-- Encabezado de página -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 mb-0" style="color: var(--win-text-primary);">
                    <i class="fas fa-edit me-2" style="color: var(--win-accent);"></i>Editar Factura No: <span class="fw-bold text-warning"><?php echo substr($factura['no_fact'] ?? '', -4); ?></span>
                </h1>
                <p class="text-muted mb-0">Número: <span class="fw-bold text-warning"><?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?></span> • 
                    Fecha Emisión: <span class="fw-bold text-warning"><?php echo !empty($factura['fecha_emision']) ? date('d/m/Y', strtotime($factura['fecha_emision'])) : 'N/A'; ?></span></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <?php if (!$mostrar_modal_no_permiso && isset($factura['estado'])): ?>
                <?php
                $badge_class = '';
                $icon = '';
                switch ($factura['estado']) {
                    case 'CONTABILIZADA':
                        $badge_class = 'bg-success';
                        $icon = '<i class="fas fa-check-circle me-1"></i>';
                        break;
                    case 'PENDIENTE':
                        $badge_class = 'bg-warning';
                        $icon = '<i class="fas fa-clock me-1"></i>';
                        break;
                    case 'ANULADA':
                        $badge_class = 'bg-danger';
                        $icon = '<i class="fas fa-ban me-1"></i>';
                        break;
                    case 'PAGADA':
                        $badge_class = 'bg-primary';
                        $icon = '<i class="fas fa-ban me-1"></i>';
                        break;
                    default:
                        $badge_class = 'bg-secondary';
                        $icon = '<i class="fas fa-question-circle me-1"></i>';
                }
                ?>
                <span class="badge <?php echo $badge_class; ?> me-2">
                    <?php echo $icon . htmlspecialchars($factura['estado']); ?>
                </span>
                <a href="ver_factura.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-eye me-1"></i>Ver Factura
                </a>
                <?php endif; ?>
                <a href="facturas.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-1"></i>Lista de Facturas
                </a>
            </div>
        </div>

        <!-- ===== ALERTAS DE PERÍODO DE CIERRE ===== -->
        <?php if ($mostrar_alerta_periodo && !$solo_eliminar): ?>
        <div class="alert alert-<?php echo $alerta_periodo_tipo; ?> alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas 
                    <?php 
                    if ($alerta_periodo_tipo == 'warning') echo 'fa-exclamation-triangle';
                    elseif ($alerta_periodo_tipo == 'danger') echo 'fa-ban';
                    else echo 'fa-info-circle';
                    ?> 
                    me-3 fa-2x"></i>
                <div>
                    <h5 class="alert-heading mb-1">
                        <?php 
                        if ($es_mes_anterior) echo 'FACTURA DE PERÍODO ANTERIOR';
                        elseif ($es_mes_posterior) echo 'FACTURA DE PERÍODO POSTERIOR';
                        else echo 'CONTROL DE PERÍODO';
                        ?>
                    </h5>
                    <p class="mb-0">
                        <strong>Período de cierre configurado:</strong> <?php echo $mes_actual_es . ' ' . $anio_cierre_num; ?><br>
                        <strong>Fecha factura:</strong> <?php echo date('d/m/Y', strtotime($fecha_emision_db)); ?><br>
                        <?php echo $alerta_periodo_mensaje; ?>
                    </p>
                    
                    <?php if ($permiso_especial && !$formulario_deshabilitado): ?>
                    <div class="mt-2 p-2 bg-info rounded">
                        <small><i class="fas fa-shield-alt me-1"></i> 
                            <strong>Modo administrador activo:</strong> Puede editar con restricciones.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- ===== INFORMACIÓN DE CIERRE EN FORMULARIO ===== -->
        <?php if (!$solo_eliminar): ?>
        <div class="alert alert-info mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-calendar-alt me-2"></i>
                    <strong>Período de cierre actual:</strong> 
                    <?php echo $mes_actual_es . ' ' . $anio_cierre_num; ?>
                </div>
                <div class="text-end">
                    <small>
                        <i class="fas fa-calendar-day me-1"></i>
                        Del <?php echo date('d', strtotime($primer_dia_mes_cierre)); ?> al 
                        <?php echo date('d', strtotime($ultimo_dia_mes_cierre)); ?> 
                        de <?php echo $mes_actual_es; ?>
                    </small>
                </div>
            </div>
            
            <?php if (!empty($mensaje_restriccion_fecha)): ?>
            <hr class="my-2">
            <small>
                <i class="fas fa-lock me-1"></i>
                <?php echo $mensaje_restriccion_fecha; ?>
            </small>
            <?php endif; ?>
        </div>
        <?php endif; ?>

<!-- ===== ALERT DINÁMICO (PAGADA = AZUL / ANULADA = ROJO) ===== -->
        <?php if ($solo_eliminar && !$es_factura_historica): ?>
        <div class="alert alert-<?php echo $bloqueo_estilo; ?> alert-dismissible fade show mb-4 shadow-sm" role="alert" 
             style="border-left: 5px solid var(--bs-<?php echo $bloqueo_estilo; ?>);">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <div class="rounded-circle bg-<?php echo $bloqueo_estilo; ?> p-3 text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas <?php echo $bloqueo_icono; ?> fa-lg"></i>
                    </div>
                </div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">
                        <?php echo $bloqueo_titulo; ?>
                    </h5>
                    <p class="mb-0 text-muted">
                        <?php echo $bloqueo_mensaje; ?>
                        <br>Solo está disponible la opción de <strong>eliminar la factura</strong> del sistema.
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($mostrar_modal_no_permiso): ?>
        <!-- Contenido para usuarios sin permisos (ya existe) -->
        <?php else: ?>

        <!-- Formulario principal - SOLO si NO es solo_eliminar -->
        <?php if (!$solo_eliminar): ?>
        <div class="<?php echo $formulario_deshabilitado ? 'formulario-deshabilitado' : ''; ?>">
            <form method="POST" action="" id="formFactura">
                
                <!-- 1. Información Básica -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-info-circle me-2"></i>Información Básica
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="fecha_emision" class="form-label">Fecha de Emisión *</label>
                                <div class="d-flex gap-2">
                                    <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" 
                                           value="<?php echo htmlspecialchars($fecha_emision_db); ?>" 
                                           
                                           <?php if (!$fecha_readonly && !empty($fecha_min) && !empty($fecha_max)): ?>
                                               min="<?php echo $fecha_min; ?>" 
                                               max="<?php echo $fecha_max; ?>"
                                           <?php else: ?>
                                               readonly
                                               style="background-color: var(--win-bg-tertiary); cursor: not-allowed; opacity: 0.7;"
                                               title="Fecha bloqueada por restricciones de período de cierre"
                                           <?php endif; ?>
                                           
                                           required>
                                    
                                    <?php if (!$fecha_readonly): ?>
                                        <button type="button" class="btn btn-outline-secondary flex-shrink-0" 
                                                onclick="setFechaCierre('fecha_emision')" 
                                                title="Establecer fecha dentro del período de cierre">
                                            <i class="fas fa-calendar-check"></i> Hoy
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($fecha_readonly): ?>
                                    <small class="text-danger mt-1 d-block">
                                        <i class="fas fa-lock"></i> 
                                        <?php 
                                        if ($es_mes_anterior) {
                                            echo 'Fecha fuera del período de cierre actual';
                                        } elseif ($es_mes_posterior) {
                                            echo 'Fecha posterior al período de cierre';
                                        } elseif ($estado_actual == 'CONTABILIZADA') {
                                            echo 'Fecha bloqueada - Factura contabilizada';
                                        } else {
                                            echo 'Fecha bloqueada por restricciones del sistema';
                                        }
                                        ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="fas fa-calendar-range"></i> 
                                        Solo fechas dentro del período de cierre
                                        (<span class="fw-bold text-warning"><?php echo date('d/m/Y', strtotime($fecha_min)); ?> - 
                                         <?php echo date('d/m/Y', strtotime($fecha_max)); ?></span>)
                                    </small>
                                <?php endif; ?>
                            </div>
                            
<!-- FILA 2: Cliente con buscador - MODIFICADO PARA INCLUIR BUSCADOR -->
<div class="col-md-6 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="form-label mb-0">Cliente *</label>
        
        <!-- Input Group: Buscador + Botón Limpiar -->
        <div class="input-group input-group-sm" style="width: 250px;">
            <input type="text" 
                   id="inputFiltroCliente"
                   class="form-control border-win" 
                   style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border-right: none;"
                   placeholder="🔍 Buscar por nombre, contrato, NIT..." 
                   autocomplete="off"
                   onkeyup="filtrarComboCliente(this.value)">
            
            <button class="btn btn-outline-secondary border-win" 
                    type="button"
                    onclick="limpiarFiltroCliente()"
                    data-bs-toggle="tooltip" 
                    title="Limpiar filtro"
                    style="background: var(--win-bg-tertiary); border-left: none; border-color: var(--win-border-color);">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <select class="form-select" id="cliente_id" name="cliente_id" <?php echo $formulario_deshabilitado ? 'disabled' : ''; ?>>
        <option value="">Seleccionar cliente...</option>
        <?php foreach ($clientes as $cliente): ?>
            <option value="<?php echo $cliente['id']; ?>" 
                    data-codigo="<?php echo htmlspecialchars($cliente['codigo'] ?? ''); ?>"
                    data-nombre="<?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>"
                    data-nit="<?php echo htmlspecialchars($cliente['NIT'] ?? ''); ?>"
                    data-contratono="<?php echo htmlspecialchars($cliente['ContratoNo'] ?? 'S/C'); ?>"
                    <?php echo ($factura['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(($cliente['ContratoNo'] ?? 'S/C') . ' - ' . ($cliente['nombre'] ?? '')); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <small class="text-info mt-1 d-block">
        <i class="fas fa-search me-1"></i> Escribe para filtrar la lista de clientes
    </small>
</div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="tipo_pago_id" class="form-label">Forma de Pago *</label>
                                <select class="form-select" name="tipo_pago_id" <?php echo $formulario_deshabilitado ? 'disabled' : ''; ?>>
                                    <option value="">Seleccione tipo de pago</option>
                                    <?php 
                                    $emoji_map = [
                                        'ABONO' => '💰',
                                        'ANTICIPO' => '⏳',
                                        'BILLETERA MÓVIL' => '📱',
                                        'CHEQUE CERTIFICADO' => '📄',
                                        'CRÉDITO COMERCIAL' => '💳',
                                        'DEPÓSITO BANCARIO' => '🏦',
                                        'EFECTIVO' => '💵',
                                        'LETRA DE CAMBIO' => '📜',
                                        'OTROS' => '❓',
                                        'PAGO CONTRA ENTREGA' => '🤝',
                                        'PAGO DIGITAL' => '📱',
                                        'PAGO EN LÍNEA' => '🌐',
                                        'PAGO MÓVIL' => '📱',
                                        'PAGO PARCIAL' => '🔢',
                                        'PAYPAL' => '🔵',
                                        'TARJETA DE CRÉDITO' => '💳',
                                        'TARJETA DE DÉBITO' => '💳',
                                        'TARJETA INTERNACIONAL' => '🌍',
                                        'TRANSFERENCIA ACH' => '🏦',
                                        'TRANSFERENCIA BANCARIA' => '🏦',
                                        'VARIOS MÉTODOS' => '🔄'
                                    ];
                                    
                                    foreach ($tipos_pago as $tipo): 
                                        $emoji = '💲';
                                        $descripcion_upper = strtoupper($tipo['descripcion']);
                                        foreach ($emoji_map as $key => $value) {
                                            if (strpos($descripcion_upper, $key) !== false) {
                                                $emoji = $value;
                                                break;
                                            }
                                        }
                                    ?>
                                        <option value="<?php echo $tipo['id']; ?>" <?php echo ($factura['tipo_pago_id'] == $tipo['id']) ? 'selected' : ''; ?>>
                                            <?php echo $emoji . ' ' . htmlspecialchars($tipo['descripcion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" 
                                          rows="1" placeholder="Observaciones adicionales..." <?php echo $formulario_deshabilitado ? 'readonly' : ''; ?>><?php echo htmlspecialchars($factura['observaciones'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 2. Detalles de servicios -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-list-alt me-2"></i>Servicios Facturados
                        </h6>
                        <?php if (!$formulario_deshabilitado): ?>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAgregarServicio" data-bs-toggle="tooltip" title="Agregar Otros Servicios a la Factura">
                            <i class="fas fa-plus me-1"></i>Agregar Servicio
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tablaDetalles">
<thead>
    <tr>
        <th width="50%">
            Servicio - Descripción
            <span class="badge bg-info ms-2" id="totalUnidadesBadge" 
                  style="font-size: 0.75rem; cursor: help;" 
                  title="Total de unidades de todos los servicios">
                0 unidades
            </span>
        </th>
        <th width="10%" class="text-center">Cant.</th>
        <th width="10%" class="text-end">P. Unitario</th>
        <th width="15%" class="text-end">Total</th>
        <th width="5%" class="text-center"></th>
    </tr>
</thead>
                                <tbody id="detallesBody">
                                    <?php if (is_array($detalles) && count($detalles) > 0): ?>
                                        <?php foreach ($detalles as $index => $detalle): ?>
                                            <tr class="detalle-row" data-index="<?php echo $index; ?>">
                                                <td>
                                                    <select class="form-select servicio-select" 
                                                            name="detalles[<?php echo $index; ?>][servicio_id]" 
                                                            data-index="<?php echo $index; ?>" 
                                                            <?php echo $formulario_deshabilitado ? 'disabled' : 'required'; ?>>
                                                        <option value="">Seleccionar servicio...</option>
                                                        <?php foreach ($servicios as $servicio): ?>
                                                            <option value="<?php echo $servicio['id']; ?>" 
                                                                data-precio="<?php echo $servicio['precio'] ?? $servicio['costo']; ?>"
                                                                data-servicio-nombre="<?php echo htmlspecialchars($servicio['descripcion']); ?>"
                                                                <?php echo ($detalle['servicio_id'] == $servicio['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($servicio['codigo'] . ' - ' . $servicio['descripcion']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control cantidad-input" 
                                                           name="detalles[<?php echo $index; ?>][cantidad]" 
                                                           value="<?php echo htmlspecialchars($detalle['cantidad'] ?? '1'); ?>" 
                                                           min="1" step="1" <?php echo $formulario_deshabilitado ? 'readonly disabled' : 'required'; ?>>
                                                </td>
<td>
    <input type="text" class="form-control precio-input text-end" 
           name="detalles[<?php echo $index; ?>][precio_unitario]" 
           value="<?php echo number_format($detalle['precio_unitario'] ?? 0, 2); ?>" 
           readonly style="background-color: var(--win-bg-tertiary);">
</td>
                                                <td>
                                                    <input type="text" class="form-control total-linea" 
                                                           value="<?php echo number_format($detalle['total_linea'] ?? 0, 2); ?>" 
                                                           readonly>
                                                </td>
                                                <td>
                                                    <?php if (!$formulario_deshabilitado): ?>
                                                    <button type="button" class="btn btn-sm btn-link eliminar-detalle" data-bs-toggle="tooltip" title="Eliminar Fila o Renglón" >
                                                        <i class="fas fa-trash text-danger"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr id="noDetalles">
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle me-2"></i>No hay servicios agregados
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
							
                        </div>
                    </div>
                </div>

<div class="total-box m-0">
    <div class="row mb-2">
        <div class="col">
            <span class="text-muted">Subtotal:</span>
        </div>
        <div class="col-auto">
            <span class="fw-bold" id="subtotalDisplay">$<?php echo number_format($factura['subtotal'] ?? 0, 2); ?></span>
        </div>
    </div>
    <div class="row mb-2">
        <div class="col">
            <span class="text-muted">Pcto. Dcto./Increm./Mora:</span>
        </div>
        <div class="col-auto">
            <span class="fw-bold" id="subtotalDisplay%Desc">0%</span>
        </div>
    </div>
    <hr class="my-2">
    <div class="row align-items-center mb-3">
        <div class="col">
            <span class="h5 m-0">TOTAL:</span>
        </div>
        <div class="col-auto">
            <span class="h4 text-primary fw-bold m-0" id="totalGeneralDisplay">$<?php echo number_format($factura['total_general'] ?? 0, 2); ?></span>
        </div>
    </div>
    
    <!-- NUEVO: Importe en Letras -->
    <div class="row">
        <div class="col-12">
            <div id="importeLetras" class="p-2 bg-dark border rounded text-uppercase text-center small fw-bold" 
                 style="color: var(--win-accent); letter-spacing: 0.5px; border-color: var(--win-border-color) !important;">
                IMPORTE EN LETRAS: <?php 
                    // Inicializar con PHP para que se vea al cargar antes de cualquier cambio JS
                    // Nota: Asegúrate de tener la función PHP disponible o usa un valor por defecto
                    echo 'CERO PESOS CON 00/100'; 
                ?>
            </div>
        </div>
    </div>
</div>
				
				<!-- 4. Fila inferior: Info Adicional y Botones -->
                <div class="row">
                    <!-- Información Adicional -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                                    <i class="fas fa-receipt me-2"></i>Información Adicional
                                </h6>
                            </div>
                            <div class="card-body">
                                <!-- Facturado por -->
                                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                                    <div>
                                        <label class="form-label text-muted d-block mb-0" style="font-size: 0.85rem;">Facturado por:</label>
                                        <span class="fw-bold">
                                            <?php 
                                            if (!empty($factura['usuario_nombre_completo'])) {
                                                echo htmlspecialchars($factura['usuario_nombre_completo']);
                                            } elseif (!empty($factura['usuario_nombre'])) {
                                                echo htmlspecialchars($factura['usuario_nombre']);
                                            } else {
                                                echo 'Usuario no encontrado';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($factura['fecha_creacion'])): ?>
                                        <div class="text-end">
                                            <label class="form-label text-muted d-block mb-0" style="font-size: 0.85rem;">Fecha:</label>
                                            <small class="fw-medium">
                                                <?php echo date('d/m/Y h:i A', strtotime($factura['fecha_creacion'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Contabilizado por -->
                                <?php if (!empty($factura['usuario_contabilizacion_nombre_completo']) || !empty($factura['fecha_contabilizacion'])): ?>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <label class="form-label text-muted d-block mb-0" style="font-size: 0.85rem;">Contabilizado por:</label>
                                            <span class="fw-bold text-success">
                                                <?php 
                                                if (!empty($factura['usuario_contabilizacion_nombre_completo'])) {
                                                    echo htmlspecialchars($factura['usuario_contabilizacion_nombre_completo']);
                                                } elseif (!empty($factura['usuario_contabilizacion_nombre'])) {
                                                    echo htmlspecialchars($factura['usuario_contabilizacion_nombre']);
                                                } else {
                                                    echo 'Usuario no registrado';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($factura['fecha_contabilizacion'])): ?>
                                            <div class="text-end">
                                                <label class="form-label text-muted d-block mb-0" style="font-size: 0.85rem;">Fecha:</label>
                                                <small class="fw-medium text-success">
                                                    <?php echo date('d/m/Y h:i A', strtotime($factura['fecha_contabilizacion'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-2">
                                        <small><i class="fas fa-clock me-1"></i> Pendiente de contabilización</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

<!-- Botones de Acción (PARA FORMULARIO EDITABLE) -->
<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header">
            <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                <i class="fas fa-save me-2"></i>Acciones
            </h6>
        </div>
        <div class="card-body d-flex flex-column justify-content-center">
            <div class="d-grid gap-2">
                <div class="row g-2">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-warning btn-lg w-100" 
                                data-bs-toggle="tooltip" 
                                title="Recarga Valores Salvados Anteriormente" 
                                onclick="window.location.reload();" 
                                <?php echo $formulario_deshabilitado ? 'disabled' : ''; ?>>
                            <i class="fas fa-sync-alt me-2"></i>Recargar Factura
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary btn-lg w-100" <?php echo $formulario_deshabilitado ? 'disabled' : ''; ?>>
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </div>
                
                <!-- BOTÓN REGISTRAR PAGO (NUEVO) - Solo para facturas CONTABILIZADAS -->
                <?php if ($estado_actual == 'CONTABILIZADA' && !$formulario_deshabilitado): ?>
                    <button type="button" class="btn btn-success" id="btnRegistrarPago" data-bs-toggle="tooltip" title="Registrar cobro de esta factura">
                        <i class="fas fa-money-bill-wave me-2"></i>Registrar Pago
                    </button>
                <?php endif; ?>
                
                <?php if ($estado_actual == 'PENDIENTE' && !$formulario_deshabilitado): ?>
                    <button type="button" class="btn btn-success" id="btnContabilizar" data-bs-toggle="tooltip" title="Contabilizar Valores al Submayor">
                        <i class="fas fa-check-circle me-2"></i>Contabilizar Factura
                    </button>
                <?php endif; ?>
                
                <a href="ver_factura.php?id=<?php echo $id; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-2"></i>Ver Factura
                </a>
                
                <a href="facturas.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>
    </div>
</div>
				</div>
                
                <!-- Campos ocultos para totales -->
                <input type="hidden" id="inputSubtotal" name="subtotal" value="<?php echo $factura['subtotal'] ?? 0; ?>">
                <input type="hidden" id="inputTotalGeneral" name="total_general" value="<?php echo $factura['total_general'] ?? 0; ?>">
            </form>
        </div>

        <?php else: ?>
        <!-- =========================================== -->
        <!-- SECCIÓN PARA FACTURAS PAGADAS/ANULADAS (SOLO BOTONES) -->
        <!-- =========================================== -->

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-ban me-2 text-danger"></i>Factura <?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?> - <?php echo $estado_actual; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Información de la factura (solo lectura) -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Número</h6>
                                        <h4 class="text-primary"><?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Cliente</h6>
                                        <h5><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'N/A'); ?></h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Fecha Emisión</h6>
                                        <h5><?php echo !empty($factura['fecha_emision']) ? date('d/m/Y', strtotime($factura['fecha_emision'])) : 'N/A'; ?></h5>
                                        
                                        <?php if ($estado_actual == 'PAGADA' && !empty($factura['fecha_pago'])): ?>
                                            <small class="text-success">
                                                Pagada: <?php echo date('d/m/Y', strtotime($factura['fecha_pago'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Total</h6>
                                        <h4 class="text-success">$<?php echo number_format($factura['total_general'] ?? 0, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones para PAGADA/ANULADA -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <button type="button" class="btn btn-danger btn-lg w-100 py-3" onclick="confirmarEliminacion(<?php echo $id; ?>)">
                                    <i class="fas fa-trash-alt me-2"></i>ELIMINAR FACTURA
                                </button>
                                <small class="text-muted d-block text-center mt-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Eliminación permanente
                                </small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <a href="ver_factura.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-lg w-100 py-3">
                                    <i class="fas fa-eye me-2"></i>VER FACTURA COMPLETA
                                </a>
                                <small class="text-muted d-block text-center mt-2">
                                    <i class="fas fa-file-invoice me-1"></i> Ver todos los detalles
                                </small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <button type="button" class="btn btn-outline-warning btn-lg w-100 py-3" onclick="mostrarInformacionFactura()">
                                    <i class="fas fa-info-circle me-2"></i>INFORMACIÓN DE FACTURA
                                </button>
                                <small class="text-muted d-block text-center mt-2">
                                    <i class="fas fa-chart-pie me-1"></i> Resumen y estado
                                </small>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <a href="facturas.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-arrow-left me-2"></i>VOLVER A LA LISTA DE FACTURAS
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; // Fin de !$solo_eliminar ?>

        <?php endif; // Fin de !$mostrar_modal_no_permiso ?>
    </main>

<!-- Quick Actions - VERSIÓN MODIFICADA (solo 2 botones) -->
<?php if ($mostrarQuickActionsEdicion): ?>
<div class="win-quick-actions">
    <!-- Acciones expandidas (ocultas por defecto) -->
    <div class="win-quick-actions-expanded" id="quickActionsExpanded">
        <button class="win-quick-action action-agregar" onclick="agregarNuevaFila()" title="Agregar Nueva Fila">
            <i class="fas fa-plus"></i>
        </button>
        <button class="win-quick-action action-guardar" onclick="guardarFactura()" title="Guardar Factura">
            <i class="fas fa-save"></i>
        </button>
    </div>
    
    <!-- Botón principal -->
    <button class="win-quick-action" onclick="toggleQuickActions()" title="Acciones rápidas" id="mainQuickAction">
        <i class="fas fa-bolt"></i>
    </button>
</div>
<?php endif; ?>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    <script src="js/jquery.min.js"></script>
    <script src="js/select2.min.js"></script>
    <script>
        //Variables globales (EXACTAMENTE IGUAL A FACTURAS.PHP)
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        //Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                //Para móvil
                sidebar.classList.toggle('open');
            } else {
                //Para desktop (toggle mini)
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                
                //Guardar preferencia
                guardarPreferencia('sidebar_mini', sidebarMini);
            }
        }
        
        //Funciones del panel de temas
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
        
        //Cambiar tema
        document.querySelectorAll('.win-theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-theme-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const theme = this.dataset.theme;
                document.documentElement.setAttribute('data-theme', theme);
                
                //Actualizar CSS variables inmediatamente
                updateCssVariables(theme);
            });
        });
        
        //Cambiar color de acento
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
        
        //Toggle sidebar mini desde panel
        document.getElementById('toggleSidebarMini')?.addEventListener('change', function() {
            sidebarMini = this.checked;
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.win-main-content');
            
            if (sidebarMini) {
                sidebar.classList.add('mini');
                mainContent.classList.add('sidebar-mini');
            } else {
                sidebar.classList.remove('mini');
                mainContent.classList.remove('sidebar-mini');
            }
        });
        
        //Actualizar variables CSS cuando cambia el tema
        function updateCssVariables(theme) {
            const root = document.documentElement;
            
            if (theme === 'dark') {
                root.style.setProperty('--win-bg-primary', '#0d0d0d');
                root.style.setProperty('--win-bg-secondary', '#1f1f1f');
                root.style.setProperty('--win-bg-tertiary', '#2d2d2d');
                root.style.setProperty('--win-text-primary', '#ffffff');
                root.style.setProperty('--win-text-secondary', '#a6a6a6');
                root.style.setProperty('--win-border-color', '#3d3d3d');
                root.style.setProperty('--win-shadow', '0 4px 12px rgba(0, 0, 0, 0.15)');
            } else {
                root.style.setProperty('--win-bg-primary', '#f3f3f3');
                root.style.setProperty('--win-bg-secondary', '#ffffff');
                root.style.setProperty('--win-bg-tertiary', '#fafafa');
                root.style.setProperty('--win-text-primary', '#000000');
                root.style.setProperty('--win-text-secondary', '#666666');
                root.style.setProperty('--win-border-color', '#e5e5e5');
                root.style.setProperty('--win-shadow', '0 2px 8px rgba(0, 0, 0, 0.08)');
            }
        }
        
        //Guardar configuración (EXACTAMENTE IGUAL A FACTURAS.PHP)
        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            const animations = document.getElementById('toggleAnimations').checked;
            
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            //Enviar al servidor
            fetch('guardar_configuracion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tema_windows: tema,
                    color_accent: color,
                    sidebar_mini: sidebarMini,
                    animations: animations
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
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo guardar la configuración',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
                });
            });
            
            cerrarPanelTemas();
        }
        
        //Guardar preferencia individual
        function guardarPreferencia(clave, valor) {
            fetch('guardar_preferencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `${clave}=${valor}`
            });
        }
        
        //Cerrar Quick Actions al hacer clic fuera
        document.addEventListener('click', function(event) {
            const quickActions = document.querySelector('.win-quick-actions');
            const expanded = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (quickActions && !quickActions.contains(event.target) && expanded.classList.contains('show')) {
                expanded.classList.remove('show');
                if (mainButton) {
                    mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                    mainButton.setAttribute('title', 'Acciones rápidas');
                }
            }
        });
        
        //Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const navbarToggle = document.querySelector('.btn-outline-secondary.d-lg-none');
            
            if (sidebar && sidebar.classList.contains('open') && 
                !sidebar.contains(event.target) && 
                navbarToggle && !navbarToggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });
        
        //Configuración global para SweetAlert2 (modo oscuro)
        const swalWithDarkMode = Swal.mixin({
            theme: {
                modal: 'dark',
                background: '#1a1a1a',
                title: '#ffffff',
                text: '#e0e0e0',
                confirmButton: '#0d6efd',
                cancelButton: '#6c757d'
            }
        });

        <?php if ($mostrar_modal_no_permiso): ?>
        // ===== NUEVO: MOSTRAR MODAL AL CARGAR LA PÁGINA =====
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Acceso Denegado',
                html: 'No tiene permisos para editar facturas.<br><br><strong>Consulte al administrador del sistema</strong> si necesita acceso.',
                icon: 'error',
                confirmButtonText: '<i class="fas fa-arrow-left me-2"></i>Volver a Facturas',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'facturas.php';
                }
            });
        });
        <?php else: ?>
        // ==================== FUNCIONALIDADES ESPECÍFICAS DE EDICIÓN ====================
        $(document).ready(function() {
			
// [INICIO MODAL DARK HISTÓRICO]
            <?php if ($es_factura_historica): ?>
            Swal.fire({
                title: 'IMPOSIBLE EDITAR',
                html: `
                    <div class="text-start mt-2">
                        <div class="alert alert-dark border-secondary d-flex align-items-center" style="background: #2b2b2b;">
                            <i class="fas fa-history fa-2x me-3 text-warning"></i>
                            <div>
                                <strong>Registro Histórico Cerrado</strong><br>
                                <span class="text-muted small">Esta factura es anterior al inicio de operaciones.</span>
                            </div>
                        </div>
                        <ul class="text-muted small mb-0">
                            <li>Fecha Factura: <span class="text-white"><?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?></span></li>
                            <li>Inicio Operaciones: <span class="text-warning"><?php echo date('d/m/Y', strtotime($fecha_inicio_operaciones)); ?></span></li>
                        </ul>
                    </div>
                `,
                icon: 'error',
                background: '#151515', // Fondo negro
                color: '#e0e0e0',      // Texto claro
                confirmButtonText: '<i class="fas fa-arrow-left me-2"></i>Volver al listado',
                confirmButtonColor: '#3d3d3d',
                allowOutsideClick: false,
                allowEscapeKey: false,
                backdrop: `rgba(0,0,0,0.9)` // Pantalla casi negra total
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'facturas.php';
                }
            });
            <?php endif; ?>
            // [FIN MODAL DARK HISTÓRICO]
            let contadorDetalles = <?php echo is_array($detalles) ? count($detalles) : 0; ?>;
            
            <?php if (!$solo_eliminar): ?>
            
            //Función para verificar si un servicio ya está en el formulario
            function servicioYaExiste(servicioId, filaActual = null) {
                let existe = false;
                let nombreServicio = '';
                
                $('.servicio-select').each(function() {
                    const currentRow = $(this).closest('.detalle-row');
                    const currentServiceId = $(this).val();
                    
                    // Si estamos en modo edición, omitir la fila actual
                    if (filaActual && currentRow.is(filaActual)) {
                        return true; // Continuar con la siguiente iteración
                    }
                    
                    if (currentServiceId && currentServiceId == servicioId) {
                        existe = true;
                        nombreServicio = $(this).find('option:selected').data('servicio-nombre') || 
                                       $(this).find('option:selected').text().split(' - ')[1] || 
                                       $(this).find('option:selected').text();
                        return false; // Salir del bucle
                    }
                });
                
                return {existe, nombreServicio};
            }

// Evento para botón Registrar Pago (facturas CONTABILIZADAS)
$('#btnRegistrarPago').on('click', function() {
    const facturaId = <?php echo $id; ?>;
    const noFactura = '<?php echo addslashes($factura['no_fact'] ?? ''); ?>';
    const clienteNombre = '<?php echo addslashes($factura['cliente_nombre'] ?? ''); ?>';
    const totalGeneral = <?php echo $factura['total_general'] ?? 0; ?>;
    const fechaEmision = '<?php echo $factura['fecha_emision'] ?? ''; ?>';
    const tipoPagoDesc = '<?php echo addslashes($factura['tipo_pago_desc'] ?? 'TRANSFERENCIA BANCARIA'); ?>';
    
    marcarComoPagada(facturaId, noFactura, clienteNombre, totalGeneral, fechaEmision, tipoPagoDesc, false);
});


// Función para calcular totales y actualizar badge de unidades
function calcularTotales() {
    let subtotal = 0;
    let totalUnidades = 0; // Variable para sumar todas las cantidades
    
    $('.detalle-row').each(function() {
        const cantidad = parseFloat($(this).find('.cantidad-input').val()) || 0;
        const precio = parseFloat($(this).find('.precio-input').val()) || 0;
        const totalLinea = cantidad * precio;
        
        $(this).find('.total-linea').val('$' + totalLinea.toFixed(2));
        subtotal += totalLinea;
        totalUnidades += cantidad; // Sumar cantidad al total de unidades
    });
    
    const totalGeneral = subtotal;
    
    // Actualizar display numérico
    $('#subtotalDisplay').text('$' + subtotal.toFixed(2));
    $('#totalGeneralDisplay').text('$' + totalGeneral.toFixed(2));
    
    // Actualizar campos ocultos
    $('#inputSubtotal').val(subtotal.toFixed(2));
    $('#inputTotalGeneral').val(totalGeneral.toFixed(2));

    // --- ACTUALIZAR BADGE DE UNIDADES ---
    const badgeUnidades = $('#totalUnidadesBadge');
    badgeUnidades.text(totalUnidades + (totalUnidades === 1 ? ' unidad' : ' unidades'));
    
    // Cambiar color según cantidad
    if (totalUnidades === 0) {
        badgeUnidades.removeClass('bg-info bg-warning bg-success').addClass('bg-secondary');
    } else if (totalUnidades < 5) {
        badgeUnidades.removeClass('bg-secondary bg-warning bg-success').addClass('bg-info');
    } else if (totalUnidades < 10) {
        badgeUnidades.removeClass('bg-secondary bg-info bg-success').addClass('bg-warning');
    } else {
        badgeUnidades.removeClass('bg-secondary bg-info bg-warning').addClass('bg-success');
    }

    // --- NUEVO: Actualizar Importe en Letras ---
    if (typeof convertirNumeroALetras === 'function') {
        const textoLetras = 'IMPORTE EN LETRAS: ' + convertirNumeroALetras(totalGeneral, 'PESOS');
        $('#importeLetras').text(textoLetras);
    }
}
			
			// Validación de fecha según período de cierre
            function validarFechaCierre() {
                const fechaInput = document.getElementById('fecha_emision');
                const fechaSeleccionada = new Date(fechaInput.value);
                
                // Fechas límite del período de cierre
                const fechaMin = new Date('<?php echo $fecha_min; ?>');
                const fechaMax = new Date('<?php echo $fecha_max; ?>');
                
                // Verificar si está dentro del período
                if (fechaSeleccionada < fechaMin || fechaSeleccionada > fechaMax) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fecha fuera del período de cierre',
                        html: `
                            <div style="text-align: left;">
                                <p>La fecha seleccionada no está dentro del período de cierre configurado.</p>
                                <div style="background: #2c3034; padding: 10px; border-radius: 6px; margin-top: 10px;">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <strong>Período permitido:</strong><br>
                                    Del <strong><?php echo date('d/m/Y', strtotime($fecha_min)); ?></strong><br>
                                    Al <strong><?php echo date('d/m/Y', strtotime($fecha_max)); ?></strong>
                                </div>
                            </div>
                        `,
                        background: '#1a1a1a',
                        color: '#e9ecef',
                        confirmButtonText: '<i class="fas fa-calendar-check me-1"></i> Ajustar al período',
                        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Ajustar al día actual del período
                            setFechaCierre('fecha_emision');
                        } else {
                            // Limpiar el campo
                            fechaInput.value = '';
                        }
                    });
                    
                    return false;
                }
                
                return true;
            }
            
            // Agregar validación al formulario
            document.getElementById('formFactura')?.addEventListener('submit', function(e) {
                if (!validarFechaCierre()) {
                    e.preventDefault();
                    return false;
                }
                
                // Validación adicional para facturas de períodos anteriores/posteriores
                const fechaInput = document.getElementById('fecha_emision');
                const fechaSeleccionada = new Date(fechaInput.value);
                const fechaCierreMes = <?php echo $mes_cierre_num; ?>;
                const fechaCierreAno = <?php echo $anio_cierre_num; ?>;
                
                const mesSeleccionado = fechaSeleccionada.getMonth() + 1;
                const anoSeleccionado = fechaSeleccionada.getFullYear();
                
                // Si se intenta mover a período diferente, pedir confirmación
                if (mesSeleccionado != <?php echo $mes_factura; ?> || 
                    anoSeleccionado != <?php echo $ano_factura; ?>) {
                    
                    e.preventDefault();
                    
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cambio de período',
                        html: `
                            <div style="text-align: left;">
                                <p>Está cambiando la fecha a un período diferente al original.</p>
                                <div style="background: #2c3034; padding: 10px; border-radius: 6px; margin-top: 10px;">
                                    <strong>Original:</strong> <?php echo date('m/Y', strtotime($fecha_emision_db)); ?><br>
                                    <strong>Nuevo:</strong> ${String(mesSeleccionado).padStart(2, '0')}/${anoSeleccionado}
                                </div>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Esto afectará los reportes y contabilización del período.
                                </div>
                            </div>
                        `,
                        background: '#1a1a1a',
                        color: '#e9ecef',
                        confirmButtonText: '<i class="fas fa-check me-1"></i> Confirmar cambio',
                        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#d33',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.getElementById('formFactura').submit();
                        }
                    });
                    
                    return false;
                }
            });
            
// Función global agregarServicio (para Quick Actions)
window.agregarServicio = function() {
    const index = contadorDetalles++;
    
    // Remover mensaje de no detalles
    const noDetalles = $('#noDetalles');
    if (noDetalles.length) noDetalles.remove();
    
    const nuevaFila = `
        <tr class="detalle-row" data-index="${index}">
            <td>
                <select class="form-select servicio-select" 
                        name="detalles[${index}][servicio_id]" 
                        data-index="${index}" required>
                    <option value="">Buscar servicio...</option>
                    <?php foreach ($servicios as $servicio): ?>
                        <option value="<?php echo $servicio['id']; ?>" 
                                data-precio="<?php echo $servicio['precio'] ?? $servicio['costo']; ?>"
                                data-servicio-nombre="<?php echo htmlspecialchars($servicio['descripcion']); ?>">
                            <?php echo htmlspecialchars($servicio['codigo'] . ' - ' . $servicio['descripcion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="number" class="form-control cantidad-input" 
                       name="detalles[${index}][cantidad]" value="1" min="1" step="1" required>
            </td>
			<td>
				<input type="text" class="form-control precio-input text-end" 
					   name="detalles[${index}][precio_unitario]" value="0.00" readonly 
					   style="background-color: var(--win-bg-tertiary);">
			</td>
            <td>
                <input type="text" class="form-control total-linea" value="$0.00" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-link eliminar-detalle">
                    <i class="fas fa-trash text-danger"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#detallesBody').append(nuevaFila);
    
    // IMPORTANTE: Inicializar Select2 después de agregar al DOM
    setTimeout(() => {
        const nuevoSelect = $(`#detallesBody tr[data-index="${index}"] .servicio-select`);
        
        // Inicializar Select2 con configuración CORREGIDA
        nuevoSelect.select2({
            placeholder: "Buscar servicio...",
            allowClear: false,
            width: '100%',
            theme: 'bootstrap-5',
            dropdownParent: document.body,
            language: {
                noResults: function() {
                    return "No se encontraron servicios";
                }
            }
        });
        
        // Agregar evento change para actualizar precio
        nuevoSelect.on('change.select2', function() {
            const row = $(this).closest('.detalle-row');
            const selectedOption = $(this).find('option:selected');
            
            if (selectedOption.val()) {
                const precio = selectedOption.data('precio');
                const servicioId = selectedOption.val();
                const servicioNombre = selectedOption.data('servicio-nombre') || 
                                      selectedOption.text().split(' - ')[1] || 
                                      selectedOption.text();
                
                // Verificar si el servicio ya existe
                const {existe, nombreServicio} = servicioYaExiste(servicioId, row);
                
                if (existe) {
                    // Mostrar SweetAlert dark
                    Swal.fire({
                        icon: 'warning',
                        title: '¡Servicio duplicado!',
                        html: `
                            <div style="text-align: left; color: #e9ecef;">
                                El servicio <strong>"${servicioNombre}"</strong> ya está en la lista.<br><br>
                                <div style="background: #2c3034; padding: 10px; border-radius: 6px; border-left: 4px solid #ffc107;">
                                    <i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>
                                    Por favor, seleccione otro servicio diferente o busque este y aumente las cantidades.
                                </div>
                            </div>
                        `,
                        background: '#1a1a1a',
                        color: '#e9ecef',
                        confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                        confirmButtonColor: '#0d6efd',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then((result) => {
                        // Limpiar el select
                        $(this).val('').trigger('change');
                        row.find('.precio-input').val(0);
                        row.find('.cantidad-input').val(1);
                        calcularTotales();
                    });
                    
                    return false;
                } else {
                    // Servicio válido, actualizar precio
                    row.find('.precio-input').val(precio);
                    calcularTotales(); // Esto actualizará el badge también
                }
            } else {
                row.find('.precio-input').val(0);
                calcularTotales();
            }
        });
        
        // Enfocar automáticamente el nuevo select
        setTimeout(() => {
            nuevoSelect.select2('open');
        }, 200);
        
    }, 100);
    
    calcularTotales(); // Esto actualizará el badge
};
// Función para verificar si un servicio ya está en el formulario (debe ser global)
window.servicioYaExiste = function(servicioId, filaActual = null) {
    let existe = false;
    let nombreServicio = '';
    
    $('.servicio-select').each(function() {
        const currentRow = $(this).closest('.detalle-row');
        const currentServiceId = $(this).val();
        
        // Si estamos en modo edición, omitir la fila actual
        if (filaActual && currentRow.is(filaActual)) {
            return true; // Continuar con la siguiente iteración
        }
        
        if (currentServiceId && currentServiceId == servicioId) {
            existe = true;
            nombreServicio = $(this).find('option:selected').data('servicio-nombre') || 
                           $(this).find('option:selected').text().split(' - ')[1] || 
                           $(this).find('option:selected').text();
            return false; // Salir del bucle
        }
    });
    
    return {existe, nombreServicio};
};

// Función para calcular totales (debe ser global)
window.calcularTotales = function() {
    let subtotal = 0;
    
    $('.detalle-row').each(function() {
        const cantidad = parseFloat($(this).find('.cantidad-input').val()) || 0;
        const precio = parseFloat($(this).find('.precio-input').val()) || 0;
        const totalLinea = cantidad * precio;
        
        $(this).find('.total-linea').val('$' + totalLinea.toFixed(2));
        subtotal += totalLinea;
    });
    
    const totalGeneral = subtotal;
    
    // Actualizar display
    $('#subtotalDisplay').text('$' + subtotal.toFixed(2));
    $('#totalGeneralDisplay').text('$' + totalGeneral.toFixed(2));
    
    // Actualizar campos ocultos
    $('#inputSubtotal').val(subtotal.toFixed(2));
    $('#inputTotalGeneral').val(totalGeneral.toFixed(2));

    // --- NUEVO: Actualizar Importe en Letras ---
    if (typeof convertirNumeroALetras === 'function') {
        const textoLetras = 'IMPORTE EN LETRAS: ' + convertirNumeroALetras(totalGeneral, 'PESOS');
        $('#importeLetras').text(textoLetras);
    }
};
			
			//Evento para agregar servicio
            $('#btnAgregarServicio').on('click', agregarServicio);
            
            // Inicializar Select2 en los selects existentes (CORREGIDO)
            function inicializarSelectsExistentes() {
                $('.servicio-select').each(function() {
                    // Si ya tiene Select2 inicializado, mantenerlo
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        return;
                    }
                    
                    // Inicializar Select2
                    $(this).select2({
                        placeholder: "Buscar servicio...",
                        allowClear: false,
                        width: '100%',
                        theme: 'bootstrap-5',
                        dropdownParent: document.body, // ESTO ES CLAVE
                        language: {
                            noResults: function() {
                                return "No se encontraron servicios";
                            }
                        }
                    });
                    
                    // Configurar precio inicial si ya tiene un valor seleccionado
                    if ($(this).val()) {
                        const selectedOption = $(this).find('option:selected');
                        const precio = selectedOption.data('precio');
                        const row = $(this).closest('.detalle-row');
                        if (row.find('.precio-input').length) {
                            row.find('.precio-input').val(precio);
                        }
                    }
                    
                    // Agregar evento change para servicios existentes (CORREGIDO)
                    if (!<?php echo $formulario_deshabilitado ? 'true' : 'false'; ?>) {
                        $(this).off('change.select2').on('change.select2', function() {
                            const row = $(this).closest('.detalle-row');
                            const selectedOption = $(this).find('option:selected');
                            
                            if (selectedOption.val()) {
                                const precio = selectedOption.data('precio');
                                const servicioId = selectedOption.val();
                                const servicioNombre = selectedOption.data('servicio-nombre') || 
                                                      selectedOption.text().split(' - ')[1] || 
                                                      selectedOption.text();
                                
                                // Verificar si el servicio ya existe
                                const {existe, nombreServicio} = servicioYaExiste(servicioId, row);
                                
                                if (existe) {
                                    // Mostrar SweetAlert dark
                                    Swal.fire({
                                        icon: 'warning',
                                        title: '¡Servicio duplicado!',
                                        html: `
                                            <div style="text-align: left; color: #e9ecef;">
                                                El servicio <strong>"${servicioNombre}"</strong> ya está en la lista.<br><br>
                                                <div style="background: #2c3034; padding: 10px; border-radius: 6px; border-left: 4px solid #ffc107;">
                                                    <i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>
                                                    Por favor, seleccione otro servicio diferente o busque este y aumente las cantidades.
                                                </div>
                                            </div>
                                        `,
                                        background: '#1a1a1a',
                                        color: '#e9ecef',
                                        confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                                        confirmButtonColor: '#0d6efd',
                                        allowOutsideClick: false,
                                        allowEscapeKey: false
                                    }).then((result) => {
                                        // Limpiar el select
                                        $(this).val('').trigger('change');
                                        row.find('.precio-input').val(0);
                                        calcularTotales();
                                    });
                                    
                                    return false;
                                } else {
                                    // Servicio válido, actualizar precio
                                    row.find('.precio-input').val(precio);
                                    calcularTotales();
                                }
                            } else {
                                row.find('.precio-input').val(0);
                                calcularTotales();
                            }
                        });
                    }
                });
            }
            
            // Inicializar Select2 al cargar la página
            inicializarSelectsExistentes();
            
            //Evento delegado para inputs de cantidad y precio
            $('#detallesBody').on('input', '.cantidad-input, .precio-input', function() {
                calcularTotales();
            });
            
            //Evento delegado para eliminar detalles (CORREGIDO)
            $('#detallesBody').on('click', '.eliminar-detalle', function() {
                const row = $(this).closest('.detalle-row');
                
                // Destruir Select2 antes de eliminar
                const selectElement = row.find('.servicio-select');
                if (selectElement.hasClass('select2-hidden-accessible')) {
                    selectElement.select2('destroy');
                }
                
                // Mostrar confirmación
                Swal.fire({
                    title: '¿Eliminar servicio?',
                    text: 'Se eliminará este servicio de la factura',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    background: '#1a1a1a',
                    color: '#e9ecef'
                }).then((result) => {
                    if (result.isConfirmed) {
                        row.remove();
                        
                        // Si no quedan detalles, mostrar mensaje
                        if ($('.detalle-row').length === 0) {
                            $('#detallesBody').html(`
                                <tr id="noDetalles">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>No hay servicios agregados
                                    </td>
                                </tr>
                            `);
                        }
                        
                        calcularTotales();
                        
                        // Mostrar notificación
                        Swal.fire({
                            icon: 'success',
                            title: 'Servicio eliminado',
                            text: 'El servicio ha sido eliminado de la factura',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true,
                            background: '#1a1a1a',
                            color: '#e9ecef'
                        });
                    }
                });
            });
            
            //Validación del formulario antes de enviar (CORREGIDO)
            $('#formFactura').on('submit', function(e) {
                const detalles = $('.detalle-row');
                if (detalles.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debe agregar al menos un servicio a la Factura.',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
                    });
                    return false;
                }

                // Verificar si hay servicios duplicados
                const serviciosIds = [];
                let hayDuplicados = false;
                let servicioDuplicadoNombre = '';
                
                $('.servicio-select').each(function() {
                    const servicioId = $(this).val();
                    const servicioNombre = $(this).find('option:selected').data('servicio-nombre') || 
                                          $(this).find('option:selected').text().split(' - ')[1] || 
                                          $(this).find('option:selected').text();
                    
                    if (servicioId) {
                        if (serviciosIds.includes(servicioId)) {
                            hayDuplicados = true;
                            servicioDuplicadoNombre = servicioNombre;
                            $(this).closest('.detalle-row').css('backgroundColor', '#ffe6e6');
                            return false; // Salir del bucle
                        } else {
                            serviciosIds.push(servicioId);
                            $(this).closest('.detalle-row').css('backgroundColor', '');
                        }
                    }
                });
                
                if (hayDuplicados) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Servicio duplicado',
                        html: `El servicio <strong>"${servicioDuplicadoNombre}"</strong> está duplicado en la factura.<br><br>Por favor, elimine uno de los duplicados antes de continuar.`,
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
                    });
                    return false;
                }
                
                let hayErrores = false;
                detalles.each(function() {
                    const servicio = $(this).find('.servicio-select').val();
                    const cantidad = $(this).find('.cantidad-input').val();
                    const precio = $(this).find('.precio-input').val();
                    
                    if (!servicio || !cantidad || cantidad <= 0 || !precio || precio <= 0) {
                        hayErrores = true;
                        $(this).css('backgroundColor', '#ffe6e6');
                    } else {
                        $(this).css('backgroundColor', '');
                    }
                });
                
                if (hayErrores) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Por favor complete todos los campos de los servicios correctamente',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
                    });
                    return false;
                }
                
                //Mostrar mensaje de confirmación
                e.preventDefault();
                Swal.fire({
                    title: '¿Guardar cambios?',
                    text: 'Se actualizará la información de la factura',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-save me-1"></i> Sí, Guardar',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#formFactura')[0].submit();
                    }
                });
            });
            
// Botón Contabilizar - MODIFICADO: Primero pregunta si quiere guardar
const btnContabilizar = $('#btnContabilizar');
if (btnContabilizar.length) {
    btnContabilizar.on('click', function(e) {
        e.preventDefault();
        
        const facturaId = <?php echo $id; ?>;
        const noFactura = '<?php echo addslashes($factura['no_fact'] ?? ''); ?>';
        const clienteNombre = '<?php echo addslashes($factura['cliente_nombre'] ?? ''); ?>';
        const totalGeneral = <?php echo $factura['total_general'] ?? 0; ?>;
        
        // Primera pregunta: ¿Desea guardar los cambios antes de contabilizar?
        Swal.fire({
            title: '¿Guardar cambios antes de contabilizar?',
            html: `
                <div class="text-start">
                    <div class="mb-3 p-3 rounded" style="background: var(--win-bg-tertiary);">
                        <div class="mb-2"><strong>Factura:</strong> <span class="text-primary fw-bold">${noFactura}</span></div>
                        <div class="mb-2"><strong>Cliente:</strong> <span class="text-info">${clienteNombre}</span></div>
                        <div class="mb-2"><strong>Total:</strong> <span class="text-success fw-bold">$${parseFloat(totalGeneral).toFixed(2)}</span></div>
                    </div>
                    <div class="alert alert-info mt-2">
                        <i class="fas fa-question-circle me-2"></i>
                        Si ha realizado cambios, debe guardarlos antes de contabilizar.
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-save me-2"></i>Sí, guardar y contabilizar',
            denyButtonText: '<i class="fas fa-forward me-2"></i>No, solo contabilizar',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            confirmButtonColor: '#28a745',
            denyButtonColor: '#17a2b8',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            width: '550px',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Opción SÍ: Guardar y luego contabilizar
                // Agregar campo oculto para indicar que vamos a contabilizar después de guardar
                let inputRedirect = document.querySelector('input[name="redirect_to_contabilizar"]');
                if (!inputRedirect) {
                    inputRedirect = document.createElement('input');
                    inputRedirect.type = 'hidden';
                    inputRedirect.name = 'redirect_to_contabilizar';
                    inputRedirect.value = '1';
                    document.getElementById('formFactura').appendChild(inputRedirect);
                } else {
                    inputRedirect.value = '1';
                }
                
                // Mostrar loading y enviar formulario
                Swal.fire({
                    title: 'Guardando cambios...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); },
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                });
                
                document.getElementById('formFactura').submit();
                
            } else if (result.isDenied) {
                // Opción NO: Solo contabilizar (sin guardar)
                Swal.fire({
                    title: '<i class="fas fa-check-circle me-2"></i>¿Contabilizar Factura?',
                    html: `
                        <div class="text-start">
                            <div class="mb-3 p-3 rounded" style="background: var(--win-bg-tertiary);">
                                <div class="mb-2"><strong>Factura:</strong> <span class="text-primary fw-bold">${noFactura}</span></div>
                                <div class="mb-2"><strong>Cliente:</strong> <span class="text-info">${clienteNombre}</span></div>
                                <div class="mb-2"><strong>Total:</strong> <span class="text-success fw-bold">$${parseFloat(totalGeneral).toFixed(2)}</span></div>
                            </div>
                            <div class="alert alert-warning mt-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>¡Atención!</strong> Una vez contabilizada, la factura no podrá ser editada por usuarios sin permisos especiales.
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check-circle me-2"></i>Sí, Contabilizar',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                    width: '550px',
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)',
                    allowOutsideClick: false
                }).then((result2) => {
                    if (result2.isConfirmed) {
                        Swal.fire({
                            title: 'Contabilizando factura...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); },
                            background: 'var(--win-bg-secondary)',
                            color: 'var(--win-text-primary)'
                        });
                        window.location.href = 'contabilizar.php?id=' + facturaId;
                    }
                });
            }
            // Si cancela, no hace nada
        });
    });
}

// Agregar esta función para detectar después de guardar y redirigir a contabilizar
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const guardado = urlParams.get('guardado');
    const contabilizar = urlParams.get('contabilizar');
    
    if (guardado === '1' && contabilizar === '1') {
        // Limpiar URL
        const newUrl = window.location.pathname + '?id=' + <?php echo $id; ?>;
        window.history.replaceState({}, document.title, newUrl);
        
        const facturaId = <?php echo $id; ?>;
        const noFactura = '<?php echo addslashes($factura['no_fact'] ?? ''); ?>';
        const clienteNombre = '<?php echo addslashes($factura['cliente_nombre'] ?? ''); ?>';
        const totalGeneral = <?php echo $factura['total_general'] ?? 0; ?>;
        
        Swal.fire({
            title: '<i class="fas fa-check-circle me-2"></i>¿Contabilizar Factura?',
            html: `
                <div class="text-start">
                    <div class="mb-3 p-3 rounded" style="background: var(--win-bg-tertiary);">
                        <div class="mb-2"><strong>Factura:</strong> <span class="text-primary fw-bold">${noFactura}</span></div>
                        <div class="mb-2"><strong>Cliente:</strong> <span class="text-info">${clienteNombre}</span></div>
                        <div class="mb-2"><strong>Total:</strong> <span class="text-success fw-bold">$${parseFloat(totalGeneral).toFixed(2)}</span></div>
                    </div>
                    <div class="alert alert-warning mt-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡Atención!</strong> Una vez contabilizada, la factura no podrá ser editada por usuarios sin permisos especiales.
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle me-2"></i>Sí, Contabilizar',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Ver factura',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            width: '550px',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Contabilizando factura...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); },
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                });
                window.location.href = 'contabilizar.php?id=' + facturaId;
            } else {
                window.location.href = 'ver_factura.php?id=' + facturaId;
            }
        });
    }
});
			
			
			
            //Calcular totales iniciales
            calcularTotales();
            
            <?php endif; ?>
            
            //Mostrar SweetAlert si hay mensajes en sesión
            <?php if (isset($_SESSION['swal_success'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_success']['title']; ?>',
                    html: '<?php echo $_SESSION['swal_success']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_success']['icon']; ?>',
                    confirmButtonText: '<?php echo $_SESSION['swal_success']['confirmButtonText']; ?>'
                }).then((result) => {
                    if (result.isConfirmed) {
                        <?php if (isset($_SESSION['swal_success']['redirect'])): ?>
                            window.location.href = '<?php echo $_SESSION['swal_success']['redirect']; ?>';
                        <?php endif; ?>
                    }
                });
                <?php unset($_SESSION['swal_success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['swal_error'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_error']['title']; ?>',
                    html: '<?php echo $_SESSION['swal_error']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_error']['icon']; ?>',
                    confirmButtonText: '<?php echo $_SESSION['swal_error']['confirmButtonText']; ?>'
                }).then((result) => {
                    if (result.isConfirmed) {
                        <?php if (isset($_SESSION['swal_error']['redirect'])): ?>
                            window.location.href = '<?php echo $_SESSION['swal_error']['redirect']; ?>';
                        <?php endif; ?>
                    }
                });
                <?php unset($_SESSION['swal_error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['swal_warning'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_warning']['title']; ?>',
                    html: '<?php echo $_SESSION['swal_warning']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_warning']['icon']; ?>',
                    confirmButtonText: '<?php echo $_SESSION['swal_warning']['confirmButtonText']; ?>'
                }).then((result) => {
                    if (result.isConfirmed) {
                        <?php if (isset($_SESSION['swal_warning']['redirect'])): ?>
                            window.location.href = '<?php echo $_SESSION['swal_warning']['redirect']; ?>';
                        <?php endif; ?>
                    }
                });
                <?php unset($_SESSION['swal_warning']); ?>
            <?php endif; ?>
            
            <?php if ($solo_eliminar): ?>
            // Auto-mostrar información de factura cuando está PAGADA/ANULADA
            setTimeout(function() {
                mostrarInformacionFactura();
            }, 1000);
            <?php endif; ?>
        });
        <?php endif; ?>
        
        //Cerrar panel de temas con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themePanelOpen) {
                cerrarPanelTemas();
            }
        });
        
        //Inicializar tooltips al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips
			// Verificar si Bootstrap está cargado
    if (typeof bootstrap === 'undefined') {
        console.error("Error: Bootstrap no está cargado. Verifique la ruta del archivo js/bootstrap5.3.0/bootstrap.bundle.min.js");
    } else {
        // Inicializar tooltips con configuración robusta
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
        container: 'body',      // Mantiene el tooltip fuera de contenedores con overflow
        trigger: 'hover focus', 
        placement: 'auto',      // <--- CAMBIO CLAVE: Se ajusta solo si arriba no cabe
        boundary: 'clippingParents', // Evita que se salga de la pantalla
        html: true,               
        fallbackPlacements: ['bottom', 'right', 'left'], // Si arriba no cabe, intenta abajo
        delay: { "show": 100, "hide": 100 } 
    });
	});
    }
        });
        
        // Función para confirmar eliminación
        function confirmarEliminacion(id) {
            Swal.fire({
                title: '¿ELIMINAR FACTURA <?php echo $estado_actual; ?>?',
                html: `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <p><strong>Esta acción eliminará permanentemente:</strong></p>
                        <ul class="mb-3">
                            <li><strong>Factura No:</strong> <?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?></li>
                            <li><strong>Cliente:</strong> <?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'N/A'); ?></li>
                            <li><strong>Estado Actual:</strong> <span class="badge bg-<?php echo ($estado_actual == 'PAGADA') ? 'success' : 'danger'; ?>"><?php echo $estado_actual; ?></span></li>
                            <li><strong>Total:</strong> $<?php echo number_format($factura['total_general'] ?? 0, 2); ?></li>
                        </ul>
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡ADVERTENCIA!</strong><br>
                            Esta acción <strong>NO SE PUEDE DESHACER</strong> y eliminará todos los registros asociados a esta factura y por ende eliminará el valor de su Mayor de Contabilización.
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar permanentemente',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                width: 600
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loader mientras se elimina
                    Swal.fire({
                        title: 'Eliminando factura...',
                        text: 'Por favor espere...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Redirigir a tu script de borrado
                    window.location.href = 'eliminar_factura.php?id=' + id;
                }
            });
        }
        
        function mostrarInformacionFactura() {
            // Verificar que las variables PHP están definidas
            const facturaNo = '<?php echo isset($factura["no_fact"]) ? htmlspecialchars($factura["no_fact"]) : "N/A"; ?>';
            const estadoActual = '<?php echo isset($estado_actual) ? $estado_actual : "DESCONOCIDO"; ?>';
            const clienteNombre = '<?php echo isset($factura["cliente_nombre"]) ? htmlspecialchars($factura["cliente_nombre"]) : "N/A"; ?>';
            // Formato dd-mm-yyyy para fecha de emisión
            const fechaEmision = '<?php 
                if(isset($factura["fecha_emision"]) && !empty($factura["fecha_emision"])) {
                    echo date("d-m-Y", strtotime($factura["fecha_emision"]));
                } else {
                    echo "N/A";
                }
            ?>';
            
            // Formato dd-mm-yyyy hh:mm:ss AM/PM para fecha de pago
            const fechaPago = '<?php 
                if(isset($factura["fecha_pago"]) && !empty($factura["fecha_pago"])) {
                    // Convertir a formato 12 horas con AM/PM
                    echo date("d-m-Y h:i:s A", strtotime($factura["fecha_pago"]));
                } else {
                    echo "No pagada";
                }
            ?>';
            const tipoPagoDesc = '<?php echo isset($factura["tipo_pago_desc"]) ? htmlspecialchars($factura["tipo_pago_desc"]) : "N/A"; ?>';
            const refPago = '<?php echo isset($factura["ref_pago"]) && !empty($factura["ref_pago"]) ? htmlspecialchars($factura["ref_pago"]) : "No especificada"; ?>';
            const totalGeneral = '<?php echo isset($factura["total_general"]) ? number_format($factura["total_general"], 2) : "0.00"; ?>';
            
            // Determinar color del badge según estado
            const badgeColor = estadoActual === 'PAGADA' ? 'success' : 'danger';
            
            // Crear el badge para el estado
            const estadoBadge = `<span class="badge bg-${badgeColor} p-2 mt-2" style="font-size: 0.85rem; width: 100%; text-align: center;">
                ${estadoActual}
            </span>`;
            
            Swal.fire({
                title: `
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-invoice me-2" style="color: #6c757d;"></i>
                            <span style="color: #e9ecef; font-weight: 500; font-size: 1.1rem;">
                                FACTURA ${facturaNo}
                            </span>
                        </div>
                        <div>
                            ${estadoBadge}
                        </div>
                    </div>
                `,
                html: true,
                html: `
                    <div style="color: #adb5bd; font-size: 13.5px;">
                        <!-- Información principal -->
                        <div style="background: #1a1d20; border-radius: 6px; padding: 10px; margin-bottom: 0px; border: 1px solid #2c3034;">
                            <!-- Cliente -->
                            <div class="d-flex justify-content-between mb-2">
                                <div style="color: #adb5bd;">Cliente:</div>
                                <div style="color: #e9ecef; text-align: right;">
                                    ${clienteNombre}
                                </div>
                            </div>
                            
                            <!-- Fecha de Emisión -->
                            <div class="d-flex justify-content-between mb-2">
                                <div style="color: #adb5bd;">
                                    <i class="far fa-calendar-alt me-1"></i>Fecha Emisión:
                                </div>
                                <div style="color: #e9ecef; font-weight: 500;">
                                    ${fechaEmision}
                                </div>
                            </div>
                            
                            <!-- Fecha de Pago -->
                            <div class="d-flex justify-content-between mb-2">
                                <div style="color: #adb5bd;">
                                    <i class="far fa-calendar-check me-1"></i>Fecha Pago:
                                </div>
                                <div style="color: ${estadoActual === 'PAGADA' ? '#20c997' : '#e9ecef'}; font-weight: ${estadoActual === 'PAGADA' ? '600' : '500'};">
                                    ${fechaPago}
                                </div>
                            </div>
                            
                            <!-- Forma de Pago -->
                            <div class="d-flex justify-content-between mb-2">
                                <div style="color: #adb5bd;">Forma Pago:</div>
                                <div style="color: #e9ecef; font-weight: 500;">
                                    ${tipoPagoDesc}
                                </div>
                            </div>
                            
                            <!-- REFERENCIA DE PAGO DESTACADA -->
                            <hr style="margin: 12px 0; border-color: #2c3034;">
                            
                            <div class="mb-3">
                                <div style="color: #6c757d; font-size: 12px; margin-bottom: 0px;">
                                    <i class="fas fa-money-check-alt me-1"></i>REFERENCIA DE PAGO
                                </div>
                                <div style="background: #0d6efd15; border-left: 3px solid #0d6efd; padding: 4px 4px; border-radius: 4px;">
                                    <div style="color: #0d6efd; font-weight: 600; font-size: 14px;">
                                        ${refPago}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total -->
                            <hr style="margin: 5px 0; border-color: #2c3034;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="color: #adb5bd; font-size: 14px;">IMPORTE TOTAL:</div>
                                <div style="color: #20c997; font-size: 1.4rem; font-weight: 600;">
                                    $${totalGeneral}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información de estado con icono dinámico -->
                        <div style="background: #1c1f23; border: 1px solid #2c3034; border-radius: 6px; padding: 12px; font-size: 12.5px; margin-bottom: 5px;">
                            <div class="d-flex align-items-center">
                                <i class="fas ${estadoActual === 'PAGADA' ? 'fa-check-circle' : 'fa-lock'} me-2" 
                                   style="color: ${estadoActual === 'PAGADA' ? '#20c997' : '#ffc107'};"></i>
                                <div>
                                    <div style="color: ${estadoActual === 'PAGADA' ? '#20c997' : '#ffc107'}; font-weight: 500;">
                                        Factura ${estadoActual}
                                    </div>
                                    <div style="color: #6c757d; margin-top: 2px;">
                                        ${estadoActual === 'PAGADA' 
                                            ? 'Factura pagada y completada - Solo disponible para eliminación del sistema' 
                                            : 'Factura Editable'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                width: 450,
                padding: '0px',
                background: '#111315',
                color: '#e9ecef',
                showCloseButton: true,
                showConfirmButton: true,
                showCancelButton: estadoActual == 'PAGADA' || estadoActual == 'ANULADA',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                cancelButtonText: '<i class="fas fa-trash-alt me-1"></i>Eliminar Factura',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#dc3545',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                customClass: {
                    popup: 'border-0 pt-0',
                    title: 'mb-0 pb-0 w-100 border-bottom border-dark',
                    closeButton: 'position-absolute end-0 mt-1 me-2 opacity-75',
                    htmlContainer: 'pt-1',
                    confirmButton: 'btn-sm px-1',
                    cancelButton: 'btn-sm px-1',
                    actions: 'mt-0 pt-0', 
                    footer: 'pt-0',
                    header: 'px-4 pt-3 pb-2 w-100 d-flex align-items-center'
                },
                padding: '1rem 1.5rem 1rem',
            }).then((result) => {
                if (result.isConfirmed) {
                    // El usuario hizo clic en "Entendido"
                    console.log('Usuario entendió la información');
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // El usuario hizo clic en "Eliminar Factura"
                    confirmarEliminacion(<?php echo $id; ?>);
                }
            });
        }
// Función para guardar la factura (disco)
function guardarFactura() {
    // Cerrar Quick Actions
    const expanded = document.getElementById('quickActionsExpanded');
    const mainButton = document.getElementById('mainQuickAction');
    if (expanded.classList.contains('show')) {
        expanded.classList.remove('show');
        mainButton.innerHTML = '<i class="fas fa-bolt"></i>';
        mainButton.classList.remove('rotated');
    }
    
    // Simular clic en el botón de guardar
    $('#formFactura').submit();
}

function agregarNuevaFila() {
    // Cerrar Quick Actions
    const expanded = document.getElementById('quickActionsExpanded');
    const mainButton = document.getElementById('mainQuickAction');
    if (expanded.classList.contains('show')) {
        expanded.classList.remove('show');
        mainButton.innerHTML = '<i class="fas fa-bolt"></i>';
        mainButton.classList.remove('rotated');
    }
    
    // Verificar si ya existe una fila en blanco
    const filasExistentes = $('.detalle-row');
    let existeFilaVacia = false;
    
    filasExistentes.each(function() {
        const servicioId = $(this).find('.servicio-select').val();
        const cantidad = $(this).find('.cantidad-input').val();
        const precio = $(this).find('.precio-input').val();
        
        // Si hay una fila con servicio vacío, cantidad 1 y precio 0, es una fila en blanco
        if (!servicioId && (cantidad == '1' || cantidad == 1) && (precio == '0' || precio == 0)) {
            existeFilaVacia = true;
            
            // Resaltar la fila vacía existente
            $(this).css({
                'background-color': '#fff3cd',
                'border-left': '4px solid #ffc107',
                'animation': 'pulse 0.5s'
            });
            
            // Desplazarse a la fila
            $(this)[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Quitar el resaltado después de 3 segundos
            setTimeout(() => {
                $(this).css({
                    'background-color': '',
                    'border-left': '',
                    'animation': ''
                });
            }, 3000);
            
            return false; // Salir del bucle each
        }
    });
    
    if (existeFilaVacia) {
        // Mostrar SweetAlert informando que ya hay una fila en blanco
        Swal.fire({
            icon: 'info',
            title: '¡Ya hay una fila en blanco!',
            html: `
                <div style="text-align: left; color: #e9ecef;">
                    <p>Ya existe una fila vacía esperando selección de servicio.</p>
                    <div style="background: #2c3034; padding: 10px; border-radius: 6px; border-left: 4px solid #ffc107; margin-top: 10px;">
                        <i class="fas fa-lightbulb me-2" style="color: #ffc107;"></i>
                        <strong>Sugerencia:</strong><br>
                        Primero complete la fila existente antes de agregar una nueva.
                    </div>
                </div>
            `,
            background: '#1a1a1a',
            color: '#e9ecef',
            confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
            confirmButtonColor: '#0d6efd',
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-plus me-1"></i> Agregar de todos modos',
            cancelButtonColor: '#28a745',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
                // El usuario quiere agregar de todos modos
                window.agregarServicio();
            }
        });
    } else {
        // No hay filas vacías, proceder a agregar nueva
        window.agregarServicio();
    }
}


// Toggle Quick Actions (MODIFICADA)
function toggleQuickActions() {
    const expanded = document.getElementById('quickActionsExpanded');
    const mainButton = document.getElementById('mainQuickAction');
    
    if (expanded.classList.contains('show')) {
        expanded.classList.remove('show');
        mainButton.innerHTML = '<i class="fas fa-bolt"></i>';
        mainButton.classList.remove('rotated');
        mainButton.setAttribute('title', 'Acciones rápidas');
    } else {
        expanded.classList.add('show');
        mainButton.innerHTML = '<i class="fas fa-times"></i>';
        mainButton.classList.add('rotated');
        mainButton.setAttribute('title', 'Cerrar acciones');
    }
}

// Cerrar Quick Actions al hacer clic fuera (MODIFICADA)
document.addEventListener('click', function(event) {
    const quickActions = document.querySelector('.win-quick-actions');
    const expanded = document.getElementById('quickActionsExpanded');
    const mainButton = document.getElementById('mainQuickAction');
    
    if (quickActions && !quickActions.contains(event.target) && expanded.classList.contains('show')) {
        expanded.classList.remove('show');
        if (mainButton) {
            mainButton.innerHTML = '<i class="fas fa-bolt"></i>';
            mainButton.classList.remove('rotated');
            mainButton.setAttribute('title', 'Acciones rápidas');
        }
    }
});

            // Función para establecer fecha dentro del período de cierre
            function setFechaCierre(elementId) {
                const input = document.getElementById(elementId);
                
                // Obtener fecha de hoy dentro del período de cierre
                const hoy = new Date();
                const hoyCierre = new Date(<?php echo $anio_cierre_num; ?>, 
                                           <?php echo $mes_cierre_num - 1; ?>, 
                                           Math.min(hoy.getDate(), <?php echo $total_dias_mes; ?>));
                
                // Formatear a YYYY-MM-DD
                const yyyy = hoyCierre.getFullYear();
                const mm = String(hoyCierre.getMonth() + 1).padStart(2, '0');
                const dd = String(hoyCierre.getDate()).padStart(2, '0');
                
                input.value = `${yyyy}-${mm}-${dd}`;
                
                // Disparar el evento 'change' manualmente
                input.dispatchEvent(new Event('change'));
                input.focus();
            }
            
/**
 * Convierte un número a letras (Versión JS exacta de tu PHP)
 */
function convertirNumeroALetras(numero, moneda = 'PESOS', centimos = 'CENTAVOS') {
    // 1. Asegurar formato numérico y 2 decimales
    let num = parseFloat(numero).toFixed(2);
    
    // 2. Separar parte entera y decimal
    const partes = num.split('.');
    const entero = partes[0];
    const decimal = partes[1];

    // Caso CERO
    if (parseInt(entero) === 0) {
        return `CERO ${moneda} CON ${decimal}/100`;
    }

    // Definición de sufijos (Escala Larga)
    // 0=Unidad, 1=Mil, 2=Millón, 3=Mil(Millones), 4=Billón, etc.
    const sufijos = {
        0: '', 
        1: 'MIL', 
        2: ['MILLÓN', 'MILLONES'], 
        3: 'MIL', 
        4: ['BILLÓN', 'BILLONES'], 
        5: 'MIL', 
        6: ['TRILLÓN', 'TRILLONES'],
        7: 'MIL',
        8: ['CUATRILLÓN', 'CUATRILLONES']
    };

    // 3. Dividir en grupos de 3 (invertido)
    // En JS no hay str_split directo para esto, usamos regex o array manipulación
    const reversed = entero.split('').reverse().join('');
    const chunks = reversed.match(/.{1,3}/g) || [];
    
    let texto_array = [];

    // 4. Iterar sobre los grupos
    chunks.forEach((chunk, index) => {
        // Volvemos el chunk al orden normal
        const triada = chunk.split('').reverse().join('');
        const num_triada = parseInt(triada);

        if (num_triada === 0) return;

        // Convertir el número de 3 dígitos a letras
        let texto_triada = convertirTriada(num_triada);

        // Determinar el sufijo
        let sufijo = '';
        if (sufijos[index]) {
            const s = sufijos[index];
            if (Array.isArray(s)) {
                // Es pluralizable (Millón/Billón)
                sufijo = (num_triada === 1) ? s[0] : s[1];
            } else {
                sufijo = s;
            }
        }

        // Reglas especiales
        
        // A) Si es 1000, 1000000000 (MIL, UN MIL -> MIL)
        if (sufijo === 'MIL' && num_triada === 1) {
            texto_triada = ''; 
        }

        // Agregar al array (al inicio, unshift)
        const parte_final = (texto_triada + ' ' + sufijo).trim();
        texto_array.unshift(parte_final);
    });

    // 5. Unir y formatear
    let resultado = texto_array.join(' ');
    
    // Limpieza de espacios dobles
    resultado = resultado.replace(/\s+/g, ' ').trim();

    return `${resultado} ${moneda} CON ${decimal}/100`;
}

// Función auxiliar para triadas (0-999)
function convertirTriada(num) {
    const unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    const decenas  = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    const diez_veinte = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    const veinti    = ['VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    const centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    let texto = '';
    
    // Centenas
    const c = Math.floor(num / 100);
    const resto = num % 100;

    if (c > 0) {
        if (c === 1 && resto === 0) {
            texto += 'CIEN';
        } else {
            texto += centenas[c];
        }
        if (resto > 0) texto += ' ';
    }

    // Decenas y Unidades
    if (resto > 0) {
        if (resto < 10) {
            texto += unidades[resto];
        } else if (resto >= 10 && resto < 20) {
            // Ajuste de índice porque JS arrays son base 0
            texto += diez_veinte[resto - 10]; 
        } else if (resto >= 20 && resto < 30) {
             texto += veinti[resto - 20];
        } else {
            const d = Math.floor(resto / 10);
            const u = resto % 10;
            
            texto += decenas[d];
            if (u > 0) {
                texto += ' Y ' + unidades[u];
            }
        }
    }
    
    return texto;
}
// Variable global para guardar las opciones ORIGINALES del cliente
let opcionesClienteCompletas = [];

// Inicializar el filtro de clientes
document.addEventListener('DOMContentLoaded', function() {
    // Guardar copia COMPLETA de las opciones del cliente
    const selectCliente = document.getElementById('cliente_id');
    if (selectCliente) {
        opcionesClienteCompletas = Array.from(selectCliente.options).map(opt => opt.cloneNode(true));
        
        // Guardar la opción vacía por separado
        if (opcionesClienteCompletas.length > 0 && opcionesClienteCompletas[0].value === "") {
            window.opcionVaciaCliente = opcionesClienteCompletas[0].cloneNode(true);
        }
    }
});

// Función para filtrar el combo de clientes y seleccionar automáticamente el primero
function filtrarComboCliente(texto) {
    const select = document.getElementById('cliente_id');
    if (!select) return;
    
    const busqueda = texto.toLowerCase().trim();
    
    // LIMPIAR completamente el select
    select.innerHTML = '';
    
    // SIEMPRE agregar la opción vacía primero
    if (window.opcionVaciaCliente) {
        select.appendChild(window.opcionVaciaCliente.cloneNode(true));
    } else {
        const opcionVacia = document.createElement('option');
        opcionVacia.value = '';
        opcionVacia.textContent = 'Seleccionar cliente...';
        select.appendChild(opcionVacia);
    }
    
    // Array para guardar las opciones filtradas (para debug)
    let opcionesFiltradas = [];
    
    // Si la búsqueda está vacía, agregar TODAS las opciones
    if (busqueda === '') {
        opcionesClienteCompletas.forEach(opcion => {
            if (opcion.value !== "") {
                select.appendChild(opcion.cloneNode(true));
                opcionesFiltradas.push(opcion.value);
            }
        });
    } else {
        // Si hay búsqueda, filtrar
        opcionesClienteCompletas.forEach(opcion => {
            if (opcion.value === "") return;
            
            const textoVisible = (opcion.textContent || '').toLowerCase();
            const codigo = (opcion.getAttribute('data-codigo') || '').toLowerCase();
            const contrato = (opcion.getAttribute('data-contratono') || '').toLowerCase();
            const nit = (opcion.getAttribute('data-nit') || '').toLowerCase();
            const nombre = (opcion.getAttribute('data-nombre') || '').toLowerCase();
            
            // Buscar en TODOS los campos relevantes
            if (textoVisible.includes(busqueda) || 
                codigo.includes(busqueda) || 
                contrato.includes(busqueda) || 
                nit.includes(busqueda) ||
                nombre.includes(busqueda)) {
                select.appendChild(opcion.cloneNode(true));
                opcionesFiltradas.push(opcion.value);
            }
        });
    }
    
    // ===== SELECCIÓN AUTOMÁTICA DEL PRIMER CLIENTE =====
    // Si hay al menos un cliente en la lista (después de la opción vacía)
    if (select.options.length > 1) {
        // Seleccionar automáticamente el PRIMER CLIENTE (índice 1)
        select.selectedIndex = 1;
        
        // Mostrar un pequeño indicador visual (opcional)
        const inputFiltro = document.getElementById('inputFiltroCliente');
        if (inputFiltro && busqueda !== '') {
            // Cambiar temporalmente el estilo para feedback visual
            inputFiltro.style.borderColor = '#28a745';
            inputFiltro.style.transition = 'border-color 0.3s ease';
            
            // Restaurar color después de 1 segundo
            setTimeout(() => {
                inputFiltro.style.borderColor = '';
            }, 1000);
        }
        
        // Remover mensaje de advertencia si existe
        const mensaje = document.getElementById('clienteFiltroMensaje');
        if (mensaje) mensaje.remove();
        
    } else {
        // No hay clientes en el filtro, mostrar mensaje
        const mensajeDiv = document.createElement('small');
        mensajeDiv.className = 'text-warning d-block mt-1';
        mensajeDiv.id = 'clienteFiltroMensaje';
        mensajeDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>No se encontraron clientes con ese criterio';
        
        // Remover mensaje anterior si existe
        const oldMsg = document.getElementById('clienteFiltroMensaje');
        if (oldMsg) oldMsg.remove();
        
        select.parentNode.appendChild(mensajeDiv);
    }
}


// Función para limpiar el filtro de clientes y restaurar selección original
function limpiarFiltroCliente() {
    const input = document.getElementById('inputFiltroCliente');
    if (input) {
        input.value = '';
        filtrarComboCliente('');
        
        // Después de limpiar, NO seleccionar automáticamente el primero
        // Para mantener el comportamiento natural
        input.focus();
    }
    
    // Remover mensaje si existe
    const mensaje = document.getElementById('clienteFiltroMensaje');
    if (mensaje) mensaje.remove();
}
// Función para marcar factura como pagada (desde editar_factura.php)
function marcarComoPagada(id, noFactura, cliente, importe, fechaEmisionFactura, tipoPago, esFacturaHistorica = false) {
    // ==========================================
    // 1. CONFIGURACIÓN Y ESTILOS
    // ==========================================
    const theme = document.documentElement.getAttribute('data-theme') || 'dark';
    const isDark = theme === 'dark';
    const style = getComputedStyle(document.documentElement);
    const bgColor = style.getPropertyValue('--win-bg-secondary').trim();
    const textColor = style.getPropertyValue('--win-text-primary').trim();
    const accentColor = style.getPropertyValue('--win-accent').trim();

    // Fallback por si no se envía el tipo de pago
    const textoTipoPago = tipoPago || 'No especificado';
    
    importe = parseFloat(importe).toFixed(2);
    
    // Configuración base para todos los modals
    const swalConfig = {
        background: bgColor,
        color: textColor,
        width: '700px',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        showCloseButton: true,           // ← MUESTRA LA "X" ARRIBA
        allowOutsideClick: false,        // ← NO CIERRA AL CLICK FUERA
        allowEscapeKey: true,            // ← ESC SÍ CIERRA
        customClass: { popup: 'mica-effect border-win' }
    };

    // Helpers
    const formatToFull12h = (timeStr) => {
        if (!timeStr) return "00:00:00 AM";
        const parts = timeStr.split(' '); 
        return `${parts[0]}:00 ${parts[1]}`; 
    };

    function formatearFechaDDMMYYYY(fechaISO) {
        if (!fechaISO) return '';
        const fechaPartes = fechaISO.split(' ')[0];
        const [anio, mes, dia] = fechaPartes.split('-');
        return `${dia}/${mes}/${anio}`;
    }

    const fechaEmisionFormateada = formatearFechaDDMMYYYY(fechaEmisionFactura);
    const fechaEmisionISO = fechaEmisionFactura.split(' ')[0];
    
    // Obtener fecha operativa desde PHP (inyectada)
    const fechaOperativaHoy = "<?php echo $hoy_cierre; ?>";

    // ==========================================
    // 2. FUNCIÓN PARA CERRAR (con o sin redirección)
    // ==========================================
    const cerrarModal = (redirigir = false) => {
        if (redirigir && esFacturaHistorica) {
            // Solo redirigir si es factura histórica
            Swal.fire({
                title: 'Redirigiendo...',
                text: 'Volviendo al listado de facturas',
                icon: 'info',
                background: bgColor,
                color: textColor,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showCloseButton: false,
                timer: 1000,
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                window.location.href = 'facturas.php';
            });
        } else {
            // Para facturas normales, solo cerrar sin redirigir
            Swal.close();
        }
    };

    // ==========================================
    // 3. FUNCIÓN INTERNA: MOSTRAR FORMULARIO
    // ==========================================
    const mostrarFormularioPago = (datosPrevios = null) => {
        const ahora = new Date();
        
        let valorFecha, valorHora, valorRef;

        // Determinar valores iniciales
        if (datosPrevios) {
            valorFecha = datosPrevios.f;
            valorHora = datosPrevios.h;
            valorRef = datosPrevios.r;
        } else {
            valorFecha = (fechaEmisionISO > fechaOperativaHoy) ? fechaEmisionISO : fechaOperativaHoy;
            valorHora = ahora.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', hour12: true 
            });
            valorRef = '';
        }

        // Función global para el botón HOY
        window.setPagoHoy = function() {
            const input = document.getElementById('fechaPago');
            if(input) {
                input.value = fechaOperativaHoy;
                input.dispatchEvent(new Event('input'));
                input.dispatchEvent(new Event('change'));
                input.focus();
            }
        };

        const htmlForm = `
            <div class="text-start" style="color: ${textColor}">
                <div class="mb-3 border-bottom pb-2" style="border-color: var(--win-border-color) !important;">
                    <h6 class="mb-0">Registrar cobro para: <span class="fw-bold" style="color: ${accentColor}">${cliente}</span></h6>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="opacity-100">Factura: <span class="fw-bold" style="color: ${accentColor}">${noFactura}</span> | Importe: <span class="fw-bold" style="color: ${accentColor}">$${importe}</span></small>
                        <span class="badge bg-secondary">${textoTipoPago}</span>
                    </div>
                    <div class="mt-1">
                        <small class="text-warning">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Fecha de emisión: <strong>${fechaEmisionFormateada}</strong>
                        </small>
                    </div>
                    ${esFacturaHistorica ? `
                    <div class="mt-2">
                        <small class="text-danger">
                            <i class="fas fa-history me-1"></i>
                            <strong>Factura Histórica:</strong> El pago se registrará con fecha actual.
                        </small>
                    </div>
                    ` : ''}
                </div>
                
                <div class="row g-2">
                    <div class="col-md-6 mb-2 container-fecha">
                        <label class="form-label small fw-bold">Fecha de pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-calendar-check"></i></span>
                            <input type="date" id="fechaPago" class="form-control win-input-dynamic" 
                                   value="${valorFecha}" min="${fechaEmisionISO}"
                                   title="No puede ser anterior a la emisión">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.setPagoHoy()" 
                                    style="border-color: var(--win-border-color); color: var(--win-text-primary);"
                                    title="Establecer fecha operativa actual">
                                <i class="fas fa-calendar-day"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">Mínimo: ${fechaEmisionFormateada}</small>
                    </div>
                    
                    <div class="col-md-6 mb-2">
                        <label class="form-label small fw-bold">Hora de pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-clock"></i></span>
                            <input type="text" id="horaPago" class="form-control win-input-dynamic" 
                                   value="${valorHora}" readonly>
                        </div>
                    </div>
                    
                    <div class="col-12 mb-2">
                        <label class="form-label small fw-bold">Referencia de Pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-receipt"></i></span>
                            <input type="text" id="refPago" class="form-control win-input-dynamic" 
                                   placeholder="EJ: TRANSF-9988" style="text-transform: uppercase;" 
                                   value="${valorRef}" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>
            <style>
                .win-input-dynamic { background-color: var(--win-bg-tertiary) !important; border: 1px solid var(--win-border-color) !important; color: var(--win-text-primary) !important; }
                input[type="date"]::-webkit-calendar-picker-indicator { filter: ${isDark ? 'invert(1)' : 'invert(0)'}; cursor: pointer; }
                .border-win { border: 1px solid var(--win-border_color) !important; }
            </style>
        `;

        Swal.fire({
            ...swalConfig,
            title: datosPrevios ? 'Corregir Pago' : 'Detalles del Pago',
            html: htmlForm,
            showCancelButton: true,
            confirmButtonText: 'Siguiente <i class="fas fa-arrow-right ms-1"></i>',
            cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
            didOpen: () => {
                // Inicializar mdtimepicker
                if (typeof mdtimepicker !== 'undefined') {
                    mdtimepicker('#horaPago', { timeFormat: 'hh:mm tt', theme: isDark ? 'dark' : 'blue', hourPadding: true });
                }
                
                const inputRef = document.getElementById('refPago');
                if (inputRef) {
                    setTimeout(() => {
                        inputRef.focus();
                        inputRef.setSelectionRange(inputRef.value.length, inputRef.value.length);
                    }, 100);
                }

                // Validación Visual
                const fechaPagoInput = document.getElementById('fechaPago');
                const validarVisualmente = () => {
                    const container = fechaPagoInput.closest('.col-md-6');
                    let errorDiv = container.querySelector('.invalid-feedback-custom');

                    if (fechaPagoInput.value && fechaEmisionISO && fechaPagoInput.value < fechaEmisionISO) {
                        fechaPagoInput.classList.add('is-invalid');
                        fechaPagoInput.classList.remove('is-valid');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback-custom text-danger fw-bold mt-1 animate__animated animate__fadeIn';
                            errorDiv.style.fontSize = '0.85em';
                            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Fecha inválida<br>(Anterior a la Fecha de Emitida la Factura)`;
                            container.appendChild(errorDiv);
                        }
                    } else {
                        fechaPagoInput.classList.remove('is-invalid');
                        if (fechaPagoInput.value) fechaPagoInput.classList.add('is-valid');
                        if (errorDiv) errorDiv.remove();
                    }
                };
                fechaPagoInput.addEventListener('change', validarVisualmente);
                fechaPagoInput.addEventListener('input', validarVisualmente);
                validarVisualmente();
            },
            preConfirm: () => {
                const f = document.getElementById('fechaPago').value;
                const h = document.getElementById('horaPago').value;
                const r = document.getElementById('refPago').value.trim();
                
                if (!f || !h || !r) {
                    Swal.showValidationMessage('Todos los campos son obligatorios');
                    return false;
                }
                if (fechaEmisionISO && f < fechaEmisionISO) {
                    const [a, m, d] = f.split('-');
                    Swal.showValidationMessage(`La fecha (${d}/${m}/${a}) no puede ser anterior a la emisión`);
                    return false;
                }
                return { f, h, r: r.toUpperCase() };
            }
        }).then((result) => {
            // Si se confirma (Siguiente)
            if (result.isConfirmed) {
                mostrarConfirmacion(result.value);
            }
            // Si se cierra con X o ESC o Cancelar
            else if (result.dismiss === 'close' || result.dismiss === 'esc' || result.dismiss === Swal.DismissReason.cancel) {
                cerrarModal(true); // Solo redirige si es factura histórica
            }
        });
    };

    // ==========================================
    // 4. FUNCIÓN INTERNA: CONFIRMACIÓN
    // ==========================================
    const mostrarConfirmacion = (datosCapturados) => {
        const fechaMostrar = datosCapturados.f.split('-').reverse().join('/');
        const horaConSegundos = formatToFull12h(datosCapturados.h);

        Swal.fire({
            ...swalConfig,
            title: '¿Confirmar Datos de Pago?',
            icon: 'warning',
            html: `
                <div class="text-start p-3 rounded" style="background: rgba(128,128,128,0.1); color: ${textColor}; line-height: 1.6;">
                    <p class="mb-1"><b>Cliente:</b> <span style="color:yellow">${cliente}</span></p>
                    <p class="mb-1"><b>Factura:</b> ${noFactura}</p>
                    <p class="mb-1"><b>Método de Pago:</b> <span class="badge bg-secondary">${textoTipoPago}</span></p>
                    <p class="mb-3"><b>Total a Cobrar:</b> <span class="text-success fw-bold">$${importe}</span></p>
                    
                    <div style="border-top: 1px solid var(--win-border-color); padding-top: 10px;">
                        <p class="mb-1"><b>Fecha Pago:</b> ${fechaMostrar} a las ${horaConSegundos}</p>
                        <p class="mb-0"><b>Referencia:</b> <span style="color: ${accentColor}">${datosCapturados.r}</span></p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Sí, Marcar Pagada',
            cancelButtonText: '<i class="fas fa-edit me-1"></i> Corregir'
        }).then((result) => {
            // Si se confirma (Sí, Marcar Pagada)
            if (result.isConfirmed) {
                Swal.fire({
                    ...swalConfig,
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showCloseButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const [time, modifier] = datosCapturados.h.split(' ');
                let [hours, minutes] = time.split(':');
                if (hours === '12') hours = '00';
                if (modifier === 'PM') hours = parseInt(hours, 10) + 12;
                const hora24 = `${hours.toString().padStart(2, '0')}:${minutes}:00`;

                const formData = new FormData();
                formData.append('id', id);
                formData.append('fecha_hora', `${datosCapturados.f} ${hora24}`);
                formData.append('ref_pago', datosCapturados.r);
                formData.append('accion', 'marcar_pagada');

                fetch('marcar_factura_pagada.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ 
                            ...swalConfig, 
                            icon: 'success', 
                            title: '¡Completado!', 
                            text: data.message,
                            showCloseButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            confirmButtonText: '<i class="fas fa-check me-1"></i> Aceptar'
                        }).then(() => { 
                            window.location.reload(); 
                        });
                    } else {
                        Swal.fire({ 
                            ...swalConfig, 
                            icon: 'error', 
                            title: 'Error', 
                            text: data.message,
                            showCloseButton: true,
                            allowOutsideClick: false,
                            confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({ 
                        ...swalConfig, 
                        icon: 'error', 
                        title: 'Error de Red', 
                        text: 'No se pudo contactar con el servidor',
                        showCloseButton: true,
                        allowOutsideClick: false,
                        confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido'
                    });
                });
            }
            // Si se corrige (Corregir) - volver al formulario
            else if (result.dismiss === Swal.DismissReason.cancel) {
                mostrarFormularioPago(datosCapturados);
            }
            // Si se cierra con X o ESC en el modal de confirmación
            else if (result.dismiss === 'close' || result.dismiss === 'esc') {
                cerrarModal(true); // Solo redirige si es factura histórica
            }
        });
    };

    mostrarFormularioPago();
}
	
	</script>
    
    <!-- Estilos para SweetAlert2 en tema oscuro (EXACTAMENTE IGUAL A FACTURAS.PHP) -->
    <style>
	
        .dark-swal-popup {
            background-color: #1a1a1a !important;
            border: 1px solid #333 !important;
        }
        .dark-swal-title {
            color: #ffffff !important;
        }
        .dark-swal-text {
            color: #e0e0e0 !important;
        }
        
        /* Asegurar que los dropdowns usan los colores correctos */
        .dropdown-item.active, .dropdown-item:active {
            background-color: var(--win-accent) !important;
            color: white !important;
        }
        
        /* Ajustes para el texto en los dropdowns */
        .dropdown-menu a, .dropdown-menu span, .dropdown-menu small {
            color: var(--win-text-primary) !important;
        }
        
        .dropdown-menu .text-muted {
            color: var(--win-text-secondary) !important;
        }
        
        /* Asegurar que el dropdown de Select2 se cierra correctamente */
        .select2-container--bootstrap-5 .select2-dropdown {
            z-index: 1060 !important;
        }

        .select2-container--open .select2-dropdown {
            z-index: 1061 !important;
        }

        /* Asegurar que el dropdown cierra al hacer clic fuera */
        .select2-container--bootstrap-5.select2-container--open .select2-dropdown {
            position: absolute !important;
        }
        
        /* Corregir el z-index para que Select2 funcione correctamente */
        .select2-container {
            z-index: 1055 !important;
        }
        
        .select2-container--open {
            z-index: 9999 !important;
        }
        
        .select2-dropdown {
            z-index: 9999 !important;
        }
/* Agrega al final de tu CSS */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
    }
}

/* Estilo para fila resaltada */
.fila-resaltada {
    background-color: #fff3cd !important;
    border-left: 4px solid #ffc107 !important;
    animation: pulse 1.5s infinite;
}
    </style>
	<?php
		if (file_exists('config/footer.php')) {
			include 'config/footer.php';
		}
	?>
</body>
</html>
