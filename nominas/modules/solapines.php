<?php
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    die("Su sesión ha expirado o no está autenticado. Inicie sesión nuevamente para continuar.");
}

// Control de acceso por rol
if (!permiso_puede('solapines', 'ver')) {
    permiso_denegar_acceso('Solapines');
}

function dividirTextoEnLineas($texto, $fuente, $tamanio, $ancho_maximo_px) {
    $lineas = [];
    $palabras = explode(' ', $texto);
    $linea_actual = '';
    
    foreach ($palabras as $palabra) {
        $prueba = $linea_actual . ($linea_actual ? ' ' : '') . $palabra;
        $bbox = imagettfbbox($tamanio, 0, $fuente, $prueba);
        $ancho = $bbox[2] - $bbox[0];
        
        if ($ancho <= $ancho_maximo_px) {
            $linea_actual = $prueba;
        } else {
            if ($linea_actual !== '') {
                $lineas[] = $linea_actual;
            }
            $linea_actual = $palabra;
        }
    }
    
    if ($linea_actual !== '') {
        $lineas[] = $linea_actual;
    }
    
    return $lineas;
}

function generarSolapinConPlantilla($empleado_id, $pdo, $ruta_salida = null) {
    $plantilla_path = __DIR__ . '/../assets/imagenes/Solapin TransNuBet.png';
    
    if (!file_exists($plantilla_path)) {
        $plantilla_path = $_SERVER['DOCUMENT_ROOT'] . '/nominas/assets/imagenes/Solapin TransNuBet.png';
    }
    
    if (!file_exists($plantilla_path)) {
        error_log("Plantilla no encontrada: " . $plantilla_path);
        return false;
    }
    
	$stmt = $pdo->prepare("
		SELECT t.*, a.nombre_area, cp.nombre_cargo AS cargo
		FROM trabajadores t
		LEFT JOIN areas a ON t.area_id = a.id
		LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
		WHERE t.id = ?
	");
    $stmt->execute([$empleado_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$emp) {
        error_log("Empleado no encontrado ID: " . $empleado_id);
        return false;
    }
    
    $imagen = @imagecreatefrompng($plantilla_path);
    if (!$imagen) {
        error_log("No se pudo cargar la plantilla PNG");
        return false;
    }
    
    imagealphablending($imagen, true);
    imagesavealpha($imagen, true);
    
    $width_px = imagesx($imagen);
    $height_px = imagesy($imagen);
    
    $ancho_cm = 6;
    $px_por_cm = $width_px / $ancho_cm;
    
    $color_azul = imagecolorallocate($imagen, 10, 80, 180);
    $color_negro = imagecolorallocate($imagen, 0, 0, 0);
    
    $nombre_completo = mb_strtoupper(trim($emp['nombres'] . ' ' . $emp['primer_apellido'] . ' ' . $emp['segundo_apellido']), 'UTF-8');
    if (empty($nombre_completo)) $nombre_completo = 'NO REGISTRADO';
    
    $ci = $emp['ci'];
    $ci_formateado = (strlen($ci) == 11) 
        ? substr($ci, 0, 2) . ' ' . substr($ci, 2, 2) . ' ' . substr($ci, 4, 2) . ' ' . substr($ci, 6, 5) 
        : ($ci ?: 'NO REGISTRADO');
    
    $cargo = !empty($emp['cargo']) ? mb_strtoupper($emp['cargo'], 'UTF-8') : 'NO ESPECIFICADO';
    $expediente = $emp['codigo'] ?: 'N/A';
$id_trabajador = $emp['id'] ?: 'N/A';

    $rutas_fuentes_bold = [
        __DIR__ . '/arialbd.ttf',
        __DIR__ . '/arialb.ttf',
        __DIR__ . '/../assets/fonts/arialbd.ttf',
        __DIR__ . '/../assets/fonts/arialb.ttf',
        'C:\Windows\Fonts\arialbd.ttf',
        'C:\Windows\Fonts\arialb.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'
    ];
    
    $rutas_fuentes_regular = [
        __DIR__ . '/arial.ttf',
        __DIR__ . '/../assets/fonts/arial.ttf',
        'C:\Windows\Fonts\arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
    ];
    
    $ruta_fuente_bold = null;
    foreach ($rutas_fuentes_bold as $f) {
        if (file_exists($f)) {
            $ruta_fuente_bold = $f;
            break;
        }
    }
    
    $ruta_fuente_regular = null;
    foreach ($rutas_fuentes_regular as $f) {
        if (file_exists($f)) {
            $ruta_fuente_regular = $f;
            break;
        }
    }
    
    $ruta_fuente = $ruta_fuente_bold ?: $ruta_fuente_regular;
    $usar_ttf = ($ruta_fuente !== null);
    
    $ancho_texto_px = $width_px * 0.75;
    
    $tamanio_nombre = 33;
    $tamanio_ci = 40;
    $tamanio_cargo = 20;
    $tamanio_expediente = 30;
    
    $nombre_y = (int)($height_px * 0.20);
    $ci_y = (int)($height_px * 0.27) + 19;
    $cargo_y = (int)($height_px * 0.32) + 5;
    $exp_y = (int)($height_px * 0.38);
    
    $nombre_x = (int)($width_px * 0.05);
    $ci_x = (int)($width_px * 0.05);
    $cargo_x = (int)($width_px * 0.05);
    
    if ($usar_ttf) {
        $lineas_nombre = dividirTextoEnLineas($nombre_completo, $ruta_fuente, $tamanio_nombre, $ancho_texto_px);
        $linea_y = $nombre_y;
        foreach ($lineas_nombre as $linea) {
            imagettftext($imagen, $tamanio_nombre, 0, $nombre_x, $linea_y, $color_azul, $ruta_fuente, $linea);
            $linea_y += $tamanio_nombre + 5;
        }
    } else {
        $nombre_mostrar = mb_strlen($nombre_completo, 'UTF-8') > 30 ? mb_substr($nombre_completo, 0, 27, 'UTF-8') . '...' : $nombre_completo;
        imagestring($imagen, 5, $nombre_x, $nombre_y - 12, $nombre_mostrar, $color_azul);
    }
    
    if ($usar_ttf) {
        imagettftext($imagen, $tamanio_ci, 0, $ci_x, $ci_y, $color_azul, $ruta_fuente, $ci_formateado);
    } else {
        imagestring($imagen, 5, $ci_x, $ci_y - 12, $ci_formateado, $color_azul);
    }
    
    if ($usar_ttf) {
        $lineas_cargo = dividirTextoEnLineas($cargo, $ruta_fuente, $tamanio_cargo, $ancho_texto_px);
        $linea_y = $cargo_y;
        foreach ($lineas_cargo as $linea) {
            imagettftext($imagen, $tamanio_cargo, 0, $cargo_x, $linea_y, $color_azul, $ruta_fuente, $linea);
            $linea_y += $tamanio_cargo + 5;
        }
    } else {
        imagestring($imagen, 4, $cargo_x, $cargo_y - 12, mb_substr($cargo, 0, 40, 'UTF-8'), $color_azul);
    }
    
    $exp_texto = "EXP: " . $expediente;
    
    if ($usar_ttf) {
        $bbox = imagettfbbox($tamanio_expediente, 0, $ruta_fuente, $exp_texto);
        $exp_width = $bbox[2] - $bbox[0];
        $exp_x = $width_px - $exp_width - 48;
    } else {
        $exp_x = $width_px - (mb_strlen($exp_texto, 'UTF-8') * imagefontwidth(5)) - 15;
    }
    
    if ($usar_ttf) {
        imagettftext($imagen, $tamanio_expediente, 0, $exp_x, $exp_y, $color_negro, $ruta_fuente, $exp_texto);
    } else {
        imagestring($imagen, 5, $exp_x, $exp_y, $exp_texto, $color_negro);
    }
    
// ============================================
// ID DEL TRABAJADOR - CON PIXELES DIRECTOS
// ============================================
$id_texto = "ID: " . $id_trabajador;
$tamanio_id = 28;

// Posición en PIXELES (ajusta estos valores)
$id_centro_x = 550; // Píxeles desde la izquierda
$id_centro_y = 350; // Píxeles desde arriba

// Color NEGRO
$color_negro_id = imagecolorallocate($imagen, 0, 0, 0);

// Usar fuente bold si está disponible
$fuente_a_usar = $ruta_fuente_bold ?: $ruta_fuente;

if ($usar_ttf && $fuente_a_usar && file_exists($fuente_a_usar)) {
    $bbox_id = imagettfbbox($tamanio_id, 0, $fuente_a_usar, $id_texto);
    if ($bbox_id !== false) {
        $id_width = $bbox_id[2] - $bbox_id[0];
        $id_x = $id_centro_x - ($id_width / 2);
        
        // Verificar límites
        if ($id_x < 0) $id_x = 10;
        if ($id_x + $id_width > $width_px) $id_x = $width_px - $id_width - 10;
        
        imagettftext($imagen, $tamanio_id, 0, (int)$id_x, (int)$id_centro_y, $color_negro_id, $fuente_a_usar, $id_texto);
    } else {
        $id_x = $id_centro_x - (mb_strlen($id_texto, 'UTF-8') * imagefontwidth(5) / 2);
        if ($id_x < 0) $id_x = 10;
        imagestring($imagen, 5, (int)$id_x, (int)$id_centro_y - 12, $id_texto, $color_negro_id);
    }
} else {
    $id_x = $id_centro_x - (mb_strlen($id_texto, 'UTF-8') * imagefontwidth(5) / 2);
    if ($id_x < 0) $id_x = 10;
    imagestring($imagen, 5, (int)$id_x, (int)$id_centro_y - 12, $id_texto, $color_negro_id);
}
	$ruta_foto = null;

	// Intentar cargar la foto registrada del trabajador
	if (!empty($emp['foto_ruta'])) {
		$ruta_foto = $_SERVER['DOCUMENT_ROOT'] . '/nominas/' . $emp['foto_ruta'];
	}

	// Si no tiene foto registrada o si el archivo físico no existe, usar la imagen por defecto
	if (empty($ruta_foto) || !file_exists($ruta_foto)) {
		$ruta_foto = __DIR__ . '/../assets/imagenes/noFoto.png';
		
		// Ruta alternativa de respaldo en caso de variaciones del servidor web
		if (!file_exists($ruta_foto)) {
			$ruta_foto = $_SERVER['DOCUMENT_ROOT'] . '/nominas/assets/imagenes/noFoto.png';
		}
	}

	// Procesar la imagen (sea la foto real o la de respaldo "noFoto.png")
	if (!empty($ruta_foto) && file_exists($ruta_foto)) {
		$ext = strtolower(pathinfo($ruta_foto, PATHINFO_EXTENSION));
		$foto = ($ext == 'png') ? @imagecreatefrompng($ruta_foto) : @imagecreatefromjpeg($ruta_foto);
		
		if ($foto) {
			$foto_x = (int)($width_px * 0.045) + 3;
			$foto_y = (int)($height_px * 0.012);
			$foto_w = (int)($width_px * 0.48) - 4;
			$foto_h = $foto_w;
			
			$orig_w = imagesx($foto);
			$orig_h = imagesy($foto);
			
			if ($orig_w > $orig_h) {
				$src_y = 0;
				$src_h = $orig_h;
				$src_w = $orig_h;
				$src_x = (int)(($orig_w - $src_w) / 2);
			} else {
				$src_x = 0;
				$src_w = $orig_w;
				$src_h = $orig_w;
				$src_y = (int)(($orig_h - $src_h) / 2);
			}
			
			$foto_redimensionada = imagecreatetruecolor($foto_w, $foto_h);
			
			// Mantener transparencias si la imagen por defecto es PNG
			imagealphablending($foto_redimensionada, false);
			imagesavealpha($foto_redimensionada, true);
			
			imagecopyresampled($foto_redimensionada, $foto, 0, 0, $src_x, $src_y, $foto_w, $foto_h, $src_w, $src_h);
			imagecopy($imagen, $foto_redimensionada, $foto_x, $foto_y, 0, 0, $foto_w, $foto_h);
			
			imagedestroy($foto);
			imagedestroy($foto_redimensionada);
		}
	}
		
    if ($ruta_salida) {
        $guardado = imagepng($imagen, $ruta_salida);
        imagedestroy($imagen);
        return $guardado;
    } else {
        ob_start();
        imagepng($imagen);
        $imagen_data = ob_get_clean();
        imagedestroy($imagen);
        return $imagen_data;
    }
}

// --- NUEVO: Exportación masiva de solapines en archivo ZIP ---
if (isset($_GET['exportar_todos'])) {
    // La generación masiva con GD puede tardar: sin límite de tiempo ni de memoria
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    // Buscar todos los trabajadores activos
  $stmt_todos = $pdo->query("SELECT id, nombres, primer_apellido, segundo_apellido 
                           FROM trabajadores 
                           WHERE fecha_baja IS NULL OR fecha_baja = ''");
    $trabajadores = $stmt_todos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($trabajadores)) {
        $mensaje = 'No se encontraron trabajadores activos para exportar. Registre al menos un trabajador activo para generar el lote de solapines.';
        $es_json = isset($_GET['verificar']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        if ($es_json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'total' => 0, 'message' => $mensaje]);
        } else {
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
                <title>Sin trabajadores activos</title>
                <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
                <link rel="stylesheet" href="../css/sweetalert2.min.css">
                <script src="../js/sweetalert211.js"></script>
            </head>
            <body>
            <script>
                Swal.fire({
                    icon: 'warning',
                    title: '<i class="fas fa-users-slash"></i> Sin trabajadores activos',
                    html: 'No se encontraron trabajadores activos para exportar.<br><br>Registre al menos un trabajador activo para poder generar el lote de solapines.',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    confirmButtonColor: '#f59e0b',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    window.location.href = 'empleados.php';
                });
            </script>
            </body>
            </html>
            <?php
        }
        exit;
    }

    // Si solo se pide verificar la existencia de trabajadores, responder JSON sin descargar
    if (isset($_GET['verificar'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'total' => count($trabajadores)]);
        exit;
    }

    $zip = new ZipArchive();
    $zipFilename = tempnam(sys_get_temp_dir(), 'solapines_') . '.zip';

    if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("No se pudo crear el archivo temporal ZIP en el servidor.");
    }

    foreach ($trabajadores as $t) {
        // Generar la información binaria de la imagen de cada trabajador
        $img_data = generarSolapinConPlantilla($t['id'], $pdo);
        
        if ($img_data) {
            // Normalizar y limpiar el nombre para el archivo dentro del ZIP
            $raw_name = trim($t['nombres'] . ' ' . $t['primer_apellido'] . ' ' . $t['segundo_apellido']);
            $clean_name = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
                ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
                $raw_name
            );
            $clean_name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $clean_name));
            $filename_dentro_zip = (!empty($clean_name) ? $clean_name : 'solapin_' . $t['id']) . '.png';

            // Añadir el archivo de imagen directamente desde la memoria al ZIP
            $zip->addFromString($filename_dentro_zip, $img_data);
        }
    }

    $zip->close();

    // Enviar las cabeceras correspondientes para descargar el archivo ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Solapines_Personal_Activo_' . date('Ymd') . '.zip"');
    header('Content-Length: ' . filesize($zipFilename));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($zipFilename);
    unlink($zipFilename); // Eliminar archivo temporal del servidor
    exit;
}
// -------------------------------------------------------------

