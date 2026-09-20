<?php
// init.php
// Iniciar buffer de salida para evitar errores de headers
ob_clean();
ob_start();

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexión a base de datos y configuración inicial
require_once 'database.php';
require_once 'cierre_funciones.php';
// =========================================================================
// VERIFICACIÓN DEL SISTEMA (si aún la necesitas)
// =========================================================================
    require_once 'verificar_sistema.php';
    if (function_exists('iniciarVerificacionSistema')) {
        iniciarVerificacionSistema();
    }
	
// =========================================================================
// VERIFICAR BANDERA DE MANTENIMIENTO PARA PROGRAMADORES
// =========================================================================
if (isset($_SESSION['usuario_id']) && !isset($_SESSION['maintenance_bypass_notice'])) {
    try {
        $db = Database::getConnection();
        $sql = "SELECT rol_id FROM clasif_usuarios WHERE id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$_SESSION['usuario_id']]);
        $user = $stmt->fetch();
        
        // Si es programador (5) o supervisor (4) y el sistema está en mantenimiento
        if ($user && ($user['rol_id'] == 5 || $user['rol_id'] == 4)) {
            // Verificar si el mantenimiento está activo
            $sqlMaint = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
            $stmtMaint = $db->prepare($sqlMaint);
            $stmtMaint->execute();
            $maint = $stmtMaint->fetch();
            
            if ($maint && $maint['modo_mantenimiento'] == 1) {
                $_SESSION['maintenance_bypass_notice'] = true;
                error_log("✅ Bandera restaurada para programador/supervisor");
            }
        }
    } catch (Exception $e) {
        error_log("Error verificando bandera: " . $e->getMessage());
    }
}
// =========================================================================
// SOLO FECHA INICIO OPERACIONES
// =========================================================================

// Variable global con la fecha
$GLOBALS['fecha_inicio_operaciones'] = null;

// Función única para obtener la fecha (con caché estática)
function obtenerFechaInicioOperaciones() {
    static $fecha_cache = null;
    
    if ($fecha_cache === null) {
        try {
            $db = Database::getConnection();
            $sql = "SELECT fecha_inicio_operaciones 
                    FROM configuracion_sistema 
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetch();
            
            $fecha_cache = $resultado['fecha_inicio_operaciones'] ?? null;
            
            // También la ponemos en global para acceso directo
            $GLOBALS['fecha_inicio_operaciones'] = $fecha_cache;
        } catch (Exception $e) {
            $fecha_cache = null;
            $GLOBALS['fecha_inicio_operaciones'] = null;
        }
    }
    
    return $fecha_cache;
}


// Nueva función: Obtener año de la fecha de cierre
function obtenerAnioCierreOperaciones() {
    $fecha = obtenerFechaInicioOperaciones();
    return $fecha ? date('Y', strtotime($fecha)) : date('Y');
}

// Nueva función: Obtener mes de la fecha de cierre
function obtenerMesCierreOperaciones() {
    $fecha = obtenerFechaInicioOperaciones();
    return $fecha ? date('n', strtotime($fecha)) : date('n');
}

// Nueva función: Obtener fecha de cierre formateada para consultas SQL
function obtenerFechaCierreSQL() {
    $fecha = obtenerFechaInicioOperaciones();
    return $fecha ? date('Y-m-d', strtotime($fecha)) : date('Y-m-d');
}

// Nueva función: Verificar si una fecha está dentro del período de cierre
function estaDentroPeriodoCierre($fecha) {
    $fecha_cierre = obtenerFechaInicioOperaciones();
    if (!$fecha_cierre) return true; // Si no hay fecha de cierre, siempre true
    
    $fecha_comparar = strtotime($fecha);
    $fecha_cierre_timestamp = strtotime($fecha_cierre);
    
    return $fecha_comparar >= $fecha_cierre_timestamp;
}


// Cargar la fecha automáticamente al inicio
obtenerFechaInicioOperaciones();

// =========================================================================
// LÓGICA DE MANTENIMIENTO 
// =========================================================================
if (Database::checkMaintenanceMode()) {
    $permitir_acceso = false;
    
    if (isset($_SESSION['usuario_id'])) {
        try {
            $db = Database::getConnection();
            
            $sql_rol = "SELECT r.codigo 
                        FROM clasif_usuarios u 
                        INNER JOIN clasif_rol r ON u.rol_id = r.id 
                        WHERE u.id = ? LIMIT 1";
            $stmt_rol = $db->prepare($sql_rol);
            $stmt_rol->execute([$_SESSION['usuario_id']]);
            $resultado = $stmt_rol->fetch();
            
            $roles_permitidos = ['Soft']; 
            
            if ($resultado && in_array($resultado['codigo'], $roles_permitidos)) {
                $permitir_acceso = true;
                $_SESSION['maintenance_bypass_notice'] = true;
            }
        } catch (Exception $e) {
            $permitir_acceso = false;
        }
    }

    if (!$permitir_acceso) {
        Database::showMaintenancePage();
        exit();
    }
}

// =========================================================================
// CONFIGURACIÓN DE ZONA HORARIA
// =========================================================================
if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/New_York'); 
} else {
    date_default_timezone_set('America/New_York'); 
}

// =========================================================================
// FUNCIÓN PARA FORMATEAR FECHA DE INICIO
// =========================================================================

