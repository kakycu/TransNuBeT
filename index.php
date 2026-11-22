<?php
/**
 * Landing público del Entorno de Aplicaciones Web de UnicornioSoftware® Cuba.
 *
 * Vive en la raíz del sitio para que http://localhost/ muestre esta portada.
 * Todo el sistema (portada de servicios, nóminas, facturación, paneles…) quedó
 * bajo /servicios/. Los enlaces antiguos sin /servicios/ se redirigen desde el
 * .htaccess de la raíz.
 *
 * Estilos: tokens del tema NeuroAI (fondo #070a12, superficies glass, acento
 * violeta #6d5dfc y cian #24d6ff, tipografía Inter).
 *
 * La tarjeta del hero muestra cifras reales del último mes leídas de las bases
 * locales (facturación y nóminas). Si MySQL está apagado no se inventan datos:
 * se usa el último resultado guardado, indicando su fecha, o «sin conexión».
 */

/* ============================================================================
 * Datos reales del último mes.
 *
 * Consultas de solo lectura, cortas y tolerantes a fallos: si el motor está
 * apagado la portada se dibuja igual y avisa en la propia tarjeta.
 * ========================================================================== */

date_default_timezone_set('America/Havana');

function landing_mes_nombre(int $mes): string
{
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
              'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $meses[$mes] ?? '';
}

function landing_num(float $n): string
{
    return number_format($n, 0, ',', ' ');
}

/** Cifra compacta para las tarjetas pequeñas (128450 → «128,5 mil»). */
function landing_num_corto(float $n): string
{
    $abs = abs($n);
    if ($abs >= 1000000) {
        return number_format($n / 1000000, 2, ',', '') . ' M';
    }
    if ($abs >= 100000) {
        return number_format($n / 1000, 1, ',', '') . ' mil';
    }
    return landing_num($n);
}

/** Credenciales desde el config.php de cada módulo, sin incluirlo. */
function landing_bd_config(string $archivo): array
{
    $cfg = [];
    if (!is_readable($archivo)) {
        return $cfg;
    }
    $texto = (string)file_get_contents($archivo);
    foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $clave) {
        if (preg_match("/define\\(\\s*'" . $clave . "'\\s*,\\s*'([^']*)'\\s*\\)/", $texto, $m)) {
            $cfg[$clave] = $m[1];
        }
    }
    return $cfg;
}

/**
 * Comprueba el puerto antes de crear el PDO. Con MySQL apagado, intentar
 * conectar desde Apache tumba el proceso hijo en este stack (Windows + PHP
 * 8.1); el sondeo de socket evita ese camino y responde al instante.
 */
function landing_bd_abierto(string $host, int $puerto, float $timeout = 0.6): bool
{
    if ($host === '' || strcasecmp($host, 'localhost') === 0) {
        $host = '127.0.0.1';
    }
    $socket = @fsockopen($host, $puerto, $errno, $errstr, $timeout);
    if (!is_resource($socket)) {
        return false;
    }
    fclose($socket);
    return true;
}