$empleado_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : 0);

if ($empleado_id <= 0) {
    http_response_code(400);
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
        echo json_encode(['success' => false, 'message' => 'ID de empleado inválido']);
    } else {
        echo "ID de empleado inválido";
    }
    exit;
}

$carpeta = $_SERVER['DOCUMENT_ROOT'] . '/nominas/solapines/';
if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

$archivo_fisico = $carpeta . 'solapin_' . $empleado_id . '.png';
$url_relativa = '/nominas/solapines/solapin_' . $empleado_id . '.png';

if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    header('Content-Type: application/json');
    $resultado = generarSolapinConPlantilla($empleado_id, $pdo, $archivo_fisico);
    
    if ($resultado) {
        echo json_encode(['success' => true, 'url' => $url_relativa . '?t=' . time()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al generar el solapín. Verifique que la plantilla existe en: assets/imagenes/Solapin TransNuBet.png']);
    }
    exit;
}

$resultado = generarSolapinConPlantilla($empleado_id, $pdo);

if ($resultado) {
    header('Content-Type: image/png');
    
    // Buscar nombre del trabajador para personalizar el nombre de descarga
    $stmt_nom = $pdo->prepare("SELECT nombres, primer_apellido, segundo_apellido FROM trabajadores WHERE id = ?");
    $stmt_nom->execute([$empleado_id]);
    $emp_nom = $stmt_nom->fetch(PDO::FETCH_ASSOC);
    $nombre_archivo = 'solapin_' . $empleado_id . '.png';
    
    if ($emp_nom) {
        $raw_name = trim($emp_nom['nombres'] . ' ' . $emp_nom['primer_apellido'] . ' ' . $emp_nom['segundo_apellido']);
        // Reemplazo de caracteres con tilde y especiales para evitar fallos de codificación en cabeceras HTTP
        $clean_name = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
            $raw_name
        );
        $clean_name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $clean_name));
        if (!empty($clean_name)) {
            $nombre_archivo = $clean_name . '.png';
        }
    }
    
    if (isset($_GET['descargar'])) {
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $nombre_archivo . '"');
    }
    echo $resultado;
} else {
    http_response_code(500);
    echo "Error al generar el solapín. Verifique la existencia de la plantilla.";
}
exit;
?>