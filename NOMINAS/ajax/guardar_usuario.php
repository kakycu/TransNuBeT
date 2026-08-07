<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['usuario_id'] ?? 0);
$usuario = trim($_POST['usuario'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$primer_apellido = trim($_POST['primer_apellido'] ?? '');
$segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
$apellidos = $primer_apellido . ' ' . $segundo_apellido;
$no_ci = preg_replace('/\D/', '', $_POST['no_ci'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono_contacto = trim($_POST['telefono_contacto'] ?? '');
$direccion_particular = trim($_POST['direccion_particular'] ?? '');
$rol_id = intval($_POST['rol_id'] ?? 0);
$activo = isset($_POST['activo']) ? 1 : 0;
$password = $_POST['password'] ?? '';
$imagen_recortada = $_POST['imagen_recortada'] ?? '';
$eliminar_foto = isset($_POST['eliminar_foto']) ? intval($_POST['eliminar_foto']) : 0;

// Función para guardar la foto
function guardarFotoUsuario($imagen_base64, $id_usuario) {
    if (empty($imagen_base64)) return null;
    
    // Decodificar la imagen
    if (strpos($imagen_base64, 'base64,') !== false) {
        $imagen_base64 = explode('base64,', $imagen_base64)[1];
    }
    $imagen_decodificada = base64_decode($imagen_base64);
    if (!$imagen_decodificada) return null;
    
    // Crear carpeta si no existe
    $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/assets/imagenes/usuarios/';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    
    // Generar nombre único
    $nombre_archivo = 'user_' . $id_usuario . '_' . time() . '.jpg';
    $ruta_completa = $carpeta . $nombre_archivo;
    $ruta_relativa = 'assets/imagenes/usuarios/' . $nombre_archivo;
    
    if (file_put_contents($ruta_completa, $imagen_decodificada)) {
        return $ruta_relativa;
    }
    return null;
}

try {
    if ($action == 'crear') {
        // Validar campos obligatorios (email NO es obligatorio)
        if (empty($usuario) || empty($nombre) || empty($primer_apellido) || empty($segundo_apellido) || strlen($no_ci) != 11 || empty($rol_id) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Complete todos los campos obligatorios (*)']);
            exit;
        }
        
        // Verificar duplicados
        $check = $pdo->prepare("SELECT id FROM clasif_usuarios WHERE usuario = ? OR no_ci = ?");
        $check->execute([$usuario, $no_ci]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El usuario o Carnet de Identidad ya existe']);
            exit;
        }
        
        // Insertar usuario
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO clasif_usuarios (usuario, password, nombre, apellidos, no_ci, email, telefono_contacto, direccion_particular, rol_id, activo, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$usuario, $hashed, $nombre, $apellidos, $no_ci, $email ?: null, $telefono_contacto ?: null, $direccion_particular ?: null, $rol_id, $activo]);
        
        $nuevo_id = $pdo->lastInsertId();
        
        // Guardar foto si se recortó una
        $foto_ruta = null;
        if (!empty($imagen_recortada)) {
            $foto_ruta = guardarFotoUsuario($imagen_recortada, $nuevo_id);
            if ($foto_ruta) {
                $stmt = $pdo->prepare("UPDATE clasif_usuarios SET foto = ? WHERE id = ?");
                $stmt->execute([$foto_ruta, $nuevo_id]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente']);
    } 
    elseif ($action == 'editar') {
        // Validar campos obligatorios
        if (empty($usuario) || empty($nombre) || empty($primer_apellido) || empty($segundo_apellido) || strlen($no_ci) != 11 || empty($rol_id)) {
            echo json_encode(['success' => false, 'message' => 'Complete todos los campos obligatorios (*)']);
            exit;
        }
        
        // Verificar duplicados excluyendo el usuario actual
        $check = $pdo->prepare("SELECT id FROM clasif_usuarios WHERE (usuario = ? OR no_ci = ?) AND id != ?");
        $check->execute([$usuario, $no_ci, $id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El usuario o Carnet de Identidad ya existe en otro registro']);
            exit;
        }
        
        // Manejo de la foto
        $foto_actual = null;
        $stmt_foto = $pdo->prepare("SELECT foto FROM clasif_usuarios WHERE id = ?");
        $stmt_foto->execute([$id]);
        $foto_actual = $stmt_foto->fetchColumn();
        
        $foto_ruta = $foto_actual;
        
        // Eliminar foto si se solicitó
        if ($eliminar_foto == 1) {
            if ($foto_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_actual)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_actual);
            }
            $foto_ruta = null;
        }
        
        // Guardar nueva foto si se recortó
        if (!empty($imagen_recortada)) {
            // Eliminar foto anterior si existe
            if ($foto_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_actual)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_actual);
            }
            $foto_ruta = guardarFotoUsuario($imagen_recortada, $id);
        }
        
        // Actualizar usuario
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE clasif_usuarios SET usuario=?, password=?, nombre=?, apellidos=?, no_ci=?, email=?, telefono_contacto=?, direccion_particular=?, rol_id=?, foto=?, activo=?, fecha_actualizacion=NOW() WHERE id=?");
            $stmt->execute([$usuario, $hashed, $nombre, $apellidos, $no_ci, $email ?: null, $telefono_contacto ?: null, $direccion_particular ?: null, $rol_id, $foto_ruta, $activo, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE clasif_usuarios SET usuario=?, nombre=?, apellidos=?, no_ci=?, email=?, telefono_contacto=?, direccion_particular=?, rol_id=?, foto=?, activo=?, fecha_actualizacion=NOW() WHERE id=?");
            $stmt->execute([$usuario, $nombre, $apellidos, $no_ci, $email ?: null, $telefono_contacto ?: null, $direccion_particular ?: null, $rol_id, $foto_ruta, $activo, $id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>