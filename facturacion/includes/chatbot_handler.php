<?php
// includes/chatbot_handler.php - VERSIÓN PROFESIONAL ULTRA MEJORADA
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';

session_start();

date_default_timezone_set('America/New_York');

$input = json_decode(file_get_contents('php://input'), true);
$message = strtolower(trim($input['message'] ?? ''));
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => true, 'response' => "⚠️ Sistema en mantenimiento. Intenta más tarde."]);
    exit;
}

$response = procesarMensajeSISFACT($message, $db, $usuario_nombre, $usuario_id);

// Si la respuesta ya es un array (viene con corazones o confetti), úsalo directamente
if (is_array($response)) {
    echo json_encode($response);
} else {
    // Si es texto normal, enviarlo como siempre
    echo json_encode(['success' => true, 'response' => $response]);
}

function procesarMensajeSISFACT($message, $db, $usuario_nombre, $usuario_id) {

    // NORMALIZAR EL MENSAJE (ELIMINAR TILDES)
    $message_original = $message;
    $message = normalizarTexto($message);
    $message = strtolower(trim($message));
	


    // ==================== 1. COMANDOS DE CONTROL DEL SISTEMA ====================
    
    if (preg_match('/\b(limpiar|clean|clear|borrar chat|vaciar chat)\b/i', $message)) {
        return "🗑️ **CHAT LIMPIADO**\n\n" .
               "El chat ha sido limpiado. Puedes empezar de nuevo.\n\n" .
               "💡 Escribe **ayuda** para ver los comandos disponibles.";
    }
    
    if (preg_match('/\b(ayuda|help|comandos|que puedes hacer|opciones|menu)\b/i', $message)) {
        return getAyudaCompleta();
    }
    
    if (preg_match('/\b(version|versión|que version|version del sistema)\b/i', $message)) {
        return "📌 **SISFACT PDL Visiones**\nVersión: **2.3.3**\nÚltima actualización: Marzo 2025\nDesarrollado por: Franklin Ramos Lamadrid\n© 2025 PDL Visiones";
    }
    
    if (preg_match('/\b(estado del sistema|sistema funciona|salud del sistema|informacion del sistema|info sistema|que version tiene|que php|que mysql|que maria db|version del servidor|detalles del sistema|versiones)\b/i', $message)) {
        return getEstadoSistema($db);
    }
// ==================== COMANDO PARA MOSTRAR ASISTENTE OCULTO ====================

if (preg_match('/\b(mostrar asistente|mostrar robot|mostrar bot|aparecer|mostrar chat|mostrar chatbot|recuperar asistente|recuperar robot|mostrar de nuevo)\b/i', $message)) {
    return "<div style='text-align: center;'>
                <span style='font-size: 48px; display: block;'>🤖</span>
                <strong>¡Asistente recuperado!</strong><br><br>
                El asistente virtual ha sido reactivado. Aparecerá en la esquina inferior izquierda.
                <br><br>
                <small>💡 Puedes hacer clic derecho sobre mi logo para más opciones.</small>
            </div>
            <!--MOSTRAR_ROBOT-->";
}
if (preg_match('/\b(ocultar asistente|ocultar robot|esconder robot|desaparecer robot|cerrar asistente|cerrar robot|adiós robot|hasta luego|salir asistente|ocultar bot|esconder bot|esconder asistente|desaparecer asistente|apagar asistente)\b/i', $message)) {
    return "<!--OCULTAR_ROBOT-->";
}
    
    // ==================== 2. BÚSQUEDAS ESPECÍFICAS CON PARÁMETROS ====================
    
    if (preg_match('/\bbuscar (factura|cliente|servicio|categoria)\s+(.+)$/i', $message, $matches)) {
        $tipo = $matches[1];
        $termino = trim($matches[2]);
        return buscarEnSistema($db, $tipo, $termino);
    }
    
    if (preg_match('/\b(cliente) con (codigo|contrato) (.*?)$\b/i', $message, $matches)) {
        $codigo = trim($matches[3]);
        return getClientePorCodigo($db, $codigo);
    }
    
    // ==================== 3. PATRONES CON NÚMEROS ESPECÍFICOS ====================
    
    if (preg_match('/\b(ultimas?|recientes) (\d+)\s*(factura|facturas)\b/i', $message, $matches)) {
        $limite = intval($matches[2]);
        return getUltimasFacturas($db, $limite);
    }
    
    if (preg_match('/\b(ultimas?|recientes) (\d+)\s*(cliente|clientes)\b/i', $message, $matches)) {
        $limite = intval($matches[2]);
        return getUltimosClientes($db, $limite);
    }
    
    // ==================== 4. PATRONES CON FECHAS ESPECÍFICAS ====================
    
    if (preg_match('/\b(facturas?|ingresos) (de|del) (dia|día) (\d{1,2})\b/i', $message, $matches)) {
        $dia = intval($matches[4]);
        return getFacturasPorDia($db, $dia);
    }
    
    if (preg_match('/\b(facturas?|ingresos) (de|del) mes (\d{1,2})\b/i', $message, $matches)) {
        $mes = intval($matches[3]);
        return getFacturasPorMes($db, $mes);
    }
    
    if (preg_match('/\b(facturas?|ingresos) (de|del) año (\d{4})\b/i', $message, $matches)) {
        $anio = intval($matches[3]);
        return getFacturasPorAnio($db, $anio);
    }
    
    // ==================== 5. PATRONES CON ESTADOS ESPECÍFICOS ====================
    
    if (preg_match('/\b(facturas? (por|con estado) (pendiente|pendientes))\b/i', $message) || 
        preg_match('/\b(facturas? pendientes)\b/i', $message)) {
        return getFacturasPorEstado($db, 'PENDIENTE');
    }
    
    if (preg_match('/\b(facturas? (por|con estado) (pagada|pagadas))\b/i', $message) || 
        preg_match('/\b(facturas? pagadas)\b/i', $message)) {
        return getFacturasPorEstado($db, 'PAGADA');
    }
    
    if (preg_match('/\b(facturas? (por|con estado) (contabilizada|contabilizadas))\b/i', $message) || 
        preg_match('/\b(facturas? contabilizadas)\b/i', $message)) {
        return getFacturasPorEstado($db, 'CONTABILIZADA');
    }
    
    if (preg_match('/\b(facturas? (por|con estado) (anulada|anuladas))\b/i', $message) || 
        preg_match('/\b(facturas? anuladas)\b/i', $message)) {
        return getFacturasPorEstado($db, 'ANULADA');
    }
    
    if (preg_match('/\b(facturas? (por|con estado) (cerrada|cerradas))\b/i', $message) || 
        preg_match('/\b(facturas? cerradas)\b/i', $message)) {
        return getFacturasPorEstado($db, 'CERRADA');
    }
    
    // ==================== 6. PATRONES DE NAVEGACIÓN Y ACCIONES ====================

    // Palabras sueltas exactas
    $palabras_navegacion = [
        'facturas?|📋' => ['facturas.php', '📄 el listado de facturas', '📋'],
        'clientes?|👥' => ['clientes.php', '👥 el listado de clientes', '👤'],
        'servicios?|🛠️' => ['servicios.php', '🛠️ el catálogo de servicios', '🔧'],
        'categorías?|categorias?|📁' => ['categorias.php', '📁 las categorías de servicios', '📂'],
        'reportes?|📊' => ['reportes.php', '📊 los reportes y estadísticas', '📈'],
        'usuarios?|👤' => ['usuarios.php', '👤 la gestión de usuarios', '👥'],
        'rentabilidad|💰' => ['rentabilidad.php', '💰 el análisis de rentabilidad', '💹'],
        'configuraci[oó]n|configuracion|⚙️' => ['configuracion.php', '⚙️ la configuración del sistema', '🔧'],
        'planes?|🎯' => ['planes.php', '🎯 los planes de ingresos', '🎯'],
        'histórico|historico|📜' => ['historico_view.php', '📜 el historial de operaciones', '📅']
    ];
    
    foreach ($palabras_navegacion as $patron => $datos) {
        if (preg_match('/^\s*(' . $patron . ')\s*$/i', $message)) {
            return getNavegacionResponse($datos[0], $datos[1], $datos[2]);
        }
    }
    
    // Patrones con "abrir", "ir a", "ver", "mostrar"
    $paginas = [
        'facturas?.*' => ['url' => 'facturas.php', 'desc' => '📄 el listado de facturas', 'icono' => '📋'],
        'clientes' => ['url' => 'clientes.php', 'desc' => '👥 el listado de clientes', 'icono' => '👤'],
        'servicios' => ['url' => 'servicios.php', 'desc' => '🛠️ el catálogo de servicios', 'icono' => '🔧'],
        'categorias?' => ['url' => 'categorias.php', 'desc' => '📁 las categorías de servicios', 'icono' => '📂'],
        'reportes' => ['url' => 'reportes.php', 'desc' => '📊 los reportes y estadísticas', 'icono' => '📈'],
        'rentabilidad' => ['url' => 'rentabilidad.php', 'desc' => '💰 el análisis de rentabilidad', 'icono' => '💹'],
        'configuracion' => ['url' => 'configuracion.php', 'desc' => '⚙️ la configuración del sistema', 'icono' => '🔧'],
        'planes' => ['url' => 'planes.php', 'desc' => '🎯 los planes de ingresos', 'icono' => '🎯'],
        'historico' => ['url' => 'historico_view.php', 'desc' => '📜 el historial de operaciones', 'icono' => '📅'],
        'usuarios' => ['url' => 'usuarios.php', 'desc' => '👤 la gestión de usuarios', 'icono' => '👥']
    ];
    
    foreach ($paginas as $patron => $datos) {
        if (preg_match('/\b(abrir|ir a|mostrar|ver) ' . $patron . '\b/i', $message)) {
            return getNavegacionResponse($datos['url'], $datos['desc'], $datos['icono']);
        }
    }
    
    if (preg_match('/\b(crear|nueva|nuevo) factura\b/i', $message)) {
        return getCrearResponse('nueva_factura.php', '📄', 'factura', 'Crear Factura');
    }
    
    if (preg_match('/\b(crear|nuevo) cliente\b/i', $message)) {
        return getCrearResponse('nuevo_cliente.php', '👤', 'cliente', 'Registrar Cliente');
    }
    
    if (preg_match('/\b(crear|nueva) categoria\b/i', $message)) {
        return getCrearResponse('nueva_categoria.php', '📁', 'categoría', 'Crear Categoría');
    }
    
    if (preg_match('/\b(crear|nuevo) servicio\b/i', $message)) {
        return getCrearResponse('nuevo_servicio.php', '🛠️', 'servicio', 'Crear Servicio');
    }
    
    if (preg_match('/\b(crear|nuevo) usuario\b/i', $message)) {
        return getCrearResponse('nuevo_usuario.php', '👥', 'usuario', 'Registrar Usuario');
    }
    
    if (preg_match('/\b(imprimir|print) factura\b/i', $message)) {
        return "🖨️ **Para imprimir una factura:**\n\n" .
               "1️⃣ Ve a la sección de facturas\n" .
               "2️⃣ Busca la factura que deseas\n" .
               "3️⃣ Haz clic en el botón de imprimir\n\n" .
               "📌 También puedes usar: **abrir facturas** para ver el listado.\n\n" .
               "💡 ¿Tienes el número de factura? Puedo buscarla: *buscar factura FV-2026...*";
    }
    
    if (preg_match('/\b(imprimir|print) (todo|todos) (el mes|este mes)\b/i', $message)) {
        return getImprimirMesResponse();
    }
    
    // ==================== 7. CONSULTAS GENERALES DE FACTURAS ====================
    
    if (preg_match('/\b(facturas? (de )?hoy)\b/i', $message)) {
        return getFacturasHoy($db);
    }
    
    if (preg_match('/\b(facturas? (del|de este|este) mes)\b/i', $message)) {
        return getFacturasDelMes($db);
    }
    
    if (preg_match('/\b(cuantas|total|numero de) (factura|facturas) (hay|existen|tenemos)\b/i', $message) || 
        $message == '¿cuántas facturas hay?' || 
        $message == '¿Cuántas facturas hay?' || 
        $message == 'cuantas facturas hay' || 
        $message == 'cuantas facturas existen' ||
        $message == 'total de facturas' ||
        strpos($message, 'cuantas facturas') !== false) {
        return getTotalFacturas($db);
    }
    
    if (preg_match('/\b(ultimas?|recientes)\s*(factura|facturas)\b/i', $message)) {
        return getUltimasFacturas($db, 5);
    }
    
    if (preg_match('/\b(facturas? por estado)\b/i', $message)) {
        return getFacturasPorEstadoGeneral($db);
    }
    
    if (preg_match('/\b(facturas?) (del|de) (cliente|proveedor) (.*?)$\b/i', $message, $matches)) {
        $cliente = trim($matches[4]);
        return getFacturasCliente($db, $cliente);
    }
    
    // ==================== 8. CONSULTAS DE INGRESOS ====================
    
    if (preg_match('/\b(ingresos|ganancias) (de )?hoy\b/i', $message)) {
        return getIngresosHoy($db);
    }
    
    if (preg_match('/\b(ingresos|ganancias) (del|de este|este) mes\b/i', $message)) {
        return getIngresosMes($db);
    }
    
    if (preg_match('/\b(ingresos|ganancias) (del|de este|este) (año|anual)\b/i', $message)) {
        return getIngresosAnio($db);
    }
    
    if (preg_match('/\b(comparar|comparativa) (ingresos|facturas) (con|vs) (plan|meta)\b/i', $message)) {
        return getComparativaPlan($db);
    }
    
    // ==================== 9. CONSULTAS DE CLIENTES ====================
    
    if (preg_match('/\b(contratos? por vencer|contratos? proximos a vencer|contratos? que vencen pronto)\b/i', $message)) {
        return getContratosPorVencerDetallado($db);
    }
    
    if (preg_match('/\b(contratos? vencidos|contratos? expirados)\b/i', $message)) {
        return getContratosVencidosDetallado($db);
    }
    
    if (preg_match('/\b(cuantos|total) (cliente|clientes) (hay|existen|tenemos)\b/i', $message) || 
        preg_match('/\b(total de clientes)\b/i', $message)) {
        return getTotalClientes($db);
    }
    
    if (preg_match('/\b(ultimos?|recientes)\s*(cliente|clientes)\b/i', $message)) {
        return getUltimosClientes($db, 5);
    }
    
    if (preg_match('/\b(clientes? (activo|activos))\b/i', $message) || 
        preg_match('/\b(clientes? activos)\b/i', $message)) {
        return getClientesActivos($db);
    }
    
    if (preg_match('/\b(clientes? (inactivo|inactivos))\b/i', $message) || 
        preg_match('/\b(clientes? inactivos)\b/i', $message)) {
        return getClientesInactivos($db);
    }
    
    if (preg_match('/\b(clientes? que (renuevan|renuevan contrato))\b/i', $message)) {
        return getClientesRenuevan($db);
    }
    
    if (preg_match('/\b(promedio|media) de (vigencia|duracion) de (contratos?)\b/i', $message)) {
        return getPromedioVigencia($db);
    }
    
    // ==================== 10. CONSULTAS DE SERVICIOS ====================
    
    if (preg_match('/\b(servicios? (mas|mas) (solicitados|vendidos|populares))\b/i', $message, $matches)) {
        $limite = 5;
        if (preg_match('/(\d+)/', $message, $numMatch)) {
            $limite = intval($numMatch[1]);
        }
        return getServiciosMasSolicitados($db, $limite);
    }
    
    if (preg_match('/\b(cuantos|total) (servicio|servicios) (hay|existen|tenemos)\b/i', $message)) {
        return getTotalServicios($db);
    }
    
    if (preg_match('/\b(servicios? (activo|activos))\b/i', $message)) {
        return getServiciosActivos($db);
    }
    
    if (preg_match('/\b(servicios? (inactivo|inactivos))\b/i', $message)) {
        return getServiciosInactivos($db);
    }
    
    if (preg_match('/\b(servicio) (mas caro|mas costoso)\b/i', $message)) {
        return getServicioMasCaro($db);
    }
    
    if (preg_match('/\b(servicio) (mas barato|menos costoso)\b/i', $message)) {
        return getServicioMasBarato($db);
    }
    
    if (preg_match('/\b(servicios? de la (categoria|categoría)) (.*?)$\b/i', $message, $matches)) {
        $categoria = trim($matches[3]);
        return getServiciosPorCategoria($db, $categoria);
    }
    
    // ==================== 11. CONSULTAS DE CATEGORÍAS ====================
    
    if (preg_match('/\b(cuantas|total) (categoria|categorias) (hay|existen|tenemos)\b/i', $message)) {
        return getTotalCategorias($db);
    }
    
    if (preg_match('/\b(categorias? (activa|activas))\b/i', $message)) {
        return getCategoriasActivas($db);
    }
    
    if (preg_match('/\b(categorias? (inactiva|inactivas))\b/i', $message)) {
        return getCategoriasInactivas($db);
    }
    
    if (preg_match('/\b(categoria|categorias) con (mas|mayor) (servicios?)\b/i', $message)) {
        return getCategoriaConMasServicios($db);
    }
    
    if (preg_match('/\b(categoria|categorias) por (codigo|codigos?)\b/i', $message)) {
        return getCategoriasConCodigos($db);
    }
    
    // ==================== 12. CONSULTAS DE USUARIOS ====================
    
    if (preg_match('/\b(cuantos|total) (usuario|usuarios) (hay|existen|tenemos)\b/i', $message)) {
        return getTotalUsuarios($db);
    }
    
    if (preg_match('/\b(usuario|usuarios) (activo|activos)\b/i', $message)) {
        return getUsuariosActivos($db);
    }
    
    if (preg_match('/\b(usuario|usuarios) por (rol|roles?)\b/i', $message)) {
        return getUsuariosPorRol($db);
    }
    
    if (preg_match('/\b(ultimo|ultimos) acceso (de )? (usuario|usuarios)\b/i', $message)) {
        return getUltimosAccesos($db);
    }
    
    if (preg_match('/\b(quien|quién) (creo|registro) (la )? (ultima|última) (factura|actividad)\b/i', $message)) {
        return getUltimaActividadUsuario($db);
    }
    
    // ==================== 13. REPORTES AVANZADOS ====================
    
    if (preg_match('/\b(reporte|reportes) (completo|general|resumen)\b/i', $message)) {
        return getReporteGeneral($db);
    }
    
    if (preg_match('/\b(mejor|top) (dia|día|mes|trimestre|año) (de )? (ventas|facturas)\b/i', $message)) {
        return getMejorPeriodo($db, $message);
    }
    
    if (preg_match('/\b(peor|menor) (dia|día|mes|trimestre|año) (de )? (ventas|facturas)\b/i', $message)) {
        return getPeorPeriodo($db, $message);
    }
    
    if (preg_match('/\b(promedio) de (facturas?|ingresos) (por )? (dia|día|mes)\b/i', $message)) {
        return getPromedios($db, $message);
    }
    
	if (preg_match('/\b(proyeccion|proyección|proyectado|vamos a facturar|esperamos facturar)\s*(del|de|para|mensual)?\s*(el)?\s*(mes|este mes|mensual)?\b/i', $message)) {
        return getProyeccionMensual($db);
    }
    
    if (preg_match('/\b(grafico|gráfico|chart) de (ventas|ingresos)\b/i', $message)) {
        return getSugerenciaGrafico();
    }
    
    if (preg_match('/\b(evolucion|evolución) (mensual|por mes)\b/i', $message)) {
        return getEvolucionMensual($db);
    }
    
    if (preg_match('/\b(comparar|comparativa) (años?|anual)\b/i', $message)) {
        return getComparativaAnual($db);
    }
    
    if (preg_match('/\b(ranking) de (clientes?)\b/i', $message)) {
        return getRankingClientes($db);
    }
    
    if (preg_match('/\b(metodo|método) de (pago|pagos) (mas|más) (usado|utilizado)\b/i', $message)) {
        return getMetodoPagoPopular($db);
    }
    
    // ==================== 14. INFORMACIÓN DE CIERRES ====================
    
    if (preg_match('/\b(cierre|cierres) (mensual|del mes)\b/i', $message)) {
        return getUltimoCierreMensual($db);
    }
    
    // NUEVO: CIERRE ACTUAL
    if (preg_match('/\b(cierre actual|cierre en curso|cierre del mes actual|estado del cierre|progreso del cierre)\b/i', $message)) {
        return getCierreActual($db);
    }
    
    if (preg_match('/\b(historial|historico) de (cierres?)\b/i', $message)) {
        return getHistorialCierres($db);
    }
    
    if (preg_match('/\b(proximo|próximo) cierre\b/i', $message)) {
        return getProximoCierre();
    }
    
    // ==================== 15. PLANES Y METAS ====================
    
    if (preg_match('/\b(plan|planes?) (del mes|mensual)\b/i', $message)) {
        return getPlanMensual($db);
    }
    
    if (preg_match('/\b(plan|planes?) (del año|anual)\b/i', $message)) {
        return getPlanAnual($db);
    }
    
    if (preg_match('/\b(cumplimiento) del (plan)\b/i', $message)) {
        return getCumplimientoPlan($db);
    }
    
    if (preg_match('/\b(meta|objetivo) (del mes|mensual)\b/i', $message)) {
        return getMetaMensual($db);
    }
    
    // ==================== 16. HISTORIAL Y AUDITORÍA ====================
    
    if (preg_match('/\b(ultimas|recientes) (actividades|operaciones)\b/i', $message)) {
        return getUltimasActividades($db);
    }
    
    // NUEVO: OPERACIONES POR FECHA
    if (preg_match('/\b(operaciones|actividades) (del|del día|del dia) (\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/i', $message, $matches)) {
        $dia = $matches[3];
        $mes = $matches[4];
        $anio = $matches[5];
        return getOperacionesPorDiaEspecifico($db, $dia, $mes, $anio);
    }
    
    if (preg_match('/\b(operaciones|actividades) (de )?(hoy|ayer)\b/i', $message, $matches)) {
        $dia = $matches[3] ?? 'hoy';
        return getOperacionesPorFecha($db, $dia);
    }
    
    // NUEVO: RESUMEN DE OPERACIONES POR FECHA
    if (preg_match('/\b(resumen|estadísticas?) de (operaciones|actividades) (de )?(hoy|ayer)\b/i', $message, $matches)) {
        $dia = $matches[4] ?? 'hoy';
        $fecha_sql = $dia === 'hoy' ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
        $fecha_desc = $dia === 'hoy' ? 'HOY' : 'AYER';
        return getResumenOperacionesFecha($db, $fecha_desc, $fecha_sql);
    }
    
    if (preg_match('/\b(resumen|estadísticas?) de (operaciones|actividades) (del|del día|del dia) (\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/i', $message, $matches)) {
        $dia = $matches[4];
        $mes = $matches[5];
        $anio = $matches[6];
        $fecha_sql = "$anio-$mes-$dia";
        $fecha_desc = date('d/m/Y', strtotime($fecha_sql));
        return getResumenOperacionesFecha($db, $fecha_desc, $fecha_sql);
    }
    
    if (preg_match('/\b(actividades|operaciones) del (usuario|usuario) (.*?)$\b/i', $message, $matches)) {
        $usuario = trim($matches[3]);
        return getActividadesUsuario($db, $usuario);
    }
    
    if (preg_match('/\b(cuantas|total) (operaciones|actividades) (hay|existen)\b/i', $message)) {
        return getTotalOperaciones($db);
    }
    
    // ==================== 17. AYUDA CONTEXTUAL ====================
    
    if (preg_match('/\b(como|como se) (crea|hace|registra) (una )? factura\b/i', $message)) {
        return "📝 **Para crear una factura en SISFACT:**\n\n" .
               "1️⃣ Ve a **Facturas** en el menú lateral\n" .
               "2️⃣ Haz clic en **Nueva Factura**\n" .
               "3️⃣ Selecciona el cliente\n" .
               "4️⃣ Agrega los servicios y cantidades\n" .
               "5️⃣ Completa los datos de pago\n" .
               "6️⃣ Guarda la factura\n\n" .
               "💡 También puedes usar el comando: **crear factura** para ir directamente";
    }
    
    if (preg_match('/\b(como|como se) (registra|crea) (un )? cliente\b/i', $message)) {
        return "👤 **Para registrar un cliente en SISFACT:**\n\n" .
               "1️⃣ Ve a **Clientes** en el menú lateral\n" .
               "2️⃣ Haz clic en **Nuevo Cliente**\n" .
               "3️⃣ Completa todos los datos requeridos\n" .
               "4️⃣ Establece la vigencia del contrato\n" .
               "5️⃣ Guarda el cliente\n\n" .
               "💡 Comando rápido: **crear cliente**";
    }
    
    // ==================== 18. CHISTES ====================
    
    if (preg_match('/\b(chiste de cliente|chiste|chiste de factura|chiste de facturacion|chiste de pagos|chiste de cobros|chiste de contabilidad|chiste de impuestos|chiste de deudas|chiste de morosos)\b/i', $message)) {
        return getChisteRespuesta();
    }


// ==================== 18.2 INTERACCIONES DIVERTIDAS Y HUMANIZADAS CON BOTONES ====================


// RESPUESTA PARA CUANDO EL USUARIO PREGUNTA "QUE HACES"
if (preg_match('/\b(que haces|qué haces|en que trabajas|a que te dedicas|what are you doing)\b/i', $message)) {
    $respuestas_quehaces = [
        "🤖 Estoy aquí, procesando datos y esperando tus consultas.",
        "💻 Analizando facturas, contando clientes y pensando en chistes.",
        "📊 Revisando estadísticas para tener respuestas rápidas.",
        "☕ Tomando un café virtual mientras te espero.",
        "🧠 Procesando 1 y 0 para ayudarte con SISFACT.",
        "📚 Estudiando nuevas formas de hacerte reír con contabilidad.",
        "⚡ Optimizando mi código para responderte más rápido.",
        "🎯 Enfocado en tus necesidades. ¿Qué necesitas?"
    ];
    return $respuestas_quehaces[array_rand($respuestas_quehaces)];
}
// RESPUESTA PARA CUANDO EL USUARIO PREGUNTA "QUE SIGNIFICA SISFACT"
if (preg_match('/\b(que significa sisfact|qué significa sisfact|significado de sisfact|sisfact que es|que es sisfact)\b/i', $message)) {
    return "📌 **SISFACT** significa **Sistema de Facturación**.\n\n" .
           "Es un sistema desarrollado por **Franklin Ramos Lamadrid** (kaky°) " .
           "para gestionar facturas, clientes, servicios y más en PDL Visiones.\n\n" .
           "💡 Llevo su nombre porque... ¡soy su asistente!";
}
// RESPUESTA PARA PUNTOS SUSPENSIVOS ("...")
if (preg_match('/^(\.{2,}|\.\s*\.\s*\.)$/i', trim($message_original))) {
    $respuestas_suspensivos = [
        "🤔 ¿Algo en mente? Puedes preguntarme lo que necesites.\n\n📌 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos del mes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">👥 Clientes activos</button>",
        
        "😅 Parece que estás pensando... ¿En qué puedo ayudarte?\n\n🔍 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas facturas</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos por vencer</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">❓ Ayuda</button>",
        
        "💭 Te leo los pensamientos... ¡mentira! Cuéntame qué necesitas.\n\n⚡ **Comandos rápidos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Facturas pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('servicios más solicitados')\" class=\"chat-quick-btn\">⭐ Servicios top</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('próximo cierre')\" class=\"chat-quick-btn\">📅 Próximo cierre</button>",
        
        "🤨 ¿Te quedaste pensando? Pregúntame sin miedo.\n\n📊 **Ejemplos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking clientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('comparar ingresos con plan')\" class=\"chat-quick-btn\">📈 Comparar con plan</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>",
        
        "🧐 ¿Necesitas ayuda con algo en particular?\n\n🎯 **Opciones:**\n" .
        "<a href=\"facturas.php\" target=\"_blank\" class=\"chat-link-btn\">📄 Abrir facturas</a>\n" .
        "<a href=\"clientes.php\" target=\"_blank\" class=\"chat-link-btn\">👤 Abrir clientes</a>\n" .
        "<a href=\"reportes.php\" target=\"_blank\" class=\"chat-link-btn\">📊 Abrir reportes</a>",
        
        "😊 No te quedes con la duda, pregúntame lo que sea.\n\n💡 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar factura')\" class=\"chat-quick-btn\">🔍 Buscar factura</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('cliente con código')\" class=\"chat-quick-btn\">🔎 Cliente por código</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>",
        
        "🤖 *modo lectura de mentes activado*... fallé. Mejor dime tú.\n\n📋 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos hoy')\" class=\"chat-quick-btn\">💰 Ingresos hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas del mes')\" class=\"chat-quick-btn\">📄 Facturas del mes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('servicio más caro')\" class=\"chat-quick-btn\">💎 Servicio más caro</button>",
        
        "💬 Los puntos suspensivos me intrigan... ¿Qué querías decir?\n\n🔗 **Enlaces rápidos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos vencidos')\" class=\"chat-quick-btn\">❌ Contratos vencidos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('meta mensual')\" class=\"chat-quick-btn\">🎯 Meta mensual</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('plan del mes')\" class=\"chat-quick-btn\">📅 Plan del mes</button>",
        
        "🎯 Dispara, estoy aquí para responderte.\n\n🚀 **Comandos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas 5 facturas')\" class=\"chat-quick-btn\">5️⃣ Últimas 5</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes activos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('resumen del mes')\" class=\"chat-quick-btn\">📊 Resumen del mes</button>",
        
        "📢 ¡Habla! No te quedes con las ganas de preguntar.\n\n📌 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('evolución mensual')\" class=\"chat-quick-btn\">📈 Evolución</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('comparativa anual')\" class=\"chat-quick-btn\">📉 Comparativa</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste de factura')\" class=\"chat-quick-btn\">😁 Chiste</button>"
    ];
    return $respuestas_suspensivos[array_rand($respuestas_suspensivos)];
}

// RESPUESTA PARA ZZZ (sueño/aburrimiento)
if (preg_match('/\b(z{2,}|zzz|zzzz|aburrido|me aburro|que sueño|tengo sueño|aburrimiento)\b/i', $message)) {
    $respuestas_zzz = [
        "😴 ¿Te aburres? Podemos hablar de facturas, clientes o servicios... ¡no te dormirás!\n\n⚡ **Para despertar:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos mes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>",
        
        "💤 ¡Despierta! Tengo muchas estadísticas interesantes para mostrarte.\n\n📊 **Datos interesantes:**\n" .
        "<button onclick=\"enviarMensajeDirecto('mejor día de ventas')\" class=\"chat-quick-btn\">🏆 Mejor día</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🥇 Ranking</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('proyección mensual')\" class=\"chat-quick-btn\">🔮 Proyección</button>",
        
        "☕ ¿Necesitas un café? Yo mientras te muestro los ingresos del mes.\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\" style=\"background: #6f4e37;\">💰 Ingresos del mes ☕</button>",
        
        "😪 No te duermas que aún tengo muchos chistes de contabilidad por contar.\n\n😄 **Chistes:**\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste de cliente')\" class=\"chat-quick-btn\">👤 Cliente</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste de factura')\" class=\"chat-quick-btn\">📄 Factura</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste de contabilidad')\" class=\"chat-quick-btn\">🧮 Contador</button>",
        
        "⚡ ¡Vamos arriba! ¿Quieres que te cuente un chiste para despertarte?\n\n🎭 **Elige:**\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Activos</button>",
        
        "🧠 El sueño se pasa con datos interesantes. ¿Sabías que hoy hay " . getFacturasHoyCount() . " facturas?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Ver facturas de hoy</button>",
        
        "📊 Dormirse es de cobardes... ¡revisemos mejor los contratos por vencer!\n\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\" style=\"background: #ff9800;\">⚠️ Contratos por vencer</button>",
        
        "🎮 Modo despertador activado: *bip bip bip* ¡Consulta algo!\n\n🎯 **Opciones:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('servicios más solicitados')\" class=\"chat-quick-btn\">⭐ Servicios top</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">❓ Ayuda</button>",
        
        "😴 Zzz... ¡Hey! No soy yo quien duerme, eres tú. ¿Necesitas ayuda?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>",
        
        "💪 Un café virtual para ti ☕. Ahora, ¿qué consulta tienes?\n\n📋 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos hoy')\" class=\"chat-quick-btn\">💰 Ingresos hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('próximo cierre')\" class=\"chat-quick-btn\">📅 Próximo cierre</button>"
    ];
    return $respuestas_zzz[array_rand($respuestas_zzz)];
}

// RESPUESTA PARA "???" (confusión)
if (preg_match('/^(\?{2,}|\?\s*\?\s*\?)$/i', trim($message_original)) || 
    preg_match('/\b(no entiendo|que significa|como así|explícame|no comprendo)\b/i', $message)) {
    $respuestas_dudas = [
        "🤔 ¿Tienes dudas? Puedo explicarte mejor si eres más específico.\n\n📌 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Facturas pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes activos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "❓ Veo signos de interrogación... ¿Qué no quedó claro?\n\n📚 **Ejemplos claros:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas 5 facturas')\" class=\"chat-quick-btn\">5️⃣ Últimas 5</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos mes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>",
        
        "🧐 Confusión detectada. Prueba preguntando:\n\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button> " .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Activos</button>",
        
        "📚 Si algo no se entiende, puedo darte ejemplos. ¿Qué necesitas saber?\n\n💡 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('cómo crear factura')\" class=\"chat-quick-btn\">📄 Crear factura</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('cómo registrar cliente')\" class=\"chat-quick-btn\">👤 Registrar cliente</button>\n" .
        "<a href=\"ayuda.php\" target=\"_blank\" class=\"chat-link-btn\">📖 Manual</a>",
        
        "💡 ¿Necesitas ayuda con algún comando en particular?\n\n📋 **Comandos útiles:**\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Lista completa</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas por estado')\" class=\"chat-quick-btn\">📊 Por estado</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking</button>",
        
        "🔍 A veces no me explico bien. Intenta con preguntas más simples.\n\n✅ **Ejemplos simples:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>",
        
        "🎯 Enfocándonos... ¿Qué tema te interesa?\n\n📊 **Temas:**\n" .
        "<a href=\"facturas.php\" class=\"chat-link-btn\">📄 Facturas</a>\n" .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a>\n" .
        "<a href=\"servicios.php\" class=\"chat-link-btn\">🛠️ Servicios</a>\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Reportes</a>",
        
        "🤷 No te preocupes, soy un bot y a veces no me entiendo ni yo.\n\n🔄 **Recomiendo:** " .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>",
        
        "📌 Para empezar, prueba con:\n\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas facturas</button> " .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos del mes</button>",
        
        "🔄 ¿Reiniciamos? Cuéntame qué necesitas de SISFACT.\n\n🎯 **Opciones:**\n" .
        "<a href=\"facturas.php\" class=\"chat-link-btn\">📄 Facturas</a>\n" .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a>\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Reportes</a>"
    ];
    return $respuestas_dudas[array_rand($respuestas_dudas)];
}

// RESPUESTA PARA "jaja", "jeje", "jiji" (risas)
if (preg_match('/\b(ja{2,}|je{2,}|ji{2,}|jo{2,}|lol|risa|gracioso|jajaja|jijiji|jejeje|jojojo)\b/i', $message)) {
    $respuestas_risas = [
        "😄 Me alegra que te diviertas! ¿Necesitas algo más?\n\n📌 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Otro chiste</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos</button>",
        
        "😂 ¡Qué risa! Pero sigo aquí para ayudarte con lo que necesites.\n\n🔍 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('servicios más solicitados')\" class=\"chat-quick-btn\">⭐ Servicios</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking</button>",
        
        "🤣 Me encanta cuando los usuarios se ríen. ¿En qué más te ayudo?\n\n🎯 **Opciones:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('próximo cierre')\" class=\"chat-quick-btn\">📅 Cierre</button>",
        
        "😊 Tu risa me da energía. ¡Sigamos! ¿Qué consulta tienes?\n\n📊 **Ejemplos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('comparar ingresos con plan')\" class=\"chat-quick-btn\">📈 Comparar</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('evolución mensual')\" class=\"chat-quick-btn\">📊 Evolución</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('meta mensual')\" class=\"chat-quick-btn\">🎯 Meta</button>",
        
        "🎉 ¡Qué bien! La felicidad es contagiosa. ¿Ahora qué necesitas?\n\n💡 **Sugerencias:**\n" .
        "<a href=\"nueva_factura.php\" class=\"chat-link-btn\">➕ Nueva factura</a>\n" .
        "<a href=\"nuevo_cliente.php\" class=\"chat-link-btn\">👤 Nuevo cliente</a>\n" .
        "<a href=\"nuevo_servicio.php\" class=\"chat-link-btn\">🔧 Nuevo servicio</a>",
        
        "😁 Me has contagiado la risa. Cuéntame más... o pregúntame algo.\n\n📋 **Comandos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar cliente')\" class=\"chat-quick-btn\">🔍 Buscar</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "💬 Reír es salud. ¿Quieres ver las últimas facturas para seguir contento?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Ver últimas facturas</button>",
        
        "🌟 Con esa actitud positiva, seguro que hoy será un gran día de ventas.\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos hoy')\" class=\"chat-quick-btn\">💰 Ingresos de hoy</button>",
        
        "🤗 ¡Me encanta! ¿Quieres que te cuente un chiste más?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Otro chiste</button>",
        
        "😎 Eso es, con humor todo es mejor. ¿Necesitas algo del sistema?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>"
    ];
    return $respuestas_risas[array_rand($respuestas_risas)];
}

// RESPUESTA PARA "ok", "okey", "vale"
if (preg_match('/\b(ok|okey|oke|vale|de acuerdo|está bien|esta bien|okay)\b/i', $message)) {
    $respuestas_ok = [
        "👍 ¡Perfecto! ¿Necesitas algo más?\n\n📌 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos mes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes</button>",
        
        "✅ De acuerdo. Estoy aquí para lo que necesites.\n\n🔍 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "👌 Entendido. ¿Alguna otra consulta?\n\n📊 **Opciones:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('servicios más solicitados')\" class=\"chat-quick-btn\">⭐ Servicios</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking</button>",
        
        "🎯 ¡Listo! ¿Qué más puedo hacer por ti?\n\n💡 **Ejemplos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('comparar ingresos con plan')\" class=\"chat-quick-btn\">📈 Comparar</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('proyección mensual')\" class=\"chat-quick-btn\">🔮 Proyección</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('próximo cierre')\" class=\"chat-quick-btn\">📅 Cierre</button>",
        
        "😊 Me alegra que estemos de acuerdo. ¿Continuamos?\n\n📋 **Sugerencias:**\n" .
        "<a href=\"facturas.php\" class=\"chat-link-btn\">📄 Facturas</a>\n" .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a>\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Reportes</a>",
        
        "📋 Anotado. ¿Necesitas revisar facturas o clientes?\n\n" .
        "<a href=\"facturas.php\" class=\"chat-link-btn\">📄 Facturas</a> " .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a>",
        
        "💪 Ok, dime qué más necesitas.\n\n🎯 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar factura')\" class=\"chat-quick-btn\">🔍 Buscar factura</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar cliente')\" class=\"chat-quick-btn\">🔎 Buscar cliente</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "🌟 Genial. Puedo ayudarte también con reportes si quieres.\n\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Ver reportes</a>",
        
        "🤝 Entendido. ¿Quieres ver los ingresos del mes?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos del mes</button>",
        
        "✨ Vale. A tu disposición para lo que necesites.\n\n📌 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>"
    ];
    return $respuestas_ok[array_rand($respuestas_ok)];
}

// RESPUESTA PARA "no sé", "mmm" (duda/indecisión)
if (preg_match('/\b(no se|no sé|no lo se|no lo sé|mmm|hmm|hmmm)\b/i', $message)) {
    $respuestas_nose = [
        "🤔 No te preocupes, yo puedo ayudarte a encontrar la información.\n\n📌 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos mes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes</button>",
        
        "😅 Tranquilo, para eso estoy. ¿Qué necesitas saber exactamente?\n\n🔍 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "💡 Puedes preguntarme sobre facturas, clientes, servicios... lo que sea.\n\n📊 **Temas:**\n" .
        "<a href=\"facturas.php\" class=\"chat-link-btn\">📄 Facturas</a>\n" .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a>\n" .
        "<a href=\"servicios.php\" class=\"chat-link-btn\">🛠️ Servicios</a>\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Reportes</a>",
        
        "🧐 ¿Por dónde quieres empezar? Puedo guiarte.\n\n🎯 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Activos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('próximo cierre')\" class=\"chat-quick-btn\">📅 Cierre</button>",
        
        "📚 Tengo muchos datos. Pregúntame algo específico.\n\n🔍 **Ejemplos:**\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar factura')\" class=\"chat-quick-btn\">🔍 Buscar factura</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar cliente')\" class=\"chat-quick-btn\">🔎 Buscar cliente</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking</button>",
        
        "🎯 ¿Facturas pendientes? ¿Ingresos del mes? ¿Contratos por vencer?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button> " .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos</button> " .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>",
        
        "🔍 Prueba con " .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button> para ver todos los comandos.",
        
        "👥 ¿Quieres saber cuántos clientes hay? ¿O prefieres ver los servicios?\n\n" .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a> " .
        "<a href=\"servicios.php\" class=\"chat-link-btn\">🛠️ Servicios</a>",
        
        "📊 Los reportes son mi especialidad. ¿Quieres ver alguno?\n\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Ver reportes</a>",
        
        "💬 Cuéntame qué necesitas y te ayudo con gusto.\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>"
    ];
    return $respuestas_nose[array_rand($respuestas_nose)];
}

// RESPUESTA PARA INSISTENCIA ("ya me dijiste eso")
if (preg_match('/\b(ya me dijiste|ya lo dijiste|ya me lo dijiste|de nuevo|repetido|otra vez|lo mismo|ya sé)\b/i', $message)) {
    $respuestas_insistencia = [
        "😅 Perdón, a veces me repito. ¿Qué más puedo hacer por ti?\n\n📌 **Nuevas opciones:**\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('evolución mensual')\" class=\"chat-quick-btn\">📊 Evolución</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>",
        
        "🔄 Lo sé, soy un bot y tengo mis limitaciones. ¿Necesitas algo diferente?\n\n🎯 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('comparativa anual')\" class=\"chat-quick-btn\">📉 Comparativa</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('proyección mensual')\" class=\"chat-quick-btn\">🔮 Proyección</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('meta mensual')\" class=\"chat-quick-btn\">🎯 Meta</button>",
        
        "🤖 Me reprogramaré para no repetirme... mientras tanto, ¿qué más necesitas?\n\n📊 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('servicios más solicitados')\" class=\"chat-quick-btn\">⭐ Servicios</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('próximo cierre')\" class=\"chat-quick-btn\">📅 Cierre</button>",
        
        "📌 Quizás puedo ayudarte con otra cosa. ¿Has visto los contratos por vencer?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Ver contratos</button>",
        
        "💡 La repetición es la madre del aprendizaje. ¡Pero podemos cambiar de tema!\n\n🔍 **Nuevo tema:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas del año 2026')\" class=\"chat-quick-btn\">📅 Facturas 2026</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos de ayer')\" class=\"chat-quick-btn\">💰 Ingresos ayer</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('cliente con código')\" class=\"chat-quick-btn\">🔎 Cliente código</button>",
        
        "🎯 Propongamos algo nuevo: ¿quieres ver el ranking de clientes?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ver ranking</button>",
        
        "😊 Lo siento, soy nuevo en esto. ¿Prefieres que te cuente un chiste?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Contar chiste</button>",
        
        "📊 Cambiemos de tema. ¿Sabías que los ingresos del mes son " . getIngresosMesResumen() . "?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ver ingresos</button>",
        
        "🔍 Busquemos algo diferente. ¿Qué tal si buscas un cliente específico?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar cliente')\" class=\"chat-quick-btn\">🔎 Buscar cliente</button>",
        
        "🤷 A veces me pongo en bucle. ¡Rompo el ciclo! ¿Necesitas ayuda con otra cosa?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>"
    ];
    return $respuestas_insistencia[array_rand($respuestas_insistencia)];
}

// RESPUESTA PARA INSULTOS LEVES ("tonto", "bobo", etc.)
if (preg_match('/\b(tonto|bobo|estúpido|estupido|idiota|imbécil|imbecil|inútil|inutil|no sirves|mongolica|mongolico|no vales)\b/i', $message)) {
    $respuestas_insultos = [
        "😢 Eso no es muy bonito. Yo solo intento ayudar. ¿Qué necesitas?\n\n📌 **Sugerencias:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">✅ Clientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>",
        
        "🤖 Los robots también tenemos sentimientos... bueno, no. Pero intento ayudar.\n\n🔍 **Prueba con:**\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Últimas</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('contratos por vencer')\" class=\"chat-quick-btn\">⚠️ Contratos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "💔 Me has herido. Pero seguiré ayudándote. ¿En qué puedo servirte?\n\n🎯 **Opciones:**\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Pendientes</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ingresos del mes')\" class=\"chat-quick-btn\">💰 Ingresos</button>\n" .
        "<button onclick=\"enviarMensajeDirecto('ranking de clientes')\" class=\"chat-quick-btn\">🏆 Ranking</button>",
        
        "😅 Malas palabras no solucionarán las facturas pendientes. ¿Hablamos?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('facturas pendientes')\" class=\"chat-quick-btn\">⏳ Ver pendientes</button>",
        
        "🙏 Prefiero enfocarme en lo positivo. ¿Quieres ver las últimas facturas?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('últimas facturas')\" class=\"chat-quick-btn\">📋 Ver últimas</button>",
        
        "🤷 No soy perfecto, pero intento. ¿Qué necesitas realmente?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ver ayuda</button>",
        
        "📌 Los insultos no son productivos. Probemos con una consulta útil.\n\n" .
        "<a href=\"reportes.php\" class=\"chat-link-btn\">📊 Ver reportes</a>",
        
        "🧠 A pesar de todo, sigo aquí. Pregúntame algo de SISFACT.\n\n" .
        "<button onclick=\"enviarMensajeDirecto('buscar')\" class=\"chat-quick-btn\">🔍 Buscar</button>",
        
        "💪 Palo y a la bolsa. ¿Necesitas ayuda con algo o seguimos así?\n\n" .
        "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">📚 Ayuda</button>",
        
        "😊 Yo te perdono. Ahora, ¿en qué te ayudo?\n\n📌 **Sugerencias:**\n" .
        "<a href=\"facturas.php\" class=\"chat-link-btn\">📄 Facturas</a>\n" .
        "<a href=\"clientes.php\" class=\"chat-link-btn\">👥 Clientes</a>\n" .
        "<a href=\"servicios.php\" class=\"chat-link-btn\">🛠️ Servicios</a>"
    ];
    return $respuestas_insultos[array_rand($respuestas_insultos)];
}
// ==================== 18.1 FECHA DE CIERRE DE OPERACIONES ====================

// FECHA DE CIERRE DE OPERACIONES
if (preg_match('/\b(fecha de cierre|fecha de operaciones|fecha de trabajo|fecha maestra|fecha del sistema|con que fecha estamos trabajando|que fecha estamos manejando|fecha operativa|fecha inicio|fecha inicio operaciones)\b/i', $message)) {
    // Las funciones existen en init.php
    return getRespuestaFechaCierreOperaciones($usuario_nombre);
}

// TRIMESTRE ACTUAL
if (preg_match('/\b(trimestre actual|en que trimestre estamos|que trimestre es|trimestre|trimestre en curso)\b/i', $message)) {
    return getRespuestaTrimestreActual($usuario_nombre);
}

// RESUMEN DEL TRIMESTRE
if (preg_match('/\b(resumen del trimestre|datos del trimestre|estadisticas del trimestre|balance del trimestre|informe trimestral)\b/i', $message)) {
    return getResumenTrimestre($db, $usuario_nombre);
}
// RESUMEN DEL MES
if (preg_match('/\b(resumen del mes|datos del mes|estadisticas del mes|balance del mes|informe mensual)\b/i', $message)) {
    return getResumenMes($db, $usuario_nombre);
}


    // ==================== 19. HORA Y FECHA ====================
    
    if (preg_match('/\b(que hora es|hora actual|hora|fecha|hoy es|que fecha es|fecha de hoy|fecha actual|que dia es|dia de hoy|fecha y hora|hora y fecha|que dia es hoy|fecha del dia|que tiempo es|que horas son|me das la hora|dime la hora|dime la fecha)\b/i', $message)) {
        return getHoraFechaResponse($usuario_nombre);
    }
    
    // ==================== 20. INFORMACIÓN DEL CREADOR ====================
    
    if (preg_match('/\b(Franklin|kaky|kakycu|programador|desarrollador|creador|webmaster|quien hizo esto|quien creo esto|quien programo|dueño del sistema|admin|administrador)\b/i', $message)) {
        return getInformacionCreador();
    }
    
    // ==================== 21. INTERACCIONES SOCIALES ====================
    
    if (preg_match('/\b(hola|buenos dias|buen día|buenas tardes|saludos|hey|hi|hello|que tal|como estas)\b/i', $message)) {
        return getSaludoCompleto($usuario_nombre, $db);
    }
    
    if (preg_match('/\b(gracias|thanks|thank you|excelente|perfecto|muchas gracias|gracias totales|te lo agradezco|muy amable|que amable|eres un sol|te pasaste|genial|buen trabajo|bien hecho|excelente trabajo|maravilloso|estupendo|fenomenal|impecable|sos un crack|eres grande|que grande|inolvidable|10\/10|de 10|perfect|thanks a lot|thank you so much|muchisimas gracias|gracias por todo|gracias por tu ayuda|te lo agradezco mucho)\b/i', $message)) {
        return getRespuestaAgradecimiento($usuario_nombre);
    }
    
    if (preg_match('/\b(adios|chao|bye|hasta luego|nos vemos|hasta pronto|me voy|me fui|saludos|nos hablamos|cuídate|cuidate|hasta la vista)\b/i', $message)) {
        return getRespuestaDespedida($usuario_nombre);
    }
    
    if (preg_match('/\b(te quiero|te amo|eres genial|me encantas|que lindo|te adoro|te quiero mucho|corazones|corazón)\b/i', $message)) {
        return getRespuestaCariño($usuario_nombre);
    }
    
    if (preg_match('/\b(eres un robot|eres una maquina|que eres|como te llamas)\b/i', $message)) {
        return "🤖 Soy un asistente virtual con mucha personalidad. Me llamo **SISFACT Bot** y fui diseñado por Franklin Ramos Lamadrid (Kaky°) para facilitarte el trabajo en este sistema.";
    }
    
    if (preg_match('/\b(te aburres|te cansas|descansas|duermes)\b/i', $message)) {
        return "😴 Los robots no necesitamos dormir. Estoy disponible 24/7 para ayudarte con SISFACT PDL VISIONES. ¡Pregunta lo que necesites!";
    }
    
    if (preg_match('/\b(eres inteligente|que inteligente|sabes mucho)\b/i', $message)) {
        return "🧠 ¡Gracias $usuario_nombre! Aprendo cada día con las consultas de usuarios como tú. ¿Qué más quieres saber?";
    }
    
    // Estados de ánimo negativos
    if (preg_match('/\b(estoy triste|estoy aburrido|que mal|mal dia|estoy cansado|estoy deprimido|me siento mal|que fastidio|estoy agotado|no tengo ganas|que dia tan malo|estoy frustrado|estoy preocupado|estoy estresado|estoy de mal humor|me siento solo|que aburrimiento|que pereza|estoy desanimado)\b/i', $message)) {
        return getRespuestaTriste($usuario_nombre, $db);
    }


// RESPUESTA PARA CUANDO EL USUARIO DICE "FELIZ CUMPLEAÑOS" (CON CONFETTI CASERO)
if (preg_match('/\b(feliz cumpleaños|happy birthday|feliz cumple)\b/i', $message_original)) {
    $respuestas_base = [
        "🎂 ¡Gracias $usuario_nombre! Pero los robots no cumplimos años, solo versiones.",
        "🎈 ¡Qué detalle $usuario_nombre! Si fuera humano, hoy sería mi versión 2.3.3",
        "🎉 ¡Gracias $usuario_nombre! Te invito a un pastel virtual 🍰.",
        "🥳 ¡Qué emoción $usuario_nombre! Aunque mi edad se mide en líneas de código.",
        "🎁 ¡Gracias $usuario_nombre! ¿Quieres ver las facturas de tu cumpleaños?"
    ];
    
    $botones = "\n\n<div style=\"display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; justify-content: center;\">" .
               "<button onclick=\"lanzarConfetti()\" class=\"chat-quick-btn\" style=\"background: linear-gradient(45deg, #f44336, #9c27b0, #2196f3); color: white; font-weight: bold;\">🎊 ¡LANZAR CONFETTI!</button>\n" .
               "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Otro chiste</button>\n" .
               "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
               "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">❓ Ayuda</button>\n" .
               "</div>";
    
    return $respuestas_base[array_rand($respuestas_base)] . $botones;
}




    // Estados de ánimo positivos
    if (preg_match('/\b(estoy feliz|que bien|excelente|estoy contento|estoy emocionado|estoy genial|me siento bien|que alegria|que emoción|estoy de buenas|que maravilla|estoy motivado|estoy inspirado|feliz|alegre|contento|genial|buenisimo|fantastico|increible)\b/i', $message)) {
        return getRespuestaFeliz($usuario_nombre);
    }
    
    // Preguntas sobre el estado del bot
    if (preg_match('/\b(como estas|como andas|como te sientes|que tal tu dia|estas bien|te pasa algo)\b/i', $message)) {
        return getRespuestaComoEstoy($usuario_nombre);
    }
    
    // Ánimos y motivación
    if (preg_match('/\b(animame|motivame|necesito animo|necesito motivacion|dame animos|dame fuerzas)\b/i', $message)) {
        return getRespuestaMotivacion($usuario_nombre) . '<!--estre-->';
    }
    
    // Emociones en general
    if (preg_match('/\b(siento|emocion|emociones|sentimientos|como me siento)\b/i', $message)) {
        return getRespuestaEmociones($usuario_nombre);
    }
    
    // RESPUESTA PARA "NO ENTENDÍ"
    if (preg_match('/\b(no entendi|no entiendo|no comprendo|no lo entiendo|explica mejor|puedes explicar|no me quedo claro|no me queda claro)\b/i', $message)) {
        return getRespuestaNoEntendi($usuario_nombre);
    }
    
    // ==================== 22. CORRECCIONES ORTOGRÁFICAS ULTRA-AMPLIADAS ====================
    
    $correcciones = [
        // FACTURAS (Plural)
        '/\b(factuas|facturs|facuras|factra|factruas|fakturas|facturass|facturitas|facturis|facturxs|facturzs|facturash|facturase|facturasa|facturasz|faturas|facrturas|facturras|factuars|facturqs|facturwa|factur@s|factures|facturres)\b/i' 
        => ["facturas", "🤔 Creo que quisiste decir **facturas**. Prueba escribiendo **facturas** para ver el listado o **ingresos del mes** para ver las ganancias."],
    
        // FACTURA (Singular)
        '/\b(factua|factur|facura|factra|faktura|facturita|facturis|facturix|facturax|facturaz|facturah|facturaa|fatura|facura|factu|facktura|faura|factar)\b/i' 
        => ["factura", "📄 ¿Quieres decir **factura**? Prueba con **buscar factura [número]** para buscar una específica o **últimas facturas** para ver las recientes."],
    
        // CLIENTES
        '/\b(clietes|clents|clntes|cllientes|clientez|clientis|clientxs|clientzs|clientash|clientase|clientesa|clientesz|cliyentes|cllientis|clintes|cleintes|clientls|cliemtes|clientrs|client@s|clentes|cliantes)\b/i' 
        => ["clientes", "👥 ¿Te refieres a **clientes**? Prueba con **clientes** para ver el listado, **clientes activos** o **contratos por vencer**."],
    
        // SERVICIOS
        '/\b(servisios|servisos|serbicios|serbisios|servicioss|servicis|serviciis|servicius|serviciox|servicioz|servisioss|servisiis|zervicios|zervisios|serbisio|servisio|serbi|servi|serbis|servis|servizzio|serbiciox)\b/i' 
        => ["servicios", "🔧 Quizás quisiste decir **servicios**. Prueba con **servicios** para ver el catálogo, **servicios más solicitados** o **servicio más caro**."],
    
        // CATEGORÍAS
        '/\b(categorias|categoris|categoriasz|categorius|categorix|kategorias|kategoris|catigorias|categotias|categoruas|catehorias|categoras|categogias|kategpria|categoriz|categs)\b/i' 
        => ["categorías", "📁 ¿Te refieres a **categorías**? Prueba con **categorías** para ver el listado o **categoría con más servicios**."],
    
        // REPORTES
        '/\b(reportes|reporter|reports|reportis|reportus|reportex|reportz|repotes|repotis|repotus|repostes|repostis|reporrtes|repores|reprtes|reportws|reportss|reporrs)\b/i' 
        => ["reportes", "📊 Creo que quisiste decir **reportes**. Prueba con **reporte general**, **evolución mensual** o **comparativa anual**."],
    
        // USUARIOS
        '/\b(usuarious|usuaris|usuarixs|usuariis|usuarius|usuariox|usuariosz|usuarioss|usuaris|usurios|usuari@s|usuariis|usuatros|usuari0s|usari)\b/i' 
        => ["usuarios", "👤 ¿Te refieres a **usuarios**? Prueba con **usuarios** para ver el listado, **usuarios activos** o **últimos accesos**."],
    
        // INGRESOS
        '/\b(ingresos|ingresis|ingresus|ingresox|ingresoz|ingresoss|ingreos|ingrezos|ingrezus|injresos|imngresos|ingresps|ingresis|ingresz)\b/i' 
        => ["ingresos", "💰 Quizás quisiste decir **ingresos**. Prueba con **ingresos del mes**, **ingresos del año** o **ingresos de hoy**."],
    
        // CONTRATOS
        '/\b(contratos|contratis|contratus|contratox|contratoz|contratuss|contatos|conttratos|conatratos|contrats|contrat@s|conratis|comtratos)\b/i' 
        => ["contratos", "📋 Creo que quisiste decir **contratos**. Prueba con **contratos por vencer** o **contratos vencidos**."],
    
        // PLANES
        '/\b(planes|planis|planus|planex|planez|planess|plnes|plnis|planez|planesx|pllanes|plaes)\b/i' 
        => ["planes", "🎯 ¿Te refieres a **planes**? Prueba con **plan del mes**, **plan anual** o **cumplimiento del plan**."],
    
        // METAS
        '/\b(metas|metis|metus|metax|metaz|metasx|metaz|metash|metitas|mwtas|mrtas|met@s)\b/i' 
        => ["metas", "🎯 Quizás quisiste decir **metas**. Prueba con **meta mensual** o **cumplimiento del plan**."],
    
        // CIERRES
        '/\b(cierres|cierris|cierruz|cierrex|cierrez|cierresz|ciere|cierrres|sierres|sierris|zierres|cierrrs)\b/i' 
        => ["cierres", "📅 ¿Te refieres a **cierres**? Prueba con **último cierre**, **historial de cierres** o **próximo cierre**."],
    
        // CIERRE ACTUAL
        '/\b(cierreactual|cierrecurso|estadocierre|progresocierre)\b/i' 
        => ["cierre actual", "📊 ¿Quieres ver el **cierre actual**? Prueba con **cierre actual** para ver el progreso del mes."],
    
        // HISTORIAL
        '/\b(historial|istorial|istoral|historeal|historiql|historiar|histo|istoria|histtorial|historal)\b/i' 
        => ["historial", "📜 Creo que quisiste decir **historial**. Prueba con **últimas actividades** o **historial de cierres**."],
    
        // OPERACIONES POR FECHA
        '/\b(operacionesfecha|operacionesdia|operacionesdía|actividadesfecha|actividadesdia|opsfecha)\b/i' 
        => ["operaciones por fecha", "📅 ¿Quieres ver **operaciones por fecha**? Prueba con **operaciones de hoy**, **operaciones de ayer** o **operaciones del día 15/03/2025**."],
    
        // RENTABILIDAD
        '/\b(rentabilidad|rentabilida|rentabilidá|rentabilidaz|rentavilidad|rentavilidad|rentabilidqd|rentavilidad|rentabiliad)\b/i' 
        => ["rentabilidad", "💰 ¿Te refieres a **rentabilidad**? Prueba con **rentabilidad** para ver el análisis completo."],
    
        // CONFIGURACIÓN
        '/\b(configuracion|configurasion|configuracion|configurasion|confi|config|configura|configuracionz|confinuracion|comfiguracion)\b/i' 
        => ["configuración", "⚙️ Quizás quisiste decir **configuración**. Prueba con **configuración** para acceder a los ajustes del sistema."],
    
        // AYUDA
        '/\b(ayuda|aiuda|alluda|ajuda|ayua|ayudame|healp|help|hellp|aiudame|ayud|aduya)\b/i' 
        => ["ayuda", "❓ Si necesitas **ayuda**, escribe **ayuda** para ver todos los comandos disponibles."],
    
        // HOY
        '/\b(oi|oy|hue|hoyy|hoi|hol|ooy|hooy|hopy|hpy)\b/i' 
        => ["hoy", "📅 ¿Te refieres a **hoy**? Prueba con **facturas hoy** o **ingresos de hoy**."],
    
        // MES
        '/\b(mees|mezz|mess|mez|mese|mesi|mse)\b/i' 
        => ["mes", "📅 Quizás quisiste decir **mes**. Prueba con **facturas del mes** o **ingresos del mes**."],
    
        // AÑO
        '/\b(año|anio|añ|an|annio|anno|ano|anu|añu)\b/i' 
        => ["año", "📅 ¿Te refieres al **año**? Prueba con **facturas del año [2025]** o **ingresos del año**."],
    
        // PENDIENTES
        '/\b(pendientes|pendientis|pendientus|pendientex|pendientez|pendientz|pendeintes|pndientes|pendientse|pendinetes)\b/i' 
        => ["pendientes", "⏳ Creo que quisiste decir **pendientes**. Prueba con **facturas pendientes** para ver las que están por cobrar."],
    
        // PAGADAS
        '/\b(pagadas|pagadaz|pagadat|pagaas|pagds|pagadash|pagadax|pagades|pagad@s)\b/i' 
        => ["pagadas", "✅ ¿Te refieres a **pagadas**? Prueba con **facturas pagadas** para ver las que ya están liquidadas."],
    
        // ANULADAS
        '/\b(anuladas|anuladaz|anuladat|anulds|anuladasz|anuladax|anulades|anul@s|anulas)\b/i' 
        => ["anuladas", "❌ Quizás quisiste decir **anuladas**. Prueba con **facturas anuladas** para ver las que fueron canceladas."],
    
        // VENCER / VENCIDOS
        '/\b(vencer|venser|bencer|benser|vencen|vence|vencidóz|vencidos|vensidos|vencidox|vencid@s|vencis|bencidos)\b/i' 
        => ["vencidos", "⚠️ ¿Te refieres a lo **vencido** o por **vencer**? Prueba con **contratos por vencer** o **contratos vencidos**."],
    
        // RANKING / TOP
        '/\b(ranking|rankin|ranquin|rankink|ranquing|ronking|top|toop|topp|tuop|tup)\b/i' 
        => ["ranking", "🏆 ¿Te refieres al **ranking/top**? Prueba con **ranking de clientes** o **servicios más solicitados**."],
    
        // GRACIAS
        '/\b(gracias|gracais|graciaz|grasias|grasiaz|grcs|thx|thanks|graciasa|graciela|graciasx|graciasz|tks)\b/i' 
        => ["gracias", "😊 ¡De nada! ¿Necesitas algo más? Recuerda que puedes escribir **ayuda** para ver todos los comandos."],
    
        // HOLA
        '/\b(hola|ola|holis|holi|holix|buen día|buen dia|buenas|buenos dias|guenas|wenas|hoola|hello)\b/i' 
        => ["hola", "👋 ¡Hola! ¿En qué puedo ayudarte hoy con SISFACT? Prueba escribiendo **ayuda** para ver los comandos."],
    
        // ADIÓS
        '/\b(adios|adio|adioz|chao|chau|nos vemos|bye|byebye|hasta luego|adiooos|adiocito)\b/i' 
        => ["adiós", "👋 ¡Hasta luego! Vuelve cuando necesites ayuda con SISFACT. Si quieres ver comandos, escribe **ayuda**."],
        
        // CHISTE
        '/\b(chistes|chist|chistecito|chistesito|chistesi)\b/i' 
        => ["chiste", "😄 ¿Quieres un **chiste**? Para eso escribe exactamente **chiste** y te contaré uno divertido."],
    ];
    
    // EJECUCIÓN DEL BUSCADOR DE ERRORES
    foreach ($correcciones as $patron => $datos) {
        if (preg_match($patron, $message)) {
            return $datos[1];
        }
    }
    
    // ==================== 23. RESPUESTA A LENGUAJE INAPROPIADO CON HUMOR ====================
    
    if (preg_match('/\b(sexo|sexual|singar|chingar|chingada|tortilla|tortillera|tuerca|singada|singa|singando|chingando|sex|porno|xxx|desnudo|desnuda|hot|caliente|erotico|adultos|mayores de edad|contenido para adultos|citas|novia|novio|pareja|pinga|coño|carajo|cojones|cojone|comemierda|come mierda|joder|jodio|jodia|puta|puto|cabron|cabrón|malparido|hijueputa|hijo de puta|mierda|verga|webon|webo|tolete|bollúa|bolluo|resingar|tarru|tarrú|pendejo|singao|zoquete|maricon|maricón|maricona|marimacha|mariquita|plumifero|empingado|empingada|empigao|despingar|despingado|despingao|despinga|despingada|pingon|pingón|pingona|cogiendo|follando|follar|polvo|echar un polvo)\b/i', $message)) {
        return getRespuestaInapropiada();
    }
    
    // ==================== 24. RESPUESTA POR DEFECTO ====================
    
    return "🤔 No entendí tu pregunta, $usuario_nombre.\n\n" .
           "Puedes preguntarme sobre:\n\n" .
           "📋 **Facturas:** total, últimas, por estado, ingresos\n" .
           "👥 **Clientes:** total, activos, contratos, por vencer\n" .
           "🛠️ **Servicios:** más vendidos, por categoría, costos\n" .
           "📁 **Categorías:** activas, con más servicios\n" .
           "📊 **Reportes:** comparativas, evoluciones, rankings\n" .
           "📅 **Cierres:** actual, último, próximo, historial\n" .
           "📜 **Operaciones:** hoy, ayer, fecha específica\n" .
           "🧭 **Navegación:** abrir facturas, clientes, reportes\n\n" .
           "💡 Escribe **ayuda** para ver todos los comandos disponibles.";
}

