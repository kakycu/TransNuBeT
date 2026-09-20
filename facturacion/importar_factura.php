<?php
// importar_factura.php - Página para importar las facturas generadas por el capturador
session_start();
require_once 'config/database.php';
require_once 'config/header.php';

// Obtener tema del usuario
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';

// Obtener conexión a BD
$db = Database::getConnection();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener usuario actual y su rol desde la base de datos
$sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                FROM clasif_usuarios u
                LEFT JOIN clasif_rol r ON u.rol_id = r.id
                WHERE u.id = :id";
$stmt_usuario = $db->prepare($sql_usuario);
$stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

$usuario_id = (int)$usuario['id'];

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Actualizar la sesión con el rol correcto
$_SESSION['rol_id'] = $usuario['rol_id'];
$rol_id = (int)$usuario['rol_id'];

// Verificar permisos
$roles_permitidos = [1, 3, 4, 5];
if (!in_array($rol_id, $roles_permitidos)) {
    // Mostrar modal de acceso denegado con SweetAlert
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>SISFACT PDL VISIONES - Acceso Denegado</title>
        <link rel="icon" type="image/x-icon" href="assets/logov.png">
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <script src="js/sweetalert211.js"></script>
        <style>
            body { 
                background: <?php echo isset($tema_windows) && $tema_windows === 'dark' ? '#0f0f1a' : '#f0f2f5'; ?>; 
                margin: 0; 
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', sans-serif;
            }
        </style>
    </head>
    <body>
        <script>
        const darkTheme = Swal.mixin({
            background: "<?php echo isset($tema_windows) && $tema_windows === 'dark' ? '#1e1e2d' : '#ffffff'; ?>",
            color: "<?php echo isset($tema_windows) && $tema_windows === 'dark' ? '#e1e1e6' : '#1a1a2e'; ?>",
            confirmButtonColor: "<?php echo $color_accent ?? '#0078d4'; ?>",
            denyButtonColor: "#6c757d",
            cancelButtonColor: "#28a745",
            allowOutsideClick: false
        });

        darkTheme.fire({
            icon: "error",
            iconColor: "#dc3545",
            title: '<i class="fas fa-ban me-2"></i> Acceso Denegado',
            html: `
                <div style="text-align: left; padding: 10px 0;">
                    <div style="background: rgba(220, 53, 69, 0.15); padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; margin-bottom: 15px;">
                        <p style="margin-bottom: 10px; font-size: 1.1rem;">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>No tiene permisos para importar facturas</strong>
                        </p>
                        <p style="margin-bottom: 5px; color: <?php echo isset($tema_windows) && $tema_windows === 'dark' ? '#d0d0d0' : '#666'; ?>;">
                            Su rol actual no cuenta con los privilegios necesarios para acceder a esta función.
                        </p>
                    </div>
                    <div style="background: <?php echo isset($tema_windows) && $tema_windows === 'dark' ? '#2d2d44' : '#f8f9fa'; ?>; padding: 15px; border-radius: 8px;">
                        <p><strong>Permisos requeridos:</strong></p>
                        <ul style="margin-bottom: 0;">
                            <li><i class="fas fa-user-tie me-2"></i>Administrador</li>
                            <li><i class="fas fa-chart-line me-2"></i>Superv. Gral</li>
                            <li><i class="fas fa-calculator me-2"></i>Contabilidad</li>
                            <li><i class="fas fa-file-invoice me-2"></i>Facturación/Edición</li>
                        </ul>
                        <p class="mt-3 mb-0">
                            <small><i class="fas fa-info-circle me-1"></i>Contacte al administrador si necesita acceder a esta sección.</small>
                        </p>
                    </div>
                </div>
            `,
            width: "550px",
            showDenyButton: true,
            denyButtonText: '<i class="fas fa-arrow-left me-2"></i>Regresar',
            confirmButtonText: '<i class="fas fa-home me-2"></i>Ir al Dashboard',
            confirmButtonColor: "<?php echo $color_accent ?? '#0078d4'; ?>",
            denyButtonColor: "#6c757d",
            timer: 8000,
            timerProgressBar: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "dashboard.php";
            } else if (result.isDenied) {
                window.history.back();
            } else {
                window.location.href = "dashboard.php";
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit();
}



// ============================================
// VALIDACIÓN DE FECHAS OPERATIVAS
// ============================================
$mes_cierre_num = obtenerMesCierreOperaciones(); 
$anio_cierre_num = obtenerAnioCierreOperaciones();

// Verificar si Diciembre está cerrado
if ($mes_cierre_num == 12) {
    $sql_check_dic = "SELECT COUNT(*) as total FROM historico_cierres 
                      WHERE periodo_mes = 12 AND periodo_anio = :anio AND tipo = 1";
    $stmt_check = $db->prepare($sql_check_dic);
    $stmt_check->execute(['anio' => $anio_cierre_num]);
    $es_diciembre_cerrado = $stmt_check->fetch(PDO::FETCH_ASSOC)['total'] > 0;

    if ($es_diciembre_cerrado) {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>SISFACT PDL VISIONES - Periodo Cerrado</title>
            <link rel="icon" type="image/x-icon" href="assets/logov.png">
            <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
            <script src="js/sweetalert211.js"></script>
            <style>
                body { background: #0f0f1a; margin: 0; min-height: 100vh; }
            </style>
        </head>
        <body>
            <script>
            const darkTheme = Swal.mixin({
                background: "#1e1e2d",
                color: "#e1e1e6",
                confirmButtonColor: "#007bff",
                denyButtonColor: "#6c757d",
                cancelButtonColor: "#28a745",
                allowOutsideClick: false
            });

            darkTheme.fire({
                icon: "error",
                iconColor: "#dc3545",
                title: '<i class="fas fa-calendar-times me-2"></i>Periodo Cerrado',
                html: `
                    <div style="text-align: left; padding: 10px 0;">
                        <p style="margin-bottom: 15px; color: #d0d0d0;">
                            <i class="fas fa-triangle-exclamation me-2"></i>
                            El periodo contable ha sido finalizado.
                        </p>
                        <div style="background: #2d2d44; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
                            <p><strong>Periodo cerrado:</strong> Diciembre <?php echo $anio_cierre_num; ?></p>
                            <p><strong>No se pueden crear ni modificar facturas en este periodo.</strong></p>
                            <p><strong>Acciones disponibles:</strong></p>
                            <ul>
                                <li><i class="fas fa-calendar-check me-2"></i>Cierre Anual para abrir nuevo periodo</li>
                                <li><i class="fas fa-chart-bar me-2"></i>Consultar reportes del periodo</li>
                                <li><i class="fas fa-home me-2"></i>Volver al Dashboard</li>
                            </ul>
                        </div>
                    </div>`,
                width: "550px",
                showDenyButton: true,
                denyButtonText: '<i class="fas fa-chart-bar me-2"></i>Ver Reportes',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-home me-2"></i>Dashboard',
                confirmButtonText: '<i class="fas fa-calendar-check me-2"></i>Cierre Anual'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = "cierre_anual.php";
                else if (result.isDenied) window.location.href = "reportes.php";
                else window.location.href = "dashboard.php";
            });
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}

// Calcular fechas operativas
$primer_dia_operativo = sprintf("%04d-%02d-01", $anio_cierre_num, $mes_cierre_num);
$ultimo_dia_mes_operativo = cal_days_in_month(CAL_GREGORIAN, $mes_cierre_num, $anio_cierre_num);
$ultimo_dia_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $ultimo_dia_mes_operativo);
$hoy_operativo = date('Y-m-d');

// Ajustar hoy al último día del mes si es necesario
$dia_actual = (int)date('j');
$dia_final = min($dia_actual, $ultimo_dia_mes_operativo);
$hoy_operativo_ajustado = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_final);

// Nombres de meses
$meses_completos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_nombre = $meses_completos[$mes_cierre_num - 1];

// ============================================
// FUNCIONES PARA GENERACIÓN DE NÚMERO DE FACTURA
// ============================================

/**
 * Obtiene el último secuencial del año operativo actual
 * 
 * @param PDO $db Conexión a la base de datos
 * @param string $tipo Tipo de documento: 'FACTURA' u 'OFERTA'
 * @return int Último número secuencial (0 si no hay facturas)
 */
function obtenerUltimoSecuencial($db, $tipo = 'FACTURA') {
    $anio_operativo = obtenerAnioCierreOperaciones();
    $tipo_prefijo = ($tipo === 'OFERTA') ? 'OFV-' : 'FV-';
    
    $sql = "SELECT no_fact FROM tbl_fact 
            WHERE no_fact LIKE :prefijo 
            ORDER BY CAST(SUBSTRING(no_fact, -4) AS UNSIGNED) DESC 
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['prefijo' => $tipo_prefijo . $anio_operativo . '%']);
    $ultima = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ultima && !empty($ultima['no_fact'])) {
        return intval(substr($ultima['no_fact'], -4));
    }
    
    return 0;
}

/**
 * Genera el siguiente número de factura secuencial ANUAL
 * Misma lógica exacta que nueva_factura.php
 * 
 * @param PDO $db Conexión a la base de datos
 * @param string $fecha_emision Fecha de emisión en formato YYYY-MM-DD
 * @param string $tipo Tipo de documento: 'FACTURA' u 'OFERTA'
 * @return string Número de factura generado (Ej: FV-202604120015)
 * @throws Exception Si se excede el límite de 9999 facturas anuales
 */
function generarNumeroFacturaSecuencial($db, $fecha_emision, $tipo = 'FACTURA') {
    $anio_operativo = obtenerAnioCierreOperaciones();
    $tipo_prefijo = ($tipo === 'OFERTA') ? 'OFV-' : 'FV-';
    
    $ultimo_secuencial = obtenerUltimoSecuencial($db, $tipo);
    $nuevo_numero = $ultimo_secuencial + 1;
    
    if ($nuevo_numero > 9999) {
        throw new Exception("Límite de facturas anuales alcanzado (máximo 9999 facturas para el año {$anio_operativo})");
    }
    
    $numero_secuencial = str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);
    
    $timestamp = strtotime($fecha_emision);
    if ($timestamp === false) {
        throw new Exception("Fecha de emisión inválida: {$fecha_emision}");
    }
    $fecha_para_formato = date('Ymd', $timestamp);
    
    return $tipo_prefijo . $fecha_para_formato . $numero_secuencial;
}

/**
 * PREDICE el siguiente número de factura (para mostrar en el modal)
 * Esta función NO modifica la BD, solo calcula cuál sería el siguiente número
 * 
 * @param PDO $db Conexión a la base de datos
 * @param string $fecha_emision Fecha de emisión en formato YYYY-MM-DD
 * @param string $tipo Tipo de documento: 'FACTURA' u 'OFERTA'
 * @return array ['error' => false, 'numero' => 'FV-...', 'secuencial' => 16, 'anio' => 2026]
 */
function predecirSiguienteNumeroFactura($db, $fecha_emision, $tipo = 'FACTURA') {
    $anio_operativo = obtenerAnioCierreOperaciones();
    $tipo_prefijo = ($tipo === 'OFERTA') ? 'OFV-' : 'FV-';
    
    $ultimo_secuencial = obtenerUltimoSecuencial($db, $tipo);
    $siguiente_secuencial = $ultimo_secuencial + 1;
    
    if ($siguiente_secuencial > 9999) {
        return [
            'error' => true, 
            'message' => "Límite de facturas anuales alcanzado (máximo 9999) para el año {$anio_operativo}"
        ];
    }
    
    $numero_secuencial = str_pad($siguiente_secuencial, 4, '0', STR_PAD_LEFT);
    
    $timestamp = strtotime($fecha_emision);
    if ($timestamp === false) {
        return ['error' => true, 'message' => "Fecha de emisión inválida: {$fecha_emision}"];
    }
    $fecha_para_formato = date('Ymd', $timestamp);
    
    return [
        'error' => false,
        'numero' => $tipo_prefijo . $fecha_para_formato . $numero_secuencial,
        'secuencial' => $siguiente_secuencial,
        'anio' => $anio_operativo,
        'prefijo' => $tipo_prefijo
    ];
}

/**
 * Clase para manejar el secuencial de facturas durante una importación por lotes
 * Mantiene el secuencial en memoria para no consultar la BD en cada factura
 */
class SecuencialFacturasImportacion {
    private $db;
    private $anio_operativo;
    private $tipo_prefijo;
    private $ultimo_secuencial = null;
    
    public function __construct($db, $tipo = 'FACTURA') {
        $this->db = $db;
        $this->anio_operativo = obtenerAnioCierreOperaciones();
        $this->tipo_prefijo = ($tipo === 'OFERTA') ? 'OFV-' : 'FV-';
        $this->inicializarUltimoSecuencial();
    }
    
    /**
     * Obtiene el último secuencial de la base de datos
     */
    private function inicializarUltimoSecuencial() {
        $this->ultimo_secuencial = obtenerUltimoSecuencial($this->db);
    }
    
    /**
     * Genera el siguiente número de factura
     * 
     * @param string $fecha_emision Fecha en formato YYYY-MM-DD
     * @return string Número de factura completo
     * @throws Exception Si se excede el límite
     */
    public function generarSiguienteNumero($fecha_emision) {
        $this->ultimo_secuencial++;
        
        if ($this->ultimo_secuencial > 9999) {
            throw new Exception("Límite de facturas anuales alcanzado (máximo 9999) para el año {$this->anio_operativo}");
        }
        
        $secuencial = str_pad($this->ultimo_secuencial, 4, '0', STR_PAD_LEFT);
        $timestamp = strtotime($fecha_emision);
        $fecha_formato = date('Ymd', $timestamp);
        
        return $this->tipo_prefijo . $fecha_formato . $secuencial;
    }
    
    /**
     * Obtiene el último secuencial generado (sin incrementar)
     */
    public function getUltimoSecuencial() {
        return $this->ultimo_secuencial;
    }
    
    /**
     * Verifica si hay espacio para más facturas
     */
    public function hayEspacioDisponible($cantidad_necesaria = 1) {
        return ($this->ultimo_secuencial + $cantidad_necesaria) <= 9999;
    }
}

// ============================================
// CLASE IMPORTADOR FACTURAS
// ============================================

class ImportadorFacturas {
    private $pdo;
    private $usuario_id;
    private $errores = [];
    private $facturas_procesadas = 0;
    private $detalles_agregados = 0;
    private $fecha_inicio;
    private $fecha_fin;
    private $generadorSecuencial;
    
    public function __construct($pdo, $usuario_id, $fecha_inicio, $fecha_fin) {
        $this->pdo = $pdo;
        $this->usuario_id = $usuario_id;
        $this->fecha_inicio = $fecha_inicio;
        $this->fecha_fin = $fecha_fin;
        
        // Inicializar el generador de secuenciales UNA SOLA VEZ para toda la importación
        $this->generadorSecuencial = new SecuencialFacturasImportacion($pdo, 'FACTURA');
    }
    
    /**
     * Valida si una fecha está dentro del período operativo
     */
    public function validarFechaOperativa($fecha) {
        if (!$fecha) return false;
        
        // Extraer solo la fecha (sin hora)
        $fecha_obj = $fecha;
        if (strpos($fecha, ' ') !== false) {
            $fecha_obj = explode(' ', $fecha)[0];
        }
        
        return ($fecha_obj >= $this->fecha_inicio && $fecha_obj <= $this->fecha_fin);
    }
    
    /**
     * Procesa un archivo SQL y extrae las facturas
     */
    public function procesarSQL($archivo_sql) {
        $contenido = file_get_contents($archivo_sql);
        return $this->parsearSQL($contenido);
    }
    
    /**
     * Parsea el contenido SQL y extrae las facturas con sus detalles
     */
    public function parsearSQL($contenido) {
        $facturas = [];
        
        // Normalizar el contenido
        $contenido = $this->normalizarSQL($contenido);
        
        // Extraer todas las facturas (cabeceras)
        preg_match_all('/--\s*FACTURA\s*(\d+):\s*([^\n]+)\s*INSERT\s+INTO\s+tbl_fact\s*\([^)]+\)\s*VALUES\s*\(([^;]+)\);/is', $contenido, $matches_facturas, PREG_SET_ORDER);
        
        $facturas_temp = [];
        foreach ($matches_facturas as $match) {
            $temp_id = $match[1];
            $cliente_nombre = trim($match[2]);
            $valores_str = $match[3];
            
            $datos = $this->parsearValoresFactura($valores_str);
            $datos['temp_id'] = $temp_id;
            $datos['cliente_nombre'] = $cliente_nombre;
            $datos['detalles'] = [];
            
            $facturas_temp[$temp_id] = $datos;
        }
        
        // Extraer todos los detalles (después de -- DETALLES)
        if (preg_match('/--\s*DETALLES\s*(.*?)(?:--\s*FIN|$)/is', $contenido, $match_detalles)) {
            $bloque_detalles = $match_detalles[1];
            
            preg_match_all('/INSERT\s+INTO\s+tbl_fact_detalle\s*\([^)]+\)\s*VALUES\s*\(\s*@fid_(\d+)\s*,\s*([^;]+)\);/i', $bloque_detalles, $matches_detalles, PREG_SET_ORDER);
            
            foreach ($matches_detalles as $match) {
                $fid = $match[1];
                $valores_str = $match[2];
                
                $detalle = $this->parsearValoresDetalle($valores_str);
                if ($detalle && isset($facturas_temp[$fid])) {
                    $facturas_temp[$fid]['detalles'][] = $detalle;
                }
            }
        }
        
        return array_values($facturas_temp);
    }
    
