<?php
// eliminar_servicio.php - Windows 11 Dark Mode
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si se recibió un ID de servicio
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de servicio no válido";
    header('Location: servicios.php');
    exit();
}

$servicio_id = $_GET['id'];
$confirmado = isset($_GET['confirmado']) && $_GET['confirmado'] == 'true';
$forzar = isset($_GET['forzar']) && $_GET['forzar'] == 'true';

try {
    $db = Database::getConnection();
    
    // Obtener información del servicio
    $sql_servicio = "SELECT * FROM clasif_serv WHERE id = :id";
    $stmt_servicio = $db->prepare($sql_servicio);
    $stmt_servicio->execute(['id' => $servicio_id]);
    $servicio = $stmt_servicio->fetch(PDO::FETCH_ASSOC);
    
    if (!$servicio) {
        $_SESSION['error'] = "Servicio no encontrado";
        header('Location: servicios.php');
        exit();
    }
    
    // Verificar si el servicio está siendo utilizado en facturas
    $sql_check = "SELECT COUNT(*) as total FROM tbl_fact_detalle WHERE servicio_id = :servicio_id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute(['servicio_id' => $servicio_id]);
    $uso = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    // Si el servicio está en uso y no estamos en modo forzado, mostrar advertencia
    if ($uso['total'] > 0 && !$forzar && !$confirmado) {
        // Mostrar página de advertencia especial
        mostrarAdvertenciaUso($servicio, $servicio_id, $uso['total']);
        exit();
    }
    
    // Si no está confirmado, mostrar confirmación normal
    if (!$confirmado) {
        // Mostrar página de confirmación normal (solo si no está en uso o es forzado)
        mostrarConfirmacion($servicio, $servicio_id, $uso['total'], $forzar);
        exit();
    }
    
    // Si llegamos aquí, significa que está confirmado
    // ================================================
    // NUEVA LÓGICA: Si hay claves foráneas, solo desactivar
    // ================================================
    
    // Re-verificar uso por seguridad
    $stmt_check->execute(['servicio_id' => $servicio_id]);
    $uso_confirmado = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($uso_confirmado['total'] > 0) {
        // Hay claves foráneas - solo marcar como inactivo
        $sql_update = "UPDATE clasif_serv SET activo = 0 WHERE id = :id";
        $stmt_update = $db->prepare($sql_update);
        $resultado = $stmt_update->execute(['id' => $servicio_id]);
        
        if ($resultado) {
            // Registrar en el histórico
            $sql_historico = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                              VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address)";
            $stmt_historico = $db->prepare($sql_historico);
            $stmt_historico->execute([
                'operacion' => 'DESACTIVAR_SERVICIO',
                'descripcion' => "Servicio {$servicio['codigo']} - {$servicio['descripcion']} desactivado (estaba en uso en {$uso_confirmado['total']} factura(s))",
                'usuario_id' => $_SESSION['usuario_id'],
                'usuario_nombre' => $_SESSION['usuario_nombre'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['warning'] = "Servicio '{$servicio['codigo']}' desactivado (no eliminado). Está siendo utilizado en {$uso_confirmado['total']} factura(s).";
            
        } else {
            $_SESSION['error'] = "Error al desactivar el servicio";
        }
        
    } else {
        // No hay claves foráneas - eliminar físicamente
        $sql_delete = "DELETE FROM clasif_serv WHERE id = :id";
        $stmt_delete = $db->prepare($sql_delete);
        $resultado = $stmt_delete->execute(['id' => $servicio_id]);
        
        if ($resultado) {
            // Registrar en el histórico
            $sql_historico = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                              VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address)";
            $stmt_historico = $db->prepare($sql_historico);
            $stmt_historico->execute([
                'operacion' => 'ELIMINAR_SERVICIO',
                'descripcion' => "Servicio {$servicio['codigo']} - {$servicio['descripcion']} eliminado completamente (no tenía uso en facturas)",
                'usuario_id' => $_SESSION['usuario_id'],
                'usuario_nombre' => $_SESSION['usuario_nombre'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['success'] = "Servicio '{$servicio['codigo']}' eliminado completamente de la base de datos";
            
        } else {
            $_SESSION['error'] = "Error al eliminar el servicio";
        }
    }
    
    // Redirigir a servicios.php
    header('Location: servicios.php');
    exit();
    
} catch (Exception $e) {
    error_log("Error al eliminar servicio: " . $e->getMessage());
    $_SESSION['error'] = "Error al procesar la solicitud: " . $e->getMessage();
    header('Location: servicios.php');
    exit();
}

// Función para mostrar página de advertencia cuando el servicio está en uso
function mostrarAdvertenciaUso($servicio, $servicio_id, $total_facturas) {
    // Obtener configuración del tema Windows 11
    $tema_windows = $_SESSION['tema_windows'] ?? 'dark';
    $color_accent = $_SESSION['color_accent'] ?? '#0078d4';
    
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
    
    try {
        $db = Database::getConnection();
        
        // Obtener usuario actual
        $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                        FROM clasif_usuarios u
                        LEFT JOIN clasif_rol r ON u.rol_id = r.id
                        WHERE u.id = :id";
        $stmt_usuario = $db->prepare($sql_usuario);
        $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
        $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        
        // Obtener información de las facturas donde se usa el servicio
        $sql_facturas = "SELECT f.id, f.fecha, f.numero_factura, f.cliente_nombre, 
                                c.nombre as cliente_nombre_completo,
                                fd.cantidad, fd.precio_unitario
                         FROM tbl_fact_detalle fd
                         JOIN tbl_fact f ON fd.factura_id = f.id
                         LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                         WHERE fd.servicio_id = :servicio_id
                         GROUP BY f.id
                         ORDER BY f.fecha DESC
                         LIMIT 10";
        $stmt_facturas = $db->prepare($sql_facturas);
        $stmt_facturas->execute(['servicio_id' => $servicio_id]);
        $facturas = $stmt_facturas->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al cargar datos: " . $e->getMessage());
        $facturas = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Advertencia - Servicio en Uso - PDL Visiones</title>
        <link rel="icon" type="image/x-icon" href="assets/logov.png">
        
        <!-- Bootstrap 5 -->
        <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        
        <!-- SweetAlert2 -->
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        
        <!-- Windows 11 Styles -->
        <style>
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
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .warning-card {
                background: var(--win-bg-secondary);
                border: 1px solid var(--win-border-color);
                border-radius: var(--win-radius);
                padding: 2rem;
                max-width: 800px;
                width: 100%;
                box-shadow: var(--win-shadow);
                animation: fadeIn 0.5s ease-in-out;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .danger-icon {
                color: #dc3545;
                font-size: 4rem;
                margin-bottom: 1.5rem;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.7; transform: scale(1.1); }
                100% { opacity: 1; transform: scale(1); }
            }

            .service-info {
                background: var(--win-bg-tertiary);
                border: 1px solid var(--win-border-color);
                border-radius: var(--win-radius-sm);
                padding: 1.5rem;
                margin: 1.5rem 0;
            }

            .info-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.1rem;
                padding-bottom: 0.1rem;
                border-bottom: 1px solid var(--win-border-color);
            }

            .info-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }

            .info-label {
                color: var(--win-text-secondary);
                font-weight: 500;
            }

            .info-value {
                color: var(--win-text-primary);
                font-weight: 600;
            }

            .facturas-table {
                background: var(--win-bg-tertiary);
                border: 1px solid var(--win-border-color);
                border-radius: var(--win-radius-sm);
                padding: 1rem;
                max-height: 250px;
                overflow-y: auto;
                margin: 1rem 0;
            }

            .factura-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                border-bottom: 1px solid var(--win-border-color);
                transition: var(--win-transition);
            }

            .factura-item:hover {
                background: rgba(var(--win-accent-rgb, 0, 120, 212), 0.05);
            }

            .factura-item:last-child {
                border-bottom: none;
            }

            .factura-info {
                flex: 1;
            }

            .factura-actions {
                display: flex;
                gap: 0.5rem;
            }

            .btn {
                border-radius: var(--win-radius-sm);
                transition: var(--win-transition);
                padding: 0.5rem 1.5rem;
                font-weight: 500;
            }

            .btn-primary {
                background: var(--win-accent);
                border-color: var(--win-accent);
            }

            .btn-primary:hover {
                background: color-mix(in srgb, var(--win-accent) 90%, black);
                border-color: color-mix(in srgb, var(--win-accent) 90%, black);
            }

            .btn-outline-secondary {
                color: var(--win-text-secondary);
                border-color: var(--win-border-color);
            }

            .btn-outline-secondary:hover {
                background: var(--win-bg-tertiary);
                color: var(--win-text-primary);
            }

            .btn-sm {
                padding: 0.25rem 0.75rem;
                font-size: 0.875rem;
            }

            .alert {
                border-radius: var(--win-radius-sm);
                border: 1px solid;
                border-left-width: 4px;
            }

            .alert-danger {
                background-color: rgba(220, 53, 69, 0.1);
                border-color: rgba(220, 53, 69, 0.3);
                border-left-color: #dc3545;
                color: #dc3545;
            }

            .alert-warning {
                background-color: rgba(255, 193, 7, 0.1);
                border-color: rgba(255, 193, 7, 0.3);
                border-left-color: #ffc107;
                color: #ffc107;
            }

            .alert-info {
                background-color: rgba(13, 110, 253, 0.1);
                border-color: rgba(13, 110, 253, 0.3);
                border-left-color: #0dcaf0;
                color: #0dcaf0;
            }

            .alert-success {
                background-color: rgba(25, 135, 84, 0.1);
                border-color: rgba(25, 135, 84, 0.3);
                border-left-color: #198754;
                color: #198754;
            }

            .badge {
                border-radius: var(--win-radius-sm);
                font-weight: 600;
                padding: 0.35em 0.65em;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .warning-card {
                    padding: 1.5rem;
                }
                
                .danger-icon {
                    font-size: 3rem;
                }
                
                .btn {
                    width: 100%;
                    margin-bottom: 0.5rem;
                }
                
                .factura-item {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                }
                
                .factura-actions {
                    width: 100%;
                    justify-content: flex-end;
                }
            }
        </style>
    </head>
    <body>
        <div class="warning-card">
            <div class="text-center mb-4">
                <div class="danger-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <h2 class="mb-2" style="color: #dc3545;">¡Acción Bloqueada!</h2>
                <p class="text-muted">El servicio está siendo utilizado en facturas</p>
            </div>
            
            <div class="alert alert-danger mb-4">
                <div class="d-flex">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-2">Eliminación Física Bloqueada</h5>
                        <p class="mb-0">
                            No es posible eliminar físicamente el servicio <strong>'<?php echo htmlspecialchars($servicio['codigo']); ?>'</strong> 
                            porque está siendo utilizado en <strong><?php echo $total_facturas; ?> factura(s)</strong>.
                        </p>
                    </div>
                </div>
            </div>
<div class="service-info">
    <h5 class="mb-3" style="color: var(--win-text-primary);">
        <i class="fas fa-info-circle me-2"></i>Información del Servicio
    </h5>
    <div class="service-grid">
        <span class="info-label">Código:</span>
        <span class="info-value"><?php echo htmlspecialchars($servicio['codigo']); ?></span>
        <span class="info-label">Descripción:</span>
        <span class="info-value"><?php echo htmlspecialchars($servicio['descripcion']); ?></span>
        
        <span class="info-label">Costo:</span>
        <span class="info-value">$<?php echo isset($servicio['costo']) ? number_format($servicio['costo'], 2) : '0.00'; ?></span>
        <span class="info-label">Estado:</span>
        <span class="info-value">
            <span class="badge <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'bg-success' : 'bg-danger'; ?>">
                <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'Activo' : 'Inactivo'; ?>
            </span>
        </span>
    </div>
</div>
            
            <?php if (!empty($facturas)): ?>
                <div class="mt-4">
                    <h6 class="mb-3" style="color: var(--win-text-primary);">
                        <i class="fas fa-file-invoice me-2"></i>Últimas facturas que usan este servicio
                    </h6>
                    <div class="facturas-table">
                        <?php foreach ($facturas as $factura): ?>
                            <div class="factura-item">
                                <div class="factura-info">
                                    <strong>Factura #<?php echo htmlspecialchars($factura['numero_factura']); ?></strong>
                                    <div class="text-muted small">
                                        <i class="far fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($factura['fecha'])); ?>
                                        <i class="fas fa-user ms-2 me-1"></i><?php echo htmlspecialchars($factura['cliente_nombre_completo'] ?? $factura['cliente_nombre']); ?>
                                    </div>
                                </div>
                                <div class="factura-actions">
                                    <a href="ver_factura.php?id=<?php echo $factura['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary"
                                       title="Ver factura"
                                       target="_blank">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($total_facturas > 10): ?>
                            <div class="text-center mt-3">
                                <span class="badge bg-secondary">
                                    <i class="fas fa-ellipsis-h me-1"></i>
                                    Y <?php echo ($total_facturas - 10); ?> factura(s) más...
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-warning mt-4">
                <div>
                    <i class="fas fa-lightbulb me-2"></i><strong>Acción Automática que se Realizará:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        <li><strong>El servicio será DESACTIVADO automáticamente</strong> (cambiará estado a INACTIVO)</li>
                        <li><strong>NO se eliminará físicamente</strong> de la base de datos</li>
                        <li>No estará disponible para nuevas facturas</li>
                        <li>Las facturas existentes mantendrán sus datos históricos</li>
                        <li>Si necesita eliminar completamente, primero debe eliminar o modificar las facturas relacionadas</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-4 pt-3 border-top border-color">
                <div class="row g-2">
                    <div class="col-md-4">
                        <a href="servicios.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-arrow-left me-1"></i>Volver a Servicios
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="editar_servicio.php?id=<?php echo $servicio_id; ?>" 
                           class="btn btn-primary w-100">
                            <i class="fas fa-edit me-1"></i>Editar Servicio
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="eliminar_servicio.php?id=<?php echo $servicio_id; ?>&confirmado=true" 
                           class="btn btn-outline-danger w-100" id="btnDesactivar">
                            <i class="fas fa-toggle-off me-1"></i>Desactivar Servicio
                        </a>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        Usuario: <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                        <i class="fas fa-clock ms-3 me-1"></i>
                        <?php echo date('d/m/Y H:i:s'); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Scripts -->
        <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
        <script src="js/sweetalert211.js"></script>
        
        <script>
            // Mostrar SweetAlert al cargar la página
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '¡Acción Bloqueada!',
                    html: `
                        <div class="text-start">
                            <p><strong>No se puede eliminar físicamente el servicio "<?php echo htmlspecialchars($servicio['codigo']); ?>"</strong></p>
                            <div class="alert alert-danger p-3 mb-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Razón:</strong> Está siendo usado en <strong><?php echo $total_facturas; ?> factura(s)</strong>
                            </div>
                            <p><strong>Acción que se realizará:</strong> El servicio será desactivado (cambiará a estado INACTIVO).</p>
                        </div>
                    `,
                    icon: 'warning',
                    confirmButtonColor: '#ffc107',
                    confirmButtonText: '<i class="fas fa-toggle-off me-2"></i>Entendido (Desactivar)',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-arrow-left me-2"></i>Cancelar',
                    reverseButtons: true,
                    allowOutsideClick: false,
                    backdrop: 'rgba(0,0,0,0.8)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirigir para desactivar
                        window.location.href = 'eliminar_servicio.php?id=<?php echo $servicio_id; ?>&confirmado=true';
                    }
                });
            });
            
            // Botón para desactivar (con confirmación)
            document.getElementById('btnDesactivar').addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                
                Swal.fire({
                    title: '¿Desactivar Servicio?',
                    html: `
                        <div class="text-start">
                            <p>El servicio <strong>"<?php echo htmlspecialchars($servicio['codigo']); ?>"</strong> será desactivado.</p>
                            <div class="alert alert-warning p-3 mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Consecuencias:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Estado cambiará a INACTIVO</li>
                                    <li>No estará disponible para nuevas facturas</li>
                                    <li>No se eliminará de la base de datos</li>
                                    <li>Las <?php echo $total_facturas; ?> factura(s) existentes no se verán afectadas</li>
                                </ul>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-toggle-off me-2"></i>Sí, desactivar',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
            
            // Detectar tecla Escape para volver
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'servicios.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
}

// Función para mostrar la página de confirmación normal
function mostrarConfirmacion($servicio, $servicio_id, $total_facturas = 0, $forzar = false) {
    // Obtener configuración del tema Windows 11
    $tema_windows = $_SESSION['tema_windows'] ?? 'dark';
    $color_accent = $_SESSION['color_accent'] ?? '#0078d4';
    
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
    
    try {
        $db = Database::getConnection();
        
        // Obtener usuario actual
        $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                        FROM clasif_usuarios u
                        LEFT JOIN clasif_rol r ON u.rol_id = r.id
                        WHERE u.id = :id";
        $stmt_usuario = $db->prepare($sql_usuario);
        $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
        $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        
        // Verificar si el servicio tiene facturas asociadas
        $sql_check_facturas = "SELECT COUNT(*) as total FROM tbl_fact_detalle WHERE servicio_id = :servicio_id";
        $stmt_check_facturas = $db->prepare($sql_check_facturas);
        $stmt_check_facturas->execute(['servicio_id' => $servicio_id]);
        $facturas_asociadas = $stmt_check_facturas->fetch(PDO::FETCH_ASSOC);
        
        // Determinar qué acción se realizará
        $tiene_claves_foraneas = ($facturas_asociadas['total'] > 0);
        
    } catch (Exception $e) {
        error_log("Error al cargar datos: " . $e->getMessage());
        $facturas_asociadas = ['total' => 0];
        $tiene_claves_foraneas = false;
    }
    ?>
    <!DOCTYPE html>
    <html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmar Eliminación - PDL Visiones</title>
        <link rel="icon" type="image/x-icon" href="assets/logov.png">
        
        <!-- Bootstrap 5 -->
        <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        
        <!-- SweetAlert2 -->
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        
        <!-- Windows 11 Styles -->
        <style>
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
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .confirmation-card {
                background: var(--win-bg-secondary);
                border: 1px solid var(--win-border-color);
                border-radius: var(--win-radius);
                padding: 2rem;
                max-width: 600px;
                width: 100%;
                box-shadow: var(--win-shadow);
                animation: fadeIn 0.3s ease-in-out;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .warning-icon {
                color: #ffc107;
                font-size: 4rem;
                margin-bottom: 1.5rem;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
 .service-info {
        background: var(--win-bg-tertiary);
        border: 1px solid var(--win-border-color);
        border-radius: var(--win-radius-sm);
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .service-grid {
        display: grid;
        grid-template-columns: repeat(2, auto 1fr);
        gap: 0.75rem 0.5rem;
        align-items: center;
    }

    .info-label {
        color: var(--win-text-secondary);
        font-weight: 500;
        white-space: nowrap;
    }

    .info-value {
        color: var(--win-text-primary);
        font-weight: 600;
        word-break: break-word;
    }

    .badge {
        border-radius: var(--win-radius-sm);
        font-weight: 600;
        padding: 0.35em 0.65em;
        font-size: 0.875em;
        display: inline-block;
    }

    /* Responsive para móviles */
    @media (max-width: 768px) {
        .service-grid {
            grid-template-columns: auto 1fr;
            gap: 0.5rem;
        }
        
        /* Ocultar algunas etiquetas en móviles si es necesario */
        .service-grid .info-label:nth-child(3),
        .service-grid .info-value:nth-child(4) {
            display: none;
        }
    }
            .btn {
                border-radius: var(--win-radius-sm);
                transition: var(--win-transition);
                padding: 0.5rem 1.5rem;
            }

            .btn-danger {
                background: #dc3545;
                border-color: #dc3545;
            }

            .btn-danger:hover {
                background: #c82333;
                border-color: #bd2130;
            }

            .btn-warning {
                background: #ffc107;
                border-color: #ffc107;
                color: #000;
            }

            .btn-warning:hover {
                background: #e0a800;
                border-color: #d39e00;
                color: #000;
            }

            .btn-outline-secondary {
                color: var(--win-text-secondary);
                border-color: var(--win-border-color);
            }

            .btn-outline-secondary:hover {
                background: var(--win-bg-tertiary);
                color: var(--win-text-primary);
            }

            .alert {
                border-radius: var(--win-radius-sm);
                border: 1px solid;
            }

            .alert-warning {
                background-color: rgba(255, 193, 7, 0.1);
                border-color: rgba(255, 193, 7, 0.3);
                color: #ffc107;
            }

            .alert-info {
                background-color: rgba(13, 110, 253, 0.1);
                border-color: rgba(13, 110, 253, 0.3);
                color: #0dcaf0;
            }

            .alert-success {
                background-color: rgba(25, 135, 84, 0.1);
                border-color: rgba(25, 135, 84, 0.3);
                color: #198754;
            }

            /* Responsive */
            @media (max-width: 576px) {
                .confirmation-card {
                    padding: 1.5rem;
                }
                
                .warning-icon {
                    font-size: 3rem;
                }
                
                .btn {
                    width: 100%;
                    margin-bottom: 0.5rem;
                }
                
                .button-group {
                    display: flex;
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="confirmation-card">
            <div class="text-center mb-4">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="mb-2" style="color: var(--win-text-primary);">
                    <?php echo $tiene_claves_foraneas ? 'Confirmar Desactivación' : 'Confirmar Eliminación'; ?>
                </h2>
                <p class="text-muted">Esta acción no se puede deshacer</p>
            </div>
            
            <?php if ($tiene_claves_foraneas): ?>
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Servicio en uso:</strong> Está siendo utilizado en <?php echo $facturas_asociadas['total']; ?> factura(s). 
                    <strong>Solo se desactivará (NO se eliminará físicamente).</strong>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Servicio sin uso:</strong> No está siendo utilizado en ninguna factura. 
                    <strong>Se eliminará completamente de la base de datos.</strong>
                </div>
            <?php endif; ?>
            
<div class="service-info">
    <h5 class="mb-3" style="color: var(--win-text-primary);">
        <i class="fas fa-info-circle me-2"></i>Información del Servicio
    </h5>
    <div class="service-grid">
        <!-- Fila 1 -->
        <span class="info-label">Código:</span>
        <span class="info-value"><?php echo htmlspecialchars($servicio['codigo']); ?></span>
        
        <span class="info-label">Descripción:</span>
        <span class="info-value"><?php echo htmlspecialchars($servicio['descripcion']); ?></span>
        
        <!-- Fila 2 -->
        <span class="info-label">Costo:</span>
        <span class="info-value">$<?php echo isset($servicio['costo']) ? number_format($servicio['costo'], 2) : '0.00'; ?></span>
        
        <span class="info-label">Estado:</span>
        <span class="info-value">
            <span class="badge <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'bg-success' : 'bg-danger'; ?>">
                <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'Activo' : 'Inactivo'; ?>
            </span>
        </span>
    </div>
</div>
            
			<div class="mt-4">
                <h6 class="mb-3" style="color: var(--win-text-primary);">
                    <?php echo $tiene_claves_foraneas ? 'Consecuencias de la desactivación:' : 'Consecuencias de la eliminación:'; ?>
                </h6>
                <ul class="text-warning small">
                    <?php if ($tiene_claves_foraneas): ?>
                        <li><strong>El servicio será DESACTIVADO</strong> (cambiará estado a INACTIVO)</li>
                        <li><strong>NO se eliminará físicamente</strong> de la base de datos</li>
                        <li>No estará disponible para nuevas facturas</li>
                        <li>Las <?php echo $facturas_asociadas['total']; ?> factura(s) existentes mantendrán el servicio en su histórico</li>
                        <li>Puede reactivarlo posteriormente si es necesario</li>
                    <?php else: ?>
                        <li><strong>El servicio será ELIMINADO FÍSICAMENTE</strong> de la base de datos</li>
                        <li><strong>Esta acción no se puede deshacer</strong></li>
                        <li>Toda la información del servicio será borrada permanentemente</li>
                        <li>No tendrá impacto en facturas (porque no está siendo utilizado)</li>
                    <?php endif; ?>
                    <li>Se registrará esta acción en el histórico del sistema</li>
                </ul>
            </div>
            
            <div class="mt-4 pt-3 border-top border-color">
                <div class="row g-2">
                    <div class="col-md-6">
                        <a href="servicios.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </a>
                    </div>
                    <div class="col-md-6">
                        <?php if ($tiene_claves_foraneas): ?>
                            <a href="eliminar_servicio.php?id=<?php echo $servicio_id; ?>&confirmado=true" 
                               class="btn btn-warning w-100" id="btnConfirmar">
                                <i class="fas fa-toggle-off me-1"></i>Desactivar Servicio
                            </a>
                        <?php else: ?>
                            <a href="eliminar_servicio.php?id=<?php echo $servicio_id; ?>&confirmado=true" 
                               class="btn btn-danger w-100" id="btnConfirmar">
                                <i class="fas fa-trash me-1"></i>Eliminar Permanentemente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        Acción realizada por: <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Scripts -->
        <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
        <script src="js/sweetalert211.js"></script>
        
        <script>
            // Confirmar acción
            document.getElementById('btnConfirmar').addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                
                <?php if ($tiene_claves_foraneas): ?>
                    const title = '¿Desactivar Servicio?';
                    const text = "El servicio será desactivado (cambiará a estado INACTIVO).";
                    const confirmText = 'Sí, desactivar';
                    const icon = 'warning';
                <?php else: ?>
                    const title = '¿Eliminar Permanentemente?';
                    const text = "El servicio será eliminado completamente de la base de datos.";
                    const confirmText = 'Sí, eliminar permanentemente';
                    const icon = 'error';
                <?php endif; ?>
                
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonColor: <?php echo $tiene_claves_foraneas ? "'#ffc107'" : "'#dc3545'"; ?>,
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    backdrop: 'rgba(0,0,0,0.7)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar carga
                        Swal.fire({
                            title: <?php echo $tiene_claves_foraneas ? "'Desactivando servicio...'" : "'Eliminando servicio...'"; ?>,
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Redirigir
                        window.location.href = url;
                    }
                });
            });
            
            // Detectar tecla Escape para cancelar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'servicios.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
}
?>