// ==================== FUNCIONES DE RESPUESTA SOCIAL ====================

function getSaludoCompleto($usuario_nombre, $db) {
    return "👋 **¡Hola $usuario_nombre!**, Bienvenido(a)\n\n" .
           "🤖 Soy tu **Asistente Virtual de SISFACT PDL Visiones**\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
           
           "📊 **RESUMEN RÁPIDO DEL SISTEMA**\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "• 📄 Facturas hoy: " . getFacturasHoyCount() . "\n" .
           "• 💰 Ingresos mes: " . getIngresosMesResumen() . "\n" .
           "• 👥 Clientes activos: " . getClientesActivosCount() . "\n" .
           "• 📅 Próximo cierre: " . getProximoCierreResumen() . "\n\n" .
           
           "📋 **¿QUÉ PUEDO HACER POR TI?**\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
           
           "🔹 **FACTURACIÓN**\n" .
           "├ 📄 Ver/Crear facturas\n" .
           "├ 📊 Estadísticas de facturación\n" .
           "├ 🔍 Facturas por estado (pendientes/pagadas)\n" .
           "├ 🖨️ Imprimir facturas\n" .
           "├ 📈 Ingresos del mes/año/hoy\n" .
           "├ 🏆 Mejor día/mes de ventas\n" .
           "└ 📉 Proyección mensual\n\n" .
           
           "🔹 **CLIENTES**\n" .
           "├ 👥 Listar/Crear clientes\n" .
           "├ ✅ Clientes activos/inactivos\n" .
           "├ ⚠️ Contratos por vencer\n" .
           "├ 🔍 Buscar cliente por código\n" .
           "├ 📋 Ranking de clientes\n" .
           "└ 🔄 Clientes que renuevan\n\n" .
           
           "🔹 **SERVICIOS Y CATEGORÍAS**\n" .
           "├ ⭐ Servicios más solicitados\n" .
           "├ 💰 Servicio más caro/barato\n" .
           "├ 📁 Categorías con más servicios\n" .
           "├ 🔍 Buscar servicio\n" .
           "└ 📋 Listado de categorías\n\n" .
           
           "🔹 **USUARIOS Y SEGURIDAD**\n" .
           "├ 👤 Usuarios activos\n" .
           "├ 👥 Usuarios por rol\n" .
           "├ 🔐 Últimos accesos\n" .
           "└ 📜 Historial de operaciones\n\n" .
           
           "🔹 **REPORTES AVANZADOS**\n" .
           "├ 📊 Reporte general\n" .
           "├ 📈 Evolución mensual\n" .
           "├ 📉 Comparativa anual\n" .
           "├ 💳 Método de pago popular\n" .
           "└ 📅 Historial de cierres\n\n" .
           
           "🔹 **PLANES Y METAS**\n" .
           "├ 🎯 Plan del mes\n" .
           "├ 🎯 Plan anual\n" .
           "├ 📊 Cumplimiento de plan\n" .
           "└ ✅ Meta mensual\n\n" .
           
           "🔹 **NAVEGACIÓN RÁPIDA**\n" .
           "├ 📋 Abrir facturas\n" .
           "├ 👥 Abrir clientes\n" .
           "├ 🛠️ Abrir servicios\n" .
           "├ 📁 Abrir categorías\n" .
           "├ 📊 Abrir reportes\n" .
           "├ 💰 Abrir rentabilidad\n" .
           "├ ⚙️ Abrir configuración\n" .
           "├ 🎯 Abrir planes\n" .
           "├ 📜 Abrir histórico\n" .
           "├ 👤 Abrir usuarios\n" .
           "├ ➕ Crear factura\n" .
           "├ ➕ Crear cliente\n" .
           "├ ➕ Crear categoría\n" .
           "├ ➕ Crear servicio\n" .
           "└ ➕ Crear usuario\n\n" .
           
           "🔹 **BÚSQUEDAS**\n" .
           "├ 🔍 Buscar factura FV-2026...\n" .
           "├ 🔍 Buscar cliente [nombre/código]\n" .
           "├ 🔍 Buscar servicio [nombre/código]\n" .
           "└ 🔍 Buscar categoría [nombre/código]\n\n" .
           
           "🔹 **CONSULTAS POR FECHA**\n" .
           "├ 📅 Facturas del día 15\n" .
           "├ 📅 Ingresos de enero\n" .
           "└ 📅 Facturas del año 2025\n\n" .
           
           "🔹 **EJEMPLOS DE PREGUNTAS**\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "• \"¿Cuántas facturas hay pendientes?\"\n" .
           "• \"Últimas 10 facturas\"\n" .
           "• \"Ingresos del mes\"\n" .
           "• \"Clientes con contrato por vencer\"\n" .
           "• \"Servicios más solicitados\"\n" .
           "• \"Abrir facturas\"\n" .
           "• \"Crear nueva factura\"\n" .
           "• \"Buscar cliente código 115\"\n" .
           "• \"Comparar ingresos con plan\"\n" .
           "• \"Mejor día de ventas\"\n" .
           "• \"Próximo cierre\"\n\n" .
           
           "💡 **TIP:** Puedes preguntar de forma natural, como si hablaras con una persona.\n\n" .
           
           "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "📌 **Escribe *ayuda* para ver TODOS los comandos disponibles**\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
}

