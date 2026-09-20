<?php
// eliminar_categoria.php - Windows 11 Dark Mode
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si se recibió un ID de categoría
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de categoría no válido";
    header('Location: categorias.php');
    exit();
}

$categoria_id = $_GET['id'];

try {
    $db = Database::getConnection();
    
    // Obtener información de la categoría antes de eliminar (para el histórico)
    $sql_categoria = "SELECT * FROM clasif_cat_de_serv WHERE id = :id";
    $stmt_categoria = $db->prepare($sql_categoria);
    $stmt_categoria->execute(['id' => $categoria_id]);
    $categoria = $stmt_categoria->fetch(PDO::FETCH_ASSOC);
    
    if (!$categoria) {
        $_SESSION['error'] = "Categoría no encontrada";
        header('Location: categorias.php');
        exit();
    }
    
// Verificar si la categoría tiene servicios asociados
$sql_servicios = "SELECT COUNT(*) as total, GROUP_CONCAT(id) as ids FROM clasif_serv WHERE categoria_id = :categoria_id";
$stmt_servicios = $db->prepare($sql_servicios);
$stmt_servicios->execute(['categoria_id' => $categoria_id]);
$servicios = $stmt_servicios->fetch(PDO::FETCH_ASSOC);

if ($servicios['total'] > 0) {
    // Verificar si esos servicios tienen facturas asociadas
    $sql_facturas = "SELECT COUNT(DISTINCT fd.factura_id) as total 
                     FROM tbl_fact_detalle fd
                     WHERE fd.servicio_id IN ({$servicios['ids']})
                     AND EXISTS (SELECT 1 FROM tbl_fact f WHERE f.id = fd.factura_id)";
    $stmt_facturas = $db->prepare($sql_facturas);
    $stmt_facturas->execute();
    $facturas = $stmt_facturas->fetch(PDO::FETCH_ASSOC);
    
    if ($facturas['total'] > 0) {
        // Tiene facturas - No se puede eliminar
        $_SESSION['error'] = "No se puede eliminar la categoría '{$categoria['codigo']}' porque tiene {$servicios['total']} servicio(s) que están siendo utilizados en {$facturas['total']} factura(s).";
        header('Location: categorias.php');
        exit();
    } else {
        // Tiene servicios pero sin facturas - Mostrar advertencia
        $_SESSION['warning'] = "La categoría '{$categoria['codigo']}' tiene {$servicios['total']} servicio(s) asociados, pero no tienen facturas. Puede eliminarla, pero los servicios quedarán sin categoría.";
        // Continuar con la eliminación
    }
}
    
    // Proceder con la eliminación
    $sql_delete = "DELETE FROM clasif_cat_de_serv WHERE id = :id";
    $stmt_delete = $db->prepare($sql_delete);
    $resultado = $stmt_delete->execute(['id' => $categoria_id]);
    
    if ($resultado) {
        // Registrar en el histórico
        $sql_historico = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                          VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address)";
        $stmt_historico = $db->prepare($sql_historico);
        $stmt_historico->execute([
            'operacion' => 'ELIMINAR_CATEGORIA',
            'descripcion' => "Categoría {$categoria['codigo']} - {$categoria['descripcion']} eliminada",
            'usuario_id' => $_SESSION['usuario_id'],
            'usuario_nombre' => $_SESSION['usuario_nombre'],
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        $_SESSION['success'] = "Categoría '{$categoria['codigo']}' eliminada correctamente";
        header('Location: categorias.php');
        exit();
    } else {
        $_SESSION['error'] = "Error al eliminar la categoría";
        header('Location: categorias.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error al eliminar categoría: " . $e->getMessage());
    $_SESSION['error'] = "Error al procesar la solicitud";
    header('Location: categorias.php');
    exit();
}

// Función para mostrar la página de confirmación
function mostrarConfirmacion($categoria, $categoria_id) {
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
    
    // Obtener estadísticas del sistema
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
        
        // Verificar si la categoría tiene servicios asociados
        $sql_check_servicios = "SELECT COUNT(*) as total FROM clasif_serv WHERE categoria_id = :categoria_id";
        $stmt_check_servicios = $db->prepare($sql_check_servicios);
        $stmt_check_servicios->execute(['categoria_id' => $categoria_id]);
        $servicios_asociados = $stmt_check_servicios->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al cargar datos: " . $e->getMessage());
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

            .category-info {
                background: var(--win-bg-tertiary);
                border: 1px solid var(--win-border-color);
                border-radius: var(--win-radius-sm);
                padding: 1.5rem;
                margin: 1.5rem 0;
            }

            .info-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                padding-bottom: 0.5rem;
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
                <h2 class="mb-2" style="color: var(--win-text-primary);">Confirmar Eliminación</h2>
                <p class="text-muted">Esta acción no se puede deshacer</p>
            </div>
            
            <?php if ($servicios_asociados['total'] > 0): ?>
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Advertencia:</strong> Esta categoría tiene <?php echo $servicios_asociados['total']; ?> servicio(s) asociado(s). 
                    Debe eliminar o reasignar estos servicios antes de eliminar la categoría.
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    Esta categoría no tiene servicios asociados. Puede proceder con la eliminación.
                </div>
            <?php endif; ?>
            
            <div class="category-info">
                <h5 class="mb-3" style="color: var(--win-text-primary);">Información de la Categoría</h5>
                <div class="info-item">
                    <span class="info-label">Código:</span>
                    <span class="info-value"><?php echo htmlspecialchars($categoria['codigo']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Descripción:</span>
                    <span class="info-value"><?php echo htmlspecialchars($categoria['descripcion']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado:</span>
                    <span class="info-value">
                        <span class="badge <?php echo $categoria['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $categoria['activo'] ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">ID:</span>
                    <span class="info-value">#<?php echo $categoria['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Servicios Asociados:</span>
                    <span class="info-value">
                        <span class="badge <?php echo $servicios_asociados['total'] > 0 ? 'bg-danger' : 'bg-success'; ?>">
                            <?php echo $servicios_asociados['total']; ?> servicio(s)
                        </span>
                    </span>
                </div>
            </div>
            
            <div class="mt-4">
                <h6 class="mb-3" style="color: var(--win-text-primary);">Consecuencias de la eliminación:</h6>
                <ul class="text-muted small">
                    <li>La categoría será eliminada permanentemente de la base de datos</li>
                    <li>Esta acción no se puede deshacer</li>
                    <?php if ($servicios_asociados['total'] > 0): ?>
                    <li><strong class="text-danger">ADVERTENCIA:</strong> Al tener servicios asociados, estos quedarán sin categoría asignada</li>
                    <?php endif; ?>
                    <li>Se registrará esta acción en el histórico del sistema</li>
                </ul>
            </div>
            
            <div class="mt-4 pt-3 border-top border-color">
                <?php if ($servicios_asociados['total'] > 0): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="fas fa-ban me-2"></i>
                        <strong>No se puede eliminar:</strong> Esta categoría tiene servicios asociados. 
                        Por favor, elimine o reasigne los servicios antes de continuar.
                    </div>
                    <div class="text-center">
                        <a href="categorias.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-arrow-left me-1"></i>Volver a Categorías
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <a href="categorias.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="eliminar_categoria.php?id=<?php echo $categoria_id; ?>&confirmado=true" 
                               class="btn btn-danger w-100" id="btnEliminar">
                                <i class="fas fa-trash me-1"></i>Eliminar Permanentemente
                            </a>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-user me-1"></i>
                            Acción realizada por: <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scripts -->
        <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
        <script src="js/sweetalert211.js"></script>
        
        <script>
            // Confirmar eliminación
            document.getElementById('btnEliminar')?.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                
                Swal.fire({
                    title: '¿Está completamente seguro?',
                    text: "Esta es la última confirmación. La categoría será eliminada permanentemente.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar permanentemente',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar carga
                        Swal.fire({
                            title: 'Eliminando categoría...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Redirigir a la eliminación
                        window.location.href = url;
                    }
                });
            });
            
            // Detectar tecla Escape para cancelar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'categorias.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
}
?>