// Función auxiliar para formatear la fecha si se necesita
function fechaInicioFormateada($formato = 1) {
    $fecha = obtenerFechaInicioOperaciones();
    
    if (!$fecha) {
        return null;
    }
    
    $timestamp = strtotime($fecha);
    
    switch($formato) {
        case 1:
        case 'texto':
        case 'completo':
            // Formato: 23 de enero de 2025
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $dia = date('j', $timestamp);
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $dia . ' de ' . $mes . ' de ' . $anio;
            
        case 2:
        case 'corto':
        case 'numerico':
            // Formato: 23/01/2025
            return date('d/m/Y', $timestamp);
            
        case 3:
        case 'sql':
        case 'ymd':
            // Formato SQL: 2025-01-23
            return date('Y-m-d', $timestamp);
            
        case 4:
        case 'us':
        case 'mdy':
            // Formato US: 01/23/2025
            return date('m/d/Y', $timestamp);
            
        case 5:
        case 'dia_mes':
            // Formato: 23 de Enero
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $dia = date('j', $timestamp);
            $mes = $meses[date('n', $timestamp)];
            return $dia . ' de ' . $mes;
            
        case 6:
        case 'mysql':
            // Para uso en consultas MySQL
            return date('Y-m-d H:i:s', $timestamp);
            
        case 7:
        case 'corta':
            // Formato corto: 23/01/25
            return date('d/m/y', $timestamp);
            
        case 8:
        case 'mes_anio':
        case 'mes_año':
            // Formato: Enero/2025 (mes completo/año)
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $mes . '/' . $anio;
            
        case 9:
        case 'mes_anio_corto':
        case 'mes_año_corto':
            // Formato: Ene/2025 (mes abreviado/año)
            $meses_cortos = array(
                1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
            );
            $mes = $meses_cortos[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $mes . '/' . $anio;
            
        case 10:
        case 'mes_anio_numerico':
        case 'mes_año_numerico':
            // Formato: 01/2025 (mes numérico/año)
            return date('m/Y', $timestamp);
            
        case 11:
        case 'anio_mes':
        case 'año_mes':
            // Formato: 2025/Enero (año/mes completo)
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $anio . '/' . $mes;
            
        case 12:
        case 'anio_mes_corto':
        case 'año_mes_corto':
            // Formato: 2025-Ene (año-mes abreviado)
            $meses_cortos = array(
                1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
            );
            $mes = $meses_cortos[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $anio . '-' . $mes;
            
        case 13:
        case 'periodo':
            // Formato para períodos: Enero 2025
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $mes . ' ' . $anio;
            
        default:
            // Si pasan un formato de date() directamente
            if (is_string($formato) && in_array($formato, ['d/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y', 'm/Y', 'Y/m'])) {
                return date($formato, $timestamp);
            }
            // Por defecto: formato 1
            return fechaInicioFormateada(1);
    }
}

// =========================================================================
// FUNCIONES GLOBALES PARA MANEJO DE FECHAS
// =========================================================================

/**
 * Función auxiliar para formatear fechas (reutiliza la lógica de fechaInicioFormateada)
 */
function formatearFecha($timestamp, $formato = 1) {
    if (!is_numeric($timestamp)) {
        return null;
    }
    
    // Si el formato es un string y es un formato date() estándar
    if (is_string($formato) && !in_array($formato, ['texto', 'completo', 'corto', 'numerico', 'sql', 'ymd', 'us', 'mdy', 'dia_mes', 'mysql', 'corta', 'mes_anio', 'mes_año', 'mes_anio_corto', 'mes_año_corto', 'mes_anio_numerico', 'mes_año_numerico', 'anio_mes', 'año_mes', 'anio_mes_corto', 'año_mes_corto', 'periodo'])) {
        return date($formato, $timestamp);
    }
    
    // Convertir formato string a número si es necesario
    if (is_string($formato)) {
        $formatos = [
            'texto' => 1, 'completo' => 1,
            'corto' => 2, 'numerico' => 2,
            'sql' => 3, 'ymd' => 3,
            'us' => 4, 'mdy' => 4,
            'dia_mes' => 5,
            'mysql' => 6,
            'corta' => 7,
            'mes_anio' => 8, 'mes_año' => 8,
            'mes_anio_corto' => 9, 'mes_año_corto' => 9,
            'mes_anio_numerico' => 10, 'mes_año_numerico' => 10,
            'anio_mes' => 11, 'año_mes' => 11,
            'anio_mes_corto' => 12, 'año_mes_corto' => 12,
            'periodo' => 13
        ];
        
        $formato = isset($formatos[$formato]) ? $formatos[$formato] : 1;
    }
    
    $formato = (int)$formato;
    
    switch($formato) {
        case 1:
            // Formato: 31 de Enero de 2025
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $dia = date('j', $timestamp);
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $dia . ' de ' . $mes . ' de ' . $anio;
            
        case 2:
            // Formato: 31/01/2025
            return date('d/m/Y', $timestamp);
            
        case 3:
            // Formato SQL: 2025-01-31
            return date('Y-m-d', $timestamp);
            
        case 4:
            // Formato US: 01/31/2025
            return date('m/d/Y', $timestamp);
            
        case 5:
            // Formato: 31 de Enero
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $dia = date('j', $timestamp);
            $mes = $meses[date('n', $timestamp)];
            return $dia . ' de ' . $mes;
            
        case 6:
            // Para uso en consultas MySQL
            return date('Y-m-d H:i:s', $timestamp);
            
        case 7:
            // Formato corto: 31/01/25
            return date('d/m/y', $timestamp);
            
        case 8:
            // Formato: Enero/2025
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $mes . '/' . $anio;
            
        case 9:
            // Formato: Ene/2025
            $meses_cortos = array(
                1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
            );
            $mes = $meses_cortos[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $mes . '/' . $anio;
            
        case 10:
            // Formato: 01/2025
            return date('m/Y', $timestamp);
            
        case 11:
            // Formato: 2025/Enero
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $anio . '/' . $mes;
            
        case 12:
            // Formato: 2025-Ene
            $meses_cortos = array(
                1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
            );
            $mes = $meses_cortos[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $anio . '-' . $mes;
            
        case 13:
            // Formato: Enero 2025
            $meses = array(
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            );
            $mes = $meses[date('n', $timestamp)];
            $anio = date('Y', $timestamp);
            return $mes . ' ' . $anio;
            
        default:
            // Por defecto formato 1
            return formatearFecha($timestamp, 1);
    }
}

/**
 * Obtiene el último día del mes de una fecha dada
 * 
 * @param string $fecha Fecha en cualquier formato válido (Y-m-d, d/m/Y, etc.)
 * @param mixed $formato Formato de salida (1=texto, 2=numerico, string=formato date())
 * @return string|null Último día del mes formateado
 */
function ultimoDiaMes($fecha = null, $formato = 1) {
    // Si no se proporciona fecha, usar la fecha actual
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    // Intentar convertir la fecha a timestamp
    $timestamp = strtotime($fecha);
    
    // Si strtotime no funciona, intentar con formato d/m/Y
    if ($timestamp === false) {
        $partes = explode('/', $fecha);
        if (count($partes) === 3) {
            // Asumir formato d/m/Y
            $timestamp = strtotime($partes[2] . '-' . $partes[1] . '-' . $partes[0]);
        }
    }
    
    // Si aún no funciona, devolver null
    if ($timestamp === false) {
        return null;
    }
    
    // Obtener el último día del mes usando 't' que da el número de días del mes
    $ultimo_dia_timestamp = strtotime(date('Y-m-t', $timestamp));
    
    // Formatear según el parámetro $formato
    return formatearFecha($ultimo_dia_timestamp, $formato);
}

/**
 * Función específica para obtener el último día del mes de la fecha de inicio
 */
function ultimoDiaMesFechaInicio($formato = 1) {
    $fecha_inicio = obtenerFechaInicioOperaciones();
    
    if (!$fecha_inicio) {
        return null;
    }
    
    return ultimoDiaMes($fecha_inicio, $formato);
}

/**
 * Función para obtener el último día del mes actual
 */
function ultimoDiaMesActual($formato = 1) {
    return ultimoDiaMes(date('Y-m-d'), $formato);
}

// =========================================================================
// FUNCIONES ADICIONALES ÚTILES PARA FECHAS
// =========================================================================

/**
 * Obtiene el primer día del mes de una fecha dada
 */
function primerDiaMes($fecha = null, $formato = 1) {
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return null;
    }
    
    $primer_dia_timestamp = strtotime(date('Y-m-01', $timestamp));
    
    return formatearFecha($primer_dia_timestamp, $formato);
}

/**
 * Verifica si una fecha es el último día del mes
 */
function esUltimoDiaMes($fecha = null) {
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return false;
    }
    
    $dia_actual = date('j', $timestamp);
    $ultimo_dia_mes = date('t', $timestamp);
    
    return $dia_actual == $ultimo_dia_mes;
}

/**
 * Obtiene el número de días en el mes de una fecha dada
 */
function diasEnMes($fecha = null) {
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return null;
    }
    
    return date('t', $timestamp);
}

/**
 * Obtiene el nombre del mes de una fecha dada
 */
function nombreMes($fecha = null, $abreviado = false) {
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return null;
    }
    
    $numero_mes = date('n', $timestamp);
    
    if ($abreviado) {
        $meses_cortos = array(
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        );
        return $meses_cortos[$numero_mes] ?? '';
    } else {
        $meses = array(
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        );
        return $meses[$numero_mes] ?? '';
    }
}

/**
 * Obtiene el año de una fecha dada
 */
function obtenerAnio($fecha = null) {
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return null;
    }
    
    return date('Y', $timestamp);
}

/**
 * Obtiene el mes numérico de una fecha dada
 */
function obtenerMes($fecha = null) {
    if ($fecha === null) {
        $fecha = date('Y-m-d');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return null;
    }
    
    return date('n', $timestamp);
}

/**
 * Calcula la diferencia en días entre dos fechas
 */
function diferenciaDias($fecha1, $fecha2 = null) {
    if ($fecha2 === null) {
        $fecha2 = date('Y-m-d');
    }
    
    $timestamp1 = strtotime($fecha1);
    $timestamp2 = strtotime($fecha2);
    
    if ($timestamp1 === false || $timestamp2 === false) {
        return null;
    }
    
    $diferencia = abs($timestamp2 - $timestamp1);
    return floor($diferencia / (60 * 60 * 24));
}

/**
 * Valida si una fecha es válida
 */
function esFechaValida($fecha, $formato = 'Y-m-d') {
    $date = DateTime::createFromFormat($formato, $fecha);
    return $date && $date->format($formato) === $fecha;
}


function convertirNumeroALetras($numero, $moneda = 'PESOS', $centimos = 'CENTAVOS') {
    // 1. Asegurar que el input sea un string numérico para evitar notación científica (ej. 1.2E+13)
    // Utilizamos number_format para estandarizar a 2 decimales y separar miles con coma temporalmente
    $numero = number_format((float)$numero, 2, '.', '');
    
    // 2. Separar parte entera y decimal
    $partes = explode('.', $numero);
    $entero = $partes[0];
    $decimal = $partes[1];

    // Caso CERO
    if (intval($entero) == 0) {
        return "CERO $moneda CON $decimal/100";
    }

    // 3. Dividir la parte entera en grupos de 3 dígitos de derecha a izquierda
    // Invertimos la cadena para procesar triadas fácilmente
    $reversed = strrev($entero);
    $chunks = str_split($reversed, 3);
    
    // Definición de sufijos para cada grupo de 3 (Escala Larga de español)
    // Indice: 0=Unidad, 1=Mil, 2=Millón, 3=Mil(Millones), 4=Billón, 5=Mil(Billones), etc.
    $sufijos = [
        0 => '', 
        1 => 'MIL', 
        2 => ['MILLÓN', 'MILLONES'], 
        3 => 'MIL', 
        4 => ['BILLÓN', 'BILLONES'], 
        5 => 'MIL', 
        6 => ['TRILLÓN', 'TRILLONES'],
        7 => 'MIL',
        8 => ['CUATRILLÓN', 'CUATRILLONES']
    ];

    $texto_array = [];

    // 4. Iterar sobre los grupos (chunks)
    foreach ($chunks as $index => $chunk) {
        // Volvemos el chunk al orden normal (ej: "321" -> "123")
        $triada = strrev($chunk); 
        $num_triada = intval($triada);

        if ($num_triada == 0) {
            continue;
        }

        // Convertir el número de 3 dígitos a letras
        $texto_triada = convertirTriada($num_triada);

        // Determinar el sufijo
        $sufijo = '';
        if (isset($sufijos[$index])) {
            $s = $sufijos[$index];
            if (is_array($s)) {
                // Es un sufijo pluralizable (Millón/Billón)
                $sufijo = ($num_triada == 1) ? $s[0] : $s[1];
            } else {
                // Es "MIL"
                $sufijo = $s;
            }
        }

        // Reglas especiales para "UNO" y "MIL"
        
        // A) Si es 1000, 1000000000, etc. no se dice "UN MIL", solo "MIL"
        if ($sufijo == 'MIL' && $num_triada == 1) {
            $texto_triada = ''; // Se suprime el "UN"
        }
        
        // B) Corrección gramatical para "UN" vs "UNO"
        // Si termina en Millón/Billón, debe decir "UN MILLÓN" (ya manejado por convertirTriada que devuelve UN)
        
        // Agregar al array final (al principio porque estamos iterando al revés)
        $parte_final = trim($texto_triada . ' ' . $sufijo);
        array_unshift($texto_array, $parte_final);
    }

    // 5. Unir y formatear
    $resultado = implode(' ', $texto_array);
    
    // Limpieza final de espacios dobles
    $resultado = preg_replace('/\s+/', ' ', $resultado);

    return trim($resultado) . " " . $moneda . " CON " . $decimal . "/100";
}

