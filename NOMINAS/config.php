<?php
// config.php - Archivo de configuración principal

// ============================================
// FUNCIÓN PARA MOSTRAR SWEETALERT DE ERROR
// ============================================
function mostrarErrorMySQL($error_message) {
    // Limpiar cualquier salida previa
    if (ob_get_level()) ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de conexión - MySQL no disponible</title>
        <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
        <script src="../js/sweetalert2.all.min.js"></script>
        <style>
            body { 
                margin: 0; 
                padding: 0; 
                background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            title: '<i class="fas fa-database" style="color: #ef4444"></i> MySQL No Disponible',
            html: `
                <div style="text-align: left;">
                    <p style="color: #f87171; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Error de conexión:</strong>
                    </p>
                    <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; margin: 15px 0;">
                        <i class="fas fa-code me-2"></i>
                        <span style="font-family: monospace; font-size: 13px;"><?php echo addslashes($error_message); ?></span>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                    <p style="font-size: 13px; color: #9ca3af;">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Posibles soluciones:</strong><br>
                        • Inicie XAMPP / WAMP<br>
                        • Inicie el servicio de MySQL<br>
                        • Verifique que el puerto 3306 esté disponible<br>
                        • Contacte al administrador del sistema
                    </p>
                </div>
            `,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-sync-alt me-2"></i> Reintentar conexión',
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            allowOutsideClick: false,
            backdrop: 'rgba(0,0,0,0.8)'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.reload();
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit();
}

session_start();
date_default_timezone_set('America/Havana');

// ============================================
// RUTA BASE
// ============================================
define('BASE_URL', '/nominas');

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

define('BASE_PATH', dirname(__FILE__));
define('ASSETS_PATH', BASE_PATH . '/assets');

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'transnubet_nomina');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// CONEXIÓN PDO
// ============================================
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3
        ]
    );
} catch(PDOException $e) {
    // Mostrar SweetAlert con el error
    $error_msg = $e->getMessage();
    
    // Mensajes amigables según el error
    if (strpos($error_msg, 'denegó expresamente') !== false) {
        $error_msg = '🛑 MySQL está DETENIDO. El servicio de base de datos no está corriendo.';
    } elseif (strpos($error_msg, 'Unknown database') !== false) {
        $error_msg = '📁 La base de datos "' . DB_NAME . '" no existe.';
    } elseif (strpos($error_msg, 'Access denied') !== false) {
        $error_msg = '🔒 Credenciales de acceso incorrectas.';
    } elseif (strpos($error_msg, 'Connection refused') !== false) {
        $error_msg = '🔌 Conexión rechazada. ¿MySQL está ejecutándose en ' . DB_HOST . '?';
    }
    
    mostrarErrorMySQL($error_msg);
}

// ============================================
// CARGAR CONFIGURACIÓN DESDE LA BASE DE DATOS
// ============================================

/**
 * Obtiene un valor de configuración desde la tabla configuracion_general
 */
