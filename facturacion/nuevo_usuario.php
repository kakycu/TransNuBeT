<?php
// nuevo_usuario.php
// para CREAR y EDITAR usuarios con recorte de imagen 7x7cm
require_once 'config/header.php';

// --- 1. GESTIÓN DE ALERTAS Y SESIÓN ---
$alerta_exito = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : null;
$alerta_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : null;
$redireccionar = false;

// Limpiamos la sesión
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);
unset($_SESSION['alert_message']);

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Inicializar variables
$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : null;
$es_edicion = ($id_usuario !== null);
$titulo_pagina = $es_edicion ? "Editar Datos de Usuario" : "Crear Nuevo Usuario";
$error = "";

// Datos por defecto
$datos_usuario = [
    'nombre' => '',
    'apellidos' => '',
    'no_ci' => '',
    'direccion_particular' => '',
    'telefono_contacto' => '',
    'rol_id' => '',
    'foto' => '',
    'usuario' => '',
    'email' => '',
    'activo' => 1
];

$roles_disponibles = [];
$total_facturas = $total_clientes = $total_categorias = $total_servicios = $total_usuarios = 0;
$usuario = [];
$finanzas = [];

try {
    $db = Database::getConnection();

    // Obtener usuario actual
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }

    // Verificar permisos
    if (!in_array($usuario['rol_id'], [1, 3, 4, 5])) {
        $role_names = [
            1 => 'Administrador',
            2 => 'Visualizador',
            3 => 'Editor / Facturador',
            4 => 'Supervisor',
            5 => 'Programador'
        ];
        $user_role_name = isset($role_names[$usuario['rol_id']])
                        ? $role_names[$usuario['rol_id']]
                        : 'Desconocido';

        echo '<script src="js/sweetalert211.js"></script>';
        echo '<link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">';
        echo '<style>
            .badge-admin { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-user { background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-operator { background: #0d6efd; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-supervisor { background: #198754; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-programador { background: #4a0d8c; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
        </style>';

        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            let badgeClass = "";
            let badgeIcon = "";
            let requiredRoles = "";

            switch(' . $usuario['rol_id'] . ') {
                case 1: badgeClass = "badge-admin"; badgeIcon = "crown"; break;
                case 2: badgeClass = "badge-user"; badgeIcon = "eye"; break;
                case 3: badgeClass = "badge-operator"; badgeIcon = "edit"; break;
                case 4: badgeClass = "badge-supervisor"; badgeIcon = "user-shield"; break;
                case 5: badgeClass = "badge-programador"; badgeIcon = "fa-helmet-safety"; break;
                default: badgeClass = "badge-user"; badgeIcon = "user";
            }

            requiredRoles = "<span class=\'badge-admin me-1\'><i class=\'fas fa-crown me-1\'></i>Admin</span>" +
                           "<span class=\'badge-operator me-1\'><i class=\'fas fa-edit me-1\'></i>Editor</span>" +
                           "<span class=\'badge-supervisor\'><i class=\'fas fa-user-shield me-1\'></i>Super</span>" +
                           "<span class=\'badge-programador\'><i class=\'fas fa-helmet-safety me-1\'></i>Programador</span>";

            Swal.fire({
                title: "Acceso Denegado",
                html: `
                    <div class="text-start">
                        <p>No tiene permisos suficientes para acceder a esta sección.</p>
                        <div class="alert alert-danger p-3 mt-2">
                            <i class="fas fa-user-tag me-2"></i>
                            <strong>Su Tipo de Usuario:</strong>
                                <span class="badge ${badgeClass}">
                                    <i class="fas fa-${badgeIcon} me-1"></i>
                                     ' . $user_role_name . '
                                </span>
                                <p class="mb-0 mt-1 small">
                                    ' . ($usuario['rol_id'] == 2 ? "Visualizador (solo lectura)" : "Usuario con permisos limitados") . '
                                </p>
                        </div>
                        <div class="alert alert-warning p-3">
                            <i class="fas fa-lock me-2"></i>
                            <strong>Se requieren estos privilegios:</strong><br>
                            <div class="mt-2">
                                ${requiredRoles}
                            </div>
                        </div>
                        <p class="mb-0 small text-muted mt-3">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Contacte al administrador si necesita acceder a esta función.
                        </p>
                    </div>
                `,
                icon: "error",
                confirmButtonColor: "#dc3545",
                confirmButtonText: "<i class=\"fas fa-check me-2\"></i>Entendido",
                backdrop: "rgba(0,0,0,0.8)",
                allowOutsideClick: false,
                allowEscapeKey: false,
                willClose: () => {
                    window.location.href = "dashboard.php";
                }
            });
        });
        </script>';
        exit();
    }

    // Obtener estadísticas
    $stmt = $db->query("SELECT COUNT(*) as total FROM tbl_fact");
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_clientes");
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_cat_de_serv");
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_serv");
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_usuarios");
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Cargar datos si es edición
    if ($es_edicion) {
        $stmt_load = $db->prepare("SELECT * FROM clasif_usuarios WHERE id = :id");
        $stmt_load->execute(['id' => $id_usuario]);
        $usuario_db = $stmt_load->fetch(PDO::FETCH_ASSOC);

        if ($usuario_db) {
            $datos_usuario = $usuario_db;
        } else {
            throw new Exception("El usuario solicitado no existe.");
        }
    }

    // Obtener lista de Roles
    $stmt_roles = $db->query("SELECT * FROM clasif_rol ORDER BY descripcion ASC");
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    // Procesar Formulario POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre']);
        $apellidos = trim($_POST['apellidos']);
        $no_ci = trim($_POST['no_ci']);
        $direccion = trim($_POST['direccion_particular']);
        $telefono = trim($_POST['telefono_contacto']);
        $email = trim($_POST['email']);

        // Validaciones
        if (!empty($telefono) && !preg_match('/^[+]?[0-9]{8,15}$/', $telefono)) {
            throw new Exception("El teléfono no es válido. Solo números y '+' (mínimo 8 dígitos).");
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.");
        }

        $rol_id = intval($_POST['rol_id']);
        $usuario_login = trim($_POST['usuario']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validación de contraseña
        if (!$es_edicion) {
            if (empty($password)) {
                throw new Exception("La contraseña es obligatoria para nuevo usuario.");
            }
            if (strlen($password) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres.");
            }
        } else {
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres (si desea cambiarla).");
                }
            }
        }

        if (empty($nombre) || empty($apellidos) || empty($usuario_login) || empty($no_ci)) {
            throw new Exception("Faltan campos obligatorios.");
        }

        // PROCESAR FOTO RECORTADA (enviada como base64)
        $ruta_foto = $datos_usuario['foto']; // Mantener foto anterior por defecto

        if (isset($_POST['imagen_recortada']) && !empty($_POST['imagen_recortada'])) {
            $directorio_usuarios = 'assets/imagenes/usuarios/';

            // Crear directorio si no existe
            if (!file_exists($directorio_usuarios)) {
                mkdir($directorio_usuarios, 0777, true);
            }

            // Eliminar foto anterior si existe
            if (!empty($datos_usuario['foto']) && file_exists($datos_usuario['foto'])) {
                if (strpos($datos_usuario['foto'], 'assets/imagenes/usuarios/') === 0) {
                    @unlink($datos_usuario['foto']);
                }
            }

            // Decodificar imagen base64
            $imagen_base64 = $_POST['imagen_recortada'];
            $imagen_base64 = str_replace('data:image/jpeg;base64,', '', $imagen_base64);
            $imagen_base64 = str_replace(' ', '+', $imagen_base64);
            $imagen_decodificada = base64_decode($imagen_base64);

            // Generar nombre único
            $nombre_unico = 'user_' . time() . '_' . uniqid() . '.jpg';
            $ruta_completa = $directorio_usuarios . $nombre_unico;

            // Guardar imagen
            if (file_put_contents($ruta_completa, $imagen_decodificada)) {
                $ruta_foto = $ruta_completa;
            } else {
                throw new Exception("Error al guardar la imagen recortada.");
            }
        }

        if ($es_edicion) {
            // ACTUALIZAR
            $sql_update = "UPDATE clasif_usuarios SET
                nombre=:n, apellidos=:a, no_ci=:ci, direccion_particular=:d,
                telefono_contacto=:t, rol_id=:r, usuario=:u, email=:e,
                activo=:act, foto=:f, fecha_actualizacion=NOW()";
            $params = [
                ':n' => $nombre, ':a' => $apellidos, ':ci' => $no_ci, ':d' => $direccion,
                ':t' => $telefono, ':r' => $rol_id, ':u' => $usuario_login,
                ':e' => $email, ':act' => $activo, ':f' => $ruta_foto, ':id' => $id_usuario
            ];

            if (!empty($password)) {
                $sql_update .= ", password = :p";
                $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql_update .= " WHERE id = :id";

            $stmt = $db->prepare($sql_update);
            $stmt->execute($params);

            $sql_historico = "INSERT INTO historico_operaciones
                             (operacion, descripcion, usuario_id, usuario_nombre, ip_address, fecha_hora)
                             VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address, NOW())";

            $detalle_hist = "Actualizó datos del usuario: $nombre $apellidos (Usuario: $usuario_login, ID: $id_usuario)";
            $stmt_hist = $db->prepare($sql_historico);
            $stmt_hist->execute([
                'operacion'      => 'EDITAR_USUARIO',
                'descripcion'    => $detalle_hist,
                'usuario_id'     => $_SESSION['usuario_id'],
                'usuario_nombre' => $_SESSION['usuario_nombre'],
                'ip_address'     => $_SERVER['REMOTE_ADDR']
            ]);

            $alerta_exito = "El Usuario: " . $nombre . " se ha actualizado correctamente.";
            $redireccionar = true;
        } else {
            // INSERTAR
            $sql_find_gap = "
                SELECT COALESCE(MIN(t1.id + 1), 1)
                FROM clasif_usuarios t1
                LEFT JOIN clasif_usuarios t2 ON t1.id + 1 = t2.id
                WHERE t2.id IS NULL;
            ";
            $stmt_find_gap = $db->prepare($sql_find_gap);
            $stmt_find_gap->execute();
            $id_para_insertar = (int)$stmt_find_gap->fetchColumn();

            if ($id_para_insertar <= 0) {
                $id_para_insertar = 1;
            }

            $sql_insert = "INSERT INTO clasif_usuarios
                (id, nombre, apellidos, no_ci, direccion_particular, telefono_contacto,
                rol_id, foto, usuario, password, email, activo, fecha_registro)
                VALUES
                (:id_val, :n, :a, :ci, :d, :t, :r, :f, :u, :p, :e, :act, NOW())";

            $stmt = $db->prepare($sql_insert);
            $stmt->execute([
                ':id_val' => $id_para_insertar,
                ':n' => $nombre,
                ':a' => $apellidos,
                ':ci' => $no_ci,
                ':d' => $direccion,
                ':t' => $telefono,
                ':r' => $rol_id,
                ':f' => $ruta_foto,
                ':u' => $usuario_login,
                ':p' => password_hash($password, PASSWORD_DEFAULT),
                ':e' => $email,
                ':act' => $activo
            ]);

            $sql_historico = "INSERT INTO historico_operaciones
                             (operacion, descripcion, usuario_id, usuario_nombre, ip_address, fecha_hora)
                             VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address, NOW())";

            $detalle_hist = "Creó nuevo usuario: $nombre $apellidos (Usuario: $usuario_login, ID Generado: $id_para_insertar)";
            $stmt_hist = $db->prepare($sql_historico);
            $stmt_hist->execute([
                'operacion'      => 'CREAR_USUARIO',
                'descripcion'    => $detalle_hist,
                'usuario_id'     => $_SESSION['usuario_id'],
                'usuario_nombre' => $_SESSION['usuario_nombre'],
                'ip_address'     => $_SERVER['REMOTE_ADDR']
            ]);

            $alerta_exito = "El Usuario: " . $nombre . " ha sido creado exitosamente.";
            $redireccionar = true;
        }
    }
} catch (Exception $e) {
    if ($e->getMessage() === "El usuario solicitado no existe.") {
        ?>
        <script src="js/sweetalert211.js"></script>
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El usuario que intenta editar no existe en el sistema.',
                confirmButtonColor: '#0078d4',
                background: '#1f1f1f',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                allowOutsideClick: false,
                customClass: {
                    popup: 'border border-secondary rounded shadow-lg'
                }
            }).then(() => {
                window.location.href = 'usuarios.php';
            });
        </script>
        <?php
        exit();
    }

    $alerta_error = $e->getMessage();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $datos_usuario['nombre'] = $_POST['nombre'] ?? '';
        $datos_usuario['apellidos'] = $_POST['apellidos'] ?? '';
        $datos_usuario['no_ci'] = $_POST['no_ci'] ?? '';
        $datos_usuario['direccion_particular'] = $_POST['direccion_particular'] ?? '';
        $datos_usuario['telefono_contacto'] = $_POST['telefono_contacto'] ?? '';
        $datos_usuario['email'] = $_POST['email'] ?? '';
        $datos_usuario['usuario'] = $_POST['usuario'] ?? '';
        $datos_usuario['rol_id'] = $_POST['rol_id'] ?? '';
    }
}

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Colores del tema
$temas_windows = [
    'dark' => [
        'nombre' => 'Windows Dark',
        'bg_primary' => '#0d0d0d',
        'bg_secondary' => '#1f1f1f',
        'bg_tertiary' => '#2d2d2d',
        'text_primary' => '#ffffff',
        'text_secondary' => '#a6a6a6',
        'border_color' => '#3d3d3d',
        'accent_color' => $color_accent
    ],
    'light' => [
        'nombre' => 'Windows Light',
        'bg_primary' => '#f3f3f3',
        'bg_secondary' => '#ffffff',
        'bg_tertiary' => '#fafafa',
        'text_primary' => '#000000',
        'text_secondary' => '#666666',
        'border_color' => '#e5e5e5',
        'accent_color' => $color_accent
    ]
];

