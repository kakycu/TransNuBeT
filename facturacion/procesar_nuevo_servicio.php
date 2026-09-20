<?php
// procesar_nuevo_servicio.php
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si es petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nuevo_servicio.php');
    exit();
}

// Obtener datos del formulario
$codigo = isset($_POST['codigo']) ? trim(strtoupper($_POST['codigo'])) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$categoria_id = isset($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
$costo = isset($_POST['costo']) ? floatval($_POST['costo']) : 0;
$activo = isset($_POST['activo']) ? 1 : 0;

// Validaciones básicas
$errores = [];

if (empty($codigo)) {
    $errores[] = 'El código es obligatorio';
} elseif (strlen($codigo) < 2 || strlen($codigo) > 20) { // Cambiado de 10 a 20 según la tabla
    $errores[] = 'El código debe tener entre 2 y 20 caracteres';
} elseif (!preg_match('/^[A-Z0-9]+$/', $codigo)) {
    $errores[] = 'El código solo puede contener letras mayúsculas y números';
}

if (empty($descripcion)) {
    $errores[] = 'La descripción es obligatoria';
} elseif (strlen($descripcion) > 200) {
    $errores[] = 'La descripción no puede exceder 200 caracteres';
}

// Validar categoría (debe existir en clasif_cat_de_serv)
if ($categoria_id === null || $categoria_id <= 0) {
    $errores[] = 'La categoría es obligatoria';
}

if ($costo <= 0) {
    $errores[] = 'El costo debe ser mayor a 0';
}

// Si hay errores, guardar en sesión y redirigir
if (!empty($errores)) {
    $_SESSION['error'] = implode('<br>', $errores);
    $_SESSION['form_data'] = [
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'categoria_id' => $categoria_id,
        'costo' => $costo,
        'activo' => $activo
    ];
    header('Location: nuevo_servicio.php');
    exit();
}

try {
    $db = Database::getConnection();
    
    // Verificar si la categoría existe
    $sql_verificar_cat = "SELECT COUNT(*) as count FROM clasif_cat_de_serv WHERE id = :categoria_id";
    $stmt_verificar_cat = $db->prepare($sql_verificar_cat);
    $stmt_verificar_cat->execute(['categoria_id' => $categoria_id]);
    $result_cat = $stmt_verificar_cat->fetch(PDO::FETCH_ASSOC);
    
    if ($result_cat['count'] == 0) {
        $_SESSION['error'] = "La categoría seleccionada no existe.";
        $_SESSION['form_data'] = [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'categoria_id' => $categoria_id,
            'costo' => $costo,
            'activo' => $activo
        ];
        header('Location: nuevo_servicio.php');
        exit();
    }
    
    // Verificar si el código ya existe (doble verificación)
    $sql_verificar = "SELECT COUNT(*) as count FROM clasif_serv WHERE codigo = :codigo";
    $stmt_verificar = $db->prepare($sql_verificar);
    $stmt_verificar->execute(['codigo' => $codigo]);
    $result = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "El código '$codigo' ya existe en el sistema.";
        $_SESSION['form_data'] = [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'categoria_id' => $categoria_id,
            'costo' => $costo,
            'activo' => $activo
        ];
        header('Location: nuevo_servicio.php');
        exit();
    }
    
    // Insertar nuevo servicio
    $sql_insert = "INSERT INTO clasif_serv (codigo, descripcion, categoria_id, costo, activo, fecha_creacion) 
                   VALUES (:codigo, :descripcion, :categoria_id, :costo, :activo, NOW())";
    $stmt_insert = $db->prepare($sql_insert);
    
    $params = [
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'categoria_id' => $categoria_id,
        'costo' => $costo,
        'activo' => $activo
    ];
    
    $stmt_insert->execute($params);
    $servicio_id = $db->lastInsertId();
    
    // Registrar en el histórico (actualizado para coincidir con la estructura real)
    $sql_historico = "INSERT INTO historico_operaciones 
                      (operacion, descripcion, fecha_hora, usuario_id, usuario_nombre, ip_address) 
                      VALUES (:operacion, :descripcion, NOW(), :usuario_id, :usuario_nombre, :ip_address)";
    $stmt_historico = $db->prepare($sql_historico);
    
    // Obtener nombre del usuario actual
    $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario desconocido';
    
    $detalles = "Servicio creado - Código: $codigo, Descripción: $descripcion, Costo: $costo, Categoría ID: $categoria_id";
    
    $stmt_historico->execute([
        'operacion' => 'CREAR_SERVICIO',
        'descripcion' => $detalles,
        'usuario_id' => $_SESSION['usuario_id'],
        'usuario_nombre' => $usuario_nombre,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
    // Éxito
    $_SESSION['success'] = "Servicio '$codigo' creado exitosamente.";
    
    // Limpiar datos del formulario en sesión
    if (isset($_SESSION['form_data'])) {
        unset($_SESSION['form_data']);
    }
    
    header('Location: nuevo_servicio.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Error al crear servicio: " . $e->getMessage());
    $_SESSION['error'] = "Error al crear el servicio: " . $e->getMessage();
    $_SESSION['form_data'] = [
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'categoria_id' => $categoria_id,
        'costo' => $costo,
        'activo' => $activo
    ];
    header('Location: nuevo_servicio.php');
    exit();
} catch (Exception $e) {
    error_log("Error general al crear servicio: " . $e->getMessage());
    $_SESSION['error'] = "Error inesperado al crear el servicio.";
    $_SESSION['form_data'] = [
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'categoria_id' => $categoria_id,
        'costo' => $costo,
        'activo' => $activo
    ];
    header('Location: nuevo_servicio.php');
    exit();
}
?>