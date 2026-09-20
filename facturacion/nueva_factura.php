<?php
// nueva_factura.php - Windows 11 Dark Mode
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}



// Inicializar variables
$error = '';
$success = '';

// Verificar si hay una factura recién guardada que necesita información de pago
$factura_guardada = null;
$mostrar_modal_pago = false;


// Si NO hay parámetro en la URL, limpiamos TODO rastro de sesiones de facturas previas
if (!isset($_GET['factura_guardada'])) {
    unset($_SESSION['factura_guardada']);
    unset($_SESSION['mostrar_dialogo_pago']);
} else {
    // Si HAY parámetro, verificamos si coincide con la sesión
    $factura_id_url = intval($_GET['factura_guardada']);
    
    if (isset($_SESSION['factura_guardada']) && $_SESSION['factura_guardada']['id'] == $factura_id_url) {
        $factura_guardada = $_SESSION['factura_guardada'];
        
        // Solo si el estado es PAGADA y tenemos el flag de mostrar diálogo
        if ($factura_guardada['estado'] === 'PAGADA' && isset($_SESSION['mostrar_dialogo_pago'])) {
            $mostrar_modal_pago = true;
            // IMPORTANTE: Limpiamos el flag de sesión AQUÍ para que un "F5" no lo repita
            unset($_SESSION['mostrar_dialogo_pago']);
        } else {
            // Si ya se procesó o no es pagada, limpiamos y redirigimos para limpiar la URL
            unset($_SESSION['factura_guardada']);
            header('Location: ver_factura.php?id=' . $factura_id_url . '&success=1');
            exit();
        }
    }
}

// Limpiar variable de sesión si no se está procesando una factura guardada
if (!isset($_GET['factura_guardada']) && isset($_SESSION['mostrar_dialogo_pago'])) {
    unset($_SESSION['mostrar_dialogo_pago']);
    unset($_SESSION['factura_guardada']);
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

try {
    $db = Database::getConnection();
	
// --- LÓGICA DE FECHAS OPERATIVAS CORREGIDA ---
// 1. Obtener datos maestros de la BD
$mes_cierre_num = obtenerMesCierreOperaciones(); 
$anio_cierre_num = obtenerAnioCierreOperaciones();

// Solo verificamos si estamos en Diciembre
if ($mes_cierre_num == 12) {
    // Verificar si existe el cierre de MES para Diciembre de este año
    $sql_check_dic = "SELECT COUNT(*) as total FROM historico_cierres 
                      WHERE periodo_mes = 12 AND periodo_anio = :anio AND tipo = 1";
    $stmt_check = $db->prepare($sql_check_dic);
    $stmt_check->execute(['anio' => $anio_cierre_num]);
    $es_diciembre_cerrado = $stmt_check->fetch(PDO::FETCH_ASSOC)['total'] > 0;

	if ($es_diciembre_cerrado) {
		$anio_siguiente = $anio_cierre_num + 1;
		
		echo '<!DOCTYPE html>
		<html lang="es">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>SISFACT PDL VISIONES - Periodo Cerrado</title>
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
				.swal2-confirm, .swal2-deny, .swal2-cancel {
					font-weight: 600;
					letter-spacing: 0.5px;
					transition: all 0.3s ease;
				}
				.swal2-confirm:hover, .swal2-deny:hover, .swal2-cancel:hover {
					transform: translateY(-2px);
					box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
				}
				.swal2-icon {
					border-width: 3px;
				}
				.custom-swal-actions {
					gap: 10px;
				}
			</style>
		</head>
		<body style="background: #0f0f1a; margin: 0; min-height: 100vh;">
			<script>
			// Configuración global de tema oscuro
			const darkTheme = Swal.mixin({
				background: "#1e1e2d",
				color: "#e1e1e6",
				confirmButtonColor: "#007bff",
				denyButtonColor: "#6c757d",
				cancelButtonColor: "#28a745",
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
					confirmButton: "custom-swal-confirm",
					denyButton: "custom-swal-deny",
					cancelButton: "custom-swal-cancel"
				}
			});

			darkTheme.fire({
				icon: "error",
				iconColor: "#dc3545",
				title: "<i class=\'fas fa-calendar-times mr-2\'></i> Periodo Cerrado",
				html: `
					<div style="text-align: left; padding: 10px 0;">
						<p style="margin-bottom: 15px; color: #a8a8b3;">
							<i class="fas fa-exclamation-triangle mr-2"></i>
							El periodo contable ha sido finalizado.
						</p>
						<div style="background: #2d2d44; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
							<p style="margin: 5px 0;">
								<strong style="color: #a8a8b3;">Periodo cerrado:</strong>
								<span style="color: #ff6b6b; font-weight: 600; margin-left: 8px;">
									<i class="fas fa-calendar-alt me-1"></i>Diciembre ' . $anio_cierre_num . '
								</span>
							</p>
							<p style="margin: 5px 0; color: #a8a8b3;">
								<i class="fas fa-ban me-1"></i> <strong>No se pueden crear más facturas en este periodo.</strong>
							</p>
							<p style="margin: 10px 0 5px 0;">
								<strong style="color: #a8a8b3;">Acciones disponibles:</strong>
							</p>
							<ul style="color: #a8a8b3; margin-left: 20px; margin-top: 5px;">
								<li><i class="fas fa-tasks me-2"></i>Cierre Anual para abrir nuevo periodo</li>
								<li><i class="fas fa-chart-bar me-2"></i>Consultar reportes del periodo</li>
								<li><i class="fas fa-home me-2"></i>Volver al Dashboard</li>
							</ul>
						</div>
						<p style="margin-top: 15px; font-size: 0.95rem; color: #8a8a9e;">
							<i class="fas fa-info-circle mr-2"></i>
							Realice el cierre anual para abrir un nuevo periodo contable.
						</p>
					</div>`,
				width: "500px",
				padding: "2rem",
				showDenyButton: true,
				denyButtonText: "<i class=\'fas fa-chart-bar mr-2\'></i> Ver Reportes",
				showCancelButton: true,
				cancelButtonText: "<i class=\'fas fa-home mr-2\'></i> Dashboard",
				confirmButtonText: "<i class=\'fas fa-calendar-check mr-2\'></i> Cierre Anual",
				focusConfirm: true,
				reverseButtons: true
			}).then((result) => {
				if (result.isConfirmed) {
					// Redirigir a Cierre Anual
					darkTheme.fire({
						title: "Redirigiendo al Cierre Anual...",
						icon: "info",
						timer: 1000,
						showConfirmButton: false,
						didOpen: () => {
							Swal.showLoading();
						}
					}).then(() => {
						window.location.href = "cierre_anual.php";
					});
				} else if (result.isDenied) {
					// Redirigir a Reportes
					darkTheme.fire({
						title: "Cargando Reportes...",
						icon: "info",
						timer: 1000,
						showConfirmButton: false,
						didOpen: () => {
							Swal.showLoading();
						}
					}).then(() => {
						window.location.href = "reportes.php";
					});
				} else if (result.dismiss === Swal.DismissReason.cancel) {
					// Redirigir al Dashboard
					darkTheme.fire({
						title: "Volviendo al Dashboard...",
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
			</script>
		</body>
		</html>';
		exit();
	}
}

    
    // Obtener usuario actual primero para verificar permisos
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
    
    // Determinar permisos según rol
    $esAdmin = ($usuario['rol_id'] == 1);
    $esVisualizador = ($usuario['rol_id'] == 2);
    $esEditor = ($usuario['rol_id'] == 3);
    $esSuper = ($usuario['rol_id'] == 4);
    $esProgramador = ($usuario['rol_id'] == 5);
    $esSoloLectura = ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4);

    // Verificar si el usuario puede agregar clientes (roles 1, 3, 4, 5)
    $puedeAgregarClientes = ($esAdmin || $esEditor || $esSuper || $esProgramador);
	
// Determinar el tipo de documento (FACTURA u OFERTA)
$tipo_documento = isset($_GET['tipo']) ? $_GET['tipo'] : 'FACTURA';
$titulo_pagina = ($tipo_documento === 'OFERTA') ? 'Nueva Oferta' : 'Nueva Factura';
$icono_pagina = ($tipo_documento === 'OFERTA') ? 'fa-tag' : 'fa-file-invoice';
    
    // VERIFICAR SI EXISTEN CLIENTES EN LA BASE DE DATOS
    $sql_verificar_clientes = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt_verificar = $db->query($sql_verificar_clientes);
    $total_clientes_db = $stmt_verificar->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Si no hay clientes, mostrar SweetAlert y ofrecer agregar cliente si tiene permisos
    if ($total_clientes_db == 0) {
        echo '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>SISFACT PDL VISIONES - Base de Datos Vacía</title>
            <link rel="icon" type="image/x-icon" href="assets/logov.png">
            <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
            <!-- SweetAlert2 con tema oscuro -->
            <script src="js/sweetalert211.js"></script>
            <style>
                body {
                    background: ' . $tema_actual['bg_primary'] . ';
                    margin: 0;
                    min-height: 100vh;
                    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
                }
                
                .permisos-info {
                    background: ' . $tema_actual['bg_tertiary'] . ';
                    padding: 15px;
                    border-radius: 8px;
                    border-left: 4px solid ' . $color_accent . ';
                    margin: 15px 0;
                }
                
                .rol-badge {
                    font-size: 0.8rem;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-weight: 600;
                }
                
                .rol-admin { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
                .rol-editor { background: linear-gradient(135deg, #0078d4, #005a9e); color: white; }
                .rol-supervisor { background: linear-gradient(135deg, #107c10, #0a5c0a); color: white; }
                .rol-programador { background: linear-gradient(135deg, #5c2d91, #4a1c7a); color: white; }
                .rol-otros { background: #6c757d; color: white; }
            </style>
        </head>
        <body>
            <script>
            // Configuración global de SweetAlert2 con tema
            const darkTheme = Swal.mixin({
                background: "' . $tema_actual['bg_secondary'] . '",
                color: "' . $tema_actual['text_primary'] . '",
                confirmButtonColor: "' . $color_accent . '",
                cancelButtonColor: "' . $tema_actual['border_color'] . '",
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showClass: {
                    popup: "swal2-show animate__animated animate__fadeInDown"
                },
                hideClass: {
                    popup: "swal2-hide animate__animated animate__fadeOutUp"
                }
            });

            // Primer alerta: Base de datos vacía
            darkTheme.fire({
                icon: "error",
                iconColor: "#ff6b6b",
                title: "<i class=\'fas fa-database mr-2\'></i> Base de Datos de Clientes Vacía",
                html: `' . addslashes('<div style="text-align: left; padding: 10px 0;">
                        <p style="margin-bottom: 15px; color: ' . $tema_actual['text_secondary'] . ';">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            No se pueden procesar facturas porque no hay clientes registrados en el sistema.
                        </p>
                        <div class="permisos-info">
                            <p style="margin: 5px 0;">
                                <strong style="color: ' . $tema_actual['text_secondary'] . ';">Usuario actual:</strong>
                                <span style="color: ' . $tema_actual['text_primary'] . '; font-weight: 600; margin-left: 8px;">
                                    <i class="fas fa-user me-2"></i>' . htmlspecialchars($usuario['nombre'] ?? 'Usuario') . '
                                </span>
                            </p>
                            <p style="margin: 5px 0;">
                                <strong style="color: ' . $tema_actual['text_secondary'] . ';">Rol:</strong>
                                <span class="rol-badge ' . 
                                    ($esAdmin ? 'rol-admin' : ($esEditor ? 'rol-editor' : ($esSuper ? 'rol-supervisor' : ($esProgramador ? 'rol-programador' : 'rol-otros')))) . 
                                    '" style="margin-left: 8px;">
                                    ' . htmlspecialchars($usuario['rol_nombre'] ?? 'Usuario') . '
                                </span>
                            </p>
                        </div>
                        <p style="margin-top: 15px; font-size: 0.95rem; color: ' . $tema_actual['text_secondary'] . ';">
                            <i class="fas fa-info-circle mr-2"></i>
                            ' . ($puedeAgregarClientes ? 
                                "Puedes agregar nuevos clientes ahora mismo." : 
                                "No tienes permisos para agregar clientes. Contacta al administrador.") . '
                        </p>
                    </div>') . '`,
                width: "550px",
                padding: "2rem",
                showCancelButton: ' . ($puedeAgregarClientes ? 'true' : 'false') . ',
                confirmButtonText: ' . ($puedeAgregarClientes ? 
                    '"<i class=\"fas fa-user-plus mr-2\"></i> Agregar Cliente Ahora"' : 
                    '"<i class=\"fas fa-home mr-2\"></i> Entendido"') . ',
                cancelButtonText: "<i class=\"fas fa-times mr-2\"></i> Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    if (' . ($puedeAgregarClientes ? 'true' : 'false') . ') {
                        // Si tiene permisos, redirigir a nuevo cliente
                        darkTheme.fire({
                            title: "Redirigiendo...",
                            text: "Serás redirigido al formulario de nuevo cliente",
                            icon: "info",
                            timer: 1500,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        }).then(() => {
                            window.location.href = "nuevo_cliente.php?return_to=nueva_factura.php";
                        });
                    } else {
                        // Si no tiene permisos, mostrar mensaje
                        darkTheme.fire({
                            title: "Permisos Insuficientes",
                            html: `' . addslashes('<div style="text-align: left; padding: 10px 0;">
                                    <p style="margin-bottom: 15px; color: ' . $tema_actual['text_secondary'] . ';">
                                        <i class="fas fa-user-lock mr-2"></i>
                                        Tu rol actual no te permite agregar nuevos clientes.
                                    </p>
                                    <div class="permisos-info">
                                        <p style="margin: 5px 0;">
                                            <strong style="color: ' . $tema_actual['text_secondary'] . ';">Roles con permiso:</strong>
                                            <span style="color: #4cd964; font-weight: 600; margin-left: 8px;">
                                                <i class="fas fa-user-shield me-2"></i>Administrador, Editor, Supervisor o Programador
                                            </span>
                                        </p>
                                        <p style="margin: 5px 0;">
                                            <strong style="color: ' . $tema_actual['text_secondary'] . ';">Tu rol:</strong>
                                            <span class="rol-badge ' . 
                                                ($esAdmin ? 'rol-admin' : ($esEditor ? 'rol-editor' : ($esSuper ? 'rol-supervisor' : ($esProgramador ? 'rol-programador' : 'rol-otros')))) . 
                                                '" style="margin-left: 8px;">
                                                ' . htmlspecialchars($usuario['rol_nombre'] ?? 'Usuario') . '
                                            </span>
                                        </p>
                                    </div>
                                    <p style="margin-top: 15px; font-size: 0.95rem; color: ' . $tema_actual['text_secondary'] . ';">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Contacta con el administrador del sistema para solicitar permisos o para que agregue clientes.
                                    </p>
                                </div>') . '`,
                            confirmButtonText: "<i class=\"fas fa-redo mr-2\"></i> Reintentar",
                            confirmButtonColor: "' . $color_accent . '",
                            width: "550px"
                        }).then(() => {
                            location.reload();
                        });
                    }
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Si cancela, preguntar si quiere salir
                    darkTheme.fire({
                        title: "¿Deseas salir?",
                        text: "Puedes regresar más tarde cuando haya clientes registrados",
                        icon: "question",
                        showCancelButton: true,
                        confirmButtonText: "<i class=\"fas fa-sign-out-alt mr-2\"></i> Salir",
                        cancelButtonText: "<i class=\"fas fa-stay mr-2\"></i> Permanecer aquí",
                        confirmButtonColor: "#dc3545",
                        cancelButtonColor: "' . $color_accent . '"
                    }).then((result2) => {
                        if (result2.isConfirmed) {
                            window.location.href = "dashboard.php";
                        } else {
                            // Si decide permanecer, mostrar mensaje informativo
                            darkTheme.fire({
                                title: "Esperando clientes",
                                text: "La página se mantendrá abierta. Puedes recargar manualmente cuando haya clientes registrados.",
                                icon: "info",
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }
                    });
                }
            });
            </script>
        </body>
        </html>';
       exit();
    }
    
    // Verificar permisos para crear facturas
    if (!$esAdmin && !$esEditor && !$esSuper) {
        $rol_actual = '';
        switch($usuario['rol_id']) {
            case 2: $rol_actual = 'Visualizador'; break;
            case 3: $rol_actual = 'Editor/Facturador'; break;
            case 1: $rol_actual = 'Administrador'; break;
            case 4: $rol_actual = 'Supervisor'; break;
            case 5: $rol_actual = 'Programador'; break;
            default: $rol_actual = 'Usuario';
        }
        
        // Mensaje de SweetAlert en PHP que se ejecutará en el cliente
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
        </head>
        <body style="background: #0f0f1a; margin: 0; min-height: 100vh;">
            <script>
            // Configuración global de tema oscuro
            const darkTheme = Swal.mixin({
                background: "#1e1e2d",
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
                                    <i class="fas fa-user-tag me-2"></i>' . $rol_actual . '
                                </span>
                            </p>
                            <p style="margin: 5px 0;">
                                <strong style="color: #a8a8b3;">Roles permitidos:</strong>
                                <span style="color: #4cd964; font-weight: 600; margin-left: 8px;">
                                    <i class="fas fa-user-shield me-2"></i>Administrador, Editor o Supervisor
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
    }
    
    // Obtener estadísticas para los badges del sidebar
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
    
    // Obtener cliente_id de la URL si existe
    $cliente_id_url = $_GET['cliente_id'] ?? 0;
    
    // Obtener clientes activos
    $sql_clientes = "SELECT id, codigo, nombre, direccion, CodReup, NIT, 
                            ContratoNo, ResponsableEntidad, SucursalCobroLocalidad, NoCtaDeudor, 
                            telefono, email, fechaRegistro, fechaVence, 
                            activo, renovac, vigenciapor, fechafinalcontrato, si_renova_cant
                     FROM clasif_clientes 
                     ORDER BY nombre";
    $stmt_clientes = $db->query($sql_clientes);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    
    $cliente_seleccionado = null;
    
    // Si hay un cliente_id en la URL, buscar ese cliente
    if ($cliente_id_url > 0) {
        foreach ($clientes as $cliente) {
            if ($cliente['id'] == $cliente_id_url) {
                $cliente_seleccionado = $cliente;
                break;
            }
        }
    }
    
    // Obtener categorías de servicios
    $sql_categorias = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv WHERE activo = 1 ORDER BY descripcion";
    $stmt_categorias = $db->query($sql_categorias);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todos los servicios activos 
        $sql_servicios = "SELECT s.id, s.codigo, s.descripcion, s.costo, s.categoria_id, s.activo, 
                      c.descripcion as categoria, c.codigo as categoria_codigo
                      FROM clasif_serv s 
                      LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id 
                      ORDER BY s.descripcion";
					
    $stmt_servicios = $db->query($sql_servicios);
    $servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener tipos de pago
    $sql_tipos_pago = "SELECT * FROM tipos_pago ORDER BY descripcion";
    $stmt_tipos_pago = $db->query($sql_tipos_pago);
    $tipos_pago = $stmt_tipos_pago->fetchAll(PDO::FETCH_ASSOC);




$fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d'); 



// 3. CALCULAR EL ÚLTIMO DÍA REAL DEL MES OPERATIVO
// cal_days_in_month obtiene si el mes tiene 28, 29, 30 o 31 días (considera bisiestos)
$ultimo_dia_mes_operativo = cal_days_in_month(CAL_GREGORIAN, $mes_cierre_num, $anio_cierre_num);
$ultimo_dia_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $ultimo_dia_mes_operativo);
// 2. Definir el día actual (de la PC/Servidor)
$dia_actual_pc = (int)date('j'); 
// 4. AJUSTE DE FECHA (CLIPPING)
// Si hoy es 31, pero el mes operativo trae 30, usamos 30.
// Si hoy es 15 y el mes trae 30, usamos 15.
$dia_final = min($dia_actual_pc, $ultimo_dia_mes_operativo);

// 5. Construir la fecha "Hoy Operativo" Segura
$hoy_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_final);
$primer_dia_operativo = sprintf("%04d-%02d-01", $anio_cierre_num, $mes_cierre_num);

// 6. Variables auxiliares para visualización
$timestamp_combinado = strtotime($hoy_operativo);
$dia_semana = date('N', $timestamp_combinado);


// 5. Obtener nombre del mes OPERATIVO (no el actual del calendario real)
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
// Ajustamos el índice (-1 porque el array empieza en 0)
$mes_actual_es = $meses_completos[$mes_cierre_num - 1]; 


// --- INICIO GENERACIÓN NÚMERO DE FACTURA (SECUENCIAL ANUAL) ---

// 1. Usamos el año OPERATIVO de la base de datos
$anio_operativo = $anio_cierre_num;

// 2. Definir el prefijo base para el tipo de documento
$tipo_prefijo = ($tipo_documento === 'OFERTA') ? 'OFV-' : 'FV-';

// 3. Buscar la última factura de TODO el AÑO (no por mes)
$sql_ultima_factura = "SELECT no_fact FROM tbl_fact 
                       WHERE no_fact LIKE :prefijo_anio 
                       ORDER BY CAST(SUBSTRING(no_fact, -4) AS UNSIGNED) DESC 
                       LIMIT 1";

$stmt_ultima_factura = $db->prepare($sql_ultima_factura);
$stmt_ultima_factura->execute(['prefijo_anio' => $tipo_prefijo . $anio_operativo . '%']);
$ultima_factura = $stmt_ultima_factura->fetch(PDO::FETCH_ASSOC);

if ($ultima_factura) {
    // Extraer el número secuencial (últimos 4 dígitos)
    $ultimo_numero = intval(substr($ultima_factura['no_fact'], -4));
    $nuevo_numero = $ultimo_numero + 1;
    
    // Verificar límite (máximo 9999 facturas por año)
    if ($nuevo_numero > 9999) {
        throw new Exception("Límite de facturas anuales alcanzado (máximo 9999)");
    }
} else {
    // Primera factura del año operativo
    $nuevo_numero = 1;
}

// 4. Formatear el número secuencial con 4 dígitos
$numero_secuencial = str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);

// 5. Construir el número de factura final
$fecha_para_formato = date('Ymd', $timestamp_combinado);
$numero_factura = $tipo_prefijo . $fecha_para_formato . $numero_secuencial;

// Si es una oferta, mantener la lógica original
if ($tipo_documento === 'OFERTA') {
    $numero_factura = 'OFV-' . $fecha_para_formato . date('his');
}

// --- FIN GENERACIÓN NÚMERO DE FACTURA (SECUENCIAL ANUAL) ---

// Obtener estadísticas para el histórico
try {
    $sql_total_historico = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt = $db->query($sql_total_historico);
    $estadisticas_historico = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_historico = $estadisticas_historico['total'] ?? 0;
} catch (Exception $e) {
    error_log("Error al contar histórico: " . $e->getMessage());
    $total_historico = 0;
}

} catch (Exception $e) {
    error_log("Error al cargar datos: " . $e->getMessage());
    $error = "Error al cargar los datos necesarios";
}

// Ya no procesamos el formulario aquí, se hace via AJAX
// La validación y guardado se manejará en JavaScript

// Si hubo error al cargar datos iniciales, generar número temporal
if (!isset($numero_factura)) {
    $ts_fallback = isset($timestamp_combinado) ? $timestamp_combinado : time();
    $fecha_actual_fallback = date('Ymd', $ts_fallback);
    $numero_factura = 'FV-' . $fecha_actual_fallback . '0001';
}

if ($tipo_documento === 'OFERTA') {
    $numero_factura = 'OFV-' . $fecha_prefijo_completa . date('his');
}

?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
	
<!-- MDTimePicker para el reloj analógico -->
<link rel="stylesheet" href="css/mdtimepicker.css">
<script src="js/mdtimepicker.min.js"></script>

<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    
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
            border-bottom: 1px solid var(--win-border-color);
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
        }

        /* Estados */
        .estado-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--win-radius-sm);
            font-weight: 500;
            display: inline-block;
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

        .estado-pagada {
            background: rgba(0, 120, 212, 0.15);
            color: #0078d4;
            border: 1px solid rgba(0, 120, 212, 0.3);
        }

        .estado-anulada {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
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
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
        }

        [data-theme="dark"] .btn-close:hover {
            opacity: 1;
        }

        /* Ajustes específicos para modales en tema oscuro */
        [data-theme="dark"] .modal-header .btn-close {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e");
        }

        /* Ajustes para botones de cerrar en alertas */
        [data-theme="dark"] .alert .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-theme="dark"] .alert-danger .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) sepia(100%) saturate(500%) hue-rotate(300deg);
        }

        /* Ajustes para el borde de separación */
        [data-theme="dark"] .border-bottom {
            border-color: var(--win-border-color) !important;
        }

        /* Ajustes para los placeholders */
        [data-theme="dark"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7;
        }

        [data-theme="dark"] .form-control::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7;
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

        /* Quick Actions - Simple */
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
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
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
                padding: 16px;
            }
        }

        @media (max-width: 768px) {
            .win-nav-search {
                display: none;
            }
            
            .win-quick-action {
                bottom: 16px;
                right: 16px;
                width: 50px;
                height: 50px;
                font-size: 18px;
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
        
        /* Dropdown estilos */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border_color);
            border-radius: var(--win-radius);
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
        
        /* Controles de cantidad */
        .cantidad-control {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cantidad-input {
            width: 70px;
            text-align: center;
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 0.375rem 0.5rem;
        }

        .cantidad-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-secondary);
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .cantidad-btn:hover {
            background: var(--win-bg-tertiary);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }

        .cantidad-btn i {
            font-size: 12px;
        }

        /* Modal */
        .modal-content {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
        }

        .modal-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border_color);
            color: var(--win-text-primary);
        }

        .modal-body {
            color: var(--win-text-primary);
        }

        .modal-footer {
            background: var(--win-bg-tertiary);
            border-top: 1px solid var(--win-border_color);
        }

        /* Totales */
        .totales-container {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius);
            padding: 1.5rem;
            border: 1px solid var(--win-border-color);
        }

        .total-label {
            color: var(--win-text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .total-value {
            color: var(--win-text-primary);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .total-general {
            color: var(--win-accent) !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
        }

        /* Estilos para la información del cliente */
        .cliente-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid var(--win-border-color);
        }

        .cliente-info-label {
            font-size: 12px;
            color: var(--win-text-secondary);
            font-weight: 500;
            flex: 1;
            opacity: 0.8;
        }

        .cliente-info-value {
            font-size: 12px;
            color: var(--win-text-primary);
            font-weight: 400;
            text-align: right;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #estadoContrato {
            font-size: 11px;
            padding: 2px 6px;
        }
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
[data-theme="dark"] .alert-dismissible .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Forzar que el body sea scrolleable si no hay modales visibles */
body:not(.modal-open) {
    overflow: auto !important;
    padding-right: 0 !important;
}

/* Evitar que se acumulen backdrops si se abren varios modales */
.modal-backdrop + .modal-backdrop {
    display: none !important;
}

/* Estilos para inputs dinámicos en el modal de pago */
.win-input-dynamic {
    background-color: var(--win-bg-tertiary) !important;
    border: 1px solid var(--win-border-color) !important;
    color: var(--win-text-primary) !important;
}

/* Invertir color de iconos (calendario/reloj) solo en modo oscuro */
[data-theme="dark"] input::-webkit-calendar-picker-indicator {
    filter: invert(1);
    cursor: pointer;
}

.border-win { 
    border: 1px solid var(--win-border-color) !important; 
}

/* ESTILOS ESPECÍFICOS PARA LA SELECCIÓN POR CLIC */
#serviciosModalBody tr {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

/* Fila seleccionada - CONTRASTE MÁXIMO */
#serviciosModalBody tr.selected-service-row {
    background-color: var(--win-accent) !important;
    color: white !important;
    border-left: 4px solid white !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
    transform: translateX(4px);
}

/* Asegurar que TODO el texto sea blanco en la fila seleccionada */
#serviciosModalBody tr.selected-service-row td {
    color: white !important;
    font-weight: 600 !important;
}

/* Radio button visible en la selección */
#serviciosModalBody tr.selected-service-row input[type="radio"] {
    accent-color: white !important;
    border: 2px solid white !important;
    width: 18px !important;
    height: 18px !important;
}