$tema_actual = $temas_windows[$tema_windows];

// Colores de acento disponibles
$colores_accent = [
    '#0078d4' => 'Azul Windows',
    '#107c10' => 'Verde',
    '#5c2d91' => 'Morado',
    '#e81123' => 'Rojo',
    '#ff8c00' => 'Naranja',
    '#0099bc' => 'Cian',
    '#e3008c' => 'Rosa',
    '#8764b8' => 'Lila'
];


$meses_completos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_es = $meses_completos[date('n') - 1];
$finanzas = Database::getProgresoFinanciero();

$estadisticas = [];
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">

    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <!-- Cropper.js - Versión corregida -->
    <link rel="stylesheet" href="css/cropper.min.css">
    <script src="js/cropper.min.js"></script>
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <style>
/* Estilos para SweetAlert2 en modo dark */
.swal2-popup {
    background: var(--win-bg-secondary) !important;
    color: var(--win-text-primary) !important;
    border: 1px solid var(--win-border-color) !important;
    border-radius: var(--win-radius) !important;
}

.swal2-title {
    color: var(--win-text-primary) !important;
    font-weight: 600 !important;
}

.swal2-html-container {
    color: var(--win-text-secondary) !important;
    text-align: left !important;
}
:root {
    --win-bg-primary: <?php echo $tema_actual['bg_primary']; ?>;
    --win-bg-secondary: <?php echo $tema_actual['bg_secondary']; ?>;
    --win-bg-tertiary: <?php echo $tema_actual['bg_tertiary']; ?>;
    --win-text-primary: <?php echo $tema_actual['text_primary']; ?>;
    --win-text-secondary: <?php echo $tema_actual['text_secondary']; ?>;
    --win-border-color: <?php echo $tema_actual['border_color']; ?>;
    --win-accent: <?php echo $tema_actual['accent_color']; ?>;
    --win-accent-light: <?php echo $tema_actual['accent_color']; ?>20;
    --win-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    --win-radius: 8px;
    --win-radius-sm: 6px;
    --win-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="light"] {
    --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            transition: var(--win-transition);
            min-height: 100vh;
        }

        .mica-effect {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        [data-theme="light"] .mica-effect { background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(0, 0, 0, 0.08); }

        /* Navbar & Sidebar Styles */
        .win-navbar { height: 48px; background: var(--win-bg-secondary); border-bottom: 1px solid var(--win-border-color); padding: 0 16px; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; display: flex; align-items: center; gap: 12px; }
        .win-navbar-brand { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        .win-nav-search { flex: 1; max-width: 400px; position: relative; margin-bottom: 5px; padding: 0 5px; }
        .win-nav-search input { background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); color: var(--win-text-primary); border-radius: var(--win-radius-sm); padding: 8px 12px 8px 30px; font-size: 13px; width: 100%; transition: var(--win-transition); }
        .win-nav-search input:focus { outline: none; border-color: var(--win-accent); box-shadow: 0 0 0 2px var(--win-accent-light); }
        .win-nav-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--win-text-secondary); font-size: 14px; }

        .win-sidebar { width: 260px; background: var(--win-bg-secondary); border-right: 1px solid var(--win-border-color); height: calc(100vh - 48px); position: fixed; left: 0; top: 48px; z-index: 999; transition: var(--win-transition); overflow-y: auto; padding: 16px 0; }
        .win-sidebar.mini { width: 68px; }
        .win-sidebar-header { padding: 0 16px 16px; border-bottom: 1px solid var(--win-border-color); margin-bottom: 16px; }
        .win-sidebar-user { display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: var(--win-radius-sm); transition: var(--win-transition); }
        .win-sidebar-user:hover { background: var(--win-bg-tertiary); }
        .win-sidebar-user-avatar { width: 36px; height: 36px; background: var(--win-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; overflow: hidden; }
        .win-sidebar-user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .win-nav { list-style: none; padding: 0; margin: 0; }
        .win-nav-item { margin: 2px 8px; }
        .win-nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--win-text-secondary); text-decoration: none; border-radius: var(--win-radius-sm); transition: var(--win-transition); font-size: 14px; position: relative; }
        .win-nav-link:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }
        .win-nav-link.active { background: var(--win-accent-light); color: var(--win-accent); font-weight: 500; }
        .win-nav-link.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--win-accent); border-radius: 0 2px 2px 0; }
        .win-nav-icon { width: 20px; text-align: center; font-size: 16px; }
        .win-sidebar.mini .win-nav-text, .win-sidebar.mini .win-sidebar-user-info, .win-sidebar.mini .win-nav-badge { display: none; }
        .win-nav-badge { margin-left: auto; background: var(--win-accent); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; min-width: 20px; text-align: center; font-weight: 600; }

        /* Contenido Principal */
        .win-main-content { margin-left: 260px; margin-top: 48px; padding: 24px; transition: var(--win-transition); min-height: calc(100vh - 48px); }
        .win-main-content.sidebar-mini { margin-left: 68px; }
        .win-main-content .card { background: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius); margin-bottom: 1.5rem; transition: var(--win-transition); }
        .win-main-content .card:hover { border-color: var(--win-accent); box-shadow: var(--win-shadow); }
        .win-main-content .card-header { background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color); padding: 1rem 1.25rem; }
        .win-main-content .card-body { padding: 1.25rem; color: var(--win-text-primary); }
        .win-main-content .form-control, .win-main-content .form-select { background-color: var(--win-bg-tertiary); border-color: var(--win-border-color); color: var(--win-text-primary); }
        .win-main-content .form-control:focus, .win-main-content .form-select:focus { background-color: var(--win-bg-tertiary); border-color: var(--win-accent); color: var(--win-text-primary); box-shadow: 0 0 0 0.25rem var(--win-accent-light); }

        /* Avatar Upload */
        .avatar-upload { position: relative; max-width: 205px; margin: 0 auto 20px; }
        .avatar-preview { width: 150px; height: 150px; position: relative; border-radius: 100%; border: 4px solid var(--win-accent); box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.1); margin: 0 auto; overflow: hidden; background: var(--win-bg-tertiary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--win-transition); }
        .avatar-preview:hover { opacity: 0.8; transform: scale(1.02); }
        .avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-placeholder { font-size: 64px; color: var(--win-text-secondary); }

        /* Estilos para el modal de recorte - CORREGIDOS */
        .modal-xl {
            max-width: 1200px;
        }

        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            min-height: 400px;
            border-radius: 8px;
            overflow: hidden;
        }

	/* Estilos para el preview canvas */
	.preview-container {
		background-color: #2d2d2d;
		background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
						  linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
						  linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
						  linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
		background-size: 20px 20px;
		background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
	}

	#previewCanvas {
		width: 100%;
		height: 100%;
		display: block;
		image-rendering: -webkit-optimize-contrast;
		image-rendering: crisp-edges;
	}
        .preview-lg {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .preview-lg img {
            max-width: 100%;
        }

        /* Mejoras para el cropper */
        .cropper-container {
            max-height: 500px;
        }

        .cropper-view-box {
            outline: 2px solid var(--win-accent);
            outline-color: var(--win-accent);
            box-shadow: 0 0 0 1px white;
        }

        .cropper-line {
            background-color: var(--win-accent);
        }

        .cropper-point {
            background-color: var(--win-accent);
            width: 8px;
            height: 8px;
            opacity: 1;
        }

        /* Botones del modal */
        .crop-controls .btn {
            text-align: left;
            padding: 10px 15px;
        }

        .crop-controls .btn i {
            width: 20px;
            text-align: center;
        }

        @media (max-width: 992px) { .win-sidebar { transform: translateX(-100%); } .win-sidebar.open { transform: translateX(0); } .win-main-content { margin-left: 0; } }

        .text-muted { color: #e0e0e0 !important; opacity: 0.9; }
        [data-theme="light"] .text-muted { color: #555555 !important; opacity: 1; }
/* Panel de temas */
.win-theme-panel {
    position: fixed;
    top: 48px;
    right: 0;
    width: 300px;
    background: var(--win-bg-secondary);
    border-left: 1px solid var(--win-border_color);
    height: calc(100vh - 48px);
    z-index: 1001;
    transform: translateX(100%);
    transition: var(--win-transition);
    padding: 24px;
    overflow-y: auto;
}

.win-theme-panel.open {
    transform: translateX(0);
}

.win-theme-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: none;
}

.win-theme-overlay.open {
    display: block;
}

.win-theme-header {
    margin-bottom: 24px;
}

.win-theme-option {
    padding: 16px;
    border: 2px solid var(--win-border_color);
    border-radius: var(--win-radius);
    cursor: pointer;
    transition: var(--win-transition);
    text-align: center;
    color: var(--win-text-primary);
}

.win-theme-option:hover {
    border-color: var(--win-accent);
}

.win-theme-option.active {
    border-color: var(--win-accent);
    background: var(--win-accent-light);
}

.win-theme-option[data-theme="dark"] {
    background: #0d0d0d;
    color: white;
}

.win-theme-option[data-theme="light"] {
    background: #f3f3f3;
    color: #000000;
}

.win-color-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.win-color-option {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid transparent;
    transition: var(--win-transition);
}

.win-color-option:hover {
    transform: scale(1.1);
}

.win-color-option.active {
    border-color: white;
    box-shadow: 0 0 0 2px var(--win-bg-secondary);
}
/* Estilos para el dropdown de usuario */
.dropdown-menu {
    background-color: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius);
}

.dropdown-header {
    background-color: var(--win-bg-tertiary);
    border-radius: var(--win-radius-sm) var(--win-radius-sm) 0 0;
}

.dropdown-footer {
    background-color: var(--win-bg-tertiary);
    border-radius: 0 0 var(--win-radius-sm) var(--win-radius-sm);
}

.dropdown-item {
    color: var(--win-text-primary);
    transition: var(--win-transition);
    border-radius: var(--win-radius-sm);
    margin: 2px 4px;
}

.dropdown-item:hover {
    background-color: var(--win-accent-light);
    color: var(--win-accent);
}

.dropdown-divider {
    border-color: var(--win-border-color);
}

.dropdown-item.text-danger:hover {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545 !important;
}
/* Responsive */
@media (max-width: 992px) {
    .win-sidebar {
        transform: translateX(-100%);
    }
    
    .win-sidebar.open {
        transform: translateX(0);
    }
    
    .win-main-content {
        margin-left: 0;
    }
    
    .win-quick-actions {
        bottom: 16px;
        right: 16px;
    }
}

@media (max-width: 768px) {
    .win-main-content {
        padding: 16px;
    }
    
    .win-nav-search {
        display: none;
    }
    
    .win-quick-action {
        width: 50px;
        height: 50px;
        font-size: 18px;
    }
    
    .win-quick-actions-expanded .win-quick-action {
        width: 44px;
        height: 44px;
        font-size: 16px;
    }
}

/* Scrollbar personalizado */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--win-bg-tertiary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--win-border_color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--win-accent);
}
    </style>
