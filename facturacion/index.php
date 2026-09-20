<?php
// index.php

// =======================================================================
// 1. NUEVO: MANEJADOR DE ESTADO (AJAX)
// =======================================================================
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    $response = ['db_ok' => false, 'maintenance' => false];
    try {
        if (file_exists('config/database.php')) {
            require_once 'config/database.php';
            $db = Database::getConnection();
            $response['db_ok'] = true;
            
            // Verificar mantenimiento
            $sql = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] !== 0) {
					$response['maintenance'] = true;
				}
            } catch (Exception $e) { /* Ignorar si no existe la columna */ }
        }
    } catch (Exception $e) { $response['db_ok'] = false; }
    echo json_encode($response);
    exit;
}
// =======================================================================

session_start();

// ========== FUNCIÓN PARA OBTENER CONFIGURACIÓN DE WHATSAPP ==========
function obtenerConfiguracionWhatsApp() {
    try {
        $db = Database::getConnection();
        
        // Consulta para obtener configuración del sistema
        $sql = "SELECT whatsapp_numero, whatsapp_ON FROM configuracion_sistema LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetch();
        
        if ($config) {
            return [
                'whatsapp_numero' => $config['whatsapp_numero'] ?? null,
                'whatsapp_ON' => $config['whatsapp_ON'] ?? 0,
                'whatsapp_activo' => ($config['whatsapp_ON'] == 1)
            ];
        }
        
        return [
            'whatsapp_numero' => null,
            'whatsapp_ON' => 0,
            'whatsapp_activo' => false
        ];
        
    } catch (Exception $e) {
        // En caso de error, retornar valores por defecto
        // error_log("Error al obtener configuración WhatsApp: " . $e->getMessage());
        return [
            'whatsapp_numero' => null,
            'whatsapp_ON' => 0,
            'whatsapp_activo' => false
        ];
    }
}

// ========== FUNCIÓN PARA FORMATEAR NÚMERO DE WHATSAPP ==========
function formatearNumeroWhatsApp($numero) {
    if (!$numero) return null;
    
    // Eliminar todos los caracteres no numéricos
    $numero = preg_replace('/\D/', '', $numero);
    
    // Versión compatible con PHP < 8.0
    if (strlen($numero) === 8 && substr($numero, 0, 2) !== '53') {
        $numero = '53' . $numero;
    }
    
    return $numero;
}

// =============================================
// COMPROBACIÓN CON MODAL SIMPLE
// =============================================
if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
    // PRIMERO: Verificar si estamos en modo mantenimiento
    $en_mantenimiento = false;
    try {
        if (file_exists('config/database.php')) {
            require_once 'config/database.php';
            $db = Database::getConnection();
            
            // Verificar mantenimiento
            $sql = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] != 0) {
                    $en_mantenimiento = true;
                }
            } catch (Exception $e) { /* Ignorar si no existe la columna */ }
        }
    } catch (Exception $e) { /* Si hay error de BD, continuar sin mantenimiento */ }
    
    // SI ESTÁ EN MANTENIMIENTO: Mostrar modal de mantenimiento y bloquear acceso
    if ($en_mantenimiento) {
        // Obtener configuración de WhatsApp para el modal
        $configWhatsApp = obtenerConfiguracionWhatsApp();
        $whatsapp_numero_formateado = $configWhatsApp['whatsapp_activo'] && $configWhatsApp['whatsapp_numero'] 
            ? formatearNumeroWhatsApp($configWhatsApp['whatsapp_numero'])
            : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sistema en Mantenimiento - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    <script src="js/font-awesome6.4.0/js/all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0d1117;
            font-family: 'Segoe UI', sans-serif;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        /* Fondo animado de mantenimiento */
        .maintenance-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 193, 7, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 193, 7, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(255, 193, 7, 0.04) 0%, transparent 50%);
            z-index: -1;
        }
        
        .gear-1 {
            position: fixed;
            top: 20%;
            left: 10%;
            font-size: 80px;
            color: rgba(255, 193, 7, 0.15);
            animation: spin 20s linear infinite;
        }
        
        .gear-2 {
            position: fixed;
            bottom: 20%;
            right: 10%;
            font-size: 120px;
            color: rgba(255, 193, 7, 0.1);
            animation: spinReverse 25s linear infinite;
        }
        
        .gear-3 {
            position: fixed;
            top: 60%;
            left: 85%;
            font-size: 60px;
            color: rgba(255, 193, 7, 0.08);
            animation: spin 30s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes spinReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        
        .modal {
            background: rgba(22, 27, 34, 0.95);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 10px 10px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(255, 193, 7, 0.2);
            backdrop-filter: blur(10px);
            z-index: 1;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .icon {
            font-size: 48px;
            color: #ffc107;
            margin-bottom: 20px;
            animation: pulseMaint 2s infinite;
        }
        
        @keyframes pulseMaint {
            0% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
            50% { transform: scale(1.1); text-shadow: 0 0 20px rgba(255, 193, 7, 1); }
            100% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
        }
        
        h2 {
            color: #ffc107;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .user-name {
            color: #25d366;
            font-weight: bold;
        }
        
        p {
            color: #8b949e;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
/* ===== ESTILOS ACTUALIZADOS PARA INFO-BOX EN FILA ===== */
.info-box-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
}

.info-box {
    background: rgba(255, 193, 7, 0.05);
    border: 1px dashed rgba(255, 193, 7, 0.3);
    border-radius: 8px;
    padding: 15px;
    text-align: left;
    flex: 1;
    min-width: 250px; /* Ancho mínimo para que no se hagan demasiado pequeños */
}

.info-box h4 {
    color: #ffc107;
    margin-bottom: 10px;
    font-size: 16px;
    display: flex;
    align-items: center;
}

.info-box h4 i {
    margin-right: 10px;
}

.info-box p {
    color: #8b949e;
    margin-bottom: 0;
    line-height: 1.5;
    font-size: 14px;
}

/* Para pantallas pequeñas, hacerlos en columna */
@media (max-width: 768px) {
    .info-box-container {
        flex-direction: column;
    }
    
    .info-box {
        min-width: 100%;
    }
}
        
        .contact-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 25px 0;
        }
        
        .contact-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(37, 211, 102, 0.1);
            color: #25d366;
            border: 1px solid rgba(37, 211, 102, 0.3);
            padding: 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .contact-btn:hover {
            background: rgba(37, 211, 102, 0.2);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.2);
        }
        
        .contact-btn.email {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border-color: rgba(13, 110, 253, 0.3);
        }
        
        .contact-btn.email:hover {
            background: rgba(13, 110, 253, 0.2);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
        }
        
        .contact-btn.phone {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
            border-color: rgba(111, 66, 193, 0.3);
        }
        
        .contact-btn.phone:hover {
            background: rgba(111, 66, 193, 0.2);
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.2);
        }
        
        .logout-link {
            display: block;
            color: #f85149;
            text-decoration: none;
            margin-top: 25px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .logout-link:hover {
            text-decoration: underline;
            text-shadow: 0 0 10px rgba(248, 81, 73, 0.3);
        }
        
        .recheck-btn {
            display: inline-block;
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .recheck-btn:hover {
            background: rgba(255, 193, 7, 0.2);
            transform: translateY(-2px);
        }
/* ============================================= */
/* DROPDOWNS SIMPLES PARA FONDO OSCURO */
/* ============================================= */

/* Contenedor del dropdown */
.support-dropdown-container {
    margin-bottom: 1.2rem;
}

/* Label del dropdown */
.support-dropdown-label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: #a0c8ff;
}

.support-dropdown-label i {
    margin-right: 0.5rem;
    color: #007bff;
}

.support-dropdown-label.required::after {
    content: " *";
    color: #ff4757;
}

/* Dropdown principal */
.support-dropdown-select {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    color: #ffffff;
    background-color: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    cursor: pointer;
    appearance: none;
    transition: all 0.2s ease;
    position: relative;
}

/* Placeholder */
.support-dropdown-select option[value=""][disabled] {
    color: rgba(160, 200, 255, 0.6);
}

/* Opciones */
.support-dropdown-select option:not([value=""]) {
    color: #ffffff;
    background-color: #1a2332;
}

/* Estados */
.support-dropdown-select:hover {
    border-color: rgba(0, 123, 255, 0.4);
    background-color: rgba(0, 0, 0, 0.4);
}

.support-dropdown-select:focus {
    outline: none;
    border-color: #007bff;
    background-color: rgba(0, 0, 0, 0.5);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
}

/* Flecha personalizada */
.support-dropdown {
    position: relative;
}

.support-dropdown::after {
    content: "▾";
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(160, 200, 255, 0.7);
    pointer-events: none;
    font-size: 1rem;
}

/* Estados de validación */
.support-dropdown-select.valid {
    border-color: #28a745;
}

.support-dropdown-select.invalid {
    border-color: #ff4757;
}

/* Responsive */
@media (max-width: 768px) {
    .support-dropdown-select {
        padding: 0.65rem 0.85rem;
        font-size: 0.85rem;
    }
    
    .support-dropdown-label {
        font-size: 0.8rem;
    }
}
    </style>
</head>
<body>
    <div class="maintenance-bg"></div>
    <div class="gear-1"><i class="fas fa-cog"></i></div>
    <div class="gear-2"><i class="fas fa-cog"></i></div>
    <div class="gear-3"><i class="fas fa-cog"></i></div>
    
    <div class="modal">
        <div class="icon"><i class="fa-solid fa-helmet-safety"></i></div>
        <h2>Sistema en Mantenimiento</h2>
        <p>Hola <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>, detectamos que tienes una sesión activa, pero el sistema se encuentra actualmente en modo de mantenimiento.</p>
		
		<?php if (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 5): ?>
                <span style="display: inline-block; background: rgba(220, 53, 69, 0.2); color: #ff6b6b; font-size: 0.85em; padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(220, 53, 69, 0.4); margin-left: 5px; font-weight: 600;">
                    <i class="fa-solid fa-user-secret"></i> Usted es un usuario Programador, puede volver a acceder en Modo Mantenimiento
                </span>
            <?php endif; ?>
        
<div class="info-box-container">
    <div class="info-box">
        <h4><i class="fa-solid fa-circle-info"></i> ¿Qué está pasando?</h4>
        <p>El equipo técnico está realizando mejoras en el sistema. Durante este tiempo, el acceso al panel de control está temporalmente deshabilitado.</p>
    </div>
    
    <div class="info-box">
        <h4><i class="fa-solid fa-clock"></i> Tiempo estimado</h4>
        <p>El mantenimiento suele completarse en 30-60 minutos. Te notificaremos cuando el sistema vuelva a estar disponible.</p>
    </div>
</div>
        
        <div class="contact-options">
            <?php if (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 5): ?>
            <a href="login.php?access_bypass=true" class="contact-btn" style="background: rgba(220, 53, 69, 0.15); color: #ff6b6b; border: 1px solid rgba(220, 53, 69, 0.4);">
                <i class="fa-solid fa-user-secret"></i> Acceder como Programador
            </a>
			<h4 style="color: #ffc107; margin-top: 25px;">Contacta con soporte si es urgente:</h4>
            <?php endif; ?>
            
            <?php if ($whatsapp_numero_formateado): ?>
            <a href="https://api.whatsapp.com/send?phone=<?php echo $whatsapp_numero_formateado; ?>" 
               target="_blank" class="contact-btn">
                <i class="fab fa-whatsapp"></i> WhatsApp Soporte
            </a>
            <?php endif; ?>
            
            <a href="mailto:soporte_pdlvisiones@gmail.com" class="contact-btn email">
                <i class="fa-solid fa-envelope"></i> Enviar Correo
            </a>
            
            <a href="tel:+5359860773" class="contact-btn phone">
                <i class="fa-solid fa-phone"></i> Llamar a Soporte
            </a>
        </div>
        
        <div class="recheck-btn" onclick="recheckStatus()">
            <i class="fa-solid fa-rotate-right"></i> Verificar estado nuevamente
        </div>
        
        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
        </a>
    </div>

    <script>
        function recheckStatus() {
            const btn = document.querySelector('.recheck-btn');
            const originalHTML = btn.innerHTML;
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verificando...';
            btn.disabled = true;
            
            fetch('index.php?action=check_status')
                .then(response => response.json())
                .then(data => {
                    setTimeout(() => {
                        if (data.maintenance) {
                            // Aún en mantenimiento
                            btn.innerHTML = '<i class="fa-solid fa-clock"></i> Aún en mantenimiento';
                            setTimeout(() => {
                                btn.innerHTML = originalHTML;
                                btn.disabled = false;
                            }, 2000);
                        } else {
                            // Mantenimiento terminado
                            btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Sistema disponible!';
                            setTimeout(() => {
                                window.location.href = 'dashboard.php';
                            }, 1000);
                        }
                    }, 1000);
                })
                .catch(error => {
                    btn.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error de conexión';
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }, 2000);
                });
        }
        
        // Verificar automáticamente cada 2 minutos
        setInterval(recheckStatus, 120000);
    </script>