    /**
     * Normaliza el contenido SQL (une líneas partidas)
     */
    private function normalizarSQL($contenido) {
        $lineas = explode("\n", $contenido);
        $resultado = [];
        $buffer = '';
        $en_insert = false;
        
        foreach ($lineas as $linea) {
            $linea_trim = trim($linea);
            
            if (preg_match('/^INSERT\s+INTO/i', $linea_trim)) {
                if ($buffer) $resultado[] = $buffer;
                $buffer = $linea_trim;
                $en_insert = true;
            } elseif ($en_insert) {
                $buffer .= ' ' . $linea_trim;
                if (strpos($linea_trim, ';') !== false) {
                    $resultado[] = $buffer;
                    $buffer = '';
                    $en_insert = false;
                }
            } else {
                if ($buffer) {
                    $resultado[] = $buffer;
                    $buffer = '';
                }
                $resultado[] = $linea;
            }
        }
        
        if ($buffer) $resultado[] = $buffer;
        return implode("\n", $resultado);
    }
    
    /**
     * Divide una cadena de valores SQL respetando comillas
     */
    private function splitValoresSQL($str) {
        $valores = [];
        $actual = '';
        $en_comillas = false;
        $escapado = false;
        
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            
            if ($char === '\\' && !$escapado) {
                $escapado = true;
                $actual .= $char;
                continue;
            }
            
            if ($char === "'" && !$escapado) {
                $en_comillas = !$en_comillas;
                $actual .= $char;
                continue;
            }
            
            if ($char === ',' && !$en_comillas) {
                $valores[] = trim($actual);
                $actual = '';
            } else {
                $actual .= $char;
            }
            
            $escapado = false;
        }
        
