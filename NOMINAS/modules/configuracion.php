<?php
// modules/configuracion.php - Configuraciones del Sistema
require_once '../config.php';
require_once '../includes/funciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';

$usuario_actual_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

// Configuración empresa
$config_empresa = ['nombre_empresa' => 'PDL TransNuBeT', 'jefe_proyecto' => 'Dainelys León Reyes', 'especialista_gestion' => 'Mailén Pérez García'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Ruta del logo
$ruta_logo = '../../images/logocorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

// Procesar guardado de configuración
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_config_general'])) {
        $params = [
            'horas_mensuales', 'dias_mensuales', 'horas_jornada_diaria',
            'tasa_contribucion_especial', 'nombre_empresa', 'direccion_empresa',
            'reeup_empresa', 'nit_empresa', 'jefe_proyecto', 'especialista_gestion', 
            'salario_minimo', 'intendente', 'recargo_nocturno'
        ];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
                    $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                    $stmt->execute([$_POST[$param], $param]);
                }
            }
            $mensaje = "Configuración general guardada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_rangos'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM configuracion_rangos_impuesto WHERE fecha_vigencia = ?");
            $stmt->execute([$_POST['fecha_vigencia']]);
            
            $rangos = $_POST['rangos'];
            foreach ($rangos as $rango) {
                if ($rango['desde'] !== '' && $rango['tasa'] !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO configuracion_rangos_impuesto (desde, hasta, tasa, monto_fijo, fecha_vigencia, descripcion)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $rango['desde'],
                        $rango['hasta'] ?: null,
                        $rango['tasa'] / 100,
                        $rango['monto_fijo'] ?? 0,
                        $_POST['fecha_vigencia'],
                        $rango['descripcion'] ?? ''
                    ]);
                }
            }
            $mensaje = "Rangos de impuesto actualizados correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar rangos: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['eliminar_tasa'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM configuracion_tasas WHERE id = ?");
            $stmt->execute([$_POST['tasa_id']]);
            $mensaje = "Tasa eliminada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar tasa: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['agregar_tasa'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO configuracion_tasas (nombre_tasa, valor, fecha_vigencia, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['nombre_tasa'],
                $_POST['valor_tasa'],
                $_POST['fecha_vigencia_tasa'],
                $_POST['descripcion_tasa']
            ]);
            $mensaje = "Tasa agregada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al agregar tasa: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Obtener configuración actual
$config = [];
$stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general");
while ($row = $stmt->fetch()) {
    $config[$row['parametro']] = $row['valor'];
}

// Obtener rangos de impuesto vigentes
$rangos_impuesto = $pdo->query("
    SELECT * FROM configuracion_rangos_impuesto 
    WHERE fecha_vigencia = (SELECT MAX(fecha_vigencia) FROM configuracion_rangos_impuesto)
    ORDER BY desde
")->fetchAll();

$fecha_vigencia_actual = !empty($rangos_impuesto) ? $rangos_impuesto[0]['fecha_vigencia'] : date('Y-m-d');

// Obtener tasas
$tasas = $pdo->query("SELECT * FROM configuracion_tasas ORDER BY fecha_vigencia DESC")->fetchAll();

// Obtener usuarios para la tabla
$stmt_usuarios = $pdo->query("
    SELECT u.*, r.descripcion as rol_nombre 
    FROM clasif_usuarios u 
    LEFT JOIN clasif_rol r ON u.rol_id = r.id 
    ORDER BY u.nombre, u.apellidos
");
$usuarios_lista = $stmt_usuarios->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Configuración</title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/cropper.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: #0c0c0c; overflow-x: hidden; color: #ffffff; }

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

        .glass-card {
            background: rgba(28, 28, 35, 0.6); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { transform: translateY(-2px); background: rgba(35, 35, 45, 0.7); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }

        .main-container { margin-left: 260px; transition: all 0.3s ease; min-height: 100vh; padding: 20px; }
        .main-container.expanded { margin-left: 80px; }

        .win-sidebar {
            position: fixed; left: 0; top: 0; height: 100vh; width: 260px;
            background: rgba(20, 20, 25, 0.85); backdrop-filter: blur(30px);
            border-right: 1px solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width: 80px; }
        .win-sidebar.collapsed .sidebar-text, .win-sidebar.collapsed .sidebar-expand-only { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding: 12px; }
        .win-sidebar.collapsed .nav-item i { margin: 0; font-size: 1.5rem; }

        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 20px; text-align: center; }
        .sidebar-logo h3 { font-size: 1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin: 0; }
        .sidebar-logo small { font-size: 0.7rem; color: rgba(255, 255, 255, 0.5); }

        .sidebar-nav { flex: 1; padding: 0 12px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px;
            margin-bottom: 6px; border-radius: 12px;
            color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
            cursor: pointer; text-decoration: none;
        }
        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 3px solid #60a5fa; }
        .nav-item i { width: 24px; font-size: 1.2rem; text-align: center; }
        .nav-item span { font-size: 0.9rem; font-weight: 500; }

        .win-topbar {
            background: rgba(20, 20, 25, 0.7); backdrop-filter: blur(20px); border-radius: 16px;
            padding: 12px 24px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        .page-title p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 4px 0 0; }

        /* User Menu & Dropdowns */
/* ========================================== */
/* FIX: Menú de usuario por encima de todo    */
/* ========================================== */

.user-menu {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown-toggle {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown-menu {
    z-index: 99999 !important;
    position: absolute !important;
    top: 100% !important;
    right: 0 !important;
    left: auto !important;
    min-width: 220px !important;
    background: rgba(32, 32, 40, 0.98) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    padding: 8px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5) !important;
}

/* Asegurar que el topbar no interfiera */
.win-topbar {
    position: relative !important;
    z-index: 100 !important;
}

/* Asegurar que el main-container no bloquee el dropdown */
.main-container {
    position: relative !important;
    z-index: 1 !important;
}

/* Para el botón del avatar */
.user-avatar {
    position: relative !important;
    z-index: 9999 !important;
}
        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: white; font-size: 0.85rem; transition: all 0.2s;
            cursor: pointer; padding: 8px 16px; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-1px); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-1px); }
        .btn-win-sm { padding: 4px 12px; font-size: 0.75rem; }
        .btn-win-danger { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-danger:hover { background: rgba(220, 53, 69, 0.4); border-color: #dc3545; }
        .btn-win-success { background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.5); }
        .btn-win-success:hover { background: rgba(16, 185, 129, 0.4); border-color: #10b981; }
        .btn-win-warning { background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.5); }
        .btn-win-warning:hover { background: rgba(245, 158, 11, 0.4); border-color: #f59e0b; }

        .form-label { color: rgba(255, 255, 255, 0.85); font-size: 0.8rem; font-weight: 500; margin-bottom: 6px; }
        
        .form-control, .form-select, input.form-control, textarea.form-control, select.form-select {
            background: rgba(20, 20, 25, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 10px !important;
            color: #ffffff !important;
            padding: 8px 12px !important;
            font-size: 0.85rem !important;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(30, 30, 40, 0.9) !important;
            border-color: #60a5fa !important;
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2) !important;
            color: #ffffff !important;
        }
        
        .input-group-text {
            background: rgba(20, 20, 25, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: rgba(255, 255, 255, 0.7) !important;
        }

        /* ========================================== */
        /* ESTILOS OSCUROS PARA TABLAS - DARK THEME   */
        /* ========================================== */
        
        .table-responsive {
            border-radius: 12px;
            overflow-x: auto;
            background: rgba(15, 15, 20, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        #rangosTable, #tasasTable, #tablaUsuarios {
            background: transparent !important;
            margin-bottom: 0;
        }
        #rangosTable thead th, #tasasTable thead th, #tablaUsuarios thead th {
            background: rgba(22, 22, 30, 0.98) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 0.7rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            padding: 12px 8px !important;
            font-weight: 600 !important;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        #rangosTable tbody tr, #tasasTable tbody tr, #tablaUsuarios tbody tr {
            background: rgba(20, 20, 25, 0.4) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        #rangosTable tbody tr:hover, #tasasTable tbody tr:hover, #tablaUsuarios tbody tr:hover {
            background: rgba(96, 165, 250, 0.1) !important;
        }
        #rangosTable td, #tasasTable td, #tablaUsuarios td {
            padding: 10px 8px !important;
            vertical-align: middle !important;
            border: none !important;
            background: transparent !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }
        #rangosTable .form-control-sm {
            background: rgba(20, 20, 25, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 8px !important;
            color: #ffffff !important;
            padding: 6px 10px !important;
            font-size: 0.75rem !important;
        }
        #rangosTable .btn-win-danger, #tasasTable .btn-win-danger {
            background: rgba(220, 53, 69, 0.2) !important;
            border: 1px solid rgba(220, 53, 69, 0.5) !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            color: #f87171 !important;
        }
        #rangosTable .btn-win-danger:hover, #tasasTable .btn-win-danger:hover {
            background: rgba(220, 53, 69, 0.4) !important;
            color: #ffffff !important;
        }
        #rangosTable tfoot td {
            background: rgba(20, 20, 25, 0.8) !important;
            padding: 12px 8px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        #rangosTable .btn-win-sm {
            background: rgba(59, 130, 246, 0.2) !important;
            border: 1px solid rgba(59, 130, 246, 0.5) !important;
            color: #60a5fa !important;
            padding: 5px 12px !important;
            font-size: 0.7rem !important;
            border-radius: 8px !important;
        }
        
        #tablaUsuarios .avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        #tablaUsuarios .avatar-iniciales {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin: 0 auto;
        }
        #tablaUsuarios .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
        }
        #tablaUsuarios .btn-group-sm .btn-win-sm {
            padding: 4px 8px;
            font-size: 0.65rem;
        }
        
        /* Avatar preview */
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid #60a5fa;
            margin: 0 auto;
            overflow: hidden;
            background: rgba(20, 20, 25, 0.8);
            position: relative;
            cursor: pointer;
        }
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            flex-direction: column;
            font-size: 32px;
            color: rgba(255, 255, 255, 0.4);
        }
        .edit-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.75);
            color: white;
            text-align: center;
            padding: 8px;
            font-size: 9px;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0 0 50% 50%;
        }
        .avatar-preview:hover .edit-overlay { opacity: 1; }
        
        /* Crop modal */
        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 20px 20px;
            min-height: 400px;
            border-radius: 8px;
            overflow: hidden;
        }
        .preview-container {
            width: 150px;
            height: 150px;
            margin: 0 auto;
            overflow: hidden;
            border: 3px solid #60a5fa;
            border-radius: 50%;
            background: #2d2d2d;
        }
        
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; border-radius: 10px; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; border-radius: 10px; }
        .alert-info { background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; border-radius: 10px; }
        .alert-warning { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; border-radius: 10px; }
        
        .modal-content-win {
            background: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 20px;
            color: #ffffff;
        }
        .modal-header-win {
            background: linear-gradient(90deg, #0f0f1a 0%, #1a1a2e 100%);
            border-bottom: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 18px 18px 0 0;
        }
        .modal-footer-win {
            background: linear-gradient(90deg, #0f0f1a 0%, #1a1a2e 100%);
            border-top: 1px solid rgba(96, 165, 250, 0.3);
            border-radius: 0 0 18px 18px;
        }
        
        .date-badge {
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            color: #ffffff;
        }
        #liveClock { display: inline-block; min-width: 85px; text-align: center; }
        
        .text-muted, .text-secondary { color: #9ca3af !important; }
        .text-white-50 { color: rgba(255, 255, 255, 0.5) !important; }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        
        hr { border-color: rgba(255, 255, 255, 0.1); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        
        .swal2-popup { background: #1a1a2e !important; color: #ffffff !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }
        .swal2-styled.swal2-confirm { background-color: #10b981 !important; }
        .swal2-styled.swal2-cancel { background-color: #6b7280 !important; }
        
        .search-box { position: relative; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); z-index: 10; }
        .search-box input { padding-left: 32px !important; }
		#backupNamePreview {
			background: rgba(96, 165, 250, 0.1);
			padding: 2px 8px;
			border-radius: 4px;
			font-family: monospace;
			font-size: 0.85rem;
		}

		#backupNameModal .form-check-input:checked {
			background-color: #10b981;
			border-color: #10b981;
		}

		#backupNameModal .form-check-label {
			cursor: pointer;
		}
::placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
    opacity: 1 !important;
}

:-ms-input-placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
}

::-ms-input-placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
}

