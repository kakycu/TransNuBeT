<?php
// includes/visor_anidar.php - Entradas anidadas (ZIP/RAR dentro de ZIP/RAR)
//
// Cadena de entradas: entradas[] son los indices desde el comprimido de la
// raiz hasta el destino (entradas[0] abre una entrada del comprimido raiz,
// entradas[1] una de lo que habia dentro, y asi sucesivamente). passwords[k]
// es la clave del comprimido del nivel k y passwords[count(entradas)] la del
// archivo final cuando este es un RAR con cabeceras cifradas. El parametro
// legado entrada (un solo indice) + password sigue funcionando.
//
// Lo comparten ver_archivo.php (previsualizar) y extraer_zip.php /
// extraer_rar.php (descargar y extraer entradas o contenedores anidados).
// Los temporales que se crean se borran con register_shutdown_function.
//
// $modo controla la restriccion de tipos: 'visor' solo admite lo previsuali-
// zable (txt/sql/dbf/xml/csv/log/json/md/ps1/cmd) y 'archivo' cualquier fichero
// (una descarga o extraccion no tiene por que ser previsualizable).

/**
 * Lee entradas[]/passwords[] (o el legado entrada/password) de la peticion.
 * Devuelve ['entradas' => int[], 'passwords' => string[], 'password' => string].
 */
function visor_anidar_parsear(array $fuente)
{
    $password = (string)($fuente['password'] ?? '');
    $entradas  = [];

    if (isset($fuente['entradas']) && is_array($fuente['entradas'])) {
        foreach ($fuente['entradas'] as $posicion => $valor) {
            $entradas[(int)$posicion] = (int)$valor;
        }
        ksort($entradas);
        $entradas = array_values($entradas);
    } elseif (trim((string)($fuente['entrada'] ?? '')) !== '') {
        $entradas = [(int)$fuente['entrada']];
    }

    $passwords = [];
    if (isset($fuente['passwords']) && is_array($fuente['passwords'])) {
        foreach ($fuente['passwords'] as $posicion => $valor) {
            $passwords[(int)$posicion] = (string)$valor;
        }
    } elseif ($password !== '') {
        $passwords[0] = $password;
    }

    return ['entradas' => $entradas, 'passwords' => $passwords, 'password' => $password];
}

/**
 * Recorre la cadena de entradas hasta el destino y devuelve el temporal final.
 * Cada tramo extrae entradas[$nivel] del comprimido actual y lo extraido pasa
 * a ser el comprimido del tramo siguiente; el ultimo tramo es el archivo final
 * (previsualizable o no, segun $modo). Si la cadena esta vacia devuelve null
 * y el llamante debe seguir con el camino normal (archivo raiz suelto).
 *
 * @return array{ruta: string, bytes: int, nombre: string, extension: string,
 *               password: string}|null
 */
function visor_anidar_recorrer($rutaInicial, $extensionInicial, $bytesInicial,
    $nombreInicial, array $parse, $modo = 'visor')
{
    $entradasRuta = $parse['entradas'];
    if (count($entradasRuta) === 0) {
        return null;
    }

    $passwordsNivel = $parse['passwords'];
    $passwordLegado = $parse['password'];

    $rutaContenedor = $rutaInicial;
    $extensionCont  = (string)$extensionInicial;
    $bytesCont      = (int)$bytesInicial;
    $nombreFinal    = $nombreInicial;
    $totalTramos    = count($entradasRuta);

    foreach ($entradasRuta as $nivel => $indiceEntrada) {
        if ($extensionCont !== 'zip' && $extensionCont !== 'rar') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'nivel' => $nivel,
                'mensaje' => 'La ruta indicada no corresponde a un archivo comprimido.']);
            exit();
        }

        $passwordNivel = (string)($passwordsNivel[$nivel] ?? ($nivel === 0 ? $passwordLegado : ''));
        $esUltimo      = ($nivel === $totalTramos - 1);

        if ($extensionCont === 'zip') {
            $extraida = visor_extraer_entrada_zip($rutaContenedor, $indiceEntrada, $passwordNivel, $nivel, $esUltimo, $modo);
        } else {
            $extraida = visor_extraer_entrada_rar($rutaContenedor, $indiceEntrada, $passwordNivel, $nivel, $esUltimo, $modo);
        }

        $rutaContenedor = $extraida['ruta'];
        $extensionCont  = $extraida['extension'];
        $bytesCont      = $extraida['bytes'];
        $nombreFinal    = $extraida['nombre'];
    }

    return [
        'ruta'      => $rutaContenedor,
        'bytes'     => (int)$bytesCont,
        'nombre'    => $nombreFinal,
        'extension' => (string)$extensionCont,
        'password'  => (string)($passwordsNivel[$totalTramos] ?? $passwordLegado),
    ];
}