</body>
</html>
<?php
        exit;
    }
    
    // SI NO ESTÁ EN MANTENIMIENTO: Mostrar la página normal de sesión activa
    // Obtener nombre de usuario
    $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SESIÓN ABIERTA - SISFACT PDL Visiones - Plataforma de Facturación e Impresión</title>
  <link rel="icon" type="image/x-icon" href="assets/logov.png">
    <script src="js/font-awesome6.4.0/js/all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0d1117;
            font-family: 'Segoe UI', sans-serif;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        /* Contenedor principal del fondo */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        /* Capa 1: Movimiento diagonal principal */
        .logo-layer-1 {
            position: absolute;
            top: 0;
            left: 0;
            width: 300%;
            height: 300%;
            opacity: 0.05;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 180px 180px;
            animation: moveDiagonal1 45s linear infinite;
        }
        
        /* Capa 2: Movimiento diagonal inverso */
        .logo-layer-2 {
            position: absolute;
            top: 0;
            left: 0;
            width: 350%;
            height: 350%;
            opacity: 0.04;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 140px 140px;
            animation: moveDiagonal2 60s linear infinite;
        }
        
        /* Capa 3: Movimiento horizontal */
        .logo-layer-3 {
            position: absolute;
            top: 0;
            left: 0;
            width: 400%;
            height: 200%;
            opacity: 0.03;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 100px 100px;
            animation: moveHorizontal 50s linear infinite;
        }
        
        /* Capa 4: Movimiento vertical */
        .logo-layer-4 {
            position: absolute;
            top: 0;
            left: 0;
            width: 200%;
            height: 400%;
            opacity: 0.03;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 120px 120px;
            animation: moveVertical 55s linear infinite;
        }
        
        /* Animaciones */
        @keyframes moveDiagonal1 {
            0% {
                transform: translate(0, 0);
            }
            25% {
                transform: translate(-33%, -33%);
            }
            50% {
                transform: translate(-66%, -66%);
            }
            75% {
                transform: translate(-33%, -33%);
            }
            100% {
                transform: translate(0, 0);
            }
        }
        
        @keyframes moveDiagonal2 {
            0% {
                transform: translate(-66%, 0);
            }
            25% {
                transform: translate(-33%, -33%);
            }
            50% {
                transform: translate(0, -66%);
            }
            75% {
                transform: translate(-33%, -33%);
            }
            100% {
                transform: translate(-66%, 0);
            }
        }
        
        @keyframes moveHorizontal {
            0% {
                transform: translateX(0);
            }
            25% {
                transform: translateX(-25%);
            }
            50% {
                transform: translateX(-50%);
            }
            75% {
                transform: translateX(-25%);
            }
            100% {
                transform: translateX(0);
            }
        }
        
        @keyframes moveVertical {
            0% {
                transform: translateY(0);
            }
            25% {
                transform: translateY(-25%);
            }
            50% {
                transform: translateY(-50%);
            }
            75% {
                transform: translateY(-25%);
            }
            100% {
                transform: translateY(0);
            }
        }
        
        /* Efecto de movimiento 8 direcciones (más complejo) */
        .logo-8directions {
            position: fixed;
            top: 0;
            left: 0;
            width: 400%;
            height: 400%;
            z-index: -1;
            opacity: 0.08;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 150px 150px;
            animation: move8Directions 120s linear infinite;
        }
        
        @keyframes move8Directions {
            0% {
                transform: translate(0, 0);
            }
            12.5% {
                transform: translate(-33%, 0); /* Derecha */
            }
            25% {
                transform: translate(-66%, -33%); /* Diagonal inferior derecha */
            }
            37.5% {
                transform: translate(-66%, -66%); /* Abajo */
            }
            50% {
                transform: translate(-33%, -66%); /* Diagonal inferior izquierda */
            }
            62.5% {
                transform: translate(0, -66%); /* Izquierda */
            }
            75% {
                transform: translate(0, -33%); /* Diagonal superior izquierda */
            }
            87.5% {
                transform: translate(-33%, 0); /* Arriba */
            }
            100% {
                transform: translate(0, 0);
            }
        }
        
        /* Versión con movimiento fluido en ambas direcciones */
        .logo-fluid-movement {
            position: fixed;
            top: 0;
            left: 0;
            width: 500%;
            height: 500%;
            z-index: -1;
            opacity: 0.07;
            background-image: url('assets/logov.png');
            background-repeat: repeat;
            background-size: 130px 130px;
            animation: fluidMovement 180s linear infinite;
        }
        
        @keyframes fluidMovement {
            0% {
                transform: translate(0, 0);
            }
            16.66% {
                transform: translate(-50%, -25%);
            }
            33.33% {
                transform: translate(-100%, 0);
            }
            50% {
                transform: translate(-50%, -25%);
            }
            66.66% {
                transform: translate(0, -50%);
            }
            83.33% {
                transform: translate(-50%, -25%);
            }
            100% {
                transform: translate(0, 0);
            }
        }
        
        .modal {
            background: rgba(22, 27, 34, 0.95);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
            backdrop-filter: blur(10px);
            z-index: 1;
            border: 1px solid rgba(45, 155, 85, 0.2);
        }
        
        .icon {
            font-size: 48px;
            color: #25d366;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 5px rgba(37, 211, 102, 0.3));
        }
        
        h2 {
            color: #fff;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .user-name {
            color: #25d366;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(37, 211, 102, 0.3);
        }
        
        p {
            color: #8b949e;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .countdown {
            font-size: 32px;
            font-weight: bold;
            color: #25d366;
            margin: 20px 0;
            font-family: monospace;
            text-shadow: 0 0 10px rgba(37, 211, 102, 0.2);
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #30363d;
            border-radius: 3px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .progress {
            height: 100%;
            background: linear-gradient(90deg, #25d366, #2fd674);
            width: 0%;
            transition: width 1s linear;
            border-radius: 3px;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #25d366, #2bc16d);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #1a8c4a, #25d366);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }
        
        .logout-link {
            display: block;
            color: #f85149;
            text-decoration: none;
            margin-top: 15px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .logout-link:hover {
            text-decoration: underline;
            text-shadow: 0 0 10px rgba(248, 81, 73, 0.3);
        }
        
        /* Controles para el fondo */
        .bg-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 10;
        }
        
        .bg-btn {
            background: rgba(22, 27, 34, 0.85);
            color: #8b949e;
            border: 1px solid #30363d;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }
        
        .bg-btn:hover {
            background: rgba(37, 211, 102, 0.2);
            color: #25d366;
            border-color: #25d366;
        }
        
        .bg-btn.active {
            background: rgba(37, 211, 102, 0.3);
            color: #25d366;
            border-color: #25d366;
        }
    </style>
</head>
<body>
    <!-- Opciones de fondo (solo una activa a la vez) -->
    <div class="background-container" id="bgLayers" style="display: none;">
        <div class="logo-layer-1"></div>
        <div class="logo-layer-2"></div>
        <div class="logo-layer-3"></div>
        <div class="logo-layer-4"></div>
    </div>
    
    <div class="logo-8directions" id="bg8Directions" style="display: none;"></div>
    <div class="logo-fluid-movement" id="bgFluid" style="display: block;"></div>
    
    <!-- Controles para cambiar fondo -->
    <div class="bg-controls">
        <button class="bg-btn active" onclick="changeBackground('fluid')">Movimiento Fluido</button>
        <button class="bg-btn" onclick="changeBackground('layers')">Capas Múltiples</button>
        <button class="bg-btn" onclick="changeBackground('8directions')">8 Direcciones</button>
    </div>
    
    <div class="modal">
        <div class="icon">✓</div>
        <h2>Sesión Activa</h2>
        <p>Hola <span class="user-name"><?php echo htmlspecialchars($usuario_nombre); ?></span>, ya tienes una sesión iniciada.</p>
        
        <div class="countdown" id="countdown">03:00</div>
        
        <div class="progress-bar">
            <div class="progress" id="progress"></div>
        </div>
        
        <p>Serás redirigido automáticamente en <span id="seconds">180</span> segundos...</p>
        
        <a href="dashboard.php" class="btn"><i class="fas fa-dashboard" style="color:black;"></i> Ir ahora al panel</a>
        
        <a href="logout.php" class="logout-link"><i class="fas fa-users"></i> ¿No eres <span class="user-name"><?php echo htmlspecialchars($usuario_nombre); ?></span>? Cerrar sesión</a>
    </div>

    <script>
        // Función para cambiar entre diferentes fondos animados
        function changeBackground(bgType) {
            // Ocultar todos los fondos
            document.getElementById('bgLayers').style.display = 'none';
            document.getElementById('bg8Directions').style.display = 'none';
            document.getElementById('bgFluid').style.display = 'none';
            
            // Mostrar el fondo seleccionado
            if (bgType === 'layers') {
                document.getElementById('bgLayers').style.display = 'block';
            } else if (bgType === '8directions') {
                document.getElementById('bg8Directions').style.display = 'block';
            } else {
                document.getElementById('bgFluid').style.display = 'block';
            }
            
            // Actualizar botones activos
            document.querySelectorAll('.bg-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Contador de redirección
        let countdown = 180; // 3 minutos = 180 segundos
        const countdownElement = document.getElementById('countdown');
        const secondsElement = document.getElementById('seconds');
        const progressElement = document.getElementById('progress');
        
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        
        const countdownInterval = setInterval(() => {
            countdown--;
            
            // Actualizar contador formato MM:SS
            countdownElement.textContent = formatTime(countdown);
            secondsElement.textContent = countdown;
            
            // Actualizar barra de progreso
            const progress = ((180 - countdown) / 180) * 100;
            progressElement.style.width = `${progress}%`;
            
            // Cambiar color en últimos 10 segundos
            if (countdown <= 10) {
                progressElement.style.background = 'linear-gradient(90deg, #ff6b6b, #ff8585)';
                countdownElement.style.color = '#ff6b6b';
                countdownElement.style.textShadow = '0 0 10px rgba(255, 107, 107, 0.3)';
            } else if (countdown <= 30) {
                progressElement.style.background = 'linear-gradient(90deg, #ffd166, #ffdd88)';
                countdownElement.style.color = '#ffd166';
                countdownElement.style.textShadow = '0 0 10px rgba(255, 209, 102, 0.3)';
            }
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'dashboard.php';
            }
        }, 1000);
        
        // Permitir que el usuario vaya manualmente
        document.querySelector('.btn').addEventListener('click', function(e) {
            e.preventDefault();
            clearInterval(countdownInterval);
            window.location.href = 'dashboard.php';
        });
        
        // Cambiar automáticamente el fondo cada 30 segundos
        let currentBg = 0;
        const bgTypes = ['fluid', 'layers', '8directions'];
        
        setInterval(() => {
            currentBg = (currentBg + 1) % bgTypes.length;
            changeBackground(bgTypes[currentBg]);
            
            // Simular click en el botón correspondiente
            document.querySelectorAll('.bg-btn')[currentBg].click();
        }, 30000);
    </script>
</body>
</html>
<?php
    exit;
}

// Forzar que no se guarde en caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// ========== CONEXIÓN A LA BASE DE DATOS ==========
// Usamos try-catch para no romper la pagina si falla la BD al inicio
try {
    if (file_exists('config/database.php')) require_once 'config/database.php';
} catch (Exception $e) {}

// Obtener configuración de WhatsApp una sola vez
$configWhatsApp = obtenerConfiguracionWhatsApp();

// Si viene de logout, limpiar todo
if (isset($_GET['logout']) || !isset($_SESSION['usuario_id'])) {
    // Destruir sesión si existe
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
        session_start(); // Iniciar nueva sesión limpia
    }
    
    // Limpiar variables de sesión
    $_SESSION = array();
}
// Obtener número formateado
$whatsapp_numero_formateado = $configWhatsApp['whatsapp_activo'] && $configWhatsApp['whatsapp_numero'] 
    ? formatearNumeroWhatsApp($configWhatsApp['whatsapp_numero'])
    : null;
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SISFACT PDL Visiones - Plataforma de Facturación e Impresión</title>
  <link rel="icon" type="image/x-icon" href="assets/logov.png">
  <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet" />
  <link href="css/Animate4.1.1/animate.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/index.css">
  <!-- SweetAlert2 -->
  <link href="css/sweetalert2.min.css" rel="stylesheet">
  <script src="js/font-awesome6.4.0/js/all.min.js"></script>
  <style>
  /* ===== INDICADOR DE CONEXIÓN DINÁMICO ===== */
#chat-header {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-right: 10px; /* Espacio para la X */
    padding-left: 15px;
}

/* El indicador se posiciona entre el texto y la X */
#chat-header > div:first-child::after {
    content: '<?php echo $configWhatsApp['whatsapp_activo'] ? "🌎 En línea" : "🔴 Fuera de Línea"; ?>';
    position: absolute;
    right: 5px; /* Deja espacio para la X (35px de la X + 10px de margen) */
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.7rem;
    font-weight: 600;
    <?php if ($configWhatsApp['whatsapp_activo']): ?>
        color: #25d366;
        background: rgba(37, 211, 102, 0.15);
        border: 1px solid rgba(37, 211, 102, 0.3);
        animation: pulseOnline 2s infinite;
    <?php else: ?>
        color: yellow;
        background: rgba(255, 107, 53, 0.15);
        border: 1px solid rgba(255, 107, 53, 0.3);
        animation: pulseOffline 2s infinite;
    <?php endif; ?>
    padding: 4px 10px;
    border-radius: 15px;
    white-space: nowrap;
    z-index: 1;
}

/* Asegurar que el contenedor del texto tenga espacio */
#chat-header > div:first-child {
    position: relative;
    padding-right: 100px; /* Espacio para el indicador */
    flex-grow: 1;
}

@keyframes pulseOnline {
    0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(37, 211, 102, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
}

@keyframes pulseOffline {
    0% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(255, 107, 53, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0); }
}

@media (max-width: 768px) {
    #chat-header { padding-right: 5px; padding-left: 10px; }
    #chat-header > div:first-child::after { right: 40px; font-size: 0.65rem; padding: 3px 8px; }
    #chat-header > div:first-child { padding-right: 80px; }
}

/* ===== NUEVO: ESTILOS PARA MANTENIMIENTO ===== */
.maintenance-overlay {
    background: rgba(0, 0, 0, 0.95) !important;
    backdrop-filter: blur(10px);
    z-index: 99999 !important;
}
.maintenance-icon-pulse {
    animation: pulseMaint 2s infinite;
    color: #ffc107;
}
@keyframes pulseMaint {
    0% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
    50% { transform: scale(1.1); text-shadow: 0 0 20px rgba(255, 193, 7, 1); }
    100% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
}
/* Estilo para botón deshabilitado */
.btn-disabled-maint {
    pointer-events: none !important;
    opacity: 0.6 !important;
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    cursor: not-allowed !important;
}
/* Asegurar que el modal siempre esté encima */
#maintenanceModal.active {
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Aseguramos que si no tiene la clase active, esté oculto */
#maintenanceModal {
    display: none;
}




/* El overlay debe ser "clickeable" para capturar los eventos */
.maintenance-overlay {
    pointer-events: auto !important;
    cursor: default !important;
}

/* Prevenir que otros elementos interfieran */
body.maintenance-modal-open * {
    pointer-events: none !important;
}

body.maintenance-modal-open #maintenanceModal,
body.maintenance-modal-open #maintenanceModal * {
    pointer-events: auto !important;
}
</style>
</head>
<body>



<header>