/* Para inputs específicos que puedan necesitar más visibilidad */
.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4) !important;
}

.form-control-sm::placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
    font-size: 0.75rem !important;
}
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-cog me-2" style="color: #60a5fa;"></i>Configuración del Sistema</h1>
                <p><i class="fas fa-sliders-h me-1"></i> Parámetros generales y configuración de impuestos</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Mensajes de alerta -->
    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show mb-4 fade-in-up" role="alert">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
<!-- Sección de Base de Datos -->
<div class="row g-4 mb-4 fade-in-up" style="animation-delay: 0.02s;">
    <div class="col-12">
        <div class="glass-card">
            <div class="p-3 border-bottom border-white-10">
                <h6 class="mb-0 fw-semibold">
                    <i class="fas fa-database me-2" style="color: #f59e0b;"></i> 
                    Base de Datos (SALVAS Y RESTAURAS)
                </h6>
            </div>
            <div class="col-12">
                <div class="alert alert-info mt-3 mb-0 position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-50 end-0 translate-middle-y me-2" 
                            onclick="this.closest('.alert').style.display='none'; localStorage.setItem('backupAlertClosed', 'true');"
                            aria-label="Cerrar"></button>
                    <i class="fas fa-shield-alt me-2"></i>
                    <strong>Recomendación:</strong> Realice un backup antes de hacer cambios importantes en la configuración o antes de restaurar datos.
                </div>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2);">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <i class="fas fa-download fa-2x mb-2" style="color: #10b981;"></i>
                                    <h6 class="mb-1">Salvar Base de Datos</h6>
                                    <small class="text-white-50">Crear copia de seguridad completa (Backup ZIP)</small>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-win btn-win-success" id="btnBackupManualCard">
                                        <i class="fas fa-download me-2"></i> Generar Backup
                                    </button>
                                    <button class="btn-win btn-win-primary" id="btnBackupWithNameCard" data-bs-toggle="modal" data-bs-target="#backupNameModal">
                                        <i class="fas fa-file-export me-2"></i> Backup con nombre
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2);">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <i class="fas fa-upload fa-2x mb-2" style="color: #f59e0b;"></i>
                                    <h6 class="mb-1">Restaurar Base de Datos</h6>
                                    <small class="text-white-50">Importar backup SQL/ZIP</small>
                                </div>
                                <button class="btn-win btn-win-warning" id="btnRestoreBackupCard">
                                    <i class="fas fa-upload me-2"></i> Restaurar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
	
    <div class="row g-4">
        <!-- Configuración General -->
        <div class="col-lg-6 fade-in-up" style="animation-delay: 0.05s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-sliders-h me-2" style="color: #60a5fa;"></i> Configuración General</h6>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Horas laborables mensuales</label>
                                <input type="number" class="form-control" name="horas_mensuales" value="<?php echo htmlspecialchars($config['horas_mensuales'] ?? '192'); ?>">
                                <small class="text-secondary">24 días × 8 horas = 192 horas</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Días laborables mensuales</label>
                                <input type="number" class="form-control" name="dias_mensuales" value="<?php echo htmlspecialchars($config['dias_mensuales'] ?? '24'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Horas por jornada diaria</label>
                                <input type="number" class="form-control" name="horas_jornada_diaria" value="<?php echo htmlspecialchars($config['horas_jornada_diaria'] ?? '8'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Salario mínimo mensual</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" name="salario_minimo" value="<?php echo htmlspecialchars($config['salario_minimo'] ?? '2100'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tasa de Contribución Especial (%)</label>
                                <input type="number" step="0.01" class="form-control" name="tasa_contribucion_especial" value="<?php echo htmlspecialchars($config['tasa_contribucion_especial'] ?? '5'); ?>">
                                <small class="text-secondary">Porcentaje aplicado al salario devengado</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Recargo Nocturno (%)</label>
                                <input type="number" step="0.01" class="form-control" name="recargo_nocturno" value="<?php echo htmlspecialchars($config['recargo_nocturno'] ?? '25'); ?>">
                                <small class="text-secondary">Porcentaje adicional por horas nocturnas</small>
                            </div>
                        </div>
                        <hr>
                        <h6 class="fw-semibold mb-3"><i class="fas fa-building me-2" style="color: #a78bfa;"></i>Datos de la Entidad</h6>
                        <div class="mb-3">
                            <label class="form-label">Nombre de la Empresa</label>
                            <input type="text" class="form-control" name="nombre_empresa" value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? 'PDL TRANSNUBET'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" name="direccion_empresa" value="<?php echo htmlspecialchars($config['direccion_empresa'] ?? 'Carretera Central Km 5, Camagüey, Cuba'); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">REEUP / Código de Identificación Fiscal</label>
                                <input type="text" class="form-control" name="reeup_empresa" value="<?php echo htmlspecialchars($config['reeup_empresa'] ?? '319-1-02264'); ?>">
                                <small class="text-secondary">Registro Estatal de Entidades y Unidades Presupuestadas</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIT (Número de Identificación Tributaria)</label>
                                <input type="text" class="form-control" name="nit_empresa" value="<?php echo htmlspecialchars($config['nit_empresa'] ?? '1018569663222'); ?>">
                                <small class="text-secondary">Número de Identificación Tributaria</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jefe de Proyecto</label>
                                <input type="text" class="form-control" name="jefe_proyecto" value="<?php echo htmlspecialchars($config['jefe_proyecto'] ?? 'Dainelys León Reyes'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialista en Gestión Económica</label>
                                <input type="text" class="form-control" name="especialista_gestion" value="<?php echo htmlspecialchars($config['especialista_gestion'] ?? 'Mailén Pérez García'); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Intendente</label>
                            <input type="text" class="form-control" name="intendente" value="<?php echo htmlspecialchars($config['intendente'] ?? 'Eladio Francisco Ávalos'); ?>">
                            <small class="text-secondary">Aprueba la plantilla de cargos</small>
                        </div>
                        <button type="submit" name="guardar_config_general" class="btn-win btn-win-primary w-100">
                            <i class="fas fa-save me-1"></i> Guardar Configuración General
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Rangos de Impuesto y Tasas -->
        <div class="col-lg-6 fade-in-up" style="animation-delay: 0.1s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-line me-2" style="color: #10b981;"></i> Rangos de Impuesto (Ingresos Personales)</h6>
                </div>
                <div class="p-4">
                    <form method="POST" id="rangosForm">
                        <div class="mb-3">
                            <label class="form-label">Fecha Vigencia</label>
                            <input type="date" class="form-control" name="fecha_vigencia" value="<?php echo $fecha_vigencia_actual; ?>" required>
                            <small class="text-secondary">Fecha desde la cual aplican estos rangos</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm" id="rangosTable">
                                <thead>
                                    <tr><th>Desde (CUP)</th><th>Hasta (CUP)</th><th>Tasa (%)</th><th>Monto Fijo</th><th style="width: 40px"></th></tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($rangos_impuesto)): ?>
                                        <?php foreach ($rangos_impuesto as $index => $rango): ?>
                                        <tr>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][desde]" value="<?php echo $rango['desde']; ?>" required></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][hasta]" value="<?php echo $rango['hasta']; ?>"></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][tasa]" value="<?php echo $rango['tasa'] * 100; ?>" required></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][monto_fijo]" value="<?php echo $rango['monto_fijo']; ?>"></td>
                                            <td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][desde]" value="0" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][hasta]" value="3260"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][tasa]" value="0" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][desde]" value="3260.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][hasta]" value="9510"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][tasa]" value="3" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][desde]" value="9510.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][hasta]" value="15000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][tasa]" value="5" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                        <td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][desde]" value="15000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][hasta]" value="20000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][tasa]" value="7.5" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                        <td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][desde]" value="20000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][hasta]" value="25000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][tasa]" value="10" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][desde]" value="25000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][hasta]" value="30000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][tasa]" value="15" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][desde]" value="30000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][hasta]" value=""></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][tasa]" value="20" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot><tr><td colspan="5"><button type="button" class="btn-win btn-win-sm" onclick="agregarFila()"><i class="fas fa-plus-circle me-1"></i> Agregar Rango</button></td></tr></tfoot>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Los rangos se aplican en orden ascendente. Dejar "Hasta" en blanco para el último rango.
                        </div>
                        <button type="submit" name="guardar_rangos" class="btn-win btn-win-primary w-100">
                            <i class="fas fa-save me-1"></i> Guardar Rangos de Impuesto
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Configuración de Tasas -->
            <div class="glass-card mt-4 fade-in-up" style="animation-delay: 0.15s;">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-percent me-2" style="color: #f59e0b;"></i> Tasas del Sistema</h6>
                    <button type="button" class="btn-win btn-win-sm btn-win-success" data-bs-toggle="modal" data-bs-target="#agregarTasaModal">
                        <i class="fas fa-plus-circle me-1"></i> Agregar Tasa
                    </button>
                </div>
                <div class="p-4">
                    <div class="table-responsive">
                        <table class="table table-sm" id="tasasTable">
                            <thead><tr><th>Tasa</th><th>Valor</th><th>Vigencia</th><th>Descripción</th><th style="width: 40px"></th></tr></thead>
                            <tbody>
                                <?php foreach ($tasas as $tasa): ?>
                                <tr>
                                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $tasa['nombre_tasa'])); ?></strong></td>
                                    <td><?php echo $tasa['valor']; ?>%</td>
                                    <td><?php echo date('d/m/Y', strtotime($tasa['fecha_vigencia'])); ?></td>
                                    <td><?php echo $tasa['descripcion']; ?></td>
                                    <td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarTasa(<?php echo $tasa['id']; ?>, '<?php echo addslashes($tasa['nombre_tasa']); ?>')"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tasas)): ?>
                                <tr><td colspan="5" class="text-center text-secondary">No hay tasas configuradas</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gestión de Usuarios -->
    <div class="glass-card fade-in-up mt-4" style="animation-delay: 0.12s;">
        <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold"><i class="fas fa-users me-2" style="color: #60a5fa;"></i> Gestión de Usuarios del Sistema</h6>
            <button type="button" class="btn-win btn-win-primary btn-win-sm" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="limpiarFormularioUsuario()">
                <i class="fas fa-user-plus me-1"></i> Nuevo Usuario
            </button>
        </div>
        <div class="p-3">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchUsuarioInput" class="form-control form-control-sm" placeholder="Buscar por nombre, usuario o CI...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterRolInput" class="form-select form-select-sm">
                        <option value="">Todos los roles</option>
                        <?php $stmt_roles = $pdo->query("SELECT id, descripcion FROM clasif_rol ORDER BY descripcion"); while ($rol = $stmt_roles->fetch()): ?>
                            <option value="<?php echo $rol['descripcion']; ?>"><?php echo htmlspecialchars($rol['descripcion']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterEstadoInput" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn-win btn-win-sm w-100" onclick="filtrarUsuarios()"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </div>
            
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover" id="tablaUsuarios" style="min-width: 800px;">
                    <thead style="position: sticky; top: 0; background: rgba(22, 22, 30, 0.95); z-index: 10;">
                        <tr><th>Avatar</th><th>Usuario</th><th>Nombre Completo</th><th>CI</th><th>Rol</th><th>Email</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody id="tablaUsuariosBody">
<?php foreach ($usuarios_lista as $usr):
    $nombre_completo = trim($usr['nombre'] . ' ' . $usr['apellidos']);
    $iniciales = strtoupper(substr($usr['nombre'], 0, 1) . substr($usr['apellidos'], 0, 1));
    $foto_url = !empty($usr['foto']) ? $usr['foto'] : null;
    
    // CORREGIR: Desde modules/ necesitamos ../assets/imagenes/usuarios/
    if ($foto_url) {
        if (strpos($foto_url, 'assets/') === 0) {
            $foto_url = '../' . $foto_url;
        }
    }
    
    // Verificar si el archivo existe
    $foto_existe = false;
    if ($foto_url) {
        $ruta_foto_absoluta = $_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . str_replace('../', '', $foto_url);
        if (file_exists($ruta_foto_absoluta)) {
            $foto_existe = true;
        }
    }
?>
<tr data-id="<?php echo $usr['id']; ?>" data-rol="<?php echo htmlspecialchars($usr['rol_nombre'] ?? ''); ?>" data-estado="<?php echo $usr['activo']; ?>">
    <td class="text-center">
        <?php if ($foto_existe): ?>
            <img src="<?php echo $foto_url; ?>" class="avatar-small" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <div class="avatar-iniciales"><?php echo $iniciales; ?></div>
        <?php endif; ?>
    </td>
    <td><code><?php echo htmlspecialchars($usr['usuario']); ?></code></td>
    <td><strong><?php echo htmlspecialchars($nombre_completo); ?></strong></td>
    <td><?php echo htmlspecialchars($usr['no_ci']); ?></td>
    <td>
        <span class="badge <?php 
            if ($usr['rol_nombre'] == 'Administrador del Sistema') echo 'bg-danger';
            elseif ($usr['rol_nombre'] == 'Programador') echo 'bg-dark';
            elseif ($usr['rol_nombre'] == 'Supervisor General') echo 'bg-warning text-dark';
            elseif ($usr['rol_nombre'] == 'Facturador / Editor') echo 'bg-info';
            else echo 'bg-secondary';
        ?>">
            <?php echo htmlspecialchars($usr['rol_nombre'] ?? 'Sin rol'); ?>
        </span>
    </td>
    <td><?php echo htmlspecialchars($usr['email'] ?? '-'); ?></td>
    <td>
        <span class="badge <?php echo $usr['activo'] ? 'bg-success' : 'bg-danger'; ?>">
            <?php echo $usr['activo'] ? 'Activo' : 'Inactivo'; ?>
        </span>
    </td>
    <td>
        <div class="btn-group btn-group-sm">
            <button class="btn-win btn-win-sm" onclick="editarUsuario(<?php echo $usr['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
            <button class="btn-win btn-win-sm <?php echo $usr['activo'] ? 'btn-win-warning' : 'btn-win-success'; ?>" onclick="toggleEstadoUsuario(<?php echo $usr['id']; ?>, <?php echo $usr['activo']; ?>, '<?php echo addslashes($nombre_completo); ?>')" title="<?php echo $usr['activo'] ? 'Desactivar' : 'Activar'; ?>"><i class="fas <?php echo $usr['activo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i></button>
            <button class="btn-win btn-win-danger btn-win-sm" onclick="eliminarUsuario(<?php echo $usr['id']; ?>, '<?php echo addslashes($nombre_completo); ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="row mt-3 pt-2 border-top border-white-10">
                <div class="col-4 text-center"><div class="h5 mb-0 text-info" id="totalUsuariosCount"><?php echo count($usuarios_lista); ?></div><small class="text-white-50">Total Usuarios</small></div>
                <div class="col-4 text-center"><div class="h5 mb-0 text-success" id="usuariosActivosCount"><?php $activos = 0; foreach ($usuarios_lista as $u) if ($u['activo']) $activos++; echo $activos; ?></div><small class="text-white-50">Activos</small></div>
                <div class="col-4 text-center"><div class="h5 mb-0 text-warning" id="rolesCount"><?php $roles_unicos = []; foreach ($usuarios_lista as $u) $roles_unicos[$u['rol_nombre']] = true; echo count($roles_unicos); ?></div><small class="text-white-50">Roles</small></div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- Modal para agregar tasa -->
<div class="modal fade" id="agregarTasaModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color: #60a5fa;"></i>Agregar Nueva Tasa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="agregarTasaForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="agregar_tasa" value="1">
                    <div class="mb-3"><label class="form-label">Nombre de la Tasa *</label><input type="text" class="form-control" name="nombre_tasa" required placeholder="Ej: contribucion_especial, recargo_nocturno"><small class="text-secondary">Identificador único de la tasa</small></div>
                    <div class="mb-3"><label class="form-label">Valor (%) *</label><input type="number" step="0.01" class="form-control" name="valor_tasa" required placeholder="Ej: 5.00"></div>
                    <div class="mb-3"><label class="form-label">Fecha de Vigencia *</label><input type="date" class="form-control" name="fecha_vigencia_tasa" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion_tasa" rows="3" placeholder="Describa el propósito de esta tasa"></textarea></div>
                </div>
                <div class="modal-footer modal-footer-win">
                    <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancelar</button>
                    <button type="submit" class="btn-win btn-win-primary btn-win-sm"><i class="fas fa-save me-1"></i> Guardar Tasa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Restaurar Backup -->
<div class="modal fade" id="restoreModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-database me-2" style="color: #f97316;"></i> Restaurar Base de Datos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning mb-4" style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 12px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <i class="fas fa-exclamation-triangle fa-2x" style="color: #fbbf24;"></i>
                        <div>
                            <strong style="color: #fbbf24;">¡Precaución!</strong>
                            <p class="mb-0 mt-1" style="color: #d1d5db;">Esta acción SOBRESCRIBIRÁ todos los datos actuales. Asegúrate de tener un backup antes de continuar.</p>
                        </div>
                    </div>
                </div>
                <div class="mb-4 p-3" style="background: rgba(96, 165, 250, 0.08); border-radius: 10px;">
                    <div class="restore-info-text"><i class="fas fa-info-circle me-2"></i> <strong>¿Qué se restaurará?</strong></div>
                    <ul class="restore-info-list mt-2" style="list-style: none; padding-left: 0;">
                        <li><i class="fas fa-check-circle text-success me-2"></i> Estructura completa de la base de datos</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Datos de empleados y nóminas</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Configuración del sistema y tasas</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Historial de vacaciones y submayores</li>
                    </ul>
                </div>
                <form id="restoreForm" enctype="multipart/form-data">
                    <div class="form-group mb-3">
                        <label class="form-label mb-2"><i class="fas fa-file-archive me-1"></i> Seleccionar archivo de backup:</label>
                        <input type="file" name="backup_file" id="restoreFile" class="form-control" accept=".sql,.zip" required>
                        <small class="text-secondary mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i> Formatos soportados: .sql, .zip (máximo 300MB)
                        </small>
                        <small class="text-secondary d-block mt-1" id="fileSizeInfo"></small>
                    </div>
                    
                    <!-- CHECKBOX DE CONFIRMACIÓN - OBLIGATORIO -->
                    <div class="form-check mb-4 p-3" style="background: rgba(220, 53, 69, 0.08); border-radius: 8px; border-left: 3px solid #dc3545;">
                        <input type="checkbox" class="form-check-input" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore" style="color: #fca5a5;">
                            <i class="fas fa-exclamation-triangle me-1"></i> 
                            <strong>Confirmo que:</strong>
                            <ul style="margin: 5px 0 0 20px; padding-left: 0; list-style: none;">
                                <li><i class="fas fa-check-circle text-success me-1" style="font-size: 0.7rem;"></i> Tengo un backup actual de la base de datos</li>
                                <li><i class="fas fa-check-circle text-success me-1" style="font-size: 0.7rem;"></i> Comprendo que se sobrescribirán todos los datos</li>
                                <li><i class="fas fa-check-circle text-success me-1" style="font-size: 0.7rem;"></i> Deseo proceder con la restauración</li>
                            </ul>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-win btn-win-warning w-100" id="btnRestore" disabled>
                        <i class="fas fa-upload me-2"></i> Restaurar Backup
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Backup con nombre personalizado -->
<div class="modal fade" id="backupNameModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-file-export me-2" style="color: #10b981;"></i> Backup con nombre personalizado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info mb-4" style="background: rgba(96, 165, 250, 0.12); border: 1px solid rgba(96, 165, 250, 0.35); border-radius: 12px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <i class="fas fa-info-circle fa-2x" style="color: #60a5fa;"></i>
                        <div>
                            <strong style="color: #60a5fa;">Backup personalizado</strong>
                            <p class="mb-0 mt-1" style="color: #d1d5db;">Asigna un nombre descriptivo a tu backup para identificarlo fácilmente.</p>
                        </div>
                    </div>
                </div>
                <form id="backupNameForm">
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-tag me-1"></i> Nombre del backup <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="backupNombreInput" 
                               placeholder="Ej: backup_pre_actualizacion_2026_07_12" 
                               required maxlength="50">
                        <small class="text-secondary d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i> 
                            Se agregará automáticamente la fecha y hora: 
                            <span id="backupNamePreview" style="color: #60a5fa; font-family: monospace;">backup_sistema_YYYY_MM_DD_HH_MM</span>
                        </small>
                        <small class="text-secondary d-block mt-1">
                            <i class="fas fa-info-circle me-1"></i> 
                            Máximo 50 caracteres (solo letras, números, guiones y guiones bajos)
                        </small>
                    </div>
                    <div class="mb-3 p-3" style="background: rgba(16, 185, 129, 0.05); border-radius: 8px;">
                        <h6 class="text-muted small"><i class="fas fa-list-check me-1"></i> Contenido del backup:</h6>
                        <ul class="text-muted small" style="list-style: none; padding-left: 0; margin-bottom: 0;">
                            <li><i class="fas fa-check-circle text-success me-1"></i> Estructura completa de la base de datos</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Datos de empleados y nóminas</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Configuración del sistema y tasas</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Historial de vacaciones y submayores</li>
                        </ul>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmBackupName" required>
                        <label class="form-check-label" for="confirmBackupName" style="color: #d1d5db;">
                            <i class="fas fa-check-circle me-1" style="color: #10b981;"></i> 
                            Confirmo que deseo crear este backup con el nombre especificado
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer modal-footer-win">
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" class="btn-win btn-win-success" id="btnConfirmBackupWithName">
                    <i class="fas fa-download me-1"></i> Generar Backup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para crear/editar usuarios con crop -->
<div class="modal fade" id="modalUsuario" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title" id="modalUsuarioTitle"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUsuario" method="POST">
                <input type="hidden" name="action" id="usuarioAction" value="crear">
                <input type="hidden" name="usuario_id" id="usuarioId" value="">
                <input type="hidden" name="imagen_recortada" id="imagen_recortada_usuario" value="">
                <input type="hidden" name="eliminar_foto" id="eliminarFotoUsuario" value="0">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <div class="avatar-preview mb-2" id="avatarPreviewUsuario" onclick="abrirEditorFotoUsuario()">
                                    <div id="fotoPlaceholderUsuario" class="avatar-placeholder"><i class="fas fa-camera"></i></div>
                                    <img id="imagePreviewUsuario" src="" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                                    <div class="edit-overlay py-1"><i class="fas fa-crop-alt me-1"></i> Editar</div>
                                </div>
                                <div class="d-flex gap-1 justify-content-center mt-2">
                                    <label class="btn-win btn-win-sm py-1" style="font-size: 0.7rem;"><i class="fas fa-upload me-1"></i> Subir Foto<input type="file" id="imageUploadUsuario" accept="image/jpeg,image/png" hidden onchange="cargarImagenUsuario(this)"></label>
                                    <button type="button" class="btn-win btn-win-danger btn-win-sm py-1" id="btnEliminarFotoUsuario" style="display: none;" onclick="eliminarFotoUsuario()"><i class="fas fa-trash-alt me-1"></i> Eliminar</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Usuario <span class="text-danger">*</span></label><input type="text" class="form-control" name="usuario" id="usuario" required></div>
                                <div class="col-md-6"><label class="form-label">Contraseña <span class="text-danger" id="passRequired">*</span></label><input type="password" class="form-control" name="password" id="password"><small class="text-muted" id="passHelp">Dejar en blanco para mantener la actual</small></div>
                                <div class="col-md-4"><label class="form-label">Nombre <span class="text-danger">*</span></label><input type="text" class="form-control" name="nombre" id="nombre" required><small class="text-muted" id="nombreFeedback"></small></div>
                                <div class="col-md-4"><label class="form-label">Primer Apellido <span class="text-danger">*</span></label><input type="text" class="form-control" name="primer_apellido" id="primer_apellido" required><small class="text-muted" id="apellido1Feedback"></small></div>
                                <div class="col-md-4"><label class="form-label">Segundo Apellido <span class="text-danger">*</span></label><input type="text" class="form-control" name="segundo_apellido" id="segundo_apellido" required><small class="text-muted" id="apellido2Feedback"></small></div>
                                <div class="col-md-6"><label class="form-label">Carnet de Identidad <span class="text-danger">*</span></label><input type="text" class="form-control" name="no_ci" id="no_ci" maxlength="11" required><small class="text-muted" id="ciFeedbackUsuario"></small></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="email"></div>
                                <div class="col-md-6"><label class="form-label">Rol <span class="text-danger">*</span></label><select class="form-select" name="rol_id" id="rol_id" required><option value="">-- Seleccione un rol --</option><?php $stmt_roles2 = $pdo->query("SELECT id, descripcion FROM clasif_rol ORDER BY id"); while ($rol2 = $stmt_roles2->fetch()): ?><option value="<?php echo $rol2['id']; ?>"><?php echo htmlspecialchars($rol2['descripcion']); ?></option><?php endwhile; ?></select></div>
                                <div class="col-md-12"><div class="form-check form-switch"><input type="checkbox" class="form-check-input" name="activo" id="activo_usuario" checked><label class="form-check-label" for="activo_usuario">Usuario Activo</label></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-win">
                    <button type="button" class="btn-win btn-win-danger btn-win-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancelar</button>
                    <button type="submit" class="btn-win btn-win-primary btn-win-sm"><i class="fas fa-save me-1"></i> Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para recortar imagen -->
<div class="modal fade" id="cropModalUsuario" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: rgba(25, 25, 35, 0.98); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-crop-alt me-2"></i> Recortar imagen de perfil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropperUsuario()"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8"><div class="img-container"><img id="imageToCropUsuario" src="" style="max-width: 100%; display: block;"></div></div>
                    <div class="col-md-4">
                        <div class="text-center mb-3"><h6>Vista previa</h6><div class="preview-container"><canvas id="previewCanvasUsuario" width="150" height="150"></canvas></div></div>
                        <div class="crop-controls p-3">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="rotarImagenUsuario(-90)"><i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="rotarImagenUsuario(90)"><i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetearCropUsuario()"><i class="fas fa-sync-alt me-2"></i>Resetear recorte</button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="zoomImagenUsuario(0.1)"><i class="fas fa-search-plus me-2"></i>Acercar</button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="zoomImagenUsuario(-0.1)"><i class="fas fa-search-minus me-2"></i>Alejar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropperUsuario()"><i class="fas fa-times me-2"></i>Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="aplicarRecorteUsuario()"><i class="fas fa-check me-2"></i>Aplicar recorte</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/cropper.min.js"></script>

<script>
// Clock y Sidebar
const usuarioActualId = <?php echo $usuario_actual_id; ?>;

function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const clockSpan = document.getElementById('liveClock');
    if (clockSpan) clockSpan.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000); updateClock();

