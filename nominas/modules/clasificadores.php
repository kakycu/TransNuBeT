<?php
// clasificadores.php - Gestión unificada de clasificadores del sistema

// 1. Cargar base de datos primero (config/database.php lee las credenciales desde config.php)
require_once '../config/database.php';

// 2. Iniciar sesión únicamente si config.php no lo hizo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sincronización de sesión unificada
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Control de acceso por rol
if (!permiso_puede('clasificadores', 'ver')) {
    permiso_denegar_acceso('Clasificadores');
}
$puede_crear_clasif = permiso_puede('clasificadores', 'crear');
$puede_editar_clasif = permiso_puede('clasificadores', 'editar');
$puede_eliminar_clasif = permiso_puede('clasificadores', 'eliminar');

// ==========================================
// CONSULTA DE RELACIONES EXTRANJERAS (FKS)
// ==========================================
$categorias_db = [];
$escalas_db = [];
try {
    $categorias_db = $pdo->query("SELECT id, codigo, nombre FROM categorias_ocupacionales WHERE activo = 1 ORDER BY orden, codigo")->fetchAll(PDO::FETCH_ASSOC);
    $escalas_db = $pdo->query("SELECT id, escala_numero, salario_mensual FROM escalas_salariales WHERE activo = 1 ORDER BY escala_numero")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar dependencias de clasificadores: " . $e->getMessage());
}

// ==========================================
// MANEJADORES DE PETICIONES AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $tabla = $_POST['tabla'] ?? '';
        $accion = $_POST['accion_ajax'];
        
        // Seguridad: solo se permiten tablas clasificadoras conocidas.
        if (!esTablaClasificador($tabla)) {
            echo json_encode(['success' => false, 'error' => 'Tabla de clasificador no válida']);
            exit();
        }
        
        // Control de permisos por rol según la acción
        $puede_mutar_clasif = true;
        if ($accion === 'guardar') {
            $puede_mutar_clasif = permiso_puede('clasificadores', 'crear') || permiso_puede('clasificadores', 'editar');
        } elseif ($accion === 'eliminar' || $accion === 'toggle_estado') {
            $puede_mutar_clasif = permiso_puede('clasificadores', 'editar') || permiso_puede('clasificadores', 'eliminar');
        }
        if (!$puede_mutar_clasif) {
            echo json_encode(['success' => false, 'error' => 'No tiene permisos suficientes para realizar esta operación. Contacte al administrador del sistema.', 'denied' => true]);
            exit();
        }
        
        // 1. Obtener Registro Individual
        if ($accion === 'obtener') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM {$tabla} WHERE id = ?");
            $stmt->execute([$id]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($registro) {
                echo json_encode(['success' => true, 'data' => $registro]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Registro no localizado en el sistema']);
            }
            exit();
        }
        
        // 2. Listar Registros (con soporte de Joins para Cargos)
        if ($accion === 'listar') {
            $search = $_POST['search'] ?? '';
            $activo_filter = $_POST['activo_filter'] ?? '';
            $params = [];
            
            if ($tabla === 'cargos_plantilla') {
                $sql = "SELECT cp.*, co.codigo AS cat_codigo, co.nombre AS cat_nombre, es.escala_numero AS esc_num, es.salario_mensual AS esc_salario
                        FROM cargos_plantilla cp
                        LEFT JOIN categorias_ocupacionales co ON cp.categoria_ocupacional_id = co.id
                        LEFT JOIN escalas_salariales es ON cp.escala_salarial_id = es.id
                        WHERE 1=1";
            } else {
                $sql = "SELECT * FROM {$tabla} WHERE 1=1";
            }
            
            // Filtro de búsqueda textual
            $camposBusqueda = obtenerCamposBusqueda($tabla);
            if (!empty($search) && !empty($camposBusqueda)) {
                // Escapar comodines LIKE para que el texto literal no actúe como patrón
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $condiciones = [];
                foreach ($camposBusqueda as $campo) {
                    $columna = ($tabla === 'cargos_plantilla') ? "cp.{$campo}" : $campo;
                    $condiciones[] = "{$columna} LIKE ?";
                    $params[] = "%$escaped%";
                }
                $sql .= " AND (" . implode(" OR ", $condiciones) . ")";
            }
            
            // Filtro de estado lógico
            if (tieneCampoActivo($tabla) && $activo_filter !== '') {
                $columnaActivo = ($tabla === 'cargos_plantilla') ? "cp.activo" : "activo";
                $sql .= " AND {$columnaActivo} = ?";
                $params[] = $activo_filter;
            }
            
            $prefijoOrden = ($tabla === 'cargos_plantilla') ? "cp." : "";
            $sql .= " ORDER BY " . $prefijoOrden . obtenerOrdenamiento($tabla);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $registros]);
            exit();
        }
        
        // 3. Crear o Actualizar Registro
        if ($accion === 'guardar') {
            $id = intval($_POST['id'] ?? 0);
            $datos = obtenerDatosFormulario($tabla, $_POST);
            
            $error = validarDatos($tabla, $datos);
            if ($error) {
                echo json_encode(['success' => false, 'error' => $error]);
                exit();
            }
            
            $duplicado = verificarDuplicado($pdo, $tabla, $datos, $id);
            if ($duplicado) {
                echo json_encode(['success' => false, 'error' => $duplicado]);
                exit();
            }
            
            if ($id > 0) {
                // Modificar
                $sets = [];
                $params = [];
                foreach ($datos as $campo => $valor) {
                    $sets[] = "{$campo} = ?";
                    $params[] = $valor;
                }
                $params[] = $id;
                $stmt = $pdo->prepare("UPDATE {$tabla} SET " . implode(', ', $sets) . " WHERE id = ?");
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => 'El registro ha sido actualizado con éxito']);
            } else {
                // Crear
                $campos = array_keys($datos);
                $placeholders = array_fill(0, count($campos), '?');
                $stmt = $pdo->prepare("INSERT INTO {$tabla} (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")");
                $stmt->execute(array_values($datos));
                echo json_encode(['success' => true, 'message' => 'El registro ha sido creado con éxito']);
            }
            exit();
        }
        
        // 4. Eliminar Registro
        if ($accion === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            
            $dependencia = verificarDependencias($pdo, $tabla, $id);
            if ($dependencia) {
                echo json_encode(['success' => false, 'error' => $dependencia]);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM {$tabla} WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'El registro ha sido eliminado del sistema']);
            exit();
        }
        
        // 5. Alternar estado activo/inactivo
        if ($accion === 'toggle_estado') {
            if (!tieneCampoActivo($tabla)) {
                echo json_encode(['success' => false, 'error' => 'Este clasificador no admite estados activo/inactivo']);
                exit();
            }
            $id = intval($_POST['id'] ?? 0);
            $activo = intval($_POST['activo'] ?? 0);
            if ($activo !== 0 && $activo !== 1) {
                echo json_encode(['success' => false, 'error' => 'Estado inválido']);
                exit();
            }
            
            $stmt = $pdo->prepare("UPDATE {$tabla} SET activo = ? WHERE id = ?");
            $stmt->execute([$activo, $id]);
            echo json_encode(['success' => true, 'message' => 'El estado se ha actualizado correctamente']);
            exit();
        }
// ========== OBTENER SIGUIENTE CÓDIGO SUGERIDO ==========
        if ($accion === 'obtener_siguiente_codigo') {
            $siguiente = 1;
            
            switch($tabla) {
                case 'areas':
                    // Analiza el incremento secuencial del patrón existente (E.g. de 100 en 100)
                    $stmt = $pdo->query("SELECT codigo FROM areas ORDER BY codigo DESC LIMIT 2");
                    $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (count($resultados) >= 2) {
                        $diff = $resultados[0] - $resultados[1];
                        $siguiente = $resultados[0] + $diff;
                    } elseif (count($resultados) === 1) {
                        $siguiente = $resultados[0] + 100;
                    } else {
                        $siguiente = 100;
                    }
                    break;
                    
                case 'centros_costo':
                    // Parsea códigos guardados como strings numéricos
                    $stmt = $pdo->query("SELECT codigo FROM centros_costo");
                    $codigos = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $max = 0;
                    foreach ($codigos as $cod) {
                        if (is_numeric($cod)) {
                            $max = max($max, intval($cod));
                        }
                    }
                    $siguiente = $max + 1;
                    break;
                    
                case 'motivos_baja':
                    $stmt = $pdo->query("SELECT MAX(codigo) FROM motivos_baja");
                    $max = $stmt->fetchColumn();
                    $siguiente = $max ? intval($max) + 1 : 1;
                    break;
                    
                case 'escalas_salariales':
                    $stmt = $pdo->query("SELECT MAX(escala_numero) FROM escalas_salariales");
                    $max = $stmt->fetchColumn();
                    $siguiente = $max ? intval($max) + 1 : 1;
                    break;
                    
                case 'categorias_ocupacionales':
                    $stmt = $pdo->query("SELECT MAX(orden) FROM categorias_ocupacionales");
                    $max = $stmt->fetchColumn();
                    $siguiente = $max ? intval($max) + 1 : 1;
                    break;
            }
            
            echo json_encode(['success' => true, 'siguiente_codigo' => $siguiente]);
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Error en clasificadores.php (ajax {$accion}/{$tabla}): " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor al procesar la solicitud']);
        exit();
    }
}

// ==========================================
// FUNCIONES DE CONTROL DE METADATOS PHP
// ==========================================

function esTablaClasificador($tabla) {
    $permitidas = [
        'centros_costo',
        'areas',
        'categorias_ocupacionales',
        'escalas_salariales',
        'cargos_plantilla',
        'motivos_baja',
        'configuracion_general',
        'configuracion_rangos_impuesto'
    ];
    return in_array($tabla, $permitidas, true);
}

function obtenerCamposBusqueda($tabla) {
    $mapa = [
        'centros_costo' => ['codigo', 'nombre', 'descripcion'],
        'areas' => ['codigo', 'nombre_area', 'descripcion'],
        'categorias_ocupacionales' => ['codigo', 'nombre', 'descripcion'],
        'escalas_salariales' => ['escala_numero', 'descripcion'],
        'cargos_plantilla' => ['nombre_cargo', 'organo_grupo', 'nivel_preparacion'],
        'motivos_baja' => ['codigo', 'nombre', 'categoria', 'base_legal'],
        'configuracion_general' => ['parametro', 'valor', 'descripcion'],
        'configuracion_rangos_impuesto' => ['descripcion']
    ];
    return $mapa[$tabla] ?? [];
}

function obtenerOrdenamiento($tabla) {
    $mapa = [
        'centros_costo' => 'codigo',
        'areas' => 'codigo',
        'categorias_ocupacionales' => 'orden, codigo',
        'escalas_salariales' => 'escala_numero',
        'cargos_plantilla' => 'organo_grupo, nombre_cargo',
        'motivos_baja' => 'codigo',
        'configuracion_general' => 'parametro',
        'configuracion_rangos_impuesto' => 'desde'
    ];
    return $mapa[$tabla] ?? 'id';
}

function tieneCampoActivo($tabla) {
    $activas = ['centros_costo', 'areas', 'categorias_ocupacionales', 'escalas_salariales', 'cargos_plantilla', 'motivos_baja'];
    return in_array($tabla, $activas);
}