/* Badge/etiqueta visible en la selección */
#serviciosModalBody tr.selected-service-row .badge {
    background-color: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    border: 1px solid white !important;
}

/* Efecto hover (antes de seleccionar) */
#serviciosModalBody tr:hover:not(.selected-service-row) {
    background-color: var(--win-accent-light) !important;
    transform: translateX(2px);
}

/* Estilos para el modal de selección múltiple */
#serviciosModalBody tr.selected-row {
    background-color: var(--win-accent-light) !important;
    border-left: 3px solid var(--win-accent) !important;
}

/* Checkbox en el header */
#checkAll {
    accent-color: var(--win-accent);
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Contador de seleccionados */
#contadorSeleccionados {
    font-size: 12px;
    font-weight: 500;
}

#contadorSeleccionados i {
    color: var(--win-accent);
}

/* Botones de selección */
#btnSeleccionarTodos, #btnDeseleccionarTodos {
    font-size: 12px;
    padding: 4px 8px;
}

/* Hover para filas con checkbox */
#serviciosModalBody tr:hover:not(:has(td[colspan])) {
    background-color: rgba(var(--win-accent-rgb), 0.1) !important;
}

/* Fila seleccionada visualmente */
#serviciosModalBody tr[style*="background-color: var(--win-accent-light)"] {
    transition: all 0.3s ease;
}

/* Forzar colores de la tabla según el tema actual */
#tablaServiciosModal {
    --bs-table-bg: transparent;
    --bs-table-color: var(--win-text-primary);
    --bs-table-hover-bg: var(--win-accent-light);
    --bs-table-hover-color: var(--win-text-primary);
    border-color: var(--win-border-color);
}

#serviciosModalBody tr {
    cursor: pointer;
    border-bottom: 1px solid var(--win-border-color);
}

#serviciosModalBody td {
    padding-top: 8px;
    padding-bottom: 8px;
    color: var(--win-text-primary); /* Esto asegura texto blanco en dark y negro en light */
}

/* Radio button personalizado */
#serviciosModalBody input[type="radio"] {
    accent-color: var(--win-accent);
    transform: scale(1.1);
}

/* Scrollbar minimalista para la tabla */
.table-responsive::-webkit-scrollbar {
    width: 6px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: var(--win-border-color);
    border-radius: 10px;
}

/* Quitar bordes azules de Bootstrap al hacer foco */
#servicioModal .form-control:focus, 
#servicioModal .form-select:focus {
    background-color: var(--win-bg-tertiary);
    color: var(--win-text-primary);
    border-color: var(--win-accent) !important;
    box-shadow: 0 0 0 2px var(--win-accent-light);
}

/* Fondo alternado para mejor legibilidad */
#serviciosModalBody tr.even-row {
    background-color: var(--win-bg-secondary);
}

#serviciosModalBody tr.odd-row {
    background-color: var(--win-bg-tertiary);
}

/* Efecto hover más pronunciado */
#serviciosModalBody tr:hover:not(.table-primary) {
    background-color: color-mix(in srgb, var(--win-accent) 15%, transparent) !important;
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Badge mejorado para el código */
#serviciosModalBody .badge {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 600;
    border: 1px solid;
}
/* Estilos específicos para el Card de Cliente Seleccionado */
.win-info-box {
    background-color: var(--win-bg-tertiary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius-sm);
    padding: 0.75rem;
}

.win-badge-outline {
    background-color: transparent;
    border: 1px solid var(--win-border-color);
    color: var(--win-text-secondary);
    font-weight: 500;
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 4px;
}

.win-label {
    font-size: 0.75rem;
    color: var(--win-text-secondary);
    margin-bottom: 2px;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.win-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--win-text-primary);
    display: block;
    line-height: 1.2;
}

.win-value-primary {
    color: var(--win-accent);
}

.win-icon-muted {
    color: var(--win-text-secondary);
    opacity: 0.7;
    width: 16px;
    text-align: center;
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

    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <!-- Botón hamburguesa para móvil -->
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">NUEVA <?php echo $tipo_documento ?>- SISFACT PDL Visiones</span>
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
                            Dashboard<span class="win-nav-badge" 
              title="Fecha de Cierre Actual: <?php echo fechaInicioFormateada(13); ?>" 
              style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
            F/Cierre: <?php echo fechaInicioFormateada(9); ?>
        </span>
                </a>
<li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            </li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="nueva_factura.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-plus-circle"></i>
                    <span class="win-nav-text">Nueva <?php echo $tipo_documento ?></span>
<?php if ($esAdmin || $esSuper): ?>
                    <span class="win-nav-badge admin-badge" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-crown"></i>
                    </span>
                    <?php endif; ?>
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
                    <span class="win-nav-badge"><?php echo $total_historico; ?></span>
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
                    <small class="text-muted d-block fw-bold">Plan <?php echo fechaInicioFormateada(9); ?> (CUP)</small>
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
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 mb-0" style="color: var(--win-text-primary);">
                     <i class="fas <?php echo $icono_pagina; ?> me-2" style="color: var(--win-accent);"></i><?php echo $titulo_pagina; ?>
                </h1>
                <p class="text-light mb-0">Complete los datos para crear una nueva <span class="fw-bold text-warning"><?php echo strtoupper($tipo_documento); ?></span> - Fecha Operaciones: <strong><span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span></strong></p>
					<p class="small text-muted mb-0">
						<i class="fas fa-clock me-1"></i>Última factura Introducida en el Sistema: 
						<span class="text-info fw-bold"><?php echo $lastInvoiceInfo['numero']; ?> - 
						<?php echo $lastInvoiceInfo['fecha'] . ' - $ '. number_format($lastInvoiceInfo['importe'], 2);  ?></span>
					</p>
            </div>
			
			<div class="btn-toolbar mb-2 mb-md-0">
				<a href="facturas.php" class="btn btn-sm btn-outline-secondary">
					<i class="fas fa-arrow-left me-1"></i>Volver
				</a>
<!-- Dropdown para NUEVO DOCUMENTO (FACTURA/OFERTA) -->
<div class="btn-group">
    <button type="button" 
            class="btn btn-primary btn-sm d-flex align-items-center gap-2 px-3 py-2 fw-medium win-button-primary dropdown-toggle" 
            data-bs-toggle="dropdown" 
            aria-expanded="false"
            data-bs-placement="bottom"
            title="Crear nuevo documento">
        <div class="win-button-icon">
            <i class="fas fa-plus-circle"></i>
        </div>
        <span class="win-button-text">Nuevo Documento</span>
        <span class="win-button-badge text-light">
            <i class="fas fa-rocket"></i>
        </span>
    </button>
    
    <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px; background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
        <!-- Opción FACTURA -->
        <li>
            <a class="dropdown-item d-flex align-items-center py-2" 
               href="nueva_factura.php?tipo=FACTURA"
               onclick="return confirmarCambioTipoDropdown('FACTURA')">
                <div class="d-flex align-items-center gap-3" style="width: 100%;">
                    <div class="win-icon-circle" style="background: rgba(0, 120, 212, 0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-file-invoice" style="color: #0078d4; font-size: 1rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <span class="d-block fw-semibold" style="color: var(--win-text-primary);">FACTURA</span>
                        <small class="text-muted d-block" style="font-size: 11px;">Documento fiscal para cobro</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary" style="font-size: 10px;">
                        <i class="fas fa-file-invoice me-1"></i>NUEVA
                    </span>
                </div>
            </a>
        </li>
        
        <!-- Divisor -->
        <li><hr class="dropdown-divider my-1" style="border-color: var(--win-border-color);"></li>
        
        <!-- Opción OFERTA -->
        <li>
            <a class="dropdown-item d-flex align-items-center py-2" 
               href="nueva_factura.php?tipo=OFERTA"
               onclick="return confirmarCambioTipoDropdown('OFERTA')">
                <div class="d-flex align-items-center gap-3" style="width: 100%;">
                    <div class="win-icon-circle" style="background: rgba(40, 167, 69, 0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-tag" style="color: #28a745; font-size: 1rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <span class="d-block fw-semibold" style="color: var(--win-text-primary);">OFERTA</span>
                        <small class="text-muted d-block" style="font-size: 11px;">Propuesta comercial sin valor fiscal</small>
                    </div>
                    <span class="badge bg-success-subtle text-success" style="font-size: 10px;">
                        <i class="fas fa-tag me-1"></i>NUEVA
                    </span>
                </div>
            </a>
        </li>
        
        <!-- Footer con info del tipo actual -->
        <li><hr class="dropdown-divider my-1" style="border-color: var(--win-border-color);"></li>
        <li class="px-3 py-2">
            <small class="text-muted d-block" style="font-size: 11px;">
                <i class="fas fa-info-circle me-1"></i>
                Documento actual: <span class="fw-bold text-<?php echo ($tipo_documento === 'FACTURA') ? 'primary' : 'success'; ?>">
                    <?php echo $tipo_documento; ?>
                </span>
            </small>
            <small class="text-muted d-block" style="font-size: 12px; margin-top: 2px;">
						<i class="fas fa-clock me-1"></i>Última factura Introducida en el Sistema: 
						<br><span class="text-info fw-bold"><?php echo $lastInvoiceInfo['numero']; ?> - 
						<?php echo $lastInvoiceInfo['fecha']; ?> - $ <?php echo number_format($lastInvoiceInfo['importe'], 2); ?></span></span>
            </small>
        </li>
    </ul>
</div>

<!-- Estilos adicionales para el dropdown -->
<style>
    .win-button-primary {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        background: linear-gradient(135deg, var(--win-accent) 0%, color-mix(in srgb, var(--win-accent) 80%, black) 100%);
    }
    
    .win-button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 120, 212, 0.3);
    }
    
    .dropdown-item {
        transition: all 0.2s ease;
        border-radius: 6px;
        margin: 2px 4px;
        color: var(--win-text-primary);
    }
    
    .dropdown-item:hover {
        background: var(--win-accent-light);
        transform: translateX(4px);
    }
    
    .dropdown-item:hover .win-icon-circle {
        transform: scale(1.05);
    }
    
    .win-icon-circle {
        transition: all 0.2s ease;
    }
    
    /* Badges de tipo */
    .bg-primary-subtle {
        background: rgba(0, 120, 212, 0.15) !important;
        color: #0078d4 !important;
    }
    
    .bg-success-subtle {
        background: rgba(40, 167, 69, 0.15) !important;
        color: #28a745 !important;
    }
    
    /* Animación del dropdown */
    .dropdown-menu {
        animation: slideIn 0.2s ease;
    }
    
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
</style>
			</div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="facturaForm" class="animate__animated animate__fadeIn">
            <div class="row mb-4">
                <div class="col-md-8">
                    <!-- Información básica -->
