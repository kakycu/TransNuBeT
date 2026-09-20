<?php
session_start();
require_once 'config/database.php';


// ============ ESTABLECER max_allowed_packet SILENCIOSAMENTE ============
try {
    $db = Database::getConnection();
    
    // Intentar establecer max_allowed_packet a 1GB para la sesión actual
    @$db->exec("SET GLOBAL max_allowed_packet = 1073741824");
    
    // Si falla, intentar establecer solo para la sesión
    if (@$db->errorInfo()[0] !== '00000') {
        @$db->exec("SET SESSION max_allowed_packet = 1073741824");
    }
    
    // Opcional: verificar el valor actual (sin mostrar nada)
    @$stmt = $db->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    if ($stmt) {
        @$row = $stmt->fetch(PDO::FETCH_ASSOC);
        // No se hace nada con el resultado
    }
    
} catch (Exception $e) {
    // Silencioso - no hacer nada
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Obtener información del usuario actual
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

// ============ MANEJAR PETICIÓN AJAX PARA HISTORIAL ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'obtener_historial_cierres') {
    header('Content-Type: application/json');
    
    // Verificar autenticación
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }
    
    
    try {
        $db = Database::getConnection();
        
        // Consulta para obtener el historial con información de usuario
$sql = "
    SELECT 
        hc.*,
        u.nombre as usuario_nombre,
        u.usuario as usuario_login
    FROM historico_cierres hc
    LEFT JOIN clasif_usuarios u ON hc.usuario_id = u.id
    WHERE hc.periodo_anio = (SELECT YEAR(fecha_inicio_operaciones) FROM configuracion_sistema LIMIT 1)
    ORDER BY 
        hc.periodo_mes DESC,
        hc.fecha_ejecucion DESC
    LIMIT 50
";
        
$stmt = $db->prepare($sql);
$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Una sola consulta para obtener todos los totales
$sql_totales = "
    SELECT 
        (SELECT COUNT(*) FROM historico_cierres) as total_general,
        (SELECT YEAR(fecha_inicio_operaciones) FROM configuracion_sistema LIMIT 1) as anio_operaciones,
        (SELECT COUNT(*) FROM historico_cierres WHERE periodo_anio = 
            (SELECT YEAR(fecha_inicio_operaciones) FROM configuracion_sistema LIMIT 1)
        ) as total_anio
    FROM DUAL
";

$stmt_totales = $db->query($sql_totales);
$totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);

// Formatear respuesta
echo json_encode([
    'success' => true,
    'historial' => $historial,
    'total' => $totales['total_general'] ?? 0,
    'total_anio' => $totales['total_anio'] ?? 0,
    'anio_operaciones' => $totales['anio_operaciones'] ?? date('Y'),
    'timestamp' => date('Y-m-d H:i:s')
]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al consultar el historial: ' . $e->getMessage(),
            'error_code' => 500
        ]);
    }
    exit();
}
// ============ FIN MANEJAR PETICIÓN AJAX ============

    // Base de datos de sucursales
$sucursales = [
    '06' => [ // BANDEC
        '5781' => 'BANDEC Sucursal Nuevitas',
        '0001' => 'BANDEC Sucursal Principal La Habana',
        '1001' => 'BANDEC Sucursal Camagüey',
        '5780' => 'BANDEC Sucursal Nuevitas Centro',
        '8888' => 'BANDEC Banca Móvil / Virtual',
        '9999' => 'BANDEC Dirección Nacional / Tarjetas MLC',
        '5960' => 'BANDEC Dirección Provincial Camagüey',
        '5961' => 'BANDEC Plaza de los Trabajadores',
        '5971' => 'BANDEC La Vigía',
        '5981' => 'BANDEC Calle República',
        '5941' => 'BANDEC Av. de los Mártires', // CAMBIADO: 5942 → 5941
        '5951' => 'BANDEC Reparto Garrido',
        '5783' => 'BANDEC Nuevitas (Calle Máximo Gómez)',
        '5790' => 'BANDEC Playa Santa Lucía (Interno)',
        '5791' => 'BANDEC Playa Santa Lucía (Oficial)',
        '6021' => 'BANDEC Florida',
        '5821' => 'BANDEC Guáimaro',
        '6151' => 'BANDEC Santa Cruz del Sur',
        '5751' => 'BANDEC Minas',
        '5701' => 'BANDEC Esmeralda',
        '6061' => 'BANDEC Vertientes',
        '5841' => 'BANDEC Sibanicú',
        '5731' => 'BANDEC Sierra de Cubitas',
        '6101' => 'BANDEC Jimaguayú',
        '6121' => 'BANDEC Najasa',
        '0650' => 'BANDEC Sucursal Principal La Habana',
        '0651' => 'BANDEC Plaza',
        '0652' => 'BANDEC Cerro',
        '0653' => 'BANDEC Boyeros',
        '0654' => 'BANDEC Marianao',
        '0655' => 'BANDEC Guanabacoa',
        '0656' => 'BANDEC San Miguel',
        '0657' => 'BANDEC 10 de Octubre',
        '0660' => 'BANDEC Habana del Este',
        '7291' => 'BANDEC Pinar del Río (Principal)',
        '7281' => 'BANDEC Pinar del Río (Martí)',
        '7251' => 'BANDEC Viñales',
        '1651' => 'BANDEC Artemisa (Principal)',
        '1681' => 'BANDEC Mariel',
        '1661' => 'BANDEC San Antonio de los Baños',
        '1751' => 'BANDEC San José de las Lajas',
        '1781' => 'BANDEC Güines',
        '1721' => 'BANDEC Santa Cruz del Norte',
        '3151' => 'BANDEC Matanzas (Principal)',
        '3181' => 'BANDEC Varadero',
        '3191' => 'BANDEC Cárdenas',
        '4031' => 'BANDEC Santa Clara (Principal)',
        '4081' => 'BANDEC Sagua la Grande',
        '4131' => 'BANDEC Placetas',
        '5041' => 'BANDEC Cienfuegos (Prado)',
        '5051' => 'BANDEC Cienfuegos (Principal)',
        '5341' => 'BANDEC Sancti Spíritus (Principal)',
        '5381' => 'BANDEC Trinidad',
        '5531' => 'BANDEC Ciego de Ávila (Principal)',
        '5571' => 'BANDEC Morón',
        '5641' => 'BANDEC Cayo Coco',
        '6431' => 'BANDEC Las Tunas (Principal)',
        '6471' => 'BANDEC Puerto Padre',
        '6731' => 'BANDEC Holguín (Principal)',
        '6761' => 'BANDEC Moa',
        '6801' => 'BANDEC Guardalavaca',
        '7541' => 'BANDEC Bayamo (Principal)',
        '7581' => 'BANDEC Manzanillo',
        '8151' => 'BANDEC Santiago (Principal)',
        '8141' => 'BANDEC Santiago (Enramadas)',
        '9041' => 'BANDEC Guantánamo (Principal)',
        '9071' => 'BANDEC Baracoa',
        '9661' => 'BANDEC Nueva Gerona',
        '9000' => 'BANDEC Oficina Central',
    ],
    '12' => [ // BPA
        '0001' => 'BPA Sucursal Principal La Habana',
        '1000' => 'BPA Banca Electrónica / Transfermóvil',
        '1001' => 'BPA Sucursal Camagüey',
        '5962' => 'BPA Calle República',
        '5932' => 'BPA Av. de la Libertad',
        '5992' => 'BPA Plaza de los Trabajadores',
        '5942' => 'BPA La Caridad', // MANTENIDO (único en BPA)
        '5972' => 'BPA Previsora',
        '5982' => 'BPA Lenin',
        '5772' => 'BPA Nuevitas (Agramonte esq. Maceo)',
        '5773' => 'BPA Microdistrito Nuevitas',
        '5774' => 'BPA Puerto de Nuevitas',
        '5782' => 'BPA Nuevitas Puerto',
        '5785' => 'BPA Nuevitas Playa',
        '6012' => 'BPA Florida',
        '5812' => 'BPA Guáimaro',
        '6142' => 'BPA Santa Cruz del Sur',
        '5752' => 'BPA Minas',
        '5692' => 'BPA Esmeralda',
        '6052' => 'BPA Vertientes',
        '5832' => 'BPA Sibanicú',
        '5722' => 'BPA Sierra de Cubitas',
        '6092' => 'BPA Jimaguayú',
        '6112' => 'BPA Najasa',
        '0100' => 'BPA Sucursal Principal (La Habana)',
        '0101' => 'BPA Centro Habana (San Rafael)',
        '0102' => 'BPA Habana Vieja (Obispo)',
        '0103' => 'BPA Plaza de la Revolución',
        '0104' => 'BPA Cerro',
        '0105' => 'BPA 10 de Octubre',
        '0106' => 'BPA Playa (Miramar)',
        '0107' => 'BPA Marianao',
        '0108' => 'BPA Boyeros',
        '0109' => 'BPA Arroyo Naranjo',
        '0110' => 'BPA Cotorro',
        '0111' => 'BPA Habana del Este',
        '0112' => 'BPA Guanabacoa',
        '0113' => 'BPA Regla',
        '0114' => 'BPA San Miguel del Padrón',
        '0115' => 'BPA Lisa',
        '0116' => 'BPA Santiago de las Vegas',
        '0117' => 'BPA La Víbora',
        '0118' => 'BPA Lawton',
        '0123' => 'BPA Alamar',
        '2012' => 'BPA Habana Vieja (Aguiar)',
        '2052' => 'BPA Centro Habana',
        '2132' => 'BPA Vedado (Línea)',
        '2212' => 'BPA Miramar',
        '7751' => 'BPA Pinar del Río (Principal)',
        '7762' => 'BPA Viñales',
        '7100' => 'BPA Principal Pinar del Río (Genérico)',
        '2582' => 'BPA Artemisa',
        '2612' => 'BPA Mariel',
        '2532' => 'BPA San Antonio de los Baños',
        '2422' => 'BPA San José de las Lajas',
        '2462' => 'BPA Güines',
        '2492' => 'BPA Santa Cruz del Norte',
        '3412' => 'BPA Matanzas (Milanés)',
        '3442' => 'BPA Varadero',
        '3452' => 'BPA Cárdenas',
        '6100' => 'BPA Matanzas (Genérico)',
        '4232' => 'BPA Santa Clara (Cuba)',
        '4292' => 'BPA Sagua la Grande',
        '4100' => 'BPA Villa Clara (Genérico)',
        '5142' => 'BPA Cienfuegos (Boulevard)',
        '5100' => 'BPA Cienfuegos (Genérico)',
        '5432' => 'BPA Sancti Spíritus',
        '5472' => 'BPA Trinidad',
        '6532' => 'BPA Ciego de Ávila',
        '6552' => 'BPA Morón',
        '6332' => 'BPA Las Tunas',
        '8100' => 'BPA Las Tunas (Genérico)',
        '6932' => 'BPA Holguín (Frexes)',
        '6992' => 'BPA Moa',
        '3100' => 'BPA Holguín (Genérico)',
        '7432' => 'BPA Bayamo',
        '7452' => 'BPA Manzanillo',
        '9100' => 'BPA Bayamo (Genérico)',
        '8351' => 'BPA Santiago (Plaza de Marte)',
        '8361' => 'BPA Santiago (Garzón)',
        '1100' => 'BPA Santiago (Genérico)',
        '9242' => 'BPA Guantánamo',
        '9272' => 'BPA Baracoa',
        '9652' => 'BPA Nueva Gerona',
        '13100' => 'BPA Isla Juventud (Genérico)',
        '9001' => 'BPA Oficina Central',
    ],
    '05' => [ // BANMET
        '0001' => 'BANMET Sucursal Principal La Habana',
        '7000' => 'BANMET Banca Remota / Nóminas',
        '0200' => 'Metropolitano Sucursal Principal',
        '0201' => 'Metropolitano Vedado (23 y L)',
        '0202' => 'Metropolitano Habana Vieja (Mercaderes)',
        '2321' => 'BANMET Vedado (23 y J)',
        '2341' => 'BANMET Rampa (23 y P)',
        '2421' => 'BANMET Habana Vieja (Obispo)',
        '2461' => 'BANMET Centro Habana (Galiano)',
        '2581' => 'BANMET Playa (3ra y 70)',
        '2621' => 'BANMET Marianao',
        '2741' => 'BANMET 10 de Octubre',
        '2781' => 'BANMET La Víbora',
        '2821' => 'BANMET Arroyo Naranjo',
        '2861' => 'BANMET Santiago de las Vegas',
        '2941' => 'BANMET Cotorro',
        '2971' => 'BANMET Guanabacoa',
        '3021' => 'BANMET Habana del Este (Alamar)',
        '3081' => 'BANMET Regla',
        '3121' => 'BANMET San Miguel del Padrón',
        '3161' => 'BANMET La Lisa',
        '9003' => 'BANMET Oficina Central',
    ],
    '15' => [ // BFI
        '0001' => 'BFI Sucursal Principal La Habana',
        '0150' => 'BFI Sucursal Principal',
        '0151' => 'BFI Vedado (Línea y L)',
        '0152' => 'BFI Miramar (7ma y 78)',
        '0154' => 'BFI Aeropuerto José Martí',
        '8000' => 'BFI Varadero',
        '8001' => 'BFI Cayo Coco',
        '8005' => 'BFI Guardalavaca',
    ],
    '14' => [ // BEC (Banco Exterior de Cuba)
        '1400' => 'BEC Sucursal Principal',
        '1401' => 'BEC Santiago de Cuba',
        '1403' => 'BEC Camagüey',
    ],
    '13' => [ // BISO (Banco de Inversiones)
        '1500' => 'BISO Sucursal Principal',
        '1503' => 'BISO Camagüey',
    ],
    '99' => [ // CASAS DE CAMBIO Y OTRAS INSTITUCIONES
        '2000' => 'CADECA Principal (La Habana)',
        '2001' => 'CADECA Obispo',
        '2005' => 'CADECA Aeropuerto José Martí',
        '2009' => 'CADECA Camagüey',
        '2007' => 'CADECA Varadero',
        '3000' => 'FINCIMEX Sucursal Principal',
        '3001' => 'FINCIMEX Vedado',
        '3005' => 'FINCIMEX Camagüey',
        '0001' => 'Banco Central de Cuba',
    ],
    '98' => [ // BANCOS INTERNACIONALES
        '5000' => 'Nova Scotia Bank',
        '5001' => 'Banco Sabadell',
        '5002' => 'BBVA',
        '5003' => 'Santander',
    ]
];
// Función que recibe el array como parámetro
function obtenerNombreSucursalPHP($codigoBanco, $codigoSucursal, $sucursalesArray) {
    if (!isset($codigoBanco) || !isset($codigoSucursal) || strlen($codigoSucursal) !== 4) {
        return 'Sucursal no identificada para ese Banco';
    }
    
    if (isset($sucursalesArray[$codigoBanco][$codigoSucursal])) {
        return $sucursalesArray[$codigoBanco][$codigoSucursal];
    }
    
    return "Sucursal no identificada para ese banco";
}


// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Obtener configuración del sistema
try {
    $db = Database::getConnection();
    
    // Obtener configuración actual
    $sql = "SELECT * FROM configuracion_sistema LIMIT 1";
    $stmt = $db->query($sql);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        // Insertar configuración por defecto
        $sql_insert = "INSERT INTO configuracion_sistema 
            (nombre_empresa, nombre_proyecto, direccion, telefono, email, 
            cod_reeup, cod_nit, sitio_web, fecha_inico_operaciones, 
            cuenta_bancaria, banco, sucursal, director_nombre, director_ci, 
            director_telefono, facturador_nombre, facturador_ci, facturador_telefono,
            whatsapp_ON, whatsapp_numero, modo_mantenimiento, restabpw) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->execute([
            'Empresa Municipal de Comercio y Gastronomia de Nuevitas PDL-Proyectos',
            'Proyecto de Desarrollo Local "Visiones"',
            'Calle Máximo Gómez. S/N e/Camilo Cienfuegos y Lugareño. Nuevitas, Camagüey.',
            '+5359860773',
            'info@sisfact-imdl.cu',
            '319.2.5400',
			'14004200858',
            'www.sisfact-imdl.cu',
			'',
            '0657833000454818',
            'Banco de Crédito y Comercio (BANDEC)',
            '5783',
            'Laicep Estrella Suñol García',
            '85072117601',
            '+5359962404',
            'Annia Guerra Loureiro',
            '71011003838',
            '+5359899690',
            1,
            '+5359860773',
			0,
			1
        ]);
        
        // Volver a obtener la configuración
        $stmt = $db->query($sql);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Corregir caracteres especiales
    if ($config) {
        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $config[$key] = str_replace('&#34;', '"', $value);
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error al cargar configuración: " . $e->getMessage());
    $error = "Error al cargar la configuración del sistema: " . $e->getMessage();
    $config = [];
}

// Procesar actualización del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getConnection();
        
        // Obtener el número actual de WhatsApp
        $sql_current = "SELECT whatsapp_numero FROM configuracion_sistema LIMIT 1";
        $stmt_current = $db->query($sql_current);
        $config_actual = $stmt_current->fetch(PDO::FETCH_ASSOC);
        $numero_antiguo = $config_actual['whatsapp_numero'] ?? '';
        
        // Corregir caracteres especiales
        foreach ($_POST as $key => $value) {
            if (is_string($value)) {
                $_POST[$key] = str_replace('&#34;', '"', $value);
            }
        }
        
        // Sanitizar y validar todos los campos
        $nombre_empresa = filter_var($_POST['nombre_empresa'], FILTER_SANITIZE_STRING);
        $nombre_empresa = str_replace('&#34;', '"', $nombre_empresa);
        
        $nombre_proyecto = filter_var($_POST['nombre_proyecto'] ?? '', FILTER_SANITIZE_STRING);
        $nombre_proyecto = str_replace('&#34;', '"', $nombre_proyecto);
        
        $direccion = filter_var($_POST['direccion'] ?? '', FILTER_SANITIZE_STRING);
        $direccion = str_replace('&#34;', '"', $direccion);
        
        $telefono = filter_var($_POST['telefono'] ?? '', FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $cuenta_bancaria = filter_var($_POST['cuenta_bancaria'] ?? '', FILTER_SANITIZE_STRING);
        $banco = filter_var($_POST['banco'] ?? '', FILTER_SANITIZE_STRING);
        $sucursal = filter_var($_POST['sucursal'] ?? '', FILTER_SANITIZE_STRING);
        $director_nombre = filter_var($_POST['director_nombre'] ?? '', FILTER_SANITIZE_STRING);
        $director_ci = filter_var($_POST['director_ci'] ?? '', FILTER_SANITIZE_STRING);
        $director_telefono = filter_var($_POST['director_telefono'] ?? '', FILTER_SANITIZE_STRING);
        $facturador_nombre = filter_var($_POST['facturador_nombre'] ?? '', FILTER_SANITIZE_STRING);
        $facturador_ci = filter_var($_POST['facturador_ci'] ?? '', FILTER_SANITIZE_STRING);
        $facturador_telefono = filter_var($_POST['facturador_telefono'] ?? '', FILTER_SANITIZE_STRING);
        $cod_reeup = filter_var($_POST['cod_reeup'] ?? '', FILTER_SANITIZE_STRING);
        $cod_nit = filter_var($_POST['cod_nit'] ?? '', FILTER_SANITIZE_STRING);
        $sitio_web = filter_var($_POST['sitio_web'] ?? '', FILTER_SANITIZE_STRING);

        // Validar código Reeup
        if (!empty($cod_reeup)) {
            if (!preg_match('/^\d{3}\.\d{1}\.\d{4}$/', $cod_reeup)) {
                throw new Exception("El código Reeup debe tener el formato ###.#.#### (ej: 123.4.5678)");
            }
        }

        // Nuevos campos: WhatsApp y booleanos
        $whatsapp_ON = isset($_POST['whatsapp_ON']) ? 1 : 0;
        
        // Lógica para número de WhatsApp
        $whatsapp_input = filter_var($_POST['whatsapp_numero'] ?? '', FILTER_SANITIZE_STRING);
        $numero_limpio = preg_replace('/[^\+\d]/', '', $whatsapp_input);
        $whatsapp_numero = '';

        if (!empty($numero_limpio)) {
            // Auto-formato Cuba (+53)
            if (substr($numero_limpio, 0, 3) !== '+53') {
                if (substr($numero_limpio, 0, 2) === '53') {
                    $numero_limpio = '+' . $numero_limpio;
                } elseif (substr($numero_limpio, 0, 1) === '5') {
                    $numero_limpio = '+53' . $numero_limpio;
                } elseif (substr($numero_limpio, 0, 1) === '0') {
                    $numero_limpio = '+53' . substr($numero_limpio, 1);
                }
            }

            // Validar formato
            if (!preg_match('/^\+53[5-8]\d{7}$/', $numero_limpio)) {
                throw new Exception("El número de WhatsApp no tiene el formato correcto para Cuba (+53...).");
            }

            $whatsapp_numero = $numero_limpio;
        } else {
            if ($whatsapp_ON == 1) {
                throw new Exception("Debe proporcionar un número de WhatsApp si habilita esta función.");
            } else {
                $whatsapp_numero = '';
            }
        }
        
        $modo_mantenimiento = isset($_POST['modo_mantenimiento']) ? 1 : 0;
        $activar_recuperacion_password = isset($_POST['activar_recuperacion_password']) ? 1 : 0;
		
        // Procesar fecha_inicio_operaciones (solo si es admin)
        if ($es_admin && isset($_POST['fecha_inicio_operaciones']) && !empty($_POST['fecha_inicio_operaciones'])) {
            $fecha_operaciones = $_POST['fecha_inicio_operaciones']; // Ya viene como YYYY-MM-01
            
            // Validar formato
            if (!preg_match('/^\d{4}-\d{2}-01$/', $fecha_operaciones)) {
                throw new Exception("Formato de fecha inválido. Debe ser YYYY-MM-01");
            }
            
            // Extraer año y mes
            list($anio, $mes, $dia) = explode('-', $fecha_operaciones);
            $anio_actual = date('Y');
            $mes_actual = date('m');
            
            // Validar rango de años (2010 - año actual)
            if ($anio < 2010 || $anio > $anio_actual) {
                throw new Exception("El año debe estar entre 2010 y " . $anio_actual);
            }
            
            // Validar que no sea mes futuro
            if ($anio == $anio_actual && $mes > $mes_actual) {
                throw new Exception("No se puede seleccionar un mes futuro");
            }
            
            // Validar mes (1-12)
            if ($mes < 1 || $mes > 12) {
                throw new Exception("Mes inválido");
            }
            
            // Siempre forzar día 1
            $fecha_operaciones = $anio . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-01';
            
        } else {
            // Si no es admin o no se envió, mantener el valor actual
            $fecha_operaciones = $config['fecha_inicio_operaciones'] ?? date('Y-m-01');
        }
        
        // ========== NUEVA LÓGICA PARA PROCESAR LOGO RECORTADO ==========
        $logo = $config['logo'] ?? null; // Valor por defecto: mantener el existente
        
        // Verificar si se solicitó eliminar el logo (solo cuando se guarda)
        if (isset($_POST['eliminar_logo']) && $_POST['eliminar_logo'] == '1') {
            // Eliminar archivo físico del logo si existe
            if (!empty($config['logo'])) {
                $ruta_logo = $config['logo'];
                if (file_exists($ruta_logo)) {
                    if (@unlink($ruta_logo)) {
                        error_log("Logo eliminado físicamente al guardar: " . $ruta_logo);
                    } else {
                        error_log("No se pudo eliminar el logo: " . $ruta_logo);
                    }
                }
            }
            $logo = null; // Establecer NULL en la base de datos
        } 
        // Si se recortó una nueva imagen
        elseif (isset($_POST['logo_recortado']) && !empty($_POST['logo_recortado'])) {
            $directorio_imagenes = 'assets/imagenes/config/';
            
            // Crear directorio si no existe
            if (!file_exists($directorio_imagenes)) {
                mkdir($directorio_imagenes, 0777, true);
            }
            
            // Eliminar logo anterior si existe (cuando se sube uno nuevo)
            if (!empty($config['logo']) && file_exists($config['logo'])) {
                @unlink($config['logo']);
            }
            
            // Decodificar imagen base64
            $imagen_base64 = $_POST['logo_recortado'];
            // Limpiar el formato data:image
            if (strpos($imagen_base64, 'base64,') !== false) {
                $imagen_base64 = explode('base64,', $imagen_base64)[1];
            }
            $imagen_base64 = str_replace(' ', '+', $imagen_base64);
            $imagen_decodificada = base64_decode($imagen_base64);
            
            if ($imagen_decodificada !== false && strlen($imagen_decodificada) > 100) {
                // Generar nombre único
                $nombre_unico = 'logo_' . time() . '_' . uniqid() . '.jpg';
                $ruta_completa = $directorio_imagenes . $nombre_unico;
                
                // Guardar imagen
                if (file_put_contents($ruta_completa, $imagen_decodificada)) {
                    $logo = $ruta_completa;
                }
            }
        } 
        // Si se sube archivo sin recorte
        elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $directorio_imagenes = 'assets/imagenes/config/';
            
            // Crear directorio si no existe
            if (!file_exists($directorio_imagenes)) {
                mkdir($directorio_imagenes, 0777, true);
            }
            
            // Eliminar logo anterior si existe
            if (!empty($config['logo']) && file_exists($config['logo'])) {
                @unlink($config['logo']);
            }
            
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                throw new Exception("El logo no puede exceder los 2MB");
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
            $file_type = mime_content_type($_FILES['logo']['tmp_name']);
            if (in_array($file_type, $allowed_types)) {
                $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $nombre_unico = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
                $ruta_completa = $directorio_imagenes . $nombre_unico;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $ruta_completa)) {
                    $logo = $ruta_completa;
                } else {
                    throw new Exception("Error al guardar el logo.");
                }
            } else {
                throw new Exception("Tipo de archivo de logo no permitido.");
            }
        }
        // Si no hay cambios, mantener el logo existente (ya está en $logo por defecto)
        
        // ========== NUEVA LÓGICA PARA PROCESAR FONDO RECORTADO ==========
        $fondo_web = $config['fondo_web'] ?? null; // Valor por defecto: mantener el existente
        
        // Verificar si se solicitó eliminar el fondo (solo cuando se guarda)
        if (isset($_POST['eliminar_fondo']) && $_POST['eliminar_fondo'] == '1') {
            // Eliminar archivo físico del fondo si existe
            if (!empty($config['fondo_web'])) {
                $ruta_fondo = $config['fondo_web'];
                if (file_exists($ruta_fondo)) {
                    if (@unlink($ruta_fondo)) {
                        error_log("Fondo eliminado físicamente al guardar: " . $ruta_fondo);
                    } else {
                        error_log("No se pudo eliminar el fondo: " . $ruta_fondo);
                    }
                }
            }
            $fondo_web = null; // Establecer NULL en la base de datos
        }
        // Si se recortó una nueva imagen
        elseif (isset($_POST['fondo_recortado']) && !empty($_POST['fondo_recortado'])) {
            $directorio_imagenes = 'assets/imagenes/config/';
            
            // Crear directorio si no existe
            if (!file_exists($directorio_imagenes)) {
                mkdir($directorio_imagenes, 0777, true);
            }
            
            // Eliminar fondo anterior si existe
            if (!empty($config['fondo_web']) && file_exists($config['fondo_web'])) {
                @unlink($config['fondo_web']);
            }
            
            // Decodificar imagen base64
            $imagen_base64 = $_POST['fondo_recortado'];
            if (strpos($imagen_base64, 'base64,') !== false) {
                $imagen_base64 = explode('base64,', $imagen_base64)[1];
            }
            $imagen_base64 = str_replace(' ', '+', $imagen_base64);
            $imagen_decodificada = base64_decode($imagen_base64);
            
            if ($imagen_decodificada !== false && strlen($imagen_decodificada) > 100) {
                // Generar nombre único
                $nombre_unico = 'fondo_' . time() . '_' . uniqid() . '.jpg';
                $ruta_completa = $directorio_imagenes . $nombre_unico;
                
                // Guardar imagen
                if (file_put_contents($ruta_completa, $imagen_decodificada)) {
                    $fondo_web = $ruta_completa;
                }
            }
        }
        // Si se sube archivo sin recorte
        elseif (isset($_FILES['fondo_web']) && $_FILES['fondo_web']['error'] === UPLOAD_ERR_OK) {
            $directorio_imagenes = 'assets/imagenes/config/';
            
            // Crear directorio si no existe
            if (!file_exists($directorio_imagenes)) {
                mkdir($directorio_imagenes, 0777, true);
            }
            
            // Eliminar fondo anterior si existe
            if (!empty($config['fondo_web']) && file_exists($config['fondo_web'])) {
                @unlink($config['fondo_web']);
            }
            
            if ($_FILES['fondo_web']['size'] > 5 * 1024 * 1024) {
                throw new Exception("El fondo no puede exceder los 5MB");
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($_FILES['fondo_web']['tmp_name']);
            if (in_array($file_type, $allowed_types)) {
                $extension = pathinfo($_FILES['fondo_web']['name'], PATHINFO_EXTENSION);
                $nombre_unico = 'fondo_' . time() . '_' . uniqid() . '.' . $extension;
                $ruta_completa = $directorio_imagenes . $nombre_unico;
                
                if (move_uploaded_file($_FILES['fondo_web']['tmp_name'], $ruta_completa)) {
                    $fondo_web = $ruta_completa;
                } else {
                    throw new Exception("Error al guardar el fondo.");
                }
            } else {
                throw new Exception("Tipo de archivo de fondo no permitido.");
            }
        }
        
        // Actualizar base de datos
        $sql_update = "UPDATE configuracion_sistema SET 
            nombre_empresa = ?, nombre_proyecto = ?, direccion = ?, telefono = ?, email = ?, 
            cod_reeup = ?, cod_nit = ?, sitio_web = ?, fecha_inicio_operaciones = ?, 
            cuenta_bancaria = ?, banco = ?, sucursal = ?,
            director_nombre = ?, director_ci = ?, director_telefono = ?,
            facturador_nombre = ?, facturador_ci = ?, facturador_telefono = ?,
            logo = ?, fondo_web = ?,
            whatsapp_ON = ?, whatsapp_numero = ?,
            modo_mantenimiento = ?,
			restabpw = ?
            WHERE id = ?";

        $stmt_update = $db->prepare($sql_update);
        $stmt_update->execute([
            $nombre_empresa, $nombre_proyecto, $direccion, $telefono, $email,
            $cod_reeup, $cod_nit, $sitio_web, $fecha_operaciones,
            $cuenta_bancaria, $banco, $sucursal,
            $director_nombre, $director_ci, $director_telefono,
            $facturador_nombre, $facturador_ci, $facturador_telefono,
            $logo, $fondo_web,
            $whatsapp_ON, $whatsapp_numero,
            $modo_mantenimiento,
			$activar_recuperacion_password,
            $config['id'] ?? 1
        ]);
        
        // Registrar actividad
        $descripcion_log = 'Configuración del sistema actualizada';
        $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                   VALUES (?, ?, ?, ?, ?)";
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            'ACTUALIZAR_CONFIG',
            $descripcion_log,
            $_SESSION['usuario_id'],
            $_SESSION['usuario_nombre'] ?? 'Administrador',
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $success = "Configuración actualizada exitosamente";
        
        // Recargar config
        $sql = "SELECT * FROM configuracion_sistema LIMIT 1";
        $stmt = $db->query($sql);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Asegurar reemplazo también en recarga post-submit
        if ($config) {
            foreach ($config as $key => $value) {
                if (is_string($value)) {
                    $config[$key] = str_replace('&#34;', '"', $value);
                }
            }
        }
        
    } catch (Exception $e) {
        $error = "Error al actualizar configuración: " . $e->getMessage();
    }
}