function obtenerDatosFormulario($tabla, $post) {
    $datos = [];
    switch($tabla) {
        case 'centros_costo':
            $datos = [
                'codigo' => trim(strtoupper($post['codigo'] ?? '')),
                'nombre' => trim($post['nombre'] ?? ''),
                'descripcion' => trim($post['descripcion'] ?? ''),
                'activo' => ($post['activo'] ?? 0) == 1 ? 1 : 0
            ];
            break;
        case 'areas':
            $datos = [
                'codigo' => intval($post['codigo'] ?? 0),
                'nombre_area' => trim($post['nombre_area'] ?? ''),
                'descripcion' => trim($post['descripcion'] ?? ''),
                'activo' => ($post['activo'] ?? 0) == 1 ? 1 : 0
            ];
            break;
        case 'categorias_ocupacionales':
            $datos = [
                'codigo' => trim(strtoupper($post['codigo'] ?? '')),
                'nombre' => trim($post['nombre'] ?? ''),
                'descripcion' => trim($post['descripcion'] ?? ''),
                'factor_incidencia' => floatval($post['factor_incidencia'] ?? 1),
                'orden' => intval($post['orden'] ?? 0),
                'activo' => ($post['activo'] ?? 0) == 1 ? 1 : 0
            ];
            break;
        case 'escalas_salariales':
            $datos = [
                'escala_numero' => intval($post['escala_numero'] ?? 0),
                'salario_mensual' => floatval($post['salario_mensual'] ?? 0),
                'salario_hora_ordinaria' => floatval($post['salario_hora_ordinaria'] ?? 0),
                'descripcion' => trim($post['descripcion'] ?? ''),
                'fecha_vigencia' => !empty($post['fecha_vigencia']) ? $post['fecha_vigencia'] : date('Y-m-d'),
                'activo' => ($post['activo'] ?? 0) == 1 ? 1 : 0
            ];
            break;
		case 'cargos_plantilla':
			$datos = [
				'organo_grupo' => trim($post['organo_grupo'] ?? ''),
				'nombre_cargo' => trim($post['nombre_cargo'] ?? ''),
				'categoria_ocupacional_id' => intval($post['categoria_ocupacional_id'] ?? 0),
				'nivel_preparacion' => trim($post['nivel_preparacion'] ?? ''),
				'escala_salarial_id' => intval($post['escala_salarial_id'] ?? 0),
				'activo' => isset($post['activo']) ? 1 : 0  // Cambio importante aquí
			];
			break;
        case 'motivos_baja':
            $datos = [
                'codigo' => intval($post['codigo'] ?? 0),
                'categoria' => trim($post['categoria'] ?? ''),
                'nombre' => trim($post['nombre'] ?? ''),
                'descripcion' => trim($post['descripcion'] ?? ''),
                'base_legal' => trim($post['base_legal'] ?? ''),
                'activo' => ($post['activo'] ?? 0) == 1 ? 1 : 0
            ];
            break;
        case 'configuracion_general':
            $datos = [
                'parametro' => trim($post['parametro'] ?? ''),
                'valor' => trim($post['valor'] ?? ''),
                'tipo_dato' => $post['tipo_dato'] ?? 'texto',
                'descripcion' => trim($post['descripcion'] ?? '')
            ];
            break;
        case 'configuracion_rangos_impuesto':
            $datos = [
                'desde' => floatval($post['desde'] ?? 0),
                'hasta' => !empty($post['hasta']) ? floatval($post['hasta']) : null,
                'tasa' => floatval($post['tasa'] ?? 0),
                'monto_fijo' => floatval($post['monto_fijo'] ?? 0),
                'fecha_vigencia' => !empty($post['fecha_vigencia']) ? $post['fecha_vigencia'] : date('Y-m-d'),
                'descripcion' => trim($post['descripcion'] ?? '')
            ];
            break;
    }
    return $datos;
}

function validarDatos($tabla, $datos) {
    switch($tabla) {
        case 'centros_costo':
        case 'areas':
            if (empty($datos['codigo'])) return 'El código numérico o identificador es obligatorio';
            if (empty($datos['nombre']) && empty($datos['nombre_area'])) return 'El nombre descriptivo es obligatorio';
            break;
        case 'categorias_ocupacionales':
            if (empty($datos['codigo'])) return 'El código es requerido';
            if (empty($datos['nombre'])) return 'El nombre es requerido';
            if ($datos['factor_incidencia'] <= 0) return 'El factor de incidencia debe ser superior a 0';
            break;
        case 'escalas_salariales':
            if ($datos['escala_numero'] <= 0) return 'El número de escala es requerido';
            if ($datos['salario_mensual'] <= 0) return 'El salario mensual debe ser mayor a 0';
            if ($datos['salario_hora_ordinaria'] < 0) return 'El salario por hora no puede ser negativo';
            if (empty($datos['fecha_vigencia'])) return 'La fecha de vigencia es obligatoria';
            break;
        case 'cargos_plantilla':
            if (empty($datos['organo_grupo'])) return 'El órgano o grupo del cargo es obligatorio';
            if (empty($datos['nombre_cargo'])) return 'El nombre del cargo es obligatorio';
            if (empty($datos['nivel_preparacion'])) return 'El nivel de preparación es obligatorio';
            if ($datos['categoria_ocupacional_id'] <= 0) return 'Debe asociar una categoría ocupacional activa';
            if ($datos['escala_salarial_id'] <= 0) return 'Debe asociar una escala salarial activa';
            break;
        case 'motivos_baja':
            if (empty($datos['codigo'])) return 'El código legal es obligatorio';
            if (empty($datos['nombre'])) return 'La descripción del motivo es obligatoria';
            break;
        case 'configuracion_general':
            if (empty($datos['parametro'])) return 'El nombre de parámetro es obligatorio';
            if (empty($datos['valor'])) return 'El valor de configuración es obligatorio';
            break;
        case 'configuracion_rangos_impuesto':
            if ($datos['desde'] < 0) return 'El rango inicial no puede ser menor a 0';
            if ($datos['tasa'] < 0) return 'La tasa impositiva no puede ser menor a 0';
            if ($datos['hasta'] !== null && $datos['hasta'] <= $datos['desde']) return 'El límite superior del rango debe ser mayor que el límite inferior';
            if (empty($datos['fecha_vigencia'])) return 'La fecha de vigencia es obligatoria';
            break;
    }
    return null;
}

function verificarDuplicado($pdo, $tabla, $datos, $id) {
    switch($tabla) {
        case 'centros_costo':
            $stmt = $pdo->prepare("SELECT id FROM centros_costo WHERE codigo = ? AND id != ?");
            $stmt->execute([$datos['codigo'], $id]);
            return $stmt->fetch() ? 'El código del centro de costo ya existe' : null;
        case 'areas':
            $stmt = $pdo->prepare("SELECT id FROM areas WHERE codigo = ? AND id != ?");
            $stmt->execute([$datos['codigo'], $id]);
            return $stmt->fetch() ? 'El código del área ya se encuentra registrado' : null;
        case 'categorias_ocupacionales':
            $stmt = $pdo->prepare("SELECT id FROM categorias_ocupacionales WHERE codigo = ? AND id != ?");
            $stmt->execute([$datos['codigo'], $id]);
            return $stmt->fetch() ? 'El código de la categoría ocupacional ya existe' : null;
        case 'escalas_salariales':
            $stmt = $pdo->prepare("SELECT id FROM escalas_salariales WHERE escala_numero = ? AND id != ?");
            $stmt->execute([$datos['escala_numero'], $id]);
            return $stmt->fetch() ? 'El número de escala ya se encuentra parametrizado' : null;
        case 'cargos_plantilla':
            $stmt = $pdo->prepare("SELECT id FROM cargos_plantilla WHERE nombre_cargo = ? AND organo_grupo = ? AND id != ?");
            $stmt->execute([$datos['nombre_cargo'], $datos['organo_grupo'], $id]);
            return $stmt->fetch() ? 'Este cargo ya existe en este grupo organizativo' : null;
        case 'motivos_baja':
            $stmt = $pdo->prepare("SELECT id FROM motivos_baja WHERE codigo = ? AND id != ?");
            $stmt->execute([$datos['codigo'], $id]);
            return $stmt->fetch() ? 'Este código de motivo ya existe' : null;
        case 'configuracion_general':
            $stmt = $pdo->prepare("SELECT id FROM configuracion_general WHERE parametro = ? AND id != ?");
            $stmt->execute([$datos['parametro'], $id]);
            return $stmt->fetch() ? 'Este parámetro de configuración ya existe' : null;
        case 'configuracion_rangos_impuesto':
            $stmt = $pdo->prepare("SELECT id FROM configuracion_rangos_impuesto WHERE desde = ? AND fecha_vigencia = ? AND id != ?");
            $stmt->execute([$datos['desde'], $datos['fecha_vigencia'], $id]);
            return $stmt->fetch() ? 'Ya existe un rango de impuesto con el mismo inicio en esta fecha de vigencia' : null;
    }
    return null;
}

function verificarDependencias($pdo, $tabla, $id) {
    switch($tabla) {
        case 'centros_costo':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE centro_costo_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            return $count > 0 ? "Existen {$count} trabajadores vinculados a este Centro de Costo" : null;
            
        case 'areas':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE area_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            return $count > 0 ? "Existen {$count} trabajadores vinculados a esta Área" : null;
            
        case 'categorias_ocupacionales':
            // Trabajadores vinculados
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE categoria_ocupacional_id = ?");
            $stmt->execute([$id]);
            $wCount = $stmt->fetchColumn();
            if ($wCount > 0) return "Operación denegada. {$wCount} trabajadores dependen de esta categoría";
            
            // Cargos vinculados
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cargos_plantilla WHERE categoria_ocupacional_id = ?");
            $stmt->execute([$id]);
            $cCount = $stmt->fetchColumn();
            if ($cCount > 0) return "Operación denegada. {$cCount} cargos en la plantilla dependen de esta categoría";
            return null;
            
        case 'escalas_salariales':
            // Trabajadores vinculados
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE escala_salarial_id = ?");
            $stmt->execute([$id]);
            $wCount = $stmt->fetchColumn();
            if ($wCount > 0) return "Operación denegada. {$wCount} trabajadores dependen de esta escala";
            
            // Cargos vinculados
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cargos_plantilla WHERE escala_salarial_id = ?");
            $stmt->execute([$id]);
            $cCount = $stmt->fetchColumn();
            if ($cCount > 0) return "Operación denegada. {$cCount} cargos de plantilla dependen de esta escala";
            return null;

        case 'cargos_plantilla':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE cargo_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            return $count > 0 ? "Operación denegada. El cargo se encuentra asignado a {$count} trabajadores" : null;

        case 'motivos_baja':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trabajadores WHERE motivo_baja = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            return $count > 0 ? "Operación denegada. {$count} trabajadores tienen este motivo de baja asignado" : null;
    }
    return null;
}

// Configuración general de visualización (desde constantes centrales con fallback)
$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas'
];

try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Datos de sesión unificados
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';
$user_email = $_SESSION['usuario_email'] ?? $_SESSION['user_email'] ?? '';