// Función auxiliar para números del 0 al 999
function convertirTriada($num) {
    $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    $decenas  = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $diez_veinte = ['ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    $veinti    = ['VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    $texto = '';

    // Centenas
    $c = floor($num / 100);
    $resto = $num % 100;

    if ($c > 0) {
        if ($c == 1 && $resto == 0) {
            $texto .= 'CIEN';
        } else {
            $texto .= $centenas[$c];
        }
        if ($resto > 0) $texto .= ' ';
    }

    // Decenas y Unidades
    if ($resto > 0) {
        if ($resto < 10) {
            $texto .= $unidades[$resto];
        } elseif ($resto > 10 && $resto < 20) {
            $texto .= $diez_veinte[$resto - 11];
        } elseif ($resto == 10) {
            $texto .= 'DIEZ';
        } elseif ($resto == 20) {
            $texto .= 'VEINTE';
        } elseif ($resto > 20 && $resto < 30) {
            $texto .= $veinti[$resto - 21];
        } else {
            $d = floor($resto / 10);
            $u = $resto % 10;
            $texto .= $decenas[$d];
            if ($u > 0) {
                $texto .= ' Y ' . $unidades[$u];
            }
        }
    }
    
    return $texto;
}


function initGlobalToolsButton() {
    // Solo si hay usuario logueado
    if (!isset($_SESSION['usuario_id'])) return;

    try {
        $db = Database::getConnection();
        
        $sql_rol = "SELECT r.codigo, r.id 
                    FROM clasif_usuarios u 
                    INNER JOIN clasif_rol r ON u.rol_id = r.id 
                    WHERE u.id = ? LIMIT 1";
        $stmt_rol = $db->prepare($sql_rol);
        $stmt_rol->execute([$_SESSION['usuario_id']]);
        $resultado = $stmt_rol->fetch();
        
        $rol_codigo = $resultado['codigo'] ?? '';
        $rol_id = $resultado['id'] ?? 0;
        
        $es_admin = ($rol_id == 1 || $rol_codigo == 'Admin');
        
    } catch (Exception $e) {
        $es_admin = false;
    }
    
    $mesCierreNum = obtenerMesCierreOperaciones();
    $anioCierre = obtenerAnioCierreOperaciones();

    $meses_completos = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    $mesEspañol = isset($meses_completos[$mesCierreNum - 1]) 
        ? $meses_completos[$mesCierreNum - 1] 
        : 'Desconocido';

    $mesAnioCompletoES = $mesEspañol . ' / ' . $anioCierre;

    
echo '
<style>
.btn-close-custom {
    background-color: transparent !important;
    filter: invert(1) grayscale(100%) brightness(200%) !important;
    opacity: 0.7;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}
.btn-close-custom:hover {
    opacity: 1;
    transform: rotate(90deg) scale(1.2);
}
.btn-close-custom:active {
    transform: scale(0.8) rotate(90deg) !important;
    transition: all 0.1s !important;
}
.modelo-btn {
    transition: all 0.2s ease !important;
}
.modelo-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.utilities-dropdown .dropdown-toggle {
    width: auto;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 1px solid var(--win-border-color, #3d3d3d);
    color: var(--win-text-primary, #ffffff);
    background: transparent;
    margin-right: 4px;
}
.utilities-dropdown .dropdown-toggle:hover {
    background: #6c757d;
    color: white;
    border-color: #6c757d;
}
.utilities-dropdown .dropdown-menu {
    background-color: var(--win-bg-secondary, #1f1f1f);
    border: 1px solid var(--win-border-color, #333);
    min-width: 250px;
    padding: 0.5rem 0;
}
.utilities-dropdown .dropdown-item {
    color: var(--win-text-primary, #fff);
    padding: 0.6rem 1.2rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}
.utilities-dropdown .dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateX(5px);
}
.utilities-dropdown .dropdown-item i {
    width: 20px;
    text-align: center;
}
.utilities-dropdown .dropdown-divider {
    border-top-color: var(--win-border-color, #333);
    margin: 0.5rem 0;
}
.utilities-dropdown .dropdown-header {
    color: var(--win-text-secondary, #aaa);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0.5rem 1.2rem;
    background-color: rgba(0,0,0,0.2);
}
/* Estilos para el nuevo dropdown del asistente */
.asistente-dropdown .dropdown-toggle {
    width: auto;
    min-width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 1px solid var(--win-border-color, #3d3d3d);
    color: var(--win-text-primary, #ffffff);
    background: transparent;
    margin-right: 4px;
    padding: 0 8px;
}
.asistente-dropdown .dropdown-toggle:hover {
    background: #2e7d5e;
    color: white;
    border-color: #2e7d5e;
    transform: scale(1.05);
}
.asistente-dropdown .dropdown-toggle.activo {
    background: #2e7d5e;
    border-color: #2e7d5e;
    color: white;
}
.asistente-dropdown .dropdown-menu {
    background-color: var(--win-bg-secondary, #1f1f1f);
    border: 1px solid var(--win-border-color, #333);
    min-width: 220px;
    padding: 0.5rem 0;
}
.asistente-dropdown .dropdown-item {
    color: var(--win-text-primary, #fff);
    padding: 0.6rem 1.2rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}
.asistente-dropdown .dropdown-item:hover {
    background-color: rgba(46, 125, 94, 0.3);
    color: #fff;
}
.asistente-dropdown .dropdown-item i {
    width: 22px;
    text-align: center;
}
.asistente-dropdown .dropdown-divider {
    border-top-color: var(--win-border-color, #333);
    margin: 0.5rem 0;
}
.btn-chatbot-toggle {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 1px solid var(--win-border-color, #3d3d3d);
    color: var(--win-text-primary, #ffffff);
    background: transparent;
    margin-right: 4px;
    font-size: 1.1rem;
    cursor: pointer;
    position: relative;
    text-decoration: none;
}
.btn-chatbot-toggle i {
    font-size: 1.1rem;
}
.btn-chatbot-toggle:hover {
    background: #2e7d5e;
    color: white;
    border-color: #2e7d5e;
    transform: scale(1.05);
}
.btn-chatbot-toggle .tooltip-chatbot {
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: #1a1a1a;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 10000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s;
    border: 1px solid #2e7d5e;
}
.btn-chatbot-toggle:hover .tooltip-chatbot {
    opacity: 1;
}
@keyframes ondaExpansionGrande {
    0% { transform: scale(0.5); opacity: 0.9; border-width: 4px; }
    100% { transform: scale(5); opacity: 0; border-width: 1px; }
}
@keyframes sparkExplosion {
    0% { transform: scale(0); opacity: 1; }
    50% { transform: scale(1.5); opacity: 0.8; }
    100% { transform: scale(2.5); opacity: 0; }
}
</style>

<div class="modal fade" id="globalToolsModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 80%; width: 1400px;"> 
        <div class="modal-content" style="background-color: var(--win-bg-secondary, #1f1f1f); border: 1px solid var(--win-border-color, #333); color: var(--win-text-primary, #fff);">
            <div class="modal-header" style="border-bottom: 1px solid var(--win-border-color, #333);">
                <h5 class="modal-title"><i class="fas fa-toolbox me-2" style="color: var(--win-accent, #0078d4);"></i> Herramientas del Sistema</h5>
                <button type="button" class="btn-close btn-close-custom" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="globalToolsContent">
                <div class="d-flex flex-column justify-content-center align-items-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <span>Cargando herramientas...</span>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--win-border-color, #333);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-close me-2"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modelosImpresionModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background-color: var(--win-bg-secondary, #1f1f1f); border: 1px solid var(--win-border-color, #333); color: var(--win-text-primary, #fff);">
            <div class="modal-header" style="border-bottom: 1px solid var(--win-border-color, #333);">
                <h5 class="modal-title"><i class="fas fa-print me-2" style="color: var(--win-accent, #0078d4);"></i> Imprimir Modelos</h5>
                <button type="button" class="btn-close btn-close-custom" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-column gap-3">
                    <button type="button" class="btn btn-outline-primary btn-lg modelo-btn" onclick="window.location.href=\'modelo_factura_blanco.php?f\'"><i class="fas fa-file-invoice me-2"></i><span class="fw-bold">FACTURA</span><div class="small text-muted mt-1">Modelo de factura en blanco</div></button>
                    <button type="button" class="btn btn-outline-success btn-lg modelo-btn" onclick="window.location.href=\'modelo_factura_blanco.php?o\'"><i class="fas fa-tag me-2"></i><span class="fw-bold">OFERTA</span><div class="small text-muted mt-1">Modelo de oferta comercial</div></button>
                    <button type="button" class="btn btn-outline-warning btn-lg modelo-btn" onclick="window.location.href=\'ficha_costo_blanco.php?ficha\'"><i class="fas fa-calculator me-2"></i><span class="fw-bold">FICHA DE COSTO</span><div class="small text-muted mt-1">Modelo de ficha de costo</div></button>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--win-border-color, #333);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>';

    if ($es_admin) {
        echo '
<div class="modal fade" id="cierreContableModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered"> 
        <div class="modal-content" style="background-color: var(--win-bg-secondary, #1f1f1f); border: 1px solid var(--win-border-color, #333); color: var(--win-text-primary, #fff);">
            <div class="modal-header" style="border-bottom: 1px solid var(--win-border-color, #333);">
                <div class="w-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="modal-title mb-1 text-primary"><i class="fas fa-calendar-alt me-2" style="color: var(--win-accent, #0078d4);"></i> Cierres Contables</h5>
                            <div class="text-info small d-flex align-items-center mt-1" style="color: #17a2b8 !important; font-size: 0.85rem;"><i class="fas fa-calendar-check me-1"></i><span>Período Actual: <strong class="text-warning">' . htmlspecialchars($mesAnioCompletoES) . '</strong></span></div>
                        </div>
                        <button type="button" class="btn-close btn-close-custom" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <div class="modal-body p-4">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="card h-100 text-center border-primary" style="background: var(--win-bg-tertiary, #2a2a2a); cursor: pointer; transition: all 0.2s ease;" onclick="window.location.href=\'cierre_mes.php\'" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 4px 8px rgba(0,0,0,0.2)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'none\';">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="fas fa-calendar-check fa-3x mb-2" style="color: var(--win-accent, #0078d4);"></i>
                                <h5 class="card-title mb-1 text-primary">Cierre Mensual</h5>
                                <p class="card-text" style="color: #aaaaaa; font-size: 0.85rem;">Realizar cierre del mes:</p>
                                <div class="badge bg-primary text-white px-3 py-1 mt-1" style="font-size: 0.9rem;"><i class="fas fa-calendar-day me-1"></i><strong>' . htmlspecialchars($mesAnioCompletoES) . '</strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 text-center border-warning" style="background: var(--win-bg-tertiary, #2a2a2a); cursor: pointer; transition: all 0.2s ease;" onclick="window.location.href=\'cierre_anual.php\'" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 4px 8px rgba(0,0,0,0.2)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'none\';">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="fas fa-calendar-days fa-3x mb-2" style="color: #ffc107;"></i>
                                <h5 class="card-title mb-1 text-warning">Cierre Anual</h5>
                                <p class="card-text" style="color: #aaaaaa; font-size: 0.85rem;">Realizar cierre del año:</p>
                                <div class="badge bg-warning text-dark px-3 py-1 mt-1" style="font-size: 0.9rem;"><i class="fas fa-calendar-alt me-1"></i><strong>' . htmlspecialchars($anioCierre) . '</strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mt-3">
                        <div class="card border-info" style="background: var(--win-bg-tertiary, #2a2a2a); cursor: pointer; transition: all 0.2s ease;" onclick="window.location.href=\'cierres_realizados.php\'" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 4px 8px rgba(0,0,0,0.2)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'none\';">
                            <div class="card-body d-flex align-items-center py-2">
                                <div class="me-3"><i class="fas fa-history fa-2x" style="color: #17a2b8;"></i></div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1 text-info">Historial de Cierres</h5>
                                    <p class="card-text mb-0" style="color: #aaaaaa; font-size: 0.85rem;">Consultar todos los cierres realizados anteriormente</p>
                                </div>
                                <div class="ms-2"><i class="fas fa-chevron-right text-info"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3" style="background: rgba(13, 110, 253, 0.1); border-color: #0dcaf0; font-size: 0.9rem;">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle me-2 mt-1"></i>
                        <div>
                            <strong class="text-primary">Información del Período:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <div class="badge bg-primary text-white px-3 py-1"><i class="fas fa-calendar-check me-1"></i> Mes de Cierre: ' . htmlspecialchars($mesAnioCompletoES) . '</div>
                                <div class="badge bg-primary text-white px-3 py-1"><i class="fas fa-calendar-alt me-1"></i> Año Fiscal: ' . htmlspecialchars($anioCierre) . '</div>
                            </div>
                            <div class="mt-2 pt-2" style="border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem;">
                                <strong class="text-warning">Nota:</strong> <span class="text-info">Los cierres contables son procesos irreversibles. Asegúrese de tener respaldo y verificar todas las operaciones antes de proceder.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--win-border-color, #333);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-close me-2"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>';
    }

    echo '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Variable global de estado
    window.asistenteVisible = true;
    
    if (localStorage.getItem("robot_oculto") === "true") {
        window.asistenteVisible = false;
    }
    
    // Función para cerrar cualquier dropdown abierto
    function closeDropdown(dropdownElement) {
        if (dropdownElement) {
            const btn = dropdownElement.querySelector(".dropdown-toggle");
            if (btn && btn.classList.contains("show")) {
                const bsDropdown = bootstrap.Dropdown.getInstance(btn);
                if (bsDropdown) {
                    bsDropdown.hide();
                } else {
                    btn.classList.remove("show");
                    const menu = dropdownElement.querySelector(".dropdown-menu");
                    if (menu) menu.classList.remove("show");
                }
            }
        }
    }
    
    // Función para cerrar el dropdown UTILIDADES específicamente
    function closeUtilitiesDropdown() {
        const utilitiesDropdown = document.querySelector(".utilities-dropdown");
        if (utilitiesDropdown) {
            const btn = utilitiesDropdown.querySelector(".dropdown-toggle");
            if (btn && btn.classList.contains("show")) {
                const bsDropdown = bootstrap.Dropdown.getInstance(btn);
                if (bsDropdown) {
                    bsDropdown.hide();
                } else {
                    btn.classList.remove("show");
                    const menu = utilitiesDropdown.querySelector(".dropdown-menu");
                    if (menu) menu.classList.remove("show");
                }
            }
        }
    }
    
    function ejecutarAnimacionEspecialEntrada() {
        const robot = document.getElementById("robot-avatar-vuelo");
        const robotContainer = document.getElementById("robot-asistente-container");
        if (!robot) return;
        
        const originalTransition = robot.style.transition;
        const originalBoxShadow = robot.style.boxShadow;
        const originalBorder = robot.style.border;
        const originalZIndex = robotContainer.style.zIndex;
        
        robotContainer.style.zIndex = "10000000";
        robot.classList.remove("luz-pulsante-verde");
        robot.style.transition = "all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1)";
        
        const animSteps = [
            { scale: 1.5, rotate: 0, shadow: "0 0 30px 15px rgba(255, 215, 0, 1)", border: "5px solid #ffd700" },
            { scale: 1.9, rotate: 25, shadow: "0 0 60px 25px rgba(255, 215, 0, 1)", border: "5px solid #ffaa00" },
            { scale: 1.6, rotate: -20, shadow: "0 0 50px 22px rgba(255, 165, 0, 1)", border: "5px solid #ffaa44" },
            { scale: 1.8, rotate: 12, shadow: "0 0 55px 25px rgba(255, 215, 0, 1)", border: "5px solid #ffcc44" },
            { scale: 1.4, rotate: -8, shadow: "0 0 40px 18px rgba(255, 200, 0, 0.9)", border: "4px solid #ffcc66" },
            { scale: 1.2, rotate: 5, shadow: "0 0 30px 12px rgba(46, 125, 94, 0.9)", border: "3px solid #2e7d5e" },
            { scale: 1.0, rotate: 0, shadow: "0 0 15px 5px rgba(46, 125, 94, 0.6)", border: "2px solid #2e7d5e" }
        ];
        
        let stepIndex = 0;
        
        function crearParticulasBrillantes() {
            const rect = robot.getBoundingClientRect();
            const centroX = rect.left + rect.width / 2;
            const centroY = rect.top + rect.height / 2;
            for (let i = 0; i < 40; i++) {
                const particula = document.createElement("div");
                const angulo = Math.random() * Math.PI * 2;
                const distancia = Math.random() * 60 + 20;
                const destinoX = centroX + Math.cos(angulo) * distancia;
                const destinoY = centroY + Math.sin(angulo) * distancia;
                particula.style.cssText = "position: fixed; width: " + (Math.random() * 8 + 4) + "px; height: " + (Math.random() * 8 + 4) + "px; background: radial-gradient(circle, #ffd700, #ffaa00); border-radius: 50%; pointer-events: none; z-index: 9999999; left: " + centroX + "px; top: " + centroY + "px; box-shadow: 0 0 8px 2px rgba(255,215,0,0.8); transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);";
                document.body.appendChild(particula);
                setTimeout(function() { particula.style.left = destinoX + "px"; particula.style.top = destinoY + "px"; particula.style.opacity = "0"; particula.style.transform = "scale(0)"; }, 10);
                setTimeout(function() { particula.remove(); }, 700);
            }
        }
        
        function crearOndaChoque() {
            const rect = robot.getBoundingClientRect();
            const centroX = rect.left + rect.width / 2;
            const centroY = rect.top + rect.height / 2;
            for (let i = 0; i < 5; i++) {
                setTimeout(function() {
                    const onda = document.createElement("div");
                    onda.style.cssText = "position: fixed; pointer-events: none; z-index: 9999998; left: " + (centroX - 40) + "px; top: " + (centroY - 40) + "px; width: 80px; height: 80px; border-radius: 50%; border: 3px solid rgba(255,215,0,0.9); box-shadow: 0 0 20px rgba(255,215,0,0.8); animation: ondaExpansionGrande 0.8s ease-out forwards;";
                    document.body.appendChild(onda);
                    setTimeout(function() { onda.remove(); }, 800);
                }, i * 80);
            }
        }
        
        function ejecutarPaso() {
            if (stepIndex >= animSteps.length) {
                robot.style.transform = "scale(1) rotate(0deg)";
                robot.style.boxShadow = originalBoxShadow;
                robot.style.border = originalBorder;
                robot.style.transition = originalTransition;
                setTimeout(function() { robotContainer.style.zIndex = originalZIndex; }, 300);
                const chatWindowCheck = document.getElementById("chat-window-final");
                if (chatWindowCheck && chatWindowCheck.style.display !== "flex") {
                    robot.classList.add("luz-pulsante-verde");
                }
                const rect = robot.getBoundingClientRect();
                const spark = document.createElement("div");
                spark.style.cssText = "position: fixed; left: " + (rect.left + rect.width/2 - 30) + "px; top: " + (rect.top + rect.height/2 - 30) + "px; width: 60px; height: 60px; pointer-events: none; z-index: 9999999; background: radial-gradient(circle, rgba(255,215,0,0.9) 0%, rgba(255,215,0,0) 70%); border-radius: 50%; animation: sparkExplosion 0.5s ease-out forwards;";
                document.body.appendChild(spark);
                setTimeout(function() { spark.remove(); }, 500);
                return;
            }
            const step = animSteps[stepIndex];
            robot.style.transform = "scale(" + step.scale + ") rotate(" + step.rotate + "deg)";
            robot.style.boxShadow = step.shadow;
            robot.style.border = step.border;
            if (step.scale > 1.5) crearParticulasBrillantes();
            if (stepIndex === 1) crearOndaChoque();
            stepIndex++;
            setTimeout(ejecutarPaso, 120);
        }
        ejecutarPaso();
    }
    
    // Función para actualizar el estado visual del dropdown del asistente
    function actualizarEstadoDropdownAsistente() {
        const dropdownToggle = document.querySelector("#asistente-dropdown-btn");
        if (dropdownToggle) {
            if (window.asistenteVisible) {
                dropdownToggle.classList.add("activo");
            } else {
                dropdownToggle.classList.remove("activo");
            }
        }
    }
    
    // Función para mostrar/ocultar el asistente
    function toggleAsistente() {
        const chatWindow = document.getElementById("chat-window-final");
        const robotContainer = document.getElementById("robot-asistente-container");
        if (!chatWindow || !robotContainer) return;
        
        // Cerrar el dropdown del asistente si está abierto
        const asistenteDropdown = document.querySelector(".asistente-dropdown");
        if (asistenteDropdown) {
            closeDropdown(asistenteDropdown);
        }
        
        if (window.asistenteVisible) {
            window.asistenteVisible = false;
            chatWindow.style.display = "none";
            robotContainer.style.display = "none";
            localStorage.setItem("robot_oculto", "true");
            Swal.fire({
                icon: "info",
                title: "Asistente Oculto",
                text: "El asistente virtual ha sido ocultado.",
                timer: 1500,
                showConfirmButton: false,
                background: "var(--win-bg-secondary, #1f1f1f)",
                color: "var(--win-text-primary, #fff)"
            });
        } else {
            window.asistenteVisible = true;
            robotContainer.style.display = "flex";
            localStorage.removeItem("robot_oculto");
            const savedX = localStorage.getItem("robot_position_x");
            const savedY = localStorage.getItem("robot_position_y");
            if (savedX && savedY) {
                robotContainer.style.left = savedX + "px";
                robotContainer.style.top = savedY + "px";
                robotContainer.style.bottom = "auto";
                robotContainer.style.right = "auto";
            } else {
                robotContainer.style.left = "25px";
                robotContainer.style.top = "";
                robotContainer.style.bottom = "25px";
                robotContainer.style.right = "";
            }
            chatWindow.style.display = "flex";
            setTimeout(function() {
                const robotPos = robotContainer.getBoundingClientRect();
                const chatWidth = 380;
                const chatHeight = 550;
                let left = robotPos.right + 10;
                let top = robotPos.top;
                if (left + chatWidth > window.innerWidth) left = robotPos.left - chatWidth - 10;
                if (left < 10) left = 10;
                if (top + chatHeight > window.innerHeight) top = window.innerHeight - chatHeight - 10;
                if (top < 10) top = 10;
                chatWindow.style.left = left + "px";
                chatWindow.style.top = top + "px";
                chatWindow.style.bottom = "auto";
                chatWindow.style.right = "auto";
                const input = document.getElementById("chat-input-final");
                if (input) input.focus();
            }, 200);
            setTimeout(function() { ejecutarAnimacionEspecialEntrada(); }, 150);
            Swal.fire({
                icon: "success",
                title: "✨ Asistente Activado ✨",
                html: "<div class=\"text-center\"><div style=\"font-size: 55px; margin-bottom: 10px;\">✨🤖✨</div><strong style=\"color: #2e7d5e; font-size: 18px;\">¡Hola! Soy SISFACT</strong><p class=\"mt-2 mb-0\">Estoy aquí para ayudarte</p></div>",
                timer: 2200,
                showConfirmButton: false,
                background: "var(--win-bg-secondary, #1f1f1f)",
                color: "var(--win-text-primary, #fff)",
                customClass: { popup: "border border-success rounded-4" }
            });
        }
        actualizarEstadoDropdownAsistente();
    }
    
    // Función para abrir la guía de comandos
    function abrirGuiaComandos() {
        // Cerrar el dropdown del asistente si está abierto
        const asistenteDropdown = document.querySelector(".asistente-dropdown");
        if (asistenteDropdown) {
            closeDropdown(asistenteDropdown);
        }
        // Cerrar cualquier otro dropdown
        closeUtilitiesDropdown();
        // Abrir AsisHelp.html en una nueva pestaña/ventana
        window.open("AsisHelp.html", "_blank");
    }
    
    // FUNCIONES PARA EL DROPDOWN DE UTILIDADES (sin la opción de Chat)
    const headerActions = document.querySelector(".header-actions") || document.querySelector(".win-navbar .d-flex[style*=\"flex: 1\"] + div") || document.querySelector(".win-navbar");
    
    if (headerActions) {
        // Crear el nuevo dropdown del asistente (reemplaza al botón anterior)
        const asistenteDropdown = document.createElement("div");
        asistenteDropdown.className = "dropdown asistente-dropdown d-inline-block";
        asistenteDropdown.setAttribute("style", "margin-right: 4px;");
        
        const asistenteBtn = document.createElement("button");
        asistenteBtn.id = "asistente-dropdown-btn";
        asistenteBtn.className = "dropdown-toggle";
        asistenteBtn.setAttribute("type", "button");
        asistenteBtn.setAttribute("data-bs-toggle", "dropdown");
        asistenteBtn.setAttribute("aria-expanded", "false");
        asistenteBtn.innerHTML = "<i class=\"fas fa-robot\"></i> <span class=\"ms-1 d-none d-sm-inline\"></span>";
        
        const asistenteMenu = document.createElement("ul");
        asistenteMenu.className = "dropdown-menu dropdown-menu-end";
        asistenteMenu.innerHTML = `
            <li><h6 class=\"dropdown-header\"><i class=\"fas fa-robot me-2\" style=\"color: #2e7d5e;\"></i>ASISTENTE VIRTUAL</h6></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"asistente-toggle-option\"><i class=\"fas fa-eye me-2\"></i> <span id=\"toggleOptionText\">Mostrar/Ocultar</span> Asistente</a></li>
            <li><hr class=\"dropdown-divider\"></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"asistente-guia-option\"><i class=\"fas fa-question-circle me-2\"></i> Guía de Comandos del Asistente</a></li>
        `;
        
        asistenteDropdown.appendChild(asistenteBtn);
        asistenteDropdown.appendChild(asistenteMenu);
        
        const refBtn = document.getElementById("theme-btn") || document.querySelector("[onclick*=\"abrirPanelTemas\"]");
        
        // Crear el dropdown de UTILIDADES (sin la opción de Chatear con el Asistente)
        const utilitiesDropdown = document.createElement("div");
        utilitiesDropdown.className = "dropdown utilities-dropdown d-inline-block";
        const utilitiesBtn = document.createElement("button");
        utilitiesBtn.className = "btn btn-outline-secondary dropdown-toggle";
        utilitiesBtn.setAttribute("type", "button");
        utilitiesBtn.setAttribute("data-bs-toggle", "dropdown");
        utilitiesBtn.setAttribute("aria-expanded", "false");
        utilitiesBtn.innerHTML = "<i class=\"fas fa-tools me-2\"></i> UTILIDADES";
        const utilitiesMenu = document.createElement("ul");
        utilitiesMenu.className = "dropdown-menu dropdown-menu-end";
        // ELIMINÉ la opción "Chatear con el Asistente" (dropdownChatbot)
        utilitiesMenu.innerHTML = `
            <li><h6 class=\"dropdown-header\"><i class=\"fas fa-toolbox me-2\" style=\"color: var(--win-accent, #0078d4);\"></i>HERRAMIENTAS DEL SISTEMA</h6></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownDbTools\"><i class=\"fas fa-database text-primary me-2\"></i> Base de Datos</a></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownImportarFacturas\"><i class=\"fas fa-file-import text-danger me-2\"></i> <span class=\"text-warning\">IMPORTAR OPERACIONES (DIARIO)</span></a></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownCapturarOffline\"><i class=\"fas fa-download text-info me-2\"></i> <span class=\"text-success\">CAPTURAR FACTURAS OFFLINE</span></a></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownModelos\"><i class=\"fas fa-print text-success me-2\"></i> Imprimir Modelos</a></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownCalculadora\"><i class=\"fas fa-calculator text-warning me-2\"></i> Calculadora de Windows</a></li>
            <li><hr class=\"dropdown-divider\"></li>
            <li><h6 class=\"dropdown-header\"><i class=\"fas fa-calendar-days me-2\" style=\"color: var(--win-accent, #0078d4);\"></i>CIERRES CONTABLES</h6></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownHistorialCierres\"><i class=\"fas fa-history text-info me-2\"></i> Historial de Cierres (Todos)</a></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownCierreMensual\"><i class=\"fas fa-calendar-check text-primary me-2\"></i> Cierre Mensual</a></li>
            <li><a class=\"dropdown-item\" href=\"#\" id=\"dropdownCierreAnual\"><i class=\"fas fa-calendar-alt text-warning me-2\"></i> Cierre Anual</a></li>
        `;
        utilitiesDropdown.appendChild(utilitiesBtn);
        utilitiesDropdown.appendChild(utilitiesMenu);
        
        // Insertar elementos en el header
        if (refBtn && refBtn.parentNode) {
            refBtn.parentNode.insertBefore(asistenteDropdown, refBtn);
            refBtn.parentNode.insertBefore(utilitiesDropdown, refBtn);
        } else {
            headerActions.appendChild(asistenteDropdown);
            headerActions.appendChild(utilitiesDropdown);
        }
        
        // Aplicar estado inicial del asistente
        setTimeout(function() {
            actualizarEstadoDropdownAsistente();
            const chatWindow = document.getElementById("chat-window-final");
            const robotContainer = document.getElementById("robot-asistente-container");
            if (!window.asistenteVisible && chatWindow && robotContainer) {
                chatWindow.style.display = "none";
                robotContainer.style.display = "none";
            }
        }, 500);
        
        // Eventos para el dropdown del asistente
        const toggleOption = document.getElementById("asistente-toggle-option");
        const guiaOption = document.getElementById("asistente-guia-option");
        
        if (toggleOption) {
            // Actualizar texto de la opción según estado
            function actualizarTextoOpcionToggle() {
                const span = document.getElementById("toggleOptionText");
                if (span) {
                    span.textContent = window.asistenteVisible ? "Ocultar" : "Mostrar";
                }
            }
            actualizarTextoOpcionToggle();
            
            toggleOption.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleAsistente();
                actualizarTextoOpcionToggle();
            });
        }
        
        if (guiaOption) {
            guiaOption.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                abrirGuiaComandos();
            });
        }
        
        // Eventos para el dropdown de UTILIDADES
        document.getElementById("dropdownImportarFacturas").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); window.location.href = "importar_factura.php"; });
        document.getElementById("dropdownCapturarOffline").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); window.location.href = "capturador_facturas.php"; });
        document.getElementById("dropdownDbTools").addEventListener("click", function(e) {
            e.preventDefault();
            closeUtilitiesDropdown();
            const modalEl = document.getElementById("globalToolsModal");
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            const container = document.getElementById("globalToolsContent");
            fetch("export-import/modal_view.php")
                .then(function(response) { return response.text(); })
                .then(function(html) {
                    container.innerHTML = html;
                    const scripts = container.querySelectorAll("script");
                    scripts.forEach(function(oldScript) {
                        const newScript = document.createElement("script");
                        for (let i = 0; i < oldScript.attributes.length; i++) {
                            newScript.setAttribute(oldScript.attributes[i].name, oldScript.attributes[i].value);
                        }
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                })
                .catch(function(err) { container.innerHTML = "<div class=\"alert alert-danger\">Error al cargar herramientas: " + err + "</div>"; });
        });
        document.getElementById("dropdownModelos").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); const modalEl = document.getElementById("modelosImpresionModal"); const modal = new bootstrap.Modal(modalEl); modal.show(); });
        document.getElementById("dropdownCalculadora").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); try { window.location.href = "calc://"; } catch(err) {} });
        document.getElementById("dropdownHistorialCierres").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); ' . ($es_admin ? 'window.location.href = "cierres_realizados.php";' : 'Swal.fire({icon:"error",title:"Acceso Restringido",text:"Solo administradores"});') . ' });
        document.getElementById("dropdownCierreMensual").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); ' . ($es_admin ? 'window.location.href = "cierre_mes.php";' : 'Swal.fire({icon:"error",title:"Acceso Restringido",text:"Solo administradores"});') . ' });
        document.getElementById("dropdownCierreAnual").addEventListener("click", function(e) { e.preventDefault(); closeUtilitiesDropdown(); ' . ($es_admin ? 'window.location.href = "cierre_anual.php";' : 'Swal.fire({icon:"error",title:"Acceso Restringido",text:"Solo administradores"});') . ' });
    }
});
</script>
';
}

