<?php
// bloquear_sesion.php
require_once 'config/header.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Guardar datos de sesión actuales para restaurar después
$_SESSION['sesion_bloqueada'] = true;
$_SESSION['sesion_bloqueada_tiempo'] = time();

// Obtener información del usuario CON LA FOTO
$db = Database::getConnection();
$sql_usuario = "SELECT u.*, r.descripcion as rol_nombre 
                FROM clasif_usuarios u
                LEFT JOIN clasif_rol r ON u.rol_id = r.id
                WHERE u.id = :id";
$stmt_usuario = $db->prepare($sql_usuario);
$stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

// Determinar si tiene foto y preparar la URL/base64
$foto_usuario = '';
$iniciales_usuario = strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1));

if (!empty($usuario['foto'])) {
    // Verificar si la foto está en formato base64 o es una ruta
    if (strpos($usuario['foto'], 'data:image') === 0) {
        // Ya está en base64
        $foto_usuario = $usuario['foto'];
    } else {
        // Es una ruta de archivo, convertir a base64
        $ruta_foto = $usuario['foto'];
        if (file_exists($ruta_foto)) {
            $tipo = mime_content_type($ruta_foto);
            $datos = base64_encode(file_get_contents($ruta_foto));
            $foto_usuario = 'data:' . $tipo . ';base64,' . $datos;
        }
    }
}

