<?php
// nuevo_cliente.php - Windows 11 Dark Mode - Full Version
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// ==================== FUNCIONES PARA ANÁLISIS NIT Y REEUP ====================

// Función para detectar tipo REUP
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

// Mapa NIT Cuba para análisis detallado
$MAPA_NIT_CUBA = [
    '01' => ['nombre' => 'Pinar del Río', 'municipios' => ['00' => 'Cabecera Pinar del Río', '01' => 'Sandino', '02' => 'Mantua', '03' => 'Minas de Matahambre', '04' => 'Viñales', '05' => 'La Palma', '06' => 'Los Palacios', '07' => 'Consolación del Sur', '08' => 'Pinar del Río', '09' => 'San Luis', '10' => 'San Juan y Martínez', '11' => 'Guane']],
    '02' => ['nombre' => 'Artemisa', 'municipios' => ['00' => 'Cabecera Artemisa', '01' => 'Bahía Honda', '02' => 'Mariel', '03' => 'Guanajay', '04' => 'Caimito', '05' => 'Bauta', '06' => 'San Antonio de los Baños', '07' => 'Güira de Melena', '08' => 'Alquízar', '09' => 'Artemisa', '10' => 'Candelaria', '11' => 'San Cristóbal']],
    '03' => ['nombre' => 'La Habana', 'municipios' => ['00' => 'Cabecera Provincial La Habana', '01' => 'Playa', '02' => 'Plaza de la Revolución', '03' => 'Centro Habana', '04' => 'Habana Vieja', '05' => 'Regla', '06' => 'La Habana del Este', '07' => 'Guanabacoa', '08' => 'San Miguel del Padrón', '09' => 'Diez de Octubre', '10' => 'Cerro', '11' => 'Marianao', '12' => 'La Lisa', '13' => 'Boyeros', '14' => 'Arroyo Naranjo', '15' => 'Cotorro']],
    '04' => ['nombre' => 'Mayabeque', 'municipios' => ['00' => 'Cabecera San José de las Lajas', '01' => 'Bejucal', '02' => 'San José de las Lajas', '03' => 'Jaruco', '04' => 'Santa Cruz del Norte', '05' => 'Madruga', '06' => 'Nueva Paz', '07' => 'San Nicolás', '08' => 'Güines', '09' => 'Melena del Sur', '10' => 'Batabanó', '11' => 'Quivicán']],
    '05' => ['nombre' => 'Matanzas', 'municipios' => ['00' => 'Cabecera Matanzas', '01' => 'Matanzas', '02' => 'Cárdenas', '03' => 'Martí', '04' => 'Colón', '05' => 'Perico', '06' => 'Jovellanos', '07' => 'Pedro Betancourt', '08' => 'Limonar', '09' => 'Unión de Reyes', '10' => 'Ciénaga de Zapata', '11' => 'Jagüey Grande', '12' => 'Calimete', '13' => 'Los Arabos']],
    '06' => ['nombre' => 'Villa Clara', 'municipios' => ['00' => 'Cabecera Santa Clara', '01' => 'Corralillo', '02' => 'Quemado de Güines', '03' => 'Sagua la Grande', '04' => 'Encrucijada', '05' => 'Camajuaní', '06' => 'Caibarién', '07' => 'Remedios', '08' => 'Placetas', '09' => 'Santa Clara', '10' => 'Cifuentes', '11' => 'Santo Domingo', '12' => 'Ranchuelo', '13' => 'Manicaragua']],
    '07' => ['nombre' => 'Cienfuegos', 'municipios' => ['00' => 'Cabecera Cienfuegos', '01' => 'Aguada de Pasajeros', '02' => 'Rodas', '03' => 'Palmira', '04' => 'Lajas', '05' => 'Cruces', '06' => 'Cumanayagua', '07' => 'Cienfuegos', '08' => 'Abreus']],
    '08' => ['nombre' => 'Sancti Spíritus', 'municipios' => ['00' => 'Cabecera Sancti Spíritus', '01' => 'Yaguajay', '02' => 'Jatibonico', '03' => 'Taguasco', '04' => 'Cabaiguán', '05' => 'Fomento', '06' => 'Trinidad', '07' => 'La Sierpe', '08' => 'Sancti Spíritus']],
    '09' => ['nombre' => 'Ciego de Ávila', 'municipios' => ['00' => 'Cabecera Ciego de Ávila', '01' => 'Chambas', '02' => 'Morón', '03' => 'Bolivia', '04' => 'Primero de Enero', '05' => 'Ciro Redondo', '06' => 'Florencia', '07' => 'Majagua', '08' => 'Ciego de Ávila', '09' => 'Venezuela', '10' => 'Baraguá']],
    '10' => ['nombre' => 'Las Tunas', 'municipios' => ['00' => 'Cabecera Victoria de las Tunas', '01' => 'Manatí', '02' => 'Puerto Padre', '03' => 'Jesús Menéndez', '04' => 'Majibacoa', '05' => 'Las Tunas', '06' => 'Jobabo', '07' => 'Colombia', '08' => 'Amancio Rodríguez']],
    '11' => ['nombre' => 'Holguín', 'municipios' => ['00' => 'Cabecera Holguín', '01' => 'Gibara', '02' => 'Rafael Freyre', '03' => 'Banes', '04' => 'Antilla', '05' => 'Báguanos', '06' => 'Holguín', '07' => 'Calixto García', '08' => 'Cacocum', '09' => 'Urbano Noris', '10' => 'Cueto', '11' => 'Mayarí', '12' => 'Frank País', '13' => 'Sagua de Tánamo', '14' => 'Moa']],
    '12' => ['nombre' => 'Granma', 'municipios' => ['00' => 'Cabecera Bayamo', '01' => 'Río Cauto', '02' => 'Cauto Cristo', '03' => 'Jiguaní', '04' => 'Bayamo', '05' => 'Yara', '06' => 'Manzanillo', '07' => 'Campechuela', '08' => 'Media Luna', '09' => 'Niquero', '10' => 'Pilón', '11' => 'Bartolomé Masó', '12' => 'Buey Arriba', '13' => 'Guisa']],
    '13' => ['nombre' => 'Santiago de Cuba', 'municipios' => ['00' => 'Cabecera Santiago de Cuba', '01' => 'Contramaestre', '02' => 'Mella', '03' => 'San Luis', '04' => 'Segundo Frente', '05' => 'Songo-La Maya', '06' => 'Santiago de Cuba', '07' => 'Palma Soriano', '08' => 'Tercer Frente', '09' => 'Guamá']],
    '14' => ['nombre' => 'Camagüey', 'municipios' => ['00' => 'Cabecera Provincial Camagüey', '01' => 'Céspedes', '02' => 'Esmeralda', '03' => 'Sierra de Cubitas', '04' => 'Minas', '05' => 'Nuevitas', '06' => 'Guáimaro', '07' => 'Sibanicú', '08' => 'Camagüey', '09' => 'Florida', '10' => 'Vertientes', '11' => 'Jimaguayú', '12' => 'Najasa', '13' => 'Santa Cruz del Sur']],
    '15' => ['nombre' => 'Guantánamo', 'municipios' => ['00' => 'Cabecera Guantánamo', '01' => 'El Salvador', '02' => 'Manuel Tames', '03' => 'Yateras', '04' => 'Baracoa', '05' => 'Maisí', '06' => 'Imías', '07' => 'San Antonio del Sur', '08' => 'Caimanera', '09' => 'Guantánamo', '10' => 'Niceto Pérez']],
    '16' => ['nombre' => 'Isla de la Juventud', 'municipios' => ['00' => 'Cabecera Nueva Gerona', '01' => 'Isla de la Juventud']],
    '99' => ['nombre' => 'Registro Nacional', 'municipios' => ['00' => 'ONAT Nacional']]
];

// ==================== FIN FUNCIONES ANÁLISIS ====================

