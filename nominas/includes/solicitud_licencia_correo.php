<?php
// includes/solicitud_licencia_correo.php
// Logica de la solicitud de licencia por correo electronico.
// Se invoca desde licencia.php (pantalla pre-login, sin sesion).

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/licencia.php';

if (!function_exists('solicitud_licencia_destino_fallback')) {
    /**
     * Correo de respaldo: se usa solo si configuracion_general no esta
     * disponible (base de datos caida) o si email_soporte esta vacio.
     */
    function solicitud_licencia_destino_fallback() {
        return 'kakycu@gmail.com';
    }
}

if (!function_exists('solicitud_licencia_version')) {
    /**
     * Version del sistema que se muestra al usuario final en los avisos,
     * para que el proveedor identifique la instalacion al recibir la solicitud.
     */
    function solicitud_licencia_version() {
        return defined('SITE_VERSION') && SITE_VERSION !== '' ? SITE_VERSION : 'v2.0.1';
    }
}

if (!function_exists('solicitud_licencia_pdo')) {
    /**
     * Conexion PDO perezosa y reutilizable.
     *
     * Se resuelve una sola vez por peticion. Nunca se llama durante el render
     * de licencia.php: esa pantalla debe seguir funcionando sin base de datos,
     * y config/database.php aborta la pagina si MySQL no responde.
     *
     * @return PDO|null null cuando la base de datos no esta disponible.
     */
    function solicitud_licencia_pdo() {
        static $resuelto = false;
        static $conexion = null;
        if ($resuelto) {
            return $conexion;
        }
        $resuelto = true;
        try {
            require_once __DIR__ . '/../config/database.php';
            if (isset($pdo) && $pdo instanceof PDO) {
                $conexion = $pdo;
            } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                $conexion = $GLOBALS['pdo'];
            }
        } catch (Throwable $e) {
            $conexion = null;
        }
        return $conexion;
    }
}

if (!function_exists('solicitud_licencia_destino')) {
    /**
     * Correo receptor de las solicitudes de licencia.
     *
     * Se lee de configuracion_general (parametro 'email_soporte'), igual que
     * hace login.php, de modo que el destino se cambia desde Configuracion
     * del sistema sin tocar el codigo. Si no hay PDO, si la consulta falla o
     * el valor no es un correo valido, se devuelve el correo de respaldo.
     *
     * @param PDO|null $pdo Conexion ya disponible; si se omite se resuelve sola.
     * @return string
     */
    function solicitud_licencia_destino($pdo = null) {
        if (!($pdo instanceof PDO)) {
            $pdo = solicitud_licencia_pdo();
        }
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'email_soporte' LIMIT 1");
                $stmt->execute();
                $correo = trim((string)$stmt->fetchColumn());
                if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false) {
                    return $correo;
                }
            } catch (Throwable $e) {
                // Sin configuracion_general legible: se usa el respaldo.
            }
        }
        return solicitud_licencia_destino_fallback();
    }
}

if (!function_exists('solicitud_licencia_correo_remitente')) {
    /**
     * Cuenta de correo configurada para el envio (configuracion_general.mail_usuario).
     *
     * Se usa como valor inicial del campo "Correo Electronico del Solicitante"
     * del formulario, para que el cliente no tenga que escribirlo. Si no hay PDO,
     * si la consulta falla o el valor no es un correo valido, se devuelve ''.
     *
     * @param PDO|null $pdo Conexion ya disponible; si se omite se resuelve sola.
     * @return string
     */
    function solicitud_licencia_correo_remitente($pdo = null) {
        if (!($pdo instanceof PDO)) {
            $pdo = solicitud_licencia_pdo();
        }
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'mail_usuario' LIMIT 1");
                $stmt->execute();
                $correo = trim((string)$stmt->fetchColumn());
                if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false) {
                    return $correo;
                }
            } catch (Throwable $e) {
                // Sin configuracion_general legible: el campo se deja vacio.
            }
        }
        return '';
    }
}