</header>
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top px-0" style="background: linear-gradient(to right, #00a86b 0%, #006a4e 100%);">
    <a class="navbar-brand d-flex align-items-center" href="#hero" aria-label="PDL Visiones, sistema de facturación">
      <img src="assets/logov.png" alt="Logo PDL Visiones" width="36" height="36" style="margin-right: 8px;">
	  <span onclick="openSystemModal()" style="cursor: pointer; color: #007bff; text-decoration: underline;" title="Sobre el Sistema...">
		SISFACT PDL Visiones
	  </span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#hero">
            <i class="fa-solid fa-house me-1"></i> Inicio
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#services">
            <i class="fa-solid fa-layer-group me-1"></i> Servicios
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#paraquien">
            <i class="fa-solid fa-briefcase me-1"></i> ¿Para Quién es?
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#billing-features">
            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Facturación
          </a>
        </li>
		
        <li class="nav-item">
          <a class="nav-link" href="#team">
            <i class="fa-solid fa-hands-helping me-1"></i> Nuestro Equipo
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#contacto">
            <i class="fa-solid fa-phone me-1"></i> Contacto
          </a>
        </li>
        <li class="nav-item">
          <a id="btn-login-nav" class="btn btn-success btn-sm ms-lg-3" href="login.php" style="font-weight: 700;">
            <i class="fa-solid fa-right-to-bracket me-1"></i> Iniciar Sesión
          </a>
        </li>
      </ul>
    </div>
  </nav>
<main>

  <section id="hero" class="hero">
    <h1 class="animate__animated animate__fadeInDown" style="font-weight: 900; font-size: 3rem; letter-spacing: 0.08em;">
      Revoluciona tu Facturación e Impresión
    </h1>
    <p class="animate__animated animate__fadeInUp" style="font-weight: 600; font-size: 1.3rem; margin-top: 0rem; color: #a3d4ff;">
      <strong>SISFACT Proyecto de Desarrollo Local "Visiones":</strong> Plataforma integral para facturación y servicios de impresión, optimizada para tu negocio. Facturación inteligente para servicios de impresión. Menos papel perdido. Más control. Más ganancias.
	</p>
<div id="heroCarousel" class="carousel slide animate__animated animate__fadeInUp mt-4" data-bs-ride="carousel" aria-label="Carrusel de imágenes relacionadas con facturación e impresión">
      
      <!-- Indicadores -->
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="4" aria-label="Slide 5"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="5" aria-label="Slide 6"></button>
      </div>

      <div class="carousel-inner">
        <!-- Item 1 -->
        <div class="carousel-item active">
          <img src="assets/Carousel/1.jpg" class="d-block w-100" alt="Factura digital en pantalla">
          <div class="carousel-caption d-none d-md-block">
            Factura digital simplificada
          </div>
        </div>
        
        <!-- Item 2 -->
        <div class="carousel-item">
          <img src="assets/Carousel/2.jpg" class="d-block w-100" alt="Impresión profesional">
          <div class="carousel-caption d-none d-md-block">
            Impresión profesional para tu negocio
          </div>
        </div>
        
        <!-- Item 3 -->
        <div class="carousel-item">
          <img src="assets/Carousel/3.jpg" class="d-block w-100" alt="Análisis y reportes de facturación">
          <div class="carousel-caption d-none d-md-block">
            Análisis y reportes inteligentes
          </div>
        </div>

        <!-- Item 4 -->
        <div class="carousel-item">
          <img src="assets/Carousel/4.jpg" class="d-block w-100" alt="Gestión de inventarios y servicios">
          <div class="carousel-caption d-none d-md-block">
            Control total de inventarios y servicios
          </div>
        </div>
        
        <!-- Item 5 -->
        <div class="carousel-item">
          <img src="assets/Carousel/5.jpg" class="d-block w-100" alt="Seguridad informática y protección de datos">
          <div class="carousel-caption d-none d-md-block">
            Máxima seguridad para tu información
          </div>
        </div>

        <!-- Item 6 -->
        <div class="carousel-item">
          <img src="assets/Carousel/6.jpg" class="d-block w-100" alt="Equipo de trabajo impulsando el desarrollo local">
          <div class="carousel-caption d-none d-md-block">
            Visiones: impulsando el desarrollo local
          </div>
        </div>
      </div>

      <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" aria-label="Anterior">
        <span class="carousel-control-prev-icon"></span>
        <span class="visually-hidden">Anterior</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" aria-label="Siguiente">
        <span class="carousel-control-next-icon"></span>
        <span class="visually-hidden">Siguiente</span>
      </button>
    </div>
  </section>
<section id="services" class="team-section animate__animated animate__fadeInUp">
  <h2><i class="fa-solid fa-layer-group me-1"></i>Servicios de Impresión Profesional</h2>
  <p class="subtitle">
    Soluciones completas de impresión y gestión, listas para escalar con tu negocio.
  </p>

<div class="team-usecases">
	<div class="card">
      <h3><i class="fa-solid fa-bolt"></i> Rapidez Imparable</h3>
      <p>Optimiza tu flujo de trabajo con nuestra plataforma veloz y siempre disponible. Factura e imprime sin perder ni un segundo.</p>
    </div>
    <div class="card">
      <h3><i class="fa-solid fa-lock"></i> Seguridad de Nivel Militar</h3>
      <p>Confía en un sistema robusto que protege tu información con cifrado avanzado y controles estrictos de acceso.</p>
    </div>
    <div class="card">
      <h3><i class="fa-solid fa-cloud-arrow-up"></i> Acceso en la Nube</h3>
      <p>Desde cualquier dispositivo, en cualquier lugar, con conexión segura a la nube para máxima flexibilidad y control.</p>
    </div>
	  <div class="card">
		<h3><i class="fa-solid fa-file-invoice"></i> Factura en 3 pasos</h3>
		<p>
		  Registra el servicio → Calcula costos → Genera factura digital o impresa.
		  Sin vueltas. Sin errores.
		</p>
	  </div>
    <!-- ... (Tus otros cards siguen igual) ... -->
	  <div class="card">
		<h3><i class="fa-solid fa-print"></i> Impresión Controlada</h3>
		<p>
		  Gestiona impresiones por tipo, tamaño, color y volumen.
		  Cada hoja cuenta. Cada centavo también.
		</p>
	  </div>
	  <div class="card">
		<h3><i class="fa-solid fa-chart-line"></i> Costos en Tiempo Real</h3>
		<p>
		  Toner, papel, formatos y márgenes visibles al instante.
		  Decisiones inteligentes, cero sorpresas.
		</p>
	  </div>
    <div class="card">
      <h3><i class="fa-solid fa-shield-halved"></i> Seguridad Total</h3>
      <p>
        Cifrado, respaldos automáticos y control de accesos.
      </p>
    </div>
    <div class="card">
      <h3><i class="fa-solid fa-print"></i> Impresión Digital</h3>
      <p>
        Alta calidad en color y B/N para documentos, informes y material corporativo.
      </p>
      <p class="small text-info">
        Alta velocidad · Múltiples formatos · Calidad profesional
      </p>
    </div>

    <div class="card">
      <h3><i class="fa-solid fa-copy"></i> Copias y Escaneo</h3>
      <p>
        Digitalización y copiado con confidencialidad y precisión garantizadas.
      </p>
      <p class="small text-info">
        PDF · Imagen · Procesamiento por lotes
      </p>
    </div>

    <div class="card">
      <h3><i class="fa-solid fa-file-invoice"></i> Facturación de Servicios</h3>
      <p>
        Factura cada impresión con trazabilidad total y reportes automáticos.
      </p>
      <p class="small text-info">
        Control · Inventario · Auditoría
      </p>
    </div>
</div>
</section>

<section id="paraquien" class="team-section animate__animated animate__fadeInUp">
  <h2><i class="fa-solid fa-briefcase me-1"></i>¿Para quién es SISFAC PDL Visiones?</h2>
  <p class="subtitle">
    Diseñado para negocios que imprimen, facturan y no quieren perder dinero.
  </p>

<div class="team-usecases">
  <div class="card">
    <h3><i class="fa-solid fa-store"></i> Centros de Impresión</h3>
    <p>
      Control total de servicios, clientes frecuentes y facturación diaria.
    </p>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-school"></i> Instituciones</h3>
    <p>
      Reportes claros, control por áreas y trazabilidad completa.
    </p>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-building"></i> Empresas</h3>
    <p>
      Facturación de impresión interna con auditoría y estadísticas.
    </p>
  </div>
</div>
</section>

<section class="info-section animate__animated animate__fadeInUp">
  <div class="card">
    <h3><i class="fa-solid fa-shield-halved"></i> Cumplimiento y Seguridad</h3>
    <p>
      Roles, permisos, auditoría y control de accesos.
      Datos protegidos, siempre.
    </p>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-layer-group"></i> Escalable</h3>
    <p>
      Desde un solo equipo hasta múltiples sucursales.
      PDL crece contigo.
    </p>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-wifi"></i> Online & Offline</h3>
    <p>
      Funciona incluso con conexión inestable.
      Porque la facturación no espera.
    </p>
  </div>
</section>

<section id="billing-features" class="team-section animate__animated animate__fadeInUp">
  <h2><i class="fa-solid fa-file-invoice-dollar me-1"></i>Facturación Avanzada</h2>
  <p class="subtitle">
    Control financiero inteligente, rápido y seguro.
  </p>

  <section class="info-section">
    <div class="card">
      <h3><i class="fa-solid fa-bolt"></i> Facturación Rápida</h3>
      <p>
        Genera facturas en segundos con plantillas listas para usar.
      </p>
    </div>

    <div class="card">
      <h3><i class="fa-solid fa-chart-line"></i> Reportes Detallados</h3>
      <p>
        Estadísticas claras de ventas, servicios y rendimiento.
      </p>
    </div>

    <div class="card">
      <h3><i class="fa-solid fa-mobile-screen"></i> Multiplataforma</h3>
      <p>
        Accede desde PC, tablet o móvil sin perder control.
      </p>
    </div>
  </section>
</section>

  <!-- Sección de Trabajadores -->
  <section id="team" class="team-section animate__animated animate__fadeInUp">
    <h2><i class="fa-solid fa-hands-helping me-1"></i>Nuestro Equipo</h2>
    <p class="subtitle">Conoce a los profesionales que hacen posible el Proyecto de Desarrollo Local (PDL Visiones). Expertos en tecnología, atención al cliente y soluciones empresariales.</p>
    
    <div id="teamCarousel" class="carousel slide" data-bs-ride="carousel">
<!-- Ejemplo para el primer miembro - Laicep Estrella Suñol García -->
<div class="carousel-item active">
    <div class="team-member">
        <!-- MODIFICADO: Añadida clase team-member-img y atributos data-* -->
        <img src="assets/team/0.jpg" 
             alt="Laicep Estrella Suñol García - Directora General" 
             class="team-member-img"
             data-name="Laicep Estrella Suñol García"
             data-role="Director General"
             data-desc="Más de 15 años de experiencia en desarrollo empresarial y Jurídico. Lidera el equipo con visión innovadora."
             onclick="openTeamMemberModal(this)">
        <h4>Laicep Estrella Suñol García</h4>
        <div class="team-member-role">Director General</div>
        <p class="team-member-desc">Más de 15 años de experiencia en desarrollo empresarial y Jurídico. Lidera el equipo con visión innovadora.</p>
    </div>
</div>

<!-- Segundo miembro - Annia Guerra Loureiro -->
<div class="carousel-item">
    <div class="team-member">
        <img src="assets/team/2.jpg" 
             alt="Annia Guerra Loureiro - Especialista en Facturación y Marketing" 
             class="team-member-img"
             data-name="Annia Guerra Loureiro"
             data-role="Especialista en Facturación y Marketing"
             data-desc="Gestión Precisa del ciclo de Facturación y del apoyo administrativo al área comercial. Un puente fundamental entre las finanzas operativas y el marketing"
             onclick="openTeamMemberModal(this)">
        <h4>Annia Guerra Loureiro</h4>
        <div class="team-member-role">Especialista en Facturación y Marketing</div>
        <p class="team-member-desc">Gestión Precisa del ciclo de Facturación y del apoyo administrativo al área comercial. Un puente fundamental entre las finanzas operativas y el marketing</p>
    </div>
</div>

<!-- Tercer miembro - Midelis Ramírez Cruz -->
<div class="carousel-item">
    <div class="team-member">
        <img src="assets/team/3.jpg" 
             alt="Midelis Ramírez Cruz - Jefe de Soporte Técnico" 
             class="team-member-img"
             data-name="Midelis Ramírez Cruz"
             data-role="Jefe de Soporte Técnico"
             data-desc="Brinda soporte especializado y soluciona problemas técnicos con rapidez y eficiencia."
             onclick="openTeamMemberModal(this)">
        <h4>Midelis Ramírez Cruz</h4>
        <div class="team-member-role">Jefe de Soporte Técnico</div>
        <p class="team-member-desc">Brinda soporte especializado y soluciona problemas técnicos con rapidez y eficiencia.</p>
    </div>
</div>

<!-- Cuarto miembro - Rafael Serrano Maure -->
<div class="carousel-item">
    <div class="team-member">
        <img src="assets/team/1.jpg" 
             alt="Rafael Serrano Maure - Administrador General" 
             class="team-member-img"
             data-name="Rafael Serrano Maure"
             data-role="Líder Operativo y eje central de nuestra estructura"
             data-desc="Gestiona, optimiza y supervisa los procesos administrativos y de recursos para garantizar la eficiencia y el soporte de todo el equipo."
             onclick="openTeamMemberModal(this)">
        <h4>Rafael Serrano Maure</h4>
        <div class="team-member-role">Líder Operativo y eje central de nuestra estructura</div>
        <p class="team-member-desc">Gestiona, optimiza y supervisa los procesos administrativos y de recursos para garantizar la eficiencia y el soporte de todo el equipo.</p>
    </div>
</div>

<!-- Quinto miembro - Franklyn Ramos Lamadrid (Kaky°) -->
<div class="carousel-item">
    <div class="team-member">
        <img src="assets/team/4.jpg" 
             alt="Franklyn Ramos Lamadrid - Desarrollador Senior" 
             class="team-member-img"
             data-name="Franklyn Ramos Lamadrid (Kaky°)"
             data-role="Desarrollador Senior y Diseñador Gráfico"
             data-desc="Con más de 30 años de experiencia se especializa en soluciones innovadoras de impresión, Diseño y optimización de procesos con las últimas tecnologías."
             onclick="openTeamMemberModal(this)">
        <h4>Franklyn Ramos Lamadrid (Kaky°)</h4>
        <div class="team-member-role">Desarrollador Senior y Diseñador Gráfico</div>
        <p class="team-member-desc">Con más de 30 años de experiencia se especializa en soluciones innovadoras de impresión, Diseño y optimización de procesos con las últimas tecnologías.</p>
    </div>
</div>      </div>
      
      <div class="team-carousel-controls">
        <button class="team-carousel-btn" type="button" data-bs-target="#teamCarousel" data-bs-slide="prev">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button class="team-carousel-btn" type="button" data-bs-target="#teamCarousel" data-bs-slide="next">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </section>

<!-- Sección de Contacto Mejorada -->
<section id="contacto" class="contact-section animate__animated animate__fadeInUp" aria-label="Sección de contacto">
    <h2><i class="fa-solid fa-envelope me-1"></i> Contáctanos</h2>
    <p>¿Tienes dudas o necesitas soporte? Estamos disponibles para asesorarte en tu proyecto de facturación e impresión.</p>
    
    <div class="contact-info">
        <div class="contact-item">
            <i class="fas fa-map-marker-alt"></i>
            <h3>Dirección</h3>
            <p>Calle Máximo Gómez. S/N e/Camilo Cienfuegos y Lugareño.<br>
            Nuevitas, Camagüey.<br>
            Cp: 72510</p>
            <a href="https://maps.app.goo.gl/bVDmo17S8VNa7Ph29" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-external-link-alt" style="font-size: 0.9em; margin-right: 5px;"></i> Ver en Google Maps
            </a>
        </div>
        
        <div class="contact-item">
            <i class="fas fa-envelope"></i>
            <h3>Email</h3>
            <a href="mailto:soporte_pdlvisiones@gmail.com?subject=Sobre el Sistema de Facturación PDL Visiones">soporte_pdlvisiones@gmail.com</a><br>
			<a href="mailto:direccion@pdlvisiones.com?subject=Sobre el Sistema de Facturación PDL Visiones">direccion@pdlvisiones.com</a><br>
			<a href="mailto:facturacion@pdlvisiones.com?subject=Sobre el Sistema de Facturación PDL Visiones">facturacion@pdlvisiones.com</a><br>
        </div>
        
        <div class="contact-item">
            <i class="fas fa-phone"></i>
            <h3>Teléfonos</h3>
            <a href="tel:+5359860773">+53 5 986 0773 Soporte</a><br>
			<a href="tel:+5359962404">+53 5 996 2404 Dirección</a><br>
			<a href="tel:+5359899690">+53 5 989 9690 Facturación y Marketing</a>
        </div>
        
        <div class="contact-item">
            <i class="fas fa-clock"></i>
            <h3>Horario de Atención</h3>
            <p>Lunes a Viernes: 8:00 AM - 5:00 PM<br>
            Sábados: 9:00 AM - 1:00 PM</p>
        </div>
    </div>
</section>

</main>
<footer>
  <div class="footer-container">

    <!-- Columna 1: Marca -->
    <div class="footer-col">
      <h4><img src="assets/logov.png" alt="Logo PDL Visiones" width="36" height="36" style="margin-right: 8px;">SISFACT PDL Visiones</h4>
      <p>
        Plataforma de facturación e impresión inteligente.<br>
        © <?= date('Y') ?> Todos los derechos reservados.<br>
		Unicornio Software° - Kaky®<br>
		<span class="text-success fw-bold">"Donde tu visión toma forma"</span>
      </p>
    </div>

    <!-- Columna 2: Contacto -->
<div class="footer-col">
    <h4>Contacto</h4>
    <p>
        <a href="mailto:soporte_pdlvisiones@gmail.com"><i class="fas fa-envelope me-1"></i>soporte_pdlvisiones@gmail.com</a><br>
        <a href="tel:+5359860773"><i class="fas fa-phone me-1"></i>+53 5986 0773</a><br>
        <small><i class="fas fa-clock me-1"></i>Horario: Lun - Vie, 8:00 AM - 5:00 PM, Sábados: 9:00 AM - 1:00 PM</small>
    </p>
    
    <!-- MODIFICADO: Contenedor para los dos botones -->
    <div class="button-group">
        <button class="back-to-top" id="back-to-top">
            <i class="fa-solid fa-arrow-up"></i> Ir al inicio
        </button>
        
        <!-- NUEVO: Botón "Iniciar Sesión" en verde -->
        <a href="login.php" class="back-to-top-green" id="footer-login-btn">
            <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
        </a>
        
        <!-- El botón de refresh se inyectará aquí dinámicamente -->
    </div>
</div>
<!-- Columna 3: Redes -->
<div class="footer-col footer-social">
<!-- Columna 3: Redes Sociales Mejorada -->
<div class="footer-col footer-social">
  <h4 class="footer-title">Síguenos</h4>
  
  <div class="social-links-wrapper">
      
      <!-- Facebook -->
      <a href="https://facebook.com/pdlvisiones" target="_blank" rel="noopener noreferrer" 
         class="social-link facebook" aria-label="Facebook">
        <i class="fa-brands fa-facebook-f"></i>
        <span class="social-tooltip"></span>
      </a>

     <a href="https://x.com/pdlvisiones" target="_blank" rel="noopener noreferrer" 
   class="social-link x-twitter" aria-label="X (Twitter)">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" 
         style="width: 18px; height: 18px; fill: currentColor;">
        <path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/>
    </svg>
    <span class="social-tooltip"></span>
</a>
      
      <!-- LinkedIn -->
      <a href="https://linkedin.com/unicorniocuba/pdlvisiones" target="_blank" rel="noopener noreferrer" 
         class="social-link linkedin" aria-label="LinkedIn">
        <i class="fa-brands fa-linkedin-in"></i>
        <span class="social-tooltip"></span>
      </a>
      
      <!-- Instagram -->
      <a href="https://instagram.com/pdlvisiones" target="_blank" rel="noopener noreferrer" 
         class="social-link instagram" aria-label="Instagram">
        <i class="fa-brands fa-instagram"></i>
        <span class="social-tooltip"></span>
      </a>
      
      <!-- YouTube (Enlace corregido) -->
      <a href="https://youtube.com/@pdlvisiones" target="_blank" rel="noopener noreferrer" 
         class="social-link youtube" aria-label="YouTube">
        <i class="fa-brands fa-youtube"></i>
        <span class="social-tooltip"></span>
      </a>
      
      <!-- WhatsApp -->
      <a href="https://api.whatsapp.com/send?phone=<?php echo $whatsapp_numero_formateado; ?>" target="_blank" rel="noopener noreferrer" 
         class="social-link whatsapp" aria-label="WhatsApp">
        <i class="fa-brands fa-whatsapp"></i>
        <span class="social-tooltip"></span>
      </a>

  </div>
</div>

<h4 class="footer-title">Legal</h4>
    <p class="footer-legal">
        <a href="#" onclick="openPrivacyModal(); return false;">Política de Privacidad</a> |
        <a href="#" onclick="openTermsModal(); return false;">Términos y Condiciones</a> |
        <a href="#" onclick="openCookiesModal(); return false;">Política de Cookies</a>
    </p>
</div>

</footer>

<!-- Botón contacto fijo -->
<button id="contact-panel" role="button" tabindex="0" aria-label="Contactar soporte">
  <i class="fa-solid fa-headset"></i>
  <span>Soporte</span>
</button>

<!-- Chat flotante actualizado - CORREGIDO ICONOS -->
<div id="chat-widget" role="dialog" aria-modal="true" aria-labelledby="chat-header-text" aria-hidden="true">
    
    <div id="chat-header">
        <div style="display:flex; align-items:center; gap: 10px;">
            <i class="fa-solid fa-headset" style="font-size: 18px;"></i>
            <span id="chat-header-text" style="font-size: 14px; font-weight: 600;">Soporte WhatsApp</span>
        </div>
        
        <div id="close-chat-btn" role="button" style="cursor: pointer; padding: 5px; margin-left: auto;">
            <i class="fa-solid fa-xmark" style="font-size: 16px;"></i>
        </div>
    </div>
  
    <div id="chat-body">
        <div style="margin-bottom: 15px;">
            <p style="margin: 0 0 5px 0; font-size: 13px; color: #25d366; font-weight: 600;">
                <i class="fa-solid fa-circle-info" style="margin-right: 5px;"></i>SOPORTE PDL VISIONES
            </p>
            <?php if ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado): ?>
                <p style="margin: 0; font-size: 12px; color: #8696a0; line-height: 1.4;">
                    ¿En qué podemos ayudarte? Déjanos tu mensaje y te responderemos por WhatsApp.
                </p>
            <?php else: ?>
                <div style="background: rgba(255, 193, 7, 0.08); border: 1px solid rgba(255, 193, 7, 0.2); 
                            border-radius: 6px; padding: 10px; margin-top: 5px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ffc107; margin-right: 6px; font-size: 12px;"></i>
                    <span style="font-size: 11px; color: #ffc107;">
                        WhatsApp temporalmente deshabilitado. Contacta por email o teléfono.
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <form id="chat-form" novalidate style="display: flex; flex-direction: column; gap: 10px;">
            <!-- Email y Teléfono en línea -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div class="form-group">
                    <input type="email" id="chat-mail" name="mail" placeholder="Email" 
                           style="width: 100%; padding: 8px 10px 8px 32px; font-size: 12px; height: 36px;" />
                    <div id="email-validation" class="validation-message"></div>
                </div>
                <div class="form-group">
                    <input type="tel" id="chat-phone" name="phone" placeholder="Teléfono" 
                           style="width: 100%; padding: 8px 10px 8px 32px; font-size: 12px; height: 36px;" />
                    <div id="phone-validation" class="validation-message"></div>
                </div>
            </div>
            
            <!-- Nombre -->
            <div class="form-group">
                <input type="text" id="chat-name" name="name" placeholder="Tu nombre completo *" required aria-required="true"
                       style="width: 100%; padding: 8px 10px 8px 32px; font-size: 12px; height: 36px;" />
                <div id="name-validation" class="validation-message"></div>
            </div>
            
            <!-- Mensaje -->
            <div class="form-group">
                <textarea id="chat-message" name="message" placeholder="Describe tu consulta o problema *" 
                          required aria-required="true" rows="3"
                          style="width: 100%; padding: 8px 10px 8px 32px; font-size: 12px; min-height: 80px; resize: vertical;"></textarea>
                <div id="message-validation" class="validation-message"></div>
            </div>
            
            <!-- Texto obligatorio -->
            <small style="display: block; color: #666; margin: -5px 0 5px 0; font-size: 10px; text-align: right;">
                * Campos obligatorios
            </small>
            
            <!-- Botón de envío -->
            <?php if ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado): ?>
                <button type="submit" aria-label="Enviar mensaje por WhatsApp"
                        style="background: linear-gradient(to right, #25d366 0%, #1da851 100%); 
                               color: white; border: none; border-radius: 6px; padding: 10px;
                               font-size: 13px; font-weight: 600; cursor: pointer; 
                               display: flex; align-items: center; justify-content: center; gap: 8px;
                               transition: all 0.3s ease; height: 40px;">
                    <i class="fab fa-whatsapp" style="font-size: 16px;"></i>
                    <span>Enviar por WhatsApp</span>
                </button>
            <?php else: ?>
                <button type="button" onclick="enviarPorEmail()"
                        style="background: linear-gradient(to right, #6c757d 0%, #5a6268 100%); 
                               color: white; border: none; border-radius: 6px; padding: 0px;
                               font-size: 13px; font-weight: 600; cursor: pointer; 
                               display: flex; align-items: center; justify-content: center; gap: 8px;
                               transition: all 0.3s ease; height: 40px;">
                    <i class="fa-solid fa-envelope" style="font-size: 16px;"></i>
                    <span>Enviar por Email</span>
                </button>
            <?php endif; ?>
            
            <!-- Feedback -->
            <div id="chat-feedback" role="alert" aria-live="polite" 
                 style="font-size: 11px; padding: 6px; border-radius: 4px; margin-top: 0px;"></div>
        </form>
        
        <!-- Información de contacto alternativa -->
        <div style="margin-top: 0px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
            <p style="font-size: 10px; color: #8696a0; margin: 0 0 5px 0; font-weight: 600;">
                <i class="fa-solid fa-phone-alt" style="margin-right: 5px; font-size: 10px;"></i>
                Contacto alternativo:
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <a href="tel:+5359860773" 
                   style="font-size: 10px; color: #25d366; text-decoration: none; padding: 4px 8px;
                          background: rgba(37, 211, 102, 0.1); border-radius: 4px;">
                    📞 +53 5986 0773
                </a>
                <a href="mailto:soporte_pdlvisiones@gmail.com" 
                   style="font-size: 10px; color: #007bff; text-decoration: none; padding: 4px 8px;
                          background: rgba(0, 123, 255, 0.1); border-radius: 4px;">
                    ✉️ soporte_pdlvisiones@gmail.com
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL DE MODO MANTENIMIENTO (MEJORADO) -->
<!-- ========================================================= -->
<div class="win11-modal-overlay maintenance-overlay" id="maintenanceModal" data-no-close-outside="true">
  <div class="win11-modal" style="border: 1px solid #ffc107; box-shadow: 0 0 40px rgba(255, 193, 7, 0.2); max-width: 550px; max-height: 700px;">
    
    <!-- Header -->
    <div class="win11-modal-header" style="border-bottom: 1px solid rgba(255, 193, 7, 0.3);">
      <div class="modal-title-wrapper">
        <div class="modal-icon maintenance-icon-pulse">
          <i class="fa-solid fa-helmet-safety"></i>
        </div>
        <h3 class="modal-title" style="color: #ffc107;">🛠️ Modo Mantenimiento Activo</h3>
      </div>
      <!-- Botón cerrar (solo visual, el bloqueo persiste) -->
      <button class="win11-modal-close" onclick="closeMaintenanceModal()" title="Cerrar ventana (El bloqueo persiste)">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    
    <!-- Body -->
    <div class="win11-modal-body" style="padding: 15px;">
      
      <p style="font-size: 1.1em; color: #fff; margin-bottom: 20px; text-align: center;">
        <strong>SISFACT PDL Visiones</strong> se encuentra actualmente en modo de mantenimiento. 
        Estamos realizando mejoras para ofrecerte un mejor servicio.
      </p>

      <!-- Bloque: Qué está pasando -->