        if ($actual !== '') $valores[] = trim($actual);
        return $valores;
    }
    
    /**
     * Parsea los valores de una factura desde SQL
     */
    private function parsearValoresFactura($valores_str) {
        $valores = $this->splitValoresSQL($valores_str);
        $campos = ['cliente_id', 'tipo_pago_id', 'subtotal', 'total_general', 'estado', 'fecha_emision', 'observaciones'];
        $resultado = [];
        
        foreach ($campos as $i => $campo) {
            if (isset($valores[$i])) {
                $valor = $valores[$i];
                
                if (strtoupper($valor) === 'NULL') {
                    $resultado[$campo] = null;
                } elseif (preg_match("/^'(.*)'$/s", $valor, $m)) {
                    $texto = $m[1];
                    
                    // PARA OBSERVACIONES: NO limpiar nada, mantener el texto original
                    if ($campo === 'observaciones') {
                        // Solo reemplazar saltos de línea por espacios
                        $texto = preg_replace('/\s+/', ' ', $texto);
                    }
                    
                    $resultado[$campo] = $texto;
                } elseif (is_numeric($valor)) {
                    $resultado[$campo] = strpos($valor, '.') !== false ? floatval($valor) : intval($valor);
                } else {
                    $resultado[$campo] = $valor;
                }
            }
        }
        
        return $resultado;
    }
    
    /**
     * Parsea los valores de un detalle desde SQL
     */
    private function parsearValoresDetalle($valores_str) {
        $valores = $this->splitValoresSQL($valores_str);
        $campos = ['servicio_id', 'cantidad', 'precio_unitario', 'total_linea'];
        $resultado = [];
        
        foreach ($campos as $i => $campo) {
            if (isset($valores[$i])) {
                $valor = $valores[$i];
                
                if (strtoupper($valor) === 'NULL') {
                    $resultado[$campo] = null;
                } elseif (preg_match("/^'(.*)'$/s", $valor, $m)) {
                    $resultado[$campo] = $m[1];
                } elseif (is_numeric($valor)) {
                    $resultado[$campo] = strpos($valor, '.') !== false ? floatval($valor) : intval($valor);
                } else {
                    $resultado[$campo] = $valor;
                }
            }
        }
        
        return $resultado;
    }
    
    /**
     * Busca facturas existentes para un cliente DENTRO DEL PERÍODO OPERATIVO
     * SOLO facturas en estado EDITABLE (NO CONTABILIZADA, ANULADA, PAGADA, CERRADA)
     */
    public function buscarFacturasExistentes($cliente_id) {
        if (!$cliente_id) return [];
        
        // Estados NO EDITABLES (no se pueden modificar)
        $estados_no_editables = ['CONTABILIZADA', 'ANULADA', 'PAGADA', 'CERRADA'];
        $placeholders = implode(',', array_fill(0, count($estados_no_editables), '?'));
        
        $stmt = $this->pdo->prepare("
            SELECT f.*, COUNT(fd.id) as total_detalles,
                   c.nombre as cliente_nombre, c.ContratoNo as contrato,
                   DATE(f.fecha_emision) as fecha_solo
            FROM tbl_fact f
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            LEFT JOIN tbl_fact_detalle fd ON f.id = fd.factura_id
            WHERE f.cliente_id = ? 
            AND DATE(f.fecha_emision) BETWEEN ? AND ?
            AND f.estado NOT IN ($placeholders)
            GROUP BY f.id
            ORDER BY f.fecha_emision DESC, f.id DESC
        ");
        
        $params = array_merge([$cliente_id, $this->fecha_inicio, $this->fecha_fin], $estados_no_editables);
        $stmt->execute($params);
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($facturas as &$factura) {
            $stmt_detalles = $this->pdo->prepare("
                SELECT fd.servicio_id, fd.cantidad, fd.precio_unitario, fd.total_linea,
                       s.codigo, s.descripcion
                FROM tbl_fact_detalle fd
                LEFT JOIN clasif_serv s ON fd.servicio_id = s.id
                WHERE fd.factura_id = ?
            ");
            $stmt_detalles->execute([$factura['id']]);
            $factura['servicios_existentes'] = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $facturas;
    }
    
    /**
     * Crea una nueva factura - USA EL SECUENCIAL ANUAL
     */
    public function crearNuevaFactura($factura_temp) {
        try {
            if (!$this->validarFechaOperativa($factura_temp['fecha_emision'])) {
                return [
                    'success' => false,
                    'message' => "La fecha de emisión está fuera del período operativo ({$this->fecha_inicio} al {$this->fecha_fin})"
                ];
            }
            
            $this->pdo->beginTransaction();
            
            // ===== USAR EL GENERADOR SECUENCIAL ANUAL =====
            $no_fact = $this->generadorSecuencial->generarSiguienteNumero($factura_temp['fecha_emision']);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_fact (
                    no_fact, cliente_id, tipo_pago_id, subtotal, 
                    total_general, usuario_id, estado, fecha_emision, 
                    observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $no_fact,
                $factura_temp['cliente_id'],
                $factura_temp['tipo_pago_id'] ?? null,
                $factura_temp['subtotal'] ?? 0,
                $factura_temp['total_general'] ?? 0,
                $this->usuario_id,
                $factura_temp['estado'] ?? 'PENDIENTE',
                $factura_temp['fecha_emision'] ?? date('Y-m-d H:i:s'),
                $factura_temp['observaciones'] ?? null
            ]);
            
            $factura_id = $this->pdo->lastInsertId();
            
            if (!empty($factura_temp['detalles'])) {
                $this->insertarDetalles($factura_id, $factura_temp['detalles']);
            }
            
            $this->pdo->commit();
            $this->facturas_procesadas++;
            
            return [
                'success' => true,
                'message' => "Factura #$no_fact creada exitosamente",
                'factura_id' => $factura_id,
                'no_fact' => $no_fact
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => "Error: " . $e->getMessage()];
        }
    }
    
    /**
     * Agrega detalles a una factura existente 
     * - SUMA CANTIDADES si el servicio ya existe
     * - AGREGA NUEVO SERVICIO si no existe
     */
    public function agregarAFacturaExistente($factura_id, $factura_temp) {
        try {
            $stmt_check = $this->pdo->prepare("SELECT id, no_fact, estado, fecha_emision, subtotal, total_general FROM tbl_fact WHERE id = ?");
            $stmt_check->execute([$factura_id]);
            $factura_destino = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$factura_destino) {
                return ['success' => false, 'message' => "Factura destino no encontrada"];
            }
            
            if (in_array($factura_destino['estado'], ['CERRADA', 'ANULADA'])) {
                return ['success' => false, 'message' => "No se puede modificar una factura {$factura_destino['estado']}"];
            }
            
            if (!$this->validarFechaOperativa($factura_destino['fecha_emision'])) {
                return ['success' => false, 'message' => "La fecha de la factura destino está fuera del período operativo"];
            }
            
            $this->pdo->beginTransaction();
            
            $total_agregado = 0;
            $servicios_nuevos = 0;
            $servicios_actualizados = 0;
            $detalles_procesados = [];
            
            $stmt_existentes = $this->pdo->prepare("
                SELECT id, servicio_id, cantidad, precio_unitario, total_linea 
                FROM tbl_fact_detalle 
                WHERE factura_id = ?
            ");
            $stmt_existentes->execute([$factura_id]);
            $servicios_existentes = [];
            while ($row = $stmt_existentes->fetch(PDO::FETCH_ASSOC)) {
                $servicios_existentes[$row['servicio_id']] = $row;
            }
            
            foreach ($factura_temp['detalles'] as $detalle) {
                $servicio_id = $detalle['servicio_id'] ?? null;
                if (!$servicio_id) continue;
                
                $cantidad_nueva = intval($detalle['cantidad'] ?? 1);
                $precio_unitario = floatval($detalle['precio_unitario'] ?? 0);
                $total_linea_nuevo = $cantidad_nueva * $precio_unitario;
                
                if (isset($servicios_existentes[$servicio_id])) {
                    $existente = $servicios_existentes[$servicio_id];
                    
                    $nueva_cantidad = $existente['cantidad'] + $cantidad_nueva;
                    $nuevo_total = $nueva_cantidad * $precio_unitario;
                    
                    $stmt_update = $this->pdo->prepare("
                        UPDATE tbl_fact_detalle 
                        SET cantidad = ?, total_linea = ?, precio_unitario = ?
                        WHERE id = ?
                    ");
                    $stmt_update->execute([$nueva_cantidad, $nuevo_total, $precio_unitario, $existente['id']]);
                    
                    $servicios_existentes[$servicio_id]['cantidad'] = $nueva_cantidad;
                    $servicios_existentes[$servicio_id]['total_linea'] = $nuevo_total;
                    $servicios_existentes[$servicio_id]['precio_unitario'] = $precio_unitario;
                    
                    $total_agregado += $total_linea_nuevo;
                    $servicios_actualizados++;
                    
                    $detalles_procesados[] = [
                        'servicio_id' => $servicio_id,
                        'accion' => 'actualizado',
                        'cantidad_anterior' => $existente['cantidad'],
                        'cantidad_nueva' => $nueva_cantidad
                    ];
                    
                } else {
                    $stmt_insert = $this->pdo->prepare("
                        INSERT INTO tbl_fact_detalle (factura_id, servicio_id, cantidad, precio_unitario, total_linea)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt_insert->execute([$factura_id, $servicio_id, $cantidad_nueva, $precio_unitario, $total_linea_nuevo]);
                    
                    $servicios_existentes[$servicio_id] = [
                        'id' => $this->pdo->lastInsertId(),
                        'servicio_id' => $servicio_id,
                        'cantidad' => $cantidad_nueva,
                        'precio_unitario' => $precio_unitario,
                        'total_linea' => $total_linea_nuevo
                    ];
                    
                    $total_agregado += $total_linea_nuevo;
                    $servicios_nuevos++;
                    
                    $detalles_procesados[] = [
                        'servicio_id' => $servicio_id,
                        'accion' => 'insertado',
                        'cantidad' => $cantidad_nueva
                    ];
                }
            }
            
            $stmt_recalcular = $this->pdo->prepare("SELECT SUM(total_linea) as total_calculado FROM tbl_fact_detalle WHERE factura_id = ?");
            $stmt_recalcular->execute([$factura_id]);
            $total_recalculado = floatval($stmt_recalcular->fetch(PDO::FETCH_ASSOC)['total_calculado'] ?? 0);
            
            // Verificar si hay observaciones nuevas para concatenar
            $nueva_observacion = $factura_temp['observaciones'] ?? '';
            if (!empty($nueva_observacion)) {
                $stmt_obs = $this->pdo->prepare("SELECT observaciones FROM tbl_fact WHERE id = ?");
                $stmt_obs->execute([$factura_id]);
                $obs_actual = $stmt_obs->fetchColumn();
                
                $observacion_final = trim($obs_actual);
                if (!empty($observacion_final)) {
                    $observacion_final .= ' ' . $nueva_observacion;
                } else {
                    $observacion_final = $nueva_observacion;
                }
                
                $stmt_update_factura = $this->pdo->prepare("UPDATE tbl_fact SET subtotal = ?, total_general = ?, observaciones = ? WHERE id = ?");
                $stmt_update_factura->execute([$total_recalculado, $total_recalculado, $observacion_final, $factura_id]);
            } else {
                $stmt_update_factura = $this->pdo->prepare("UPDATE tbl_fact SET subtotal = ?, total_general = ? WHERE id = ?");
                $stmt_update_factura->execute([$total_recalculado, $total_recalculado, $factura_id]);
            }
            
            $this->registrarHistorico(
                "IMPORTACIÓN", 
                "Se importaron servicios a factura #{$factura_destino['no_fact']}. " .
                "Nuevos: $servicios_nuevos, Actualizados: $servicios_actualizados. " .
                "Total agregado: $" . number_format($total_agregado, 2)
            );
            
            $this->pdo->commit();
            $this->facturas_procesadas++;
            
            $mensaje = "Factura #{$factura_destino['no_fact']} actualizada. ";
            if ($servicios_nuevos > 0) {
                $mensaje .= "$servicios_nuevos servicio(s) nuevo(s) agregado(s). ";
            }
            if ($servicios_actualizados > 0) {
                $mensaje .= "$servicios_actualizados servicio(s) actualizado(s) (cantidades sumadas). ";
            }
            $mensaje .= "Nuevo total: $" . number_format($total_recalculado, 2);
            
            return [
                'success' => true,
                'message' => $mensaje,
                'factura_id' => $factura_id,
                'no_fact' => $factura_destino['no_fact'],
                'nuevos' => $servicios_nuevos,
                'actualizados' => $servicios_actualizados,
                'total_agregado' => $total_agregado,
                'total_final' => $total_recalculado,
                'detalles' => $detalles_procesados
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error en agregarAFacturaExistente: " . $e->getMessage());
            return ['success' => false, 'message' => "Error: " . $e->getMessage()];
        }
    }

    /**
     * Registra una operación en el histórico
     */
    private function registrarHistorico($operacion, $descripcion) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $operacion,
                $descripcion,
                $this->usuario_id,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            error_log("Error registrando histórico: " . $e->getMessage());
        }
    }
    
    /**
     * Inserta los detalles de una factura
     */
    private function insertarDetalles($factura_id, $detalles) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_fact_detalle (factura_id, servicio_id, cantidad, precio_unitario, total_linea)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($detalles as $detalle) {
            $servicio_id = $detalle['servicio_id'] ?? null;
            if (!$servicio_id) continue;
            
            $stmt->execute([
                $factura_id,
                $servicio_id,
                $detalle['cantidad'] ?? 1,
                $detalle['precio_unitario'] ?? 0,
                $detalle['total_linea'] ?? 0
            ]);
            $this->detalles_agregados++;
        }
    }
    
    /**
     * Obtiene información de un cliente por ID
     */
    public function getClienteInfo($cliente_id) {
        if (!$cliente_id) return null;
        
        $stmt = $this->pdo->prepare("
            SELECT id, codigo, nombre, ContratoNo, telefono, email 
            FROM clasif_clientes WHERE id = ?
        ");
        $stmt->execute([$cliente_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene información de un servicio por ID
     */
    public function getServicioInfo($servicio_id) {
        if (!$servicio_id) return null;
        
        $stmt = $this->pdo->prepare("
            SELECT id, codigo, descripcion, costo 
            FROM clasif_serv WHERE id = ?
        ");
        $stmt->execute([$servicio_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene el número de facturas procesadas
     */
    public function getFacturasProcesadas() {
        return $this->facturas_procesadas;
    }
    
    /**
     * Obtiene el número de detalles agregados
     */
    public function getDetallesAgregados() {
        return $this->detalles_agregados;
    }
    
    /**
     * Obtiene los errores ocurridos
     */
    public function getErrores() {
        return $this->errores;
    }
    
    /**
     * Busca facturas NO EDITABLES para mostrar advertencia
     */
    public function buscarFacturasNoEditables($cliente_id) {
        if (!$cliente_id) return [];
        
        $estados_no_editables = ['CONTABILIZADA', 'ANULADA', 'PAGADA', 'CERRADA'];
        $placeholders = implode(',', array_fill(0, count($estados_no_editables), '?'));
        
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.no_fact, f.estado, f.fecha_emision, f.total_general
            FROM tbl_fact f
            WHERE f.cliente_id = ? 
            AND DATE(f.fecha_emision) BETWEEN ? AND ?
            AND f.estado IN ($placeholders)
            ORDER BY f.fecha_emision DESC
        ");
        
        $params = array_merge([$cliente_id, $this->fecha_inicio, $this->fecha_fin], $estados_no_editables);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ============================================
// PROCESAMIENTO DEL FORMULARIO
// ============================================

// Variables de estado
$facturas_extraidas = [];
$modo_procesamiento = false;
$resultados = [];
$error = '';
$advertencias_fecha = [];
$archivo_hash = '';
$archivo_nombre = '';

// Obtener el último secuencial para pasarlo a JavaScript
$ultimo_secuencial_actual = obtenerUltimoSecuencial($db);
$siguiente_secuencial = $ultimo_secuencial_actual + 1;
$numero_factura_ejemplo = predecirSiguienteNumeroFactura($db, $hoy_operativo_ajustado);

// Procesar archivo subido
if (isset($_FILES['archivo_sql']) && $_FILES['archivo_sql']['error'] === UPLOAD_ERR_OK) {
    // Validar extensión del archivo
    $nombre_archivo = $_FILES['archivo_sql']['name'];
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['sql', 'txt'])) {
        $error = "El archivo '$nombre_archivo' tiene extensión .$extension. Solo se permiten archivos .sql o .txt generados por el Capturador de Facturas.";
    } else {
        $importador = new ImportadorFacturas($db, $usuario_id, $primer_dia_operativo, $ultimo_dia_operativo);
        $facturas_extraidas = $importador->procesarSQL($_FILES['archivo_sql']['tmp_name']);
        
        foreach ($facturas_extraidas as $factura) {
            $fecha_factura = $factura['fecha_emision'] ?? '';
            if ($fecha_factura && !$importador->validarFechaOperativa($fecha_factura)) {
                $fecha_mostrar = $fecha_factura;
                if (strpos($fecha_factura, ' ') !== false) {
                    $fecha_mostrar = explode(' ', $fecha_factura)[0];
                }
                if (strpos($fecha_mostrar, '-') !== false) {
                    $fecha_mostrar = date('d/m/Y', strtotime($fecha_mostrar));
                } elseif (strpos($fecha_mostrar, '/') === false) {
                    $fecha_mostrar = date('d/m/Y', strtotime($fecha_mostrar));
                }
                $advertencias_fecha[] = "Factura del cliente: <strong class='text-warning fw-bold'>'{$factura['cliente_nombre']}'</strong> tiene fecha: <strong class='fw-bold text-warning'>{$fecha_mostrar}</strong> (fuera del período operativo)";
            }
        }
        
        if (empty($facturas_extraidas)) {
            $contenido_archivo = file_get_contents($_FILES['archivo_sql']['tmp_name']);
            $tiene_insert_fact = (stripos($contenido_archivo, 'INSERT INTO tbl_fact') !== false);
            $tiene_factura_comentario = (stripos($contenido_archivo, '-- FACTURA') !== false);
            
            if (!$tiene_insert_fact && !$tiene_factura_comentario) {
                $error = "El archivo no contiene facturas en el formato esperado. Asegúrate de que sea un archivo generado por el Capturador de Facturas.";
            } else {
                $error = "No se encontraron facturas válidas en el archivo a Importar.";
            }
        } else {
            $modo_procesamiento = true;
            // Calcular hash del archivo para localStorage
            $archivo_hash = hash_file('sha256', $_FILES['archivo_sql']['tmp_name']);
            $archivo_nombre = $_FILES['archivo_sql']['name'];
            $_SESSION['import_archivo_hash'] = $archivo_hash;
            $_SESSION['import_archivo_nombre'] = $archivo_nombre;
        }
    }
}

// Procesar acciones individuales (Crear nueva o Agregar a existente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_factura'])) {
    $importador = new ImportadorFacturas($db, $usuario_id, $primer_dia_operativo, $ultimo_dia_operativo);
    $factura_temp = json_decode($_POST['factura_data'], true);
    $accion = $_POST['accion_factura'];
    $factura_temp_id = $_POST['factura_temp_id'] ?? '';
    
    // Obtener hash del archivo actual (de la sesión actual)
    $archivo_hash_actual = $_SESSION['import_archivo_hash'] ?? '';
    
    // Actualizar observaciones si se modificaron en el formulario
    if (isset($_POST['observaciones_modificadas'])) {
        $factura_temp['observaciones'] = $_POST['observaciones_modificadas'];
    }
    
    $resultado = null;
    
    if ($accion === 'nueva') {
        $resultado = $importador->crearNuevaFactura($factura_temp);
        $resultados[] = $resultado;
    } elseif ($accion === 'agregar' && isset($_POST['factura_existente_id'])) {
        $resultado = $importador->agregarAFacturaExistente($_POST['factura_existente_id'], $factura_temp);
        $resultados[] = $resultado;
    }
    
    // No se guarda en BD el estado, solo se enviará a JS para localStorage en la respuesta.
    // La respuesta incluirá el factura_temp_id para que el frontend lo marque como procesado.
}

// ============================================
// CONFIGURACIÓN DE TEMA
// ============================================
$temas_windows = [
    'dark' => [
        'bg_primary' => '#0d0d0d',
        'bg_secondary' => '#1a1a1a',
        'bg_tertiary' => '#2a2a2a',
        'text_primary' => '#ffffff',
        'text_secondary' => '#d0d0d0',
        'border_color' => '#4a4a4a'
    ],
    'light' => [
        'bg_primary' => '#f3f3f3',
        'bg_secondary' => '#ffffff',
        'bg_tertiary' => '#fafafa',
        'text_primary' => '#000000',
        'text_secondary' => '#555555',
        'border_color' => '#cccccc'
    ]
];
$tema_actual = $temas_windows[$tema_windows] ?? $temas_windows['dark'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Facturas - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <script src="js/sweetalert211.js"></script>
    
    <!-- MDTimePicker -->
    <link rel="stylesheet" href="css/mdtimepicker.css">
    <script src="js/mdtimepicker.min.js"></script>

    <!-- Chatbot -->
    <link rel="stylesheet" href="css/chatbot.css">
    <script src="js/chatbot.js"></script>
    
    <style>
        :root {
            --win-bg-primary: <?php echo $tema_actual['bg_primary']; ?>;
            --win-bg-secondary: <?php echo $tema_actual['bg_secondary']; ?>;
            --win-bg-tertiary: <?php echo $tema_actual['bg_tertiary']; ?>;
            --win-text-primary: <?php echo $tema_actual['text_primary']; ?>;
            --win-text-secondary: <?php echo $tema_actual['text_secondary']; ?>;
            --win-border-color: <?php echo $tema_actual['border_color']; ?>;
            --win-accent: <?php echo $color_accent; ?>;
			<!-- Nuevo: conversión de hex a rgb -->
			--win-accent-rgb: <?php 
				// Convertir el color hex (ej: #0078d4) a rgb
				$hex = ltrim($color_accent, '#');
				$r = hexdec(substr($hex, 0, 2));
				$g = hexdec(substr($hex, 2, 2));
				$b = hexdec(substr($hex, 4, 2));
				echo "$r, $g, $b";
			?>;
            --win-accent-light: <?php echo $color_accent; ?>20;
            --win-radius: 8px;
			--win-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
			--win-radius: 8px;
			--win-radius-sm: 6px;
			--win-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            background: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }
        
        .main-container { max-width: 1400px; margin: 0 auto; }
        
        .card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 15px 20px;
            border-radius: var(--win-radius) var(--win-radius) 0 0;
        }
        
        .card-body { padding: 20px; }
        
        .factura-card {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }
        
        .factura-card.procesada {
            opacity: 0.85;
            border-left: 4px solid #28a745;
        }
        
        
        /* TABLAS VISIBLES EN MODO OSCURO */
        .table {
            color: var(--win-text-primary) !important;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background: #3a3a5a !important;
            color: #ffffff !important;
            font-weight: 600;
            border-color: #5a5a7a !important;
            border-bottom: 2px solid var(--win-accent) !important;
            padding: 10px 8px !important;
        }
        
        .table td {
            background: transparent !important;
            border-color: #5a5a7a !important;
            color: #f0f0f0 !important;
            padding: 8px !important;
        }
        
        .table tbody tr:hover {
            background: rgba(0, 120, 212, 0.15) !important;
        }
        
        .table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.03);
        }
        
        [data-theme="light"] .table th {
            background: #e9ecef !important;
            color: #000000 !important;
            border-color: #dee2e6 !important;
            border-bottom: 2px solid var(--win-accent) !important;
        }
        
        [data-theme="light"] .table td {
            border-color: #dee2e6 !important;
            color: #000000 !important;
        }
        
        [data-theme="light"] .table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .btn-primary { background: var(--win-accent); border-color: var(--win-accent); }
        .btn-primary:hover { filter: brightness(1.1); }
        .btn-success { background: #198754; border-color: #198754; }
        .btn-outline-primary { color: var(--win-accent); border-color: var(--win-accent); }
        .btn-outline-secondary { background: transparent; border: 1px solid var(--win-border-color); color: var(--win-text-primary); }
        .btn-outline-danger { background: transparent; border: 1px solid #dc3545; color: #dc3545; }
        
        .text-secondary-custom { color: var(--win-text-secondary) !important; }
        .text-warning { color: #ffc107 !important; font-weight: 500; }
        .text-success { color: #28a745 !important; }
        
        .badge.bg-info { background-color: #17a2b8 !important; color: #fff !important; }
        .badge.bg-success { background-color: #28a745 !important; color: #fff !important; }
        .badge-success { background: #198754; color: white; padding: 5px 10px; border-radius: 20px; }
        
        .alert-info { background: rgba(23, 162, 184, 0.2) !important; border: 1px solid rgba(23, 162, 184, 0.4) !important; color: #e0f7fa !important; }
        .alert-warning { background: rgba(255, 193, 7, 0.2) !important; border: 1px solid rgba(255, 193, 7, 0.4) !important; color: #ffe69c !important; }
        .alert-success { background: rgba(40, 167, 69, 0.2) !important; border: 1px solid rgba(40, 167, 69, 0.4) !important; color: #c3e6cb !important; }
        
        .form-control { background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); color: var(--win-text-primary); }
        .form-label { color: var(--win-text-primary) !important; font-weight: 500; }
        h1, h2, h3, h4, h5, h6 { color: var(--win-text-primary) !important; }
        
        .existente-info {
            background: rgba(0, 120, 212, 0.15);
            border-left: 3px solid var(--win-accent);
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .numero-factura-badge {
            font-family: 'arial', monospace;
            font-size: 0.8rem;
            font-weight: bold;
            background: var(--win-accent);
            padding: 4px 12px;
            border-radius: 6px;
            color: yellow;
            border: 1px solid var(--win-accent);
        }
/* ==================== QUICK ACTIONS PARA IMPORTAR ==================== */
.import-quick-actions {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}
/* Contenedor de botones fijos */
.import-quick-actions-fixed {
    display: flex;
    gap: 12px;
    margin-bottom: 0;
}

/* Los botones fijos mantienen el mismo estilo que los demás */
.import-quick-action.fixed {
    width: 52px;
    height: 52px;
    border-radius: 26px;
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.import-quick-action.fixed:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
}

.import-quick-actions-expanded {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.8);
    transition: all 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
}

.import-quick-actions-expanded.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.import-quick-action {
    width: 52px;
    height: 52px;
    border-radius: 26px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    position: relative;
}

.import-quick-action[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    right: 68px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--win-bg-secondary);
    color: var(--win-text-primary);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 10000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--win-border-color);
}

.import-quick-action[data-tooltip]:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateY(-50%) translateX(-4px);
}

/* Botón principal */
#importMainQuickAction {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

#importMainQuickAction:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
}

#importMainQuickAction:active {
    transform: scale(0.96);
}

/* Botones secundarios */
.import-quick-action-start {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
}

.import-quick-action-start:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
}

.import-quick-action-end {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    color: white;
}

.import-quick-action-end:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
}
/* Selector de facturas - MÁS CORTO */
.import-quick-action-select {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    color: white;
    width: auto;
    min-width: 140px;
    max-width: 180px;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 500;
    gap: 6px;
    border-radius: 26px;
}

.import-quick-action-select select {
    background: transparent;
    border: none;
    color: white;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    padding: 8px 0;
    max-width: 110px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.import-quick-action-select select option {
    background: var(--win-bg-secondary);
    color: var(--win-text-primary);
    white-space: normal;
}

.import-quick-action-select i {
    font-size: 14px;
}
.import-quick-action-select:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
}

/* Tema claro */
[data-theme="light"] .import-quick-action[data-tooltip]::after {
    background: #ffffff;
    color: #1e1e2d;
    border: 1px solid #e5e5e5;
}

/* Responsive */
@media (max-width: 576px) {
    .import-quick-actions {
        bottom: 16px;
        right: 16px;
    }
    
    .import-quick-action {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
    
    .import-quick-action-select {
        min-width: 140px;
    }
    
    .import-quick-action-select span {
        display: none;
    }
    
    .import-quick-action[data-tooltip]::after {
        display: none;
    }
}
/* ==================== NAVBAR FIJO ==================== */
.import-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: #064e3b;  /* Verde oscuro */
    border-bottom: 3px solid var(--win-accent);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}


.import-navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.import-navbar-brand img {
    width: 32px;
    height: 32px;
}

.import-navbar-brand span {
    font-weight: 600;
    font-size: 16px;
    color: var(--win-text-primary);
}

.import-navbar-brand small {
    font-size: 11px;
    color: var(--win-text-secondary);
    margin-left: 8px;
}

.import-navbar-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

/* Selector de facturas en navbar */
.import-navbar-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--win-bg-tertiary);
    border: 1px solid var(--win-border-color);
    border-radius: 20px;
    padding: 6px 14px;
}

.import-navbar-selector i {
    color: var(--win-accent);
    font-size: 14px;
}

.import-navbar-selector select {
    background: transparent;
    border: none;
    color: var(--win-text-primary);
    font-size: 13px;
    padding: 4px 8px;
    cursor: pointer;
    outline: none;
    min-width: 220px;
    font-weight: 500;
}

.import-navbar-selector select:hover {
    color: var(--win-accent);
}

.import-navbar-selector select option {
    background: var(--win-bg-secondary);
    color: var(--win-text-primary);
}

/* Botones del navbar */
.import-navbar-btn {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}

/* Botón Nueva Importación - Primario (Azul sólido) */
.import-navbar-btn-primary {
    background: var(--win-accent);
    border: 1px solid var(--win-accent);
    color: white;
    transition: all 0.2s ease;
}

.import-navbar-btn-primary:hover {
    background: transparent;
    color: var(--win-accent);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
/* Botón Facturas - Outline con texto blanco */
.import-navbar-btn-outline {
    background: transparent;
    border: 1px solid var(--win-accent);
    color: white;
    transition: all 0.2s ease;
}

.import-navbar-btn-outline:hover {
    background: var(--win-accent);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.import-navbar-btn-danger {
    background: #ffc107;
    border: 1px solid #ffc107;
    color: #000000;
}

.import-navbar-btn-danger:hover {
    background: #e0a800;
    border-color: #e0a800;
    color: #000000;
    transform: translateY(-1px);
}

/* Ajustar el main container para el navbar fijo */
.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding-top: 72px; /* Espacio para el navbar fijo */
}

/* Responsive */
@media (max-width: 768px) {
    .import-navbar {
        padding: 0 16px;
        height: auto;
        min-height: 56px;
        flex-wrap: wrap;
        padding: 8px 16px;
    }
    
    .import-navbar-actions {
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .import-navbar-selector select {
        min-width: 160px;
    }
    
    .main-container {
        padding-top: 100px;
    }
}

@media (max-width: 576px) {
    .import-navbar-brand span {
        font-size: 13px;
    }
    
    .import-navbar-brand small {
        display: none;
    }
    
    .import-navbar-selector select {
        min-width: 120px;
        font-size: 11px;
    }
    
    .import-navbar-btn {
        padding: 4px 10px;
        font-size: 11px;
    }
}
/* Contenedor de botones en columna */
.import-nav-column {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    align-items: center;
}

/* Botones de navegación en columna */
.import-nav-column .import-quick-action {
    width: 100%;
    min-width: 160px;
    height: 44px;
    border-radius: 22px;
    font-size: 14px;
    padding: 0 16px;
    gap: 8px;
    justify-content: center;
}

.import-nav-column .import-quick-action i {
    font-size: 14px;
}

/* Botones de navegación arriba/abajo en fila */
.import-nav-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
    width: 100%;
    flex-wrap: wrap;
}

.import-nav-buttons .import-quick-action {
    width: auto;
    min-width: 90px;
    height: 44px;
    border-radius: 22px;
    font-size: 14px;
    padding: 0 16px;
    gap: 6px;
}

/* Selector de facturas */
.import-quick-action-select {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    color: white;
    width: 100%;
    min-width: 200px;
    padding: 0 16px;
    font-size: 13px;
    font-weight: 500;
    gap: 8px;
    border-radius: 26px;
    height: 52px;
    position: relative; /* Para que el menú absoluto se posicione respecto a este */
    z-index: 1050;
}

.import-quick-action-select select {
    background: transparent;
    border: none;
    color: white;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    padding: 8px 0;
    flex: 1;
}

.import-quick-action-select select option {
    background: var(--win-bg-secondary);
    color: var(--win-text-primary);
}

/* Botones individuales */
.import-quick-action-start {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
}

.import-quick-action-start:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
}

.import-quick-action-end {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    color: white;
}

.import-quick-action-end:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
}

.import-quick-action-facturas {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.import-quick-action-facturas:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}

.import-quick-action-dashboard {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.import-quick-action-dashboard:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.import-quick-action-cancelar {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.import-quick-action-cancelar:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}
.form-select {
    background-color: var(--win-bg-tertiary);
    color: var(--win-text-primary);
    border-color: var(--win-border-color);
}
.form-select option {
    background-color: var(--win-bg-secondary);
    color: var(--win-text-primary);
}

/* Contenedor de botones fijos (inicio/final) */
.import-quick-actions-fixed {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
}

.import-quick-action.fixed {
    width: 52px;
    height: 52px;
    border-radius: 26px;
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.import-quick-action.fixed:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
}

/* Ajustar el orden de los elementos en la columna */
.import-quick-actions {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}
/* Estilos para el dropdown personalizado del filtro */
.custom-filter-dropdown .dropdown-toggle {
    background-color: var(--win-bg-tertiary);
    color: var(--win-text-primary);
    border-color: var(--win-border-color);
    transition: all 0.2s ease;
}

.custom-filter-dropdown .dropdown-toggle:hover {
    background-color: var(--win-accent);
    color: white;
    border-color: var(--win-accent);
}

.custom-filter-dropdown .dropdown-menu {
    background-color: var(--win-bg-secondary);
    border-color: var(--win-border-color);
    backdrop-filter: blur(0px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}
.dropdown-menu {
    --bs-dropdown-zindex: 1060;
}
.custom-filter-dropdown .dropdown-item {
    color: var(--win-text-primary);
    padding: 8px 16px;
    transition: all 0.2s;
}

.custom-filter-dropdown .dropdown-item:hover {
    background-color: var(--win-accent);
    color: white;
}

.custom-filter-dropdown .dropdown-item i {
    width: 1.25rem;
    margin-right: 0.5rem;
    text-align: center;
}

.custom-filter-dropdown .dropdown-toggle::after {
    margin-left: 0.5em;
    vertical-align: middle;
}
/* ==================== CUSTOM DROPDOWN PARA "IR A FACTURA" ==================== */
/* Botones */
.navbar-factura-dropdown-btn,
.quick-factura-dropdown-btn {
    background: transparent;
    border: none;
    color: var(--win-text-primary);
    font-weight: 500;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 13px;
    white-space: nowrap;
    transition: all 0.2s;
}
.navbar-factura-dropdown-btn:hover,
.quick-factura-dropdown-btn:hover {
    color: var(--win-accent);
}
/* Menú desplegable personalizado del quick action - CON SCROLLBAR VISIBLE */
.quick-factura-menu {
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 8px;
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: 12px;
    padding: 8px 0;
    min-width: 260px;
    max-width: 320px;
    max-height: 320px;
    overflow-y: auto !important;  /* Forzar scroll vertical */
    overflow-x: hidden;
    z-index: 1060;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

/* Ajuste para el dropdown del navbar */
.navbar-factura-menu {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 4px;
    z-index: 1060;
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: 12px;
    padding: 8px 0;
    min-width: 260px;
    max-width: 320px;
    max-height: 320px;
    overflow-y: auto !important;  /* Forzar scroll vertical */
    overflow-x: hidden;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}
/* Elementos del menú */
.dropdown-item-custom {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    color: var(--win-text-primary);
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
    cursor: pointer;
}
.dropdown-item-custom i {
    width: 20px;
    color: var(--win-accent);
}
.dropdown-item-custom:hover {
    background: var(--win-accent);
    color: white;
}
.dropdown-item-custom:hover i {
    color: white;
}
//* Scrollbar personalizada para mejor visibilidad */
.navbar-factura-menu::-webkit-scrollbar,
.quick-factura-menu::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.navbar-factura-menu::-webkit-scrollbar-track {
    background: var(--win-bg-tertiary);
    border-radius: 10px;
    margin: 4px 0;
}

.navbar-factura-menu::-webkit-scrollbar-thumb {
    background: var(--win-accent);
    border-radius: 10px;
}

.navbar-factura-menu::-webkit-scrollbar-thumb:hover {
    background: var(--win-accent);
    opacity: 0.8;
    cursor: pointer;
}

/* Para Firefox */
.navbar-factura-menu,
.quick-factura-menu {
    scrollbar-width: thin;
    scrollbar-color: var(--win-accent) var(--win-bg-tertiary);
}

/* Para temas claro y oscuro consistencia */
[data-theme="light"] .navbar-factura-menu::-webkit-scrollbar-track,
[data-theme="light"] .quick-factura-menu::-webkit-scrollbar-track {
    background: #e9ecef;
}

[data-theme="light"] .navbar-factura-menu::-webkit-scrollbar-thumb,
[data-theme="light"] .quick-factura-menu::-webkit-scrollbar-thumb {
    background: var(--win-accent);
}
/* Efecto hover para facturas principales - MÁS NOTORIO */
.factura-card:hover {
    background-color: rgba(var(--win-accent-rgb), 0.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    transition: all 0.25s ease;
    border-color: var(--win-accent);
}

/* Efecto hover para facturas internas */
.inner-card:hover {
    background-color: rgba(var(--win-accent-rgb), 0.15);
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.2s ease;
    border-left: 3px solid var(--win-accent);
}

/* Refuerzo del header al hover de la tarjeta principal */
.factura-card:hover .factura-header {
    border-bottom-color: var(--win-accent);
    padding-bottom: 15px;
}
/* Animación de parpadeo */
@keyframes flashTitle {
    0% { background-color: rgba(var(--win-accent-rgb), 0); }
    50% { background-color: var(--win-accent); color: white; } /* fondo sólido y texto blanco */
    100% { background-color: rgba(var(--win-accent-rgb), 0); }
}
/* Animación de parpadeo más notoria */
@keyframes flashTitleStrong {
    0% { background-color: rgba(var(--win-accent-rgb), 0); box-shadow: none; }
    25% { background-color: var(--win-accent); color: white; box-shadow: 0 0 15px var(--win-accent); }
    75% { background-color: var(--win-accent); color: white; box-shadow: 0 0 15px var(--win-accent); }
    100% { background-color: rgba(var(--win-accent-rgb), 0); box-shadow: none; }
}

.factura-header.flash {
    animation: flashTitleStrong 0.8s ease 5 !important; /* 5 repeticiones */
    border-radius: 6px;
}

/* Colapso de tarjetas */
.factura-body, .inner-card-body { display: block; }
.factura-card.collapsed .factura-body, .inner-card.collapsed .inner-card-body { display: none; }

.collapse-icon, .inner-collapse-icon {
    transition: all 0.2s ease;
    color: var(--win-accent);
    font-size: 1.1rem;
}
/* Estilos específicos para el dropdown de usuario */
.dropdown-menu {
    background-color: var(--win-bg-secondary) !important;
    border: 1px solid var(--win-border-color) !important;
    border-radius: var(--win-radius) !important;
    box-shadow: var(--win-shadow) !important;
}

.dropdown-header {
    color: var(--win-text-secondary) !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
}

.dropdown-footer {
    background-color: var(--win-bg-tertiary) !important;
    border-radius: 0 0 var(--win-radius-sm) var(--win-radius-sm);
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

.dropdown-item.text-danger:hover {
    background-color: rgba(220, 53, 69, 0.1) !important;
    color: #dc3545 !important;
}

.dropdown-divider {
    border-color: var(--win-border-color) !important;
    opacity: 0.5 !important;
}

/* Avatar de usuario */
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

/* Botón del dropdown */
.btn-outline-secondary {
    color: var(--win-text-secondary);
    border-color: var(--win-border-color);
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background: var(--win-bg-tertiary);
    color: var(--win-text-primary);
}

.d-flex {
    display: flex !important;
}

.align-items-center {
    align-items: center !important;
}

.gap-2 {
    gap: 0.5rem !important;
}

/* Texto pequeño */
.small {
    font-size: 0.875rem;
}

/* Margen izquierdo */
.ms-1 {
    margin-left: 0.25rem !important;
}

.me-1 {
    margin-right: 0.25rem !important;
}

.me-2 {
    margin-right: 0.5rem !important;
}

.me-3 {
    margin-right: 1rem !important;
}

.px-3 {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.mt-1 {
    margin-top: 0.25rem !important;
}

.my-1 {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
}

.mb-0 {
    margin-bottom: 0 !important;
}

/* Texto muted */
.text-muted {
    color: var(--win-text-secondary) !important;
}

.fw-bold {
    font-weight: 700 !important;
}

/* Shadow */
.shadow-lg {
    box-shadow: var(--win-shadow) !important;
}
/* Efecto hover persistente para TODO el card-title */
.factura-header .d-flex.align-items-center {
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 30px;
    margin: -6px -14px;
    cursor: pointer;
}

.factura-header .d-flex.align-items-center:hover {
    background: var(--win-accent);
    color: white;
    box-shadow: 0 0 12px var(--win-accent);
    transform: scale(1.02);
}

/* Para que el ícono también cambie de color */
.factura-header .d-flex.align-items-center:hover i {
    color: white !important;
}

/* Para el texto pequeño */
.factura-header .d-flex.align-items-center:hover small {
    color: white !important;
}

/* Para el h4 completo */
.factura-header .d-flex.align-items-center:hover h4 {
    color: white !important;
}

    </style>
</head>
<body>
    <div class="main-container">
<!-- Navbar Fijo para Importar Facturas -->
<div class="import-navbar">
    <div class="import-navbar-brand">
        <img src="assets/logov.png" alt="Logo">
        <span>SISFACT PDL Visiones <h5 class="text-warning">Importador de Facturas</h5></span>
    </div>
    
    <div class="import-navbar-actions">
        <!-- Selector de Facturas (solo visible cuando hay facturas) -->
        <?php if ($modo_procesamiento && !empty($facturas_extraidas)): ?>
<div class="import-navbar-selector">
    <i class="fas fa-file-invoice"></i>
    <!-- Botón que abre el menú -->
    <button class="navbar-factura-dropdown-btn" id="navbarFacturaBtn" data-bs-toggle="dropdown" aria-expanded="false">
        Ir a factura...
    </button>
    <!-- Menú desplegable personalizado -->
    <ul class="dropdown-menu navbar-factura-menu" id="navbarFacturaMenu">
        <!-- Las opciones se cargarán dinámicamente con JS -->
    </ul>
</div>
        <?php endif; ?>
        
        <!-- Botón global colapsar/expandir (único) -->
        <?php if ($modo_procesamiento && !empty($facturas_extraidas)): ?>
        <button class="import-navbar-btn import-navbar-btn-primary" id="globalToggleCollapseBtn" onclick="toggleGlobalCollapse()" title="Colapsar/Expandir todas">
            <i class="fas fa-compress-alt"></i> <span id="globalToggleText">Colapsar todas</span>
        </button>
        <?php endif; ?>
        
        <!-- Botón Analizar/Subir archivo -->
        <?php if (!$modo_procesamiento): ?>
        <button class="import-navbar-btn import-navbar-btn-primary" id="btnAnalizarArchivo" onclick="return validarSubidaNavbar()">
            <i class="fas fa-search"></i> Analizar Archivo
        </button>
        <?php endif; ?>
		
        <!-- Botones de navegación -->
		
		<a href="importar_factura.php" class="import-navbar-btn import-navbar-btn-danger" style="background: #dc3545; border-color: #dc3545; color: white;">
			<i class="fas fa-undo-alt me-2"></i> Cancelar
		</a>
        <a href="importar_factura.php" class="import-navbar-btn import-navbar-btn-primary">
            <i class="fas fa-upload"></i> Nueva Importación
        </a>
        <a href="dashboard.php" class="import-navbar-btn import-navbar-btn-outline">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!---<a href="facturas.php" class="import-navbar-btn import-navbar-btn-outline">
            <i class="fas fa-file-invoice"></i> Facturas
        </a>
        
        
        <a href="logout.php" class="import-navbar-btn import-navbar-btn-danger">
            <i class="fas fa-sign-out-alt"></i> Salir
        </a>---->
    </div>
	
        
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
</div>
        
        <?php if (!$modo_procesamiento): ?>
        <!-- Formulario de subida -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Seleccionar archivo a Importar</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger animate__animated animate__shakeX">
                    <i class="fas fa-triangle-exclamation me-2"></i>
                    <strong>Error de importación:</strong><br>
                    <?php echo $error; ?>
                    <hr class="my-2">
                    <small class="d-block">
                        <i class="fas fa-lightbulb me-1"></i>
                        <strong>Sugerencia:</strong> Asegúrate de que el archivo sea generado por el Capturador de Facturas 
                        y tenga el formato correcto (debe contener "-- FACTURA" e "INSERT INTO tbl_fact").
                    </small>
                </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-file-code me-2"></i>Archivo SQL:</label>
                        <input type="file" name="archivo_sql" class="form-control" accept=".sql,.txt" required>
                        <small class="text-secondary-custom">Solo archivos .sql generados por el capturador de facturas</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" onclick="return validarSubida()">
                            <i class="fas fa-search me-2"></i>Analizar Archivo
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Ir al Inicio
                        </a>
                    </div>
                </form>
                
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-lightbulb me-2"></i>Instrucciones:</h6>
                    <ol class="mb-0">
                        <li>Sube el archivo en formato SQL generado por el capturador</li>
                        <li>El sistema validará que las fechas estén dentro del período operativo</li>
                        <li>Para cada factura podrás:
                            <ul>
                                <li>Crear una nueva factura</li>
                                <li>Agregar a una factura existente (suma cantidades si el servicio ya existe)</li>
                            </ul>
                        </li>
                        <li><strong>NUEVO:</strong> El estado de procesamiento se guarda en tu navegador. Si recargas la página, las facturas ya procesadas aparecerán marcadas.</li>
                    </ol>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($modo_procesamiento && !empty($facturas_extraidas)): ?>
        
        <?php if (!empty($advertencias_fecha)): ?>
        <div class="alert alert-warning animate__animated animate__fadeIn">
            <h6><i class="fas fa-triangle-exclamation me-2"></i><span class="fw-bold">Advertencias de fecha:</span></h6>
            <ul class="mb-0">
                <?php foreach ($advertencias_fecha as $adv): ?>
                    <li><?php echo $adv; ?></li>
                <?php endforeach; ?>
            </ul>
            <small class="mt-2 d-block fw-bold">Las facturas con fechas fuera del período operativo no podrán ser procesadas.</small>
        </div>
        <?php endif; ?>
        
        <!-- Resumen con progreso y controles de persistencia -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Facturas Encontradas: <span class="fw-bold text-warning"><?php echo count($facturas_extraidas); ?></span></h5>
            </div>
            <div class="card-body">
<div class="alert alert-success">
    <i class="fas fa-chart-line me-2"></i>
    Se encontraron <strong class="text-warning"><?php echo count($facturas_extraidas); ?> factura(s)</strong> en total.
    <span class="text-success"><i class="fas fa-check-circle me-1"></i> Procesadas: <strong id="procesadasAlert">0</strong></span> |
    <span class="text-warning"><i class="fas fa-clock me-1"></i> Pendientes: <strong id="pendientesAlert"><?php echo count($facturas_extraidas); ?></strong></span>
</div>
                
                <?php 
                // Calcular estadísticas de progreso (desde localStorage se hará en JS)
                ?>
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="progress mb-2" style="height: 30px;">
                            <div id="progresoImportacion" class="fw-bold text-white progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%; background-color: #28a745 !important;">0%</div>
                        </div>
						<div class="d-flex justify-content-between" style="font-size: 18px;">
							<span class="text-warning"><i class="fas fa-check-circle text-warning me-1"></i> <span class="text-success">Procesadas:</span> <strong class="text-light" id="procesadasCount">0</strong></span>
							<span class="text-warning"><i class="fas fa-clock text-warning me-1"></i> <span class="text-warning">Pendientes:</span> <strong class="text-light" id="pendientesCount"><?php echo count($facturas_extraidas); ?></strong></span>
							<span class="text-warning"><i class="fas fa-file-invoice me-1"></i> <span class="text-danger">Total:</span> <strong class="text-light"><?php echo count($facturas_extraidas); ?></strong></span>
						</div>
                    </div>
                    <div class="col-md-4 text-end">
<div class="custom-filter-dropdown d-inline-block">
    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filtroFacturasBtn" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-list me-1"></i> Todas las facturas
    </button>
    <ul class="dropdown-menu" aria-labelledby="filtroFacturasBtn">
        <li><a class="dropdown-item" href="#" data-value="todas"><i class="fas fa-list me-2"></i> Todas las facturas</a></li>
        <li><a class="dropdown-item" href="#" data-value="pendientes"><i class="fas fa-hourglass-half me-2"></i> Solo pendientes</a></li>
        <li><a class="dropdown-item" href="#" data-value="procesadas"><i class="fas fa-check-circle me-2"></i> Solo procesadas</a></li>
    </ul>
</div>
<input type="hidden" id="filtroFacturasValor" value="todas">
<!-- Campo oculto para mantener el valor -->
<input type="hidden" id="filtroFacturasValor" value="todas">
                        <button class="btn btn-sm btn-outline-warning ms-2" onclick="resetearTodoElArchivo()">
                            <i class="fas fa-undo-alt me-1"></i> Reiniciar todo
                        </button>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="importar_factura.php" class="btn btn-outline-danger">
                        <i class="fas fa-times me-2"></i>Cancelar y volver
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
        
<!-- Listado de facturas -->
<?php 
$importador_temp = new ImportadorFacturas($db, $usuario_id, $primer_dia_operativo, $ultimo_dia_operativo);
// Agregar nombres de servicios a cada detalle para JavaScript
foreach ($facturas_extraidas as &$factura) {
    foreach ($factura['detalles'] as &$detalle) {
        $servicio_info = $importador_temp->getServicioInfo($detalle['servicio_id'] ?? null);
        if ($servicio_info) {
            $detalle['servicio_nombre'] = $servicio_info['descripcion'];
            $detalle['servicio_codigo'] = $servicio_info['codigo'];
        } else {
            $detalle['servicio_nombre'] = 'Servicio no encontrado';
            $detalle['servicio_codigo'] = '';
        }
    }
}
unset($factura, $detalle);

// ===== SECUENCIAL INCREMENTAL (usamos index+1 para garantizar unicidad) =====
$secuencial_base = obtenerUltimoSecuencial($db);
$contador_secuencial = 0;
$anio_operativo = obtenerAnioCierreOperaciones();
$tipo_prefijo = 'FV-';

foreach ($facturas_extraidas as $index => $factura): 
    // Aseguramos un temp_id único (no dependemos del SQL)
    $factura_temp_id = ($index + 1);  // 1,2,3...
    $cliente_id = $factura['cliente_id'] ?? null;
    $fecha_emision = $factura['fecha_emision'] ?? '';
    $fecha_valida = $importador_temp->validarFechaOperativa($fecha_emision);
    
    $cliente_info = $cliente_id ? $importador_temp->getClienteInfo($cliente_id) : null;
    $facturas_existentes = ($cliente_id && $fecha_valida) ? $importador_temp->buscarFacturasExistentes($cliente_id) : [];
    $total_factura = floatval($factura['total_general'] ?? $factura['subtotal'] ?? 0);
    $num_detalles = count($factura['detalles'] ?? []);
    
    // ===== PREDECIR NÚMERO CON SECUENCIAL INCREMENTAL =====
    $contador_secuencial++;
    $secuencial_predicho = $secuencial_base + $contador_secuencial;
    
    if ($secuencial_predicho > 9999) {
        $numero_predicho = 'ERROR: Límite alcanzado';
        $prediccion_error = true;
        $mensaje_error = "Límite de facturas anuales alcanzado (máximo 9999)";
    } else {
        $prediccion_error = false;
        $numero_secuencial = str_pad($secuencial_predicho, 4, '0', STR_PAD_LEFT);
        
        $timestamp = strtotime($fecha_emision);
        if ($timestamp === false) {
            $fecha_para_formato = date('Ymd');
        } else {
            $fecha_para_formato = date('Ymd', $timestamp);
        }
        
        $numero_predicho = $tipo_prefijo . $fecha_para_formato . $numero_secuencial;
    }
?>
<div class="factura-card animate__animated animate__fadeInUp" 
     id="factura-<?php echo $index; ?>" 
     data-temp-id="<?php echo $factura_temp_id; ?>"
     data-index="<?php echo $index; ?>"
     data-procesada="false">
    
    <!-- Header con botón para colapsar -->
    <div class="factura-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="fas fa-file-invoice me-2" style="color: var(--win-accent);"></i>
                Factura #<?php echo $index + 1; ?>
                <?php if (!empty($factura['cliente_nombre'])): ?>
                    <small class="fw-bold text-warning ms-3"><?php echo htmlspecialchars($factura['cliente_nombre']); ?></small>
                <?php endif; ?>
            </h4>
            <div class="d-flex align-items-center gap-3">
                <div>
                    <!-- Badges (procesada, sin procesar, total) -->
                    <span class="badge bg-success me-2 procesada-badge" style="display: none;">
                        <i class="fas fa-check-circle me-1"></i> PROCESADA
                    </span>
                    <span class="badge bg-danger me-2 pendiente-badge" style="display: none;">
                        <i class="fas fa-hourglass-half me-1"></i> SIN PROCESAR
                    </span>
                    <span class="badge-success">
                        <i class="fas fa-tag me-1"></i>Total: $<?php echo number_format($total_factura, 2); ?>
                    </span>
                </div>
                <!-- Botón de colapsar/expandir -->
                <button type="button" class="btn btn-sm btn-outline-secondary toggle-collapse-btn" data-temp-id="<?php echo $factura_temp_id; ?>" style="padding: 2px 8px;">
                    <i class="fas fa-chevron-up collapse-icon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- CONTENIDO COLAPSABLE -->
    <div class="factura-body">
        <?php if (!$fecha_valida): ?>
            <div class="alert alert-warning py-2 mb-3">
                <i class="fas fa-calendar-times me-2"></i>
                <strong>Fecha fuera del período operativo:</strong> 
                <?php 
                $fecha_mostrar = $fecha_emision;
                if (strpos($fecha_emision, ' ') !== false) $fecha_mostrar = explode(' ', $fecha_emision)[0];
                if (strpos($fecha_mostrar, '-') !== false) $fecha_mostrar = date('d/m/Y', strtotime($fecha_mostrar));
                echo htmlspecialchars($fecha_mostrar); 
                ?>
                <br><small>Esta factura NO podrá ser procesada. El período actual es <?php echo date('d/m/Y', strtotime($primer_dia_operativo)); ?> - <?php echo date('d/m/Y', strtotime($ultimo_dia_operativo)); ?></small>
            </div>
        <?php endif; ?>

        <?php if ($num_detalles == 0): ?>
            <div class="alert alert-warning py-2 mb-3">
                <i class="fas fa-box-open me-2"></i>
                <strong>Factura sin servicios:</strong> 
                Esta factura no contiene servicios para importar.
                <br><small>No se podrá crear una nueva factura ni agregar servicios a una existente.</small>
            </div>
        <?php endif; ?>
        
        <?php if (!$cliente_info && $cliente_id): ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="fas fa-user-slash me-2"></i>
                <strong>Cliente no encontrado:</strong> 
                El cliente con ID <?php echo $cliente_id; ?> no existe en la base de datos.
                <br><small>No se podrá procesar esta factura. Verifique los datos del cliente.</small>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-5">
                <h6><i class="fas fa-user me-2"></i>Cliente:
                <span class="mb-2">
                    <?php if ($cliente_info): ?>
                        <span class="fw-bold text-warning"><?php echo htmlspecialchars($cliente_info['nombre']); ?></span><br>
                        <span class="text-secondary-custom">
                            Contrato: <span class="fw-bold text-warning"><?php echo htmlspecialchars($cliente_info['ContratoNo'] ?? 'S/C'); ?></span><br>
                            Tel:  <span class="fw-bold text-warning"><?php echo htmlspecialchars($cliente_info['telefono'] ?? 'N/A'); ?></span>
                        </span>
                    <?php else: ?>
                        <span class="text-warning">
                            <i class="fas fa-triangle-exclamation me-1"></i>
                            Cliente ID: <?php echo $cliente_id ?: 'No especificado'; ?> (no encontrado)
                        </span>
                    <?php endif; ?>
                </span></h6>
                
                <h6><i class="fas fa-calendar me-2"></i>Fecha Emisión:
                <span class="<?php echo $fecha_valida ? 'badge bg-info' : 'text-warning badge bg-danger'; ?>">
                    <?php 
                    $fecha_mostrar = $fecha_emision;
                    if (strpos($fecha_emision, ' ') !== false) $fecha_mostrar = explode(' ', $fecha_emision)[0];
                    if (strpos($fecha_mostrar, '-') !== false) $fecha_mostrar = date('d/m/Y', strtotime($fecha_mostrar));
                    echo htmlspecialchars($fecha_mostrar); 
                    ?>
                    <?php if (!$fecha_valida): ?>
                        <i class="fas fa-circle-xmark text-warning ms-2" title="Fuera del período operativo"></i>
                    <?php endif; ?>
                </span></h6>
                
                <h6><i class="fas fa-hashtag me-2"></i>Posible Nº Asignado:
                    <span class="numero-factura-badge">
                        <i class="fas fa-ticket-alt me-1"></i><?php echo $numero_predicho; ?>
                    </span>
                </h6>
                
                <h6><i class="fas fa-tag me-2"></i>Estado: 
                <span class="badge bg-info"><?php echo htmlspecialchars($factura['estado'] ?? 'PENDIENTE'); ?></span></h6>
                
                <h6><i class="fas fa-credit-card me-2"></i>Tipo de Pago ID:
                <span class="badge bg-info"><?php echo htmlspecialchars($factura['tipo_pago_id'] ?? 'No especificado'); ?></span></h6>
            </div>
            
            <div class="col-md-7">
                <h6><i class="fas fa-list me-2"></i>Servicios (<?php echo $num_detalles; ?>):</h6>
                
                <?php if ($num_detalles > 0): ?>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Servicio</th>
                                <th class="text-center">Cant</th>
                                <th class="text-end">P.Unit</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_calculado = 0;
                            foreach ($factura['detalles'] as $i => $detalle): 
                                $servicio_id = $detalle['servicio_id'] ?? null;
                                $servicio_info = $servicio_id ? $importador_temp->getServicioInfo($servicio_id) : null;
                                $cantidad = intval($detalle['cantidad'] ?? 1);
                                $precio = floatval($detalle['precio_unitario'] ?? 0);
                                $total = floatval($detalle['total_linea'] ?? ($cantidad * $precio));
                                $total_calculado += $total;
                            ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <?php if ($servicio_info): ?>
                                            <strong><?php echo htmlspecialchars($servicio_info['descripcion']); ?></strong>
                                            <small class="text-secondary-custom d-block"><?php echo htmlspecialchars($servicio_info['codigo'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span class="text-warning">Servicio ID: <?php echo $servicio_id; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $cantidad; ?></td>
                                    <td class="text-end">$<?php echo number_format($precio, 2); ?></td>
                                    <td class="text-end fw-bold">$<?php echo number_format($total, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Subtotal:</th>
                                <th class="text-end">$<?php echo number_format($factura['subtotal'] ?? $total_calculado, 2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">Total General:</th>
                                <th class="text-end" style="color: var(--win-accent);">$<?php echo number_format($total_factura, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-secondary-custom">No hay servicios en esta factura</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-3">
            <h6><i class="fas fa-comment me-2"></i>Observaciones: <span class="text-warning">(Puede modificar las observaciones antes de importar)</span></h6>
            <textarea 
                class="form-control observaciones-input" 
                id="observaciones-<?php echo $index; ?>" 
                data-index="<?php echo $index; ?>"
                rows="1" 
                style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border: 1px solid var(--win-border-color); border-radius: 6px; resize: vertical;"
                placeholder="Observaciones de la factura..."
                onkeydown="if(event.key === 'Enter') { event.preventDefault(); this.value = this.value + ' '; }"
            ><?php echo htmlspecialchars(str_replace(["\r", "\n"], ' ', $factura['observaciones'] ?? '')); ?></textarea>
        </div>
        
        <style>
            /* Animación fade + slide */
            @keyframes slideFadeWarning {
                0% { opacity: 0.6; transform: translateX(-5px); }
                50% { opacity: 1; transform: translateX(0); }
                100% { opacity: 0.6; transform: translateX(-5px); }
            }
            .slide-fade-warning {
                animation: slideFadeWarning 1s ease-in-out infinite;
                display: inline-block;
            }
            .slide-fade-warning i { color: #ffc107 !important; animation: none; }
            .slide-fade-warning span { color: #ffc107 !important; font-weight: 800; }
        </style>
        
        <?php if ($fecha_valida && !empty($facturas_existentes)): ?>
            <!-- Mostrar facturas EDITABLES -->
            <div class="existente-info">
                <h6 class="slide-fade-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span>Facturas existentes para este cliente en el período:</span>
                </h6>
                <p class="text-secondary-custom mb-3">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Período: <span class="fw-bold"><?php echo date('d/m/Y', strtotime($primer_dia_operativo)); ?> - <?php echo date('d/m/Y', strtotime($ultimo_dia_operativo)); ?></span>
                </p>
                
                <?php foreach ($facturas_existentes as $existente): ?>
                <div class="mb-3 p-3 inner-card" style="background: var(--win-bg-secondary); border-radius: 8px; border: 1px solid var(--win-border-color);" data-inner-id="<?php echo $existente['id']; ?>">
                    <!-- Cabecera con botón -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($existente['no_fact']); ?></strong>
                            <span class="badge bg-info ms-2"><?php echo $existente['estado']; ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="text-end me-2">
                                <div>Fecha: <strong><?php echo date('d/m/Y', strtotime($existente['fecha_emision'])); ?></strong></div>
                                <div>Total actual: <strong style="color: var(--win-accent);">$<?php echo number_format($existente['total_general'], 2); ?></strong></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary toggle-inner-collapse" data-inner-id="<?php echo $existente['id']; ?>" data-parent-temp-id="<?php echo $factura_temp_id; ?>" style="padding: 2px 8px;">
                                <i class="fas fa-chevron-up inner-collapse-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="inner-card-body">
                        <?php if (!empty($existente['servicios_existentes'])): ?>
                        <small class="text-secondary-custom">Servicios en esta factura:</small>
                        <div class="table-responsive mt-1" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr class="text-secondary-custom">
                                        <th>Servicio</th>
                                        <th class="text-center">Cant. Actual</th>
                                        <th class="text-center">+ Import</th>
                                        <th class="text-center">Nueva Cant.</th>
                                        <th class="text-end">P.Unit</th>
                                        <th class="text-end">Total Línea</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $servicios_a_importar_ids = array_column($factura['detalles'], 'servicio_id');
                                    $total_actual_factura = floatval($existente['total_general'] ?? 0);
                                    $total_agregado_calculado = 0;
                                    
                                    foreach ($existente['servicios_existentes'] as $serv_existente): 
                                        $servicio_id = $serv_existente['servicio_id'];
                                        $coincide = in_array($servicio_id, $servicios_a_importar_ids);
                                        $cantidad_a_sumar = 0;
                                        $precio_unitario_import = 0;
                                        $total_linea_actual = floatval($serv_existente['total_linea'] ?? ($serv_existente['cantidad'] * $serv_existente['precio_unitario']));
                                        if ($coincide) {
                                            foreach ($factura['detalles'] as $det) {
                                                if ($det['servicio_id'] == $servicio_id) {
                                                    $cantidad_a_sumar = $det['cantidad'] ?? 1;
                                                    $precio_unitario_import = floatval($det['precio_unitario'] ?? 0);
                                                    break;
                                                }
                                            }
                                            $total_agregado_calculado += ($cantidad_a_sumar * $precio_unitario_import);
                                        }
                                        $nueva_cantidad = $serv_existente['cantidad'] + $cantidad_a_sumar;
                                        $nuevo_total_linea = $nueva_cantidad * $serv_existente['precio_unitario'];
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($serv_existente['descripcion'] ?? "ID: $servicio_id"); ?>
                                            <?php if ($coincide): ?>
                                                <i class="fas fa-check-circle text-success ms-2" title="Coincide con la importación"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $serv_existente['cantidad']; ?></td>
                                        <td class="text-center">
                                            <?php if ($coincide && $cantidad_a_sumar > 0): ?>
                                                <span class="text-success fw-bold">+<?php echo $cantidad_a_sumar; ?></span>
                                            <?php else: ?>
                                                <span class="text-secondary-custom">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($coincide): ?>
                                                <strong style="color: var(--win-accent);"><?php echo $nueva_cantidad; ?></strong>
                                            <?php else: ?>
                                                <?php echo $serv_existente['cantidad']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">$<?php echo number_format($serv_existente['precio_unitario'], 2); ?></td>
                                        <td class="text-end fw-bold">
                                            <?php if ($coincide): ?>
                                                <span class="text-warning">$<?php echo number_format($nuevo_total_linea, 2); ?></span>
                                                <small class="text-secondary-custom d-block">(era $<?php echo number_format($total_linea_actual, 2); ?>)</small>
                                            <?php else: ?>
                                                $<?php echo number_format($total_linea_actual, 2); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                    foreach ($factura['detalles'] as $detalle):
                                        $servicio_id = $detalle['servicio_id'];
                                        $ya_existe = false;
                                        foreach ($existente['servicios_existentes'] as $serv_existente) {
                                            if ($serv_existente['servicio_id'] == $servicio_id) { $ya_existe = true; break; }
                                        }
                                        if (!$ya_existe):
                                            $servicio_info = $importador_temp->getServicioInfo($servicio_id);
                                            $cantidad_nueva = intval($detalle['cantidad'] ?? 1);
                                            $precio_nuevo = floatval($detalle['precio_unitario'] ?? 0);
                                            $total_linea_nuevo = $cantidad_nueva * $precio_nuevo;
                                            $total_agregado_calculado += $total_linea_nuevo;
                                    ?>
                                    <tr style="background: rgba(40, 167, 69, 0.15);">
                                        <td>
                                            <?php echo htmlspecialchars($servicio_info['descripcion'] ?? "ID: $servicio_id"); ?>
                                            <span class="badge bg-success ms-2">NUEVO</span>
                                        </td>
                                        <td class="text-center text-secondary-custom">0</td>
                                        <td class="text-center"><span class="text-success fw-bold">+<?php echo $cantidad_nueva; ?></span></td>
                                        <td class="text-center"><strong style="color: var(--win-accent);"><?php echo $cantidad_nueva; ?></strong></td>
                                        <td class="text-end">$<?php echo number_format($precio_nuevo, 2); ?></td>
                                        <td class="text-end fw-bold text-success">$<?php echo number_format($total_linea_nuevo, 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="border-top: 2px solid var(--win-border-color);">
                                        <td colspan="5" class="text-end fw-bold">Total Actual:</td>
                                        <td class="text-end fw-bold">$<?php echo number_format($total_actual_factura, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end fw-bold text-success">
                                            <i class="fas fa-plus-circle me-1"></i>Total a Agregar:
                                        </td>
                                        <td class="text-end fw-bold text-success">$<?php echo number_format($total_agregado_calculado, 2); ?></td>
                                    </tr>
                                    <tr style="border-top: 2px solid var(--win-accent);">
                                        <td colspan="5" class="text-end fw-bold" style="font-size: 1.1rem;">
                                            <i class="fas fa-calculator me-2" style="color: var(--win-accent);"></i>NUEVO TOTAL:
                                        </td>
                                        <td class="text-end fw-bold" style="font-size: 1.2rem;">
                                            <span class="text-warning fw-bold">$<?php echo number_format($total_actual_factura + $total_agregado_calculado, 2); ?></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                            <p class="text-secondary-custom">Esta factura no tiene servicios aún.</p>
                            <?php 
                            $total_agregado_calculado = 0;
                            foreach ($factura['detalles'] as $detalle) {
                                $cantidad = intval($detalle['cantidad'] ?? 1);
                                $precio = floatval($detalle['precio_unitario'] ?? 0);
                                $total_agregado_calculado += ($cantidad * $precio);
                            }
                            ?>
                            <div class="mt-3 p-3 rounded" style="background: rgba(40, 167, 69, 0.1); border: 1px solid #198754;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><i class="fas fa-plus-circle text-success me-2"></i>Total a agregar (nuevos servicios):</span>
                                    <span class="fw-bold text-success">$<?php echo number_format($total_agregado_calculado, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: #198754 !important;">
                                    <span class="fw-bold" style="font-size: 1.1rem;">
                                        <i class="fas fa-file-invoice me-2" style="color: var(--win-accent);"></i>NUEVO TOTAL DE FACTURA:
                                    </span>
                                    <span class="fw-bold" style="font-size: 1.2rem; color: var(--win-accent);">
                                        $<?php echo number_format($total_agregado_calculado, 2); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <?php if ($num_detalles > 0 && $cliente_info): ?>
                            <form method="post" style="display: inline;" class="form-agregar" data-index="<?php echo $index; ?>" data-no-factura="<?php echo htmlspecialchars($existente['no_fact']); ?>">
                                <input type="hidden" name="factura_data" value="<?php echo htmlspecialchars(json_encode($factura)); ?>">
                                <input type="hidden" name="factura_existente_id" value="<?php echo $existente['id']; ?>">
                                <input type="hidden" name="accion_factura" value="agregar">
                                <input type="hidden" name="factura_temp_id" value="<?php echo $factura_temp_id; ?>">
                                <input type="hidden" name="observaciones_modificadas" class="observaciones-hidden" value="">
                                <button type="submit" class="btn btn-success btn-procesar">
                                    <i class="fas fa-plus-circle me-2"></i>Agregar a esta factura
                                </button>
                            </form>
                            <small class="text-secondary-custom ms-2">
                                <i class="fas fa-info-circle"></i> 
                                <span class="text-success">Coincidentes:</span> suman cantidades | 
                                <span class="badge bg-success">NUEVO:</span> se agregan
                            </small>
                            <?php elseif ($num_detalles > 0 && !$cliente_info): ?>
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fas fa-ban me-2"></i>Cliente no encontrado
                            </button>
                            <small class="text-secondary-custom ms-2">
                                <i class="fas fa-info-circle"></i> No se puede agregar porque el cliente no existe en la BD
                            </small>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fas fa-ban me-2"></i>No hay servicios para agregar
                            </button>
                            <small class="text-secondary-custom ms-2">
                                <i class="fas fa-info-circle"></i> Esta factura no tiene servicios para importar
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            $facturas_no_editables = ($fecha_valida && $cliente_id) ? $importador_temp->buscarFacturasNoEditables($cliente_id) : [];
            if (!empty($facturas_no_editables)): ?>
            <div class="alert alert-warning mt-2">
                <i class="fas fa-lock me-2"></i>
                <strong>Facturas no editables encontradas:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($facturas_no_editables as $no_editable): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($no_editable['no_fact']); ?></strong> 
                        - Estado: <span class="badge bg-secondary"><?php echo $no_editable['estado']; ?></span>
                        (No se pueden agregar servicios porque la factura está <?php echo $no_editable['estado']; ?>)
                    </li>
                    <?php endforeach; ?>
                </ul>
                <small class="d-block mt-2">Estas facturas no aparecen en la lista porque no son editables.</small>
            </div>
            <?php endif; ?>
            
        <?php elseif ($fecha_valida && empty($facturas_existentes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No se encontraron facturas existentes <strong class="text-warning">EDITABLES</strong> para este cliente en el período operativo.
                <br><small>Nota: Facturas con estado CONTABILIZADA, ANULADA, PAGADA o CERRADA no son editables.</small>
                <span class="fw-bold text-warning">Puedes crear una nueva factura.</span>
            </div>
        <?php endif; ?>
    </div> <!-- fin factura-body -->

    <!-- BOTONES DE ACCIÓN (no colapsables) -->
<div class="d-flex gap-2 mt-3">
    <?php if ($fecha_valida && !$prediccion_error && $num_detalles > 0 && $cliente_info): ?>
        <form method="post" style="display: inline;" class="form-nueva" data-index="<?php echo $index; ?>" data-numero-predicho="<?php echo $numero_predicho; ?>">
            <input type="hidden" name="factura_data" value="<?php echo htmlspecialchars(json_encode($factura)); ?>">
            <input type="hidden" name="accion_factura" value="nueva">
            <input type="hidden" name="factura_temp_id" value="<?php echo $factura_temp_id; ?>">
            <input type="hidden" name="observaciones_modificadas" class="observaciones-hidden" value="">
            <button type="submit" class="btn btn-primary btn-procesar">
                <i class="fas fa-file-medical me-2"></i>Crear Nueva Factura
            </button>
        </form>
    <?php elseif ($fecha_valida && !$prediccion_error && $num_detalles > 0 && !$cliente_info): ?>
        <button type="button" class="btn btn-secondary" disabled>
            <i class="fas fa-user-slash me-2"></i>SIN CLIENTE ENCONTRADO
        </button>
    <?php elseif ($fecha_valida && !$prediccion_error && $num_detalles == 0): ?>
        <button type="button" class="btn btn-secondary" disabled>
            <i class="fas fa-ban me-2"></i>No hay servicios para facturar
        </button>
    <?php elseif ($prediccion_error): ?>
        <button type="button" class="btn btn-danger" disabled>
            <i class="fas fa-ban me-2"></i><?php echo $mensaje_error; ?>
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-secondary" disabled>
            <i class="fas fa-ban me-2"></i>Fecha fuera de período
        </button>
    <?php endif; ?>
    
    <button type="button" class="btn btn-outline-secondary" onclick="verDetalles(<?php echo $index; ?>)">
        <i class="fas fa-search me-2"></i>Ver SQL Original
    </button>
    
    <button type="button" class="btn btn-outline-warning resetear-factura-btn" data-temp-id="<?php echo $factura_temp_id; ?>" style="display: none;">
        <i class="fas fa-undo-alt me-2"></i>Resetear factura
    </button>
</div>

</div>
<?php endforeach; ?>
<?php endif; ?>
        
        <?php if (!empty($resultados)): ?>
        <!-- Resultados -->
        <div class="card mt-4 animate__animated animate__fadeIn">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Resultados de la Importación</h5>
            </div>
            <div class="card-body">
                <?php foreach ($resultados as $resultado): ?>
                    <div class="alert alert-<?php echo $resultado['success'] ? 'success' : 'danger'; ?>">
                        <i class="fas fa-<?php echo $resultado['success'] ? 'check' : 'times'; ?>-circle me-2"></i>
                        <?php echo $resultado['message']; ?>
                        <?php if (isset($resultado['no_fact'])): ?>
                            <br>
                            <a href="ver_factura.php?id=<?php echo $resultado['factura_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fas fa-eye me-1"></i>Ver Factura <?php echo $resultado['no_fact']; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-flex gap-2 mt-3">
                    <a href="facturas.php" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>Ir a Facturas
                    </a>
                    <a href="importar_factura.php" class="btn btn-outline-secondary">
                        <i class="fas fa-upload me-2"></i>Importar Más
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-danger">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<!-- Quick Actions -->
<div class="import-quick-actions">
    <!-- Menú expandible (resto de acciones que se ocultan) -->
    <div class="import-quick-actions-expanded" id="importQuickActionsExpanded">
        <!-- Selector de facturas -->
<div class="import-quick-action import-quick-action-select">
    <i class="fas fa-file-invoice"></i>
    <button class="quick-factura-dropdown-btn" id="quickFacturaBtn" data-bs-toggle="dropdown" aria-expanded="false">
        Ir a la Factura No...
    </button>
    <ul class="dropdown-menu quick-factura-menu" id="quickFacturaMenu">
        <!-- Las opciones se cargarán dinámicamente -->
    </ul>
</div>
        
        <!-- Botones de navegación principales (columna) -->
        <div class="import-nav-column">
            <button class="import-quick-action import-quick-action-facturas" onclick="window.location.href='facturas.php'" data-tooltip="Facturas">
                <i class="fas fa-file-invoice"></i> Facturas
            </button>
            <button class="import-quick-action import-quick-action-dashboard" onclick="window.location.href='dashboard.php'" data-tooltip="Dashboard">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
            <button class="import-quick-action import-quick-action-cancelar" onclick="window.location.href='importar_factura.php'" data-tooltip="Cancelar">
                <i class="fas fa-undo-alt"></i> Cancelar
            </button>
        </div>
        
        <!-- Botón colapsar/expandir todas -->
        <div class="import-nav-buttons mt-2">
            <button class="import-quick-action import-quick-action-start" onclick="toggleGlobalCollapse()" data-tooltip="Colapsar/Expandir todas">
                <i class="fas fa-compress-alt"></i> <span id="quickGlobalToggleText">Colapsar todas</span>
            </button>
        </div>
    </div>

    <!-- Botones siempre visibles (Inicio / Final) -->
    <div class="import-quick-actions-fixed">
        <button class="import-quick-action fixed" onclick="window.scrollTo({top:0,behavior:'smooth'})" data-tooltip="Ir al inicio">
            <i class="fas fa-arrow-up"></i>
        </button>
        <button class="import-quick-action fixed" onclick="window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'})" data-tooltip="Ir al final">
            <i class="fas fa-arrow-down"></i>
        </button>
    </div>

    <!-- Botón principal que despliega el menú -->
    <button class="import-quick-action" id="importMainQuickAction" onclick="toggleImportQuickActions()" title="Más acciones">
        <i class="fas fa-ellipsis-h" id="importMainQuickActionIcon"></i>
    </button>
</div>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales para persistencia con localStorage
        const currentFileHash = '<?php echo addslashes($archivo_hash); ?>';
        const currentFileName = '<?php echo addslashes($archivo_nombre); ?>';
        const totalFacturas = <?php echo count($facturas_extraidas); ?>;

// Desplazamiento suave con offset (para evitar que el navbar tape el elemento)
function scrollToElementWithOffset(element, offset = 70) {
    if (!element) return;
    const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
    const offsetPosition = elementPosition - offset;
    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
    });
}

        // Obtener clave de localStorage para este archivo
        function getStorageKey() {
            if (!currentFileHash) return null;
            return 'import_facturas_estado_' + currentFileHash;
        }
        
        // Obtener objeto con estados de facturas (temp_id => true/false)
        function getEstadosProcesadas() {
            if (!currentFileHash) return {};
            const key = getStorageKey();
            const stored = localStorage.getItem(key);
            return stored ? JSON.parse(stored) : {};
        }
        
        // Guardar estado de una factura específica
        function setFacturaProcesada(tempId, procesada) {
            if (!currentFileHash) return;
            const key = getStorageKey();
            let estados = getEstadosProcesadas();
            if (procesada) {
                estados[tempId] = true;
            } else {
                delete estados[tempId];
            }
            localStorage.setItem(key, JSON.stringify(estados));
            marcarFacturaUI(tempId, procesada);
            actualizarProgresoGlobal();
        }
        
        // Marcar visualmente una factura (badge, deshabilitar botones, etc.)
        function marcarFacturaUI(tempId, procesada) {
            const card = document.querySelector(`.factura-card[data-temp-id="${tempId}"]`);
            if (!card) return;
            const badgeProcesada = card.querySelector('.procesada-badge');
            const badgePendiente = card.querySelector('.pendiente-badge');
            const botonesProcesar = card.querySelectorAll('.btn-procesar');
            const botonResetear = card.querySelector('.resetear-factura-btn');
            if (procesada) {
                card.classList.add('procesada');
                if (badgeProcesada) badgeProcesada.style.display = 'inline-block';
                if (badgePendiente) badgePendiente.style.display = 'none';
                botonesProcesar.forEach(btn => btn.disabled = true);
                if (botonResetear) botonResetear.style.display = 'inline-block';
                card.setAttribute('data-procesada', 'true');
            } else {
                card.classList.remove('procesada');
                if (badgeProcesada) badgeProcesada.style.display = 'none';
                if (badgePendiente) badgePendiente.style.display = 'inline-block';
                botonesProcesar.forEach(btn => btn.disabled = false);
                if (botonResetear) botonResetear.style.display = 'none';
                card.setAttribute('data-procesada', 'false');
            }
            const filtroSelect = document.getElementById('filtroFacturas');
            if (filtroSelect) {
                const filtroActual = filtroSelect.value;
                if (filtroActual === 'pendientes') {
                    card.style.display = procesada ? 'none' : 'block';
                } else if (filtroActual === 'procesadas') {
                    card.style.display = procesada ? 'block' : 'none';
                } else {
                    card.style.display = 'block';
                }
            }
        }
        
        // Restaurar todos los estados desde localStorage al cargar la página
        function restaurarEstados() {
            const estados = getEstadosProcesadas();
            Object.keys(estados).forEach(tempId => {
                if (estados[tempId]) marcarFacturaUI(tempId, true);
            });
            document.querySelectorAll('.factura-card').forEach(card => {
                const tempId = card.getAttribute('data-temp-id');
                const esProcesada = card.getAttribute('data-procesada') === 'true';
                if (!esProcesada && tempId && !estados[tempId]) {
                    const badgeProcesada = card.querySelector('.procesada-badge');
                    const badgePendiente = card.querySelector('.pendiente-badge');
                    const botonesProcesar = card.querySelectorAll('.btn-procesar');
                    const botonResetear = card.querySelector('.resetear-factura-btn');
                    if (badgeProcesada) badgeProcesada.style.display = 'none';
                    if (badgePendiente) badgePendiente.style.display = 'inline-block';
                    botonesProcesar.forEach(btn => btn.disabled = false);
                    if (botonResetear) botonResetear.style.display = 'none';
                    card.classList.remove('procesada');
                    card.setAttribute('data-procesada', 'false');
                }
            });
            actualizarProgresoGlobal();
        }
        
        // Actualizar barra de progreso y contadores
		function actualizarProgresoGlobal() {
			const estados = getEstadosProcesadas();
			let procesadas = 0;
			for (let key in estados) { if (estados[key]) procesadas++; }
			const pendientes = totalFacturas - procesadas;
			const porcentaje = totalFacturas > 0 ? Math.round((procesadas / totalFacturas) * 100) : 0;

			const barra = document.getElementById('progresoImportacion');
			if (barra) {
				barra.style.width = porcentaje + '%';
				barra.innerText = porcentaje + '%';
			}

			// Actualizar contadores de la barra de progreso
			const procesadasSpan = document.getElementById('procesadasCount');
			if (procesadasSpan) procesadasSpan.innerText = procesadas;
			const pendientesSpan = document.getElementById('pendientesCount');
			if (pendientesSpan) pendientesSpan.innerText = pendientes;

			// ACTUALIZAR TAMBIÉN LOS NUEVOS SPANS DEL ALERT
			const procesadasAlert = document.getElementById('procesadasAlert');
			if (procesadasAlert) procesadasAlert.innerText = procesadas;
			const pendientesAlert = document.getElementById('pendientesAlert');
			if (pendientesAlert) pendientesAlert.innerText = pendientes;

			verificarTodasProcesadas(); // si ya existe esta llamada
		}
				
// Inicializar el dropdown personalizado del filtro
function initCustomFilter() {
    const button = document.getElementById('filtroFacturasBtn');
    const dropdownItems = document.querySelectorAll('#filtroFacturasBtn + .dropdown-menu .dropdown-item');
    const hiddenInput = document.getElementById('filtroFacturasValor');

    dropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const valor = this.getAttribute('data-value');
            const iconHtml = this.querySelector('i').outerHTML;
            const text = this.innerText.trim();
            // Actualizar botón
            button.innerHTML = `${iconHtml} ${text}`;
            // Guardar valor oculto (opcional)
            if (hiddenInput) hiddenInput.value = valor;
            // Aplicar filtro
            aplicarFiltro(valor);
        });
    });
}

// La función aplicarFiltro se mantiene exactamente igual
function aplicarFiltro(valor) {
    const cards = document.querySelectorAll('.factura-card');
    if (valor === 'pendientes') {
        cards.forEach(card => {
            const esProcesada = card.getAttribute('data-procesada') === 'true';
            card.style.display = esProcesada ? 'none' : 'block';
        });
        const primera = Array.from(cards).find(card => card.style.display !== 'none');
        if (primera) primera.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else if (valor === 'procesadas') {
        cards.forEach(card => {
            const esProcesada = card.getAttribute('data-procesada') === 'true';
            card.style.display = esProcesada ? 'block' : 'none';
        });
        const primera = Array.from(cards).find(card => card.style.display !== 'none');
        if (primera) primera.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        cards.forEach(card => card.style.display = 'block');
    }
}

// Al cargar la página, inicializar y establecer el valor por defecto
document.addEventListener('DOMContentLoaded', function() {
    initCustomFilter();
    // Opcional: si quieres llamar a aplicarFiltro con el valor inicial (todas)
    aplicarFiltro('todas');
});
		
		
		// Reiniciar todo el estado del archivo actual
        function resetearTodoElArchivo() {
            if (!currentFileHash) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No hay archivo cargado' });
                return;
            }
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle me-2"></i>¿Reiniciar todo el progreso?',
                html: `<p>Se marcarán como pendientes <strong>todas las ${totalFacturas} facturas</strong> de este archivo.</p><p class="text-secondary-custom small">Esta acción no se puede deshacer fácilmente, pero puedes volver a procesar las facturas manualmente.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, reiniciar todo',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                iconColor: '#ffc107'
            }).then((result) => {
                if (result.isConfirmed) {
                    const key = getStorageKey();
                    localStorage.removeItem(key);
                    document.querySelectorAll('.factura-card').forEach(card => {
                        const tempId = card.getAttribute('data-temp-id');
                        if (tempId) marcarFacturaUI(tempId, false);
                    });
                    actualizarProgresoGlobal();
                    Swal.fire({
                        icon: 'success',
                        title: '<i class="fas fa-check-circle me-2"></i>Reiniciado',
                        html: '<p>Todas las facturas están pendientes nuevamente.</p>',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        confirmButtonColor: '#28a745',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)',
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        }
        
        // Eventos: al enviar formularios de procesar, marcar como procesada (optimista)
        function bindFormSubmitEvents() {
            document.querySelectorAll('.form-nueva, .form-agregar').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const tempId = this.querySelector('input[name="factura_temp_id"]').value;
                    if (tempId) setFacturaProcesada(tempId, true);
                });
            });
            document.querySelectorAll('.resetear-factura-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tempId = this.getAttribute('data-temp-id');
                    if (tempId) {
                        Swal.fire({
                            title: '<i class="fas fa-question-circle me-2"></i>¿Reiniciar esta factura?',
                            html: `<p>La factura volverá a estado <strong class="text-warning">pendiente</strong> y podrás procesarla nuevamente.</p>`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, reiniciar',
                            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                            background: 'var(--win-bg-secondary)',
                            color: 'var(--win-text-primary)'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                setFacturaProcesada(tempId, false);
                                Swal.fire({
                                    icon: 'success',
                                    title: '<i class="fas fa-undo-alt me-2"></i>Factura reiniciada',
                                    html: `<p>La factura ha sido marcada como <strong class="text-warning">pendiente</strong> nuevamente.</p>`,
                                    confirmButtonText: '<i class="fas fa-check-circle me-2"></i>Entendido',
                                    confirmButtonColor: '#28a745',
                                    background: 'var(--win-bg-secondary)',
                                    color: 'var(--win-text-primary)',
                                    timer: 3000,
                                    timerProgressBar: true
                                });
                            }
                        });
                    }
                });
            });
        }
        
        // Funciones auxiliares
        function formatearFecha(fecha) {
            if (!fecha) return '';
            let fechaSolo = fecha.split(' ')[0];
            if (fechaSolo.indexOf('-') !== -1) {
                let partes = fechaSolo.split('-');
                return partes[2] + '/' + partes[1] + '/' + partes[0];
            }
            return fechaSolo;
        }
        
        function validarSubida() {
            const fileInput = document.querySelector('input[name="archivo_sql"]');
            if (!fileInput.files[0]) {
                Swal.fire({ icon: 'warning', title: 'Archivo requerido', text: 'Por favor selecciona un archivo SQL', background: 'var(--win-bg-secondary)', color: 'var(--win-text-primary)', confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido', confirmButtonColor: 'var(--win-accent)' });
                return false;
            }
            const file = fileInput.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'sql' && ext !== 'txt') {
                Swal.fire({ icon: 'error', title: 'Extensión no válida', html: `<p>El archivo debe ser .sql o .txt</p>`, background: 'var(--win-bg-secondary)', color: 'var(--win-text-primary)', confirmButtonText: 'Entendido' });
                fileInput.value = '';
                return false;
            }
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({ icon: 'error', title: 'Archivo demasiado grande', text: 'Máximo 10 MB', background: 'var(--win-bg-secondary)', color: 'var(--win-text-primary)' });
                fileInput.value = '';
                return false;
            }
            Swal.fire({
                title: '<i class="fas fa-spinner fa-spin me-2"></i>Analizando archivo...',
                text: 'Por favor espera mientras se procesa el archivo',
                allowOutsideClick: false,
                showConfirmButton: false,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
                didOpen: () => {
                    Swal.showLoading();
                    setTimeout(() => { document.getElementById('uploadForm').submit(); }, 100);
                }
            });
            return false;
        }
        
        function validarSubidaNavbar() { return validarSubida(); }
        
        function verDetalles(index) {
            const facturasData = <?php echo json_encode($facturas_extraidas); ?>;
            if (facturasData && facturasData[index]) {
                const f = facturasData[index];
                let detallesHtml = '';
                if (f.detalles && f.detalles.length > 0) {
                    detallesHtml = '<h6 class="mt-3"><i class="fas fa-list me-2"></i>Detalles SQL:</h6><div style="max-height:200px;overflow:auto;"><table class="table table-sm"><thead><tr><th>Servicio ID</th><th>Cant</th><th>Total</th></tr></thead><tbody>';
                    f.detalles.forEach(d => {
                        detallesHtml += `<tr><td>${d.servicio_id || 'N/A'}</td><td>${d.cantidad || 1}</td><td>$${parseFloat(d.total_linea || 0).toFixed(2)}</td></tr>`;
                    });
                    detallesHtml += '</tbody></table></div>';
                }
                let sqlOriginal = `-- FACTURA ${f.temp_id || index+1}: ${f.cliente_nombre || 'N/A'}\n`;
                sqlOriginal += `INSERT INTO tbl_fact (cliente_id, tipo_pago_id, subtotal, total_general, estado, fecha_emision, observaciones) VALUES (\n`;
                sqlOriginal += `    ${f.cliente_id || 'NULL'}, ${f.tipo_pago_id || 'NULL'}, ${parseFloat(f.subtotal || 0).toFixed(2)}, ${parseFloat(f.total_general || 0).toFixed(2)},\n`;
                sqlOriginal += `    '${f.estado || 'PENDIENTE'}', '${f.fecha_emision || ''}', ${f.observaciones ? `'${f.observaciones.replace(/'/g, "\\'")}'` : 'NULL'}\n`;
                sqlOriginal += `);\nSET @fid_${f.temp_id || index+1} = LAST_INSERT_ID();\n\n`;
                if (f.detalles && f.detalles.length > 0) {
                    sqlOriginal += `-- DETALLES\n`;
                    f.detalles.forEach(d => {
                        sqlOriginal += `INSERT INTO tbl_fact_detalle (factura_id, servicio_id, cantidad, precio_unitario, total_linea) VALUES (\n`;
                        sqlOriginal += `    @fid_${f.temp_id || index+1}, ${d.servicio_id || 'NULL'}, ${d.cantidad || 1}, ${parseFloat(d.precio_unitario || 0).toFixed(2)}, ${parseFloat(d.total_linea || 0).toFixed(2)}\n);\n`;
                    });
                }
                sqlOriginal += `-- FIN`;
                const sqlEscapado = sqlOriginal.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                Swal.fire({
                    title: '<i class="fas fa-file-code me-2"></i>SQL Original',
                    html: `<div class="text-start"><div class="row mb-2"><div class="col-6"><strong>Cliente ID:</strong></div><div class="col-6">${f.cliente_id || 'N/A'}</div></div><div class="row mb-2"><div class="col-6"><strong>Fecha:</strong></div><div class="col-6">${formatearFecha(f.fecha_emision) || 'N/A'}</div></div><div class="row mb-2"><div class="col-6"><strong>Total:</strong></div><div class="col-6"><strong style="color: var(--win-accent);">$${parseFloat(f.total_general || 0).toFixed(2)}</strong></div></div>${f.observaciones ? `<p class="mb-2"><strong>Obs:</strong> ${f.observaciones}</p>` : ''}${detallesHtml}<div class="d-flex justify-content-between align-items-center mt-2 mb-1"><h6 class="mb-0"><i class="fas fa-database me-2"></i>Código SQL:</h6><button type="button" class="btn btn-sm btn-outline-primary" onclick="copiarSQL(event)" data-sql="${sqlEscapado.replace(/"/g, '&quot;')}"><i class="fas fa-copy me-1"></i>Copiar SQL</button></div><pre style="background: #1e1e2d; color: #e2e8f0; padding: 10px; border-radius: 6px; font-size: 11px; max-height: 200px; overflow: auto; white-space: pre-wrap;">${sqlEscapado}</pre></div>`,
                    width: '650px',
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Cerrar',
                    confirmButtonColor: 'var(--win-accent)',
                    showCloseButton: true,
                    didOpen: () => {
                        const btn = document.querySelector('[data-sql]');
                        if (btn) {
                            btn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                const sql = this.getAttribute('data-sql').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"');
                                navigator.clipboard.writeText(sql).then(() => {
                                    Swal.fire({ icon: 'success', title: '¡Copiado!', text: 'SQL copiado al portapapeles', timer: 1500, showConfirmButton: false, background: 'var(--win-bg-secondary)', color: 'var(--win-text-primary)' });
                                });
                            });
                        }
                    }
                });
            }
        }
        
        function copiarSQL(event) {
            // La funcionalidad ya está dentro de verDetalles, pero mantenemos por si se llama externamente
            const sql = event.currentTarget.getAttribute('data-sql').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"');
            navigator.clipboard.writeText(sql).then(() => {
                Swal.fire({ icon: 'success', title: '¡Copiado!', text: 'SQL copiado al portapapeles', timer: 1500, showConfirmButton: false });
            });
        }
        
        
        // ==================== COLAPSO DE FACTURAS PRINCIPALES ====================
        function getCollapseStorageKey() { return currentFileHash ? 'import_facturas_collapsed_' + currentFileHash : null; }
        function saveCollapseState(tempId, isCollapsed) {
            if (!currentFileHash) return;
            const key = getCollapseStorageKey();
            let collapsed = localStorage.getItem(key);
            collapsed = collapsed ? JSON.parse(collapsed) : {};
            if (isCollapsed) collapsed[tempId] = true;
            else delete collapsed[tempId];
            localStorage.setItem(key, JSON.stringify(collapsed));
        }
        function applyCollapseState(card, tempId, isCollapsed) {
            const icon = card.querySelector('.collapse-icon');
            if (isCollapsed) {
                card.classList.add('collapsed');
                if (icon) { icon.classList.remove('fa-chevron-up'); icon.classList.add('fa-chevron-down'); }
            } else {
                card.classList.remove('collapsed');
                if (icon) { icon.classList.remove('fa-chevron-down'); icon.classList.add('fa-chevron-up'); }
            }
        }
function toggleFacturaCollapse(button) {
    const card = button.closest('.factura-card');
    if (!card) return;
    const tempId = card.getAttribute('data-temp-id');
    const isCollapsed = card.classList.contains('collapsed');
    toggleFacturaCard(card, tempId, !isCollapsed);
    updateGlobalToggleButton();
}
        function restoreCollapseStates() {
            if (!currentFileHash) return;
            const key = getCollapseStorageKey();
            const collapsed = localStorage.getItem(key);
            if (!collapsed) return;
            const data = JSON.parse(collapsed);
            document.querySelectorAll('.factura-card').forEach(card => {
                const tempId = card.getAttribute('data-temp-id');
                if (tempId && data[tempId]) applyCollapseState(card, tempId, true);
                else applyCollapseState(card, tempId, false);
            });
        }
        function initCollapseEvents() {
            document.querySelectorAll('.toggle-collapse-btn').forEach(btn => {
                btn.removeEventListener('click', collapseHandler);
                btn.addEventListener('click', collapseHandler);
            });
        }
        function collapseHandler(e) { e.stopPropagation(); toggleFacturaCollapse(this); }
        
        // ==================== COLAPSO DE CARDS INTERNOS ====================
        function getInnerCollapseStorageKey(parentTempId) { return currentFileHash ? 'import_facturas_inner_collapsed_' + currentFileHash + '_' + parentTempId : null; }
        function saveInnerCollapseState(parentTempId, innerId, isCollapsed) {
            if (!currentFileHash) return;
            const key = getInnerCollapseStorageKey(parentTempId);
            let collapsed = localStorage.getItem(key) ? JSON.parse(localStorage.getItem(key)) : {};
            if (isCollapsed) collapsed[innerId] = true;
            else delete collapsed[innerId];
            localStorage.setItem(key, JSON.stringify(collapsed));
        }
        function applyInnerCollapseState(innerCard, parentTempId, innerId, isCollapsed) {
            const icon = innerCard.querySelector('.inner-collapse-icon');
            if (isCollapsed) {
                innerCard.classList.add('collapsed');
                if (icon) { icon.classList.remove('fa-chevron-up'); icon.classList.add('fa-chevron-down'); }
            } else {
                innerCard.classList.remove('collapsed');
                if (icon) { icon.classList.remove('fa-chevron-down'); icon.classList.add('fa-chevron-up'); }
            }
        }
function toggleInnerCollapse(button) {
    const innerCard = button.closest('.inner-card');
    if (!innerCard) return;
    const innerId = button.getAttribute('data-inner-id');
    const parentCard = innerCard.closest('.factura-card');
    const parentTempId = parentCard ? parentCard.getAttribute('data-temp-id') : null;
    const isCollapsed = innerCard.classList.contains('collapsed');
    toggleInnerCard(innerCard, parentTempId, innerId, !isCollapsed);
}
        function restoreInnerCollapseStates() {
            if (!currentFileHash) return;
            document.querySelectorAll('.factura-card').forEach(card => {
                const parentTempId = card.getAttribute('data-temp-id');
                if (!parentTempId) return;
                const key = getInnerCollapseStorageKey(parentTempId);
                const collapsed = localStorage.getItem(key);
                if (!collapsed) return;
                const data = JSON.parse(collapsed);
                card.querySelectorAll('.inner-card').forEach(innerCard => {
                    const innerId = innerCard.getAttribute('data-inner-id');
                    if (innerId && data[innerId]) applyInnerCollapseState(innerCard, parentTempId, innerId, true);
                    else applyInnerCollapseState(innerCard, parentTempId, innerId, false);
                });
            });
        }
        function initInnerCollapseEvents() {
            document.querySelectorAll('.toggle-inner-collapse').forEach(btn => {
                btn.removeEventListener('click', innerCollapseHandler);
                btn.addEventListener('click', innerCollapseHandler);
            });
        }
        function innerCollapseHandler(e) { e.stopPropagation(); toggleInnerCollapse(this); }
        
        // ==================== COLAPSAR / EXPANDIR TODAS ====================
        function toggleGlobalCollapse() {
            const cards = document.querySelectorAll('.factura-card');
            let anyExpanded = false;
            cards.forEach(card => { if (!card.classList.contains('collapsed')) anyExpanded = true; });
            const shouldCollapse = anyExpanded; // si alguna expandida -> colapsar todas
            cards.forEach(card => {
                const tempId = card.getAttribute('data-temp-id');
                if (tempId) {
                    const currentState = card.classList.contains('collapsed');
                    if (shouldCollapse && !currentState) {
                        applyCollapseState(card, tempId, true);
                        saveCollapseState(tempId, true);
                    } else if (!shouldCollapse && currentState) {
                        applyCollapseState(card, tempId, false);
                        saveCollapseState(tempId, false);
                    }
                }
            });
            const btnText = document.getElementById('globalToggleText');
            const btnIcon = document.querySelector('#globalToggleCollapseBtn i');
            if (shouldCollapse) {
                if (btnText) btnText.innerText = 'Expandir todas';
                if (btnIcon) btnIcon.className = 'fas fa-expand-alt';
            } else {
                if (btnText) btnText.innerText = 'Colapsar todas';
                if (btnIcon) btnIcon.className = 'fas fa-compress-alt';
            }
        }
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
			restaurarEstados();
            bindFormSubmitEvents();
            restoreCollapseStates();
            initCollapseEvents();
            restoreInnerCollapseStates();
            initInnerCollapseEvents();
            document.querySelectorAll('.observaciones-input').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    const idx = this.getAttribute('data-index');
                    let val = this.value.replace(/[\r\n]+/g, ' ');
                    const card = this.closest('.factura-card');
                    card.querySelectorAll('.observaciones-hidden').forEach(hid => hid.value = val);
                    card.querySelectorAll('input[name="factura_data"]').forEach(inp => {
                        try { let data = JSON.parse(inp.value); data.observaciones = val; inp.value = JSON.stringify(data); } catch(e) {}
                    });
                });
                textarea.dispatchEvent(new Event('input'));
            });
            const navbarSelect = document.getElementById('navbarFacturaSelector');
            if (navbarSelect) {
                const facturas = document.querySelectorAll('.factura-card');
                facturas.forEach((card, idx) => {
                    const option = document.createElement('option');
                    option.value = idx;
                    const cliente = card.querySelector('.factura-header small')?.innerText || 'Factura';
                    option.textContent = `Factura #${idx+1} - ${cliente.substring(0,30)}`;
                    navbarSelect.appendChild(option);
                });
            }
            // Sincronizar texto del botón global
            const anyExpanded = document.querySelectorAll('.factura-card:not(.collapsed)').length > 0;
            const btnText = document.getElementById('globalToggleText');
            const btnIcon = document.querySelector('#globalToggleCollapseBtn i');
            if (anyExpanded) {
                if (btnText) btnText.innerText = 'Colapsar todas';
                if (btnIcon) btnIcon.className = 'fas fa-compress-alt';
            } else {
                if (btnText) btnText.innerText = 'Expandir todas';
                if (btnIcon) btnIcon.className = 'fas fa-expand-alt';
            }
        });
		
        function toggleImportQuickActions() {
            const exp = document.getElementById('importQuickActionsExpanded');
            if (exp.classList.contains('show')) {
                exp.classList.remove('show');
                document.getElementById('importMainQuickActionIcon').className = 'fas fa-ellipsis-h';
            } else {
                exp.classList.add('show');
                document.getElementById('importMainQuickActionIcon').className = 'fas fa-times';
            }
        }
// Cargar opciones en el dropdown del navbar con scroll
function cargarNavbarFacturas() {
    const menu = document.getElementById('navbarFacturaMenu');
    if (!menu) return;
    menu.innerHTML = '';
    const facturas = document.querySelectorAll('.factura-card');
    
    if (facturas.length === 0) {
        const item = document.createElement('li');
        item.innerHTML = `<a class="dropdown-item-custom" href="#" style="cursor: default; opacity: 0.6; justify-content: center;">
                            <i class="fas fa-info-circle"></i>
                            <span class="dropdown-text">No hay facturas disponibles</span>
                          </a>`;
        menu.appendChild(item);
        return;
    }
    
    facturas.forEach((card, idx) => {
        const clienteElem = card.querySelector('.factura-header small');
        const cliente = clienteElem ? clienteElem.innerText.substring(0, 35) : 'Factura sin cliente';
        const totalSpan = card.querySelector('.badge-success');
        const total = totalSpan ? totalSpan.innerText.substring(0, 20) : '';
        
        const item = document.createElement('li');
        item.innerHTML = `<a class="dropdown-item-custom" href="#" data-idx="${idx}">
                            <i class="fas fa-file-invoice"></i>
                            <span class="dropdown-text">Factura #${idx+1} - ${cliente}</span>
                            ${total ? `<small style="font-size: 10px; opacity: 0.7; margin-left: auto;">${total}</small>` : ''}
                          </a>`;
        
        const link = item.querySelector('a');
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const elem = document.getElementById(`factura-${idx}`);
            if (elem) {
                // Cerrar el dropdown antes de scroll
                const dropdownBtn = document.getElementById('navbarFacturaBtn');
                if (dropdownBtn && typeof bootstrap !== 'undefined') {
                    const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn);
                    if (bsDropdown) bsDropdown.hide();
                }
                
                scrollToElementWithOffset(elem, 70);
                setTimeout(() => {
                    const header = elem.querySelector('.factura-header');
                    if (header) {
                        header.classList.remove('flash');
                        void header.offsetHeight;
                        header.classList.add('flash');
                        setTimeout(() => {
                            header.classList.remove('flash');
                        }, 4000);
                    }
                }, 400);
            }
        });
        menu.appendChild(item);
    });
    
    // Log para debug
    console.log(`Navbar dropdown cargado con ${facturas.length} facturas`);
}

// Cargar opciones en el dropdown del quick action con scroll
function cargarQuickFacturas() {
    const menu = document.getElementById('quickFacturaMenu');
    if (!menu) return;
    menu.innerHTML = '';
    const facturas = document.querySelectorAll('.factura-card');
    
    if (facturas.length === 0) {
        const item = document.createElement('li');
        item.innerHTML = `<a class="dropdown-item-custom" href="#" style="cursor: default; opacity: 0.6; justify-content: center;">
                            <i class="fas fa-info-circle"></i>
                            <span class="dropdown-text">No hay facturas disponibles</span>
                          </a>`;
        menu.appendChild(item);
        return;
    }
    
    facturas.forEach((card, idx) => {
        const clienteElem = card.querySelector('.factura-header small');
        const cliente = clienteElem ? clienteElem.innerText.substring(0, 35) : 'Factura sin cliente';
        const totalSpan = card.querySelector('.badge-success');
        const total = totalSpan ? totalSpan.innerText.substring(0, 20) : '';
        
        const item = document.createElement('li');
        item.innerHTML = `<a class="dropdown-item-custom" href="#" data-idx="${idx}">
                            <i class="fas fa-file-invoice"></i>
                            <span class="dropdown-text">Factura #${idx+1} - ${cliente}</span>
                            ${total ? `<small style="font-size: 10px; opacity: 0.7; margin-left: auto;">${total}</small>` : ''}
                          </a>`;
        
        const link = item.querySelector('a');
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const elem = document.getElementById(`factura-${idx}`);
            if (elem) {
                // Cerrar el dropdown antes de scroll
                const dropdownBtn = document.getElementById('quickFacturaBtn');
                if (dropdownBtn && typeof bootstrap !== 'undefined') {
                    const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn);
                    if (bsDropdown) bsDropdown.hide();
                }
                
                scrollToElementWithOffset(elem, 70);
                setTimeout(() => {
                    const header = elem.querySelector('.factura-header');
                    if (header) {
                        header.classList.remove('flash');
                        void header.offsetHeight;
                        header.classList.add('flash');
                        setTimeout(() => {
                            header.classList.remove('flash');
                        }, 4000);
                    }
                }, 400);
            }
        });
        menu.appendChild(item);
    });
    
    // Log para debug
    console.log(`Quick dropdown cargado con ${facturas.length} facturas`);
}

