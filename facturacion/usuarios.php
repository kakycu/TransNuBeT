<?php
// usuarios.php - Windows 11 Dark Mode
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener fechas de operación desde init.php
$fecha_inicio_operaciones = obtenerFechaInicioOperaciones();
$mes_cierre_operaciones = obtenerMesCierreOperaciones();

// Formatear fecha de inicio para mostrarla
$fecha_inicio_formateada = '';
$año_inicio_operaciones = date('Y'); // Fallback
if ($fecha_inicio_operaciones) {
    $fecha_inicio_formateada = date('d/m/Y', strtotime($fecha_inicio_operaciones));
    $año_inicio_operaciones = date('Y', strtotime($fecha_inicio_operaciones));
}

// Obtener nombre del mes de cierre
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_cierre_nombre = $meses_completos[$mes_cierre_operaciones - 1] ?? 'Enero';

// --- 1. SOLUCIÓN ALERTAS FANTASMA Y TIEMPO ---
// Capturamos los mensajes de la sesión en variables locales INMEDIATAMENTE
$alerta_exito = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : null;
$alerta_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : null;

// Limpiamos la sesión YA MISMO para que no salga al recargar de nuevo
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);
unset($_SESSION['alert_message']);

// Verificar si hay alertas en la sesión
if (isset($_SESSION['swal_success']) || isset($_SESSION['swal_error'])) {
    $alerta_exito = $_SESSION['swal_success'] ?? '';
    $alerta_error = $_SESSION['swal_error'] ?? '';
    $mostrar_alerta = true;
    
    // Limpiar inmediatamente para que no aparezcan de nuevo
    unset($_SESSION['swal_success'], $_SESSION['swal_error'], $_SESSION['alert_message']);
}

// También limpiar mensajes de error de PHP visuales
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0);

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

// Manejo de alertas de éxito/error
$alerta_exito = $alerta_error = '';
$mostrar_alerta = false;

// Verificar si hay alertas en la sesión
if (isset($_SESSION['swal_success']) || isset($_SESSION['swal_error'])) {
    $alerta_exito = $_SESSION['swal_success'] ?? '';
    $alerta_error = $_SESSION['swal_error'] ?? '';
    $mostrar_alerta = true;
    
    // Limpiar inmediatamente para que no aparezcan de nuevo
    unset($_SESSION['swal_success'], $_SESSION['swal_error'], $_SESSION['alert_message']);
}

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
    
    // Verificar si el usuario tiene permisos (rol_id = 1, 5)
    if (!in_array($usuario['rol_id'], [1, 5])) {
        // Preparar mensaje según el rol
        $role_names = [
            1 => 'Administrador',
            2 => 'Visualizador',
            3 => 'Editor / Facturador', 
            4 => 'Supervisor',
            5 => 'Programador'
        ];
        
        $role_description = [
            1 => 'Todos los Permisos',
            2 => 'Permisos Limitados a Visualización',
            3 => 'Editar, Facturar, Contabilizar',
            4 => 'Supervisar Operaciones',
            5 => 'Programador del Sistema o Autor'
        ];
        
        $user_role_name = isset($role_names[$usuario['rol_id']]) 
                        ? $role_names[$usuario['rol_id']] 
                        : 'Desconocido';
        
        // Mostrar SweetAlert y detener ejecución
        echo '<script src="js/sweetalert211.js"></script>';
        echo '<link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">'.
        '<!DOCTYPE html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Gestión de Usuarios - SISFACT PDL Visiones</title>
            <link rel="icon" type="image/x-icon" href="assets/logov.png">
        <style>
            .badge-admin { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-user { background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-operator { background: #0d6efd; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-supervisor { background: #198754; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
            .badge-programador { background: #4a0d8c; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; } 
        </style>';
        
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Determinar clase badge según rol
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
            
        // Roles requeridos
        requiredRoles = "<span class=\'badge-admin me-1\'><i class=\'fas fa-crown me-1\'></i>Admin</span>" +
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
    ' . 
    (in_array($usuario['rol_id'], [1, 5]) 
        ? "Visualizador (solo lectura)" 
        : (isset($role_names[$usuario['rol_id']]) && isset($role_description[$usuario['rol_id']])
            ? $role_names[$usuario['rol_id']] . ' - ' . $role_description[$usuario['rol_id']]
            : (isset($role_description[$usuario['rol_id']])
                ? $role_description[$usuario['rol_id']]
                : 'Desconocido'))
    ) . '
</p>
                        </div>
                        
                        <div class="alert alert-warning p-3">
                            <i class="fas fa-lock me-2"></i>
                            <strong>Se requieren estos privilegios:</strong><br>
                            <div class="mt-2">
                                ${requiredRoles}
                            </div>
                        </div>
                        
                        <div class="alert alert-info p-2">
                            <i class="fas fa-info-circle me-2"></i>
                            <small><strong>ID de su Rol:</strong> ' . $usuario['rol_id'] . '</small><br>
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <small><strong>Su privilegio:</strong> ' . $user_role_name . '</small>
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
                    // Redirigir a la página principal
                    window.location.href = "dashboard.php";
                }
            });
        });
        </script>';
        
        exit(); // Detener ejecución del resto de la página
    }
    // Si llegamos aquí, el usuario tiene permisos (rol 1, 3, 4 o 5)
    // Verificar si es administrador (rol 1)
    $es_admin = ($usuario['rol_id'] == 1);
        
    // Obtener estadísticas para los badges del sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener usuarios con sus roles
    $sql = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
            FROM clasif_usuarios u
            LEFT JOIN clasif_rol r ON u.rol_id = r.id
            ORDER BY u.nombre, u.apellidos";
    $stmt = $db->query($sql);
    $usuarios = $stmt->fetchAll();
    
    // Obtener todos los roles
    $sql_roles = "SELECT * FROM clasif_rol ORDER BY descripcion";
    $stmt_roles = $db->query($sql_roles);
    $roles = $stmt_roles->fetchAll();
    
    // Calcular estadísticas
    $total_usuarios_activos = 0;
    $total_usuarios_inactivos = 0;
    $usuarios_por_rol = [];
    
    foreach ($usuarios as $usuario_item) {
        if ($usuario_item['activo'] == 1) {
            $total_usuarios_activos++;
        } else {
            $total_usuarios_inactivos++;
        }
        
        $tasa_actividad = ($total_usuarios > 0) ? ($total_usuarios_activos / $total_usuarios) * 100 : 0;
        
        $rol = $usuario_item['rol_nombre'] ?? 'Sin rol';
        if (!isset($usuarios_por_rol[$rol])) {
            $usuarios_por_rol[$rol] = 0;
        }
        $usuarios_por_rol[$rol]++;
    }
    
    // Obtener distribución de usuarios por rol para las barras de progreso
    $sql_distribucion_roles = "SELECT 
        r.id as rol_id,
        r.descripcion as rol,
        COUNT(u.id) as total_usuarios
    FROM clasif_rol r
    LEFT JOIN clasif_usuarios u ON r.id = u.rol_id
    GROUP BY r.id, r.descripcion
    ORDER BY total_usuarios DESC, r.descripcion ASC";
    
    $stmt_distribucion = $db->query($sql_distribucion_roles);
    $distribucion_roles = $stmt_distribucion->fetchAll();
    
    // Obtener el total REAL de todos los usuarios (activos + inactivos)
    $sql_total_usuarios = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt_total = $db->query($sql_total_usuarios);
    $total_todos_usuarios = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular porcentajes para barras de progreso
    foreach ($distribucion_roles as &$item) {
        $item['porcentaje'] = $total_todos_usuarios > 0 ? 
            round(($item['total_usuarios'] / $total_todos_usuarios) * 100, 1) : 0;
    }
    
} catch (Exception $e) {
    error_log("Error al cargar usuarios: " . $e->getMessage());
    $error = "Error al cargar los usuarios";
}

// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

// Estadísticas para el sidebar
$estadisticas = [];
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// ===== LOGO BASE64 PARA EXPORTACIONES =====
$logo_path = 'assets/logov.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $mime_type = mime_content_type($logo_path);
    $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
} else {
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
            <rect width="50" height="50" fill="#0078D4" rx="5"/>
            <text x="25" y="28" font-family="Arial" font-size="16" fill="white" text-anchor="middle" font-weight="bold">PDL</text>
            <text x="25" y="40" font-family="Arial" font-size="9" fill="white" text-anchor="middle">VISIONES</text>
        </svg>
    ');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- ===== LIBRERÍAS PARA EXPORTACIÓN ===== -->
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/html2pdf.bundle.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>

<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>

    <!-- Windows 11 Styles -->
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

/* Botones personalizados */
.btn-win {
    background: var(--win-accent) !important;
    border-color: var(--win-accent) !important;
    color: white !important;
    border-radius: var(--win-radius-sm) !important;
    padding: 8px 16px !important;
    font-weight: 500 !important;
    transition: var(--win-transition) !important;
}

.btn-win:hover {
    background: color-mix(in srgb, var(--win-accent) 90%, black) !important;
    border-color: color-mix(in srgb, var(--win-accent) 90%, black) !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
}

.btn-win-cancel {
    background: var(--win-bg-tertiary) !important;
    border: 1px solid var(--win-border-color) !important;
    color: var(--win-text-primary) !important;
    border-radius: var(--win-radius-sm) !important;
    padding: 8px 16px !important;
    font-weight: 500 !important;
    transition: var(--win-transition) !important;
}

.btn-win-cancel:hover {
    background: var(--win-bg-secondary) !important;
    border-color: var(--win-text-secondary) !important;
    transform: translateY(-1px) !important;
}

.btn-win-deny {
    background: #ffc107 !important;
    border: 1px solid #ffc107 !important;
    color: #212529 !important;
    border-radius: var(--win-radius-sm) !important;
    padding: 8px 16px !important;
    font-weight: 500 !important;
    transition: var(--win-transition) !important;
}

.btn-win-deny:hover {
    background: #e0a800 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
    transform: translateY(-1px) !important;
}

/* Íconos en botones que no los tienen */
.swal2-confirm:not(:has(i))::before,
.swal2-deny:not(:has(i))::before,
.swal2-cancel:not(:has(i))::before {
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    margin-right: 8px;
    font-size: 14px;
}

.swal2-confirm:not(:has(i))::before {
    content: "\f00c";
}

.swal2-deny:not(:has(i))::before {
    content: "\f05e";
}

.swal2-cancel:not(:has(i))::before {
    content: "\f00d";
}

/* Inputs y selects */
.swal2-input,
.swal2-textarea,
.swal2-select {
    background: var(--win-bg-tertiary) !important;
    border: 1px solid var(--win-border-color) !important;
    color: var(--win-text-primary) !important;
    border-radius: var(--win-radius-sm) !important;
}

.swal2-input:focus,
.swal2-textarea:focus,
.swal2-select:focus {
    border-color: var(--win-accent) !important;
    box-shadow: 0 0 0 2px var(--win-accent-light) !important;
}

/* Footer */
.swal2-footer {
    border-top: 1px solid var(--win-border-color) !important;
    color: var(--win-text-secondary) !important;
}

/* Estilos para animaciones de SweetAlert */
.swal2-show {
    animation: swal2-show 0.3s !important;
}

.swal2-hide {
    animation: swal2-hide 0.15s forwards !important;
}

@keyframes swal2-show {
    0% {
        transform: scale(0.7);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes swal2-hide {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(0.5);
        opacity: 0;
    }
}

/* Mejora de contraste para texto en alerts */
.swal2-html-container .alert {
    margin: 1rem 0;
    padding: 1rem;
    border-radius: var(--win-radius-sm);
    border-left: 4px solid;
}

.swal2-html-container .alert-warning {
    background-color: rgba(255, 193, 7, 0.15);
    border-color: #ffc107;
    color: #ffc107;
}

.swal2-html-container .alert-danger {
    background-color: rgba(220, 53, 69, 0.15);
    border-color: #dc3545;
    color: #dc3545;
}

.swal2-html-container .alert-success {
    background-color: rgba(40, 167, 69, 0.15);
    border-color: #28a745;
    color: #28a745;
}

.swal2-html-container .alert-info {
    background-color: rgba(23, 162, 184, 0.15);
    border-color: #17a2b8;
    color: #17a2b8;
}

/* Mejora de visibilidad para timer progress bar */
.swal2-timer-progress-bar {
    background: var(--win-accent) !important;
}

/* Responsive para SweetAlert */
@media (max-width: 576px) {
    .swal2-popup {
        width: 90% !important;
        margin: 0 auto !important;
    }
}

/* Estilos para alertas de dependencias */
.swal2-html-container .alert {
    margin: 1rem 0;
    padding: 1rem;
    border-radius: 0.375rem;
    border-left: 4px solid #ffc107;
}

.swal2-html-container .alert-warning {
    background-color: rgba(255, 193, 7, 0.1);
    border-color: #ffc107;
    color: #856404;
}

/* Mejorar la presentación del texto con saltos de línea */
.swal2-html-container {
    white-space: pre-line;
    text-align: left;
}

/* Estilos para botones de acción alternativa */
.swal2-actions .swal2-deny {
    background-color: #ffc107 !important;
    border-color: #ffc107 !important;
    color: #212529 !important;
}

.swal2-actions .swal2-deny:hover {
    background-color: #e0a800 !important;
    border-color: #d39e00 !important;
}

/* Barras de progreso animadas */
.progress-bar-container {
    margin-bottom: 1.5rem;
}

.progress-bar-wrapper {
    margin-bottom: 1rem;
}

.progress-bar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.progress-bar-label {
    font-weight: 600;
    color: var(--win-text-primary);
    font-size: 0.9rem;
    flex: 1;
}

.progress-bar-stats {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    font-size: 0.8rem;
    margin-left: 1rem;
}

.progress-bar-count {
    color: var(--win-accent);
    font-weight: 600;
    min-width: 50px;
    text-align: right;
}

.progress-bar-percentage {
    color: var(--win-text-secondary);
    min-width: 50px;
    text-align: right;
}

.progress-bar-track {
    height: 10px;
    background: var(--win-bg-tertiary);
    border-radius: 5px;
    overflow: hidden;
    position: relative;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 5px;
    background: var(--win-accent);
    width: 0;
    transition: width 1.5s ease-in-out;
    position: relative;
    box-shadow: 0 0 10px rgba(0, 120, 212, 0.3);
}

.progress-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, 
        transparent 0%, 
        rgba(255, 255, 255, 0.1) 50%, 
        transparent 100%);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.progress-bar-empty {
    height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--win-bg-tertiary);
    border-radius: var(--win-radius);
    border: 1px dashed var(--win-border_color);
}