<div style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 10px; margin-bottom: 25px; border-left: 3px solid #0dcaf0;">
    <h5 style="color: #0dcaf0; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center;">
        <i class="fa-solid fa-circle-info me-2"></i> ¿Qué está pasando?
    </h5>
    
    <div style="color: #ccc; line-height: 0.9;  max-height: 800px;">
        <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
            <i class="fa-solid fa-arrow-up-right-dots me-3" style="color: #0dcaf0; margin-top: 3px;"></i>
            <div>
                <strong style="color: #fff;">Actualización del sistema</strong>
                <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                    Mejoras en funcionalidades y corrección de errores
                </div>
            </div>
        </div>
        
        <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
            <i class="fa-solid fa-shield-halved me-3" style="color: #20c997; margin-top: 3px;"></i>
            <div>
                <strong style="color: #fff;">Mejoras de seguridad</strong>
                <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                    Implementación de nuevas medidas de protección
                </div>
            </div>
        </div>
        
        <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
            <i class="fa-solid fa-gauge-high me-3" style="color: #ff6b6b; margin-top: 3px;"></i>
            <div>
                <strong style="color: #fff;">Optimización de rendimiento</strong>
                <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                    Velocidad mejorada y mayor estabilidad
                </div>
            </div>
        </div>
        
        <div style="display: flex; align-items: flex-start;">
            <i class="fa-solid fa-database me-3" style="color: #9d4edd; margin-top: 3px;"></i>
            <div>
                <strong style="color: #fff;">Backup de datos</strong>
                <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                    Respaldo completo de la información
                </div>
            </div>
        </div>
    </div>
</div>

      <!-- Bloque: Contacto de Emergencia -->
      <div style="background: rgba(255, 193, 7, 0.05); border-radius: 8px; padding: 15px; border: 1px dashed rgba(255, 193, 7, 0.3);">
        <h5 style="color: #ffc107; font-size: 0.95rem; margin-bottom: 10px; text-align: center;">
          Para emergencias, contacta con soporte:
        </h5>
        <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
            
            <!-- WhatsApp -->
            <?php if ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado): ?>
            <a href="https://api.whatsapp.com/send?phone=<?php echo $whatsapp_numero_formateado; ?>" target="_blank" 
               class="btn btn-sm btn-outline-success" style="border-radius: 20px;">
                <i class="fab fa-whatsapp me-1"></i> WhatsApp
            </a>
            <?php endif; ?>

            <!-- Correo -->
            <a href="mailto:soporte_pdlvisiones@gmail.com" class="btn btn-sm btn-outline-light" style="border-radius: 20px;">
                <i class="fa-solid fa-envelope me-1"></i> Correo
            </a>

            <!-- Teléfono -->
            <a href="tel:+5359860773" class="btn btn-sm btn-outline-info" style="border-radius: 20px;">
                <i class="fa-solid fa-phone me-1"></i> +53 5986 0773
            </a>
        </div>
      </div>

    </div>
    
    <!-- Footer -->
    <div class="win11-modal-footer" style="justify-content: space-between; background: rgba(0,0,0,0.2);">
      <button class="modal-btn modal-btn-secondary" onclick="closeMaintenanceModal()">
        <i class="fa-solid fa-eye"></i> Solo mirar
      </button>
      
      <!-- Botón con ID para la lógica JS -->
      <button id="btn-recheck-maint" class="modal-btn modal-btn-primary" onclick="recheckMaintenanceStatus()" 
              style="background-color: #ffc107; color: #000; border: none; font-weight: bold;">
        <i class="fa-solid fa-rotate-right me-2"></i> Volver a comprobar
      </button>
    </div>
  </div>
</div>





<!-- MODAL SISTEMA PRINCIPAL - Windows 11 Style -->
<div class="win11-modal-overlay" id="systemModal">
  <div class="win11-modal">
    <div class="win11-modal-header">
      <div class="modal-title-wrapper">
        <div class="modal-icon">
          <img src="assets/logov.png" alt="Logo PDL Visiones" width="36" height="36">
        </div>
        <h3 class="modal-title"><span class="">SISFACT PDL Visiones</span></h3>
      </div>
      <button class="win11-modal-close" onclick="closeSystemModal()" aria-label="Cerrar modal">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    
    <div class="win11-modal-body">
      <p><strong>SISFACT Proyecto de Desarrollo Local "Visiones":</strong> Plataforma integral para facturación y servicios de impresión, optimizada para tu negocio.</p>
      
      <p><strong>Facturación inteligente</strong> para servicios de impresión. <strong>Menos papel perdido.</strong> <strong>Más control.</strong> <strong>Más ganancias.</strong></p>
      
      <p>Nuestro sistema está diseñado específicamente para negocios de impresión, ofreciendo herramientas especializadas que te permiten optimizar cada aspecto de tu operación, desde la cotización hasta la entrega final.</p>
      
      <div class="modal-features">
        <div class="modal-feature">
          <div class="feature-icon">
            <i class="fa-solid fa-bolt"></i>
          </div>
          <h4 class="feature-title">Rápido y Eficiente</h4>
          <p class="feature-desc">Procesamiento veloz de facturas e impresiones</p>
        </div>
        
        <div class="modal-feature">
          <div class="feature-icon">
            <i class="fa-solid fa-shield-halved"></i>
          </div>
          <h4 class="feature-title">Seguridad Total</h4>
          <p class="feature-desc">Protección avanzada de datos y transacciones</p>
        </div>
        
        <div class="modal-feature">
          <div class="feature-icon">
            <i class="fa-solid fa-cloud"></i>
          </div>
          <h4 class="feature-title">Acceso en la Nube</h4>
          <p class="feature-desc">Disponible desde cualquier dispositivo</p>
        </div>
        
        <div class="modal-feature">
          <div class="feature-icon">
            <i class="fa-solid fa-chart-line"></i>
          </div>
          <h4 class="feature-title">Reportes Inteligentes</h4>
          <p class="feature-desc">Análisis detallado de costos y ganancias</p>
        </div>
      </div>
    </div>
    
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-secondary" onclick="closeSystemModal()">
        <i class="fa-solid fa-times-circle"></i> Cerrar
      </button>
      <!-- MODIFICADO: Agregado id="btn-login-modal" -->
      <button id="btn-login-modal" class="modal-btn modal-btn-primary" onclick="window.location.href='login.php'">
        <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
      </button>
    </div>
  </div>
</div>

<!-- MODAL POLÍTICA DE PRIVACIDAD - Windows 11 Style -->
<div class="win11-modal-overlay" id="privacyModal">
  <div class="win11-modal">
    <div class="win11-modal-header">
      <div class="modal-title-wrapper">
        <div class="modal-icon">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <h3 class="modal-title">Política de Privacidad</h3>
      </div>
      <button class="win11-modal-close" onclick="closePrivacyModal()" aria-label="Cerrar modal">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    
    <div class="win11-modal-body">
      <div class="privacy-content">
        <div class="privacy-section">
          <h4><i class="fa-solid fa-info-circle"></i> Información que Recopilamos</h4>
          <p>En SISFACT PDL Visiones, recopilamos únicamente la información necesaria para brindar nuestros servicios:</p>
          <ul class="privacy-list">
            <li>Datos de contacto (nombre, email, teléfono)</li>
            <li>Información de facturación y transacciones</li>
            <li>Registros de uso del sistema</li>
            <li>Preferencias de configuración</li>
          </ul>
        </div>
        
        <div class="privacy-section">
          <h4><i class="fa-solid fa-lock"></i> Protección de Datos</h4>
          <p>Implementamos medidas de seguridad avanzadas para proteger tu información:</p>
          <ul class="privacy-list">
            <li>Cifrado de extremo a extremo</li>
            <li>Acceso controlado por roles y permisos</li>
            <li>Copias de seguridad regulares</li>
            <li>Monitoreo constante de seguridad</li>
          </ul>
        </div>
        
        <div class="privacy-section">
          <h4><i class="fa-solid fa-handshake"></i> Compartir Información</h4>
          <p>No compartimos tu información personal con terceros, excepto cuando sea requerido por ley o necesario para brindar nuestros servicios.</p>
        </div>
        
        <div class="privacy-section">
          <h4><i class="fa-solid fa-user-check"></i> Tus Derechos</h4>
          <p>Tienes derecho a acceder, rectificar, cancelar u oponerte al tratamiento de tus datos personales.</p>
        </div>
      </div>
    </div>
    
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-primary" onclick="closePrivacyModal()">
        <i class="fa-solid fa-check-circle"></i> Aceptar
      </button>
      <button class="modal-btn modal-btn-secondary" onclick="printPrivacy()">
        <i class="fa-solid fa-print"></i> Imprimir
      </button>
    </div>
  </div>
</div>

<!-- MODAL TÉRMINOS Y CONDICIONES - Windows 11 Style -->
<div class="win11-modal-overlay" id="termsModal">
  <div class="win11-modal">
    <div class="win11-modal-header">
      <div class="modal-title-wrapper">
        <div class="modal-icon">
          <i class="fa-solid fa-file-contract"></i>
        </div>
        <h3 class="modal-title">Términos y Condiciones</h3>
      </div>
      <button class="win11-modal-close" onclick="closeTermsModal()" aria-label="Cerrar modal">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    
    <div class="win11-modal-body">
      <div class="terms-content">
        <div class="terms-section">
          <h4><i class="fa-solid fa-gavel"></i> Aceptación de Términos</h4>
          <p>Al utilizar SISFACT PDL Visiones, aceptas cumplir con estos términos y condiciones. Si no estás de acuerdo, por favor no uses nuestros servicios.</p>
        </div>
        
        <div class="terms-section">
          <h4><i class="fa-solid fa-desktop"></i> Uso del Sistema</h4>
          <p>El sistema está diseñado para uso empresarial en negocios de impresión y facturación. No está permitido:</p>
          <ul class="privacy-list">
            <li>Usar el sistema para actividades ilegales</li>
            <li>Intentar acceder a datos de otros usuarios</li>
            <li>Realizar ingeniería inversia del software</li>
            <li>Compartir credenciales de acceso</li>
          </ul>
        </div>
        
        <div class="terms-section">
          <h4><i class="fa-solid fa-money-bill-wave"></i> Facturación y Pagos</h4>
          <p>Los servicios de facturación electrónica cumplen con las normativas fiscales vigentes. Los pagos deben realizarse según los términos acordados.</p>
        </div>
        
        <div class="terms-section">
          <h4><i class="fa-solid fa-ban"></i> Limitación de Responsabilidad</h4>
          <p>SISFACT PDL Visiones no se responsabiliza por pérdidas o daños derivados del uso del sistema, excepto en casos de negligencia comprobada.</p>
        </div>
      </div>
    </div>
    
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-primary" onclick="closeTermsModal()">
        <i class="fa-solid fa-check-circle"></i> Aceptar
      </button>
      <button class="modal-btn modal-btn-secondary" onclick="printTerms()">
        <i class="fa-solid fa-print"></i> Imprimir
      </button>
    </div>
  </div>
</div>

<!-- MODAL COOKIES - Windows 11 Style -->
<div class="win11-modal-overlay" id="cookiesModal">
  <div class="win11-modal">
    <div class="win11-modal-header">
      <div class="modal-title-wrapper">
        <div class="modal-icon">
          <i class="fa-solid fa-cookie-bite"></i>
        </div>
        <h3 class="modal-title">Política de Cookies</h3>
      </div>
      <button class="win11-modal-close" onclick="closeCookiesModal()" aria-label="Cerrar modal">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    
    <div class="win11-modal-body">
      <div class="cookies-content">
        <p>Utilizamos cookies para mejorar tu experiencia en nuestro sitio web. A continuación, explicamos qué cookies usamos y por qué.</p>
        
        <div class="cookie-types">
          <div class="cookie-type">
            <h5><i class="fa-solid fa-cookie"></i> Cookies Esenciales</h5>
            <p>Necesarias para el funcionamiento básico del sitio. Permiten la navegación y el uso de funciones seguras.</p>
          </div>
          
          <div class="cookie-type">
            <h5><i class="fa-solid fa-chart-bar"></i> Cookies de Analítica</h5>
            <p>Nos ayudan a entender cómo los usuarios interactúan con el sitio para mejorarlo continuamente.</p>
          </div>
          
          <div class="cookie-type">
            <h5><i class="fa-solid fa-user-cog"></i> Cookies de Preferencias</h5>
            <p>Recuerdan tus ajustes y preferencias para personalizar tu experiencia.</p>
          </div>
          
          <div class="cookie-type">
            <h5><i class="fa-solid fa-ad"></i> Cookies de Marketing</h5>
            <p>Permiten mostrar anuncios relevantes según tus intereses y visitas anteriores.</p>
          </div>
        </div>
        
        <div class="privacy-section" style="margin-top: 25px;">
          <h4><i class="fa-solid fa-sliders-h"></i> Control de Cookies</h4>
          <p>Puedes gestionar tus preferencias de cookies en cualquier momento. Ten en cuenta que desactivar algunas cookies puede afectar la funcionalidad del sitio.</p>
        </div>
      </div>
    </div>
    
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-primary" onclick="acceptAllCookies()">
        <i class="fa-solid fa-check-double"></i> Aceptar Todas
      </button>
      <button class="modal-btn modal-btn-secondary" onclick="configureCookies()">
        <i class="fa-solid fa-cog"></i> Configurar
      </button>
      <button class="modal-btn modal-btn-danger" onclick="rejectCookies()">
        <i class="fa-solid fa-ban"></i> Rechazar
      </button>
    </div>
  </div>
</div>

<!-- MODAL EXISTENTE CONVERTIDO - Windows 11 Style -->
<div class="win11-modal-overlay" id="customModal">
  <div class="win11-modal">
    <div class="win11-modal-header">
      <div class="modal-title-wrapper">
        <div class="modal-icon">
          <i class="fa-solid fa-info-circle"></i>
        </div>
        <h3 class="modal-title">Acerca de SISFACT</h3>
      </div>
      <button class="win11-modal-close" onclick="closeModal()" aria-label="Cerrar modal">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>
    
    <div class="win11-modal-body">
      <div class="modal-content">
        <div class="text-block">
          <p><strong>SISFACT Proyecto de Desarrollo Local "Visiones":</strong> Plataforma integral para facturación y servicios de impresión, optimizada para tu negocio.</p>
          
          <p><strong>Facturación inteligente</strong> para servicios de impresión. <strong>Menos papel perdido.</strong> <strong>Más control.</strong> <strong>Más ganancias.</strong></p>
          
          <p>Nuestro sistema está diseñado específicamente para negocios de impresión, ofreciendo herramientas especializadas que te permiten optimizar cada aspecto de tu operación, desde la cotización hasta la entrega final.</p>
        </div>
        
        <div class="modal-features" style="margin-top: 20px;">
          <div class="modal-feature">
            <div class="feature-icon">
              <i class="fa-solid fa-print"></i>
            </div>
            <h4 class="feature-title">Impresión Controlada</h4>
            <p class="feature-desc">Gestiona cada impresión con precisión</p>
          </div>
          
          <div class="modal-feature">
            <div class="feature-icon">
              <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <h4 class="feature-title">Facturación Rápida</h4>
            <p class="feature-desc">Genera facturas en segundos</p>
          </div>
          
          <div class="modal-feature">
            <div class="feature-icon">
              <i class="fa-solid fa-chart-pie"></i>
            </div>
            <h4 class="feature-title">Reportes Detallados</h4>
            <p class="feature-desc">Analiza costos y ganancias</p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-secondary" onclick="closeModal()">
        <i class="fa-solid fa-times"></i> Cerrar
      </button>
      <button class="modal-btn modal-btn-primary" onclick="window.location.href='login.php'">
        <i class="fa-solid fa-rocket"></i> Comenzar
      </button>
    </div>
  </div>
</div>

<!-- SweetAlert2 -->
<script src="js/sweetalert211.js"></script>
<script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script>
// ============================================
// 1. CONFIGURACIÓN GLOBAL DE SWEETALERT (TEMA DARK)
// ============================================
const SwalTheme = Swal.mixin({
    background: 'linear-gradient(145deg, #1e1e1e 0%, #2d2d30 100%)',
    color: '#e0e0e0',
    customClass: {
        container: 'swal2-container',
        popup: 'swal2-popup',
        title: 'swal2-title',
        htmlContainer: 'swal2-html-container',
        confirmButton: 'swal2-confirm',
        denyButton: 'swal2-deny',
        cancelButton: 'swal2-cancel',
        closeButton: 'swal2-close',
        icon: 'swal2-icon',
        input: 'swal2-input',
        validationMessage: 'swal2-validation-message'
    },
    buttonsStyling: false,
    showClass: { popup: 'swal2-show' },
    hideClass: { popup: 'swal2-hide' }
});

// Sobreescribir Swal global
window.Swal = SwalTheme;

// ============================================
// 2. VARIABLES GLOBALES DEL CHAT
// ============================================
let chatOpen = false;
let chatClosing = false;

// ============================================
// 3. FUNCIONES DE LÓGICA DEL CHAT
// ============================================

