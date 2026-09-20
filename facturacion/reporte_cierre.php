<?php
//reporte_cierres.php

ob_start();

require_once 'config/header.php';
require_once 'config/cierre_funciones.php';

// Limpiar buffer si hay redirección
if (isset($_SESSION['redirect_needed'])) {
    ob_end_clean(); // Limpiar buffer
    unset($_SESSION['redirect_needed']);
}

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar que se proporcione un ID de cierre
$cierre_id = $_GET['id'] ?? 0;

if (!$cierre_id) {
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SISFACT PDL VISIONES - Error</title>
        <link rel="icon" type="image/x-icon" href="assets/logov.png">
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <!-- SweetAlert2 con tema oscuro -->
		<link rel="stylesheet" href="css/sweetalert2.min.css">
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
                box-shadow: 0 4px 12px rgba(0, 120, 212, 0.4);
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
            confirmButtonColor: "#0078d4",
            confirmButtonText: "<i class='fas fa-history mr-2'></i> Volver al Historial",
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
            title: "<i class='fas fa-exclamation-circle mr-2'></i> Error en la Consulta",
            html: `
                <div style="text-align: left; padding: 10px 0;">
                    <p style="margin-bottom: 15px; color: #a8a8b3;">
                        <i class="fas fa-info-circle mr-2"></i>
                        No se proporcionó un identificador válido para consultar el cierre.
                    </p>
                    <div style="background: #2d2d44; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <p style="margin: 5px 0;">
                            <strong style="color: #a8a8b3;">Problema detectado:</strong>
                            <span style="color: #ff6b6b; font-weight: 600; margin-left: 8px;">
                                <i class="fas fa-bug me-1"></i>Parámetro ID no especificado
                            </span>
                        </p>
                        <p style="margin: 5px 0;">
                            <strong style="color: #a8a8b3;">Solución requerida:</strong>
                            <span style="color: #4cd964; font-weight: 600; margin-left: 8px;">
                                <i class="fas fa-link me-2"></i>Acceder desde el historial de cierres
                            </span>
                        </p>
                    </div>
                    <p style="margin-top: 15px; font-size: 0.95rem; color: #8a8a9e;">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Para consultar un cierre, debe hacerlo desde la sección "Historial de Cierres".
                    </p>
                </div>`,
            width: "500px",
            padding: "2rem",
        }).then((result) => {
            if (result.isConfirmed) {
                // Animación de salida antes de redirigir
                darkTheme.fire({
                    title: "Redirigiendo al Historial...",
                    icon: "info",
                    timer: 1000,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                }).then(() => {
                    window.location.href = "cierres_realizados.php";
                });
            }
        });

        // Redirección automática después de 8 segundos (como fallback)
        setTimeout(() => {
            darkTheme.fire({
                title: "Redirección automática",
                text: "Serás redirigido al historial de cierres",
                icon: "info",
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = "cierres_realizados.php";
            });
        }, 8000);
        </script>
    </body>
    </html>
    <?php
    exit();
}

try {
    $db = Database::getConnection();
    
    // ==================== OBTENER DATOS DEL CIERRE ====================
    $sql = "SELECT hc.*, CONCAT(cu.nombre, ' ', cu.apellidos) as usuario_nombre, cu.usuario as usuario_login, cu.usuario as usuario_login,
            cu.email as usuario_email
            FROM historico_cierres hc
            LEFT JOIN clasif_usuarios cu ON hc.usuario_id = cu.id
            WHERE hc.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $cierre_id]);
    $cierre = $stmt->fetch(PDO::FETCH_ASSOC);
    
if (!$cierre) {
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SISFACT PDL VISIONES - Cierre no encontrado</title>
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
                box-shadow: 0 4px 12px rgba(0, 120, 212, 0.4);
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
            confirmButtonColor: "#0078d4",
            confirmButtonText: "<i class='fas fa-history mr-2'></i> Volver al Historial",
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
            title: "<i class='fas fa-search-minus mr-2'></i> Cierre no Encontrado",
            html: `
                <div style="text-align: left; padding: 10px 0;">
                    <p style="margin-bottom: 15px; color: #a8a8b3;">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        El registro de cierre solicitado no existe en el sistema o ha sido eliminado.
                    </p>
                    <div style="background: #2d2d44; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <p style="margin: 5px 0;">
                            <strong style="color: #a8a8b3;">ID Buscado:</strong>
                            <span style="color: #ff6b6b; font-weight: 600; margin-left: 8px;">
                                <i class="fas fa-hashtag me-1"></i>#<?php echo $cierre_id; ?>
                            </span>
                        </p>
                        <p style="margin: 5px 0;">
                            <strong style="color: #a8a8b3;">Posibles causas:</strong>
                            <span style="color: #ffc107; font-weight: 600; margin-left: 8px;">
                                <i class="fas fa-list-alt me-2"></i>ID incorrecto o cierre eliminado
                            </span>
                        </p>
                    </div>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(220, 53, 69, 0.05); border-radius: 6px; border: 1px solid rgba(220, 53, 69, 0.2);">
                        <p style="margin: 5px 0; font-size: 0.9rem; color: #ff6b6b;">
                            <i class="fas fa-lightbulb mr-2"></i>
                            <strong>Recomendación:</strong> Verifique que el ID sea correcto o consulte con el administrador del sistema.
                        </p>
                    </div>
                </div>`,
            width: "500px",
            padding: "2rem",
        }).then((result) => {
            if (result.isConfirmed) {
                // Animación de salida antes de redirigir
                darkTheme.fire({
                    title: "Redirigiendo al Historial...",
                    icon: "info",
                    timer: 1000,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                }).then(() => {
                    window.location.href = "cierres_realizados.php";
                });
            }
        });

        // Redirección automática después de 8 segundos (como fallback)
        setTimeout(() => {
            darkTheme.fire({
                title: "Redirección automática",
                text: "Serás redirigido al historial de cierres",
                icon: "info",
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = "cierres_realizados.php";
            });
        }, 8000);
        </script>
    </body>
    </html>
    <?php
    exit();
}    
    // Configurar variables según el tipo
    $es_mensual = ($cierre['tipo'] == 1);
    $es_anual = ($cierre['tipo'] == 2);
    
    // Array de meses
    $meses_nombres = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    // Variables para detectar diferencias
    $hay_diferencias = false;
    $mensaje_diferencias = '';
    $detalle_diferencias = [];
    
    // ==================== PROCESAR SEGÚN TIPO DE CIERRE ====================
if ($es_mensual) {
    // CORREGIDO: Traer TODAS las facturas del período (excepto ANULADA)
    $sql_detalle = "SELECT 
            f.no_fact,
            c.nombre as cliente,
            DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as fecha_emision,
            f.total_general,
            f.estado as estado_original,
            -- Determinar estado para mostrar en el reporte
            CASE 
                WHEN f.estado = 'ANULADA' THEN 'ANULADA'
                WHEN (f.fecha_pago IS NOT NULL AND f.fecha_pago != '0000-00-00') OR (f.Ref_pago IS NOT NULL AND f.Ref_pago != '') THEN 'PAGADA'
                WHEN f.estado = 'CONTABILIZADA' THEN 'CONTABILIZADA'
                WHEN f.estado = 'CERRADA' THEN 'CERRADA'
                ELSE f.estado
            END as estado,
            f.Ref_pago,
            DATE_FORMAT(f.fecha_contabilizacion, '%d/%m/%Y %H:%i') as fecha_contabilizacion,
            DATE_FORMAT(f.fecha_pago, '%d/%m/%Y') as fecha_pago,
            GROUP_CONCAT(DISTINCT s.descripcion ORDER BY s.descripcion SEPARATOR ', ') as servicios
            FROM tbl_fact f
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            LEFT JOIN tbl_fact_detalle fd ON f.id = fd.factura_id
            LEFT JOIN clasif_serv s ON fd.servicio_id = s.id
            WHERE MONTH(f.fecha_emision) = :mes 
            AND YEAR(f.fecha_emision) = :anio
            AND f.estado != 'ANULADA'  -- Excluir solo anuladas
            GROUP BY f.id
            ORDER BY CAST(SUBSTRING_INDEX(f.no_fact, '-', -1) AS UNSIGNED) ASC";
    
    $stmt = $db->prepare($sql_detalle);
    $stmt->execute([
        'mes' => $cierre['periodo_mes'],
        'anio' => $cierre['periodo_anio']
    ]);
    $detalle_facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $periodo_texto = $meses_nombres[$cierre['periodo_mes']] . ' ' . $cierre['periodo_anio'];
    
    // ==================== CALCULAR TOTALES REALES ====================
    $total_facturas_real = count($detalle_facturas);
    $total_importe_real = 0;
    $facturas_pagadas_real = 0;
    $facturas_contabilizadas_real = 0;
    
    foreach ($detalle_facturas as $factura) {
        $total_importe_real += (float)$factura['total_general'];
        
        // Determinar si está PAGADA (tiene fecha_pago válida o Ref_pago)
        $tiene_fecha_pago = !empty($factura['fecha_pago']) && $factura['fecha_pago'] != '0000-00-00';
        $tiene_ref_pago = !empty($factura['Ref_pago']) && trim($factura['Ref_pago']) != '';
        $es_pagada = $tiene_fecha_pago || $tiene_ref_pago;
        
        if ($es_pagada) {
            $facturas_pagadas_real++;
        }
        
        // Determinar si está CONTABILIZADA
        // Una factura está contabilizada si:
        // 1. Su estado es 'CONTABILIZADA'
        // 2. O está PAGADA (porque no puede estar pagada sin estar contabilizada)
        // 3. O está CERRADA (en algunos sistemas, CERRADA = contabilizada)
        $es_contabilizada = ($factura['estado_original'] == 'CONTABILIZADA') || 
                            ($factura['estado_original'] == 'CERRADA') ||
                            $es_pagada;
        
        if ($es_contabilizada) {
            $facturas_contabilizadas_real++;
        }
    }
    
    // Verificar diferencias con el registro de cierre
    $dif_facturas = $total_facturas_real - (int)$cierre['total_facturas'];
    $dif_importe = $total_importe_real - (float)$cierre['importe_total'];
    //$dif_pagadas = $facturas_pagadas_real - (int)$cierre['cant_pagadas'];
    //$dif_contabilizadas = $facturas_contabilizadas_real - (int)$cierre['cant_contabilizadas'];
    
    //if ($dif_facturas != 0 || abs($dif_importe) > 0.01 || $dif_pagadas != 0 || $dif_contabilizadas != 0) {
		
	if ($dif_facturas != 0 || abs($dif_importe) > 0.01) {
    $hay_diferencias = true;
    $mensaje_diferencias = "Se detectaron diferencias entre el registro de cierre y las facturas actuales.";
        $hay_diferencias = true;
        $mensaje_diferencias = "Se detectaron diferencias entre el registro de cierre y las facturas actuales.";
        
        $detalle_diferencias = [
            'facturas' => [
                'registro' => (int)$cierre['total_facturas'],
                'actual' => $total_facturas_real,
                'diferencia' => $dif_facturas
            ],
            'importe' => [
                'registro' => (float)$cierre['importe_total'],
                'actual' => $total_importe_real,
                'diferencia' => $dif_importe
            ],
            /*'pagadas' => [
                'registro' => (int)$cierre['cant_pagadas'],
                'actual' => $facturas_pagadas_real,
                'diferencia' => $dif_pagadas
            ],
            'contabilizadas' => [
                'registro' => (int)$cierre['cant_contabilizadas'],
                'actual' => $facturas_contabilizadas_real,
                'diferencia' => $dif_contabilizadas
            ]*/
        ];
    }
        
    } else {
        // Para cierres anuales, obtener resumen por mes
        $sql_resumen_meses = "SELECT 
                     MONTH(f.fecha_emision) as mes,
                     COUNT(*) as cantidad_facturas,
                     COALESCE(SUM(f.total_general), 0) as importe_total,
                     SUM(CASE WHEN f.fecha_pago IS NOT NULL OR f.Ref_pago IS NOT NULL THEN 1 ELSE 0 END) as pagadas,
                     COUNT(*) as cerradas
                     FROM tbl_fact f
                     WHERE YEAR(f.fecha_emision) = :anio
                     AND f.estado = 'CERRADA'
                     GROUP BY MONTH(f.fecha_emision)
                     ORDER BY MONTH(f.fecha_emision)";
        
        $stmt = $db->prepare($sql_resumen_meses);
        $stmt->execute(['anio' => $cierre['periodo_anio']]);
        $resumen_meses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $periodo_texto = 'Año ' . $cierre['periodo_anio'];
        
        // Obtener total anual desde facturas para verificar
        $sql_total_anual = "SELECT 
                   COUNT(*) as total_facturas,
                   COALESCE(SUM(total_general), 0) as importe_total,
                   SUM(CASE WHEN fecha_pago IS NOT NULL THEN 1 ELSE 0 END) as total_pagadas
                   FROM tbl_fact 
                   WHERE YEAR(fecha_emision) = :anio
                   AND estado IN ('CERRADA', 'PAGADA')";
        
        $stmt = $db->prepare($sql_total_anual);
        $stmt->execute(['anio' => $cierre['periodo_anio']]);
        $total_verificado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular totales reales
        $total_facturas_real = (int)($total_verificado['total_facturas'] ?? 0);
        $total_importe_real = (float)($total_verificado['importe_total'] ?? 0);
        
        // Verificar diferencias
        $dif_facturas = $total_facturas_real - (int)$cierre['total_facturas'];
        $dif_importe = abs($total_importe_real - (float)$cierre['importe_total']);
        
        if ($dif_facturas != 0 || $dif_importe > 0.01) {
            $hay_diferencias = true;
            $mensaje_diferencias = "Se detectaron diferencias entre el registro de cierre y las facturas actuales.";
            
            $detalle_diferencias = [
                'facturas' => [
                    'registro' => (int)$cierre['total_facturas'],
                    'actual' => $total_facturas_real,
                    'diferencia' => $dif_facturas
                ],
                'importe' => [
                    'registro' => (float)$cierre['importe_total'],
                    'actual' => $total_importe_real,
                    'diferencia' => $total_importe_real - (float)$cierre['importe_total']
                ]
            ];
        }
    }
    
    // Obtener cierres previos/siguientes para navegación
    if ($es_mensual) {
        $sql_navegacion = "SELECT id, periodo_mes, periodo_anio 
                          FROM historico_cierres 
                          WHERE tipo = 1 
                          AND periodo_anio = :anio 
                          AND id != :id 
                          ORDER BY periodo_mes ASC";
        $stmt = $db->prepare($sql_navegacion);
        $stmt->execute(['anio' => $cierre['periodo_anio'], 'id' => $cierre_id]);
    } else {
        $sql_navegacion = "SELECT id, periodo_anio 
                          FROM historico_cierres 
                          WHERE tipo = 2 
                          AND id != :id 
                          ORDER BY periodo_anio ASC";
        $stmt = $db->prepare($sql_navegacion);
        $stmt->execute(['id' => $cierre_id]);
    }
    
    $cierres_relacionados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error en reporte_cierre: " . $e->getMessage());
    $_SESSION['sweet_alert'] = [
        'title' => 'Error del sistema',
        'text' => 'Ocurrió un error al generar el reporte: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    header('Location: cierres_realizados.php');
    exit();
}

// ==================== CONFIGURACIÓN UI ====================
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';

// Preparar datos para impresión/PDF
$fecha_generacion = date('d/m/Y h:i:s A');
$usuario_actual = $_SESSION['usuario_nombre'] ?? 'Usuario';

// Logo para exportaciones
$logo_path = 'assets/logov.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $mime_type = mime_content_type($logo_path);
    $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
} else {
    // Logo por defecto si no existe
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
            <rect width="50" height="50" fill="#0078D4" rx="5"/>
            <text x="25" y="28" font-family="Arial" font-size="16" fill="white" text-anchor="middle" font-weight="bold">PDL</text>
            <text x="25" y="40" font-family="Arial" font-size="9" fill="white" text-anchor="middle">VISIONES</text>
        </svg>
    ');
}

// Preparar datos para exportación (antes de la sección HTML)
$report_data_json = [
    // Datos básicos del cierre
    'id_cierre' => $cierre_id,
    'tipo' => $es_mensual ? 'Mensual' : 'Anual',
    'periodo' => $periodo_texto,
    'titulo' => ($es_mensual ? 'Cierre Mensual' : 'Cierre Anual') . ' - ' . $periodo_texto,
    'descripcion' => $es_mensual ? 'Reporte detallado de cierre mensual' : 'Reporte resumido de cierre anual',
    'fecha_ejecucion' => date('d/m/Y h:i:s A', strtotime($cierre['fecha_ejecucion'])),
    'ejecutado_por' => $cierre['usuario_nombre'],
    
    // Datos registrados en el cierre
    'total_facturas' => (int)$cierre['total_facturas'],
    'importe_total' => (float)$cierre['importe_total'],
    'cant_pagadas' => (int)$cierre['cant_pagadas'],
    'cant_contabilizadas' => (int)$cierre['cant_contabilizadas'],
    'observaciones' => $cierre['observaciones'] ?? '',
    
    // Metadatos de generación
    'fecha_generacion' => $fecha_generacion,
    'usuario_actual' => $usuario_actual,
    
    // Datos detallados según tipo
    'detalle_facturas' => $es_mensual ? $detalle_facturas : [],
    'resumen_meses' => $es_anual ? $resumen_meses : [],
    
    // Para verificación anual
    'total_verificado' => $es_anual ? [
        'total_facturas' => $total_verificado['total_facturas'] ?? 0,
        'importe_total' => $total_verificado['importe_total'] ?? 0
    ] : null,
    
    // Recursos
    'logo_base64' => $logo_base64,
    'meses_nombres' => $meses_nombres,
    
    // --- SECCIÓN DE DIFERENCIAS ---
    'hay_diferencias' => $hay_diferencias,
    'mensaje_diferencias' => $mensaje_diferencias,
    'detalle_diferencias' => $detalle_diferencias ?? null,
    
    // Datos reales del sistema (calculados durante verificación)
    'total_facturas_real' => $total_facturas_real ?? 0,
    'total_importe_real' => $total_importe_real ?? 0,
    'facturas_pagadas_real' => $facturas_pagadas_real ?? 0,
    'facturas_contabilizadas_real' => $facturas_contabilizadas_real ?? 0,
    
    // Información de contexto
    'es_mensual' => $es_mensual,
    'es_anual' => $es_anual,
    
    // Sistema
    'sistema_nombre' => 'PDL Visiones - SISFACT',
    'sistema_version' => '2.3.3',
    'sistema_url' => 'www.pdlvisiones.com',
    'sistema_eslogan' => 'Donde tu visión toma forma'
];

// Si hay diferencias, calcular si son graves y agregar datos adicionales
if ($hay_diferencias && isset($detalle_diferencias)) {
    // Calcular diferencias absolutas
    $dif_facturas_abs = isset($detalle_diferencias['facturas']['diferencia']) ? 
        abs($detalle_diferencias['facturas']['diferencia']) : 0;
    $dif_importe_abs = isset($detalle_diferencias['importe']['diferencia']) ? 
        abs($detalle_diferencias['importe']['diferencia']) : 0;
    
    // Determinar si son diferencias graves
    $diferencias_graves = false;
    if ($es_mensual) {
        $dif_pagadas_abs = isset($detalle_diferencias['pagadas']['diferencia']) ? 
            abs($detalle_diferencias['pagadas']['diferencia']) : 0;
        $dif_contabilizadas_abs = isset($detalle_diferencias['contabilizadas']['diferencia']) ? 
            abs($detalle_diferencias['contabilizadas']['diferencia']) : 0;
        
        $diferencias_graves = $dif_facturas_abs > 0 || $dif_importe_abs > 100 || 
                             $dif_pagadas_abs > 5 || $dif_contabilizadas_abs > 5;
    } else {
        $diferencias_graves = $dif_facturas_abs > 0 || $dif_importe_abs > 1000;
    }
    
    // Agregar datos adicionales para diferencias
    $report_data_json['diferencias_graves'] = $diferencias_graves;
    $report_data_json['dif_facturas_abs'] = $dif_facturas_abs;
    $report_data_json['dif_importe_abs'] = $dif_importe_abs;
    
    if ($es_mensual) {
        $report_data_json['dif_pagadas_abs'] = $dif_pagadas_abs ?? 0;
        $report_data_json['dif_contabilizadas_abs'] = $dif_contabilizadas_abs ?? 0;
    }
    
    // Agregar recomendaciones
    $report_data_json['recomendaciones'] = [
        'Verificar si se han realizado cambios en las facturas después del cierre',
        'Revisar el estado de las facturas (CERRADA, PAGADA, CONTABILIZADA)',
        $es_mensual ? 
            'Validar que todas las facturas del mes estén correctamente contabilizadas' :
            'Validar que todas las facturas del año estén correctamente registradas',
        'Considerar la posibilidad de ejecutar un nuevo cierre si las diferencias son significativas'
    ];
}

// Asegurarnos de que los datos numéricos sean correctos en el detalle
if ($es_mensual && !empty($report_data_json['detalle_facturas'])) {
    foreach ($report_data_json['detalle_facturas'] as &$factura) {
        $factura['total_general'] = (float)$factura['total_general'];
        // Asegurar otros campos numéricos si existen
        if (isset($factura['subtotal'])) $factura['subtotal'] = (float)$factura['subtotal'];
        if (isset($factura['impuestos'])) $factura['impuestos'] = (float)$factura['impuestos'];
    }
    unset($factura); // Romper referencia
}

if ($es_anual && !empty($report_data_json['resumen_meses'])) {
    foreach ($report_data_json['resumen_meses'] as &$mes) {
        $mes['cantidad_facturas'] = (int)$mes['cantidad_facturas'];
        $mes['importe_total'] = (float)$mes['importe_total'];
        $mes['pagadas'] = (int)($mes['pagadas'] ?? 0);
        $mes['cerradas'] = (int)($mes['cerradas'] ?? 0);
    }
    unset($mes); // Romper referencia
}