</head>
<body>
    <!-- Overlay para tema panel -->
    <div class="win-theme-overlay" id="themeOverlay"></div>

    <!-- Panel de configuración de temas -->
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" data-theme="dark">
                    <i class="fas fa-moon mb-2"></i>
                    <div>Oscuro</div>
                </div>
            </div>
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" data-theme="light">
                    <i class="fas fa-sun mb-2"></i>
                    <div>Claro</div>
                </div>
            </div>
        </div>

        <h6 class="mb-3" style="color: var(--win-text-primary);">Color de acento</h6>
        <div class="win-color-options mb-4">
            <?php foreach ($colores_accent as $color => $nombre): ?>
                <div class="win-color-option <?php echo $color_accent == $color ? 'active' : ''; ?>"
                     style="background-color: <?php echo $color; ?>;"
                     data-color="<?php echo $color; ?>"
                     title="<?php echo $nombre; ?>"></div>
            <?php endforeach; ?>
        </div>

        <h6 class="mb-3" style="color: var(--win-text-primary);">Opciones de interfaz</h6>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini"
                   <?php echo $sidebar_mini ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleSidebarMini" style="color: var(--win-text-primary);">
                Sidebar compacto
            </label>
        </div>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleAnimations" checked>
            <label class="form-check-label" for="toggleAnimations" style="color: var(--win-text-primary);">
                Animaciones
            </label>
        </div>

        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()">
            <i class="fas fa-save"></i> Guardar cambios
        </button>
    </div>

    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48">
            <span style="color: var(--win-text-primary);">USUARIOS - SISFACT PDL Visiones</span>
        </div>

        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>

        <div style="flex: 1;"></div>

        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>

        <?= renderNotificationsDropdown() ?>

        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="d-none d-md-inline" style="color: var(--win-text-primary);">
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-chevron-down ms-1 small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px;">
                <li class="dropdown-header px-3 py-2">
                    <div class="d-flex align-items-center">
                        <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0" style="color: var(--win-text-primary); font-size: 14px;">
                                <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 12px;">
                                <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?>
                            </small>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item d-flex align-items-center py-2" href="dashboard.php"><i class="fas fa-tachometer-alt me-3 text-primary" style="width: 20px;"></i><div><span class="d-block" style="color: var(--win-text-primary);">Dashboard</span><small class="text-muted d-block" style="font-size: 12px;">Panel principal</small></div></a></li>
                <li><a class="dropdown-item d-flex align-items-center py-2" href="perfil.php"><i class="fas fa-user me-3 text-primary" style="width: 20px;"></i><div><span class="d-block" style="color: var(--win-text-primary);">Mi Perfil</span><small class="text-muted d-block" style="font-size: 12px;">Ver y editar tu información</small></div></a></li>
                <li><a class="dropdown-item d-flex align-items-center py-2" href="configuracion.php"><i class="fas fa-cog me-3 text-secondary" style="width: 20px;"></i><div><span class="d-block" style="color: var(--win-text-primary);">Configuración</span><small class="text-muted d-block" style="font-size: 12px;">Preferencias del sistema</small></div></a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item d-flex align-items-center py-2 text-warning" href="bloquear_sesion.php"><i class="fas fa-lock me-3" style="width: 20px;"></i><div><span class="d-block fw-bold">Bloquear Sesión</span><small class="text-muted d-block" style="font-size: 12px;">Bloquear pantalla temporalmente</small></div></a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i><div><span class="d-block fw-bold">Cerrar Sesión</span><small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small></div></a></li>
                <?php $ultimo_acceso_texto = obtenerUltimoAcceso($_SESSION['usuario_id'] ?? 0);?>
				<li class="dropdown-footer px-3 py-2 mt-1">
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-shield-alt me-1"></i> Sesión segura
					</small>
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-clock me-1"></i>Último acceso: 
						<span class="fw-bold text-info"><?php echo $ultimo_acceso_texto; ?></span>
					</small>
				</li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
        <a href="perfil.php" style="text-decoration: none; display: block;">
            <div class="win-sidebar-header">
                <div class="win-sidebar-user">
                    <div class="win-sidebar-user-avatar">
                        <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="win-sidebar-user-info">
                        <h6 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></h6>
                        <small style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></small>
                    </div>
                </div>
            </div>
        </a>
        <ul class="win-nav">
            <li class="win-nav-item">
                <a href="dashboard.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tachometer-alt"></i>
                    Dashboard
                    <span class="win-nav-badge" title="Fecha de Cierre Actual: <?php echo date('t') . ' de ' . $mes_actual_es; ?>" style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
                        F/Cierre: <?php echo date('t'); ?> / <?php echo substr($mes_actual_es, 0, 3); ?>
                    </span>
                </a>
            </li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            <li class="win-nav-item"><a href="facturas.php" class="win-nav-link"><i class="win-nav-icon fas fa-file-invoice"></i><span class="win-nav-text">Facturas</span><span class="win-nav-badge"><?php echo $total_facturas; ?></span></a></li>
            <li class="win-nav-item"><a href="clientes.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Clientes</span><span class="win-nav-badge"><?php echo $total_clientes; ?></span></a></li>
            <li class="win-nav-item"><a href="categorias.php" class="win-nav-link"><i class="win-nav-icon fas fa-tags"></i><span class="win-nav-text">Categorías</span><span class="win-nav-badge"><?php echo $total_categorias; ?></span></a></li>
            <li class="win-nav-item"><a href="servicios.php" class="win-nav-link"><i class="win-nav-icon fas fa-list"></i><span class="win-nav-text">Servicios</span><span class="win-nav-badge"><?php echo $total_servicios; ?></span></a></li>
            <li class="win-nav-item"><a href="usuarios.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Usuarios</span><span class="win-nav-badge"><?php echo $total_usuarios; ?></span></a></li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text"><?php echo $titulo_pagina; ?></span>
                    <span class="win-nav-badge"><?php echo isset($_GET['id']) ? strtoupper($datos_usuario['nombre'] . ' ' . $datos_usuario['apellidos']) : 'Nuevo'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="reportes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-chart-bar"></i>
                    <span class="win-nav-text">Reportes</span>
                    <span class="win-nav-badge">17</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="rentabilidad.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-trend-up"></i>
                    <span class="win-nav-text">Rentabilidad y Costos</span>
                </a>
            </li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;"><?php echo $sidebar_mini ? '...' : 'Configuración'; ?></small></li>
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
                    <span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>
            <li class="win-nav-item"><a href="configuracion.php" class="win-nav-link"><i class="win-nav-icon fas fa-cog"></i><span class="win-nav-text">Configuración</span></a></li>
            <li class="win-nav-item"><a href="historico_view.php" class="win-nav-link"><i class="win-nav-icon fas fa-history"></i><span class="win-nav-text">Histórico</span><span class="win-nav-badge"><?php echo $estadisticas['total']; ?></span></a></li>
        </ul>

        <?php $finanzas = Database::getProgresoFinanciero(); ?>
        <div class="mt-4 px-3">
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;"><?php echo $finanzas['mensaje']; ?></small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><?php echo number_format($finanzas['porcentaje'], 1); ?>%</h5>
            </div>
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" role="progressbar" style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>;" aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <small class="text-muted" style="font-size: 12px;"><strong>$<?php echo number_format($finanzas['real'], 2); ?></strong></small>
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;"><i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas</small>
                </div>
                <small class="text-end text-success" style="font-size: 12px;">Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?></small>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas <?php echo $es_edicion ? 'fa-user-edit' : 'fa-user-plus'; ?> me-2" style="color: var(--win-accent);"></i>
                            <span style="color: var(--win-accent);"><?php echo $titulo_pagina . ': '; ?></span><span class="text-danger"> <?php echo $datos_usuario['nombre'] . ' ' . $datos_usuario['apellidos']; ?></span>
                        </h5>
                        <a href="usuarios.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Volver</a>
                    </div>

                    <div class="card-body p-4">
                        <form action="" method="POST" enctype="multipart/form-data" id="userForm">
                            <!-- Campo oculto para la imagen recortada en base64 -->
                            <input type="hidden" name="imagen_recortada" id="imagen_recortada">

                            <div class="row">
                                <!-- Columna Izquierda - Foto -->
                                <div class="col-md-4 mb-4 border-end border-secondary border-opacity-25">
                                    <div class="text-center mb-1">
                                        <div class="avatar-upload">
                                            <div class="avatar-preview mb-2" onclick="document.getElementById('imageUpload').click();">
                                                <?php if (!empty($datos_usuario['foto']) && file_exists($datos_usuario['foto'])): ?>
                                                    <img id="imagePreview" src="<?php echo htmlspecialchars($datos_usuario['foto'] . '?t=' . time()); ?>" alt="Foto de perfil" style="width:100%; height:100%; object-fit:cover;">
                                                    <div id="placeholderPreview" style="display: none;"></div>
                                                <?php else: ?>
                                                    <div id="placeholderPreview" class="avatar-placeholder d-flex align-items-center justify-content-center h-100">
                                                        <i class="fas fa-camera" style="font-size: 48px; opacity: 0.5;"></i>
                                                    </div>
                                                    <img id="imagePreview" src="" style="display: none;">
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-grid">
                                                <label class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-upload me-1"></i> Seleccionar Foto
                                                    <input type="file" name="foto" id="imageUpload" accept="image/*" hidden onchange="cargarImagenParaRecorte(this)">
                                                </label>
                                                <small class="text-muted mt-1">
                                                    <i class="fas fa-image me-1"></i>
                                                    Máximo 5MB
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="mb-1 text-uppercase small fw-bold" style="color: var(--win-accent);">Datos de Acceso</h6>

                                    <div class="mb-1">
                                        <label class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" name="usuario" id="inp_usuario" value="<?php echo htmlspecialchars($datos_usuario['usuario']); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <label class="form-label">Contraseña
                                            <?php if ($es_edicion): ?>
                                                <small class="text-danger">(En blanco para mantener)</small>
                                            <?php else: ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="password" id="password"
                                                   <?php if (!$es_edicion): ?>required<?php endif; ?>
                                                   placeholder="<?php echo $es_edicion ? 'Nueva contraseña (opcional)' : 'Contraseña obligatoria'; ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('password')"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                                   placeholder="<?php echo $es_edicion ? 'Confirmar nueva contraseña' : 'Confirmar contraseña'; ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('confirm_password')"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Rol para los permisos <span class="text-danger">*</span></label>
                                        <select class="form-select" name="rol_id" id="inp_rol">
                                            <option value="">Seleccionar Rol...</option>
                                            <?php foreach ($roles_disponibles as $rol): ?>
                                                <option value="<?php echo $rol['id']; ?>" <?php echo ($datos_usuario['rol_id'] == $rol['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rol['descripcion']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="activo" id="checkActivo"
                                            <?php echo (isset($datos_usuario['activo']) && $datos_usuario['activo'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="checkActivo">
                                            Usuario Activo
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Permitir acceso al sistema</small>
                                        </label>
                                    </div>
                                </div>

                                <!-- Columna Derecha - Datos Personales -->
                                <div class="col-md-8">
                                    <h6 class="mb-3 text-uppercase small fw-bold" style="color: var(--win-accent);">Información Personal</h6>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nombre(s) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nombre" id="inp_nombre" value="<?php echo htmlspecialchars($datos_usuario['nombre']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="apellidos" id="inp_apellidos" value="<?php echo htmlspecialchars($datos_usuario['apellidos']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CI <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="no_ci" id="inp_ci" value="<?php echo htmlspecialchars($datos_usuario['no_ci']); ?>" maxlength="11" oninput="uiValidarCI(this)">
                                            <div id="ciFeedback" style="display: none;" class="mt-1 small"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" id="inp_email" value="<?php echo htmlspecialchars($datos_usuario['email']); ?>">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Teléfono</label>
                                            <input type="tel" class="form-control" name="telefono_contacto" id="inp_telefono" value="<?php echo htmlspecialchars($datos_usuario['telefono_contacto']); ?>" oninput="this.value = this.value.replace(/[^0-9+]/g, '')" placeholder="+535xxxxxxx" maxlength="15">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Dirección</label>
                                            <textarea class="form-control" name="direccion_particular" rows="3"><?php echo htmlspecialchars($datos_usuario['direccion_particular']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top border-secondary border-opacity-25">
                                        <a href="usuarios.php" class="btn btn-outline-secondary"><i class="fas fa-close me-2"></i>Cancelar</a>
                                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Guardar Usuario</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

<!-- Modal para recortar imagen - VERSIÓN CON PREVIEW MANUAL -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: var(--win-bg-secondary); color: var(--win-text-primary);">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-crop-alt me-2" style="color: var(--win-accent);"></i>
                    Recortar imagen a 7x7cm
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropper()"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="img-container" style="max-height: 500px; overflow: hidden; background: #333; border-radius: 8px; padding: 10px;">
                            <img id="imageToCrop" src="" style="max-width: 100%; display: block;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <h6 class="mb-2">Vista previa 7x7cm</h6>
                            <div class="preview-container" style="width: 200px; height: 200px; margin: 0 auto; overflow: hidden; border: 3px solid var(--win-accent); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: #2d2d2d;">
                                <canvas id="previewCanvas" width="200" height="200" style="width: 100%; height: 100%; display: block;"></canvas>
                            </div>
                        </div>

                        <div class="text-center mb-3">
                            <small class="text-muted d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                La imagen final será de <strong>7x7cm (827x827 píxeles)</strong>
                            </small>
                        </div>

                        <div class="crop-controls p-3" style="background: var(--win-bg-tertiary); border-radius: 8px;">
                            <h6 class="mb-2">Controles</h6>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(-90)">
                                    <i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(90)">
                                    <i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetearCrop()">
                                    <i class="fas fa-sync-alt me-2"></i>Resetear recorte
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="zoomImagen(0.1)">
                                    <i class="fas fa-search-plus me-2"></i>Acercar
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="zoomImagen(-0.1)">
                                    <i class="fas fa-search-minus me-2"></i>Alejar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropper()">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="aplicarRecorte()">
                    <i class="fas fa-check me-2"></i>Aplicar recorte
                </button>
            </div>
        </div>
    </div>
</div>
    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>

    <script>
        // ==========================================
        // FUNCIONES PARA RECORTE DE IMAGEN - CORREGIDAS
        // ==========================================

		let cropper = null;
		let previewCanvas = null;
		let previewCtx = null;

        // Verificar que Cropper.js esté disponible
        if (typeof Cropper === 'undefined') {
            console.error('Cropper.js no está cargado. Cargándolo ahora...');
            const script = document.createElement('script');
            script.src = 'js/cropper.min.js';
            document.head.appendChild(script);
        }

function cargarImagenParaRecorte(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];

        // Validar tamaño (máximo 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'La imagen no debe superar los 5MB',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)'
            });
            input.value = '';
            return;
        }

        // Validar tipo de archivo
        if (!file.type.match('image.*')) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Solo se permiten archivos de imagen',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)'
            });
            input.value = '';
            return;
        }

        // Mostrar loading
        Swal.fire({
            title: 'Cargando imagen...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)'
        });

        const reader = new FileReader();
        reader.onload = function(e) {
            const imageToCrop = document.getElementById('imageToCrop');
            imageToCrop.src = e.target.result;

            // Asegurar que la imagen esté completamente cargada
            imageToCrop.onload = function() {
                // Cerrar loading
                Swal.close();

                // Inicializar canvas de preview
                previewCanvas = document.getElementById('previewCanvas');
                previewCtx = previewCanvas.getContext('2d');
                
                // Limpiar canvas
                previewCtx.fillStyle = '#2d2d2d';
                previewCtx.fillRect(0, 0, 200, 200);

                // Si ya existe un cropper, destruirlo
                if (cropper) {
                    cropper.destroy();
                }

                // Inicializar cropper
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    cropBoxResizable: true,
                    cropBoxMovable: true,
                    guides: true,
                    center: true,
                    highlight: true,
                    background: true,
                    responsive: true,
                    restore: true,
                    zoomable: true,
                    rotatable: true,
                    scalable: true,
                    wheelZoomRatio: 0.1,
                    minContainerWidth: 500,
                    minContainerHeight: 400,
                    crop: function(event) {
                        // Actualizar preview manualmente en cada movimiento
                        actualizarPreview(event);
                    },
                    ready: function() {
                        // Ajustar cropBox
                        const containerData = cropper.getContainerData();
                        const cropBoxSize = Math.min(containerData.width, containerData.height) * 0.8;
                        cropper.setCropBoxData({
                            width: cropBoxSize,
                            height: cropBoxSize,
                            left: (containerData.width - cropBoxSize) / 2,
                            top: (containerData.height - cropBoxSize) / 2
                        });
                        
                        // Forzar actualización del preview
                        setTimeout(() => {
                            const cropData = cropper.getData();
                            actualizarPreview({ detail: cropData });
                        }, 100);
                    }
                });

                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('cropModal'));
                modal.show();
            };
        };
        reader.readAsDataURL(file);
    }
}