<div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                                <i class="fas fa-info-circle me-2" style="color: var(--win-accent);"></i>Información de la Factura
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- FILA 1: Número y Fecha -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Número de Factura *</label>
                                    <input type="text" class="form-control" name="no_fact" required
                                           value="<?php echo $numero_factura; ?>" readonly>
                                    <?php if ($tipo_documento == 'FACTURA'): ?>
										<small class="text-info">Número generado automáticamente (Formato: FV-AAAAMMDD####)</small>
									<?php else: ?>
										<small class="text-info">Número generado automáticamente (Formato: FV-AAAAMMDDhms)</small>
									<?php endif; ?>
                                </div>
<div class="col-md-6 mb-3">
    <label class="form-label">Fecha de Emisión *</label>
    <div class="row g-2">
        <div class="col">
            <input type="date" 
                   class="form-control" 
                   name="fecha_emision" 
                   required 
                   value="<?php echo $hoy_operativo; ?>" 
                   id="fechaEmision"
                   min="<?php echo $primer_dia_operativo; ?>"
                   max="<?php echo $ultimo_dia_operativo; ?>"> <!-- 👈 AHORA ES EL ÚLTIMO DÍA -->
            <small class="text-info">
                Rango: <?php echo date('d/m/Y', strtotime($primer_dia_operativo)); ?> - 
                       <?php echo date('d/m/Y', strtotime($ultimo_dia_operativo)); ?>
            </small>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-outline-secondary" 
                    onclick="setFechaHoy('fechaEmision')" title="Hoy">
                <i class="fas fa-calendar-day"></i> Hoy
            </button>
        </div>
    </div>
</div>
                            </div>
                            
                            <!-- FILA 2: Cliente (Ancho completo) -->
<div class="row">
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0">Cliente *</label>
            
            <!-- Input Group: Buscador + Botón Limpiar -->
            <div class="input-group input-group-sm" style="width: 200px;">
                <input type="text" 
                       id="inputFiltroCliente"
                       class="form-control border-win" 
                       style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border-right: none;"
                       placeholder="🔍 filtrar..." 
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

        <select class="form-select" name="cliente_id" id="clienteSelect" onchange="actualizarInfoCliente()">
            <option value="">Seleccione un cliente</option>
            <?php foreach ($clientes as $cliente): 
                $selected = ($cliente_seleccionado && $cliente['id'] == $cliente_seleccionado['id']) ? 'selected' : '';
                $contratoNo = !empty($cliente['ContratoNo']) ? trim($cliente['ContratoNo']) : 'S/C';
                $nombreCliente = trim($cliente['nombre']);
                $displayText = $contratoNo . ' - ' . $nombreCliente;
                
                // Atributos data...
                $fRegistro = !empty($cliente['fechaRegistro']) && $cliente['fechaRegistro'] != '0000-00-00' ? date('d/m/Y', strtotime($cliente['fechaRegistro'])) : '';
                $fVence = !empty($cliente['fechaVence']) && $cliente['fechaVence'] != '0000-00-00' ? date('d/m/Y', strtotime($cliente['fechaVence'])) : '';
                $fFinal = !empty($cliente['fechafinalcontrato']) && $cliente['fechafinalcontrato'] != '0000-00-00' ? date('d/m/Y', strtotime($cliente['fechafinalcontrato'])) : '';
                $fFinalIso = !empty($cliente['fechafinalcontrato']) && $cliente['fechafinalcontrato'] != '0000-00-00' ? $cliente['fechafinalcontrato'] : '';
            ?>
                <option value="<?php echo $cliente['id']; ?>" 
                        data-codigo="<?php echo htmlspecialchars($cliente['codigo'] ?? ''); ?>"
                        data-nombre="<?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>"
                        data-direccion="<?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?>"
                        data-codreup="<?php echo htmlspecialchars($cliente['CodReup'] ?? ''); ?>"
                        data-nit="<?php echo htmlspecialchars($cliente['NIT'] ?? ''); ?>"
                        data-contratono="<?php echo htmlspecialchars($contratoNo); ?>"
                        data-responsable="<?php echo htmlspecialchars($cliente['ResponsableEntidad'] ?? ''); ?>"
                        data-sucursal="<?php echo htmlspecialchars($cliente['SucursalCobroLocalidad'] ?? ''); ?>"
                        data-noctadeudor="<?php echo htmlspecialchars($cliente['NoCtaDeudor'] ?? ''); ?>"
                        data-telefono="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>"
                        data-email="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                        data-fecharegistro="<?php echo $fRegistro; ?>"
                        data-fechavence="<?php echo $fVence; ?>"
                        data-fechafinal="<?php echo $fFinal; ?>"
                        data-fechafinal-iso="<?php echo $fFinalIso; ?>"
                        data-vigencia="<?php echo htmlspecialchars($cliente['vigenciapor'] ?? ''); ?>"
                        data-renovac="<?php echo $cliente['renovac'] ?? 0; ?>"
                        data-sirenova="<?php echo $cliente['si_renova_cant'] ?? ''; ?>"
                        data-activo="<?php echo $cliente['activo'] ?? 0; ?>"
                        <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($displayText); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
							
							<!-- FILA 3: Tipo de Pago y Estado -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Pago *</label>
                                    <select class="form-select" name="tipo_pago_id" required id="tipoPagoSelect">
                                        <option value="">Seleccione tipo de pago</option>
                                        <?php 
                                        $emoji_map = [
                                            'ABONO' => '💰', 'ANTICIPO' => '⏳', 'BILLETERA MÓVIL' => '📱',
                                            'CHEQUE CERTIFICADO' => '📄', 'CRÉDITO COMERCIAL' => '💳', 'DEPÓSITO BANCARIO' => '🏦',
                                            'EFECTIVO' => '💵', 'LETRA DE CAMBIO' => '📜', 'OTROS' => '❓',
                                            'PAGO CONTRA ENTREGA' => '🤝', 'PAGO DIGITAL' => '📱', 'PAGO EN LÍNEA' => '🌐',
                                            'PAGO MÓVIL' => '📱', 'PAGO PARCIAL' => '🔢', 'PAYPAL' => '🔵',
                                            'TARJETA DE CRÉDITO' => '💳', 'TARJETA DE DÉBITO' => '💳', 'TARJETA INTERNACIONAL' => '🌍',
                                            'TRANSFERENCIA ACH' => '🏦', 'TRANSFERENCIA BANCARIA' => '🏦', 'VARIOS MÉTODOS' => '🔄'
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
                                            $selected = ($tipo['id'] == 20) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $tipo['id']; ?>" <?php echo $selected; ?>>
                                                <?php echo $emoji . ' ' . htmlspecialchars($tipo['descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-info mt-1">
                                        <i class="fas fa-money-bill-wave me-1"></i> 
                                        <span id="tipoPagoInfo">TRANSFERENCIA BANCARIA por defecto</span>
                                    </small>
                                </div>

								<div class="col-md-6 mb-3">
									<label class="form-label">Estado</label>
									<select class="form-select" name="estado" id="estadoSelect" <?php echo ($tipo_documento === 'OFERTA') ? 'disabled' : ''; ?>>
										<option value="PENDIENTE" class="estado-pendiente" <?php echo ($tipo_documento === 'OFERTA') ? 'selected' : ''; ?>>⏳ Pendiente</option>
										<option value="CONTABILIZADA" class="estado-contabilizada">📌 Contabilizada</option>
										<option value="PAGADA" class="estado-pagada">💵 Pagada</option>
									</select>
									<?php if ($tipo_documento === 'OFERTA'): ?>
										<input type="hidden" name="estado" value="PENDIENTE">
										<small class="text-info mt-1">
											<i class="fas fa-info-circle me-1"></i>Las ofertas solo pueden tener estado "Pendiente"
										</small>
									<?php endif; ?>
								</div>
                            </div>
<div class="row mt-2">
    <div class="col-12 mb-3">
        <label class="form-label">
            <i class="fas fa-comment me-1" style="color: var(--win-accent);"></i>Observaciones
        </label>
        <textarea class="form-control" 
                  name="observaciones" 
                  id="observacionesFactura" 
                  rows="3" 
                  placeholder="Ingrese observaciones, notas o comentarios adicionales para esta factura... (Opcional)"
                  style="resize: vertical; min-height: 80px; max-height: 200px; background-color: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); color: var(--win-text-primary);"></textarea>
        <small class="text-info mt-1 d-block">
            <i class="fas fa-info-circle me-1"></i>Campo opcional - Puede agregar notas internas o información adicional para el cliente.
        </small>
    </div>
</div>
                        </div>
                    </div>
					<!--------->
                </div>
                
<div class="col-md-4">
    <!-- Información del cliente - Versión Detallada y Adaptable -->
    <div class="card mb-4" style="border-color: var(--win-border-color);">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary); font-size: 0.95rem;">
                <i class="fas fa-user-circle me-2" style="color: var(--win-accent);"></i>Cliente Actual
            </h6>
            <!-- Badge de Estado del Contrato -->
            <span id="estadoContrato" class="badge bg-secondary" style="font-size: 0.7rem; font-weight: 500;">--</span>
        </div>
        
        <div class="card-body">
            <!-- 1. Encabezado: Nombre y Códigos -->
            <div class="text-center mb-3">
                <h5 id="clienteNombre" class="mb-2 fw-bold" style="color: var(--win-text-primary);">Seleccione cliente</h5>
                
                <div class="d-flex justify-content-center gap-2 mb-2">
                    <span class="win-badge-outline" id="clienteCodigo">COD: --</span>
                    <span class="win-badge-outline" id="clienteNit">NIT: --</span>
                </div>
                
                <span id="clienteActivo" class="badge bg-secondary" style="font-size: 0.7rem;">--</span>
            </div>
            
            <!-- 2. Caja Principal: Contrato y Responsable -->
            <div class="win-info-box mb-3">
                <div class="row g-2">
                    <div class="col-12 border-bottom" style="border-color: var(--win-border-color) !important; padding-bottom: 8px; margin-bottom: 8px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="win-label mb-0"><i class="fas fa-file-contract win-icon-muted me-1"></i>Contrato</span>
                            <span id="clienteContratoNo" class="win-value text-end">--</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <span class="win-label"><i class="fas fa-user-tie win-icon-muted me-1"></i>Responsable</span>
                        <span id="clienteResponsable" class="win-value text-truncate">--</span>
                    </div>
                </div>
            </div>

            <!-- 3. Grid de Detalles -->
            <div class="row g-3 px-1">
                <!-- Dirección -->
                <div class="col-12">
                    <span class="win-label"><i class="fas fa-map-marker-alt win-icon-muted me-1"></i>Dirección</span>
                        <span id="clienteDireccion" class="win-value text-truncate d-block" style="font-weight: 400; max-width: 100%;">
							--
						</span>
                </div>

                <!-- Fechas (Lado a Lado) -->
                <div class="col-6">
                    <span class="win-label">F. Inicio</span>
                    <span id="clienteFechaRegistro" class="win-value">--</span>
                </div>
                <div class="col-6">
                    <span class="win-label" style="color: var(--win-accent);">F. Final Real</span>
                    <span id="clienteFechaFinal" class="win-value win-value-primary">--</span>
                </div>

                <!-- Vigencia y Suplemento -->
                <div class="col-6">
                    <span class="win-label">Vigencia</span>
                    <span id="clienteVigencia" class="win-value" style="font-weight: 400;">--</span>
                </div>
                <div class="col-6">
                    <span class="win-label">Suplemento</span>
                    <span id="clienteRenovac" class="win-value" style="font-weight: 400;">--</span>
                </div>
                
                <!-- Separador -->
                <div class="col-12">
                    <hr class="my-0" style="border-color: var(--win-border-color); opacity: 0.5;">
                </div>

                <!-- Contacto -->
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="win-label mb-0"><i class="fas fa-university win-icon-muted me-1"></i>Cta</span>
                        <span id="clienteNoCtaDeudor" class="win-value" style="font-size: 0.8rem;">--</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-truncate me-2" style="color: var(--win-text-secondary);" title="Email">
                            <i class="fas fa-envelope win-icon-muted me-1"></i><span id="clienteEmail">--</span>
                        </small>
                        <small style="color: var(--win-text-secondary);" title="Teléfono">
                            <i class="fas fa-phone win-icon-muted me-1"></i><span id="clienteTelefono">--</span>
                        </small>
                    </div>
                </div>
            </div>
            
<!-- Botones de acción -->
<div class="mt-4 d-flex gap-2">
    <button type="button" class="btn btn-sm btn-outline-primary flex-fill" id="btnVerMasDetalles" 
			data-bs-toggle="tooltip" title="Ver Ficha Completa"  
            onclick="verMasDetallesCliente()" disabled 
            style="border-style: dashed;">
        <i class="fas fa-expand-alt me-1"></i>Ver ficha
    </button>
    
    <button type="button" class="btn btn-sm btn-outline-warning flex-fill" id="btnEditarCliente" 
			data-bs-toggle="tooltip" title="Editar Datos del Cliente"  
            onclick="editarCliente()" disabled 
            style="border-style: dashed;">
        <i class="fas fa-edit me-1"></i>Editar datos
    </button>
</div>
        </div>
    </div>
</div>
			</div>

            <!-- Tabla de Servicios -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h6 class="mb-0 fw-bold d-inline-block" style="color: var(--win-text-primary);">
                            <i class="fas fa-list me-2" style="color: var(--win-accent);"></i>Detalles de la <?php echo $tipo_documento; ?>
                        </h6>
                        <div class="ms-3 d-flex gap-2">
                            <span class="badge bg-primary text-dark" id="contadorServicios" data-bs-toggle="tooltip" 
                                  title="Cantidad de servicios">0</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <!-- TODOS LOS BOTONES EN EL HEADER -->
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarServicios()" 
                                data-bs-toggle="tooltip" title="Eliminar todos los servicios" id="btnLimpiarHeader">
                            <i class="fas fa-trash-alt"></i>
                            <span class="d-none d-md-inline ms-1">Limpiar</span>
                        </button>
                        
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="validarServicios()" 
                                data-bs-toggle="tooltip" title="Validar servicios" id="btnValidarHeader">
                            <i class="fas fa-check-circle"></i>
                            <span class="d-none d-md-inline ms-1">Validar</span>
                        </button>
                        
                        <div class="vr"></div>
                        
                        <button type="button" class="btn btn-sm btn-primary" onclick="agregarServicio()"
                                data-bs-toggle="tooltip" title="Agregar nuevos servicios">
                            <i class="fas fa-plus me-1"></i>
                            <span class="d-none d-md-inline">Agregar</span>
                        </button>
                        
                        <!----<button type="button" class="btn btn-sm btn-success" onclick="procesarFactura()"
                                data-bs-toggle="tooltip" title="Guardar <?php echo $tipo_documento; ?> completa" id="btnGuardarHeader">
                            <i class="fas fa-save me-1"></i>
                            <span class="d-none d-md-inline">Guardar <?php echo $tipo_documento; ?></span>
                        </button>----->
						
						<button type="button" class="btn btn-sm btn-success" onclick="procesarFactura()"
								data-bs-toggle="tooltip" title="<?php echo ($tipo_documento === 'OFERTA') ? 'Imprimir Oferta' : 'Guardar Factura'; ?>" 
								id="btnGuardarHeader">
							<i class="fas fa-<?php echo ($tipo_documento === 'OFERTA') ? 'print' : 'save'; ?> me-1"></i>
							<span class="d-none d-md-inline">
								<?php echo ($tipo_documento === 'OFERTA') ? 'Imprimir Oferta' : 'Guardar ' . $tipo_documento; ?>
							</span>
						</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="errorServicios" class="alert alert-danger alert-dismissible fade show d-none" role="alert">
                        <div id="errorContent"></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table" id="serviciosTable">
                            <thead>
                                <tr>
                                    <th width="40">#</th>
                                    <th>Servicio</th>
                                    <th width="150" class="text-center">Cantidad</th>
                                    <th width="150" class="text-end">Precio Unitario</th>
                                    <th width="150" class="text-end">Total Línea</th>
                                    <th width="60"></th>
                                </tr>
                            </thead>
                            <tbody id="serviciosBody">
                                <!-- Las filas se agregarán dinámicamente -->
                            </tbody>
<tfoot>
    <tr>
        <td colspan="4" class="text-end fw-bold">Subtotal:</td>
        <td class="text-end fw-bold" id="subtotalDisplay">$0.00</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="4" class="text-end fw-bold">Total General:</td>
        <td class="text-end fw-bold total-general" id="totalGeneralDisplay">$0.00</td>
        <td></td>
    </tr>
    <!-- NUEVA FILA: Importe en letras al final -->
    <tr style="background-color: var(--win-bg-tertiary);">
        <td colspan="6" class="text-end py-2">
            <small class="text-muted me-2">IMPORTE EN LETRAS:</small>
            <span id="importeLetrasFooter" class="text-uppercase fw-bold text-warning" style="font-size: 0.85rem; color: var(--win-text-secondary);">
                CERO PESOS CON 00/100
            </span>
        </td>
    </tr>
</tfoot>
                        </table>
                    </div>
                    
                    <!-- Información adicional en el footer del card -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                        <div>
                            <small class="text-muted" id="infoServiciosDetalle">
                                <i class="fas fa-info-circle me-1"></i>
                                <span id="numServiciosInfo">0</span> servicios - 
                                <span id="totalItemsInfo">0</span> unidades totales
                            </small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="agregarServicio()">
                                <i class="fas fa-plus-circle me-1"></i>Agregar Más
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="mostrarResumen()">
                                <i class="fas fa-eye me-1"></i>Ver Resumen Detallado
                            </button>
                        </div>
                    </div>
                </div>
            </div>
			
			
			<!-- Campos ocultos para el formulario -->
            <input type="hidden" id="subtotalInput" name="subtotal" value="0">
            <input type="hidden" id="totalGeneralInput" name="total_general" value="0">

            <!-- Botones de acción -->
			<div class="d-flex justify-content-between pt-3 border-top">
				<div>
					<a href="facturas.php" class="btn btn-outline-secondary">
						<i class="fas fa-times me-1"></i>Cancelar
					</a>
					<button type="button" class="btn btn-outline-danger ms-2"  data-bs-toggle="tooltip" title="Restablecer Factura Limpia" onclick="limpiarFacturaCompleta()">
						<i class="fas fa-trash-alt me-1"></i>Limpiar Todo
					</button>
				</div>
						<button type="button" class="btn btn-sm btn-success" onclick="procesarFactura()"
								data-bs-toggle="tooltip" title="<?php echo ($tipo_documento === 'OFERTA') ? 'Imprimir Oferta' : 'Guardar Factura'; ?>" 
								id="btnGuardar">
							<i class="fas fa-<?php echo ($tipo_documento === 'OFERTA') ? 'print' : 'save'; ?> me-1"></i>
							<span class="d-none d-md-inline">
								<?php echo ($tipo_documento === 'OFERTA') ? 'Imprimir Oferta' : 'Guardar ' . $tipo_documento; ?>
							</span>
						</button>
			</div>
        </form>
    </main>

<!-- Modal para seleccionar servicio -->
<div class="modal fade" id="servicioModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-win shadow-lg" style="background-color: var(--win-bg-secondary); color: var(--win-text-primary);">
            
            <!-- Header Compacto -->
            <div class="modal-header py-2 px-3 border-bottom border-win" style="background: var(--win-bg-tertiary);">
                <h6 class="modal-title d-flex align-items-center fw-semibold" style="color: var(--win-text-primary); font-size: 14px;">
                    <i class="fas fa-list-ul me-2" style="color: var(--win-accent);"></i></span>
                    Seleccionar Servicios <span class="fw-bold text-warning">&nbsp;(ESC -> Cancelar/Cerrar)</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                <!-- Alerta de error -->
                <div id="modalError" class="alert alert-danger py-2 mb-3 d-none" style="font-size: 12px;"></div>
                
                <!-- Filtros en una sola línea para ahorrar espacio -->
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text border-win" style="background: var(--win-bg-tertiary); color: var(--win-text-secondary); border-right: none;">
                                <i class="fas fa-filter"></i>
                            </span>
                            <select class="form-select border-win" id="selectCategoria" onchange="cargarServiciosPorCategoria()" 
                                    style="background-color: var(--win-bg-tertiary); color: var(--win-text-primary); border-left: none;">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>">
                                        <?php echo htmlspecialchars($categoria['codigo'] . ' - ' . $categoria['descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text border-win" style="background: var(--win-bg-tertiary); color: var(--win-text-secondary); border-right: none;">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control border-win" id="searchServicio" 
                                   placeholder="Buscar por código o nombre..." onkeyup="filtrarServicios()"
                                   style="background-color: var(--win-bg-tertiary); color: var(--win-text-primary); border-left: none;">
                        </div>
                    </div>
                </div>
                
                <!-- Contador de seleccionados -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted" id="contadorSeleccionados">
                        <i class="fas fa-check-circle me-1"></i>
                        <span id="numSeleccionados">0</span> servicios seleccionados
                    </small>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="seleccionarTodos()" id="btnSeleccionarTodos">
                            <i class="fas fa-check-double me-1"></i>Seleccionar todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"  data-bs-toggle="tooltip" title="Desmarcar todos los Servicios" onclick="deseleccionarTodos()">
                            <i class="fas fa-times me-1"></i>Limpiar
                        </button>
                    </div>
                </div>
                
                <!-- Tabla Tematizada -->
                <div class="table-responsive border-win rounded" style="max-height: 380px; background-color: var(--win-bg-secondary);">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaServiciosModal" style="color: inherit;">
                        <thead style="position: sticky; top: 0; z-index: 10;">
                            <tr style="background-color: var(--win-bg-tertiary); color: var(--win-text-secondary); font-size: 11px;">
                                <th class="ps-3 border-win text-uppercase py-2" width="50">
                                    <input type="checkbox" id="checkAll" onchange="toggleTodosCheckboxes()">
                                </th>
                                <th class="border-win text-uppercase py-2" width="110">Código</th>
                                <th class="border-win text-uppercase py-2">Descripción</th>
                                <th class="border-win text-uppercase py-2">Categoría</th>
                                <th class="text-end pe-3 border-win text-uppercase py-2" width="100">Precio</th>
                            </tr>
                        </thead>
                        <tbody id="serviciosModalBody" style="font-size: 13px;">
                            <!-- El contenido se carga dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer con botones estilo Windows -->
            <div class="modal-footer py-2 border-top border-win" style="background: var(--win-bg-tertiary);">
                <button class="btn btn-outline-secondary border-win" 
                        type="button"
						data-bs-toggle="tooltip" 
                        title="Cerrar Ventada" 
						data-bs-dismiss="modal"
                        style="background: var(--win-bg-tertiary); border-left: none; border-color: var(--win-border-color);">
                    <i class="fas fa-times"></i> Cancelar/Cerrar
                </button>
                <button type="button" class="btn btn-sm px-4" onclick="seleccionarServicio()" 
                        style="background: var(--win-accent); color: #ffffff; font-size: 13px; font-weight: 500; border: none;"
                        id="btnAgregarServicios">
                    <i class="fas fa-plus me-2"></i>
                    <span id="btnAgregarTexto">Agregar (0)</span>
                </button>
            </div>
        </div>
    </div>
</div>

   <!-- Quick Action Simple - Solo para agregar servicios -->
    <?php if ($esAdmin || $esEditor): ?>
	<button class="win-quick-action" onclick="agregarServicio()"  data-bs-toggle="tooltip" title="Agregar servicio a la <?php echo strtolower($tipo_documento); ?>">
		<i class="fas fa-plus"></i>
	</button>
    <?php endif; ?>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
        let serviciosDisponibles = <?php echo json_encode($servicios); ?>;
        let serviciosAgregados = new Set();
        let filaIndex = 0;
        let serviciosSeleccionadosModal = new Set();

// --- VARIABLES GLOBALES DE FECHA (Desde PHP) ---
    // Estas constantes traen la configuración de la BD
    const FECHA_OPERATIVA_HOY = "<?php echo $hoy_operativo; ?>";      // YYYY-MM-DD (Fin del rango)
    const FECHA_OPERATIVA_INICIO = "<?php echo $primer_dia_operativo; ?>"; // YYYY-MM-01 (Inicio del rango)
	const FECHA_OPERATIVA_FIN = "<?php echo $ultimo_dia_operativo; ?>";    // ÚLTIMO día del mes
    const MES_OPERATIVO_NOMBRE = "<?php echo $mes_actual_es; ?>";
    const ANIO_OPERATIVO = <?php echo $anio_cierre_num; ?>;
    const MES_OPERATIVO_NUM = <?php echo $mes_cierre_num; ?>; // 1-12
	
	
	
// Función para actualizar información del cliente en el Card Lateral
function actualizarInfoCliente() {
    const select = document.getElementById('clienteSelect');
    const selectedOption = select.options[select.selectedIndex];
    const btnVerMas = document.getElementById('btnVerMasDetalles');
    
    if (selectedOption.value) {
        // 1. Obtener todos los datos del atributo data-
        const nombre = selectedOption.getAttribute('data-nombre');
        const codigo = selectedOption.getAttribute('data-codigo');
        const nit = selectedOption.getAttribute('data-nit');
        const direccion = selectedOption.getAttribute('data-direccion');
        
        // Datos Contractuales
        const contratoNo = selectedOption.getAttribute('data-contratono');
        const responsable = selectedOption.getAttribute('data-responsable');
        const vigencia = selectedOption.getAttribute('data-vigencia');
        const renovac = selectedOption.getAttribute('data-renovac'); // 1 o 0
        const siRenovaCant = selectedOption.getAttribute('data-sirenova');
        
        // Fechas
        const fechaRegistro = selectedOption.getAttribute('data-fecharegistro');
        const fechaFinal = selectedOption.getAttribute('data-fechafinal');
        const fechaFinalIso = selectedOption.getAttribute('data-fechafinal-iso'); // YYYY-MM-DD para lógica
        
        // Contacto y Banco
        const noCtaDeudor = selectedOption.getAttribute('data-noctadeudor');
        const telefono = selectedOption.getAttribute('data-telefono');
        const email = selectedOption.getAttribute('data-email');
        const activo = selectedOption.getAttribute('data-activo');

        // 2. Rellenar HTML del Card
        
        // Encabezado
        document.getElementById('clienteNombre').textContent = nombre || '--';
        document.getElementById('clienteCodigo').textContent = codigo || '--';
        document.getElementById('clienteNit').textContent = 'NIT: ' + (nit || '--');
        
        // Estado Cliente (Activo/Inactivo)
        const estadoBadge = document.getElementById('clienteActivo');
        if (activo === '1') {
            estadoBadge.textContent = 'Activo';
            estadoBadge.className = 'badge bg-success';
        } else {
            estadoBadge.textContent = 'Inactivo';
            estadoBadge.className = 'badge bg-danger';
        }
const direccionEl = document.getElementById('clienteDireccion');
direccionEl.textContent = direccion || '--';
direccionEl.title = direccion || ''; 

        // Información Detallada
        document.getElementById('clienteDireccion').textContent = direccion || '--';
        document.getElementById('clienteDireccion').title = direccion || ''; // Tooltip nativo
        
        document.getElementById('clienteContratoNo').textContent = contratoNo || '--';
        document.getElementById('clienteResponsable').textContent = responsable || '--';
        document.getElementById('clienteResponsable').title = responsable || '';
        
        // Fechas
        document.getElementById('clienteFechaRegistro').textContent = fechaRegistro || '--';
        document.getElementById('clienteFechaFinal').textContent = fechaFinal || '--';
        
        // Vigencia y Suplemento (Formateo)
        let textoVigencia = '--';
        if (vigencia) textoVigencia = vigencia == '1' ? '1 año' : `${vigencia} años`;
        document.getElementById('clienteVigencia').textContent = textoVigencia;

        let textoRenovac = '--';
        if (renovac == '1') {
             textoRenovac = siRenovaCant == '1' ? 'Sí (1 año)' : `Sí (${siRenovaCant} a)`;
        } else {
            textoRenovac = 'No';
        }
        document.getElementById('clienteRenovac').textContent = textoRenovac;

        // Contacto
        document.getElementById('clienteNoCtaDeudor').textContent = noCtaDeudor || '--';
        document.getElementById('clienteTelefono').textContent = telefono || '--';
        document.getElementById('clienteEmail').textContent = email || '--';
        document.getElementById('clienteEmail').parentNode.title = email || '';

        // 3. Lógica de Estado del Contrato (Semáforo)
        const estadoContrato = document.getElementById('estadoContrato');
        
        if (!fechaFinalIso || fechaFinalIso === '0000-00-00') {
            estadoContrato.textContent = 'Sin Fecha';
            estadoContrato.className = 'badge bg-secondary float-end';
        } else {
            const fechaFin = new Date(fechaFinalIso + 'T00:00:00');
            const hoy = new Date();
            hoy.setHours(0,0,0,0);
            
            const diffTime = fechaFin - hoy;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays < 0) {
                estadoContrato.textContent = 'Vencido';
                estadoContrato.className = 'badge bg-danger float-end';
            } else if (diffDays === 0) {
                estadoContrato.textContent = 'VENCE HOY';
                estadoContrato.className = 'badge bg-danger float-end animate__animated animate__pulse animate__infinite';
            } else if (diffDays <= 30) {
                estadoContrato.textContent = 'Por Vencer';
                estadoContrato.className = 'badge bg-warning text-dark float-end';
            } else {
                estadoContrato.textContent = 'Vigente';
                estadoContrato.className = 'badge bg-success float-end';
            }
        }
        
		// Habilitar botones de acción
		btnVerMas.disabled = false;
		document.getElementById('btnEditarCliente').disabled = false;

    } else {
        // 4. Resetear a valores por defecto si no hay selección
        document.getElementById('clienteNombre').textContent = 'Seleccione un cliente';
        document.getElementById('clienteCodigo').textContent = 'Código: --';
        document.getElementById('clienteNit').textContent = 'NIT: --';
        document.getElementById('clienteActivo').textContent = '--';
        document.getElementById('clienteActivo').className = 'badge bg-secondary';
        
        const camposAResetear = [
            'clienteDireccion', 'clienteContratoNo', 'clienteResponsable',
            'clienteFechaRegistro', 'clienteFechaFinal', 'clienteVigencia',
            'clienteRenovac', 'clienteNoCtaDeudor', 'clienteTelefono', 'clienteEmail'
        ];
        
        camposAResetear.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.textContent = '--';
        });

        const estadoContrato = document.getElementById('estadoContrato');
        estadoContrato.textContent = '--';
        estadoContrato.className = 'badge bg-secondary float-end';
        
        btnVerMas.disabled = true;
		document.getElementById('btnEditarCliente').disabled = true;
    }
}



// Función para ver más detalles del cliente (Actualizada con todos los campos)
function verMasDetallesCliente() {
    const select = document.getElementById('clienteSelect');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        // Obtener todos los datos del option
        const d = {
            codigo: selectedOption.getAttribute('data-codigo'),
            nombre: selectedOption.getAttribute('data-nombre'),
            direccion: selectedOption.getAttribute('data-direccion'),
            codReup: selectedOption.getAttribute('data-codreup'),
            nit: selectedOption.getAttribute('data-nit'),
            contratoNo: selectedOption.getAttribute('data-contratono'),
            responsable: selectedOption.getAttribute('data-responsable'),
            sucursal: selectedOption.getAttribute('data-sucursal'),
            noCta: selectedOption.getAttribute('data-noctadeudor'),
            telefono: selectedOption.getAttribute('data-telefono'),
            email: selectedOption.getAttribute('data-email'),
            fRegistro: selectedOption.getAttribute('data-fecharegistro'),
            fVence: selectedOption.getAttribute('data-fechavence'),
            fFinal: selectedOption.getAttribute('data-fechafinal'),
            vigencia: selectedOption.getAttribute('data-vigencia'),
            renovac: selectedOption.getAttribute('data-renovac'),
            siRenovaCant: selectedOption.getAttribute('data-sirenova'),
            activo: selectedOption.getAttribute('data-activo')
        };
        
        // Formatear vigencia
        let vigenciaTexto = '--';
        if(d.vigencia) {
            vigenciaTexto = d.vigencia == '1' ? '1 año' : `${d.vigencia} años`;
        }

        // Formatear renovación
        let renovacionTexto = 'No';
        if(d.renovac == '1') {
            const cant = d.siRenovaCant;
            renovacionTexto = cant == '1' ? 'Sí (1 año)' : `Sí (${cant} años)`;
        }

        // Crear mensaje con todos los detalles (Formato Tabla/Grid)
        let mensaje = `
            <div class="text-start" style="font-size: 0.95rem;">
                <h5 class="mb-3 border-bottom pb-2 text-primary">
                    <i class="fas fa-building me-2"></i>${d.nombre}
                </h5>
                
                <div class="row g-3">
                    <!-- Columna Izquierda: Identificación y Contacto -->
                    <div class="col-md-6 border-end">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Datos Generales</h6>
                        <p class="mb-1"><strong>Código:</strong> ${d.codigo || '--'}</p>
                        <p class="mb-1"><strong>NIT:</strong> ${d.nit || '--'}</p>
                        <p class="mb-1"><strong>Cód. REUP:</strong> ${d.codReup || '--'}</p>
                        <p class="mb-1"><strong>Responsable:</strong> ${d.responsable || '--'}</p>
                        <p class="mb-1"><strong>Dirección:</strong><br><small>${d.direccion || '--'}</small></p>
                        <p class="mb-1"><strong>Sucursal:</strong> ${d.sucursal || '--'}</p>
                            <p class="mb-1"><i class="fas fa-university me-1"></i><strong>Cta:</strong> ${d.noCta || '--'}</p>
                            <p class="mb-1"><i class="fas fa-phone me-1"></i> ${d.telefono || '--'}</p>
                            <p class="mb-0"><i class="fas fa-envelope me-1"></i> ${d.email || '--'}</p>
                    </div>

                    <!-- Columna Derecha: Datos del Contrato -->
                    <div class="col-md-6 ps-md-3">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Información Contractual</h6>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><strong>No. Contrato:</strong></span>
                            <span class="badge bg-primary">${d.contratoNo || '--'}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><strong>Estado Cliente:</strong></span>
                            <span class="badge ${d.activo === '1' ? 'bg-success' : 'bg-danger'}">
                                ${d.activo === '1' ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>

                        <hr class="my-2">
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted d-block">Fecha Inicio</small>
                                <strong>${d.fRegistro || '--'}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Fecha Término</small>
                                <strong>${d.fVence || '--'}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Vigencia</small>
                                <strong>${vigenciaTexto}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Suplemento</small>
                                <strong>${renovacionTexto}</strong>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="alert alert-danger py-2 px-3 mb-0">
                                    <small class="text-uppercase fw-bold text-dark" style="font-size: 0.7rem;">Fecha Final Contractual<br>(Fecha Término + Años Suplementados)</small>
                                    <div class="fw-bold fs-5 text-dark">${d.fFinal || 'Indefinida'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Mostrar modal con SweetAlert2
        Swal.fire({
            html: mensaje,
            width: '850px',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                popup: 'border-win shadow-lg'
            }
        });
    }
}
// Función para editar cliente
function editarCliente() {
    const select = document.getElementById('clienteSelect');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const clienteId = selectedOption.value;
        
        // Preguntar confirmación antes de redirigir
        Swal.fire({
            title: '¿Editar este cliente?',
            text: 'Serás redirigido a la página de edición del cliente',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-edit me-2"></i>Sí, editar',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            confirmButtonColor: '#ffc107',
            cancelButtonColor: 'var(--win-accent)'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Redirigiendo...',
                    text: 'Cargando editor de cliente',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Redirigir a la página de edición
                window.location.href = `editar_cliente.php?id=${clienteId}&return_to=nueva_factura.php`;
            }
        });
    }
}
// Cargar servicios en el modal - CORREGIDO
function cargarServiciosModal() {
    
    const tbody = document.getElementById('serviciosModalBody');
    const categoriaId = document.getElementById('selectCategoria').value;
    
    // Filtrar servicios por categoría
    let serviciosFiltrados = serviciosDisponibles.filter(servicio => {
        if (!categoriaId) return true;
        return servicio.categoria_id == categoriaId;
    });
    
    // Filtrar servicios ya agregados (para no mostrar duplicados)
    serviciosFiltrados = serviciosFiltrados.filter(servicio => {
        const servicioIdStr = servicio.id.toString();
        const yaAgregado = Array.from(serviciosAgregados).some(id => id.toString() === servicioIdStr);
        return !yaAgregado;
    });
    
    let html = '';
    
    if (serviciosFiltrados.length === 0) {
        html = `
            <tr>
                <td colspan="5" class="text-center py-5" 
                    style="color: var(--win-text-secondary) !important; background: var(--win-bg-tertiary);">
                    <i class="fas fa-inbox fa-2x mb-3" style="color: var(--win-border-color);"></i>
                    <div class="fw-medium">No hay servicios disponibles</div>
                    <small class="opacity-75">
                        ${categoriaId ? 'En esta categoría' : ''}
                        ${serviciosAgregados.size > 0 ? ' (todos los servicios ya han sido agregados)' : ''}
                    </small>
                </td>
            </tr>`;
    } else {
        serviciosFiltrados.forEach((servicio, index) => {
            const rowClass = index % 2 === 0 ? 'even-row' : 'odd-row';
            const estaSeleccionado = serviciosSeleccionadosModal.has(servicio.id.toString());
            
            // --- LÓGICA DE INACTIVOS ---
            // Si servicio.activo es "0" o 0, es inactivo.
            const esInactivo = (servicio.activo == 0);
            
            // Estilos visuales
            let estiloFila = '';
            let estiloCheckbox = 'accent-color: var(--win-accent); width: 18px; height: 18px;';
            let badgeClass = 'var(--win-accent-light)';
            let badgeColor = 'var(--win-accent)';
            let badgeTexto = '';

            if (esInactivo) {
                // Estilo gris, opaco y cursor prohibido
                estiloFila = 'background-color: rgba(128, 128, 128, 0.15); color: var(--win-text-secondary); cursor: not-allowed; opacity: 0.7;';
                badgeClass = '#6c757d'; // Gris
                badgeColor = 'white';
                badgeTexto = '<span class="badge bg-secondary ms-2" style="font-size: 0.6rem;">INACTIVO</span>';
            } else {
                // Estilo normal interactivo
                estiloFila = `cursor: pointer; transition: all 0.2s ease; ${estaSeleccionado ? 'background-color: var(--win-accent-light); border-left: 3px solid var(--win-accent);' : ''}`;
            }

            html += `
                <tr data-id="${servicio.id}" 
                    data-precio="${servicio.costo}" 
                    data-descripcion="${servicio.descripcion}"
                    data-codigo="${servicio.codigo}"
                    data-categoria="${servicio.categoria || 'Sin categoría'}"
                    data-categoria-id="${servicio.categoria_id}"
                    data-activo="${servicio.activo}"
                    class="service-row ${rowClass}"
                    style="${estiloFila}">
                    
                    <td class="text-center ps-3" style="width: 50px; vertical-align: middle;">
                        <input type="checkbox" class="servicio-checkbox" 
                               value="${servicio.id}"
                               onchange="actualizarContador()"
                               ${estaSeleccionado ? 'checked' : ''}
                               ${esInactivo ? 'disabled' : ''} 
                               style="${estiloCheckbox}">
                    </td>
                    
                    <td style="font-weight: 500; color: inherit !important;">
                        <span class="badge" 
                              style="background: ${badgeClass}; color: ${badgeColor}; padding: 4px 8px;">
                            ${servicio.codigo}
                        </span>
                    </td>
                    
                    <td style="font-weight: 500; color: inherit !important;">
                        ${servicio.descripcion} ${badgeTexto}
                    </td>
                    
                    <td style="color: inherit !important;">
                        <small>${servicio.categoria || 'Sin categoría'}</small>
                    </td>
                    
                    <td class="text-end pe-3" style="font-weight: 600; color: inherit !important;">
                        $${parseFloat(servicio.costo).toFixed(2)}
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
    
    // Re-asignar eventos click (BLOQUEANDO INACTIVOS)
    document.querySelectorAll('#serviciosModalBody tr[data-id]').forEach(row => {
        row.addEventListener('click', function(e) {
            // Si el clic fue directo en el checkbox, dejar que el evento nativo actúe
            if (e.target.type === 'checkbox') return;
            
            const checkbox = this.querySelector('.servicio-checkbox');
            
            // --- VALIDACIÓN CLAVE: Si está deshabilitado, NO hacer nada ---
            if (checkbox.disabled) return;

            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
                
                // Actualizar estilo visual de selección
                if (checkbox.checked) {
                    this.style.backgroundColor = 'var(--win-accent-light)';
                    this.style.borderLeft = '3px solid var(--win-accent)';
                } else {
                    this.style.backgroundColor = '';
                    this.style.borderLeft = '';
                }
            }
        });
    });
    
    actualizarContador();
}
		
		
		// Actualizar contador de servicios seleccionados
        function actualizarContador() {
            const checkboxes = document.querySelectorAll('.servicio-checkbox:checked');
            const numSeleccionados = checkboxes.length;
            
            document.getElementById('numSeleccionados').textContent = numSeleccionados;
            
            // Actualizar variable global
            serviciosSeleccionadosModal.clear();
            checkboxes.forEach(checkbox => {
                serviciosSeleccionadosModal.add(checkbox.value);
            });
            
            // Actualizar estado del checkbox "Seleccionar todos"
            const totalCheckboxes = document.querySelectorAll('.servicio-checkbox').length;
            const checkAll = document.getElementById('checkAll');
            if (totalCheckboxes > 0) {
                checkAll.checked = (numSeleccionados === totalCheckboxes);
                checkAll.indeterminate = (numSeleccionados > 0 && numSeleccionados < totalCheckboxes);
            }
            
            // Actualizar texto del botón
            const btnSeleccionarTodos = document.getElementById('btnSeleccionarTodos');
            if (numSeleccionados === totalCheckboxes && totalCheckboxes > 0) {
                btnSeleccionarTodos.innerHTML = '<i class="fas fa-times me-1"></i>Deseleccionar todos';
            } else {
                btnSeleccionarTodos.innerHTML = '<i class="fas fa-check-double me-1"></i>Seleccionar todos';
            }
            
            // Actualizar texto del botón de agregar
            const btnAgregarTexto = document.getElementById('btnAgregarTexto');
            btnAgregarTexto.textContent = `Agregar (${numSeleccionados})`;
            
            const btnAgregar = document.getElementById('btnAgregarServicios');
            if (numSeleccionados > 0) {
                btnAgregar.disabled = false;
                btnAgregar.style.opacity = '1';
            } else {
                btnAgregar.disabled = true;
                btnAgregar.style.opacity = '0.7';
            }
        }
        
        // Seleccionar todos los checkboxes
        function seleccionarTodos() {
            const checkboxes = document.querySelectorAll('.servicio-checkbox');
            const todosMarcados = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !todosMarcados;
                
                // Aplicar estilo visual a la fila
                const fila = checkbox.closest('tr');
                if (fila) {
                    if (!todosMarcados) {
                        fila.style.backgroundColor = 'var(--win-accent-light)';
                        fila.style.borderLeft = '3px solid var(--win-accent)';
                    } else {
                        fila.style.backgroundColor = '';
                        fila.style.borderLeft = '';
                    }
                }
            });
            
            actualizarContador();
        }
        
        // Deseleccionar todos
        function deseleccionarTodos() {
            document.querySelectorAll('.servicio-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                
                // Quitar estilo visual
                const fila = checkbox.closest('tr');
                if (fila) {
                    fila.style.backgroundColor = '';
                    fila.style.borderLeft = '';
                }
            });
            
            document.getElementById('checkAll').checked = false;
            document.getElementById('checkAll').indeterminate = false;
            actualizarContador();
        }
        
        // Toggle todos los checkboxes desde el checkbox del header
        function toggleTodosCheckboxes() {
            const checkAll = document.getElementById('checkAll');
            const checkboxes = document.querySelectorAll('.servicio-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = checkAll.checked;
                
                // Aplicar/quitar estilo visual
                const fila = checkbox.closest('tr');
                if (fila) {
                    if (checkAll.checked) {
                        fila.style.backgroundColor = 'var(--win-accent-light)';
                        fila.style.borderLeft = '3px solid var(--win-accent)';
                    } else {
                        fila.style.backgroundColor = '';
                        fila.style.borderLeft = '';
                    }
                }
            });
            
            actualizarContador();
        }
        