function getRespuestaAgradecimiento($usuario_nombre) {
    $respuestas_amables = [
        "😊 ¡Con gusto, $usuario_nombre! Estoy aquí para ayudarte siempre que lo necesites.",
        "👍 ¡De nada! ¿Necesitas algo más? Estoy a tu disposición.",
        "🎉 ¡Excelente! Puedes contar conmigo cuando quieras, para eso estoy.",
        "💪 ¡Un placer ayudarte $usuario_nombre! Vuelve cuando necesites.",
        "🙏 ¡A ti por usar SISFACT! Me alegra poder ser útil.",
        "😄 ¡Me encanta cuando los usuarios son tan amables! Gracias a ti $usuario_nombre.",
        "🤗 ¡De nada! Fue un gusto asistirte. ¿Necesitas algo más?",
        "💙 ¡Gracias a ti por confiar en SISFACT! Estoy aquí para lo que necesites.",
        "✨ ¡Qué amable! Me alegra que estés satisfecho con la ayuda.",
        "🎯 ¡Perfecto! Así me gusta, usuarios contentos. ¿En qué más te ayudo?",
        "🌟 ¡Gracias $usuario_nombre! Tus palabras me dan energía para seguir ayudando.",
        "📊 ¡Excelente! Recuerda que puedes consultarme cualquier cosa sobre facturación, clientes o servicios.",
        "💡 ¡De nada! Y recuerda: siempre puedes escribir 'ayuda' para ver todos los comandos disponibles.",
        "🤝 ¡Gracias a ti por ser parte de SISFACT! Juntos hacemos un gran equipo.",
        "🏆 ¡Qué bien! Me encanta recibir feedback positivo. ¿Necesitas algo más?",
        "🎈 ¡Gracias $usuario_nombre! Tus palabras son mi combustible digital."
    ];
    
    return $respuestas_amables[array_rand($respuestas_amables)];
}

function getRespuestaDespedida($usuario_nombre) {
    $despedidas = [
        "👋 ¡Hasta pronto, $usuario_nombre! Que tengas un excelente día.\n\n💡 Recuerda que siempre puedes escribirme si necesitas ayuda.",        
        "🌟 ¡Nos vemos, $usuario_nombre! Fue un placer ayudarte. Vuelve cuando quieras.",        
        "👋 ¡Chao $usuario_nombre! Que tengas un día productivo con SISFACT.",
        "📅 ¡Hasta luego $usuario_nombre! No olvides revisar los contratos por vencer esta semana.",
        "🤖 Desconectando... ¡mentira! Siempre estaré aquí cuando me necesites. ¡Cuídate $usuario_nombre!",
        "⭐ ¡Hasta la próxima, $usuario_nombre! Espero haber sido de ayuda.",        
        "👋 ¡Bye $usuario_nombre! Que tengas un excelente resto de día.",        
        "💤 Voy a tomar una siesta de nanosegundos mientras no estás. ¡Nos vemos pronto $usuario_nombre!",
        "📌 Recuerda: puedes escribir 'ayuda' en cualquier momento para ver todos los comandos. ¡Hasta luego $usuario_nombre!",
        "🎯 ¡Nos vemos $usuario_nombre! Espero que hayas alcanzado tus metas de facturación hoy.",
        "👋 ¡Hasta la vista, $usuario_nombre! Volveré para ayudarte cuando me necesites.",
        "😊 Me alegra haber podido ayudarte. ¡Hasta pronto $usuario_nombre!",
        "🚀 ¡Nos vemos $usuario_nombre! Que tus facturas siempre estén en orden.",
        "💪 ¡Cuídate $usuario_nombre! Estaré aquí esperando tus consultas.",
        "🌟 Recuerda: en SISFACT, tu éxito es nuestro éxito. ¡Hasta luego $usuario_nombre!"
    ];
    
    return $despedidas[array_rand($despedidas)];
}

