<?php
// includes/logos_sistema.php - Utilidades de los logos del sistema.
//
// Los logos viven en servicios/images con nombres fijos y todas las páginas los
// referencian así:
//   LogoTN.png    -> logo normal (inicio de sesión, menú lateral y cabecera)
//   LogoCorto.png -> logo corto (reportes, modelos y exportaciones)
//   favicon.png   -> imagen base del sistema (favicon y respaldo de los logos)
//
// Si alguno falta (o está dañado), se reconstruye a partir de favicon.png; si
// tampoco existe favicon.png se usa su copia optimizada favicon_respaldo.png y,
// como último recurso, se genera un PNG neutro con GD. De este modo ninguna
// página queda con una imagen rota.

if (!function_exists('logo_sistema_imagen_valida')) {
    // Comprueba que el archivo exista, no esté vacío y sea una imagen legible.
    function logo_sistema_imagen_valida($ruta) {
        if (!is_file($ruta) || filesize($ruta) === 0) {
            return false;
        }
        $medidas = @getimagesize($ruta);
        return $medidas !== false && (int)$medidas[0] > 0 && (int)$medidas[1] > 0;
    }
}

if (!function_exists('logo_sistema_escribir')) {
    // Escritura atómica: archivo temporal en la misma carpeta y reemplazo.
    // En Windows el rename puede fallar si el destino está bloqueado, así que
    // se intenta una copia como respaldo.
    function logo_sistema_escribir($ruta, $datos) {
        if ($datos === null || $datos === false || $datos === '') {
            return false;
        }
        $temporal = $ruta . '.tmp';
        if (@file_put_contents($temporal, $datos) === false) {
            return false;
        }
        if (@rename($temporal, $ruta)) {
            return true;
        }
        $listo = @copy($temporal, $ruta);
        @unlink($temporal);
        return $listo;
    }
}

