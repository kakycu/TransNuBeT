<?php
/**
 * snc125_reporte.php - Modelo oficial SC-4-09 (SNC 1-25)
 * "Certificación de Años de Servicios y Salarios Devengados".
 *
 * Con los datos del trabajador (expediente, plaza, entidad, dirección, fechas
 * de alta y baja, días trabajados y los salarios devengados de los últimos 10
 * años naturales) arma el formulario y lo sirve como vista previa, Excel,
 * Word o PDF por impresión.
 *
 * El modelo se emite en papel carta vertical, Arial 11, tal como el formulario
 * en blanco de la tarjeta de Modelos.
 */

/** Carga el autoload de las librerías de Office. */
function snc125_autoload(): bool
{
    $vendor = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendor)) {
        return false;
    }
    require_once $vendor;
    return true;
}

/** Fecha dd/mm/aaaa o '—'. */
function snc125_fecha_txt(?string $fecha): string
{
    $fecha = trim((string)$fecha);
    if ($fecha === '' || strpos($fecha, '0000-00-00') === 0) {
        return '—';
    }
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** Nombre del mes en minúsculas (1 = enero). */
function snc125_mes_nombre(int $mes): string
{
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $meses[$mes] ?? '';
}

/** Importe con separador de miles y dos decimales. */
function snc125_importe(float $importe): string
{
    return number_format($importe, 2, '.', ',');
}

/** Valor del dato o una línea de guiones bajos cuando está vacío. */
function snc125_rellenar(string $valor, int $guiones): string
{
    $valor = trim($valor);
    return $valor !== '' ? $valor : str_repeat('_', $guiones);
}

/**
 * Datos del trabajador, sus años de servicio y el salario devengado en cada
 * año natural. El modelo los reparte en hojas de 10 años (una por década).
 *
 * Los días trabajados se calculan igual que en el modelo SC-4-08: horas
 * laboradas de la nómina automática / 8, usando las demás nóminas del mes
 * solo como respaldo cuando no hay automática.
 */
function snc125_datos(PDO $pdo, int $id, string $corte = ''): ?array
{
    $st = $pdo->prepare(
        "SELECT t.id, t.codigo, t.ci, t.nombres, t.primer_apellido, t.segundo_apellido,
                t.nombre_completo, t.fecha_alta, t.fecha_baja,
                cp.nombre_cargo, cc.codigo AS c_costo_codigo, cc.nombre AS c_costo_nombre,
                a.nombre_area, co.nombre AS categoria_ocupacional
           FROM trabajadores t
           LEFT JOIN cargos_plantilla cp ON cp.id = t.cargo_id
           LEFT JOIN centros_costo cc ON cc.id = t.centro_costo_id
           LEFT JOIN areas a ON a.id = t.area_id
           LEFT JOIN categorias_ocupacionales co ON co.id = t.categoria_ocupacional_id
          WHERE t.id = ?"
    );
    $st->execute([$id]);
    $trabajador = $st->fetch(PDO::FETCH_ASSOC);
    if (!$trabajador) {
        return null;
    }

    $sql = "SELECT periodo_hasta, horas_laboradas, total_salario_devengado, tipo_nomina
              FROM nominas
             WHERE trabajador_id = ? AND total_salario_devengado > 0";
    $params = [$id];
    if ($corte !== '') {
        $sql .= " AND periodo_hasta <= ?";
        $params[] = $corte;
    }
    $sql .= " ORDER BY periodo_hasta";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $meses = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $ph = substr((string)$n['periodo_hasta'], 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ph, $m)) {
            continue;
        }
        $anio = (int)$m[1];
        $mes  = (int)$m[2];
        if (!isset($meses[$anio][$mes])) {
            $meses[$anio][$mes] = ['dias' => 0.0, 'salario' => 0.0, 'dias_max' => 0.0];
        }
        $meses[$anio][$mes]['salario'] += (float)$n['total_salario_devengado'];

        $horas = (float)$n['horas_laboradas'];
        $dias = $horas > 0 ? round($horas / 8, 2) : 0.0;
        if ($dias > $meses[$anio][$mes]['dias_max']) {
            $meses[$anio][$mes]['dias_max'] = $dias;
        }
        if (($n['tipo_nomina'] ?? '') === 'automatica') {
            $meses[$anio][$mes]['dias'] += $dias;
        }
    }
    foreach ($meses as $anio => $filas) {
        foreach ($filas as $mes => $valores) {
            if ($valores['dias'] <= 0 && $valores['dias_max'] > 0) {
                $meses[$anio][$mes]['dias'] = $valores['dias_max'];
            }
            unset($meses[$anio][$mes]['dias_max']);
        }
    }
    ksort($meses);

    // Año de la certificación: el del corte o el actual.
    $anioCert = $corte !== '' ? (int)substr($corte, 0, 4) : (int)date('Y');

    // Días trabajados en total.
    $diasTotales = 0.0;
    $salarioPorAnio = [];
    foreach ($meses as $anio => $filas) {
        foreach ($filas as $valores) {
            $diasTotales += (float)$valores['dias'];
            if (!isset($salarioPorAnio[$anio])) {
                $salarioPorAnio[$anio] = 0.0;
            }
            $salarioPorAnio[$anio] += (float)$valores['salario'];
        }
    }

    // Años de servicio cumplidos a la fecha de corte. El modelo dedica una hoja
    // a cada década trabajada: 10 años = 1 hoja, 20 años = 2 hojas, etc.
    $anioAlta = !empty($trabajador['fecha_alta']) ? (int)substr((string)$trabajador['fecha_alta'], 0, 4) : $anioCert;
    $fechaCorte = $corte !== '' ? $corte : date('Y-m-d');
    $aniosServicio = 0;
    if (!empty($trabajador['fecha_alta'])) {
        try {
            $alta = new \DateTime(substr((string)$trabajador['fecha_alta'], 0, 10));
            $fin = new \DateTime($fechaCorte);
            if ($fin > $alta) {
                $aniosServicio = $alta->diff($fin)->y;
            }
        } catch (\Exception $e) {
            $aniosServicio = max(0, $anioCert - $anioAlta);
        }
    }
    $paginas = max(1, (int)ceil($aniosServicio / 10));
    // Cada hoja lleva 10 años naturales (dos columnas de cinco).
    $desde = max($anioAlta, $anioCert - ($paginas * 10) + 1);
    $anios = [];
    for ($anio = $desde; $anio <= $anioCert; $anio++) {
        $anios[] = [
            'anio'   => $anio,
            'importe' => round($salarioPorAnio[$anio] ?? 0.0, 2),
        ];
    }

    return [
        'trabajador' => $trabajador,
        'anios'      => $anios,
        'dias'       => $diasTotales,
        'anio_cert'  => $anioCert,
        'anios_servicio' => $aniosServicio,
        'paginas'    => $paginas,
    ];
}