// Obtener logo en base64
$ruta_logo = '../../images/logotn.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $datos_logo = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($datos_logo);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Clasificadores - <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/buttons.dataTables.min.css" rel="stylesheet">

    <style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); overflow-x: hidden; color: #ffffff; }

        /* ===== MODO SOLO LECTURA (permisos por rol) ===== */
        body.solo-lectura .editar-item-btn,
        body.solo-lectura .eliminar-item-btn,
        body.solo-lectura [data-bs-target*="modalNuevo"],
        body.solo-lectura [data-bs-target*="modalEliminar"],
        body.solo-lectura [data-bs-target*="modalEditar"] { display: none !important; }

        /* Windows 11 Acrylic Background */
        .win11-bg {
            position: fixed; top:0; left:0; width:100%; height:100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top:0; left:0; width:100%; height:100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Glassmorphism */
        .glass-card {
            background: var(--panel-2); backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            padding:1.25rem;
        }

        /* Sidebar Windows 11 */
        .win-sidebar {
            position: fixed; left:0; top:0; height:100vh; width:16.25rem;
            background: var(--panel); backdrop-filter: blur(1.875rem);
            border-right: 0.0625rem solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width:5rem; }
        .win-sidebar.collapsed .sidebar-text { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding:0.75rem; }
        .win-sidebar.collapsed .nav-item i { margin:0; font-size:1.5rem; }

        .sidebar-logo { padding:1.5rem 1.25rem; border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08); margin-bottom:1.25rem; text-align: center; }
        .sidebar-logo h3 { font-size:1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin:0; }

        .sidebar-nav { flex: 1; padding:0 0.75rem; }
        .nav-item {
            display: flex; align-items: center; gap:0.875rem; padding:0.75rem 1rem;
            margin-bottom:0.375rem; border-radius: 0.75rem;
            color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
            cursor: pointer; text-decoration: none;
        }
        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 0.1875rem solid #60a5fa; }
		
        /* Main Content */
        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        /* Top Bar Windows 11 */
        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100 !important; position: relative !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        /* User Menu & Dropdowns */
        .user-menu { display: flex; align-items: center; gap:1rem; }

        .user-avatar { width:2.5rem; height:2.5rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 1050 !important; }
        .user-avatar:hover { transform: scale(1.05); }

        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
        .dropdown-menu-win {
            background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(1.25rem) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important; border-radius: 0.75rem !important;
            padding:0.5rem !important; box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.4) !important;
        }
        .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 0.5rem !important; padding:0.625rem 1rem !important; font-size:0.9rem !important; }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(148, 163, 184, 0.35) !important; border-top-width: 0.0625rem !important; opacity: 1 !important; margin:0.5rem 0.25rem !important; }
        .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }
        .dropdown-menu-win .dropdown-item small { font-size:0.65rem; color: rgba(255,255,255,0.6) !important; }
        .dropdown-menu-win .dropdown-item:hover small { color: #ffffff !important; }

        /* Botones e Inputs */
        .btn-primary-glass {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            color: white;
            padding:0.625rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary-glass:hover { transform: translateY(-0.125rem); box-shadow: 0 0.25rem 0.9375rem rgba(59, 130, 246, 0.3); }

        /* Grupo Imprimir / Exportar */
        .btn-export-main { border-radius: 0.75rem 0 0 0.75rem; padding:0.625rem 1rem; }
        .btn-export-toggle { border-radius: 0 0.75rem 0.75rem 0; padding:0.625rem 0.75rem; border-left: 0.0625rem solid rgba(255, 255, 255, 0.3); }
        .btn-export-toggle::after { margin-left:0; }

        .btn-success-glass { background: linear-gradient(135deg, var(--color-success), var(--color-success)); border: none; color: white; }
        .btn-danger-glass { background: linear-gradient(135deg, #ef4444, #dc2626); border: none; color: white; }

        .dark-input, .dark-textarea, .dark-select {
            background: var(--panel);
            border: 0.0625rem solid rgba(255, 255, 255, 0.15);
            border-radius: 0.75rem;
            color: #ffffff;
            padding:0.75rem 1rem;
            width:100%;
            transition: all 0.2s;
        }
        .dark-input:focus, .dark-textarea:focus, .dark-select:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 0 0 0 0.1875rem rgba(96, 165, 250, 0.2);
        }

        /* Overrides de visibilidad en tema oscuro (modales SweetAlert: Salvar con nombre, etc.) */
        .text-muted, .text-secondary { color: #9ca3af !important; }
        ::placeholder { color: rgba(255, 255, 255, 0.4) !important; opacity: 1 !important; }
        :-ms-input-placeholder { color: rgba(255, 255, 255, 0.4) !important; }
        ::-ms-input-placeholder { color: rgba(255, 255, 255, 0.4) !important; }
        .form-control::placeholder, .dark-input::placeholder, .dark-textarea::placeholder { color: rgba(255, 255, 255, 0.45) !important; }
        .form-control-sm::placeholder { color: rgba(255, 255, 255, 0.4) !important; font-size:0.75rem !important; }

        /* Selector de Clasificador */
        .clasificador-selector {
            background: var(--card-hover);
            border-radius: 1rem;
            padding:0.25rem;
            display: inline-flex;
            gap:0.25rem;
            margin-bottom:1.25rem;
            flex-wrap: wrap;
        }
        .clasificador-btn {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            padding:0.625rem 1.25rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .clasificador-btn:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .clasificador-btn.active { background: #3b82f6; color: white; }

        /* Estructuras de Tabla */
        .data-table-wrapper { overflow-x: auto; margin-top:1.25rem; }
        .table-custom { width:100%; border-collapse: collapse; }
        .table-custom th, .table-custom td { padding:0.75rem 1rem; border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08); text-align: left; }
        .table-custom th { color: #60a5fa; font-weight: 600; font-size:0.8rem; text-transform: uppercase; letter-spacing:0.0312rem; }

        /* Datatables Overrides */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: #e5e7eb !important; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background: var(--panel);
            border: 0.0625rem solid rgba(255, 255, 255, 0.15);
            border-radius: 0.75rem;
            color: #ffffff;
            padding:0.375rem 0.75rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: #e5e7eb !important;
            border-radius: 0.5rem;
            margin:0 0.125rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #3b82f6; color: white !important; }

        .action-buttons { display: flex; gap:0.5rem; }
        .action-btn { background: none; border: none; padding:0.375rem 0.625rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; }
        .action-btn.edit { color: #60a5fa; }
        .action-btn.edit:hover { background: rgba(96, 165, 250, 0.2); }
        .action-btn.delete { color: #ef4444; }
        .action-btn.delete:hover { background: rgba(239, 68, 68, 0.2); }
        .action-btn.toggle { color: #fbbf24; }
        .action-btn.toggle:hover { background: rgba(251, 191, 36, 0.2); }

        .filters-bar { display: flex; gap:0.9375rem; flex-wrap: wrap; margin-bottom:1.25rem; align-items: flex-end; }
        .filter-group { flex: 1; min-width:11.25rem; }
        .filter-label { font-size:0.7rem; color: #9ca3af; margin-bottom:0.375rem; text-transform: uppercase; letter-spacing:0.0312rem; }

        /* Vista de Permisos por Usuario (tarjetero) */
        .permisos-leyenda {
            display: flex; gap:0.875rem; flex-wrap: wrap; align-items: center;
            padding:0.625rem 0.875rem; background: rgba(255,255,255,0.04);
            border: 0.0625rem solid rgba(255,255,255,0.08); border-radius: 0.75rem;
            margin-bottom:1rem; font-size:0.75rem; color: #9ca3af;
        }
        .permisos-leyenda .chip { display: inline-flex; align-items: center; gap:0.3125rem; }
        .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(20.625rem, 1fr)); gap:1rem; }
        .perm-card {
            background: var(--card-hover); border: 0.0625rem solid rgba(255,255,255,0.08);
            border-radius: 1rem; overflow: hidden; transition: all 0.2s;
        }
        .perm-card:hover {
            transform: translateY(-0.1875rem); border-color: rgba(96,165,250,0.4);
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.35);
        }
        .perm-card-actual {
            border-color: rgba(14,165,233,0.55);
            box-shadow: 0 0 0 0.0625rem rgba(14,165,233,0.35), 0 0.5rem 1.5rem rgba(14,165,233,0.12);
        }
        .perm-card-head {
            display: flex; align-items: center; justify-content: space-between; gap:0.5rem;
            padding:0.75rem 1rem; background: linear-gradient(135deg, rgba(59,130,246,0.16), rgba(139,92,246,0.10));
            border-bottom: 0.0625rem solid rgba(255,255,255,0.08);
        }
        .perm-card-rol { font-weight: 700; font-size:0.95rem; color: #93c5fd; display: flex; align-items: center; gap:0.5rem; }
        .perm-card-turol { background: #0ea5e9; color: #fff; font-size:0.65rem; font-weight: 700; padding:0.1875rem 0.5625rem; border-radius: 6.1875rem; letter-spacing:0.025rem; }
        .perm-card-body { padding:0.5rem 1rem 0.75rem; }
        .perm-row {
            display: flex; align-items: center; justify-content: space-between; gap:0.5rem;
            padding:0.4375rem 0; border-bottom: 0.0625rem dashed rgba(255,255,255,0.06);
        }
        .perm-row:last-child { border-bottom: none; }
        .perm-row-nombre { font-size:0.8rem; color: #e5e7eb; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width:60%; }
        .perm-row-acciones { display: flex; gap:0.375rem; flex-shrink: 0; }
        .perm-chip {
            display: inline-flex; align-items: center; justify-content: center;
            width:1.5rem; height:1.5rem; border-radius: 0.4375rem; font-size:0.7rem;
        }
        .perm-chip-on { background: rgba(var(--color-success-rgb),0.16); color: var(--color-success-soft); border: 0.0625rem solid rgba(var(--color-success-rgb),0.35); }
        .perm-chip-off { background: rgba(255,255,255,0.05); color: #4b5563; border: 0.0625rem solid rgba(255,255,255,0.08); }

        /* Modales */
        .modal-glass .modal-content {
            background: var(--card);
            backdrop-filter: blur(1.25rem);
            border: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.25rem;
            color: #fff;
        }
        .modal-glass .modal-header { border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.2); }
        .modal-glass .modal-footer { border-top: 0.0625rem solid rgba(96, 165, 250, 0.2); }

        /* Switches */
        .switch { position: relative; display: inline-block; width:3.125rem; height:1.5rem; }
        .switch input { opacity: 0; width:0; height:0; }
        .slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color: #4b5563; transition: .3s; border-radius: 1.5rem; }
        .slider:before { position: absolute; content: ""; height:1.125rem; width:1.125rem; left:0.1875rem; bottom:0.1875rem; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--color-success); }
        input:checked + .slider:before { transform: translateX(1.625rem); }

        .estado-activo { color: var(--color-success-soft); font-weight: 600; }
        .estado-inactivo { color: #f87171; font-weight: 600; }

        @media (max-width: 768px) {
            .win-sidebar { transform: translateX(-100%); }
            .main-container { margin-left:0; }
        }
.btn-success-glass {
    background: linear-gradient(135deg, var(--color-success), var(--color-success));
    border: none;
    color: white;
    padding:0.625rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-success-glass:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.25rem 0.9375rem rgba(var(--color-success-rgb), 0.3);
}		
    </style>




</head>
<body class="<?php echo !$puede_editar_clasif ? 'solo-lectura' : ''; ?>">

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">
    
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-database me-2"></i>Clasificadores</h1>
                <p><i class="fas fa-tags me-1"></i> Configuración estructural e integridad de datos</p>
            </div>
        </div>
        
        <!-- Grupo Imprimir / Exportar del clasificador actual -->
        <div class="btn-group ms-3" role="group" aria-label="Acciones de Clasificador">
            <button class="btn-primary-glass btn-export-main" id="btnImprimirClasificador" title="Imprimir clasificador actual" data-tooltip="Imprimir clasificador" data-tooltip-theme="primary">
                <i class="fas fa-print me-2"></i>Imprimir
            </button>
            <button type="button" class="btn-primary-glass btn-export-toggle dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar clasificador actual" data-tooltip="Exportar clasificador" data-tooltip-theme="info">
                <span class="visually-hidden">Exportar</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win" id="exportClasificadorMenu">
                <li><a class="dropdown-item" href="#" id="btnExportClasPdf" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="danger"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasExcel" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="success"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel (XLSX)</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasWord" title="Exportar a Word" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word (DOCX)</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasTxt" title="Exportar a TXT" data-tooltip="Exportar a TXT" data-tooltip-theme="secondary"><i class="fas fa-file-alt me-2" style="color: #eab308;"></i>Exportar a TXT</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasCsv" title="Exportar a CSV" data-tooltip="Exportar a CSV" data-tooltip-theme="info"><i class="fas fa-file-csv me-2" style="color: var(--color-success);"></i>Exportar a CSV</a></li>
            </ul>
        </div>
        
    <?php include '../includes/user_menu.php'; ?>
	</div>

    <!-- Contenedor general del clasificador -->
    <div class="glass-card">
        
        <!-- Botonera selectora de clasificadores -->
        <div class="clasificador-selector">
            <button class="clasificador-btn" data-tabla="centros_costo">
                <i class="fas fa-chart-pie me-2"></i>Centros de Costo
            </button>
            <button class="clasificador-btn" data-tabla="areas">
                <i class="fas fa-building me-2"></i>Áreas
            </button>
            <button class="clasificador-btn" data-tabla="categorias_ocupacionales">
                <i class="fas fa-user-tag me-2"></i>Categorías Ocupacionales
            </button>
            <button class="clasificador-btn" data-tabla="escalas_salariales">
                <i class="fas fa-dollar-sign me-2"></i>Escalas Salariales
            </button>
            <button class="clasificador-btn" data-tabla="cargos_plantilla">
                <i class="fas fa-briefcase me-2"></i>Cargos (Plantilla)
            </button>
            <button class="clasificador-btn" data-tabla="motivos_baja">
                <i class="fas fa-exclamation-triangle me-2"></i>Motivos de Baja
            </button>
            <button class="clasificador-btn" data-tabla="configuracion_general">
                <i class="fas fa-cog me-2"></i>Configuración General
            </button>
            <button class="clasificador-btn" data-tabla="configuracion_rangos_impuesto">
                <i class="fas fa-percent me-2"></i>Rangos de Impuesto
            </button>
            <button class="clasificador-btn" data-tabla="permisos_usuario">
                <i class="fas fa-shield-halved me-2"></i>Permisos por Usuario
            </button>
        </div>
        
        <!-- Barra de filtros -->
<div class="filters-bar">
    <div class="filter-group">
        <div class="filter-label"><i class="fas fa-search me-1"></i> Buscar en tabla</div>
        <input type="text" id="searchInput" class="dark-input" placeholder="Escriba para filtrar..." autocomplete="off" title="Buscar en la tabla" data-tooltip="Buscar en la tabla" data-tooltip-theme="primary">
    </div>
    <div class="filter-group" id="filtroActivoGroup" style="display: none;">
        <div class="filter-label"><i class="fas fa-filter me-1"></i> Filtrar por estado</div>
        <select id="activoFilter" class="dark-select" title="Filtrar por estado" data-tooltip="Filtrar por estado" data-tooltip-theme="primary">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
        </select>
    </div>
    
    <!-- AÑADIR ESTE BLOQUE PARA EL BOTÓN LIMPIAR: -->
    <div class="filter-group" style="flex: 0 0 auto; min-width:auto;">
        <div class="filter-label">&nbsp;</div>
        <button id="btnLimpiarFiltros" class="btn" style="border-radius: 0.75rem; background: rgba(255,255,255,0.08); border: 0.0625rem solid rgba(255,255,255,0.15); color: white; padding:0.75rem 1.125rem; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'" title="Restablecer filtros de búsqueda" data-tooltip="Restablecer filtros de búsqueda" data-tooltip-theme="warning">
            <i class="fas fa-eraser me-1"></i> Limpiar
        </button>
    </div>

    <div class="filter-group">
        <div class="filter-label">&nbsp;</div>
        <button id="btnNuevo" class="btn-primary-glass" style="width:100%;" title="Crear nuevo registro en el clasificador" data-tooltip="Crear nuevo registro" data-tooltip-theme="success">
            <i class="fas fa-plus me-2"></i>Nuevo Registro
        </button>
    </div>
</div>

        <!-- Tabla dinâmica de registros -->
        <div class="data-table-wrapper">
            <table class="table-custom" id="tablaRegistros">
                <thead id="tablaHeader">
                    <tr><th>Cargando cabecera...</th></tr>
                </thead>
                <tbody id="tablaBody">
                    <tr><td colspan="10" class="text-center py-5"><i class="fas fa-spinner fa-pulse me-2"></i> Cargando registros desde la base de datos...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Vista de Permisos por Usuario (tarjetero) -->
        <div id="permisosView" style="display: none; margin-top:1.25rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3" style="margin-bottom:0.875rem;">
                <div>
                    <h3 class="mb-1" style="font-size:1.25rem; font-weight: 600; margin:0;">
                        <i class="fas fa-shield-halved me-2" style="color: #60a5fa;"></i>Permisos por Tipo de Usuario
                    </h3>
                    <p class="mb-0" style="color: #9ca3af; font-size:0.85rem; margin-top:0.25rem;">
                        Su usuario posee el rol de: <strong style="color: #93c5fd;"><span id="permRolActual">-</span></strong>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn-primary-glass" onclick="exportarPermisosPDF()">
                        <i class="fas fa-file-pdf me-2" style="color: #fca5a5;"></i>Exportar PDF
                    </button>
                    <button type="button" class="btn-primary-glass" onclick="imprimirPermisos()">
                        <i class="fas fa-print me-2" style="color: #fcd34d;"></i>Imprimir
                    </button>
                </div>
            </div>
            <div class="permisos-leyenda">
                <span class="chip"><i class="fas fa-eye" style="color: var(--color-success-soft);"></i> Ver</span>
                <span class="chip"><i class="fas fa-plus" style="color: #60a5fa;"></i> Crear</span>
                <span class="chip"><i class="fas fa-pen" style="color: #fbbf24;"></i> Editar</span>
                <span class="chip"><i class="fas fa-trash" style="color: #f87171;"></i> Eliminar</span>
                <span class="chip"><i class="fas fa-file-export" style="color: #a78bfa;"></i> Exportar</span>
                <span class="chip" style="margin-left:auto;"><i class="fas fa-circle-check" style="color: var(--color-success-soft);"></i> Permitido &nbsp;&middot;&nbsp; <i class="fas fa-circle-minus" style="color: #4b5563;"></i> Denegado</span>
            </div>
            <div id="permisosGrid" class="perm-grid"></div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- Modal para Crear/Editar -->
<div class="modal fade modal-glass" id="modalRegistro" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-plus me-2" style="color: #60a5fa;"></i>
                    <span>Nuevo Registro</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formRegistro">
                    <input type="hidden" id="registro_id" name="id" value="0">
                    <input type="hidden" id="tabla_actual" name="tabla_actual" value="">
                    <div id="formularioDinamico"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn-success-glass btn" id="btnGuardar">
                    <i class="fas fa-save me-2"></i>Guardar Registro
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/datatables/1.13.6/dataTables.buttons.min.js"></script>
<script src="../js/html2canvas.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>

<script>
// ==========================================
// FUNCIONES AUXILIARES GLOBALES (Compiladas al inicio)
// ==========================================
function numeroRomano(num) {
    const r = {1:'I',2:'II',3:'III',4:'IV',5:'V',6:'VI',7:'VII',8:'VIII',9:'IX',10:'X',11:'XI',12:'XII',13:'XIII',14:'XIV',15:'XV',16:'XVI',17:'XVII',18:'XVIII',19:'XIX',20:'XX',21:'XXI',22:'XXII',23:'XXIII',24:'XXIV',25:'XXV',26:'XXVI',27:'XXVII',28:'XXVIII',29:'XXIX',30:'XXX',31:'XXXI',32:'XXXII'};
    return r[num] || num;
}

// ==========================================
// CONFIGURACIÓN DE TABLAS Y FUENTES DB
// ==========================================
const SELECT_OPTIONS = {
    categorias: <?php echo json_encode($categorias_db); ?>,
    escalas: <?php echo json_encode($escalas_db); ?>
};

const EMPRESA_DATOS = {
    nombre: <?php echo json_encode($config_empresa['nombre_empresa'] ?? COMPANY_NAME); ?>,
    jefe: <?php echo json_encode($config_empresa['jefe_proyecto'] ?? JEFE_PROYECTO); ?>,
    especialista: <?php echo json_encode($config_empresa['especialista_gestion'] ?? ESPECIALISTA); ?>,
    logo: <?php echo json_encode($logo_base64); ?>
};
const USUARIO_ACTUAL = <?php echo json_encode($user_nombre_completo ?? 'Usuario'); ?>;

const TABLAS_CONFIG = {
    centros_costo: {
        nombre: 'Centros de Costo',
        icono: 'fa-chart-pie',
        columnas: [
            { data: 'codigo', titulo: 'Código' },
            { data: 'nombre', titulo: 'Nombre' },
            { data: 'descripcion', titulo: 'Descripción' },
            { data: 'activo', titulo: 'Estado' }
        ],
        tieneActivo: true,
        campos: [
            { name: 'codigo', label: 'CÓDIGO', type: 'text', required: true, uppercase: true },
            { name: 'nombre', label: 'NOMBRE', type: 'text', required: true },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'textarea', required: false },
            { name: 'activo', label: 'ACTIVO', type: 'switch', required: false }
        ]
    },
    areas: {
        nombre: 'Áreas',
        icono: 'fa-building',
        columnas: [
            { data: 'codigo', titulo: 'Código' },
            { data: 'nombre_area', titulo: 'Nombre' },
            { data: 'descripcion', titulo: 'Descripción' },
            { data: 'activo', titulo: 'Estado' }
        ],
        tieneActivo: true,
        campos: [
            { name: 'codigo', label: 'CÓDIGO', type: 'number', required: true },
            { name: 'nombre_area', label: 'NOMBRE DEL ÁREA', type: 'text', required: true },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'textarea', required: false },
            { name: 'activo', label: 'ACTIVO', type: 'switch', required: false }
        ]
    },
    categorias_ocupacionales: {
        nombre: 'Categorías Ocupacionales',
        icono: 'fa-user-tag',
        columnas: [
            { data: 'codigo', titulo: 'Código' },
            { data: 'nombre', titulo: 'Nombre' },
            { data: 'factor_incidencia', titulo: 'Factor Incidencia' },
            { data: 'orden', titulo: 'Orden' },
            { data: 'activo', titulo: 'Estado' }
        ],
        tieneActivo: true,
        campos: [
            { name: 'codigo', label: 'CÓDIGO', type: 'text', required: true, uppercase: true, maxlength: 5 },
            { name: 'nombre', label: 'NOMBRE', type: 'text', required: true },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'textarea', required: false },
            { name: 'factor_incidencia', label: 'FACTOR DE INCIDENCIA', type: 'number', required: true, step: 0.0001, value: 1.0 },
            { name: 'orden', label: 'ORDEN', type: 'number', required: true, value: 0 },
            { name: 'activo', label: 'ACTIVO', type: 'switch', required: false }
        ]
    },
	escalas_salariales: {
        nombre: 'Escalas Salariales',
        icono: 'fa-dollar-sign',
        columnas: [
            { data: 'escala_numero', titulo: 'N° Escala' },
            { data: 'salario_mensual', titulo: 'Salario Mensual' },
            { data: 'salario_hora_ordinaria', titulo: 'Salario Hora' }, // Nueva
            { data: 'salario_dia', titulo: 'Salario Día' },           // Nueva
            { data: 'fecha_vigencia', titulo: 'Vigencia' },
            { data: 'descripcion', titulo: 'Descripción' },
            { data: 'activo', titulo: 'Estado' }
        ],
        tieneActivo: true,
        campos: [
            { name: 'escala_numero', label: 'NÚMERO DE ESCALA', type: 'number', required: true },
            { name: 'salario_mensual', label: 'SALARIO MENSUAL (CUP)', type: 'number', required: true, step: 0.01 },
            { name: 'salario_hora_ordinaria', label: 'SALARIO POR HORA (CUP)', type: 'number', required: false, step: 0.0001 },
            { name: 'fecha_vigencia', label: 'FECHA VIGENCIA', type: 'date', required: true },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'text', required: false },
            { name: 'activo', label: 'ACTIVO', type: 'switch', required: false }
        ]
    },
    cargos_plantilla: {
        nombre: 'Cargos (Plantilla)',
        icono: 'fa-briefcase',
        columnas: [
            { data: 'organo_grupo', titulo: 'Órgano / Grupo' },
            { data: 'nombre_cargo', titulo: 'Nombre Cargo' },
            { data: 'categoria_texto', titulo: 'Categoría Ocupacional' },
            { data: 'nivel_preparacion', titulo: 'Nivel Prep.' },
            { data: 'escala_texto', titulo: 'Escala Salarial' },
            { data: 'activo', titulo: 'Estado' }
        ],
        tieneActivo: true,
        campos: [
            { name: 'organo_grupo', label: 'ÓRGANO / GRUPO', type: 'select', required: true, options: ['DIRECCIÓN', 'GRUPO TÉCNICO PRODUCTIVO', 'GRUPO DE CONSTRUCCIÓN Y MANTENIMIENTO', 'INDIRECTOS A LA PRODUCCIÓN'] },
            { name: 'nombre_cargo', label: 'NOMBRE DEL CARGO', type: 'text', required: true },
            { name: 'categoria_ocupacional_id', label: 'CATEGORÍA OCUPACIONAL', type: 'select_db', optionsKey: 'categorias', required: true },
            { name: 'nivel_preparacion', label: 'NIVEL DE PREPARACIÓN (E.g. NS, NMS)', type: 'text', required: true },
            { name: 'escala_salarial_id', label: 'ESCALA SALARIAL', type: 'select_db', optionsKey: 'escalas', required: true },
            { name: 'activo', label: 'ACTIVO', type: 'switch', required: false }
        ]
    },
    motivos_baja: {
        nombre: 'Motivos de Baja',
        icono: 'fa-exclamation-triangle',
        columnas: [
            { data: 'codigo', titulo: 'Código' },
            { data: 'categoria', titulo: 'Categoría' },
            { data: 'nombre', titulo: 'Nombre' },
            { data: 'base_legal', titulo: 'Base Legal' },
            { data: 'activo', titulo: 'Estado' }
        ],
        tieneActivo: true,
        campos: [
            { name: 'codigo', label: 'CÓDIGO', type: 'number', required: true },
            { name: 'categoria', label: 'CATEGORÍA', type: 'text', required: true },
            { name: 'nombre', label: 'NOMBRE', type: 'text', required: true },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'textarea', required: false },
            { name: 'base_legal', label: 'BASE LEGAL', type: 'text', required: false },
            { name: 'activo', label: 'ACTIVO', type: 'switch', required: false }
        ]
    },
    configuracion_general: {
        nombre: 'Configuración General',
        icono: 'fa-cog',
        columnas: [
            { data: 'parametro', titulo: 'Parámetro' },
            { data: 'valor', titulo: 'Valor' },
            { data: 'tipo_dato', titulo: 'Tipo' },
            { data: 'descripcion', titulo: 'Descripción' }
        ],
        tieneActivo: false,
        campos: [
            { name: 'parametro', label: 'PARÁMETRO', type: 'text', required: true },
            { name: 'valor', label: 'VALOR', type: 'text', required: true },
            { name: 'tipo_dato', label: 'TIPO DE DATO', type: 'select', required: true, options: ['entero', 'decimal', 'texto', 'fecha', 'booleano'] },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'textarea', required: false }
        ]
    },
    configuracion_rangos_impuesto: {
        nombre: 'Rangos de Impuesto',
        icono: 'fa-percent',
        columnas: [
            { data: 'desde', titulo: 'Desde (CUP)' },
            { data: 'hasta', titulo: 'Hasta (CUP)' },
            { data: 'tasa', titulo: 'Tasa (%)' },
            { data: 'monto_fijo', titulo: 'Monto Fijo' },
            { data: 'fecha_vigencia', titulo: 'Vigencia' },
            { data: 'descripcion', titulo: 'Descripción' }
        ],
        tieneActivo: false,
        campos: [
            { name: 'desde', label: 'DESDE (CUP)', type: 'number', required: true, step: 0.01 },
            { name: 'hasta', label: 'HASTA (CUP)', type: 'number', required: false, step: 0.01 },
            { name: 'tasa', label: 'TASA (%)', type: 'number', required: true, step: 0.0001 },
            { name: 'monto_fijo', label: 'MONTO FIJO (CUP)', type: 'number', required: true, step: 0.01, value: 0 },
            { name: 'fecha_vigencia', label: 'FECHA VIGENCIA', type: 'date', required: true },
            { name: 'descripcion', label: 'DESCRIPCIÓN', type: 'text', required: false }
        ]
    }
};

let dataTableInstance = null;
let ultimosRegistros = [];
let tablaActual = new URLSearchParams(window.location.search).get('tabla');
if (!tablaActual || !Object.keys(TABLAS_CONFIG).includes(tablaActual)) {
    tablaActual = localStorage.getItem('clasificador_actual') || 'centros_costo';
}
const abrirNuevo = new URLSearchParams(window.location.search).get('nuevo') === '1';
let paginaGuardada = 0;
var PRINT_TOOLBAR_HTML = '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style><div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">'
    + '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
    + '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#16a34a\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#22c55e\';this.style.transform=\'translateY(0)\';">'
    + '🖨️ Imprimir</button>'
    + '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#ef4444\';this.style.transform=\'translateY(0)\';">'
    + '✖ Cerrar</button>'
    + ''
    + '</div><div style="height:3.4375rem;" class="no-print"></div>'
    + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';

// ==========================================
// COMPONENTES UI Y REACCIONES DE TIEMPO
// ==========================================
function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const clockElement = document.getElementById('liveClock');
    if (clockElement) clockElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000);
updateClock();


// Cierre de Sesión seguro
function cerrarSesion() {
    fetch('../logout.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(() => { window.location.href = '../login.php?logout=1'; })
        .catch(() => { window.location.href = '../login.php?logout=1'; });
}
const logoutLogic = (e) => {
    e.preventDefault();
    Swal.fire({
        title: '<i class="fas fa-sign-out-alt" style="color: #ef4444"></i> Cerrar sesión',
        text: '¿Está seguro que desea salir del sistema?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#D13438',
        cancelButtonColor: '#2D2D2D',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, salir',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1F1F1F',
        color: '#FFFFFF'
    }).then((result) => { if (result.isConfirmed) cerrarSesion(); });
};
document.getElementById('logoutBtn')?.addEventListener('click', logoutLogic);
document.getElementById('logoutSidebarBtn')?.addEventListener('click', logoutLogic);

// ==========================================
// COMPONENTES DE TABLA DE DATOS
// ==========================================
function cargarRegistros(restaurarPagina = true) {
    const search = $('#searchInput').val();
    const activo_filter = $('#activoFilter').val();
    
    // 1. Destruir la tabla anterior de forma segura antes de realizar el AJAX
    if ($.fn.DataTable.isDataTable('#tablaRegistros')) {
        dataTableInstance = $('#tablaRegistros').DataTable();
        dataTableInstance.destroy();
        dataTableInstance = null;
    }
    
    // 2. Colocar una fila limpia de carga con el colspan correcto de la nueva tabla elegida
    const config = TABLAS_CONFIG[tablaActual];
    $('#tablaBody').html(`<tr><td colspan="${config.columnas.length + 1}" class="text-center py-5"><i class="fas fa-spinner fa-pulse me-2"></i> Cargando registros desde la base de datos...</td></tr>`);
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
            accion_ajax: 'listar',
            tabla: tablaActual,
            search: search,
            activo_filter: activo_filter
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderTabla(response.data);
                initDataTable(restaurarPagina);
            } else {
                $('#tablaBody').html(`<tr><td colspan="${config.columnas.length + 1}" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Error al cargar datos</td></tr>`);
            }
        },
        error: function() {
            $('#tablaBody').html(`<tr><td colspan="${config.columnas.length + 1}" class="text-center py-5 text-danger"><i class="fas fa-wifi me-2"></i> Error de conexión con el servidor</td></tr>`);
        }
    });
}

