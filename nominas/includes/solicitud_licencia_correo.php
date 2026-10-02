<?php
// includes/solicitud_licencia_correo.php
// Logica de la solicitud de licencia por correo electronico.
// Se invoca desde licencia.php (pantalla pre-login, sin sesion).

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/licencia.php';

if (!function_exists('solicitud_licencia_destino')) {
    /**
     * Correo receptor de las solicitudes de licencia.
     */
    function solicitud_licencia_destino() {
        return 'kakycu@gmail.com';
    }
}

if (!function_exists('solicitud_licencia_periodos')) {
    /**
     * Periodos de validez ofrecidos en el formulario.
     * Se derivan de licencia_tipos() para que la etiqueta que ve el cliente
     * coincida siempre con la duracion real que dara la licencia al activarse.
     * La clave es el codigo de tipo; se muestra entre parentesis para que el
     * proveedor sepa que licencia emitir al leer la solicitud.
     */
    function solicitud_licencia_periodos() {
        $periodos = array();
        foreach (licencia_tipos() as $codigo => $info) {
            $periodos[$codigo] = $info['nombre'] . ' (' . $codigo . ')';
        }
        return $periodos;
    }
}

if (!function_exists('solicitud_licencia_limpiar')) {
    /**
     * Normaliza un valor recibido por POST.
     */
    function solicitud_licencia_limpiar($v) {
        $v = trim((string)$v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
        return trim((string)$v);
    }
}

if (!function_exists('solicitud_licencia_validar')) {
    /**
     * Valida los datos del formulario de solicitud.
     * @return array{ok:bool, errores:array<string,string>, datos:array<string,string>}
     */
    function solicitud_licencia_validar($entrada) {
        $errores = array();
        $datos   = array();

        $campos = array(
            'nombre'     => array('etiqueta' => 'Nombre',        'max' => 60),
            'apellidos'  => array('etiqueta' => 'Apellidos',     'max' => 60),
            'ci'         => array('etiqueta' => 'Carne de identidad', 'max' => 20),
            'email'      => array('etiqueta' => 'Correo electronico', 'max' => 120),
            'usuario'    => array('etiqueta' => 'Usuario',       'max' => 60),
            'entidad'    => array('etiqueta' => 'Entidad',       'max' => 120),
            'periodo'    => array('etiqueta' => 'Periodo de validez', 'max' => 10),
        );

        foreach ($campos as $clave => $cfg) {
            $valor = isset($entrada[$clave]) ? solicitud_licencia_limpiar($entrada[$clave]) : '';
            $largo = function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
            if ($valor === '') {
                $errores[$clave] = 'El campo ' . $cfg['etiqueta'] . ' es obligatorio.';
            } elseif ($largo > $cfg['max']) {
                $errores[$clave] = 'El campo ' . $cfg['etiqueta'] . ' es demasiado largo (maximo ' . $cfg['max'] . ' caracteres).';
            } else {
                $datos[$clave] = $valor;
            }
        }

        // El CI se normaliza quitando guiones/espacios y debe tener 11 digitos.
        if (isset($datos['ci'])) {
            $datos['ci'] = preg_replace('/[\s\-]+/', '', $datos['ci']);
            if (preg_match('/^\d{11}$/', $datos['ci']) !== 1) {
                $errores['ci'] = 'El campo Carne de identidad debe contener exactamente 11 digitos.';
            }
        }

        // El correo debe ser una direccion valida (mismo filtro que el login).
        if (isset($datos['email']) && filter_var($datos['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'El campo Correo electronico no es una direccion valida.';
        }

        // Slider "Generica": opcional, se normaliza a '1' o '0'. Cuando esta activo
        // la licencia se emite para el equipo que la instale, sin huella fija.
        $datos['generica'] = (isset($entrada['generica']) && (string)$entrada['generica'] === '1') ? '1' : '0';

        $periodos = solicitud_licencia_periodos();
        if (isset($datos['periodo']) && !isset($periodos[$datos['periodo']])) {
            $errores['periodo'] = 'Seleccione un periodo de validez valido.';
        }

        return array('ok' => (count($errores) === 0), 'errores' => $errores, 'datos' => $datos);
    }
}

if (!function_exists('solicitud_licencia_html')) {
    /**
     * Arma el cuerpo HTML del correo con los datos de la solicitud.
     */
    function solicitud_licencia_html($datos, $periodo_texto, $huella) {
        $filas = array(
            'Nombre'             => $datos['nombre'],
            'Apellidos'          => $datos['apellidos'],
            'Carne de identidad' => $datos['ci'],
            'Correo electronico' => $datos['email'],
            'Usuario'            => $datos['usuario'],
            'Entidad'            => $datos['entidad'],
            'Periodo de validez' => $periodo_texto,
            'Codigo de equipo'   => $huella,
        );
        if (isset($datos['generica']) && $datos['generica'] === '1') {
            $filas['Tipo'] = 'Generica';
        }

        $anio = date('Y');
        $app  = 'SisGesNom';

        $filas_html = '';
        foreach ($filas as $etiqueta => $valor) {
            $filas_html .= '
                <tr>
                    <td style="padding:0.625rem 0; border-bottom:0.0625rem solid rgba(255,255,255,0.08); font-size:0.75rem; color:#9d9d9d; width:40%; vertical-align:top;">'
                    . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</td>
                    <td style="padding:0.625rem 0; border-bottom:0.0625rem solid rgba(255,255,255,0.08); font-size:0.8125rem; color:#ffffff; font-weight:600;">'
                    . htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>';
        }

        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de licencia</title>
</head>
<body style="margin:0; padding:1.5rem 0.75rem; background:#1c1c1c; font-family:Segoe UI, Arial, sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px; margin:0 auto; background:#262626; border:0.0625rem solid #3d3d3d; border-radius:0.75rem; overflow:hidden;">
        <tr>
            <td style="background:#202020; padding:1.25rem 1.5rem; border-bottom:0.0625rem solid #3d3d3d;">
                <div style="font-size:0.75rem; color:#9d9d9d; letter-spacing:0.05em; text-transform:uppercase;">Solicitud de licencia</div>
                <div style="font-size:1.25rem; font-weight:700; color:#ffffff; margin-top:0.25rem;">' . $app . '</div>
            </td>
        </tr>
        <tr>
            <td style="padding:1.5rem;">
                <p style="margin:0 0 1rem; font-size:0.8125rem; line-height:1.6; color:#e5e5e5;">
                    Se solicita la emision de una licencia con las siguientes caracteristicas:
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                    ' . $filas_html . '
                </table>
                <p style="margin:1.25rem 0 0; font-size:0.75rem; line-height:1.6; color:#9d9d9d;">
                    El codigo de equipo identifica esta PC y es necesario para emitir la licencia.
                </p>
            </td>
        </tr>
        <tr>
            <td style="background:#202020; padding:1rem 1.5rem; border-top:0.0625rem solid #3d3d3d; text-align:center; font-size:0.6875rem; color:#6b6b6b;">
                &copy; ' . $anio . ' ' . $app . ' &middot; Sistema de Gestion de Nominas y Trabajadores
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}

if (!function_exists('solicitud_licencia_texto')) {
    /**
     * Versión en texto plano del correo.
     */
    function solicitud_licencia_texto($datos, $periodo_texto, $huella) {
        $lineas = array(
            'SOLICITUD DE LICENCIA',
            str_repeat('=', 40),
            '',
            'Nombre:             ' . $datos['nombre'],
            'Apellidos:          ' . $datos['apellidos'],
            'Carne de identidad: ' . $datos['ci'],
            'Correo electronico: ' . $datos['email'],
            'Usuario:            ' . $datos['usuario'],
            'Entidad:            ' . $datos['entidad'],
            'Periodo de validez: ' . $periodo_texto,
            'Codigo de equipo:   ' . $huella,
        );
        if (isset($datos['generica']) && $datos['generica'] === '1') {
            $lineas[] = 'Tipo:               Generica';
        }
        $lineas[] = '';
        $lineas[] = str_repeat('=', 40);
        return implode("\n", $lineas);
    }
}

if (!function_exists('solicitud_licencia_enviar')) {
    /**
     * Envia la solicitud por correo usando la configuracion SMTP vigente.
     * @return array{success:bool, error:string}
     */
    function solicitud_licencia_enviar($datos) {
        $periodos = solicitud_licencia_periodos();
        $periodo  = isset($periodos[$datos['periodo']]) ? $periodos[$datos['periodo']] : $datos['periodo'];
        $huella   = licencia_formatear_fingerprint(licencia_fingerprint_machine());

        $destino = solicitud_licencia_destino();
        $nombre  = trim($datos['nombre'] . ' ' . $datos['apellidos']);
        $asunto  = 'Solicitud de licencia - ' . $datos['entidad'] . ' (' . $periodo . ')';
        $html    = solicitud_licencia_html($datos, $periodo, $huella);
        $texto   = solicitud_licencia_texto($datos, $periodo, $huella);

        $pdo = null;
        try {
            require_once __DIR__ . '/../config/database.php';
            require_once __DIR__ . '/../config/mail.php';
            if (isset($pdo) && $pdo instanceof PDO) {
                return enviarCorreo($pdo, $destino, $nombre, $asunto, $html, $texto);
            }
        } catch (Throwable $e) {
            return array('success' => false, 'error' => 'No fue posible conectar con la base de datos para leer la configuracion de correo.');
        }

        return array('success' => false, 'error' => 'mail_not_configured');
    }
}