// Obtener estadísticas del histórico
$estadisticas = ['total' => 0];
try {
    $db = Database::getConnection();
    $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt = $db->query($sql_total);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $estadisticas['total'] = $resultado['total'] ?? 0;
} catch (Exception $e) {
    error_log("Error al obtener estadísticas del histórico: " . $e->getMessage());
}

$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

// Colores de temas Windows 11
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

$avatar_placeholder = strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1));

// Obtener estadísticas para los badges del sidebar
try {
    $db = Database::getConnection();
    
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $estadisticas = [];
    $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt = $db->query($sql_total);
    $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
    $total_facturas = 0;
    $total_clientes = 0;
    $total_categorias = 0;
    $total_servicios = 0;
    $total_usuarios = 0;
    $estadisticas['total'] = 0;
}

require_once __DIR__ . '/config/header.php';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo htmlspecialchars($tema_windows); ?>" data-accent="<?php echo htmlspecialchars($color_accent); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISFACT IMDL Visiones - Configuración</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <!-- Windows 11 Styles -->
<style>
    :root {
        --win-bg-primary: <?php echo htmlspecialchars($tema_actual['bg_primary']); ?>;
        --win-bg-secondary: <?php echo htmlspecialchars($tema_actual['bg_secondary']); ?>;
        --win-bg-tertiary: <?php echo htmlspecialchars($tema_actual['bg_tertiary']); ?>;
        --win-text-primary: <?php echo htmlspecialchars($tema_actual['text_primary']); ?>;
        --win-text-secondary: <?php echo htmlspecialchars($tema_actual['text_secondary']); ?>;
        --win-border-color: <?php echo htmlspecialchars($tema_actual['border_color']); ?>;
        --win-accent: <?php echo htmlspecialchars($tema_actual['accent_color']); ?>;
        --win-accent-light: <?php echo htmlspecialchars($tema_actual['accent_color']); ?>20;
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

    /* Footer Fijo Estilo Windows */
    .win-footer-fixed {
        position: fixed;
        bottom: 0;
        left: 260px; /* Ancho del sidebar */
        right: 0;
        background: var(--win-bg-secondary);
        border-top: 1px solid var(--win-border-color);
        padding: 12px 24px;
        z-index: 990;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
        transition: var(--win-transition);
    }

    /* Ajustes para cuando el sidebar está minimizado */
    .sidebar-mini ~ .win-main-content .win-footer-fixed,
    body:has(.win-sidebar.mini) .win-footer-fixed {
        left: 68px;
    }

    /* Ajustes para móvil */
    @media (max-width: 992px) {
        .win-footer-fixed {
            left: 0;
        }
    }

    /* Padding inferior para que el contenido no quede tapado por el footer */
    .win-main-content {
        padding-bottom: 80px; 
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
        overflow: hidden;
    }
    
    .win-sidebar-user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
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

    .win-main-content .border-bottom {
        border-bottom-color: var(--win-border-color) !important;
    }

    .win-main-content .text-muted {
        color: var(--win-text-secondary) !important;
    }

    .win-main-content .bg-light {
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
        animation: pulseSave 1s infinite;
    }

    @keyframes pulseSave {
        0% {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
        }
        50% {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.6);
        }
        100% {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
        }
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

    /* Vista previa de imágenes */
    .image-preview-container {
        margin-top: 10px;
    }

    .image-preview {
        width: 150px;
        height: 150px;
        object-fit: contain;
        background: var(--win-bg-tertiary);
        border: 1px solid var(--win-border-color);
        border-radius: var(--win-radius-sm);
        padding: 5px;
        margin-top: 10px;
    }

    /* ==================== ESTILOS PARA ELIMINACIÓN PENDIENTE ==================== */
    .eliminacion-pendiente {
        animation: fadeIn 0.5s ease;
        background: var(--win-bg-tertiary);
        border: 1px solid var(--win-border-color);
        border-radius: var(--win-radius);
        padding: 16px;
        margin-top: 10px;
    }

    .eliminacion-pendiente .alert {
        background: linear-gradient(135deg, 
            rgba(255, 193, 7, 0.1),
            rgba(255, 193, 7, 0.05)
        ) !important;
        border: 2px solid rgba(255, 193, 7, 0.3) !important;
        color: var(--win-text-primary) !important;
    }

    .eliminacion-pendiente .alert-warning {
        border-color: #ffc107 !important;
    }

    .eliminacion-pendiente .alert h6 {
        color: #ffc107 !important;
        font-weight: 600;
    }

    .eliminacion-pendiente .alert p {
        color: var(--win-text-secondary);
        margin-bottom: 10px;
    }

    .eliminacion-pendiente .btn-outline-warning {
        border-color: #ffc107;
        color: #ffc107;
        transition: all 0.3s ease;
    }

    .eliminacion-pendiente .btn-outline-warning:hover {
        background-color: #ffc107;
        color: #212529;
        transform: translateY(-2px);
    }

    .eliminacion-pendiente .btn-light {
        background: var(--win-bg-secondary);
        border-color: var(--win-border-color);
        color: var(--win-text-primary);
    }

    .eliminacion-pendiente .btn-light:hover {
        background: var(--win-bg-tertiary);
        border-color: var(--win-accent);
    }

    .eliminacion-pendiente .progress {
        background: var(--win-bg-secondary);
        border-radius: 10px;
        overflow: hidden;
    }

    .eliminacion-pendiente .progress-bar {
        background: linear-gradient(90deg, #ffc107, #ff9800);
        background-size: 200% 100%;
    }

    .progress-bar-animated {
        animation: progress-bar-stripes 1s linear infinite;
    }

    @keyframes progress-bar-stripes {
        0% { background-position: 1rem 0; }
        100% { background-position: 0 0; }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    /* ==================== FIN ESTILOS ELIMINACIÓN ==================== */

    /* Botones de eliminar imágenes */
    .btn-eliminar-imagen {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: none;
        border-radius: var(--win-radius-sm);
        padding: 6px 12px;
        color: white;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--win-transition);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
    }

    .btn-eliminar-imagen:hover {
        background: linear-gradient(135deg, #c82333, #b21f2d);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }

    /* Mejora para las vistas previas */
    .image-preview-container {
        position: relative;
        margin-top: 10px;
    }

    .image-preview {
        width: 100%;
        max-width: 200px;
        height: auto;
        max-height: 150px;
        object-fit: contain;
        background: var(--win-bg-tertiary);
        border: 2px solid var(--win-border-color);
        border-radius: var(--win-radius-sm);
        padding: 5px;
    }

    .image-preview-container .btn-eliminar-imagen {
        position: absolute;
        top: 5px;
        right: 5px;
        opacity: 0.9;
        z-index: 10;
    }

    .btn-outline-warning:hover {
        background-color: #ffc107;
        color: #212529;
    }

    /* ==================== ESTILOS PARA SWITCHES ==================== */
    .win-main-content .form-check.form-switch {
        padding-left: 0;
        margin-bottom: 0;
    }

    .win-main-content .form-check-input[type="checkbox"] {
        width: 2.8em !important;
        height: 1.4em !important;
        margin: 0;
        cursor: pointer;
        background-color: var(--win-bg-tertiary);
        border: 1px solid var(--win-border-color);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba(255,255,255,0.9)'/%3e%3c/svg%3e");
        background-position: left center;
        background-repeat: no-repeat;
        background-size: contain;
        transition: background-position 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        flex-shrink: 0;
    }

    .win-main-content .form-check-input:checked[type="checkbox"] {
        background-color: var(--win-accent);
        border-color: var(--win-accent);
        background-position: right center;
    }

    .win-main-content .form-check-input:focus {
        outline: none;
        box-shadow: 0 0 0 0.2rem var(--win-accent-light);
        border-color: var(--win-accent);
    }

    /* Para tema claro */
    [data-theme="light"] .win-main-content .form-check-input[type="checkbox"] {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba(0,0,0,0.7)'/%3e%3c/svg%3e");
    }

    .win-main-content .form-check-label {
        color: var(--win-text-primary);
        cursor: pointer;
        user-select: none;
    }

    /* Alineación de switches */
    .win-main-content .form-switch.d-flex.align-items-center {
        min-height: auto;
    }

    .win-main-content .form-switch.d-flex.align-items-center .d-flex.flex-column {
        flex: 1;
    }

    /* Estados de texto para los toggles */
    #whatsappStatus.text-success,
    #modoMantenimientoStatus.text-warning {
        font-weight: 600;
    }

    #whatsappStatus.text-success {
        color: #28a745 !important;
    }

    #whatsappStatus.text-danger {
        color: #dc3545 !important;
        font-weight: 500;
    }

    #modoMantenimientoStatus.text-warning {
        color: #ffc107 !important;
    }

    #modoMantenimientoStatus.text-muted {
        color: var(--win-text-secondary) !important;
    }

    /* Responsive para móviles */
    @media (max-width: 768px) {
        .win-main-content .form-check-input[type="checkbox"] {
            width: 2.4em !important;
            height: 1.2em !important;
        }
        
        .bg-light.p-3.rounded {
            padding: 12px !important;
        }
    }
    /* ==================== FIN ESTILOS PARA SWITCHES ==================== */

    /* ==================== ESTILOS PARA SUCURSALES ==================== */
    /* Información de sucursal debajo del campo */
    #sucursalInfo {
        display: none;
        background: var(--win-bg-tertiary);
        border: 1px solid var(--win-border-color);
        border-radius: var(--win-radius-sm);
        padding: 8px 12px;
        margin-top: 6px;
        font-size: 13px;
        color: var(--win-text-primary);
    }

    #sucursalInfo i {
        color: var(--win-accent);
        margin-right: 6px;
    }

    #sucursalInfo strong {
        font-weight: 600;
        color: var(--win-text-primary);
    }

    #sucursalInfo .text-muted {
        color: var(--win-text-secondary) !important;
        font-size: 12px;
    }

    /* Icono de información */
    .info-icon {
        color: var(--win-accent);
        font-size: 16px;
        margin-left: 6px;
        cursor: pointer;
        opacity: 0.8;
        transition: all 0.2s ease;
        vertical-align: middle;
    }

    .info-icon:hover {
        opacity: 1;
        transform: scale(1.1);
    }

    /* Campos de información bancaria */
    input[name="banco"],
    input[name="sucursal"],
    input[name="bancocliente"] {
        background: var(--win-bg-tertiary) !important;
        border: 1px solid var(--win-border-color) !important;
        color: var(--win-text-primary) !important;
        font-size: 14px !important;
    }

    input[name="sucursal"] {
        border-left: 3px solid var(--win-accent) !important;
    }

    /* Estados de validación */
    .is-valid {
        border-color: #107c10 !important;
    }

    .is-invalid {
        border-color: #e81123 !important;
    }

    /* Estilos para elementos desconocidos */
    .banco-desconocido {
        color: #e81123 !important;
        font-weight: 600 !important;
        background: linear-gradient(135deg, rgba(232, 17, 35, 0.05), transparent) !important;
        padding: 2px 6px !important;
        border-radius: 4px !important;
    }

    .sucursal-no-identificada {
        color: #ff8c00 !important;
        font-weight: 600 !important;
        background: linear-gradient(135deg, rgba(255, 140, 0, 0.05), transparent) !important;
        padding: 2px 6px !important;
        border-radius: 4px !important;
    }

    /* Colores de texto para iconos */
    .text-danger {
        color: #e81123 !important;
    }

    .text-warning {
        color: #ff8c00 !important;
    }

    .text-success {
        color: #107c10 !important;
    }


    /* Variables RGB para efectos de sombra */
    :root {
        --win-accent-rgb: 0, 120, 212;
    }

    [data-accent="#107c10"] { --win-accent-rgb: 16, 124, 16; }
    [data-accent="#5c2d91"] { --win-accent-rgb: 92, 45, 145; }
    [data-accent="#e81123"] { --win-accent-rgb: 232, 17, 35; }
    [data-accent="#ff8c00"] { --win-accent-rgb: 255, 140, 0; }
    [data-accent="#0099bc"] { --win-accent-rgb: 0, 153, 188; }
    [data-accent="#e3008c"] { --win-accent-rgb: 227, 0, 140; }
    [data-accent="#8764b8"] { --win-accent-rgb: 135, 100, 184; }


    /* Estilos adicionales para campos relacionados */
    input[name="cuenta_bancaria"] {
        background: linear-gradient(135deg, var(--win-bg-tertiary), color-mix(in srgb, var(--win-bg-tertiary) 90%, transparent)) !important;
        border: 1px solid var(--win-border-color) !important;
        color: var(--win-text-primary) !important;
        font-size: 15px !important;
        padding: 10px 12px !important;
        transition: all 0.2s ease !important;
        font-family: 'Segoe UI', monospace !important;
    }

    input[name="cuenta_bancaria"]:focus {
        border-color: var(--win-accent) !important;
        box-shadow: 0 0 0 3px rgba(var(--win-accent-rgb), 0.15) !important;
        background: linear-gradient(135deg, color-mix(in srgb, var(--win-bg-tertiary) 95%, white), var(--win-bg-tertiary)) !important;
    }

    input[name="cuenta_bancaria"].is-valid {
        border-color: #107c10 !important;
        background: linear-gradient(135deg, rgba(16, 124, 16, 0.05), var(--win-bg-tertiary)) !important;
    }

    input[name="cuenta_bancaria"].is-invalid {
        border-color: #e81123 !important;
        background: linear-gradient(135deg, rgba(232, 17, 35, 0.05), var(--win-bg-tertiary)) !important;
    }

    /* Para campos bancarios readonly */
    input[name="banco"],
    input[name="sucursal"],
    input[name="bancocliente"] {
        background: var(--win-bg-tertiary) !important;
        border: 1px solid var(--win-border-color) !important;
        color: var(--win-text-primary) !important;
        font-weight: 500 !important;
    }

    input.banco-desconocido {
        border-left: 3px solid #e81123 !important;
        animation: pulseWarning 2s infinite;
    }

    input.sucursal-no-identificada {
        border-left: 3px solid #ff8c00 !important;
        animation: pulseWarning 2s infinite;
    }

    @keyframes pulseWarning {
        0% { border-left-color: rgba(232, 17, 35, 0.5); }
        50% { border-left-color: #e81123; }
        100% { border-left-color: rgba(232, 17, 35, 0.5); }
    }

    /* Contenedor de información de sucursal */
    #sucursalInfo {
        display: none;
        background: var(--win-bg-tertiary);
        border: 1px solid var(--win-border-color);
        border-radius: var(--win-radius-sm);
        padding: 10px 12px;
        margin-top: 8px;
        font-size: 13px;
        color: var(--win-text-primary);
        line-height: 1.4;
        animation: fadeIn 0.3s ease;
    }

    #sucursalInfo i {
        color: var(--win-accent);
        margin-right: 6px;
        font-size: 14px;
    }

    #sucursalInfo strong {
        font-weight: 600;
        color: var(--win-text-primary);
    }

    #sucursalInfo .text-muted {
        color: var(--win-text-secondary) !important;
        font-size: 12px;
        display: block;
        margin-top: 2px;
    }

    /* Información adicional */
    #infoAdicional {
        margin-top: 12px;
    }

    #infoAdicional .alert {
        font-size: 12px;
        padding: 10px 12px;
        margin: 8px 0;
        border-radius: var(--win-radius-sm);
        line-height: 1.5;
    }

    #infoAdicional .alert-success {
        background: linear-gradient(135deg, rgba(16, 124, 16, 0.08), rgba(16, 124, 16, 0.12)) !important;
        border: 1px solid rgba(16, 124, 16, 0.2) !important;
        color: #107c10 !important;
    }

    #infoAdicional .alert-warning {
        background: linear-gradient(135deg, rgba(255, 140, 0, 0.08), rgba(255, 140, 0, 0.12)) !important;
        border: 1px solid rgba(255, 140, 0, 0.2) !important;
        color: #ff8c00 !important;
    }

    /* Feedback de validación */
    .invalid-feedback {
        display: none;
        font-size: 12px;
        color: #e81123;
        margin-top: 6px;
        padding-left: 4px;
        animation: fadeIn 0.3s ease;
    }

    .is-invalid {
        border-color: #e81123 !important;
        background: linear-gradient(135deg, rgba(232, 17, 35, 0.05), var(--win-bg-tertiary)) !important;
    }

    .is-valid {
        border-color: #107c10 !important;
        background: linear-gradient(135deg, rgba(16, 124, 16, 0.05), var(--win-bg-tertiary)) !important;
    }

    /* Animación general */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Estilos para SweetAlert con tema oscuro */
    .sweetalert-dark {
        background-color: var(--win-bg-secondary) !important;
        border: 1px solid var(--win-border-color) !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    }

    .sweetalert-title-dark {
        color: var(--win-text-primary) !important;
    }

    .sweetalert-content-dark {
        color: var(--win-text-primary) !important;
    }

    .sweetalert-confirm-dark {
        background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 80%, #000)) !important;
        border: none !important;
        font-weight: 500 !important;
    }

    .sweetalert-cancel-dark {
        background: var(--win-bg-tertiary) !important;
        border: 1px solid var(--win-border-color) !important;
        color: var(--win-text-primary) !important;
        font-weight: 500 !important;
    }

    .sweetalert-confirm-dark:hover {
        background: linear-gradient(135deg, color-mix(in srgb, var(--win-accent) 90%, #000), color-mix(in srgb, var(--win-accent) 70%, #000)) !important;
    }

    .sweetalert-cancel-dark:hover {
        background: var(--win-bg-tertiary) !important;
        border-color: var(--win-text-secondary) !important;
        color: var(--win-text-primary) !important;
    }

    /* Estilos para los íconos de SweetAlert en modo oscuro */
    .swal2-icon.swal2-question {
        color: var(--win-accent) !important;
        border-color: var(--win-accent) !important;
    }

    .swal2-icon.swal2-error {
        color: #e81123 !important;
        border-color: #e81123 !important;
    }

    .swal2-icon.swal2-warning {
        color: #ff8c00 !important;
        border-color: #ff8c00 !important;
    }

    .swal2-icon.swal2-info {
        color: var(--win-accent) !important;
        border-color: var(--win-accent) !important;
    }

    .swal2-icon.swal2-success {
        color: #107c10 !important;
        border-color: #107c10 !important;
    }

    /* Contenedor del Tooltip Global */
    #global-tooltip {
        position: fixed; /* Flota sobre todo */
        display: none;   /* Oculto por defecto */
        z-index: 99999;  /* Siempre encima */
        pointer-events: none; /* No interfiere con los clics */
        
        /* Estilo Windows 11 */
        background: var(--win-bg-secondary);
        color: var(--win-text-primary);
        border: 1px solid var(--win-border-color);
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-family: 'Segoe UI', sans-serif;
        box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        
        /* Animación sutil */
        opacity: 0;
        transition: opacity 0.15s ease;
    }

    #global-tooltip.visible {
        display: block;
        opacity: 1;
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

    [data-theme="light"] ::placeholder {
        color: #666666 !important;
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

    /* Estilos para campos más pequeños */
    input[name="bancocliente"],
    input[name="tipocuenta"] {
        font-size: 13px !important;
        padding: 6px 10px !important;
        height: 38px !important;
    }

    .banco-desconocido {
        color: #e81123 !important;
        font-weight: 600 !important;
        border-color: #e81123 !important;
        background: linear-gradient(135deg, 
            rgba(232, 17, 35, 0.05),
            var(--win-bg-tertiary)) !important;
    }

    .sucursal-no-identificada {
        color: #ff8c00 !important;
        font-weight: 600 !important;
        border-color: #ff8c00 !important;
        background: linear-gradient(135deg, 
            rgba(255, 140, 0, 0.05),
            var(--win-bg-tertiary)) !important;
    }

    /* Para los campos de entrada con errores */
    input.banco-desconocido,
    input.sucursal-no-identificada {
        border-left: 3px solid !important;
    }

    input.banco-desconocido {
        border-left-color: #e81123 !important;
    }

    input.sucursal-no-identificada {
        border-left-color: #ff8c00 !important;
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
/* ==================== ESTILOS PARA DESGLOSE DE CUENTA BANCARIA ==================== */
/* Overlay del desglose */
/* Overlay: Fondo oscuro que cubre TODO */
.desglose-overlay {
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000 !important; /* Más alto que el navbar */
    display: none;
    backdrop-filter: blur(5px);
}

.desglose-overlay.open {
    display: block;
}

/* Cuadro del desglose: Centrado absoluto */
.cuenta-desglose {
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    z-index: 10001 !important; /* Siempre encima del overlay */
    width: 480px;
    max-width: 95vw;
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-accent);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.7);
    display: none;
    color: var(--win-text-primary);
}
/* Asegurar que se vea en móviles */
@media (max-width: 768px) {
    .cuenta-desglose {
        width: 95vw;
        max-height: 85vh;
        padding: 16px;
    }
}

/* Botón de cerrar - SIMPLIFICADO */
.desglose-close-btn {
    position: absolute !important;
    top: 12px !important;
    right: 12px !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background: var(--win-bg-tertiary) !important;
    border: 1px solid var(--win-border-color) !important;
    color: var(--win-text-primary) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    font-size: 14px !important;
    z-index: 10001 !important;
    padding: 0 !important;
    margin: 0 !important;
    transition: all 0.2s ease !important;
}

.desglose-close-btn:hover {
    background: #e81123 !important;
    color: white !important;
    border-color: #e81123 !important;
    transform: scale(1.1) !important;
}

/* Header del desglose */
.desglose-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--win-border-color);
}

.desglose-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 600;
    color: var(--win-text-primary);
    margin: 0;
}

.desglose-title i {
    color: var(--win-accent);
    font-size: 20px;
}

/* Items del desglose */
.desglose-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--win-border-color);
    font-size: 14px;
    flex-wrap: wrap;
}

.desglose-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.desglose-item span:first-child {
    color: var(--win-text-secondary);
    flex: 1;
    min-width: 120px;
    font-weight: 500;
}

.desglose-valor {
    font-weight: 500;
    color: var(--win-text-primary);
    text-align: right;
    flex: 2;
    max-width: 250px;
    word-break: break-word;
    line-height: 1.4;
}

/* Número formateado */
.desglose-numero {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 16px;
    letter-spacing: 1px;
    margin: 15px 0;
    text-align: center;
    padding: 12px;
    background: var(--win-bg-tertiary);
    border-radius: var(--win-radius);
    border: 1px solid var(--win-border-color);
    color: var(--win-text-primary);
    line-height: 1.6;
    overflow-wrap: break-word;
    word-break: break-all;
}

/* Alertas dentro del desglose */
.cuenta-desglose .alert {
    border-radius: var(--win-radius-sm);
    font-size: 13px;
    padding: 12px;
    margin: 12px 0;
    border-width: 1px;
    border-style: solid;
    line-height: 1.5;
}

.cuenta-desglose .alert-danger {
    background: rgba(232, 17, 35, 0.1) !important;
    border-color: rgba(232, 17, 35, 0.2) !important;
    color: #e81123 !important;
}

.cuenta-desglose .alert-warning {
    background: rgba(255, 140, 0, 0.1) !important;
    border-color: rgba(255, 140, 0, 0.2) !important;
    color: #ff8c00 !important;
}

.cuenta-desglose .alert-info {
    background: rgba(0, 120, 212, 0.1) !important;
    border-color: rgba(0, 120, 212, 0.2) !important;
    color: var(--win-text-primary) !important;
}

.cuenta-desglose .alert-success {
    background: rgba(16, 124, 16, 0.1) !important;
    border-color: rgba(16, 124, 16, 0.2) !important;
    color: #107c10 !important;
}

/* Scrollbar personalizado */
.cuenta-desglose::-webkit-scrollbar {
    width: 6px;
}

.cuenta-desglose::-webkit-scrollbar-track {
    background: var(--win-bg-tertiary);
    border-radius: 3px;
}

.cuenta-desglose::-webkit-scrollbar-thumb {
    background: var(--win-accent);
    border-radius: 3px;
}

.cuenta-desglose::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--win-accent) 80%, #000);
}

/* Botón "Entendido" */
.cuenta-desglose .btn-primary {
    background: var(--win-accent) !important;
    border: none !important;
    border-radius: var(--win-radius-sm) !important;
    padding: 10px 20px !important;
    font-weight: 500 !important;
    font-size: 14px !important;
    transition: all 0.2s ease !important;
    margin-top: 15px !important;
    width: 100% !important;
}

.cuenta-desglose .btn-primary:hover {
    background: color-mix(in srgb, var(--win-accent) 90%, #000) !important;
    transform: translateY(-2px);
}
/* ==================== FIN ESTILOS PARA DESGLOSE ==================== */

/* ==================== FIX PARA FLECHA DE SELECTS ==================== */
/* Asegurar que los selects muestren la flecha de dropdown */
.win-main-content select.form-control,
.win-main-content select.form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 16px 12px !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    padding-right: 2.5rem !important;
}

/* Para tema claro - flecha negra */
[data-theme="light"] .win-main-content select.form-control,
[data-theme="light"] .win-main-content select.form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23000000' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
}

/* Específico para los selects de período contable */
select[name="mes_operaciones"],
select[name="anio_operaciones"] {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 16px 12px !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    padding-right: 2.5rem !important;
}

[data-theme="light"] select[name="mes_operaciones"],
[data-theme="light"] select[name="anio_operaciones"] {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23000000' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
}
/* ==================== FIN FIX PARA FLECHA DE SELECTS ==================== */

/* ==================== ESTILOS PARA MODAL DE RECORTE ==================== */
.modal-xl {
    max-width: 1200px;
}

.img-container {
    background-color: #2d2d2d;
    background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                      linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                      linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                      linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    min-height: 400px;
    border-radius: 8px;
    overflow: hidden;
}

.preview-container {
    background-color: #2d2d2d;
    background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                      linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                      linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                      linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    border: 3px solid var(--win-accent);
    border-radius: 8px;
    overflow: hidden;
}

#previewCanvas {
    width: 100%;
    height: 100%;
    display: block;
}

.cropper-view-box {
    outline: 2px solid var(--win-accent);
    outline-color: var(--win-accent);
    box-shadow: 0 0 0 1px white;
}

.cropper-line {
    background-color: var(--win-accent);
}

.cropper-point {
    background-color: var(--win-accent);
    width: 8px;
    height: 8px;
    opacity: 1;
}

.crop-controls .btn {
    text-align: left;
    padding: 10px 15px;
}