// Inicializar los dropdowns personalizados
function initFacturaDropdowns() {
    // Inicializar Bootstrap dropdowns
    if (typeof bootstrap !== 'undefined') {
        const navbarBtn = document.getElementById('navbarFacturaBtn');
        const quickBtn = document.getElementById('quickFacturaBtn');
        
        if (navbarBtn) {
            new bootstrap.Dropdown(navbarBtn);
        }
        if (quickBtn) {
            new bootstrap.Dropdown(quickBtn);
        }
    }
    
    // Cargar las opciones
    cargarNavbarFacturas();
    cargarQuickFacturas();
}

// También actualizar cuando haya cambios dinámicos
function refreshDropdowns() {
    cargarNavbarFacturas();
    cargarQuickFacturas();
}

// Observador de cambios para refrescar dropdowns automáticamente
function observeFacturasChanges() {
    const observer = new MutationObserver(function(mutations) {
        let shouldRefresh = false;
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && 
                (mutation.target.classList?.contains('factura-card') || 
                 mutation.target.closest?.('.factura-card'))) {
                shouldRefresh = true;
            }
        });
        if (shouldRefresh) {
            setTimeout(refreshDropdowns, 100);
        }
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
}


        document.addEventListener('DOMContentLoaded', function() {
			    initFacturaDropdowns();
            const fastSelect = document.getElementById('facturaSelector');
            if (fastSelect) {
                const facturas = document.querySelectorAll('.factura-card');
                facturas.forEach((card, idx) => {
                    const opt = document.createElement('option');
                    opt.value = idx;
                    const cliente = card.querySelector('.factura-header small')?.innerText || 'Factura';
                    opt.textContent = `Factura #${idx+1} - ${cliente.substring(0,30)}`;
                    fastSelect.appendChild(opt);
                });
            }
            const anyExpanded = document.querySelectorAll('.factura-card:not(.collapsed)').length > 0;
            const quickText = document.getElementById('quickGlobalToggleText');
            const quickIcon = document.querySelector('#quickGlobalToggleCollapseBtn i');
            if (anyExpanded) {
                if (quickText) quickText.innerText = 'Colapsar todas';
                if (quickIcon) quickIcon.className = 'fas fa-compress-alt';
            } else {
                if (quickText) quickText.innerText = 'Expandir todas';
                if (quickIcon) quickIcon.className = 'fas fa-expand-alt';
            }
        });