const sidebar = document.getElementById('winSidebar');
const mainContainer = document.getElementById('mainContainer');
if (sidebar && mainContainer) {
    if (localStorage.getItem('winSidebarCollapsed') === 'true') { sidebar.classList.add('collapsed'); mainContainer.classList.add('expanded'); }
    document.getElementById('sidebarToggleBtn')?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed'); mainContainer.classList.toggle('expanded');
        localStorage.setItem('winSidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
}

// Backup y Restore
function realizarBackupManual() {
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: '<div style="text-align: left;"><p><i class="fas fa-info-circle me-2"></i> Se creará una copia de seguridad completa.</p><p><small>La copia incluirá: empleados, nóminas, configuración y vacaciones.</small></p><div class="alert alert-info mt-2"><i class="fas fa-clock me-1"></i> El archivo se guardará en formato ZIP</div></div>',
        icon: 'info', showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        background: '#1a1a2e', color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#fff' });
            fetch('../ajax/backup_db.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ title: '<i class="fas fa-check-circle me-2"></i> Backup Completado', html: `<p>Archivo: ${data.filename}</p><p>Tamaño: ${data.size}</p><a href="../${data.download_url}" class="btn btn-success" download><i class="fas fa-download me-2"></i> Descargar</a>`, icon: 'success', background: '#1a1a2e', color: '#fff', confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'});
                } else { Swal.fire({ title: '<i class="fas fa-exclamation-triangle me-2"></i> Error', text: data.message, icon: 'error', background: '#1a1a2e', color: '#fff' }); }
            })
            .catch(() => { Swal.fire({ title: 'Error', text: 'Error de conexión', icon: 'error', background: '#1a1a2e', color: '#fff' }); });
        }
    });
}
// ==========================================
// BACKUP CON NOMBRE PERSONALIZADO
// ==========================================

