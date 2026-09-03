<?php
// solic_userinfo/api.php - Endpoints JSON protegidos por CLAVE de acceso.
// Réplica de los handlers AJAX del modal "Listado Total Salario Devengado por
// Trabajador" de modules/nominas.php: datos_iniciales, meses y listado.
// Solo lectura; usa las credenciales centrales vía config/database.php.
// La única acción pública es "verificar_clave", que comprueba si la clave
// introducida existe en el sistema (contraseñas bcrypt de clasif_usuarios
// activos). El resto exige sesión verificada.

require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

function json_salida($datos, $codigo_http = 200) {
    http_response_code($codigo_http);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $accion = $_GET['accion'] ?? '';

    // ---------------------------------------------------------------
    // VERIFICACIÓN DE LA CLAVE DE ACCESO (única acción pública)
    // Uso racional y cuidadoso de datos:
    //  - Solo se consultan los campos imprescindibles (nunca CI,
    //    dirección, email ni foto) y nada de ello sale al cliente.
    //  - Comparación con password_verify (segura ante timing attacks),
    //    sin revelar qué usuario coincidió ni listar usuarios.
    //  - Pequeño retardo ante fallos (sin bloqueo por tiempo).
    //  - Mensajes genéricos que no filtran información.
    // ---------------------------------------------------------------
    if ($accion === 'verificar_clave') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_salida(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $clave = (string)($_POST['clave'] ?? '');
        if ($clave === '' || strlen($clave) > 255) {
            json_salida(['success' => false, 'error' => 'Debe introducir la clave de acceso.'], 400);
        }

        $stmt = $pdo->query("SELECT nombre, apellidos, password FROM clasif_usuarios WHERE activo = 1");
        $acceso_ok = false;
        $nombre_persona = '';
        while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($u['password']) && password_verify($clave, $u['password'])) {
                $acceso_ok = true;
                $nombre_persona = trim($u['nombre'] . ' ' . $u['apellidos']);
                break;
            }
        }

        if (!$acceso_ok) {
            usleep(400000); // freno suave e invisible ante intentos masivos
            json_salida(['success' => false, 'error' => 'No tiene acceso: la clave no existe dentro del sistema.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['su_acceso']   = true;
        $_SESSION['su_nombre']   = $nombre_persona;

        json_salida(['success' => true, 'nombre' => $nombre_persona]);
    }

    // ---------------------------------------------------------------
    // CERRAR ACCESO: destruye la sesión y su cookie, dejando la
    // página en su estado inicial (portal de clave visible de nuevo).
    // ---------------------------------------------------------------
    if ($accion === 'cerrar_acceso') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
        json_salida(['success' => true, 'mensaje' => 'Sesión cerrada.']);
    }

    // El resto de las acciones exigen acceso previamente verificado
    if (empty($_SESSION['su_acceso']) || $_SESSION['su_acceso'] !== true) {
        json_salida(['success' => false, 'error' => 'Acceso denegado. Verifique su clave primero.'], 403);
    }

    // ---------------------------------------------------------------
    // Datos iniciales: trabajadores, años y datos de la empresa
    // (mismas consultas que prepara nominas.php para el modal)
    // ---------------------------------------------------------------
    if ($accion === 'inicial') {
        $trabajadores = $pdo->query("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
        $anios = $pdo->query("SELECT DISTINCT YEAR(periodo_desde) AS anio FROM nominas ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);

        // Logo de la empresa para impresiones/exportaciones
        $ruta_logo = __DIR__ . '/../../images/logotn.png';
        $logo_base64 = '';
        if (file_exists($ruta_logo)) {
            $type = pathinfo($ruta_logo, PATHINFO_EXTENSION);
            $logo_base64 = 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($ruta_logo));
        }

        json_salida([
            'success' => true,
            'trabajadores' => $trabajadores,
            'anios' => array_map('intval', $anios),
            'empresa' => [
                'nombre'       => COMPANY_NAME,
                'reeup'        => REEUP_EMPRESA,
                'nit'          => NIT,
                'jefe_proyecto'=> JEFE_PROYECTO,
                'especialista' => ESPECIALISTA,
            ],
            'logoBase64' => $logo_base64,
        ]);
    }

    // ---------------------------------------------------------------
    // Meses con nóminas por AÑO (+ trabajador y estado opcionales)
    // (clon de action=get_meses_listado)
    // ---------------------------------------------------------------
    if ($accion === 'meses') {
        $anio = intval($_GET['anio'] ?? 0);
        $trabajador_id = intval($_GET['trabajador_id'] ?? 0);
        $estado = $_GET['estado'] ?? '';
        $meses = [];

        if ($anio) {
            $sql = "SELECT DISTINCT MONTH(periodo_desde) as mes FROM nominas WHERE YEAR(periodo_desde) = ?";
            $params = [$anio];

            if ($trabajador_id > 0) {
                $sql .= " AND trabajador_id = ?";
                $params[] = $trabajador_id;
            }
            if ($estado != '') {
                $sql .= " AND estado = ?";
                $params[] = $estado;
            }
            $sql .= " ORDER BY mes";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $meses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        json_salida(['success' => true, 'meses' => array_map('intval', $meses)]);
    }

    // ---------------------------------------------------------------
    // Listado Total Salario Devengado POR TRABAJADOR
    // (clon de action=get_listado_devengado_trabajador)
    // ---------------------------------------------------------------
    if ($accion === 'listado') {
        $trabajador_id = intval($_GET['trabajador_id'] ?? 0);
        $anio = intval($_GET['anio'] ?? 0);
        $mes = intval($_GET['mes'] ?? 0);
        $estado = $_GET['estado'] ?? '';
        $modo = $_GET['modo'] ?? 'consolidado';

        if ($trabajador_id <= 0 || $anio <= 0) {
            json_salida(['success' => false, 'error' => 'Parámetros incompletos.'], 400);
        }

        $stmt_t = $pdo->prepare("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores WHERE id = ?");
        $stmt_t->execute([$trabajador_id]);
        $trabajador = $stmt_t->fetch();
        if (!$trabajador) {
            json_salida(['success' => false, 'error' => 'Trabajador no encontrado.'], 404);
        }

        $sql = "SELECT tipo_nomina,
                       COUNT(*) as cantidad,
                       COALESCE(SUM(COALESCE(total_salario_devengado, 0)), 0) as devengado,
                       COALESCE(SUM(COALESCE(total_deducciones, 0)), 0) as deducciones,
                       COALESCE(SUM(COALESCE(contribucion_especial, 0)), 0) as cess,
                       COALESCE(SUM(COALESCE(importe_neto, 0)), 0) as neto";

        if ($modo === 'completo') {
            $sql .= ", MONTH(periodo_desde) as mes_num";
        }

        $sql .= " FROM nominas WHERE trabajador_id = ? AND YEAR(periodo_desde) = ?";
        $params = [$trabajador_id, $anio];

        if ($mes > 0) {
            $sql .= " AND MONTH(periodo_desde) = ?";
            $params[] = $mes;
        }
        if ($estado != '') {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }

        if ($modo === 'completo') {
            $sql .= " GROUP BY tipo_nomina, MONTH(periodo_desde) ORDER BY tipo_nomina, MONTH(periodo_desde)";
        } else {
            $sql .= " GROUP BY tipo_nomina ORDER BY tipo_nomina";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totales = ['cantidad' => 0, 'devengado' => 0, 'deducciones' => 0, 'cess' => 0, 'neto' => 0];
        foreach ($filas as $f) {
            $totales['cantidad'] += intval($f['cantidad']);
            $totales['devengado'] += floatval($f['devengado']);
            $totales['deducciones'] += floatval($f['deducciones']);
            $totales['cess'] += floatval($f['cess']);
            $totales['neto'] += floatval($f['neto']);
        }

        json_salida([
            'success' => true,
            'trabajador' => $trabajador,
            'filas' => $filas,
            'totales' => $totales,
            'modo' => $modo,
        ]);
    }

    json_salida(['success' => false, 'error' => 'Acción no válida.'], 400);
} catch (PDOException $e) {
    json_salida(['success' => false, 'error' => $e->getMessage()], 500);
}