// Función para verificar si todas las facturas están procesadas
function verificarTodasProcesadas() {
    const procesadas = parseInt(document.getElementById('procesadasCount')?.innerText || '0');
    if (procesadas === totalFacturas && totalFacturas > 0) {
        Swal.fire({
            icon: 'success',
            title: '<i class="fas fa-tasks me-2"></i>¡El Archivo Analizado ha sido procesado en su totalidad!',
            html: `
                <p>Todas las <strong class="text-warning">${totalFacturas} facturas</strong> que contiene han sido procesadas exitosamente con anterioridad.</p>
                <p class="text-secondary-custom small">Puedes reiniciar el progreso, ir al dashboard o cerrar este mensaje.</p>
            `,
            showCloseButton: true,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Reiniciar todo',
            denyButtonText: '<i class="fas fa-home me-2"></i>Dashboard',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cerrar',
            confirmButtonColor: '#d33',
            denyButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                resetearTodoElArchivo();
            } else if (result.isDenied) {
                window.location.href = 'dashboard.php';
            }
            // Si es cancelado (Cerrar o X) no hace nada
        });
    }
}
    </script>
<script>
/**
 * Lógica Unificada de Colapso y Persistencia
 */

// Configuración de íconos
const ICON_OPEN = 'fa-chevron-up';
const ICON_CLOSED = 'fa-chevron-down';