// Si no hay diferencias, asegurar que los campos existan
if (!$hay_diferencias) {
    $report_data_json['diferencias_graves'] = false;
    $report_data_json['dif_facturas_abs'] = 0;
    $report_data_json['dif_importe_abs'] = 0;
    $report_data_json['dif_pagadas_abs'] = 0;
    $report_data_json['dif_contabilizadas_abs'] = 0;
    $report_data_json['recomendaciones'] = [
        'Los datos del cierre coinciden con los datos actuales del sistema.',
        'No se requiere ninguna acción adicional.'
    ];
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Cierre #<?php echo $cierre_id; ?> - SISFACT</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- LIBRERÍAS DE EXPORTACIÓN -->  
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/html2pdf.bundle.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
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
        
        /* --- Alerta de diferencias --- */
        .alerta-diferencias {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 193, 7, 0.1));
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: var(--win-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }
        
        .alerta-diferencias.diferencias-graves {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(220, 53, 69, 0.1));
            border: 1px solid rgba(220, 53, 69, 0.3);
            animation: pulse-danger 2s infinite;
        }
        
        .alerta-diferencias .alerta-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .alerta-diferencias .alerta-icono {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .alerta-diferencias.diferencias-graves .alerta-icono {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .alerta-diferencias .alerta-titulo {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            color: #ffc107;
        }
        
        .alerta-diferencias.diferencias-graves .alerta-titulo {
            color: #dc3545;
        }
        
        .alerta-diferencias .tabla-diferencias {
            width: 100%;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .alerta-diferencias .tabla-diferencias th {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--win-text-secondary);
            font-size: 0.85rem;
        }
        
        .alerta-diferencias .tabla-diferencias td {
            padding: 0.75rem 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .alerta-diferencias .diferencia {
            font-weight: bold;
        }
        
        .alerta-diferencias .diferencia-positiva {
            color: #28a745;
        }
        
        .alerta-diferencias .diferencia-negativa {
            color: #dc3545;
        }
        
        .alerta-diferencias .diferencia-cero {
            color: #6c757d;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        
        @keyframes pulse-danger {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        /* --- Tarjeta Principal --- */
        .reporte-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .win-card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .win-card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .win-card-body { padding: 2rem; }
        
        /* --- Encabezado del Reporte --- */
        .reporte-header {
            border-bottom: 2px solid var(--win-accent);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .logo-reporte {
            width: 80px;
            height: auto;
            margin-bottom: 1rem;
        }
        
        /* --- Tablas --- */
        .table-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        
        .table-reporte th {
            background: var(--win-bg-tertiary);
            color: var(--win-text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 2px solid var(--win-border-color);
            text-align: left;
        }
        
        .table-reporte td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--win-border-color);
            vertical-align: middle;
        }
        
        .table-reporte tbody tr:hover {
            background: var(--win-bg-tertiary);
        }
        
        .table-reporte tfoot td {
            background: var(--win-bg-tertiary);
            font-weight: bold;
            border-top: 2px solid var(--win-border-color);
        }
        
        /* --- Badges --- */
        .badge-reporte {
            padding: 0.35em 0.65em;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .badge-mensual { background: rgba(13, 110, 253, 0.15); color: #3d8bfd; }
        .badge-anual { background: rgba(25, 135, 84, 0.15); color: #20c997; }
        
        /* --- Estadísticas --- */
        .estadisticas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .estadistica-item {
            background: var(--win-bg-tertiary);
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
        }
        
        .estadistica-valor {
            font-size: 2rem;
            font-weight: 700;
            color: var(--win-accent);
            margin-bottom: 0.5rem;
        }
        
        .estadistica-label {
            font-size: 0.85rem;
            color: var(--win-text-secondary);
            text-transform: uppercase;
        }
        
        /* --- Botones --- */
        .btn-reporte {
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            transition: var(--win-transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-reporte-primary {
            background: var(--win-accent);
            color: white;
            border: 1px solid var(--win-accent);
        }
        
        .btn-reporte-primary:hover {
            filter: brightness(110%);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-reporte-outline {
            background: transparent;
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
        }
        
        .btn-reporte-outline:hover {
            background: var(--win-bg-elevated);
            border-color: var(--win-text-secondary);
        }
        
        /* --- COMBO DE EXPORTACIÓN --- */
        .export-combo-container {
            position: relative;
            display: inline-block;
        }

        .export-combo-btn {
            background: linear-gradient(135deg, #0078d4, #005a9e);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .export-combo-btn:hover {
            background: linear-gradient(135deg, #005a9e, #004578);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .export-combo-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: 8px;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            margin-top: 5px;
            overflow: hidden;
        }

        .export-combo-menu.show {
            display: block;
        }

        .export-combo-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--win-text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--win-border-color);
            cursor: pointer;
        }

        .export-combo-item:last-child {
            border-bottom: none;
        }

        .export-combo-item:hover {
            background: var(--win-accent-light);
            color: var(--win-accent);
        }
        
        /* BOTONES DE ACCIÓN */
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-imprimir {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-imprimir:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* --- Sección de impresión --- */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 0;
            }
            
            .win-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .table-reporte th {
                background: #f8f9fa !important;
                color: #495057 !important;
            }
            
            .table-reporte td, .table-reporte th {
                border-color: #dee2e6 !important;
            }
            
            .estadistica-valor {
                color: #007bff !important;
            }
            
            /* Encabezado impresión */
            .print-header {
                display: block !important;
                border-bottom: 2px solid #000;
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            /* Pie impresión */
            .print-footer {
                display: block !important;
                border-top: 1px solid #ccc;
                margin-top: 20px;
                padding-top: 10px;
                font-size: 10px;
            }
            
            /* Alerta diferencias para impresión */
            .alerta-diferencias {
                border: 1px solid #ffc107 !important;
                background: #fff8e1 !important;
            }
            
            .alerta-diferencias.diferencias-graves {
                border: 1px solid #dc3545 !important;
                background: #f8d7da !important;
            }
        }
        
        /* --- Navegación entre cierres --- */
        .navegacion-cierres {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius);
            margin-bottom: 1.5rem;
        }
        
        .navegacion-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            text-decoration: none;
            transition: var(--win-transition);
        }
        
        .navegacion-btn:hover {
            background: var(--win-bg-elevated);
            border-color: var(--win-accent);
        }
        
        .navegacion-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* --- Footer del reporte --- */
        .reporte-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--win-border-color);
            font-size: 0.85rem;
            color: var(--win-text-secondary);
        }
        
        /* --- Responsive --- */
        @media (max-width: 768px) {
            .win-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .estadisticas-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-imprimir, .export-combo-btn {
                width: 100%;
                justify-content: center;
            }
            
            .export-combo-container {
                width: 100%;
            }
            
            .export-combo-menu {
                width: 100%;
                right: auto;
                left: 0;
            }
            
            .alerta-diferencias .tabla-diferencias {
                font-size: 0.8rem;
            }
        }
        
        /* --- ESTILOS DE IMPRESIÓN --- */
        .print-header, .print-footer {
            display: none;
        }
        
        @media print {
            .print-header, .print-footer {
                display: block !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
                font-size: 12px !important;
                color: #000 !important;
                background: #fff !important;
            }
            
            .reporte-container {
                max-width: 100% !important;
                margin: 0 !important;
            }
            
            .win-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                margin: 0 !important;
            }
            
            .table-reporte {
                border: 1px solid #000 !important;
                font-size: 10px !important;
            }
            
            .table-reporte th {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            
            .table-reporte td {
                border: 1px solid #000 !important;
            }
            
            .badge-reporte {
                background: transparent !important;
                color: #000 !important;
                border: none !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="reporte-container">
        <!-- Navegación entre cierres -->
        <div class="navegacion-cierres no-print">
            <a href="cierres_realizados.php" class="btn-reporte btn-reporte-outline">
                <i class="fas fa-arrow-left"></i> Volver al Historial
            </a>
            
            <div class="d-flex gap-2">
                <?php if (!empty($cierres_relacionados)): ?>
                    <?php 
                    $prev_cierre = null;
                    $next_cierre = null;
                    
                    // Encontrar cierre anterior y siguiente
                    foreach ($cierres_relacionados as $index => $rel) {
                        if ($rel['id'] < $cierre_id) {
                            $prev_cierre = $rel;
                        } elseif ($rel['id'] > $cierre_id && !$next_cierre) {
                            $next_cierre = $rel;
                        }
                    }
                    ?>
                    
                    <?php if ($prev_cierre): ?>
                        <a href="reporte_cierre.php?id=<?php echo $prev_cierre['id']; ?>" class="navegacion-btn">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="navegacion-btn disabled">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($next_cierre): ?>
                        <a href="reporte_cierre.php?id=<?php echo $next_cierre['id']; ?>" class="navegacion-btn">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="navegacion-btn disabled">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Botones de acción -->
                <div class="action-buttons no-print">
                    <button class="btn-imprimir" onclick="imprimirReporte()">
                        <i class="fas fa-print"></i>
                        <span>IMPRIMIR</span>
                    </button>
                    
                    <div class="export-combo-container">
                        <button class="export-combo-btn" onclick="toggleExportMenu()">
                            <i class="fas fa-download"></i>
                            <span>EXPORTAR</span>
                            <i class="fas fa-chevron-down ms-2"></i>
                        </button>
                        <div class="export-combo-menu" id="exportMenu">
                            <a href="javascript:void(0)" class="export-combo-item" onclick="exportarExcel()">
                                <i class="fas fa-file-excel text-success"></i>
                                <span>Excel (.xlsx)</span>
                            </a>
                            <a href="javascript:void(0)" class="export-combo-item" onclick="exportarWord()">
                                <i class="fas fa-file-word text-primary"></i>
                                <span>Word (.doc)</span>
                            </a>
                            <a href="javascript:void(0)" class="export-combo-item" onclick="exportarPDF()">
                                <i class="fas fa-file-pdf text-danger"></i>
                                <span>PDF (.pdf)</span>
                            </a>
                            <a href="javascript:void(0)" class="export-combo-item" onclick="exportarCSV()">
                                <i class="fas fa-file-csv text-info"></i>
                                <span>CSV (.csv)</span>
                            </a>
                            <a href="javascript:void(0)" class="export-combo-item" onclick="exportarTXT()">
                                <i class="fas fa-file-alt text-secondary"></i>
                                <span>Texto (.txt)</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerta de diferencias -->
        <?php if ($hay_diferencias): 
            // Determinar si son diferencias graves
            $diferencias_graves = false;
            if ($es_mensual) {
                $dif_facturas_abs = abs($detalle_diferencias['facturas']['diferencia']);
                $dif_importe_abs = abs($detalle_diferencias['importe']['diferencia']);
                //$dif_pagadas_abs = abs($detalle_diferencias['pagadas']['diferencia']);
                //$dif_contabilizadas_abs = abs($detalle_diferencias['contabilizadas']['diferencia']);
                
                if ($dif_facturas_abs > 0 || $dif_importe_abs > 100 || $dif_pagadas_abs > 0 || $dif_contabilizadas_abs > 0) {
                    $diferencias_graves = true;
                }
            } else {
                $dif_facturas_abs = abs($detalle_diferencias['facturas']['diferencia']);
                $dif_importe_abs = abs($detalle_diferencias['importe']['diferencia']);
                
                if ($dif_facturas_abs > 0 || $dif_importe_abs > 100) {
                    $diferencias_graves = true;
                }
            }
        ?>
        <div class="alerta-diferencias <?php echo $diferencias_graves ? 'diferencias-graves' : ''; ?>">
            <div class="alerta-header">
                <div class="alerta-icono">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h5 class="alerta-titulo">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        <?php echo $mensaje_diferencias; ?>
                    </h5>
                    <p class="mb-0" style="color: var(--win-text-secondary); font-size: 0.9rem;">
                        Se encontraron discrepancias entre los datos registrados en el cierre y los datos actuales del sistema.
                        <?php if ($es_mensual): ?>
                            Esto puede deberse a facturas modificadas, eliminadas o agregadas después del cierre.
                        <?php else: ?>
                            Esto puede deberse a cambios en las facturas anuales después del cierre.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="tabla-diferencias">
                <table style="width: 100%;" class="text-muted">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Registro de Cierre</th>
                            <th>Datos Actuales</th>
                            <th>Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($es_mensual): ?>
                        <tr>
                            <td>Total Facturas</td>
                            <td><?php echo number_format($detalle_diferencias['facturas']['registro']); ?></td>
                            <td><?php echo number_format($detalle_diferencias['facturas']['actual']); ?></td>
                            <td>
                                <span class="diferencia <?php echo $detalle_diferencias['facturas']['diferencia'] > 0 ? 'diferencia-positiva' : ($detalle_diferencias['facturas']['diferencia'] < 0 ? 'diferencia-negativa' : 'diferencia-cero'); ?>">
                                    <?php echo $detalle_diferencias['facturas']['diferencia'] > 0 ? '+' : ''; ?>
                                    <?php echo number_format($detalle_diferencias['facturas']['diferencia']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Importe Total</td>
                            <td>$<?php echo number_format($detalle_diferencias['importe']['registro'], 2); ?></td>
                            <td>$<?php echo number_format($detalle_diferencias['importe']['actual'], 2); ?></td>
                            <td>
                                <span class="diferencia <?php echo $detalle_diferencias['importe']['diferencia'] > 0 ? 'diferencia-positiva' : ($detalle_diferencias['importe']['diferencia'] < 0 ? 'diferencia-negativa' : 'diferencia-cero'); ?>">
                                    <?php echo $detalle_diferencias['importe']['diferencia'] > 0 ? '+' : ''; ?>
                                    $<?php echo number_format($detalle_diferencias['importe']['diferencia'], 2); ?>
                                </span>
                            </td>
                        </tr>
                        <!---<tr>
                            <td>Facturas Pagadas</td>
                            <td><?php echo number_format($detalle_diferencias['pagadas']['registro']); ?></td>
                            <td><?php echo number_format($detalle_diferencias['pagadas']['actual']); ?></td>
                            <td>
                                <span class="diferencia <?php echo $detalle_diferencias['pagadas']['diferencia'] > 0 ? 'diferencia-positiva' : ($detalle_diferencias['pagadas']['diferencia'] < 0 ? 'diferencia-negativa' : 'diferencia-cero'); ?>">
                                    <?php echo $detalle_diferencias['pagadas']['diferencia'] > 0 ? '+' : ''; ?>
                                    <?php echo number_format($detalle_diferencias['pagadas']['diferencia']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Facturas Contabilizadas Sin Pagar (Mes)</td>
                            <td><?php echo number_format($detalle_diferencias['contabilizadas']['registro']); ?></td>
                            <td><?php echo number_format($detalle_diferencias['contabilizadas']['actual']); ?></td>
                            <td>
                                <span class="diferencia <?php echo $detalle_diferencias['contabilizadas']['diferencia'] > 0 ? 'diferencia-positiva' : ($detalle_diferencias['contabilizadas']['diferencia'] < 0 ? 'diferencia-negativa' : 'diferencia-cero'); ?>">
                                    <?php echo $detalle_diferencias['contabilizadas']['diferencia'] > 0 ? '+' : ''; ?>
                                    <?php echo number_format($detalle_diferencias['contabilizadas']['diferencia']); ?>
                                </span>
                            </td>
                        </tr>--->
                        <?php else: ?>
                        <tr>
                            <td>Total Facturas</td>
                            <td><?php echo number_format($detalle_diferencias['facturas']['registro']); ?></td>
                            <td><?php echo number_format($detalle_diferencias['facturas']['actual']); ?></td>
                            <td>
                                <span class="diferencia <?php echo $detalle_diferencias['facturas']['diferencia'] > 0 ? 'diferencia-positiva' : ($detalle_diferencias['facturas']['diferencia'] < 0 ? 'diferencia-negativa' : 'diferencia-cero'); ?>">
                                    <?php echo $detalle_diferencias['facturas']['diferencia'] > 0 ? '+' : ''; ?>
                                    <?php echo number_format($detalle_diferencias['facturas']['diferencia']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Importe Total</td>
                            <td>$<?php echo number_format($detalle_diferencias['importe']['registro'], 2); ?></td>
                            <td>$<?php echo number_format($detalle_diferencias['importe']['actual'], 2); ?></td>
                            <td>
                                <span class="diferencia <?php echo $detalle_diferencias['importe']['diferencia'] > 0 ? 'diferencia-positiva' : ($detalle_diferencias['importe']['diferencia'] < 0 ? 'diferencia-negativa' : 'diferencia-cero'); ?>">
                                    <?php echo $detalle_diferencias['importe']['diferencia'] > 0 ? '+' : ''; ?>
                                    $<?php echo number_format($detalle_diferencias['importe']['diferencia'], 2); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3" style="font-size: 0.85rem; color: var(--win-text-secondary);">
                <p class="mb-1"><strong>Recomendaciones:</strong></p>
                <ul class="mb-0" style="padding-left: 1.2rem;">
                    <li>Verificar si se han realizado cambios en las facturas después del cierre</li>
                    <li>Revisar el estado de las facturas (CERRADA, PAGADA, CONTABILIZADA)</li>
                    <?php if ($es_mensual): ?>
                    <li>Validar que todas las facturas del mes estén correctamente contabilizadas</li>
                    <?php else: ?>
                    <li>Validar que todas las facturas del año estén correctamente registradas</li>
                    <?php endif; ?>
                    <li>Considerar la posibilidad de ejecutar un nuevo cierre si las diferencias son significativas</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- ÁREA PARA IMPRESIÓN/EXPORTACIÓN -->
        <div id="printArea">
            <!-- ENCABEZADO PARA IMPRESIÓN -->
            <div class="print-header">
                <table style="width: 100%; border-bottom: 2px solid #000; margin-bottom: 20px;">
                    <tr>
                        <td style="width: 100px; vertical-align: top;">
                            <img src="assets/logov.png" width="80" alt="Logo" style="display: block;">
                        </td>
                        <td style="vertical-align: top; padding-left: 15px;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: bold;">PDL VISIONES</h1>
                            <p style="margin: 3px 0 0 0; font-size: 14px; color: #666;">Sistema Integral de Facturación - SISFACT</p>
                            <h2 style="margin: 15px 0 5px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;">
                                <?php echo ($es_mensual ? 'CIERRE MENSUAL' : 'CIERRE ANUAL'); ?> - <?php echo $periodo_texto; ?>
                            </h2>
                            <p style="margin: 0; font-size: 12px; color: #666;">
                                Reporte de cierre #<?php echo $cierre_id; ?>
                                <?php if ($hay_diferencias): ?>
                                    <br><span style="color: #dc3545; font-weight: bold;">
                                        <i class="fas fa-exclamation-triangle"></i> CON DIFERENCIAS DETECTADAS
                                    </span>
                                <?php endif; ?>
                            </p>
                        </td>
                        <td style="vertical-align: top; text-align: right; font-size: 11px; color: #666;">
                            <p style="margin: 0;"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                            <p style="margin: 3px 0;"><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario_actual); ?></p>
                            <p style="margin: 3px 0;"><strong>ID Cierre:</strong> <?php echo $cierre_id; ?></p>
                            <p style="margin: 3px 0;"><strong>Ejecutado por:</strong> <?php echo htmlspecialchars($cierre['usuario_nombre']); ?></p>
                            <p style="margin: 3px 0;"><strong>Fecha ejecución:</strong> <?php echo date('d/m/Y h:i:s A', strtotime($cierre['fecha_ejecucion'])); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php if ($hay_diferencias): ?>
                <div style="background: #fff8e1; border: 1px solid #ffc107; border-radius: 4px; padding: 10px; margin-bottom: 15px; font-size: 11px;">
                    <p style="margin: 0 0 5px 0; color: #856404; font-weight: bold;">
                        <i class="fas fa-exclamation-triangle"></i> ADVERTENCIA: Se detectaron diferencias
                    </p>
                    <p style="margin: 0; color: #856404;">
                        Los datos actuales del sistema no coinciden con los registrados en este cierre. 
                        Verifique la consistencia de la información.
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tarjeta Principal del Reporte -->
            <div class="win-card">
                <div class="win-card-header">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="fas fa-file-alt me-2 text-accent"></i>
                            Reporte de Cierre
                        </h1>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge-reporte <?php echo $es_mensual ? 'badge-mensual' : 'badge-anual'; ?>">
                                <i class="fas fa-<?php echo $es_mensual ? 'calendar-alt' : 'calendar-star'; ?> me-1"></i>
                                <?php echo $es_mensual ? 'Cierre Mensual' : 'Cierre Anual'; ?>
                            </span>
                            <span class="text-muted">ID: <strong class="text-white">#<?php echo $cierre_id; ?></strong></span>
                            <?php if ($hay_diferencias): ?>
                                <span class="badge-reporte" style="background: rgba(220, 53, 69, 0.15); color: #dc3545;">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    Con Diferencias
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Generado: <?php echo $fecha_generacion; ?></div>
                        <div class="text-muted small">Por: <?php echo $usuario_actual; ?></div>
                    </div>
                </div>

                <div class="win-card-body">
                    <!-- Encabezado del Reporte -->
                    <div class="reporte-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-3">
                                    <?php echo $es_mensual ? 'Cierre Mensual' : 'Cierre Anual'; ?> - 
                                    <span class="text-accent"><?php echo $periodo_texto; ?></span>
                                </h2>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong class="text-muted">Período:</strong>
                                            <div class="text-white h5"><?php echo $periodo_texto; ?></div>
                                        </div>
                                        
                                        <?php if ($es_mensual): ?>
                                        <div class="mb-2">
                                            <strong class="text-muted">Mes:</strong>
                                            <span class="text-white"><?php echo $meses_nombres[$cierre['periodo_mes']]; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong class="text-muted">Fecha de ejecución:</strong>
                                            <div class="text-white">
                                                <?php 
                                                $fecha_ejecucion = new DateTime($cierre['fecha_ejecucion']);
                                                echo $fecha_ejecucion->format('d/m/Y h:i:s A');
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong class="text-muted">Ejecutado por:</strong>
                                            <div class="text-white"><?php echo htmlspecialchars($cierre['usuario_nombre']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 text-center">
                                <div style="font-size: 4rem; color: var(--win-accent); opacity: 0.1;">
                                    <i class="fas fa-<?php echo $es_mensual ? 'file-invoice-dollar' : 'chart-bar'; ?>"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas Principales -->
                    <div class="estadisticas-grid">
                        <div class="estadistica-item">
                            <div class="estadistica-valor"><?php echo $cierre['total_facturas']; ?></div>
                            <div class="estadistica-label">Total Facturas</div>
                        </div>
                        
                        <div class="estadistica-item">
                            <div class="estadistica-valor">$<?php echo number_format($cierre['importe_total'], 2); ?></div>
                            <div class="estadistica-label">Importe Total</div>
                        </div>
                        
                        <?php if ($es_mensual): ?>
                        <div class="estadistica-item">
                            <div class="estadistica-valor"><?php echo $cierre['cant_pagadas']; ?></div>
                            <div class="estadistica-label">Facturas Pagadas</div>
                        </div>
                        
                        <div class="estadistica-item">
                            <div class="estadistica-valor"><?php echo $cierre['cant_contabilizadas']; ?></div>
                            <div class="estadistica-label">Contabilizadas</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Detalles Específicos -->
                    <?php if ($es_mensual && !empty($detalle_facturas)): ?>
                        <!-- Reporte Mensual - Detalle de Facturas -->
                        <h4 class="mb-3"><i class="fas fa-list me-2"></i>Detalle de Facturas Cerradas</h4>
                        
                        <div class="table-responsive">
<table class="table-reporte" id="tablaDetalle">
    <thead>
        <tr>
            <th>Factura</th>
            <th>Cliente</th>
            <th>Fecha Emisión</th>
            <th>Servicios</th>
            <th>Estado</th>
            <th>Ref. Pago</th>
            <th>Fecha Pago</th>
            <th class="text-end">Monto</th>
        </tr>
    </thead>
    <tbody class="text-muted">
        <?php 
        $subtotal = 0;
        $subtotal_pagadas = 0;
        foreach ($detalle_facturas as $factura): 
            $subtotal += $factura['total_general'];
            $esta_pagada = !empty($factura['fecha_pago']) || !empty($factura['Ref_pago']);
            if ($esta_pagada) {
                $subtotal_pagadas += $factura['total_general'];
            }
        ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($factura['no_fact']); ?></strong></td>
            <td><?php echo htmlspecialchars($factura['cliente']); ?></td>
            <td><?php echo htmlspecialchars($factura['fecha_emision']); ?></td>
            <td>
                <small class="text-muted">
                    <?php echo htmlspecialchars($factura['servicios'] ?? 'No especificado'); ?>
                </small>
            </td>
            <td>
                <span class="badge-reporte <?php echo $esta_pagada ? 'badge-anual' : 'badge-mensual'; ?>">
                    <?php echo $esta_pagada ? 'PAGADA' : 'CERRADA'; ?>
                </span>
            </td>
            <td>
                <?php if (!empty($factura['Ref_pago'])): ?>
                    <span class="text-info" title="Referencia de pago">
                        <?php echo htmlspecialchars($factura['Ref_pago']); ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($factura['fecha_pago'])): ?>
                    <span class="text-success"><?php echo htmlspecialchars($factura['fecha_pago']); ?></span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td class="text-end">$<?php echo number_format($factura['total_general'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="text-end"><strong>Total del Período:</strong></td>
            <td class="text-end"><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
        </tr>
        <tr>
            <td colspan="7" class="text-end text-success"><strong>Total Pagado:</strong></td>
            <td class="text-end text-success"><strong>$<?php echo number_format($subtotal_pagadas, 2); ?></strong></td>
        </tr>
        <tr>
            <td colspan="7" class="text-end text-warning"><strong>Total Pendiente:</strong></td>
            <td class="text-end text-warning"><strong>$<?php echo number_format($subtotal - $subtotal_pagadas, 2); ?></strong></td>
        </tr>
    </tfoot>
</table>
						
						</div>
                        
                    <?php elseif ($es_anual && !empty($resumen_meses)): ?>
                        <!-- Reporte Anual - Resumen por Mes -->
                        <h4 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Resumen Anual por Mes</h4>
                        
                        <div class="table-responsive">

<table class="table-reporte" id="tablaResumen">
    <thead>
        <tr>
            <th>Mes</th>
            <th class="text-center">Facturas</th>
            <th class="text-center">Pagadas</th>
            <th class="text-center">Cerradas</th>
            <th class="text-end">Importe Total</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $total_facturas_verificadas = 0;
        $total_importe_verificado = 0;
        $total_pagadas_verificadas = 0;
        $total_cerradas_verificadas = 0;
        
        foreach ($resumen_meses as $mes): 
            $total_facturas_verificadas += $mes['cantidad_facturas'];
            $total_importe_verificado += $mes['importe_total'];
            $total_pagadas_verificadas += $mes['pagadas'];
            $total_cerradas_verificadas += $mes['cerradas'];
        ?>
        <tr>
            <td><strong><?php echo $meses_nombres[$mes['mes']]; ?></strong></td>
            <td class="text-center"><?php echo $mes['cantidad_facturas']; ?></td>
            <td class="text-center">
                <span class="text-success">
                    <?php echo $mes['pagadas']; ?>
                </span>
            </td>
            <td class="text-center"><?php echo $mes['cerradas']; ?></td>
            <td class="text-end">$<?php echo number_format($mes['importe_total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td><strong>Total Anual</strong></td>
            <td class="text-center"><strong><?php echo $total_facturas_verificadas; ?></strong></td>
            <td class="text-center"><strong class="text-success"><?php echo $total_pagadas_verificadas; ?></strong></td>
				<td class="text-center"><strong><?php echo $total_cerradas_verificadas; ?></strong></td>
            <td class="text-end"><strong>$<?php echo number_format($total_importe_verificado, 2); ?></strong></td>
        </tr>
    </tfoot>
</table>
                        </div>
                        
                        <!-- Verificación de totales -->
                        <?php if (isset($total_verificado)): ?>
                        <div class="mt-4 p-3" style="background: rgba(40, 199, 111, 0.05); border-radius: 8px; border-left: 4px solid #28c76f;">
                            <h5 class="text-success mb-2"><i class="fas fa-check-circle me-2"></i>Verificación de Totales</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <strong class="text-muted">Facturas en reporte:</strong>
                                        <span class="text-white"><?php echo $total_facturas_verificadas; ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong class="text-muted">Importe en reporte:</strong>
                                        <span class="text-white">$<?php echo number_format($total_importe_verificado, 2); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <strong class="text-muted">Registro de cierre:</strong>
                                        <span class="text-white"><?php echo $cierre['total_facturas']; ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong class="text-muted">Importe de cierre:</strong>
                                        <span class="text-white">$<?php echo number_format($cierre['importe_total'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($total_facturas_verificadas == $cierre['total_facturas'] && 
                                     abs($total_importe_verificado - $cierre['importe_total']) < 0.01): ?>
                            <div class="mt-2 text-success">
                                <i class="fas fa-check me-1"></i> Totales verificados correctamente.
                            </div>
                            <?php else: ?>
                            <div class="mt-2 text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> 
                                Se detectaron diferencias entre el reporte y el registro de cierre.
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                    <?php elseif ($es_mensual && empty($detalle_facturas)): ?>
                        <!-- Mes sin facturas -->
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-file-alt fa-4x text-muted opacity-25"></i>
                            </div>
                            <h4 class="text-muted">Sin facturas registradas</h4>
                            <p class="text-muted">No se encontraron facturas cerradas para este período.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Observaciones -->
                    <?php if (!empty($cierre['observaciones'])): ?>
                    <div class="mt-4 p-3" style="background: rgba(var(--win-accent-rgb), 0.05); border-radius: 8px; border-left: 4px solid var(--win-accent);">
                        <h5 class="mb-2"><i class="fas fa-sticky-note me-2 text-accent"></i>Observaciones</h5>
                        <p class="mb-0 text-white"><?php echo nl2br(htmlspecialchars($cierre['observaciones'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Footer del Reporte -->
                    <div class="reporte-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong class="text-muted">Sistema:</strong>
                                    <span>SISFACT PDL Visiones</span>
                                </div>
                                <div class="mb-2">
                                    <strong class="text-muted">Versión:</strong>
                                    <span>2.3.3</span>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="mb-2">
                                    <strong class="text-muted">Reporte ID:</strong>
                                    <span>RPT-<?php echo str_pad($cierre_id, 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="text-muted small">
                                    Este documento es válido solo como referencia interna.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PIE DE PÁGINA PARA IMPRESIÓN -->
            <div class="print-footer">
                <table style="width: 100%;">
                    <tr>
                        <td style="vertical-align: top;">
                            <p style="margin: 0; font-weight: bold;">PDL VISIONES - SISFACT</p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">Sistema Integral de Facturación y Control</p>
                        </td>
                        <td style="text-align: center; vertical-align: top;">
                            <p style="margin: 0; font-style: italic; font-size: 10px;"> <i class="fas fa-eye" style="transform: rotate(15deg); margin-right: 10px;"></i> 
                                            PDL Visiones 
                                            <i class="fas fa-eye" style="transform: rotate(-15deg); margin-left: 10px;"></i></p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">www.pdlvisiones.com, "Donde tu visión toma forma"</p>
                        </td>
                        <td style="text-align: right; vertical-align: top;">
                            <p style="margin: 0; font-size: 10px;">Reporte ID: RPT-<?php echo str_pad($cierre_id, 6, '0', STR_PAD_LEFT); ?></p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">Cierre <?php echo $es_mensual ? 'Mensual' : 'Anual'; ?> - <?php echo $periodo_texto; ?></p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">Total: $<?php echo number_format($cierre['importe_total'], 2); ?> | Facturas: <?php echo $cierre['total_facturas']; ?></p>
                            <?php if ($hay_diferencias): ?>
                            <p style="margin: 3px 0 0 0; font-size: 9px; color: #dc3545; font-weight: bold;">
                                <i class="fas fa-exclamation-triangle"></i> CON DIFERENCIAS DETECTADAS
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Botones de acción adicionales -->
        <div class="d-flex justify-content-center gap-3 mt-4 no-print">
            <button onclick="imprimirReporte()" class="btn-reporte btn-reporte-primary">
                <i class="fas fa-print me-2"></i> Imprimir Reporte
            </button>
            
            <button onclick="exportarPDF()" class="btn-reporte btn-reporte-outline">
                <i class="fas fa-file-pdf me-2"></i> Exportar PDF
            </button>
            
            <a href="cierres_realizados.php" class="btn-reporte btn-reporte-outline">
                <i class="fas fa-history me-2"></i> Ver Historial
            </a>
        </div>
    </div>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales para datos del reporte
        const REPORT_DATA = <?php echo json_encode($report_data_json, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
// Asegurarnos que los valores numéricos sean números en JavaScript
if (REPORT_DATA.hay_diferencias && REPORT_DATA.detalle_diferencias) {
    // Convertir datos de diferencias a números
    REPORT_DATA.detalle_diferencias.facturas.registro = parseInt(REPORT_DATA.detalle_diferencias.facturas.registro) || 0;
    REPORT_DATA.detalle_diferencias.facturas.actual = parseInt(REPORT_DATA.detalle_diferencias.facturas.actual) || 0;
    REPORT_DATA.detalle_diferencias.facturas.diferencia = parseInt(REPORT_DATA.detalle_diferencias.facturas.diferencia) || 0;
    
    REPORT_DATA.detalle_diferencias.importe.registro = parseFloat(REPORT_DATA.detalle_diferencias.importe.registro) || 0;
    REPORT_DATA.detalle_diferencias.importe.actual = parseFloat(REPORT_DATA.detalle_diferencias.importe.actual) || 0;
    REPORT_DATA.detalle_diferencias.importe.diferencia = parseFloat(REPORT_DATA.detalle_diferencias.importe.diferencia) || 0;
    
    if (REPORT_DATA.tipo === 'Mensual') {
        REPORT_DATA.detalle_diferencias.pagadas.registro = parseInt(REPORT_DATA.detalle_diferencias.pagadas.registro) || 0;
        REPORT_DATA.detalle_diferencias.pagadas.actual = parseInt(REPORT_DATA.detalle_diferencias.pagadas.actual) || 0;
        REPORT_DATA.detalle_diferencias.pagadas.diferencia = parseInt(REPORT_DATA.detalle_diferencias.pagadas.diferencia) || 0;
        
        REPORT_DATA.detalle_diferencias.contabilizadas.registro = parseInt(REPORT_DATA.detalle_diferencias.contabilizadas.registro) || 0;
        REPORT_DATA.detalle_diferencias.contabilizadas.actual = parseInt(REPORT_DATA.detalle_diferencias.contabilizadas.actual) || 0;
        REPORT_DATA.detalle_diferencias.contabilizadas.diferencia = parseInt(REPORT_DATA.detalle_diferencias.contabilizadas.diferencia) || 0;
    }
}

// Convertir otros valores numéricos
REPORT_DATA.total_facturas = parseInt(REPORT_DATA.total_facturas) || 0;
REPORT_DATA.importe_total = parseFloat(REPORT_DATA.importe_total) || 0;
REPORT_DATA.cant_pagadas = parseInt(REPORT_DATA.cant_pagadas) || 0;
REPORT_DATA.cant_contabilizadas = parseInt(REPORT_DATA.cant_contabilizadas) || 0;
REPORT_DATA.total_facturas_real = parseInt(REPORT_DATA.total_facturas_real) || 0;
REPORT_DATA.total_importe_real = parseFloat(REPORT_DATA.total_importe_real) || 0;
REPORT_DATA.facturas_pagadas_real = parseInt(REPORT_DATA.facturas_pagadas_real) || 0;
REPORT_DATA.facturas_contabilizadas_real = parseInt(REPORT_DATA.facturas_contabilizadas_real) || 0;
        
        // Función para mostrar/ocultar menú de exportación
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.classList.toggle('show');
        }
        
        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('exportMenu');
            const btn = document.querySelector('.export-combo-btn');
            if (menu && btn && !btn.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.remove('show');
            }
        });
        


// 1. FUNCIÓN DE IMPRESIÓN PROFESIONAL (SIMPLIFICADA - CON ESTADÍSTICAS ESENCIALES)
function imprimirReporte() {
    const printWindow = window.open('', '_blank');
    
    // Formateo de fechas y horas
    const fechaActual = new Date();
    const fechaStr = fechaActual.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const horaStr = fechaActual.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    
    const diferenciasHTML = REPORT_DATA.hay_diferencias 
        ? '<span style="color: #dc3545; font-weight: bold;"> CON DIFERENCIAS</span>' 
        : '';
    const tituloReporte = 'Reporte de Cierre ' + REPORT_DATA.tipo + ' ID: ' + REPORT_DATA.id_cierre;

    // ==================== CALCULAR ESTADÍSTICAS ESENCIALES ====================
    let estadisticasHTML = '';
    
    if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses && REPORT_DATA.resumen_meses.length > 0) {
        // ESTADÍSTICAS PARA REPORTE ANUAL
        const meses = REPORT_DATA.resumen_meses;
        let mejorMes = meses[0];
        let peorMes = meses[0];
        let sumaFacturas = 0;
        let sumaImporte = 0;
        let totalPagadas = 0;
        
        meses.forEach(function(mes) {
            const facturas = parseInt(mes.cantidad_facturas || 0);
            const importe = parseFloat(mes.importe_total || 0);
            const pagadas = parseInt(mes.pagadas || 0);
            sumaFacturas += facturas;
            sumaImporte += importe;
            totalPagadas += pagadas;
            
            if (importe > parseFloat(mejorMes.importe_total || 0)) mejorMes = mes;
            if (importe < parseFloat(peorMes.importe_total || 0) && importe > 0) peorMes = mes;
        });
        
        const mesesConDatos = meses.filter(function(m) { return parseInt(m.cantidad_facturas || 0) > 0; }).length;
        const promedioFacturas = mesesConDatos > 0 ? Math.round(sumaFacturas / mesesConDatos) : 0;
        const promedioImporte = mesesConDatos > 0 ? (sumaImporte / mesesConDatos) : 0;
        
        // Calcular tendencia (primer semestre vs segundo semestre)
        const primerSemestre = meses.slice(0, 6).reduce(function(sum, m) { return sum + parseFloat(m.importe_total || 0); }, 0);
        const segundoSemestre = meses.slice(6, 12).reduce(function(sum, m) { return sum + parseFloat(m.importe_total || 0); }, 0);
        let tendencia = 'Estable →';
        let variacion = 0;
        if (primerSemestre > 0) {
            variacion = Math.abs((segundoSemestre - primerSemestre) / primerSemestre * 100).toFixed(1);
            if (segundoSemestre > primerSemestre * 1.1) tendencia = 'Crecimiento ▲ (+' + variacion + '%)';
            else if (segundoSemestre < primerSemestre * 0.9) tendencia = 'Decrecimiento ▼ (-' + variacion + '%)';
            else tendencia = 'Estable → (' + variacion + '% var)';
        }
        
        const mejorNombre = REPORT_DATA.meses_nombres ? REPORT_DATA.meses_nombres[mejorMes.mes] : 'Mes ' + mejorMes.mes;
        const peorNombre = REPORT_DATA.meses_nombres ? REPORT_DATA.meses_nombres[peorMes.mes] : 'Mes ' + peorMes.mes;
        const mejorImporte = parseFloat(mejorMes.importe_total || 0);
        const peorImporte = parseFloat(peorMes.importe_total || 0);
        const porcentajeMejor = sumaImporte > 0 ? (mejorImporte / sumaImporte * 100).toFixed(1) : 0;
        const porcentajePeor = sumaImporte > 0 ? (peorImporte / sumaImporte * 100).toFixed(1) : 0;
        const tasaCobro = sumaFacturas > 0 ? (totalPagadas / sumaFacturas * 100).toFixed(1) : 0;
        
        estadisticasHTML = `
            <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #f0f8ff, #e8f4f8); border-radius: 8px; border: 1px solid #0078D4;">
                <h4 style="margin-top: 0; text-align: center; color: #0078D4; text-transform: uppercase;">📊 Análisis Estadístico Anual</h4>
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                    <div style="flex: 1; min-width: 200px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #28a745;">
                        <div style="font-size: 12px; color: #28a745; font-weight: bold;">🏆 MEJOR MES</div>
                        <div style="font-size: 16px; font-weight: bold;">${mejorNombre}</div>
                        <div style="font-size: 13px;">Facturas: ${mejorMes.cantidad_facturas}</div>
                        <div style="font-size: 13px;">Importe: $${mejorImporte.toFixed(2)}</div>
                        <div style="font-size: 11px; color: #666;">${porcentajeMejor}% del total anual</div>
                    </div>
                    
                    <div style="flex: 1; min-width: 200px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <div style="font-size: 12px; color: #dc3545; font-weight: bold;">📉 PEOR MES</div>
                        <div style="font-size: 16px; font-weight: bold;">${peorNombre}</div>
                        <div style="font-size: 13px;">Facturas: ${peorMes.cantidad_facturas || 0}</div>
                        <div style="font-size: 13px;">Importe: $${peorImporte.toFixed(2)}</div>
                        <div style="font-size: 11px; color: #666;">${porcentajePeor}% del total anual</div>
                    </div>
                    
                    <div style="flex: 1; min-width: 200px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #17a2b8;">
                        <div style="font-size: 12px; color: #17a2b8; font-weight: bold;">📈 PROMEDIO MENSUAL</div>
                        <div style="font-size: 13px;">Facturas: ${promedioFacturas}</div>
                        <div style="font-size: 13px;">Importe: $${promedioImporte.toFixed(2)}</div>
                        <div style="font-size: 11px;">Basado en ${mesesConDatos} meses activos</div>
                    </div>
                    
                    <div style="flex: 1; min-width: 200px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <div style="font-size: 12px; color: #856404; font-weight: bold;">📊 TENDENCIA</div>
                        <div style="font-size: 14px; font-weight: bold;">${tendencia}</div>
                        <div style="font-size: 13px;">Tasa de Cobro: ${tasaCobro}%</div>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; text-align: center;">
                    <div style="font-size: 12px; color: #666;">
                        <strong>📌 Resumen Anual:</strong> Total facturas: ${sumaFacturas} | Importe total: $${sumaImporte.toFixed(2)} | 
                        Facturas pagadas: ${totalPagadas} (${tasaCobro}%)
                    </div>
                </div>
            </div>
        `;
        
    } else if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas && REPORT_DATA.detalle_facturas.length > 0) {
        // ESTADÍSTICAS PARA REPORTE MENSUAL (SOLO LO ESENCIAL)
        const facturas = REPORT_DATA.detalle_facturas;
        const totalFacturas = facturas.length;
        let totalImporte = 0;
        let facturasPagadas = 0;
        
        // Calcular totales
        for (var i = 0; i < facturas.length; i++) {
            var f = facturas[i];
            var importe = parseFloat(f.total_general || 0);
            totalImporte += importe;
            var esPagada = (f.estado === 'PAGADA') || (f.fecha_pago && f.fecha_pago !== '0000-00-00');
            if (esPagada) facturasPagadas++;
        }
        
        const facturasPendientes = totalFacturas - facturasPagadas;
        const tasaCobro = totalFacturas > 0 ? (facturasPagadas / totalFacturas * 100).toFixed(1) : 0;
        const montoPromedio = totalFacturas > 0 ? totalImporte / totalFacturas : 0;
        
        // Encontrar montos min y max
        var montos = [];
        for (var i = 0; i < facturas.length; i++) {
            montos.push(parseFloat(facturas[i].total_general || 0));
        }
        var montoMin = Math.min.apply(null, montos);
        var montoMax = Math.max.apply(null, montos);
        
        // CLIENTE QUE MÁS Y QUE MENOS FACTURÓ
        var clientesMap = {};
        for (var i = 0; i < facturas.length; i++) {
            var f = facturas[i];
            var cliente = f.cliente || 'Sin cliente';
            var importe = parseFloat(f.total_general || 0);
            if (clientesMap[cliente]) {
                clientesMap[cliente] += importe;
            } else {
                clientesMap[cliente] = importe;
            }
        }
        
        var clientesArray = [];
        for (var cliente in clientesMap) {
            clientesArray.push({ nombre: cliente, importe: clientesMap[cliente] });
        }
        clientesArray.sort(function(a, b) { return b.importe - a.importe; });
        
        var mejorCliente = clientesArray.length > 0 ? clientesArray[0] : null;
        var peorCliente = clientesArray.length > 0 ? clientesArray[clientesArray.length - 1] : null;
        
        estadisticasHTML = `
            <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #f0f8ff, #e8f4f8); border-radius: 8px; border: 1px solid #0078D4;">
                <h4 style="margin-top: 0; text-align: center; color: #0078D4; text-transform: uppercase;">📊 Análisis Estadístico Mensual</h4>
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                    <div style="flex: 1; min-width: 180px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #0078D4;">
                        <div style="font-size: 12px; color: #0078D4; font-weight: bold;">📋 GENERAL</div>
                        <div style="font-size: 14px;">Facturas: ${totalFacturas}</div>
                        <div style="font-size: 14px;">Importe: $${totalImporte.toFixed(2)}</div>
                        <div style="font-size: 13px;">Promedio: $${montoPromedio.toFixed(2)}</div>
                    </div>
                    
                    <div style="flex: 1; min-width: 180px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #28a745;">
                        <div style="font-size: 12px; color: #28a745; font-weight: bold;">💰 EFECTIVIDAD</div>
                        <div style="font-size: 14px;">Pagadas: ${facturasPagadas}</div>
                        <div style="font-size: 14px;">Pendientes: ${facturasPendientes}</div>
                        <div style="font-size: 16px; font-weight: bold;">Tasa: ${tasaCobro}%</div>
                    </div>
                    
                    <div style="flex: 1; min-width: 180px; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <div style="font-size: 12px; color: #856404; font-weight: bold;">💵 MONTOS</div>
                        <div style="font-size: 14px;">Mínimo: $${montoMin.toFixed(2)}</div>
                        <div style="font-size: 14px;">Máximo: $${montoMax.toFixed(2)}</div>
                        <div style="font-size: 14px;">Promedio: $${montoPromedio.toFixed(2)}</div>
                    </div>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                    ${mejorCliente ? `
                    <div style="flex: 1; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #28a745;">
                        <div style="font-size: 12px; color: #28a745; font-weight: bold;">🏆 CLIENTE QUE MÁS FACTURÓ</div>
                        <div style="font-size: 14px; font-weight: bold;">${mejorCliente.nombre.substring(0, 45)}</div>
                        <div style="font-size: 13px;">Importe: $${mejorCliente.importe.toFixed(2)}</div>
                        <div style="font-size: 11px; color: #666;">${(mejorCliente.importe / totalImporte * 100).toFixed(1)}% del total</div>
                    </div>
                    ` : ''}
                    
                    ${peorCliente ? `
                    <div style="flex: 1; background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <div style="font-size: 12px; color: #dc3545; font-weight: bold;">📉 CLIENTE QUE MENOS FACTURÓ</div>
                        <div style="font-size: 14px; font-weight: bold;">${peorCliente.nombre.substring(0, 45)}</div>
                        <div style="font-size: 13px;">Importe: $${peorCliente.importe.toFixed(2)}</div>
                        <div style="font-size: 11px; color: #666;">${(peorCliente.importe / totalImporte * 100).toFixed(1)}% del total</div>
                    </div>
                    ` : ''}
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; text-align: center;">
                    <div style="font-size: 12px; color: #666;">
                        <strong>📌 Resumen Mensual:</strong> Total facturas: ${totalFacturas} | Importe: $${totalImporte.toFixed(2)} | 
                        Promedio por factura: $${montoPromedio.toFixed(2)} | Tasa de cobro: ${tasaCobro}%
                    </div>
                </div>
            </div>
        `;
    }

    // --- ENCABEZADO ---
    const headerContent = `
        <table style="width: 100%; border-bottom: 2px solid #0078D4; margin-bottom: 15px;">
            <tr>
                <td style="width: 80px; vertical-align: top;">
                    <img src="${REPORT_DATA.logo_base64}" width="65" alt="Logo" style="display: block;">
                </td>
                <td style="vertical-align: top; padding-left: 15px;">
                    <h1 style="margin: 0; font-size: 18px; font-weight: bold; color: #000;">PDL VISIONES</h1>
                    <p style="margin: 2px 0 0 0; font-size: 10px; color: #666;">Sistema Integral de Facturación - SISFACT</p>
                    <h2 style="margin: 8px 0 0 0; font-size: 14px; font-weight: bold; text-transform: uppercase; color: #0078D4;">
                        ${REPORT_DATA.titulo}
                    </h2>
                </td>
                <td style="vertical-align: top; text-align: right; font-size: 9px; color: #666;">
                    <p style="margin: 0;"><strong>ID Cierre:</strong> ${REPORT_DATA.id_cierre}</p>
                    <p style="margin: 2px 0;"><strong>Periodo:</strong> ${REPORT_DATA.periodo}</p>
                    <p style="margin: 2px 0;"><strong>Fecha Imp.:</strong> ${fechaStr}</p>
                    ${REPORT_DATA.hay_diferencias ? '<p style="margin: 2px 0; color: #dc3545; font-weight:bold;">⚠ CON DIFERENCIAS</p>' : ''}
                </td>
            </tr>
        </table>
    `;

    // --- FOOTER ---
    const footerContent = `
        <div class="footer-content">
            <table style="width: 100%; border-top: 1px solid #ccc; padding-top: 5px;">
                <tr>
                    <td style="width: 33%; vertical-align: top;">
                        <p style="margin: 0; font-weight: bold;">PDL VISIONES - SISFACT</p>
                        <p style="margin: 2px 0 0 0; font-size: 8px;">Versión ${REPORT_DATA.sistema_version}</p>
                    </td>
                    <td style="width: 34%; text-align: center; vertical-align: top;">
                        <p style="margin: 0; font-style: italic;">"Donde tu visión toma forma"</p>
                        <p style="margin: 2px 0 0 0; font-size: 8px;">www.pdlvisiones.com</p>
                    </td>
                    <td style="width: 33%; text-align: right; vertical-align: top;">
                        <p style="margin: 0;">Reporte ID: RPT-${String(REPORT_DATA.id_cierre).padStart(6, '0')}</p>
                        <p style="margin: 2px 0 0 0; font-size: 8px;">Uso interno exclusivo</p>
                    </td>
                </tr>
            </table>
            <div class="page-number"></div>
        </div>
    `;

    // --- PÁGINA 1: RESUMEN ---
    let page1 = `
        <div class="page-container">
            ${headerContent}
            
            <div class="content-wrapper">
                <div style="text-align: center; margin-bottom: 25px; margin-top: 10px;">
                    <h2 style="font-size: 20px; margin-bottom: 5px;">${tituloReporte} ${diferenciasHTML}</h2>
                    <h3 style="font-size: 15px; color: #555; font-weight: normal; margin-top: 0;">Cierre ${REPORT_DATA.tipo} - ${REPORT_DATA.periodo}</h3>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 25px;">
                    <div style="width: 48%; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                        <h4 style="margin-top: 0; border-bottom: 2px solid #0078D4; padding-bottom: 8px; color: #0078D4;">Generado</h4>
                        <table style="width: 100%; font-size: 11px; line-height: 1.6;">
                            <tr><td style="font-weight: bold; width: 70px;">Fecha:</td><td>${fechaStr}</td></tr>
                            <tr><td style="font-weight: bold;">Hora:</td><td>${horaStr}</td></tr>
                            <tr><td style="font-weight: bold;">Por:</td><td>${REPORT_DATA.usuario_actual}</td></tr>
                        </table>
                    </div>

                    <div style="width: 48%; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                        <h4 style="margin-top: 0; border-bottom: 2px solid #0078D4; padding-bottom: 8px; color: #0078D4;">Datos de Ejecución</h4>
                        <table style="width: 100%; font-size: 11px; line-height: 1.6;">
                            <tr><td style="font-weight: bold; width: 100px;">Período:</td><td>${REPORT_DATA.periodo}</td></tr>
                            <tr><td style="font-weight: bold;">Fecha Ejecución:</td><td>${REPORT_DATA.fecha_ejecucion}</td></tr>
                            <tr><td style="font-weight: bold;">Ejecutado por:</td><td>${REPORT_DATA.ejecutado_por}</td></tr>
                        </table>
                    </div>
                </div>
                
                ${estadisticasHTML}
                
                <div style="margin-top: 25px; padding: 15px; border: 2px solid #333; border-radius: 8px;">
                    <h4 style="margin-top: 0; text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">Totales Registrados</h4>
                    <table style="width: 100%; font-size: 12px;">
                        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Total Facturas:</strong></td>
                            <td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee; font-size: 14px;">${REPORT_DATA.total_facturas}</td>
                        </tr>
                        <tr><td style="padding: 8px;"><strong>Importe Total:</strong></td>
                            <td style="text-align: right; padding: 8px; font-size: 16px; color: #0078D4; font-weight: bold;">
                                $${new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2 }).format(REPORT_DATA.importe_total)} Pesos
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            ${footerContent}
        </div>
    `;

    // --- TABLA DE DATOS CON TAMAÑO DE LETRA MÁS GRANDE ---
    const FILAS_POR_PAGINA = 14; // Reducido para dar más espacio a cada fila
    let paginasTabla = [];
    
    if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas && REPORT_DATA.detalle_facturas.length > 0) {
        const facturas = REPORT_DATA.detalle_facturas;
        const totalFacturas = facturas.length;
        const totalPaginas = Math.ceil(totalFacturas / FILAS_POR_PAGINA);
        
        for (let pagina = 0; pagina < totalPaginas; pagina++) {
            const inicio = pagina * FILAS_POR_PAGINA;
            const fin = Math.min(inicio + FILAS_POR_PAGINA, totalFacturas);
            const facturasPagina = facturas.slice(inicio, fin);
            
            let rows = '';
            for (var i = 0; i < facturasPagina.length; i++) {
                var f = facturasPagina[i];
                var monto = parseFloat(f.total_general).toFixed(2);
                rows += '<tr style="font-size: 12px;">' +
                    '<td style="padding: 10px 8px; border: 1px solid #ddd; font-weight: bold;">' + (f.no_fact || '') + '</td>' +
                    '<td style="padding: 10px 8px; border: 1px solid #ddd;">' + (f.cliente || '').substring(0, 45) + '</td>' +
                    '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: center;">' + (f.fecha_emision || '') + '</td>' +
                    '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: center;">' + (f.estado || 'CERRADA') + '</td>' +
                    '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">$' + parseFloat(monto).toLocaleString('es-MX', { minimumFractionDigits: 2 }) + '</td>' +
                '</tr>';
            }
            
            let subtotalPagina = 0;
            for (var i = 0; i < facturasPagina.length; i++) {
                subtotalPagina += parseFloat(facturasPagina[i].total_general || 0);
            }
            const esUltimaPagina = (pagina === totalPaginas - 1);
            let totalGeneral = 0;
            for (var i = 0; i < facturas.length; i++) {
                totalGeneral += parseFloat(facturas[i].total_general || 0);
            }
            
            let footerRows = '';
            if (esUltimaPagina) {
                footerRows = '<tr style="background-color: #f0f0f0; font-weight: bold; font-size: 13px;">' +
                    '<td colspan="4" style="padding: 12px 8px; border: 1px solid #ddd; text-align: right;">TOTAL GENERAL:' +
                    '<td style="padding: 12px 8px; border: 1px solid #ddd; text-align: right;">$' + totalGeneral.toFixed(2) + '</td>' +
                '</tr>';
            } else {
                footerRows = '<tr style="background-color: #f8f9fa; font-size: 12px;">' +
                    '<td colspan="4" style="padding: 10px 8px; border: 1px solid #ddd; text-align: right;">Subtotal Página ' + (pagina + 1) + ':' +
                    '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: right;">$' + subtotalPagina.toFixed(2) + '</td>' +
                '</tr>';
            }
            
            const tablaHTML = `
                <table class="report-table" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                    <thead>
                        <tr style="background-color: #0078D4; color: white; font-size: 13px;">
                            <th style="padding: 12px 8px; border: 1px solid #005a9e; width: 15%;">Factura</th>
                            <th style="padding: 12px 8px; border: 1px solid #005a9e; width: 35%;">Cliente</th>
                            <th style="padding: 12px 8px; border: 1px solid #005a9e; width: 15%;">Emisión</th>
                            <th style="padding: 12px 8px; border: 1px solid #005a9e; width: 15%;">Estado</th>
                            <th style="padding: 12px 8px; border: 1px solid #005a9e; width: 20%; text-align: right;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                    <tfoot>
                        ${footerRows}
                    </tfoot>
                </table>
            `;
            
            paginasTabla.push(`
                <div class="page-container ${pagina > 0 ? 'page-break' : ''}">
                    ${headerContent}
                    <div class="content-wrapper">
                        <h3 style="border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 5px; margin-bottom: 15px; font-size: 16px;">
                            Detalle de Facturas - Página ${pagina + 1} de ${totalPaginas}
                            <span style="float: right; font-size: 12px; font-weight: normal; color: #666;">
                                Mostrando ${inicio + 1} - ${fin} de ${totalFacturas} facturas
                            </span>
                        </h3>
                        ${tablaHTML}
                    </div>
                    ${footerContent}
                </div>
            `);
        }
        
    } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses && REPORT_DATA.resumen_meses.length > 0) {
        let rows = '';
        let totalF = 0;
        let totalI = 0;
        let totalPagadas = 0;
        
        for (var i = 0; i < REPORT_DATA.resumen_meses.length; i++) {
            var m = REPORT_DATA.resumen_meses[i];
            var importe = parseFloat(m.importe_total);
            var mesNombre = REPORT_DATA.meses_nombres ? REPORT_DATA.meses_nombres[m.mes] : 'Mes ' + m.mes;
            totalF += parseInt(m.cantidad_facturas);
            totalI += importe;
            totalPagadas += parseInt(m.pagadas || 0);
            
            rows += '<tr style="font-size: 12px;">' +
                '<td style="padding: 10px 8px; border: 1px solid #ddd; font-weight: bold;">' + mesNombre + '</td>' +
                '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: center;">' + m.cantidad_facturas + '</td>' +
                '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: center;">' + (m.pagadas || 0) + '</td>' +
                '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: center;">' + (m.cerradas || 0) + '</td>' +
                '<td style="padding: 10px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">$' + importe.toLocaleString('es-MX', { minimumFractionDigits: 2 }) + '</td>' +
            '</tr>';
        }
        
        const tablaHTML = `
            <table class="report-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #0078D4; color: white; font-size: 13px;">
                        <th style="padding: 12px 8px; border: 1px solid #005a9e; width: 25%;">Mes</th>
                        <th style="padding: 12px 8px; border: 1px solid #005a9e; text-align: center;">Facturas</th>
                        <th style="padding: 12px 8px; border: 1px solid #005a9e; text-align: center;">Pagadas</th>
                        <th style="padding: 12px 8px; border: 1px solid #005a9e; text-align: center;">Cerradas</th>
                        <th style="padding: 12px 8px; border: 1px solid #005a9e; text-align: right;">Importe Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
                <tfoot>
                    <tr style="background-color: #f0f0f0; font-weight: bold; font-size: 13px;">
                        <td style="padding: 12px 8px; border: 1px solid #ddd;">TOTAL ANUAL</td>
                        <td style="padding: 12px 8px; border: 1px solid #ddd; text-align: center;">${totalF}</td>
                        <td style="padding: 12px 8px; border: 1px solid #ddd; text-align: center;">${totalPagadas}</td>
                        <td style="padding: 12px 8px; border: 1px solid #ddd; text-align: center;">${totalF}</td>
                        <td style="padding: 12px 8px; border: 1px solid #ddd; text-align: right;">$${totalI.toFixed(2)}</td>
                    </tr>
                </tfoot>
            </table>
        `;
        
        paginasTabla.push(`
            <div class="page-container page-break">
                ${headerContent}
                <div class="content-wrapper">
                    <h3 style="border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 5px; margin-bottom: 15px; font-size: 16px;">
                        Resumen Anual por Mes
                    </h3>
                    ${tablaHTML}
                </div>
                ${footerContent}
            </div>
        `);
    }

    // --- PÁGINA DE VERIFICACIÓN ---
    let pageVerificacion = '';
    if (REPORT_DATA.hay_diferencias || REPORT_DATA.observaciones) {
        const fmt = (num) => '$' + num.toFixed(2);
        pageVerificacion = `
            <div class="page-container page-break">
                ${headerContent}
                <div class="content-wrapper">
                    <h3 style="border-bottom: 2px solid #ccc; padding-bottom: 8px; margin-bottom: 15px; font-size: 16px;">Verificación de Totales</h3>
                    <div style="background: #fdfdfd; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <table style="width: 100%; font-size: 12px;">
                            <tr><td style="font-weight: bold; width: 45%;">Facturas en reporte (Actual):</td><td>${REPORT_DATA.total_facturas_real}</td></tr>
                            <tr><td style="font-weight: bold;">Importe en reporte (Actual):</td><td>${fmt(REPORT_DATA.total_importe_real)}</td></tr>
                            <tr><td colspan="2"><hr style="margin: 10px 0;"></td></tr>
                            <tr><td style="font-weight: bold;">Registro de cierre:</td><td>${REPORT_DATA.total_facturas}</td></tr>
                            <tr><td style="font-weight: bold;">Importe de cierre:</td><td>${fmt(REPORT_DATA.importe_total)}</td></tr>
                        </table>
                        ${REPORT_DATA.hay_diferencias 
                            ? '<div style="margin-top: 15px; padding: 12px; background: #fff5f5; border-left: 4px solid #dc3545; color: #dc3545; font-weight: bold; font-size: 12px;">⚠ Se detectaron diferencias entre el reporte actual y el registro de cierre histórico.</div>'
                            : '<div style="margin-top: 15px; padding: 12px; background: #f0fff4; border-left: 4px solid #28a745; color: #28a745; font-weight: bold; font-size: 12px;">✓ Los totales coinciden correctamente.</div>'}
                    </div>
                    <h3 style="border-bottom: 2px solid #ccc; padding-bottom: 8px; margin-bottom: 10px; font-size: 16px;">Observaciones</h3>
                    <div style="min-height: 80px; padding: 12px; background: #f9f9f9; border: 1px solid #eee; border-radius: 5px; font-size: 12px;">
                        ${REPORT_DATA.observaciones ? REPORT_DATA.observaciones.replace(/\n/g, '<br>') : 'Ninguna observación registrada.'}
                    </div>
                </div>
                ${footerContent}
            </div>
        `;
    }

    // --- HTML FINAL ---
    const htmlContent = `<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>${tituloReporte}</title>
        <style>
            @page { size: Letter; margin: 0.4in; }
            body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #fff; color: #333; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-container { width: 100%; min-height: 10in; position: relative; box-sizing: border-box; margin: 0 auto; display: flex; flex-direction: column; page-break-after: always; }
            .page-container:last-child { page-break-after: auto; }
            .page-break { page-break-before: always; }
            .content-wrapper { flex: 1; }
            .report-table { width: 100%; border-collapse: collapse; }
            .report-table th, .report-table td { border: 1px solid #ddd; }
            .report-table th { background-color: #0078D4; color: white; text-transform: uppercase; font-weight: bold; }
            .footer-content { font-size: 9px; color: #666; margin-top: 20px; padding-top: 8px; }
            .page-number::after { content: ""; display: none; }
            @media print { body { margin: 0; padding: 0; } .page-container { page-break-after: always; } }
        </style>
    </head>
    <body>
        ${page1}
        ${paginasTabla.join('')}
        ${pageVerificacion}
        <script>
            window.onload = function() {
                setTimeout(function() { window.print(); window.close(); }, 500);
            };
        <\/script>
    </body>
    </html>`;

    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

// 2. EXPORTAR A PDF (TABLA EN HOJA NUEVA - 20 FILAS POR PAGINA - CON TODAS LAS ESTADISTICAS)
function exportarPDF() {
    try {
        const { jsPDF } = window.jspdf;
        
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'letter'
        });
        
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 15;
        let currentY = margin;
        let currentPageNum = 1;
        let totalPagesEstimated = 1;
        
        const COLORS = {
            primary: [0, 120, 212],
            text: [60, 60, 60],
            danger: [220, 53, 69],
            success: [40, 167, 69],
            warning: [255, 193, 7],
            lightBg: [248, 249, 250]
        };

        // ==================== CALCULAR TODAS LAS ESTADISTICAS ====================
        let statsTipo = '';
        let statsData = {};
        
        if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses && REPORT_DATA.resumen_meses.length > 0) {
            const meses = REPORT_DATA.resumen_meses;
            let mejorMes = meses[0];
            let peorMes = meses[0];
            let sumaFacturas = 0;
            let sumaImporte = 0;
            let totalPagadas = 0;
            
            meses.forEach(function(mes) {
                const facturas = parseInt(mes.cantidad_facturas || 0);
                const importe = parseFloat(mes.importe_total || 0);
                const pagadas = parseInt(mes.pagadas || 0);
                sumaFacturas += facturas;
                sumaImporte += importe;
                totalPagadas += pagadas;
                if (importe > parseFloat(mejorMes.importe_total || 0)) mejorMes = mes;
                if (importe < parseFloat(peorMes.importe_total || 0) && importe > 0) peorMes = mes;
            });
            
            const mesesConDatos = meses.filter(function(m) { return parseInt(m.cantidad_facturas || 0) > 0; }).length;
            const promedioFacturas = mesesConDatos > 0 ? Math.round(sumaFacturas / mesesConDatos) : 0;
            const promedioImporte = mesesConDatos > 0 ? (sumaImporte / mesesConDatos) : 0;
            
            const primerSemestre = meses.slice(0, 6).reduce(function(s, m) { return s + parseFloat(m.importe_total || 0); }, 0);
            const segundoSemestre = meses.slice(6, 12).reduce(function(s, m) { return s + parseFloat(m.importe_total || 0); }, 0);
            let tendencia = 'Estable';
            let variacion = 0;
            if (primerSemestre > 0) {
                variacion = Math.abs((segundoSemestre - primerSemestre) / primerSemestre * 100).toFixed(1);
                if (segundoSemestre > primerSemestre * 1.1) tendencia = 'Crecimiento (+' + variacion + '%)';
                else if (segundoSemestre < primerSemestre * 0.9) tendencia = 'Decrecimiento (-' + variacion + '%)';
                else tendencia = 'Estable (' + variacion + '% var)';
            }
            
            statsTipo = 'anual';
            statsData = {
                mejorNombre: REPORT_DATA.meses_nombres ? REPORT_DATA.meses_nombres[mejorMes.mes] : 'Mes ' + mejorMes.mes,
                mejorFacturas: mejorMes.cantidad_facturas,
                mejorImporte: parseFloat(mejorMes.importe_total || 0),
                mejorPorcentaje: sumaImporte > 0 ? (parseFloat(mejorMes.importe_total || 0) / sumaImporte * 100).toFixed(1) : 0,
                peorNombre: REPORT_DATA.meses_nombres ? REPORT_DATA.meses_nombres[peorMes.mes] : 'Mes ' + peorMes.mes,
                peorFacturas: peorMes.cantidad_facturas || 0,
                peorImporte: parseFloat(peorMes.importe_total || 0),
                peorPorcentaje: sumaImporte > 0 ? (parseFloat(peorMes.importe_total || 0) / sumaImporte * 100).toFixed(1) : 0,
                promedioFacturas: promedioFacturas,
                promedioImporte: promedioImporte,
                mesesConDatos: mesesConDatos,
                tendencia: tendencia,
                tasaCobro: sumaFacturas > 0 ? (totalPagadas / sumaFacturas * 100).toFixed(1) : 0,
                sumaFacturas: sumaFacturas,
                sumaImporte: sumaImporte,
                totalPagadas: totalPagadas
            };
            
        } else if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas && REPORT_DATA.detalle_facturas.length > 0) {
            const facturas = REPORT_DATA.detalle_facturas;
            let totalFacturas = facturas.length;
            let totalImporte = 0;
            let facturasPagadas = 0;
            
            for (var i = 0; i < facturas.length; i++) {
                var f = facturas[i];
                var importe = parseFloat(f.total_general || 0);
                totalImporte += importe;
                var esPagada = (f.estado === 'PAGADA') || (f.fecha_pago && f.fecha_pago !== '0000-00-00');
                if (esPagada) facturasPagadas++;
            }
            
            const facturasPendientes = totalFacturas - facturasPagadas;
            const tasaCobro = totalFacturas > 0 ? (facturasPagadas / totalFacturas * 100).toFixed(1) : 0;
            const montoPromedio = totalFacturas > 0 ? totalImporte / totalFacturas : 0;
            
            var montos = [];
            for (var i = 0; i < facturas.length; i++) {
                montos.push(parseFloat(facturas[i].total_general || 0));
            }
            var montoMin = Math.min.apply(null, montos);
            var montoMax = Math.max.apply(null, montos);
            
            var clientesMap = {};
            for (var i = 0; i < facturas.length; i++) {
                var f = facturas[i];
                var cliente = f.cliente || 'Sin cliente';
                var importe = parseFloat(f.total_general || 0);
                if (clientesMap[cliente]) clientesMap[cliente] += importe;
                else clientesMap[cliente] = importe;
            }
            
            var clientesArray = [];
            for (var cliente in clientesMap) {
                clientesArray.push({ nombre: cliente, importe: clientesMap[cliente] });
            }
            clientesArray.sort(function(a, b) { return b.importe - a.importe; });
            
            var mejorCliente = clientesArray.length > 0 ? clientesArray[0] : null;
            var peorCliente = clientesArray.length > 0 ? clientesArray[clientesArray.length - 1] : null;
            
            statsTipo = 'mensual';
            statsData = {
                totalFacturas: totalFacturas,
                totalImporte: totalImporte,
                facturasPagadas: facturasPagadas,
                facturasPendientes: facturasPendientes,
                tasaCobro: tasaCobro,
                montoPromedio: montoPromedio,
                montoMin: montoMin,
                montoMax: montoMax,
                mejorCliente: mejorCliente,
                peorCliente: peorCliente
            };
        }

        // --- FUNCION ENCABEZADO ---
        const drawHeader = (pageNum = null, totalPages = null) => {
            let startY = margin;
            if (REPORT_DATA.logo_base64 && REPORT_DATA.logo_base64.includes('base64')) {
                doc.addImage(REPORT_DATA.logo_base64, 'PNG', margin, startY, 18, 18);
            }
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.setTextColor(COLORS.primary[0], COLORS.primary[1], COLORS.primary[2]);
            doc.text('PDL VISIONES - SISFACT', margin + 22, startY + 5);
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(100);
            doc.text('Sistema Integral de Facturacion', margin + 22, startY + 11);
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0);
            let tituloReporte = REPORT_DATA.titulo.toUpperCase();
            if (REPORT_DATA.hay_diferencias) {
                tituloReporte += ' (CON DIFERENCIAS)';
                doc.setTextColor(COLORS.danger[0], COLORS.danger[1], COLORS.danger[2]);
            }
            doc.text(tituloReporte, margin + 22, startY + 19);
            doc.setFontSize(8);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(80);
            const rightColX = pageWidth - margin - 5;
            doc.text('ID Cierre: ' + REPORT_DATA.id_cierre, rightColX, startY + 5, { align: 'right' });
            doc.text('Periodo: ' + REPORT_DATA.periodo, rightColX, startY + 11, { align: 'right' });
            doc.text('Tipo: ' + REPORT_DATA.tipo, rightColX, startY + 17, { align: 'right' });
            if (pageNum && totalPages) {
                doc.text('Pagina ' + pageNum + ' de ' + totalPages, rightColX, startY + 23, { align: 'right' });
                startY += 30;
            } else {
                doc.text('Generado: ' + REPORT_DATA.fecha_generacion, rightColX, startY + 23, { align: 'right' });
                startY += 30;
            }
            doc.setDrawColor(COLORS.primary[0], COLORS.primary[1], COLORS.primary[2]);
            doc.setLineWidth(0.5);
            doc.line(margin, startY, pageWidth - margin, startY);
            return startY + 5;
        };

        // --- FUNCION PIE DE PAGINA ---
        const drawFooter = (pageNum, totalPages) => {
            const footerY = pageHeight - 12;
            doc.setDrawColor(200);
            doc.setLineWidth(0.2);
            doc.line(margin, footerY - 5, pageWidth - margin, footerY - 5);
            doc.setFontSize(7);
            doc.setTextColor(100);
            doc.setFont('helvetica', 'normal');
            doc.text('PDL VISIONES - SISFACT', margin, footerY);
            doc.setFontSize(6);
            doc.text('Sistema Integral de Facturacion', margin, footerY + 3);
            doc.setFont('helvetica', 'italic');
            doc.setFontSize(7);
            doc.text('www.pdlvisiones.com', pageWidth / 2, footerY, { align: 'center' });
            doc.setFontSize(6);
            doc.text('"Donde tu vision toma forma"', pageWidth / 2, footerY + 3, { align: 'center' });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7);
            doc.text('Pagina ' + pageNum + ' de ' + totalPages, pageWidth - margin, footerY, { align: 'right' });
            doc.setFontSize(6);
            doc.text('RPT-' + String(REPORT_DATA.id_cierre).padStart(6, '0'), pageWidth - margin, footerY + 3, { align: 'right' });
            if (REPORT_DATA.hay_diferencias) {
                doc.setTextColor(COLORS.danger[0], COLORS.danger[1], COLORS.danger[2]);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(6);
                doc.text('CON DIFERENCIAS', pageWidth - margin - 25, footerY, { align: 'right' });
            }
        };

        // --- VERIFICAR ESPACIO ---
        const checkPageBreak = (heightNeeded) => {
            if (currentY + heightNeeded > pageHeight - margin - 12) {
                drawFooter(currentPageNum, totalPagesEstimated);
                doc.addPage();
                currentPageNum++;
                currentY = drawHeader(currentPageNum, totalPagesEstimated);
                return true;
            }
            return false;
        };

        // ==================== PORTADA (PAGINA 1) ====================
        currentY = drawHeader();
        
        // INFORMACION GENERAL
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor(0);
        doc.text('INFORMACION DEL CIERRE', margin, currentY);
        currentY += 6;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text('Fecha ejecucion: ' + REPORT_DATA.fecha_ejecucion, margin, currentY);
        doc.text('Ejecutado por: ' + REPORT_DATA.ejecutado_por, pageWidth / 2 + 5, currentY);
        currentY += 5;
        doc.text('Total facturas: ' + REPORT_DATA.total_facturas, margin, currentY);
        doc.text('Importe total: $' + REPORT_DATA.importe_total.toFixed(2), pageWidth / 2 + 5, currentY);
        currentY += 5;
        if (REPORT_DATA.tipo === 'Mensual') {
            doc.text('Facturas pagadas: ' + REPORT_DATA.cant_pagadas, margin, currentY);
            doc.text('Facturas contabilizadas: ' + REPORT_DATA.cant_contabilizadas, pageWidth / 2 + 5, currentY);
            currentY += 5;
        }
        currentY += 8;
        
        // DIFERENCIAS
        if (REPORT_DATA.hay_diferencias) {
            checkPageBreak(45);
            doc.setFillColor(255, 248, 225);
            doc.roundedRect(margin, currentY, pageWidth - (margin * 2), 22, 2, 2, 'F');
            doc.setTextColor(COLORS.danger[0], COLORS.danger[1], COLORS.danger[2]);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.text('ATENCION: SE DETECTARON DIFERENCIAS', margin + 5, currentY + 6);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(60);
            const msgLines = doc.splitTextToSize(REPORT_DATA.mensaje_diferencias, pageWidth - (margin * 2) - 10);
            doc.text(msgLines, margin + 5, currentY + 12);
            currentY += 28;
            
            const dif = REPORT_DATA.detalle_diferencias;
            let diffData = REPORT_DATA.tipo === 'Mensual' ? [
                ['Total Facturas', dif.facturas.registro, dif.facturas.actual, (dif.facturas.diferencia > 0 ? '+' : '') + dif.facturas.diferencia],
                ['Importe Total', '$' + dif.importe.registro.toFixed(2), '$' + dif.importe.actual.toFixed(2), (dif.importe.diferencia > 0 ? '+' : '') + '$' + dif.importe.diferencia.toFixed(2)],
                //['Facturas Pagadas', dif.pagadas.registro, dif.pagadas.actual, (dif.pagadas.diferencia > 0 ? '+' : '') + dif.pagadas.diferencia],
                //['Facturas Contabilizadas', dif.contabilizadas.registro, dif.contabilizadas.actual, (dif.contabilizadas.diferencia > 0 ? '+' : '') + dif.contabilizadas.diferencia]
            ] : [
                ['Total Facturas', dif.facturas.registro, dif.facturas.actual, (dif.facturas.diferencia > 0 ? '+' : '') + dif.facturas.diferencia],
                ['Importe Total', '$' + dif.importe.registro.toFixed(2), '$' + dif.importe.actual.toFixed(2), (dif.importe.diferencia > 0 ? '+' : '') + '$' + dif.importe.diferencia.toFixed(2)]
            ];
            doc.autoTable({
                startY: currentY,
                head: [['Concepto', 'Registro', 'Actual', 'Diferencia']],
                body: diffData,
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 2, halign: 'left' },
                headStyles: { fillColor: COLORS.danger, textColor: 255, fontStyle: 'bold' },
                columnStyles: { 1: { halign: 'right' }, 2: { halign: 'right' }, 3: { halign: 'right', fontStyle: 'bold' } },
                margin: { left: margin, right: margin }
            });
            currentY = doc.lastAutoTable.finalY + 8;
        }
        
        // --- ESTADISTICAS COMPLETAS EN PORTADA ---
        if (statsTipo === 'anual') {
            checkPageBreak(55);
            doc.setFillColor(240, 248, 255);
            doc.roundedRect(margin, currentY, pageWidth - (margin * 2), 52, 3, 3, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(COLORS.primary[0], COLORS.primary[1], COLORS.primary[2]);
            doc.text('ANALISIS ESTADISTICO ANUAL', margin + 5, currentY + 5);
            doc.setFontSize(8);
            doc.setTextColor(0);
            
            doc.setFont('helvetica', 'bold');
            doc.text('MEJOR MES:', margin + 5, currentY + 14);
            doc.setFont('helvetica', 'normal');
            doc.text(statsData.mejorNombre + ' - ' + statsData.mejorFacturas + ' facturas - $' + statsData.mejorImporte.toFixed(2) + ' (' + statsData.mejorPorcentaje + '% del total)', margin + 35, currentY + 14);
            
            doc.setFont('helvetica', 'bold');
            doc.text('PEOR MES:', margin + 5, currentY + 20);
            doc.setFont('helvetica', 'normal');
            doc.text(statsData.peorNombre + ' - ' + statsData.peorFacturas + ' facturas - $' + statsData.peorImporte.toFixed(2) + ' (' + statsData.peorPorcentaje + '% del total)', margin + 32, currentY + 20);
            
            doc.setFont('helvetica', 'bold');
            doc.text('PROMEDIO MENSUAL:', margin + 5, currentY + 26);
            doc.setFont('helvetica', 'normal');
            doc.text(statsData.promedioFacturas + ' facturas - $' + statsData.promedioImporte.toFixed(2) + ' (basado en ' + statsData.mesesConDatos + ' meses activos)', margin + 50, currentY + 26);
            
            doc.setFont('helvetica', 'bold');
            doc.text('TENDENCIA:', margin + 5, currentY + 32);
            doc.setFont('helvetica', 'normal');
            doc.text(statsData.tendencia + ' | Tasa de Cobro: ' + statsData.tasaCobro + '%', margin + 30, currentY + 32);
            
            doc.setFont('helvetica', 'bold');
            doc.text('RESUMEN ANUAL:', margin + 5, currentY + 38);
            doc.setFont('helvetica', 'normal');
            doc.text('Total facturas: ' + statsData.sumaFacturas + ' | Importe total: $' + statsData.sumaImporte.toFixed(2) + ' | Facturas pagadas: ' + statsData.totalPagadas + ' (' + statsData.tasaCobro + '%)', margin + 40, currentY + 38);
            currentY += 52;
            
        } else if (statsTipo === 'mensual') {
            checkPageBreak(65);
            doc.setFillColor(240, 248, 255);
            doc.roundedRect(margin, currentY, pageWidth - (margin * 2), 68, 3, 3, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(COLORS.primary[0], COLORS.primary[1], COLORS.primary[2]);
            doc.text('ANALISIS ESTADISTICO MENSUAL', margin + 5, currentY + 5);
            doc.setFontSize(8);
            doc.setTextColor(0);
            
            doc.setFont('helvetica', 'bold');
            doc.text('GENERAL:', margin + 5, currentY + 14);
            doc.setFont('helvetica', 'normal');
            doc.text('Facturas: ' + statsData.totalFacturas + ' | Importe: $' + statsData.totalImporte.toFixed(2) + ' | Promedio: $' + statsData.montoPromedio.toFixed(2), margin + 28, currentY + 14);
            
            doc.setFont('helvetica', 'bold');
            doc.text('EFECTIVIDAD:', margin + 5, currentY + 20);
            doc.setFont('helvetica', 'normal');
            doc.text('Pagadas: ' + statsData.facturasPagadas + ' | Pendientes: ' + statsData.facturasPendientes + ' | Tasa: ' + statsData.tasaCobro + '%', margin + 32, currentY + 20);
            
            doc.setFont('helvetica', 'bold');
            doc.text('MONTOS:', margin + 5, currentY + 26);
            doc.setFont('helvetica', 'normal');
            doc.text('Minimo: $' + statsData.montoMin.toFixed(2) + ' | Maximo: $' + statsData.montoMax.toFixed(2) + ' | Promedio: $' + statsData.montoPromedio.toFixed(2), margin + 25, currentY + 26);
            
            if (statsData.mejorCliente) {
                doc.setFont('helvetica', 'bold');
                doc.text('CLIENTE QUE MAS FACTURO:', margin + 5, currentY + 32);
                doc.setFont('helvetica', 'normal');
                var porcentajeMejor = (statsData.mejorCliente.importe / statsData.totalImporte * 100).toFixed(1);
                var nombreMejor = statsData.mejorCliente.nombre.substring(0, 35);
                doc.text(nombreMejor + ' - $' + statsData.mejorCliente.importe.toFixed(2) + ' (' + porcentajeMejor + '% del total)', margin + 65, currentY + 32);
            }
            
            if (statsData.peorCliente) {
                doc.setFont('helvetica', 'bold');
                doc.text('CLIENTE QUE MENOS FACTURO:', margin + 5, currentY + 38);
                doc.setFont('helvetica', 'normal');
                var porcentajePeor = (statsData.peorCliente.importe / statsData.totalImporte * 100).toFixed(1);
                var nombrePeor = statsData.peorCliente.nombre.substring(0, 35);
                doc.text(nombrePeor + ' - $' + statsData.peorCliente.importe.toFixed(2) + ' (' + porcentajePeor + '% del total)', margin + 70, currentY + 38);
            }
            
            doc.setFont('helvetica', 'bold');
            doc.text('RESUMEN MENSUAL:', margin + 5, currentY + 44);
            doc.setFont('helvetica', 'normal');
            doc.text('Total facturas: ' + statsData.totalFacturas + ' | Importe: $' + statsData.totalImporte.toFixed(2) + ' | Promedio por factura: $' + statsData.montoPromedio.toFixed(2) + ' | Tasa de cobro: ' + statsData.tasaCobro + '%', margin + 45, currentY + 44);
            currentY += 68;
        }
        
        // --- TOTALES REGISTRADOS EN PORTADA ---
        checkPageBreak(20);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor(0);
        doc.text('TOTALES REGISTRADOS', margin, currentY);
        currentY += 5;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(11);
        doc.setTextColor(COLORS.primary[0], COLORS.primary[1], COLORS.primary[2]);
        doc.text('Total Facturas: ' + REPORT_DATA.total_facturas, margin, currentY);
        doc.text('Importe Total: $' + REPORT_DATA.importe_total.toFixed(2), pageWidth / 2 + 5, currentY);
        currentY += 8;
        
        // ==================== SALTO DE PAGINA PARA TABLA ====================
        drawFooter(currentPageNum, totalPagesEstimated);
        doc.addPage();
        currentPageNum++;
        currentY = drawHeader(currentPageNum, totalPagesEstimated);
        
        // --- TABLA DE DATOS (25 FILAS POR PAGINA) ---
        const FILAS_POR_PAGINA = 25;
        
        if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas && REPORT_DATA.detalle_facturas.length > 0) {
            const facturas = REPORT_DATA.detalle_facturas;
            const totalFacturas = facturas.length;
            const totalPaginas = Math.ceil(totalFacturas / FILAS_POR_PAGINA);
            totalPagesEstimated = 2 + totalPaginas + (REPORT_DATA.hay_diferencias || REPORT_DATA.observaciones ? 1 : 0);
            
            for (let pagina = 0; pagina < totalPaginas; pagina++) {
                const inicio = pagina * FILAS_POR_PAGINA;
                const fin = Math.min(inicio + FILAS_POR_PAGINA, totalFacturas);
                const facturasPagina = facturas.slice(inicio, fin);
                
                const tableData = facturasPagina.map(f => [
                    f.no_fact || '',
                    f.cliente ? f.cliente.substring(0, 45) : '',
                    f.fecha_emision || '',
                    (f.estado === 'PAGADA' || (f.fecha_pago && f.fecha_pago !== '0000-00-00')) ? 'PAGADA' : (f.estado || 'CERRADA'),
                    '$' + parseFloat(f.total_general || 0).toFixed(2)
                ]);
                
                const subtotalPagina = facturasPagina.reduce(function(s, f) { return s + parseFloat(f.total_general || 0); }, 0);
                const esUltimaPagina = (pagina === totalPaginas - 1);
                const totalGeneral = facturas.reduce(function(s, f) { return s + parseFloat(f.total_general || 0); }, 0);
                
                if (esUltimaPagina) {
                    tableData.push(['', '', '', 'TOTAL GENERAL:', '$' + totalGeneral.toFixed(2)]);
                } else {
                    tableData.push(['', '', '', 'Subtotal Pagina ' + (pagina + 1) + ':', '$' + subtotalPagina.toFixed(2)]);
                }
                
                if (pagina > 0) {
                    drawFooter(currentPageNum, totalPagesEstimated);
                    doc.addPage();
                    currentPageNum++;
                    currentY = drawHeader(currentPageNum, totalPagesEstimated);
                }
                
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.setTextColor(0);
                doc.text('Detalle de Facturas - Pagina ' + (pagina + 1) + ' de ' + totalPaginas, margin, currentY);
                currentY += 5;
                doc.setFontSize(8);
                doc.setTextColor(100);
                doc.text('Mostrando ' + (inicio + 1) + ' - ' + fin + ' de ' + totalFacturas + ' facturas', margin, currentY);
                currentY += 4;
                
                doc.autoTable({
                    startY: currentY,
                    head: [['Factura', 'Cliente', 'Fecha', 'Estado', 'Monto']],
                    body: tableData,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 2, halign: 'left' },
                    headStyles: { fillColor: COLORS.primary, textColor: 255, fontStyle: 'bold', fontSize: 8, halign: 'center' },
                    columnStyles: { 0: { cellWidth: 28, fontStyle: 'bold' }, 1: { cellWidth: 70 }, 2: { cellWidth: 20, halign: 'center' }, 3: { cellWidth: 20, halign: 'center' }, 4: { cellWidth: 28, halign: 'right', fontStyle: 'bold' } },
                    margin: { left: margin, right: margin }
                });
                currentY = doc.lastAutoTable.finalY + 6;
            }
            
        } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses && REPORT_DATA.resumen_meses.length > 0) {
            totalPagesEstimated = 2 + (REPORT_DATA.hay_diferencias || REPORT_DATA.observaciones ? 1 : 0);
            
            const tableData = REPORT_DATA.resumen_meses.map(function(m) {
                return [
                    REPORT_DATA.meses_nombres ? REPORT_DATA.meses_nombres[m.mes] : 'Mes ' + m.mes,
                    m.cantidad_facturas || '0',
                    m.pagadas || '0',
                    m.cerradas || '0',
                    '$' + parseFloat(m.importe_total || 0).toFixed(2)
                ];
            });
            
            const totalFacturas = REPORT_DATA.resumen_meses.reduce(function(s, m) { return s + parseInt(m.cantidad_facturas || 0); }, 0);
            const totalImporte = REPORT_DATA.resumen_meses.reduce(function(s, m) { return s + parseFloat(m.importe_total || 0); }, 0);
            const totalPagadas = REPORT_DATA.resumen_meses.reduce(function(s, m) { return s + parseInt(m.pagadas || 0); }, 0);
            tableData.push(['TOTAL ANUAL', totalFacturas.toString(), totalPagadas.toString(), totalFacturas.toString(), '$' + totalImporte.toFixed(2)]);
            
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(0);
            doc.text('Resumen Anual por Mes', margin, currentY);
            currentY += 6;
            
            doc.autoTable({
                startY: currentY,
                head: [['Mes', 'Facturas', 'Pagadas', 'Cerradas', 'Importe Total']],
                body: tableData,
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 2, halign: 'center' },
                headStyles: { fillColor: COLORS.primary, textColor: 255, fontStyle: 'bold', fontSize: 8 },
                footStyles: { fillColor: COLORS.lightBg, fontStyle: 'bold' },
                columnStyles: { 0: { halign: 'left', cellWidth: 45 }, 4: { halign: 'right', cellWidth: 35 } },
                margin: { left: margin, right: margin }
            });
            currentY = doc.lastAutoTable.finalY + 6;
        }
        
        // --- VERIFICACION Y OBSERVACIONES ---
        if (REPORT_DATA.hay_diferencias || REPORT_DATA.observaciones) {
            if (currentY + 70 > pageHeight - margin - 12) {
                drawFooter(currentPageNum, totalPagesEstimated);
                doc.addPage();
                currentPageNum++;
                currentY = drawHeader(currentPageNum, totalPagesEstimated);
            }
            
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(0);
            doc.text('Verificacion de Totales', margin, currentY);
            currentY += 6;
            
            doc.autoTable({
                startY: currentY,
                body: [
                    ['Facturas en reporte (Actual):', REPORT_DATA.total_facturas_real.toString()],
                    ['Importe en reporte (Actual):', '$' + REPORT_DATA.total_importe_real.toFixed(2)],
                    ['', ''],
                    ['Registro de cierre:', REPORT_DATA.total_facturas.toString()],
                    ['Importe de cierre:', '$' + REPORT_DATA.importe_total.toFixed(2)]
                ],
                theme: 'plain',
                styles: { fontSize: 9, cellPadding: 3 },
                columnStyles: { 0: { fontStyle: 'bold', cellWidth: 65 }, 1: { cellWidth: 50 } },
                margin: { left: margin, right: margin }
            });
            currentY = doc.lastAutoTable.finalY + 6;
            
            if (REPORT_DATA.hay_diferencias) {
                doc.setFillColor(255, 245, 245);
                doc.rect(margin, currentY, pageWidth - (margin * 2), 10, 'F');
                doc.setTextColor(COLORS.danger[0], COLORS.danger[1], COLORS.danger[2]);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.text('ATENCION: Se detectaron diferencias entre el reporte actual y el registro de cierre historico.', margin + 5, currentY + 7);
                currentY += 14;
            } else {
                doc.setFillColor(240, 255, 240);
                doc.rect(margin, currentY, pageWidth - (margin * 2), 10, 'F');
                doc.setTextColor(COLORS.success[0], COLORS.success[1], COLORS.success[2]);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.text('Los totales coinciden correctamente.', margin + 5, currentY + 7);
                currentY += 14;
            }
            
            if (REPORT_DATA.observaciones) {
                if (currentY + 30 > pageHeight - margin - 12) {
                    drawFooter(currentPageNum, totalPagesEstimated);
                    doc.addPage();
                    currentPageNum++;
                    currentY = drawHeader(currentPageNum, totalPagesEstimated);
                }
                
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.setTextColor(0);
                doc.text('Observaciones', margin, currentY);
                currentY += 5;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                var obsLines = doc.splitTextToSize(REPORT_DATA.observaciones, pageWidth - (margin * 2));
                doc.text(obsLines, margin, currentY);
                currentY += obsLines.length * 4 + 5;
            }
        }
        
        // --- FINALIZAR ---
        const totalPages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            doc.setPage(i);
            drawFooter(i, totalPages);
        }
        
        doc.save('Cierre_' + REPORT_DATA.tipo + '_' + REPORT_DATA.periodo.replace(/\s+/g, '_') + '_' + new Date().getTime() + '.pdf');
        
        Swal.fire({ icon: 'success', title: 'PDF Generado', text: 'El reporte ha sido descargado exitosamente.', timer: 2000, showConfirmButton: false });
        
    } catch (error) {
        console.error('Error al exportar PDF:', error);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + error.message });
    }
}

// Función auxiliar para verificar si un campo está vacío
function empty(value) {
    return value === null || value === undefined || value === '' || value === 'null' || value === '0000-00-00';
}
		
        // 3. EXPORTAR A EXCEL (CON DIFERENCIAS)
function exportarExcel() {
    try {
        const wb = XLSX.utils.book_new();
        
        // HOJA 1: INFORMACIÓN DEL CIERRE CON DIFERENCIAS
        let infoData = [
            ['PDL VISIONES - SISFACT'],
            [REPORT_DATA.titulo + (REPORT_DATA.hay_diferencias ? ' (CON DIFERENCIAS)' : '')],
            [''],
            ['INFORMACIÓN GENERAL'],
            ['ID Cierre:', REPORT_DATA.id_cierre],
            ['Tipo:', REPORT_DATA.tipo],
            ['Periodo:', REPORT_DATA.periodo],
            ['Fecha ejecución:', REPORT_DATA.fecha_ejecucion],
            ['Ejecutado por:', REPORT_DATA.ejecutado_por],
            ['Generado por:', REPORT_DATA.usuario_actual],
            ['Fecha generación:', REPORT_DATA.fecha_generacion],
            [''],
            ['DATOS DEL CIERRE REGISTRADO'],
            ['Total facturas:', REPORT_DATA.total_facturas],
            ['Importe total:', `$${REPORT_DATA.importe_total.toFixed(2)}`]
        ];
        
        if (REPORT_DATA.tipo === 'Mensual') {
            infoData.push(['Facturas pagadas:', REPORT_DATA.cant_pagadas]);
            infoData.push(['Facturas contabilizadas:', REPORT_DATA.cant_contabilizadas]);
        }
        
        infoData.push(['']);
        infoData.push(['VERIFICACIÓN DE DATOS ACTUALES']);
        
        if (REPORT_DATA.tipo === 'Mensual') {
            infoData.push(['Facturas en sistema:', REPORT_DATA.total_facturas_real]);
            infoData.push(['Importe en sistema:', `$${REPORT_DATA.total_importe_real.toFixed(2)}`]);
            infoData.push(['Pagadas en sistema:', REPORT_DATA.facturas_pagadas_real]);
            infoData.push(['Contabilizadas en sistema:', REPORT_DATA.facturas_contabilizadas_real]);
        } else {
            infoData.push(['Facturas en sistema:', REPORT_DATA.total_facturas_real]);
            infoData.push(['Importe en sistema:', `$${REPORT_DATA.total_importe_real.toFixed(2)}`]);
        }
        
        // SECCIÓN: RESULTADO DE VERIFICACIÓN
        if (REPORT_DATA.hay_diferencias) {
            infoData.push(['']);
            infoData.push(['RESULTADO: SE DETECTARON DIFERENCIAS']);
            infoData.push(['Mensaje:', REPORT_DATA.mensaje_diferencias]);
            infoData.push(['']);
            
            // TABLA DETALLADA DE DIFERENCIAS
            infoData.push(['DETALLE DE DIFERENCIAS DETECTADAS']);
            infoData.push(['Concepto', 'Registro Cierre', 'Datos Actuales', 'Diferencia', 'Estado']);
            
            if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_diferencias) {
                const dif = REPORT_DATA.detalle_diferencias;
                
                // Facturas
                const difFact = dif.facturas.diferencia;
                const estadoFact = difFact === 0 ? 'OK' : 
                                  difFact > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                infoData.push([
                    'Total Facturas', 
                    dif.facturas.registro, 
                    dif.facturas.actual, 
                    (difFact > 0 ? '+' : '') + difFact,
                    estadoFact
                ]);
                
                // Importe
                const difImp = dif.importe.diferencia;
                const estadoImp = Math.abs(difImp) <= 0.01 ? 'OK' : 
                                 difImp > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                infoData.push([
                    'Importe Total', 
                    `$${dif.importe.registro.toFixed(2)}`, 
                    `$${dif.importe.actual.toFixed(2)}`, 
                    (difImp > 0 ? '+' : '') + `$${difImp.toFixed(2)}`,
                    estadoImp
                ]);
                
                // Pagadas
                const difPag = dif.pagadas.diferencia;
                const estadoPag = difPag === 0 ? 'OK' : 
                                 difPag > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                infoData.push([
                    'Facturas Pagadas', 
                    dif.pagadas.registro, 
                    dif.pagadas.actual, 
                    (difPag > 0 ? '+' : '') + difPag,
                    estadoPag
                ]);
                
                // Contabilizadas
                const difCont = dif.contabilizadas.diferencia;
                const estadoCont = difCont === 0 ? 'OK' : 
                                  difCont > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                infoData.push([
                    'Facturas Contabilizadas', 
                    dif.contabilizadas.registro, 
                    dif.contabilizadas.actual, 
                    (difCont > 0 ? '+' : '') + difCont,
                    estadoCont
                ]);
                
            } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.detalle_diferencias) {
                const dif = REPORT_DATA.detalle_diferencias;
                
                // Facturas
                const difFact = dif.facturas.diferencia;
                const estadoFact = difFact === 0 ? 'OK' : 
                                  difFact > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                infoData.push([
                    'Total Facturas', 
                    dif.facturas.registro, 
                    dif.facturas.actual, 
                    (difFact > 0 ? '+' : '') + difFact,
                    estadoFact
                ]);
                
                // Importe
                const difImp = dif.importe.diferencia;
                const estadoImp = Math.abs(difImp) <= 0.01 ? 'OK' : 
                                 difImp > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                infoData.push([
                    'Importe Total', 
                    `$${dif.importe.registro.toFixed(2)}`, 
                    `$${dif.importe.actual.toFixed(2)}`, 
                    (difImp > 0 ? '+' : '') + `$${difImp.toFixed(2)}`,
                    estadoImp
                ]);
            }
            
            // RECOMENDACIONES
            infoData.push(['']);
            infoData.push(['RECOMENDACIONES']);
            infoData.push(['1. Verificar si se han realizado cambios en las facturas después del cierre']);
            infoData.push(['2. Revisar el estado de las facturas (CERRADA, PAGADA, CONTABILIZADA)']);
            
            if (REPORT_DATA.tipo === 'Mensual') {
                infoData.push(['3. Validar que todas las facturas del mes estén correctamente contabilizadas']);
            } else {
                infoData.push(['3. Validar que todas las facturas del año estén correctamente registradas']);
            }
            
            infoData.push(['4. Considerar la posibilidad de ejecutar un nuevo cierre si las diferencias son significativas']);
            
        } else {
            infoData.push(['']);
            infoData.push(['RESULTADO: TODOS LOS DATOS COINCIDEN']);
            infoData.push(['✓ Verificación completada exitosamente']);
            infoData.push(['✓ No se detectaron diferencias entre el registro y los datos actuales']);
        }
        
        // Observaciones
        if (REPORT_DATA.observaciones) {
            infoData.push(['']);
            infoData.push(['OBSERVACIONES']);
            const obsLines = REPORT_DATA.observaciones.split('\n');
            obsLines.forEach(line => {
                infoData.push([line]);
            });
        }
        
        const wsInfo = XLSX.utils.aoa_to_sheet(infoData);
        
        // APLICAR ESTILOS Y FORMATOS
        // Título principal
        wsInfo['A1'].s = { 
            font: { bold: true, sz: 14, color: { rgb: "0078D4" } } 
        };
        
        // Subítulo con diferencias
        if (REPORT_DATA.hay_diferencias) {
            wsInfo['A2'].s = { 
                font: { bold: true, sz: 12, color: { rgb: "DC3545" } },
                fill: { fgColor: { rgb: "F8D7DA" } }
            };
        } else {
            wsInfo['A2'].s = { 
                font: { bold: true, sz: 12 } 
            };
        }
        
        // Sección de información general
        let infoGeneralRow = infoData.findIndex(row => row[0] === 'INFORMACIÓN GENERAL');
        if (infoGeneralRow !== -1) {
            const cell = XLSX.utils.encode_cell({r: infoGeneralRow, c: 0});
            wsInfo[cell].s = { 
                font: { bold: true, sz: 11 },
                fill: { fgColor: { rgb: "E9ECEF" } }
            };
        }
        
        // Sección de datos del cierre
        let datosCierreRow = infoData.findIndex(row => row[0] === 'DATOS DEL CIERRE REGISTRADO');
        if (datosCierreRow !== -1) {
            const cell = XLSX.utils.encode_cell({r: datosCierreRow, c: 0});
            wsInfo[cell].s = { 
                font: { bold: true, sz: 11 },
                fill: { fgColor: { rgb: "D4EDDA" } }
            };
        }
        
        // Sección de verificación
        let verificacionRow = infoData.findIndex(row => row[0] === 'VERIFICACIÓN DE DATOS ACTUALES');
        if (verificacionRow !== -1) {
            const cell = XLSX.utils.encode_cell({r: verificacionRow, c: 0});
            wsInfo[cell].s = { 
                font: { bold: true, sz: 11 },
                fill: { fgColor: { rgb: "FFF3CD" } }
            };
        }
        
        // Sección de diferencias
        if (REPORT_DATA.hay_diferencias) {
            // Resultado con diferencias
            let resultadoRow = infoData.findIndex(row => row[0] === 'RESULTADO: SE DETECTARON DIFERENCIAS');
            if (resultadoRow !== -1) {
                const cell = XLSX.utils.encode_cell({r: resultadoRow, c: 0});
                wsInfo[cell].s = { 
                    font: { bold: true, sz: 11, color: { rgb: "DC3545" } },
                    fill: { fgColor: { rgb: "F8D7DA" } }
                };
            }
            
            // Tabla de diferencias
            let tablaDifRow = infoData.findIndex(row => row[0] === 'DETALLE DE DIFERENCIAS DETECTADAS');
            if (tablaDifRow !== -1) {
                const cell = XLSX.utils.encode_cell({r: tablaDifRow, c: 0});
                wsInfo[cell].s = { 
                    font: { bold: true, sz: 11 },
                    fill: { fgColor: { rgb: "FFE5D0" } }
                };
                
                // Encabezado de tabla
                const headerRow = tablaDifRow + 1;
                for (let c = 0; c < 5; c++) {
                    const cell = XLSX.utils.encode_cell({r: headerRow, c: c});
                    if (wsInfo[cell]) {
                        wsInfo[cell].s = { 
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            fill: { fgColor: { rgb: "DC3545" } }
                        };
                    }
                }
                
                // Datos de la tabla con colores condicionales
                const startDataRow = headerRow + 1;
                const endDataRow = startDataRow + (REPORT_DATA.tipo === 'Mensual' ? 3 : 1);
                
                for (let r = startDataRow; r <= endDataRow; r++) {
                    // Columna de diferencia
                    const difCell = XLSX.utils.encode_cell({r: r, c: 3});
                    const estadoCell = XLSX.utils.encode_cell({r: r, c: 4});
                    
                    if (wsInfo[difCell] && wsInfo[difCell].v) {
                        const difValue = wsInfo[difCell].v;
                        const esPositivo = typeof difValue === 'string' ? 
                            difValue.includes('+') : difValue > 0;
                        const esNegativo = typeof difValue === 'string' ? 
                            (difValue.includes('-') && !difValue.includes('+')) : difValue < 0;
                        
                        // Color para diferencia
                        const difColor = esPositivo ? "28A745" : 
                                        esNegativo ? "DC3545" : "6C757D";
                        
                        wsInfo[difCell].s = {
                            font: { bold: true, color: { rgb: difColor } }
                        };
                        
                        // Color para estado
                        if (wsInfo[estadoCell]) {
                            const estadoColor = esPositivo ? "28A745" : 
                                              esNegativo ? "DC3545" : "6C757D";
                            wsInfo[estadoCell].s = {
                                font: { bold: true, color: { rgb: estadoColor } }
                            };
                        }
                    }
                }
            }
            
            // Recomendaciones
            let recomendacionesRow = infoData.findIndex(row => row[0] === 'RECOMENDACIONES');
            if (recomendacionesRow !== -1) {
                const cell = XLSX.utils.encode_cell({r: recomendacionesRow, c: 0});
                wsInfo[cell].s = { 
                    font: { bold: true, sz: 11 },
                    fill: { fgColor: { rgb: "D1ECF1" } }
                };
            }
        } else {
            // Sin diferencias - resultado OK
            let resultadoOkRow = infoData.findIndex(row => row[0] === 'RESULTADO: TODOS LOS DATOS COINCIDEN');
            if (resultadoOkRow !== -1) {
                const cell = XLSX.utils.encode_cell({r: resultadoOkRow, c: 0});
                wsInfo[cell].s = { 
                    font: { bold: true, sz: 11, color: { rgb: "28A745" } },
                    fill: { fgColor: { rgb: "D4EDDA" } }
                };
            }
        }
        
        // HOJA 2: DATOS DETALLADOS
        let wsData;
        if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas.length > 0) {
            const headers = ['Factura', 'Cliente', 'Fecha Emisión', 'Servicios', 'Estado', 'Monto'];
            const data = REPORT_DATA.detalle_facturas.map(f => [
                f.no_fact,
                f.cliente,
                f.fecha_emision,
                f.servicios || 'No especificado',
                f.estado,
                parseFloat(f.total_general || 0)
            ]);
            
            // Agregar total
            const total = REPORT_DATA.detalle_facturas.reduce((sum, f) => sum + parseFloat(f.total_general || 0), 0);
            data.push(['', '', '', '', 'Total:', total]);
            
            wsData = XLSX.utils.aoa_to_sheet([headers, ...data]);
            
            // Estilo para cabecera
            const range = XLSX.utils.decode_range(wsData['!ref']);
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cell = XLSX.utils.encode_cell({r: 0, c: C});
                if (!wsData[cell]) continue;
                wsData[cell].s = {
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "0078D4" } }
                };
            }
            
            // Estilo para total
            const totalRow = data.length;
            const totalCell = XLSX.utils.encode_cell({r: totalRow, c: 5});
            if (wsData[totalCell]) {
                wsData[totalCell].s = { 
                    font: { bold: true },
                    fill: { fgColor: { rgb: "E9ECEF" } }
                };
            }
            
            // Ajustar ancho de columnas
            const colWidths = [
                { wch: 15 }, // Factura
                { wch: 25 }, // Cliente
                { wch: 12 }, // Fecha
                { wch: 30 }, // Servicios
                { wch: 12 }, // Estado
                { wch: 15 }  // Monto
            ];
            wsData['!cols'] = colWidths;
            
        } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses.length > 0) {
            const headers = ['Mes', 'Facturas', 'Pagadas', 'Cerradas', 'Importe Total'];
            const data = REPORT_DATA.resumen_meses.map(mes => {
                const mesNombre = REPORT_DATA.meses_nombres && REPORT_DATA.meses_nombres[mes.mes] 
                    ? REPORT_DATA.meses_nombres[mes.mes] 
                    : `Mes ${mes.mes}`;
                return [
                    mesNombre,
                    parseInt(mes.cantidad_facturas || 0),
                    parseInt(mes.pagadas || 0),
                    parseInt(mes.cerradas || 0),
                    parseFloat(mes.importe_total || 0)
                ];
            });
            
            // Agregar total
            const totalFacturas = REPORT_DATA.resumen_meses.reduce((sum, m) => sum + parseInt(m.cantidad_facturas || 0), 0);
            const totalImporte = REPORT_DATA.resumen_meses.reduce((sum, m) => sum + parseFloat(m.importe_total || 0), 0);
            data.push(['Total Anual', totalFacturas, '-', '-', totalImporte]);
            
            wsData = XLSX.utils.aoa_to_sheet([headers, ...data]);
            
            // Estilo para cabecera
            const range = XLSX.utils.decode_range(wsData['!ref']);
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cell = XLSX.utils.encode_cell({r: 0, c: C});
                if (!wsData[cell]) continue;
                wsData[cell].s = {
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "0078D4" } }
                };
            }
            
            // Estilo para total
            const totalRow = data.length;
            for (let C = 0; C <= 4; ++C) {
                const cell = XLSX.utils.encode_cell({r: totalRow, c: C});
                if (wsData[cell]) {
                    wsData[cell].s = { 
                        font: { bold: true },
                        fill: { fgColor: { rgb: "E9ECEF" } }
                    };
                }
            }
            
            // Ajustar ancho de columnas
            const colWidths = [
                { wch: 20 }, // Mes
                { wch: 12 }, // Facturas
                { wch: 12 }, // Pagadas
                { wch: 12 }, // Cerradas
                { wch: 15 }  // Importe
            ];
            wsData['!cols'] = colWidths;
        }
        
        // Agregar hojas al libro
        XLSX.utils.book_append_sheet(wb, wsInfo, "Información");
        if (wsData) {
            XLSX.utils.book_append_sheet(wb, wsData, 
                REPORT_DATA.tipo === 'Mensual' ? "Detalle Facturas" : "Resumen por Mes");
        }
        
        // Descargar archivo
        const fecha = new Date().toISOString().split('T')[0];
        const nombreArchivo = `Cierre_${REPORT_DATA.tipo}_${REPORT_DATA.id_cierre}_${
            REPORT_DATA.hay_diferencias ? 'CON_DIFERENCIAS_' : ''
        }${fecha}.xlsx`;
        
        XLSX.writeFile(wb, nombreArchivo);
        
        Swal.fire({
            icon: 'success',
            title: 'Excel generado',
            text: 'El archivo Excel se ha descargado correctamente',
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar Excel:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el archivo Excel: ' + error.message
        });
    }
}
		