.progress-bar-empty i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--win-text-secondary);
}

/* Estadísticas circulares */
.circular-stats {
    display: flex;
    justify-content: space-around;
    margin: 1.5rem 0;
}

.circular-stat {
    text-align: center;
    position: relative;
    width: 80px;
    height: 80px;
}

.circular-progress {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: conic-gradient(var(--win-accent) 0% var(--percentage), 
                               var(--win-bg-tertiary) var(--percentage) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.circular-progress::before {
    content: '';
    position: absolute;
    width: 70px;
    height: 70px;
    background: var(--win-bg-secondary);
    border-radius: 50%;
}

.circular-value {
    position: relative;
    z-index: 1;
    font-weight: 600;
    color: var(--win-text-primary);
    font-size: 1.2rem;
}

.circular-label {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--win-text-secondary);
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

/* Efecto Mica (Windows 11) */
.mica-effect {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.05);
}

[data-theme="light"] .mica-effect {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(0, 0, 0, 0.08);
}

/* Navbar */
.win-navbar {
    height: 48px;
    background: var(--win-bg-secondary);
    border-bottom: 1px solid var(--win-border-color);
    padding: 0 16px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 12px;
}

.win-navbar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.win-navbar-brand i {
    color: var(--win-accent);
}

.win-nav-search {
    flex: 1;
    max-width: 400px;
    position: relative;
    margin-bottom: 5px;
    padding: 0 5px;
}

.win-nav-search input {
    background: var(--win-bg-tertiary);
    border: 1px solid var(--win-border-color);
    color: var(--win-text-primary);
    border-radius: var(--win-radius-sm);
    padding: 8px 12px 8px 30px;
    font-size: 13px;
    width: 100%;
    transition: var(--win-transition);
}

.win-nav-search input:focus {
    outline: none;
    border-color: var(--win-accent);
    box-shadow: 0 0 0 2px var(--win-accent-light);
}

.win-nav-search i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 14px;
}

/* Sidebar inmersivo */
.win-sidebar {
    width: 260px;
    background: var(--win-bg-secondary);
    border-right: 1px solid var(--win-border_color);
    height: calc(100vh - 48px);
    position: fixed;
    left: 0;
    top: 48px;
    z-index: 999;
    transition: var(--win-transition);
    overflow-y: auto;
    padding: 16px 0;
}

.win-sidebar.mini {
    width: 68px;
}

.win-sidebar-header {
    padding: 0 16px 16px;
    border-bottom: 1px solid var(--win-border_color);
    margin-bottom: 16px;
}

.win-sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: var(--win-radius-sm);
    transition: var(--win-transition);
}

.win-sidebar-user:hover {
    background: var(--win-bg-tertiary);
}

.win-sidebar-user-avatar {
    width: 36px;
    height: 36px;
    background: var(--win-accent);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.win-sidebar-user-info h6 {
    margin: 0;
    font-size: 14px;
    font-weight: 500;
}

.win-sidebar-user-info small {
    color: var(--win-text-secondary);
    font-size: 12px;
}

.win-sidebar.mini .win-sidebar-user-info {
    display: none;
}

/* Menú lateral */
.win-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}

.win-nav-item {
    margin: 2px 8px;
}

.win-nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    color: var(--win-text-secondary);
    text-decoration: none;
    border-radius: var(--win-radius-sm);
    transition: var(--win-transition);
    font-size: 14px;
    position: relative;
}

.win-nav-link:hover {
    background: var(--win-bg-tertiary);
    color: var(--win-text-primary);
}

.win-nav-link.active {
    background: var(--win-accent-light);
    color: var(--win-accent);
    font-weight: 500;
}

.win-nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 4px;
    bottom: 4px;
    width: 3px;
    background: var(--win-accent);
    border-radius: 0 2px 2px 0;
}

.win-nav-icon {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.win-sidebar.mini .win-nav-text {
    display: none;
}

.win-nav-badge {
    margin-left: auto;
    background: var(--win-accent);
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
    font-weight: 600;
    transition: var(--win-transition);
}

.win-nav-link:hover .win-nav-badge {
    background: color-mix(in srgb, var(--win-accent) 90%, black);
}

/* Contenido principal */
.win-main-content {
    margin-left: 260px;
    margin-top: 48px;
    padding: 24px;
    transition: var(--win-transition);
    min-height: calc(100vh - 48px);
}

.win-main-content.sidebar-mini {
    margin-left: 68px;
}

/* Estilos para contenido */
.win-main-content .card {
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border_color);
    border-radius: var(--win-radius);
    transition: var(--win-transition);
    margin-bottom: 1.5rem;
}

.win-main-content .card:hover {
    border-color: var(--win-accent);
    box-shadow: var(--win-shadow);
}

.win-main-content .card-header {
    background: var(--win-bg-tertiary);
    border-bottom: 1px solid var(--win-border_color);
    padding: 1rem 1.25rem;
}

.win-main-content .card-body {
    padding: 1.25rem;
    color: var(--win-text-primary);
}

.win-main-content .table {
    color: var(--win-text-primary);
    border-color: var(--win-border_color);
}

.win-main-content .table th {
    background: var(--win-bg-tertiary);
    border-color: var(--win-border_color);
    font-weight: 600;
    color: var(--win-text-primary);
}

.win-main-content .table td {
    border-color: var(--win-border_color);
    color: var(--win-text-primary);
}

.win-main-content .table-hover tbody tr:hover {
    background: var(--win-bg-tertiary);
}

.win-main-content .btn {
    border-radius: var(--win-radius-sm);
    transition: var(--win-transition);
}

.win-main-content .btn-primary {
    background: var(--win-accent);
    border-color: var(--win-accent);
}

.win-main-content .btn-primary:hover {
    background: color-mix(in srgb, var(--win-accent) 90%, black);
    border-color: color-mix(in srgb, var(--win-accent) 90%, black);
}

.win-main-content .btn-outline-primary {
    color: var(--win-accent);
    border-color: var(--win-accent);
}

.win-main-content .btn-outline-primary:hover {
    background: var(--win-accent);
    color: white;
}

.win-main-content .border-bottom {
    border-bottom-color: var(--win-border_color) !important;
}

.win-main-content .text-muted {
    color: var(--win-text-secondary) !important;
}

.win-main-content .bg-light {
    background-color: var(--win-bg-tertiary) !important;
}

.win-main-content .table-light {
    background-color: var(--win-bg-tertiary) !important;
}

.win-main-content .table-secondary {
    background-color: var(--win-bg-tertiary) !important;
}

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

/* Quick Actions (Botones flotantes) */
.win-quick-actions {
    position: fixed;
    bottom: 80px;
    right: 24px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 1000;
}

.win-quick-action {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--win-accent);
    color: white;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 1001;
}

.win-quick-action:hover {
    transform: scale(1.15) rotate(5deg);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}

/* Quick Actions Expandibles */
.win-quick-actions-expanded {
    position: absolute;
    bottom: 100%;
    right: 0;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    opacity: 0;
    transform: translateY(20px) scale(0.8);
    pointer-events: none;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 1002;
}

.win-quick-actions-expanded.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: all;
}

/* Animación circular para los botones expandidos */
.win-quick-actions-expanded .win-quick-action {
    width: 48px;
    height: 48px;
    font-size: 18px;
    transform: scale(0);
    animation: expandButton 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
}

.win-quick-actions-expanded.show .win-quick-action:nth-child(1) {
    animation-delay: 0.1s;
}

.win-quick-actions-expanded.show .win-quick-action:nth-child(2) {
    animation-delay: 0.2s;
}

.win-quick-actions-expanded.show .win-quick-action:nth-child(3) {
    animation-delay: 0.3s;
}

.win-quick-actions-expanded.show .win-quick-action:nth-child(4) {
    animation-delay: 0.4s;
}

@keyframes expandButton {
    0% {
        transform: scale(0) rotate(-90deg);
        opacity: 0;
    }
    100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

.win-quick-actions-expanded .action-usuario {
    background: #e3008c;
}

.win-quick-actions-expanded .action-factura {
    background: #0078d4;
}

.win-quick-actions-expanded .action-cliente {
    background: #107c10;
}

.win-quick-actions-expanded .action-servicio {
    background: #5c2d91;
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

/* Formularios */
.win-main-content .form-control,
.win-main-content .form-select {
    background-color: var(--win-bg-tertiary);
    border-color: var(--win-border_color);
    color: var(--win-text-primary);
}

.win-main-content .form-control:focus,
.win-main-content .form-select:focus {
    background-color: var(--win-bg-tertiary);
    border-color: var(--win-accent);
    color: var(--win-text-primary);
    box-shadow: 0 0 0 0.25rem var(--win-accent-light);
}

.win-main-content .form-label {
    color: var(--win-text-primary);
    font-weight: 500;
}

/* Stats cards */
.stat-card {
    border: none;
    border-radius: var(--win-radius);
    border-left: 4px solid;
    background: var(--win-bg-secondary);
    transition: var(--win-transition);
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--win-shadow);
}

.stat-card.success {
    border-left-color: #28a745;
}

.stat-card.warning {
    border-left-color: #ffc107;
}

.stat-card.info {
    border-left-color: #17a2b8;
}

.stat-card .text-success {
    color: #28a745 !important;
}

.stat-card .text-warning {
    color: #ffc107 !important;
}

.stat-card .text-info {
    color: #17a2b8 !important;
}

/* Búsqueda y filtros */
.filtros-card {
    margin-bottom: 1.5rem;
}

.search-box {
    position: relative;
}

.search-box input {
    padding-right: 40px;
}

.search-box .search-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--win-text-secondary);
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