/** Conexión corta: si MySQL no responde devuelve null sin romper la portada. */
function landing_bd(?array $cfg): ?PDO
{
    if (!$cfg || empty($cfg['DB_NAME'])) {
        return null;
    }

    $host = trim((string)($cfg['DB_HOST'] ?? 'localhost'));
    $puerto = 3306;
    if (preg_match('/^(.*?)[;\s]*port\s*=\s*(\d+)/i', $host, $m)) {
        $host = trim($m[1], " ;\t") ?: 'localhost';
        $puerto = (int)$m[2];
    }

    if (!landing_bd_abierto($host, $puerto)) {
        return null;
    }

    try {
        return new PDO(
            'mysql:host=' . ($host ?: 'localhost') . ';port=' . $puerto . ';dbname=' . $cfg['DB_NAME'] . ';charset=utf8mb4',
            $cfg['DB_USER'] ?: 'root',
            $cfg['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function landing_snapshot_ruta(): string
{
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'unicornio_landing_datos.json';
}

function landing_snapshot_leer(): array
{
    $ruta = landing_snapshot_ruta();
    if (!is_readable($ruta)) {
        return [];
    }
    $json = json_decode((string)file_get_contents($ruta), true);
    return is_array($json) ? $json : [];
}

function landing_snapshot_guardar(array $datos): void
{
    @file_put_contents(
        landing_snapshot_ruta(),
        json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

/** Facturación del mes indicado, serie de 8 meses y última factura emitida. */
function landing_facturacion(?PDO $pdo, int $anio, int $mes): array
{
    $datos = [
        'ok' => false, 'fuente' => 'vivo',
        'anio' => $anio, 'mes' => $mes,
        'etiqueta' => landing_mes_nombre($mes) . ' ' . $anio,
        'mes_corto' => substr(landing_mes_nombre($mes), 0, 3),
        'exacto' => true,
        'total' => 0.0, 'facturas' => 0, 'cobrado' => 0.0,
        'variacion' => null, 'mes_anterior' => '',
        'serie' => [], 'ultima' => null,
        'generado' => date('Y-m-d H:i'),
    ];

    if (!$pdo) {
        return $datos;
    }

    $inicio = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
    $fin = (new DateTimeImmutable($inicio))->modify('+1 month')->format('Y-m-d 00:00:00');

    $totales = static function (PDO $pdo, string $desde, string $hasta): array {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS facturas,
                    COALESCE(SUM(total_general), 0) AS total,
                    COALESCE(SUM(CASE WHEN estado = 'PAGADA'
                                       OR (estado = 'CERRADA' AND fecha_pago IS NOT NULL AND fecha_pago <> '')
                                      THEN total_general ELSE 0 END), 0) AS cobrado
               FROM tbl_fact
              WHERE fecha_emision >= ? AND fecha_emision < ?
                AND estado <> 'ANULADA'"
        );
        $st->execute([$desde, $hasta]);
        return $st->fetch() ?: [];
    };

    try {
        $fila = $totales($pdo, $inicio, $fin);
    } catch (Throwable $e) {
        return $datos;
    }

    // Si el mes pasado no dejó facturas se usa el último mes con movimiento y
    // se avisa en la etiqueta, igual que nóminas hace con el último cierre.
    if ((int)($fila['facturas'] ?? 0) === 0) {
        try {
            $ultimo = $pdo->query(
                "SELECT DATE_FORMAT(fecha_emision, '%Y-%m') AS ym
                   FROM tbl_fact
                  WHERE estado <> 'ANULADA'
                  ORDER BY fecha_emision DESC
                  LIMIT 1"
            )->fetchColumn();

            if ($ultimo) {
                [$anioUlt, $mesUlt] = array_map('intval', explode('-', (string)$ultimo));
                if ($anioUlt !== $anio || $mesUlt !== $mes) {
                    $anio = $anioUlt;
                    $mes = $mesUlt;
                    $inicio = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
                    $fin = (new DateTimeImmutable($inicio))->modify('+1 month')->format('Y-m-d 00:00:00');
                    $datos['anio'] = $anio;
                    $datos['mes'] = $mes;
                    $datos['etiqueta'] = landing_mes_nombre($mes) . ' ' . $anio;
                    $datos['mes_corto'] = substr(landing_mes_nombre($mes), 0, 3);
                    $datos['exacto'] = false;
                    $fila = $totales($pdo, $inicio, $fin);
                }
            }
        } catch (Throwable $e) {
        }
    }

    $datos['total'] = (float)($fila['total'] ?? 0);
    $datos['facturas'] = (int)($fila['facturas'] ?? 0);
    $datos['cobrado'] = (float)($fila['cobrado'] ?? 0);
    $datos['ok'] = true;

    try {
        $st = $pdo->prepare(
            "SELECT f.no_fact, f.fecha_emision, f.estado, f.total_general, c.nombre AS cliente
               FROM tbl_fact f
               LEFT JOIN clasif_clientes c ON c.id = f.cliente_id
              ORDER BY f.fecha_emision DESC, f.id DESC
              LIMIT 1"
        );
        $st->execute();
        $datos['ultima'] = $st->fetch() ?: null;
    } catch (Throwable $e) {
    }

    $desdeSerie = (new DateTimeImmutable($inicio))->modify('-7 months');
    $porMes = [];
    try {
        $st = $pdo->prepare(
            "SELECT DATE_FORMAT(fecha_emision, '%Y-%m') AS ym,
                    COALESCE(SUM(total_general), 0) AS total
               FROM tbl_fact
              WHERE fecha_emision >= ? AND fecha_emision < ?
                AND estado <> 'ANULADA'
              GROUP BY ym"
        );
        $st->execute([$desdeSerie->format('Y-m-01 00:00:00'), $fin]);
        foreach ($st->fetchAll() as $f) {
            $porMes[$f['ym']] = (float)$f['total'];
        }
    } catch (Throwable $e) {
    }

    for ($i = 0; $i < 8; $i++) {
        $m = $desdeSerie->modify('+' . $i . ' months');
        $clave = $m->format('Y-m');
        $datos['serie'][] = [
            'clave' => $clave,
            'etiqueta' => substr(landing_mes_nombre((int)$m->format('n')), 0, 3),
            'total' => $porMes[$clave] ?? 0.0,
        ];
    }

    // El penúltimo punto de la serie es el mes anterior al consultado.
    $anterior = $datos['serie'][6]['total'] ?? null;
    if ($anterior !== null && $anterior > 0) {
        $datos['variacion'] = round((($datos['total'] - $anterior) / $anterior) * 100, 1);
        $datos['mes_anterior'] = $datos['serie'][6]['etiqueta'];
    }

    return $datos;
}

/** Nómina del mes indicado; si aún no hay cierre, el último periodo cerrado. */
function landing_nominas(?PDO $pdo, int $anio, int $mes): array
{
    $datos = [
        'ok' => false, 'fuente' => 'vivo',
        'anio' => $anio, 'mes' => $mes,
        'etiqueta' => landing_mes_nombre($mes) . ' ' . $anio,
        'mes_corto' => substr(landing_mes_nombre($mes), 0, 3),
        'bruto' => 0.0, 'deducciones' => 0.0, 'neto' => 0.0,
        'trabajadores' => 0, 'exacto' => true,
        'generado' => date('Y-m-d H:i'),
    ];

    if (!$pdo) {
        return $datos;
    }

    $sql = "SELECT periodo_anio, periodo_mes, total_devengado, total_deducciones,
                   total_neto, total_trabajadores, fecha_cierre
              FROM cierres_periodo_nomina
             WHERE tipo = 1 AND estado = 'cerrado'";

    try {
        $st = $pdo->prepare($sql . ' AND periodo_anio = ? AND periodo_mes = ? LIMIT 1');
        $st->execute([$anio, $mes]);
        $fila = $st->fetch();

        if (!$fila) {
            $st = $pdo->query($sql . ' ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1');
            $fila = $st->fetch();
            $datos['exacto'] = false;
        }
    } catch (Throwable $e) {
        return $datos;
    }

    if (!$fila) {
        return $datos;
    }

    $datos['anio'] = (int)$fila['periodo_anio'];
    $datos['mes'] = (int)$fila['periodo_mes'];
    $datos['etiqueta'] = landing_mes_nombre($datos['mes']) . ' ' . $datos['anio'];
    $datos['mes_corto'] = substr(landing_mes_nombre($datos['mes']), 0, 3);
    $datos['bruto'] = (float)$fila['total_devengado'];
    $datos['deducciones'] = (float)$fila['total_deducciones'];
    $datos['neto'] = $fila['total_neto'] !== null
        ? (float)$fila['total_neto']
        : max(0, $datos['bruto'] - $datos['deducciones']);
    $datos['trabajadores'] = (int)$fila['total_trabajadores'];
    $datos['ok'] = true;

    return $datos;
}

$ref = new DateTimeImmutable('first day of last month');
$refAnio = (int)$ref->format('Y');
$refMes = (int)$ref->format('n');

$pdoFacturacion = landing_bd(landing_bd_config(__DIR__ . '/servicios/facturacion/config.php'));
$pdoNominas = landing_bd(landing_bd_config(__DIR__ . '/servicios/nominas/config.php'));

$snapshot = landing_snapshot_leer();

$facturacion = landing_facturacion($pdoFacturacion, $refAnio, $refMes);
$nominas = landing_nominas($pdoNominas, $refAnio, $refMes);

$factVivo = $facturacion['ok'];
$nomVivo = $nominas['ok'];

if (!$factVivo && !empty($snapshot['facturacion']['ok'])) {
    $facturacion = $snapshot['facturacion'];
    $facturacion['fuente'] = 'guardado';
}
if (!$nomVivo && !empty($snapshot['nominas']['ok'])) {
    $nominas = $snapshot['nominas'];
    $nominas['fuente'] = 'guardado';
}

// La caché solo se reescribe con lecturas en vivo: un dato guardado nunca se
// presenta como actual ni renueva su fecha.
if ($factVivo || $nomVivo) {
    landing_snapshot_guardar([
        'guardado' => date('Y-m-d H:i'),
        'facturacion' => $factVivo ? $facturacion : ($snapshot['facturacion'] ?? null),
        'nominas' => $nomVivo ? $nominas : ($snapshot['nominas'] ?? null),
    ]);
}

$bdEstado = ($factVivo && $nomVivo)
    ? 'online'
    : (($factVivo || $nomVivo) ? 'parcial' : 'offline');
$desdeGuardado = ($facturacion['fuente'] === 'guardado' || $nominas['fuente'] === 'guardado');
$faltante = !$facturacion['ok'] ? 'facturación' : (!$nominas['ok'] ? 'nóminas' : '');
$datosGuardados = $snapshot['guardado'] ?? null;

$serieMax = 0.0;
foreach ($facturacion['serie'] as $punto) {
    $serieMax = max($serieMax, (float)$punto['total']);
}

$anio = (int)date('Y');
$base = '/servicios';

/* Estado de la licencia local.
 * Se reutiliza el mismo módulo que emplea /servicios/nominas/licencia.php
 * (sin base de datos) para ofrecer la renovación desde la propia portada. */
$licenciaArchivo = __DIR__ . '/servicios/nominas/includes/licencia.php';
$licenciaDatos   = null;
$licenciaActiva  = false;
if (is_file($licenciaArchivo)) {
    require_once $licenciaArchivo;
    $licenciaDatos  = licencia_leer();
    $licenciaActiva = licencia_activada();
}
$licenciaTitulo = $licenciaActiva
    ? 'Licencia activa hasta ' . licencia_texto_vencimiento($licenciaDatos) . '. Puedes revisarla o renovarla.'
    : 'Activa o renueva la licencia del sistema.';

/* ============================================================================
 * Activación / renovación de licencia en la propia portada.
 * El formulario se envía a este mismo archivo: no hace falta salir a
 * /servicios/nominas/licencia.php para registrar o renovar la licencia.
 * ======================================================================== */
$licForm = array(
    'nombre'  => ($licenciaDatos !== null) ? (string)$licenciaDatos['registro'] : '',
    'usuario' => ($licenciaDatos !== null) ? (string)$licenciaDatos['usuario']  : '',
    'serial'  => '',
    'texto'   => '',
);
$licError        = '';
$licAviso        = '';
$licAvisoTipo    = '';
$licOk           = false;
$licOkTipo       = '';
$licOkHasta      = '';
$licModalAbierto = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['licencia_accion']) && function_exists('licencia_guardar')) {
    $licAccion       = (string)$_POST['licencia_accion'];
    $licModalAbierto = true;
    $licForm['nombre']  = isset($_POST['nombre'])  ? trim((string)$_POST['nombre'])  : $licForm['nombre'];
    $licForm['usuario'] = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : $licForm['usuario'];
    $licForm['serial']  = isset($_POST['serial'])  ? trim((string)$_POST['serial'])  : '';
    $licForm['texto']   = isset($_POST['licencia_texto']) ? (string)$_POST['licencia_texto'] : '';

    if ($licAccion === 'examinar_texto') {
        // Verifica el texto pegado: si es válido rellena los campos.
        $verif = licencia_texto_verificar($licForm['texto']);
        if ($verif['ok']) {
            $licForm['nombre']  = $verif['datos']['registro'];
            $licForm['usuario'] = $verif['datos']['usuario'];
            $licForm['serial']  = $verif['datos']['serial'];
            $licAvisoTipo = 'ok';
            $licAviso = 'Licencia válida para este equipo (' . $verif['datos']['termino'] . '). Revise los datos y pulse «Activar licencia».';
        } else {
            $licAvisoTipo = 'error';
            $licAviso = $verif['mensaje'];
            if ($verif['huella_destino'] !== '') {
                $licAviso .= ' Huella destino: ' . $verif['huella_destino']
                    . ' · Este equipo: ' . $verif['huella_equipo'] . '.';
            }
        }
    } elseif ($licAccion === 'examinar_archivo') {
        // Importa los datos de un archivo .lic, sin instalarlo todavía.
        $licAvisoTipo = 'error';
        if (empty($_FILES['archivo_lic']['tmp_name'])
            || (($_FILES['archivo_lic']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
            $licAviso = 'Debe seleccionar un archivo .lic válido para importar.';
        } elseif (strtolower(pathinfo($_FILES['archivo_lic']['name'], PATHINFO_EXTENSION)) !== 'lic') {
            $licAviso = 'El archivo debe tener extensión .lic.';
        } else {
            $impDatos = licencia_leer_archivo_lic($_FILES['archivo_lic']['tmp_name']);
            if ($impDatos === null) {
                $licAviso = 'El archivo .lic no es válido o su llave no corresponde.';
            } elseif ($impDatos['huella'] !== ''
                && !hash_equals($impDatos['huella'], licencia_fingerprint_machine())) {
                $licAviso = 'Esta licencia fue emitida para otro equipo. Huella destino: '
                    . licencia_formatear_fingerprint($impDatos['huella'])
                    . ' · Este equipo: '
                    . licencia_formatear_fingerprint(licencia_fingerprint_machine()) . '.';
            } else {
                $licForm['nombre']  = $impDatos['registro'];
                $licForm['usuario'] = $impDatos['usuario'];
                $licForm['serial']  = licencia_formatear_serial($impDatos['serial']);
                $licAvisoTipo = 'ok';
                $licAviso = 'Archivo .lic válido ('
                    . (isset($impDatos['info']['nombre']) ? $impDatos['info']['nombre'] : 'Permanente')
                    . '). Revise los datos y pulse «Activar licencia».';
            }
        }
    } else {
        // Activación o renovación manual con la llave serial.
        if ($licForm['nombre'] === '') {
            $licError = 'Debe indicar el <b>Nombre de Registro</b>.';
        } elseif ($licForm['usuario'] === '') {
            $licError = 'Debe indicar el <b>Usuario del Registro</b>.';
        } elseif (preg_match('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){4}$/', strtoupper($licForm['serial'])) !== 1) {
            $licError = 'La <b>Llave Serial</b> no tiene el formato válido (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX).';
        } elseif (!licencia_validar_serial($licForm['nombre'], $licForm['usuario'], $licForm['serial'])) {
            $licError = 'La <b>Llave Serial</b> no corresponde a los datos de registro introducidos.';
        } else {
            // Si ya había una licencia vigente, se reemplaza (renovación).
            $guardado = licencia_guardar($licForm['nombre'], $licForm['usuario'], $licForm['serial'], null, $licenciaActiva);
            if ($guardado === false) {
                $licError = 'No se pudo guardar la licencia en este equipo. Compruebe que la aplicación tiene permisos de escritura.';
            } else {
                $licOk      = true;
                $licOkTipo  = isset($guardado['info']['nombre']) ? $guardado['info']['nombre'] : 'Permanente';
                $licOkHasta = licencia_texto_vencimiento($guardado, 'Sin vencimiento (Permanente)');
                $licenciaDatos  = $guardado;
                $licenciaActiva = licencia_activada();
                $licForm['serial'] = '';
            }
        }
    }
}

if ($licOk) {
    $licenciaEstadoTxt = 'Licencia registrada correctamente en este equipo.';
} elseif ($licenciaActiva && function_exists('licencia_texto_vencimiento')) {
    $licenciaEstadoTxt = 'Licencia vigente hasta ' . licencia_texto_vencimiento($licenciaDatos)
        . '. Puede introducir una nueva llave para renovarla.';
} elseif ($licenciaDatos !== null) {
    $licenciaEstadoTxt = 'La licencia guardada no está vigente en este equipo. Active una nueva llave para continuar.';
} else {
    $licenciaEstadoTxt = 'Este equipo aún no tiene licencia. Introduzca la llave serial para activarla.';
}

// La intro de la portada no se repite cuando la página se recarga para
// tramitar la licencia: el usuario debe ver el modal sin esperar la animación.
$introSaltar = $licModalAbierto;

/* Resumen del estado de la licencia para el panel «Estado de la licencia». */
if ($licenciaActiva) {
    $licEdoNombre = 'Activa';
    $licEdoTono   = 'is-ok';
    $licEdoIcono  = 'fa-circle-check';
} elseif ($licenciaDatos !== null) {
    $licEdoNombre = 'Vencida';
    $licEdoTono   = 'is-warn';
    $licEdoIcono  = 'fa-hourglass-end';
} elseif (function_exists('licencia_tiene_valor_guardado') && licencia_tiene_valor_guardado()) {
    $licEdoNombre = 'No válida';
    $licEdoTono   = 'is-warn';
    $licEdoIcono  = 'fa-triangle-exclamation';
} else {
    $licEdoNombre = 'Sin activar';
    $licEdoTono   = 'is-off';
    $licEdoIcono  = 'fa-circle-minus';
}
$licEdoRegistro = ($licenciaDatos !== null) ? (string)$licenciaDatos['registro'] : '';
$licEdoUsuario  = ($licenciaDatos !== null) ? (string)$licenciaDatos['usuario']  : '';
$licEdoTipo     = ($licenciaDatos !== null && isset($licenciaDatos['info']['nombre'])) ? (string)$licenciaDatos['info']['nombre'] : '';
$licEdoActivada = ($licenciaDatos !== null && !empty($licenciaDatos['fecha_activacion']))
    ? date('d/m/Y', (int)$licenciaDatos['fecha_activacion']) : '';
$licEdoVence    = ($licenciaDatos !== null) ? licencia_texto_vencimiento($licenciaDatos) : '';

$ecosistema = [
    [
        'titulo'  => 'Ecosistema Financiero',
        'texto'   => 'Facturación, nóminas, costos e inventarios en un solo lugar: entra al sistema completo y elige el módulo que necesitas.',
        'icono'   => 'fa-solid fa-wallet',
        'url'     => $base . '/index.php',
        'etiqueta'=> 'Portada del sistema',
        'destacada' => true,
    ],
    [
        'titulo'  => 'Gestión de Nóminas',
        'texto'   => 'Cálculo de nóminas, cierres mensuales, vacaciones, contribuciones y exportaciones bancarias.',
        'icono'   => 'fa-solid fa-money-check-dollar',
        'url'     => $base . '/nominas/',
        'etiqueta'=> 'Módulo de nóminas',
        'imagen'  => $base . '/images/nominas.png',
    ],
    [
        'titulo'  => 'Facturación Electrónica',
        'texto'   => 'Emisión de facturas, clientes, servicios y reportes de cierre con control de usuarios.',
        'icono'   => 'fa-solid fa-file-invoice-dollar',
        'url'     => $base . '/facturacion/index.php',
        'etiqueta'=> 'Módulo de facturación',
        'imagen'  => $base . '/images/facturacion.png',
    ],
    [
        'titulo'  => 'Gestión de Costos',
        'texto'   => 'Fichas de costo, gastos operativos, márgenes y proyecciones para controlar la rentabilidad.',
        'icono'   => 'fa-solid fa-calculator',
        'url'     => $base . '/costos/login.php',
        'etiqueta'=> 'Módulo de costos',
        'imagen'  => $base . '/images/costos.png',
        'pendiente' => true,
    ],
    [
        'titulo'  => 'Inventarios',
        'texto'   => 'Control de stock, entradas y salidas, alertas de inventario mínimo y gestión de proveedores.',
        'icono'   => 'fa-solid fa-boxes-stacked',
        'url'     => $base . '/inventarios/login.php',
        'etiqueta'=> 'Módulo de inventarios',
        'imagen'  => $base . '/images/inventario.png',
        'pendiente' => true,
    ],
    [
        'titulo'  => 'Administración',
        'texto'   => 'Usuarios, roles, parámetros globales, auditoría y configuración del sistema en un solo panel.',
        'icono'   => 'fa-solid fa-user-shield',
        'url'     => $base . '/administracion/index.php',
        'etiqueta'=> 'Módulo de administración',
        'imagen'  => $base . '/images/admin.png',
        'pendiente' => true,
    ],
    [
        'titulo'  => 'Panel del Servidor',
        'texto'   => 'Estado de Apache, PHP y MySQL: versión, uptime real del motor y lanzadores de herramientas.',
        'icono'   => 'fa-solid fa-server',
        'url'     => $base . '/phpinfo/',
        'etiqueta'=> 'Información del servidor',
    ],
    [
        'titulo'  => 'Instalador de Base de Datos',
        'texto'   => 'Asistente para crear o restaurar la base de datos del sistema cuando se prepara un equipo nuevo.',
        'icono'   => 'fa-solid fa-database',
        'url'     => $base . '/InstalarBD/InstalarBD.php',
        'etiqueta'=> 'Preparar el sistema',
    ],
    [
        'titulo'  => 'Soporte y Contacto',
        'texto'   => 'Documentación de soporte, contacto directo y canales de atención para la operación diaria.',
        'icono'   => 'fa-solid fa-headset',
        'url'     => $base . '/soporte.php',
        'etiqueta'=> 'Ayuda y contacto',
    ],
];

$servicios = [
    ['icono' => 'fa-solid fa-database',      'titulo' => 'Base de datos',   'texto' => 'Motor MySQL con integridad referencial, respaldo periódico y restauración confiable.'],
    ['icono' => 'fa-brands fa-php',          'titulo' => 'PHP 8.1',         'texto' => 'Compatibilidad con el stack local y rendimiento estable para todos los módulos.'],
    ['icono' => 'fa-solid fa-shield-halved', 'titulo' => 'Seguridad',       'texto' => 'Comunicación cifrada, autenticación reforzada y secretos fuera del código.'],
    ['icono' => 'fa-solid fa-arrows-rotate', 'titulo' => 'Sincronización',  'texto' => 'Información siempre al día entre módulos, sin cálculos repetidos ni desfases.'],
    ['icono' => 'fa-solid fa-clock',         'titulo' => 'Soporte 24/7',    'texto' => 'Acompañamiento continuo para la operación y resolución de incidencias.'],
    ['icono' => 'fa-solid fa-lock',          'titulo' => 'Licencia local',  'texto' => 'Todo funciona en el equipo del cliente: los datos no salen de la red local.'],
];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Entorno de Aplicaciones Web de UnicornioSoftware® Cuba. Kaky®</title>
<meta name="description" content="Entorno de Aplicaciones Web de UnicornioSoftware® Cuba. Kaky®. Facturación, nóminas y gestión en una plataforma local.">
<link rel="icon" type="image/png" href="/images/logo.png">
<link rel="apple-touch-icon" href="/images/logo.png">
<link rel="stylesheet" href="<?= $base ?>/css/font-awesome6.4.0/css/all.min.css">
<!-- Tipografías del hero destacado (Bebas Neue para el titular, Exo 2 para cejilla,
     botones y etiquetas). Si se prefiere no depender de Google, se pueden alojar en local. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Exo+2:wght@600;700;800&display=swap">
<script>
    /* Aplica el tema guardado antes de pintar para que no haya parpadeo. */
    (function () {
        try {
            var tema = localStorage.getItem('theme');
            document.documentElement.setAttribute('data-bs-theme', tema === 'light' ? 'light' : 'dark');
        } catch (e) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    })();
</script>
<style>
    /* ===== Tokens del tema (NeuroAI), oscuro por defecto ===== */
    :root {
        --bg:            #070a12;
        --bg-soft:       #0b1020;
        --surface:       rgba(18, 26, 45, .72);
        --surface-strong:rgba(24, 33, 56, .82);
        --surface-solid: #121a2d;
        --tile:          rgba(255, 255, 255, .05);
        --panel:         rgba(11, 16, 32, .82);
        --header-bg:     rgba(7, 10, 18, .78);
        --footer-bg:     rgba(9, 13, 24, .74);
        --overlay-top:   rgba(7, 10, 18, .32);
        --overlay-bottom:rgba(7, 10, 18, .78);
        --fondo-imagen:  url("/images/fondo.jpg");
        --fondo-opacidad: 1.55;
        --shine:         rgba(255, 255, 255, .10);
        --card-hover-border: rgba(255, 255, 255, .28);
        --card-hover-shadow: 0 0 0 1px rgba(36, 214, 255, .12), 0 1.25rem 4rem rgba(109, 93, 252, .30);
        --card-icon-color: #cfd6ff;
        --card-top:      rgba(255, 255, 255, .05);
        --card-bottom:   rgba(255, 255, 255, .015);
        --destacada-top: rgba(109, 93, 252, .16);
        --destacada-bottom: rgba(18, 26, 45, .72);
        --border:        rgba(255, 255, 255, 0.12);
        --border-strong: rgba(255, 255, 255, 0.18);
        --txt:           #f7f9ff;
        --muted:         #a8b3c7;
        --subtle:        #7f8ba3;
        --accent:        #6d5dfc;
        --accent-soft:   rgba(109, 93, 252, 0.18);
        --cyan:          #24d6ff;
        --mint:          #2ee59d;
        --radius:        .75rem;
        --radius-lg:     1rem;
        --shadow:        0 1.5rem 4rem rgba(0, 0, 0, .35);
        --shadow-glow:   0 0 0 1px rgba(36, 214, 255, .08), 0 1.25rem 4rem rgba(109, 93, 252, .22);
        --switch-bg:     rgba(255, 255, 255, .08);
        --switch-border: rgba(255, 255, 255, .18);
        --switch-thumb:  #232c38;
        --switch-thumb-border: rgba(255, 255, 255, .22);
        --switch-sun:    #8b9bb4;
        --switch-moon:   #24d6ff;
        --window-bg:     linear-gradient(180deg, rgba(26, 35, 58, .96), rgba(9, 13, 24, .97));
        --window-line:   rgba(255, 255, 255, .08);
        --chart-track:   rgba(255, 255, 255, .06);
        --ded:           #ff8a76;
        --danger:        #f87171;
        --danger-txt:    #ffe1e1;
        --danger-soft:   rgba(248, 113, 113, .12);
        --danger-border: rgba(248, 113, 113, .34);
        --warn:          #fbbf24;
        --max:           72rem;
    }
    /* ===== Tema claro (mismo mecanismo que el panel phpinfo) ===== */
    [data-bs-theme="light"] {
        --bg:            #eef2fa;
        --bg-soft:       #f7f9fd;
        --surface:       rgba(255, 255, 255, .84);
        --surface-strong:rgba(255, 255, 255, .96);
        --surface-solid: #ffffff;
        --tile:          rgba(15, 23, 42, .04);
        --panel:         rgba(255, 255, 255, .76);
        --header-bg:     rgba(255, 255, 255, .86);
        --footer-bg:     rgba(255, 255, 255, .84);
        --overlay-top:   rgba(238, 242, 250, .55);
        --overlay-bottom:rgba(238, 242, 250, .92);
        --fondo-imagen:  url("/images/fondo-dia.jpg");
        --fondo-opacidad: 1.55;
        --shine:         rgba(99, 90, 230, .26);
        --card-hover-border: rgba(91, 75, 219, .5);
        --card-hover-shadow: 0 0 0 1px rgba(14, 116, 144, .2), 0 1.1rem 2.8rem rgba(91, 75, 219, .30);
        --card-icon-color: #4c3fd0;
        --card-top:      rgba(255, 255, 255, .92);
        --card-bottom:   rgba(255, 255, 255, .55);
        --destacada-top: rgba(109, 93, 252, .12);
        --destacada-bottom: rgba(255, 255, 255, .86);
        --border:        rgba(15, 23, 42, 0.12);
        --border-strong: rgba(15, 23, 42, 0.20);
        --txt:           #101828;
        --muted:         #475467;
        --subtle:        #667085;
        --accent:        #5b4bdb;
        --accent-soft:   rgba(91, 75, 219, 0.12);
        --cyan:          #0e7490;
        --mint:          #047857;
        --shadow:        0 1.5rem 4rem rgba(16, 24, 40, .12);
        --shadow-glow:   0 0 0 1px rgba(14, 116, 144, .10), 0 1.25rem 4rem rgba(91, 75, 219, .16);
        --switch-bg:     #e2e8f2;
        --switch-border: #cfd7e4;
        --switch-thumb:  #ffffff;
        --switch-thumb-border: #cfd7e4;
        --switch-sun:    #f59e0b;
        --switch-moon:   #94a3b8;
        --window-bg:     linear-gradient(180deg, #ffffff, #f1f5fd);
        --window-line:   rgba(15, 23, 42, .09);
        --chart-track:   rgba(15, 23, 42, .06);
        --ded:           #c2410c;
        --danger:        #b42318;
        --danger-txt:    #7a271a;
        --danger-soft:   rgba(180, 35, 24, .08);
        --danger-border: rgba(180, 35, 24, .30);
        --warn:          #a15c00;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    /* Que el navbar fijo no tape el título al saltar a una sección. */
    section[id] { scroll-margin-top: 4.75rem; }
    body {
        margin: 0;
        background: var(--bg);
        color: var(--txt);
        font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }
    /* Fondo fotográfico (imagen descargada en /images/fondo.jpg) + capa de
     * oscurecido y resplandores del tema para no perder legibilidad. */
    body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -2;
        background: var(--fondo-imagen) center center / cover no-repeat;
        opacity: var(--fondo-opacidad);
        pointer-events: none;
        transition: opacity .3s ease;
    }
    body::after {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        background:
            radial-gradient(60rem 40rem at 15% -5%, rgba(36, 214, 255, .12), transparent 60%),
            radial-gradient(55rem 38rem at 88% 0%, rgba(109, 93, 252, .18), transparent 60%),
            linear-gradient(180deg, var(--overlay-top) 0%, var(--overlay-bottom) 85%);
        pointer-events: none;
    }
    a { color: inherit; }
    img { max-width: 100%; display: block; }
    .skip {
        position: absolute; top: .75rem; left: .75rem;
        padding: .6rem .9rem; border-radius: .5rem;
        background: var(--accent); color: #fff;
        transform: translateY(-160%); transition: transform .2s;
        z-index: 100;
    }
    .skip:focus { transform: translateY(0); }

    .wrap { width: min(100% - 2.5rem, var(--max)); margin-inline: auto; }
    .section { padding-block: clamp(3.5rem, 7vw, 6rem); }
    .stack > * + * { margin-top: 1rem; }

    /* ===== Barra superior ===== */
    .site-header {
        position: sticky; top: 0; z-index: 50;
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        background: var(--header-bg);
        border-bottom: 1px solid var(--border);
    }
    .nav {
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; min-height: 4.25rem;
    }
    .brand { display: inline-flex; align-items: center; gap: .7rem; text-decoration: none; font-weight: 800; min-width: 0; }
    .brand-logo {
        width: 2.9rem; height: 2.9rem; flex: 0 0 auto;
        object-fit: contain;
        padding: .18rem;
        border-radius: .7rem;
        background: var(--tile);
        border: 1px solid var(--border);
    }
    .brand-name { font-size: clamp(.76rem, 1.5vw, .92rem); line-height: 1.25; letter-spacing: -.01em; }
    .brand-name small {
        display: block;
        font-size: clamp(.55rem, 1vw, .63rem);
        font-weight: 600; letter-spacing: .06em;
        color: var(--subtle);
    }
    .nav-links { display: none; align-items: center; gap: .25rem; }
    .nav-links a {
        display: inline-flex; align-items: center; gap: .42rem;
        padding: .46rem .7rem; border-radius: .6rem;
        text-decoration: none; color: var(--muted); font-size: .9rem; font-weight: 500;
        transition: color .15s, background-color .15s;
    }
    .nav-links a i { font-size: .8rem; color: var(--subtle); transition: color .15s; }
    .nav-links a:hover, .nav-links a:focus-visible { color: var(--txt); background: var(--tile); }
    .nav-links a:hover i, .nav-links a:focus-visible i { color: var(--cyan); }

    /* ===== Botones ===== */
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .7rem 1.15rem; border-radius: .65rem;
        border: 1px solid transparent; cursor: pointer;
        font: inherit; font-weight: 700; font-size: .92rem;
        text-decoration: none; white-space: nowrap;
        transition: transform .15s, background .15s, border-color .15s, box-shadow .15s;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
        color: #fff;
        background: linear-gradient(135deg, var(--accent) 0%, #5d4fd6 100%);
        box-shadow: 0 .6rem 1.6rem rgba(109, 93, 252, .35);
    }
    .btn-primary:hover, .btn-primary:focus-visible {
        transform: translateY(-1px);
        box-shadow: 0 0 0 1px rgba(36, 214, 255, .08), 0 1.25rem 4rem rgba(109, 93, 252, .22);
    }
    .btn-glass {
        color: var(--txt); background: var(--surface);
        border-color: var(--border-strong);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
    }
    .btn-glass:hover { border-color: var(--border-strong); background: var(--surface-strong); }
    .btn-sm { padding: .55rem .9rem; font-size: .82rem; }

    /* ===== Hero (texto + ventana de producto) ===== */
    .hero { padding-block: clamp(3.25rem, 7vw, 5.5rem) clamp(2.25rem, 4vw, 3.25rem); }
    .hero-grid {
        display: grid; gap: clamp(2.25rem, 4vw, 3.5rem);
        grid-template-columns: minmax(0, 1fr);
        align-items: center;
    }
    .hero-copy { min-width: 0; }
    .badge {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .38rem .8rem; border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--cyan) 40%, transparent);
        background: color-mix(in srgb, var(--cyan) 10%, transparent);
        color: var(--txt); font-size: .76rem; font-weight: 700;
        letter-spacing: .02em;
    }
    .badge .dot {
        width: .45rem; height: .45rem; border-radius: 50%;
        background: var(--mint); box-shadow: 0 0 0 .25rem rgba(46, 229, 157, .18);
    }
    h1 {
        margin: 1.1rem 0 0;
        font-size: clamp(2.2rem, 5.4vw, 3.5rem);
        line-height: 1.05; letter-spacing: -.035em; font-weight: 800;
        max-width: 17ch;
        text-wrap: balance;
    }
    .text-gradient {
        background: linear-gradient(135deg, var(--accent) 0%, var(--cyan) 100%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
    }
    .lead { margin-top: 1.15rem; max-width: 52ch; color: var(--muted); font-size: clamp(1rem, 1.5vw, 1.1rem); }
    .hero-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.85rem; }
    /* Tres accesos del hero en una sola fila: columnas iguales que encogen y
     * dejan envolver la etiqueta antes que saltar de línea el botón completo. */
    .hero-actions.hero-actions-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .6rem;
    }
    .hero-actions-3 .btn {
        width: 100%; min-width: 0;
        padding: .72rem .55rem;
        font-size: .84rem; line-height: 1.25;
        white-space: normal; text-align: center;
    }
    .hero-actions-3 .btn i { flex: 0 0 auto; }
    @media (max-width: 30rem) {
        .hero-actions.hero-actions-3 { grid-template-columns: 1fr; }
    }

    /* ===== Hero: titular y etiquetas (tipografía estilo cyberforja) ===== */
    .hero-eyebrow {
        display: block; margin-top: 1.05rem;
        font-family: "Exo 2", "Inter", "Segoe UI", system-ui, sans-serif;
        font-size: .76rem; font-weight: 800;
        letter-spacing: .18em; text-transform: uppercase; color: var(--cyan);
    }
    .hero-eyebrow span { color: var(--subtle); font-weight: 600; }
    .hero-titulo {
        margin: .55rem 0 0; max-width: 40rem;
        font-family: "Bebas Neue", "Oswald", Impact, "Arial Narrow", sans-serif;
        font-size: clamp(2.4rem, 5.6vw, 3.9rem);
        line-height: .95; letter-spacing: .015em; font-weight: 400;
        color: var(--txt);
    }
    .hero-titulo span {
        color: var(--cyan);
        filter: drop-shadow(0 0 14px rgba(36, 214, 255, .28));
    }
    .hero-tags {
        display: flex; flex-wrap: wrap; align-items: center; gap: .45rem .85rem;
        margin-top: 1.2rem;
        font-family: "Exo 2", "Inter", "Segoe UI", system-ui, sans-serif;
        font-size: .7rem; font-weight: 700;
        letter-spacing: .1em; text-transform: uppercase; color: var(--muted);
    }
    .hero-tags span { display: inline-flex; align-items: center; gap: .4rem; }
    .hero-tags i { color: var(--cyan); font-size: .68rem; }
    .hero-tags .sep { color: var(--border-strong); }
    .hero-note {
        display: flex; align-items: center; gap: .45rem;
        margin-top: 1.25rem; color: var(--subtle); font-size: .8rem;
    }
    .hero-note i { color: var(--mint); }
    /* Aviso de renovación / activación de licencia, destacado en ámbar
     * para diferenciarlo de la nota mint del hero. */
    .hero-note-licencia { margin-top: .5rem; color: var(--warn); }
    .hero-note-licencia i { color: var(--warn); }
    .hero-note-licencia a {
        color: inherit; text-decoration: none;
        border-bottom: 1px dashed currentColor;
    }
    .hero-note-licencia a:hover { filter: brightness(1.15); border-bottom-color: currentColor; }

    /* ===== Panel de activación / renovación de licencia =====
     * Se abre sobre la portada: todo el trámite ocurre en index.php. */
    body.lic-abierto { overflow: hidden; }
    .lic-overlay {
        position: fixed; inset: 0; z-index: 130;
        display: grid; place-items: center;
        padding: 1rem; overflow-y: auto;
        background: rgba(4, 7, 16, .72);
        -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
    }
    .lic-overlay[hidden] { display: none; }
    .lic-modal {
        position: relative; width: min(620px, 100%);
        max-height: 92vh; overflow-y: auto;
        padding: 1.6rem 1.6rem 1.4rem;
        border-radius: 20px;
        border: 1px solid var(--border-strong);
        background: var(--surface-solid);
        color: var(--txt);
        box-shadow: 0 2rem 5rem rgba(0, 0, 0, .55);
    }
    .lic-modal-x {
        position: absolute; top: .85rem; right: .85rem;
        display: grid; place-items: center;
        width: 2rem; height: 2rem; padding: 0;
        border: 0; border-radius: .55rem;
        background: transparent; color: var(--muted);
        font-size: 1rem; cursor: pointer;
    }
    .lic-modal-x:hover { color: var(--txt); background: var(--tile); }
    .lic-modal-head { display: flex; gap: .85rem; align-items: flex-start; padding-right: 2rem; }
    .lic-modal-icon {
        display: grid; place-items: center;
        width: 2.6rem; height: 2.6rem; flex: 0 0 auto;
        border-radius: .9rem; color: #fbbf24;
        background: rgba(251, 191, 36, .12);
        border: 1px solid rgba(251, 191, 36, .28);
    }
    .lic-modal-head h2 { margin: 0; font-size: 1.15rem; line-height: 1.25; }
    .lic-modal-sub { margin: .3rem 0 0; color: var(--muted); font-size: .84rem; line-height: 1.45; }
    .lic-form { display: grid; gap: .9rem; margin-top: 1.15rem; }
    .lic-grid { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    .lic-field { display: grid; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--muted); }
    .lic-field input, .lic-alt textarea {
        width: 100%; padding: .68rem .8rem;
        border-radius: .7rem;
        border: 1px solid var(--border-strong);
        background: var(--tile); color: var(--txt);
        font: inherit; font-size: .9rem; font-weight: 400;
        outline: none;
    }
    .lic-field input:focus, .lic-alt textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 .2rem var(--accent-soft);
    }
    .lic-fp { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .78rem; color: var(--muted); }
    .lic-fp b { color: var(--txt); letter-spacing: .04em; }
    .lic-modal-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: .25rem; }
    .lic-msg {
        display: flex; gap: .6rem; align-items: flex-start;
        margin-top: 1rem; padding: .7rem .85rem;
        border-radius: .8rem; font-size: .84rem; line-height: 1.45;
    }
    .lic-msg-ok { background: rgba(46, 229, 157, .1); border: 1px solid rgba(46, 229, 157, .34); }
    .lic-msg-error { background: var(--danger-soft); border: 1px solid var(--danger-border); color: var(--danger-txt); }
    .lic-msg i { margin-top: .15rem; }
    .lic-msg-ok i { color: var(--mint); }
    .lic-msg-error i { color: var(--danger); }
    .lic-alt { display: grid; gap: .6rem; margin-top: 1.15rem; }
    .lic-alt details {
        padding: .55rem .85rem;
        border: 1px solid var(--border); border-radius: .8rem;
        background: var(--tile);
    }
    .lic-alt summary { cursor: pointer; font-size: .82rem; font-weight: 600; color: var(--muted); }
    .lic-alt details[open] summary { margin-bottom: .65rem; color: var(--txt); }
    .lic-alt form { display: grid; gap: .55rem; }
    .lic-alt input[type="file"] { font-size: .8rem; color: var(--muted); }
    .lic-alt textarea { resize: vertical; min-height: 4.6rem; }
    .lic-ok {
        display: flex; gap: .75rem; align-items: flex-start;
        margin-top: 1.15rem; padding: .9rem 1rem;
        border-radius: .9rem;
        background: rgba(46, 229, 157, .1);
        border: 1px solid rgba(46, 229, 157, .34);
    }
    .lic-ok i { color: var(--mint); font-size: 1.4rem; margin-top: .1rem; }
    .lic-ok strong { display: block; font-size: .95rem; }
    .lic-ok p { margin: .3rem 0 0; color: var(--muted); font-size: .85rem; }

    /* ===== Solicitud de licencia por correo, dentro de la portada =====
     * Mismo formulario y misma lógica que /servicios/nominas/licencia.php. */
    .lic-solicitar { margin-top: 1.15rem; }
    .lic-solicitar .btn { width: 100%; }
    .sol-modal { width: min(760px, 100%); }
    .sol-intro { margin: .95rem 0 0; color: var(--muted); font-size: .84rem; line-height: 1.5; }
    .sol-intro b { color: var(--txt); }
    .sol-form { display: grid; gap: .85rem; margin-top: 1rem; }
    .sol-grid { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .sol-full { grid-column: 1 / -1; }
    .sol-field { display: grid; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--muted); }
    .sol-field input, .sol-field select {
        width: 100%; padding: .68rem .8rem;
        border-radius: .7rem;
        border: 1px solid var(--border-strong);
        background: var(--tile); color: var(--txt);
        font: inherit; font-size: .9rem; font-weight: 400;
        outline: none;
    }
    .sol-field select {
        cursor: pointer;
        background-color: var(--surface-solid);
        color: var(--txt);
    }
    .sol-field select option {
        background-color: var(--surface-solid);
        color: var(--txt);
    }
    .sol-field input:focus, .sol-field select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 .2rem var(--accent-soft);
    }
    .sol-field input:disabled {
        opacity: .8; cursor: not-allowed;
        font-family: ui-monospace, Consolas, monospace; letter-spacing: .06em;
    }
    .sol-field input.error, .sol-field select.error { border-color: var(--danger); }
    .sol-feedback { font-size: .72rem; font-weight: 600; color: var(--muted); }
    .sol-feedback:empty { display: none; }
    .sol-feedback.ok { color: var(--mint); }
    .sol-feedback.mal { color: var(--danger); }
    .sol-huella-fila { display: flex; gap: .5rem; align-items: center; }
    .sol-switch {
        display: inline-flex; align-items: center; gap: .55rem;
        font-size: .82rem; font-weight: 600; color: var(--txt); cursor: pointer;
    }
    .sol-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .sol-switch-track {
        position: relative; flex: 0 0 auto;
        width: 2.6rem; height: 1.45rem; border-radius: 999px;
        border: 1px solid var(--border-strong); background: var(--tile);
        transition: background .18s, border-color .18s;
    }
    .sol-switch-track::after {
        content: ""; position: absolute; top: .18rem; left: .18rem;
        width: 1rem; height: 1rem; border-radius: 50%;
        background: var(--muted); transition: transform .18s, background .18s;
    }
    .sol-switch input:checked + .sol-switch-track {
        background: rgba(46, 229, 157, .18); border-color: var(--mint);
    }
    .sol-switch input:checked + .sol-switch-track::after {
        transform: translateX(1.15rem); background: var(--mint);
    }
    .sol-menu { position: relative; }
    .sol-menu-lista {
        position: absolute; bottom: calc(100% + .4rem); left: 0; z-index: 5;
        display: grid; min-width: 12.5rem; padding: .35rem;
        border-radius: .7rem; border: 1px solid var(--border-strong);
        background: var(--surface-solid);
        box-shadow: 0 .8rem 2rem rgba(0, 0, 0, .45);
    }
    .sol-menu-lista[hidden] { display: none; }
    .sol-menu-lista button {
        display: flex; gap: .5rem; align-items: center;
        padding: .5rem .6rem; border: 0; border-radius: .5rem;
        background: transparent; color: var(--txt);
        font: inherit; font-size: .8rem; text-align: left; cursor: pointer;
    }
    .sol-menu-lista button:hover { background: var(--tile); }
    .sol-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: .3rem; }
    @media (max-width: 34rem) { .sol-actions .btn { width: 100%; } }

    /* ===== Estado de la licencia ===== */
    .lic-estado-badge {
        display: inline-flex; align-items: center; gap: .45rem;
        margin-top: .45rem; padding: .3rem .7rem;
        border-radius: 999px; font-size: .78rem; font-weight: 700;
    }
    .lic-estado-badge.is-ok {
        color: var(--mint);
        background: rgba(46, 229, 157, .12);
        border: 1px solid rgba(46, 229, 157, .34);
    }
    .lic-estado-badge.is-warn {
        color: var(--warn);
        background: rgba(251, 191, 36, .12);
        border: 1px solid rgba(251, 191, 36, .32);
    }
    .lic-estado-badge.is-off {
        color: var(--muted);
        background: var(--tile);
        border: 1px solid var(--border-strong);
    }
    .lic-estado-lista { display: grid; gap: .5rem; margin: 1.1rem 0 0; }
    .lic-estado-fila {
        display: grid; grid-template-columns: minmax(8rem, 36%) 1fr;
        gap: .75rem; align-items: baseline;
        padding: .55rem .8rem; border-radius: .7rem;
        background: var(--tile); border: 1px solid var(--border);
        font-size: .84rem;
    }
    .lic-estado-fila dt { margin: 0; color: var(--muted); font-weight: 600; }
    .lic-estado-fila dd { margin: 0; color: var(--txt); font-weight: 600; word-break: break-word; }
    .lic-estado-fila dd.mono { font-family: ui-monospace, Consolas, monospace; letter-spacing: .04em; }
    @media (max-width: 34rem) {
        .lic-estado-fila { grid-template-columns: 1fr; gap: .2rem; }
    }

    /* Ventana de producto dibujada con CSS: da contexto visual al hero sin
     * depender de capturas ni de imágenes que pesen. */
    .hero-visual { position: relative; min-width: 0; }
    .hero-visual::before {
        content: "";
        position: absolute; inset: 8% 2% -8% 4%;
        background: radial-gradient(60% 60% at 65% 35%, rgba(109, 93, 252, .38), transparent 70%);
        filter: blur(42px);
        z-index: 0; pointer-events: none;
    }
    /* Las tarjetas flotantes se anclan a la ventana, no al bloque completo:
     * así el aviso de conexión (en flujo) no las arrastra sobre el texto. */
    .visual-stage { position: relative; }
    .window {
        position: relative; z-index: 1;
        border-radius: 1.1rem; overflow: hidden;
        background: var(--window-bg);
        border: 1px solid var(--border-strong);
        box-shadow: 0 2rem 5rem rgba(0, 0, 0, .45), 0 0 0 1px rgba(109, 93, 252, .14);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        transform: rotateX(3deg) rotateY(-5deg);
        transition: transform .5s cubic-bezier(.2, .7, .3, 1), box-shadow .5s;
    }
    .hero-visual:hover .window {
        transform: rotateX(0) rotateY(0);
        box-shadow: 0 2.4rem 6rem rgba(0, 0, 0, .5), 0 0 0 1px rgba(36, 214, 255, .22);
    }
    .window-bar {
        display: flex; align-items: center; gap: .55rem;
        padding: .7rem .9rem;
        border-bottom: 1px solid var(--window-line);
        background: var(--tile);
    }
    .window-dots { display: inline-flex; gap: .3rem; }
    .window-dots i {
        display: block; width: .5rem; height: .5rem; border-radius: 50%;
        background: #ff5f57;
    }
    .window-dots i:nth-child(2) { background: #febc2e; }
    .window-dots i:nth-child(3) { background: #28c840; }
    .window-url {
        flex: 1; min-width: 0;
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .22rem .6rem; border-radius: 999px;
        background: var(--chart-track);
        color: var(--subtle); font-size: .64rem;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .window-url i { font-size: .55rem; color: var(--mint); }
    .window-body { display: grid; gap: .85rem; padding: 1rem; }

    .mock-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
    .mock-kpi {
        padding: .65rem .7rem; border-radius: .7rem;
        background: var(--card-top);
        border: 1px solid var(--window-line);
    }
    .mock-kpi small {
        display: flex; align-items: center; gap: .35rem;
        color: var(--subtle); font-size: .58rem; font-weight: 700;
        letter-spacing: .05em; text-transform: uppercase;
    }
    .mock-kpi small i { color: var(--cyan); font-size: .58rem; }
    .mock-kpi b { display: block; margin-top: .22rem; font-size: 1rem; letter-spacing: -.02em; }
    .mock-kpi span { font-size: .6rem; font-weight: 700; color: var(--mint); }

    .mock-chart {
        display: grid; gap: .55rem;
        padding: .8rem .85rem;
        border-radius: .8rem;
        background: var(--card-bottom);
        border: 1px solid var(--window-line);
    }
    .mock-chart-head {
        display: flex; align-items: center; justify-content: space-between;
        font-size: .62rem; color: var(--subtle);
    }
    .mock-chart-head b { color: var(--txt); font-size: .72rem; }
    .mock-bars {
        display: grid; grid-auto-flow: column; grid-auto-columns: 1fr;
        align-items: end; gap: .35rem; height: 5rem;
    }
    .mock-bars span {
        display: block; min-height: 3px; border-radius: .3rem .3rem .1rem .1rem;
        background: linear-gradient(180deg, var(--cyan), var(--accent));
        transform-origin: bottom;
        animation: bar-in .9s cubic-bezier(.2, .8, .25, 1) backwards;
    }
    .mock-bars span:nth-child(1) { animation-delay: .05s; }
    .mock-bars span:nth-child(2) { animation-delay: .1s; }
    .mock-bars span:nth-child(3) { animation-delay: .15s; }
    .mock-bars span:nth-child(4) { animation-delay: .2s; }
    .mock-bars span:nth-child(5) { animation-delay: .25s; }
    .mock-bars span:nth-child(6) { animation-delay: .3s; background: linear-gradient(180deg, var(--mint), var(--cyan)); }
    .mock-bars span:nth-child(7) { animation-delay: .35s; }
    .mock-bars span:nth-child(8) { animation-delay: .4s; }
    /* Arranca ya visible: si el navegador congela la animación (pestaña en
     * segundo plano, captura o impresión) las barras siguen mostrando el dato. */
    @keyframes bar-in {
        from { transform: scaleY(.55); opacity: .55; }
        to   { transform: scaleY(1); opacity: 1; }
    }

    /* Gráfico de ingresos + tarjeta de nómina, uno al lado del otro. */
    .mock-split {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem; align-items: stretch;
    }
    .mock-split > * { min-width: 0; }
    .mock-split .mock-chart { gap: .5rem; padding: .7rem; }
    .mock-split .mock-chart-head { font-size: .58rem; }
    .mock-split .mock-chart-head b { font-size: .66rem; }
    .mock-split .mock-bars { gap: .25rem; height: 4.85rem; }

    .mock-payroll {
        display: grid; gap: .5rem; align-content: space-between;
        padding: .7rem;
        border-radius: .8rem;
        background: var(--card-bottom);
        border: 1px solid var(--window-line);
    }
    .mock-pay-head {
        display: flex; align-items: baseline; justify-content: space-between; gap: .35rem;
        font-size: .6rem; color: var(--subtle);
    }
    .mock-pay-head b { color: var(--txt); font-size: .64rem; font-weight: 700; }
    .mock-pay-bars { display: grid; gap: .6rem; }
    .mock-pay {
        display: grid; grid-template-columns: minmax(0, 1fr) auto;
        align-items: baseline; gap: .22rem .4rem;
        font-size: .56rem; color: var(--subtle);
    }
    .mock-pay b { color: var(--txt); font-size: .64rem; letter-spacing: -.01em; }
    .pay-track {
        grid-column: 1 / -1;
        display: block; height: .34rem; border-radius: 999px;
        background: var(--chart-track); overflow: hidden;
    }
    .pay-fill {
        display: block; height: 100%; border-radius: inherit;
        width: var(--w, 60%);
        transform-origin: left center;
        animation: pay-in .9s cubic-bezier(.2, .8, .25, 1) backwards;
    }
    .mock-pay--bruto .pay-fill { background: linear-gradient(90deg, var(--accent), var(--cyan)); animation-delay: .12s; }
    .mock-pay--ded .pay-fill   { background: var(--ded); animation-delay: .22s; }
    .mock-pay--net .pay-fill   { background: linear-gradient(90deg, var(--cyan), var(--mint)); animation-delay: .32s; }
    @keyframes pay-in {
        from { transform: scaleX(.9); opacity: .4; }
        to   { transform: scaleX(1); opacity: 1; }
    }

    .mock-rows { display: grid; gap: .45rem; }
    .mock-row {
        display: grid; grid-template-columns: 1.6rem minmax(0, 1fr) auto;
        align-items: center; gap: .55rem;
        font-size: .64rem; color: var(--muted);
    }
    .mock-row i {
        display: grid; place-items: center;
        width: 1.6rem; height: 1.6rem; border-radius: .45rem;
        background: var(--accent-soft); color: var(--cyan); font-size: .58rem;
    }
    .mock-row b { color: var(--txt); font-weight: 600; }
    .mock-row em { font-style: normal; font-weight: 700; font-size: .6rem; color: var(--mint); }
    .mock-row em.is-off { color: var(--ded); }

    /* Estados de la tarjeta cuando MySQL no responde. */
    .kpi-unit { font-size: .62em; font-weight: 600; color: var(--subtle); letter-spacing: 0; }
    .mock-kpi span.is-off { color: var(--subtle); }
    .mock-kpi span.is-down { color: var(--ded); }
    .mock-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .4rem; min-height: 4.6rem; padding: .6rem;
        border: 1px dashed var(--window-line); border-radius: .6rem;
        color: var(--subtle); font-size: .58rem; text-align: center; line-height: 1.35;
    }
    .mock-empty i { font-size: .9rem; }
    .mock-pay-foot { font-size: .53rem; color: var(--subtle); }
    .window-status {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .5rem; border-radius: 999px;
        background: var(--chart-track); border: 1px solid var(--window-line);
        color: var(--muted); font-size: .56rem; font-weight: 700;
        white-space: nowrap;
    }
    .window-status i { font-size: .42rem; }
    .window-status.is-on i   { color: var(--mint); }
    .window-status.is-warn i { color: #febc2e; }
    .window-status.is-off i  { color: var(--ded); }
    .alerta-bd {
        position: relative; z-index: 5;
        display: flex; align-items: flex-start; gap: .55rem;
        max-width: 31rem; margin: 2.9rem auto 0;
        padding: .75rem .8rem;
        border-radius: .75rem;
        background: rgba(220, 38, 38, .16);
        border: 1px solid rgba(220, 38, 38, .55);
        color: var(--txt);
        font-size: .72rem; line-height: 1.45;
        box-shadow: 0 .6rem 1.6rem rgba(0, 0, 0, .22);
    }
    .alerta-bd > i { color: var(--ded, #ef4444); flex: 0 0 auto; margin-top: .12rem; }
    .alerta-bd p { margin: 0; flex: 1 1 auto; }
    .alerta-bd b { color: var(--ded, #ef4444); }
    .alerta-bd-cerrar {
        flex: 0 0 auto; display: grid; place-items: center;
        width: 1.45rem; height: 1.45rem; margin: -.1rem -.15rem 0 .1rem;
        border: 0; border-radius: .35rem; background: transparent;
        color: var(--muted); cursor: pointer;
        transition: background .2s, color .2s;
    }
    .alerta-bd-cerrar:hover, .alerta-bd-cerrar:focus-visible {
        background: rgba(220, 38, 38, .28); color: var(--txt);
    }

    .float-card {
        position: absolute; z-index: 2;
        display: inline-flex; align-items: center; gap: .55rem;
        padding: .6rem .8rem; border-radius: .8rem;
        background: var(--surface-strong); border: 1px solid var(--border-strong);
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        font-size: .68rem; color: var(--muted); white-space: nowrap;
        animation: float-y 6s ease-in-out infinite;
    }
    .float-card b { display: block; color: var(--txt); font-size: .76rem; }
    .float-icon {
        display: grid; place-items: center;
        width: 1.9rem; height: 1.9rem; border-radius: .55rem;
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        color: #fff; font-size: .72rem;
    }
    .float-card--a { top: -1.1rem; right: -.75rem; }
    .float-card--b { bottom: -1.2rem; left: -.75rem; animation-delay: -3s; }
    .float-card--c { bottom: -1.2rem; right: -.75rem; animation-delay: -1.5s; }
    .float-card--d { top: -3.4rem; left: -.75rem; animation-delay: -4.5s; }
    .visual-floats { display: contents; }
    @media (max-width: 979.98px) {
        /* En una sola columna las tarjetas dejan de flotar: se leen como
         * cuadrícula bajo la ventana y no tapan el texto ni el marco. */
        .visual-floats {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .5rem; margin-top: 1rem;
        }
        .visual-floats .float-card {
            position: static; inset: auto;
            white-space: normal; animation: none;
        }
        .visual-floats .float-card b { white-space: nowrap; }
        .alerta-bd { margin-top: 1.1rem; }
    }
    @keyframes float-y {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-.4rem); }
    }

    /* ===== Métricas ===== */
    .metrics {
        display: grid; gap: .8rem;
        margin-top: clamp(2.5rem, 5vw, 3.75rem);
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .metric {
        position: relative; overflow: hidden;
        padding: 1.05rem 1.1rem;
        border-radius: var(--radius-lg);
        background: var(--surface);
        border: 1px solid var(--border);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        transition: border-color .18s ease, transform .18s ease;
    }
    .metric::before {
        content: ""; position: absolute; inset: 0 0 auto; height: 1px;
        background: linear-gradient(90deg, transparent, var(--accent), var(--cyan), transparent);
        opacity: .75;
    }
    .metric:hover { border-color: var(--card-hover-border); transform: translateY(-2px); }
    .metric i {
        display: grid; place-items: center;
        width: 1.85rem; height: 1.85rem; border-radius: .55rem;
        background: var(--accent-soft); color: var(--cyan); font-size: .8rem;
        margin-bottom: .6rem;
    }
    .metric b { display: block; font-size: clamp(1.1rem, 1.8vw, 1.3rem); letter-spacing: -.02em; line-height: 1.25; }
    .metric span { color: var(--subtle); font-size: .75rem; }

    /* ===== Títulos de sección ===== */
    .eyebrow {
        text-transform: uppercase; letter-spacing: .14em;
        font-size: .72rem; font-weight: 800; color: var(--cyan);
    }
    h2 {
        margin: .55rem 0 0;
        font-size: clamp(1.7rem, 3.4vw, 2.4rem);
        line-height: 1.15; letter-spacing: -.02em; font-weight: 800;
    }
    .section-head p { margin: .8rem 0 0; max-width: 62ch; color: var(--muted); }

    /* ===== Tarjetas glass ===== */
    .grid { display: grid; gap: 1.1rem; margin-top: 2rem; grid-template-columns: 1fr; }
    .card {
        position: relative; overflow: hidden;
        display: flex; flex-direction: column;
        padding: 1.35rem;
        border-radius: var(--radius-lg);
        background:
            linear-gradient(180deg, var(--card-top), var(--card-bottom)),
            var(--surface);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        text-decoration: none; color: inherit;
        transition: border-color .18s, box-shadow .18s, transform .18s, background-color .18s;
    }
    /* Barrido de luz del tema NeuroAI: una franja diagonal cruza la tarjeta al
     * pasar el ratón (::after de -130% a 135%, transición de .6s). */
    .card::after {
        content: "";
        position: absolute; top: 0; left: -130%;
        width: 55%; height: 100%;
        background: linear-gradient(120deg, transparent, var(--shine), transparent);
        transform: skew(-18deg);
        transition: left .6s;
        pointer-events: none;
    }
    a.card:hover::after, a.card:focus-visible::after { left: 135%; }
    a.card:hover, a.card:focus-visible {
        transform: translateY(-3px);
        border-color: var(--card-hover-border);
        box-shadow: var(--card-hover-shadow);
        outline: none;
    }
    /* Miniatura del subsistema: siempre visible, alineada con el icono para no
       tapar el título ni la descripción. */
    .card-media {
        position: absolute; top: 1.35rem; right: 1.35rem; z-index: 1;
        width: 5.5rem; height: 3.6rem; border-radius: .8rem;
        overflow: hidden;
        border: 1px solid var(--border-strong);
        box-shadow: 0 .6rem 1.4rem rgba(0, 0, 0, .35);
        pointer-events: none;
    }
    .card-media img { display: block; width: 100%; height: 100%; object-fit: cover; }

    /* Fondo del card destacado: muestra la imagen del subsistema que se señala. */
    .card.is-featured { --featured-img: none; }
    .card.is-featured::before {
        content: "";
        position: absolute; inset: 0; z-index: 0;
        background-image: var(--featured-img);
        background-size: cover; background-position: center;
        opacity: 0;
        transition: opacity .2s ease;
        pointer-events: none;
    }
    .card.is-featured.is-preview::before { opacity: .3; }
    .card.is-featured > * { position: relative; z-index: 1; }
    .card-icon {
        display: grid; place-items: center;
        width: 2.8rem; height: 2.8rem; border-radius: .8rem;
        background: linear-gradient(135deg, rgba(109, 93, 252, .25), rgba(36, 214, 255, .14));
        border: 1px solid var(--border-strong);
        color: var(--card-icon-color); font-size: 1.1rem;
        margin-bottom: 1rem;
        transition: transform .18s ease, background .18s ease, border-color .18s ease, color .18s ease;
    }
    /* En hover el icono toma el gradiente del tema: así el efecto se nota
     * igual en modo oscuro y en modo claro. */
    a.card:hover .card-icon, a.card:focus-visible .card-icon {
        transform: translateY(-2px) scale(1.06);
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        border-color: transparent;
        color: #fff;
    }
    .card h3 { margin: 0; font-size: 1.08rem; letter-spacing: -.01em; transition: color .18s ease; }
    a.card:hover h3, a.card:focus-visible h3 { color: var(--accent); }
    .card p { margin: .55rem 0 0; color: var(--muted); font-size: .9rem; flex: 1; }
    .card-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: .75rem; margin-top: 1.15rem;
        color: var(--muted); font-size: .76rem;
    }
    .card-foot .arrow { color: var(--cyan); transition: transform .16s; }
    a.card:hover .arrow { transform: translateX(.25rem); }
    .card.is-featured {
        background:
            linear-gradient(180deg, var(--destacada-top), var(--destacada-bottom));
        border-color: rgba(109, 93, 252, .45);
        box-shadow: var(--shadow-glow);
    }
    .card.is-featured .card-icon {
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        color: #fff;
    }
    .featured-flag {
        align-self: flex-start;
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .25rem .6rem; margin-bottom: .9rem;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        color: #fff; font-size: .62rem; font-weight: 800;
        letter-spacing: .08em; text-transform: uppercase;
    }
    .card-chips {
        list-style: none; display: flex; flex-wrap: wrap; gap: .4rem;
        margin: 1rem 0 0; padding: 0;
    }
    .card-chips li {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .3rem .6rem; border-radius: .5rem;
        background: var(--tile); border: 1px solid var(--border);
        color: var(--muted); font-size: .72rem; font-weight: 600;
    }
    .card-chips i { color: var(--cyan); font-size: .68rem; }
    .tag {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .2rem .55rem; border-radius: 999px;
        background: var(--tile); border: 1px solid var(--border);
        color: var(--muted); font-size: .68rem; font-weight: 700;
    }

    /* ===== Servicios ===== */
    .features { display: grid; gap: 1rem; margin-top: 2rem; }
    .feature {
        position: relative;
        padding: 1.4rem 1.35rem;
        border-radius: var(--radius-lg);
        background: var(--surface);
        border: 1px solid var(--border);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .feature:hover { transform: translateY(-2px); border-color: var(--card-hover-border); box-shadow: var(--shadow-glow); }
    .feature i {
        display: inline-grid; place-items: center;
        width: 2.35rem; height: 2.35rem; border-radius: .65rem;
        background: var(--accent-soft); border: 1px solid var(--border-strong);
        color: var(--cyan); font-size: .95rem;
    }
    .feature h3 { margin: .75rem 0 0; font-size: .98rem; }
    .feature p { margin: .4rem 0 0; color: var(--muted); font-size: .85rem; }

    /* ===== CTA ===== */
    .cta {
        position: relative; overflow: hidden;
        margin-top: 1rem; padding: clamp(2rem, 5vw, 3.25rem);
        border-radius: 1.25rem;
        background:
            radial-gradient(30rem 18rem at 10% 0%, rgba(36, 214, 255, .16), transparent 60%),
            radial-gradient(30rem 18rem at 90% 100%, rgba(109, 93, 252, .22), transparent 60%),
            var(--surface);
        border: 1px solid rgba(109, 93, 252, .32);
        box-shadow: var(--shadow);
    }
    .cta::after {
        content: ""; position: absolute; inset: 0 0 auto; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(36, 214, 255, .55), rgba(109, 93, 252, .55), transparent);
    }
    .cta > * { position: relative; z-index: 1; }
    .cta h2 { max-width: 24ch; }
    .cta p { margin: .8rem 0 0; max-width: 60ch; color: var(--muted); }
    /* El CTA cierra la página: sin el padding completo de .section para no
     * dejar un hueco enorme entre los servicios y el bloque final. */
    .cta-section { padding-block: 0 clamp(3.5rem, 6vw, 5rem); }

    /* ===== Pie (estructura de la portada de facturación) =====
     * Cuatro columnas: marca, enlaces, contacto con botones y redes + legal. */
    .site-footer {
        margin-top: clamp(3rem, 6vw, 5rem);
        border-top: 1px solid rgba(109, 93, 252, .38);
        background: var(--footer-bg);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, .35);
        /* Interlineado compacto, como se pidió para todo el pie. */
        line-height: 1;
    }
    .footer-grid {
        display: grid;
        gap: 2rem;
        grid-template-columns: 1fr;
        padding-block: clamp(2.5rem, 5vw, 3.5rem);
    }
    .footer-col { color: var(--muted); font-size: .86rem; min-width: 0; }
    .footer-col h4 {
        margin: 0 0 .9rem;
        color: var(--txt);
        font-size: .76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .12em;
    }
    .footer-col h4 i { color: var(--cyan); margin-right: .45rem; }
    .footer-col p { margin: 0 0 .9rem; line-height: 1; }
    .footer-col a { color: var(--muted); text-decoration: none; transition: color .15s; }
    .footer-col a:hover, .footer-col a:focus-visible { color: var(--cyan); }

    .footer-brand { display: flex; flex-direction: column; align-items: flex-start; gap: .55rem; }
    .footer-logo {
        width: 3.4rem; height: 3.4rem; object-fit: contain;
        padding: .22rem; margin-bottom: .35rem;
        border-radius: .8rem;
        background: var(--tile);
        border: 1px solid var(--border);
    }
    .footer-brand strong { color: var(--txt); font-size: 1.02rem; letter-spacing: -.01em; }
    .footer-brand small { color: var(--subtle); font-size: .72rem; }
    .footer-slogan { color: var(--mint); font-weight: 700; font-size: .8rem; margin: 0; }

    .footer-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .6rem; }
    .footer-list a { display: inline-flex; align-items: center; gap: .5rem; }
    .footer-list i { width: 1rem; text-align: center; color: var(--subtle); font-size: .8rem; transition: color .15s; }
    .footer-list a:hover i { color: var(--cyan); }
    /* Aviso para módulos del pie cuya página todavía no existe. */
    .aviso-modulo {
        position: fixed; left: 50%; bottom: 1.5rem; z-index: 10000;
        display: flex; align-items: center; gap: .6rem;
        max-width: calc(100vw - 2rem); padding: .75rem 1rem;
        border-radius: .8rem; border: 1px solid var(--border);
        background: rgba(15, 23, 42, .96); color: #e2e8f0;
        box-shadow: 0 18px 40px rgba(0, 0, 0, .45); font-size: .82rem;
        opacity: 0; pointer-events: none;
        transform: translateX(-50%) translateY(1rem);
        transition: opacity .2s ease, transform .2s ease;
    }
    .aviso-modulo.is-on { opacity: 1; transform: translateX(-50%) translateY(0); }
    .aviso-modulo i { color: var(--cyan); }

    .footer-contact p { margin-bottom: 1rem; }
    .footer-contact a { display: inline-flex; align-items: center; gap: .5rem; }
    .footer-contact i { color: var(--subtle); font-size: .8rem; width: 1rem; text-align: center; }
    .footer-contact small { display: inline-flex; align-items: flex-start; gap: .5rem; color: var(--subtle); font-size: .76rem; line-height: 1; }
    .footer-buttons { display: flex; flex-wrap: wrap; gap: .55rem; }
    /* Los botones del pie no deben heredar el color de enlace de .footer-col a. */
    .footer-col .btn { color: var(--txt); text-decoration: none; }
    .footer-col .btn i { color: inherit; opacity: .9; }
    .footer-buttons .btn-primary,
    .footer-buttons .btn-primary:hover,
    .footer-buttons .btn-primary:focus-visible { color: #fff; }

    .social-links { display: flex; flex-wrap: wrap; gap: .55rem; }
    .social-link {
        display: inline-grid; place-items: center;
        width: 2.4rem; height: 2.4rem;
        border-radius: .7rem;
        background: var(--tile);
        border: 1px solid var(--border);
        color: var(--muted); font-size: .92rem;
        transition: transform .15s, color .15s, border-color .15s, background .15s;
    }
    .social-link:hover, .social-link:focus-visible {
        transform: translateY(-2px);
        color: #fff;
        border-color: rgba(109, 93, 252, .6);
        background: rgba(109, 93, 252, .22);
        outline: none;
    }
    .footer-legal-title { margin-top: 1.4rem !important; }
    .footer-legal-links { display: flex; flex-wrap: wrap; gap: .4rem .95rem; font-size: .78rem; }
    .footer-legal-links a { display: inline-flex; align-items: center; gap: .35rem; }

    .footer-bar {
        border-top: 1px solid var(--border);
        padding-block: 1rem;
        color: var(--subtle);
        font-size: .76rem;
    }
    .footer-bar .wrap { display: flex; flex-direction: column; gap: .3rem; }
    .footer-bar i { color: var(--cyan); }

    /* ===== Interruptor de tema (sol / luna), igual que el panel phpinfo ===== */
    .theme-switch {
        display: inline-flex; align-items: center;
        padding: 0; background: transparent; border: 0;
        line-height: 0; cursor: pointer; flex: 0 0 auto;
    }
    .theme-switch-track {
        position: relative;
        display: inline-flex; align-items: center; justify-content: space-between;
        width: 64px; height: 32px; padding: 0 10px;
        border-radius: 999px;
        background-color: var(--switch-bg);
        border: 1px solid var(--switch-border);
        transition: background-color .25s ease, border-color .25s ease;
    }
    .theme-switch-icon {
        position: relative; z-index: 1;
        font-size: .8rem; color: var(--muted); opacity: .55;
        transition: color .25s ease, opacity .25s ease;
    }
    .theme-switch-thumb {
        position: absolute; top: 2px; left: 2px; z-index: 0;
        width: 26px; height: 26px; border-radius: 50%;
        background-color: var(--switch-thumb);
        border: 1px solid var(--switch-thumb-border);
        box-shadow: 0 2px 6px rgba(16, 24, 40, .35);
        transition: transform .28s cubic-bezier(.4, .1, .3, 1), background-color .25s ease;
    }
    [data-bs-theme="light"] .theme-switch-thumb { transform: translateX(0); }
    [data-bs-theme="dark"]  .theme-switch-thumb { transform: translateX(32px); }
    [data-bs-theme="light"] .theme-switch-sun { color: var(--switch-sun); opacity: 1; }
    [data-bs-theme="dark"]  .theme-switch-moon { color: var(--switch-moon); opacity: 1; }
    [data-bs-theme="light"] .theme-switch-moon,
    [data-bs-theme="dark"]  .theme-switch-sun { opacity: .45; }
    .theme-switch:hover .theme-switch-track,
    .theme-switch:focus-visible .theme-switch-track {
        border-color: var(--switch-moon);
        background-color: var(--accent-soft);
    }
    .theme-switch:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 999px; }

    /* ===== Botones flotantes: ir hasta arriba / ir hasta abajo ===== */
    .scroll-fabs {
        position: fixed;
        right: 1.15rem;
        bottom: 1.15rem;
        z-index: 60;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .scroll-fab {
        display: flex; align-items: center; justify-content: center;
        width: 2.9rem; height: 2.9rem;
        padding: 0;
        border-radius: 50%;
        background-color: var(--surface-strong);
        color: var(--accent);
        border: 1px solid var(--border);
        box-shadow: 0 8px 20px -10px rgba(16, 24, 40, .55);
        cursor: pointer;
        opacity: 0; visibility: hidden;
        transform: translateY(8px) scale(.92);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        transition: opacity .2s ease, transform .2s ease, visibility .2s ease,
                    background-color .2s ease, color .2s ease, border-color .2s ease;
    }
    .scroll-fab.is-visible { opacity: 1; visibility: visible; transform: none; }
    .scroll-fab:hover { color: #fff; background-color: var(--accent); border-color: var(--accent); transform: translateY(-2px); }
    .scroll-fab:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

    /* Agrupación del interruptor y el botón de acceso en el topbar */
    .nav-acciones { display: flex; align-items: center; gap: .65rem; }

    /* ===== Preguntas frecuentes (acordeón nativo) ===== */
    .faq { display: grid; gap: .7rem; margin-top: 2rem; }
    .faq-item {
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        /* Vidrio: fondo translucido + desenfoque para dejar ver la pagina. */
        background: color-mix(in srgb, var(--surface) 72%, transparent);
        backdrop-filter: blur(22px) saturate(135%); -webkit-backdrop-filter: blur(22px) saturate(135%);
        overflow: hidden;
        transition: border-color .18s ease, background-color .18s ease,
                    transform .22s ease, box-shadow .22s ease;
    }
    .faq-item:hover {
        border-color: var(--card-hover-border);
        background: color-mix(in srgb, var(--surface-strong) 84%, transparent);
        transform: translateY(-2px);
        box-shadow: 0 .9rem 1.8rem rgba(0, 0, 0, .2);
    }
    .faq-item[open] {
        border-color: var(--card-hover-border);
        background: color-mix(in srgb, var(--surface-strong) 84%, transparent);
    }
    .faq-item summary {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 1.05rem 1.2rem;
        cursor: pointer; list-style: none;
        font-size: .95rem; font-weight: 700; color: var(--txt);
    }
    .faq-item summary::-webkit-details-marker { display: none; }
    .faq-item summary:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
    .faq-item summary i {
        flex: 0 0 auto; color: var(--cyan); font-size: .8rem;
        transition: transform .2s ease, color .2s ease;
    }
    .faq-item:not([open]):hover summary i { transform: rotate(90deg); color: var(--accent); }
    .faq-item[open] summary i { transform: rotate(45deg); }
    .faq-body { padding: 0 1.2rem 1.15rem; color: var(--muted); font-size: .9rem; }
    .faq-body p { margin: 0; }
    .faq-body a {
        color: var(--cyan); text-decoration: none;
        border-bottom: 1px solid color-mix(in srgb, var(--cyan) 40%, transparent);
    }
    .faq-item code {
        font-size: .82em; padding: .05rem .3rem; border-radius: .3rem;
        background: var(--tile); border: 1px solid var(--border); color: var(--txt);
    }

    /* ===== Barra de progreso de lectura ===== */
    .scroll-progress {
        position: fixed; top: 0; left: 0; right: 0;
        height: 2px; z-index: 70; pointer-events: none;
    }
    .scroll-progress span {
        display: block; width: 0; height: 100%;
        background: linear-gradient(90deg, var(--accent), var(--cyan));
        box-shadow: 0 0 12px rgba(36, 214, 255, .55);
    }

    /* ===== Navegación compacta (tablet y móvil) ===== */
    .mobile-nav {
        display: flex; gap: .4rem;
        padding-bottom: .7rem;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .mobile-nav::-webkit-scrollbar { display: none; }
    .mobile-nav a {
        flex: 0 0 auto;
        display: inline-flex; align-items: center; gap: .38rem;
        padding: .36rem .7rem; border-radius: 999px;
        background: var(--tile); border: 1px solid var(--border);
        color: var(--muted); font-size: .75rem; font-weight: 600;
        text-decoration: none; white-space: nowrap;
        transition: color .15s, border-color .15s, background-color .15s;
    }
    .mobile-nav a i { color: var(--cyan); font-size: .68rem; }
    .mobile-nav a:hover, .mobile-nav a:focus-visible {
        color: var(--txt); border-color: var(--accent); background: var(--accent-soft);
    }

    /* ===== Aparición progresiva de bloques ===== */
    .js-reveal .reveal {
        opacity: 0; transform: translateY(16px);
        transition: opacity .6s ease, transform .6s cubic-bezier(.2, .7, .3, 1);
    }
    .js-reveal .reveal.is-in { opacity: 1; transform: none; }
    .js-reveal .grid > .reveal:nth-child(2),
    .js-reveal .features > .reveal:nth-child(2) { transition-delay: .06s; }
    .js-reveal .grid > .reveal:nth-child(3),
    .js-reveal .features > .reveal:nth-child(3) { transition-delay: .12s; }
    .js-reveal .grid > .reveal:nth-child(4),
    .js-reveal .features > .reveal:nth-child(4) { transition-delay: .18s; }
    .js-reveal .grid > .reveal:nth-child(5),
    .js-reveal .features > .reveal:nth-child(5) { transition-delay: .24s; }
    .js-reveal .grid > .reveal:nth-child(6),
    .js-reveal .features > .reveal:nth-child(6) { transition-delay: .3s; }

    /* ===== Responsive ===== */
    @media (min-width: 640px) {
        .metrics { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .features { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .card.is-featured { grid-column: span 2; }
    }
    /* Con dos columnas la última tarjeta quedaría sola: ocupa el ancho completo. */
    @media (min-width: 640px) and (max-width: 1023px) {
        .grid > .card:nth-child(6) { grid-column: span 2; }
    }
    @media (min-width: 720px) {
        .footer-grid { grid-template-columns: 1.5fr 1fr 1.25fr 1fr; }
        .footer-bar .wrap { flex-direction: row; align-items: center; justify-content: space-between; }
    }
    @media (min-width: 980px) {
        .hero-grid { grid-template-columns: minmax(0, 1.03fr) minmax(0, .97fr); }
    }
    @media (min-width: 1024px) {
        .nav-links { display: flex; }
        .mobile-nav { display: none; }
        .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        /* Bentor: la tarjeta destacada ocupa 2x2 y el resto rellena las celdas. */
        .card.is-featured { grid-column: span 2; grid-row: span 2; padding: clamp(1.5rem, 2.2vw, 2rem); }
        .card.is-featured h3 { font-size: 1.45rem; }
        .card.is-featured p { max-width: 46ch; }
        .card.is-featured .card-icon { width: 3.1rem; height: 3.1rem; font-size: 1.25rem; }
        .features { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .footer-grid { grid-template-columns: 1.6fr 1fr 1.3fr 1.1fr; }
    }
    @media (max-width: 979px) {
        /* En una sola columna la ventana se centra y se limita para no dominar. */
        .hero-visual { width: 100%; max-width: 30rem; margin-inline: auto; }
    }
    @media (max-width: 1023px) {
        /* Compensa la barra de navegación compacta al saltar a una sección. */
        section[id] { scroll-margin-top: 7.5rem; }
    }
    @media (max-width: 480px) {
        /* En pantallas muy estrechas el nombre completo tiene que caber junto
         * al interruptor de tema y al botón: se reducen logo y tipografía. */
        .brand-logo { width: 2.35rem; height: 2.35rem; }
        .brand-name { font-size: .68rem; }
        .brand-name small { font-size: .49rem; letter-spacing: .02em; }
        .btn-sm { padding: .5rem .6rem; font-size: .76rem; }
        .btn-texto { display: none; }
        .nav { gap: .5rem; }
        .nav-acciones { gap: .45rem; }
        .theme-switch-track { width: 50px; height: 26px; padding: 0 7px; }
        .theme-switch-thumb { width: 20px; height: 20px; }
        [data-bs-theme="dark"] .theme-switch-thumb { transform: translateX(24px); }
        .theme-switch-icon { font-size: .68rem; }
        .scroll-fabs { right: .8rem; bottom: .8rem; }
        .scroll-fab { width: 2.55rem; height: 2.55rem; font-size: .9rem; }
        /* La ventana cabe completa: se aprieta el par de tarjetas del mock. */
        .mock-split { gap: .45rem; }
        .mock-split .mock-chart, .mock-payroll { padding: .55rem; }
        .mock-pay { font-size: .53rem; }
        .mock-pay b { font-size: .6rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        * { transition: none !important; }
        .card::after { display: none; }
        .float-card, .mock-bars span, .pay-fill { animation: none !important; }
        .faq-item:hover { transform: none; }
    }

    /* ===== Intro animada: piezas del logo (images/anim) =====
       Secuencia: cada pieza entra con un fundido y, al final, aparece el logo
       completo con un destello transversal recortado por su propia silueta. */
    html.intro-lock { overflow: hidden; }
    .intro {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: grid;
        place-items: center;
        background: radial-gradient(120% 120% at 50% 26%, #111c38 0%, #070a12 56%, #03050a 100%);
        transition: opacity .8s ease, visibility .8s ease;
    }
    .intro.is-out { opacity: 0; visibility: hidden; }
    .intro-stage {
        position: relative;
        width: min(92vmin, 760px);
        aspect-ratio: 429 / 440;
        display: grid;
        place-items: center;
        isolation: isolate;
    }
    .intro-halo {
        position: absolute;
        z-index: 0;
        width: 78%;
        aspect-ratio: 1;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(36, 214, 255, .34) 0%, rgba(109, 93, 252, .20) 44%, rgba(109, 93, 252, 0) 70%);
        filter: blur(10px);
        opacity: 0;
        transform: scale(.72);
        transition: opacity 1s ease, transform 1.4s cubic-bezier(.22, .8, .24, 1);
    }
    .intro.is-final .intro-halo { opacity: 1; transform: scale(1); animation: introHalo 4s ease-in-out 1.2s infinite; }
    @keyframes introHalo {
        0%, 100% { opacity: .82; transform: scale(1); }
        50%      { opacity: 1;   transform: scale(1.06); }
    }
    .intro-layer,
    .intro-final {
        position: absolute;
        inset: 0;
        z-index: 1;
        display: grid;
        place-items: center;
        opacity: 0;
        transform: scale(.92) translateY(10px);
        transition: opacity .7s ease, transform 1.15s cubic-bezier(.16, .84, .24, 1);
        will-change: opacity, transform;
        pointer-events: none;
    }
    .intro-layer img {
        position: absolute;
        height: auto;
        max-width: none;
        max-height: none;
        filter: drop-shadow(0 24px 46px rgba(0, 0, 0, .62));
    }
    /* Cada pieza queda en el sitio que ocupa dentro del logo final. */
    .intro-layer[data-paso="1"] img { left: 0;   top: 0;  width: 100%; }
    .intro-layer[data-paso="2"] img { left: 3%;  top: 7%; width: 78%; }
    .intro-layer[data-paso="3"] img { left: 5%;  top: 40%; width: 66%; }
    .intro-layer[data-paso="4"] img { left: 55%; top: 18%; width: 44%; }
    .intro-layer[data-paso="5"] img { left: 12%; top: 74%; width: 78%; }
    /* Entradas: cada pieza recorre una trayectoria larga desde fuera del
       escenario (el círculo llega desde el fondo) hasta su sitio en el logo. */
    .intro-layer[data-paso="1"] { transform: scale(.5); }
    .intro-layer[data-paso="2"] { transform: translateX(135%) scale(.68); }
    .intro-layer[data-paso="3"] { transform: translateX(-135%) scale(.68); }
    .intro-layer[data-paso="4"] { transform: translateX(135%) scale(.68); }
    .intro-layer[data-paso="5"] { transform: translateY(-185%) scale(.68); }
    .intro-layer.is-on { opacity: 1; transform: translate(0, 0) scale(1); }
    /* Al entrar el logo completo se retiran las piezas para que no asomen por
       los bordes transparentes de final.png. */
    .intro.is-final .intro-layer { opacity: 0; transition: opacity .3s ease; }
    .intro-final { z-index: 2; transform: scale(.92) translateY(8px); }
    .intro-logo { position: relative; width: 100%; }
    .intro-logo img { display: block; width: 100%; height: auto; filter: drop-shadow(0 30px 70px rgba(0, 0, 0, .66)); }
    .intro-final.is-on { opacity: 1; transform: scale(1) translateY(0); }
    .intro-final.is-on .intro-logo { animation: introLogoIn 1.15s cubic-bezier(.2, .72, .2, 1) both; }
    @keyframes introLogoIn {
        0%   { transform: scale(.84); filter: brightness(1.45) saturate(1.15); }
        60%  { transform: scale(1.03); }
        100% { transform: scale(1); filter: none; }
    }
    .intro-sheen {
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0;
        background-image: linear-gradient(105deg,
            rgba(255, 255, 255, 0) 40%,
            rgba(255, 255, 255, .32) 46%,
            rgba(255, 255, 255, .95) 50%,
            rgba(140, 235, 255, .85) 54%,
            rgba(255, 255, 255, 0) 60%);
        background-size: 260% 100%;
        background-position: 135% 0;
        background-repeat: no-repeat;
        -webkit-mask-size: contain;
        mask-size: contain;
        -webkit-mask-repeat: no-repeat;
        mask-repeat: no-repeat;
        -webkit-mask-position: center;
        mask-position: center;
        mix-blend-mode: screen;
    }
    .intro.is-sheen .intro-sheen { opacity: 1; animation: introSheen 1.4s cubic-bezier(.4, 0, .5, 1) 2; }
    @keyframes introSheen {
        0%   { background-position: 135% 0; }
        100% { background-position: -45% 0; }
    }
    .intro-bar {
        position: absolute;
        left: 50%;
        bottom: max(2.25rem, 5vh);
        width: min(64vmin, 520px);
        height: 3px;
        transform: translateX(-50%);
        background: rgba(255, 255, 255, .14);
        border-radius: 2px;
        overflow: hidden;
    }
    .intro-bar span {
        display: block;
        width: 0;
        height: 100%;
        background: linear-gradient(90deg, #24d6ff, #6d5dfc);
        transition: width .35s ease;
    }
    .intro-skip {
        position: absolute;
        right: max(1rem, 2vw);
        bottom: max(1rem, 2vh);
        padding: .5rem .95rem;
        font: inherit;
        font-size: .8rem;
        color: #cfd6ff;
        background: rgba(255, 255, 255, .06);
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 999px;
        cursor: pointer;
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
        transition: background .2s ease, border-color .2s ease, color .2s ease;
    }
    @media (max-width: 520px) {
        /* En pantallas estrechas el botón sube para no pisar la barra. */
        .intro-skip { top: max(1rem, 2vh); bottom: auto; right: 1rem; }
        .intro-bar { bottom: max(1.25rem, 3vh); width: min(82vw, 360px); }
    }
    .intro-skip:hover,
    .intro-skip:focus-visible {
        background: rgba(255, 255, 255, .12);
        border-color: rgba(36, 214, 255, .5);
        color: #fff;
        outline: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .intro-layer, .intro-final, .intro-halo { transition-duration: .01ms !important; }
        .intro-final.is-on .intro-logo,
        .intro.is-sheen .intro-sheen,
        .intro.is-final .intro-halo { animation: none !important; }
    }
</style>
</head>
<body id="top">
<!-- Intro animada: piezas del logo en images/anim. Se retira sola y se puede
     saltar; con «prefers-reduced-motion» no se muestra. El comportamiento
     está al pie del documento, junto al resto de scripts. -->
<div class="intro<?= $introSaltar ? ' is-out' : '' ?>" id="introAnim">
    <div class="intro-stage">
        <div class="intro-halo"></div>
        <div class="intro-layer" data-paso="1"><img src="/images/anim/01_Circulo.png" alt="" width="498" height="490" decoding="async"></div>
        <div class="intro-layer" data-paso="2"><img src="/images/anim/02_Unicornio.png" alt="" width="456" height="303" decoding="async"></div>
        <div class="intro-layer" data-paso="3"><img src="/images/anim/03_Trabajadores.png" alt="" width="446" height="319" decoding="async"></div>
        <div class="intro-layer" data-paso="4"><img src="/images/anim/04_Elementos_Nomina.png" alt="" width="412" height="372" decoding="async"></div>
        <div class="intro-layer" data-paso="5"><img src="/images/anim/05_Logo_SiGesNom.png" alt="" width="546" height="183" decoding="async"></div>
        <div class="intro-final">
            <div class="intro-logo">
                <img src="/images/anim/final.png" alt="UnicornioSoftware Cuba" width="429" height="440" decoding="async">
                <span class="intro-sheen" aria-hidden="true" style="-webkit-mask-image:url('/images/anim/final.png');mask-image:url('/images/anim/final.png');"></span>
            </div>
        </div>
    </div>
    <div class="intro-bar" aria-hidden="true"><span id="introBar"></span></div>
    <button type="button" class="intro-skip" id="introSkip">Saltar intro</button>
</div>
<?php if (!$introSaltar): ?><script>document.documentElement.classList.add('intro-lock');</script><?php endif; ?>
<a class="skip" href="#contenido"><i class="fa-solid fa-arrow-down"></i> Saltar al contenido</a>
<div class="scroll-progress" aria-hidden="true"><span id="scrollProgress"></span></div>

<header class="site-header">
    <div class="wrap nav">
        <a class="brand" href="/" title="Entorno de Aplicaciones Web de UnicornioSoftware® Cuba. Kaky®">
            <img class="brand-logo" src="/images/logo.png" alt="UnicornioSoftware Cuba">
            <span class="brand-name">Entorno de Aplicaciones Web
                <small>de UnicornioSoftware<sup>&reg;</sup> Cuba. Kaky<sup>&reg;</sup></small>
            </span>
        </a>
        <nav class="nav-links" aria-label="Secciones">
            <a href="#top"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
            <a href="#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
            <a href="#servicios"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Servicios</a>
            <a href="#preguntas"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Preguntas</a>
            <a href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Soporte</a>
            <a href="<?= $base ?>/explorer.php"><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Autor</a>
        </nav>
        <div class="nav-acciones">
            <button class="theme-switch" id="themeToggle" type="button" role="switch"
                    aria-checked="true" aria-label="Cambiar entre tema claro y oscuro"
                    title="Cambiar a tema claro">
                <span class="theme-switch-track">
                    <i class="fa-solid fa-sun theme-switch-icon theme-switch-sun" aria-hidden="true"></i>
                    <i class="fa-solid fa-moon theme-switch-icon theme-switch-moon" aria-hidden="true"></i>
                    <span class="theme-switch-thumb"></span>
                </span>
            </button>
            <a class="btn btn-primary btn-sm" href="<?= $base ?>/index.php" title="Explorar subsistemas">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> <span class="btn-texto">Explorar subsistemas</span>
            </a>
        </div>
    </div>
    <!-- En pantallas estrechas los enlaces del navbar no caben: pasan a una
         fila de píldoras desplazable dentro del propio header fijo. -->
    <div class="wrap">
        <nav class="mobile-nav" aria-label="Secciones">
            <a href="#top"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
            <a href="#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
            <a href="#servicios"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Servicios</a>
            <a href="#preguntas"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Preguntas</a>
            <a href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Soporte</a>
            <a href="<?= $base ?>/explorer.php"><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Autor</a>
        </nav>
    </div>
</header>

<main id="contenido">
    <!-- ===== Hero ===== -->
    <section class="wrap hero">
        <div class="hero-grid">
            <div class="hero-copy">
                <span class="badge"><span class="dot"></span> Plataforma local · v10.0.1</span>
                <span class="hero-eyebrow">UnicornioSoftware · Gestión empresarial <span>· Cuba</span></span>
                <h1 class="hero-titulo">SOFTWARE PARA <span>TRANSFORMAR TU NEGOCIO.</span></h1>
                <p class="lead">
                    Facturación, nóminas, costos e inventarios en una plataforma que corre en tu propia red,
                    pensada para resolver el día a día real de tu operación.
                </p>
                <div class="hero-tags">
                    <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Productos propios</span>
                    <span class="sep" aria-hidden="true">·</span>
                    <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Gestión a medida</span>
                    <span class="sep" aria-hidden="true">·</span>
                    <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Soporte técnico</span>
                </div>
                <div class="hero-actions hero-actions-3">
                    <a class="btn btn-primary" href="#ecosistema">
                        <i class="fa-solid fa-layer-group"></i> Explorar el ecosistema
                    </a>
                    <button type="button" class="btn btn-glass" data-estado-lic-open>
                        <i class="fa-solid fa-key"></i> Estado de la licencia
                    </button>
                    <a class="btn btn-glass" href="<?= $base ?>/phpinfo/">
                        <i class="fa-solid fa-server"></i> Estado del servidor
                    </a>
                </div>
                <p class="hero-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Instalación local · sin dependencia de la nube · respaldo periódico
                </p>
                <p class="hero-note hero-note-licencia">
                    <i class="fa-solid fa-key"></i>
                    <a href="<?= $base ?>/nominas/licencia.php<?= $licenciaActiva ? '?renovar=1' : '' ?>" data-lic-open
                       title="<?= htmlspecialchars($licenciaTitulo, ENT_QUOTES, 'UTF-8') ?>">¿Ya lo usas, licencia vencida? Renueva o activa tu licencia.</a>
                </p>
            </div>

            <!-- Vista previa del sistema con datos reales del último mes. -->
            <div class="hero-visual">
                <div class="visual-stage">
                <div class="window">
                    <div class="window-bar">
                        <span class="window-dots"><i></i><i></i><i></i></span>
                        <span class="window-url"><i class="fa-solid fa-lock"></i> localhost/servicios/facturacion</span>
                        <span class="window-status is-<?= $bdEstado === 'online' ? 'on' : ($bdEstado === 'parcial' ? 'warn' : 'off') ?>">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i><span>MySQL</span>
                        </span>
                    </div>
                    <div class="window-body">
                        <div class="mock-kpis">
                            <div class="mock-kpi">
                                <small><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturado</small>
                                <b><?= $facturacion['ok'] ? landing_num_corto((float)$facturacion['total']) : '—' ?><span class="kpi-unit"> CUP</span></b>
                                <?php if (!$facturacion['ok']): ?>
                                    <span class="is-off">Sin conexión a MySQL</span>
                                <?php elseif ($facturacion['variacion'] === null): ?>
                                    <span>Sin mes anterior</span>
                                <?php else: ?>
                                    <span class="<?= $facturacion['variacion'] < 0 ? 'is-down' : '' ?>"><?= $facturacion['variacion'] >= 0 ? '+' : '−' ?><?= number_format(abs((float)$facturacion['variacion']), 1, ',', '') ?> % vs <?= htmlspecialchars((string)$facturacion['mes_anterior']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mock-kpi">
                                <small><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Facturas</small>
                                <b><?= $facturacion['ok'] ? (int)$facturacion['facturas'] : '—' ?></b>
                                <span><?= $facturacion['ok'] ? 'en ' . htmlspecialchars((string)$facturacion['mes_corto']) : 'Sin conexión' ?></span>
                            </div>
                        </div>
                        <div class="mock-split">
                            <div class="mock-chart">
                                <div class="mock-chart-head"><span>Ingresos por mes</span><b><?= (int)$facturacion['anio'] ?></b></div>
                                <?php if ($facturacion['ok'] && $serieMax > 0): ?>
                                    <div class="mock-bars">
                                        <?php foreach ($facturacion['serie'] as $punto): ?>
                                            <?php $alto = max(4, (int)round(((float)$punto['total'] / $serieMax) * 100)); ?>
                                            <span style="height: <?= $alto ?>%" title="<?= htmlspecialchars($punto['etiqueta'] . ' ' . substr((string)$punto['clave'], 0, 4) . ': ' . landing_num((float)$punto['total']) . ' CUP') ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="mock-empty">
                                        <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                                        <span><?= $facturacion['ok'] ? 'Sin facturas en el periodo' : 'Sin datos de MySQL' ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mock-payroll">
                                <div class="mock-pay-head"><span>Nómina · <?= htmlspecialchars((string)$nominas['mes_corto']) ?></span><b><?= $nominas['ok'] ? 'CUP' : '—' ?></b></div>
                                <?php if ($nominas['ok']): ?>
                                    <?php
                                        $brutoNom = max(0, (float)$nominas['bruto']);
                                        $dedNom = max(0, (float)$nominas['deducciones']);
                                        $netoNom = max(0, (float)$nominas['neto']);
                                        $pctDed = $brutoNom > 0 ? (int)round($dedNom / $brutoNom * 100) : 0;
                                        $pctNeto = $brutoNom > 0 ? (int)round($netoNom / $brutoNom * 100) : 0;
                                    ?>
                                    <div class="mock-pay-bars">
                                        <div class="mock-pay mock-pay--bruto">
                                            <span>Pagado bruto</span><b><?= landing_num($brutoNom) ?></b>
                                            <span class="pay-track"><span class="pay-fill" style="--w: 100%"></span></span>
                                        </div>
                                        <div class="mock-pay mock-pay--ded">
                                            <span>Deducciones</span><b><?= landing_num($dedNom) ?></b>
                                            <span class="pay-track"><span class="pay-fill" style="--w: <?= $pctDed ?>%"></span></span>
                                        </div>
                                        <div class="mock-pay mock-pay--net">
                                            <span>Cobrado real</span><b><?= landing_num($netoNom) ?></b>
                                            <span class="pay-track"><span class="pay-fill" style="--w: <?= $pctNeto ?>%"></span></span>
                                        </div>
                                    </div>
                                    <div class="mock-pay-foot"><?= (int)$nominas['trabajadores'] ?> trabajadores · <?= $nominas['exacto'] ? 'cierre del mes' : 'último cierre' ?></div>
                                <?php else: ?>
                                    <div class="mock-empty">
                                        <i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i>
                                        <span>Sin datos de MySQL</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mock-rows">
                            <div class="mock-row">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                <span><b>Nómina <?= htmlspecialchars((string)$nominas['etiqueta']) ?></b> · <?= $nominas['ok'] ? ($nominas['exacto'] ? 'cerrada' : 'último cierre') : 'sin datos' ?></span>
                                <em class="<?= $nominas['ok'] ? '' : 'is-off' ?>"><?= $nominas['ok'] ? 'OK' : '—' ?></em>
                            </div>
                            <div class="mock-row">
                                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                                <span>
                                    <?php if (!empty($facturacion['ultima'])): ?>
                                        <b>Factura <?= htmlspecialchars((string)$facturacion['ultima']['no_fact']) ?></b> · <?= htmlspecialchars((string)($facturacion['ultima']['cliente'] ?? 'sin cliente')) ?>
                                    <?php else: ?>
                                        <b>Sin facturas emitidas</b> · <?= $facturacion['ok'] ? 'base vacía' : 'sin conexión' ?>
                                    <?php endif; ?>
                                </span>
                                <em><?= !empty($facturacion['ultima']) ? htmlspecialchars(ucfirst(strtolower((string)$facturacion['ultima']['estado']))) : '—' ?></em>
                            </div>
                            <div class="mock-row">
                                <i class="fa-solid fa-database" aria-hidden="true"></i>
                                <span><b>MySQL</b> · <?= $bdEstado === 'online' ? 'conectado' : ($bdEstado === 'parcial' ? 'conexión parcial' : 'apagado') ?></span>
                                <em class="<?= $bdEstado === 'online' ? '' : 'is-off' ?>"><?= $bdEstado === 'online' ? 'En línea' : 'Revisar' ?></em>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="visual-floats">
                    <?php if ($nominas['ok']): ?>
                        <div class="float-card float-card--a">
                            <span class="float-icon"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i></span>
                            <span>Cobrado real<b><?= landing_num((float)$nominas['neto']) ?> CUP</b></span>
                        </div>
                        <div class="float-card float-card--d">
                            <span class="float-icon"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i></span>
                            <span>Deducciones<b><?= landing_num((float)$nominas['deducciones']) ?> CUP</b></span>
                        </div>
                    <?php endif; ?>
                    <div class="float-card float-card--b">
                        <span class="float-icon"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i></span>
                        <span>Facturación<b><?= $facturacion['ok'] ? (int)$facturacion['facturas'] . ' facturas · ' . htmlspecialchars((string)$facturacion['mes_corto']) : 'sin conexión' ?></b></span>
                    </div>
                    <?php if ($nominas['ok']): ?>
                        <div class="float-card float-card--c">
                            <span class="float-icon"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i></span>
                            <span>Nómina · <?= htmlspecialchars((string)$nominas['mes_corto']) ?><b><?= landing_num((float)$nominas['bruto']) ?> CUP</b></span>
                        </div>
                    <?php endif; ?>
                </div>
                </div>

                <?php if ($desdeGuardado): ?>
                    <div class="alerta-bd" role="alert">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <p><b>MySQL está apagado.</b> Mostrando el último dato guardado el <?= htmlspecialchars((string)($datosGuardados ?? 'sin fecha')) ?>.</p>
                        <button type="button" class="alerta-bd-cerrar" aria-label="Ocultar aviso"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                <?php elseif ($bdEstado === 'parcial'): ?>
                    <div class="alerta-bd" role="alert">
                        <i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i>
                        <p><b>Conexión parcial.</b> Sin datos de <?= htmlspecialchars($faltante) ?>; el resto se leyó al cargar.</p>
                        <button type="button" class="alerta-bd-cerrar" aria-label="Ocultar aviso"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                <?php elseif ($bdEstado === 'offline'): ?>
                    <div class="alerta-bd" role="alert">
                        <i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i>
                        <p><b>MySQL está apagado.</b> Todavía no hay datos guardados para mostrar.</p>
                        <button type="button" class="alerta-bd-cerrar" aria-label="Ocultar aviso"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="metrics" role="list">
            <div class="metric" role="listitem"><i class="fa-solid fa-cubes" aria-hidden="true"></i><b>4</b><span>Módulos de gestión</span></div>
            <div class="metric" role="listitem"><i class="fa-brands fa-php" aria-hidden="true"></i><b>PHP 8.1</b><span>Servidor local</span></div>
            <div class="metric" role="listitem"><i class="fa-solid fa-database" aria-hidden="true"></i><b>MySQL 5.7</b><span>Base de datos</span></div>
            <div class="metric" role="listitem"><i class="fa-solid fa-headset" aria-hidden="true"></i><b>24/7</b><span>Soporte técnico</span></div>
        </div>
        </section>

    <!-- ===== Ecosistema ===== -->
    <section class="wrap section" id="ecosistema">
        <div class="section-head reveal">
            <span class="eyebrow">Opciones y servicios</span>
            <h2>Elige por dónde entrar</h2>
            <p>
                Cada acceso abre una parte del sistema. Si es tu primera vez, empieza por el
                <strong>Ecosistema Financiero</strong>: desde ahí llegas a todos los módulos.
            </p>
        </div>

        <div class="grid">
            <?php foreach ($ecosistema as $item): ?>
                <a class="card reveal<?= !empty($item['destacada']) ? ' is-featured' : '' ?>" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"<?= !empty($item['pendiente']) ? ' data-modulo-pendiente="' . htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= !empty($item['imagen']) ? ' data-preview-img="' . htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                    <?php if (!empty($item['destacada'])): ?>
                        <span class="featured-flag"><i class="fa-solid fa-star" aria-hidden="true"></i> Punto de entrada</span>
                    <?php endif; ?>
                    <?php if (!empty($item['imagen'])): ?>
                    <span class="card-media" aria-hidden="true"><img src="<?= htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"></span>
                    <?php endif; ?>
                    <span class="card-icon"><i class="<?= htmlspecialchars($item['icono'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                    <h3><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="card-desc"><?= htmlspecialchars($item['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($item['destacada'])): ?>
                        <ul class="card-chips">
                            <li><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</li>
                            <li><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</li>
                            <li><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Reportes</li>
                            <li><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</li>
                            <li><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</li>
                            <li><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</li>
                        </ul>
                    <?php endif; ?>
                    <span class="card-foot">
                        <span class="tag"><?= htmlspecialchars($item['etiqueta'], ENT_QUOTES, 'UTF-8') ?></span>
                        <i class="fa-solid fa-arrow-right arrow" aria-hidden="true"></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Servicios ===== -->
    <section class="wrap section" id="servicios">
        <div class="section-head reveal">
            <span class="eyebrow">Lo que incluye</span>
            <h2>Servicios de la plataforma</h2>
            <p>Una base técnica pensada para la operación diaria y para no depender de servicios externos.</p>
        </div>

        <div class="features">
            <?php foreach ($servicios as $s): ?>
                <div class="feature reveal">
                    <i class="<?= htmlspecialchars($s['icono'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <h3><?= htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($s['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Preguntas frecuentes ===== -->
    <section class="wrap section" id="preguntas">
        <div class="section-head reveal">
            <span class="eyebrow">Antes de entrar</span>
            <h2>Preguntas frecuentes</h2>
            <p>Lo que conviene saber sobre los datos, la conexión y el soporte de la plataforma.</p>
        </div>

        <div class="faq reveal">
            <details class="faq-item">
                <summary><span>¿De dónde salen los datos que muestra esta portada?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>Se leen en cada carga desde tus bases locales: facturación (<code>tbl_fact</code>) y nóminas (<code>cierres_periodo_nomina</code>), del último mes. Si ese mes no tiene movimiento, se toma el último mes con datos y se indica cuál es. No hay cifras de ejemplo.</p></div>
            </details>
            <details class="faq-item">
                <summary><span>¿Y si MySQL está apagado?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>La portada se abre igual. La tarjeta muestra el último dato guardado con su fecha y hora, o «sin datos» si nunca se pudo consultar. Nunca se inventan cifras, y los módulos que dependen de la base esperan a que el servicio vuelva.</p></div>
            </details>
            <details class="faq-item">
                <summary><span>¿Qué se consulta exactamente del último mes?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>De facturación: total facturado, número de facturas y la última emitida (sin contar anuladas). De nóminas: pagado bruto, deducciones y cobrado real del cierre mensual. Si el mes aún no está cerrado, se usa el último cierre y se indica cuál es.</p></div>
            </details>
            <details class="faq-item">
                <summary><span>¿Necesito internet para usar el sistema?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>No. Todo corre en tu equipo o en la red local: Apache, PHP y MySQL. Los datos no salen de esa red.</p></div>
            </details>
            <details class="faq-item">
                <summary><span>¿Estas consultas modifican la base de datos?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>No. Son consultas de solo lectura del mes anterior, sin escrituras ni tareas de mantenimiento.</p></div>
            </details>
            <details class="faq-item">
                <summary><span>¿Cómo empiezo si el equipo es nuevo?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>Entra al <a href="<?= $base ?>/InstalarBD/InstalarBD.php">Instalador de base de datos</a>, crea el esquema y después abre el Ecosistema Financiero. Si ya tienes datos, salta ese paso.</p></div>
            </details>
            <details class="faq-item">
                <summary><span>¿Cómo pido soporte?</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                <div class="faq-body"><p>Escribe a <a href="mailto:kakycu@gmail.com">kakycu@gmail.com</a> o llama al <a href="tel:+5352712861">+53 5 2712861</a>, de lunes a viernes de 8:00 a 17:00.</p></div>
            </details>
        </div>
    </section>

    <!-- ===== CTA ===== -->
    <section class="wrap cta-section">
        <div class="cta reveal">
            <span class="eyebrow">Empieza ahora</span>
            <h2>Todo el sistema, a un clic desde aquí</h2>
            <p>
                La portada del sistema reúne los accesos a cada módulo, el estado de la licencia
                y la información de soporte de tu instalación.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= $base ?>/index.php">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Ir al Ecosistema Financiero
                </a>
                <a class="btn btn-glass" href="<?= $base ?>/soporte.php">
                    <i class="fa-solid fa-headset"></i> Hablar con soporte
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="wrap footer-grid">

        <!-- Columna 1: Marca -->
        <div class="footer-col footer-brand">
            <img class="footer-logo" src="/images/logo.png" alt="UnicornioSoftware Cuba">
            <strong>Entorno de Aplicaciones Web</strong>
            <small>de UnicornioSoftware<sup>&reg;</sup> Cuba. Kaky<sup>&reg;</sup></small>
            <p>
                Facturación, nóminas y gestión en una plataforma local.<br>
                &copy; <?= $anio ?> Todos los derechos reservados.
            </p>
            <p class="footer-slogan">«Donde tu visión toma forma»</p>
        </div>

        <!-- Columna 2: Enlaces -->
        <nav class="footer-col" aria-label="Enlaces del pie">
            <h4><i class="fa-solid fa-compass" aria-hidden="true"></i> Explorar</h4>
            <ul class="footer-list">
                <li><a href="<?= $base ?>/index.php"><i class="fa-solid fa-wallet" aria-hidden="true"></i> Ecosistema Financiero</a></li>
                <li><a href="<?= $base ?>/nominas/"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a></li>
                <li><a href="<?= $base ?>/facturacion/index.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a></li>
                <li><a href="<?= $base ?>/costos/login.php" data-modulo-pendiente="Costos"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a></li>
                <li><a href="<?= $base ?>/inventarios/login.php" data-modulo-pendiente="Inventarios"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a></li>
                <li><a href="<?= $base ?>/administracion/index.php" data-modulo-pendiente="Administración"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a></li>
                <li><a href="<?= $base ?>/phpinfo/"><i class="fa-solid fa-server" aria-hidden="true"></i> Servidor Info PHP</a></li>
                <li><a href="<?= $base ?>/InstalarBD/InstalarBD.php"><i class="fa-solid fa-database" aria-hidden="true"></i> Instalador de BD</a></li>
            </ul>
        </nav>

        <!-- Columna 3: Contacto -->
        <div class="footer-col footer-contact">
            <h4><i class="fa-solid fa-headset" aria-hidden="true"></i> Contacto</h4>
            <p>
                <a href="mailto:kakycu@gmail.com"><i class="fa-solid fa-envelope" aria-hidden="true"></i> kakycu@gmail.com</a><br>
                <a href="tel:+5352712861"><i class="fa-solid fa-phone" aria-hidden="true"></i> +53 5 2712861</a><br>
                <small><i class="fa-solid fa-clock" aria-hidden="true"></i> Lun - Vie, 8:00 AM - 5:00 PM<br>Sábados: 9:00 AM - 1:00 PM</small>
            </p>
            <div class="footer-buttons">
                <button type="button" class="btn btn-glass btn-sm" id="irArriba">
                    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Ir al inicio
                </button>
                <a class="btn btn-primary btn-sm" href="<?= $base ?>/index.php">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Explorar subsistemas
                </a>
            </div>
        </div>

        <!-- Columna 4: Redes sociales y legal -->
        <div class="footer-col">
            <h4><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Síguenos</h4>
            <div class="social-links">
                <a class="social-link" href="https://www.facebook.com/unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                <a class="social-link" href="https://www.x.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)">
                    <!-- La marca X no viene en el build de Font Awesome del proyecto: se dibuja con su SVG oficial. -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/></svg>
                </a>
                <a class="social-link" href="https://www.linkedin.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                <a class="social-link" href="https://www.instagram.com/unicorniosoftcuba" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                <a class="social-link" href="https://www.youtube.com/@unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
                <a class="social-link" href="https://api.whatsapp.com/send?phone=5352712861" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
            </div>
            <h4 class="footer-legal-title"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Legal</h4>
            <p class="footer-legal-links">
                <a href="<?= $base ?>/terminos.php"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
                <a href="<?= $base ?>/privacidad.php"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
                <a href="<?= $base ?>/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
            </p>
        </div>
    </div>

    <div class="footer-bar">
        <div class="wrap">
            <span>&copy; <?= $anio ?> Entorno de Aplicaciones Web de UnicornioSoftware<sup>&reg;</sup> Cuba. Kaky<sup>&reg;</sup></span>
            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Plataforma local · v10.0.1</span>
        </div>
    </div>
</footer>

<!-- Botones flotantes: ir hasta arriba / ir hasta abajo -->
<div class="scroll-fabs">
    <button type="button" class="scroll-fab" id="btnScrollTop"
            title="Ir hasta arriba" aria-label="Ir hasta arriba de la página">
        <i class="fa-solid fa-angles-up"></i>
    </button>
    <button type="button" class="scroll-fab" id="btnScrollBottom"
            title="Ir hasta abajo" aria-label="Ir hasta abajo de la página">
        <i class="fa-solid fa-angles-down"></i>
    </button>
</div>

<script>
    // Cambio de tema claro / oscuro (mismo mecanismo que el panel phpinfo).
    (function () {
        var boton = document.getElementById('themeToggle');
        var raiz = document.documentElement;

        function sincronizar(tema) {
            var esDark = tema !== 'light';
            if (boton) {
                boton.setAttribute('aria-checked', esDark ? 'true' : 'false');
                boton.title = esDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
            }
        }

        sincronizar(raiz.getAttribute('data-bs-theme'));
        if (boton) {
            boton.addEventListener('click', function () {
                var actual = raiz.getAttribute('data-bs-theme') === 'light' ? 'light' : 'dark';
                var nuevo = actual === 'dark' ? 'light' : 'dark';
                raiz.setAttribute('data-bs-theme', nuevo);
                try { localStorage.setItem('theme', nuevo); } catch (e) {}
                sincronizar(nuevo);
            });
        }
    })();

    // Botón "Ir al inicio" del pie y botones flotantes arriba/abajo.
    (function () {
        var botonPie = document.getElementById('irArriba');
        var arriba = document.getElementById('btnScrollTop');
        var abajo = document.getElementById('btnScrollBottom');

        function irArriba() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        function irAbajo() {
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            window.scrollTo({ top: max, behavior: 'smooth' });
        }

        if (botonPie) { botonPie.addEventListener('click', irArriba); }
        if (arriba) { arriba.addEventListener('click', irArriba); }
        if (abajo) { abajo.addEventListener('click', irAbajo); }

        // Cada botón aparece solo cuando tiene sentido; de paso se alimenta la
        // barra de progreso de lectura de la parte superior.
        var progreso = document.getElementById('scrollProgress');
        function actualizar() {
            var y = window.scrollY || document.documentElement.scrollTop || 0;
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            if (arriba) { arriba.classList.toggle('is-visible', y > 140); }
            if (abajo) { abajo.classList.toggle('is-visible', y < max - 140); }
            if (progreso) {
                progreso.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + '%';
            }
        }
        window.addEventListener('scroll', actualizar, { passive: true });
        window.addEventListener('resize', actualizar);
        actualizar();
    })();

    // Aparición progresiva: si no hay soporte o el usuario pidió menos
    // movimiento, todo queda visible desde el principio.
    (function () {
        var elementos = document.querySelectorAll('.reveal');
        if (!elementos.length) { return; }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function mostrarTodo() {
            for (var i = 0; i < elementos.length; i++) {
                elementos[i].classList.add('is-in');
            }
        }

        if (reduce || !('IntersectionObserver' in window)) { mostrarTodo(); return; }

        document.documentElement.classList.add('js-reveal');
        var observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('is-in');
                    observador.unobserve(entrada.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
        for (var i = 0; i < elementos.length; i++) {
            observador.observe(elementos[i]);
        }
    })();

    // Las alertas de conexión se pueden cerrar con su X; el aviso vuelve a
    // aparecer al recargar mientras MySQL siga apagado.
    (function () {
        var botones = document.querySelectorAll('.alerta-bd-cerrar');
        for (var i = 0; i < botones.length; i++) {
            botones[i].addEventListener('click', function () {
                var alerta = this.closest('.alerta-bd');
                if (alerta) { alerta.remove(); }
            });
        }
    })();
</script>

<script>
    // ===== Intro animada: piezas del logo =====
    // Secuencia: cada pieza entra en su posición y se queda; al completarse el
    // logo aparece final.png con un destello transversal recortado por su
    // silueta. Es decorativa: se puede saltar y nunca debe bloquear la portada.
    (function () {
        var intro = document.getElementById('introAnim');
        if (!intro) { return; }
        var raiz = document.documentElement;
        var barra = document.getElementById('introBar');
        var saltar = document.getElementById('introSkip');
        var capas = intro.querySelectorAll('.intro-layer');
        var capaFinal = intro.querySelector('.intro-final');
        var pasos = capas.length + 1;
        var reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var terminado = false;
        var temporizadores = [];

        function esperar(fn, ms) {
            temporizadores.push(window.setTimeout(fn, ms));
        }

        function pintar(paso) {
            if (barra) { barra.style.width = Math.round((paso / pasos) * 100) + '%'; }
        }

        // Vuela el logo final hasta el logo del topbar mientras la intro se
        // desvanece, para que ambos queden alineados en el relevo.
        function volarAlLogo() {
            if (!capaFinal || !capaFinal.classList.contains('is-on')) { return; }
            var destino = document.querySelector('.site-header .brand-logo');
            if (!destino) { return; }
            var desde = capaFinal.getBoundingClientRect();
            var hasta = destino.getBoundingClientRect();
            if (!desde.width || !hasta.width) { return; }
            var escala = hasta.width / desde.width;
            var dx = (hasta.left + hasta.width / 2) - (desde.left + desde.width / 2);
            var dy = (hasta.top + hasta.height / 2) - (desde.top + desde.height / 2);
            capaFinal.style.transition = 'transform .65s cubic-bezier(.4, 0, .2, 1)';
            capaFinal.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(' + escala + ')';
        }

        function terminar() {
            if (terminado) { return; }
            terminado = true;
            volarAlLogo();
            for (var i = 0; i < temporizadores.length; i++) {
                window.clearTimeout(temporizadores[i]);
            }
            temporizadores = [];
            if (barra) { barra.style.width = '100%'; }
            intro.classList.add('is-out');
            raiz.classList.remove('intro-lock');
            window.setTimeout(function () {
                if (intro.parentNode) { intro.parentNode.removeChild(intro); }
            }, 900);
        }

        if (reducido || <?= $introSaltar ? 'true' : 'false' ?>) { terminar(); return; }

        function mostrarFinal() {
            if (terminado) { return; }
            pintar(pasos);
            intro.classList.add('is-final');
            if (capaFinal) { capaFinal.classList.add('is-on'); }
            esperar(function () {
                if (!terminado) { intro.classList.add('is-sheen'); }
            }, 1050);
            esperar(terminar, 4100);
        }

        function mostrarCapa(indice) {
            if (terminado) { return; }
            // Pausa larga tras la última pieza antes de revelar final.png.
            if (indice >= capas.length) { esperar(mostrarFinal, 1000); return; }
            var capa = capas[indice];
            capa.classList.add('is-on');
            pintar(indice + 1);
            esperar(function () { mostrarCapa(indice + 1); }, 820);
        }

        if (saltar) { saltar.addEventListener('click', terminar); }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { terminar(); }
        });

        // Red de seguridad: aunque falle un paso, la portada queda libre.
        esperar(terminar, 14000);
        esperar(function () { mostrarCapa(0); }, 220);
    })();

    // Al señalar una tarjeta de subsistema, su imagen pasa al fondo del card
    // destacado «Punto de entrada»; al salir se retira.
    (function () {
        var destacada = document.querySelector('.card.is-featured');
        if (!destacada) { return; }
        var tarjetas = document.querySelectorAll('[data-preview-img]');
        var descDestacada = destacada.querySelector('.card-desc');
        var textoOriginal = descDestacada ? descDestacada.textContent : '';

        function mostrar(url, texto) {
            destacada.style.setProperty('--featured-img', 'url("' + url + '")');
            destacada.classList.add('is-preview');
            if (descDestacada && texto) { descDestacada.textContent = texto; }
        }

        function limpiar() {
            destacada.classList.remove('is-preview');
            destacada.style.removeProperty('--featured-img');
            if (descDestacada) { descDestacada.textContent = textoOriginal; }
        }

        for (var i = 0; i < tarjetas.length; i++) {
            (function (tarjeta) {
                var url = tarjeta.getAttribute('data-preview-img');
                var desc = tarjeta.querySelector('.card-desc');
                var texto = desc ? desc.textContent : '';
                tarjeta.addEventListener('mouseenter', function () { mostrar(url, texto); });
                tarjeta.addEventListener('mouseleave', limpiar);
                tarjeta.addEventListener('focus', function () { mostrar(url, texto); });
                tarjeta.addEventListener('blur', limpiar);
            })(tarjetas[i]);
        }
    })();

    // Los enlaces del pie a módulos que aún no existen avisan en el sitio en
    // lugar de llevar a un 404. Al publicar el módulo, quitar el atributo
    // data-modulo-pendiente y el enlace funciona sin tocar nada más.
    (function () {
        var aviso = null;
        var temporizador = null;

        function mostrarAviso(nombre) {
            if (!aviso) {
                aviso = document.createElement('div');
                aviso.className = 'aviso-modulo';
                aviso.setAttribute('role', 'status');
                aviso.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i><span></span>';
                document.body.appendChild(aviso);
            }
            aviso.querySelector('span').textContent = 'El módulo ' + nombre + ' todavía no está disponible.';
            aviso.classList.add('is-on');
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(function () { aviso.classList.remove('is-on'); }, 4000);
        }

        var enlaces = document.querySelectorAll('[data-modulo-pendiente]');
        for (var i = 0; i < enlaces.length; i++) {
            enlaces[i].addEventListener('click', function (e) {
                e.preventDefault();
                mostrarAviso(this.getAttribute('data-modulo-pendiente'));
            });
        }
    })();
</script>

<?php if (function_exists('licencia_fingerprint_equipo')): ?>
<!-- ===== Activación / renovación de licencia, sin salir de la portada ===== -->
<div class="lic-overlay" id="licencia"<?= $licModalAbierto ? '' : ' hidden' ?>>
    <div class="lic-modal" role="dialog" aria-modal="true" aria-labelledby="licModalTitulo">
        <button type="button" class="lic-modal-x" data-lic-close aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="lic-modal-head">
            <span class="lic-modal-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
            <div>
                <h2 id="licModalTitulo">Activar o renovar licencia</h2>
                <p class="lic-modal-sub"><?= htmlspecialchars($licenciaEstadoTxt, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <?php if ($licOk): ?>
        <div class="lic-ok" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <div>
                <strong>Licencia activada correctamente.</strong>
                <p>Tipo: <b><?= htmlspecialchars($licOkTipo, ENT_QUOTES, 'UTF-8') ?></b> · Vence: <b><?= htmlspecialchars($licOkHasta, ENT_QUOTES, 'UTF-8') ?></b></p>
            </div>
        </div>
        <div class="lic-modal-actions" style="margin-top:1.1rem;">
            <a class="btn btn-primary" href="<?= $base ?>/nominas/login.php">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar al sistema
            </a>
            <button type="button" class="btn btn-glass" data-lic-close>Cerrar</button>
        </div>
        <?php else: ?>
        <?php if ($licError !== ''): ?>
        <div class="lic-msg lic-msg-error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?= $licError ?></span>
        </div>
        <?php elseif ($licAviso !== ''): ?>
        <div class="lic-msg <?= $licAvisoTipo === 'ok' ? 'lic-msg-ok' : 'lic-msg-error' ?>" role="status">
            <i class="fa-solid <?= $licAvisoTipo === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i>
            <span><?= $licAviso ?></span>
        </div>
        <?php endif; ?>

        <form class="lic-form" method="post" action="#licencia" autocomplete="off">
            <input type="hidden" name="licencia_accion" value="activar">
            <div class="lic-grid">
                <label class="lic-field">Nombre de Registro
                    <input type="text" name="nombre" value="<?= htmlspecialchars($licForm['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="120" placeholder="Empresa o instalación" required>
                </label>
                <label class="lic-field">Usuario del Registro
                    <input type="text" name="usuario" value="<?= htmlspecialchars($licForm['usuario'], ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="80" placeholder="Administrador responsable" required>
                </label>
            </div>
            <label class="lic-field">Llave Serial
                <input type="text" name="serial" id="licSerial" value="<?= htmlspecialchars($licForm['serial'], ENT_QUOTES, 'UTF-8') ?>"
                       maxlength="29" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" spellcheck="false" required>
            </label>
            <div class="lic-fp">
                <span>Código de este equipo:</span>
                <b><?= htmlspecialchars(licencia_fingerprint_equipo(), ENT_QUOTES, 'UTF-8') ?></b>
                <button type="button" class="btn btn-glass btn-sm" id="licCopiar">
                    <i class="fa-regular fa-copy"></i> Copiar
                </button>
            </div>
            <div class="lic-modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i> Activar licencia
                </button>
                <button type="button" class="btn btn-glass" data-lic-close>Cancelar</button>
            </div>
        </form>

        <div class="lic-solicitar">
            <button type="button" class="btn btn-glass" data-sol-open>
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar licencia por correo
            </button>
        </div>

        <div class="lic-alt">
            <details>
                <summary><i class="fa-solid fa-file-import" aria-hidden="true"></i> Importar desde un archivo .lic</summary>
                <form method="post" action="#licencia" enctype="multipart/form-data">
                    <input type="hidden" name="licencia_accion" value="examinar_archivo">
                    <input type="file" name="archivo_lic" accept=".lic" required onchange="this.form.submit()">
                </form>
            </details>
            <details>
                <summary><i class="fa-solid fa-file-signature" aria-hidden="true"></i> Pegar el texto de la licencia</summary>
                <form method="post" action="#licencia">
                    <input type="hidden" name="licencia_accion" value="examinar_texto">
                    <textarea name="licencia_texto" rows="3" spellcheck="false"
                              placeholder="Pegue aquí el texto de la licencia..."><?= htmlspecialchars($licForm['texto'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div>
                        <button type="submit" class="btn btn-glass btn-sm">
                            <i class="fa-solid fa-magnifying-glass"></i> Verificar y rellenar
                        </button>
                    </div>
                </form>
            </details>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Solicitud de licencia por correo, igual que en /servicios/nominas/licencia.php ===== -->
<div class="lic-overlay" id="solLicencia" hidden>
    <div class="lic-modal sol-modal" role="dialog" aria-modal="true" aria-labelledby="solTitulo">
        <button type="button" class="lic-modal-x" data-sol-close aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="lic-modal-head">
            <span class="lic-modal-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
            <div>
                <h2 id="solTitulo">Solicitar licencia por correo</h2>
                <p class="lic-modal-sub">Complete sus datos y el periodo de validez deseado. La licencia se envía al correo electrónico que registre.</p>
            </div>
        </div>

        <p class="sol-intro">
            Se enviará la solicitud con el <b>código de equipo</b> de esta PC, o con el de otro equipo
            si lo escribe con el lápiz, y la licencia será remitida al <b>correo electrónico</b> del solicitante.
        </p>

        <div class="lic-fp">
            <span>Código de este equipo:</span>
            <b><?= htmlspecialchars(licencia_formatear_fingerprint(licencia_fingerprint_machine()), ENT_QUOTES, 'UTF-8') ?></b>
        </div>

        <div class="lic-msg lic-msg-error" id="solMsg" role="alert" hidden>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span id="solMsgTexto"></span>
        </div>

        <form class="sol-form" id="solForm" autocomplete="off" novalidate>
            <div class="sol-grid">
                <label class="sol-field">Nombre
                    <input type="text" id="solNombre" name="nombre" maxlength="60" placeholder="Nombres">
                </label>
                <label class="sol-field">Apellidos
                    <input type="text" id="solApellidos" name="apellidos" maxlength="60" placeholder="Apellidos">
                </label>
                <label class="sol-field">Carné de identidad
                    <input type="text" id="solCi" name="ci" maxlength="11" inputmode="numeric" placeholder="11 dígitos">
                    <small class="sol-feedback" id="solCiFeedback"></small>
                </label>
                <label class="sol-field">Usuario
                    <input type="text" id="solUsuario" name="usuario" maxlength="60" placeholder="Usuario del sistema">
                </label>
                <label class="sol-field">Entidad
                    <input type="text" id="solEntidad" name="entidad" maxlength="120" placeholder="Empresa o entidad">
                </label>
                <label class="sol-field">Periodo de validez
                    <select id="solPeriodo" name="periodo">
                        <option value="">Seleccione un periodo</option>
                        <?php foreach (licencia_tipos() as $codigoTipo => $infoTipo): ?>
                        <option value="<?= htmlspecialchars($codigoTipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($infoTipo['nombre'] . ' (' . $codigoTipo . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="sol-field">
                    <span>Tipo de licencia</span>
                    <label class="sol-switch" for="solGenerica">
                        <input type="checkbox" id="solGenerica" name="generica" value="1">
                        <span class="sol-switch-track"></span>
                        <span id="solGenericaTxt">Específica</span>
                    </label>
                </div>
                <div class="sol-field">
                    <span>Código de equipo actual</span>
                    <div class="sol-huella-fila">
                        <input type="text" id="solHuellaInput" name="huella" maxlength="29"
                               value="<?= htmlspecialchars(licencia_formatear_fingerprint(licencia_fingerprint_machine()), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="#####-#####-#####-#####" spellcheck="false" autocapitalize="characters" disabled>
                        <button type="button" class="btn btn-glass btn-sm" id="solEditarHuella"
                                title="Escribir el código de otro equipo" aria-label="Escribir el código de otro equipo">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </button>
                    </div>
                    <small class="sol-feedback" id="solHuellaInputFeedback"></small>
                </div>
                <label class="sol-field">No. soporte WhatsApp
                    <input type="text" id="solWhatsapp" name="whatsapp" maxlength="20" inputmode="numeric"
                           placeholder="5359860773" spellcheck="false" autocomplete="off">
                    <small class="sol-feedback" id="solWhatsappFeedback"></small>
                </label>
                <label class="sol-field">Correo del solicitante
                    <input type="email" id="solEmail" name="email" maxlength="120" placeholder="correo@ejemplo.com" spellcheck="false" autocomplete="email">
                    <small class="sol-feedback" id="solEmailFeedback"></small>
                </label>
                <label class="sol-field sol-full">Email del proveedor de soporte técnico
                    <input type="email" id="solEmailSoporte" name="email_soporte" maxlength="120" placeholder="soporte@ejemplo.com" spellcheck="false" autocomplete="off">
                    <small class="sol-feedback" id="solEmailSoporteFeedback"></small>
                </label>
            </div>
            <div class="sol-actions">
                <button type="button" class="btn btn-primary" id="solEnviar">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar solicitud
                </button>
                <div class="sol-menu">
                    <button type="button" class="btn btn-glass" id="solCorreo" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Enviar con…
                    </button>
                    <div class="sol-menu-lista" id="solEnviarLista" hidden>
                        <button type="button" data-canal="correo"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Enviar con mi correo</button>
                        <button type="button" data-canal="whatsapp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Enviar con WhatsApp</button>
                        <button type="button" data-canal="gmail"><i class="fa-brands fa-google" aria-hidden="true"></i> Enviar con Gmail</button>
                    </div>
                </div>
                <button type="button" class="btn btn-glass" data-sol-close>Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Estado de la licencia ===== -->
<div class="lic-overlay" id="estadoLicencia" hidden>
    <div class="lic-modal" role="dialog" aria-modal="true" aria-labelledby="edoLicTitulo">
        <button type="button" class="lic-modal-x" data-estado-lic-close aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="lic-modal-head">
            <span class="lic-modal-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
            <div>
                <h2 id="edoLicTitulo">Estado de la licencia</h2>
                <p class="lic-modal-sub">
                    <span class="lic-estado-badge <?= $licEdoTono ?>">
                        <i class="fa-solid <?= $licEdoIcono ?>" aria-hidden="true"></i> <?= htmlspecialchars($licEdoNombre, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </p>
            </div>
        </div>

        <dl class="lic-estado-lista">
            <div class="lic-estado-fila">
                <dt>Nombre de registro</dt>
                <dd><?= $licEdoRegistro !== '' ? htmlspecialchars($licEdoRegistro, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Usuario del registro</dt>
                <dd><?= $licEdoUsuario !== '' ? htmlspecialchars($licEdoUsuario, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Tipo de licencia</dt>
                <dd><?= $licEdoTipo !== '' ? htmlspecialchars($licEdoTipo, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Activada el</dt>
                <dd><?= $licEdoActivada !== '' ? htmlspecialchars($licEdoActivada, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Vence</dt>
                <dd><?= $licEdoVence !== '' ? htmlspecialchars($licEdoVence, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Código de equipo</dt>
                <dd class="mono"><?= htmlspecialchars(licencia_fingerprint_equipo(), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        </dl>

        <div class="lic-modal-actions" style="margin-top:1.15rem;">
            <button type="button" class="btn btn-primary" data-estado-lic-ir="licencia">
                <i class="fa-solid fa-key" aria-hidden="true"></i> Activar o renovar
            </button>
            <button type="button" class="btn btn-glass" data-estado-lic-ir="solicitud">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar por correo
            </button>
            <button type="button" class="btn btn-glass" data-estado-lic-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('licencia');
    if (!overlay) { return; }

    function abrir() {
        overlay.hidden = false;
        document.body.classList.add('lic-abierto');
        var campo = overlay.querySelector('input[name="nombre"]');
        if (campo) { window.setTimeout(function () { campo.focus(); }, 40); }
    }
    function cerrar() {
        overlay.hidden = true;
        document.body.classList.remove('lic-abierto');
    }

    var abridores = document.querySelectorAll('[data-lic-open]');
    for (var i = 0; i < abridores.length; i++) {
        abridores[i].addEventListener('click', function (e) { e.preventDefault(); abrir(); });
    }
    var cierres = overlay.querySelectorAll('[data-lic-close]');
    for (var j = 0; j < cierres.length; j++) {
        cierres[j].addEventListener('click', cerrar);
    }
    overlay.addEventListener('mousedown', function (e) {
        // El modal no se cierra al pulsar fuera: solo con la X, Cancelar o Escape.
        if (e.target === overlay) { e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { cerrar(); }
    });
    if (!overlay.hidden) { document.body.classList.add('lic-abierto'); }

    // Se expone para el panel «Estado de la licencia».
    window.licAbrir = abrir;

    // La llave serial se agrupa sola en 5 bloques de 5 caracteres.
    var serial = document.getElementById('licSerial');
    if (serial) {
        serial.addEventListener('input', function () {
            var valor = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 25);
            var grupos = valor.match(/.{1,5}/g);
            this.value = grupos ? grupos.join('-') : '';
        });
    }

    var copiar = document.getElementById('licCopiar');
    if (copiar) {
        copiar.addEventListener('click', function () {
            var texto = <?= json_encode(licencia_fingerprint_equipo()) ?>;
            function listo() {
                copiar.innerHTML = '<i class="fa-solid fa-check"></i> Copiado';
                window.setTimeout(function () {
                    copiar.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar';
                }, 1600);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(listo);
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = texto;
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); listo(); } catch (err) {}
                document.body.removeChild(tmp);
            }
        });
    }
})();
</script>

<script>
/* Solicitud de licencia por correo desde la portada.
 * Replica el comportamiento de /servicios/nominas/licencia.php: valida los
 * campos en el navegador, envía por el SMTP del sistema al endpoint
 * ?solicitar_licencia=1 y ofrece los canales externos (correo, WhatsApp, Gmail). */
(function () {
    var overlay = document.getElementById('solLicencia');
    if (!overlay) { return; }

    var endpoint  = <?= json_encode($base . '/nominas/licencia.php') ?>;
    var licOverlay = document.getElementById('licencia');
    var btnAbrir  = document.querySelectorAll('[data-sol-open]');
    var btnEnviar = document.getElementById('solEnviar');
    var btnCorreo = document.getElementById('solCorreo');
    var listaEnvio = document.getElementById('solEnviarLista');
    var huellaInput = document.getElementById('solHuellaInput');
    var btnEditar = document.getElementById('solEditarHuella');
    var cajaMsg   = document.getElementById('solMsg');
    var textoMsg  = document.getElementById('solMsgTexto');

    var CAMPOS = [
        { id: 'solNombre',    clave: 'nombre' },
        { id: 'solApellidos', clave: 'apellidos' },
        { id: 'solCi',        clave: 'ci' },
        { id: 'solEmail',     clave: 'email' },
        { id: 'solUsuario',   clave: 'usuario' },
        { id: 'solEntidad',   clave: 'entidad' },
        { id: 'solPeriodo',   clave: 'periodo' }
    ];

    // Correos y WhatsApp tomados de Configuración del sistema. Se piden al abrir
    // el modal para no cargar la base de datos al dibujar la portada.
    var correosConfig = { destino: '', solicitante: '', whatsapp: '', resuelto: false };

    function porId(id) { return document.getElementById(id); }

    function avisar(texto) {
        textoMsg.innerHTML = texto;
        cajaMsg.hidden = false;
        cajaMsg.classList.add('lic-msg-error');
        cajaMsg.classList.remove('lic-msg-ok');
    }
    function celebrar(texto) {
        textoMsg.innerHTML = texto;
        cajaMsg.hidden = false;
        cajaMsg.classList.add('lic-msg-ok');
        cajaMsg.classList.remove('lic-msg-error');
    }
    function limpiarAviso() { cajaMsg.hidden = true; textoMsg.innerHTML = ''; }

    function aplicarCorreosConfig() {
        if (correosConfig.destino) {
            porId('solEmailSoporte').value = correosConfig.destino;
        }
        if (correosConfig.solicitante) {
            porId('solEmail').value = correosConfig.solicitante;
        }
        if (correosConfig.whatsapp) {
            porId('solWhatsapp').value = correosConfig.whatsapp;
        }
    }

    function cargarCorreos() {
        aplicarCorreosConfig();
        if (correosConfig.resuelto) { return; }
        fetch(endpoint + '?solicitar_licencia_destino=1')
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.destino) {
                    correosConfig.destino     = j.destino;
                    correosConfig.solicitante = j.solicitante || '';
                    correosConfig.whatsapp    = j.whatsapp || '';
                    correosConfig.resuelto    = true;
                    aplicarCorreosConfig();
                }
            })
            .catch(function () { /* se conservan los valores escritos */ });
    }

    function abrir() {
        if (licOverlay) { licOverlay.hidden = true; }
        overlay.hidden = false;
        document.body.classList.add('lic-abierto');
        limpiarAviso();
        var campo = porId('solNombre');
        if (campo) { window.setTimeout(function () { campo.focus(); }, 40); }
        cargarCorreos();
    }

    function cerrar() {
        overlay.hidden = true;
        limpiarAviso();
        cerrarMenuEnvio();
        if (licOverlay) {
            licOverlay.hidden = false;
            document.body.classList.add('lic-abierto');
        } else {
            document.body.classList.remove('lic-abierto');
        }
    }

    for (var i = 0; i < btnAbrir.length; i++) {
        btnAbrir[i].addEventListener('click', function (e) { e.preventDefault(); abrir(); });
    }
    var cierres = overlay.querySelectorAll('[data-sol-close]');
    for (var j = 0; j < cierres.length; j++) {
        cierres[j].addEventListener('click', cerrar);
    }
    // Sin cierre al pulsar fuera del modal.
    overlay.addEventListener('mousedown', function (e) {
        if (e.target === overlay) { e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!listaEnvio.hidden) { cerrarMenuEnvio(); return; }
            if (!overlay.hidden) { cerrar(); }
        }
    });

    // --- Validaciones, con el mismo criterio que el servidor ---
    function validarCI(ci) {
        ci = String(ci).replace(/[\s-]/g, '');
        if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: 'Debe contener 11 dígitos numéricos' };
        var anio = ci.substr(0, 2);
        var mes  = ci.substr(2, 2);
        var dia  = ci.substr(4, 2);
        if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes de nacimiento inválido' };
        var diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var maxDias = diasPorMes[parseInt(mes, 10) - 1];
        if (parseInt(mes, 10) === 2 && parseInt(dia, 10) === 29) {
            var anioLargo = parseInt(anio, 10) < 30 ? 2000 + parseInt(anio, 10) : 1900 + parseInt(anio, 10);
            var bisiesto = (anioLargo % 4 === 0 && anioLargo % 100 !== 0) || (anioLargo % 400 === 0);
            if (!bisiesto) return { valido: false, mensaje: '29/02 solo es válido en años bisiestos' };
        } else if (dia < '01' || parseInt(dia, 10) > maxDias) {
            return { valido: false, mensaje: 'Día de nacimiento inválido' };
        }
        var digitoGenero = parseInt(ci.charAt(9), 10);
        var genero    = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
        var anioTexto = parseInt(anio, 10) < 30 ? '20' + anio : '19' + anio;
        return {
            valido: true,
            mensaje: 'CI válido | ' + genero + ' | Nac: ' + dia + '/' + mes + '/' + anioTexto,
            genero: genero,
            icono: digitoGenero % 2 === 0 ? 'fa-mars' : 'fa-venus'
        };
    }

    var RE_EMAIL  = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
    var RE_HUELLA = /^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/;

    function validarEmail(email) {
        var v = String(email).trim();
        if (v === '') return { valido: false, mensaje: 'Ingrese su correo electrónico' };
        if (v.indexOf('..') !== -1 || v.charAt(0) === '.' || v.charAt(v.length - 1) === '.') {
            return { valido: false, mensaje: 'Formato de correo electrónico inválido' };
        }
        if (!RE_EMAIL.test(v)) return { valido: false, mensaje: 'Formato de correo electrónico inválido' };
        return { valido: true, mensaje: 'Correo electrónico válido' };
    }

    function normalizarWhatsapp(valor) {
        var n = (valor || '').replace(/\D/g, '');
        if (n.indexOf('00') === 0) { n = n.slice(2); }
        if (n.length === 8 && n.slice(0, 2) !== '53') { n = '53' + n; }
        return (n.length >= 8 && n.length <= 15) ? n : '';
    }

    function mostrarFeedback(id, resultado) {
        var caja = porId(id + 'Feedback');
        if (!caja) { return; }
        caja.classList.remove('ok', 'mal');
        if (!resultado) { caja.innerHTML = ''; return; }
        if (resultado.valido) {
            caja.classList.add('ok');
            caja.innerHTML = '<i class="fa-solid fa-circle-check"></i> '
                + (resultado.icono ? '<i class="fa-solid ' + resultado.icono + '"></i> ' : '')
                + resultado.mensaje;
        } else {
            caja.classList.add('mal');
            caja.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + resultado.mensaje;
        }
    }

    function limpiarErrores() {
        for (var k = 0; k < CAMPOS.length; k++) {
            porId(CAMPOS[k].id).classList.remove('error');
            mostrarFeedback(CAMPOS[k].id, null);
        }
        huellaInput.classList.remove('error');
        mostrarFeedback('solHuellaInput', null);
        porId('solWhatsapp').classList.remove('error');
        mostrarFeedback('solWhatsapp', null);
        porId('solEmailSoporte').classList.remove('error');
        mostrarFeedback('solEmailSoporte', null);
        limpiarAviso();
    }

    for (var c = 0; c < CAMPOS.length; c++) {
        porId(CAMPOS[c].id).addEventListener('input', function () { this.classList.remove('error'); });
    }

    porId('solCi').addEventListener('input', function () {
        var digitos = this.value.replace(/\D/g, '').slice(0, 11);
        if (this.value !== digitos) { this.value = digitos; }
        mostrarFeedback('solCi', digitos.length === 11 ? validarCI(digitos) : null);
    });

    porId('solEmail').addEventListener('input', function () {
        var v = this.value.trim();
        mostrarFeedback('solEmail', (v === '' || v.indexOf('@') === -1) ? null : validarEmail(v));
    });

    porId('solEmailSoporte').addEventListener('input', function () {
        this.classList.remove('error');
        mostrarFeedback('solEmailSoporte', null);
    });

    porId('solWhatsapp').addEventListener('input', function () {
        var v = (this.value || '').trim();
        if (v !== '' && normalizarWhatsapp(v) !== '') {
            this.classList.remove('error');
            mostrarFeedback('solWhatsapp', null);
        }
    });

    // --- Tipo de licencia y código de equipo ---
    var chkGenerica = porId('solGenerica');
    var huellaEditando = false;
    var huellaMaquina = huellaInput.value;

    function aplicarEstadoHuella() {
        var generica = !!chkGenerica.checked;
        btnEditar.disabled = generica;
        huellaInput.disabled = generica ? true : !huellaEditando;
        btnEditar.title = generica
            ? 'No se modifica con licencia genérica'
            : (huellaEditando ? 'Restaurar el código de esta PC' : 'Escribir el código de otro equipo');
    }

    function aplicarTipo() {
        porId('solGenericaTxt').textContent = chkGenerica.checked ? 'Genérica' : 'Específica';
        aplicarEstadoHuella();
    }
    chkGenerica.addEventListener('change', aplicarTipo);
    aplicarEstadoHuella();

    huellaInput.addEventListener('input', function () {
        if (this.disabled) { return; }
        var limpio = (this.value.match(/[A-Za-z0-9]/g) || []).join('').toUpperCase().slice(0, 20);
        this.value = limpio.replace(/(.{5})(?=.)/g, '$1-');
    });

    btnEditar.addEventListener('click', function () {
        if (huellaEditando) {
            huellaEditando = false;
            huellaInput.value = huellaMaquina;
        } else {
            huellaEditando = true;
            huellaInput.classList.remove('error');
            mostrarFeedback('solHuellaInput', null);
        }
        aplicarEstadoHuella();
        if (huellaEditando) { huellaInput.focus(); huellaInput.select(); }
    });

    // --- Recolección y envío ---
    function prepararSolicitud() {
        limpiarErrores();

        var valores = {};
        var faltan = [];
        for (var k = 0; k < CAMPOS.length; k++) {
            var campo = porId(CAMPOS[k].id);
            var valor = (campo.value || '').trim();
            valores[CAMPOS[k].clave] = valor;
            if (valor === '') {
                campo.classList.add('error');
                faltan.push(campo);
            }
        }
        if (faltan.length > 0) {
            avisar('Complete todos los campos para enviar la solicitud.');
            faltan[0].focus();
            return null;
        }

        var ci = porId('solCi');
        var resCI = validarCI(ci.value);
        if (!resCI.valido) {
            ci.classList.add('error');
            mostrarFeedback('solCi', resCI);
            avisar('Carné de identidad inválido: ' + resCI.mensaje + '.');
            ci.focus();
            return null;
        }

        var campoWa = porId('solWhatsapp');
        if (campoWa.value.trim() !== '' && normalizarWhatsapp(campoWa.value) === '') {
            campoWa.classList.add('error');
            mostrarFeedback('solWhatsapp', { valido: false, mensaje: 'Número de WhatsApp inválido' });
            avisar('Número de WhatsApp inválido: debe tener entre 8 y 15 dígitos, con el prefijo del país.');
            campoWa.focus();
            return null;
        }

        var mail = porId('solEmail');
        var resMail = validarEmail(mail.value);
        if (!resMail.valido) {
            mail.classList.add('error');
            mostrarFeedback('solEmail', resMail);
            avisar('Correo electrónico inválido: ' + resMail.mensaje + '.');
            mail.focus();
            return null;
        }

        var mailSoporte = porId('solEmailSoporte');
        if (mailSoporte.value.trim() !== '') {
            var resSoporte = validarEmail(mailSoporte.value);
            if (!resSoporte.valido) {
                mailSoporte.classList.add('error');
                mostrarFeedback('solEmailSoporte', resSoporte);
                avisar('Correo del proveedor inválido: ' + resSoporte.mensaje + '.');
                mailSoporte.focus();
                return null;
            }
            mostrarFeedback('solEmailSoporte', resSoporte);
        }

        var huella = (huellaInput.value || '').trim().toUpperCase();
        if (huella !== '' && !RE_HUELLA.test(huella)) {
            huellaInput.classList.add('error');
            mostrarFeedback('solHuellaInput', { valido: false, mensaje: 'Formato de código de equipo inválido' });
            avisar('Código de equipo inválido: debe tener el formato #####-#####-#####-#####.');
            huellaInput.focus();
            return null;
        }

        valores.ci            = ci.value.replace(/\D/g, '');
        valores.email         = mail.value.trim();
        valores.email_soporte = mailSoporte.value.trim();
        valores.generica      = chkGenerica.checked ? '1' : '0';
        valores.huella        = huella;
        return valores;
    }

    btnEnviar.addEventListener('click', function () {
        var datos = prepararSolicitud();
        if (!datos) { return; }

        var fd = new FormData();
        for (var k = 0; k < CAMPOS.length; k++) { fd.append(CAMPOS[k].clave, datos[CAMPOS[k].clave]); }
        fd.append('email_soporte', datos.email_soporte);
        fd.append('huella', datos.huella);
        fd.append('generica', datos.generica);

        var textoBoton = btnEnviar.innerHTML;
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

        fetch(endpoint + '?solicitar_licencia=1', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp && resp.ok) {
                    celebrar(resp.mensaje || 'Su solicitud fue enviada.');
                    porId('solForm').reset();
                    huellaInput.value = huellaMaquina;
                    huellaEditando = false;
                    aplicarTipo();
                } else {
                    var errores = (resp && resp.errores) ? resp.errores : {};
                    for (var clave in errores) {
                        if (!Object.prototype.hasOwnProperty.call(errores, clave)) { continue; }
                        for (var m = 0; m < CAMPOS.length; m++) {
                            if (CAMPOS[m].clave === clave) { porId(CAMPOS[m].id).classList.add('error'); }
                        }
                    }
                    if (errores.email_soporte) {
                        porId('solEmailSoporte').classList.add('error');
                        mostrarFeedback('solEmailSoporte', { valido: false, mensaje: errores.email_soporte });
                    }
                    if (errores.huella) {
                        huellaEditando = true;
                        huellaInput.disabled = false;
                        huellaInput.classList.add('error');
                        mostrarFeedback('solHuellaInput', { valido: false, mensaje: errores.huella });
                    }
                    avisar((resp && resp.mensaje) ? resp.mensaje : 'Ocurrió un error inesperado.');
                }
            })
            .catch(function () {
                avisar('No se pudo contactar al servidor. Verifique su conexión e intente nuevamente.');
            })
            .then(function () {
                btnEnviar.disabled = false;
                btnEnviar.innerHTML = textoBoton;
            });
    });

    // --- Canales externos: correo local, WhatsApp y Gmail ---
    function destinoActual() {
        return (porId('solEmailSoporte').value || '').trim()
            || (correosConfig.destino || '').trim();
    }

    function whatsappActual() {
        return normalizarWhatsapp(porId('solWhatsapp').value)
            || normalizarWhatsapp(correosConfig.whatsapp);
    }

    function construirMensaje(d) {
        var sel = porId('solPeriodo');
        var periodo = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : d.periodo;
        var linea = new Array(41).join('=');
        var resCI = validarCI(d.ci);
        var sexo = (resCI && resCI.valido && resCI.genero) ? resCI.genero : '';
        var cuerpo = [
            'SOLICITUD DE LICENCIA SiGesNom',
            linea,
            '',
            'Nombre:             ' + d.nombre,
            'Apellidos:          ' + d.apellidos,
            'Carne de identidad: ' + d.ci,
            'Sexo:                ' + sexo,
            'Correo electronico: ' + d.email,
            'Usuario:            ' + d.usuario,
            'Entidad:            ' + d.entidad,
            'Periodo de validez: ' + periodo,
            'Codigo de equipo:   ' + d.huella,
            'Tipo de Licencia:   ' + (d.generica === '1' ? 'Generica' : 'Especifica')
        ];
        cuerpo.push('', linea);
        return {
            asunto: 'Solicitud de licencia - ' + d.entidad + ' (' + periodo + ')',
            texto: cuerpo.join('\n')
        };
    }

    function abrirEnlace(url) {
        var enlace = document.createElement('a');
        enlace.href = url;
        enlace.target = '_blank';
        enlace.rel = 'noopener noreferrer';
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
    }

    function enviarPor(canal) {
        cerrarMenuEnvio();
        var d = prepararSolicitud();
        if (!d) { return; }
        var m = construirMensaje(d);

        if (canal === 'whatsapp') {
            var numero = whatsappActual();
            if (numero === '') {
                avisar('WhatsApp no está configurado en el sistema.');
                return;
            }
            abrirEnlace('https://api.whatsapp.com/send?phone=' + numero + '&text=' + encodeURIComponent(m.texto));
            return;
        }
        var destino = destinoActual();
        if (destino === '') {
            avisar('No se pudo determinar el correo del proveedor de soporte.');
            return;
        }
        if (canal === 'gmail') {
            abrirEnlace('https://mail.google.com/mail/?view=cm&to=' + encodeURIComponent(destino)
                + '&su=' + encodeURIComponent(m.asunto)
                + '&body=' + encodeURIComponent(m.texto));
            return;
        }
        abrirEnlace('mailto:' + encodeURI(destino)
            + '?subject=' + encodeURIComponent(m.asunto)
            + '&body=' + encodeURIComponent(m.texto));
    }

    function abrirMenuEnvio() {
        listaEnvio.hidden = false;
        btnCorreo.setAttribute('aria-expanded', 'true');
    }
    function cerrarMenuEnvio() {
        if (!listaEnvio || listaEnvio.hidden) { return; }
        listaEnvio.hidden = true;
        btnCorreo.setAttribute('aria-expanded', 'false');
    }

    btnCorreo.addEventListener('click', function (e) {
        e.stopPropagation();
        if (listaEnvio.hidden) { abrirMenuEnvio(); } else { cerrarMenuEnvio(); }
    });
    listaEnvio.addEventListener('click', function (e) {
        var item = e.target.closest('.sol-menu-lista button');
        if (!item) { return; }
        e.preventDefault();
        enviarPor(item.getAttribute('data-canal'));
    });
    document.addEventListener('click', function (e) {
        if (!btnCorreo.parentNode.contains(e.target)) { cerrarMenuEnvio(); }
    });

    // Se expone para el panel «Estado de la licencia».
    window.licSolicitar = abrir;
})();
</script>

<script>
/* Panel «Estado de la licencia»: resume la licencia guardada en el equipo y
 * lleva a los dos trámites que ya existen en la portada. */
(function () {
    var overlay = document.getElementById('estadoLicencia');
    if (!overlay) { return; }

    function abrirEstado() {
        overlay.hidden = false;
        document.body.classList.add('lic-abierto');
    }
    function cerrarEstado() {
        overlay.hidden = true;
        if (!document.querySelector('.lic-overlay:not([hidden])')) {
            document.body.classList.remove('lic-abierto');
        }
    }

    var abridores = document.querySelectorAll('[data-estado-lic-open]');
    for (var i = 0; i < abridores.length; i++) {
        abridores[i].addEventListener('click', function (e) { e.preventDefault(); abrirEstado(); });
    }
    var cierres = overlay.querySelectorAll('[data-estado-lic-close]');
    for (var j = 0; j < cierres.length; j++) {
        cierres[j].addEventListener('click', cerrarEstado);
    }
    // Sin cierre por clic fuera, igual que el resto de modales.
    overlay.addEventListener('mousedown', function (e) {
        if (e.target === overlay) { e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { cerrarEstado(); }
    });

    var irA = overlay.querySelectorAll('[data-estado-lic-ir]');
    for (var k = 0; k < irA.length; k++) {
        irA[k].addEventListener('click', function () {
            var destino = this.getAttribute('data-estado-lic-ir');
            cerrarEstado();
            if (destino === 'solicitud' && typeof window.licSolicitar === 'function') {
                window.licSolicitar();
            } else if (typeof window.licAbrir === 'function') {
                window.licAbrir();
            }
        });
    }
})();
</script>
<?php endif; ?>
</body>
</html>