/**
 * Extrae una entrada de un ZIP a un temporal y devuelve ruta/peso/nombre.
 * $nivel es el tramo de la cadena (se refleja en el campo 'nivel' de los
 * errores de clave) y $esUltimo marca el destino final: solo el ultimo tramo
 * admite archivos que no sean comprimidos; los intermedios deben ser ZIP o
 * RAR. Con $modo 'visor' el ultimo tramo debe ademas ser previsualizable.
 * Los errores se responden aqui mismo, como en el resto del fichero.
 */
function visor_extraer_entrada_zip($rutaZip, $indice, $password, $nivel, $esUltimo, $modo = 'visor')
{
    if ($indice < 0) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'Entrada no indicada.']);
        exit();
    }

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'El servidor no tiene habilitada la extension ZIP.']);
        exit();
    }

    $zip = new ZipArchive();
    if ($zip->open($rutaZip) !== true) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'No se pudo abrir el ZIP.']);
        exit();
    }

    if ($indice >= $zip->numFiles) {
        $zip->close();
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'Esa entrada no existe en el ZIP.']);
        exit();
    }

    $nombreInterno = (string)$zip->getNameIndex($indice);
    $seguro        = carpetas_nombre_seguro($nombreInterno);
    $esDir         = substr(str_replace('\\', '/', $nombreInterno), -1) === '/';

    if ($seguro === null || $esDir) {
        $zip->close();
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,
            'mensaje' => $modo === 'visor' ? 'Entrada no previsualizable.' : 'Entrada no descargable.']);
        exit();
    }

    $extensionInterna  = strtolower(pathinfo($seguro, PATHINFO_EXTENSION));
    $esComprimido      = in_array($extensionInterna, ['zip', 'rar'], true);
    $esPrevisualizable = in_array($extensionInterna, ['txt', 'sql', 'dbf', 'xml', 'csv', 'log', 'json', 'md', 'ps1', 'cmd'], true);

    if ($modo === 'visor' && !$esComprimido && !$esPrevisualizable) {
        $zip->close();
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,
            'mensaje' => 'Ese tipo de archivo no se puede previsualizar dentro del ZIP.']);
        exit();
    }

    if (!$esComprimido && !$esUltimo) {
        $zip->close();
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'nivel' => $nivel,
            'mensaje' => 'La ruta intermedia debe apuntar a un archivo comprimido.']);
        exit();
    }

    $entradaStat   = $zip->statIndex($indice);
    $flagsZip      = carpetas_zip_flags($rutaZip);
    $entradaCifrada = !empty($flagsZip['mapa'][$indice]);

    if ($entradaCifrada) {
        if ($password === '') {
            $zip->close();
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'requierePassword' => true,
                'codigo'  => 'password_requerida',
                'nivel'   => $nivel,
                'mensaje' => 'Esta entrada esta protegida con contraseña.',
                'entrada' => $nombreInterno]);
            exit();
        }

        carpetas_zip_aplicar_password($zip, $password);
    }

    $crcEsperado = carpetas_zip_crc_esperado($entradaStat);
    $temporal    = tempnam(sys_get_temp_dir(), 'visorzip');
    if ($temporal === false) {
        $zip->close();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'No se pudo preparar la previsualizacion.']);
        exit();
    }

    // Se vuelca a un temporal por streaming para no cargar el archivo entero
    // en memoria; si el stream no esta disponible (p. ej. entradas cifradas)
    // se recurre a la lectura completa.
    $contenido  = false;
    $crcActual  = '';
    $completado = false;

    if (!$entradaCifrada) {
        $flujoOrigen = $zip->getStream($nombreInterno);
        if ($flujoOrigen !== false) {
            $destino = fopen($temporal, 'wb');
            if ($destino !== false) {
                $hash = hash_init('crc32b');
                while (!feof($flujoOrigen)) {
                    $trozo = fread($flujoOrigen, 1048576);
                    if ($trozo === false) {
                        break;
                    }
                    fwrite($destino, $trozo);
                    hash_update($hash, $trozo);
                }
                fclose($destino);
                $crcActual  = hash_final($hash);
                $completado = true;
            }
            fclose($flujoOrigen);
        }
    }

    if (!$completado) {
        $contenido = $zip->getFromIndex($indice);
        if ($contenido === false) {
            $zip->close();
            @unlink($temporal);
            if ($entradaCifrada) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'passwordIncorrecta' => true,
                    'requierePassword' => true,
                    'codigo'  => 'password_incorrecta',
                    'nivel'   => $nivel,
                    'mensaje' => 'Contraseña incorrecta.']);
                exit();
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'mensaje' => 'No se pudo leer esa entrada.']);
            exit();
        }

        $crcActual = hash('crc32b', $contenido);
        if (file_put_contents($temporal, $contenido) === false) {
            unset($contenido);
            $zip->close();
            @unlink($temporal);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'mensaje' => 'No se pudo preparar la previsualizacion.']);
            exit();
        }
        unset($contenido);
    }

    $zip->close();

    if ($crcEsperado !== '' && $crcActual !== $crcEsperado) {
        @unlink($temporal);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,
            'mensaje' => 'El CRC-32 no coincide: la entrada esta corrupta.']);
        exit();
    }

    register_shutdown_function(function () use ($temporal) {
        @unlink($temporal);
    });

    return [
        'ruta'      => $temporal,
        'bytes'     => (int)@filesize($temporal),
        'nombre'    => $seguro,
        'extension' => $extensionInterna,
    ];
}

