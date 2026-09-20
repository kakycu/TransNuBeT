<?php
// config/database.php
// Verificar si la clase ya está definida para evitar conflictos
if (!class_exists('Database')) {

class Database {
    // 1. DEFINIMOS LAS CREDENCIALES UNA SOLA VEZ AQUÍ
    private static $host = 'localhost';
    private static $db_name = 'sisfact_imdl';
    private static $username = 'root';
    private static $password = 'frl8110kaky';
    
    private static $connection = null;

    public static function getConnection() {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4",
                    self::$username,
                    self::$password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                self::showDatabaseError($e);
            }
        }
        return self::$connection;
    }
// --- MODO DE MANTENIMIENTO MODIFICADO ---
public static function checkMaintenanceMode() {
    // Asegurar que la sesión esté iniciada para leer el rol
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    try {
        $db = self::getConnection();
        $sql = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        // Verificar si el modo de mantenimiento está activo en BD
        $isMaintenanceActive = ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] >= 1);

        if ($isMaintenanceActive) {
            
            // ===== NUEVA LÓGICA PARA LOGIN =====
            // Verificar si viene del bypass en la URL (para login.php)
            if (isset($_GET['access_bypass']) && $_GET['access_bypass'] === 'true') {
                // Mostrar alerta visual de que está en modo bypass
                if (!isset($_SESSION['usuario_id'])) { // Solo mostrar en login, no en páginas internas
                    self::renderBypassAlert();
                }
                return false; // Permitir acceso COMPLETAMENTE
            }
            
// ===== USUARIOS YA LOGEADOS =====
if (isset($_SESSION['usuario_id'])) {
    $userRol = $_SESSION['rol_id'] ?? $_SESSION['usuario_rol'] ?? 0;
    
    // ROLES PERMITIDOS EN MANTENIMIENTO
    $roles_permitidos = [4, 5]; // 4 = Supervisor, 5 = Programador
    
    if (in_array($userRol, $roles_permitidos)) {
        // ❌ NO renderizar aquí - el header.php ya lo hace
        // self::renderMaintenanceAlert(); // ← ELIMINAR ESTA LÍNEA
        
        // ✅ SOLO establecer la variable de sesión
        $_SESSION['maintenance_bypass_notice'] = true;
        
        return false; // Permitir acceso
    }
}
            
            // Si no hay sesión Y no hay bypass, o el rol no está permitido
            return true; // BLOQUEAR
        }

        return false; // No hay mantenimiento

    } catch (Exception $e) {
        // Si hay error al consultar, no activamos mantenimiento
        return false;
    }
}



// NUEVA FUNCIÓN: Alerta para modo bypass
private static function renderBypassAlert() {

    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        echo '
<style>
@keyframes slideDown {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes glow {
    0% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); }
    50% { box-shadow: 0 4px 25px rgba(220, 53, 69, 0.7); }
    100% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); }
}

@keyframes shine {
    0% { left: -100%; }
    20% { left: 100%; }
    100% { left: 100%; }
}

.bypass-notification {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(135deg, #ff416c, #ff4b2b, #dc3545);
    background-size: 200% 200%;
    color: white;
    text-align: center;
    padding: 14px 20px;
    z-index: 999999;
    font-weight: 600;
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 20px rgba(220, 53, 69, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    animation: slideDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), glow 2s infinite;
    backdrop-filter: blur(5px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    letter-spacing: 0.5px;
    transform-origin: top;
    overflow: hidden;
}

.bypass-notification::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: shine 3s infinite;
    pointer-events: none;
}

.bypass-notification i {
    font-size: 1.4rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    animation: pulse 2s infinite;
}

.bypass-notification i:first-child {
    animation: pulse 2s infinite 0.5s;
}

.bypass-notification span {
    background: rgba(255, 255, 255, 0.15);
    padding: 6px 18px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.95rem;
    letter-spacing: 1px;
}

.bypass-close {
    position: absolute;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.bypass-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg) scale(1.1);
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
}