// 4. EXPORTAR A WORD (CORREGIDA Y MEJORADA - 2 PÁGINAS)
function exportarWord() {
    try {
        const ahora = new Date();
        const horaFormateada = ahora.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true 
        });
        const fechaCompleta = ahora.toLocaleDateString('es-ES', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        let contenidoHTML = `
            <html xmlns:o='urn:schemas-microsoft-com:office:office' 
                  xmlns:w='urn:schemas-microsoft-com:office:word' 
                  xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset="UTF-8">
                <title>Reporte de Cierre #${REPORT_DATA.id_cierre}</title>
                <style>
                    /* ESTILOS GENERALES */
                    body { 
                        font-family: 'Arial', sans-serif; 
                        font-size: 10pt; 
                        line-height: 1.4;
                        margin: 0;
                        padding: 0;
                    }
                    
                    /* CONTROL DE PÁGINAS */
                    .page-break {
                        page-break-before: always;
                        margin-top: 30px;
                    }
                    
                    /* ENCABEZADOS */
                    h1 { 
                        color: #0078D4; 
                        font-size: 16pt; 
                        margin: 10px 0 5px 0; 
                        border-bottom: 2px solid #0078D4;
                        padding-bottom: 5px;
                    }
                    
                    h2 { 
                        color: #333; 
                        font-size: 14pt; 
                        margin: 15px 0 10px 0; 
                    }
                    
                    h3 { 
                        color: #555; 
                        font-size: 12pt; 
                        margin: 12px 0 8px 0; 
                    }
                    
                    h4 { 
                        color: #666; 
                        font-size: 11pt; 
                        margin: 10px 0 6px 0; 
                    }
                    
                    /* TABLAS */
                    table { 
                        border-collapse: collapse; 
                        width: 100%; 
                        margin: 10px 0;
                    }
                    
                    .info-table td { 
                        border: none; 
                        padding: 5px 10px; 
                        vertical-align: top;
                    }
                    
                    .data-table { 
                        border: 1px solid #000; 
                        font-size: 9pt; 
                        table-layout: fixed;
                        margin-bottom: 15px;
                    }
                    
                    .data-table th { 
                        background-color: #0078D4; 
                        color: white; 
                        border: 1px solid #000; 
                        padding: 6px; 
                        text-align: center; 
                        font-weight: bold;
                    }
                    
                    .data-table td { 
                        border: 1px solid #666; 
                        padding: 4px; 
                        vertical-align: top;
                    }
                    
                    /* ALINEACIONES */
                    .text-left { text-align: left; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .text-bold { font-weight: bold; }
                    
                    /* TOTALES */
                    .tfoot-total td { 
                        background-color: #f0f0f0; 
                        font-weight: bold; 
                        border-top: 2px solid #000;
                    }
                    
                    /* SECCIÓN DE DIFERENCIAS */
                    .diferencias-section {
                        margin: 20px 0;
                        padding: 15px;
                        border-radius: 4px;
                        page-break-inside: avoid;
                    }
                    
                    .diferencias-normales {
                        background: #fff8e1;
                        border: 1px solid #ffc107;
                    }
                    
                    .diferencias-graves {
                        background: #f8d7da;
                        border: 1px solid #dc3545;
                    }
                    
                    .tabla-diferencias {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 10px 0;
                        font-size: 9pt;
                    }
                    
                    .tabla-diferencias th {
                        background: #dc3545;
                        color: white;
                        border: 1px solid #000;
                        padding: 6px;
                        text-align: left;
                        font-weight: bold;
                    }
                    
                    .tabla-diferencias td {
                        border: 1px solid #666;
                        padding: 5px;
                    }
                    
                    /* COLORES PARA DIFERENCIAS */
                    .diferencia-positiva { 
                        color: #28a745; 
                        font-weight: bold; 
                    }
                    
                    .diferencia-negativa { 
                        color: #dc3545; 
                        font-weight: bold; 
                    }
                    
                    .diferencia-cero { 
                        color: #6c757d; 
                    }
                    
                    /* RECOMENDACIONES */
                    .recomendaciones {
                        background: #f8f9fa;
                        border-left: 4px solid #007bff;
                        padding: 12px;
                        margin: 15px 0;
                        font-size: 9pt;
                    }
                    
                    /* ESTADÍSTICAS */
                    .estadisticas {
                        background: #e8f5e8;
                        border: 1px solid #28a745;
                        border-radius: 4px;
                        padding: 15px;
                        margin: 15px 0;
                    }
                    
                    .estadisticas-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 10px;
                        margin: 15px 0;
                    }
                    
                    .estadistica-item {
                        background: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 4px;
                        padding: 10px;
                        text-align: center;
                    }
                    
                    .estadistica-valor {
                        font-size: 18pt;
                        font-weight: bold;
                        color: #0078D4;
                        margin: 5px 0;
                    }
                    
                    .estadistica-label {
                        font-size: 8pt;
                        color: #6c757d;
                        text-transform: uppercase;
                    }
                    
                    /* OBSERVACIONES */
                    .observaciones {
                        background: #e3f2fd;
                        border-left: 4px solid #0078D4;
                        padding: 12px;
                        margin: 15px 0;
                        font-size: 9pt;
                    }
                    
                    /* PIE DE PÁGINA Y NUMERACIÓN */
                    .footer {
                        margin-top: 30px;
                        padding-top: 10px;
                        border-top: 1px solid #ccc;
                        font-size: 8pt;
                        color: #666;
                        position: relative;
                    }
                    
                    .page-number {
                        position: absolute;
                        right: 0;
                        bottom: 0;
                        font-size: 8pt;
                        color: #999;
                    }
                    
                    /* CONTENEDORES */
                    .section-container {
                        padding: 20px;
                    }
                    
                    .header-container {
                        border-bottom: 2px solid #0078D4;
                        margin-bottom: 20px;
                        padding-bottom: 10px;
                    }
                </style>
            </head>
            <body>
                <!-- PÁGINA 1: RESUMEN, ESTADÍSTICAS Y DIFERENCIAS -->
                <div class="Section1 section-container">
                
                    <!-- ENCABEZADO PRINCIPAL -->
                    <div class="header-container">
                        <table class="info-table" width="100%">
                            <tr>
                                <td width="60" valign="top">
                                    <img src="${REPORT_DATA.logo_base64}" width="55" height="55" 
                                         style="width:55px; height:55px;" alt="Logo PDL Visiones">
                                </td>
                                <td valign="top">
                                    <h1>PDL VISIONES - SISFACT</h1>
                                    <h2>${REPORT_DATA.titulo.toUpperCase()} ${
                                        REPORT_DATA.hay_diferencias ? '<span style="color: #dc3545;">(CON DIFERENCIAS)</span>' : ''
                                    }</h2>
                                    <p style="margin: 0; font-size: 9pt; color: #666;">
                                        Reporte de cierre #${REPORT_DATA.id_cierre} | Generado el ${fechaCompleta} a las ${horaFormateada}
                                    </p>
                                </td>
                                <td width="200" valign="top" style="text-align: right; font-size: 8pt; color: #555;">
                                    <p style="margin: 0 0 3px 0;"><b>ID Cierre:</b> ${REPORT_DATA.id_cierre}</p>
                                    <p style="margin: 0 0 3px 0;"><b>Tipo:</b> ${REPORT_DATA.tipo}</p>
                                    <p style="margin: 0 0 3px 0;"><b>Usuario:</b> ${REPORT_DATA.usuario_actual}</p>
                                    <p style="margin: 0 0 3px 0;"><b>Periodo:</b> ${REPORT_DATA.periodo}</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- INFORMACIÓN DEL CIERRE -->
                    <div style="margin: 20px 0; padding: 10px 0;">
                        <h3>Información del Cierre</h3>
                        <table class="info-table">
                            <tr>
                                <td width="50%"><b>Fecha ejecución:</b> ${REPORT_DATA.fecha_ejecucion}</td>
                                <td width="50%"><b>Ejecutado por:</b> ${REPORT_DATA.ejecutado_por}</td>
                            </tr>
                            <tr>
                                <td><b>Fecha generación:</b> ${REPORT_DATA.fecha_generacion}</td>
                                <td><b>Generado por:</b> ${REPORT_DATA.usuario_actual}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- ESTADÍSTICAS PRINCIPALES -->
                    <div class="estadisticas">
                        <h3>Resumen Estadístico</h3>
                        
                        <!-- ESTADÍSTICAS EN GRID -->
                        <div class="estadisticas-grid">
                            <div class="estadistica-item">
                                <div class="estadistica-valor">${REPORT_DATA.total_facturas}</div>
                                <div class="estadistica-label">Total Facturas</div>
                            </div>
                            <div class="estadistica-item">
                                <div class="estadistica-valor">$${REPORT_DATA.importe_total.toFixed(2)}</div>
                                <div class="estadistica-label">Importe Total</div>
                            </div>`;
        
        if (REPORT_DATA.tipo === 'Mensual') {
            contenidoHTML += `
                            <div class="estadistica-item">
                                <div class="estadistica-valor">${REPORT_DATA.cant_pagadas}</div>
                                <div class="estadistica-label">Facturas Pagadas</div>
                            </div>
                            <div class="estadistica-item">
                                <div class="estadistica-valor">${REPORT_DATA.cant_contabilizadas}</div>
                                <div class="estadistica-label">Contabilizadas</div>
                            </div>`;
        }
        
        contenidoHTML += `
                        </div>
                        
                        <!-- DATOS ACTUALES DEL SISTEMA -->
                        <div style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <h4 style="margin-top: 0; color: #0056b3;">Datos Actuales del Sistema</h4>
                            <table class="info-table">
                                <tr>
                                    <td width="50%"><b>Facturas en sistema:</b> ${REPORT_DATA.total_facturas_real}</td>
                                    <td width="50%"><b>Importe en sistema:</b> $${REPORT_DATA.total_importe_real.toFixed(2)}</td>
                                </tr>`;
        
        if (REPORT_DATA.tipo === 'Mensual') {
            contenidoHTML += `
                                <tr>
                                    <td><b>Pagadas en sistema:</b> ${REPORT_DATA.facturas_pagadas_real}</td>
                                    <td><b>Contabilizadas en sistema:</b> ${REPORT_DATA.facturas_contabilizadas_real}</td>
                                </tr>`;
        }
        
        contenidoHTML += `
                            </table>
                        </div>
                    </div>`;
        
        <!-- SECCIÓN DE DIFERENCIAS -->
        if (REPORT_DATA.hay_diferencias) {
            const esGrave = REPORT_DATA.diferencias_graves;
            const claseDif = esGrave ? 'diferencias-graves' : 'diferencias-normales';
            const colorTitulo = esGrave ? '#721c24' : '#856404';
            const colorIcono = esGrave ? '#dc3545' : '#ffc107';
            
            contenidoHTML += `
                    <div class="diferencias-section ${claseDif}">
                        <h3 style="color: ${colorTitulo}; margin-top: 0;">
                            <span style="color: ${colorIcono};">⚠</span> 
                            ${REPORT_DATA.mensaje_diferencias}
                        </h3>
                        
                        <p style="margin: 0 0 15px 0; font-size: 9pt;">
                            Se encontraron discrepancias entre los datos registrados en el cierre y los datos actuales del sistema.
                            ${REPORT_DATA.tipo === 'Mensual' ? 
                                'Esto puede deberse a facturas modificadas, eliminadas o agregadas después del cierre.' : 
                                'Esto puede deberse a cambios en las facturas anuales después del cierre.'}
                        </p>
                        
                        <!-- TABLA DE DIFERENCIAS -->
                        <table class="tabla-diferencias">
                            <thead>
                                <tr>
                                    <th width="30%">Concepto</th>
                                    <th width="20%">Registro de Cierre</th>
                                    <th width="20%">Datos Actuales</th>
                                    <th width="30%">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            if (REPORT_DATA.tipo === 'Mensual') {
                const dif = REPORT_DATA.detalle_diferencias;
                contenidoHTML += `
                                <tr>
                                    <td class="text-bold">Total Facturas</td>
                                    <td>${dif.facturas.registro}</td>
                                    <td>${dif.facturas.actual}</td>
                                    <td class="${dif.facturas.diferencia > 0 ? 'diferencia-positiva' : 
                                               dif.facturas.diferencia < 0 ? 'diferencia-negativa' : 
                                               'diferencia-cero'}">
                                        ${dif.facturas.diferencia > 0 ? '+' : ''}${dif.facturas.diferencia}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-bold">Importe Total</td>
                                    <td>$${dif.importe.registro.toFixed(2)}</td>
                                    <td>$${dif.importe.actual.toFixed(2)}</td>
                                    <td class="${dif.importe.diferencia > 0 ? 'diferencia-positiva' : 
                                               dif.importe.diferencia < 0 ? 'diferencia-negativa' : 
                                               'diferencia-cero'}">
                                        ${dif.importe.diferencia > 0 ? '+' : ''}$${dif.importe.diferencia.toFixed(2)}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-bold">Facturas Pagadas</td>
                                    <td>${dif.pagadas.registro}</td>
                                    <td>${dif.pagadas.actual}</td>
                                    <td class="${dif.pagadas.diferencia > 0 ? 'diferencia-positiva' : 
                                               dif.pagadas.diferencia < 0 ? 'diferencia-negativa' : 
                                               'diferencia-cero'}">
                                        ${dif.pagadas.diferencia > 0 ? '+' : ''}${dif.pagadas.diferencia}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-bold">Facturas Contabilizadas</td>
                                    <td>${dif.contabilizadas.registro}</td>
                                    <td>${dif.contabilizadas.actual}</td>
                                    <td class="${dif.contabilizadas.diferencia > 0 ? 'diferencia-positiva' : 
                                               dif.contabilizadas.diferencia < 0 ? 'diferencia-negativa' : 
                                               'diferencia-cero'}">
                                        ${dif.contabilizadas.diferencia > 0 ? '+' : ''}${dif.contabilizadas.diferencia}
                                    </td>
                                </tr>`;
            } else {
                const dif = REPORT_DATA.detalle_diferencias;
                contenidoHTML += `
                                <tr>
                                    <td class="text-bold">Total Facturas</td>
                                    <td>${dif.facturas.registro}</td>
                                    <td>${dif.facturas.actual}</td>
                                    <td class="${dif.facturas.diferencia > 0 ? 'diferencia-positiva' : 
                                               dif.facturas.diferencia < 0 ? 'diferencia-negativa' : 
                                               'diferencia-cero'}">
                                        ${dif.facturas.diferencia > 0 ? '+' : ''}${dif.facturas.diferencia}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-bold">Importe Total</td>
                                    <td>$${dif.importe.registro.toFixed(2)}</td>
                                    <td>$${dif.importe.actual.toFixed(2)}</td>
                                    <td class="${dif.importe.diferencia > 0 ? 'diferencia-positiva' : 
                                               dif.importe.diferencia < 0 ? 'diferencia-negativa' : 
                                               'diferencia-cero'}">
                                        ${dif.importe.diferencia > 0 ? '+' : ''}$${dif.importe.diferencia.toFixed(2)}
                                    </td>
                                </tr>`;
            }
            
            contenidoHTML += `
                            </tbody>
                        </table>
                        
                        <!-- ANÁLISIS DE DIFERENCIAS -->
                        <div style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                            <h4 style="margin-top: 0; color: #495057;">Análisis de Diferencias</h4>
                            <table class="info-table">
                                <tr>`;
            
            // Calcular porcentaje de diferencia
            if (REPORT_DATA.total_importe_real > 0) {
                const difImporteAbs = Math.abs(REPORT_DATA.detalle_diferencias?.importe?.diferencia || 0);
                const porcentajeDif = (difImporteAbs / REPORT_DATA.total_importe_real) * 100;
                contenidoHTML += `<td width="50%"><b>Porcentaje de diferencia:</b> ${porcentajeDif.toFixed(2)}%</td>`;
            }
            
            // Determinar severidad
            let severidad = 'BAJA';
            if (REPORT_DATA.diferencias_graves) {
                severidad = REPORT_DATA.dif_facturas_abs > 0 ? 'ALTA' : 'MEDIA';
            }
            contenidoHTML += `<td width="50%"><b>Severidad:</b> ${severidad}</td>`;
            
            contenidoHTML += `
                                </tr>
                            </table>
                        </div>
                        
                        <!-- RECOMENDACIONES -->
                        <div class="recomendaciones">
                            <h4 style="margin-top: 0; color: #0056b3;">Recomendaciones:</h4>
                            <ul style="margin: 0; padding-left: 15px;">
                                <li>Verificar si se han realizado cambios en las facturas después del cierre</li>
                                <li>Revisar el estado de las facturas (CERRADA, PAGADA, CONTABILIZADA)</li>`;
            
            if (REPORT_DATA.tipo === 'Mensual') {
                contenidoHTML += `<li>Validar que todas las facturas del mes estén correctamente contabilizadas</li>`;
            } else {
                contenidoHTML += `<li>Validar que todas las facturas del año estén correctamente registradas</li>`;
            }
            
            contenidoHTML += `
                                <li>Considerar la posibilidad de ejecutar un nuevo cierre si las diferencias son significativas</li>
                            </ul>
                        </div>
                    </div>`;
        } else {
            contenidoHTML += `
                    <div style="margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                        <h3 style="color: #155724; margin-top: 0;">
                            <span style="color: #28a745;">✓</span> 
                            VERIFICACIÓN SATISFACTORIA
                        </h3>
                        <p style="margin: 0; color: #155724;">
                            No se detectaron diferencias entre los datos registrados en el cierre y los datos actuales del sistema.
                            Todos los valores coinciden correctamente.
                        </p>
                    </div>`;
        }
        
        <!-- OBSERVACIONES (PÁGINA 1) -->
        if (REPORT_DATA.observaciones) {
            contenidoHTML += `
                    <div class="observaciones">
                        <h4>Observaciones</h4>
                        <p style="margin: 0; line-height: 1.4;">
                            ${REPORT_DATA.observaciones.replace(/\n/g, '<br>')}
                        </p>
                    </div>`;
        }
        
        <!-- PIE DE PÁGINA 1 -->
        contenidoHTML += `
                    <div class="footer">
                        <table width="100%">
                            <tr>
                                <td width="33%">
                                    <b>PDL VISIONES - SISFACT</b><br>
                                    Sistema Integral de Facturación y Control
                                </td>
                                <td width="34%" style="text-align: center;">
                                    <i>PDL Visiones</i><br>
                                    www.pdlvisiones.com<br>
                                    "Donde tu visión toma forma"
                                </td>
                                <td width="33%" style="text-align: right;">
                                    Página 1 de 2<br>
                                    Reporte ID: RPT-${REPORT_DATA.id_cierre.toString().padStart(6, '0')}
                                </td>
                            </tr>
                        </table>
                        <div class="page-number">Página 1</div>
                    </div>
                    
                </div> <!-- Fin Página 1 -->
                
                <!-- SALTO DE PÁGINA -->
                <div class="page-break"></div>
                
                <!-- PÁGINA 2: TABLA DE DATOS DETALLADOS -->
                <div class="Section2 section-container">
                
                    <!-- ENCABEZADO PÁGINA 2 -->
                    <div class="header-container">
                        <table class="info-table" width="100%">
                            <tr>
                                <td width="60" valign="top">
                                    <img src="${REPORT_DATA.logo_base64}" width="55" height="55" 
                                         style="width:55px; height:55px;" alt="Logo PDL Visiones">
                                </td>
                                <td valign="top">
                                    <h1>PDL VISIONES - SISFACT</h1>
                                    <h2>${REPORT_DATA.tipo === 'Mensual' ? 'DETALLE DE FACTURAS' : 'RESUMEN POR MES'}</h2>
                                    <p style="margin: 0; font-size: 9pt; color: #666;">
                                        ${REPORT_DATA.titulo} | Página 2 de 2
                                    </p>
                                </td>
                                <td width="200" valign="top" style="text-align: right; font-size: 8pt; color: #555;">
                                    <p style="margin: 0 0 3px 0;"><b>ID Cierre:</b> ${REPORT_DATA.id_cierre}</p>
                                    <p style="margin: 0 0 3px 0;"><b>Tipo:</b> ${REPORT_DATA.tipo}</p>
                                    <p style="margin: 0 0 3px 0;"><b>Periodo:</b> ${REPORT_DATA.periodo}</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- DATOS DETALLADOS -->
                    <div style="margin: 20px 0;">
                        <h3>${REPORT_DATA.tipo === 'Mensual' ? 'Detalle de Facturas del Mes' : 'Resumen Anual por Mes'}</h3>`;
        
        if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas.length > 0) {
            contenidoHTML += `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="15%">Factura</th>
                                    <th width="25%">Cliente</th>
                                    <th width="15%">Fecha Emisión</th>
                                    <th width="25%">Servicios</th>
                                    <th width="10%">Estado</th>
                                    <th width="10%">Monto</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            let subtotalPagadas = 0;
            let subtotalTotal = 0;
            
            REPORT_DATA.detalle_facturas.forEach(factura => {
                const monto = parseFloat(factura.total_general || 0);
                subtotalTotal += monto;
                if (factura.estado === 'PAGADA' || !empty(factura.fecha_pago) || !empty(factura.Ref_pago)) {
                    subtotalPagadas += monto;
                }
                
                contenidoHTML += `
                                <tr>
                                    <td class="text-left">${factura.no_fact}</td>
                                    <td class="text-left">${factura.cliente}</td>
                                    <td class="text-center">${factura.fecha_emision}</td>
                                    <td class="text-left">${factura.servicios || 'No especificado'}</td>
                                    <td class="text-center">${factura.estado}</td>
                                    <td class="text-right">$${monto.toFixed(2)}</td>
                                </tr>`;
            });
            
            const pendiente = subtotalTotal - subtotalPagadas;
            
            contenidoHTML += `
                            </tbody>
                            <tfoot class="tfoot-total">
                                <tr>
                                    <td colspan="5" class="text-right"><b>Total del Período:</b></td>
                                    <td class="text-right"><b>$${subtotalTotal.toFixed(2)}</b></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-right" style="color: #28a745;"><b>Total Pagado:</b></td>
                                    <td class="text-right" style="color: #28a745;"><b>$${subtotalPagadas.toFixed(2)}</b></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-right" style="color: #dc3545;"><b>Total Pendiente:</b></td>
                                    <td class="text-right" style="color: #dc3545;"><b>$${pendiente.toFixed(2)}</b></td>
                                </tr>
                            </tfoot>
                        </table>`;
            
        } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses.length > 0) {
            contenidoHTML += `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="25%">Mes</th>
                                    <th width="19%">Facturas</th>
                                    <th width="19%">Pagadas</th>
                                    <th width="19%">Cerradas</th>
                                    <th width="18%">Importe Total</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            let totalAnualFacturas = 0;
            let totalAnualImporte = 0;
            let totalAnualPagadas = 0;
            
            REPORT_DATA.resumen_meses.forEach(mes => {
                const mesNombre = REPORT_DATA.meses_nombres && REPORT_DATA.meses_nombres[mes.mes] 
                    ? REPORT_DATA.meses_nombres[mes.mes] 
                    : `Mes ${mes.mes}`;
                const facturas = parseInt(mes.cantidad_facturas || 0);
                const importe = parseFloat(mes.importe_total || 0);
                const pagadas = parseInt(mes.pagadas || 0);
                
                totalAnualFacturas += facturas;
                totalAnualImporte += importe;
                totalAnualPagadas += pagadas;
                
                contenidoHTML += `
                                <tr>
                                    <td class="text-left"><b>${mesNombre}</b></td>
                                    <td class="text-center">${facturas}</td>
                                    <td class="text-center">${pagadas}</td>
                                    <td class="text-center">${mes.cerradas || 0}</td>
                                    <td class="text-right">$${importe.toFixed(2)}</td>
                                </tr>`;
            });
            
            contenidoHTML += `
                            </tbody>
                            <tfoot class="tfoot-total">
                                <tr>
                                    <td class="text-left"><b>Total Anual</b></td>
                                    <td class="text-center"><b>${totalAnualFacturas}</b></td>
                                    <td class="text-center"><b>${totalAnualPagadas}</b></td>
                                    <td class="text-center"><b>${totalAnualFacturas}</b></td>
                                    <td class="text-right"><b>$${totalAnualImporte.toFixed(2)}</b></td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <!-- RESUMEN FINAL ANUAL -->
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                            <h4 style="margin-top: 0;">Resumen Anual</h4>
                            <table class="info-table">
                                <tr>
                                    <td width="50%"><b>Total facturas anuales:</b> ${totalAnualFacturas}</td>
                                    <td width="50%"><b>Importe total anual:</b> $${totalAnualImporte.toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <td><b>Facturas pagadas:</b> ${totalAnualPagadas}</td>
                                    <td><b>Porcentaje pagado:</b> ${totalAnualFacturas > 0 ? ((totalAnualPagadas / totalAnualFacturas) * 100).toFixed(1) : '0'}%</td>
                                </tr>
                            </table>
                        </div>`;
        } else {
            contenidoHTML += `
                        <div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 4px;">
                            <p style="font-size: 12pt; color: #6c757d;">
                                <i class="fas fa-file-alt" style="font-size: 24pt; margin-bottom: 10px; display: block; color: #adb5bd;"></i>
                                No hay datos disponibles para mostrar
                            </p>
                        </div>`;
        }
        
        <!-- PIE DE PÁGINA 2 -->
        contenidoHTML += `
                    </div>
                    
                    <div class="footer">
                        <table width="100%">
                            <tr>
                                <td width="33%">
                                    <b>PDL VISIONES - SISFACT</b><br>
                                    Sistema Integral de Facturación y Control
                                </td>
                                <td width="34%" style="text-align: center;">
                                    <i>PDL Visiones</i><br>
                                    www.pdlvisiones.com<br>
                                    "Donde tu visión toma forma"
                                </td>
                                <td width="33%" style="text-align: right;">
                                    Página 2 de 2<br>
                                    Reporte ID: RPT-${REPORT_DATA.id_cierre.toString().padStart(6, '0')}<br>
                                    ${REPORT_DATA.hay_diferencias ? '<span style="color: #dc3545; font-weight: bold;">CON DIFERENCIAS DETECTADAS</span>' : ''}
                                </td>
                            </tr>
                        </table>
                        <div class="page-number">Página 2</div>
                    </div>
                    
                </div> <!-- Fin Página 2 -->
            </body>
            </html>`;
        
        // Descargar archivo
        const fechaExportacion = new Date().toISOString().split('T')[0];
        const nombreArchivo = `Cierre_${REPORT_DATA.tipo}_${REPORT_DATA.id_cierre}_${
            REPORT_DATA.hay_diferencias ? 'CON_DIFERENCIAS_' : ''
        }${fechaExportacion}.doc`;
        
        const blob = new Blob(['\ufeff', contenidoHTML], { type: 'application/msword' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        
        Swal.fire({
            icon: 'success',
            title: 'Word generado',
            text: `Archivo "${nombreArchivo}" descargado correctamente (2 páginas).`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar Word:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el documento Word: ' + error.message
        });
    }
}
		
        
		
		
		// 5. EXPORTAR A CSV (CORREGIDA)
function exportarCSV() {
    try {
        let csvContent = '';
        
        // ENCABEZADO
        csvContent += '"PDL VISIONES - SISFACT"\n';
        csvContent += `"${REPORT_DATA.titulo}"`;
        if (REPORT_DATA.hay_diferencias) {
            csvContent += ' (CON DIFERENCIAS)';
        }
        csvContent += '\n';
        csvContent += `"Reporte de cierre #${REPORT_DATA.id_cierre}"\n\n`;
        
        // INFORMACIÓN GENERAL
        csvContent += '"INFORMACIÓN GENERAL"\n';
        csvContent += '"Campo","Valor"\n';
        csvContent += `"ID Cierre","${REPORT_DATA.id_cierre}"\n`;
        csvContent += `"Tipo","${REPORT_DATA.tipo}"\n`;
        csvContent += `"Periodo","${REPORT_DATA.periodo}"\n`;
        csvContent += `"Fecha ejecución","${REPORT_DATA.fecha_ejecucion}"\n`;
        csvContent += `"Ejecutado por","${REPORT_DATA.ejecutado_por}"\n`;
        csvContent += `"Generado por","${REPORT_DATA.usuario_actual}"\n`;
        csvContent += `"Fecha generación","${REPORT_DATA.fecha_generacion}"\n\n`;
        
        // DATOS DEL CIERRE
        csvContent += '"DATOS DEL CIERRE"\n';
        csvContent += '"Campo","Valor"\n';
        csvContent += `"Total facturas","${REPORT_DATA.total_facturas}"\n`;
        csvContent += `"Importe total","$${REPORT_DATA.importe_total.toFixed(2)}"\n`;
        
        if (REPORT_DATA.tipo === 'Mensual') {
            csvContent += `"Facturas pagadas","${REPORT_DATA.cant_pagadas}"\n`;
            csvContent += `"Facturas contabilizadas","${REPORT_DATA.cant_contabilizadas}"\n`;
        }
        csvContent += '\n';
        
        // VERIFICACIÓN DE DATOS ACTUALES
        csvContent += '"VERIFICACIÓN DE DATOS ACTUALES"\n';
        csvContent += '"Campo","Valor"\n';
        csvContent += `"Facturas en sistema","${REPORT_DATA.total_facturas_real}"\n`;
        csvContent += `"Importe en sistema","$${REPORT_DATA.total_importe_real.toFixed(2)}"\n`;
        
        if (REPORT_DATA.tipo === 'Mensual') {
            csvContent += `"Pagadas en sistema","${REPORT_DATA.facturas_pagadas_real}"\n`;
            csvContent += `"Contabilizadas en sistema","${REPORT_DATA.facturas_contabilizadas_real}"\n`;
        }
        csvContent += '\n';
        
        // RESULTADO DE VERIFICACIÓN
        if (REPORT_DATA.hay_diferencias) {
            csvContent += '"RESULTADO: SE DETECTARON DIFERENCIAS"\n';
            csvContent += `"Mensaje","${REPORT_DATA.mensaje_diferencias}"\n\n`;
            
            // TABLA DETALLADA DE DIFERENCIAS
            csvContent += '"DETALLE DE DIFERENCIAS"\n';
            csvContent += '"Concepto","Registro Cierre","Datos Actuales","Diferencia","Estado"\n';
            
            if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_diferencias) {
                const dif = REPORT_DATA.detalle_diferencias;
                
                // Facturas
                const difFact = dif.facturas.diferencia;
                const estadoFact = difFact === 0 ? 'OK' : 
                                  difFact > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                csvContent += `"Total Facturas","${dif.facturas.registro}","${dif.facturas.actual}","${
                    (difFact > 0 ? '+' : '') + difFact
                }","${estadoFact}"\n`;
                
                // Importe
                const difImp = dif.importe.diferencia;
                const estadoImp = Math.abs(difImp) <= 0.01 ? 'OK' : 
                                 difImp > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                csvContent += `"Importe Total","$${dif.importe.registro.toFixed(2)}","$${dif.importe.actual.toFixed(2)}","${
                    (difImp > 0 ? '+' : '') + '$' + difImp.toFixed(2)
                }","${estadoImp}"\n`;
                
                // Pagadas
                const difPag = dif.pagadas.diferencia;
                const estadoPag = difPag === 0 ? 'OK' : 
                                 difPag > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                csvContent += `"Facturas Pagadas","${dif.pagadas.registro}","${dif.pagadas.actual}","${
                    (difPag > 0 ? '+' : '') + difPag
                }","${estadoPag}"\n`;
                
                // Contabilizadas
                const difCont = dif.contabilizadas.diferencia;
                const estadoCont = difCont === 0 ? 'OK' : 
                                  difCont > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                csvContent += `"Facturas Contabilizadas","${dif.contabilizadas.registro}","${dif.contabilizadas.actual}","${
                    (difCont > 0 ? '+' : '') + difCont
                }","${estadoCont}"\n`;
                
            } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.detalle_diferencias) {
                const dif = REPORT_DATA.detalle_diferencias;
                
                // Facturas
                const difFact = dif.facturas.diferencia;
                const estadoFact = difFact === 0 ? 'OK' : 
                                  difFact > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                csvContent += `"Total Facturas","${dif.facturas.registro}","${dif.facturas.actual}","${
                    (difFact > 0 ? '+' : '') + difFact
                }","${estadoFact}"\n`;
                
                // Importe
                const difImp = dif.importe.diferencia;
                const estadoImp = Math.abs(difImp) <= 0.01 ? 'OK' : 
                                 difImp > 0 ? 'AUMENTO' : 'DISMINUCIÓN';
                csvContent += `"Importe Total","$${dif.importe.registro.toFixed(2)}","$${dif.importe.actual.toFixed(2)}","${
                    (difImp > 0 ? '+' : '') + '$' + difImp.toFixed(2)
                }","${estadoImp}"\n`;
            }
            
            csvContent += '\n';
            
            // ANÁLISIS DE DIFERENCIAS
            csvContent += '"ANÁLISIS DE DIFERENCIAS"\n';
            csvContent += '"Ítem","Valor"\n';
            
            // Calcular porcentaje de diferencia
            if (REPORT_DATA.total_importe_real > 0) {
                const difImporteAbs = Math.abs(REPORT_DATA.detalle_diferencias?.importe?.diferencia || 0);
                const porcentajeDif = (difImporteAbs / REPORT_DATA.total_importe_real) * 100;
                csvContent += `"Porcentaje de diferencia","${porcentajeDif.toFixed(2)}%"\n`;
            }
            
            // Determinar severidad
            let severidad = 'BAJA';
            if (REPORT_DATA.diferencias_graves) {
                severidad = REPORT_DATA.dif_facturas_abs > 0 ? 'ALTA' : 'MEDIA';
            }
            csvContent += `"Severidad","${severidad}"\n\n`;
            
            // RECOMENDACIONES
            csvContent += '"RECOMENDACIONES"\n';
            csvContent += '"Número","Recomendación"\n';
            csvContent += '"1","Verificar si se han realizado cambios en las facturas después del cierre"\n';
            csvContent += '"2","Revisar el estado de las facturas (CERRADA, PAGADA, CONTABILIZADA)"\n';
            
            if (REPORT_DATA.tipo === 'Mensual') {
                csvContent += '"3","Validar que todas las facturas del mes estén correctamente contabilizadas"\n';
            } else {
                csvContent += '"3","Validar que todas las facturas del año estén correctamente registradas"\n';
            }
            
            csvContent += '"4","Considerar la posibilidad de ejecutar un nuevo cierre si las diferencias son significativas"\n\n';
            
        } else {
            csvContent += '"RESULTADO: TODOS LOS DATOS COINCIDEN"\n';
            csvContent += '"Estado","OK"\n';
            csvContent += '"Mensaje","No se detectaron diferencias entre el registro y los datos actuales"\n\n';
        }
        
        // DATOS DETALLADOS
        if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas.length > 0) {
            csvContent += '"DETALLE DE FACTURAS"\n';
            csvContent += '"Factura","Cliente","Fecha Emisión","Servicios","Estado","Monto"\n';
            
            REPORT_DATA.detalle_facturas.forEach(factura => {
                csvContent += `"${factura.no_fact}","${factura.cliente}","${factura.fecha_emision}","${
                    factura.servicios || 'No especificado'
                }","${factura.estado}","$${parseFloat(factura.total_general || 0).toFixed(2)}"\n`;
            });
            
            const total = REPORT_DATA.detalle_facturas.reduce((sum, f) => sum + parseFloat(f.total_general || 0), 0);
            csvContent += `"","","","","Total","$${total.toFixed(2)}"\n\n`;
            
        } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses.length > 0) {
            csvContent += '"RESUMEN POR MES"\n';
            csvContent += '"Mes","Facturas","Pagadas","Cerradas","Importe Total"\n';
            
            REPORT_DATA.resumen_meses.forEach(mes => {
                const mesNombre = REPORT_DATA.meses_nombres && REPORT_DATA.meses_nombres[mes.mes] 
                    ? REPORT_DATA.meses_nombres[mes.mes] 
                    : `Mes ${mes.mes}`;
                csvContent += `"${mesNombre}","${mes.cantidad_facturas || 0}","${mes.pagadas || 0}","${
                    mes.cerradas || 0
                }","$${parseFloat(mes.importe_total || 0).toFixed(2)}"\n`;
            });
            
            const totalFacturas = REPORT_DATA.resumen_meses.reduce((sum, m) => sum + parseInt(m.cantidad_facturas || 0), 0);
            const totalImporte = REPORT_DATA.resumen_meses.reduce((sum, m) => sum + parseFloat(m.importe_total || 0), 0);
            csvContent += `"Total Anual","${totalFacturas}","-","-","$${totalImporte.toFixed(2)}"\n\n`;
        }
        
        // OBSERVACIONES
        if (REPORT_DATA.observaciones) {
            csvContent += '"OBSERVACIONES"\n';
            const obsLines = REPORT_DATA.observaciones.split('\n');
            obsLines.forEach(line => {
                csvContent += `"${line.replace(/"/g, '""')}"\n`;
            });
            csvContent += '\n';
        }
        
        // METADATOS DEL REPORTE
        csvContent += '"METADATOS"\n';
        csvContent += '"Campo","Valor"\n';
        csvContent += `"Fecha de exportación","${new Date().toLocaleString('es-ES')}"\n`;
        csvContent += `"Formato","CSV (Valores separados por comas)"\n`;
        csvContent += `"Codificación","UTF-8"\n`;
        csvContent += `"Sistema","PDL Visiones - SISFACT v2.3.3"\n`;
        
        // Descargar archivo
        const fecha = new Date().toISOString().split('T')[0];
        const fileName = `Cierre_${REPORT_DATA.tipo}_${REPORT_DATA.id_cierre}_${
            REPORT_DATA.hay_diferencias ? 'CON_DIFERENCIAS_' : ''
        }${fecha}.csv`;
        
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            icon: 'success',
            title: 'CSV generado',
            text: `Archivo "${fileName}" descargado correctamente`,
            timer: 3000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar CSV:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el archivo CSV'
        });
    }
}
        
		// 6. EXPORTAR A TXT (CORREGIDA)