if (!function_exists('solicitud_licencia_correo_whatsapp')) {
    /**
     * Numero de WhatsApp para enviar la solicitud desde el navegador.
     *
     * Se toma de configuracion_general: primero whatsapp_numero (el mismo
     * parametro que usa mantenimiento.php, respetando whatsapp_ON) y, si no
     * esta, del telefono de soporte, que es el numero que atiende las dudas.
     * Devuelve solo digitos en formato internacional, sin el +.
     *
     * @param PDO|null $pdo Conexion ya disponible; si se omite se resuelve sola.
     * @return string '' si no hay un numero utilizable.
     */
    function solicitud_licencia_correo_whatsapp($pdo = null) {
        if (!($pdo instanceof PDO)) {
            $pdo = solicitud_licencia_pdo();
        }
        $numero = '';
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT parametro, valor FROM configuracion_general"
                    . " WHERE parametro IN ('whatsapp_numero','whatsapp_ON','telefono_soporte')");
                $stmt->execute();
                $cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                if (!empty($cfg['whatsapp_numero']) && (!isset($cfg['whatsapp_ON']) || (string)$cfg['whatsapp_ON'] === '1')) {
                    $numero = (string)$cfg['whatsapp_numero'];
                } elseif (!empty($cfg['telefono_soporte'])) {
                    $numero = (string)$cfg['telefono_soporte'];
                }
            } catch (Throwable $e) {
                // Sin configuracion_general legible: no hay numero.
            }
        }
        return solicitud_licencia_formatear_whatsapp($numero);
    }
}

