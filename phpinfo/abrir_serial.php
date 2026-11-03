<?php
/* Lanzador local de Serial Generador (GUI / TUI) */

$modos = [
    'gui' => [
        'etiqueta' => 'GUI (aplicación de escritorio)',
        'ruta'     => dirname(__DIR__, 2) . '/herramientas/Serial Generator/VB6/Licencia_SISGESNOM/Licencia_SISGESNOM.exe',
    ],
    'tui' => [
        'etiqueta' => 'TUI (terminal)',
        'ruta'     => dirname(__DIR__, 2) . '/herramientas/Serial Generator/Terminal/Licencia_SISGESNOM.cmd',
    ],
];

$esJson = (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json');
$modo   = strtolower(trim((string)($_GET['modo'] ?? '')));

function serial_responder(bool $esJson, int $codigo, bool $ok, string $mensaje, string $etiqueta = '', string $ruta = ''): void
{
    http_response_code($codigo);

    if ($esJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ok' => $ok, 'mensaje' => $mensaje, 'etiqueta' => $etiqueta],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    $icono = $ok ? 'fa-circle-check' : 'fa-circle-exclamation';
    $color = $ok ? '#22c55e' : '#ef4444';
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Serial Generador · <?= htmlspecialchars($etiqueta !== '' ? $etiqueta : 'Lanzador', ENT_QUOTES, 'UTF-8') ?></title>
<link href="../css/font-awesome6.4.0/css/all.min.css" rel="stylesheet">
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: #0d1117; color: #e6edf3; padding: 1.5rem;
        font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
    }
    .box {
        max-width: 520px; width: 100%; background: #161b22; border: 1px solid #30363d;
        border-radius: 14px; padding: 2rem 1.6rem; text-align: center;
        box-shadow: 0 18px 40px -20px rgba(0, 0, 0, .8);
    }
    .box i.icon { font-size: 2.6rem; color: <?= $color ?>; }
    h1 { font-size: 1.2rem; margin: 1rem 0 .4rem; }
    p { margin: .35rem 0; color: #8b949e; font-size: .92rem; line-height: 1.45; }
    code {
        display: inline-block; margin-top: .8rem; padding: .45rem .6rem; border-radius: 8px;
        background: #0b0f14; border: 1px solid #30363d; color: #8b949e;
        font-size: .74rem; word-break: break-all; text-align: left;
    }
    a.btn {
        display: inline-flex; align-items: center; gap: .5rem; margin-top: 1.4rem;
        padding: .55rem 1.1rem; border-radius: 8px; text-decoration: none;
        background: #3b82f6; color: #fff; font-weight: 600; font-size: .9rem;
    }
    a.btn:hover { background: #2f6fe0; }
</style>
</head>
<body>
    <div class="box">
        <i class="fa-solid <?= $icono ?> icon"></i>
        <h1><?= htmlspecialchars($etiqueta !== '' ? 'Serial Generador · ' . $etiqueta : 'Serial Generador', ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($ruta !== ''): ?>
            <code><?= htmlspecialchars($ruta, ENT_QUOTES, 'UTF-8') ?></code>
        <?php endif; ?>
        <a class="btn" href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver al panel</a>
    </div>
</body>
</html>
    <?php
    exit;
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    serial_responder($esJson, 403, false, 'Solo se puede lanzar desde este equipo (localhost).');
}

if (!isset($modos[$modo])) {
    serial_responder($esJson, 400, false, 'Modo no válido. Use WEB, GUI o TUI.');
}

$etiqueta = $modos[$modo]['etiqueta'];
$ruta     = $modos[$modo]['ruta'];

if (!is_file($ruta)) {
    serial_responder($esJson, 404, false, 'No se encontró el archivo de la herramienta.', $etiqueta, $ruta);
}

// --- Lanzamiento con traída al frente (PowerShell) -----------------------
// start "" deja la ventana detrás del navegador; lanzar_serial.ps1 abre el
// proceso, espera su ventana y la pone en primer plano.
$lanzador = __DIR__ . '/lanzar_serial.ps1';
$lanzado  = false;
$ok       = false;
$estado   = '';
$frente   = false;
$detalle  = '';
$salida   = '';

if (is_file($lanzador) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $comando = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden'
             . ' -File ' . escapeshellarg($lanzador)
             . ' -Modo ' . escapeshellarg($modo);

    $lineas = [];
    $codigo = 1;
    @exec($comando, $lineas, $codigo);
    $salida = trim(implode("\n", $lineas));

    if ($salida !== '' && preg_match('/\{.*\}\s*$/s', $salida, $m)) {
        $datos = json_decode($m[0], true);
        if (is_array($datos)) {
            $lanzado = true;
            $estado  = (string)($datos['estado'] ?? '');
            $frente  = !empty($datos['frente']);
            $detalle = (string)($datos['mensaje'] ?? '');
            $ok      = !empty($datos['ok']);
        }
    }
}

// Reserva: si PowerShell no está disponible, se usa el método clásico.
if (!$lanzado) {
    $extension = strtolower((string)pathinfo($ruta, PATHINFO_EXTENSION));
    $comando   = 'start "" /D ' . escapeshellarg(dirname($ruta)) . ' ' . escapeshellarg($ruta);

    if (in_array($extension, ['cmd', 'bat'], true)) {
        $comando = 'start "" /D ' . escapeshellarg(dirname($ruta)) . ' cmd /k ' . escapeshellarg($ruta);
    }

    $handle = @popen($comando, 'r');
    if (is_resource($handle)) {
        pclose($handle);
        $lanzado = true;
        $ok      = true;
        $estado  = 'abierta';
        $detalle = $modo === 'gui'
            ? 'La aplicación GUI se está abriendo (sin control de primer plano).'
            : 'Se abrió una ventana de terminal con la TUI (sin control de primer plano).';
    }
}

if (!$lanzado) {
    serial_responder($esJson, 500, false, 'No se pudo iniciar el proceso. Verifique los permisos de Apache.', $etiqueta, $ruta);
}

if (empty($ok)) {
    $mensajeError = $detalle !== ''
        ? $detalle
        : 'El lanzador no pudo abrir la ventana.';
    serial_responder($esJson, 500, false, $mensajeError, $etiqueta, $ruta);
}

$mensaje = $detalle !== ''
    ? $detalle
    : ($modo === 'gui'
        ? 'La aplicación GUI se abrió en este equipo.'
        : 'Se abrió la ventana de terminal con la TUI.');

// 'frente' queda en la respuesta para que el panel pueda avisar si Windows
// bloqueó el primer plano (política de foco).
if ($esJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(
        [
            'ok'      => true,
            'estado'  => $estado,
            'frente'  => $frente,
            'mensaje' => $mensaje,
            'etiqueta' => $etiqueta,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

serial_responder($esJson, 200, true, $mensaje, $etiqueta, $ruta);