// --- UTILIDADES DE ALMACENAMIENTO ---
function getCollapseStorageKey() { 
    return currentFileHash ? 'import_facturas_collapsed_' + currentFileHash : null; 
}

function getInnerCollapseStorageKey(parentTempId) { 
    return currentFileHash ? `import_facturas_inner_collapsed_${currentFileHash}_${parentTempId}` : null; 
}

// --- FUNCIONES CORE DE CAMBIO DE ESTADO ---

/**
 * Toglea una factura principal
 */
function toggleFacturaCard(card, forcedState) {
    if (!card) return;
    const tempId = card.getAttribute('data-temp-id');
    const icon = card.querySelector('.collapse-icon');
    
    const isCurrentlyCollapsed = card.classList.contains('collapsed');
    const shouldCollapse = (forcedState !== undefined) ? forcedState : !isCurrentlyCollapsed;

    if (shouldCollapse) {
        card.classList.add('collapsed');
        if (icon) {
            icon.classList.remove(ICON_OPEN);
            icon.classList.add(ICON_CLOSED);
        }
        saveCollapseState(tempId, true);
    } else {
        card.classList.remove('collapsed');
        if (icon) {
            icon.classList.remove(ICON_CLOSED);
            icon.classList.add(ICON_OPEN);
        }
        saveCollapseState(tempId, false);
    }
    updateGlobalToggleButton();
}