.crop-controls .btn i {
    width: 20px;
    text-align: center;
}
/* ==================== FIN ESTILOS MODAL DE RECORTE ==================== */

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
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini" <?php echo $sidebar_mini ? 'checked' : ''; ?>>
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
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="win-navbar-brand">
            <i class="fas fa-eye"></i>
            <span style="color: var(--win-text-primary);">CONFIGURACIONES - SISFACT IMDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
			<i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <div style="flex: 1;"></div>
        
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
<?= renderNotificationsDropdown() ?>
        
        <!-- Perfil de usuario -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar position-relative overflow-hidden" 
                     style="width: 32px; height: 32px; border-radius: 50%;">
                    <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" 
                             alt="Avatar" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; 
                                    background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, #ffffff)); 
                                    color: white; font-weight: 600; border-radius: 50%;">
                            <?php echo $avatar_placeholder; ?>
                        </div>
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
                        <div class="position-relative overflow-hidden me-2" 
                             style="width: 32px; height: 32px; border-radius: 50%;">
                            <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" 
                                     alt="Avatar" 
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; 
                                            background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, #ffffff)); 
                                            color: white; font-weight: 600; font-size: 14px; border-radius: 50%;">
                                    <?php echo $avatar_placeholder; ?>
                                </div>
                            <?php endif; ?>
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
                    <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo $avatar_placeholder; ?>
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
            </li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo isset($total_facturas) ? $total_facturas : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo isset($total_clientes) ? $total_clientes : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-layer-group"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo isset($total_categorias) ? $total_categorias : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo isset($total_servicios) ? $total_servicios : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo isset($total_usuarios) ? $total_usuarios : '0'; ?></span>
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
            </li>            <li class="win-nav-item">
                <a href="configuracion.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-cog"></i>
                    <span class="win-nav-text">Configuración</span>
					<?php if ($es_admin): ?>
                    <span class="win-nav-badge admin-badge" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-crown"></i>
                    </span>
                    <?php endif; ?>
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
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: '¡Atención!',
                        html: '<?php echo addslashes($error); ?>',
                        confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                        confirmButtonColor: '#d33',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)',
                        customClass: {
                            popup: 'sweetalert-dark',
                            title: 'sweetalert-title-dark',
                            htmlContainer: 'sweetalert-content-dark',
                            confirmButton: 'sweetalert-confirm-dark'
                        }
                    });
                });
            </script>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Operación Exitosa!',
                        text: '<?php echo addslashes($success); ?>',
                        timer: 2500,
                        showConfirmButton: false,
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)',
                        customClass: {
                            popup: 'sweetalert-dark',
                            title: 'sweetalert-title-dark',
                            htmlContainer: 'sweetalert-content-dark'
                        }
                    });
                });
            </script>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-cog me-2" style="color: var(--win-accent);"></i>Configuración del Sistema
                </h1>
                <p class="text-muted mb-0">Configure los parámetros generales del sistema - Fecha de Cierre Operaciones: <span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span></p>
            </div>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <button type="button" class="btn btn-sm btn-outline-primary" title="Regresar al Inicio" onclick="volverAlDashboard()">
            <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
        </button>
        <button type="button" title="Cancelar Cambios y Recargar Datos" class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync-alt me-1"></i>
        </button>
        <!-- NUEVO BOTÓN EXPANDIR/COLAPSAR TODAS -->
        <button type="button" id="btnExpandirTodas" class="btn btn-sm btn-outline-info" title="Expandir/Colapsar todas las secciones" onclick="toggleAllCards()">
            <i class="fas fa-expand-alt me-1"></i>Expandir Todas
        </button>
        <button type="button" class="btn btn-sm btn-primary" title="Guardar Cambios" onclick="confirmarGuardarConfiguracion()">
            <i class="fas fa-save me-1"></i>Guardar
        </button>
    </div>
</div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" id="configForm" autocomplete="off">
            <!-- Inputs ocultos para marcar eliminación -->
            <input type="hidden" name="eliminar_logo" id="eliminarLogoInput" value="0">
            <input type="hidden" name="eliminar_fondo" id="eliminarFondoInput" value="0">
            
            <!-- Inputs ocultos para imágenes recortadas -->
            <input type="hidden" name="logo_recortado" id="logo_recortado">
            <input type="hidden" name="fondo_recortado" id="fondo_recortado">
            
            <!-- Información de la Empresa -->
            <div class="card mica-effect animate__animated animate__fadeInUp" style="border-left: 4px solid var(--win-accent);">
                <div class="card-header">
                    <h6 class="mb-0" style="color: var(--win-accent);"><i class="fa fa-coins" style="color: #007bff; font-size: 24px;"></i>  Información de la Empresa, PDL IMDL, MIPYMES, etc.</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Nombre de la Empresa *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-industry text-muted"></i></span>
                                <input type="text" class="form-control" name="nombre_empresa"  
                                       value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? 'Empresa Municipal de Comercio y Gastronomía de Nuevitas'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Nombre del Proyecto *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-project-diagram text-muted"></i></span>
                                <input type="text" class="form-control" name="nombre_proyecto"  
                                       value="<?php echo htmlspecialchars($config['nombre_proyecto'] ?? 'Proyecto de Desarrollo Local "Visiones"'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Dirección</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                <textarea class="form-control" name="direccion" rows="1"><?php echo htmlspecialchars($config['direccion'] ?? 'Calle Máximo Gómez. S/N e/Camilo Cienfuegos y Lugareño. Nuevitas, Camagüey.'); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fila con Código REUP, NIT, Teléfono y Email -->
                    <div class="row mb-3">
                        <!-- Código REUP -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Código REUP</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-barcode text-muted"></i></span>
                                <?php 
                                if (!function_exists('detectarTipoREUP')) {
                                    function detectarTipoREUP($codigo) {
                                        if (empty($codigo) || !preg_match('/^(\d{3})\.(\d)\.(\d{4})$/', $codigo, $matches)) {
                                            return [
                                                'org' => 'No identificado', 
                                                'nat' => ['clase' => 'bg-danger', 'text' => 'Formato Inválido', 'icon' => 'fa-times']
                                            ];
                                        }
                                        
                                        $codOrg = $matches[1];
                                        $tipoDigito = (int)$matches[2];

                                        $organismos_reales = [
                                            "101" => "Asamblea Nacional del Poder Popular", "102" => "Consejo de Estado", "103" => "Consejo de Ministros",
                                            "104" => "Tribunal Supremo Popular", "105" => "Fiscalía General de la República", "106" => "Contraloría General de la República",
                                            "111" => "MINREX", "112" => "MINFAR", "113" => "MININT", "114" => "MINJUS", "115" => "MINCIN",
                                            "116" => "MINCEX", "117" => "MINEM", "118" => "MINSAP", "119" => "MINED", "120" => "MES",
                                            "121" => "MTSS", "122" => "MINAL", "123" => "MINAG", "124" => "MINCULT", "125" => "INDER",
                                            "126" => "MITRANS", "127" => "MICONS", "128" => "MINCOM", "129" => "MINTUR", "130" => "MFP",
                                            "131" => "MEP", "132" => "CITMA", "133" => "MINDUS", "134" => "BCC", "135" => "IICS",
                                            "136" => "INRH", "138" => "Aduana General", "142" => "OSDE", "201" => "PCC", "202" => "UJC",
                                            "203" => "CTC", "204" => "ANAP", "205" => "FMC", "206" => "CDR", "300" => "Gobierno Pinar del Río",
                                            "301" => "Gobierno La Habana", "302" => "Gobierno Ciudad de La Habana", "303" => "Gobierno Matanzas",
                                            "304" => "Gobierno Villa Clara", "305" => "Gobierno Cienfuegos", "306" => "Gobierno Sancti Spíritus",
                                            "307" => "Gobierno Ciego de Ávila", "308" => "Gobierno Camagüey", "309" => "Gobierno Las Tunas",
                                            "310" => "Gobierno Holguín", "311" => "Gobierno Granma", "312" => "Gobierno Santiago de Cuba",
                                            "313" => "Gobierno Guantánamo", "314" => "Gobierno Isla de la Juventud", "315" => "Gobierno Artemisa",
                                            "316" => "Gobierno Mayabeque", "317" => "Entidades Locales Construcción", "318" => "Entidades Locales Transporte",
                                            "319" => "Comercio y Gastronomía Local", "320" => "Entidades Servicios Comunales"
                                        ];

                                        $naturalezas_reales = [
                                            0 => ['clase' => 'bg-primary', 'text' => 'Empresa Estatal', 'icon' => 'fa-building'],
                                            1 => ['clase' => 'bg-warning text-dark', 'text' => 'CPA', 'icon' => 'fa-tractor'],
                                            2 => ['clase' => 'bg-dark', 'text' => 'Empresa Mixta', 'icon' => 'fa-handshake'],
                                            3 => ['clase' => 'bg-secondary', 'text' => 'Sociedad Mercantil', 'icon' => 'fa-briefcase'],
                                            4 => ['clase' => 'bg-warning text-dark', 'text' => 'UBPC', 'icon' => 'fa-users-cog'],
                                            5 => ['clase' => 'bg-info text-dark', 'text' => 'Unidad Presupuestada', 'icon' => 'fa-university'],
                                            6 => ['clase' => 'bg-success', 'text' => 'CNA', 'icon' => 'fa-store-alt'],
                                            7 => ['clase' => 'bg-success', 'text' => 'MIPYME / TCP', 'icon' => 'fa-user-tie'],
                                            8 => ['clase' => 'bg-light text-dark border', 'text' => 'Asociación / ONG', 'icon' => 'fa-church'],
                                            9 => ['clase' => 'bg-danger', 'text' => 'Otros', 'icon' => 'fa-globe']
                                        ];

                                        return [
                                            'org' => $organismos_reales[$codOrg] ?? "Organismo $codOrg",
                                            'nat' => $naturalezas_reales[$tipoDigito] ?? ['clase' => 'bg-light text-dark', 'text' => 'No definido', 'icon' => 'fa-question']
                                        ];
                                    }
                                }

                                $codigo_actual = isset($config['cod_reeup']) ? $config['cod_reeup'] : '319.2.5400'; 
                                $analisis = detectarTipoREUP($codigo_actual); 
                                $info = $analisis['nat'];
                                ?>
                                <input type="text" class="form-control" id="cod_reeup" name="cod_reeup" 
                                       value="<?php echo htmlspecialchars($codigo_actual); ?>"
                                       placeholder="319.2.5400" maxlength="10"
                                       oninput="detectarTipoREUP(this.value)">
    
                                <span class="input-group-text p-1 bg-transparent border-start-0">
                                    <span id="badge-tipo-empresa" 
                                          class="badge <?php echo $info['clase']; ?> px-3 py-2" 
                                          onclick="mostrarDetalleReeup()" 
                                          style="cursor:pointer;" 
                                          title="Haga clic para ver análisis detallado">
                                        <i class="fas <?php echo $info['icon']; ?> me-1"></i>
                                        <?php echo $info['text']; ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- NIT -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">No. Ident. Tributario (NIT)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-id-card text-muted"></i></span>
                                <?php
                                // Función para analizar el NIT
                                function analizarNIT($nit) {
                                    if (empty($nit) || strlen($nit) !== 11 || !is_numeric($nit)) {
                                        return [
                                            'valido' => false,
                                            'mensaje' => 'NIT inválido (debe tener 11 dígitos)',
                                            'badge_class' => 'bg-danger',
                                            'badge_text' => 'Inválido',
                                            'badge_icon' => 'fa-times'
                                        ];
                                    }
                                    
                                    $tipos_entidad = [
                                        '1' => ['text' => 'Persona Natural', 'class' => 'bg-info', 'icon' => 'fa-user'],
                                        '2' => ['text' => 'Persona Jurídica', 'class' => 'bg-primary', 'icon' => 'fa-building'],
                                        '3' => ['text' => 'Extranjero sin identificación', 'class' => 'bg-warning text-dark', 'icon' => 'fa-passport'],
                                        '5' => ['text' => 'Agrupación de ciudadanos', 'class' => 'bg-secondary', 'icon' => 'fa-users'],
                                        '9' => ['text' => 'Otros', 'class' => 'bg-light text-dark border', 'icon' => 'fa-ellipsis-h']
                                    ];
                                    
                                    $paises = [
                                        '1' => 'Cuba',
                                        '2' => 'Extranjero',
                                        '3' => 'Extranjero residente en Cuba'
                                    ];
                                    
                                    $provincias = [
                                        '01' => 'Pinar del Río',
                                        '02' => 'La Habana (Provincia)',
                                        '03' => 'Ciudad de La Habana',
                                        '04' => 'Matanzas',
                                        '05' => 'Villa Clara',
                                        '06' => 'Cienfuegos',
                                        '07' => 'Sancti Spíritus',
                                        '08' => 'Ciego de Ávila',
                                        '09' => 'Camagüey',
                                        '10' => 'Las Tunas',
                                        '11' => 'Holguín',
                                        '12' => 'Granma',
                                        '13' => 'Santiago de Cuba',
                                        '14' => 'Guantánamo',
                                        '15' => 'Isla de la Juventud',
                                        '16' => 'Artemisa',
                                        '17' => 'Mayabeque'
                                    ];
                                    
                                    $tipo_entidad = $tipos_entidad[$nit[0]] ?? ['text' => 'Desconocido', 'class' => 'bg-light text-dark', 'icon' => 'fa-question'];
                                    
                                    return [
                                        'valido' => true,
                                        'tipo_entidad' => $tipo_entidad['text'],
                                        'tipo_entidad_class' => $tipo_entidad['class'],
                                        'tipo_entidad_icon' => $tipo_entidad['icon'],
                                        'pais' => $paises[$nit[1]] ?? 'Desconocido',
                                        'provincia' => $provincias[substr($nit, 2, 2)] ?? 'Desconocida',
                                        'municipio_cod' => substr($nit, 4, 3),
                                        'secuencial' => substr($nit, 7, 3),
                                        'verificador' => $nit[10],
                                        'formateado' => substr($nit, 0, 3) . '-' . 
                                                       substr($nit, 3, 3) . '-' . 
                                                       substr($nit, 6, 3) . '-' . 
                                                       substr($nit, 9, 2),
                                        'badge_class' => $tipo_entidad['class'],
                                        'badge_text' => $tipo_entidad['text'],
                                        'badge_icon' => $tipo_entidad['icon']
                                    ];
                                }

                                $nit_actual = isset($config['cod_nit']) ? $config['cod_nit'] : ''; 
                                $analisis_nit = analizarNIT($nit_actual);
                                ?>
                                <input type="text" class="form-control" id="cod_nit" name="cod_nit"
                                       maxlength="11" 
                                       placeholder="Solo 11 dígitos"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, ''); validarEstiloNit(this); analizarNITJS(this.value);"
                                       value="<?php echo htmlspecialchars($nit_actual); ?>">
                                <span class="input-group-text p-1 bg-transparent border-start-0">
                                    <span id="badge-tipo-nit" 
                                          class="badge <?php echo $analisis_nit['badge_class']; ?> px-3 py-2" 
                                          style="cursor:pointer;" 
                                          title="Haga clic para ver análisis detallado"
                                          onclick="mostrarDetalleNit()">
                                        <i class="fas <?php echo $analisis_nit['badge_icon']; ?> me-1"></i>
                                        <?php echo $analisis_nit['badge_text']; ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- Teléfono -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Teléfono</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-success" onclick="makeCall()" title="Llamar">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <input type="text" class="form-control" id="telefono" name="telefono"
                                       value="<?php echo htmlspecialchars($config['telefono'] ?? '+5359860773'); ?>">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Email</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-primary" onclick="sendMail()" title="Enviar correo">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($config['email'] ?? 'info@sisfact-imdl.cu'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Análisis del código REUP y NIT -->
                    <div class="row mb-3">
                        <!-- Análisis REUP -->
                        <div class="col-md-6">
                            <div class="p-2 rounded" style="background-color: var(--win-bg-tertiary); border: 1px dashed var(--win-border-color);">
                                <small class="text-muted d-block">
                                    <i class="fas fa-microchip text-primary me-2"></i>
                                    <strong>Análisis REUP:</strong> 
                                    <span id="analisis-reeup-detalle" style="color: var(--win-text-primary);">
                                        <?php 
                                        if (preg_match('/^(\d{3})\.(\d)\.(\d{4})$/', $codigo_actual, $matches)) {
                                            echo "Subordinación: <strong>" . $analisis['org'] . "</strong> | Gestión: <strong>" . $info['text'] . "</strong>";
                                        } else {
                                            echo "Esperando formato correcto...";
                                        }
                                        ?>
                                    </span>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Análisis NIT -->
                        <div class="col-md-6">
                            <div class="p-2 rounded" style="background-color: var(--win-bg-tertiary); border: 1px dashed var(--win-border-color);">
                                <small class="text-muted d-block">
                                    <i class="fas fa-id-card text-success me-2"></i>
                                    <strong>Análisis NIT:</strong> 
                                    <span id="analisis-nit-detalle" style="color: var(--win-text-primary);">
                                        <?php 
                                        if ($analisis_nit['valido']) {
                                            echo "Tipo: <strong>" . $analisis_nit['tipo_entidad'] . "</strong> | ";
                                            echo "Provincia: <strong>" . $analisis_nit['provincia'] . "</strong>";
                                        } else {
                                            echo $analisis_nit['mensaje'];
                                        }
                                        ?>
                                    </span>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Sitio Web -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Sitio Web</label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-warning" onclick="testWeb()" title="Visitar sitio">
                                        <i class="fas fa-globe"></i>
                                    </button>
                                    <input type="text" class="form-control" id="sitio_web" name="sitio_web"
                                           value="<?php echo htmlspecialchars($config['sitio_web'] ?? 'www.sisfact-imdl.cu'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- JavaScript para análisis dinámico del NIT -->
            <script>
            function analizarNITJS(nit) {
                if (nit.length === 11 && /^\d+$/.test(nit)) {
                    // Tipos de entidad
                    const tipos_entidad = {
                        '1': {text: 'Persona Natural', class: 'bg-info', icon: 'fa-user'},
                        '2': {text: 'Persona Jurídica', class: 'bg-primary', icon: 'fa-building'},
                        '3': {text: 'Extranjero sin identificación', class: 'bg-warning text-dark', icon: 'fa-passport'},
                        '5': {text: 'Agrupación de ciudadanos', class: 'bg-secondary', icon: 'fa-users'},
                        '9': {text: 'Otros', class: 'bg-light text-dark border', icon: 'fa-ellipsis-h'}
                    };
                    
                    // Países
                    const paises = {
                        '1': 'Cuba',
                        '2': 'Extranjero',
                        '3': 'Extranjero residente en Cuba'
                    };
                    
                    // Provincias
                    const provincias = {
                        '01': 'Pinar del Río',
                        '02': 'La Habana (Provincia)',
                        '03': 'Ciudad de La Habana',
                        '04': 'Matanzas',
                        '05': 'Villa Clara',
                        '06': 'Cienfuegos',
                        '07': 'Sancti Spíritus',
                        '08': 'Ciego de Ávila',
                        '09': 'Camagüey',
                        '10': 'Las Tunas',
                        '11': 'Holguín',
                        '12': 'Granma',
                        '13': 'Santiago de Cuba',
                        '14': 'Guantánamo',
                        '15': 'Isla de la Juventud',
                        '16': 'Artemisa',
                        '17': 'Mayabeque'
                    };
                    
                    const tipo = tipos_entidad[nit[0]] || {text: 'Desconocido', class: 'bg-light text-dark', icon: 'fa-question'};
                    const pais = paises[nit[1]] || 'Desconocido';
                    const provincia = provincias[nit.substring(2, 4)] || 'Desconocida';
                    const formateado = nit.substring(0, 3) + '-' + nit.substring(3, 6) + '-' + nit.substring(6, 9) + '-' + nit.substring(9, 11);
                    
                    // Actualizar badge
                    const badge = document.getElementById('badge-tipo-nit');
                    badge.className = 'badge ' + tipo.class + ' px-3 py-2';
                    badge.innerHTML = '<i class="fas ' + tipo.icon + ' me-1"></i>' + tipo.text;
                    
                    // Actualizar análisis
                    document.getElementById('analisis-nit-detalle').innerHTML = 
                        'Tipo: <strong>' + tipo.text + '</strong> | ' +
                        'Provincia: <strong>' + provincia + '</strong>';
                } else if (nit.length === 0) {
                    // Campo vacío
                    document.getElementById('badge-tipo-nit').className = 'badge bg-light text-dark border px-3 py-2';
                    document.getElementById('badge-tipo-nit').innerHTML = '<i class="fas fa-id-card me-1"></i>Sin NIT';
                    document.getElementById('analisis-nit-detalle').innerHTML = 'Ingrese un NIT de 11 dígitos';
                } else if (nit.length < 11) {
                    // Incompleto
                    document.getElementById('badge-tipo-nit').className = 'badge bg-warning text-dark px-3 py-2';
                    document.getElementById('badge-tipo-nit').innerHTML = '<i class="fas fa-clock me-1"></i>Incompleto';
                    document.getElementById('analisis-nit-detalle').innerHTML = 'Faltan ' + (11 - nit.length) + ' dígitos';
                } else {
                    // Inválido
                    document.getElementById('badge-tipo-nit').className = 'badge bg-danger px-3 py-2';
                    document.getElementById('badge-tipo-nit').innerHTML = '<i class="fas fa-times me-1"></i>Inválido';
                    document.getElementById('analisis-nit-detalle').innerHTML = 'NIT inválido (solo 11 dígitos)';
                }
            }
            
            // Función para validar estilo del NIT (ya existente)
            function validarEstiloNit(input) {
                if (input.value.length === 11 && /^\d+$/.test(input.value)) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                    if (input.value.length > 0 && input.value.length < 11) {
                        input.classList.add('is-invalid');
                    }
                }
            }
            
            // Función para mostrar detalles del NIT (puedes implementar un modal)
            function mostrarDetalleNit() {
                const nit = document.getElementById('cod_nit').value;
                if (nit.length === 11 && /^\d+$/.test(nit)) {
                    // Aquí puedes implementar un modal con análisis detallado
                    alert('Análisis detallado del NIT:\n\n' +
                          'NIT completo: ' + nit + '\n' +
                          'Formateado: ' + nit.substring(0, 3) + '-' + nit.substring(3, 6) + '-' + nit.substring(6, 9) + '-' + nit.substring(9, 11) + '\n' +
                          'Tipo de entidad: ' + document.getElementById('badge-tipo-nit').textContent + '\n' +
                          'Provincia: ' + document.getElementById('analisis-nit-detalle').textContent.split('Provincia: ')[1]);
                }
            }
            
            // Inicializar análisis del NIT al cargar la página
            document.addEventListener('DOMContentLoaded', function() {
                const nitInput = document.getElementById('cod_nit');
                if (nitInput && nitInput.value) {
                    analizarNITJS(nitInput.value);
                }
            });
            </script>
<!-- Información Bancaria -->
<div class="card mica-effect animate__animated animate__fadeInUp" style="border-left: 4px solid var(--win-accent);">
    <div class="card-header">
        <h6 class="mb-0" style="color: var(--win-accent);">
            <i class="fa fa-coins" style="color: #007bff; font-size: 20px; margin-right: 8px;"></i> 
            Información Bancaria
        </h6>
    </div>
<div class="card-body">
    <!-- FILA 1: Cuenta (4) y Banco (8) -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label d-flex justify-content-between align-items-center">
                <span>Cuenta Bancaria <i class="fas fa-info-circle info-icon ms-1" onclick="toggleDesgloseCuenta(event)" style="cursor:pointer; color: var(--win-accent);"></i></span>
                <span class="badge bg-success" style="font-size: 10px;">14/16 dígitos</span>
            </label>
            <input type="text" class="form-control" name="cuenta_bancaria" id="cuentaBancaria"
                value="<?php echo htmlspecialchars($config['cuenta_bancaria'] ?? ''); ?>"
                oninput="actualizarDesglose()" maxlength="16">
        </div>

        <div class="col-md-8">
            <label class="form-label">Banco Institución (Automático)</label>
            <input type="text" class="form-control fw-bold" name="banco" id="nombreBanco"
                value="<?php echo htmlspecialchars($config['banco'] ?? ''); ?>" readonly 
                style="background-color: var(--win-bg-secondary); color: var(--win-accent) !important;">
        </div>
    </div>

    <!-- FILA 2: Sucursal (2), Tipo (5), No. Cuenta (5) -->
    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <label class="form-label">Sucursal</label>
            <input type="text" class="form-control text-center fw-bold" name="sucursal" id="nombreSucursal" readonly>
        </div>
        <div class="col-md-5">
            <label class="form-label">Tipo de Cuenta</label>
            <input type="text" class="form-control" name="tipocuenta" id="tipoCuentaInput" readonly>
        </div>
        <div class="col-md-5">
            <label class="form-label">Número de Cliente</label>
            <input type="text" class="form-control fw-bold" name="bancocliente" id="nocliente" readonly>
        </div>
    </div>

    <!-- FILA 3: Información detallada de Sucursal -->
    <div class="row">
        <div class="col-12">
            <div id="sucursalInfo" class="w-100 rounded border p-3" 
                 style="background-color: var(--win-bg-secondary); min-height: 45px; border-style: dashed !important;">
                <span class="text-muted small">Análisis de cuenta bancaria...</span>
            </div>
            <!-- Div para tus alertas de "Cuenta válida pero con advertencias" -->
            <div id="infoAdicional" class="mt-2"></div>
        </div>
    </div>
</div>
</div>

            <!-- Logos y Diseño -->
            <div class="card mica-effect animate__animated animate__fadeInUp" style="border-left: 4px solid var(--win-accent);">
                <div class="card-header">
                    <h6 class="mb-0" style="color: var(--win-accent);"><i class="fa fa-palette" style="color: #007bff; font-size: 24px;"></i> Logos y Diseño</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Logo de la Empresa</label>
                                <input type="file" class="form-control" name="logo" accept="image/*" id="logoInput" onchange="cargarImagenParaRecorte(this, 'logo')">
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF, SVG. Máx. 2MB. Se redimensionará automáticamente.</small>
                                
                                <div class="image-preview-container mt-3" id="logoPreviewContainer">
                                    <?php if (!empty($config['logo'])): ?>
                                        <?php if (strpos($config['logo'], 'assets/imagenes/') === 0 && file_exists($config['logo'])): ?>
                                            <p class="mb-2"><strong>Logo actual:</strong></p>
                                            <div class="position-relative" style="max-width: 200px;">
                                                <img src="<?php echo htmlspecialchars($config['logo'] . '?t=' . time()); ?>" 
                                                    class="image-preview img-thumbnail" 
                                                    id="logoPreview"
                                                    alt="Logo actual">
                                                <!-- Botón flotante para eliminar -->
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                                        onclick="eliminarLogo(event)"
                                                        title="Eliminar logo">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Tamaño: <?php echo round(filesize($config['logo']) / 1024, 2); ?> KB
                                            </small>
                                        <?php else: ?>
                                            <p class="mb-2"><strong>Logo actual (base64):</strong></p>
                                            <div class="position-relative" style="max-width: 200px;">
                                                <img src="data:image/png;base64,<?php echo $config['logo']; ?>" 
                                                    class="image-preview img-thumbnail" 
                                                    id="logoPreview"
                                                    alt="Logo actual">
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                                        onclick="eliminarLogo(event)"
                                                        title="Eliminar logo">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Tamaño: <?php echo number_format(strlen($config['logo']) / 1024, 2); ?> KB
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="mb-2"><strong>Vista previa:</strong></p>
                                        <img src="" class="image-preview img-thumbnail d-none" 
                                            id="logoPreview" 
                                            style="max-width: 200px;"
                                            alt="Vista previa del logo">
                                        <small class="text-muted d-block">No hay logo cargado</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fondo del Sistema</label>
                                <input type="file" class="form-control" name="fondo_web" accept="image/*" id="fondoInput" onchange="cargarImagenParaRecorte(this, 'fondo')">
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF. Máx. 5MB. Se redimensionará automáticamente.</small>
                                
                                <div class="image-preview-container mt-3" id="fondoPreviewContainer">
                                    <?php if (!empty($config['fondo_web'])): ?>
                                        <?php if (strpos($config['fondo_web'], 'assets/imagenes/') === 0 && file_exists($config['fondo_web'])): ?>
                                            <p class="mb-2"><strong>Fondo actual:</strong></p>
                                            <div class="position-relative" style="max-width: 200px;">
                                                <img src="<?php echo htmlspecialchars($config['fondo_web'] . '?t=' . time()); ?>" 
                                                    class="image-preview img-thumbnail" 
                                                    id="fondoPreview"
                                                    alt="Fondo actual">
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                                        onclick="eliminarFondo(event)"
                                                        title="Eliminar fondo">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Tamaño: <?php echo round(filesize($config['fondo_web']) / 1024, 2); ?> KB
                                            </small>
                                        <?php else: ?>
                                            <p class="mb-2"><strong>Fondo actual (base64):</strong></p>
                                            <div class="position-relative" style="max-width: 200px;">
                                                <img src="data:image/png;base64,<?php echo $config['fondo_web']; ?>" 
                                                    class="image-preview img-thumbnail" 
                                                    id="fondoPreview"
                                                    alt="Fondo actual">
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                                        onclick="eliminarFondo(event)"
                                                        title="Eliminar fondo">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Tamaño: <?php echo number_format(strlen($config['fondo_web']) / 1024, 2); ?> KB
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="mb-2"><strong>Vista previa:</strong></p>
                                        <img src="" class="image-preview img-thumbnail d-none" 
                                            id="fondoPreview" 
                                            style="max-width: 200px;"
                                            alt="Vista previa del fondo">
                                        <small class="text-muted d-block">No hay fondo cargado</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Autorizado -->
            <div class="card mica-effect animate__animated animate__fadeInUp" style="border-left: 4px solid var(--win-accent);">
                <div class="card-header">
                    <h6 class="mb-0" style="color: var(--win-accent);"><i class="fa fa-user-tie" style="color: #007bff; font-size: 24px;"></i> Personal Autorizado</h6>
                </div>
                <div class="card-body">
                    <h6 class="mb-3" style="color: var(--win-accent);">Director</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Director</label>
                                <input type="text" class="form-control" name="director_nombre"
                                       value="<?php echo htmlspecialchars($config['director_nombre'] ?? 'Laicep Rodríguez Pérez'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Carnet de Identidad</label>
                                <input type="text" class="form-control" name="director_ci"
                                       value="<?php echo htmlspecialchars($config['director_ci'] ?? '81103016986'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="director_telefono"
                                       value="<?php echo htmlspecialchars($config['director_telefono'] ?? '52789632'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4" style="color: var(--win-accent);">Facturador</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Facturador</label>
                                <input type="text" class="form-control" name="facturador_nombre"
                                       value="<?php echo htmlspecialchars($config['facturador_nombre'] ?? 'Annia Canden Ferreiro'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Carnet de Identidad</label>
                                <input type="text" class="form-control" name="facturador_ci"
                                       value="<?php echo htmlspecialchars($config['facturador_ci'] ?? '90012538346'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="facturador_telefono"
                                       value="<?php echo htmlspecialchars($config['facturador_telefono'] ?? '52712896'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<!-- Fecha de Inicio de Operaciones (Solo Administradores) -->