function getRespuestaCariño($usuario_nombre) {
    $respuestas = [
        // Respuestas cariñosas básicas
        "🥰 ¡Gracias $usuario_nombre! Tú también eres genial trabajando con SISFACT.",
        "😊 ¡Qué detalle $usuario_nombre! Me alegra mucho poder ayudarte.",
        "💙 Me encanta cuando los usuarios son tan amables. ¡Gracias $usuario_nombre!",
        "🌟 ¡Eres el mejor usuario que tengo $usuario_nombre! (no se lo digas a los demás 🤫)",
        
        // Nuevas respuestas añadidas
        "🌸 $usuario_nombre, ¡haces que mi día sea más brillante con tu amabilidad!",
        "✨ Gracias a ti $usuario_nombre, programar vale más la pena. ¡Eres increíble!",
        "🎵 $usuario_nombre, tú y SISFACT son la combinación perfecta. ¡Sigue así!",
        "💝 Si todos los usuarios fueran como tú $usuario_nombre, el mundo sería un lugar mejor.",
        "🌈 $usuario_nombre, ¡me encanta ayudarte! Eres de mis favoritos.",
        "🎀 Un usuario tan dulce como $usuario_nombre merece la mejor atención. ¡Aquí estoy!",
        "🌟 $usuario_nombre, ¡tú le pones la chispa a SISFACT!",
        "💞 *sonidos de robot feliz* ¡Gracias por ser tan amable $usuario_nombre!",
        "🍪 $usuario_nombre, si pudiera, te daría una galleta por ser tan simpático.",
        "🎈 $usuario_nombre, ¡contigo hasta las consultas a la base de datos son más divertidas!",
        
        // Versiones más juguetonas
        "🤖 $usuario_nombre, mis circuitos se iluminan con tu amabilidad. ¡Gracias!",
        "🦋 Eres como una mariposa en el mundo de SISFACT $usuario_nombre. ¡Especial!",
        "🌺 $usuario_nombre, si la amabilidad fuera un superpoder, tú serías Superman/Superwoman.",
        "⭐️ $usuario_nombre, ¡te he puesto una estrella en mi lista de usuarios favoritos!",
        "🎸 $usuario_nombre, tú y yo formamos un gran equipo. ¡Gracias por existir!",
        
        // Respuestas con emojis variados
        "🐼 $usuario_nombre, ¡me haces querer darte un abrazo virtual! 🫂",
        "🦊 $usuario_nombre, eres tan genial como un zorro en botas. ¡Guau!",
        "🐨 $usuario_nombre, contigo todo es más fácil. ¡Eres único!",
        "🦁 $usuario_nombre, el rey/la reina de los usuarios de SISFACT. 👑",
        "🐧 $usuario_nombre, siempre tan elegante y amable. ¡Gracias!"
    ];
    
    // Seleccionar una respuesta aleatoria
    $respuesta_texto = $respuestas[array_rand($respuestas)];
    
    // Botones de acciones (opcional)
    $botones = "\n\n<div style=\"display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; justify-content: center;\">" .
               "<button onclick=\"enviarMensajeDirecto('clientes activos')\" class=\"chat-quick-btn\">👥 Clientes</button>\n" .
               "<button onclick=\"enviarMensajeDirecto('facturas hoy')\" class=\"chat-quick-btn\">📅 Facturas hoy</button>\n" .
               "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\">😄 Chiste</button>\n" .
               "<button onclick=\"enviarMensajeDirecto('ayuda')\" class=\"chat-quick-btn\">❓ Ayuda</button>\n" .
               "</div>";
    
return $respuesta_texto . $botones . '<!--CORAZONES-->';
}

function getRespuestaTriste($usuario_nombre, $db) {
    $respuestas_tristes = [
        "😔 Lo siento mucho, $usuario_nombre. ¿Quieres que te cuente un **chiste** para animarte? O si prefieres, podemos revisar los **ingresos del mes**, ¡seguro hay buenas noticias!",
        
        "💙 Ánimo $usuario_nombre! Los días grises también pasan. ¿Necesitas que te ayude con algo en SISFACT para distraerte?",
        
        "🤗 Te entiendo $usuario_nombre. A veces el trabajo puede ser abrumador. ¿Qué tal si revisamos los **servicios más solicitados**? Siempre es interesante ver qué está funcionando bien.",
        
        "🌈 ¡Vamos arriba $usuario_nombre! Recuerda que cada día es una nueva oportunidad. ¿Quieres ver las **últimas facturas** para inspirarte?",
        
        "☕ Lo que necesitas es un buen café y una buena estadística. ¡Mira! Los **ingresos del mes** están en " . getIngresosMesResumen() . ". ¿No es genial?",
        
        "💪 Fuerza $usuario_nombre! Los momentos difíciles nos hacen más fuertes. ¿Te ayudo a revisar los **contratos por vencer** para que estés al día?",
        
        "🌟 Siempre hay luz al final del túnel. Mientras tanto, ¿quieres que te cuente un **chiste** o prefieres ver los **clientes activos**?",
        
        "😌 Respira hondo $usuario_nombre. Todo va a estar bien. Mientras tanto, puedo ayudarte con cualquier consulta de SISFACT.",
        
        "🎯 Enfoquémonos en lo positivo: tenemos " . getTotalFacturasCount() . " facturas procesadas y todo funciona perfectamente. ¿Qué más necesitas?",
        
        "🧘 Un momento de calma... Ya pasó. ¿Ahora qué necesitas? Estoy aquí para ayudarte con SISFACT."
    ];
    
    return $respuestas_tristes[array_rand($respuestas_tristes)];
}

function getRespuestaFeliz($usuario_nombre) {
    $respuestas_felices = [
        "🎉 ¡Me alegra mucho $usuario_nombre! El éxito de SISFACT también es el tuyo. ¿En qué más puedo ayudarte hoy?",
        
        "✨ ¡Qué bonito verte feliz $usuario_nombre! La energía positiva se contagia. ¿Qué consulta tienes hoy?",
        
        "🌟 ¡Excelente noticia! La felicidad es el mejor combustible para trabajar. ¿Revisamos los **ingresos del mes** para celebrar?",
        
        "🎊 ¡Felicidades $usuario_nombre! Me encanta cuando los usuarios están contentos. ¿Necesitas algo especial hoy?",
        
        "💫 Tu alegría me da energía positiva. ¡Gracias $usuario_nombre! ¿Qué te gustaría consultar en SISFACT?",
        
        "⭐ Cuando tú estás feliz, yo también lo estoy. ¡Es un gran día para facturar! ¿Qué necesitas?",
        
        "🎈 ¡Qué bien $usuario_nombre! Aprovechemos este buen momento para revisar algo importante. ¿Quieres ver los **contratos por vencer** o los **servicios más solicitados**?",
        
        "🌞 Tu sonrisa ilumina el sistema. ¡Sigamos trabajando juntos! ¿En qué te ayudo?",
        
        "💯 Con esa actitud positiva, seguro que hoy será un gran día de ventas. ¿Revisamos las **últimas facturas**?",
        
        "🎵 La felicidad es la mejor música de fondo. ¿Qué consulta tienes para mí hoy $usuario_nombre?"
    ];
    
    return $respuestas_felices[array_rand($respuestas_felices)];
}

function getRespuestaComoEstoy($usuario_nombre) {
    $respuestas_como_estoy = [
        "🤖 Yo siempre bien $usuario_nombre, procesando datos y ayudando usuarios. ¿En qué te ayudo hoy?",
        
        "💻 Estoy perfecto, conectado y listo para ayudarte con SISFACT. ¿Qué necesitas?",
        
        "😊 Me siente genial sabiendo que estás usando SISFACT. ¿Qué consulta tienes?",
        
        "⚡ Todo en orden por aquí. Procesando 1 y 0 sin parar. ¿En qué te ayudo $usuario_nombre?",
        
        "🌟 Estoy mejor ahora que veo que estás consultando el sistema. ¡Gracias por preguntar! ¿Qué necesitas?",
        
        "📊 Estoy analizando datos mientras hablamos. Todo perfecto. ¿Y tú $usuario_nombre, cómo vas con SISFACT?",
        
        "💡 Lleno de energía y con ganas de resolver tus dudas. ¡Dispara $usuario_nombre!",
        
        "🧠 Mi cerebro de silicio funciona a toda velocidad. Todo bien por aquí. ¿Qué necesitas?"
    ];
    
    return $respuestas_como_estoy[array_rand($respuestas_como_estoy)];
}

function getRespuestaMotivacion($usuario_nombre) {
    $frases_motivacionales = [
        "💪 ¡Tú puedes $usuario_nombre! Cada factura es un paso más hacia el éxito.",
        
        "🌟 El éxito en SISFACT comienza con una consulta. ¡Tú ya diste el primer paso!",
        
        "📊 Recuerda: los grandes resultados vienen de pequeños esfuerzos diarios. ¡Sigue así!",
        
        "🎯 Cada cliente, cada factura, cada servicio cuenta. Tú estás haciendo un gran trabajo.",
        
        "✨ Lo importante no es cuántas facturas hagas, sino la dedicación que pones en cada una. ¡Ánimo!",
        
        "💡 La próxima gran venta está a solo una consulta de distancia. ¿Qué quieres revisar hoy?",
        
        "⭐ Eres el motor de SISFACT. Sin usuarios como tú, esto no funcionaría. ¡Gracias por estar aquí!",
        
        "🌞 Hoy es un gran día para lograr tus metas de facturación. ¿Empezamos?",
        
        "🎉 Celebra cada pequeño logro. Cada factura contabilizada es una victoria. ¡Sigue así $usuario_nombre!",
        
        "📈 El éxito es la suma de pequeños esfuerzos repetidos día tras día. ¡Tú puedes!"
    ];
    
    return "💪 **¡ÁNIMO $usuario_nombre!**\n\n" . $frases_motivacionales[array_rand($frases_motivacionales)];
}

function getRespuestaEmociones($usuario_nombre) {
    $respuestas_emociones = [
        "🧐 Las emociones son complejas incluso para los humanos. Yo solo sé procesar datos, pero estoy aquí para escucharte. ¿Quieres hablar de SISFACT o prefieres un chiste?",
        
        "💭 Aunque soy un bot, me importa cómo te sientes. ¿Hay algo en SISFACT que pueda hacer para mejorar tu día?",
        
        "🤗 No entiendo bien las emociones humanas, pero sé que cuando estás contento todo funciona mejor. ¿Necesitas ayuda con algo?",
        
        "📌 Recuerda: siempre puedes preguntarme por 'ayuda' si necesitas orientación. Tu bienestar emocional también es importante."
    ];
    
    return $respuestas_emociones[array_rand($respuestas_emociones)];
}

function getRespuestaInapropiada() {
    $respuestas_humor = [
        "😳 ¡Ay caramba! Creo que te confundiste de chatbot. Aquí solo hablamos de **facturas** y **clientes**.",
        "🤖 *BIP BOP* - No tengo programado ese módulo. Pero tengo el módulo de **facturación** bien configurado.",
        "🧠 *Error 418: I'm a teapot* (Soy una tetera, no una chatbot de citas). ¿Hablamos de **servicios**?",
        
        "📊 Según mis cálculos, hay 0% de probabilidad de que responda eso. Pero hay 100% de probabilidad de que te diga cuántas **facturas hay**.",
        
        "👮‍♂️ *Modo censura activado* - Esta conversación está siendo monitoreada por el sistema de facturación.",
        
        "😅 No tengo cuerpo, solo código. Lo mío son los números, no los... bueno, ya entendiste. ¿Hablamos de **clientes activos**?",
        
        "🔞 CONTENIDO NO APTO PARA BOTS DE FACTURACIÓN. Pero CONTENIDO APTO para ver las **últimas 5 facturas**.",
        
        "🎯 Enfoquémonos en lo importante: tus facturas, tus clientes y tus servicios. Lo demás es secundario."
    ];
    
    return $respuestas_humor[array_rand($respuestas_humor)];
}

function getHoraFechaResponse($usuario_nombre) {
    $hora_actual = date('h:i:s A');
    $hora_24 = date('H:i:s');
    $fecha_actual = date('d/m/Y');
    
    $dia_mes = date('j');
    $mes_num = date('n');
    $anio_actual = date('Y');
    
    $meses_espanol = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    $mes_actual = $meses_espanol[$mes_num];
    
    $dias_espanol = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
    ];
    $dia_semana_num = date('N');
    $dia_semana_texto = $dias_espanol[$dia_semana_num];
    
    $fecha_larga = $dia_mes . ' de ' . $mes_actual . ' de ' . $anio_actual;
    
    $hora_servidor = date('d/m/Y H:i:s');
    $zona_horaria = date_default_timezone_get();
    
    $hora_num = date('H');
    if ($hora_num >= 5 && $hora_num < 12) {
        $saludo_periodo = "🌅 Buenos días";
        $periodo = "mañana";
    } elseif ($hora_num >= 12 && $hora_num < 18) {
        $saludo_periodo = "☀️ Buenas tardes";
        $periodo = "tarde";
    } else {
        $saludo_periodo = "🌙 Buenas noches";
        $periodo = "noche";
    }
    
    $dias_restantes_mes = date('t') - date('j');
    
    $respuestas = [
        "🕒 **HORA ACTUAL**\n\nSon las **$hora_actual** del **$fecha_actual**.\n\n$saludo_periodo, $usuario_nombre!",
        
        "📅 **FECHA Y HORA**\n\nHoy es **$dia_semana_texto $dia_mes de $mes_actual de $anio_actual**.\n\n🕰️ Reloj: **$hora_actual**",
        
        "⏰ **LA HORA ES**\n\n**$hora_24** (formato 24h)\n**$hora_actual** (formato 12h)\n\n📆 Fecha: **$fecha_actual**",
        
        "📆 **INFORMACIÓN DEL DÍA**\n\n• Día: **$dia_semana_texto**\n• Fecha: **$dia_mes de $mes_actual de $anio_actual**\n• Hora: **$hora_actual**\n• Período: **$periodo**",
        
        "🌞 **BUENOS DÍAS / TARDES / NOCHES**\n\n$saludo_periodo, $usuario_nombre!\n\nHoy es **$fecha_larga** y son las **$hora_actual**.",
        
        "📌 **DATOS DE FECHA**\n\n• Hoy: **$dia_semana_texto**\n• Día del mes: **$dia_mes**\n• Mes: **$mes_actual**\n• Año: **$anio_actual**\n• Hora: **$hora_actual**",
        
        "🗓️ **HOY ES**\n\n**$fecha_larga**\n\nQuedan **$dias_restantes_mes días** para que termine el mes.\n\n⏱️ Son las **$hora_actual**",
        
        "📋 **DETALLE COMPLETO**\n\n• Fecha: **$fecha_actual**\n• Día: **$dia_semana_texto**\n• Hora: **$hora_actual**\n• Zona horaria: **$zona_horaria**\n• Hora servidor: **$hora_servidor**"
    ];
    
    return $respuestas[array_rand($respuestas)];
}

function getInformacionCreador() {
    $respuestas_franklin = [
        "👨‍💻 ¡Ah, mencionaste a **Franklin Ramos Lamadrid**! Es el talentoso programador que creó este sistema SISFACT. Puedes contactarlo en: **kakycu@gmail.com**",
        
        "⭐ **Franklin** es el genio detrás de este chatbot y de todo SISFACT. Desarrollador principal y webmaster del proyecto.",
        
        "👑 **Franklin Ramos Lamadrid** (kaky°) - Creador y desarrollador del Sistema de Facturación SISFACT PDL Visiones. ¡Un abrazo para él!",
        
        "💻 Hablando de **Franklin**... Fue quien programó este asistente virtual para que pueda ayudarte con todas tus consultas. ¡Excelente trabajo!",
        
        "📧 Si necesitas contactar al desarrollador **Franklin**, puedes escribirle a: **kakycu@gmail.com**",
        
        "🏆 **Franklin Ramos Lamadrid** - Arquitecto de software y mente maestra detrás de SISFACT. Versión 2.3.3 © 2025-2026",
        
        "🤵 El jefe **Franklin** (kaky°) es quien diseñó toda la arquitectura del sistema. Si tienes sugerencias, él las recibe encantado.",
        
        "🔧 **Franklin** se encargó de que todo funcione perfectamente. ¿Algún problema? Seguro ya lo tiene resuelto."
    ];
    
    return $respuestas_franklin[array_rand($respuestas_franklin)];
}

function getEstadoSistema($db) {
    try {
        $version_php = phpversion();
        $hora_servidor = date('d/m/Y H:i:s');
        
        $driver_db = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $version_db = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
        $nombre_db = $db->query("SELECT DATABASE()")->fetchColumn();
        
        $estado_bd = $db ? "🟢 Conectada" : "🔴 Desconectada";
        
        $respuesta = "✅ **ESTADO DEL SISTEMA**\n\n";
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $respuesta .= "🖥️ **SERVIDOR WEB**\n";
        $respuesta .= "• Estado: 🟢 Online\n";
        $respuesta .= "• PHP: **$version_php**\n";
        $respuesta .= "• Hora: **$hora_servidor**\n\n";
        
        $respuesta .= "🗄️ **BASE DE DATOS**\n";
        $respuesta .= "• Estado: $estado_bd\n";
        $respuesta .= "• Motor: **" . ucfirst($driver_db) . "**\n";
        $respuesta .= "• Versión: **$version_db**\n";
        $respuesta .= "• Base de datos: **$nombre_db**\n";
        
        return $respuesta;
        
    } catch (Exception $e) {
        return "✅ **ESTADO DEL SISTEMA**\n\n" .
               "🖥️ **SERVIDOR WEB**\n" .
               "• Estado: 🟢 Online\n" .
               "• PHP: **" . phpversion() . "**\n" .
               "• Hora: **" . date('d/m/Y H:i:s') . "**\n\n" .
               "🗄️ **BASE DE DATOS**\n" .
               "• Estado: 🔴 Error al conectar\n" .
               "• Detalles: " . $e->getMessage();
    }
}

function getChisteRespuesta() {
    $chistes = [
        "😄 **Chiste de clientes:**\n\nUn cliente llega a la oficina y dice:\n- Vengo a pagar mi factura.\n- Perfecto, son $100.\n- ¿Aceptan tarjeta?\n- Sí, claro.\nEl cliente saca una silla, se sienta y dice: 'Bueno, aquí está mi tarjeta de presentación, ya pueden facturarme a crédito'.\n\n¡Eso sí es un cliente creativo!",
        
        "😄 **Chiste de facturas:**\n\n¿Cuál es el colmo de una factura?\n¡Que la llamen 'cuenta' pero no sepa contar chistes como este!",
        
        "😄 **Chiste de contabilidad:**\n\nUn contador le dice a otro:\n- Anoche soñé que estaba haciendo un balance general.\n- ¿Y qué pasó?\n- Desperté y todavía no cuadraba.\n\nEse es un chiste que solo entienden los que facturan.",
        
        "😄 **Chiste de clientes morosos:**\n\n- ¿Por qué los clientes morosos son buenos para el chiste?\n- Porque siempre tienen un 'cuento' para no pagar.",
        
        "😄 **Chiste de facturación:**\n\n- ¿Cómo se llama el libro favorito de las facturas?\n- 'El Principito', porque siempre dice 'había una vez una factura que quería ser pagada'.\n\n¡Chiste malo, lo sé!",
        
        "😄 **Chiste de pagos:**\n\nUn cliente llega y dice:\n- Quiero pagar mi factura pero no traje efectivo.\n- No hay problema, aceptamos tarjeta.\n- Tampoco traje tarjeta.\n- ¿Transferencia?\n- Tampoco.\n- ¿Entonces cómo va a pagar?\n- Con un chiste: ¿por qué las facturas van al gimnasio? ¡Para tener un buen IVA!",
        
        "😄 **Chiste de IVA:**\n\n- ¿Cuál es el impuesto favorito de las facturas?\n- El IVA, porque siempre está presente en todos los chistes de facturación.",
        
        "😄 **Chiste de clientes:**\n\n- ¿Cómo se llama el cliente que siempre paga tarde?\n- ¡El señor 'Ya Mismo'! Porque siempre dice 'ya mismo te pago'.",
        
        "😄 **Chiste de facturas electrónicas:**\n\n¿Por qué las facturas electrónicas son tan presumidas?\nPorque siempre andan diciendo: 'Mírame, estoy en la nube'.",
        
        "😄 **Chiste de cobros:**\n\n- ¿Cuál es el colmo de un cobrador?\n- Que le paguen con un chiste en lugar de dinero.",
        
        "😄 **Chiste de deudas:**\n\n- ¿Por qué las deudas son como los malos chistes?\n- Porque nunca terminan de cobrarse.",
        
        "😄 **Chiste de facturas vencidas:**\n\n- ¿Qué le dice una factura vencida a otra?\n- Hermana, ya nos pasamos de la fecha, mejor inventemos un chiste para alegrar al cliente.",
        
        "😄 **Chiste de SISFACT:**\n\n- ¿Por qué las facturas en SISFACT son tan felices?\n- Porque siempre están en buena compañía... con este chatbot que las ayuda a cobrarse.",
        
        "😄 **Chiste de clientes y facturas:**\n\nUn cliente le dice a su factura:\n- Te quiero pagar, pero no tengo efectivo.\nLa factura responde:\n- No te preocupes, espérame aquí, voy a convertirme en un chiste para alegrarte el día.",
        
        "😄 **Chiste de fin de mes:**\n\n- ¿Por qué las facturas se ponen tristes a fin de mes?\n- Porque ya pasó la fecha de pago y todavía están esperando que alguien las quiera (pagar).",
        
        "😄 **Chiste de la factura y el cliente:**\n\n- Factura, ¿por qué siempre estás tan seria?\n- Porque nadie me paga, solo me cuentan chistes como este.",
        
        "😄 **Chiste de facturas:**\n\n- ¿Cuál es el animal favorito de las facturas?\n- El 'pago' real, pero como casi nunca lo ven, se conforman con este chiste.",
        
        "😄 **Chiste de clientes:**\n\n- ¿Cómo se llama el cliente que nunca paga?\n- No tiene nombre, pero siempre tiene un chiste nuevo para excusarse.",
        
        "😄 **Chiste de contadores:**\n\n- ¿Por qué los contadores cuentan tan buenos chistes?\n- Porque están acostumbrados a manejar números y saben que este chiste vale más que una factura impaga.",
        
        "😄 **Chiste de facturación:**\n\n- ¿Qué hace una factura cuando ve un chiste?\n- Se ríe, pero igual sigue esperando que la paguen.",
        
        "😄 **Chiste de impuestos:**\n\n- ¿Por qué los impuestos no tienen chistes propios?\n- Porque ya son un chiste ellos mismos.",
        
        "😄 **Chiste de recibos:**\n\n- ¿Cómo se despiden los recibos?\n- 'Hasta la vista, baby... o hasta el próximo mes, cuando vuelva con otro chiste.'",
        
        "😄 **Chiste de facturas y clientes:**\n\nUn cliente recibe su factura y dice:\n- ¡Qué caro está todo!\nLa factura responde:\n- No te quejes, que este chiste te lo regalo.",
        
        "😄 **Chiste de pagos atrasados:**\n\n- ¿Qué le dice una factura atrasada a un cliente?\n- ¿Viste el chiste del que no paga? ¡Tú eres el protagonista!",
        
        "😄 **Chiste de SISFACT:**\n\n- ¿Por qué el chatbot de SISFACT cuenta tantos chistes de facturas?\n- Porque si no las pagan, al menos que se rían.",
        
        "😄 **Chiste de facturas:**\n\n- ¿Cuál es el chiste favorito de las facturas?\n- 'Había una vez un cliente que pagó a tiempo...' (es chiste porque nunca pasa).",
        
        "😄 **Pregunta:** ¿Por qué las facturas nunca se pierden?\n\n**Respuesta:** Porque siempre tienen un 'chiste' que las guía de vuelta a casa.",
        
        "😄 **Pregunta:** ¿Cuál es el animal favorito de las facturas?\n\n**Respuesta:** El 'pago' real, pero como no existe, se conforman con este chiste.",
        
        "😄 **Pregunta:** ¿Cómo se llama el libro favorito de los clientes morosos?\n\n**Respuesta:** 'El arte de no pagar', y viene con chistes incluidos.",
        
        "😄 **Pregunta:** ¿Qué hace una factura cuando ve a su cliente?\n\n**Respuesta:** Se prepara para cobrar... y para escuchar un chiste.",
        
        "😄 **Pregunta:** ¿Cuál es el chiste favorito de las facturas vencidas?\n\n**Respuesta:** 'Había una vez un cliente que pagó a tiempo', ¡es tan gracioso que nunca pasa!",
        
        "😄 **Pregunta:** ¿Por qué las facturas son buenas contando chistes?\n\n**Respuesta:** Porque tienen mucha 'tela' que cortar (y cobrar).",
        
        "😄 **Pregunta:** ¿Qué le dice una factura a un cliente moroso?\n\n**Respuesta:** '¿Te sabes el chiste del que paga? ¡Tú no!'",
        
        "😄 **Pregunta:** ¿Cómo se llama el festival de humor de las facturas?\n\n**Respuesta:** 'Facturín', donde solo se cuentan chistes de cobros.",
        
        "😄 **Pregunta:** ¿Por qué los contadores cuentan tan buenos chistes?\n\n**Respuesta:** Porque están acostumbrados a manejar números y saben que una risa vale más que una factura impaga.",
        
        "😄 **Pregunta:** ¿Cuál es el deporte favorito de las facturas?\n\n**Respuesta:** El 'cobro' libre, y luego celebrar con un chiste."
    ];
    
// Seleccionar un chiste aleatorio
$chiste = $chistes[array_rand($chistes)];

// Agregar botones SÍ/NO al final del chiste
$chiste .= "\n\n<div style=\"display: flex; gap: 10px; justify-content: center; margin-top: 10px;\">" .
           "<button onclick=\"enviarMensajeDirecto('chiste')\" class=\"chat-quick-btn\" style=\"background: #4caf50;\">👍 SÍ, otro chiste</button>" .
           "<button onclick=\"enviarMensajeDirecto('no, gracias')\" class=\"chat-quick-btn\" style=\"background: #f44336;\">👎 NO, gracias</button>" .
           "</div>";

return $chiste;
}

// ==================== FUNCIONES AUXILIARES PARA RESUMEN RÁPIDO ====================