function renderTabla(registros) {
    const config = TABLAS_CONFIG[tablaActual];
    if (!config) return;
    
    ultimosRegistros = registros || [];
    
    // Renderizado estructurado de la cabecera
    let headerHtml = '<tr>';
    config.columnas.forEach(col => {
        headerHtml += `<th>${col.titulo}</th>`;
    });
    headerHtml += '<th style="width:7.5rem;">Acciones</th></tr>';
    $('#tablaHeader').html(headerHtml);
    
    if (!registros || registros.length === 0) {
        $('#tablaBody').html(`<tr><td colspan="${config.columnas.length + 1}" class="text-center py-5 text-muted"><i class="fas fa-inbox me-2"></i> No se encontraron registros</td></tr>`);
        return;
    }
    
    let html = '';
    registros.forEach(reg => {
        html += '<tr data-id="' + reg.id + '">';
        config.columnas.forEach(col => {
            html += `<td>${formatearCelda(reg, col, true)}</td>`;
        });
        
        html += `<td><div class="action-buttons">`;
        html += `<button class="action-btn edit" onclick="editarRegistro(${reg.id})" title="Editar"><i class="fas fa-edit"></i></button>`;
        
        if (config.tieneActivo) {
            const nuevoEstado = reg.activo == 1 ? 0 : 1;
            html += `<button class="action-btn toggle" onclick="toggleEstado(${reg.id}, ${reg.activo})" title="${reg.activo == 1 ? 'Desactivar' : 'Activar'}">`;
            html += `<i class="fas fa-${reg.activo == 1 ? 'ban' : 'check-circle'}"></i></button>`;
        }
        
        html += `<button class="action-btn delete" onclick="eliminarRegistro(${reg.id})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>`;
        html += `</div></td></tr>`;
    });
    $('#tablaBody').html(html);
}