function realizarBackupConNombre(nombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
        html: `<p>Creando backup: <strong>${nombre}</strong></p><p class="text-muted small">Este proceso puede tomar unos segundos...</p>`,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: '#fff'
    });

    fetch('../ajax/backup_db.php?nombre_custom=' + encodeURIComponent(nombre), { 
        method: 'GET', 
        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                title: '<i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Backup Completado',
                html: `
                    <div class="text-start">
                        <p><strong>Archivo:</strong> ${data.filename}</p>
                        <p><strong>Tamaño:</strong> ${data.size}</p>
                        <p><strong>Nombre asignado:</strong> ${data.nombre || nombre}</p>
                        <div class="mt-3">
                            <a href="../${data.download_url}" class="btn btn-success w-100" download>
                                <i class="fas fa-download me-2"></i> Descargar Backup
                            </a>
                        </div>
                    </div>
                `,
                icon: 'success',
                background: '#1a1a2e',
                color: '#fff',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
            });
        } else {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Error',
                text: data.message || 'Error al generar el backup',
                icon: 'error',
                background: '#1a1a2e',
                color: '#fff'
            });
        }
    })
    .catch(() => {
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-wifi me-2" style="color: #dc3545;"></i> Error de Conexión',
            text: 'No se pudo conectar con el servidor',
            icon: 'error',
            background: '#1a1a2e',
            color: '#fff'
        });
    });
}

