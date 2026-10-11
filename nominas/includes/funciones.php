<?php
// includes/functions.php


/**
 * Convierte "YYYY-MM-DD" (o "YYYY-MM") en un DateTimeImmutable sin usar strtotime().
 *
 * El PHP de este servidor es de 32 bits (PHP_INT_SIZE=4), así que strtotime() no
 * puede representar ningún epoch posterior al 19/01/2038: devuelve false con el
 * aviso "Epoch doesn't fit in a PHP integer" y todas las comparaciones posteriores
 * se calculan sobre 1970. DateTime trabaja con el calendario y no tiene ese tope.
 *
 * @param string $fecha
 * @return DateTimeImmutable|null null si la fecha no es interpretable
 */
if (!function_exists('fechaComoDateTime')) {
function fechaComoDateTime($fecha) {
    $texto = trim((string)$fecha);
    if ($texto === '') { return null; }

    // "YYYY-MM" se completa con el día 1; "YYYY-MM-DD" se toma tal cual.
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $texto, $m)) {
        $texto = $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-01';
    }

    $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($texto, 0, 10));
    if ($d === false) { return null; }

    // createFromFormat acepta fechas desbordadas (2027-02-31) corriéndolas en
    // silencio; se descartan para no propagar periodos inventados.
    if ($d->format('Y-m-d') !== substr($texto, 0, 10)) { return null; }

    return $d;
}
}


/**
 * último día del mes al que pertenece una fecha "YYYY-MM-DD" o "YYYY-MM".
 * Reemplaza date('Y-m-t', strtotime($x)) por el motivo del límite de 32 bits.
 *
 * @param string $fecha
 * @return string|null "YYYY-MM-DD" o null si la fecha no es interpretable
 */
if (!function_exists('ultimoDiaDePeriodo')) {
function ultimoDiaDePeriodo($fecha) {
    $d = fechaComoDateTime($fecha);
    return $d === null ? null : $d->format('Y-m-t');
}
}



/**
 * Obtiene todos los centros de costo activos
 * 
 * @param PDO $pdo
 * @return array
 */
if (!function_exists('getCentrosCosto')) {
function getCentrosCosto($pdo) {
    $stmt = $pdo->query("SELECT id, codigo, nombre FROM centros_costo WHERE activo = 1 ORDER BY codigo");
    return $stmt->fetchAll();
}
}


/**
 * Calcula el impuesto sobre ingresos personales según los rangos configurados
 * 
 * @param PDO $pdo
 * @param float $ingreso_imponible
 * @return float
 */
if (!function_exists('calcularImpuestoPersonal')) {
function calcularImpuestoPersonal($pdo, $ingreso_imponible) {
    $rangos = getRangosImpuesto($pdo);
    
    foreach ($rangos as $rango) {
        $desde = (float)$rango['desde'];
        $hasta = $rango['hasta'] !== null ? (float)$rango['hasta'] : null;
        $tasa = (float)$rango['tasa'];
        $monto_fijo = (float)$rango['monto_fijo'];
        
        if ($ingreso_imponible >= $desde && ($hasta === null || $ingreso_imponible <= $hasta)) {
            $excedente = $ingreso_imponible - $desde;
            return $monto_fijo + ($excedente * $tasa);
        }
    }
    
    return 0;
}
}

/**
 * Calcula la cosntribución especial a la seguridad social
 * 
 * @param PDO $pdo
 * @param float $salario_devengado
 * @return float
 */
if (!function_exists('calcularContribucionEspecial')) {
function calcularContribucionEspecial($pdo, $salario_devengado) {
    $tasa = cargarTasaCessEspecial($pdo);

    return $salario_devengado * ($tasa / 100);
}
}

/**
 * Obtiene la configuración general
 * 
 * @param PDO $pdo
 * @param string $parametro
 * @return mixed
 */
if (!function_exists('getConfiguracion')) {
function getConfiguracion($pdo, $parametro) {
    $stmt = $pdo->prepare("SELECT valor, tipo_dato FROM configuracion_general WHERE parametro = ?");
    $stmt->execute([$parametro]);
    $row = $stmt->fetch();
    
    if (!$row) {
        return null;
    }
    
    $valor = $row['valor'];
    $tipo = $row['tipo_dato'];
    
    switch ($tipo) {
        case 'entero':
            return (int)$valor;
        case 'decimal':
            return (float)$valor;
        case 'booleano':
            return (valor == '1' || valor == 'true' || valor == 'si');
        default:
            return $valor;
    }
}
}

/**
 * Calcula las vacaciones generadas según la Ley 116
 * Fórmula: días de vacaciones = días trabajados ?? 11
 * Valor a pagar = Salarios devengados ?? 11
 * 
 * @param float $dias_trabajados
 * @param float $salarios_devengados
 * @return array ['dias' => float, 'valor' => float]
 */
if (!function_exists('calcularVacacionesLey116')) {
function calcularVacacionesLey116($dias_trabajados, $salarios_devengados) {
    $dias_vacaciones = $dias_trabajados / 11;
    $valor_vacaciones = $salarios_devengados / 11;
    
    return [
        'dias' => round($dias_vacaciones, 2),
        'valor' => round($valor_vacaciones, 2)
    ];
}
}

/**
 * Obtiene la configuración de vacaciones vigente
 * 
 * @param PDO $pdo
 * @return array
 */
if (!function_exists('getConfiguracionVacaciones')) {
function getConfiguracionVacaciones($pdo) {
    $stmt = $pdo->query("SELECT dias_por_mes, factor_calculo, meses_requeridos FROM configuracion_vacaciones WHERE activo = 1 ORDER BY fecha_vigencia DESC LIMIT 1");
    return $stmt->fetch();
}
}

/**
 * Convierte un número a su representación en números romanos
 * 
 * @param int $numero
 * @return string
 */
if (!function_exists('numeroRomano')) {
function numeroRomano($numero) {
    if ($numero == 'S/E' || $numero == '?' || $numero === null || $numero === '') {
        return '?';
    }
    $numero = intval($numero);
    if ($numero <= 0) return '0';
    if ($numero >= 4000) return (string)$numero;
    $romanos = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
        100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
        10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
    ];
    $resultado = '';
    foreach ($romanos as $valorDecimal => $romano) {
        while ($numero >= $valorDecimal) {
            $resultado .= $romano;
            $numero -= $valorDecimal;
        }
    }
    return $resultado;
}
}

/**
 * Valida el formato del Carnet de Identidad cubano
 * 
 * @param string $ci
 * @return bool
 */
if (!function_exists('validarCI')) {
function validarCI($ci) {
    $ci_limpio = preg_replace('/\D/', '', $ci);
    return strlen($ci_limpio) === 11;
}
}

/**
 * Valida el formato de cuenta bancaria (14 o 16 dígitos)
 * 
 * @param string $cuenta
 * @return bool
 */
if (!function_exists('validarCuentaBancaria')) {
function validarCuentaBancaria($cuenta) {
    $cuenta_limpia = preg_replace('/\D/', '', $cuenta);
    $longitud = strlen($cuenta_limpia);
    return $longitud === 0 || $longitud === 14 || $longitud === 16;
}
}

/**
 * Calcula el salario por hora según el salario mensual y horas mensuales configuradas
 * 
 * @param PDO $pdo
 * @param float $salario_mensual
 * @return float
 */
if (!function_exists('calcularSalarioHora')) {
function calcularSalarioHora($pdo, $salario_mensual) {
    $horas_mensuales = getConfiguracion($pdo, 'horas_mensuales');
    return $salario_mensual / $horas_mensuales;
}
}

/**
 * Calcula el valor por día según el salario mensual y días mensuales configurados
 * 
 * @param PDO $pdo
 * @param float $salario_mensual
 * @return float
 */
if (!function_exists('calcularValorDia')) {
function calcularValorDia($pdo, $salario_mensual) {
    $dias_mensuales = getConfiguracion($pdo, 'dias_mensuales');
    return $salario_mensual / $dias_mensuales;
}
}

/**
 * Obtiene los motivos de baja activos
 * 
 * @param PDO $pdo
 * @return array
 */
if (!function_exists('getMotivosBaja')) {
function getMotivosBaja($pdo) {
    $stmt = $pdo->query("SELECT * FROM motivos_baja WHERE activo = 1 ORDER BY codigo");
    return $stmt->fetchAll();
}
}

/**
 * Obtiene los centros de costo activos (alias de getCentrosCosto para compatibilidad)
 * 
 * @param PDO $pdo
 * @return array
 */
if (!function_exists('getCentrosCostoActivos')) {
function getCentrosCostoActivos($pdo) {
    return getCentrosCosto($pdo);
}
}

/**
 * Calcula el total de días trabajados en un período
 * 
 * @param string $fecha_inicio
 * @param string $fecha_fin
 * @param array $dias_no_laborables (opcional, domingos por defecto)
 * @return int
 */
if (!function_exists('calcularDiasTrabajados')) {
function calcularDiasTrabajados($fecha_inicio, $fecha_fin, $dias_no_laborables = [0]) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day'); // Incluir el día de fin
    
    $dias_trabajados = 0;
    $interval = new DateInterval('P1D');
    $periodo = new DatePeriod($inicio, $interval, $fin);
    
    foreach ($periodo as $fecha) {
        $dia_semana = $fecha->format('w'); // 0 = domingo, 6 = sábado
        if (!in_array($dia_semana, $dias_no_laborables)) {
            $dias_trabajados++;
        }
    }
    
    return $dias_trabajados;
}
}

/**
 * Obtiene un trabajador por su ID con todos sus datos relacionados
 * 
 * @param PDO $pdo
 * @param int $id
 * @return array|null
 */