// Obtener configuración del tema
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión Bloqueada - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
<style>
        :root {
            --win-bg-primary: <?php echo $tema_windows == 'dark' ? '#0d0d0d' : '#f3f3f3'; ?>;
            --win-bg-secondary: <?php echo $tema_windows == 'dark' ? '#1f1f1f' : '#ffffff'; ?>;
            --win-text-primary: <?php echo $tema_windows == 'dark' ? '#ffffff' : '#000000'; ?>;
            --win-text-secondary: <?php echo $tema_windows == 'dark' ? '#a6a6a6' : '#666666'; ?>;
            --win-accent: <?php echo $color_accent; ?>;
            --win-accent-light: <?php echo $color_accent; ?>20;
        }

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
            position: relative;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        /* Logo de fondo moviéndose */
        .logo-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.03;
            z-index: 0;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 200px; /* Tamaño del logo repetido */
            animation: moveBackground 60s linear infinite;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        @keyframes moveBackground {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 400px 400px; /* Mueve el fondo */
            }
        }

        .lock-screen {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
            overflow-y: auto;
            z-index: 1;
            padding: 20px;
            box-sizing: border-box;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
        }

        .lock-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px); /* Reducido de 20px a 10px */
            -webkit-backdrop-filter: blur(10px); /* Reducido de 20px a 10px */
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 30px; /* Reducido de 40px */
            width: 100%;
            max-width: 580px; /* Reducido de 620px */
            text-align: center;
            z-index: 1;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25); /* Reducida intensidad */
            animation: fadeIn 0.5s ease-out;
            box-sizing: border-box;
            overflow: visible;
            margin: 20px auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transform: translateZ(0); /* Mejora renderizado */
            will-change: transform;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Contenedor de usuario con foto y candado */
        .user-lock-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 25px; /* Reducido de 30px */
            margin-bottom: 25px; /* Reducido de 30px */
            position: relative;
        }

        .user-avatar-large {
            width: 150px; /* Reducido de 180px */
            height: 150px; /* Reducido de 180px */
            background: var(--win-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px; /* Reducido de 48px */
            font-weight: 600;
            border: 5px solid rgba(255, 255, 255, 0.2); /* Reducido de 6px */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); /* Reducida intensidad */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            transition: transform 0.3s ease;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .user-avatar-large:hover {
            transform: scale(1.03); /* Reducido de 1.05 */
        }

        .user-avatar-large.has-photo {
            background-color: transparent;
            color: transparent;
        }

        /* Candado grande al lado */
        .lock-side {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px; /* Reducido de 15px */
        }

        .lock-icon-large {
            font-size: 80px; /* Reducido de 100px */
            color: var(--win-accent);
            animation: pulse 2s infinite;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.25); /* Reducida intensidad */
        }

        .lock-text {
            color: var(--win-text-secondary);
            font-size: 16px; /* Reducido de 18px */
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px; /* Reducido de 1px */
        }

        @keyframes pulse {
            0% { 
                transform: scale(1) rotate(0deg); 
                text-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            }
            50% { 
                transform: scale(1.05) rotate(3deg); /* Reducido de 1.1 y 5deg */
                text-shadow: 0 8px 20px rgba(var(--win-accent-rgb, 0, 120, 212), 0.3);
            }
            100% { 
                transform: scale(1) rotate(0deg); 
                text-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            }
        }

        .user-info {
            margin-top: 8px; /* Reducido de 10px */
        }

        .user-info h2 {
            margin: 0 0 4px 0; /* Reducido de 5px */
            color: var(--win-text-primary);
            font-weight: 600;
            font-size: 17px; /* Reducido de 18px */
        }

        .user-info p {
            color: var(--win-text-secondary);
            margin-bottom: 0px;
            font-size: 15px; /* Reducido de 16px */
            opacity: 0.9;
        }

        .time-display {
            font-size: 42px; /* Reducido de 46px */
            font-weight: 300;
            color: var(--win-text-primary);
            margin-bottom: 0px;
            font-variant-numeric: tabular-nums;
            text-shadow: 0 1px 8px rgba(0, 0, 0, 0.15); /* Reducida intensidad */
            letter-spacing: -0.5px;
        }

        .date-display {
            font-size: 18px; /* Reducido de 20px */
            color: var(--win-text-secondary);
            margin-bottom: 8px; /* Reducido de 10px */
            opacity: 0.8;
        }

        .unlock-form {
            width: 100%;
            max-width: 380px; /* Reducido de 400px */
            margin: 0 auto;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.08); /* Reducida transparencia */
            border: 1px solid rgba(255, 255, 255, 0.15); /* Reducida transparencia */
            color: var(--win-text-primary);
            border-radius: 10px; /* Reducido de 12px */
            padding: 9px 14px; /* Reducido */
            font-size: 15px; /* Reducido de 16px */
            transition: all 0.3s ease;
            backdrop-filter: blur(5px); /* Reducido de 10px */
            -webkit-backdrop-filter: blur(5px);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--win-accent);
            color: var(--win-text-primary);
            box-shadow: 0 0 0 2px var(--win-accent-light); /* Reducido de 3px */
            transform: translateY(-1px); /* Reducido de -2px */
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4); /* Aumentado contraste */
        }

        .btn-unlock {
            background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 80%, black));
            border: none;
            border-radius: 10px; /* Reducido de 12px */
            padding: 14px; /* Cambiado de 6px a 14px */
            font-size: 16px; /* Reducido de 18px */
            font-weight: 600;
            color: white;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18); /* Reducida intensidad */
            position: relative;
            overflow: hidden;
        }

        .btn-unlock:hover {
            transform: translateY(-2px); /* Reducido de -3px */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25); /* Reducida intensidad */
        }

        .btn-unlock:active {
            transform: translateY(0);
        }

        .btn-unlock i {
            margin-right: 8px; /* Reducido de 10px */
        }

        .btn-unlock::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent); /* Reducida intensidad */
            transition: left 0.5s;
        }

        .btn-unlock:hover::after {
            left: 100%;
        }

        .system-info {
            position: absolute;
            bottom: 25px; /* Reducido de 30px */
            left: 0;
            right: 0;
            text-align: center;
            color: var(--win-text-secondary);
            font-size: 16px; /* Reducido de 18px */
            z-index: 2;
            opacity: 0.9;
        }

        .session-time {
            position: absolute;
            top: 25px; /* Reducido de 30px */
            right: 25px; /* Reducido de 30px */
            background: rgba(255, 255, 255, 0.08); /* Reducida transparencia */
            padding: 8px 16px; /* Reducido */
            border-radius: 18px; /* Reducido de 20px */
            font-size: 16px; /* Reducido de 18px */
            font-weight: 600;
            color: var(--win-text-secondary);
            backdrop-filter: blur(5px); /* Reducido de 10px */
            -webkit-backdrop-filter: blur(5px);
            z-index: 2;
            border: 1px solid rgba(255, 255, 255, 0.08); /* Reducida transparencia */
        }

        .error-message {
            color: #ff6b6b;
            font-size: 13px; /* Reducido de 14px */
            margin-top: 5px;
            display: none;
            background: rgba(255, 107, 107, 0.08); /* Reducida transparencia */
            padding: 8px; /* Reducido de 10px */
            border-radius: 6px; /* Reducido de 8px */
            border: 1px solid rgba(255, 107, 107, 0.15); /* Reducida transparencia */
        }

        .input-group {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px; /* Reducido de 15px */
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--win-text-secondary);
            cursor: pointer;
            z-index: 10;
            padding: 4px; /* Reducido de 5px */
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-password:hover {
            background: rgba(255, 255, 255, 0.08); /* Reducida transparencia */
            color: var(--win-accent);
        }

        /* Efectos de fondo adicionales */
        .bg-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .bg-particle {
            position: absolute;
            background: var(--win-accent);
            border-radius: 50%;
            opacity: 0.04; /* Reducido de 0.05 */
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-1000px) rotate(720deg); }
        }

        /* Efecto brillo en la foto */
        .user-avatar-large::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 70%); /* Reducida intensidad */
            pointer-events: none;
            z-index: 1;
        }

        /* Responsive general */
        @media (max-width: 768px) {
            .lock-screen {
                padding: 12px; /* Reducido de 15px */
            }
            
            .lock-container {
                max-width: 96%; /* Aumentado de 95% */
                padding: 22px 12px; /* Reducido */
                margin: 8px auto; /* Reducido de 10px */
                backdrop-filter: blur(8px); /* Reducido para móvil */
                -webkit-backdrop-filter: blur(8px);
            }
            
            .user-lock-container {
                flex-direction: column;
                gap: 12px; /* Reducido de 15px */
            }
            
            .user-avatar-large {
                width: 110px; /* Reducido de 130px */
                height: 110px;
                font-size: 32px; /* Reducido de 36px */
            }
            
            .lock-icon-large {
                font-size: 60px; /* Reducido de 70px */
            }
            
            .time-display {
                font-size: 38px; /* Reducido de 42px */
            }
            
            .system-info {
                position: relative;
                margin-top: 16px; /* Reducido de 20px */
                bottom: auto;
                font-size: 15px; /* Reducido de 16px */
            }
        }

        @media (max-width: 480px) {
            .user-avatar-large {
                width: 100px; /* Reducido de 120px */
                height: 100px;
                font-size: 28px; /* Reducido de 32px */
            }
            
            .lock-icon-large {
                font-size: 45px; /* Reducido de 50px */
            }
            
            .time-display {
                font-size: 36px; /* Reducido de 40px */
            }
            
            .session-time {
                top: 15px; /* Reducido de 20px */
                right: 15px;
                padding: 6px 12px; /* Reducido */
                font-size: 11px; /* Reducido de 12px */
            }
        }

        /* Agregar después de los estilos del .btn-unlock */
        .btn-logout {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.15); /* Reducida transparencia */
            border-radius: 10px; /* Reducido de 12px */
            padding: 14px; /* Cambiado de 4px a 14px */
            font-size: 15px; /* Reducido de 16px */
            font-weight: 500;
            color: var(--win-text-secondary);
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            backdrop-filter: blur(5px); /* Reducido de 10px */
            -webkit-backdrop-filter: blur(5px);
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.08); /* Reducida transparencia */
            color: #ff6b6b;
            border-color: rgba(255, 107, 107, 0.25); /* Reducida transparencia */
            transform: translateY(-1px); /* Reducido de -2px */
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.08); /* Reducida intensidad */
        }

        .btn-logout:active {
            transform: translateY(0);
        }

        .btn-logout i {
            margin-right: 8px; /* Reducido de 10px */
        }
        
        .unlock-instructions {
            width: 100%;
            max-width: 460px; /* Reducido de 500px */
            margin: 0 auto 0px auto;
            animation: fadeIn 0.8s ease-out 0.3s both;
        }

        .unlock-instructions .alert {
            background: rgba(var(--win-accent-rgb, 0, 120, 212), 0.08); /* Reducida transparencia */
            border: 1px solid rgba(var(--win-accent-rgb, 0, 120, 212), 0.15); /* Reducida transparencia */
            border-radius: 10px; /* Reducido de 12px */
            color: var(--win-text-primary);
            backdrop-filter: blur(5px); /* Reducido de 10px */
            -webkit-backdrop-filter: blur(5px);
            padding: 12px; /* Reducido de 15px */
        }

        .unlock-instructions .alert-info {
            background: rgba(13, 110, 253, 0.08); /* Reducida transparencia */
            border-color: rgba(13, 110, 253, 0.15); /* Reducida transparencia */
        }

        .unlock-instructions .alert-heading {
            color: var(--win-accent);
            font-size: 14px; /* Reducido de 15px */
            font-weight: 600;
        }

        .unlock-instructions p {
            color: var(--win-text-secondary);
            font-size: 12px; /* Reducido de 13px */
            line-height: 1.4; /* Ajustado de .4 */
        }

        .unlock-instructions i {
            color: var(--win-accent);
            font-size: 16px; /* Reducido de 18px */
        }

        /* Estilos para versión elegante */
        .button-row.elegant-buttons {
            display: flex;
            align-items: center;
            gap: 0;
            margin-top: 22px; /* Reducido de 25px */
        }

        .button-row.elegant-buttons .elegant-btn {
            flex: 1;
            padding: 14px; /* Reducido de 16px */
            border-radius: 10px; /* Reducido de 12px */
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .button-row.elegant-buttons .btn-unlock {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 80%, black));
            color: white;
            border: none;
        }

        .button-row.elegant-buttons .btn-logout {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            background: rgba(255, 107, 107, 0.08); /* Reducida transparencia */
            border: 1px solid rgba(255, 107, 107, 0.25); /* Reducida transparencia */
            color: #ff6b6b;
        }

        .button-row.elegant-buttons .btn-unlock:hover {
            transform: translateY(-1px); /* Reducido de -2px */
            box-shadow: 0 6px 18px rgba(var(--win-accent-rgb, 0, 120, 212), 0.25); /* Reducida intensidad */
        }

        .button-row.elegant-buttons .btn-logout:hover {
            background: rgba(255, 107, 107, 0.15); /* Reducida transparencia */
            transform: translateY(-1px); /* Reducido de -2px */
            box-shadow: 0 6px 18px rgba(255, 107, 107, 0.15); /* Reducida intensidad */
        }

        .button-divider {
            padding: 0 12px; /* Reducido de 15px */
            color: var(--win-text-secondary);
            font-size: 13px; /* Reducido de 14px */
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .button-row.elegant-buttons {
                flex-direction: column;
                gap: 8px; /* Reducido de 10px */
            }
            
            .button-row.elegant-buttons .elegant-btn {
                width: 100%;
                border-radius: 10px !important; /* Reducido de 12px */
            }
            
            .button-divider {
                display: none;
            }
        }

        /* MEDIA QUERIES ESPECÍFICAS PARA 1024x768 - ELIMINADO EL ESCALADO */
        @media (max-height: 768px) and (max-width: 1024px) {
            .lock-screen {
                padding: 15px; /* Reducido de 20px */
                justify-content: center; /* Cambiado de flex-start */
                overflow-y: hidden;
            }
            
            .lock-container {
                max-width: 85%; /* Reducido de 90% */
                max-height: 85vh;
                margin: 0 auto; /* Eliminado margin-top/bottom */
                padding: 25px 18px; /* Reducido */
                /* ELIMINADO: transform: scale(0.9); */
                backdrop-filter: blur(8px); /* Reducido para pantalla pequeña */
                -webkit-backdrop-filter: blur(8px);
            }
            
            .user-lock-container {
                flex-direction: column;
                gap: 16px; /* Reducido de 20px */
            }
            
            .user-avatar-large {
                width: 100px; /* Reducido de 120px */
                height: 100px;
                font-size: 28px; /* Reducido de 32px */
                border-width: 4px;
            }
            
            .lock-icon-large {
                font-size: 50px; /* Reducido de 60px */
            }
            
            .lock-text {
                font-size: 13px; /* Reducido de 14px */
            }
            
            .time-display {
                font-size: 34px; /* Reducido de 36px */
                margin-bottom: 3px; /* Reducido de 5px */
            }
            
            .date-display {
                font-size: 15px; /* Reducido de 16px */
                margin-bottom: 12px; /* Reducido de 15px */
            }
            
            .user-info h2 {
                font-size: 15px; /* Reducido de 16px */
                margin-bottom: 2px; /* Reducido de 3px */
            }
            
            .user-info p {
                font-size: 13px; /* Reducido de 14px */
            }
            
            .unlock-instructions {
                max-width: 100%;
                margin-bottom: 12px; /* Reducido de 15px */
            }
            
            .unlock-instructions .alert {
                padding: 10px; /* Reducido de 12px */
            }
            
            .unlock-instructions .alert-heading {
                font-size: 12px; /* Reducido de 13px */
            }
            
            .unlock-instructions p {
                font-size: 11px; /* Reducido de 12px */
                line-height: 1.3;
            }
            
            .form-control {
                padding: 7px 10px; /* Reducido */
                font-size: 13px; /* Reducido de 14px */
            }
            
            .btn-unlock, .btn-logout {
                padding: 12px; /* Reducido de 10px (más consistente) */
                font-size: 13px; /* Reducido de 14px */
            }
            
            .system-info {
                position: relative;
                bottom: auto;
                margin-top: 16px; /* Reducido de 20px */
                font-size: 13px; /* Reducido de 14px */
            }
            
            .session-time {
                position: fixed;
                top: 8px; /* Reducido de 10px */
                right: 8px;
                padding: 6px 12px; /* Reducido */
                font-size: 11px; /* Reducido de 12px */
            }
        }

        /* ELIMINADO TODOS LOS MEDIA QUERIES CON TRANSFORM: SCALE() */
        /* @media (max-height: 700px) { ... } */
        /* @media (max-height: 600px) { ... } */
        /* @media (max-height: 500px) { ... } */

        /* Ajustes específicos para 1024x768 en orientación horizontal */
        @media (max-width: 1024px) and (orientation: landscape) {
            .lock-screen {
                flex-direction: row;
                padding: 8px; /* Reducido de 10px */
            }
            
            .lock-container {
                max-width: 92%; /* Reducido de 95% */
                max-height: 92vh; /* Reducido de 95vh */
                margin: auto;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 20px; /* Reducido */
                backdrop-filter: blur(8px); /* Reducido */
                -webkit-backdrop-filter: blur(8px);
            }
            
            .user-lock-container {
                flex-direction: row;
                gap: 20px; /* Reducido de 30px */
                margin-bottom: 20px; /* Reducido */
            }
            
            .user-avatar-large {
                width: 80px; /* Reducido de 100px */
                height: 80px;
                font-size: 24px; /* Reducido */
            }
            
            .lock-icon-large {
                font-size: 40px; /* Reducido de 50px */
            }
            
            .time-display {
                font-size: 32px; /* Reducido */
            }
        }

        /* Optimizaciones de renderizado */
        .lock-container * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