// ==========================================
// FORMATO DE CELDAS PARA PANTALLA Y EXPORTACIÓN
// ==========================================
function formatearCelda(reg, col, paraPantalla) {
    const config = TABLAS_CONFIG[tablaActual];
    const tieneActivo = !!(config && config.tieneActivo);
    const valor = reg[col.data];
    
    if (col.data === 'categoria_texto') {
        return reg.cat_codigo ? `${reg.cat_codigo} - ${reg.cat_nombre}` : '—';
    } else if (col.data === 'escala_texto') {
        return reg.esc_num ? `Grupo: ${numeroRomano(reg.esc_num)} ($${parseFloat(reg.esc_salario).toFixed(2)})` : '—';
    } else if (col.data === 'activo' && tieneActivo) {
        const texto = valor == 1 ? 'ACTIVO' : 'INACTIVO';
        return paraPantalla ? `<span class="estado-${valor == 1 ? 'activo' : 'inactivo'}">${texto}</span>` : texto;
    } else if (col.data === 'salario_hora_ordinaria') {
        return '$' + (valor ? parseFloat(valor).toFixed(2) : '0.00');
    } else if (col.data === 'salario_dia') {
        const hora = parseFloat(reg.salario_hora_ordinaria) || 0;
        return new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(hora * 8);
    } else if (col.data === 'salario_mensual' && valor) {
        return new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(valor);
    } else if (col.data === 'factor_incidencia' && valor) {
        return parseFloat(valor).toFixed(4);
    } else if (col.data === 'tasa' && valor) {
        return parseFloat(valor).toFixed(2) + '%';
    } else if (col.data === 'fecha_vigencia' && valor) {
        const partes = valor.split('-');
        if (partes.length === 3) return `${partes[2]}/${partes[1]}/${partes[0]}`;
    } else if ((col.data === 'desde' || col.data === 'hasta' || col.data === 'monto_fijo') && valor !== null) {
        return new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(valor);
    } else if (valor === null || valor === '') {
        return '—';
    }
    return valor;
}

