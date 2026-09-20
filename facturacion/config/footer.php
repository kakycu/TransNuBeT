<?php

require_once 'Database.php';

// Variables de empresa
$empresa_nombre = "SISFACT PDL Visiones";
$empresa_direccion = "Dirección no especificada";
$empresa_telefono = "Teléfono no especificado";
$empresa_email = "Email no especificado";
$empresa_cuenta_bancaria = "";
$empresa_banco = "";
$empresa_sucursal = "";
$director_nombre = "";
$facturador_nombre = "";

// Variables para información del usuario
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Invitado';
$usuario_rol = 'Sin rol';
$usuario_rol_codigo = '';

// Variables de tiempo y fecha
$hora_actual = date('h:i:s A');
$dia_actual = date('d/m/Y');
$fecha_cierre = 'No configurada';
$fecha_cierre_timestamp = null;

// Variables de sistema
$php_version = PHP_VERSION;
$php_os = php_uname('s') . ' ' . php_uname('r'). ' ' . php_uname('v');//round(memory_get_usage() / 1024 / 1024, 2) . ' MB';
$db_version = 'Desconocida';
$db_status = 'Desconocido';

try {
    $db = Database::getConnection();
    
    // Obtener versión de MariaDB
    $stmt = $db->query("SELECT VERSION() as version");
    if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db_version = preg_replace('/-MariaDB.*/', '', $row['version']);
        $db_status = 'Conectada';
    }
    
    // Obtener rol del usuario
    if (isset($_SESSION['usuario_id'])) {
        $sql = "SELECT cr.codigo as rol_codigo, cr.descripcion as rol_descripcion 
                FROM clasif_usuarios cu 
                LEFT JOIN clasif_rol cr ON cu.rol_id = cr.id 
                WHERE cu.id = :usuario_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario_id' => $_SESSION['usuario_id']]);
        
        if ($rol_data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usuario_rol = $rol_data['rol_descripcion'] ?? 'Sin rol';
            $usuario_rol_codigo = $rol_data['rol_codigo'] ?? '';
        }
    }
    
    // Obtener fecha de cierre
    $sql = "SELECT fecha_inicio_operaciones FROM configuracion_sistema LIMIT 1";
    $stmt = $db->query($sql);
	if ($stmt && $config = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if (!empty($config['fecha_inicio_operaciones'])) {
			$timestamp = strtotime($config['fecha_inicio_operaciones']);
			
			// 't' nos da el último día del mes
			$fecha_cierre = date('t/m/Y', $timestamp);
			
			// Si necesitas también el timestamp del último día del mes:
			$fecha_cierre_timestamp = strtotime(date('Y-m-t', $timestamp));
		}
	}
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}