</style>

</head>
<body>
    <!-- Logo de fondo moviéndose -->
    <div class="logo-background"></div>
    
    <!-- Partículas de fondo -->
    <div class="bg-particles" id="bgParticles"></div>
    
    <!-- Tiempo de sesión bloqueada -->
    <div class="session-time" id="sessionTimer">
        <i class="fas fa-clock me-2"></i>
        <span id="timerDisplay">00:00</span>
    </div>
    
    <div class="lock-screen">
        <div class="lock-container">
            <!-- Contenedor con foto de usuario y candado -->
            <div class="user-lock-container">
                <!-- Foto grande del usuario -->
                <div class="user-avatar-large <?php echo !empty($foto_usuario) ? 'has-photo' : ''; ?>" 
                     id="userAvatar"
                     style="<?php echo !empty($foto_usuario) ? 'background-image: url(\'' . htmlspecialchars($foto_usuario) . '\')' : ''; ?>">
                    <?php if (empty($foto_usuario)): ?>
                        <?php echo $iniciales_usuario; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Candado grande al lado -->
                <div class="lock-side">
                    <div class="lock-icon-large">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="lock-text fw-bold text-primary">
                        Sesión Bloqueada
                    </div>
                </div>
            </div>
            
            <!-- Información del usuario -->
            <div class="user-info">
				<h5>Usuario:</h5>
                <span><h2 class="text-success" id="userName"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></h2></span>
                <p id="userRole"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></p>
            </div>
            
            <!-- Hora y fecha actual -->
            <div class="time-display" id="currentTime">00:00:00</div>
            <div class="date-display" id="currentDate">Lunes, 1 de enero de 2025</div>