function openChat() {
    if (chatOpen || chatClosing) return;
    
    const chat = document.getElementById('chat-widget');
    const btn = document.getElementById('contact-panel');
    const form = document.getElementById('chat-form'); 
    
    if (form) {
        form.reset(); 
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => { input.classList.remove('input-error', 'input-success'); });
        const messages = form.querySelectorAll('.validation-message');
        messages.forEach(msg => { msg.textContent = ''; msg.className = 'validation-message'; });
    }
    
    chat.style.display = 'flex';
    setTimeout(() => { chat.classList.add('show'); }, 10);
    btn.classList.add('circle-mode');
    btn.innerHTML = `<i class="fa-brands fa-whatsapp" style="font-size: 32px;"></i><span style="display:none;">Soporte</span>`;
    chatOpen = true;
    setTimeout(setupRealTimeValidation, 300);
}

function closeChat() {
    if (!chatOpen || chatClosing) return;
    chatClosing = true;
    
    const chat = document.getElementById('chat-widget');
    const btn = document.getElementById('contact-panel');
    
    chat.classList.remove('show');
    
    setTimeout(() => {
        chat.style.display = 'none';
        btn.classList.remove('circle-mode');
        btn.innerHTML = `<i class="fa-solid fa-headset"></i><span>Soporte</span>`;
        chatOpen = false;
        chatClosing = false;
    }, 400);
}

function toggleChat() {
    if (chatClosing) return;
    if (chatOpen) { closeChat(); } else { openChat(); }
}

// ============================================
// 4. GESTIÓN DE MODALES (WINDOWS 11 STYLE)
// ============================================