.bypass-close:active {
    transform: rotate(180deg) scale(0.9);
}

@keyframes floatingParticles {
    0% { transform: translateY(0) rotate(0deg); opacity: 0; }
    50% { opacity: 0.5; }
    100% { transform: translateY(-20px) rotate(360deg); opacity: 0; }
}

.bypass-notification .particle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    pointer-events: none;
    animation: floatingParticles 3s infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .bypass-notification {
        flex-direction: column;
        gap: 10px;
        padding: 12px;
    }
    
    .bypass-notification span {
        font-size: 0.85rem;
        padding: 4px 12px;
    }
    
    .bypass-close {
        position: relative;
        right: auto;
        margin-top: 5px;
    }
}
</style>

<div class="bypass-notification" id="bypassNotification">
    <!-- Partículas decorativas -->
    <div class="particle" style="top: 10%; left: 10%; animation-delay: 0s;"></div>
    <div class="particle" style="top: 20%; left: 20%; animation-delay: 0.5s;"></div>
    <div class="particle" style="top: 30%; left: 30%; animation-delay: 1s;"></div>
    <div class="particle" style="top: 40%; left: 40%; animation-delay: 1.5s;"></div>
    <div class="particle" style="top: 50%; left: 50%; animation-delay: 2s;"></div>
    
    <i class="fas fa-unlock-alt" style="animation: pulse 2s infinite;"></i>
    
    <span>
        <i class="fas fa-code me-2" style="font-size: 0.9rem; animation: none;"></i>
        MODO BYPASS ACTIVADO
        <i class="fas fa-shield-alt mx-2" style="font-size: 0.9rem; animation: none;"></i>
    </span>
    
    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="background: rgba(0,0,0,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">
            <i class="fas fa-user-cog me-1"></i> Acceso Programador
        </span>
        <span style="background: #ffc107; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700;">
            <i class="fas fa-tools me-1"></i> Mantenimiento Activo
        </span>
    </div>
    
    <i class="fas fa-tools" style="animation: pulse 2s infinite 0.5s;"></i>
    
    <button class="bypass-close" onclick="this.parentElement.remove(); document.body.style.marginTop=\'0\';" title="Cerrar notificación">
        <i class="fas fa-times"></i>
    </button>
</div>

<script>
// Auto-ocultar después de 5 segundos con animación suave
setTimeout(function() {
    const notification = document.getElementById("bypassNotification");
    if (notification) {
        notification.style.transition = "transform 0.5s ease, opacity 0.5s ease";
        notification.style.transform = "translateY(-100%)";
        notification.style.opacity = "0";
        setTimeout(() => {
            notification.remove();
            document.body.style.marginTop = "0";
        }, 500);
    }
}, 5000);

// Hacer que reaparezca al pasar el mouse cerca del top
window.addEventListener("scroll", function() {
    const notification = document.getElementById("bypassNotification");
    if (!notification) return;
    
    if (window.scrollY < 50) {
        notification.style.transform = "translateY(0)";
        notification.style.opacity = "1";
    } else {
        notification.style.transform = "translateY(-100%)";
        notification.style.opacity = "0";
    }
});

