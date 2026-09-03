<?php
/* Formulario de contacto publico - envio por Gmail SMTP con plantilla HTML.
   Estilo Windows Dark. El nombre de la empresa viene de la BD. */
declare(strict_types=1);
session_start();
/* Limite de ejecucion a 600s: el envio SMTP con adjuntos puede tardar
   y en Windows el contador corre en tiempo real (incluye esperas del socket). */
@ini_set('max_execution_time', '600');
@set_time_limit(600);

/* ---------- Nombre de la empresa desde la base de datos ---------- */
$nombreEmpresa = '';
if (is_file(__DIR__ . '/nominas/config.php')) {
    require_once __DIR__ . '/nominas/config.php';
    try {
        $pdoContacto = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $q = $pdoContacto->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'nombre_empresa' LIMIT 1");
        $q->execute();
        $v = $q->fetchColumn();
        if ($v !== false && trim((string)$v) !== '') $nombreEmpresa = trim((string)$v);
    } catch (Throwable $e) { /* sin BD se usa el respaldo */ }
}
if ($nombreEmpresa === '') $nombreEmpresa = 'Soporte';

$correoDisponible = is_file(__DIR__ . '/correo_privado.php');
$mensaje = null;
$exito = false;

/* ---------- Validación de archivos adjuntos ----------
   Formatos permitidos: png, jpg, jpeg, gif, pdf, txt, xls, xlsx, doc, docx, csv, zip, rar.
   Devuelve [lista de adjuntos válidos, texto de error ('' si todo bien)]. */