/* Avatar styles */
.avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--win-border_color);
}

.avatar-medium {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--win-accent);
}

.avatar-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--win-accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}

.avatar-initials-medium {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--win-accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
}

/* User card styles */
.user-card {
    transition: var(--win-transition);
    border-left: 4px solid transparent;
}

.user-card:hover {
    transform: translateX(4px);
    border-left-color: var(--win-accent);
}

.user-card.active {
    border-left-color: #28a745;
}

.user-card.inactive {
    border-left-color: #dc3545;
}

/* ==================== CORRECCIÓN DE LEGIBILIDAD TEXT-MUTED ==================== */

/* Para el tema oscuro (por defecto) - Usamos un gris muy claro */
.text-muted, 
.win-main-content .text-muted,
.form-text,
small.text-muted {
    color: #e0e0e0 !important; /* Casi blanco (mucho más visible) */
    opacity: 0.9;
}

/* Para el tema claro - Usamos un gris oscuro */
[data-theme="light"] .text-muted,
[data-theme="light"] .win-main-content .text-muted,
[data-theme="light"] .form-text,
[data-theme="light"] small.text-muted {
    color: #555555 !important; /* Gris oscuro fuerte */
    opacity: 1;
}

/* Ajuste específico para los placeholders de los inputs para que también se vean */
::placeholder {
    color: #cccccc !important;
    opacity: 0.7 !important;
}