/**
 * Parte la dirección de la entidad en calle, número, municipio y provincia.
 * "Calle Martí No. 28. Reparto Pastelillo. Nuevitas" → calle + No. + resto.
 *
 * @return array{calle:string,numero:string,municipio:string,provincia:string}
 */
function snc125_partir_direccion(string $direccion): array
{
    $direccion = trim(preg_replace('/\s+/u', ' ', $direccion));
    if ($direccion === '') {
        return ['calle' => '', 'numero' => '', 'municipio' => '', 'provincia' => ''];
    }

    $calle = $direccion;
    $numero = '';
    $resto = '';
    if (preg_match('/^(.*?)\bNo\.?\s*([0-9A-Za-z\-]+)\s*[.,]?\s*(.*)$/iu', $direccion, $m)) {
        $calle = trim($m[1]);
        $numero = trim($m[2]);
        $resto = trim($m[3], " .,");
    }

    // "Calle Martí No. 28. Reparto Pastelillo. Nuevitas, Camagüey": la calle
    // va sin la palabra Calle, el municipio es el último tramo antes de la
    // provincia y la provincia lo que sigue a la coma.
    $calle = trim(preg_replace('/^(calle|avenida|ave\.|carretera|ctra\.)\s+/iu', '', $calle));

    $municipio = $resto;
    $provincia = '';
    if ($resto !== '' && strpos($resto, ',') !== false) {
        $partes = array_map('trim', explode(',', $resto));
        $municipio = array_shift($partes);
        $provincia = implode(', ', array_filter($partes));
    }
    if ($municipio !== '' && strpos($municipio, '.') !== false) {
        $tramos = array_values(array_filter(array_map('trim', explode('.', $municipio)), 'strlen'));
        if ($tramos) {
            $municipio = (string)end($tramos);
        }
    }

    return ['calle' => $calle, 'numero' => $numero, 'municipio' => $municipio, 'provincia' => $provincia];
}