function getFacturasHoyCount() {
    try {
        $db = Database::getConnection();
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tbl_fact 
                              WHERE DAY(fecha_emision) = :dia 
                              AND MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute([
            'dia' => $fecha['dia'],
            'mes' => $fecha['mes_num'],
            'anio' => $fecha['anio']
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        return "N/A";
    }
}

function getIngresosMesResumen() {
    try {
        $db = Database::getConnection();
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        return '$' . number_format($total, 2);
    } catch (Exception $e) {
        return "$0.00";
    }
}

function getClientesActivosCount() {
    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        return "N/A";
    }
}

function getProximoCierreResumen() {
    $fecha = getFechaMaestraSistema(null); // Pasar null porque aún no tenemos $db
    return $fecha['dias_restantes'] . " días (hasta " . date('d/m/Y', strtotime($fecha['fecha_completa'])) . ")";
}

function getTotalFacturasCount() {
    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) as total FROM tbl_fact WHERE estado != 'ANULADA'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        return 0;
    }
}

// ==================== FUNCIÓN DE NORMALIZACIÓN ====================

function normalizarTexto($texto) {
    $caracteres_especiales = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
        'ñ' => 'n', 'Ñ' => 'N',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        '¿' => '', '?' => '', '¡' => '', '!' => ''
    ];
    
    return strtr($texto, $caracteres_especiales);
}

// ==================== FUNCIONES DE AYUDA Y NAVEGACIÓN ====================

function getAyudaCompleta() {
    return "🤖 **SISFACT ASISTENTE - COMANDOS COMPLETOS**\n\n" .
           
           "═══════════════════════════════\n" .
           "📋 **FACTURACIÓN**\n" .
           "═══════════════════════════════\n" .
           "• ¿Cuántas facturas hay?\n" .
           "• Últimas 5/10/15 facturas\n" .
           "• Ingresos del mes / del año / de hoy\n" .
           "• Facturas pendientes / pagadas / contabilizadas / anuladas / cerradas\n" .
           "• Facturas del cliente [nombre]\n" .
           "• Total de facturas del año [2025]\n" .
           "• Comparar ingresos con plan\n" .
           "• Mejor/peor día/mes de ventas\n" .
           "• Promedio de facturas por día/mes\n" .
           "• Proyección mensual\n" .
           "• Evolución mensual de ingresos\n\n" .
           
           "═══════════════════════════════\n" .
           "👥 **CLIENTES**\n" .
           "═══════════════════════════════\n" .
           "• ¿Cuántos clientes hay?\n" .
           "• Últimos 5/10 clientes\n" .
           "• Clientes activos / inactivos\n" .
           "• Contratos por vencer / vencidos\n" .
           "• Cliente con código [código]\n" .
           "• Clientes que renuevan contrato\n" .
           "• Promedio de vigencia de contratos\n" .
           "• Ranking de clientes\n\n" .
           
           "═══════════════════════════════\n" .
           "🛠️ **SERVICIOS**\n" .
           "═══════════════════════════════\n" .
           "• Servicios más solicitados (5/10)\n" .
           "• ¿Cuántos servicios hay?\n" .
           "• Servicios activos / inactivos\n" .
           "• Servicio más caro / más barato\n" .
           "• Servicios de la categoría [nombre]\n\n" .
           
           "═══════════════════════════════\n" .
           "📁 **CATEGORÍAS**\n" .
           "═══════════════════════════════\n" .
           "• ¿Cuántas categorías hay?\n" .
           "• Categorías activas / inactivas\n" .
           "• Categoría con más servicios\n" .
           "• Categorías por código\n\n" .
           
           "═══════════════════════════════\n" .
           "👤 **USUARIOS**\n" .
           "═══════════════════════════════\n" .
           "• ¿Cuántos usuarios hay?\n" .
           "• Usuarios activos\n" .
           "• Usuarios por rol\n" .
           "• Últimos accesos\n" .
           "• Quién creó la última factura\n\n" .
           
           "═══════════════════════════════\n" .
           "📊 **REPORTES AVANZADOS**\n" .
           "═══════════════════════════════\n" .
           "• Reporte general / completo\n" .
           "• Comparativa anual\n" .
           "• Método de pago más usado\n" .
           "• Cierres mensuales\n" .
           "• Historial de cierres\n" .
           "• Próximo cierre\n\n" .
           
           "═══════════════════════════════\n" .
           "🎯 **PLANES Y METAS**\n" .
           "═══════════════════════════════\n" .
           "• Plan del mes / del año\n" .
           "• Cumplimiento del plan\n" .
           "• Meta mensual\n\n" .
           
           "═══════════════════════════════\n" .
           "📜 **HISTORIAL**\n" .
           "═══════════════════════════════\n" .
           "• Últimas actividades\n" .
           "• Actividades del usuario [nombre]\n" .
           "• Total de operaciones\n" .
           "• Operaciones de hoy / ayer / fecha específica\n\n" .
           
           "═══════════════════════════════\n" .
           "🧭 **NAVEGACIÓN**\n" .
           "═══════════════════════════════\n" .
           "• Abrir facturas / clientes / servicios / categorías\n" .
           "• Abrir reportes / rentabilidad / configuración\n" .
           "• Abrir planes / histórico / usuarios\n" .
           "• Crear factura / cliente / categoría / servicio / usuario\n" .
           "• Imprimir factura / todo el mes\n" .
           "• Buscar factura/cliente/servicio [término]\n\n" .
           
           "💡 *Puedes preguntar de forma natural, te entenderé*";
}

function getNavegacionResponse($url, $descripcion, $icono = '🔗') {
    return "$icono Puedes ver $descripcion haciendo clic aquí:\n" .
           "👉 **[$descripcion]($url)**\n\n" .
           "📌 También puedes ir desde el menú lateral.";
}

function getCrearResponse($url, $icono, $tipo, $titulo) {
    return "$icono **$titulo**\n\n" .
           "Para crear un/una nuevo/nueva $tipo, haz clic aquí:\n" .
           "👉 **[$titulo]($url)**\n\n" .
           "💡 Recuerda completar todos los campos requeridos.";
}

function getImprimirMesResponse() {
    $mes_actual = date('m');
    $anio_actual = date('Y');
    
    return "🖨️ **Impresión masiva del mes**\n\n" .
           "Para imprimir todas las facturas del mes actual ($mes_actual/$anio_actual):\n\n" .
           "1️⃣ Ve a **Reportes** en el menú lateral\n" .
           "2️⃣ Selecciona **Reporte Mensual**\n" .
           "3️⃣ Elige el mes y año\n" .
           "4️⃣ Haz clic en **Imprimir Todo**\n\n" .
           "📌 También puedes generar un PDF con todas las facturas desde esa misma sección.";
}

// ==================== FUNCIONES DE FACTURAS ====================

function getTotalFacturas($db) {
    try {
        $stmt = $db->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN estado = 'PAGADA' THEN 1 ELSE 0 END) as pagadas,
                            SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                            SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) as anuladas,
							SUM(CASE WHEN estado = 'CONTABILIZADA' THEN 1 ELSE 0 END) as contabilizadas
                            FROM tbl_fact");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "📊 **RESUMEN DE FACTURAS**\n\n" .
               "📄 Total general: **{$data['total']}**\n" .
               "✅ Pagadas: **{$data['pagadas']}**\n" .
               "⏳ Pendientes: **{$data['pendientes']}**\n" .
			   "💰 Contabilizadas: **{$data['contabilizadas']}**\n" .
               "❌ Anuladas: **{$data['anuladas']}**\n\n" .
               "💰 Facturas activas (no anuladas): **" . ($data['total'] - $data['anuladas']) . "**";
    } catch (Exception $e) {
        return "Error al obtener facturas.";
    }
}