/**
 * Extrae una entrada de un RAR con UnRAR y devuelve ruta/peso/nombre.
 * Misma semantica que visor_extraer_entrada_zip(): $nivel identifica el tramo
 * de la cadena en los errores de clave, $esUltimo admite solo el ultimo tramo
 * como archivo no comprimido y $modo 'visor' restringe ademas a tipos
 * previsualizables.
 */
function visor_extraer_entrada_rar($rutaRar, $indice, $password, $nivel, $esUltimo, $modo = 'visor')
{
    require_once __DIR__ . '/rarlib/autoload.php';
    require_once __DIR__ . '/rarlib/unrar.php';

    if ($indice < 0) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'Entrada no indicada.']);
        exit();
    }

    $indiceRarDatos = rar_indice_completo($rutaRar, $password);
    if ($indiceRarDatos['ok'] !== true) {
        $motivoRar = $indiceRarDatos['motivo'] ?? 'error';
        if ($motivoRar === 'password') {
            if ($password === '') {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'requierePassword' => true,
                    'codigo'  => 'password_requerida',
                    'nivel'   => $nivel,
                    'mensaje' => 'Este RAR tiene las cabeceras cifradas: falta la contraseña.']);
            } else {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'passwordIncorrecta' => true,
                    'requierePassword' => true,
                    'codigo'  => 'password_incorrecta',
                    'nivel'   => $nivel,
                    'mensaje' => 'Contraseña incorrecta.']);
            }
            exit();
        }
        if ($motivoRar === 'no_encontrada') {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'mensaje' => 'Esa entrada no existe en el RAR.']);
            exit();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,
            'mensaje' => 'No se pudo abrir el RAR (formato no reconocido o dañado).']);
        exit();
    }

    $entradasRar = $indiceRarDatos['entradas'];

    if ($indice >= count($entradasRar)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => 'Esa entrada no existe en el RAR.']);
        exit();
    }

    $entradaR      = $entradasRar[$indice];
    $nombreInterno = $entradaR['nombre'];
    $seguro        = carpetas_nombre_seguro($nombreInterno);
    $esDir         = $entradaR['esDir'];

    if ($seguro === null || $esDir) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,
            'mensaje' => $modo === 'visor' ? 'Entrada no previsualizable.' : 'Entrada no descargable.']);
        exit();
    }

    $extensionInterna  = strtolower(pathinfo($seguro, PATHINFO_EXTENSION));
    $esComprimido      = in_array($extensionInterna, ['zip', 'rar'], true);
    $esPrevisualizable = in_array($extensionInterna, ['txt', 'sql', 'dbf', 'xml', 'csv', 'log', 'json', 'md', 'ps1', 'cmd'], true);

    if ($modo === 'visor' && !$esComprimido && !$esPrevisualizable) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,
            'mensaje' => 'Ese tipo de archivo no se puede previsualizar dentro del RAR.']);
        exit();
    }

    if (!$esComprimido && !$esUltimo) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'nivel' => $nivel,
            'mensaje' => 'La ruta intermedia debe apuntar a un archivo comprimido.']);
        exit();
    }

    $entradaCifrada = $entradaR['cifrada'];

    if ($entradaCifrada && $password === '') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'requierePassword' => true,
            'codigo'  => 'password_requerida',
            'nivel'   => $nivel,
            'mensaje' => 'Esta entrada esta protegida con contraseña.',
            'entrada' => $nombreInterno]);
        exit();
    }

    $extraida = rar_extraer_entrada_temporal($rutaRar, $nombreInterno, $password);
    if ($extraida['ok'] === false) {
        if ($extraida['motivo'] === 'password') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'passwordIncorrecta' => true,
                'requierePassword' => true,
                'codigo'  => 'password_incorrecta',
                'nivel'   => $nivel,
                'mensaje' => 'Contraseña incorrecta.']);
            exit();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'mensaje' => (string)($extraida['mensaje'] ?? 'No se pudo leer esa entrada.')]);
        exit();
    }

    // UnRAR ya dejo el contenido en su propio temporal: se apunta a el sin
    // cargarlo en memoria y se borra al terminar la peticion.
    $rutaExtraida = (string)$extraida['archivo'];
    $dirTemporal  = (string)$extraida['temporal'];
    register_shutdown_function(function () use ($rutaExtraida, $dirTemporal) {
        @unlink($rutaExtraida);
        @rmdir($dirTemporal);
    });

    return [
        'ruta'      => $rutaExtraida,
        'bytes'     => (int)@filesize($rutaExtraida),
        'nombre'    => $seguro,
        'extension' => $extensionInterna,
    ];
}