// Previsualizar nombre del backup
document.getElementById('backupNombreInput')?.addEventListener('input', function() {
    const nombre = this.value.trim() || 'backup_sistema';
    // Limpiar caracteres especiales para previsualización
    const nombreLimpio = nombre.replace(/[^a-zA-Z0-9_\-]/g, '_');
    const fecha = new Date();
    const fechaStr = fecha.getFullYear() + '_' + 
                     String(fecha.getMonth() + 1).padStart(2, '0') + '_' + 
                     String(fecha.getDate()).padStart(2, '0') + '_' + 
                     String(fecha.getHours()).padStart(2, '0') + '_' + 
                     String(fecha.getMinutes()).padStart(2, '0');
    const nombreCompleto = nombreLimpio + '_' + fechaStr;
    document.getElementById('backupNamePreview').textContent = nombreCompleto;
    document.getElementById('backupNamePreview').style.color = nombreLimpio.length > 0 ? '#60a5fa' : '#f59e0b';
});

// Botón confirmar backup con nombre
document.getElementById('btnConfirmBackupWithName')?.addEventListener('click', function() {
    const nombreInput = document.getElementById('backupNombreInput');
    let nombre = nombreInput.value.trim();
    
    if (!nombre) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i> Nombre requerido',
            text: 'Por favor, ingresa un nombre para identificar el backup',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#fff',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
        nombreInput.focus();
        return;
    }
    
    // Limpiar caracteres especiales
    nombre = nombre.replace(/[^a-zA-Z0-9_\-]/g, '_');
    
    if (nombre.length < 3) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i> Nombre muy corto',
            text: 'El nombre debe tener al menos 3 caracteres',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#fff'
        });
        nombreInput.focus();
        return;
    }
    
    const confirmCheckbox = document.getElementById('confirmBackupName');
    if (!confirmCheckbox.checked) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i> Confirmación requerida',
            text: 'Debes marcar la casilla de confirmación para crear el backup',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#fff',
			confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
        return;
    }
    
    // Cerrar modal y ejecutar backup
    bootstrap.Modal.getInstance(document.getElementById('backupNameModal')).hide();
    
    // Limpiar el campo para la próxima vez
    setTimeout(() => {
        nombreInput.value = '';
        confirmCheckbox.checked = false;
        document.getElementById('backupNamePreview').textContent = 'backup_sistema_YYYY_MM_DD_HH_MM';
        document.getElementById('backupNamePreview').style.color = '#60a5fa';
    }, 300);
    
    realizarBackupConNombre(nombre);
});

// Evento para tecla Enter en el input
document.getElementById('backupNombreInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('btnConfirmBackupWithName').click();
    }
});

// ==========================================
// FUNCIONES EXISTENTES (mantener las que ya tienes)
// ==========================================