[data-theme="light"] ::placeholder {
    color: #666666 !important;
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

/* Estilos para botones de exportación */
.btn-group .btn-outline-danger:hover i { color: white; }
.btn-group .btn-outline-success:hover i { color: white; }
.btn-group .btn-outline-primary:hover i { color: white; }
.btn-group .btn-outline-info:hover i { color: white; }
.btn-group .btn-outline-secondary:hover i { color: white; }
.btn-group .btn-outline-warning:hover i { color: white; }

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
                <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" 
                     data-theme="dark">
                    <i class="fas fa-moon mb-2"></i>
                    <div>Oscuro</div>
                </div>
            </div>
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" 
                     data-theme="light">
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
        <!-- Botón hamburguesa para móvil -->
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">GESTIÓN USUARIOS - SISFACT PDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Acciones del navbar -->
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
<?= renderNotificationsDropdown() ?>
        
        <!-- Perfil de usuario -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
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
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Dashboard</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Panel principal</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="perfil.php">
                        <i class="fas fa-user me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Mi Perfil</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Ver y editar tu información</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="configuracion.php">
                        <i class="fas fa-cog me-3 text-secondary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Configuración</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Preferencias del sistema</small>
                        </div>
                    </a>
                </li>
                
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 text-warning" href="bloquear_sesion.php">
                        <i class="fas fa-lock me-3" style="width: 20px;"></i>
                        <div>
                            <span class="d-block fw-bold">Bloquear Sesión</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Bloquear pantalla temporalmente</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i>
                        <div>
                            <span class="d-block fw-bold">Cerrar Sesión</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small>
                        </div>
                    </a>
                </li>
                
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

    <!-- Sidebar inmersivo -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
        <a href="perfil.php" style="text-decoration: none; display: block;">
            <div class="win-sidebar-header">
                <div class="win-sidebar-user">
                    <div class="win-sidebar-user-avatar">
                        <?php if (!empty($usuario['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
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
					<!-- Badge con F/Cierre y Tooltip -->
					Dashboard<span class="win-nav-badge" 
						  title="Fecha de Cierre Actual: <?php echo fechaInicioFormateada(13); ?>" 
						  data-bs-toggle="tooltip" 
						  data-bs-placement="auto"
						  style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
						F/Cierre: <?php echo fechaInicioFormateada(9); ?>
					</span>
				</a>
			</li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo $total_categorias; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $total_servicios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo $total_usuarios; ?></span>
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
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Configuración</small></li>
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
					<span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="configuracion.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-cog"></i>
                    <span class="win-nav-text">Configuración</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="historico_view.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-history"></i>
                    <span class="win-nav-text">Histórico</span>
                    <span class="win-nav-badge"><?php echo $estadisticas['total']; ?></span>
                </a>
            </li>
        </ul>
        
        <?php
        // Obtener datos globales de facturación (CUP)
        $finanzas = Database::getProgresoFinanciero();
        ?>
        <div class="mt-4 px-3">
            <!-- Título y Estado -->
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo fechaInicioFormateada(9); ?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;">
                        <?php echo $finanzas['mensaje']; ?>
                    </small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                    <?php echo number_format($finanzas['porcentaje'], 1); ?>%
                </h5>
            </div>

            <!-- Barra de progreso -->
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>; transition: width 1s ease-in-out;" 
                     aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            
            <!-- Datos numéricos: Dinero y Cantidad -->
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <!-- Dinero Real -->
                    <small class="text-muted" style="font-size: 12px;">
                        <strong>$<?php echo number_format($finanzas['real'], 2); ?></strong>
                    </small>
                    <!-- Cantidad de Facturas (Nuevo) -->
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;">
                        <i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas
                    </small>
                </div>
                
                <!-- Meta -->
                <small class="text-end text-success" style="font-size: 12px;">
                    Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?>
                </small>
            </div>
        </div>
    </aside>

   <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-users me-2" style="color: var(--win-accent);"></i>Gestión de Usuarios
                </h1>
                <p class="text-muted mb-0">Administre los usuarios del sistema</p>
                <p class="text-muted small mb-0">
                    <i class="fas fa-calendar-alt me-1"></i> 
                    Fecha de Inicio de Operaciones: <span class="fw-bold text-warning"><?php echo $fecha_inicio_formateada; ?></span> | 
                    Período de Cierre: <span class="fw-bold text-warning"><?php echo $mes_cierre_nombre; ?> / <?php echo $año_inicio_operaciones; ?></span>
                </p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="actualizarListaUsuarios()">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                    <a href="nuevo_usuario.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-plus me-1"></i>Nuevo Usuario
                    </a>
                </div>
            </div>
        </div>

        <!-- Mostrar alertas de éxito/error si existen -->
        <?php if ($mostrar_alerta && $alerta_exito): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $alerta_exito; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($mostrar_alerta && $alerta_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $alerta_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Estilo Advanced Telemetry - DATOS REALES -->
        <div class="col-xl-12 mb-3">
            <div class="card border-0 shadow-lg animate__animated animate__fadeIn" 
                 style="background: linear-gradient(135deg, rgba(28, 28, 30, 0.95) 0%, rgba(10, 10, 12, 1) 100%); 
                        border-radius: 14px; 
                        backdrop-filter: blur(12px); 
                        border: 1px solid rgba(255,255,255,0.08) !important;
                        overflow: hidden;">
                
                <!-- Línea de acento con resplandor (Glow) usando tu color de acento -->
                <div style="height: 3px; background: linear-gradient(90deg, transparent, var(--win-accent), transparent); filter: drop-shadow(0 0 5px var(--win-accent));"></div>
                
                <div class="card-body p-4">
                    <div class="row g-4">
                        
                        <!-- COLUMNA IZQUIERDA: MÉTRICAS MAESTRAS -->
                        <div class="col-lg-8 border-end" style="border-color: rgba(255,255,255,0.1) !important;">
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="text-uppercase fw-bold mb-0" style="font-size: 0.65rem; letter-spacing: 1.5px; color: var(--win-accent); opacity: 0.8;">
                                    <i class="fas fa-terminal me-2"></i>USUARIOS EN SISFACT PDL VISIONES
                                </h6>
                                <div class="badge text-success border border-success border-opacity-25" style="background-color: rgba(40, 167, 69, 0.1); font-size: 0.6rem;">
                                    <i class="fas fa-sync fa-spin me-1"></i> EN TIEMPO REAL
                                </div>
                            </div>

                            <div class="row align-items-end mb-4">
                                <div class="col-auto">
                                    <!-- Dato Real: Total Usuarios -->
                                    <div class="display-5 fw-bold text-white mb-0 lh-1" id="statTotal">
                                        <?php echo $total_usuarios; ?>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="text-uppercase fw-bold text-muted lh-1 mb-1" style="font-size: 0.7rem; opacity: 0.6;">Total de Usuarios Registrados</div>
                                    <div class="text-success small fw-bold"><i class="fas fa-database me-1"></i>Master Database</div>
                                </div>
                            </div>

                            <!-- Grid de Detalles -->
                            <div class="row g-2">
                                <!-- Activos (Dato Real) -->
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                        <span class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.55rem; letter-spacing: 0.5px;">Activos</span>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="h4 fw-bold text-success mb-0" id="statActivos"><?php echo $total_usuarios_activos; ?></span>
                                            <i class="fas fa-circle-check text-success opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <!-- Inactivos (Dato Real) -->
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                        <span class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.55rem; letter-spacing: 0.5px;">Inactivos</span>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="h4 fw-bold text-danger mb-0" id="statInactivos"><?php echo $total_usuarios_inactivos; ?></span>
                                            <i class="fas fa-user-slash text-danger opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <!-- Administradores (Dato Real - Calculado por tu bucle) -->
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                        <span class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.55rem; letter-spacing: 0.5px;">Admins</span>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="h4 fw-bold text-warning mb-0" id="statAdmins">
                                                <?php 
                                                $admin_count = 0;
                                                foreach ($usuarios as $user) { if (($user['rol_nombre'] ?? '') == 'Administrador del Sistema') $admin_count++; }
                                                echo $admin_count;
                                                ?>
                                            </span>
                                            <i class="fas fa-shield-halved text-warning opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <!-- Nuevos del Mes (Dato Real - Calculado por tu bucle) -->
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                        <span class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.55rem; letter-spacing: 0.5px;">Este Mes</span>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="h4 fw-bold text-info mb-0">
                                                <?php 
                                                $current_month = date('Y-m');
                                                $monthly_count = 0;
                                                foreach ($usuarios as $user) { if (strpos($user['fecha_registro'], $current_month) === 0) $monthly_count++; }
                                                echo $monthly_count;
                                                ?>
                                            </span>
                                            <i class="fas fa-calendar-plus text-info opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- COLUMNA DERECHA: GAUGE DE TASA REAL -->
                        <div class="col-lg-4 d-flex flex-column justify-content-center align-items-center">
                            <div class="position-relative d-flex align-items-center justify-content-center mb-3">
                                <!-- SVG del Gauge Técnico con Tasa Real -->
                                <svg width="120" height="120" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="6" />
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="var(--win-accent)" stroke-width="6" 
                                            stroke-dasharray="<?php echo (2 * pi() * 45) * ($tasa_actividad/100); ?> 282.7" 
                                            stroke-linecap="round" transform="rotate(-90 50 50)" 
                                            style="filter: drop-shadow(0 0 3px var(--win-accent)); transition: stroke-dasharray 1.5s ease-in-out;" />
                                </svg>
                                <div class="position-absolute text-center">
                                    <span class="h3 fw-bold text-white mb-0" id="statTasaVal"><?php echo number_format($tasa_actividad, 0); ?>%</span>
                                </div>
                            </div>

                            <div class="text-center">
                                <h6 class="text-white text-uppercase fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 2px; opacity: 0.7;">Tasa de Actividad</h6>
                                <div class="text-muted small mb-2" id="statTasaText">
                                    <?php echo $total_usuarios_activos; ?> de <?php echo $total_usuarios; ?> Activos
                                </div>
                                <div class="progress bg-white bg-opacity-10" style="height: 4px; width: 120px; border-radius: 10px;">
                                    <div class="progress-bar" id="statTasaBar" role="progressbar" 
                                         style="width: <?php echo $tasa_actividad; ?>%; background-color: var(--win-accent);"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- BARRA DE ROLES (DAtos Reales calculados por tus bucles inferiores) -->
                <div class="px-4 py-2 border-top d-flex justify-content-between align-items-center" 
                     style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05) !important;">
                    <div class="d-flex flex-wrap gap-4">
                        <!-- Supervisores -->
                        <span style="font-size: 0.65rem; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-circle text-warning me-2" style="font-size: 0.4rem;"></i>SUPERVISORES: 
                            <strong class="text-white">
                                <?php 
                                $supervisor_count = 0;
                                foreach ($usuarios as $user) { if (($user['rol_nombre'] ?? '') == 'Supervisor General') $supervisor_count++; }
                                echo $supervisor_count;
                                ?>
                            </strong>
                        </span>
                        <!-- Programadores -->
                        <span style="font-size: 0.65rem; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-circle me-2" style="font-size: 0.4rem; color: #C532FA;"></i>PROGRAMADORES: 
                            <strong class="text-white">
                                <?php 
                                $programador_count = 0;
                                foreach ($usuarios as $user) { if (($user['rol_nombre'] ?? '') == 'Programador') $programador_count++; }
                                echo $programador_count;
                                ?>
                            </strong>
                        </span>
                        <!-- Editores -->
                        <span style="font-size: 0.65rem; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-circle text-info me-2" style="font-size: 0.4rem;"></i>EDITORES: 
                            <strong class="text-white">
                                <?php 
                                $facturador_count = 0;
                                foreach ($usuarios as $user) { if (($user['rol_nombre'] ?? '') == 'Facturador / Editor') $facturador_count++; }
                                echo $facturador_count;
                                ?>
                            </strong>
                        </span>
                        <!-- Visualizadores -->
                        <span style="font-size: 0.65rem; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-circle text-primary me-2" style="font-size: 0.4rem;"></i>VISUALIZADORES: 
                            <strong class="text-white">
                                <?php 
                                $visualizador_count = 0;
                                foreach ($usuarios as $user) { if (($user['rol_nombre'] ?? '') == 'Visualizador') $visualizador_count++; }
                                echo $visualizador_count;
                                ?>
                            </strong>
                        </span>
                    </div>
                    <div class="d-none d-md-block">
                        <span class="text-muted" style="font-size: 0.6rem; letter-spacing: 1px; opacity: 0.5;">TELEMETRÍA DE USUARIOS</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primero Resumen de Usuarios y al lado Distribución de Usuarios por Rol -->
        <div class="row mb-4">
            <!-- COLUMNA IZQUIERDA: Resumen de Usuarios -->
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-chart-pie me-1"></i>Resumen de Usuarios
                        </h6>
                        <span class="badge" style="background-color: var(--win-accent);">
                            <i class="fas fa-user"></i>
                        </span>
                    </div>
                    <div class="card-body">
                        <!-- Estadísticas rápidas -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Usuarios Activos</span>
                                <span class="fw-bold text-success"><?php echo $total_usuarios_activos; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Administradores</span>
                                <span class="fw-bold text-warning">
                                    <?php 
                                    $admin_count = 0;
                                    foreach ($usuarios as $user) {
                                        if (($user['rol_nombre'] ?? '') == 'Administrador del Sistema') {
                                            $admin_count++;
                                        }
                                    }
                                    echo $admin_count;
                                    ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Supervisores</span>
                                <span class="fw-bold text-warning">
                                    <?php 
                                    $supervisor_count = 0;
                                    foreach ($usuarios as $user) {
                                        if (($user['rol_nombre'] ?? '') == 'Supervisor General') {
                                            $supervisor_count++;
                                        }
                                    }
                                    echo $supervisor_count;
                                    ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Programadores</span>
                                <span class="fw-bold text-warning">
                                    <?php 
                                    $programador_count = 0;
                                    foreach ($usuarios as $user) {
                                        if (($user['rol_nombre'] ?? '') == 'Programador') {
                                            $programador_count++;
                                        }
                                    }
                                    echo $programador_count;
                                    ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Facturadores / Editores</span>
                                <span class="fw-bold text-info">
                                    <?php 
                                    $facturador_count = 0;
                                    foreach ($usuarios as $user) {
                                        if (($user['rol_nombre'] ?? '') == 'Facturador / Editor') {
                                            $facturador_count++;
                                        }
                                    }
                                    echo $facturador_count;
                                    ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Visualizadores</span>
                                <span class="fw-bold text-info">
                                    <?php 
                                    $visualizador_count = 0;
                                    foreach ($usuarios as $user) {
                                        if (($user['rol_nombre'] ?? '') == 'Visualizador') {
                                            $visualizador_count++;
                                        }
                                    }
                                    echo $visualizador_count;
                                    ?>
                                </span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $total_usuarios > 0 ? ($total_usuarios_activos / $total_usuarios * 100) : 0; ?>%">
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?php echo $total_usuarios_activos; ?> de <?php echo $total_usuarios; ?> activos
                            </small>
                        </div>
                        
                        <hr class="my-3">
                        
                        <!-- Acciones rápidas -->
                        <div>
                            <h6 class="mb-3" style="color: var(--win-text-primary); font-size: 0.9rem;">
                                <i class="fas fa-bolt me-1" style="color: var(--win-accent);"></i>Acciones Rápidas
                            </h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="nuevo_usuario.php" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fas fa-user-plus me-1"></i>Nuevo
                                    </a>
                                </div>
                                <div class="col-6">
                                    <button onclick="exportarUsuarios()" class="btn btn-sm btn-outline-success w-100">
                                        <i class="fas fa-download me-1"></i>Exportar
                                    </button>
                                </div>
                                <div class="col-6">
                                    <a href="roles.php" class="btn btn-sm btn-outline-info w-100">
                                        <i class="fas fa-user-tag me-1"></i>Gestionar Roles
                                    </a>
                                </div>
                                <div class="col-6">
                                    <button onclick="imprimirTabla()" class="btn btn-sm btn-outline-secondary w-100">
                                        <i class="fas fa-print me-1"></i>Imprimir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- COLUMNA DERECHA: Distribución de Usuarios por Rol -->
            <div class="col-md-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-chart-bar me-1"></i>Distribución de Usuarios por Rol
                            <small class="text-primary">(Total usuarios: <?php echo $total_usuarios; ?>)</small>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Crear array combinado con todos los roles (incluyendo los sin usuarios)
                        $todos_los_roles_completos = [];
                        
                        // Agregar roles de la BD (todos, aunque tengan 0 usuarios)
                        foreach ($roles as $rol_db) {
                            $rol_nombre = $rol_db['descripcion'];
                            $cantidad = isset($usuarios_por_rol[$rol_nombre]) ? $usuarios_por_rol[$rol_nombre] : 0;
                            $todos_los_roles_completos[$rol_nombre] = $cantidad;
                        }
                        
                        // Agregar "Sin rol" si existe
                        if (isset($usuarios_por_rol['Sin rol'])) {
                            $todos_los_roles_completos['Sin rol'] = $usuarios_por_rol['Sin rol'];
                        }
                        
                        // Ordenar por cantidad (mayor a menor)
                        arsort($todos_los_roles_completos);
                        
                        // Función para obtener color según índice
                        function getColor($index) {
                            $colors = ['#0078d4', '#107c10', '#5c2d91', '#e81123', '#ff8c00', '#0099bc', '#e3008c', '#8764b8'];
                            return $colors[$index % count($colors)];
                        }
                        
                        // Mostrar barras de progreso
                        if (!empty($todos_los_roles_completos)):
                        ?>
                        <div class="progress-bar-container">
                            <?php 
                            $index = 0;
                            foreach ($todos_los_roles_completos as $rol_nombre => $cantidad): 
                                $porcentaje = $total_usuarios > 0 ? round(($cantidad / $total_usuarios) * 100, 1) : 0;
                                $color = getColor($index);
                                $index++;
                            ?>
                            <div class="progress-bar-wrapper" data-percentage="<?php echo $porcentaje; ?>">
                                <div class="progress-bar-header">
                                    <span class="progress-bar-label">
                                        <?php echo htmlspecialchars($rol_nombre); ?>
                                        <?php if ($cantidad == 0): ?>
                                            <small class="text-muted ms-1">(sin usuarios)</small>
                                        <?php endif; ?>
                                    </span>
                                    <div class="progress-bar-stats">
                                        <span class="progress-bar-count">
                                            <?php echo $cantidad; ?> usuario<?php echo $cantidad != 1 ? 's' : ''; ?>
                                        </span>
                                        <span class="progress-bar-percentage"><?php echo $porcentaje; ?>%</span>
                                    </div>
                                </div>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" 
                                         style="background-color: <?php echo $color; ?>; 
                                                --target-width: <?php echo $porcentaje; ?>%;
                                                <?php echo $cantidad == 0 ? 'opacity: 0.3;' : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Estadísticas rápidas -->
                        <div class="row mt-4 pt-3 border-top">
                            <div class="col-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-success"><?php echo $total_usuarios_activos; ?></div>
                                    <small class="text-muted">Activos</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-danger"><?php echo $total_usuarios_inactivos; ?></div>
                                    <small class="text-muted">Inactivos</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-info"><?php echo count($todos_los_roles_completos); ?></div>
                                    <small class="text-muted">Roles totales</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-primary"><?php echo count(array_filter($todos_los_roles_completos, function($cantidad) { return $cantidad > 0; })); ?></div>
                                    <small class="text-muted">Roles con usuarios</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="mt-3">
                            <div class="alert alert-info small py-2 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Se muestran <strong>todos los roles</strong> definidos en el sistema, incluyendo aquellos sin usuarios asignados.
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <div class="progress-bar-empty">
                            <i class="fas fa-users fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-2">No hay roles definidos en el sistema</p>
                            <a href="roles.php" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-user-tag me-1"></i>Gestionar Roles
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Búsqueda y filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-search me-1"></i>Buscar Usuarios
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="search-box">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Buscar por nombre, apellido o CI...">
                            <div class="search-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterRole">
                            <option value="">Todos los roles</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo htmlspecialchars($rol['descripcion']); ?>">
                                    <?php echo htmlspecialchars($rol['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus">
                            <option value="">Todos los estados</option>
                            <option value="activo">Solo activos</option>
                            <option value="inactivo">Solo inactivos</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="filtrarUsuarios()">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">Lista de Usuarios</h6>
                <div>
                    <span class="badge bg-info me-2"><?php echo $total_usuarios; ?> usuarios</span>
                    <span class="badge bg-success">Activos: <?php echo $total_usuarios_activos; ?></span>
                    
                    <!-- ===== BOTONES DE EXPORTACIÓN - CON TODOS LOS FORMATOS ===== -->
                    <div class="btn-group ms-2" role="group">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="exportarExcel()" 
                                title="Exportar a Excel" data-bs-toggle="tooltip">
                            <i class="fas fa-file-excel"></i>
                        </button>          
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="exportarPDF()" 
                                title="Exportar a PDF" data-bs-toggle="tooltip">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportarWord()" 
                                title="Exportar a Word" data-bs-toggle="tooltip">
                            <i class="fas fa-file-word"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="exportarCSV()" 
                                title="Exportar a CSV" data-bs-toggle="tooltip">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarTXT()" 
                                title="Exportar a TXT" data-bs-toggle="tooltip">
                            <i class="fas fa-file-alt"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="imprimirTabla()" 
                                title="Imprimir" data-bs-toggle="tooltip">
                            <i class="fas fa-print"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="exportarUsuarios()" 
                                title="Todos los formatos" data-bs-toggle="tooltip">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usuariosTable">
                        <thead>
                            <tr>
                                <th>Avatar</th>
                                <th>Nombre Completo</th>
                                <th>Carnet Identidad</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-users text-muted fa-2x mb-2 d-block"></i>
                                        No hay usuarios registrados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $user): ?>
                                    <tr data-id="<?php echo $user['id']; ?>" 
                                        data-role="<?php echo htmlspecialchars($user['rol_nombre'] ?? 'Sin rol'); ?>" 
                                        data-status="<?php echo $user['activo'] ? 'activo' : 'inactivo'; ?>">
                                        <td>
                                            <?php if (!empty($user['foto'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['foto']); ?>" 
                                                     alt="Avatar" 
                                                     class="avatar-small"
                                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iMTYiIGZpbGw9IiMwMDc4ZDQiLz4KPHRleHQgeD0iMTYiIHk9IjE4IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IndoaXRlIiBmb250LXdlaWdodD0iYm9sZCI+CiAgICA8dHNwYW4gZHk9IjAuM2VtIj4KICAgICAgICA8dHNwYW4gZD0iTTAgMEgxNlYxNkgwWiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoOCwgOCkiLz4KICAgICAgICA8dHNwYW4gZD0iTTAgMEgxNlYxNkgwWiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoOCwgMTYpIi8+CiAgICA8L3RzcGFuPgo8L3RleHQ+Cjwvc3ZnPg=='">
                                            <?php else: ?>
                                                <div class="avatar-initials">
                                                    <?php echo strtoupper(substr($user['nombre'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: var(--win-text-primary);">
                                                <?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($user['email'] ?? 'Sin email'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($user['no_ci']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($user['usuario']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if (($user['rol_nombre'] ?? '') == 'Administrador del Sistema') {
                                                    echo 'bg-success';
                                                } elseif (($user['rol_nombre'] ?? '') == 'Facturador / Editor') {
                                                    echo 'bg-info';
                                                } elseif (($user['rol_nombre'] ?? '') == 'Visualizador') {
                                                    echo 'bg-primary';
                                                } elseif (($user['rol_nombre'] ?? '') == 'Supervisor General') {
                                                    echo 'bg-warning text-dark';
                                                } elseif (($user['rol_nombre'] ?? '') == 'Programador') {
                                                    echo 'bg-dark';
                                                } else {
                                                    echo 'bg-secondary';
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($user['rol_nombre'] ?? 'Sin rol'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $user['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $user['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="nuevo_usuario.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-warning" 
                                                   title="Editar" data-bs-toggle="tooltip">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="toggleEstadoUsuario(<?php echo $user['id']; ?>, <?php echo $user['activo'] ? 'true' : 'false'; ?>, '<?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos'], ENT_QUOTES); ?>')" 
                                                        class="btn <?php echo $user['activo'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>" 
                                                        title="<?php echo $user['activo'] ? 'Desactivar' : 'Activar'; ?>" 
                                                        data-bs-toggle="tooltip"
                                                        <?php echo $user['id'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                                                    <i class="fas <?php echo $user['activo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                </button>
                                                <button onclick="eliminarUsuario(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos'], ENT_QUOTES); ?>', <?php echo $user['activo'] ? 'true' : 'false'; ?>)" 
                                                        class="btn btn-outline-danger" 
                                                        title="Eliminar" data-bs-toggle="tooltip"
                                                        <?php echo $user['id'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Quick Actions -->
    <div class="win-quick-actions">
        <!-- Acciones expandidas -->
        <div class="win-quick-actions-expanded" id="quickActionsExpanded">
            <button class="win-quick-action action-factura" onclick="window.location.href='nueva_factura.php'" title="Nueva Factura">
                <i class="fas fa-file-invoice"></i>
            </button>
            <button class="win-quick-action action-cliente" onclick="window.location.href='nuevo_cliente.php'" title="Nuevo Cliente">
                <i class="fas fa-user-plus"></i>
            </button>
            <button class="win-quick-action action-servicio" onclick="window.location.href='nuevo_servicio.php'" title="Nuevo Servicio">
                <i class="fas fa-plus-circle"></i>
            </button>
            <!-- AGREGAR ESTE NUEVO BOTÓN -->
            <button class="win-quick-action action-usuario" onclick="window.location.href='nuevo_usuario.php'" title="Nuevo Usuario">
                <i class="fas fa-user-plus"></i>
            </button>
        </div>
        
        <!-- Botón principal -->
        <button class="win-quick-action" onclick="toggleQuickActions()" title="Acciones rápidas" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <!-- ===== SCRIPTS DE EXPORTACIÓN - IMPLEMENTACIÓN COMPLETA ===== -->
    <script>
        // ============================================
        // VARIABLES GLOBALES PARA EXPORTACIÓN
        // ============================================
        const totalUsuarios = <?php echo $total_usuarios; ?>;
        const usuariosActivos = <?php echo $total_usuarios_activos; ?>;
        const usuariosInactivos = <?php echo $total_usuarios_inactivos; ?>;
        const usuarioNombre = "<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'SISFACT PDL VISIONES'); ?>";
        const logoBase64 = '<?php echo $logo_base64; ?>';
        
        // ===== VARIABLES PARA FECHAS DE CIERRE DE OPERACIONES =====
        const fechaInicioOperaciones = "<?php echo $fecha_inicio_operaciones; ?>";
        const fechaInicioOperacionesFormateada = "<?php echo $fecha_inicio_formateada; ?>";
        const mesCierreOperaciones = <?php echo $mes_cierre_operaciones; ?>;
        const mesCierreOperacionesNombre = "<?php echo $mes_cierre_nombre; ?>";
        const añoInicioOperaciones = <?php echo $año_inicio_operaciones; ?>; 

        // ============================================
        // FECHA DE CIERRE DE OPERACIONES PARA EXPORTACIONES
        // ============================================
        function obtenerFechaOperacion() {
            if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
                // Dividir la fecha manualmente para evitar problemas de zona horaria
                const partes = fechaInicioOperaciones.split('-');
                const año = parseInt(partes[0]);
                const mes = parseInt(partes[1]) - 1; // Los meses en JS son 0-11
                const dia = parseInt(partes[2]);
                
                const fecha = new Date(año, mes, dia);
                const opciones = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                };
                return fecha.toLocaleDateString('es-ES', opciones);
            }
            
            // Fallback: usar fecha actual
            const fecha = new Date();
            const opciones = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            };
            return fecha.toLocaleDateString('es-ES', opciones);
        }

        function obtenerFechaOperacionCorta() {
            if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
                return fechaInicioOperaciones; // Formato YYYY-MM-DD
            }
            
            // Fallback: usar fecha actual
            const fecha = new Date();
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }

        function obtenerPeriodoCierre() {
            // Usar el año de la fecha de inicio de operaciones
            if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
                const partes = fechaInicioOperaciones.split('-');
                const año = partes[0];
                return `${mesCierreOperacionesNombre} / ${año}`;
            }
            return `${mesCierreOperacionesNombre} / ${new Date().getFullYear()}`;
        }

        // ============================================
        // FUNCIÓN PARA OBTENER DATOS DE LA TABLA
        // ============================================
        function obtenerDatosTabla() {
            const tabla = document.getElementById('usuariosTable');
            if (!tabla) return { datos: [], totalFiltrado: 0 };
            
            const datos = [];
            const filas = Array.from(tabla.querySelectorAll('tbody tr'));
            
            filas.forEach(fila => {
                if (fila.querySelector('td[colspan]')) return;
                
                const celdas = fila.querySelectorAll('td');
                if (celdas.length >= 8) {
                    // Extraer avatar (no incluimos en datos)
                    // Nombre completo con email
                    const nombreCompleto = celdas[1]?.querySelector('.fw-bold')?.textContent?.trim() || '-';
                    const email = celdas[1]?.querySelector('.text-muted')?.textContent?.trim() || '';
                    
                    const filaDatos = [
                        '',                                                         // Avatar (omitimos en exportación)
                        nombreCompleto,                                            // Nombre Completo
                        celdas[2]?.querySelector('.badge')?.textContent?.trim() || '-', // Carnet Identidad
                        celdas[3]?.querySelector('code')?.textContent?.trim() || '-',   // Usuario
                        celdas[4]?.querySelector('.badge')?.textContent?.trim() || '-', // Rol
                        celdas[5]?.querySelector('.badge')?.textContent?.trim() || '-', // Estado
                        celdas[6]?.querySelector('.text-muted')?.textContent?.trim() || '-', // Fecha Registro
                    ];
                    datos.push(filaDatos);
                }
            });
            
            return {
                datos: datos,
                totalFiltrado: datos.length,
                totalGeneral: totalUsuarios
            };
        }

        // ============================================
        // FORMATEAR FECHA PARA ARCHIVOS
        // ============================================
        function formatearFechaParaArchivo() {
            const fecha = new Date();
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            const horas = String(fecha.getHours()).padStart(2, '0');
            const minutos = String(fecha.getMinutes()).padStart(2, '0');
            const segundos = String(fecha.getSeconds()).padStart(2, '0');
            return `${año}${mes}${dia}_${horas}${minutos}${segundos}`;
        }

        // ============================================
        // EXPORTAR A PDF
        // ============================================
        function exportarPDF() {
            try {
                Swal.fire({
                    title: 'Generando PDF...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.close();
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay usuarios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const fechaOperacionCorta = obtenerFechaOperacionCorta();
                const periodoCierre = obtenerPeriodoCierre();
                
                const element = document.createElement('div');
                element.innerHTML = `
                    <style>
                        * { color: #000000 !important; font-family: Arial, sans-serif; }
                        .pagina { padding: 20px; page-break-after: always; }
                        h1 { color: #0078d4 !important; text-align: center; }
                        h2 { text-align: center; }
                        .fecha-operacion { text-align: center; font-weight: bold; color: #0056b3; margin: 10px 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #0078d4 !important; color: white !important; padding: 8px; border: 1px solid #0056b3; }
                        td { padding: 6px; border: 1px solid #ddd; color: #000000 !important; }
                        .estadisticas { display: flex; justify-content: space-around; margin: 20px 0; }
                        .stat { background: #f5f5f5; padding: 10px; border-radius: 5px; text-align: center; }
                    </style>
                    <div class="pagina">
                        <h1>SISFACT PDL VISIONES</h1>
                        <h2>REPORTE DE USUARIOS DEL SISTEMA</h2>
                        <div class="fecha-operacion">
                            Fecha de Inicio de Operaciones: ${fechaOperacion}
                        </div>
                        <div class="fecha-operacion" style="font-size: 12px;">
                            Período de Cierre: ${periodoCierre}
                        </div>
                        
                        <div class="estadisticas">
                            <div class="stat"><strong>Total:</strong> ${totalUsuarios}</div>
                            <div class="stat"><strong>Activos:</strong> ${usuariosActivos}</div>
                            <div class="stat"><strong>Inactivos:</strong> ${usuariosInactivos}</div>
                            <div class="stat"><strong>Tasa Actividad:</strong> ${totalUsuarios > 0 ? ((usuariosActivos / totalUsuarios) * 100).toFixed(1) : 0}%</div>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Nombre Completo</th>
                                    <th>Carnet Identidad</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tablaDatos.datos.map((fila, index) => `
                                    <tr>
                                        <td style="text-align: center;">${index + 1}</td>
                                        <td>${fila[1]}</td>
                                        <td style="text-align: center;">${fila[2]}</td>
                                        <td style="text-align: center;">${fila[3]}</td>
                                        <td style="text-align: center;">${fila[4]}</td>
                                        <td style="text-align: center; color: ${fila[5] === 'Activo' ? '#28a745' : '#dc3545'}; font-weight: bold;">${fila[5]}</td>
                                        <td style="text-align: center;">${fila[6]}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        <p style="text-align: center; margin-top: 30px; color: #666;">
                            Generado por: ${usuarioNombre} - ${fechaOperacion}
                        </p>
                    </div>
                `;

                html2pdf().set({
                    margin: 0.5,
                    filename: `Usuarios_${formatearFechaParaArchivo()}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
                }).from(element).save().then(() => {
                    Swal.close();
                    Swal.fire({ icon: 'success', title: 'PDF Descargado', timer: 1500, showConfirmButton: false });
                });
            } catch (error) {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A EXCEL
        // ============================================
        function exportarExcel() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay usuarios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();

                const wb = XLSX.utils.book_new();
                
                const ws_data = [
                    ['REPORTE DE USUARIOS - SISFACT PDL VISIONES'],
                    [`Fecha de Inicio de Operaciones: ${fechaOperacion}`],
                    [`Período de Cierre: ${periodoCierre}`],
                    [`Fecha Generación: ${new Date().toLocaleString('es-ES')}`],
                    [`Usuario: ${usuarioNombre}`],
                    [],
                    ['ESTADÍSTICAS'],
                    ['Total Usuarios', totalUsuarios],
                    ['Usuarios Activos', usuariosActivos],
                    ['Usuarios Inactivos', usuariosInactivos],
                    ['Tasa de Actividad', `${totalUsuarios > 0 ? ((usuariosActivos / totalUsuarios) * 100).toFixed(1) : 0}%`],
                    [],
                    ['No.', 'Nombre Completo', 'Carnet Identidad', 'Usuario', 'Rol', 'Estado', 'Fecha Registro'],
                    ...tablaDatos.datos.map((fila, index) => [
                        index + 1,
                        fila[1],
                        fila[2],
                        fila[3],
                        fila[4],
                        fila[5],
                        fila[6]
                    ])
                ];
                
                const ws = XLSX.utils.aoa_to_sheet(ws_data);
                ws['!cols'] = [
                    { wch: 6 }, { wch: 35 }, { wch: 18 }, { wch: 15 }, 
                    { wch: 25 }, { wch: 12 }, { wch: 15 }
                ];
                
                XLSX.utils.book_append_sheet(wb, ws, 'Usuarios');
                XLSX.writeFile(wb, `Usuarios_${formatearFechaParaArchivo()}.xlsx`);
                
                Swal.fire({ icon: 'success', title: 'Excel Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A WORD
        // ============================================
        function exportarWord() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay usuarios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();

                let html = `
                    <html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns:m="http://schemas.microsoft.com/office/2004/12/omml" xmlns="http://www.w3.org/TR/REC-html40">
                    <head>
                        <meta charset="UTF-8">
                        <title>Reporte de Usuarios</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 1.5cm; }
                            h1 { color: #0078d4; border-bottom: 2px solid #0078d4; padding-bottom: 10px; }
                            .fecha-operacion { text-align: center; font-weight: bold; color: #0056b3; margin: 15px 0; }
                            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                            th { background: #0078d4; color: white; padding: 8px; border: 1px solid #0056b3; }
                            td { padding: 6px; border: 1px solid #999; }
                            .stats { display: flex; justify-content: space-between; margin: 20px 0; }
                            .stat-box { background: #f5f5f5; padding: 10px; border-radius: 5px; width: 23%; text-align: center; }
                            .activo { color: #28a745; font-weight: bold; }
                            .inactivo { color: #dc3545; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        <h1>REPORTE DE USUARIOS DEL SISTEMA</h1>
                        <div class="fecha-operacion">
                            Fecha de Inicio de Operaciones: ${fechaOperacion}
                        </div>
                        <div style="text-align: center; color: #666; margin-bottom: 20px;">
                            Período de Cierre: ${periodoCierre}
                        </div>
                        <p><strong>Usuario:</strong> ${usuarioNombre}</p>
                        
                        <div class="stats">
                            <div class="stat-box"><strong>Total:</strong> ${totalUsuarios}</div>
                            <div class="stat-box"><strong>Activos:</strong> ${usuariosActivos}</div>
                            <div class="stat-box"><strong>Inactivos:</strong> ${usuariosInactivos}</div>
                            <div class="stat-box"><strong>Tasa:</strong> ${totalUsuarios > 0 ? ((usuariosActivos / totalUsuarios) * 100).toFixed(1) : 0}%</div>
                        </div>
                        
                        <table>
                            <tr>
                                <th>No.</th>
                                <th>Nombre Completo</th>
                                <th>Carnet Identidad</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                            </tr>
                            ${tablaDatos.datos.map((fila, index) => `
                                <tr>
                                    <td align="center">${index + 1}</td>
                                    <td>${fila[1]}</td>
                                    <td align="center">${fila[2]}</td>
                                    <td align="center">${fila[3]}</td>
                                    <td align="center">${fila[4]}</td>
                                    <td align="center" class="${fila[5].toLowerCase()}">${fila[5]}</td>
                                    <td align="center">${fila[6]}</td>
                                </tr>
                            `).join('')}
                        </table>
                        
                        <p style="margin-top: 30px; text-align: center; color: #666;">
                            Generado por SISFACT PDL Visiones - ${fechaOperacion}
                        </p>
                    </body>
                    </html>
                `;

                const blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Usuarios_${formatearFechaParaArchivo()}.doc`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'Word Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A CSV
        // ============================================
        function exportarCSV() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay usuarios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();

                let csv = '';
                csv += 'REPORTE DE USUARIOS - SISFACT PDL VISIONES\n';
                csv += `Fecha de Inicio de Operaciones,${fechaOperacion}\n`;
                csv += `Período de Cierre,${periodoCierre}\n`;
                csv += `Usuario,${usuarioNombre}\n`;
                csv += `Total Usuarios,${totalUsuarios}\n`;
                csv += `Usuarios Activos,${usuariosActivos}\n`;
                csv += `Usuarios Inactivos,${usuariosInactivos}\n`;
                csv += `Tasa de Actividad,${totalUsuarios > 0 ? ((usuariosActivos / totalUsuarios) * 100).toFixed(1) : 0}%\n`;
                csv += '\n';
                csv += 'No.,Nombre Completo,Carnet Identidad,Usuario,Rol,Estado,Fecha Registro\n';
                
                tablaDatos.datos.forEach((fila, index) => {
                    const filaEscapada = [
                        index + 1,
                        fila[1],
                        fila[2],
                        fila[3],
                        fila[4],
                        fila[5],
                        fila[6]
                    ].map(celda => {
                        if (typeof celda === 'string' && (celda.includes(',') || celda.includes('"'))) {
                            return `"${celda.replace(/"/g, '""')}"`;
                        }
                        return celda;
                    });
                    csv += filaEscapada.join(',') + '\n';
                });

                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Usuarios_${formatearFechaParaArchivo()}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'CSV Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A TXT
        // ============================================
        function exportarTXT() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay usuarios para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();
                
                let contenido = '';
                contenido += '='.repeat(120) + '\n';
                contenido += 'REPORTE DE USUARIOS DEL SISTEMA - SISFACT PDL VISIONES\n';
                contenido += '='.repeat(120) + '\n\n';
                contenido += `Fecha de Inicio de Operaciones: ${fechaOperacion}\n`;
                contenido += `Período de Cierre: ${periodoCierre}\n`;
                contenido += `Usuario: ${usuarioNombre}\n\n`;
                contenido += 'ESTADÍSTICAS:\n';
                contenido += `  Total Usuarios: ${totalUsuarios}\n`;
                contenido += `  Usuarios Activos: ${usuariosActivos}\n`;
                contenido += `  Usuarios Inactivos: ${usuariosInactivos}\n`;
                contenido += `  Tasa de Actividad: ${totalUsuarios > 0 ? ((usuariosActivos / totalUsuarios) * 100).toFixed(1) : 0}%\n\n`;
                contenido += '-'.repeat(120) + '\n\n';
                
                // Encabezados
                contenido += 'No.'.padEnd(6) + 
                            'Nombre Completo'.padEnd(40) + 
                            'Carnet Identidad'.padEnd(20) + 
                            'Usuario'.padEnd(18) + 
                            'Rol'.padEnd(30) + 
                            'Estado'.padEnd(12) + 
                            'Fecha Registro\n';
                contenido += '-'.repeat(120) + '\n';
                
                // Datos
                tablaDatos.datos.forEach((fila, index) => {
                    const nombreCompleto = fila[1].substring(0, 38).padEnd(40);
                    const carnet = fila[2].padEnd(20);
                    const usuario = fila[3].padEnd(18);
                    const rol = fila[4].substring(0, 28).padEnd(30);
                    const estado = fila[5].padEnd(12);
                    const fecha = fila[6];
                    
                    contenido += (index + 1).toString().padEnd(6) + 
                                nombreCompleto + 
                                carnet + 
                                usuario + 
                                rol + 
                                estado + 
                                fecha + '\n';
                });
                
                contenido += '\n' + '='.repeat(120) + '\n';
                contenido += 'FIN DEL REPORTE\n';
                contenido += '='.repeat(120) + '\n';

                const blob = new Blob(['\ufeff' + contenido], { type: 'text/plain;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Usuarios_${formatearFechaParaArchivo()}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'TXT Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // IMPRIMIR TABLA
        // ============================================
        function imprimirTabla() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay usuarios para imprimir' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
                const periodoCierre = obtenerPeriodoCierre();

                const printWindow = window.open('', '_blank', 'width=1200,height=850');
                
                let html = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Reporte de Usuarios</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h1 { color: #0078d4; border-bottom: 2px solid #0078d4; padding-bottom: 10px; }
                            .fecha-operacion { text-align: center; font-weight: bold; color: #0056b3; margin: 15px 0; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th { background: #0078d4; color: white; padding: 8px; border: 1px solid #0056b3; }
                            td { padding: 6px; border: 1px solid #ddd; }
                            .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                            .stat-box { background: #f0f0f0; padding: 10px; border-radius: 5px; }
                            .activo { color: #28a745; font-weight: bold; }
                            .inactivo { color: #dc3545; font-weight: bold; }
                            @media print { @page { margin: 1cm; } }
                        </style>
                    </head>
                    <body>
                        <h1>REPORTE DE USUARIOS DEL SISTEMA</h1>
                        <div class="fecha-operacion">
                            Fecha de Inicio de Operaciones: ${fechaOperacion}
                        </div>
                        <div style="text-align: center; color: #666; margin-bottom: 20px;">
                            Período de Cierre: ${periodoCierre}
                        </div>
                        <p><strong>Usuario:</strong> ${usuarioNombre}</p>
                        
                        <div class="stats">
                            <div class="stat-box"><strong>Total:</strong> ${totalUsuarios}</div>
                            <div class="stat-box"><strong>Activos:</strong> ${usuariosActivos}</div>
                            <div class="stat-box"><strong>Inactivos:</strong> ${usuariosInactivos}</div>
                            <div class="stat-box"><strong>Tasa:</strong> ${totalUsuarios > 0 ? ((usuariosActivos / totalUsuarios) * 100).toFixed(1) : 0}%</div>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Nombre Completo</th>
                                    <th>Carnet Identidad</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tablaDatos.datos.map((fila, index) => `
                                    <tr>
                                        <td align="center">${index + 1}</td>
                                        <td>${fila[1]}</td>
                                        <td align="center">${fila[2]}</td>
                                        <td align="center">${fila[3]}</td>
                                        <td align="center">${fila[4]}</td>
                                        <td align="center" class="${fila[5].toLowerCase()}">${fila[5]}</td>
                                        <td align="center">${fila[6]}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        
                        <p style="text-align: center; margin-top: 30px; color: #666;">
                            SISFACT PDL Visiones - ${fechaOperacion}
                        </p>
                    </body>
                    </html>
                `;

                printWindow.document.write(html);
                printWindow.document.close();
                
                setTimeout(() => {
                    printWindow.focus();
                    printWindow.print();
                    setTimeout(() => printWindow.close(), 1000);
                }, 500);

            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR USUARIOS - TODOS LOS FORMATOS CON ICONOS
        // ============================================
        function exportarUsuarios() {
            Swal.fire({
                title: '📊 Exportar Usuarios',
                html: `
                    <div style="text-align: left; margin-top: 15px;">
                        <p style="margin-bottom: 15px; color: var(--win-text-primary);">
                            <i class="fas fa-download me-2"></i>Seleccione el formato de exportación:
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <button id="btnExcel" class="btn btn-success" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-excel me-2"></i> Microsoft Excel
                            </button>
                            <button id="btnPDF" class="btn btn-danger" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-pdf me-2"></i> Adobe PDF
                            </button>
                            <button id="btnWord" class="btn btn-primary" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-word me-2"></i> Microsoft Word
                            </button>
                            <button id="btnCSV" class="btn btn-info" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-csv me-2"></i> Archivo CSV
                            </button>
                            <button id="btnTXT" class="btn btn-secondary" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-file-alt me-2"></i> Archivo TXT
                            </button>
                            <button id="btnPrint" class="btn btn-warning" style="width: 100%; padding: 10px; border-radius: 6px;">
                                <i class="fas fa-print me-2"></i> Imprimir
                            </button>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-download me-2"></i>Exportar Todo',
                confirmButtonColor: '#0078d4',
                showCloseButton: true,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                },
                didOpen: () => {
                    // Asignar eventos a los botones personalizados
                    document.getElementById('btnExcel').addEventListener('click', () => {
                        Swal.close();
                        exportarExcel();
                    });
                    document.getElementById('btnPDF').addEventListener('click', () => {
                        Swal.close();
                        exportarPDF();
                    });
                    document.getElementById('btnWord').addEventListener('click', () => {
                        Swal.close();
                        exportarWord();
                    });
                    document.getElementById('btnCSV').addEventListener('click', () => {
                        Swal.close();
                        exportarCSV();
                    });
                    document.getElementById('btnTXT').addEventListener('click', () => {
                        Swal.close();
                        exportarTXT();
                    });
                    document.getElementById('btnPrint').addEventListener('click', () => {
                        Swal.close();
                        imprimirTabla();
                    });
                },
                preConfirm: () => {
                    // Exportar todos los formatos (opcional)
                    exportarExcel();
                    exportarPDF();
                    exportarWord();
                    exportarCSV();
                    exportarTXT();
                    setTimeout(() => imprimirTabla(), 1000);
                }
            });
        }

        // ============================================
        // FUNCIONES ORIGINALES (SE MANTIENEN IGUAL)
        // ============================================
        
        // CONFIGURACIÓN SWEETALERT DARK (INTEGRADO AL TEMA) - MEJORADO
        const Toast = Swal.mixin({
            toast: false,
            position: 'center',
            showConfirmButton: true,
            confirmButtonText: '<i class="fa fa-check me-2"></i>Aceptar',
            confirmButtonColor: 'var(--win-accent)',
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            customClass: {
                popup: 'border border-secondary rounded shadow-lg',
                title: 'text-primary',
                htmlContainer: 'text-primary',
                confirmButton: 'btn-win',
                cancelButton: 'btn-win-cancel',
                denyButton: 'btn-win-deny'
            }
        });

        // 1. DISPARAR ALERTAS INMEDIATAMENTE AL CARGAR (SI VIENEN DE PHP)
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($mostrar_alerta && $alerta_exito): ?>
                Toast.fire({
                    icon: 'success',
                    title: '<i class="fas fa-check-circle me-2"></i>¡Operación Exitosa!',
                    html: '<?php echo addslashes($alerta_exito); ?>',
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            <?php endif; ?>

            <?php if ($mostrar_alerta && $alerta_error): ?>
                Toast.fire({
                    icon: 'error',
                    title: '<i class="fas fa-exclamation-triangle me-2"></i>Error',
                    html: '<?php echo addslashes($alerta_error); ?>'
                });
            <?php endif; ?>
        });

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
        
        function toggleEstadoUsuario(id, activoActual, nombreUsuario) {
            console.log('=== TOGGLE ESTADO ===');
            console.log('ID:', id);
            console.log('Estado actual (activo):', activoActual);
            console.log('Nombre:', nombreUsuario);
            
            const usuarioActualId = <?php echo isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'null'; ?>;
            
            if (id == usuarioActualId) {
                Swal.fire({
                    icon: 'warning',
                    title: '<i class="fas fa-user-shield me-2"></i>Acción no permitida',
                    html: `<p>No puede cambiar su propio estado.</p>
                           <p class="text-muted small">Para modificar su estado, contacte a otro administrador.</p>`,
                    confirmButtonText: 'Entendido',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
                return;
            }
            
            const nuevoEstado = activoActual ? 0 : 1;
            const accionTexto = activoActual ? 'desactivar' : 'activar';
            const mensajeEstado = activoActual 
                ? 'El usuario perderá acceso al sistema' 
                : 'El usuario recuperará acceso al sistema';
            const icono = activoActual ? 'fa-user-slash' : 'fa-user-check';
            const color = activoActual ? '#dc3545' : '#28a745';
            
            Swal.fire({
                title: `<i class="fas ${icono} me-2"></i>${activoActual ? 'Desactivar' : 'Activar'} usuario`,
                html: `<div class="text-start">
                          <p>¿Está seguro de <strong>${accionTexto}</strong> al usuario?</p>
                          <div class="alert ${activoActual ? 'alert-warning' : 'alert-success'} p-3 mt-2">
                              <i class="fas ${icono} me-2"></i>
                              <strong>${nombreUsuario}</strong>
                              <p class="mb-0 mt-1 small">${mensajeEstado}</p>
                          </div>
                       </div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: color,
                cancelButtonColor: '#6c757d',
                confirmButtonText: activoActual ? '<i class="fas fa-user-slash me-2"></i>Desactivar' : '<i class="fas fa-user-check me-2"></i>Activar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                reverseButtons: true,
                showLoaderOnConfirm: true,
                backdrop: 'rgba(0,0,0,0.8)',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                },
                preConfirm: async () => {
                    try {
                        const formData = new URLSearchParams();
                        formData.append('id', id);
                        formData.append('estado', nuevoEstado);
                        
                        const response = await fetch('toggle_estado_usuario.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: formData
                        });
                        
                        const text = await response.text();
                        
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Respuesta inválida del servidor');
                        }
                        
                    } catch (error) {
                        Swal.showValidationMessage(
                            `Error: ${error.message}`
                        );
                        return null;
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    if (result.value.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<i class="fas fa-check-circle me-2"></i>¡Éxito!',
                            text: result.value.message,
                            timer: 1500,
                            showConfirmButton: false,
                            backdrop: 'rgba(0,0,0,0.8)',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        }).then(() => {
                            actualizarEstadoUsuarioUI(id, nuevoEstado, nombreUsuario);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                            text: result.value.message || 'Error desconocido',
                            backdrop: 'rgba(0,0,0,0.8)',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        });
                    }
                }
            });
        }

        // Función para actualizar la interfaz
        function actualizarEstadoUsuarioUI(id, nuevoEstado, nombreUsuario) {
            console.log('Actualizando UI para usuario ID:', id, 'Nuevo estado:', nuevoEstado);
            
            // Encontrar la fila
            const fila = document.querySelector(`tr[data-id="${id}"]`);
            if (!fila) {
                console.log('Fila no encontrada, recargando página...');
                setTimeout(() => location.reload(), 500);
                return;
            }
            
            // 1. Actualizar badge de estado
            const badgeEstado = fila.querySelector('td:nth-child(6) .badge');
            if (badgeEstado) {
                badgeEstado.textContent = nuevoEstado ? 'Activo' : 'Inactivo';
                badgeEstado.classList.remove(nuevoEstado ? 'bg-danger' : 'bg-success');
                badgeEstado.classList.add(nuevoEstado ? 'bg-success' : 'bg-danger');
                console.log('Badge actualizado');
            }
            
            // 2. Actualizar botón de toggle
            const botonToggle = fila.querySelector(`button[onclick*="toggleEstadoUsuario(${id}"]`);
            if (botonToggle) {
                // Actualizar icono
                const icono = botonToggle.querySelector('i');
                if (icono) {
                    icono.className = nuevoEstado ? 'fas fa-user-slash' : 'fas fa-user-check';
                }
                
                // Actualizar clases del botón
                botonToggle.classList.remove(nuevoEstado ? 'btn-outline-success' : 'btn-outline-danger');
                botonToggle.classList.add(nuevoEstado ? 'btn-outline-danger' : 'btn-outline-success');
                
                // Actualizar título
                botonToggle.title = nuevoEstado ? 'Desactivar' : 'Activar';
                
                // Actualizar onclick con nuevo estado invertido
                const nuevoEstadoBoolean = nuevoEstado === 1;
                botonToggle.setAttribute('onclick', 
                    `toggleEstadoUsuario(${id}, ${nuevoEstadoBoolean}, '${nombreUsuario.replace(/'/g, "\\'")}')`);
                
                // Actualizar tooltip si existe
                if (botonToggle._tooltip) {
                    botonToggle._tooltip.dispose();
                    new bootstrap.Tooltip(botonToggle);
                }
                
                console.log('Botón actualizado');
            }
            
            // 3. Actualizar atributo data-status de la fila
            fila.setAttribute('data-status', nuevoEstado ? 'activo' : 'inactivo');
            
            // 4. Actualizar contadores
            recalcularEstadisticasGlobales();
            
            // 5. Animación
            fila.classList.add('user-updated');
            setTimeout(() => {
                fila.classList.remove('user-updated');
            }, 1000);
            
            console.log('UI actualizada exitosamente');
        }

        function eliminarUsuario(id, nombreUsuario, estaActivo = true) {
            console.log('=== INICIANDO ELIMINACIÓN ===');
            console.log('ID:', id, 'Nombre:', nombreUsuario);
            
            // Verificar si es el usuario actual
            const usuarioActualId = <?php echo isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'null'; ?>;
            if (id == usuarioActualId) {
                Swal.fire({
                    icon: 'warning',
                    title: '<i class="fas fa-exclamation-triangle me-2"></i>Acción no permitida',
                    html: `<p>No puede eliminar su propia cuenta.</p>`,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    backdrop: 'rgba(0,0,0,0.8)',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
                return;
            }
            
            Swal.fire({
                title: '<i class="fas fa-trash-alt me-2"></i>¿Eliminar usuario?',
                html: `<div class="text-start">
                          <p>¿Está seguro de eliminar al usuario:</p>
                          <div class="alert alert-danger p-3 mt-2">
                              <i class="fas fa-user me-2"></i>
                              <strong>${nombreUsuario}</strong>
                              <p class="mb-0 mt-1 small text-danger">Esta acción no se puede deshacer.</p>
                          </div>
                       </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Eliminar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                showDenyButton: true,
                denyButtonText: '<i class="fas fa-user-slash me-2"></i>Mejor desactivar',
                denyButtonColor: '#ffc107',
                reverseButtons: true,
                backdrop: 'rgba(0,0,0,0.8)',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                },
                preConfirm: async () => {
                    try {
                        const formData = new URLSearchParams();
                        formData.append('id', id);
                        
                        const response = await fetch('eliminar_usuario.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: formData
                        });
                        
                        const text = await response.text();
                        
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Respuesta inválida del servidor');
                        }
                        
                    } catch (error) {
                        Swal.showValidationMessage(
                            `Error: ${error.message}`
                        );
                        return null;
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    if (result.value.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<i class="fas fa-check-circle me-2"></i>¡Eliminado!',
                            text: result.value.message,
                            timer: 1500,
                            showConfirmButton: false,
                            backdrop: 'rgba(0,0,0,0.8)',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        }).then(() => {
                            const fila = document.querySelector(`tr[data-id="${id}"]`);
                            if (fila) {
                                fila.style.transition = 'all 0.5s ease';
                                fila.style.opacity = '0';
                                fila.style.transform = 'translateX(-100%)';
                                
                                setTimeout(() => {
                                    fila.remove();
                                    recalcularEstadisticasGlobales();
                                }, 500);
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        let mensajeLimpio = result.value.message
                            .replace('No se puede eliminar el usuario porque tiene las siguientes dependencias:\n', '')
                            .replace('Solución: Desactive al usuario en lugar de eliminarlo.', '')
                            .trim();
                        
                        mensajeLimpio = mensajeLimpio.replace(/\u2022/g, '•');
                        
                        Swal.fire({
                            icon: 'error',
                            title: '<i class="fas fa-ban me-2"></i>No se puede eliminar',
                            html: `<div style="text-align: left; max-height: 400px; overflow-y: auto;">
                                      <p class="mb-3"><strong>${nombreUsuario}</strong> no puede ser eliminado porque tiene datos asociados.</p>
                                      
                                      <div class="alert alert-warning p-3 mt-2">
                                          <div class="d-flex align-items-start">
                                              <i class="fas fa-database me-2 mt-1"></i>
                                              <div>
                                                  <strong>Dependencias detectadas:</strong>
                                                  <div class="mt-2 small" style="white-space: pre-line;">
                                                      ${mensajeLimpio}
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                      
                                      <div class="alert alert-info p-3 mt-3">
                                          <div class="d-flex align-items-start">
                                              <i class="fas fa-lightbulb me-2 mt-1"></i>
                                              <div>
                                                  <strong>Solución recomendada:</strong>
                                                  <p class="mb-0 mt-1">Desactive al usuario en lugar de eliminarlo.</p>
                                              </div>
                                          </div>
                                      </div>
                                  </div>`,
                            width: '600px',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                            confirmButtonColor: '#3085d6',
                            showCancelButton: true,
                            cancelButtonText: '<i class="fas fa-user-slash me-2"></i>Desactivar',
                            cancelButtonColor: '#ffc107',
                            backdrop: 'rgba(0,0,0,0.8)',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        }).then((result2) => {
                            if (result2.dismiss === Swal.DismissReason.cancel) {
                                toggleEstadoUsuario(id, estaActivo, nombreUsuario);
                            }
                        });
                    }
                } else if (result.isDenied) {
                    toggleEstadoUsuario(id, estaActivo, nombreUsuario);
                }
            });
        }
        
        // Función para imprimir reporte
        function imprimirReporte() {
            imprimirTabla();
        }

        // Actualizar lista de usuarios
        function actualizarListaUsuarios() {
            const btn = document.querySelector('button[onclick="actualizarListaUsuarios()"]');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
                btn.disabled = true;
                
                Swal.fire({
                    title: '<i class="fas fa-sync-alt fa-spin me-2"></i>Actualizando...',
                    text: 'Por favor espere mientras se actualiza la lista de usuarios',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    backdrop: 'rgba(0,0,0,0.8)'
                });
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
        
        // Filtrar usuarios mejorado
        function filtrarUsuarios() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterRole = document.getElementById('filterRole').value;
            const filterStatus = document.getElementById('filterStatus').value;
            const rows = document.querySelectorAll('#usuariosTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const role = row.getAttribute('data-role');
                const status = row.getAttribute('data-status');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesRole = filterRole === '' || role === filterRole;
                const matchesStatus = filterStatus === '' || status === filterStatus;
                
                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Actualizar contadores
            recalcularEstadisticasGlobales();
            
            // Mostrar mensaje si no hay resultados
            if (visibleCount === 0 && rows.length > 0) {
                const tbody = document.querySelector('#usuariosTable tbody');
                if (!tbody.querySelector('.no-results')) {
                    const noResults = document.createElement('tr');
                    noResults.className = 'no-results';
                    noResults.innerHTML = `
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-search text-muted fa-2x mb-2 d-block"></i>
                            <p class="mb-0">No se encontraron usuarios</p>
                            <small class="text-muted">Intenta con otros filtros</small>
                        </td>
                    `;
                    tbody.appendChild(noResults);
                }
            } else {
                const noResults = document.querySelector('.no-results');
                if (noResults) {
                    noResults.remove();
                }
            }
        }

        // Toggle Quick Actions
        function toggleQuickActions() {
            const expandedActions = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (!expandedActions) return;
            
            const isExpanded = expandedActions.classList.contains('show');
            
            if (isExpanded) {
                expandedActions.classList.remove('show');
                mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                mainButton.title = 'Mostrar acciones rápidas';
                mainButton.style.transform = 'rotate(0deg)';
            } else {
                expandedActions.classList.add('show');
                mainButton.innerHTML = '<i class="fas fa-times"></i>';
                mainButton.title = 'Ocultar acciones rápidas';
                mainButton.style.transform = 'rotate(45deg)';
            }
        }
        
        // Animar barras de progreso
        function animarBarrasProgreso() {
            const bars = document.querySelectorAll('.progress-bar-fill');
            bars.forEach(bar => {
                // Reiniciar animación
                bar.style.width = '0%';
                
                // Usar requestAnimationFrame para animación suave
                setTimeout(() => {
                    const targetWidth = bar.style.getPropertyValue('--target-width') || '0%';
                    bar.style.width = targetWidth;
                }, 100);
            });
        }

        // Función Maestra para actualizar TODAS las estadísticas en tiempo real
        function recalcularEstadisticasGlobales() {
            console.log("Recalculando estadísticas globales...");
            
            // 1. Obtener todas las filas visibles y no visibles de la tabla
            const filas = document.querySelectorAll('#usuariosTable tbody tr');
            
            let total = 0;
            let activos = 0;
            let inactivos = 0;
            let admins = 0;
            let conteoRoles = {};

            filas.forEach(fila => {
                if (fila.querySelector('td[colspan]')) return;
                
                total++;
                
                // Contar estado
                const estado = fila.getAttribute('data-status');
                if (estado === 'activo') {
                    activos++;
                } else {
                    inactivos++;
                }

                // Contar roles
                const rol = fila.getAttribute('data-role') || 'Sin rol';
                if (rol === 'Administrador del Sistema') {
                    admins++;
                }

                // Acumular para las barras de progreso
                if (!conteoRoles[rol]) {
                    conteoRoles[rol] = 0;
                }
                conteoRoles[rol]++;
            });

            // 2. Actualizar Tarjetas Superiores
            const elTotal = document.getElementById('statTotal');
            const elActivos = document.getElementById('statActivos');
            const elInactivos = document.getElementById('statInactivos');
            const elAdmins = document.getElementById('statAdmins');
            const elTasaVal = document.getElementById('statTasaVal');
            const elTasaBar = document.getElementById('statTasaBar');
            const elTasaText = document.getElementById('statTasaText');

            if (elTotal) elTotal.textContent = total;
            if (elActivos) elActivos.textContent = activos;
            if (elInactivos) elInactivos.textContent = inactivos;
            if (elAdmins) elAdmins.textContent = admins;

            // 3. Actualizar Tasa de Actividad
            let tasa = total > 0 ? ((activos / total) * 100).toFixed(1) : '0.0';
            if (elTasaVal) elTasaVal.textContent = tasa + '%';
            if (elTasaBar) elTasaBar.style.width = tasa + '%';
            if (elTasaText) elTasaText.textContent = `${activos} de ${total} usuarios activos`;
            
            // 4. Actualizar Badges de la Cabecera de la Tabla
            const badgeHeaderInfo = document.querySelector('.card-header .badge.bg-info');
            const badgeHeaderSuccess = document.querySelector('.card-header .badge.bg-success');
            
            if (badgeHeaderInfo) badgeHeaderInfo.textContent = `${total} usuarios`;
            if (badgeHeaderSuccess) badgeHeaderSuccess.textContent = `Activos: ${activos}`;

            // 5. Actualizar Barras de Progreso (Distribución de Roles)
            document.querySelectorAll('.progress-bar-wrapper').forEach(wrapper => {
                const labelEl = wrapper.querySelector('.progress-bar-label');
                if (labelEl) {
                    const nombreRol = labelEl.textContent.trim().replace(/\s*\(sin usuarios\)\s*$/, '');
                    const cantidad = conteoRoles[nombreRol] || 0;
                    const porcentaje = total > 0 ? ((cantidad / total) * 100).toFixed(1) : 0;
                    
                    const countEl = wrapper.querySelector('.progress-bar-count');
                    const percentEl = wrapper.querySelector('.progress-bar-percentage');
                    const fillEl = wrapper.querySelector('.progress-bar-fill');
                    
                    if (countEl) countEl.textContent = `${cantidad} usuario${cantidad != 1 ? 's' : ''}`;
                    if (percentEl) percentEl.textContent = `${porcentaje}%`;
                    if (fillEl) {
                        fillEl.style.width = `${porcentaje}%`;
                        fillEl.style.setProperty('--target-width', `${porcentaje}%`);
                    }
                }
            });
            
            console.log("Estadísticas recalculadas:", { total, activos, inactivos, admins, tasa });
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips
            if (typeof bootstrap !== 'undefined') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        container: 'body',
                        trigger: 'hover focus',
                        placement: 'auto',
                        boundary: 'clippingParents',
                        html: true,
                        fallbackPlacements: ['bottom', 'right', 'left'],
                        delay: { "show": 100, "hide": 100 }
                    });
                });
            }
            
            // Configurar búsqueda y filtros
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.addEventListener('input', filtrarUsuarios);
            
            const filterRole = document.getElementById('filterRole');
            if (filterRole) filterRole.addEventListener('change', filtrarUsuarios);
            
            const filterStatus = document.getElementById('filterStatus');
            if (filterStatus) filterStatus.addEventListener('change', filtrarUsuarios);
            
            // Cerrar panel de temas
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            // Cerrar panel de temas con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && themePanelOpen) {
                    cerrarPanelTemas();
                }
            });
            
            // Manejar cambios de tamaño de ventana
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('open');
                }
            });
            
            // Cerrar Quick Actions al hacer clic fuera
            document.addEventListener('click', function(event) {
                const quickActions = document.querySelector('.win-quick-actions');
                const expandedActions = document.getElementById('quickActionsExpanded');
                const mainButton = document.getElementById('mainQuickAction');
                
                if (!quickActions || !expandedActions) return;
                
                const isClickInside = quickActions.contains(event.target);
                const isExpanded = expandedActions.classList.contains('show');
                
                if (!isClickInside && isExpanded) {
                    expandedActions.classList.remove('show');
                    if (mainButton) {
                        mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                        mainButton.style.transform = 'rotate(0deg)';
                    }
                }
            });
            
            // Animar barras de progreso después de cargar la página
            setTimeout(() => {
                animarBarrasProgreso();
            }, 500);
            
            // Recalcular estadísticas iniciales
            setTimeout(() => {
                recalcularEstadisticasGlobales();
            }, 100);
        });
    </script>
    
    <?php
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
    ?>
</body>
</html>