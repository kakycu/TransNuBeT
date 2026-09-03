<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


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

// Control de acceso: solo quienes tienen permiso de edición gestionan otros usuarios.
// Un usuario sin ese permiso solo puede editar su propio perfil (p. ej. cambiar contraseña).
$id_actual = intval($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
$es_admin = permiso_puede('usuarios', 'editar');

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
    $carpeta = $_SERVER['DOCUMENT_ROOT'] . '/nominas/assets/imagenes/usuarios/';
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
        // Solo administradores pueden crear usuarios
        if (!$es_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado para crear usuarios']);
            exit;
        }
        
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
        // Solo administradores pueden editar a otros usuarios
        if (!$es_admin && $id_actual != $id) {
            echo json_encode(['success' => false, 'message' => 'No autorizado para editar este usuario']);
            exit;
        }
        
        // Un usuario no administrador no puede cambiar su rol ni estado
        if (!$es_admin) {
            $stmt_cur = $pdo->prepare("SELECT rol_id, activo FROM clasif_usuarios WHERE id = ?");
            $stmt_cur->execute([$id]);
            $cur = $stmt_cur->fetch(PDO::FETCH_ASSOC);
            if ($cur) {
                $rol_id = (int)$cur['rol_id'];
                $activo = (int)$cur['activo'];
            }
        }

        // El propio usuario logueado nunca puede desactivarse a sí mismo, aunque sea admin
        if ($id == $id_actual && $activo == 0) {
            $activo = 1;
        }
        
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
            if ($foto_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/nominas/' . $foto_actual)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/nominas/' . $foto_actual);
            }
            $foto_ruta = null;
        }
        
        // Guardar nueva foto si se recortó
        if (!empty($imagen_recortada)) {
            // Eliminar foto anterior si existe
            if ($foto_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/nominas/' . $foto_actual)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/nominas/' . $foto_actual);
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
        
        // Si el usuario editado es el propio usuario logueado, refrescar la sesión
        // para que el menú superior derecho y las variables PHP reflejen los cambios.
        if ($id == $id_actual) {
            $rol_info = ['codigo' => '', 'descripcion' => ''];
            $stmt_rol = $pdo->prepare("SELECT codigo, descripcion FROM clasif_rol WHERE id = ?");
            $stmt_rol->execute([$rol_id]);
            $rol_fila = $stmt_rol->fetch(PDO::FETCH_ASSOC);
            if ($rol_fila) {
                $rol_info['codigo'] = $rol_fila['codigo'];
                $rol_info['descripcion'] = $rol_fila['descripcion'];
            }
            
            $nombre_completo = trim($nombre . ' ' . $apellidos);
            $_SESSION['user_id'] = $id;
            $_SESSION['usuario_id'] = $id;
            $_SESSION['username'] = $usuario;
            $_SESSION['user_nombre'] = $nombre_completo;
            $_SESSION['usuario_nombre'] = $nombre_completo;
            $_SESSION['user_ci'] = $no_ci;
            $_SESSION['usuario_ci'] = $no_ci;
            $_SESSION['user_email'] = $email;
            $_SESSION['usuario_email'] = $email;
            $_SESSION['rol_id'] = $rol_id;
            $_SESSION['rol_codigo'] = $rol_info['codigo'];
            $_SESSION['rol_descripcion'] = $rol_info['descripcion'];
            $_SESSION['usuario_rol'] = $rol_info['codigo'];
        }
        
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>