function realizarBackupManual() {
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: '<div style="text-align: left;"><p><i class="fas fa-info-circle me-2"></i> Se creará una copia de seguridad completa.</p><p><small>La copia incluirá: empleados, nóminas, configuración y vacaciones.</small></p><div class="alert alert-info mt-2"><i class="fas fa-clock me-1"></i> El archivo se guardará en formato ZIP</div></div>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        background: '#1a1a2e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#1a1a2e',
                color: '#fff'
            });
            fetch('../ajax/backup_db.php', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle me-2"></i> Backup Completado',
                        html: `<p>Archivo: ${data.filename}</p><p>Tamaño: ${data.size}</p><a href="../${data.download_url}" class="btn btn-success" download><i class="fas fa-download me-2"></i> Descargar</a>`,
                        icon: 'success',
                        background: '#1a1a2e',
                        color: '#fff',
                        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
                    });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-exclamation-triangle me-2"></i> Error',
                        text: data.message,
                        icon: 'error',
                        background: '#1a1a2e',
                        color: '#fff'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: '#fff'
                });
            });
        }
    });
}


// Event listeners para los botones
document.getElementById('btnBackupManual')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    realizarBackupManual(); 
});

document.getElementById('btnBackupManualCard')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    realizarBackupManual(); 
});

document.getElementById('btnRestoreBackup')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    restaurarBackup(); 
});

document.getElementById('btnRestoreBackupCard')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    restaurarBackup(); 
});

// 1. Crear una variable global para la instancia del modal
let restoreModalInstance = null;

function restaurarBackup() {
    const modalEl = document.getElementById('restoreModal');
    
    // 2. Solo crear la instancia si no existe
    if (!restoreModalInstance) {
        restoreModalInstance = new bootstrap.Modal(modalEl);
    }
    
    restoreModalInstance.show();
}

// 3. AGREGAR ESTO: Limpieza forzosa al cerrar cualquier modal
document.addEventListener('hidden.bs.modal', function () {
    // Si quedan backdrops huérfanos, los eliminamos
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(b => b.remove());
    // Devolvemos el scroll al cuerpo
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
});


document.getElementById('btnBackupManual')?.addEventListener('click', (e) => { e.preventDefault(); realizarBackupManual(); });
document.getElementById('btnRestoreBackup')?.addEventListener('click', (e) => { e.preventDefault(); restaurarBackup(); });
document.getElementById('confirmRestore')?.addEventListener('change', function() { document.getElementById('btnRestore').disabled = !this.checked; });
document.getElementById('restoreForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('restoreFile');
    if (!fileInput.files || fileInput.files.length === 0) { Swal.fire({ title: 'Error', text: 'Seleccione un archivo', icon: 'error', background: '#1a1a2e', color: '#fff' }); return; }
    const formData = new FormData(this);
	
    // CERRAR EL MODAL DE BOOTSTRAP ANTES DE MOSTRAR EL SWAL DE CARGA
    if (restoreModalInstance) {
        restoreModalInstance.hide();
    }
	
    Swal.fire({ title: 'Restaurando...', html: '<i class="fas fa-spinner fa-pulse fa-3x"></i><p>Este proceso puede tomar varios minutos...</p>', allowOutsideClick: false, showConfirmButton: false, background: '#1a1a2e', color: '#fff', didOpen: () => Swal.showLoading() });
    fetch('../ajax/restore_db.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) { Swal.fire({ title: 'Restauración Completada', html: `<pre style="background:#2d2d3a; padding:12px; border-radius:8px;">${data.message}</pre>`, icon: 'success', confirmButtonText: '<i class="fas fa-check me-2"></i> Recargar', background: '#1a1a2e', color: '#fff',  }).then(() => location.reload()); }
        else { Swal.fire({ title: 'Error', html: `<pre style="background:#2d2d3a; padding:12px; border-radius:8px; color:#fca5a5;">${data.message}</pre>`, icon: 'error', background: '#1a1a2e', color: '#fff' }); }
    })
    .catch(() => { Swal.fire({ title: 'Error de Conexión', text: 'No se pudo conectar', icon: 'error', background: '#1a1a2e', color: '#fff' }); });
});

// Funciones para rangos y tasas
function agregarFila() {
    const tbody = document.querySelector('#rangosTable tbody');
    const rowCount = tbody.children.length;
    const newRow = document.createElement('tr');
    newRow.innerHTML = `<td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][desde]" required></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][hasta]"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][tasa]" required></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][monto_fijo]" value="0"></td>
                        <td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)"><i class="fas fa-trash"></i></button></td>`;
    tbody.appendChild(newRow);
}

function confirmarEliminarRango(btn) {
    const row = btn.closest('tr');
    Swal.fire({ title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Eliminar Rango', text: '¿Está seguro?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí', background: '#1a1a2e', color: '#fff' }).then((result) => { if (result.isConfirmed) { row.remove(); Swal.fire({ title: 'Eliminado', text: 'Rango eliminado', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1a1a2e', color: '#fff' }); } });
}

function confirmarEliminarTasa(tasaId, tasaNombre) {
    Swal.fire({ title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Eliminar Tasa', html: `<p>¿Eliminar la tasa <strong>${tasaNombre}</strong>?</p>`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí', background: '#1a1a2e', color: '#fff' }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form'); form.method = 'POST'; form.style.display = 'none'; form.innerHTML = `<input type="hidden" name="eliminar_tasa" value="1"><input type="hidden" name="tasa_id" value="${tasaId}">`;
            document.body.appendChild(form); form.submit();
        }
    });
}

document.getElementById('rangosForm')?.addEventListener('submit', function(e) {
    const rangos = document.querySelectorAll('#rangosTable tbody tr');
    let tieneRangoValido = false;
    for (let rango of rangos) {
        const desde = rango.querySelector('input[name*="[desde]"]')?.value;
        const tasa = rango.querySelector('input[name*="[tasa]"]')?.value;
        if (desde && parseFloat(desde) >= 0 && tasa && parseFloat(tasa) >= 0) { tieneRangoValido = true; break; }
    }
    if (!tieneRangoValido) { e.preventDefault(); Swal.fire({ title: '<i class="fas fa-exclamation-triangle me-2"></i> Error', text: 'Debe tener al menos un rango válido', icon: 'error', background: '#1a1a2e', color: '#fff' }); }
});

document.getElementById('btnBackupManualCard')?.addEventListener('click', (e) => { e.preventDefault(); realizarBackupManual(); });
document.getElementById('btnRestoreBackupCard')?.addEventListener('click', (e) => { e.preventDefault(); restaurarBackup(); });

// Gestión de Usuarios
let cropperUsuario = null, previewCanvasUsuario = null, previewCtxUsuario = null;

function validarNombreCampo(valor, feedbackId, minLength = 3) {
    const limpio = valor.trim();
    if (limpio.length === 0) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Obligatorio').show(); return false; }
    if (limpio.length < minLength) { $(feedbackId).html(`<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo ${minLength} caracteres`).show(); return false; }
    if (!/^[a-zA-ZáéíóúñÑüÜ\s]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Solo letras').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarCICubano(ci) {
    ci = ci.replace(/[\s-]/g, '');
    if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: '⚠️ Debe tener 11 dígitos' };
    const año = ci.substr(0,2), mes = ci.substr(2,2), dia = ci.substr(4,2);
    if (mes < '01' || mes > '12') return { valido: false, mensaje: '❌ Mes inválido' };
    const diasPorMes = [31,28,31,30,31,30,31,31,30,31,30,31];
    let maxDias = diasPorMes[parseInt(mes)-1];
    if (parseInt(mes) === 2) {
        const añoCompleto = parseInt(año) < 50 ? 2000+parseInt(año) : 1900+parseInt(año);
        if ((añoCompleto % 4 === 0 && añoCompleto % 100 !== 0) || (añoCompleto % 400 === 0)) maxDias = 29;
    }
    if (parseInt(dia) < 1 || parseInt(dia) > maxDias) return { valido: false, mensaje: `❌ Día inválido (máx ${maxDias})` };
    const genero = parseInt(ci.charAt(9)) % 2 === 0 ? 'Masculino' : 'Femenino';
    const icono = genero === 'Masculino' ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    const añoCompleto = parseInt(año) < 50 ? `20${año}` : `19${año}`;
    return { valido: true, mensaje: `<i class="fas fa-check-circle text-success me-1"></i> ${icono} ${genero} | ${dia}/${mes}/${añoCompleto}` };
}

$(document).ready(function() {
    $('#nombre').on('input', function() { validarNombreCampo($(this).val(), '#nombreFeedback', 3); });
    $('#primer_apellido').on('input', function() { validarNombreCampo($(this).val(), '#apellido1Feedback', 3); });
    $('#segundo_apellido').on('input', function() { validarNombreCampo($(this).val(), '#apellido2Feedback', 3); });
    $('#no_ci').on('input', function() {
        const resultado = validarCICubano($(this).val());
        $('#ciFeedbackUsuario').html(resultado.mensaje).css('color', resultado.valido ? '#28a745' : '#dc3545');
        $(this).css('border-color', resultado.valido ? '#28a745' : ($(this).val().length > 0 ? '#dc3545' : '')).css('background-color', resultado.valido ? 'rgba(40,167,69,0.1)' : ($(this).val().length > 0 ? 'rgba(220,53,69,0.1)' : ''));
    });
});