<div class="card mica-effect animate__animated animate__fadeInUp" style="border-left: 4px solid var(--win-accent);">
    <div class="card-header">
        <h6 class="mb-0" style="color: var(--win-accent);">
            <i class="fa fa-calendar-alt" style="color: #007bff; font-size: 24px;"></i> Período Contable Actual
            <?php if ($es_admin): ?>
                <span class="badge bg-danger ms-2"><i class="fas fa-crown me-1"></i>Solo Admin/Super/Soft</span>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Seleccionar Período Contable</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Mes</label>
                            <select class="form-control" 
                                    name="mes_operaciones" 
                                    id="mesOperaciones"
                                    <?php echo !$es_admin ? 'disabled' : ''; ?>>
                                <?php
                                $meses_es = [
                                    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', 
                                    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                                    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', 
                                    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                                ];
                                
                                $mes_actual = date('m');
                                $mes_seleccionado = isset($config['fecha_inicio_operaciones']) && !empty($config['fecha_inicio_operaciones'])
                                    ? date('m', strtotime($config['fecha_inicio_operaciones']))
                                    : $mes_actual;
                                
                                foreach ($meses_es as $num => $nombre):
                                    $selected = ($num == $mes_seleccionado) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $num; ?>" <?php echo $selected; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-6">
                            <label class="form-label small">Año</label>
                            <select class="form-control" 
                                    name="anio_operaciones" 
                                    id="anioOperaciones"
                                    <?php echo !$es_admin ? 'disabled' : ''; ?>>
                                <?php
                                $anio_actual = date('Y');
                                $anio_minimo = 2010;
                                $anio_seleccionado = isset($config['fecha_inicio_operaciones']) && !empty($config['fecha_inicio_operaciones'])
                                    ? date('Y', strtotime($config['fecha_inicio_operaciones']))
                                    : $anio_actual;
                                
                                for ($anio = $anio_actual; $anio >= $anio_minimo; $anio--):
                                    $selected = ($anio == $anio_seleccionado) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $anio; ?>" <?php echo $selected; ?>>
                                        <?php echo $anio; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Información del período -->
                    <div id="infoPeriodoContable" class="mt-3 p-3 rounded" 
                         style="background-color: var(--win-bg-tertiary); border: 1px dashed var(--win-border-color);">
                        <?php
                        $fecha_guardada = isset($config['fecha_inicio_operaciones']) && !empty($config['fecha_inicio_operaciones']) 
                            ? $config['fecha_inicio_operaciones'] 
                            : date('Y-m-01');
                        
                        $mes_num = date('m', strtotime($fecha_guardada));
                        $mes_nombre = $meses_es[$mes_num];
                        $año = date('Y', strtotime($fecha_guardada));
                        $fecha_formateada = date('Y-m-01', strtotime($fecha_guardada));
                        ?>
                        <small class="text-muted d-block mb-2">
                            <i class="fas fa-calendar-check me-1"></i>
                            <strong>Período Contable Actual</strong>
                        </small>
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="text-center bg-primary text-white rounded p-2" style="min-width: 70px;">
                                    <div class="fw-bold" style="font-size: 20px;">01</div>
                                    <div style="font-size: 12px;">
                                        <?php echo substr($mes_nombre, 0, 3); ?>
                                    </div>
                                    <div style="font-size: 10px; opacity: 0.9;">
                                        <?php echo $año; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo $mes_nombre . ' ' . $año; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-database me-1"></i>
                                    Se guarda en BD como: <code><?php echo $fecha_formateada; ?></code>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-file-invoice me-1"></i>
                                    Facturación permitida desde: 1 de <?php echo $mes_nombre; ?> de <?php echo $año; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Información del Período</label>
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="alert-heading mb-2">¡ADVERTENCIA!</h6>
                                <p class="mb-2">Esta configuración define el período contable actual del sistema.</p>
                                <ul class="mb-0 ps-3" style="font-size: 13px;">
                                    <li class="text-danger"><strong>Solo modificar en casos excepcionales</strong></li>
                                    <li>Define la fecha desde la cual se pueden procesar facturas</li>
                                    <li>Se actualiza automáticamente al cerrar mes/año</li>
                                    <li>Rango permitido: Desde 2010 hasta el año actual</li>
                                    <li>Siempre se guarda como día 1 del mes seleccionado</li>
									<li>Esta configuración afecta reportes y estadísticas históricas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botón para restablecer al mes actual -->
                    <?php if ($es_admin): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm w-100 mb-2" 
                                onclick="sincronizarPeriodoActual()">
                            <i class="fas fa-sync-alt me-1"></i> Sincronizar con Mes Actual
                        </button>
                        
                        <button type="button" class="btn btn-outline-info btn-sm w-100" 
                                onclick="mostrarHistorialPeriodos()">
                            <i class="fas fa-history me-1"></i> Ver Historial de Períodos
                        </button>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-lock fa-2x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Acceso Restringido</h6>
                                    <p class="mb-0">Solo usuarios Administrador, Supervisor o Programador pueden modificar esta configuración.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Validación en tiempo real -->
        <div id="validacionPeriodo" class="mt-3" style="display: none;">
            <div class="alert" id="alertaValidacion">
                <i class="fas fa-info-circle me-2"></i>
                <span id="mensajeValidacion"></span>
            </div>
        </div>
        
        <!-- Input oculto para enviar fecha completa -->
        <input type="hidden" name="fecha_inicio_operaciones" id="fechaCompletaInput" 
               value="<?php echo $fecha_formateada; ?>">
    </div>
</div>


            <!-- Configuración de WhatsApp, mmto y Recuperacion de pw-->
<div class="card mica-effect animate__animated animate__fadeInUp" style="border-left: 4px solid var(--win-accent);">
    <div class="card-header">
        <h6 class="mb-0" style="color: var(--win-accent);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div>
                    <i class="fab fa-whatsapp" style="color: #25D366; font-size: 24px;"></i>
                    <span>WhatsApp</span> y 
                </div>
                <div>
                    <i class="fas fa-laptop-code" style="color: #007bff; font-size: 24px;"></i>
                    <span>Mantenimiento Web</span>
                </div>
            </div>
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- WhatsApp Switch -->
            <div class="col-md-6 mb-4">
                <div class="mb-3">
                    <label class="form-label d-block mb-3">
                        <strong>Habilitar WhatsApp</strong>
                        <span class="badge bg-info ms-2">Nuevo</span>
                    </label>
                    <div class="form-check form-switch d-flex align-items-center bg-light p-3 rounded" style="background: var(--win-bg-tertiary);">
                        <div class="d-flex align-items-center w-100">
                            <input class="form-check-input me-3 flex-shrink-0" type="checkbox" role="switch" 
                                id="whatsappToggle" name="whatsapp_ON"
                                <?php echo (isset($config['whatsapp_ON']) && $config['whatsapp_ON'] == 1) ? 'checked' : ''; ?>>
                            <div class="d-flex flex-column">
                                <label class="form-check-label fw-medium mb-1" for="whatsappToggle">
                                    Estado: <span id="whatsappStatus" class="ms-1">
                                        <?php echo (isset($config['whatsapp_ON']) && $config['whatsapp_ON'] == 1) ? 'Activado' : 'Desactivado'; ?>
                                    </span>
                                </label>
                                <small class="text-muted">
                                    Habilita el envío de mensajes por WhatsApp
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label d-block mb-3">
                        <strong>Número de WhatsApp <b style="color: var(--win-accent);">Soporte Técnico </b>SISFACT PDL "Visiones"</strong>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fab fa-whatsapp text-success"></i>
                        </span>
                        <input type="text" 
                            class="form-control" 
                            name="whatsapp_numero" 
                            id="whatsappNumero"
                            placeholder="+53512345678"
                            value="<?php echo htmlspecialchars($config['whatsapp_numero'] ?? '+5359860773'); ?>">
                        <button type="button" class="btn btn-outline-success" id="testWhatsAppBtn"
                                title="Probar enlace de WhatsApp">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            Formato para Cuba: +53 5XX XXX XXXX (Ej: +53512345678)
                        </small>
                        <small class="text-muted d-block">
                            <i class="fas fa-shield-alt me-1"></i>
                            El número será validado automáticamente
                        </small>
                    </div>
                    
                    <!-- Previsualización del enlace -->
                    <div class="mt-3 border rounded p-3 bg-light" id="whatsappPreview" 
                        style="<?php echo (empty($config['whatsapp_numero']) || (!isset($config['whatsapp_ON']) || $config['whatsapp_ON'] == 0)) ? 'display: none;' : ''; ?>">
                        <h6 class="mb-2">
                            <i class="fab fa-whatsapp text-success me-2"></i>
                            Vista previa del enlace
                        </h6>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 text-truncate">
                                <a href="#" id="whatsappLinkPreview" class="text-decoration-none" target="_blank">
                                    <small class="text-muted">https://wa.me/</small>
                                    <span id="whatsappNumberPreview">
                                        <?php echo htmlspecialchars($config['whatsapp_numero'] ?? '+5359860773'); ?>
                                    </span>
                                </a>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success ms-2" id="copyWhatsAppLink">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-external-link-alt me-1"></i>
                            Haga clic para probar el enlace
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Modo Mantenimiento y Recuperación de Contraseña -->
            <div class="col-md-6 mb-4">
                <!-- Modo Mantenimiento -->
                <div class="mb-4">
                    <label class="form-label d-block mb-3">
                        <strong>Modo Mantenimiento</strong>
                    </label>
                    <div class="form-check form-switch d-flex align-items-center bg-light p-3 rounded">
                        <div class="d-flex align-items-center w-100">
                            <input class="form-check-input me-3 flex-shrink-0" type="checkbox" role="switch" 
                                name="modo_mantenimiento"
                                id="modoMantenimiento"
                                <?php echo (isset($config['modo_mantenimiento']) && $config['modo_mantenimiento'] == 1) ? 'checked' : ''; ?>>
                            <div class="d-flex flex-column">
                                <label class="form-check-label fw-medium mb-1" for="modoMantenimiento">
                                    Estado: <span id="modoMantenimientoStatus" class="ms-1">
                                        <?php echo (isset($config['modo_mantenimiento']) && $config['modo_mantenimiento'] == 1) ? 'Activado' : 'Desactivado'; ?>
                                    </span>
                                </label>
                                <small class="text-muted">
                                    Activar modo mantenimiento del sistema
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activar Recuperación de Contraseña -->
                <div class="mb-3">
                    <label class="form-label d-block mb-3">
                        <strong>Recuperación de Contraseña</strong>
                    </label>
                    <div class="form-check form-switch d-flex align-items-center bg-light p-3 rounded">
                        <div class="d-flex align-items-center w-100">
                             <input class="form-check-input me-3 flex-shrink-0" type="checkbox" role="switch" 
								name="activar_recuperacion_password"
								id="activarRecuperacionPassword"
								<?php echo (isset($config['restabpw']) && $config['restabpw'] == 1) ? 'checked' : ''; ?>>
                            <div class="d-flex flex-column">
                                <label class="form-check-label fw-medium mb-1" for="activarRecuperacionPassword">
                                    Estado: <span id="recuperacionPasswordStatus" class="ms-1">
												<?php echo (isset($config['restabpw']) && $config['restabpw'] == 1) ? 'Activado' : 'Desactivado'; ?>
											</span>
                                </label>
                                <small class="text-muted">
                                    Permite a los usuarios recuperar su contraseña de forma inmediata, de lo contrario deberá hacer una solicitud de cambio al Administrador del Sistema
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
		</form>
    </main>
    
    <!-- MODAL PARA RECORTAR IMAGEN -->
    <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="background: var(--win-bg-secondary); color: var(--win-text-primary);">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-crop-alt me-2" style="color: var(--win-accent);"></i>
                        Recortar imagen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropper()"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="img-container" style="max-height: 500px; overflow: hidden; background: #333; border-radius: 8px; padding: 10px;">
                                <img id="imageToCrop" src="" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="mb-2">Vista previa</h6>
                                <div class="preview-container" style="width: 200px; height: 200px; margin: 0 auto; overflow: hidden; border: 3px solid var(--win-accent); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: #2d2d2d;">
                                    <canvas id="previewCanvas" width="200" height="200" style="width: 100%; height: 100%; display: block;"></canvas>
                                </div>
                            </div>

                            <div class="text-center mb-3">
                                <small class="text-muted d-block">
                                    <i class="fas fa-info-circle me-1"></i>
                                    La imagen se redimensionará automáticamente
                                </small>
                            </div>

                            <div class="crop-controls p-3" style="background: var(--win-bg-tertiary); border-radius: 8px;">
                                <h6 class="mb-2">Controles</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(-90)">
                                        <i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(90)">
                                        <i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetearCrop()">
                                        <i class="fas fa-sync-alt me-2"></i>Resetear recorte
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="zoomImagen(0.1)">
                                        <i class="fas fa-search-plus me-2"></i>Acercar
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="zoomImagen(-0.1)">
                                        <i class="fas fa-search-minus me-2"></i>Alejar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropper()">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="aplicarRecorte()">
                        <i class="fas fa-check me-2"></i>Aplicar recorte
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
    <!-- Botones en footer fijo -->
<div class="win-footer-fixed">
    <button type="button" class="btn btn-secondary" title="Cancelar cambios" onclick="resetForm()">
        <i class="fas fa-times me-1"></i>Cancelar Cambios
    </button>
    
    <!-- Botón Dinámico Arriba/Abajo -->
    <button type="button" title="Ir Arriba o Abajo" class="btn btn-outline-success" id="btnScrollToggle" onclick="toggleScrollPage()">
        <i class="fas fa-arrow-down me-1"></i>Ir Abajo
    </button>

    <!-- NUEVO BOTÓN EXPANDIR/COLAPSAR TODAS -->
    <button type="button" id="btnExpandirTodasFooter" class="btn btn-outline-info" title="Expandir/Colapsar todas las secciones" onclick="toggleAllCards()">
        <i class="fas fa-expand-alt me-1"></i>Expandir Todas
    </button>

    <button type="button" class="btn btn-primary" title="Guardar Cambios" onclick="confirmarGuardarConfiguracion()" id="btnGuardarFinal">
        <i class="fas fa-save me-1"></i>Guardar Configuración
    </button>