/**
 * Campos del formulario ya resueltos, para pintarlos en cualquier formato.
 */
function snc125_campos(array $res, array $config, string $obs = '', string $fecha = ''): array
{
    $t = $res['trabajador'];

    // Fecha de expedición: si no se indica, el pie del modelo queda en blanco
    // igual que en el modelo oficial.
    $expedicion = false;
    $dia = '';
    $mes = '';
    $anio = '';
    if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $ts = strtotime($fecha);
        if ($ts) {
            $expedicion = true;
            $dia = (string)(int)date('j', $ts);
            $mes = snc125_mes_nombre((int)date('n', $ts));
            $anio = date('Y', $ts);
        }
    }

    $alta = !empty($t['fecha_alta']) && strpos((string)$t['fecha_alta'], '0000-00-00') !== 0
        ? strtotime((string)$t['fecha_alta']) : 0;
    $baja = !empty($t['fecha_baja']) && strpos((string)$t['fecha_baja'], '0000-00-00') !== 0
        ? strtotime((string)$t['fecha_baja']) : 0;

    $direccion = snc125_partir_direccion((string)($config['direccion_empresa'] ?? ''));
    $nombre = trim((string)($t['nombre_completo'] ?? ''));
    if ($nombre === '') {
        $nombre = trim(($t['nombres'] ?? '') . ' ' . ($t['primer_apellido'] ?? '') . ' ' . ($t['segundo_apellido'] ?? ''));
    }

    $plaza = trim((string)($t['nombre_cargo'] ?? ''));
    if ($plaza === '') {
        $plaza = trim((string)($t['categoria_ocupacional'] ?? ''));
    }

    // Nombre de la entidad tal como va en el modelo: solo el nombre, sin el NIT
    // (con el NIT la línea no cabe y el nombre quedaba partido).
    $entidad = trim((string)($config['nombre_empresa'] ?? ''));

    return [
        'nombre'    => $nombre,
        'ci'        => trim((string)($t['ci'] ?? '')),
        'plaza'     => $plaza,
        'entidad'   => $entidad,
        'calle'     => $direccion['calle'],
        'numero'    => $direccion['numero'],
        'municipio' => $direccion['municipio'],
        'provincia' => $direccion['provincia'],
        'desde_mes' => $alta ? snc125_mes_nombre((int)date('n', $alta)) : '',
        'desde_anio' => $alta ? date('Y', $alta) : '',
        'hasta_mes' => $baja ? snc125_mes_nombre((int)date('n', $baja)) : '',
        'hasta_anio' => $baja ? date('Y', $baja) : '',
        'dias'      => (int)round((float)$res['dias']),
        'ciudad'    => trim((string)($config['ciudad'] ?? $config['municipio_empresa'] ?? '')),
        'fecha_exp' => $expedicion ? $fecha : '',
        'dia'       => $dia,
        'mes'       => $mes,
        'anio'      => $anio,
        'obs'       => $obs,
        'anios'     => $res['anios'],
        'paginas'   => (int)($res['paginas'] ?? 1),
        'jefe'      => trim((string)($config['especialista_gestionRRHH'] ?? '')),
    ];
}

/** Reparte los años en los dos bloques de cinco del formulario. */
function snc125_parejas_anios(array $anios): array
{
    $anios = array_slice($anios, 0, 10);
    $izquierda = array_slice($anios, 0, 5);
    $derecha = array_slice($anios, 5, 5);
    $filas = [];
    for ($i = 0; $i < 5; $i++) {
        $filas[] = [$izquierda[$i] ?? null, $derecha[$i] ?? null];
    }
    return $filas;
}

/** Nombre del archivo descargable. */
function snc125_nombre_archivo(array $res, string $extension): string
{
    $nombre = trim((string)($res['trabajador']['nombre_completo'] ?? ''));
    if ($nombre === '') {
        $nombre = 'Trabajador';
    }
    $limpiar = static function (string $texto): string {
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', 'ü', 'Ü'],
            ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N', 'u', 'U'], $texto);
        $texto = preg_replace('/[^A-Za-z0-9]+/', '_', $texto);
        return trim($texto, '_');
    };
    return 'SC-4-09_' . $limpiar($nombre) . '_' . date('Y-m-d') . '.' . $extension;
}