if (!function_exists('getTrabajadorCompleto')) {
function getTrabajadorCompleto($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT t.*, a.nombre_area, c.nombre as categoria_nombre, c.factor_incidencia,
               e.salario_mensual, e.escala_numero, e.salario_hora_ordinaria,
               cc.nombre as centro_costo_nombre
        FROM trabajadores t
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
        LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
        JOIN escalas_salariales e ON t.escala_salarial_id = e.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
}

/**
 * Obtiene todas las nómias de un trabajador
 * 
 * @param PDO $pdo
 * @param int $trabajador_id
 * @return array
 */
if (!function_exists('getNominasPorTrabajador')) {
function getNominasPorTrabajador($pdo, $trabajador_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM nominas 
        WHERE trabajador_id = ? 
        ORDER BY periodo_desde DESC
    ");
    $stmt->execute([$trabajador_id]);
    return $stmt->fetchAll();
}
}

/**
 * Registra un cierre de nómia
 * 
 * @param PDO $pdo
 * @param array $datos
 * @return int|false
 */
if (!function_exists('registrarCierreNomina')) {
function registrarCierreNomina($pdo, $datos) {
    $stmt = $pdo->prepare("
        INSERT INTO cierres_nomina (
            periodo_desde, periodo_hasta, tipo_nomina, usuario_cierre,
            total_trabajadores, total_devengado, total_deducciones, total_neto,
            total_contribucion, total_vacaciones_pagadas, observaciones, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $datos['periodo_desde'],
        $datos['periodo_hasta'],
        $datos['tipo_nomina'] ?? 'automatica',
        $datos['usuario_cierre'] ?? $_SESSION['usuario'] ?? null,
        $datos['total_trabajadores'],
        $datos['total_devengado'],
        $datos['total_deducciones'],
        $datos['total_neto'],
        $datos['total_contribucion'],
        $datos['total_vacaciones_pagadas'],
        $datos['observaciones'] ?? null,
        $datos['estado'] ?? 'cerrado'
    ]);
    
    return $pdo->lastInsertId();
}
}

/**
 * Obtiene el último cierre de nómia para un período
 * 
 * @param PDO $pdo
 * @param string $periodo_desde
 * @param string $periodo_hasta
 * @return array|null
 */
if (!function_exists('getUltimoCierreNomina')) {
function getUltimoCierreNomina($pdo, $periodo_desde, $periodo_hasta) {
    $stmt = $pdo->prepare("
        SELECT * FROM cierres_nomina 
        WHERE periodo_desde = ? AND periodo_hasta = ?
        ORDER BY fecha_cierre DESC LIMIT 1
    ");
    $stmt->execute([$periodo_desde, $periodo_hasta]);
    return $stmt->fetch();
}
}

/**
 * Genera un código único para trabajador (últimos 6 dígitos del CI)
 * 
 * @param string $ci
 * @return string
 */
if (!function_exists('generarCodigoTrabajador')) {
function generarCodigoTrabajador($ci) {
    $ci_limpio = preg_replace('/\D/', '', $ci);
    return substr($ci_limpio, -6);
}
}

/**
 * Genera un código de confirmación tipo captcha con letras (mayúsculas y
 * minúsculas) y números.
 *
 * @param int $longitud número de caracteres (por defecto 8)
 * @return string
 */
if (!function_exists('generarCaptchaAlfanumerico')) {
function generarCaptchaAlfanumerico($longitud = 8) {
    $longitud = max(4, (int)$longitud);
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($caracteres) - 1;
    $codigo = '';
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $caracteres[random_int(0, $max)];
    }
    return $codigo;
}
}

/**
 * Verifica si las tablas referenciales/clasificadoras están vacías y, si es
 * así, emite una barra fija superior (estilo "Solicitud de cambio de
 * contraseña pendiente") con una "X" para cerrar. La barra aparece en cada
 * carga de página mientras existan clasificadores vacíos (no se recuerda la
 * decisión: al recargar o abrir otra página vuelve a mostrarse).
 *
 * Uso: se invoca automáticamente desde includes/footer.php; también puede
 * llamarse desde cualquier módulo (donde $pdo ya está disponible):
 *     avisarClasificadoresVacios($pdo);                                       // verifica todas
 *     avisarClasificadoresVacios($pdo, ['areas', 'centros_costo']);          // solo algunas
 *
 * @param PDO   $pdo    Conexión PDO activa.
 * @param array $tablas Lista de tablas a verificar. Si se omite, se verifican todas.
 * @return void
 */
if (!function_exists('getClasificadoresVacios')) {
function getClasificadoresVacios($pdo, $tablas = []) {
    // Determina la URL base del directorio "modules" según desde donde se invoque:
    // dashboard.php está en /nominas/ y los módulos en /nominas/modules/.
    $script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $base_modules = (basename($script_dir) === 'modules') ? $script_dir : $script_dir . '/modules';

    $config = [
        'trabajadores'             => ['nombre' => 'Trabajadores',             'icono' => 'fa-users',                'url' => $base_modules . '/empleados.php?nuevo=1'],
        'areas'                    => ['nombre' => 'áreas',                    'icono' => 'fa-building',             'url' => $base_modules . '/clasificadores.php?tabla=areas&nuevo=1'],
        'centros_costo'            => ['nombre' => 'Centros de Costo',         'icono' => 'fa-chart-pie',            'url' => $base_modules . '/clasificadores.php?tabla=centros_costo&nuevo=1'],
        'categorias_ocupacionales' => ['nombre' => 'Categorías Ocupacionales', 'icono' => 'fa-user-tag',             'url' => $base_modules . '/clasificadores.php?tabla=categorias_ocupacionales&nuevo=1'],
        'escalas_salariales'       => ['nombre' => 'Escalas Salariales',       'icono' => 'fa-dollar-sign',          'url' => $base_modules . '/clasificadores.php?tabla=escalas_salariales&nuevo=1'],
        'cargos_plantilla'         => ['nombre' => 'Cargos de Plantilla',      'icono' => 'fa-briefcase',            'url' => $base_modules . '/clasificadores.php?tabla=cargos_plantilla&nuevo=1'],
        'motivos_baja'             => ['nombre' => 'Motivos de Baja',          'icono' => 'fa-exclamation-triangle', 'url' => $base_modules . '/clasificadores.php?tabla=motivos_baja&nuevo=1'],
    ];

    if (empty($tablas)) {
        $tablas = array_keys($config);
    }

    $pendientes = [];
    foreach ($tablas as $tabla) {
        if (!isset($config[$tabla])) {
            continue;
        }
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tabla}`");
            if ((int) $stmt->fetchColumn() === 0) {
                $pendientes[] = array_merge($config[$tabla], ['tabla' => $tabla]);
            }
        } catch (PDOException $e) {
            // La tabla no existe: se omite para no romper la página
        }
    }

    return $pendientes;
}
}

/**
 * Avisa (barra fija superior) cuando existen clasificadores sin datos. La
 * barra se emite solo si hay pendientes y se mueve al <body> vía JS para
 * evitar que ancestros con transform/backdrop-filter/z-index rompan el
 * position:fixed.
 *
 * Uso: se invoca automáticamente desde includes/footer.php; también puede
 * llamarse desde cualquier módulo (donde $pdo ya está disponible):
 *     avisarClasificadoresVacios($pdo);                                       // verifica todas
 *     avisarClasificadoresVacios($pdo, ['areas', 'centros_costo']);          // solo algunas
 *
 * @param PDO   $pdo    Conexión PDO activa.
 * @param array $tablas Lista de tablas a verificar. Si se omite, se verifican todas.
 * @return void
 */
if (!function_exists('avisarClasificadoresVacios')) {
function avisarClasificadoresVacios($pdo, $tablas = []) {
    $pendientes = getClasificadoresVacios($pdo, $tablas);

    if (empty($pendientes)) {
        return;
    }

    // Enlaces a cada clasificador vacío
    $lista = [];
    foreach ($pendientes as $p) {
        $lista[] = '<a class="bvc-enlace" href="' . htmlspecialchars($p['url']) . '"><i class="fas ' . $p['icono'] . '"></i> ' . htmlspecialchars($p['nombre']) . '</a>';
    }
    $lista_html = implode(', ', $lista);
    $total = count($pendientes);

    echo <<<HTML
<div id="barraClasificadoresVacios" class="bvc-barra">
    <div class="bvc-wrap">
        <div class="bvc-icono"><i class="fas fa-database"></i></div>
        <div class="bvc-texto">
            <span class="bvc-titulo"><i class="fas fa-triangle-exclamation" style="color:#f59e0b"></i> Datos pendientes de registrar</span>
            <span class="bvc-sub">Hay {$total} clasificador(es) vac&iacute;o(s): {$lista_html}</span>
        </div>
        <div class="bvc-botones">
            <a class="bvc-btn" href="{$base_modules}/clasificadores.php"><i class="fas fa-arrow-right"></i> Registrar ahora</a>
        </div>
    </div>
    <button type="button" class="bvc-cerrar" title="Cerrar notificaci&oacute;n" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>
</div>
<style>
.bvc-barra { position: fixed; top:0; left:0; right:0; z-index: 9995; display: flex; align-items: center; justify-content: space-between; gap:0.75rem; padding:0.625rem 1.125rem; background: linear-gradient(135deg, rgba(30,41,59,.97), rgba(15,23,42,.97)); border-bottom: 0.125rem solid #f59e0b; box-shadow: 0 0.375rem 1.25rem rgba(0,0,0,.5); color: #fff; animation: bvcSlide .45s ease; }
@keyframes bvcSlide { from { transform: translateY(-100%); } to { transform: translateY(0); } }
.bvc-barra.bvc-oculta { transition: transform .45s ease; transform: translateY(-130%); }
.bvc-wrap { display: flex; align-items: center; gap:0.75rem; flex: 1; min-width:0; }
.bvc-icono { width:2.375rem; height:2.375rem; flex: 0 0 2.375rem; border-radius: 0.625rem; background: rgba(245,158,11,.15); border: 0.0625rem solid rgba(245,158,11,.4); display: flex; align-items: center; justify-content: center; color: #fbbf24; font-size:1rem; }
.bvc-texto { display: flex; flex-direction: column; gap:0.125rem; min-width:0; }
.bvc-titulo { font-weight: 700; font-size:.92rem; color: #fbbf24; }
.bvc-sub { font-size:.82rem; color: rgba(255,255,255,.75); }
.bvc-enlace { color: #fbbf24; text-decoration: none; border-bottom: 0.0625rem dashed rgba(245,158,11,.5); }
.bvc-enlace:hover { color: #fff; }
.bvc-botones { display: flex; gap:0.5rem; flex: 0 0 auto; }
.bvc-btn { border: none; border-radius: 0.5rem; padding:0.5rem 0.875rem; font-size:.82rem; font-weight: 600; cursor: pointer; color: #0f172a; background: #f59e0b; text-decoration: none; transition: filter .2s ease; }
.bvc-btn:hover { filter: brightness(1.12); }
.bvc-cerrar { background: transparent; border: none; color: rgba(255,255,255,.5); font-size:1.1rem; cursor: pointer; padding:0.25rem; }
.bvc-cerrar:hover { color: #fff; }
@media (max-width:768px){ .bvc-wrap{flex-wrap:wrap;} .bvc-botones{width:100%;justify-content:flex-end;} }
</style>
<script>
(function(){
    var barra = document.getElementById('barraClasificadoresVacios');
    if (!barra) return;

    // Mover la barra al <body>: si quedó dentro de un ancestro con
    // transform/backdrop-filter/z-index propio (main-container, glass-card),
    // position:fixed se rompe o queda bajo el sidebar. En el body se posiciona
    // siempre respecto al viewport, arriba y a todo lo largo.
    if (barra.parentElement && barra.parentElement !== document.body) {
        document.body.appendChild(barra);
    }

    function ocultar(){
        if (!barra || barra.style.display === 'none') return;
        barra.style.display = 'none';
    }
    function mostrar(){
        if (!barra || barra.style.display !== 'none') return;
        barra.style.display = '';
        programarAutohide();
    }

    var timer = null;
    function programarAutohide(){
        clearTimeout(timer);
        timer = setTimeout(ocultar, 10000);
    }

    // Autohide: se oculta sola tras 10 s; reaparece al volver el mouse
    // a la parte superior o al hacer scroll hacia arriba
    window.addEventListener('scroll', function(){
        if (window.scrollY < 80) {
            mostrar();
        } else {
            ocultar();
        }
    });

    var btn = barra.querySelector('.bvc-cerrar');
    if (btn) btn.addEventListener('click', ocultar);

    var otra = document.getElementById('barraResetPendiente');
    if (otra && !otra.classList.contains('brp-oculta')) {
        barra.style.top = (otra.offsetHeight + 2) + 'px';
    }

    programarAutohide();
})();
</script>
HTML;
}
}

if (!function_exists('verificarCuadreCierres')) {
/**
 * Verifica el cuadre de los cierres registrados en cierres_nomina:
 * compara los totales almacenados en cada cierre contra la suma real de las
 * filas contabilizadas de esa nómia (periodo + tipo + numero) y valida la
 * aritmética interna del cierre (neto = devengado - deducciones).
 *
 * @param PDO $pdo
 * @param array $filtro Claves opcionales: periodo_desde, periodo_hasta, tipo
 * @return array [
 *   'cierres'          => int total de cierres evaluados,
 *   'cierres_con_error'=> int cantidad de cierres con al menos un descuadre,
 *   'errores_total'    => int cantidad total de descuadres,
 *   'errores_cierre'   => array detalle de cada descuadre
 * ]
 */
function verificarCuadreCierres($pdo, $filtro = []) {
    $sql = "SELECT c.* FROM cierres_nomina c WHERE 1 = 1";
    $params = [];

    if (!empty($filtro['periodo_desde']) && !empty($filtro['periodo_hasta'])) {
        $sql .= " AND c.periodo_desde = ? AND c.periodo_hasta = ?";
        $params[] = $filtro['periodo_desde'];
        $params[] = $filtro['periodo_hasta'];
    }
    if (!empty($filtro['tipo'])) {
        $sql .= " AND c.tipo_nomina = ?";
        $params[] = $filtro['tipo'];
    }
    $sql .= " ORDER BY c.periodo_desde DESC, c.tipo_nomina, c.numero_nomina";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Suma real por nómia contabilizada (para comparar con los totales del cierre)
    $stmt_sum = $pdo->prepare("SELECT periodo_desde, periodo_hasta, tipo_nomina, numero_nomina,
                                      COUNT(*) as total_trabajadores,
                                      SUM(total_salario_devengado) as total_devengado,
                                      SUM(COALESCE(descuentos, 0) + COALESCE(contribucion_especial, 0) + COALESCE(ingresos_personales, 0) + COALESCE(otras_deducciones, 0)) as total_deducciones,
                                      SUM(importe_neto) as total_neto,
                                      SUM(contribucion_especial) as total_contribucion,
                                      SUM(COALESCE(importe_vacaciones, 0)) as total_vacaciones_pagadas
                                 FROM nominas
                                WHERE estado = 'contabilizado' AND numero_nomina IS NOT NULL
                                GROUP BY periodo_desde, periodo_hasta, tipo_nomina, numero_nomina");
    $stmt_sum->execute();
    $sumas = [];
    foreach ($stmt_sum->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $sumas[$s['periodo_desde'] . '|' . $s['periodo_hasta'] . '|' . $s['tipo_nomina'] . '|' . $s['numero_nomina']] = $s;
    }

    $errores = [];
    $numeros_con_error = [];

    foreach ($cierres as $c) {
        $clave = $c['periodo_desde'] . '|' . $c['periodo_hasta'] . '|' . $c['tipo_nomina'] . '|' . $c['numero_nomina'];
        $s = $sumas[$clave] ?? null;

        $total_trab = $s ? intval($s['total_trabajadores']) : 0;
        $total_dev  = $s ? floatval($s['total_devengado']) : 0;
        $total_ded  = $s ? floatval($s['total_deducciones']) : 0;
        $total_neto = $s ? floatval($s['total_neto']) : 0;
        $total_cont = $s ? floatval($s['total_contribucion']) : 0;
        $total_vac  = $s ? floatval($s['total_vacaciones_pagadas']) : 0;

        $checks = [
            ['total_trabajadores', 'Trabajadores', $total_trab, intval($c['total_trabajadores'] ?? 0), 0],
            ['total_devengado', 'Devengado', $total_dev, floatval($c['total_devengado'] ?? 0), 0.02],
            ['total_deducciones', 'Deducciones', $total_ded, floatval($c['total_deducciones'] ?? 0), 0.02],
            ['total_neto', 'Neto', $total_neto, floatval($c['total_neto'] ?? 0), 0.02],
            ['total_contribucion', 'cosntribución CESS', $total_cont, floatval($c['total_contribucion'] ?? 0), 0.02],
            ['total_vacaciones_pagadas', 'Vacaciones pagadas', $total_vac, floatval($c['total_vacaciones_pagadas'] ?? 0), 0.02],
            ['neto_aritmetica', 'Aritmética (neto = dev - ded)', floatval($c['total_devengado'] ?? 0) - floatval($c['total_deducciones'] ?? 0), floatval($c['total_neto'] ?? 0), 0.02],
        ];

        $local = [];
        foreach ($checks as $ck) {
            $check = $ck[0]; $det = $ck[1]; $esp = $ck[2]; $enc = $ck[3]; $tol = $ck[4];
            if ($check === 'total_trabajadores') {
                $ok = (intval($enc) === intval($esp));
            } else {
                $ok = (abs(round($enc, 2) - round($esp, 2)) <= $tol);
            }
            if (!$ok) {
                $local[] = [
                    'tipo' => $c['tipo_nomina'],
                    'numero' => $c['numero_nomina'],
                    'periodo' => $c['periodo_desde'],
                    'check' => 'cierre_' . $check,
                    'detalle' => $det,
                    'esperado' => number_format(round($esp, 2), 2, '.', ''),
                    'encontrado' => number_format(round($enc, 2), 2, '.', ''),
                    'diferencia' => number_format(round($enc, 2) - round($esp, 2), 2, '.', ''),
                ];
            }
        }

        if (!empty($local)) {
            $numeros_con_error[] = $clave;
            $errores = array_merge($errores, $local);
        }
    }

    return [
        'cierres' => count($cierres),
        'cierres_con_error' => count($numeros_con_error),
        'errores_total' => count($errores),
        'errores_cierre' => array_slice($errores, 0, 200),
    ];
}
}


// ============================================
// CIERRES DE PERIODO DE NOMINA (mes y anio)
// ============================================
// Un cierre de periodo congela TODAS las nominas del mes (los 5 tipos),
// a diferencia de `cierres_nomina`, que es solo el snapshot de un lote.
// Si un ano esta cerrado, todos sus meses cuentan como cerrados.

if (!function_exists('etiquetaMesNominas')) {
    /**
     * Nombre del mes en espanol. `nombreMesEspanol()` vive en modules/nominas.php,
     * asi que aqui se resuelve por si el modulo ya esta cargado o no.
     */
    function etiquetaMesNominas($mes) {
        $mes = str_pad((string)(int)$mes, 2, '0', STR_PAD_LEFT);
        if (function_exists('nombreMesEspanol')) {
            $nombre = nombreMesEspanol($mes);
            if ($nombre !== '') { return $nombre; }
        }
        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        return $meses[$mes] ?? $mes;
    }
}

if (!function_exists('periodoNominasEnCurso')) {
    /**
     * Mes de nomina en curso, equivalente a `fecha_inicio_operaciones` de
     * facturacion. Es el mes abierto mas antiguo: se avanza al cerrar un mes y
     * se retrocede al reabrirlo. Se guarda como primer dia del mes (YYYY-MM-01).
     */
    function periodoNominasEnCurso($pdo) {
        $fallback = [
            'fecha' => date('Y-m-01'),
            'anio'  => (int)date('Y'),
            'mes'   => (int)date('m'),
        ];
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = ?");
            $st->execute(['periodo_nominas_en_curso']);
            $v = $st->fetchColumn();
            if ($v !== false && preg_match('/^(\d{4})-(\d{2})-01$/', (string)$v, $m)) {
                $anio = (int)$m[1];
                $mes  = (int)$m[2];
                if ($mes >= 1 && $mes <= 12) {
                    return ['fecha' => sprintf('%04d-%02d-01', $anio, $mes), 'anio' => $anio, 'mes' => $mes];
                }
            }
        } catch (PDOException $e) {}
        return $fallback;
    }
}

if (!function_exists('actualizarPeriodoNominasEnCurso')) {
    /**
     * Fija el mes de nomina en curso. Rechaza cualquier fecha que no sea el
     * primer dia de un mes valido, para no dejar el parametro inconsistente.
     * Tolera estar dentro de una transaccion abierta para poder invocarse desde
     * el propio cierre de mes.
     */
    function actualizarPeriodoNominasEnCurso($pdo, $fecha, $usuario = null) {
        if (!preg_match('/^(\d{4})-(\d{2})-01$/', (string)$fecha, $m)) {
            return ['success' => false, 'mensaje' => 'La fecha debe ser el primer dia del mes (YYYY-MM-01)'];
        }
        $mes = (int)$m[2];
        if ($mes < 1 || $mes > 12) {
            return ['success' => false, 'mensaje' => 'Mes fuera de rango'];
        }

        $propia = !$pdo->inTransaction();
        try {
            if ($propia) { $pdo->beginTransaction(); }
            $st = $pdo->prepare("UPDATE configuracion_general SET valor = ?, tipo_dato = 'fecha', usuario_modifico = ? WHERE parametro = ?");
            $st->execute([$fecha, $usuario, 'periodo_nominas_en_curso']);
            if ($st->rowCount() === 0) {
                $ins = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato, descripcion, usuario_modifico)
                                      VALUES ('periodo_nominas_en_curso', ?, 'fecha', ?, ?)
                                      ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                $ins->execute([$fecha, 'Mes de nomina en curso (YYYY-MM-01)', $usuario]);
            }
            if ($propia) { $pdo->commit(); }
            return [
                'success' => true,
                'mensaje' => 'Periodo en curso actualizado',
                'fecha'   => $fecha,
                'mes'     => $mes,
                'anio'    => (int)$m[1],
            ];
        } catch (PDOException $e) {
            if ($propia && $pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false, 'mensaje' => 'Error al actualizar el periodo en curso: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('periodoNominasCerrado')) {
    /* Trazabilidad de los fallos de la consulta de cierres: si el estado real no
     * se pudo leer, el guard bloquea por seguridad pero deja rastro en el log. */
    $GLOBALS['nominas_cierres_indisponibles'] = false;

    /**
     * - El periodo (YYYY-MM-DD primer dia) tiene un cierre vigente?
     * Cuenta como cerrado tanto el cierre mensual como el anual de su ano.
     *
     * Ante un error de base de datos se responde true, no false. Este guard
     * protege datos que ya se dieron por cerrados, asi que lo seguro es impedir
     * la escritura: devolver false abriria el periodo solo porque falló la
     * consulta, que es justo cuando no se sabe si está cerrado.
     */
    function periodoNominasCerrado($pdo, $periodo_desde) {
        $mes  = (int)substr((string)$periodo_desde, 5, 2);
        $anio = (int)substr((string)$periodo_desde, 0, 4);

        if ($mes < 1 || $mes > 12 || $anio < 2000) {
            return false;
        }

        try {
            $st = $pdo->prepare("SELECT tipo FROM cierres_periodo_nomina
                                  WHERE estado = 'cerrado'
                                    AND ((tipo = 1 AND periodo_mes = ? AND periodo_anio = ?)
                                      OR (tipo = 2 AND periodo_anio = ?))
                                  LIMIT 1");
            $st->execute([$mes, $anio, $anio]);
            $GLOBALS['nominas_cierres_indisponibles'] = false;
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            $GLOBALS['nominas_cierres_indisponibles'] = true;
            error_log('TransNuBeT: no se pudo verificar el estado de cierre del periodo '
                . $anio . '-' . sprintf('%02d', $mes) . ': ' . $e->getMessage());
            return true;
        }
    }
}

if (!function_exists('normalizar_periodo_nominas')) {
    /**
     * Valida y normaliza un periodo. Acepta 'AAAA-MM' (como lo manda la pagina)
     * y tambien 'AAAA-MM-DD' (como lo mandan las peticiones AJAX en 'pd' y
     * 'periodo_desde'); en ambos casos devuelve 'AAAA-MM'. Si el valor no es
     * valido devuelve el mes en curso, de modo que un parametro manipulado nunca
     * produce un periodo vacio ni uno con mes 0. Ver mas abajo por que aceptar
     * los dos formatos es una cuestion de seguridad y no de comodidad.
     */
    function normalizar_periodo_nominas($pdo, $periodo, $usar_en_curso_si_invalido = true) {
        $periodo = trim((string)$periodo);

        /* Acepta tanto 'AAAA-MM' como una fecha completa 'AAAA-MM-DD'. El modulo
         * manda el periodo en pantalla como 'AAAA-MM', pero las peticiones AJAX
         * mandan 'pd' y 'periodo_desde' como primer dia del mes. Antes solo se
         * aceptaba la primera forma, y al descartar la segunda el guard de
         * periodos cerrados resolvia el periodo en curso --que casi siempre esta
         * abierto-- y dejaba pasar la mutacion sobre un mes ya cerrado. */
        if (preg_match('/^(\d{4})-(\d{2})(?:-\d{2})?$/', $periodo, $m)) {
            $anio = (int)$m[1];
            $mes  = (int)$m[2];
            if ($mes >= 1 && $mes <= 12 && $anio >= 2000 && $anio <= 2100) {
                return sprintf('%04d-%02d', $anio, $mes);
            }
        }

        if (!$usar_en_curso_si_invalido) {
            return null;
        }
        $en_curso = periodoNominasEnCurso($pdo);
        return sprintf('%04d-%02d', $en_curso['anio'], $en_curso['mes']);
    }
}

if (!function_exists('periodoNominasFuturo')) {
    /**
     * - El periodo pedido esta POR DELANTE del periodo en curso?
     *
     * El periodo en curso es el mes abierto mas antiguo, asi que cualquier mes
     * posterior todavia no ha llegado: no se puede generar ni modificar su
     * nomina. Sin esta comprobacion se podia trabajar sobre meses futuros
     * escribiendo filas con periodos_desde que todavia no existen.
     */
    function periodoNominasFuturo($pdo, $periodo) {
        $periodo = normalizar_periodo_nominas($pdo, $periodo);
        $anio = (int)substr($periodo, 0, 4);
        $mes  = (int)substr($periodo, 5, 2);

        if ($mes < 1 || $mes > 12 || $anio < 2000) {
            return false;
        }

        $en_curso = periodoNominasEnCurso($pdo);
        return $anio > $en_curso['anio']
            || ($anio === $en_curso['anio'] && $mes > $en_curso['mes']);
    }
}

if (!function_exists('mensaje_periodo_futuro_nominas')) {
    /**
     * Texto unico del bloqueo por periodo futuro. Lo comparten el mensaje del
     * guard, la alerta de la pagina y el SweetAlert del frontend, para que el
     * texto sea el mismo llegue por donde llegue.
     */
    function mensaje_periodo_futuro_nominas($pdo, $periodo, $en_curso = null) {
        $periodo = normalizar_periodo_nominas($pdo, $periodo);
        $anio = (int)substr($periodo, 0, 4);
        $mes  = (int)substr($periodo, 5, 2);

        if ($en_curso === null) {
            $en_curso = periodoNominasEnCurso($pdo);
        }

        return etiquetaMesNominas($mes) . ' ' . $anio . ' todavía no ha llegado. '
             . 'El período en curso es ' . etiquetaMesNominas($en_curso['mes']) . ' ' . $en_curso['anio']
             . ', así que no se puede generar ni modificar la nómia de un período futuro.';
    }
}

if (!function_exists('cierreAnualVigenteNominas')) {
    /**
     * Devuelve el cierre ANUAL del anio si sigue en estado 'cerrado', o null.
     *
     * Un cierre anual vigente es DEFINITIVO: congela todos los meses del anio,
     * asi que ni el ano ni ninguno de sus meses se pueden reabrir despues.
     */
    function cierreAnualVigenteNominas($pdo, $anio) {
        $anual = obtenerCierrePeriodoNominas($pdo, (int)$anio, 0);
        return ($anual && $anual['estado'] === 'cerrado') ? $anual : null;
    }
}

if (!function_exists('periodo_desde_nomina_id')) {
    /**
     * Periodo (YYYY-MM) de una nomina concreta, o null si el id no existe.
     * Permite bloquear la operacion mirando el periodo real de la fila que se
     * va a tocar, y no solo el periodo que aparece en pantalla.
     */
    function periodo_desde_nomina_id($pdo, $id) {
        $id = (int)$id;
        if ($id <= 0) { return null; }

        try {
            $st = $pdo->prepare("SELECT periodo_desde FROM nominas WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $desde = $st->fetchColumn();
            return $desde ? substr((string)$desde, 0, 7) : null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('estado_operacion_periodo_nominas')) {
    /**
     * Indica si se puede modificar una nomina de un periodo concreto.
     * Un mes cuenta como no operable si esta cerrado, o si su ano esta cerrado.
     */
    function estado_operacion_periodo_nominas($pdo, $periodo) {
        $periodo = normalizar_periodo_nominas($pdo, $periodo);
        $anio = (int)substr($periodo, 0, 4);
        $mes  = (int)substr($periodo, 5, 2);

        /* Periodo FUTURO: todavia no ha llegado. Se comprueba antes que el
         * cierre porque un mes posterior al periodo en curso no puede estar
         * cerrado, y el mensaje util es "aun no ha llegado", no "esta cerrado".
         * Al ir aqui, todos los handlers que ya llamaban a esta funcion
         * (exigir_periodo_operable, bloquear_periodo_nominas, el guard de la
         * fila) quedan cubiertos sin tocar uno por uno. */
        $en_curso = periodoNominasEnCurso($pdo);
        if ($mes >= 1 && $mes <= 12 && $anio >= 2000
            && ($anio > $en_curso['anio'] || ($anio === $en_curso['anio'] && $mes > $en_curso['mes']))) {
            return [
                'operable' => false,
                'periodo'  => $periodo,
                'mensaje'  => mensaje_periodo_futuro_nominas($pdo, $periodo, $en_curso),
                'cierre'   => null,
                'motivo'   => 'periodo_futuro',
            ];
        }

        if (!periodoNominasCerrado($pdo, "$anio-" . sprintf('%02d', $mes) . '-01')) {
            return ['operable' => true, 'periodo' => $periodo, 'mensaje' => '', 'cierre' => null];
        }

        /* Si el estado no se pudo leer, no se sabe si el periodo esta cerrado. Se
         * bloquea, pero sin pedir que se reabra nada: no hay un cierre que abrir. */
        if (!empty($GLOBALS['nominas_cierres_indisponibles'])) {
            return [
                'operable' => false,
                'periodo'  => $periodo,
                'mensaje'  => 'No se pudo verificar el estado de cierre de ' . etiquetaMesNominas($mes) . ' ' . $anio
                            . ' porque la base de datos no responde. No se realizaron cambios.',
                'cierre'   => null,
                'motivo'   => 'estado_cierres_indisponible',
            ];
        }

        /* El cierre ANUAL se consulta SIEMPRE, no solo cuando el mes no tiene cierre
         * propio: un mes puede estar cerrado por su cuenta y aun asi caer dentro
         * de un ano ya cerrado, que es el caso definitivo. Si aqui se saltara la
         * consulta, el aviso prometeria una reapertura que el servidor rechaza. */
        $cierre = obtenerCierrePeriodoNominas($pdo, $anio, $mes);
        $anual  = cierreAnualVigenteNominas($pdo, $anio);
        $anual_definitivo = !empty($anual);

        if ($anual_definitivo) {
            $mensaje = 'El año ' . $anio . ' está cerrado desde el ' . $anual['fecha_cierre']
                . ', por lo que el período ' . etiquetaMesNominas($mes) . ' ' . $anio
                . ($cierre ? ' (cerrado el ' . $cierre['fecha_cierre'] . ')' : '')
                . ' no admite cambios.';
        } else {
            $mensaje = 'El período ' . etiquetaMesNominas($mes) . ' ' . $anio . ' está cerrado'
                . ' desde el ' . $cierre['fecha_cierre'] . ' y no admite cambios.';
        }

        /* Cerrado no significa oculto: el periodo se puede consultar e imprimir
         * siempre. Lo unico que se impide es modificarlo, y si el ano esta cerrado
         * tampoco se puede reabrir. */
        if ($anual_definitivo) {
            $mensaje .= ' Puede consultarlo e imprimirlo, pero no modificarlo. El cierre anual es'
                . ' definitivo, por lo que ya no se puede reabrir.';
        } else {
            $mensaje .= ' Puede consultarlo e imprimirlo. Si necesita modificarlo,'
                . ' debe reabrir el cierre con un motivo justificado.';
        }

        return [
            'operable' => false,
            'periodo'  => $periodo,
            'mensaje'  => $mensaje,
            'cierre'   => $cierre ?: $anual,
            'motivo'   => $anual_definitivo ? 'anio_cerrado_definitivo' : 'periodo_cerrado',
        ];
    }
}

if (!function_exists('exigir_periodo_operable')) {
    /**
     * Corta la ejecucion si el periodo no es operable.
     *
     * Pensado para colocarse al inicio de cada handler que modifica nominas.
     * Por defecto responde JSON y termina, que es lo que esperan los handlers
     * AJAX. Con $salida = 'booleano' solo devuelve false, para los casos en que
     * el handler necesita seguir su propio flujo de salida.
     */
    function exigir_periodo_operable($pdo, $periodo, $salida = 'json') {
        $estado = estado_operacion_periodo_nominas($pdo, $periodo);

        if (!empty($estado['operable'])) {
            return true;
        }

        if ($salida === 'booleano') {
            return false;
        }

        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => $estado['mensaje'],
            'mensaje' => $estado['mensaje'],
            'motivo'  => $estado['motivo'] ?? 'periodo_cerrado',
            'periodo' => $estado['periodo'],
            'data'    => $estado['cierre'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('periodo_nominas_de_la_peticion')) {
    /**
     * Resuelve el periodo de una peticion de nominas. Prioriza los parametros
     * explicitos que envian algunos formularios y endpoints (pd, periodo) y cae
     * en el periodo de la pagina si no hay ninguno.
     */
    function periodo_nominas_de_la_peticion($pdo, $pordefecto = null) {
        foreach (['pd', 'periodo_desde', 'periodo'] as $clave) {
            if (isset($_POST[$clave]) && trim((string)$_POST[$clave]) !== '') {
                return normalizar_periodo_nominas($pdo, $_POST[$clave]);
            }
            if (isset($_GET[$clave]) && trim((string)$_GET[$clave]) !== '') {
                return normalizar_periodo_nominas($pdo, $_GET[$clave]);
            }
        }
        return $pordefecto !== null ? normalizar_periodo_nominas($pdo, $pordefecto) : null;
    }
}

if (!function_exists('bloquear_periodo_nominas')) {
    /**
     * Corta la peticion si el periodo no es operable, respondiendo en el formato
     * que espera el lugar desde donde se llama: JSON para los handlers AJAX y
     * redireccion al modulo para los que solo redibujan la pagina.
     */
    function bloquear_periodo_nominas($pdo, $periodo, $es_ajax, $modulo = 'nominas.php') {
        $estado = estado_operacion_periodo_nominas($pdo, $periodo);
        if (!empty($estado['operable'])) {
            return false;
        }

        if ($es_ajax) {
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => $estado['mensaje'],
                'mensaje' => $estado['mensaje'],
                'motivo'  => 'periodo_cerrado',
                'periodo' => $estado['periodo'],
                'data'    => $estado['cierre'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $modulo . '?periodo=' . urlencode($estado['periodo'])
            . '&periodo_bloqueado=1&motivo_bloqueo=' . urlencode($estado['motivo'] ?? 'periodo_cerrado'));
        exit;
    }
}

if (!function_exists('obtenerCierrePeriodoNominas')) {
    /**
     * Devuelve la fila de cierre de un periodo, o null si no esta cerrado.
     * $mes = 0 pide el cierre anual.
     */
    function obtenerCierrePeriodoNominas($pdo, $anio, $mes = 0) {
        try {
            $st = $pdo->prepare("SELECT * FROM cierres_periodo_nomina
                                  WHERE periodo_anio = ? AND periodo_mes = ?
                                  LIMIT 1");
            $st->execute([(int)$anio, (int)$mes]);
            $fila = $st->fetch(PDO::FETCH_ASSOC);
            return $fila ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('resumenTotalesPeriodoNominas')) {
    /**
     * Totales del periodo. Importes y lotes con las mismas formulas que usa
     * verificarCuadreCierres() para que las cifras del cierre coincidan con las
     * del modulo de nominas.
     *
     * Trabajadores: PERSONAS DISTINTAS (COUNT(DISTINCT trabajador_id)), no filas.
     * COUNT(*) contaba una vez por cada nomina, asi un trabajador con nomina
     * automatica + bono + ajuste contaba 3 veces y el mes se veia con mas gente
     * que la plantilla real (Julio mostraba 134 con ~57 trabajadores). Solo
     * cuenta filas contabilizadas y ya numeradas.
     */
function resumenTotalesPeriodoNominas($pdo, $periodo_desde, $periodo_hasta) {
        $sql = "SELECT COUNT(DISTINCT trabajador_id) AS total_trabajadores,
                       COUNT(DISTINCT numero_nomina) AS total_lotes,
                       COALESCE(SUM(total_salario_devengado), 0) AS total_devengado,
                       COALESCE(SUM(COALESCE(descuentos, 0) + COALESCE(contribucion_especial, 0)
                                   + COALESCE(ingresos_personales, 0) + COALESCE(otras_deducciones, 0)), 0) AS total_deducciones,
                       COALESCE(SUM(importe_neto), 0) AS total_neto,
                       COALESCE(SUM(contribucion_especial), 0) AS total_contribucion,
                       COALESCE(SUM(COALESCE(importe_vacaciones, 0)), 0) AS total_vacaciones
                  FROM nominas
                 WHERE periodo_desde = ?
                   AND periodo_hasta = ?
                   AND estado = 'contabilizado'
                   AND numero_nomina IS NOT NULL";
        $st = $pdo->prepare($sql);
        $st->execute([$periodo_desde, $periodo_hasta]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        return $fila ? [
            'total_trabajadores' => (int)$fila['total_trabajadores'],
            'total_lotes'        => (int)$fila['total_lotes'],
            'total_devengado'    => round((float)$fila['total_devengado'], 2),
            'total_deducciones'  => round((float)$fila['total_deducciones'], 2),
            'total_neto'         => round((float)$fila['total_neto'], 2),
            'total_contribucion' => round((float)$fila['total_contribucion'], 2),
            'total_vacaciones'   => round((float)$fila['total_vacaciones'], 2),
        ] : null;
    }
}

if (!function_exists('desgloseTiposNominaPeriodo')) {
    /**
     * Totales por tipo de nomina dentro del periodo. Es lo que se guarda en la
     * columna desglose_tipos del cierre.
     *
     * UNA fila por tipo (no por lote): trabajadores = personas distintas de ese
     * tipo y lotes = numero_nomina distintos, de modo que el panel no sume dos
     * veces a quien aparece en varios lotes del mismo tipo. Si alguna fila vieja
     * viniera por lote, quien la agrupa suma lote a lote y sigue siendo correcto.
     */
    function desgloseTiposNominaPeriodo($pdo, $periodo_desde, $periodo_hasta) {
        $st = $pdo->prepare("SELECT tipo_nomina,
                                    COUNT(DISTINCT numero_nomina) AS lotes,
                                    COUNT(DISTINCT trabajador_id) AS trabajadores,
                                    COALESCE(SUM(total_salario_devengado), 0) AS devengado,
                                    COALESCE(SUM(importe_neto), 0) AS neto
                               FROM nominas
                              WHERE periodo_desde = ? AND periodo_hasta = ?
                                AND estado = 'contabilizado' AND numero_nomina IS NOT NULL
                              GROUP BY tipo_nomina
                              ORDER BY tipo_nomina");
        $st->execute([$periodo_desde, $periodo_hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('mesesConNominaTipo')) {
    /**
     * Devuelve el conjunto de meses del anio que tienen al menos una nomina del tipo
     * indicado. Resuelve en UNA consulta los doce meses, porque la lista de meses del
     * panel del anio la necesita el JavaScript para decidir que meses son enlaces y
     * cuales no; consultar mes por mes serian doce viajes por cada cambio de anio.
     *
     * Se usa YEAR/MONTH y no el intervalo de fechas porque los datos arrastran el
     * mismo criterio que el resto del modulo (periodo_desde = primer dia del mes).
     *
     * @return array<int,bool>  mes => true
     */
    function mesesConNominaTipo($pdo, $anio, $tipo = 'automatica') {
        try {
            $st = $pdo->prepare("SELECT DISTINCT MONTH(periodo_desde) AS m
                FROM nominas
                WHERE YEAR(periodo_desde) = ? AND tipo_nomina = ?");
            $st->execute([(int)$anio, $tipo]);

            $mapa = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $mapa[(int)$fila['m']] = true;
            }
            return $mapa;
        } catch (Throwable $e) {
            // Si no se puede saber, se devuelve vacio: ningun mes sera enlace y la
            // lista sigue siendo utilizable. Es preferible a enlazar a un mes vacio.
            return [];
        }
    }
}

if (!function_exists('periodoSinNominas')) {
    /**
     * - El mes indicado no tiene nominas contabilizadas? Esos meses no bloquean
     * la progresion: se pueden dejar sin cerrar porque no aportan nada.
     */
    function periodoSinNominas($pdo, $anio, $mes) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ?");
        $st->execute([sprintf('%04d-%02d-01', (int)$anio, (int)$mes)]);
        return (int)$st->fetchColumn() === 0;
    }
}

if (!function_exists('anioConNominas')) {
    /**
     * - El anio indicado tiene alguna nomina?
     *
     * Cuenta cualquier fila, no solo las contabilizadas: un anio con borradores
     * tiene nominas y por lo tanto necesita un cierre, asi que no puede tratarse
     * como vacio. Con el filtro antiguo un anio lleno de borradores contaba como
     * vacio y January del anio siguiente se abria sin haberlo consolidado.
     */
    function anioConNominas($pdo, $anio) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM nominas
                              WHERE periodo_desde >= ? AND periodo_desde <= ?");
        $st->execute([sprintf('%04d-01-01', (int)$anio), sprintf('%04d-12-31', (int)$anio)]);
        return (int)$st->fetchColumn() > 0;
    }
}

if (!function_exists('periodoPrevioCerrado')) {
    /**
     * Regla de progresion: no se puede abrir un periodo si el inmediatamente
     * anterior sigue abierto. Los cierres son una cadena, no un checklist.
     *
     *   - mes > 1  -> hace falta el cierre mensual del mes anterior del mismo anio
     *   - mes == 1 -> hace falta el cierre ANUAL del anio anterior
     *
     * Un periodo previo sin nominas no bloquea, porque no genera nada que
     * cuadrar ni congelar.
     *
     * Devuelve ['permitido' => bool, 'mensaje' => string, 'motivo' => string].
     */
    function periodoPrevioCerrado($pdo, $anio, $mes) {
        $anio = (int)$anio;
        $mes  = (int)$mes;

        if ($mes < 1 || $mes > 12) {
            return ['permitido' => false, 'mensaje' => 'Mes fuera de rango', 'motivo' => 'rango'];
        }

        // Enero depende del cierre ANUAL de los años anteriores, y no solo del
        // inmediato: si 2026 sigue abierto, tampoco se abre 2028. Se reutiliza
        // la misma regla de cadena que la generación de nominas.
        if ($mes === 1) {
            $anioPendiente = ultimoAnioNominasSinCierreAnual($pdo, $anio - 1);
            if ($anioPendiente > 0) {
                return [
                    'permitido' => false,
                    'motivo'    => 'anio_sin_cerrar',
                    'mensaje'   => 'No se puede abrir Enero de ' . $anio . ' porque el año ' . $anioPendiente
                                  . ' todavía no está cerrado. Cierre el año ' . $anioPendiente
                                  . ' en el módulo Cierres primero.',
                ];
            }
            // Todos los años anteriores con nominas están cerrados.
            return ['permitido' => true, 'mensaje' => '', 'motivo' => 'anual_previo_cerrado'];
        }

        $mesPrevio = $mes - 1;
        $previo = obtenerCierrePeriodoNominas($pdo, $anio, $mesPrevio);
        if ($previo && $previo['estado'] === 'cerrado') {
            return ['permitido' => true, 'mensaje' => '', 'motivo' => 'mes_previo_cerrado'];
        }
        if (periodoSinNominas($pdo, $anio, $mesPrevio)) {
            return ['permitido' => true, 'mensaje' => '', 'motivo' => 'mes_previo_vacio'];
        }
        if ($previo && $previo['estado'] === 'revertido') {
            return [
                'permitido' => false,
                'motivo'    => 'mes_previo_revertido',
                'mensaje'   => 'No se puede abrir ' . etiquetaMesNominas($mes) . ' de ' . $anio . ' porque el cierre de '
                              . etiquetaMesNominas($mesPrevio) . ' ' . $anio . ' fue revertido. Vuelva a cerrarlo.',
            ];
        }        return [
            'permitido'    => false,
            'motivo'    => 'mes_previo_abierto',
            'mensaje'   => 'No se puede abrir ' . etiquetaMesNominas($mes) . ' de ' . $anio . ' porque '
                          . etiquetaMesNominas($mesPrevio) . ' ' . $anio . ' sigue abierto. Los periodos se cierran en orden: '
                          . 'cierre ' . etiquetaMesNominas($mesPrevio) . ' primero.',
        ];
    }
}

if (!function_exists('anioPrecedenteOperableNominas')) {
    /**
     * Cadena de anios aplicada a la GENERACION de nominas: no se puede abrir
     * un anio de trabajo si el anterior tiene nominas y su cierre anual no
     * esta registrado. Devuelve el mismo formato que periodoPrevioCerrado().
     *
     * Ojo: esto no sustituye al guard de periodos cerrados (que bloquea tocar
     * un mes ya cerrado); este mira el anio ANTERIOR al que se pide.
     */
    function anioPrecedenteOperableNominas($pdo, $anio) {
        $anio = (int)$anio;
        if ($anio <= 2000) {
            return ['permitido' => true, 'mensaje' => '', 'motivo' => 'sin_anio_previo', 'anio_pendiente' => 0];
        }

        // La cadena es completa, no solo de un año a otro: si 2026 sigue sin
        // cierre anual, NO se puede abrir ni 2027 ni 2028 ni 2029. Por eso se
        // recorre hacia atrás el último año con nómias sin cerrar en vez de
        // mirar solo $anio - 1. El bloqueo se reporta siempre sobre el año MÁS
        // RECIENTE pendiente, que es el primero que hay que cerrar para
        // desbloquear la cadena.
        $anioPendiente = ultimoAnioNominasSinCierreAnual($pdo, $anio - 1);
        if ($anioPendiente > 0) {
            return [
                'permitido'      => false,
                'motivo'         => 'anio_previo_sin_cerrar',
                'anio_pendiente' => $anioPendiente,
                'mensaje'        => mensaje_anio_previo_nominas($anio, $anioPendiente),
            ];
        }

        // Todos los años anteriores con nominas están cerrados: se puede abrir.
        return ['permitido' => true, 'mensaje' => '', 'motivo' => 'anio_previo_cerrado', 'anio_pendiente' => 0];
    }
}

if (!function_exists('ultimoAnioNominasSinCierreAnual')) {
    /**
     * último año, igual o anterior a $anioLimite, que tenga nómias y cuyo
     * cierre ANUAL no está registrado como 'cerrado'. Si un año anterior tiene
     * cierre anual pero uno MÁS antiguo sigue abierto, se sigue hacia atrás:
     * los cierres son una cadena y no se salta un hueco.
     *
     * Devuelve 0 si no hay ninguno pendiente.
     */
    function ultimoAnioNominasSinCierreAnual($pdo, $anioLimite) {
        $anioLimite = (int)$anioLimite;
        if ($anioLimite < 2000) {
            return 0;
        }

        // El año se deriva de periodo_desde: nominas no tiene columna anio, y un hueco
        // aquí abriría la cadena de cierres justo donde no debe.
        $st = $pdo->prepare("SELECT DISTINCT YEAR(periodo_desde) AS anio FROM nominas
                              WHERE periodo_desde IS NOT NULL AND YEAR(periodo_desde) <= ?
                              ORDER BY anio DESC");
        $st->execute([$anioLimite]);

        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $fila) {
            $candidato = (int)$fila;
            $anual = obtenerCierrePeriodoNominas($pdo, $candidato, 0);
            if (!$anual || $anual['estado'] !== 'cerrado') {
                return $candidato;
            }
        }

        return 0;
    }
}

if (!function_exists('mensaje_anio_previo_nominas')) {
    /**
     * Texto único del bloqueo por cadena de años. Lo usan la alerta HTML, el
     * SweetAlert del frontend y la rama error=anio_previo_abierto, para que el
     * texto sea el mismo llegue por donde llegue.
     *
     * $anioPendiente es el año concreto que sigue sin cerrar. Si coincide con
     * el año anterior se habla de "el año anterior" (redacción pedida); si está
     * MÁS atrás se nombra, porque "el anterior" sería engañoso cuando el hueco
     * es de varios años.
     */
    function mensaje_anio_previo_nominas($anio, $anioPendiente = 0) {
        $anio = (int)$anio;
        $anioPendiente = (int)$anioPendiente;
        $quien = ($anioPendiente > 0 && $anioPendiente === $anio - 1)
            ? 'el año anterior'
            : 'el año ' . $anioPendiente;

        return 'No se puede generar nómia de ' . $anio . ': ' . $quien
             . ' todavía no está cerrado. Cierre ese año en el módulo Cierres antes de trabajar con '
             . $anio . '.';
    }
}

if (!function_exists('bloquear_anio_previo_nominas')) {
    /**
     * Corta la peticion si anioPrecedenteOperableNominas() no da el visto
     * bueno. Mismo formato de salida que bloquear_periodo_nominas(): JSON 409
     * para AJAX y redirect con ?error= para los formularios normales.
     */
    function bloquear_anio_previo_nominas($pdo, $anio, $periodo, $es_ajax, $modulo = 'nominas.php') {
        $previo = anioPrecedenteOperableNominas($pdo, $anio);
        if (!empty($previo['permitido'])) {
            return false;
        }

        if ($es_ajax) {
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'        => false,
                'error'          => $previo['mensaje'],
                'mensaje'        => $previo['mensaje'],
                'motivo'         => $previo['motivo'],
                'anio'           => (int)$anio,
                'anio_pendiente' => (int)($previo['anio_pendiente'] ?? 0),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $modulo . '?periodo=' . urlencode($periodo)
            . '&error=anio_previo_abierto&anio=' . (int)$anio
            . '&anio_pendiente=' . (int)($previo['anio_pendiente'] ?? 0));
        exit;
    }
}

if (!function_exists('puedeCerrarMesNominas')) {
    /**
     * Precondiciones para cerrar un mes. Devuelve
     * ['puede_cerrar' => bool, 'mensaje' => string, 'estadisticas' => array].
     *
     * Reglas: no se cierra un mes que todavia no ha llegado, ni uno ya
     * cerrado, ni uno con borradores o nominas sin contabilizar, ni uno cuyo
     * cuadre de lotes descuadre. Ademas el mes anterior debe estar cerrado: los
     * periodos se cierran en orden y no se pueden saltar huecos.
     *
     * Un mes sin NINGUNA nomina se puede cerrar EN CERRO. Es la forma de dejar
     * constancia de un periodo que no se pago, sin crear nominas ficticias, y
     * es lo que permite desatascar la cadena cuando un mes se queda en blanco.
     *
     * El vacio se decide contando filas en 'nominas' y no con total_trabajadores:
     * ese total solo mira nominas contabilizadas, asi que un mes con borradores
     * daria cero tambien, y aqui los borradores ya se han descartado antes.
     *
     * @return array{puede_cerrar:bool, cierre_en_cero?:bool, mensaje:string,
     *               estadisticas?:array, periodo_desde?:string, periodo_hasta?:string}
     */
    function puedeCerrarMesNominas($pdo, $anio, $mes) {
        $anio = (int)$anio;
        $mes  = (int)$mes;
        if ($mes < 1 || $mes > 12) {
            return ['puede_cerrar' => false, 'mensaje' => 'Mes fuera de rango'];
        }

        $en_curso = periodoNominasEnCurso($pdo);
        if ($anio > $en_curso['anio'] || ($anio === $en_curso['anio'] && $mes > $en_curso['mes'])) {
            return [
                'puede_cerrar' => false,
                'mensaje' => 'No se puede cerrar un periodo que aun no ha llegado. El periodo en curso es '
                             . etiquetaMesNominas($en_curso['mes']) . ' ' . $en_curso['anio'],
            ];
        }

        $existente = obtenerCierrePeriodoNominas($pdo, $anio, $mes);
        if ($existente && $existente['estado'] === 'cerrado') {
            return ['puede_cerrar' => false, 'mensaje' => 'Este mes ya fue cerrado el ' . $existente['fecha_cierre']];
        }

        // Progresion en cadena: el mes anterior debe estar cerrado.
        $previo = periodoPrevioCerrado($pdo, $anio, $mes);
        if (empty($previo['permitido'])) {
            return [
                'puede_cerrar' => false,
                'mensaje'     => $previo['mensaje'],
                'motivo_bloqueo' => $previo['motivo'],
            ];
        }

        $periodo_desde = sprintf('%04d-%02d-01', $anio, $mes);
        $periodo_hasta = ultimoDiaDePeriodo($periodo_desde);

        $st = $pdo->prepare("SELECT COUNT(*) FROM nominas
                              WHERE periodo_desde = ? AND periodo_hasta = ?
                                AND (estado <> 'contabilizado' OR numero_nomina IS NULL)");
        $st->execute([$periodo_desde, $periodo_hasta]);
        $pendientes = (int)$st->fetchColumn();
        if ($pendientes > 0) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "Hay $pendientes filas sin contabilizar en el periodo. Contabilice o elimine las nominas en borrador antes de cerrar.",
            ];
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ?");
        $st->execute([$periodo_desde, $periodo_hasta]);
        $filas_periodo = (int)$st->fetchColumn();

        $estadisticas = resumenTotalesPeriodoNominas($pdo, $periodo_desde, $periodo_hasta);

        if ($filas_periodo === 0) {
            return [
                'puede_cerrar'   => true,
                'cierre_en_cero' => true,
                'mensaje'        => 'El periodo no tiene nominas: se puede cerrar en cero',
                'estadisticas'   => $estadisticas,
                'periodo_desde'  => $periodo_desde,
                'periodo_hasta'  => $periodo_hasta,
            ];
        }

        if (!$estadisticas || $estadisticas['total_trabajadores'] === 0) {
            return ['puede_cerrar' => false, 'mensaje' => 'No hay nominas contabilizadas en este periodo'];
        }

        $cuadre = verificarCuadreCierres($pdo, ['periodo_desde' => $periodo_desde, 'periodo_hasta' => $periodo_hasta]);
        if ($cuadre && !empty($cuadre['errores_total'])) {
            return [
                'puede_cerrar' => false,
                'mensaje' => "El periodo tiene {$cuadre['errores_total']} descuadres en los cierres de lote. Corrijalos antes de cerrar.",
                'cuadre' => $cuadre,
            ];
        }

        return [
            'puede_cerrar'  => true,
            'mensaje'       => 'Mes listo para cierre',
            'estadisticas'  => $estadisticas,
            'periodo_desde' => $periodo_desde,
            'periodo_hasta' => $periodo_hasta,
        ];
    }
}

if (!function_exists('registrarCierreMesNominas')) {
    /**
     * Cierra el mes: guarda el snapshot en cierres_periodo_nomina y avanza el
     * periodo en curso al mes siguiente. Todo en una transaccion.
     */
    function registrarCierreMesNominas($pdo, $anio, $mes, $usuario, $observaciones = '') {
        $check = puedeCerrarMesNominas($pdo, $anio, $mes);
        if (empty($check['puede_cerrar'])) {
            return ['success' => false, 'mensaje' => $check['mensaje']];
        }

        $periodo_desde = $check['periodo_desde'];
        $periodo_hasta = $check['periodo_hasta'];
        $stats  = $check['estadisticas'];
        $desglo = desgloseTiposNominaPeriodo($pdo, $periodo_desde, $periodo_hasta);

        /* Cierre EN CERRO: el periodo no tiene nominas. Se registra con todos sus
         * totales a cero y una observacion que lo explique, para que el historial
         * diga por que ese mes aparece cerrado sin nada que mostrar. */
        $cierre_en_cero = !empty($check['cierre_en_cero']);
        if ($cierre_en_cero && trim((string)$observaciones) === '') {
            $observaciones = 'Cierre en cero: el periodo no tiene nominas.';
        }
        if (!is_array($stats)) {
            $stats = [
                'total_lotes' => 0, 'total_trabajadores' => 0, 'total_devengado' => 0,
                'total_deducciones' => 0, 'total_neto' => 0, 'total_contribucion' => 0,
                'total_vacaciones' => 0,
            ];
        }
        if (!is_array($desglo)) { $desglo = []; }

        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("INSERT INTO cierres_periodo_nomina
                    (tipo, periodo_anio, periodo_mes, periodo_desde, periodo_hasta, estado,
                     total_lotes, total_trabajadores, total_devengado, total_deducciones,
                     total_neto, total_contribucion, total_vacaciones, desglose_tipos,
                     observaciones, fecha_cierre, usuario_cierre)
                  VALUES (1, ?, ?, ?, ?, 'cerrado', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                  ON DUPLICATE KEY UPDATE
                     estado = 'cerrado', total_lotes = VALUES(total_lotes),
                     total_trabajadores = VALUES(total_trabajadores),
                     total_devengado = VALUES(total_devengado),
                     total_deducciones = VALUES(total_deducciones),
                     total_neto = VALUES(total_neto),
                     total_contribucion = VALUES(total_contribucion),
                     total_vacaciones = VALUES(total_vacaciones),
                     desglose_tipos = VALUES(desglose_tipos),
                     observaciones = VALUES(observaciones),
                     fecha_cierre = NOW(), usuario_cierre = VALUES(usuario_cierre),
                     motivo_reapertura = NULL, fecha_reapertura = NULL, usuario_reapertura = NULL");
            // Un 'lote' es un numero_nomina, igual que en la tabla cierres_nomina.
            $st->execute([
                (int)$anio, (int)$mes, $periodo_desde, $periodo_hasta,
                (int)$stats['total_lotes'], $stats['total_trabajadores'], $stats['total_devengado'],
                $stats['total_deducciones'], $stats['total_neto'], $stats['total_contribucion'],
                $stats['total_vacaciones'], json_encode($desglo, JSON_UNESCAPED_UNICODE),
                $observaciones, $usuario,
            ]);

            $cierre_id = (int)$pdo->lastInsertId();

            // Avanzar el periodo en curso, pero solo hacia adelante. Cerrar un
            // mes que ya quedo atras (por ejemplo, despues de cerrar el ano, que
            // adelanta el periodo a enero del anio siguiente) no debe arrastrar
            // el periodo en curso hacia detras.
            $siguiente = ($mes === 12)
                ? ['anio' => $anio + 1, 'mes' => 1]
                : ['anio' => $anio, 'mes' => $mes + 1];
            $fecha_siguiente = sprintf('%04d-%02d-01', $siguiente['anio'], $siguiente['mes']);
            $actual = periodoNominasEnCurso($pdo);
            $r = $fecha_siguiente > $actual['fecha']
                ? actualizarPeriodoNominasEnCurso($pdo, $fecha_siguiente, $usuario)
                : null;

            $pdo->commit();

            return [
                'success'     => true,
                'mensaje'     => $cierre_en_cero
                    ? etiquetaMesNominas($mes) . ' ' . $anio . ' se cerró en cero (el periodo no tiene nominas)'
                    : 'Cierre de ' . etiquetaMesNominas($mes) . ' ' . $anio . ' registrado correctamente',
                'cierre_en_cero' => $cierre_en_cero,
                'cierre_id'   => $cierre_id,
                'estadisticas' => $stats,
                'desglose'    => $desglo,
                'en_curso'    => $r,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false, 'mensaje' => 'Error al cerrar el mes: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('reabrirCierreNominas')) {
    /**
     * Reabre un cierre. No lo borra: lo marca 'revertido' y guarda quien y por
     * que, de modo que quede rastro. $mes = 0 reabre el cierre anual.
     *
     * El cierre ANUAL es definitivo y no admite reapertura: en cuanto el ano
     * queda cerrado, ni el ano ni ninguno de sus meses pueden volver a editable.
     * Solo se reabre un mes mientras su ano siga abierto.
     */
    function reabrirCierreNominas($pdo, $tipo, $anio, $mes, $usuario, $motivo) {
        $motivo = trim((string)$motivo);
        $anio = (int)$anio;
        $mes  = (int)$mes;

        if ($motivo === '') {
            return ['success' => false, 'mensaje' => 'Debe indicar el motivo de la reapertura'];
        }

        // El cierre anual no tiene vuelta atras.
        if ($mes === 0) {
            return [
                'success' => false,
                'mensaje' => 'El cierre del año ' . $anio . ' es definitivo: una vez cerrado el año no se puede reabrir.',
                'motivo'  => 'cierre_anual_definitivo',
            ];
        }

        // Un ano cerrado congela todos sus meses: tampoco se reabre un mes.
        $anual = cierreAnualVigenteNominas($pdo, $anio);
        if ($anual) {
            return [
                'success' => false,
                'mensaje' => 'El año ' . $anio . ' está cerrado desde el ' . $anual['fecha_cierre']
                          . '. El cierre anual es definitivo, por lo que ya no se puede reabrir '
                          . etiquetaMesNominas($mes) . ' ' . $anio . '.',
                'motivo'  => 'anio_cerrado_definitivo',
            ];
        }

        $cierre = obtenerCierrePeriodoNominas($pdo, $anio, $mes);
        if (!$cierre) {
            return ['success' => false, 'mensaje' => 'Ese periodo no tiene cierre registrado'];
        }
        if ($cierre['estado'] === 'revertido') {
            return ['success' => false, 'mensaje' => 'Ese cierre ya estaba reabierto'];
        }

        /* La transaccion es de quien la abre. Si el llamante ya tiene una
         * abierta se.worka dentro de ella y NO se hace commit: cerrarla aqui
         *Confirmaria a medias el trabajo de quien la abrio. Es el mismo patron
         * que usa actualizarPeriodoNominasEnCurso. */
        $transaccion_propia = !$pdo->inTransaction();

        try {
            if ($transaccion_propia) { $pdo->beginTransaction(); }

            $st = $pdo->prepare("UPDATE cierres_periodo_nomina
                                  SET estado = 'revertido', motivo_reapertura = ?,
                                      fecha_reapertura = NOW(), usuario_reapertura = ?
                                WHERE id = ?");
            $st->execute([$motivo, $usuario, $cierre['id']]);

            if ($transaccion_propia) { $pdo->commit(); }

            $en_curso = null;
            if ((int)$tipo === 1) {
                $actual = periodoNominasEnCurso($pdo);
                $objetivo = sprintf('%04d-%02d-01', (int)$anio, (int)$mes);
                if ($objetivo < $actual['fecha']) {
                    $en_curso = actualizarPeriodoNominasEnCurso($pdo, $objetivo, $usuario);
                }
            }

            return ['success' => true, 'mensaje' => 'Cierre reabierto', 'en_curso' => $en_curso];
        } catch (PDOException $e) {
            if ($transaccion_propia && $pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false, 'mensaje' => 'Error al reabrir el cierre: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('listarCierresNominas')) {
    /** Historial de cierres, del mas reciente al mas antiguo. */
    function listarCierresNominas($pdo, $filtro = []) {
        $sql = "SELECT * FROM cierres_periodo_nomina WHERE 1 = 1";
        $params = [];
        if (!empty($filtro['tipo'])) {
            $sql .= " AND tipo = ?";
            $params[] = (int)$filtro['tipo'];
        }
        if (!empty($filtro['anio'])) {
            $sql .= " AND periodo_anio = ?";
            $params[] = (int)$filtro['anio'];
        }
        $sql .= " ORDER BY periodo_anio DESC, tipo ASC, periodo_mes DESC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('aniosConNominas')) {
    /**
     * años que tienen nómias en la tabla, sin importar el estado en que
     * están (borrador, contabilizada, revertida...).
     *
     * El combo de Cierres lo necesita porque un año puede tener nómias y no
     * tener ni un solo cierre registrado: ese año se puede cerrar, así que
     * tiene que aparecer. Si el combo solo mirara cierres, el año quedaría
     * fuera y no habría forma de cerrarlo desde la interfaz.
     *
     * @return int[] años descendentes, sin repetir.
     */
    function aniosConNominas($pdo) {
        try {
            $st = $pdo->query("SELECT DISTINCT SUBSTRING(periodo_desde, 1, 4) AS anio
                                 FROM nominas
                                WHERE periodo_desde IS NOT NULL
                                  AND periodo_desde <> ''
                                ORDER BY anio DESC");
            $anios = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'anio'));
        } catch (PDOException $e) {
            return [];
        }
        return array_values(array_filter($anios));
    }
}

if (!function_exists('resumenCierresAnioNominas')) {
    /**
     * Resumen ligero del año de nómias en curso, para los badges de la interfaz
     * (sidebar, configuracion). No calcula totales ni cuadres: solo cuenta cierres
     * vigentes en una sola consulta, así que es barato en cada carga de página.
     *
     * Devuelve ['anio' => int, 'cerrados' => int, 'total' => 12,
     *           'anio_cerrado' => bool, 'etiqueta' => '4/12'].
     */
    function resumenCierresAnioNominas($pdo, $anio = null) {
        if ($anio === null) {
            $anio = (int)(periodoNominasEnCurso($pdo)['anio'] ?? date('Y'));
        }
        $anio = (int)$anio;
        $resumen = [
            'anio'         => $anio,
            'cerrados'     => 0,
            'total'        => 12,
            'anio_cerrado' => false,
            'etiqueta'     => '0/12',
        ];
        if ($anio < 1900 || $anio > 2100) {
            return $resumen;
        }
        try {
            $st = $pdo->prepare("SELECT SUM(CASE WHEN tipo = 1 AND estado = 'cerrado' THEN 1 ELSE 0 END) AS meses,
                                        MAX(CASE WHEN tipo = 2 AND estado = 'cerrado' THEN 1 ELSE 0 END) AS anio_cerrado
                                 FROM cierres_periodo_nomina
                                 WHERE periodo_anio = ?");
            $st->execute([$anio]);
            $fila = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return $resumen;
        }
        $anioCerrado = !empty($fila['anio_cerrado']);
        // Un cierre ANUAL consolidado deja los doce meses del anio cerrados,
        // aunque no exista fila mensual para cada uno.
        $resumen['cerrados']     = $anioCerrado ? 12 : (int)($fila['meses'] ?? 0);
        $resumen['anio_cerrado'] = $anioCerrado;
        $resumen['etiqueta']     = $resumen['cerrados'] . '/12';
        return $resumen;
    }
}

if (!function_exists('estadoMesParaCierre')) {
    /**
     * Clasifica un mes concreto para efectos de cierre.
     *
     * El detalle importante es que 'tiene datos' significa 'tiene filas', no
     * 'tiene importes contabilizados'. Antes se media con el resumen contable, que
     * solo cuenta lo contabilizado, y eso hacia que un mes lleno de borradores
     * saliera como vacio: el cierre del anio lo registraba con importes en cero y
     * esas nominas quedaban congeladas sin possibility de contabilizarlas nunca.
     *
     * Claves devueltas:
     *   estado            'cerrado' | 'revertido' | 'pendiente' | 'sin_nomina'
     *   tiene_datos       hay al menos una fila en cualquier estado
     *   filas             total de filas del mes
     *   filas_contables   filas contabilizadas con numero asignado
     *   filas_pendientes  filas sin contabilizar (borrador o sin numero)
     *   cerrable          tiene datos y no le queda nada pendiente
     */
    function estadoMesParaCierre($pdo, $anio, $mes) {
        $anio = (int)$anio;
        $mes  = (int)$mes;
        $cierre = obtenerCierrePeriodoNominas($pdo, $anio, $mes);

        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = ultimoDiaDePeriodo($desde);

        $st = $pdo->prepare("SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN estado = 'contabilizado' AND numero_nomina IS NOT NULL THEN 1 ELSE 0 END), 0) AS contabilizadas,
                COALESCE(SUM(CASE WHEN estado <> 'contabilizado' OR numero_nomina IS NULL THEN 1 ELSE 0 END), 0) AS pendientes
            FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ?");
        $st->execute([$desde, $hasta]);
        $row = $st->fetch();

        $filas           = (int)($row['total'] ?? 0);
        $filas_contables = (int)($row['contabilizadas'] ?? 0);
        $filas_pendientes= (int)($row['pendientes'] ?? 0);
        $tiene_datos     = $filas > 0;

        if ($cierre && $cierre['estado'] === 'cerrado') {
            $estado = 'cerrado';
        } elseif ($cierre && $cierre['estado'] === 'revertido') {
            $estado = 'revertido';
        } elseif (!$tiene_datos) {
            $estado = 'sin_nomina';
        } else {
            $estado = 'pendiente';
        }

        return [
            'anio'             => $anio,
            'mes'              => $mes,
            'etiqueta'         => etiquetaMesNominas($mes),
            'estado'           => $estado,
            'tiene_datos'      => $tiene_datos,
            'filas'            => $filas,
            'filas_contables'  => $filas_contables,
            'filas_pendientes' => $filas_pendientes,
            /* Cerrable = no le queda nada pendiente. Un mes SIN nominas tambien
             * lo es, pero EN CERRO: deja constancia de un periodo que no se
             * pago (una quincena sin actividad, por ejemplo) sin obligar a
             * inventar una nomina ficticia. 'tiene_datos' sigue diciendo si hay
             * algo que sellar. */
            'cerrable'         => $filas_pendientes === 0,
            'cierre_en_cero'   => !$tiene_datos,
            'estadisticas'     => $tiene_datos ? resumenTotalesPeriodoNominas($pdo, $desde, $hasta) : null,
            'cierre'           => $cierre,
        ];
    }
}

if (!function_exists('mesesNominasSinCerrarAntesDe')) {
    /**
     * Meses del mismo anio, anteriores al indicado, que NO estan cerrados.
     *
     * Sirve para avisar antes de generar en un mes que tiene meses previos sin
     * cerrar. Se listan los tres casos que de verdad dejan el ano desordenado:
     *
     *   - 'pendiente': hay nominas y el mes sigue abierto.
     *   - 'revertido': se le reabrio el cierre, asi que esta tan abierto como el
     *     anterior y la cadena de cierre lo vuelve a bloquear.
     *   - 'sin_nomina': no se corrio nomina para ese mes. No es un error, pero se
     *     avisa porque el ano queda con un hueco y la unica forma de sellarlo es
     *     cerrarlo en cero.
     *
     * Solo mira hacia atras dentro del mismo anio: los meses futuros ya los
     * bloquea la regla de "periodo que aun no ha llegado", y los anios anteriores
     * los encadena el cierre anual al abrir enero.
     *
     * @return array<int, array{mes:int, etiqueta:string, estado:string,
     *                          tiene_datos:bool, filas:int}>
     */
    function mesesNominasSinCerrarAntesDe($pdo, $anio, $mes) {
        $anio = (int)$anio;
        $mes  = (int)$mes;
        if ($mes < 1 || $mes > 12 || $anio < 2000) {
            return [];
        }

        /* Dos consultas en vez de llamar a estadoMesParaCierre mes a mes: esta
         * funcion se ejecuta en cada carga de la pagina para saber si hay que
         * avisar, y recorrer hasta once meses con una consulta cada uno se
         * nota. La foto del ano se arma una vez y se recorre en memoria. */
        try {
            $st = $pdo->prepare("SELECT periodo_mes, estado
                                   FROM cierres_periodo_nomina
                                  WHERE tipo = 1 AND periodo_anio = ?");
            $st->execute([$anio]);
            $cierres = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cierres[(int)$c['periodo_mes']] = $c['estado'];
            }

            $st = $pdo->prepare("SELECT SUBSTRING(periodo_desde, 6, 2) AS m, COUNT(*) AS filas
                                   FROM nominas
                                  WHERE periodo_desde >= ? AND periodo_desde <= ?
                                  GROUP BY m");
            $st->execute([
                sprintf('%04d-01-01', $anio),
                sprintf('%04d-12-31', $anio),
            ]);
            $filas_por_mes = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $filas_por_mes[(int)$f['m']] = (int)$f['filas'];
            }
        } catch (PDOException $e) {
            // Sin datos no se inventa un hueco: es preferible no avisar a avisar mal.
            return [];
        }

        $pendientes = [];
        for ($m = 1; $m < $mes; $m++) {
            $estadoCierre = $cierres[$m] ?? null;
            $filas = $filas_por_mes[$m] ?? 0;

            if ($estadoCierre === 'cerrado') {
                continue;
            }

            $estado = $estadoCierre === 'revertido'
                ? 'revertido'
                : ($filas > 0 ? 'pendiente' : 'sin_nomina');

            $pendientes[] = [
                'mes'         => $m,
                'etiqueta'    => etiquetaMesNominas($m),
                'estado'      => $estado,
                'tiene_datos' => $filas > 0,
                'filas'       => $filas,
            ];
        }
        return $pendientes;
    }
}

if (!function_exists('mesesDelAnioConEstado')) {
    /**
     * Estado de los 12 meses de un anio, para pintar el panel de cierre anual.
     * Un mes sin nominas se marca 'sin_nomina': se puede cerrar igual, pero no
     * aporta totales.
     */
    function mesesDelAnioConEstado($pdo, $anio) {
        $anio = (int)$anio;
        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $meses[$m] = estadoMesParaCierre($pdo, $anio, $m);
        }
        return $meses;
    }
}

if (!function_exists('puedeCerrarAnioNominas')) {
    /**
     * Precondiciones del cierre anual.
     *
     * Se puede cerrar un ano sin datos en todos sus meses: los meses sin nominas
     * se registran automaticamente al cerrar el ano, para no obligar a crear
     * nominas ficticias. Lo que no se permite es dejar meses con datos abiertos.
     */
    function puedeCerrarAnioNominas($pdo, $anio) {
        $anio = (int)$anio;

        $en_curso = periodoNominasEnCurso($pdo);
        if ($anio > $en_curso['anio']) {
            return [
                'puede_cerrar' => false,
                'mensaje' => 'No se puede cerrar un año que aún no ha llegado. El periodo en curso es '
                             . etiquetaMesNominas($en_curso['mes']) . ' ' . $en_curso['anio'],
            ];
        }

        $existente = obtenerCierrePeriodoNominas($pdo, $anio, 0);
        if ($existente && $existente['estado'] === 'cerrado') {
            return ['puede_cerrar' => false, 'mensaje' => 'Este año ya fue cerrado el ' . $existente['fecha_cierre']];
        }

        // Los anios tambien se cierran en cadena: el anio anterior debe estar
        // cerrado antes de consolidar el siguiente.
        $previo = periodoPrevioCerrado($pdo, $anio, 1);
        if (empty($previo['permitido'])) {
            return [
                'puede_cerrar'  => false,
                'mensaje'      => $previo['mensaje'],
                'motivo_bloqueo' => $previo['motivo'],
            ];
        }

        $meses = mesesDelAnioConEstado($pdo, $anio);
        $pendientes = [];
        $cerrados    = [];
        $sin_datos   = [];   // numeros de mes, para poder insertar el cierre con ceros
        $sin_contabilizar = [];
        foreach ($meses as $num => $m) {
            if ($m['estado'] === 'cerrado') {
                $cerrados[] = $m['etiqueta'];
            } elseif (!$m['tiene_datos']) {
                $sin_datos[] = (int)$num;
            } elseif ((int)($m['filas_pendientes'] ?? 0) > 0) {
                // Distinto de 'falta cerrar': aqui ya hay nominas emitidas y solo
                // falta contabilizarlas. Cerrar el anio dejaria el mes congelado
                // con la nomina a medias, asi que no se permite.
                $sin_contabilizar[] = $m['etiqueta'] . ' (' . (int)$m['filas_pendientes'] . ' sin contabilizar)';
            } else {
                $pendientes[] = $m['etiqueta'];
            }
        }

        // Las nominas sin contabilizar se reportan antes que los meses sin cerrar:
        // es el motivo por el que el usuario llega aqui casi siempre, y el
        // mensaje tiene que decir que hacer.
        if (!empty($sin_contabilizar)) {
            return [
                'puede_cerrar' => false,
                'mensaje' => 'Hay nominas sin contabilizar: ' . implode(', ', $sin_contabilizar)
                             . '. Contabilice o elimine esas nominas antes de cerrar el año.',
                'motivo_bloqueo' => 'nominas_sin_contabilizar',
                'sin_contabilizar' => $sin_contabilizar,
            ];
        }

        if (!empty($pendientes)) {
            return [
                'puede_cerrar' => false,
                'mensaje' => 'Faltan por cerrar meses con nominas: ' . implode(', ', $pendientes),
                'pendientes' => $pendientes,
            ];
        }

        $etiquetas_sin_datos = [];
        foreach ($sin_datos as $num) {
            $etiquetas_sin_datos[] = etiquetaMesNominas($num);
        }

        return [
            'puede_cerrar' => true,
            'mensaje'     => 'El año está listo para cierre',
            'cerrados'    => $cerrados,
            'sin_datos'   => $sin_datos,
            'sin_datos_etiquetas' => $etiquetas_sin_datos,
            'meses'       => $meses,
        ];
    }
}

if (!function_exists('registrarCierreAnioNominas')) {
    /**
     * Cierra el anio: consolida los cierres mensuales ya registrados y, para los
     * meses que no tuvieron nominas, deja un cierre con ceros y la nota
     * 'Sin nominas en el periodo'. Al terminar avanza el periodo en curso a
     * enero del ano siguiente si ya iba por diciembre.
     */
    function registrarCierreAnioNominas($pdo, $anio, $usuario, $observaciones = '') {
        $check = puedeCerrarAnioNominas($pdo, $anio);
        if (empty($check['puede_cerrar'])) {
            return ['success' => false, 'mensaje' => $check['mensaje']];
        }

        $anio = (int)$anio;

        $totales = [
            'total_lotes' => 0, 'total_trabajadores' => 0, 'total_devengado' => 0.0,
            'total_deducciones' => 0.0, 'total_neto' => 0.0, 'total_contribucion' => 0.0,
            'total_vacaciones' => 0.0,
        ];
        $desglosePorTipo = [];
        $desglosePorMes  = [];

        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT * FROM cierres_periodo_nomina
                                  WHERE tipo = 1 AND periodo_anio = ? AND estado = 'cerrado'
                                  ORDER BY periodo_mes");
            $st->execute([$anio]);
            $cierresMes = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cierresMes as $c) {
                $totales['total_lotes']         += (int)$c['total_lotes'];
                // total_trabajadores NO se suma aqui: se recalcula como personas
                // distintas del anio mas abajo, antes de insertar el cierre.
                $totales['total_devengado']     += (float)$c['total_devengado'];
                $totales['total_deducciones']   += (float)$c['total_deducciones'];
                $totales['total_neto']          += (float)$c['total_neto'];
                $totales['total_contribucion']  += (float)$c['total_contribucion'];
                $totales['total_vacaciones']    += (float)$c['total_vacaciones'];

                $mesEtiq = etiquetaMesNominas($c['periodo_mes']);
                $detalle = $c['desglose_tipos'] ? json_decode($c['desglose_tipos'], true) : [];
                foreach ((array)$detalle as $d) {
                    $tipo = $d['tipo_nomina'] ?? 'desconocido';
                    if (!isset($desglosePorTipo[$tipo])) {
                        $desglosePorTipo[$tipo] = ['tipo_nomina' => $tipo, 'lotes' => 0, 'trabajadores' => 0, 'devengado' => 0.0, 'neto' => 0.0];
                    }
                    // Las filas viejas del JSON venian por lote (1 por lote); las
                    // nuevas vienen por tipo y traen 'lotes' ya contados.
                    $desglosePorTipo[$tipo]['lotes']         += (int)($d['lotes'] ?? 1);
                    $desglosePorTipo[$tipo]['trabajadores'] += (int)$d['trabajadores'];
                    $desglosePorTipo[$tipo]['devengado']     += (float)$d['devengado'];
                    $desglosePorTipo[$tipo]['neto']          += (float)$d['neto'];
                }
                $desglosePorMes[] = [
                    'mes'      => $mesEtiq,
                    'lotes'    => (int)$c['total_lotes'],
                    'devengado' => round((float)$c['total_devengado'], 2),
                    'neto'     => round((float)$c['total_neto'], 2),
                ];
            }

            // Trabajadores del anio: personas distintas en todo el anio. Sumar los
            // totales mensuales (arriba) contaria varias veces a quien cobro en mas
            // de un mes, y el resultado superaria la plantilla real.
            $stTrabAnio = $pdo->prepare("SELECT COUNT(DISTINCT trabajador_id)
                                           FROM nominas
                                          WHERE periodo_desde >= ? AND periodo_hasta <= ?
                                            AND estado = 'contabilizado'
                                            AND numero_nomina IS NOT NULL");
            $stTrabAnio->execute([$anio . '-01-01', $anio . '-12-31']);
            $totales['total_trabajadores'] = (int)$stTrabAnio->fetchColumn();

            // Meses sin nominas: cierre con ceros para que el anio quede completo.
            $insCero = $pdo->prepare("INSERT INTO cierres_periodo_nomina
                    (tipo, periodo_anio, periodo_mes, periodo_desde, periodo_hasta, estado,
                     total_lotes, total_trabajadores, total_devengado, total_deducciones,
                     total_neto, total_contribucion, total_vacaciones, observaciones,
                     fecha_cierre, usuario_cierre)
                  VALUES (1, ?, ?, ?, ?, 'cerrado', 0, 0, 0, 0, 0, 0, 0, ?, NOW(), ?)
                  ON DUPLICATE KEY UPDATE estado = 'cerrado'");
            foreach ($check['sin_datos'] as $m) {
                $desde = sprintf('%04d-%02d-01', $anio, (int)$m);
                $insCero->execute([$anio, (int)$m, $desde, ultimoDiaDePeriodo($desde), 'Sin nominas en el periodo', $usuario]);
            }

            $totales['total_devengado']    = round($totales['total_devengado'], 2);
            $totales['total_deducciones']  = round($totales['total_deducciones'], 2);
            $totales['total_neto']         = round($totales['total_neto'], 2);
            $totales['total_contribucion'] = round($totales['total_contribucion'], 2);
            $totales['total_vacaciones']   = round($totales['total_vacaciones'], 2);

            $nota = $observaciones;
            if (!empty($check['sin_datos'])) {
                $nota = trim($observaciones . ' | Meses sin nominas: ' . implode(', ', $check['sin_datos_etiquetas']));
            }

            $desglose = ['por_tipo' => array_values($desglosePorTipo), 'por_mes' => $desglosePorMes];

            $ins = $pdo->prepare("INSERT INTO cierres_periodo_nomina
                    (tipo, periodo_anio, periodo_mes, periodo_desde, periodo_hasta, estado,
                     total_lotes, total_trabajadores, total_devengado, total_deducciones,
                     total_neto, total_contribucion, total_vacaciones, desglose_tipos,
                     observaciones, fecha_cierre, usuario_cierre)
                  VALUES (2, ?, 0, ?, ?, 'cerrado', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                  ON DUPLICATE KEY UPDATE
                     estado = 'cerrado', total_lotes = VALUES(total_lotes),
                     total_trabajadores = VALUES(total_trabajadores),
                     total_devengado = VALUES(total_devengado),
                     total_deducciones = VALUES(total_deducciones),
                     total_neto = VALUES(total_neto),
                     total_contribucion = VALUES(total_contribucion),
                     total_vacaciones = VALUES(total_vacaciones),
                     desglose_tipos = VALUES(desglose_tipos),
                     observaciones = VALUES(observaciones),
                     fecha_cierre = NOW(), usuario_cierre = VALUES(usuario_cierre),
                     motivo_reapertura = NULL, fecha_reapertura = NULL, usuario_reapertura = NULL");
            $ins->execute([
                $anio, $anio . '-01-01', $anio . '-12-31',
                $totales['total_lotes'], $totales['total_trabajadores'], $totales['total_devengado'],
                $totales['total_deducciones'], $totales['total_neto'], $totales['total_contribucion'],
                $totales['total_vacaciones'], json_encode($desglose, JSON_UNESCAPED_UNICODE),
                $nota, $usuario,
            ]);

            $cierre_id = (int)$pdo->lastInsertId();

            // Si el ano en curso era el que se acaba de cerrar, pasar a enero.
            $en_curso = null;
            $actual = periodoNominasEnCurso($pdo);
            if ($actual['anio'] === $anio) {
                $en_curso = actualizarPeriodoNominasEnCurso($pdo, sprintf('%04d-01-01', $anio + 1), $usuario);
            }

            $pdo->commit();

            return [
                'success'    => true,
                'mensaje'    => "Cierre del año $anio registrado correctamente",
                'cierre_id'  => $cierre_id,
                'estadisticas' => $totales,
                'sin_datos'  => $check['sin_datos_etiquetas'],
                'en_curso'   => $en_curso,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false, 'mensaje' => 'Error al cerrar el año: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('redondearImporte')) {
/**
 * Redondeo a dos decimales con la misma regla que roundExcel() del modulo de
 * nominas, pero sin depender de el: asi estas funciones se pueden llamar desde
 * configuracion.php, reportes y cualquier otro modulo sin arrastrar el modulo
 * de nominas entero.
 */
function redondearImporte($valor) {
    return floor(((float)$valor) * 100 + 0.5) / 100;
}
}

if (!function_exists('cargarTarifasTrabajoExtraordinario')) {
/**
 * Carga las tarifas del trabajo extraordinario y de los turnos nocturnos desde
 * configuracion_general.
 *
 * Solo quedan tres parametros, los tres con origen legal:
 *  - recargo_trabajo_extraordinario: Ley 189/2026 "Codigo de Trabajo", art. 227
 *    y 230, incremento del 25 % por cada hora en exceso (1.25 = 125 %).
 *  - tarifa_nocturnidad_temprana: Res. 15/2026 MTSS, QUINTO.2, turno de
 *    19:00 a 23:00, 0.60 pesos por hora.
 *  - tarifa_nocturnidad_tardia: Res. 15/2026 MTSS, QUINTO.2, turno de 23:00 a
 *    07:00, 1.15 pesos por hora.
 *
 * Los parametros anteriores (recargo_nocturno, recargo_extra_diurna,
 * recargo_extra_nocturna, recargo_doble_turno) quedaron sin efecto: los
 * nocturnos ya no son un porcentaje del salario sino una tarifa fija en pesos,
 * y la Ley 189/2026 unifica en el mismo 25 % las horas extras y el doble turno.
 */
function cargarTarifasTrabajoExtraordinario($pdo) {
    $defaults = [
        'recargo_trabajo_extraordinario' => 1.25,
        'tarifa_nocturnidad_temprana'     => 0.60,
        'tarifa_nocturnidad_tardia'       => 1.15,
    ];

    $tarifas = $defaults;
    try {
        $stmt = $pdo->prepare(
            "SELECT parametro, valor FROM configuracion_general
             WHERE parametro IN ('recargo_trabajo_extraordinario',
                                 'tarifa_nocturnidad_temprana',
                                 'tarifa_nocturnidad_tardia')"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            // Un valor vacio o no numerico no debe dejar la tarifa a cero: se
            // conserva el valor legal por defecto.
            if (isset($fila['valor']) && is_numeric($fila['valor']) && $fila['valor'] !== '') {
                $tarifas[$fila['parametro']] = (float)$fila['valor'];
            }
        }
    } catch (PDOException $e) {
        // Sin configuracion legible se usan los valores de la norma.
    }

    return $tarifas;
}
}

if (!function_exists('cargarTarifasConvenio')) {
/**
 * Carga las tarifas pactadas por Convenio Colectivo de Trabajo desde
 * configuracion_general. Son valores fijos en pesos por hora ($/h) que, cuando
 * una nómia extraordinaria se genera con la opcion "Convenio Colectivo
 * Empleador - Empleado", sustituyen al recargo de la Ley 189/2026 y a las
 * tarifas fijas de la Res. 15/2026 MTSS.
 */
function cargarTarifasConvenio($pdo) {
    $defaults = [
        'convenio_valor_he'                   => 10.25,
        'convenio_valor_doble_turno'          => 20.05,
        'convenio_valor_nocturnidad_temprana' => 8.00,
        'convenio_valor_nocturnidad_tardia'   => 14.75,
    ];

    $tarifas = $defaults;
    try {
        $stmt = $pdo->prepare(
            "SELECT parametro, valor FROM configuracion_general
             WHERE parametro IN ('convenio_valor_he',
                                 'convenio_valor_doble_turno',
                                 'convenio_valor_nocturnidad_temprana',
                                 'convenio_valor_nocturnidad_tardia')"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if (isset($fila['valor']) && is_numeric($fila['valor']) && $fila['valor'] !== '') {
                $tarifas[$fila['parametro']] = (float)$fila['valor'];
            }
        }
    } catch (PDOException $e) {
    }

    return $tarifas;
}
}

if (!function_exists('cargarTasaCessEspecial')) {
/**
 * Tasa de la Contribucion a la Seguridad Social usada cuando la nomina se
 * calcula por el modo "total_rangos" (ISIP): se aplica como porcentaje plano
 * sobre el devengado, y por separado corren los rangos progresivos de ISIP.
 *
 * Se lee de configuracion_tasas.nombre_tasa = 'contribucion_especial', que es
 * la misma fila que usa el servidor al guardar, de modo que la previsualizacion
 * no puede quedar con una tasa distinta de la que se persiste.
 *
 * @return float  Porcentaje, por ejemplo 5 para el 5 %.
 */
function cargarTasaCessEspecial($pdo) {
    // Delega en getTasaContribucion() (config/database.php), que ya filtra por
    // fecha de vigencia: una sola implementaci??n para leer la tasa canónica de
    // configuracion_tasas.nombre_tasa = 'contribucion_especial'.
    try {
        $valor = getTasaContribucion($pdo);

        if (is_numeric($valor) && (float)$valor > 0) {
            return (float)$valor;
        }
    } catch (Exception $e) {
    }

    return 5.0;
}
}

if (!function_exists('cargarParamsCessProgresiva')) {
/**
 * Carga los parametros de la CESS progresiva (PDL SOLO CESS):
 * - base (porcentaje) desde configuracion_tasas
 * - exceso (porcentaje) y limite (CUP) desde configuracion_general
 *
 * @return array ['base'=>5.0, 'exceso'=>10.0, 'limite'=>15000.0]
 */
function cargarParamsCessProgresiva($pdo) {
    $params = ['base' => 5.0, 'exceso' => 10.0, 'limite' => 15000.0];

    try {
        $params['base'] = cargarTasaCessEspecial($pdo);
    } catch (Exception $e) {
        $params['base'] = 5.0;
    }

    try {
        $stmt = $pdo->prepare("SELECT parametro, valor FROM configuracion_general
                               WHERE parametro IN ('cess_tasa_exceso','cess_limite_progresivo')");
        $stmt->execute();
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $v = $r['valor'];
            if (!is_numeric($v)) continue;
            if ($r['parametro'] === 'cess_tasa_exceso') {
                $params['exceso'] = (float)$v;
            } elseif ($r['parametro'] === 'cess_limite_progresivo') {
                $params['limite'] = (float)$v;
            }
        }
    } catch (PDOException $e) {
    }

    return $params;
}
}

if (!function_exists('calcularImporteTrabajoExtraordinario')) {
/**
 * Calcula los importes del trabajo extraordinario y de los turnos nocturnos de
 * una nomina de tipo "extraordinaria".
 *
 * Es la unica fuente de la regla y la usan las tres rutas que hoy la repetian
 * (resumen AJAX, generacion de nomina y agregacion a una nomina existente), de
 * modo que ya no pueden divergir entre si.
 *
 * Reglas aplicadas:
 *  - Ley 189/2026, art. 230.1: toda hora en exceso se remunera con un
 *    incremento del 25 % sobre el salario por hora, es decir, 1.25. El doble
 *    turno es trabajo extraordinario segun el art. 227, por lo que usa la misma
 *    tarifa.
 *  - Res. 15/2026 MTSS, QUINTO.2: los turnos nocturnos no se remuneran como
 *    porcentaje del salario sino con una tarifa fija en pesos por hora
 *    (0.60 entre 19:00 y 23:00, 1.15 entre 23:00 y 07:00).
 *  - Convenio Colectivo de Trabajo (Empleador - Empleado): si $usar_convenio es
 *    true se usan los valores pactados de $tarifas_convenio (pesos por hora
 *    fijos para horas extras, doble turno y las dos nocturnidades), sin recargo
 *    sobre el salario del trabajador.
 *
 * @param float $salario_hora  Salario por hora ordinario del trabajador.
 * @param array $cantidades    horas_he, horas_nocturnas_tempranas,
 *                             horas_nocturnas_tardias, horas_doble_turno.
 * @param array $tarifas       Resultado de cargarTarifasTrabajoExtraordinario().
 * @param bool  $usar_convenio True para aplicar las tarifas pactadas por convenio.
 * @param array $tarifas_convenio Resultado de cargarTarifasConvenio() (solo se usa si $usar_convenio).
 * @return array
 */
function calcularImporteTrabajoExtraordinario($salario_hora, array $cantidades, array $tarifas, $usar_convenio = false, array $tarifas_convenio = []) {
    $salario_hora = (float)$salario_hora;

    $horas_he    = max(0, (float)($cantidades['horas_he'] ?? 0));
    $horas_nt_t  = max(0, (float)($cantidades['horas_nocturnas_tempranas'] ?? 0));
    $horas_nt_d  = max(0, (float)($cantidades['horas_nocturnas_tardias'] ?? 0));
    $horas_dt    = max(0, (float)($cantidades['horas_doble_turno'] ?? 0));

    if ($usar_convenio) {
        // Valores pactados por Convenio Colectivo de Trabajo: tarifas fijas en
        // pesos por hora, sin recargo porcentual sobre el salario del trabajador.
        $tar_cu_he = (float)($tarifas_convenio['convenio_valor_he'] ?? 10.25);
        $tar_cu_dt = (float)($tarifas_convenio['convenio_valor_doble_turno'] ?? 20.05);
        $tar_cu_t  = (float)($tarifas_convenio['convenio_valor_nocturnidad_temprana'] ?? 8.00);
        $tar_cu_d  = (float)($tarifas_convenio['convenio_valor_nocturnidad_tardia'] ?? 14.75);

        $importe_he_diurnas    = redondearImporte($horas_he * $tar_cu_he);
        $importe_noct_temprana = redondearImporte($horas_nt_t * $tar_cu_t);
        $importe_noct_tardia   = redondearImporte($horas_nt_d * $tar_cu_d);
        $importe_doble_turno   = redondearImporte($horas_dt * $tar_cu_dt);
    } else {
        $recargo = (float)($tarifas['recargo_trabajo_extraordinario'] ?? 1.25);
        $tar_t   = (float)($tarifas['tarifa_nocturnidad_temprana'] ?? 0.60);
        $tar_d   = (float)($tarifas['tarifa_nocturnidad_tardia'] ?? 1.15);

        $importe_he_diurnas    = redondearImporte($salario_hora * $recargo * $horas_he);
        $importe_noct_temprana = redondearImporte($horas_nt_t * $tar_t);
        $importe_noct_tardia   = redondearImporte($horas_nt_d * $tar_d);
        $importe_doble_turno   = redondearImporte($salario_hora * $recargo * $horas_dt);
    }

    $importe_nocturnas = redondearImporte($importe_noct_temprana + $importe_noct_tardia);

    return [
        'importe_he_diurnas'        => $importe_he_diurnas,
        'importe_noct_temprana'     => $importe_noct_temprana,
        'importe_noct_tardia'       => $importe_noct_tardia,
        'importe_nocturnas'         => $importe_nocturnas,
        'importe_doble_turno'       => $importe_doble_turno,
        // El salario laboral guarda las horas extras y el doble turno, que es lo
        // que verifica exportar_bandec.php al cuadrar la nomina extraordinaria.
        'importe_salario_laboral'   => redondearImporte($importe_he_diurnas + $importe_doble_turno),
        'total_devengado'           => redondearImporte($importe_he_diurnas + $importe_nocturnas + $importe_doble_turno),
        // Suma de las cuatro magnitudes informative. El doble turno se cuenta
        // una sola vez: las horas nocturnas ya son horas de la jornada, no una
        // categoria adicional a sumar aparte del total.
        'total_horas'               => redondearImporte($horas_he + $horas_nt_t + $horas_nt_d),
    ];
}
}

if (!function_exists('validarPareoNocturno48')) {
    /**
     * Coherencia de la nocturnidad por Convenio. Hay serenos de turno exclusivo
     * (solo 19:00-23:00 o solo 23:00-07:00): si una de las dos franjas está en
     * cero se permite cualquier cantidad de horas en la otra. Cuando hay ambas
     * franjas, cada noche son 4 h (19:00-23:00) + 8 h (23:00-07:00), de modo que
     * la tardía debe ser el doble de la temprana con margen de ±1 noche parcial
     * (el turno puede empezar/finalizar fuera de la franja completa). Devuelve
     * null si el parámetro cumple o un mensaje con el error si las dos franjas
     * narran historias distintas (p. ej. 132:132). El pago es SIEMPRE por hora:
     * la noche completa 4:8 da $150, cualquier parcial se paga proporcional,
     * así que aquí solo se valida coherencia.
     */
    function validarPareoNocturno48($noct_t, $noct_d) {
        $noct_t = max(0, (float)$noct_t);
        $noct_d = max(0, (float)$noct_d);
        if ($noct_t == 0 || $noct_d == 0) return null;
        $desvio = abs($noct_d - ($noct_t * 2));
        if ($desvio > 8.001) {
            $min = max(0, round($noct_t * 2 - 8, 2));
            $max = round($noct_t * 2 + 8, 2);
            return 'Nocturnidades incoherentes: con las dos franjas activas cada noche son 4 h (19:00-23:00) + 8 h (23:00-07:00), con margen de ±1 noche parcial. Con ' . $noct_t . ' h tempranas, las tardías deben estar entre ' . $min . ' y ' . $max . ' h (capturó ' . $noct_d . ' h). Si el trabajador solo hace un turno (19:00-23:00 o 23:00-07:00), capture 0 en la otra franja.';
        }
        return null;
    }
}

if (!function_exists('horasTrabajoExtraordinarioTotales')) {
/**
 * Total de horas extraordinarias de un trabajador en un año natural, sumando horas
 * extras, doble turno y habilitación de días de descanso semanal.
 *
 * Ley 189/2026 "Codigo de Trabajo", art. 229.2: la persona trabajadora no está
 * obligada a laborar MÁS de 160 horas extraordinarias al año. El dato es
 * informativo porque el módulo trabaja con totales mensuales y no puede saber
 * cómo se reparten entre días, de modo que el control diario y semanal queda en
 * manos de la entidad.
 */
function horasTrabajoExtraordinarioTotales($pdo, $trabajador_id, $anio, $excluir_nomina_id = 0) {
    try {
        $sql = "SELECT COALESCE(SUM(n.horas_laboradas), 0) + COALESCE(SUM(n.horas_doble_turno), 0) AS total
                FROM nominas n
                WHERE n.trabajador_id = ?
                  AND n.tipo_nomina = 'extraordinaria'
                  AND YEAR(n.periodo_desde) = ?";
        $params = [$trabajador_id, (int)$anio];
        // Al editar una fila ya guardada, su registro se excluye del acumulado
        // para que el aviso del tope de 160 h no duplique las propias horas.
        if ((int)$excluir_nomina_id > 0) {
            $sql .= " AND n.id <> ?";
            $params[] = (int)$excluir_nomina_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.0;
    }
}
}
?>