/**
 * Toglea una card interna (facturas existentes)
 */
function toggleInnerCard(innerCard, forcedState) {
    if (!innerCard) return;
    const innerId = innerCard.getAttribute('data-inner-id');
    const parentCard = innerCard.closest('.factura-card');
    const parentTempId = parentCard ? parentCard.getAttribute('data-temp-id') : null;
    const icon = innerCard.querySelector('.inner-collapse-icon');
    
    const isCurrentlyCollapsed = innerCard.classList.contains('collapsed');
    const shouldCollapse = (forcedState !== undefined) ? forcedState : !isCurrentlyCollapsed;

    if (shouldCollapse) {
        innerCard.classList.add('collapsed');
        if (icon) {
            icon.classList.remove(ICON_OPEN);
            icon.classList.add(ICON_CLOSED);
        }
        if (parentTempId) saveInnerCollapseState(parentTempId, innerId, true);
    } else {
        innerCard.classList.remove('collapsed');
        if (icon) {
            icon.classList.remove(ICON_CLOSED);
            icon.classList.add(ICON_OPEN);
        }
        if (parentTempId) saveInnerCollapseState(parentTempId, innerId, false);
    }
}

// --- PERSISTENCIA (LocalStorage) ---

function saveCollapseState(tempId, isCollapsed) {
    const key = getCollapseStorageKey();
    if (!key) return;
    let data = localStorage.getItem(key) ? JSON.parse(localStorage.getItem(key)) : {};
    if (isCollapsed) data[tempId] = true;
    else delete data[tempId];
    localStorage.setItem(key, JSON.stringify(data));
}