function getConfigValue($pdo, $parametro, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT valor, tipo_dato FROM configuracion_general WHERE parametro = ?");
        $stmt->execute([$parametro]);
        $result = $stmt->fetch();
        
        if ($result) {
            $valor = $result['valor'];
            $tipo = $result['tipo_dato'];
            
            switch ($tipo) {
                case 'entero':
                    return (int) $valor;
                case 'decimal':
                    return (float) $valor;
                case 'booleano':
                    return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                default:
                    return $valor;
            }
        }
        return $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Carga todas las configuraciones en un array cacheado
 */
function loadAllConfigs($pdo) {
    static $configs = null;
    
    if ($configs === null) {
        $configs = [];
        try {
            $stmt = $pdo->query("SELECT parametro, valor, tipo_dato FROM configuracion_general");
            while ($row = $stmt->fetch()) {
                $valor = $row['valor'];
                switch ($row['tipo_dato']) {
                    case 'entero':
                        $configs[$row['parametro']] = (int) $valor;
                        break;
                    case 'decimal':
                        $configs[$row['parametro']] = (float) $valor;
                        break;
                    case 'booleano':
                        $configs[$row['parametro']] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                        break;
                    default:
                        $configs[$row['parametro']] = $valor;
                }
            }
        } catch (PDOException $e) {
            // Si hay error, usar valores por defecto
        }
    }
    
    return $configs;
}

// ============================================
// DEFINIR CONSTANTES DESDE BASE DE DATOS
// ============================================

// Cargar configuraciones
$configs = loadAllConfigs($pdo);

// Definir constantes del sistema (con fallback a valores por defecto)
define('SITE_NAME', $configs['nombre_empresa'] ?? 'PDL TransNubet');
define('SITE_VERSION', '1.0.1');
define('COMPANY_NAME', $configs['nombre_empresa'] ?? 'PDL TRANSNUBET');
define('COMPANY_ADDRESS', $configs['direccion_empresa'] ?? 'Carretera Central Km 5, Camagüey, Cuba');
define('NIT', $configs['nit_empresa'] ?? '319-1-02264');
define('JEFE_PROYECTO', $configs['jefe_proyecto'] ?? 'Dainelys León Reyes');
define('ESPECIALISTA', $configs['especialista_gestion'] ?? 'Mailen Perez Garcia');

// Definir constantes de configuración laboral
define('HORAS_MENSUALES', $configs['horas_mensuales'] ?? 192);
define('DIAS_MENSUALES', $configs['dias_mensuales'] ?? 24);
define('HORAS_JORNADA_DIARIA', $configs['horas_jornada_diaria'] ?? 8);
define('SALARIO_MINIMO', $configs['salario_minimo'] ?? 2100);
define('TASA_CONTRIBUCION_ESPECIAL', $configs['tasa_contribucion_especial'] ?? 5);

// ============================================
// FUNCIONES DEL SISTEMA (AHORA USAN LAS CONSTANTES)
// ============================================

function getHorasMensuales() {
    return HORAS_MENSUALES;
}

function getDiasMensuales() {
    return DIAS_MENSUALES;
}

function getHorasJornadaDiaria() {
    return HORAS_JORNADA_DIARIA;
}

function getSalarioMinimo() {
    return SALARIO_MINIMO;
}

function getTasaContribucionBase() {
    return TASA_CONTRIBUCION_ESPECIAL;
}

/**
 * Obtiene la tasa de contribución especial desde la base de datos (con fecha de vigencia)
 */
function getTasaContribucion($pdo, $fecha = null) {
    if (!$fecha) $fecha = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracion_tasas 
                               WHERE nombre_tasa = 'contribucion_especial' AND fecha_vigencia <= ?
                               ORDER BY fecha_vigencia DESC LIMIT 1");
        $stmt->execute([$fecha]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (float) $result : TASA_CONTRIBUCION_ESPECIAL;
    } catch (PDOException $e) {
        return TASA_CONTRIBUCION_ESPECIAL;
    }
}

/**
 * Calcula el impuesto sobre ingresos personales según rangos configurados
 */
function calcularImpuestoIngresos($pdo, $salario_devengado, $fecha = null) {
    if (!$fecha) $fecha = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("SELECT desde, hasta, tasa, monto_fijo 
                               FROM configuracion_rangos_impuesto 
                               WHERE fecha_vigencia <= ? 
                               AND desde <= ?
                               ORDER BY desde DESC LIMIT 1");
        $stmt->execute([$fecha, $salario_devengado]);
        $rango = $stmt->fetch();
        
        if (!$rango) return 0;
        
        if ($rango['monto_fijo'] > 0) {
            return (float) $rango['monto_fijo'];
        }
        
        $base = $salario_devengado - $rango['desde'];
        if ($base < 0) $base = 0;
        
        return $base * (float) $rango['tasa'];
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Obtiene todos los rangos de impuesto
 */
function getRangosImpuesto($pdo, $fecha = null) {
    if (!$fecha) $fecha = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("
            SELECT desde, hasta, tasa, monto_fijo 
            FROM configuracion_rangos_impuesto 
            WHERE fecha_vigencia <= ? 
            ORDER BY desde ASC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ============================================
// FUNCIONES GENERALES
// ============================================

function existeNominaPeriodo($pdo, $periodo_desde, $periodo_hasta) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ?");
    $stmt->execute([$periodo_desde, $periodo_hasta]);
    return $stmt->fetchColumn() > 0;
}

function getTrabajadoresActivos($pdo) {
    $stmt = $pdo->prepare("
        SELECT t.*, e.salario_mensual, e.salario_hora_ordinaria, a.nombre_area, c.nombre as categoria_nombre
        FROM trabajadores t
        JOIN escalas_salariales e ON t.escala_salarial_id = e.id
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
        WHERE t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
        ORDER BY t.nombre_completo
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAreas($pdo) {
    $stmt = $pdo->query("SELECT * FROM areas WHERE activo = 1 ORDER BY nombre_area");
    return $stmt->fetchAll();
}

function getEscalas($pdo) {
    $stmt = $pdo->query("SELECT * FROM escalas_salariales WHERE activo = 1 ORDER BY escala_numero");
    return $stmt->fetchAll();
}

function getCategoriasOcupacionales($pdo) {
    $stmt = $pdo->query("SELECT * FROM categorias_ocupacionales WHERE activo = 1 ORDER BY orden");
    return $stmt->fetchAll();
}

function nombreMesEspanol($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)$mes];
}

function formatearMoneda($cantidad) {
    if ($cantidad === null || $cantidad === '') {
        $cantidad = 0;
    }
    $cantidad = floatval($cantidad);
    return '$' . number_format($cantidad, 2, '.', ',');
}

function formatearNumero($cantidad, $decimales = 2) {
    if ($cantidad === null || $cantidad === '') {
        $cantidad = 0;
    }
    return number_format(floatval($cantidad), $decimales, '.', ',');
}

// ============================================
// FUNCIONES DE VACACIONES (Ley 116)
// ============================================

function calcularDiasVacacionesGenerados($dias_trabajados) {
    if ($dias_trabajados <= 0) return 0;
    return $dias_trabajados / 11;
}

function calcularValorVacacionesGeneradas($salarios_devengados) {
    if ($salarios_devengados <= 0) return 0;
    return $salarios_devengados / 11;
}

function actualizarPeriodoVacaciones($pdo, $trabajador_id, $dias_laborados_mes, $salario_devengado_mes, $fecha_periodo) {
    $stmt = $pdo->prepare("SELECT id, dias_trabajados_en_periodo, salarios_devengados_en_periodo, fecha_inicio 
                           FROM periodos_laborales 
                           WHERE trabajador_id = ? AND fecha_fin IS NULL 
                           ORDER BY fecha_inicio DESC LIMIT 1");
    $stmt->execute([$trabajador_id]);
    $periodo_activo = $stmt->fetch();

    if ($periodo_activo) {
        $stmt = $pdo->prepare("UPDATE periodos_laborales 
                               SET dias_trabajados_en_periodo = dias_trabajados_en_periodo + ?,
                                   salarios_devengados_en_periodo = salarios_devengados_en_periodo + ?
                               WHERE id = ?");
        $stmt->execute([$dias_laborados_mes, $salario_devengado_mes, $periodo_activo['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO periodos_laborales (trabajador_id, fecha_inicio, dias_trabajados_en_periodo, salarios_devengados_en_periodo) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$trabajador_id, $fecha_periodo, $dias_laborados_mes, $salario_devengado_mes]);
    }

    recalcularVacacionesGeneradas($pdo, $trabajador_id);
}

function recalcularVacacionesGeneradas($pdo, $trabajador_id) {
    $stmt = $pdo->prepare("SELECT id, dias_trabajados_en_periodo, salarios_devengados_en_periodo 
                           FROM periodos_laborales 
                           WHERE trabajador_id = ? AND fecha_fin IS NULL");
    $stmt->execute([$trabajador_id]);
    $periodos_activos = $stmt->fetchAll();

    foreach ($periodos_activos as $periodo) {
        $dias_vacaciones = calcularDiasVacacionesGenerados($periodo['dias_trabajados_en_periodo']);
        $valor_vacaciones = calcularValorVacacionesGeneradas($periodo['salarios_devengados_en_periodo']);
        
        $updStmt = $pdo->prepare("UPDATE periodos_laborales 
                                   SET vacaciones_generadas_dias = ?, vacaciones_generadas_valor = ? 
                                   WHERE id = ?");
        $updStmt->execute([$dias_vacaciones, $valor_vacaciones, $periodo['id']]);
    }
}

function getSaldoVacaciones($pdo, $trabajador_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(saldo_vacaciones_dias), 0) as total_dias,
                                  COALESCE(SUM(vacaciones_generadas_valor), 0) as total_valor
                           FROM periodos_laborales 
                           WHERE trabajador_id = ? AND fecha_fin IS NULL");
    $stmt->execute([$trabajador_id]);
    return $stmt->fetch();
}

function cerrarPeriodoVacaciones($pdo, $trabajador_id, $fecha_fin) {
    $stmt = $pdo->prepare("UPDATE periodos_laborales 
                           SET fecha_fin = ? 
                           WHERE trabajador_id = ? AND fecha_fin IS NULL");
    return $stmt->execute([$fecha_fin, $trabajador_id]);
}

function descontarVacacionesDisfrutadas($pdo, $trabajador_id, $dias_disfrutados) {
    $stmt = $pdo->prepare("SELECT id FROM periodos_laborales 
                           WHERE trabajador_id = ? AND fecha_fin IS NULL 
                           ORDER BY fecha_inicio DESC LIMIT 1");
    $stmt->execute([$trabajador_id]);
    $periodo = $stmt->fetch();
    
    if ($periodo) {
        $stmt = $pdo->prepare("UPDATE periodos_laborales 
                               SET vacaciones_disfrutadas_dias = vacaciones_disfrutadas_dias + ? 
                               WHERE id = ?");
        return $stmt->execute([$dias_disfrutados, $periodo['id']]);
    }
    return false;
}
?>