// Valor primitivo (número/texto) para XLSX/CSV/TXT
function valorExport(reg, col) {
    switch (col.data) {
        case 'activo':
            return reg.activo == 1 ? 'ACTIVO' : 'INACTIVO';
        case 'categoria_texto':
            return reg.cat_codigo ? `${reg.cat_codigo} - ${reg.cat_nombre}` : '';
        case 'escala_texto':
            return reg.esc_num ? `Grupo: ${numeroRomano(reg.esc_num)} (${parseFloat(reg.esc_salario).toFixed(2)})` : '';
        case 'salario_dia':
            return (parseFloat(reg.salario_hora_ordinaria) || 0) * 8;
        case 'salario_mensual':
        case 'salario_hora_ordinaria':
        case 'factor_incidencia':
        case 'tasa':
        case 'monto_fijo':
        case 'desde':
        case 'hasta': {
            const v = reg[col.data];
            return (v === null || v === undefined || v === '') ? '' : Number(v);
        }
        case 'fecha_vigencia': {
            const v = reg[col.data];
            if (!v) return '';
            const p = v.split('-');
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : v;
        }
        default: {
            const v = reg[col.data];
            return (v === null || v === undefined) ? '' : String(v);
        }
    }
}

function initDataTable(restaurarPagina = true) {
    if (restaurarPagina && dataTableInstance && $.fn.DataTable.isDataTable('#tablaRegistros')) {
        paginaGuardada = dataTableInstance.page();
    }
    
    if ($.fn.DataTable.isDataTable('#tablaRegistros')) {
        dataTableInstance = $('#tablaRegistros').DataTable();
        dataTableInstance.destroy();
        dataTableInstance = null;
    }
    
    // EVITAR INICIALIZAR DATATABLES SI SOLO SE TIENE LA FILA DE COMODÍN (SPINNER O "NO SE ENCONTRARON")
    if ($('#tablaBody tr td').length === 1 && $('#tablaBody tr td').attr('colspan')) {
        return; 
    }
    
    const config = TABLAS_CONFIG[tablaActual];
    const columnCount = config.columnas.length + 1;
    
    dataTableInstance = $('#tablaRegistros').DataTable({
        searching: false,
        paging: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
        language: {
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "paginate": {
                "first": '<i class="fas fa-step-backward"></i>',
                "last": '<i class="fas fa-step-forward"></i>',
                "next": '<i class="fas fa-chevron-right"></i>',
                "previous": '<i class="fas fa-chevron-left"></i>'
            }
        },
        responsive: true,
        order: [[0, 'asc']],
        pagingType: 'full_numbers',
        dom: 'lBfrtip',
        buttons: [
            {
                text: '<i class="fas fa-step-backward me-1"></i>',
                className: 'btn-win btn-sm buttons-first',
                titleAttr: 'Ir al primer registro',
                action: function(e, dt) {
                    dt.page('first').draw(false);
                }
            },
            {
                text: '<i class="fas fa-step-forward me-1"></i>',
                className: 'btn-win btn-sm buttons-last',
                titleAttr: 'Ir al último registro',
                action: function(e, dt) {
                    dt.page('last').draw(false);
                }
            }
        ],
        columnDefs: [
            { orderable: false, targets: columnCount - 1 }
        ]
    });
    
    if (restaurarPagina && paginaGuardada > 0) {
        let totalPages = dataTableInstance.page.info().pages;
        if (paginaGuardada < totalPages) {
            dataTableInstance.page(paginaGuardada).draw(false);
        } else if (totalPages > 0) {
            dataTableInstance.page(totalPages - 1).draw(false);
        }
        paginaGuardada = 0;
    }
}

// ==========================================
// FORMULARIOS DINÁMICOS RELACIONALES
// ==========================================
function generarFormulario(datos = null) {
    const config = TABLAS_CONFIG[tablaActual];
    if (!config) return;
    
    let html = '';
    config.campos.forEach(campo => {
        let valor = '';
        if (datos && datos[campo.name] !== undefined && datos[campo.name] !== null) {
            valor = datos[campo.name];
        } else if (campo.value !== undefined) {
            valor = campo.value;
        }
        
        html += `<div class="mb-3">`;
        html += `<label class="form-label small">${campo.label} ${campo.required ? '<span class="text-danger">*</span>' : ''}</label>`;
        
        if (campo.type === 'textarea') {
            html += `<textarea class="dark-textarea" id="${campo.name}" name="${campo.name}" rows="3" placeholder="${campo.label}">${escapeHtml(valor)}</textarea>`;
        } else if (campo.type === 'switch') {
            // Al crear un registro nuevo el switch se marca por defecto
            const isChecked = !datos ? true : (valor == 1 || valor === true || valor === '1');
            html += `<label class="switch">
                        <input type="checkbox" id="${campo.name}" name="${campo.name}" ${isChecked ? 'checked' : ''}>
                        <span class="slider"></span>
                     </label>`;
        } else if (campo.type === 'select') {
            html += `<select class="dark-select" id="${campo.name}" name="${campo.name}" ${campo.required ? 'required' : ''}>`;
            html += `<option value="">-- Seleccionar Opción --</option>`;
            campo.options.forEach(opt => {
                const selected = (valor == opt) ? 'selected' : '';
                html += `<option value="${opt}" ${selected}>${opt}</option>`;
            });
            html += `</select>`;
        } else if (campo.type === 'select_db') {
            html += `<select class="dark-select" id="${campo.name}" name="${campo.name}" ${campo.required ? 'required' : ''}>`;
            html += `<option value="">-- Seleccionar Opción --</option>`;
            const opciones = SELECT_OPTIONS[campo.optionsKey] || [];
            opciones.forEach(opt => {
                let texto = '';
                if (campo.optionsKey === 'categorias') {
                    texto = `${opt.codigo} - ${opt.nombre}`;
                } else if (campo.optionsKey === 'escalas') {
                    texto = `Grupo: ${numeroRomano(opt.escala_numero)} ($${parseFloat(opt.salario_mensual).toFixed(2)})`;
                }
                const selected = (valor == opt.id) ? 'selected' : '';
                html += `<option value="${opt.id}" ${selected}>${texto}</option>`;
            });
            html += `</select>`;
        } else {
            let attrs = '';
            if (campo.uppercase) attrs += ' style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()"';
            if (campo.step) attrs += ` step="${campo.step}"`;
            if (campo.maxlength) attrs += ` maxlength="${campo.maxlength}"`;
            html += `<input type="${campo.type}" class="dark-input" id="${campo.name}" name="${campo.name}" value="${escapeHtml(String(valor))}" ${campo.required ? 'required' : ''}${attrs}>`;
        }
        
        html += `</div>`;
    });
    
    $('#formularioDinamico').html(html);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==========================================
// CONTROLADOR DE OPERACIONES CRUD
// ==========================================
function editarRegistro(id) {
    console.log('Editando registro ID:', id, 'Tabla:', tablaActual);
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Cargando...',
        text: 'Recuperando información del registro',
        allowOutsideClick: false,
        showConfirmButton: false,
        background: '#1F1F1F',
        color: '#FFFFFF'
    });
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
            accion_ajax: 'obtener',
            tabla: tablaActual,
            id: id
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            console.log('Respuesta del servidor:', response);
            
            if (response.success) {
                // Asegurar que el ID esté presente
                if (!response.data.id && id) {
                    response.data.id = id;
                }
                
                console.log('Datos a cargar en formulario:', response.data);
                generarFormulario(response.data);
                $('#registro_id').val(response.data.id || id);
                $('#modalTitle span').text(`Editar ${TABLAS_CONFIG[tablaActual].nombre}`);
                $('#modalRegistro').modal('show');
            } else {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Error', 
                    text: response.error || 'No se pudo cargar el registro', 
                    background: '#1F1F1F', 
                    color: '#FFFFFF',
					confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            console.error('Error AJAX:', error, xhr.responseText);
            Swal.fire({ 
                icon: 'error', 
                title: 'Error de conexión', 
                text: 'No se pudieron recuperar los datos del registro', 
                background: '#1F1F1F', 
                color: '#FFFFFF',
				confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido'
            });
        }
    });
}


function eliminarRegistro(id) {
    Swal.fire({
        title: '<i class="fas fa-trash-alt" style="color: #ef4444"></i> Eliminar Registro',
        text: '¿Está seguro de continuar? Esta acción no es reversible.',
        icon: 'warning',
        background: '#1F1F1F',
        color: '#FFFFFF',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Eliminando...', text: 'Procesando operación', allowOutsideClick: false, showConfirmButton: false, background: '#1F1F1F', color: '#FFFFFF' });
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    accion_ajax: 'eliminar',
                    tabla: tablaActual,
                    id: id
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: response.message, background: '#1F1F1F', color: '#FFFFFF', timer: 2000, showConfirmButton: false });
                        cargarRegistros(true);
                    } else if (response.denied) {
                        notificarAccesoDenegado(response.error || response.message);
                    } else {
                        notificarError('No se pudo eliminar el registro', response.error || response.message);
                    }
                },
                error: function() {
                    Swal.close();
                    notificarError('Error de conexión', 'No se pudo completar la eliminación. Verifique su conexión e inténtelo de nuevo.');
                }
            });
        }
    });
}

function toggleEstado(id, estadoActual) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    
    Swal.fire({
        title: `<i class="fas fa-${nuevoEstado == 1 ? 'check-circle' : 'ban'}" style="color: #fbbf24"></i> ${accion.toUpperCase()} Registro`,
        text: `¿Desea cambiar el estado lógico a ${accion}?`,
        icon: 'question',
        background: '#1F1F1F',
        color: '#FFFFFF',
        showCancelButton: true,
        confirmButtonColor: '#fbbf24',
        confirmButtonText: `<i class="fas fa-${nuevoEstado == 1 ? 'check-circle' : 'ban'} me-2"></i>Sí, confirmar`,
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Actualizando...', text: 'Procesando cambios', allowOutsideClick: false, showConfirmButton: false, background: '#1F1F1F', color: '#FFFFFF' });
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    accion_ajax: 'toggle_estado',
                    tabla: tablaActual,
                    id: id,
                    activo: nuevoEstado
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        cargarRegistros(true);
                        Swal.fire({ icon: 'success', title: 'Actualizado', text: response.message, background: '#1F1F1F', color: '#FFFFFF', timer: 2000, showConfirmButton: false });
                    } else if (response.denied) {
                        notificarAccesoDenegado(response.error || response.message);
                    } else {
                        notificarError('No se pudo actualizar el estado', response.error || response.message);
                    }
                },
                error: function() {
                    Swal.close();
                    notificarError('Error de conexión', 'No se pudo actualizar el estado. Verifique su conexión e inténtelo de nuevo.');
                }
            });
        }
    });
}