</div>

    <!-- Quick Actions -->
    <div class="win-quick-actions">
        <button class="win-quick-action" onclick="confirmarGuardarConfiguracion()" title="Guardar configuración" id="mainQuickAction">
            <i class="fas fa-save"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        let desgloseVisible = false;
        
        // Variables para recorte de imagen
        let cropper = null;
        let previewCanvas = null;
        let previewCtx = null;
        let tipoImagenActual = null; // 'logo' o 'fondo'
        
        // ==================== FUNCIONES DE RECORTE DE IMAGEN ====================
        
        function cargarImagenParaRecorte(input, tipo) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validar tamaño según tipo
                const maxSize = tipo === 'logo' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: `El ${tipo === 'logo' ? 'logo' : 'fondo'} no puede exceder los ${tipo === 'logo' ? '2MB' : '5MB'}`,
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                    input.value = '';
                    return;
                }
                
                // Validar tipo de archivo
                if (!file.type.match('image.*')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Solo se permiten archivos de imagen',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                    input.value = '';
                    return;
                }
                
                // Guardar tipo actual
                tipoImagenActual = tipo;
                
                // Mostrar loading
                Swal.fire({
                    title: 'Cargando imagen...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                });
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageToCrop = document.getElementById('imageToCrop');
                    imageToCrop.src = e.target.result;
                    
                    imageToCrop.onload = function() {
                        Swal.close();
                        
                        previewCanvas = document.getElementById('previewCanvas');
                        previewCtx = previewCanvas.getContext('2d');
                        
                        previewCtx.fillStyle = '#2d2d2d';
                        previewCtx.fillRect(0, 0, 200, 200);
                        
                        if (cropper) {
                            cropper.destroy();
                        }
                        
                        cropper = new Cropper(imageToCrop, {
                            aspectRatio: NaN, // Relación libre (se puede cambiar)
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 1,
                            cropBoxResizable: true,
                            cropBoxMovable: true,
                            guides: true,
                            center: true,
                            highlight: true,
                            background: true,
                            responsive: true,
                            restore: true,
                            zoomable: true,
                            rotatable: true,
                            scalable: true,
                            wheelZoomRatio: 0.1,
                            minContainerWidth: 500,
                            minContainerHeight: 400,
                            crop: function(event) {
                                actualizarPreview(event);
                            },
                            ready: function() {
                                const containerData = cropper.getContainerData();
                                const cropBoxSize = Math.min(containerData.width, containerData.height) * 0.8;
                                cropper.setCropBoxData({
                                    width: cropBoxSize,
                                    height: cropBoxSize,
                                    left: (containerData.width - cropBoxSize) / 2,
                                    top: (containerData.height - cropBoxSize) / 2
                                });
                                
                                setTimeout(() => {
                                    const cropData = cropper.getData();
                                    actualizarPreview({ detail: cropData });
                                }, 100);
                            }
                        });
                        
                        const modal = new bootstrap.Modal(document.getElementById('cropModal'));
                        modal.show();
                    };
                };
                reader.readAsDataURL(file);
            }
        }
        
        function actualizarPreview(event) {
            if (!cropper || !previewCtx) return;
            
            try {
                const canvas = cropper.getCroppedCanvas({
                    width: 200,
                    height: 200,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                });
                
                if (canvas) {
                    previewCtx.drawImage(canvas, 0, 0, 200, 200);
                }
            } catch (error) {
                console.error('Error actualizando preview:', error);
            }
        }
        
        function rotarImagen(grados) {
            if (cropper) {
                cropper.rotate(grados);
                setTimeout(() => {
                    const cropData = cropper.getData();
                    actualizarPreview({ detail: cropData });
                }, 50);
            }
        }
        
        function zoomImagen(factor) {
            if (cropper) {
                cropper.zoom(factor);
                setTimeout(() => {
                    const cropData = cropper.getData();
                    actualizarPreview({ detail: cropData });
                }, 50);
            }
        }
        
        function resetearCrop() {
            if (cropper) {
                cropper.reset();
                setTimeout(() => {
                    const cropData = cropper.getData();
                    actualizarPreview({ detail: cropData });
                }, 50);
            }
        }
        
        function limpiarCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            
            if (previewCtx) {
                previewCtx.fillStyle = '#2d2d2d';
                previewCtx.fillRect(0, 0, 200, 200);
            }
            
            tipoImagenActual = null;
        }
        
        function aplicarRecorte() {
            if (cropper && tipoImagenActual) {
                try {
                    Swal.fire({
                        title: 'Procesando imagen...',
                        text: 'Aplicando recorte',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                    
                    const canvas = cropper.getCroppedCanvas({
                        width: 800, // Tamaño adecuado para logo/fondo
                        height: 600,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high'
                    });
                    
                    const imagenRecortadaBase64 = canvas.toDataURL('image/jpeg', 0.9);
                    
                    // Guardar en el campo oculto correspondiente
                    if (tipoImagenActual === 'logo') {
                        document.getElementById('logo_recortado').value = imagenRecortadaBase64;
                        
                        // Actualizar preview
                        const logoPreview = document.getElementById('logoPreview');
                        const logoPreviewContainer = document.getElementById('logoPreviewContainer');
                        
                        if (logoPreview) {
                            logoPreview.src = imagenRecortadaBase64;
                            logoPreview.classList.remove('d-none');
                            
                            // Eliminar el estado de eliminación pendiente si existe
                            const eliminacionDiv = logoPreviewContainer.querySelector('.eliminacion-pendiente');
                            if (eliminacionDiv) {
                                eliminacionDiv.remove();
                            }
                            
                            // Asegurar que el botón de eliminar esté presente
                            if (!logoPreviewContainer.querySelector('.btn-danger')) {
                                const parentDiv = logoPreview.closest('.position-relative') || document.createElement('div');
                                if (!parentDiv.classList.contains('position-relative')) {
                                    const newParent = document.createElement('div');
                                    newParent.className = 'position-relative';
                                    newParent.style.maxWidth = '200px';
                                    logoPreview.parentNode.insertBefore(newParent, logoPreview);
                                    newParent.appendChild(logoPreview);
                                    
                                    const deleteBtn = document.createElement('button');
                                    deleteBtn.type = 'button';
                                    deleteBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-1';
                                    deleteBtn.style.borderRadius = '50%';
                                    deleteBtn.style.width = '30px';
                                    deleteBtn.style.height = '30px';
                                    deleteBtn.onclick = eliminarLogo;
                                    deleteBtn.title = 'Eliminar logo';
                                    deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                                    newParent.appendChild(deleteBtn);
                                }
                            }
                        }
                        
                        // Resetear input file
                        document.getElementById('logoInput').value = '';
                        
                    } else if (tipoImagenActual === 'fondo') {
                        document.getElementById('fondo_recortado').value = imagenRecortadaBase64;
                        
                        // Actualizar preview
                        const fondoPreview = document.getElementById('fondoPreview');
                        const fondoPreviewContainer = document.getElementById('fondoPreviewContainer');
                        
                        if (fondoPreview) {
                            fondoPreview.src = imagenRecortadaBase64;
                            fondoPreview.classList.remove('d-none');
                            
                            // Eliminar el estado de eliminación pendiente si existe
                            const eliminacionDiv = fondoPreviewContainer.querySelector('.eliminacion-pendiente');
                            if (eliminacionDiv) {
                                eliminacionDiv.remove();
                            }
                            
                            // Asegurar que el botón de eliminar esté presente
                            if (!fondoPreviewContainer.querySelector('.btn-danger')) {
                                const parentDiv = fondoPreview.closest('.position-relative') || document.createElement('div');
                                if (!parentDiv.classList.contains('position-relative')) {
                                    const newParent = document.createElement('div');
                                    newParent.className = 'position-relative';
                                    newParent.style.maxWidth = '200px';
                                    fondoPreview.parentNode.insertBefore(newParent, fondoPreview);
                                    newParent.appendChild(fondoPreview);
                                    
                                    const deleteBtn = document.createElement('button');
                                    deleteBtn.type = 'button';
                                    deleteBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-1';
                                    deleteBtn.style.borderRadius = '50%';
                                    deleteBtn.style.width = '30px';
                                    deleteBtn.style.height = '30px';
                                    deleteBtn.onclick = eliminarFondo;
                                    deleteBtn.title = 'Eliminar fondo';
                                    deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                                    newParent.appendChild(deleteBtn);
                                }
                            }
                        }
                        
                        // Resetear input file
                        document.getElementById('fondoInput').value = '';
                    }
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                    modal.hide();
                    
                    limpiarCropper();
                    
                    Swal.close();
                    
                    // Mensaje de éxito
                    Swal.fire({
                        icon: 'success',
                        title: '¡Imagen recortada!',
                        text: `El ${tipoImagenActual === 'logo' ? 'logo' : 'fondo'} se ha actualizado correctamente`,
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                    
                } catch (error) {
                    console.error('Error al recortar:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo procesar la imagen. Intente nuevamente.',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    });
                }
            }
        }
        
        // ==================== FUNCIONES DE ELIMINACIÓN MEJORADAS ====================
        
        function eliminarLogo(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            Swal.fire({
                title: '¿Eliminar logo?',
                text: 'Esta acción eliminará el logo actual del sistema. ¿Está seguro?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                reverseButtons: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark',
                    cancelButton: 'sweetalert-cancel-dark'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    marcarLogoParaEliminacion();
                }
            });
            
            return false;
        }

        function marcarLogoParaEliminacion() {
            // Marcar para eliminación
            document.getElementById('eliminarLogoInput').value = '1';
            
            // Reemplazar vista previa por alerta con botón cancelar
            const logoContainer = document.getElementById('logoPreviewContainer');
            
            if (logoContainer) {
                logoContainer.innerHTML = `
                    <div class="eliminacion-pendiente">
                        <div class="alert alert-warning border-warning p-3 shadow-sm">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-trash-alt fa-2x text-warning"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 fw-bold text-warning">
                                        <i class="fas fa-clock me-1"></i> Eliminación pendiente
                                    </h6>
                                    <p class="mb-2">El logo será eliminado al guardar la configuración.</p>
                                    <div class="d-flex gap-2">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-warning" 
                                                onclick="cancelarEliminacionLogo()">
                                            <i class="fas fa-undo me-1"></i> Cancelar eliminación
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-light" 
                                                onclick="mostrarAyudaEliminacion('logo')">
                                            <i class="fas fa-question-circle me-1"></i> Ayuda
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                                 style="width: 100%"></div>
                        </div>
                        
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Recuerde guardar los cambios al final de la página
                        </small>
                    </div>
                `;
            }
            
            // Limpiar input de archivo
            const logoInput = document.getElementById('logoInput');
            if (logoInput) logoInput.value = '';
            
            // Mostrar notificación flotante
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Logo marcado para eliminación',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
        }

        function cancelarEliminacionLogo() {
            Swal.fire({
                title: '¿Cancelar eliminación?',
                text: 'El logo ya no será eliminado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, cancelar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> No, mantener',
                reverseButtons: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark',
                    cancelButton: 'sweetalert-cancel-dark'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    restaurarLogo();
                }
            });
        }

        function restaurarLogo() {
            // Restaurar valores
            document.getElementById('eliminarLogoInput').value = '0';
            
            // Restaurar vista original
            const container = document.querySelector('#logoPreviewContainer .eliminacion-pendiente');
            if (container && container.parentNode) {
                <?php if (!empty($config['logo'])): ?>
                    <?php if (strpos($config['logo'], 'assets/imagenes/') === 0 && file_exists($config['logo'])): ?>
                        container.parentNode.innerHTML = `
                            <p class="mb-2"><strong>Logo actual:</strong></p>
                            <div class="position-relative" style="max-width: 200px;">
                                <img src="<?php echo htmlspecialchars($config['logo'] . '?t=' . time()); ?>" 
                                     class="image-preview img-thumbnail" 
                                     id="logoPreview"
                                     alt="Logo actual">
                                <button type="button" 
                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                        onclick="eliminarLogo(event)"
                                        title="Eliminar logo">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Tamaño: <?php echo round(filesize($config['logo']) / 1024, 2); ?> KB
                            </small>
                        `;
                    <?php else: ?>
                        container.parentNode.innerHTML = `
                            <p class="mb-2"><strong>Logo actual (base64):</strong></p>
                            <div class="position-relative" style="max-width: 200px;">
                                <img src="data:image/png;base64,<?php echo $config['logo']; ?>" 
                                     class="image-preview img-thumbnail" 
                                     id="logoPreview"
                                     alt="Logo actual">
                                <button type="button" 
                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                        onclick="eliminarLogo(event)"
                                        title="Eliminar logo">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Tamaño: <?php echo number_format(strlen($config['logo']) / 1024, 2); ?> KB
                            </small>
                        `;
                    <?php endif; ?>
                <?php else: ?>
                    container.parentNode.innerHTML = `
                        <p class="mb-2"><strong>Vista previa:</strong></p>
                        <img src="" class="image-preview img-thumbnail d-none" 
                             id="logoPreview" 
                             style="max-width: 200px;"
                             alt="Vista previa del logo">
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-image me-1"></i>
                            No hay logo cargado
                        </small>
                    `;
                <?php endif; ?>
            }
            
            // Mostrar confirmación
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Eliminación cancelada',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
        }

        function eliminarFondo(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            Swal.fire({
                title: '¿Eliminar fondo?',
                text: 'Esta acción eliminará el fondo actual del sistema. ¿Está seguro?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                reverseButtons: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark',
                    cancelButton: 'sweetalert-cancel-dark'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    marcarFondoParaEliminacion();
                }
            });
            
            return false;
        }

        function marcarFondoParaEliminacion() {
            // Marcar para eliminación
            document.getElementById('eliminarFondoInput').value = '1';
            
            // Reemplazar vista previa por alerta con botón cancelar
            const fondoContainer = document.getElementById('fondoPreviewContainer');
            
            if (fondoContainer) {
                fondoContainer.innerHTML = `
                    <div class="eliminacion-pendiente">
                        <div class="alert alert-warning border-warning p-3 shadow-sm">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-trash-alt fa-2x text-warning"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 fw-bold text-warning">
                                        <i class="fas fa-clock me-1"></i> Eliminación pendiente
                                    </h6>
                                    <p class="mb-2">El fondo será eliminado al guardar la configuración.</p>
                                    <div class="d-flex gap-2">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-warning" 
                                                onclick="cancelarEliminacionFondo()">
                                            <i class="fas fa-undo me-1"></i> Cancelar eliminación
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-light" 
                                                onclick="mostrarAyudaEliminacion('fondo')">
                                            <i class="fas fa-question-circle me-1"></i> Ayuda
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                                 style="width: 100%"></div>
                        </div>
                        
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Recuerde guardar los cambios al final de la página
                        </small>
                    </div>
                `;
            }
            
            // Limpiar input de archivo
            const fondoInput = document.getElementById('fondoInput');
            if (fondoInput) fondoInput.value = '';
            
            // Mostrar notificación flotante
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Fondo marcado para eliminación',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
        }

        function cancelarEliminacionFondo() {
            Swal.fire({
                title: '¿Cancelar eliminación?',
                text: 'El fondo ya no será eliminado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, cancelar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> No, mantener',
                reverseButtons: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark',
                    cancelButton: 'sweetalert-cancel-dark'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    restaurarFondo();
                }
            });
        }

        function restaurarFondo() {
            // Restaurar valores
            document.getElementById('eliminarFondoInput').value = '0';
            
            // Restaurar vista original
            const container = document.querySelector('#fondoPreviewContainer .eliminacion-pendiente');
            if (container && container.parentNode) {
                <?php if (!empty($config['fondo_web'])): ?>
                    <?php if (strpos($config['fondo_web'], 'assets/imagenes/') === 0 && file_exists($config['fondo_web'])): ?>
                        container.parentNode.innerHTML = `
                            <p class="mb-2"><strong>Fondo actual:</strong></p>
                            <div class="position-relative" style="max-width: 200px;">
                                <img src="<?php echo htmlspecialchars($config['fondo_web'] . '?t=' . time()); ?>" 
                                     class="image-preview img-thumbnail" 
                                     id="fondoPreview"
                                     alt="Fondo actual">
                                <button type="button" 
                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                        onclick="eliminarFondo(event)"
                                        title="Eliminar fondo">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Tamaño: <?php echo round(filesize($config['fondo_web']) / 1024, 2); ?> KB
                            </small>
                        `;
                    <?php else: ?>
                        container.parentNode.innerHTML = `
                            <p class="mb-2"><strong>Fondo actual (base64):</strong></p>
                            <div class="position-relative" style="max-width: 200px;">
                                <img src="data:image/png;base64,<?php echo $config['fondo_web']; ?>" 
                                     class="image-preview img-thumbnail" 
                                     id="fondoPreview"
                                     alt="Fondo actual">
                                <button type="button" 
                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                        style="border-radius: 50%; width: 30px; height: 30px;"
                                        onclick="eliminarFondo(event)"
                                        title="Eliminar fondo">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Tamaño: <?php echo number_format(strlen($config['fondo_web']) / 1024, 2); ?> KB
                            </small>
                        `;
                    <?php endif; ?>
                <?php else: ?>
                    container.parentNode.innerHTML = `
                        <p class="mb-2"><strong>Vista previa:</strong></p>
                        <img src="" class="image-preview img-thumbnail d-none" 
                             id="fondoPreview" 
                             style="max-width: 200px;"
                             alt="Vista previa del fondo">
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-image me-1"></i>
                            No hay fondo cargado
                        </small>
                    `;
                <?php endif; ?>
            }
            
            // Mostrar confirmación
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Eliminación cancelada',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
        }

        function mostrarAyudaEliminacion(tipo) {
            const tipos = {
                'logo': {
                    titulo: '¿Cómo funciona la eliminación del logo?',
                    descripcion: 'El logo se utiliza como identificador principal de la empresa en facturas, reportes y la cabecera del sistema.',
                    icono: 'fas fa-image'
                },
                'fondo': {
                    titulo: '¿Cómo funciona la eliminación del fondo?',
                    descripcion: 'El fondo se utiliza en toda la interfaz del sistema como imagen de fondo principal.',
                    icono: 'fas fa-desktop'
                }
            };
            
            const info = tipos[tipo] || tipos['logo'];
            
            Swal.fire({
                icon: 'info',
                title: info.titulo,
                html: `
                    <div style="text-align: left; font-size: 14px;">
                        <div class="d-flex align-items-center mb-3">
                            <i class="${info.icono} fa-2x text-primary me-3"></i>
                            <div>
                                <p class="mb-0"><strong>${tipo === 'logo' ? 'Logo' : 'Fondo'} del sistema</strong></p>
                                <small class="text-muted">${info.descripcion}</small>
                            </div>
                        </div>
                        
                        <p><strong>Proceso de eliminación en dos pasos:</strong></p>
                        
                        <div class="alert alert-light border mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-warning me-2">1</span>
                                <strong>Marcar para eliminación</strong>
                            </div>
                            <p class="mb-0">El ${tipo} se marca pero no se elimina inmediatamente.</p>
                            <small class="text-muted">Esto permite cancelar la acción antes de guardar.</small>
                        </div>
                        
                        <div class="alert alert-light border mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-primary me-2">2</span>
                                <strong>Guardar configuración</strong>
                            </div>
                            <p class="mb-0">La eliminación se completa al guardar todos los cambios.</p>
                            <small class="text-muted">Requiere confirmación final al hacer clic en "Guardar Configuración".</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Puede cancelar</strong> la eliminación en cualquier momento 
                            antes de guardar los cambios haciendo clic en "Cancelar eliminación".
                        </div>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                width: 500,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark'
                }
            });
        }

        // ==================== FUNCIONES EXISTENTES ====================
        
        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('open');
            } else {
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                guardarPreferencia('sidebar_mini', sidebarMini);
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
                customClass: {
                    popup: 'sweetalert-dark'
                },
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
                        showConfirmButton: false,
                        customClass: {
                            popup: 'sweetalert-dark'
                        }
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

// Resetear formulario
        function resetForm() {
            Swal.fire({
                title: '¿Cancelar cambios?',
                text: '¿Está seguro de cancelar todos los cambios? Se perderán los datos no guardados.',
                icon: 'warning',
                showCancelButton: true,
                showCloseButton: true,      // Muestra la X de cerrar
                allowOutsideClick: false,   // Evita cerrar al hacer clic fuera
                confirmButtonColor: '#d33', // Rojo para acción destructiva
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-undo mr-2"></i> Sí, cancelar',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> No, continuar',
                customClass: {
                    popup: 'sweetalert-dark',
                    title: 'sweetalert-title-dark',
                    htmlContainer: 'sweetalert-content-dark',
                    confirmButton: 'sweetalert-confirm-dark',
                    cancelButton: 'sweetalert-cancel-dark',
                    closeButton: 'sweetalert-close-dark' // Estilo para la X si lo necesitas
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Lógica original de reseteo
                    document.getElementById('configForm').reset();
                    document.getElementById('eliminarLogoInput').value = '0';
                    document.getElementById('eliminarFondoInput').value = '0';
                    document.getElementById('logo_recortado').value = '';
                    document.getElementById('fondo_recortado').value = '';
                    
                    // Restaurar vistas previas
                    const logoPreview = document.getElementById('logoPreview');
                    const fondoPreview = document.getElementById('fondoPreview');
                    const codReeup = document.querySelector('input[name="cod_reeup"]');
                    
                    if (codReeup) {
                        codReeup.value = '<?php echo htmlspecialchars($config["cod_reeup"] ?? "319.2.5400"); ?>';
                    }
                    
                    <?php if (!empty($config['logo'])): ?>
                        <?php if (strpos($config['logo'], 'assets/imagenes/') === 0 && file_exists($config['logo'])): ?>
                            if (logoPreview) {
                                logoPreview.src = '<?php echo htmlspecialchars($config['logo'] . '?t=' . time()); ?>';
                                logoPreview.style.display = 'block';
                            }
                        <?php else: ?>
                            if (logoPreview) {
                                logoPreview.src = 'data:image/png;base64,<?php echo $config['logo']; ?>';
                                logoPreview.style.display = 'block';
                            }
                        <?php endif; ?>
                    <?php else: ?>
                        if (logoPreview) {
                            logoPreview.classList.add('d-none');
                        }
                    <?php endif; ?>
                    
                    <?php if (!empty($config['fondo_web'])): ?>
                        <?php if (strpos($config['fondo_web'], 'assets/imagenes/') === 0 && file_exists($config['fondo_web'])): ?>
                            if (fondoPreview) {
                                fondoPreview.src = '<?php echo htmlspecialchars($config['fondo_web'] . '?t=' . time()); ?>';
                                fondoPreview.style.display = 'block';
                            }
                        <?php else: ?>
                            if (fondoPreview) {
                                fondoPreview.src = 'data:image/png;base64,<?php echo $config['fondo_web']; ?>';
                                fondoPreview.style.display = 'block';
                            }
                        <?php endif; ?>
                    <?php else: ?>
                        if (fondoPreview) {
                            fondoPreview.classList.add('d-none');
                        }
                    <?php endif; ?>
                    
                    // Restaurar información bancaria
                    actualizarDesglose();
                    
                    // Alerta de éxito
                    Swal.fire({
                        icon: 'info',
                        title: 'Cambios cancelados',
                        text: 'El formulario ha sido restablecido',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'sweetalert-dark'
                        }
                    });
                }
            });
        }        
		// ==================== FUNCIONES PARA INFORMACIÓN BANCARIA ====================

        // Base de datos de bancos
// Base de datos de bancos - CORREGIDA según estructura de sucursales
const bancosCuba = {
    '01': 'Banco Nacional de Cuba (BNC)',
    '03': 'Banco de Crédito y Comercio (BANDEC)',
    '05': 'Banco Metropolitano S.A. (BANMET)',
    '06': 'Banco de Crédito y Comercio (BANDEC)', // Confirmado por sucursales
    '08': 'Banco Financiero Internacional S.A. (BFI)',
    '09': 'Banco Exterior de Cuba (BEC)',
    '10': 'Banco Internacional de Comercio S.A. (BICSA)',
    '12': 'Banco Popular de Ahorro (BPA)', // Confirmado por sucursales
    '13': 'Banco de Inversiones (BISO)', // Confirmado por sucursales
    '14': 'Banco Exterior de Cuba (BEC)', // Confirmado por sucursales
    '15': 'Banco Financiero Internacional (BFI)', // Confirmado por sucursales
    '20': 'Banco Central de Cuba (BCC)',
    '21': 'Banco de Cuba para el Comercio Exterior (BANCEC)',
    '22': 'Banco de Desarrollo Local (BDL)',
    '23': 'Banco de Inversiones de Holguín',
    '25': 'Banco de la Construcción',
    '30': 'Caja de Ahorros',
    '35': 'Financiera Nacional (FINATUR)',
    '40': 'Banco de la Industria Alimentaria (BINAL)',
    '45': 'Banco de la Industria Ligera (BANIL)',
    '50': 'Banco de la Industria Sidero-Mecánica (BANISME)',
    '55': 'Banco de la Industria Químico-Farmacéutica (BANIQ)',
    '60': 'Banco de la Industria de Materiales de Construcción (BANIMAT)',
    '65': 'Banco de la Industria de Bienes de Consumo (BANICON)',
    '70': 'Banco de la Industria Agropecuaria (BANAGRO)',
    '75': 'Banco de la Industria Forestal (BANIF)',
    '80': 'Banco de la Industria Pesquera (BANIPES)',
    '85': 'Banco de la Industria del Turismo (BANITUR)',
    '90': 'Banco de la Industria de Transporte (BANITRANS)',
    '95': 'Banco de la Industria de Comunicaciones (BANICOM)',
    '98': 'Bancos Internacionales', // Nova Scotia, Sabadell, BBVA, Santander
    '99': 'Casas de Cambio y Otras Instituciones' // CADECA, FINCIMEX, Banco Central
};

// Tipos de cuenta completos - CORREGIDOS y alineados con bancos reales
const tiposCuenta = {
    // Tipos Básicos (01-09)
    '01': 'Cuenta de Ahorro CUP (Básica)',
    '02': 'Cuenta de Ahorro para la Vivienda',
    '03': 'Cuenta de Ahorro a Plazo Fijo CUP',
    '04': 'Cuenta en Dólares Estadounidenses (USD)',
    '05': 'Cuenta en Moneda Libremente Convertible (MLC)',
    '06': 'Cuenta en Euros (EUR)',
    '07': 'Cuenta Mixta (CUP/USD)',
    '08': 'Cuenta de Ahorro Joven (BPA)',
    '09': 'Cuenta de Ahorro Escolar',
    
    // Cuentas Corrientes (10-19)
    '10': 'Cuenta Corriente Empresarial CUP',
    '11': 'Cuenta Corriente Persona Natural CUP',
    '12': 'Cuenta Corriente USD (BFI/BICSA)',
    '13': 'Cuenta Corriente EUR',
    '14': 'Cuenta Corriente MLC',
    '15': 'Cuenta Corriente para TCP/Empresa',
    '16': 'Cuenta Corriente Mixta',
    '17': 'Cuenta Corriente para Inversiones',
    '18': 'Cuenta Corriente Offshore',
    '19': 'Cuenta Corriente Internacional',
    
    // Ahorros Especializados (20-29)
    '20': 'Ahorro a Plazo Fijo Largo',
    '21': 'Ahorro para el Retiro',
    '22': 'Ahorro para la Vivienda Especial',
    '23': 'Ahorro para Estudios',
    '24': 'Ahorro para Salud',
    '25': 'Ahorro USD a Plazo Fijo',
    '26': 'Tarjeta Magnética CUP / Jubilados',
    '27': 'Ahorro EUR a Plazo Fijo',
    '28': 'Ahorro MLC a Plazo Fijo',
    '29': 'Ahorro para Emergencias',
    
    // Tarjetas y Productos Digitales (30-39)
    '30': 'Tarjeta de Débito CUP',
    '31': 'Tarjeta de Débito USD',
    '32': 'Tarjeta de Débito MLC',
    '33': 'Tarjeta de Crédito Nacional',
    '34': 'Tarjeta de Crédito Internacional',
    '35': 'Tarjeta Prepago',
    '36': 'Tarjeta Virtual',
    '37': 'Tarjeta MLC (BANDEC Nacional)',
    '38': 'Banca Móvil / Virtual',
    '39': 'Cuenta Digital',
    
    // Cuentas Institucionales (40-49)
    '40': 'Cuenta Gubernamental',
    '41': 'Cuenta de Organizaciones Sociales',
    '42': 'Cuenta de Gastos Institucionales',
    '43': 'Cuenta de Empresa Estatal',
    '44': 'Cuenta de Empresa Mixta',
    '45': 'Cuenta de Inversión Extranjera',
    '46': 'Cuenta de Proyectos de Desarrollo',
    '47': 'Cuenta de ONG/Organismos',
    '48': 'Cuenta de Fondos Especiales',
    '49': 'Cuenta de Asociaciones',
    
    // Nómina y Beneficios (50-59)
    '50': 'Cuenta de Nómina Estatal',
    '51': 'Cuenta de Nómina Empresa Mixta',
    '52': 'Cuenta de Nómina TCP',
    '53': 'Cuenta de Remesas',
    '54': 'Cuenta para Pensionados',
    '55': 'Cuenta para Beneficiarios Sociales',
    '56': 'Cuenta de Subsidios',
    '57': 'Banca Remota / Nóminas (BANMET)',
    '58': 'Cuenta de Incentivos',
    '59': 'Cuenta de Bonificaciones',
    
    // Inversiones (60-69)
    '60': 'Cuenta de Inversión Corto Plazo',
    '61': 'Cuenta de Inversión Largo Plazo',
    '62': 'Cuenta de Fondos Mutuos',
    '63': 'Cuenta de Valores',
    '64': 'Cuenta de Bonos',
    '65': 'Cuenta de Acciones',
    '66': 'Cuenta de Fondo de Inversión',
    '67': 'Cuenta de Capital de Riesgo',
    '68': 'Cuenta de Inversión Extranjera Directa',
    '69': 'Cuenta de Portafolio',
    
    // Comercio Exterior (70-79)
    '70': 'Cuenta de Corresponsalía Bancaria',
    '71': 'Cuenta para Importaciones',
    '72': 'Cuenta para Exportaciones',
    '73': 'Cuenta de Financiamiento Externo',
    '74': 'Cuenta de Cartas de Crédito',
    '75': 'Cuenta de Garantías',
    '76': 'Cuenta de Operaciones Cambiarias',
    '77': 'Cuenta de Divisas',
    '78': 'Cuenta de Transferencias Internacionales',
    '79': 'Cuenta de Compensación',
    
    // MLC y Divisas Internacionales (80-89)
    '80': 'Cuenta MLC Persona Natural',
    '81': 'Cuenta MLC Empresa',
    '82': 'Tarjeta MLC con Cuenta',
    '83': 'Cuenta USD Persona Natural',
    '84': 'Cuenta EUR Persona Natural',
    '85': 'Cuenta en Libras Esterlinas (GBP)',
    '86': 'Cuenta en Dólares Canadienses (CAD)',
    '87': 'Cuenta en Dólares Australianos (AUD)',
    '88': 'Cuenta en Yuanes Chinos (CNY)',
    '89': 'Cuenta en Dólares Caribeños (XCD)',
    
    // Especiales y Otros (90-99)
    '90': 'Cuenta de Casa de Cambio (CADECA)',
    '91': 'Cuenta de FinCimex',
    '92': 'Cuenta de Operaciones Especiales',
    '93': 'Cuenta de Fideicomiso',
    '94': 'Cuenta de Garantía',
    '95': 'Cuenta de Depósito Judicial',
    '96': 'Cuenta de Secuestro',
    '97': 'Cuenta de Administración',
    '98': 'Cuenta Temporal',
    '99': 'Otras Cuentas Especiales'
};
        // Variable para controlar si la cuenta es válida
        let cuentaValida = false;

// Pasar el array PHP a JavaScript
const sucursalesCubaJS = <?php echo json_encode($sucursales, JSON_UNESCAPED_UNICODE); ?>;

// Función equivalente en JavaScript (actualizada)
function obtenerNombreSucursal(codigoBanco, codigoSucursal) {
    if (!codigoBanco || !codigoSucursal || codigoSucursal.length !== 4) {
        return 'Sucursal no identificada para ese banco';
    }
    
    if (sucursalesCubaJS[codigoBanco] && sucursalesCubaJS[codigoBanco][codigoSucursal]) {
        return sucursalesCubaJS[codigoBanco][codigoSucursal];
    }
    
    return 'Sucursal no identificada para ese banco';
}


// Función principal para actualizar el desglose (Manteniendo TODA tu lógica original)
function actualizarDesglose() {
    const cuentaInput = document.getElementById('cuentaBancaria');
    let cuenta = cuentaInput.value.trim();
    
    // MANTENER TU LÓGICA: solo números
    const cuentaLimpia = cuenta.replace(/\D/g, '');
    const longitud = cuentaLimpia.length;
    
    // MANTENER TU LÓGICA: Obtener componentes
    const codigoBanco = longitud >= 2 ? cuentaLimpia.substring(0, 2) : '';
    const codigoSucursal = longitud >= 6 ? cuentaLimpia.substring(2, 6) : '';
    const codigoTipoCuenta = longitud >= 8 ? cuentaLimpia.substring(6, 8) : '';
    const numeroCuenta = longitud >= 8 ? cuentaLimpia.substring(8) : '';
    
    // MANTENER TU LÓGICA: Obtener nombres con validaciones
    const nombreBanco = bancosCuba[codigoBanco] || 'Banco Desconocido';
    const nombreSucursal = obtenerNombreSucursal(codigoBanco, codigoSucursal);
    const descripcionTipo = tiposCuenta[codigoTipoCuenta] || 'Tipo de cuenta desconocido';
    
    // Referencias a los campos (Ajustado a los IDs del nuevo diseño parejo)
    const nombreBancoInput = document.getElementById('nombreBanco');
    const nombreSucursalInput = document.getElementById('nombreSucursal');
    const noClienteInput = document.getElementById('nocliente');
    const tipoCuentaInput = document.getElementById('tipoCuentaInput');
    const sucursalInfo = document.getElementById('sucursalInfo');
    const infoAdicional = document.getElementById('infoAdicional');
    const cuentaError = document.getElementById('cuentaError');
    
    // MANTENER TU LÓGICA: Validar cuenta
    const esValida = validarCuentaBancariaCompleta(cuentaLimpia);
    cuentaValida = esValida;
    
    // MANTENER TU LÓGICA: Aplicar estilos según validación
    if (cuentaLimpia.length > 0) {
        if (esValida) {
            cuentaInput.classList.remove('is-invalid');
            cuentaInput.classList.add('is-valid');
            if (cuentaError) cuentaError.style.display = 'none';
        } else {
            cuentaInput.classList.remove('is-valid');
            cuentaInput.classList.add('is-invalid');
            if (cuentaError) cuentaError.style.display = 'block';
            
            // MANTENER TU LÓGICA: abrir automáticamente el desglose
            if (!esValida && longitud >= 8) {
                const desglose = document.getElementById('desgloseCuenta');
                if (desglose && desglose.style.display !== 'block') {
                    desglose.style.display = 'block';
                    desgloseVisible = true;
                }
            }
        }
    } else {
        cuentaInput.classList.remove('is-valid', 'is-invalid');
        if (cuentaError) cuentaError.style.display = 'none';
    }
    
    // MEJORA: Actualizar campo del banco (Ahora es automático)
    if (nombreBancoInput) {
        nombreBancoInput.value = (longitud >= 2) ? nombreBanco : '';
        if (nombreBanco === 'Banco Desconocido' && longitud >= 2) {
            nombreBancoInput.classList.add('banco-desconocido');
            nombreBancoInput.style.color = '#e81123';
            nombreBancoInput.style.fontWeight = '600';
        } else {
            nombreBancoInput.classList.remove('banco-desconocido');
            nombreBancoInput.style.color = '';
            nombreBancoInput.style.fontWeight = '';
        }
    }
    
    // MANTENER TU LÓGICA: campo de sucursal
    if (nombreSucursalInput) {
        nombreSucursalInput.value = codigoSucursal;
        if (nombreSucursal === 'Sucursal no identificada para ese banco' && longitud >= 6) {
            nombreSucursalInput.classList.add('sucursal-no-identificada');
            nombreSucursalInput.style.color = '#ff8c00';
            nombreSucursalInput.style.fontWeight = '600';
        } else {
            nombreSucursalInput.classList.remove('sucursal-no-identificada');
            nombreSucursalInput.style.color = '';
            nombreSucursalInput.style.fontWeight = '';
        }
    }
    
    // MANTENER TU LÓGICA: información de sucursal (Cuadro de abajo)
    if (sucursalInfo && codigoSucursal) {
        let icono, color, estilo;
        if (nombreSucursal === 'Sucursal no identificada para ese banco') {
            icono = 'fa-exclamation-triangle';
            color = 'text-warning';
            estilo = 'sucursal-no-identificada';
        } else {
            icono = 'fa-building';
            color = 'text-primary';
            estilo = '';
        }
        
        sucursalInfo.innerHTML = `
            <div class="d-inline-flex align-items-center">
                <i class="fas ${icono} me-2 ${color}"></i>
                <span class="${estilo} me-2">
                    <strong>${nombreSucursal}</strong>
                </span>
                <span class="text-muted">|</span>
                <small class="text-muted ms-2">Código Sucursal: ${codigoSucursal}</small>
            </div>
        `;
        sucursalInfo.style.display = 'flex';
    } else if (sucursalInfo) {
        sucursalInfo.innerHTML = '<span class="text-muted small">Esperando datos de cuenta...</span>';
    }
    
    // MANTENER TU LÓGICA: número de cuenta del cliente
    if (noClienteInput) {
        if (longitud === 16) {
            noClienteInput.value = numeroCuenta;
        } else if (longitud === 14) {
            noClienteInput.value = numeroCuenta + ' (6 dígitos)';
        } else {
            noClienteInput.value = '';
        }
    }
    
    // MANTENER TU LÓGICA: tipo de cuenta
    if (tipoCuentaInput) {
        if (codigoTipoCuenta) {
            tipoCuentaInput.value = `${codigoTipoCuenta} → ${descripcionTipo}`;
            if (descripcionTipo === 'Tipo de cuenta desconocido') {
                tipoCuentaInput.style.color = '#ff8c00';
                tipoCuentaInput.style.fontWeight = '600';
            } else {
                tipoCuentaInput.style.color = '';
                tipoCuentaInput.style.fontWeight = '';
            }
        } else {
            tipoCuentaInput.value = '';
        }
    }
    
    // MANTENER TU LÓGICA: información adicional (Alertas detalladas)
    if (infoAdicional) {
        let mensajes = [];
        if (esValida) {
            if (nombreBanco === 'Banco Desconocido') {
                mensajes.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> Banco no reconocido`);
            }
            if (nombreSucursal === 'Sucursal no identificada para ese banco') {
                mensajes.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> Sucursal no registrada`);
            }
            if (descripcionTipo === 'Tipo de cuenta desconocido') {
                mensajes.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> Tipo de cuenta no reconocido`);
            }
            
            if (mensajes.length > 0) {
                infoAdicional.innerHTML = `
                    <div class="alert alert-warning p-2 mb-2 w-100">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Cuenta válida pero con advertencias:</strong>
                        <ul class="mb-0 mt-1">${mensajes.map(msg => `<li style="font-size: 12px;">${msg}</li>`).join('')}</ul>
                    </div>
                    <div class="alert alert-success p-2 mb-0 w-100">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Cuenta válida:</strong> ${longitud} dígitos - Formato ${longitud === 16 ? 'estándar' : 'antiguo'}
                    </div>`;
            } else {
                infoAdicional.innerHTML = `
                    <div class="alert alert-success p-2 mb-0 w-100">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Cuenta válida:</strong> ${longitud} dígitos - Formato ${longitud === 16 ? 'estándar' : 'antiguo'}
                    </div>`;
            }
        } else if (cuentaLimpia.length > 0) {
            infoAdicional.innerHTML = `
                <div class="alert alert-warning p-2 mb-0 w-100">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Validación:</strong> ${getMensajeError(cuentaLimpia)}
                </div>`;
        } else {
            infoAdicional.innerHTML = '';
        }
    }
    
    // MANTENER TU LÓGICA: Sincronizar Popup
    actualizarDesglosePopup(cuentaLimpia, longitud, codigoBanco, nombreBanco, 
                           codigoSucursal, nombreSucursal, 
                           codigoTipoCuenta, descripcionTipo, numeroCuenta);
    
    // MANTENER TU LÓGICA: Auto-abrir desglose si hay errores detectados
    if ((nombreBanco === 'Banco Desconocido' || nombreSucursal === 'Sucursal no identificada para ese banco') && cuentaLimpia.length >= 6) {
        if (!desgloseVisible) {
            abrirDesglose(null);
        }
    }
}


