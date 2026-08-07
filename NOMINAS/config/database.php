<?php
// config/database.php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Havana');

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

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'transnubet_nomina');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración general del sistema
define('SITE_NAME', 'PDL TransNuBeT');
define('SITE_VERSION', '1.0.0');
define('COMPANY_NAME', 'PDL TransNuBeT');
define('NIT', '319-1-02264');
define('JEFE_PROYECTO', 'Dainelys León Reyes');
define('ESPECIALISTA', 'Mailen Perez Garcia');

// Conexión PDO
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
    $error_msg = $e->getMessage();
    
    // Mensajes amigables según el error
    if (strpos($error_msg, 'denegó expresamente') !== false) {
        $error_msg = '🛑 MySQL está DETENIDO. El servicio de base de datos no está corriendo.';
    } elseif (strpos($error_msg, 'Unknown database') !== false) {
        $error_msg = '📁 La base de datos "' . DB_NAME . '" no existe.';
    } elseif (strpos($error_msg, 'Access denied') !== false) {
        $error_msg = '🔒 Credenciales de acceso incorrectas. Verifique usuario y contraseña.';
    } elseif (strpos($error_msg, 'Connection refused') !== false) {
        $error_msg = '🔌 Conexión rechazada. ¿MySQL está ejecutándose en ' . DB_HOST . '?';
    }
    
    mostrarErrorMySQL($error_msg);
}

// ============================================
// FUNCIONES DEL SISTEMA
// ============================================

function getConfig($pdo, $parametro, $default = null) {
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = ?");
    $stmt->execute([$parametro]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

function getHorasMensuales($pdo) {
    return (int) getConfig($pdo, 'horas_mensuales', 192);
}

function getTasaContribucion($pdo, $fecha = null) {
    if (!$fecha) $fecha = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT valor FROM configuracion_tasas 
                           WHERE nombre_tasa = 'contribucion_especial' AND fecha_vigencia <= ?
                           ORDER BY fecha_vigencia DESC LIMIT 1");
    $stmt->execute([$fecha]);
    $result = $stmt->fetchColumn();
    return $result !== false ? (float) $result : 5.00;
}

function calcularImpuestoIngresos($pdo, $salario_devengado, $fecha = null) {
    if (!$fecha) $fecha = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT desde, hasta, tasa FROM configuracion_rangos_impuesto 
                           WHERE fecha_vigencia <= ? AND desde <= ?
                           ORDER BY desde DESC LIMIT 1");
    $stmt->execute([$fecha, $salario_devengado]);
    $rango = $stmt->fetch();
    
    if (!$rango) return 0;
    
    $base = $salario_devengado - $rango['desde'];
    if ($base < 0) $base = 0;
    
    return $base * $rango['tasa'];
}

function actualizarVacaciones($pdo, $trabajador_id, $dias) {
    $stmt = $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas + ? WHERE id = ?");
    return $stmt->execute([$dias, $trabajador_id]);
}

function existeNominaPeriodo($pdo, $periodo_desde, $periodo_hasta) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ?");
    $stmt->execute([$periodo_desde, $periodo_hasta]);
    return $stmt->fetchColumn() > 0;
}

function getTrabajadoresActivos($pdo) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               e.salario_mensual, 
               e.salario_hora_ordinaria, 
               a.nombre_area, 
               c.nombre as categoria_nombre,
               t.cuentabanc
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
    return '$' . number_format($cantidad, 2, '.', ',');
}

/**
 * Registrar movimiento en el submayor de vacaciones
 */
function registrarMovimientoVacaciones($pdo, $trabajador_id, $periodo_desde, $periodo_hasta, $tipo_movimiento, $dias, $importe, $nomina_id = null, $tipo_nomina = null, $referencia = null, $observaciones = null) {
    global $user_nombre_completo;
    
    $stmt = $pdo->prepare("INSERT INTO submayor_vacaciones 
        (trabajador_id, periodo_desde, periodo_hasta, tipo_movimiento, dias, importe, nomina_id, tipo_nomina, referencia, usuario_registro, observaciones) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $trabajador_id, 
        $periodo_desde, 
        $periodo_hasta, 
        $tipo_movimiento, 
        $dias, 
        $importe, 
        $nomina_id, 
        $tipo_nomina,
        $referencia,
        $user_nombre_completo ?? $_SESSION['user_nombre'] ?? 'Sistema',
        $observaciones
    ]);
}
?>