function openSystemModal() {
    document.getElementById('systemModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeSystemModal() {
    document.getElementById('systemModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function openPrivacyModal() { document.getElementById('privacyModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closePrivacyModal() { document.getElementById('privacyModal').classList.remove('active'); document.body.style.overflow = 'auto'; }

function openTermsModal() { document.getElementById('termsModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeTermsModal() { document.getElementById('termsModal').classList.remove('active'); document.body.style.overflow = 'auto'; }

function openCookiesModal() { document.getElementById('cookiesModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeCookiesModal() { document.getElementById('cookiesModal').classList.remove('active'); document.body.style.overflow = 'auto'; }

function openModal() { // Modal genérico legacy
    document.getElementById('customModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('customModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function printPrivacy() {
    const content = document.querySelector('#privacyModal .privacy-content').innerHTML;
    printContent('Política de Privacidad', content);
}
function printTerms() {
    const content = document.querySelector('#termsModal .terms-content').innerHTML;
    printContent('Términos y Condiciones', content);
}
function printContent(title, content) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<html><head><title>${title}</title><style>body{font-family:Arial;padding:20px}h1{color:#0078d4}</style></head><body><h1>${title}</h1>${content}</body></html>`);
    printWindow.document.close();
    printWindow.print();
}

function acceptAllCookies() { localStorage.setItem('cookiesAccepted', 'true'); closeCookiesModal(); SwalTheme.fire({ icon: 'success', title: '¡Cookies aceptadas!', text: 'Preferencias guardadas correctamente.', timer: 2000, showConfirmButton: false }); }
function rejectCookies() { localStorage.setItem('cookiesAccepted', 'false'); closeCookiesModal(); SwalTheme.fire({ icon: 'info', title: 'Cookies rechazadas', text: 'Solo usaremos cookies esenciales.', timer: 2000, showConfirmButton: false }); }
function configureCookies() { closeCookiesModal(); SwalTheme.fire({ title: 'Configuración guardada', icon: 'success', timer: 1500, showConfirmButton: false }); }

// ============================================
// NUEVO: FUNCIONES DE MANTENIMIENTO
// ============================================
function openMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeMaintenanceModal() {
    // Solo cierra el visual, pero los botones siguen bloqueados
    const modal = document.getElementById('maintenanceModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// ============================================
// MODIFICADO: FUNCIÓN PARA DESHABILITAR ACCESO E INYECTAR BOTÓN REFRESCAR
// ============================================
function disableLoginAccess() {
    // 1. Bloquear botón del Navbar
    const btnNav = document.getElementById('btn-login-nav');
    if (btnNav) {
        btnNav.classList.add('btn-disabled-maint');
        btnNav.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Mantenimiento';
        btnNav.removeAttribute('href');
        btnNav.onclick = (e) => e.preventDefault();

        // --- Inyectar botón de actualizar en el Navbar ---
        if (!document.getElementById('btn-refresh-nav')) {
            if(btnNav.parentElement) {
                btnNav.parentElement.classList.add('d-flex', 'align-items-center');
            }

            const refreshBtn = document.createElement('button');
            refreshBtn.id = 'btn-refresh-nav';
            refreshBtn.className = 'btn btn-warning btn-sm ms-2 animate__animated animate__fadeIn';
            refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i>';
            refreshBtn.title = 'Verificar de nuevo el estado';
            refreshBtn.style.fontWeight = 'bold';
            
            refreshBtn.onclick = (e) => {
                e.preventDefault();
                recheckMaintenanceStatus(refreshBtn);
            };
            
            btnNav.parentNode.insertBefore(refreshBtn, btnNav.nextSibling);
        }
    }

    // 2. Bloquear botón del Modal (Sistema Principal)
    const btnModal = document.getElementById('btn-login-modal');
    if (btnModal) {
        btnModal.disabled = true;
        btnModal.classList.add('btn-disabled-maint');
        btnModal.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Acceso Deshabilitado';
        btnModal.onclick = (e) => e.preventDefault();

        // --- Inyectar botón de actualizar en el Modal ---
        if (!document.getElementById('btn-refresh-modal')) {
            const refreshBtnModal = document.createElement('button');
            refreshBtnModal.id = 'btn-refresh-modal';
            refreshBtnModal.className = 'modal-btn ms-2 animate__animated animate__fadeIn'; 
            refreshBtnModal.style.backgroundColor = '#ffc107';
            refreshBtnModal.style.color = '#000';
            refreshBtnModal.style.border = 'none';
            refreshBtnModal.innerHTML = '<i class="fa-solid fa-rotate-right"></i>';
            refreshBtnModal.title = 'Revisar estado';
            
            refreshBtnModal.onclick = (e) => {
                e.preventDefault();
                recheckMaintenanceStatus(refreshBtnModal);
            };

            btnModal.parentNode.insertBefore(refreshBtnModal, btnModal.nextSibling);
        }
    }

    // 3. NUEVO: Bloquear botón del Footer
    const btnFooter = document.getElementById('footer-login-btn');
    if (btnFooter) {
        // Aplicar la misma clase de deshabilitado
        btnFooter.classList.add('btn-disabled-maint');
        // Cambiar el texto/icono
        btnFooter.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Mantenimiento';
        // Remover el href
        btnFooter.removeAttribute('href');
        // Prevenir cualquier acción
        btnFooter.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openMaintenanceModal(); // Opcional: abrir el modal si se hace clic
            return false;
        };

        // --- Inyectar botón de actualizar en el Footer ---
        if (!document.getElementById('btn-refresh-footer')) {
            // Crear el botón de actualizar
            const refreshBtnFooter = document.createElement('button');
            refreshBtnFooter.id = 'btn-refresh-footer';
            refreshBtnFooter.className = 'btn btn-warning btn-sm ms-2 animate__animated animate__fadeIn';
            refreshBtnFooter.innerHTML = '<i class="fa-solid fa-rotate-right"></i>';
            refreshBtnFooter.title = 'Verificar estado del sistema';
            refreshBtnFooter.style.fontWeight = 'bold';
            refreshBtnFooter.style.borderRadius = '20px'; // Para que coincida con el estilo
            refreshBtnFooter.style.padding = '6px 10px';
            refreshBtnFooter.style.fontSize = '0.85rem';
            
            refreshBtnFooter.onclick = (e) => {
                e.preventDefault();
                recheckMaintenanceStatus(refreshBtnFooter);
            };

            // Insertarlo después del botón de login en el footer
            // Asegurarnos de que el contenedor de botones exista
            const buttonGroup = btnFooter.closest('.button-group');
            if (buttonGroup) {
                buttonGroup.appendChild(refreshBtnFooter);
            } else {
                // Si no hay grupo de botones, insertarlo después
                btnFooter.parentNode.insertBefore(refreshBtnFooter, btnFooter.nextSibling);
            }
        }
    }
}

// ============================================
// 5. VALIDACIÓN Y ENVÍO DEL CHAT (ORIGINAL)
// ============================================
function validateEmail(email) {
    if (!email) return { valid: true, message: '' };
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email) ? { valid: true, message: 'Email válido ✓' } : { valid: false, message: 'Email inválido' };
}
function validatePhone(phone) {
    if (!phone) return { valid: true, message: '' };
    const clean = phone.replace(/\D/g, '');
    if (clean.length < 8 || clean.length > 15) return { valid: false, message: 'Entre 8 y 15 dígitos' };
    return { valid: true, message: 'Teléfono válido ✓' };
}
function validateName(name) {
    if (!name) return { valid: false, message: 'Nombre obligatorio' };
    if (name.length < 2) return { valid: false, message: 'Mínimo 2 caracteres' };
    return { valid: true, message: 'Nombre válido ✓' };
}
function validateMessage(msg) {
    if (!msg) return { valid: false, message: 'Mensaje obligatorio' };
    if (msg.length < 10) return { valid: false, message: 'Mínimo 10 caracteres' };
    return { valid: true, message: 'Mensaje válido ✓' };
}
function updateValidationUI(id, el, val) {
    const div = document.getElementById(id);
    if (!div) return;
    div.textContent = val.message;
    div.className = 'validation-message ' + (val.valid ? 'success' : (val.message ? 'error' : ''));
    if (el) {
        el.classList.remove('input-error', 'input-success');
        if (val.message) el.classList.add(val.valid ? 'input-success' : 'input-error');
    }
}
function setupRealTimeValidation() {
    ['chat-mail', 'chat-phone', 'chat-name', 'chat-message'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function() {
                if(id === 'chat-mail') updateValidationUI('email-validation', this, validateEmail(this.value));
                if(id === 'chat-phone') updateValidationUI('phone-validation', this, validatePhone(this.value));
                if(id === 'chat-name') updateValidationUI('name-validation', this, validateName(this.value));
                if(id === 'chat-message') updateValidationUI('message-validation', this, validateMessage(this.value));
            });
        }
    });
}


function initChatForm() {
    const form = document.getElementById('chat-form');
    if (!form) return;
    
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    // Agregar campos dinámicos para recolección de datos
    addSupportDataFields(newForm);

    newForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Datos básicos del usuario
        const name = document.getElementById('chat-name').value.trim();
        const message = document.getElementById('chat-message').value.trim();
        const phone = document.getElementById('chat-phone')?.value.trim() || '';
        const email = document.getElementById('chat-mail')?.value.trim() || '';
        
        // Datos de soporte recopilados
        const supportData = collectSupportData();

        // Validaciones
        const vName = validateName(name);
        const vMsg = validateMessage(message);
        const vPhone = validatePhone(phone);
        const vEmail = validateEmail(email);

        if (!vName.valid || !vMsg.valid || !vPhone.valid || !vEmail.valid) {
            SwalTheme.fire({ 
                icon: 'error', 
                title: 'Datos incorrectos', 
                text: 'Por favor revisa los campos marcados en rojo o marcados como Obligatorios (*)', 
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido', 
                allowOutsideClick: false 
            });
            return;
        }
        
        // Recolección de datos del sistema y usuario
        const systemData = collectSystemData();
        const userData = collectUserData(supportData);
        
        // Formatear teléfono
        let formattedPhone = formatPhoneNumber(phone);
        
        // Crear mensaje estructurado para WhatsApp
        const finalMessage = generateWhatsAppMessage({
            name,
            email,
            phone: formattedPhone,
            message,
            systemData,
            userData,
            supportData
        });

        const whatsappNumero = "<?php echo $whatsapp_numero_formateado; ?>";
        
        if (!whatsappNumero) {
            SwalTheme.fire({ 
                icon: 'error', 
                title: 'Servicio no disponible', 
                text: 'WhatsApp no configurado.', 
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido' 
            });
            return;
        }

        // Mostrar resumen antes de enviar
        const shouldSend = await showSummaryAndConfirm(supportData, finalMessage);
        if (!shouldSend) return;

        const waLink = `https://api.whatsapp.com/send?phone=${whatsappNumero}&text=${encodeURIComponent(finalMessage)}`;

        const result = await SwalTheme.fire({
            title: '<span style="color:#25d366"><i class="fab fa-whatsapp me-2"></i>¿Enviar a WhatsApp?</span>',
            html: generateSummaryHTML(name, supportData),
            showCancelButton: true,
            confirmButtonText: '<i class="fab fa-whatsapp me-2"></i> Enviar',
            cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
            confirmButtonColor: '#25d366',
            showDenyButton: true,
            denyButtonText: '<i class="fas fa-copy me-2"></i> Copiar',
            denyButtonColor: '#6c757d',
            width: '600px'
        });

        if (result.isConfirmed) {
            window.open(waLink, '_blank');
            newForm.reset();
            resetSupportDataFields();
            closeChat();
            showSuccessNotification();
        } else if (result.isDenied) {
            // Opción para copiar mensaje
            copyToClipboard(finalMessage);
        }
    });

    // Agregar funcionalidad de captura de pantalla
    addScreenshotFeature();
}

// =======================================================================
// FUNCIONES DE RECOLECCIÓN DE DATOS
// =======================================================================

function addSupportDataFields(form) {
    // Crear contenedor para datos de soporte
    const supportContainer = document.createElement('div');
    supportContainer.className = 'support-data-container';
    supportContainer.innerHTML = `
        <div class="support-header">
            <h5><i class="fas fa-tools me-2"></i>Información de Soporte</h5	>
            <small class="text-light">Complete estos datos para un mejor diagnóstico</small>
        </div>
        
        <div class="row g-2 mt-2">
<!-- Dropdown de Tipo de Problema -->
<div class="col-md-6">
    <label for="issue-type" class="form-label small">
        <i class="fas fa-bug me-1"></i>Tipo de Problema *
    </label>
    <select id="issue-type" class="form-select form-select-sm" required style="background-color: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.15); color: white;">
        <option value="" disabled selected style="color: rgba(160,200,255,0.6);">Seleccione el tipo de problema...</option>
        <option value="error_sistema">🚨 Error del Sistema</option>
        <option value="funcionalidad">⚙️ Funcionalidad no funciona</option>
        <option value="rendimiento">⚡ Problema de Rendimiento</option>
        <option value="seguridad">🔒 Problema de Seguridad</option>
        <option value="usuario">👤 Configuración de Usuario</option>
        <option value="facturacion">🧾 Facturación/Impresión</option>
        <option value="cierre_mes">📅 Problemas de Cierre Mes/Año</option>
        <option value="reporte">📊 Error en Reporte</option>
        <option value="otro">❓ Otro</option>
    </select>
</div>

<!-- Dropdown de Urgencia -->
<div class="col-md-6">
    <label for="urgency" class="form-label small">
        <i class="fas fa-clock me-1"></i>Urgencia *
    </label>
    <select id="urgency" class="form-select form-select-sm" required style="background-color: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.15); color: white;">
        <option value="" disabled selected style="color: rgba(160,200,255,0.6);">Seleccione el nivel de urgencia...</option>
        <option value="baja">🟢 Baja (Consulta general)</option>
        <option value="media">🟡 Media (Afecta operaciones)</option>
        <option value="alta">🟠 Alta (Sistema no funciona)</option>
        <option value="critica">🔴 Crítica (Pérdida de datos)</option>
    </select>
</div>
            
            <div class="col-12 mt-2">
                <label for="affected-module" class="form-label small">
                    <i class="fas fa-cube me-1"></i>Módulo/Sección Afectada
                </label>
                <input type="text" id="affected-module" class="form-control form-control-sm" 
                       placeholder="Ej: Login, Facturación, Reportes, Dashboard...">
            </div>
            
            <div class="col-12 mt-2">
                <label for="error-code" class="form-label small">
                    <i class="fas fa-code me-1"></i>Código de Error (si aplica)
                </label>
                <input type="text" id="error-code" class="form-control form-control-sm" 
                       placeholder="Ej: ERR-404, PHP Notice, SQL Error...">
            </div>
            
            <div class="col-12 mt-2">
                <label for="steps-to-reproduce" class="form-label small">
                    <i class="fas fa-list-ol me-1"></i>Pasos para Reproducir el Problema
                </label>
                <textarea id="steps-to-reproduce" class="form-control form-control-sm" rows="2" 
                          placeholder="1. Ingresar al sistema...
2. Navegar a...
3. Hacer clic en..."></textarea>
            </div>
            
            <div class="col-12 mt-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="attach-screenshot">
                    <label class="form-check-label small" for="attach-screenshot">
                        <i class="fas fa-camera me-1"></i>Incluir captura de pantalla
                    </label>
                </div>
                <small class="text-muted">(Se capturará automáticamente al enviar)</small>
            </div>
        </div>
        
        <div class="mt-3" id="screenshot-preview"></div>
    `;
    
    // Insertar después del textarea del mensaje
    const messageField = form.querySelector('#chat-message');
    if (messageField) {
        messageField.parentNode.insertBefore(supportContainer, messageField.nextSibling);
    }
}

function collectSupportData() {
    return {
        issueType: document.getElementById('issue-type')?.value || '',
        urgency: document.getElementById('urgency')?.value || '',
        affectedModule: document.getElementById('affected-module')?.value || '',
        errorCode: document.getElementById('error-code')?.value || '',
        stepsToReproduce: document.getElementById('steps-to-reproduce')?.value || '',
        attachScreenshot: document.getElementById('attach-screenshot')?.checked || false,
        screenshotData: document.getElementById('screenshot-preview')?.querySelector('img')?.src || null
    };
}

function collectSystemData() {
    const now = new Date();
    return {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        language: navigator.language,
        screenResolution: `${window.screen.width}x${window.screen.height}`,
        windowSize: `${window.innerWidth}x${window.innerHeight}`,
        dateTime: now.toLocaleDateString('es-ES', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: 'numeric', 
            minute: 'numeric', 
            hour12: true 
        }),
        timestamp: now.toISOString(),
        url: window.location.href,
        referrer: document.referrer || 'Directo',
        cookiesEnabled: navigator.cookieEnabled,
        onlineStatus: navigator.onLine ? 'En línea' : 'Sin conexión'
    };
}

function collectUserData(supportData) {
    // Obtener información de la sesión si está disponible
    const sessionInfo = {
        userId: window.userId || 'No identificado',
        userName: window.userName || 'Usuario no logueado',
        userRole: window.userRole || 'Invitado',
        lastActivity: new Date().toLocaleTimeString()
    };
    
    // Determinar tipo de SO basado en userAgent
    const ua = navigator.userAgent.toLowerCase();
    let os = 'Desconocido';
    let browser = 'Desconocido';
    
    if (ua.includes('windows')) os = 'Windows';
    else if (ua.includes('mac')) os = 'macOS';
    else if (ua.includes('linux')) os = 'Linux';
    else if (ua.includes('android')) os = 'Android';
    else if (ua.includes('ios')) os = 'iOS';
    
    if (ua.includes('chrome')) browser = 'Chrome';
    else if (ua.includes('firefox')) browser = 'Firefox';
    else if (ua.includes('safari')) browser = 'Safari';
    else if (ua.includes('edge')) browser = 'Edge';
    else if (ua.includes('opera')) browser = 'Opera';
    
    return {
        ...sessionInfo,
        operatingSystem: os,
        browser: browser,
        issueType: supportData.issueType,
        urgencyLevel: supportData.urgency,
        affectedArea: supportData.affectedModule
    };
}

function formatPhoneNumber(phone) {
    if (!phone) return 'N/D';
    
    let clean = phone.replace(/\D/g, '');
    if (!clean.startsWith('53') && clean.length === 8) {
        clean = '53' + clean;
    }
    
    // Formatear con espacios para mejor legibilidad
    if (clean.startsWith('53')) {
        return `+${clean.slice(0, 2)} ${clean.slice(2, 5)} ${clean.slice(5)}`;
    }
    
    return `+${clean}`;
}

function generateWhatsAppMessage(data) {
    const urgencyIcons = {
        'baja': '🔵',
        'media': '🟡', 
        'alta': '🟠',
        'critica': '🔴'
    };
    
    const issueTypes = {
        'error_sistema': 'Error del Sistema',
        'funcionalidad': 'Funcionalidad no funciona',
        'rendimiento': 'Problema de Rendimiento',
        'seguridad': 'Problema de Seguridad',
        'usuario': 'Configuración de Usuario',
        'facturacion': 'Facturación/Impresión',
        'reporte': 'Error en Reporte',
        'otro': 'Otro'
    };
    
    const urgencyIcon = urgencyIcons[data.supportData.urgency] || '⚪';
    const issueTypeName = issueTypes[data.supportData.issueType] || data.supportData.issueType;
    
    return `📞 *SOLICITUD DE SOPORTE - SISFACT PDL Visiones*
━━━━━━━━━━━━━━━━━━━━━━

👤 *INFORMACIÓN DEL USUARIO*
• Nombre: *${data.name}*
• Email: ${data.email || 'N/D'}
• Teléfono: ${data.phone || 'N/D'}
• Usuario: ${data.userData.userName}
• Rol: ${data.userData.userRole}

⚠️ *INCIDENCIA*
• Tipo: ${issueTypeName}
• Urgencia: ${urgencyIcon} ${data.supportData.urgency.toUpperCase()}
• Módulo: ${data.supportData.affectedModule || 'N/D'}
• Código Error: ${data.supportData.errorCode || 'N/D'}

📋 *DESCRIPCIÓN*
${data.message}

🔧 *PASOS PARA REPRODUCIR*
${data.supportData.stepsToReproduce || 'No especificado'}

💻 *INFORMACIÓN DEL SISTEMA*
• Fecha: ${data.systemData.dateTime}
• URL: ${data.systemData.url}
• Sistema: ${data.userData.operatingSystem}
• Navegador: ${data.userData.browser}
• Resolución: ${data.systemData.screenResolution}
• User Agent: ${data.systemData.userAgent}

📊 *ESTADO*
• Online: ${data.systemData.onlineStatus}
• Cookies: ${data.systemData.cookiesEnabled ? 'Habilitadas' : 'Deshabilitadas'}

📎 *ADJUNTOS*
• Captura: ${data.supportData.attachScreenshot ? 'Sí' : 'No'}

━━━━━━━━━━━━━━━━━━━━━━
🆔 Ticket ID: ${generateTicketId()}
⏰ Generado: ${data.systemData.dateTime}`;
}

function generateTicketId() {
    const now = new Date();
    const datePart = now.getFullYear().toString().slice(-2) + 
                    (now.getMonth() + 1).toString().padStart(2, '0') + 
                    now.getDate().toString().padStart(2, '0');
    const timePart = now.getHours().toString().padStart(2, '0') + 
                    now.getMinutes().toString().padStart(2, '0') + 
                    now.getSeconds().toString().padStart(2, '0');
    const randomPart = Math.random().toString(36).substring(2, 6).toUpperCase();
    
    return `TICKET-${datePart}-${timePart}-${randomPart}`;
}

function generateSummaryHTML(name, supportData) {
    const urgencyColors = {
        'baja': '#28a745',
        'media': '#ffc107',
        'alta': '#fd7e14',
        'critica': '#dc3545'
    };
    
    const issueTypes = {
        'error_sistema': 'Error del Sistema',
        'funcionalidad': 'Funcionalidad no funciona',
        'rendimiento': 'Problema de Rendimiento',
        'seguridad': 'Problema de Seguridad',
        'usuario': 'Configuración de Usuario',
        'facturacion': 'Facturación/Impresión',
        'reporte': 'Error en Reporte',
        'otro': 'Otro'
    };
    
    const urgencyColor = urgencyColors[supportData.urgency] || '#6c757d';
    const issueTypeName = issueTypes[supportData.issueType] || supportData.issueType;
    
    return `
        <div class="text-start">
            <div class="alert alert-info mb-3">
                <i class="fas fa-user me-2"></i>
                <strong>${name}</strong> enviará una solicitud de soporte
            </div>
            
            <div class="row">
                <div class="col-6">
                    <small class="text-muted">Tipo de Problema</small>
                    <p class="mb-2"><i class="fas fa-bug me-1"></i> ${issueTypeName}</p>
                </div>
                <div class="col-6">
                    <small class="text-muted">Nivel de Urgencia</small>
                    <p class="mb-2" style="color: ${urgencyColor};">
                        <i class="fas fa-exclamation-triangle me-1"></i> 
                        ${supportData.urgency.toUpperCase()}
                    </p>
                </div>
            </div>
            
            ${supportData.affectedModule ? `
            <div class="mt-2">
                <small class="text-muted">Módulo Afectado</small>
                <p class="mb-2"><i class="fas fa-cube me-1"></i> ${supportData.affectedModule}</p>
            </div>
            ` : ''}
            
            ${supportData.errorCode ? `
            <div class="mt-2">
                <small class="text-muted">Código de Error</small>
                <p class="mb-2"><i class="fas fa-code me-1"></i> ${supportData.errorCode}</p>
            </div>
            ` : ''}
            
            <div class="mt-3 alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                Se incluirán datos del sistema y navegador para diagnóstico
            </div>
        </div>
    `;
}

async function showSummaryAndConfirm(supportData, message) {
    // Verificar si es un problema crítico
    if (supportData.urgency === 'critica') {
        const result = await SwalTheme.fire({
            icon: 'warning',
            title: '⚠️ Problema Crítico Detectado',
            html: `
                <div class="text-start">
                    <p>Esta incidencia ha sido marcada como <strong>CRÍTICA</strong>.</p>
                    <p class="text-danger">Se recomienda contactar al soporte técnico inmediatamente.</p>
                    <p><small>Se generará un ticket prioritario y se notificará al equipo de soporte.</small></p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-exclamation-triangle me-2"></i> Continuar',
            cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
            confirmButtonColor: '#dc3545'
        });
        
        if (!result.isConfirmed) return false;
    }
    
    return true;
}

// Variable global para almacenar la captura
window.currentScreenshot = null;

function addScreenshotFeature() {
    const checkbox = document.getElementById('attach-screenshot');
    const preview = document.getElementById('screenshot-preview');
    
    if (!checkbox || !preview) return;
    
    checkbox.addEventListener('change', async function() {
        if (this.checked) {
            try {
                // Mostrar loader
                preview.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        Preparando captura de pantalla...
                    </div>
                `;
                
                // Solicitar permiso para captura
                const stream = await navigator.mediaDevices.getDisplayMedia({
                    video: { 
                        cursor: "always",
                        displaySurface: "window"
                    },
                    audio: false
                });
                
                const video = document.createElement('video');
                video.srcObject = stream;
                
                // Esperar a que el video cargue
                await new Promise((resolve) => {
                    video.onloadedmetadata = () => {
                        video.play();
                        resolve();
                    };
                });
                
                // Esperar un momento para estabilizar
                await new Promise(resolve => setTimeout(resolve, 300));
                
                // Crear canvas y capturar
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                
                // Dibujar el video en el canvas
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Convertir a base64 - USAR PNG PARA MEJOR CALIDAD
                const screenshot = canvas.toDataURL('image/png');
                
                // GUARDAR EN VARIABLE GLOBAL DE FORMA GLOBAL
                window.currentScreenshot = screenshot;
                
                console.log('Captura guardada, tamaño:', screenshot.length, 'bytes');
                
                // Mostrar vista previa
                preview.innerHTML = `
                    <div class="alert alert-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Captura lista</strong>
                                <small class="d-block">Se adjuntará al mensaje</small>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" id="view-screenshot-btn">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="remove-screenshot-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <img src="${screenshot}" class="img-fluid rounded border screenshot-thumbnail" 
                             style="max-height: 150px; cursor: pointer;" 
                             alt="Vista previa de captura"
                             id="screenshot-img">
                        <p class="small text-muted mt-1">Haz clic para ver en tamaño completo</p>
                    </div>
                `;
                
                // Detener el stream
                stream.getTracks().forEach(track => track.stop());
                
                // Agregar eventos
                document.getElementById('remove-screenshot-btn').addEventListener('click', function() {
                    preview.innerHTML = '';
                    checkbox.checked = false;
                    window.currentScreenshot = null;
                    console.log('Captura eliminada');
                });
                
                document.getElementById('view-screenshot-btn').addEventListener('click', function() {
                    viewFullScreenshot(screenshot);
                });
                
                document.getElementById('screenshot-img').addEventListener('click', function() {
                    viewFullScreenshot(screenshot);
                });
                
            } catch (error) {
                console.error('Error al capturar pantalla:', error);
                preview.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error en captura</strong>
                        <small class="d-block">${error.message || 'El usuario canceló la captura'}</small>
                    </div>
                `;
                checkbox.checked = false;
                window.currentScreenshot = null;
            }
        } else {
            preview.innerHTML = '';
            window.currentScreenshot = null;
            console.log('Checkbox desmarcado, captura eliminada');
        }
    });
}

// Función para ver captura completa
function viewFullScreenshot(base64Image) {
    SwalTheme.fire({
        title: '<i class="fas fa-expand me-2"></i> Vista previa completa',
        html: `<div class="text-center">
                  <img src="${base64Image}" class="img-fluid rounded" style="max-height: 70vh;" alt="Captura completa">
                  <div class="mt-3">
                      <button class="btn btn-sm btn-outline-primary" onclick="testDescargarCaptura('${base64Image}')">
                          <i class="fas fa-download me-1"></i> Probar Descarga
                      </button>
                      <button class="btn btn-sm btn-outline-secondary" onclick="console.log('Captura almacenada:', window.currentScreenshot ? 'SÍ' : 'NO')">
                          <i class="fas fa-code me-1"></i> Debug
                      </button>
                  </div>
               </div>`,
        showConfirmButton: false,
        showCloseButton: true,
        width: '90%'
    });
}

// Función de prueba para descargar
function testDescargarCaptura(base64Image) {
    console.log('Probando descarga...');
    console.log('Base64 length:', base64Image.length);
    console.log('Base64 preview:', base64Image.substring(0, 100) + '...');
    descargarCaptura(base64Image);
}

function resetSupportDataFields() {
    const fields = [
        'issue-type',
        'urgency',
        'affected-module',
        'error-code',
        'steps-to-reproduce'
    ];
    
    fields.forEach(id => {
        const field = document.getElementById(id);
        if (field) field.value = '';
    });
    
    const checkbox = document.getElementById('attach-screenshot');
    if (checkbox) checkbox.checked = false;
    
    const preview = document.getElementById('screenshot-preview');
    if (preview) preview.innerHTML = '';
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        SwalTheme.fire({
            icon: 'success',
            title: 'Copiado al portapapeles',
            text: 'El mensaje se ha copiado correctamente.',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

function showSuccessNotification() {
    SwalTheme.fire({
        icon: 'success',
        title: 'Solicitud Enviada',
        html: `
            <div class="text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p>La solicitud de soporte ha sido enviada correctamente.</p>
                <small class="text-muted">El equipo de soporte se pondrá en contacto pronto.</small>
                <hr>
                <p class="small">
                    <i class="fas fa-clock me-1"></i>
                    Tiempo estimado de respuesta: 1-2 horas hábiles
                </p>
            </div>
        `,
        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
        timer: 5000
    });
}



function enviarPorEmail() {
    // Datos básicos del formulario
    const name = document.getElementById('chat-name')?.value.trim() || '';
    const message = document.getElementById('chat-message')?.value.trim() || '';
    const phone = document.getElementById('chat-phone')?.value.trim() || '';
    const emailUser = document.getElementById('chat-mail')?.value.trim() || '';
    
    // Datos de soporte adicionales
    const issueType = document.getElementById('issue-type')?.value || '';
    const urgency = document.getElementById('urgency')?.value || '';
    const affectedModule = document.getElementById('affected-module')?.value || '';
    const errorCode = document.getElementById('error-code')?.value || '';
    const stepsToReproduce = document.getElementById('steps-to-reproduce')?.value || '';
    const hasScreenshot = document.getElementById('attach-screenshot')?.checked || false;
    
    // OBTENER CAPTURA ACTUAL - VERIFICAR DE MÚLTIPLES MANERAS
    let screenshotData = null;
    
    // Método 1: De la variable global
    if (window.currentScreenshot) {
        screenshotData = window.currentScreenshot;
        console.log('Captura obtenida de window.currentScreenshot');
    }
    
    // Método 2: Del elemento img en el preview
    if (!screenshotData) {
        const screenshotImg = document.querySelector('#screenshot-preview img');
        if (screenshotImg && screenshotImg.src) {
            screenshotData = screenshotImg.src;
            console.log('Captura obtenida del elemento img');
        }
    }
    
    // Método 3: Verificar si realmente hay captura visual
    if (hasScreenshot && !screenshotData) {
        console.warn('Checkbox marcado pero no hay captura disponible');
        console.log('window.currentScreenshot:', window.currentScreenshot ? 'EXISTE' : 'NO EXISTE');
        
        // Mostrar advertencia
        SwalTheme.fire({
            icon: 'warning',
            title: 'Captura no encontrada',
            html: `<div class="text-start">
                      <p>El checkbox de captura está marcado pero no se encontró la imagen.</p>
                      <p class="small">Posibles soluciones:</p>
                      <ul class="small">
                          <li>Vuelve a tomar la captura</li>
                          <li>Continúa sin captura</li>
                          <li>Actualiza la página y vuelve a intentar</li>
                      </ul>
                      <button class="btn btn-sm btn-primary mt-2" onclick="reintentarCaptura()">
                          <i class="fas fa-redo me-2"></i> Reintentar Captura
                      </button>
                   </div>`,
            showCancelButton: true,
			confirmButtonText: '<i class="fas fa-check me-2"></i> Continuar sin captura',
            cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Continuar sin captura
                procesarEnvioSinCaptura();
            }
        });
        return;
    }
    
    // Validaciones básicas
    if (!name || !message) {
        SwalTheme.fire({ 
            icon: 'error', 
            title: 'Datos incompletos', 
            text: 'Nombre y mensaje son obligatorios.',
			confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
        return;
    }
    
    console.log('Enviando con captura:', screenshotData ? 'SÍ' : 'NO');
    
    // Generar contenido del correo
    const emailContent = generarContenidoCorreo({
        name, message, phone, emailUser,
        issueType, urgency, affectedModule, 
        errorCode, stepsToReproduce, 
        hasScreenshot: !!screenshotData // Usar booleano basado en si hay captura real
    });
    
    // Mostrar opciones
    SwalTheme.fire({
        title: '<i class="fas fa-paper-plane me-2"></i> Enviar Solicitud',
        html: generarResumenHTML(name, issueType, urgency, !!screenshotData, emailContent.ticketId),
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-envelope me-2"></i> Cliente de Correo',
        cancelButtonText: '<i class="fab fa-google me-2"></i> Gmail Web',
        showDenyButton: true,
        denyButtonText: '<i class="fas fa-copy me-2"></i> Copiar',
        denyButtonColor: '#6c757d',
        confirmButtonColor: '#007bff',
        cancelButtonColor: '#ea4335',
        width: '550px',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Cliente de correo local
            abrirClienteCorreoLocal(emailContent.subject, emailContent.body, screenshotData);
        } else if (result.isDenied) {
            // Copiar al portapapeles
            copiarContenidoCompleto(emailContent.subject, emailContent.body);
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Gmail Web
            abrirGmailWeb(emailContent.subject, emailContent.body, emailUser, emailContent.ticketId, !!screenshotData);
        }
    });
    
    // Función auxiliar para reintentar captura
    window.reintentarCaptura = function() {
        document.getElementById('attach-screenshot').checked = false;
        document.getElementById('attach-screenshot').click();
    };
    
    // Función para procesar sin captura
    function procesarEnvioSinCaptura() {
        const emailContent = generarContenidoCorreo({
            name, message, phone, emailUser,
            issueType, urgency, affectedModule, 
            errorCode, stepsToReproduce, 
            hasScreenshot: false
        });
        
        SwalTheme.fire({
            title: '<i class="fas fa-paper-plane me-2"></i> Enviar Solicitud (sin captura)',
            html: generarResumenHTML(name, issueType, urgency, false, emailContent.ticketId),
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-envelope me-2"></i> Cliente de Correo',
            cancelButtonText: '<i class="fab fa-google me-2"></i> Gmail Web',
            showDenyButton: true,
            denyButtonText: '<i class="fas fa-copy me-2"></i> Copiar',
            denyButtonColor: '#6c757d',
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#ea4335',
            width: '550px',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                abrirClienteCorreoLocal(emailContent.subject, emailContent.body, null);
            } else if (result.isDenied) {
                copiarContenidoCompleto(emailContent.subject, emailContent.body);
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                abrirGmailWeb(emailContent.subject, emailContent.body, emailUser, emailContent.ticketId, false);
            }
        });
    }
}

// Función para abrir cliente de correo local - VERSIÓN CORREGIDA
function abrirClienteCorreoLocal(subject, body, screenshotData) {
    // IMPORTANTE: Para mailto:, usar %0D%0A para saltos de línea
    const encodedSubject = encodeURIComponent(subject);
    
    // Reemplazar \n con %0D%0A (CRLF - retorno de carro + línea nueva)
    let mailtoBody = body.replace(/\n/g, '%0D%0A');
    
    // Si hay caracteres especiales que necesiten encoding adicional
    mailtoBody = encodeURIComponent(body)
        .replace(/%20/g, ' ')      // Espacios como espacios normales
        .replace(/%0A/g, '%0D%0A') // Asegurar CRLF
        .replace(/%2F/g, '/')      // Slashes normales
        .replace(/%3A/g, ':')      // Dos puntos normales
        .replace(/%28/g, '(')      // Paréntesis abiertos
        .replace(/%29/g, ')');     // Paréntesis cerrados
    
    // Crear enlace mailto
    const mailtoLink = `mailto:soporte_pdlvisiones@gmail.com?subject=${encodedSubject}&body=${mailtoBody}`;
    
    console.log('Mailto link creado:', mailtoLink.substring(0, 200) + '...');
    
    // Si hay captura, mostrar instrucciones primero
    if (screenshotData) {
        mostrarInstruccionesAdjuntoLocal(screenshotData, subject, body);
        
        // Abrir correo después de un delay
        setTimeout(() => {
            abrirMailtoEnNuevaVentana(mailtoLink);
        }, 2000);
    } else {
        // Sin captura, abrir directamente
        abrirMailtoEnNuevaVentana(mailtoLink);
        
        // Mostrar éxito
        setTimeout(() => {
            SwalTheme.fire({
                icon: 'success',
                title: 'Correo listo',
                html: `<div class="text-center">
                          <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                          <p>Tu cliente de correo se abrió con el mensaje formateado.</p>
                          <small class="text-muted">Completa el envío y adjunta archivos si es necesario.</small>
                       </div>`,
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                timer: 4000
            });
        }, 1000);
    }
}


// Función para mostrar instrucciones de adjunto - VERSIÓN CORREGIDA
function mostrarInstruccionesAdjuntoLocal(screenshotData, subject, body) {
    // Usar template literal para HTML sin pasar strings largos como parámetros
    const htmlContent = `
        <div class="text-start">
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                Tu cliente de correo se abrirá en unos segundos. Sigue estos pasos:
            </div>
            
            <div class="steps-container">
                <div class="step mb-3">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>Descarga la captura</strong>
                        <p class="mb-1 small">Guarda la imagen en tu computadora:</p>
                        <button class="btn btn-primary btn-sm" id="btn-descargar-captura">
                            <i class="fas fa-download me-2"></i> Descargar Captura.jpg
                        </button>
                    </div>
                </div>
                
                <div class="step mb-3">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>Abre tu cliente de correo</strong>
                        <p class="mb-1 small">Se abrirá automáticamente con:</p>
                        <ul class="small mb-0">
                            <li>Destinatario: <code>soporte_pdlvisiones@gmail.com</code></li>
                            <li>Asunto: ${subject.substring(0, 50)}...</li>
                        </ul>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>Adjunta la captura</strong>
                        <p class="mb-1 small">En tu cliente de correo:</p>
                        <ol class="small mb-0">
                            <li>Busca el botón "Adjuntar" o el clip 📎</li>
                            <li>Selecciona el archivo descargado</li>
                            <li>Envía el correo normalmente</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <small>Si no se abre automáticamente, usa los botones abajo</small>
            </div>
            
            <div class="text-center mt-3">
                <button class="btn btn-outline-primary btn-sm me-2" id="btn-abrir-correo">
                    <i class="fas fa-external-link-alt me-2"></i> Abrir Correo
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btn-copiar-todo">
                    <i class="fas fa-copy me-2"></i> Copiar Todo
                </button>
            </div>
        </div>
    `;
    
    SwalTheme.fire({
        title: '<i class="fas fa-paperclip me-2"></i> Adjuntar Captura de Pantalla',
        html: htmlContent,
        showConfirmButton: false,
        showCloseButton: true,
        width: '600px',
        didOpen: () => {
            // Configurar eventos después de abrir el modal
            document.getElementById('btn-descargar-captura').addEventListener('click', () => {
                descargarCaptura(screenshotData);
            });
            
            document.getElementById('btn-abrir-correo').addEventListener('click', () => {
                const encodedSubject = encodeURIComponent(subject);
                const encodedBody = encodeURIComponent(body);
                const mailtoLink = `mailto:soporte_pdlvisiones@gmail.com?subject=${encodedSubject}&body=${encodedBody}`;
                window.open(mailtoLink, '_blank');
            });
            
            document.getElementById('btn-copiar-todo').addEventListener('click', () => {
                const textoCompleto = `Para: soporte_pdlvisiones@gmail.com\nAsunto: ${subject}\n\n${body}`;
                navigator.clipboard.writeText(textoCompleto);
                SwalTheme.fire({
                    icon: 'success',
                    title: '¡Copiado!',
                    text: 'Contenido copiado al portapapeles',
                    timer: 2000
                });
            });
        }
    });
}

// Función para abrir Gmail Web - VERSIÓN CORREGIDA
function abrirGmailWeb(subject, body, emailUser, ticketId, hasScreenshot) {
    // Crear cuerpo corto para evitar error 400
    const cuerpoCorto = `Solicitud de Soporte - Ticket ID: ${ticketId}\n\nHola equipo de soporte,\n\nPor favor revisa la solicitud completa. Ticket ID: ${ticketId}\nUsuario: ${emailUser || 'No proporcionado'}\n\n--\nEste mensaje fue generado desde el formulario de soporte SISFACT PDL Visiones`;
    
    // Codificar
    const encodedSubject = encodeURIComponent(`Soporte SISFACT - Ticket ${ticketId}`);
    const encodedBody = encodeURIComponent(cuerpoCorto);
    
    // URL más corta
    let gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=soporte_pdlvisiones@gmail.com&su=${encodedSubject}&body=${encodedBody}`;
    
    // Abrir Gmail
    const gmailWindow = window.open(gmailUrl, '_blank', 'width=1000,height=700');
    
    // HTML para las instrucciones
    const htmlInstrucciones = `
        <div class="text-start">
            <div class="alert alert-success mb-3">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Gmail se abrió en una nueva ventana</strong>
            </div>
            
            <p><strong>Sigue estos pasos:</strong></p>
            
            <div class="step mb-3">
                <div class="step-number">1</div>
                <div class="step-content">
                    <strong>Copia el contenido completo</strong>
                    <button class="btn btn-sm btn-primary mt-1" id="btn-copiar-gmail">
                        <i class="fas fa-copy me-2"></i> Copiar Todo el Mensaje
                    </button>
                </div>
            </div>
            
            <div class="step mb-3">
                <div class="step-number">2</div>
                <div class="step-content">
                    <strong>Pega en Gmail</strong>
                    <p class="small mb-0">En la ventana de Gmail, pega el contenido copiado</p>
                </div>
            </div>
            
            ${hasScreenshot ? `
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <strong>Adjunta captura</strong>
                    <p class="small mb-1">Descarga la captura primero:</p>
                    <button class="btn btn-sm btn-success" id="btn-descargar-gmail">
                        <i class="fas fa-download me-2"></i> Descargar Captura
                    </button>
                    <p class="small mt-1 mb-0">Luego adjunta el archivo en Gmail</p>
                </div>
            </div>
            ` : ''}
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-lightbulb me-2"></i>
                <small>Si no ves la ventana de Gmail, revisa las pestañas de tu navegador o bloqueadores de popups</small>
            </div>
        </div>
    `;
    
    SwalTheme.fire({
        title: '<i class="fab fa-google me-2"></i> Gmail Abierto',
        html: htmlInstrucciones,
        showConfirmButton: false,
        showCloseButton: true,
        width: '600px',
        didOpen: () => {
            // Configurar eventos
            document.getElementById('btn-copiar-gmail').addEventListener('click', () => {
                copiarContenidoCompleto(subject, body);
            });
            
            if (hasScreenshot && document.getElementById('btn-descargar-gmail')) {
                document.getElementById('btn-descargar-gmail').addEventListener('click', () => {
                    descargarCaptura(window.currentScreenshot);
                });
            }
        }
    });
}

// Función para copiar contenido completo - VERSIÓN SEGURA
function copiarContenidoCompleto(subject, body) {
    const textoCompleto = `Para: soporte_pdlvisiones@gmail.com\nAsunto: ${subject}\n\n${body}`;
    
    navigator.clipboard.writeText(textoCompleto).then(() => {
        SwalTheme.fire({
            icon: 'success',
            title: '¡Copiado!',
            html: `<div class="text-center">
                      <i class="fas fa-clipboard-check fa-2x text-success mb-2"></i>
                      <p>Todo el contenido se copió al portapapeles</p>
                      <small class="text-muted">Pega en tu cliente de correo</small>
                   </div>`,
            timer: 3000,
            showConfirmButton: false
        });
    }).catch(err => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = textoCompleto;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        
        SwalTheme.fire({
            icon: 'success',
            title: '¡Copiado!',
            text: 'Contenido copiado (método alternativo)',
            timer: 2000
        });
    });
}

function descargarCaptura(base64Image) {
    console.log('Intentando descargar captura...');
    console.log('base64Image recibido:', base64Image ? 'SÍ' : 'NO');
    
    if (!base64Image) {
        // Intentar obtener de múltiples fuentes
        console.log('Buscando captura en fuentes alternativas...');
        
        // Fuente 1: Variable global
        if (window.currentScreenshot) {
            base64Image = window.currentScreenshot;
            console.log('Encontrada en window.currentScreenshot');
        }
        
        // Fuente 2: Elemento img
        if (!base64Image) {
            const screenshotImg = document.querySelector('#screenshot-preview img');
            if (screenshotImg && screenshotImg.src && screenshotImg.src.startsWith('data:image')) {
                base64Image = screenshotImg.src;
                console.log('Encontrada en elemento img');
            }
        }
        
        // Fuente 3: Variable temporal del formulario
        if (!base64Image && window.lastScreenshot) {
            base64Image = window.lastScreenshot;
            console.log('Encontrada en window.lastScreenshot');
        }
    }
    
    if (!base64Image) {
        SwalTheme.fire({
            icon: 'error',
            title: 'No hay captura',
            html: `<div class="text-start">
                      <p>No se encontró ninguna captura de pantalla.</p>
                      <p class="small">Posibles causas:</p>
                      <ul class="small">
                          <li>No se tomó la captura correctamente</li>
                          <li>La página se recargó y se perdió la captura</li>
                          <li>La captura fue eliminada</li>
                      </ul>
                      <div class="mt-3">
                          <button class="btn btn-sm btn-primary" onclick="document.getElementById('attach-screenshot').click()">
                              <i class="fas fa-camera me-2"></i> Tomar Nueva Captura
                          </button>
                      </div>
                   </div>`,
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
            width: '500px'
        });
        return;
    }
    
    try {
        console.log('Procesando base64, longitud:', base64Image.length);
        
        // Verificar que sea un base64 válido
        if (!base64Image.startsWith('data:image/')) {
            console.error('No es un base64 de imagen válido:', base64Image.substring(0, 100));
            throw new Error('Formato de imagen no válido');
        }
        
        // Extraer tipo MIME y datos
        const match = base64Image.match(/^data:(image\/\w+);base64,(.+)$/);
        if (!match) {
            throw new Error('Formato base64 incorrecto');
        }
        
        const mimeType = match[1];
        const base64Data = match[2];
        
        console.log('MIME type:', mimeType);
        console.log('Datos base64 (primeros 100 chars):', base64Data.substring(0, 100));
        
        // Decodificar base64
        const byteCharacters = atob(base64Data);
        const byteNumbers = new Array(byteCharacters.length);
        
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: mimeType });
        
        // Crear URL del blob
        const blobUrl = URL.createObjectURL(blob);
        
        // Crear enlace de descarga
        const link = document.createElement('a');
        const fileName = `captura-soporte-${new Date().toISOString().slice(0, 10)}-${Date.now()}.${mimeType.split('/')[1]}`;
        
        link.href = blobUrl;
        link.download = fileName;
        link.style.display = 'none';
        
        // Agregar al DOM y hacer clic
        document.body.appendChild(link);
        link.click();
        
        // Limpiar
        setTimeout(() => {
            document.body.removeChild(link);
            URL.revokeObjectURL(blobUrl);
            console.log('Descarga completada:', fileName);
        }, 100);
        
        // Mostrar confirmación
        SwalTheme.fire({
            icon: 'success',
            title: '¡Descargada!',
            html: `<div class="text-center">
                      <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                      <p>Captura guardada como:</p>
                      <code class="d-block small">${fileName}</code>
                      <small class="text-muted">Ahora puedes adjuntarla a tu correo</small>
                   </div>`,
            timer: 4000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al descargar:', error);
        
        SwalTheme.fire({
            icon: 'error',
            title: 'Error en descarga',
            html: `<div class="text-start">
                      <p>No se pudo descargar la captura:</p>
                      <p class="small text-danger">${error.message}</p>
                      <p class="small">Solución alternativa:</p>
                      <ol class="small">
                          <li>Haz clic derecho en la vista previa de la captura</li>
                          <li>Selecciona "Guardar imagen como..."</li>
                          <li>Guarda la imagen manualmente</li>
                      </ol>
                   </div>`,
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
            width: '500px'
        });
    }
}

// Función de ayuda para debug
function debugCaptura() {
    console.log('=== DEBUG DE CAPTURA ===');
    console.log('1. window.currentScreenshot:', window.currentScreenshot ? 'EXISTE' : 'NO EXISTE');
    
    const screenshotImg = document.querySelector('#screenshot-preview img');
    console.log('2. Elemento img en preview:', screenshotImg ? 'EXISTE' : 'NO EXISTE');
    if (screenshotImg) {
        console.log('   - src:', screenshotImg.src.substring(0, 100) + '...');
    }
    
    console.log('3. Checkbox estado:', document.getElementById('attach-screenshot')?.checked);
    console.log('4. Preview innerHTML:', document.getElementById('screenshot-preview')?.innerHTML.substring(0, 200) + '...');
    console.log('=== FIN DEBUG ===');
}
// Función para generar resumen HTML - VERSIÓN SEGURA
function generarResumenHTML(name, issueType, urgency, hasScreenshot, ticketId) {
    const issueTypes = {
        'error_sistema': 'Error del Sistema',
        'funcionalidad': 'Funcionalidad no funciona',
        'rendimiento': 'Problema de Rendimiento',
        'seguridad': 'Problema de Seguridad',
        'usuario': 'Configuración de Usuario',
        'facturacion': 'Facturación/Impresión',
        'reporte': 'Error en Reporte',
        'otro': 'Otro'
    };
    
    const urgencyColors = {
        'baja': '#28a745',
        'media': '#ffc107',
        'alta': '#fd7e14',
        'critica': '#dc3545'
    };
    
    const issueTypeName = issueTypes[issueType] || issueType || 'Consulta';
    const urgencyColor = urgencyColors[urgency] || '#6c757d';
    
    return `
        <div class="text-start">
            <div class="alert alert-info mb-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-circle fa-2x me-3"></i>
                    <div>
                        <strong>${name}</strong>
                        <div class="small">Ticket ID: ${ticketId}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-6">
                    <small class="text-muted">Tipo de Problema</small>
                    <p class="mb-1"><i class="fas fa-bug me-1"></i> ${issueTypeName}</p>
                </div>
                
                ${urgency ? `
                <div class="col-6">
                    <small class="text-muted">Urgencia</small>
                    <p class="mb-1">
                        <i class="fas fa-exclamation-triangle me-1"></i> 
                        <span style="color: ${urgencyColor}">${urgency.toUpperCase()}</span>
                    </p>
                </div>
                ` : ''}
            </div>
            
            ${hasScreenshot ? `
            <div class="alert alert-success mb-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-camera fa-lg me-3"></i>
                    <div>
                        <strong>Captura de pantalla incluida</strong>
                        <small class="d-block">Se te guiará para adjuntarla</small>
                    </div>
                </div>
            </div>
            ` : ''}
            
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Selecciona cómo enviar:</strong>
                <ul class="mb-0 mt-2 small">
                    <li><strong>Cliente de Correo:</strong> Abre tu aplicación de correo local</li>
                    <li><strong>Gmail Web:</strong> Abre Gmail en tu navegador</li>
                    <li><strong>Copiar:</strong> Copia el texto para pegarlo manualmente</li>
                </ul>
            </div>
        </div>
    `;
}

// Función para generar contenido del correo - VERSIÓN SEGURA
function generarContenidoCorreo(data) {
    const {
        name, message, phone, emailUser,
        issueType, urgency, affectedModule,
        errorCode, stepsToReproduce, hasScreenshot
    } = data;
    
    // Mapear tipos de problema
    const issueTypes = {
        'error_sistema': 'Error del Sistema',
        'funcionalidad': 'Funcionalidad no funciona',
        'rendimiento': 'Problema de Rendimiento',
        'seguridad': 'Problema de Seguridad',
        'usuario': 'Configuración de Usuario',
        'facturacion': 'Facturación/Impresión',
        'reporte': 'Error en Reporte',
        'otro': 'Otro'
    };
    
    // Crear asunto
    const issueTypeName = issueTypes[issueType] || issueType || 'Consulta';
    const urgencyText = urgency ? `[${urgency.toUpperCase()}] ` : '';
    const subject = `${urgencyText}Soporte SISFACT - ${issueTypeName} - ${name}`;
    
    // Formatear teléfono
    let formattedPhone = 'No proporcionado';
    if (phone) {
        const cleanPhone = phone.replace(/\D/g, '');
        if (cleanPhone.length === 8 && !cleanPhone.startsWith('53')) {
            formattedPhone = `+53 ${cleanPhone.slice(0, 4)} ${cleanPhone.slice(4)}`;
        } else if (cleanPhone.length > 0) {
            formattedPhone = `+${cleanPhone}`;
        }
    }
    
    // Información del sistema
    const now = new Date();
    const fecha = now.toLocaleDateString('es-ES', { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    const hora = now.toLocaleTimeString('es-ES', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
    
    // Generar Ticket ID
    const ticketId = 'TICKET-' + 
        now.getFullYear().toString().slice(-2) + 
        (now.getMonth() + 1).toString().padStart(2, '0') + 
        now.getDate().toString().padStart(2, '0') + '-' +
        now.getHours().toString().padStart(2, '0') + 
        now.getMinutes().toString().padStart(2, '0') + 
        now.getSeconds().toString().padStart(2, '0') + '-' +
        Math.random().toString(36).substring(2, 6).toUpperCase();
    
    // Crear cuerpo del correo - usando template literal
    const body = `═══════════════════════════════════════════════════════════════════════
                    SOLICITUD DE SOPORTE - SISFACT PDL Visiones
═══════════════════════════════════════════════════════════════════════

🆔 TICKET ID: ${ticketId}
📅 GENERADO: ${fecha} a las ${hora}
🌐 URL: ${window.location.href}
📧 ENVIADO POR: ${emailUser || 'Formulario web'}

───────────────────────────────────────────────────────────────────────
👤 INFORMACIÓN DEL USUARIO
───────────────────────────────────────────────────────────────────────
• Nombre: ${name}
• Email: ${emailUser || 'No proporcionado'}
• Teléfono: ${formattedPhone}
• Conexión: ${navigator.onLine ? 'Sí' : 'No'}

───────────────────────────────────────────────────────────────────────
⚠️ DETALLES DE LA INCIDENCIA
───────────────────────────────────────────────────────────────────────
• Tipo de problema: ${issueTypeName}
• Nivel de urgencia: ${urgency ? urgency.toUpperCase() : 'No especificado'}
• Módulo/Sección afectada: ${affectedModule || 'No especificado'}
• Código de error: ${errorCode || 'No aplica'}
• Captura de pantalla: ${hasScreenshot ? 'SÍ (adjunta)' : 'No'}

───────────────────────────────────────────────────────────────────────
📋 DESCRIPCIÓN DEL PROBLEMA
───────────────────────────────────────────────────────────────────────
${message}

${stepsToReproduce ? `───────────────────────────────────────────────────────────────────────
🔧 PASOS PARA REPRODUCIR
───────────────────────────────────────────────────────────────────────
${stepsToReproduce}

` : ''}───────────────────────────────────────────────────────────────────────
💻 INFORMACIÓN DEL SISTEMA
───────────────────────────────────────────────────────────────────────
• Fecha y hora: ${fecha} ${hora}
• URL de la página: ${window.location.href}
• Navegador y sistema: ${navigator.userAgent.split(') ')[0] + ')'}
• Sistema operativo: ${navigator.platform}
• Resolución de pantalla: ${window.screen.width}x${window.screen.height}
• Estado conexión: ${navigator.onLine ? 'Sí' : 'No'}

═══════════════════════════════════════════════════════════════════════
📧 CONTACTO ALTERNATIVO
• WhatsApp Soporte: +53 5986 0773
• Horario atención: L-V 8:00 AM - 5:00 PM
═══════════════════════════════════════════════════════════════════════

NOTA: Este mensaje fue generado automáticamente desde el formulario de soporte.
Para seguimiento, utilice el Ticket ID: ${ticketId}`;

    return {
        subject,
        body,
        ticketId
    };
}
// ============================================
// 6. INICIALIZACIÓN PRINCIPAL
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Sistema iniciado.');


const fixZIndex = () => {
    const body = document.body;
    const contactBtn = document.getElementById('contact-panel');
    const chatWidget = document.getElementById('chat-widget');
    
    if (contactBtn && contactBtn.parentNode !== body) {
        body.appendChild(contactBtn);
    }
    if (chatWidget && chatWidget.parentNode !== body) {
        body.appendChild(chatWidget);
    }
};
fixZIndex();

    // A. LÓGICA DE ESTADO (MANTENIMIENTO)
    fetch('index.php?action=check_status')
        .then(res => res.json())
        .then(data => {
            if (data.db_ok) {
                if (data.maintenance) {
                    // Si hay mantenimiento: Abrir modal y bloquear botones
                    openMaintenanceModal();
                    disableLoginAccess();
					showProgrammerButton(); 
                }
            }
        })
        .catch(e => console.error(e));

    // B. LISTENERS ORIGINALES
    const contactPanel = document.getElementById('contact-panel');
	contactPanel.onclick = function(e) { e.preventDefault(); e.stopPropagation(); toggleChat(); };
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('win11-modal-overlay')) {
            // Permitir cerrar maintenanceModal haciendo click fuera
            if (e.target.id === 'maintenanceModal') { closeMaintenanceModal(); }
            else { e.target.classList.remove('active'); document.body.style.overflow = 'auto'; }
        }
        const closeButton = e.target.closest('#close-chat-btn');
        if (closeButton) { e.preventDefault(); e.stopPropagation(); closeChat(); }
    });

    /*document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (chatOpen) closeChat();
            document.querySelectorAll('.win11-modal-overlay.active').forEach(m => {
                m.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }
    });*/

    // C. NAVBAR SCROLL
    const navbar = document.querySelector('.navbar');
    let lastScrollY = window.scrollY;
    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        if (currentScrollY > lastScrollY && currentScrollY > 100) { navbar.classList.add('navbar-hidden'); } 
        else { navbar.classList.remove('navbar-hidden'); }
        if (currentScrollY > 10) navbar.classList.add('navbar-scrolled');
        else navbar.classList.remove('navbar-scrolled');
        lastScrollY = currentScrollY;
    });

// D. FOOTER AUTO-HIDE (SOLO VISIBLE AL FINAL)
    const footer = document.querySelector("footer");
    if (footer) {
        window.addEventListener("scroll", () => {
            const current = window.scrollY;
            const windowHeight = window.innerHeight;
            const bodyHeight = document.body.offsetHeight;

            // Detectar si estamos al final de la página (con margen de 20px)
            const isBottom = (windowHeight + current) >= (bodyHeight - 20);

            if (isBottom) {
                // 1. Si llegamos al final -> MOSTRAR
                footer.classList.remove("hide-footer");
                footer.classList.add("show-footer");
            } else {
                // 2. Si estamos en cualquier otro punto (subiendo o bajando) -> OCULTAR
                footer.classList.remove("show-footer");
                footer.classList.add("hide-footer");
            }
        });
        
        // Inicialización
        setTimeout(() => footer.classList.add("hide-footer"), 500);
    }
    const backBtn = document.getElementById("back-to-top");
    if (backBtn) { backBtn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" })); }

    setTimeout(initChatForm, 1000);
	
	const footerLoginBtn = document.getElementById("footer-login-btn");
	if (footerLoginBtn) {
		footerLoginBtn.addEventListener("click", function(e) {
			// Lógica de mantenimiento se aplicará automáticamente
			// ya que redirige a login.php
		});
	}
});

// ============================================
// NUEVO: BOTÓN FLOTANTE DE PROGRAMADOR
// ============================================
function showProgrammerButton() {
    // Evitar duplicados
    if (document.getElementById('btn-programmer-access')) return;

    // Crear el botón
    const btn = document.createElement('a');
    btn.id = 'btn-programmer-access';
    btn.href = 'login.php?access_bypass=true'; 
    btn.innerHTML = '<i class="fa-solid fa-user-secret"></i> <span class="prog-text">Acceder Programador</span>';
    
    // Estilos CSS inyectados directamente para asegurar la posición
    Object.assign(btn.style, {
        position: 'fixed',
        bottom: '90px', // 90px desde abajo (encima del botón de soporte que suele estar a 20px)
        right: '20px',
        zIndex: '100000', // Por encima de todo
        backgroundColor: '#dc3545', // Rojo peligro/admin
        color: 'white',
        padding: '10px 20px',
        borderRadius: '50px',
        textDecoration: 'none',
        boxShadow: '0 4px 15px rgba(220, 53, 69, 0.5)',
        fontWeight: 'bold',
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        fontSize: '14px',
        border: '2px solid rgba(255,255,255,0.2)',
        transition: 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)',
        cursor: 'pointer'
    });

    // Efecto Hover
    btn.onmouseover = function() {
        this.style.transform = 'translateY(-5px) scale(1.05)';
        this.style.boxShadow = '0 8px 25px rgba(220, 53, 69, 0.7)';
        this.style.backgroundColor = '#ff4d5e';
    };
    btn.onmouseout = function() {
        this.style.transform = 'translateY(0) scale(1)';
        this.style.boxShadow = '0 4px 15px rgba(220, 53, 69, 0.5)';
        this.style.backgroundColor = '#dc3545';
    };

    // Estilo para el texto (para ocultarlo en móviles si es necesario, opcional)
    const styleElem = document.createElement('style');
    styleElem.innerHTML = `
        @media (max-width: 480px) {
            #btn-programmer-access .prog-text { display: none; }
            #btn-programmer-access { padding: 12px !important; border-radius: 50% !important; width: 50px; height: 50px; justify-content: center; }
        }
    `;
    document.head.appendChild(styleElem);

    // Agregar al cuerpo del documento
    document.body.appendChild(btn);
}

// ============================================
// MODIFICADO: LÓGICA RE-COMPROBAR MANTENIMIENTO (DINÁMICA)
// ============================================
function recheckMaintenanceStatus(callerBtn = null) {
    // Identificar qué botón llamó a la función. Si es null, buscamos el del modal de mantenimiento por defecto
    let btn = callerBtn || document.getElementById('btn-recheck-maint');
    let originalContent = '';
    let icon = null;

    // 1. Estado de carga visual en el botón
    if (btn) {
        originalContent = btn.innerHTML;
        btn.disabled = true;
        
        // Buscamos si tiene un icono dentro para hacerlo girar
        icon = btn.querySelector('i');
        if (icon) {
            // Si tiene icono, lo ponemos a girar
            icon.classList.remove('fa-rotate-right'); // Quitamos el estático si existe
            icon.classList.add('fa-spinner', 'fa-spin');
        } else {
            // Si no tiene icono (o es texto puro), cambiamos el HTML
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
    }

    // 2. Intentar conectar con el servidor
    fetch('index.php?action=check_status')
        .then(response => {
            if (!response.ok) { throw new Error('Error de red o servidor'); }
            return response.json();
        })
        .then(data => {
            setTimeout(() => {
                
                // === CASO 1: SIGUE EN MANTENIMIENTO (1) ===
                if (data.db_ok && data.maintenance) {
                    
                    const now = new Date();
                    const fecha = now.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                    
                    let hours = now.getHours();
                    const minutes = now.getMinutes().toString().padStart(2, '0');
                    const ampm = hours >= 12 ? 'P. M.' : 'A. M.';
                    hours = hours % 12; 
                    hours = hours ? hours : 12;
                    const hora = `${hours.toString().padStart(2, '0')}:${minutes} ${ampm}`;

                    SwalTheme.fire({
                        icon: 'info',
                        title: 'Sistema en mantenimiento',
                        html: `
                            <div style="font-size: 1.1em; color: #e0e0e0;">
                                El sistema continúa en mantenimiento.<br>
                                <span style="color: #ffc107; font-size: 0.9em; margin-top: 15px; display: block; background: rgba(255,193,7,0.1); padding: 8px; border-radius: 5px;">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Última verificación:<br>
                                    <strong>${fecha} - ${hora}</strong>
                                </span>
                            </div>
                        `,
                        confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Entendido',
                        confirmButtonColor: '#ffc107',
                        allowOutsideClick: false,
                        background: '#1e2a3a',
                        didOpen: () => {
                            const container = Swal.getContainer();
                            if(container) container.style.zIndex = '200000';
                            const b = Swal.getConfirmButton();
                            if(b) b.style.color = '#000'; 
                        }
                    });

                // === CASO 2: YA NO HAY MANTENIMIENTO (0) ===
                } else if (data.db_ok && !data.maintenance) {
                    SwalTheme.fire({
                        icon: 'success',
                        title: '¡Mantenimiento finalizado!',
                        text: 'El sistema está operativo nuevamente. Recargando...',
                        timer: 2000,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        didOpen: () => {
                            const container = Swal.getContainer();
                            if(container) container.style.zIndex = '200000';
                        }
                    }).then(() => {
                        location.reload();
                    });

                } else {
                     throw new Error('Error de base de datos');
                }

                // Restaurar botón
                if (btn) {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }

            }, 800);
        })
        .catch(error => {
            console.error('Error de comprobación:', error);
            
            SwalTheme.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo verificar el estado. Revisa tu internet.',
                confirmButtonText: '<i class="fa-solid fa-rotate-right me-2"></i> Reintentar',
                confirmButtonColor: '#d33',
                allowOutsideClick: false,
                didOpen: () => {
                    const container = Swal.getContainer();
                    if(container) container.style.zIndex = '200000';
                }
            });

            // Restaurar botón
            if (btn) {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('maintenanceModal');
    
    if (modal) {
        // Bloquear clic fuera
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                e.preventDefault();
                e.stopPropagation();
                
                // Efecto visual opcional
                modal.style.backgroundColor = 'rgba(0,0,0,0.95)';
                setTimeout(() => {
                    modal.style.backgroundColor = 'rgba(0,0,0,0.85)';
                }, 200);
                
                return false;
            }
        });
        
        // Bloquear tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && getComputedStyle(modal).display !== 'none') {
                e.preventDefault();
                e.stopPropagation();
                
                // Mostrar alerta pequeña
                const alert = document.createElement('div');
                alert.style.cssText = `
                    position: fixed; top: 10px; right: 10px; 
                    background: #ffc107; color: #000; padding: 8px 15px;
                    border-radius: 5px; z-index: 1000000; font-size: 14px;
                `;
                alert.innerHTML = '⚠️ Use los botones para cerrar';
                document.body.appendChild(alert);
                
                setTimeout(() => alert.remove(), 2000);
                return false;
            }
        });
    }
});
// ============================================
// FUNCIONES PARA MODAL DE MIEMBRO DEL EQUIPO
// ============================================