// Función para actualizar el contenido del desglose
function actualizarDesglosePopup(cuenta, longitud, codigoBanco, nombreBanco, 
                                codigoSucursal, nombreSucursal, 
                                codigoTipoCuenta, descripcionTipo, numeroCuenta) {
    const desglose = document.getElementById('desgloseCuenta');
    if (!desglose) return;
    
    // Determinar si es válida
    const esValida = validarCuentaBancariaCompleta(cuenta);
    
    // Determinar clase del badge
    let badgeClass, badgeText;
    if (cuenta.length === 0) {
        badgeClass = 'bg-secondary';
        badgeText = 'Sin datos';
    } else if (esValida) {
        badgeClass = longitud === 16 ? 'bg-success' : 'bg-warning';
        badgeText = longitud + ' dígitos - ' + (longitud === 16 ? 'Estándar' : 'Antiguo');
    } else {
        badgeClass = 'bg-danger';
        badgeText = longitud + ' dígitos - Inválido';
    }
    
    // Formatear número para visualización
    let visualizacion = '';
    if (cuenta.length >= 2) {
        visualizacion = cuenta.substring(0, 2);
        if (cuenta.length >= 6) {
            visualizacion += ' - ' + cuenta.substring(2, 6);
            if (cuenta.length >= 8) {
                visualizacion += ' - ' + cuenta.substring(6, 8);
                if (cuenta.length > 8) {
                    visualizacion += ' - ' + cuenta.substring(8);
                }
            }
        }
    }
    
    // Construir contenido
    let contenido = `
        <button type="button" class="desglose-close-btn" onclick="cerrarDesglose(event)" title="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="desglose-header">
            <div class="desglose-title">
                <i class="fas fa-credit-card"></i>
                <span>Desglose de Cuenta Bancaria</span>
            </div>
        </div>
        
        <div class="desglose-numero">
            ${visualizacion || 'No ingresado'}
            <span class="badge ${badgeClass} ms-2">${badgeText}</span>
        </div>
        
        <div class="desglose-item">
            <span>Número completo:</span>
            <span class="desglose-valor">${cuenta || 'No ingresado'}</span>
        </div>`;
    
    // Agregar información del banco
    if (codigoBanco) {
        contenido += `
        <div class="desglose-item">
            <span>Banco (AA):</span>
            <span class="desglose-valor ${nombreBanco === 'Banco Desconocido' ? 'banco-desconocido' : ''}">
                <i class="fas ${nombreBanco === 'Banco Desconocido' ? 'fa-exclamation-triangle text-danger' : 'fa-bank'} me-1"></i>
                ${codigoBanco} → ${nombreBanco}
            </span>
        </div>`;
    }
    
    // Agregar información de la sucursal
    if (codigoSucursal) {
        contenido += `
        <div class="desglose-item">
            <span>Sucursal (BBBB):</span>
            <span class="desglose-valor ${nombreSucursal.includes('no identificada') ? 'sucursal-no-identificada' : ''}">
                <i class="fas ${nombreSucursal.includes('no identificada') ? 'fa-exclamation-circle text-warning' : 'fa-building'} me-1"></i>
                ${codigoSucursal} → ${nombreSucursal}
            </span>
        </div>`;
    }
    
    // Agregar información del tipo de cuenta
    if (codigoTipoCuenta) {
        contenido += `
        <div class="desglose-item">
            <span>Tipo cuenta (CC):</span>
            <span class="desglose-valor ${descripcionTipo === 'Tipo de cuenta desconocido' ? 'text-warning' : ''}">
                <i class="fas fa-credit-card me-1"></i>
                ${codigoTipoCuenta} → ${descripcionTipo}
            </span>
        </div>`;
    }
    
    // Agregar información del número de cuenta
    if (numeroCuenta) {
        const digitos = longitud === 16 ? '8 dígitos' : 
                       longitud === 14 ? '6 dígitos' : 
                       numeroCuenta.length + ' dígitos';
        contenido += `
        <div class="desglose-item">
            <span>Número cuenta:</span>
            <span class="desglose-valor">${numeroCuenta} <small class="text-muted">(${digitos})</small></span>
        </div>`;
    }
    
    // Agregar mensajes de advertencia
    if (nombreBanco === 'Banco Desconocido') {
        contenido += `
        <div class="alert alert-danger mt-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Banco Desconocido:</strong> El código <strong>${codigoBanco}</strong> no está registrado en el sistema.
        </div>`;
    }
    
    if (nombreSucursal.includes('no identificada')) {
        contenido += `
        <div class="alert alert-warning mt-2">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Sucursal no identificada:</strong> La sucursal <strong>${codigoSucursal}</strong> no está registrada para este banco.
        </div>`;
    }
    
    if (descripcionTipo === 'Tipo de cuenta desconocido') {
        contenido += `
        <div class="alert alert-warning mt-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Tipo de cuenta desconocido:</strong> El código <strong>${codigoTipoCuenta}</strong> no está registrado.
        </div>`;
    }
    
    // Agregar mensaje de error si la cuenta es inválida
    if (!esValida && cuenta.length > 0) {
        contenido += `
        <div class="alert alert-danger mt-2">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error de validación:</strong> ${getMensajeError(cuenta)}
        </div>`;
    }
    
    // Agregar nota informativa
    if (cuenta.length >= 6) {
        contenido += `
        <div class="alert alert-info mt-2">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Nota:</strong> El sistema valida bancos, sucursales y tipos de cuenta según los registros oficiales.
        </div>`;
    }
    
    // Botón para cerrar
    contenido += `
    <div class="d-grid gap-2 mt-3">
        <button type="button" class="btn btn-primary" onclick="cerrarDesglose(event)">
            <i class="fas fa-check me-2"></i>Entendido
        </button>
    </div>`;
    
    desglose.innerHTML = contenido;
}
		
		
// Función para validar cuenta bancaria completa
function validarCuentaBancariaCompleta(cuenta) {
    const longitud = cuenta.length;
    
    // Debe tener 14 o 16 dígitos
    if (longitud !== 14 && longitud !== 16) {
        return false;
    }
    
    // Debe contener solo números
    if (!/^\d+$/.test(cuenta)) {
        return false;
    }
    
    // Los primeros 2 dígitos deben ser un banco válido
    const codigoBanco = cuenta.substring(0, 2);
    if (!bancosCuba[codigoBanco]) {
        return false;
    }
    
    // Los dígitos 6-7 deben ser un tipo de cuenta válido (si existen)
    if (longitud >= 8) {
        const codigoTipo = cuenta.substring(6, 8);
        if (!tiposCuenta[codigoTipo]) {
            return false;
        }
    }
    
    return true;
}


// Función para mostrar/ocultar desglose - SIMPLIFICADA
function toggleDesgloseCuenta(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (!desglose || !overlay) return false;
    
    // Si ya está visible, cerrarlo
    if (desgloseVisible) {
        cerrarDesglose();
        return false;
    }
    
    // Actualizar datos antes de mostrar
    actualizarDesglose();
    
    // Mostrar overlay y desglose
    overlay.classList.add('open');
    desglose.style.display = 'block';
    desgloseVisible = true;
    
    // Agregar evento para cerrar con ESC
    document.addEventListener('keydown', cerrarDesgloseConESC);
    
    // Agregar evento para cerrar al hacer clic en overlay
    overlay.addEventListener('click', cerrarDesglose);
    
    return false;
}

// Función simplificada para cerrar
function cerrarDesglose(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (desglose) {
        desglose.style.display = 'none';
    }
    
    if (overlay) {
        overlay.classList.remove('open');
        overlay.removeEventListener('click', cerrarDesglose);
    }
    
    desgloseVisible = false;
    document.removeEventListener('keydown', cerrarDesgloseConESC);
    
    return false;
}

// Función para cerrar con tecla ESC
function cerrarDesgloseConESC(event) {
    if (event.key === 'Escape' && desgloseVisible) {
        cerrarDesglose();
    }
}


// Función para obtener mensaje de error (actualizada)
function getMensajeError(cuenta) {
    const longitud = cuenta.length;
    
    if (longitud === 0) {
        return 'Ingrese una cuenta bancaria';
    }
    
    if (!/^\d+$/.test(cuenta)) {
        return 'Solo se permiten números';
    }
    
    if (longitud < 14) {
        return 'Faltan ' + (14 - longitud) + ' dígitos (mínimo 14)';
    }
    
    if (longitud > 16) {
        return 'Sobran ' + (longitud - 16) + ' dígitos (máximo 16)';
    }
    
    if (longitud !== 14 && longitud !== 16) {
        return 'Debe tener 14 o 16 dígitos exactos';
    }
    
    const codigoBanco = cuenta.substring(0, 2);
    if (!bancosCuba[codigoBanco]) {
        return 'Código de banco ' + codigoBanco + ' no reconocido (Banco Desconocido)';
    }
    
    if (longitud >= 8) {
        const codigoTipo = cuenta.substring(6, 8);
        if (!tiposCuenta[codigoTipo]) {
            return 'Tipo de cuenta ' + codigoTipo + ' no reconocido';
        }
    }
    
    return 'Formato de cuenta inválido';
}


// Función para abrir el desglose (actualizada)
function abrirDesglose(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (!desglose || !overlay) return false;
    
    // Actualizar datos antes de mostrar
    actualizarDesglose();
    
    // Mostrar overlay y desglose
    overlay.classList.add('open');
    desglose.style.display = 'block';
    desgloseVisible = true;
    
    // Agregar eventos para cerrar (solo uno)
    overlay.addEventListener('click', cerrarDesglose);
    document.addEventListener('keydown', cerrarDesgloseConESC);
    
    return false;
}




      // Validar formulario antes de enviar
        document.getElementById('configForm')?.addEventListener('submit', function(e) {
            // Primero validar cuenta bancaria
            const cuentaInput = document.getElementById('cuentaBancaria');
            const cuenta = cuentaInput?.value.trim().replace(/\D/g, '') || '';
            
            // Validar cuenta bancaria
            if (cuenta && !validarCuentaBancariaCompleta(cuenta)) {
                e.preventDefault();
                
                // Mostrar error
                Swal.fire({
                    icon: 'error',
                    title: 'Error en cuenta bancaria',
                    html: `
                        <div style="text-align: left; font-size: 14px;">
                            <p><strong>La cuenta bancaria no es válida.</strong></p>
                            <div class="alert alert-danger mt-3">
                                <strong>Error:</strong> ${getMensajeError(cuenta)}
                            </div>
                            <div class="alert alert-info mt-3">
                                <strong>Requisitos:</strong>
                                <ul class="mb-0">
                                    <li>14 dígitos (formato antiguo) o 16 dígitos (estándar)</li>
                                    <li>Primeros 2 dígitos: código de banco válido</li>
                                    <li>Dígitos 7-8: tipo de cuenta válido</li>
                                    <li>Solo números, sin espacios ni caracteres especiales</li>
                                </ul>
                            </div>
                            <p class="mt-3">Por favor, corrija la cuenta bancaria antes de guardar.</p>
                        </div>
                    `,
                    confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                    width: 550,
    customClass: {
        popup: 'sweetalert-dark'
    }
                });
                
                // Enfocar el campo y abrir el desglose
                if (cuentaInput) {
                    cuentaInput.focus();
                    cuentaInput.classList.add('is-invalid');
                    
                    const desglose = document.getElementById('desgloseCuenta');
                    if (desglose) {
                        desglose.style.display = 'block';
                        desgloseVisible = true;
                        
                        // Desplazar hasta el campo
                        cuentaInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                
                return false;
            }
            
            // Validar otros campos obligatorios
            const nombreEmpresa = document.querySelector('input[name="nombre_empresa"]');
            const nombreProyecto = document.querySelector('input[name="nombre_proyecto"]');
            
            if (!nombreEmpresa?.value.trim()) {
                e.preventDefault();
                Swal.fire('Error', 'El nombre de la empresa es obligatorio', 'error');
                nombreEmpresa?.focus();
                return false;
            }
            
            if (!nombreProyecto?.value.trim()) {
                e.preventDefault();
                Swal.fire('Error', 'El nombre del proyecto es obligatorio', 'error');
                nombreProyecto?.focus();
                return false;
            }
            
            // Si WhatsApp está activado, validar número
            const whatsappToggle = document.getElementById('whatsappToggle');
            const whatsappNumero = document.getElementById('whatsappNumero');
            
            if (whatsappToggle?.checked && whatsappNumero) {
                const numero = whatsappNumero.value.trim();
                if (!numero) {
                    e.preventDefault();
                    Swal.fire('Error', 'El número de WhatsApp es requerido cuando está activado', 'error');
                    whatsappNumero.focus();
                    return false;
                }
                
                // Validar formato cubano
                const regex = /^\+53[5-8]\d{7}$/;
                if (!regex.test(numero)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Formato de WhatsApp inválido',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p><strong>El número de WhatsApp no tiene el formato correcto.</strong></p>
                                <div class="alert alert-warning mt-3">
                                    <strong>Formato requerido para Cuba:</strong><br>
                                    <code>+53512345678</code>
                                </div>
                                <p>Número ingresado: <strong>${numero}</strong></p>
                            </div>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                        width: 500,
    customClass: {
        popup: 'sweetalert-dark'
    }
                    });
                    whatsappNumero.focus();
                    return false;
                }
            }
            
// Validar código Reeup
    const codReeup = document.querySelector('input[name="cod_reeup"]');
    if (codReeup && codReeup.value.trim()) {
        const reeupPattern = /^\d{3}\.\d{1}\.\d{4}$/;
        if (!reeupPattern.test(codReeup.value.trim())) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Formato de Código Reeup inválido',
                html: `
                    <div style="text-align: left; font-size: 14px;">
                        <p><strong>El código Reeup no tiene el formato correcto.</strong></p>
                        <div class="alert alert-warning mt-3">
                            <strong>Formato requerido:</strong><br>
                            <code>123.1.1234</code>
                        </div>
                        <p>Debe tener exactamente 3 dígitos, punto, 1 dígito, punto, 4 dígitos.</p>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                width: 500,
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
            codReeup.focus();
            return false;
        }
    }
                // Mostrar carga
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            return true;
        });

        // Manejar el toggle de WhatsApp
        const whatsappToggle = document.getElementById('whatsappToggle');
        const whatsappNumero = document.getElementById('whatsappNumero');
        const whatsappStatus = document.getElementById('whatsappStatus');
        
        if (whatsappToggle) {
            whatsappToggle.addEventListener('change', function() {
                const isChecked = this.checked;
                if (whatsappStatus) {
                    whatsappStatus.textContent = isChecked ? 'Activado' : 'Desactivado';
                    whatsappStatus.className = isChecked ? 'text-success' : 'text-danger';
                }
                
                //if (whatsappNumero) {
                    //whatsappNumero.disabled = !isChecked;
                //}
            });
        }
        
        // Manejar el toggle del modo mantenimiento
        const modoMantenimiento = document.getElementById('modoMantenimiento');
        const modoMantenimientoStatus = document.getElementById('modoMantenimientoStatus');
        
        if (modoMantenimiento) {
            modoMantenimiento.addEventListener('change', function() {
                const isChecked = this.checked;
                if (modoMantenimientoStatus) {
                    modoMantenimientoStatus.textContent = isChecked ? 'Activado' : 'Desactivado';
                    modoMantenimientoStatus.className = isChecked ? 'text-warning' : 'text-muted';
                }
            });
        }
        
// Manejar el toggle de la recuperación de contraseña
const activarRecuperacionPassword = document.getElementById('activarRecuperacionPassword');
const recuperacionPasswordStatus = document.getElementById('recuperacionPasswordStatus');

if (activarRecuperacionPassword) {
    activarRecuperacionPassword.addEventListener('change', function() {
        const isChecked = this.checked;
        if (recuperacionPasswordStatus) {
            recuperacionPasswordStatus.textContent = isChecked ? 'Activado' : 'Desactivado';
            recuperacionPasswordStatus.className = isChecked ? 'text-success' : 'text-danger';
            // También puedes cambiar el estilo del texto según el estado
        }
    });
}
        // Event listeners principales
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar
            const cuentaInput = document.getElementById('cuentaBancaria');
            if (cuentaInput) {
                actualizarDesglose();
                cuentaInput.addEventListener('input', actualizarDesglose);
            }
			
// Agregar eventos al campo Reeup
    const codReeupInput = document.querySelector('input[name="cod_reeup"]');
    if (codReeupInput) {
        // Formatear automáticamente mientras se escribe
        codReeupInput.addEventListener('input', function() {
            formatReeup(this);
        });
        
        // Validar al perder foco
        codReeupInput.addEventListener('blur', function() {
            validateReeup(this);
        });
        
        // Validar al cargar la página
        validateReeup(codReeupInput);
    }
            
            // Cerrar panel de temas al hacer clic en overlay
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            // Cerrar panel de temas con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (themePanelOpen) {
                        cerrarPanelTemas();
                    }
                    if (desgloseVisible) {
                        const desglose = document.getElementById('desgloseCuenta');
                        if (desglose) {
                            desglose.style.display = 'none';
                            desgloseVisible = false;
                        }
                    }
                }
            });
            
           // Funcionalidad de WhatsApp
            const whatsappToggle = document.getElementById('whatsappToggle');
            const whatsappNumero = document.getElementById('whatsappNumero');
            const testWhatsAppBtn = document.getElementById('testWhatsAppBtn');
            const whatsappPreview = document.getElementById('whatsappPreview');
            const whatsappLinkPreview = document.getElementById('whatsappLinkPreview');
            const whatsappNumberPreview = document.getElementById('whatsappNumberPreview');
            const copyWhatsAppLink = document.getElementById('copyWhatsAppLink');
            
            if (whatsappToggle && whatsappNumero) {
                // Toggle WhatsApp
                whatsappToggle.addEventListener('change', function() {
                    const isChecked = this.checked;
                    const statusElement = document.getElementById('whatsappStatus');
                    
                    if (isChecked) {
                        statusElement.textContent = 'Activado';
                        statusElement.className = 'text-success';
                        whatsappNumero.disabled = false;
                        testWhatsAppBtn.disabled = false;
                        
                        // Mostrar preview si hay número
                        if (whatsappNumero.value.trim()) {
                            actualizarPreviewWhatsApp(whatsappNumero.value);
                        }
                    } else {
                        statusElement.textContent = 'Desactivado';
                        statusElement.className = 'text-danger';
                        //whatsappNumero.disabled = true;
                        testWhatsAppBtn.disabled = true;
                        whatsappPreview.style.display = 'none';
                    }
                });
                
                // Validar número en tiempo real
                whatsappNumero.addEventListener('input', function() {
                    const numero = this.value.trim();
                    if (numero) {
                        actualizarPreviewWhatsApp(numero);
                    } else {
                        whatsappPreview.style.display = 'none';
                    }
                });
                
                // Botón de prueba
                if (testWhatsAppBtn) {
                    testWhatsAppBtn.addEventListener('click', function() {
                        const numero = whatsappNumero.value.trim();
                        if (numero) {
                            // Formatear número para enlace WhatsApp
                            let numeroWhatsApp = numero.replace(/\s/g, '');
                            if (!numeroWhatsApp.startsWith('+')) {
                                numeroWhatsApp = '+' + numeroWhatsApp;
                            }
                            window.open(`https://wa.me/${numeroWhatsApp}`, '_blank');
                        }
                    });
                }
                
                // Copiar enlace
                if (copyWhatsAppLink) {
                    copyWhatsAppLink.addEventListener('click', function() {
                        // 1. Tomar el valor directamente del input para asegurar que sea el actual
                        const inputVal = document.getElementById('whatsappNumero').value;
                        
                        // 2. Limpiar el número: dejar SOLAMENTE dígitos (quitar +, espacios, guiones)
                        // WhatsApp funciona mejor con formato puro: 5359860773
                        const numeroLimpio = inputVal.replace(/\D/g, ''); 

                        if (numeroLimpio.length < 8) {
                             Swal.fire({
                                icon: 'warning',
                                title: 'Número incompleto',
                                text: 'Por favor verifique el número antes de copiar el enlace.',
                                customClass: { popup: 'sweetalert-dark' }
                            });
                            return;
                        }

                        // 3. Construir el enlace manualmente
                        const link = `https://wa.me/${numeroLimpio}`;

                        // 4. Copiar al portapapeles
                        navigator.clipboard.writeText(link).then(() => {
                            // Efecto visual de éxito
                            const originalHTML = this.innerHTML;
                            this.innerHTML = '<i class="fas fa-check text-success"></i>';
                            this.classList.add('btn-outline-success');
                            this.classList.remove('btn-outline-secondary');
                            
                            setTimeout(() => {
                                this.innerHTML = originalHTML; // Restaurar icono
                                this.classList.remove('btn-outline-success');
                                this.classList.add('btn-outline-secondary');
                            }, 2000);
                        }).catch(err => {
                            console.error('Error al copiar:', err);
                            // Fallback por si el navegador bloquea el portapapeles
                            prompt("Copia el enlace manualmente:", link);
                        });
                    });
                }                
                function actualizarPreviewWhatsApp(numero) {
                    // Formatear número
                    let numeroLimpio = numero.replace(/\s/g, '');
                    if (!numeroLimpio.startsWith('+')) {
                        numeroLimpio = '+' + numeroLimpio;
                    }
                    
                    // Actualizar preview
                    if (whatsappNumberPreview) {
                        whatsappNumberPreview.textContent = numeroLimpio;
                    }
                    if (whatsappLinkPreview) {
                        whatsappLinkPreview.href = `https://wa.me/${numeroLimpio}`;
                    }
                    if (whatsappPreview) {
                        whatsappPreview.style.display = 'block';
                    }
                }
            }
			
		 const mesSelect = document.getElementById('mesOperaciones');
			const anioSelect = document.getElementById('anioOperaciones');
			
			if (mesSelect && anioSelect) {
				// Actualizar al cambiar mes o año
				mesSelect.addEventListener('change', actualizarPeriodoContable);
				anioSelect.addEventListener('change', actualizarPeriodoContable);
				
				// Validar inicialmente
				actualizarPeriodoContable();
			}
        });

function actualizarPeriodoContable() {
    const mesSelect = document.getElementById('mesOperaciones');
    const anioSelect = document.getElementById('anioOperaciones');
    const infoDiv = document.getElementById('infoPeriodoContable');
    const validacionDiv = document.getElementById('validacionPeriodo');
    const mensajeValidacion = document.getElementById('mensajeValidacion');
    const alertaValidacion = document.getElementById('alertaValidacion');
    const fechaCompletaInput = document.getElementById('fechaCompletaInput');
    
    if (!mesSelect || !anioSelect) return;
    
    const mes = mesSelect.value;
    const anio = parseInt(anioSelect.value);
    const anioActual = new Date().getFullYear();
    const mesActual = new Date().getMonth() + 1;
    
    // Construir fecha completa (siempre día 1)
    const fechaCompleta = anio + '-' + mes + '-01';
    fechaCompletaInput.value = fechaCompleta;
    
    // Nombres de meses en español
    const mesesNombres = {
        '01': 'Enero', '02': 'Febrero', '03': 'Marzo', '04': 'Abril',
        '05': 'Mayo', '06': 'Junio', '07': 'Julio', '08': 'Agosto',
        '09': 'Septiembre', '10': 'Octubre', '11': 'Noviembre', '12': 'Diciembre'
    };
    
    const mesNombre = mesesNombres[mes];
    
    // Actualizar información visual
    if (infoDiv) {
        infoDiv.innerHTML = `
            <small class="text-muted d-block mb-2">
                <i class="fas fa-calendar-check me-1"></i>
                <strong>Período Contable a Configurar</strong>
            </small>
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <div class="text-center bg-primary text-white rounded p-2" style="min-width: 70px;">
                        <div class="fw-bold" style="font-size: 20px;">01</div>
                        <div style="font-size: 12px;">${mesNombre.substring(0, 3)}</div>
                        <div style="font-size: 10px; opacity: 0.9;">${anio}</div>
                    </div>
                </div>
                <div>
                    <div class="fw-bold" style="color: var(--win-text-primary);">
                        ${mesNombre} ${anio}
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-database me-1"></i>
                        Se guardará como: <code>${fechaCompleta}</code>
                    </small>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-file-invoice me-1"></i>
                        Facturación permitida desde: 1 de ${mesNombre} de ${anio}
                    </small>
                </div>
            </div>
        `;
    }
    
    // Validaciones
    let esValido = true;
    let mensaje = '';
    let tipoAlerta = 'success';
    
    // Validar que no sea período futuro
    if (anio > anioActual || (anio === anioActual && parseInt(mes) > mesActual)) {
        esValido = false;
        mensaje = '<strong>ADVERTENCIA:</strong> No se puede seleccionar un período futuro';
        tipoAlerta = 'danger';
        
        // Auto-corregir si es futuro
        if (anio > anioActual) {
            anioSelect.value = anioActual;
            actualizarPeriodoContable();
            return;
        }
    }
    
    // Validar que no sea anterior a 2010
    if (anio < 2010) {
        esValido = false;
        mensaje = '<strong>ERROR:</strong> El año mínimo permitido es 2010';
        tipoAlerta = 'danger';
        anioSelect.value = 2010;
        actualizarPeriodoContable();
        return;
    }
    
    // Si es el período actual, mostrar información normal
    if (anio === anioActual && parseInt(mes) === mesActual) {
        mensaje = '<strong>Período actual:</strong> Se permite facturación para el mes en curso';
        tipoAlerta = 'success';
    } else if (anio === anioActual && parseInt(mes) < mesActual) {
        mensaje = '<strong>Período pasado:</strong> Modificación excepcional - se permitirá facturación histórica';
        tipoAlerta = 'warning';
    } else if (anio < anioActual) {
        mensaje = '<strong>Período histórico:</strong> Solo para correcciones contables - use con precaución';
        tipoAlerta = 'warning';
    }
    
    // Mostrar validación
    if (validacionDiv && mensajeValidacion && alertaValidacion) {
        validacionDiv.style.display = 'block';
        alertaValidacion.className = `alert alert-${tipoAlerta}`;
        mensajeValidacion.innerHTML = `<i class="fas fa-${tipoAlerta === 'success' ? 'check-circle' : tipoAlerta === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'} me-2"></i>${mensaje}`;
    }
    
    // Aplicar estilos a los selects
    const aplicarEstilo = (elemento, valido) => {
        elemento.classList.remove('is-valid', 'is-invalid');
        elemento.classList.add(valido ? 'is-valid' : 'is-invalid');
    };
    
    aplicarEstilo(mesSelect, esValido);
    aplicarEstilo(anioSelect, esValido);
}

function sincronizarPeriodoActual() {
    // Crear fecha actual formateada para mostrar
    const hoy = new Date();
    const mesActual = String(hoy.getMonth() + 1).padStart(2, '0');
    const anioActual = hoy.getFullYear();
    const periodoFormateado = hoy.toLocaleDateString('es-ES', {
        month: 'long',
        year: 'numeric'
    }).replace(/^\w/, c => c.toUpperCase());

    // Calcular fechas de inicio y fin del mes
    const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
    
    const formatoFecha = (fecha) => fecha.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });

    Swal.fire({
        title: '<i class="fas fa-sync-alt me-2"></i>Sincronizar Período Contable',
        html: `
            <div class="sweet-alert-content">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡ATENCIÓN! Esta acción afectará múltiples áreas del sistema:</strong>
                </div>
                
                <div style="text-align: left; font-size: 14px; line-height: 1.5;">
                    <p><strong>¿Desea sincronizar el período contable con el mes actual?</strong></p>
                    
                    <div class="alert alert-info mt-3 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar-alt fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-1"><strong>Nuevo período:</strong></h6>
                                <h5 class="mb-0 text-primary">${periodoFormateado}</h5>
                                <small class="text-muted">${formatoFecha(primerDiaMes)} al ${formatoFecha(ultimoDiaMes)}</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger mb-3">
                        <h6><i class="fas fa-exclamation-circle me-2"></i><strong>¡ADVERTENCIA CRÍTICA!</strong></h6>
                        <ul class="mb-0 ps-3" style="font-size: 13px;">
                            <li>Esta acción <strong>afecta irreversiblemente</strong> operaciones, reportes, contabilidad y cuadres</li>
                            <li>Los cierres contables se procesan automáticamente en el cierre mensual</li>
                            <li>El uso de esta opción es <strong>exclusivamente bajo su responsabilidad</strong></li>
                            <li>Operaciones anteriores se bloquearán permanentemente para modificación</li>
                            <li>Reportes anteriores no podrán ser recalculados</li>
                        </ul>
                        <div class="mt-2 pt-2 border-top border-danger">
                            <small class="text-danger">
                                <i class="fas fa-shield-alt me-1"></i>
                                <strong>ADVERTENCIA:</strong> Utilice esta opción SOLO con fines de sincronización inicial o corrección de períodos. Los cierres se registran automáticamente al procesar el cierre mensual.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0078d4',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Sincronizar período',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        reverseButtons: true,
        showLoaderOnConfirm: false,
        focusCancel: true,
        allowOutsideClick: () => !Swal.isLoading(),
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        customClass: {
            popup: 'sweetalert-dark animated animate__fadeIn',
            title: 'sweet-title',
            htmlContainer: 'sweet-html-container'
        },
        preConfirm: () => {
            return new Promise((resolve) => {
                // Aquí podrías agregar validaciones adicionales
                resolve(true);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loader mientras se procesa
            Swal.fire({
                title: 'Sincronizando...',
                html: 'Actualizando período contable y configuraciones del sistema',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: 'var(--win-bg-secondary)',
                customClass: { popup: 'sweetalert-dark' }
            });

            // Simular proceso asíncrono
            setTimeout(() => {
                // Actualizar valores en el formulario
                document.getElementById('mesOperaciones').value = mesActual;
                document.getElementById('anioOperaciones').value = anioActual;
                
                // Llamar a función de actualización
                actualizarPeriodoContable();
                
                // Mostrar confirmación con detalles
                Swal.fire({
                    icon: 'success',
                    title: '<i class="fas fa-check-circle me-2"></i>Período sincronizado exitosamente',
                    html: `
                        <div style="text-align: left;">
                            <p>El período contable ha sido actualizado a <strong>${periodoFormateado}</strong></p>
                            <div class="alert alert-success mt-3">
                                <h6><i class="fas fa-check me-2"></i>Cambios aplicados:</h6>
                                <ul class="mb-0 ps-3">
                                    <li>Mes de operaciones: <strong>${mesActual}</strong></li>
                                    <li>Año de operaciones: <strong>${anioActual}</strong></li>
                                    <li>Facturación habilitada para el mes actual</li>
                                    <li>Reportes configurados con nueva fecha de corte</li>
                                </ul>
                            </div>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>¡IMPORTANTE!</strong> No olvide guardar los cambios para que surtan efecto en todo el sistema.
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
					showConfirmButton: false,
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cerrar',
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)',
                    customClass: { 
                        popup: 'sweetalert-dark animated animate__fadeIn',
                        confirmButton: 'btn-success'
                    },
                    timerProgressBar: true
                });
            }, 1000);
        }
    });
}