// Mostrar al hacer hover en el header
document.addEventListener("mouseover", function(e) {
    const notification = document.getElementById("bypassNotification");
    if (!notification) return;
    
    if (e.target.closest("header, nav, .navbar, .win-navbar, .header-actions")) {
        notification.style.transform = "translateY(0)";
        notification.style.opacity = "1";
    }
});
</script>

        <script>
            // Ajustar margen del cuerpo para que no tape el contenido
            document.addEventListener("DOMContentLoaded", function() {
                document.body.style.marginTop = "55px";
            });
        </script>
        ';
    }
}
	


    public static function showMaintenancePage() {
        try {
            $db = self::getConnection();
            // Obtener información de la empresa para mostrar en la página
            $sql = "SELECT nombre_empresa, logo FROM configuracion_sistema LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch();

            $empresa = $config['nombre_empresa'] ?? 'Sistema PDL Visiones';
            $logoBase64 = $config['logo'] ?? null;
            
            // Manejar el logo en base64
            if ($logoBase64 && !empty(trim($logoBase64))) {
                $logoBase64 = trim($logoBase64);
                if (strpos($logoBase64, 'data:image') === 0) {
                    $logo = $logoBase64;
                }
                else if (strlen($logoBase64) > 50) { 
                    $decoded = @base64_decode($logoBase64, true);
                    if ($decoded !== false) {
                        $imageInfo = @getimagesizefromstring($decoded);
                        if ($imageInfo !== false) {
                            $mime_type = $imageInfo['mime'];
                            $logo = 'data:' . $mime_type . ';base64,' . $logoBase64;
                        } else {
                            $logo = 'assets/logov.png';
                        }
                    } else {
                        $logo = 'assets/logov.png';
                    }
                } else {
                    $logo = 'assets/logov.png';
                }
            } else {
                $logo = 'assets/logov.png';
            }
        } catch (Exception $e) {
            $empresa = 'Sistema PDL Visiones';
            $logo = 'assets/logov.png';
        }

        if (ob_get_length()) ob_clean();
        ?>
        
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Modo Mantenimiento - SISFACT PDL VISIONES</title>
            <link rel="icon" type="image/x-icon" href="assets/logov.png">
            <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
            <style>
                body { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    min-height: 100vh; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    font-family: 'Segoe UI', system-ui, sans-serif; 
                    margin: 0; 
                }
                .maintenance-container { 
                    background: white; 
                    border-radius: 15px; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.2); 
                    width: 90%; 
                    max-width: 600px; 
                    overflow: hidden; 
                    animation: slideIn 0.5s ease-out; 
                }
                @keyframes slideIn { 
                    from { transform: translateY(-20px); opacity: 0; } 
                    to { transform: translateY(0); opacity: 1; } 
                }
                .maintenance-header { 
                    background: linear-gradient(135deg, #FFA500, #FF8C00); 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .maintenance-icon { 
                    font-size: 64px; 
                    margin-bottom: 15px; 
                    animation: wrenchSpin 3s infinite; 
                }
                @keyframes wrenchSpin { 
                    0% { transform: rotate(0deg); } 
                    25% { transform: rotate(30deg); } 
                    50% { transform: rotate(0deg); } 
                    75% { transform: rotate(-30deg); } 
                    100% { transform: rotate(0deg); } 
                }
                .maintenance-body { 
                    padding: 30px; 
                }
                .maintenance-details { 
                    background: #fff9e6; 
                    border-left: 4px solid #FFA500; 
                    padding: 15px; 
                    margin: 20px 0; 
                    border-radius: 4px; 
                }
                .btn-refresh { 
                    background: #0078d4; 
                    color: white; 
                    border: none; 
                    padding: 12px 30px; 
                    border-radius: 50px; 
                    font-weight: 600; 
                    width: 100%; 
                    transition: all 0.3s; 
                    text-transform: uppercase; 
                }
                .btn-refresh:hover { 
                    background: #005a9e; 
                    transform: translateY(-2px); 
                }
                .maintenance-content { 
                    display: flex; 
                    align-items: center; 
                    justify-content: center;
                    gap: 15px; 
                    font-size: 1.5rem;
                }
                .progress-container {
                    margin: 20px 0;
                }
                .progress-bar {
                    height: 10px;
                    border-radius: 5px;
                    background: #e0e0e0;
                    overflow: hidden;
                }
                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #FFA500, #FF8C00);
                    animation: progressAnimation 2s infinite alternate;
                }
                @keyframes progressAnimation {
                    0% { width: 30%; }
                    100% { width: 70%; }
                }
                .countdown {
                    font-size: 2rem;
                    font-weight: bold;
                    color: #FF8C00;
                    text-align: center;
                    margin: 20px 0;
                }
                .btn-admin-login {
                    display: inline-block; margin-top: 20px; color: red; 
                    text-decoration: none; font-size: 1rem; border: 1px solid green;
                    padding: 8px 20px; border-radius: 30px; transition: all 0.3s;
                }
                .btn-admin-login:hover { background-color: green; color: white; border-color: black; }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="maintenance-header">
                    <div class="maintenance-content">
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo <?php echo htmlspecialchars($empresa); ?>" style="height: 100px;">
                        <?php echo htmlspecialchars($empresa); ?>
                        <i class="fas fa-tools maintenance-icon"></i>
                    </div>
                    <h2>Modo de Mantenimiento</h2>
                </div>
                <div class="maintenance-body">
                    <p class="text-center lead mb-4">
                        <strong><?php echo htmlspecialchars($empresa); ?> - <span class="text-success">(PDL VISIONES)</span></strong> está experimentando mejoras
                    </p>
                    
                    <div class="maintenance-details">
                        <strong><i class="fas fa-info-circle me-2"></i>Información:</strong>
                        <div class="mt-2">
                            Estamos realizando tareas de mantenimiento para mejorar tu experiencia. 
                            El sistema estará disponible nuevamente en breve.
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>
                    
                    <div class="countdown">
                        <i class="fas fa-clock me-2"></i>
                        <span id="countdown-timer">Volviendo pronto...</span>
                    </div>
                    
                    <h6 class="mt-4"><i class="fas fa-lightbulb text-warning me-2"></i>¿Qué está pasando?</h6>
                    <ul class="suggestion-list">
                        <li><i class="fas fa-sync-alt text-primary me-2"></i> Actualización de la base de datos y/o del Sistema</li>
                        <li><i class="fas fa-shield-alt text-success me-2"></i> Mejoras de seguridad</li>
                        <li><i class="fas fa-bolt text-warning me-2"></i> Optimización del rendimiento</li>
                        <li><i class="fas fa-plus-circle text-info me-2"></i> Implementación de nuevas funciones</li>
                    </ul>
                    
                    <button class="btn btn-refresh mt-4" onclick="window.location.reload()">
                        <i class="fas fa-redo me-2"></i>Verificar Disponibilidad
                    </button>
					<div class="text-center">
						<!-- ENLACE PARA ACCESO PROGRAMADOR CON BYPASS -->
						<a href="login.php?access_bypass=true" class="btn-admin-login">
							<i class="fas fa-unlock-alt me-1"></i> Acceso Programador
						</a>
					</div>
					<div class="text-center mt-4 text-muted small">
                        <i class="fas fa-envelope me-1"></i>
                        <a href="mailto:soporte_pdlvisiones@gmail.com?subject=Modo Mantenimiento de SISFACT PDL VISIONES"><strong>Contacto: soporte_pdlvisiones@gmail.com</strong></a>
                    </div>
                </div>
            </div>
			
            
            <script>
                // Simulación de cuenta regresiva
                let seconds = 300; // 5 minutos
                const countdownElement = document.getElementById('countdown-timer');
                
                function updateCountdown() {
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds % 60;
                    
                    countdownElement.textContent = 
                        `${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
                    
                    if (seconds > 0) {
                        seconds--;
                        setTimeout(updateCountdown, 1000);
                    } else {
                        countdownElement.textContent = "¡Sistema listo!";
                        countdownElement.style.color = "#107c10";
                    }
                }
                
                // Iniciar cuenta regresiva cuando la página cargue
                document.addEventListener('DOMContentLoaded', updateCountdown);
            </script>
        </body>
        </html>
        <?php
        exit();
    }


// --- FUNCIÓN DE PROGRESO FINANCIERO ---
public static function getProgresoFinanciero($mes = null, $anio = null) {
    try {
        $db = self::getConnection();
        
        // Obtener fechas de operaciones desde la base de datos
        $mes_cierre_num = obtenerMesCierreOperaciones();
        $anio_cierre_num = obtenerAnioCierreOperaciones();
        $fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d');
        $timestamp_op = strtotime($fecha_op_raw);
        
        // Usar las fechas de operaciones como referencia
        $mes = $mes ?? $mes_cierre_num;
        $anio = $anio ?? $anio_cierre_num;
        
        // Validar que mes y año sean numéricos
        if (!is_numeric($mes) || !is_numeric($anio)) {
            throw new Exception("Mes o año inválido");
        }
        
        // 1. Obtener la META
        $sql_plan = "SELECT importe FROM tbl_planes WHERE mes_plan = ? AND anio = ?";
        $stmt = $db->prepare($sql_plan);
        $stmt->execute([$mes, $anio]);
        $plan = $stmt->fetch();
        
        $meta = ($plan && $plan['importe'] > 0) ? floatval($plan['importe']) : 0;
        
        // 2. Obtener lo REAL y la CANTIDAD usando la fecha de operaciones como referencia
        // Filtramos por el año de operaciones en lugar de CURRENT_DATE()
$sql_real = "SELECT SUM(total_general) as total, COUNT(*) as cantidad 
            FROM tbl_fact 
            WHERE MONTH(fecha_emision) = ? 
            AND YEAR(fecha_emision) = ? 
            AND estado != 'ANULADA'
            AND YEAR(fecha_emision) = ?";
        $stmt_real = $db->prepare($sql_real);
        $stmt_real->execute([$mes, $anio, $anio_cierre_num]);
        $resultado = $stmt_real->fetch();
        
        $real = $resultado['total'] ? floatval($resultado['total']) : 0;
        $cantidad = $resultado['cantidad'] ? intval($resultado['cantidad']) : 0;
        
        // 3. Calcular porcentaje
        $porcentaje = $meta > 0 ? ($real / $meta) * 100 : 0;
        // 4. Estilos y mensaje
        $color = '#0078d4';
        $mensaje = 'En progreso';

        if ($real >= $meta && $meta > 0) {
            $color = '#004b1c';
            $mensaje = 'Meta superada';
        } elseif ($porcentaje >= 100) {
            $color = '#107c10';
            $mensaje = 'Meta alcanzada';
        } elseif ($porcentaje >= 80) {
            $color = '#107c10';
            $mensaje = 'Cerca de la meta';
        } elseif ($porcentaje >= 50) {
            $color = '#ffb900';
            $mensaje = 'Progreso moderado';
        } elseif ($porcentaje >= 30) {
            $color = '#f7630c';
            $mensaje = 'Progreso lento';
        } else {
            $color = '#e81123';
            $mensaje = 'Atención necesaria';
        }

		return [
			'meta' => $meta,
			'real' => $real,
			'cantidad' => $cantidad,
			'porcentaje' => round(($meta > 0 ? ($real / $meta) * 100 : 0), 2), // SIN min(..., 100)
			'color' => $color,
			'mensaje' => $mensaje,
			'error' => false,
			'mes_consulta' => $mes,
			'anio_consulta' => $anio,
			'mes_operaciones' => $mes_cierre_num,
			'anio_operaciones' => $anio_cierre_num
		];
        
    } catch (Exception $e) {
        // Fallback a fecha actual si hay error
        $mes_fallback = $mes ?? $mes_cierre_num;
        $anio_fallback = $anio ?? $anio_cierre_num;
        
        return [
            'meta' => 0, 
            'real' => 0, 
            'cantidad' => 0, 
            'porcentaje' => 0, 
            'color' => '#666', 
            'mensaje' => 'Error Calculando Planes', 
            'error' => true,
            'mes_consulta' => $mes_fallback,
            'anio_consulta' => $anio_fallback,
            'mes_operaciones' => $mes_cierre_num ?? date('n'),
            'anio_operaciones' => $anio_cierre_num ?? date('Y')
        ];
    }
}
	// --- MANEJO DE ERRORES  ---
    private static function showDatabaseError($exception) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $errorCode = $exception->getCode();
        $errorMessage = $exception->getMessage();
        $errorType = 'Error de Base de Datos';
        
        if (strpos($errorMessage, 'Unknown database') !== false) {
            $errorType = 'Base de Datos No Encontrada';
            $detailedMessage = "La base de datos '" . self::$db_name . "' no existe o el nombre es incorrecto.";
            $suggestions = ['Verifique que la base de datos esté creada', 'Compruebe el nombre en config', 'Ejecute el script SQL'];
        } elseif (strpos($errorMessage, 'Connection refused') !== false || strpos($errorMessage, 'Can\'t connect') !== false) {
            $errorType = 'Servidor No Disponible';
            $detailedMessage = "No se puede conectar al servidor MySQL.";
            $suggestions = ['Verifique que MySQL esté ejecutándose', 'Compruebe XAMPP/WAMP', 'Revise el puerto 3306'];
        } elseif (strpos($errorMessage, 'Access denied') !== false) {
            $errorType = 'Acceso Denegado';
            $detailedMessage = "Credenciales incorrectas.";
            $suggestions = ['Verifique usuario y contraseña', 'Revise permisos', 'Reinicie servicios'];
        } else {
            $errorType = 'Error de Conexión';
            $detailedMessage = "Error general de conexión.";
            $suggestions = ['Revise parámetros configuración', 'Revise Datos Conectividad','Contacte al administrador'];
        }
        
        $_SESSION['database_error'] = [
            'type' => $errorType,
            'message' => $detailedMessage,
            'details' => $errorMessage,
            'code' => $errorCode,
            'suggestions' => $suggestions,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!defined('DATABASE_ERROR_SHOWN')) {
            define('DATABASE_ERROR_SHOWN', true);
            self::renderErrorPage();
        }
        exit();
    }

    private static function renderErrorPage() {
        if (ob_get_length()) ob_clean();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error de Conexión - SISFACT PDL</title>
            <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
            <link rel="icon" type="image/x-icon" href="assets/logov.png">
            <style>
                body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; }
                .error-container { background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 90%; max-width: 600px; overflow: hidden; animation: slideIn 0.5s ease-out; }
                @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
                .error-header { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 30px 20px; text-align: center; }
                .error-icon { font-size: 64px; margin-bottom: 15px; animation: pulse 2s infinite; }
                @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
                .error-body { padding: 30px; }
                .error-details { background: #fff3f3; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .suggestion-list { list-style: none; padding: 0; margin-top: 5px; }
                .suggestion-list li { padding: 3px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; color: #555; }
                .suggestion-list i { color: #0078d4; margin-right: 10px; }
                .btn-retry { background: #0078d4; color: white; border: none; padding: 12px 30px; border-radius: 50px; font-weight: 600; width: 100%; transition: all 0.3s; text-transform: uppercase; }
                .btn-retry:hover { background: #005a9e; transform: translateY(-2px); }
                .error-content { display: flex; align-items: center; gap: 10px; font-size: 2rem;}
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-header">
                    <div class="error-content">
                        <img src="assets/logov.png" alt="Logo PDL Visiones">
                        <i class="fas fa-database"></i>
                        <h2><?php echo htmlspecialchars($_SESSION['database_error']['type']); ?></h2>
                    </div>
                </div>
                <div class="error-body">
                    <p class="text-center lead mb-4"><?php echo htmlspecialchars($_SESSION['database_error']['message']); ?></p>
                    <div class="error-details">
                        <strong><i class="fas fa-bug me-2"></i>Detalle técnico:</strong>
                        <div class="mt-2 font-monospace small text-muted"><?php echo htmlspecialchars($_SESSION['database_error']['details']); ?></div>
                    </div>
                    <h6 class="mt-4"><i class="fas fa-lightbulb text-warning me-2"></i>Sugerencias:</h6>
                    <ul class="suggestion-list">
                        <?php foreach ($_SESSION['database_error']['suggestions'] as $suggestion): ?>
                            <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($suggestion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button class="btn btn-retry mt-4" onclick="window.location.reload()"><i class="fas fa-sync-alt me-2"></i>Reintentar Conexión</button>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    public static function checkConnection() {
        try {
            $conn = self::getConnection();
            $conn->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
} 
?>