// ==========================================
// PERSISTENCIA Y GUARDADO
// ==========================================
$('#btnGuardar').on('click', function() {
    const formData = new FormData(document.getElementById('formRegistro'));
    const data = {};
    formData.forEach((value, key) => { 
        data[key] = value; 
    });
    
    // IMPORTANTE: Para el checkbox activo
    if (TABLAS_CONFIG[tablaActual].tieneActivo) {
        data.activo = $('#activo').is(':checked') ? 1 : 0;
    }
    
    data.accion_ajax = 'guardar';
    data.tabla = tablaActual;
    data.id = $('#registro_id').val();
    
    console.log('Datos a guardar:', data); // Para depuración
    
    const btn = $(this);
    const originalHtml = btn.html();
    btn.html('<i class="fas fa-spinner fa-pulse me-2"></i>Guardando...').prop('disabled', true);
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            btn.html(originalHtml).prop('disabled', false);
            if (response.success) {
                $('#modalRegistro').modal('hide');
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Éxito', 
                    text: response.message, 
                    background: '#1F1F1F', 
                    color: '#FFFFFF', 
                    timer: 2000, 
                    showConfirmButton: false 
                });
                cargarRegistros(true);
            } else {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Error', 
                    text: response.error, 
                    background: '#1F1F1F', 
                    color: '#FFFFFF',
					confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido'
                });
            }
        },
        error: function(xhr, status, error) {
            btn.html(originalHtml).prop('disabled', false);
            console.error('Error al guardar:', error, xhr.responseText);
            Swal.fire({ 
                icon: 'error', 
                title: 'Error de conexión', 
                text: 'No se pudo procesar el almacenamiento', 
                background: '#1F1F1F', 
                color: '#FFFFFF',
				confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido'
            });
        }
    });
});

function limpiarFormulario() {
    $('#registro_id').val('0');
    generarFormulario(null);
    $('#modalTitle span').text(`Nuevo ${TABLAS_CONFIG[tablaActual].nombre}`);
}

// REEMPLAZAR LA ACCIÓN CLIC DEL BOTÓN NUEVO POR ESTA:
$('#btnNuevo').on('click', function() {
    limpiarFormulario();
    
    const tablasConAutocodigo = ['areas', 'centros_costo', 'motivos_baja', 'escalas_salariales', 'categorias_ocupacionales'];
    
    if (tablasConAutocodigo.includes(tablaActual)) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                accion_ajax: 'obtener_siguiente_codigo',
                tabla: tablaActual
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const sugerido = response.siguiente_codigo;
                    if (tablaActual === 'areas' || tablaActual === 'centros_costo' || tablaActual === 'motivos_baja') {
                        $('#codigo').val(sugerido);
                    } else if (tablaActual === 'escalas_salariales') {
                        $('#escala_numero').val(sugerido);
                    } else if (tablaActual === 'categorias_ocupacionales') {
                        $('#orden').val(sugerido);
                    }
                }
            }
        });
    }
    
    $('#modalRegistro').modal('show');
});

$('#modalRegistro').on('hidden.bs.modal', function() {
    limpiarFormulario();
});

// ==========================================
// CONTROLADOR DE CLASIFICADORES ACTIVOS
// ==========================================
function cambiarTabla(tabla) {
    tablaActual = tabla;
    localStorage.setItem('clasificador_actual', tabla);
    
    $('.clasificador-btn').removeClass('active');
    $(`.clasificador-btn[data-tabla="${tabla}"]`).addClass('active');
    
    // Vista especial de Permisos por Usuario (tarjetero)
    if (tabla === 'permisos_usuario') {
        $('.win-topbar .btn-group').addClass('d-none');
        $('.filters-bar').addClass('d-none');
        $('.data-table-wrapper').addClass('d-none');
        $('#permisosView').removeClass('d-none').show();
        $('.page-title h1').html('<i class="fas fa-shield-halved me-2"></i>Permisos por Usuario');
        $('.page-title p').html('<i class="fas fa-users-gear me-1"></i> Accesos y capacidades por tipo de usuario');
        renderPermisosTarjetas();
        return;
    }

    $('.win-topbar .btn-group').removeClass('d-none');
    $('.filters-bar').removeClass('d-none');
    $('.data-table-wrapper').removeClass('d-none');
    $('#permisosView').hide();

    const config = TABLAS_CONFIG[tabla];
    if (config.tieneActivo) {
        $('#filtroActivoGroup').show();
    } else {
        $('#filtroActivoGroup').hide();
    }
    
    $('.page-title h1').html(`<i class="fas ${config.icono} me-2"></i>${config.nombre}`);
    
    paginaGuardada = 0;
    $('#searchInput').val('');
    $('#activoFilter').val('');
    cargarRegistros(false);
}

// Renderiza el tarjetero de permisos por tipo de usuario
function renderPermisosTarjetas() {
    var cont = document.getElementById('permisosGrid');
    var rolSpan = document.getElementById('permRolActual');
    if (!cont) return;
    if (rolSpan) rolSpan.textContent = (typeof ROL_ACTUAL !== 'undefined' && ROL_ACTUAL) ? ROL_ACTUAL : 'Usuario';

    var m = (typeof PERMISOS_MATRIZ !== 'undefined') ? PERMISOS_MATRIZ : {};
    var order = ['Admin', 'Editor', 'Super', 'Soft', 'Visor'];
    var acciones = (typeof PERMISOS_ACCIONES !== 'undefined') ? PERMISOS_ACCIONES : [
        ['ver', 'Ver', '#34d399'], ['crear', 'Crear', '#60a5fa'], ['editar', 'Editar', '#fbbf24'],
        ['eliminar', 'Eliminar', '#f87171'], ['exportar', 'Exportar', '#a78bfa']
    ];
    var nombres = (typeof PERMISOS_NOMBRES_MODULOS !== 'undefined') ? PERMISOS_NOMBRES_MODULOS : {};
    var accIconos = ['fa-eye', 'fa-plus', 'fa-pen', 'fa-trash', 'fa-file-export'];
    var rolActual = (typeof ROL_ACTUAL !== 'undefined') ? ROL_ACTUAL : '';
    var html = '';

    order.forEach(function (rol) {
        var def = m[rol];
        if (!def) return;
        var esActual = (rol === rolActual);
        html += '<div class="perm-card' + (esActual ? ' perm-card-actual' : '') + '">';
        html += '<div class="perm-card-head">';
        html += '<span class="perm-card-rol"><i class="fas fa-user-tag"></i> ' + permEsc(rol) + '</span>';
        if (esActual) html += '<span class="perm-card-turol">TU ROL</span>';
        html += '</div>';
        html += '<div class="perm-card-body">';
        Object.keys(def).forEach(function (mod) {
            var perms = def[mod] || {};
            var etiqueta = nombres[mod] || mod;
            html += '<div class="perm-row">';
            html += '<span class="perm-row-nombre" title="' + permEsc(etiqueta) + '">' + permEsc(etiqueta) + '</span>';
            html += '<span class="perm-row-acciones">';
            acciones.forEach(function (a, ai) {
                var ok = !!perms[a[0]];
                html += '<span class="perm-chip ' + (ok ? 'perm-chip-on' : 'perm-chip-off') + '" title="' + permEsc(a[1]) + (ok ? ' - Permitido' : ' - Denegado') + '"><i class="fas ' + accIconos[ai] + '"></i></span>';
            });
            html += '</span>';
            html += '</div>';
        });
        html += '</div></div>';
    });

    cont.innerHTML = html;
}

$('.clasificador-btn').on('click', function() {
    cambiarTabla($(this).data('tabla'));
});

let debounceTimer;
$('#searchInput, #activoFilter').on('input change', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        paginaGuardada = 0;
        cargarRegistros(false);
    }, 300);
});
$('#btnLimpiarFiltros').on('click', function() {
    $('#searchInput').val('');
    $('#activoFilter').val('');
    paginaGuardada = 0;
    cargarRegistros(false);
});

$(document).ready(function() {
    // Advertencia de entrada: zona de cambios de datos sensibles
    Swal.fire({
        icon: 'warning',
        title: '<i class="fas fa-triangle-exclamation me-2" style="color: #fbbf24;"></i>¡Advertencia!',
        html: 'Está a punto de trabajar con <strong>cambios de datos estructurales</strong> del sistema.<br><br>' +
              '<i class="fas fa-hand-point-right me-2" style="color: #fbbf24;"></i>Proceda con <strong>extremo cuidado</strong>: cualquier modificación puede afectar los cálculos y la integridad de los registros.<br><br>' +
              '<i class="fas fa-user-shield me-2" style="color: #60a5fa;"></i>Solo continúe si es la <strong>persona responsable</strong> autorizada y posee el <strong>conocimiento necesario sobre el tema</strong>.',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido, continúo bajo mi responsabilidad',
        confirmButtonColor: '#D97706',
        showCloseButton: true,
        closeButtonHtml: '<i class="fas fa-times"></i>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        background: '#1F1F1F',
        color: '#FFFFFF'
    });

    cambiarTabla(tablaActual);
    if (abrirNuevo && TABLAS_CONFIG[tablaActual] && tablaActual !== 'permisos_usuario') {
        setTimeout(function() {
            $('#btnNuevo').trigger('click');
        }, 400);
    }
});

// ==========================================
// IMPRESIÓN Y EXPORTACIÓN DEL CLASIFICADOR
// (modos: 'print' | 'word' | 'pdf')
// ==========================================
function obtenerFechaHoraExport() {
    const ahora = new Date();
    const opciones = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true };
    return ahora.toLocaleString('es-ES', opciones);
}

function nombreArchivoClasificador(ext) {
    const base = (TABLAS_CONFIG[tablaActual]?.nombre || 'clasificador')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'clasificador';
    const f = new Date();
    const d = f.getFullYear() + ('0' + (f.getMonth() + 1)).slice(-2) + ('0' + f.getDate()).slice(-2);
    return 'Clasificador_' + base + '_' + d + '.' + ext;
}

function descargarBlob(blob, nombre) {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nombre;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}