// ==================== FUNCIÓN MODIFICADA PARA HISTORIAL ====================
async function mostrarHistorialPeriodos() {
    try {
        // Obtener tema actual para estilos
        const temaActual = document.documentElement.getAttribute('data-theme') || 'dark';
        const colorAcento = document.documentElement.getAttribute('data-accent') || '#0078d4';
        
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Cargando historial...',
            allowOutsideClick: false,
            background: temaActual === 'dark' ? '#1e1e2d' : '#f8f9fa',
            color: temaActual === 'dark' ? '#ffffff' : '#212529',
            customClass: { 
                popup: 'sweetalert-dark'
            },
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Crear formulario para enviar la petición AJAX
        const formData = new FormData();
        formData.append('action', 'obtener_historial_cierres');
        formData.append('usuario_id', '<?php echo $_SESSION["usuario_id"]; ?>');

        // Enviar petición AJAX al mismo archivo
        const response = await fetch('configuracion.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        // Verificar si hay datos
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar el historial');
        }

        // Formatear los datos para la tabla con tema actual
        const historialHTML = data.historial && data.historial.length > 0 
            ? generarTablaHistorial(data.historial, temaActual)
            : `<div class="alert alert-info mt-2 mb-2" style="
                background: ${temaActual === 'dark' ? 'rgba(13, 110, 253, 0.1)' : '#d1ecf1'}; 
                border: 1px solid ${temaActual === 'dark' ? 'rgba(13, 110, 253, 0.3)' : '#bee5eb'}; 
                color: ${temaActual === 'dark' ? '#8bb9fe' : '#0c5460'}; 
                padding: 12px 15px;
                border-radius: 6px;
                font-size: 13px;
                margin: 0;
            ">
                  <i class="fas fa-info-circle me-2"></i>
                  No hay registros de cierres en el historial para el mes en curso.
               </div>`;

        // Cerrar el loader
        Swal.close();

        // Mostrar el historial en modal
        Swal.fire({
            title: '<i class="fas fa-history me-2"></i>Historial de Cierres Contables Mes de Operaciones',
            html: `
                <div style="text-align: left; font-size: 14px; max-height: 60vh; overflow-y: auto; padding: 0 5px;">
                    <div class="table-responsive" style="
                        background: ${temaActual === 'dark' ? '#1e1e2d' : '#ffffff'};
                        border-radius: 6px;
                        padding: 1px;
                    ">
                        ${historialHTML}
                    </div>
                </div>
                <div class="mt-3 pt-3 border-top" style="
                    border-color: ${temaActual === 'dark' ? '#3d3d3d' : '#dee2e6'} !important;
                    padding-top: 15px;
                    margin-top: 15px;
                    font-size: 13px;
                ">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div style="color: ${temaActual === 'dark' ? '#a8a8b3' : '#6c757d'};">
                                <i class="fas fa-info-circle me-2"></i>
                                Los cierres se registran automáticamente al procesar el cierre mensual.
                            </div>
								<div class="mt-1" style="color: ${temaActual === 'dark' ? '#8a8a9e' : '#868e96'}; font-size: 12px;">
									<i class="fas fa-database me-1"></i>
									Total de registros Año Operaciones: <strong>${data.total_anio || 0}</strong>
								</div>
								<div class="mt-1" style="color: ${temaActual === 'dark' ? '#8a8a9e' : '#868e96'}; font-size: 12px;">
									<i class="fas fa-database me-1"></i>
									Total de registros BD: <strong>${data.total || 0}</strong>
								</div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-sm" onclick="abrirResumenCierres()" style="
                                background: ${temaActual === 'dark' ? '#2d2d44' : '#f8f9fa'};
                                border: 1px solid ${temaActual === 'dark' ? '#3d3d3d' : '#dee2e6'};
                                color: ${temaActual === 'dark' ? '#ffffff' : '#212529'};
                                padding: 6px 12px;
                                border-radius: 4px;
                                font-size: 12px;
                                transition: all 0.2s ease;
                            " onmouseover="this.style.background='${temaActual === 'dark' ? '#3d3d3d' : '#e9ecef'}; this.style.borderColor='${colorAcento}'" 
                               onmouseout="this.style.background='${temaActual === 'dark' ? '#2d2d44' : '#f8f9fa'}; this.style.borderColor='${temaActual === 'dark' ? '#3d3d3d' : '#dee2e6'}'">
                                <i class="fas fa-chart-pie me-1"></i> VER HISTORIAL COMPLETO
                            </button>
                        </div>
                    </div>
                </div>
            `,
            width: 900,
            background: temaActual === 'dark' ? '#1e1e2d' : '#ffffff',
            color: temaActual === 'dark' ? '#ffffff' : '#212529',
            showConfirmButton: true,
            showCloseButton: true,
            closeButtonHtml: '<i class="fas fa-times"></i>',
            confirmButtonText: '<i class="fas fa-times me-1"></i> Cerrar',
            confirmButtonColor: colorAcento,
            customClass: {
                popup: 'sweetalert-dark',
                title: 'sweetalert-title-dark',
                htmlContainer: 'sweetalert-content-dark',
                confirmButton: 'sweetalert-confirm-dark',
                closeButton: 'sweetalert-close-dark'
            }
        });

    } catch (error) {
        console.error('Error al cargar el historial:', error);
        
        // Cerrar loader si está abierto
        Swal.close();
        
        const temaActual = document.documentElement.getAttribute('data-theme') || 'dark';
        
        Swal.fire({
            title: 'Error',
            text: 'No se pudo cargar el historial de períodos: ' + error.message,
            icon: 'error',
            showCloseButton: true,
            closeButtonHtml: '<i class="fas fa-times"></i>',
            background: temaActual === 'dark' ? '#1e1e2d' : '#ffffff',
            color: temaActual === 'dark' ? '#ffffff' : '#212529',
            confirmButtonText: '<i class="fas fa-close me-1"></i> Cerrar',
            confirmButtonColor: temaActual === 'dark' ? '#0078d4' : '#0d6efd',
            customClass: {
                popup: 'sweetalert-dark',
                title: 'sweetalert-title-dark',
                htmlContainer: 'sweetalert-content-dark',
                confirmButton: 'sweetalert-confirm-dark',
                closeButton: 'sweetalert-close-dark'
            }
        });
    }
}