/** Mensaje de error con el mismo estilo del módulo. */
function snc125_error(string $mensaje): void
{
    if (ob_get_level()) {
        ob_clean();
    }
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<title>SC-4-09 · Certificación de Años de Servicios y Salarios Devengados (SNC 1-25)</title></head>'
        . '<body style="font-family:Arial,sans-serif;padding:2rem;text-align:center;color:#111;">'
        . '<h3>SC-4-09 · Certificación de Años de Servicios y Salarios Devengados (SNC 1-25)</h3>'
        . '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><button type="button" onclick="window.close()" style="padding:.5rem 1rem;">Cerrar</button></p>'
        . '</body></html>';
    exit;
}

/** Envía el libro como descarga XLSX. */
/**
 * Ancho en twips de un texto en Arial 11.
 *
 * Arial y Helvetica comparten las mismas métricas de avance, así que el valor
 * sirve tanto para el Word (PhpWord/Arial) como para el PDF (jsPDF/Helvetica).
 */
function snc125_ancho_texto(string $texto): float
{
    static $anchos = null;
    if ($anchos === null) {
        $anchos = [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889,
            '&' => 667, "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
            ',' => 278, '-' => 333, '.' => 278, '/' => 278, ':' => 278, ';' => 278,
            '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015, '[' => 278,
            '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, '{' => 334,
            '|' => 260, '}' => 334, '~' => 584,
            '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
            '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
            'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611,
            'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556,
            'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722,
            'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
            'Y' => 667, 'Z' => 611,
            'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278,
            'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222,
            'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333,
            's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
            'y' => 500, 'z' => 500,
            // Acentos y eñe: mismo ancho que la letra base.
            'á' => 556, 'é' => 556, 'í' => 278, 'ó' => 556, 'ú' => 556, 'ü' => 556, 'ñ' => 556,
            'Á' => 667, 'É' => 667, 'Í' => 278, 'Ó' => 778, 'Ú' => 722, 'Ü' => 722, 'Ñ' => 722,
        ];
    }

    $total = 0;
    $largo = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
    for ($i = 0; $i < $largo; $i++) {
        $caracter = function_exists('mb_substr') ? mb_substr($texto, $i, 1, 'UTF-8') : $texto[$i];
        $total += $anchos[$caracter] ?? 556;
    }
    // unidades/1000 × 11 pt × 20 twips/pt
    return $total * 0.22;
}

/**
 * Formulario SC-4-09 como lista de bloques.
 *
 * Es la única definición del modelo: de aquí salen el Word (PhpWord) y la
 * vista previa / PDF (jsPDF), así los tres formatos tienen los mismos párrafos,
 * sangrías y bordes. Los datos del trabajador van en negrita; si falta un dato
 * se deja la línea de guiones bajos del modelo en blanco.
 *
 * Se emite una hoja por cada década trabajada y cada hoja es el modelo completo
 * con 10 años: mismo encabezado, misma frase oficial y mismas firmas. Entre
 * hojas va un bloque "salto" que Word y el PDF convierten en página nueva.
 */
function snc125_formulario(array $c): array
{
    $bloques = [];

    $parrafo = static function (array $runs, array $opciones = []) use (&$bloques): void {
        // Los datos vienen como listas de tramos; se aplana en un solo texto.
        $planos = [];
        foreach ($runs as $r) {
            if (isset($r[0]) && is_array($r[0])) {
                foreach ($r as $sub) {
                    $planos[] = $sub;
                }
            } else {
                $planos[] = $r;
            }
        }
        $bloques[] = array_merge(['tipo' => 'p', 'runs' => $planos], $opciones);
    };
    // El dato se escribe ENCIMA de la raya del modelo: se conservan los guiones
    // bajos reales y el valor va centrado dentro de ese espacio, en negrita.
    // Si el dato no cabe se deja el valor completo (la raya se alarga).
    $dato = static function (string $texto, int $guiones = 0): array {
        $valor = trim($texto);
        if ($valor === '') {
            return [['texto' => str_repeat('_', $guiones), 'bold' => false]];
        }
        $largo = function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
        if ($guiones <= $largo) {
            return [['texto' => $valor, 'bold' => true]];
        }
        $resto = $guiones - $largo;
        $izquierda = intdiv($resto, 2);
        $derecha = $resto - $izquierda;
        $runs = [];
        if ($izquierda > 0) {
            $runs[] = ['texto' => str_repeat('_', $izquierda), 'bold' => false];
        }
        $runs[] = ['texto' => $valor, 'bold' => true];
        if ($derecha > 0) {
            $runs[] = ['texto' => str_repeat('_', $derecha), 'bold' => false];
        }
        return $runs;
    };
    $texto = static function (string $texto): array {
        return ['texto' => $texto, 'bold' => false];
    };

    // Una hoja del modelo: encabezado, frase oficial, tabla de 10 años y pie.
    $pagina = static function (array $anios) use (&$bloques, $parrafo, $dato, $texto, $c): void {
        $parrafo([$texto('CERTIFICACIÓN DE AÑOS DE SERVICIOS Y SALARIOS DEVENGADOS')],
            ['bold' => true, 'size' => 12, 'centro' => true, 'after' => 60]);
        $parrafo([$texto('SNC 1-25 * SC-4-09')],
            ['bold' => true, 'size' => 11, 'centro' => true, 'after' => 240]);

        $parrafo([
            $texto('El trabajador '), $dato($c['nombre'], 72),
            $texto(' con Carnet de Identidad No '), $dato($c['ci'], 23),
            $texto(', que ocupó la plaza de '), $dato($c['plaza'], 18),
        ]);
        $parrafo([$texto('__________________________________________________________________________________')]);

        // Entidad: el nombre va en la misma línea, después de un espacio, como
        // en el modelo. Sin NIT para que quepa completo.
        // El espacio va como tramo propio: si se deja al final de la frase, el
        // avance acumulado de jsPDF se queda corto y el nombre se dibuja encima
        // del espacio, quedando pegado a "entidad".
        $parrafo([
            $texto('Según documentos que obran en nuestro poder prestó servicios en la entidad'),
            $texto(' '),
            $dato($c['entidad'], $c['entidad'] === '' ? 20 : 0),
        ]);
        $parrafo([$texto('___________________________________________________________________________________')]);
        $parrafo([
            $texto('Calle '), $dato($c['calle'], 23),
            $texto(' No. '), $dato($c['numero'], 6),
            $texto(' Municipio '), $dato($c['municipio'], 19),
            $texto(' Provincia '), $dato($c['provincia'], 9),
        ]);
        $frase = [
            $texto('Desde el mes de '), $dato($c['desde_mes'], 14),
            $texto(' del '), $dato((string)$c['desde_anio'], 8),
            $texto(' al mes de '), $dato($c['hasta_mes'], 14),
            $texto(' del '), $dato((string)$c['hasta_anio'], 9),
            $texto(', '), $dato((string)$c['dias'], 7),
        ];
        $parrafo($frase);

        // Centro de la raya de los días: ancho real de la frase (Arial 11) menos
        // la mitad de sus 7 guiones finales. DÍAS y TRABAJADOS se centran en ese
        // punto para quedar uno encima del otro, debajo de la raya, como el modelo.
        $textoFrase = '';
        foreach ($frase as $tramo) {
            if (isset($tramo[0]) && is_array($tramo[0])) {
                foreach ($tramo as $sub) {
                    $textoFrase .= $sub['texto'];
                }
            } else {
                $textoFrase .= $tramo['texto'];
            }
        }
        $centroDias = (int)round(snc125_ancho_texto($textoFrase) - (snc125_ancho_texto('_') * 7) / 2);
        // "centro_texto"/"centro_guiones" permiten que el PDF (jsPDF) recalcule
        // el centro con sus propias métricas; el Word usa "centro_x" (Arial).
        $centroOpciones = ['after' => 0, 'linea' => 1.0, 'centro_x' => $centroDias,
            'centro_texto' => $textoFrase, 'centro_guiones' => 7];
        $parrafo([$texto('DÍAS')], $centroOpciones);
        $parrafo([$texto('TRABAJADOS')], $centroOpciones);
        $parrafo([], ['after' => 0, 'linea' => 1.0]);

        // Texto oficial del modelo, idéntico en todas las hojas.
        $parrafo([$texto('Y ha devengado en los últimos 10 años los salarios que a continuación se relacionan:')],
            ['after' => 120]);

        $bloques[] = ['tipo' => 'anios', 'filas' => snc125_parejas_anios($anios)];

        $parrafo([], ['after' => 80]);
        $parrafo([$texto('Y para constancia se expide esta certificación por el jefe del Dpto. Contabilidad de acuerdo con los requisitos establecidos en la legislación de Seguridad Social vigente.')]);
        $parrafo([]);
        if ($c['fecha_exp'] === '') {
            // Sin fecha de expedición el pie queda igual que el modelo en blanco.
            $parrafo([$texto('Dada en la ciudad de __________________ a los ____ días del mes de ____________ del ________')]);
        } else {
            $parrafo([
                $texto('Dada en la ciudad de '), $dato($c['ciudad'], 18),
                $texto(' a los '), $dato((string)$c['dia'], 4),
                $texto(' días del mes de '), $dato($c['mes'], 12),
                $texto(' del '), $dato((string)$c['anio'], 8),
            ]);
        }
        $parrafo([$texto('OBSERVACIONES:')], ['bold' => true]);
        $parrafo($c['obs'] !== '' ? [$dato($c['obs'])] : []);
        for ($fila = 0; $fila < 3; $fila++) {
            $parrafo([]);
        }

        $bloques[] = ['tipo' => 'firmas'];

        $parrafo([]);
        $parrafo([$texto('Esta certificación se expide en casos de baja del trabajador o de inicio de expediente de Seguridad Social.')],
            ['bold' => true, 'italic' => true, 'size' => 10, 'after' => 0]);
    };

    // 10 años por hoja: 20 años de servicio = 2 hojas, 30 = 3 hojas, etc.
    $grupos = array_chunk($c['anios'], 10);
    if (!$grupos) {
        $grupos = [[]];
    }
    foreach ($grupos as $indice => $grupo) {
        if ($indice > 0) {
            $bloques[] = ['tipo' => 'salto'];
        }
        $pagina($grupo);
    }

    return $bloques;
}

/** Anchos de la tabla de años del modelo (twips) y de las firmas. */
function snc125_anchos_anios(): array
{
    return [1680, 222, 3023, 236, 1680, 222, 3023];
}

function snc125_anchos_firmas(): array
{
    return [4763, 567, 4309];
}

function snc125_exportar_word(array $res, array $config, string $obs = '', string $fecha = ''): void
{
    if (!snc125_autoload()) {
        snc125_error('No está disponible la librería para generar Word (vendor/autoload.php).');
    }
    if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
        snc125_error('La librería PhpWord no está instalada en el servidor.');
    }

    $c = snc125_campos($res, $config, $obs, $fecha);

    // Mismo documento que el modelo en blanco SC-4-09 (Modelos → "Modelo en
    // blanco (Word)"): hoja carta vertical, márgenes 426/900/1021/1134 twips,
    // Arial, la tabla de años con raya solo bajo AÑO e IMPORTE RECIBIDO y las
    // firmas sin bordes. Los datos del trabajador van en negrita.
    $documento = new \PhpOffice\PhpWord\PhpWord();
    $documento->setDefaultFontName('Arial');
    $documento->setDefaultFontSize(10);

    $seccion = $documento->addSection([
        'pageSizeW' => 12240,
        'pageSizeH' => 15840,
        'marginTop' => 426,
        'marginRight' => 900,
        'marginBottom' => 1021,
        'marginLeft' => 1134,
    ]);

    // Numeración de hojas: "Página X de Y" al pie de cada modelo. Word resuelve
    // los campos {PAGE} y {NUMPAGES} al abrir o imprimir el documento.
    $pie = $seccion->addFooter();
    $pie->addPreserveText('Página {PAGE} de {NUMPAGES}',
        ['name' => 'Arial', 'size' => 9], ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0]);

    $anchosAnios = snc125_anchos_anios();
    $anchosFirmas = snc125_anchos_firmas();
    $raya = ['borderBottomSize' => 4, 'borderBottomColor' => '000000'];

    foreach (snc125_formulario($c) as $bloque) {
        if ($bloque['tipo'] === 'salto') {
            // Una hoja por cada década trabajada.
            $seccion->addPageBreak();
            continue;
        }
        if ($bloque['tipo'] === 'p') {
            $size = $bloque['size'] ?? 11;
            $estilo = [
                'name' => 'Arial',
                'size' => $size,
                'bold' => $bloque['bold'] ?? false,
                'italic' => $bloque['italic'] ?? false,
            ];
            $formato = [
                'spaceAfter' => $bloque['after'] ?? 140,
                'lineHeight' => $bloque['linea'] ?? 1.15,
            ];
            if (!empty($bloque['centro_x'])) {
                // Rótulos (DÍAS / TRABAJADOS) centrados sobre un punto fijo: se
                // define una región de 2400 twips centrada en ese punto y Word
                // centra el texto entre las sangrías izquierda y derecha.
                $region = 2400;
                $centroX = (int)$bloque['centro_x'];
                $formato['alignment'] = 'center';
                $formato['indentation'] = [
                    'left'  => $centroX - intdiv($region, 2),
                    'right' => (12240 - 1134 - 900) - ($centroX + intdiv($region, 2)),
                ];
            } elseif (!empty($bloque['centro'])) {
                $formato['alignment'] = 'center';
            }
            if (!empty($bloque['indent'])) {
                $formato['indentation'] = $bloque['indent'];
            }
            if (isset($bloque['linea']) && $bloque['linea'] === null) {
                unset($formato['lineHeight']);
            }

            $runs = $bloque['runs'];
            if (!$runs) {
                $seccion->addText('', $estilo, $formato);
                continue;
            }
            $run = $seccion->addTextRun($formato);
            foreach ($runs as $r) {
                $run->addText($r['texto'], [
                    'name' => 'Arial',
                    'size' => $size,
                    // La negrita del párrafo (título, OBSERVACIONES, nota) se
                    // aplica también a sus tramos.
                    'bold' => !empty($bloque['bold']) || !empty($r['bold']),
                    'italic' => $bloque['italic'] ?? false,
                ]);
            }
            continue;
        }

        if ($bloque['tipo'] === 'anios') {
            // Relación de los últimos 10 años: dos bloques de cinco, como el
            // modelo. Las líneas van solo debajo de AÑO e IMPORTE RECIBIDO.
            $tabla = $seccion->addTable(['width' => 10086, 'unit' => 'dxa', 'cellMargin' => 0]);
            $encabezado = $tabla->addRow(340);
            foreach (['AÑO', '', 'IMPORTE RECIBIDO', '', 'AÑO', '', 'IMPORTE RECIBIDO'] as $i => $rotulo) {
                $encabezado->addCell($anchosAnios[$i])
                    ->addText($rotulo, ['name' => 'Arial', 'size' => 11, 'bold' => true], ['alignment' => 'center', 'spaceAfter' => 0]);
            }

            $parejas = $bloque['filas'];
            for ($fila = 0; $fila < 5; $fila++) {
                $filaTabla = $tabla->addRow(420);
                $par = $parejas[$fila] ?? [null, null];
                $valores = [
                    $par[0] ? (string)$par[0]['anio'] : ' ',
                    '',
                    $par[0] ? snc125_importe((float)$par[0]['importe']) : ' ',
                    ' ',
                    $par[1] ? (string)$par[1]['anio'] : ' ',
                    '',
                    $par[1] ? snc125_importe((float)$par[1]['importe']) : ' ',
                ];
                foreach ($anchosAnios as $i => $ancho) {
                    $conRaya = $i % 2 === 0;
                    $celda = $filaTabla->addCell($ancho, $conRaya ? $raya : []);
                    if (!in_array($i, [1, 5], true)) {
                        $esDato = trim($valores[$i]) !== '';
                        $celda->addText($valores[$i],
                            ['name' => 'Arial', 'size' => 11, 'bold' => $esDato],
                            ['alignment' => $i % 2 === 0 && $esDato ? 'center' : 'left', 'spaceAfter' => 0]);
                    }
                }
            }
            continue;
        }

        // Firmas: tabla sin bordes (las líneas son los guiones bajos del texto).
        $firmas = $seccion->addTable(['cellMargin' => 0]);
        $rayaFirmas = $firmas->addRow(340);
        foreach (['_________________________________', '', '__________________________'] as $i => $texto) {
            $rayaFirmas->addCell($anchosFirmas[$i])
                ->addText($texto, ['name' => 'Arial', 'size' => 11], ['alignment' => 'center', 'spaceAfter' => 0]);
        }
        $rotulos = $firmas->addRow(340);
        foreach (['Nombre y Apellidos de quien certifica', '', 'Firma y cuño'] as $i => $texto) {
            $rotulos->addCell($anchosFirmas[$i])
                ->addText($texto, ['name' => 'Arial', 'size' => 11], ['alignment' => 'center', 'spaceAfter' => 0]);
        }
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . snc125_nombre_archivo($res, 'docx') . '"');
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');

    $escritor = \PhpOffice\PhpWord\IOFactory::createWriter($documento, 'Word2007');
    $escritor->save('php://output');
    exit;
}