function saveInnerCollapseState(parentTempId, innerId, isCollapsed) {
    const key = getInnerCollapseStorageKey(parentTempId);
    if (!key) return;
    let data = localStorage.getItem(key) ? JSON.parse(localStorage.getItem(key)) : {};
    if (isCollapsed) data[innerId] = true;
    else delete data[innerId];
    localStorage.setItem(key, JSON.stringify(data));
}

// --- SINCRONIZACIÓN GLOBAL ---

function updateGlobalToggleButton() {
    const cards = document.querySelectorAll('.factura-card');
    let anyExpanded = false;
    cards.forEach(card => { if (!card.classList.contains('collapsed')) anyExpanded = true; });
    
    const elements = [
        { text: document.getElementById('globalToggleText'), icon: document.querySelector('#globalToggleCollapseBtn i') },
        { text: document.getElementById('quickGlobalToggleText'), icon: document.querySelector('#quickGlobalToggleText i') }
    ];

    elements.forEach(el => {
        if (el.text) el.text.innerText = anyExpanded ? 'Colapsar todas' : 'Expandir todas';
        if (el.icon) el.icon.className = anyExpanded ? 'fas fa-compress-alt' : 'fas fa-expand-alt';
    });
}

/**
 * Función para el botón del Navbar "Colapsar/Expandir todas"
 */
window.toggleGlobalCollapse = function() {
    const cards = document.querySelectorAll('.factura-card');
    let anyExpanded = false;
    cards.forEach(card => { if (!card.classList.contains('collapsed')) anyExpanded = true; });
    
    const shouldCollapseAll = anyExpanded;
    cards.forEach(card => toggleFacturaCard(card, shouldCollapseAll));
};

// --- INICIALIZACIÓN DE EVENTOS ---

function initCollapseHandlers() {
    // 1. Click en el Header de Factura Principal
    document.querySelectorAll('.factura-header').forEach(header => {
        header.style.cursor = 'pointer';
        header.onclick = function(e) {
            if (e.target.closest('button') && !e.target.closest('.toggle-collapse-btn')) return;
            const card = this.closest('.factura-card');
            toggleFacturaCard(card);
        };
    });

    // 2. Click en el botón Chevron específico
    document.querySelectorAll('.toggle-collapse-btn').forEach(btn => {
        btn.onclick = function(e) {
            e.stopPropagation();
            const card = this.closest('.factura-card');
            toggleFacturaCard(card);
        };
    });

    // 3. Click en Headers de Facturas Internas
    document.querySelectorAll('.inner-card .d-flex').forEach(header => {
        header.style.cursor = 'pointer';
        header.onclick = function(e) {
            if (e.target.closest('button') && !e.target.closest('.toggle-inner-collapse')) return;
            const innerCard = this.closest('.inner-card');
            toggleInnerCard(innerCard);
        };
    });
}

/**
 * Restaura el estado desde LocalStorage al cargar
 */
function restoreAllCollapseStates() {
    const mainKey = getCollapseStorageKey();
    if (mainKey) {
        const mainData = localStorage.getItem(mainKey) ? JSON.parse(localStorage.getItem(mainKey)) : {};
        document.querySelectorAll('.factura-card').forEach(card => {
            const tempId = card.getAttribute('data-temp-id');
            if (mainData[tempId]) toggleFacturaCard(card, true);
            else toggleFacturaCard(card, false);
        });
    }

    document.querySelectorAll('.factura-card').forEach(card => {
        const parentTempId = card.getAttribute('data-temp-id');
        const innerKey = getInnerCollapseStorageKey(parentTempId);
        if (innerKey) {
            const innerData = localStorage.getItem(innerKey) ? JSON.parse(localStorage.getItem(innerKey)) : {};
            card.querySelectorAll('.inner-card').forEach(innerCard => {
                const innerId = innerCard.getAttribute('data-inner-id');
                if (innerData[innerId]) toggleInnerCard(innerCard, true);
                else toggleInnerCard(innerCard, false);
            });
        }
    });
}

// Inicializar todo al cargar el DOM
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        initCollapseHandlers();
        restoreAllCollapseStates();
        updateGlobalToggleButton();
        
        const container = document.querySelector('.main-container');
        if (container) {
            const observer = new MutationObserver(() => {
                initCollapseHandlers();
                updateGlobalToggleButton();
            });
            observer.observe(container, { childList: true });
        }
    }, 100);
});
</script>

</body>
</html>