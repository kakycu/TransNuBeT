<?php
// includes/user_menu.php - Menú de usuario unificado (misma lógica que sidebar.php)

// Detectar ubicación actual
$current_script = $_SERVER['SCRIPT_NAME'];
$is_in_modules = (strpos($current_script, '/modules/') !== false);
$base_prefix = $is_in_modules ? '../' : '';

// Variable para controlar si mostrar el reloj
$show_clock = $show_clock ?? true;

// Recuperar datos del usuario de la sesión
$user_nombre_completo = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['user_ci'] ?? $_SESSION['usuario_ci'] ?? '';
$user_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;

// ==========================================
// OBTENER FOTO DEL USUARIO - MISMA LÓGICA QUE SIDEBAR.PHP
// ==========================================
$user_foto_menu = null;
$defaultSvgMenu = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');

if ($user_id) {
    try {
        // Verificar si $pdo existe (desde database.php)
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT foto FROM clasif_usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['foto'])) {
                $foto_valor = $result['foto'];
                
                // CASO 1: Es un BLOB (datos binarios)
                if (strpos($foto_valor, 'data:image') === 0) {
                    $user_foto_menu = $foto_valor;
                }
                // CASO 2: Es una URL absoluta
                elseif (filter_var($foto_valor, FILTER_VALIDATE_URL)) {
                    $user_foto_menu = $foto_valor;
                }
                // CASO 3: Es un BLOB (datos binarios largos sin texto)
                elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false && strpos($foto_valor, '.') === false) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_buffer($finfo, $foto_valor);
                    finfo_close($finfo);
                    $user_foto_menu = 'data:' . ($mime_type ?: 'image/jpeg') . ';base64,' . base64_encode($foto_valor);
                }
                // CASO 4: Es una ruta de archivo (manejo según ubicación)
                else {
                    // Limpiar la ruta (eliminar posibles duplicados)
                    $foto_limpia = ltrim($foto_valor, './');
                    
                    // Verificar si la ruta ya contiene 'assets/imagenes/'
                    if (strpos($foto_limpia, 'assets/imagenes/') === 0) {
                        $foto_limpia = substr($foto_limpia, strlen('assets/imagenes/'));
                    }
                    
                    // Solo mantener el nombre del archivo si es necesario
                    $nombre_archivo = basename($foto_limpia);
                    
                    // Construir la ruta CORRECTA según dónde estemos
                    if ($is_in_modules) {
                        // Estamos en /modules/ -> usar ../assets/imagenes/trabajadores/
                        $ruta_foto = '../assets/imagenes/trabajadores/' . $nombre_archivo;
                    } else {
                        // Estamos en la raíz -> usar assets/imagenes/trabajadores/
                        $ruta_foto = 'assets/imagenes/trabajadores/' . $nombre_archivo;
                    }
                    
                    // Verificar si el archivo existe
                    if (file_exists($ruta_foto)) {
                        $user_foto_menu = $ruta_foto;
                    } else {
                        // Intentar con la ruta original como está
                        if (file_exists($foto_valor)) {
                            $user_foto_menu = $foto_valor;
                        } elseif ($is_in_modules && file_exists('../' . $foto_valor)) {
                            $user_foto_menu = '../' . $foto_valor;
                        } else {
                            $user_foto_menu = $defaultSvgMenu;
                        }
                    }
                }
            } else {
                $user_foto_menu = $defaultSvgMenu;
            }
        } else {
            // Si $pdo no está disponible, intentar conectar
            $db_path = __DIR__ . '/../config/database.php';
            if (file_exists($db_path)) {
                require_once $db_path;
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare("SELECT foto FROM clasif_usuarios WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && !empty($result['foto'])) {
                        $foto_valor = $result['foto'];
                        if (strpos($foto_valor, 'data:image') === 0) {
                            $user_foto_menu = $foto_valor;
                        } elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false) {
                            $user_foto_menu = 'data:image/jpeg;base64,' . base64_encode($foto_valor);
                        } else {
                            $nombre_archivo = basename($foto_valor);
                            $user_foto_menu = $is_in_modules ? '../assets/imagenes/trabajadores/' . $nombre_archivo : 'assets/imagenes/trabajadores/' . $nombre_archivo;
                        }
                    } else {
                        $user_foto_menu = $defaultSvgMenu;
                    }
                } else {
                    $user_foto_menu = $defaultSvgMenu;
                }
            } else {
                $user_foto_menu = $defaultSvgMenu;
            }
        }
    } catch (Exception $e) {
        $user_foto_menu = $defaultSvgMenu;
    }
} else {
    $user_foto_menu = $defaultSvgMenu;
}

// Si por algún motivo no hay foto válida, usar default
if (!$user_foto_menu || $user_foto_menu === '') {
    $user_foto_menu = $defaultSvgMenu;
}