/**
 * Barra flotante de la vista previa de impresión (Imprimir / Cerrar).
 *
 * La usan la vista previa del modelo y el listado impreso, así las dos
 * pantallas tienen la misma barra con sus iconos.
 */
function snc125_barra_impresion(): string
{
    $icono_imprimir = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 auto;"><polyline points="6 9 6 2 18 2 18 9"></polyline>'
        . '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>'
        . '<rect x="6" y="14" width="12" height="8"></rect></svg>';
    $icono_cerrar = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 auto;"><line x1="18" y1="6" x2="6" y2="18"></line>'
        . '<line x1="6" y1="6" x2="18" y2="18"></line></svg>';

    $barra = '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;'
        . 'background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;'
        . 'align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;'
        . 'border-bottom:0.1875rem solid #1e40af;">'
        . '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">VISTA PREVIA DE IMPRESIÓN</span>'
        . '<button type="button" onclick="window.print()" style="display:inline-flex;align-items:center;gap:0.4rem;'
        . 'padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;'
        . 'font-weight:bold;cursor:pointer;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">' . $icono_imprimir . ' Imprimir</button>'
        . '<button type="button" onclick="window.close()" style="display:inline-flex;align-items:center;gap:0.4rem;'
        . 'padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;'
        . 'font-weight:bold;cursor:pointer;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">' . $icono_cerrar . ' Cerrar</button>'
        . '</div><div style="height:3.4375rem;" class="no-print"></div>';

    $estilo = '<style>#auto-hide-toolbar{transition:transform 0.3s ease}'
        . '#auto-hide-toolbar.oculta{transform:translateY(-100%)}'
        . '@media print{#auto-hide-toolbar,.no-print{display:none !important}}</style>';

    $script = '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;'
        . 'var ultimo=window.scrollY||window.pageYOffset,pendiente=false;'
        . 'function revisar(){if(pendiente)return;pendiente=true;window.requestAnimationFrame(function(){'
        . 'var actual=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;'
        . 'if(actual>ultimo&&actual>60){tb.classList.add("oculta");}else{tb.classList.remove("oculta");}'
        . 'ultimo=actual;pendiente=false;});}'
        . 'window.addEventListener("scroll",revisar);document.addEventListener("scroll",revisar);})();</script>';

    return $estilo . $barra . $script;
}