// Inicializar variables
$error = '';
$success = '';

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
    
    // Determinar permisos según rol
    $esAdmin = ($usuario['rol_id'] == 1);
    $esEditor = ($usuario['rol_id'] == 3);
    $esSuper = ($usuario['rol_id'] == 4);
    $esSoloLectura = ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4);
    
    // Verificar permisos para crear clientes
    if (!$esAdmin && !$esEditor && !$esSuper) {
        $_SESSION['swal_no_privilegios'] = [
            'titulo' => 'Acceso Denegado',
            'mensaje' => 'Solo usuarios con rol de <strong>Administrador, Editor o Supervisor</strong> pueden crear nuevos Clientes.',
            'tipo_usuario' => $usuario['rol_nombre'] ?? 'Usuario',
            'icono' => 'error',
            'rol_id' => $usuario['rol_id'] ?? 0
        ];
        
        header('Location: clientes.php');
        exit();
    }
    
    $es_admin = true;
    
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
    
    try {
        $sql_total_historico = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total_historico);
        $estadisticas_historico = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_historico = $estadisticas_historico['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar histórico: " . $e->getMessage());
        $total_historico = 0;
    }
    
    // Generar código secuencial para nuevo cliente
    $sql_ultimo_codigo = "SELECT codigo FROM clasif_clientes WHERE codigo REGEXP '^[0-9]+$' ORDER BY CAST(codigo AS UNSIGNED) DESC LIMIT 1";
    $stmt_codigo = $db->query($sql_ultimo_codigo);
    $ultimo_codigo = $stmt_codigo->fetch(PDO::FETCH_ASSOC);
    
    if ($ultimo_codigo && is_numeric($ultimo_codigo['codigo'])) {
        $nuevo_codigo = str_pad((int)$ultimo_codigo['codigo'] + 1, 5, '0', STR_PAD_LEFT);
    } else {
        $nuevo_codigo = '00001';
    }
    
    // Generar contrato CVIS
    $sql_ultimo_contrato = "SELECT ContratoNo FROM clasif_clientes WHERE ContratoNo LIKE 'CVIS-%' ORDER BY LENGTH(ContratoNo), ContratoNo DESC LIMIT 1";
    $stmt_contrato = $db->query($sql_ultimo_contrato);
    $ultimo_contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
    
    if ($ultimo_contrato && preg_match('/CVIS-(\d+)/', $ultimo_contrato['ContratoNo'], $matches)) {
        $nuevo_numero_contrato = (int)$matches[1] + 1;
        $nuevo_contrato = 'CVIS-' . str_pad($nuevo_numero_contrato, 4, '0', STR_PAD_LEFT);
    } else {
        $nuevo_contrato = 'CVIS-0001';
    }
    
} catch (Exception $e) {
    error_log("Error al cargar datos: " . $e->getMessage());
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// RESUMEN DEL CLIENTE - Calcular fechas para el resumen
$fecha_inicio = date('Y-m-d');
$fecha_fin = '';
$fecha_final_total = '';
$estado_cliente = 'PENDIENTE';

if (isset($_POST['fechaRegistro']) && !empty($_POST['fechaRegistro'])) {
    $fecha_inicio = $_POST['fechaRegistro'];
}

if (isset($_POST['vigenciapor']) && is_numeric($_POST['vigenciapor']) && $_POST['vigenciapor'] > 0) {
    $vigencia = (int)$_POST['vigenciapor'];
    $fecha_fin_obj = new DateTime($fecha_inicio);
    $fecha_fin_obj->add(new DateInterval('P' . $vigencia . 'Y'));
    $fecha_fin = $fecha_fin_obj->format('Y-m-d');
    
    // Si hay renovación
    if (isset($_POST['renovac']) && isset($_POST['si_renova_cant']) && is_numeric($_POST['si_renova_cant']) && $_POST['si_renova_cant'] > 0) {
        $renovacion = (int)$_POST['si_renova_cant'];
        $fecha_final_total_obj = new DateTime($fecha_inicio);
        $fecha_final_total_obj->add(new DateInterval('P' . ($vigencia + $renovacion) . 'Y'));
        $fecha_final_total = $fecha_final_total_obj->format('Y-m-d');
    } else {
        $fecha_final_total = $fecha_fin;
    }
    
    // Calcular estado
    $hoy = new DateTime();
    $fecha_final = new DateTime($fecha_final_total);
    
    if ($fecha_final < $hoy) {
        $estado_cliente = 'VENCIDO';
    } elseif (isset($_POST['activo']) && $_POST['activo'] == 'on') {
        $estado_cliente = 'ACTIVO';
    } else {
        $estado_cliente = 'INACTIVO';
    }
}

// PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    try {
        // 1. Validar Vigencia (Entero > 0)
        if (!isset($_POST['vigenciapor']) || !is_numeric($_POST['vigenciapor']) || (int)$_POST['vigenciapor'] <= 0 || strpos($_POST['vigenciapor'], '.') !== false) {
            throw new Exception("La <strong>Vigencia (Años)</strong> debe ser un número entero mayor a 0.");
        }

        // 2. Validar Código REEUP (Formato específico)
        if (!empty($_POST['CodReup'])) {
            $codReup = trim($_POST['CodReup']);
            // Permitir solo números y formato xxx.x.xxxx
            if (!preg_match('/^\d{3}\.\d\.\d{4}$/', $codReup)) {
                throw new Exception("El formato del <strong>Código REEUP</strong> es inválido. Debe ser: 123.1.1234 (solo números con puntos)");
            }
        }

        // 3. Validar NIT (Solo números, longitud específica si se proporciona)
        if (!empty($_POST['NIT'])) {
            $nit = preg_replace('/\s+/', '', $_POST['NIT']);
            if (!ctype_digit($nit)) {
                throw new Exception("El <strong>NIT</strong> debe contener solo números.");
            }
            if (strlen($nit) != 11) {
                throw new Exception("El <strong>NIT</strong> debe tener 11 dígitos.");
            }
        }

        // 4. Validar Teléfono (Formato: + y 8 o más números)
        if (!empty($_POST['telefono'])) {
            $telefono = trim($_POST['telefono']);
            // Eliminar espacios y validar
            $telefono_limpio = preg_replace('/\s+/', '', $telefono);
            
            // Permitir formatos: +53512345678, +53 512345678, 512345678
            if (!preg_match('/^(\+\d{1,3})?\d{8,}$/', $telefono_limpio)) {
                throw new Exception("El <strong>Teléfono</strong> debe comenzar con + y tener al menos 8 números, o tener al menos 8 dígitos.");
            }
            
            // Si empieza con +, verificar que tenga al menos 8 números después del código
            if (strpos($telefono_limpio, '+') === 0) {
                $numeros = substr($telefono_limpio, strpos($telefono_limpio, '+') + 1);
                if (strlen($numeros) < 8) {
                    throw new Exception("El <strong>Teléfono</strong> debe tener al menos 8 números después del código de país.");
                }
            }
        }

        // 5. Validar Cuenta Deudor (Opcional, pero si hay dato: solo números y longitud)
        if (!empty($_POST['NoCtaDeudor'])) {
            $cta = preg_replace('/\s+/', '', $_POST['NoCtaDeudor']);
            if (!ctype_digit($cta)) {
                throw new Exception("La <strong>Cuenta del Cliente</strong> debe contener solo números.");
            }
            if (strlen($cta) < 14 || strlen($cta) > 16) {
                throw new Exception("La <strong>Cuenta del Cliente</strong> debe tener entre 14 y 16 dígitos.");
            }
        }

        // 6. Validar Sucursal (Opcional, pero si hay dato: solo números y longitud)
        if (!empty($_POST['SucursalCobroLocalidad'])) {
            $suc = preg_replace('/\s+/', '', $_POST['SucursalCobroLocalidad']);
            if (!ctype_digit($suc)) {
                throw new Exception("La <strong>Sucursal</strong> debe contener solo números.");
            }
            if (strlen($suc) < 4 || strlen($suc) > 8) {
                throw new Exception("La <strong>Sucursal</strong> debe tener entre 4 y 8 dígitos.");
            }
        }

        // 7. Validar Campos Obligatorios
        $required_fields = [
            'codigo' => 'Código Único', 
            'nombre' => 'Nombre Empresa',
            'ResponsableEntidad' => 'Responsable de la Entidad',
            'vigenciapor' => 'Vigencia (Años)'
        ];
        
        foreach ($required_fields as $field => $nombre_campo) {
            if (empty(trim($_POST[$field] ?? ''))) {
                throw new Exception("El campo <strong>$nombre_campo</strong> es obligatorio.");
            }
        }
        
        // 8. Validar Renovación
        if (isset($_POST['renovac'])) {
            if (empty($_POST['si_renova_cant']) || (int)$_POST['si_renova_cant'] <= 0) {
                throw new Exception("Si activa la Renovación, debe especificar la cantidad de años (mayor a 0).");
            }
        }
        
        // 9. Verificar código único duplicado
        if (!empty($_POST['codigo'])) {
            $sql_check_codigo = "SELECT COUNT(*) as count FROM clasif_clientes WHERE codigo = :codigo";
            $stmt_check = $db->prepare($sql_check_codigo);
            $stmt_check->execute(['codigo' => trim($_POST['codigo'])]);
            $existe_codigo = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($existe_codigo['count'] > 0) {
                throw new Exception("El código de Único Registro <span class='text-warning'><strong>" . htmlspecialchars($_POST['codigo']) . "</strong></span> ya existe.<br>Genere otro Código.");
            }
        }

        $sql_max_id = "SELECT MAX(id) as max_id FROM clasif_clientes";
        $stmt_max_id = $db->query($sql_max_id);
        $max_id_result = $stmt_max_id->fetch(PDO::FETCH_ASSOC);
        $nuevo_id = ($max_id_result['max_id'] ?? 0) + 1;

        $datos = [
            'id' => $nuevo_id,
            'codigo' => trim($_POST['codigo'] ?? $nuevo_codigo),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'CodReup' => trim($_POST['CodReup'] ?? ''),
            'NIT' => trim($_POST['NIT'] ?? ''),
            'ContratoNo' => $nuevo_contrato,
            'SucursalCobroLocalidad' => trim($_POST['SucursalCobroLocalidad']),
            'NoCtaDeudor' => trim($_POST['NoCtaDeudor']),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'fechaRegistro' => !empty($_POST['fechaRegistro']) ? $_POST['fechaRegistro'] : date('Y-m-d'),
            
            // Nuevos campos
            'vigenciapor' => (int)$_POST['vigenciapor'],
            'ResponsableEntidad' => trim($_POST['ResponsableEntidad'] ?? ''),
            'renovac' => isset($_POST['renovac']) ? 1 : 0,
            'si_renova_cant' => (int)($_POST['si_renova_cant'] ?? 0),
            'observaciones' => trim($_POST['observaciones'] ?? ''),
            
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];

        $db->beginTransaction();

        $sql_insert = "INSERT INTO clasif_clientes 
                      (id, codigo, nombre, direccion, CodReup, NIT, ContratoNo, SucursalCobroLocalidad, 
                       NoCtaDeudor, telefono, email, fechaRegistro, activo, 
                       vigenciapor, ResponsableEntidad, renovac, si_renova_cant, observaciones) 
                      VALUES 
                      (:id, :codigo, :nombre, :direccion, :CodReup, :NIT, :ContratoNo, :SucursalCobroLocalidad, 
                       :NoCtaDeudor, :telefono, :email, :fechaRegistro, :activo,
                       :vigenciapor, :ResponsableEntidad, :renovac, :si_renova_cant, :observaciones)";

        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->execute($datos);

        // Log
        $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                   VALUES (?, ?, ?, ?, ?)";
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            'CREAR_CLIENTE',
            'Cliente ' . $datos['nombre'] . ' creado (Código: ' . $datos['codigo'] . ', Contrato: ' . $datos['ContratoNo'] . ')',
            $_SESSION['usuario_id'],
            $_SESSION['usuario_nombre'],
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $db->commit();

       echo '<html><head><script src="js/sweetalert211.js"></script></head><body><script>
        Swal.fire({
            title: "Cliente Creado",
            html: "El cliente ha sido registrado correctamente.",
            icon: "success",
            background: "var(--win-bg-secondary)",
            color: "var(--win-text-primary)",
            showCancelButton: true,
            confirmButtonText: "<i class=\'fas fa-eye\'></i> Ver Cliente",
            cancelButtonText: "<i class=\'fas fa-user-plus\'></i> Nuevo Cliente"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "editar_cliente.php?id=' . $nuevo_id . '";
            } else {
                window.location.href = "nuevo_cliente.php";
            }
        });
        </script></body></html>';
        exit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Obtener mes actual en español