function actualizarPreview(event) {
    if (!cropper || !previewCtx) return;
    
    try {
        // Obtener datos del recorte
        const cropData = event.detail || cropper.getData();
        
        // Obtener la imagen original
        const image = cropper.getImageData();
        
        // Calcular dimensiones para el preview
        const previewWidth = 200;
        const previewHeight = 200;
        
        // Limpiar canvas
        previewCtx.fillStyle = '#2d2d2d';
        previewCtx.fillRect(0, 0, previewWidth, previewHeight);
        
        // Dibujar la imagen recortada en el canvas de preview
        const canvas = cropper.getCroppedCanvas({
            width: 200,
            height: 200,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        if (canvas) {
            previewCtx.drawImage(canvas, 0, 0, 200, 200);
        }
    } catch (error) {
        console.error('Error actualizando preview:', error);
    }
}


function rotarImagen(grados) {
    if (cropper) {
        cropper.rotate(grados);
        // Forzar actualización del preview después de rotar
        setTimeout(() => {
            const cropData = cropper.getData();
            actualizarPreview({ detail: cropData });
        }, 50);
    }
}

function zoomImagen(factor) {
    if (cropper) {
        cropper.zoom(factor);
        // Forzar actualización del preview después de zoom
        setTimeout(() => {
            const cropData = cropper.getData();
            actualizarPreview({ detail: cropData });
        }, 50);
    }
}

function resetearCrop() {
    if (cropper) {
        cropper.reset();
        // Forzar actualización del preview después de reset
        setTimeout(() => {
            const cropData = cropper.getData();
            actualizarPreview({ detail: cropData });
        }, 50);
    }
}

function limpiarCropper() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    
    // Limpiar canvas de preview
    if (previewCtx) {
        previewCtx.fillStyle = '#2d2d2d';
        previewCtx.fillRect(0, 0, 200, 200);
    }
    
    document.getElementById('imageUpload').value = '';
}

function aplicarRecorte() {
    if (cropper) {
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Procesando imagen...',
                text: 'Aplicando recorte',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)'
            });

            // Obtener la imagen recortada como canvas (tamaño exacto 7x7cm)
            const canvas = cropper.getCroppedCanvas({
                width: 827,
                height: 827,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            // Convertir canvas a base64 (JPEG calidad 90%)
            const imagenRecortadaBase64 = canvas.toDataURL('image/jpeg', 0.9);

            // Guardar en el campo oculto
            document.getElementById('imagen_recortada').value = imagenRecortadaBase64;

            // Actualizar preview principal
            const imagePreview = document.getElementById('imagePreview');
            const placeholderPreview = document.getElementById('placeholderPreview');

            imagePreview.src = imagenRecortadaBase64;
            imagePreview.style.display = 'block';
            imagePreview.style.width = '100%';
            imagePreview.style.height = '100%';
            imagePreview.style.objectFit = 'cover';

            if (placeholderPreview) {
                placeholderPreview.style.display = 'none';
            }

            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
            modal.hide();

            // Limpiar
            limpiarCropper();

            // Cerrar loading
            Swal.close();

            // Mensaje de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Imagen recortada!',
                text: 'La imagen se ha recortado a 7x7cm correctamente',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)'
            });

        } catch (error) {
            console.error('Error al recortar:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo procesar la imagen. Intente nuevamente.',
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)'
            });
        }
    }
}


        // CONFIGURACIÓN SWEETALERT
        const Toast = Swal.mixin({
            toast: false,
            position: 'center',
            showConfirmButton: true,
            confirmButtonText: '<i class="fa fa-user-check me-2"></i>Aceptar',
            confirmButtonColor: 'var(--win-accent)',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            customClass: {
                popup: 'border border-secondary rounded shadow-lg'
            }
        });

        // Alertas PHP
        <?php if ($alerta_exito): ?>
        Toast.fire({
            icon: 'success',
            title: '¡Operación Exitosa!',
            text: '<?php echo $alerta_exito; ?>',
            timer: 2000,
            timerProgressBar: true,
            didDestroy: () => {
                <?php if (isset($redireccionar) && $redireccionar): ?>
                    window.location.href = 'usuarios.php';
                <?php endif; ?>
            }
        });
        <?php endif; ?>

        <?php if ($alerta_error): ?>
        Toast.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo $alerta_error; ?>'
        });
        <?php endif; ?>

        // ==========================================
        // VALIDACIÓN DE CI
        // ==========================================
        function validarCI(ci) {
            ci = ci.replace(/[\s-]/g, '');
            if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: '11 dígitos requeridos' };

            const año = ci.substr(0, 2);
            const mes = ci.substr(2, 2);
            const dia = ci.substr(4, 2);

            if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes inválido' };

            const diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            const maxDias = diasPorMes[parseInt(mes) - 1];

            if (parseInt(mes) === 2 && parseInt(dia) === 29) {
                const añoCompleto = parseInt(año) < 30 ? 2000 + parseInt(año) : 1900 + parseInt(año);
                const esBisiesto = (añoCompleto % 4 === 0 && añoCompleto % 100 !== 0) || (añoCompleto % 400 === 0);
                if (!esBisiesto) {
                    return { valido: false, mensaje: '29/02 solo válido en años bisiestos' };
                }
            } else if (dia < '01' || parseInt(dia) > maxDias) {
                return { valido: false, mensaje: 'Día inválido' };
            }

            const digitoGenero = parseInt(ci.charAt(9));
            const genero = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
            const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
            const añoCompleto = parseInt(año) < 30 ? `20${año}` : `19${año}`;

            return {
                valido: true,
                mensaje: `<i class="fas fa-check-circle me-1"></i><span style="color: cyan;">CI válido </span><span class="text-success">${iconoGenero}${genero}<br><i class="fas fa-calendar-alt ms-0 me-0"></i> ${dia}/${mes}/${añoCompleto}</span>`,
                genero: genero
            };
        }

        function uiValidarCI(input) {
            const ci = input.value.trim();
            const feedback = document.getElementById('ciFeedback');
            if (!ci) { feedback.style.display = 'none'; input.classList.remove('is-valid', 'is-invalid'); return; }
            const resultado = validarCI(ci);
            feedback.style.display = 'block';
            if (resultado.valido) {
                feedback.className = 'mt-1 small text-success fw-bold animate__animated animate__fadeIn';
                feedback.innerHTML = `${resultado.mensaje}`;
                input.classList.remove('is-invalid'); input.classList.add('is-valid');
            } else {
                feedback.className = 'mt-1 small text-danger fw-bold animate__animated animate__fadeIn';
                feedback.innerHTML = `<i class="fas fa-times-circle me-1"></i> ${resultado.mensaje}`;
                input.classList.remove('is-valid'); input.classList.add('is-invalid');
            }
        }

        // ==========================================
        // VALIDACIÓN DEL FORMULARIO
        // ==========================================
        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();

            let msg = "";
            let focusInput = null;

            // Limpiar errores visuales
            let inputs = this.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                if (input.id !== 'inp_ci') input.classList.remove('is-invalid');
            });

            let nombre = document.getElementById('inp_nombre').value.trim();
            let apellido = document.getElementById('inp_apellidos').value.trim();
            let usuario = document.getElementById('inp_usuario').value.trim();
            let ci = document.getElementById('inp_ci').value.trim();
            let email = document.getElementById('inp_email').value.trim();
            let rol = document.getElementById('inp_rol').value;
            let pass = document.getElementById('password').value;
            let confirm = document.getElementById('confirm_password').value;
            let telefono = document.getElementById('inp_telefono').value.trim();
            let isEdit = <?php echo $es_edicion ? 'true' : 'false'; ?>;

            // Validaciones de campos obligatorios
            if (!nombre) {
                msg += "• Falta el Nombre del Usuario<br>";
                document.getElementById('inp_nombre').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_nombre');
            }
            if (!apellido) {
                msg += "• Falta los Apellidos del Usuario<br>";
                document.getElementById('inp_apellidos').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_apellidos');
            }
            if (!usuario) {
                msg += "• Falta el Usuario para acceder<br>";
                document.getElementById('inp_usuario').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_usuario');
            }

            // CI
            if (!ci) {
                msg += "• Falta el No. CI<br>";
                document.getElementById('inp_ci').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_ci');
            } else {
                let resCI = validarCI(ci);
                if (!resCI.valido) {
                    msg += `• Error en CI: ${resCI.mensaje}<br>`;
                    document.getElementById('inp_ci').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('inp_ci');
                }
            }

            if (!email) {
                msg += "• Falta el Email del usuario<br>";
                document.getElementById('inp_email').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_email');
            }

            let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.length > 0 && !emailRegex.test(email)) {
                msg += "• El formato del correo electrónico no es válido<br>";
                document.getElementById('inp_email').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_email');
            }

            if (!rol) {
                msg += "• Seleccione un Rol para el usuario<br>";
                document.getElementById('inp_rol').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_rol');
            }

            // Teléfono
            if (telefono.length > 0) {
                let phoneRegex = /^\+?[0-9]{8,15}$/;
                if (!phoneRegex.test(telefono)) {
                    msg += "• El teléfono no es válido (ej: +5352712861, 32415418) signo '+' y mínimo 8 Dígitos<br>";
                    document.getElementById('inp_telefono').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('inp_telefono');
                }
            }

            // Longitud mínima
            if (nombre.length > 0 && nombre.length < 3) {
                msg += "• El Nombre debe tener al menos 3 caracteres<br>";
                document.getElementById('inp_nombre').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_nombre');
            }

            if (apellido.length > 0 && apellido.length < 3) {
                msg += "• Los Apellidos deben tener al menos 3 caracteres<br>";
                document.getElementById('inp_apellidos').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_apellidos');
            }

            if (usuario.length > 0 && usuario.length < 3) {
                msg += "• El Nombre de Usuario debe tener al menos 3 caracteres<br>";
                document.getElementById('inp_usuario').classList.add('is-invalid');
                if (!focusInput) focusInput = document.getElementById('inp_usuario');
            }

            // Validación de contraseña
            if (!isEdit) {
                // Para NUEVO USUARIO
                if (!pass) {
                    msg += "• La Contraseña es obligatoria para nuevo usuario<br>";
                    document.getElementById('password').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('password');
                } else if (pass.length < 6) {
                    msg += "• La contraseña debe tener al menos 6 caracteres<br>";
                    document.getElementById('password').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('password');
                }

                if (!confirm) {
                    msg += "• Confirme la contraseña<br>";
                    document.getElementById('confirm_password').classList.add('is-invalid');
                    if (!focusInput && !document.getElementById('password').classList.contains('is-invalid'))
                        focusInput = document.getElementById('confirm_password');
                } else if (pass !== confirm) {
                    msg += "• Las contraseñas no coinciden<br>";
                    document.getElementById('password').classList.add('is-invalid');
                    document.getElementById('confirm_password').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('password');
                }
            } else {
                // Para EDICIÓN
                if (pass.length > 0) {
                    if (pass.length < 6) {
                        msg += "• La contraseña debe tener al menos 6 caracteres (si desea cambiarla)<br>";
                        document.getElementById('password').classList.add('is-invalid');
                        if (!focusInput) focusInput = document.getElementById('password');
                    }

                    if (!confirm) {
                        msg += "• Confirme la nueva contraseña<br>";
                        document.getElementById('confirm_password').classList.add('is-invalid');
                        if (!focusInput && !document.getElementById('password').classList.contains('is-invalid'))
                            focusInput = document.getElementById('confirm_password');
                    } else if (pass !== confirm) {
                        msg += "• Las contraseñas no coinciden<br>";
                        document.getElementById('password').classList.add('is-invalid');
                        document.getElementById('confirm_password').classList.add('is-invalid');
                        if (!focusInput) focusInput = document.getElementById('password');
                    }
                } else if (confirm.length > 0) {
                    msg += "• Ingrese la nueva contraseña en el campo anterior<br>";
                    document.getElementById('password').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('password');
                }
            }

            if (msg) {
                Toast.fire({
                    icon: 'warning',
                    title: 'Datos Incorrectos',
                    html: '<div style="text-align:left">' + msg + '</div>',
                    didClose: () => {
                        if (focusInput) {
                            focusInput.focus();
                            focusInput.scrollIntoView({ behavior: "smooth", block: "center" });
                        }
                    }
                });
            } else {
                this.submit();
            }
        });

        // Medidor de contraseña
        document.getElementById('password').addEventListener('input', function() {
            let val = this.value;
            let bar = document.getElementById('meterBar');
            let text = document.getElementById('meterText');
            let score = 0;

            if (val.length > 0) score++;
            if (val.length >= 6) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            if (!bar) {
                // Crear elementos del medidor si no existen
                const meterDiv = document.createElement('div');
                meterDiv.className = 'password-meter';
                meterDiv.innerHTML = '<div class="meter-bar" id="meterBar"></div>';
                this.parentNode.parentNode.appendChild(meterDiv);
                const textDiv = document.createElement('div');
                textDiv.className = 'meter-text';
                textDiv.id = 'meterText';
                this.parentNode.parentNode.appendChild(textDiv);

                bar = document.getElementById('meterBar');
                text = document.getElementById('meterText');
            }

            bar.className = 'meter-bar';

            if (val.length === 0) {
                bar.style.width = '0%';
                if (text) text.innerHTML = '';
            } else if (score < 3) {
                bar.style.width = '30%';
                bar.style.backgroundColor = '#e81123';
                if (text) text.innerHTML = '<span style="color:#e81123">Floja</span>';
            } else if (score < 5) {
                bar.style.width = '60%';
                bar.style.backgroundColor = '#ff8c00';
                if (text) text.innerHTML = '<span style="color:#ff8c00">Media</span>';
            } else {
                bar.style.width = '100%';
                bar.style.backgroundColor = '#107c10';
                if (text) text.innerHTML = '<span style="color:#107c10">Fuerte</span>';
            }
        });


        function togglePass(id) {
            let x = document.getElementById(id);
            x.type = x.type === 'password' ? 'text' : 'password';
        }

        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                // Para móvil
                sidebar.classList.toggle('open');
            } else {
                // Para desktop (toggle mini)
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                
                // Guardar preferencia
                guardarPreferencia('sidebar_mini', sidebarMini);
            }
        }
        
        function guardarPreferencia(clave, valor) {
            fetch('guardar_preferencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `${clave}=${valor}`
            });
        }
        
        // Funciones del panel de temas
        function abrirPanelTemas() {
            document.getElementById('themePanel').classList.add('open');
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            document.getElementById('themeOverlay').classList.add('open');
            themePanelOpen = true;
        }
        
        function cerrarPanelTemas() {
            document.getElementById('themePanel').classList.remove('open');
            document.getElementById('themeOverlay').classList.remove('open');
            themePanelOpen = false;
        }
        
        // Cambiar tema
        document.querySelectorAll('.win-theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-theme-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const theme = this.dataset.theme;
                document.documentElement.setAttribute('data-theme', theme);
            });
        });
        
        // Cambiar color de acento
        document.querySelectorAll('.win-color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-color-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const color = this.dataset.color;
                document.documentElement.style.setProperty('--win-accent', color);
                document.documentElement.style.setProperty('--win-accent-light', color + '20');
            });
        });
        
        // Guardar configuración
        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            
            Swal.fire({
                title: '<i class="fas fa-spinner fa-spin me-2"></i>Guardando configuración...',
                allowOutsideClick: false,
                backdrop: 'rgba(0,0,0,0.8)',
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar al servidor
            fetch('guardar_configuracion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tema_windows: tema,
                    color_accent: color,
                    sidebar_mini: sidebarMini
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<i class="fas fa-check-circle me-2"></i>¡Configuración guardada!',
                        text: 'Los cambios se han aplicado correctamente.',
                        timer: 2000,
                        showConfirmButton: false,
                        backdrop: 'rgba(0,0,0,0.8)',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOutUp'
                        }
                    });
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                        text: 'No se pudo guardar la configuración',
                        backdrop: 'rgba(0,0,0,0.8)',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOutUp'
                        }
                    });
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                    text: 'Error de conexión',
                    backdrop: 'rgba(0,0,0,0.8)',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
            });
            
            cerrarPanelTemas();
        }

        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && cropper) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                if (modal) modal.hide();
                limpiarCropper();
            }
        });
    </script>
    <?php if (file_exists('config/footer.php')) include 'config/footer.php'; ?>
</body>
</html>