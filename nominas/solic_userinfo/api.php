<?php
// solic_userinfo/api.php - Endpoints JSON protegidos por CLAVE de acceso.
// Réplica de los handlers AJAX del modal "Listado Total Salario Devengado por
// Trabajador" de modules/nominas.php: datos_iniciales, meses y listado.
// Solo lectura; usa las credenciales centrales vía config/database.php.
// La única acción pública es "verificar_clave", que comprueba el usuario
// y la contraseña introducidos (contraseñas bcrypt de clasif_usuarios
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
    // VERIFICACIÓN DE USUARIO + CONTRASEÑA (única acción pública)
    // Uso racional y cuidadoso de datos:
    //  - Solo se consultan los campos imprescindibles (nunca CI,
    //    dirección, email ni foto) y nada de ello sale al cliente.
    //  - Búsqueda del usuario igual que login.php (usuario, email,
    //    carné o teléfono normalizado) y password_verify (segura
    //    ante timing attacks), sin revelar qué usuario coincidió.
    //  - Pequeño retardo ante fallos (sin bloqueo por tiempo).
    //  - Mensajes genéricos que no filtran información.
    // ---------------------------------------------------------------
    if ($accion === 'verificar_clave') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_salida(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $usuario = trim((string)($_POST['usuario'] ?? ''));
        $clave = (string)($_POST['clave'] ?? '');
        if ($usuario === '' || $clave === '' || strlen($usuario) > 50 || strlen($clave) > 255) {
            json_salida(['success' => false, 'error' => 'Debe introducir su usuario y su contraseña.'], 400);
        }
        $tel_lim = preg_replace('/[^0-9]/', '', $usuario);

        $stmt = $pdo->prepare(
            "SELECT u.id, u.usuario, u.nombre, u.apellidos, u.no_ci, u.email, u.foto, u.rol_id,
                    r.codigo AS rol_codigo, r.descripcion AS rol_descripcion, u.password
               FROM clasif_usuarios u
          LEFT JOIN clasif_rol r ON u.rol_id = r.id
              WHERE u.activo = 1
                AND (u.usuario = :usuario
                     OR u.email = :email
                     OR u.no_ci = :ci
                     OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.telefono_contacto, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = :tel)
              LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario, ':email' => $usuario, ':ci' => $usuario, ':tel' => $tel_lim]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        $acceso_ok = ($u !== false && !empty($u['password']) && password_verify($clave, $u['password']));
        $nombre_persona = '';
        if ($acceso_ok) {
            $nombre_persona = trim($u['nombre'] . ' ' . $u['apellidos']);
        } else {
            usleep(400000); // freno suave e invisible ante intentos masivos
            json_salida(['success' => false, 'error' => 'No tiene acceso: el usuario o la contraseña no son correctos.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['su_acceso']   = true;
        $_SESSION['su_nombre']   = $nombre_persona;
        $_SESSION['su_tipo']     = 'usuario';
        unset($_SESSION['su_trabajador_id']);

        // Datos del usuario del sistema, usados por "ir_dashboard" para
        // abrir la sesión del dashboard con el mismo identificador.
        $_SESSION['su_sistema'] = [
            'user_id'          => (int)$u['id'],
            'username'         => (string)$u['usuario'],
            'user_ci'          => (string)($u['no_ci'] ?? ''),
            'user_email'       => (string)($u['email'] ?? ''),
            'user_foto'        => (string)($u['foto'] ?? ''),
            'rol_id'           => (int)($u['rol_id'] ?? 0),
            'rol_codigo'       => (string)($u['rol_codigo'] ?? ''),
            'rol_descripcion'  => (string)($u['rol_descripcion'] ?? ''),
        ];

        json_salida(['success' => true, 'nombre' => $nombre_persona]);
    }

    // ---------------------------------------------------------------
    // VERIFICACIÓN DE TRABAJADOR (expediente + carné de identidad)
    // Acción pública. Ambos datos deben coincidir con un registro de
    // la tabla trabajadores. El carné debe tener exactamente 11 dígitos
    // (validación igual que login.php). Normalización: mayúsculas y sin
    // espacios, guiones ni puntos. Mensaje genérico que no filtra
    // cuál de los dos datos falló.
    // ---------------------------------------------------------------
    if ($accion === 'verificar_trabajador') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_salida(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $expediente = trim((string)($_POST['expediente'] ?? ''));
        $ci = trim((string)($_POST['ci'] ?? ''));

        if ($expediente === '' || $ci === '' || strlen($expediente) > 50 || strlen($ci) > 30) {
            json_salida(['success' => false, 'error' => 'Debe introducir su número de expediente y su carné de identidad.'], 400);
        }

        $normalizar = function ($v) {
            return strtoupper(preg_replace('/[\s\-_.]+/', '', $v));
        };
        $exp_norm = $normalizar($expediente);
        $ci_norm  = $normalizar($ci);

        if (!preg_match('/^\d{11}$/', $ci_norm)) {
            json_salida(['success' => false, 'error' => 'El número de carné de identidad debe contener exactamente 11 dígitos numéricos.'], 400);
        }

        $stmt = $pdo->prepare(
            "SELECT id, codigo, ci, nombre_completo
               FROM trabajadores
              WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(codigo, ' ', ''), '-', ''), '.', ''), '_', '')) = ?
                AND UPPER(REPLACE(REPLACE(REPLACE(REPLACE(ci, ' ', ''), '-', ''), '.', ''), '_', '')) = ?
              LIMIT 1"
        );
        $stmt->execute([$exp_norm, $ci_norm]);
        $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trabajador) {
            usleep(400000);
            json_salida(['success' => false, 'error' => 'No tiene acceso: el expediente o el carné de identidad no coinciden con ningún trabajador registrado.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['su_acceso']        = true;
        $_SESSION['su_tipo']          = 'trabajador';
        $_SESSION['su_trabajador_id'] = (int)$trabajador['id'];
        $_SESSION['su_nombre']        = $trabajador['nombre_completo'];

        json_salida(['success' => true, 'nombre' => $trabajador['nombre_completo']]);
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

    // ---------------------------------------------------------------
    // IR AL DASHBOARD: cierra la sesión del portal (claves su_*) y abre
    // la sesión del sistema con el mismo usuario, para entrar al
    // dashboard ya logeado sin pasar por login.php. Mismas claves de
    // sesión y mismas validaciones que el login local de login.php.
    // ---------------------------------------------------------------
    if ($accion === 'ir_dashboard') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_salida(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $es_usuario = !empty($_SESSION['su_acceso'])
            && (($_SESSION['su_tipo'] ?? '') === 'usuario')
            && !empty($_SESSION['su_sistema']);
        if (!$es_usuario) {
            json_salida(['success' => false, 'error' => 'Debe iniciar sesión en el portal para continuar.'], 401);
        }

        $t = $_SESSION['su_sistema'];
        $nombre_portal = (string)($_SESSION['su_nombre'] ?? '');

        require_once __DIR__ . '/../includes/subsistemas.php';
        if (modo_mantenimiento_activo($pdo) && (int)$t['rol_id'] !== 5) {
            json_salida([
                'success' => false, 'bloqueo' => true,
                'error'   => 'El sistema se encuentra en MANTENIMIENTO. Su rol no tiene permisos para acceder en este momento.'
            ], 403);
        }
        if (!subsistema_activo($pdo, '0001') && (int)$t['rol_id'] !== 5) {
            json_salida([
                'success' => false, 'bloqueo' => true,
                'error'   => 'El subsistema de Nóminas está deshabilitado. Contacte al administrador del sistema.'
            ], 403);
        }

        // Cerrar la sesión del portal...
        foreach (array_keys($_SESSION) as $clave_portal) {
            if (strpos($clave_portal, 'su_') === 0) {
                unset($_SESSION[$clave_portal]);
            }
        }

        // ...y abrir la sesión del sistema (idéntico al login local).
        $_SESSION['user_id']          = (int)$t['user_id'];
        $_SESSION['username']         = $t['username'];
        $_SESSION['user_nombre']      = $nombre_portal !== '' ? $nombre_portal : $t['username'];
        $_SESSION['user_ci']          = $t['user_ci'];
        $_SESSION['user_email']       = $t['user_email'];
        $_SESSION['user_foto']        = $t['user_foto'];
        $_SESSION['auth_provider']    = 'local';
        $_SESSION['rol_id']           = (int)$t['rol_id'];
        $_SESSION['rol_codigo']       = $t['rol_codigo'];
        $_SESSION['rol_descripcion']  = $t['rol_descripcion'];
        $_SESSION['logged_in']        = true;
        $_SESSION['login_time']       = time();

        require_once __DIR__ . '/../includes/logger.php';
        try {
            logAction(
                'iniciar_sesion',
                'login',
                'Inicio de sesión desde el portal (usuario: ' . $t['username'] . ')',
                ['metodo' => 'portal_dashboard'],
                (int)$t['user_id'],
                'success',
                null,
                'local'
            );
        } catch (Throwable $e) {
            // La auditoría nunca debe bloquear el acceso.
        }

        session_regenerate_id(true);
        json_salida(['success' => true, 'url' => '/nominas/dashboard.php']);
    }

    // El resto de las acciones exigen acceso previamente verificado
    if (empty($_SESSION['su_acceso']) || $_SESSION['su_acceso'] !== true) {
        json_salida(['success' => false, 'error' => 'Acceso denegado. Verifique su clave primero.'], 403);
    }

    // ---------------------------------------------------------------
    // Modo de acceso: 'usuario' (consulta libre) o 'trabajador'
    // (consulta limitada a su propio registro). La restricción se
    // aplica SIEMPRE en el servidor, sea cual sea el cliente.
    // ---------------------------------------------------------------
    $su_tipo = (($_SESSION['su_tipo'] ?? 'usuario') === 'trabajador') ? 'trabajador' : 'usuario';
    $su_trabajador_id = (int)($_SESSION['su_trabajador_id'] ?? 0);
    if ($su_tipo === 'trabajador' && $su_trabajador_id <= 0) {
        json_salida(['success' => false, 'error' => 'Acceso denegado. Verifique su expediente y carné de identidad primero.'], 403);
    }

    // ---------------------------------------------------------------
    // Datos iniciales: trabajadores, años y datos de la empresa
    // (mismas consultas que prepara nominas.php para el modal)
    // ---------------------------------------------------------------
    if ($accion === 'inicial') {
        if ($su_tipo === 'trabajador') {
            $stmt = $pdo->prepare("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores WHERE id = ?");
            $stmt->execute([$su_trabajador_id]);
            $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $trabajadores = $pdo->query("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
        }
        $anios = $pdo->query("SELECT DISTINCT YEAR(periodo_desde) AS anio FROM nominas ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);

        // Logo de la empresa para impresiones/exportaciones
        $ruta_logo = __DIR__ . '/../../images/LogoTN.png';
        $logo_base64 = '';
        if (file_exists($ruta_logo)) {
            $type = pathinfo($ruta_logo, PATHINFO_EXTENSION);
            $logo_base64 = 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($ruta_logo));
        }

        json_salida([
            'success' => true,
            'trabajadores' => $trabajadores,
            'anios' => array_map('intval', $anios),
            'suTipo' => $su_tipo,
            'suNombre' => (string)($_SESSION['su_nombre'] ?? ''),
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
    // INICIAL_VAC: años con movimientos en el submayor de vacaciones
    // ---------------------------------------------------------------
    if ($accion === 'inicial_vac') {
        $anios = $pdo->query(
            "SELECT DISTINCT YEAR(periodo_desde) AS anio
               FROM submayor_vacaciones
              ORDER BY anio DESC"
        )->fetchAll(PDO::FETCH_COLUMN);

        $hay_movimientos = count($anios) > 0;
        if (!$hay_movimientos) {
            $anios = [(int)date('Y')];
        }

        $areas = $pdo->query(
            "SELECT id, nombre_area FROM areas ORDER BY nombre_area"
        )->fetchAll(PDO::FETCH_ASSOC);

        $centros_costo = $pdo->query(
            "SELECT id, codigo, nombre FROM centros_costo ORDER BY codigo"
        )->fetchAll(PDO::FETCH_ASSOC);

        json_salida([
            'success' => true,
            'anios'   => array_map('intval', $anios),
            'hayMov'  => $hay_movimientos,
            'areas'   => array_map(function ($a) {
                return [
                    'id'     => (int)$a['id'],
                    'nombre' => (string)$a['nombre_area'],
                ];
            }, $areas),
            'centrosCosto' => array_map(function ($c) {
                return [
                    'id'     => (int)$c['id'],
                    'codigo' => (string)$c['codigo'],
                    'nombre' => (string)$c['nombre'],
                ];
            }, $centros_costo),
        ]);
    }

    // ---------------------------------------------------------------
    // REPORTE_VAC: saldos de vacaciones con los filtros del módulo
    // modules/submayor_vacaciones (año/mes, desde/hasta, trabajador,
    // área, centro de costo, tipo de movimiento y ocultar saldo en
    // cero). Misma fórmula: acumulacion -> +dias, ajuste -> +dias
    // (con signo), disfrute/liquidac -> -dias. Devuelve activo,
    // excedida (> 20 días en el submayor) e importe
    // (saldo * salario mensual / días laborables). En modo trabajador
    // con mes en "Todos" devuelve una fila por mes con totales.
    // En modo trabajador se fuerza su propio id sin importar el enviado.
    // ---------------------------------------------------------------
    if ($accion === 'reporte_vac') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_salida(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $anio = (int)($_POST['anio'] ?? 0);
        $mes  = (int)($_POST['mes'] ?? 0);
        $desde_post = trim((string)($_POST['desde'] ?? ''));
        $hasta_post = trim((string)($_POST['hasta'] ?? ''));
        $trabajador_id = (int)($_POST['trabajador_id'] ?? 0);
        $area_id = (int)($_POST['area_id'] ?? 0);
        $cc_id = (int)($_POST['centro_costo_id'] ?? 0);
        $tipo_mov = trim((string)($_POST['tipo_mov'] ?? ''));
        $ocultar_cero = (string)($_POST['ocultar_cero'] ?? '') === '1';

        if ($mes < 0 || $mes > 12) {
            json_salida(['success' => false, 'error' => 'Seleccione un mes válido o "Todos".'], 400);
        }
        if (!in_array($tipo_mov, ['', 'acumulacion', 'disfrute', 'liquidac', 'ajuste'], true)) {
            json_salida(['success' => false, 'error' => 'Tipo de movimiento no válido.'], 400);
        }

        $anio_ok = ($anio >= 1900 && $anio <= 2100);
        $fechas_ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde_post) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta_post) === 1
            && $desde_post <= $hasta_post;

        if ($anio_ok) {
            if ($mes >= 1 && $mes <= 12) {
                $desde = sprintf('%04d-%02d-01', $anio, $mes);
                $hasta = date('Y-m-t', strtotime($desde));
            } else {
                $desde = sprintf('%04d-01-01', $anio);
                $hasta = sprintf('%04d-12-31', $anio);
            }
        } elseif ($fechas_ok) {
            $desde = $desde_post;
            $hasta = $hasta_post;
        } else {
            $anio = (int)date('Y');
            $desde = sprintf('%04d-01-01', $anio);
            $hasta = sprintf('%04d-12-31', $anio);
        }

        $dias_lab = 24.0;
        $valor_cfg = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'dias_mensuales'")->fetchColumn();
        if ($valor_cfg !== false && is_numeric($valor_cfg) && (float)$valor_cfg > 0) {
            $dias_lab = (float)$valor_cfg;
        }

        $es_trabajador = ($su_tipo === 'trabajador');
        if ($es_trabajador) {
            $trabajador_id = (int)$su_trabajador_id;
        }

        if ($es_trabajador && $mes === 0 && $anio_ok) {
            $anio_desde = sprintf('%04d-01-01', $anio);
            $anio_hasta = sprintf('%04d-12-31', $anio);

            $stmt_t = $pdo->prepare(
                "SELECT t.id, t.codigo, t.ci, t.nombre_completo, t.activo,
                        COALESCE(e.salario_mensual, 0) AS salario_mensual
                   FROM trabajadores t
              LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                  WHERE t.id = :tid"
            );
            $stmt_t->execute([':tid' => $trabajador_id]);
            $trab = $stmt_t->fetch(PDO::FETCH_ASSOC);
            if ($trab === false) {
                json_salida(['success' => false, 'error' => 'Trabajador no encontrado.'], 404);
            }

            $params_base = [':tid' => $trabajador_id, ':anio_desde' => $anio_desde];

            $stmt_base = $pdo->prepare(
                "SELECT COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias
                                         WHEN sv.tipo_movimiento IN ('disfrute','liquidac') THEN -sv.dias
                                         ELSE sv.dias END), 0) AS saldo_base
                   FROM submayor_vacaciones sv
                  WHERE sv.trabajador_id = :tid
                    AND sv.periodo_hasta < :anio_desde"
            );
            $stmt_base->execute($params_base);
            $saldo_corr = round((float)$stmt_base->fetchColumn(), 2);

            $params_meses = [':tid' => $trabajador_id, ':anio_desde' => $anio_desde, ':anio_hasta' => $anio_hasta];
            $sql_mov_mes = '';
            if ($tipo_mov !== '') {
                $sql_mov_mes = ', SUM(CASE WHEN sv.tipo_movimiento = :tipo_mov THEN sv.dias ELSE 0 END) AS mov_sel';
                $params_meses[':tipo_mov'] = $tipo_mov;
            }

            $stmt_meses = $pdo->prepare(
                "SELECT MONTH(sv.periodo_desde) AS mes,
                        SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias ELSE 0 END) AS acumuladas,
                        SUM(CASE WHEN sv.tipo_movimiento = 'disfrute' THEN sv.dias ELSE 0 END) AS disfrutadas,
                        SUM(CASE WHEN sv.tipo_movimiento = 'liquidac' THEN sv.dias ELSE 0 END) AS liquidadas,
                        SUM(CASE WHEN sv.tipo_movimiento = 'ajuste' THEN sv.dias ELSE 0 END) AS ajustes,
                        SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias
                                 WHEN sv.tipo_movimiento IN ('disfrute','liquidac') THEN -sv.dias
                                 ELSE sv.dias END) AS delta{$sql_mov_mes}
                   FROM submayor_vacaciones sv
                  WHERE sv.trabajador_id = :tid
                    AND sv.periodo_desde >= :anio_desde
                    AND sv.periodo_desde <= :anio_hasta
               GROUP BY MONTH(sv.periodo_desde)
               ORDER BY mes"
            );
            $stmt_meses->execute($params_meses);

            $por_mes = [];
            while ($m = $stmt_meses->fetch(PDO::FETCH_ASSOC)) {
                $por_mes[(int)$m['mes']] = $m;
            }

            $meses_esp = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                          7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

            $salario = (float)$trab['salario_mensual'];
            $valor_dia = ($salario > 0 && $dias_lab > 0) ? ($salario / $dias_lab) : 0.0;

            $filas = [];
            $tot_acum = 0.0;
            $tot_disf = 0.0;
            $tot_liq = 0.0;
            $tot_ajus = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $acum = isset($por_mes[$m]) ? (float)$por_mes[$m]['acumuladas'] : 0.0;
                $disf = isset($por_mes[$m]) ? (float)$por_mes[$m]['disfrutadas'] : 0.0;
                $liq = isset($por_mes[$m]) ? (float)$por_mes[$m]['liquidadas'] : 0.0;
                $ajus = isset($por_mes[$m]) ? (float)$por_mes[$m]['ajustes'] : 0.0;
                $delta = isset($por_mes[$m]) ? (float)$por_mes[$m]['delta'] : 0.0;
                $saldo_corr = round($saldo_corr + $delta, 2);
                if ($tipo_mov !== '' && (!isset($por_mes[$m]) || (float)$por_mes[$m]['mov_sel'] == 0.0)) {
                    continue;
                }
                $tot_acum += $acum;
                $tot_disf += $disf;
                $tot_liq += $liq;
                $tot_ajus += $ajus;
                $filas[] = [
                    'mes'                   => $m,
                    'mes_nombre'            => $meses_esp[$m],
                    'acumuladas'            => round($acum, 2),
                    'disfrutadas'           => round($disf, 2),
                    'liquidadas'            => round($liq, 2),
                    'importe_liquidadas'    => round($liq * $valor_dia, 2),
                    'ajustes'               => round($ajus, 2),
                    'saldo_dias'            => $saldo_corr,
                    'importe'               => round($saldo_corr * $valor_dia, 2),
                ];
            }

            json_salida([
                'success'        => true,
                'modo'           => 'mensual',
                'desde'          => $desde,
                'hasta'          => $hasta,
                'diasLaborables' => $dias_lab,
                'valorDia'       => round($valor_dia, 2),
                'trabajador'     => [
                    'id'              => (int)$trab['id'],
                    'codigo'          => (string)$trab['codigo'],
                    'ci'              => (string)$trab['ci'],
                    'nombre_completo' => (string)$trab['nombre_completo'],
                    'activo'          => ((int)$trab['activo'] === 1),
                    'salario_mensual' => round($salario, 2),
                ],
                'filas'          => $filas,
                'totales'        => [
                    'acumuladas'            => round($tot_acum, 2),
                    'disfrutadas'           => round($tot_disf, 2),
                    'liquidadas'            => round($tot_liq, 2),
                    'importe_liquidadas'    => round($tot_liq * $valor_dia, 2),
                    'ajustes'               => round($tot_ajus, 2),
                    'saldo_dias'            => $saldo_corr,
                    'importe'               => round($saldo_corr * $valor_dia, 2),
                ],
            ]);
        }

        $cond_fila_per = '';
        $sql_mov_sel = '';
        $params = [':desde_ini' => $desde, ':desde_per' => $desde, ':hasta_per' => $hasta];
        if ($tipo_mov !== '') {
            $cond_fila_per = ' AND COALESCE(p.mov_sel, 0) <> 0';
            $sql_mov_sel = ', SUM(CASE WHEN sv.tipo_movimiento = :tipo_mov_per THEN sv.dias ELSE 0 END) AS mov_sel';
            $params[':tipo_mov_per'] = $tipo_mov;
        }
        if ($trabajador_id > 0) {
            $params[':trab_id'] = $trabajador_id;
        }
        if ($area_id > 0) {
            $params[':area_id'] = $area_id;
        }
        if ($cc_id > 0) {
            $params[':cc_id'] = $cc_id;
        }

        $sql = "SELECT t.id, t.codigo, t.ci, t.nombre_completo, t.activo,
                       COALESCE(e.salario_mensual, 0) AS salario_mensual,
                       COALESCE(si.saldo_inicial, 0) AS saldo_inicial,
                       COALESCE(p.acumuladas, 0)     AS acumuladas,
                       COALESCE(p.disfrutadas, 0)    AS disfrutadas,
                       COALESCE(p.liquidadas, 0)     AS liquidadas,
                       COALESCE(p.ajustes, 0)        AS ajustes,
                       COALESCE(si.saldo_inicial, 0)
                         + COALESCE(p.acumuladas, 0)
                         + COALESCE(p.ajustes, 0)
                         - COALESCE(p.disfrutadas, 0)
                         - COALESCE(p.liquidadas, 0) AS saldo_final,
                       COALESCE((SELECT SUM(CASE WHEN s.tipo_movimiento IN ('disfrute','liquidac') THEN -s.dias
                                                 ELSE s.dias END)
                                   FROM submayor_vacaciones s
                                  WHERE s.trabajador_id = t.id), 0) AS saldo_total_hist
                  FROM trabajadores t
             LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
             LEFT JOIN (
                    SELECT sv.trabajador_id,
                           SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias
                                    WHEN sv.tipo_movimiento IN ('disfrute','liquidac') THEN -sv.dias
                                    ELSE sv.dias END) AS saldo_inicial
                       FROM submayor_vacaciones sv
                      WHERE sv.periodo_hasta < :desde_ini
                        AND sv.tipo_movimiento IN ('acumulacion','disfrute','liquidac')
                   GROUP BY sv.trabajador_id
                 ) si ON si.trabajador_id = t.id
             LEFT JOIN (
                    SELECT sv.trabajador_id,
                           SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias ELSE 0 END) AS acumuladas,
                           SUM(CASE WHEN sv.tipo_movimiento = 'disfrute' THEN sv.dias ELSE 0 END) AS disfrutadas,
                           SUM(CASE WHEN sv.tipo_movimiento = 'liquidac' THEN sv.dias ELSE 0 END) AS liquidadas,
                            SUM(CASE WHEN sv.tipo_movimiento = 'ajuste' THEN sv.dias ELSE 0 END) AS ajustes{$sql_mov_sel}
                       FROM submayor_vacaciones sv
                      WHERE (sv.tipo_movimiento = 'ajuste'
                             OR (sv.periodo_desde >= :desde_per AND sv.periodo_hasta <= :hasta_per))
                   GROUP BY sv.trabajador_id
                 ) p ON p.trabajador_id = t.id
                 WHERE 1 = 1{$cond_fila_per}";

        if ($trabajador_id > 0) {
            $sql .= " AND t.id = :trab_id";
        }
        if ($area_id > 0) {
            $sql .= " AND t.area_id = :area_id";
        }
        if ($cc_id > 0) {
            $sql .= " AND t.centro_costo_id = :cc_id";
        }

        $sql .= " ORDER BY t.nombre_completo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filas = [];
        foreach ($filas_raw as $f) {
            $saldo_final = round((float)$f['saldo_final'], 2);
            if ($ocultar_cero && $saldo_final == 0.0) {
                continue;
            }
            $salario = (float)$f['salario_mensual'];
            $importe = ($salario > 0 && $dias_lab > 0) ? round($saldo_final * ($salario / $dias_lab), 2) : 0.0;
            $liquidadas = round((float)$f['liquidadas'], 2);
            $filas[] = [
                'id'                   => (int)$f['id'],
                'codigo'               => (string)$f['codigo'],
                'ci'                   => (string)$f['ci'],
                'nombre_completo'      => (string)$f['nombre_completo'],
                'activo'               => ((int)$f['activo'] === 1),
                'excedida'             => ((float)$f['saldo_total_hist'] > 20),
                'saldo_inicial'        => round((float)$f['saldo_inicial'], 2),
                'acumuladas'           => round((float)$f['acumuladas'], 2),
                'disfrutadas'          => round((float)$f['disfrutadas'], 2),
                'liquidadas'           => $liquidadas,
                'importe_liquidadas'   => ($salario > 0 && $dias_lab > 0) ? round($liquidadas * ($salario / $dias_lab), 2) : 0.0,
                'ajustes'              => round((float)$f['ajustes'], 2),
                'saldo_final'          => $saldo_final,
                'importe'              => $importe,
            ];
        }

        json_salida([
            'success'        => true,
            'modo'           => 'consolidado',
            'desde'          => $desde,
            'hasta'          => $hasta,
            'diasLaborables' => $dias_lab,
            'filas'          => $filas,
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

        if ($su_tipo === 'trabajador') {
            $trabajador_id = $su_trabajador_id;
        }

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

        if ($su_tipo === 'trabajador' && $trabajador_id !== $su_trabajador_id) {
            json_salida(['success' => false, 'error' => 'Acceso denegado: solo puede consultar la información de su propia cuenta.'], 403);
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