// Obtener iniciales
$user_iniciales_menu = '';
$nombre_parts_menu = explode(' ', trim($user_nombre_completo));
if (count($nombre_parts_menu) >= 2) {
    $user_iniciales_menu = strtoupper(substr($nombre_parts_menu[0], 0, 1) . substr($nombre_parts_menu[1], 0, 1));
} else {
    $user_iniciales_menu = strtoupper(substr($user_nombre_completo, 0, 2));
}
?>

<style>
.user-menu-container {
    display: flex;
    align-items: center;
    gap: 16px;
}
.date-badge {
    background: rgba(255, 255, 255, 0.08);
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 0.85rem;
    color: #ffffff;
}
#liveClockMenu {
    display: inline-block;
    min-width: 85px;
    text-align: center;
}
.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.user-avatar:hover {
    transform: scale(1.05);
}
.user-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}
.user-avatar-iniciales {
    z-index: 1;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: white;
}
.dropdown-menu-win {
    background: rgba(32, 32, 40, 0.98) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    padding: 8px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
}
.dropdown-menu-win .dropdown-item {
    color: #ffffff !important;
    border-radius: 8px !important;
    padding: 10px 16px !important;
    font-size: 0.9rem !important;
}
.dropdown-menu-win .dropdown-item:hover {
    background: rgba(96, 165, 250, 0.2) !important;
}
.dropdown-menu-win .dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.2) !important;
}
.dropdown-menu-win .dropdown-divider {
    border-color: rgba(255, 255, 255, 0.1) !important;
}
/* Agregar o modificar estas líneas en el style del user_menu.php */
.dropdown-menu-win .dropdown-item-text {
    color: #ffffff !important;
}

.dropdown-menu-win .dropdown-item-text .fw-bold {
    color: #ffffff !important;
}

.dropdown-menu-win .dropdown-item-text small {
    color: #60a5fa !important;
}

.dropdown-menu-win .badge {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6) !important;
    color: white !important;
}
</style>

<div class="user-menu-container">
    <?php if ($show_clock): ?>
    <div class="date-badge d-none d-md-block">
        <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d/m/Y'); ?>
        <span class="mx-1">•</span>
        <i class="fas fa-clock me-1"></i> <span id="liveClockMenu"></span>
    </div>
    <?php endif; ?>
    
    <div class="dropdown">
        <div class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($user_foto_menu); ?>" 
                 alt="Avatar" 
                 class="user-avatar-img" 
                 style="display: none;"
                 onload="this.style.display='block'; this.parentElement.querySelector('.user-avatar-iniciales').style.display='none';"
                 onerror="this.style.display='none'; this.parentElement.querySelector('.user-avatar-iniciales').style.display='flex';">
            <span class="user-avatar-iniciales" style="display: flex;"><?php echo htmlspecialchars($user_iniciales_menu); ?></span>
        </div>
        <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
            <li>
                <div class="dropdown-item-text">
                    <div class="fw-bold mb-1"><?php echo htmlspecialchars($user_nombre_completo); ?></div>
                    <small><i class="fas fa-id-card me-1"></i> CI: <?php echo htmlspecialchars($user_ci); ?></small>
                    <div class="mt-1">
                        <span class="badge" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6);">
                            <i class="fas fa-user-tag me-1"></i> <?php echo htmlspecialchars($user_rol_descripcion ?: 'Usuario'); ?>
                        </span>
                    </div>
                </div>
            </li>
            <li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?php echo $base_prefix; ?>modules/users.php?id=<?php echo $user_id; ?>"><i class="fas fa-user me-2"></i> Mi Perfil</a></li>
<li><a class="dropdown-item" href="<?php echo $base_prefix; ?>modules/configuracion.php?tab=perfil"><i class="fas fa-lock me-2"></i> Cambiar Contraseña</a></li>
<li><hr class="dropdown-divider"></li>
<!-- NUEVO: Sobre el autor con enlace fijo ../explorer.html -->
<li><a class="dropdown-item" href="../../explorer.html"><i class="fas fa-info-circle me-2"></i> Sobre el autor</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item text-danger" href="#" id="logoutUserMenuBtn"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<script>
// Reloj
if (document.getElementById('liveClockMenu')) {
    function updateClockMenu() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        const clockElement = document.getElementById('liveClockMenu');
        if (clockElement) clockElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
    }
    updateClockMenu();
    setInterval(updateClockMenu, 1000);
}

// Logout
const logoutUserMenuBtn = document.getElementById('logoutUserMenuBtn');
if (logoutUserMenuBtn) {
    logoutUserMenuBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
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
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo $base_prefix; ?>logout.php';
                }
            });
        } else {
            window.location.href = '<?php echo $base_prefix; ?>logout.php';
        }
    });
}
</script>