function cargarImagenUsuario(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5*1024*1024) { Swal.fire({ icon: 'error', title: 'Error', text: 'La imagen no debe superar los 5MB', background: '#1a1a2e', color: '#fff' }); input.value = ''; return; }
        if (!file.type.match('image.*')) { Swal.fire({ icon: 'error', title: 'Error', text: 'Solo imágenes', background: '#1a1a2e', color: '#fff' }); input.value = ''; return; }
        Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#fff' });
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageToCrop = document.getElementById('imageToCropUsuario');
            imageToCrop.src = e.target.result;
            imageToCrop.onload = function() {
                Swal.close();
                previewCanvasUsuario = document.getElementById('previewCanvasUsuario');
                previewCtxUsuario = previewCanvasUsuario.getContext('2d');
                if (cropperUsuario) cropperUsuario.destroy();
                cropperUsuario = new Cropper(imageToCrop, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 1, cropBoxResizable: true, cropBoxMovable: true, guides: true, center: true, highlight: true, background: true, responsive: true, zoomable: true, rotatable: true, scalable: true, wheelZoomRatio: 0.1, crop: function(event) { actualizarPreviewUsuario(); }, ready: function() { const containerData = cropperUsuario.getContainerData(); const cropBoxSize = Math.min(containerData.width, containerData.height)*0.8; cropperUsuario.setCropBoxData({ width: cropBoxSize, height: cropBoxSize, left: (containerData.width-cropBoxSize)/2, top: (containerData.height-cropBoxSize)/2 }); setTimeout(() => actualizarPreviewUsuario(), 100); } });
                new bootstrap.Modal(document.getElementById('cropModalUsuario')).show();
            };
        };
        reader.readAsDataURL(file);
    }
}

function actualizarPreviewUsuario() {
    if (!cropperUsuario || !previewCtxUsuario) return;
    const canvas = cropperUsuario.getCroppedCanvas({ width: 150, height: 150 });
    if (canvas) previewCtxUsuario.drawImage(canvas, 0, 0, 150, 150);
}

function rotarImagenUsuario(deg) { if(cropperUsuario) cropperUsuario.rotate(deg); }
function resetearCropUsuario() { if(cropperUsuario) { cropperUsuario.reset(); setTimeout(() => actualizarPreviewUsuario(), 50); } }
function zoomImagenUsuario(factor) { if(cropperUsuario) cropperUsuario.zoom(factor); setTimeout(() => actualizarPreviewUsuario(), 50); }
function limpiarCropperUsuario() { if(cropperUsuario){ cropperUsuario.destroy(); cropperUsuario=null; } document.getElementById('imageUploadUsuario').value=''; }

function aplicarRecorteUsuario() {
    if(cropperUsuario) {
        const b64 = cropperUsuario.getCroppedCanvas({width:500,height:500}).toDataURL('image/jpeg',0.9);
        document.getElementById('imagen_recortada_usuario').value = b64;
        const imagePreview = document.getElementById('imagePreviewUsuario');
        const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
        const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
        if (imagePreview) { imagePreview.src = b64; imagePreview.style.display = 'block'; imagePreview.style.objectFit = 'cover'; imagePreview.style.padding = '0'; }
        if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
        if (btnEliminarFoto) btnEliminarFoto.style.display = 'inline-flex';
        document.getElementById('eliminarFotoUsuario').value = '0';
        bootstrap.Modal.getInstance(document.getElementById('cropModalUsuario')).hide();
        limpiarCropperUsuario();
    }
}

function eliminarFotoUsuario() {
    Swal.fire({
        title: '<i class="fas fa-trash-alt text-danger me-2"></i> Eliminar foto',
        text: '¿Está seguro que desea eliminar la foto de perfil?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('imagePreviewUsuario').src = '<?php echo $logo_base64; ?>';
            document.getElementById('imagePreviewUsuario').style.objectFit = 'contain';
            document.getElementById('imagePreviewUsuario').style.padding = '20px';
            document.getElementById('fotoPlaceholderUsuario').style.display = 'none';
            document.getElementById('btnEliminarFotoUsuario').style.display = 'none';
            document.getElementById('eliminarFotoUsuario').value = '1';
            document.getElementById('imagen_recortada_usuario').value = '';
            Swal.fire({ title: 'Foto eliminada', text: 'Se ha eliminado la foto', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1a1a2e', color: '#fff' });
        }
    });
}

function abrirEditorFotoUsuario() { document.getElementById('imageUploadUsuario').click(); }

function limpiarFormularioUsuario() {
    document.getElementById('usuarioAction').value = 'crear';
    document.getElementById('usuarioId').value = '';
    document.getElementById('modalUsuarioTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Usuario';
    document.getElementById('formUsuario').reset();
    document.getElementById('activo_usuario').checked = true;
    document.getElementById('passRequired').innerHTML = '*';
    document.getElementById('passHelp').style.display = 'block';
    
    const imagePreview = document.getElementById('imagePreviewUsuario');
    const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
    const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
    
    if (imagePreview) {
        imagePreview.src = '<?php echo $logo_base64; ?>';
        imagePreview.style.display = 'block';
        imagePreview.style.objectFit = 'contain';
        imagePreview.style.padding = '20px';
        imagePreview.style.borderRadius = '50%';
    }
    if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
    if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
    
    document.getElementById('eliminarFotoUsuario').value = '0';
    document.getElementById('imagen_recortada_usuario').value = '';
    
    $('#nombreFeedback, #apellido1Feedback, #apellido2Feedback, #ciFeedbackUsuario').html('');
    $('#no_ci').css('border-color', '').css('background-color', '');
    setTimeout(() => $('#usuario').focus(), 100);
}

function editarUsuario(id) {
    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#fff' });
    fetch('../ajax/get_usuario.php?id=' + id)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            document.getElementById('usuarioAction').value = 'editar';
            document.getElementById('usuarioId').value = data.usuario.id;
            document.getElementById('modalUsuarioTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i> Editar Usuario: ' + data.usuario.usuario;
            document.getElementById('usuario').value = data.usuario.usuario;
            document.getElementById('nombre').value = data.usuario.nombre;
            document.getElementById('primer_apellido').value = data.usuario.apellidos?.split(' ')[0] || '';
            document.getElementById('segundo_apellido').value = data.usuario.apellidos?.split(' ')[1] || '';
            document.getElementById('no_ci').value = data.usuario.no_ci;
            document.getElementById('email').value = data.usuario.email || '';
            document.getElementById('rol_id').value = data.usuario.rol_id;
            document.getElementById('activo_usuario').checked = data.usuario.activo == 1;
            document.getElementById('password').value = '';
            document.getElementById('passRequired').innerHTML = '';
            document.getElementById('passHelp').style.display = 'none';
            
            if (data.usuario.foto && data.usuario.foto !== '') {
                let fotoUrl = data.usuario.foto;
                if (fotoUrl.startsWith('assets/')) {
                    fotoUrl = '../' + fotoUrl;
                }
                const img = new Image();
                img.onload = function() {
                    const imagePreview = document.getElementById('imagePreviewUsuario');
                    const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
                    const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
                    if (imagePreview) {
                        imagePreview.src = fotoUrl;
                        imagePreview.style.display = 'block';
                        imagePreview.style.objectFit = 'cover';
                        imagePreview.style.padding = '0';
                        imagePreview.style.borderRadius = '50%';
                    }
                    if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
                    if (btnEliminarFoto) {
                        btnEliminarFoto.style.display = 'inline-flex';
                        btnEliminarFoto.disabled = false;
                    }
                    document.getElementById('eliminarFotoUsuario').value = '0';
                };
                img.onerror = function() {
                    const imagePreview = document.getElementById('imagePreviewUsuario');
                    if (imagePreview) {
                        imagePreview.src = '<?php echo $logo_base64; ?>';
                        imagePreview.style.objectFit = 'contain';
                        imagePreview.style.padding = '20px';
                    }
                };
                img.src = fotoUrl;
            } else {
                const imagePreview = document.getElementById('imagePreviewUsuario');
                if (imagePreview) {
                    imagePreview.src = '<?php echo $logo_base64; ?>';
                    imagePreview.style.objectFit = 'contain';
                    imagePreview.style.padding = '20px';
                }
            }
            
            new bootstrap.Modal(document.getElementById('modalUsuario')).show();
            $('#usuario').focus();
        } else { 
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, background: '#1a1a2e', color: '#fff' }); 
        }
    })
    .catch(() => { Swal.close(); Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el usuario', background: '#1a1a2e', color: '#fff' }); });
}

// ==========================================
// TOGGLE ESTADO USUARIO - CON VALIDACIÓN DE USUARIO ACTUAL
// ==========================================
function toggleEstadoUsuario(id, estadoActual, nombre) {
    if (id == usuarioActualId) {
        Swal.fire({
            title: '<i class="fas fa-user-shield me-2" style="color: #f59e0b;"></i> Acción no permitida',
            html: `<div class="text-start"><p><strong>No puede cambiar su propio estado.</strong></p><div class="alert alert-warning p-3 mt-2" style="background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; border-radius: 8px;"><i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> No puede desactivar su propia cuenta mientras está logueado.</div><p class="text-muted small mt-2">Si necesita desactivar su usuario, contacte a otro administrador.</p></div>`,
            icon: 'warning', confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido', confirmButtonColor: '#f59e0b', background: '#1a1a2e', color: '#ffffff'
        });
        return;
    }
    
    const nuevoEstado = estadoActual ? 0 : 1;
    const accion = estadoActual ? 'desactivar' : 'activar';
    const icono = estadoActual ? 'fa-user-slash' : 'fa-user-check';
    const color = estadoActual ? '#dc3545' : '#10b981';
    
    Swal.fire({
        title: `<i class="fas ${icono} me-2"></i> ${estadoActual ? 'Desactivar' : 'Activar'} usuario`,
        html: `<p>¿Está seguro de ${accion} a <strong>${nombre}</strong>?</p>`,
        icon: 'question', showCancelButton: true, confirmButtonColor: color,
        confirmButtonText: `<i class="fas ${icono} me-2"></i> Sí, ${accion}`,
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e', color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#fff' });
            fetch('../ajax/toggle_usuario.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&estado=${nuevoEstado}` })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    const row = document.querySelector(`#tablaUsuariosBody tr[data-id="${id}"]`);
                    if (row) {
                        row.setAttribute('data-estado', nuevoEstado);
                        const estadoBadge = row.querySelector('td:nth-child(7) .badge');
                        const toggleBtn = row.querySelector('td:last-child .btn-win-warning, td:last-child .btn-win-success');
                        if (estadoBadge) { estadoBadge.textContent = nuevoEstado ? 'Activo' : 'Inactivo'; estadoBadge.className = `badge ${nuevoEstado ? 'bg-success' : 'bg-danger'}`; }
                        if (toggleBtn) { toggleBtn.className = `btn-win btn-win-sm ${nuevoEstado ? 'btn-win-warning' : 'btn-win-success'}`; toggleBtn.innerHTML = `<i class="fas ${nuevoEstado ? 'fa-user-slash' : 'fa-user-check'}"></i>`; toggleBtn.setAttribute('onclick', `toggleEstadoUsuario(${id}, ${nuevoEstado}, '${nombre}')`); }
                    }
                    actualizarEstadisticasUsuarios();
                    Swal.fire({ icon: 'success', title: '<i class="fas fa-check-circle me-2"></i> Completado', text: data.message, timer: 1500, showConfirmButton: false, background: '#1a1a2e', color: '#fff' });
                } else { Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message, background: '#1a1a2e', color: '#fff' }); }
            });
        }
    });
}