if (!function_exists('logo_sistema_generar_png')) {
    // Logo neutro dibujado con GD: cuadrado redondeado con degradado azul →
    // verde azulado y las iniciales «TN» en blanco. Solo se usa si no hay
    // ninguna imagen base disponible. Devuelve los bytes PNG o null.
    function logo_sistema_generar_png() {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $base   = 800;          // tamaño final del logo
        $escala = 3;            // se dibuja al triple y se reduce para suavizar bordes
        $lado   = $base * $escala;

        $img = imagecreatetruecolor($lado, $lado);
        if (!$img) {
            return null;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, $lado - 1, $lado - 1, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        // Fondo cuadrado redondeado con degradado vertical (azul → verde azulado).
        $x0 = (int)round(0.05 * $lado);
        $x1 = (int)round(0.95 * $lado);
        $r  = (int)round(0.20 * $lado);
        $alto = $x1 - $x0;
        for ($y = 0; $y <= $alto; $y++) {
            $fy = $y / max(1, $alto);
            $rojo  = (int)round(30 + (15 - 30) * $fy);
            $verde = (int)round(136 + (118 - 136) * $fy);
            $azul  = (int)round(229 + (110 - 229) * $fy);
            $color = imagecolorallocate($img, $rojo, $verde, $azul);

            // Inserto de las esquinas redondeadas.
            $inset = 0;
            if ($y < $r) {
                $dy = $r - $y;
                $inset = (int)round($r - sqrt(max(0, $r * $r - $dy * $dy)));
            } elseif ($y > $alto - $r) {
                $dy = $y - ($alto - $r);
                $inset = (int)round($r - sqrt(max(0, $r * $r - $dy * $dy)));
            }
            imagefilledrectangle(
                $img,
                $x0 + $inset,
                $x0 + $y,
                $x1 - $inset,
                $x0 + $y,
                $color
            );
        }

        // Trazo grueso como polígono (mejor acabado que imagesetthickness).
        $colorBlanco = imagecolorallocate($img, 255, 255, 255);
        $trazo = function ($xa, $ya, $xb, $yb, $grosor) use ($img, $colorBlanco) {
            $dx = $xb - $xa;
            $dy = $yb - $ya;
            $largo = sqrt($dx * $dx + $dy * $dy);
            if ($largo == 0) {
                return;
            }
            $nx = -$dy / $largo * $grosor / 2;
            $ny =  $dx / $largo * $grosor / 2;
            imagefilledpolygon($img, [
                (int)round($xa + $nx), (int)round($ya + $ny),
                (int)round($xb + $nx), (int)round($yb + $ny),
                (int)round($xb - $nx), (int)round($yb - $ny),
                (int)round($xa - $nx), (int)round($ya - $ny),
            ], $colorBlanco);
        };

        // Iniciales del sistema: T y N dibujadas con trazos blancos.
        $altoLetra  = 0.32 * $lado;
        $grosor     = 0.075 * $lado;
        $anchoLetra = 0.20 * $lado;
        $separacion = 0.07 * $lado;
        $bloque     = ($anchoLetra * 2) + $separacion;
        $inicioX    = ($lado - $bloque) / 2;
        $inicioY    = ($lado - $altoLetra) / 2;

        // T
        $trazo($inicioX, $inicioY + $grosor / 2, $inicioX + $anchoLetra, $inicioY + $grosor / 2, $grosor);
        $trazo($inicioX + $anchoLetra / 2, $inicioY, $inicioX + $anchoLetra / 2, $inicioY + $altoLetra, $grosor);

        // N
        $nx = $inicioX + $anchoLetra + $separacion;
        $trazo($nx, $inicioY, $nx, $inicioY + $altoLetra, $grosor);
        $trazo($nx + $anchoLetra, $inicioY, $nx + $anchoLetra, $inicioY + $altoLetra, $grosor);
        $trazo(
            $nx + $grosor * 0.5,
            $inicioY + $grosor * 0.5,
            $nx + $anchoLetra - $grosor * 0.5,
            $inicioY + $altoLetra - $grosor * 0.5,
            $grosor
        );

        // Reducción final con suavizado.
        $final = imagecreatetruecolor($base, $base);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        imagefilledrectangle($final, 0, 0, $base - 1, $base - 1, imagecolorallocatealpha($final, 0, 0, 0, 127));
        imagecopyresampled($final, $img, 0, 0, 0, 0, $base, $base, $lado, $lado);
        imagedestroy($img);

        ob_start();
        $ok = imagepng($final, null, 6);
        $png = ob_get_clean();
        imagedestroy($final);

        return ($ok && $png !== '' && $png !== false) ? $png : null;
    }
}

if (!function_exists('logo_sistema_imagen_defecto')) {
    // Imagen base disponible, en orden de preferencia:
    //   favicon.png > favicon_respaldo.png > PNG generado con GD.
    // Devuelve ['datos' => string, 'origen' => 'favicon'|'respaldo'|'generado']
    // o null si no se puede obtener ninguna.
    function logo_sistema_imagen_defecto($dirImagenes) {
        $dir = rtrim($dirImagenes, '/\\');
        $candidatos = [
            'favicon.png'           => 'favicon',
            'favicon_respaldo.png'  => 'respaldo',
        ];
        foreach ($candidatos as $archivo => $origen) {
            $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
            if (!is_file($ruta)) {
                continue;
            }
            $datos = @file_get_contents($ruta);
            if ($datos === false || @getimagesizefromstring($datos) === false) {
                continue;
            }
            return ['datos' => $datos, 'origen' => $origen, 'archivo' => $archivo];
        }

        $png = logo_sistema_generar_png();
        if ($png === null) {
            return null;
        }
        return ['datos' => $png, 'origen' => 'generado', 'archivo' => null];
    }
}

if (!function_exists('asegurar_logos_sistema')) {
    // Garantiza que favicon.png, LogoTN.png y LogoCorto.png existan y sean
    // imágenes. Se ejecuta una sola vez por petición y no lanza errores: si la
    // carpeta no existe o no se puede escribir, la página sigue funcionando.
    function asegurar_logos_sistema($dirImagenes = null) {
        static $hecho = false;
        if ($hecho) {
            return true;
        }
        $hecho = true;

        if ($dirImagenes === null) {
            // includes/ -> nominas/ -> servicios/images
            $dirImagenes = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'images';
        }
        $dir = rtrim($dirImagenes, '/\\');
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $favicon = $dir . DIRECTORY_SEPARATOR . 'favicon.png';
        if (!logo_sistema_imagen_valida($favicon)) {
            $defecto = logo_sistema_imagen_defecto($dir);
            if ($defecto === null || !logo_sistema_escribir($favicon, $defecto['datos'])) {
                return false;
            }
        }

        if (!logo_sistema_imagen_valida($favicon)) {
            return false;
        }
        $datosFavicon = @file_get_contents($favicon);
        if ($datosFavicon === false) {
            return false;
        }

        // Los dos logos se reconstruyen a partir de favicon.png.
        foreach (['LogoTN.png', 'LogoCorto.png'] as $logo) {
            $ruta = $dir . DIRECTORY_SEPARATOR . $logo;
            if (logo_sistema_imagen_valida($ruta)) {
                continue;
            }
            logo_sistema_escribir($ruta, $datosFavicon);
        }

        return true;
    }
}