if (!function_exists('solicitud_licencia_formatear_whatsapp')) {
    /**
     * Deja un numero de telefono en formato internacional para api.whatsapp.com.
     *
     * Un movil cubano de 8 digitos se completa con el prefijo 53; cualquier
     * otro prefijo internacional se respeta tal como esta configurado.
     *
     * @return string Solo digitos, o '' si no queda un numero valido (8 a 15).
     */
    function solicitud_licencia_formatear_whatsapp($numero) {
        if ($numero === null || trim((string)$numero) === '') {
            return '';
        }
        $numero = preg_replace('/\D/', '', (string)$numero);
        // Prefijo internacional escrito como 00: WhatsApp lo espera sin el 00.
        if (substr($numero, 0, 2) === '00') {
            $numero = substr($numero, 2);
        }
        if (strlen($numero) === 8 && substr($numero, 0, 2) !== '53') {
            $numero = '53' . $numero;
        }
        $largo = strlen($numero);
        if ($largo < 8 || $largo > 15) {
            return '';
        }
        return $numero;
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

if (!function_exists('solicitud_licencia_ci_validar')) {
    /**
     * Valida el Carné de identidad con el mismo criterio que login.php.
     *
     * El formulario ya lo revisa en el navegador, pero esa comprobación se
     * puede saltar con un POST directo. Como el correo va al proveedor de
     * licencias, que usa el CI para identificar al cliente, el servidor repite
     * aqui la validación completa: 11 dígitos, fecha de nacimiento posible y
     * dígito de género en la posición 10.
     *
     * @param string $ci Carné ya normalizado (solo dígitos).
     * @return string '' si es válido; en otro caso, el motivo del rechazo.
     */
    function solicitud_licencia_ci_validar($ci) {
        if (preg_match('/^\d{11}$/', $ci) !== 1) {
            return 'debe contener exactamente 11 digitos';
        }

        $anio = (int)substr($ci, 0, 2);
        $mes  = (int)substr($ci, 2, 2);
        $dia  = (int)substr($ci, 4, 2);

        if ($mes < 1 || $mes > 12) {
            return 'tiene un mes de nacimiento invalido';
        }

        $anio_completo = ($anio < 30) ? (2000 + $anio) : (1900 + $anio);
        $bisiesto = (($anio_completo % 4 === 0) && ($anio_completo % 100 !== 0)) || ($anio_completo % 400 === 0);

        if ($mes === 2 && $dia === 29 && !$bisiesto) {
            return 'no puede tener 29/02 en un ano que no es bisiesto';
        }

        $dias_por_mes = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $max_dia = ($mes === 2) ? ($bisiesto ? 29 : 28) : $dias_por_mes[$mes - 1];
        if ($dia < 1 || $dia > $max_dia) {
            return 'tiene un dia de nacimiento invalido';
        }

        return '';
    }
}

if (!function_exists('solicitud_licencia_sexo')) {
    /**
     * Sexo segun el digito de genero del carne de identidad (posicion 10),
     * con el mismo criterio que usa el login: par = Masculino, impar = Femenino.
     *
     * @param string $ci Carne ya normalizado o con guiones/espacios.
     * @return string Masculino, Femenino, o '' si el CI no tiene 11 digitos.
     */
    function solicitud_licencia_sexo($ci) {
        $ci = preg_replace('/[\s\-]+/', '', (string)$ci);
        if (preg_match('/^\d{11}$/', $ci) !== 1) {
            return '';
        }
        return (((int)$ci[9] % 2) === 0) ? 'Masculino' : 'Femenino';
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
            'email'      => array('etiqueta' => 'Correo Electronico del Solicitante', 'max' => 120),
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

        // El CI se normaliza quitando guiones/espacios y se valida por completo.
        if (isset($datos['ci'])) {
            $datos['ci'] = preg_replace('/[\s\-]+/', '', $datos['ci']);
            $problema_ci = solicitud_licencia_ci_validar($datos['ci']);
            if ($problema_ci !== '') {
                $errores['ci'] = 'El campo Carne de identidad ' . $problema_ci . '.';
            }
        }

        // El correo debe ser una direccion valida (mismo filtro que el login).
        if (isset($datos['email']) && filter_var($datos['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'El campo Correo Electronico del Solicitante no es una direccion valida.';
        }

        // Correo del proveedor de soporte tecnico: es opcional porque el formulario
        // lo prellena con configuracion_general.email_soporte, pero el cliente puede
        // cambiarlo si necesita enviar la solicitud a otra direccion. Si llega
        // vacio se conserva el destino configurado en el sistema.
        $datos['email_soporte'] = '';
        if (isset($entrada['email_soporte'])) {
            $soporte = solicitud_licencia_limpiar($entrada['email_soporte']);
            if ($soporte !== '') {
                $largo_soporte = function_exists('mb_strlen') ? mb_strlen($soporte, 'UTF-8') : strlen($soporte);
                if ($largo_soporte > 120) {
                    $errores['email_soporte'] = 'El campo Correo Electronico del Proveedor de Soporte Tecnico es demasiado largo (maximo 120 caracteres).';
                } elseif (filter_var($soporte, FILTER_VALIDATE_EMAIL) === false) {
                    $errores['email_soporte'] = 'El campo Correo Electronico del Proveedor de Soporte Tecnico no es una direccion valida.';
                } else {
                    $datos['email_soporte'] = $soporte;
                }
            }
        }

        // Codigo de equipo: es opcional porque el formulario lo manda deshabilitado con
        // el de esta PC. Si el cliente lo cambia con el lapiz, se respeta su valor;
        // si viene vacio o no tiene el formato, se usa el de esta maquina.
        $datos['huella'] = '';
        if (isset($entrada['huella'])) {
            $huella = strtoupper(solicitud_licencia_limpiar($entrada['huella']));
            $huella = preg_replace('/[^A-Z0-9\-]/', '', (string)$huella);
            if ($huella !== '') {
                if (preg_match('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/', $huella) !== 1) {
                    $errores['huella'] = 'El campo Codigo de Equipo debe tener el formato #####-#####-#####-#####.';
                } else {
                    $datos['huella'] = $huella;
                }
            }
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
            'Sexo'              => solicitud_licencia_sexo($datos['ci']),
            'Correo electronico' => $datos['email'],
            'Usuario'            => $datos['usuario'],
            'Entidad'            => $datos['entidad'],
            'Periodo de validez' => $periodo_texto,
            'Codigo de equipo'   => $huella,
        );
        if (isset($datos['generica']) && $datos['generica'] === '1') {
            $filas['Tipo de Licencia'] = 'Generica';
        } else {
            $filas['Tipo de Licencia'] = 'Especifica';
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
            'Sexo:                ' . solicitud_licencia_sexo($datos['ci']),
            'Correo electronico: ' . $datos['email'],
            'Usuario:            ' . $datos['usuario'],
            'Entidad:            ' . $datos['entidad'],
            'Periodo de validez: ' . $periodo_texto,
            'Codigo de equipo:   ' . $huella,
        );
        if (isset($datos['generica']) && $datos['generica'] === '1') {
            $lineas[] = 'Tipo de Licencia:   Generica';
        } else {
            $lineas[] = 'Tipo de Licencia:   Especifica';
        }
        $lineas[] = '';
        $lineas[] = str_repeat('=', 40);
        return implode("\n", $lineas);
    }
}

if (!function_exists('solicitud_licencia_rate_limite')) {
    /**
     * Limite de solicitudes por IP.
     *
     * La pantalla es pre-login, asi que no hay sesion de la que valerse: el
     * control se hace por direccion IP. Con 8 intentos por 10 minutos el
     * cliente legitimo puede corregir y reenviar, pero un script que dispare
     * miles de solicitudes se queda en el primero de cada bloque y el buzon del
     * proveedor de licencias no se inunda.
     *
     * @return array{maximo:int, ventana:int}
     */
    function solicitud_licencia_rate_limite() {
        return array('maximo' => 8, 'ventana' => 600);
    }
}

if (!function_exists('solicitud_licencia_ip')) {
    /**
     * Direccion IP de quien hace la solicitud.
     */
    function solicitud_licencia_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
        if ($ip === '' || $ip === '::1' || $ip === '::ffff:127.0.0.1') {
            $ip = '127.0.0.1';
        }
        return function_exists('filter_var') && filter_var($ip, FILTER_VALIDATE_IP) !== false
            ? $ip
            : sha1($ip);
    }
}

if (!function_exists('solicitud_licencia_rate_almacen')) {
    /**
     * Carpeta donde se registran los intentos por IP.
     *
     * Usa el directorio temporal del sistema para no escribir dentro del sitio
     * web: %TEMP% en Windows y /tmp en Linux (o $TMPDIR si esta definido).
     * El nombre lleva un resumen de la ruta de instalacion, de modo que dos
     * copias del sistema en el mismo servidor no compartan el mismo archivo.
     * Se crea con permisos 0700 para que ningun otro usuario del servidor
     * compartido lea ni manipule los registros.
     *
     * @return string|null Ruta utilizable, o null si no se puede escribir; en
     *                     ese caso el envio no se bloquea.
     */
    function solicitud_licencia_rate_almacen() {
        static $dir = false;
        if ($dir !== false) {
            return $dir;
        }

        $dir  = null;
        $base = rtrim(sys_get_temp_dir(), '/\\');
        if ($base === '') {
            return null;
        }
        $base .= DIRECTORY_SEPARATOR . 'sgsnom_licencia_' . substr(sha1(__DIR__), 0, 8);

        if (!is_dir($base)) {
            @mkdir($base, 0700, true);
        }
        if (is_dir($base) && is_writable($base)) {
            $dir = $base;
        }
        return $dir;
    }
}

if (!function_exists('solicitud_licencia_rate_limpiar')) {
    /**
     * Borra los registros que ya no cuentan para el limite.
     */
    function solicitud_licencia_rate_limpiar($dir, $ventana) {
        $limite = time() - ($ventana * 4);
        $archivos = @scandir($dir);
        if (!is_array($archivos)) {
            return;
        }
        foreach ($archivos as $archivo) {
            if ($archivo === '.' || $archivo === '..') {
                continue;
            }
            $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
            if (is_file($ruta) && @filemtime($ruta) < $limite) {
                @unlink($ruta);
            }
        }
    }
}

if (!function_exists('solicitud_licencia_rate_limitar')) {
    /**
     * Registra el intento y aplica el limite por IP.
     *
     * Se cuenta cualquier POST al endpoint, tambien el que falla la
     * validacion: el objetivo es que ni los datos erroneos ni los validos
     * repetidos por un script lleguen al buzon del proveedor.
     *
     * @return int|null null si la solicitud puede continuar; en otro caso los
     *                segundos que faltan para volver a intentarlo.
     */
    function solicitud_licencia_rate_limitar() {
        $cfg = solicitud_licencia_rate_limite();
        $dir = solicitud_licencia_rate_almacen();
        if ($dir === null) {
            return null;
        }

        $ruta  = $dir . DIRECTORY_SEPARATOR . sha1(solicitud_licencia_ip()) . '.txt';
        $ahora = time();

        $intentos = array();
        if (is_file($ruta)) {
            $contenido = @file_get_contents($ruta);
            if (is_string($contenido)) {
                foreach (explode("\n", $contenido) as $marca) {
                    $marca = (int)trim($marca);
                    if ($marca > 0 && ($ahora - $marca) < $cfg['ventana']) {
                        $intentos[] = $marca;
                    }
                }
            }
        }

        if (count($intentos) >= $cfg['maximo']) {
            $restantes = $cfg['ventana'] - ($ahora - min($intentos));
            return max(1, (int)ceil($restantes));
        }

        $intentos[] = $ahora;
        @file_put_contents($ruta, implode("\n", $intentos), LOCK_EX);
        solicitud_licencia_rate_limpiar($dir, $cfg['ventana']);

        return null;
    }
}

if (!function_exists('solicitud_licencia_enviar')) {
    /**
     * Envia la solicitud por correo usando la configuracion SMTP vigente.
     * @return array{success:bool, error:string} error es siempre un codigo
     *         (mail_not_configured|sin_email|sin_bd o el texto tecnico de
     *         PHPMailer); el detalle real se registra con error_log.
     */
    function solicitud_licencia_enviar($datos) {
        $periodos = solicitud_licencia_periodos();
        $periodo  = isset($periodos[$datos['periodo']]) ? $periodos[$datos['periodo']] : $datos['periodo'];
        // El codigo de equipo lo elige el cliente en el formulario; si no lo cambio se
        // usa el de esta maquina, que es el caso normal.
        $huella = (isset($datos['huella']) && $datos['huella'] !== '')
            ? $datos['huella']
            : licencia_formatear_fingerprint(licencia_fingerprint_machine());

        // El cliente puede cambiar el destino desde el formulario; si lo deja vacio se
        // usa el de configuracion_general.email_soporte.
        $destino = (isset($datos['email_soporte']) && $datos['email_soporte'] !== '')
            ? $datos['email_soporte']
            : solicitud_licencia_destino();
        $nombre  = trim($datos['nombre'] . ' ' . $datos['apellidos']);
        $asunto  = 'Solicitud de licencia - ' . $datos['entidad'] . ' (' . $periodo . ')';
        $html    = solicitud_licencia_html($datos, $periodo, $huella);
        $texto   = solicitud_licencia_texto($datos, $periodo, $huella);

        try {
            require_once __DIR__ . '/../config/mail.php';
            if (solicitud_licencia_pdo() instanceof PDO) {
                return enviarCorreo(solicitud_licencia_pdo(), $destino, $nombre, $asunto, $html, $texto);
            }
        } catch (Throwable $e) {
            // El detalle tecnico no se muestra al usuario final, solo queda en el log.
            error_log('[licencia] no se pudo leer la configuracion de correo: ' . $e->getMessage());
            return array('success' => false, 'error' => 'sin_bd');
        }

        return array('success' => false, 'error' => 'mail_not_configured');
    }
}