<div class="unlock-instructions">
    <div class="alert alert-info" role="alert">
        <div class="d-flex align-items-start">
            <i class="fas fa-info-circle me-3 mt-1"></i>
            <div>
                <h6 class="alert-heading mb-2">Ingrese sus credenciales para desbloquear la sesión</h6>
                <p class="mb-0 small">
                    A los <span class="fw-bold">10:00</span> minutos se cerrará automáticament.
                </p>
            </div>
        </div>
    </div>
</div>

		<!-- Formulario de desbloqueo -->
		<form class="unlock-form" id="unlockForm" novalidate>
			<div class="input-group">
				<input type="password" 
					   class="form-control" 
					   id="passwordInput"
					   placeholder="Ingresa tu contraseña para desbloquear"
					   required
					   autocomplete="current-password">
				<button type="button" class="toggle-password" id="togglePassword">
					<i class="fas fa-eye"></i>
				</button>
			</div>
			
			<div class="error-message" id="errorMessage">
				<i class="fas fa-exclamation-circle me-2"></i>
				<span id="errorText">Contraseña incorrecta</span>
			</div>
    
				<!-- Contenedor para ambos botones en la misma fila -->
<div class="button-row elegant-buttons">
    <button type="submit" class="btn-unlock elegant-btn">
        <i class="fas fa-unlock me-2"></i>
        <span>Desbloquear</span>
    </button>
    
    <div class="button-divider">
        <span>o</span>
    </div>
    
    <button type="button" class="btn-logout elegant-btn" id="logoutButton">
        <i class="fas fa-sign-out-alt me-2"></i>
        <span>Cerrar Sesión</span>
    </button>
