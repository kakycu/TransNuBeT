<?php
// clasificadores.php - Gestión unificada de clasificadores del sistema

// 1. Cargar base de datos primero (esto iniciará la sesión dentro de config.php de forma automática)
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once '../config/database.php';
}

// 2. Iniciar sesión únicamente si config.php no lo hizo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sincronización de sesión unificada
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

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

// Configuración general de visualización
$config_empresa = [
    'nombre_empresa' => 'PDL TransNuBeT',
    'jefe_proyecto' => 'Dainelys León Reyes',
    'especialista_gestion' => 'Mailén Pérez García'
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clasificadores - <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: #0c0c0c; overflow-x: hidden; color: #ffffff; }

        /* Windows 11 Acrylic Background */
        .win11-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Glassmorphism */
        .glass-card {
            background: rgba(28, 28, 35, 0.6); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            padding: 20px;
        }

        /* Sidebar Windows 11 */
        .win-sidebar {
            position: fixed; left: 0; top: 0; height: 100vh; width: 260px;
            background: rgba(20, 20, 25, 0.85); backdrop-filter: blur(30px);
            border-right: 1px solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width: 80px; }
        .win-sidebar.collapsed .sidebar-text { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding: 12px; }
        .win-sidebar.collapsed .nav-item i { margin: 0; font-size: 1.5rem; }

        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 20px; text-align: center; }
        .sidebar-logo h3 { font-size: 1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin: 0; }

        .sidebar-nav { flex: 1; padding: 0 12px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px;
            margin-bottom: 6px; border-radius: 12px;
            color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
            cursor: pointer; text-decoration: none;
        }
        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 3px solid #60a5fa; }
		
        /* Main Content */
        .main-container { margin-left: 260px; transition: all 0.3s ease; min-height: 100vh; padding: 20px; }
        .main-container.expanded { margin-left: 80px; }

        /* Top Bar Windows 11 */
        .win-topbar {
            background: rgba(20, 20, 25, 0.7); backdrop-filter: blur(20px); border-radius: 16px;
            padding: 12px 24px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100 !important; position: relative !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        .page-title p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 4px 0 0; }

        /* User Menu & Dropdowns */
        .user-menu { display: flex; align-items: center; gap: 16px; }

        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 1050 !important; }
        .user-avatar:hover { transform: scale(1.05); }

        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
        .dropdown-menu-win {
            background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 12px !important;
            padding: 8px !important; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
        }
        .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 8px !important; padding: 10px 16px !important; font-size: 0.9rem !important; }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(96, 165, 250, 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(255, 255, 255, 0.1) !important; }
        .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }
        .dropdown-menu-win .dropdown-item small { font-size: 0.65rem; color: rgba(255,255,255,0.6) !important; }
        .dropdown-menu-win .dropdown-item:hover small { color: #ffffff !important; }

        /* Botones e Inputs */
        .btn-primary-glass {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary-glass:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }

        /* Grupo Imprimir / Exportar */
        .btn-export-main { border-radius: 12px 0 0 12px; padding: 10px 16px; }
        .btn-export-toggle { border-radius: 0 12px 12px 0; padding: 10px 12px; border-left: 1px solid rgba(255, 255, 255, 0.3); }
        .btn-export-toggle::after { margin-left: 0; }

        .btn-success-glass { background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; }
        .btn-danger-glass { background: linear-gradient(135deg, #ef4444, #dc2626); border: none; color: white; }

        .dark-input, .dark-textarea, .dark-select {
            background: rgba(20, 20, 25, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #ffffff;
            padding: 12px 16px;
            width: 100%;
            transition: all 0.2s;
        }
        .dark-input:focus, .dark-textarea:focus, .dark-select:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }

        /* Selector de Clasificador */
        .clasificador-selector {
            background: rgba(28, 28, 35, 0.8);
            border-radius: 16px;
            padding: 4px;
            display: inline-flex;
            gap: 4px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .clasificador-btn {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .clasificador-btn:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .clasificador-btn.active { background: #3b82f6; color: white; }

        /* Estructuras de Tabla */
        .data-table-wrapper { overflow-x: auto; margin-top: 20px; }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th, .table-custom td { padding: 12px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left; }
        .table-custom th { color: #60a5fa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Datatables Overrides */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: #e5e7eb !important; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background: rgba(20, 20, 25, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #ffffff;
            padding: 6px 12px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: #e5e7eb !important;
            border-radius: 8px;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #3b82f6; color: white !important; }

        .action-buttons { display: flex; gap: 8px; }
        .action-btn { background: none; border: none; padding: 6px 10px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .action-btn.edit { color: #60a5fa; }
        .action-btn.edit:hover { background: rgba(96, 165, 250, 0.2); }
        .action-btn.delete { color: #ef4444; }
        .action-btn.delete:hover { background: rgba(239, 68, 68, 0.2); }
        .action-btn.toggle { color: #fbbf24; }
        .action-btn.toggle:hover { background: rgba(251, 191, 36, 0.2); }

        .filters-bar { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-label { font-size: 0.7rem; color: #9ca3af; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Modales */
        .modal-glass .modal-content {
            background: rgba(20, 20, 28, 0.96);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 20px;
            color: #fff;
        }
        .modal-glass .modal-header { border-bottom: 1px solid rgba(96, 165, 250, 0.2); }
        .modal-glass .modal-footer { border-top: 1px solid rgba(96, 165, 250, 0.2); }

        /* Switches */
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #4b5563; transition: .3s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(26px); }

        .estado-activo { color: #34d399; font-weight: 600; }
        .estado-inactivo { color: #f87171; font-weight: 600; }

        @media (max-width: 768px) {
            .win-sidebar { transform: translateX(-100%); }
            .main-container { margin-left: 0; }
        }
.btn-success-glass {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-success-glass:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}		
    </style>




</head>
<body>

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
            <button class="btn-primary-glass btn-export-main" id="btnImprimirClasificador">
                <i class="fas fa-print me-2"></i>Imprimir
            </button>
            <button type="button" class="btn-primary-glass btn-export-toggle dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar clasificador actual">
                <span class="visually-hidden">Exportar</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win" id="exportClasificadorMenu">
                <li><a class="dropdown-item" href="#" id="btnExportClasPdf"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasExcel"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel (XLSX)</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasWord"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word (DOCX)</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasTxt"><i class="fas fa-file-alt me-2" style="color: #eab308;"></i>Exportar a TXT</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportClasCsv"><i class="fas fa-file-csv me-2" style="color: #16a34a;"></i>Exportar a CSV</a></li>
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
        </div>
        
        <!-- Barra de filtros -->
<div class="filters-bar">
    <div class="filter-group">
        <div class="filter-label"><i class="fas fa-search me-1"></i> Buscar en tabla</div>
        <input type="text" id="searchInput" class="dark-input" placeholder="Escriba para filtrar..." autocomplete="off">
    </div>
    <div class="filter-group" id="filtroActivoGroup" style="display: none;">
        <div class="filter-label"><i class="fas fa-filter me-1"></i> Filtrar por estado</div>
        <select id="activoFilter" class="dark-select">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
        </select>
    </div>
    
    <!-- AÑADIR ESTE BLOQUE PARA EL BOTÓN LIMPIAR: -->
    <div class="filter-group" style="flex: 0 0 auto; min-width: auto;">
        <div class="filter-label">&nbsp;</div>
        <button id="btnLimpiarFiltros" class="btn" style="border-radius: 12px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: white; padding: 12px 18px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
            <i class="fas fa-eraser me-1"></i> Limpiar
        </button>
    </div>

    <div class="filter-group">
        <div class="filter-label">&nbsp;</div>
        <button id="btnNuevo" class="btn-primary-glass" style="width: 100%;">
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
    nombre: <?php echo json_encode($config_empresa['nombre_empresa'] ?? 'PDL TransNuBeT'); ?>,
    jefe: <?php echo json_encode($config_empresa['jefe_proyecto'] ?? 'Dainelys León Reyes'); ?>,
    especialista: <?php echo json_encode($config_empresa['especialista_gestion'] ?? 'Mailén Pérez García'); ?>
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
let tablaActual = localStorage.getItem('clasificador_actual') || 'centros_costo';
let paginaGuardada = 0;

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

const sidebar = document.getElementById('winSidebar');
const mainContainer = document.getElementById('mainContainer');
if (localStorage.getItem('winSidebarCollapsed') === 'true') {
    sidebar?.classList.add('collapsed');
    mainContainer?.classList.add('expanded');
}
document.getElementById('sidebarToggleBtn')?.addEventListener('click', () => {
    sidebar?.classList.toggle('collapsed');
    mainContainer?.classList.toggle('expanded');
    localStorage.setItem('winSidebarCollapsed', sidebar?.classList.contains('collapsed'));
});


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
    headerHtml += '<th style="width: 120px;">Acciones</th></tr>';
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
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        responsive: true,
        order: [[0, 'asc']],
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
            // Asegurar que el valor se evalúa correctamente para el switch
            const isChecked = (valor == 1 || valor === true || valor === '1');
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
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error de integridad', text: response.error, background: '#1F1F1F', color: '#FFFFFF', confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido' });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo completar la eliminación', background: '#1F1F1F', color: '#FFFFFF', confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido' });
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
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.error, background: '#1F1F1F', color: '#FFFFFF',confirmButtonText: '<i class="fas fa-check me-2"></i>Entenido' });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo actualizar el estado', background: '#1F1F1F', color: '#FFFFFF' });
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
    cambiarTabla(tablaActual);
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
            tbody = `<tr><td colspan="${config.columnas.length}" style="text-align:center; padding:30px; color:#666;">No hay registros para este clasificador</td></tr>`;
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
                <div class="logo-area"></div>
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
    
    return `<!DOCTYPE html>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <title>${escapeHtml(config.nombre)} - ${escapeHtml(EMPRESA_DATOS.nombre)}</title>
        <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 8.5pt; color: #000; background: #fff; }
            ${paraWord
                ? `@page WordSection1 { size: 8.5in 11in portrait; mso-page-orientation: portrait; margin: 0.5in; }
                   div.WordSection1 { page: WordSection1; }
                   .page-sheet { display: block !important; height: auto !important; page-break-after: always; }
                   .print-footer { position: static !important; margin-top: 30px; }`
                : `@page { size: letter portrait; margin: 0; }`}
            .page-sheet { width: 215.9mm; height: 279.4mm; padding: 12mm 14mm 16mm 14mm; background: #fff; color: #000; position: relative; page-break-after: always; overflow: hidden; display: flex; flex-direction: column; }
            .print-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #004B87; padding-bottom: 6px; margin-bottom: 8px; }
            .logo-area { width: 12%; }
            .header-text { text-align: center; width: 60%; }
            .header-text h1 { font-size: 11pt; color: #004B87; text-transform: uppercase; margin: 0; }
            .header-text h2 { font-size: 9.5pt; color: #333; margin-top: 2px; }
            .header-right { width: 28%; text-align: right; font-size: 7pt; color: #555; line-height: 1.4; }
            .filtros-line { font-size: 7.5pt; color: #444; margin-bottom: 6px; border-left: 3px solid #004B87; padding-left: 6px; }
            table.data-table-print { width: 100%; border-collapse: collapse; }
            table.data-table-print th { background: #004B87; color: #fff; font-size: 7.5pt; text-transform: uppercase; padding: 5px 6px; text-align: left; border: 1px solid #004B87; }
            table.data-table-print td { font-size: 8pt; padding: 4px 6px; border: 1px solid #cbd5e1; vertical-align: top; }
            table.data-table-print tr:nth-child(even) td { background: #f1f5f9; }
            .print-footer { position: absolute; bottom: 6mm; left: 14mm; right: 14mm; display: flex; justify-content: space-between; font-size: 7pt; color: #666; border-top: 1px solid #cbd5e1; padding-top: 3px; }
        </style>
    </head>
    <body>
    ${paraWord ? '<div class="WordSection1">' : ''}
    ${cuerpoPaginas}
    ${paraWord ? '</div>' : ''}
    ${opts.modo !== 'print' ? '' : `
    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); window.close(); }, 300);
        };
    <\/script>
    `}
    </body>
    </html>`;
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
    iframe.style.cssText = 'position: fixed; left: -20000px; top: 0; width: 816px; height: 1056px; border: 0; background: #ffffff;';
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
</body>
</html>