$meses_completos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_es = $meses_completos[date('n') - 1];
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">

    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
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

        /* Efecto Mica */
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

        /* Navbar */
        .win-navbar {
            height: 48px;
            background: var(--win-bg-secondary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 0 16px;
            position: fixed;
            top: 0; left: 0; right: 0; z-index: 1000;
            display: flex; align-items: center; gap: 12px;
        }

        .win-navbar-brand { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        .win-navbar-brand i { color: var(--win-accent); }

        .win-nav-search { flex: 1; max-width: 400px; position: relative; margin-bottom: 5px; padding: 0 5px; }
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
        .win-nav-search input:focus { outline: none; border-color: var(--win-accent); box-shadow: 0 0 0 2px var(--win-accent-light); }
        .win-nav-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--win-text-secondary); font-size: 14px; }

        /* Sidebar */
        .win-sidebar {
            width: 260px;
            background: var(--win-bg-secondary);
            border-right: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            position: fixed; left: 0; top: 48px; z-index: 999;
            transition: var(--win-transition);
            overflow-y: auto; padding: 16px 0;
        }
        .win-sidebar.mini { width: 68px; }
        .win-sidebar-header { padding: 0 16px 16px; border-bottom: 1px solid var(--win-border-color); margin-bottom: 16px; }
        .win-sidebar-user { display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: var(--win-radius-sm); transition: var(--win-transition); }
        .win-sidebar-user:hover { background: var(--win-bg-tertiary); }
        .win-sidebar-user-avatar { width: 36px; height: 36px; background: var(--win-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .win-sidebar-user-info h6 { margin: 0; font-size: 14px; font-weight: 500; }
        .win-sidebar-user-info small { color: var(--win-text-secondary); font-size: 12px; }
        .win-sidebar.mini .win-sidebar-user-info { display: none; }

        .win-nav { list-style: none; padding: 0; margin: 0; }
        .win-nav-item { margin: 2px 8px; }
        .win-nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--win-text-secondary); text-decoration: none; border-radius: var(--win-radius-sm); transition: var(--win-transition); font-size: 14px; position: relative; }
        .win-nav-link:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }
        .win-nav-link.active { background: var(--win-accent-light); color: var(--win-accent); font-weight: 500; }
        .win-nav-link.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--win-accent); border-radius: 0 2px 2px 0; }
        .win-nav-icon { width: 20px; text-align: center; font-size: 16px; }
        .win-sidebar.mini .win-nav-text { display: none; }
        .win-nav-badge { margin-left: auto; background: var(--win-accent); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; min-width: 20px; text-align: center; }

        /* Main Content */
        .win-main-content { margin-left: 260px; margin-top: 48px; padding: 24px; transition: var(--win-transition); min-height: calc(100vh - 48px); }
        .win-main-content.sidebar-mini { margin-left: 68px; }

        /* Cards & Forms */
        .win-main-content .card { background: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius); transition: var(--win-transition); margin-bottom: 1.5rem; }
        .win-main-content .card:hover { border-color: var(--win-accent); box-shadow: var(--win-shadow); }
        .win-main-content .card-header { background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color); padding: 1rem 1.25rem; }
        .win-main-content .card-body { padding: 1.25rem; color: var(--win-text-primary); }
        .win-main-content .form-control, .win-main-content .form-select { background-color: var(--win-bg-tertiary); border: 1px solid var(--win-border_color); color: var(--win-text-primary); border-radius: var(--win-radius-sm); transition: var(--win-transition); }
        .win-main-content .form-control:focus { background-color: var(--win-bg-tertiary); border-color: var(--win-accent); color: var(--win-text-primary); box-shadow: 0 0 0 0.25rem var(--win-accent-light); }
        .win-main-content .form-control[readonly] { background-color: rgba(0,0,0,0.2); border-color: var(--win-border_color); opacity: 0.8; cursor: not-allowed; font-weight: bold; }
        [data-theme="light"] .win-main-content .form-control[readonly] { background-color: #e9ecef; }
        .win-main-content .form-label { color: var(--win-text-primary); font-weight: 500; margin-bottom: 0.5rem; }

        /* Buttons */
        .win-main-content .btn { border-radius: var(--win-radius-sm); transition: var(--win-transition); font-weight: 500; padding: 0.5rem 1rem; }
        .win-main-content .btn-primary { background: var(--win-accent); border-color: var(--win-accent); }
        .win-main-content .btn-primary:hover { background: color-mix(in srgb, var(--win-accent) 90%, black); transform: translateY(-1px); }
        .win-main-content .btn-outline-secondary { color: var(--win-text-secondary); border-color: var(--win-border_color); }
        .win-main-content .btn-outline-secondary:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }

        /* Theme Panel */
        .win-theme-panel { position: fixed; top: 48px; right: 0; width: 300px; background: var(--win-bg-secondary); border-left: 1px solid var(--win-border_color); height: calc(100vh - 48px); z-index: 1001; transform: translateX(100%); transition: var(--win-transition); padding: 24px; overflow-y: auto; }
        .win-theme-panel.open { transform: translateX(0); }
        .win-theme-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: none; }
        .win-theme-overlay.open { display: block; }
        .win-theme-option { padding: 16px; border: 2px solid var(--win-border_color); border-radius: var(--win-radius); cursor: pointer; transition: var(--win-transition); text-align: center; color: var(--win-text-primary); }
        .win-theme-option:hover { border-color: var(--win-accent); }
        .win-theme-option.active { border-color: var(--win-accent); background: var(--win-accent-light); }
        .win-color-option { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: var(--win-transition); }
        .win-color-option.active { border-color: white; box-shadow: 0 0 0 2px var(--win-bg-secondary); }

        /* Validations & Helpers */
        .required::after { content: " *"; color: #dc3545; }
        .is-valid { border-color: #198754 !important; }
        .is-invalid { border-color: #dc3545 !important; }
        .invalid-feedback { display: none; color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
        .was-validated .form-control:invalid ~ .invalid-feedback { display: block; }
        .field-help { font-size: 0.75rem; color: var(--win-text-secondary); margin-top: 0.25rem; display: block; }
        
        /* Dropdown & Modal */
        .dropdown-menu { background-color: var(--win-bg-secondary); border: 1px solid var(--win-border_color); border-radius: var(--win-radius); }
        .dropdown-item { color: var(--win-text-primary); transition: var(--win-transition); border-radius: var(--win-radius-sm); margin: 2px 4px; }
        .dropdown-item:hover { background-color: var(--win-accent-light); color: var(--win-accent); }
        
        /* Resumen del Cliente */
        .resumen-cliente {
            background: linear-gradient(135deg, var(--win-bg-secondary), var(--win-bg-tertiary));
            border: 1px solid var(--win-border_color);
            border-radius: var(--win-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--win-shadow);
        }
        
        .resumen-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--win-text-primary);
            border-bottom: 2px solid var(--win-accent);
            padding-bottom: 0.5rem;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .resumen-item {
            display: flex;
            flex-direction: column;
        }
        
        .resumen-label {
            font-size: 0.875rem;
            color: var(--win-text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .resumen-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--win-text-primary);
        }
        
        .estado-activo {
            color: #28a745 !important;
        }
        
        .estado-inactivo {
            color: #dc3545 !important;
        }
        
        .estado-vencido {
            color: #ffc107 !important;
        }
        
        /* Contadores */
        .char-counter {
            font-size: 0.75rem;
            text-align: right;
            margin-top: 0.25rem;
            color: var(--win-text-secondary);
        }
        
        .char-counter.warning {
            color: #ffc107;
        }
        
        .char-counter.danger {
            color: #dc3545;
        }
        
        /* Formato REEUP */
        .reup-input-group {
            position: relative;
        }
        
        .reup-input-group .form-control {
            padding-right: 50px;
        }
        
        .reup-format {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.875rem;
            color: var(--win-text-secondary);
        }
        
        /* Quick Action Button */
        .win-quick-action {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: var(--win-accent);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: var(--win-transition);
            z-index: 99;
        }
        
        .win-quick-action:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }
        
        .win-quick-action:active {
            transform: scale(0.95);
        }
        
        .alerta-vencido {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            display: none;
            animation: pulseRed 2s infinite;
            font-weight: bold;
        }
        
        @keyframes pulseRed { 
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.2); } 
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } 
        }
        
        /* Indicador de longitud para cuenta */
        #cuenta-length.text-success {
            color: #198754 !important;
        }
        
        #cuenta-length.text-warning {
            color: #ffc107 !important;
        }
        
        #cuenta-length.text-danger {
            color: #dc3545 !important;
        }
        
        #cuenta-length.text-muted {
            color: #6c757d !important;
        }
        
        /* Estados de validación */
        #cuenta-status, #cuenta-validation {
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .text-muted { color: #e0e0e0 !important; opacity: 0.9; }
        [data-theme="light"] .text-muted { color: #555555 !important; opacity: 1; }
 /* Badges para NIT y REEUP */
        .badge-tipo-nit, .badge-tipo-reeup {
            font-size: 11px !important;
            padding: 3px 8px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }

        .badge-tipo-nit:hover, .badge-tipo-reeup:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Estados de validación para NIT y REEUP */
        #nitInput.is-valid, #reupInput.is-valid {
            border-color: #28a745 !important;
            background-color: rgba(40, 167, 69, 0.05) !important;
        }

        #nitInput.is-invalid, #reupInput.is-invalid {
            border-color: #dc3545 !important;
            background-color: rgba(220, 53, 69, 0.05) !important;
        }

        /* Badge colors para tipos */
        .bg-nit-persona-natural { background: linear-gradient(135deg, #17a2b8, #138496) !important; color: white !important; }
        .bg-nit-persona-juridica { background: linear-gradient(135deg, #007bff, #0056b3) !important; color: white !important; }
        .bg-nit-extranjero { background: linear-gradient(135deg, #ffc107, #e0a800) !important; color: #212529 !important; }
        .bg-nit-agrupacion { background: linear-gradient(135deg, #6c757d, #545b62) !important; color: white !important; }
        .bg-nit-otros { background: linear-gradient(135deg, #f8f9fa, #e2e6ea) !important; color: #212529 !important; border: 1px solid #dee2e6 !important; }

        /* Badge colors para REEUP */
        .bg-reeup-estatal { background: linear-gradient(135deg, #007bff, #0056b3) !important; color: white !important; }
        .bg-reeup-cpa { background: linear-gradient(135deg, #ffc107, #e0a800) !important; color: #212529 !important; }
        .bg-reeup-mixta { background: linear-gradient(135deg, #343a40, #23272b) !important; color: white !important; }
        .bg-reeup-sociedad { background: linear-gradient(135deg, #6c757d, #545b62) !important; color: white !important; }
        .bg-reeup-ubpc { background: linear-gradient(135deg, #fd7e14, #e9690c) !important; color: white !important; }
        .bg-reeup-presupuestada { background: linear-gradient(135deg, #17a2b8, #138496) !important; color: white !important; }
        .bg-reeup-cna { background: linear-gradient(135deg, #28a745, #1e7e34) !important; color: white !important; }
        .bg-reeup-mipyme { background: linear-gradient(135deg, #20c997, #17a589) !important; color: white !important; }
        .bg-reeup-asociacion { background: linear-gradient(135deg, #f8f9fa, #e2e6ea) !important; color: #212529 !important; border: 1px solid #dee2e6 !important; }
        .bg-reeup-otros { background: linear-gradient(135deg, #dc3545, #c82333) !important; color: white !important; }
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

    <!-- Navbar -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="win-navbar-brand"><i class="fas fa-user-plus"></i><span style="color: var(--win-text-primary);">NUEVO CLIENTE - SISFACT PDL Visiones</span></div>
        <div class="win-nav-search d-none d-md-block"><i class="fas fa-search"></i><input type="text" placeholder="Buscar en el sistema..."></div>
        <div style="flex: 1;"></div>
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar"><i class="fas fa-palette"></i></button>
		
<?= renderNotificationsDropdown() ?>

        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?><img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); endif; ?>
                </div>
                <span class="d-none d-md-inline" style="color: var(--win-text-primary);"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
                <i class="fas fa-chevron-down ms-1 small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px;">
                <li class="dropdown-header px-3 py-2">
                    <div class="d-flex align-items-center">
                        <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;"><?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?></div>
                        <div><h6 class="mb-0" style="color: var(--win-text-primary); font-size: 14px;"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></h6><small class="text-muted" style="font-size: 12px;"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></small></div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item d-flex align-items-center py-2" href="dashboard.php"><i class="fas fa-tachometer-alt me-3 text-primary" style="width: 20px;"></i><div><span class="d-block" style="color: var(--win-text-primary);">Dashboard</span><small class="text-muted d-block" style="font-size: 12px;">Panel principal</small></div></a></li>
                <li><a class="dropdown-item d-flex align-items-center py-2" href="perfil.php"><i class="fas fa-user me-3 text-primary" style="width: 20px;"></i><div><span class="d-block" style="color: var(--win-text-primary);">Mi Perfil</span><small class="text-muted d-block" style="font-size: 12px;">Ver y editar tu información</small></div></a></li>
                <li><a class="dropdown-item d-flex align-items-center py-2" href="configuracion.php"><i class="fas fa-cog me-3 text-secondary" style="width: 20px;"></i><div><span class="d-block" style="color: var(--win-text-primary);">Configuración</span><small class="text-muted d-block" style="font-size: 12px;">Preferencias del sistema</small></div></a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item d-flex align-items-center py-2 text-warning" href="bloquear_sesion.php"><i class="fas fa-lock me-3" style="width: 20px;"></i><div><span class="d-block fw-bold">Bloquear Sesión</span><small class="text-muted d-block" style="font-size: 12px;">Bloquear pantalla temporalmente</small></div></a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i><div><span class="d-block fw-bold">Cerrar Sesión</span><small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small></div></a></li>
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

    <!-- Sidebar -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
        <a href="perfil.php" style="text-decoration: none; display: block;">
            <div class="win-sidebar-header">
                <div class="win-sidebar-user">
                    <div class="win-sidebar-user-avatar">
                        <?php if (!empty($usuario['foto'])): ?><img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); endif; ?>
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
                    <i class="win-nav-icon fas fa-tachometer-alt"></i> Dashboard
                    <span class="win-nav-badge" title="Fecha de Cierre Actual: <?php echo date('t') . ' de ' . $mes_actual_es; ?>" style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">F/Cierre: <?php echo date('t'); ?> / <?php echo substr($mes_actual_es, 0, 3); ?></span>
                </a>
                <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            </li>
            <li class="win-nav-item"><a href="facturas.php" class="win-nav-link"><i class="win-nav-icon fas fa-file-invoice"></i><span class="win-nav-text">Facturas</span><span class="win-nav-badge"><?php echo $total_facturas; ?></span></a></li>
            <li class="win-nav-item"><a href="clientes.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Clientes</span><span class="win-nav-badge"><?php echo $total_clientes; ?></span></a></li>
            <li class="win-nav-item"><a href="nuevo_cliente.php" class="win-nav-link active"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Nuevo Cliente</span><?php if ($es_admin): ?><span class="win-nav-badge admin-badge" style="background: linear-gradient(135deg, #dc3545, #c82333);"><i class="fas fa-crown"></i></span><?php endif; ?></a></li>
            <li class="win-nav-item"><a href="categorias.php" class="win-nav-link"><i class="win-nav-icon fas fa-tags"></i><span class="win-nav-text">Categorías</span><span class="win-nav-badge"><?php echo $total_categorias; ?></span></a></li>
            <li class="win-nav-item"><a href="servicios.php" class="win-nav-link"><i class="win-nav-icon fas fa-list"></i><span class="win-nav-text">Servicios</span><span class="win-nav-badge"><?php echo $total_servicios; ?></span></a></li>
            <li class="win-nav-item"><a href="usuarios.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Usuarios</span><span class="win-nav-badge"><?php echo $total_usuarios; ?></span></a></li>
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
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
                    <span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>
            <li class="win-nav-item"><a href="configuracion.php" class="win-nav-link"><i class="win-nav-icon fas fa-cog"></i><span class="win-nav-text">Configuración</span></a></li>
            <li class="win-nav-item"><a href="historico_view.php" class="win-nav-link"><i class="win-nav-icon fas fa-history"></i><span class="win-nav-text">Histórico</span><span class="win-nav-badge"><?php echo $total_historico; ?></span></a></li>
        </ul>
        
        <?php $finanzas = Database::getProgresoFinanciero(); ?>
        <div class="mt-4 px-3">
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div><small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small><small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;"><?php echo $finanzas['mensaje']; ?></small></div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><?php echo number_format($finanzas['porcentaje'], 1); ?>%</h5>
            </div>
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" role="progressbar" style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>; transition: width 1s ease-in-out;"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column"><small class="text-muted" style="font-size: 12px;"><strong>$<?php echo number_format($finanzas['real'], 2); ?></strong></small><small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;"><i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas</small></div>
                <small class="text-end text-success" style="font-size: 12px;">Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?></small>
            </div>
        </div>
    </aside>

    <!-- Contenido -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 mb-0" style="color: var(--win-text-primary);"><i class="fas fa-user-plus me-2" style="color: var(--win-accent);"></i>Nuevo Cliente</h1>
				<p class="text-muted mb-0">
					Complete los datos para registrar un nuevo cliente 
					<span class="badge bg-secondary me-1">Fecha Operaciones:</span>-
					<span class="badge bg-success"><?php echo $mes_actual_es . '/' . date('Y'); ?></span>
				</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="clientes.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver a Clientes</a>
            </div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: <?php echo json_encode($error); ?>,
                    background: '#1f1f1f',
                    color: '#fff',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    confirmButtonColor: '#3085d6'
                });
            });
            </script>
        <?php endif; ?>

        <!-- RESUMEN DEL CLIENTE EN TIEMPO REAL -->
        <div class="resumen-cliente animate__animated animate__fadeIn">
            <div class="resumen-title">
                <i class="fas fa-file-contract me-2" style="color: var(--win-accent);"></i>Resumen del Nuevo Cliente
                <small class="float-end text-muted" style="font-size: 0.75rem;">Los campos marcados con <span class="text-danger">*</span> son obligatorios</small>
            </div>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <span class="resumen-label">Estado:</span>
                    <span class="resumen-value <?php 
                        if ($estado_cliente == 'ACTIVO') echo 'estado-activo';
                        elseif ($estado_cliente == 'VENCIDO') echo 'estado-vencido';
                        else echo 'estado-inactivo';
                    ?>" id="resumenEstado"><?php echo $estado_cliente; ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Código:</span>
                    <span class="resumen-value" id="resumenCodigo"><?php echo htmlspecialchars($nuevo_codigo ?? ''); ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Contrato:</span>
                    <span class="resumen-value" id="resumenContrato"><?php echo htmlspecialchars($nuevo_contrato); ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Fecha Inicio:</span>
                    <span class="resumen-value" id="resumenFechaInicio"><?php echo !empty($fecha_inicio) ? date('d/m/Y', strtotime($fecha_inicio)) : date('d/m/Y'); ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Fecha Fin:</span>
                    <span class="resumen-value" id="resumenFechaFin"><?php echo !empty($fecha_final_total) ? date('d/m/Y', strtotime($fecha_final_total)) : 'No definida'; ?></span>
                </div>
            </div>
        </div>

        <form method="POST" action="" id="clienteForm" class="animate__animated animate__fadeIn" novalidate>
            <input type="hidden" name="crear" value="1">
            <input type="hidden" name="ContratoNo" id="contratoHidden" value="<?php echo htmlspecialchars($nuevo_contrato); ?>">
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Información Básica -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-info-circle me-2" style="color: var(--win-accent);"></i>Información Básica del Nuevo Cliente</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label required">Código Único</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="codigo" value="<?php echo htmlspecialchars($nuevo_codigo ?? ''); ?>" id="codigoInput" required onblur="validarCodigoUnico()" oninput="actualizarResumen('codigo', this.value)">
                                        <button type="button" class="btn btn-outline-secondary" onclick="generarNuevoCodigo()"><i class="fas fa-redo"></i></button>
                                    </div>
                                    <span class="field-help">Código único del cliente - Últimos 5 dígitos de la cuenta bancaria</span>
                                    <div class="invalid-feedback">Este código ya está en uso</div>
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label class="form-label required">Nombre Empresa, PDL IMDL, MIPYMES, etc.</label>
                                    <input type="text" class="form-control" name="nombre" id="nombre" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                                    <span class="field-help">Nombre completo de la empresa o cliente</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label required">Responsable de la Entidad</label>
                                    <input type="text" class="form-control" name="ResponsableEntidad" id="ResponsableEntidad" required value="<?php echo htmlspecialchars($_POST['ResponsableEntidad'] ?? ''); ?>">
                                    <span class="field-help">Nombre de la persona Responsable de la Entidad o Autorizada a Firmar Contratos</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label">Dirección</label>
                                    <textarea class="form-control" name="direccion" rows="2"><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
                                    <span class="field-help">Dirección física completa</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Código REEUP</label>
                                    <div class="reup-input-group">
                                        <input type="text" class="form-control" name="CodReup" id="reupInput" 
                                               placeholder="xxx.x.xxxx" 
                                               value="<?php echo htmlspecialchars($_POST['CodReup'] ?? ''); ?>" 
                                               maxlength="10" 
                                               oninput="formatearREEUP(this);">
                                        <span class="reup-format">xxx.x.xxxx</span>
                                    </div>
                                    <span class="field-help">Formato: xxx.x.xxxx (solo números)</span>
                                    <div class="mt-2">
                                        <span id="badgeTipoReeup" class="badge bg-light text-dark border px-2 py-1 badge-tipo-reeup" 
                                              onclick="mostrarDetalleReeup()">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <span id="textoTipoReeup">Sin datos</span>
                                        </span>
                                        <small id="analisisReeup" class="text-muted d-block mt-1" style="font-size: 11px;">
                                            Espere datos del REEUP...
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">NIT: Identificación Tributaria</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="NIT" id="nitInput" 
                                               maxlength="11" placeholder="11 dígitos" 
                                               value="<?php echo htmlspecialchars($_POST['NIT'] ?? ''); ?>" 
                                               oninput="validarNIT(this);">
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="mostrarDetalleNit()" title="Ver análisis detallado">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <span id="badgeTipoNit" class="badge bg-light text-dark border px-2 py-1 badge-tipo-nit" 
                                              onclick="mostrarDetalleNit()">
                                            <i class="fas fa-id-card me-1"></i>
                                            <span id="textoTipoNit">Sin NIT</span>
                                        </span>
                                        <small id="analisisNit" class="text-muted d-block mt-1" style="font-size: 11px;">
                                            Ingrese 11 dígitos para análisis...
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datos Bancarios -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-university me-2" style="color: var(--win-accent);"></i>DATOS BANCARIOS</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required">Número de Contrato</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($nuevo_contrato); ?>" disabled id="contratoDisplay">
                                    <span class="field-help">Número de contrato CVIS-#### (último +1, no editable)</span>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Sucursal Bancaria</label>
                                    <input type="text" class="form-control" name="SucursalCobroLocalidad" id="sucursalInput" maxlength="8" value="<?php echo htmlspecialchars($_POST['SucursalCobroLocalidad'] ?? '5781'); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)">
                                    <span class="field-help">Número de 4 a 8 dígitos</span>
                                </div>
                                <div class="col-5 mb-3">
                                    <label class="form-label">Cuenta Bancaria</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="NoCtaDeudor" id="cuentaInput" maxlength="16" value="<?php echo htmlspecialchars($_POST['NoCtaDeudor'] ?? ''); ?>" oninput="validarCuentaBancaria()">
                                        <button type="button" class="btn btn-outline-secondary" onclick="generarCodigoDesdeCuenta()"><i class="fas fa-magic"></i></button>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Formato: <strong>14-16 dígitos numéricos</strong> | 
                                        Longitud: <span id="cuenta-length"><?php echo strlen($_POST['NoCtaDeudor'] ?? ''); ?></span> dígitos |
                                        <span id="cuenta-status" class="ms-2"></span>
                                    </small>
                                    <div id="cuenta-validation" class="invalid-feedback"></div>
                                    <span class="field-help">14 a 16 dígitos. El código del contrato puede generarse automáticamente con los últimos 5 dígitos</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Contacto -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-address-card me-2" style="color: var(--win-accent);"></i>Información de Contacto</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" id="telefonoInput" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>" oninput="validarTelefono(this)">
                                <span class="field-help">Formatos: +53 5 2712861, +53 32415418, 52712861, 0 32415418 (mínimo 8 dígitos)</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="emailInput" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <span class="field-help">Correo electrónico de contacto</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estado del Cliente</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="activo" id="flexSwitchCheckChecked" checked onchange="actualizarEstado()">
                                    <label class="form-check-label fw-bold" for="flexSwitchCheckChecked" id="labelEstado">ACTIVO</label>
                                </div>
                            </div>
                            <div id="alertVencido" class="alerta-vencido"><i class="fas fa-exclamation-triangle"></i> ESE CONTRATO ESTARÁ VENCIDO</div>
                        </div>
                    </div>

                    <!-- Fechas Contractuales -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-calendar-check me-2" style="color: var(--win-accent);"></i>FECHAS CONTRACTUALES</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Fecha de Registro</label>
                                <input type="date" class="form-control" name="fechaRegistro" id="fechaRegistro" value="<?php echo !empty($_POST['fechaRegistro']) ? $_POST['fechaRegistro'] : date('Y-m-d'); ?>" onchange="calcularFechas(); actualizarResumen('fechaInicio', this.value)">
                                <span class="field-help">Fecha de registro del cliente</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Vigencia (Años)</label>
                                <input type="number" class="form-control" name="vigenciapor" id="vigenciapor" value="<?php echo htmlspecialchars($_POST['vigenciapor'] ?? '0'); ?>" min="1" step="1" required oninput="this.value=this.value.replace(/[^0-9]/g,''); calcularFechas()">
                                <span class="field-help">Vigencia en años enteros</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-warning"><i class="fas fa-lock me-1"></i>Fecha Vencimiento (Calc)</label>
                                <input type="date" class="form-control" id="fechaVenceDisplay" readonly>
                                <span class="field-help">Fecha de vencimiento del contrato</span>
                            </div>
                            <hr class="border-secondary my-3">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="renovac" id="renovacSwitch" onchange="toggleRenovacion()" <?php echo isset($_POST['renovac']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="renovacSwitch">¿Renovación de Contrato?</label>
                                </div>
                            </div>
                            <div id="divRenovacion" class="animate__animated animate__fadeIn" style="display: none;">
                                <div class="mb-3 p-3 rounded" style="background: var(--win-bg-tertiary); border: 1px solid var(--win-border_color);">
                                    <label class="form-label required">Cantidad Años Renovación</label>
                                    <input type="number" class="form-control mb-2" name="si_renova_cant" id="si_renova_cant" value="<?php echo htmlspecialchars($_POST['si_renova_cant'] ?? '0'); ?>" min="1" step="1" oninput="this.value=this.value.replace(/[^0-9]/g,''); calcularFechas()">
                                    <span class="field-help">Años adicionales de renovación</span>
                                    <label class="form-label text-success mt-2"><i class="fas fa-lock me-1"></i>Fecha Final Total</label>
                                    <input type="date" class="form-control" id="fechafinalcontratoDisplay" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between pt-3 border-top mb-5">
                <a href="clientes.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Cancelar</a>
                <div>
                    <button type="button" class="btn btn-outline-primary me-2" onclick="limpiarFormulario()"><i class="fas fa-broom me-1"></i>Limpiar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar"><i class="fas fa-save me-1"></i>Crear Cliente</button>
                </div>
            </div>
        </form>
    </main>

    <!-- Quick Action -->
    <?php if ($esAdmin || $esEditor || $esSuper): ?>
    <button class="win-quick-action" onclick="document.getElementById('btnGuardar').click()" title="Crear cliente"><i class="fas fa-user-plus"></i></button>
    <?php endif; ?>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>

    <script>
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
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
            }
        }
        
        // Funciones del panel de temas
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
        
        // Cerrar panel con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themePanelOpen) {
                cerrarPanelTemas();
            }
        });
        
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
        
        // Validación en tiempo real para campo NoCtaDeudor (solo números, 14-16 dígitos)
        function validarCuentaBancaria() {
            const cuentaInput = document.getElementById('cuentaInput');
            const cuentaLengthSpan = document.getElementById('cuenta-length');
            const cuentaStatusSpan = document.getElementById('cuenta-status');
            const cuentaValidation = document.getElementById('cuenta-validation');
            
            // Permitir solo números
            cuentaInput.value = cuentaInput.value.replace(/\D/g, ''); // Solo números
            cuentaInput.value = cuentaInput.value.substring(0, 16); // Máximo 16 caracteres
            
            // Validar y actualizar estado
            const value = cuentaInput.value;
            const length = value.length;
            
            cuentaLengthSpan.textContent = length;
            
            if (length === 0) {
                cuentaLengthSpan.className = 'text-muted fw-bold';
                cuentaStatusSpan.textContent = '';
                cuentaStatusSpan.className = '';
                cuentaInput.classList.remove('is-valid', 'is-invalid');
                cuentaValidation.textContent = '';
            } else if (length >= 14 && length <= 16) {
                cuentaLengthSpan.className = 'text-success fw-bold';
                cuentaStatusSpan.textContent = '✓ Válido';
                cuentaStatusSpan.className = 'text-success';
                cuentaInput.classList.remove('is-invalid');
                cuentaInput.classList.add('is-valid');
                cuentaValidation.textContent = '';
            } else if (length < 14) {
                cuentaLengthSpan.className = 'text-warning fw-bold';
                cuentaStatusSpan.textContent = '⚠ Muy corto';
                cuentaStatusSpan.className = 'text-warning';
                cuentaInput.classList.remove('is-valid');
                cuentaInput.classList.add('is-invalid');
                cuentaValidation.textContent = 'La cuenta debe tener al menos 14 dígitos';
            } else {
                cuentaLengthSpan.className = 'text-danger fw-bold';
                cuentaStatusSpan.textContent = '✗ Demasiado largo';
                cuentaStatusSpan.className = 'text-danger';
                cuentaInput.classList.remove('is-valid');
                cuentaInput.classList.add('is-invalid');
                cuentaValidation.textContent = 'La cuenta no puede tener más de 16 dígitos';
            }
        }
        
        // Funciones Lógica Cliente
        function generarNuevoCodigo() {
            let code = document.getElementById('codigoInput').value;
            document.getElementById('codigoInput').value = /^\d+$/.test(code) ? (parseInt(code)+1).toString().padStart(5,'0') : '00001';
            validarCodigoUnico();
            actualizarResumen('codigo', document.getElementById('codigoInput').value);
        }

        function generarCodigoDesdeCuenta() {
            const cuenta = document.getElementById('cuentaInput').value.replace(/\D/g,'');
            if (cuenta.length >= 5) { 
                document.getElementById('codigoInput').value = cuenta.slice(-5).padStart(5, '0'); 
                validarCodigoUnico(); 
                actualizarResumen('codigo', document.getElementById('codigoInput').value);
            }
        }
        
        function validarCodigoUnico() { 
            if(document.getElementById('codigoInput').value) 
                document.getElementById('codigoInput').classList.add('is-valid'); 
        }
        
        // Actualizar resumen en tiempo real
        function actualizarResumen(campo, valor) {
            switch(campo) {
                case 'codigo':
                    document.getElementById('resumenCodigo').textContent = valor;
                    break;
                case 'fechaInicio':
                    if (valor) {
                        let fecha = new Date(valor);
                        document.getElementById('resumenFechaInicio').textContent = fecha.toLocaleDateString('es-ES');
                    }
                    break;
            }
        }
        
        // Actualizar estado
        function actualizarEstado() {
            const estadoCheck = document.getElementById('flexSwitchCheckChecked');
            const estadoLabel = document.getElementById('labelEstado');
            const resumenEstado = document.getElementById('resumenEstado');
            
            if (estadoCheck.checked) {
                estadoLabel.textContent = "ACTIVO";
                estadoLabel.style.color = "var(--win-text-primary)";
                resumenEstado.textContent = "ACTIVO";
                resumenEstado.className = "resumen-value estado-activo";
            } else {
                estadoLabel.textContent = "INACTIVO";
                estadoLabel.style.color = "#dc3545";
                resumenEstado.textContent = "INACTIVO";
                resumenEstado.className = "resumen-value estado-inactivo";
            }
        }
        
        // Validar teléfono
        function validarTelefono(input) {
            let valor = input.value;
            // Permitir solo números, espacios y el signo +
            input.value = valor.replace(/[^0-9+\s]/g, '');
        }
        
        // ==================== FUNCIONES PARA NIT Y REEUP ====================

        // Lista maestra de organismos (igual que en PHP)
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

        // Lista maestra de naturalezas (dígito central)
        const naturalezasReales = {
            0: { clase: 'bg-reeup-estatal', text: 'Empresa Estatal', icon: 'fa-building' },
            1: { clase: 'bg-reeup-cpa', text: 'CPA', icon: 'fa-tractor' },
            2: { clase: 'bg-reeup-mixta', text: 'Empresa Mixta', icon: 'fa-handshake' },
            3: { clase: 'bg-reeup-sociedad', text: 'Sociedad Mercantil', icon: 'fa-briefcase' },
            4: { clase: 'bg-reeup-ubpc', text: 'UBPC', icon: 'fa-users-cog' },
            5: { clase: 'bg-reeup-presupuestada', text: 'Unidad Presupuestada', icon: 'fa-university' },
            6: { clase: 'bg-reeup-cna', text: 'CNA', icon: 'fa-store-alt' },
            7: { clase: 'bg-reeup-mipyme', text: 'MIPYME / TCP', icon: 'fa-user-tie' },
            8: { clase: 'bg-reeup-asociacion', text: 'Asociación / ONG', icon: 'fa-church' },
            9: { clase: 'bg-reeup-otros', text: 'Otros / Extranjero', icon: 'fa-globe' }
        };

        // Tipos de entidad para NIT
        const tiposEntidadNIT = {
            '1': { clase: 'bg-nit-persona-natural', text: 'Persona Natural', icon: 'fa-user' },
            '2': { clase: 'bg-nit-persona-juridica', text: 'Persona Jurídica', icon: 'fa-building' },
            '3': { clase: 'bg-nit-extranjero', text: 'Extranjero sin identificación', icon: 'fa-passport' },
            '5': { clase: 'bg-nit-agrupacion', text: 'Agrupación de ciudadanos', icon: 'fa-users' },
            '9': { clase: 'bg-nit-otros', text: 'Otros', icon: 'fa-ellipsis-h' }
        };

        // Mapa NIT Cuba
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

        // ============ FUNCIONES PARA REEUP ============

        // Función para formatear REEUP mientras se escribe
        function formatearREEUP(input) {
            let value = input.value.replace(/[^\d]/g, '');
            
            if (value.length > 0) {
                let formatted = '';
                
                if (value.length <= 3) {
                    formatted = value;
                } else if (value.length <= 4) {
                    formatted = value.substring(0, 3) + '.' + value.substring(3);
                } else {
                    formatted = value.substring(0, 3) + '.' + 
                               value.substring(3, 4) + '.' + 
                               value.substring(4, 8);
                }
                
                // Solo actualizar si hay cambio
                if (input.value !== formatted) {
                    input.value = formatted;
                }
            }
            
            detectarTipoREUPJS(input.value);
        }

        // Función mejorada para detectar tipo REUP
        function detectarTipoREUPJS(codigo) {
            const badge = document.getElementById('badgeTipoReeup');
            const texto = document.getElementById('textoTipoReeup');
            const analisis = document.getElementById('analisisReeup');
            const input = document.getElementById('reupInput');
            
            if (!badge || !texto || !analisis || !input) {
                console.warn('Elementos REEUP no encontrados');
                return;
            }

            // Limpiar espacios
            codigo = codigo.trim();
            
            if (!codigo || codigo.length === 0) {
                badge.className = 'badge bg-light text-dark border px-2 py-1 badge-tipo-reeup';
                texto.textContent = 'Sin datos';
                analisis.textContent = 'Formato requerido: 000.0.0000';
                input.classList.remove('is-valid', 'is-invalid');
                return;
            }
            
            // Validar formato con Expresión Regular (###.#.####)
            const regex = /^(\d{3})\.(\d)\.(\d{4})$/;
            const match = codigo.match(regex);
            
            if (!match) {
                badge.className = 'badge bg-danger px-2 py-1 badge-tipo-reeup';
                texto.innerHTML = '<i class="fas fa-times me-1"></i>Formato inválido';
                analisis.textContent = 'Formato correcto: 000.0.0000 (solo números)';
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                return;
            }
            
            const codOrg = match[1];
            const tipoDigito = parseInt(match[2]);
            const registro = match[3];

            const orgNombre = organismosReales[codOrg] || "Organismo " + codOrg;
            const nat = naturalezasReales[tipoDigito] || { clase: 'bg-light text-dark', text: 'No definido', icon: 'fa-question' };
            
            // Actualizar badge
            badge.className = 'badge ' + nat.clase + ' px-2 py-1 badge-tipo-reeup';
            texto.innerHTML = '<i class="fas ' + nat.icon + ' me-1"></i>' + nat.text;
            
            // Actualizar análisis
            analisis.innerHTML = `Organismo: <strong>${orgNombre}</strong> | Tipo: <strong>${nat.text}</strong>`;
            
            // Marcar como válido
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        }

        // Función para mostrar detalles del REEUP
        function mostrarDetalleReeup() {
            const codigo = document.getElementById('reupInput').value;
            const regex = /^(\d{3})\.(\d)\.(\d{4})$/;
            const match = codigo.match(regex);

            if (!match || codigo.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Código Inválido',
                    text: 'Por favor, ingrese un código REEUP válido (formato 000.0.0000) para ver el análisis.',
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)',
                    confirmButtonColor: 'var(--win-accent)',
					confirmButtonText: '<i class="fas fa-check"></i> Entendido'
                });
                return;
            }

            const codOrg = match[1];
            const tipoDigito = parseInt(match[2]);
            const registro = match[3];

            const orgNombre = organismosReales[codOrg] || "Organismo " + codOrg;
            const nat = naturalezasReales[tipoDigito] || { text: 'No definido', icon: 'fa-question' };

            const naturalezasDescriptivas = {
                0: "Empresa Estatal (propiedad 100% estatal)",
                1: "CPA (Cooperativa de Producción Agropecuaria)",
                2: "Empresa Mixta (capital estatal y extranjero)",
                3: "Sociedad Mercantil (Capital 100% Cubano)",
                4: "UBPC (Unidad Básica de Producción Cooperativa)",
                5: "Unidad Presupuestada (financiada por el presupuesto estatal)",
                6: "CNA (Cooperativa No Agropecuaria)",
                7: "MIPYME / TCP (Trabajador por Cuenta Propia)",
                8: "Asociación / ONG / Institución Religiosa",
                9: "Sucursal Extranjera / Otros"
            };

            Swal.fire({
                title: '<i class="fas fa-microchip me-2"></i>Análisis Técnico REEUP',
                html: `
                    <div style="text-align: left; font-size: 14px;">
                        <p class="mt-3"><strong>Código Completo:</strong> <span class="badge bg-dark">${codigo}</span></p>
                        
                        <div class="p-3 rounded mb-2" style="background: rgba(255,255,255,0.05); border-left: 4px solid var(--win-accent);">
                            <small class="text-muted d-block">Subordinación / Organismo:</small>
                            <span style="font-size: 16px; font-weight: 600;">${orgNombre}</span>
                            <small class="text-muted d-block mt-1">Código: ${codOrg}</small>
                        </div>

                        <div class="p-3 rounded mb-2" style="background: rgba(255,255,255,0.05); border-left: 4px solid #28a745;">
                            <small class="text-muted d-block">Tipo de Gestión (Naturaleza):</small>
                            <span style="font-size: 16px; font-weight: 600;">${nat.text}</span>
                            <small class="text-muted d-block mt-1">${naturalezasDescriptivas[tipoDigito] || 'No especificado'}</small>
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
                width: 500
            });
        }

        // ============ FUNCIONES PARA NIT ============

        // Función para validar NIT en tiempo real
        function validarNIT(input) {
            let value = input.value.replace(/[^\d]/g, '');
            
            // Limitar a 11 dígitos
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            // Actualizar valor si cambió
            if (input.value !== value) {
                input.value = value;
            }
            
            analizarNITJS(value);
            
            // Actualizar clases de validación
            if (value.length === 11) {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            } else if (value.length > 0) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-valid', 'is-invalid');
            }
        }

        // Función para analizar NIT en tiempo real
        function analizarNITJS(nit) {
            const badge = document.getElementById('badgeTipoNit');
            const texto = document.getElementById('textoTipoNit');
            const analisis = document.getElementById('analisisNit');
            const input = document.getElementById('nitInput');
            
            if (!badge || !texto || !analisis || !input) {
                console.warn('Elementos NIT no encontrados');
                return;
            }

            nit = nit.replace(/\D/g, ''); // Solo números
            
            if (nit.length === 11 && /^\d{11}$/.test(nit)) {
                const tipo = tiposEntidadNIT[nit[0]] || { clase: 'bg-light text-dark', text: 'Desconocido', icon: 'fa-question' };
                const codProv = nit.substring(0, 2);
                const codMun = nit.substring(2, 4);
                const provInfo = MAPA_NIT_CUBA[codProv] || { nombre: 'Desconocida', municipios: {} };
                const munNombre = provInfo.municipios[codMun] || 'Mun. Desconocido';
                const expediente = nit.substring(4, 10);
                const control = nit.substring(10);
                
                badge.className = 'badge ' + tipo.clase + ' px-2 py-1 badge-tipo-nit';
                texto.innerHTML = '<i class="fas ' + tipo.icon + ' me-1"></i>' + tipo.text;
                
                analisis.innerHTML = `
                    <div class="small">
                        Provincia: <strong>${provInfo.nombre}</strong><br>
                        Municipio: <strong>${munNombre}</strong><br>
                        Expediente: <strong>${expediente}</strong> | Control: <strong>${control}</strong>
                    </div>
                `;
                
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                
            } else if (nit.length === 0) {
                badge.className = 'badge bg-light text-dark border px-2 py-1 badge-tipo-nit';
                texto.textContent = 'Sin NIT';
                analisis.textContent = 'Ingrese 11 dígitos para análisis...';
                input.classList.remove('is-valid', 'is-invalid');
                
            } else if (nit.length < 11) {
                badge.className = 'badge bg-warning text-dark px-2 py-1 badge-tipo-nit';
                texto.innerHTML = '<i class="fas fa-clock me-1"></i>Incompleto';
                analisis.textContent = 'Faltan ' + (11 - nit.length) + ' dígitos para completar';
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                
            } else {
                badge.className = 'badge bg-danger px-2 py-1 badge-tipo-nit';
                texto.innerHTML = '<i class="fas fa-times me-1"></i>Inválido';
                analisis.textContent = 'Solo se permiten 11 dígitos numéricos';
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
            }
        }

        // Función para mostrar detalles del NIT
        function mostrarDetalleNit() {
            const nit = document.getElementById('nitInput').value;
            
            if (nit.length !== 11 || !/^\d+$/.test(nit)) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'NIT Inválido', 
                    text: 'El NIT debe tener 11 dígitos numéricos.',
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)',
					confirmButtonText: '<i class="fas fa-check"></i> Entendido'
                });
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
                width: 500
            });
        }

        // ==================== FUNCIONES AUXILIARES ====================

        // Función para limpiar validación visual
        function limpiarValidacion() {
            // Limpiar clases de validación
            document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
            
            // Resetear badges de NIT y REEUP
            const nitBadge = document.getElementById('badgeTipoNit');
            if (nitBadge) {
                nitBadge.className = 'badge bg-light text-dark border px-2 py-1 badge-tipo-nit';
                const textoNit = document.getElementById('textoTipoNit');
                if (textoNit) textoNit.textContent = 'Sin NIT';
                const analisisNit = document.getElementById('analisisNit');
                if (analisisNit) analisisNit.textContent = 'Ingrese 11 dígitos para análisis...';
            }
            
            const reupBadge = document.getElementById('badgeTipoReeup');
            if (reupBadge) {
                reupBadge.className = 'badge bg-light text-dark border px-2 py-1 badge-tipo-reeup';
                const textoReeup = document.getElementById('textoTipoReeup');
                if (textoReeup) textoReeup.textContent = 'Sin datos';
                const analisisReeup = document.getElementById('analisisReeup');
                if (analisisReeup) analisisReeup.textContent = 'Formato requerido: 000.0.0000';
            }
        }
        
        // Fechas
        function calcularFechas() {
            let fReg = document.getElementById('fechaRegistro').value;
            if(!fReg) return;
            let base = new Date(fReg); 
            base.setMinutes(base.getMinutes() + base.getTimezoneOffset());
            let vig = parseInt(document.getElementById('vigenciapor').value) || 0;
            
            let fVence = new Date(base); 
            fVence.setFullYear(fVence.getFullYear() + vig);
            document.getElementById('fechaVenceDisplay').value = fVence.toISOString().split('T')[0];
            
            let ren = document.getElementById('renovacSwitch').checked ? (parseInt(document.getElementById('si_renova_cant').value)||0) : 0;
            let fFinal = new Date(base); 
            fFinal.setFullYear(fFinal.getFullYear() + vig + ren);
            document.getElementById('fechafinalcontratoDisplay').value = fFinal.toISOString().split('T')[0];
            
            // Actualizar resumen
            document.getElementById('resumenFechaFin').textContent = fFinal.toLocaleDateString('es-ES');
            
            let hoy = new Date(); 
            hoy.setHours(0,0,0,0);
            let alert = document.getElementById('alertVencido');
            let sw = document.getElementById('flexSwitchCheckChecked');
            let lbl = document.getElementById('labelEstado');
            let resumenEstado = document.getElementById('resumenEstado');
            
            if(fFinal < hoy) {
                alert.style.display = 'block'; 
                sw.checked = false; 
                lbl.textContent = "INACTIVO (VENCIDO)"; 
                lbl.style.color = "#dc3545";
                resumenEstado.textContent = "VENCIDO";
                resumenEstado.className = "resumen-value estado-vencido";
            } else {
                alert.style.display = 'none'; 
                sw.checked = true; 
                lbl.textContent = "ACTIVO"; 
                lbl.style.color = "var(--win-text-primary)";
                resumenEstado.textContent = "ACTIVO";
                resumenEstado.className = "resumen-value estado-activo";
            }
        }

        function toggleRenovacion() {
            let div = document.getElementById('divRenovacion');
            let chk = document.getElementById('renovacSwitch');
            div.style.display = chk.checked ? 'block' : 'none';
            if(!chk.checked) document.getElementById('si_renova_cant').value = 0;
            calcularFechas();
        }

        function limpiarFormulario() {
            document.getElementById('clienteForm').reset();
            document.getElementById('fechaRegistro').value = '<?php echo date("Y-m-d"); ?>';
            document.getElementById('codigoInput').value = '<?php echo htmlspecialchars($nuevo_codigo); ?>';
            document.getElementById('contratoDisplay').value = '<?php echo htmlspecialchars($nuevo_contrato); ?>';
            document.getElementById('contratoHidden').value = '<?php echo htmlspecialchars($nuevo_contrato); ?>';
            
            // Limpiar validación
            limpiarValidacion();
            
            // Resetear validación de cuenta bancaria
            validarCuentaBancaria();
            
            // Resetear resumen
            document.getElementById('resumenCodigo').textContent = '<?php echo htmlspecialchars($nuevo_codigo); ?>';
            document.getElementById('resumenContrato').textContent = '<?php echo htmlspecialchars($nuevo_contrato); ?>';
            document.getElementById('resumenFechaInicio').textContent = new Date().toLocaleDateString('es-ES');
            document.getElementById('resumenFechaFin').textContent = 'No definida';
            document.getElementById('resumenEstado').textContent = 'PENDIENTE';
            document.getElementById('resumenEstado').className = "resumen-value";
            
            toggleRenovacion(); 
            calcularFechas();
        }

        // Validación y Submit
        document.getElementById('clienteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let errors = [];
            
            // Requeridos
            const req = { 
                'codigoInput':'Código Único', 
                'nombre':'Nombre Empresa, PDL IMDL, MIPYMES, etc.', 
                'ResponsableEntidad':'Responsable de la Entidad o Persona Autorizada a Firmar Contratos', 
                'vigenciapor':'Vigencia del Contrato' 
            };
            
            for(let id in req) {
                let el = document.getElementById(id);
                if(!el.value.trim()){ 
                    errors.push("El campo <span class='text-warning'><strong>"+req[id]+"</strong></span> es obligatorio."); 
                    el.classList.add('is-invalid'); 
                }
                else el.classList.remove('is-invalid');
            }

            // Validar REEUP (si se proporciona)
            let reup = document.getElementById('reupInput');
            if(reup.value && !/^\d{3}\.\d\.\d{4}$/.test(reup.value)) { 
                errors.push("Formato REEUP incorrecto. Debe ser: xxx.x.xxxx"); 
                reup.classList.add('is-invalid'); 
            } else if(reup.value) {
                reup.classList.remove('is-invalid');
            }

            // Validar NIT (si se proporciona)
            let nit = document.getElementById('nitInput');
            if(nit.value) {
                let nitNum = nit.value.replace(/\D/g, '');
                if(!/^\d{11}$/.test(nitNum)) { 
                    errors.push("El NIT debe tener 11 dígitos."); 
                    nit.classList.add('is-invalid'); 
                } else {
                    nit.classList.remove('is-invalid');
                }
            }

            // Validar si hay texto en Email
            let email = document.getElementById('emailInput');
            if(email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) { 
                errors.push("Formato de email incorrecto."); 
                email.classList.add('is-invalid'); 
            }

            // Validar Teléfono (si se proporciona)
            let tel = document.getElementById('telefonoInput');
            if(tel.value) {
                let cleanTel = tel.value.replace(/\s+/g, '');
                // Validar formato: + seguido de números, o solo números, mínimo 8 dígitos
                if(!/^(\+\d{1,3})?\d{8,}$/.test(cleanTel)) {
                    errors.push("Teléfono debe comenzar con + y tener al menos 8 números, o tener al menos 8 dígitos."); 
                    tel.classList.add('is-invalid');
                }
            }

            // Validar Cta Bancaria (Longitud 14-16 si existe)
            let cta = document.getElementById('cuentaInput');
            if(cta.value && (cta.value.length < 14 || cta.value.length > 16)) { 
                errors.push("Cuenta bancaria debe tener entre 14 y 16 dígitos."); 
                cta.classList.add('is-invalid'); 
            }

            // Validar Sucursal (Longitud 4-8 si existe)
            let suc = document.getElementById('sucursalInput');
            if(suc.value && (suc.value.length < 4 || suc.value.length > 8)) { 
                errors.push("Sucursal debe tener entre 4 y 8 dígitos."); 
                suc.classList.add('is-invalid'); 
            }

            // Validar Vigencia
            let vig = document.getElementById('vigenciapor');
            if(!vig.value || vig.value <= 0) { 
                errors.push("<span class='text-warning'><strong>La Vigencia del Contrato debe ser mayor a 0 años.<strong></span>"); 
                vig.classList.add('is-invalid'); 
            }

            // Validar Renovación
            if(document.getElementById('renovacSwitch').checked) {
                let ren = document.getElementById('si_renova_cant');
                if(!ren.value || ren.value <= 0) { 
                    errors.push("Años renovación requeridos."); 
                    ren.classList.add('is-invalid'); 
                }
            }

            if(errors.length > 0) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Atención', 
                    html: errors.join('<br>'),
                    background: '#1f1f1f',
                    color: '#fff',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                });
            } else {
                // Verificar si el contrato está VENCIDO
                const fechaFinalTotal = document.getElementById('fechafinalcontratoDisplay').value;
                const hoy = new Date().toISOString().split('T')[0];
                const contratoVencido = fechaFinalTotal < hoy;
                
                // Obtener datos para el mensaje
                const nombreEmpresa = document.getElementById('nombre').value;
                const responsable = document.getElementById('ResponsableEntidad').value;
                const codigoCliente = document.getElementById('codigoInput').value;
                const contrato = document.getElementById('contratoDisplay').value;
                const fechaInicio = document.getElementById('fechaRegistro').value;
                const fechaVencimiento = fechaFinalTotal;
                
                if (contratoVencido) {
                    // Mostrar alerta especial para contrato vencido
                    Swal.fire({
                        title: '⚠️ CONTRATO VENCIDO',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>¿Desea crear un contrato vencido desde el inicio?</strong></p>
                                <hr>
                                <div style="background: rgba(255, 193, 7, 0.1); padding: 10px; border-radius: 5px; margin: 10px 0;">
                                    <p><strong>Detalles del Cliente:</strong></p>
                                    <p><strong>Empresa:</strong> ${nombreEmpresa}</p>
                                    <p><strong>Responsable:</strong> ${responsable}</p>
                                    <p><strong>Código:</strong> ${codigoCliente}</p>
                                    <p><strong>Contrato:</strong> ${contrato}</p>
                                    <p><strong>Fecha Inicio:</strong> ${new Date(fechaInicio).toLocaleDateString('es-ES')}</p>
                                    <p><strong>Fecha Vencimiento:</strong> ${new Date(fechaVencimiento).toLocaleDateString('es-ES')} <span style="color: #dc3545; font-weight: bold;">(VENCIDO)</span></p>
                                </div>
                                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Este contrato ya está vencido según la fecha de vencimiento calculada. ¿Aun así desea Crearlo?</p>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-check-circle"></i> Sí, Crear igualmente',
                        cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                        background: '#1f1f1f',
                        color: '#fff',
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Si confirma, enviar el formulario
                            this.submit();
                        }
                    });
                } else {
                    // Mostrar confirmación normal con más detalles
                    Swal.fire({
                        title: '¿Crear Cliente?',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>Confirme los datos del nuevo cliente:</strong></p>
                                <hr>
                                <div style="background: rgba(13, 110, 253, 0.1); padding: 5px; border-radius: 5px; margin: 5px 0;line-height:1;">
                                    <p><strong>Empresa:</strong> ${nombreEmpresa}</p>
                                    <p><strong>Responsable del Contrato:</strong> ${responsable}</p>
                                    <p><strong>Código Único:</strong> ${codigoCliente}</p>
                                    <p><strong>N° Contrato:</strong> ${contrato}</p>
                                    <p><strong>Fecha Inicio:</strong> ${new Date(fechaInicio).toLocaleDateString('es-ES')}</p>
                                    <p><strong>Fecha Vencimiento:</strong> ${new Date(fechaVencimiento).toLocaleDateString('es-ES')}</p>
                                    <p><strong>Estado:</strong> <span style="color: #28a745; font-weight: bold;">ACTIVO</span></p>
                                </div>
                                <p class="text-muted"><i class="fas fa-info-circle"></i> Revise que todos los datos sean correctos antes de continuar.</p>
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-user-check"></i> Sí, Crear Cliente',
                        cancelButtonText: '<i class="fas fa-edit"></i> Revisar datos',
                        background: '#1f1f1f',
                        color: '#fff'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                }
            }
        });

        // ==================== INICIALIZACIÓN ====================

        document.addEventListener('DOMContentLoaded', () => {
            let t = localStorage.getItem('tema_windows'); 
            if(t) document.documentElement.setAttribute('data-theme', t);
            let c = localStorage.getItem('color_accent'); 
            if(c) { 
                document.documentElement.style.setProperty('--win-accent', c); 
                document.documentElement.style.setProperty('--win-accent-light', c + '20'); 
            }
            
            // Inicializar validación de cuenta bancaria
            validarCuentaBancaria();
            
            // Configurar eventos para cuenta bancaria
            const cuentaInput = document.getElementById('cuentaInput');
            if (cuentaInput) {
                cuentaInput.addEventListener('input', validarCuentaBancaria);
                cuentaInput.addEventListener('blur', validarCuentaBancaria);
            }
            
            // Configurar eventos para NIT
            const nitInput = document.getElementById('nitInput');
            if (nitInput) {
                nitInput.addEventListener('input', function(){ 
                    validarNIT(this);
                });
                // Analizar NIT inicial si tiene valor
                if (nitInput.value) {
                    validarNIT(nitInput);
                }
            }
            
            // Configurar eventos para REEUP
            const reupInput = document.getElementById('reupInput');
            if (reupInput) {
                reupInput.addEventListener('input', function() {
                    formatearREEUP(this);
                });
                // Analizar REEUP inicial si tiene valor
                if (reupInput.value) {
                    detectarTipoREUPJS(reupInput.value);
                }
            }
            
            // Configurar evento para sucursal
            const sucursalInput = document.getElementById('sucursalInput');
            if (sucursalInput) {
                sucursalInput.addEventListener('input', function(){ 
                    this.value = this.value.replace(/\D/g, '').substring(0, 8);
                });
            }
            
            // Configurar evento para teléfono
            const telefonoInput = document.getElementById('telefonoInput');
            if (telefonoInput) {
                telefonoInput.addEventListener('input', function() {
                    validarTelefono(this);
                });
            }
            
            // Inicializar estado y fechas
            toggleRenovacion(); 
            calcularFechas();
            actualizarEstado();
        });
    </script>
</body>
</html>
</body>
</html>