// ==========================================
// ELIMINAR USUARIO - CON VALIDACIÓN DE USUARIO ACTUAL
// ==========================================
function eliminarUsuario(id, nombre) {
    if (id == usuarioActualId) {
        Swal.fire({
            title: '<i class="fas fa-user-shield me-2" style="color: #dc3545;"></i> Acción no permitida',
            html: `<div class="text-start"><p><strong>No puede eliminar su propia cuenta.</strong></p><div class="alert alert-danger p-3 mt-2" style="background: rgba(220, 53, 69, 0.15); border: 1px solid #dc3545; border-radius: 8px;"><i class="fas fa-exclamation-triangle me-2"></i> No puede eliminar su propio usuario mientras está logueado en el sistema.</div><p class="text-muted small mt-2">Si necesita eliminar su cuenta, contacte a otro administrador del sistema.</p></div>`,
            icon: 'error', confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido', confirmButtonColor: '#dc3545', background: '#1a1a2e', color: '#ffffff'
        });
        return;
    }
    
    Swal.fire({
        title: '<i class="fas fa-trash-alt text-danger me-2"></i> Eliminar usuario',
        html: `<p>Esta acción no se puede deshacer: <strong>${nombre}</strong></p><p class="text-warning small mt-2"><i class="fas fa-exclamation-triangle me-1"></i> Se eliminarán todos los datos asociados a este usuario.</p>`,
        icon: 'warning', showCancelButton: true, showDenyButton: true, confirmButtonColor: '#dc3545', denyButtonColor: '#f59e0b',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí, eliminar', denyButtonText: '<i class="fas fa-user-slash me-2"></i> Mejor desactivar', cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e', color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#fff' });
            fetch('../ajax/eliminar_usuario.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}` })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    const row = document.querySelector(`#tablaUsuariosBody tr[data-id="${id}"]`);
                    if (row) row.remove();
                    actualizarEstadisticasUsuarios();
                    Swal.fire({ icon: 'success', title: '<i class="fas fa-check-circle me-2"></i> Eliminado', text: data.message, timer: 1500, showConfirmButton: false, background: '#1a1a2e', color: '#fff' });
                } else { Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message, background: '#1a1a2e', color: '#fff' }); }
            });
        } else if (result.isDenied) {
            toggleEstadoUsuario(id, true, nombre);
        }
    });
}

function filtrarUsuarios() {
    const searchTerm = document.getElementById('searchUsuarioInput').value.toLowerCase();
    const filterRol = document.getElementById('filterRolInput').value;
    const filterEstado = document.getElementById('filterEstadoInput').value;
    const rows = document.querySelectorAll('#tablaUsuariosBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase(), rol = row.getAttribute('data-rol'), estado = row.getAttribute('data-estado');
        if ((searchTerm === '' || text.includes(searchTerm)) && (filterRol === '' || rol === filterRol) && (filterEstado === '' || estado === filterEstado)) row.style.display = '';
        else row.style.display = 'none';
    });
    actualizarEstadisticasUsuarios();
}

function actualizarEstadisticasUsuarios() {
    const rows = document.querySelectorAll('#tablaUsuariosBody tr');
    let total = 0, activos = 0;
    const roles = new Set();
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            total++;
            if (row.getAttribute('data-estado') == '1') activos++;
            const rol = row.getAttribute('data-rol');
            if (rol) roles.add(rol);
        }
    });
    document.getElementById('totalUsuariosCount').textContent = total;
    document.getElementById('usuariosActivosCount').textContent = activos;
    document.getElementById('rolesCount').textContent = roles.size;
}

document.getElementById('formUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const usuario = document.getElementById('usuario').value.trim();
    const nombre = document.getElementById('nombre').value.trim();
    const primerApellido = document.getElementById('primer_apellido').value.trim();
    const segundoApellido = document.getElementById('segundo_apellido').value.trim();
    const ci = document.getElementById('no_ci').value.replace(/\D/g, '');
    const rolId = document.getElementById('rol_id').value;
    const action = document.getElementById('usuarioAction').value;
    const password = document.getElementById('password').value;
    
    if (!usuario) { Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'El nombre de usuario es obligatorio', background: '#1a1a2e', color: '#fff' }); $('#usuario').focus(); return; }
    if (!nombre || nombre.length < 3) { Swal.fire({ icon: 'warning', title: 'Nombre inválido', text: 'Mínimo 3 caracteres', background: '#1a1a2e', color: '#fff' }); $('#nombre').focus(); return; }
    if (!primerApellido || primerApellido.length < 3) { Swal.fire({ icon: 'warning', title: 'Primer apellido inválido', text: 'Mínimo 3 caracteres', background: '#1a1a2e', color: '#fff' }); $('#primer_apellido').focus(); return; }
    if (!segundoApellido || segundoApellido.length < 3) { Swal.fire({ icon: 'warning', title: 'Segundo apellido inválido', text: 'Mínimo 3 caracteres', background: '#1a1a2e', color: '#fff' }); $('#segundo_apellido').focus(); return; }
    if (ci.length !== 11) { Swal.fire({ icon: 'warning', title: 'CI inválido', text: 'Debe tener 11 dígitos', background: '#1a1a2e', color: '#fff' }); $('#no_ci').focus(); return; }
    if (!rolId) { Swal.fire({ icon: 'warning', title: 'Selección requerida', text: 'Debe seleccionar un rol', background: '#1a1a2e', color: '#fff' }); $('#rol_id').focus(); return; }
    if (action === 'crear' && !password) { Swal.fire({ icon: 'warning', title: 'Contraseña requerida', text: 'La contraseña es obligatoria', background: '#1a1a2e', color: '#fff' }); $('#password').focus(); return; }
    
    const ciValidacion = validarCICubano(ci);
    if (!ciValidacion.valido) { Swal.fire({ icon: 'warning', title: 'CI inválido', text: ciValidacion.mensaje, background: '#1a1a2e', color: '#fff' }); $('#no_ci').focus(); return; }
    
    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#fff' });
    fetch('../ajax/guardar_usuario.php', { method: 'POST', body: new FormData(this) })
    .then(response => response.json())
    .then(data => {
        if (data.success) { Swal.fire({ icon: 'success', title: '<i class="fas fa-check-circle me-2"></i> Completado', text: data.message, timer: 1500, showConfirmButton: false, background: '#1a1a2e', color: '#fff' }); setTimeout(() => location.reload(), 1500); }
        else { Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message, background: '#1a1a2e', color: '#fff' }); }
    })
    .catch(() => { Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión', background: '#1a1a2e', color: '#fff' }); });
});

document.getElementById('searchUsuarioInput')?.addEventListener('input', filtrarUsuarios);
document.getElementById('filterRolInput')?.addEventListener('change', filtrarUsuarios);
document.getElementById('filterEstadoInput')?.addEventListener('change', filtrarUsuarios);
</script>

</body>
</html>