// Tema
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
?>

    <footer class="dashboard-footer win-footer" 
            style="--win-accent: <?php echo $color_accent; ?>;"
            data-theme="<?php echo $tema_windows; ?>">
        <div class="container-fluid">
            <div class="row g-0">
                <!-- Línea 1: Información principal -->
                <div class="col-12 border-bottom win-border py-1">
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <!-- Sistema y PHP -->
                        <div class="d-flex align-items-center">
                            <i class="fas fa-desktop me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">SISFACT:</small>
                            <small class="me-3">v2.3.3</small>
                            
                            <i class="fab fa-php me-2 win-icon ms-2" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">PHP:</small>
                            <small><?php echo substr($php_version, 0, 6); ?></small>
                        </div>
                        
                        <!-- Usuario y Rol -->
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Usuario:</small>
                            <small class="me-3" title="<?php echo htmlspecialchars($usuario_nombre); ?>"><?php echo htmlspecialchars($usuario_nombre); ?></small>
                            
                            <span class="badge bg-danger badge-rol me-2" 
                                  style="font-size: 0.55rem; padding: 1px 3px;"
                                  title="<?php echo htmlspecialchars($usuario_rol); ?>">
                                <?php echo htmlspecialchars($usuario_rol ?: $usuario_rol_codigo); ?>
                            </span>
                            
                            <i class="fas fa-clock me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Sesión:</small>
                            <small id="sessionTimer">00:00:00</small>
                        </div>
                    </div>
                </div>
                
                <!-- Línea 2: Información secundaria -->
                <div class="col-12 py-1">
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <!-- Tiempo y Fechas -->
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Hora:</small>
                            <small id="currentTime" class="me-3"><?php echo $hora_actual; ?></small>
                            
                            <i class="fas fa-calendar-day me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Fecha:</small>
                            <small id="currentDate" class="me-3"><?php echo $dia_actual; ?></small>
                            
                            <i class="fas fa-calendar-check me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Próx. Cierre:</small>
                            <small id="cierreDate"><?php echo $fecha_cierre; ?></small>
                        </div>
                        
                        <!-- Base de Datos y Memoria -->
                        <div class="d-flex align-items-center">
                            <i class="fas fa-database me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">MariaDB:</small>
                            <small id="dbVersion" class="me-3" title="<?php echo htmlspecialchars($db_version); ?>">
                                <?php echo htmlspecialchars(substr($db_version, 0, 8)); ?>
                            </small>
                            
                            <i class="fas fa-signal me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Estado:</small>
                            <small id="dbStatus" class="me-3 <?php echo $db_status === 'Conectada' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $db_status; ?>
                            </small>
                            
                            <i class="fas fa-memory me-2 win-icon" style="font-size: 0.7rem;"></i>
                            <small class="fw-bold me-1">Kernel OS:</small>
                            <small id="SistemOperat"><?php echo $php_os; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .win-footer {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-width, 260px);
            right: 0;
            background-color: var(--win-bg-secondary);
            border-top: 1px solid var(--win-border-color);
            padding: 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
            font-size: 0.7rem;
            height: 54px; /* 2 líneas de 27px cada una */
        }
        
        .win-main-content.sidebar-mini + .win-footer {
            left: var(--sidebar-mini-width, 68px);
        }
        
        .win-footer .win-border {
            border-color: var(--win-border-color) !important;
        }
        
        .win-footer .win-icon {
            color: var(--win-accent);
            width: 12px;
            text-align: center;
        }
        
        /* Badge del rol */
        .badge-rol {
            opacity: 0.9;
            transition: all 0.2s ease;
            border: 1px solid rgba(255,255,255,0.2);
            font-weight: 600;
            cursor: help;
        }
        
        /* Colores para roles */
        .badge-rol[data-rol-codigo="Admin"],
        .badge-rol[data-rol*="Administrador"] {
            background-color: #dc3545 !important;
        }
        
        .badge-rol[data-rol-codigo="Super"],
        .badge-rol[data-rol*="Supervisor"] {
            background-color: #fd7e14 !important;
        }
        
        .badge-rol[data-rol-codigo="Editor"],
        .badge-rol[data-rol*="Editor"],
        .badge-rol[data-rol*="Facturador"] {
            background-color: #20c997 !important;
        }
        
        .badge-rol[data-rol-codigo="Visor"],
        .badge-rol[data-rol*="Visualizador"] {
            background-color: #0d6efd !important;
        }
        
        .badge-rol[data-rol="Sin rol"] {
            background-color: #6c757d !important;
        }
        
        /* Estado BD animado */
        #dbStatus.text-success {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .win-footer {
                height: 64px; /* Un poco más alto para móvil */
            }
            
            .win-footer .col-12 .d-flex {
                flex-wrap: wrap;
                justify-content: center !important;
                gap: 8px;
            }
            
            .win-footer .me-3 {
                margin-right: 0.5rem !important;
            }
        }
        
        @media (max-width: 768px) {
            .win-footer {
                left: 0 !important;
                height: 80px;
            }
            
            .win-footer .col-12 {
                padding: 4px 0 !important;
            }
            
            .win-footer .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 4px;
            }
            
            .win-footer .d-flex > div {
                width: 100%;
                justify-content: flex-start !important;
            }
        }
    </style>
    
    <script>
        // Variables globales
        let sessionSeconds = localStorage.getItem('sessionSeconds') ? parseInt(localStorage.getItem('sessionSeconds')) : 0;
        let fechaCierreTimestamp = <?php echo $fecha_cierre_timestamp ?: 'null'; ?>;
        
        // Actualizar hora en formato 12h AM/PM
        function updateCurrentTime() {
            const now = new Date();
            const timeElement = document.getElementById('currentTime');
            const dateElement = document.getElementById('currentDate');
            
            if (timeElement) {
                let hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12;
                timeElement.textContent = hours.toString().padStart(2, '0') + ':' + minutes + ':' + seconds + ' ' + ampm;
            }
            
            if (dateElement) {
                dateElement.textContent = now.getDate().toString().padStart(2, '0') + '/' + 
                                        (now.getMonth() + 1).toString().padStart(2, '0') + '/' + 
                                        now.getFullYear();
            }
            
        }
        
        // Timer de sesión
        function initSessionTimer() {
            const timerElement = document.getElementById('sessionTimer');
            if (!timerElement) return;
            
            updateTimerDisplay();
            const timerInterval = setInterval(() => {
                sessionSeconds++;
                updateTimerDisplay();
                if (sessionSeconds % 30 === 0) saveSessionTime();
            }, 1000);
            
            window.addEventListener('beforeunload', saveSessionTime);
        }
        
        function updateTimerDisplay() {
            const timerElement = document.getElementById('sessionTimer');
            if (!timerElement) return;
            
            const hours = Math.floor(sessionSeconds / 3600);
            const minutes = Math.floor((sessionSeconds % 3600) / 60);
            const seconds = sessionSeconds % 60;
            timerElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        function saveSessionTime() {
            localStorage.setItem('sessionSeconds', sessionSeconds);
            localStorage.setItem('lastSessionSave', new Date().toISOString());
        }
        
        // Actualizar memoria
        function updateMemoryUsage() {
            const memoryElement = document.getElementById('memoryUsage');
            if (memoryElement) {
                const current = parseFloat(memoryElement.textContent);
                const variation = (Math.random() * 0.08) - 0.04;
                const newValue = Math.max(1, current + variation);
                memoryElement.textContent = newValue.toFixed(2) + ' MB';
            }
        }
        
        // Tema del footer
        function applyThemeToFooter() {
            const footer = document.querySelector('.win-footer');
            if (footer) {
                const theme = document.documentElement.getAttribute('data-theme');
                const accent = document.documentElement.getAttribute('data-accent') || 
                               getComputedStyle(document.documentElement).getPropertyValue('--win-accent');
                footer.setAttribute('data-theme', theme);
                footer.style.setProperty('--win-accent', accent);
            }
        }
        
        // Inicializar tooltips
        function initTooltips() {
            const tooltips = document.querySelectorAll('[title]');
            tooltips.forEach(el => {
                new bootstrap.Tooltip(el, { trigger: 'hover', placement: 'top' });
            });
        }
        
        // DOM Ready
        document.addEventListener('DOMContentLoaded', function() {
            initSessionTimer();
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
            setInterval(updateMemoryUsage, 30000);
            applyThemeToFooter();
            setTimeout(initTooltips, 300);
            
            // Observar cambios de tema
            new MutationObserver(() => applyThemeToFooter()).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme', 'data-accent']
            });
        });
    </script>