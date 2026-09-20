<?php
// Función para verificar todas las tablas necesarias
function verificarTablasExistentes() {
    try {
        $db = Database::getConnection();
        
        // Lista de tablas requeridas para el sistema
        $tablasRequeridas = [
            'configuracion_sistema',
            'historico_operaciones',
            'clasif_usuarios',
            'clasif_serv',
            'tbl_fact',
            'tbl_fact_detalle',
            'clasif_rol',
			'historico_cierres',
            'tbl_planes',
            'clasif_clientes',
            'clasif_cat_de_serv',
			'tipos_pago'
        ];
        
        $tablasFaltantes = [];
        
        // Método robusto usando información del schema
        try {
            // Obtener el nombre de la base de datos actual
            $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
            
            foreach ($tablasRequeridas as $tabla) {
                try {
                    // Consulta usando information_schema (más confiable)
                    $sql = "SELECT COUNT(*) as existe 
                            FROM information_schema.tables 
                            WHERE table_schema = ? 
                            AND table_name = ?";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$dbName, $tabla]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$result || $result['existe'] == 0) {
                        $tablasFaltantes[] = $tabla;
                    }
                } catch (Exception $e) {
                    // Método alternativo: intentar consultar la tabla directamente
                    try {
                        // Escapar el nombre de la tabla para evitar inyección SQL
                        $tablaEscapada = str_replace('`', '``', $tabla);
                        $testStmt = $db->query("SELECT 1 FROM `{$tablaEscapada}` LIMIT 0");
                        // Si llega aquí, la tabla existe (no se añade a faltantes)
                    } catch (Exception $testEx) {
                        // La tabla no existe o hay error de acceso
                        if (strpos($testEx->getMessage(), 'table') !== false || 
                            strpos($testEx->getMessage(), 'Table') !== false) {
                            $tablasFaltantes[] = $tabla;
                        } else {
                            $tablasFaltantes[] = $tabla . " (Error: " . $testEx->getMessage() . ")";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Si falla el método principal, usar método simple
            foreach ($tablasRequeridas as $tabla) {
                try {
                    // Método simple y directo
                    $sql = "SHOW TABLES LIKE '" . addslashes($tabla) . "'";
                    $stmt = $db->query($sql);
                    $result = $stmt->fetch();
                    
                    if (!$result) {
                        $tablasFaltantes[] = $tabla;
                    }
                } catch (Exception $simpleEx) {
                    $tablasFaltantes[] = $tabla . " (Error: " . $simpleEx->getMessage() . ")";
                }
            }
        }
        
        return $tablasFaltantes;
        
    } catch (Exception $e) {
        return ['Error de conexión: ' . $e->getMessage()];
    }
}

// Función para mostrar SweetAlert y redirigir
function mostrarErrorTablasFaltantes($tablasFaltantes) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/x-icon" href="assets/logov.png">
        <title>Error - SISFACT PDL Visiones</title>
        <script src="js/sweetalert211.js"></script>
        <script src="js/jquery.min.js"></script>
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
                margin: 0;
                padding: 0;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                font-family: 'Segoe UI', system-ui, sans-serif;
            }
        </style>
    </head>
    <body>
        <script>
        $(document).ready(function() {
            // Configuración global de SweetAlert2
            const Toast = Swal.mixin({
                background: '#1e1e1e',
                color: '#ffffff',
                customClass: {
                    popup: 'swal2-dark',
                    title: 'swal2-dark-title',
                    htmlContainer: 'swal2-dark-content',
                    confirmButton: 'swal2-dark-confirm',
                    cancelButton: 'swal2-dark-cancel',
                    icon: 'swal2-dark-icon',
                    actions: 'swal2-dark-actions'
                },
                buttonsStyling: false
            });
            
            // Estilos CSS personalizados para SweetAlert
            const style = document.createElement('style');
            style.innerHTML = `
                .swal2-popup.swal2-dark {
                    background: #1e1e1e !important;
                    border: 1px solid #404040 !important;
                    border-radius: 12px !important;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
                    max-width: 650px !important;
                    width: 90% !important;
                }
                
                .swal2-title.swal2-dark-title {
                    color: #ffffff !important;
                    font-size: 1.8rem !important;
                    font-weight: 600 !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 10px !important;
                    border-bottom: 1px solid #404040 !important;
                    padding-bottom: 15px !important;
                    margin-bottom: 20px !important;
                }
                
                .swal2-html-container.swal2-dark-content {
                    color: #cccccc !important;
                    text-align: left !important;
                    font-size: 1rem !important;
                    line-height: 1.6 !important;
                    padding: 0 10px !important;
                }
                
                .swal2-actions.swal2-dark-actions {
                    display: flex !important;
                    gap: 10px !important;
                    margin-top: 20px !important;
                    justify-content: center !important;
                    flex-wrap: wrap;
                }
                
                .swal2-dark-confirm {
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
                    border: none !important;
                    border-radius: 8px !important;
                    padding: 12px 25px !important;
                    font-weight: 600 !important;
                    font-size: 1rem !important;
                    transition: all 0.3s ease !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    min-width: 160px !important;
                    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3) !important;
                    color: white !important;
                    cursor: pointer !important;
                    order: 2 !important;
                    margin: 5px !important;
                }
                
                .swal2-dark-confirm:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4) !important;
                }
                
                .swal2-dark-confirm:active {
                    transform: translateY(0) !important;
                }
                
                .swal2-dark-copy {
                    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
                    border: none !important;
                    border-radius: 8px !important;
                    padding: 12px 25px !important;
                    font-weight: 600 !important;
                    font-size: 1rem !important;
                    transition: all 0.3s ease !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    min-width: 160px !important;
                    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3) !important;
                    color: white !important;
                    cursor: pointer !important;
                    order: 1 !important;
                    margin: 5px !important;
                }
                
                .swal2-dark-copy:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4) !important;
                }
                
                .swal2-dark-copy:active {
                    transform: translateY(0) !important;
                }
                
                .swal2-dark-copy.copied {
                    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
                    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3) !important;
                }
                
                .swal2-icon.swal2-dark-icon {
                    border-color: #dc3545 !important;
                    color: #dc3545 !important;
                }
                
                .swal2-icon.swal2-dark-icon .swal2-icon-content {
                    font-size: 3.5rem !important;
                }
                
                /* Estilos para la lista de tablas */
                .tablas-lista {
                    background: #2d2d2d !important;
                    border: 1px solid #404040 !important;
                    border-radius: 8px !important;
                    padding: 15px !important;
                    margin: 15px 0 !important;
                    max-height: 250px !important;
                    overflow-y: auto !important;
                }
                
                .tablas-lista-header {
                    display: flex !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    margin-bottom: 10px !important;
                    padding-bottom: 8px !important;
                    border-bottom: 1px solid #404040 !important;
                }
                
                .tablas-count {
                    background: #dc3545 !important;
                    color: white !important;
                    padding: 3px 10px !important;
                    border-radius: 20px !important;
                    font-size: 0.85rem !important;
                    font-weight: 600 !important;
                }
                
                .tablas-lista ul {
                    margin: 0 !important;
                    padding-left: 20px !important;
                }
                
                .tablas-lista li {
                    color: #ffffff !important;
                    padding: 8px 0 !important;
                    border-bottom: 1px solid #404040 !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 10px !important;
                    font-family: 'Consolas', 'Monaco', monospace !important;
                    font-size: 0.95rem !important;
                    transition: background-color 0.2s !important;
                }
                
                .tablas-lista li:hover {
                    background: rgba(255, 255, 255, 0.05) !important;
                    padding-left: 5px !important;
                }
                
                .tablas-lista li:last-child {
                    border-bottom: none !important;
                }
                
                .tablas-lista li i {
                    color: #dc3545 !important;
                    font-size: 0.9rem !important;
                    min-width: 18px !important;
                }
                
                /* Estilos para el footer */
                .swal2-footer {
                    background: #252525 !important;
                    border-top: 1px solid #404040 !important;
                    color: #888888 !important;
                    font-size: 0.85rem !important;
                    padding: 10px 20px !important;
                    margin-top: 20px !important;
                    border-radius: 0 0 12px 12px !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                }
                
                /* Estilos para la sección de copiado */
                .copy-info {
                    background: rgba(0, 123, 255, 0.1) !important;
                    border: 1px solid rgba(0, 123, 255, 0.3) !important;
                    border-radius: 6px !important;
                    padding: 10px 15px !important;
                    margin: 10px 0 !important;
                    font-size: 0.9rem !important;
                    color: #a3d0ff !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                }
                
                .copy-success {
                    background: rgba(40, 167, 69, 0.1) !important;
                    border: 1px solid rgba(40, 167, 69, 0.3) !important;
                    color: #90ee90 !important;
                    padding: 8px 15px !important;
                    border-radius: 6px !important;
                    margin-top: 10px !important;
                    align-items: center !important;
                    gap: 8px !important;
                    animation: fadeIn 0.3s ease !important;
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(-5px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                
                @media (max-width: 576px) {
                    .swal2-actions.swal2-dark-actions {
                        flex-direction: column;
                    }
                    
                    .swal2-dark-confirm,
                    .swal2-dark-copy {
                        width: 100%;
                        order: unset;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Función para copiar al portapapeles
            function copyToClipboard(text) {
                // Crear un textarea temporal
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                
                // Seleccionar y copiar
                textarea.select();
                textarea.setSelectionRange(0, 99999); // Para dispositivos móviles
                
                try {
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    return successful;
                } catch (err) {
                    console.error('Error al copiar: ', err);
                    document.body.removeChild(textarea);
                    return false;
                }
            }
            
            // Destruir la sesión PHP primero
            $.ajax({
                url: 'logout.php',
                type: 'POST',
                async: false,
                success: function() {
                    // Crear HTML para la lista de tablas
                    const tablas = <?php echo json_encode($tablasFaltantes); ?>;
                    
                    let listaHTML = '<div class="tablas-lista">';
                    listaHTML += '<div class="tablas-lista-header">';
                    listaHTML += '<span><i class="fas fa-list me-2"></i>Tablas faltantes:</span>';
                    listaHTML += '<span class="tablas-count">' + tablas.length + ' tabla(s)</span>';
                    listaHTML += '</div>';
                    listaHTML += '<ul>';
                    
                    tablas.forEach(function(tabla) {
                        listaHTML += '<li><i class="fas fa-times-circle"></i> ' + tabla + '</li>';
                    });
                    
                    listaHTML += '</ul>';
                    listaHTML += '</div>';
                    
                    // Crear el texto a copiar
                    const textoACopiar = "TABLAS FALTANTES EN SISTEMA PDL VISIONES:\n" +
                                        "========================================\n" +
                                        tablas.join('\n') + 
                                        "\n\nFecha: " + new Date().toLocaleString() + 
                                        "\nSistema: SISFACT PDL Visiones";
                    
                    // SOLUCIÓN: Crear un botón personalizado en lugar de usar showCancelButton
                    // Mostrar SweetAlert personalizado
                    Toast.fire({
                        icon: 'error',
                        title: '<i class="fas fa-database"></i> ERROR DEL SISTEMA',
                        html: '<div style="text-align: left;">' +
                              '<div class="copy-info">' +
                              '<i class="fas fa-info-circle me-2"></i>' +
                              'Puedes copiar esta información para reportar el error' +
                              '</div>' +
                              '<p style="margin-bottom: 15px; color: #cccccc;">' +
                              '<i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>' +
                              'Las siguientes tablas no existen en la base de datos:</p>' +
                              listaHTML +
                              '<div id="copySuccess" class="copy-success" style="display: none;">' +
                              '<i class="fas fa-check-circle me-2"></i>' +
                              '¡Copiado al portapapeles correctamente!' +
                              '</div>' +
                              '<p style="margin-top: 20px; color: #dc3545; font-weight: 600;">' +
                              '<i class="fas fa-power-off me-2"></i>' +
                              'El sistema se cerrará automáticamente.</p>' +
                              '</div>',
                        showConfirmButton: true,
                        showCancelButton: false, // IMPORTANTE: No usar cancel button
                        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>CERRAR SISTEMA',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        showCloseButton: false,
                        backdrop: 'rgba(0, 0, 0, 0.9)',
						footer: '<i class="fas fa-info-circle me-2" style="color: #00ffff;"></i>' +
								'<span style="color: #e0e0e0;">PDL Visiones - Sistema de Facturación v2.3.3 </span>' +
								'<a href="soporte.php" target="_blank" style="display: inline-block; background: #1e222a; color: #00ffff; text-decoration: none; font-weight: 600; font-size: 13px; padding: 4px 14px; margin-left: 8px; border-radius: 16px; border: 1px solid #00ffff; box-shadow: 0 0 8px rgba(0,255,255,0.3); transition: 0.2s;">' +
								'⚡ Soporte SISFACT VISIONES</a>',
                        customClass: {
                            confirmButton: 'swal2-dark-confirm',
                            popup: 'swal2-dark'
                        },
                        didOpen: () => {
                            // Obtener el contenedor de acciones
                            const actionsContainer = document.querySelector('.swal2-actions');
                            
                            // Crear botón personalizado para copiar
                            const copyButton = document.createElement('button');
                            copyButton.className = 'swal2-dark-copy';
                            copyButton.type = 'button';
                            copyButton.innerHTML = '<i class="fas fa-copy me-2"></i>COPIAR LISTA';
                            
                            // Insertar el botón de copiar ANTES del botón de confirmar
                            const confirmButton = document.querySelector('.swal2-confirm');
                            actionsContainer.insertBefore(copyButton, confirmButton);
                            
                            // Agregar evento al botón de copiar personalizado
                            copyButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                if (copyToClipboard(textoACopiar)) {
                                    // Mostrar mensaje de éxito dentro del mismo modal
                                    const successDiv = document.getElementById('copySuccess');
                                    successDiv.style.display = 'flex';
                                    
                                    // Cambiar estilo del botón temporalmente
                                    copyButton.classList.add('copied');
                                    copyButton.innerHTML = '<i class="fas fa-check me-2"></i>COPIADO';
                                    
                                    // Restaurar botón después de 2 segundos
                                    setTimeout(() => {
                                        copyButton.classList.remove('copied');
                                        copyButton.innerHTML = '<i class="fas fa-copy me-2"></i>COPIAR LISTA';
                                    }, 2000);
                                    
                                    // Ocultar mensaje después de 3 segundos
                                    setTimeout(() => {
                                        successDiv.style.display = 'none';
                                    }, 3000);
                                } else {
                                    // Si falla, mostrar mensaje de error dentro del mismo modal
                                    const errorDiv = document.createElement('div');
                                    errorDiv.className = 'copy-success';
                                    errorDiv.style.background = 'rgba(220, 53, 69, 0.1)';
                                    errorDiv.style.border = '1px solid rgba(220, 53, 69, 0.3)';
                                    errorDiv.style.color = '#ffb3b3';
                                    errorDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error al copiar al portapapeles';
                                    
                                    const successDiv = document.getElementById('copySuccess');
                                    successDiv.parentNode.insertBefore(errorDiv, successDiv.nextSibling);
                                    errorDiv.style.display = 'flex';
                                    
                                    // Ocultar mensaje de error después de 3 segundos
                                    setTimeout(() => {
                                        if (errorDiv.parentNode) {
                                            errorDiv.remove();
                                        }
                                    }, 3000);
                                }
                            });
                            
                            // Deshabilitar clic derecho
                            document.addEventListener('contextmenu', function(e) {
                                e.preventDefault();
                            });
                            
                            // Deshabilitar teclas específicas
                            document.addEventListener('keydown', function(e) {
                                if (e.key === 'Escape' || e.key === 'F5' || 
                                    (e.ctrlKey && e.key === 'r') || 
                                    (e.ctrlKey && e.shiftKey && e.key === 'R')) {
                                    e.preventDefault();
                                }
                            });
                        }
                    }).then((result) => {
                        // Solo se ejecuta si se hace clic en "CERRAR SISTEMA"
                        if (result.isConfirmed) {
                            window.location.href = 'index.php';
                        }
                    });
                },
                error: function() {
                    // Si falla el logout, mostrar error de sesión
                    Toast.fire({
                        icon: 'warning',
                        title: '<i class="fas fa-user-slash"></i> ERROR DE SESIÓN',
                        html: '<p style="color: #cccccc;">' +
                              '<i class="fas fa-exclamation-circle me-2" style="color: #ffc107;"></i>' +
                              'No se pudo cerrar la sesión correctamente.</p>' +
                              '<p style="margin-top: 15px; color: #ffc107;">' +
                              'Será redirigido al inicio del sistema...</p>',
                        confirmButtonText: '<i class="fas fa-redo me-2"></i>CONTINUAR',
                        showCancelButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        showCloseButton: false,
                        backdrop: 'rgba(0, 0, 0, 0.9)',
                        footer: '<i class="fas fa-exclamation-triangle me-2"></i>Error de conexión con el servidor'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                }
            });
        });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Verificación principal
function iniciarVerificacionSistema() {
    // Verificar conexión a base de datos primero
    if (!Database::checkConnection()) {
        // Si no hay conexión, Database::getConnection() ya mostrará el error
        return;
    }
    
    // Verificar tablas existentes
    $tablasFaltantes = verificarTablasExistentes();
    
    // Si hay tablas faltantes, mostrar error y redirigir
    if (!empty($tablasFaltantes)) {
        mostrarErrorTablasFaltantes($tablasFaltantes);
    }
    
    // Si todo está bien, continuar con la verificación de modo mantenimiento
    if (Database::checkMaintenanceMode()) {
        Database::showMaintenancePage();
    }
}
?>