/** Bloques del modelo en JSON, para pintarlo con jsPDF en el navegador. */
function snc125_bloques_pdf(array $res, array $config, string $obs = '', string $fecha = ''): array
{
    $c = snc125_campos($res, $config, $obs, $fecha);

    return [
        'bloques' => snc125_formulario($c),
        'anchos_anios' => snc125_anchos_anios(),
        'anchos_firmas' => snc125_anchos_firmas(),
        'archivo' => snc125_nombre_archivo($res, 'pdf'),
        'empresa' => (string)($config['nombre_empresa'] ?? ''),
    ];
}

/** Atiende las peticiones del modelo SC-4-09 hechas desde snc125.php. */
function snc125_reporte_manejar(PDO $pdo, array $config): void
{
    if (!isset($_GET['snc125_reporte'])) {
        return;
    }

    $formato = strtolower(trim((string)$_GET['snc125_reporte']));
    if (!in_array($formato, ['alerta', 'word', 'datos'], true)) {
        $formato = 'datos';
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $corte = isset($_GET['corte']) ? trim((string)$_GET['corte']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte)) {
        $corte = '';
    }

    $fecha = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = '';
    }

    $obs = isset($_GET['obs']) ? trim((string)$_GET['obs']) : '';
    $obs = preg_replace('/\s+/u', ' ', $obs);
    if (mb_strlen($obs, 'UTF-8') > 200) {
        $obs = mb_substr($obs, 0, 200, 'UTF-8');
    }

    if ($id <= 0) {
        snc125_error('Debe seleccionar un trabajador para generar la certificación.');
    }

    $res = snc125_datos($pdo, $id, $corte);
    if ($res === null) {
        snc125_error('No se encontró el trabajador seleccionado.');
    }

    if ($formato === 'word') {
        snc125_exportar_word($res, $config, $obs, $fecha);
        exit;
    }

    if ($formato === 'datos') {
        // Bloques del modelo para construirlo con jsPDF en el navegador.
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo json_encode(snc125_bloques_pdf($res, $config, $obs, $fecha), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($formato === 'alerta') {
        // Diagnóstico rápido del modelo para la tarjeta.
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'trabajador' => $res['trabajador']['nombre_completo'] ?? '',
            'dias' => (int)round((float)$res['dias']),
            'anios' => $res['anios'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    snc125_error('Formato de modelo no reconocido.');
}