// Modificar la función openTeamMemberModal para actualizar el índice
function openTeamMemberModal(element) {
    // Obtener datos del miembro
    const name = element.getAttribute('data-name');
    const role = element.getAttribute('data-role');
    const desc = element.getAttribute('data-desc');
    const imgSrc = element.getAttribute('src');
    
    // Configurar el modal
    document.getElementById('teamModalImg').src = imgSrc;
    document.getElementById('teamModalImg').alt = name;
    document.getElementById('teamModalName').textContent = name;
    document.getElementById('teamModalRole').textContent = role;
    document.getElementById('teamModalDesc').textContent = desc;
    
    // Actualizar índice actual
    const index = teamMembers.indexOf(element);
    if (index !== -1) {
        currentTeamMemberIndex = index;
    }
    
    // Mostrar el modal
    document.getElementById('teamMemberModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeTeamMemberModal() {
    document.getElementById('teamMemberModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('team-member-modal-overlay')) {
        closeTeamMemberModal();
    }
});

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('teamMemberModal');
        if (modal.classList.contains('active')) {
            closeTeamMemberModal();
        }
    }
});

// Hacer que todo el área del miembro sea clickeable (opcional)
document.querySelectorAll('.team-member').forEach(member => {
    member.style.cursor = 'pointer';
    member.addEventListener('click', function(e) {
        // Evitar abrir el modal si se hace clic en un enlace o botón
        if (!e.target.closest('a') && !e.target.closest('button')) {
            const img = this.querySelector('.team-member-img');
            if (img) openTeamMemberModal(img);
        }
    });
});
// Variables para navegación entre miembros
let currentTeamMemberIndex = 0;
let teamMembers = [];