function exportarTXT() {
    try {
        let txtContent = '';
        const separador = '='.repeat(80);
        const separadorMedio = '-'.repeat(80);
        const separadorCorto = '-'.repeat(40);
        
        // ENCABEZADO PRINCIPAL
        txtContent += separador + '\n';
        txtContent += 'PDL VISIONES - SISFACT\n';
        txtContent += separador + '\n';
        txtContent += REPORT_DATA.titulo.toUpperCase();
        if (REPORT_DATA.hay_diferencias) {
            txtContent += ' (CON DIFERENCIAS)';
        }
        txtContent += '\n';
        txtContent += `Reporte de cierre #${REPORT_DATA.id_cierre}\n`;
        txtContent += separadorMedio + '\n\n';
        
        // INFORMACIÓN GENERAL
        txtContent += 'INFORMACIÓN GENERAL\n';
        txtContent += separadorCorto + '\n';
        txtContent += `ID Cierre: ${REPORT_DATA.id_cierre}\n`;
        txtContent += `Tipo: ${REPORT_DATA.tipo}\n`;
        txtContent += `Periodo: ${REPORT_DATA.periodo}\n`;
        txtContent += `Fecha ejecución: ${REPORT_DATA.fecha_ejecucion}\n`;
        txtContent += `Ejecutado por: ${REPORT_DATA.ejecutado_por}\n`;
        txtContent += `Generado por: ${REPORT_DATA.usuario_actual}\n`;
        txtContent += `Fecha generación: ${REPORT_DATA.fecha_generacion}\n\n`;
        
        // DATOS DEL CIERRE
        txtContent += 'DATOS DEL CIERRE REGISTRADO\n';
        txtContent += separadorCorto + '\n';
        txtContent += `Total facturas: ${REPORT_DATA.total_facturas}\n`;
        txtContent += `Importe total: $${REPORT_DATA.importe_total.toFixed(2)}\n`;
        
        if (REPORT_DATA.tipo === 'Mensual') {
            txtContent += `Facturas pagadas: ${REPORT_DATA.cant_pagadas}\n`;
            txtContent += `Facturas contabilizadas: ${REPORT_DATA.cant_contabilizadas}\n`;
        }
        txtContent += '\n';
        
        // VERIFICACIÓN DE DATOS ACTUALES
        txtContent += 'VERIFICACIÓN DE DATOS ACTUALES\n';
        txtContent += separadorCorto + '\n';
        txtContent += `Facturas en sistema: ${REPORT_DATA.total_facturas_real}\n`;
        txtContent += `Importe en sistema: $${REPORT_DATA.total_importe_real.toFixed(2)}\n`;
        
        if (REPORT_DATA.tipo === 'Mensual') {
            txtContent += `Pagadas en sistema: ${REPORT_DATA.facturas_pagadas_real}\n`;
            txtContent += `Contabilizadas en sistema: ${REPORT_DATA.facturas_contabilizadas_real}\n`;
        }
        txtContent += '\n';
        
        // RESULTADO DE VERIFICACIÓN
        if (REPORT_DATA.hay_diferencias) {
            txtContent += 'RESULTADO: ¡SE DETECTARON DIFERENCIAS!\n';
            txtContent += separadorMedio + '\n';
            txtContent += `${REPORT_DATA.mensaje_diferencias}\n\n`;
            
            // TABLA DETALLADA DE DIFERENCIAS
            txtContent += 'DETALLE DE DIFERENCIAS\n';
            txtContent += separadorMedio + '\n';
            txtContent += 'Concepto'.padEnd(25) + 
                         'Cierre'.padEnd(12) + 
                         'Actual'.padEnd(12) + 
                         'Diferencia'.padStart(15) + 
                         '   Estado\n';
            txtContent += separadorMedio + '\n';
            
            if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_diferencias) {
                const dif = REPORT_DATA.detalle_diferencias;
                
                // Facturas
                const difFact = dif.facturas.diferencia;
                const estadoFact = difFact === 0 ? 'OK' : 
                                  difFact > 0 ? 'AUMENTO ▲' : 'DISMINUCIÓN ▼';
                txtContent += 'Total Facturas'.padEnd(25) + 
                            dif.facturas.registro.toString().padEnd(12) + 
                            dif.facturas.actual.toString().padEnd(12) + 
                            (difFact > 0 ? '+' : '') + difFact.toString().padStart(15) + 
                            '   ' + estadoFact + '\n';
                
                // Importe
                const difImp = dif.importe.diferencia;
                const estadoImp = Math.abs(difImp) <= 0.01 ? 'OK' : 
                                 difImp > 0 ? 'AUMENTO ▲' : 'DISMINUCIÓN ▼';
                txtContent += 'Importe Total'.padEnd(25) + 
                            ('$' + dif.importe.registro.toFixed(2)).padEnd(12) + 
                            ('$' + dif.importe.actual.toFixed(2)).padEnd(12) + 
                            (difImp > 0 ? '+' : '') + ('$' + difImp.toFixed(2)).padStart(15) + 
                            '   ' + estadoImp + '\n';
                
                // Pagadas
                const difPag = dif.pagadas.diferencia;
                const estadoPag = difPag === 0 ? 'OK' : 
                                 difPag > 0 ? 'AUMENTO ▲' : 'DISMINUCIÓN ▼';
                txtContent += 'Facturas Pagadas'.padEnd(25) + 
                            dif.pagadas.registro.toString().padEnd(12) + 
                            dif.pagadas.actual.toString().padEnd(12) + 
                            (difPag > 0 ? '+' : '') + difPag.toString().padStart(15) + 
                            '   ' + estadoPag + '\n';
                
                // Contabilizadas
                const difCont = dif.contabilizadas.diferencia;
                const estadoCont = difCont === 0 ? 'OK' : 
                                  difCont > 0 ? 'AUMENTO ▲' : 'DISMINUCIÓN ▼';
                txtContent += 'Fact. Contabilizadas'.padEnd(25) + 
                            dif.contabilizadas.registro.toString().padEnd(12) + 
                            dif.contabilizadas.actual.toString().padEnd(12) + 
                            (difCont > 0 ? '+' : '') + difCont.toString().padStart(15) + 
                            '   ' + estadoCont + '\n';
                
            } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.detalle_diferencias) {
                const dif = REPORT_DATA.detalle_diferencias;
                
                // Facturas
                const difFact = dif.facturas.diferencia;
                const estadoFact = difFact === 0 ? 'OK' : 
                                  difFact > 0 ? 'AUMENTO ▲' : 'DISMINUCIÓN ▼';
                txtContent += 'Total Facturas'.padEnd(25) + 
                            dif.facturas.registro.toString().padEnd(12) + 
                            dif.facturas.actual.toString().padEnd(12) + 
                            (difFact > 0 ? '+' : '') + difFact.toString().padStart(15) + 
                            '   ' + estadoFact + '\n';
                
                // Importe
                const difImp = dif.importe.diferencia;
                const estadoImp = Math.abs(difImp) <= 0.01 ? 'OK' : 
                                 difImp > 0 ? 'AUMENTO ▲' : 'DISMINUCIÓN ▼';
                txtContent += 'Importe Total'.padEnd(25) + 
                            ('$' + dif.importe.registro.toFixed(2)).padEnd(12) + 
                            ('$' + dif.importe.actual.toFixed(2)).padEnd(12) + 
                            (difImp > 0 ? '+' : '') + ('$' + difImp.toFixed(2)).padStart(15) + 
                            '   ' + estadoImp + '\n';
            }
            
            txtContent += separadorMedio + '\n\n';
            
            // ANÁLISIS DE DIFERENCIAS
            txtContent += 'ANÁLISIS DE DIFERENCIAS\n';
            txtContent += separadorCorto + '\n';
            
            // Calcular porcentaje de diferencia
            if (REPORT_DATA.total_importe_real > 0) {
                const difImporteAbs = Math.abs(REPORT_DATA.detalle_diferencias?.importe?.diferencia || 0);
                const porcentajeDif = (difImporteAbs / REPORT_DATA.total_importe_real) * 100;
                txtContent += `Porcentaje de diferencia: ${porcentajeDif.toFixed(2)}%\n`;
            }
            
            // Determinar severidad
            let severidad = 'BAJA';
            if (REPORT_DATA.diferencias_graves) {
                severidad = REPORT_DATA.dif_facturas_abs > 0 ? 'ALTA' : 'MEDIA';
            }
            txtContent += `Severidad: ${severidad}\n\n`;
            
            // RECOMENDACIONES
            txtContent += 'RECOMENDACIONES\n';
            txtContent += separadorCorto + '\n';
            txtContent += '1. Verificar si se han realizado cambios en las facturas después del cierre\n';
            txtContent += '2. Revisar el estado de las facturas (CERRADA, PAGADA, CONTABILIZADA)\n';
            
            if (REPORT_DATA.tipo === 'Mensual') {
                txtContent += '3. Validar que todas las facturas del mes estén correctamente contabilizadas\n';
            } else {
                txtContent += '3. Validar que todas las facturas del año estén correctamente registradas\n';
            }
            
            txtContent += '4. Considerar la posibilidad de ejecutar un nuevo cierre si las diferencias son significativas\n\n';
            
        } else {
            txtContent += 'RESULTADO: TODOS LOS DATOS COINCIDEN ✓\n';
            txtContent += separadorCorto + '\n';
            txtContent += '✓ Verificación completada exitosamente\n';
            txtContent += '✓ No se detectaron diferencias entre el registro y los datos actuales\n\n';
        }
        
        // DATOS DETALLADOS
        if (REPORT_DATA.tipo === 'Mensual' && REPORT_DATA.detalle_facturas.length > 0) {
            txtContent += 'DETALLE DE FACTURAS\n';
            txtContent += separadorMedio + '\n';
            txtContent += 'Factura'.padEnd(15) + 
                         'Cliente'.padEnd(25) + 
                         'Fecha'.padEnd(12) + 
                         'Estado'.padEnd(12) + 
                         'Monto'.padStart(12) + '\n';
            txtContent += separadorMedio + '\n';
            
            REPORT_DATA.detalle_facturas.forEach(factura => {
                txtContent += (factura.no_fact || '').padEnd(15) + 
                            (factura.cliente || '').substring(0, 23).padEnd(25) + 
                            (factura.fecha_emision || '').padEnd(12) + 
                            (factura.estado || '').padEnd(12) + 
                            `$${parseFloat(factura.total_general || 0).toFixed(2)}`.padStart(12) + '\n';
            });
            
            const total = REPORT_DATA.detalle_facturas.reduce((sum, f) => sum + parseFloat(f.total_general || 0), 0);
            txtContent += separadorMedio + '\n';
            txtContent += 'TOTAL:'.padEnd(67) + `$${total.toFixed(2)}`.padStart(12) + '\n\n';
            
        } else if (REPORT_DATA.tipo === 'Anual' && REPORT_DATA.resumen_meses.length > 0) {
            txtContent += 'RESUMEN POR MES\n';
            txtContent += separadorMedio + '\n';
            txtContent += 'Mes'.padEnd(15) + 
                         'Facturas'.padEnd(10) + 
                         'Pagadas'.padEnd(10) + 
                         'Cerradas'.padEnd(10) + 
                         'Importe Total'.padStart(15) + '\n';
            txtContent += separadorMedio + '\n';
            
            REPORT_DATA.resumen_meses.forEach(mes => {
                const mesNombre = REPORT_DATA.meses_nombres && REPORT_DATA.meses_nombres[mes.mes] 
                    ? REPORT_DATA.meses_nombres[mes.mes] 
                    : `Mes ${mes.mes}`;
                txtContent += mesNombre.padEnd(15) + 
                            (mes.cantidad_facturas || 0).toString().padEnd(10) + 
                            (mes.pagadas || 0).toString().padEnd(10) + 
                            (mes.cerradas || 0).toString().padEnd(10) + 
                            `$${parseFloat(mes.importe_total || 0).toFixed(2)}`.padStart(15) + '\n';
            });
            
            const totalFacturas = REPORT_DATA.resumen_meses.reduce((sum, m) => sum + parseInt(m.cantidad_facturas || 0), 0);
            const totalImporte = REPORT_DATA.resumen_meses.reduce((sum, m) => sum + parseFloat(m.importe_total || 0), 0);
            txtContent += separadorMedio + '\n';
            txtContent += 'Total Anual'.padEnd(15) + 
                        totalFacturas.toString().padEnd(10) + 
                        '-'.padEnd(10) + 
                        '-'.padEnd(10) + 
                        `$${totalImporte.toFixed(2)}`.padStart(15) + '\n\n';
        }
        
        // OBSERVACIONES
        if (REPORT_DATA.observaciones) {
            txtContent += 'OBSERVACIONES\n';
            txtContent += separadorCorto + '\n';
            txtContent += (REPORT_DATA.observaciones || '') + '\n\n';
        }
        
        // PIE DE PÁGINA
        txtContent += separador + '\n';
        txtContent += 'PDL VISIONES - Sistema Integral de Facturación y Control\n';
        txtContent += 'www.pdlvisiones.com - "Donde tu visión toma forma"\n';
        txtContent += separadorMedio + '\n';
        txtContent += `Reporte ID: RPT-${REPORT_DATA.id_cierre.toString().padStart(6, '0')}\n`;
        txtContent += `Cierre ${REPORT_DATA.tipo} - ${REPORT_DATA.periodo}\n`;
        txtContent += `Total: $${REPORT_DATA.importe_total.toFixed(2)} | Facturas: ${REPORT_DATA.total_facturas}\n`;
        
        if (REPORT_DATA.hay_diferencias) {
            txtContent += 'ESTADO: CON DIFERENCIAS DETECTADAS\n';
        } else {
            txtContent += 'ESTADO: VERIFICACIÓN OK\n';
        }
        
        txtContent += `Generado: ${new Date().toLocaleString('es-ES')}\n`;
        txtContent += separador;
        
        // Descargar archivo
        const fecha = new Date().toISOString().split('T')[0];
        const fileName = `Cierre_${REPORT_DATA.tipo}_${REPORT_DATA.id_cierre}_${
            REPORT_DATA.hay_diferencias ? 'CON_DIFERENCIAS_' : ''
        }${fecha}.txt`;
        
        const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            icon: 'success',
            title: 'TXT generado',
            text: `Archivo "${fileName}" descargado correctamente`,
            timer: 3000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar TXT:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el archivo TXT'
        });
    }
}
		
        
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl+P para imprimir
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                imprimirReporte();
            }
            
            // Ctrl+E para exportar
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                toggleExportMenu();
            }
            
            // Esc para volver al historial
            if (e.key === 'Escape') {
                window.location.href = 'cierres_realizados.php';
            }
        });
        
        // Cargar datos adicionales
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Reporte de cierre #<?php echo $cierre_id; ?> cargado');
            document.title = `Reporte de Cierre #<?php echo $cierre_id; ?> - SISFACT`;
            
            // Mostrar alerta si hay diferencias
            if (REPORT_DATA.hay_diferencias) {
                console.warn('Se detectaron diferencias en el cierre:', REPORT_DATA.detalle_diferencias);
            }
            
            // Inicializar tooltips
            if (typeof bootstrap !== 'undefined') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        container: 'body',
                        trigger: 'hover focus',
                        placement: 'auto'
                    });
                });
            }
        });
    </script>
</body>
</html>