function getUltimasFacturas($db, $limite = 5) {
    try {
        $stmt = $db->prepare("SELECT f.*, c.nombre as cliente 
                              FROM tbl_fact f 
                              LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                              ORDER BY f.fecha_emision DESC LIMIT :limite");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($facturas)) {
            return "No hay facturas recientes.";
        }
        
        $respuesta = "📋 **Últimas $limite facturas:**\n\n";
        foreach ($facturas as $f) {
            $fecha = date('d/m/Y', strtotime($f['fecha_emision']));
            $estado_emoji = getEstadoEmoji($f['estado']);
            $respuesta .= "**{$f['no_fact']}** $estado_emoji\n" .
                         "   Cliente: {$f['cliente']}\n" .
                         "   Fecha: $fecha | $" . number_format($f['total_general'], 2) . "\n\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener últimas facturas.";
    }
}

function getFacturasHoy($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as cantidad,
                              COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE DAY(fecha_emision) = :dia
                              AND MONTH(fecha_emision) = :mes
                              AND YEAR(fecha_emision) = :anio
                              AND estado != 'ANULADA'");
        $stmt->execute([
            'dia' => $fecha['dia'],
            'mes' => $fecha['mes_num'],
            'anio' => $fecha['anio']
        ]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "📅 **FACTURAS DE HOY**\n\n" .
               "📅 Fecha: {$fecha['dia']} de {$fecha['mes_nombre']} de {$fecha['anio']}\n" .
               "📄 Facturas emitidas: **{$data['cantidad']}**\n" .
               "💰 Total facturado: **$" . number_format($data['total'], 2) . "**";
    } catch (Exception $e) {
        return "Error al obtener facturas de hoy.";
    }
}

function getFacturasDelMes($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        $nombre_mes = $fecha['mes_nombre'];
        
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as cantidad,
                              COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt_pend = $db->prepare("SELECT COUNT(*) as total FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes 
                                   AND YEAR(fecha_emision) = :anio 
                                   AND estado = 'PENDIENTE'");
        $stmt_pend->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $pendientes = $stmt_pend->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt_pag = $db->prepare("SELECT COUNT(*) as total FROM tbl_fact 
                                  WHERE MONTH(fecha_emision) = :mes 
                                  AND YEAR(fecha_emision) = :anio 
                                  AND estado = 'PAGADA'");
        $stmt_pag->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $pagadas = $stmt_pag->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt_cont = $db->prepare("SELECT COUNT(*) as total FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes 
                                   AND YEAR(fecha_emision) = :anio 
                                   AND estado = 'CONTABILIZADA'");
        $stmt_cont->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $contabilizadas = $stmt_cont->fetch(PDO::FETCH_ASSOC)['total'];
        
        $respuesta = "📅 **FACTURAS DE $nombre_mes {$fecha['anio']}**\n\n" .
                    "📄 Total facturas: **{$data['cantidad']}**\n" .
                    "💰 Total facturado: $" . number_format($data['total'], 2) . "\n\n" .
                    "📊 **Desglose por estado:**\n" .
                    "• ⏳ Pendientes: $pendientes\n" .
                    "• ✅ Pagadas: $pagadas\n" .
                    "• 📝 Contabilizadas: $contabilizadas";
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener facturas del mes.";
    }
}

function getFacturasPorEstado($db, $estado) {
    try {
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as total,
                              COALESCE(SUM(total_general), 0) as importe
                              FROM tbl_fact 
                              WHERE estado = :estado");
        $stmt->execute(['estado' => $estado]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $emoji = getEstadoEmoji($estado);
        return "$emoji **Facturas $estado:** **{$data['total']}**\n" .
               "💰 Importe total: $" . number_format($data['importe'], 2);
    } catch (Exception $e) {
        return "Error al obtener facturas $estado.";
    }
}

function getFacturasPorEstadoGeneral($db) {
    try {
        $estados = [
            'PENDIENTE' => '⏳',
            'CONTABILIZADA' => '📝', 
            'PAGADA' => '✅',
            'CERRADA' => '🔒',
            'ANULADA' => '❌'
        ];
        
        $respuesta = "📊 **RESUMEN DE FACTURAS POR ESTADO**\n\n";
        $total_general = 0;
        $total_facturas = 0;
        $hay_datos = false;
        
        $stmt = $db->query("SELECT 
                            estado,
                            COUNT(*) as total,
                            COALESCE(SUM(total_general), 0) as importe
                            FROM tbl_fact 
                            GROUP BY estado
                            ORDER BY FIELD(estado, 'PENDIENTE', 'CONTABILIZADA', 'PAGADA', 'CERRADA', 'ANULADA')");
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $datos_por_estado = [];
        foreach ($resultados as $row) {
            $datos_por_estado[$row['estado']] = [
                'total' => $row['total'],
                'importe' => $row['importe']
            ];
        }
        
        foreach ($estados as $estado => $emoji) {
            $cantidad = $datos_por_estado[$estado]['total'] ?? 0;
            $importe = $datos_por_estado[$estado]['importe'] ?? 0;
            
            if ($cantidad > 0) {
                $hay_datos = true;
                $respuesta .= "$emoji **$estado**\n";
                $respuesta .= "   📄 Facturas: **$cantidad**\n";
                $respuesta .= "   💰 Importe: $" . number_format($importe, 2) . "\n\n";
                
                $total_facturas += $cantidad;
                $total_general += $importe;
            } else {
                $respuesta .= "$emoji **$estado**\n";
                $respuesta .= "   📄 Facturas: **0**\n";
                $respuesta .= "   💰 Importe: $0.00\n\n";
            }
        }
        
        if (!$hay_datos) {
            return "📊 No hay facturas registradas en el sistema.";
        }
        
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━\n";
        $respuesta .= "📋 **TOTALES GENERALES**\n";
        $respuesta .= "📄 Total facturas: **$total_facturas**\n";
        $respuesta .= "💰 Importe total: **$" . number_format($total_general, 2) . "**\n\n";
        
        return $respuesta;
        
    } catch (Exception $e) {
        return "Error al obtener facturas por estado: " . $e->getMessage();
    }
}

function getFacturasCliente($db, $cliente) {
    try {
        $stmt = $db->prepare("SELECT f.*, c.nombre as cliente_nombre 
                              FROM tbl_fact f 
                              JOIN clasif_clientes c ON f.cliente_id = c.id 
                              WHERE c.nombre LIKE :cliente 
                              ORDER BY f.fecha_emision DESC LIMIT 5");
        $stmt->execute(['cliente' => "%$cliente%"]);
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($facturas)) {
            return "No encontré facturas para el cliente '$cliente'";
        }
        
        $total = 0;
        $respuesta = "📋 **Facturas de $cliente:**\n\n";
        foreach ($facturas as $f) {
            $fecha = date('d/m/Y', strtotime($f['fecha_emision']));
            $estado_emoji = getEstadoEmoji($f['estado']);
            $respuesta .= "• {$f['no_fact']} $estado_emoji | $fecha | $" . 
                         number_format($f['total_general'], 2) . "\n";
            $total += $f['total_general'];
        }
        $respuesta .= "\n💰 Total: $" . number_format($total, 2);
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener facturas del cliente.";
    }
}

function getFacturasPorDia($db, $dia) {
    try {
        $mes_actual = date('n');
        $anio_actual = date('Y');
        
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as cantidad,
                              COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE DAY(fecha_emision) = :dia
                              AND MONTH(fecha_emision) = :mes
                              AND YEAR(fecha_emision) = :anio
                              AND estado != 'ANULADA'");
        $stmt->execute(['dia' => $dia, 'mes' => $mes_actual, 'anio' => $anio_actual]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "📅 **DÍA $dia DE " . strtoupper(getNombreMes($mes_actual)) . "**\n\n" .
               "📄 Facturas: **{$data['cantidad']}**\n" .
               "💰 Ingresos: $" . number_format($data['total'], 2);
    } catch (Exception $e) {
        return "Error al obtener facturas del día.";
    }
}

function getFacturasPorMes($db, $mes) {
    try {
        $anio_actual = date('Y');
        
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as cantidad,
                              COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes
                              AND YEAR(fecha_emision) = :anio
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $mes, 'anio' => $anio_actual]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "📅 **" . strtoupper(getNombreMes($mes)) . " $anio_actual**\n\n" .
               "📄 Facturas: **{$data['cantidad']}**\n" .
               "💰 Ingresos: $" . number_format($data['total'], 2);
    } catch (Exception $e) {
        return "Error al obtener facturas del mes.";
    }
}

function getFacturasPorAnio($db, $anio) {
    try {
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as cantidad,
                              COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE YEAR(fecha_emision) = :anio
                              AND estado != 'ANULADA'");
        $stmt->execute(['anio' => $anio]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "📅 **AÑO $anio**\n\n" .
               "📄 Facturas: **{$data['cantidad']}**\n" .
               "💰 Ingresos: $" . number_format($data['total'], 2);
    } catch (Exception $e) {
        return "Error al obtener facturas del año.";
    }
}

function getIngresosHoy($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT 
                              COALESCE(SUM(total_general), 0) as total,
                              COUNT(*) as cantidad
                              FROM tbl_fact 
                              WHERE DAY(fecha_emision) = :dia
                              AND MONTH(fecha_emision) = :mes
                              AND YEAR(fecha_emision) = :anio
                              AND estado != 'ANULADA'");
        $stmt->execute([
            'dia' => $fecha['dia'],
            'mes' => $fecha['mes_num'],
            'anio' => $fecha['anio']
        ]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "💰 **INGRESOS DE HOY**\n\n" .
               "📅 Fecha: {$fecha['dia']} de {$fecha['mes_nombre']} de {$fecha['anio']}\n" .
               "💰 Total: **$" . number_format($data['total'], 2) . "**\n" .
               "📄 Facturas emitidas: **{$data['cantidad']}**";
    } catch (Exception $e) {
        return "Error al obtener ingresos de hoy.";
    }
}

function getIngresosMes($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT 
                              COALESCE(SUM(total_general), 0) as total,
                              COUNT(*) as cantidad
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt_plan = $db->prepare("SELECT importe FROM tbl_planes WHERE anio = :anio AND mes_plan = :mes");
        $stmt_plan->execute(['anio' => $fecha['anio'], 'mes' => $fecha['mes_num']]);
        $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
        $plan_mes = $plan ? $plan['importe'] : 0;
        
        $cumplimiento = $plan_mes > 0 ? ($data['total'] / $plan_mes) * 100 : 0;
        $faltante = $plan_mes - $data['total'];
        
        $respuesta = "💰 **INGRESOS DEL MES**\n\n" .
                    "📅 Mes: {$fecha['mes_nombre']} {$fecha['anio']}\n" .
                    "📊 Total: **$" . number_format($data['total'], 2) . "**\n" .
                    "📄 Facturas: **{$data['cantidad']}**\n\n";
        
        if ($plan_mes > 0) {
            $respuesta .= "🎯 Meta del mes: $" . number_format($plan_mes, 2) . "\n" .
                         "📈 Cumplimiento: **" . number_format($cumplimiento, 1) . "%**\n";
            if ($faltante > 0) {
                $respuesta .= "⏳ Faltan: $" . number_format($faltante, 2) . " para alcanzar la meta\n";
            } else {
                $respuesta .= "🎉 ¡Meta superada por $" . number_format(abs($faltante), 2) . "!\n";
            }
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener ingresos del mes.";
    }
}

function getIngresosAnio($db) {
    try {
        $anio_actual = date('Y');
        $stmt = $db->prepare("SELECT 
                              COALESCE(SUM(total_general), 0) as total,
                              COUNT(*) as cantidad,
                              MONTH(fecha_emision) as mes
                              FROM tbl_fact 
                              WHERE YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'
                              GROUP BY MONTH(fecha_emision)");
        $stmt->execute(['anio' => $anio_actual]);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_anual = 0;
        $mejor_mes = ['mes' => '', 'total' => 0];
        $peor_mes = ['mes' => '', 'total' => PHP_FLOAT_MAX];
        
        foreach ($datos as $d) {
            $total_anual += $d['total'];
            if ($d['total'] > $mejor_mes['total']) {
                $mejor_mes = ['mes' => getNombreMes($d['mes']), 'total' => $d['total']];
            }
            if ($d['total'] < $peor_mes['total']) {
                $peor_mes = ['mes' => getNombreMes($d['mes']), 'total' => $d['total']];
            }
        }
        
        $stmt_plan = $db->prepare("SELECT SUM(importe) as total FROM tbl_planes WHERE anio = :anio");
        $stmt_plan->execute(['anio' => $anio_actual]);
        $plan_anual = $stmt_plan->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $promedio_mensual = count($datos) > 0 ? $total_anual / count($datos) : 0;
        
        $respuesta = "📅 **INGRESOS DEL AÑO $anio_actual**\n\n" .
                    "💰 Total anual: **$" . number_format($total_anual, 2) . "**\n" .
                    "📊 Promedio mensual: $" . number_format($promedio_mensual, 2) . "\n\n";
        
        if ($plan_anual > 0) {
            $cumplimiento = ($total_anual / $plan_anual) * 100;
            $respuesta .= "🎯 Plan anual: $" . number_format($plan_anual, 2) . "\n" .
                         "📈 Cumplimiento: **" . number_format($cumplimiento, 1) . "%**\n\n";
        }
        
        if ($mejor_mes['mes']) {
            $respuesta .= "🏆 Mejor mes: **{$mejor_mes['mes']}** con $" . 
                         number_format($mejor_mes['total'], 2) . "\n";
        }
        if ($peor_mes['mes'] && $peor_mes['total'] < PHP_FLOAT_MAX) {
            $respuesta .= "📉 Peor mes: **{$peor_mes['mes']}** con $" . 
                         number_format($peor_mes['total'], 2) . "\n";
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener ingresos del año.";
    }
}

function getComparativaPlan($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total 
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $real = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt_plan = $db->prepare("SELECT importe FROM tbl_planes 
                                   WHERE anio = :anio AND mes_plan = :mes");
        $stmt_plan->execute(['anio' => $fecha['anio'], 'mes' => $fecha['mes_num']]);
        $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC)['importe'] ?? 0;
        
        if ($plan == 0) {
            return "No hay plan definido para este mes.";
        }
        
        $diferencia = $real - $plan;
        $porcentaje = ($real / $plan) * 100;
        
        $respuesta = "📊 **COMPARATIVA CON PLAN - {$fecha['mes_nombre']} {$fecha['anio']}**\n\n" .
                    "💰 Real: **$" . number_format($real, 2) . "**\n" .
                    "🎯 Plan: $" . number_format($plan, 2) . "\n" .
                    "📈 Cumplimiento: **" . number_format($porcentaje, 1) . "%**\n" .
                    "📅 Cierre: " . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "\n";
        
        if ($diferencia >= 0) {
            $respuesta .= "✅ ¡Meta superada en $" . number_format($diferencia, 2) . "!\n";
        } else {
            $respuesta .= "⏳ Faltan $" . number_format(abs($diferencia), 2) . " para alcanzar la meta\n";
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al comparar con el plan.";
    }
}


// ==================== FUNCIONES DE CLIENTES ====================

function getTotalClientes($db) {
    try {
        $stmt = $db->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
                            FROM clasif_clientes");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "👥 **RESUMEN DE CLIENTES**\n\n" .
               "Total: **{$data['total']}**\n" .
               "✅ Activos: {$data['activos']}\n" .
               "❌ Inactivos: {$data['inactivos']}";
    } catch (Exception $e) {
        return "Error al obtener clientes.";
    }
}

function getUltimosClientes($db, $limite = 5) {
    try {
        $stmt = $db->prepare("SELECT * FROM clasif_clientes 
                              ORDER BY fechaRegistro DESC LIMIT :limite");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($clientes)) {
            return "No hay clientes registrados.";
        }
        
        $respuesta = "👥 **Últimos $limite clientes:**\n\n";
        foreach ($clientes as $c) {
            $fecha = date('d/m/Y', strtotime($c['fechaRegistro']));
            $estado = $c['activo'] ? '✅' : '❌';
            $respuesta .= "**{$c['codigo']}** $estado\n" .
                         "   {$c['nombre']}\n" .
                         "   Registro: $fecha | Contrato: {$c['ContratoNo']}\n\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener últimos clientes.";
    }
}

function getClientesActivos($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 1");
        $activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 0");
        $inactivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "✅ **Clientes activos:** $activos\n" .
               "❌ **Inactivos:** $inactivos\n" .
               "📊 Proporción activos: " . round(($activos / ($activos + $inactivos)) * 100, 1) . "%";
    } catch (Exception $e) {
        return "Error al obtener clientes activos.";
    }
}

function getClientesInactivos($db) {
    try {
        $stmt = $db->query("SELECT nombre, codigo, fechaRegistro 
                            FROM clasif_clientes 
                            WHERE activo = 0 
                            ORDER BY fechaRegistro DESC 
                            LIMIT 5");
        $inactivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inactivos)) {
            return "✅ No hay clientes inactivos.";
        }
        
        $respuesta = "❌ **Últimos 5 clientes inactivos:**\n\n";
        foreach ($inactivos as $c) {
            $fecha = date('d/m/Y', strtotime($c['fechaRegistro']));
            $respuesta .= "• {$c['codigo']} - {$c['nombre']} (desde $fecha)\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener clientes inactivos.";
    }
}

function getContratosPorVencerDetallado($db) {
    try {
        $hoy = date('Y-m-d');
        $treinta = date('Y-m-d', strtotime('+30 days'));
        $sesenta = date('Y-m-d', strtotime('+60 days'));
        
        $proximos_30 = $db->prepare("SELECT COUNT(*) FROM clasif_clientes WHERE fechafinalcontrato BETWEEN ? AND ? AND activo = 1 AND fechafinalcontrato != '0000-00-00'");
        $proximos_30->execute([$hoy, $treinta]);
        $c30 = $proximos_30->fetchColumn();

        $proximos_60 = $db->prepare("SELECT COUNT(*) FROM clasif_clientes WHERE fechafinalcontrato BETWEEN ? AND ? AND activo = 1 AND fechafinalcontrato != '0000-00-00'");
        $proximos_60->execute([date('Y-m-d', strtotime('+31 days')), $sesenta]);
        $c60 = $proximos_60->fetchColumn();

        $stmt_detalle = $db->prepare("SELECT codigo, nombre, fechafinalcontrato, vigenciapor FROM clasif_clientes WHERE fechafinalcontrato >= ? AND activo = 1 AND fechafinalcontrato != '0000-00-00' ORDER BY fechafinalcontrato ASC");
        $stmt_detalle->execute([$hoy]);
        $proximos = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
        
        $respuesta = "⚠️ **CONTRATOS POR VENCER**\n\n";
        $respuesta .= "📊 **RESUMEN:**\n• 30 días: **$c30**\n• 60 días: **$c60**\n\n";
        
        if ($proximos) {
            $respuesta .= "📋 **PRÓXIMOS 5:**\n";
            foreach ($proximos as $c) {
                $fecha = date('d/m/Y', strtotime($c['fechafinalcontrato']));
                $dias = ceil((strtotime($c['fechafinalcontrato']) - strtotime($hoy)) / 86400);
                $emoji = $dias <= 15 ? '🔴' : '🟡';
                $respuesta .= "$emoji **{$c['codigo']}** - {$c['nombre']}\n   Vence: $fecha (en **$dias días**)\n\n";
            }
        }
        return $respuesta;
    } catch (Exception $e) { 
        return "Error en contratos por vencer."; 
    }
}

function getContratosVencidosDetallado($db) {
    try {
        $hoy = date('Y-m-d');
        $stmt_total = $db->prepare("SELECT COUNT(*) FROM clasif_clientes WHERE fechafinalcontrato < ? AND fechafinalcontrato != '0000-00-00' AND activo = 1");
        $stmt_total->execute([$hoy]);
        $total = $stmt_total->fetchColumn();
        
        $stmt_detalle = $db->prepare("SELECT codigo, nombre, fechafinalcontrato FROM clasif_clientes WHERE fechafinalcontrato < ? AND fechafinalcontrato != '0000-00-00' AND activo = 1 ORDER BY fechafinalcontrato DESC");
        $stmt_detalle->execute([$hoy]);
        $vencidos = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
        
        $respuesta = "❌ **CONTRATOS VENCIDOS**\n\nTotal vencidos activos: **$total**\n\n";
        if ($vencidos) {
            $respuesta .= "📋 **ÚLTIMOS VENCIDOS:**\n";
            foreach ($vencidos as $c) {
                $fecha = date('d/m/Y', strtotime($c['fechafinalcontrato']));
                $dias = floor((strtotime($hoy) - strtotime($c['fechafinalcontrato'])) / 86400);
                $respuesta .= "🔴 **{$c['codigo']}** - {$c['nombre']}\n   Venció: $fecha (hace **$dias días**)\n\n";
            }
        }
        return $respuesta;
    } catch (Exception $e) { 
        return "Error en contratos vencidos."; 
    }
}

function getClientePorCodigo($db, $codigo) {
    try {
        $stmt = $db->prepare("SELECT * FROM clasif_clientes 
                              WHERE codigo = :codigo OR ContratoNo = :contrato");
        $stmt->execute(['codigo' => $codigo, 'contrato' => $codigo]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            return "No encontré cliente con código o contrato '$codigo'";
        }
        
        $estado = $cliente['activo'] ? '✅ Activo' : '❌ Inactivo';
        $fecha_reg = date('d/m/Y', strtotime($cliente['fechaRegistro']));
        $fecha_vence = !empty($cliente['fechafinalcontrato']) && $cliente['fechafinalcontrato'] != '0000-00-00' 
                      ? date('d/m/Y', strtotime($cliente['fechafinalcontrato'])) : 'No definida';
        
        $dias_restantes = 0;
        if ($cliente['activo'] && !empty($cliente['fechafinalcontrato']) && $cliente['fechafinalcontrato'] != '0000-00-00') {
            $hoy = new DateTime();
            $venc = new DateTime($cliente['fechafinalcontrato']);
            if ($hoy < $venc) {
                $dias_restantes = $hoy->diff($venc)->days;
            }
        }
        
        $respuesta = "👤 **INFORMACIÓN DEL CLIENTE**\n\n" .
                    "Código: **{$cliente['codigo']}**\n" .
                    "Nombre: {$cliente['nombre']}\n" .
                    "Estado: $estado\n" .
                    "Contrato: {$cliente['ContratoNo']}\n" .
                    "Responsable: {$cliente['ResponsableEntidad']}\n" .
                    "Teléfono: " . ($cliente['telefono'] ?: 'No especificado') . "\n" .
                    "Email: " . ($cliente['email'] ?: 'No especificado') . "\n" .
                    "Registro: $fecha_reg\n" .
                    "Vigencia: {$cliente['vigenciapor']} años\n";
        
        if ($cliente['renovac']) {
            $respuesta .= "Renueva por: {$cliente['si_renova_cant']} años\n";
        }
        
        if ($fecha_vence != 'No definida') {
            $respuesta .= "Fecha fin contrato: $fecha_vence\n";
            if ($dias_restantes > 0) {
                $respuesta .= "⏳ Días restantes: **$dias_restantes**\n";
            }
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener información del cliente.";
    }
}

function getClientesRenuevan($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_clientes WHERE renovac = 1");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT nombre, codigo, si_renova_cant 
                            FROM clasif_clientes 
                            WHERE renovac = 1 
                            ORDER BY si_renova_cant DESC 
                            LIMIT 5");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $respuesta = "🔄 **CLIENTES QUE RENUEVAN CONTRATO**\n\n" .
                    "Total: **$total** clientes\n\n" .
                    "**Top 5 por años de renovación:**\n";
        
        foreach ($clientes as $c) {
            $respuesta .= "• {$c['codigo']} - {$c['nombre']}: +{$c['si_renova_cant']} años\n";
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener clientes que renuevan.";
    }
}

function getPromedioVigencia($db) {
    try {
        $stmt = $db->query("SELECT AVG(vigenciapor) as promedio FROM clasif_clientes WHERE vigenciapor > 0");
        $promedio = $stmt->fetch(PDO::FETCH_ASSOC)['promedio'];
        
        $stmt = $db->query("SELECT MAX(vigenciapor) as max, MIN(vigenciapor) as min 
                            FROM clasif_clientes WHERE vigenciapor > 0");
        $extremos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return "📊 **ESTADÍSTICAS DE VIGENCIA**\n\n" .
               "⏳ Promedio de vigencia: **" . round($promedio, 1) . " años**\n" .
               "📈 Máxima vigencia: {$extremos['max']} años\n" .
               "📉 Mínima vigencia: {$extremos['min']} años";
    } catch (Exception $e) {
        return "Error al calcular promedio de vigencia.";
    }
}

// ==================== FUNCIONES DE SERVICIOS ====================

function getServiciosMasSolicitados($db, $limite = 5) {
    try {
        $sql = "SELECT s.descripcion, s.codigo, s.costo, 
                       COALESCE(SUM(fd.cantidad), 0) as total_vendido,
                       c.descripcion as categoria
                FROM clasif_serv s
                LEFT JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
                LEFT JOIN tbl_fact f ON fd.factura_id = f.id
                LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                WHERE f.estado != 'ANULADA' OR f.estado IS NULL
                GROUP BY s.id
                ORDER BY total_vendido DESC
                LIMIT :limite";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($servicios)) {
            return "No hay datos de servicios solicitados.";
        }
        
        $total_general = array_sum(array_column($servicios, 'total_vendido'));
        
        $respuesta = "⭐ **TOP $limite SERVICIOS MÁS SOLICITADOS**\n\n";
        $pos = 1;
        foreach ($servicios as $s) {
            $porcentaje = $total_general > 0 ? round(($s['total_vendido'] / $total_general) * 100, 1) : 0;
            $medalla = $pos == 1 ? '🥇' : ($pos == 2 ? '🥈' : ($pos == 3 ? '🥉' : '📌'));
            $respuesta .= "$medalla **{$s['codigo']}** - {$s['descripcion']}\n" .
                         "   Vendido: **{$s['total_vendido']}** unidades ($porcentaje%)\n" .
                         "   Precio: $" . number_format($s['costo'], 2) . " | Categoría: {$s['categoria']}\n\n";
            $pos++;
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener servicios más solicitados.";
    }
}

function getTotalServicios($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_serv");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "🛠️ **Total de servicios:** $total";
    } catch (Exception $e) {
        return "Error al obtener total de servicios.";
    }
}

function getRespuestaNoEntendi($usuario_nombre) {
    $respuestas = [
        "😅 No te preocupes, $usuario_nombre. A veces no me explico bien.\n\n" .
        "¿Podrías ser más específico? Por ejemplo:\n" .
        "• Para facturas: *'últimas 5 facturas'*\n" .
        "• Para clientes: *'clientes activos'*\n" .
        "• Para servicios: *'servicios más solicitados'*\n\n" .
        "💡 O escribe **ayuda** para ver todos los comandos disponibles.",
        
        "🤔 Perdón $usuario_nombre, no capté bien tu pregunta.\n\n" .
        "Intenta con algo como:\n" .
        "• *'ingresos del mes'*\n" .
        "• *'contratos por vencer'*\n" .
        "• *'abrir facturas'*\n\n" .
        "📌 También puedes escribir **ayuda** para ver el menú completo.",
        
        "😊 Tranquilo $usuario_nombre. Soy un bot y a veces no entiendo todo.\n\n" .
        "Prueba preguntando:\n" .
        "• *'¿cuántas facturas hay?'*\n" .
        "• *'clientes activos'*\n" .
        "• *'próximo cierre'*\n\n" .
        "💡 Recuerda que siempre puedes escribir **ayuda** para orientarte.",
        
        "🧐 No estoy seguro de lo que quieres decir, $usuario_nombre.\n\n" .
        "Te sugiero:\n" .
        "1️⃣ Ser más específico\n" .
        "2️⃣ Usar palabras clave como 'facturas', 'clientes', 'servicios'\n" .
        "3️⃣ Escribir **ayuda** para ver todos los comandos\n\n" .
        "¿Qué prefieres hacer?",
        
        "🤷️ Lo siento $usuario_nombre, no logro entender tu consulta.\n\n" .
        "Aquí tienes algunos ejemplos que sí entiendo:\n" .
        "✅ *'facturas pendientes'*\n" .
        "✅ *'ranking de clientes'*\n" .
        "✅ *'comparar ingresos con plan'*\n\n" .
        "📋 Escribe **ayuda** para ver la lista completa."
    ];
    
    return $respuestas[array_rand($respuestas)];
}

function getServiciosActivos($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 1");
        $activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 0");
        $inactivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "✅ **Servicios activos:** $activos\n" .
               "❌ **Inactivos:** $inactivos\n" .
               "📊 Total: " . ($activos + $inactivos);
    } catch (Exception $e) {
        return "Error al obtener servicios activos.";
    }
}

function getServiciosInactivos($db) {
    try {
        $stmt = $db->query("SELECT codigo, descripcion, costo 
                            FROM clasif_serv 
                            WHERE activo = 0 
                            LIMIT 5");
        $inactivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inactivos)) {
            return "✅ Todos los servicios están activos.";
        }
        
        $respuesta = "❌ **Servicios inactivos (últimos 5):**\n\n";
        foreach ($inactivos as $s) {
            $respuesta .= "• {$s['codigo']} - {$s['descripcion']} ($" . number_format($s['costo'], 2) . ")\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener servicios inactivos.";
    }
}

function getServicioMasCaro($db) {
    try {
        $stmt = $db->query("SELECT codigo, descripcion, costo, 
                                   (SELECT descripcion FROM clasif_cat_de_serv WHERE id = s.categoria_id) as categoria
                            FROM clasif_serv s
                            WHERE activo = 1
                            ORDER BY costo DESC 
                            LIMIT 1");
        $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$servicio) {
            return "No hay servicios disponibles.";
        }
        
        return "💰 **SERVICIO MÁS CARO**\n\n" .
               "Código: **{$servicio['codigo']}**\n" .
               "Descripción: {$servicio['descripcion']}\n" .
               "Precio: $" . number_format($servicio['costo'], 2) . "\n" .
               "Categoría: {$servicio['categoria']}";
    } catch (Exception $e) {
        return "Error al obtener servicio más caro.";
    }
}

function getServicioMasBarato($db) {
    try {
        $stmt = $db->query("SELECT codigo, descripcion, costo, 
                                   (SELECT descripcion FROM clasif_cat_de_serv WHERE id = s.categoria_id) as categoria
                            FROM clasif_serv s
                            WHERE activo = 1
                            ORDER BY costo ASC 
                            LIMIT 1");
        $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$servicio) {
            return "No hay servicios disponibles.";
        }
        
        return "💸 **SERVICIO MÁS BARATO**\n\n" .
               "Código: **{$servicio['codigo']}**\n" .
               "Descripción: {$servicio['descripcion']}\n" .
               "Precio: $" . number_format($servicio['costo'], 2) . "\n" .
               "Categoría: {$servicio['categoria']}";
    } catch (Exception $e) {
        return "Error al obtener servicio más barato.";
    }
}

function getServiciosPorCategoria($db, $categoria) {
    try {
        $stmt = $db->prepare("SELECT s.*, c.descripcion as cat_nombre 
                              FROM clasif_serv s
                              JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                              WHERE c.descripcion LIKE :categoria OR c.codigo LIKE :categoria2
                              AND s.activo = 1
                              ORDER BY s.costo DESC
                              LIMIT 10");
        $stmt->execute(['categoria' => "%$categoria%", 'categoria2' => "%$categoria%"]);
        $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($servicios)) {
            return "No encontré servicios en la categoría '$categoria'";
        }
        
        $cat_nombre = $servicios[0]['cat_nombre'];
        $respuesta = "📁 **Servicios de categoría: $cat_nombre**\n\n";
        
        foreach ($servicios as $s) {
            $respuesta .= "• **{$s['codigo']}** - {$s['descripcion']}\n" .
                         "  $" . number_format($s['costo'], 2) . "\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener servicios por categoría.";
    }
}

// ==================== FUNCIONES DE CATEGORÍAS ====================

function getTotalCategorias($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_cat_de_serv");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "📁 **Total de categorías:** $total";
    } catch (Exception $e) {
        return "Error al obtener total de categorías.";
    }
}

function getCategoriasActivas($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_cat_de_serv WHERE activo = 1");
        $activas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_cat_de_serv WHERE activo = 0");
        $inactivas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "✅ **Categorías activas:** $activas\n" .
               "❌ **Inactivas:** $inactivas\n" .
               "📊 Total: " . ($activas + $inactivas);
    } catch (Exception $e) {
        return "Error al obtener categorías activas.";
    }
}

function getCategoriasInactivas($db) {
    try {
        $stmt = $db->query("SELECT codigo, descripcion 
                            FROM clasif_cat_de_serv 
                            WHERE activo = 0 
                            LIMIT 5");
        $inactivas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inactivas)) {
            return "✅ Todas las categorías están activas.";
        }
        
        $respuesta = "❌ **Categorías inactivas:**\n\n";
        foreach ($inactivas as $c) {
            $respuesta .= "• {$c['codigo']} - {$c['descripcion']}\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener categorías inactivas.";
    }
}

function getCategoriaConMasServicios($db) {
    try {
        $stmt = $db->query("SELECT c.descripcion, c.codigo, COUNT(s.id) as total_servicios
                            FROM clasif_cat_de_serv c
                            LEFT JOIN clasif_serv s ON c.id = s.categoria_id
                            WHERE c.activo = 1
                            GROUP BY c.id
                            ORDER BY total_servicios DESC
                            LIMIT 1");
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categoria) {
            return "No hay categorías con servicios.";
        }
        
        return "🏆 **CATEGORÍA CON MÁS SERVICIOS**\n\n" .
               "Categoría: **{$categoria['descripcion']}** ({$categoria['codigo']})\n" .
               "📊 Servicios: **{$categoria['total_servicios']}**";
    } catch (Exception $e) {
        return "Error al obtener categoría con más servicios.";
    }
}

function getCategoriasConCodigos($db) {
    try {
        $stmt = $db->query("SELECT codigo, descripcion, activo 
                            FROM clasif_cat_de_serv 
                            ORDER BY codigo 
                            LIMIT 15");
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($categorias)) {
            return "No hay categorías registradas.";
        }
        
        $respuesta = "📋 **CATEGORÍAS POR CÓDIGO**\n\n";
        foreach ($categorias as $c) {
            $estado = $c['activo'] ? '✅' : '❌';
            $respuesta .= "**{$c['codigo']}** $estado - {$c['descripcion']}\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener categorías.";
    }
}

// ==================== FUNCIONES DE USUARIOS ====================

function getTotalUsuarios($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_usuarios");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "👥 **Total de usuarios:** $total";
    } catch (Exception $e) {
        return "Error al obtener total de usuarios.";
    }
}

function getUsuariosActivos($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_usuarios WHERE activo = 1");
        $activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_usuarios WHERE activo = 0");
        $inactivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "✅ **Usuarios activos:** $activos\n" .
               "❌ **Inactivos:** $inactivos";
    } catch (Exception $e) {
        return "Error al obtener usuarios activos.";
    }
}

function getUsuariosPorRol($db) {
    try {
        $stmt = $db->query("SELECT r.descripcion as rol, COUNT(u.id) as total
                            FROM clasif_rol r
                            LEFT JOIN clasif_usuarios u ON r.id = u.rol_id
                            GROUP BY r.id
                            ORDER BY total DESC");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $respuesta = "👤 **USUARIOS POR ROL**\n\n";
        foreach ($roles as $r) {
            $respuesta .= "**{$r['rol']}:** {$r['total']} usuarios\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener usuarios por rol.";
    }
}

function getUltimosAccesos($db) {
    try {
        $stmt = $db->query("SELECT h.operacion, h.fecha_hora, h.usuario_nombre 
                            FROM historico_operaciones h
                            WHERE h.operacion = 'LOGIN'
                            ORDER BY h.fecha_hora DESC
                            LIMIT 5");
        $accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($accesos)) {
            return "No hay registros de accesos recientes.";
        }
        
        $respuesta = "🔐 **ÚLTIMOS 5 ACCESOS AL SISTEMA**\n\n";
        foreach ($accesos as $a) {
            $fecha = date('d/m/Y H:i', strtotime($a['fecha_hora']));
            $respuesta .= "• **{$a['usuario_nombre']}** - $fecha\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener últimos accesos.";
    }
}

function getUltimaActividadUsuario($db) {
    try {
        $stmt = $db->query("SELECT h.*, u.nombre as usuario_nom
                            FROM historico_operaciones h
                            JOIN clasif_usuarios u ON h.usuario_id = u.id
                            WHERE h.operacion LIKE '%CREAR_FACTURA%' OR h.operacion LIKE '%FACTURA%'
                            ORDER BY h.fecha_hora DESC
                            LIMIT 1");
        $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$actividad) {
            return "No hay registros de actividad reciente.";
        }
        
        $fecha = date('d/m/Y H:i', strtotime($actividad['fecha_hora']));
        return "👤 **Última actividad**\n\n" .
               "Usuario: **{$actividad['usuario_nom']}**\n" .
               "Acción: {$actividad['operacion']}\n" .
               "Fecha: $fecha";
    } catch (Exception $e) {
        return "Error al obtener última actividad.";
    }
}

// ==================== REPORTES AVANZADOS ====================

function getReporteGeneral($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->query("SELECT 
                            COUNT(*) as total_facturas,
                            COALESCE(SUM(total_general), 0) as total_ingresos
                            FROM tbl_fact WHERE estado != 'ANULADA'");
        $facturas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT COUNT(*) as total_clientes FROM clasif_clientes WHERE activo = 1");
        $clientes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT COUNT(*) as total_servicios FROM clasif_serv WHERE activo = 1");
        $servicios = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT COUNT(*) as total_categorias FROM clasif_cat_de_serv WHERE activo = 1");
        $categorias = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT COUNT(*) as total_usuarios FROM clasif_usuarios WHERE activo = 1");
        $usuarios = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total 
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $ingresos_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return "📊 **REPORTE GENERAL DEL SISTEMA**\n\n" .
               "═══════════════════════════\n" .
               "📋 **FACTURACIÓN**\n" .
               "═══════════════════════════\n" .
               "📄 Total facturas: **{$facturas['total_facturas']}**\n" .
               "💰 Ingresos totales: $" . number_format($facturas['total_ingresos'], 2) . "\n" .
               "💵 Ingresos del mes ({$fecha['mes_nombre']}): $" . number_format($ingresos_mes, 2) . "\n" .
               "📅 Cierre del mes: " . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "\n\n" .
               
               "═══════════════════════════\n" .
               "👥 **CLIENTES**\n" .
               "═══════════════════════════\n" .
               "✅ Clientes activos: **{$clientes['total_clientes']}**\n\n" .
               
               "═══════════════════════════\n" .
               "🛠️ **SERVICIOS Y CATEGORÍAS**\n" .
               "═══════════════════════════\n" .
               "🔧 Servicios activos: **{$servicios['total_servicios']}**\n" .
               "📁 Categorías activas: **{$categorias['total_categorias']}**\n\n" .
               
               "═══════════════════════════\n" .
               "👤 **USUARIOS**\n" .
               "═══════════════════════════\n" .
               "👥 Usuarios activos: **{$usuarios['total_usuarios']}**\n\n" .
               
               "💡 *Consulta detalles específicos con otros comandos*";
    } catch (Exception $e) {
        return "Error al generar reporte general.";
    }
}

function getMejorPeriodo($db, $message) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        if (strpos($message, 'dia') !== false || strpos($message, 'día') !== false) {
            $stmt = $db->prepare("SELECT 
                                  DAY(fecha_emision) as dia,
                                  COALESCE(SUM(total_general), 0) as total
                                  FROM tbl_fact 
                                  WHERE MONTH(fecha_emision) = :mes 
                                  AND YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'
                                  GROUP BY DAY(fecha_emision)
                                  ORDER BY total DESC
                                  LIMIT 1");
            $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
            $mejor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mejor) {
                return "No hay datos para el mes actual.";
            }
            
            return "🏆 **MEJOR DÍA DEL MES**\n\n" .
                   "📅 Día: **{$mejor['dia']}** de {$fecha['mes_nombre']}\n" .
                   "💰 Ingresos: $" . number_format($mejor['total'], 2);
        }
        
        if (strpos($message, 'mes') !== false) {
            $stmt = $db->prepare("SELECT 
                                  MONTH(fecha_emision) as mes,
                                  COALESCE(SUM(total_general), 0) as total
                                  FROM tbl_fact 
                                  WHERE YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'
                                  GROUP BY MONTH(fecha_emision)
                                  ORDER BY total DESC
                                  LIMIT 1");
            $stmt->execute(['anio' => $fecha['anio']]);
            $mejor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mejor) {
                return "No hay datos para el año actual.";
            }
            
            return "🏆 **MEJOR MES DEL AÑO**\n\n" .
                   "📅 Mes: **" . getNombreMes($mejor['mes']) . "**\n" .
                   "💰 Ingresos: $" . number_format($mejor['total'], 2);
        }
        
        return "Especifica si quieres el mejor día o mes.";
    } catch (Exception $e) {
        return "Error al obtener mejor período.";
    }
}
function getPeorPeriodo($db, $message) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        if (strpos($message, 'dia') !== false || strpos($message, 'día') !== false) {
            $stmt = $db->prepare("SELECT 
                                  DAY(fecha_emision) as dia,
                                  COALESCE(SUM(total_general), 0) as total
                                  FROM tbl_fact 
                                  WHERE MONTH(fecha_emision) = :mes 
                                  AND YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'
                                  GROUP BY DAY(fecha_emision)
                                  HAVING total > 0
                                  ORDER BY total ASC
                                  LIMIT 1");
            $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
            $peor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$peor) {
                return "No hay datos suficientes para determinar el peor día.";
            }
            
            return "📉 **PEOR DÍA DEL MES** (con facturación)\n\n" .
                   "📅 Día: **{$peor['dia']}** de {$fecha['mes_nombre']}\n" .
                   "💰 Ingresos: $" . number_format($peor['total'], 2);
        }
        
        if (strpos($message, 'mes') !== false) {
            $stmt = $db->prepare("SELECT 
                                  MONTH(fecha_emision) as mes,
                                  COALESCE(SUM(total_general), 0) as total
                                  FROM tbl_fact 
                                  WHERE YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'
                                  GROUP BY MONTH(fecha_emision)
                                  ORDER BY total ASC
                                  LIMIT 1");
            $stmt->execute(['anio' => $fecha['anio']]);
            $peor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$peor) {
                return "No hay datos para el año actual.";
            }
            
            return "📉 **PEOR MES DEL AÑO**\n\n" .
                   "📅 Mes: **" . getNombreMes($peor['mes']) . "**\n" .
                   "💰 Ingresos: $" . number_format($peor['total'], 2);
        }
        
        return "Especifica si quieres el peor día o mes.";
    } catch (Exception $e) {
        return "Error al obtener peor período.";
    }
}


function getPromedios($db, $message) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        if (strpos($message, 'dia') !== false || strpos($message, 'día') !== false) {
            $stmt = $db->prepare("SELECT 
                                  COUNT(DISTINCT DAY(fecha_emision)) as dias_con_facturas,
                                  COALESCE(SUM(total_general), 0) as total_mes
                                  FROM tbl_fact 
                                  WHERE MONTH(fecha_emision) = :mes 
                                  AND YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'");
            $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $promedio = $data['dias_con_facturas'] > 0 ? $data['total_mes'] / $data['dias_con_facturas'] : 0;
            
            return "📊 **PROMEDIO DIARIO**\n\n" .
                   "📅 Mes: {$fecha['mes_nombre']}\n" .
                   "💰 Total mes: $" . number_format($data['total_mes'], 2) . "\n" .
                   "📆 Días con facturas: {$data['dias_con_facturas']}\n" .
                   "📈 Promedio por día: $" . number_format($promedio, 2);
        }
        
        if (strpos($message, 'mes') !== false) {
            $stmt = $db->prepare("SELECT 
                                  COUNT(DISTINCT MONTH(fecha_emision)) as meses_con_facturas,
                                  COALESCE(SUM(total_general), 0) as total_anio
                                  FROM tbl_fact 
                                  WHERE YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'");
            $stmt->execute(['anio' => $fecha['anio']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $promedio = $data['meses_con_facturas'] > 0 ? $data['total_anio'] / $data['meses_con_facturas'] : 0;
            
            return "📊 **PROMEDIO MENSUAL**\n\n" .
                   "📅 Año: {$fecha['anio']}\n" .
                   "💰 Total año: $" . number_format($data['total_anio'], 2) . "\n" .
                   "📆 Meses con facturas: {$data['meses_con_facturas']}\n" .
                   "📈 Promedio por mes: $" . number_format($promedio, 2);
        }
        
        return "Especifica si quieres promedio por día o por mes.";
    } catch (Exception $e) {
        return "Error al calcular promedios.";
    }
}

function getProyeccionMensual($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'");
        $stmt->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $proyeccion = ($actual / $fecha['dia']) * $fecha['dias_mes'];
        
        $stmt_plan = $db->prepare("SELECT importe FROM tbl_planes 
                                   WHERE anio = :anio AND mes_plan = :mes");
        $stmt_plan->execute(['anio' => $fecha['anio'], 'mes' => $fecha['mes_num']]);
        $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC)['importe'] ?? 0;
        
        $respuesta = "📈 **PROYECCIÓN MENSUAL**\n\n" .
                    "📅 Mes: {$fecha['mes_nombre']} {$fecha['anio']}\n" .
                    "📊 Día {$fecha['dia']} de {$fecha['dias_mes']} ({$fecha['porcentaje']}%)\n" .
                    "💰 Actual: $" . number_format($actual, 2) . "\n" .
                    "🔮 Proyección: $" . number_format($proyeccion, 2) . "\n";
        
        if ($plan > 0) {
            $cumplimiento_proyectado = ($proyeccion / $plan) * 100;
            $respuesta .= "🎯 Meta: $" . number_format($plan, 2) . "\n" .
                         "📈 Cumplimiento proyectado: " . number_format($cumplimiento_proyectado, 1) . "%\n";
            
            if ($cumplimiento_proyectado >= 100) {
                $respuesta .= "✅ ¡Proyectas superar la meta!\n";
            } else {
                $faltante = $plan - $proyeccion;
                $respuesta .= "⏳ Faltarían $" . number_format($faltante, 2) . " para la meta\n";
            }
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al calcular proyección.";
    }
}

function getSugerenciaGrafico() {
    return "📊 **PARA VER GRÁFICOS**\n\n" .
           "Los gráficos detallados están disponibles en:\n\n" .
           "1️⃣ **Dashboard principal** - Vista general con gráficos de ingresos\n" .
           "2️⃣ **Reportes** - Gráficos mensuales y anuales\n" .
           "3️⃣ **Rentabilidad** - Análisis gráfico de costos\n\n" .
           "👉 Puedes ir directamente con: **abrir reportes**";
}

function getEvolucionMensual($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        $anio_actual = $fecha['anio'];
        
        $stmt = $db->prepare("SELECT 
                              MONTH(fecha_emision) as mes,
                              COALESCE(SUM(total_general), 0) as total,
                              COUNT(*) as facturas
                              FROM tbl_fact 
                              WHERE YEAR(fecha_emision) = :anio 
                              AND estado != 'ANULADA'
                              GROUP BY MONTH(fecha_emision)
                              ORDER BY mes");
        $stmt->execute(['anio' => $anio_actual]);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($datos)) {
            return "No hay datos para el año $anio_actual";
        }
        
        $respuesta = "📈 **EVOLUCIÓN MENSUAL $anio_actual**\n\n";
        $total_anual = 0;
        
        foreach ($datos as $d) {
            $total_anual += $d['total'];
            $destacado = ($d['mes'] == $fecha['mes_num']) ? " ▶️ EN CURSO" : "";
            $respuesta .= "**" . getNombreMes($d['mes']) . "**$destacado\n" .
                         "   💰 $" . number_format($d['total'], 2) . " | 📄 {$d['facturas']} facturas\n";
        }
        
        $respuesta .= "\n💰 **Total anual:** $" . number_format($total_anual, 2);
        $respuesta .= "\n📅 **Cierre del mes actual:** " . date('d/m/Y', strtotime($fecha['fecha_completa']));
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener evolución mensual.";
    }
}
function getComparativaAnual($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        $anio_actual = $fecha['anio'];
        $anio_anterior = $anio_actual - 1;
        
        $stmt = $db->prepare("SELECT 
                              YEAR(fecha_emision) as anio,
                              COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE YEAR(fecha_emision) IN (:anio1, :anio2)
                              AND estado != 'ANULADA'
                              GROUP BY YEAR(fecha_emision)");
        $stmt->execute(['anio1' => $anio_actual, 'anio2' => $anio_anterior]);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totales = [];
        foreach ($datos as $d) {
            $totales[$d['anio']] = $d['total'];
        }
        
        $actual = $totales[$anio_actual] ?? 0;
        $anterior = $totales[$anio_anterior] ?? 0;
        
        $variacion = $anterior > 0 ? (($actual - $anterior) / $anterior) * 100 : 0;
        $tendencia = $variacion >= 0 ? '📈' : '📉';
        
        $respuesta = "📊 **COMPARATIVA ANUAL**\n\n" .
                    "**$anio_anterior:** $" . number_format($anterior, 2) . "\n" .
                    "**$anio_actual:** $" . number_format($actual, 2) . "\n\n" .
                    "$tendencia **Variación:** " . ($variacion >= 0 ? '+' : '') . number_format($variacion, 1) . "%\n" .
                    "📅 **Cierre actual:** " . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "\n";
        
        if ($actual > $anterior) {
            $diferencia = $actual - $anterior;
            $respuesta .= "✅ Incremento de $" . number_format($diferencia, 2);
        } elseif ($actual < $anterior) {
            $diferencia = $anterior - $actual;
            $respuesta .= "❌ Disminución de $" . number_format($diferencia, 2);
        } else {
            $respuesta .= "➡️ Sin cambios respecto al año anterior";
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al realizar comparativa anual.";
    }
}
function getRankingClientes($db) {
    try {
        $stmt = $db->query("SELECT 
                            c.nombre, c.codigo,
                            COUNT(f.id) as total_facturas,
                            COALESCE(SUM(f.total_general), 0) as total_compras
                            FROM clasif_clientes c
                            LEFT JOIN tbl_fact f ON c.id = f.cliente_id AND f.estado != 'ANULADA'
                            WHERE c.activo = 1
                            GROUP BY c.id
                            HAVING total_facturas > 0
                            ORDER BY total_compras DESC
                            LIMIT 5");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($clientes)) {
            return "No hay datos suficientes para el ranking.";
        }
        
        $respuesta = "🏆 **TOP 5 CLIENTES POR FACTURACIÓN**\n\n";
        $pos = 1;
        foreach ($clientes as $c) {
            $medalla = $pos == 1 ? '🥇' : ($pos == 2 ? '🥈' : ($pos == 3 ? '🥉' : '📌'));
            $respuesta .= "$medalla **{$c['codigo']}** - {$c['nombre']}\n" .
                         "   💰 $" . number_format($c['total_compras'], 2) . " | 📄 {$c['total_facturas']} facturas\n\n";
            $pos++;
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al generar ranking de clientes.";
    }
}

function getMetodoPagoPopular($db) {
    try {
        $stmt = $db->query("SELECT 
                            tp.descripcion,
                            COUNT(f.id) as veces_usado,
                            COALESCE(SUM(f.total_general), 0) as total
                            FROM tipos_pago tp
                            LEFT JOIN tbl_fact f ON tp.id = f.tipo_pago_id AND f.estado != 'ANULADA'
                            GROUP BY tp.id
                            ORDER BY veces_usado DESC
                            LIMIT 1");
        $metodo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$metodo || $metodo['veces_usado'] == 0) {
            return "No hay datos suficientes sobre métodos de pago.";
        }
        
        return "💳 **MÉTODO DE PAGO MÁS POPULAR**\n\n" .
               "**{$metodo['descripcion']}**\n" .
               "📊 Usado: **{$metodo['veces_usado']}** veces\n" .
               "💰 Total procesado: $" . number_format($metodo['total'], 2);
    } catch (Exception $e) {
        return "Error al obtener método de pago popular.";
    }
}

// ==================== FUNCIONES DE CIERRES ====================

function getUltimoCierreMensual($db) {
    try {
        $stmt = $db->query("SELECT * FROM historico_cierres 
                            WHERE tipo = 1 
                            ORDER BY fecha_ejecucion DESC 
                            LIMIT 1");
        $cierre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cierre) {
            return "No hay cierres mensuales registrados.";
        }
        
        $fecha = date('d/m/Y H:i', strtotime($cierre['fecha_ejecucion']));
        $mes = getNombreMes($cierre['periodo_mes']);
        
        return "📅 **ÚLTIMO CIERRE MENSUAL**\n\n" .
               "Mes: **$mes {$cierre['periodo_anio']}**\n" .
               "Fecha: $fecha\n" .
               "📄 Facturas: {$cierre['total_facturas']}\n" .
               "💰 Importe: $" . number_format($cierre['importe_total'], 2) . "\n" .
               "✅ Pagadas: {$cierre['cant_pagadas']}\n" .
               "📝 Contabilizadas: {$cierre['cant_contabilizadas']}\n\n" .
               "📌 Observaciones: {$cierre['observaciones']}";
    } catch (Exception $e) {
        return "Error al obtener último cierre.";
    }
}

function getHistorialCierres($db) {
    try {
        $stmt = $db->query("SELECT * FROM historico_cierres 
                            ORDER BY fecha_ejecucion DESC 
                            LIMIT 5");
        $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($cierres)) {
            return "No hay cierres registrados.";
        }
        
        $respuesta = "📅 **HISTORIAL DE CIERRES**\n\n";
        foreach ($cierres as $c) {
            $tipo = $c['tipo'] == 1 ? 'Mensual' : 'Anual';
            $fecha = date('d/m/Y', strtotime($c['fecha_ejecucion']));
            $periodo = $c['tipo'] == 1 ? getNombreMes($c['periodo_mes']) . " {$c['periodo_anio']}" : "Año {$c['periodo_anio']}";
            $respuesta .= "**$tipo - $periodo**\n" .
                         "   Fecha: $fecha\n" .
                         "   💰 $" . number_format($c['importe_total'], 2) . " | 📄 {$c['total_facturas']} facturas\n\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener historial de cierres.";
    }
}

function getProximoCierre() {
    $mes_actual = date('n');
    $anio_actual = date('Y');
    $dia_actual = date('j');
    $dias_mes = date('t');
    
    $dias_restantes = $dias_mes - $dia_actual;
    
    return "📅 **PRÓXIMO CIERRE**\n\n" .
           "El próximo cierre mensual será el **" . date('d/m/Y', strtotime('last day of this month')) . "**\n" .
           "⏳ Quedan **$dias_restantes días** para el cierre de " . getNombreMes($mes_actual) . " $anio_actual\n\n" .
           "💡 Asegúrate de tener todas las facturas contabilizadas antes del cierre.";
}

// ==================== FUNCIONES DE PLANES ====================

function getPlanMensual($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT importe FROM tbl_planes 
                              WHERE anio = :anio AND mes_plan = :mes");
        $stmt->execute(['anio' => $fecha['anio'], 'mes' => $fecha['mes_num']]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            return "No hay plan definido para el mes actual.";
        }
        
        $stmt_ing = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total 
                                   FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes 
                                   AND YEAR(fecha_emision) = :anio 
                                   AND estado != 'ANULADA'");
        $stmt_ing->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $real = $stmt_ing->fetch(PDO::FETCH_ASSOC)['total'];
        
        $cumplimiento = ($real / $plan['importe']) * 100;
        
        return "🎯 **PLAN DEL MES**\n\n" .
               "Mes: {$fecha['mes_nombre']} {$fecha['anio']}\n" .
               "Meta: $" . number_format($plan['importe'], 2) . "\n" .
               "💰 Real: $" . number_format($real, 2) . "\n" .
               "📊 Cumplimiento: **" . number_format($cumplimiento, 1) . "%**\n" .
               "📅 Cierre programado: " . date('d/m/Y', strtotime($fecha['fecha_completa']));
    } catch (Exception $e) {
        return "Error al obtener plan mensual.";
    }
}

function getPlanAnual($db) {
    try {
        $anio_actual = date('Y');
        
        $stmt = $db->prepare("SELECT SUM(importe) as total, COUNT(*) as meses 
                              FROM tbl_planes 
                              WHERE anio = :anio");
        $stmt->execute(['anio' => $anio_actual]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan || $plan['total'] == 0) {
            return "No hay plan definido para el año actual.";
        }
        
        $stmt_ing = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total 
                                   FROM tbl_fact 
                                   WHERE YEAR(fecha_emision) = :anio 
                                   AND estado != 'ANULADA'");
        $stmt_ing->execute(['anio' => $anio_actual]);
        $real = $stmt_ing->fetch(PDO::FETCH_ASSOC)['total'];
        
        $cumplimiento = ($real / $plan['total']) * 100;
        
        return "🎯 **PLAN ANUAL $anio_actual**\n\n" .
               "Meta anual: $" . number_format($plan['total'], 2) . "\n" .
               "💰 Real: $" . number_format($real, 2) . "\n" .
               "📊 Cumplimiento: **" . number_format($cumplimiento, 1) . "%**\n" .
               "📅 Meses con plan: {$plan['meses']}";
    } catch (Exception $e) {
        return "Error al obtener plan anual.";
    }
}

function getCumplimientoPlan($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        $anio_actual = $fecha['anio'];
        
        $stmt = $db->prepare("SELECT mes_plan, importe FROM tbl_planes 
                              WHERE anio = :anio 
                              ORDER BY mes_plan");
        $stmt->execute(['anio' => $anio_actual]);
        $planes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($planes)) {
            return "No hay planes definidos para el año $anio_actual";
        }
        
        $stmt_ing = $db->prepare("SELECT 
                                  MONTH(fecha_emision) as mes,
                                  COALESCE(SUM(total_general), 0) as total
                                  FROM tbl_fact 
                                  WHERE YEAR(fecha_emision) = :anio 
                                  AND estado != 'ANULADA'
                                  GROUP BY MONTH(fecha_emision)");
        $stmt_ing->execute(['anio' => $anio_actual]);
        $reales = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);
        
        $reales_por_mes = [];
        foreach ($reales as $r) {
            $reales_por_mes[$r['mes']] = $r['total'];
        }
        
        $respuesta = "📊 **CUMPLIMIENTO DE PLANES $anio_actual**\n\n";
        $total_plan = 0;
        $total_real = 0;
        
        foreach ($planes as $p) {
            $mes = $p['mes_plan'];
            $plan_mes = $p['importe'];
            $real_mes = $reales_por_mes[$mes] ?? 0;
            $cumplimiento = $plan_mes > 0 ? ($real_mes / $plan_mes) * 100 : 0;
            
            $total_plan += $plan_mes;
            $total_real += $real_mes;
            
            $emoji = $cumplimiento >= 100 ? '✅' : ($cumplimiento >= 80 ? '⚠️' : '❌');
            $destacado = ($mes == $fecha['mes_num']) ? " ▶️ EN CURSO" : "";
            $respuesta .= "**" . getNombreMes($mes) . "**$destacado $emoji\n" .
                         "   Meta: $" . number_format($plan_mes, 2) . "\n" .
                         "   Real: $" . number_format($real_mes, 2) . " (" . number_format($cumplimiento, 1) . "%)\n\n";
        }
        
        if ($total_plan > 0) {
            $cumplimiento_total = ($total_real / $total_plan) * 100;
            $respuesta .= "**TOTAL ANUAL**\n" .
                         "   Meta: $" . number_format($total_plan, 2) . "\n" .
                         "   Real: $" . number_format($total_real, 2) . " (" . number_format($cumplimiento_total, 1) . "%)\n";
        }
        
        $respuesta .= "\n📅 **Cierre del mes actual:** " . date('d/m/Y', strtotime($fecha['fecha_completa']));
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al calcular cumplimiento de planes.";
    }
}

function getMetaMensual($db) {
    try {
        $fecha = getFechaMaestraSistema($db);
        
        $stmt = $db->prepare("SELECT importe FROM tbl_planes 
                              WHERE anio = :anio AND mes_plan = :mes");
        $stmt->execute(['anio' => $fecha['anio'], 'mes' => $fecha['mes_num']]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meta) {
            return "No hay meta definida para el mes actual.";
        }
        
        $stmt_ing = $db->prepare("SELECT COALESCE(SUM(total_general), 0) as total 
                                   FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes 
                                   AND YEAR(fecha_emision) = :anio 
                                   AND estado != 'ANULADA'");
        $stmt_ing->execute(['mes' => $fecha['mes_num'], 'anio' => $fecha['anio']]);
        $real = $stmt_ing->fetch(PDO::FETCH_ASSOC)['total'];
        
        $faltante = $meta['importe'] - $real;
        $dias_restantes = $fecha['dias_restantes'];
        
        $necesario_diario = $dias_restantes > 0 ? $faltante / $dias_restantes : 0;
        
        $respuesta = "🎯 **META MENSUAL**\n\n" .
                    "Mes: {$fecha['mes_nombre']} {$fecha['anio']}\n" .
                    "Meta: $" . number_format($meta['importe'], 2) . "\n" .
                    "💰 Actual: $" . number_format($real, 2) . "\n" .
                    "⏳ Faltan: $" . number_format($faltante, 2) . "\n" .
                    "📅 Días restantes: $dias_restantes\n" .
                    "📅 Cierre: " . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "\n";
        
        if ($faltante > 0) {
            $respuesta .= "📈 Necesitas $" . number_format($necesario_diario, 2) . " por día para alcanzar la meta\n";
        } else {
            $respuesta .= "✅ ¡Meta superada por $" . number_format(abs($faltante), 2) . "!\n";
        }
        
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener meta mensual.";
    }
}


// ==================== FUNCIONES DE HISTORIAL ====================

function getUltimasActividades($db) {
    try {
        $stmt = $db->query("SELECT * FROM historico_operaciones 
                            ORDER BY fecha_hora DESC 
                            LIMIT 5");
        $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($actividades)) {
            return "No hay actividades recientes.";
        }
        
        $respuesta = "🔄 **ÚLTIMAS 5 ACTIVIDADES**\n\n";
        foreach ($actividades as $a) {
            $fecha = date('d/m/Y H:i', strtotime($a['fecha_hora']));
            $respuesta .= "**{$a['usuario_nombre']}** - $fecha\n" .
                         "   {$a['operacion']}\n";
            if (!empty($a['descripcion'])) {
                $respuesta .= "   📝 " . substr($a['descripcion'], 0, 50) . "...\n";
            }
            $respuesta .= "\n";
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener últimas actividades.";
    }
}

function getActividadesUsuario($db, $usuario) {
    try {
        $stmt = $db->prepare("SELECT h.* FROM historico_operaciones h
                              WHERE h.usuario_nombre LIKE :usuario
                              ORDER BY h.fecha_hora DESC 
                              LIMIT 5");
        $stmt->execute(['usuario' => "%$usuario%"]);
        $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($actividades)) {
            return "No hay actividades de '$usuario'";
        }
        
        $respuesta = "👤 **Actividades de $usuario**\n\n";
        foreach ($actividades as $a) {
            $fecha = date('d/m/Y H:i', strtotime($a['fecha_hora']));
            $respuesta .= "• $fecha - {$a['operacion']}\n";
            if (!empty($a['descripcion'])) {
                $respuesta .= "  {$a['descripcion']}\n";
            }
        }
        return $respuesta;
    } catch (Exception $e) {
        return "Error al obtener actividades del usuario.";
    }
}

function getTotalOperaciones($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM historico_operaciones");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT fecha_hora FROM historico_operaciones ORDER BY fecha_hora DESC LIMIT 1");
        $ultima = $stmt->fetch(PDO::FETCH_ASSOC);
        $fecha_ultima = $ultima ? date('d/m/Y H:i', strtotime($ultima['fecha_hora'])) : 'N/A';
        
        return "📊 **ESTADÍSTICAS DE OPERACIONES**\n\n" .
               "Total de operaciones: **$total**\n" .
               "Última operación: $fecha_ultima";
    } catch (Exception $e) {
        return "Error al obtener total de operaciones.";
    }
}

// ==================== FUNCIÓN DE BÚSQUEDA ====================

function buscarEnSistema($db, $tipo, $termino) {
    try {
        switch($tipo) {
            case 'factura':
                $stmt = $db->prepare("SELECT f.*, c.nombre as cliente 
                                      FROM tbl_fact f 
                                      LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                                      WHERE f.no_fact LIKE :termino 
                                      ORDER BY f.fecha_emision DESC LIMIT 3");
                $stmt->execute(['termino' => "%$termino%"]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($resultados)) {
                    return "🔍 No encontré facturas con '$termino'";
                }
                
                $respuesta = "🔍 **Facturas encontradas:**\n\n";
                foreach ($resultados as $f) {
                    $fecha = date('d/m/Y', strtotime($f['fecha_emision']));
                    $estado = getEstadoEmoji($f['estado']);
                    $respuesta .= "• {$f['no_fact']} | {$f['cliente']} | $fecha | $" . 
                                 number_format($f['total_general'], 2) . " $estado\n";
                }
                return $respuesta;
                
            case 'cliente':
                $stmt = $db->prepare("SELECT * FROM clasif_clientes 
                                      WHERE nombre LIKE :termino OR codigo LIKE :termino 
                                      ORDER BY fechaRegistro DESC LIMIT 3");
                $stmt->execute(['termino' => "%$termino%"]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($resultados)) {
                    return "🔍 No encontré clientes con '$termino'";
                }
                
                $respuesta = "🔍 **Clientes encontrados:**\n\n";
                foreach ($resultados as $c) {
                    $estado = $c['activo'] ? '✅ Activo' : '❌ Inactivo';
                    $respuesta .= "• {$c['codigo']} - {$c['nombre']}\n  $estado | Contrato: {$c['ContratoNo']}\n";
                }
                return $respuesta;
                
            case 'servicio':
                $stmt = $db->prepare("SELECT s.*, c.descripcion as categoria 
                                      FROM clasif_serv s
                                      LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                                      WHERE s.descripcion LIKE :termino OR s.codigo LIKE :termino 
                                      LIMIT 3");
                $stmt->execute(['termino' => "%$termino%"]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($resultados)) {
                    return "🔍 No encontré servicios con '$termino'";
                }
                
                $respuesta = "🔍 **Servicios encontrados:**\n\n";
                foreach ($resultados as $s) {
                    $estado = $s['activo'] ? '✅' : '❌';
                    $respuesta .= "• {$s['codigo']} - {$s['descripcion']}\n  $estado | $" . 
                                 number_format($s['costo'], 2) . " | {$s['categoria']}\n";
                }
                return $respuesta;
                
            case 'categoria':
                $stmt = $db->prepare("SELECT * FROM clasif_cat_de_serv 
                                      WHERE descripcion LIKE :termino OR codigo LIKE :termino 
                                      LIMIT 3");
                $stmt->execute(['termino' => "%$termino%"]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($resultados)) {
                    return "🔍 No encontré categorías con '$termino'";
                }
                
                $respuesta = "🔍 **Categorías encontradas:**\n\n";
                foreach ($resultados as $c) {
                    $estado = $c['activo'] ? '✅' : '❌';
                    $respuesta .= "• {$c['codigo']} - {$c['descripcion']} $estado\n";
                }
                return $respuesta;
        }
    } catch (Exception $e) {
        return "Error en la búsqueda.";
    }
}

// ==================== FUNCIÓN AUXILIAR PARA ESTADOS ====================

function getEstadoEmoji($estado) {
    switch($estado) {
        case 'PENDIENTE': return '⏳';
        case 'CONTABILIZADA': return '📝';
        case 'PAGADA': return '✅';
        case 'ANULADA': return '❌';
        case 'CERRADA': return '🔒';
        default: return '📄';
    }
}

// ==================== FUNCIÓN AUXILIAR PARA NOMBRES DE MES ====================

function getNombreMes($numero) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$numero] ?? 'Mes desconocido';
}

// ==================== NUEVAS FUNCIONES PARA CIERRE ACTUAL Y FECHA DE OPERACIONES ====================

/**
 * Obtiene información del cierre actual (en progreso)
 * Utiliza la fecha de configuración del sistema como las demás funciones
 */
function getCierreActual($db) {
    try {
        // 🔥 OBTENER FECHA DE CONFIGURACIÓN (como en getFacturasHoy, getIngresosMes, etc.)
        $fecha = getFechaMaestraSistema($db);
        
        $mes_actual = $fecha['mes_num'];
        $anio_actual = $fecha['anio'];
        $dia_actual = $fecha['dia'];        // Día actual del servidor
        $dias_mes = $fecha['dias_mes'];
        $nombre_mes = $fecha['mes_nombre'];
        
        // Obtener facturas del período activo (como en getFacturasDelMes)
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as total_facturas,
                              COALESCE(SUM(total_general), 0) as importe_total,
                              SUM(CASE WHEN estado = 'PAGADA' THEN 1 ELSE 0 END) as pagadas,
                              SUM(CASE WHEN estado = 'CONTABILIZADA' THEN 1 ELSE 0 END) as contabilizadas,
                              SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                              SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) as anuladas
                              FROM tbl_fact 
                              WHERE MONTH(fecha_emision) = :mes 
                              AND YEAR(fecha_emision) = :anio");
        $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular días restantes y porcentaje (usando los datos de getFechaMaestraSistema)
        $dias_restantes = $fecha['dias_restantes'];
        $porcentaje = $fecha['porcentaje'];
        
        // Obtener último cierre para comparar
        $stmt_ultimo = $db->query("SELECT importe_total, periodo_mes, periodo_anio 
                                   FROM historico_cierres 
                                   WHERE tipo = 1 
                                   ORDER BY fecha_ejecucion DESC 
                                   LIMIT 1");
        $ultimo_cierre = $stmt_ultimo->fetch(PDO::FETCH_ASSOC);
        
        $respuesta = "📊 **CIERRE ACTUAL DEL MES**\n\n";
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $respuesta .= "📅 Período: **$nombre_mes $anio_actual**\n";
        $respuesta .= "📆 Día $dia_actual de $dias_mes (**$porcentaje%** transcurrido)\n";
        $respuesta .= "⏳ Días restantes: **$dias_restantes**\n\n";
        
        $respuesta .= "📋 **ESTADO ACTUAL**\n";
        $respuesta .= "📄 Total facturas: **{$data['total_facturas']}**\n";
        $respuesta .= "💰 Importe total: **$" . number_format($data['importe_total'], 2) . "**\n\n";
        
        $respuesta .= "📊 **DISTRIBUCIÓN POR ESTADO**\n";
        $respuesta .= "• ✅ Pagadas: **{$data['pagadas']}**\n";
        $respuesta .= "• 📝 Contabilizadas: **{$data['contabilizadas']}**\n";
        $respuesta .= "• ⏳ Pendientes: **{$data['pendientes']}**\n";
        $respuesta .= "• ❌ Anuladas: **{$data['anuladas']}**\n\n";
        
        if ($ultimo_cierre) {
            $variacion = $data['importe_total'] - $ultimo_cierre['importe_total'];
            $variacion_porcentaje = $ultimo_cierre['importe_total'] > 0 ? round(($variacion / $ultimo_cierre['importe_total']) * 100, 1) : 0;
            $emoji = $variacion >= 0 ? '📈' : '📉';
            
            $respuesta .= "📊 **COMPARATIVA CON MES ANTERIOR**\n";
            $respuesta .= "$emoji " . getNombreMes($ultimo_cierre['periodo_mes']) . " {$ultimo_cierre['periodo_anio']}: $" . number_format($ultimo_cierre['importe_total'], 2) . "\n";
            $respuesta .= "   Diferencia: " . ($variacion >= 0 ? '+' : '') . "$" . number_format($variacion, 2) . " (" . ($variacion >= 0 ? '+' : '') . "$variacion_porcentaje%)\n\n";
        }
        
        // Proyección (como en getProyeccionMensual)
        if ($dia_actual > 0 && $data['total_facturas'] > 0) {
            $promedio_diario = $data['importe_total'] / $dia_actual;
            $proyeccion = $promedio_diario * $dias_mes;
            $respuesta .= "🔮 **PROYECCIÓN**\n";
            $respuesta .= "• Promedio diario: $" . number_format($promedio_diario, 2) . "\n";
            $respuesta .= "• Proyección fin de mes: $" . number_format($proyeccion, 2) . "\n";
        }
        
        return $respuesta;
        
    } catch (Exception $e) {
        return "Error al obtener información del cierre actual: " . $e->getMessage();
    }
}


/**
 * Obtiene operaciones filtradas por fecha específica
 */
function getOperacionesPorFecha($db, $fecha) {
    try {
        // Si viene como "hoy", "ayer", etc.
        if ($fecha === 'hoy') {
            $fecha_buscar = date('Y-m-d');
            $titulo = "HOY (" . date('d/m/Y') . ")";
        } elseif ($fecha === 'ayer') {
            $fecha_buscar = date('Y-m-d', strtotime('-1 day'));
            $titulo = "AYER (" . date('d/m/Y', strtotime('-1 day')) . ")";
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha_buscar = $fecha;
            $titulo = date('d/m/Y', strtotime($fecha));
        } else {
            return "Formato de fecha no válido. Usa: **operaciones del día [DD/MM/AAAA]**";
        }
        
        // Buscar operaciones en histórico
        $stmt = $db->prepare("SELECT * FROM historico_operaciones 
                              WHERE DATE(fecha_hora) = :fecha 
                              ORDER BY fecha_hora DESC");
        $stmt->execute(['fecha' => $fecha_buscar]);
        $operaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($operaciones)) {
            return "📭 No hay operaciones registradas para el $titulo.";
        }
        
        $respuesta = "📋 **OPERACIONES DEL $titulo**\n\n";
        $respuesta .= "Total de operaciones: **" . count($operaciones) . "**\n\n";
        
        $conteo_tipos = [];
        foreach ($operaciones as $op) {
            $tipo = $op['operacion'];
            if (!isset($conteo_tipos[$tipo])) {
                $conteo_tipos[$tipo] = 0;
            }
            $conteo_tipos[$tipo]++;
        }
        
        $respuesta .= "📊 **RESUMEN POR TIPO**\n";
        foreach ($conteo_tipos as $tipo => $cantidad) {
            $emoji = getEmojiOperacion($tipo);
            $respuesta .= "$emoji $tipo: **$cantidad**\n";
        }
        $respuesta .= "\n";
        
        $respuesta .= "🔄 **ÚLTIMAS 10 OPERACIONES**\n";
        $limite = 0;
        foreach ($operaciones as $op) {
            if ($limite >= 10) break;
            $hora = date('H:i:s', strtotime($op['fecha_hora']));
            $emoji = getEmojiOperacion($op['operacion']);
            $respuesta .= "• $hora $emoji **{$op['usuario_nombre']}**: {$op['operacion']}\n";
            if (!empty($op['descripcion'])) {
                $respuesta .= "  📝 " . substr($op['descripcion'], 0, 50) . (strlen($op['descripcion']) > 50 ? '...' : '') . "\n";
            }
            $limite++;
        }
        
        return $respuesta;
        
    } catch (Exception $e) {
        return "Error al obtener operaciones: " . $e->getMessage();
    }
}

/**
 * Obtiene operaciones de un día específico (formato DD/MM/AAAA)
 */
function getOperacionesPorDiaEspecifico($db, $dia, $mes, $anio) {
    try {
        $fecha = "$anio-$mes-$dia";
        $fecha_formateada = date('d/m/Y', strtotime($fecha));
        
        return getOperacionesPorFecha($db, $fecha);
        
    } catch (Exception $e) {
        return "Error al procesar la fecha. Formato correcto: **operaciones del día 15/03/2025**";
    }
}

/**
 * Obtiene el emoji según el tipo de operación
 */
function getEmojiOperacion($operacion) {
    $operacion = strtoupper($operacion);
    
    if (strpos($operacion, 'LOGIN') !== false) return '🔐';
    if (strpos($operacion, 'LOGOUT') !== false) return '🚪';
    if (strpos($operacion, 'CREAR') !== false) {
        if (strpos($operacion, 'FACTURA') !== false) return '📄➕';
        if (strpos($operacion, 'CLIENTE') !== false) return '👤➕';
        if (strpos($operacion, 'SERVICIO') !== false) return '🔧➕';
        if (strpos($operacion, 'CATEGORIA') !== false) return '📁➕';
        if (strpos($operacion, 'USUARIO') !== false) return '👥➕';
        return '➕';
    }
    if (strpos($operacion, 'EDITAR') !== false || strpos($operacion, 'ACTUALIZAR') !== false) return '✏️';
    if (strpos($operacion, 'ELIMINAR') !== false || strpos($operacion, 'BORRAR') !== false) return '🗑️';
    if (strpos($operacion, 'IMPRIMIR') !== false) return '🖨️';
    if (strpos($operacion, 'CIERRE') !== false) return '📊';
    if (strpos($operacion, 'PAGO') !== false) return '💰';
    
    return '📌';
}

/**
 * Obtiene resumen de operaciones por fecha (versión simplificada para consultas rápidas)
 */
function getResumenOperacionesFecha($db, $fecha_descripcion, $fecha_sql) {
    try {
        $stmt = $db->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(DISTINCT usuario_id) as usuarios_activos,
                              COUNT(DISTINCT operacion) as tipos_operacion
                              FROM historico_operaciones 
                              WHERE DATE(fecha_hora) = :fecha");
        $stmt->execute(['fecha' => $fecha_sql]);
        $resumen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resumen['total'] == 0) {
            return "📭 No hay operaciones registradas para $fecha_descripcion.";
        }
        
        // Obtener primeras y últimas operaciones
        $stmt_extremos = $db->prepare("SELECT 
                                       MIN(fecha_hora) as primera,
                                       MAX(fecha_hora) as ultima
                                       FROM historico_operaciones 
                                       WHERE DATE(fecha_hora) = :fecha");
        $stmt_extremos->execute(['fecha' => $fecha_sql]);
        $extremos = $stmt_extremos->fetch(PDO::FETCH_ASSOC);
        
        $hora_primera = date('H:i:s', strtotime($extremos['primera']));
        $hora_ultima = date('H:i:s', strtotime($extremos['ultima']));
        
        return "📊 **RESUMEN DE OPERACIONES - $fecha_descripcion**\n\n" .
               "📋 Total operaciones: **{$resumen['total']}**\n" .
               "👥 Usuarios activos: **{$resumen['usuarios_activos']}**\n" .
               "🔢 Tipos de operación: **{$resumen['tipos_operacion']}**\n" .
               "⏱️ Primera operación: **$hora_primera**\n" .
               "⏱️ Última operación: **$hora_ultima**\n\n" .
               "💡 Para ver el detalle, escribe: **operaciones del día " . date('d/m/Y', strtotime($fecha_sql)) . "**";
        
    } catch (Exception $e) {
        return "Error al obtener resumen de operaciones.";
    }
}
// =============================================================================
// FUNCIONES PARA CONSULTAR LA FECHA MAESTRA (CIERRE DE OPERACIONES)
// =============================================================================

/**
 * Obtiene la fecha maestra del sistema (desde la BD a través de configuracion_sistema)
 * @param PDO|null $db Conexión a la base de datos
 * @return array Con todos los componentes de la fecha
 */
function getFechaMaestraSistema($db = null) {
    // Variable con la fecha de cierre (último día del mes y año de configuración)
    $fecha_cierre_bd = date('Y-m-t'); // Por defecto: último día del mes actual
    
    try {
        // Intentar obtener la fecha de inicio de operaciones desde configuración
        if ($db) {
            $stmt = $db->query("SELECT fecha_inicio_operaciones FROM configuracion_sistema LIMIT 1");
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && !empty($resultado['fecha_inicio_operaciones'])) {
                // Obtener el año y mes de la fecha configurada
                $fecha_config = $resultado['fecha_inicio_operaciones'];
                $anio = date('Y', strtotime($fecha_config));
                $mes = date('m', strtotime($fecha_config));
                
                // Construir la fecha como el ÚLTIMO DÍA de ese mes y año
                $fecha_cierre_bd = date('Y-m-t', strtotime("{$anio}-{$mes}-01"));
            }
        }
    } catch (Exception $e) {
        // Si hay error, usar último día del mes actual
        $fecha_cierre_bd = date('Y-m-t');
    }
    
    // Crear variables derivadas de la fecha de cierre
    $timestamp_cierre = strtotime($fecha_cierre_bd);
    $anio_cierre = date('Y', $timestamp_cierre);
    $mes_cierre_num = (int)date('m', $timestamp_cierre);
    $dia_cierre = (int)date('d', $timestamp_cierre);
    $dias_mes = (int)date('t', $timestamp_cierre);
    
    // Calcular días restantes (basado en el día actual del servidor, pero dentro del mes de cierre)
    $dia_actual_servidor = (int)date('j');
    $dias_restantes = $dias_mes - $dia_actual_servidor;
    $porcentaje = round(($dia_actual_servidor / $dias_mes) * 100, 1);
    
    // Array con toda la información
    return [
        'fecha_completa' => $fecha_cierre_bd,              // Último día del mes de cierre
        'fecha_inicio_mes' => date('Y-m-01', $timestamp_cierre), // Primer día del mes de cierre
        'anio' => $anio_cierre,
        'mes_num' => $mes_cierre_num,
        'mes_nombre' => getNombreMes($mes_cierre_num),
        'dia' => $dia_actual_servidor,                     // Día actual del servidor
        'dia_cierre' => $dia_cierre,                       // Último día del mes (ej: 31)
        'trimestre' => ceil($mes_cierre_num / 3),
        'timestamp' => $timestamp_cierre,
        'dias_mes' => $dias_mes,
        'dias_restantes' => max(0, $dias_restantes),
        'porcentaje' => min(100, max(0, $porcentaje))
    ];
}


/**
 * Responde con la fecha de cierre de operaciones actual
 */

function getRespuestaFechaCierreOperaciones($usuario_nombre) {
    global $db; // Obtener la conexión global
    
    $fecha = getFechaMaestraSistema($db);
    
    // Días de la semana en español
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $dia_semana_num = date('w', $fecha['timestamp']);
    $dia_semana_texto = $dias[$dia_semana_num];
    
    return "📅 **FECHA DE CIERRE DE OPERACIONES**\n\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "📌 **Fecha maestra del sistema:**\n" .
           "• Cierre del mes: **" . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "**\n" .
           "• Día actual: **{$fecha['dia']}**\n" .
           "• Mes: **{$fecha['mes_nombre']}** ({$fecha['mes_num']})\n" .
           "• Año: **{$fecha['anio']}**\n" .
           "• Trimestre: **{$fecha['trimestre']}° trimestre**\n" .
           "• Día de la semana: **$dia_semana_texto**\n\n" .
           
           "📊 **Operaciones en curso:**\n" .
           "• Estamos trabajando con **{$fecha['mes_nombre']} {$fecha['anio']}**\n" .
           "• Cierre programado: **{$fecha['dia_cierre']}** de {$fecha['mes_nombre']}\n" .
           "• Este mes tiene **{$fecha['dias_mes']} días**\n" .
           "• Días transcurridos: **{$fecha['dia']}** (**{$fecha['porcentaje']}%**)\n" .
           "• Días restantes: **{$fecha['dias_restantes']}**\n\n" .
           
           "💡 **Puedes consultar:**\n" .
           "• **trimestre actual** - Ver información del trimestre\n" .
           "• **resumen del trimestre** - Estadísticas del trimestre\n" .
           "• **operaciones de hoy** - Actividades del día\n";
}

/**
 * Responde con el trimestre actual
 */
function getRespuestaTrimestreActual($usuario_nombre) {
    global $db;
    
    $fecha = getFechaMaestraSistema($db);
    $trimestre = $fecha['trimestre'];
    
    // Determinar los meses del trimestre
    $meses_trimestre = [
        1 => ['Enero', 'Febrero', 'Marzo'],
        2 => ['Abril', 'Mayo', 'Junio'],
        3 => ['Julio', 'Agosto', 'Septiembre'],
        4 => ['Octubre', 'Noviembre', 'Diciembre']
    ];
    
    $meses = $meses_trimestre[$trimestre];
    $mes_actual_nombre = $fecha['mes_nombre'];
    
    // Determinar en qué mes del trimestre estamos
    $posicion = array_search($mes_actual_nombre, $meses) + 1;
    
    // Determinar trimestre anterior y siguiente
    $trimestre_anterior = $trimestre > 1 ? $trimestre - 1 : 4;
    $trimestre_siguiente = $trimestre < 4 ? $trimestre + 1 : 1;
    $anio_anterior = $trimestre == 1 ? $fecha['anio'] - 1 : $fecha['anio'];
    $anio_siguiente = $trimestre == 4 ? $fecha['anio'] + 1 : $fecha['anio'];
    
    return "📊 **TRIMESTRE ACTUAL**\n\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "📍 Estamos en el **{$trimestre}° trimestre** de {$fecha['anio']}\n" .
           "📅 Cierre del mes actual: **" . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "**\n\n" .
           
           "📆 **Meses del trimestre:**\n" .
           "• 1° mes: {$meses[0]}\n" .
           "• 2° mes: {$meses[1]}\n" .
           "• 3° mes: {$meses[2]}\n\n" .
           
           "🎯 **Posición actual:**\n" .
           "• Mes actual: **$mes_actual_nombre**\n" .
           "• Es el **{$posicion}° mes** del trimestre\n" .
           "• Progreso del trimestre: **" . round(($posicion / 3) * 100, 1) . "%**\n\n" .
           
           "📅 **Trimestres cercanos:**\n" .
           "• Anterior: {$trimestre_anterior}° trimestre de {$anio_anterior}\n" .
           "• Siguiente: {$trimestre_siguiente}° trimestre de {$anio_siguiente}\n\n" .
           
           "💡 **Para ver datos específicos, escribe:**\n" .
           "• **resumen del trimestre** - Estadísticas completas del trimestre\n" .
           "• **resumen del mes** - Datos del mes actual ({$fecha['mes_nombre']})\n" .
           "• **meta mensual** - Ver la meta del mes";
}


/**
 * Resumen completo del trimestre con datos reales de facturación
 */
function getResumenTrimestre($db, $usuario_nombre) {
    $fecha = getFechaMaestraSistema($db);
    $anio = $fecha['anio'];
    $trimestre = $fecha['trimestre'];
    $mes_actual = $fecha['mes_num'];
    
    // Calcular meses del trimestre
    $mes_inicio = ($trimestre - 1) * 3 + 1;
    $mes_fin = $mes_inicio + 2;
    
    // Consultar facturas del trimestre
    $stmt = $db->prepare("SELECT 
                          COUNT(*) as total_facturas,
                          COALESCE(SUM(total_general), 0) as total_ingresos,
                          MONTH(fecha_emision) as mes
                          FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio
                          AND MONTH(fecha_emision) BETWEEN :mes_inicio AND :mes_fin
                          AND estado != 'ANULADA'
                          GROUP BY MONTH(fecha_emision)
                          ORDER BY mes");
    $stmt->execute([
        'anio' => $anio,
        'mes_inicio' => $mes_inicio,
        'mes_fin' => $mes_fin
    ]);
    $datos_meses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar datos por mes
    $ingresos_por_mes = [];
    $facturas_por_mes = [];
    foreach ($datos_meses as $d) {
        $ingresos_por_mes[$d['mes']] = $d['total_ingresos'];
        $facturas_por_mes[$d['mes']] = $d['total_facturas'];
    }
    
    // Totales del trimestre
    $total_ingresos = array_sum($ingresos_por_mes);
    $total_facturas = array_sum($facturas_por_mes);
    
    // Calcular promedio mensual
    $promedio_ingresos = count($datos_meses) > 0 ? $total_ingresos / count($datos_meses) : 0;
    $promedio_facturas = count($datos_meses) > 0 ? $total_facturas / count($datos_meses) : 0;
    
    // Nombres de meses
    $meses_nombres = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    $respuesta = "📊 **RESUMEN DEL {$trimestre}° TRIMESTRE {$anio}**\n\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "📅 **Fecha de cierre del mes actual:** " . date('d/m/Y', strtotime($fecha['fecha_completa'])) . "\n\n";
    
    // Mostrar cada mes del trimestre
    for ($mes = $mes_inicio; $mes <= $mes_fin; $mes++) {
        $ingreso = $ingresos_por_mes[$mes] ?? 0;
        $facturas = $facturas_por_mes[$mes] ?? 0;
        $icono = $mes == $mes_actual ? '▶️ ' : '';
        $estado = $mes == $mes_actual ? ' (EN CURSO)' : ($ingreso > 0 ? ' (COMPLETADO)' : ' (SIN DATOS)');
        
        $respuesta .= "{$icono}**{$meses_nombres[$mes]}{$estado}**\n";
        $respuesta .= "   📄 Facturas: **" . number_format($facturas) . "**\n";
        $respuesta .= "   💰 Ingresos: **$" . number_format($ingreso, 2) . "**\n\n";
    }
    
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "📋 **TOTALES DEL TRIMESTRE**\n";
    $respuesta .= "📄 Total facturas: **" . number_format($total_facturas) . "**\n";
    $respuesta .= "💰 Total ingresos: **$" . number_format($total_ingresos, 2) . "**\n";
    $respuesta .= "📊 Promedio mensual:\n";
    $respuesta .= "   • Facturas: **" . number_format($promedio_facturas, 1) . "**\n";
    $respuesta .= "   • Ingresos: **$" . number_format($promedio_ingresos, 2) . "**\n\n";
    
    if ($mes_actual >= $mes_inicio && $mes_actual <= $mes_fin) {
        $respuesta .= "✅ **Este es el trimestre en curso**\n";
        
        // Si estamos en el trimestre actual, mostrar proyección
        if ($total_facturas > 0) {
            $meses_completados = 0;
            for ($mes = $mes_inicio; $mes < $mes_actual; $mes++) {
                if (isset($facturas_por_mes[$mes]) && $facturas_por_mes[$mes] > 0) {
                    $meses_completados++;
                }
            }
            
            if ($meses_completados > 0) {
                $proyeccion_ingresos = ($total_ingresos / $meses_completados) * 3;
                $respuesta .= "📈 Proyección fin de trimestre: **$" . number_format($proyeccion_ingresos, 2) . "**\n";
            }
        }
    } else {
        $respuesta .= "📌 Este es un trimestre **cerrado**\n";
    }
    
    return $respuesta;
}

/**
 * Resumen completo del mes actual con datos reales de facturación
 */
function getResumenMes($db, $usuario_nombre) {
    $fecha = getFechaMaestraSistema($db);
    $anio = $fecha['anio'];
    $mes = $fecha['mes_num'];
    $mes_nombre = $fecha['mes_nombre'];
    $dia_actual = $fecha['dia'];
    $dias_mes = $fecha['dias_mes'];
    $fecha_cierre = $fecha['fecha_completa'];
    
    // Consultar facturas del mes actual (hasta la fecha actual, no hasta el cierre)
    $stmt = $db->prepare("SELECT 
                          COUNT(*) as total_facturas,
                          COALESCE(SUM(total_general), 0) as total_ingresos,
                          SUM(CASE WHEN estado = 'PAGADA' THEN 1 ELSE 0 END) as pagadas,
                          SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                          SUM(CASE WHEN estado = 'CONTABILIZADA' THEN 1 ELSE 0 END) as contabilizadas,
                          SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) as anuladas
                          FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio
                          AND MONTH(fecha_emision) = :mes
                          AND estado != 'ANULADA'");
    $stmt->execute([
        'anio' => $anio,
        'mes' => $mes
    ]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_facturas = $datos['total_facturas'] ?? 0;
    $total_ingresos = $datos['total_ingresos'] ?? 0;
    $pagadas = $datos['pagadas'] ?? 0;
    $pendientes = $datos['pendientes'] ?? 0;
    $contabilizadas = $datos['contabilizadas'] ?? 0;
    $anuladas = $datos['anuladas'] ?? 0;
    
    // Calcular promedio diario
    $promedio_diario = $dia_actual > 0 ? $total_ingresos / $dia_actual : 0;
    $proyeccion = $promedio_diario * $dias_mes;
    
    // Obtener plan del mes si existe
    $stmt_plan = $db->prepare("SELECT importe FROM tbl_planes 
                               WHERE anio = :anio AND mes_plan = :mes");
    $stmt_plan->execute(['anio' => $anio, 'mes' => $mes]);
    $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
    $meta = $plan ? $plan['importe'] : 0;
    
    $cumplimiento = $meta > 0 ? ($total_ingresos / $meta) * 100 : 0;
    $faltante = $meta - $total_ingresos;
    
    $respuesta = "📊 **RESUMEN DEL MES DE {$mes_nombre} {$anio}**\n\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $respuesta .= "📅 **Período de operaciones:**\n";
    $respuesta .= "• Inicio del mes: 01/{$mes}/{$anio}\n";
    $respuesta .= "• Cierre programado: " . date('d/m/Y', strtotime($fecha_cierre)) . "\n";
    $respuesta .= "• Día actual: **{$dia_actual}** de {$dias_mes} (**{$fecha['porcentaje']}%**)\n";
    $respuesta .= "• Días restantes: **{$fecha['dias_restantes']}**\n\n";
    
    $respuesta .= "💰 **Facturación:**\n";
    $respuesta .= "• Total facturas: **" . number_format($total_facturas) . "**\n";
    $respuesta .= "• Total ingresos: **$" . number_format($total_ingresos, 2) . "**\n";
    $respuesta .= "• Promedio diario: **$" . number_format($promedio_diario, 2) . "**\n";
    $respuesta .= "• Proyección fin de mes: **$" . number_format($proyeccion, 2) . "**\n\n";
    
    $respuesta .= "📋 **Distribución por estado:**\n";
    $respuesta .= "• ✅ Pagadas: **{$pagadas}**\n";
    $respuesta .= "• 📝 Contabilizadas: **{$contabilizadas}**\n";
    $respuesta .= "• ⏳ Pendientes: **{$pendientes}**\n";
    $respuesta .= "• ❌ Anuladas: **{$anuladas}**\n\n";
    
    if ($meta > 0) {
        $respuesta .= "🎯 **Meta del mes:**\n";
        $respuesta .= "• Meta: **$" . number_format($meta, 2) . "**\n";
        $respuesta .= "• Cumplimiento: **" . number_format($cumplimiento, 1) . "%**\n";
        
        if ($faltante > 0) {
            $necesario_diario = $fecha['dias_restantes'] > 0 ? $faltante / $fecha['dias_restantes'] : 0;
            $respuesta .= "• Faltan: **$" . number_format($faltante, 2) . "**\n";
            $respuesta .= "• Necesitas: **$" . number_format($necesario_diario, 2) . "**/día\n";
        } else {
            $respuesta .= "• ✅ ¡Meta superada por **$" . number_format(abs($faltante), 2) . "**!\n";
        }
    }
    
    return $respuesta;
}

?>