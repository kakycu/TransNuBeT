<?php
// includes/functions.php



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
 * Calcula la contribución especial a la seguridad social
 * 
 * @param PDO $pdo
 * @param float $salario_devengado
 * @return float
 */
if (!function_exists('calcularContribucionEspecial')) {
function calcularContribucionEspecial($pdo, $salario_devengado) {
    $stmt = $pdo->query("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1");
    $tasa = $stmt->fetchColumn();
    $tasa = $tasa ? (float)$tasa : 5.0;
    
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
 * Fórmula: Días de vacaciones = Días trabajados ÷ 11
 * Valor a pagar = Salarios devengados ÷ 11
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
 * Obtiene todas las nóminas de un trabajador
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
 * Registra un cierre de nómina
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
 * Obtiene el último cierre de nómina para un período
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
 * Verifica si las tablas referenciales/clasificadoras están vacías y, si es
 * así, emite una barra fija superior (estilo "Solicitud de cambio de
 * contraseña pendiente") con una "X" para cerrar. La barra aparece en cada
 * carga de página mientras existan clasificadores vacíos (no se recuerda la
 * decisión: al recargar o abrir otra página vuelve a mostrarse).
 *
 * Uso: se invoca automáticamente desde includes/footer.php; también puede
 * llamarse desde cualquier módulo (donde $pdo ya esté disponible):
 *     avisarClasificadoresVacios($pdo);                                       // verifica todas
 *     avisarClasificadoresVacios($pdo, ['areas', 'centros_costo']);          // solo algunas
 *
 * @param PDO   $pdo    Conexión PDO activa.
 * @param array $tablas Lista de tablas a verificar. Si se omite, se verifican todas.
 * @return void
 */
if (!function_exists('getClasificadoresVacios')) {
function getClasificadoresVacios($pdo, $tablas = []) {
    // Determina la URL base del directorio "modules" según desde dónde se invoque:
    // dashboard.php está en /nominas/ y los módulos en /nominas/modules/.
    $script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $base_modules = (basename($script_dir) === 'modules') ? $script_dir : $script_dir . '/modules';

    $config = [
        'trabajadores'             => ['nombre' => 'Trabajadores',             'icono' => 'fa-users',                'url' => $base_modules . '/empleados.php?nuevo=1'],
        'areas'                    => ['nombre' => 'Áreas',                    'icono' => 'fa-building',             'url' => $base_modules . '/clasificadores.php?tabla=areas&nuevo=1'],
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
 * llamarse desde cualquier módulo (donde $pdo ya esté disponible):
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
 * filas contabilizadas de esa nómina (periodo + tipo + numero) y valida la
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

    // Suma real por nómina contabilizada (para comparar con los totales del cierre)
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
            ['total_contribucion', 'Contribución CESS', $total_cont, floatval($c['total_contribucion'] ?? 0), 0.02],
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
?>
