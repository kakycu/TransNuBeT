<?php
// includes/domiciliacion_dbf.php
// Estructura DBF exacta de la base de datos de domiciliacion de tarjetas del banco.
// Solo se llenan los 5 primeros campos; el resto queda vacio (espacios).

function domiciliacion_campos_dbf(): array {
    return [
        ['NUM_IDEPER', 'C', 15, 0],
        ['NOMBRE',     'C', 15, 0],
        ['APELLIDO_1', 'C', 15, 0],
        ['APELLIDO_2', 'C', 15, 0],
        ['NOMB_APELL', 'C', 23, 0],
        ['DIR_PERSO1', 'C', 35, 0],
        ['DIR_PERSO2', 'C', 35, 0],
        ['DIR_PERSO3', 'C', 35, 0],
        ['NUM_IDEBEN', 'C', 15, 0],
        ['NOMBRE_B',   'C', 15, 0],
        ['AP1_BENEF',  'C', 15, 0],
        ['AP2_BENEF',  'C', 15, 0],
        ['NOM_MNAC',   'L', 1,  0],
        ['EST_MLC',    'L', 1,  0],
        ['CTA_MLC',    'C', 16, 0],
        ['CTA_MNAC',   'C', 16, 0]
    ];
}

function domiciliacion_abreviar_nombres($nombres): string {
    $nombres = trim(preg_replace('/\s+/u', ' ', (string)$nombres));
    if ($nombres === '') return '';

    $partes = explode(' ', $nombres);
    $primero = array_shift($partes);
    if (empty($partes)) return $primero;

    $segundo = ltrim($partes[0], "'`.");
    if ($segundo === '') return $primero;

    return $primero . ' ' . mb_strtoupper(mb_substr($segundo, 0, 1)) . '.';
}

function domiciliacion_validar_ci($ci): array {
    $errores = [];
    $ci_limpio = preg_replace('/\D/', '', (string)$ci);

    if ($ci_limpio === '') {
        $errores[] = 'Falta el carnet de identidad';
        return $errores;
    }
    if (strlen($ci_limpio) !== 11) {
        $errores[] = 'El carnet debe tener 11 digitos';
        return $errores;
    }

    $anio  = substr($ci_limpio, 0, 2);
    $mes   = substr($ci_limpio, 2, 2);
    $dia   = substr($ci_limpio, 4, 2);
    $anioC = ((int)$anio < 24) ? (2000 + (int)$anio) : (1900 + (int)$anio);

    if (!checkdate((int)$mes, (int)$dia, $anioC)) {
        $errores[] = 'Fecha de nacimiento del carnet invalida';
    }
    return $errores;
}

function domiciliacion_validar_trabajador(array $t): array {
    $errores = [];

    if (trim((string)($t['nombres'] ?? '')) === '')          $errores[] = 'Falta el nombre';
    if (trim((string)($t['primer_apellido'] ?? '')) === '')  $errores[] = 'Falta el primer apellido';
    if (trim((string)($t['segundo_apellido'] ?? '')) === '') $errores[] = 'Falta el segundo apellido';

    return array_merge($errores, domiciliacion_validar_ci($t['ci'] ?? ''));
}

function domiciliacion_preparar_registro(array $t): array {
    $nombres = trim((string)($t['nombres'] ?? ''));
    $ap1     = trim((string)($t['primer_apellido'] ?? ''));
    $ap2     = trim((string)($t['segundo_apellido'] ?? ''));

    $valores = [
        'NUM_IDEPER' => preg_replace('/\D/', '', (string)($t['ci'] ?? '')),
        'NOMBRE'     => domiciliacion_abreviar_nombres($nombres),
        'APELLIDO_1' => $ap1,
        'APELLIDO_2' => $ap2,
        'NOMB_APELL' => trim($nombres . ' ' . $ap1 . ' ' . $ap2)
    ];

    $registro = [];
    foreach (domiciliacion_campos_dbf() as $campo) {
        $registro[$campo[0]] = $valores[$campo[0]] ?? '';
    }
    return $registro;
}

function domiciliacion_texto_dbf($valor): string {
    $valor = preg_replace('/[\x00-\x1F\x7F]/', '', (string)$valor);
    $valor = mb_convert_encoding($valor, 'Windows-1252', 'UTF-8');
    return $valor;
}

function domiciliacion_generar_dbf(string $archivoSalida, array $registros) {
    $fields = domiciliacion_campos_dbf();

    $recordLen = 1;
    foreach ($fields as $campo) $recordLen += $campo[2];
    $headerLen = 32 + (count($fields) * 32) + 1;
    $numRecords = count($registros);

    $header = '';
    $header .= pack('C', 0x03);
    $header .= pack('C', date('Y') - 1900);
    $header .= pack('C', date('m'));
    $header .= pack('C', date('d'));
    $header .= pack('V', $numRecords);
    $header .= pack('v', $headerLen);
    $header .= pack('v', $recordLen);
    $header .= str_repeat("\x00", 17) . "\x03" . str_repeat("\x00", 2);

    foreach ($fields as $campo) {
        $header .= str_pad(substr($campo[0], 0, 11), 11, "\x00");
        $header .= pack('C', ord($campo[1]));
        $header .= pack('V', 0);
        $header .= pack('C', $campo[2]);
        $header .= pack('C', $campo[3]);
        $header .= str_repeat("\x00", 14);
    }
    $header .= pack('C', 0x0D);

    $fp = fopen($archivoSalida, 'wb');
    if (!$fp) return false;

    fwrite($fp, $header);
    foreach ($registros as $registro) {
        $linea = pack('C', 0x20);
        foreach ($fields as $campo) {
            $valor = domiciliacion_texto_dbf($registro[$campo[0]] ?? '');
            $linea .= str_pad(substr($valor, 0, $campo[2]), $campo[2], ' ');
        }
        fwrite($fp, $linea);
    }
    fwrite($fp, pack('C', 0x1A));
    fclose($fp);

    return $archivoSalida;
}