</div>
			</form>
			</div>
        </div>
        
        <!-- Información del sistema -->
        <div class="system-info">
            <i class="fas fa-shield-alt me-2"></i>
            <span class="text-success">Sistema protegido</span> • SISFACT PDL Visiones v.2.2.3
            <span class="ms-2">•</span>
            <span class="ms-2" id="systemTime"></span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
        let sessionStartTime = Date.now();
        let unlockAttempts = 0;
        const maxAttempts = 3;
        let lockTimeout = null;

        // Actualizar hora y fecha en tiempo real
function updateDateTime() {
    const now = new Date();
    
    // Formatear hora en formato 12 horas con AM/PM
    let hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'pm' : 'am';
    
    // Convertir a formato 12 horas
    hours = hours % 12;
    hours = hours ? hours : 12; // La hora 0 se convierte a 12
    
    // Formatear con ceros a la izquierda
    const time = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${ampm}`;
    
    document.getElementById('currentTime').textContent = time;
    
    // Formatear fecha (mantener igual)
    const date = now.toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    document.getElementById('currentDate').textContent = date.charAt(0).toUpperCase() + date.slice(1);
    
    // Hora del sistema para el footer (también en formato 12 horas)
    let footerHours = now.getHours();
    const footerMinutes = now.getMinutes();
    const footerAmpm = footerHours >= 12 ? 'pm' : 'am';
    
    footerHours = footerHours % 12;
    footerHours = footerHours ? footerHours : 12;
    
    const systemTime = `${footerHours.toString().padStart(2, '0')}:${footerMinutes.toString().padStart(2, '0')} ${footerAmpm}`;
    document.getElementById('systemTime').textContent = time;//systemTime;
    
    // Actualizar temporizador de sesión bloqueada (mantener igual)
    const elapsed = Math.floor((Date.now() - sessionStartTime) / 1000);
    const minutesElapsed = Math.floor(elapsed / 60);
    const secondsElapsed = elapsed % 60;
    document.getElementById('timerDisplay').textContent = 
        `${minutesElapsed.toString().padStart(2, '0')}:${secondsElapsed.toString().padStart(2, '0')}`;
}

	   // Crear partículas de fondo
        function createParticles() {
            const particlesContainer = document.getElementById('bgParticles');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'bg-particle';
                
                // Tamaño aleatorio
                const size = Math.random() * 80 + 20;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Posición aleatoria
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Opacidad aleatoria
                particle.style.opacity = Math.random() * 0.08 + 0.02;
                
                // Animación aleatoria
                const duration = Math.random() * 25 + 15;
                const delay = Math.random() * 10;
                particle.style.animationDuration = `${duration}s`;
                particle.style.animationDelay = `${delay}s`;
                
                // Color aleatorio basado en el acento
                const hue = Math.floor(Math.random() * 60) + 200; // Tonos azules/morados
                particle.style.backgroundColor = `hsl(${hue}, 70%, 50%)`;
                
                particlesContainer.appendChild(particle);
            }
        }

        // Mostrar/ocultar contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('passwordInput');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
                this.title = 'Ocultar contraseña';
            } else {
                passwordInput.type = 'password';
                icon.className = 'fas fa-eye';
                this.title = 'Mostrar contraseña';
            }
            
            // Efecto visual
            this.style.transform = 'translateY(-50%) scale(1.1)';
            setTimeout(() => {
                this.style.transform = 'translateY(-50%) scale(1)';
            }, 200);
        });

        // Manejar envío del formulario
        document.getElementById('unlockForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const passwordInput = document.getElementById('passwordInput');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            const unlockButton = this.querySelector('.btn-unlock');
            
            // Deshabilitar botón durante la verificación
            unlockButton.disabled = true;
            unlockButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verificando...';
            
            try {
                // Verificar que haya contraseña
                if (passwordInput.value.trim() === '') {
                    throw new Error('La contraseña no puede estar vacía');
                }
                
                // Incrementar intentos
                unlockAttempts++;
                
                if (unlockAttempts >= maxAttempts) {
                    // Bloquear temporalmente después de máximo intentos
                    Swal.fire({
                        icon: 'error',
                        title: 'Demasiados intentos',
                        html: `
                            <p>Has excedido el número máximo de intentos permitidos.</p>
                            <p>La sesión se bloqueará por <strong>30 segundos</strong> por seguridad.</p>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    }).then(() => {
                        // Redirigir al login después del bloqueo
                        setTimeout(() => {
                            window.location.href = 'logout.php';
                        }, 30000);
                        
                        // Mostrar cuenta regresiva
                        let secondsLeft = 30;
                        const countdownInterval = setInterval(() => {
                            errorText.textContent = `Espere ${secondsLeft} segundos antes de reintentar`;
                            errorMessage.style.display = 'block';
                            
                            if (secondsLeft <= 0) {
                                clearInterval(countdownInterval);
                                window.location.href = 'logout.php';
                            }
                            secondsLeft--;
                        }, 1000);
                    });
                    
                    // Restaurar botón
                    unlockButton.disabled = false;
                    unlockButton.innerHTML = '<i class="fas fa-unlock me-2"></i>Desbloquear';
                    return;
                }
                
                // Verificar contraseña con el servidor
                const formData = new FormData();
                formData.append('password', passwordInput.value);
                formData.append('usuario_id', '<?php echo $_SESSION['usuario_id']; ?>');
                
                const response = await fetch('verificar_contrasena.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Verificar si la respuesta es JSON válido
                const responseText = await response.text();
                console.log('Respuesta del servidor:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('Error parseando JSON:', jsonError, 'Respuesta:', responseText);
                    throw new Error('Error del servidor: respuesta no válida');
                }
                
                if (data.success) {
                    // Contraseña correcta - redirigir al dashboard
                    Swal.fire({
                        icon: 'success',
                        title: '¡Sesión desbloqueada!',
                        text: 'Redirigiendo al dashboard...',
                        timer: 1500,
                        showConfirmButton: false,
                        background: 'var(--win-bg-secondary)',
                        color: 'var(--win-text-primary)'
                    }).then(() => {
                        // Limpiar flag de sesión bloqueada
                        fetch('limpiar_bloqueo.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ action: 'clear' })
                        });
                        
                        // Redirigir al dashboard
                        window.location.href = 'dashboard.php';
                    });
                } else {
                    // Contraseña incorrecta
                    throw new Error(data.error || 'Contraseña incorrecta');
                }
                
            } catch (error) {
                console.error('Error en desbloqueo:', error);
                
                // Mostrar error
                errorText.textContent = error.message;
                errorMessage.style.display = 'block';
                
                // Efecto visual de error
                const form = document.getElementById('unlockForm');
                form.style.animation = 'shake 0.5s ease-in-out';
                setTimeout(() => {
                    form.style.animation = '';
                }, 500);
                
                // Efecto en el campo de contraseña
                passwordInput.style.borderColor = '#ff6b6b';
                passwordInput.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.2)';
                setTimeout(() => {
                    passwordInput.style.borderColor = '';
                    passwordInput.style.boxShadow = '';
                }, 1500);
                
                // Limpiar campo de contraseña
                passwordInput.value = '';
                passwordInput.focus();
                
                // Restaurar botón
                unlockButton.disabled = false;
                unlockButton.innerHTML = '<i class="fas fa-unlock me-2"></i>Desbloquear';
            }
        });
        
        // Agregar atajo de teclado (Enter para enviar)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.id !== 'passwordInput') {
                document.getElementById('passwordInput').focus();
            }
        });

        // Detectar inactividad y redirigir al logout después de 10 minutos
        function resetInactivityTimer() {
            if (lockTimeout) clearTimeout(lockTimeout);
            
            lockTimeout = setTimeout(() => {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sesión expirada',
                    html: `
                        <p>La sesión bloqueada ha expirado por inactividad.</p>
                        <p>Serás redirigido a la página de login.</p>
                    `,
                    confirmButtonText: 'Continuar',
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                }).then(() => {
                    window.location.href = 'logout.php';
                });
            }, 10 * 60 * 1000); // 10 minutos
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Iniciar reloj
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Crear partículas de fondo
            createParticles();
            
            // Iniciar temporizador de inactividad
            resetInactivityTimer();
            
            // Reiniciar temporizador en eventos de interacción
            ['mousedown', 'mousemove', 'keydown', 'touchstart'].forEach(event => {
                document.addEventListener(event, resetInactivityTimer);
            });
            
            // Enfocar campo de contraseña automáticamente
            setTimeout(() => {
                document.getElementById('passwordInput').focus();
            }, 500);
            
            // Efecto de entrada para el avatar
            const avatar = document.getElementById('userAvatar');
            setTimeout(() => {
                avatar.style.transform = 'scale(1)';
                avatar.style.opacity = '1';
            }, 300);
            
            // Verificar si el avatar tiene foto
            if (avatar.classList.contains('has-photo')) {
                // Si tiene foto, ocultar las iniciales
                avatar.innerHTML = '';
                
                // Agregar efecto de brillo a la foto
                const shine = document.createElement('div');
                shine.style.position = 'absolute';
                shine.style.top = '0';
                shine.style.left = '0';
                shine.style.right = '0';
                shine.style.bottom = '0';
                shine.style.borderRadius = '50%';
                shine.style.background = 'radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.3) 0%, transparent 70%)';
                shine.style.pointerEvents = 'none';
                shine.style.zIndex = '2';
                avatar.appendChild(shine);
            }
            
            // Agregar estilo para animación de shake
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-8px); }
                    20%, 40%, 60%, 80% { transform: translateX(8px); }
                }
                
                .user-avatar-large {
                    transform: scale(0.9);
                    opacity: 0;
                    transition: transform 0.5s ease, opacity 0.5s ease;
                }
            `;
            document.head.appendChild(style);
            
            // Efecto de parallax para el logo de fondo
            document.addEventListener('mousemove', function(e) {
                const x = (e.clientX / window.innerWidth) * 20;
                const y = (e.clientY / window.innerHeight) * 20;
                document.querySelector('.logo-background').style.backgroundPosition = 
                    `${x}px ${y}px`;
            });
        });

document.getElementById('logoutButton').addEventListener('click', function() {
    Swal.fire({
        icon: 'question',
        title: '¿Cerrar sesión?',
        html: `
            <p>¿Estás seguro que deseas cerrar la sesión activa?</p>
            <p class="text-muted small">Serás redirigido a la página de inicio de sesión.</p>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, cerrar sesión',
        cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
        confirmButtonColor: '#ff6b6b',
        cancelButtonColor: 'var(--win-text-secondary)',
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        reverseButtons: true,
		allowOutsideClick: false,
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar animación de carga
            Swal.fire({
                title: 'Cerrando sesión...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: 'var(--win-bg-secondary)',
                color: 'var(--win-text-primary)',
            });
            
            // Enviar solicitud al servidor
            fetch('logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'logout' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirigir después de 1 segundo
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1000);
                } else {
                    // Si hay error, redirigir directamente
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = 'login.php';
            });
        }
    });
});
// Prevenir que el usuario pueda volver atrás desde la pantalla de bloqueo
(function preventBackNavigation() {
    // Empujar un estado nuevo
    history.pushState(null, null, location.href);
    
    // Si intenta ir atrás, lo enviamos de vuelta a la pantalla de bloqueo
    window.addEventListener('popstate', function() {
        history.pushState(null, null, location.href);
        // Opcional: Mostrar un mensaje
        console.log('No puedes volver atrás mientras la sesión está bloqueada');
    });
})();
    </script>
</body>
</html>