function construirHtmlClasificador(opts) {
    opts = opts || {};
    const config = TABLAS_CONFIG[tablaActual];
    if (!config) return '';
    const registros = ultimosRegistros || [];
    const paraWord = opts.modo === 'word';
    const fechaHora = obtenerFechaHoraExport();
    
    // Línea de contexto de filtros
    const filtroTexto = $('#searchInput').val();
    const filtroEstado = $('#activoFilter').val();
    const filtros = [];
    if (filtroTexto) filtros.push('Búsqueda: "' + filtroTexto + '"');
    if (filtroEstado !== '') filtros.push('Estado: ' + (filtroEstado == 1 ? 'Activos' : 'Inactivos'));
    const lineaFiltros = (filtros.length ? filtros.join(' · ') : 'Sin filtros aplicados') + ' · Total de registros: ' + registros.length;
    
    // Cabecera de la tabla de datos
    let thead = '<tr>';
    config.columnas.forEach(col => { thead += '<th>' + col.titulo + '</th>'; });
    thead += '</tr>';
    
    // Paginado de filas para que el contenido no se recorte
    const filasPorPagina = 22;
    const paginas = [];
    if (registros.length === 0) {
        paginas.push([]);
    } else {
        for (let i = 0; i < registros.length; i += filasPorPagina) {
            paginas.push(registros.slice(i, i + filasPorPagina));
        }
    }
    
    const cuerpoPaginas = paginas.map((filas, idx) => {
        let tbody = '';
        if (filas.length === 0) {
            tbody = `<tr><td colspan="${config.columnas.length}" style="text-align:center; padding:1.875rem; color:#666;">No hay registros para este clasificador</td></tr>`;
        } else {
            filas.forEach(reg => {
                tbody += '<tr>';
                config.columnas.forEach(col => {
                    tbody += '<td>' + formatearCelda(reg, col, false) + '</td>';
                });
                tbody += '</tr>';
            });
        }
        return `
        <div class="page-sheet">
            <div class="print-header">
                <div class="logo-area">${EMPRESA_DATOS.logo ? (paraWord
                    ? `<img src="${EMPRESA_DATOS.logo}" width="113" height="113" style="width:3cm; height:3cm;">`
                    : `<img src="${EMPRESA_DATOS.logo}">`) : ''}</div>
                <div class="header-text">
                    <h1>${escapeHtml(EMPRESA_DATOS.nombre)}</h1>
                    <h2>${escapeHtml(config.nombre)}</h2>
                </div>
                <div class="header-right">
                    <div>Generado: ${fechaHora}</div>
                    <div>Por: ${escapeHtml(USUARIO_ACTUAL)}</div>
                </div>
            </div>
            <div class="filtros-line">${lineaFiltros}</div>
            <table class="data-table-print">
                ${thead}
                ${tbody}
            </table>
            <div class="print-footer">
                <div>${escapeHtml(EMPRESA_DATOS.nombre)}</div>
                <div>Página ${idx + 1} de ${paginas.length}</div>
            </div>
        </div>`;
    }).join('');
    
    let html = `<!DOCTYPE html>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <title>${escapeHtml(config.nombre)} - ${escapeHtml(EMPRESA_DATOS.nombre)}</title>
        <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
        <style>
            * { margin:0; padding:0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size:8.5pt; color: #000; background: #fff; }
            ${paraWord
                ? `@page WordSection1 { size: 792pt 612pt; mso-page-orientation: landscape; margin:0.5in 0.6in 0.75in 0.6in; }
                   div.WordSection1 { page: WordSection1; }
                   .page-sheet { display: block !important; width:100% !important; height:auto !important; page-break-after: auto !important; break-after: auto !important; }
                   .rep-word-break { page-break-before: always !important; break-before: page !important; }
                   .print-header { display: table !important; width:100% !important; }
                   .print-header .logo-area, .print-header .header-text, .print-header .header-right { display: table-cell !important; vertical-align: middle !important; }
                   .print-header td { border: none !important; }
                   .logo-area img { width:3cm !important; height:3cm !important; max-width:none !important; max-height:none !important; object-fit: contain; }
                   .print-footer { position: static !important; margin-top:1.875rem; }`
                : `@page { size: letter portrait; margin:0; }`}
            .page-sheet { width:215.9mm; height:279.4mm; padding:12mm 14mm 16mm 14mm; background: #fff; color: #000; position: relative; page-break-after: always; overflow: hidden; display: flex; flex-direction: column; }
            .print-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 0.125rem solid #004B87; padding-bottom:0.375rem; margin-bottom:0.5rem; }
            .logo-area { width:12%; display: flex; align-items: center; }
            .logo-area img { max-height:3.125rem; max-width:100%; }
            .header-text { text-align: center; width:60%; }
            .header-text h1 { font-size:11pt; color: #004B87; text-transform: uppercase; margin:0; }
            .header-text h2 { font-size:9.5pt; color: #333; margin-top:0.125rem; }
            .header-right { width:28%; text-align: right; font-size:7pt; color: #555; line-height:1.4; }
            .filtros-line { font-size:7.5pt; color: #444; margin-bottom:0.375rem; border-left: 0.1875rem solid #004B87; padding-left:0.375rem; }
            table.data-table-print { width:100%; border-collapse: collapse; }
            table.data-table-print th { background: #004B87; color: #fff; font-size:7.5pt; text-transform: uppercase; padding:0.3125rem 0.375rem; text-align: left; border: 0.0625rem solid #004B87; }
            table.data-table-print td { font-size:8pt; padding:0.25rem 0.375rem; border: 0.0625rem solid #cbd5e1; vertical-align: top; }
            table.data-table-print tr:nth-child(even) td { background: #f1f5f9; }
            .print-footer { position: absolute; bottom:6mm; left:14mm; right:14mm; display: flex; justify-content: space-between; font-size:7pt; color: #666; border-top: 0.0625rem solid #cbd5e1; padding-top:0.1875rem; }
            @media print { .no-print { display: none !important; } }
        </style>
    </head>
    <body>
    ${PRINT_TOOLBAR_HTML}
    ${paraWord ? '<div class="WordSection1">' : ''}
    ${cuerpoPaginas}
    ${paraWord ? '</div>' : ''}
    </body>
    </html>`;

    if (paraWord) {
        let primeraHoja = true;
        html = html.replace(/<div class="page-sheet[ "][^"]*"/g, function (match) {
            if (primeraHoja) { primeraHoja = false; return match; }
            return match.replace('<div class="page-sheet', '<div class="page-sheet rep-word-break');
        });
    }

    return html;
}

// ==========================================
// IMPRIMIR EL CLASIFICADOR ACTUAL
// ==========================================
document.getElementById('btnImprimirClasificador')?.addEventListener('click', function () {
    const html = construirHtmlClasificador({ modo: 'print' });
    if (!html) return;
    const ventana = window.open('', '_blank');
    if (!ventana) {
        Swal.fire({ icon: 'warning', title: 'Aviso', text: 'El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes.' });
        return;
    }
    ventana.document.write(html);
    ventana.document.close();
});

// ==========================================
// EXPORTAR A WORD (mismo contenido que la impresión)
// ==========================================
document.getElementById('btnExportClasWord')?.addEventListener('click', function (e) {
    e.preventDefault();
    const html = construirHtmlClasificador({ modo: 'word' });
    if (!html) return;
    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    descargarBlob(blob, nombreArchivoClasificador('doc'));
});

// ==========================================
// EXPORTAR A EXCEL (XLSX)
// ==========================================
document.getElementById('btnExportClasExcel')?.addEventListener('click', function (e) {
    e.preventDefault();
    exportarClasificadorExcel();
});

function exportarClasificadorExcel() {
    if (typeof XLSX === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'La librería de Excel no cargó correctamente.' });
        return;
    }
    const config = TABLAS_CONFIG[tablaActual];
    const registros = ultimosRegistros || [];
    const filas = [
        [config.nombre.toUpperCase(), EMPRESA_DATOS.nombre],
        ['Generado', obtenerFechaHoraExport()],
        ['Total de registros', registros.length],
        []
    ];
    filas.push(config.columnas.map(c => c.titulo));
    registros.forEach(reg => {
        filas.push(config.columnas.map(c => valorExport(reg, c)));
    });
    const ws = XLSX.utils.aoa_to_sheet(filas);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, (config.nombre || 'Clasificador').slice(0, 31));
    XLSX.writeFile(wb, nombreArchivoClasificador('xlsx'));
}

// ==========================================
// EXPORTAR A TXT
// ==========================================
document.getElementById('btnExportClasTxt')?.addEventListener('click', function (e) {
    e.preventDefault();
    exportarClasificadorTxt();
});

function exportarClasificadorTxt() {
    const config = TABLAS_CONFIG[tablaActual];
    const registros = ultimosRegistros || [];
    const lineas = [];
    lineas.push('LISTADO DE ' + config.nombre.toUpperCase());
    lineas.push('Empresa: ' + EMPRESA_DATOS.nombre);
    lineas.push('Generado: ' + obtenerFechaHoraExport());
    lineas.push('Total de registros: ' + registros.length);
    lineas.push('============================================================');
    lineas.push(config.columnas.map(c => c.titulo).join('\t'));
    registros.forEach(reg => {
        lineas.push(config.columnas.map(c => {
            const v = valorExport(reg, c);
            return (v === '' || v === null || v === undefined) ? '-' : String(v);
        }).join('\t'));
    });
    descargarBlob(new Blob([lineas.join('\r\n')], { type: 'text/plain;charset=utf-8' }), nombreArchivoClasificador('txt'));
}

// ==========================================
// EXPORTAR A CSV
// ==========================================
document.getElementById('btnExportClasCsv')?.addEventListener('click', function (e) {
    e.preventDefault();
    exportarClasificadorCsv();
});

function exportarClasificadorCsv() {
    const config = TABLAS_CONFIG[tablaActual];
    const registros = ultimosRegistros || [];
    const filas = [config.columnas.map(c => c.titulo)];
    registros.forEach(reg => {
        filas.push(config.columnas.map(c => valorExport(reg, c)));
    });
    const csv = filas.map(f => f.map(v => {
        const s = String(v === null || v === undefined ? '' : v);
        return /[;"\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }).join(';')).join('\r\n');
    descargarBlob(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }), nombreArchivoClasificador('csv'));
}

// ==========================================
// EXPORTAR A PDF (idéntico a la vista de impresión)
// ==========================================
document.getElementById('btnExportClasPdf')?.addEventListener('click', function (e) {
    e.preventDefault();
    exportarClasificadorPdf();
});

async function exportarClasificadorPdf() {
    if (typeof window.jspdf === 'undefined' || typeof window.html2canvas === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Las librerías de PDF no están disponibles.' });
        return;
    }
    if ((ultimosRegistros || []).length === 0) {
        Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros para exportar.' });
        return;
    }
    Swal.fire({ icon: 'info', title: 'Generando PDF...', text: 'Se está generando el PDF, espere unos segundos.', showConfirmButton: false, allowOutsideClick: false });

    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position: fixed; left: -1250rem; top: 0; width: 816px; height: 1056px; border: 0; background: #ffffff;';
    document.body.appendChild(iframe);

    try {
        const html = construirHtmlClasificador({ modo: 'pdf' });
        if (!html) throw new Error('No se pudo construir el documento.');

        const baseTag = '<base href="' + location.href + '">';
        const doc = iframe.contentDocument;
        doc.open();
        doc.write(html.replace('<head>', '<head>' + baseTag));
        doc.close();

        await new Promise(resolve => setTimeout(resolve, 400));

        const paginas = doc.querySelectorAll('.page-sheet');
        if (paginas.length === 0) throw new Error('No se encontraron páginas para exportar.');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'px', format: [816, 1056], compress: true });

        for (let i = 0; i < paginas.length; i++) {
            const canvas = await window.html2canvas(paginas[i], {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                windowWidth: 816,
                windowHeight: 1056
            });
            const img = canvas.toDataURL('image/jpeg', 0.95);
            if (i > 0) pdf.addPage([816, 1056], 'portrait');
            pdf.addImage(img, 'JPEG', 0, 0, 816, 1056);
        }

        pdf.save(nombreArchivoClasificador('pdf'));
        Swal.close();
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + err.message });
    } finally {
        document.body.removeChild(iframe);
    }
}
</script>

<!-- Botones flotantes de navegación rápida -->
<style>
.scroll-quick-btns { position: fixed; right:1.25rem; bottom:1.25rem; display: flex; flex-direction: column; gap:0.625rem; z-index: 950; }
.scroll-quick-btn {
    width:2.75rem; height:2.75rem; border-radius: 0.75rem; border: 0.0625rem solid rgba(148, 163, 184, .35);
    background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; font-size:1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, .5); transition: all .2s;
}
.scroll-quick-btn:hover { transform: translateY(-0.125rem); filter: brightness(1.15); }
.scroll-quick-btn.hidden { opacity: 0; pointer-events: none; transform: translateY(0.5rem); }
@media print { .scroll-quick-btns { display: none !important; } }
</style>
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnScrollTop" title="Ir al principio" data-tooltip="Ir al principio" data-tooltip-theme="primary">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnScrollBottom" title="Ir al final" data-tooltip="Ir al final" data-tooltip-theme="primary">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>
<script>
(function () {
    var btnTop = document.getElementById('btnScrollTop');
    var btnBottom = document.getElementById('btnScrollBottom');
    if (!btnTop || !btnBottom) return;
    function actualizarVisibilidad() {
        var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        var y = window.scrollY || document.documentElement.scrollTop;
        btnTop.classList.toggle('hidden', y < 150);
        btnBottom.classList.toggle('hidden', maxScroll <= 150 || y > maxScroll - 150);
    }
    btnTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    btnBottom.addEventListener('click', function () { window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' }); });
    window.addEventListener('scroll', actualizarVisibilidad);
    actualizarVisibilidad();
})();
</script>
</body>
</html>