function validarAdjuntosContacto(): array {
    $permitidas  = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'txt', 'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx', 'csv', 'zip', 'rar'];
    $maxArchivo  = 10 * 1024 * 1024;   /* 10 MB por archivo */
    /* Gmail admite 25 MB por mensaje DESPUES de codificar base64 (~+37%);
       18 MB reales ≈ 24.6 MB codificados: dentro del limite */
    $maxTotal    = 18 * 1024 * 1024;   /* 18 MB en total */
    $maxCantidad = 100;
    $adjuntos = [];
    if (!isset($_FILES['adjuntos']['name']) || !is_array($_FILES['adjuntos']['name'])) {
        return [[], ''];
    }
    $total = 0;
    foreach ($_FILES['adjuntos']['name'] as $i => $nombreArchivo) {
        $nombreArchivo = (string)$nombreArchivo;
        $codigo = (int)($_FILES['adjuntos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($codigo === UPLOAD_ERR_NO_FILE || trim($nombreArchivo) === '') continue;
        if (count($adjuntos) >= $maxCantidad) {
            return [[], 'Se permiten un maximo de ' . $maxCantidad . ' archivos adjuntos.'];
        }
        if ($codigo !== UPLOAD_ERR_OK) {
            return [[], 'No se pudo subir "' . $nombreArchivo . '". Verifica que no supere el tamaño permitido por el servidor.'];
        }
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        if (!in_array($extension, $permitidas, true)) {
            return [[], 'El archivo "' . $nombreArchivo . '" no tiene un formato permitido (png, jpg, jpeg, gif, pdf, txt, xls, xlsx, doc, docx, ppt, pptx, csv, zip, rar).'];
        }
        $tamano = (int)($_FILES['adjuntos']['size'][$i] ?? 0);
        $tmp    = (string)($_FILES['adjuntos']['tmp_name'][$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return [[], 'El archivo "' . $nombreArchivo . '" no es valido.'];
        }
        if ($tamano > $maxArchivo) {
            return [[], 'El archivo "' . $nombreArchivo . '" supera el maximo de 10 MB por archivo.'];
        }
        $total += $tamano;
        if ($total > $maxTotal) {
            return [[], 'Los adjuntos superan el maximo total de 18 MB (limite de Gmail).'];
        }
        $baseLimpia = preg_replace('/[^A-Za-z0-9 ._()\-]/', '_', pathinfo($nombreArchivo, PATHINFO_FILENAME));
        $adjuntos[] = ['tmp' => $tmp, 'nom' => $baseLimpia . '.' . $extension];
    }
    return [$adjuntos, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $correoDisponible) {
    /* honeypot anti-bots: los robots rellenan este campo oculto */
    if (!empty($_POST['website'])) {
        $exito = true;
        $mensaje = ['tipo' => 'success', 'titulo' => 'Mensaje enviado', 'texto' => 'Gracias por escribirnos.', 'iconoBoton' => 'fa-check', 'claseBoton' => 'wbtn wbtn-accent'];
    } else {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $correo = trim((string)($_POST['correo'] ?? ''));
        $asunto = trim((string)($_POST['asunto'] ?? ''));
        $cuerpo = trim((string)($_POST['mensaje'] ?? ''));
        $prioridad = strtolower(trim((string)($_POST['prioridad'] ?? 'normal')));
        [$adjuntosMail, $errorAdjunto] = validarAdjuntosContacto();
        if ($nombre === '' || $cuerpo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = ['tipo' => 'error', 'titulo' => 'Datos incompletos', 'texto' => 'Completa tu nombre, un correo valido y el mensaje.', 'iconoBoton' => 'fa-pen-to-square', 'claseBoton' => 'wbtn'];
        } elseif ($errorAdjunto !== '') {
            $mensaje = ['tipo' => 'error', 'titulo' => 'Adjunto no permitido', 'texto' => $errorAdjunto, 'iconoBoton' => 'fa-paperclip', 'claseBoton' => 'wbtn'];
        } else {
            /* Windows cuenta tiempo real (incluye esperas del socket SMTP) */
            @set_time_limit(600);

            require __DIR__ . '/lib/phpmailer/Exception.php';
            require __DIR__ . '/lib/phpmailer/PHPMailer.php';
            require __DIR__ . '/lib/phpmailer/SMTP.php';
            require __DIR__ . '/correo_privado.php';

            $fecha = date('d/m/Y H:i');
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $asuntoLimpio = $asunto !== '' ? $asunto : 'Nuevo mensaje de contacto';
            $eNombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $eCorreo = htmlspecialchars($correo, ENT_QUOTES, 'UTF-8');
            $eAsunto = htmlspecialchars($asuntoLimpio, ENT_QUOTES, 'UTF-8');
            $eEmpresa = htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8');

            /* Limpiar HTML proveniente del editor Quill: permitir solo etiquetas seguras */
            function sanitizarHtmlQuill(string $html): string {
                /* Eliminar atributos on* (onclick, onload, etc.) y javascript: URIs */
                $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
                $html = preg_replace('/javascript\s*:/i', '', $html);
                $html = preg_replace('/vbscript\s*:/i', '', $html);
                $html = preg_replace('/data\s*:(?!image\/)/i', '', $html);
                /* Eliminar etiquetas no permitidas manteniendo el texto interior */
                $permitidas = 'p|br|b|i|u|strong|em|a|ul|ol|li|h[1-6]|blockquote|code|pre|img|span|table|thead|tbody|tr|td|th|div|iframe|ol';
                $html = preg_replace('~<(/?)(?!(?:' . $permitidas . ')[\s>])[a-z0-9]+(?:\b[^>]*)>~is', '', $html);
                /* <img>: permitir src (incluyendo data:image/), style, alt, width, height */
                $html = preg_replace_callback('#<img\b([^>]*)>#i', function($m) {
                    $attrs = $m[1];
                    $out = '<img';
                    /* src */
                    if (preg_match('/src\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $sm)) {
                        $out .= ' src="' . htmlspecialchars($sm[1] ?? $sm[2] ?? '', ENT_QUOTES, 'UTF-8') . '"';
                    }
                    /* alt */
                    if (preg_match('/alt\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $am)) {
                        $out .= ' alt="' . htmlspecialchars($am[1] ?? $am[2] ?? '', ENT_QUOTES, 'UTF-8') . '"';
                    }
                    /* width */
                    if (preg_match('/width\s*=\s*(?:"(\d+)"|\'(\d+)\'|(\d+))/i', $attrs, $wm)) {
                        $out .= ' width="' . ($wm[1] ?? $wm[2] ?? $wm[3] ?? '') . '"';
                    }
                    /* height */
                    if (preg_match('/height\s*=\s*(?:"(\d+)"|\'(\d+)\'|(\d+))/i', $attrs, $hm)) {
                        $out .= ' height="' . ($hm[1] ?? $hm[2] ?? $hm[3] ?? '') . '"';
                    }
                    /* style - permitir propiedades seguras */
                    if (preg_match('/style\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $sm2)) {
                        $st = $sm2[1] ?? $sm2[2] ?? '';
                        if (preg_match_all('/(max-width|width|height|border-radius|display|margin|padding|border[^:]*)\s*:\s*([^;]+)/i', $st, $cm)) {
                            $limpio = '';
                            foreach ($cm[0] as $c) { $limpio .= $c . ';'; }
                            $out .= ' style="' . htmlspecialchars($limpio, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    }
                    $out .= '>';
                    return $out;
                }, $html);
                /* <a>: extraer solo href */
                $html = preg_replace('#<a\s+[^>]*href\s*=\s*("([^"]*)"|\'([^\']*)\'|(\S+))#i', '<a href="$2$3$4"', $html);
                $html = preg_replace('#(<a\s[^>]*?)(\s+style\s*=\s*(?:"[^"]*"|\'[^\']*\'))#i', '$1', $html);
                $html = preg_replace('#(<a\s[^>]*?)(\s+class\s*=\s*(?:"[^"]*"|\'[^\']*\'))#i', '$1', $html);
                /* <iframe>: permitir src de youtube/vimeo, width, height, frameborder, allow */
                $html = preg_replace_callback('#<iframe\b([^>]*)>#i', function($m) {
                    $attrs = $m[1];
                    $out = '<iframe';
                    if (preg_match('/src\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $sm)) {
                        $src = htmlspecialchars($sm[1] ?? $sm[2] ?? '', ENT_QUOTES, 'UTF-8');
                        if (preg_match('#^(https?:)?//([a-z]+\.)?(youtube\.com|youtu\.be|vimeo\.com|player\.vimeo\.com)/#i', $src)) {
                            $out .= ' src="' . $src . '"';
                        }
                    }
                    if (preg_match('/width\s*=\s*(?:"(\d+)"|\'(\d+)\'|(\d+))/i', $attrs, $wm)) {
                        $out .= ' width="' . ($wm[1] ?? $wm[2] ?? $wm[3] ?? '') . '"';
                    }
                    if (preg_match('/height\s*=\s*(?:"(\d+)"|\'(\d+)\'|(\d+))/i', $attrs, $hm)) {
                        $out .= ' height="' . ($hm[1] ?? $hm[2] ?? $hm[3] ?? '') . '"';
                    }
                    if (preg_match('/frameborder\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $fm)) {
                        $out .= ' frameborder="' . htmlspecialchars($fm[1] ?? $fm[2] ?? '', ENT_QUOTES, 'UTF-8') . '"';
                    }
                    if (preg_match('/allowfullscreen/i', $attrs)) {
                        $out .= ' allowfullscreen';
                    }
                    $out .= '></iframe>';
                    return $out;
                }, $html);
                /* <span>: permitir style con colores y fuentes, y class line-* para bordes */
                $html = preg_replace_callback('#<span\b([^>]*)>#i', function($m) {
                    $attrs = $m[1];
                    $styles = '';
                    /* Extraer style si existe */
                    if (preg_match('/style\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $sm)) {
                        $st = $sm[1] ?? $sm[2] ?? '';
                        if (preg_match_all('/(color|background-color|font-family|font-size|font-weight|line-height|text-align|text-decoration|border)\s*:\s*([^;]+)/i', $st, $cm)) {
                            foreach ($cm[0] as $c) { $styles .= $c . ';'; }
                        }
                    }
                    /* Extraer class line-* para bordes */
                    if (preg_match('/class\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs, $cm2)) {
                        $cls = $cm2[1] ?? $cm2[2] ?? '';
                        if (preg_match('/\bline-(solid|double|dotted)\b/', $cls, $lm)) {
                            $styles .= 'border:1px ' . $lm[1] . ' #334155;';
                        }
                    }
                    if ($styles !== '') {
                        return '<span style="' . htmlspecialchars($styles, ENT_QUOTES, 'UTF-8') . '">';
                    }
                    return '<span>';
                }, $html);
                return $html;
            }

            /* Convertir HTML del editor a texto plano legible (sin etiquetas ni estilos) */
            function htmlAPlano(string $html): string {
                /* Emojis insertados como imagen SVG -> recuperar el caracter para el texto plano */
                $html = preg_replace_callback('/<img\b[^>]*src\s*=\s*"([^"]*data:image\/svg\+xml[^"]*)"/i', function($m) {
                    $src = urldecode($m[1]);
                    if (preg_match('/<text[^>]*>([^<]*)<\/text>/is', $src, $t)) {
                        return $t[1];
                    }
                    return '';
                }, $html);
                $html = preg_replace('#<\s*(br\s*/?|/p|/div|/li|/h[1-6]|/tr|/pre|/blockquote)\s*>#i', "\n", $html);
                $html = strip_tags($html);
                $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
                $html = preg_replace('/[ \t\r]+/', ' ', $html);
                $html = preg_replace('/\n\s*\n+/', "\n", $html);
                return trim($html);
            }

            /* Sanitizar el HTML crudo del editor (sin htmlspecialchars: preservar tags reales) */
            $cuerpoHtml = sanitizarHtmlQuill($cuerpo);

            $mapPrioridad = [
                'baja'    => ['label' => 'Baja',    'color' => '#64748b'],
                'normal'  => ['label' => 'Normal',  'color' => '#2563eb'],
                'alta'    => ['label' => 'Alta',    'color' => '#d97706'],
                'urgente' => ['label' => 'Urgente', 'color' => '#dc2626'],
            ];
            if (!isset($mapPrioridad[$prioridad])) { $prioridad = 'normal'; }
            $ePrioridad      = $mapPrioridad[$prioridad]['label'];
            $colorPrioridad  = $mapPrioridad[$prioridad]['color'];
            $etiquetaWeb     = ($prioridad === 'urgente') ? '[Web][URGENTE] ' : '[Web] ';

            $filaAdjuntos = '';
            if (count($adjuntosMail) > 0) {
                $itemsAdj = '';
                foreach ($adjuntosMail as $adj) {
                    $itemsAdj .= '<div style="margin:2px 0;font-size:14px;color:#0f172a;">&#128206; ' . htmlspecialchars($adj['nom'], ENT_QUOTES, 'UTF-8') . '</div>';
                }
                $filaAdjuntos = '<tr><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;">&#128206; Adjuntos</td><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;">' . $itemsAdj . '</td></tr>';
            }

            $plantillaHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8">
<style>
.line-solid{border-style:solid !important;}
.line-double{border-style:double !important;}
.line-dotted{border-style:dotted !important;}
</style>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.08);">
    <tr>
      <td style="background:#1e293b;padding:22px 28px;">
        <table role="presentation" width="100%"><tr>
          <td style="font-size:20px;font-weight:700;color:#ffffff;">{$eEmpresa}</td>
          <td align="right" style="color:#94a3b8;font-size:13px;"><span style="font-family:Arial,sans-serif;">&#9993;</span> Formulario de contacto</td>
        </tr></table>
      </td>
    </tr>
    <tr>
      <td style="height:4px;background:linear-gradient(90deg,#3b82f6,#06b6d4);font-size:0;line-height:0;">&nbsp;</td>
    </tr>
    <tr><td style="padding:26px 28px 6px;">
      <p style="margin:0 0 18px;color:#334155;font-size:15px;">Has recibido un nuevo mensaje a traves del sitio web:</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;">
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;">&#128100; Nombre</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;font-weight:600;">{$eNombre}</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#9993; Correo</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;"><a href="mailto:{$eCorreo}" style="color:#2563eb;font-size:14px;text-decoration:none;">{$eCorreo}</a></td>
        </tr>
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#127991; Asunto</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;">{$eAsunto}</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#9873; Prioridad</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;"><span style="color:{$colorPrioridad};font-weight:700;font-size:14px;">{$ePrioridad}</span></td>
        </tr>
{$filaAdjuntos}
        <tr>
          <td style="padding:12px 16px;color:#64748b;font-size:13px;">&#128337; Fecha / IP</td>
          <td style="padding:12px 16px;color:#0f172a;font-size:14px;">{$fecha} &nbsp;&middot;&nbsp; {$ip}</td>
        </tr>
      </table>
      <div style="margin:20px 0 4px;color:#64748b;font-size:13px;">Mensaje:</div>
      <div style="background:#f8fafc;border-left:4px solid #3b82f6;border-radius:0 8px 8px 0;padding:14px 18px;color:#334155;font-size:14px;line-height:1.7;">{$cuerpoHtml}</div>
    </td></tr>
    <tr><td style="padding:22px 28px 26px;">
      <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">
        Este mensaje fue enviado automaticamente desde el formulario de contacto de {$eEmpresa}.<br>
        Puedes responder directamente a este correo para contactar al remitente.
      </p>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
HTML;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->Port = 587;
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Timeout = 60;   /* detecta conexiones colgadas antes del limite de PHP */
                $mail->Username = CORREO_SMTP_USER;
                $mail->Password = CORREO_SMTP_PASS;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(CORREO_SMTP_USER, $nombreEmpresa);
                $mail->addAddress(CORREO_DESTINATARIO);
                $mail->addReplyTo($correo, $nombre);
                $mail->Subject = $etiquetaWeb . $asuntoLimpio;
                $mail->Priority = ($prioridad === 'alta' || $prioridad === 'urgente') ? 1 : (($prioridad === 'baja') ? 5 : 3);
                $enviarPlano = !empty($_POST['texto_plano']);
                $listaAdjTxt = count($adjuntosMail) > 0 ? "\n\nAdjuntos (" . count($adjuntosMail) . "):\n- " . implode("\n- ", array_column($adjuntosMail, 'nom')) : '';
                $textoPlano = "Nuevo mensaje desde el formulario web\n\nNombre: {$nombre}\nCorreo: {$correo}\nAsunto: {$asuntoLimpio}\nPrioridad: {$ePrioridad}\nFecha: {$fecha}\nIP: {$ip}\n\n" . htmlAPlano($cuerpo) . $listaAdjTxt;
                if ($enviarPlano) {
                    $mail->isHTML(false);
                    $mail->Body = $textoPlano;
                } else {
                    $mail->isHTML(true);
                    $mail->Body = $plantillaHtml;
                    $mail->AltBody = $textoPlano;
                }
                foreach ($adjuntosMail as $adj) {
                    $mail->addAttachment($adj['tmp'], $adj['nom']);
                }
                $mail->send();
                $_SESSION['ultimo_envio'] = time();
                $exito = true;
                $mensaje = [
                    'tipo' => 'success',
                    'titulo' => 'Mensaje enviado',
                    'html' => 'Gracias <b style="color:#5eead4">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</b>. Te responderemos a la brevedad.',
                    'iconoBoton' => 'fa-house',
                    'claseBoton' => 'wbtn wbtn-accent',
                ];
                /* ===== Acuse de recibo: respuesta automatica al remitente ===== */
                if (filter_var($correo, FILTER_VALIDATE_EMAIL) && !empty($_POST['acuse_recibo'])) {
                    try {
                        $acuseAsunto = 'Acuse de recibo - ' . $asuntoLimpio;
                        $acuseHtml = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>'
                            . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:\'Segoe UI\',Arial,sans-serif;">'
                            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">'
                            . '<tr><td align="center">'
                            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.08);">'
                            . '<tr><td style="background:#1e293b;padding:22px 28px;"><table role="presentation" width="100%"><tr>'
                            . '<td style="font-size:20px;font-weight:700;color:#ffffff;">' . $eEmpresa . '</td>'
                            . '<td align="right" style="color:#94a3b8;font-size:13px;">&#9989; Acuse de recibo</td>'
                            . '</tr></table></td></tr>'
                            . '<tr><td style="height:4px;background:linear-gradient(90deg,#059669,#10b981);font-size:0;line-height:0;">&nbsp;</td></tr>'
                            . '<tr><td style="padding:28px 28px 10px;">'
                            . '<p style="margin:0 0 18px;color:#334155;font-size:15px;">Hola <b>' . $eNombre . '</b>,</p>'
                            . '<p style="margin:0 0 18px;color:#334155;font-size:14px;">Hemos recibido tu mensaje correctamente. A continuacion, un resumen de lo que nos enviaste:</p>'
                            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;">'
                            . '<tr><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;">&#128100; Nombre</td><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;font-weight:600;">' . $eNombre . '</td></tr>'
                            . '<tr><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#9993; Correo</td><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#2563eb;font-size:14px;">' . $eCorreo . '</td></tr>'
                            . '<tr><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#127991; Asunto</td><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;">' . $eAsunto . '</td></tr>'
                            . '<tr><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#9873; Prioridad</td><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;"><span style="color:' . $colorPrioridad . ';font-weight:700;font-size:14px;">' . $ePrioridad . '</span></td></tr>'
                            . '<tr><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#128337; Recibido</td><td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;">' . $fecha . '</td></tr>'
                            . '</table>'
                            . '<div style="margin:22px 0 6px;color:#64748b;font-size:13px;">Tu mensaje:</div>'
                            . '<div style="background:#f8fafc;border-left:4px solid #10b981;border-radius:0 8px 8px 0;padding:14px 18px;color:#334155;font-size:14px;line-height:1.7;">' . $cuerpoHtml . '</div>'
                            . '</td></tr>'
                            . '<tr><td style="padding:22px 28px 26px;">'
                            . '<p style="margin:0 0 12px;color:#334155;font-size:14px;">Nuestro equipo revisara tu mensaje y te respondera lo antes posible.</p>'
                            . '<p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">'
                            . 'Este es un acuse automatico de que hemos recibido tu mensaje. No es necesario responder a este correo.<br>'
                            . 'Si necesitas ayuda urgente, puedes contactarnos directamente a <b style="color:#0f172a;">' . $eEmpresa . '</b>.</p>'
                            . '</td></tr></table></td></tr></table></body></html>';
                        $mailAcuse = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mailAcuse->isSMTP();
                        $mailAcuse->Host = 'smtp.gmail.com';
                        $mailAcuse->Port = 587;
                        $mailAcuse->SMTPAuth = true;
                        $mailAcuse->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mailAcuse->Timeout = 60;
                        $mailAcuse->Username = CORREO_SMTP_USER;
                        $mailAcuse->Password = CORREO_SMTP_PASS;
                        $mailAcuse->CharSet = 'UTF-8';
                        $mailAcuse->setFrom(CORREO_SMTP_USER, $nombreEmpresa);
                        $mailAcuse->addAddress($correo, $nombre);
                        $mailAcuse->Subject = $acuseAsunto;
                        $mailAcuse->Priority = 3;
                        $mailAcuse->isHTML(true);
                        $mailAcuse->Body = $acuseHtml;
                        $mailAcuse->AltBody = "Hola {$nombre},\n\nHemos recibido tu mensaje correctamente.\n\nAsunto: {$asuntoLimpio}\nPrioridad: {$ePrioridad}\nFecha: {$fecha}\n\nTu mensaje:\n{$cuerpo}\n\nNuestro equipo te respondera lo antes posible.\n\n-- {$eEmpresa}";
                        $mailAcuse->send();
                    } catch (Throwable $eAcuse) {
                        error_log('Contacto acuse: ' . (isset($mailAcuse) ? $mailAcuse->ErrorInfo : $eAcuse->getMessage()));
                    }
                }
            } catch (Throwable $e) {
                error_log('Contacto: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
                $mensaje = [
                    'tipo' => 'error',
                    'titulo' => 'No se pudo enviar',
                    'texto' => 'Ocurrio un problema temporal. Intenta nuevamente en unos minutos.',
                    'iconoBoton' => 'fa-rotate-right',
                    'claseBoton' => 'wbtn',
                ];
            }
        }
    }
}
$valor = fn(string $k): string => htmlspecialchars(trim((string)($_POST[$k] ?? '')), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Póngase en Contacto con - <?= htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" type="image/png" href="/images/favicon.png">
<link rel="stylesheet" href="/assets/winui.css">
<link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
<link href="css/quill.snow.css" rel="stylesheet">
<style>
    /* ===== Fondo web animado estilo InstalarBD ===== */
    body.win-page{
        background:linear-gradient(160deg,#0f766e 0%,#0f172a 45%,#020617 100%);
        align-items:flex-start;
    }
    .bg{
        animation:slide 3s ease-in-out infinite alternate;
        background-image:linear-gradient(-60deg,#6c3 50%,#09f 50%);
        bottom:0;left:-50%;opacity:.5;position:fixed;right:-50%;top:0;z-index:-1;
    }
    .bg2{animation-direction:alternate-reverse;animation-duration:4s;}
    .bg3{animation-duration:5s;}
    @keyframes slide{
        0%{transform:translateX(-25%);}
        100%{transform:translateX(25%);}
    }

    /* ===== Encabezado y pie estilo instalador ===== */
    .pagina{width:100%;max-width:62rem;display:flex;flex-direction:column;gap:20px;}
    .encabezado{text-align:center;color:#fff;margin-bottom:2px;}
    .enc-titulo{font-size:1.45rem;font-weight:800;letter-spacing:.5px;margin:0;}
    .enc-titulo i{color:#5eead4;margin-right:.55rem;}
    .enc-sub{margin:.4rem 0 0;color:#cbd5e1;font-size:.95rem;}
    .pie{
        text-align:center;color:rgba(255,255,255,.92);font-size:12.5px;padding:14px 18px;
        border-radius:12px;border:1px solid rgba(255,255,255,.1);
        background:linear-gradient(-60deg,rgba(34,197,94,.18) 50%,rgba(14,165,233,.18) 50%);
        background-size:200% 200%;letter-spacing:.3px;
        animation:footerSlide 6s ease-in-out infinite alternate;
    }
    @keyframes footerSlide{0%{background-position:0% 50%;}100%{background-position:100% 50%;}}

    /* ===== Ventana con paleta del instalador ===== */
    .win-window{background:#0f172a;border:1px solid #334155;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.5);}
    .win-titlebar{
        background:linear-gradient(135deg,#0f766e 0%,#134e4a 100%);
        border-bottom:1px solid rgba(255,255,255,.1);
        padding:12px 16px;
    }
    .win-titlebar .tb-icon{color:#5eead4;font-size:18px;}
    .win-titlebar .tb-text{font-size:15px;font-weight:700;color:#fff;letter-spacing:.3px;}
    .cap-btn{
        width:30px;height:30px;margin-left:8px;border-radius:8px;
        background:rgba(255,255,255,.12);color:#e2e8f0;font-size:15px;
    }
    .cap-btn.home:hover{background:rgba(255,255,255,.25);color:#fff;}
    .cap-btn.close:hover{background:#ef4444;color:#fff;}
    button.cap-btn{border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;justify-content:center;font-family:inherit;}
    .cap-btn.send:hover{background:#14b8a6;color:#042f2e;}
    .btn-adjuntar{white-space:nowrap;padding:.58rem .9rem;}
    .btn-adjuntar #adjuntarContador{
        min-width:20px;text-align:center;background:rgba(255,255,255,.22);
        border-radius:10px;padding:0 5px;font-size:.78rem;font-weight:700;
    }
    .btn-adjuntar #adjuntarContador:empty{display:none;}
    .win-body{padding:24px 28px 26px;}

    /* ===== Formulario con paleta del instalador ===== */
    .win-h1{font-size:1.15rem;color:#e2e8f0;font-weight:700;}
    .win-h1 i{color:#5eead4;}
    .win-sub{color:#94a3b8;font-size:13.5px;}
    .win-label{font-size:12.5px;font-weight:600;color:#cbd5e1;}
    .win-label i{color:#5eead4;}
    .win-input,.win-textarea{
        background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:10px;
    }
    .win-input:focus,.win-textarea:focus{
        border-bottom:1px solid #14b8a6;
        box-shadow:0 0 0 .2rem rgba(20,184,166,.18);
    }
    /* ===== Dropdown personalizado de prioridad ===== */
    .prio-dd{position:relative;}
    .prio-dd-btn{
        display:flex;align-items:center;gap:6px;width:100%;cursor:pointer;
        padding:6px 8px;justify-content:flex-start;font:inherit;
    }
    .prio-dd-btn .prio-dd-ico{width:14px;font-size:12px;text-align:center;}
    .prio-dd-btn .prio-dd-txt{
        flex:1;font-size:12.5px;color:#e2e8f0;text-align:left;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }
    .prio-dd-btn .prio-dd-caret{font-size:9px;color:#64748b;}
    .prio-dd-list{
        position:absolute;top:calc(100% + 4px);left:0;z-index:60;
        min-width:150px;background:#1e293b;border:1px solid #334155;border-radius:10px;
        list-style:none;margin:0;padding:4px;display:none;
        box-shadow:0 10px 24px rgba(0,0,0,.45);
    }
    .prio-dd-list.open{display:block;}
    .prio-dd-list li{
        display:flex;align-items:center;gap:8px;padding:7px 9px;border-radius:7px;
        cursor:pointer;font-size:12.5px;color:#e2e8f0;user-select:none;white-space:nowrap;
    }
    .prio-dd-list li i{width:15px;text-align:center;font-size:12px;}
    .prio-dd-list li:hover,.prio-dd-list li.seleccion{background:#0f172a;}
    .prio-dd-list li.seleccion{color:#2dd4bf;}
    .wbtn-accent{
        background:linear-gradient(135deg,#2dd4bf,#14b8a6);
        border:none;color:#042f2e;
    }
    .wbtn-accent:hover{background:linear-gradient(135deg,#43dbcc,#19c0aa);}
    .win-footnote a{color:#5eead4;}

    /* ===== Zona de adjuntos ===== */
    .drop-zona{
        border:2px dashed #334155;border-radius:12px;background:#111c31;
        padding:18px 14px;text-align:center;color:#94a3b8;cursor:pointer;transition:.2s;
        outline:none;
    }
    .drop-zona:hover,.drop-zona.drag{border-color:#14b8a6;background:rgba(20,184,166,.08);color:#cbd5e1;}
    .drop-zona>.fas{font-size:26px;color:#5eead4;margin-bottom:6px;display:block;}
    .drop-zona .dz-titulo{font-size:13.5px;}
    .drop-zona .dz-titulo b{color:#e2e8f0;}
    .chips{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin:10px 0 8px;padding:0;}
    .chips span{
        background:#1e293b;border:1px solid #334155;border-radius:20px;
        font-size:10.5px;font-weight:600;padding:2px 9px;color:#cbd5e1;
        text-transform:uppercase;letter-spacing:.5px;list-style:none;
    }
    .drop-zona small{font-size:11.5px;color:#64748b;}
    .drop-zona small b{color:#94a3b8;}
    /* ===== Fila principal: formulario + panel lateral ===== */
    .fila-principal{display:flex;gap:14px;align-items:stretch;width:100%;}
    .fila-principal .win-window{flex:1;min-width:0;}

    /* ===== Panel lateral de adjuntos ===== */
    .panel-adjuntos{
        width:16rem;flex-shrink:0;display:flex;flex-direction:column;
        background:#0f172a;border:1px solid #334155;border-radius:12px;
        box-shadow:0 10px 30px rgba(0,0,0,.5);overflow:hidden;
    }
    .pa-titlebar{
        background:linear-gradient(135deg,#0f766e,#134e4a);
        padding:11px 13px;color:#fff;font-size:12.5px;font-weight:700;
        display:flex;align-items:center;gap:8px;letter-spacing:.4px;
        border-bottom:1px solid rgba(255,255,255,.1);
    }
    .pa-titlebar i{color:#5eead4;font-size:14px;}
    .pa-titlebar .pa-contador{
        margin-left:auto;background:rgba(255,255,255,.16);
        border-radius:12px;padding:1px 9px;font-size:11px;
    }
    .pa-body{
        padding:10px;display:flex;flex-direction:column;gap:8px;
        overflow-y:auto;flex:1;min-height:120px;max-height:520px;
    }
    .pa-body::-webkit-scrollbar{width:6px;}
    .pa-body::-webkit-scrollbar-thumb{background:#334155;border-radius:6px;}
    .pa-vacio{
        margin:auto;text-align:center;color:#475569;font-size:12.5px;
        line-height:1.7;padding:26px 10px;
    }
    .pa-vacio i{display:block;font-size:30px;margin-bottom:8px;color:#334155;}
    .pa-vacio small{color:#334155;font-size:10.5px;}
    .pa-footer{
        border-top:1px solid #334155;padding:11px 13px;
        font-size:11.5px;color:#94a3b8;background:#111c31;
    }
    .pa-footer .pa-linea{display:flex;align-items:center;gap:7px;margin-bottom:7px;}
    .pa-footer i{color:#5eead4;width:14px;text-align:center;}

    /* Tarjeta de archivo dentro del panel */
    .adj-item{
        position:relative;display:flex;gap:9px;align-items:flex-start;
        background:#16223a;border:1px solid #334155;border-radius:10px;
        padding:9px 10px;
        transition:background .2s,border-color .2s,transform .15s,box-shadow .2s;
    }
    .adj-item:hover{
        background:#1c2c4a;border-color:rgba(45,212,191,.55);
        transform:translateX(3px);box-shadow:0 2px 10px rgba(20,184,166,.18);
    }
    .adj-item>.adj-icono i{font-size:19px;width:22px;text-align:center;margin-top:2px;transition:transform .2s;}
    .adj-item:hover>.adj-icono i{transform:scale(1.15);}
    .adj-item>.adj-thumb{
        width:38px;height:38px;min-width:38px;border-radius:7px;overflow:hidden;
        background:#1e293b;display:flex;align-items:center;justify-content:center;
    }
    .adj-info{flex:1;min-width:0;}
    .adj-nombre{
        font-size:12px;color:#e2e8f0;word-break:break-all;
        line-height:1.35;padding-right:18px;
    }
    .adj-tamano{font-size:10.5px;color:#94a3b8;margin-top:3px;display:flex;align-items:center;gap:5px;}
    .adj-tamano .ok{color:#34d399;}
    .adj-item button{
        position:absolute;top:6px;right:6px;background:none;border:none;
        color:#64748b;cursor:pointer;font-size:12px;padding:3px 5px;border-radius:6px;
        transition:.2s;
    }
    .adj-item button:hover{color:#ef4444;background:rgba(239,68,68,.14);}
    .btn-quitar-todos{
        background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.4);
        color:#f87171;border-radius:8px;padding:4px 10px;font-size:10.5px;font-weight:600;
        cursor:pointer;transition:.2s;
    }
    .btn-quitar-todos:hover{background:rgba(239,68,68,.18);border-color:#ef4444;}

    /* ===== Menu contextual de adjuntos ===== */
    .ctx-menu{
        position:fixed;z-index:10050;display:none;min-width:210px;
        background:#0f172a;border:1px solid #334155;border-radius:12px;
        box-shadow:0 12px 36px rgba(0,0,0,.65);padding:6px;
        font-size:13px;color:#e2e8f0;user-select:none;
        animation:ctxIn .12s ease;
    }
    @keyframes ctxIn{from{opacity:0;transform:translateY(-4px) scale(.98);}to{opacity:1;transform:translateY(0) scale(1);}}
    .ctx-menu .ctx-hd{
        padding:7px 10px;font-size:11px;font-weight:700;color:#5eead4;
        border-bottom:1px solid #1e293b;margin-bottom:4px;
        word-break:break-all;max-width:240px;line-height:1.35;letter-spacing:.3px;
    }
    .ctx-item{
        display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:8px;
        cursor:pointer;transition:background .12s;color:#cbd5e1;font-weight:500;
    }
    .ctx-item i{width:16px;text-align:center;color:#94a3b8;font-size:13px;}
    .ctx-item:hover{background:rgba(94,234,212,.12);color:#f0fdfa;}
    .ctx-item:hover i{color:#5eead4;}
    .ctx-item.ctx-danger:hover{background:rgba(239,68,68,.14);}
    .ctx-item.ctx-danger:hover i{color:#f87171;}
    .ctx-item.ctx-danger{color:#fca5a5;}
    .ctx-item.ctx-danger i{color:#f87171;}
    .ctx-sep{height:1px;background:#1e293b;margin:5px 8px;}
    .ctx-menu.ctx-disabled .ctx-item{pointer-events:none;opacity:.45;}

    @media (max-width:980px){
        .fila-principal{flex-direction:column;}
        .panel-adjuntos{width:100%;}
        .pa-body{max-height:300px;}
    }

    /* ===== Engranajes animados del modal ===== */
    @keyframes girarD { to { transform: rotate(360deg); } }
    @keyframes girarI { to { transform: rotate(-360deg); } }
    .gear-grande{display:inline-block;font-size:36px;color:#5eead4;animation:girarD 2.2s linear infinite;filter:drop-shadow(0 0 8px rgba(45,212,191,.45));}
    .gear-peque{display:inline-block;font-size:21px;color:#14b8a6;animation:girarI 1.5s linear infinite;margin-left:-11px;}

    /* ===== Quill editor dark theme ===== */
    .ql-toolbar.ql-snow{
        background:#1e293b !important;border:1px solid #334155 !important;
        border-radius:10px 10px 0 0 !important;padding:3px 4px !important;
        flex-wrap:wrap !important;gap:2px 0 !important;
    }
    .ql-toolbar .ql-formats{
        display:flex;align-items:center;gap:0px;white-space:nowrap !important;
        margin:0 1px !important;padding:2px 3px !important;
        flex:1 1 100% !important;flex-wrap:wrap;
    }
    .ql-toolbar .ql-formats:last-child{border-right:none !important;}
    .ql-toolbar .ql-size-custom,.ql-toolbar .ql-clean{position:relative;}
    .ql-toolbar button{padding:2px 4px !important;height:26px !important;}
    .ql-toolbar button svg{width:15px;height:15px !important;}
    .ql-toolbar.ql-snow .ql-stroke{stroke:#94a3b8 !important;}
    .ql-toolbar.ql-snow .ql-fill{fill:#94a3b8 !important;}
    .ql-toolbar.ql-snow .ql-picker{color:#94a3b8 !important;font-size:12px !important;}
    .ql-toolbar.ql-snow .ql-picker-label{padding:1px 4px !important;height:22px !important;line-height:20px !important;}
    .ql-toolbar.ql-snow .ql-picker-label::after{display:none !important;}
    .ql-toolbar.ql-snow .ql-picker-options{
        background:#1e293b !important;border:1px solid #334155 !important;
        border-radius:8px !important;
    }
    .ql-toolbar.ql-snow .ql-picker-item:hover{color:#5eead4 !important;}
    .ql-toolbar.ql-snow .ql-active .ql-stroke{stroke:#5eead4 !important;}
    .ql-toolbar.ql-snow .ql-active .ql-fill{fill:#5eead4 !important;}
    .ql-toolbar.ql-snow .ql-active{color:#5eead4 !important;}
    .ql-toolbar.ql-snow button:hover .ql-stroke{stroke:#5eead4 !important;}
    .ql-toolbar.ql-snow button:hover .ql-fill{fill:#5eead4 !important;}
    .ql-container.ql-snow{
        background:#ffffff !important;border:1px solid #cbd5e1 !important;
        border-top:none !important;border-radius:0 0 10px 10px !important;
        color:#000000 !important;font-family:'Inter','Segoe UI',sans-serif;
        min-height:140px !important;
    }
    .ql-editor{min-height:140px !important;line-height:1.65 !important;font-size:14px !important;}
    .ql-editor.ql-blank::before{color:#64748b !important;font-style:normal !important;}
    .ql-editor ol{list-style-type:none !important;padding-left:1.5em !important;}
    .ql-editor li[data-list=ordered] > .ql-ui:before{color:currentColor !important;font-weight:600;text-align:right;}
    .ql-editor ul{list-style:none !important;padding-left:1.5em !important;}
    .ql-editor ul li{padding-left:0.5em !important;}
    .ql-editor li[data-list=bullet] > .ql-ui:before{content:'\2022';margin-left:-1.5em;margin-right:.35em;color:currentColor !important;font-weight:700;font-size:1.3em;line-height:1;}
    .ql-editor p,.ql-editor li{color:#000000 !important;}
    .ql-editor h1{color:#0f172a !important;font-size:1.8em !important;font-weight:700 !important;margin:0.6em 0 0.4em !important;}
    .ql-editor h2{color:#0f172a !important;font-size:1.5em !important;font-weight:700 !important;margin:0.5em 0 0.3em !important;}
    .ql-editor h3{color:#0f172a !important;font-size:1.25em !important;font-weight:700 !important;margin:0.5em 0 0.3em !important;}
    .ql-editor h4{color:#111827 !important;font-size:1.1em !important;font-weight:700 !important;margin:0.4em 0 0.2em !important;}
    .ql-editor h5{color:#111827 !important;font-size:1em !important;font-weight:700 !important;margin:0.4em 0 0.2em !important;}
    .ql-editor h6{color:#111827 !important;font-size:.78em !important;font-weight:700 !important;margin:0.4em 0 0.2em !important;}
    .ql-editor a{color:#0d9488 !important;cursor:pointer;text-decoration:underline;}
    .ql-editor .ql-formula{color:#b45309 !important;font-style:italic;}
    .ql-editor td.tbl-marca-fila{background-color:rgba(14,165,233,.30) !important;}
    .ql-editor td.tbl-marca-col{background-color:rgba(14,165,233,.30) !important;}
    .ql-editor td.tbl-celda-marcada{background-color:rgba(6,182,212,.45) !important;box-shadow:inset 0 0 0 2px #0891b2 !important;}
    .ql-editor blockquote{border-left:4px solid #0f766e !important;color:#374151 !important;padding-left:12px !important;}
    .ql-editor code,.ql-editor pre{background:#1e293b !important;color:#5eead4 !important;border-radius:6px !important;}
    .ql-snow .ql-tooltip{background:#1e293b !important;border:1px solid #334155 !important;color:#e2e8f0 !important;border-radius:8px !important;}
    .ql-snow .ql-tooltip input{background:#0f172a !important;border:1px solid #334155 !important;color:#e2e8f0 !important;border-radius:6px !important;}
    /* Link modal custom dark */
    #qlLinkProto{background:#1a2332 !important;border:none !important;color:#5eead4 !important;}
    #qlLinkProto option{background:#0f172a;color:#e2e8f0;}
    #qlLinkUrl:focus{border-color:#0f766e !important;box-shadow:0 0 0 2px rgba(15,118,110,.3) !important;}
    .ql-toolbar button,.ql-toolbar .ql-emoji-btn,.ql-toolbar .ql-attach-btn,.ql-toolbar .ql-html-btn,.ql-toolbar .ql-print-btn,.ql-toolbar .ql-pdf-btn,.ql-toolbar .ql-size-custom,.ql-toolbar .ql-sym-btn,.ql-toolbar .ql-table-btn,.ql-toolbar .ql-img-btn,.ql-toolbar .ql-undo-btn,.ql-toolbar .ql-redo-btn{
        display:flex;align-items:center;justify-content:center;width:24px;height:26px;cursor:pointer;border-radius:3px;transition:.15s;padding:1px 2px !important;
    }
    .ql-toolbar button:hover,.ql-toolbar .ql-emoji-btn:hover,.ql-toolbar .ql-attach-btn:hover,.ql-toolbar .ql-html-btn:hover,.ql-toolbar .ql-print-btn:hover,.ql-toolbar .ql-pdf-btn:hover,.ql-toolbar .ql-size-custom:hover,.ql-toolbar .ql-sym-btn:hover,.ql-toolbar .ql-table-btn:hover,.ql-toolbar .ql-img-btn:hover{background:rgba(94,234,212,.12) !important;}
    .ql-toolbar .ql-emoji-btn i{font-size:14px;color:#fbbf24;}
    .ql-toolbar .ql-attach-btn i{font-size:13px;color:#60a5fa;}
    .ql-toolbar .ql-html-btn i{font-size:12px;color:#a78bfa;}
    .ql-toolbar .ql-print-btn i{font-size:12px;color:#94a3b8;}
    .ql-toolbar .ql-pdf-btn i{font-size:12px;color:#f87171;}
    .ql-toolbar .ql-size-custom i{font-size:12px;color:#fbbf24;}
    .ql-toolbar .ql-size-mb,.ql-toolbar .ql-size-pb{width:26px !important;height:26px !important;}
    .ql-toolbar .ql-size-mb{margin-right:2px !important;}
    .ql-toolbar .ql-size-pb{margin-left:2px !important;}
    .ql-toolbar .ql-size-mb i,.ql-toolbar .ql-size-pb i{font-size:14px;color:#fde68a;font-weight:700;}
    .ql-toolbar .ql-sym-btn i{font-size:12px;color:#f472b6;}
    .ql-toolbar .ql-table-btn i{font-size:12px;color:#60a5fa;}
    .ql-toolbar .ql-img-btn i{font-size:12px;color:#34d399;}
    .ql-toolbar .ql-border-btn i{font-size:12px;color:#fb923c;}
    .ql-toolbar .ql-border-select{
        display:inline-block;height:26px;max-width:110px;background:#0f172a;color:#e2e8f0;
        border:1px solid #334155;border-radius:5px;font-size:11px;padding:0 4px;cursor:pointer;outline:none;
    }
    .ql-toolbar .ql-border-select:hover{border-color:#0d9488;}
    .ql-toolbar .ql-border-select option{background:#0f172a;color:#e2e8f0;}
    .ql-toolbar .ql-header-select{
        display:inline-block;height:26px;max-width:96px;background:#0f172a;color:#e2e8f0;
        border:1px solid #334155;border-radius:5px;font-size:11px;padding:0 4px;cursor:pointer;outline:none;font-weight:700;
        margin-right:10px !important;
    }
    .ql-toolbar .ql-header-select:hover{border-color:#0d9488;}
    .ql-toolbar .ql-header-select option{background:#0f172a;color:#e2e8f0;}
    .ql-toolbar .ql-font-btn{margin-left:4px !important;width:96px !important;height:26px !important;overflow:hidden;background:#0f172a;border:1px solid #334155;border-radius:5px;padding:0 !important;}
    .ql-toolbar .ql-case-btn{width:74px !important;height:26px !important;overflow:hidden;background:#0f172a;border:1px solid #334155;border-radius:5px;padding:0 !important;}
    .ql-toolbar .ql-bold i,.ql-toolbar .ql-bold b{font-size:15px;color:#e2e8f0;}
    .ql-toolbar .ql-italic i{font-size:15px;color:#e2e8f0;}
    .ql-toolbar .ql-underline u{font-size:15px;color:#e2e8f0;}
    .ql-toolbar .ql-strike s{font-size:15px;color:#e2e8f0;}
    .ql-toolbar .ql-font-select{
        display:inline-block;height:26px;max-width:138px;background:#0f172a;color:#e2e8f0;
        border:1px solid #334155;border-radius:5px;font-size:11px;padding:0 4px;cursor:pointer;outline:none;
    }
    .ql-toolbar .ql-font-select:hover{border-color:#0d9488;}
    .ql-toolbar .ql-font-select option{background:#0f172a;color:#e2e8f0;}
    .ql-toolbar .ql-case-select{
        display:inline-block;height:26px;max-width:120px;background:#0f172a;color:#e2e8f0;
        border:1px solid #334155;border-radius:5px;font-size:11px;padding:0 4px;cursor:pointer;outline:none;
    }
    .ql-toolbar .ql-case-select:hover{border-color:#0d9488;}
    .ql-toolbar .ql-case-select option{background:#0f172a;color:#e2e8f0;}
    /* Toolbar en dos lineas (cada grupo al 100%) */
    .ql-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:2px 0;padding:3px 4px !important;}
    .ql-toolbar .ql-header-btn i{font-size:12px;color:#c084fc;}
    /* Header bubble popup */
    .header-bubble-popup{
        position:absolute;top:100%;left:50%;transform:translateX(-50%);z-index:10001;
        background:#0f172a;border:1px solid #334155;border-radius:10px;
        box-shadow:0 8px 32px rgba(0,0,0,.6);padding:6px;margin-top:4px;
        display:none;white-space:nowrap;
    }
    .header-bubble-popup.abierto{display:flex;gap:2px;}
    .header-bubble-popup button{
        display:flex;align-items:center;justify-content:center;min-width:34px;height:28px;
        background:none;border:1px solid transparent;border-radius:6px;
        color:#94a3b8;font-size:12px;font-weight:700;cursor:pointer;padding:0 6px;transition:.12s;
    }
    .header-bubble-popup button:hover{background:rgba(94,234,212,.12);color:#5eead4;border-color:rgba(94,234,212,.2);}
    .header-bubble-popup .hdr-h1{font-size:15px;font-weight:800;color:#f0fdfa;}
    .header-bubble-popup .hdr-h2{font-size:13px;font-weight:800;color:#ccfbf1;}
    .header-bubble-popup .hdr-h3{font-size:12px;font-weight:700;color:#99f6e4;}
    .header-bubble-popup .hdr-h4{font-size:11px;font-weight:700;color:#5eead4;}
    .header-bubble-popup .hdr-h5{font-size:10.5px;font-weight:700;color:#2dd4bf;}
    .header-bubble-popup .hdr-h6{font-size:10px;font-weight:600;color:#14b8a6;text-transform:uppercase;}
    .header-bubble-popup .hdr-normal{font-size:11px;font-weight:400;color:#64748b;font-style:italic;}
    .header-bubble-popup button.hdr-active{background:rgba(15,118,110,.3);border-color:#0f766e;color:#5eead4;}
    /* Border dropdown popup */
    .border-dropdown-popup{
        position:absolute;top:100%;left:0;z-index:10001;min-width:140px;
        background:#0f172a;border:1px solid #334155;border-radius:8px;
        box-shadow:0 8px 24px rgba(0,0,0,.5);padding:4px 0;margin-top:2px;
        display:none;
    }
    .border-dropdown-popup.abierto{display:block;}
    .border-dropdown-popup button{
        display:flex;align-items:center;gap:8px;width:100%;padding:7px 12px;
        background:none;border:none;color:#e2e8f0;font-size:12px;cursor:pointer;text-align:left;
    }
    .border-dropdown-popup button:hover{background:rgba(94,234,212,.1);}
    .border-dropdown-popup button .bdr-line{
        width:28px;height:0;border-top:2px solid #94a3b8;flex-shrink:0;
    }
    .border-dropdown-popup button .bdr-line-double{
        width:28px;height:0;border-top:3px double #94a3b8;flex-shrink:0;
    }
    .border-dropdown-popup button .bdr-line-dotted{
        width:28px;height:0;border-top:2px dotted #94a3b8;flex-shrink:0;
    }
    .border-dropdown-popup button.active{color:#5eead4;}
    /* Image resize handles in editor */
    .ql-editor img{
        position:relative;display:inline-block;max-width:100%;cursor:default;
    }
    .ql-editor img:hover{outline:2px solid rgba(94,234,212,.4);outline-offset:2px;}
    .ql-editor img.img-rsz-activo{outline:2px solid #5eead4;outline-offset:2px;}
    .img-rsz-overlay{
        position:fixed;z-index:10005;display:none;
        border:1px solid #5eead4;
        background:rgba(94,234,212,.05);
        box-shadow:0 0 0 1px rgba(15,23,42,.6);
        pointer-events:none;box-sizing:border-box;
    }
    .img-rsz-overlay.on{display:block;}
    .img-rsz-handle{
        position:absolute;width:12px;height:12px;
        background:#5eead4;border:2px solid #0f172a;border-radius:3px;
        pointer-events:auto;box-sizing:border-box;
    }
    .img-rsz-handle.nw{left:-7px;top:-7px;cursor:nwse-resize;}
    .img-rsz-handle.ne{right:-7px;top:-7px;cursor:nesw-resize;}
    .img-rsz-handle.sw{left:-7px;bottom:-7px;cursor:nesw-resize;}
    .img-rsz-handle.se{right:-7px;bottom:-7px;cursor:nwse-resize;}
    .img-rsz-label{
        position:absolute;top:-24px;right:0;background:#0f172a;
        color:#94a3b8;font-size:10px;padding:2px 6px;border-radius:4px;
        pointer-events:none;white-space:nowrap;border:1px solid #334155;
    }
    /* Color picker dropdowns dark */
    .ql-snow .ql-picker-options .ql-color-picker{background:#1e293b !important;border:1px solid #334155 !important;border-radius:8px !important;}
    .ql-snow .ql-color-picker .ql-picker-options{background:#1e293b !important;border:1px solid #334155 !important;}
    .ql-snow .ql-color-picker .ql-picker-item{border-color:#334155 !important;}
    .ql-snow .ql-color-picker .ql-picker-item:hover{border-color:#5eead4 !important;}
    .ql-snow .ql-color-picker .ql-selected{outline:2px solid #5eead4 !important;}
    .ql-snow .ql-picker-label{color:#94a3b8 !important;}
    .ql-snow .ql-picker-label:hover{color:#5eead4 !important;}
    /* Font & size picker dark */
    .ql-snow .ql-picker.ql-font .ql-picker-options{
        background:#1e293b !important;border:1px solid #334155 !important;
        border-radius:8px !important;padding:4px 0 !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-item{
        color:#94a3b8 !important;padding:4px 12px !important;font-size:13px !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-item:hover{
        color:#5eead4 !important;background:rgba(94,234,212,.08) !important;
    }
    .ql-snow .ql-picker.ql-font .ql-selected{
        color:#5eead4 !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label{
        color:#94a3b8 !important;min-width:50px !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label:hover{
        color:#5eead4 !important;
    }
    /* Header picker dark */
    .ql-snow .ql-picker.ql-header .ql-picker-options{
        background:#1e293b !important;border:1px solid #334155 !important;
        border-radius:8px !important;padding:4px 0 !important;
    }
    .ql-snow .ql-picker.ql-header .ql-picker-item{
        color:#94a3b8 !important;padding:4px 12px !important;
    }
    .ql-snow .ql-picker.ql-header .ql-picker-item:hover{
        color:#5eead4 !important;background:rgba(94,234,212,.08) !important;
    }
    .ql-snow .ql-picker.ql-header .ql-selected{color:#5eead4 !important;}
    /* Font classes - specific selectors to override .ql-container base */
    #editor-mensaje.ql-font-arial .ql-editor{font-family:'Arial',sans-serif !important;}
    #editor-mensaje.ql-font-times-new-roman .ql-editor{font-family:'Times New Roman',serif !important;}
    #editor-mensaje.ql-font-verdana .ql-editor{font-family:'Verdana',sans-serif !important;}
    #editor-mensaje.ql-font-comic-sans-ms .ql-editor{font-family:'Comic Sans MS',cursive !important;}
    #editor-mensaje.ql-font-georgia .ql-editor{font-family:'Georgia',serif !important;}
    #editor-mensaje.ql-font-courier-new .ql-editor{font-family:'Courier New',monospace !important;}
    #editor-mensaje.ql-font-inter .ql-editor{font-family:'Inter',sans-serif !important;}
    #editor-mensaje.ql-font-segoe-ui .ql-editor{font-family:'Segoe UI',sans-serif !important;}
    #editor-mensaje.ql-font-calibri .ql-editor{font-family:'Calibri',sans-serif !important;}
    /* Fallback class selectors */
    .ql-font-arial{font-family:'Arial',sans-serif !important;}
    .ql-font-times-new-roman{font-family:'Times New Roman',serif !important;}
    .ql-font-verdana{font-family:'Verdana',sans-serif !important;}
    .ql-font-comic-sans-ms{font-family:'Comic Sans MS',cursive !important;}
    .ql-font-georgia{font-family:'Georgia',serif !important;}
    .ql-font-courier-new{font-family:'Courier New',monospace !important;}
    .ql-font-inter{font-family:'Inter',sans-serif !important;}
    .ql-font-segoe-ui{font-family:'Segoe UI',sans-serif !important;}
    .ql-font-calibri{font-family:'Calibri',sans-serif !important;}
    .ql-font-sans-serif{font-family:'Arial',sans-serif !important;}
    .ql-font-serif{font-family:'Times New Roman',serif !important;}
    .ql-font-monospace{font-family:'Courier New',monospace !important;}
    #editor-mensaje{width:100%;}
    #editor-mensaje .ql-container{width:100%;}
    #editor-mensaje .line-solid{border:1px solid #94a3b8 !important;border-radius:2px;padding:1px 2px;}
    #editor-mensaje .line-double{border:2px double #94a3b8 !important;border-radius:2px;padding:1px 2px;}
    #editor-mensaje .line-dotted{border:1px dotted #94a3b8 !important;border-radius:2px;padding:1px 2px;}

    /* ===== Tooltip custom estilo Bootstrap ===== */
    #ttGlobo{animation:ttFadeIn .12s ease;}
    @keyframes ttFadeIn{from{opacity:0;transform:translateY(-2px)}to{opacity:1;transform:translateY(0)}}
    #ttGlobo::after{
        content:'';position:absolute;left:50%;top:100%;transform:translateX(-50%);
        border:5px solid transparent;border-top-color:#0e7490;
    }
    #ttGlobo.ttBelow::after{top:auto;bottom:100%;border-top-color:transparent;border-bottom-color:#0e7490;}

    /* ===== Emoji picker popup ===== */
    .emoji-picker-popup{
        position:absolute;z-index:10001;
        background:#0f172a;border:1px solid #334155;border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,.6);width:300px;
        animation:epFadeIn .15s ease;
    }
    @keyframes epFadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
    .ep-header{
        padding:8px 12px;border-bottom:1px solid #1e293b;
        font-size:12px;font-weight:700;color:#94a3b8;display:flex;align-items:center;gap:6px;
    }
    .ep-header i{color:#fbbf24;font-size:14px;}
    .ep-grid{
        display:grid;grid-template-columns:repeat(10,1fr);gap:2px;
        padding:8px;max-height:260px;overflow-y:auto;
    }
    .ep-grid::-webkit-scrollbar{width:4px;}
    .ep-grid::-webkit-scrollbar-thumb{background:#334155;border-radius:4px;}
    .ep-btn{
        width:100%;aspect-ratio:1;border:none;background:transparent;
        font-size:18px;cursor:pointer;border-radius:6px;transition:.12s;
        display:flex;align-items:center;justify-content:center;
    }
    .ep-btn:hover{background:rgba(94,234,212,.15);transform:scale(1.2);}

    /* ===== Size dropdown popup ===== */
    .size-dropdown-popup{
        position:absolute;z-index:10001;
        background:#0f172a;border:1px solid #334155;border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,.6);width:250px;
        animation:epFadeIn .15s ease;
    }
    .sd-header{
        padding:8px 12px;border-bottom:1px solid #1e293b;
        font-size:12px;font-weight:700;color:#94a3b8;display:flex;align-items:center;gap:6px;
    }
    .sd-header i{color:#fbbf24;font-size:13px;}
    .sd-grid{
        display:grid;grid-template-columns:repeat(5,1fr);gap:3px;
        padding:8px;max-height:240px;overflow-y:auto;
    }
    .sd-grid::-webkit-scrollbar{width:4px;}
    .sd-grid::-webkit-scrollbar-thumb{background:#334155;border-radius:4px;}
    .sd-btn{
        border:none;background:#1e293b;color:#94a3b8;font-size:12px;font-weight:600;
        padding:6px 0;border-radius:6px;cursor:pointer;transition:.12s;
    }
    .sd-btn:hover{background:#0f766e;color:#fff;}

    /* ===== Symbol picker popup ===== */
    .sym-picker-popup{
        position:absolute;z-index:10001;
        background:#0f172a;border:1px solid #334155;border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,.6);width:390px;max-height:420px;overflow-y:auto;
        animation:epFadeIn .15s ease;
    }
    .sym-picker-popup::-webkit-scrollbar{width:5px;}
    .sym-picker-popup::-webkit-scrollbar-thumb{background:#334155;border-radius:4px;}
    .sym-cat{
        padding:6px 12px 2px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;
    }
    .sym-item{font-size:16px !important;color:#e2e8f0 !important;}
    .sym-item:hover{background:rgba(94,234,212,.15) !important;color:#5eead4 !important;}

    /* ===== Botón flotante para log ===== */
    .log-fab-stack{
        position:fixed;bottom:24px;right:24px;z-index:9998;
        display:flex;flex-direction:column;align-items:center;gap:6px;
    }
    .log-fab{
        position:relative;
        width:48px;height:48px;border-radius:50%;border:none;cursor:pointer;
        background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 4px 18px rgba(15,118,110,.5);transition:.25s;
    }
    .log-fab:hover{transform:scale(1.1);box-shadow:0 6px 24px rgba(15,118,110,.7);}
    .log-fab i{font-size:18px;}
    .log-nav-btn{
        width:32px;height:32px;border-radius:50%;border:none;cursor:pointer;
        background:linear-gradient(135deg,#1e293b,#334155);color:#94a3b8;
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 3px 10px rgba(0,0,0,.45);transition:.25s;
    }
    .log-nav-btn:hover{transform:scale(1.1);color:#5eead4;background:linear-gradient(135deg,#0f172a,#1e293b);}
    .log-nav-btn i{font-size:12px;}
    .log-fab .fab-badge{
        position:absolute;top:-3px;right:-3px;
        background:#ef4444;color:#fff;font-size:7px;font-weight:800;
        width:16px;height:16px;min-width:16px;max-width:16px;border-radius:50%;display:none;
        align-items:center;justify-content:center;padding:0;
        border:1.5px solid #0f172a;line-height:1;
    }
    .log-fab .fab-badge.visible{display:flex;}

    /* ===== Log de proceso ===== */
    .log-contenedor{
        display:block;visibility:visible;
        margin-top:16px;background:#0a0f1a;border:1px solid #334155;
        border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.35);
    }
    .log-contenedor.d-none{display:none !important;}
    .log-header{
        display:flex;align-items:center;gap:8px;padding:8px 12px;
        background:#16233b;border-bottom:1px solid #334155;cursor:pointer;
        user-select:none;
    }
    .log-header i{color:#5eead4;font-size:13px;}
    .log-header span{color:#94a3b8;font-size:12px;font-weight:600;flex:1;}
    .log-header .log-badge{
        background:#0f766e;color:#fff;font-size:9px;font-weight:700;
        min-width:18px;width:18px;height:18px;border-radius:50%;padding:0;overflow:hidden;
        display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
    }
    .log-clear-btn{
        background:none;border:none;color:#475569;cursor:pointer;
        padding:2px 5px;border-radius:4px;font-size:11px;transition:.15s;
    }
    .log-clear-btn:hover{color:#f87171;background:rgba(248,113,113,.1);}
    .log-copy-btn,.log-export-btn{
        background:none;border:none;color:#475569;cursor:pointer;
        padding:2px 5px;border-radius:4px;font-size:11px;transition:.15s;
    }
    .log-copy-btn:hover{color:#5eead4;background:rgba(20,184,166,.12);}
    .log-export-btn:hover{color:#fbbf24;background:rgba(251,191,36,.12);}

    /* ===== Nota adjuntos ===== */
    .nota-adjuntos{
        font-size:12px;color:#64748b;line-height:1.55;
        padding:8px 12px;background:#0f172a;border:1px solid #1e293b;
        border-radius:8px;margin-top:4px;
    }
    .nota-adjuntos i{color:#38bdf8;margin-right:4px;}
    .nota-adjuntos b{color:#94a3b8;}

        /* ===== Sello postal ondulado (SVG inline, estilo sello antiguo, flat) ===== */
    .sello-prioridad{
        --prio:#2563eb;
        --prio-bg:#ffffff;
        flex:0 0 auto;
        width:118px;
        height:88px;
        box-sizing:border-box;
        margin:6px 0 0 auto;
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        text-align:center;
        transform:rotate(-6deg);
        user-select:none;
        cursor:default;
        position:relative;
        transition:color .25s ease;
    }
    .sello-svg{
        position:absolute;
        inset:0;
        width:100%;
        height:100%;
        display:block;
        pointer-events:none;
    }
    .sello-svg path.base{ fill:var(--prio-bg); stroke:var(--prio); stroke-width:2; }
    .sello-svg path.linea{ fill:none; stroke:var(--prio); stroke-width:1; stroke-opacity:.5; }
    .sello-ico{
        position:relative;
        z-index:2;
        font-size:26px;
        line-height:1;
        margin-bottom:6px;
        color:var(--prio);
    }
    .sello-valor{
        position:relative;
        z-index:2;
        font-size:18px;
        font-weight:800;
        letter-spacing:.5px;
        text-transform:uppercase;
        line-height:1.1;
        color:var(--prio);
    }
    /* ===== Mobile responsive ===== *//* ===== Mobile responsive ===== */
    @media(max-width:640px){
        .fila-principal{padding:8px !important;}
        .win-window.wide{max-width:100% !important;width:100% !important;}
        .win-body{padding:12px 10px !important;}
        .nota-adjuntos{font-size:11px;padding:6px 8px;line-height:1.5;}
        .nota-adjuntos br{display:none;}
        .ql-toolbar{flex-wrap:wrap !important;padding:3px 4px !important;}
        .ep-grid{grid-template-columns:repeat(8,1fr) !important;}
        .emoji-picker-popup{width:min(90vw,280px) !important;}
        .log-fab-stack{bottom:14px !important;right:14px !important;}
        .log-fab{width:42px !important;height:42px !important;}
        .nota-adjuntos{display:block !important;width:100% !important;}
        .form-adj-send{flex-direction:column !important;align-items:stretch !important;}
        .form-adj-col,.form-send-col{flex:none !important;width:100% !important;}
        .form-send-col{flex-direction:row !important;justify-content:center !important;flex-wrap:wrap !important;}
        .sello-prioridad{margin:10px auto 0 !important;}
    }
    .log-body{
        max-height:220px;overflow-y:auto;padding:8px 0;
        font-family:'Cascadia Code','Fira Code','Consolas',monospace;
        font-size:11.5px;line-height:1.65;
        position:relative;
    }
    .log-body:empty::after{
        content:'Sin eventos todavía';display:flex;align-items:center;justify-content:center;
        color:#475569;font-size:11px;padding:22px 0;
        font-family:'Segoe UI',Tahoma,sans-serif;
    }
    .log-body::-webkit-scrollbar{width:5px;}
    .log-body::-webkit-scrollbar-track{background:#0a0f1a;}
    .log-body::-webkit-scrollbar-thumb{background:#1e293b;border-radius:4px;}
    .log-entrada{display:flex;gap:8px;padding:2px 12px;align-items:flex-start;}
    .log-entrada .log-hora{color:#475569;min-width:68px;flex-shrink:0;}
    .log-entrada .log-icono{width:16px;text-align:center;flex-shrink:0;}
    .log-entrada .log-msg{color:#94a3b8;word-break:break-word;}
    .log-entrada.log-ok .log-icono{color:#34d399;}
    .log-entrada.log-ok .log-msg{color:#94a3b8;}
    .log-entrada.log-info .log-icono{color:#38bdf8;}
    .log-entrada.log-warn .log-icono{color:#fbbf24;}
    .log-entrada.log-warn .log-msg{color:#fbbf24;}
    .log-entrada.log-error .log-icono{color:#f87171;}
    .log-entrada.log-error .log-msg{color:#f87171;}
    .log-entrada.log-send .log-icono{color:#5eead4;}
    .log-entrada.log-send .log-msg{color:#cbd5e1;}
</style>
</head>
<body class="win-page">
<div class="bg"></div>
<div class="bg bg2"></div>
<div class="bg bg3"></div>
<div class="pagina">
    <header class="encabezado">
        <h1 class="enc-titulo"><i class="fas fa-envelope-open-text"></i>FORMULARIO DE CONTACTO</h1>
        <p class="enc-sub"><?= htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') ?> - Sistema de Gesti&oacute;n de N&oacute;minas</p>
    </header>
    <div class="fila-principal">
    <div class="win-window wide">
        <div class="win-titlebar">
            <i class="fas fa-envelope-open-text tb-icon"></i>
            <span class="tb-text">Póngase en Contacto con: — <?= htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') ?> — </span>
            <div class="win-caption">
                        <button type="button" class="cap-btn send" id="btnEnviarBarra" title="Enviar mensaje"><i class="fas fa-paper-plane"></i></button>
						<button type="button" class="cap-btn home" id="btnAdjuntarBarra" title="Adjuntar archivos"><i class="fas fa-paperclip"></i></button>
						<button type="button" class="cap-btn home" id="btnNuevoBarra" title="Nuevo / Limpiar formulario"><i class="fas fa-broom"></i></button>
                        <button type="button" class="cap-btn home" id="btnSaveEml" title="Guardar como archivo .eml"><i class="fas fa-floppy-disk"></i></button>
                <a class="cap-btn home" href="javascript:history.back()" title="Regresar Atr&aacute;s"><i class="fas fa-arrow-left"></i></a>
                <a class="cap-btn home" href="/" title="Volver al inicio"><i class="fas fa-house"></i></a>
                <a class="cap-btn home" href="/soporte.php" title="Soporte t&eacute;cnico"><i class="fas fa-headset"></i></a>
                <a class="cap-btn close" href="/" title="Cerrar"><i class="fas fa-xmark"></i></a>
            </div>
        </div>
        <div class="win-body">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h1 class="win-h1"><i class="fas fa-headset"></i>Contacta con nosotros</h1>
                    <p class="win-sub">Escribenos y te responderemos lo antes posible.</p>
                </div>
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding-top:4px">
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;user-select:none" title="Solicitar confirmacion de lectura del correo electronico"><input type="checkbox" id="chkAcuse" name="acuse_recibo" value="1" style="accent-color:#3b82f6;width:14px;height:14px;cursor:pointer"><span style="color:#94a3b8;font-size:12px"><i class="fas fa-envelope-open" style="margin-right:2px"></i> Acuse de Recibo</span></label>
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;user-select:none" title="Enviar el mensaje sin formato HTML"><input type="checkbox" id="chkTextoPlano" name="texto_plano" value="1" style="accent-color:#f59e0b;width:14px;height:14px;cursor:pointer"><span style="color:#94a3b8;font-size:12px"><i class="fas fa-font" style="margin-right:2px"></i> Texto plano</span></label>
                </div>
            </div>
<?php if (!$correoDisponible): ?>
        <script>var _runModal = function() { swalWin({
            icono:'error', titulo:'Servicio no disponible',
            texto:'El formulario de contacto no esta activo en este momento.',
            confirmarIcono:'fa-circle-check',
            toast:false, temporizador:6000
        }); };
        document.readyState === 'complete' ? _runModal() : window.addEventListener('load', _runModal);</script>
<?php else: ?>
    <?php if ($mensaje): ?>
        <script>var _runModal = () => swalWin({
            icono:'<?= $mensaje["tipo"] ?>',
            titulo:'<?= htmlspecialchars($mensaje["titulo"], ENT_QUOTES, "UTF-8") ?>',
            <?= isset($mensaje['html'])
                ? 'html:' . json_encode($mensaje['html']) . ','
                : 'texto:' . json_encode($mensaje["texto"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ',' ?>
            confirmarIcono:'<?= $mensaje["iconoBoton"] ?>',
            confirmarClase:'<?= $mensaje["claseBoton"] ?>',
            temporizador: <?= $exito ? '8000' : '6000' ?>,
            toast: false
        })<?= $exito ? '.then(() => { window.location.href = "contacto.php"; })' : '' ?>;
        document.readyState === 'complete' ? _runModal() : window.addEventListener('load', _runModal);</script>
    <?php endif; ?>
    <?php if (!$exito): ?>
<form method="post" action="contacto.php" autocomplete="off" id="formContacto" enctype="multipart/form-data" novalidate>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
    
    <?php $prioActual = !in_array($valor('prioridad'), ['baja', 'alta', 'urgente'], true) ? 'normal' : $valor('prioridad'); ?>
    <!-- Fila: Nombre + Correo + Prioridad (todos más compactos) + sello postal -->
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">
        <div style="flex:1 1 340px;min-width:270px;display:flex;gap:12px;flex-wrap:wrap;">
            <!-- Nombre -->
            <div style="flex:1;min-width:180px;max-width:380px;display:flex;flex-direction:column;">
                <label class="win-label" for="nombre"><i class="fas fa-user"></i> Tu Nombre *</label>
                <input class="win-input" id="nombre" name="nombre" maxlength="120" value="<?= $valor('nombre') ?>" required style="height:auto;padding:6px 10px;width:100%;">
            </div>
            
            <!-- Correo -->
            <div style="flex:1;min-width:200px;max-width:400px;display:flex;flex-direction:column;">
                <label class="win-label" for="correo"><i class="fas fa-at"></i> Tu correo *</label>
                <input class="win-input" id="correo" name="correo" type="email" maxlength="160" value="<?= $valor('correo') ?>" required style="height:auto;padding:6px 10px;width:100%;">
            </div>
            
            <!-- Prioridad -->
            <div style="flex:0.35;min-width:90px;max-width:118px;display:flex;flex-direction:column;">
                <label class="win-label" for="prioridad"><i class="fas fa-flag"></i> Prioridad</label>
                <div class="prio-dd" id="prioDd">
                    <button type="button" class="prio-dd-btn win-input" id="prioDdBtn" title="Prioridad" aria-haspopup="listbox" aria-expanded="false">
                        <i class="prio-dd-ico fas fa-flag"></i>
                        <span class="prio-dd-txt" id="prioDdTxt"><?= ucfirst($prioActual) ?></span>
                        <i class="prio-dd-caret fas fa-chevron-down"></i>
                    </button>
                    <ul class="prio-dd-list" id="prioDdList" role="listbox">
                        <li role="option" data-value="baja"   aria-selected="false"><i class="fas fa-arrow-down" style="color:#64748b;"></i> Baja</li>
                        <li role="option" data-value="normal" aria-selected="false"><i class="fas fa-flag" style="color:#2563eb;"></i> Normal</li>
                        <li role="option" data-value="alta"   aria-selected="false"><i class="fas fa-arrow-up" style="color:#d97706;"></i> Alta</li>
                        <li role="option" data-value="urgente" aria-selected="false"><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> Urgente</li>
                    </ul>
                </div>
                <select class="win-input" id="prioridad" name="prioridad" title="Prioridad" style="display:none;">
                    <option value="baja"<?= $prioActual === 'baja' ? ' selected' : '' ?>>Baja</option>
                    <option value="normal"<?= $prioActual === 'normal' ? ' selected' : '' ?>>Normal</option>
                    <option value="alta"<?= $prioActual === 'alta' ? ' selected' : '' ?>>Alta</option>
                    <option value="urgente"<?= $prioActual === 'urgente' ? ' selected' : '' ?>>Urgente</option>
                </select>
            </div>
        </div>
        
        <!-- Sello postal según la prioridad elegida -->
        <div class="sello-prioridad" id="selloPrioridad" data-prio="<?= $prioActual ?>" role="img" aria-label="Sello de prioridad">
            <svg class="sello-svg" viewBox="0 0 118 88" preserveAspectRatio="none" aria-hidden="true">
                <path class="base" d="M 2,1 L 9.13,11 16.25,1 23.38,11 30.5,1 37.63,11 44.75,1 51.88,11 59,1 66.13,11 73.25,1 80.38,11 87.5,1 94.63,11 101.75,1 108.88,11 116,1 A1.2,1.2 0 0 1 117,2 L 107,7.25 117,12.5 107,17.75 117,23 107,28.25 117,33.5 107,38.75 117,44 107,49.25 117,54.5 107,59.75 117,65 107,70.25 117,75.5 107,80.75 117,86 A1.2,1.2 0 0 1 116,87 L 108.88,77 101.75,87 94.63,77 87.5,87 80.38,77 73.25,87 66.13,77 59,87 51.88,77 44.75,87 37.63,77 30.5,87 23.38,77 16.25,87 9.13,77 2,87 A1.2,1.2 0 0 1 1,86 L 11,80.75 1,75.5 11,70.25 1,65 11,59.75 1,54.5 11,49.25 1,44 11,38.75 1,33.5 11,28.25 1,23 11,17.75 1,12.5 11,7.25 1,2 A1.2,1.2 0 0 1 2,1 Z"/>
                <path class="linea" d="M 2,12 L 9.13,18 16.25,12 23.38,18 30.5,12 37.63,18 44.75,12 51.88,18 59,12 66.13,18 73.25,12 80.38,18 87.5,12 94.63,18 101.75,12 108.88,18 116,12 A3,3 0 0 1 106,2 L 100,7.25 106,12.5 100,17.75 106,23 100,28.25 106,33.5 100,38.75 106,44 100,49.25 106,54.5 100,59.75 106,65 100,70.25 106,75.5 100,80.75 106,86 A3,3 0 0 1 116,76 L 108.88,70 101.75,76 94.63,70 87.5,76 80.38,70 73.25,76 66.13,70 59,76 51.88,70 44.75,76 37.63,70 30.5,76 23.38,70 16.25,76 9.13,70 2,76 A3,3 0 0 1 12,86 L 18,80.75 12,75.5 18,70.25 12,65 18,59.75 12,54.5 18,49.25 12,44 18,38.75 12,33.5 18,28.25 12,23 18,17.75 12,12.5 18,7.25 12,2 A3,3 0 0 1 2,12 Z"/>
            </svg>
            <div class="sello-ico"><i class="fas fa-flag"></i></div>
            <div class="sello-valor" id="selloValor">Normal</div>
        </div>
    </div>
    
    <!-- Asunto (más largo) -->
    <div style="display:flex;gap:12px;margin-top:12px;">
        <div style="flex:1;max-width:none;display:flex;flex-direction:column;">
            <label class="win-label" for="asunto"><i class="fas fa-tag"></i> Asunto</label>
            <input class="win-input" id="asunto" name="asunto" maxlength="150" value="<?= $valor('asunto') ?>" style="height:auto;padding:6px 10px;width:100%;">
        </div>
    </div>
    
    <!-- Mensaje -->
    <label class="win-label" for="editor-mensaje"><i class="fas fa-comment-dots"></i> Mensaje *</label>
    <div id="editor-mensaje"></div>
    <input type="hidden" id="mensaje" name="mensaje" value="<?= $valor('mensaje') ?>">
    
    <!-- Adjuntos y botón enviar -->
    <div style="display:flex;gap:12px;align-items:flex-start;margin-top:2px" class="form-adj-send">
        <div style="flex:8;min-width:0;display:flex;flex-direction:column;justify-content:flex-start" class="form-adj-col">
            <input type="file" id="adjuntos" name="adjuntos[]" multiple
                   accept=".png,.jpg,.jpeg,.gif,.pdf,.txt,.xls,.xlsx,.doc,.docx,.ppt,.pptx,.csv,.zip,.rar" hidden>
            <div class="nota-adjuntos"><i class="fas fa-circle-info"></i> <b>Nota:</b> Extensiones permitidas: png, jpg, jpeg, gif, pdf, txt, xls, xlsx, doc, docx, ppt, pptx, csv, zip, rar. M&aacute;x. <b>10 MB</b> por archivo &middot; <b>18 MB</b> en total &middot; hasta <b>100</b> archivos</div>
        </div>
        <div style="flex:4;display:flex;flex-direction:column;justify-content:flex-start;gap:8px" class="form-send-col">
            <div style="display:flex;gap:8px;align-items:stretch" class="form-enviar-fila">
                <button type="button" class="wbtn wbtn-warning btn-adjuntar" id="btnAdjuntar" title="Adjuntar archivos">
                    <i class="fas fa-paperclip"></i><span id="adjuntarContador"></span>
                </button>
                <button class="wbtn wbtn-accent" type="submit" style="flex:1"><i class="fas fa-paper-plane"></i> Enviar mensaje</button>
            </div>
            <div style="display:flex;gap:6px;justify-content:right">
                <button type="button" class="cap-btn home" id="btnNuevo" title="Nuevo / Limpiar formulario"><i class="fas fa-broom"></i></button>
                <button type="button" class="cap-btn home" id="btnToggleLog" title="Mostrar / Ocultar log"><i class="fas fa-terminal"></i></button>
                <button type="button" class="cap-btn home" id="btnSaveEml" title="Guardar como archivo .eml"><i class="fas fa-floppy-disk"></i></button>
                <a class="cap-btn home" href="javascript:history.back()" title="Regresar"><i class="fas fa-arrow-left"></i></a>
                <a class="cap-btn home" href="/" title="Inicio"><i class="fas fa-house"></i></a>
                <a class="cap-btn home" href="/soporte.php" title="Soporte"><i class="fas fa-headset"></i></a>
                <!---<a class="cap-btn close" href="/" title="Cerrar"><i class="fas fa-xmark"></i></a>---->
            </div>
        </div>
    </div>
</form>        <div class="log-contenedor d-none" id="logContenedor">
            <div class="log-header" id="logHeader">
                <i class="fas fa-terminal"></i>
                <span>Log de proceso</span>
                <span class="log-badge" id="logContador">0</span>
                <button type="button" class="log-copy-btn" id="logCopyBtn" title="Copiar logs al portapapeles"><i class="fas fa-copy"></i></button>
                <button type="button" class="log-export-btn" id="logExportBtn" title="Exportar logs a .txt"><i class="fas fa-floppy-disk"></i></button>
                <button type="button" class="log-clear-btn" id="logClearBtn" title="Borrar logs"><i class="fas fa-trash-can"></i></button>
                <i class="fas fa-chevron-down" id="logChevron" style="font-size:10px;color:#475569;transition:transform .2s"></i>
            </div>
            <div class="log-body" id="logBody" style="display:none"></div>
        </div>
        
		
		
		
		<div class="log-fab-stack">
            <button type="button" class="log-nav-btn" id="logFabInicio" title="Ir al principio de la página"><i class="fas fa-angle-double-up"></i></button>
            <button type="button" class="log-fab" id="logFab" title="Mostrar / ocultar log">
                <i class="fas fa-terminal"></i>
                <span class="fab-badge" id="logFabBadge">0</span>
            </button>
            <button type="button" class="log-nav-btn" id="logFabFinal" title="Ir al final de la página"><i class="fas fa-angle-double-down"></i></button>
        </div>
    <?php endif; ?>
<?php endif; ?>
        </div>
    </div>
<?php if ($correoDisponible && !$exito): ?>
    <aside class="panel-adjuntos" id="panelAdjuntos">
        <div class="pa-titlebar">
            <i class="fas fa-paperclip"></i> ADJUNTOS
            <span class="pa-contador" id="adjContador">0</span>
        </div>
        <div class="pa-body" id="listaAdjuntos"></div>
        <div class="pa-footer" id="adjResumen"></div>
    </aside>
    <?php endif; ?>
    </div>
    <footer class="pie">
        <?= htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') ?> - Sistema de Gesti&oacute;n de N&oacute;minas<br>
        Soporte T&eacute;cnico | Copyright &copy; 2000 - <?= date('Y') ?>. All Right Reserved to UnicornioSoftware&reg;
    </footer>
</div>
<div id="ctxMenuAdjuntos" class="ctx-menu">
    <div class="ctx-hd" id="ctxAdjNombre">archivo</div>
    <div class="ctx-item" data-acc="abrir"><i class="fas fa-eye"></i> Previsualizar</div>
    <div class="ctx-item" data-acc="descargar"><i class="fas fa-download"></i> Descargar</div>
    <div class="ctx-sep"></div>
    <div class="ctx-item" data-acc="copiar"><i class="fas fa-copy"></i> Copiar nombre</div>
    <div class="ctx-item" data-acc="renombrar"><i class="fas fa-pen"></i> Renombrar</div>
    <div class="ctx-item" data-acc="detalles"><i class="fas fa-circle-info"></i> Ver detalles</div>
    <div class="ctx-sep"></div>
    <div class="ctx-item ctx-danger" data-acc="quitar"><i class="fas fa-xmark"></i> Quitar archivo</div>
    <div class="ctx-item ctx-danger" data-acc="quitarTodos"><i class="fas fa-trash-can"></i> Quitar todos</div>
</div>
<link rel="stylesheet" href="css/katex.min.css">
<script src="js/katex.min.js"></script>
    <script src="js/typo.js"></script>
    <script src="js/sweetalert211.js"></script>
<script src="/assets/winui.js"></script>
<script src="js/quill.js"></script>
<script src="NOMINAS/js/html2canvas.min.js"></script>
<script src="NOMINAS/js/jspdf.umd.min.js"></script>
<script>
/* ===== Registrar formatos personalizados en Quill 2.0.3 ===== */
(function(){
    var Parchment = Quill.import('parchment');
    var Font = Quill.import('formats/font');
    Font.whitelist = ['arial','times-new-roman','verdana','comic-sans-ms','georgia','courier-new','inter','segoe-ui','calibri'];
    Quill.register(Font, true);
    var _sizeWhitelist = [];
    for (var _sw = 5; _sw <= 120; _sw++) _sizeWhitelist.push(_sw + 'px');
    var SizeStyle = new Parchment.StyleAttributor('size','font-size',{
        scope: Parchment.Scope.INLINE,
        whitelist: _sizeWhitelist
    });
    Quill.register(SizeStyle, true);
    var BorderStyle = new Parchment.StyleAttributor('box','border',{
        scope: Parchment.Scope.INLINE,
        whitelist: ['2px solid #94a3b8','4px double #94a3b8','2px dotted #94a3b8']
    });
    Quill.register(BorderStyle, true);
    var NoSpell = new Parchment.Attributor('nospell','spellcheck',{
        scope: Parchment.Scope.INLINE
    });
    Quill.register(NoSpell, true);
})();
</script>
<script>
(function(){
    const form = document.getElementById('formContacto');
    if (!form) return;

    /* Botón del avión en la barra de título: dispara el mismo envío */
    const btnEnviarBarra = document.getElementById('btnEnviarBarra');
    if (btnEnviarBarra) {
        btnEnviarBarra.addEventListener('click', function() {
            const b = form.querySelector('button[type=submit]');
            if (b && !b.disabled) { b.click(); }
        });
    }
    const campoNombre = document.getElementById('nombre');
    const campoCorreo = document.getElementById('correo');
    const campoMensaje = document.getElementById('mensaje');
    const campoAdjuntos = document.getElementById('adjuntos');
    const listaAdjuntos = document.getElementById('listaAdjuntos');
    /* Rango de seleccion recordado: al pulsar botones del toolbar Quill pierde el
       foco y getSelection(true) puede devolver null en el primer clic. Se actualiza
       en selection-change (ver mas abajo) y se usa como fallback en los handlers. */
    var _qlRangoGuardado = null;
    /* Seleccion de celdas de tabla (multifila/multicolumna), tipo Excel */
    var _celdasMarcadas = [];
    function _desmarcarCeldas() {
        var raiz = (typeof quill !== 'undefined' && quill) ? quill.root : document.querySelector('.ql-editor');
        if (!raiz) return;
        raiz.querySelectorAll('td.tbl-celda-marcada').forEach(function(e) { e.classList.remove('tbl-celda-marcada'); });
    }
    function _pintarCeldasSel(lista) {
        _desmarcarCeldas();
        if (lista) lista.forEach(function(e) { if (e) e.classList.add('tbl-celda-marcada'); });
    }

    /* ===== Inicializar Quill editor (español, colores, emojis) ===== */
    const EMOJIS_BASIC = [
        '😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😊',
        '😇','😍','🥰','😘','😗','😋','😛','😜','🤪','😝',
        '🤑','🤗','🤔','🤐','😐','😑','😶','😏','😒','🙄',
        '😬','😮','😯','😲','😳','🥺','😢','😭','😤','😡',
        '🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻',
        '👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀',
        '✅','❌','⚠️','🔴','🟡','🟢','🔵','⚪','⚫','🟣',
        '👍','👎','👋','🤚','✋','🖖','👌','🤌','🤏','✌️',
        '🤞','🤟','🤘','🤙','👈','👉','👆','👇','☝️','✋',
        '💪','🙏','🤝','👏','🙌','👐','🤲','💯','🎉','🎊',
        '🔥','⭐','🌟','💫','✨','💥','💢','💤','💬','💭',
        '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔',
        '📅','📌','📎','📝','📁','📂','🗂️','📊','📈','📉',
        '🔗','🔒','🔑','⚙️','🛠️','💻','🖥️','📱','📧','✉️',
        '📞','🕐','⏰','🌍','🌎','🏠','🏢','🏭','🏥','🏫'
    ];
    let emojiPickerEl = null;
    function _emojiSvgDataUri(emoji) {
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">' +
            '<text x="64" y="64" font-size="90" text-anchor="middle" dominant-baseline="central" ' +
            'font-family="Segoe UI Emoji,Apple Color Emoji,Noto Color Emoji,sans-serif">' + emoji + '</text></svg>';
        return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
    }
    function crearEmojiPicker() {
        if (emojiPickerEl) { emojiPickerEl.remove(); emojiPickerEl = null; return; }
        emojiPickerEl = document.createElement('div');
        emojiPickerEl.className = 'emoji-picker-popup';
        let html = '<div class="ep-header"><i class="fas fa-face-smile"></i> Emojis</div><div class="ep-grid">';
        EMOJIS_BASIC.forEach(function(e) {
            html += '<button type="button" class="ep-btn" data-emoji="' + e + '">' + e + '</button>';
        });
        html += '</div>';
        emojiPickerEl.innerHTML = html;
        document.body.appendChild(emojiPickerEl);
        /* Posicionar junto al toolbar */
        const toolbarBtn = document.querySelector('.ql-toolbar .ql-emoji-btn');
        if (toolbarBtn) {
            const r = toolbarBtn.getBoundingClientRect();
            emojiPickerEl.style.position = 'fixed';
            emojiPickerEl.style.top = (r.bottom + 6) + 'px';
            emojiPickerEl.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 310)) + 'px';
        }
        emojiPickerEl.querySelectorAll('.ep-btn').forEach(function(btn) {
            btn.addEventListener('click', function(ev) {
                ev.preventDefault();
                const emoji = btn.getAttribute('data-emoji');
                const range = quill.getSelection(true);
                const srcSvg = _emojiSvgDataUri(emoji);
                quill.insertEmbed(range.index, 'image', srcSvg, Quill.sources.USER);
                setTimeout(function() {
                    var imgs = quill.root.querySelectorAll('img');
                    var last = imgs[imgs.length - 1];
                    if (last) {
                        last.setAttribute('style', 'display:inline-block;width:1.4em;height:1.4em;vertical-align:middle;margin:0 2px;');
                        if (typeof _rszActivar === 'function') _rszActivar(last);
                    }
                }, 10);
            });
        });
    }

    var REPORTE_CSS = '.rep-root{font-family:"Segoe UI",Arial,Helvetica,sans-serif;color:#1e293b;line-height:1.6;}' +
        '.rep-root,.rep-root *{box-sizing:border-box;}' +
        '.rep-sheet{width:8.5in;height:11in;padding:.6in .7in .7in;page-break-after:always;position:relative;overflow:hidden;background:#fff;margin:0 auto;}' +
        '.rep-sheet:last-child{page-break-after:auto;}' +
        '.rep-page-body{height:calc(100% - 0.6in);}' +
        '.rep-page-footer{position:absolute;left:.7in;right:.7in;bottom:.38in;text-align:center;font-size:10px;line-height:16px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:6px;height:23px;}' +
        '.rep-header{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #0f766e;padding-bottom:14px;margin-bottom:22px;}' +
        '.rep-header-left h1{font-size:22px;color:#0f766e;margin:0;letter-spacing:.3px;}' +
        '.rep-header-left p{font-size:12px;color:#64748b;margin-top:4px;}' +
        '.rep-header-right{text-align:right;font-size:11px;color:#64748b;}' +
        '.rep-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;}' +
        '.rep-meta-table{width:100%;border-collapse:collapse;margin-bottom:22px;}' +
        '.rep-meta-table td{padding:10px 16px;font-size:13px;border-bottom:1px solid #e2e8f0;}' +
        '.rep-meta-table td:first-child{width:140px;color:#64748b;font-weight:600;background:#f8fafc;}' +
        '.rep-meta-table td:last-child{color:#0f172a;font-weight:500;}' +
        '.rep-msg-label{font-size:13px;font-weight:700;color:#64748b;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;}' +
        '.rep-msg-body{background:#f8fafc;border-left:4px solid #3b82f6;border-radius:0 8px 8px 0;padding:14px 18px;color:#334155;font-size:14px;line-height:1.8;margin-bottom:10px;}';

    function _armarReportePaginas(doc, empresa, oculto) {
        var nombre = campoNombre ? campoNombre.value.trim() : '';
        var correo = campoCorreo ? campoCorreo.value.trim() : '';
        var asunto = doc.getElementById('asunto') ? doc.getElementById('asunto').value.trim() : '';
        var prioridad = doc.getElementById('prioridad') ? doc.getElementById('prioridad').value : 'normal';
        var prioMap = {'baja':'Baja','normal':'Normal','alta':'Alta','urgente':'Urgente'};
        var prioLabel = prioMap[prioridad] || 'Normal';
        var prioColor = {'baja':'#64748b','normal':'#2563eb','alta':'#d97706','urgente':'#dc2626'}[prioridad] || '#2563eb';
        var fecha = new Date();
        var fechaStr = fecha.toLocaleDateString('es-ES',{day:'2-digit',month:'long',year:'numeric'});
        var horaStr = fecha.toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit',hour12:true});
        var raiz = doc.createElement('div');
        raiz.className = 'rep-root';
        var prefijo = '<div class="rep-header"><div class="rep-header-left"><h1>' + empresa + '</h1><p>Reporte de Formulario de Contacto</p></div><div class="rep-header-right"><div>' + fechaStr + '</div><div style="margin-top:2px;">' + horaStr + '</div><div style="margin-top:4px;"><span class="rep-badge" style="background:' + prioColor + ';">Prioridad: ' + prioLabel + '</span></div></div></div>' +
            '<table class="rep-meta-table"><tr><td>Remitente</td><td>' + (nombre || '-') + '</td></tr><tr><td>Correo</td><td>' + (correo || '-') + '</td></tr><tr><td>Asunto</td><td>' + (asunto || 'Sin asunto') + '</td></tr><tr><td>Prioridad</td><td><span class="rep-badge" style="background:' + prioColor + ';">' + prioLabel + '</span></td></tr><tr><td>Fecha / Hora</td><td>' + fechaStr + ' ' + horaStr + '</td></tr></table>' +
            '<div class="rep-msg-label">Mensaje</div>';
        var srcMsj = doc.createElement('div');
        srcMsj.innerHTML = quill.root.innerHTML;
        var parrafos = [];
        while (srcMsj.firstChild) parrafos.push(srcMsj.removeChild(srcMsj.firstChild));
        if (oculto) {
            raiz.style.position = 'fixed';
            raiz.style.left = '-100000px';
            raiz.style.top = '0';
            raiz.style.width = '8.5in';
        }
        if (doc.body) doc.body.appendChild(raiz);
        var hojas = [];
        var hoja, cuerpoPag, msgBody, pie, limite = 0;
        function nuevaPagina(conPrefijo) {
            hoja = doc.createElement('div');
            hoja.className = 'rep-sheet';
            cuerpoPag = doc.createElement('div');
            cuerpoPag.className = 'rep-page-body';
            hoja.appendChild(cuerpoPag);
            if (conPrefijo) {
                var tmpP = doc.createElement('div');
                tmpP.innerHTML = prefijo;
                while (tmpP.firstChild) cuerpoPag.appendChild(tmpP.firstChild);
            }
            msgBody = doc.createElement('div');
            msgBody.className = 'rep-msg-body';
            cuerpoPag.appendChild(msgBody);
            pie = doc.createElement('div');
            pie.className = 'rep-page-footer';
            hoja.appendChild(pie);
            raiz.appendChild(hoja);
            var cuerpoTop = cuerpoPag.offsetTop;
            var pieTop = pie.offsetTop;
            limite = pieTop - cuerpoTop - 10;
            hojas.push({ hoja: hoja, cuerpo: cuerpoPag, msg: msgBody, pie: pie });
        }
        nuevaPagina(true);
        for (var ip = 0; ip < parrafos.length; ip++) {
            msgBody.appendChild(parrafos[ip]);
            if (cuerpoPag.scrollHeight > limite + 1) {
                msgBody.removeChild(parrafos[ip]);
                nuevaPagina(false);
                msgBody.appendChild(parrafos[ip]);
            }
        }
        var total = hojas.length;
        for (var i = 0; i < total; i++) {
            hojas[i].pie.innerHTML = 'P\u00e1gina ' + (i + 1) + ' de ' + total + ' &mdash; ' + empresa;
        }
        return raiz;
    }

    function _imprimirReporte() {
        var empresa = '<?= htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') ?>';
        var w = window.open('', '_blank', 'width=850,height=1100');
        w.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Reporte de mensaje</title>');
        w.document.write('<style>');
        w.document.write('@page{size:letter portrait;margin:0;}');
        w.document.write('*{margin:0;padding:0;box-sizing:border-box;}');
        w.document.write('html,body{width:100%;background:#707070;}');
        w.document.write(REPORTE_CSS);
        w.document.write('.rep-sheet{margin:8px auto;}');
        w.document.write('@media print{.rep-sheet{margin:0 auto !important;}}');
        w.document.write('</style></head><body></body></html>');
        w.document.close();
        setTimeout(function() {
            _armarReportePaginas(w.document, empresa);
            setTimeout(function() { w.focus(); w.print(); }, 300);
        }, 100);
    }

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            if (typeof quill === 'undefined') return;
            _imprimirReporte();
        }
    });

    const quill = new Quill('#editor-mensaje', {
        theme: 'snow',
        placeholder: 'Escribe tu mensaje aqui...',
        modules: {
            table: true,
            toolbar: {
                container: [
                        [
                            'undo-btn', 'redo-btn', 'case-btn', 'header-btn', { 'font': ['arial', 'times-new-roman', 'verdana', 'comic-sans-ms', 'georgia', 'courier-new', 'inter', 'segoe-ui', 'calibri'] }, 'border-btn', 'bold', 'italic', 'underline', 'strike', { 'script': 'sub' }, { 'script': 'super' }, 'size-mb', 'size-custom', 'size-pb'
                        ],
                        [
                            { 'color': [] }, { 'background': [] }, { 'align': [] }, { 'direction': 'rtl' }, 'blockquote', 'code-block', 'formula', { 'list': 'ordered' }, { 'list': 'bullet' }, { 'list': 'check' }, { 'indent': '-1' }, { 'indent': '+1' }, 'link', 'video', 'clean', 'sym-btn', 'table-btn', 'img-btn', 'attach-btn', 'emoji-btn', 'html-btn', 'print-btn', 'pdf-btn'
                        ]
                    ],
                handlers: {
                    'undo-btn': function() { quill.history.undo(); },
                    'redo-btn': function() { quill.history.redo(); },
                    'header-btn': function() {
                        /* Reemplazado en tiempo de ejecucion por un <select class="ql-header-select"> */
                    },
                    'case-btn': function() {
                        /* Reemplazado en tiempo de ejecucion por el dropdown de mayusculas */
                    },
                    'emoji-btn': function() { crearEmojiPicker(); },
                    'attach-btn': function() { if (campoAdjuntos) campoAdjuntos.click(); },
                    'print-btn': function() { _imprimirReporte(); },
                    'pdf-btn': function() {
                        var empresa = '<?= htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8') ?>';
                        if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined' || !window.jspdf.jsPDF) {
                            logEvent('err', 'No se cargaron las librer\u00edas del PDF');
                            alert('No se cargaron las librer\u00edas del PDF');
                            return;
                        }
                        if (!document.getElementById('rep-css') && typeof REPORTE_CSS === 'string') {
                            var st = document.createElement('style');
                            st.id = 'rep-css';
                            st.textContent = REPORTE_CSS;
                            document.head.appendChild(st);
                        }
                        logEvent('ok', 'Generando PDF...');
                        var raiz = _armarReportePaginas(document, empresa, true);
                        var hojas = raiz.querySelectorAll('.rep-sheet');
                        var pdf = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter', compress: true });
                        (function siguiente(i) {
                            if (i >= hojas.length) {
                                var d = new Date();
                                function dos(n) { return ('0' + n).slice(-2); }
                                var ddmmyyyy = dos(d.getDate()) + '-' + dos(d.getMonth() + 1) + '-' + d.getFullYear();
                                var h12 = d.getHours() % 12; if (h12 === 0) h12 = 12;
                                var ap = d.getHours() >= 12 ? 'pm' : 'am';
                                var hhmmss = dos(h12) + '-' + dos(d.getMinutes()) + '-' + dos(d.getSeconds()) + '-' + ap;
                                var nom = 'CorreoContacto-' + ddmmyyyy + '-' + hhmmss + '.pdf';
                                pdf.save(nom);
                                raiz.parentNode.removeChild(raiz);
                                logEvent('ok', 'PDF guardado: ' + nom);
                                return;
                            }
                            html2canvas(hojas[i], { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false }).then(function(canvas) {
                                var img = canvas.toDataURL('image/jpeg', 0.95);
if (i > 0) pdf.addPage('letter', 'portrait');
                pdf.addImage(img, 'JPEG', 0, 0, 215.9, 279.4, undefined, 'FAST');
                                siguiente(i + 1);
                            });
                        })(0);
                    },
                    'size-mb': function() {
                        var sel = _qlRangoGuardado || quill.getSelection(true) || { index: 0, length: 0 };
                        var f = quill.getFormat(sel.index, Math.max(1, sel.length));
                        var cur = parseFloat(f.size) || 0;
                        if (!cur) {
                            try {
                                var hoja = quill.getLeaf(sel.index);
                                if (hoja && hoja[0]) cur = parseFloat(window.getComputedStyle(hoja[0]).fontSize);
                            } catch (e) { cur = 0; }
                        }
                        if (!cur) cur = parseFloat(window.getComputedStyle(quill.root).fontSize) || 14;
                        var nv = Math.max(5, Math.round((cur - 1) * 10) / 10);
                        quill.formatText(sel.index, sel.length || 1, 'size', nv + 'px');
                        logEvent('ok', 'Tamaño -1pt: ' + nv + 'px');
                    },
                    'size-pb': function() {
                        var sel = _qlRangoGuardado || quill.getSelection(true) || { index: 0, length: 0 };
                        var f = quill.getFormat(sel.index, Math.max(1, sel.length));
                        var cur = parseFloat(f.size) || 0;
                        if (!cur) {
                            try {
                                var hoja = quill.getLeaf(sel.index);
                                if (hoja && hoja[0]) cur = parseFloat(window.getComputedStyle(hoja[0]).fontSize);
                            } catch (e) { cur = 0; }
                        }
                        if (!cur) cur = parseFloat(window.getComputedStyle(quill.root).fontSize) || 14;
                        var nv = Math.min(120, Math.round((cur + 1) * 10) / 10);
                        quill.formatText(sel.index, sel.length || 1, 'size', nv + 'px');
                        logEvent('ok', 'Tamaño +1pt: ' + nv + 'px');
                    },
                    'size-custom': function() {
                        var existing = document.querySelector('.size-dropdown-popup');
                        document.querySelectorAll('.font-dropdown-popup, .case-dropdown-popup, .sym-picker-popup').forEach(function(p) { p.style.display = 'none'; });
                        var syp2 = document.querySelector('.sym-picker-popup');
                        if (syp2) syp2.remove();
                        if (existing) { existing.remove(); return; }
                        var dd = document.createElement('div');
                        dd.className = 'size-dropdown-popup';
                        var sizes = [5,6,7,8,9,10,11,12,14,16,18,20,22,24,28,32,36,40,48,56,64,72,80,96,100];
                        var html = '<div class="sd-header"><i class="fas fa-text-height"></i> Tamaño de letra</div><div class="sd-grid">';
                        sizes.forEach(function(sz) { html += '<button type="button" class="sd-btn" data-size="' + sz + '">' + sz + '</button>'; });
                        html += '</div>';
                        dd.innerHTML = html;
                        document.body.appendChild(dd);
                        var btn = document.querySelector('.ql-size-custom');
                        if (btn) {
                            var r = btn.getBoundingClientRect();
                            dd.style.position = 'fixed';
                            dd.style.top = (r.bottom + 6) + 'px';
                            dd.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 260)) + 'px';
                        }
                        dd.querySelectorAll('.sd-btn').forEach(function(b) {
                            b.addEventListener('click', function(ev) {
                                ev.preventDefault();
                                var sel = quill.getSelection(true);
                                quill.formatText(sel.index, sel.length || 1, 'size', b.getAttribute('data-size') + 'px');
                                dd.remove();
                            });
                        });
                    },
                    'border-btn': function() {
                        /* Reemplazado en tiempo de ejecucion por un <select class="ql-border-select"> */
                    },
                    'sym-btn': function() {
                        var existing = document.querySelector('.sym-picker-popup');
                        if (existing) { existing.remove(); return; }
                        var picker = document.createElement('div');
                        picker.className = 'sym-picker-popup';
                        var SYMS = {
                            'Matematicas': ['\u00b1','\u00d7','\u00f7','\u2260','\u2264','\u2265','\u221e','\u2211','\u220f','\u222b','\u221a','\u2248','\u221c','\u221d','\u2234','\u2235','\u223c','\u2261','\u2237','\u2295'],
                            'Monedas': ['\u00a2','\u00a3','\u00a5','\u20ac','\u20b9','\u20a0','\u20a1','\u20a2','\u20a4','\u20a5','\u20a6','\u20a7','\u20a8','\u20a9','\u20aa','\u20ab','\u20ad','\u20ae','\u20af','\u20b1'],
                            'Flechas': ['\u2190','\u2191','\u2192','\u2193','\u2194','\u2195','\u2196','\u2197','\u2198','\u2199','\u21a9','\u21aa','\u2b05','\u2b06','\u2b07','\u21a6','\u21a4','\u21c4','\u21c5','\u21c6'],
                            'Formas': ['\u25a0','\u25a1','\u25b2','\u25b3','\u25bc','\u25bd','\u25c6','\u25c7','\u25cf','\u25d0','\u25d1','\u2605','\u2606','\u2663','\u2660','\u2665','\u2666','\u2609','\u263c','\u2318'],
                            'Puntuacion': ['\u00ab','\u00bb','\u00a1','\u00bf','\u2039','\u203a','\u201c','\u201d','\u2018','\u2019','\u2013','\u2014','\u2026','\u2025','\u00b7','\u2022','\u25e6','\u2020','\u2021','\u00a7'],
                            'Latin/Griegas': ['\u00c0','\u00c1','\u00c2','\u00c3','\u00c4','\u00c5','\u00c6','\u00c7','\u00c8','\u00c9','\u03b1','\u03b2','\u03b3','\u03b4','\u03b5','\u03b6','\u03b7','\u03b8','\u03b9','\u03ba'],
                            'Util': ['\u00a9','\u00ae','\u2122','\u2103','\u2109','\u00b0','\u2116','\u2117','\u00a4','\u00a6','\u00ac','\u00ae','\u2302','\u2310','\u2312','\u2313','\u2318','\u2319','\u2328','\u23cf']
                        };
                        var html = '<div class="ep-header"><i class="fas fa-omega"></i> Caracteres especiales</div>';
                        Object.keys(SYMS).forEach(function(cat) {
                            html += '<div class="sym-cat">' + cat + '</div><div class="ep-grid" style="grid-template-columns:repeat(10,1fr);">';
                            SYMS[cat].forEach(function(s) { html += '<button type="button" class="ep-btn sym-item" data-sym="' + s + '">' + s + '</button>'; });
                            html += '</div>';
                        });
                        picker.innerHTML = html;
                        document.body.appendChild(picker);
                        var btn = document.querySelector('.ql-sym-btn');
                        if (btn) {
                            var r = btn.getBoundingClientRect();
                            picker.style.position = 'fixed';
                            picker.style.top = (r.bottom + 6) + 'px';
                            picker.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 400)) + 'px';
                        }
                        picker.querySelectorAll('.sym-item').forEach(function(b) {
                            b.addEventListener('click', function(ev) {
                                ev.preventDefault();
                                var sel = quill.getSelection(true);
                                quill.insertText(sel.index, b.getAttribute('data-sym'), Quill.sources.USER);
                                quill.setSelection(sel.index + 1, Quill.sources.SILENT);
                            });
                        });
                    },
                    'table-btn': function() {
                        var existing = document.querySelector('.table-modal-overlay');
                        if (existing) { existing.remove(); return; }
                        var modal = document.createElement('div');
                        modal.className = 'table-modal-overlay';
                        modal.style.cssText = 'position:fixed;inset:0;z-index:10002;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.6);';
                        modal.innerHTML =
                            '<div style="background:#0f172a;border:1px solid #334155;border-radius:14px;width:min(94vw,560px);box-shadow:0 12px 44px rgba(0,0,0,.7);overflow:hidden;">' +
                            '<div style="display:flex;align-items:center;gap:8px;padding:14px 18px;background:#1e293b;border-bottom:1px solid #334155;">' +
                            '<i class="fas fa-table" style="color:#5eead4;font-size:14px;"></i>' +
                            '<span style="color:#e2e8f0;font-size:13px;font-weight:700;flex:1;">Insertar tabla</span>' +
                            '<button id="tblClose" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:16px;"><i class="fas fa-xmark"></i></button>' +
                            '</div>' +
                            '<div style="padding:20px;">' +
                            '<div style="display:flex;gap:14px;margin-bottom:14px;">' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">Filas</label>' +
                            '<div style="display:flex;align-items:center;gap:6px;">' +
                            '<button type="button" id="tblRowMinus" style="width:32px;height:32px;border:1px solid #334155;background:#1e293b;color:#94a3b8;border-radius:8px;cursor:pointer;font-weight:700;"><i class="fas fa-minus"></i></button>' +
                            '<input id="tblRows" type="number" min="1" max="50" value="3" style="flex:1;width:100%;text-align:center;padding:6px 8px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:13px;box-sizing:border-box;outline:none;">' +
                            '<button type="button" id="tblRowPlus" style="width:32px;height:32px;border:1px solid #334155;background:#1e293b;color:#94a3b8;border-radius:8px;cursor:pointer;font-weight:700;"><i class="fas fa-plus"></i></button>' +
                            '</div></div>' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">Columnas</label>' +
                            '<div style="display:flex;align-items:center;gap:6px;">' +
                            '<button type="button" id="tblColMinus" style="width:32px;height:32px;border:1px solid #334155;background:#1e293b;color:#94a3b8;border-radius:8px;cursor:pointer;font-weight:700;"><i class="fas fa-minus"></i></button>' +
                            '<input id="tblCols" type="number" min="1" max="20" value="3" style="flex:1;width:100%;text-align:center;padding:6px 8px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:13px;box-sizing:border-box;outline:none;">' +
                            '<button type="button" id="tblColPlus" style="width:32px;height:32px;border:1px solid #334155;background:#1e293b;color:#94a3b8;border-radius:8px;cursor:pointer;font-weight:700;"><i class="fas fa-plus"></i></button>' +
                            '</div></div>' +
                            '</div>' +
                            '<label style="display:flex;align-items:center;gap:8px;color:#94a3b8;font-size:12px;cursor:pointer;margin-bottom:4px;">' +
                            '<input type="checkbox" id="tblHeader" checked style="accent-color:#0f766e;"> Fila de encabezado</label>' +
                            '<label style="display:flex;align-items:center;gap:8px;color:#94a3b8;font-size:12px;cursor:pointer;margin-bottom:4px;">' +
                            '<input type="checkbox" id="tblFull" checked style="accent-color:#0f766e;"> Ancho completo (100%)</label>' +
                            '<div style="margin-top:10px;"><label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">Estilo de tabla</label>' +
                            '<select id="tblStyle" style="width:100%;padding:6px 8px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:12px;cursor:pointer;outline:none;">' +
                            '<option value="clasica">Clasica (bordes completos)</option>' +
                            '<option value="solo-lineas">Solo lineas horizontales</option>' +
                            '<option value="sin-bordes">Sin bordes</option>' +
                            '<option value="lineas-grises">Lineas finas grises</option>' +
                            '<option value="encabezado-azul">Encabezado azul</option>' +
                            '<option value="zebra">Filas zebra</option>' +
                            '<option value="oscura">Tabla oscura</option>' +
                            '<option value="doble-linea">Doble linea</option>' +
                            '<option value="encabezado-verde">Encabezado verde</option>' +
                            '<option value="encabezado-rojo">Encabezado rojo</option>' +
                            '<option value="encabezado-violeta">Encabezado violeta</option>' +
                            '<option value="encabezado-naranja">Encabezado naranja</option>' +
                            '<option value="encabezado-rosa">Encabezado rosa</option>' +
                            '<option value="menta">Tabla menta</option>' +
                            '<option value="azul-marino">Azul marino</option>' +
                            '</select></div>' +
                            '<div style="display:flex;gap:12px;margin-top:10px;">' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">Estilo de bordes</label>' +
                            '<select id="tblBorder" style="width:100%;padding:6px 8px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:12px;cursor:pointer;outline:none;">' +
                            '<option value="all">Completos</option>' +
                            '<option value="line">Solo lineas horizontales</option>' +
                            '<option value="double">Dobles</option>' +
                            '<option value="none">Sin bordes</option>' +
                            '</select></div>' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">Alineacion</label>' +
                            '<select id="tblAlig" style="width:100%;padding:6px 8px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:12px;cursor:pointer;outline:none;">' +
                            '<option value="left">Izquierda</option>' +
                            '<option value="center">Centrada</option>' +
                            '<option value="right">Derecha</option>' +
                            '</select></div>' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">Ajuste</label>' +
                            '<select id="tblPad" style="width:100%;padding:6px 8px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:12px;cursor:pointer;outline:none;">' +
                            '<option value="com">Compacto</option>' +
                            '<option value="nor" selected>Normal</option>' +
                            '<option value="amp">Amplio</option>' +
                            '</select></div>' +
                            '</div>' +
                            '<div id="tblPreview" style="margin-top:12px;background:#e2e8f0;border:1px dashed #475569;border-radius:8px;padding:10px;overflow:auto;"></div>' +
                            '<div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">' +
                            '<button id="tblCancel" style="padding:7px 16px;border-radius:8px;border:1px solid #334155;background:transparent;color:#94a3b8;font-size:12px;font-weight:700;cursor:pointer;"><i class="fas fa-xmark"></i> Cancelar</button>' +
                            '<button id="tblInsert" style="padding:7px 18px;border-radius:8px;border:none;background:#0f766e;color:#fff;font-size:12px;font-weight:700;cursor:pointer;"><i class="fas fa-check"></i> Insertar</button>' +
                            '</div></div></div>';
                        document.body.appendChild(modal);
                        var iptR = modal.querySelector('#tblRows');
                        var iptC = modal.querySelector('#tblCols');
                        var chkHdr = modal.querySelector('#tblHeader');
                        var chkFull = modal.querySelector('#tblFull');
                        var selBorder = modal.querySelector('#tblBorder');
                        var selAlig = modal.querySelector('#tblAlig');
                        var selPad = modal.querySelector('#tblPad');
                        var selStyle = modal.querySelector('#tblStyle');
                        var preview = modal.querySelector('#tblPreview');
                        var padMap = { 'com': '4px 8px', 'nor': '8px 12px', 'amp': '12px 16px' };
                        var ESTILOS_TABLA = {
                            'clasica':          { border: 'all',  pad: 'nor', header: 1 },
                            'solo-lineas':      { border: 'line', pad: 'nor', header: 1 },
                            'sin-bordes':       { border: 'none', pad: 'nor', header: 0 },
                            'lineas-grises':    { border: 'all',  pad: 'com', header: 0, lineColor: '#cbd5e1' },
                            'encabezado-azul':  { border: 'all',  pad: 'nor', header: 1 },
                            'zebra':            { border: 'line', pad: 'nor', header: 1 },
                            'oscura':           { border: 'all',  pad: 'amp', header: 1 },
                            'doble-linea':      { border: 'double', pad: 'nor', header: 0, lineColor: '#94a3b8' },
                            'encabezado-verde':   { border: 'all', pad: 'nor', header: 1, headerBg: '#bbf7d0', headerColor: '#14532d' },
                            'encabezado-rojo':    { border: 'all', pad: 'nor', header: 1, headerBg: '#fecaca', headerColor: '#7f1d1d' },
                            'encabezado-violeta': { border: 'all', pad: 'nor', header: 1, headerBg: '#ddd6fe', headerColor: '#4c1d95' },
                            'encabezado-naranja': { border: 'all', pad: 'nor', header: 1, headerBg: '#fed7aa', headerColor: '#7c2d12' },
                            'encabezado-rosa':    { border: 'all', pad: 'nor', header: 1, headerBg: '#fbcfe8', headerColor: '#831843' },
                            'menta':            { border: 'all', pad: 'nor', header: 1, lineColor: '#99f6e4', headerBg: '#5eead4', headerColor: '#134e4a' },
                            'azul-marino':      { border: 'all', pad: 'amp', header: 1, lineColor: '#c7d2fe', headerBg: '#1e3a8a', headerColor: '#ffffff', bodyBg: '#eef2ff' }
                        };
                        function aplicarEstiloPreset() {
                            var e = ESTILOS_TABLA[selStyle.value] || ESTILOS_TABLA['clasica'];
                            selBorder.value = e.border;
                            selPad.value = e.pad;
                            chkHdr.checked = !!e.header;
                            renderPreview();
                        }
                        selStyle.addEventListener('change', aplicarEstiloPreset);
                        function clampVal(inp, lo, hi, def) {
                            var v = parseInt(inp.value, 10);
                            if (isNaN(v) || v === '') v = def;
                            inp.value = Math.max(lo, Math.min(hi, v));
                            return parseInt(inp.value, 10);
                        }
                        function renderPreview() {
                            var rows = clampVal(iptR, 1, 50, 3);
                            var cols = clampVal(iptC, 1, 20, 3);
                            var header = chkHdr.checked;
                            var border = selBorder.value;
                            var alig = selAlig.value;
                            var estilo = selStyle.value;
                            var pad = padMap[selPad.value] || '8px 12px';
                            var frag = document.createElement('div');
                            frag.style.display = 'grid';
                            frag.style.gridTemplateColumns = 'repeat(' + cols + ',minmax(34px,1fr))';
                            frag.style.gap = '2px';
                            var cellH = '26px';
                            for (var r = 0; r < rows; r++) {
                                var isHeader = header && r === 0;
                                for (var c = 0; c < cols; c++) {
                                    var cell = document.createElement('div');
                                    cell.style.minWidth = '26px';
                                    cell.style.minHeight = cellH;
                                    cell.style.height = 'auto';
                                    cell.style.lineHeight = '16px';
                                    cell.style.fontSize = '10px';
                                    cell.style.boxSizing = 'border-box';
                                    cell.style.padding = pad;
                                    cell.style.overflow = 'hidden';
                                    cell.style.textAlign = alig;
                                    cell.style.wordBreak = 'break-word';
                                    var st = ESTILOS_TABLA[estilo] || ESTILOS_TABLA['clasica'];
                                    var lineColor = st.lineColor || '#94a3b8';
                                    var bg = isHeader ? (st.headerBg || '#dbeafe') : (st.bodyBg || '#ffffff');
                                    var tcol = isHeader ? (st.headerColor || '#1e3a8a') : '#0f172a';
                                    if (estilo === 'oscura') {
                                        bg = isHeader ? '#1e293b' : '#0f172a';
                                        tcol = isHeader ? '#5eead4' : '#e2e8f0';
                                    } else if (estilo === 'zebra' && header && r % 2 === 1) {
                                        bg = '#f1f5f9';
                                    } else if (estilo === 'lineas-grises' && r % 2 === 1) {
                                        bg = '#f8fafc';
                                    } else if ((estilo === 'zebra' || estilo === 'menta') && header && r % 2 === 1) {
                                        bg = (st.bodyZebraBg || '#f1f5f9');
                                    }
                                    if (border === 'all') {
                                        cell.style.border = '1px solid ' + lineColor;
                                        cell.style.background = bg;
                                        cell.style.color = tcol;
                                    } else if (border === 'line') {
                                        cell.style.background = bg;
                                        cell.style.color = tcol;
                                        if (r === 0) cell.style.borderTop = '1px solid ' + lineColor;
                                        if (r === rows - 1) cell.style.borderBottom = '1px solid ' + lineColor;
                                        else cell.style.borderBottom = '1px solid ' + lineColor;
                                    } else if (border === 'double') {
                                        cell.style.border = '3px double ' + lineColor;
                                        cell.style.background = bg;
                                        cell.style.color = tcol;
                                    } else {
                                        cell.style.background = bg;
                                        cell.style.color = tcol;
                                        cell.style.borderBottom = (estilo === 'lineas-grises') ? '1px solid ' + lineColor : 'none';
                                    }
                                    cell.style.fontWeight = isHeader ? '700' : '400';
                                    cell.textContent = isHeader ? ('C' + (c + 1)) : '\u00A0';
                                    frag.appendChild(cell);
                                }
                            }
                            preview.innerHTML = '';
                            preview.appendChild(frag);
                        }
                        function bindStep(btnId, inp, delta, lo, hi, def) {
                            var btn = modal.querySelector(btnId);
                            if (btn) btn.addEventListener('click', function(ev) {
                                ev.preventDefault();
                                var cur = parseInt(inp.value, 10);
                                cur = isNaN(cur) ? def : cur;
                                inp.value = Math.max(lo, Math.min(hi, cur + delta));
                                renderPreview();
                            });
                        }
                        bindStep('#tblRowMinus', iptR, -1, 1, 50, 3);
                        bindStep('#tblRowPlus', iptR, 1, 1, 50, 3);
                        bindStep('#tblColMinus', iptC, -1, 1, 20, 3);
                        bindStep('#tblColPlus', iptC, 1, 1, 20, 3);
                        iptR.addEventListener('input', renderPreview);
                        iptC.addEventListener('input', renderPreview);
                        chkHdr.addEventListener('change', renderPreview);
                        chkFull.addEventListener('change', renderPreview);
                        selBorder.addEventListener('change', renderPreview);
                        selAlig.addEventListener('change', renderPreview);
                        selPad.addEventListener('change', renderPreview);
                        modal.querySelector('#tblClose').onclick = function() { modal.remove(); };
                        modal.querySelector('#tblCancel').onclick = function() { modal.remove(); };
                        modal.addEventListener('click', function(ev) { if (ev.target === modal) modal.remove(); });
                        modal.addEventListener('keydown', function(ev) {
                            if (ev.key === 'Escape') { modal.remove(); }
                            if (ev.key === 'Enter') { ev.preventDefault(); iptR.value = clampVal(iptR, 1, 50, 3); iptC.value = clampVal(iptC, 1, 20, 3); modal.querySelector('#tblInsert').click(); }
                        });
                        renderPreview();
                        if (iptR) iptR.focus();
                        if (iptR && typeof iptR.select === 'function') iptR.select();
                        modal.querySelector('#tblInsert').onclick = function() {
                            var rows = clampVal(iptR, 1, 50, 3);
                            var cols = clampVal(iptC, 1, 20, 3);
                            var hasHeader = chkHdr.checked;
                            var full = chkFull.checked;
                            var border = selBorder.value;
                            var alig = selAlig.value;
                            var pad = padMap[selPad.value] || '8px 12px';
                            var tAlign = { 'left': 'left', 'center': 'center', 'right': 'right' }[alig] || 'left';
                            var sel = quill.getSelection(true);
                            if (!sel) { modal.remove(); return; }
                            /* Estructura simple: Quill 2.0.3 descarta los style de td al convertir el HTML pegado,
                               asi que la tabla se inserta limpia y luego se aplican los estilos al DOM directamente. */
                            var html = '<table><tbody>';
                            if (hasHeader) {
                                html += '<tr>';
                                for (var c = 0; c < cols; c++) html += '<td><p><br></p></td>';
                                html += '</tr>';
                            }
                            for (var r = (hasHeader ? 1 : 0); r < rows; r++) {
                                html += '<tr>';
                                for (var c2 = 0; c2 < cols; c2++) html += '<td><p><br></p></td>';
                                html += '</tr>';
                            }
                            html += '</tbody></table><p><br></p>';
                            var antes = document.querySelectorAll('.ql-editor table').length;
                            quill.clipboard.dangerouslyPasteHTML(sel.index, html);
                            quill.setSelection(sel.index, 0, 'silent');
                            var tablas = document.querySelectorAll('.ql-editor table');
                            var tabla = tablas[antes] || tablas[0];
                            if (tabla) {
                                var estilo = selStyle.value;
                                var st = ESTILOS_TABLA[estilo] || ESTILOS_TABLA['clasica'];
                                var lineColor = st.lineColor || '#94a3b8';
                                var zfila = estilo === 'zebra' && hasHeader;
                                var oscura = estilo === 'oscura';
                                var filas = tabla.rows;
                                for (var i = 0; i < filas.length; i++) {
                                    var celdas = filas[i].cells;
                                    var isHeader = hasHeader && i === 0;
                                    for (var j = 0; j < celdas.length; j++) {
                                        var cel = celdas[j];
                                        var bg = isHeader ? (st.headerBg || '#dbeafe') : (st.bodyBg || '#ffffff');
                                        var tcol = isHeader ? (st.headerColor || '#1e3a8a') : '#0f172a';
                                        if (oscura) { bg = isHeader ? '#1e293b' : '#0f172a'; tcol = isHeader ? '#5eead4' : '#e2e8f0'; }
                                        else if (zfila && i % 2 === 1) bg = '#f1f5f9';
                                        else if (estilo === 'lineas-grises' && i % 2 === 1) bg = '#f8fafc';
                                        cel.style.borderCollapse = 'collapse';
                                        if (border === 'all') cel.style.border = '1px solid ' + lineColor;
                                        else if (border === 'line') { cel.style.border = 'none'; if (i === 0) cel.style.borderTop = '1px solid ' + lineColor; if (i < filas.length - 1) cel.style.borderBottom = '1px solid ' + lineColor; }
                                        else if (border === 'double') cel.style.border = '3px double ' + lineColor;
                                        else { cel.style.border = 'none'; if (estilo === 'lineas-grises' && i < filas.length - 1) cel.style.borderBottom = '1px solid ' + lineColor; }
                                        cel.style.padding = pad;
                                        cel.style.textAlign = tAlign;
                                        cel.style.backgroundColor = bg;
                                        cel.style.color = tcol;
                                        cel.style.fontWeight = isHeader ? '700' : '400';
                                        cel.style.verticalAlign = 'middle';
                                    }
                                }
                                if (full) tabla.style.width = '100%';
                                else tabla.style.width = 'auto';
                                tabla.style.borderCollapse = 'collapse';
                            }
                            modal.remove();
                        };
                    },
                    'img-btn': function() {
                        var existing = document.querySelector('.img-modal-overlay');
                        if (existing) { existing.remove(); return; }
                        var modal = document.createElement('div');
                        modal.className = 'img-modal-overlay';
                        modal.style.cssText = 'position:fixed;inset:0;z-index:10002;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.6);';
                        modal.innerHTML =
                            '<div style="background:#0f172a;border:1px solid #334155;border-radius:14px;width:min(92vw,520px);box-shadow:0 12px 44px rgba(0,0,0,.7);overflow:hidden;">' +
                            '<div style="display:flex;align-items:center;gap:8px;padding:14px 18px;background:#1e293b;border-bottom:1px solid #334155;">' +
                            '<i class="fas fa-image" style="color:#5eead4;font-size:14px;"></i>' +
                            '<span style="color:#e2e8f0;font-size:13px;font-weight:700;flex:1;">Insertar imagen</span>' +
                            '<button id="imgClose" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:16px;"><i class="fas fa-xmark"></i></button>' +
                            '</div>' +
                            '<div style="padding:20px;">' +
                            /* Tabs URL / PC */
                            '<div style="display:flex;gap:0;margin-bottom:16px;border:1px solid #334155;border-radius:8px;overflow:hidden;">' +
                            '<button id="tabUrl" type="button" style="flex:1;padding:8px;border:none;background:#0f766e;color:#fff;font-size:12px;font-weight:700;cursor:pointer;"><i class="fas fa-link" style="margin-right:5px;"></i>URL</button>' +
                            '<button id="tabPc" type="button" style="flex:1;padding:8px;border:none;background:#1e293b;color:#94a3b8;font-size:12px;font-weight:700;cursor:pointer;border-left:1px solid #334155;"><i class="fas fa-desktop" style="margin-right:5px;"></i>Desde PC</button>' +
                            '</div>' +
                            /* Panel URL */
                            '<div id="panelUrl">' +
                            '<label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">URL de la imagen</label>' +
                            '<input id="imgUrl" type="url" placeholder="https://ejemplo.com/imagen.jpg" style="width:100%;padding:8px 12px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:13px;box-sizing:border-box;outline:none;">' +
                            '</div>' +
                            /* Panel PC */
                            '<div id="panelPc" style="display:none;">' +
                            '<div id="imgDropZone" style="border:2px dashed #334155;border-radius:10px;padding:28px 16px;text-align:center;cursor:pointer;transition:.2s;">' +
                            '<i class="fas fa-cloud-arrow-up" style="font-size:28px;color:#475569;margin-bottom:8px;display:block;"></i>' +
                            '<div style="color:#94a3b8;font-size:12px;font-weight:600;">Arrastra una imagen aqui</div>' +
                            '<div style="color:#475569;font-size:11px;margin-top:4px;">o haz clic para buscar</div>' +
                            '<input type="file" id="imgFileInput" accept="image/*" style="display:none;">' +
                            '</div>' +
                            '</div>' +
                            /* Preview comun */
                            '<div id="imgPreviewWrap" style="margin-top:12px;display:none;text-align:center;background:#1e293b;border:1px dashed #334155;border-radius:8px;padding:12px;">' +
                            '<img id="imgPreview" style="max-width:100%;max-height:180px;border-radius:6px;">' +
                            '<div id="imgFileName" style="color:#64748b;font-size:11px;margin-top:6px;"></div>' +
                            '</div>' +
                            /* Dimensiones */
                            '<div style="display:flex;gap:12px;margin-top:14px;">' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:11px;font-weight:600;margin-bottom:4px;">Ancho (px)</label>' +
                            '<input id="imgW" type="number" min="10" placeholder="auto" style="width:100%;padding:6px 10px;background:#1e293b;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:12px;box-sizing:border-box;outline:none;"></div>' +
                            '<div style="flex:1"><label style="display:block;color:#94a3b8;font-size:11px;font-weight:600;margin-bottom:4px;">Alto (px)</label>' +
                            '<input id="imgH" type="number" min="10" placeholder="auto" style="width:100%;padding:6px 10px;background:#1e293b;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:12px;box-sizing:border-box;outline:none;"></div>' +
                            '</div>' +
                            '<label style="display:block;margin-top:10px;color:#94a3b8;font-size:12px;cursor:pointer;">' +
                            '<input type="checkbox" id="imgFull" checked style="accent-color:#0f766e;margin-right:6px;"> Ancho completo (100%)</label>' +
                            '<div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">' +
                            '<button id="imgInsert" disabled style="padding:7px 18px;border-radius:8px;border:none;background:#0f766e;color:#fff;font-size:12px;font-weight:700;cursor:pointer;opacity:.5;"><i class="fas fa-check"></i> Insertar</button>' +
                            '</div></div></div>';
                        document.body.appendChild(modal);

                        /* --- Elementos --- */
                        var tabUrl = modal.querySelector('#tabUrl');
                        var tabPc = modal.querySelector('#tabPc');
                        var panelUrl = modal.querySelector('#panelUrl');
                        var panelPc = modal.querySelector('#panelPc');
                        var iptUrl = modal.querySelector('#imgUrl');
                        var dropZone = modal.querySelector('#imgDropZone');
                        var fileInput = modal.querySelector('#imgFileInput');
                        var iptW = modal.querySelector('#imgW');
                        var iptH = modal.querySelector('#imgH');
                        var chkFull = modal.querySelector('#imgFull');
                        var previewWrap = modal.querySelector('#imgPreviewWrap');
                        var previewImg = modal.querySelector('#imgPreview');
                        var fileNameEl = modal.querySelector('#imgFileName');
                        var btnIns = modal.querySelector('#imgInsert');
                        var currentDataUrl = '';

                        modal.querySelector('#imgClose').onclick = function() { modal.remove(); };
                        modal.addEventListener('click', function(ev) { if (ev.target === modal) modal.remove(); });

                        /* --- Tabs --- */
                        function switchTab(tab) {
                            if (tab === 'url') {
                                tabUrl.style.background = '#0f766e'; tabUrl.style.color = '#fff';
                                tabPc.style.background = '#1e293b'; tabPc.style.color = '#94a3b8';
                                panelUrl.style.display = ''; panelPc.style.display = 'none';
                            } else {
                                tabPc.style.background = '#0f766e'; tabPc.style.color = '#fff';
                                tabUrl.style.background = '#1e293b'; tabUrl.style.color = '#94a3b8';
                                panelPc.style.display = ''; panelUrl.style.display = 'none';
                            }
                            currentDataUrl = ''; previewWrap.style.display = 'none';
                            iptUrl.value = ''; fileNameEl.textContent = '';
                            btnIns.disabled = true; btnIns.style.opacity = '.5';
                        }
                        tabUrl.onclick = function() { switchTab('url'); iptUrl.focus(); };
                        tabPc.onclick = function() { switchTab('pc'); };

                        /* --- URL preview --- */
                        iptUrl.addEventListener('input', function() {
                            var url = iptUrl.value.trim();
                            currentDataUrl = '';
                            if (url) {
                                previewWrap.style.display = '';
                                previewImg.src = url;
                                fileNameEl.textContent = '';
                                previewImg.onerror = function() { previewWrap.style.display = 'none'; btnIns.disabled = true; btnIns.style.opacity = '.5'; };
                                previewImg.onload = function() { btnIns.disabled = false; btnIns.style.opacity = '1'; };
                            } else {
                                previewWrap.style.display = 'none';
                                btnIns.disabled = true; btnIns.style.opacity = '.5';
                            }
                        });

                        /* --- PC: clic en dropzone --- */
                        dropZone.onclick = function() { fileInput.click(); };
                        dropZone.addEventListener('dragover', function(ev) { ev.preventDefault(); dropZone.style.borderColor = '#0f766e'; dropZone.style.background = 'rgba(15,118,110,.08)'; });
                        dropZone.addEventListener('dragleave', function() { dropZone.style.borderColor = '#334155'; dropZone.style.background = 'transparent'; });
                        dropZone.addEventListener('drop', function(ev) {
                            ev.preventDefault(); dropZone.style.borderColor = '#334155'; dropZone.style.background = 'transparent';
                            var f = ev.dataTransfer.files[0];
                            if (f && f.type.startsWith('image/')) leerArchivo(f);
                        });
                        fileInput.addEventListener('change', function() {
                            if (fileInput.files[0]) leerArchivo(fileInput.files[0]);
                        });
                        function leerArchivo(file) {
                            if (file.size > 10 * 1024 * 1024) { alert('La imagen supera 10 MB.'); return; }
                            var reader = new FileReader();
                            reader.onload = function(ev) {
                                currentDataUrl = ev.target.result;
                                previewWrap.style.display = '';
                                previewImg.src = currentDataUrl;
                                fileNameEl.textContent = file.name + ' (' + (file.size >= 1048576 ? (file.size/1048576).toFixed(1)+' MB' : Math.round(file.size/1024)+' KB') + ')';
                                btnIns.disabled = false; btnIns.style.opacity = '1';
                            };
                            reader.readAsDataURL(file);
                        }

                        /* --- Dimensiones --- */
                        chkFull.addEventListener('change', function() {
                            iptW.disabled = chkFull.checked; iptH.disabled = chkFull.checked;
                            iptW.style.opacity = chkFull.checked ? '.4' : '1';
                            iptH.style.opacity = chkFull.checked ? '.4' : '1';
                        });
                        iptW.disabled = true; iptW.style.opacity = '.4';
                        iptH.disabled = true; iptH.style.opacity = '.4';

                        /* --- Insertar --- */
                        btnIns.onclick = function() {
                            var src = currentDataUrl || iptUrl.value.trim();
                            if (!src) return;
                            var w = iptW.value ? iptW.value + 'px' : (chkFull.checked ? '100%' : 'auto');
                            var h = iptH.value ? iptH.value + 'px' : 'auto';
                            var styleAttr = 'max-width:' + w + ';height:' + h + ';border-radius:8px;display:block;margin:8px 0;';
                            var range = quill.getSelection(true);
                            quill.insertEmbed(range.index, 'image', src);
                            /* Aplicar estilo a la imagen insertada */
                            setTimeout(function() {
                                var imgs = quill.root.querySelectorAll('img');
                                var last = imgs[imgs.length - 1];
                                if (last) last.setAttribute('style', styleAttr);
                                _rszActivar(last);
                            }, 10);
                            modal.remove();
                        };
                    },
                    'html-btn': function() {
                        const contenido = quill.root.innerHTML;
                        const modal = document.createElement('div');
                        modal.style.cssText = 'position:fixed;inset:0;z-index:10002;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.6);';
                        modal.innerHTML =
                            '<div style="background:#0f172a;border:1px solid #334155;border-radius:14px;width:min(92vw,640px);max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 12px 44px rgba(0,0,0,.7);">' +
                            '<div style="display:flex;align-items:center;gap:8px;padding:14px 18px;background:#1e293b;border-bottom:1px solid #334155;">' +
                            '<i class="fas fa-code" style="color:#5eead4;font-size:14px;"></i>' +
                            '<span style="color:#e2e8f0;font-size:13px;font-weight:700;flex:1;">HTML del mensaje</span>' +
                            '<button id="htmlCopyBtn" style="background:none;border:1px solid #334155;color:#94a3b8;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px;"><i class="fas fa-copy"></i> Copiar</button>' +
                            '<button id="htmlCloseBtn" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:16px;"><i class="fas fa-xmark"></i></button>' +
                            '</div>' +
                            '<pre style="margin:0;padding:16px;overflow:auto;flex:1;color:#5eead4;font-size:12.5px;font-family:\'Cascadia Code\',\'Fira Code\',Consolas,monospace;line-height:1.6;white-space:pre-wrap;word-break:break-all;">' +
                            escHtml(contenido) +
                            '</pre></div>';
                        document.body.appendChild(modal);
                        modal.querySelector('#htmlCloseBtn').onclick = function() { modal.remove(); };
                        modal.querySelector('#htmlCopyBtn').onclick = function() {
                            navigator.clipboard.writeText(contenido).then(function() {
                                const btn = modal.querySelector('#htmlCopyBtn');
                                btn.innerHTML = '<i class="fas fa-check"></i> Copiado';
                                setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i> Copiar'; }, 1500);
                            });
                        };
                        modal.addEventListener('click', function(ev) { if (ev.target === modal) modal.remove(); });
                    },
                    'link': function() {
                        const range = quill.getSelection();
                        if (!range) return;
                        const existing = range.length > 0 ? quill.getContents(range.index, range.length) : null;
                        const existingLink = existing ? existing.ops.find(function(op) { return op.attributes && op.attributes.link; }) : null;
                        const rawUrl = existingLink ? existingLink.attributes.link : '';
                        /* Detectar protocolo actual */
                        var proto = 'https://';
                        var urlBody = rawUrl;
                        var protos = ['https://','http://','mailto:','ftp://','tel:','irc://','file://'];
                        protos.forEach(function(p){ if(rawUrl.toLowerCase().indexOf(p)===0){ proto=p; urlBody=rawUrl.substring(p.length); }});
                        if (rawUrl.indexOf('mailto:')===0){ proto='mailto:'; urlBody=rawUrl.substring(7); }
                        else if (rawUrl.indexOf('tel:')===0){ proto='tel:'; urlBody=rawUrl.substring(4); }
                        var newTab = rawUrl.indexOf('target=')>-1 ? true : false;

                        var optsHtml = '';
                        var protoLabels = {'https://':'https://','http://':'http://','mailto:':'mailto:','ftp://':'ftp://','tel:':'tel:','irc://':'irc://','file://':'file://'};
                        protos.forEach(function(p){ optsHtml += '<option value="'+p+'"'+(proto===p?' selected':'')+'>'+protoLabels[p]+'</option>'; });

                        const modal = document.createElement('div');
                        modal.style.cssText = 'position:fixed;inset:0;z-index:10002;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.6);';
                        modal.innerHTML =
                            '<div style="background:#0f172a;border:1px solid #334155;border-radius:14px;width:min(92vw,480px);box-shadow:0 12px 44px rgba(0,0,0,.7);overflow:hidden;">' +
                            '<div style="display:flex;align-items:center;gap:8px;padding:14px 18px;background:#1e293b;border-bottom:1px solid #334155;">' +
                            '<i class="fas fa-link" style="color:#5eead4;font-size:14px;"></i>' +
                            '<span style="color:#e2e8f0;font-size:13px;font-weight:700;flex:1;">' + (rawUrl ? 'Editar enlace' : 'Insertar enlace') + '</span>' +
                            '<button id="qlLinkClose" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:16px;"><i class="fas fa-xmark"></i></button>' +
                            '</div>' +
                            '<div style="padding:18px;">' +
                            '<label style="display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-bottom:6px;">URL del enlace</label>' +
                            '<div style="display:flex;align-items:center;gap:0;border:1px solid #334155;border-radius:8px;overflow:hidden;background:#1e293b;">' +
                            '<select id="qlLinkProto" style="background:#1a2332;border:none;border-right:1px solid #334155;color:#5eead4;font-size:12px;font-weight:700;padding:8px 6px;cursor:pointer;outline:none;min-width:82px;">' +
                            optsHtml +
                            '</select>' +
                            '<input id="qlLinkUrl" type="text" value="' + escHtml(urlBody) + '" placeholder="www.ejemplo.com" ' +
                            'style="flex:1;padding:8px 10px;background:transparent;border:none;color:#e2e8f0;font-size:13px;outline:none;min-width:0;">' +
                            '</div>' +
                            '<div style="display:flex;align-items:center;gap:12px;margin-top:10px;">' +
                            '<label style="display:flex;align-items:center;gap:6px;color:#94a3b8;font-size:12px;cursor:pointer;">' +
                            '<input type="checkbox" id="qlLinkNewTab" '+(newTab?'checked':'')+' style="accent-color:#0f766e;width:14px;height:14px;">' +
                            'Abrir en nueva pesta&ntilde;a</label>' +
                            '<span id="qlLinkPreview" style="color:#475569;font-size:11px;word-break:break-all;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>' +
                            '</div>' +
                            '<div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">' +
                            (rawUrl ? '<button id="qlLinkRemove" style="padding:7px 14px;border-radius:8px;border:1px solid #334155;background:transparent;color:#f87171;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-trash-can"></i> Quitar</button>' : '') +
                            '<button id="qlLinkSave" style="padding:7px 18px;border-radius:8px;border:none;background:#0f766e;color:#fff;font-size:12px;font-weight:700;cursor:pointer;"><i class="fas fa-check"></i> Guardar</button>' +
                            '</div></div></div>';
                        document.body.appendChild(modal);

                        const inputProto = modal.querySelector('#qlLinkProto');
                        const inputUrl = modal.querySelector('#qlLinkUrl');
                        const preview = modal.querySelector('#qlLinkPreview');
                        function actualizarPreview(){
                            var full = inputProto.value + inputUrl.value.trim();
                            preview.textContent = full || '';
                            preview.title = full;
                        }
                        inputProto.addEventListener('change', actualizarPreview);
                        inputUrl.addEventListener('input', actualizarPreview);
                        actualizarPreview();
                        inputUrl.focus();

                        modal.querySelector('#qlLinkSave').onclick = function() {
                            let url = inputUrl.value.trim();
                            if (!url) { quill.removeFormat(range.index, range.length); modal.remove(); return; }
                            /* Si el usuario ya escribio un protocolo completo, respetarlo */
                            var hasProto = false;
                            protos.forEach(function(p){ if(url.toLowerCase().indexOf(p)===0) hasProto=true; });
                            if (!hasProto) url = inputProto.value + url;
                            quill.format('link', url);
                            modal.remove();
                            logEvent('ok', 'Enlace insertado/actualizado');
                        };
                        if (modal.querySelector('#qlLinkRemove')) {
                            modal.querySelector('#qlLinkRemove').onclick = function() {
                                quill.removeFormat(range.index, range.length);
                                modal.remove();
                                logEvent('ok', 'Enlace eliminado');
                            };
                        }
                        modal.querySelector('#qlLinkClose').onclick = function() { modal.remove(); };
                        modal.addEventListener('click', function(ev) { if (ev.target === modal) modal.remove(); });
                        modal.addEventListener('keydown', function(ev){ if(ev.key==='Escape') modal.remove(); if(ev.key==='Enter'){ ev.preventDefault(); modal.querySelector('#qlLinkSave').click(); }});
                    }
                }
            }
        }
    });

    /* ===== Poner iconos en botones custom (deferred para asegurar que Quill ya renderizo) ===== */
    var customIcons = {'.ql-emoji-btn':'<i class="fas fa-face-smile"></i>','.ql-attach-btn':'<i class="fas fa-paperclip"></i>','.ql-html-btn':'<i class="fas fa-code"></i>','.ql-print-btn':'<i class="fas fa-print"></i>','.ql-pdf-btn':'<i class="fas fa-file-pdf"></i>','.ql-size-custom':'<i class="fas fa-text-height"></i>','.ql-sym-btn':'<i class="fas fa-asterisk"></i>','.ql-table-btn':'<i class="fas fa-table"></i>','.ql-img-btn':'<i class="fas fa-image"></i>','.ql-case-btn':'<i class="fas fa-font-case"></i>','.ql-size-mb':'<i class="fas fa-minus"></i>','.ql-size-pb':'<i class="fas fa-plus"></i>','.ql-bold':'<i class="fas fa-bold" style="font-size:15px;color:#e2e8f0;font-weight:900;"></i>','.ql-italic':'<i class="fas fa-italic" style="font-size:15px;color:#e2e8f0;"></i>','.ql-underline':'<i class="fas fa-underline" style="font-size:15px;color:#e2e8f0;"></i>','.ql-strike':'<i class="fas fa-strikethrough" style="font-size:15px;color:#e2e8f0;"></i>'};
    function applyCustomIcons() {
        Object.keys(customIcons).forEach(function(s){
            var e=document.querySelector(s);
            if(!e) return;
            if (s === '.ql-case-btn' && e.querySelector('.case-aa-label')) return;
            e.innerHTML=customIcons[s];
        });
    }
    applyCustomIcons();
    requestAnimationFrame(function(){ applyCustomIcons(); });
    setTimeout(applyCustomIcons, 200);

    /* ===== Traducir labels del font picker (Quill 2.0.3) ===== */
    var FONT_LABELS = { 'arial':'Arial', 'times-new-roman':'Times New Roman', 'verdana':'Verdana', 'comic-sans-ms':'Comic Sans MS', 'georgia':'Georgia', 'courier-new':'Courier New', 'inter':'Inter', 'segoe-ui':'Segoe UI', 'calibri':'Calibri' };
    var ES_LETRA = /[A-Za-z\u00C0-\u00FF\u0100-\u024F]/;
    function tituloPropio(s) {
        var low = s.toLowerCase();
        var out = '';
        var inicioPalabra = true;
        for (var i = 0; i < low.length; i++) {
            var c = low.charAt(i);
            if (ES_LETRA.test(c) && inicioPalabra) { out += c.toUpperCase(); inicioPalabra = false; }
            else { out += c; if (!ES_LETRA.test(c)) inicioPalabra = true; }
        }
        return out;
    }
    function capitalizarOracion(s) {
        var out = '';
        var inicioOracion = true;
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i);
            if (ES_LETRA.test(c) && inicioOracion) { out += c.toUpperCase(); inicioOracion = false; }
            else { out += c; if (/[.!?\u2026]/.test(c)) inicioOracion = true; }
        }
        return out;
    }
    function alternarMayusculas(s) {
        var out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i);
            out += (c === c.toUpperCase()) ? c.toLowerCase() : c.toUpperCase();
        }
        return out;
    }
    function traducirFontPicker() {
        /* 1) Convertir el picker de fuentes de Quill en un dropdown (popup) nativo a medida */
        var fontPicker = document.querySelector('.ql-picker.ql-font');
        if (fontPicker && !document.querySelector('.ql-font-btn')) {
            var fontsList = [{ v: '', l: 'Por defecto' }];
            Object.keys(FONT_LABELS).sort(function(a, b) { return FONT_LABELS[a].localeCompare(FONT_LABELS[b]); }).forEach(function(k) { fontsList.push({ v: k, l: FONT_LABELS[k] }); });
            var fSelStyle = 'position:relative;display:inline-flex;align-items:center;width:96px;height:24px;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:0 6px;cursor:pointer;color:#e2e8f0;font-size:12px;font-family:inherit;';
            var fSel = document.createElement('button');
            fSel.type = 'button';
            fSel.className = 'ql-font-btn';
            fSel.title = 'Tipo de letra';
            fSel.setAttribute('aria-label', 'Tipo de letra');
            fSel.innerHTML = '<span style="' + fSelStyle + '"><span class="ql-font-label" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;">Por defecto</span><span style="margin-left:4px;color:#64748b;font-size:9px;">&#9662;</span></span>';
            fSel.style.position = 'relative';
            fSel.style.display = 'inline-flex';
            fSel.style.alignItems = 'center';
            fontPicker.parentNode.replaceChild(fSel, fontPicker);
            /* Popup con las fuentes (anclado a body, position:fixed) */
            var fPop = document.createElement('div');
            fPop.className = 'font-dropdown-popup';
            fPop.style.cssText = 'position:fixed;z-index:10001;min-width:160px;background:#0f172a;border:1px solid #334155;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.5);padding:4px 0;display:none;max-height:260px;overflow-y:auto;';
            fontsList.forEach(function(o) {
                var b = document.createElement('button');
                b.type = 'button';
                b.dataset.font = o.v;
                b.textContent = o.l;
                b.style.cssText = 'display:block;width:100%;padding:7px 12px;background:none;border:none;color:#e2e8f0;font-size:12px;cursor:pointer;text-align:left;font-family:' + (o.v || 'inherit') + ';';
                b.onmouseover = function() { b.style.background = 'rgba(94,234,212,.1)'; };
                b.onmouseout = function() { b.style.background = 'none'; };
                b.addEventListener('click', function() {
                    var v = b.dataset.font;
                    var s = fSel._savedRange || quill.getSelection(true) || { index: 0, length: 0 };
                    if (v) {
                        quill.formatText(s.index, s.length, 'font', v);
                        logEvent('ok', 'Fuente: ' + v);
                    } else {
                        quill.removeFormat(s.index, s.length, 'font');
                        logEvent('ok', 'Fuente: por defecto');
                    }
                    fSel.querySelector('.ql-font-label').textContent = v ? FONT_LABELS[v] : 'Por defecto';
                    fPop.style.display = 'none';
                    quill.setSelection(s.index, Math.max(s.length, 1));
                    quill.focus();
                });
                fPop.appendChild(b);
            });
            document.body.appendChild(fPop);
            fSel.addEventListener('click', function(ev) {
                ev.stopPropagation();
                var wasOpen = fPop.style.display !== 'none';
                document.querySelectorAll('.font-dropdown-popup, .case-dropdown-popup').forEach(function(p) { p.style.display = 'none'; });
                if (wasOpen) return;
                var r = fSel.getBoundingClientRect();
                fPop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 180)) + 'px';
                fPop.style.top = (r.bottom + 6) + 'px';
                fSel._savedRange = quill.getSelection(true);
                fPop.style.display = 'block';
            });
            fSel.addEventListener('focus', function() {
                fSel._savedRange = quill.getSelection(true);
            });
            quill.on('selection-change', function(range) {
                if (range) {
                    var ff = quill.getFormat(range);
                    fSel.querySelector('.ql-font-label').textContent = ff.font ? (FONT_LABELS[ff.font] || ff.font) : 'Por defecto';
                }
            });
        }
        /* 2.5) Convertir el boton de mayusculas en un dropdown (popup) tipo Word */
        var caseBtn = document.querySelector('.ql-case-btn');
        if (caseBtn && !document.querySelector('.case-dropdown-popup')) {
            var cSelStyle = 'display:inline-flex;align-items:center;width:74px;height:24px;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:0 6px;cursor:pointer;color:#e2e8f0;font-size:12px;font-family:inherit;';
            var newCaseBtn = document.createElement('button');
            newCaseBtn.type = 'button';
            newCaseBtn.className = 'ql-case-btn';
            newCaseBtn.title = 'Cambiar mayusculas / minusculas';
            newCaseBtn.setAttribute('aria-label', 'Cambiar mayusculas / minusculas');
            newCaseBtn.innerHTML = '<span style="' + cSelStyle + '"><span class="case-aa-label" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;">Aa</span><span style="margin-left:4px;color:#64748b;font-size:9px;">&#9662;</span></span>';
            newCaseBtn.style.position = 'relative';
            newCaseBtn.style.display = 'inline-flex';
            caseBtn.parentNode.replaceChild(newCaseBtn, caseBtn);
            var optsC = [
                { v: '',   l: '\u0041\u0061 \u00B7 Cambiar' },
                { v: 'lower', l: 'min\u00FAsculas' },
                { v: 'upper', l: 'MAY\u00DASCULAS' },
                { v: 'title', l: 'M\u00E1yusculas por Palabra' },
                { v: 'sentence', l: 'Capitalizar oraci\u00F3n' },
                { v: 'toggle', l: 'ALTERNAR M\u00E1y\u00FAsc. Y MIN\u00DAsc.' }
            ];
            var cPop = document.createElement('div');
            cPop.className = 'case-dropdown-popup';
            cPop.style.cssText = 'position:fixed;z-index:10001;min-width:220px;background:#0f172a;border:1px solid #334155;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.5);padding:4px 0;display:none;';
            optsC.forEach(function(o) {
                var b = document.createElement('button');
                b.type = 'button';
                b.dataset.case = o.v;
                b.textContent = o.l;
                if (!o.v) b.style.cssText = 'display:block;width:100%;padding:7px 12px;background:none;border:none;color:#94a3b8;font-size:12px;cursor:default;text-align:left;font-weight:600;';
                else b.style.cssText = 'display:block;width:100%;padding:7px 12px;background:none;border:none;color:#e2e8f0;font-size:12px;cursor:pointer;text-align:left;';
                if (o.v) {
                    b.onmouseover = function() { b.style.background = 'rgba(94,234,212,.1)'; };
                    b.onmouseout = function() { b.style.background = 'none'; };
                    b.addEventListener('click', function() {
                        cPop.style.display = 'none';
                        var v = b.dataset.case;
                        var rng = quill.getSelection(true);
                        if (!v) { return; }
                        var s = newCaseBtn._savedRange || rng;
                        if (!s) { return; }
                        var idx = s.index, len = s.length;
                        if (!len) {
                            /* Sin seleccion: transformar la palabra del cursor */
                            var text = quill.getText();
                            var esPalabra = function(ch) { return /[A-Za-z0-9\u00C0-\u024F\u1E00-\u1EFF]/.test(ch); };
                            var start = idx;
                            while (start > 0 && esPalabra(text.charAt(start - 1))) start--;
                            var fin = idx;
                            while (fin < text.length && esPalabra(text.charAt(fin))) fin++;
                            if (fin === start) { logEvent('info', 'Sin texto seleccionado'); return; }
                            idx = start; len = fin - start;
                        }
                        var selText = quill.getText(idx, len);
                        var nuevo;
                        if (v === 'lower') nuevo = selText.toLowerCase();
                        else if (v === 'upper') nuevo = selText.toUpperCase();
                        else if (v === 'title') nuevo = tituloPropio(selText);
                        else if (v === 'sentence') nuevo = capitalizarOracion(selText);
                        else if (v === 'toggle') nuevo = alternarMayusculas(selText);
                        if (nuevo != null) {
                            var transformarOp = function(txt) {
                                if (v === 'lower') return txt.toLowerCase();
                                if (v === 'upper') return txt.toUpperCase();
                                if (v === 'title') return tituloPropio(txt);
                                if (v === 'sentence') return capitalizarOracion(txt);
                                if (v === 'toggle') return alternarMayusculas(txt);
                                return txt;
                            };
                            var contenido = quill.getContents(idx, len).ops;
                            var opsNuevos = [];
                            var pos = 0;
                            contenido.forEach(function(op) {
                                if (typeof op.insert === 'string') {
                                    var t = op.insert;
                                    var porcion;
                                    if (nuevo.length === selText.length) {
                                        porcion = nuevo.substring(pos, pos + t.length);
                                        pos += t.length;
                                    } else {
                                        porcion = transformarOp(t);
                                    }
                                    opsNuevos.push({ insert: porcion, attributes: op.attributes });
                                } else {
                                    opsNuevos.push(op);
                                }
                            });
                            if (nuevo.length === selText.length) {
                                var DeltaCls = Quill.import('delta');
                                var dOps = new DeltaCls();
                                dOps.retain(idx).delete(len);
                                opsNuevos.forEach(function(op) { dOps.insert(op.insert, op.attributes); });
                                quill.updateContents(dOps, Quill.sources.USER);
                            } else {
                                quill.deleteText(idx, len, Quill.sources.USER);
                                quill.insertText(idx, nuevo, 'user');
                            }
                        }
                        var longFinal = nuevo ? nuevo.length : 0;
                        quill.setSelection(idx + longFinal, Quill.sources.SILENT);
                        logEvent('ok', 'May\u00FAsculas aplicadas');
                    });
                }
                cPop.appendChild(b);
            });
            document.body.appendChild(cPop);
            newCaseBtn.addEventListener('click', function(ev) {
                ev.stopPropagation();
                var wasOpen = cPop.style.display !== 'none';
                document.querySelectorAll('.font-dropdown-popup, .case-dropdown-popup').forEach(function(p) { p.style.display = 'none'; });
                if (wasOpen) return;
                var r = newCaseBtn.getBoundingClientRect();
                cPop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 240)) + 'px';
                cPop.style.top = (r.bottom + 6) + 'px';
                newCaseBtn._savedRange = quill.getSelection(true);
                cPop.style.display = 'block';
            });
            newCaseBtn.addEventListener('focus', function() {
                newCaseBtn._savedRange = quill.getSelection(true);
            });
        }
        /* 3) Convertir el boton de borde en un dropdown (select) */
        var borderBtn = document.querySelector('.ql-border-btn');
        if (borderBtn && !document.querySelector('.ql-border-select')) {
            var selB = document.createElement('select');
            selB.className = 'ql-border-select';
            selB.title = 'Borde de linea';
            selB.setAttribute('aria-label', 'Borde de linea');
            var optsB = [
                { v: '', l: 'Sin borde', icon: '' },
                { v: 'solid', l: 'Linea solida', icon: '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500' },
                { v: 'double', l: 'Linea doble', icon: '\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550' },
                { v: 'dotted', l: 'Linea punteada', icon: '\u2504\u2504\u2504\u2504\u2504\u2504\u2504\u2504' }
            ];
            optsB.forEach(function(o) {
                var op = document.createElement('option');
                op.value = o.v;
                op.textContent = o.icon ? o.icon + '\u00A0 ' + o.l : o.l;
                if (o.v === 'solid') op.style.color = '#94a3b8';
                else if (o.v === 'double') op.style.color = '#94a3b8';
                else if (o.v === 'dotted') op.style.color = '#94a3b8';
                if (o.icon) op.style.fontWeight = '700';
                selB.appendChild(op);
            });
            borderBtn.parentNode.replaceChild(selB, borderBtn);
            var BORDER_MAP = { 'solid': '2px solid #94a3b8', 'double': '4px double #94a3b8', 'dotted': '2px dotted #94a3b8' };
            selB.addEventListener('change', function() {
                var rng = _qlRangoGuardado || quill.getSelection(true);
                if (!rng) return;
                var v = selB.value;
                if (v) {
                    quill.formatText(rng.index, rng.length || 1, 'box', BORDER_MAP[v]);
                    logEvent('ok', 'Borde de linea: ' + v);
                } else {
                    quill.removeFormat(rng.index, rng.length || 1, 'box');
                    logEvent('ok', 'Borde de linea: ninguno');
                }
            });
            quill.on('selection-change', function(range) {
                if (range) {
                    var fb = quill.getFormat(range);
                    selB.value = fb.box || '';
                }
            });
        }
        /* 4) Iconos undo/redo */
        var undoBtn = document.querySelector('.ql-undo-btn');
        if (undoBtn && !undoBtn.querySelector('i')) {
            undoBtn.innerHTML = '<i class="fas fa-rotate-left" style="font-size:12px;color:#94a3b8;"></i>';
        }
        var redoBtn = document.querySelector('.ql-redo-btn');
        if (redoBtn && !redoBtn.querySelector('i')) {
            redoBtn.innerHTML = '<i class="fas fa-rotate-right" style="font-size:12px;color:#94a3b8;"></i>';
        }
        /* 5) Convertir el boton de encabezados en un dropdown (select) */
        var hdrBtn = document.querySelector('.ql-header-btn');
        if (hdrBtn && !document.querySelector('.ql-header-select')) {
            var selH = document.createElement('select');
            selH.className = 'ql-header-select';
            selH.title = 'Encabezados (H1-H6)';
            selH.setAttribute('aria-label', 'Encabezados (H1-H6)');
            var optsH = [
                { v: '0', l: 'Normal', size: '13px', weight: '400' },
                { v: '1', l: 'H1', size: '22px', weight: '700' },
                { v: '2', l: 'H2', size: '19px', weight: '700' },
                { v: '3', l: 'H3', size: '16px', weight: '700' },
                { v: '4', l: 'H4', size: '15px', weight: '700' },
                { v: '5', l: 'H5', size: '14px', weight: '700' },
                { v: '6', l: 'H6', size: '13px', weight: '700' }
            ];
            optsH.forEach(function(o) {
                var op = document.createElement('option');
                op.value = o.v;
                op.textContent = o.l;
                op.style.fontSize = o.size;
                op.style.fontWeight = o.weight;
                if (o.v !== '0') op.style.color = '#5eead4';
                selH.appendChild(op);
            });
            hdrBtn.parentNode.replaceChild(selH, hdrBtn);
            selH.addEventListener('change', function() {
                var rng = _qlRangoGuardado || quill.getSelection(true);
                if (!rng) return;
                var v = selH.value;
                if (v === '0') {
                    quill.formatLine(rng.index, rng.length, 'header', false);
                    logEvent('ok', 'Encabezado: Normal');
                } else {
                    quill.formatLine(rng.index, rng.length, 'header', parseInt(v, 10));
                    quill.formatText(rng.index, rng.length, { size: null });
                    logEvent('ok', 'Encabezado: H' + v);
                }
            });
            quill.on('selection-change', function(range) {
                if (range) {
                    var fh = quill.getFormat(range);
                    selH.value = fh.header ? String(fh.header) : '0';
                }
            });
        }
    }
    setTimeout(traducirFontPicker, 100);
    setTimeout(traducirFontPicker, 300);
    setTimeout(traducirFontPicker, 800);
    setTimeout(traducirFontPicker, 1500);
    setTimeout(traducirFontPicker, 3000);
    setTimeout(traducirFontPicker, 5000);
    /* Re-traducir cuando cambia la seleccion (los selects se refrescan) */
    quill.on('selection-change', function(){ setTimeout(traducirFontPicker, 0); });

    /* Guardar la ultima seleccion real para los botones de tamanio (±1pt) */
    quill.on('selection-change', function(r) {
        if (r && r.index != null) {
            _qlRangoGuardado = { index: r.index, length: r.length };
        }
    });

    /* ===== Abrir enlace con Ctrl+clic en el editor ===== */
    quill.root.addEventListener('click', function(ev) {
        if (!(ev.ctrlKey || ev.metaKey)) return;
        var node = ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!node) {
            var n = ev.target;
            while (n && n !== quill.root && n.tagName !== 'A') n = n.parentNode;
            if (n && n.tagName === 'A') node = n;
        }
        if (node) {
            ev.preventDefault();
            var url = node.getAttribute('href') || '';
            if (/^(https?:|mailto:|tel:)/i.test(url)) {
                window.open(url, '_blank', 'noopener');
                logEvent('ok', 'Abriendo enlace: ' + url);
            }
        }
    });

    /* ===== Dropdown personalizado de prioridad + sello postal ===== */
    var prioSelect = document.getElementById('prioridad');
    var prioDd = document.getElementById('prioDd');
    var prioBtn = document.getElementById('prioDdBtn');
    var prioList = document.getElementById('prioDdList');
    var prioTxt = document.getElementById('prioDdTxt');
    var prioIco = document.querySelector('.prio-dd-btn .prio-dd-ico');
    var iconosP = { 'baja':'fa-arrow-down', 'normal':'fa-flag', 'alta':'fa-arrow-up', 'urgente':'fa-exclamation-triangle' };
    var coloresP = { 'baja':'#64748b', 'normal':'#2563eb', 'alta':'#d97706', 'urgente':'#dc2626' };

    function setPrio(val) {
        if (!prioSelect) return;
        prioSelect.value = val;
        var opt = prioSelect.options[prioSelect.selectedIndex];
        var ic = iconosP[val] || 'fa-flag';
        if (prioTxt) prioTxt.textContent = opt ? opt.textContent : '';
        if (prioIco) { prioIco.className = 'prio-dd-ico fas ' + ic; prioIco.style.color = coloresP[val] || '#2563eb'; }
        var sel = prioSelect.selectedIndex;
        if (prioList) {
            Array.from(prioList.children).forEach(function(li, i) {
                li.classList.toggle('seleccion', i === sel);
                li.setAttribute('aria-selected', i === sel ? 'true' : 'false');
            });
        }
        pintarSello(val);
    }

    if (prioDd && prioBtn && prioList) {
        prioBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var abrir = prioList.classList.toggle('open');
            prioBtn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        });
        Array.from(prioList.children).forEach(function(li) {
            li.addEventListener('click', function(e) {
                e.stopPropagation();
                setPrio(li.getAttribute('data-value'));
                prioList.classList.remove('open');
                prioBtn.setAttribute('aria-expanded', 'false');
            });
        });
        document.addEventListener('click', function() {
            if (prioList.classList.contains('open')) {
                prioList.classList.remove('open');
                prioBtn.setAttribute('aria-expanded', 'false');
            }
        });
        prioSelect.style.display = 'none';
    }

    if (prioSelect) {
        /* Sello postal: color, fondo, valor e icono según la prioridad */
        var selloPrioridad = document.getElementById('selloPrioridad');
        function pintarSello(prio) {
            if (!selloPrioridad) return;
            var sellos = {
                'baja':    { color:'#64748b', bg:'#ffffff', label:'Baja',    icono:'fa-arrow-down' },
                'normal':  { color:'#2563eb', bg:'#ffffff', label:'Normal',  icono:'fa-flag' },
                'alta':    { color:'#d97706', bg:'#ffffff', label:'Alta',    icono:'fa-arrow-up' },
                'urgente': { color:'#dc2626', bg:'#ffffff', label:'Urgente', icono:'fa-exclamation-triangle' }
            };
            var d = sellos[prio] || sellos['normal'];
            selloPrioridad.style.setProperty('--prio', d.color);
            selloPrioridad.style.setProperty('--prio-bg', d.bg);
            var v = selloPrioridad.querySelector('.sello-valor');
            if (v) v.textContent = d.label;
            var ic = selloPrioridad.querySelector('.sello-ico i');
            if (ic) ic.className = 'fas ' + d.icono;
            selloPrioridad.setAttribute('aria-label', 'Sello de prioridad ' + d.label);
            selloPrioridad.dataset.prio = prio;
        }
        pintarSello(prioSelect.value);
        setPrio(prioSelect.value);
    }



    /* ===== Tooltips en español ===== */
    var TOOLTIPS_ES = {
        'ql-bold': 'Negrita (Ctrl+B)', 'ql-italic': 'Cursiva (Ctrl+I)',
        'ql-undo-btn': 'Deshacer (Ctrl+Z)', 'ql-redo-btn': 'Rehacer (Ctrl+Y)',
        'ql-header-btn': 'Encabezados (H1-H6)',
        'ql-direction': 'Direccion de texto (RTL/LTR)',
        'ql-underline': 'Subrayado (Ctrl+U)', 'ql-strike': 'Tachado',
        'ql-blockquote': 'Cita', 'ql-code-block': 'Bloque de codigo',
        'ql-list-ordered': 'Numeracion', 'ql-list-bullet': 'Vinetas',
        'ql-list-check': 'Lista de verificacion',
        'ql-link': 'Enlace', 'ql-video': 'Insertar video', 'ql-formula': 'Insertar formula',
        'ql-clean': 'Limpiar formato',
        'ql-emoji-btn': 'Emojis', 'ql-attach-btn': 'Adjuntar archivo',
        'ql-html-btn': 'Ver HTML', 'ql-print-btn': 'Imprimir',
        'ql-size-custom': 'Tamaño de letra', 'ql-sym-btn': 'Caracteres especiales',
        'ql-table-btn': 'Insertar tabla', 'ql-img-btn': 'Insertar imagen',
        'ql-color': 'Color de texto', 'ql-background': 'Color de fondo',
        'ql-border-btn': 'Borde de linea', 'ql-align': 'Alineacion de texto',
        'ql-case-btn': 'Cambiar mayusculas / minusculas',
        'ql-indent': 'Sangria', 'ql-script': 'Subindice/Superindice',
        'ql-font': 'Tipo de letra', 'ql-header': 'Encabezado',
        'ql-size-mb': 'Disminuir Texto', 'ql-size-pb': 'Aumentar Texto'
    };
    document.querySelectorAll('.ql-toolbar button, .ql-toolbar .ql-picker-label, .ql-toolbar .ql-picker-options, .ql-toolbar select').forEach(function(el) {
        var txt = '';
        var cls = Array.from(el.classList).find(function(c) { return c.startsWith('ql-') && c !== 'ql-picker'; });
        if (cls && TOOLTIPS_ES[cls]) txt = TOOLTIPS_ES[cls];
        if (!txt) {
            var picker = el.closest('.ql-picker');
            if (picker) {
                var pc = Array.from(picker.classList).find(function(c) { return c.startsWith('ql-') && c !== 'ql-picker'; });
                if (pc === 'ql-color') txt = 'Color de texto';
                else if (pc === 'ql-background') txt = 'Color de fondo de texto';
                else if (TOOLTIPS_ES['ql-' + pc]) txt = TOOLTIPS_ES['ql-' + pc];
            }
        }
        if (!txt && el.getAttribute('title')) txt = el.getAttribute('title');
        if (!txt && cls) {
            var human = cls.replace(/^ql-/, '').replace(/-/g, ' ');
            txt = human.charAt(0).toUpperCase() + human.slice(1);
        }
        if (txt) { el.setAttribute('data-tt', txt); el.removeAttribute('title'); el.setAttribute('aria-label', txt); }
    });
    document.querySelectorAll('.ql-toolbar button, .ql-toolbar .ql-picker-label, .ql-toolbar select').forEach(function(el) {
        if (!el.getAttribute('data-tt')) el.setAttribute('data-tt', 'Herramienta de edicion');
    });
    /* Barrer tooltips nativos: ningun title en el toolbar ni sus dropdowns debe mostrarse,
       solo el globo custom (#ttGlobo). Convierte titles residuales en data-tt y los elimina. */
    document.querySelectorAll('.ql-toolbar [title]').forEach(function(el) {
        var txtN = el.getAttribute('title');
        if (!el.getAttribute('data-tt') && txtN) el.setAttribute('data-tt', txtN);
        el.removeAttribute('title');
    });
    /* Los dropdowns de pickers (.ql-picker-options > .ql-picker-item) tambien quitan su title */
    document.querySelectorAll('.ql-toolbar .ql-picker-options [title]').forEach(function(el) {
        var txtN = el.getAttribute('title');
        if (!el.getAttribute('data-tt') && txtN) el.setAttribute('data-tt', txtN);
        el.removeAttribute('title');
    });
    /* Barrido global: ningun tooltip nativo en la pagina; todo via globo custom */
    document.querySelectorAll('[title]').forEach(function(el) {
        var txtN = el.getAttribute('title');
        if (!el.getAttribute('data-tt') && txtN) el.setAttribute('data-tt', txtN);
        el.removeAttribute('title');
    });
    /* Tooltips custom (no nativos) */
    var ttGlobo = document.createElement('div');
    ttGlobo.id = 'ttGlobo';
    ttGlobo.style.cssText = 'position:fixed;z-index:99999;display:none;background:#0e7490;color:#ffffff;font-size:11px;font-weight:600;line-height:1.35;padding:6px 10px;border-radius:6px;border:1px solid #155e75;box-shadow:0 4px 14px rgba(0,0,0,.35);pointer-events:none;white-space:nowrap;';
    document.body.appendChild(ttGlobo);
    document.addEventListener('mouseover', function(ev) {
        /* Convertir en vivo cualquier title nativo residual (en cualquier elemento)
           a data-tt para que solo se vea el globo custom estilo Bootstrap. */
        var escaneado = ev.target.closest('[title]');
        if (escaneado && escaneado.getAttribute && escaneado.removeAttribute) {
            var tituloN = escaneado.getAttribute('title');
            if (!escaneado.getAttribute('data-tt') && tituloN) escaneado.setAttribute('data-tt', tituloN);
            escaneado.removeAttribute('title');
        }
        var t = ev.target.closest('[data-tt]');
        var txt = null;
        if (!t) {
            var pickerItem = ev.target.closest('.ql-picker-options .ql-picker-item, .ql-picker-options .ql-picker-option');
            if (pickerItem) {
                txt = pickerItem.getAttribute('data-label') || pickerItem.getAttribute('data-value') || (pickerItem.textContent || '').trim();
                if (!txt) txt = 'Opcion';
                t = pickerItem;
            } else if (ev.target.closest('.ql-toolbar')) {
                t = ev.target;
                txt = t.getAttribute('data-tt') || 'Herramienta de edicion';
                if (!t.getAttribute('data-tt')) t.setAttribute('data-tt', txt);
            }
        }
        if (!t) { ttGlobo.style.display = 'none'; return; }
        if (!txt) txt = t.getAttribute('data-tt');
        if (!txt && t.getAttribute('title')) { txt = t.getAttribute('title'); }
        if (t.hasAttribute('title')) t.removeAttribute('title');
        if (!txt) { ttGlobo.style.display = 'none'; return; }
        ttGlobo.textContent = txt;
        var r = t.getBoundingClientRect();
        ttGlobo.style.display = 'block';
        ttGlobo.style.left = '0px';
        ttGlobo.style.top = '0px';
        var g = ttGlobo.offsetWidth;
        var left = r.left + r.width / 2 - g / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - g - 8));
        ttGlobo.classList.remove('ttBelow');
        ttGlobo.style.left = left + 'px';
        ttGlobo.style.top = (r.top - ttGlobo.offsetHeight - 8) + 'px';
        if (r.top - ttGlobo.offsetHeight - 8 < 0) {
            ttGlobo.classList.add('ttBelow');
            ttGlobo.style.top = (r.bottom + 8) + 'px';
        }
    });
    document.addEventListener('mouseout', function(ev) {
        var t = ev.target.closest('[data-tt]');
        if (t) ttGlobo.style.display = 'none';
    });
    document.addEventListener('scroll', function() { ttGlobo.style.display = 'none'; }, true);
    /* ===== Menu contextual para tablas ===== */
    var ctxMenuEl = null;
    var ctxCell = null;
    function ocultarCtxMenu() {
        if (ctxMenuEl) { ctxMenuEl.remove(); ctxMenuEl = null; }
        ctxCell = null;
    }
    function separadorCtx() {
        var dv = document.createElement('div');
        dv.style.cssText = 'height:1px;background:#1e293b;margin:5px 8px;';
        return dv;
    }
    function crearCtxMenu(x, y, construir) {
        ocultarCtxMenu();
        var menu = document.createElement('div');
        menu.style.cssText = 'position:fixed;z-index:99998;min-width:210px;background:#0f172a;border:1px solid #334155;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.6);padding:6px;font-size:12px;color:#e2e8f0;';
        function item(txt, ic, fn, danger, colorIco) {
            var b = document.createElement('button');
            b.type = 'button';
            b.style.cssText = 'display:flex;align-items:center;gap:9px;width:100%;padding:7px 10px;background:none;border:none;border-radius:7px;color:' + (danger ? '#f87171' : '#e2e8f0') + ';font-size:12px;cursor:pointer;text-align:left;font-family:inherit;';
            b.innerHTML = '<i class="fas fa-' + ic + '" style="width:14px;font-size:11px;color:' + (colorIco || (danger ? '#f87171' : '#64748b')) + ';"></i><span>' + txt + '</span>';
            b.addEventListener('mouseover', function() { b.style.background = '#1e293b'; });
            b.addEventListener('mouseout', function() { b.style.background = 'none'; });
            b.addEventListener('click', function(ev) {
                ev.preventDefault(); ev.stopPropagation();
                ocultarCtxMenu();
                fn();
            });
            menu.appendChild(b);
            return b;
        }
        function itemSub(txt, ic, colorIco, construirSub) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative;';
            var b = document.createElement('button');
            b.type = 'button';
            b.style.cssText = 'display:flex;align-items:center;gap:9px;width:100%;padding:7px 10px;background:none;border:none;border-radius:7px;color:#e2e8f0;font-size:12px;cursor:pointer;text-align:left;font-family:inherit;';
            b.innerHTML = '<i class="fas fa-' + ic + '" style="width:14px;font-size:11px;color:' + (colorIco || '#64748b') + ';"></i><span style="flex:1;">' + txt + '</span><i class="fas fa-chevron-right" style="font-size:9px;color:#64748b;width:auto;"></i>';
            b.addEventListener('mouseover', function() { b.style.background = '#1e293b'; });
            b.addEventListener('mouseout', function() { b.style.background = 'none'; });
            var sub = document.createElement('div');
            sub.style.cssText = 'position:fixed;z-index:99999;min-width:190px;max-width:280px;max-height:320px;overflow-y:auto;background:#0f172a;border:1px solid #334155;border-radius:10px;box-shadow:0 14px 44px rgba(0,0,0,.65);padding:6px;font-size:12px;color:#e2e8f0;display:none;overflow-x:hidden;';
            function subItem(txt, ic, fn, danger, colorIco2) {
                var sb = document.createElement('button');
                sb.type = 'button';
                sb.style.cssText = 'display:flex;align-items:center;gap:9px;width:100%;padding:6px 10px;background:none;border:none;border-radius:7px;color:' + (danger ? '#f87171' : '#e2e8f0') + ';font-size:12px;cursor:pointer;text-align:left;font-family:inherit;white-space:normal;';
                sb.innerHTML = '<i class="fas fa-' + (ic || 'check') + '" style="width:14px;font-size:11px;color:' + (colorIco2 || '#4ade80') + ';"></i><span>' + txt + '</span>';
                sb.addEventListener('mouseover', function() { sb.style.background = '#1e293b'; });
                sb.addEventListener('mouseout', function() { sb.style.background = 'none'; });
                sb.addEventListener('click', function(ev) {
                    ev.preventDefault(); ev.stopPropagation();
                    ocultarCtxMenu();
                    fn();
                });
                sub.appendChild(sb);
                return sb;
            }
            if (construirSub) construirSub(sub, subItem);
            wrap.appendChild(b);
            wrap.appendChild(sub);
            menu.appendChild(wrap);
            function posicionarSub() {
                var r = b.getBoundingClientRect();
                var sw = sub.offsetWidth, sh = sub.offsetHeight;
                var left = r.right + 2;
                if (left + sw > window.innerWidth - 4) left = r.left - sw - 2;
                var top = Math.max(4, Math.min(r.top, window.innerHeight - sh - 4));
                sub.style.left = left + 'px';
                sub.style.top = top + 'px';
            }
            var cierre = null;
            function mostrarSub() {
                if (cierre) { clearTimeout(cierre); cierre = null; }
                sub.style.display = 'block';
                posicionarSub();
            }
            function ocultarSub() {
                cierre = setTimeout(function() { sub.style.display = 'none'; }, 220);
            }
            b.addEventListener('mouseenter', mostrarSub);
            b.addEventListener('mouseleave', ocultarSub);
            sub.addEventListener('mouseenter', mostrarSub);
            sub.addEventListener('mouseleave', ocultarSub);
            return b;
        }
        construir({ item: item, itemSub: itemSub, sep: separadorCtx, menu: menu });
        document.body.appendChild(menu);
        var mw = menu.offsetWidth, mh = menu.offsetHeight;
        var left = Math.max(4, Math.min(x, window.innerWidth - mw - 4));
        var top = Math.max(4, Math.min(y, window.innerHeight - mh - 4));
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        ctxMenuEl = menu;
    }
    function mostrarCtxMenu(x, y, td) {
        var cellRef = td;
        crearCtxMenu(x, y, function(t) {
            var item = t.item, sep = t.sep, menu = t.menu;
            item('Insertar fila arriba', 'arrow-up', function() { _txInsertarFila(cellRef, true); }, false, '#22c55e');
            item('Insertar fila abajo', 'arrow-down', function() { _txInsertarFila(cellRef, false); }, false, '#22c55e');
            item('Insertar columna izquierda', 'arrow-left', function() { _txInsertarColumna(cellRef, true); }, false, '#38bdf8');
            item('Insertar columna derecha', 'arrow-right', function() { _txInsertarColumna(cellRef, false); }, false, '#38bdf8');
            menu.appendChild(sep());
            item('Combinar celda con la derecha', 'code-merge', function() { _txCombinarCelda(cellRef, 'right'); }, false, '#a78bfa');
            item('Combinar celda con la izquierda', 'code-merge fa-flip-horizontal', function() { _txCombinarCelda(cellRef, 'left'); }, false, '#a78bfa');
            item('Combinar celda con la de abajo', 'code-merge', function() { _txCombinarCelda(cellRef, 'down'); }, false, '#a78bfa');
            item('Combinar celda con la de arriba', 'code-merge fa-flip-vertical', function() { _txCombinarCelda(cellRef, 'up'); }, false, '#a78bfa');
            item('Separar celda', 'code-branch', function() { _txSepararCelda(cellRef); }, false, '#a78bfa');
            menu.appendChild(sep());
            item('Eliminar fila', 'trash-can', function() {
                if (_txPosicionarEnCelda(cellRef)) quill.getModule('table').deleteRow();
            }, true);
            item('Eliminar columna', 'trash-can', function() {
                if (_txPosicionarEnCelda(cellRef)) quill.getModule('table').deleteColumn();
            }, true);
            item('Eliminar tabla', 'table', function() {
                if (_txPosicionarEnCelda(cellRef)) quill.getModule('table').deleteTable();
            }, true);
            menu.appendChild(sep());
            var anular = item('Cancelar', 'xmark', function() {});
            anular.style.color = '#94a3b8';
        });
        ctxCell = td;
    }
    function _txPosicionarEnCelda(td) {
        try {
            var blot = quill.scroll.find(td);
            if (!blot) return false;
            var idx = quill.getIndex(blot);
            if (idx < 0) return false;
            quill.setSelection(idx, 0, 'silent');
            return true;
        } catch (e) { return false; }
    }
    /* Parche: que Quill no recalcule filas con celdas combinadas */
    (function() {
        var modTbl = quill.getModule('table');
        if (!modTbl) return;
        var origBalance = modTbl.balanceTables;
        modTbl.balanceTables = function() {
            var scrollBlot = this.quill.scroll;
            try {
                scrollBlot.descendants(function(b) {
                    return b.domNode && b.domNode.tagName === 'TABLE';
                }).forEach(function(tblBlot) {
                    var tds = tblBlot.domNode.querySelectorAll('td');
                    var conMerge = false;
                    for (var i = 0; i < tds.length; i++) {
                        if ((tds[i].colSpan || 1) > 1 || (tds[i].rowSpan || 1) > 1) { conMerge = true; break; }
                    }
                    if (!conMerge) tblBlot.balanceCells();
                });
            } catch (e) { try { origBalance.call(this); } catch (e2) {} }
        };
    })();
    function _txEstilosFila(trOriginal) {
        return Array.prototype.map.call(trOriginal.children, function(c) { return c.getAttribute('style') || ''; });
    }
    function _txInsertarFila(cell, arriba) {
        if (!_txPosicionarEnCelda(cell)) return;
        var tr = cell.parentNode;
        var refer = arriba ? tr : (tr.nextElementSibling || tr);
        var estilos = _txEstilosFila(refer);
        var mod = quill.getModule('table');
        if (arriba) mod.insertRowAbove(); else mod.insertRowBelow();
        var nuevoTr = arriba ? tr.previousElementSibling : tr.nextElementSibling;
        if (nuevoTr && estilos.length) {
            Array.prototype.forEach.call(nuevoTr.children, function(c, i) {
                if (estilos[i]) c.setAttribute('style', estilos[i]);
            });
        }
    }
    /* ===== Navegacion con Tab entre celdas de tabla (estilo Excel) ===== */
    function _txTdBajoCursor() {
        try {
            var sel = quill.getSelection();
            if (sel && !sel.length) {
                var leafInfo = quill.getLeaf(sel.index);
                var node = leafInfo && leafInfo[0] ? leafInfo[0].domNode : null;
                if (node && node.nodeType === 3) node = node.parentElement;
                var td = node && node.closest ? node.closest('td') : null;
                if (td) return td;
            }
        } catch (e) {}
        try {
            var selNat = document.getSelection();
            if (selNat && selNat.rangeCount) {
                var anc = selNat.getRangeAt(0).startContainer;
                if (anc && anc.nodeType === 3) anc = anc.parentElement;
                var cerca = anc && anc.closest ? anc.closest('td') : null;
                if (cerca) return cerca;
            }
        } catch (e) {}
        return null;
    }
    function _txProximaCelda(td, dir, tabla) {
        var tr = td.parentNode;
        var filas = tabla.rows;
        var idx = filas ? Array.prototype.indexOf.call(filas, tr) : -1;
        var celdasFila = tr.cells || tr.children;
        var idxCel = Array.prototype.indexOf.call(celdasFila, td);
        if (dir > 0) {
            /* ultima celda de la fila -> primera de la siguiente */
            if (idxCel < celdasFila.length - 1) return celdasFila[idxCel + 1];
            var sigTr = filas[idx + 1];
            return sigTr && sigTr.cells ? sigTr.cells[0] : null;
        }
        if (idxCel > 0) return celdasFila[idxCel - 1];
        var antTr = filas[idx - 1];
        return antTr && antTr.cells ? antTr.cells[antTr.cells.length - 1] : null;
    }
    function _txTabEnTabla(ev) {
        var td = _txTdBajoCursor();
        if (!td) {
            /* No hay celda: dejar que Quill procese Tab (listas, sangria) */
            return;
        }
        var tabla = td.closest('table');
        if (!tabla) return;
        var filas = tabla.rows;
        if (!filas.length) return;
        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation();
        var ultimaFila = filas[filas.length - 1];
        var esUltimaCelda = (td.parentNode === ultimaFila) &&
            (td === ultimaFila.cells[ultimaFila.cells.length - 1]);
        if (esUltimaCelda && !ev.shiftKey) {
            _txInsertarFila(td, false);
            var sigTr = td.parentNode.nextElementSibling;
            var primerCelda = sigTr && sigTr.cells ? sigTr.cells[0] : null;
            if (primerCelda) _txPosicionarEnCelda(primerCelda);
            return;
        }
        if (ev.shiftKey && td.parentNode === filas[0] && td === filas[0].cells[0]) {
            return;
        }
        var destino = _txProximaCelda(td, ev.shiftKey ? -1 : 1, tabla);
        if (destino) _txPosicionarEnCelda(destino);
    }
    function _txInsertarColumna(cell, izq) {
        if (!_txPosicionarEnCelda(cell)) return;
        var tr = cell.parentNode;
        var idxCol = Array.prototype.indexOf.call(tr.children, cell);
        var tabla = cell.closest('table');
        var estilos = [];
        var filas = tabla ? tabla.rows : [];
        for (var i = 0; i < filas.length; i++) {
            var c = filas[i].cells && filas[i].cells[idxCol];
            estilos.push(c ? (c.getAttribute('style') || '') : '');
        }
        var mod = quill.getModule('table');
        if (izq) mod.insertColumnLeft(); else mod.insertColumnRight();
        var nuevaPos = izq ? idxCol : idxCol + 1;
        var filasN = tabla ? tabla.rows : [];
        for (var k = 0; k < filasN.length; k++) {
            var celda = filasN[k].cells && filasN[k].cells[nuevaPos];
            if (celda && estilos[k]) celda.setAttribute('style', estilos[k]);
        }
    }
    function _txColVisual(td) {
        var tr = td ? td.parentNode : null;
        if (!tr) return 0;
        var col = 0;
        for (var i = 0; i < tr.children.length; i++) {
            var c = tr.children[i];
            if (c === td) break;
            col += (c.colSpan || 1);
        }
        return col;
    }
    function _txCeldaEnColumna(filaTr, col) {
        var acc = 0;
        for (var i = 0; i < filaTr.children.length; i++) {
            var c = filaTr.children[i];
            if (acc === col) return c;
            acc += (c.colSpan || 1);
            if (acc > col) return null;
        }
        return null;
    }
    function _txRemoverCelda(tr, cell) {
        var blot = null;
        try { blot = quill.scroll.find(cell) || null; } catch (e) {}
        if (blot && blot.parent) blot.remove();
        else if (tr && cell && cell.parentNode === tr) tr.removeChild(cell);
    }
    function _txCombinarCelda(td, dir) {
        var tr = td ? td.parentNode : null;
        if (!tr) return;
        var tableEl = td.closest('table');
        if (dir === 'right' || dir === 'left') {
            var cellIdx = Array.prototype.indexOf.call(tr.children, td);
            var vecino = (dir === 'right') ? tr.children[cellIdx + 1] : tr.children[cellIdx - 1];
            if (!vecino) return;
            if (dir === 'right') {
                td.colSpan = (td.colSpan || 1) + (vecino.colSpan || 1);
                _txRemoverCelda(tr, vecino);
            } else {
                vecino.colSpan = (vecino.colSpan || 1) + (td.colSpan || 1);
                _txRemoverCelda(tr, td);
            }
        } else {
            var rows = tableEl ? Array.prototype.slice.call(tableEl.rows) : [];
            var rowIdx = rows.indexOf(tr);
            var targetRow = (dir === 'down') ? rows[rowIdx + 1] : rows[rowIdx - 1];
            if (!targetRow) return;
            var celdaTarget = _txCeldaEnColumna(targetRow, _txColVisual(td));
            if (!celdaTarget) return;
            if (dir === 'down') {
                td.rowSpan = (td.rowSpan || 1) + (celdaTarget.rowSpan || 1);
                _txRemoverCelda(targetRow, celdaTarget);
            } else {
                celdaTarget.rowSpan = (celdaTarget.rowSpan || 1) + (td.rowSpan || 1);
                _txRemoverCelda(tr, td);
            }
        }
        if (tableEl) tableEl.setAttribute('data-merged', '1');
    }
    function _txSepararCelda(td) {
        if (!td) return;
        var tr = td.parentNode;
        var tableEl = td.closest('table');
        if ((td.colSpan || 1) > 1) {
            var cs = td.colSpan;
            td.colSpan = 1;
            for (var i = 1; i < cs; i++) {
                var c = document.createElement('td');
                c.innerHTML = '<p><br></p>';
                c.style.cssText = td.getAttribute('style') || '';
                tr.insertBefore(c, td.nextSibling);
            }
        } else if ((td.rowSpan || 1) > 1) {
            var rs = td.rowSpan;
            td.rowSpan = 1;
            if (tableEl) {
                var rows = Array.prototype.slice.call(tableEl.rows);
                var rowIdx = rows.indexOf(tr);
                for (var r2 = 1; r2 < rs; r2++) {
                    var crow = rows[rowIdx + r2];
                    if (!crow) break;
                    var ci = Array.prototype.indexOf.call(crow.children, td);
                    var c2 = document.createElement('td');
                    c2.innerHTML = '<p><br></p>';
                    c2.style.cssText = td.getAttribute('style') || '';
                    if (ci < 0) crow.appendChild(c2);
                    else crow.insertBefore(c2, crow.children[ci]);
                }
            }
        }
    }
    function mostrarCtxMenuMensaje(x, y, ev, infoOrt) {
        ev.preventDefault();
        crearCtxMenu(x, y, function(t) {
            var item = t.item, sep = t.sep, menu = t.menu;
            if (infoOrt && infoOrt.palabra) {
                var wInfo = { inicio: infoOrt.inicio, longitud: infoOrt.longitud, formats: infoOrt.formats, palabra: infoOrt.palabra };
                var b = t.itemSub('Ortografía', 'spell-check', '#22d3ee', function(sub, subItem) {
                    var hd = document.createElement('div');
                    hd.style.cssText = 'padding:2px 10px;font-size:11px;color:#94a3b8;font-weight:600;border-bottom:1px solid #1e293b;margin-bottom:4px;';
                    hd.textContent = '¿Querías decir...? "' + wInfo.palabra + '"';
                    sub.appendChild(hd);
                    if (!_esOmitida(wInfo.palabra)) {
                        _cargarTypo().then(function(ty) {
                            if (!ty) return;
                            if (ty.check(wInfo.palabra)) return;
                            var sugs = ty.suggest(wInfo.palabra, 10);
                            if (!sugs || !sugs.length) return;
                            sugs.forEach(function(sg) {
                                subItem(sg, 'check', function() { _reemplazarPalabra(wInfo, sg); }, false, '#4ade80');
                            });
                        });
                    }
                    var om = subItem('Omitir "' + wInfo.palabra + '"', 'eye-slash', function() { _omitirOrtografia(wInfo); }, false, '#94a3b8');
                    om.style.color = '#cbd5e1';
                });
                var wrap = b.parentNode;
                if (wrap && menu.contains(wrap)) menu.insertBefore(wrap, menu.firstChild);
            }
            function clipAccion(fn, fallback) {
                var selQ = _qlRangoGuardado || quill.getSelection(true);
                try {
                    if (selQ) {
                        quill.setSelection(selQ.index, selQ.length, 'silent');
                        quill.focus();
                    }
                } catch (e) {}
                if (!fn()) fallback && fallback();
            }
            item('Cortar', 'scissors', function() {
                if (_rszImg) {
                    _rszCopiarMarcada();
                    try {
                        var blotRs = quill.scroll.find(_rszImg);
                        if (blotRs) quill.deleteText(quill.getIndex(blotRs), blotRs.length(), Quill.sources.USER);
                    } catch (e) {}
                    _rszDesactivar();
                    return;
                }
                clipAccion(function() { try { return document.execCommand('cut'); } catch (e) { return false; } });
            }, false, '#f97316');
            item('Copiar', 'copy', function() {
                if (_rszImg) { _rszCopiarMarcada(); return; }
                clipAccion(function() {
                    var selQ = _qlRangoGuardado || quill.getSelection(true);
                    if (selQ && selQ.length > 0) {
                        var txt = quill.getText(selQ.index, selQ.length);
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            try { navigator.clipboard.writeText(txt); return true; } catch (e) {}
                        }
                    }
                    try { return document.execCommand('copy'); } catch (e) { return false; }
                });
            }, false, '#38bdf8');
            item('Pegar', 'paste', function() {
                var selQ = _qlRangoGuardado || quill.getSelection(true) || { index: 0, length: 0 };
                function pegarTexto(txtP) {
                    if (txtP) {
                        quill.clipboard.dangerouslyPasteHTML(selQ.index, txtP.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>'));
                        quill.setSelection(selQ.index + txtP.length, 0, 'silent');
                    }
                }
                function pegarImagen(blob) {
                    var rd = new FileReader();
                    rd.onload = function() {
                        quill.insertEmbed(selQ.index, 'image', rd.result);
                        try { quill.setSelection(selQ.index + 1, 0, Quill.sources.USER); } catch (e) {}
                    };
                    rd.readAsDataURL(blob);
                }
                if (navigator.clipboard && navigator.clipboard.read) {
                    navigator.clipboard.read().then(function(its) {
                        var imgBlob = null, htmlTxt = '';
                        its.forEach(function(it) {
                            if (it.types.indexOf('image/png') >= 0 || it.types.indexOf('image/jpeg') >= 0) {
                                try {
                                    var t = it.types.indexOf('image/png') >= 0 ? 'image/png' : 'image/jpeg';
                                    it.getType(t).then(function(b) { imgBlob = b; if (imgBlob) pegarImagen(imgBlob); });
                                } catch (e) {}
                            } else if (it.types.indexOf('text/html') >= 0) {
                                it.getType('text/html').then(function(b) { return b.text(); }).then(function(txt) { htmlTxt = txt; if (!imgBlob && htmlTxt) quill.clipboard.dangerouslyPasteHTML(selQ.index, htmlTxt); }).catch(function(){});
                            } else if (it.types.indexOf('text/plain') >= 0) {
                                it.getType('text/plain').then(function(b) { return b.text(); }).then(pegarTexto).catch(function(){});
                            }
                        });
                    }).catch(function() {
                        try { document.execCommand('paste'); } catch (e) {}
                    });
                } else if (navigator.clipboard && navigator.clipboard.readText) {
                    navigator.clipboard.readText().then(pegarTexto).catch(function() {
                        try { document.execCommand('paste'); } catch (e) {}
                    });
                } else {
                    try { document.execCommand('paste'); } catch (e) {}
                }
            }, false, '#4ade80');
            menu.appendChild(sep());
            item('Seleccionar todo', 'expand', function() {
                quill.setSelection(0, quill.getLength(), 'silent');
                quill.focus();
            }, false, '#a78bfa');
            item('Limpiar formato (selección)', 'eraser', function() {
                var selQ = _qlRangoGuardado || quill.getSelection(true);
                if (selQ && selQ.length > 0) {
                    quill.removeFormat(selQ.index, selQ.length);
                }
            }, false, '#fbbf24');
            item('Limpiar formato (todo el mensaje)', 'broom', function() {
                quill.removeFormat(0, quill.getLength());
            }, false, '#fb7185');
            menu.appendChild(sep());
            var sp = _qcEditor ? _qcEditor.getAttribute('spellcheck') : null;
            item('Revisar ortografia' + (sp === 'false' ? ' (desactivada)' : ''), 'spell-check', function() {
                if (_qcEditor) {
                    var cur = _qcEditor.getAttribute('spellcheck') !== 'false';
                    _qcEditor.setAttribute('spellcheck', cur ? 'false' : 'true');
                }
            }, false, '#22d3ee');
            item('Insertar emoji', 'face-smile', function() {
                if (typeof crearEmojiPicker === 'function') crearEmojiPicker();
                else { var b = document.querySelector('.ql-emoji-btn'); if (b) b.click(); }
            }, false, '#facc15');
            menu.appendChild(sep());
            var btns = {
                'Ver HTML': '.ql-html-btn',
                'Imprimir': '.ql-print-btn',
                'Exportar PDF': '.ql-pdf-btn'
            };
            Object.keys(btns).forEach(function(lbl) {
                item(lbl, lbl === 'Imprimir' ? 'print' : lbl === 'Exportar PDF' ? 'file-pdf' : 'code', function() {
                    var el = document.querySelector(btns[lbl]);
                    if (el) el.click();
                }, false, lbl === 'Imprimir' ? '#94a3b8' : lbl === 'Exportar PDF' ? '#f87171' : '#fb7185');
            });
            menu.appendChild(sep());
            var anular = item('Cancelar', 'xmark', function() {});
            anular.style.color = '#94a3b8';
        });
    }
    var _qcEditor = document.querySelector('.ql-editor');
    if (_qcEditor) {
        var _typo = null;
        var _typoProm = null;
        var _spPopup = null;
        var _spInfo = null;
        var _spOmitidas = {};
        function _cargarTypo() {
            if (_typo) return Promise.resolve(_typo);
            if (_typoProm) return _typoProm;
            _typoProm = Promise.all([
                fetch('js/es.aff').then(function(r) { return r.text(); }),
                fetch('js/es.dic').then(function(r) { return r.text(); })
            ]).then(function(d) {
                try {
                    _typo = new Typo('es_ES', d[0], d[1], {});
                } catch (e) { _typo = null; }
                return _typo;
            });
            return _typoProm;
        }
        function _esLetraP(c) { return /[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/.test(c); }
        function _omitirOrtografia(info) {
            if (!info || !info.palabra) return;
            _spOmitidas[String(info.palabra).toLocaleLowerCase()] = true;
            try {
                var nl = (info.longitud || info.palabra.length);
                var palabra = info.palabra;
                var fmt = null;
                try { fmt = quill.getFormat(info.inicio, nl); } catch (e3) {}
                quill.formatText(info.inicio, nl, { nospell: 'false' });
                quill.setSelection(info.inicio + nl, 0, 'silent');
                _quitarSpPopup();
                ocultarCtxMenu();
            } catch (e) { _quitarSpPopup(); ocultarCtxMenu(); }
        }
        function _esOmitida(p) { return !!_spOmitidas[String(p).toLocaleLowerCase()]; }
        function _quitarSpPopup() {
            if (_spPopup) { _spPopup.remove(); _spPopup = null; }
            _spInfo = null;
        }
        function _reemplazarPalabra(info, sugerencia) {
            try {
                var fmt = info && info.formats ? info.formats : null;
                quill.deleteText(info.inicio, info.longitud, 'user');
                quill.insertText(info.inicio, sugerencia, fmt || {}, 'user');
                quill.setSelection(info.inicio + sugerencia.length, 0, 'silent');
                _quitarSpPopup();
            } catch (e) { _quitarSpPopup(); }
        }
        function _mostrarSpPopup(x, y, info, sugerencias) {
            _quitarSpPopup();
            var div = document.createElement('div');
            div.style.cssText = 'position:fixed;z-index:100000;min-width:190px;max-width:260px;background:#0f172a;border:1px solid #334155;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.6);padding:6px;font-size:12px;color:#e2e8f0;';
            var titulo = document.createElement('div');
            titulo.style.cssText = 'padding:2px 10px;font-size:11px;color:#94a3b8;font-weight:600;border-bottom:1px solid #1e293b;margin-bottom:4px;';
            titulo.textContent = '¿Querías decir...?';
            div.appendChild(titulo);
            sugerencias.forEach(function(sg) {
                var b = document.createElement('button');
                b.type = 'button';
                b.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;padding:6px 10px;background:none;border:none;border-radius:7px;color:#e2e8f0;font-size:12px;cursor:pointer;text-align:left;font-family:inherit;';
                b.innerHTML = '<i class="fas fa-check" style="width:14px;font-size:11px;color:#4ade80;"></i><span>' + sg.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span>';
                b.addEventListener('mouseover', function() { b.style.background = '#1e293b'; });
                b.addEventListener('mouseout', function() { b.style.background = 'none'; });
                b.addEventListener('click', function() {
                    _reemplazarPalabra(_spInfo, sg);
                });
                div.appendChild(b);
            });
            var cerrar = document.createElement('button');
            cerrar.type = 'button';
            cerrar.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;padding:6px 10px;background:none;border:none;border-radius:7px;color:#94a3b8;font-size:12px;cursor:pointer;text-align:left;font-family:inherit;margin-top:4px;border-top:1px solid #1e293b;';
            cerrar.innerHTML = '<i class="fas fa-xmark" style="width:14px;font-size:11px;"></i><span>Descartar</span>';
            cerrar.addEventListener('mouseover', function() { cerrar.style.background = '#1e293b'; });
            cerrar.addEventListener('mouseout', function() { cerrar.style.background = 'none'; });
            cerrar.addEventListener('click', _quitarSpPopup);
            div.appendChild(cerrar);
            var omitir = document.createElement('button');
            omitir.type = 'button';
            omitir.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;padding:6px 10px;background:none;border:none;border-radius:7px;color:#cbd5e1;font-size:12px;cursor:pointer;text-align:left;font-family:inherit;margin-top:2px;';
            omitir.innerHTML = '<i class="fas fa-eye-slash" style="width:14px;font-size:11px;color:#94a3b8;"></i><span>Omitir "' + (info.palabra || '').replace(/&/g, '&amp;').replace(/</g, '&lt;') + '"</span>';
            omitir.addEventListener('mouseover', function() { omitir.style.background = '#1e293b'; });
            omitir.addEventListener('mouseout', function() { omitir.style.background = 'none'; });
            omitir.addEventListener('click', function() { _omitirOrtografia(info); });
            div.appendChild(omitir);
            document.body.appendChild(div);
            var mw = div.offsetWidth, mh = div.offsetHeight;
            div.style.left = Math.max(4, Math.min(x, window.innerWidth - mw - 4)) + 'px';
            div.style.top = Math.max(4, Math.min(y + 14, window.innerHeight - mh - 4)) + 'px';
            _spPopup = div;
            _spInfo = info;
        }
        _qcEditor.addEventListener('mouseup', function(ev) {
            if (ev.button !== 0) return;
            if (_qcEditor.getAttribute('spellcheck') === 'false') return;
            if (_celdasMarcadas && _celdasMarcadas.length) return;
            try {
                var sel = quill.getSelection();
                if (!sel || sel.length > 0) return;
                var idx = sel.index;
                var leaf = quill.getLeaf(idx);
                if (!leaf || !leaf[0]) return;
                var blot = leaf[0];
                var node = blot.domNode;
                if (!node || node.nodeType !== 3) return;
                var off = leaf[1] || 0;
                var texto = node.data || '';
                var ini = off, fin = off;
                while (ini > 0 && _esLetraP(texto.charAt(ini - 1))) ini--;
                while (fin < texto.length && _esLetraP(texto.charAt(fin))) fin++;
                if (ini === fin) return;
                var palabra = texto.substring(ini, fin);
                var inicio = idx - (off - ini);
                var longitud = fin - ini;
                if (palabra.length < 2) return;
                if (_esOmitida(palabra)) return;
                _cargarTypo().then(function(t) {
                    if (!t) return;
                    if (t.check(palabra)) return;
                    var sugs = t.suggest(palabra, 6);
                    if (!sugs || !sugs.length) return;
                    var fmt = null;
                    try { fmt = quill.getFormat(inicio, longitud); } catch (e2) {}
                    _mostrarSpPopup(ev.clientX, ev.clientY, { inicio: inicio, longitud: longitud, formats: fmt }, sugs);
                });
            } catch (e) {}
        });
        document.addEventListener('mousedown', function(ev) {
            if (_spPopup && ev.target && (!ev.target.closest || !ev.target.closest('.ql-toolbar'))) {
                if (!(ev.target.isSameNode && ev.target.isSameNode(_spPopup)) && !(_spPopup.contains && _spPopup.contains(ev.target))) {
                    _quitarSpPopup();
                }
            }
        }, true);
        var _qcElFocusable = quill && quill.root ? quill.root : _qcEditor;
        _qcElFocusable.addEventListener('keydown', function() {
            if (_spPopup) setTimeout(_quitarSpPopup, 0);
        });
        function _palabraEnPuntoCtx(x, y) {
            try {
                var range = null;
                if (document.caretRangeFromPoint) range = document.caretRangeFromPoint(x, y);
                else if (document.caretPositionFromPoint) {
                    var cp = document.caretPositionFromPoint(x, y);
                    if (cp) range = { startContainer: cp.offsetNode, startOffset: cp.offset };
                }
                if (!range || !range.startContainer) {
                    var sFall = quill.getSelection(true);
                    return _palabraDeCaret(sFall);
                }
                var node = range.startContainer;
                var off = range.startOffset || 0;
                if (node.nodeType !== 3) {
                    var sF2 = quill.getSelection(true);
                    return _palabraDeCaret(sF2);
                }
                var texto = node.data || '';
                var ini = off, fin = off;
                while (ini > 0 && _esLetraP(texto.charAt(ini - 1))) ini--;
                while (fin < texto.length && _esLetraP(texto.charAt(fin))) fin++;
                if (ini === fin) return null;
                var palabra = texto.substring(ini, fin);
                if (palabra.length < 2) return null;
                var inicio = null;
                try {
                    var selN = window.getSelection();
                    var save = null;
                    if (selN && selN.rangeCount) save = selN.getRangeAt(0).cloneRange();
                    var r2 = document.createRange();
                    r2.setStart(node, ini);
                    r2.setEnd(node, fin);
                    selN.removeAllRanges();
                    selN.addRange(r2);
                    var selQ = quill.getSelection(true);
                    if (selQ) inicio = selQ.index;
                    if (save) { selN.removeAllRanges(); selN.addRange(save); }
                } catch (e3) {}
                if (inicio == null) {
                    var sF3 = quill.getSelection(true);
                    return _palabraDeCaret(sF3);
                }
                var longitud = fin - ini;
                var fmt = null;
                try { fmt = quill.getFormat(inicio, longitud); } catch (e2) {}
                if (fmt && fmt.nospell) return null;
                return { palabra: palabra, inicio: inicio, longitud: longitud, formats: fmt, word: palabra };
            } catch (e) { return null; }
        }
        function _palabraDeCaret(selQ) {
            try {
                if (!selQ || selQ.length > 0) return null;
                var leaf = quill.getLeaf(selQ.index);
                if (!leaf || !leaf[0]) return null;
                var blot = leaf[0];
                var node = blot.domNode;
                if (!node || node.nodeType !== 3) return null;
                var off = leaf[1] || 0;
                var texto = node.data || '';
                var ini = off, fin = off;
                while (ini > 0 && _esLetraP(texto.charAt(ini - 1))) ini--;
                while (fin < texto.length && _esLetraP(texto.charAt(fin))) fin++;
                if (ini === fin) return null;
                var palabra = texto.substring(ini, fin);
                if (palabra.length < 2) return null;
                var inicio = selQ.index - (off - ini);
                var longitud = fin - ini;
                var fmt = null;
                try { fmt = quill.getFormat(inicio, longitud); } catch (e2) {}
                if (fmt && fmt.nospell) return null;
                return { palabra: palabra, inicio: inicio, longitud: longitud, formats: fmt, word: palabra };
            } catch (e) { return null; }
        }
        _qcEditor.addEventListener('contextmenu', function(ev) {
            var td = ev.target.closest('td');
            if (!td) {
                var infoOrt = null;
                if (_qcEditor.getAttribute('spellcheck') !== 'false' && typeof quill !== 'undefined') {
                    infoOrt = _palabraEnPuntoCtx(ev.clientX, ev.clientY);
                }
                mostrarCtxMenuMensaje(ev.clientX, ev.clientY, ev, infoOrt);
                return;
            }
            ev.preventDefault();
            mostrarCtxMenu(ev.clientX, ev.clientY, td);
        });
        _qcEditor.addEventListener('click', ocultarCtxMenu);
    }
    document.addEventListener('scroll', ocultarCtxMenu, true);
    document.addEventListener('mousedown', function(ev) {
        if (ctxMenuEl && !ev.button && ev.target.closest && !ev.target.closest('.ql-toolbar')) {
            if (!ev.target.isSameNode(ctxMenuEl) && !ctxMenuEl.contains(ev.target)) ocultarCtxMenu();
        }
    });
    /* ===== Redimensionar filas y columnas de tablas con click y arrastre ===== */
    (function() {
        var editorDeTbl = document.querySelector('.ql-editor');
        if (!editorDeTbl) return;
        var estado = null; /* {td, dir: 'col'|'row', startX, startY, base} */
        var guia = document.createElement('div');
        guia.style.cssText = 'position:fixed;z-index:99997;pointer-events:none;background:#0ea5e9;box-shadow:0 0 8px rgba(14,165,233,.8);display:none;';
        document.body.appendChild(guia);
        function esTabla(td) { return !!td && td.tagName === 'TD' && !!td.closest('table'); }
        function posGuia(dir, x, y, ancho, alto) {
            guia.style.display = 'block';
            guia.style.width = ancho + 'px';
            guia.style.height = alto + 'px';
            guia.style.left = x + 'px';
            guia.style.top = y + 'px';
        }
        function ocultarGuia() { guia.style.display = 'none'; }
        var tdCursorEl = null, tdCursorOk = null;
        function setCtlCursor(tdEl, tipo) {
            if (tdCursorEl) {
                if (tdCursorOk) tdCursorEl.style.cursor = tdCursorOk;
                else tdCursorEl.style.removeProperty('cursor');
                tdCursorEl = null; tdCursorOk = null;
            }
            if (tdEl && tipo) {
                tdCursorOk = tdEl.style.cursor || '';
                tdCursorEl = tdEl;
                tdEl.style.setProperty('cursor', tipo);
            }
        }
        function resetCursorTbl() {
            editorDeTbl.style.cursor = '';
            setCtlCursor(null);
        }
        /* Resaltar la fila o columna que se va a redimensionar (fondo en otro color) */
        function limpiarMarcaTbl() {
            editorDeTbl.querySelectorAll('td.tbl-marca-fila, td.tbl-marca-col').forEach(function(c) {
                c.classList.remove('tbl-marca-fila');
                c.classList.remove('tbl-marca-col');
            });
        }
        function marcarFilaCl(td, dir) {
            limpiarMarcaTbl();
            var tabla = td ? td.closest('table') : null;
            if (!tabla) return;
            if (dir === 'row') {
                Array.prototype.forEach.call(td.parentNode.cells, function(c) {
                    if (c) c.classList.add('tbl-marca-fila');
                });
            } else {
                var idxCol = td.cellIndex;
                Array.prototype.forEach.call(tabla.rows, function(tr) {
                    var c = tr.cells && tr.cells[idxCol];
                    if (c) c.classList.add('tbl-marca-col');
                });
            }
        }
        function aplicarCursorBorde(ev) {
            if (estado) return;
            if (selB && selB.activo) { limpiarMarcaTbl(); ocultarGuia(); return; }
            var td = ev.target && ev.target.closest ? ev.target.closest('td') : null;
            if (!esTabla(td)) { editorDeTbl.style.cursor = ''; setCtlCursor(null); limpiarMarcaTbl(); ocultarGuia(); return; }
            var r = td.getBoundingClientRect();
            var d = 6;
            var cercaIzq = ev.clientX - r.left < d;
            var cercaDer = r.right - ev.clientX < d;
            var cercaSup = ev.clientY - r.top < d;
            var cercaInf = r.bottom - ev.clientY < d;
            if (cercaIzq) { setCtlCursor(td, 'col-resize'); marcarFilaCl(td, 'col'); posGuia('col', r.left - 4, r.top, 4, r.height); }
            else if (cercaDer) { setCtlCursor(td, 'col-resize'); marcarFilaCl(td, 'col'); posGuia('col', r.right - 2, r.top, 4, r.height); }
            else if (cercaSup) { setCtlCursor(td, 'row-resize'); marcarFilaCl(td, 'row'); posGuia('row', r.left, r.top - 2, r.width, 4); }
            else if (cercaInf) { setCtlCursor(td, 'row-resize'); marcarFilaCl(td, 'row'); posGuia('row', r.left, r.bottom - 2, r.width, 4); }
            else { editorDeTbl.style.cursor = ''; setCtlCursor(null); limpiarMarcaTbl(); ocultarGuia(); }
        }
        function iniciarArrastre(ev) {
            if (ev.button !== 0) return;
            var td = ev.target && ev.target.closest ? ev.target.closest('td') : null;
            if (!esTabla(td)) return;
            var r = td.getBoundingClientRect();
            var d = 6;
            var cercaIzq = ev.clientX - r.left < d;
            var cercaDer = r.right - ev.clientX < d;
            var cercaSup = ev.clientY - r.top < d;
            var cercaInf = r.bottom - ev.clientY < d;
            if (!cercaIzq && !cercaDer && !cercaSup && !cercaInf) return;
            var dir = (cercaIzq || cercaDer) ? 'col' : 'row';
            var linea = (dir === 'col')
                ? (cercaIzq ? r.left : r.right)
                : (cercaSup ? r.top : r.bottom);
            var base = (dir === 'col') ? r.width : r.height;
            /* No roba la seleccion de texto: el preventDefault solo se ejecuta
               cuando se confirma que es un resize (apoyado sobre la linea). */
            estado = { td: td, dir: dir, linea: linea, startX: ev.clientX, startY: ev.clientY, base: base, tabla: td.closest('table'), activo: false };
            document.addEventListener('mousemove', moverArrastre);
            document.addEventListener('mouseup', finArrastre);
        }
        function moverArrastre(ev) {
            if (!estado) return;
            if (!estado.activo) {
                var mvX = Math.abs(ev.clientX - estado.startX);
                var mvY = Math.abs(ev.clientY - estado.startY);
                if (mvX <= 3 && mvY <= 3) return;
                /* Confirmar intencion de resize: el puntero debe seguir PRÁCTICAMENTE
                   sobre la linea del borde (desviarse hacia la celda = seleccionar texto). */
                var sigoLinea = estado.dir === 'col'
                    ? Math.abs(ev.clientX - estado.linea) <= 6
                    : Math.abs(ev.clientY - estado.linea) <= 6;
                if (!sigoLinea) {
                    /* No es resize: no tocamos la seleccion, Quill la sigue normal. */
                    estado = null;
                    limpiarMarcaTbl();
                    ocultarGuia();
                    document.removeEventListener('mousemove', moverArrastre);
                    document.removeEventListener('mouseup', finArrastre);
                    editorDeTbl.style.cursor = '';
                    setCtlCursor(null);
                    return;
                }
                estado.activo = true;
                ev.preventDefault();
                try { document.body.style.cursor = (estado.dir === 'col') ? 'col-resize' : 'row-resize'; } catch (e) {}
                try { document.getSelection().removeAllRanges(); } catch (e) {}
            }
            var delta = (estado.dir === 'col') ? (ev.clientX - estado.startX) : (ev.clientY - estado.startY);
            var nuevo = Math.max(28, estado.base + delta);
            if (estado.dir === 'col') {
                var idxCol = estado.td.cellIndex;
                var filasCol = estado.tabla.rows;
                for (var i = 0; i < filasCol.length; i++) {
                    var celCol = filasCol[i].cells && filasCol[i].cells[idxCol];
                    if (celCol) celCol.style.width = nuevo + 'px';
                }
                estado.tabla.setAttribute('data-resize', '1');
                posGuia('col', ev.clientX - 2, estado.td.getBoundingClientRect().top, 4, estado.td.getBoundingClientRect().height);
            } else {
                var celdasFila = estado.td.parentNode.cells;
                for (var i2 = 0; i2 < celdasFila.length; i2++) {
                    celdasFila[i2].style.height = nuevo + 'px';
                }
                estado.tabla.setAttribute('data-resize', '1');
                posGuia('row', estado.td.getBoundingClientRect().left, ev.clientY - 2, estado.td.getBoundingClientRect().width, 4);
            }
        }
        function finArrastre() {
            var selFin = (estado && estado.sel && estado.sel.index != null) ? { index: estado.sel.index, length: estado.sel.length } : null;
            estado = null;
            try { document.body.style.cursor = ''; } catch (e) {}
            editorDeTbl.style.cursor = '';
            setCtlCursor(null);
            limpiarMarcaTbl();
            ocultarGuia();
            document.removeEventListener('mousemove', moverArrastre);
            document.removeEventListener('mouseup', finArrastre);
            if (selFin) {
                try { quill.setSelection(selFin.index, selFin.length, 'silent'); } catch (e) {}
            }
        }
        /* ===== Seleccion de bloque de celdas (Excel) ===== */
        var selB = null;
        function _filaIdx(tabla, tr) { return Array.prototype.indexOf.call(tabla.rows, tr); }
        function _celdasRect(tdA, tdB) {
            var tabla = tdA.closest('table');
            if (!tabla) return [];
            var trA = tdA.parentNode, trB = tdB.parentNode;
            var iA = _filaIdx(tabla, trA), iB = _filaIdx(tabla, trB);
            if (iA < 0 || iB < 0) return [];
            var r1 = Math.min(iA, iB), r2 = Math.max(iA, iB);
            var cA = _txColVisual(tdA), cB = _txColVisual(tdB);
            var c1 = Math.min(cA, cB), c2 = Math.max(cA, cB);
            var lista = [];
            for (var r = r1; r <= r2; r++) {
                var tr = tabla.rows[r];
                if (!tr) continue;
                for (var c = c1; c <= c2; c++) {
                    var cel = _txCeldaEnColumna(tr, c);
                    if (cel && lista.indexOf(cel) < 0) lista.push(cel);
                }
            }
            return lista;
        }
        function marcarBloque(tdA, tdB) {
            var lista = _celdasRect(tdA, tdB);
            _pintarCeldasSel(lista);
            _celdasMarcadas = lista;
        }
        function iniciarSelBloque(ev) {
            if (ev.button !== 0) return;
            var td = ev.target && ev.target.closest ? ev.target.closest('td') : null;
            if (!esTabla(td)) return;
            var r = td.getBoundingClientRect(), d = 6;
            if ((ev.clientX - r.left < d) || (r.right - ev.clientX < d) ||
                (ev.clientY - r.top < d) || (r.bottom - ev.clientY < d)) {
                return; /* zona de borde: lo maneja el resize */
            }
            selB = { start: td, current: td, active: false };
            document.addEventListener('mousemove', moverSelBloque);
            document.addEventListener('mouseup', finSelBloque);
        }
        function moverSelBloque(ev) {
            if (!selB) return;
            if (ev.buttons !== 1) { finSelBloque(); return; }
            var td = ev.target && ev.target.closest ? ev.target.closest('td') : null;
            if (!esTabla(td)) return;
            if (selB.start.closest && td.closest('table') !== selB.start.closest('table')) return;
            selB.current = td;
            if (td !== selB.start) {
                if (!selB.active) {
                    selB.active = true;
                    try { document.getSelection().removeAllRanges(); } catch (e) {}
                }
                ev.preventDefault();
                marcarBloque(selB.start, td);
            }
        }
        function finSelBloque() {
            document.removeEventListener('mousemove', moverSelBloque);
            document.removeEventListener('mouseup', finSelBloque);
            if (selB && !selB.active) {
                _celdasMarcadas = [];
                _desmarcarCeldas();
            }
            selB = null;
        }
        editorDeTbl.addEventListener('mouseout', function() { resetCursorTbl(); });
        editorDeTbl.addEventListener('mousemove', aplicarCursorBorde);
        editorDeTbl.addEventListener('mousedown', iniciarArrastre);
        editorDeTbl.addEventListener('mousedown', iniciarSelBloque);
        editorDeTbl.addEventListener('mouseup', function() { finArrastre(); });
        editorDeTbl.addEventListener('mouseleave', function() { ocultarGuia(); resetCursorTbl(); limpiarMarcaTbl(); });
    })();
    /* ===== Borrado controlado en celdas de tabla (Delete/Backspace) ===== */
    function _celdasEnRango(start, end) {
        var resultado = [];
        var raiz = quill ? quill.root : null;
        if (!raiz) return resultado;
        var tables = raiz.querySelectorAll('table');
        for (var t = 0; t < tables.length; t++) {
            var tds = tables[t].querySelectorAll('td');
            for (var i = 0; i < tds.length; i++) {
                try {
                    var blot = quill.scroll.find(tds[i]);
                    if (!blot) continue;
                    var idx = quill.getIndex(blot);
                    var len = blot.length();
                    if (idx < end && start < idx + len) resultado.push({ td: tds[i], idx: idx, len: len });
                } catch (e) {}
            }
        }
        return resultado;
    }
    function _borrarTextoCeldas(start, end) {
        /* Si hay un bloque de celdas marcadas, borrar el contenido de todas ellas */
        if (_celdasMarcadas && _celdasMarcadas.length) {
            var lista = _celdasMarcadas.slice();
            var items = [];
            lista.forEach(function(td) {
                try {
                    var blot = quill.scroll.find(td);
                    if (!blot) return;
                    var idx = quill.getIndex(blot);
                    var len = blot.length();
                    items.push({ td: td, blot: blot, idx: idx, len: len });
                } catch (e) {}
            });
            if (items.length) {
                items.sort(function(a, b) { return b.idx - a.idx; });
                items.forEach(function(c) {
                    var delLen = c.len - 1;
                    if (delLen > 0) {
                        try { quill.deleteText(c.idx, delLen, Quill.sources.USER); } catch (e) {}
                    }
                });
                _celdasMarcadas = [];
                _desmarcarCeldas();
                if (items.length) {
                    var primeroIdx = null;
                    items.forEach(function(c) { if (primeroIdx == null) primeroIdx = c.idx; });
                    try { quill.setSelection(primeroIdx != null ? primeroIdx : 0, 0, Quill.sources.SILENT); } catch (e) {}
                }
                return true;
            }
        }
        var celdas = _celdasEnRango(start, end);
        if (!celdas.length) return false;
        /* De atras hacia adelante para no desalinear los indices */
        celdas.sort(function(a, b) { return b.idx - a.idx; });
        var primeroIdx = null;
        celdas.forEach(function(c) {
            var delStart = Math.max(start, c.idx);
            var delEnd = Math.min(end, c.idx + c.len);
            if (delEnd > delStart) {
                var delLen = delEnd - delStart;
                /* No borrar el newline final de la celda para conservar la estructura */
                if (delStart + delLen >= c.idx + c.len) delLen = c.len - 1;
                if (delLen > 0) {
                    try { quill.deleteText(delStart, delLen, Quill.sources.USER); } catch (e) {}
                }
            }
            if (primeroIdx == null) primeroIdx = delStart;
        });
        try { quill.setSelection(primeroIdx != null ? primeroIdx : start, 0, Quill.sources.SILENT); } catch (e) {}
        return true;
    }
    if (quill && quill.root) {
        quill.root.addEventListener('keydown', function(ev) {
            if (ev.key === 'Tab') {
                try { _txTabEnTabla(ev); } catch (e) {}
                return;
            }
            try {
                if (_celdasMarcadas && _celdasMarcadas.length) {
                    if (_borrarTextoCeldas(0, 1)) {
                        ev.preventDefault();
                        ev.stopPropagation();
                    }
                    return;
                }
                var g = quill.getSelection();
                if (!g || g.length === 0) return;
                if (_borrarTextoCeldas(g.index, g.index + g.length)) {
                    ev.preventDefault();
                    ev.stopPropagation();
                }
            } catch (e) {}
        }, true);
    }
    /* Cerrar pickers al hacer clic fuera */
    document.addEventListener('click', function(ev) {
        if (emojiPickerEl && !emojiPickerEl.contains(ev.target) && !ev.target.closest('.ql-emoji-btn')) {
            emojiPickerEl.remove(); emojiPickerEl = null;
        }
        if (!ev.target.closest('.ql-size-custom') && !ev.target.closest('.size-dropdown-popup')) {
            var sdd = document.querySelector('.size-dropdown-popup');
            if (sdd) {
                var sddBtn = document.querySelector('.ql-size-custom');
                if (sddBtn && !ev.target.closest('.ql-size-custom')) sdd.remove();
                else if (!sddBtn) sdd.remove();
            }
        }
        if (!ev.target.closest('.ql-font-btn') && !ev.target.closest('.font-dropdown-popup')) {
            var fdp = document.querySelector('.font-dropdown-popup');
            if (fdp) fdp.style.display = 'none';
        }
        if (!ev.target.closest('.ql-case-btn') && !ev.target.closest('.case-dropdown-popup')) {
            var cdp = document.querySelector('.case-dropdown-popup');
            if (cdp) cdp.style.display = 'none';
        }
        if (!ev.target.closest('.ql-sym-btn') && !ev.target.closest('.sym-picker-popup')) {
            var syp = document.querySelector('.sym-picker-popup');
            if (syp) syp.remove();
        }
    });
    /* ===== Image resize: overlay de 4 esquinas al marcar la imagen ===== */
    var _rszImg = null;
    var _rszOverlay = null;
    function reEncodeImage(img, w, h) {
        var src = img.getAttribute('src');
        if (!src || !src.startsWith('data:image/')) return;
        var format = src.match(/^data:(image\/\w+)/);
        var mime = format ? format[1] : 'image/jpeg';
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(w);
        canvas.height = Math.round(h);
        var ctx = canvas.getContext('2d');
        var tmp = new Image();
        tmp.onload = function() {
            ctx.drawImage(tmp, 0, 0, canvas.width, canvas.height);
            var quality = mime === 'image/jpeg' || mime === 'image/webp' ? 0.72 : undefined;
            img.src = canvas.toDataURL(mime, quality);
            _rszActualizarLabel(canvas.width, canvas.height);
        };
        tmp.src = src;
    }
    function _rszCrearOverlay() {
        var ov = document.createElement('div');
        ov.className = 'img-rsz-overlay';
        var dirs = ['nw', 'ne', 'sw', 'se'];
        for (var i = 0; i < dirs.length; i++) {
            var h = document.createElement('div');
            h.className = 'img-rsz-handle ' + dirs[i];
            (function(dir) {
                h.addEventListener('mousedown', function(e) { _rszIniciarArrastre(e, dir); });
            })(dirs[i]);
            ov.appendChild(h);
        }
        var lab = document.createElement('div');
        lab.className = 'img-rsz-label';
        ov.appendChild(lab);
        document.body.appendChild(ov);
        return ov;
    }
    function _rszActualizarLabel(w, h) {
        if (!_rszOverlay) return;
        var lab = _rszOverlay.querySelector('.img-rsz-label');
        if (lab) lab.textContent = Math.round(w) + ' x ' + Math.round(h);
    }
    function _rszPosicionar() {
        if (!_rszImg || !_rszOverlay) return;
        var r = _rszImg.getBoundingClientRect();
        _rszOverlay.style.left = r.left + 'px';
        _rszOverlay.style.top = r.top + 'px';
        _rszOverlay.style.width = r.width + 'px';
        _rszOverlay.style.height = r.height + 'px';
        if (!_rszImg.classList.contains('img-rsz-activo')) _rszImg.classList.add('img-rsz-activo');
    }
    function _rszActivar(img) {
        var nueva = (img && img.tagName === 'IMG' && img.closest('.ql-editor')) ? img : null;
        if (!nueva) { _rszDesactivar(); return; }
        if (_rszImg === nueva) { _rszPosicionar(); return; }
        _rszDesactivar();
        _rszImg = nueva;
        if (nueva.draggable !== false) nueva.draggable = false;
        if (!_rszOverlay) _rszOverlay = _rszCrearOverlay();
        _rszOverlay.classList.add('on');
        _rszActualizarLabel(img.naturalWidth || img.offsetWidth, img.naturalHeight || img.offsetHeight);
        _rszPosicionar();
    }
    function _rszDesactivar() {
        if (_rszImg) { _rszImg.classList.remove('img-rsz-activo'); _rszImg = null; }
        if (_rszOverlay) _rszOverlay.classList.remove('on');
    }
    function _rszIniciarArrastre(e, dir) {
        if (!_rszImg) return;
        e.preventDefault(); e.stopPropagation();
        var sx = e.clientX, sy = e.clientY;
        var w0 = _rszImg.offsetWidth, h0 = _rszImg.offsetHeight;
        var ratio = w0 > 0 ? h0 / w0 : 0;
        function onMove(ev) {
            if (!_rszImg) return;
            var dx = ev.clientX - sx, dy = ev.clientY - sy;
            var nw = w0, nh = h0;
            if (dir === 'nw') { nw = w0 - dx; nh = h0 - dy; }
            else if (dir === 'ne') { nw = w0 + dx; nh = h0 - dy; }
            else if (dir === 'sw') { nw = w0 - dx; nh = h0 + dy; }
            else if (dir === 'se') { nw = w0 + dx; nh = h0 + dy; }
            nw = Math.max(16, nw);
            if (!ev.shiftKey && ratio > 0) {
                nh = nw * ratio;
            } else {
                nh = Math.max(16, nh);
            }
            _rszImg.style.width = Math.round(nw) + 'px';
            _rszImg.style.height = Math.round(nh) + 'px';
            _rszPosicionar();
            _rszActualizarLabel(nw, nh);
        }
        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            if (_rszImg) {
                reEncodeImage(_rszImg, _rszImg.offsetWidth, _rszImg.offsetHeight);
                _rszPosicionar();
            }
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }
    function _rszIndiceEn(x, y) {
        if (!quill || !quill.root) return null;
        /* Precisión por caret: índice de caracter exacto bajo el cursor */
        if (document.caretRangeFromPoint) {
            var rng = null;
            try { rng = document.caretRangeFromPoint(x, y); } catch (e) {}
            if (rng && rng.startContainer && quill.root.contains(rng.startContainer)) {
                var nodo = rng.startContainer;
                var blob = null;
                try { blob = quill.scroll.find(nodo, true); } catch (e) {}
                if (blob) {
                    var base = quill.getIndex(blob);
                    var off = nodo.nodeType === 3 ? rng.startOffset : 0;
                    return Math.max(0, base + off);
                }
            }
        }
        /* Fallback por bloques */
        var bloques = quill.root.querySelectorAll('p, li, h1, h2, h3, h4, h5, h6, pre, blockquote, table');
        var mejor = null, mejorD = Infinity;
        bloques.forEach(function(el) {
            var r = el.getBoundingClientRect();
            if (!r.width && !r.height) return;
            var d = Math.abs(y - (r.top + r.height / 2));
            if (d < mejorD) { mejorD = d; mejor = el; }
        });
        if (!mejor) return null;
        var blot = quill.scroll.find(mejor);
        if (!blot) return null;
        var r2 = mejor.getBoundingClientRect();
        return (y > r2.top + r2.height / 2) ? quill.getIndex(blot) + blot.length() : quill.getIndex(blot);
    }
    function _rszStartMove(e, imgRef) {
        var img = (imgRef && imgRef.tagName === 'IMG') ? imgRef : _rszImg;
        if (!img || e.button !== 0) return;
        e.preventDefault(); e.stopPropagation();
        if (img.draggable !== false) img.draggable = false;
        var blot = quill.scroll.find(img);
        if (!blot) return;
        var idxSrc = quill.getIndex(blot);
        var src = img.src;
        var styleAttr = img.getAttribute('style') || '';
        var ancho = img.offsetWidth, alto = img.offsetHeight;
        _rszDesactivar();
        var fantasma = document.createElement('img');
        fantasma.src = src;
        fantasma.style.cssText = styleAttr + ';position:fixed;pointer-events:none;z-index:10050;opacity:.8;box-shadow:0 8px 24px rgba(0,0,0,.5);border-radius:8px;';
        fantasma.style.width = ancho + 'px';
        fantasma.style.height = alto + 'px';
        document.body.appendChild(fantasma);
        function poner(x, y) {
            fantasma.style.left = (x - ancho / 2) + 'px';
            fantasma.style.top = (y - alto / 2) + 'px';
        }
        poner(e.clientX, e.clientY);
        var marcador = document.createElement('div');
        marcador.style.cssText = 'position:fixed;width:3px;height:26px;background:#3b82f6;z-index:10049;pointer-events:none;border-radius:2px;display:none;';
        document.body.appendChild(marcador);
        function onMove(ev) {
            poner(ev.clientX, ev.clientY);
            var idx = _rszIndiceEn(ev.clientX, ev.clientY);
            if (idx !== null) {
                try {
                    var b = quill.getBounds(idx);
                    if (b) {
                        marcador.style.left = (b.left - 1) + 'px';
                        marcador.style.top = b.top + 'px';
                        marcador.style.height = (b.height || 26) + 'px';
                        marcador.style.display = 'block';
                    }
                } catch (e) {}
            } else {
                marcador.style.display = 'none';
            }
        }
        function onUp(ev) {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            if (fantasma && fantasma.parentNode) fantasma.parentNode.removeChild(fantasma);
            if (marcador && marcador.parentNode) marcador.parentNode.removeChild(marcador);
            var idxDest = _rszIndiceEn(ev.clientX, ev.clientY);
            if (idxDest === null || idxDest === idxSrc) { _rszActivar(img); return; }
            if (idxDest > idxSrc) idxDest -= 1;
            quill.deleteText(idxSrc, 1, Quill.sources.USER);
            quill.insertEmbed(idxDest, 'image', src, Quill.sources.USER);
            var imgs = quill.root.querySelectorAll('img');
            var nueva = imgs[imgs.length - 1];
            if (nueva) {
                nueva.setAttribute('style', styleAttr);
                _rszActivar(nueva);
            }
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }
    quill.root.addEventListener('click', function(ev) {
        var img = ev.target && ev.target.closest ? ev.target.closest('img') : null;
        if (img) {
            _rszActivar(img);
            if (quill.getSelection) {
                try {
                    var blot = quill.scroll.find(img);
                    if (blot) quill.setSelection(quill.getIndex(blot), 0, Quill.sources.SILENT);
                } catch (e) {}
            }
        } else {
            _rszDesactivar();
        }
    });
    quill.root.addEventListener('mousedown', function(ev) {
        var img = ev.target && ev.target.closest ? ev.target.closest('img') : null;
        if (!img || ev.button !== 0) return;
        if (ev.target && ev.target.classList && ev.target.classList.contains('img-rsz-handle')) return;
        var sx = ev.clientX, sy = ev.clientY, arrastrando = false;
        function probeMove(mv) {
            if (!arrastrando && (Math.abs(mv.clientX - sx) + Math.abs(mv.clientY - sy) > 4)) {
                arrastrando = true;
                document.removeEventListener('mousemove', probeMove);
                document.removeEventListener('mouseup', probeUp);
                _rszStartMove(mv, img);
            }
        }
        function probeUp() {
            document.removeEventListener('mousemove', probeMove);
            document.removeEventListener('mouseup', probeUp);
        }
        document.addEventListener('mousemove', probeMove);
        document.addEventListener('mouseup', probeUp);
    });
    document.addEventListener('click', function(ev) {
        if (_rszImg && ev.target && ev.target.closest && ev.target.closest('.img-rsz-overlay')) return;
        if (_rszImg && _rszImg.contains && _rszImg.contains(ev.target)) return;
        _rszDesactivar();
    });
    var _rszScrollTick;
    window.addEventListener('scroll', function() {
        if (!_rszImg) return;
        clearTimeout(_rszScrollTick);
        _rszScrollTick = setTimeout(function() { _rszPosicionar(); }, 40);
    }, true);
    window.addEventListener('resize', function() {
        if (_rszImg) _rszPosicionar();
    });
    quill.on('text-change', function() {
        _rszDesactivar();
    });

    /* ===== Copiar/Pegar imagen marcada ===== */
    function _rszCopiarMarcada() {
        if (!_rszImg) return false;
        var html = _rszImg.outerHTML;
        try { navigator.clipboard.write([
            new ClipboardItem({
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([_rszImg.getAttribute('src') || ''], { type: 'text/plain' })
            })
        ]); } catch (e) {}
        return true;
    }
    quill.root.addEventListener('copy', function(ev) {
        if (_rszImg) {
            if (ev.clipboardData) {
                ev.preventDefault();
                ev.clipboardData.setData('text/html', _rszImg.outerHTML);
                var src2 = _rszImg.getAttribute('src');
                if (src2) ev.clipboardData.setData('text/plain', src2);
            } else {
                _rszCopiarMarcada();
            }
        }
    });
    quill.root.addEventListener('paste', function(ev) {
        var cd = ev.clipboardData || window.clipboardData;
        if (!cd) return;
        var items = cd.items || [];
        var blob = null;
        for (var i = 0; i < items.length; i++) {
            if (items[i].kind === 'file' && items[i].type && items[i].type.indexOf('image/') === 0) {
                blob = items[i].getAsFile();
                break;
            }
        }
        if (!blob && cd.files && cd.files.length) {
            for (var f2 = 0; f2 < cd.files.length; f2++) {
                if (cd.files[f2].type.indexOf('image/') === 0) { blob = cd.files[f2]; break; }
            }
        }
        if (!blob) return;
        ev.preventDefault();
        var reader = new FileReader();
        reader.onload = function() {
            var range = quill.getSelection(true);
            var idx = range ? range.index : quill.getLength();
            quill.insertEmbed(idx, 'image', reader.result);
            try { quill.setSelection(idx + 1, 0, Quill.sources.USER); } catch (e) {}
        };
        reader.readAsDataURL(blob);
    });

    /* ===== Log de proceso ===== */
    const logEl = document.getElementById('logBody');
    const logContador = document.getElementById('logContador');
    const logContenedor = document.getElementById('logContenedor');
    const logFab = document.getElementById('logFab');
    const logFabBadge = document.getElementById('logFabBadge');
    const logFabInicio = document.getElementById('logFabInicio');
    const logFabFinal = document.getElementById('logFabFinal');
    const logHeader = document.getElementById('logHeader');
    const logClearBtn = document.getElementById('logClearBtn');
    const logCopyBtn = document.getElementById('logCopyBtn');
    const logExportBtn = document.getElementById('logExportBtn');
    let logCount = 0;
    function logCuerpoVisible() {
        return logEl && logEl.style.display !== 'none';
    }
    function logContenedorVisible() {
        return logContenedor &&
            !logContenedor.classList.contains('d-none') &&
            logContenedor.style.display !== 'none';
    }
    function _syncLogUi() {
        const cardVisible = logContenedorVisible();
        const iconos = [logFab, document.getElementById('btnToggleLog')];
        iconos.forEach(function(c) {
            const ico = c ? c.querySelector('i') : null;
            if (ico) ico.className = 'fas ' + (cardVisible ? 'fa-eye' : 'fa-terminal');
        });
        const chev = logHeader ? logHeader.querySelector('#logChevron') : null;
        if (chev) chev.style.transform = logCuerpoVisible() ? 'rotate(180deg)' : '';
    }
    function _mostrarCardColapsado() {
        if (!logContenedor || !logEl) return;
        logContenedor.classList.remove('d-none');
        logContenedor.style.display = '';
        logEl.style.display = 'none';
        _syncLogUi();
    }
    function toggleLog() {
        /* FAB y botón >_ : solo alternan oculto ↔ card colapsado (header visible) */
        if (logContenedorVisible()) {
            logEl.style.display = 'none';
            logContenedor.classList.add('d-none');
            logContenedor.style.display = 'none';
        } else {
            _mostrarCardColapsado();
        }
        _syncLogUi();
    }
    function toggleCard() {
        /* Botón del card (header/chevron): colapsa o expande el cuerpo */
        if (!logContenedor || !logEl) return;
        if (!logContenedorVisible()) {
            _mostrarCardColapsado();
            return;
        }
        const expandir = !logCuerpoVisible();
        logEl.style.display = expandir ? '' : 'none';
        if (expandir) {
            setTimeout(function() { logEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 80);
        }
        _syncLogUi();
    }
    function logEvent(tipo, msg) {
        if (!logEl) return;
        logCount++;
        if (logContador) logContador.textContent = logCount;
        if (logContenedor) {
            logContenedor.classList.remove('d-none');
            logContenedor.style.display = '';
        }
        if (logEl) logEl.style.display = '';
        if (logFabBadge) { logFabBadge.textContent = logCount; logFabBadge.classList.add('visible'); }
        const ahora = new Date();
        const hh = String(ahora.getHours()).padStart(2,'0');
        const mm = String(ahora.getMinutes()).padStart(2,'0');
        const ss = String(ahora.getSeconds()).padStart(2,'0');
        const ms = String(ahora.getMilliseconds()).padStart(3,'0');
        const hora = hh+':'+mm+':'+ss+'.'+ms;
        const iconos = {
            info: 'fa-circle-info',
            ok: 'fa-circle-check',
            warn: 'fa-triangle-exclamation',
            error: 'fa-circle-xmark',
            send: 'fa-paper-plane'
        };
        const entry = document.createElement('div');
        entry.className = 'log-entrada log-' + tipo;
        entry.innerHTML =
            '<span class="log-hora">' + hora + '</span>' +
            '<span class="log-icono"><i class="fas ' + (iconos[tipo] || 'fa-circle-info') + '"></i></span>' +
            '<span class="log-msg">' + escHtml(msg) + '</span>';
        logEl.appendChild(entry);
        logEl.scrollTop = logEl.scrollHeight;
        _syncLogUi();
    }
    /* Toggle del log con el botón flotante, header y botón del form */
    _syncLogUi();
    /* Al abrir la página el card de logs queda colapsado y oculto */
    if (logContenedor) { logContenedor.classList.add('d-none'); logContenedor.style.display = 'none'; }
    if (logEl) logEl.style.display = 'none';
    if (logFab) logFab.addEventListener('click', toggleLog);
    function irInicioLog() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function irFinalLog() {
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
    if (logFabInicio) logFabInicio.addEventListener('click', function(ev) { ev.stopPropagation(); irInicioLog(); });
    if (logFabFinal) logFabFinal.addEventListener('click', function(ev) { ev.stopPropagation(); irFinalLog(); });
    if (logHeader) logHeader.addEventListener('click', function(ev) {
        if (ev.target.closest('.log-clear-btn')) return;
        toggleCard();
    });
    const btnToggleLog = document.getElementById('btnToggleLog');
    if (btnToggleLog) btnToggleLog.addEventListener('click', toggleLog);

    /* ===== Guardar como .eml ===== */
    var btnSaveEml = document.getElementById('btnSaveEml');
    if (btnSaveEml) btnSaveEml.addEventListener('click', function() {
        var nombre = campoNombre ? campoNombre.value.trim() : '';
        var correo = campoCorreo ? campoCorreo.value.trim() : '';
        var asunto = document.getElementById('asunto') ? document.getElementById('asunto').value.trim() : '(sin asunto)';
        var prioridad = document.getElementById('prioridad') ? document.getElementById('prioridad').value : 'normal';
        var prioMap = {'baja':'Baja','normal':'Normal','alta':'Alta','urgente':'Urgente'};
        var prioHdr = prioMap[prioridad] || 'Normal';
        var ahora = new Date();
        var dateHdr = ahora.toUTCString();
        var bodyHtml = quill.root.innerHTML;
        var boundary = '----=_Part_' + Math.random().toString(36).substr(2, 12);
        var archivos = Array.isArray(acumulados) ? acumulados : [];
        var esPlano = document.getElementById('chkTextoPlano') && document.getElementById('chkTextoPlano').checked;
        function construirEml(adjuntosBase64) {
            var eml = 'MIME-Version: 1.0\r\n'
                + 'Date: ' + dateHdr + '\r\n'
                + 'From: ' + (nombre ? nombre + ' <' + correo + '>' : correo) + '\r\n'
                + 'To: ' + (typeof CORREO_DESTINATARIO !== 'undefined' ? CORREO_DESTINATARIO : 'admin@localhost') + '\r\n'
                + 'Subject: =?UTF-8?B?' + btoa(unescape(encodeURIComponent(asunto))) + '?=\r\n'
                + 'X-Priority: ' + (prioHdr === 'Alta' || prioHdr === 'Urgente' ? '1' : (prioHdr === 'Baja' ? '5' : '3')) + '\r\n';
            if (adjuntosBase64.length) {
                eml += 'Content-Type: multipart/mixed; boundary="' + boundary + '"\r\n'
                    + 'Content-Transfer-Encoding: 7bit\r\n'
                    + '\r\n'
                    + '--' + boundary + '\r\n';
            }
            if (esPlano) {
                eml += 'Content-Type: text/plain; charset="UTF-8"\r\n'
                    + 'Content-Transfer-Encoding: quoted-printable\r\n'
                    + '\r\n'
                    + quill.getText() + '\r\n';
            } else {
                if (adjuntosBase64.length) {
                    eml += 'Content-Type: multipart/alternative; boundary="' + boundary + '-alt"\r\n'
                        + '\r\n'
                        + '--' + boundary + '-alt\r\n';
                } else {
                    eml += 'Content-Type: multipart/alternative; boundary="' + boundary + '-alt"\r\n'
                        + 'Content-Transfer-Encoding: 7bit\r\n'
                        + '\r\n'
                        + '--' + boundary + '-alt\r\n';
                }
                eml += 'Content-Type: text/plain; charset="UTF-8"\r\n'
                    + 'Content-Transfer-Encoding: quoted-printable\r\n'
                    + '\r\n'
                    + quill.getText() + '\r\n'
                    + '\r\n'
                    + '--' + boundary + '-alt\r\n'
                    + 'Content-Type: text/html; charset="UTF-8"\r\n'
                    + 'Content-Transfer-Encoding: quoted-printable\r\n'
                    + '\r\n'
                    + '<html><head><meta charset="utf-8"></head><body>' + bodyHtml + '</body></html>\r\n'
                    + '\r\n'
                    + '--' + boundary + '-alt--\r\n';
            }
            adjuntosBase64.forEach(function(att) {
                eml += '\r\n--' + boundary + '\r\n'
                    + 'Content-Type: ' + (att.type || 'application/octet-stream') + '; name="' + att.name + '"\r\n'
                    + 'Content-Transfer-Encoding: base64\r\n'
                    + 'Content-Disposition: attachment; filename="' + att.name + '"\r\n'
                    + '\r\n'
                    + att.b64.match(/.{1,76}/g).join('\r\n') + '\r\n';
            });
            if (adjuntosBase64.length) eml += '--' + boundary + '--\r\n';
            return eml;
        }
        function descargar(emlContent, nombreArchivo) {
            var blob = new Blob([emlContent], { type: 'message/rfc822' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = nombreArchivo;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            logEvent('ok', 'Archivo .eml guardado: ' + nombreArchivo);
        }
        var nombreArchivo = (asunto.replace(/[^a-zA-Z0-9_\- ]/g, '').trim() || 'mensaje') + '.eml';
        if (!archivos.length) {
            descargar(construirEml([]), nombreArchivo);
            return;
        }
        var lectores = archivos.map(function(f) {
            return new Promise(function(resolve) {
                var reader = new FileReader();
                reader.onload = function() {
                    resolve({ name: f.name, type: f.type, b64: reader.result.split(',')[1] || '' });
                };
                reader.readAsDataURL(f);
            });
        });
        Promise.all(lectores).then(function(adjuntos) {
            descargar(construirEml(adjuntos), nombreArchivo);
        });
    });
    /* Borrar todos los logs */
    if (logClearBtn) logClearBtn.addEventListener('click', function(ev) {
        ev.stopPropagation();
        if (logEl) logEl.innerHTML = '';
        logCount = 0;
        if (logContador) logContador.textContent = '0';
        if (logFabBadge) { logFabBadge.textContent = '0'; logFabBadge.classList.remove('visible'); }
    });

    /* Texto plano de los logs para copiar / exportar */
    function obtenerTextoLogs() {
        if (!logEl) return '';
        var partes = [];
        Array.prototype.forEach.call(logEl.querySelectorAll('.log-entrada'), function(en) {
            var hora = en.querySelector('.log-hora');
            var msg = en.querySelector('.log-msg');
            var linea = hora ? '[' + hora.textContent.trim() + '] ' : '';
            linea += (msg ? msg.textContent.trim() : '');
            if (linea) partes.push(linea);
        });
        return partes.join('\n');
    }
    /* Copiar logs al portapapeles */
    if (logCopyBtn) logCopyBtn.addEventListener('click', function(ev) {
        ev.stopPropagation();
        var texto = obtenerTextoLogs();
        if (!texto) { aviso('info', 'Log vacío', 'No hay entradas para copiar.'); return; }
        function copiaFallback() {
            var ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            return ok;
        }
        function ok() { logEvent('ok', 'Log copiado al portapapeles (' + texto.split('\n').length + ' líneas)'); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(ok, function() {
                if (copiaFallback()) ok(); else aviso('error', 'No se pudo copiar', 'Tu navegador bloqueó el acceso al portapapeles.');
            });
        } else if (copiaFallback()) {
            ok();
        } else {
            aviso('error', 'No se pudo copiar', 'Tu navegador bloqueó el acceso al portapapeles.');
        }
    });
    /* Exportar logs a .txt */
    if (logExportBtn) logExportBtn.addEventListener('click', function(ev) {
        ev.stopPropagation();
        var texto = obtenerTextoLogs();
        if (!texto) { aviso('info', 'Log vacío', 'No hay entradas para exportar.'); return; }
        var ahora = new Date();
        function cd(n) { return String(n).padStart(2, '0'); }
        var nombre = 'log_proceso_' + ahora.getFullYear() + cd(ahora.getMonth() + 1) + cd(ahora.getDate()) + '_' +
                     cd(ahora.getHours()) + cd(ahora.getMinutes()) + cd(ahora.getSeconds()) + '.txt';
        var blob = new Blob([texto], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        logEvent('ok', 'Log exportado: ' + nombre);
    });

    function aviso(icono, titulo, texto, campo) {
        const conf = {
            error:   { texto: 'Corregir',    ico: 'fa-pen-to-square' },
            warning: { texto: 'Entendido',   ico: 'fa-check' },
            success: { texto: 'Aceptar',     ico: 'fa-check' },
            info:    { texto: 'Aceptar',     ico: 'fa-circle-info' }
        }[icono] || { texto: 'Aceptar', ico: 'fa-check' };
        swalWin({ icono: icono, titulo: titulo, texto: texto, temporizador: 6000,
                   confirmarTexto: conf.texto, confirmarIcono: conf.ico }).then(() => { if (campo) campo.focus(); });
    }

    /* ===== Zona de adjuntos: clic / arrastrar + lista de archivos ===== */
    const EXT_OK = ['png','jpg','jpeg','gif','pdf','txt','xls','xlsx','doc','docx','ppt','pptx','csv','zip','rar'];
    const IMG_EXT = ['png','jpg','jpeg','gif'];
    const ICONO_EXT = {
        png:['fa-file-image','#34d399'], jpg:['fa-file-image','#34d399'], jpeg:['fa-file-image','#34d399'],
        gif:['fa-file-image','#34d399'],
        pdf:['fa-file-pdf','#f87171'],   txt:['fa-file-lines','#94a3b8'],
        csv:['fa-file-csv','#4ade80'],   xls:['fa-file-excel','#22c55e'], xlsx:['fa-file-excel','#22c55e'],
        doc:['fa-file-word','#60a5fa'],  docx:['fa-file-word','#60a5fa'],
        ppt:['fa-file-powerpoint','#fb923c'], pptx:['fa-file-powerpoint','#fb923c'],
        zip:['fa-file-zipper','#eab308'],rar:['fa-file-zipper','#eab308']
    };
    function formatoBytes(b) {
        if (b >= 1048576) return (b / 1048576).toFixed(2) + ' MB';
        if (b >= 1024) return (b / 1024).toFixed(1) + ' KB';
        return b + ' B';
    }
    function extDe(nombre) { return (String(nombre).split('.').pop() || '').toLowerCase(); }

    /* Acumulador de archivos: la seleccion nativa reemplaza, aqui se suman */
    let acumulados = [];

    function sincronizarInput() {
        if (!campoAdjuntos) return;
        try {
            const dt = new DataTransfer();
            acumulados.forEach(function(f) { dt.items.add(f); });
            campoAdjuntos.files = dt.files;
        } catch (err) { /* navegador sin soporte */ }
    }

    function agregarArchivos(nuevos) {
        if (!campoAdjuntos) return;
        const MAX_CANT = 100;
        let duplicados = [], limite = false;
        Array.from(nuevos || []).forEach(function(f) {
            if (acumulados.length >= MAX_CANT) { limite = true; return; }
            const yaEsta = acumulados.some(function(x) { return x.name === f.name && x.size === f.size; });
            if (yaEsta) { duplicados.push(f.name); return; }
            acumulados.push(f);
        });
        sincronizarInput();
        refrescarLista();
        if (duplicados.length) {
            aviso('warning', 'Archivo duplicado',
                  (duplicados.length > 1 ? 'Los archivos "' : 'El archivo "') +
                  duplicados.join('", "') +
                  (duplicados.length > 1 ? '" ya est\u00e1n adjuntados.' : '" ya est\u00e1 adjuntado.'),
                  campoAdjuntos);
        } else if (limite) {
            aviso('warning', 'Limite alcanzado', 'Se permiten un m\u00e1ximo de ' + MAX_CANT + ' archivos adjuntos.', campoAdjuntos);
        }
    }

    function quitarArchivo(idx) {
        acumulados.splice(idx, 1);
        sincronizarInput();
        refrescarLista();
    }

    function refrescarLista() {
        if (!listaAdjuntos) return;
        const resumen = document.getElementById('adjResumen');
        const contador = document.getElementById('adjContador');
        const panel = document.getElementById('panelAdjuntos');
        const archivos = acumulados.slice();
        let total = 0;
        archivos.forEach(function(f) { total += f.size; });
        if (contador) contador.textContent = archivos.length;
        const contadorAdjuntar = document.getElementById('adjuntarContador');
        if (contadorAdjuntar) contadorAdjuntar.textContent = archivos.length ? archivos.length : '';

        /* Sin adjuntos: ocultar el panel; la ventana principal se expande sola */
        if (!archivos.length) {
            if (panel) { panel.style.display = 'none'; }
            listaAdjuntos.innerHTML = '';
            if (resumen) { resumen.innerHTML = ''; }
            return;
        }
        if (panel) { panel.style.display = 'flex'; }

        /* Tarjetas de archivos (miniatura real para imágenes) */
        let html = '';
        archivos.forEach(function(f, i) {
            const extOk = EXT_OK.includes(extDe(f.name)) && f.size <= 10 * 1024 * 1024;
            const icono = extOk
                ? (ICONO_EXT[extDe(f.name)] || ['fa-file-question', '#94a3b8'])
                : ['fa-ban', '#ef4444'];
            const esImg = extOk && IMG_EXT.includes(extDe(f.name));
            const thumbHtml = esImg
                ? '<div class="adj-thumb">' +
                  '<img src="' + URL.createObjectURL(f) + '" alt="" loading="eager" onload="this.style.opacity=1" style="opacity:0;transition:opacity .2s;width:100%;height:100%;object-fit:cover;border-radius:6px;">' +
                  '</div>'
                : '<div class="adj-icono"><i class="fas ' + icono[0] + '" style="color:' + icono[1] + '"></i></div>';
            html +=
                '<div class="adj-item" data-idx="' + i + '" data-ext="' + escHtml(extDe(f.name)) + '" data-size="' + f.size + '" data-name="' + escHtml(f.name) + '">' +
                thumbHtml +
                '<div class="adj-info">' +
                '<div class="adj-nombre" style="' + (extOk ? '' : 'color:#f87171') + '">' + escHtml(f.name) + '</div>' +
                '<div class="adj-tamano">' + formatoBytes(f.size) +
                (extOk
                    ? ' <span class="ok"><i class="fas fa-circle-check"></i></span>'
                    : ' <span style="color:#f87171">&middot; no permitido</span>') +
                '</div>' +
                '</div>' +
                '<button type="button" title="Quitar" onclick="quitarArchivoContacto(' + i + ')"><i class="fas fa-xmark"></i></button>' +
                '</div>';
        });
        listaAdjuntos.innerHTML = html;

        /* Pie del panel: totales + barrita */
        const pctTotal = Math.min(100, (total / (18 * 1024 * 1024)) * 100);
        const colorBarra = pctTotal > 80 ? '#f87171' : (pctTotal > 50 ? '#fbbf24' : '#14b8a6');
        let pie =
            '<div class="pa-linea"><i class="fas fa-files"></i> Total: <b style="color:#e2e8f0">' + archivos.length + '</b> archivo(s)</div>' +
            '<div class="pa-linea"><i class="fas fa-weight-hanging"></i> Peso: <b style="color:#e2e8f0">' + formatoBytes(total) + '</b>&nbsp;/ 18 MB</div>' +
            '<div style="height:6px;background:#1e293b;border-radius:4px;overflow:hidden;margin-bottom:7px;">' +
            '<span style="display:block;height:100%;width:' + pctTotal.toFixed(1) + '%;background:' + colorBarra + ';border-radius:4px;transition:width .25s ease;"></span>' +
            '</div>';
        if (archivos.length > 2) {
            pie += '<div style="display:flex;justify-content:flex-end;">' +
                   '<button type="button" class="btn-quitar-todos" onclick="quitarTodosContacto()">' +
                   '<i class="fas fa-trash-can" style="margin-right:5px"></i>Quitar todos</button></div>';
        }
        if (resumen) resumen.innerHTML = pie;
    }
    window.quitarArchivoContacto = quitarArchivo;
    window.quitarTodosContacto = function () {
        acumulados = [];
        if (!campoAdjuntos) return;
        campoAdjuntos.value = '';
        refrescarLista();
    };

    if (campoAdjuntos) {
        campoAdjuntos.addEventListener('change', function() {
            agregarArchivos(campoAdjuntos.files);
        });
    }
    /* Boton clip: abrir el selector de archivos */
    var btnAdjuntar = document.getElementById('btnAdjuntar');
    if (btnAdjuntar && campoAdjuntos) {
        btnAdjuntar.addEventListener('click', function() {
            campoAdjuntos.click();
        });
    }
    var btnAdjuntarBarra = document.getElementById('btnAdjuntarBarra');
    if (btnAdjuntarBarra && campoAdjuntos) {
        btnAdjuntarBarra.addEventListener('click', function() {
            campoAdjuntos.click();
        });
    }
    /* Drag & drop sobre el editor/formulario */
    var editorWrap = document.getElementById('editor-mensaje');
    if (editorWrap) {
        editorWrap.addEventListener('dragover', function(ev) { ev.preventDefault(); ev.stopPropagation(); editorWrap.style.outline = '2px solid #14b8a6'; editorWrap.style.outlineOffset = '-2px'; });
        editorWrap.addEventListener('dragleave', function() { editorWrap.style.outline = 'none'; });
        editorWrap.addEventListener('drop', function(ev) {
            ev.preventDefault(); ev.stopPropagation();
            editorWrap.style.outline = 'none';
            if (ev.dataTransfer && ev.dataTransfer.files.length) agregarArchivos(ev.dataTransfer.files);
        });
    }
    refrescarLista();

    /* ===== Menu contextual de adjuntos (click derecho) ===== */
    var ctxMenu = document.getElementById('ctxMenuAdjuntos');
    if (!ctxMenu) {
        ctxMenu = document.createElement('div');
        ctxMenu.id = 'ctxMenuAdjuntos';
        ctxMenu.className = 'ctx-menu';
        ctxMenu.innerHTML =
            '<div class="ctx-hd" id="ctxAdjNombre">archivo</div>' +
            '<div class="ctx-item" data-acc="abrir"><i class="fas fa-eye"></i> Previsualizar</div>' +
            '<div class="ctx-item" data-acc="descargar"><i class="fas fa-download"></i> Descargar</div>' +
            '<div class="ctx-sep"></div>' +
            '<div class="ctx-item" data-acc="copiar"><i class="fas fa-copy"></i> Copiar nombre</div>' +
            '<div class="ctx-item" data-acc="renombrar"><i class="fas fa-pen"></i> Renombrar</div>' +
            '<div class="ctx-item" data-acc="detalles"><i class="fas fa-circle-info"></i> Ver detalles</div>' +
            '<div class="ctx-sep"></div>' +
            '<div class="ctx-item ctx-danger" data-acc="quitar"><i class="fas fa-xmark"></i> Quitar archivo</div>' +
            '<div class="ctx-item ctx-danger" data-acc="quitarTodos"><i class="fas fa-trash-can"></i> Quitar todos</div>';
        document.body.appendChild(ctxMenu);
    }
    var ctxIdx = -1;
    function cerrarCtxMenu() { if (ctxMenu) ctxMenu.style.display = 'none'; ctxIdx = -1; }
    function abrirCtxMenu(idx, x, y) {
        ctxIdx = idx;
        var f = acumulados[idx];
        if (!f || !ctxMenu) return;
        var enc = ctxMenu.querySelector('#ctxAdjNombre');
        if (enc) enc.textContent = f.name;
        ctxMenu.style.display = 'block';
        ctxMenu.style.left = '0px';
        ctxMenu.style.top = '0px';
        var r = ctxMenu.getBoundingClientRect();
        var mw = r.width, mh = r.height;
        var nx = Math.min(x, window.innerWidth - mw - 8);
        var ny = Math.min(y, window.innerHeight - mh - 8);
        if (nx < 4) nx = 4;
        if (ny < 4) ny = 4;
        ctxMenu.style.left = nx + 'px';
        ctxMenu.style.top = ny + 'px';
    }
    function descargarAdjunto(idx) {
        var f = acumulados[idx];
        if (!f) return;
        var url = URL.createObjectURL(f);
        var a = document.createElement('a');
        a.href = url; a.download = f.name;
        document.body.appendChild(a); a.click();
        document.body.removeChild(a);
        setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
        logEvent('ok', 'Descargado adjunto: ' + f.name);
    }
    function previsualizarAdjunto(idx) {
        var f = acumulados[idx];
        if (!f) return;
        var esImg = f.type && f.type.indexOf('image/') === 0;
        if (esImg) {
            var url = URL.createObjectURL(f);
            swalWin({
                icono: 'info', titulo: f.name,
                html: '<img src="' + url + '" style="max-width:100%;max-height:58vh;border-radius:8px;display:block;margin:0 auto;box-shadow:0 4px 18px rgba(0,0,0,.5);">' +
                      '<div style="margin-top:10px;font-size:13px;color:#94a3b8;">' + formatoBytes(f.size) + '</div>',
                confirmarTexto: 'Cerrar', confirmarIcono: 'fa-xmark'
            }).then(function() { URL.revokeObjectURL(url); });
        } else {
            descargarAdjunto(idx);
        }
    }
    function copiarNombreAdjunto(idx) {
        var f = acumulados[idx];
        if (!f) return;
        var corto = f.name.length > 48 ? f.name.slice(0, 48) + '...' : f.name;
        function copiarFallback() {
            var ta = document.createElement('textarea');
            ta.value = f.name; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(ta);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(f.name)
                .then(function() { aviso('success', 'Copiado', 'Nombre copiado al portapapeles: ' + corto, null); })
                .catch(function() { copiarFallback(); aviso('success', 'Copiado', 'Nombre copiado al portapapeles: ' + corto, null); });
        } else {
            copiarFallback();
            aviso('success', 'Copiado', 'Nombre copiado al portapapeles: ' + corto, null);
        }
    }
    function renombrarAdjunto(idx) {
        var f = acumulados[idx];
        if (!f) return;
        var ext = extDe(f.name);
        var base = (ext ? f.name.slice(0, f.name.length - ext.length - 1) : f.name);
        var inputId = 'renombrarInput' + Date.now();
        Swal.fire({
            title: undefined,
            html:
                '<div style="text-align:left;">' +
                '<div style="font-size:15px;font-weight:700;color:#e2e8f0;margin-bottom:12px;">Renombrar archivo</div>' +
                '<label style="font-size:12.5px;color:#94a3b8;">Nuevo nombre</label>' +
                '<input id="' + inputId + '" type="text" value="' + escHtml(base) + '" ' +
                'style="width:100%;margin-top:6px;padding:9px 11px;border-radius:8px;border:1px solid #334155;background:#1e293b;color:#e2e8f0;font-size:14px;outline:none;">' +
                (ext ? '<div style="margin-top:8px;font-size:11.5px;color:#64748b;">La extensi\u00f3n .' + escHtml(ext) + ' se mantendr\u00e1 autom\u00e1ticamente.</div>' : '') +
                '</div>',
            background: '#0f172a',
            color: '#e2e8f0',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check" style="margin-right:8px"></i>Renombrar',
            cancelButtonText: '<i class="fas fa-xmark" style="margin-right:8px"></i>Cancelar',
            confirmButtonColor: '#d97706',
            cancelButtonColor: '#33333f',
            reverseButtons: true,
            customClass: { popup: 'swal-dark-popup', confirmButton: 'swal-dark-confirm' },
            didOpen: function() {
                var inp = document.getElementById(inputId);
                if (inp) { inp.focus(); inp.select(); }
            },
            preConfirm: function() {
                var inp = document.getElementById(inputId);
                var val = inp ? inp.value.trim() : '';
                if (!val) {
                    Swal.showValidationMessage('<i class="fas fa-triangle-exclamation"></i> Escribe un nombre.');
                    return false;
                }
                if (/[\/\\:*?"<>|]/.test(val)) {
                    Swal.showValidationMessage('<i class="fas fa-triangle-exclamation"></i> El nombre contiene caracteres no v\u00e1lidos ( / \\ : * ? " < > | ).');
                    return false;
                }
                return val;
            }
        }).then(function(resultado) {
            if (!resultado.isConfirmed || !resultado.value) return;
            var nuevoNombre = resultado.value + (ext ? '.' + ext : '');
            var blob = f.slice(0, f.size, f.type);
            var nuevoFile;
            try { nuevoFile = new File([blob], nuevoNombre, { type: f.type || 'application/octet-stream', lastModified: f.lastModified }); }
            catch (e) { nuevoFile = blob; }
            acumulados[idx] = nuevoFile;
            sincronizarInput();
            refrescarLista();
            logEvent('ok', 'Adjunto renombrado: ' + f.name + ' -> ' + nuevoNombre);
            aviso('success', 'Renombrado', 'Adjunto renombrado a ' + nuevoNombre, null);
        });
    }
    function mostrarDetallesAdjunto(idx) {
        var f = acumulados[idx];
        if (!f) return;
        var ext = extDe(f.name) || 'desconocida';
        var iconoExt = ICONO_EXT[ext] || ['fa-file-question', '#94a3b8'];
        var permitido = EXT_OK.includes(ext) && f.size <= 10 * 1024 * 1024;
        var tipoF = f.type || 'Sin especificar';
        if (tipoF === '') tipoF = 'Sin especificar';
        swalWin({
            icono: 'info', titulo: 'Detalles del archivo',
            html:
                '<div style="text-align:left;font-size:13.5px;line-height:1.9;">' +
                '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">' +
                '<i class="fas ' + iconoExt[0] + '" style="font-size:34px;color:' + iconoExt[1] + ';"></i>' +
                '<div style="font-weight:700;color:#e2e8f0;word-break:break-all;">' + escHtml(f.name) + '</div></div>' +
                '<table style="width:100%;border-collapse:collapse;font-size:13px;">' +
                '<tr><td style="padding:5px 8px;color:#94a3b8;width:120px;">Tama\u00f1o</td><td style="padding:5px 8px;color:#e2e8f0;">' + formatoBytes(f.size) + ' (' + f.size + ' bytes)</td></tr>' +
                '<tr><td style="padding:5px 8px;color:#94a3b8;">Extensi\u00f3n</td><td style="padding:5px 8px;color:#e2e8f0;">.' + escHtml(ext) + '</td></tr>' +
                '<tr><td style="padding:5px 8px;color:#94a3b8;">Tipo MIME</td><td style="padding:5px 8px;color:#e2e8f0;">' + escHtml(tipoF) + '</td></tr>' +
                '<tr><td style="padding:5px 8px;color:#94a3b8;">Estado</td><td style="padding:5px 8px;color:' + (permitido ? '#34d399' : '#f87171') + ';">' + (permitido ? '<i class="fas fa-circle-check"></i> V\u00e1lido / permitido' : '<i class="fas fa-ban"></i> ' + (EXT_OK.includes(ext) ? 'Excede 10 MB por archivo' : 'Extensi\u00f3n no permitida')) + '</td></tr>' +
                '</table></div>',
            confirmarTexto: 'Cerrar', confirmarIcono: 'fa-xmark'
        });
    }
    if (listaAdjuntos) {
        listaAdjuntos.addEventListener('contextmenu', function(ev) {
            var item = ev.target.closest('.adj-item');
            if (!item) return;
            ev.preventDefault();
            var idx = parseInt(item.getAttribute('data-idx'), 10);
            if (!isNaN(idx)) abrirCtxMenu(idx, ev.clientX, ev.clientY);
        });
    }
    if (ctxMenu) {
        ctxMenu.addEventListener('click', function(ev) {
            var it = ev.target.closest('.ctx-item');
            if (!it) return;
            var acc = it.getAttribute('data-acc');
            var f = acumulados[ctxIdx];
            if (acc === 'quitar' && f) {
                quitarArchivo(ctxIdx);
            } else if (acc === 'quitarTodos') {
                window.quitarTodosContacto();
            } else if (acc === 'descargar' && f) {
                descargarAdjunto(ctxIdx);
            } else if (acc === 'abrir' && f) {
                previsualizarAdjunto(ctxIdx);
            } else if (acc === 'copiar' && f) {
                copiarNombreAdjunto(ctxIdx);
            } else if (acc === 'renombrar' && f) {
                renombrarAdjunto(ctxIdx);
            } else if (acc === 'detalles' && f) {
                mostrarDetallesAdjunto(ctxIdx);
            }
            cerrarCtxMenu();
        });
    }
    document.addEventListener('click', function(ev) {
        if (ctxMenu && !ctxMenu.contains(ev.target)) cerrarCtxMenu();
    });
    document.addEventListener('scroll', cerrarCtxMenu, true);
    window.addEventListener('resize', cerrarCtxMenu);
    document.addEventListener('contextmenu', function(ev) {
        if (ctxMenu && !ev.target.closest('.adj-item')) cerrarCtxMenu();
    });
    document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') cerrarCtxMenu(); });

    /* ===== Modal de progreso de envío / carga de adjuntos ===== */
    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function crearModalProgreso(archivos, totalBytes) {
        const overlay = document.createElement('div');
        overlay.id = 'modalProgreso';
        /* Sin backdrop: la página queda visible y clicable detrás */
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;pointer-events:none;';
        const infoAdj = archivos.length
            ? '<div style="margin-top:8px;font-size:12.5px;color:#94a3b8;">' +
              '<i class="fas fa-paperclip" style="color:#5eead4;margin-right:6px"></i>' +
              archivos.length + ' archivo(s) &middot; ' + formatoBytes(totalBytes) + '</div>' +
              '<div id="progArchivo" style="margin-top:4px;font-size:12.5px;color:#cbd5e1;min-height:17px;font-weight:600;"></div>'
            : '';
        overlay.innerHTML =
            '<div style="background:#0f172a;border:1px solid #334155;border-radius:14px;box-shadow:0 14px 44px rgba(0,0,0,.65);width:min(92vw,420px);padding:26px 28px;text-align:center;pointer-events:auto;">' +
            '<div id="progIconoWrap"><i id="progIcono" class="fas fa-cloud-arrow-up" style="font-size:34px;color:#5eead4;"></i></div>' +
            '<div id="progTitulo" style="margin-top:10px;font-weight:700;color:#e2e8f0;font-size:15.5px;">Enviando mensaje...</div>' +
            infoAdj +
            '<div style="margin-top:16px;height:12px;background:#1e293b;border-radius:7px;overflow:hidden;box-shadow:inset 0 1px 3px rgba(0,0,0,.4);">' +
            '<div id="progBarra" style="height:100%;width:0%;background:linear-gradient(90deg,#2dd4bf,#14b8a6);border-radius:7px;box-shadow:0 0 10px rgba(45,212,191,.6);"></div>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;margin-top:6px;">' +
            '<span id="progSub" style="font-size:11.5px;color:#64748b;">Preparando datos...</span>' +
            '<span id="progPct" style="font-size:11.5px;font-weight:700;color:#5eead4;">0%</span>' +
            '</div>' +
            '<button type="button" id="progCancelar" ' +
            'style="margin-top:16px;padding:7px 18px;border-radius:8px;border:1px solid #334155;background:transparent;color:#cbd5e1;font-size:13px;font-weight:600;cursor:pointer;transition:.2s;">' +
            '<i class="fas fa-ban" style="margin-right:6px"></i>Cancelar env&iacute;o</button>' +
            '</div>';
        document.body.appendChild(overlay);
        const btnC = document.getElementById('progCancelar');
        if (btnC) {
            btnC.addEventListener('mouseenter', function() { btnC.style.borderColor = '#ef4444'; btnC.style.color = '#f87171'; });
            btnC.addEventListener('mouseleave', function() { btnC.style.borderColor = '#334155'; btnC.style.color = '#cbd5e1'; });
        }
        return overlay;
    }
    function modalEngranajes() {
        const wrap = document.getElementById('progIconoWrap');
        if (wrap) wrap.innerHTML = '<span class="gear-grande"><i class="fas fa-gear"></i></span><span class="gear-peque"><i class="fas fa-gear"></i></span>';
        const titulo = document.getElementById('progTitulo');
        if (titulo) titulo.textContent = 'Procesando...';
    }
    function modalCanceladoOk() {
        const btnC = document.getElementById('progCancelar');
        if (btnC) { btnC.disabled = true; btnC.style.opacity = '.45'; btnC.style.cursor = 'default'; }
    }
    function progresoSet(pct, fase, sub) {
        const barra = document.getElementById('progBarra');
        const pctxt = document.getElementById('progPct');
        const subEl = document.getElementById('progSub');
        pct = Math.max(0, Math.min(100, pct));
        if (barra) barra.style.width = pct + '%';
        if (pctxt) pctxt.textContent = Math.round(pct) + '%';
        if (subEl && sub !== undefined) subEl.textContent = sub;
        else if (subEl && fase !== undefined) subEl.textContent = fase;
    }
    function cerrarModalProgreso() {
        const m = document.getElementById('modalProgreso');
        if (m) m.remove();
        if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null; }
    }

    /* ===== Animación fluida de la barra hacia el % objetivo (bytes reales) =====
       Los eventos onprogress llegan en ráfagas: la barra persigue el valor
       objetivo con interpolación por frame para reflejar el flujo continuo. */
    let objetivoPct = 0, mostradoPct = 0, rafId = null;
    function animarHaciaObjetivo() {
        if (rafId !== null) return;
        const paso = function () {
            const diff = objetivoPct - mostradoPct;
            mostradoPct = Math.abs(diff) < 0.25 ? objetivoPct : mostradoPct + diff * 0.14;
            const barra = document.getElementById('progBarra');
            const pctxt = document.getElementById('progPct');
            if (barra) barra.style.width = mostradoPct + '%';
            if (pctxt) pctxt.textContent = Math.round(mostradoPct) + '%';
            if (mostradoPct === objetivoPct) { rafId = null; return; }
            rafId = requestAnimationFrame(paso);
        };
        rafId = requestAnimationFrame(paso);
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        try {
        const nombre = campoNombre.value.trim();
        const correo = campoCorreo.value.trim();
        const mensaje = quill.getText().trim();
        campoMensaje.value = quill.root.innerHTML;
        logEvent('info', 'Iniciando validacion del formulario...');

        if (nombre === '') { logEvent('error', 'Campo Nombre vacio'); return aviso('error', 'Campo incompleto', 'Rellene el campo Nombre.', campoNombre); }
        logEvent('ok', 'Nombre: "' + nombre + '"');
        if (correo === '') { logEvent('error', 'Campo Correo vacio'); return aviso('error', 'Campo incompleto', 'Rellene el campo Correo electronico.', campoCorreo); }
        const correoOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(correo);
        if (!correoOk) { logEvent('error', 'Correo invalido: "' + correo + '"'); return aviso('error', 'Correo no valido', 'Introduzca una direccion de correo valida en el campo Correo electronico.', campoCorreo); }
        logEvent('ok', 'Correo: "' + correo + '"');
        var mensajeVacio = mensaje === '';
        if (mensajeVacio) logEvent('warn', 'Mensaje vacio: se preguntara al usuario antes de enviar');
        if (!mensajeVacio) logEvent('ok', 'Mensaje: ' + mensaje.length + ' caracteres');

        /* Validación de archivos adjuntos (usar acumulados directamente) */
        const MAX_ARCHIVO = 10 * 1024 * 1024;
        const MAX_TOTAL = 18 * 1024 * 1024;
        const archivos = Array.isArray(acumulados) ? acumulados.slice() : [];
        let totalBytes = 0;
        if (archivos.length) logEvent('info', 'Validando ' + archivos.length + ' archivo(s)...');
        for (const f of archivos) {
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (!EXT_OK.includes(ext)) { logEvent('error', 'Formato no permitido: "' + f.name + '"'); return aviso('error', 'Formato no permitido', 'El archivo "' + f.name + '" no es un formato admitido.', campoAdjuntos); }
            if (f.size > MAX_ARCHIVO) { logEvent('error', 'Archivo excede 10MB: "' + f.name + '"'); return aviso('error', 'Archivo muy grande', '"' + f.name + '" supera el maximo de 10 MB por archivo.', campoAdjuntos); }
            totalBytes += f.size;
        }
        if (archivos.length > 100) { logEvent('error', 'Mas de 100 archivos adjuntos'); return aviso('error', 'Demasiados archivos', 'Se permiten un maximo de 100 archivos adjuntos.', campoAdjuntos); }
        if (totalBytes > MAX_TOTAL) { logEvent('error', 'Peso total excede 18MB'); return aviso('error', 'Adjuntos muy pesados', 'El total de los adjuntos supera los 18 MB.', campoAdjuntos); }
        if (archivos.length) logEvent('ok', 'Adjuntos OK: ' + archivos.length + ' archivo(s), ' + formatoBytes(totalBytes));

        /* Asunto en blanco: preguntar si enviar igualmente */
        var campoAsunto = document.getElementById('asunto');
        var asuntoVacio = !campoAsunto || campoAsunto.value.trim() === '';

        function _deshabilitarForm() {
            const formEl = document.getElementById('formContacto');
            if (formEl) {
                formEl.querySelectorAll('input, select, textarea, button').forEach(function(el) {
                    el.disabled = true;
                });
            }
            ['btnNuevo', 'btnNuevoBarra', 'btnToggleLog', 'btnSaveEml', 'logClearBtn', 'btnEnviarBarra', 'chkAcuse', 'chkTextoPlano'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.disabled = true;
            });
            try { if (typeof quill !== 'undefined' && quill && quill.enable) { quill.enable(false); } } catch (e) {}
            ['logFab', 'logFabInicio', 'logFabFinal', 'panelAdjuntos', 'ctxMenuAdjuntos', 'prioDd'].forEach(function(id) {
                const el = document.getElementById(id);
                if (!el) return;
                if (!el.dataset.prevPo) el.dataset.prevPo = el.style.pointerEvents || '';
                if (!el.dataset.prevOp) el.dataset.prevOp = el.style.opacity || '';
                el.style.pointerEvents = 'none';
                el.style.opacity = '0.4';
            });
            const toolbar = document.querySelector('.ql-toolbar');
            if (toolbar) {
                if (!toolbar.dataset.prevPo) toolbar.dataset.prevPo = toolbar.style.pointerEvents || '';
                toolbar.style.pointerEvents = 'none';
            }
            if (quill && quill.root) {
                if (!quill.root.dataset.prevPo) quill.root.dataset.prevPo = quill.root.style.pointerEvents || '';
                quill.root.style.pointerEvents = 'none';
            }
        }
        function _habilitarForm() {
            const formEl = document.getElementById('formContacto');
            if (formEl) {
                formEl.querySelectorAll('input, select, textarea, button').forEach(function(el) {
                    el.disabled = false;
                });
            }
            ['btnNuevo', 'btnNuevoBarra', 'btnToggleLog', 'btnSaveEml', 'logClearBtn', 'btnEnviarBarra', 'chkAcuse', 'chkTextoPlano'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.disabled = false;
            });
            try { if (typeof quill !== 'undefined' && quill && quill.enable) { quill.enable(true); } } catch (e) {}
            ['logFab', 'logFabInicio', 'logFabFinal', 'panelAdjuntos', 'ctxMenuAdjuntos', 'prioDd'].forEach(function(id) {
                const el = document.getElementById(id);
                if (!el) return;
                if (el.dataset.prevPo === undefined) el.style.pointerEvents = '';
                else el.style.pointerEvents = el.dataset.prevPo;
                if (el.dataset.prevOp === undefined) el.style.opacity = '';
                else el.style.opacity = el.dataset.prevOp;
            });
            const toolbar = document.querySelector('.ql-toolbar');
            if (toolbar) toolbar.style.pointerEvents = toolbar.dataset.prevPo || '';
            if (quill && quill.root) quill.root.style.pointerEvents = quill.root.dataset.prevPo || '';
        }

        function iniciarEnvio() {
            logEvent('send', 'Validacion completa, preparando envio XHR...');
            const b = form.querySelector('button[type=submit]');
        b.disabled = true;
        b.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Enviando...';

        cerrarModalProgreso();
        objetivoPct = 0;
        mostradoPct = 0;

        crearModalProgreso(archivos, totalBytes);
        const hayAdjuntos = archivos.length > 0;

        const umbrales = [];
        let acumulado = 600;
        archivos.forEach(function(f) {
            umbrales.push({ inicio: acumulado, tam: Math.max(1, f.size) });
            acumulado += f.size + 600;
        });

        const datos = new FormData();
        form.querySelectorAll('input:not([type=file]), select, textarea').forEach(function(el) {
            if (!el.name || el.disabled) return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
            datos.append(el.name, el.value);
        });
        ['chkAcuse', 'chkTextoPlano'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el && !el.disabled && el.checked && el.name) datos.append(el.name, el.value);
        });
        if (datos.has('acuse_recibo')) logEvent('ok', 'Acuse de recibo solicitado');
        else logEvent('info', 'Sin acuse de recibo');
        if (datos.has('texto_plano')) logEvent('ok', 'Enviando como texto plano');
        else logEvent('info', 'Enviando como HTML');
        if (archivos.length) {
            archivos.forEach(function(f) { datos.append('adjuntos[]', f, f.name); });
        }

        logEvent('send', 'Abriendo conexion HTTP a ' + (form.action || 'contacto.php') + '...');
        _deshabilitarForm();
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action || 'contacto.php', true);

        const btnCancelar = document.getElementById('progCancelar');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function() {
                logEvent('warn', 'Usuario cancelo el envio');
                xhr.abort();
            });
        }

        xhr.upload.onprogress = function(ev) {
            if (!ev.lengthComputable) return;
            const pct = (ev.loaded / ev.total) * 100;

            if (hayAdjuntos) {
                let idx = 0;
                for (let i = 0; i < umbrales.length; i++) {
                    if (ev.loaded >= umbrales[i].inicio) { idx = i; } else { break; }
                }
                const u = umbrales[idx];
                const dentro = Math.min(100, Math.max(0, ((ev.loaded - u.inicio) / u.tam) * 100));

                const progArchivo = document.getElementById('progArchivo');
                if (progArchivo) {
                    progArchivo.innerHTML =
                        'Enviando <b style="color:#5eead4">' + (idx + 1) + '</b> de <b style="color:#5eead4">' + archivos.length + '</b>' +
                        ' &mdash; ' + escHtml(archivos[idx].name) +
                        ' <span style="color:#64748b">(' + Math.round(dentro) + '%)</span>';
                }
            }

            objetivoPct = Math.max(objetivoPct, pct);
            animarHaciaObjetivo();
            const subEl = document.getElementById('progSub');
            if (subEl) subEl.textContent =
                'Subiendo... ' + formatoBytes(ev.loaded) + ' de ' + formatoBytes(ev.total);

            if (pct >= 99.9) {
                objetivoPct = 100;
                animarHaciaObjetivo();
                modalEngranajes();
                logEvent('send', 'Bytes transmitidos: ' + formatoBytes(ev.total) + '. Esperando respuesta del servidor...');
                const s2 = document.getElementById('progSub');
                if (s2) s2.textContent = 'Entregando al servidor de correo, espera un momento...';
            }
        };
        xhr.onabort = function () {
            logEvent('warn', 'Envio cancelado por el usuario');
            cerrarModalProgreso();
            _habilitarForm();
            b.disabled = false;
            b.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar mensaje';
            aviso('warning', 'Env\u00edo cancelado', 'La operaci\u00f3n de env\u00edo fue cancelada. El mensaje no fue entregado.', null);
        };
        xhr.onerror = function () {
            logEvent('error', 'Error de red durante el envio');
            cerrarModalProgreso();
            _habilitarForm();
            b.disabled = false;
            b.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar mensaje';
            aviso('error', 'Error de red', 'No se pudo enviar el mensaje. Revisa tu conexion e intenta de nuevo.', null);
        };
        xhr.onload = function () {
            logEvent('info', 'Respuesta del servidor: HTTP ' + xhr.status);
            cerrarModalProgreso();
            if (xhr.status >= 200 && xhr.status < 300) {
                /* Analizar la respuesta para distinguir el exito (panel de confirmacion)
                   de un re-render del formulario (error controlado, p.ej. el mensaje
                   "Ocurrio un problema temporal"). */
                const tmp = document.createElement('div');
                tmp.innerHTML = xhr.responseText || '';
                const bodyContent = tmp.querySelector('.win-body');
                const respuestaTraeFormulario = !!(bodyContent && bodyContent.querySelector('#formContacto'));

                if (respuestaTraeFormulario) {
                    /* El servidor devolvio el formulario re-renderizado (fallo controlado).
                       No reemplazar el DOM para no perder el editor Quill ni los eventos:
                       restauramos los controles y dejamos que los scripts embebidos de la
                       respuesta (modal de error) se ejecuten. El formulario queda activo. */
                    logEvent('error', 'El servidor devolvio el formulario (fallo en el envio). El formulario queda listo.');
                    _habilitarForm();
                    b.disabled = false;
                    b.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar mensaje';
                    if (bodyContent) {
                        bodyContent.querySelectorAll('script').forEach(function(s) {
                            const ns = document.createElement('script');
                            ns.textContent = s.textContent;
                            document.head.appendChild(ns);
                            s.remove();
                        });
                    }
                    if (logFab) logFab.style.display = 'flex';
                    return;
                }

                logEvent('ok', 'Mensaje enviado con exito');
                /* Inyectar respuesta en la win-body sin destruir el log */
                const winBody = document.querySelector('.win-body');
                if (winBody) {
                    /* Extraer solo el contenido del body de la respuesta */
                    if (bodyContent) {
                        winBody.innerHTML = bodyContent.innerHTML;
                    } else {
                        winBody.innerHTML = tmp.innerHTML;
                    }
                    /* Ejecutar scripts embebidos en la respuesta */
                    winBody.querySelectorAll('script').forEach(function(s) {
                        const ns = document.createElement('script');
                        ns.textContent = s.textContent;
                        document.head.appendChild(ns);
                        s.remove();
                    });
                }
                /* Mantener el botón flotante activo */
                if (logFab) logFab.style.display = 'flex';
            } else {
                b.disabled = false;
                b.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar mensaje';
                var resp = '';
                try { resp = xhr.responseText.substring(0, 500); } catch(e) {}
                var detalle = resp ? '\n\nServidor: ' + resp.replace(/<[^>]+>/g, '').substring(0, 300) : '';
                logEvent('error', 'Servidor respondio error (' + xhr.status + ')');
                _habilitarForm();
                aviso('error', 'No se pudo enviar', 'El servidor respondio con error (' + xhr.status + ').' + detalle, null);
            }
        };
        xhr.send(datos);
        logEvent('send', 'FormData enviado (' + datos.toString().length + ' bytes approx.)');
        }

        /* Campos en blanco opcionales: preguntar si enviar igualmente o editar */
        var pendientesConfirmar = [];
        if (asuntoVacio) {
            logEvent('warn', 'El campo Asunto esta vacio, se consultara al usuario');
            pendientesConfirmar.push({ campo: campoAsunto, campoNombre: 'Asunto', msg: 'El correo no tiene asunto.' });
        }
        if (mensajeVacio) {
            logEvent('warn', 'El campo Mensaje esta vacio, se consultara al usuario');
            pendientesConfirmar.push({ campo: null, campoNombre: 'Mensaje', msg: 'El cuerpo del mensaje esta en blanco.' });
        }

        function enviarTrasConfirmaciones(i) {
            if (i >= pendientesConfirmar.length) {
                logEvent('ok', 'Confirmaciones superadas, enviando...');
                iniciarEnvio();
                return;
            }
            var pc = pendientesConfirmar[i];
            logEvent('info', 'Preguntando sobre "' + pc.campoNombre + '" en blanco');
            Swal.fire({
                title: undefined,
                html:
                    '<div style="text-align:center;padding:6px 4px 2px;">' +
                    '<div style="font-size:15px;font-weight:700;color:#e2e8f0;margin-bottom:10px;">' + pc.campoNombre + ' en blanco</div>' +
                    '<div style="font-size:13px;color:#94a3b8;line-height:1.6;">' +
                    '<i class="fas fa-triangle-exclamation" style="color:#fbbf24;margin-right:6px;"></i>' +
                    pc.msg + '<br>¿Deseas enviar el mensaje igualmente?</div>' +
                    '</div>',
                background: '#0f172a',
                color: '#e2e8f0',
                showCancelButton: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                backdrop: 'rgba(0, 0, 0, 0.72)',
                confirmButtonText: '<i class="fas fa-paper-plane" style="margin-right:8px"></i>Sí, enviar',
                cancelButtonText: '<i class="fas fa-pen-to-square" style="margin-right:8px"></i>Editar ' + pc.campoNombre,
                confirmButtonColor: '#0f766e',
                cancelButtonColor: '#33333f',
                reverseButtons: true,
                customClass: { popup: 'swal-dark-popup', confirmButton: 'swal-dark-confirm' }
            }).then(function(resultado) {
                if (resultado.isConfirmed) {
                    logEvent('ok', 'Usuario acepto enviar con "' + pc.campoNombre + '" en blanco');
                    enviarTrasConfirmaciones(i + 1);
                } else {
                    logEvent('info', 'Usuario edita el campo "' + pc.campoNombre + '"');
                    if (pc.campo) pc.campo.focus();
                    else if (typeof quill !== 'undefined' && quill) quill.focus();
                }
            });
        }
        enviarTrasConfirmaciones(0);
        } catch(err) { logEvent('error', 'Error JS en envio: ' + err.message); console.error(err); aviso('error', 'Error interno', 'Ocurrio un error inesperado: ' + err.message, null); }
    });

    /* ===== Nuevo / Limpiar formulario ===== */
    function limpiarFormulario() {
        if (campoNombre) campoNombre.value = '';
        if (campoCorreo) campoCorreo.value = '';
        var taAsunto = document.getElementById('asunto');
        if (taAsunto) taAsunto.value = '';
        var prioSel = document.getElementById('prioridad');
        if (prioSel) { prioSel.value = 'normal'; }
        if (typeof setPrio === 'function') setPrio('normal');
        else if (typeof pintarSello === 'function') pintarSello('normal');
        var chkAcuse = document.getElementById('chkAcuse');
        if (chkAcuse) chkAcuse.checked = false;
        var chkTexto = document.getElementById('chkTextoPlano');
        if (chkTexto) chkTexto.checked = false;
        if (typeof quill !== 'undefined' && quill) {
            quill.setText('');
            quill.setSelection(0, Quill.sources.SILENT);
        }
        if (typeof window.quitarTodosContacto === 'function') {
            window.quitarTodosContacto();
        }
        /* Limpiar y ocultar el log de proceso */
        if (logEl) logEl.innerHTML = '';
        logCount = 0;
        if (logContador) logContador.textContent = '0';
        if (logContenedor) {
            logContenedor.classList.add('d-none');
            logContenedor.style.display = 'none';
        }
        if (logEl) logEl.style.display = 'none';
        if (logFabBadge) { logFabBadge.textContent = '0'; logFabBadge.classList.remove('visible'); }
        var icoLog = logFab ? logFab.querySelector('i') : null;
        if (icoLog) icoLog.className = 'fas fa-terminal';
        var icoBtn = document.getElementById('btnToggleLog');
        if (icoBtn) {
            var icoB = icoBtn.querySelector('i');
            if (icoB) icoB.className = 'fas fa-terminal';
        }
        var chevL = logHeader ? logHeader.querySelector('#logChevron') : null;
        if (chevL) chevL.style.transform = '';
        aviso('success', 'Listo', 'El formulario se ha limpiado. Puedes escribir un mensaje nuevo.', null);
    }
    ['btnNuevo', 'btnNuevoBarra'].forEach(function(id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', limpiarFormulario);
    });
})();
</script>
</body>
</html>