// Filtrar servicios en el modal - CORREGIDO
function filtrarServicios() {
    const searchTerm = document.getElementById('searchServicio').value.toLowerCase();
    const categoriaId = document.getElementById('selectCategoria').value;
    
    let serviciosFiltrados = serviciosDisponibles.filter(servicio => {
        if (categoriaId && servicio.categoria_id != categoriaId) return false;
        
        if (searchTerm) {
            const descripcion = servicio.descripcion.toLowerCase();
            const codigo = servicio.codigo.toLowerCase();
            return descripcion.includes(searchTerm) || codigo.includes(searchTerm);
        }
        return true;
    });
    
    serviciosFiltrados = serviciosFiltrados.filter(servicio => {
        return !serviciosAgregados.has(servicio.id.toString());
    });
    
    const tbody = document.getElementById('serviciosModalBody');
    let html = '';
    
    if (serviciosFiltrados.length === 0) {
        html = `
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                    No hay servicios disponibles con los filtros aplicados
                </td>
            </tr>`;
    } else {
        serviciosFiltrados.forEach((servicio, index) => {
            const rowClass = index % 2 === 0 ? 'even-row' : 'odd-row';
            const estaSeleccionado = serviciosSeleccionadosModal.has(servicio.id.toString());
            
            // --- LÓGICA PARA INACTIVOS ---
            const esInactivo = servicio.activo == 0; 
            
            const estiloFila = esInactivo 
                ? 'background-color: rgba(128, 128, 128, 0.1); color: var(--win-text-secondary); cursor: not-allowed; opacity: 0.7;' 
                : `cursor: pointer; transition: all 0.2s ease; ${estaSeleccionado ? 'background-color: var(--win-accent-light); border-left: 3px solid var(--win-accent);' : ''}`;
            
            const badgeInactivo = esInactivo 
                ? '<span class="badge bg-secondary ms-2" style="font-size: 0.6rem;">INACTIVO</span>' 
                : '';

            html += `
                <tr data-id="${servicio.id}" 
                    data-precio="${servicio.costo}" 
                    data-descripcion="${servicio.descripcion}"
                    data-codigo="${servicio['codigo']}"
                    data-categoria="${servicio.categoria || 'Sin categoría'}"
                    data-categoria-id="${servicio.categoria_id}"
                    class="service-row ${rowClass}"
                    style="${estiloFila}">
                    <td class="text-center ps-3" style="width: 50px; vertical-align: middle;">
                        <input type="checkbox" class="servicio-checkbox" 
                               value="${servicio.id}"
                               onchange="actualizarContador()"
                               ${estaSeleccionado ? 'checked' : ''}
                               ${esInactivo ? 'disabled' : ''}
                               style="accent-color: var(--win-accent); width: 18px; height: 18px;">
                    </td>
                    <td style="font-weight: 500; color: inherit !important;">
                        <span class="badge" 
                              style="background: ${esInactivo ? '#6c757d' : 'var(--win-accent-light)'}; color: ${esInactivo ? 'white' : 'var(--win-accent)'}; padding: 4px 8px;">
                            ${servicio.codigo}
                        </span>
                    </td>
                    <td style="font-weight: 500; color: inherit !important;">
                        ${servicio.descripcion} ${badgeInactivo}
                    </td>
                    <td style="color: inherit !important;">
                        <small>${servicio.categoria || 'Sin categoría'}</small>
                    </td>
                    <td class="text-end pe-3" style="font-weight: 600; color: inherit !important;">
                        $${parseFloat(servicio.costo).toFixed(2)}
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
    
    document.querySelectorAll('#serviciosModalBody tr[data-id]').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox') return;
            
            const checkbox = this.querySelector('.servicio-checkbox');
            
            // Si está inactivo, no hacer nada
            if (checkbox.disabled) return;

            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
                
                if (checkbox.checked) {
                    this.style.backgroundColor = 'var(--win-accent-light)';
                    this.style.borderLeft = '3px solid var(--win-accent)';
                } else {
                    this.style.backgroundColor = '';
                    this.style.borderLeft = '';
                }
            }
        });
    });
    
    actualizarContador();
}
		
// Cargar servicios por categoría - CORREGIDO PARA INACTIVOS
function cargarServiciosPorCategoria() {
    const categoriaId = document.getElementById('selectCategoria').value;
    const searchTerm = document.getElementById('searchServicio').value.toLowerCase();
    
    // Filtrar servicios
    let serviciosFiltrados = serviciosDisponibles.filter(servicio => {
        // Filtrar por categoría
        if (categoriaId && servicio.categoria_id != categoriaId) {
            return false;
        }
        
        // Filtrar por búsqueda (si hay texto escrito)
        if (searchTerm) {
            const descripcion = servicio.descripcion.toLowerCase();
            const codigo = servicio.codigo.toLowerCase();
            return descripcion.includes(searchTerm) || codigo.includes(searchTerm);
        }
        
        return true;
    });
    
    // Filtrar servicios ya agregados (para no duplicar)
    serviciosFiltrados = serviciosFiltrados.filter(servicio => {
        return !serviciosAgregados.has(servicio.id.toString());
    });
    
    const tbody = document.getElementById('serviciosModalBody');
    let html = '';
    
    if (serviciosFiltrados.length === 0) {
        html = `<tr><td colspan="5" class="text-center py-4 text-muted">
            No hay servicios disponibles ${categoriaId ? 'en esta categoría' : ''}
        </td></tr>`;
    } else {
        serviciosFiltrados.forEach((servicio, index) => {
            const rowClass = index % 2 === 0 ? 'even-row' : 'odd-row';
            const estaSeleccionado = serviciosSeleccionadosModal.has(servicio.id.toString());
            
            // --- LÓGICA DE INACTIVOS (AQUÍ ESTABA FALTANDO) ---
            const esInactivo = (servicio.activo == 0); 
            
            // Estilos visuales
            let estiloFila = '';
            let estiloCheckbox = 'accent-color: var(--win-accent); width: 18px; height: 18px;';
            let badgeTexto = '';

            if (esInactivo) {
                // Estilo gris, opaco y cursor prohibido
                estiloFila = 'background-color: rgba(128, 128, 128, 0.15); color: var(--win-text-secondary); cursor: not-allowed; opacity: 0.7;';
                badgeTexto = '<span class="badge bg-secondary ms-2" style="font-size: 0.6rem;">INACTIVO</span>';
            } else {
                // Estilo normal interactivo
                estiloFila = `cursor: pointer; transition: all 0.2s ease; ${estaSeleccionado ? 'background-color: var(--win-accent-light); border-left: 3px solid var(--win-accent);' : ''}`;
            }
            
            html += `
                <tr data-id="${servicio.id}" 
                    data-precio="${servicio.costo}" 
                    data-descripcion="${servicio.descripcion}"
                    data-codigo="${servicio['codigo']}"
                    data-categoria="${servicio.categoria || 'Sin categoría'}"
                    data-categoria-id="${servicio.categoria_id}"
                    class="service-row ${rowClass}"
                    style="${estiloFila}">
                    
                    <td class="text-center ps-3" style="width: 50px; vertical-align: middle;">
                        <input type="checkbox" class="servicio-checkbox" 
                               value="${servicio.id}"
                               onchange="actualizarContador()"
                               ${estaSeleccionado ? 'checked' : ''}
                               ${esInactivo ? 'disabled' : ''} 
                               style="${estiloCheckbox}">
                    </td>
                    <td style="font-weight: 500; color: inherit !important;">
                        <span class="badge" 
                              style="background: ${esInactivo ? '#6c757d' : 'var(--win-accent-light)'}; color: ${esInactivo ? 'white' : 'var(--win-accent)'}; padding: 4px 8px;">
                            ${servicio.codigo}
                        </span>
                    </td>
                    <td style="font-weight: 500; color: inherit !important;">
                        ${servicio.descripcion} ${badgeTexto}
                    </td>
                    <td style="color: inherit !important;">
                        <small>${servicio.categoria || 'Sin categoría'}</small>
                    </td>
                    <td class="text-end pe-3" style="font-weight: 600; color: inherit !important;">
                        $${parseFloat(servicio.costo).toFixed(2)}
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
    
    // Re-asignar eventos click (IMPORTANTE: Bloquear clic en inactivos)
    document.querySelectorAll('#serviciosModalBody tr[data-id]').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox') return;
            
            const checkbox = this.querySelector('.servicio-checkbox');
            
            // Si está inactivo, no hacer nada
            if (checkbox.disabled) return;

            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
                
                if (checkbox.checked) {
                    this.style.backgroundColor = 'var(--win-accent-light)';
                    this.style.borderLeft = '3px solid var(--win-accent)';
                } else {
                    this.style.backgroundColor = '';
                    this.style.borderLeft = '';
                }
            }
        });
    });
    
    actualizarContador();
}
// Función para limpiar completamente la factura - VERSIÓN CORREGIDA
function limpiarFacturaCompleta() {
    Swal.fire({
        title: '<i class="fas fa-broom me-2"></i>¿Limpiar toda la <?php echo strtolower($tipo_documento); ?>?',
        html: `
            <div class="text-start">
                <p>Esta acción realizará:</p>
                <ul class="mb-3">
                    <li>Eliminar todos los servicios agregados</li>
                    <li>Restablecer selección de cliente</li>
                    <li>Poner fecha actual</li>
                    <li>Mantener tipo de pago por defecto (TRANSFERENCIA BANCARIA)</li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Esta acción no se puede deshacer
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-broom me-2"></i> Sí, limpiar todo',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: 'var(--win-accent)',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Cerrar el modal actual inmediatamente
            Swal.close();
            
            // Mostrar loading inmediatamente después
            setTimeout(() => {
                ejecutarLimpiezaFactura();
            }, 100);
        }
    });
}

// Función que ejecuta la limpieza (separada para mejor control)
function ejecutarLimpiezaFactura() {
	
    
    // Ejecutar limpieza después de un pequeño delay
        // 1. Limpiar tabla de servicios
        document.getElementById('serviciosBody').innerHTML = '';
        
        // 2. Limpiar Set de servicios agregados
        serviciosAgregados.clear();
        
        // 3. Reiniciar índice
        filaIndex = 0;
        
        // 4. Resetear combo de cliente (seleccionar primera opción vacía)
        const clienteSelect = document.getElementById('clienteSelect');
        if (clienteSelect) {
            clienteSelect.selectedIndex = 0;
            // Disparar evento change para actualizar información del cliente
            setTimeout(() => {
                clienteSelect.dispatchEvent(new Event('change'));
            }, 50);
        }
        
        // 5. Poner fecha actual (HOY)
		const fechaEmision = document.getElementById('fechaEmision');
			if (fechaEmision) {
				// Usamos la constante global definida por PHP en el header
				fechaEmision.value = FECHA_OPERATIVA_HOY;
				
				// Disparar validación visual
				setTimeout(() => {
					validarFechaSeleccionada();
				}, 50);
			}
        
        // 6. Resetear estado a "PENDIENTE" (opcional)
        const estadoSelect = document.getElementById('estadoSelect');
        if (estadoSelect) {
            estadoSelect.value = 'PENDIENTE';
        }
        
        // 7. Mantener tipo de pago como TRANSFERENCIA BANCARIA (ID 20)
        const tipoPagoSelect = document.getElementById('tipoPagoSelect');
        if (tipoPagoSelect) {
            // Buscar la opción con value="20"
            const optionTransferencia = tipoPagoSelect.querySelector('option[value="20"]');
            if (optionTransferencia) {
                tipoPagoSelect.value = '20';
            } else {
                // Si no existe ID 20, seleccionar la primera opción
                tipoPagoSelect.selectedIndex = 1; // Saltar la primera opción vacía
            }
        }
        
        // 8. Limpiar errores
        limpiarErrores();
        
        // 9. Actualizar totales
        calcularTotales();
        
        // 10. Recargar modal de servicios
            cargarServiciosModal();
            
            // 11. Actualizar información del cliente
            actualizarInfoCliente();
            
            // 12. Actualizar contadores
            actualizarInfoServiciosDetallada();
                    Swal.fire({
                        icon: 'success',
                        title: '<i class="fas fa-check-circle me-2"></i><?php echo $tipo_documento; ?> limpiada',
                        html: `
                            <div class="text-start">
                                <p>La factura ha sido restablecida a su estado inicial:</p>
                                <div class="alert alert-success">
                                    <ul class="mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i> Servicios eliminados</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Cliente restablecido</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Fecha actual establecida</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Tipo de pago por defecto</li>
                                    </ul>
                                </div>
                                <p class="text-muted mt-2"><small>Puedes comenzar una nueva <?php echo $tipo_documento; ?></small></p>
                            </div>
                        `,
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)',
                        confirmButtonText: '<i class="fas fa-play me-2"></i> Comenzar nueva',
                        confirmButtonColor: 'var(--win-accent)',
                        timerProgressBar: true,
                        willClose: () => {
                            // Enfocar en el primer campo (cliente) después de cerrar
                            if (clienteSelect) {
                                    clienteSelect.focus();
                            }
                        }
                    });
		}
        // Abrir modal para agregar servicio
        function agregarServicio() {
            const modal = new bootstrap.Modal(document.getElementById('servicioModal'));
            modal.show();
        }
        
// Función agregarServicioATabla - CON VERIFICACIÓN INTERNA DE DUPLICADOS
function agregarServicioATabla(servicio) {
    console.log(`Intentando agregar servicio ID: ${servicio.id}`);
    
    // VERIFICACIÓN DOBLE DE DUPLICADOS
    const servicioIdStr = servicio.id.toString();
    
    if (serviciosAgregados.has(servicioIdStr)) {
        console.error(`❌ ERROR: El servicio ${servicio.descripcion} (ID: ${servicio.id}) ya está agregado`);
        return false;
    }
    
    try {
        const tbody = document.getElementById('serviciosBody');
        if (!tbody) {
            console.error('No se encontró el tbody con id="serviciosBody"');
            return false;
        }
        
        const fila = document.createElement('tr');
        const nuevoIndex = filaIndex;
        fila.id = 'fila_' + nuevoIndex;
        
        // Asegurar valores
        const id = servicio.id || '0';
        const descripcion = servicio.descripcion || 'Servicio sin nombre';
        const codigo = servicio.codigo || 'N/A';
        const categoria = servicio.categoria || 'Sin categoría';
        const costo = parseFloat(servicio.costo) || 0;
        const totalLinea = costo; // Cantidad inicial = 1
        
        // Crear HTML
        const html = `
            <td>${nuevoIndex + 1}</td>
            <td>
                <input type="hidden" name="servicio_id[]" value="${id}">
                <div>
                    <strong>${descripcion}</strong>
                    <div class="text-muted small">${codigo} | ${categoria}</div>
                </div>
            </td>
            <td class="text-center">
                <div class="cantidad-control justify-content-center">
                    <button type="button" class="btn btn-sm cantidad-btn" onclick="cambiarCantidad(${nuevoIndex}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" class="form-control form-control-sm cantidad-input cantidad" 
                           name="cantidad[]" value="1" min="1" step="1" 
                           onchange="actualizarLinea(${nuevoIndex})"
                           onblur="validarCantidadEntera(${nuevoIndex})">
                    <button type="button" class="btn btn-sm cantidad-btn" onclick="cambiarCantidad(${nuevoIndex}, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </td>
            <td class="text-end">
                <input type="number" class="form-control form-control-sm text-end precio" 
                       name="precio_unitario[]" value="${costo.toFixed(2)}" step="0.01" readonly>
            </td>
            <td class="text-end">
                <span class="fw-bold total-linea">$${totalLinea.toFixed(2)}</span>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarFila(${nuevoIndex})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        fila.innerHTML = html;
        tbody.appendChild(fila);
        
        // Agregar al Set de servicios agregados
        serviciosAgregados.add(id.toString());
        filaIndex++;
        
        // Animación
        fila.classList.add('animate__animated', 'animate__fadeIn');
        
        // Actualizar totales
        setTimeout(() => {
            calcularTotales();
        }, 100);
        
        console.log(`✅ Servicio agregado: ${descripcion} (ID: ${id})`);
        return true;
        
    } catch (error) {
        console.error('Error al agregar servicio a tabla:', error);
        return false;
    }
}
// Función seleccionarServicio - CON PREVENCIÓN DE DUPLICADOS MEJORADA
function seleccionarServicio() {
    
    const errorDiv = document.getElementById('modalError');
    errorDiv.classList.add('d-none');
    
    // Obtener checkboxes seleccionados
    const checkboxesSeleccionados = document.querySelectorAll('.servicio-checkbox:checked');
    
    if (checkboxesSeleccionados.length === 0) {
        errorDiv.textContent = 'Por favor seleccione al menos un servicio';
        errorDiv.classList.remove('d-none');
        return;
    }
    
    // VERIFICACIÓN DE DUPLICADOS MEJORADA
    const serviciosParaAgregar = [];
    const serviciosDuplicados = [];
    const serviciosYaAgregadosList = Array.from(serviciosAgregados);
    
    
    checkboxesSeleccionados.forEach(checkbox => {
        const servicioId = checkbox.value;
        
        // Buscar el servicio en la lista de disponibles
        const servicio = serviciosDisponibles.find(s => {
            // Comparar como string para evitar problemas de tipo
            return s.id.toString() === servicioId.toString();
        });
        
        if (servicio) {
            // Verificar si YA está en la lista de agregados
            const yaAgregado = serviciosYaAgregadosList.some(id => id.toString() === servicioId.toString());
            
            if (yaAgregado) {
                serviciosDuplicados.push({
                    id: servicioId,
                    nombre: servicio.descripcion,
                    codigo: servicio.codigo
                });
            } else {
                serviciosParaAgregar.push(servicio);
            }
        }
    });
    
    // MOSTRAR ERROR SI HAY DUPLICADOS
    if (serviciosDuplicados.length > 0) {
        const mensajeDuplicados = serviciosDuplicados.map(d => 
            `${d.nombre} (${d.codigo})`
        ).join(', ');
        
        errorDiv.innerHTML = `
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Servicios ya agregados:</strong><br>
            ${mensajeDuplicados}<br>
            <small class="text-muted">Estos servicios ya están en la factura actual.</small>
        `;
        errorDiv.classList.remove('d-none');
        
        // También mostrar alerta de SweetAlert
        Swal.fire({
            icon: 'warning',
            title: 'Servicios duplicados',
            html: `
                <div class="text-start">
                    <p>Los siguientes servicios ya están en la factura:</p>
                    <div class="alert alert-warning p-2">
                        <ul class="mb-0">
                            ${serviciosDuplicados.map(d => `<li><strong>${d.nombre}</strong> (${d.codigo})</li>`).join('')}
                        </ul>
                    </div>
                    <p class="mt-2 text-muted">Puedes eliminarlos de la factura si necesitas agregarlos nuevamente.</p>
                </div>
            `,
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: 'var(--win-accent)'
        });
        
        return;
    }
    
    if (serviciosParaAgregar.length === 0) {
        errorDiv.textContent = 'No hay servicios válidos para agregar';
        errorDiv.classList.remove('d-none');
        return;
    }
    
    console.log(`Servicios a agregar: ${serviciosParaAgregar.length}`);
    
    // AGREGAR SERVICIOS A LA TABLA
    let serviciosAgregadosCount = 0;
    serviciosParaAgregar.forEach(servicio => {
        if (agregarServicioATabla(servicio)) {
            // Agregar al Set de servicios agregados
            serviciosAgregados.add(servicio.id.toString());
            serviciosAgregadosCount++;
            console.log(`✔ Agregado: ${servicio.descripcion} (ID: ${servicio.id})`);
        }
    });
    
    // Cerrar modal si se agregaron servicios
    if (serviciosAgregadosCount > 0) {
        cerrarModalServicios();
        
        // Mostrar mensaje de éxito
Swal.fire({
    icon: 'success',
    toast: true,
    position: 'top-end',
    timerProgressBar: true,
    title: '¡Servicios agregados!',
    text: `Se agregaron ${serviciosAgregadosCount} servicio(s) correctamente`,
    timer: 2000,
    showConfirmButton: false,
    
    // Colores principales
    background: 'linear-gradient(90deg, #4CAF50 0%, #45a049 100%)',
    color: '#ffffff',
    iconColor: '#c8e6c9',
    
    // Función para aplicar estilos adicionales
    didOpen: (toast) => {
        // Barra de progreso
        const progressBar = toast.querySelector('.swal2-timer-progress-bar');
        if (progressBar) {
            progressBar.style.background = 'linear-gradient(90deg, #ff4081 0%, #f50057 100%)';
            progressBar.style.height = '4px';
        }
        
        // Icono
        const icon = toast.querySelector('.swal2-icon');
        if (icon) {
            icon.style.borderColor = 'rgba(255,255,255,0.3)';
        }
        
        // Contenedor
        const popup = toast.querySelector('.swal2-popup');
        if (popup) {
            popup.style.borderRadius = '25px';
            popup.style.boxShadow = '0 6px 25px rgba(76, 175, 80, 0.3)';
            popup.style.border = '1px solid rgba(255,255,255,0.2)';
        }
    }
});
        
        // Actualizar el modal para reflejar que ya no están disponibles
        setTimeout(() => {
            cargarServiciosModal();
        }, 300);
    }
    
    console.log('=== FIN seleccionarServicio() ===');
}

// Función para limpiar solo servicios (no toda la factura)
function limpiarServicios() {
    Swal.fire({
        title: '¿Limpiar solo servicios?',
        text: 'Esto eliminará todos los servicios pero mantendrá los datos del cliente',
        icon: 'question',
        showCancelButton: true,
		confirmButtonText: '<i class="fas fa-broom mr-2"></i> Sí, limpiar servicios',
		cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancelar',
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: 'var(--win-accent)'
    }).then((result) => {
        if (result.isConfirmed) {
            // Limpiar tabla de servicios
            document.getElementById('serviciosBody').innerHTML = '';
            
            // Limpiar Set de servicios agregados
            serviciosAgregados.clear();
            
            // Reiniciar índice
            filaIndex = 0;
            
            // Actualizar totales
            calcularTotales();
            
            // Recargar modal
            cargarServiciosModal();
            
            Swal.fire({
                icon: 'success',
                title: 'Servicios limpiados',
                text: 'Se han eliminado todos los servicios',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// Función para validar servicios - MODIFICADA PARA INCLUIR IMPORTE EN LETRAS
function validarServicios() {
    const filas = document.querySelectorAll('#serviciosBody tr');
    
    if (filas.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No hay servicios',
            text: 'dAgrega al menos un servicio a la <?php echo strtolower($tipo_documento); ?>',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)'
        });
        return;
    }
    
    let serviciosValidos = 0;
    let serviciosInvalidos = [];
    
    filas.forEach((fila, index) => {
        const cantidad = parseInt(fila.querySelector('.cantidad').value) || 0;
        const servicio = fila.querySelector('strong').textContent;
        
        if (cantidad > 0) {
            serviciosValidos++;
        } else {
            serviciosInvalidos.push(servicio);
        }
    });
    
    // Obtener importe en letras
    const totalNumero = parseFloat(document.getElementById('totalGeneralInput').value) || 0;
    const importeLetras = convertirNumeroALetras(totalNumero, 'PESOS');
    
    if (serviciosInvalidos.length === 0) {
        Swal.fire({
            icon: 'success',
            title: '<i class="fas fa-check-circle me-2"></i>¡Validación exitosa!',
            html: `
                <div class="text-start">
                    <p>Todos los servicios seleccionados están correctamente validados:</p>
                    
                    <div class="mb-3 p-3 rounded" style="background: var(--win-bg-tertiary);">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Total servicios:</small>
                                <strong>${serviciosValidos}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Subtotal:</small>
                                <strong>${document.getElementById('subtotalDisplay').textContent}</strong>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top" style="border-color: var(--win-border-color) !important;">
                                <small class="text-muted d-block">Total general:</small>
                                <strong class="text-primary fs-5">${document.getElementById('totalGeneralDisplay').textContent}</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success p-3" style="background: rgba(25, 135, 84, 0.1); color: #198754; border: 1px solid rgba(25, 135, 84, 0.3);">
                        <h6 class="mb-2"><i class="fas fa-font me-2"></i>Importe en letras:</h6>
                        <p class="mb-0 text-uppercase fw-bold text-warning" style="font-size: 0.9rem;">
                            ${importeLetras}
                        </p>
                    </div>
                </div>
            `,
            background: 'var(--win-bg-secondary)',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            color: 'var(--win-text-primary)',
            width: 600
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Validación fallida',
            html: `
                <div class="text-start">
                    <p>Los siguientes servicios tienen cantidad inválida:</p>
                    <div class="alert alert-danger p-3">
                        <ul class="mb-0">
                            ${serviciosInvalidos.map(s => `<li><strong>${s}</strong></li>`).join('')}
                        </ul>
                    </div>
                    <p>Por favor, corrige las cantidades antes de continuar.</p>
                </div>
            `,
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)'
        });
    }
}

// Función para mostrar resumen - MODIFICADA PARA INCLUIR IMPORTE EN LETRAS
function mostrarResumen() {
    const filas = document.querySelectorAll('#serviciosBody tr');
    
    if (filas.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No hay servicios',
            text: 'Agrega al menos un servicio para ver el resumen',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            confirmButtonText: '<i class="fas fa-plus me-2"></i>Agregar servicio',
            confirmButtonColor: 'var(--win-accent)'
        }).then((result) => {
            if (result.isConfirmed) {
                agregarServicio();
            }
        });
        return;
    }
    
    const total = document.getElementById('totalGeneralDisplay').textContent;
    const totalNumero = parseFloat(document.getElementById('totalGeneralInput').value) || 0;
    const cliente = document.getElementById('clienteNombre').textContent;
    const servicios = document.querySelectorAll('#serviciosBody tr').length;
    
    // Obtener el importe en letras
    const importeLetras = convertirNumeroALetras(totalNumero, 'PESOS');
     const tipo = '<?php echo $tipo_documento; ?>';
	 
    Swal.fire({
        title: `<i class="fas fa-file-invoice me-2"></i> Resumen de ${tipo}`,
        html: `
            <div class="text-start" style="font-size: 0.95rem;">
                <div class="mb-3 border-bottom pb-2" style="border-color: var(--win-border-color) !important;">
                    <h6><i class="fas fa-user me-2" style="color: var(--win-accent);"></i>Cliente:</h6>
                    <p class="ps-4 mb-2" style="color: var(--win-text-primary);">${cliente}</p>
                </div>
                
                <div class="mb-3 border-bottom pb-2" style="border-color: var(--win-border-color) !important;">
                    <h6><i class="fas fa-list me-2" style="color: var(--win-accent);"></i>Servicios:</h6>
                    <p class="ps-4 mb-2" style="color: var(--win-text-primary);">
                        <span class="badge bg-primary">${servicios}</span> servicio(s) agregado(s)
                    </p>
                </div>
                
                <div class="mb-3">
                    <h6><i class="fas fa-money-bill-wave me-2" style="color: var(--win-accent);"></i>Total en números:</h6>
                    <p class="ps-4 display-6 text-primary fw-bold mb-2">${total}</p>
                    
                    <h6><i class="fas fa-font me-2" style="color: var(--win-accent);"></i>Importe en letras:</h6>
                    <div class="ps-4 p-3 rounded" style="background: var(--win-bg-tertiary); border-left: 3px solid var(--win-accent);">
                        <p class="mb-0 text-uppercase fw-bold" style="color: var(--win-text-primary); font-size: 0.9rem;">
                            <i class="fas fa-quote-left me-2"></i>
                            ${importeLetras}
                            <i class="fas fa-quote-right ms-2"></i>
                        </p>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3 py-2" style="background: var(--win-accent-light); color: var(--win-accent); border: 1px solid var(--win-accent);">
    <i class="fas fa-info-circle me-2"></i>
    Revisa que todos los datos sean correctos antes de guardar la 
    <strong><?php echo strtolower($tipo_documento); ?></strong>.
    <?php if ($tipo_documento === 'OFERTA'): ?>
    <br><small class="text-muted">Nota: Las ofertas no tienen valor fiscal.</small>
    <?php endif; ?>
</div>
            </div>
        `,
        width: 600,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        confirmButtonText: '<i class="fas fa-arrow-right me-2"></i>Continuar',
        confirmButtonColor: 'var(--win-accent)',
        showCloseButton: true,
        customClass: {
            popup: 'border-win shadow-lg'
        }
    });
}
// Función auxiliar para cerrar el modal de servicios
function cerrarModalServicios() {
    const modalElement = document.getElementById('servicioModal');
    const modalInstance = bootstrap.Modal.getInstance(modalElement);
    if (modalInstance) {
        modalInstance.hide();
    }
    
    // Limpiar backdrop
    setTimeout(() => {
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        const backdrops = document.getElementsByClassName('modal-backdrop');
        while (backdrops.length > 0) {
            backdrops[0].parentNode.removeChild(backdrops[0]);
        }
    }, 100);
    
    // Limpiar selección en el modal
    serviciosSeleccionadosModal.clear();
    document.getElementById('checkAll').checked = false;
    document.getElementById('checkAll').indeterminate = false;
}


	   // Cambiar cantidad
        function cambiarCantidad(index, cambio) {
            const fila = document.getElementById('fila_' + index);
            if (fila) {
                const cantidadInput = fila.querySelector('.cantidad');
                let cantidadActual = parseInt(cantidadInput.value) || 1;
                
                // Aplicar cambio
                cantidadActual += cambio;
                
                // Asegurar que no sea menor a 1
                if (cantidadActual < 1) {
                    cantidadActual = 1;
                }
                
                cantidadInput.value = cantidadActual;
                validarCantidadEntera(index);
                actualizarLinea(index);
            }
        }
        
        // Validar cantidad entera
        function validarCantidadEntera(index) {
            const fila = document.getElementById('fila_' + index);
            if (fila) {
                const cantidadInput = fila.querySelector('.cantidad');
                let cantidad = cantidadInput.value;
                
                // Remover caracteres no numéricos
                cantidad = cantidad.replace(/[^\d]/g, '');
                
                // Convertir a entero
                let cantidadEntera = parseInt(cantidad);
                
                // Validaciones
                if (isNaN(cantidadEntera) || cantidadEntera < 1) {
                    cantidadEntera = 1;
                    cantidadInput.classList.add('is-invalid');
                } else {
                    cantidadInput.classList.remove('is-invalid');
                }
                
                // Actualizar valor
                cantidadInput.value = cantidadEntera;
                actualizarLinea(index);
            }
        }
        
        // Actualizar línea de servicio
        function actualizarLinea(index) {
            const fila = document.getElementById('fila_' + index);
            if (fila) {
                const cantidad = parseInt(fila.querySelector('.cantidad').value) || 0;
                const precio = parseFloat(fila.querySelector('.precio').value) || 0;
                const totalLinea = cantidad * precio;
                
                fila.querySelector('.total-linea').textContent = '$' + totalLinea.toFixed(2);
                calcularTotales();
            }
        }
        
// Eliminar fila - ACTUALIZADO para remover del Set
function eliminarFila(index) {
    const fila = document.getElementById('fila_' + index);
    if (fila) {
        // Obtener el ID del servicio antes de eliminarlo
        const servicioIdInput = fila.querySelector('input[name="servicio_id[]"]');
        if (servicioIdInput) {
            const servicioId = servicioIdInput.value;
            console.log(`Eliminando servicio ID: ${servicioId} de la lista de agregados`);
            
            // Remover del Set de servicios agregados
            serviciosAgregados.delete(servicioId);
            
            // Actualizar el modal para que este servicio vuelva a estar disponible
            setTimeout(() => {
                cargarServiciosModal();
            }, 300);
        }
        
        // Animación y eliminación
        fila.classList.add('animate__fadeOut');
        
        setTimeout(() => {
            fila.remove();
            
            // Renumerar filas
            document.querySelectorAll('#serviciosBody tr').forEach((fila, i) => {
                fila.querySelector('td:first-child').textContent = i + 1;
                // Actualizar el ID de la fila
                fila.id = 'fila_' + i;
                
                // Actualizar eventos de botones
                const botones = fila.querySelectorAll('button[onclick*="cambiarCantidad"]');
                botones.forEach(boton => {
                    const oldOnClick = boton.getAttribute('onclick');
                    const newOnClick = oldOnClick.replace(/cambiarCantidad\(\d+,/g, `cambiarCantidad(${i},`);
                    boton.setAttribute('onclick', newOnClick);
                });
                
                // Actualizar eventos de inputs
                const inputs = fila.querySelectorAll('input[onchange*="actualizarLinea"], input[onblur*="validarCantidadEntera"]');
                inputs.forEach(input => {
                    const oldOnChange = input.getAttribute('onchange');
                    const oldOnBlur = input.getAttribute('onblur');
                    if (oldOnChange) {
                        input.setAttribute('onchange', oldOnChange.replace(/actualizarLinea\(\d+\)/g, `actualizarLinea(${i})`));
                    }
                    if (oldOnBlur) {
                        input.setAttribute('onblur', oldOnBlur.replace(/validarCantidadEntera\(\d+\)/g, `validarCantidadEntera(${i})`));
                    }
                });
                
                // Actualizar botón de eliminar
                const btnEliminar = fila.querySelector('button[onclick*="eliminarFila"]');
                if (btnEliminar) {
                    btnEliminar.setAttribute('onclick', `eliminarFila(${i})`);
                }
            });
            
            // Actualizar índice global si es necesario
            filaIndex = document.querySelectorAll('#serviciosBody tr').length;
            
            calcularTotales();
            limpiarErrores();
        }, 300);
    }
}

// Función para actualizar información detallada de servicios
function actualizarInfoServiciosDetallada() {
    const filas = document.querySelectorAll('#serviciosBody tr');
    const numServicios = filas.length;
    let totalUnidades = 0;
    let subtotal = 0;
    
    // Calcular total de unidades y subtotal
    filas.forEach(fila => {
        const cantidad = parseInt(fila.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(fila.querySelector('.precio').value) || 0;
        
        totalUnidades += cantidad;
        if (cantidad > 0 && precio > 0) {
            subtotal += cantidad * precio;
        }
    });
}
// Función para actualizar contadores de servicios
function actualizarContadoresServicios() {
    const numServicios = document.querySelectorAll('#serviciosBody tr').length;
    const subtotal = document.getElementById('subtotalDisplay').textContent;
    
    // Actualizar badge en header
    document.getElementById('contadorServicios').textContent = `${numServicios} servicios`;
	
    
    // Actualizar info en footer del card
    document.getElementById('numServiciosInfo').textContent = numServicios;


    // Obtener botón "Ver Resumen Detallado"
    const btnResumen = document.querySelector('button[onclick="mostrarResumen()"]');
	
	
    // Habilitar/deshabilitar botones según haya servicios
    const btnLimpiar = document.getElementById('btnLimpiarHeader');
    const btnValidar = document.getElementById('btnValidarHeader');
    
    if (btnLimpiar) btnLimpiar.disabled = numServicios === 0;
    if (btnValidar) btnValidar.disabled = numServicios === 0;
    if (btnResumen) {
        if (numServicios === 0) {
            btnResumen.disabled = true;
            btnResumen.classList.remove('btn-outline-info');
            btnResumen.classList.add('btn-outline-secondary');
            btnResumen.setAttribute('title', 'Agrega servicios para ver el resumen');
        } else {
            btnResumen.disabled = false;
            btnResumen.classList.remove('btn-outline-secondary');
            btnResumen.classList.add('btn-outline-info');
            btnResumen.setAttribute('title', 'Ver resumen detallado de la factura');
        }
	 }
	 
    // Cambiar color del badge según cantidad
    const badge = document.getElementById('contadorServicios');
    if (numServicios === 0) {
        badge.className = 'badge bg-secondary fw-bold text-dark ms-2';
    } else if (numServicios < 3) {
        badge.className = 'badge bg-warning fw-bold text-dark ms-2';
    } else {
        badge.className = 'badge bg-success fw-bold text-dark ms-2';
    }
}

// Reemplazar la función calcularTotales existente
function calcularTotales() {
    let subtotal = 0;
    let serviciosValidos = 0;
    let totalUnidadesItems = 0; // Variable para sumar todas las cantidades
    
    document.querySelectorAll('#serviciosBody tr').forEach(fila => {
        const cantidadInput = fila.querySelector('.cantidad');
        const precioInput = fila.querySelector('.precio');
        
        // Validar que los inputs existan
        if (cantidadInput && precioInput) {
            const cantidad = parseInt(cantidadInput.value) || 0;
            const precio = parseFloat(precioInput.value) || 0;
            
            if (cantidad > 0) {
                totalUnidadesItems += cantidad; // Sumar al total de items
                
                if (precio > 0) {
                    const totalLinea = cantidad * precio;
                    subtotal += totalLinea;
                    serviciosValidos++;
                }
            }
        }
    });
    
    const totalGeneral = subtotal;
    
    // 1. Actualizar displays numéricos
    document.getElementById('subtotalDisplay').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('totalGeneralDisplay').textContent = '$' + totalGeneral.toFixed(2);
    
    // 2. Actualizar inputs ocultos
    document.getElementById('subtotalInput').value = subtotal.toFixed(2);
    document.getElementById('totalGeneralInput').value = totalGeneral.toFixed(2);
    
    // 3. Actualizar contadores de items dinámicos
    const totalItemsInfo = document.getElementById('totalItemsInfo');
    if (totalItemsInfo) {
        // Animación simple de cambio
        totalItemsInfo.style.opacity = '0.5';
        totalItemsInfo.textContent = totalUnidadesItems;
        setTimeout(() => totalItemsInfo.style.opacity = '1', 200);
    }
    // 4. Actualizar Importe en Letras (Card y Footer)
    const textoLetras = convertirNumeroALetras(totalGeneral, 'PESOS');
    
    const cardLetras = document.getElementById('importeLetrasCard');
    if (cardLetras) cardLetras.textContent = textoLetras;
    
    const footerLetras = document.getElementById('importeLetrasFooter');
    if (footerLetras) footerLetras.textContent = textoLetras;
    
    // 5. Actualizar contadores generales de servicios (distintos)
    actualizarContadoresServicios();
    
    return { subtotal, totalGeneral, serviciosValidos };
}



// Llamar a actualizarContadoresServicios cuando se agregue o elimine un servicio
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar contadores
    actualizarContadoresServicios();
    
    // Observar cambios en la tabla de servicios
    const observer = new MutationObserver(function(mutations) {
        actualizarContadoresServicios();
    });
    
    const serviciosBody = document.getElementById('serviciosBody');
    if (serviciosBody) {
        observer.observe(serviciosBody, { childList: true, subtree: true });
    }
});
        
        // Limpiar errores
        function limpiarErrores() {
            document.getElementById('errorServicios').classList.add('d-none');
            document.getElementById('errorServicios').innerHTML = '';
        }
        
        function validarFactura() {
            limpiarErrores();
            
            const errorDiv = document.getElementById('errorServicios');
            const errorContent = document.getElementById('errorContent') || errorDiv;
            let errores = [];
            
// 1. Validar fecha
if (!validarFechaSeleccionada()) {
    const [anioInicio, mesInicio, diaInicio] = FECHA_OPERATIVA_INICIO.split('-');
    const [anioFin, mesFin, diaFin] = FECHA_OPERATIVA_FIN.split('-');
    
    errores.push(`La fecha de emisión de la <?php echo $tipo_documento; ?> debe estar entre ${diaInicio}/${mesInicio}/${anioInicio} y ${diaFin}/${mesFin}/${anioFin}`);
}
            
            // 2. Validar cliente
            const clienteId = document.getElementById('clienteSelect').value;
            if (!clienteId || clienteId == "0") {
                errores.push('Debe seleccionar un cliente');
            }
            
            // 3. Validar tipo de pago
            const tipoPago = document.querySelector('select[name="tipo_pago_id"]').value;
            if (!tipoPago || tipoPago == "0") {
                errores.push('Debe seleccionar un tipo de pago');
            }

		// 4. Validar que si el estado es PAGADA, la fecha de pago no sea anterior a la fecha de emisión
    const estadoSeleccionado = document.getElementById('estadoSelect').value;
    if (estadoSeleccionado === 'PAGADA') {
        const fechaEmision = document.getElementById('fechaEmision').value;
        
        // Verificar si hay información de pago temporal (cuando se guarda la factura por primera vez)
        if (window.infoPagoTemporal) {
            const fechaPago = window.infoPagoTemporal.fecha_pago;
            if (fechaPago && fechaPago < fechaEmision) {
                errores.push(`La fecha de pago (${fechaPago}) no puede ser anterior a la fecha de emisión (${fechaEmision})`);
            }
        }
	}
            // 4. Validar servicios
            const filas = document.querySelectorAll('#serviciosBody tr');
            if (filas.length === 0) {
                errores.push(`Debe agregar al menos un servicio a la <?php echo strtolower($tipo_documento); ?>`);
            }
            
            let serviciosValidos = 0;
            let serviciosDuplicados = new Set();
            let idsVistos = new Set();
            let serviciosInvalidos = [];

            document.querySelectorAll('#serviciosBody tr').forEach((fila, index) => {
                const cantidadInput = fila.querySelector('.cantidad');
                const cantidad = parseInt(cantidadInput.value) || 0;
                const servicioIdInput = fila.querySelector('input[name="servicio_id[]"]');
                const servicioId = servicioIdInput ? servicioIdInput.value : null;
                const nombreServicio = fila.querySelector('strong') ? fila.querySelector('strong').textContent : `Servicio ${index + 1}`;
                
                // Validar que el servicio_id no sea 0 o vacío
                if (!servicioId || servicioId == "0" || servicioId == "") {
                    serviciosInvalidos.push(`El servicio "${nombreServicio}" tiene un ID inválido`);
                }
                
                if (cantidad <= 0) {
                    errores.push(`La cantidad para "${nombreServicio}" debe ser mayor a 0`);
                    cantidadInput.classList.add('is-invalid');
                }

                if (servicioId && servicioId != "0") {
                    if (idsVistos.has(servicioId)) {
                        serviciosDuplicados.add(nombreServicio);
                    }
                    idsVistos.add(servicioId);
                }
                
                if (cantidad > 0 && servicioId && servicioId != "0") {
                    serviciosValidos++;
                }
            });
            
            if (serviciosInvalidos.length > 0) {
                errores = errores.concat(serviciosInvalidos);
            }
            
            if (serviciosDuplicados.size > 0) {
                errores.push('Servicios duplicados: ' + Array.from(serviciosDuplicados).join(', '));
            }

            if (serviciosValidos === 0 && filas.length > 0) {
                errores.push('Ninguno de los servicios tiene una cantidad válida o tiene un ID inválido');
            }

            // --- MOSTRAR ERRORES SI EXISTEN ---
            if (errores.length > 0) {
                const listaHtml = `
                    <ul class="mb-0 text-start">
                        ${errores.map(err => `<li>${err}</li>`).join('')}
                    </ul>`;

                errorDiv.classList.remove('d-none');
                if(document.getElementById('errorContent')){
                    document.getElementById('errorContent').innerHTML = `<strong><i class="fas fa-exclamation-circle me-2"></i>Errores de validación:</strong>${listaHtml}`;
                } else {
                    errorDiv.innerHTML = `<strong>Errores:</strong>${listaHtml}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Atención',
                    html: `<div class="text-start">Se encontraron los siguientes errores:<br><br>${listaHtml}</div>`,
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)',
                    confirmButtonColor: 'var(--win-accent)',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido y corregir',
                    showClass: { popup: 'animate__animated animate__shakeX' }
                });

                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

                const btnGuardar = document.getElementById('btnGuardar');
				const tipoDocumento = '<?php echo $tipo_documento; ?>';
				btnGuardar.innerHTML = `<i class="fas fa-save me-1"></i>Guardar ${tipoDocumento}`;
				btnGuardar.disabled = false;
                
                return false;
            }
            
            return true;
        }
        
// Función principal para procesar la factura/oferta - MODIFICADA
function procesarFactura() {
    const btnGuardar = document.getElementById('btnGuardar');
    
    // 1. Validar la factura (misma validación para ambos tipos)
    if (!validarFactura()) {
        return;
    }
    
    // 2. Mostrar loading
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Procesando...';
    btnGuardar.disabled = true;
    
    // 3. Determinar tipo de documento
    const tipoDocumento = '<?php echo $tipo_documento; ?>';
    
    if (tipoDocumento === 'OFERTA') {
        // OFERTA: No guardar en BD, solo generar para impresión inmediata
        generarOfertaParaImpresion();
    } else {
        // FACTURA: Comportamiento normal con BD
        const estadoSeleccionado = document.getElementById('estadoSelect').value;
        
        if (estadoSeleccionado === 'PAGADA') {
            mostrarModalPagoAntesDeGuardar();
        } else {
            guardarFactura();
        }
    }
}
        
// --- FUNCIÓN DEL MODAL DE PAGO (ACTUALIZADA Y CORREGIDA) ---
function mostrarModalPagoAntesDeGuardar() {
    // 1. OBTENCIÓN DE DATOS DEL DOM
    const clienteSelect = document.getElementById('clienteSelect');
    const clienteOption = clienteSelect.options[clienteSelect.selectedIndex];
    const clienteNombre = clienteOption.getAttribute('data-nombre') || clienteOption.text;
    
    // Obtener total y asegurar formato numérico
    const totalInputVal = document.getElementById('totalGeneralInput').value;
    const totalGeneral = parseFloat(totalInputVal || 0);
    
    const noFactura = document.querySelector('input[name="no_fact"]').value;
    
    // Obtener fecha emisión
    const fechaEmisionInput = document.getElementById('fechaEmision');
    const fechaEmisionFactura = fechaEmisionInput ? fechaEmisionInput.value : null;
    
    // Obtener TEXTO del Tipo de Pago seleccionado
    const tipoPagoSelect = document.getElementById('tipoPagoSelect');
    const tipoPagoTexto = tipoPagoSelect.options[tipoPagoSelect.selectedIndex].text;

    // Estilos del tema
    const theme = document.documentElement.getAttribute('data-theme') || 'dark';
    const isDark = theme === 'dark';
    const style = getComputedStyle(document.documentElement);
    const bgColor = style.getPropertyValue('--win-bg-secondary').trim();
    const textColor = style.getPropertyValue('--win-text-primary').trim();
    const accentColor = style.getPropertyValue('--win-accent').trim();

    // Configuración base SweetAlert
    const swalConfig = {
        background: bgColor,
        color: textColor,
        width: '700px',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        customClass: { popup: 'mica-effect border-win' }
    };

    // Helpers de Fecha/Hora
    const ahora = new Date();
    const horaActual = ahora.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    
    function formatearFechaDDMMYYYY(fechaISO) {
        if (!fechaISO) return '';
        const [anio, mes, dia] = fechaISO.split('-');
        return `${dia}/${mes}/${anio}`;
    }
    const fechaEmisionFormateada = formatearFechaDDMMYYYY(fechaEmisionFactura);

    // ============================================================
    // FUNCIÓN 1: MOSTRAR FORMULARIO (RECURSIVA)
    // ============================================================
    const lanzarModalFormulario = (datosPrevios = null) => {
        
        let valorFecha, valorHora, valorRef;

        // Determinar valores iniciales
        if (datosPrevios) {
            valorFecha = datosPrevios.fecha_pago;
            valorHora = datosPrevios.hora_pago;
            valorRef = datosPrevios.referencia_pago;
        } else {
            // Lógica inteligente: Si emisión > hoy operativo, sugerir emisión.
            // Si no, hoy operativo.
            let fechaSugerida = FECHA_OPERATIVA_HOY;
            if (fechaEmisionFactura && fechaEmisionFactura > FECHA_OPERATIVA_HOY) {
                fechaSugerida = fechaEmisionFactura;
            }
            
            valorFecha = fechaSugerida;
            valorHora = horaActual;
            valorRef = '';
        }

        // Definir mínimo
        const fechaMinima = fechaEmisionFactura || FECHA_OPERATIVA_HOY;

        // Función global para el botón HOY dentro del modal
        window.setPagoHoyModal = function() {
            const input = document.getElementById('fechaPago');
            if(input) {
                input.value = FECHA_OPERATIVA_HOY;
                input.dispatchEvent(new Event('input')); // Activar validación
                input.dispatchEvent(new Event('change'));
                input.focus();
            }
        };

        const htmlForm = `
            <div class="text-start" style="color: ${textColor}">
                <div class="mb-3 border-bottom pb-2" style="border-color: var(--win-border-color) !important;">
                    <h6 class="mb-0">Registrar pago para: <span style="color: ${accentColor}">${clienteNombre}</span></h6>
                    
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="opacity-75">Factura: ${noFactura} | Total: <b>$${totalGeneral.toFixed(2)}</b></small>
                        <span class="badge bg-secondary">${tipoPagoTexto}</span>
                    </div>
					<!-- FECHA DE EMISIÓN VISIBLE -->
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="opacity-75">Total: <b class="text-success">$${totalGeneral.toFixed(2)}</b></small>
                        <small style="color: var(--win-text-secondary);">
                            <i class="fas fa-calendar-alt me-1"></i>Emisión: <strong class="text-warning">${fechaEmisionFormateada}</strong>
                        </small>
                    </div>
                </div>
                </div>

                </div>
                
                <div class="row g-2">
                    <!-- COLUMNA FECHA CON CLASE IDENTIFICADORA -->
                    <div class="col-md-6 mb-2 container-fecha">
                        <label class="form-label small fw-bold">Fecha de pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-calendar-check"></i></span>
                            <input type="date" id="fechaPago" class="form-control win-input-dynamic" 
                                   value="${valorFecha}" min="${fechaMinima}"
                                   title="No anterior a emisión">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.setPagoHoyModal()" 
                                    style="border-color: var(--win-border-color); color: var(--win-text-primary);"
                                    title="Establecer fecha operativa actual">
                                <i class="fas fa-calendar-day"></i> Hoy
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">Mínimo: ${fechaEmisionFormateada}</small>
                        <!-- AQUÍ SE INSERTARÁ EL ERROR VISUAL -->
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

        // Renderizar Formulario
        Swal.fire({
            ...swalConfig,
            title: datosPrevios ? 'Corregir Pago' : 'Información de Pago',
            html: htmlForm,
            confirmButtonText: 'Siguiente <i class="fas fa-arrow-right ms-1"></i>',
            cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
            showCancelButton: true,
            didOpen: () => {
                // Inicializar componentes
                mdtimepicker('#horaPago', { timeFormat: 'hh:mm tt', theme: isDark ? 'dark' : 'blue', hourPadding: true });
                
                // Focus en referencia con delay
                setTimeout(() => {
                    const refInput = document.getElementById('refPago');
                    if(refInput) {
                        refInput.focus();
                        if(refInput.value) refInput.setSelectionRange(refInput.value.length, refInput.value.length);
                    }
                }, 100);

                // --- VALIDACIÓN VISUAL DEBAJO DEL INPUT ---
                const fechaPagoInput = document.getElementById('fechaPago');
                
                const validarVisualmente = () => {
                    // Buscar el contenedor padre específico (.container-fecha)
                    const container = fechaPagoInput.closest('.container-fecha');
                    let errorDiv = container.querySelector('.invalid-feedback-custom');

                    // Lógica de validación
                    if (fechaPagoInput.value && fechaEmisionFactura && fechaPagoInput.value < fechaEmisionFactura) {
                        // ES INVÁLIDO
                        fechaPagoInput.classList.add('is-invalid');
                        fechaPagoInput.classList.remove('is-valid');
                        
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback-custom text-danger fw-bold mt-1 animate__animated animate__fadeIn';
                            errorDiv.style.fontSize = '0.85em';
                            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> No puede ser anterior a emisión`;
                            container.appendChild(errorDiv);
                        }
                    } else {
                        // ES VÁLIDO
                        fechaPagoInput.classList.remove('is-invalid');
                        if(fechaPagoInput.value) fechaPagoInput.classList.add('is-valid');
                        
                        if (errorDiv) {
                            errorDiv.remove();
                        }
                    }
                };

                fechaPagoInput.addEventListener('change', validarVisualmente);
                fechaPagoInput.addEventListener('input', validarVisualmente);
                // Validar al abrir
                validarVisualmente();
            },
            preConfirm: () => {
                const f = document.getElementById('fechaPago').value;
                const h = document.getElementById('horaPago').value;
                const r = document.getElementById('refPago').value.trim();
                
                // Validaciones bloqueantes
                if (!f || !h || !r) {
                    Swal.showValidationMessage('Todos los campos son obligatorios');
                    return false;
                }
                if (fechaEmisionFactura && f < fechaEmisionFactura) {
                    Swal.showValidationMessage(`Fecha inválida. Mínimo: ${fechaEmisionFormateada}`);
                    return false;
                }
                
                return { 
                    fecha_pago: f, 
                    hora_pago: h, 
                    referencia_pago: r.toUpperCase() 
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Ir a confirmación
                mostrarConfirmacion(result.value);
            } else {
				// Canceló todo el proceso -> Restaurar botón principal
				const btnGuardar = document.getElementById('btnGuardar');
				const tipoDocumento = '<?php echo $tipo_documento; ?>';
				btnGuardar.disabled = false;
				btnGuardar.innerHTML = `<i class="fas fa-save me-1"></i>Guardar ${tipoDocumento}`;
            }
        });
    };

    // ============================================================
    // FUNCIÓN 2: MOSTRAR CONFIRMACIÓN
    // ============================================================
    const mostrarConfirmacion = (datos) => {
        const fechaMostrar = datos.fecha_pago.split('-').reverse().join('/');
        
        Swal.fire({
            ...swalConfig,
            title: '¿Confirmar Pago?',
            icon: 'warning',
            html: `
                <div class="text-start p-3 rounded" style="background: rgba(128,128,128,0.1); color: ${textColor}; line-height: 1.6;">
                    <p class="mb-1"><b>Cliente:</b> ${clienteNombre}</p>
                    <p class="mb-1"><b>Total:</b> <span class="text-success fw-bold">$${totalGeneral.toFixed(2)}</span></p>
                    <p class="mb-2"><b>Método:</b> <span class="badge bg-secondary">${tipoPagoTexto}</span></p>
                    
                    <div style="border-top: 1px solid var(--win-border-color); padding-top: 10px;">
                        <p class="mb-1 text-uppercase small fw-bold opacity-50">Datos a registrar:</p>
                        <p class="mb-1"><b>Fecha Pago:</b> ${fechaMostrar} ${datos.hora_pago}</p>
                        <p class="mb-0"><b>Referencia:</b> <span style="color: ${accentColor}">${datos.referencia_pago}</span></p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Guardar Factura <i class="fas fa-save ms-1"></i>',
            cancelButtonText: '<i class="fas fa-edit me-1"></i> Corregir', // Botón corregir
            focusConfirm: true
        }).then((res) => {
            if (res.isConfirmed) {
                // GUARDAR: Pasar datos a variable global y enviar formulario
                window.infoPagoTemporal = datos;
                guardarFactura();
            } else if (res.dismiss === Swal.DismissReason.cancel) {
                // CORREGIR: Volver al formulario con los datos
                lanzarModalFormulario(datos);
            }
        });
    };

    // INICIAR FLUJO
    lanzarModalFormulario();
}
    
    // Función para validar fecha de pago en tiempo real
function validarFechaPago() {
    // Función para formatear fecha de YYYY-MM-DD a dd/mm/yyyy
    function formatearFechaDDMMYYYY(fechaISO) {
        if (!fechaISO) return '';
        const [anio, mes, dia] = fechaISO.split('-');
        return `${dia}/${mes}/${anio}`;
    }

    const fechaPagoInput = document.getElementById('fechaPago');
    
    if (fechaPagoInput && fechaPagoInput.value && fechaEmisionFactura) {
        const fechaEmision = new Date(fechaEmisionFactura);
        const fechaPago = new Date(fechaPagoInput.value);
        
        // Formatear fecha de emisión para mostrar en formato dd/mm/yyyy
        const fechaEmisionFormateada = formatearFechaDDMMYYYY(fechaEmisionFactura);
        
        if (fechaPago < fechaEmision) {
            fechaPagoInput.classList.add('is-invalid');
            fechaPagoInput.classList.remove('is-valid');
            
            // Mostrar mensaje de error debajo del input
            const errorMsg = document.createElement('div');
            errorMsg.className = 'invalid-feedback d-block';
            errorMsg.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> 
                                  La fecha de pago no puede ser anterior a ${fechaEmisionFormateada}`;
            
            // Remover mensaje anterior si existe
            const existingError = fechaPagoInput.parentNode.querySelector('.invalid-feedback');
            if (existingError) {
                existingError.remove();
            }
            
            fechaPagoInput.parentNode.appendChild(errorMsg);
        } else {
            fechaPagoInput.classList.remove('is-invalid');
            fechaPagoInput.classList.add('is-valid');
            
            // Remover mensaje de error si existe
            const existingError = fechaPagoInput.parentNode.querySelector('.invalid-feedback');
            if (existingError) {
                existingError.remove();
            }
        }
    }
}
    // Agregar evento de validación en tiempo real
    document.getElementById('fechaPago')?.addEventListener('change', validarFechaPago);
    document.getElementById('fechaPago')?.addEventListener('blur', validarFechaPago);

        
        // Función para guardar la factura (se llama después del modal si es PAGADA, o directamente si no)
        function guardarFactura() {
            const btnGuardar = document.getElementById('btnGuardar');
            
            // Crear FormData del formulario original
            const formData = new FormData(document.getElementById('facturaForm'));
            
            // Si hay información de pago temporal (estado PAGADA), agregarla
            if (window.infoPagoTemporal) {
                formData.append('fecha_pago', window.infoPagoTemporal.fecha_pago);
                formData.append('hora_pago', window.infoPagoTemporal.hora_pago);
                formData.append('referencia_pago', window.infoPagoTemporal.referencia_pago);
            }
            
            // Mostrar mensaje de procesamiento
            Swal.fire({
                title: 'Guardando <?php echo strtolower($tipo_documento); ?>...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar la factura al servidor
            fetch('guardar_factura.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Primero verificar si la respuesta es JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('La respuesta del servidor no es JSON válido');
                }
                return response.json();
            })
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    // Mostrar éxito y redirigir
                    Swal.fire({
                        icon: 'success',
                        title: '¡<?php echo strtolower($tipo_documento); ?> guardada!',
                        text: data.message,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)',
                        confirmButtonColor: 'var(--win-accent)'
                    }).then(() => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else if (data.factura_id) {
                            window.location.href = 'ver_factura.php?id=' + data.factura_id;
                        }
                    });
                } else {
                    // Mostrar error
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error desconocido al guardar la <?php echo strtolower($tipo_documento); ?>',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                    
                    // Re-habilitar botón
                    btnGuardar.innerHTML = `<i class="fas fa-save me-1"></i>Guardar ${tipoDocumento}`;
					btnGuardar.disabled = false;
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    html: `No se pudo conectar con el servidor.<br><small>${error.message}</small>`,
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                });
                
                // Re-habilitar botón
				btnGuardar.innerHTML = `<i class="fas fa-save me-1"></i>Guardar ${tipoDocumento}`;
				btnGuardar.disabled = false;
            });
        }
        
        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                // Para móvil
                sidebar.classList.toggle('open');
            } else {
                // Para desktop (toggle mini)
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
            }
        }
        
        // Prevenir entrada de decimales en los campos de cantidad
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('cantidad')) {
                // Remover cualquier carácter que no sea dígito
                e.target.value = e.target.value.replace(/[^\d]/g, '');
                
                // Si está vacío, establecer 1
                if (e.target.value === '') {
                    e.target.value = '1';
                }
                
                // Actualizar línea
                const fila = e.target.closest('tr');
                if (fila && fila.id.startsWith('fila_')) {
                    const index = parseInt(fila.id.replace('fila_', ''));
                    actualizarLinea(index);
                }
            }
        });
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            
            configurarInputFecha();
			
            // Actualizar info del cliente al seleccionar
            document.getElementById('clienteSelect').addEventListener('change', actualizarInfoCliente);
            
            // Si hay un cliente seleccionado desde la URL, actualizar la info
            const select = document.getElementById('clienteSelect');
            if (select.value) {
                // Forzar el evento change para cargar la información
                setTimeout(() => {
                    select.dispatchEvent(new Event('change'));
                }, 100);
            }
            
            // Asegurar que TRANSFERENCIA BANCARIA (ID 20) esté seleccionada por defecto
            const tipoPagoSelect = document.getElementById('tipoPagoSelect');
            if (tipoPagoSelect.value === '') {
                // Buscar la opción con value="20"
                const optionTransferencia = tipoPagoSelect.querySelector('option[value="20"]');
                if (optionTransferencia) {
                    tipoPagoSelect.value = '20';
                }
            }
            
            /** 
             * LÓGICA DE CONTROL DE MODALES ÚNICA
             */
            <?php if ($mostrar_modal_pago && $factura_guardada): ?>
                // CASO A: Factura recién guardada como PAGADA
                // Prioridad absoluta al registro de pago
                setTimeout(() => {
                    mostrarDialogoInformacionPago(
                        <?php echo $factura_guardada['id']; ?>, 
                        '<?php echo addslashes($factura_guardada['no_fact']); ?>'
                    );
                }, 600);
            <?php else: ?>
                // CASO B: Es una factura nueva desde cero
                // Solo abrimos el buscador si no venimos de guardar una factura
                <?php if (!isset($_GET['factura_guardada'])): ?>
                    setTimeout(() => {
                        agregarServicio();
                    }, 500);
                <?php endif; ?>
            <?php endif; ?>
        });
        
        // Funciones del panel de temas
        let themePanelOpen = false;
        
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
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar panel de temas al hacer clic en overlay
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
            
                        // Cargar servicios en el modal
            cargarServiciosModal();
			
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

 // Función para obtener el primer día del mes OPERATIVO
    function obtenerPrimerDiaMesActual() {
        // En JS los meses son 0-11, restamos 1 al mes de PHP
        return new Date(ANIO_OPERATIVO, MES_OPERATIVO_NUM - 1, 1);
    }

    // Función para formatear fecha como YYYY-MM-DD (sin problemas de zona horaria)
    function formatearFechaInput(fecha) {
        const yyyy = fecha.getFullYear();
        const mm = String(fecha.getMonth() + 1).padStart(2, '0');
        const dd = String(fecha.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

// Función para mostrar error con SweetAlert (CORREGIDA)
function mostrarErrorFecha() {
    // Formatear fechas para mostrar
    const [anioInicio, mesInicio, diaInicio] = FECHA_OPERATIVA_INICIO.split('-');
    const fechaInicioFormateada = `${diaInicio}/${mesInicio}/${anioInicio}`;
    
    // Usar FECHA_OPERATIVA_FIN en lugar de FECHA_OPERATIVA_HOY
    const [anioFin, mesFin, diaFin] = FECHA_OPERATIVA_FIN.split('-');
    const fechaFinFormateada = `${diaFin}/${mesFin}/${anioFin}`;

    Swal.fire({
        icon: 'error',
        title: '<i class="fas fa-calendar-times me-2"></i>Fecha fuera de periodo',
        html: `
            <div style="text-align: left; padding: 10px 0;">
                <p style="margin-bottom: 15px; color: var(--win-text-secondary);">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    La fecha debe estar dentro del periodo operativo configurado.
                </p>
                <div style="background: var(--win-bg-tertiary); padding: 15px; border-radius: 8px; border-left: 4px solid var(--win-accent);">
                    <p style="margin: 5px 0;">
                        <strong style="color: var(--win-text-secondary);">Periodo Abierto:</strong>
                    </p>
                    <p style="margin: 5px 0;">
                        <span style="color: var(--win-accent); font-weight: 600;">
                            <i class="fas fa-calendar-alt me-2"></i>Desde: ${fechaInicioFormateada}
                        </span>
                    </p>
                    <p style="margin: 5px 0;">
                        <span style="color: var(--win-accent); font-weight: 600;">
                            <i class="fas fa-calendar-day me-2"></i>Hasta (Último día): ${fechaFinFormateada}
                        </span>
                    </p>
                    <p style="margin: 5px 0; border-top: 1px solid var(--win-border-color); padding-top:5px; margin-top:5px;">
                        <strong style="color: var(--win-text-secondary);">Mes Operativo:</strong>
                        <span style="color: #4cd964; font-weight: 600; margin-left: 8px;">
                            <i class="fas fa-calendar-check me-2"></i>${MES_OPERATIVO_NOMBRE} ${ANIO_OPERATIVO}
                        </span>
                    </p>
                    <p style="margin: 5px 0; font-size: 0.85rem;">
                        <span style="color: var(--win-text-secondary);">
                            <i class="fas fa-info-circle me-2"></i>Hoy es: <span class="fw-bold text-success">${FECHA_OPERATIVA_HOY.split('-').reverse().join('/')}</span>
                        </span>
                    </p>
                </div>
            </div>`,
        width: "500px",
        padding: "1.5rem",
        confirmButtonText: '<i class="fas fa-redo me-2"></i>Usar fecha actual',
        confirmButtonColor: "var(--win-accent)",
        showClass: { popup: "swal2-show animate__animated animate__fadeInDown" },
        hideClass: { popup: "swal2-hide animate__animated animate__fadeOutUp" }
    }).then((result) => {
        if (result.isConfirmed) {
            setFechaHoy('fechaEmision');
        }
    });
}

// Función para validar fecha seleccionada (actualizada)
function validarFechaSeleccionada(mostrarAlert = true) {
    const inputFecha = document.getElementById('fechaEmision');
    const valorFecha = inputFecha.value; // El formato siempre es YYYY-MM-DD

    if (!valorFecha) return false;

    // Comparación robusta de cadenas ISO (YYYY-MM-DD)
    const esInvalida = (valorFecha < FECHA_OPERATIVA_INICIO || valorFecha > FECHA_OPERATIVA_FIN);

    if (esInvalida) {
        inputFecha.classList.add('is-invalid');
        inputFecha.classList.remove('is-valid');
        
        if (mostrarAlert) {
            // Pequeño delay para asegurar que el valor se haya asentado
            setTimeout(() => {
                mostrarErrorFecha();
            }, 100);
        }
        return false;
    } else {
        inputFecha.classList.remove('is-invalid');
        inputFecha.classList.add('is-valid');
        return true;
    }
}

// Función para configurar el input fecha - VERSIÓN MÁS LIMPIA
function configurarInputFecha() {
    const inputFecha = document.getElementById('fechaEmision');
    
    inputFecha.min = FECHA_OPERATIVA_INICIO;
    inputFecha.max = FECHA_OPERATIVA_FIN;
    
    // Al cambiar la fecha, validar y actualizar número
    inputFecha.addEventListener('change', function() {
        if (validarFechaSeleccionada(true)) {
            actualizarNumeroFactura();
        }
    });
    
    validarFechaSeleccionada(false);
}
    // Función para establecer la fecha de "hoy" (Operativa)
    function setFechaHoy(elementId) {
        const input = document.getElementById(elementId);
        
        // Usamos la constante global generada por PHP
        input.value = FECHA_OPERATIVA_HOY;
        
        // Disparar eventos y foco
        input.dispatchEvent(new Event('change'));
        input.focus();
    }

// Variable global para guardar las opciones
let opcionesCache = [];

document.addEventListener('DOMContentLoaded', function() {
    // 1. Guardar copia de las opciones al cargar la página
    const select = document.getElementById('clienteSelect');
    opcionesCache = Array.from(select.options);
});
// Variable global para guardar las opciones ORIGINALES completas
let opcionesCacheCompletas = [];

document.addEventListener('DOMContentLoaded', function() {
    // 1. Guardar copia COMPLETA de las opciones (incluyendo todos los atributos data-*)
    const select = document.getElementById('clienteSelect');
    opcionesCacheCompletas = Array.from(select.options).map(opt => opt.cloneNode(true));
    
    // También guardar la referencia a la primera opción (vacía) para preservarla
    if (opcionesCacheCompletas.length > 0 && opcionesCacheCompletas[0].value === "") {
        window.opcionVaciaCliente = opcionesCacheCompletas[0].cloneNode(true);
    }
});
function filtrarComboCliente(texto) {
    const select = document.getElementById('clienteSelect');
    const busqueda = texto.toLowerCase().trim();
    
    // Guardar el valor actualmente seleccionado (YA NO LO USAREMOS PARA RESTAURAR)
    // const valorSeleccionado = select.value;  <-- COMENTADO, ya no lo usamos
    
    // LIMPIAR completamente el select
    select.innerHTML = '';
    
    // SIEMPRE agregar la opción vacía primero
    if (window.opcionVaciaCliente) {
        select.appendChild(window.opcionVaciaCliente.cloneNode(true));
    } else {
        // Fallback: crear opción vacía
        const opcionVacia = document.createElement('option');
        opcionVacia.value = '';
        opcionVacia.textContent = 'Seleccione un cliente';
        select.appendChild(opcionVacia);
    }
    
    // Si la búsqueda está vacía, agregar TODAS las opciones
    if (busqueda === '') {
        opcionesCacheCompletas.forEach(opcion => {
            // No duplicar la opción vacía
            if (opcion.value !== "") {
                select.appendChild(opcion.cloneNode(true));
            }
        });
    } else {
        // Si hay búsqueda, filtrar
        opcionesCacheCompletas.forEach(opcion => {
            // Saltar la opción vacía (ya la agregamos)
            if (opcion.value === "") return;
            
            const textoVisible = (opcion.textContent || '').toLowerCase();
            const codigo = (opcion.getAttribute('data-codigo') || '').toLowerCase();
            const contrato = (opcion.getAttribute('data-contratono') || '').toLowerCase();
            const nit = (opcion.getAttribute('data-nit') || '').toLowerCase();
            
            // Buscar en TODOS los campos relevantes
            if (textoVisible.includes(busqueda) || 
                codigo.includes(busqueda) || 
                contrato.includes(busqueda) || 
                nit.includes(busqueda)) {
                select.appendChild(opcion.cloneNode(true));
            }
        });
    }
    
    // --- SELECCIONAR EL PRIMER CLIENTE DESPUÉS DEL FILTRO ---
    // Si hay más de 1 opción (la opción vacía + al menos 1 cliente)
    if (select.options.length > 1) {
        // Seleccionar el PRIMER CLIENTE (índice 1, saltando la opción vacía)
        select.selectedIndex = 1;
        
        // Disparar evento change para actualizar el card con el cliente seleccionado
        setTimeout(() => {
            select.dispatchEvent(new Event('change'));
        }, 50);
    } else {
        // Si no hay clientes en el filtro, seleccionar la opción vacía
        select.selectedIndex = 0;
        
        // Disparar evento change para limpiar el card
        setTimeout(() => {
            select.dispatchEvent(new Event('change'));
        }, 50);
    }
}



// FUNCIÓN MEJORADA para limpiar filtro
function limpiarFiltroCliente() {
    const input = document.getElementById('inputFiltroCliente');
    input.value = '';
    
    // Restaurar TODAS las opciones
    filtrarComboCliente('');
    
    // NO seleccionar ninguna opción por defecto, mantener la selección actual
    // o dejar en opción vacía
    const select = document.getElementById('clienteSelect');
    if (select.options.length > 0 && !select.value) {
        select.selectedIndex = 0;
    }
    
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
</script>
<!-- Modal de selección de tipo de documento -->
<div class="modal fade" id="tipoDocumentoModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
        <div class="modal-content" style="background-color: var(--win-bg-secondary); border: 1px solid var(--win-border-color); color: var(--win-text-primary); border-radius: 12px;">
            
            <div class="modal-header" style="border-bottom: 1px solid var(--win-border-color);">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2" style="color: var(--win-accent);"></i>
                    Seleccionar tipo de documento
                </h5>
                <!---<button type="button" class="btn-close bg-success" data-bs-dismiss="modal" aria-label="Close"></button>--->
            </div>
            
            <div class="modal-body p-4">
                <p class="text-center mb-4" style="color: var(--win-text-secondary);">
                    ¿Qué tipo de documento deseas crear?
                </p>
                
                <div class="d-flex flex-column gap-3">
                    <!-- Opción FACTURA -->
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center gap-3 p-3" 
                            onclick="seleccionarTipoDocumento('FACTURA')"
                            style="border: 2px solid var(--win-accent); border-radius: 10px; transition: all 0.3s ease;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-file-invoice fa-2x" style="color: var(--win-accent); width: 40px;"></i>
                        <div class="text-start flex-grow-1">
                            <span class="fw-bold d-block" style="font-size: 1.2rem;">FACTURA</span>
                            <small class="text-muted">Documento fiscal para cobro de servicios</small>
                        </div>
                        <i class="fas fa-chevron-right" style="color: var(--win-accent);"></i>
                    </button>
                    
                    <!-- Opción OFERTA -->
                    <button type="button" class="btn btn-outline-success btn-lg w-100 d-flex align-items-center gap-3 p-3" 
                            onclick="seleccionarTipoDocumento('OFERTA')"
                            style="border: 2px solid #28a745; border-radius: 10px; transition: all 0.3s ease;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-tag fa-2x" style="color: #28a745; width: 40px;"></i>
                        <div class="text-start flex-grow-1">
                            <span class="fw-bold d-block" style="font-size: 1.2rem;">OFERTA</span>
                            <small class="text-muted">Propuesta comercial sin valor fiscal</small>
                        </div>
                        <i class="fas fa-chevron-right" style="color: #28a745;"></i>
                    </button>
<button type="button" class="btn btn-outline-warning btn-lg w-40 d-flex align-items-center gap-3 p-3" 
        onclick="window.location.href='dashboard.php'"
        style="border: 2px solid yellow; border-radius: 10px; transition: all 0.3s ease; cursor: pointer;"
        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
    <i class="fas fa-chevron-left" style="color: #28a745;"></i>
    <i class="fas fa-home fa-2x text-warning" style="width: 40px;"></i>
    <div class="text-start flex-grow-1">
        <span class="fw-bold d-block" style="font-size: 1.2rem;">REGRESAR</span>
        <small class="d-block">Cancelar y Regresar al Inicio</small>
    </div>
</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-close-custom {
        background: transparent;
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.7;
        transition: all 0.3s ease;
    }
    .btn-close-custom:hover {
        opacity: 1;
        transform: rotate(90deg) scale(1.2);
    }
</style>
<script>
// Función para mostrar el modal de selección de tipo de documento
function mostrarModalTipoDocumento() {
    // Solo mostrar si no hay tipo definido en la URL
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('tipo')) {
        const modal = new bootstrap.Modal(document.getElementById('tipoDocumentoModal'));
        modal.show();
    }
}

// Función para seleccionar el tipo de documento y recargar la página
function seleccionarTipoDocumento(tipo) {
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('tipoDocumentoModal'));
    modal.hide();
    
    // Mostrar loading
    Swal.fire({
        title: 'Preparando...',
        text: `Cargando ${tipo.toLowerCase()}`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Redirigir con el tipo seleccionado
    setTimeout(() => {
        window.location.href = 'nueva_factura.php?tipo=' + tipo;
    }, 500);
}

// Mostrar el modal al cargar la página si no hay tipo definido
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('tipo')) {
        setTimeout(() => {
            mostrarModalTipoDocumento();
        }, 500);
    }
});
// Función para confirmar cambio desde el dropdown
function confirmarCambioTipoDropdown(nuevoTipo) {
    // Obtener el tipo actual desde PHP
    const tipoActual = '<?php echo $tipo_documento; ?>';
    
    // Si es el mismo tipo, no preguntar (solo recargar la misma página)
    if (tipoActual === nuevoTipo) {
        window.location.href = 'nueva_factura.php?tipo=' + nuevoTipo;
        return false;
    }
    
    // Verificar si hay servicios agregados
    const filas = document.querySelectorAll('#serviciosBody tr');
    const tieneServicios = filas.length > 0;
    
    // Verificar si hay cliente seleccionado
    const clienteSelect = document.getElementById('clienteSelect');
    const tieneCliente = clienteSelect && clienteSelect.value && clienteSelect.value !== "";
    
    // Verificar observaciones
    const observaciones = document.getElementById('observacionesFactura');
    const tieneObservaciones = observaciones && observaciones.value.trim() !== "";
    
    // Si no hay datos, redirigir directamente
    if (!tieneServicios && !tieneCliente && !tieneObservaciones) {
        window.location.href = 'nueva_factura.php?tipo=' + nuevoTipo;
        return false;
    }
    
    // Mostrar SweetAlert de confirmación
    Swal.fire({
        title: '<i class="fas fa-exchange-alt me-2"></i>¿Cambiar tipo de documento?',
        html: `
            <div class="text-start">
                <p class="mb-3" style="color: var(--win-text-secondary);">
                    Estás cambiando de <strong class="text-${tipoActual === 'FACTURA' ? 'primary' : 'success'}">${tipoActual}</strong> 
                    a <strong class="text-${nuevoTipo === 'FACTURA' ? 'primary' : 'success'}">${nuevoTipo}</strong>.
                </p>
                
                <div class="alert alert-warning py-2 px-3 mb-3" style="background: rgba(255, 193, 7, 0.1); border: 1px solid #ffc107;">
                    <i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>
                    <span class="fw-bold text-light">Se perderán los siguientes datos no guardados:</span>
                </div>
                
                <ul class="mb-3" style="list-style: none; padding-left: 0;">
                    ${tieneServicios ? `<li><i class="fas fa-list me-2 text-danger"></i>${filas.length} servicio(s) agregado(s)</li>` : ''}
                    ${tieneCliente ? `<li><i class="fas fa-user me-2 text-danger"></i>Cliente seleccionado</li>` : ''}
                    ${tieneObservaciones ? `<li><i class="fas fa-comment me-2 text-danger"></i>Observaciones escritas</li>` : ''}
                </ul>
                
                <div class="alert alert-danger py-2 px-3" style="background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545;">
                    <i class="fas fa-info-circle me-2" style="color: #dc3545;"></i>
                    <span class="fw-bold text-light">Esta acción no se puede deshacer.</span>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: `<i class="fas fa-redo-alt me-2"></i>Sí, crear ${nuevoTipo}`,
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: 'var(--win-accent)',
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Cambiando...',
                text: `Preparando nueva ${nuevoTipo.toLowerCase()}`,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirigir después del loading
            setTimeout(() => {
                window.location.href = 'nueva_factura.php?tipo=' + nuevoTipo;
            }, 500);
        }
    });
    
    return false; // Prevenir la navegación inmediata
}
// Función para generar oferta usando ver_factura.php (sin guardar en BD)
function generarOfertaParaImpresion() {
    console.log('=== INICIO generarOfertaParaImpresion ===');
    
    // 1. Recolectar todos los datos del formulario
    const formData = new FormData(document.getElementById('facturaForm'));
    
    // 2. Recolectar servicios de la tabla
    const servicios = [];
    document.querySelectorAll('#serviciosBody tr').forEach((fila, index) => {
        const servicioId = fila.querySelector('input[name="servicio_id[]"]')?.value;
        const cantidad = fila.querySelector('.cantidad')?.value;
        const precio = fila.querySelector('.precio')?.value;
        const descripcion = fila.querySelector('strong')?.textContent;
        const codigo = fila.querySelector('.text-muted.small')?.textContent.split(' | ')[0] || '';
        
        if (servicioId && cantidad && precio) {
            servicios.push({
                id: servicioId,
                codigo: codigo,
                descripcion: descripcion,
                cantidad: cantidad,
                precio: precio,
                total: (parseFloat(cantidad) * parseFloat(precio)).toFixed(2)
            });
        }
    });
    
    console.log('Servicios recolectados:', servicios.length);
    
    if (servicios.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No hay servicios en la oferta',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)'
        });
        return;
    }
    
    // 3. Datos del cliente
    const clienteSelect = document.getElementById('clienteSelect');
    const clienteOption = clienteSelect.options[clienteSelect.selectedIndex];
    
    if (!clienteOption || !clienteOption.value) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Debe seleccionar un cliente',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)'
        });
        return;
    }
    
    const clienteData = {
        id: clienteSelect.value,
        nombre: clienteOption.getAttribute('data-nombre') || '',
        codigo: clienteOption.getAttribute('data-codigo') || '',
        nit: clienteOption.getAttribute('data-nit') || '',
        direccion: clienteOption.getAttribute('data-direccion') || '',
        telefono: clienteOption.getAttribute('data-telefono') || '',
        email: clienteOption.getAttribute('data-email') || '',
        contratoNo: clienteOption.getAttribute('data-contratono') || 'S/C'
    };
    
    // 4. Datos de la oferta
    const ofertaData = {
        no_oferta: document.querySelector('input[name="no_fact"]')?.value || 'OFERTA-TEMP',
        fecha_emision: document.getElementById('fechaEmision')?.value || new Date().toISOString().split('T')[0],
        tipo_pago_id: document.getElementById('tipoPagoSelect')?.value || '20',
        tipo_pago_texto: document.getElementById('tipoPagoSelect')?.options[document.getElementById('tipoPagoSelect')?.selectedIndex]?.text || 'TRANSFERENCIA BANCARIA',
        observaciones: document.getElementById('observacionesFactura')?.value || '',
        subtotal: document.getElementById('subtotalInput')?.value || '0',
        total_general: document.getElementById('totalGeneralInput')?.value || '0',
        importe_letras: document.getElementById('importeLetrasFooter')?.textContent || 'CERO PESOS CON 00/100',
        servicios: servicios,
        cliente: clienteData,
        usuario: {
            id: '<?php echo $_SESSION['usuario_id']; ?>',
            nombre: '<?php echo $_SESSION['usuario_nombre']; ?>'
        }
    };
    
    console.log('Datos a enviar:', ofertaData);
    
    // 5. Mostrar loading
    Swal.fire({
        title: 'Generando Oferta...',
        text: 'Preparando documento para visualización',
        allowOutsideClick: false,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // 6. Enviar al servidor para procesar (SIN GUARDAR EN BD)
    fetch('procesar_oferta_temporal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(ofertaData)
    })
    .then(async response => {
        console.log('Respuesta recibida, status:', response.status);
        
        // Intentar obtener el texto de la respuesta
        const text = await response.text();
        console.log('Texto de respuesta:', text);
        
        // Intentar parsear JSON
        try {
            const data = JSON.parse(text);
            return data;
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            throw new Error('La respuesta del servidor no es JSON válido. Respuesta: ' + text.substring(0, 200));
        }
    })
    .then(data => {
        Swal.close();
        console.log('Datos recibidos:', data);
        
        if (data.success && data.token) {
            // Abrir ver_factura.php con el token temporal
            const url = `ver_factura.php?temporal_token=${data.token}&tipo=OFERTA`;
            window.open(url, '_blank');
            
            // Restaurar botón
            const btnGuardar = document.getElementById('btnGuardar');
            if (btnGuardar) {
                btnGuardar.innerHTML = '<i class="fas fa-save me-1"></i>Guardar OFERTA';
                btnGuardar.disabled = false;
            }
            
            // Mostrar mensaje de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Oferta generada!',
                html: `
                    <div class="text-start">
                        <p>La oferta ha sido generada correctamente en una nueva ventana.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota importante:</strong> Las ofertas NO se guardan en la base de datos.
                            Asegúrate de imprimir o guardar el PDF generado.
                        </div>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: 'var(--win-accent)',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)'
            });
        } else {
            throw new Error(data.message || 'Error al generar oferta');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error completo:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Error al generar oferta',
            html: `
                <div class="text-start">
                    <p>${error.message}</p>
                    <p class="text-muted small">Revisa la consola para más detalles (F12)</p>
                </div>
            `,
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)'
        });
        
        // Restaurar botón
        const btnGuardar = document.getElementById('btnGuardar');
        if (btnGuardar) {
            btnGuardar.innerHTML = '<i class="fas fa-save me-1"></i>Guardar OFERTA';
            btnGuardar.disabled = false;
        }
    });
}
// Función simple para actualizar el número de factura al cambiar la fecha
function actualizarNumeroFactura() {
    const inputFecha = document.getElementById('fechaEmision');
    const inputNumero = document.querySelector('input[name="no_fact"]');
    
    if (!inputFecha || !inputNumero) return;
    
    const fechaSeleccionada = inputFecha.value;
    if (!fechaSeleccionada) return;
    
    // Extraer el número actual (últimos 4 dígitos)
    const numeroActual = inputNumero.value;
    const secuencial = numeroActual.slice(-4); // Los últimos 4 caracteres
    
    // Formatear fecha de YYYY-MM-DD a YYYYMMDD
    const fechaFormateada = fechaSeleccionada.replace(/-/g, '');
    
    // Obtener el prefijo (FV- o OFV-)
    const prefijo = numeroActual.substring(0, 3); // Toma "FV-" o "OFV-"
    
    // Construir nuevo número
    const nuevoNumero = prefijo + fechaFormateada + secuencial;
    
    // Actualizar el input
    inputNumero.value = nuevoNumero;
}

</script>
</body>
</html>