// Ejecutar automáticamente
initGlobalToolsButton();

// =========================================================================
// FUNCIÓN PARA OBTENER INFORMACIÓN DE LA ÚLTIMA FACTURA
// =========================================================================

// Obtener la última factura (número, fecha, proveedor e importe)
function getLastInvoiceInfo() {
    try {
        $db = Database::getConnection();
        
        // Verificar si la tabla tiene datos
        $checkQuery = "SELECT COUNT(*) as total FROM tbl_fact";
        $checkResult = $db->query($checkQuery);
        $totalRows = $checkResult->fetch()['total'];
        
        if ($totalRows > 0) {
            // Obtener la última factura con proveedor e importe
            $query = "SELECT f.no_fact, f.fecha_emision, f.total_general, p.nombre as proveedor 
                      FROM tbl_fact f
                      LEFT JOIN clasif_clientes p ON f.cliente_id = p.id
                      ORDER BY f.id DESC LIMIT 1";
            $result = $db->query($query);
            
            if ($result) {
                $row = $result->fetch();
                return [
                    'numero' => $row['no_fact'],
                    'fecha' => date('d/m/Y', strtotime($row['fecha_emision'])),
                    'proveedor' => $row['proveedor'] ?? 'Sin proveedor',
                    'importe' => floatval($row['total_general'] ?? 0)
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error en getLastInvoiceInfo: " . $e->getMessage());
    }
    
    // Si no hay datos o hay error
    return [
        'numero' => '??',
        'fecha' => 'N/A',
        'proveedor' => 'N/A',
        'importe' => 0
    ];
}

$lastInvoiceInfo = getLastInvoiceInfo();


// =========================================================================
// FUNCIONES DE NOTIFICACIONES - SOLO CANTIDADES
// =========================================================================

/**
 * Obtiene el total de facturas pendientes (no pagadas ni anuladas ni cerradas)
 */
function getTotalFacturasPendientes() {
    try {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total 
                FROM tbl_fact 
                WHERE estado NOT IN ('PAGADA', 'ANULADA', 'CERRADA', 'CONTABILIZADA')";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return (int)($resultado['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Error en getTotalFacturasPendientes: " . $e->getMessage());
        return 0;
    }
}


/**
 * Obtiene el total de facturas sin pago (sin referencia de pago)
 * Facturas que no tienen valor en Ref_pago (NULL o cadena vacía)
 */
function getTotalFacturasSinPago() {
    try {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total 
                FROM tbl_fact 
                WHERE (Ref_pago IS NULL OR Ref_pago = '')
                AND estado NOT IN ('ANULADA', 'PENDIENTE')
                ORDER BY fecha_emision ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return (int)($resultado['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Error en getTotalFacturasSinPago: " . $e->getMessage());
        return 0;
    }
}


/**
 * Obtiene el total de contratos vencidos (clientes con fecha final contrato menor a hoy)
 */
function getTotalContratosVencidos() {
    try {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total 
                FROM clasif_clientes 
                WHERE fechafinalcontrato < CURDATE() 
                AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return (int)($resultado['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Error en getTotalContratosVencidos: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtiene el total de contratos próximos a vencer (próximos 30 días)
 */
function getTotalContratosProximosVencer($dias = 30) {
    try {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total 
                FROM clasif_clientes 
                WHERE fechafinalcontrato BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':dias' => $dias]);
        $resultado = $stmt->fetch();
        return (int)($resultado['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Error en getTotalContratosProximosVencer: " . $e->getMessage());
        return 0;
    }
}

// =========================================================================
// FUNCIÓN PARA VERIFICAR CIERRE PENDIENTE POR FECHA DEL SISTEMA
// =========================================================================

// =========================================================================
// FUNCIÓN PARA VERIFICAR CIERRE PENDIENTE POR FECHA DEL SISTEMA
// =========================================================================

/**
 * Verifica si hay un cierre pendiente basado en la fecha actual del sistema
 * Compara la fecha actual con el último día del mes de la fecha de operaciones
 * y verifica si ya existe un cierre registrado en historico_cierres
 * 
 * @return array [hay_cierre_pendiente, mensaje, mes, anio]
 */
function verificarCierrePendientePorFecha() {
    try {
        $db = Database::getConnection();
        
        // Obtener la fecha de inicio de operaciones
        $fecha_inicio = obtenerFechaInicioOperaciones();
        if (!$fecha_inicio) {
            return [
                'hay_cierre_pendiente' => false,
                'mensaje' => 'No hay fecha de operaciones configurada',
                'mes' => null,
                'anio' => null
            ];
        }
        
        // Obtener el último día del mes de la fecha de operaciones
        $ultimo_dia_mes_operaciones = date('Y-m-t', strtotime($fecha_inicio));
        $fecha_actual = date('Y-m-d');
        
        // Extraer mes y año de la fecha de operaciones
        $mes_operaciones = date('n', strtotime($fecha_inicio));
        $anio_operaciones = date('Y', strtotime($fecha_inicio));
        
        // Verificar si la fecha actual es mayor que el último día del mes de operaciones
        if (strtotime($fecha_actual) > strtotime($ultimo_dia_mes_operaciones)) {
            
            // Verificar si ya existe un cierre para este mes en historico_cierres
            $sql_verificar = "SELECT COUNT(*) as total 
                              FROM historico_cierres 
                              WHERE periodo_mes = :mes 
                              AND periodo_anio = :anio 
                              AND tipo = 1"; // tipo 1 = cierre mensual
            
            $stmt = $db->prepare($sql_verificar);
            $stmt->execute([
                ':mes' => $mes_operaciones,
                ':anio' => $anio_operaciones
            ]);
            $resultado = $stmt->fetch();
            
            $cierre_existente = ($resultado['total'] > 0);
            
            if (!$cierre_existente) {
                // Obtener nombre del mes
                $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                         'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                $nombre_mes = $meses[$mes_operaciones - 1] ?? '';
                
                return [
                    'hay_cierre_pendiente' => true,
                    'mensaje' => "CIERRE PENDIENTE:",
                    'periodo' => $nombre_mes . " " . $anio_operaciones,
                    'texto_completo' => "CIERRE PENDIENTE: El período de " . $nombre_mes . " " . $anio_operaciones . " ya puede cerrarse",
                    'mes' => $mes_operaciones,
                    'anio' => $anio_operaciones,
                    'nombre_mes' => $nombre_mes,
                    'ultimo_dia' => date('d/m/Y', strtotime($ultimo_dia_mes_operaciones)),
                    'fecha_actual' => date('d/m/Y')
                ];
            }
        }
        
        return [
            'hay_cierre_pendiente' => false,
            'mensaje' => '',
            'periodo' => '',
            'texto_completo' => '',
            'mes' => null,
            'anio' => null
        ];
        
    } catch (Exception $e) {
        error_log("Error en verificarCierrePendientePorFecha: " . $e->getMessage());
        return [
            'hay_cierre_pendiente' => false,
            'mensaje' => 'Error al verificar cierre pendiente',
            'periodo' => '',
            'texto_completo' => '',
            'mes' => null,
            'anio' => null
        ];
    }
}

// =========================================================================
// FUNCIÓN PARA VERIFICAR ÚLTIMO DÍA DE FACTURACIÓN DEL MES
// =========================================================================

/**
 * Verifica si la última factura emitida corresponde al último día del mes actual
 * @return array [es_ultimo_dia, mensaje, fecha_ultima_factura]
 */
function verificarUltimoDiaFacturacionMes() {
    try {
        $db = Database::getConnection();
        
        // Obtener la última fecha de emisión de factura
        $sql = "SELECT MAX(fecha_emision) as ultima_fecha 
                FROM tbl_fact 
                WHERE fecha_emision IS NOT NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch();
        
        $ultima_fecha = $resultado['ultima_fecha'] ?? null;
        
        if (!$ultima_fecha) {
            return [
                'es_ultimo_dia' => false,
                'mensaje' => 'No hay facturas en el sistema',
                'fecha_ultima_factura' => null
            ];
        }
        
        // Obtener el último día del mes de esa fecha
        $timestamp = strtotime($ultima_fecha);
        $ultimo_dia_mes = date('Y-m-t', $timestamp);
        $fecha_ultima = date('Y-m-d', $timestamp);

        // Verificar si es el último día del mes
        if ($fecha_ultima == $ultimo_dia_mes) {
            // Obtener nombre del mes
            $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                     'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $mes_numero = date('n', $timestamp) - 1;
            $nombre_mes = $meses[$mes_numero] ?? '';
            $anio = date('Y', $timestamp);
            
            return [
                'es_ultimo_dia' => true,
                'mensaje' => "Última factura del " . date('d/m/Y', $timestamp) . "<br>Puede cerrar $nombre_mes $anio",
                'fecha_ultima_factura' => $ultima_fecha,
                'mes' => $nombre_mes,
                'anio' => $anio
            ];
        } else {
            return [
                'es_ultimo_dia' => false,
                'mensaje' => "Última factura del " . date('d/m/Y', $timestamp) . "<br>(no es último día del mes)",
                'fecha_ultima_factura' => $ultima_fecha
            ];
        }
    } catch (Exception $e) {
        error_log("Error en verificarUltimoDiaFacturacionMes: " . $e->getMessage());
        return [
            'es_ultimo_dia' => false,
            'mensaje' => 'Error al verificar última factura',
            'fecha_ultima_factura' => null
        ];
    }
}

/**
 * Obtiene el total general de notificaciones (versión mejorada con cierre por fecha)
 */
function getTotalNotificaciones() {
    $total = 0;
    $total += getTotalFacturasPendientes();      // Facturas pendientes
    $total += getTotalFacturasSinPago();         // FACTURAS SIN PAGO
    $total += getTotalContratosVencidos();       // Contratos vencidos
    $total += getTotalContratosProximosVencer(); // Próximos a vencer
    
    // Verificar última factura del mes
    try {
        $ultimo_dia_info = verificarUltimoDiaFacturacionMes();
        if ($ultimo_dia_info['es_ultimo_dia']) {
            $total++;
        }
    } catch (Exception $e) {
        // Ignorar error
    }
    
    // Verificar cierre pendiente por fecha del sistema
    try {
        $cierre_pendiente = verificarCierrePendientePorFecha();
        if ($cierre_pendiente['hay_cierre_pendiente']) {
            $total++;
        }
    } catch (Exception $e) {
        // Ignorar error
    }
    
    return $total;
}

// =========================================================================
// FUNCIÓN PARA RENDERIZAR EL DROPDOWN DE NOTIFICACIONES (VERSIÓN MEJORADA)
// =========================================================================

// =========================================================================
// FUNCIÓN PARA RENDERIZAR EL DROPDOWN DE NOTIFICACIONES (VERSIÓN MEJORADA)
// =========================================================================

/**
 * Función para renderizar el dropdown de notificaciones
 * Usa las funciones de cierre_funciones.php e incluye la nueva verificación por fecha
 */
function renderNotificationsDropdown() {
    // Obtener todos los contadores
    $total_facturas_pendientes = getTotalFacturasPendientes();
    $total_facturas_sin_pago = getTotalFacturasSinPago();
    $total_contratos_vencidos = getTotalContratosVencidos();
    $total_contratos_proximos = getTotalContratosProximosVencer();
    
    // Verificar cierres usando las funciones de cierre_funciones.php
    $hay_cierre_mes = false;
    $hay_cierre_anual = false;
    $mensaje_cierre_mes = '';
    $mensaje_cierre_anual = '';
    
    // Verificar última factura del mes
    $ultimo_dia_info = verificarUltimoDiaFacturacionMes();
    $es_ultimo_dia = $ultimo_dia_info['es_ultimo_dia'];

    // Verificar cierre pendiente por fecha del sistema
    $cierre_pendiente_fecha = verificarCierrePendientePorFecha();
    $hay_cierre_pendiente_fecha = $cierre_pendiente_fecha['hay_cierre_pendiente'];

    try {
        $db = Database::getConnection();
        
        // Obtener período actual
        $periodo = obtenerPeriodoOperaciones($db);
        
        if ($periodo) {
            // Verificar si la última factura es del período actual Y es último día
            if ($es_ultimo_dia) {
                // Extraer mes y año de la última factura
                $fecha_ultima = $ultimo_dia_info['fecha_ultima_factura'];
                $mes_ultima = date('n', strtotime($fecha_ultima));
                $anio_ultima = date('Y', strtotime($fecha_ultima));
                
                // Si la última factura es del período actual, mostrar mensaje
                if ($mes_ultima == $periodo['mes'] && $anio_ultima == $periodo['anio']) {
                    $hay_cierre_mes = true;
                    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                             'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    $mensaje_cierre_mes = 'PUEDE CERRAR EL MES DE ' . $meses[$periodo['mes']-1] . ' ' . $periodo['anio'];
                }
            }
            
            // Verificar si se puede cerrar el año (solo en diciembre)
            if ($periodo['mes'] == 12) {
                $verificacion_anio = verificarCierreAnio($periodo['anio'], $db);
                if ($verificacion_anio['puede_cerrar']) {
                    $hay_cierre_anual = true;
                    $mensaje_cierre_anual = 'PUEDE CERRAR EL AÑO ' . $periodo['anio'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error al verificar cierres: " . $e->getMessage());
    }

    // Calcular total de notificaciones (contar cada tipo por separado)
    $total_notificaciones = $total_facturas_pendientes + 
                           $total_facturas_sin_pago + 
                           $total_contratos_vencidos + 
                           $total_contratos_proximos;
    
    // Contar cada tipo de notificación de cierre por separado
    $total_notificaciones_cierre = 0;
    if ($hay_cierre_pendiente_fecha) $total_notificaciones_cierre++;
    if ($es_ultimo_dia) $total_notificaciones_cierre++;
    if ($hay_cierre_mes) $total_notificaciones_cierre++;
    if ($hay_cierre_anual) $total_notificaciones_cierre++;
    
    $total_notificaciones += $total_notificaciones_cierre;
    
    // Determinar color del badge
    $badge_color = 'bg-secondary';
    if ($total_notificaciones > 0) {
        if ($total_contratos_vencidos > 0 || $total_facturas_sin_pago > 5 || $total_notificaciones_cierre > 0) {
            $badge_color = 'bg-danger';
        } elseif ($total_facturas_sin_pago > 0 || $total_contratos_proximos > 0) {
            $badge_color = 'bg-warning';
        } else {
            $badge_color = 'bg-info';
        }
    }
    
    ob_start();
    ?>
    <style>
    /* Pequeñas mejoras visuales */
    .notification-item {
        transition: all 0.2s ease;
    }
    .notification-item:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
    .notification-item .badge {
        transition: transform 0.2s ease;
    }
    .notification-item:hover .badge {
        transform: scale(1.1);
    }
    .dropdown-menu {
        border: 1px solid var(--win-border-color, #333) !important;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3) !important;
    }
    .dropdown-header {
        background-color: var(--win-bg-tertiary, #2a2a2a);
        border-bottom: 1px solid var(--win-border-color, #333);
    }
    /* Estilo para separador visual entre tipos de notificaciones */
    .cierre-section-divider {
        border-top: 2px dashed #dc3545;
        margin: 8px 0;
        opacity: 0.5;
    }
    </style>

    <div class="dropdown">
        <button class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <?php if ($total_notificaciones > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill <?= $badge_color ?>" style="font-size: 0.65rem;">
                <?= $total_notificaciones > 99 ? '99+' : $total_notificaciones ?>
            </span>
            <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 380px; max-height: 500px; overflow-y: auto;">
            <li>
                <h6 class="dropdown-header text-white py-2 d-flex justify-content-between align-items-center fw-bold">
                    <span>
                        <i class="fas fa-bell me-2"></i>Notificaciones del Sistema
                    </span>
                    <?php if ($total_notificaciones > 0): ?>
                    <span class="badge <?= $badge_color ?> rounded-pill"><?= $total_notificaciones ?></span>
                    <?php endif; ?>
                </h6>
            </li>
            
            <?php if ($total_notificaciones == 0): ?>
            <li>
                <div class="dropdown-item-text text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">No hay notificaciones</p>
                    <small>Todo está al día</small>
                </div>
            </li>
            <?php else: ?>
                
                <!-- =================================================== -->
                <!-- SECCIÓN: NOTIFICACIONES DE CIERRE CONTABLE -->
                <!-- =================================================== -->
                <?php if ($total_notificaciones_cierre > 0): ?>
                <li>
                    <div class="dropdown-item-text small text-danger fw-bold px-3 py-1" style="background-color: rgba(220, 53, 69, 0.2);">
                        <i class="fas fa-calendar-alt me-1"></i> CIERRES CONTABLES PENDIENTES (<?= $total_notificaciones_cierre ?>)
                    </div>
                </li>
                
                <!-- NOTIFICACIÓN DE CIERRE PENDIENTE POR FECHA (ROJO) -->
                <?php if ($hay_cierre_pendiente_fecha): ?>
                <li>
                    <a class="dropdown-item notification-item" href="cierre_mes.php" style="background-color: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
                        <div class="d-flex align-items-center">
                            <span class="me-2 text-danger"><i class="fas fa-exclamation-triangle fa-lg"></i></span>
                            <div class="flex-grow-1">
                                <span class="fw-bold text-danger d-block">CIERRE PENDIENTE:</span>
                                <span class="text-warning fw-bold d-block" style="font-size: 1.1rem;"><?= htmlspecialchars($cierre_pendiente_fecha['periodo']) ?></span>
                                <span class="text-success fw-bold d-block">YA PUEDE CERRARSE</span>
                                <small class="text-muted d-block mt-1">
                                    Último día: <?= $cierre_pendiente_fecha['ultimo_dia'] ?> | 
                                    Fecha actual: <?= $cierre_pendiente_fecha['fecha_actual'] ?>
                                </small>
                            </div>
                            <span class="badge bg-danger ms-2">PENDIENTE</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- NOTIFICACIÓN DE ÚLTIMO DÍA DEL MES (VERDE) -->
                <?php if ($es_ultimo_dia): ?>
                <li>
                    <a class="dropdown-item notification-item" href="cierre_mes.php" style="background-color: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745;">
                        <div class="d-flex align-items-center">
                            <span class="me-2 text-success"><i class="fas fa-calendar-check fa-lg"></i></span>
                            <div class="flex-grow-1">
                                <span class="fw-bold text-success d-block"><?= $ultimo_dia_info['mensaje'] ?></span>
                                <small class="text-muted">Haga clic para realizar cierre mensual</small>
                            </div>
                            <span class="badge bg-success ms-2">CERRAR</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- NOTIFICACIÓN DE CIERRE MENSUAL (por factura) - VERDE -->
                <?php if ($hay_cierre_mes): ?>
                <li>
                    <a class="dropdown-item notification-item" href="cierre_mes.php" style="background-color: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745;">
                        <div class="d-flex align-items-center">
                            <span class="me-2 text-success"><i class="fas fa-calendar-alt fa-lg"></i></span>
                            <div class="flex-grow-1">
                                <span class="fw-bold text-success d-block"><?= $mensaje_cierre_mes ?></span>
                            </div>
                            <span class="badge bg-success ms-2">CERRAR</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- NOTIFICACIÓN DE CIERRE ANUAL (AMARILLO) -->
                <?php if ($hay_cierre_anual): ?>
                <li>
                    <a class="dropdown-item notification-item" href="cierre_anual.php" style="background-color: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
                        <div class="d-flex align-items-center">
                            <span class="me-2 text-warning"><i class="fas fa-calendar-alt fa-lg"></i></span>
                            <div class="flex-grow-1">
                                <span class="fw-bold text-warning d-block"><?= $mensaje_cierre_anual ?></span>
                            </div>
                            <span class="badge bg-warning text-dark ms-2">CERRAR</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Separador entre secciones si hay notificaciones regulares -->
                <?php if ($total_facturas_pendientes > 0 || $total_facturas_sin_pago > 0 || $total_contratos_vencidos > 0 || $total_contratos_proximos > 0): ?>
                <li><hr class="dropdown-divider ciere-section-divider"></li>
                <?php endif; ?>
                
                <?php endif; ?>
                
                <!-- =================================================== -->
                <!-- SECCIÓN: NOTIFICACIONES REGULARES -->
                <!-- =================================================== -->
                <?php if ($total_facturas_pendientes > 0 || $total_facturas_sin_pago > 0 || $total_contratos_vencidos > 0 || $total_contratos_proximos > 0): ?>
                
                <?php if ($total_notificaciones_cierre > 0): ?>
                <li>
                    <div class="dropdown-item-text small text-primary fw-bold px-3 py-1" style="background-color: rgba(13, 110, 253, 0.1);">
                        <i class="fas fa-bell me-1"></i> OTRAS NOTIFICACIONES
                    </div>
                </li>
                <?php endif; ?>
                
                <!-- FACTURAS PENDIENTES -->
                <?php if ($total_facturas_pendientes > 0): ?>
                <li>
                    <a class="dropdown-item notification-item" href="facturas.php?pagina=1&registros=99999&estado=PENDIENTE">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-file-invoice text-primary me-2"></i>
                                Facturas pendientes
                            </span>
                            <span class="badge bg-primary rounded-pill"><?= $total_facturas_pendientes ?></span>
                        </div>
                        <small class="text-muted d-block ps-4">Facturas que no han sido contabilizadas</small>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- FACTURAS SIN PAGO -->
                <?php if ($total_facturas_sin_pago > 0): ?>
                <li>
                    <a class="dropdown-item notification-item" href="facturas.php?pagina=1&registros=99999&estado=SIN_REF">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                Facturas sin pago
                            </span>
                            <span class="badge bg-danger rounded-pill"><?= $total_facturas_sin_pago ?></span>
                        </div>
                        <small class="text-muted d-block ps-4">Sin referencia de pago</small>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- CONTRATOS VENCIDOS -->
                <?php if ($total_contratos_vencidos > 0): ?>
                <li>
                    <a class="dropdown-item notification-item" href="clientes.php?contract_status=vencido&pagina=1&orden=nombre&direccion=ASC&registros_por_pagina=todos">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-calendar-times text-danger me-2"></i>
                                Contratos vencidos
                            </span>
                            <span class="badge bg-danger rounded-pill"><?= $total_contratos_vencidos ?></span>
                        </div>
                        <small class="text-muted d-block ps-4">Contratos con fecha final vencida</small>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- CONTRATOS PRÓXIMOS A VENCER -->
                <?php if ($total_contratos_proximos > 0): ?>
                <li>
                    <a class="dropdown-item notification-item" href="clientes.php?contract_status=proximo&pagina=1&orden=nombre&direccion=ASC&registros_por_pagina=todos">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-clock text-warning me-2"></i>
                                Contratos Próximos a vencer
                            </span>
                            <span class="badge bg-warning rounded-pill text-dark"><?= $total_contratos_proximos ?></span>
                        </div>
                        <small class="text-muted d-block ps-4">Vencen en los próximos 30 días</small>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php endif; ?>
                
            <?php endif; ?>
            
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-center text-primary py-2" href="notificaciones.php">
                    <i class="fas fa-list me-2"></i>Ver todas las notificaciones
                </a>
            </li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Obtiene la fecha del último acceso exitoso del usuario
 * @param int $usuario_id ID del usuario
 * @return string Fecha formateada o mensaje descriptivo
 */
function obtenerUltimoAcceso($usuario_id) {
    if (!$usuario_id) {
        return 'No disponible';
    }
    
    try {
        $db = Database::getConnection();
        
        if (!$db) {
            error_log("Error: Conexión a BD no disponible en obtenerUltimoAcceso");
            return 'Error de conexión';
        }
        
        // Verificar si hay registros de login para este usuario
        $sql_verificar = "SELECT COUNT(*) as total FROM historico_operaciones 
                          WHERE usuario_id = :usuario_id 
                          AND operacion = 'LOGIN'";
        $stmt_verificar = $db->prepare($sql_verificar);
        $stmt_verificar->execute(['usuario_id' => $usuario_id]);
        $total_logins = (int)$stmt_verificar->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($total_logins == 0) {
            // No hay ningún login registrado
            return 'Primer acceso';
        }
        
        if ($total_logins == 1) {
            // Solo tiene el login actual, no hay accesos previos
            return 'Primer acceso';
        }
        
        // Tiene más de un login, obtener el penúltimo (el anterior al actual)
        $sql_ultimo_acceso = "SELECT fecha_hora 
                              FROM historico_operaciones 
                              WHERE usuario_id = :usuario_id 
                              AND operacion = 'LOGIN' 
                              ORDER BY fecha_hora DESC 
                              LIMIT 1, 1"; // El segundo registro (el penúltimo login)
        
        $stmt = $db->prepare($sql_ultimo_acceso);
        $stmt->execute(['usuario_id' => $usuario_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado && isset($resultado['fecha_hora'])) {
            $ultimo_acceso = $resultado['fecha_hora'];
            
            // Formatear la fecha para mostrarla
            $timestamp = strtotime($ultimo_acceso);
            if ($timestamp === false) {
                return 'Fecha inválida';
            }
            
            $fecha_formateada = date('d/m/Y', $timestamp);
            $hora_formateada = date('h:i:s A', $timestamp); // Formato 12h con AM/PM
            
            return $fecha_formateada . '-' . $hora_formateada;
        }
        
        return 'No disponible';
        
    } catch (Exception $e) {
        error_log("Error en obtenerUltimoAcceso: " . $e->getMessage());
        return 'Error en consulta';
    }
}
// =========================================================================
// VARIABLE GLOBAL PARA EL NOMBRE DEL USUARIO (CHATBOT)
// =========================================================================
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
?>

<script>
    // Variable global para el chatbot
    window.usuarioSISFACT = '<?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?>';
</script>