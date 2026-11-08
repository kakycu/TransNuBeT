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
    ],
    [
        'titulo'  => 'Facturación Electrónica',
        'texto'   => 'Emisión de facturas, clientes, servicios y reportes de cierre con control de usuarios.',
        'icono'   => 'fa-solid fa-file-invoice-dollar',
        'url'     => $base . '/facturacion/index.php',
        'etiqueta'=> 'Módulo de facturación',
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
    .hero-note {
        display: flex; align-items: center; gap: .45rem;
        margin-top: 1.25rem; color: var(--subtle); font-size: .8rem;
    }
    .hero-note i { color: var(--mint); }

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
</style>
</head>
<body>
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
            <a href="#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
            <a href="#servicios"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Servicios</a>
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
            <a class="btn btn-primary btn-sm" href="<?= $base ?>/index.php" title="Entrar al sistema">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> <span class="btn-texto">Entrar al sistema</span>
            </a>
        </div>
    </div>
    <!-- En pantallas estrechas los enlaces del navbar no caben: pasan a una
         fila de píldoras desplazable dentro del propio header fijo. -->
    <div class="wrap">
        <nav class="mobile-nav" aria-label="Secciones">
            <a href="#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
            <a href="#servicios"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Servicios</a>
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
                <h1>El ecosistema de gestión <span class="text-gradient">que impulsa tu operación</span></h1>
                <p class="lead">
                    Facturación, nóminas, costos e inventarios en una plataforma que corre en tu propia red.
                    Elige un módulo del ecosistema y empieza a trabajar: los datos no salen de tu equipo.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#ecosistema">
                        <i class="fa-solid fa-layer-group"></i> Explorar el ecosistema
                    </a>
                    <a class="btn btn-glass" href="<?= $base ?>/phpinfo/">
                        <i class="fa-solid fa-server"></i> Estado del servidor
                    </a>
                </div>
                <p class="hero-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Instalación local · sin dependencia de la nube · respaldo periódico
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
                <a class="card reveal<?= !empty($item['destacada']) ? ' is-featured' : '' ?>" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($item['destacada'])): ?>
                        <span class="featured-flag"><i class="fa-solid fa-star" aria-hidden="true"></i> Punto de entrada</span>
                    <?php endif; ?>
                    <span class="card-icon"><i class="<?= htmlspecialchars($item['icono'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                    <h3><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($item['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($item['destacada'])): ?>
                        <ul class="card-chips">
                            <li><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</li>
                            <li><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</li>
                            <li><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Reportes</li>
                            <li><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</li>
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
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Iniciar sesión
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
</body>
</html>