// Función auxiliar para generar la tabla HTML con tema actual
function generarTablaHistorial(historial, tema) {
    const meses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    // VALORES FIJOS SEGÚN TEMA (no usar var() dentro de SweetAlert)
    const bgHeader = tema === 'dark' ? '#2d2d44' : '#f8f9fa';
    const bgTable = tema === 'dark' ? '#1e1e2d' : '#ffffff';
    const bgRowEven = tema === 'dark' ? '#1e1e2d' : '#ffffff';
    const bgRowOdd = tema === 'dark' ? '#252538' : '#f8f9fa';
    const bgRowHover = tema === 'dark' ? '#3d3d3d' : '#e9ecef';
    const borderColor = tema === 'dark' ? '#3d3d3d' : '#dee2e6';
    const textColor = tema === 'dark' ? '#ffffff' : '#212529';
    const textMuted = tema === 'dark' ? '#a8a8b3' : '#6c757d';
    const textSecondary = tema === 'dark' ? '#d1d1d1' : '#495057';

    return `
        <table class="table table-sm mb-0" style="
            background: ${bgTable}; 
            color: ${textColor}; 
            margin-bottom: 0;
            font-size: 12.5px;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        ">
            <thead>
                <tr style="background: ${bgHeader} !important;">
                    <th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        border-top-left-radius: 6px;
                        background: ${bgHeader} !important;
                    ">Período</th>
                    <th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        background: ${bgHeader} !important;
                    ">Tipo</th>
                    <!--<th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        background: ${bgHeader} !important;
                    ">Fecha y Hora</th>
                    <th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        background: ${bgHeader} !important;
                    ">Usuario</th>--->
                    <th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        text-align: center;
                        background: ${bgHeader} !important;
                    ">Facturas</th>
                    <th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        text-align: right;
                        background: ${bgHeader} !important;
                    ">Importe Total</th>
                    <th style="
                        border: none; 
                        border-bottom: 1px solid ${borderColor} !important; 
                        padding: 10px 12px;
                        font-weight: 600;
                        color: ${textSecondary};
                        font-size: 12px;
                        border-top-right-radius: 6px;
                        background: ${bgHeader} !important;
                    ">Observaciones</th>
                </tr>
            </thead>
            <tbody>
                ${historial.map((item, index) => `
                    <tr style="
                        background: ${index % 2 === 0 ? bgRowEven : bgRowOdd};
                        transition: all 0.2s ease;
                        border-bottom: 1px solid ${borderColor};
                    " onmouseover="this.style.background='${bgRowHover}'" 
                       onmouseout="this.style.background='${index % 2 === 0 ? bgRowEven : bgRowOdd}'">
                        <td style="
                            border: none; 
                            padding: 8px 12px;
                            vertical-align: middle;
                            background: inherit;
                        ">
                            <div style="font-weight: 600; color: ${textColor}; font-size: 13px;">
                                ${meses[item.periodo_mes - 1] || item.periodo_mes}
                            </div>
                            <div style="font-size: 11px; color: ${textMuted}; margin-top: 2px;">
                                ${item.periodo_anio}
                            </div>
                        </td>
                        <td style="
                            border: none; 
                            padding: 8px 12px;
                            vertical-align: middle;
                            background: inherit;
                        ">
                            ${getTipoCierre(item.tipo, tema)}
                        </td>
                        <!--<td style="
                            border: none; 
                            padding: 8px 12px;
                            vertical-align: middle;
                            background: inherit;
                        ">
                            <div style="font-weight: 500; font-size: 12px; color: ${textColor};">${formatDate(item.fecha_ejecucion)}</div>
                            <div style="font-size: 11px; color: ${textMuted}; margin-top: 2px; font-family: monospace;">${formatTime12h(item.fecha_ejecucion)}</div>
                        </td>
                        <td style="
                            border: none; 
                            padding: 8px 12px;
                            vertical-align: middle;
                            background: inherit;
                        ">
                            <div style="font-weight: 500; color: ${textColor}; font-size: 13px;">
                                ${item.usuario_nombre || 'Sistema'}
                            </div>
                            ${item.usuario_login ? `<div style="font-size: 10px; color: ${textMuted}; margin-top: 1px;">@${item.usuario_login}</div>` : ''}
                        </td>-->
                        <td style="
                            border: none; 
                            padding: 8px 12px;
                            text-align: center;
                            vertical-align: middle;
                            background: inherit;
                        ">
                            <span style="
                                display: inline-block;
                                min-width: 30px;
                                background: ${item.total_facturas > 0 ? 
                                    (tema === 'dark' ? 'rgba(0, 120, 212, 0.2)' : 'rgba(0, 120, 212, 0.1)') : 
                                    (tema === 'dark' ? 'rgba(108, 117, 125, 0.2)' : 'rgba(108, 117, 125, 0.1)')}; 
                                color: ${item.total_facturas > 0 ? 
                                    (tema === 'dark' ? '#8bb9fe' : '#0d6efd') : 
                                    textMuted}; 
                                font-weight: 600; 
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 12px;
                            ">
                                ${item.total_facturas || 0}
                            </span>
                        </td>
                        <td style="
                            border: none; 
                            padding: 8px 12px;
                            text-align: right;
                            vertical-align: middle;
                            background: inherit;
                        ">
                            <div style="
                                font-weight: 600; 
                                color: ${item.importe_total > 0 ? 
                                    (tema === 'dark' ? '#20c997' : '#198754') : 
                                    textColor};
                                font-size: 13px;
                            ">
                                $${parseFloat(item.importe_total || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </div>
                        </td>
                        <td style="
                            border: none; 
                            padding: 8px 12px;
                            vertical-align: middle;
                            max-width: 200px;
                            background: inherit;
                        ">
                            ${item.observaciones ? `
                                <div style="
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                    cursor: help;
                                    color: ${textSecondary};
                                    font-size: 12px;
                                " title="${item.observaciones.replace(/"/g, '&quot;')}">
                                    ${item.observaciones.length > 30 ? item.observaciones.substring(0, 30) + '...' : item.observaciones}
                                </div>
                            ` : `<div style="color: ${textMuted}; font-style: italic; font-size: 11.5px;">-</div>`}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

// Función para formatear fecha
function formatDate(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Función para formatear hora en formato 12h con am/pm
function formatTime12h(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

// Función para obtener el tipo de cierre con tema
function getTipoCierre(tipo, tema) {
    const tipos = {
        1: {
            text: 'Mensual', 
            icon: 'fa-calendar-alt', 
            color: tema === 'dark' ? 'rgba(13, 110, 253, 0.2)' : 'rgba(13, 110, 253, 0.1)', 
            textColor: tema === 'dark' ? '#8bb9fe' : '#0d6efd',
            iconColor: tema === 'dark' ? '#8bb9fe' : '#0d6efd'
        },
        2: {
            text: 'Anual', 
            icon: 'fa-calendar-star', 
            color: tema === 'dark' ? 'rgba(25, 135, 84, 0.2)' : 'rgba(25, 135, 84, 0.1)', 
            textColor: tema === 'dark' ? '#20c997' : '#198754',
            iconColor: tema === 'dark' ? '#20c997' : '#198754'
        },
        3: {
            text: 'Ajuste', 
            icon: 'fa-adjust', 
            color: tema === 'dark' ? 'rgba(255, 193, 7, 0.2)' : 'rgba(255, 193, 7, 0.1)', 
            textColor: tema === 'dark' ? '#ffc107' : '#664d03',
            iconColor: tema === 'dark' ? '#ffc107' : '#ffc107'
        },
        4: {
            text: 'Corrección', 
            icon: 'fa-wrench', 
            color: tema === 'dark' ? 'rgba(111, 66, 193, 0.2)' : 'rgba(111, 66, 193, 0.1)', 
            textColor: tema === 'dark' ? '#8b5cf6' : '#6f42c1',
            iconColor: tema === 'dark' ? '#8b5cf6' : '#6f42c1'
        },
        5: {
            text: 'Reapertura', 
            icon: 'fa-redo', 
            color: tema === 'dark' ? 'rgba(108, 117, 125, 0.2)' : 'rgba(108, 117, 125, 0.1)', 
            textColor: tema === 'dark' ? '#adb5bd' : '#6c757d',
            iconColor: tema === 'dark' ? '#adb5bd' : '#6c757d'
        }
    };
    
    const info = tipos[tipo] || {
        text: 'Otro', 
        icon: 'fa-question', 
        color: tema === 'dark' ? 'rgba(108, 117, 125, 0.2)' : 'rgba(108, 117, 125, 0.1)',
        textColor: tema === 'dark' ? '#adb5bd' : '#6c757d',
        iconColor: tema === 'dark' ? '#adb5bd' : '#6c757d'
    };
    
    return `<div style="
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: ${info.color};
        color: ${info.textColor};
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        border: 1px solid ${tema === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)'};
    ">
        <i class="fas ${info.icon}" style="font-size: 11px; color: ${info.iconColor};"></i>
        <span>${info.text}</span>
    </div>`;
}

// Función para abrir resumen de cierres
function abrirResumenCierres() {
    // Cerrar el modal actual
    Swal.close();
    
    // Abrir la página de resumen en nueva pestaña
    window.open('cierres_realizados.php', '_blank');
    
    // Opcional: mostrar mensaje de confirmación
    const temaActual = document.documentElement.getAttribute('data-theme') || 'dark';
    
    Swal.fire({
        title: 'Redirigiendo...',
        text: 'Abriendo resumen detallado de cierres',
        icon: 'info',
        timer: 1500,
        showConfirmButton: false,
        background: temaActual === 'dark' ? '#1e1e2d' : '#ffffff',
        color: temaActual === 'dark' ? '#ffffff' : '#212529',
        customClass: {
            popup: 'sweetalert-dark',
            title: 'sweetalert-title-dark',
            htmlContainer: 'sweetalert-content-dark'
        }
    });
}

// Función para formatear código Reeup automáticamente (formato: ###.#.####)
function formatReeup(input) {
    let value = input.value.replace(/[^\d]/g, ''); // Solo números
    
    if (value.length > 0) {
        // Formato: ###.#.#### (3.1.4 dígitos)
        let formatted = '';
        
        if (value.length <= 3) {
            formatted = value;
        } else if (value.length <= 4) {
            // Después de 3 dígitos, agregar punto
            formatted = value.substring(0, 3) + '.' + value.substring(3);
        } else {
            // Después de 4 dígitos, agregar segundo punto
            formatted = value.substring(0, 3) + '.' + 
                       value.substring(3, 4) + '.' + 
                       value.substring(4, 8); // Máximo 8 dígitos (3+1+4)
        }
        
        input.value = formatted;
    }
    
    // Validar formato
    validateReeup(input);
}

// Función para validar código Reeup
function validateReeup(input) {
    const value = input.value.trim();
    const reeupPattern = /^\d{3}\.\d{1}\.\d{4}$/;
    
    input.classList.remove('is-valid', 'is-invalid');
    
    if (value === '') {
        return;
    }
    
    if (reeupPattern.test(value)) {
        input.classList.add('is-valid');
    } else {
        input.classList.add('is-invalid');
    }
} 

// Función para probar el sitio web
function testWeb() {
    let url = document.getElementById('sitio_web').value;
    if (url) {
        // Asegurar que tenga el protocolo http/https
        if (!/^https?:\/\//i.test(url)) {
            url = 'http://' + url;
        }
        window.open(url, '_blank');
    } else {
        Swal.fire({
  title: '¡Atención!',
  text: 'Por favor, ingrese una dirección web primero.',
  icon: 'warning',
  background: '#1e1e1e', // Fondo oscuro
  color: '#ffffff',       // Color de texto blanco
  confirmButtonColor: '#3085d6',
  confirmButtonText: '<i class="fa fa-check"></i> Entendido', // Icono en el botón
  customClass: {
    popup: 'border-radius-15'
  },
  // Esto aplica el tema oscuro automáticamente si no usas un CSS externo
  backdrop: `
    rgba(0,0,0,0.6)
  `
});
    }
}
// Función para enviar correo
function sendMail() {
    const email = document.getElementById('email').value;
    if (email) {
        window.location.href = "mailto:" + email;
    } else {
		        Swal.fire({
  title: '¡Atención!',
  text: 'Por favor, ingrese una dirección de email válida.',
  icon: 'warning',
  background: '#1e1e1e', // Fondo oscuro
  color: '#ffffff',       // Color de texto blanco
  confirmButtonColor: '#3085d6',
  confirmButtonText: '<i class="fa fa-check"></i> Entendido', // Icono en el botón
  customClass: {
    popup: 'border-radius-15'
  },
  // Esto aplica el tema oscuro automáticamente si no usas un CSS externo
  backdrop: `
    rgba(0,0,0,0.6)
  `
});
	}
}

// Función para llamar
function makeCall() {
    // Obtenemos el valor y quitamos espacios vacíos
    const inputTel = document.getElementById('telefono');
    const tel = inputTel ? inputTel.value.trim() : "";

    if (tel !== "") {
        window.location.href = "tel:" + tel;
    } else {
        Swal.fire({
            title: '¡Atención!',
            text: 'Por favor, ingrese un No. de Teléfono Válido.',
            icon: 'warning',
            background: '#1e1e1e',
            color: '#ffffff',
            confirmButtonColor: '#3085d6',
            confirmButtonText: '<i class="fas fa-check-circle"></i> Entendido',
            customClass: {
                popup: 'my-custom-dark-alert'
            }
        });
    }
}
// Manejar clics en el botón X del desglose
document.addEventListener('click', function(event) {
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    // Si el clic fue en el botón de cerrar
    if (event.target.closest('.desglose-close-btn')) {
        event.preventDefault();
        event.stopPropagation();
        cerrarDesglose();
        return false;
    }
    
    // Si el clic fue en el overlay
    if (event.target === overlay && desgloseVisible) {
        cerrarDesglose();
        return false;
    }
    
    // Si el clic fue en el botón "Entendido" dentro del desglose
    if (desgloseVisible && event.target.closest('.btn-primary')) {
        const btn = event.target.closest('.btn-primary');
        if (btn && btn.textContent.includes('Entendido')) {
            event.preventDefault();
            event.stopPropagation();
            cerrarDesglose();
            return false;
        }
    }
});

// Prevenir que Enter envíe el formulario cuando el desglose está abierto
document.addEventListener('keydown', function(event) {
    if (desgloseVisible && event.key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();
        cerrarDesglose();
    }
});

// Función para confirmar y guardar configuración desde el botón final
function confirmarGuardarConfiguracion() {
	
// VALIDACIÓN DEL NIT (BLOQUEANTE)
    const nitInput = document.getElementById('cod_nit');
    if (nitInput.value.length !== 11) {
        Swal.fire({
            icon: 'error',
            title: 'Código NIT Incorrecto',
            text: 'El Número de Identificación Tributaria debe tener exactamente 11 dígitos para poder guardar la configuración.',
            confirmButtonColor: '#0078d4',
			confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            customClass: { popup: 'sweetalert-dark' }
        });
        nitInput.focus();
        nitInput.classList.add('is-invalid');
        return; // Detiene la función, no deja guardar
    }
    // Validar cuenta bancaria primero
    const cuentaInput = document.getElementById('cuentaBancaria');
    const cuenta = cuentaInput?.value.trim().replace(/\D/g, '') || '';
    
    if (cuenta && !validarCuentaBancariaCompleta(cuenta)) {
        Swal.fire({
            icon: 'error',
            title: 'Error en cuenta bancaria',
            html: `
                <div style="text-align: left; font-size: 14px;">
                    <p><strong>La cuenta bancaria no es válida.</strong></p>
                    <div class="alert alert-danger mt-3">
                        <strong>Error:</strong> ${getMensajeError(cuenta)}
                    </div>
                    <p class="mt-3">Por favor, corrija la cuenta bancaria antes de guardar.</p>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
            width: 550,
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            customClass: {
                popup: 'sweetalert-dark'
            }
        });
        
        if (cuentaInput) {
            cuentaInput.focus();
            cuentaInput.classList.add('is-invalid');
            cuentaInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    
    // Validar campos obligatorios
    const nombreEmpresa = document.querySelector('input[name="nombre_empresa"]');
    const nombreProyecto = document.querySelector('input[name="nombre_proyecto"]');
    
    if (!nombreEmpresa?.value.trim()) {
        Swal.fire({
            icon: 'error',
            title: 'Campo requerido',
            text: 'El nombre de la empresa es obligatorio',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            confirmButtonColor: '#0078d4',
            customClass: {
                popup: 'sweetalert-dark'
            }
        });
        nombreEmpresa?.focus();
        return;
    }
    
    if (!nombreProyecto?.value.trim()) {
        Swal.fire({
            icon: 'error',
            title: 'Campo requerido',
            text: 'El nombre del proyecto es obligatorio',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            confirmButtonColor: '#0078d4',
            customClass: {
                popup: 'sweetalert-dark'
            }
        });
        nombreProyecto?.focus();
        return;
    }
    
    // Validar WhatsApp si está activado
    const whatsappToggle = document.getElementById('whatsappToggle');
    const whatsappNumero = document.getElementById('whatsappNumero');
    
    if (whatsappToggle?.checked && whatsappNumero) {
        const numero = whatsappNumero.value.trim();
        if (!numero) {
            Swal.fire({
                icon: 'error',
                title: 'Campo requerido',
                text: 'El número de WhatsApp es requerido cuando está activado',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                confirmButtonColor: '#0078d4',
				confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
            whatsappNumero.focus();
            return;
        }
        
        const regex = /^\+53[5-8]\d{7}$/;
        if (!regex.test(numero)) {
            Swal.fire({
                icon: 'error',
                title: 'Formato de WhatsApp inválido',
                html: `
                    <div style="text-align: left; font-size: 14px;">
                        <p><strong>El número de WhatsApp no tiene el formato correcto.</strong></p>
                        <div class="alert alert-warning mt-3">
                            <strong>Formato requerido para Cuba:</strong><br>
                            <code>+53512345678</code>
                        </div>
                        <p>Número ingresado: <strong>${numero}</strong></p>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                width: 500,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
            whatsappNumero.focus();
            return;
        }
    }
    
// Validar código Reeup
    const codReeup = document.querySelector('input[name="cod_reeup"]');
    if (codReeup && codReeup.value.trim()) {
        const reeupPattern = /^\d{3}\.\d{1}\.\d{4}$/;
        if (!reeupPattern.test(codReeup.value.trim())) {
            Swal.fire({
                icon: 'error',
                title: 'Formato de Código Reeup inválido',
                html: `
                    <div style="text-align: left; font-size: 14px;">
                        <p><strong>El código Reeup no tiene el formato correcto.</strong></p>
                        <div class="alert alert-warning mt-3">
                            <strong>Formato requerido:</strong><br>
                            <code>123.1.1234</code> (###.#.####)
                        </div>
                        <p>Debe tener exactamente 3 dígitos, punto, 1 dígito, punto, 4 dígitos.</p>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                width: 500,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
            codReeup.focus();
            return;
        }
    }
function validarEstiloNit(input) {
    if (input.value.length === 11) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        input.classList.remove('is-valid');
    }
}
    // Mostrar diálogo de confirmación con tema oscuro
    Swal.fire({
        title: '¿Guardar configuración?',
        html: `
            <div style="text-align: left; font-size: 14px; color: var(--win-text-primary);">
                <p><strong>Se guardarán todos los cambios realizados en:</strong></p>
                <ul style="padding-left: 20px; margin-bottom: 15px;">
                    <li>Información de la empresa</li>
                    <li>Datos bancarios</li>
                    <li>Logos y diseño</li>
                    <li>Personal autorizado</li>
                    <li>Configuración de WhatsApp</li>
                </ul>
                <div class="alert alert-info mt-2" style="background: rgba(0, 120, 212, 0.1); border-color: rgba(0, 120, 212, 0.2); color: var(--win-text-primary); padding: 8px 12px; border-radius: 6px; font-size: 13px;">
                    <i class="fas fa-info-circle me-1"></i>
                    Esta acción registrará la operación en el histórico del sistema.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0078d4',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-save me-1"></i> Sí, guardar cambios',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        reverseButtons: true,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        width: 550,
        customClass: {
            popup: 'sweetalert-dark',
            title: 'sweetalert-title-dark',
            htmlContainer: 'sweetalert-content-dark',
            confirmButton: 'sweetalert-confirm-dark',
            cancelButton: 'sweetalert-cancel-dark'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar indicador de progreso
            Swal.fire({
                title: 'Guardando configuración...',
                html: `
                    <div style="text-align: center; margin: 20px 0;">
                        <div class="spinner-border" style="width: 3rem; height: 3rem; color: var(--win-accent);" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3" style="color: var(--win-text-primary);">Procesando los cambios...</p>
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                background: 'var(--win-bg-secondary)',
                customClass: {
                    popup: 'sweetalert-dark'
                }
            });
            
            // Enviar formulario después de breve delay
            setTimeout(() => {
                document.getElementById('configForm').submit();
            }, 1000);
        }
    });
}
// Función para alternar entre ir arriba o abajo
function toggleScrollPage() {
    // Si estamos cerca del principio (menos de 300px), bajar al final
    if (window.scrollY < 300) {
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    } else {
        // Si ya bajamos, subir al principio
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Escuchar el evento scroll para cambiar el texto e icono del botón automáticamente
window.addEventListener('scroll', function() {
    const btn = document.getElementById('btnScrollToggle');
    if (!btn) return;

    if (window.scrollY < 300) {
        // Estamos arriba, mostrar opción de bajar
        if (btn.innerText.includes('Arriba')) { // Solo actualizar si es necesario
            btn.innerHTML = '<i class="fas fa-arrow-down me-1"></i>Ir Abajo';
        }
    } else {
        // Estamos abajo, mostrar opción de subir
        if (btn.innerText.includes('Abajo')) { // Solo actualizar si es necesario
            btn.innerHTML = '<i class="fas fa-arrow-up me-1"></i>Ir Arriba';
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // 1. Crear el elemento tooltip y agregarlo al body
    const tooltip = document.createElement('div');
    tooltip.id = 'global-tooltip';
    document.body.appendChild(tooltip);
	
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

    // Variables para control
    const offset = 15; // Distancia del mouse

    // 2. Escuchar movimientos del mouse en todo el documento
    document.addEventListener('mouseover', function(e) {
        // Buscar si el elemento (o su padre) tiene title
        const target = e.target.closest('[title]');
        if (!target) return;

        // Obtener el texto y guardarlo en un atributo temporal
        const titleText = target.getAttribute('title');
        if (!titleText) return;

        target.setAttribute('data-original-title', titleText);
        target.removeAttribute('title'); // Quitar título nativo

        // Mostrar nuestro tooltip
        tooltip.textContent = titleText;
        tooltip.classList.add('visible');
    });

    document.addEventListener('mousemove', function(e) {
        // Mover el tooltip con el mouse
        if (tooltip.classList.contains('visible')) {
            // Evitar que se salga de la pantalla (derecha/abajo)
            let top = e.clientY + offset;
            let left = e.clientX + offset;
            
            // Ajustes simples de bordes
            if (left + tooltip.offsetWidth > window.innerWidth) {
                left = e.clientX - tooltip.offsetWidth - offset;
            }
            if (top + tooltip.offsetHeight > window.innerHeight) {
                top = e.clientY - tooltip.offsetHeight - offset;
            }

            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        }
    });

    document.addEventListener('mouseout', function(e) {
        // Buscar elementos que modificamos
        const target = e.target.closest('[data-original-title]');
        if (target) {
            // Restaurar el título original
            target.setAttribute('title', target.getAttribute('data-original-title'));
            target.removeAttribute('data-original-title');
            
            // Ocultar tooltip
            tooltip.classList.remove('visible');
        }
    });
});
// Función para Resetear y Volver al Dashboard
function volverAlDashboard() {
    Swal.fire({
        title: '¿Volver al Dashboard?',
        text: 'Se descartarán los cambios no guardados. ¿Desea continuar?',
        icon: 'warning',
        showCancelButton: true,
        showCloseButton: true,      // Muestra la X
        allowOutsideClick: false,   // Bloquea clic fuera
        confirmButtonColor: '#d33', // Rojo (acción de salir/perder datos)
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<i class="fas fa-undo mr-2"></i> Sí, regresar',
        cancelButtonText: '<i class="fas fa-times mr-2"></i> No, continuar',
        customClass: {
            popup: 'sweetalert-dark',
            title: 'sweetalert-title-dark',
            htmlContainer: 'sweetalert-content-dark',
            confirmButton: 'sweetalert-confirm-dark',
            cancelButton: 'sweetalert-cancel-dark',
            closeButton: 'sweetalert-close-dark'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // 1. Reseteamos el formulario como solicitaste
            document.getElementById('configForm').reset();
            
            // 2. Redirigimos al dashboard
            window.location.href = 'dashboard.php';
        }
    });
}
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
            background: 'var(--win-bg-secondary)', // Manteniendo tu estilo
            color: 'var(--win-text-primary)'
        });
        
        return false;
    }
});
</script>
<!-- JavaScript para detección en tiempo real -->
<script>
function detectarTipoREUP(codigo) {
    const badge = document.getElementById('badge-tipo-empresa');
    const analisisDetalle = document.getElementById('analisis-detalle');
    
    if (!badge) return;

    // 1. Validar formato con Expresión Regular (###.#.####)
    const regex = /^(\d{3})\.(\d)\.(\d{4})$/;
    const match = codigo.match(regex);
    
    if (!match) {
        badge.innerHTML = '<i class="fas fa-question-circle me-1"></i>Formato inválido';
        badge.className = 'badge bg-danger px-3 py-2';
        if(analisisDetalle) analisisDetalle.innerHTML = '<span class="text-danger">Formato requerido: 000.0.0000</span>';
        return;
    }
    
    const codOrg = match[1];
    const tipoDigito = parseInt(match[2]);
    const registro = match[3];

    // 2. LISTA MAESTRA DE ORGANISMOS (Idéntica a PHP)
    const organismosReales = {
        "101": "Asamblea Nacional del Poder Popular",
        "102": "Consejo de Estado",
        "103": "Consejo de Ministros",
        "104": "Tribunal Supremo Popular",
        "105": "Fiscalía General de la República",
        "106": "Contraloría General de la República",
        "111": "MINREX (Relaciones Exteriores)",
        "112": "MINFAR (Fuerzas Armadas)",
        "113": "MININT (Interior)",
        "114": "MINJUS (Justicia)",
        "115": "MINCIN (Comercio Interior)",
        "116": "MINCEX (Comercio Exterior)",
        "117": "MINEM (Energía y Minas)",
        "118": "MINSAP (Salud Pública)",
        "119": "MINED (Educación)",
        "120": "MES (Educación Superior)",
        "121": "MTSS (Trabajo y Seguridad Social)",
        "122": "MINAL (Industria Alimentaria)",
        "123": "MINAG (Agricultura)",
        "124": "MINCULT (Cultura)",
        "125": "INDER (Deportes)",
        "126": "MITRANS (Transporte)",
        "127": "MICONS (Construcción)",
        "128": "MINCOM (Comunicaciones)",
        "129": "MINTUR (Turismo)",
        "130": "MFP (Finanzas y Precios)",
        "131": "MEP (Economía y Planificación)",
        "132": "CITMA (Ciencia y Medio Ambiente)",
        "133": "MINDUS (Industrias)",
        "134": "BCC (Banco Central)",
        "135": "IICS (Información y Comunicación Social)",
        "136": "INRH (Recursos Hidráulicos)",
        "138": "Aduana General",
        "142": "OSDE (Organizaciones Superiores)",
        "201": "PCC (Partido Comunista de Cuba)",
        "202": "UJC (Unión de Jóvenes Comunistas)",
        "203": "CTC (Central de Trabajadores)",
        "204": "ANAP (Agricultores Pequeños)",
        "205": "FMC (Federación de Mujeres Cubanas)",
        "206": "CDR (Comités de Defensa de la Rev.)",
        "300": "Gobierno Pinar del Río",
        "301": "Gobierno La Habana",
        "302": "Gobierno Ciudad de La Habana",
        "303": "Gobierno Matanzas",
        "304": "Gobierno Villa Clara",
        "305": "Gobierno Cienfuegos",
        "306": "Gobierno Sancti Spíritus",
        "307": "Gobierno Ciego de Ávila",
        "308": "Gobierno Camagüey",
        "309": "Gobierno Las Tunas",
        "310": "Gobierno Holguín",
        "311": "Gobierno Granma",
        "312": "Gobierno Santiago de Cuba",
        "313": "Gobierno Guantánamo",
        "314": "Gobierno Isla de la Juventud",
        "315": "Gobierno Artemisa",
        "316": "Gobierno Mayabeque",
        "317": "Entidades Locales Construcción",
        "318": "Entidades Locales Transporte",
        "319": "Comercio y Gastronomía Local",
        "320": "Entidades Servicios Comunales"
    };

    // 3. LISTA MAESTRA DE NATURALEZAS (Dígito Central)
    const naturalezasReales = {
        0: { clase: 'bg-primary', text: 'Empresa Estatal', icon: 'fa-building' },
        1: { clase: 'bg-warning text-dark', text: 'CPA', icon: 'fa-tractor' },
        2: { clase: 'bg-dark', text: 'Empresa Mixta', icon: 'fa-handshake' },
        3: { clase: 'bg-secondary', text: 'Sociedad Mercantil', icon: 'fa-briefcase' },
        4: { clase: 'bg-warning text-dark', text: 'UBPC', icon: 'fa-users-cog' },
        5: { clase: 'bg-info text-dark', text: 'Unidad Presupuestada', icon: 'fa-university' },
        6: { clase: 'bg-success', text: 'CNA', icon: 'fa-store-alt' },
        7: { clase: 'bg-success', text: 'MIPYME / TCP', icon: 'fa-user-tie' },
        8: { clase: 'bg-light text-dark border', text: 'Asociación / ONG', icon: 'fa-church' },
        9: { clase: 'bg-danger', text: 'Otros / Extranjero', icon: 'fa-globe' }
    };

    const orgNombre = organismosReales[codOrg] || `Organismo ${codOrg}`;
    const nat = naturalezasReales[tipoDigito] || { clase: 'bg-light text-dark', text: 'No definido', icon: 'fa-question' };

    // 4. Actualizar el Badge visualmente
    badge.innerHTML = `<i class="fas ${nat.icon} me-1"></i>${nat.text}`;
    badge.className = `badge ${nat.clase} px-3 py-2`;

    // 5. Actualizar el texto descriptivo del Análisis (el SPAN)
    if (analisisDetalle) {
        analisisDetalle.innerHTML = `Subordinación: <strong>${orgNombre}</strong> | Gestión: <strong>${nat.text}</strong>`;
    }
}
function mostrarDetalleReeup() {
    const codigo = document.getElementById('cod_reeup').value;
    const regex = /^(\d{3})\.(\d)\.(\d{4})$/;
    const match = codigo.match(regex);

    if (!match) {
        Swal.fire({
            icon: 'error',
            title: 'Código Inválido',
            text: 'Por favor, ingrese un código REEUP válido (formato 000.0.0000) para ver el análisis.',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            confirmButtonColor: 'var(--win-accent)',
            customClass: { popup: 'sweetalert-dark' }
        });
        return;
    }

    const codOrg = match[1];
    const tipoDigito = parseInt(match[2]);
    const registro = match[3];

    // Listas idénticas a las anteriores para coherencia total
    const organismosReales = {
        "101": "Asamblea Nacional del Poder Popular", "102": "Consejo de Estado", "103": "Consejo de Ministros",
        "104": "Tribunal Supremo Popular", "105": "Fiscalía General de la República", "106": "Contraloría General de la República",
        "111": "MINREX (Relaciones Exteriores)", "112": "MINFAR (Fuerzas Armadas)", "113": "MININT (Interior)",
        "114": "MINJUS (Justicia)", "115": "MINCIN (Comercio Interior)", "116": "MINCEX (Comercio Exterior)",
        "117": "MINEM (Energía y Minas)", "118": "MINSAP (Salud Pública)", "119": "MINED (Educación)",
        "120": "MES (Educación Superior)", "121": "MTSS (Trabajo y Seguridad Social)", "122": "MINAL (Industria Alimentaria)",
        "123": "MINAG (Agricultura)", "124": "MINCULT (Cultura)", "125": "INDER (Deportes)", "126": "MITRANS (Transporte)",
        "127": "MICONS (Construcción)", "128": "MINCOM (Comunicaciones)", "129": "MINTUR (Turismo)", "130": "MFP (Finanzas y Precios)",
        "131": "MEP (Economía y Planificación)", "132": "CITMA (Ciencia y Medio Ambiente)", "133": "MINDUS (Industrias)",
        "134": "BCC (Banco Central)", "135": "IICS (Información y Comunicación Social)", "136": "INRH (Recursos Hidráulicos)",
        "138": "Aduana General", "142": "OSDE (Organizaciones Superiores)", "201": "PCC", "202": "UJC", "203": "CTC",
        "204": "ANAP", "205": "FMC", "206": "CDR", "300": "Gobierno Pinar del Río", "301": "Gobierno La Habana",
        "302": "Gobierno Ciudad de La Habana", "303": "Gobierno Matanzas", "304": "Gobierno Villa Clara",
        "305": "Gobierno Cienfuegos", "306": "Gobierno Sancti Spíritus", "307": "Gobierno Ciego de Ávila",
        "308": "Gobierno Camagüey", "309": "Gobierno Las Tunas", "310": "Gobierno Holguín", "311": "Gobierno Granma",
        "312": "Gobierno Santiago de Cuba", "313": "Gobierno Guantánamo", "314": "Gobierno Isla de la Juventud",
        "315": "Gobierno Artemisa", "316": "Gobierno Mayabeque", "317": "Entidades Locales Construcción",
        "318": "Entidades Locales Transporte", "319": "Comercio y Gastronomía Local", "320": "Entidades Servicios Comunales"
    };

    const naturalezasReales = {
        0: "Empresa Estatal", 1: "CPA (Cooperativa de Producción Agropecuaria)", 2: "Empresa Mixta",
        3: "Sociedad Mercantil (Capital 100% Cubano)", 4: "UBPC (Unidad Básica de Prod. Cooperativa)",
        5: "Unidad Presupuestada", 6: "CNA (Cooperativa No Agropecuaria)", 7: "MIPYME / TCP",
        8: "Asociación / ONG / Institución Religiosa", 9: "Sucursal Extranjera / Otros"
    };

    const nombreOrg = organismosReales[codOrg] || "Organismo no identificado";
    const nombreNat = naturalezasReales[tipoDigito] || "Naturaleza no identificada";

    Swal.fire({
        title: '<i class="fas fa-microchip me-2"></i>Análisis Técnico REEUP',
        html: `
            <div style="text-align: left; font-size: 14px; border-top: 1px solid var(--win-border-color); pt-3">
                <p class="mt-3"><strong>Código Completo:</strong> <span class="badge bg-dark">${codigo}</span></p>
                
                <div class="p-3 rounded mb-2" style="background: rgba(255,255,255,0.05); border-left: 4px solid var(--win-accent);">
                    <small class="text-muted d-block">Subordinación / Organismo:</small>
                    <span style="font-size: 16px; font-weight: 600;">${nombreOrg}</span>
                </div>

                <div class="p-3 rounded mb-2" style="background: rgba(255,255,255,0.05); border-left: 4px solid #28a745;">
                    <small class="text-muted d-block">Tipo de Gestión (Naturaleza):</small>
                    <span style="font-size: 16px; font-weight: 600;">${nombreNat}</span>
                </div>

                <div class="p-3 rounded" style="background: rgba(255,255,255,0.05); border-left: 4px solid #ffc107;">
                    <small class="text-muted d-block">Número de Registro Único:</small>
                    <span style="font-size: 16px; font-weight: 600;">${registro}</span>
                </div>
            </div>
        `,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
        confirmButtonColor: 'var(--win-accent)',
        customClass: {
            popup: 'sweetalert-dark',
            title: 'text-start'
        }
    });
}
const MAPA_NIT_CUBA = {
    '01': { nombre: 'Pinar del Río', municipios: { '00': 'Cabecera Pinar del Río', '01': 'Sandino', '02': 'Mantua', '03': 'Minas de Matahambre', '04': 'Viñales', '05': 'La Palma', '06': 'Los Palacios', '07': 'Consolación del Sur', '08': 'Pinar del Río', '09': 'San Luis', '10': 'San Juan y Martínez', '11': 'Guane' }},
    '02': { nombre: 'Artemisa', municipios: { '00': 'Cabecera Artemisa', '01': 'Bahía Honda', '02': 'Mariel', '03': 'Guanajay', '04': 'Caimito', '05': 'Bauta', '06': 'San Antonio de los Baños', '07': 'Güira de Melena', '08': 'Alquízar', '09': 'Artemisa', '10': 'Candelaria', '11': 'San Cristóbal' }},
    '03': { nombre: 'La Habana', municipios: { '00': 'Cabecera Provincial La Habana', '01': 'Playa', '02': 'Plaza de la Revolución', '03': 'Centro Habana', '04': 'Habana Vieja', '05': 'Regla', '06': 'La Habana del Este', '07': 'Guanabacoa', '08': 'San Miguel del Padrón', '09': 'Diez de Octubre', '10': 'Cerro', '11': 'Marianao', '12': 'La Lisa', '13': 'Boyeros', '14': 'Arroyo Naranjo', '15': 'Cotorro' }},
    '04': { nombre: 'Mayabeque', municipios: { '00': 'Cabecera San José de las Lajas', '01': 'Bejucal', '02': 'San José de las Lajas', '03': 'Jaruco', '04': 'Santa Cruz del Norte', '05': 'Madruga', '06': 'Nueva Paz', '07': 'San Nicolás', '08': 'Güines', '09': 'Melena del Sur', '10': 'Batabanó', '11': 'Quivicán' }},
    '05': { nombre: 'Matanzas', municipios: { '00': 'Cabecera Matanzas', '01': 'Matanzas', '02': 'Cárdenas', '03': 'Martí', '04': 'Colón', '05': 'Perico', '06': 'Jovellanos', '07': 'Pedro Betancourt', '08': 'Limonar', '09': 'Unión de Reyes', '10': 'Ciénaga de Zapata', '11': 'Jagüey Grande', '12': 'Calimete', '13': 'Los Arabos' }},
    '06': { nombre: 'Villa Clara', municipios: { '00': 'Cabecera Santa Clara', '01': 'Corralillo', '02': 'Quemado de Güines', '03': 'Sagua la Grande', '04': 'Encrucijada', '05': 'Camajuaní', '06': 'Caibarién', '07': 'Remedios', '08': 'Placetas', '09': 'Santa Clara', '10': 'Cifuentes', '11': 'Santo Domingo', '12': 'Ranchuelo', '13': 'Manicaragua' }},
    '07': { nombre: 'Cienfuegos', municipios: { '00': 'Cabecera Cienfuegos', '01': 'Aguada de Pasajeros', '02': 'Rodas', '03': 'Palmira', '04': 'Lajas', '05': 'Cruces', '06': 'Cumanayagua', '07': 'Cienfuegos', '08': 'Abreus' }},
    '08': { nombre: 'Sancti Spíritus', municipios: { '00': 'Cabecera Sancti Spíritus', '01': 'Yaguajay', '02': 'Jatibonico', '03': 'Taguasco', '04': 'Cabaiguán', '05': 'Fomento', '06': 'Trinidad', '07': 'La Sierpe', '08': 'Sancti Spíritus' }},
    '09': { nombre: 'Ciego de Ávila', municipios: { '00': 'Cabecera Ciego de Ávila', '01': 'Chambas', '02': 'Morón', '03': 'Bolivia', '04': 'Primero de Enero', '05': 'Ciro Redondo', '06': 'Florencia', '07': 'Majagua', '08': 'Ciego de Ávila', '09': 'Venezuela', '10': 'Baraguá' }},
    '10': { nombre: 'Las Tunas', municipios: { '00': 'Cabecera Victoria de las Tunas', '01': 'Manatí', '02': 'Puerto Padre', '03': 'Jesús Menéndez', '04': 'Majibacoa', '05': 'Las Tunas', '06': 'Jobabo', '07': 'Colombia', '08': 'Amancio Rodríguez' }},
    '11': { nombre: 'Holguín', municipios: { '00': 'Cabecera Holguín', '01': 'Gibara', '02': 'Rafael Freyre', '03': 'Banes', '04': 'Antilla', '05': 'Báguanos', '06': 'Holguín', '07': 'Calixto García', '08': 'Cacocum', '09': 'Urbano Noris', '10': 'Cueto', '11': 'Mayarí', '12': 'Frank País', '13': 'Sagua de Tánamo', '14': 'Moa' }},
    '12': { nombre: 'Granma', municipios: { '00': 'Cabecera Bayamo', '01': 'Río Cauto', '02': 'Cauto Cristo', '03': 'Jiguaní', '04': 'Bayamo', '05': 'Yara', '06': 'Manzanillo', '07': 'Campechuela', '08': 'Media Luna', '09': 'Niquero', '10': 'Pilón', '11': 'Bartolomé Masó', '12': 'Buey Arriba', '13': 'Guisa' }},
    '13': { nombre: 'Santiago de Cuba', municipios: { '00': 'Cabecera Santiago de Cuba', '01': 'Contramaestre', '02': 'Mella', '03': 'San Luis', '04': 'Segundo Frente', '05': 'Songo-La Maya', '06': 'Santiago de Cuba', '07': 'Palma Soriano', '08': 'Tercer Frente', '09': 'Guamá' }},
    '14': { nombre: 'Camagüey', municipios: { '00': 'Cabecera Provincial Camagüey', '01': 'Céspedes', '02': 'Esmeralda', '03': 'Sierra de Cubitas', '04': 'Minas', '05': 'Nuevitas', '06': 'Guáimaro', '07': 'Sibanicú', '08': 'Camagüey', '09': 'Florida', '10': 'Vertientes', '11': 'Jimaguayú', '12': 'Najasa', '13': 'Santa Cruz del Sur' }},
    '15': { nombre: 'Guantánamo', municipios: { '00': 'Cabecera Guantánamo', '01': 'El Salvador', '02': 'Manuel Tames', '03': 'Yateras', '04': 'Baracoa', '05': 'Maisí', '06': 'Imías', '07': 'San Antonio del Sur', '08': 'Caimanera', '09': 'Guantánamo', '10': 'Niceto Pérez' }},
    '16': { nombre: 'Isla de la Juventud', municipios: { '00': 'Cabecera Nueva Gerona', '01': 'Isla de la Juventud' }},
    '99': { nombre: 'Registro Nacional', municipios: { '00': 'ONAT Nacional' }}
};


// ============ MOSTRAR DETALLE NIT (SWEETALERT) ============
function mostrarDetalleNit() {
    const nit = document.getElementById('cod_nit').value;
    
    if (nit.length !== 11 || !/^\d+$/.test(nit)) {
        Swal.fire({ icon: 'error', title: 'NIT Inválido', text: 'El NIT debe tener 11 dígitos numéricos.', background: 'var(--win-bg-secondary)', color: 'var(--win-text-primary)' });
        return;
    }

    const codProv = nit.substring(0, 2);        
    const codMun = nit.substring(2, 4);        
    const consecutivo = nit.substring(4, 10);     
    const control = nit.substring(10, 11);        
    
    const provInfo = MAPA_NIT_CUBA[codProv] || { nombre: 'Provincia Desconocida', municipios: {} };
    const munNombre = provInfo.municipios[codMun] || `Municipio no identificado (Cód. ${codMun})`;

    Swal.fire({
        title: '<i class="fas fa-id-card me-2"></i>Análisis Técnico NIT',
        html: `
            <div style="text-align: left; font-size: 14px;">
                <div class="p-3 rounded mb-2" style="background: rgba(0, 120, 212, 0.1); border-left: 4px solid #0078d4;">
                    <small class="text-muted d-block">Provincia:</small>
                    <span style="font-size: 16px; font-weight: 600;">${provInfo.nombre} (Cód. ${codProv})</span>
                </div>
                <div class="p-3 rounded mb-2" style="background: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745;">
                    <small class="text-muted d-block">Municipio / Registro:</small>
                    <span style="font-size: 16px; font-weight: 600;">${munNombre}</span>
                </div>
                <div class="p-3 rounded mb-2" style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
                    <small class="text-muted d-block">Expediente:</small>
                    <span style="font-size: 16px; font-weight: 600;">${consecutivo}</span>
                </div>
                <div class="p-3 rounded" style="background: rgba(108, 117, 125, 0.1); border-left: 4px solid #6c757d;">
                    <small class="text-muted d-block">Verificación:</small>
                    <span style="font-size: 16px; font-weight: 600;">Dígito de control: ${control}</span>
                </div>
            </div>
        `,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        confirmButtonColor: 'var(--win-accent)',
		confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
        customClass: { popup: 'sweetalert-dark' }
    });
}

// ============ ANÁLISIS EN TIEMPO REAL (FRANJA LARGA) ============
function analizarNITJS(nit) {
    const badge = document.getElementById('badge-tipo-nit');
    const analisisDetalle = document.getElementById('analisis-nit-detalle');
    if (!badge || !analisisDetalle) return;

    if (nit.length === 11 && /^\d+$/.test(nit)) {
        const codProv = nit.substring(0, 2);
        const codMun = nit.substring(2, 4);
        const provInfo = MAPA_NIT_CUBA[codProv] || { nombre: 'Desconocida', municipios: {} };
        const munNombre = provInfo.municipios[codMun] || 'Mun. Desconocido';
        
        badge.className = 'badge bg-success px-3 py-2';
        badge.innerHTML = `<i class="fas fa-check-circle me-1"></i>Válido`;
        
        analisisDetalle.innerHTML = `
            Provincia: <strong>${provInfo.nombre}</strong> | 
            Registro: <strong>${munNombre}</strong> | 
            Expediente: <strong>${nit.substring(4, 10)}</strong> | 
            Control: <strong>${nit.substring(10)}</strong>
        `;
    } else {
        badge.className = 'badge bg-warning text-dark px-3 py-2';
        badge.innerHTML = `<i class="fas fa-clock me-1"></i>${nit.length}/11`;
        analisisDetalle.innerHTML = `<span class="text-muted">Esperando NIT completo (11 dígitos)...</span>`;
    }
}

</script>
<!-- ==================== SCRIPT PARA CONTROL DE CARDS CORREGIDO ==================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar todos los cards principales dentro del contenido principal
    const cards = document.querySelectorAll('.win-main-content .card');
    
    cards.forEach((card, index) => {
        // Buscar el card-header que contiene el título
        const header = card.querySelector('.card-header');
        if (!header) return;
        
        // Verificar si ya existe un botón toggle para no duplicar
        if (header.querySelector('.card-toggle-btn')) return;
        
        // Añadir clases de flex para alinear el contenido
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.cursor = 'pointer';
        
        // Crear el botón del chevron
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'card-toggle-btn';
        toggleBtn.style.background = 'none';
        toggleBtn.style.border = 'none';
        toggleBtn.style.color = 'var(--win-text-secondary)';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.padding = '8px 12px';
        toggleBtn.style.fontSize = '16px';
        toggleBtn.style.transition = 'transform 0.3s ease';
        toggleBtn.style.display = 'flex';
        toggleBtn.style.alignItems = 'center';
        toggleBtn.style.justifyContent = 'center';
        toggleBtn.style.borderRadius = '4px';
        toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
        
        // Agregar hover effect al botón
        toggleBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
        });
        toggleBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
        });
        
        // Añadir el botón al header
        header.appendChild(toggleBtn);
        
        // Obtener el card-body
        const body = card.querySelector('.card-body');
        if (body) {
            // Ocultar el body inicialmente
            body.style.display = 'none';
            
            // Guardar estado
            toggleBtn.setAttribute('data-state', 'closed');
            
            // Función para toggle (abrir/cerrar)
            function toggleCard(event) {
                // Prevenir propagación y comportamiento por defecto
                if (event) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                
                const isOpen = toggleBtn.getAttribute('data-state') === 'open';
                
                if (isOpen) {
                    // Cerrar
                    body.style.display = 'none';
                    toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                    toggleBtn.setAttribute('data-state', 'closed');
                } else {
                    // Abrir
                    body.style.display = 'block';
                    toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                    toggleBtn.setAttribute('data-state', 'open');
                }
                return false;
            }
            
            // Evento click en el header completo (incluye título y chevron)
            header.addEventListener('click', toggleCard);
            
            // Evento click específico para el botón (también funciona)
            toggleBtn.addEventListener('click', toggleCard);
        }
    });
});

// ==================== FUNCIÓN PARA EXPANDIR/COLAPSAR TODAS LAS CARDS ====================
let todasLasCardsExpandidas = false;

function toggleAllCards() {
    const cards = document.querySelectorAll('.win-main-content .card');
    const btnToolbar = document.getElementById('btnExpandirTodas');
    const btnFooter = document.getElementById('btnExpandirTodasFooter');
    
    if (todasLasCardsExpandidas) {
        // Colapsar todas las cards
        cards.forEach(card => {
            const header = card.querySelector('.card-header');
            const toggleBtn = header?.querySelector('.card-toggle-btn');
            const body = card.querySelector('.card-body');
            
            if (body && toggleBtn && toggleBtn.getAttribute('data-state') === 'open') {
                body.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                toggleBtn.setAttribute('data-state', 'closed');
            }
        });
        todasLasCardsExpandidas = false;
        
        // Actualizar ambos botones
        if (btnToolbar) {
            btnToolbar.innerHTML = '<i class="fas fa-expand-alt me-1"></i>Expandir Todas';
            btnToolbar.classList.remove('btn-outline-secondary');
            btnToolbar.classList.add('btn-outline-info');
        }
        if (btnFooter) {
            btnFooter.innerHTML = '<i class="fas fa-expand-alt me-1"></i>Expandir Todas';
            btnFooter.classList.remove('btn-outline-secondary');
            btnFooter.classList.add('btn-outline-info');
        }
    } else {
        // Expandir todas las cards
        cards.forEach(card => {
            const header = card.querySelector('.card-header');
            const toggleBtn = header?.querySelector('.card-toggle-btn');
            const body = card.querySelector('.card-body');
            
            if (body && toggleBtn && toggleBtn.getAttribute('data-state') === 'closed') {
                body.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                toggleBtn.setAttribute('data-state', 'open');
            }
        });
        todasLasCardsExpandidas = true;
        
        // Actualizar ambos botones
        if (btnToolbar) {
            btnToolbar.innerHTML = '<i class="fas fa-compress-alt me-1"></i>Colapsar Todas';
            btnToolbar.classList.remove('btn-outline-info');
            btnToolbar.classList.add('btn-outline-secondary');
        }
        if (btnFooter) {
            btnFooter.innerHTML = '<i class="fas fa-compress-alt me-1"></i>Colapsar Todas';
            btnFooter.classList.remove('btn-outline-info');
            btnFooter.classList.add('btn-outline-secondary');
        }
    }
}
</script>
			<div class="desglose-overlay" id="desgloseOverlay"></div>
			<div class="cuenta-desglose" id="desgloseCuenta" style="display: none;"></div>
</body>
</html>