// Función para inicializar la lista de miembros
function initTeamMembers() {
    teamMembers = Array.from(document.querySelectorAll('.team-member-img'));
}

// Función para navegar al siguiente miembro
function navigateToNextMember() {
    if (teamMembers.length === 0) return;
    
    currentTeamMemberIndex = (currentTeamMemberIndex + 1) % teamMembers.length;
    openTeamMemberModal(teamMembers[currentTeamMemberIndex]);
}

// Función para navegar al miembro anterior
function navigateToPrevMember() {
    if (teamMembers.length === 0) return;
    
    currentTeamMemberIndex = (currentTeamMemberIndex - 1 + teamMembers.length) % teamMembers.length;
    openTeamMemberModal(teamMembers[currentTeamMemberIndex]);
}


// Inicializar cuando se cargue la página
document.addEventListener('DOMContentLoaded', function() {
    initTeamMembers();
 // Inicializar variable global
    window.currentScreenshot = null;
    window.lastScreenshot = null;
    
    // Verificar si hay captura en el preview al cargar
    setTimeout(() => {
        const screenshotImg = document.querySelector('#screenshot-preview img');
        if (screenshotImg && screenshotImg.src.startsWith('data:image')) {
            window.currentScreenshot = screenshotImg.src;
            window.lastScreenshot = screenshotImg.src;
            console.log('Captura encontrada al cargar página');
        }
    }, 1000);
});
</script>
<!-- Modal para detalles del miembro del equipo (versión mejorada) -->
<div class="team-member-modal-overlay" id="teamMemberModal">
    <div class="team-member-modal">
        <div class="team-member-modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="modal-header-icon">
                    <i class="fa-solid fa-users" style="color: #25d366;"></i>
                </div>
                <div>
                    <h3 style="color: #fff; margin: 0; font-size: 20px;">Equipo PDL Visiones</h3>
                    <p style="color: #8b949e; margin: 0; font-size: 12px;">Detalles del miembro</p>
                </div>
            </div>
            <button class="team-member-modal-close" onclick="closeTeamMemberModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        
        <div class="team-member-modal-body">
            <!-- Enunciado con iconos y diseño mejorado -->
            <div class="team-modal-intro-enhanced">
                <div class="intro-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <h4 class="intro-title">Nuestro Equipo de Expertos</h4>
                <p class="intro-text">
                    Profesionales que hacen posible el <strong>Proyecto de Desarrollo Local (PDL Visiones)</strong>.
                    Expertos en tecnología, atención al cliente y soluciones empresariales.
                </p>
                <div class="intro-tags">
                    <span class="intro-tag"><i class="fa-solid fa-microchip"></i> Tecnología</span>
                    <span class="intro-tag"><i class="fa-solid fa-headset"></i> Atención al Cliente</span>
                    <span class="intro-tag"><i class="fa-solid fa-chart-line"></i> Soluciones Empresariales</span>
                </div>
            </div>
            
            <div class="team-modal-content">
                <img src="" alt="Foto del miembro" class="team-member-modal-img" id="teamModalImg">
                <h4 class="team-member-modal-name" id="teamModalName"></h4>
                <div class="team-member-modal-role" id="teamModalRole"></div>
                <p class="team-member-modal-desc" id="teamModalDesc"></p>
            </div>
        </div>
        
        <!-- Footer del modal -->
        <div class="team-modal-footer">
            <button class="team-modal-nav-btn" onclick="navigateToPrevMember()">
                <i class="fa-solid fa-chevron-left"></i> Anterior
            </button>
            <span style="color: #8b949e; font-size: 12px;">
                <i class="fa-solid fa-people-group"></i> Equipo de Élite
            </span>
            <button class="team-modal-nav-btn" onclick="navigateToNextMember()">
                Siguiente <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>
</body>
</html>