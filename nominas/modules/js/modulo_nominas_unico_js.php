<script>

// ===== Select2: filtro "Por Nombre" con búsqueda en tiempo real =====
if ($.fn.select2) {
    $('#filtroTrabajador').select2({
        width: '100%',
        language: {
            noResults: function() { return 'Sin resultados'; },
            searching: function() { return 'Buscando...'; }
        }
    });
    $(document).on('select2:open', function() {
        var sf = document.querySelector('.select2-container--open .select2-search__field');
        if (sf) { sf.placeholder = 'Escriba para buscar...'; sf.focus(); }
    });
}

// Función para limpiar valores numéricos (remover comas, signos de dólar, etc.)
function limpiarNumero(valor) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return 0;
    // Convertir a string y limpiar
    var str = valor.toString();
    // Remover todo excepto números, punto decimal y signo negativo
    var limpio = str.replace(/[^0-9.-]/g, '');
    var num = parseFloat(limpio);
    return isNaN(num) ? 0 : num;
}
// Datos desde PHP para los filtros de impresión
var areasDisponibles = <?php echo json_encode($all_areas); ?>;
var centrosCostoDisponibles = <?php echo json_encode($all_centros); ?>;
var categoriasDisponibles = <?php echo json_encode($pdo->query("SELECT id, codigo, nombre FROM categorias_ocupacionales WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC)); ?>;
var escalasDisponibles = <?php echo json_encode($pdo->query("SELECT id, escala_numero, salario_mensual FROM escalas_salariales WHERE activo=1 ORDER BY escala_numero")->fetchAll(PDO::FETCH_ASSOC)); ?>;
var tiposContrato = [
    { id: 'Indeterminado', nombre: 'Indeterminado' },
    { id: 'Determinado', nombre: 'Determinado' },
    { id: 'A Prueba', nombre: 'A Prueba' }
];


// Variable global para almacenar los datos activos del selector
window.currentFilterData = { datos: [], alcance: '' };


// Función unificada para obtener la etiqueta formateada con Código - Descripción
function obtenerLabelPorAlcance(item, alcance) {
    switch(alcance) {
        case 'area':
            return `${item.codigo} - ${item.nombre_area}`;
        case 'centro_costo':
            return `${item.codigo} - ${item.nombre}`;
        case 'categoria':
            //return `${item.codigo} - ${item.nombre}`;
			return item.nombre;
        case 'escala':
            let rom = obtenerRomanoLocal(item.escala_numero);
            return `${rom} - Escala Grupo ${rom} ($${parseFloat(item.salario_mensual).toFixed(2)})`;
        case 'tipo_contrato':
            let cod_tc = 'CONTR';
            if (item.nombre.toLowerCase().includes('indet')) cod_tc = 'IND';
            else if (item.nombre.toLowerCase().includes('determ')) cod_tc = 'DET';
            else if (item.nombre.toLowerCase().includes('prueba')) cod_tc = 'APR';
            return `${cod_tc} - ${item.nombre}`;
        case 'cargo':  // <-- NUEVO CASO
            return item.nombre;  // Ya viene como "1 - Jefa de Proyecto"
        default:
            return item.nombre || item.codigo || '';
    }
}

function cargarSelectores() {
    const alcance = $('input[name="alcanceImpresion"]:checked').val();
    const contenedor = $('#contenedorSelectores');
    contenedor.empty();

    // Si es General o Tirillas, ocultar selectores y NO validar filtros
    if (alcance === 'general' || alcance === 'tirillas') {
        $('#selectoresDinamicos').hide();
        contenedor.empty();
        window.currentFilterData = { datos: [], alcance: alcance };
        // IMPORTANTE: Limpiar cualquier valor previo del selector
        $('#selectImpresion').val('');
        return;
    }

    // Resto del código para otros alcances (area, centro_costo, etc.)
    $('#selectoresDinamicos').show();
    let datos = [];
    let nombreCampo = '';

    switch(alcance) {
        case 'area':
            datos = areasDisponibles;
            nombreCampo = 'Área';
            break;
        case 'centro_costo':
            datos = centrosCostoDisponibles;
            nombreCampo = 'Centro de Costo';
            break;
        case 'categoria':
            datos = categoriasDisponibles;
            nombreCampo = 'Categoría Ocupacional';
            break;
        case 'escala':
            datos = escalasDisponibles;
            nombreCampo = 'Escala Salarial';
            break;
        case 'tipo_contrato':
            datos = tiposContrato;
            nombreCampo = 'Tipo de Contrato';
            break;
        case 'cargo':
            datos = cargosDisponibles;
            nombreCampo = 'Cargo';
            break;
        default:
            return;
    }

    if (!datos || datos.length === 0) {
        contenedor.html('<div class="alert alert-warning m-2">No hay datos disponibles para este filtro.</div>');
        return;
    }

    window.currentFilterData = { datos: datos, alcance: alcance };

    let html = `
        <div class="p-1">
            <label class="form-label fw-bold mb-2 d-flex align-items-center justify-content-between" style="font-size:0.82rem; color: #a3a3a3;">
                <span><i class="fas fa-search me-1 text-info"></i> Seleccione ${nombreCampo}</span>
                <span class="badge" style="background: rgba(255,255,255,0.06); color: #888;" id="badgeResultadosCount">${datos.length} disponibles</span>
            </label>
            <div class="input-group mb-2 shadow-sm">
                <span class="input-group-text border-secondary" style="background: rgba(20,20,30,0.8); border-color: rgba(255,255,255,0.12) !important; color: #60a5fa;"><i class="fas fa-filter"></i></span>
                <input type="text" id="buscarEnSelector" class="form-control border-secondary text-white" placeholder="Escriba para buscar..." style="background: rgba(20,20,30,0.8); border-color: rgba(255,255,255,0.12) !important; font-size:0.85rem;" autocomplete="off">
            </div>
            <select class="form-select form-select-custom select-scroll-panel" id="selectImpresion" size="4" style="max-height:9.375rem; overflow-y: auto;">
                <option value="" selected>-- Todos --</option>
    `;

    datos.forEach(item => {
        let value = item.id;
        let label = obtenerLabelPorAlcance(item, alcance);
        html += `<option value="${value}">${label}</option>`;
    });
    
    html += `
            </select>
        </div>
    `;
    
    contenedor.html(html);
}


    // Función auxiliar local para formatear números romanos
    function obtenerRomanoLocal(num) {
        let val = parseInt(num);
        if (isNaN(val) || val <= 0) return num || '?';
        const lookup = {M:1000,CM:900,D:500,CD:400,C:100,XC:90,L:50,XL:40,X:10,IX:9,V:5,IV:4,I:1};
        let roman = '';
        for (let i in lookup) {
            while (val >= lookup[i]) {
                roman += i;
                val -= lookup[i];
            }
        }
        return roman;
    }
	
// ==========================================
// FUNCIÓN DE REDONDEO TIPO EXCEL
// ==========================================
function roundExcel(number, precision) {
    if (precision === undefined) {
        precision = 2;
    }
    var multiplier = Math.pow(10, precision);
    return Math.floor(number * multiplier + 0.5) / multiplier;
}

// Asignar a Math para compatibilidad
Math.roundExcel = function(number, precision) {
    if (precision === undefined) {
        precision = 2;
    }
    return roundExcel(number, precision);
};

// ==========================================
// VARIABLES GLOBALES DE JAVASCRIPT
// ==========================================
var logoBase64 = '<?php echo $logoBase64; ?>';
var nombreEmpresa = '<?php echo addslashes($config_empresa['nombre_empresa']); ?>';
//var jefeProyecto = '<?php echo addslashes(JEFE_PROYECTO); ?>';
//var especialistaGestion = '<?php echo addslashes(ESPECIALISTA); ?>';
var tipoNominaTexto = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
window.tipoNomina = '<?php echo $tipo_nomina_activa; ?>';
var tipoNomina = window.tipoNomina; // Para compatibilidad en ambas llamadas
var puedeCrearNomina = <?php echo $puede_crear_nomina ? 'true' : 'false'; ?>;
window.trabajadores = <?php echo json_encode($trabajadores); ?>;
// Todos los trabajadores (activos e inactivos) para el Listado Total Salario Devengado
var trabajadoresTodos = <?php
    $stmt_todos_t = $pdo->query("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores ORDER BY nombre_completo");
    echo json_encode($stmt_todos_t->fetchAll(PDO::FETCH_ASSOC));
?>;
// Años con nóminas en la base de datos para el Listado Total Salario Devengado
var aniosDisponibles = <?php
    $stmt_anios_all = $pdo->query("SELECT DISTINCT YEAR(periodo_desde) as anio FROM nominas ORDER BY anio DESC");
    echo json_encode($stmt_anios_all->fetchAll(PDO::FETCH_COLUMN));
?>;
//var periodoTexto = '<?php echo $nombre_mes . " " . $anio; ?>';
var periodoTexto = '<?php echo date("d/m/Y", strtotime($periodo_desde)) . " al " . date("d/m/Y", strtotime($periodo_hasta)); ?>';
var periodo = '<?php echo addslashes($periodo); ?>'; 
var usuarioNombre = '<?php echo addslashes($user_nombre_completo); ?>';
var recargoNocturno = parseFloat('<?php echo $recargo_nocturno; ?>') || 1.25;
var recargoExtraDiurna = parseFloat('<?php echo $recargo_extra_diurna; ?>') || 1.50;
var recargoExtraNocturna = parseFloat('<?php echo $recargo_extra_nocturna; ?>') || 2.00;
var recargoDobleturno = parseFloat('<?php echo $recargo_doble_turno; ?>') || 2.00;
var PRINT_TOOLBAR_HTML = '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style><div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">'
        + '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
        + '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#16a34a\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#22c55e\';this.style.transform=\'translateY(0)\';">'
        + '🖨️ Imprimir</button>'
        + '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#ef4444\';this.style.transform=\'translateY(0)\';">'
        + '✖ Cerrar</button>'
        + ''
        + '</div><div style="height:3.4375rem;"></div>'
        + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';
var tarifaNoctTemprana = parseFloat('<?php echo $tarifa_nocturnidad_temprana; ?>') || 0.60;
var tarifaNoctTardia = parseFloat('<?php echo $tarifa_nocturnidad_tardia; ?>') || 1.15;

var jefeProyecto = '<?php echo addslashes($config_empresa['jefe_proyecto'] ?? JEFE_PROYECTO); ?>';
var especialistaGestion = '<?php echo addslashes($config_empresa['especialista_gestion'] ?? ESPECIALISTA); ?>';
var especialistaNominas = '<?php echo addslashes($config_empresa['especialista_nominas'] ?? ''); ?>';


var cargosDisponibles = <?php 
    $stmt_cargos = $pdo->query("SELECT id, CONCAT(id, ' - ', nombre_cargo) as nombre FROM cargos_plantilla WHERE activo = 1 ORDER BY id");
    echo json_encode($stmt_cargos->fetchAll(PDO::FETCH_ASSOC)); 
?>;

var numeroNomina = '<?php echo $num_nomina_actual; ?>';
var reeup = '<?php echo isset($config_empresa["reeup_empresa"]) ? $config_empresa["reeup_empresa"] : "309-2-1401"; ?>';
var nitEmpresa = '<?php echo isset($config_empresa["nit_empresa"]) ? $config_empresa["nit_empresa"] : "S/R"; ?>';

// Estado de las filas VISIBLES (aplicando búsqueda y filtros) para decidir si se puede
// generar la Nómina Impresa. En ajuste puede haber varios números de nómina en el mismo
// período, por eso se valida por lote y no con el flag global "contabilizada".
window.obtenerEstadoImpresion = function() {
    var $tabla = $('#tablaNominas');
    if (!$tabla.length || !$.fn.DataTable.isDataTable('#tablaNominas')) {
        return { ok: false, motivo: 'vacio' };
    }
    var dt = $tabla.DataTable();
    var nodos = dt.rows({ search: 'applied' }).nodes();
    if (nodos.length === 0) return { ok: false, motivo: 'vacio' };

    var hayBorrador = false;
    var numeros = {};
    $(nodos).each(function() {
        var $fila = $(this);
        if ($fila.has('.badge-borrador').length) hayBorrador = true;
        var num = $fila.find('.col-numero-nomina').text().trim();
        if (num && num !== 'Borrador') numeros[num] = true;
    });
    var nums = Object.keys(numeros);
    if (hayBorrador) return { ok: false, motivo: 'borrador' };
    if (nums.length > 1) return { ok: false, motivo: 'multiples', numeros: nums };
    return { ok: true, numero: nums.length === 1 ? nums[0] : null };
};

var nombreMesGlobal = '<?php echo addslashes($nombre_mes); ?>';
var anioGlobal = '<?php echo addslashes($anio); ?>';
var mesGlobal = '<?php echo addslashes($mes); ?>';
var observacionesCierreGlobal = <?php echo json_encode($observaciones_cierre); ?>;
var observacionesCierreGlobalOriginal = observacionesCierreGlobal;
// 🔽 NUEVO: Mapa nómina -> observaciones (AJUSTE) para actualizarlas según el filtro seleccionado
var observacionesPorNomina = <?php echo json_encode($observaciones_por_nomina ?? []); ?>;

// Horas de la jornada diaria (configuración general) para equivalencias en nómina de vacaciones
var horasJornadaDiaria = <?php
    $stmt_hjd = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'horas_jornada_diaria' LIMIT 1");
    echo (int)($stmt_hjd->fetchColumn() ?: 8);
?>;

var idsEnNominaActual = <?php 
    $stmt_ids = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $stmt_ids->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    echo json_encode($stmt_ids->fetchAll(PDO::FETCH_COLUMN));
?>;

var activeTipoDescuento = '<?php 
    $sql_td = "SELECT tipo_descuento FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?";
    $params_td = [$periodo_desde, $periodo_hasta, $tipo_nomina_activa];
    
    if (!empty($filtro_numero_nomina)) {
        if ($filtro_numero_nomina === 'Borrador') {
            $sql_td .= " AND numero_nomina IS NULL";
        } else {
            $sql_td .= " AND numero_nomina = ?";
            $params_td[] = $filtro_numero_nomina;
        }
    }
    $sql_td .= " LIMIT 1";
    
    $stmt_td = $pdo->prepare($sql_td);
    $stmt_td->execute($params_td);
    echo $stmt_td->fetchColumn() ?: 'total_rangos'; 
?>';

    <?php
    // Inicializar la variable que se pasará a JavaScript
    $monto_distribuido_db = 0.00;
    
    // Solo calcular y pasar el monto si la nómina activa es "bono"
    if ($tipo_nomina_activa == 'bono') {
        $nombre_mes_esp = mb_strtolower(nombreMesEspanol($mes), 'UTF-8');
        
        // Construir la consulta base para obtener el monto a distribuir
        $sql_monto = "SELECT COALESCE(SUM(n.pago_resultado), 0) as total_monto
                      FROM nominas n
                      WHERE n.tipo_nomina = 'bono'
                        AND n.estado = 'contabilizado'
                        AND MONTH(n.periodo_desde) = ?
                        AND YEAR(n.periodo_desde) = ?";
        $params_monto = [intval($mes), intval($anio)];

        // Si hay un filtro por número de nómina, aplicarlo
        // Esto es crucial para que el monto mostrado sea el de esa nómina específica
        if (!empty($filtro_numero_nomina) && $filtro_numero_nomina !== 'Borrador') {
            $sql_monto .= " AND n.numero_nomina = ?";
            $params_monto[] = $filtro_numero_nomina;
        }

        $stmt_monto = $pdo->prepare($sql_monto);
        $stmt_monto->execute($params_monto);
        $total_contabilizado = floatval($stmt_monto->fetchColumn()) ?: 0.00;

        // Si no se encontró ningún bono contabilizado para este período, se usa el monto del borrador
        if ($total_contabilizado <= 0) {
            // Obtener la suma de los bonos en borrador (sin número de nómina)
            $sql_draft = "SELECT COALESCE(SUM(pago_resultado), 0) FROM nominas 
                          WHERE periodo_desde = ? AND periodo_hasta = ? 
                          AND tipo_nomina = 'bono' AND estado = 'borrador'";
            $stmt_draft = $pdo->prepare($sql_draft);
            $stmt_draft->execute([$periodo_desde, $periodo_hasta]);
            $total_borrador = floatval($stmt_draft->fetchColumn()) ?: 0.00;
            
            // El monto a distribuir será el total contabilizado. Si es 0, se mostrará "(Hasta que se contabilice)"
            $monto_distribuido_db = $total_contabilizado;
        } else {
            $monto_distribuido_db = $total_contabilizado;
        }

        // Si el monto sigue siendo 0, significa que no hay bonos contabilizados ni en borrador.
        // Dejamos el valor en 0 para que el JS muestre "(Hasta que se contabilice)"
    }
    ?>
    var montoDistribuidoGlobal = parseFloat('<?php echo $monto_distribuido_db; ?>') || 0.00;


function ordenarTrabajadoresPorAlcance(trabajadores, alcance) {
    if (!trabajadores || trabajadores.length === 0) return trabajadores;
    
    return [...trabajadores].sort((a, b) => {
        let valorA = 0;
        let valorB = 0;
        
        switch (alcance) {
            case 'centro_costo':
                valorA = a.centroCostoCodigo || 9999;
                valorB = b.centroCostoCodigo || 9999;
                break;
                
            case 'cargo':
                valorA = a.cargoId || 9999;
                valorB = b.cargoId || 9999;
                break;
                
            case 'area':
                valorA = a.areaCodigo || 9999;
                valorB = b.areaCodigo || 9999;
                break;
                
            case 'escala':
                valorA = a.escalaNumero || 9999;
                valorB = b.escalaNumero || 9999;
                break;
                
            case 'general':
                valorA = parseInt(a.codigo) || 9999;
                valorB = parseInt(b.codigo) || 9999;
                break;
                
            default:
                // Orden alfabético para otros casos
                let txtA = (a[alcance] || '').toString().toLowerCase();
                let txtB = (b[alcance] || '').toString().toLowerCase();
                if (txtA < txtB) return -1;
                if (txtA > txtB) return 1;
                return 0;
        }
        
        // Comparación numérica
        if (valorA < valorB) return -1;
        if (valorA > valorB) return 1;
        
        // Desempate por nombre
        let nombreA = (a.nombre || '').toLowerCase();
        let nombreB = (b.nombre || '').toLowerCase();
        if (nombreA < nombreB) return -1;
        if (nombreA > nombreB) return 1;
        
        return 0;
    });
}

function obtenerCampoOrden(alcance) {
    switch (alcance) {
        case 'tipo_contrato': return 'tipoContrato';
        case 'categoria': return 'categoria';
        default: return 'nombre';
    }
}


// ==========================================
// SELECTOR PERSONALIZADO DE TIPO DE NÓMINA
// ==========================================

// Abrir/cerrar dropdown al hacer clic en el preview
$(document).on('click', '#tipoNominaPreview', function(e) {
    e.stopPropagation();
    $('.tipo-nomina-selector-custom').toggleClass('open');
});

// Cerrar dropdown al hacer clic fuera
$(document).on('click', function(e) {
    if (!$(e.target).closest('.tipo-nomina-selector-custom').length) {
        $('.tipo-nomina-selector-custom').removeClass('open');
    }
});

// Seleccionar una opción del dropdown
$(document).on('click', '.tipo-nomina-option', function(e) {
    e.stopPropagation();
    var tipo = $(this).data('value');
    var icono = $(this).data('icon');
    var nombre = $(this).find('span').text();
    
    // Actualizar el preview
    $('#tipoNominaPreview i:first-child').attr('class', 'fas ' + icono);
    $('#tipoNominaPreview span').text(nombre);
    
    // Cerrar dropdown
    $('.tipo-nomina-selector-custom').removeClass('open');
    
    // Marcar la opción seleccionada
    $('.tipo-nomina-option').removeClass('selected');
    $(this).addClass('selected');
    
    // Redirigir a la URL con el nuevo tipo
    var periodoActual = '<?php echo $periodo; ?>';
    var filtroCuenta = '<?php echo $filtro_cuenta; ?>';
    var url = 'nominas.php?periodo=' + periodoActual + '&tipo=' + tipo;
    if (filtroCuenta) url += '&filtro_cuenta=' + filtroCuenta;
    window.location.href = url;
});

// Sincronizar el selector con el tipo de nómina actual
function sincronizarSelectorTipoNomina() {
    var tipoActual = '<?php echo $tipo_nomina_activa; ?>';
    var nombreActual = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
    var iconoActual = '<?php echo $tipos_nomina[$tipo_nomina_activa]['icono']; ?>';
    
    // Actualizar el preview
    $('#tipoNominaPreview i:first-child').attr('class', 'fas ' + iconoActual);
    $('#tipoNominaPreview span').text(nombreActual);
    
    // Marcar la opción seleccionada
    $('.tipo-nomina-option').removeClass('selected');
    $('.tipo-nomina-option[data-value="' + tipoActual + '"]').addClass('selected');
}

// Llamar a la sincronización cuando cargue la página
$(document).ready(function() {
    sincronizarSelectorTipoNomina();
});


// ==========================================
// LÓGICA PRINCIPAL DE NÓMINAS
// ==========================================

// ==========================================
// VARIABLE GLOBAL PARA ALMACENAR DATOS DEL HISTORIAL
// ==========================================
let datosHistorialCompleto = [];

// ==========================================
// FUNCIÓN PARA CARGAR EL HISTORIAL COMPLETO
// ==========================================
function cargarHistorialMontos() {
    Swal.fire({
        title: 'Cargando Base de Datos...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: 'white'
    });

    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: { action: 'get_full_historial_bonos', ajax: 1 },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                // Guardar datos globalmente
                datosHistorialCompleto = response.historial;
                
                // Cargar años en el combo
                cargarAniosEnCombo();
                
                // Mostrar todos los datos inicialmente
                filtrarHistorialPorAnio();
                
                // Abrir el modal
                new bootstrap.Modal(document.getElementById('modalFullHistorial')).show();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.error || 'No se pudieron cargar los datos',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: 'white'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            Swal.fire({
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor: ' + error,
                icon: 'error',
                background: '#1a1a2e',
                color: 'white'
            });
        }
    });
}

// ==========================================
// FUNCIÓN PARA CARGAR AÑOS EN EL COMBO
// ==========================================
function cargarAniosEnCombo() {
    const select = document.getElementById('filtroAnio');
    if (!select) return;
    
    // Obtener años únicos de los datos
    const años = [...new Set(datosHistorialCompleto.map(item => item.anio))];
    
    // Ordenar de más reciente a más antiguo
    años.sort((a, b) => b - a);
    
    // Limpiar opciones existentes (excepto "Todos")
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Agregar años al combo
    años.forEach(anio => {
        const option = document.createElement('option');
        option.value = anio;
        option.textContent = anio;
        option.style.background = '#1e1e26';
        option.style.color = '#fff';
        select.appendChild(option);
    });
}

// ==========================================
// FUNCIÓN PARA FILTRAR POR AÑO
// ==========================================
function filtrarHistorialPorAnio() {
    const select = document.getElementById('filtroAnio');
    const anioSeleccionado = select ? select.value : 'todos';
    const tbody = document.querySelector('#tablaFullHistorial tbody');
    const totalElement = document.getElementById('totalFullHistorial');
    
    if (!tbody) return;
    
    // Filtrar datos
    let datosFiltrados = [];
    if (anioSeleccionado === 'todos') {
        datosFiltrados = datosHistorialCompleto;
    } else {
        datosFiltrados = datosHistorialCompleto.filter(item => 
            parseInt(item.anio) === parseInt(anioSeleccionado)
        );
    }
    
    // Limpiar tabla
    tbody.innerHTML = '';
    
    // Si no hay datos, mostrar mensaje
    if (datosFiltrados.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-white-50 py-3">
                    <i class="fas fa-info-circle me-2"></i>No hay registros para este año
                </td>
            </tr>
        `;
        totalElement.textContent = '$0.00';
        return;
    }
    
    // Calcular importe máximo para las barras
    const maxImporte = Math.max(...datosFiltrados.map(item => parseFloat(item.importe_dis) || 0));
    
    // Llenar tabla con datos filtrados
    let total = 0;
    datosFiltrados.forEach(item => {
        const importe = parseFloat(item.importe_dis) || 0;
        total += importe;
        const porcentaje = maxImporte > 0 ? ((importe / maxImporte) * 100) : 0;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="py-2 text-center">${item.anio}</td>
            <td class="py-2 text-capitalize">${item.mes}</td>
            <td class="py-2">
                <div class="barra-comparacion-container">
                    <div class="barra-comparacion-fill" style="width:${porcentaje.toFixed(1)}%"></div>
                    <span class="barra-comparacion-label">${porcentaje.toFixed(1)}%</span>
                </div>
            </td>
            <td class="py-2 text-end fw-bold text-info">$${importe.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        `;
        tbody.appendChild(tr);
    });
    
    // Actualizar total
    totalElement.textContent = '$' + total.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

//cargarHistorialMontos();

// ==========================================
// FUNCIÓN PARA LIMPIAR FILTRO
// ==========================================
function limpiarFiltroAnio() {
    const select = document.getElementById('filtroAnio');
    if (select) {
        select.value = 'todos';
        filtrarHistorialPorAnio();
    }
}




$(document).ready(function() {
// --- Cargar Base Completa ---
    $('#btnFullHistorialBonos').on('click', function() {
        Swal.fire({
            title: 'Cargando Base de Datos...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: '#1a1a2e',
            color: 'white'
        });

        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: { action: 'get_full_historial_bonos', ajax: 1 },
            dataType: 'json',
            success: function(r) {
                Swal.close();
                if (r.success) {
                    datosHistorialCompleto = r.historial;
                    cargarAniosEnCombo();
                    filtrarHistorialPorAnio();
                    new bootstrap.Modal(document.getElementById('modalFullHistorial')).show();
                }
            }
        });
    });

// --- Imprimir Historial con Formato Oficial ---
$('#btnImprimirFull').on('click', function() {
    const tableBody = $('#tablaFullHistorial tbody').html();
    const totalValue = $('#totalFullHistorial').text();
    const now = new Date();
    const fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    
    // Obtener el filtro seleccionado
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : `AÑO ${filtroSeleccionado}`;

    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Historial de Montos Distribuidos - ${nombreEmpresa}</title>
            <style>
                @page { size: portrait; margin:15mm; }
                body { font-family: Arial, sans-serif; font-size:10pt; color: #000; margin:0; padding:0; }
                
                /* Estilo de Cabecera similar a SC-4-06 */
                .header-container { width:100%; border: 0.0625rem solid #000; border-collapse: collapse; margin-bottom:1.25rem; }
                .header-container td { border: 0.0625rem solid #000; padding:0.5rem; vertical-align: middle; }
                .logo-cell { width:3.75rem; text-align: center; }
                .title-cell { text-align: center; font-weight: bold; font-size:12pt; }
                .meta-cell { font-size:8pt; width:11.25rem; }

                /* Tabla de Datos */
                .main-table { width:100%; border-collapse: collapse; margin-top:0.625rem; }
                .main-table th { background-color: #004B87; color: #ffffff; font-weight: bold; padding:0.5rem; border: 0.0625rem solid #000; text-align: center; -webkit-print-color-adjust: exact; }
                .main-table td { border: 0.0625rem solid #000; padding:0.375rem 0.625rem; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .text-capitalize { text-transform: capitalize; }
                
                /* Fila de Total */
                .total-row { background-color: #f0f4f8; font-weight: bold; }
                .total-row td { border-top: 0.125rem solid #000; color: #004B87; }

                /* Bloque de Firmas Oficiales */
                .signature-section { margin-top:3.125rem; page-break-inside: avoid; }
                .signature-table { width:100%; border: none !important; border-collapse: collapse; }
                .signature-table td { border: none !important; width:25%; text-align: center; padding:0.625rem; font-size:8.5pt; vertical-align: top; }
                .sig-line { border-top: 0.0625rem solid #000; width:90%; margin:2.8125rem auto 0.3125rem auto; }
                .sig-name { font-weight: bold; display: block; }
                .sig-label { color: #444; font-size:7.5pt; }

                .footer-info { margin-top:0.9375rem; font-size:8pt; color: #666; text-align: center; border-top: 0.5pt solid #eee; padding-top:0.3125rem; }
                
                /* Estilo para el filtro en el encabezado */
                .filtro-info { background-color: #f8f9fa; font-weight: bold; color: #004B87; padding:0.25rem 0.625rem; border-radius: 0.25rem; display: inline-block; }
                @media print { .no-print { display: none !important; } }
            </style>
        </head>
        <body>
            ${PRINT_TOOLBAR_HTML}
            <!-- Encabezado Oficial -->
            <table class="header-container">
                <tr>
                    <td class="logo-cell">
                        ${logoBase64 ? `<img src="${logoBase64}" width="50">` : ''}
                    </td>
                    <td class="title-cell">
                        REGISTRO DE BASE DE DATOS DE MONTOS DISTRIBUIDOS<br>
                        <span style="font-size:10pt;">${nombreEmpresa.toUpperCase()}</span>
                    </td>
                    <td class="meta-cell">
                        <strong>Emisión:</strong> ${fechaHora}<br>
                        <strong>REEUP:</strong> ${reeup}<br>
                        <strong>NIT:</strong> ${nitEmpresa}
                    </td>
                </tr>
            </table>

            <div style="text-align: center; margin-bottom:0.625rem;">
                <h4 style="margin:0;">HISTORIAL CRONOLÓGICO DE IMPORTES PARA PRODUCTIVIDAD</h4>
                <div style="margin-top:0.3125rem;">
                    <span class="filtro-info">🔍 FILTRO APLICADO: ${filtroTexto}</span>
                </div>
            </div>

            <!-- Tabla de Datos -->
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width:15%;">AÑO</th>
                        <th style="width:25%;">MES CORRESPONDIENTE</th>
                        <th style="width:25%;">COMPARACIÓN</th>
                        <th style="width:35%;" class="text-end">IMPORTE REGISTRADO ($)</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableBody}
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="text-end">TOTAL ACUMULADO HISTÓRICO:</td>
                        <td class="text-end" style="font-size:12pt;">${totalValue}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Bloque de Firmas -->
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td>
                            <p><b>Elaborado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-name">${especialistaNominas}</span>
                            <span class="sig-label">Especialista de Nóminas</span>
                        </td>
                        <td>
                            <p><b>Revisado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-name">${especialistaGestion}</span>
                            <span class="sig-label">Especialista en Gestión Económica</span>
                        </td>
                        <td>
                            <p><b>Aprobado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-name">${jefeProyecto}</span>
                            <span class="sig-label">Director de Proyecto</span>
                        </td>
                        <td>
                            <p><b>Contabilizado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-label">Área Contable y Financiera</span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer-info">
                Documento generado por el Sistema de Gestión de Nóminas - Usuario: ${usuarioNombre}
            </div>
        </body>
        </html>
    `);
    win.document.close();
});

// --- Exportar PDF del Historial con Formato Oficial SC-4-06 ---
$('#btnExportPDFFull').on('click', function() {
    const now = new Date();
    const fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    const totalAcumulado = $('#totalFullHistorial').text();
    
    // Obtener el filtro seleccionado
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : `AÑO ${filtroSeleccionado}`;

    // 1. Extraer los datos de la tabla HTML
    let tablaDatos = [];
    // Encabezado de la tabla de datos
    tablaDatos.push([
        { text: 'AÑO', style: 'tableHeader' },
        { text: 'MES CORRESPONDIENTE', style: 'tableHeader' },
        { text: 'COMPARACIÓN', style: 'tableHeader', alignment: 'center' },
        { text: 'IMPORTE REGISTRADO ($)', style: 'tableHeader', alignment: 'right' }
    ]);

    // Filas de la tabla
    $('#tablaFullHistorial tbody tr').each(function() {
        const anio = $(this).find('td:eq(0)').text();
        const mes = $(this).find('td:eq(1)').text();
        const comparacion = $(this).find('.barra-comparacion-label').text().trim() || '0%';
        const importe = $(this).find('td:eq(3)').text();
        
        tablaDatos.push([
            { text: anio, style: 'tableCell', alignment: 'center' },
            { text: mes.toUpperCase(), style: 'tableCell' },
            { text: comparacion, style: 'tableCell', alignment: 'center' },
            { text: importe, style: 'tableCell', alignment: 'right' }
        ]);
    });

    // 2. Definición del Documento para pdfMake
    const docDefinition = {
        pageSize: 'LETTER',
        pageMargins: [30, 30, 30, 40],
        content: [
            // CABECERA OFICIAL (Logo, Título, Metadatos)
            {
                table: {
                    widths: [50, '*', 160],
                    body: [
                        [
                            logoBase64 ? { image: logoBase64, width: 40, alignment: 'center' } : { text: '' },
                            {
                                stack: [
                                    { text: 'REGISTRO DE BASE DE DATOS DE MONTOS DISTRIBUIDOS', fontSize: 11, bold: true, alignment: 'center' },
                                    { text: nombreEmpresa.toUpperCase(), fontSize: 9, alignment: 'center', margin: [0, 2, 0, 0] },
                                    { text: 'HISTORIAL CRONOLÓGICO DE PRODUCTIVIDAD', fontSize: 8, color: '#444', alignment: 'center', margin: [0, 2, 0, 0] },
                                    { 
                                        text: `🔍 FILTRO APLICADO: ${filtroTexto}`, 
                                        fontSize: 8, 
                                        bold: true, 
                                        color: '#004B87',
                                        alignment: 'center',
                                        margin: [0, 4, 0, 0]
                                    }
                                ]
                            },
                            {
                                stack: [
                                    { text: `Emisión: ${fechaHora}`, fontSize: 7 },
                                    { text: `REEUP: ${reeup}`, fontSize: 7, margin: [0, 2, 0, 0] },
                                    { text: `NIT: ${nitEmpresa}`, fontSize: 7, margin: [0, 2, 0, 0] }
                                ],
                                alignment: 'left'
                            }
                        ]
                    ]
                },
                layout: {
                    hLineWidth: () => 0.5,
                    vLineWidth: () => 0.5,
                    hLineColor: () => '#000',
                    vLineColor: () => '#000'
                }
            },
            { text: ' ', margin: [0, 10] }, // Espaciador

            // TABLA DE DATOS
            {
                table: {
                    headerRows: 1,
                    widths: ['12%', '30%', '25%', '33%'],
                    body: tablaDatos
                },
                layout: {
                    hLineWidth: (i, node) => (i === 0 || i === node.table.body.length) ? 1 : 0.5,
                    vLineWidth: () => 0.5,
                    hLineColor: () => '#000',
                    vLineColor: () => '#000',
                    paddingTop: () => 4,
                    paddingBottom: () => 4
                }
            },

            // FILA DE TOTAL
            {
                table: {
                    widths: ['12%', '30%', '25%', '33%'],
                    body: [
                        [
                            { text: '', fillColor: '#f0f4f8' },
                            { text: 'TOTAL ACUMULADO HISTÓRICO:', alignment: 'right', bold: true, fontSize: 10, fillColor: '#f0f4f8', colSpan: 2 },
                            '',
                            { text: totalAcumulado, alignment: 'right', bold: true, fontSize: 11, color: '#004B87', fillColor: '#f0f4f8' }
                        ]
                    ]
                },
                layout: {
                    hLineWidth: () => 1,
                    vLineWidth: () => 0.5,
                    hLineColor: () => '#000',
                    vLineColor: () => '#000'
                }
            },

            // BLOQUE DE FIRMAS (Idéntico a SC-4-06)
            {
                margin: [0, 50, 0, 0],
                unbreakable: true,
                table: {
                    widths: ['25%', '25%', '25%', '25%'],
                    body: [
                        [
                            { text: 'Elaborado por:', style: 'sigLabel' },
                            { text: 'Revisado por:', style: 'sigLabel' },
                            { text: 'Aprobado por:', style: 'sigLabel' },
                            { text: 'Contabilizado por:', style: 'sigLabel' }
                        ],
                        [
                            { text: '\n\n______________________', alignment: 'center' },
                            { text: '\n\n______________________', alignment: 'center' },
                            { text: '\n\n______________________', alignment: 'center' },
                            { text: '\n\n______________________', alignment: 'center' }
                        ],
                        [
                            { text: especialistaNominas, style: 'sigName' },
                            { text: especialistaGestion, style: 'sigName' },
                            { text: jefeProyecto, style: 'sigName' },
                            { text: 'Área Contable y Financiera', style: 'sigRole' }
                        ],
                        [
                            { text: 'Especialista de Nóminas', style: 'sigRole' },
                            { text: 'Especialista en Gestión Económica', style: 'sigRole' },
                            { text: 'Director de Proyecto', style: 'sigRole' },
                            { text: '', style: 'sigRole' }
                        ]
                    ]
                },
                layout: 'noBorders'
            }
        ],
        styles: {
            tableHeader: {
                fillColor: '#004B87',
                color: '#ffffff',
                bold: true,
                fontSize: 9,
                margin: [0, 2, 0, 2]
            },
            tableCell: {
                fontSize: 8.5
            },
            sigLabel: {
                fontSize: 8.5,
                bold: true,
                alignment: 'center'
            },
            sigName: {
                fontSize: 8.5,
                bold: true,
                alignment: 'center',
                margin: [0, 2, 0, 0]
            },
            sigRole: {
                fontSize: 7.5,
                color: '#444',
                alignment: 'center'
            }
        },
        footer: function(currentPage, pageCount) {
            return {
                text: `Sistema de Gestión de Nóminas - Página ${currentPage} de ${pageCount} - Usuario: ${usuarioNombre}`,
                fontSize: 7,
                alignment: 'center',
                margin: [0, 10, 0, 0]
            };
        }
    };

    // 3. Generar y Descargar
    pdfMake.createPdf(docDefinition).download(`Historial_Montos_${now.getTime()}.pdf`);
});

// --- Exportar Word del Historial de Montos ---
$('#btnExportWordFull').on('click', function() {
    const now = new Date();
    const fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    const totalAcumulado = $('#totalFullHistorial').text();
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : `AÑO ${filtroSeleccionado}`;

    let rows = '';
    $('#tablaFullHistorial tbody tr').each(function() {
        const c = $(this).find('td');
        rows += '<tr><td style="text-align:center;">' + c.eq(0).text() + '</td>'
            + '<td>' + c.eq(1).text() + '</td>'
            + '<td style="text-align:center;">' + (c.eq(2).find('.barra-comparacion-label').text().trim() || '0%') + '</td>'
            + '<td style="text-align:right;">' + c.eq(3).text() + '</td></tr>';
    });

    const html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">'
        + '<head><meta charset="utf-8"><style>table{width:100%;border-collapse:collapse;}th,td{border:0.0625rem solid #000;padding:0.3125rem 0.5rem;font-size:9pt;}th{background:#1e3a8a;color:#fff;text-align:center;font-weight:bold;font-size:8pt;}</style></head>'
        + '<body><p style="text-align:center;font-size:14pt;font-weight:bold;">' + nombreEmpresa.toUpperCase() + '</p>'
        + '<p style="text-align:center;font-size:11pt;font-weight:bold;">HISTORIAL DE MONTOS DISTRIBUIDOS</p>'
        + '<p style="font-size:9pt;">Filtro: ' + filtroTexto + ' | Generado: ' + fechaHora + ' | Por: ' + usuarioNombre + '</p>'
        + '<table><thead><tr><th>AÑO</th><th>MES</th><th>COMPARACIÓN</th><th>IMPORTE REGISTRADO</th></tr></thead><tbody>' + rows + '</tbody></table>'
        + '<p style="font-weight:bold;text-align:right;margin-top:0.625rem;">TOTAL ACUMULADO: ' + totalAcumulado + '</p>'
        + '</body></html>';

    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'Historial_Montos_' + now.getTime() + '.doc';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

// --- Exportar Excel del Historial de Montos ---
$('#btnExportExcelFull').on('click', function() {
    if (typeof XLSX === 'undefined') { Swal.fire({ title: 'Librería no cargada', text: 'No se encontró la librería XLSX.', icon: 'error', background: '#121722', color: '#fff' }); return; }
    const now = new Date();
    const totalAcumulado = $('#totalFullHistorial').text();
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : 'AÑO ' + filtroSeleccionado;

    const data = [['AÑO', 'MES', 'COMPARACIÓN', 'IMPORTE REGISTRADO']];
    $('#tablaFullHistorial tbody tr').each(function() {
        const c = $(this).find('td');
        data.push([c.eq(0).text().trim(), c.eq(1).text().trim(), (c.eq(2).find('.barra-comparacion-label').text().trim() || '0%'), c.eq(3).text().trim()]);
    });
    data.push([]);
    data.push(['TOTAL ACUMULADO:', '', '', totalAcumulado]);

    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [{ wch: 8 }, { wch: 20 }, { wch: 14 }, { wch: 22 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Historial Montos');
    XLSX.writeFile(wb, 'Historial_Montos_' + now.getTime() + '.xlsx');
});

// --- Exportar CSV del Historial de Montos ---
$('#btnExportCsvFull').on('click', function() {
    const now = new Date();
    let csv = 'AÑO,MES,COMPARACIÓN,IMPORTE REGISTRADO\n';
    $('#tablaFullHistorial tbody tr').each(function() {
        const c = $(this).find('td');
        csv += '"' + c.eq(0).text().trim() + '","' + c.eq(1).text().trim() + '","' + (c.eq(2).find('.barra-comparacion-label').text().trim() || '0%') + '","' + c.eq(3).text().trim() + '"\n';
    });
    csv += '\n"TOTAL ACUMULADO","","","' + $('#totalFullHistorial').text() + '"\n';
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'Historial_Montos_' + now.getTime() + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

// --- Exportar TXT del Historial de Montos ---
$('#btnExportTxtFull').on('click', function() {
    const now = new Date();
    const fechaHora = now.toLocaleDateString('es-ES') + ' ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    const totalAcumulado = $('#totalFullHistorial').text();
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : 'AÑO ' + filtroSeleccionado;
    const sep = '='.repeat(70);
    const sep2 = '-'.repeat(70);

    let txt = sep + '\n';
    txt += '  ' + nombreEmpresa.toUpperCase() + '\n';
    txt += '  HISTORIAL DE MONTOS DISTRIBUIDOS\n';
    txt += sep + '\n';
    txt += '  Filtro: ' + filtroTexto + '\n';
    txt += '  Generado: ' + fechaHora + '\n';
    txt += '  Usuario: ' + usuarioNombre + '\n';
    txt += sep2 + '\n';
    txt += padR('AÑO', 8) + padR('MES', 22) + padR('COMPARACIÓN', 16) + 'IMPORTE\n';
    txt += sep2 + '\n';

    $('#tablaFullHistorial tbody tr').each(function() {
        const c = $(this).find('td');
        txt += padR(c.eq(0).text().trim(), 8) + padR(c.eq(1).text().trim(), 22) + padR((c.eq(2).find('.barra-comparacion-label').text().trim() || '0%'), 16) + c.eq(3).text().trim() + '\n';
    });
    txt += sep2 + '\n';
    txt += padR('TOTAL ACUMULADO:', 46) + totalAcumulado + '\n';
    txt += sep + '\n';

    const blob = new Blob([txt], { type: 'text/plain;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'Historial_Montos_' + now.getTime() + '.txt';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);

    function padR(s, n) { return (s || '').length >= n ? s : s + ' '.repeat(n - s.length); }
});
	
	
// =========================================================================
// FUNCIONES AUXILIARES DE MAPEO UNIFICADO PARA EXPORTACIONES
// =========================================================================

// Extrae el valor numérico limpio de una celda, detectando si contiene un input
function parseCell($el) {
    if (!$el.length) return 0;
    
    // Si el elemento en sí es un input
    if ($el.is('input')) {
        return parseNumber($el.val());
    }
    
    // Si contiene un input adentro (caso de celdas editables en borrador)
    var $inputInside = $el.find('input');
    if ($inputInside.length) {
        return parseNumber($inputInside.val());
    }
    
    // Caso estándar (texto plano)
    return parseNumber($el.text());
}

// Mapea una fila completa del DataTable a un objeto unificado de Trabajador
function mapRowToTrabajador($row) {
    var catCod = $row.data('categoria-codigo') || '';
    var catNom = $row.data('categoria-nombre') || '';
    var categoriaFull = (catCod && catNom) ? (catCod + ' - ' + catNom) : (catCod || catNom || '-');

    // 1. Horas / Días
    var horasVal = 0;
    var diasTomadosVal = 0;
    if (tipoNomina === 'vacaciones') {
        // Días de vacaciones pagados/rebajados en la nómina
        var diasParseados = parseCell($row.find('.edit-dias')) || parseCell($row.find('.col-horas').first());
        // Horas equivalentes = Días × jornada diaria (configurable)
        horasVal = Math.roundExcel(diasParseados * (horasJornadaDiaria || 8), 2);
        // Días = Horas / Jornada diaria
        diasTomadosVal = Math.roundExcel(horasVal / (horasJornadaDiaria || 8), 2);
    } else {
        horasVal = parseCell($row.find('.edit-horas')) || parseCell($row.find('.col-horas'));
    }

    // 2. Salarios Básicos y Tarifas
    var salarioMensual = parseNumber($row.attr('data-salario-mensual') || $row.data('salario-mensual')) || 0;
    var tarifaSal = parseNumber($row.data('salario-hora')) || 0;

    // 3. A cobrar / Importe Salario Laboral
    var aCobrar = 0;
    if (tipoNomina === 'bono' || tipoNomina === 'ajuste') {
        aCobrar = parseCell($row.find('.edit-bono')) || parseCell($row.find('.bono-val-cell'));
    } else if (tipoNomina === 'vacaciones') {
        aCobrar = parseCell($row.find('.total-devengado'));
    } else {
        aCobrar = parseCell($row.find('.salario-laboral'));
    }

    // 4. Bono / Otros Pagos
    var bono = 0;
    if (tipoNomina === 'bono') {
        bono = aCobrar;
    } else {
        bono = parseCell($row.find('.edit-otros-pagos')) || parseCell($row.find('.col-otros-pagos'));
    }

    // 🔽 NUEVO: Obtener el concepto/descripción (para BONO y AJUSTE)
    var concepto = '';
    if (tipoNomina === 'bono' || tipoNomina === 'ajuste') {
        // Buscar en la columna de concepto (segunda columna .col-nombre)
        var $colsNombre = $row.find('.col-nombre');
        if ($colsNombre.length > 1) {
            concepto = $colsNombre.eq(1).text().trim();
        } else {
            // Fallback: buscar en la columna 7 (índice 7)
            concepto = $row.find('td').eq(7).text().trim();
        }
        if (concepto === '-' || concepto === '') {
            concepto = 'Sin concepto';
        }
    }

    // 5. Descuentos / Otros Descuentos
    var descuentos = parseCell($row.find('.edit-descuentos')) || parseCell($row.find('.col-otros-descuentos'));

    // 6. Devengado
    var devengado = parseCell($row.find('.total-devengado'));

    // 7. Contribución CESS
    var impS = parseCell($row.find('.contribucion'));

    // 8. Retenciones / Total Deducciones
    var retenciones = parseCell($row.find('.total-deducciones'));

    // 9. Pagado / Neto
    var pagado = parseCell($row.find('.neto'));

    // 10. Vacaciones y Feriados
    var vacDias = 0;
    if (tipoNomina === 'vacaciones') {
        // Saldo restante del submayor de vacaciones (días que quedan tras descontar los ya tomados)
        var diasAcumuladosRow = parseNumber($row.data('dias-acumulados')) || 0;
        var diasYaTomadosRow = parseNumber($row.data('dias-ya-tomados')) || 0;
        vacDias = parseCell($row.find('.dias-restantes')) || Math.max(0, diasAcumuladosRow - diasYaTomadosRow);
    } else {
        vacDias = parseCell($row.find('.vacaciones-dias'));
    }
    var tiempoImp = (tipoNomina === 'vacaciones')
        ? Math.roundExcel(vacDias * (salarioMensual / diasLaborables) * 100) / 100
        : parseCell($row.find('.vacations-importe'));
    var feriadoImp = parseCell($row.find('.feriados-importe'));

    var noctT = 0, noctD = 0, dt = 0, importeHE = 0, importeNtT = 0, importeNtD = 0, importeDT = 0;
    if (tipoNomina === 'extraordinaria') {
        noctT = parseCell($row.find('.edit-noct-temprana')) || parseCell($row.find('.col-noct-t')) || 0;
        noctD = parseCell($row.find('.edit-noct-tardia')) || parseCell($row.find('.col-noct-d')) || 0;
        dt   = parseCell($row.find('.edit-doble-turno')) || parseCell($row.find('.col-dt')) || 0;
        importeNtT = parseCell($row.find('.col-noct-t-imp')) || 0;
        importeNtD = parseCell($row.find('.col-noct-d-imp')) || 0;
        importeDT  = parseCell($row.find('.col-dt-imp')) || 0;
        var importeSalarioLaboral = parseCell($row.find('.salario-laboral')) || 0;
        importeHE = importeSalarioLaboral - importeDT;
    }

    return {
        codigo: $row.find('td:eq(0)').text().trim(),
        ci: $row.find('td:eq(1)').text().trim(),
        nombre: $row.find('td:eq(2)').text().trim(),
        area: $row.data('area') || $row.find('td:eq(3)').text().trim(),
        cargoId: $row.data('cargo-id') || 0,
        cargo: $row.data('cargo') || $row.find('td:eq(4)').text().trim(),
        centroCosto: $row.data('centro-costo') || $row.find('td:eq(5)').text().trim(),
        centroCostoCodigo: parseInt($row.data('centro-costo-codigo')) || 9999,
        categoria: categoriaFull,
        categoriaCodigo: catCod || '-',
        escala: $row.data('escala-romana') || '-',
        escalaNumero: $row.data('escala-numero') || 0,
        escalaDescripcion: $row.data('escala-descripcion') || '-',
        tipoContrato: $row.data('tipo-contrato') || '',
        tarifaSal: tarifaSal,
        horas: horasVal,
        diasTomados: diasTomadosVal,
        aCobrar: aCobrar,
        bono: bono,
        concepto: concepto,
        devengado: devengado,
        impS: impS,
        retenciones: retenciones,
        descuentos: descuentos,
        pagado: pagado,
        vacDias: vacDias,
        tiempoImp: tiempoImp,
        feriadoImp: feriadoImp,
        vacAcumDias: parseNumber($row.data('dias-acumulados')) || 0,
        vacAcumMes: parseNumber($row.data('vacaciones-acumuladas-mes')) || 0,
        importeVacMes: parseNumber($row.data('importe-vacaciones-mes')) || 0,
        salarioMensual: salarioMensual,
        noctT: noctT,
        noctD: noctD,
        dt: dt,
        importeHE: importeHE,
        importeNtT: importeNtT,
        importeNtD: importeNtD,
        importeDT: importeDT,
        firma: ''
    };
}
	
	// Llamar a la sincronización cuando cargue la página
    sincronizarSelectorTipoNomina();

	// Escuchar parámetro de impresión automática desde el Dashboard
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('abrir_impresion') === '1') {
        // Esperar un breve momento a que DataTables renderice las columnas fijas
        setTimeout(function() {
            // Ejecutar el clic programático en el botón de impresión oficial
            $('#menuNominaImpresa').trigger('click');
            
            // Limpiar el parámetro de la URL para evitar que se reabra al recargar la página manualmente
            urlParams.delete('abrir_impresion');
            var nuevaUrl = window.location.pathname + '?' + urlParams.toString();
            window.history.replaceState({}, document.title, nuevaUrl);
        }, 600);
    }

// ==========================================
// MODAL DE AJUSTE
// ==========================================
var selectedAjuste = [];
var modoAjuste = 'directo';

function ajustarModoAjusteCards() {
    $('#modoAjuste').val(modoAjuste);
    var tiposAjuste = {
        'directo':    { label: 'Pago directo específico', icon: 'fa-money-bill-wave', color: '#10b981', bg: 'rgba(16,185,129,0.15)' },
        'horas':      { label: 'Pago por horas trabajadas', icon: 'fa-clock', color: '#f59e0b', bg: 'rgba(245,158,11,0.15)' },
        'liquidacion':{ label: 'Liquidación de fracciones del submayor de vacaciones', icon: 'fa-sun', color: '#8b5cf6', bg: 'rgba(139,92,246,0.15)' }
    };
    var t = tiposAjuste[modoAjuste] || tiposAjuste['directo'];
    $('#badgeTipoAjuste').attr('style', 'background:' + t.bg + '; color:' + t.color + '; padding: 0.5rem 0.875rem; font-size: 0.9rem; font-weight: 600;')
        .html('<i class="fas ' + t.icon + ' me-1"></i> ' + t.label);
    $('#ajusteSelectedList').html('<em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista</em>');
    updateAjusteTotals();
}

function renderAjusteWorkerList(term = '') {
    var areaVal = $('#filterAjusteArea').val();
    var ccVal = $('#filterAjusteCC').val();
    var html = '';
    
    // En modo Liquidación se permite seleccionar trabajadores de baja
    var source = (modoAjuste === 'liquidacion') ? trabajadoresLiquidacion : trabajadores;
    var filtered = source.filter(w => {
        var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
        var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
        var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
        return matchesSearch && matchesArea && matchesCC;
    });
    
    filtered.forEach(w => {
        var sel = selectedAjuste.some(s => s.id == w.id);

        // Resolución del path de la foto
        var workerFotoUrl = '';
        if (w.foto_ruta && w.foto_ruta.trim() !== '') {
            workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
        }

        var avatarHtml = '';
        if (workerFotoUrl) {
            avatarHtml = `<img src="${workerFotoUrl}" style="width:100%; height:100%; object-fit: cover;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2360a5fa%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">`;
        } else {
            avatarHtml = `<i class="fas fa-user"></i>`;
        }

        html += `<div class="worker-item ${sel?'selected':''}" data-id="${w.id}" data-nombre="${w.nombre_completo}" data-codigo="${w.codigo}" data-salario="${w.salario_mensual}" data-salario-hora="${w.salario_hora_ordinaria}" data-valor-dia="${(parseFloat(w.salario_mensual) || 0) / <?php echo $dias_laborables; ?>}" data-dias-submayor="${w.dias_submayor_periodo || 0}" data-foto-ruta="${w.foto_ruta || ''}">
            <input type="checkbox" class="worker-checkbox" ${sel?'checked':''}>
            <div class="worker-avatar">${avatarHtml}</div>
            <div class="worker-info">
                <div class="worker-name">${w.codigo} - ${w.nombre_completo}${parseInt(w.activo) !== 1 ? ' <span class="badge bg-danger" style="font-size:0.625rem; vertical-align: middle;">DE BAJA</span>' : ''}</div>
                <div class="worker-detail"><i class="fas fa-wallet me-1"></i>Salario Básico: $${parseFloat(w.salario_mensual).toFixed(2)} | <i class="fas fa-clock me-1"></i>$${parseFloat(w.salario_hora_ordinaria).toFixed(2)}/h</div>
            </div>
        </div>`;
    });
    $('#ajusteWorkerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash me-1"></i>No hay resultados</div>');
}

function updateAjusteList() {
    var html = selectedAjuste.map(w => {
        if (modoAjuste === 'horas') {
            var horasVal = w.horas !== undefined ? w.horas : '';
            var montoCalc = (parseFloat(horasVal) || 0) * (parseFloat(w.salario_hora) || 0);
            return `
                <div class="selected-worker-card" data-id="${w.id}">
                    <div class="selected-worker-header">
                        <span class="worker-name">${w.codigo} - ${w.nombre}</span>
                        <button type="button" class="ajuste-item-remove" data-id="${w.id}"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="row g-2 align-items-center mt-2">
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-dark border-secondary text-white"><i class="fas fa-clock"></i></span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center ajuste-horas-input"
                                       data-id="${w.id}" value="${horasVal}" step="0.5" min="0" placeholder="Horas trabajadas">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center text-white-50 small" style="line-height:1.9375rem;">
                                <i class="fas fa-calculator me-1"></i>Monto: <span class="ajuste-monto-calc text-success fw-bold">$${(montoCalc || 0).toFixed(2)}</span>
                                <small class="d-block">$${(parseFloat(w.salario_hora) || 0).toFixed(2)}/h</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        if (modoAjuste === 'liquidacion') {
            var diasLiq = parseFloat(w.dias_submayor) || 0;
            var valorDiaLiq = parseFloat(w.valor_dia) || 0;
            var montoCalcLiq = Math.roundExcel(diasLiq * valorDiaLiq, 2);
            return `
                <div class="selected-worker-card" data-id="${w.id}">
                    <div class="selected-worker-header">
                        <span class="worker-name">${w.codigo} - ${w.nombre}</span>
                        <button type="button" class="ajuste-item-remove" data-id="${w.id}"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="row g-2 align-items-center mt-2">
                        <div class="col-md-12">
                            <div class="small text-white-50" style="line-height:1.75rem;">
                                <i class="fas fa-sun me-1"></i>Días acumulados: <span class="text-info fw-bold">${diasLiq.toFixed(2)}</span>
                                <i class="fas fa-dollar-sign me-1 ms-3"></i>Valor/día: <span class="text-success fw-bold">$${valorDiaLiq.toFixed(2)}</span>
                                <i class="fas fa-calculator me-1 ms-3"></i>Total a liquidar: <span class="text-success fw-bold">$${montoCalcLiq.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        var montoVal = w.monto !== undefined ? w.monto : '';
        return `
            <div class="selected-worker-card" data-id="${w.id}">
                <div class="selected-worker-header">
                    <span class="worker-name">${w.codigo} - ${w.nombre}</span>
                    <button type="button" class="ajuste-item-remove" data-id="${w.id}"><i class="fas fa-times"></i></button>
                </div>
                <div class="row g-2 align-items-center mt-2">
                    <div class="col-md-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white">$</span>
                            <input type="number" class="form-control bg-dark border-secondary text-white text-center ajuste-monto-input"
                                   data-id="${w.id}" value="${montoVal}" step="0.01" min="0" placeholder="Monto del Ajuste">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    $('#ajusteSelectedList').html(html || '<em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista</em>');
    updateAjusteTotals();
}

function updateAjusteTotals() {
    var tDev = 0, val = 0;
    selectedAjuste.forEach(w => {
        if (modoAjuste === 'horas') {
            var h = parseFloat(w.horas) || 0;
            var m = h * (parseFloat(w.salario_hora) || 0);
            if (h > 0 && m > 0) { tDev += m; val++; }
        } else if (modoAjuste === 'liquidacion') {
            var d = parseFloat(w.dias_submayor) || 0;
            var m = Math.roundExcel(d * (parseFloat(w.valor_dia) || 0), 2);
            if (d > 0 && m > 0) { tDev += m; val++; }
        } else {
            var m = parseFloat(w.monto) || 0;
            if (m > 0) { tDev += m; val++; }
        }
    });
    
    if(val > 0){
        $('#previewAjusteCount').text(val);
        $('#previewAjusteTotal').text('$' + tDev.toFixed(2));
        $('#previewAjuste').show();
        $('#btnGenerarAjuste').prop('disabled', false);
    } else {
        $('#previewAjuste').hide();
        $('#btnGenerarAjuste').prop('disabled', true);
    }
}

function recalcularPreviewAjuste() {
    var monto = parseFloat($('#editMontoAjuste').val()) || 0;
    var descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
    var otrosPagos = Math.max(0, parseFloat($('#editOtrosPagos').val()) || 0);
    var $fila = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]');
    var tipoDescuento = $fila.data('tipo-descuento') || 'total_rangos';
    
    var $horasInput = $('#editHorasAjuste');
    if ($horasInput.length) {
        var horas = parseFloat($horasInput.val()) || 0;
        var salarioHora = parseFloat($fila.data('salario-hora')) || 0;
        monto = Math.roundExcel(salarioHora * horas, 2);
        $('#editMontoAjuste').val(monto.toFixed(2));
    }
    
    var total = Math.roundExcel(monto + otrosPagos, 2);
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(total);
    } else {
        contribucion = Math.roundExcel(total * 0.05, 2);
        impuesto = calcularImpuestoProgresivo(total);
    }

    descuentos = validarDescuentosConCESS(total, contribucion, impuesto, descuentos);
    $('#editDescuentos').val(descuentos.toFixed(2));

    var totalDeducciones = Math.roundExcel(contribucion + impuesto + descuentos, 2);
    var neto = Math.max(0, Math.roundExcel(total - totalDeducciones, 2));
    
    $('#previewDevengado').text('$' + total.toFixed(2));
    $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
}

$(document).on('click', '#ajusteWorkerList .worker-item', function(){
    var id = $(this).data('id'), idx = selectedAjuste.findIndex(s=>s.id==id);
    if(idx>=0) {
        selectedAjuste.splice(idx,1); 
    } else {
        selectedAjuste.push({
            id: id, 
            nombre: $(this).data('nombre'), 
            codigo: $(this).data('codigo'), 
            salario: parseFloat($(this).data('salario')) || 0,
            salario_hora: parseFloat($(this).data('salario-hora')) || 0,
            dias_submayor: parseFloat($(this).data('dias-submayor')) || 0,
            valor_dia: parseFloat($(this).data('valor-dia')) || 0,
            monto: '',
            horas: ''
        });
    }
    renderAjusteWorkerList($('#searchAjusteWorker').val()); 
    updateAjusteList();
});

$(document).on('input', '.ajuste-monto-input', function() {
    var id = $(this).data('id');
    var valMonto = parseFloat($(this).val()) || 0;
    var w = selectedAjuste.find(s => s.id == id);
    if (w) {
        w.monto = valMonto;
        updateAjusteTotals();
    }
});

$(document).on('input', '.ajuste-horas-input', function() {
    var id = $(this).data('id');
    var valHoras = parseFloat($(this).val()) || 0;
    var w = selectedAjuste.find(s => s.id == id);
    if (w) {
        w.horas = valHoras;
        w.monto = Math.roundExcel(valHoras * (parseFloat(w.salario_hora) || 0), 2);
        var $monto = $(this).closest('.selected-worker-card').find('.ajuste-monto-calc');
        if ($monto.length) $monto.text('$' + w.monto.toFixed(2));
        updateAjusteTotals();
    }
});

$(document).on('click', '.ajuste-item-remove', function(){
    selectedAjuste.splice(selectedAjuste.findIndex(s => s.id == $(this).data('id')), 1); 
    renderAjusteWorkerList($('#searchAjusteWorker').val()); 
    updateAjusteList();
});

$(document).on('click', '.tipo-ajuste-option', function(){
    modoAjuste = $(this).data('modo') || 'directo';
    $('#modalSeleccionTipoAjuste .tipo-ajuste-option').removeClass('selected');
    $(this).addClass('selected');
    $('#btnConfirmarTipoAjuste').prop('disabled', false);
});

// ==========================================
// MODAL DE SELECCIÓN DE TIPO DE AJUSTE (directo / horas)
// ==========================================
$('#modalSeleccionTipoAjuste').on('show.bs.modal', function(){
    var preseleccion = 'directo';
    if (window.ajusteModoDetectado) {
        var primeraFila = $('#tablaNominas tbody tr:first');
        preseleccion = (primeraFila.length && parseFloat(primeraFila.data('horas-ajuste') || 0) > 0) ? 'horas' : 'directo';
    }
    modoAjuste = preseleccion;
    $('#modalSeleccionTipoAjuste .tipo-ajuste-option').removeClass('selected');
    $('#opcionTipoAjuste' + (preseleccion === 'horas' ? 'Horas' : 'Directo')).addClass('selected');
    $('#btnConfirmarTipoAjuste').prop('disabled', false);
});

$('#btnConfirmarTipoAjuste').on('click', function() {
    var tipoModalEl = document.getElementById('modalSeleccionTipoAjuste');
    var tipoModal = bootstrap.Modal.getInstance(tipoModalEl);
    tipoModal.hide();
    $(tipoModalEl).one('hidden.bs.modal', function() {
        window.ajusteModoTipo = modoAjuste;
        window.ajusteModoDetectado = false;
        var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAjuste'));
        modalObj.show();
    });
});

$('#modalAjuste').on('show.bs.modal', function(){ 
    // Modo elegido explícitamente en el modal de selección de tipo
    if (window.ajusteModoTipo) {
        modoAjuste = window.ajusteModoTipo;
        window.ajusteModoTipo = null;
    } else if (window.ajusteModoDetectado) {
        var primeraFila = $('#tablaNominas tbody tr:first');
        modoAjuste = (primeraFila.length && parseFloat(primeraFila.data('horas-ajuste') || 0) > 0) ? 'horas' : 'directo';
        window.ajusteModoDetectado = false;
    } else {
        modoAjuste = 'directo';
    }
    selectedAjuste=[]; 
    ajustarModoAjusteCards();
    renderAjusteWorkerList(); 
    updateAjusteList(); 
    $('#searchAjusteWorker').val(''); 
    $('#conceptoAjuste').val(''); 
    
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    $('#tipoDescuentoAjuste').val(td);
    
    window.tempSelectedDiscount = null;
});

$('#formAjuste').on('submit', function(e){
    // Limpiar inputs previos dinámicos
    $(this).find('input[name="trabajador_id[]"], input[name="monto_ajuste[]"], input[name="horas_ajuste[]"]').remove();
    
    if(!$('#conceptoAjuste').val().trim()){ 
        e.preventDefault(); 
        Swal.fire({
            title: 'Error', 
            text: 'Ingrese un concepto para el ajuste.', 
            icon: 'error', 
            background: '#1a1a2e', 
            color: 'white', 
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        }); 
        return false; 
    }

    if (selectedAjuste.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Sin Selección',
            text: 'Debe seleccionar al menos un trabajador para el ajuste.',
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return false;
    }

    var esHoras = (modoAjuste === 'horas');
    var esLiquidacion = (modoAjuste === 'liquidacion');
    var incompleteWorker = null;
    var valid = false;

    selectedAjuste.forEach(w => {
        if (esHoras) {
            var h = parseFloat(w.horas) || 0;
            if (h <= 0) {
                incompleteWorker = w.nombre;
            } else {
                $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="horas_ajuste[]" value="${h}">`);
                valid = true;
            }
        } else if (esLiquidacion) {
            var dLiq = parseFloat(w.dias_submayor) || 0;
            if (dLiq <= 0) {
                incompleteWorker = w.nombre;
            } else {
                $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}">`);
                valid = true;
            }
        } else {
            var m = parseFloat(w.monto) || 0;
            if (m <= 0) {
                incompleteWorker = w.nombre;
            } else {
                $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="monto_ajuste[]" value="${m}">`);
                valid = true;
            }
        }
    });

    if (incompleteWorker) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> ' + (esHoras ? 'Horas Requeridas' : (esLiquidacion ? 'Días Requeridos' : 'Monto Requerido')),
            html: `Por favor, asigne ${esHoras ? 'horas trabajadas' : (esLiquidacion ? 'días acumulados en el submayor del período' : 'un monto de ajuste')} válido y mayor a cero para el trabajador:<br><strong>${escapeHtml(incompleteWorker)}</strong>`,
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Corregir'
        });
        return false;
    }

    $(this).append('<input type="hidden" name="confirmar_ajuste" value="1">'); 
    return true;
});

// ==========================================
// SELECTOR DE CONSULTA RÁPIDA (Mover aquí el contenido del segundo ready)
// ==========================================

// Función actualizarMesesConsulta ya existe arriba, no la redefinas.

$('#consultaAnioSelect, #consultaEstadoSelect, #consultaTipoSelect').on('change', function() {
    $('#consultaNumeroSelect').html('<option value="">-- Seleccione mes --</option>').prop('disabled', true);
    actualizarMesesConsulta(); // Esta función ya está definida antes en el mismo bloque
});

$('#consultaMesSelect').on('change', function() {
    var anio = $('#consultaAnioSelect').val();
    var mes = $(this).val();
    var tipo = $('#consultaTipoSelect').val();
    var estado = $('#consultaEstadoSelect').val();
    var numSelect = $('#consultaNumeroSelect');

    if (!mes) {
        numSelect.html('<option value="">-- Seleccione mes --</option>').prop('disabled', true);
        return;
    }

    numSelect.html('<option value="">Cargando números...</option>').prop('disabled', true);

    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: {
            action: 'get_numeros_nominas',
            anio: anio,
            mes: mes,
            tipo: tipo,
            estado: estado,
            ajax: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.numeros.length > 0) {
                var options = '<option value="">-- Todas las corridas --</option>';
                response.numeros.forEach(function(num) {
                    options += `<option value="${num}">${num}</option>`;
                });
                numSelect.html(options).prop('disabled', false);

                // Preseleccionar si hay un número en la URL
                var urlParams = new URLSearchParams(window.location.search);
                var numeroActual = urlParams.get('numero_nomina');
                if (numeroActual && response.numeros.includes(numeroActual)) {
                    numSelect.val(numeroActual);
                }
            } else {
                numSelect.html('<option value="">-- Ninguno disponible --</option>').prop('disabled', true);
            }
        },
        error: function() {
            numSelect.html('<option value="">-- Error al cargar --</option>').prop('disabled', true);
        }
    });
});

$('#consultaRapidaBtn').on('click', function() {
    var anio = $('#consultaAnioSelect').val();
    var mes = $('#consultaMesSelect').val();
    var tipo = $('#consultaTipoSelect').val();
    var estado = $('#consultaEstadoSelect').val();
    var numero = $('#consultaNumeroSelect').val();

    if (!anio || !mes) {
        Swal.fire({
            title: 'Selección incompleta',
            text: 'Debe seleccionar un año y un mes para consultar.',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }

    var periodo = anio + '-' + mes;
    var url = 'nominas.php?periodo=' + periodo + '&tipo=' + (tipo || tipoNomina);
    if (estado) url += '&estado=' + estado;
    if (numero && numero !== '') url += '&numero_nomina=' + encodeURIComponent(numero);
    var filtroCuentaActual = '<?php echo $filtro_cuenta; ?>';
    if (filtroCuentaActual) url += '&filtro_cuenta=' + filtroCuentaActual;
    window.location.href = url;
});

// Si el año ya está seleccionado al cargar, actualizar meses
if ($('#consultaAnioSelect').val()) {
    actualizarMesesConsulta();
}

   // Evitar que el foco se quede atrapado en elementos internos cuando un modal se oculta (WAI-ARIA Fix)
    $('.modal').on('hide.bs.modal', function () {
        if (document.activeElement && $(this).has(document.activeElement).length) {
            document.activeElement.blur();
        }
    });
	
	
    var diasLaborables = <?php echo $dias_laborables; ?>;
    var rangosImpuesto = <?php echo json_encode($rangos_impuesto); ?>;
    //var tipoNomina = '<?php echo $tipo_nomina_activa; ?>';
	var existeNomina = <?php echo $existe_nomina ? 'true' : 'false'; ?>;
	var contabilizada = <?php echo $contabilizada ? 'true' : 'false'; ?>;
	var periodoActual = '<?php echo $nombre_mes . ' ' . $anio; ?>';
	var tipoNominaActual = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
    var selectedWorkers = [];
    var selectedBonos = [];
    var selectedExtra = [];
    var idsConNomina = <?php echo json_encode($ids_con_nomina_vacaciones); ?>;
    var trabajadores = <?php echo json_encode($trabajadores); ?>;
    var trabajadoresLiquidacion = <?php echo json_encode($trabajadores_liquidacion); ?>;
    
    var filtroCuentaActual = '<?php echo $filtro_cuenta; ?>';

// Inicializar estado de los filtros (habilitados/deshabilitados según valores iniciales)
inicializarEstadoFiltros();

// Botón Actualizar Página (recarga la misma URL)
$('#btnActualizarPagina').on('click', function(e) {
    e.preventDefault();
    window.location.href = 'nominas.php';
});

// Botón Regresar a Inicio (va a nominas.php sin parámetros)
$('#btnRegresarInicio').on('click', function(e) {
    e.preventDefault();
    window.location.href = '../dashboard.php';
});

// Botón seleccionar todos los trabajadores disponibles
$('#btnSeleccionarTodosAuto').on('click', function() {
    if (trabajadoresDisponibles.length === 0) {
        Swal.fire({
            title: 'Sin trabajadores disponibles',
            text: 'No hay trabajadores disponibles para agregar a la nómina.',
            icon: 'info',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    trabajadoresSeleccionadosAuto = [];
    
    trabajadoresDisponibles.forEach(function(t) {
        trabajadoresSeleccionadosAuto.push({
            id: t.id,
            nombre: t.nombre_completo,
            codigo: t.codigo,
            salario_mensual: t.salario_mensual,
            salario_hora: t.salario_hora_ordinaria,
            area: t.nombre_area || 'Sin área',
            centro_costo: (t.centro_costo_codigo && t.centro_costo_nombre) ? 
                t.centro_costo_codigo + ' - ' + t.centro_costo_nombre : 'Sin CC'
        });
    });
    
    renderAutoWorkerList();
    
    Swal.fire({
        title: '<i class="fas fa-check-circle me-2" style="color: 1;"></i> Selección completa',
        html: `Se seleccionaron <strong>${trabajadoresSeleccionadosAuto.length}</strong> trabajadores para agregar a la nómina.`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#ffffff'
    });
});

// Evento para el botón "Seleccionar todos" dentro de la lista
$(document).on('click', '.btn-seleccionar-todos-sm', function() {
    $('#btnSeleccionarTodosAuto').click();
});

    // Previene de forma absoluta la entrada de valores negativos en tiempo real (tabla y modal)
    $(document).on('input keyup paste', 
        '#editHoras, #editNoctT, #editNoctD, #editDT, #editFeriados, #editOtrosPagos, #editDescuentos, #editMontoBono, #editDias, ' +
        '.edit-horas, .edit-noct-temprana, .edit-noct-tardia, .edit-doble-turno, .edit-feriados, .edit-descuentos, .edit-otros-pagos, .edit-bono, .edit-dias', 
        function() {
            var $el = $(this);
            var valorTxt = $el.val();

            if (valorTxt.indexOf('-') !== -1) {
                $el.val(valorTxt.replace(/-/g, ''));
            }

            var valorNum = parseFloat($el.val());
            if (valorNum < 0 || isNaN(valorNum)) {
                if ($el.val() !== '') {
                    $el.val(0);
                }
            }
        }
    );
// =======================================================
// GENERAR IMPRESIÓN SEGÚN ALCANCE SELECCIONADO
// =======================================================
$('#btnGenerarImpresion').on('click', function() {
    const alcance = $('input[name="alcanceImpresion"]:checked').val();
    const action = $(this).attr('data-action') || 'imprimir';
    let filtroValor = null;
    let filtroNombre = null;

    // IMPORTANTE: Para 'tirillas' NO se necesita filtro de selector
    if (alcance !== 'general' && alcance !== 'tirillas') {
        filtroValor = $('#selectImpresion').val();
        filtroNombre = $('#selectImpresion option:selected').text();
        
        if (filtroValor === '') {
            filtroNombre = 'Todos';
        } else if (!filtroValor) {
            Swal.fire('Error', 'Debe seleccionar un valor para el filtro.', 'error');
            return;
        }
    }

    let isTodos = (filtroValor === ''); 

    // Obtener los nodos visibles del DataTable
    const dataTable = $('#tablaNominas').DataTable();
    const filas = dataTable.rows({ search: 'applied' }).nodes();
    let trabajadoresFiltrados = [];

    // Usar el número de nómina del lote visible para la cabecera del reporte
    var numerosVisibles = {};
    $(filas).each(function() {
        var num = $(this).find('.col-numero-nomina').text().trim();
        if (num && num !== 'Borrador') numerosVisibles[num] = true;
    });
    var numKeys = Object.keys(numerosVisibles);
    if (numKeys.length === 1) numeroNomina = numKeys[0];

    $(filas).each(function() {
        const $row = $(this);
        let incluir = true;

        const areaId = $row.data('area-id');
        const centroId = $row.data('centro-costo-id');
        const categoriaId = $row.data('categoria-ocupacional-id');
        const escalaId = $row.data('escala-id');
        const tipoContrato = $row.data('tipo-contrato');
        const cargoId = $row.data('cargo-id');

        switch (alcance) {
            case 'area':
                incluir = (filtroValor === '' || areaId == filtroValor);
                break;
            case 'centro_costo':
                incluir = (filtroValor === '' || centroId == filtroValor);
                break;
            case 'categoria':
                incluir = (filtroValor === '' || categoriaId == filtroValor);
                break;
            case 'escala':
                incluir = (filtroValor === '' || escalaId == filtroValor);
                break;
            case 'tipo_contrato':
                incluir = (filtroValor === '' || tipoContrato == filtroValor);
                break;
            case 'cargo':
                incluir = (filtroValor === '' || cargoId == filtroValor);
                break;
            default:
                incluir = true;
        }

		if (incluir) {
					// Mapeo unificado y seguro de campos
					const trabajador = mapRowToTrabajador($row);
					trabajadoresFiltrados.push(trabajador);
				}
			});

    // NUEVO: Manejo de Tirillas de Pago (esto debe ir ANTES de la validación de trabajadores vacíos)
    if (alcance === 'tirillas') {
        generarTirillasPago(trabajadoresFiltrados);
        return;
    }
    
    if (trabajadoresFiltrados.length === 0) {
        Swal.fire({
            title: '<i class="fas fa-database me-2" style="color: #f59e0b;"></i> Sin datos disponibles',
            html: '<div class="text-center"><i class="fas fa-filter fa-3x mb-3" style="color: #f59e0b; opacity: 0.7;"></i><p class="mb-2">No hay registros que coincidan con la agrupación seleccionada.</p></div>',
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    // Ejecutar la acción según la opción seleccionada
    if (action === 'imprimir') {
        generarNominaImpresa(trabajadoresFiltrados, alcance, filtroNombre, isTodos);
    } else if (action === 'pdf') {
        exportarPdfOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'excel') {
        exportarExcelOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'word') {
        exportarWordOficial(trabajadoresFiltrados, alcance, filtroNombre);
    }
});

$('#modalOpcionesImpresion').on('hidden.bs.modal', function () {
    // Restablecer radio button a "General"
    $('#alcanceGeneral').prop('checked', true);
    // Ocultar selectores dinámicos
    $('#selectoresDinamicos').hide();
    // Limpiar contenido de selectores
    $('#contenedorSelectores').empty();
    // Habilitar botón de generar por si quedó deshabilitado
    $('#btnGenerarImpresion').prop('disabled', false).html('<i class="fas fa-print me-2"></i>Generar Nómina Impresa');
});
// Dentro de $(document).ready, después de la definición de los otros filtros
$('#filterAjusteArea').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Ajuste', 'area');
    }
});
$('#filterAjusteCC').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Ajuste', 'cc');
    }
});
$('#searchAjusteWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        limpiarFiltrosModal('Ajuste', 'search');
    } else {
        renderAjusteWorkerList('');
    }
});

// Actualizar la función limpiarFiltrosModal existente para incluir 'Ajuste'
function limpiarFiltrosModal(modalId, filtroActivo) {
    var areaId = '#filter' + modalId + 'Area';
    var ccId = '#filter' + modalId + 'CC';
    var searchId = '#search' + modalId + 'Worker';
    
    if (filtroActivo === 'area') {
        $(ccId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'cc') {
        $(areaId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'search') {
        $(areaId).val('');
        $(ccId).val('');
    }
    
    var term = $(searchId).val();
    if (modalId === 'Extra') renderExtraWorkerList(term);
    else if (modalId === 'Vac') renderWorkerList(term);
    else if (modalId === 'Bono') renderBonoWorkerList(term);
    else if (modalId === 'Ajuste') renderAjusteWorkerList(term);
}
// =========================================================================
// ASIGNACIÓN GLOBAL Y SELECCIÓN DEL MODAL DE IMPRESIÓN MEJORADO
// =========================================================================

// Exponer la función de impresión global para evitar ReferenceError
window.generarNominaImpresa = function(trabajadores, alcance, filtroNombre) {
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const horaActual = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    const FILAS_POR_PAGINA = 15;

    trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);

    let paginasHtmlArray = [];
    let totalGeneralCompleto = {
        aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0,
        pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0,
        horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0
    };
    
    // Sumamos los totales generales
    trabajadores.forEach(t => {
        totalGeneralCompleto.aCobrar += t.aCobrar || 0;
        totalGeneralCompleto.bono += t.bono || 0;
        totalGeneralCompleto.devengado += t.devengado || 0;
        totalGeneralCompleto.impS += t.impS || 0;
        totalGeneralCompleto.descuentos += t.descuentos || 0;
        totalGeneralCompleto.retenciones += t.retenciones || 0;
        totalGeneralCompleto.pagado += t.pagado || 0;
        totalGeneralCompleto.vacDias += t.vacDias || 0;
        totalGeneralCompleto.tiempoImp += t.tiempoImp || 0;
        totalGeneralCompleto.salarioMensual += t.salarioMensual || 0;
        totalGeneralCompleto.horas += t.horas || 0;
        totalGeneralCompleto.importeHE += t.importeHE || 0;
        totalGeneralCompleto.noctT += t.noctT || 0;
        totalGeneralCompleto.importeNtT += t.importeNtT || 0;
        totalGeneralCompleto.noctD += t.noctD || 0;
        totalGeneralCompleto.importeNtD += t.importeNtD || 0;
        totalGeneralCompleto.dt += t.dt || 0;
        totalGeneralCompleto.importeDT += t.importeDT || 0;
    });

    const esBono = (tipoNomina === 'bono');
    const esAjuste = (tipoNomina === 'ajuste');
    const esExtraominaria = (tipoNomina === 'extraordinaria');
    const esVacaciones = (tipoNomina === 'vacaciones');
    const mostrarConcepto = (esBono || esAjuste);

    if (alcance === 'general') {
        let totalPaginas = Math.ceil(trabajadores.length / FILAS_POR_PAGINA);
        let numeroPagina = 1;
        
        for (let i = 0; i < trabajadores.length; i += FILAS_POR_PAGINA) {
            const trabajadoresPagina = trabajadores.slice(i, i + FILAS_POR_PAGINA);
            let subtotalPagina = {
                aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0,
                pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0,
                horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0
            };
            
            let cuerpoHtml = '';
            trabajadoresPagina.forEach(t => {
                    if (esBono) {
                        cuerpoHtml += `<tr>
                            <td class="text-center">${escapeHtml(t.codigo)}</td>
                            <td class="text-center">${escapeHtml(t.ci)}</td>
                            <td class="text-left">${escapeHtml(t.nombre)}</td>
                            <td class="text-center">${escapeHtml(t.categoriaCodigo)}</td>
                            <td class="text-right">$${(t.salarioMensual || 0).toFixed(2)}</td>
                            <td class="text-right">$${(t.devengado || 0).toFixed(2)}</td>
                            <td class="text-right">$${(t.impS || 0).toFixed(2)}</td>
                            <td class="text-right">$${(t.descuentos || 0).toFixed(2)}</td>
                            <td class="text-right">$${(t.retenciones || 0).toFixed(2)}</td>
                            <td class="text-right"><strong>$${(t.pagado || 0).toFixed(2)}</strong></td>
                            <td class="text-center"></td>
                        </tr>
                        <tr class="concepto-fila"><td colspan="11" class="text-left">Observación: ${escapeHtml(t.concepto)}</td></tr>`;
                    } else if (esExtraominaria) {
                        cuerpoHtml += `<tr>
                            <td class="text-center">${escapeHtml(t.codigo)}</td>
                            <td class="text-center">${escapeHtml(t.ci)}</td>
                            <td class="text-left">${escapeHtml(t.nombre)}</td>
                            <td class="text-center">${escapeHtml(t.categoriaCodigo)}</td>
                            <td class="text-right">$${(t.tarifaSal || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.horas || 0).toFixed(0)}</td>
                            <td class="text-right">$${(t.importeHE || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.noctT || 0).toFixed(0)}</td>
                            <td class="text-right">$${(t.importeNtT || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.noctD || 0).toFixed(0)}</td>
                            <td class="text-right">$${(t.importeNtD || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.dt || 0).toFixed(0)}</td>
                            <td class="text-right">$${(t.importeDT || 0).toFixed(2)}</td>
                            <td class="text-right">$${(t.devengado || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.impS || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.descuentos || 0).toFixed(2)}</td>
                            <td class="text-right">${(t.retenciones || 0).toFixed(2)}</td>
                            <td class="text-right"><strong>$${(t.pagado || 0).toFixed(2)}</strong></td>
                            <td class="text-center"></td>
                        </tr>`;
                    } else {
                    cuerpoHtml += `<tr>
                        <td class="text-center">${escapeHtml(t.codigo)}</td>
                        <td class="text-center">${escapeHtml(t.ci)}</td>
                        <td class="text-left">${escapeHtml(t.nombre)}</td>
                        <td class="text-center">${escapeHtml(t.categoriaCodigo)}</td>
                        <td class="text-right">$${(t.salarioMensual || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.tarifaSal || 0).toFixed(2)}</td>
                        <td class="text-right">${esVacaciones ? (t.diasTomados || 0).toFixed(2) : (t.horas || 0)}</td>
                        <td class="text-right">$${(t.aCobrar || 0).toFixed(2)}</td>
                        ${esVacaciones ? '' : `<td class="text-right">$${(t.bono || 0).toFixed(2)}</td>`}
                        ${esVacaciones ? '' : `<td class="text-right">$${(t.devengado || 0).toFixed(2)}</td>`}
                        <td class="text-right">$${(t.impS || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.descuentos || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.retenciones || 0).toFixed(2)}</td>
                        <td class="text-right"><strong>$${(t.pagado || 0).toFixed(2)}</strong></td>
                        <td class="text-right">${(t.vacDias || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.tiempoImp || 0).toFixed(2)}</td>
                        <td class="text-center"></td>
                    </tr>`;
                    if (mostrarConcepto) {
                        cuerpoHtml += `<tr class="concepto-fila"><td colspan="17" class="text-left">Observación: ${escapeHtml(t.concepto)}</td></tr>`;
                    }
                }
                
                subtotalPagina.aCobrar += t.aCobrar || 0;
                subtotalPagina.bono += t.bono || 0;
                subtotalPagina.devengado += t.devengado || 0;
                subtotalPagina.impS += t.impS || 0;
                subtotalPagina.descuentos += t.descuentos || 0;
                subtotalPagina.retenciones += t.retenciones || 0;
                subtotalPagina.pagado += t.pagado || 0;
                subtotalPagina.vacDias += t.vacDias || 0;
                subtotalPagina.tiempoImp += t.tiempoImp || 0;
                subtotalPagina.salarioMensual += t.salarioMensual || 0;
                subtotalPagina.horas += t.horas || 0;
                subtotalPagina.importeHE += t.importeHE || 0;
                subtotalPagina.noctT += t.noctT || 0;
                subtotalPagina.importeNtT += t.importeNtT || 0;
                subtotalPagina.noctD += t.noctD || 0;
                subtotalPagina.importeNtD += t.importeNtD || 0;
                subtotalPagina.dt += t.dt || 0;
                subtotalPagina.importeDT += t.importeDT || 0;
            });
            
            if (esBono) {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="5" class="text-right"><strong>SUBTOTAL PÁGINA ${numeroPagina}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.devengado.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.impS.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.descuentos.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.retenciones.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.pagado.toFixed(2)}</strong></td>
                    <td class="text-center">-</td>
                </tr>`;
                
                if (numeroPagina === totalPaginas) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="5" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.devengado.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.impS.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.descuentos.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.retenciones.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td class="text-center">-</td>
                    </tr>`;
                }
            } else if (esExtraominaria) {
                cuerpoHtml += filaTotalExtraordinaria('SUBTOTAL PÁGINA ' + numeroPagina, subtotalPagina, { trClass: 'totales-pagina', cellClass: 'text-right', strong: true });
                
                if (numeroPagina === totalPaginas) {
                    cuerpoHtml += filaTotalExtraordinaria('TOTAL NOMINA', totalGeneralCompleto, { trClass: 'totales-nomina', cellClass: 'text-right', strong: true });
                }
            } else {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="7" class="text-right"><strong>SUBTOTAL PÁGINA ${numeroPagina}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.aCobrar.toFixed(2)}</strong></td>
                    ${esVacaciones ? '' : `<td class="text-right"><strong>$${subtotalPagina.bono.toFixed(2)}</strong></td>`}
                    ${esVacaciones ? '' : `<td class="text-right"><strong>$${subtotalPagina.devengado.toFixed(2)}</strong></td>`}
                    <td class="text-right"><strong>$${subtotalPagina.impS.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.descuentos.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.retenciones.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.pagado.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>${subtotalPagina.vacDias.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.tiempoImp.toFixed(2)}</strong></td>
                    <td class="text-center">-</td>
                </tr>`;
                
                if (numeroPagina === totalPaginas) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="7" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.aCobrar.toFixed(2)}</strong></td>
                        ${esVacaciones ? '' : `<td class="text-right"><strong>$${totalGeneralCompleto.bono.toFixed(2)}</strong></td>`}
                        ${esVacaciones ? '' : `<td class="text-right"><strong>$${totalGeneralCompleto.devengado.toFixed(2)}</strong></td>`}
                        <td class="text-right"><strong>$${totalGeneralCompleto.impS.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.descuentos.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.retenciones.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>${totalGeneralCompleto.vacDias.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.tiempoImp.toFixed(2)}</strong></td>
                        <td class="text-center">-</td>
                    </tr>`;
                }
            }
            
            const paginaHtml = generarHtmlCompletoConPaginacion(
                cuerpoHtml, alcance, filtroNombre, nombreEmpresa, 
                periodoTexto, usuarioNombre, numeroNomina, 
                fechaActual, horaActual, numeroPagina, totalPaginas
            );
            paginasHtmlArray.push(paginaHtml);
            numeroPagina++;
        }
    } else {
        // Lógica agrupada por Área, CC, etc.
        let campoAgrupacion = obtenerCampoAgrupacion(alcance);
        let nombreAgrupacion = obtenerNombreAgrupacionTexto(alcance);
        let grupos = agruparTrabajadores(trabajadores, campoAgrupacion);
        
        let paginas = [];
        let paginaActual = [];
        let contadorFilas = 0;
        let subtotalPagina = { aCobrar:0, bono:0, devengado:0, impS:0, retenciones:0, pagado:0, vacDias:0, tiempoImp:0, descuentos:0, salarioMensual:0, horas:0, importeHE:0, noctT:0, importeNtT:0, noctD:0, importeNtD:0, dt:0, importeDT:0 };

        function cerrarPagina() {
            if (paginaActual.length === 0) return;
            paginas.push({ rows: [...paginaActual], subtotal: { ...subtotalPagina } });
            paginaActual = [];
            contadorFilas = 0;
            subtotalPagina = { aCobrar:0, bono:0, devengado:0, impS:0, retenciones:0, pagado:0, vacDias:0, tiempoImp:0, descuentos:0, salarioMensual:0, horas:0, importeHE:0, noctT:0, importeNtT:0, noctD:0, importeNtD:0, dt:0, importeDT:0 };
        }

        Object.entries(grupos).forEach(([clave, empleados]) => {
            let subTotalGrupo = { aCobrar:0, bono:0, devengado:0, impS:0, retenciones:0, pagado:0, vacDias:0, tiempoImp:0, descuentos:0, salarioMensual:0, horas:0, importeHE:0, noctT:0, importeNtT:0, noctD:0, importeNtD:0, dt:0, importeDT:0 };
            if (paginaActual.length > 0 && (contadorFilas + empleados.length + 2 > FILAS_POR_PAGINA)) {
                cerrarPagina();
            }
            paginaActual.push({ tipo: 'grupo_header', titulo: clave });
            contadorFilas++;

            empleados.forEach(t => {
                if (contadorFilas >= FILAS_POR_PAGINA) {
                    cerrarPagina();
                    paginaActual.push({ tipo: 'grupo_header', titulo: `${clave} (cont.)` });
                    contadorFilas++;
                }
                paginaActual.push({ tipo: 'registro', data: t });
                acumularTotales(subTotalGrupo, t);
                acumularTotales(subtotalPagina, t);
                contadorFilas++;
            });

            paginaActual.push({ tipo: 'grupo_subtotal', titulo: `TOTAL POR ${nombreAgrupacion}`, data: subTotalGrupo });
            contadorFilas++;
        });
        if (paginaActual.length > 0) cerrarPagina();

        paginas.forEach((pag, index) => {
            let numPag = index + 1;
            let cuerpoHtml = '';
            
            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    let totalColsSpan = esBono ? 11 : (esExtraominaria ? 19 : 17);
                    cuerpoHtml += `<tr><td colspan="${totalColsSpan}" style="background:#e0e0e0; font-weight:bold;">${escapeHtml(row.titulo)}</td></tr>`;
                } else if (row.tipo === 'registro') {
                    if (esBono) {
                        cuerpoHtml += `<tr>
                            <td class="text-center" style="border:0.5pt solid #000;">${escapeHtml(row.data.codigo)}</td>
                            <td class="text-center" style="border:0.5pt solid #000;">${escapeHtml(row.data.ci)}</td>
                            <td style="border:0.5pt solid #000;">${escapeHtml(row.data.nombre)}</td>
                            <td class="text-center" style="border:0.5pt solid #000;">${escapeHtml(row.data.categoriaCodigo)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.impS || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; font-weight:bold;">$${(row.data.pagado || 0).toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>
                        <tr class="concepto-fila"><td colspan="11" class="text-left">Observación: ${escapeHtml(row.data.concepto)}</td></tr>`;
                    } else if (esExtraominaria) {
                        cuerpoHtml += `<tr>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.codigo)}</td>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.ci)}</td>
                            <td style="border:0.5pt solid #000;">${escapeHtml(row.data.nombre)}</td>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.categoriaCodigo)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.tarifaSal || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.horas || 0).toFixed(0)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeHE || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.noctT || 0).toFixed(0)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeNtT || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.noctD || 0).toFixed(0)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeNtD || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.dt || 0).toFixed(0)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeDT || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.impS || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.descuentos || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.retenciones || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000; font-weight:bold;">$${(row.data.pagado || 0).toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                    } else {
                        // Se inyecta la columna de Salario Básico (columna 5) para nóminas estándar agrupadas
                        cuerpoHtml += `<tr>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.codigo)}</td>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.ci)}</td>
                            <td style="border:0.5pt solid #000;">${escapeHtml(row.data.nombre)}</td>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.categoriaCodigo)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.tarifaSal || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${esVacaciones ? (row.data.diasTomados || 0).toFixed(2) : row.data.horas}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.aCobrar || 0).toFixed(2)}</td>
                            ${esVacaciones ? '' : `<td style="text-align:right; border:0.5pt solid #000;">$${(row.data.bono || 0).toFixed(2)}</td>`}
                            ${esVacaciones ? '' : `<td style="text-align:right; border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>`}
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.impS || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.descuentos || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${(row.data.retenciones || 0).toFixed(2)}</td>
                            <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">$${(row.data.pagado || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.tiempoImp || 0).toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                        if (mostrarConcepto) {
                            cuerpoHtml += `<tr class="concepto-fila"><td colspan="17" class="text-left">Observación: ${escapeHtml(row.data.concepto)}</td></tr>`;
                        }
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        cuerpoHtml += `
                            <tr class="totales-subgrupo" style="font-size:7.5pt;">
                                <td colspan="5" class="text-right"><strong>${escapeHtml(row.titulo)}:</strong></td>
                                <td class="text-right">$${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right">$${row.data.impS.toFixed(2)}</td>
                                <td class="text-right">$${row.data.descuentos.toFixed(2)}</td>
                                <td class="text-right">$${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right"><strong>$${row.data.pagado.toFixed(2)}</strong></td>
                                <td>-</td>
                            </tr>`;
                    } else if (esExtraominaria) {
                    cuerpoHtml += filaTotalExtraordinaria(escapeHtml(row.titulo) + ':', row.data, { trClass: 'totales-subgrupo', trStyle: 'font-size:7.5pt;', cellClass: 'text-right' });
                } else {
                        cuerpoHtml += `
                            <tr class="totales-subgrupo" style="font-size:7.5pt;">
                                <td colspan="7" class="text-right"><strong>${escapeHtml(row.titulo)}:</strong></td>
                                <td class="text-right">$${row.data.aCobrar.toFixed(2)}</td>
                                ${esVacaciones ? '' : `<td class="text-right">$${row.data.bono.toFixed(2)}</td>`}
                                ${esVacaciones ? '' : `<td class="text-right">$${row.data.devengado.toFixed(2)}</td>`}
                                <td class="text-right">$${row.data.impS.toFixed(2)}</td>
                                <td class="text-right">$${row.data.descuentos.toFixed(2)}</td>
                                <td class="text-right">$${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right"><strong>$${row.data.pagado.toFixed(2)}</strong></td>
                                <td class="text-right">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right">$${row.data.tiempoImp.toFixed(2)}</td>
                                <td>-</td>
                            </tr>`;
                    }
                }
            });

            if (esBono) {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="5" class="text-right"><strong>SUBTOTAL PÁGINA ${numPag}</strong></td>
                    <td class="text-right">$${pag.subtotal.devengado.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.impS.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.descuentos.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.retenciones.toFixed(2)}</td>
                    <td class="text-right"><strong>$${pag.subtotal.pagado.toFixed(2)}</strong></td>
                    <td>-</td>
                </tr>`;

                if (numPag === paginas.length) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="5" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right">$${totalGeneralCompleto.devengado.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.impS.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.descuentos.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.retenciones.toFixed(2)}</td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td>-</td>
                    </tr>`;
                }
            } else if (esExtraominaria) {
                cuerpoHtml += filaTotalExtraordinaria('SUBTOTAL PÁGINA ' + numPag, pag.subtotal, { trClass: 'totales-pagina', cellClass: 'text-right' });

                if (numPag === paginas.length) {
                    cuerpoHtml += filaTotalExtraordinaria('TOTAL NOMINA', totalGeneralCompleto, { trClass: 'totales-nomina', cellClass: 'text-right' });
                }
            } else {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="7" class="text-right"><strong>SUBTOTAL PÁGINA ${numPag}</strong></td>
                    <td class="text-right">$${pag.subtotal.aCobrar.toFixed(2)}</td>
                    ${esVacaciones ? '' : `<td class="text-right">$${pag.subtotal.bono.toFixed(2)}</td>`}
                    ${esVacaciones ? '' : `<td class="text-right">$${pag.subtotal.devengado.toFixed(2)}</td>`}
                    <td class="text-right">$${pag.subtotal.impS.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.descuentos.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.retenciones.toFixed(2)}</td>
                    <td class="text-right"><strong>$${pag.subtotal.pagado.toFixed(2)}</strong></td>
                    <td class="text-right">${pag.subtotal.vacDias.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.tiempoImp.toFixed(2)}</td>
                    <td>-</td>
                </tr>`;

                if (numPag === paginas.length) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="7" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right">$${totalGeneralCompleto.aCobrar.toFixed(2)}</td>
                        ${esVacaciones ? '' : `<td class="text-right">$${totalGeneralCompleto.bono.toFixed(2)}</td>`}
                        ${esVacaciones ? '' : `<td class="text-right">$${totalGeneralCompleto.devengado.toFixed(2)}</td>`}
                        <td class="text-right">$${totalGeneralCompleto.impS.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.descuentos.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.retenciones.toFixed(2)}</td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td class="text-right">${totalGeneralCompleto.vacDias.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.tiempoImp.toFixed(2)}</td>
                        <td>-</td>
                    </tr>`;
                }
            }

            const paginaHtml = generarHtmlCompletoConPaginacion(
                cuerpoHtml, alcance, filtroNombre, nombreEmpresa, 
                periodoTexto, usuarioNombre, numeroNomina, 
                fechaActual, horaActual, numPag, paginas.length
            );
            paginasHtmlArray.push(paginaHtml);
        });
    }

    const ventana = window.open('', '_blank');
    if (!ventana) return;
    ventana.document.write(paginasHtmlArray.join('<div class="page-break"></div>'));
    ventana.document.close();
};

// Control interactivo de las tarjetas de selección
$(document).on('click', '.print-option-card', function() {
    $('.print-option-card').removeClass('selected');
    $(this).addClass('selected');
    
    const targetRadioId = $(this).data('target-radio');
    $('#' + targetRadioId).prop('checked', true).trigger('change');

    if (targetRadioId === 'alcanceGeneral') {
        $('#selectoresDinamicos').slideUp(200);
        $('#resumenSeleccion').slideUp(200);
    } else {
        $('#selectoresDinamicos').slideDown(250);
        $('#resumenSeleccion').slideDown(250);
    }
});

// Limpieza de parámetros al ocultar el modal
$('#modalOpcionesImpresion').on('hidden.bs.modal', function () {
    $('.print-option-card').removeClass('selected');
    $('.print-option-card[data-target-radio="alcanceGeneral"]').addClass('selected');
    $('#alcanceGeneral').prop('checked', true);
    $('#selectoresDinamicos').hide();
    $('#resumenSeleccion').hide();
    $('#contenedorSelectores').empty();
});

// Intercepción y mapeo de acciones del Dropdown del modal
// Intercepción y mapeo de acciones del Dropdown del modal
$(document).on('click', '.opt-impresion', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const action = $(this).data('action');
    const alcance = $('input[name="alcanceImpresion"]:checked').val();
    let filtroValor = null;
    let filtroNombre = null;

    // IMPORTANTE: Para 'tirillas' NO se necesita filtro de selector
    if (alcance !== 'general' && alcance !== 'tirillas') {
        filtroValor = $('#selectImpresion').val();
        filtroNombre = $('#selectImpresion option:selected').text();
        
        if (filtroValor === '') {
            filtroNombre = 'Todos';
        } else if (!filtroValor) {
            Swal.fire({
                title: 'Atención',
                text: 'Por favor, elija un valor en el menú desplegable del alcance seleccionado.',
                icon: 'warning',
                background: '#1e1e24',
                color: '#ffffff'
            });
            return;
        }
    }

    const isTodos = (filtroValor === ''); 

    // Colección de datos y procesamiento de filtros de alcance
    const dataTable = $('#tablaNominas').DataTable();
    const filas = dataTable.rows({ search: 'applied' }).nodes();
    let trabajadoresFiltrados = [];

    $(filas).each(function() {
        const $row = $(this);
        let incluir = true;

        const areaId = $row.data('area-id');
        const centroId = $row.data('centro-costo-id');
        const categoriaId = $row.data('categoria-ocupacional-id');
        const escalaId = $row.data('escala-id');
        const tipoContrato = $row.data('tipo-contrato');
        const cargoId = $row.data('cargo-id');

        switch (alcance) {
            case 'area':
                incluir = (filtroValor === '' || areaId == filtroValor);
                break;
            case 'centro_costo':
                incluir = (filtroValor === '' || centroId == filtroValor);
                break;
            case 'categoria':
                incluir = (filtroValor === '' || categoriaId == filtroValor);
                break;
            case 'escala':
                incluir = (filtroValor === '' || escalaId == filtroValor);
                break;
            case 'tipo_contrato':
                incluir = (filtroValor === '' || tipoContrato == filtroValor);
                break;
            case 'cargo':
                incluir = (filtroValor === '' || cargoId == filtroValor);
                break;
            default:
                incluir = true;
        }

	if (incluir) {
				// Mapeo unificado y seguro de campos
				const trabajador = mapRowToTrabajador($row);
				trabajadoresFiltrados.push(trabajador);
			}
		});

    // NUEVO: Manejo de Tirillas de Pago
    if (alcance === 'tirillas') {
        generarTirillasPago(trabajadoresFiltrados);
        bootstrap.Modal.getInstance(document.getElementById('modalOpcionesImpresion')).hide();
        return;
    }
    
    if (trabajadoresFiltrados.length === 0) {
        Swal.fire({
            title: '<i class="fas fa-users-slash me-2" style="color: #ef4444;"></i> Sin trabajadores',
            html: '<div class="text-center"><i class="fas fa-search fa-3x mb-3" style="color: #ef4444; opacity: 0.7;"></i><p>No se encontraron trabajadores con los filtros seleccionados.</p></div>',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    // Ejecución de la acción seleccionada
    if (action === 'imprimir') {
        window.generarNominaImpresa(trabajadoresFiltrados, alcance, filtroNombre, isTodos);
    } else if (action === 'pdf') {
        exportarPdfOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'excel') {
        exportarExcelOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'word') {
        exportarWordOficial(trabajadoresFiltrados, alcance, filtroNombre);
    }

    // Ocultar modal
    bootstrap.Modal.getInstance(document.getElementById('modalOpcionesImpresion')).hide();
});


function generarHtmlCompletoConPaginacion(cuerpoHtml, alcance, filtroNombre, nombreEmpresa, periodoTexto, usuarioNombre, numeroNomina, fechaActual, horaActual, pagina, totalPaginas) {
    const codigoMostrado = (numeroNomina === 'S/N' || numeroNomina === 'Borrador' || !numeroNomina) ? '' : escapeHtml(numeroNomina);
    const esBono = (tipoNomina === 'bono');
    const esAjuste = (tipoNomina === 'ajuste');
    const esExtraominaria = (tipoNomina === 'extraordinaria');
    const esVacaciones = (tipoNomina === 'vacaciones');
    const colsCount = esBono ? 11 : (esExtraominaria ? 19 : (esVacaciones ? 16 : 17));

    const cabecerasTabla = esBono ? `
        <tr>
            <th style="width:5%;">Código</th>
            <th style="width:10%;">CI</th>
            <th style="width:30%;">Nombre y Apellidos</th>
            <th style="width:5%;">Cat.</th>
            <th style="width:10%;">S. Básico</th>
            <th style="width:8%;">Deven.</th>
            <th style="width:8%;">Imp. CESS</th>
            <th style="width:8%;">Deducc.</th>
            <th style="width:8%;">Ret Total</th>
            <th style="width:8%;">Pagado</th>
            <th style="width:10%;">Firma</th>
        </tr>
    ` : esExtraominaria ? `
        <tr>
            <th style="width:3%">Código</th><th style="width:6%">CI</th><th style="width:22%">Nombre y Apellidos</th><th style="width:3%">Cat.</th><th style="width:3%">Tarf.</th>
            <th style="width:4%">HE/D</th><th style="width:4%">$HE/D</th><th style="width:3%">Nt 7-23h</th><th style="width:4%">$/Nt 7-23h</th>
            <th style="width:3%">Nt 23-7h</th><th style="width:4%">$/Nt 23-7h</th>
            <th style="width:3%">D/T</th><th style="width:4%">$/DT</th>
            <th style="width:6%">Deven.</th><th style="width:5%">Imp. CESS</th><th style="width:5%">Dsctos.</th><th style="width:5%">Ret. Tot.</th><th style="width:6%">Pagado</th><th style="width:8%">Firma</th>
        </tr>
    ` : `
        <tr>
            <th>Código</th><th>CI</th><th>Nombre y Apellidos</th><th>Cat.</th><th>S. Básico</th><th style="width:3%">Tarf.</th><th>${esVacaciones ? 'Días' : 'Horas'}</th>
            <th>A cobrar</th>${esVacaciones ? '' : '<th>Bon.</th>'}${esVacaciones ? '' : '<th>Deven.</th>'}<th>Imp. CESS.</th>
            <th>Dsctos.</th><th>Ret. Tot.</th><th>Pagado</th><th>Vac.</th><th>Tiem. Imp.</th><th>Firma</th>
        </tr>
    `;

    return `<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Nómina de Salarios - ${escapeHtml(nombreEmpresa)}</title>
    <style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size:10pt; margin:15mm 10mm; background: white; color: black; }
        .nomina-container { width:100%; max-width:81.25rem; margin:0 auto; }
        .header-nomina { width:100%; border-collapse: collapse; margin-bottom:0.9375rem; border: 0.0625rem solid #000; }
        .header-nomina td { border: 0.0625rem solid #000; padding:0.25rem 0.375rem; font-size:8.5pt; vertical-align: middle; }
        .header-label { font-weight: bold; background-color: #f0f0f0; }
        .tabla-nomina { width:100%; border-collapse: collapse; font-size:9pt; margin-top:0.625rem; }
        .tabla-nomina th, .tabla-nomina td { border: 0.0625rem solid #000; padding:0.25rem 0.125rem; }
        .tabla-nomina th { background-color: #004B87; color: white; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .area-header td { background-color: var(--txt); font-weight: bold; }
        .totales-subgrupo { background-color: #f9f9f9; font-weight: bold; }
        .totales-pagina { background-color: #fff3cd; font-weight: bold; }
        .totales-nomina { background-color: #d9e1f2; font-weight: bold; }
        .concepto-fila td { text-align: left !important; }
        .footer { margin-top:1.25rem; font-size:8pt; text-align: center; border-top: 0.0625rem solid #ccc; padding-top:0.5rem; }
        .page-break { page-break-before: always; }
        @media print { body { margin:0; padding:0; } .no-print { display: none !important; } }
    </style>
    </head>
    <body>
    ${PRINT_TOOLBAR_HTML}
    <div class="nomina-container">
        <!-- HEADER SC-4-06 -->
<table class="header-nomina">
    <tr style="height:28pt;">
        <td width="54" style="width:40.85pt; text-align: center;">
            ${logoBase64 ? `<img width="40" height="38" src="${logoBase64}" style="display: block; margin:0 auto;">` : ''}
        </td>
        <td colspan="${colsCount - 2}" style="text-align: center;">
            <span style="font-size:14pt; font-weight: bold;">MODELO SC-4-06 NOMINA - ${escapeHtml(nombreEmpresa)}</span>
        </td>
        <td width="170" style="width:127.3pt; text-align: center;">
            <span style="font-size:11pt; font-weight: bold;">Página ${pagina} de ${totalPaginas}</span>
        </td>
    </tr>
    <tr style="height:13pt;">
        <td colspan="3">
            <strong>Tipo Nómina:</strong> <span style="font-style: italic;font-weight: bold;font-size:0.75rem;">${escapeHtml(tipoNominaTexto)}</span>
        </td>
        <td colspan="${esBono ? 2 : 1}">
            <strong>Código:</strong> <span>${codigoMostrado}</span>
        </td>
        <td colspan="${esBono ? 6 : 10}" rowspan="5" style="padding:0.5rem 0.625rem; line-height:1.6; font-size:8.5pt;">
            <div style="margin-bottom:0.375rem;">
                <b>REVISADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size:13pt; color: #000;">${escapeHtml(especialistaGestion)}</span>
            </div>
            <div style="margin-bottom:0.375rem;">
                <b>APROBADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size:13pt; color: #000;">${escapeHtml(jefeProyecto)}</span>
            </div>
            <div style="margin-bottom:0.375rem; font-size:8.5pt; color: #222;"><b>ELABORADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size:13pt; color: #000;">${escapeHtml(especialistaNominas)}</span></div>
            <div style="font-size:8.5pt; color: #222;"><b>CONTABILIZADA POR:</b> <span>__________________________________</span></div>
        </td>
    </tr>
    
    <!-- 🔽 FILA 1: Monto a Distribuir (SOLO PARA BONO) o Cheque No. (para otros tipos) -->
	${esBono ? `
		<tr>
			<td colspan="${esBono ? 5 : 4}">
				<strong>Monto a Distribuir:</strong> 
				<span style="${montoDistribuidoGlobal > 0 ? 'font-weight: bold; color: #004B87; font-size:11pt;' : 'font-weight: bold; color: #ef4444; font-size:11pt;'}">
					${montoDistribuidoGlobal > 0 ? '$' + montoDistribuidoGlobal.toFixed(2) : '(Hasta que se contabilice)'}
				</span>
			</td>
		</tr>
            ` : `
		<tr>
			<td colspan="${esBono ? 5 : 4}">
				<strong>Cheque No.:</strong> <span></span>
			</td>
		</tr>
	`}
    
    <!-- 🔽 FILA 2: No. Instrum. Pago (SIEMPRE SE MUESTRA) -->
    <tr style="height:12pt;">
        <td colspan="${esBono ? 5 : 4}">
            <strong>No. Instrum. Pago:</strong> <span>${escapeHtml(numeroNomina)}</span>
        </td>
    </tr>
    
    <!-- 🔽 FILA 3: Alcance y Fecha Impresa -->
    <tr style="height:12pt;">
        <td colspan="2">
            <strong>Alcance:</strong> <span>${escapeHtml(alcance.toUpperCase())}${filtroNombre ? ': ' + escapeHtml(filtroNombre) : ''}</span>
        </td>
        <td colspan="${esBono ? 3 : 2}">
            <strong>Fecha Impresa:</strong> <span>${fechaActual} ${horaActual}</span>
        </td>
    </tr>
    
    <!-- 🔽 FILA 4: Período de Pago y REEUP/NIT -->
    <tr style="height:24pt;">
        <td colspan="2" style="vertical-align: middle;">
            <div style="margin-bottom:0.125rem;"><strong>Período de Pago:</strong> <span>${periodoTexto}</span></div>
            <div><strong>MES / AÑO:</strong> <span>${escapeHtml(nombreMesGlobal)} / ${escapeHtml(anioGlobal)}</span></div>
        </td>
        <td colspan="${esBono ? 3 : 2}" style="vertical-align: middle;">
            <div><strong>Código REEUP:</strong> <span>${escapeHtml(reeup)}</span></div>
            <div><strong>NIT Empresa:</strong> <span>${escapeHtml(nitEmpresa)}</span></div>
        </td>
    </tr>
    
    <!-- 🔽 FILA 5: Observaciones de Cierre (SOLO SI EXISTEN) -->
    ${observacionesCierreGlobal ? `
    <tr style="height:14pt;">
        <td colspan="${colsCount}" style="background-color: #f8fafc; font-size:8pt; border: 0.0625rem solid #000;">
            <strong>Observaciones de Cierre:</strong> <span>${escapeHtml(observacionesCierreGlobal)}</span>
        </td>
    </tr>` : ''}
</table>

        <table class="tabla-nomina">
            <thead>${cabecerasTabla}</thead>
            <tbody>${cuerpoHtml}</tbody>
        </table>
        <div class="footer">
            <div>Documento generado por el Sistema de Gestión de Nóminas de ${escapeHtml(nombreEmpresa)}</div>
        </div>
    </div>
    </body>
    </html>`;
}

function generarHtmlCompleto(cuerpoHtml, duplicadaRows, duplicadaTotales, alcance, filtroNombre, nombreEmpresa, periodoTexto, usuarioNombre, numeroNomina, fechaActual, horaActual, jefeProyecto, especialistaGestion) {
    const codigoMostrado = (numeroNomina === 'S/N' || numeroNomina === 'Borrador' || !numeroNomina) ? '' : escapeHtml(numeroNomina);

    return `<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Nómina de Salarios - ${escapeHtml(nombreEmpresa)}</title>
    <style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size:10pt; margin:15mm 10mm; background: white; color: black; }
        .nomina-container { width:100%; max-width:81.25rem; margin:0 auto; }
        .header-nomina { width:100%; border-collapse: collapse; margin-bottom:0.9375rem; border: 0.0625rem solid #000; }
        .header-nomina td { border: 0.0625rem solid #000; padding:0.25rem 0.375rem; font-size:8.5pt; vertical-align: middle; }
        .header-label { font-weight: bold; background-color: #f0f0f0; }
        .tabla-nomina { width:100%; border-collapse: collapse; font-size:9pt; margin-top:0.625rem; }
        .tabla-nomina th, .tabla-nomina td { border: 0.0625rem solid #000; padding:0.25rem 0.125rem; }
        .tabla-nomina th { background-color: #004B87; color: white; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .area-header td { background-color: var(--txt); font-weight: bold; }
        .totales-subgrupo { background-color: #f9f9f9; font-weight: bold; }
        .totales-nomina { background-color: #d9e1f2; font-weight: bold; }
        .duplicada { margin-top:1.875rem; border-top: 0.125rem dashed #000; padding-top:0.9375rem; }
        .footer { margin-top:1.25rem; font-size:8pt; text-align: center; border-top: 0.0625rem solid #ccc; padding-top:0.5rem; }
        @media print { body { margin:0; padding:0; } .no-print { display: none !important; } .page-break { page-break-before: always; } }
    </style>
    </head>
    <body>
    ${PRINT_TOOLBAR_HTML}
    <div class="nomina-container">
        <!-- HEADER SC-4-06 -->
        <table class="header-nomina">
            <tr style="height:28pt;">
                <td width="54" style="width:40.85pt; text-align: center;">
                    ${logoBase64 ? `<img width="40" height="38" src="${logoBase64}" style="display: block; margin:0 auto;">` : ''}
                </td>
                <td width="749" colspan="4" style="width:561.65pt; text-align: center;">
                    <span style="font-size:14pt; font-weight: bold;">MODELO SC-4-06 NOMINA - ${escapeHtml(nombreEmpresa)}</span>
                </td>
                <td width="170" style="width:127.3pt; text-align: center;">
                    <span style="font-size:11pt; font-weight: bold;">Página 1 de 1</span>
                </td>
            </tr>
            <tr style="height:13pt;">
                <td colspan="3" style="width:304.55pt;">
                    <strong>Tipo Nómina:</strong> <span style="font-style: italic;">${escapeHtml(tipoNominaTexto)}</span>
                </td>
                <td style="width:142.8pt;">
                    <strong>Código:</strong> <span>${codigoMostrado}</span>
                </td>
                <td colspan="2" rowspan="5" style="width:282.45pt; padding:0.5rem 0.625rem; line-height:1.6; font-size:8.5pt;">
                    <div style="margin-bottom:0.375rem;">
                        <b>REVISADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size:13pt; color: #000;"><?php echo htmlspecialchars($config_empresa['especialista_gestion'] ?? ESPECIALISTA, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div style="margin-bottom:0.375rem;">
                        <b>APROBADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size:13pt; color: #000;"><?php echo htmlspecialchars($config_empresa['jefe_proyecto'] ?? JEFE_PROYECTO, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div style="margin-bottom:0.375rem; font-size:8.5pt; color: #222;"><b>ELABORADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size:13pt; color: #000;"><?php echo htmlspecialchars($config_empresa['especialista_nominas'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div style="font-size:8.5pt; color: #222;"><b>CONTABILIZADA POR:</b> <span>______________________________________</span></div>
                </td>
            </tr>
            <tr style="height:12pt;">
                <td colspan="4">
                    <strong>Cheque No.:</strong> <span></span>
                </td>
            </tr>
            <tr style="height:12pt;">
                <td colspan="4">
                    <strong>No. Instrum. Pago:</strong> <span>${escapeHtml(numeroNomina)}</span>
                </td>
            </tr>
            <tr style="height:12pt;">
                <td colspan="2" style="width:269.1pt;">
                    <strong>Alcance:</strong> <span>${escapeHtml(alcance.toUpperCase())}${filtroNombre ? ': ' + escapeHtml(filtroNombre) : ''}</span>
                </td>
                <td colspan="2" style="width:178.25pt;">
                    <strong>Fecha Impresa:</strong> <span>${fechaActual} ${horaActual}</span>
                </td>
            </tr>
			<tr style="height:24pt;">
                <td colspan="2" style="width:269.1pt; vertical-align: middle;">
                    <div style="margin-bottom:0.125rem;"><strong>Período de Pago:</strong> <span>${periodoTexto}</span></div>
                    <div><strong>MES / AÑO:</strong> <span><?php echo addslashes($nombre_mes); ?> / <?php echo addslashes($anio); ?></span></div>
                </td>
                <td colspan="2" style="width:178.25pt; vertical-align: middle;">
                    <div><strong>Código REEUP:</strong> <span>${escapeHtml(reeup)}</span></div>
                    <div><strong>NIT Empresa:</strong> <span>${escapeHtml(nitEmpresa)}</span></div> <!-- <-- NIT INYECTADO -->
                </td>
            </tr>
        </table>

        <table class="tabla-nomina">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>CI</th>
                    <th>Nombre y Apellidos</th>
                    <th>Cat.</th>
                    <th>Tarf.</th>
                    <th>Horas</th>
                    <th>A cobrar</th>
                    <th>Bon.</th>
                    <th>Deven.</th>
                    <th>Imp. CESS.</th>
                    <th>Ret.</th>
                    <th>Pagado</th>
                    <th>Vac.</th>
                    <th>Tiem. Imp.</th>
                    <th>Firma</th>
                </tr>
            </thead>
            <tbody>${cuerpoHtml}</tbody>
        </table>

        <div class="duplicada">
            <h4 style="text-align:center; font-size:10pt;">--- CORTE PARA VOLTEO ---</h4>
            <table class="tabla-nomina">
                <thead><tr><th>Código</th><th>CI</th><th>Nombre</th><th>A cobrar</th><th>Bon.</th><th>Deven.</th><th>Imp. S.</th><th>Ret.</th><th>Pagado</th></tr></thead>
                <tbody>${duplicadaRows}${duplicadaTotales}</tbody>
            </table>
        </div>

        <div class="footer">
            <div>Documento generado por el Sistema de Gestión de Nóminas de ${escapeHtml(nombreEmpresa)}</div>
        </div>
    </div>
    </body>
    </html>`;
}




// ==========================================
// FILTROS EN MODALES: ÁREA Y CENTRO DE COSTO
// ==========================================
$(document).on('change', '.filter-modal-worker', function() {
    var modal = $(this).data('modal'); // 'extra', 'vac', 'bono'
    var searchVal = '';
    
    if (modal === 'extra') {
        searchVal = $('#searchExtraWorker').val();
        renderExtraWorkerList(searchVal);
    } else if (modal === 'vac') {
        searchVal = $('#searchWorker').val();
        renderWorkerList(searchVal);
    } else if (modal === 'bono') {
        searchVal = $('#searchBonoWorker').val();
        renderBonoWorkerList(searchVal);
    } else if (modal === 'ajuste') {
        searchVal = $('#searchAjusteWorker').val();
        renderAjusteWorkerList(searchVal);
    }
});

    if (filtroCuentaActual) {
        $('#filtroCuenta').val(filtroCuentaActual);
    }
    
$('#filtroCuenta').on('change', function() {
    if (nominasTable) aplicarFiltros();
});

$('#tablaNominas').on('click', 'tbody tr', function(e) {
    // Evitar abrir el modal si hace clic en inputs, botones o selects
    if ($(e.target).closest('input, button, select, .btn-icon, a, .edit-input').length) {
        return;
    }
    
    // Evitar abrir el modal si hace clic en la columna de acciones (última columna)
    if ($(e.target).closest('td').is(':last-child')) {
        return;
    }

    if ($('.modal.show').length > 0) {
        return;
    }

    var $row = $(this);
    var rowId = $row.attr('data-id') || $row.data('id'); // <-- MODIFICADO
    if (!rowId) return;

    var table = $('#tablaNominas').DataTable();
    var allVisibleRows = table.rows({ search: 'applied' }).nodes();
    var currentPos = Array.from(allVisibleRows).findIndex(node => $(node).attr('data-id') == rowId);

    window.editCurrentRowId = rowId;
    window.editCurrentRowIndex = currentPos;
    window.editVisibleRowsIds = Array.from(allVisibleRows).map(node => $(node).attr('data-id') || $(node).data('id'));

    cargarModalEdicion($row);
    
	var modalEl = document.getElementById('modalEdicionRapida');
	if (modalEl) {
		// Verificar si existe el modal en el DOM
		var modal = bootstrap.Modal.getInstance(modalEl);
		if (!modal) {
			modal = new bootstrap.Modal(modalEl, {
				backdrop: 'static',
				keyboard: false
			});
		}
		modal.show();
	} else {
		console.error('Modal no encontrado: modalEdicionRapida');
	}
});

    $(document).on('change', '.edit-dias, #editDias, .dias-input', function() {
        var $input = $(this);
        var valorRaw = parseFloat($input.val()) || 1;
        
        var valorRedondeado = Math.roundExcel(valorRaw * 2) / 2;
        
        if (valorRedondeado < 1) {
            valorRedondeado = 1;
        }
        
        $input.val(valorRedondeado);
        $input.trigger('input');
    });

// ==========================================
// AGREGAR TRABAJADORES A NÓMINA AUTOMÁTICA
// ==========================================
var trabajadoresDisponibles = [];
var trabajadoresSeleccionadosAuto = [];

function cargarTrabajadoresDisponibles() {
    var idsEnNomina = [];
    
    <?php
    $stmt_ids_nomina = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $stmt_ids_nomina->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    $ids_nomina_db = $stmt_ids_nomina->fetchAll(PDO::FETCH_COLUMN);
    ?>
    var idsEnNominaDB = <?php echo json_encode($ids_nomina_db); ?>;
    
    var idsEnTabla = [];
    $('#tablaNominas tbody tr').each(function() {
        var id = $(this).data('trabajador-id');
        if (id) idsEnTabla.push(parseInt(id));
    });
    
    var idsEnNomina = idsEnNominaDB;
    
    var areaVal = $('#filterAutoArea').val();
    var ccVal = $('#filterAutoCC').val();
    var searchVal = $('#searchAutoWorker').val().toLowerCase();
    
    var disponibles = trabajadores.filter(function(t) {
        if (idsEnNomina.includes(parseInt(t.id))) return false;
        if (areaVal && parseInt(t.area_id) !== parseInt(areaVal)) return false;
        if (ccVal && parseInt(t.centro_costo_id) !== parseInt(ccVal)) return false;
        if (searchVal && !t.nombre_completo.toLowerCase().includes(searchVal) && 
            !t.codigo.toLowerCase().includes(searchVal) && 
            !t.ci.toLowerCase().includes(searchVal)) return false;
        return true;
    });
    
    trabajadoresDisponibles = disponibles;
    renderAutoWorkerList(idsEnNomina);
}

function renderAutoWorkerList(idsEnNomina) {
    var html = '';
    
    if (!idsEnNomina) {
        idsEnNomina = [];
        $('#tablaNominas tbody tr').each(function() {
            var id = $(this).data('trabajador-id');
            if (id) idsEnNomina.push(parseInt(id));
        });
    }
    
    var totalTrabajadoresSistema = trabajadores.length;
    var totalEnNomina = idsEnNomina.length;
    var disponiblesCount = trabajadoresDisponibles.length;
    var faltantes = totalTrabajadoresSistema - totalEnNomina;
    
    var coberturaColor = '';
    var coberturaIcono = '';
    var coberturaTexto = '';
    
    if (totalEnNomina === 0) {
        coberturaColor = '#ef4444';
        coberturaIcono = '<i class="fas fa-times-circle"></i>';
        coberturaTexto = '⚠️ NINGÚN trabajador en nómina';
    } else if (totalEnNomina === totalTrabajadoresSistema) {
        coberturaColor = '#10b981';
        coberturaIcono = '<i class="fas fa-check-circle"></i>';
        coberturaTexto = '✅ NÓMINA COMPLETA - Todos los trabajadores están incluidos';
    } else {
        coberturaColor = '#f59e0b';
        coberturaIcono = '<i class="fas fa-chart-line"></i>';
        coberturaTexto = `📊 Cobertura: ${totalEnNomina} de ${totalTrabajadoresSistema} trabajadores (${Math.round(totalEnNomina/totalTrabajadoresSistema*100)}%) - Faltan ${faltantes}`;
    }
    
    html += `<div class="alert alert-info mb-3" style="background: rgba(16, 185, 129, 0.1); border: 0.0625rem solid 1; border-radius: 0.625rem;">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <i class="fas fa-chart-pie me-2"></i>
                        <strong style="color: ${coberturaColor};">${coberturaTexto}</strong>
                    </div>
                    <div>
                        <span class="badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;">
                            <i class="fas fa-users me-1"></i> Total sistema: ${totalTrabajadoresSistema}
                        </span>
                        <span class="badge ms-2" style="background: rgba(16, 185, 129, 0.2); color: 1;">
                            <i class="fas fa-check-circle me-1"></i> En nómina: ${totalEnNomina}
                        </span>
                        <span class="badge ms-2" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                            <i class="fas fa-user-plus me-1"></i> Faltan: ${faltantes}
                        </span>
                    </div>
                </div>
                <hr class="my-2" style="border-color: rgba(148, 163, 184, 0.25); opacity: 1;">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Los trabajadores en <span style="color: #ef4444;">ROJO</span> ya están en la nómina. 
                    Los de <span style="color: 1;">VERDE</span> están disponibles para agregar.
                </small>
            </div>`;
    
    if (trabajadoresDisponibles.length === 0) {
        html += '<div class="text-center p-4 text-white-50" style="background: rgba(16, 185, 129, 0.05); border-radius: 0.75rem; margin-bottom:0.9375rem;">';
        html += '<i class="fas fa-check-circle fa-3x mb-2 text-success"></i>';
        html += '<br><strong>Todos los trabajadores ya están incluidos en la nómina</strong>';
        html += '<br><small>No hay trabajadores disponibles para agregar</small>';
        html += '</div>';
    } else {
        html += `<div class="mb-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-user-plus me-1" style="color: 1;"></i> Trabajadores DISPONIBLES para agregar (${trabajadoresDisponibles.length}):</strong>
                    <button type="button" class="btn-seleccionar-todos-sm" style="background: rgba(16, 185, 129, 0.15); border: 0.0625rem solid 1; border-radius: 0.375rem; padding:0.25rem 0.625rem; font-size:0.7rem; color: #10b981;">
                        <i class="fas fa-check-double me-1"></i> Seleccionar todos
                    </button>
                </div>`;
        
        trabajadoresDisponibles.forEach(function(t) {
            var isSelected = trabajadoresSeleccionadosAuto.some(function(s) { return s.id == t.id; });
            var centroCostoNombre = 'Sin CC';
            if (t.centro_costo_codigo && t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_codigo + ' - ' + t.centro_costo_nombre;
            } else if (t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_nombre;
            }
            
            html += `<div class="worker-item ${isSelected ? 'selected' : ''}" data-id="${t.id}" data-nombre="${t.nombre_completo}" data-codigo="${t.codigo}" data-salario-mensual="${t.salario_mensual}" data-salario-hora="${t.salario_hora_ordinaria}" data-area="${t.nombre_area || 'Sin área'}" data-centro-costo="${centroCostoNombre}" style="border-left: 0.1875rem solid 1;">
                <input type="checkbox" class="worker-checkbox" ${isSelected ? 'checked' : ''}>
                <div class="worker-avatar" style="background: rgba(16, 185, 129, 0.15);">
                    <i class="fas fa-user" style="color: 1;"></i>
                </div>
                <div class="worker-info">
                    <div class="worker-name">${t.codigo} - ${t.nombre_completo}</div>
                    <div class="worker-detail">
                        <i class="fas fa-briefcase me-1"></i> ${t.cargo || 'Sin cargo'} 
                        <i class="fas fa-building ms-2 me-1"></i> ${t.nombre_area || 'Sin área'}
                        <i class="fas fa-chart-pie ms-2 me-1"></i> ${centroCostoNombre}
                    </div>
                    <div class="worker-detail small text-muted">
                        Salario: $${parseFloat(t.salario_mensual).toFixed(2)} mensual | $${parseFloat(t.salario_hora_ordinaria).toFixed(2)}/hora
                    </div>
                </div>
                <div class="worker-dias">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: 1;">
                        <i class="fas fa-check-circle me-1"></i> Disponible
                    </span>
                </div>
            </div>`;
        });
    }
    
    var trabajadoresYaIncluidos = trabajadores.filter(function(t) {
        return idsEnNomina.includes(parseInt(t.id));
    });
    
    if (trabajadoresYaIncluidos.length > 0) {
        html += `<div class="mt-4 pt-3 border-top border-secondary"><strong class="text-muted"><i class="fas fa-check-circle me-1"></i> Trabajadores YA INCLUIDOS en nómina (${trabajadoresYaIncluidos.length}):</strong></div>`;
        
        trabajadoresYaIncluidos.forEach(function(t) {
            var centroCostoNombre = 'Sin CC';
            if (t.centro_costo_codigo && t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_codigo + ' - ' + t.centro_costo_nombre;
            } else if (t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_nombre;
            }
            
            html += `<div class="worker-item disabled" style="opacity: 0.6; border-left: 0.1875rem solid #ef4444; cursor: not-allowed; background: rgba(239, 68, 68, 0.05);">
                <div class="worker-avatar" style="background: rgba(239, 68, 68, 0.15);">
                    <i class="fas fa-user-check" style="color: #ef4444;"></i>
                </div>
                <div class="worker-info">
                    <div class="worker-name">${t.codigo} - ${t.nombre_completo}</div>
                    <div class="worker-detail">
                        <i class="fas fa-briefcase me-1"></i> ${t.cargo || 'Sin cargo'} 
                        <i class="fas fa-building ms-2 me-1"></i> ${t.nombre_area || 'Sin área'}
                        <i class="fas fa-chart-pie ms-2 me-1"></i> ${centroCostoNombre}
                    </div>
                </div>
                <div class="worker-dias">
                    <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                        <i class="fas fa-check-circle me-1"></i> Ya incluido
                    </span>
                </div>
            </div>`;
        });
    }
    
    $('#autoWorkerList').html(html);
    $('#selectedAutoCount').text(trabajadoresSeleccionadosAuto.length);
    $('#btnConfirmarAgregarAuto').prop('disabled', trabajadoresSeleccionadosAuto.length === 0);
}

$(document).on('click', '#autoWorkerList .worker-item', function() {
    if ($(this).hasClass('disabled')) return;
    var id = $(this).data('id');
    var idx = trabajadoresSeleccionadosAuto.findIndex(function(s) { return s.id == id; });
    
    if (idx >= 0) {
        trabajadoresSeleccionadosAuto.splice(idx, 1);
    } else {
        trabajadoresSeleccionadosAuto.push({
            id: id,
            nombre: $(this).data('nombre'),
            codigo: $(this).data('codigo'),
            salario_mensual: parseFloat($(this).data('salario-mensual')),
            salario_hora: parseFloat($(this).data('salario-hora')),
            area: $(this).data('area'),
            centro_costo: $(this).data('centro-costo')
        });
    }
    renderAutoWorkerList();
});

$('#filterAutoArea, #filterAutoCC, #searchAutoWorker').on('change input', function() {
    cargarTrabajadoresDisponibles();
});

$('#modalAgregarTrabajadoresAuto').on('show.bs.modal', function() {
    trabajadoresSeleccionadosAuto = [];
    cargarTrabajadoresDisponibles();
});

$('#btnConfirmarAgregarAuto').on('click', function() {
    if (trabajadoresSeleccionadosAuto.length === 0) return;
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Agregando...',
        text: 'Procesando ' + trabajadoresSeleccionadosAuto.length + ' trabajadores',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    var datos = {
        agregar_trabajadores_auto: 1,
        trabajadores: trabajadoresSeleccionadosAuto.map(function(w) { return w.id; }),
        periodo_desde: '<?php echo $periodo_desde; ?>',
        periodo_hasta: '<?php echo $periodo_hasta; ?>',
        tipo_descuento: activeTipoDescuento
    };
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: datos,
        success: function(r) {
            if (r.success) {
                Swal.fire({
                    title: '<i class="fas fa-check-circle text-success me-2"></i> Completado',
                    html: 'Se agregaron <strong>' + r.agregados + '</strong> trabajadores a la nómina.',
                    icon: 'success',
                    background: '#1a1a2e',
                    color: '#ffffff',
                    confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Recargar'
                }).then(function() {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: r.error || 'No se pudieron agregar los trabajadores',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: '#ffffff',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error de conexión',
                text: 'No se pudo completar la solicitud',
                icon: 'error',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            });
        }
    });
});

function limpiarFiltroContrario(modalId, filtroQueCambio) {
    if (filtroQueCambio === 'area') {
        $(`#filter${modalId}CC`).val('');
    } else if (filtroQueCambio === 'cc') {
        $(`#filter${modalId}Area`).val('');
    }
    var searchVal = $(`#search${modalId}Worker`).val();
    if (modalId === 'Extra') renderExtraWorkerList(searchVal);
    else if (modalId === 'Vac') renderWorkerList(searchVal);
    else if (modalId === 'Bono') renderBonoWorkerList(searchVal);
    else if (modalId === 'Ajuste') renderAjusteWorkerList(searchVal);
}

$('#filtroArea').on('change', function() {
    if ($(this).val() !== '') {
        $('#filtroTrabajador').val('').trigger('change.select2');
        if (nominasTable) aplicarFiltros();
    } else {
        if (nominasTable) aplicarFiltros();
    }
});

$('#filtroCentroCosto').on('change', function() {
    if ($(this).val() !== '') {
        $('#filtroTrabajador').val('').trigger('change.select2');
        if (nominasTable) aplicarFiltros();
    } else {
        if (nominasTable) aplicarFiltros();
    }
});

$('#filtroTrabajador').on('change', function() {
    if ($(this).val() !== '') {
        $('#filtroArea').val('');
        $('#filtroCentroCosto').val('');
        $('#filtroCuenta').val('');
        $('#filtroAcumulaVacaciones').val('');
        $('#filtroRangoVacaciones').val('');
        
        $('#filtroRangoVacaciones').prop('disabled', false);
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', false);
        
        if (nominasTable) aplicarFiltros();
    } else {
        if (nominasTable) aplicarFiltros();
    }
});

function limpiarFiltrosModal(modalId, filtroActivo) {
    var areaId = '#filter' + modalId + 'Area';
    var ccId = '#filter' + modalId + 'CC';
    var searchId = '#search' + modalId + 'Worker';
    
    if (filtroActivo === 'area') {
        $(ccId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'cc') {
        $(areaId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'search') {
        $(areaId).val('');
        $(ccId).val('');
    }
    
    var term = $(searchId).val();
    if (modalId === 'Extra') renderExtraWorkerList(term);
    else if (modalId === 'Vac') renderWorkerList(term);
    else if (modalId === 'Bono') renderBonoWorkerList(term);
    else if (modalId === 'Ajuste') renderAjusteWorkerList(term);
}

$('#filterExtraArea').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Extra', 'area');
    }
});
$('#filterExtraCC').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Extra', 'cc');
    }
});
$('#searchExtraWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        limpiarFiltrosModal('Extra', 'search');
    } else {
        renderExtraWorkerList('');
    }
});

$('#filterVacArea').on('change', function() {
    if ($(this).val() !== '') {
        $('#filterVacCC').val('');
        $('#searchWorker').val('');
        renderWorkerList('');
    }
});

$('#filterVacCC').on('change', function() {
    if ($(this).val() !== '') {
        $('#filterVacArea').val('');
        $('#searchWorker').val('');
        renderWorkerList('');
    }
});

$('#searchWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        $('#filterVacArea').val('');
        $('#filterVacCC').val('');
    }
    renderWorkerList(term);
});

$('#filterBonoArea').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Bono', 'area');
    }
});
$('#filterBonoCC').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Bono', 'cc');
    }
});
$('#searchBonoWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        limpiarFiltrosModal('Bono', 'search');
    } else {
        renderBonoWorkerList('');
    }
});



// Reemplazar la función cargarModalEdicion completa
function cargarModalEdicion($row) {
    console.log('=== CARGANDO MODAL DE EDICIÓN ===');
    console.log('Fila recibida:', $row);
    
    // Ocultar mensaje de contabilizada por defecto
    $('#modalContabilizadaWarning').hide();
    
    // Obtener el ID de forma robusta
    var id = $row.attr('data-id') || $row.data('id');
    if (!id) {
        console.error('No se pudo obtener el ID de la fila');
        return;
    }
    console.log('ID del trabajador:', id);
    
    // Limpiar el cuerpo del modal antes de cargar nuevos datos
    $('#modalEdicionBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando datos...</div>');
    
    // [RESOLUCIÓN ULTRA-REFORZADA PARA COLUMNAS FIJAS]
    // Filtramos para ignorar cualquier fila que pertenezca a los clones de FixedColumns
    var $realRow = $('#tablaNominas tbody tr[data-id="' + id + '"]').filter(function() {
        return $(this).closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length === 0;
    }).first();
    
    if ($realRow.length > 0) {
        $row = $realRow;
    }
    
    // Verificar que la fila tenga datos válidos
    if ($row.length === 0) {
        console.error('No se encontró la fila para el ID:', id);
        $('#modalEdicionBody').html('<div class="alert alert-danger">Error: No se encontraron datos para este trabajador</div>');
        return;
    }

    var actual = (window.editCurrentRowIndex !== undefined && window.editCurrentRowIndex !== null) ? (window.editCurrentRowIndex + 1) : 1;
    var total = (window.editVisibleRowsIds !== undefined && window.editVisibleRowsIds.length > 0) ? window.editVisibleRowsIds.length : 1;
    
    $('#modalRegistroContador').html('<i class="fas fa-list-ol me-1"></i> Registro: ' + actual + ' de ' + total);

    var tipo = tipoNomina;
    var trabajadorId = $row.data('trabajador-id');
    var nombre = $row.find('td:eq(2)').text().trim();
    var fotoRuta = $row.data('foto-ruta') || '';
    
    var codigo = $row.data('codigo') || $row.find('td:eq(0)').text().trim() || 'S/D';
    var ci = $row.data('ci') || $row.find('td:eq(1)').text().trim() || '';
    var area = $row.data('area') || $row.find('td:eq(3)').text().trim() || 'S/D';
    var cargo = $row.data('cargo') || $row.find('td:eq(4)').text().trim() || 'S/D';
    var centroCosto = $row.data('centro-costo') || $row.find('td:eq(5)').text().trim() || 'S/D';
    var escalaRomana = $row.data('escala-romana') || 'S/D';
    var salarioMensual = parseFloat($row.data('salario-mensual')) || 0;
    var salarioHora = parseFloat($row.data('salario-hora')) || 0;
    
    var analisisCI = analizarCubanCI(ci);
    var edadLabel = analisisCI.edad !== 'S/D' ? analisisCI.edad + ' años' : 'S/D';
    var sexoLabel = analisisCI.sexo;
    var fechaNacLabel = analisisCI.fechaNac;

    // Construir la URL de la foto
    var fotoUrl = '';
    if (fotoRuta && fotoRuta.trim() !== '') {
        if (fotoRuta.indexOf('assets/') === 0 || fotoRuta.indexOf('uploads/') === 0) {
            fotoUrl = '../' + fotoRuta;
        } else if (fotoRuta.startsWith('data:image')) {
            fotoUrl = fotoRuta;
        } else {
            fotoUrl = '../assets/imagenes/trabajadores/' + fotoRuta;
        }
    } else {
        fotoUrl = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23f59e0b%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';
    }
    
    var originalValues = {};
    
    // [FIX NOMINAS MIXTAS] En ajuste puede haber filas contabilizadas y borradores en el mismo período,
    // por eso el estado se detecta POR FILA (badge "Contab.") y no con el flag global de la página.
    var esContabilizada = contabilizada || $row.has('.badge-contabilizado').length > 0;

    var isReadOnlyAttr = esContabilizada ? 'readonly' : '';
    var isDisabledAttr = esContabilizada ? 'disabled' : '';
    
    if (esContabilizada) {
        $('#btnModalActualizar').hide();
        $('#btnModalReset').hide();
        $('#modalContabilizadaWarning').show();
    } else {
        $('#btnModalActualizar').show();
        $('#btnModalReset').show();
        $('#modalContabilizadaWarning').hide();
    }
    
    var html = '<div class="d-flex align-items-center mb-3">';
    if (fotoUrl && !fotoUrl.startsWith('data:image/svg')) {
        html += '<div class="flex-shrink-0">';
        html += '<img src="' + fotoUrl + '" alt="Foto" class="rounded-circle" style="width:3.75rem; height:3.75rem; object-fit: cover; border: 0.125rem solid #60a5fa;" onerror="this.onerror=null; this.src=\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2360a5fa%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E\'; width:3.75rem; height:3.75rem;">';
        html += '</div>';
    } else {
        html += '<div class="flex-shrink-0">';
        html += '<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:3.75rem; height:3.75rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6);">';
        html += '<i class="fas fa-user fa-lg text-white"></i>';
        html += '</div></div>';
    }
    html += '<div class="flex-grow-1 ms-3"><h5 class="mb-0">' + escapeHtml(nombre) + '</h5>';
    var workerObj = trabajadores.find(function(t) { return parseInt(t.id) === parseInt(trabajadorId); });
    if (workerObj && workerObj.cargo) {
        html += '<small class="text-white-50 d-block"><i class="fas fa-briefcase me-1"></i>' + escapeHtml(workerObj.cargo) + '</small>';
    }
    html += '<small class="text-white-50 d-block mt-1" style="font-size:0.75rem; line-height:1.4;">';
    html += '<strong>Edad:</strong> <span class="text-warning">' + edadLabel + '</span> | ';
    html += '<strong>Sexo:</strong> <span class="text-warning">' + sexoLabel + '</span> | ';
    html += '<strong>F. Nac:</strong> <span class="text-warning">' + fechaNacLabel + '</span> | ';
    html += '<strong>No. CI:</strong> <span class="text-warning">' + escapeHtml(ci) + '</span>';
    html += '</small>';
    html += '</div></div>';
    
    html += `
        <div class="row g-2 mb-4">
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height:100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size:0.7rem;"><i class="fas fa-building me-1 text-primary"></i> Datos del Trabajador</small>
                    <span class="text-white small d-block"><strong>Código:</strong> <span class="text-warning">${escapeHtml(codigo)}</span></span>
                    <span class="text-white small d-block mt-1"><strong>CI:</strong> <span class="text-warning">${escapeHtml(ci)}</span></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height:100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size:0.7rem;"><i class="fas fa-briefcase me-1 text-info"></i> Cargo</small>
                    <span class="text-white small d-block"><strong>Cargo:</strong> <span class="text-warning">${escapeHtml(cargo)}</span></span>
                    <span class="text-white small d-block mt-1"><strong>Área:</strong> <span class="text-warning">${escapeHtml(area)}</span></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height:100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size:0.7rem;"><i class="fas fa-chart-pie me-1 text-info"></i> Centro Costo</small>
                    <span class="text-white small d-block"><strong>Centro Costo:</strong> <span class="text-warning">${escapeHtml(centroCosto)}</span></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height:100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size:0.7rem;"><i class="fas fa-layer-group me-1 text-warning"></i> Escala Salarial</small>
                    <span class="text-white small d-block"><strong>Grupo:</strong> <span class="text-warning">${escalaRomana}</span><br><strong>Salario:</strong> <span class="text-warning">$${salarioMensual.toFixed(2)} / $${salarioHora.toFixed(2)}h</span></span>
                </div>
            </div>
        </div>
    `;
    
    html += '<input type="hidden" id="editId" value="' + id + '">';

    // Obtener valores actuales de la fila
    var horas = 0, descuentos = 0, nocturnas = 0, feriados = 0, otrosPagos = 0;
    var totalDevengadoActual = 0, netoActual = 0;
    
    // Extraer valores según el tipo de nómina
    if (tipo === 'automatica' || tipo === 'extraordinaria') {
        // Horas - buscar en diferentes lugares
        var $horasInput = $row.find('.edit-horas');
        if ($horasInput.length) {
            horas = parseNumber($horasInput.val());
        } else {
            horas = parseFloat($row.find('.col-horas').text().replace(/,/g, '')) || 0;
        }
        console.log('Horas obtenidas:', horas);
        
        // Descuentos
        var $descuentosInput = $row.find('.edit-descuentos');
        if ($descuentosInput.length) {
            descuentos = parseNumber($descuentosInput.val());
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace('$', '').replace(/,/g, '')) || 0;
        }
        console.log('Descuentos obtenidos:', descuentos);
        
        totalDevengadoActual = parseFloat($row.find('.total-devengado').text().replace('$', '').replace(/,/g, '')) || 0;
        netoActual = parseFloat($row.find('.neto').text().replace('$', '').replace(/,/g, '')) || 0;
        console.log('Total devengado actual:', totalDevengadoActual);
        console.log('Neto actual:', netoActual);
        
        if (tipo === 'automatica') {
            var $feriadosInput = $row.find('.edit-feriados');
            if ($feriadosInput.length) {
                feriados = parseNumber($feriadosInput.val());
            } else {
                feriados = parseFloat($row.find('.col-feriados-dias').text().replace(/,/g, '')) || 0;
            }
            
            var $otrosPagosInput = $row.find('.edit-otros-pagos');
            if ($otrosPagosInput.length) {
                otrosPagos = parseNumber($otrosPagosInput.val());
            } else {
                otrosPagos = parseFloat($row.find('.col-otros-pagos').text().replace('$', '').replace(/,/g, '')) || 0;
            }
            originalValues = { horas: horas, feriados: feriados, otrosPagos: otrosPagos, descuentos: descuentos };
        } else {
            var noctT = 0, noctD = 0, dt = 0;
            var $noctTInput = $row.find('.edit-noct-temprana');
            var $noctDInput = $row.find('.edit-noct-tardia');
            var $dtInput = $row.find('.edit-doble-turno');
            noctT = $noctTInput.length ? parseNumber($noctTInput.val()) : 0;
            noctD = $noctDInput.length ? parseNumber($noctDInput.val()) : 0;
            dt = $dtInput.length ? parseNumber($dtInput.val()) : 0;
            originalValues = { horas: horas, noctT: noctT, noctD: noctD, dt: dt, descuentos: descuentos };
        }
        
        var disabledAttr = (netoActual <= 0) ? 'disabled' : '';
        
        html += '<div class="row">';
        if (tipo === 'extraordinaria') {
            html += '<div class="col-md-4 mb-3"><label class="form-label"><i class="fas fa-sun me-1 text-warning"></i>HE Diurnas (x' + recargoExtraDiurna + ')</label>';
        } else {
            html += '<div class="col-md-3 mb-3"><label class="form-label"><i class="fas fa-clock me-1 text-info"></i>Horas Laboradas</label>';
        }
        html += '<input type="number" step="0.5" class="form-control edit-field" id="editHoras" value="' + horas.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
        
        if (tipo === 'extraordinaria') {
            html += '<div class="col-md-4 mb-3"><label class="form-label"><i class="fas fa-moon me-1 text-info"></i>Nt 7-23h (x' + recargoExtraNocturna + ')</label>';
            html += '<input type="number" step="0.5" class="form-control edit-field" id="editNoctT" value="' + (originalValues.noctT || 0).toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '<div class="col-md-4 mb-3"><label class="form-label"><i class="fas fa-moon me-1" style="color:#8b5cf6;"></i>Nt 23-7h (x' + recargoExtraNocturna + ')</label>';
            html += '<input type="number" step="0.5" class="form-control edit-field" id="editNoctD" value="' + (originalValues.noctD || 0).toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '</div>';
            
            html += '<div class="row">';
            html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-exchange-alt me-1 text-success"></i>Doble Turno (x' + recargoDobleturno + ')</label>';
            html += '<input type="number" step="0.5" class="form-control edit-field" id="editDT" value="' + (originalValues.dt || 0).toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1"></i>Descuentos</label>';
            html += '<input type="number" step="0.01" class="form-control edit-field" id="editDescuentos" value="' + descuentos.toFixed(2) + '" ' + isReadOnlyAttr + ' ' + disabledAttr + '></div>';
            html += '</div>';
        } else {
            html += '<div class="col-md-3 mb-3"><label class="form-label"><i class="fas fa-calendar-day me-1"></i>Días feriados</label>';
            html += '<input type="number" step="0.5" class="form-control edit-field" id="editFeriados" value="' + feriados.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            
            html += '<div class="col-md-3 mb-3"><label class="form-label"><i class="fas fa-coins me-1"></i>Otros pagos</label>';
            html += '<input type="number" step="0.01" class="form-control edit-field" id="editOtrosPagos" value="' + otrosPagos.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '<div class="col-md-3 mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1"></i>Descuentos</label>';
            html += '<input type="number" step="0.01" class="form-control edit-field" id="editDescuentos" value="' + descuentos.toFixed(2) + '" ' + isReadOnlyAttr + ' ' + disabledAttr + '></div>';
            html += '</div>';

            var pagosMn = $row.data('pagos-mn');
            if (pagosMn && pagosMn.length > 0) {
                html += '<div class="mb-3"><label class="form-label text-white-50"><i class="fas fa-list me-1 text-info"></i>Pagos adicionales aplicados</label>';
                html += '<div class="rounded p-2" style="background: rgba(96,165,250,0.08); border: 0.0625rem solid rgba(96,165,250,0.2);">';
                pagosMn.forEach(function(pm, i) {
                    var tipoLbl = (pm.tipo_calculo === 'porcentaje') ? ' <span class="text-warning">(%)</span>' : '';
                    html += '<div class="d-flex justify-content-between align-items-center py-1 pago-adic-item" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.06);">';
                    html += '<span class="small text-white"><i class="fas fa-circle me-2" style="font-size:0.4rem; color:#60a5fa;"></i>' + escapeHtml(pm.nombre) + tipoLbl + '</span>';
                    html += '<div class="d-flex align-items-center gap-2"><span class="small text-info fw-bold">$' + Number(pm.importe_aplicado || 0).toFixed(2) + '</span>';
                    html += (!esContabilizada) ? '<button type="button" class="btn btn-link btn-sm p-0 text-danger ms-2 btn-toggle-pago-adic" data-importe="' + Number(pm.importe_aplicado || 0).toFixed(2) + '" data-aplicado="1" title="Quitar este pago"><i class="fas fa-times"></i></button>' : '';
                    html += '</div></div>';
                });
                html += '</div>';
                html += '<small class="text-white-50 d-block mt-1" style="font-size:0.7rem;"><i class="fas fa-info-circle me-1 text-info"></i>Importes calculados y persistidos en la generación; se reflejan en "Otros pagos".</small></div>';
            }
        }
        
        html += '<div class="card mt-3 text-warning" style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05);">';
        html += '<div class="card-body">';
        html += '<h6 class="card-title text-success" style="font-size:0.85rem;"><i class="fas fa-chart-line me-1"></i>Previsualización en tiempo real</h6>';
        html += '<div class="row text-center mt-2">';
        html += '<div class="col-4"><small class="text-white-50">Total Devengado</small><h5 id="previewDevengado" class="text-info mt-1">$' + totalDevengadoActual.toFixed(2) + '</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Deducciones</small><h5 id="previewDeducciones" class="text-warning mt-1">$0.00</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$' + netoActual.toFixed(2) + '</h5></div>';
        html += '</div></div></div>';
        
    } else if (tipo === 'bono') {
        var montoValido = 0;
        var $bonoInput = $row.find('.edit-bono');
        if ($bonoInput.length) {
            montoValido = parseNumber($bonoInput.val());
        } else {
            montoValido = parseFloat($row.find('.bono-val-cell').text().replace('$', '').replace(/,/g, '')) || 0;
        }
        
        var $descuentosInput = $row.find('.edit-descuentos');
        if ($descuentosInput.length) {
            descuentos = parseNumber($descuentosInput.val()); 
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace('$', '').replace(/,/g, '')) || 0;
        }
        
        // CORRECCIÓN CLAVE: Buscar la segunda columna .col-nombre (Concepto), no la primera (Nombre del Trabajador)
        var descripcionBono = '';
        var $colsNombre = $row.find('.col-nombre');
        if ($colsNombre.length > 1) {
            descripcionBono = $colsNombre.eq(1).text().trim();
        } else {
            // Alternativa en caso de que la estructura de la tabla varíe
            descripcionBono = $row.find('td').eq(7).text().trim();
        }
        if (descripcionBono === '-') descripcionBono = '';
        
        originalValues = { monto: montoValido, descuentos: descuentos, descripcion: descripcionBono };
        
        html += `
            <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 0.0625rem solid rgba(255,255,255,0.08);">
                <label class="form-label d-block mb-2"><i class="fas fa-calculator me-1 text-warning"></i> Método de Cálculo del Bono</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="editTipoBonoRadio" id="editTipoBonoPorciento" value="porciento" ${isDisabledAttr}>
                    <label class="form-check-label small text-white" for="editTipoBonoPorciento">Porcentaje (%) del Salario Básico</label>
                </div>
                <div class="form-check form-check-inline ms-3">
                    <input class="form-check-input" type="radio" name="editTipoBonoRadio" id="editTipoBonoFijo" value="fijo" checked ${isDisabledAttr}>
                    <label class="form-check-label small text-white" for="editTipoBonoFijo">Monto Fijo ($)</label>
                </div>
            </div>
        `;
        
        html += '<div class="row">';
        html += `
            <div class="col-md-4 mb-3" id="wrapperEditPorciento" style="display: none;">
                <label class="form-label"><i class="fas fa-percent me-1 text-info"></i>Porcentaje del Bono</label>
                <div class="input-group">
                    <input type="number" step="0.1" min="0" class="form-control edit-field" id="editPorcientoBono" value="0" ${isReadOnlyAttr}>
                    <span class="input-group-text bg-dark text-info">%</span>
                </div>
            </div>
            <div class="col-md-4 mb-3" id="wrapperEditCoeficiente" style="display: none;">
                <label class="form-label"><i class="fas fa-calculator me-1 text-warning"></i>Coeficiente</label>
                <input type="number" step="0.01" min="0" class="form-control edit-field" id="editCoeficienteBono" value="0" ${isReadOnlyAttr}>
            </div>
            <div class="col-md-4 mb-3" id="wrapperEditMonto">
                <label class="form-label"><i class="fas fa-dollar-sign me-1 text-success"></i>Monto del Bono</label>
                <input type="number" step="0.01" min="0" class="form-control edit-field" id="editMontoBono" value="${montoValido.toFixed(2)}" ${isReadOnlyAttr}>
                <small class="text-white-50">Monto directo a devengar</small>
            </div>
            <div class="col-md-4 mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1 text-warning"></i>Otros Descuentos</label>
            <input type="number" step="0.01" class="form-control edit-field" id="editDescuentosBono" value="${descuentos.toFixed(2)}" ${isReadOnlyAttr}></div>            <div class="col-md-12 mb-3">
                <label class="form-label"><i class="fas fa-pen me-1"></i>Concepto del Bono</label>
                <input type="text" class="form-control edit-field" id="editDescripcionBono" value="${escapeHtml(descripcionBono)}" ${isReadOnlyAttr} placeholder="Ej: Productividad...">
            </div>
        `;
        html += '</div>';
        
        html += '<div class="card mt-3 text-success" style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05);"><div class="card-body"><h6 class="card-title" style="font-size:0.85rem;"><i class="fas fa-chart-line me-1"></i>Previsualización en tiempo real</h6>';
        html += '<div class="row text-center mt-2"><div class="col-4"><small class="text-white-50">Total Devengado</small><h5 id="previewDevengado" class="text-info mt-1">$' + montoValido.toFixed(2) + '</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Deducciones (CESS+Dsctos)</small><h5 id="previewDeducciones" class="text-warning mt-1">$0.00</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$' + montoValido.toFixed(2) + '</h5></div></div></div></div>';
        
    } else if (tipo === 'vacaciones') {
        var dias = 0;
        var $diasInput = $row.find('.edit-dias');
        if ($diasInput.length) {
            dias = parseNumber($diasInput.val());
        } else {
            dias = parseFloat($row.find('.col-horas').first().text().replace(/,/g, '')) || 0;
        }
        var diasAcumulados = $row.data('dias-acumulados') || 0;
        var $descInput = $row.find('.edit-descuentos');
        var descuentos = 0;
        if ($descInput.length) {
            descuentos = parseNumber($descInput.val());
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace(/[^\d.-]/g, '')) || 0;
        }
        originalValues = { dias: dias, descuentos: descuentos };
        html += '<div class="row">';
        html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-umbrella-beach me-1"></i>Días a tomar</label>';
        html += '<input type="number" step="0.5" class="form-control edit-field" id="editDias" value="' + dias.toFixed(2) + '" max="' + diasAcumulados + '" ' + isReadOnlyAttr + '>';
        html += '<small class="text-info d-block mt-1">Días acumulados disponibles: <span id="disponiblesDisplay">' + diasAcumulados + '</span></small></div>';
        html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1 text-danger"></i>Retenciones (Descuentos)</label>';
        html += '<input type="number" step="0.01" min="0" class="form-control edit-field" id="editDescuentos" value="' + descuentos.toFixed(2) + '" ' + isReadOnlyAttr + '>';
        html += '<small class="text-white-50">Se restan del neto a pagar (además de CESS e impuesto)</small></div>';
        html += '</div>';
        html += '<div class="card mt-3 bg-dark text-success" style="border-color: rgba(255,255,255,0.05);"><div class="card-body"><h6 class="card-title" style="font-size:0.85rem;">Previsualización</h6>';
        html += '<div class="row text-center mt-2"><div class="col-6"><small class="text-white-50">Importe vacaciones</small><h5 id="previewDevengado" class="text-info mt-1">$0.00</h5></div>';
        html += '<div class="col-6"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$0.00</h5></div></div></div></div>';
        
    } else if (tipo === 'ajuste') {
        var $montoInput = $row.find('.edit-bono');
        var montoVal = $montoInput.length ? parseNumber($montoInput.val()) : parseCell($row.find('.bono-val-cell'));
        var descripcion = $row.find('.col-nombre').eq(1).text().trim(); // Segunda columna de nombre es el concepto
        if (descripcion === '-') descripcion = '';
        var $descInput = $row.find('.edit-descuentos');
        var descuentos = 0;
        if ($descInput.length) {
            descuentos = parseNumber($descInput.val());
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace(/[^\d.-]/g, '')) || 0;
        }
        var $otrosPagosInput = $row.find('.edit-otros-pagos');
        var otrosPagosVal = $otrosPagosInput.length ? parseNumber($otrosPagosInput.val()) : parseCell($row.find('.col-otros-pagos'));
        
        var horasAjuste = parseFloat($row.data('horas-ajuste') || 0);
        var esModoHorasAjuste = horasAjuste > 0;
        
        originalValues = { monto: montoVal, otrosPagos: otrosPagosVal, descuentos: descuentos, descripcion: descripcion, horas: horasAjuste };

        html += `
            <div class="row">
                ${esModoHorasAjuste ? `
                <div class="col-md-12 mb-3">
                    <label class="form-label"><i class="fas fa-clock me-1 text-info"></i>Horas Trabajadas</label>
                    <input type="number" step="0.01" min="0" class="form-control edit-field" id="editHorasAjuste" value="${horasAjuste.toFixed(2)}" ${isReadOnlyAttr}>
                    <small class="text-white-50">Pago por horas: acumula vacaciones (9.09%) y aplica CESS. El monto se calcula automáticamente.</small>
                </div>
                ` : ''}
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-dollar-sign me-1 text-success"></i>Monto del Ajuste</label>
                    <input type="number" step="0.01" class="form-control edit-field" id="editMontoAjuste" value="${montoVal.toFixed(2)}" ${isReadOnlyAttr} ${esModoHorasAjuste ? 'readonly' : ''}>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-coins me-1 text-info"></i>Otros Pagos</label>
                    <input type="number" step="0.01" class="form-control edit-field" id="editOtrosPagos" value="${otrosPagosVal.toFixed(2)}" ${isReadOnlyAttr}>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-minus-circle me-1 text-danger"></i>Otras Retenciones</label>
                    <input type="number" step="0.01" class="form-control edit-field" id="editDescuentos" value="${descuentos.toFixed(2)}" ${isReadOnlyAttr}>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label"><i class="fas fa-pen me-1"></i>Concepto / Motivo</label>
                    <input type="text" class="form-control edit-field" id="editConceptoAjuste" value="${escapeHtml(descripcion)}" ${isReadOnlyAttr}>
                </div>
            </div>
            <div class="card mt-3 text-warning" style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05);">
                <div class="card-body">
                    <h6 class="card-title text-success" style="font-size:0.85rem;"><i class="fas fa-chart-line me-1"></i>Previsualización en tiempo real</h6>
                    <div class="row text-center mt-2">
                        <div class="col-4"><small class="text-white-50">Total Devengado</small><h5 id="previewDevengado" class="text-info mt-1">$${montoVal.toFixed(2)}</h5></div>
                        <div class="col-4"><small class="text-white-50">Deducciones</small><h5 id="previewDeducciones" class="text-warning mt-1">$0.00</h5></div>
                        <div class="col-4"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$0.00</h5></div>
                    </div>
                </div>
            </div>
        `;
    }
    
    $('#modalEdicionBody').html(html);
    $('#modalEdicionRapida').data('originalValues', originalValues);
    console.log('Valores originales guardados en modal:', originalValues);
    
    // Re-inicializar los eventos de previsualización
    if (tipo === 'automatica') {
        $('#editHoras, #editFeriados, #editOtrosPagos, #editDescuentos').off('input').on('input', function() {
            recalcularPreviewAuto();
        });
        // Pago adicional aplicado: toggle (x) quitar / (+) volver a incluir.
        // Solo rebaja o restaura el importe en "Otros pagos" de esta nómina; la fila en la tabla se
        // conserva para futuros pagos (no se elimina del módulo).
        $('#modalEdicionBody').off('click', '.btn-toggle-pago-adic').on('click', '.btn-toggle-pago-adic', function() {
            var $btn = $(this);
            var importe = parseFloat($btn.data('importe')) || 0;
            var aplicado = ($btn.data('aplicado') === 1);
            var $editOtros = $('#editOtrosPagos');
            var actual = parseFloat($editOtros.val()) || 0;
            var nuevo;
            if (aplicado) {
                // Quitar: resta el importe y cambia el botón a "+"
                nuevo = Math.max(0, actual - importe);
                $btn.data('aplicado', 0)
                    .html('<i class="fas fa-plus"></i>')
                    .attr('title', 'Volver a incluir este pago')
                    .addClass('text-success').removeClass('text-danger');
                $btn.closest('.d-flex').css('opacity', '0.55');
            } else {
                // Volver a incluir: suma el importe y cambia el botón a "x"
                nuevo = actual + importe;
                $btn.data('aplicado', 1)
                    .html('<i class="fas fa-times"></i>')
                    .attr('title', 'Quitar este pago')
                    .addClass('text-danger').removeClass('text-success');
                $btn.closest('.d-flex').css('opacity', '1');
            }
            $editOtros.val(nuevo.toFixed(2)).trigger('input');
        });
        recalcularPreviewAuto();
    } else if (tipo === 'extraordinaria') {
        $('#editHoras, #editNoctT, #editNoctD, #editDT, #editDescuentos').off('input').on('input', function() {
            recalcularPreviewAutoExtraordinaria();
        });
        recalcularPreviewAutoExtraordinaria();
    } else if (tipo === 'bono') {
        $('input[name="editTipoBonoRadio"]').off('change').on('change', function() {
            var metodo = $(this).val();
            if (metodo === 'porciento') {
                $('#wrapperEditPorciento').show();
                $('#wrapperEditCoeficiente').show();
                $('#wrapperEditMonto').hide();
            } else {
                $('#wrapperEditPorciento').hide();
                $('#wrapperEditCoeficiente').hide();
                $('#wrapperEditMonto').show();
            }
            recalcularPreviewBono();
        });
        
        $('#editPorcientoBono, #editCoeficienteBono, #editMontoBono, #editDescuentosBono, #editDescripcionBono').off('input').on('input', function() {
            recalcularPreviewBono();
        });
        recalcularPreviewBono();
    } else if (tipo === 'vacaciones') {
        $('#editDias, #editDescuentos').off('input').on('input', function() {
            recalcularPreviewVacaciones();
        });
        recalcularPreviewVacaciones();
    } else if (tipo === 'ajuste') {
        $('#editMontoAjuste, #editConceptoAjuste, #editDescuentos, #editHorasAjuste, #editOtrosPagos').on('input', recalcularPreviewAjuste);
        recalcularPreviewAjuste();
    }
}



// ==========================================
// BUSCADOR EN MODAL DE EDICIÓN (CORREGIDO)
// ==========================================

// Variable global para almacenar la referencia a cargarModalEdicion
window.cargarModalEdicionRef = null;

// Función para buscar trabajadores usando la API de DataTables correctamente
function buscarTrabajadoresEnTabla(termino) {
    if (!termino || termino.length < 2) {
        $('#resultadosBusquedaModal').hide();
        return [];
    }
    
    var terminoLower = termino.toLowerCase();
    var resultados = [];
    
    var table = $('#tablaNominas').DataTable();
    var allRows = table.rows({ search: 'applied' }).nodes();
    
    for (var i = 0; i < allRows.length; i++) {
        var $row = $(allRows[i]);
        
        if ($row.closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length > 0) {
            continue;
        }
        
        var $codigo = $row.find('td:eq(0)');
        var $ci = $row.find('td:eq(1)');
        var $nombre = $row.find('td:eq(2)');
        
        if ($codigo.length === 0) continue;
        
        var codigo = $codigo.text().trim().toLowerCase();
        var ci = $ci.text().trim().toLowerCase();
        var nombre = $nombre.text().trim().toLowerCase();
        var id = $row.data('id');
        
        if (!id) continue;
        
        if (nombre.includes(terminoLower) || codigo.includes(terminoLower) || ci.includes(terminoLower)) {
            resultados.push({
                id: id,
                nombre: $nombre.text().trim(),
                codigo: $codigo.text().trim(),
                ci: $ci.text().trim(),
                cargo: $row.data('cargo') || '',
                area: $row.data('area') || '',
                rowIndex: i
            });
        }
    }
    
    return resultados;
}

// Obtener la fila real del DataTable
function obtenerFilaReal(trabajadorId) {
    var table = $('#tablaNominas').DataTable();
    var rows = table.rows().nodes();
    
    for (var i = 0; i < rows.length; i++) {
        var $row = $(rows[i]);
        if ($row.closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length > 0) {
            continue;
        }
        if ($row.data('id') == trabajadorId) {
            return { $row: $row, index: i };
        }
    }
    return null;
}

// Navegar a un trabajador específico por ID
function navegarATrabajadorPorId(trabajadorId) {
    console.log('Navegando a trabajador ID:', trabajadorId);
    
    $('#modalEdicionBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando datos del trabajador...</div>');
    
    var table = $('#tablaNominas').DataTable();
    var resultado = obtenerFilaReal(trabajadorId);
    
    if (!resultado) {
        Swal.fire({
            title: 'Trabajador no encontrado',
            text: 'No se pudo encontrar el trabajador en la nómina actual.',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    var idx = resultado.index;
    var $row = resultado.$row;
    var pageLength = table.page.len();
    var currentPage = table.page();
    var targetPage = Math.floor(idx / pageLength);
    
    var actualizarModal = function() {
        var $filaActual = $('#tablaNominas').find('tr[data-id="' + trabajadorId + '"]').filter(function() {
            return $(this).closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length === 0;
        }).first();
        
        if ($filaActual.length === 0) {
            console.error('No se encontró la fila para actualizar');
            $('#modalEdicionBody').html('<div class="alert alert-danger">Error: No se encontraron datos para este trabajador</div>');
            return;
        }
        
        var allVisibleRows = table.rows({ search: 'applied' }).nodes();
        var visibleIds = [];
        var newPos = -1;
        
        for (var i = 0; i < allVisibleRows.length; i++) {
            var $visibleRow = $(allVisibleRows[i]);
            if ($visibleRow.closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length > 0) {
                continue;
            }
            var rowId = $visibleRow.data('id');
            if (rowId) {
                visibleIds.push(rowId);
                if (rowId == trabajadorId) {
                    newPos = visibleIds.length - 1;
                }
            }
        }
        
        window.editCurrentRowId = trabajadorId;
        window.editCurrentRowIndex = newPos;
        window.editVisibleRowsIds = visibleIds;
        
        var actual = newPos + 1;
        var total = visibleIds.length;
        $('#modalRegistroContador').html('<i class="fas fa-list-ol me-1"></i> Registro: ' + actual + ' de ' + total);
        
        // Usar la función global si está disponible
        if (typeof window.cargarModalEdicionRef === 'function') {
            window.cargarModalEdicionRef($filaActual);
        } else if (typeof cargarModalEdicion === 'function') {
            cargarModalEdicion($filaActual);
        } else {
            console.error('cargarModalEdicion no está definida');
            // Fallback: recargar la página
            location.reload();
        }
    };
    
    if (currentPage !== targetPage) {
        table.page(targetPage).draw('page');
        setTimeout(actualizarModal, 400);
    } else {
        actualizarModal();
    }
}

// Renderizar resultados de búsqueda
function renderizarResultadosBusqueda(resultados, termino) {
    var $container = $('#resultadosBusquedaModal');
    
    if (resultados.length === 0) {
        $container.html('<div class="p-3 text-center text-white-50"><i class="fas fa-search me-2"></i>No se encontraron resultados para "' + escapeHtml(termino) + '"</div>');
        $container.show();
        return;
    }
    
    var html = '';
    resultados.forEach(function(r, idx) {
        html += `
            <div class="resultado-item" data-id="${r.id}" data-index="${idx}">
                <div class="resultado-nombre">
                    <i class="fas fa-user me-2" style="color: #60a5fa;"></i>
                    ${escapeHtml(r.codigo)} - ${escapeHtml(r.nombre)}
                </div>
                <div class="resultado-detalle">
                    <i class="fas fa-id-card"></i> CI: ${escapeHtml(r.ci)}
                    ${r.cargo ? '<i class="fas fa-briefcase ms-2"></i> ' + escapeHtml(r.cargo) : ''}
                    ${r.area ? '<i class="fas fa-building ms-2"></i> ' + escapeHtml(r.area) : ''}
                </div>
            </div>
        `;
    });
    
    $container.html(html);
    $container.show();
}

function actualizarSeleccionResultados(items) {
    items.removeClass('active');
    if (indiceSeleccionadoModal >= 0 && indiceSeleccionadoModal < items.length) {
        $(items[indiceSeleccionadoModal]).addClass('active');
        $(items[indiceSeleccionadoModal])[0].scrollIntoView({ block: 'nearest' });
    }
}

// Inicializar eventos del buscador (esto debe ir dentro del document.ready)
$(document).ready(function() {
	
// Manejador para agregar personas directamente en Nómina de Ajuste
$(document).on('click', '#btnAgregarPersonasAjusteDirecto', function() {
    // 1. Detectar el tipo de descuento que ya tiene la nómina en pantalla
    // Buscamos en la primera fila de la tabla el atributo data-tipo-descuento
    let tipoDescuentoExistente = $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    
    // 2. Asignar ese descuento a la variable temporal para que el modal lo use
    window.tempSelectedDiscount = tipoDescuentoExistente;
    window.ajusteModoDetectado = true;
    
    // 3. Abrir el modal de selección de tipo de ajuste (directo/horas)
    var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSeleccionTipoAjuste'));
    modalObj.show();
});

// Ajuste adicional: Asegurarse de que el modal de Ajuste se limpie correctamente al abrirse
$('#modalAjuste').on('show.bs.modal', function() {
    // Si venimos por el camino directo, la lista de seleccionados debe resetearse
    // pero el tipo de descuento debe tomarse de lo que detectamos arriba
    if (window.tempSelectedDiscount) {
        $('#tipoDescuentoAjuste').val(window.tempSelectedDiscount);
    }
});

    // Guardar referencia a cargarModalEdicion si existe
    if (typeof cargarModalEdicion === 'function') {
        window.cargarModalEdicionRef = cargarModalEdicion;
    }
    
    if ($('#buscadorTrabajadorModal').length === 0) {
        console.log('Elemento buscadorTrabajadorModal no encontrado');
        return;
    }
    
    var timeoutBusquedaModal = null;
    var resultadosActivosModal = [];
    var indiceSeleccionadoModal = -1;
    
    $('#buscadorTrabajadorModal').off('input').on('input', function() {
        var termino = $(this).val().trim();
        clearTimeout(timeoutBusquedaModal);
        
        if (termino.length < 2) {
            $('#resultadosBusquedaModal').hide();
            return;
        }
        
        timeoutBusquedaModal = setTimeout(function() {
            var resultados = buscarTrabajadoresEnTabla(termino);
            resultadosActivosModal = resultados;
            indiceSeleccionadoModal = -1;
            renderizarResultadosBusqueda(resultados, termino);
        }, 300);
    });

    $('#buscadorTrabajadorModal').off('keydown').on('keydown', function(e) {
        if (!$('#resultadosBusquedaModal').is(':visible')) return;
        
        var items = $('.resultado-item');
        var totalItems = items.length;
        if (totalItems === 0) return;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                indiceSeleccionadoModal = Math.min(indiceSeleccionadoModal + 1, totalItems - 1);
                actualizarSeleccionResultados(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                indiceSeleccionadoModal = Math.max(indiceSeleccionadoModal - 1, 0);
                actualizarSeleccionResultados(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (indiceSeleccionadoModal >= 0 && resultadosActivosModal[indiceSeleccionadoModal]) {
                    var trabajadorId = resultadosActivosModal[indiceSeleccionadoModal].id;
                    $('#resultadosBusquedaModal').hide();
                    $('#buscadorTrabajadorModal').val('');
                    navegarATrabajadorPorId(trabajadorId);
                }
                break;
            case 'Escape':
                $('#resultadosBusquedaModal').hide();
                $('#buscadorTrabajadorModal').val('');
                break;
        }
    });

    $(document).off('click', '.resultado-item').on('click', '.resultado-item', function() {
        var id = $(this).data('id');
        $('#resultadosBusquedaModal').hide();
        $('#buscadorTrabajadorModal').val('');
        navegarATrabajadorPorId(id);
    });

    $('#limpiarBuscadorModal').off('click').on('click', function() {
        $('#buscadorTrabajadorModal').val('');
        $('#resultadosBusquedaModal').hide();
    });

    $(document).off('click.buscadorModal').on('click.buscadorModal', function(e) {
        if (!$(e.target).closest('#buscadorTrabajadorModal, #resultadosBusquedaModal, .resultado-item').length) {
            $('#resultadosBusquedaModal').hide();
        }
    });

    $('#modalEdicionRapida').off('hidden.bs.modal').on('hidden.bs.modal', function() {
        $('#buscadorTrabajadorModal').val('');
        $('#resultadosBusquedaModal').hide();
    });
    
    $('#modalEdicionRapida').off('shown.bs.modal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#buscadorTrabajadorModal').focus();
        }, 200);
    });
	
// ==========================================
// RESPONSIVE: Ajustar DataTable al rotar pantalla
// ==========================================
var ultimoAncho = window.innerWidth;
var resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        var anchoActual = window.innerWidth;
        var cambioSignificativo = Math.abs(anchoActual - ultimoAncho) > 100;
        var cambioOrientacion = (ultimoAncho < 768 && anchoActual >= 768) ||
                                (ultimoAncho >= 768 && anchoActual < 768);
        
        if (cambioSignificativo || cambioOrientacion) {
            ultimoAncho = anchoActual;
            if (nominasTable) {
                nominasTable.columns.adjust();
                if (typeof nominasTable.responsive !== 'undefined') {
                    nominasTable.responsive.recalc();
                }
            }
            // Ajustar DataTables FixedColumns
            if (nominasTable && typeof nominasTable.fixedColumns === 'function') {
                nominasTable.fixedColumns().update();
            }
        }
    }, 250);
});
});


// ==========================================
// HELPER: VALIDAR DESCUENTOS vs CESS (todas las nóminas)
// Si descuentos + impuesto >= totalDevengado, no se puede descontar la
// totalidad porque hay que aportar al Estado la CESS. Se ajusta al máximo.
// ==========================================
var _lastDescuentosAlertTime = 0;
function validarDescuentosConCESS(totalDevengado, contribucion, impuesto, descuentos) {
    var netoAntesDesc = totalDevengado - contribucion - impuesto;
    if (netoAntesDesc < 0) netoAntesDesc = 0;
    var maxDescuentos = Math.roundExcel(netoAntesDesc, 2);

    if (descuentos > maxDescuentos && totalDevengado > 0) {
        var ahora = Date.now();
        if (ahora - _lastDescuentosAlertTime > 3000) {
            _lastDescuentosAlertTime = ahora;
            Swal.fire({
                icon: 'warning',
                title: '<i class="fas fa-shield-alt me-2" style="color:#f59e0b;"></i>Descuento ajustado',
                html: 'Los <b>descuentos</b> no pueden igualar o superar el Total Devengado.<br><br>' +
                      '<small style="color:#94a3b8;">Se requiere aportar al Estado la <b>Contribución Especial de la Seguridad Social (CESS)</b>.<br>' +
                      'Descuento máximo permitido: <b style="color:#22c55e;">$' + maxDescuentos.toFixed(2) + '</b></small>',
                timer: 4500,
                timerProgressBar: true,
                showConfirmButton: false,
                background: '#1e293b',
                color: '#ffffff',
                position: 'center',
                toast: false
            });
        }
        return maxDescuentos;
    }
    return descuentos;
}


function recalcularPreviewAuto() {
    var $filaOriginal = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]');
    var salarioHora = parseFloat($filaOriginal.data('salario-hora')) || 0;
    var salarioMensual = parseFloat($filaOriginal.data('salario-mensual')) || 0;
    var tipoDescuento = $filaOriginal.data('tipo-descuento') || 'total_rangos';
    var noAcumularVacaciones = parseInt($filaOriginal.data('no-acumular-vacaciones')) || 0;
    
    var horas = parseFloat($('#editHoras').val()) || 0;
    var feriados = parseFloat($('#editFeriados').val()) || 0;
    var otrosPagos = parseFloat($('#editOtrosPagos').val()) || 0;
    var descuentos = parseFloat($('#editDescuentos').val()) || 0;
    
    var factor909 = 0.0909;
    var horasJornada = 8;

    var salarioLaboral = Math.roundExcel(salarioHora * horas, 2);
    var salarioDiario = salarioMensual / 24;
    var importeFeriados = Math.roundExcel(salarioDiario * feriados * 2, 2);
    
    // =========================================================
    // CÁLCULO PROPORCIONAL VACACIONES PARA EL PREVIEW DEL MODAL
    // =========================================================
    var diasVacMes = Math.roundExcel(((horas * factor909) / horasJornada), 2);
    var importeVacMes = Math.roundExcel(diasVacMes * salarioDiario, 2);
    
    var importeVacacionesAdicional = 0;
    if (noAcumularVacaciones === 1) {
        importeVacacionesAdicional = importeVacMes;
    }
    
    var totalDevengado = Math.roundExcel(salarioLaboral + importeFeriados + otrosPagos + importeVacacionesAdicional, 2);
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(totalDevengado);
        impuesto = 0;
    } else {
        contribucion = Math.roundExcel(totalDevengado * 0.05, 2);
        impuesto = calcularImpuestoProgresivo(totalDevengado);
    }
    
    descuentos = validarDescuentosConCESS(totalDevengado, contribucion, impuesto, descuentos);
    $('#editDescuentos').val(descuentos.toFixed(2));
    
    var totalDeducciones = Math.roundExcel(contribucion + impuesto + descuentos, 2);
    var neto = Math.max(0, Math.roundExcel(totalDevengado - totalDeducciones, 2));
    
    $('#previewDevengado').text('$' + totalDevengado.toFixed(2));
    $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
}

    function recalcularPreviewAutoExtraordinaria() {
        var salarioHora = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('salario-hora')) || 0;
        var horas = parseFloat($('#editHoras').val()) || 0;
        var noctT = parseFloat($('#editNoctT').val()) || 0;
        var noctD = parseFloat($('#editNoctD').val()) || 0;
        var dt = parseFloat($('#editDT').val()) || 0;
        var descuentos = parseFloat($('#editDescuentos').val()) || 0;
        var tipoDescuento = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('tipo-descuento') || 'total_rangos';
        
        var importeHE = (salarioHora * recargoExtraDiurna) * horas;
        var importeNtT = (salarioHora * recargoExtraNocturna) * noctT;
        var importeNtD = (salarioHora * recargoExtraNocturna) * noctD;
        var importeDT = (salarioHora * recargoDobleturno) * dt;

        var totalDevengado = importeHE + importeNtT + importeNtD + importeDT;
        
        var contribucion = 0, impuesto = 0;
        if (tipoDescuento === 'solo_cess') {
            contribucion = calcularCessProgresivoJS(totalDevengado);
            impuesto = 0;
        } else {
            contribucion = totalDevengado * 0.05;
            impuesto = calcularImpuestoProgresivo(totalDevengado);
        }

        descuentos = validarDescuentosConCESS(totalDevengado, contribucion, impuesto, descuentos);
        $('#editDescuentos').val(descuentos.toFixed(2));

        var totalDeducciones = contribucion + impuesto + descuentos;
        var neto = Math.max(0, totalDevengado - totalDeducciones);
        
        var $descuentosModal = $('#editDescuentos');
        $descuentosModal.prop('disabled', neto <= 0);
        
        $('#previewDevengado').text('$' + totalDevengado.toFixed(2));
        $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
        $('#previewNeto').text('$' + neto.toFixed(2));
    }

function recalcularPreviewBono() {
    var salarioMensual = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').attr('data-salario-mensual')) || 0;
    var metodo = $('input[name="editTipoBonoRadio"]:checked').val() || 'porciento';
    var monto = 0;

    if (metodo === 'porciento') {
        var porciento = parseFloat($('#editPorcientoBono').val()) || 0;
        var coeficiente = parseFloat($('#editCoeficienteBono').val()) || 0;
        var brutoCalculado = (porciento * coeficiente) - salarioMensual;
        monto = Math.max(0, Math.roundExcel(brutoCalculado, 2));
        $('#editMontoBono').val(monto);
    } else {
        monto = parseFloat($('#editMontoBono').val()) || 0;
    }

    var descuentos = parseFloat($('#editDescuentosBono').val()) || 0;
    var tipoDescuento = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').attr('data-tipo-descuento') || 'total_rangos';
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(monto);
        impuesto = 0;
    } else {
        contribucion = Math.roundExcel(monto * 0.05, 2);
        impuesto = calcularImpuestoProgresivo(monto);
    }

    descuentos = validarDescuentosConCESS(monto, contribucion, impuesto, descuentos);
    $('#editDescuentosBono').val(descuentos.toFixed(2));

    var totalDeducciones = contribucion + impuesto + descuentos;
    var neto = Math.max(0, monto - totalDeducciones);
    
    $('#previewDevengado').text('$' + monto.toFixed(2));
    $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
    
    var $descuentosInput = $('#editDescuentosBono');
    $descuentosInput.prop('disabled', neto <= 0);
}

function recalcularPreviewVacaciones() {
    var dias = parseFloat($('#editDias').val()) || 0;
    var salarioMensual = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('salario-mensual')) || 0;
    var diasAcumulados = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('dias-acumulados')) || 0;
    var tipoDescuento = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('tipo-descuento') || 'total_rangos';
    
    console.log('Recalculando vacaciones - días:', dias, 'días acumulados:', diasAcumulados);
    
    if (dias > diasAcumulados) {
        $('#editDias').val(diasAcumulados);
        dias = diasAcumulados;
        mostrarToast('warning', 'Máximo de días disponibles: ' + diasAcumulados);
    }
    $('#disponiblesDisplay').text(diasAcumulados);
    
    var valorPorDia = salarioMensual / diasLaborables;
    var importe = dias * valorPorDia;
    var diasRestantes = diasAcumulados - dias;
    if (diasRestantes < 0) diasRestantes = 0;
    
    // Actualizar días restantes en tiempo real
    $('#previewDiasRestantes').text(diasRestantes.toFixed(2));
    $('#previewDias').text(dias.toFixed(2));
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(importe);
        impuesto = 0;
    } else {
        contribucion = importe * 0.05;
        impuesto = calcularImpuestoProgresivo(importe);
    }
    var netoBase = importe - (contribucion + impuesto);
    if (netoBase < 0) netoBase = 0;
    var descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
    if (descuentos > netoBase) {
        $('#editDescuentos').val(netoBase.toFixed(2));
        descuentos = netoBase;
    }
    var neto = netoBase - descuentos;
    if (neto < 0) neto = 0;
    $('#previewDevengado').text('$' + importe.toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
}

    function mostrarToast(tipo, mensaje) {
        var toastHtml = '<div class="alert alert-' + tipo + ' alert-dismissible fade show mb-3" role="alert">' + mensaje + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        $('#modalEdicionBody').prepend(toastHtml);
        setTimeout(function() {
            $('.alert').alert('close');
        }, 3000);
    }

    // ==========================================
    // FUNCIÓN CENTRALIZADA PARA ENFOCAR CAMPO ADECUADO
    // ==========================================
    function enfocarCampoEdicion() {
        // Pequeño delay para asegurar que el DOM esté completamente renderizado
        setTimeout(function() {
            try {
                if (tipoNomina === 'bono') {
                    // Para nómina de bonos: enfocar el monto del bono
                    var $montoInput = $('#editMontoBono');
                    if ($montoInput.length && !$montoInput.prop('disabled') && !$montoInput.prop('readonly')) {
                        $montoInput.focus().select();
                        return;
                    }
                    // Fallback: si el monto está deshabilitado, enfocar descuentos
                    var $descuentosInput = $('#editDescuentosBono');
                    if ($descuentosInput.length && !$descuentosInput.prop('disabled')) {
                        $descuentosInput.focus().select();
                        return;
                    }
                } else if (tipoNomina === 'vacaciones') {
                    // Para vacaciones: enfocar días a tomar
                    var $diasInput = $('#editDias');
                    if ($diasInput.length && !$diasInput.prop('disabled') && !$diasInput.prop('readonly')) {
                        $diasInput.focus().select();
                        return;
                    }
                } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                    // Para automática y extraordinaria: enfocar horas laboradas
                    var $horasInput = $('#editHoras');
                    if ($horasInput.length && !$horasInput.prop('disabled') && !$horasInput.prop('readonly')) {
                        $horasInput.focus().select();
                        return;
                    }
                }
                
                // Fallback genérico: primer campo editable visible
                var $firstEditable = $('.edit-field:not([readonly]):not([disabled]):visible').first();
                if ($firstEditable.length) {
                    $firstEditable.focus().select();
                }
            } catch (e) {
                console.warn('Error al enfocar campo:', e);
            }
        }, 150);
    }

    // Enfoque automático al abrir el modal
    $('#modalEdicionRapida').on('shown.bs.modal', function () {
        enfocarCampoEdicion();
    });

    // Sincronizar y actualizar a través de AJAX
    function actualizarFilaDesdeModal(callback) {
        var id = $('#editId').val();
        var $row = $('#tablaNominas').find('tr[data-id="' + id + '"]');
        var datos = { actualizar_nomina: 1, id: id, tipo_nomina: tipoNomina };
        
        var valHoras, valNocturnas, valFeriados, valOtros, valDesc, valBono, valDias;
        
        if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
            valHoras = Math.max(0, parseFloat($('#editHoras').val()) || 0);
            valDesc = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);

            $('#editHoras').val(valHoras);
            $('#editDescuentos').val(valDesc);

            datos.horas_laboradas = valHoras;
            datos.descuentos = valDesc;

            if (tipoNomina === 'automatica') {
                valFeriados = Math.max(0, parseFloat($('#editFeriados').val()) || 0);
                valOtros = Math.max(0, parseFloat($('#editOtrosPagos').val()) || 0);
                
                $('#editFeriados').val(valFeriados);
                $('#editOtrosPagos').val(valOtros);

                datos.dias_feriados = valFeriados;
                datos.otros_salarios = valOtros;
                datos.horas_nocturnas = 0;
            } else {
                valNoctT = Math.max(0, parseFloat($('#editNoctT').val()) || 0);
                valNoctD = Math.max(0, parseFloat($('#editNoctD').val()) || 0);
                valDT = Math.max(0, parseFloat($('#editDT').val()) || 0);
                $('#editNoctT').val(valNoctT);
                $('#editNoctD').val(valNoctD);
                $('#editDT').val(valDT);
                
                datos.nocturnidad_temprana = valNoctT;
                datos.nocturnidad_tardia = valNoctD;
                datos.doble_turno = valDT;
                datos.dias_feriados = 0;
                datos.otros_salarios = 0;
            }
        
        } else if (tipoNomina === 'bono') {
			valBono = Math.max(0, parseFloat($('#editMontoBono').val()) || 0);
			valDesc = Math.max(0, parseFloat($('#editDescuentosBono').val()) || 0);
			var valDescrip = $('#editDescripcionBono').val() || '';  // ← AGREGAR ESTA LÍNEA
			datos.monto_bono = valBono;
			datos.descuentos = valDesc;
			datos.descripcion = valDescrip; 
		} else if (tipoNomina === 'vacaciones') {
            valDias = Math.max(0, parseFloat($('#editDias').val()) || 0);
            $('#editDias').val(valDias);
            datos.dias_vacaciones = valDias;
        } else if (tipoNomina === 'ajuste') {
			var valMonto = Math.max(0, parseFloat($('#editMontoAjuste').val()) || 0);
			var valConcepto = $('#editConceptoAjuste').val() || '';
			datos.monto_bono = valMonto; // Usamos el campo que tu PHP ya procesa
			datos.descripcion = valConcepto;
		}
        $('#btnModalActualizar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            dataType: 'json',
            data: datos,
            success: function(r) {
                if (r.success) {
                    if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                        $row.find('.edit-horas').val(valHoras);
                        $row.find('.edit-descuentos').val(valDesc);
                        
                        if (tipoNomina === 'automatica') {
                            $row.find('.edit-feriados').val(valFeriados);
                            $row.find('.edit-otros-pagos').val(valOtros);
                        } else {
                            $row.find('.edit-noct-temprana').val(valNoctT);
                            $row.find('.edit-noct-tardia').val(valNoctD);
                            $row.find('.edit-doble-turno').val(valDT);
                        }
                        $row.find('.edit-horas').trigger('input');
                        
                    } else if (tipoNomina === 'bono') {
                        $row.find('.edit-bono').val(valBono);
                        $row.find('.edit-descuentos').val(valDesc);
                        
                        // CORRECCIÓN CLAVE: Buscar la columna Concepto correcta para actualizarla
                        var $colsNombre = $row.find('.col-nombre');
                        if ($colsNombre.length > 1) {
                            $colsNombre.eq(1).text(valDescrip || '-');
                        } else {
                            $row.find('td').eq(7).text(valDescrip || '-');
                        }
                        
                        $row.find('.edit-bono').trigger('input');
					} else if (tipoNomina === 'vacaciones') {
                        $row.find('.edit-dias').val(valDias);
                        $row.find('.edit-dias').trigger('input');
                    } else if (tipoNomina === 'ajuste') {
						$row.find('.edit-bono').val(valMonto);
						$row.find('.col-nombre').eq(1).text(valConcepto || '-');
						$row.find('.edit-bono').trigger('input'); // Esto dispara el recálculo de la fila
					}
                    
                    mostrarToast('success', 'Registro guardado correctamente');
                    
                    // ✅ Enfocar el campo correspondiente después de guardar
                    enfocarCampoEdicion();
                    
                    var originalValues = {};
                    if (tipoNomina === 'automatica') {
                        originalValues = { 
                            horas: valHoras.toString(), 
                            feriados: valFeriados.toString(), 
                            otrosPagos: valOtros.toString(), 
                            descuentos: valDesc.toString() 
                        };
                    } else if (tipoNomina === 'extraordinaria') {
                        originalValues = { 
                            horas: valHoras.toString(), 
                            nocturnas: valNocturnas.toString(), 
                            descuentos: valDesc.toString() 
                        };
                    } else if (tipoNomina === 'bono') {
                        originalValues = { 
                            monto: valBono.toString(), 
                            descuentos: valDesc.toString(),
                            descripcion: valDescrip
                        };
                    } else if (tipoNomina === 'vacaciones') {
                        originalValues = { dias: valDias.toString() };
                    } else if (tipoNomina === 'ajuste') {
						$row.find('.edit-bono').val(valMonto); // actualiza input oculto si existe
						$row.find('.col-nombre').eq(1).text(valConcepto || '-'); // actualiza celda concepto
						$row.find('.total-devengado').text('$' + valMonto.toFixed(2));
						// Forzar recálculo visual de la fila
						recalcularFilaBono($row); 
					}
                    $('#modalEdicionRapida').data('originalValues', originalValues);
                    
                    $('#btnModalActualizar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
                    if (callback) callback(true);
                } else {
                    mostrarToast('danger', r.error || 'Error al guardar');
                    $('#btnModalActualizar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
                    if (callback) callback(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', status, error);
                mostrarToast('danger', 'Error de conexión con el servidor');
                $('#btnModalActualizar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
                if (callback) callback(false);
            }
        });
    }

    function navegarARegistro(targetId, targetIdx) {
        var table = $('#tablaNominas').DataTable();
        var pageLength = table.page.len();
        
        if (pageLength > 0) {
            var pageNum = Math.floor(targetIdx / pageLength);
            table.page(pageNum).draw('page');
        }
        
        var $row = $('#tablaNominas').find('tr[data-id="' + targetId + '"]');
        if ($row.length) {
            window.editCurrentRowIndex = targetIdx;
            window.editCurrentRowId = targetId;
            cargarModalEdicion($row);
            
            // ✅ El enfoque se maneja dentro de cargarModalEdicion -> enfocarCampoEdicion()
        } else {
            console.warn("No se pudo localizar el elemento DOM para el ID: " + targetId);
        }
    }

    // Eventos de Navegación del Modal
    $('#btnModalPrimero').on('click', function() {
        if (window.editVisibleRowsIds.length > 0) {
            var firstId = window.editVisibleRowsIds[0];
            navegarARegistro(firstId, 0);
        }
    });

    $('#btnModalAnterior').on('click', function() {
        var idx = window.editCurrentRowIndex;
        if (idx > 0) {
            var prevId = window.editVisibleRowsIds[idx - 1];
            navegarARegistro(prevId, idx - 1);
        } else {
            Swal.fire({
                title: 'Inicio',
                text: 'Se encuentra en el primer registro de la lista filtrada.',
                icon: 'info',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: '#1a1a2e',
                color: '#ffffff'
            });
            // Mantener el foco en el campo actual
            enfocarCampoEdicion();
        }
    });

    $('#btnModalSiguiente').on('click', function() {
        var idx = window.editCurrentRowIndex;
        if (idx < window.editVisibleRowsIds.length - 1) {
            var nextId = window.editVisibleRowsIds[idx + 1];
            navegarARegistro(nextId, idx + 1);
        } else {
            Swal.fire({
                title: 'Final',
                text: 'Se encuentra en el último registro de la lista filtrada.',
                icon: 'info',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: '#1a1a2e',
                color: '#ffffff'
            });
            // Mantener el foco en el campo actual
            enfocarCampoEdicion();
        }
    });

    $('#btnModalUltimo').on('click', function() {
        var lastIdx = window.editVisibleRowsIds.length - 1;
        if (lastIdx >= 0) {
            var lastId = window.editVisibleRowsIds[lastIdx];
            navegarARegistro(lastId, lastIdx);
        }
    });

// ==========================================
// EVENTO COMPLETO DEL BOTÓN ACTUALIZAR EN MODAL DE EDICIÓN
// ==========================================
$('#btnModalActualizar').on('click', function() {
    console.log('=== INICIO CLICK BOTON ACTUALIZAR ===');
    
    // Guardar referencia al botón
    var $btn = $(this);
    var id = $('#editId').val();
    console.log('ID del registro:', id);

    if (!id) {
        mostrarToast('danger', 'Error: ID de registro no encontrado');
        return;
    }

    // --- 1. DECLARAR VARIABLES ---
    var monto = 0;
    var descuentos = 0;
    var descripcion = '';
    var horas = 0;
    var feriados = 0;
    var otrosPagos = 0;
    var nocturnas = 0;
    var dias = 0;
    var nombre = '';

    // --- 2. OBTENER NOMBRE DEL TRABAJADOR ---
    var $filaOriginal = $('#tablaNominas tbody tr[data-id="' + id + '"]');
    if ($filaOriginal.length) {
        nombre = $filaOriginal.find('td:eq(2)').text().trim();
    }

    // --- 3. PREPARAR DATOS SEGÚN TIPO DE NÓMINA ---
    var datos = {
        actualizar_nomina: 1,
        id: id,
        tipo_nomina: tipoNomina
    };

    console.log('Tipo de nómina:', tipoNomina);

    // ==========================================
    // VALIDACIONES Y PREPARACIÓN POR TIPO DE NÓMINA
    // ==========================================
    
    if (tipoNomina === 'bono') {
        console.log('=== PROCESANDO BONO ===');
        monto = Math.max(0, parseFloat($('#editMontoBono').val()) || 0);
        descuentos = Math.max(0, parseFloat($('#editDescuentosBono').val()) || 0);
        descripcion = $('#editDescripcionBono').val() || '';
        
        console.log('Monto capturado:', monto);
        console.log('Descuentos capturados:', descuentos);
        console.log('Descripción capturada:', descripcion);

        // ==========================================
        // ✅ VALIDACIÓN: Monto del Bono en Cero
        // ==========================================
        if (monto === 0 || monto < 0.5) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto del Bono en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-gift fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 monto del bono</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 monto en la nómina de bonos.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar monto',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editMontoBono').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }

        datos.monto_bono = monto;
        datos.descuentos = descuentos;
        datos.descripcion = descripcion;
        
        console.log('Datos a enviar (BONO):', datos);

    } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
        horas = Math.max(0, parseFloat($('#editHoras').val()) || 0);
        descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
        
        // ==========================================
        // ✅ VALIDACIÓN: Horas en Cero
        // ==========================================
        var nombreCampo = getNombreCampo(tipoNomina);
        if (tipoNomina === 'automatica' && (horas === 0 || horas < 0.5)) {
            Swal.fire({
                title: `<i class="fas fa-exclamation-triangle text-warning me-2"></i> ${nombreCampo.charAt(0).toUpperCase() + nombreCampo.slice(1)} en Cero`,
                html: `
                    <div class="text-center">
                        <i class="fas ${getIconoCampo(tipoNomina)} fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 ${nombreCampo}</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 ${nombreCampo} en la nómina.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: `<i class="fas fa-pen me-2"></i>Asignar ${nombreCampo}`,
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editHoras').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }

        datos.horas_laboradas = horas;
        datos.descuentos = descuentos;

        if (tipoNomina === 'automatica') {
            feriados = Math.max(0, parseFloat($('#editFeriados').val()) || 0);
            otrosPagos = Math.max(0, parseFloat($('#editOtrosPagos').val()) || 0);
            datos.dias_feriados = feriados;
            datos.otros_salarios = otrosPagos;
            datos.horas_nocturnas = 0;
        } else {
            var noctT = Math.max(0, parseFloat($('#editNoctT').val()) || 0);
            var noctD = Math.max(0, parseFloat($('#editNoctD').val()) || 0);
            var dt = Math.max(0, parseFloat($('#editDT').val()) || 0);
            
            // ==========================================
            // ✅ VALIDACIÓN EXTRAORDINARIA: TODO EN CERO
            // ==========================================
            if (horas === 0 && noctT === 0 && noctD === 0 && dt === 0 && descuentos === 0) {
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Nómina Extraordinaria en Cero',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-clock fa-3x mb-3" style="color: #f59e0b;"></i>
                            <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">todos los valores en cero</span>.</p>
                            <p class="text-muted small">No se puede guardar un trabajador sin al menos un valor (HE Diurnas, Nt 7-23h, Nt 23-7h, Doble Turno o Descuentos).</p>
                        </div>
                    `,
                    icon: 'warning',
                    confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar valores',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                    cancelButtonColor: '#ef4444',
                    background: '#1a1a2e',
                    color: '#ffffff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        setTimeout(function() {
                            $('#editHoras').focus().select();
                        }, 200);
                    } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                        eliminarTrabajadorPorId(id, nombre);
                    }
                });
                return;
            }
            
            datos.nocturnidad_temprana = noctT;
            datos.nocturnidad_tardia = noctD;
            datos.doble_turno = dt;
            datos.dias_feriados = 0;
            datos.otros_salarios = 0;
        }
        
        console.log('Datos a enviar (AUTO/EXTRA):', datos);

    } else if (tipoNomina === 'vacaciones') {
        dias = Math.max(0, parseFloat($('#editDias').val()) || 0);
        descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
        
        // ==========================================
        // ✅ VALIDACIÓN: Días en Cero
        // ==========================================
        if (dias === 0 || dias < 0.5) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Días Tomados en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-umbrella-beach fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 días tomados</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 días en la nómina de vacaciones.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar días',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editDias').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }
        
        datos.dias_vacaciones = dias;
        datos.descuentos = descuentos;
        console.log('Datos a enviar (VACACIONES):', datos);

    } else if (tipoNomina === 'ajuste') {
        descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
        descripcion = $('#editConceptoAjuste').val() || '';
        otrosPagos = Math.max(0, parseFloat($('#editOtrosPagos').val()) || 0);
        var $horasInputAjuste = $('#editHorasAjuste');
        var esModoHorasAjuste = $horasInputAjuste.length > 0;
        horas = esModoHorasAjuste ? (Math.max(0, parseFloat($horasInputAjuste.val()) || 0)) : 0;
        monto = Math.max(0, parseFloat($('#editMontoAjuste').val()) || 0);
        
        // ==========================================
        // ✅ VALIDACIÓN: Monto del Ajuste en Cero
        // ==========================================
        if (esModoHorasAjuste) {
            if (horas === 0 || horas < 0.5) {
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Horas en Cero',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-clock fa-3x mb-3" style="color: #f59e0b;"></i>
                            <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 horas trabajadas</span>.</p>
                            <p class="text-muted small">No se puede guardar un ajuste por horas con 0 horas.</p>
                        </div>
                    `,
                    icon: 'warning',
                    confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar horas',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                    cancelButtonColor: '#ef4444',
                    background: '#1a1a2e',
                    color: '#ffffff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        setTimeout(function() {
                            $('#editHorasAjuste').focus().select();
                        }, 200);
                    } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                        eliminarTrabajadorPorId(id, nombre);
                    }
                });
                return;
            }
        } else if (monto === 0 || monto < 0.5) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto del Ajuste en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-pen fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 monto del ajuste</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 monto en la nómina de ajustes.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar monto',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editMontoAjuste').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }
        
        datos.monto_bono = monto;
        datos.descuentos = descuentos;
        datos.descripcion = descripcion;
        datos.otros_salarios = otrosPagos;
        if (esModoHorasAjuste) {
            datos.horas_laboradas = horas;
        }
        console.log('Datos a enviar (AJUSTE):', datos);
    }

    // ==========================================
    // 4. DESHABILITAR BOTÓN Y ENVIAR AJAX
    // ==========================================
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

    console.log('Enviando petición AJAX...');
    console.log('Objeto datos FINAL:', datos);

    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: datos,
        success: function(r) {
            console.log('=== RESPUESTA AJAX RECIBIDA ===');
            console.log('Respuesta del servidor:', r);
            console.log('¿Éxito?', r.success);
            
            if (r.success) {
                console.log('✅ Guardado exitoso en el servidor');
                
                // Actualizar la fila en la tabla
                var $row = $('#tablaNominas').find('tr[data-id="' + id + '"]');
                console.log('Fila encontrada:', $row.length > 0);

                if ($row.length) {
                    if (tipoNomina === 'bono') {
                        console.log('Actualizando fila BONO con monto:', monto);
                        $row.find('.edit-bono').val(monto);
                        $row.find('.edit-descuentos').val(descuentos);
                        var $colsNombre = $row.find('.col-nombre');
                        if ($colsNombre.length > 1) {
                            $colsNombre.eq(1).text(descripcion || '-');
                        }
                        if (typeof recalcularFilaBono === 'function') {
                            recalcularFilaBono($row);
                        }
                    } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                        $row.find('.edit-horas').val(horas);
                        $row.find('.edit-descuentos').val(descuentos);
                        if (tipoNomina === 'automatica') {
                            $row.find('.edit-feriados').val(feriados);
                            $row.find('.edit-otros-pagos').val(otrosPagos);
                        } else {
                            $row.find('.edit-noct-temprana').val(noctT || 0);
                            $row.find('.edit-noct-tardia').val(noctD || 0);
                            $row.find('.edit-doble-turno').val(dt || 0);
                        }
                        $row.find('.edit-horas').trigger('input');
                    } else if (tipoNomina === 'vacaciones') {
                        $row.find('.edit-dias').val(dias);
                        $row.find('.edit-descuentos').val(descuentos);
                        $row.find('.edit-dias').trigger('input');
                    } else if (tipoNomina === 'ajuste') {
                        $row.find('.edit-bono').val(monto);
                        $row.find('.edit-descuentos').val(descuentos);
                        $row.find('.edit-otros-pagos').val(otrosPagos);
                        $row.find('.col-nombre').eq(1).text(descripcion || '-');
                        if (esModoHorasAjuste) {
                            $row.attr('data-horas-ajuste', horas);
                        }
                        // 🔁 Actualizar el badge de horas trabajadas (ajuste por horas)
                        var $badgeHoras = $row.find('.bono-val-cell .badge.bg-info');
                        if (parseFloat(horas) > 0) {
                            if ($badgeHoras.length) {
                                $badgeHoras.text(formatNumber(parseFloat(horas)) + ' h');
                            } else {
                                $row.find('.bono-val-cell').append(
                                    '<span class="badge bg-info text-dark ms-1" title="Nómina por horas trabajadas (acumula vacaciones)">' +
                                    formatNumber(parseFloat(horas)) + ' h</span>'
                                );
                            }
                        } else if ($badgeHoras.length) {
                            $badgeHoras.remove();
                        }
                        $row.find('.edit-bono').trigger('input');
                    }

                    if (typeof actualizarEstadisticas === 'function') {
                        actualizarEstadisticas();
                    }
                }

                // ✅ Guardar valores originales en el modal
                var originalValues = {
                    monto: monto.toString(),
                    descuentos: descuentos.toString(),
                    descripcion: descripcion,
                    horas: horas.toString(),
                    feriados: feriados.toString(),
                    otrosPagos: otrosPagos.toString(),
                    nocturnas: nocturnas.toString(),
                    dias: dias.toString()
                };
                console.log('Guardando valores originales en modal:', originalValues);
                $('#modalEdicionRapida').data('originalValues', originalValues);

                mostrarToast('success', '✅ Registro actualizado correctamente');

                // 🔄 Actualizar los DataTables para reflejar los cambios
                try {
                    if ($.fn.DataTable.isDataTable('#tablaNominas')) {
                        $('#tablaNominas').DataTable().rows().invalidate().draw(false);
                    }
                } catch (e) {
                    console.warn('No se pudo refrescar el DataTable:', e);
                }

                enfocarCampoEdicion();

            } else {
                console.error('❌ Error en el servidor:', r.error || 'Error desconocido');
                mostrarToast('danger', r.error || 'Error al guardar');
            }
        },
        error: function(xhr, status, error) {
            console.error('=== ERROR AJAX ===');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Respuesta del servidor:', xhr.responseText);
            mostrarToast('danger', 'Error de conexión con el servidor: ' + error);
        },
        complete: function() {
            console.log('=== AJAX COMPLETADO ===');
            $btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
        }
    });
});

// EVENTO AGREGADO: Devuelve el foco al primer input cuando se cierra una alerta en el modal
$(document).on('closed.bs.alert', '#modalEdicionBody .alert', function () {
    setTimeout(function() {
        enfocarCampoEdicion();
    }, 50);
});

// EVENTO Controlador para el botón Restablecer
    $('#btnModalReset').on('click', function() {
        var original = $('#modalEdicionRapida').data('originalValues');
        if (!original) return;

        if (tipoNomina === 'automatica') {
            $('#editHoras').val(original.horas);
            $('#editFeriados').val(original.feriados);
            $('#editOtrosPagos').val(original.otrosPagos);
            $('#editDescuentos').val(original.descuentos);
            // Reincorporar todo pago adicional que se hubiera quitado: restaurar su botón "x"
            // y su contribución visual, sin borrar la fila (queda para futuros pagos).
            $('#modalEdicionBody').find('.btn-toggle-pago-adic').each(function() {
                var $b = $(this);
                $b.data('aplicado', 1)
                    .html('<i class="fas fa-times"></i>')
                    .attr('title', 'Quitar este pago')
                    .addClass('text-danger').removeClass('text-success');
                $b.closest('.d-flex').css('opacity', '1');
            });
            recalcularPreviewAuto();
        } else if (tipoNomina === 'extraordinaria') {
            $('#editHoras').val(original.horas);
            $('#editNoctT').val(original.noctT || 0);
            $('#editNoctD').val(original.noctD || 0);
            $('#editDT').val(original.dt || 0);
            $('#editDescuentos').val(original.descuentos);
            recalcularPreviewAutoExtraordinaria();
        } else if (tipoNomina === 'bono') {
            $('#editMontoBono').val(original.monto);
            $('#editDescuentosBono').val(original.descuentos);
            $('#editDescripcionBono').val(original.descripcion);
            $('#editPorcientoBono').val(0);
            $('input[name="editTipoBonoRadio"][value="fijo"]').prop('checked', true).trigger('change');
            recalcularPreviewBono();
            // ✅ Enfocar el monto del bono después de restablecer
            enfocarCampoEdicion();
        } else if (tipoNomina === 'vacaciones') {
            $('#editDias').val(original.dias);
            $('#editDescuentos').val((parseFloat(original.descuentos) || 0).toFixed(2));
            recalcularPreviewVacaciones();
            enfocarCampoEdicion();
        } else if (tipoNomina === 'ajuste') {
            $('#editMontoAjuste').val((parseFloat(original.monto) || 0).toFixed(2));
            $('#editOtrosPagos').val((parseFloat(original.otrosPagos) || 0).toFixed(2));
            $('#editDescuentos').val((parseFloat(original.descuentos) || 0).toFixed(2));
            $('#editConceptoAjuste').val(original.descripcion || '');
            if (parseFloat(original.horas) > 0 && $('#editHorasAjuste').length) {
                $('#editHorasAjuste').val(parseFloat(original.horas).toFixed(2));
            }
            recalcularPreviewAjuste();
            enfocarCampoEdicion();
        }
        
        // Notificación visual de restablecimiento exitoso
        mostrarToast('info', 'Valores restablecidos a su estado inicial');
    });
	
	window.escapeHtml = function(str) {
		if (str === null || str === undefined) return '';
		var cadenaSegura = str.toString();
		return cadenaSegura.replace(/[&<>]/g, function(m) {
			if (m === '&') return '&amp;';
			if (m === '<') return '&lt;';
			if (m === '>') return '&gt;';
			return m;
		});
	}

    // =========================================================
    // LÓGICA DEL NUEVO MODAL DE SELECCIÓN DE DESCUENTO GENERAL
    // =========================================================
    var tipoDescuentoGenSeleccionado = null;
    var targetTypeGen = null;

    $('#modalSeleccionDescuentoGeneral').on('show.bs.modal', function(e) {
        var button = $(e.relatedTarget);
        targetTypeGen = button.data('target-type');
        $('#descuentoGeneralTarget').val(targetTypeGen);
        
        tipoDescuentoGenSeleccionado = null;
        $('#opcionTotalDescuentosGen').removeClass('selected');
        $('#opcionSoloCessGen').removeClass('selected');
        $('#infoCessGen').hide();
        $('#btnConfirmarDescuentoGen').prop('disabled', true);
    });

    $('#opcionTotalDescuentosGen').on('click', function() {
        $('#opcionTotalDescuentosGen').addClass('selected');
        $('#opcionSoloCessGen').removeClass('selected');
        $('#infoCessGen').hide();
        tipoDescuentoGenSeleccionado = 'total_rangos';
        $('#btnConfirmarDescuentoGen').prop('disabled', false);
    });

    $('#opcionSoloCessGen').on('click', function() {
        $('#opcionSoloCessGen').addClass('selected');
        $('#opcionTotalDescuentosGen').removeClass('selected');
        $('#infoCessGen').show();
        tipoDescuentoGenSeleccionado = 'solo_cess';
        $('#btnConfirmarDescuentoGen').prop('disabled', false);
    });

$('#btnConfirmarDescuentoGen').on('click', function() {
    if (!tipoDescuentoGenSeleccionado || !targetTypeGen) return;

    var genModalEl = document.getElementById('modalSeleccionDescuentoGeneral');
    var genModal = bootstrap.Modal.getInstance(genModalEl);
    genModal.hide();

    $(genModalEl).one('hidden.bs.modal', function() {
        // Guardamos la elección en una variable global temporal
        window.tempSelectedDiscount = tipoDescuentoGenSeleccionado;

        if (targetTypeGen === 'extraordinaria') {
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExtraordinaria'));
            modalObj.show();
        } else if (targetTypeGen === 'vacaciones') {
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVacaciones'));
            modalObj.show();
        } else if (targetTypeGen === 'bono') {
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBono'));
            modalObj.show();
        } else if (targetTypeGen === 'ajuste') {  // <--- NUEVO
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSeleccionTipoAjuste'));
            modalObj.show();
        }
    });
});

$('#modalAjuste').on('show.bs.modal', function() {
    // Reiniciamos listas de selección (el tipo de descuento ya lo asignó el primer handler)
    selectedAjuste = [];
    renderAjusteWorkerList();
    updateAjusteList();
    $('#searchAjusteWorker').val('');
    $('#conceptoAjuste').val('');
});



    // =========================================================
    // AJUSTAR COLUMNAS SEGÚN TIPO DESCUENTO EN TIEMPO REAL
    // =========================================================
function ajustarColumnasPorTipoDescuento(apiInstance) {
    var api = apiInstance || nominasTable;
    if (!api) return;
    
    // Detectamos si el descuento activo es "solo_cess"
    var esSoloCess = (activeTipoDescuento === 'solo_cess');
    
    // Seleccionamos las columnas directamente a través de su clase en DataTables
    var colImpuestos = api.columns('.col-impuestos-det');
    
    if (esSoloCess) {
        // Cambiamos el texto de la cabecera CESS a progresivo
        $('#tablaNominas thead tr:first th.col-cess-header').html('CESS<br>Prog.');
        
        // Ocultamos las columnas detalladas de impuestos
        colImpuestos.visible(false, false);
        
        // Ocultamos la cabecera principal que agrupa (Ingresos Personales)
        $('.col-impuestos').hide();
    } else {
        // Restauramos el texto de la cabecera CESS habitual
        $('#tablaNominas thead tr:first th.col-cess-header').html('CESS.<br>Hasta10%');
        
        // Mostramos las columnas detalladas de impuestos
        colImpuestos.visible(true, false);
        
        // Mostramos la cabecera principal agrupada
        $('.col-impuestos').show();
    }
    
    // Ajustamos la estructura de la tabla y recalculamos las columnas fijas
    api.columns.adjust();
    if (typeof api.fixedColumns === 'function') {
        api.fixedColumns().update();
    }
}



    $('#btnGuardarTodo').on('click', function() {
        guardarTodosLosCambios();
    });

    function guardarTodosLosCambios() {
        if (contabilizada) {
            Swal.fire({
                title: 'Nómina Contabilizada',
                text: 'No se pueden guardar cambios en una nómina contabilizada.',
                icon: 'warning',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            });
            return;
        }
        
        var todasLasFilas = [];
        if (nominasTable) {
            todasLasFilas = nominasTable.rows().nodes();
        } else {
            todasLasFilas = $('#tablaNominas tbody tr');
        }
        
        // 🔽 NUEVO: Solo se guardan las filas con estado Borrador
        todasLasFilas = $.grep(todasLasFilas, function(tr) {
            return $(tr).find('.badge-borrador').length > 0;
        });
        
        var totalFilas = todasLasFilas.length;
        
        if (totalFilas === 0) {
            Swal.fire({
                title: 'Sin datos',
                text: 'No hay registros para guardar.',
                icon: 'info',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            });
            return;
        }
        
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i>Guardando cambios...',
            text: 'Procesando ' + totalFilas + ' trabajadores',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1a1a2e',
            color: '#ffffff'
        });
        
        var procesadas = 0;
        var errores = 0;
        var exitosas = 0;
        
        function procesarFila(index) {
            if (index >= totalFilas) {
                Swal.fire({
                    title: '<i class="fas fa-check-circle text-success me-2"></i>Guardado completado',
                    html: '<strong>' + exitosas + '</strong> registros guardados correctamente.<br>' +
                          (errores > 0 ? '<span class="text-danger">' + errores + ' errores encontrados.</span>' : ''),
                    icon: errores > 0 ? 'warning' : 'success',
                    background: '#1a1a2e',
                    color: '#ffffff',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                }).then(() => {
                    location.reload();
                });
                return;
            }
            
            var fila = $(todasLasFilas[index]);
            var id = fila.data('id');
            
            if (!id) {
                procesadas++;
                exitosas++;
                procesarFila(index + 1);
                return;
            }
            
            // Omitir filas sin campos editables (p. ej. ajustes ya contabilizados)
            if (fila.find('.edit-bono, .edit-descuentos, .edit-horas, .edit-dias, .edit-salario, .edit-noct-temprana, .edit-noct-tardia, .edit-doble-turno').length === 0) {
                procesadas++;
                exitosas++;
                procesarFila(index + 1);
                return;
            }
            
            var datos = { actualizar_nomina: 1, id: id, tipo_nomina: tipoNomina };
            
            if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                var horasInput = fila.find('.edit-horas');
                var descuentosInput = fila.find('.edit-descuentos');
                
                datos.horas_laboradas = horasInput.length ? parseNumber(horasInput.val()) : 0;
                datos.descuentos = descuentosInput.length ? parseNumber(descuentosInput.val()) : 0;

                if (tipoNomina === 'automatica') {
                    var feriadosInput = fila.find('.edit-feriados');
                    var otrosPagosInput = fila.find('.edit-otros-pagos');
                    datos.dias_feriados = feriadosInput.length ? parseNumber(feriadosInput.val()) : 0;
                    datos.otros_salarios = otrosPagosInput.length ? parseNumber(otrosPagosInput.val()) : 0;
                    datos.horas_nocturnas = 0;
                } else {
                    var noctTInput = fila.find('.edit-noct-temprana');
                    var noctDInput = fila.find('.edit-noct-tardia');
                    var dtInput = fila.find('.edit-doble-turno');
                    datos.nocturnidad_temprana = noctTInput.length ? parseNumber(noctTInput.val()) : 0;
                    datos.nocturnidad_tardia = noctDInput.length ? parseNumber(noctDInput.val()) : 0;
                    datos.doble_turno = dtInput.length ? parseNumber(dtInput.val()) : 0;
                    datos.dias_feriados = 0;
                    datos.otros_salarios = 0;
                }
            } else if (tipoNomina === 'bono') {
                var bonoInput = fila.find('.edit-bono');
                var descInput = fila.find('.edit-descuentos');
                datos.monto_bono = bonoInput.length ? parseNumber(bonoInput.val()) : 0;
                datos.descuentos = descInput.length ? parseNumber(descInput.val()) : 0;
            } else if (tipoNomina === 'ajuste') {
                var bonoInput = fila.find('.edit-bono');
                var descInput = fila.find('.edit-descuentos');
                var otrosPagosRow = fila.find('.edit-otros-pagos');
                datos.monto_bono = bonoInput.length ? parseNumber(bonoInput.val()) : 0;
                datos.descuentos = descInput.length ? parseNumber(descInput.val()) : 0;
                datos.otros_salarios = otrosPagosRow.length ? parseNumber(otrosPagosRow.val()) : 0;
                var conceptoRow = fila.find('.col-nombre').eq(1).text().trim();
                datos.descripcion = (conceptoRow === '-' || conceptoRow === '') ? '' : conceptoRow;
                var horasAjusteFilas = parseFloat(fila.data('horas-ajuste') || 0);
                if (horasAjusteFilas > 0) {
                    datos.horas_laboradas = horasAjusteFilas;
                }
            } else if (tipoNomina === 'vacaciones') {
                var diasInput = fila.find('.edit-dias');
                var descuentosInput = fila.find('.edit-descuentos');
                datos.dias_vacaciones = diasInput.length ? parseNumber(diasInput.val()) : 0;
                datos.descuentos = descuentosInput.length ? parseNumber(descuentosInput.val()) : 0;
            } else {
                procesadas++;
                exitosas++;
                procesarFila(index + 1);
                return;
            }
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                data: datos,
                success: function(r) {
                    procesadas++;
                    if (r.success) {
                        exitosas++;
                    } else {
                        errores++;
                        console.error('Error guardando ID ' + id + ': ' + (r.error || 'Desconocido'));
                    }
                    procesarFila(index + 1);
                },
                error: function(xhr, status, error) {
                    procesadas++;
                    errores++;
                    console.error('Error AJAX guardando ID ' + id + ': ' + error);
                    procesarFila(index + 1);
                }
            });
        }
        
        procesarFila(0);
    }

    var nominasTable = null;
    var $tabla = $('#tablaNominas');

if ($tabla.length && $tabla.find('tbody tr').length > 0) {
    if ($.fn.DataTable.isDataTable('#tablaNominas')) {
        $tabla.DataTable().destroy();
    }
    
    try {
        nominasTable = $tabla.DataTable({
            language: {
                "decimal": "",
                "emptyTable": "No hay datos disponibles en la tabla",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 registros",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "lengthMenu": "Mostrar _MENU_ registros",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "search": "Buscar:",
                "zeroRecords": "No se encontraron registros coincidentes",
                "paginate": {
                    "first": '<i class="fas fa-step-backward"></i>',
                    "last": '<i class="fas fa-step-forward"></i>',
                    "next": '<i class="fas fa-chevron-right"></i>',
                    "previous": '<i class="fas fa-chevron-left"></i>'
                },
                buttons: {
                    colvisRestore: '<i class="fas fa-undo me-1"></i>Restaurar columnas'
                }
            },
            layout: {
                topStart: {
                    buttons: [
                        'colvis',
                        'colvisRestore',
                        {
                            extend: 'copy',
                            text: '<i class="fas fa-copy me-1"></i> Copiar',
                            className: 'btn-win btn-sm'
                        },
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv me-1"></i> CSV',
    className: 'btn-win btn-sm',
    footer: true,
                            action: function(e, dt, node, config) {
                                exportarCSVDesdeTabla(dt);
                            }
                        },
                        {
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel me-1"></i> Excel',
                            className: 'btn-win btn-sm'
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                            className: 'btn-win btn-sm',
                            action: function(e, dt, node, config) {
                                if (tipoNomina === 'extraordinaria') {
                                    exportarPdfOficial(obtenerTrabajadoresFiltrados(dt), 'general', '');
                                    return;
                                }
                                $.fn.dataTable.ext.buttons.pdf.action.call(this, e, dt, node, config);
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print me-1"></i> Imprimir',
                            className: 'btn-win btn-sm',
                            customize: function(win) {
                                win._origPrint = win.print.bind(win);
                                win._origClose = win.close.bind(win);
                                win.print = function() {};
                                win.close = function() {};
                                $(win.document.body).prepend('<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;"><span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span><button onclick="window._origPrint();window._origClose()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">🖨️ Imprimir</button><button onclick="window._origClose()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">✖ Cerrar</button></div>');
                                $(win.document.head).append('<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}@media print { .no-print { display: none !important; } }</style>');
                                var ahScript = win.document.createElement('script');
                                ahScript.textContent = '(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();';
                                win.document.body.appendChild(ahScript);
                            }
                        }
                    ]
                }
            },
            pageLength: -1,
            pagingType: 'full_numbers',
            autoWidth: false,
            scrollX: true,
            scrollY: "25rem",
            scrollCollapse: true,
            fixedColumns: {
                leftColumns: 2
            },
            columnDefs: [
                { orderable: false, targets: -1 },
                { width: '1.875rem', targets: [3, 4] }
            ],
responsive: {
    details: {
        type: 'inline',
        target: 'tr'
    }
},
            order: [[2, 'asc']],
            lengthMenu: [[5, 10, 15, 20, 25, 50, 100, -1], [5, 10, 15, 20, 25, 50, 100, "Todos"]],
            dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-colvis"c><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
            buttons: [
                {
                    text: '<i class="fas fa-step-backward me-1"></i>',
                    className: 'btn-win btn-sm buttons-first',
                    titleAttr: 'Ir al primer registro',
                    action: function(e, dt) {
                        dt.page('first').draw(false);
                    }
                },
                {
                    text: '<i class="fas fa-step-forward me-1"></i>',
                    className: 'btn-win btn-sm buttons-last',
                    titleAttr: 'Ir al último registro',
                    action: function(e, dt) {
                        dt.page('last').draw(false);
                    }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns me-1"></i> Columnas',
                    className: 'btn-win btn-sm',
                    postfixButtons: ['colvisRestore']
                },
                {
                    text: '<i class="fas fa-print text-primary me-2"></i> Nómina Impresa',
                    className: 'btn-win btn-sm',
                    action: function(e, dt, node, config) {
                        var estado = window.obtenerEstadoImpresion();
                        if (estado.motivo === 'vacio') {
                            Swal.fire({
                                title: '<i class="fas fa-inbox me-2" style="color: #60a5fa;"></i> Sin filas visibles',
                                html: '<div class="text-center"><p>No hay registros visibles para generar la Nómina Impresa.</p><p class="text-muted small">Revise la búsqueda y el filtro de número de nómina.</p></div>',
                                icon: 'info',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: '#3b82f6',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                            return;
                        }
                        if (estado.motivo === 'borrador') {
                            Swal.fire({
                                title: '📄 Nómina en estado Borrador',
                                html: '<div style="text-align: left;"><p><i class="fas fa-exclamation-triangle text-warning me-2"></i> <strong>No es posible generar la Nómina Impresa</strong> hasta que la nómina sea <strong>contabilizada</strong>.</p><p class="mt-3">Mientras está en <strong>Borrador</strong>, puedes:</p><ul class="text-start" style="display: inline-block; margin:0 auto;"><li><i class="fas fa-print me-2"></i>Imprimir un <strong>Reporte Oficial</strong> de la nómina</li><li><i class="fas fa-file-excel me-2"></i>Exportar a <strong>Excel, PDF, CSV o Word</strong></li><li><i class="fas fa-edit me-2"></i>Editar y ajustar los valores libremente</li></ul><hr class="my-3"><p class="text-muted small">Una vez contabilizada, podrás generar la Nómina Impresa definitiva.</p></div>',
                                icon: 'warning',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                width: '34.375rem'
                            });
                            return;
                        }
                        if (estado.motivo === 'multiples') {
                            Swal.fire({
                                title: '<i class="fas fa-layers me-2" style="color: #60a5fa;"></i> Varios números de nómina visibles',
                                html: '<div class="text-center"><p>Hay <strong>' + estado.numeros.length + ' nóminas diferentes</strong> visibles a la vez.</p><p class="mt-3">Use el filtro <strong>"No. Nómina"</strong> para seleccionar una sola nómina y luego genere la <strong>Nómina Impresa</strong>.</p></div>',
                                icon: 'info',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: '#3b82f6',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                            return;
                        }
                        if (estado.numero) numeroNomina = estado.numero;
                        const modalOpciones = new bootstrap.Modal(document.getElementById('modalOpcionesImpresion'));
                        modalOpciones.show();
                        cargarSelectores();
                        $('input[name="alcanceImpresion"]').off('change').on('change', cargarSelectores);
                    }
                },
				{
					text: '<i class="fas fa-file-pdf text-danger me-2"></i> PDF',
					className: 'btn-win btn-sm',
					action: function(e, dt, node, config) {
						// Obtener los trabajadores filtrados actualmente (con todos los campos de extraordinaria)
						var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
						
						if (trabajadoresFiltrados.length === 0) {
							Swal.fire({
								title: 'Sin datos',
								text: 'No hay registros visibles para exportar a PDF.',
								icon: 'warning',
								background: '#1a1a2e',
								color: '#ffffff',
								confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
							});
							return;
						}
						
						// Llamar a la función de exportación PDF con alcance 'general'
						exportarPdfOficial(trabajadoresFiltrados, 'general', '');
					}
				},
				 {
						text: '<i class="fas fa-file-word text-primary me-2"></i> Word',
						className: 'btn-win btn-sm',
						action: function(e, dt, node, config) {
							var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
							
							if (trabajadoresFiltrados.length === 0) {
								Swal.fire({
									title: 'Sin datos',
									text: 'No hay registros visibles para exportar a Word.',
									icon: 'warning',
									background: '#1a1a2e',
									color: '#ffffff'
								});
								return;
							}
							
							exportarWordOficial(trabajadoresFiltrados, 'general', '');
						}
					},
					{
						text: '<i class="fas fa-file-excel text-success me-2"></i> Excel',
						className: 'btn-win btn-sm',
						action: function(e, dt, node, config) {
							var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
							
							if (trabajadoresFiltrados.length === 0) {
								Swal.fire({
									title: 'Sin datos',
									text: 'No hay registros visibles para exportar a Excel.',
									icon: 'warning',
									background: '#1a1a2e',
									color: '#ffffff'
								});
								return;
							}
							
							exportarExcelOficial(trabajadoresFiltrados, 'general', '');
						}
					},
								{
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv text-info me-2"></i> CSV',
                    className: 'btn-win btn-sm',
                    title: function() { return nombreEmpresa + '_' + tipoNomina + '_' + '<?php echo $periodo; ?>'; },
                    action: function(e, dt, node, config) {
                        exportarCSVDesdeTabla(dt);
                    },
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function (data, row, column, node) {
                                var $el = $('<div>').html(data);
                                if ($el.find('input').length) return $el.find('input').val();
                                $el.find('button, i').remove();
                                return $el.text().trim();
                            }
                        }
                    }
                },
				{
					text: '<i class="fas fa-file-alt text-secondary me-2"></i> TXT',
					className: 'btn-win btn-sm',
					action: function(e, dt, node, config) {
						var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
						
						if (trabajadoresFiltrados.length === 0) {
							Swal.fire({
								title: 'Sin datos',
								text: 'No hay registros visibles para exportar a TXT.',
								icon: 'warning',
								background: '#1a1a2e',
								color: '#ffffff'
							});
							return;
						}
						
						// Generar contenido TXT
						var contenido = generarContenidoTXT(trabajadoresFiltrados);
						
						// Crear y descargar archivo
						var blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
						var link = document.createElement('a');
						var now = new Date();
						var timestamp = now.getFullYear() + '' + 
									   String(now.getMonth() + 1).padStart(2, '0') + '' + 
									   String(now.getDate()).padStart(2, '0') + '_' +
									   String(now.getHours()).padStart(2, '0') +
									   String(now.getMinutes()).padStart(2, '0') +
									   String(now.getSeconds()).padStart(2, '0');
						
						link.href = URL.createObjectURL(blob);
						link.download = nombreEmpresa.replace(/[^a-zA-Z0-9]/g, '_') + '_' + 
										tipoNomina + '_' + 
										'<?php echo $periodo; ?>' + '_' + 
										timestamp + '.txt';
						link.click();
						URL.revokeObjectURL(link.href);
						
						Swal.fire({
							title: '¡Exportado!',
							text: 'Archivo TXT generado correctamente.',
							icon: 'success',
							timer: 1500,
							showConfirmButton: false,
							background: '#1a1a2e',
							color: '#ffffff'
						});
					}
				},
{
    extend: 'print',
    text: '<i class="fas fa-print text-warning me-2"></i> Imprimir Rep. Pago',
    className: 'btn-win btn-sm',
    title: function() {
        var cleanTipo = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F _-]/g, '_');
        var cleanPeriodo = periodo.replace(/[^a-zA-Z0-9\u00C0-\u017F _-]/g, '_');
        return 'SC-4-06_' + cleanTipo + '_' + cleanPeriodo;
    },
    exportOptions: {
        columns: ':visible:not(.col-estado, .col-acciones, .col-numero-nomina)',
        footer: true, // <-- CONFIGURACIÓN CLAVE: Fuerza la exportación del tfoot (totales)
        format: {
            body: function (data, row, column, node) {
                var $el = $('<div>').html(data);
                if ($el.find('input').length) return $el.find('input').val();
                $el.find('.badge-borrador, .badge-contabilizado, button, i').remove();
                return $el.text().trim();
            },
            footer: function (data, row, column, node) { // Limpia y formatea la fila de totales
                var $el = $('<div>').html(data);
                $el.find('button, i').remove();
                return $el.text().trim();
            }
        }
    },
customize: function(win) {
    var totalTrabajadores = nominasTable.rows().count();
    var now = new Date();

    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var year = now.getFullYear();
    var hours24 = now.getHours();
    var ampm = hours24 >= 12 ? 'pm' : 'am';
    var hours12 = hours24 % 12 || 12;
    var hoursStr = String(hours12).padStart(2, '0');
    var minutesStr = String(now.getMinutes()).padStart(2, '0');
    var secondsStr = String(now.getSeconds()).padStart(2, '0');
    var fechaHora12h = `${day}/${month}/${year} - ${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;

    var $table = $(win.document.body).find('table');

    // 🔽 EXTRAORDINARIA: Inyectar columna $HE/D (importe) justo después de HE/D (horas)
    if (tipoNomina === 'extraordinaria') {
        $table.find('tbody tr').each(function() {
            var $fila = $(this);
            var $celdaHoras = $fila.find('td').eq(9);
            var $salDev = $fila.find('td').eq(16);
            var $dtImp = $fila.find('td').eq(15);
            if ($celdaHoras.length && $salDev.length && $dtImp.length) {
                var _pHE = function(txt) { return parseFloat(String(txt).replace(/[^0-9.\-]/g, '')) || 0; };
                var importeHE = _pHE($salDev.text()) - _pHE($dtImp.text());
                $celdaHoras.after('<td class="text-right">$' + importeHE.toFixed(2) + '</td>');
            }
        });
    }

    var totalColumns = $table.find('tbody tr:first td').length;

    // 🔽 NUEVO: Quitar el título <h1> que agrega el plugin (la cabecera ya trae el título del reporte)
    $(win.document.body).children('h1').remove();

    // 🔽 Suprimir auto-print Y auto-close de DataTables
    win._origPrint = win.print.bind(win);
    win._origClose = win.close.bind(win);
    win.print = function() {};
    win.close = function() {};

    // 🔽 TOOLBAR FLOTANTE DE IMPRESIÓN
    $(win.document.body).prepend(
        '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">'
        + '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
        + '<button onclick="window._origPrint();window._origClose()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;">🖨️ Imprimir</button>'
        + '<button onclick="window._origClose()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;">✖ Cerrar</button>'
        + ''
        + '</div>'
    );
    $(win.document.head).append('<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}@media print { .no-print { display: none !important; } }</style>');
    var ahScript = win.document.createElement('script');
    ahScript.textContent = '(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();';
    win.document.body.appendChild(ahScript);

    // 🔽 NUEVO: Determinar si es BONO
    var esBono = (tipoNomina === 'bono');
    var esAjuste = (tipoNomina === 'ajuste');
    // Para BONO y AJUSTE, el "Concepto" se imprime debajo de cada trabajador, no como columna
    var quitarConceptoCol = esBono || esAjuste;
    if (quitarConceptoCol) totalColumns = totalColumns - 1;
    var montoDistribuir = montoDistribuidoGlobal || 0;
    var mostrarMonto = (esBono && montoDistribuir > 0);
    var montoTexto = mostrarMonto ? '$' + montoDistribuir.toFixed(2) : '(Hasta que se contabilice)';

    var styles = `
        @page {
            size: letter landscape;
            margin: 15mm 12mm 22mm 12mm;
        }
        html {
            counter-reset: page;
        }
        body {
            font-family: 'Arial', sans-serif !important;
            font-size: 0.6875rem !important; 
            color: #000000 !important;
            background-color: #ffffff !important;
            padding-bottom: 2.1875rem !important;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            page-break-inside: auto !important;
        }
        tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        thead {
            display: table-header-group !important;
        }
        tfoot {
            display: table-footer-group !important;
        }
        th, td {
            border: 0.5pt solid #000000 !important;
            padding: 0.25rem 0.1562rem !important;
            font-family: 'Arial', sans-serif !important;
        }
        th {
            background-color: #004B87 !important;
            color: #ffffff !important;
            text-align: left !important; 
            font-weight: bold !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table tbody td:nth-child(n+${quitarConceptoCol ? 8 : 9}), table thead th:nth-child(n+${quitarConceptoCol ? 8 : 9}) {
            text-align: right !important;
        }
        .text-center { text-align: center !important; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        
        .col-feriados-header, .col-vacaciones-header, .col-impuestos {
            border-bottom: 0.5pt solid #ffffff !important;
            background-color: #003366 !important;
        }
        
        .print-footer-container {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-top: 0.0625rem solid #000000 !important; 
            padding-top: 0.3125rem !important;
            height: 1.5625rem !important;
            font-size: 8pt !important;
            font-family: 'Arial', sans-serif !important;
            color: #000000 !important; 
            background-color: #ffffff !important; 
            z-index: 999999 !important; 
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* 🔽 PAGINACIÓN MANUAL: cada hoja con su propia numeración */
        .print-sheet {
            page-break-after: always !important;
            break-after: page !important;
        }
        .print-sheet-last {
            page-break-after: auto !important;
        }
        .print-sheet-page {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        .print-sheet-page:not(.print-sheet-last) {
            page-break-after: always !important;
            break-after: page !important;
        }
        .print-sheet-table {
            width: 100% !important;
            border-collapse: collapse !important;
            page-break-inside: auto !important;
        }
        .print-sheet-footer {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-top: 0.0625rem solid #000000 !important;
            padding-top: 0.3125rem !important;
            height: 1.5625rem !important;
            font-size: 8pt !important;
            font-family: 'Arial', sans-serif !important;
            color: #000000 !important;
            margin-top: 0.5rem !important;
        }
        .print-sheet-footer div {
            font-weight: bold !important;
        }
        .print-sheet-footer-center {
            text-align: center !important;
        }
        .print-sheet-footer-right {
            text-align: right !important;
        }
        
        /* 🔽 Estilo para el Monto a Distribuir */
        .monto-distribuir {
            font-weight: bold !important;
            color: ${mostrarMonto ? '#004B87' : '#ef4444'} !important;
            font-size: 12pt !important;
        }
        tr.subtotal-row td {
            background-color: #dde6f0 !important;
            font-weight: bold !important;
            color: #004B87 !important;
            border: 0.5pt solid #000000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        tr.subtotal-row td.subtotal-label {
            text-align: left !important;
            font-style: italic !important;
        }
    `;
    $(win.document.head).append('<style>' + styles + '</style>');

    // 🔽 NUEVO: Monto a Distribuir en el header (si es BONO)
    var montoHeaderHtml = '';
    if (esBono) {
        montoHeaderHtml = `
            <tr>
                <td colspan="${totalColumns}" style="background-color: #ffffff !important; border: none !important; padding:0 0 0.9375rem 0 !important; color: #000000 !important; text-align: left !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:0.125rem solid #004B87; padding-bottom:0.625rem; font-family: Arial;">
                        <div style="display:flex; align-items:center;">
                            ${logoBase64 ? '<img src="' + logoBase64 + '" style="width:4.6875rem; margin-right:0.9375rem;">' : ''}
                            <div>
                                <h2 style="color:#004B87 !important; margin:0; font-size:12.5pt; font-weight:bold; font-family: Arial;">${nombreEmpresa}</h2>
                                <h4 style="color:#333; margin:0.1875rem 0 0 0; font-size:9.5pt; font-weight:bold; font-family: Arial;">
                                    REPORTE DE PROCESAMIENTO DE PAGO - <span style="color: red !important;">${tipoNominaTexto.toUpperCase()}</span>
                                </h4>
                                <p style="color:#004B87 !important; margin:0.125rem 0 0 0; font-size:8.5pt; font-weight:bold; font-family: Arial;">PERÍODO: ${periodoTexto.toUpperCase()}</p>
                            </div>
                        </div>
                        <div style="text-align:right; font-size:8pt; color:#444; line-height:1.4; font-family: Arial;">
                            <strong>No. Instrum. Pago:</strong> ${numeroNomina}<br>
                            <strong>Emisión:</strong> ${fechaHora12h}<br>
                            <strong>Código REEUP:</strong> ${reeup}<br>
                            <strong>NIT:</strong> ${nitEmpresa}<br>
                            <strong>Total Trabajadores:</strong> <span style="color:#004B87; font-weight:bold;">${totalTrabajadores}</span><br>
                            ${esBono ? `<strong>Monto a Distribuir:</strong> <span class="monto-distribuir">${montoTexto}</span>` : ''}
                        </div>
                    </div>
                    ${observacionesCierreGlobal ? `
                    <div style="margin-top:0.5rem; padding:0.375rem 0.625rem; background-color: #f8fafc; border: 0.5pt solid #004B87; font-size:8.5pt; font-family: Arial; font-weight: normal; color: #333333;">
                        <strong>Observaciones de Cierre:</strong> ${observacionesCierreGlobal}
                    </div>` : ''}
                </th>
            </tr>
        `;
    } else {
        // Header original para otros tipos de nómina
        montoHeaderHtml = `
            <tr>
                <th colspan="${totalColumns}" style="background-color: #ffffff !important; border: none !important; padding:0 0 0.9375rem 0 !important; color: #000000 !important; text-align: left !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:0.125rem solid #004B87; padding-bottom:0.625rem; font-family: Arial;">
                        <div style="display:flex; align-items:center;">
                            ${logoBase64 ? '<img src="' + logoBase64 + '" style="width:4.6875rem; margin-right:0.9375rem;">' : ''}
                            <div>
                                <h2 style="color:#004B87 !important; margin:0; font-size:12.5pt; font-weight:bold; font-family: Arial;">${nombreEmpresa}</h2>
                                <h4 style="color:#333; margin:0.1875rem 0 0 0; font-size:9.5pt; font-weight:bold; font-family: Arial;">
                                    REPORTE DE PROCESAMIENTO DE PAGO - <span style="color: red !important;">${tipoNominaTexto.toUpperCase()}</span>
                                </h4>
                                <p style="color:#004B87 !important; margin:0.125rem 0 0 0; font-size:8.5pt; font-weight:bold; font-family: Arial;">PERÍODO: ${periodoTexto.toUpperCase()}</p>
                            </div>
                        </div>
                        <div style="text-align:right; font-size:8pt; color:#444; line-height:1.4; font-family: Arial;">
                            <strong>No. Instrum. Pago:</strong> ${numeroNomina}<br>
                            <strong>Emisión:</strong> ${fechaHora12h}<br>
                            <strong>Código REEUP:</strong> ${reeup}<br>
                            <strong>NIT:</strong> ${nitEmpresa}<br>
                            <strong>Total Trabajadores:</strong> <span style="color:#004B87; font-weight:bold;">${totalTrabajadores}</span>
                        </div>
                    </div>
                    ${observacionesCierreGlobal ? `
                    <div style="margin-top:0.5rem; padding:0.375rem 0.625rem; background-color: #f8fafc; border: 0.5pt solid #004B87; font-size:8.5pt; font-family: Arial; font-weight: normal; color: #333333;">
                        <strong>Observaciones de Cierre:</strong> ${observacionesCierreGlobal}
                    </div>` : ''}
                </th>
            </tr>
        `;
    }

    // 🔽 MODIFICADO: Construcción de las cabeceras de la tabla
    var tr1 = '';
    var tr2 = '';
    var visibleTaxesCount = 0;
    var subImpuestosHtml = '';
    
    $('#tablaNominas thead tr:last th.col-impuestos-det').each(function() {
        if ($(this).is(':visible')) {
            visibleTaxesCount++;
            subImpuestosHtml += `<th class="text-right" style="background-color:#004B87 !important; color:#ffffff !important; border:0.5pt solid #000000 !important; font-size:0.4375rem !important;">${$(this).html()}</th>`;
        }
    });

    if (tipoNomina === 'automatica') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:8.75rem;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2">Escala</th>
                <th rowspan="2" class="text-right">S. Básico</th>
                <th rowspan="2" class="text-right">Horas</th>
                <th rowspan="2" class="text-right">S. Dev.</th>
                <th rowspan="2" class="text-right">$/Hora</th>
                <th colspan="2" class="col-feriados-header text-right">Días Feriados</th>
                <th colspan="2" class="col-vacaciones-header text-right">Acum. Vacaciones</th>
                <th rowspan="2" class="text-right">Otros Pagos</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = `
            <tr>
                <th class="text-right">Días</th>
                <th class="text-right">Importe</th>
                <th class="text-right">Días</th>
                <th class="text-right">Importe</th>
                ${subImpuestosHtml}
            </tr>
        `;
    } else if (tipoNomina === 'extraordinaria') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:8.75rem;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2">Escala</th>
                <th rowspan="2" class="text-right">S. Básico</th>
                <th rowspan="2" class="text-right">HE/D</th>
                <th rowspan="2" class="text-right">$/HE/D</th>
                <th rowspan="2" class="text-right">Nt 7-23h</th>
                <th rowspan="2" class="text-right">$/Nt 7-23h</th>
                <th rowspan="2" class="text-right">Nt 23-7h</th>
                <th rowspan="2" class="text-right">$/Nt 23-7h</th>
                <th rowspan="2" class="text-right">D/T</th>
                <th rowspan="2" class="text-right">$/DT</th>
                <th rowspan="2" class="text-right">S. Dev.</th>
                <th rowspan="2" class="text-right">$/Hora</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    } else if (tipoNomina === 'bono') {
        // 🔽 MODIFICADO: Para BONO el "Concepto" se imprime debajo de cada trabajador (sin columna)
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:8.75rem;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2" class="text-right">Monto Bono</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    } else if (tipoNomina === 'vacaciones') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:8.75rem;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2" class="text-right">S. Básico</th>
                <th rowspan="2" class="text-right">Tarf.</th>
                <th rowspan="2" class="text-right">Días Tomados</th>
                <th rowspan="2" class="text-right">Días Restantes</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    } else if (tipoNomina === 'ajuste') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:8.75rem;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2" class="text-right">Monto/hrs</th>
                <th rowspan="2" class="text-right">Otros Pagos</th>
                <th colspan="2" class="col-vacaciones-header text-right">Acum. Vacaciones</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = `
            <tr>
                <th class="text-right">Días</th>
                <th class="text-right">Importe</th>
                ${subImpuestosHtml}
            </tr>
        `;
    }

    // 🔽 APLICAR LOS HEADERS MODIFICADOS
    $table.find('thead').html(montoHeaderHtml + tr1 + tr2);

    // 🔽 NUEVO: Fondo azul inline en los títulos de columnas (evita que quede transparente en impresión)
    $table.find('thead tr:not(:first) th').each(function() {
        var est = this.getAttribute('style') || '';
        this.setAttribute('style', est + '; background-color: #004B87 !important; color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;');
    });

    $table.find('input').each(function() { $(this).replaceWith(document.createTextNode($(this).val() || '')); });
    $table.find('button, .btn-icon').remove();

    // 🔽 MODIFICADO: Para BONO, asegurar que el concepto se muestre correctamente
    if (tipoNomina === 'bono') {
        $table.find('tbody tr').each(function() {
            // Buscar la celda de concepto (la que tiene la clase .col-nombre o la columna 7)
            var $cells = $(this).find('td');
            if ($cells.length > 7) {
                // La columna 7 es la de concepto (índice 7)
                var concepto = $cells.eq(7).text().trim();
                if (concepto === '' || concepto === '-') {
                    $cells.eq(7).text('Sin concepto');
                }
            }
        });
    }

    $table.find('tbody tr').each(function() {
        $(this).find('.neto, td:last-child').css({
            'font-weight': 'bold',
            'background-color': '#f0f4f8',
            '-webkit-print-color-adjust': 'exact',
            'print-color-adjust': 'exact'
        });
    });
    
    $table.find('tfoot td').css({
        'background-color': '#f0f4f8',
        'font-weight': 'bold',
        'color': '#004B87',
        'border': '0.0625rem solid #000000',
        '-webkit-print-color-adjust': 'exact',
        'print-color-adjust': 'exact'
    });

    // 🔽 NUEVO: Para BONO y AJUSTE, mover el "Concepto" debajo de cada trabajador (sin columna)
    if (quitarConceptoCol) {
        var conceptoIdx = 7;
        $table.find('tbody tr').each(function() {
            var $cells = $(this).find('td');
            if ($cells.length > conceptoIdx) {
                var concepto = $cells.eq(conceptoIdx).text().trim();
                if (concepto === '' || concepto === '-') {
                    concepto = 'Sin concepto';
                }
                var nCols = $cells.length - 1;
                $cells.eq(conceptoIdx).remove();
                $(this).after('<tr><td colspan="' + nCols + '" style="text-align:left !important; border:0.0625rem solid #000000 !important;">Observación: ' + concepto + '</td></tr>');
            }
        });
        // Quitar la celda del Concepto en la fila de totales (tfoot)
        $table.find('tfoot tr').each(function() {
            var $cells = $(this).find('th, td');
            if ($cells.length > conceptoIdx) $cells.eq(conceptoIdx).remove();
        });
    }

    // 🔽 PAGINACIÓN DEL REPORTE CON SUBTOTALES POR HOJA Y TOTAL GENERAL
    var ROWS_POR_PAGINA = 12;
    var $filasImpresion = $table.find('tbody tr');
    var totalPaginasReales = Math.max(1, Math.ceil($filasImpresion.length / ROWS_POR_PAGINA));
    var theadHtml = $table.find('thead').html();

    // DETECCIÓN DE COLUMNAS NUMÉRICAS (desde salario_básico en adelante)
    var firstNumCol = (quitarConceptoCol || tipoNomina === 'vacaciones') ? 7 : 8;
    var totalCols = ($filasImpresion.length > 0) ? $filasImpresion.first().find('td').length : 0;
    var numericCols = [];
    var isMoneyCol = [];
    for (var ci = firstNumCol; ci < totalCols; ci++) {
        numericCols.push(ci);
        var hasMoney = false;
        $filasImpresion.each(function() {
            var t = $(this).find('td').eq(ci).text().trim();
            if (t.indexOf('$') !== -1) hasMoney = true;
        });
        isMoneyCol.push(hasMoney);
    }

    function _parseCell(t) {
        return parseFloat(t.replace(/[^0-9.\-]/g, '')) || 0;
    }
    function _fmtCell(val, money) {
        var s = Math.abs(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        return (money ? '$' : '') + (val < 0 ? '-' : '') + s;
    }

    // Calcular FILA DE TOTALES (suma de TODAS las filas — reemplaza el tfoot desalineado)
    var grandVals = {};
    for (var gi = 0; gi < numericCols.length; gi++) grandVals[numericCols[gi]] = 0;
    $filasImpresion.each(function() {
        var $tds = $(this).find('td');
        for (var gi = 0; gi < numericCols.length; gi++) {
            var gci = numericCols[gi];
            if (gci < $tds.length) grandVals[gci] += _parseCell($tds.eq(gci).text().trim());
        }
    });

    var tStyle = 'background-color:#004B87 !important;font-weight:bold !important;color:#ffffff !important;border:0.5pt solid #000000 !important;text-align:right !important;-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;';
    var sStyle = 'background-color:#f0f4f8 !important;font-weight:bold !important;color:#004B87 !important;border:0.5pt solid #000000 !important;text-align:right !important;-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;';

    function _buildFilaTotal(label, vals, style) {
        var html = '<tr style="page-break-inside:avoid !important;">';
        html += '<td colspan="' + firstNumCol + '" style="' + style + 'text-align:left !important;">' + label + '</td>';
        for (var si = 0; si < numericCols.length; si++) {
            html += '<td style="' + style + '">' + _fmtCell(vals[numericCols[si]], isMoneyCol[si]) + '</td>';
        }
        html += '</tr>';
        return html;
    }

    var htmlPaginado = '';
    for (var pIdx = 0; pIdx < totalPaginasReales; pIdx++) {
        var filasPagina = $filasImpresion.slice(pIdx * ROWS_POR_PAGINA, (pIdx + 1) * ROWS_POR_PAGINA);
        var filasHtml = '';
        filasPagina.each(function() { filasHtml += this.outerHTML; });

        // SUBTOTAL DE LA HOJA (solo filas de datos de esta página)
        if (numericCols.length > 0 && filasPagina.length > 0) {
            var subVals = {};
            for (var si = 0; si < numericCols.length; si++) subVals[numericCols[si]] = 0;
            filasPagina.each(function() {
                var $tds = $(this).find('td');
                for (var si = 0; si < numericCols.length; si++) {
                    var sci = numericCols[si];
                    if (sci < $tds.length) subVals[sci] += _parseCell($tds.eq(sci).text().trim());
                }
            });
            filasHtml += _buildFilaTotal('Subtotal', subVals, sStyle);
        }

        var esUltimaHoja = (pIdx === totalPaginasReales - 1);

        // TOTAL GENERAL al final de la última hoja
        if (esUltimaHoja && numericCols.length > 0) {
            filasHtml += _buildFilaTotal('TOTAL GENERAL', grandVals, tStyle);
        }

        htmlPaginado += `
            <div class="print-sheet-page ${esUltimaHoja ? 'print-sheet-last' : ''}">
                <table class="print-sheet-table">
                    <thead>${theadHtml}</thead>
                    <tbody>${filasHtml}</tbody>
                </table>
                <div class="print-sheet-footer">
                    <div>Reporte de Pago - ${nombreEmpresa}</div>
                    <div class="print-sheet-footer-center">Página ${pIdx + 1} de ${totalPaginasReales}</div>
                    <div class="print-sheet-footer-right">Impresión: ${fechaHora12h}</div>
                </div>
            </div>
        `;
    }
    $table.replaceWith(htmlPaginado);

    // INYECCIÓN DEL BLOQUE OFICIAL DE FIRMAS DE RESPONSABILIDAD
    $(win.document.body).append(`
        <div style="margin-top:3.125rem; page-break-inside: avoid; break-inside: avoid; font-family: Arial, sans-serif;">
            <table style="width:100%; border: none !important; margin-top:1.875rem; border-collapse: collapse;">
                <tr style="border: none !important;">
                    <td style="width:25%; text-align: center; border: none !important; padding:0.625rem; font-size:8.5pt; font-family: Arial, sans-serif; line-height:1.4;">
                        <p style="margin-bottom:3.125rem;"><b>Elaborado por:</b></p>
                        <p style="border-top: 0.0625rem solid #000; width:85%; margin:0 auto; padding-top:0.1875rem;"><b>${especialistaNominas}</b></p>
                        <p style="font-size:7.5pt; color: #555; margin-top:0.125rem;">Especialista de Nóminas</p>
                    </td>
                    <td style="width:25%; text-align: center; border: none !important; padding:0.625rem; font-size:8.5pt; font-family: Arial, sans-serif; line-height:1.4;">
                        <p style="margin-bottom:3.125rem;"><b>Revisado por:</b></p>
                        <p style="border-top: 0.0625rem solid #000; width:85%; margin:0 auto; padding-top:0.1875rem;"><b>${especialistaGestion}</b></p>
                        <p style="font-size:7.5pt; color: #555; margin-top:0.125rem;">Especialista en Gestión Económica</p>
                    </td>
                    <td style="width:25%; text-align: center; border: none !important; padding:0.625rem; font-size:8.5pt; font-family: Arial, sans-serif; line-height:1.4;">
                        <p style="margin-bottom:3.125rem;"><b>Aprobado por:</b></p>
                        <p style="border-top: 0.0625rem solid #000; width:85%; margin:0 auto; padding-top:0.1875rem;"><b>${jefeProyecto}</b></p>
                        <p style="font-size:7.5pt; color: #555; margin-top:0.125rem;">Director de Proyecto</p>
                    </td>
                    <td style="width:25%; text-align: center; border: none !important; padding:0.625rem; font-size:8.5pt; font-family: Arial, sans-serif; line-height:1.4;">
                        <p style="margin-bottom:3.125rem;"><b>Contabilizado por:</b></p>
                        <p style="border-top: 0.0625rem solid #000; width:85%; margin:0 auto; padding-top:0.1875rem;">Firma del Contador</p>
                        <p style="font-size:7.5pt; color: #555; margin-top:0.125rem;">Área Contable y Financiera</p>
                    </td>
                </tr>
            </table>
        </div>
    `);
}
}
			],
            drawCallback: function() { 
                actualizarEstadisticas(); 
                ajustarColumnasPorTipoDescuento(this.api()); 
            },
            initComplete: function() {
                ajustarColumnasPorTipoDescuento(this.api()); 
                actualizarEstadisticas();
                actualizarEstadoVista();
                $('#customSearchInput').on('keyup', function() {
                    setTimeout(function() {
                        actualizarEstadisticas();
                    }, 100);
                });
            }
        });

        // 🔽 NUEVO: Aplicar filtros y estado inicial de la vista tras crear el DataTable
        aplicarFiltros();

// Función auxiliar para obtener trabajadores filtrados del DataTable de forma robusta
function obtenerTrabajadoresFiltrados(dt) {
    var trabajadores = [];
    var filas = dt.rows({ search: 'applied' }).nodes();
    
    $(filas).each(function() {
        const $row = $(this);
        var trabajador = mapRowToTrabajador($row);
        trabajadores.push(trabajador);
    });
    
    return trabajadores;
}

// 🔽 NUEVO: Generador de contenido CSV (mismo formato que TXT, con la Observación debajo de cada trabajador)
function generarContenidoCSV(trabajadores) {
    var lines = [];
    var esc = function(v) {
        v = (v === null || v === undefined) ? '' : String(v);
        return '"' + v.replace(/"/g, '""') + '"';
    };

    // ============================================================
    // ENCABEZADO PRINCIPAL
    // ============================================================
    var esBono = (tipoNomina === 'bono');
    var esAjuste = (tipoNomina === 'ajuste');
    var esExtraominaria = (tipoNomina === 'extraordinaria');
    var mostrarConcepto = esBono || esAjuste;

    lines.push(esc(nombreEmpresa));
    lines.push(esc('Tipo de Nómina: ' + tipoNominaTexto) + ',' + esc('Período: ' + periodoTexto));
    lines.push(esc('Número Nómina: ' + (numeroNomina === 'Borrador' ? 'Borrador' : numeroNomina)));
    if (esBono) {
        lines.push(esc('Monto a Distribuir: ' + (montoDistribuidoGlobal > 0 ? '$' + montoDistribuidoGlobal.toFixed(2) : '(Hasta que se contabilice)')));
    }
    lines.push('');

    // ============================================================
    // ENCABEZADOS DE COLUMNAS (sin columna CONCEPTO: se muestra como línea debajo)
    // ============================================================
    var header;
    if (esBono) {
        header = [esc('COD'), esc('CI'), esc('NOMBRE Y APELLIDOS'), esc('MONTO BONO'), esc('NETO')];
    } else if (esAjuste) {
        header = [esc('COD'), esc('CI'), esc('NOMBRE Y APELLIDOS'), esc('MONTO AJUSTE'), esc('OTROS PAGOS'), esc('VAC. DÍAS'), esc('VAC. IMPORTE'), esc('DEVENGADO'), esc('CESS'), esc('RET.'), esc('NETO')];
    } else if (esExtraominaria) {
        header = [esc('COD'), esc('CI'), esc('NOMBRE Y APELLIDOS'), esc('CAT.'), esc('TARF.'),
                  esc('HE/D'), esc('$/HE/D'), esc('NT 7-23H'), esc('$/NT 7-23H'), esc('NT 23-7H'), esc('$/NT 23-7H'),
                  esc('DT'), esc('$/DT'), esc('DEVENGADO'), esc('CESS'), esc('DSCTOS.'), esc('RET. TOT.'), esc('PAGADO')];
    } else {
        header = [esc('COD'), esc('CI'), esc('NOMBRE Y APELLIDOS'), esc('DEVENGADO'), esc('DEDUCC.'), esc('NETO')];
    }
    lines.push(header.join(','));

    // ============================================================
    // DATOS DE TRABAJADORES
    // ============================================================
    trabajadores.forEach(function(t) {
        var row;
        if (esBono) {
            row = [
                esc(t.codigo), esc(t.ci), esc(t.nombre),
                esc('$' + (t.bono || 0).toFixed(2)),
                esc('$' + (t.pagado || 0).toFixed(2))
            ];
        } else if (esAjuste) {
            row = [
                esc(t.codigo), esc(t.ci), esc(t.nombre),
                esc('$' + (t.aCobrar || 0).toFixed(2)),
                esc('$' + (t.bono || 0).toFixed(2)),
                esc((t.vacDias || 0).toFixed(2)),
                esc('$' + (t.tiempoImp || 0).toFixed(2)),
                esc('$' + (t.devengado || 0).toFixed(2)),
                esc('$' + (t.impS || 0).toFixed(2)),
                esc('$' + (t.retenciones || 0).toFixed(2)),
                esc('$' + (t.pagado || 0).toFixed(2))
            ];
        } else if (esExtraominaria) {
            row = [
                esc(t.codigo), esc(t.ci), esc(t.nombre),
                esc(t.categoriaCodigo), esc((t.tarifaSal || 0).toFixed(2)),
                esc((t.horas || 0).toFixed(0)), esc('$' + (t.importeHE || 0).toFixed(2)),
                esc((t.noctT || 0).toFixed(0)), esc('$' + (t.importeNtT || 0).toFixed(2)),
                esc((t.noctD || 0).toFixed(0)), esc('$' + (t.importeNtD || 0).toFixed(2)),
                esc((t.dt || 0).toFixed(0)), esc('$' + (t.importeDT || 0).toFixed(2)),
                esc('$' + (t.devengado || 0).toFixed(2)),
                esc('$' + (t.impS || 0).toFixed(2)),
                esc('$' + (t.descuentos || 0).toFixed(2)),
                esc('$' + (t.retenciones || 0).toFixed(2)),
                esc('$' + (t.pagado || 0).toFixed(2))
            ];
        } else {
            row = [
                esc(t.codigo), esc(t.ci), esc(t.nombre),
                esc('$' + (t.devengado || 0).toFixed(2)),
                esc('$' + (t.retenciones || 0).toFixed(2)),
                esc('$' + (t.pagado || 0).toFixed(2))
            ];
        }
        lines.push(row.join(','));
        // 🔽 NUEVO: Observación/concepto debajo de cada trabajador (BONO y AJUSTE)
        if (mostrarConcepto) {
            lines.push(esc('Observación: ' + (t.concepto || 'Sin concepto')));
        }
    });

    if (observacionesCierreGlobal) {
        lines.push(esc('Observaciones de Cierre: ' + observacionesCierreGlobal));
    }

    return '\uFEFF' + lines.join('\r\n');
}

function exportarCSVDesdeTabla(dt) {
    var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);

    if (trabajadoresFiltrados.length === 0) {
        Swal.fire({
            title: 'Sin datos',
            text: 'No hay registros visibles para exportar a CSV.',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    var contenido = generarContenidoCSV(trabajadoresFiltrados);

    var blob = new Blob([contenido], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var now = new Date();
    var timestamp = now.getFullYear() + '' +
                    String(now.getMonth() + 1).padStart(2, '0') + '' +
                    String(now.getDate()).padStart(2, '0') + '_' +
                    String(now.getHours()).padStart(2, '0') +
                    String(now.getMinutes()).padStart(2, '0') +
                    String(now.getSeconds()).padStart(2, '0');

    link.href = URL.createObjectURL(blob);
    link.download = nombreEmpresa.replace(/[^a-zA-Z0-9]/g, '_') + '_' +
                    tipoNomina + '_' +
                    '<?php echo $periodo; ?>' + '_' +
                    timestamp + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);

    Swal.fire({
        title: '¡Exportado!',
        text: 'Archivo CSV generado correctamente.',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#ffffff'
    });
}

        // ==========================================
        // CONFIGURAR BÚSQUEDA PERSONALIZADA
        // ==========================================
        $('.dt-search').html(`
            <div class="input-group input-group-sm" style="width:23.75rem;">
                <span class="input-group-text" style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); border-right: none; color: #60a5fa;">
                    <i class="fas fa-search" style="font-size:0.875rem;"></i>
                </span>
                <input type="text" class="form-control form-control-sm" 
                       id="customSearchInput"
                       placeholder="Buscar en nómina..." 
                       style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); color: white;">
                <button class="btn btn-sm" type="button" id="clearSearchBtn" 
                        style="background: rgba(239, 68, 68, 0.2); border: 0.0625rem solid rgba(239, 68, 68, 0.3); color: #fca5a5;"
                        title="Limpiar búsqueda">
                    <i class="fas fa-times" style="font-size:0.75rem;"></i>
                </button>
            </div>
        `);

        $('#customSearchInput').on('keyup', function() {
            nominasTable.search(this.value).draw();
        });

        $('#clearSearchBtn').on('click', function() {
            $('#customSearchInput').val('');
            nominasTable.search('').draw();
        });
        
        // ==========================================
        // CONTROL DE VISIBILIDAD DEL MENÚ OPCIONES
        // ==========================================
        function actualizarVisibilidadMenuOpciones() {
            var hayDatos = false;
            
            if (nominasTable) {
                var filasVisibles = nominasTable.rows({ search: 'applied' }).count();
                hayDatos = (filasVisibles > 0);
            }
            
            if (hayDatos) {
                $('.menu-personalizar-vista').show();
                $('.menu-exportar-reportes').show();
                $('.menu-separador').show();
            } else {
                $('.menu-personalizar-vista').hide();
                $('.menu-exportar-reportes').hide();
                $('.menu-separador').hide();
            }
        }
        
        // Inicializar visibilidad del menú
        setTimeout(function() {
            actualizarVisibilidadMenuOpciones();
        }, 100);
        
        // Actualizar después de cada filtro o búsqueda
        nominasTable.on('draw', function() {
            actualizarVisibilidadMenuOpciones();
        });
        
        // Actualizar después de limpiar filtros
        $(document).on('click', '#btnLimpiarFiltros', function() {
            setTimeout(function() {
                actualizarVisibilidadMenuOpciones();
            }, 200);
        });
        
        console.log('DataTable inicializado correctamente');
        
    } catch(e) {
        console.error('Error al inicializar DataTable:', e);
    }
} else {
    console.log('No hay datos en la tabla o tabla no encontrada');
}

$('#filtroAcumulaVacaciones').on('change', function() {
    var valor = $(this).val();
    var $rangoSelect = $('#filtroRangoVacaciones');
    
    if (valor !== '') {
        $('#filtroTrabajador').val('').trigger('change.select2');
    }
    
    if (valor === 'no') {
        $rangoSelect.prop('disabled', true);
        $rangoSelect.val('');
        $rangoSelect.attr('title', 'No aplicable: Los trabajadores que no acumulan vacaciones tienen 0 días acumulados');
    } else {
        $rangoSelect.prop('disabled', false);
        $rangoSelect.attr('title', 'Filtrar por rango de días acumulados');
    }
    
    aplicarFiltros();
});

$('#filtroRangoVacaciones').on('change', function() {
    var valor = $(this).val();
    var $acumulaSelect = $('#filtroAcumulaVacaciones');
    
    if (valor !== '') {
        $('#filtroTrabajador').val('').trigger('change.select2');
    }
    
    if (valor && valor !== '') {
        $acumulaSelect.find('option[value="no"]').prop('disabled', true);
        
        if ($acumulaSelect.val() === 'no') {
            $acumulaSelect.val('si');
        }
        
        $acumulaSelect.find('option[value="no"]').css('color', '#6c757d');
    } else {
        $acumulaSelect.find('option[value="no"]').prop('disabled', false);
        $acumulaSelect.find('option[value="no"]').css('color', '');
    }
    
    aplicarFiltros();
});

function inicializarEstadoFiltros() {
    var acumulaVal = $('#filtroAcumulaVacaciones').val();
    var rangoVal = $('#filtroRangoVacaciones').val();
    
    if ($('#filtroTrabajador').val() !== '') {
        $('#filtroRangoVacaciones').prop('disabled', true);
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', true);
        return;
    }
    
    if (acumulaVal === 'no') {
        $('#filtroRangoVacaciones').prop('disabled', true);
        $('#filtroRangoVacaciones').attr('title', 'No aplicable: Los trabajadores que no acumulan vacaciones tienen 0 días acumulados');
    } else {
        $('#filtroRangoVacaciones').prop('disabled', false);
    }
    
    if (rangoVal && rangoVal !== '') {
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', true);
        if ($('#filtroAcumulaVacaciones').val() === 'no') {
            $('#filtroAcumulaVacaciones').val('si');
        }
    } else {
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', false);
    }
}

function actualizarEstadisticas() {
    var tDev = 0, tNeto = 0, tDesc = 0, tDias = 0;
    var tOtrosDescuentos = 0; // Variable exclusiva para acumular "Otros Descuentos"
    var tHorasNormales = 0, tNoctT = 0, tImporteNtT = 0, tNoctD = 0, tImporteNtD = 0, tDT = 0, tImporteDT = 0;
    var tBono = 0;
    var tSalarioBasico = 0, tSalarioLaboral = 0, tFeriadosDias = 0, tFeriadosImporte = 0;
    var tVacacionesDias = 0, tVacacionesImporte = 0, tOtrosPagos = 0;
    var tContribucion = 0;
    
    var $tabla = $('#tablaNominas');
    
    if (!$tabla.length || $tabla.find('tbody tr').length === 0) {
        $('#totalDevengado').text('$0.00');
        $('#totalNeto').text('$0.00');
        $('#totalDescuentos').text('$0.00');
        $('.total-horas-footer').text('0');
        return;
    }
    
    // Siempre se totalizan TODOS los registros (equivale a "Mostrar Todo"),
    // sin importar la paginación, búsqueda o filtros aplicados en la tabla.
    var filasVisibles = $tabla.find('tbody tr');
    var totalTrabajadores = filasVisibles.length;
    
    var impuestosAcumulados = [];
    var cantidadRangos = $('#tablaNominas thead tr:last th.col-impuestos-det').length;
    for (var i = 0; i < cantidadRangos; i++) {
        impuestosAcumulados.push(0);
    }
    
    filasVisibles.each(function() {
        var $row = $(this);
        
        var devengado = parseNumber($row.find('.total-devengado').text());
        var neto = parseNumber($row.find('.neto').text());
        tDev += devengado;
        tNeto += neto;
        
        // Sumar las deducciones totales de cada fila (CESS + IP + Otros Descuentos)
        var deducciones = parseNumber($row.find('.total-deducciones').text());
        tDesc += deducciones;

        // Sumar exclusivamente los "Otros Descuentos" de la fila
        var otrosDesc = $row.find('.edit-descuentos').length ? 
                        parseNumber($row.find('.edit-descuentos').val()) : 
                        parseNumber($row.find('.col-otros-descuentos').text());
        tOtrosDescuentos += otrosDesc;
        
        $row.find('.col-impuestos-det').each(function(idx) {
            var impVal = parseNumber($(this).text());
            if (impuestosAcumulados[idx] !== undefined) {
                impuestosAcumulados[idx] += impVal;
            }
        });
        
        var contribucion = parseNumber($row.find('.contribucion').text());
        tContribucion += contribucion;
        
        if (tipoNomina === 'bono') {
            var $bonoInput = $row.find('.edit-bono');
            var bonoVal = $bonoInput.length ? parseNumber($bonoInput.val()) : parseNumber($row.find('.bono-val-cell').text());
            tBono += bonoVal;
        } else if (tipoNomina === 'vacaciones') {
            var diasValue = 0;
            if ($row.find('.edit-dias').length) {
                diasValue = parseNumber($row.find('.edit-dias').val());
            } else {
                diasValue = parseNumber($row.find('td').eq(7).text());
            }
            tDias += diasValue;
        } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
            var horasNorm = $row.find('.edit-horas').length ? parseNumber($row.find('.edit-horas').val()) : parseNumber($row.find('td').eq(8).text());
            tHorasNormales += horasNorm;
            
            var salarioBasico = parseNumber($row.find('.salario-basico').text());
            tSalarioBasico += salarioBasico;
            
            var salarioLaboral = parseNumber($row.find('.salario-laboral').text());
            tSalarioLaboral += salarioLaboral;
            
            if (tipoNomina === 'automatica') {
                var feriadosDias = $row.find('.edit-feriados').length ? parseNumber($row.find('.edit-feriados').val()) : parseNumber($row.find('.col-feriados-dias').text());
                tFeriadosDias += feriadosDias;
                tFeriadosImporte += parseNumber($row.find('.feriados-importe').text());
                tOtrosPagos += parseNumber($row.find('.edit-otros-pagos').val() || $row.find('.col-otros-pagos').text());
            }
            
            if (tipoNomina === 'extraordinaria') {
                tNoctT += $row.find('.edit-noct-temprana').length ? parseNumber($row.find('.edit-noct-temprana').val()) : 0;
                tImporteNtT += parseNumber($row.find('.importe-nt-temprana').text());
                tNoctD += $row.find('.edit-noct-tardia').length ? parseNumber($row.find('.edit-noct-tardia').val()) : 0;
                tImporteNtD += parseNumber($row.find('.importe-nt-tardia').text());
                tDT += $row.find('.edit-doble-turno').length ? parseNumber($row.find('.edit-doble-turno').val()) : 0;
                tImporteDT += parseNumber($row.find('.importe-doble-turno').text());
            }
            
            if (tipoNomina === 'automatica') {
                tVacacionesDias += parseNumber($row.find('.vacaciones-dias').text());
                tVacacionesImporte += parseNumber($row.find('.vacations-importe').text());
            }
        } else if (tipoNomina === 'ajuste') {
            var $bonoInputAjuste = $row.find('.edit-bono');
            tBono += $bonoInputAjuste.length ? parseNumber($bonoInputAjuste.val()) : parseNumber($row.find('.bono-val-cell').text());
            tOtrosPagos += $row.find('.edit-otros-pagos').length ? parseNumber($row.find('.edit-otros-pagos').val()) : parseNumber($row.find('.col-otros-pagos').text());
            tVacacionesDias += parseNumber($row.find('.vacaciones-dias').text());
            tVacacionesImporte += parseNumber($row.find('.vacations-importe').text());
        }
    });
    
    $('#totalDevengado').text('$' + formatNumber(tDev));
    $('#totalNeto').text('$' + formatNumber(tNeto));
    $('#totalDescuentos').text('$' + formatNumber(tDesc)); // Mantiene el total de deducciones en la tarjeta de estadísticas
    $('#statTrabajadores').text(totalTrabajadores);
    
    if (tipoNomina === 'bono' || tipoNomina === 'ajuste') {
        $('.total-monto-bono-footer').text('$' + formatNumber(tBono));
    }
    
    if (tipoNomina === 'vacaciones') {
        $('.total-vacaciones-dias-footer').text(formatNumber(tDias));
    }
    
    actualizarTfootTotales({
        totalHoras: tHorasNormales,
        totalSalarioBasico: tSalarioBasico,
        totalSalarioLaboral: tSalarioLaboral,
        totalFeriadosDias: tFeriadosDias,
        totalFeriadosImporte: tFeriadosImporte,
        totalNoctTempranas: tNoctT,
        totalImporteNtTemprana: tImporteNtT,
        totalNoctTardias: tNoctD,
        totalImporteNtTardia: tImporteNtD,
        totalDobleTurno: tDT,
        totalImporteDT: tImporteDT,
        totalVacacionesDias: tVacacionesDias,
        totalVacacionesImporte: tVacacionesImporte,
        totalOtrosPagos: tOtrosPagos,
        totalDevengado: tDev,
        totalDescuentos: tOtrosDescuentos,
        totalDeducciones: tDesc,
        totalContribucion: tContribucion,
        totalNeto: tNeto,
        totalBono: tBono,
        totalVacaciones: tDias,
        impuestosPorRango: impuestosAcumulados
    });
}


function actualizarTfootTotales(totales) {
    $('.total-devengado-footer').text('$' + formatNumber(totales.totalDevengado));
    $('.total-descuentos-footer').text('$' + formatNumber(totales.totalDescuentos));
    $('.total-deducciones-footer').text('$' + formatNumber(totales.totalDeducciones));
    $('.total-contribucion-footer').text('$' + formatNumber(totales.totalContribucion));
    $('.total-neto-footer').text('$' + formatNumber(totales.totalNeto));
    
    if (totales.impuestosPorRango && totales.impuestosPorRango.length > 0) {
        for (var i = 0; i < totales.impuestosPorRango.length; i++) {
            $('.total-impuesto-' + i + '-footer').text('$' + formatNumber(totales.impuestosPorRango[i]));
        }
    }

    if (tipoNomina === 'automatica' || tipoNomina === 'ajuste') {
        $('.total-horas-footer').text(formatNumber(totales.totalHoras));
        $('.total-salario-basico-footer').text('$' + formatNumber(totales.totalSalarioBasico));
        $('.total-salario-laboral-footer').text('$' + formatNumber(totales.totalSalarioLaboral));
        $('.total-feriados-dias-footer').text(formatNumber(totales.totalFeriadosDias));
        $('.total-feriados-importe-footer').text('$' + formatNumber(totales.totalFeriadosImporte));
        $('.total-vacaciones-dias-footer').text(formatNumber(totales.totalVacacionesDias));
        $('.total-vacaciones-importe-footer').text('$' + formatNumber(totales.totalVacacionesImporte));
        $('.total-otros-pagos-footer').text('$' + formatNumber(totales.totalOtrosPagos));
    }
    
    if (tipoNomina === 'extraordinaria') {
        $('.total-horas-footer').text(formatNumber(totales.totalHoras));
        $('.total-salario-basico-footer').text('$' + formatNumber(totales.totalSalarioBasico));
        $('.total-salario-laboral-footer').text('$' + formatNumber(totales.totalSalarioLaboral));
        $('.total-nt-temprana-footer').text(formatNumber(totales.totalNoctTempranas));
        $('.total-importe-nt-temprana-footer').text('$' + formatNumber(totales.totalImporteNtTemprana));
        $('.total-nt-tardia-footer').text(formatNumber(totales.totalNoctTardias));
        $('.total-importe-nt-tardia-footer').text('$' + formatNumber(totales.totalImporteNtTardia));
        $('.total-doble-turno-footer').text(formatNumber(totales.totalDobleTurno));
        $('.total-importe-doble-turno-footer').text('$' + formatNumber(totales.totalImporteDT));
    }
    
    if (tipoNomina === 'bono') {
        $('.total-monto-bono-footer').text('$' + formatNumber(totales.totalBono));
    }
    
    if (tipoNomina === 'vacaciones') {
        $('.total-vacaciones-dias-footer').text(formatNumber(totales.totalVacaciones));
    }
}
    
    $('#menuColumnas').on('click', function(e) {
        e.preventDefault();
        $('.buttons-colvis').click();
    });

$('#menuNominaImpresa').on('click', function(e) {
    e.preventDefault();
    
    // 1. Verificar si existe nómina en el período actual
    var existeNomina = <?php echo $existe_nomina ? 'true' : 'false'; ?>;
    var tipoNominaTexto = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
    var periodoTexto = '<?php echo $nombre_mes . ' ' . $anio; ?>';
    
    if (!existeNomina) {
        Swal.fire({
            title: '<i class="fas fa-inbox me-2" style="color: rgba(255,255,255,0.4);"></i> No hay nómina generada',
            html: `
                <div style="text-align: center;">
                    <p style="color: rgba(255,255,255,0.6);">No existe nómina de tipo <strong>${tipoNominaTexto}</strong> para <strong>${periodoTexto}</strong></p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff',
backdrop: 'rgba(0,0,0,0.6)'
        });
        return;
    }
    
    // 2. Verificar el estado de las filas visibles (permitido si todas son contabilizadas de un solo lote)
    var estado = window.obtenerEstadoImpresion();
    
    if (estado.motivo === 'vacio') {
        Swal.fire({
            title: '<i class="fas fa-inbox me-2" style="color: #60a5fa;"></i> Sin filas visibles',
            html: '<div class="text-center"><p>No hay registros visibles para generar la Nómina Impresa.</p><p class="text-muted small">Revise la búsqueda y el filtro de número de nómina.</p></div>',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    if (estado.motivo === 'borrador') {
        Swal.fire({
            title: '<i class="fas fa-file-alt me-2" style="color: #f59e0b;"></i> Nómina en estado Borrador',
            html: `
                <div style="text-align: center;">
                    <i class="fas fa-file-alt fa-4x mb-3" style="color: #f59e0b; opacity: 0.7;"></i>
                    <h4 class="mb-2" style="color: #ffffff;">Nómina en estado Borrador</h4>
                    <p style="color: rgba(255,255,255,0.6);">No es posible generar la <strong>Nómina Impresa</strong> hasta que la nómina sea <strong>contabilizada</strong>.</p>
                    <hr style="border-color: rgba(148, 163, 184, 0.25); opacity: 1; margin:0.9375rem 0;">
                    <p style="color: rgba(255,255,255,0.5); font-size:0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        Mientras está en Borrador, puedes usar el botón <strong>"Imprimir Reporte"</strong> para obtener una vista previa.
                    </p>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#f59e0b',
            background: '#1a1a2e',
            color: '#ffffff',
        backdrop: 'rgba(0,0,0,0.6)'
        });
        return;
    }
    
    if (estado.motivo === 'multiples') {
        Swal.fire({
            title: '<i class="fas fa-layers me-2" style="color: #60a5fa;"></i> Varios números de nómina visibles',
            html: '<div class="text-center"><p>Hay <strong>' + estado.numeros.length + ' nóminas diferentes</strong> visibles a la vez.</p><p class="mt-3">Use el filtro <strong>"No. Nómina"</strong> para seleccionar una sola nómina y luego genere la <strong>Nómina Impresa</strong>.</p></div>',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    if (estado.numero) numeroNomina = estado.numero;
    
    // 3. Si existe y está contabilizada, mostrar el modal de opciones de impresión
    const modalOpciones = new bootstrap.Modal(document.getElementById('modalOpcionesImpresion'));
    modalOpciones.show();
    cargarSelectores();
    $('input[name="alcanceImpresion"]').off('change').on('change', cargarSelectores);
});

	$('#menuMontos').on('click', function(e) {
		e.preventDefault();
		cargarHistorialMontos();
	});

    $('#menuHistorialMontos').on('click', function(e) {
        e.preventDefault();
        cargarHistorialMontos();
    });

    $('#menuResumenSalarial').on('click', function(e) {
        e.preventDefault();
        var modal = new bootstrap.Modal(document.getElementById('modalSeleccionResumenSalarial'), { backdrop: 'static' });
        modal.show();
    });

    $('#opcionResumenPorCuentaBancaria').on('click', function() {
        var sel = bootstrap.Modal.getInstance(document.getElementById('modalSeleccionResumenSalarial'));
        if (sel) sel.hide();
        var modal = new bootstrap.Modal(document.getElementById('modalSinCuenta'), { backdrop: 'static' });
        modal.show();
        if (!sinCuentaBuscarIniciado) {
            sinCuentaBuscarIniciado = true;
            sinCuentaInitBuscar();
        }
        cargarSinCuenta();
    });

    $('#menuExportPDF').on('click', function(e) {
        e.preventDefault();
        $('.buttons-pdf').click();
    });

    $('#menuExportWord').on('click', function(e) {
        e.preventDefault();
        $('.buttons-word').click();
    });

    $('#menuExportExcel').on('click', function(e) {
        e.preventDefault();
        $('.buttons-excel').click();
    });

    $('#menuExportCSV').on('click', function(e) {
        e.preventDefault();
        $('.buttons-csv').first().click();
    });

    $('#menuExportTXT').on('click', function(e) {
        e.preventDefault();
        $('.buttons-csv').last().click();
    });

    $('#menuExportPrint').on('click', function(e) {
        e.preventDefault();
        $('.buttons-print').click();
    });

    // ==================== TRABAJADORES SIN NÓMINA ====================
    var SIN_NOMINA_LOGO = <?php echo json_encode($logo_base64); ?>;
    var SIN_NOMINA_EMPRESA = <?php echo json_encode($config_empresa['nombre_empresa'] ?? COMPANY_NAME); ?>;
    var SIN_NOMINA_USUARIO = <?php echo json_encode($user_nombre_completo ?? 'Usuario'); ?>;
    var SIN_NOMINA_JEFE = <?php echo json_encode($config_empresa['jefe_proyecto'] ?? JEFE_PROYECTO); ?>;
    var SIN_NOMINA_ESP_GESTION = <?php echo json_encode($config_empresa['especialista_gestion'] ?? ESPECIALISTA); ?>;
    var SIN_NOMINA_ESP_RRHH = <?php echo json_encode($config_empresa['especialista_gestionRRHH'] ?? ''); ?>;
    var SIN_NOMINA_PRINT_CSS = <?php echo json_encode('
        @page { size: landscape; margin: 6mm 10mm 10mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 0.6875rem; color: #000; margin: 0; }
        .btn-print { background: #b91c1c; color: #fff; border: none; border-radius: 0.375rem; padding: 0.625rem 1.125rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
        .btn-print-secondary { background: #666; }
        .sn-cabecera { width: 100%; border-bottom: 0.125rem solid #b91c1c; padding-bottom: 0.5rem; margin-bottom: 0.75rem; }
        .sn-cabecera-logo { width: 2.3cm; text-align: left; vertical-align: middle; }
        .sn-cabecera-logo img { width: 2.03cm; height: 2.03cm; }
        .sn-cabecera-titulo { text-align: center; vertical-align: middle; }
        .sn-empresa { font-size: 16pt; font-weight: bold; color: #1f2937; }
        .sn-titulo { font-size: 13pt; font-weight: bold; color: #b91c1c; }
        .sn-alcance { font-size: 9pt; color: #444; }
        .sn-cabecera-datos { font-size: 8pt; text-align: right; vertical-align: middle; }
        .sn-total { font-weight: bold; color: #b91c1c; }
        .sn-tabla { width: 100%; border-collapse: collapse; }
        .sn-tabla th { background: #b91c1c; color: #fff; border: 0.0625rem solid #000; padding: 0.1875rem; font-size: 8pt; }
        .sn-tabla td { border: 0.0625rem solid #666; padding: 0.125rem 0.1875rem; font-size: 8pt; }
        .sn-tabla tbody tr:nth-child(even) { background: #f3f4f6; }
        .sn-total-fila { background: #fed7d7; font-weight: bold; }
        .sn-total-fila td { border-top: 0.125rem solid #000; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .sn-firmas { margin-top: 1.5625rem; }
        .sn-firmas-tabla { width: 100%; border-collapse: collapse; }
        .sn-firmas-tabla td { text-align: center; vertical-align: top; padding: 0.3125rem; width: 33%; }
        .sn-firma-label { font-size: 9pt; font-weight: bold; margin: 0 0 0.25rem; }
        .sn-firma-linea { border-bottom: 0.0625rem solid #000; height: 1rem; margin: 0 0.625rem; }
        .sn-firma-cargo { font-size: 10pt; font-weight: bold; margin: 0.25rem 0 0; }
        .sn-firma-subcargo { font-size: 8pt; color: #444; margin: 0; }
        @media print { .no-print { display: none !important; } }
    '); ?>;

    $('#menuSinNomina').on('click', function(e) {
        e.preventDefault();
        var modal = new bootstrap.Modal(document.getElementById('modalSinNomina'), { backdrop: 'static' });
        modal.show();
        cargarSinNomina();
    });

    function dispararAccionHashNominas() {
        var h = window.location.hash || '';
        if (h === '#verificar_cuadres') { $('#btnVerificarCuadre').trigger('click'); }
        else if (h === '#historial_montos') { $('#menuHistorialMontos').trigger('click'); }
        else if (h === '#resumen_salarial') { $('#menuResumenSalarial').trigger('click'); }
        else if (h === '#sin_nomina') { $('#menuSinNomina').trigger('click'); }
    }
    dispararAccionHashNominas();
    window.addEventListener('hashchange', dispararAccionHashNominas);

    var modalesSubmenuNominas = [
        { modal: 'modalCuadre', enlace: 'verificar_cuadres' },
        { modal: 'modalFullHistorial', enlace: 'historial_montos' },
        { modal: 'modalSeleccionResumenSalarial', enlace: 'resumen_salarial' },
        { modal: 'modalListadoDevengadoTrabajador', enlace: 'resumen_salarial' },
        { modal: 'modalSinCuenta', enlace: 'resumen_salarial' },
        { modal: 'modalSinNomina', enlace: 'sin_nomina' }
    ];

    function actualizarActivoSubmenuNominas() {
        var hayAbierto = false;
        document.querySelectorAll('#nominasSubmenu a').forEach(function(a) {
            a.classList.remove('active');
        });
        modalesSubmenuNominas.forEach(function(m) {
            var el = document.getElementById(m.modal);
            if (el && el.classList.contains('show')) {
                var link = document.querySelector('#nominasSubmenu a[href$="#' + m.enlace + '"]');
                if (link) link.classList.add('active');
                hayAbierto = true;
            }
        });
        return hayAbierto;
    }
    modalesSubmenuNominas.forEach(function(m) {
        var el = document.getElementById(m.modal);
        if (el) {
            el.addEventListener('shown.bs.modal', actualizarActivoSubmenuNominas);
            el.addEventListener('hidden.bs.modal', function() {
                var quedaAbierto = actualizarActivoSubmenuNominas();
                if (!quedaAbierto && window.history && window.history.replaceState) {
                    history.replaceState(null, '', window.location.pathname + window.location.search);
                }
            });
        }
    });

    $('#btnBuscarSinNomina').on('click', function() {
        cargarSinNomina();
    });

    $('#sinNominaAnio, #sinNominaMes').on('change', function() {
        cargarSinNomina();
    });

    function periodoSinNominaActual() {
        var anio = $('#sinNominaAnio').val() || new Date().getFullYear();
        var mes = $('#sinNominaMes').val() || '01';
        return anio + '-' + String(mes).padStart(2, '0');
    }

    function formatMoneySN(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cargarSinNomina() {
        var periodo = periodoSinNominaActual();
        var tbody = $('#tablaSinNomina tbody');
        tbody.html('<tr><td colspan="12" class="text-center text-muted"><i class="fas fa-spinner fa-pulse me-1"></i> Consultando...</td></tr>');
        $('#sinNominaResumen').html('<i class="fas fa-spinner fa-pulse me-1"></i> Consultando...');
        $.getJSON('exportar_sin_nomina.php', { accion: 'lista', periodo: periodo })
            .done(function(data) {
                if (data.success) {
                    var html = '';
                    if (data.trabajadores.length === 0) {
                        html = '<tr><td colspan="12" class="text-center text-success py-3"><i class="fas fa-check-circle me-2"></i>Todos los trabajadores con alta en ' + data.periodo_label + ' tienen nómina.</td></tr>';
                    } else {
                        $.each(data.trabajadores, function(i, t) {
                            html += '<tr>'
                                + '<td>' + (i + 1) + '</td>'
                                + '<td>' + $('<div>').text(t.codigo).html() + '</td>'
                                + '<td>' + $('<div>').text(t.ci).html() + '</td>'
                                + '<td>' + $('<div>').text(t.nombre).html() + '</td>'
                                + '<td>' + $('<div>').text(t.area || '—').html() + '</td>'
                                + '<td>' + $('<div>').text(t.centro_costo || '—').html() + '</td>'
                                + '<td>' + $('<div>').text(t.fecha_alta || '—').html() + '</td>'
                                + '<td>' + $('<div>').text(t.fecha_baja || '—').html() + '</td>'
                                + '<td>' + $('<div>').text(t.ultima_nomina).html() + '</td>'
                                + '<td>' + t.total_nominas + '</td>'
                                + '<td class="text-end text-info">' + formatMoneySN(t.total_devengado) + '</td>'
                                + '<td class="text-end text-success">' + formatMoneySN(t.total_neto) + '</td>'
                                + '</tr>';
                        });
                    }
                    tbody.html(html);
                    $('#sinNominaResumen').html('<i class="fas fa-user-slash me-1"></i>' + data.registros + ' trabajador(es) sin nómina · ' + data.periodo_label);
                } else {
                    tbody.html('<tr><td colspan="12" class="text-center text-danger py-3">' + $('<div>').text(data.mensaje || 'Error').html() + '</td></tr>');
                    $('#sinNominaResumen').html('<i class="fas fa-exclamation-triangle me-1"></i> Error');
                }
            })
            .fail(function() {
                tbody.html('<tr><td colspan="12" class="text-center text-danger py-3">No se pudo consultar el servidor.</td></tr>');
                $('#sinNominaResumen').html('<i class="fas fa-exclamation-triangle me-1"></i> Error');
            });
    }

    function exportarSinNomina(formato) {
        var periodo = periodoSinNominaActual();
        var extensiones = { pdf: 'pdf', word: 'doc', excel: 'xlsx', csv: 'csv', txt: 'txt' };
        Swal.fire({
            title: '<i class="fas fa-spinner fa-pulse me-2" style="color: #f87171;"></i> Generando archivo',
            text: 'Por favor espere, se está generando el listado de trabajadores sin nómina...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: '#1a1a2e',
            color: '#ffffff'
        });
        var formData = new FormData();
        formData.append('formato', formato);
        formData.append('periodo', periodo);
        fetch('exportar_sin_nomina.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var ext = extensiones[formato] || formato;
                var nombreArchivo = data.archivo + '.' + ext;
                fetch(data.descarga)
                    .then(function(res) { return res.blob(); })
                    .then(function(blob) {
                        var blobUrl = window.URL.createObjectURL(blob);
                        var link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = nombreArchivo;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        window.URL.revokeObjectURL(blobUrl);
                        Swal.fire({
                            icon: 'success',
                            title: '<i class="fas fa-check-circle me-2" style="color: #34d399;"></i> Exportación completada',
                            html: '<div style="text-align: center;">'
                                + '<i class="fas fa-file-alt" style="font-size:3rem; color: #34D399; margin-bottom:1rem;"></i>'
                                + '<p><strong>Archivo generado:</strong> ' + nombreArchivo + '</p>'
                                + '<p><strong>Registros exportados:</strong> ' + data.registros + '</p>'
                                + '<p><strong>Formato:</strong> ' + formato.toUpperCase() + '</p>'
                                + '</div>',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                            confirmButtonColor: '#3b82f6',
                            background: '#1a1a2e',
                            color: '#ffffff'
                        });
                    })
                    .catch(function(err) {
                        Swal.fire({
                            icon: 'error', title: 'Error al descargar', text: 'No se pudo descargar el archivo.',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                            confirmButtonColor: '#ef4444', background: '#1a1a2e', color: '#ffffff'
                        });
                    });
            } else {
                Swal.fire({
                    icon: 'error', title: 'Error en la exportación', text: data.mensaje || 'No se pudo generar el archivo',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                    confirmButtonColor: '#ef4444', background: '#1a1a2e', color: '#ffffff'
                });
            }
        })
        .catch(function(err) {
            Swal.fire({
                icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                confirmButtonColor: '#ef4444', background: '#1a1a2e', color: '#ffffff'
            });
        });
    }

    $('#btnSinNominaPDF').on('click', function() { exportarSinNomina('pdf'); });
    $('#btnSinNominaWord').on('click', function() { exportarSinNomina('word'); });
    $('#btnSinNominaExcel').on('click', function() { exportarSinNomina('excel'); });
    $('#btnSinNominaCSV').on('click', function() { exportarSinNomina('csv'); });
    $('#btnSinNominaTXT').on('click', function() { exportarSinNomina('txt'); });
    $('#btnSinNominaPrint').on('click', function() { imprimirSinNomina(); });

    function escHtmlSN(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function imprimirSinNomina() {
        var periodo = periodoSinNominaActual();
        $.getJSON('exportar_sin_nomina.php', { accion: 'lista', periodo: periodo })
            .done(function(data) {
                if (!data.success || data.trabajadores.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: '<i class="fas fa-user-slash me-2" style="color:#f87171;"></i> Sin datos',
                        text: 'No hay trabajadores activos sin nómina en el período seleccionado.',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        confirmButtonColor: '#3b82f6',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    return;
                }
                var filas = '';
                $.each(data.trabajadores, function(i, t) {
                    filas += '<tr>'
                        + '<td class="text-right">' + (i + 1) + '</td>'
                        + '<td>' + escHtmlSN(t.codigo) + '</td>'
                        + '<td>' + escHtmlSN(t.ci) + '</td>'
                        + '<td>' + escHtmlSN(t.nombre) + '</td>'
                        + '<td>' + escHtmlSN(t.area || '—') + '</td>'
                        + '<td>' + escHtmlSN(t.centro_costo || '—') + '</td>'
                        + '<td class="text-center">' + escHtmlSN(t.fecha_alta || '—') + '</td>'
                        + '<td class="text-center">' + escHtmlSN(t.fecha_baja || '—') + '</td>'
                        + '<td class="text-center">' + escHtmlSN(t.ultima_nomina) + '</td>'
                        + '<td class="text-center">' + t.total_nominas + '</td>'
                        + '<td class="text-right">' + formatMoneySN(t.total_devengado) + '</td>'
                        + '<td class="text-right">' + formatMoneySN(t.total_neto) + '</td>'
                        + '</tr>';
                });
                var fechaHora = new Date().toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                var contenido = `
                    <table class="sn-cabecera" cellspacing="0" cellpadding="0">
                        <tr>
                            <td class="sn-cabecera-logo">${SIN_NOMINA_LOGO ? '<img src="' + SIN_NOMINA_LOGO + '" alt="Logo">' : ''}</td>
                            <td class="sn-cabecera-titulo">
                                <div class="sn-empresa">${escHtmlSN(SIN_NOMINA_EMPRESA)}</div>
                                <div class="sn-titulo">TRABAJADORES SIN NÓMINA</div>
                                <div class="sn-alcance">${escHtmlSN(SIN_NOMINA_EMPRESA)} - Período: ${escHtmlSN(data.periodo_label)}</div>
                            </td>
                            <td class="sn-cabecera-datos">
                                <strong>Emisión:</strong> ${fechaHora}<br>
                                <strong>Generado por:</strong> ${escHtmlSN(SIN_NOMINA_USUARIO)}<br>
                                <strong>Total de trabajadores:</strong> <span class="sn-total">${data.registros}</span>
                            </td>
                        </tr>
                    </table>
                    <table class="sn-tabla">
                        <thead><tr>
                            <th>No.</th><th>Expediente</th><th>CI</th><th>Nombre Completo</th><th>Área</th><th>Centro de Costo</th><th>F. Alta</th><th>F. Baja</th><th>Últ. Nómina</th><th>Total</th><th>Devengado</th><th>Neto</th>
                        </tr></thead>
                        <tbody>${filas}</tbody>
                    </table>
                    <div class="sn-firmas">
                        <table class="sn-firmas-tabla">
                            <tr>
                                <td>
                                    <p class="sn-firma-label">Elaborado por:</p>
                                    <p class="sn-firma-linea"></p>
                                    <p class="sn-firma-cargo">${escHtmlSN(SIN_NOMINA_ESP_RRHH || SIN_NOMINA_USUARIO)}</p>
                                    <p class="sn-firma-subcargo">Especialista de Recursos Humanos</p>
                                </td>
                                <td>
                                    <p class="sn-firma-label">Revisado por:</p>
                                    <p class="sn-firma-linea"></p>
                                    <p class="sn-firma-cargo">${escHtmlSN(SIN_NOMINA_ESP_GESTION)}</p>
                                    <p class="sn-firma-subcargo">Especialista en Gestión Económica</p>
                                </td>
                                <td>
                                    <p class="sn-firma-label">Aprobado por:</p>
                                    <p class="sn-firma-linea"></p>
                                    <p class="sn-firma-cargo">${escHtmlSN(SIN_NOMINA_JEFE)}</p>
                                    <p class="sn-firma-subcargo">Director de Proyecto</p>
                                </td>
                            </tr>
                        </table>
                    </div>`;
                var win = window.open('', '_blank');
                if (!win) {
                    Swal.fire({
                        title: '<i class="fas fa-external-link-alt me-2" style="color:#fbbf24;"></i> Permiso requerido',
                        html: '<div class="text-center"><p>El navegador bloqueó la ventana emergente.</p><p class="text-muted small">Permita las ventanas emergentes para este sitio e inténtelo de nuevo.</p></div>',
                        icon: 'warning',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        confirmButtonColor: '#3b82f6',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    return;
                }
                win.document.open();
                win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Trabajadores Sin Nómina</title><style>' + SIN_NOMINA_PRINT_CSS + '</style></head><body>' + PRINT_TOOLBAR_HTML + contenido + '</body></html>');
                win.document.close();
            })
            .fail(function() {
                Swal.fire({
                    icon: 'error',
                    title: '<i class="fas fa-wifi text-danger me-2"></i> Error de conexión',
                    text: 'No se pudo consultar el servidor.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    confirmButtonColor: '#dc3545',
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
            });
    }
    // ==================== FIN TRABAJADORES SIN NÓMINA ====================

    // ==================== RESUMEN SIN TARJETA (TRABAJADORES SIN CUENTA BANCARIA) ====================
    var sinCuentaBuscarIniciado = false;
    var sinCuentaDatos = null;

    function sinCuentaInitBuscar() {
        $('#sinCuentaBuscarTrabajador').on('input', function() { sinCuentaBuscarTrabajador($(this).val()); });

        $('#sinCuentaLimpiarTrabajador').on('click', function() {
            $('#sinCuentaTrabajadorId').val('');
            $('#sinCuentaBuscarTrabajador').val('');
            $('#sinCuentaResultadosBusqueda').hide().empty();
            $('#sinCuentaTrabajadorInfo').empty();
            cargarSinCuenta();
        });

        $(document).on('click', '.sinCuenta-opt-trabajador', function(e) {
            e.preventDefault();
            var id = parseInt($(this).data('id')) || 0;
            var t = null;
            $.each(window.trabajadoresTodos || [], function(i, item) {
                if (parseInt(item.id) === id) { t = item; return false; }
            });
            if (t) {
                $('#sinCuentaTrabajadorId').val(t.id);
                $('#sinCuentaBuscarTrabajador').val(t.nombre_completo);
                $('#sinCuentaResultadosBusqueda').hide().empty();
                $('#sinCuentaTrabajadorInfo').html('<i class="fas fa-check-circle text-success me-1"></i>Seleccionado: <strong>' + escHtmlSC(t.nombre_completo) + '</strong> <small class="text-white-50">(' + escHtmlSC(t.codigo || 'S/C') + ' · ' + escHtmlSC(t.ci || 'S/CI') + ')</small>');
                cargarSinCuenta();
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.buscador-trabajador-modal').length) {
                $('#sinCuentaResultadosBusqueda').hide().empty();
            }
        });
    }

    function sinCuentaBuscarTrabajador(texto) {
        var term = (texto || '').toLowerCase().trim();
        var cont = $('#sinCuentaResultadosBusqueda');
        if (term.length < 2) { cont.hide().empty(); return; }

        var matches = [];
        $.each(window.trabajadoresTodos || [], function(i, t) {
            var hayNombre = (t.nombre_completo || '').toLowerCase().indexOf(term) !== -1;
            var hayCodigo = (t.codigo || '').toLowerCase().indexOf(term) !== -1;
            var hayCI = (t.ci || '').toLowerCase().indexOf(term) !== -1;
            if (hayNombre || hayCodigo || hayCI) matches.push(t);
        });

        if (!matches.length) {
            cont.html('<div class="dropdown-item text-muted">Sin resultados</div>').show();
            return;
        }

        var html = '';
        matches.slice(0, 40).forEach(function(t) {
            html += '<a class="dropdown-item sinCuenta-opt-trabajador" href="#" data-id="' + t.id + '">'
                + '<i class="fas fa-user me-2 text-info"></i>' + escHtmlSC(t.nombre_completo)
                + ' <small class="text-white-50">(' + escHtmlSC(t.codigo || 'S/C') + ' · ' + escHtmlSC(t.ci || 'S/CI') + ')</small>'
                + '</a>';
        });
        cont.html(html).show();
    }

    function escHtmlSC(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    $('#btnBuscarSinCuenta').on('click', function() {
        cargarSinCuenta();
    });

    $('#sinCuentaAnio, #sinCuentaMes, #sinCuentaEstado, #sinCuentaCuenta').on('change', function() {
        cargarSinCuenta();
    });

    function periodoSinCuentaActual() {
        var anio = $('#sinCuentaAnio').val() || new Date().getFullYear();
        var mes = $('#sinCuentaMes').val() || '01';
        return anio + '-' + String(mes).padStart(2, '0');
    }

    function formatMoneySC(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cargarSinCuenta() {
        var periodo = periodoSinCuentaActual();
        var estado = $('#sinCuentaEstado').val() || 'contabilizado';
        var cuenta = $('#sinCuentaCuenta').val() || 'sin';
        var trabajadorId = $('#sinCuentaTrabajadorId').val() ? parseInt($('#sinCuentaTrabajadorId').val()) : 0;
        var tituloSC = $('#sinCuentaCuenta').val() === 'con' ? 'Resumen Con Tarjeta' : ($('#sinCuentaCuenta').val() === 'todos' ? 'Resumen de Trabajadores' : 'Resumen Sin Tarjeta');
        $('#sinCuentaModalTitle').html('<i class="fas fa-credit-card me-2"></i> ' + tituloSC);
        var parametros = { accion: 'lista', periodo: periodo, estado: estado, cuenta: cuenta };
        if (trabajadorId) parametros.trabajador_id = trabajadorId;
        var tbody = $('#tablaSinCuenta tbody');
        tbody.html('<tr><td colspan="9" class="text-center text-muted"><i class="fas fa-spinner fa-pulse me-1"></i> Consultando...</td></tr>');
        $('#sinCuentaResumen').html('<i class="fas fa-spinner fa-pulse me-1"></i> Consultando...');
        ['sinCuentaTotBasico', 'sinCuentaTotNoct', 'sinCuentaTotVacac', 'sinCuentaTotAjuste', 'sinCuentaTotRendim', 'sinCuentaTotTotal'].forEach(function(id) {
            $('#' + id).text('0.00');
        });
        $.getJSON('exportar_sin_cuenta.php', parametros)
            .done(function(data) {
                if (data.success) {
                    sinCuentaDatos = data;
                    var html = '';
                    if (data.trabajadores.length === 0) {
                        html = '<tr><td colspan="9" class="text-center text-success py-3"><i class="fas fa-check-circle me-2"></i>No hay trabajadores ' + (cuenta === 'sin' ? 'sin cuenta bancaria' : (cuenta === 'con' ? 'con cuenta bancaria' : 'con nóminas')) + ' en ' + data.periodo_label + ' (' + data.estado_label + ').</td></tr>';
                    } else {
                        $.each(data.trabajadores, function(i, t) {
                            html += '<tr>'
                                + '<td>' + (i + 1) + '</td>'
                                + '<td>' + $('<div>').text(t.ci).html() + '</td>'
                                + '<td>' + $('<div>').text(t.nombre).html() + '</td>'
                                + '<td class="text-end">' + formatMoneySC(t.salar_basico) + '</td>'
                                + '<td class="text-end">' + formatMoneySC(t.noct_h_ext) + '</td>'
                                + '<td class="text-end">' + formatMoneySC(t.vacac) + '</td>'
                                + '<td class="text-end">' + formatMoneySC(t.ajuste_liquid) + '</td>'
                                + '<td class="text-end">' + formatMoneySC(t.rendim) + '</td>'
                                + '<td class="text-end text-warning">' + formatMoneySC(t.total_a_pagar) + '</td>'
                                + '</tr>';
                        });
                    }
                    tbody.html(html);
                    if (data.totales) {
                        $('#sinCuentaTotBasico').text(formatMoneySC(data.totales.salar_basico));
                        $('#sinCuentaTotNoct').text(formatMoneySC(data.totales.noct_h_ext));
                        $('#sinCuentaTotVacac').text(formatMoneySC(data.totales.vacac));
                        $('#sinCuentaTotAjuste').text(formatMoneySC(data.totales.ajuste_liquid));
                        $('#sinCuentaTotRendim').text(formatMoneySC(data.totales.rendim));
                        $('#sinCuentaTotTotal').text(formatMoneySC(data.totales.total_a_pagar));
                    }
                    resumenSinCuenta(data);
                } else {
                    tbody.html('<tr><td colspan="9" class="text-center text-danger py-3">' + $('<div>').text(data.mensaje || 'Error').html() + '</td></tr>');
                    $('#sinCuentaResumen').html('<i class="fas fa-exclamation-triangle me-1"></i> Error');
                }
            })
            .fail(function() {
                tbody.html('<tr><td colspan="9" class="text-center text-danger py-3">No se pudo consultar el servidor.</td></tr>');
                $('#sinCuentaResumen').html('<i class="fas fa-exclamation-triangle me-1"></i> Error');
            });
    }

    function resumenSinCuenta(data) {
        var cuentaTxt = data.cuenta_label || 'Sin cuenta';
        var lineaEstado = data.estado === 'todos' ? 'Todos los estados' : (data.estado_label || data.estado);
        var partes = [
            data.registros + ' trabajador(es)',
            cuentaTxt,
            data.periodo_label || '',
            lineaEstado
        ];
        if (data.trabajador_id && data.trabajador_label) partes.splice(1, 0, data.trabajador_label);
        $('#sinCuentaResumen').html('<i class="fas fa-filter me-1"></i>' + $('<div>').text(partes.join('  |  ')).html());
    }

    function exportarSinCuenta(formato) {
        if (!sinCuentaDatos || !sinCuentaDatos.success || !sinCuentaDatos.trabajadores || sinCuentaDatos.trabajadores.length === 0) {
            Swal.fire({
                icon: 'warning', title: '<i class="fas fa-credit-card me-2" style="color:#fbbf24;"></i> Sin datos',
                text: 'Primero consulte los trabajadores para poder exportar.',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: '#3b82f6', background: '#1a1a2e', color: '#ffffff'
            });
            return;
        }
        var d = sinCuentaDatos;
        var cuenta = $('#sinCuentaCuenta').val() || 'sin';
        var estado = $('#sinCuentaEstado').val() || 'contabilizado';
        var periodo = d.periodo || periodoSinCuentaActual();
        var extMap = { pdf: 'pdf', word: 'doc', excel: 'xlsx', csv: 'csv', txt: 'txt' };
        var sufijoCuenta = cuenta === 'sin' ? 'sin_tarjeta' : (cuenta === 'con' ? 'con_tarjeta' : 'todas_cuentas');
        var ahora = new Date();
        function p2(n) { return String(n).padStart(2, '0'); }
        var fechats = ahora.getFullYear() + p2(ahora.getMonth() + 1) + p2(ahora.getDate()) + '_' + p2(ahora.getHours()) + p2(ahora.getMinutes()) + p2(ahora.getSeconds());
        var nombreBase = 'trabajadores_' + sufijoCuenta + '_' + periodo + '_' + estado + '_' + fechats;
        var nombreArchivo = nombreBase + '.' + (extMap[formato] || formato);

        function sinCuentaDescargar(contenido, nombreArch, mime) {
            var blob = new Blob(['\ufeff' + contenido], { type: mime || 'text/plain;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = nombreArch;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function() { URL.revokeObjectURL(url); }, 1500);
        }

        function sinCuentaNum(v) {
            return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function sinCuentaCsvCell(v) {
            v = String(v == null ? '' : v);
            if (/[",\r\n]/.test(v)) { v = '"' + v.replace(/"/g, '""') + '"'; }
            return v;
        }

        var titulo = cuenta === 'con' ? 'RESUMEN CON TARJETA' : (cuenta === 'todos' ? 'RESUMEN DE TRABAJADORES' : 'RESUMEN SIN TARJETA');
        var periodoEtiqueta = String(d.periodo_label || periodo).toUpperCase().split(' ').join(' / ');
        var tituloCabecera = titulo + ' (' + periodoEtiqueta + ')';
        var filtrosLine = '  |  Estado: ' + (d.estado_label || estado)
            + '  |  Cuenta bancaria: ' + (d.cuenta_label || cuenta)
            + (d.trabajador_id && d.trabajador_label ? '  |  Trabajador: ' + String(d.trabajador_label).toUpperCase() : '');
        var fechaHora = ahora.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' - ' + ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var nombreEmpresaTxt = SIN_NOMINA_EMPRESA || nombreEmpresa || '';
        var usuarioTxt = SIN_NOMINA_USUARIO || usuarioNombre || '';

        if (formato === 'pdf') {
            var body = [[
                { text: 'No.', style: 'tableHeader' },
                { text: 'No CI.', style: 'tableHeader' },
                { text: 'Nombre y Apellidos', style: 'tableHeader' },
                { text: 'SALAR. BÁSICO', style: 'tableHeader', alignment: 'right' },
                { text: 'NOCT. H. EXT', style: 'tableHeader', alignment: 'right' },
                { text: 'VACAC.', style: 'tableHeader', alignment: 'right' },
                { text: 'AJUSTE Y/O LIQUID.', style: 'tableHeader', alignment: 'right' },
                { text: 'RENDIM.', style: 'tableHeader', alignment: 'right' },
                { text: 'TOTAL A PAGAR', style: 'tableHeader', alignment: 'right' }
            ]];
            $.each(d.trabajadores, function(i, t) {
                body.push([
                    { text: String(i + 1) },
                    { text: String(t.ci || '') },
                    { text: String(t.nombre || '') },
                    { text: sinCuentaNum(t.salar_basico), alignment: 'right' },
                    { text: sinCuentaNum(t.noct_h_ext), alignment: 'right' },
                    { text: sinCuentaNum(t.vacac), alignment: 'right' },
                    { text: sinCuentaNum(t.ajuste_liquid), alignment: 'right' },
                    { text: sinCuentaNum(t.rendim), alignment: 'right' },
                    { text: sinCuentaNum(t.total_a_pagar), alignment: 'right', bold: true }
                ]);
            });
            if (d.totales) {
                body.push([
                    { text: 'TOTAL GENERAL', bold: true, fillColor: '#f0f4f8', colSpan: 3 }, {}, {},
                    { text: sinCuentaNum(d.totales.salar_basico), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                    { text: sinCuentaNum(d.totales.noct_h_ext), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                    { text: sinCuentaNum(d.totales.vacac), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                    { text: sinCuentaNum(d.totales.ajuste_liquid), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                    { text: sinCuentaNum(d.totales.rendim), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                    { text: sinCuentaNum(d.totales.total_a_pagar), bold: true, alignment: 'right', fillColor: '#f0f4f8' }
                ]);
            }
            var docDefinition = {
                pageSize: 'LETTER',
                pageOrientation: 'landscape',
                pageMargins: [18, 18, 18, 30],
                content: [
                    {
                        table: {
                            widths: [55, '*', 170],
                            body: [[
                                (SIN_NOMINA_LOGO ? { image: SIN_NOMINA_LOGO, width: 40, alignment: 'center' } : { text: '' }),
                                {
                                    stack: [
                                        { text: nombreEmpresaTxt.toUpperCase(), fontSize: 12, bold: true, alignment: 'center' },
                                        { text: tituloCabecera, fontSize: 12, bold: true, alignment: 'center', margin: [0, 2, 0, 0] }
                                    ]
                                },
                                {
                                    stack: [
                                        { text: 'Emisión: ' + fechaHora, fontSize: 8, alignment: 'right' },
                                        { text: 'Generado por: ' + usuarioTxt, fontSize: 8, alignment: 'right' },
                                        { text: 'Total de trabajadores: ' + (d.registros || d.trabajadores.length), fontSize: 8, alignment: 'right' }
                                    ]
                                }
                            ]]
                        },
                        layout: 'noBorders',
                        margin: [0, 0, 0, 6]
                    },
                    { text: filtrosLine, fontSize: 9, bold: true, margin: [0, 2, 0, 8] },
                    {
                        table: {
                            headerRows: 1,
                            widths: [22, 55, '*', 58, 58, 56, 68, 56, 62],
                            body: body
                        },
                        layout: {
                            fillColor: function(rowIndex) { return rowIndex === 0 ? '#004B87' : null; }
                        }
                    },
                    { text: '', margin: [0, 14, 0, 0] },
                    {
                        columns: [
                            {
                                width: '*',
                                stack: [
                                    { text: 'Generado por:', bold: true, fontSize: 9 },
                                    { text: usuarioTxt, bold: true, fontSize: 8, margin: [0, 24, 0, 0] },
                                    { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                    { text: 'Usuario del sistema', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                                ]
                            },
                            {
                                width: '*',
                                stack: [
                                    { text: 'Revisado por:', bold: true, fontSize: 9 },
                                    { text: String(SIN_NOMINA_ESP_GESTION || especialistaGestion || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 24, 0, 0] },
                                    { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                    { text: 'Especialista en Gestión Económica', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                                ]
                            },
                            {
                                width: '*',
                                stack: [
                                    { text: 'Aprobado por:', bold: true, fontSize: 9 },
                                    { text: String(SIN_NOMINA_JEFE || jefeProyecto || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 24, 0, 0] },
                                    { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                    { text: 'Director de Proyecto', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                                ]
                            }
                        ]
                    }
                ],
                styles: {
                    tableHeader: { color: '#ffffff', bold: true }
                },
                footer: function(currentPage, pageCount) {
                    return { text: 'Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + usuarioTxt + '   Página ' + currentPage + ' de ' + pageCount, fontSize: 7, color: '#666', alignment: 'center', margin: [0, 8, 0, 0] };
                }
            };
            if (typeof pdfMake !== 'undefined') {
                pdfMake.createPdf(docDefinition).download(nombreArchivo);
            } else {
                Swal.fire({
                    icon: 'error', title: 'Error', text: 'La librería pdfMake no está disponible.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido', confirmButtonColor: '#ef4444', background: '#1a1a2e', color: '#ffffff'
                });
            }
            return;
        }

        if (formato === 'csv' || formato === 'txt') {
            if (formato === 'csv') {
                var lineas = [];
                lineas.push('LISTADO ' + titulo);
                lineas.push(['EMPRESA', nombreEmpresaTxt].join(','));
                lineas.push(['PERIODO', (d.periodo_label || periodo)].join(','));
                lineas.push(['ESTADO', (d.estado_label || estado)].join(','));
                lineas.push(['CUENTA BANCARIA', (d.cuenta_label || cuenta)].join(','));
                lineas.push(['EMISION', fechaHora].join(','));
                lineas.push(['GENERADO POR', usuarioTxt].join(','));
                lineas.push(['TOTAL DE TRABAJADORES', (d.registros || d.trabajadores.length)].join(','));
                lineas.push('');
                lineas.push(['No.', 'No CI.', 'Nombre y Apellidos', 'SALAR. BÁSICO', 'NOCT. H. EXT', 'VACAC.', 'AJUSTE Y/O LIQUID.', 'RENDIM.', 'TOTAL A PAGAR'].join(','));
                $.each(d.trabajadores, function(i, t) {
                    lineas.push([i + 1, t.ci, t.nombre, sinCuentaNum(t.salar_basico), sinCuentaNum(t.noct_h_ext), sinCuentaNum(t.vacac), sinCuentaNum(t.ajuste_liquid), sinCuentaNum(t.rendim), sinCuentaNum(t.total_a_pagar)].map(sinCuentaCsvCell).join(','));
                });
                if (d.totales) {
                    lineas.push(['TOTAL GENERAL', '', '', sinCuentaNum(d.totales.salar_basico), sinCuentaNum(d.totales.noct_h_ext), sinCuentaNum(d.totales.vacac), sinCuentaNum(d.totales.ajuste_liquid), sinCuentaNum(d.totales.rendim), sinCuentaNum(d.totales.total_a_pagar)].map(sinCuentaCsvCell).join(','));
                }
                sinCuentaDescargar(lineas.join('\r\n'), nombreArchivo, 'text/csv;charset=utf-8');
            } else {
                function padRightS(s, n) { s = String(s == null ? '' : s); return s.length >= n ? s : s + ' '.repeat(n - s.length); }
                function padLeftS(s, n) { s = String(s == null ? '' : s); return s.length >= n ? s : ' '.repeat(n - s.length) + s; }
                var tl = [];
                tl.push('========================================================================');
                tl.push('                ' + titulo);
                tl.push('========================================================================');
                tl.push('Empresa: ' + nombreEmpresaTxt);
                tl.push('Período: ' + (d.periodo_label || periodo));
                tl.push('Estado: ' + (d.estado_label || estado));
                tl.push('Cuenta bancaria: ' + (d.cuenta_label || cuenta));
                if (d.trabajador_id && d.trabajador_label) tl.push('Trabajador: ' + d.trabajador_label);
                tl.push('Emisión: ' + fechaHora);
                tl.push('Generado por: ' + usuarioTxt);
                tl.push('Total de trabajadores: ' + (d.registros || d.trabajadores.length));
                tl.push('------------------------------------------------------------------------');
                tl.push(padRightS('No.', 5) + padRightS('No CI.', 12) + padRightS('Nombre y Apellidos', 30) + padLeftS('SALAR. BAS', 12) + padLeftS('NOCT. H. EXT', 12) + padLeftS('VACAC.', 10) + padLeftS('AJUSTE', 12) + padLeftS('RENDIM.', 10) + padLeftS('TOTAL', 12));
                tl.push('------------------------------------------------------------------------');
                $.each(d.trabajadores, function(i, t) {
                    tl.push(padRightS(i + 1, 5) + padRightS(t.ci || '', 12) + padRightS(t.nombre || '', 30) + padLeftS(sinCuentaNum(t.salar_basico), 12) + padLeftS(sinCuentaNum(t.noct_h_ext), 12) + padLeftS(sinCuentaNum(t.vacac), 10) + padLeftS(sinCuentaNum(t.ajuste_liquid), 12) + padLeftS(sinCuentaNum(t.rendim), 10) + padLeftS(sinCuentaNum(t.total_a_pagar), 12));
                });
                tl.push('------------------------------------------------------------------------');
                if (d.totales) {
                    tl.push(padRightS('TOTAL GENERAL', 47) + padLeftS(sinCuentaNum(d.totales.salar_basico), 12) + padLeftS(sinCuentaNum(d.totales.noct_h_ext), 12) + padLeftS(sinCuentaNum(d.totales.vacac), 10) + padLeftS(sinCuentaNum(d.totales.ajuste_liquid), 12) + padLeftS(sinCuentaNum(d.totales.rendim), 10) + padLeftS(sinCuentaNum(d.totales.total_a_pagar), 12));
                }
                tl.push('========================================================================');
                sinCuentaDescargar(tl.join('\r\n'), nombreArchivo, 'text/plain;charset=utf-8');
            }
            return;
        }

        var html = '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;">'
            + '<tr><th colspan="9" style="font-size:0.875rem;text-align:center;">' + tituloCabecera + '</th></tr>'
            + '<tr><td colspan="9" style="text-align:center;font-weight:bold;font-size:0.75rem;">' + escHtmlSC(nombreEmpresaTxt.toUpperCase()) + '</td></tr>'
            + '<tr><td colspan="9"><b>Período:</b> ' + escHtmlSC(d.periodo_label || periodo) + '</td></tr>'
            + '<tr><td colspan="9"><b>Estado:</b> ' + escHtmlSC(d.estado_label || estado) + ' | <b>Cuenta bancaria:</b> ' + escHtmlSC(d.cuenta_label || cuenta) + (d.trabajador_id && d.trabajador_label ? ' | <b>Trabajador:</b> ' + escHtmlSC(d.trabajador_label) : '') + '</td></tr>'
            + '<tr><th>No.</th><th>No CI.</th><th>Nombre y Apellidos</th><th>SALAR. BÁSICO</th><th>NOCT. H. EXT</th><th>VACAC.</th><th>AJUSTE Y/O LIQUID.</th><th>RENDIM.</th><th>TOTAL A PAGAR</th></tr>';

        $.each(d.trabajadores, function(i, t) {
            html += '<tr><td>' + (i + 1) + '</td><td>' + escHtmlSC(t.ci) + '</td><td>' + escHtmlSC(t.nombre) + '</td>'
                + '<td style="text-align:right;">' + sinCuentaNum(t.salar_basico) + '</td>'
                + '<td style="text-align:right;">' + sinCuentaNum(t.noct_h_ext) + '</td>'
                + '<td style="text-align:right;">' + sinCuentaNum(t.vacac) + '</td>'
                + '<td style="text-align:right;">' + sinCuentaNum(t.ajuste_liquid) + '</td>'
                + '<td style="text-align:right;">' + sinCuentaNum(t.rendim) + '</td>'
                + '<td style="text-align:right;">' + sinCuentaNum(t.total_a_pagar) + '</td></tr>';
        });
        if (d.totales) {
            html += '<tr style="background:#eee;"><th colspan="3">TOTAL GENERAL</th>'
                + '<th style="text-align:right;">' + sinCuentaNum(d.totales.salar_basico) + '</th>'
                + '<th style="text-align:right;">' + sinCuentaNum(d.totales.noct_h_ext) + '</th>'
                + '<th style="text-align:right;">' + sinCuentaNum(d.totales.vacac) + '</th>'
                + '<th style="text-align:right;">' + sinCuentaNum(d.totales.ajuste_liquid) + '</th>'
                + '<th style="text-align:right;">' + sinCuentaNum(d.totales.rendim) + '</th>'
                + '<th style="text-align:right;">' + sinCuentaNum(d.totales.total_a_pagar) + '</th></tr>';
        }
        html += '</table>';

        html += '<table border="0" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;margin-top:3.4375rem;">'
            + '<tr>'
            + '<td style="text-align:center;width:33%;"><p><b>Generado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p>' + escHtmlSC(usuarioTxt) + '<br><span style="font-size:8pt;color:#444;">Usuario del sistema</span></td>'
            + '<td style="text-align:center;width:33%;"><p><b>Revisado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p><b>' + escHtmlSC(String(SIN_NOMINA_ESP_GESTION || especialistaGestion || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Especialista en Gestión Económica</span></td>'
            + '<td style="text-align:center;width:33%;"><p><b>Aprobado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p><b>' + escHtmlSC(String(SIN_NOMINA_JEFE || jefeProyecto || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Director de Proyecto</span></td>'
            + '</tr>'
            + '</table>'
            + '<p style="font-size:8pt;color:#666;text-align:center;margin-top:0.9375rem;">Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + escHtmlSC(usuarioTxt) + '</p>';

        if (formato === 'excel') {
            if (typeof ExcelJS === 'undefined') {
                Swal.fire({
                    icon: 'error', title: 'Error', text: 'La librería ExcelJS no está disponible.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido', confirmButtonColor: '#ef4444', background: '#1a1a2e', color: '#ffffff'
                });
                return;
            }
            function sinCuentaNumN(v) { var n = Number(v); return isNaN(n) ? 0 : n; }
            var wb = new ExcelJS.Workbook();
            wb.creator = nombreEmpresaTxt || 'TransNuBeT';
            wb.created = new Date();
            var nombreHoja = (cuenta === 'con') ? 'Resume_Con_Tarjeta' : ((cuenta === 'todos') ? 'Resume_Todos' : 'Resume_Sin_Tarjeta');
            var ws = wb.addWorksheet(nombreHoja, {
                pageSetup: { orientation: 'landscape', fitToPage: true, margins: { left: 0.7, right: 0.7, top: 0.7, bottom: 0.7, header: 0.3, footer: 0.3 } }
            });
            var colW = [6, 13, 32, 13, 12, 10, 13, 11, 14];
            for (var cw = 0; cw < colW.length; cw++) { ws.getColumn(cw + 1).width = colW[cw]; }
            var filaExcel = 1;
            function scCelda(fila, col, valor, opts) {
                var c = ws.getCell(fila, col);
                c.value = valor;
                opts = opts || {};
                if (opts.bold) c.font = { name: 'Arial', size: opts.size || 10, bold: true };
                else if (opts.size) c.font = { name: 'Arial', size: opts.size };
                if (opts.align) c.alignment = { horizontal: opts.align, vertical: 'middle' };
                return c;
            }
            scCelda(filaExcel, 1, tituloCabecera, { bold: true, size: 12, align: 'center' });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            scCelda(filaExcel, 1, String(nombreEmpresaTxt).toUpperCase(), { bold: true, size: 10, align: 'center' });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            scCelda(filaExcel, 1, 'Período: ' + (d.periodo_label || periodo), { bold: true, size: 10 });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            scCelda(filaExcel, 1, 'Estado: ' + (d.estado_label || estado) + '  |  Cuenta bancaria: ' + (d.cuenta_label || cuenta) + (d.trabajador_id && d.trabajador_label ? '  |  Trabajador: ' + String(d.trabajador_label).toUpperCase() : ''), { bold: true, size: 10 });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            scCelda(filaExcel, 1, 'Emisión: ' + fechaHora, { size: 10 });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            scCelda(filaExcel, 1, 'Generado por: ' + String(usuarioTxt || ''), { size: 10 });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            scCelda(filaExcel, 1, 'Total de trabajadores: ' + (d.registros || d.trabajadores.length), { size: 10 });
            ws.mergeCells(filaExcel, 1, filaExcel, 9);
            filaExcel++;
            filaExcel++;
            var filaHeader = filaExcel;
            var headers = ['No.', 'No CI.', 'Nombre y Apellidos', 'SALAR. BÁSICO', 'NOCT. H. EXT', 'VACAC.', 'AJUSTE Y/O LIQUID.', 'RENDIM.', 'TOTAL A PAGAR'];
            for (var hi = 0; hi < headers.length; hi++) {
                var hc = scCelda(filaHeader, hi + 1, headers[hi], { bold: true, size: 10, align: 'center' });
                hc.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF004B87' } };
                hc.font = { name: 'Arial', size: 10, bold: true, color: { argb: 'FFFFFFFF' } };
                hc.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
            }
            filaExcel++;
            $.each(d.trabajadores, function(i, t) {
                scCelda(filaExcel, 1, i + 1, { align: 'center' });
                scCelda(filaExcel, 2, String(t.ci || ''), { align: 'center' });
                scCelda(filaExcel, 3, String(t.nombre || ''));
                scCelda(filaExcel, 4, sinCuentaNumN(t.salar_basico), { align: 'right' }).numFmt = '$#,##0.00';
                scCelda(filaExcel, 5, sinCuentaNumN(t.noct_h_ext), { align: 'right' }).numFmt = '$#,##0.00';
                scCelda(filaExcel, 6, sinCuentaNumN(t.vacac), { align: 'right' }).numFmt = '$#,##0.00';
                scCelda(filaExcel, 7, sinCuentaNumN(t.ajuste_liquid), { align: 'right' }).numFmt = '$#,##0.00';
                scCelda(filaExcel, 8, sinCuentaNumN(t.rendim), { align: 'right' }).numFmt = '$#,##0.00';
                scCelda(filaExcel, 9, sinCuentaNumN(t.total_a_pagar), { bold: true, align: 'right' }).numFmt = '$#,##0.00';
                filaExcel++;
            });
            if (d.totales) {
                var fc = scCelda(filaExcel, 1, 'TOTAL GENERAL', { bold: true, align: 'left' });
                ws.mergeCells(filaExcel, 1, filaExcel, 3);
                for (var tci = 4; tci <= 8; tci++) {
                    var vals = [d.totales.salar_basico, d.totales.noct_h_ext, d.totales.vacac, d.totales.ajuste_liquid, d.totales.rendim];
                    scCelda(filaExcel, tci, sinCuentaNumN(vals[tci - 4]), { bold: true, align: 'right' }).numFmt = '$#,##0.00';
                }
                scCelda(filaExcel, 9, sinCuentaNumN(d.totales.total_a_pagar), { bold: true, align: 'right' }).numFmt = '$#,##0.00';
            }
            filaExcel++;
            filaExcel++;
            var firmasL = ['Generado por:', 'Revisado por:', 'Aprobado por:'];
            for (var fi = 0; fi < firmasL.length; fi++) { scCelda(filaExcel, fi * 3 + 1, firmasL[fi], { bold: true }); }
            filaExcel++;
            var firmasN = [String(usuarioTxt).toUpperCase(), String(SIN_NOMINA_ESP_GESTION || especialistaGestion || '').toUpperCase(), String(SIN_NOMINA_JEFE || jefeProyecto || '').toUpperCase()];
            for (var fi2 = 0; fi2 < firmasN.length; fi2++) { scCelda(filaExcel, fi2 * 3 + 1, firmasN[fi2], { bold: true }); }
            filaExcel++;
            var firmasC = ['Usuario del sistema', 'Especialista en Gestión Económica', 'Director de Proyecto'];
            for (var fi3 = 0; fi3 < firmasC.length; fi3++) { scCelda(filaExcel, fi3 * 3 + 1, firmasC[fi3]); }
            wb.xlsx.writeBuffer().then(function(buffer) {
                var blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = nombreArchivo;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(function() { URL.revokeObjectURL(url); }, 1500);
            });
        } else if (formato === 'word') {
            var htmlWord = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>' + titulo + '</title><!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]--></head><body>' + html + '</body></html>';
            sinCuentaDescargar(htmlWord, nombreArchivo, 'application/msword;charset=utf-8');
        }
    }

    $('#btnSinCuentaPDF').on('click', function() { exportarSinCuenta('pdf'); });
    $('#btnSinCuentaWord').on('click', function() { exportarSinCuenta('word'); });
    $('#btnSinCuentaExcel').on('click', function() { exportarSinCuenta('excel'); });
    $('#btnSinCuentaCSV').on('click', function() { exportarSinCuenta('csv'); });
    $('#btnSinCuentaTXT').on('click', function() { exportarSinCuenta('txt'); });
    $('#btnSinCuentaPrint').on('click', function() { imprimirSinCuenta(); });

    function imprimirSinCuenta() {
        var periodo = periodoSinCuentaActual();
        var estado = $('#sinCuentaEstado').val() || 'contabilizado';
        var cuenta = $('#sinCuentaCuenta').val() || 'sin';
        var trabajadorId = $('#sinCuentaTrabajadorId').val() ? parseInt($('#sinCuentaTrabajadorId').val()) : 0;
        var parametros = { accion: 'lista', periodo: periodo, estado: estado, cuenta: cuenta };
        if (trabajadorId) parametros.trabajador_id = trabajadorId;
        $.getJSON('exportar_sin_cuenta.php', parametros)
            .done(function(data) {
                if (!data.success || data.trabajadores.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: '<i class="fas fa-credit-card me-2" style="color:#fbbf24;"></i> Sin datos',
                        text: 'No hay trabajadores con nóminas en el período y estado seleccionados.',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        confirmButtonColor: '#3b82f6',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    return;
                }
                var filas = '';
                $.each(data.trabajadores, function(i, t) {
                    filas += '<tr>'
                        + '<td class="text-right">' + (i + 1) + '</td>'
                        + '<td>' + escHtmlSN(t.ci) + '</td>'
                        + '<td>' + escHtmlSN(t.nombre) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(t.salar_basico) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(t.noct_h_ext) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(t.vacac) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(t.ajuste_liquid) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(t.rendim) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(t.total_a_pagar) + '</td>'
                        + '</tr>';
                });
                var filaTotales = '';
                if (data.totales) {
                    filaTotales = '<tr class="sn-total-fila">'
                        + '<td colspan="3" class="text-right">TOTAL GENERAL</td>'
                        + '<td class="text-right">' + formatMoneySC(data.totales.salar_basico) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(data.totales.noct_h_ext) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(data.totales.vacac) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(data.totales.ajuste_liquid) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(data.totales.rendim) + '</td>'
                        + '<td class="text-right">' + formatMoneySC(data.totales.total_a_pagar) + '</td>'
                        + '</tr>';
                }
                var fechaHora = new Date().toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                var cuentaLbl = data.cuenta_label || 'Sin cuenta';
                var tituloPrint = (cuenta === 'con') ? 'RESUMEN CON TARJETA' : ((cuenta === 'todos') ? 'RESUMEN DE TRABAJADORES' : 'RESUMEN SIN TARJETA');
                var tituloPrintCabecera = tituloPrint + ' (' + escHtmlSN(data.periodo_label).toUpperCase().split(' ').join(' / ') + ')';
                var filtrosPrint = 'Estado: ' + escHtmlSN(data.estado_label) + '  |  Cuenta bancaria: ' + escHtmlSN(cuentaLbl) + (data.trabajador_id && data.trabajador_label ? '  |  Trabajador: ' + escHtmlSN(data.trabajador_label).toUpperCase() : '');
                var contenido = `
                    <table class="sn-cabecera" cellspacing="0" cellpadding="0">
                        <tr>
                            <td class="sn-cabecera-logo">${SIN_NOMINA_LOGO ? '<img src="' + SIN_NOMINA_LOGO + '" alt="Logo">' : ''}</td>
                            <td class="sn-cabecera-titulo">
                                <div class="sn-empresa">${escHtmlSN(SIN_NOMINA_EMPRESA)}</div>
                                <div class="sn-titulo">${tituloPrintCabecera}</div>
                                <div class="sn-alcance">${filtrosPrint}</div>
                            </td>
                            <td class="sn-cabecera-datos">
                                <strong>Emisión:</strong> ${fechaHora}<br>
                                <strong>Generado por:</strong> ${escHtmlSN(SIN_NOMINA_USUARIO)}<br>
                                <strong>Total de trabajadores:</strong> <span class="sn-total">${data.registros}</span>
                            </td>
                        </tr>
                    </table>
                    <table class="sn-tabla">
                        <thead><tr>
                            <th>No.</th><th>No CI.</th><th>Nombre y Apellidos</th><th>SALAR. BÁSICO</th><th>NOCT. H. EXT</th><th>VACAC.</th><th>AJUSTE Y/O LIQUID.</th><th>RENDIM.</th><th>TOTAL A PAGAR</th>
                        </tr></thead>
                        <tbody>${filas}</tbody>
                        ${filaTotales ? '<tfoot>' + filaTotales + '</tfoot>' : ''}
                    </table>
                    <div class="sn-firmas">
                        <table class="sn-firmas-tabla">
                            <tr>
                                <td>
                                    <p class="sn-firma-label">Generado por:</p>
                                    <p class="sn-firma-linea"></p>
                                    <p class="sn-firma-cargo">${escHtmlSN(SIN_NOMINA_USUARIO)}</p>
                                    <p class="sn-firma-subcargo">Usuario del sistema</p>
                                </td>
                                <td>
                                    <p class="sn-firma-label">Revisado por:</p>
                                    <p class="sn-firma-linea"></p>
                                    <p class="sn-firma-cargo">${escHtmlSN(SIN_NOMINA_ESP_GESTION)}</p>
                                    <p class="sn-firma-subcargo">Especialista en Gestión Económica</p>
                                </td>
                                <td>
                                    <p class="sn-firma-label">Aprobado por:</p>
                                    <p class="sn-firma-linea"></p>
                                    <p class="sn-firma-cargo">${escHtmlSN(SIN_NOMINA_JEFE)}</p>
                                    <p class="sn-firma-subcargo">Director de Proyecto</p>
                                </td>
                            </tr>
                        </table>
                    </div>`;
                var win = window.open('', '_blank');
                if (!win) {
                    Swal.fire({
                        title: '<i class="fas fa-external-link-alt me-2" style="color:#fbbf24;"></i> Permiso requerido',
                        html: '<div class="text-center"><p>El navegador bloqueó la ventana emergente.</p><p class="text-muted small">Permita las ventanas emergentes para este sitio e inténtelo de nuevo.</p></div>',
                        icon: 'warning',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        confirmButtonColor: '#3b82f6',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    return;
                }
                win.document.open();
                win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Resumen Sin Tarjeta</title><style>' + SIN_NOMINA_PRINT_CSS + '</style></head><body>' + PRINT_TOOLBAR_HTML + contenido + '</body></html>');
                win.document.close();
            })
            .fail(function() {
                Swal.fire({
                    icon: 'error',
                    title: '<i class="fas fa-wifi text-danger me-2"></i> Error de conexión',
                    text: 'No se pudo consultar el servidor.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    confirmButtonColor: '#dc3545',
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
            });
    }
    // ==================== FIN RESUMEN SIN TARJETA ====================

function aplicarFiltros() {
    if (!nominasTable) return;
    
    var filtroTrabajador = $('#filtroTrabajador').val();
    var filtroArea = $('#filtroArea').val();
    var filtroCentro = $('#filtroCentroCosto').val();
    var filtroCuenta = $('#filtroCuenta').val();
    var filtroAcumulaVac = $('#filtroAcumulaVacaciones').val();
    var filtroRangoVac = $('#filtroRangoVacaciones').val();
    var filtroNumeroNomina = $('#filtroNumeroNomina').val();
    
    if ($('#filtroRangoVacaciones').prop('disabled')) {
        filtroRangoVac = '';
    }
    
    if ($('#filtroAcumulaVacaciones').val() === 'no' && 
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled')) {
        filtroAcumulaVac = '';
    }
    
    $.fn.dataTable.ext.search.pop();
    
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row = nominasTable.row(dataIndex).node();
        var $row = $(row);
        
        var okTrab = !filtroTrabajador || ($row.data('trabajador-id') == filtroTrabajador);
        var okArea = !filtroArea || ($row.data('area-id') == filtroArea);
        var okCentro = !filtroCentro || ($row.data('centro-costo-id') == filtroCentro);
        var okCuenta = !filtroCuenta || ($row.data('tiene-cuenta') === filtroCuenta);
        var okNumero = !filtroNumeroNomina || ($row.data('numero-nomina') === filtroNumeroNomina);
        
        var okAcumulaVac = true;
        if (filtroAcumulaVac) {
            var noAcumula = parseInt($row.data('no-acumular-vacaciones')) || 0;
            okAcumulaVac = (filtroAcumulaVac === 'si' && noAcumula === 0) ||
                           (filtroAcumulaVac === 'no' && noAcumula === 1);
        }
        
        var okRangoVac = true;
        if (filtroRangoVac && !$('#filtroRangoVacaciones').prop('disabled')) {
            var diasAcum = parseFloat($row.data('dias-acumulados')) || 0;
            switch(filtroRangoVac) {
                case '0-5': okRangoVac = (diasAcum >= 0 && diasAcum <= 5); break;
                case '5-10': okRangoVac = (diasAcum > 5 && diasAcum <= 10); break;
                case '10-15': okRangoVac = (diasAcum > 10 && diasAcum <= 15); break;
                case '15-20': okRangoVac = (diasAcum > 15 && diasAcum <= 20); break;
                case '20-100': okRangoVac = (diasAcum > 20); break;
                default: okRangoVac = true;
            }
        }
        
        return okTrab && okArea && okCentro && okCuenta && okAcumulaVac && okRangoVac && okNumero;
    });
    
    nominasTable.draw();
    actualizarEstadoVista();
}

// 🔽 NUEVO: Actualiza el título del card según la nómina filtrada (No. concreto o "(TODAS)").
function actualizarTituloCard() {
    var $spanTitulo = $('#tituloCardNumeroNomina');
    if ($spanTitulo.length === 0) return;
    var filtroNum = $('#filtroNumeroNomina').val();
    if (filtroNum && filtroNum !== 'Borrador') {
        $spanTitulo.html('No.: <span class="text-warning">' + $('<div>').text(filtroNum).html() + '</span>');
    } else if (filtroNum === 'Borrador') {
        $spanTitulo.html('<span class="text-info">(Borrador)</span>');
    } else {
        $spanTitulo.html('<span class="text-warning">(TODAS)</span>');
    }
}

// 🔽 NUEVO: Actualiza dinámicamente botones/badge/observaciones según la nómina de ajuste filtrada.
function actualizarEstadoVista() {
    if (!nominasTable) return;

    // El título dinámico solo aplica a la nómina de ajuste (múltiples números por período)
    if (window.tipoNomina === 'ajuste') {
        actualizarTituloCard();
    }

    if (window.tipoNomina !== 'ajuste') return;

    var $btnBorrador = $('#accionesBorradorAjuste');
    var $badge = $('#badgeContabilizadaAjuste');
    var $alerta = $('#alertaObservacionesCierre');
    var $textoObs = $('#textoObservacionesCierre');
    var $btnEliminarTodoAjuste = $('#btnEliminarTodoAjuste');
    var $revertirAjuste = $('#accionesRevertirAjuste');
    if ($btnBorrador.length === 0 || $badge.length === 0) return;

    var filtroNum = $('#filtroNumeroNomina').val();

    if (filtroNum && filtroNum !== 'Borrador') {
        // Nómina de ajuste específica y contabilizada
        $btnBorrador.hide();
        $badge.show();
        if ($btnEliminarTodoAjuste.length) $btnEliminarTodoAjuste.hide();
        if ($revertirAjuste.length) {
            $revertirAjuste.css('display', 'flex').show();
            $('#revertirBtnAjuste').data('numero', filtroNum);
        }
        var obs = (observacionesPorNomina && observacionesPorNomina[filtroNum] !== undefined)
            ? observacionesPorNomina[filtroNum]
            : observacionesCierreGlobal;
        $textoObs.text(obs || '');
        if (obs) { $alerta.show(); } else { $alerta.hide(); }
        return;
    }

    // "Todos" o "Borrador": alternar según filas visibles
    var filasVisibles = $(nominasTable.rows({ search: 'applied' }).nodes());
    var hayBorradorVisible = false;
    var hayContabilizadaVisible = false;
    var numerosVisibles = {};
    filasVisibles.each(function() {
        var $row = $(this);
        if ($row.find('.badge-borrador').length) hayBorradorVisible = true;
        else hayContabilizadaVisible = true;
        var num = $row.find('.col-numero-nomina').text().trim();
        if (num && num !== 'Borrador') numerosVisibles[num] = true;
    });

    if (hayBorradorVisible) {
        $btnBorrador.show();
        $badge.hide();
    } else {
        $btnBorrador.hide();
        $badge.show();
    }
    if ($revertirAjuste.length) $revertirAjuste.hide();

    // "Eliminar Todo" solo si hay filas visibles y TODAS son Borrador
    if ($btnEliminarTodoAjuste.length) {
        if (filasVisibles.length > 0 && hayBorradorVisible && !hayContabilizadaVisible) {
            $btnEliminarTodoAjuste.show();
        } else {
            $btnEliminarTodoAjuste.hide();
        }
    }

    // Observaciones: si hay exactamente una nómina visible (sin borradores), mostrar la suya
    var claves = Object.keys(numerosVisibles);
    var obsDefault = observacionesCierreGlobalOriginal || '';
    if (!hayBorradorVisible && claves.length === 1 && observacionesPorNomina &&
        observacionesPorNomina[claves[0]] !== undefined && observacionesPorNomina[claves[0]] !== '') {
        obsDefault = observacionesPorNomina[claves[0]];
    }
    $textoObs.text(obsDefault);
    if (obsDefault) { $alerta.show(); } else { $alerta.hide(); }
}
    $('#filtroTrabajador, #filtroArea, #filtroCentroCosto, #filtroNumeroNomina').on('change', aplicarFiltros);

    $('#nomFiltrosToggle').on('click', function() {
        var $body = $('#nomFiltrosBody');
        var $chevron = $('#nomFiltrosChevron');
        if ($body.is(':visible')) {
            $body.slideUp(200);
            $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            $body.slideDown(200);
            $chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });

$('#btnLimpiarFiltros').off('click').on('click', function(e) {
    e.preventDefault();
    
    $('#filtroTrabajador, #filtroArea, #filtroCentroCosto, #filtroCuenta, #filtroAcumulaVacaciones, #filtroRangoVacaciones, #filtroNumeroNomina').val('');
    $('#filtroTrabajador').trigger('change.select2');
    
    $('#filtroRangoVacaciones').prop('disabled', false);
    $('#filtroRangoVacaciones').attr('title', 'Filtrar por rango de días acumulados');
    $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', false);
    $('#filtroAcumulaVacaciones').find('option[value="no"]').css('color', '');
    
    if ($.fn.DataTable.isDataTable('#tablaNominas')) {
        var table = $('#tablaNominas').DataTable();
        table.search('').draw();
        $('#customSearchInput').val('');
    }
    
    $.fn.dataTable.ext.search.pop();
    
    var url = new URL(window.location.href);
    if (url.searchParams.has('filtro_cuenta')) {
        url.searchParams.delete('filtro_cuenta');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
    
    if (nominasTable) nominasTable.draw();
    actualizarEstadoVista();
    
    Swal.fire({
        icon: 'success',
        title: 'Filtros eliminados',
        text: 'Se han restablecido todos los filtros de búsqueda.',
        timer: 1500,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#fff'
    });
});

	function parseNumber(value) {
		if (!value || value === '-' || value === '') return 0;
		// Remover $, comas y cualquier caracter no numérico excepto punto y negativo
		var num = parseFloat(value.toString().replace(/[^0-9.-]/g, ''));
		if (isNaN(num)) return 0;
		return Math.max(0, num);
	}
    function formatNumber(num) { return num.toFixed(2); }

    function obtenerEscalaRomana(num) {
        if (!num || num === '?' || num === 'S/D') return 'S/D';
        var val = parseInt(num);
        if (isNaN(val)) return num; 
        var romanos = {
            1:'I', 2:'II', 3:'III', 4:'IV', 5:'V', 6:'VI', 7:'VII', 8:'VIII', 9:'IX', 10:'X', 
            11:'XI', 12:'XII', 13:'XIII', 14:'XIV', 15:'XV', 16:'XVI', 17:'XVII', 18:'XVIII', 19:'XIX', 20:'XX'
        };
        return romanos[val] || num;
    }

    function calcularCessProgresivoJS(salario) {
        var limite = 15000;
        if (salario <= limite) {
            return Math.roundExcel((salario * 0.05) * 100) / 100;
        } else {
            return Math.roundExcel(((15000 * 0.05) + ((salario - 15000) * 0.10)) * 100) / 100;
        }
    }

    function calcularImpuestoProgresivo(salario) {
        if (!rangosImpuesto || salario <= 0) return 0;
        var total = 0;
        for (var i = 0; i < rangosImpuesto.length; i++) {
            var desde = parseFloat(rangosImpuesto[i].desde);
            var hasta = rangosImpuesto[i].hasta ? parseFloat(rangosImpuesto[i].hasta) : Infinity;
            var tasa = parseFloat(rangosImpuesto[i].tasa);
            if (tasa > 0 && salario > desde) total += Math.min(salario - desde, hasta - desde) * tasa;
        }
        return Math.roundExcel(total * 100) / 100;
    }
    
    function calcularImpuestosPorRangoProgresivo(salario) {
        var impuestos = [];
        for (var i = 0; i < rangosImpuesto.length; i++) {
            var desde = parseFloat(rangosImpuesto[i].desde);
            var hasta = rangosImpuesto[i].hasta ? parseFloat(rangosImpuesto[i].hasta) : Infinity;
            var tasa = parseFloat(rangosImpuesto[i].tasa);
            var imp = (tasa > 0 && salario > desde) ? Math.min(salario - desde, hasta - desde) * tasa : 0;
            impuestos.push(Math.roundExcel(imp * 100) / 100);
        }
        return impuestos;
    }

function recalcularFilaVacaciones(fila) {
    var salarioMensual = parseFloat(fila.data('salario-mensual')) || 0;
    var diasActuales = Math.max(0, parseFloat(fila.find('.edit-dias').val()) || 0);
    var diasAcumuladosOriginal = Math.max(0, parseFloat(fila.data('dias-acumulados')) || 0);
    var tipoDescuento = fila.data('tipo-descuento') || 'total_rangos';
    var descuentos = Math.max(0, parseNumber(fila.find('.edit-descuentos').val()));
    
    if (diasActuales < 0) {
        diasActuales = 0;
        fila.find('.edit-dias').val(0);
    }
    
    var diasDisponibles = diasAcumuladosOriginal;
    if (diasActuales > diasDisponibles) {
        Swal.fire({
            title: 'Advertencia',
            text: 'No se pueden asignar más días de los disponibles. Días disponibles: ' + diasDisponibles.toFixed(2),
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        fila.find('.edit-dias').val(diasDisponibles.toFixed(2));
        diasActuales = diasDisponibles;
    }
    
    var valorPorDia = salarioMensual / diasLaborables;
    var importe = diasActuales * valorPorDia;
    var diasRestantes = diasAcumuladosOriginal - diasActuales;
    if (diasRestantes < 0) diasRestantes = 0;
    
    var contribucion = 0;
    var impuesto = 0;
    var impuestosArr = [];

    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(importe);
        impuesto = 0;
        for (var i = 0; i < rangosImpuesto.length; i++) {
            impuestosArr.push(0);
        }
    } else {
        contribucion = Math.roundExcel((importe * 0.05) * 100) / 100;
        impuesto = calcularImpuestoProgresivo(importe);
        impuestosArr = calcularImpuestosPorRangoProgresivo(importe);
    }

    var netoAntesDesc = importe - (contribucion + impuesto);
    if (netoAntesDesc < 0) netoAntesDesc = 0;
    descuentos = validarDescuentosConCESS(importe, contribucion, impuesto, descuentos);
    
    var neto = Math.max(0, Math.roundExcel((importe - (contribucion + impuesto + descuentos)) * 100) / 100);
    
    fila.find('.total-devengado').text('$' + formatNumber(importe));
    fila.find('.contribucion').text('$' + formatNumber(contribucion));
    fila.find('.total-deducciones').text('$' + formatNumber(contribucion + impuesto + descuentos));
    
    for (var i = 0; i < impuestosArr.length; i++) {
        fila.find('.impuesto-rango-' + i).text('$' + formatNumber(impuestosArr[i]));
    }
    
    fila.find('.neto').text('$' + formatNumber(neto));
    fila.find('.dias-restantes span').text(diasRestantes.toFixed(2));
    
    var restantesColor = '#10b981';
    if (diasRestantes <= 0) restantesColor = '#ef4444';
    else if (diasRestantes <= 5) restantesColor = '#f59e0b';
    fila.find('.dias-restantes span').css('color', restantesColor);
    
    actualizarEstadisticas();
}

function recalcularFilaAutomatica(fila) {
    var salarioHora = parseFloat(fila.data('salario-hora')) || 0;
    var salarioMensual = parseFloat(fila.data('salario-mensual')) || 0;
    var tipoDescuento = fila.data('tipo-descuento') || 'total_rangos';
    var noAcumularVacaciones = parseInt(fila.data('no-acumular-vacaciones')) || 0;
    var tipoNominaActual = tipoNomina; 

    var horas = Math.max(0, parseNumber(fila.find('.edit-horas').val()));
    var descuentos = Math.max(0, parseNumber(fila.find('.edit-descuentos').val()));
    var diasFeriados = 0, otrosPagos = 0, noctT = 0, noctD = 0, dt = 0;
    
    if (tipoNominaActual === 'automatica') {
        diasFeriados = Math.max(0, parseNumber(fila.find('.edit-feriados').val()));
        otrosPagos = Math.max(0, parseNumber(fila.find('.edit-otros-pagos').val()));
    } else {
        noctT = Math.max(0, parseNumber(fila.find('.edit-noct-temprana').val()));
        noctD = Math.max(0, parseNumber(fila.find('.edit-noct-tardia').val()));
        dt = Math.max(0, parseNumber(fila.find('.edit-doble-turno').val()));
    }
    
    if (parseFloat(fila.find('.edit-horas').val()) < 0) fila.find('.edit-horas').val(horas);
    if (tipoNominaActual === 'automatica') {
        if (parseFloat(fila.find('.edit-feriados').val()) < 0) fila.find('.edit-feriados').val(diasFeriados);
        if (parseFloat(fila.find('.edit-otros-pagos').val()) < 0) fila.find('.edit-otros-pagos').val(otrosPagos);
    } else {
        if (parseFloat(fila.find('.edit-noct-temprana').val()) < 0) fila.find('.edit-noct-temprana').val(noctT);
        if (parseFloat(fila.find('.edit-noct-tardia').val()) < 0) fila.find('.edit-noct-tardia').val(noctD);
        if (parseFloat(fila.find('.edit-doble-turno').val()) < 0) fila.find('.edit-doble-turno').val(dt);
    }
    if (parseFloat(fila.find('.edit-descuentos').val()) < 0) fila.find('.edit-descuentos').val(descuentos);
    
    var salarioDiario = salarioMensual / 24;
    var importeFeriados = 0, importeHE = 0, importeNtT = 0, importeNtD = 0, importeDT = 0;
    
    if (tipoNominaActual === 'automatica') {
        importeFeriados = salarioDiario * diasFeriados * 2;
    } else {
        importeHE = (salarioHora * recargoExtraDiurna) * horas;
        importeNtT = (salarioHora * recargoExtraNocturna) * noctT;
        importeNtD = (salarioHora * recargoExtraNocturna) * noctD;
        importeDT = (salarioHora * recargoDobleturno) * dt;
    }

    var factor909 = 0.0909; 
    var horasJornadaDB = 8;
    var diasProporcional = Math.roundExcel(((horas * factor909) / horasJornadaDB), 2);
    var importeVacacionesMes = Math.roundExcel(diasProporcional * salarioDiario, 2);

    var importeVacacionesAdicional = 0;
    var diasAcumuladosHistoricos = parseFloat(fila.data('dias-acumulados-value')) || 0;
    
    if (tipoNominaActual === 'automatica') {
        if (noAcumularVacaciones === 1) {
            importeVacacionesAdicional = importeVacacionesMes;
            fila.find('.vacaciones-dias').text(diasProporcional.toFixed(2));
            fila.find('.vacations-importe').text('$' + importeVacacionesMes.toFixed(2));
        } else {
            var totalDiasVis = diasAcumuladosHistoricos + diasProporcional;
            var totalImpVis = totalDiasVis * salarioDiario;
            fila.find('.vacaciones-dias').text(totalDiasVis.toFixed(2));
            fila.find('.vacations-importe').text('$' + totalImpVis.toFixed(2));
            fila.attr('data-dias-acumulados', totalDiasVis);
        }
    }

    var totalDevengado;
    if (tipoNominaActual === 'extraordinaria') {
        totalDevengado = importeHE + importeNtT + importeNtD + importeDT;
    } else {
        var salarioLaboral = salarioHora * horas;
        totalDevengado = salarioLaboral + importeFeriados + otrosPagos + importeVacacionesAdicional;
    }
    
    var contribucion = 0;
    var impuestoTotal = 0;
    var impuestosArr = [];

    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(totalDevengado);
        impuestoTotal = 0;
        for (var i = 0; i < rangesImpuestosLength(); i++) { impuestosArr.push(0); }
    } else {
        contribucion = totalDevengado * 0.05;
        impuestoTotal = calcularImpuestoProgresivo(totalDevengado);
        impuestosArr = calcularImpuestosPorRangoProgresivo(totalDevengado);
    }

    descuentos = validarDescuentosConCESS(totalDevengado, contribucion, impuestoTotal, descuentos);
    fila.find('.edit-descuentos').val(formatNumber(descuentos));
    
    var netoFinal = Math.max(0, totalDevengado - (contribucion + impuestoTotal + descuentos));
    
    if (tipoNominaActual === 'extraordinaria') {
        var salarioLaboralHE = importeHE + importeDT;
        fila.find('.salario-laboral').text('$' + formatNumber(salarioLaboralHE));
        fila.find('.salario-hora-real').text('$' + formatNumber(horas > 0 ? salarioLaboralHE/horas : 0));
    } else {
        var salarioLaboral = salarioHora * horas;
        fila.find('.salario-laboral').text('$' + formatNumber(salarioLaboral));
        fila.find('.salario-hora-real').text('$' + formatNumber(horas > 0 ? salarioLaboral/horas : 0));
    }

    if (tipoNominaActual === 'automatica') {
        fila.find('.feriados-importe').text('$' + formatNumber(importeFeriados));
    }

    fila.find('.total-devengado').text('$' + formatNumber(totalDevengado));
    fila.find('.contribucion').text('$' + formatNumber(contribucion));
    fila.find('.total-deducciones').text('$' + formatNumber(contribucion + impuestoTotal + descuentos));
    
    for (var i = 0; i < impuestosArr.length; i++) {
        fila.find('.impuesto-rango-' + i).text('$' + formatNumber(impuestosArr[i]));
    }
    
    fila.find('.neto').text('$' + formatNumber(netoFinal));
    
    actualizarEstadisticas();
}

    function rangesImpuestosLength() {
        return rangosImpuesto ? rangosImpuesto.length : 0;
    }



function recalcularFilaBono(fila) {
    var tipoDescuento = fila.data('tipo-descuento') || 'total_rangos';
    var val = parseNumber(fila.find('.edit-bono').val());
    var descuentos = parseNumber(fila.find('.edit-descuentos').val());
    var otrosPagos = fila.find('.edit-otros-pagos').length ? parseNumber(fila.find('.edit-otros-pagos').val()) : 0;
    var total = val + otrosPagos;

    if (val < 0) val = 0;
    if (descuentos < 0) descuentos = 0;

    var contribucion = 0;
    var impuesto = 0;
    var impuestosArr = [];

    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(total);
        impuesto = 0;
        for (var i = 0; i < rangesImpuestosLength(); i++) {
            impuestosArr.push(0);
        }
    } else {
        contribucion = Math.roundExcel((total * 0.05) * 100) / 100;
        impuesto = calcularImpuestoProgresivo(total);
        impuestosArr = calcularImpuestosPorRangoProgresivo(total);
    }

    descuentos = validarDescuentosConCESS(total, contribucion, impuesto, descuentos);

    var netoFinal = Math.max(0, total - (contribucion + impuesto + descuentos));

    fila.find('.edit-descuentos').val(formatNumber(descuentos));

    var $descuentosInput = fila.find('.edit-descuentos');
    if (netoFinal <= 0) {
        $descuentosInput.prop('disabled', true);
        $descuentosInput.css('opacity', '0.6');
    } else {
        $descuentosInput.prop('disabled', false);
        $descuentosInput.css('opacity', '1');
    }

    fila.find('.total-devengado').text('$' + formatNumber(total));
    fila.find('.contribucion').text('$' + formatNumber(contribucion));
    fila.find('.total-deducciones').text('$' + formatNumber(contribucion + impuesto + descuentos));
    
    for (var i = 0; i < impuestosArr.length; i++) {
        fila.find('.impuesto-rango-' + i).text('$' + formatNumber(impuestosArr[i]));
    }
    fila.find('.neto').text('$' + formatNumber(netoFinal));

    actualizarEstadisticas();
}

$(document).on('input', '.edit-horas, .edit-feriados, .edit-descuentos, .edit-otros-pagos, .edit-bono, .edit-dias, .edit-noct-temprana, .edit-noct-tardia, .edit-doble-turno', function() {
    if (contabilizada) return;
    
    var $input = $(this);
    var valor = parseNumber($input.val());
    
    if (valor < 0 || isNaN(valor)) {
        valor = 0;
        $input.val('0');
    }
    
    var fila = $(this).closest('tr');
    
    // ==========================================
    // ✅ VALIDACIÓN UNIVERSAL DE CAMPOS CLAVE EN CERO
    // ==========================================
    var esCampoClave = false;
    var selectorCampo = getSelectorCampo(tipoNomina);
    
    // Verificar si el input que cambió es el campo clave de esta nómina
    if ($input.is(selectorCampo)) {
        esCampoClave = true;
    }
    
    // Recalcular según tipo
    if (tipoNomina == 'automatica' || tipoNomina == 'extraordinaria') {
        recalcularFilaAutomatica(fila);
    } else if (tipoNomina == 'bono') {
        recalcularFilaBono(fila);
    } else if (tipoNomina == 'vacaciones') {
        recalcularFilaVacaciones(fila);
    } else if (tipoNomina == 'ajuste') {
        recalcularFilaBono(fila);
    }
    
    // ✅ Si es el campo clave, validar después del recalculo
    if (esCampoClave) {
        setTimeout(function() {
            validarCamposCero(fila);
        }, 300);
    }
});

$(document).on('click', '.guardar-fila', function() {
    if (contabilizada) {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-lock me-2" style="color: #f59e0b;"></i> Nómina Contabilizada',
            html: '<p>No se pueden guardar cambios en una nómina contabilizada.</p>',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#1E1E1E',
            color: '#FFFFFF',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }
    
    var fila = $(this).closest('tr');
    var trabajadorNombre = fila.find('td:eq(2)').text().trim();
    var id = fila.data('id');
    
    // ==========================================
    // ✅ VALIDACIÓN EXTRAORDINARIA: TODO EN CERO
    // ==========================================
    if (tipoNomina === 'extraordinaria') {
        var hrsExt = parseNumber(fila.find('.edit-horas').val());
        var ntTExt = parseNumber(fila.find('.edit-noct-temprana').val());
        var ntDExt = parseNumber(fila.find('.edit-noct-tardia').val());
        var dtExt = parseNumber(fila.find('.edit-doble-turno').val());
        var dscExt = parseNumber(fila.find('.edit-descuentos').val());
        if (hrsExt === 0 && ntTExt === 0 && ntDExt === 0 && dtExt === 0 && dscExt === 0) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Nómina Extraordinaria en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-clock fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(trabajadorNombre)}</strong> tiene <span class="text-danger fw-bold">todos los valores en cero</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador sin al menos un valor (HE Diurnas, Nt 7-23h, Nt 23-7h, Doble Turno o Descuentos).</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar valores',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        fila.find('.edit-horas').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, trabajadorNombre);
                }
            });
            return;
        }
    }
    
    // ==========================================
    // ✅ VALIDACIÓN UNIVERSAL ANTES DE GUARDAR
    // ==========================================
    var campoValor = 0;
    var selectorCampo = getSelectorCampo(tipoNomina);
    var nombreCampo = getNombreCampo(tipoNomina);
    
    if (selectorCampo) {
        var $campoInput = fila.find(selectorCampo);
        campoValor = $campoInput.length ? parseNumber($campoInput.val()) : 0;
    }
    
    // Si el campo clave está en cero, mostrar advertencia
    if (tipoNomina !== 'extraordinaria' && (campoValor === 0 || campoValor < 0.5)) {
        Swal.fire({
            title: `<i class="fas fa-exclamation-triangle text-warning me-2"></i> ${nombreCampo.charAt(0).toUpperCase() + nombreCampo.slice(1)} en Cero`,
            html: `
                <div class="text-center">
                    <i class="fas ${getIconoCampo(tipoNomina)} fa-3x mb-3" style="color: #f59e0b;"></i>
                    <p>El trabajador <strong>${escapeHtml(trabajadorNombre)}</strong> tiene <span class="text-danger fw-bold">0 ${nombreCampo}</span>.</p>
                    <p class="text-muted small">No se puede guardar un trabajador con 0 ${nombreCampo} en la nómina.</p>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: `<i class="fas fa-pen me-2"></i>Asignar ${nombreCampo}`,
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
            cancelButtonColor: '#ef4444',
            background: '#1a1a2e',
            color: '#ffffff'
        }).then((result) => {
            if (result.isConfirmed) {
                // Asignar valor: enfocar el input
                setTimeout(function() {
                    var $input = fila.find(selectorCampo);
                    if ($input.length) {
                        $input.focus().select();
                    }
                }, 200);
            } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                // Eliminar trabajador
                eliminarTrabajadorPorId(id, trabajadorNombre);
            }
        });
        return;
    }
    
    // Continuar con el guardado normal...
    var datos = { actualizar_nomina: 1, id: id, tipo_nomina: tipoNomina };
        
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...',
            html: `Guardando cambios de <strong>${trabajadorNombre}</strong>`,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        
        if (tipoNomina == 'automatica' || tipoNomina == 'extraordinaria') {
            datos.horas_laboradas = parseNumber(fila.find('.edit-horas').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
            
            if (tipoNomina == 'automatica') {
                datos.dias_feriados = parseNumber(fila.find('.edit-feriados').val());
                datos.otros_salarios = parseNumber(fila.find('.edit-otros-pagos').val());
                datos.horas_nocturnas = 0;
            } else {
                datos.nocturnidad_temprana = parseNumber(fila.find('.edit-noct-temprana').val());
                datos.nocturnidad_tardia = parseNumber(fila.find('.edit-noct-tardia').val());
                datos.doble_turno = parseNumber(fila.find('.edit-doble-turno').val());
                datos.dias_feriados = 0;
                datos.otros_salarios = 0;
            }
        } else if (tipoNomina == 'bono') {
            datos.monto_bono = parseNumber(fila.find('.edit-bono').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
            var conceptoBono = fila.find('.col-nombre').eq(1).text().trim();
            datos.descripcion = (conceptoBono === '-' || conceptoBono === '') ? '' : conceptoBono;
        } else if (tipoNomina == 'ajuste') {
            datos.monto_bono = parseNumber(fila.find('.edit-bono').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
            datos.otros_salarios = parseNumber(fila.find('.edit-otros-pagos').val());
            var conceptoAjuste = fila.find('.col-nombre').eq(1).text().trim();
            datos.descripcion = (conceptoAjuste === '-' || conceptoAjuste === '') ? '' : conceptoAjuste;
            var horasAjusteActual = parseFloat(fila.data('horas-ajuste') || 0);
            if (horasAjusteActual > 0) {
                datos.horas_laboradas = horasAjusteActual;
            }
        } else if (tipoNomina == 'vacaciones') {
            datos.dias_vacaciones = parseNumber(fila.find('.edit-dias').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
        }
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            dataType: 'json',
            data: datos,
            success: function(r) {
                if (r.success) {
                    var resumenHtml = '';
                    if (tipoNomina == 'automatica' || tipoNomina == 'extraordinaria') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Horas:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.horas_laboradas}</strong></div>
                                </div>
                                ${tipoNomina == 'automatica' ? `
                                <div class="row">
                                    <div class="col-6 text-start"><small>Días feriados:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.dias_feriados}</strong></div>
                                </div>
                                ` : `
                                <div class="row">
                                    <div class="col-6 text-start"><small>Nt 7-23h:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.nocturnidad_temprana || 0}h</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Nt 23-7h:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.nocturnidad_tardia || 0}h</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Doble Turno:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.doble_turno || 0}h</strong></div>
                                </div>
                                `}
                                <div class="row">
                                    <div class="col-6 text-start"><small>Descuentos:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.descuentos.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    } else if (tipoNomina == 'bono') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Monto Bono:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.monto_bono.toFixed(2)}</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Descuentos:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.descuentos.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    } else if (tipoNomina == 'ajuste') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Monto Ajuste:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.monto_bono.toFixed(2)}</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Otros Pagos:</small></div>
                                    <div class="col-6 text-end"><strong>$${((datos.otros_salarios) || 0).toFixed(2)}</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Descuentos:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.descuentos.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    } else if (tipoNomina == 'vacaciones') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Días tomados:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.dias_vacaciones.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: '<i class="fas fa-check-circle me-2" style="color: 1;"></i> Guardado exitoso',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-user-check fa-3x mb-3" style="color: 1;"></i>
                                <p class="mb-1"><strong>${trabajadorNombre}</strong></p>
                                <p class="text-muted small">Los cambios han sido guardados correctamente</p>
                                ${resumenHtml}
                            </div>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: '#1E1E1E',
                        color: '#FFFFFF',
                        confirmButtonColor: '#10b981',
                        timer: 2500,
                        timerProgressBar: true
                    });
                    
                    actualizarEstadisticas();
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i> Error al guardar',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-times-circle fa-3x mb-3" style="color: #ef4444;"></i>
                                <p><strong>${trabajadorNombre}</strong></p>
                                <p class="text-danger">${r.error || 'No se pudo guardar el registro'}</p>
                            </div>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        background: '#1E1E1E',
                        color: '#FFFFFF',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: '<i class="fas fa-plug me-2" style="color: #ef4444;"></i> Error de conexión',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-wifi fa-3x mb-3" style="color: #ef4444;"></i>
                            <p><strong>${trabajadorNombre}</strong></p>
                            <p class="text-danger">No se pudo conectar con el servidor</p>
                            <p class="text-muted small">Error: ${error}</p>
                        </div>
                    `,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    background: '#1E1E1E',
                    color: '#FFFFFF',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    });

    $('#consultarBtn').on('click', function() {
        var periodo = $('#anioSelect').val() + '-' + $('#mesSelect').val();
        var url = 'nominas.php?periodo=' + periodo + '&tipo=' + tipoNomina;
        if (filtroCuentaActual) url += '&filtro_cuenta=' + filtroCuentaActual;

        $.ajax({
            url: '../ajax/verificar_nomina.php',
            data: { periodo: periodo, tipo: tipoNomina },
            method: 'GET',
            dataType: 'json'
        }).done(function(data) {
            if (data.success && data.existe) {
                window.location.href = url;
                return;
            }
            if (!data.success) {
                window.location.href = url;
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: '<i class="fas fa-search me-2" style="color: #f59e0b;"></i>No existe nómina',
                html: 'No existe nómina de tipo <strong>' + tipoNominaTexto + '</strong> para el período <strong>' + periodo + '</strong>.',
                showCancelButton: puedeCrearNomina,
                confirmButtonColor: puedeCrearNomina ? '#0ea5e9' : '#6b7280',
                confirmButtonText: puedeCrearNomina ? '<i class="fas fa-plus-circle me-2"></i>Crear Nueva' : '<i class="fas fa-check me-2"></i>Entendido',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: '#1a1a2e',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed && puedeCrearNomina) window.location.href = url + '&abrir_descuento=1';
            });
        }).fail(function() {
            window.location.href = url;
        });
    });

    $('#btnRegenerarNomina').on('click', function() {
        Swal.fire({
            title: '¿Regenerar nómina?', text: 'Se perderán los cambios manuales no guardados.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#0ea5e9', confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Regenerar', cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar', background: '#1a1a2e', color: 'white'
        }).then((r) => { if (r.isConfirmed) $('#formRegenerarNomina').submit(); });
    });
    
        $('#eliminarTodoBtn').on('click', function() {
        Swal.fire({
            title: '¿Eliminar toda la nómina?', text: 'Acción irreversible.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar', cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar', background: '#1a1a2e', color: 'white'
        }).then((r) => {
            if (r.isConfirmed) {
                $('<form method="POST"><input type="hidden" name="eliminar_nomina_completa" value="1"><input type="hidden" name="tipo_nomina" value="'+tipoNomina+'"></form>').appendTo('body').submit();
            }
        });
    });

    // 🔽 NUEVO: Eliminar Todo en AJUSTE (solo filas visibles con estado Borrador)
    $('#btnEliminarTodoAjuste').on('click', function() {
        var idsBorrador = [];
        if (nominasTable) {
            $(nominasTable.rows({ search: 'applied' }).nodes()).each(function() {
                var $row = $(this);
                if ($row.find('.badge-borrador').length) {
                    var idFila = $row.data('id');
                    if (idFila) idsBorrador.push(idFila);
                }
            });
        }
        if (idsBorrador.length === 0) {
            Swal.fire({ title: 'Sin registros en borrador', text: 'No hay filas en estado Borrador para eliminar.', icon: 'info', background: '#1a1a2e', color: '#ffffff', confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' });
            return;
        }
        Swal.fire({
            title: '¿Eliminar toda la nómina en borrador?',
            html: 'Se eliminarán <strong>' + idsBorrador.length + ' registro(s)</strong> en estado Borrador. Las nóminas contabilizadas no se verán afectadas.',
            icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar', cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar', background: '#1a1a2e', color: 'white'
        }).then((r) => {
            if (r.isConfirmed) {
                var $form = $('<form method="POST">');
                $form.append('<input type="hidden" name="eliminar_borrador_por_ids" value="1">');
                $form.append('<input type="hidden" name="tipo_nomina" value="' + tipoNomina + '">');
                idsBorrador.forEach(function(idFila) {
                    $form.append('<input type="hidden" name="ids[]" value="' + idFila + '">');
                });
                $form.appendTo('body').submit();
            }
        });
    });

    // 🔽 NUEVO: Revertir (descontabilizar) una nómina contabilizada
    $(document).on('click', '.btn-revertir-nomina', function() {
        var numeroNominaRevertir = $(this).data('numero');
        if (!numeroNominaRevertir) {
            Swal.fire({ title: 'Sin número de nómina', text: 'No se pudo identificar el número de la nómina a revertir.', icon: 'error', background: '#1a1a2e', color: '#ffffff', confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' });
            return;
        }
        var nombreTipo = tipoNominaTexto || tipoNomina;
        Swal.fire({
            title: '<i class="fas fa-undo-alt me-2" style="color: #f59e0b;"></i> Revertir nómina ' + numeroNominaRevertir + '?',
            html: `
                <div class="text-center">
                    <i class="fas fa-undo-alt fa-3x mb-3" style="color: #f59e0b;"></i>
                    <p class="mb-2">La nómina <strong>${numeroNominaRevertir}</strong> (${$('<div>').text(nombreTipo).html()}) volverá a estado <strong>Borrador</strong> para poder modificarse.</p>
                    <div class="p-3 my-2 rounded text-start" style="background: rgba(245, 158, 11, 0.1); border: 0.0625rem solid rgba(245, 158, 11, 0.25); font-size:0.85rem;">
                        <i class="fas fa-info-circle me-1 text-warning"></i>
                        <span>Se desharán automáticamente:</span>
                        <ul class="mb-0 mt-1" style="padding-left:1.125rem;">
                            <li>El cierre registrado de la nómina.</li>
                            <li>La acumulación o disfrute de <strong>vacaciones</strong> aplicada a los trabajadores.</li>
                            <li>Los movimientos del <strong>submayor de vacaciones</strong>.</li>
                            <li>El monto en <strong>montos_distrib</strong> (bonos), si aplica.</li>
                        </ul>
                    </div>
                    <p class="text-muted small mb-0">Si la nómina ya fue enviada/exportada al banco (BANDEC), contacte al especialista antes de revertir.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, revertir',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            background: '#1a1a2e',
            color: 'white'
        }).then((r) => {
            if (r.isConfirmed) {
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i> Revertiendo...',
                    text: 'Procesando solicitud, por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); },
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
                var $form = $('<form method="POST">');
                $form.append('<input type="hidden" name="revertir_nomina" value="1">');
                $form.append('<input type="hidden" name="tipo_nomina" value="' + (window.tipoNomina || tipoNomina) + '">');
                $form.append('<input type="hidden" name="numero_nomina" value="' + numeroNominaRevertir + '">');
                $form.appendTo('body').submit();
            }
        });
    });

        $(document).on('click', '.eliminar-fila', function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        Swal.fire({
            title: '<i class="fas fa-trash-alt me-2" style="color: #ef4444;"></i> Eliminar registro',
            html: `
                <div class="text-center">
                    <i class="fas fa-user-slash fa-3x mb-3" style="color: #ef4444;"></i>
                    <p>¿Está seguro que desea eliminar de la nómina A:</p>
                    <p><strong>${nombre}?</strong></p>
                    <p class="text-muted small">Esta acción no se puede deshacer.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            background: '#1E1E1E',
            color: '#FFFFFF'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i> Eliminando...',
                    text: 'Procesando solicitud',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    background: '#1E1E1E',
                    color: '#FFFFFF'
                });
                
                $('<form method="POST"><input type="hidden" name="eliminar_nomina_individual" value="1"><input type="hidden" name="id" value="'+id+'"></form>').appendTo('body').submit();
            }
        });
    });
    
    // ==========================================
    // CONTABILIZAR NÓMINA CON MODAL OBLIGATORIO
    // ==========================================

    var modalDescripcion = null;

// ==========================================
// IMPRIMIR RESULTADOS DEL CHEQUEO DE CUADRE
// ==========================================
window.imprimirCuadrePendiente = function(rep, tituloReporte) {
    var lista = (rep && rep.errores) || [];
    var w = window.open('', '_blank', 'width=980,height=680');
    if (!w) { return; }

    var faUrl = new URL('../css/font-awesome6.4.0/css/all.min.css', window.location.href).href;

    var nombreMes = function(ym) {
        var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        var p = String(ym || '').split('-');
        var m = parseInt(p[1], 10);
        if (p.length === 2 && m >= 1 && m <= 12) {
            return meses[m - 1] + ' / ' + p[0];
        }
        return (nombreMesGlobal || '') + ' / ' + (anioGlobal || '');
    };

    var imp = 0, arit = 0;
    for (var i = 0; i < lista.length; i++) {
        var d = (lista[i].detalle || '').toLowerCase();
        if (d.indexOf('contribuci') !== -1 || d.indexOf('impuesto') !== -1 || d.indexOf('isip') !== -1) { imp++; } else { arit++; }
    }
    var errTotal = (rep && rep.errores_total != null) ? rep.errores_total : lista.length;
    var impTotal = (rep && rep.errores_impuestos != null) ? rep.errores_impuestos : imp;
    var aritTotal = (rep && rep.errores_aritmetica != null) ? rep.errores_aritmetica : arit;
    var filasRev = (rep && rep.filas != null) ? rep.filas : 0;
    var filasErr = (rep && rep.filas_con_error != null) ? rep.filas_con_error : (lista.length > 0 ? 1 : 0);

    var periodoTexto = nombreMes(rep && rep.periodo ? rep.periodo : '');
    var tipoTexto = (rep && rep.tipo) ? escapeHtml(rep.tipo) : '';
    var esContabilizada = (tituloReporte || '').toUpperCase().indexOf('CONTABILIZADA') !== -1;
    var fecha = new Date().toLocaleString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

    var POR_PAGINA = 18;
    var paginas = [];
    for (var k = 0; k < lista.length; k += POR_PAGINA) {
        paginas.push(lista.slice(k, k + POR_PAGINA));
    }
    if (paginas.length === 0) { paginas.push([]); }
    var totalPaginas = paginas.length;

    var paginasHtml = '';
    for (var pg = 0; pg < paginas.length; pg++) {
        var rows = '';
        for (var r = 0; r < paginas[pg].length; r++) {
            var e = paginas[pg][r];
            rows += '<tr>'
                + '<td class="tc">' + escapeHtml(e.trabajador_id) + '</td>'
                + '<td class="tc">' + escapeHtml(e.numero || '') + '</td>'
                + (esContabilizada ? '<td class="tc">' + escapeHtml((e.periodo || '').substring(0, 7)) + '</td>' : '')
                + '<td>' + escapeHtml(e.trabajador) + '</td>'
                + '<td class="tc" style="text-transform:capitalize;">' + escapeHtml(e.tipo) + '</td>'
                + '<td>' + escapeHtml(e.detalle) + '</td>'
                + '<td class="tr">' + escapeHtml(e.encontrado) + '</td>'
                + '<td class="tr">' + escapeHtml(e.esperado) + '</td>'
                + '<td class="tr dif">' + escapeHtml(e.diferencia) + '</td>'
                + '</tr>';
        }
        var firmas = '';
        if (pg === paginas.length - 1) {
            firmas = '<div class="firmas">'
                + '<div class="firma"><p class="fl">&nbsp;</p><p><b>Elaborado por:</b></p><b>' + escapeHtml(especialistaNominas || '') + '</b><br><span class="sig-label">Especialista de N&oacute;minas</span></div>'
                + '<div class="firma"><p class="fl">&nbsp;</p><p><b>Revisado por:</b></p><b>' + escapeHtml(especialistaGestion || '') + '</b><br><span class="sig-label">Especialista en Gesti&oacute;n Econ&oacute;mica</span></div>'
                + '<div class="firma"><p class="fl">&nbsp;</p><p><b>Aprobado por:</b></p><b>' + escapeHtml(jefeProyecto || '') + '</b><br><span class="sig-label">Jefe de Proyecto</span></div>'
                + '</div>';
        }
        paginasHtml += '<div class="pagina">'
            + '<table class="hdr"><tr>'
            + '<td class="logo">' + (logoBase64 ? '<img src="' + logoBase64 + '" alt="Logo">' : '') + '</td>'
            + '<td class="titulo"><div class="titulo1">CHEQUEO DE CUADRE &mdash; ' + (tituloReporte || 'N&Oacute;MINAS EN BORRADOR') + '</div>'
            + '<div class="titulo2">' + escapeHtml(nombreEmpresa || '') + '</div>'
            + '<div class="titulo3">Per&iacute;odo: <b>' + periodoTexto + '</b> &nbsp;&mdash;&nbsp; Tipo de n&oacute;mina: <b>' + tipoTexto + '</b> &nbsp;&mdash;&nbsp; Generado: ' + fecha + '</div></td>'
            + '<td class="num">P&aacute;gina ' + (pg + 1) + ' de ' + totalPaginas + '</td>'
            + '</tr></table>'
            + '<div class="resumen">Filas revisadas: <b>' + filasRev + '</b> &nbsp;&nbsp; Con descuadres: <b>' + filasErr + '</b> &nbsp;&nbsp; Errores: <b>' + errTotal + '</b> &nbsp;(impuestos: ' + impTotal + ', aritm&eacute;tica: ' + aritTotal + ')</div>'
            + '<table class="tbl"><thead><tr>'
            + '<th class="tc" style="width:5%;">ID</th><th class="tc" style="width:8%;">No. N&oacute;mina</th>'
            + (esContabilizada ? '<th class="tc" style="width:8%;">Per&iacute;odo</th>' : '')
            + '<th style="width:21%;">Trabajador</th><th class="tc" style="width:10%;">Tipo</th><th>Verificaci&oacute;n</th><th class="tr" style="width:10%;">Almacenado</th><th class="tr" style="width:10%;">Calculado</th><th class="tr" style="width:10%;">Diferencia</th>'
            + '</tr></thead><tbody>' + rows + '</tbody></table>'
            + '<div class="pie">P&aacute;gina ' + (pg + 1) + ' de ' + totalPaginas + '</div>'
            + firmas
            + '</div>';
    }

    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Chequeo de Cuadre</title>'
        + '<link rel="stylesheet" href="' + faUrl + '">'
        + '<style>'
        + '@page { size: Letter landscape; margin:10mm 12mm; }'
        + '* { box-sizing: border-box; }'
        + 'body { font-family: Arial, sans-serif; margin:0; padding:0.875rem; background: #eef1f5; color: #111; }'
        + '.no-print { display: block; }'
        + '@media print { body { padding:0; background: #fff; } .no-print { display: none !important; } }'
        + '.toolbar { position: sticky; top:0; z-index: 10; display: flex; gap:0.625rem; justify-content: center; padding:0.625rem; background: var(--panel); border-radius: 0.625rem; margin-bottom:0.875rem; box-shadow: 0 0.125rem 0.625rem rgba(0,0,0,0.25); }'
        + '.toolbar button { border: none; border-radius: 0.5rem; padding:0.625rem 1.375rem; font-size:0.8125rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap:0.5rem; }'
        + '.toolbar .btn-print { background: #f59e0b; color: #1a1a2e; }'
        + '.toolbar .btn-close { background: #475569; color: #fff; }'
        + '.pagina { background: #fff; border: 0.0625rem solid #cbd5e1; border-radius: 0.375rem; padding:0.875rem 1rem; margin-bottom:1rem; page-break-after: always; }'
        + '.pagina:last-child { page-break-after: auto; }'
        + 'table.hdr { width:100%; border-collapse: collapse; margin-bottom:0.5rem; }'
        + 'table.hdr td { border: 0.0625rem solid #000; padding:0.375rem 0.625rem; vertical-align: middle; }'
        + 'td.logo { width:4.375rem; text-align: center; }'
        + 'td.logo img { max-width:3.375rem; max-height:3.125rem; }'
        + 'td.titulo { text-align: center; }'
        + '.titulo1 { font-size:0.9375rem; font-weight: bold; color: #004b87; }'
        + '.titulo2 { font-size:0.75rem; font-weight: bold; margin-top:0.125rem; }'
        + '.titulo3 { font-size:0.625rem; margin-top:0.25rem; color: #333; }'
        + 'td.num { width:5.625rem; text-align: center; font-size:0.625rem; font-weight: bold; }'
        + '.resumen { font-size:0.6562rem; margin:0.375rem 0 0.5rem; padding:0.375rem 0.625rem; background: #f8fafc; border: 0.0625rem solid #dbe3ee; border-radius: 0.25rem; }'
        + 'table.tbl { width:100%; border-collapse: collapse; font-size:0.625rem; }'
        + 'table.tbl th, table.tbl td { border: 0.0625rem solid #000; padding:0.25rem 0.375rem; vertical-align: top; }'
        + 'table.tbl th { background: #004b87; color: #fff; font-weight: bold; }'
        + 'table.tbl tr:nth-child(even) td { background: #f6f8fb; }'
        + '.tc { text-align: center; } .tr { text-align: right; }'
        + 'td.dif { font-weight: bold; color: #b45309; }'
        + '.pie { margin-top:0.375rem; text-align: right; font-size:0.5312rem; color: #555; border-top: 0.0625rem solid #cbd5e1; padding-top:0.25rem; }'
        + '.firmas { display: table; width:100%; margin-top:2.125rem; }'
        + '.firma { display: table-cell; width:33.33%; text-align: center; font-size:0.625rem; vertical-align: top; padding:0 0.5rem; }'
        + '.firma p { margin:0 0 0.1875rem; }'
        + '.firma .fl { border-top: 0.0625rem solid #000; width:78%; margin:0 auto 0.375rem; height:1.625rem; }'
        + '.sig-label { font-size:0.5312rem; color: #555; }'
        + '</style></head><body>' + PRINT_TOOLBAR_HTML
        + paginasHtml
        + '</body></html>');
    w.document.close();
    w.focus();
};

// Imprimir el cuadre de las nóminas CONTABILIZADAS (desde el modal Verificar Cuadre)
window.imprimirCuadreContabilizadas = function(rep) {
    window.imprimirCuadrePendiente(rep, 'N&Oacute;MINAS CONTABILIZADAS');
};

// ==========================================
// DROPDOWN DE ACCIONES DEL CHEQUEO DE CUADRE (IMPRIMIR / XLS / PDF / TXT / DOCX)
// ==========================================
window.cuadreDropData = window.cuadreDropData || {};

window.cuadreContexto = function(rep, titulo) {
    var lista = (rep && rep.errores) || [];
    var imp = 0, arit = 0;
    for (var i = 0; i < lista.length; i++) {
        var d = (lista[i].detalle || '').toLowerCase();
        if (d.indexOf('contribuci') !== -1 || d.indexOf('impuesto') !== -1 || d.indexOf('isip') !== -1) { imp++; } else { arit++; }
    }
    var nombreMes = function(ym) {
        var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        var p = String(ym || '').split('-');
        var m = parseInt(p[1], 10);
        if (p.length === 2 && m >= 1 && m <= 12) { return meses[m - 1] + ' / ' + p[0]; }
        return (nombreMesGlobal || '') + ' / ' + (anioGlobal || '');
    };
    var now = new Date();
    var ymd = now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
    var hms = String(now.getHours()).padStart(2, '0') + String(now.getMinutes()).padStart(2, '0') + String(now.getSeconds()).padStart(2, '0');
    var tipo = (rep && rep.tipo) ? String(rep.tipo).replace(/[^A-Za-z0-9\u00C0-\u017F]/g, '_') : 'nomina';
    return {
        lista: lista,
        errTotal: (rep && rep.errores_total != null) ? rep.errores_total : lista.length,
        impTotal: (rep && rep.errores_impuestos != null) ? rep.errores_impuestos : imp,
        aritTotal: (rep && rep.errores_aritmetica != null) ? rep.errores_aritmetica : arit,
        filasRev: (rep && rep.filas != null) ? rep.filas : 0,
        filasErr: (rep && rep.filas_con_error != null) ? rep.filas_con_error : (lista.length > 0 ? 1 : 0),
        periodoTexto: nombreMes(rep && rep.periodo ? rep.periodo : ''),
        tipoTexto: (rep && rep.tipo) ? escapeHtml(rep.tipo) : '',
        esContabilizada: (titulo || '').toUpperCase().indexOf('CONTABILIZADA') !== -1,
        nombreBase: 'Chequeo_Cuadre_' + tipo + '_' + ymd + '_' + hms
    };
};

window.cuadreDescargar = function(contenido, nombreArchivo, mime) {
    var blob = new Blob(['\ufeff' + contenido], { type: mime || 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = nombreArchivo;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function() { URL.revokeObjectURL(url); }, 1500);
};

var CUADRE_BOTONES = [
    ['imprimir', 'fa-print', 'Imprimir'],
    ['xls', 'fa-file-excel', 'XLS'],
    ['pdf', 'fa-file-pdf', 'PDF'],
    ['txt', 'fa-file-lines', 'TXT'],
    ['docx', 'fa-file-word', 'DOCX']
];

window.cuadreBotonesHtml = function(key) {
    var items = '';
    for (var i = 0; i < CUADRE_BOTONES.length; i++) {
        var b = CUADRE_BOTONES[i];
        items += '<button type="button" class="cuadre-export-btn cuadre-export-btn--' + b[0] + '" onclick="window.cuadreAccion(\'' + key + '\',\'' + b[0] + '\')">'
            + '<i class="fas ' + b[1] + '"></i>' + b[2] + '</button>';
    }
    return '<div class="cuadre-export-group">' + items + '</div>';
};

var CUADRE_FORMATOS = {
    imprimir: { nombre: 'Impresión', ext: 'Impresora', icono: '<i class="fas fa-print me-2" style="color:#60a5fa;"></i>', desc: 'Abre una vista previa imprimible del reporte (Carta horizontal, logo, firmas y numeración de páginas).' },
    xls:      { nombre: 'Excel (XLS)', ext: 'xls', icono: '<i class="fas fa-file-excel me-2" style="color:#21a366;"></i>', desc: 'Hoja de cálculo compatible con Microsoft Excel con las columnas del chequeo.' },
    pdf:      { nombre: 'PDF', ext: 'pdf', icono: '<i class="fas fa-file-pdf me-2" style="color:#ef4444;"></i>', desc: 'Documento PDF en orientación horizontal (Carta) con paginación automática.' },
    txt:      { nombre: 'TXT', ext: 'txt', icono: '<i class="fas fa-file-lines me-2" style="color:#eab308;"></i>', desc: 'Texto plano con columnas alineadas para cualquier editor.' },
    docx:     { nombre: 'Word (DOCX)', ext: 'doc', icono: '<i class="fas fa-file-word me-2" style="color:#3b82f6;"></i>', desc: 'Documento de Microsoft Word con cabecera de empresa y firmas.' }
};

window.cuadreAccion = function(key, accion) {
    window.cuadreDropData = window.cuadreDropData || {};
    var d = window.cuadreDropData[key];
    if (!d) { return; }
    var rep = d.rep, titulo = d.titulo;
    var conf = CUADRE_FORMATOS[accion];
    if (!conf) { return; }
    var c = window.cuadreContexto(rep, titulo);

    if (accion === 'imprimir') {
        Swal.fire({
            title: conf.icono + 'Imprimir reporte',
            html: '<p>' + conf.desc + '</p>'
                + '<div style="text-align:left; background:rgba(255,255,255,0.05); border:0.0625rem solid rgba(255,255,255,0.12); border-radius:0.5rem; padding:0.625rem 0.875rem; font-size:0.82rem;">'
                + '<div><b>Formato:</b> ' + conf.nombre + ' (' + conf.ext + ')</div>'
                + '<div class="mt-1"><b>Reporte:</b> ' + escapeHtml(titulo || 'N\u00d3MINAS EN BORRADOR') + '</div>'
                + '</div>',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-print me-1"></i>Imprimir',
            cancelButtonText: '<i class="fas fa-times me-1"></i>Cancelar',
            confirmButtonColor: '#0ea5e9',
            background: '#1a1a2e',
            color: '#ffffff'
        }).then(function(res) {
            if (res.isConfirmed) { window.imprimirCuadrePendiente(rep, titulo); }
        });
        return;
    }

    Swal.fire({
        title: conf.icono + 'Exportar a ' + conf.nombre,
        html: '<p>' + conf.desc + '</p>'
            + '<div style="text-align:left; background:rgba(255,255,255,0.05); border:0.0625rem solid rgba(255,255,255,0.12); border-radius:0.5rem; padding:0.625rem 0.875rem; font-size:0.82rem;">'
            + '<div><b>Formato:</b> ' + conf.nombre + ' (' + conf.ext + ')</div>'
            + '<div class="mt-1"><b>Archivo:</b> ' + c.nombreBase + '.' + conf.ext + '</div>'
            + (c.esContabilizada ? '<div class="mt-1"><b>Reporte:</b> N&oacute;minas contabilizadas</div>' : '<div class="mt-1"><b>Reporte:</b> N&oacute;minas en borrador</div>')
            + '</div>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-download me-1"></i>Exportar',
        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancelar',
        confirmButtonColor: '#0ea5e9',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then(function(res) {
        if (!res.isConfirmed) { return; }
        if (accion === 'pdf' && typeof pdfMake === 'undefined') {
            Swal.fire({
                title: '<i class="fas fa-file-pdf me-2" style="color:#ef4444;"></i>PDF no disponible',
                text: 'La librer\u00eda de generaci\u00f3n PDF no est\u00e1 cargada en esta p\u00e1gina.',
                icon: 'warning',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
            });
            return;
        }
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i>Generando ' + conf.nombre + '...',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); },
            background: '#1a1a2e',
            color: '#ffffff'
        });
        setTimeout(function() {
            if (accion === 'xls') { window.cuadreExportarXls(rep, titulo); }
            else if (accion === 'pdf') { window.cuadreExportarPdf(rep, titulo); }
            else if (accion === 'txt') { window.cuadreExportarTxt(rep, titulo); }
            else if (accion === 'docx') { window.cuadreExportarDocx(rep, titulo); }
            Swal.close();
            Swal.fire({
                title: '<i class="fas fa-check-circle me-2" style="color:3;"></i>Exportaci\u00f3n completada',
                text: 'El archivo ' + conf.nombre + ' se ha generado y descargado.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
                background: '#1a1a2e',
                color: '#ffffff'
            });
        }, 300);
    });
};

window.cuadreExportarXls = function(rep, titulo) {
    var c = window.cuadreContexto(rep, titulo);
    var th = titulo || 'N\u00d3MINAS EN BORRADOR';
    var nCol = 8 + (c.esContabilizada ? 1 : 0);
    var periodoTh = c.esContabilizada ? '<th>Per&iacute;odo</th>' : '';
    var html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Chequeo de Cuadre</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>'
        + '<table border="1"><tr><td colspan="' + nCol + '" style="text-align:center;font-weight:bold;font-size:0.75rem;">' + th + '</td></tr>'
        + '<tr><td colspan="' + nCol + '"><b>Empresa:</b> ' + escapeHtml(nombreEmpresa || '') + '</td></tr>'
        + '<tr><td colspan="' + nCol + '"><b>Per&iacute;odo:</b> ' + c.periodoTexto + ' &nbsp;&nbsp;<b>Tipo de n&oacute;mina:</b> ' + c.tipoTexto + ' &nbsp;&nbsp;<b>Generado:</b> ' + new Date().toLocaleString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }) + '</td></tr>'
        + '<tr><td colspan="' + nCol + '"><b>Filas revisadas:</b> ' + c.filasRev + ' &nbsp;&nbsp;<b>Con descuadres:</b> ' + c.filasErr + ' &nbsp;&nbsp;<b>Errores:</b> ' + c.errTotal + ' (impuestos: ' + c.impTotal + ', aritm&eacute;tica: ' + c.aritTotal + ')</td></tr>'
        + '<tr><th>Trabajador ID</th><th>No. N&oacute;mina</th>' + periodoTh + '<th>Trabajador</th><th>Tipo</th><th>Verificaci&oacute;n</th><th>Almacenado</th><th>Calculado</th><th>Diferencia</th></tr>';
    for (var i = 0; i < c.lista.length; i++) {
        var e = c.lista[i];
        html += '<tr><td>' + escapeHtml(e.trabajador_id) + '</td><td>' + escapeHtml(e.numero || '') + '</td>' + (c.esContabilizada ? '<td>' + escapeHtml((e.periodo || '').substring(0, 7)) + '</td>' : '') + '<td>' + escapeHtml(e.trabajador) + '</td><td>' + escapeHtml(e.tipo) + '</td><td>' + escapeHtml(e.detalle) + '</td><td style="mso-number-format:\'0.00\';">' + escapeHtml(e.encontrado) + '</td><td style="mso-number-format:\'0.00\';">' + escapeHtml(e.esperado) + '</td><td style="mso-number-format:\'0.00\';">' + escapeHtml(e.diferencia) + '</td></tr>';
    }
    html += '</table></body></html>';
    window.cuadreDescargar(html, c.nombreBase + '.xls', 'application/vnd.ms-excel;charset=utf-8');
};

window.cuadreExportarPdf = function(rep, titulo) {
    var c = window.cuadreContexto(rep, titulo);
    if (typeof pdfMake === 'undefined') {
        Swal.fire({ title: '<i class="fas fa-file-pdf me-2" style="color:#ef4444;"></i>PDF no disponible', text: 'La librer&iacute;a de generaci&oacute;n PDF no est&aacute; cargada en esta p&aacute;gina.', icon: 'warning', background: '#1a1a2e', color: '#ffffff', confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar' });
        return;
    }
    var th = titulo || 'N\u00d3MINAS EN BORRADOR';
    var tbody = [];
    var cabecera = [
        { text: 'ID', style: 'th' }, { text: 'No. N\u00f3mina', style: 'th' }
    ];
    if (c.esContabilizada) { cabecera.push({ text: 'Per\u00edodo', style: 'th' }); }
    cabecera.push({ text: 'Trabajador', style: 'th' }, { text: 'Tipo', style: 'th' });
    cabecera.push({ text: 'Verificaci\u00f3n', style: 'th' }, { text: 'Almacenado', style: 'th', alignment: 'right' });
    cabecera.push({ text: 'Calculado', style: 'th', alignment: 'right' }, { text: 'Diferencia', style: 'th', alignment: 'right' });
    tbody.push(cabecera);
    for (var i = 0; i < c.lista.length; i++) {
        var e = c.lista[i];
        var fila = [String(e.trabajador_id || ''), String(e.numero || '')];
        if (c.esContabilizada) { fila.push(String((e.periodo || '').substring(0, 7))); }
        fila.push(String(e.trabajador || ''), String(e.tipo || ''), String(e.detalle || ''), String(e.encontrado || ''), String(e.esperado || ''), String(e.diferencia || ''));
        tbody.push(fila);
    }
    var fecha = new Date().toLocaleString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var doc = {
        pageSize: 'LETTER',
        pageOrientation: 'landscape',
        pageMargins: [32, 96, 32, 44],
        images: logoBase64 ? { logo: logoBase64 } : undefined,
        header: function() {
            var cols = [];
            if (logoBase64) { cols.push({ width: 50, image: 'logo', alignment: 'left' }); }
            cols.push({ width: '*', stack: [
                { text: 'CHEQUEO DE CUADRE \u2014 ' + th, style: 'titulo1', alignment: 'center' },
                { text: (nombreEmpresa || '').toUpperCase(), style: 'titulo2', alignment: 'center' },
                { text: 'Per\u00edodo: ' + c.periodoTexto + '   \u2022   Tipo de n\u00f3mina: ' + c.tipoTexto + '   \u2022   Generado: ' + fecha, style: 'titulo3', alignment: 'center' }
            ] });
            return { margin: [32, 14, 32, 0], columns: cols };
        },
        footer: function(currentPage, pageCount) {
            return { text: 'P\u00e1gina ' + currentPage + ' de ' + pageCount, alignment: 'center', fontSize: 8, color: '#555', margin: [0, 6, 0, 0] };
        },
        content: [
            { text: 'Filas revisadas: ' + c.filasRev + '   \u2022   Con descuadres: ' + c.filasErr + '   \u2022   Errores: ' + c.errTotal + '   (impuestos: ' + c.impTotal + ', aritm\u00e9tica: ' + c.aritTotal + ')', style: 'resumen' },
            { table: { headerRows: 1, widths: c.esContabilizada ? [30, 50, 52, 128, 50, '*', 58, 58, 58] : [32, 55, 140, 55, '*', 62, 62, 62], body: tbody }, layout: 'headerLineOnly' }
        ],
        styles: {
            th: { bold: true, color: '#ffffff', fillColor: '#004b87', fontSize: 8.5 },
            titulo1: { fontSize: 12, bold: true, color: '#004b87' },
            titulo2: { fontSize: 9, bold: true, margin: [0, 2, 0, 0] },
            titulo3: { fontSize: 7.5, color: '#333', margin: [0, 3, 0, 0] },
            resumen: { fontSize: 8.5, margin: [0, 0, 0, 8], color: '#111' }
        },
        defaultStyle: { fontSize: 8 }
    };
    doc.content.push({ text: '', margin: [0, 28, 0, 0] });
    doc.content.push({
        margin: [0, 2, 0, 0],
        table: {
            widths: ['*', '*', '*'],
            body: [[
                { stack: [{ text: (especialistaNominas || '').toUpperCase(), fontSize: 7.5, bold: true, alignment: 'center' }, { text: 'Elaborado por', fontSize: 7, color: '#555', alignment: 'center' }], border: [false, true, false, false] },
                { stack: [{ text: (especialistaGestion || '').toUpperCase(), fontSize: 7.5, bold: true, alignment: 'center' }, { text: 'Revisado por \u2014 Especialista en Gesti\u00f3n Econ\u00f3mica', fontSize: 7, color: '#555', alignment: 'center' }], border: [false, true, false, false] },
                { stack: [{ text: (jefeProyecto || '').toUpperCase(), fontSize: 7.5, bold: true, alignment: 'center' }, { text: 'Aprobado por \u2014 Jefe de Proyecto', fontSize: 7, color: '#555', alignment: 'center' }], border: [false, true, false, false] }
            ]]
        },
        layout: 'noBorders'
    });
    pdfMake.createPdf(doc).download(c.nombreBase + '.pdf');
};

window.cuadreExportarTxt = function(rep, titulo) {
    var c = window.cuadreContexto(rep, titulo);
    var th = titulo || 'N\u00d3MINAS EN BORRADOR';
    function padR(s, n) { s = String(s == null ? '' : s); return s.length >= n ? s : s + ' '.repeat(n - s.length); }
    function padL(s, n) { s = String(s == null ? '' : s); return s.length >= n ? s : ' '.repeat(n - s.length) + s; }
    var lineas = [];
    lineas.push('========================================================================');
    lineas.push('            ' + th);
    lineas.push('========================================================================');
    lineas.push('Empresa: ' + (nombreEmpresa || ''));
    lineas.push('Per\u00edodo: ' + c.periodoTexto + '  |  Tipo de n\u00f3mina: ' + c.tipoTexto);
    lineas.push('Filas revisadas: ' + c.filasRev + '  |  Con descuadres: ' + c.filasErr + '  |  Errores: ' + c.errTotal + ' (impuestos: ' + c.impTotal + ', aritm\u00e9tica: ' + c.aritTotal + ')');
    lineas.push('------------------------------------------------------------------------');
    lineas.push(padR('ID', 6) + padR('No. N\u00f3mina', 12) + (c.esContabilizada ? padR('Per\u00edodo', 10) : '') + padR('Trabajador', 26) + padR('Tipo', 12) + padL('Almacenado', 12) + padL('Calculado', 12) + padL('Diferencia', 12) + '  Verificaci\u00f3n');
    lineas.push('------------------------------------------------------------------------');
    for (var i = 0; i < c.lista.length; i++) {
        var e = c.lista[i];
        lineas.push(padR(e.trabajador_id, 6) + padR(e.numero || '', 12) + (c.esContabilizada ? padR((e.periodo || '').substring(0, 7), 10) : '') + padR(e.trabajador, 26) + padR(e.tipo, 12) + padL(e.encontrado, 12) + padL(e.esperado, 12) + padL(e.diferencia, 12) + '  ' + e.detalle);
    }
    lineas.push('========================================================================');
    window.cuadreDescargar(lineas.join('\r\n'), c.nombreBase + '.txt', 'text/plain;charset=utf-8');
};

window.cuadreExportarDocx = function(rep, titulo) {
    var c = window.cuadreContexto(rep, titulo);
    var th = titulo || 'N\u00d3MINAS EN BORRADOR';
    var fecha = new Date().toLocaleString('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var periodoTh = c.esContabilizada ? '<th style="color:#fff;font-size:8pt;">Per&iacute;odo</th>' : '';
    var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>' + th + '</title></head><body>'
        + '<table border="0" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;">'
        + '<tr><td style="width:4.375rem;text-align:center;">' + (logoBase64 ? '<img src="' + logoBase64 + '" width="55">' : '') + '</td>'
        + '<td style="text-align:center;font-weight:bold;font-size:0.875rem;">CHEQUEO DE CUADRE \u2014 ' + th + '<br><span style="font-size:0.6875rem;font-weight:normal;">' + escapeHtml((nombreEmpresa || '').toUpperCase()) + '</span></td>'
        + '<td style="width:12.5rem;font-size:8pt;"><b>Emisi\u00f3n:</b> ' + fecha + '</td></tr></table>'
        + '<p style="font-size:9pt;"><b>Per\u00edodo:</b> ' + c.periodoTexto + ' &nbsp;&nbsp;<b>Tipo de n\u00f3mina:</b> ' + c.tipoTexto + '</p>'
        + '<p style="font-size:9pt;"><b>Filas revisadas:</b> ' + c.filasRev + ' &nbsp;&nbsp;<b>Con descuadres:</b> ' + c.filasErr + ' &nbsp;&nbsp;<b>Errores:</b> ' + c.errTotal + ' (impuestos: ' + c.impTotal + ', aritm&eacute;tica: ' + c.aritTotal + ')</p>'
        + '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;">'
        + '<tr style="background:#004b87;"><th style="color:#fff;font-size:8pt;">Trabajador ID</th><th style="color:#fff;font-size:8pt;">No. N&oacute;mina</th>' + periodoTh + '<th style="color:#fff;font-size:8pt;">Trabajador</th><th style="color:#fff;font-size:8pt;">Tipo</th><th style="color:#fff;font-size:8pt;">Verificaci&oacute;n</th><th style="color:#fff;font-size:8pt;text-align:right;">Almacenado</th><th style="color:#fff;font-size:8pt;text-align:right;">Calculado</th><th style="color:#fff;font-size:8pt;text-align:right;">Diferencia</th></tr>';
    for (var i = 0; i < c.lista.length; i++) {
        var e = c.lista[i];
        html += '<tr><td style="font-size:8pt;">' + escapeHtml(e.trabajador_id) + '</td><td style="font-size:8pt;">' + escapeHtml(e.numero || '') + '</td>' + (c.esContabilizada ? '<td style="font-size:8pt;">' + escapeHtml((e.periodo || '').substring(0, 7)) + '</td>' : '') + '<td style="font-size:8pt;">' + escapeHtml(e.trabajador) + '</td><td style="font-size:8pt;">' + escapeHtml(e.tipo) + '</td><td style="font-size:8pt;">' + escapeHtml(e.detalle) + '</td><td style="font-size:8pt;text-align:right;">' + escapeHtml(e.encontrado) + '</td><td style="font-size:8pt;text-align:right;">' + escapeHtml(e.esperado) + '</td><td style="font-size:8pt;text-align:right;">' + escapeHtml(e.diferencia) + '</td></tr>';
    }
    html += '</table>'
        + '<table style="width:100%;margin-top:2.25rem;border-collapse:collapse;"><tr style="text-align:center;font-size:8pt;">'
        + '<td style="border-top:0.0625rem solid #000;padding:0.375rem;width:33%;">' + escapeHtml(especialistaNominas || '') + '<br><span style="font-size:7pt;">Elaborado por \u2014 Especialista de N\u00f3minas</span></td>'
        + '<td style="border-top:0.0625rem solid #000;padding:0.375rem;width:33%;">' + escapeHtml(especialistaGestion || '') + '<br><span style="font-size:7pt;">Revisado por \u2014 Especialista en Gesti\u00f3n Econ\u00f3mica</span></td>'
        + '<td style="border-top:0.0625rem solid #000;padding:0.375rem;width:33%;">' + escapeHtml(jefeProyecto || '') + '<br><span style="font-size:7pt;">Aprobado por \u2014 Jefe de Proyecto</span></td>'
        + '</tr></table></body></html>';
    window.cuadreDescargar(html, c.nombreBase + '.doc', 'application/msword;charset=utf-8');
};

// Inyecta el alert de "Revisión de cuadre previa" (estilo anterior) en la página
window.mostrarAlertCuadrePendiente = function(rep) {
    var container = document.getElementById('alertasGenerales');
    if (!container) return;
    if (document.getElementById('alertCuadrePendienteInyectado')) return;
    var lista = (rep && rep.errores) || [];
    var rows = '';
    for (var i = 0; i < lista.length && i < 100; i++) {
        var e = lista[i];
        rows += '<tr>'
            + '<td><a class="cuadre-link" href="empleados.php?editar=' + escapeHtml(e.trabajador_id) + '" title="Abrir ficha del empleado">' + escapeHtml(e.trabajador) + '</a></td>'
            + '<td>' + escapeHtml(e.tipo) + '</td>'
            + '<td>' + escapeHtml(e.detalle) + '</td>'
            + '<td class="text-end cuadre-val-almacenado">' + escapeHtml(e.encontrado) + '</td>'
            + '<td class="text-end cuadre-val-recalculado">' + escapeHtml(e.esperado) + '</td>'
            + '<td class="text-end cuadre-val-diferencia">' + escapeHtml(e.diferencia) + '</td>'
            + '</tr>';
    }
    var filas = (rep && rep.filas_con_error != null) ? rep.filas_con_error : lista.length;
    var html = '<div class="alert cuadre-alert-inyectado" id="alertCuadrePendienteInyectado">'
        + '<i class="fas fa-ban me-2"></i><strong>Revisi&oacute;n de cuadre previa:</strong> la n&oacute;mina <strong>NO fue contabilizada</strong>. Hay <strong>' + filas + '</strong> fila(s) en borrador con descuadres (devengado, CESS/ISIP, deducciones o neto).'
        + '<button type="button" class="btn-close" style="float:right;" onclick="this.closest(\'.alert\').remove();" aria-label="Cerrar"></button>'
        + '<div class="mt-2 d-flex flex-wrap gap-2">'
        + '<button type="button" class="btn btn-warning btn-sm" id="btnCorregirPendientesInyectado"><i class="fas fa-calculator me-1"></i> Recalcular y corregir</button>'
        + window.cuadreBotonesHtml('cuadre_inyectado')
        + '</div>'
        + '<div class="cuadre-alert-detalle">'
        + '<strong><i class="fas fa-list me-1"></i> Detalle de los descuadres pendientes:</strong>'
        + '<table class="table table-sm table-dark align-middle mt-2 mb-0"><thead><tr>'
        + '<th>Trabajador</th><th>Tipo</th><th>Verificaci&oacute;n</th><th class="text-end">Almacenado</th><th class="text-end">Recalculado</th><th class="text-end">Diferencia</th>'
        + '</tr></thead><tbody>' + rows + '</tbody></table>'
        + '</div>'
        + '</div>';
    window.cuadreDropData['cuadre_inyectado'] = { rep: rep, titulo: 'N\u00d3MINAS EN BORRADOR' };
    container.insertAdjacentHTML('afterbegin', html);

    var btnCorr = document.getElementById('btnCorregirPendientesInyectado');
    if (btnCorr) {
        btnCorr.addEventListener('click', function() {
            var parts = (rep.periodo || '').split('-');
            var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10);
            if (!y || !m) { return; }
            var ultimoDia = new Date(y, m, 0).getDate();
            var pd = rep.periodo + '-01';
            var ph = y + '-' + String(m).padStart(2, '0') + '-' + String(ultimoDia).padStart(2, '0');
            Swal.fire({
                title: '<i class="fas fa-spinner fa-spin me-2"></i>Recalculando...',
                text: 'Corrigiendo los borradores con descuadres',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); },
                background: '#1a1a2e',
                color: '#ffffff'
            });
            fetch('nominas.php?action=corregir_pendientes&ajax=1&pd=' + encodeURIComponent(pd) + '&ph=' + encodeURIComponent(ph) + '&tipo=' + encodeURIComponent(rep.tipo), { cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        var alerta = document.getElementById('alertCuadrePendienteInyectado');
                        if (alerta && data.errores_total === 0) { alerta.style.display = 'none'; }
                        if (data.errores_total === 0) {
                            Swal.fire({
                                title: '<i class="fas fa-check-circle me-2" style="color:3;"></i>Borradores corregidos',
                                html: 'Se corrigieron <strong>' + data.filas_corregidas + '</strong> borrador(es). El cuadre est&aacute; correcto, puede contabilizar.',
                                icon: 'success',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            });
                        } else {
                            Swal.fire({
                                title: '<i class="fas fa-exclamation-triangle me-2"></i>Quedan descuadres',
                                html: 'Se corrigieron <strong>' + data.filas_corregidas + '</strong> borrador(es), pero quedan <strong>' + data.errores_total + '</strong> descuadre(s).',
                                icon: 'warning',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            });
                        }
                    } else {
                        Swal.fire({
                            title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                            text: (data && data.message) ? data.message : 'No se pudo recalcular.',
                            icon: 'error',
                            background: '#1a1a2e',
                            color: '#ffffff',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                        });
                    }
                })
                .catch(function() {
                    Swal.fire({
                        title: '<i class="fas fa-wifi me-2"></i>Error',
                        text: 'No se pudo conectar para recalcular.',
                        icon: 'error',
                        background: '#1a1a2e',
                        color: '#ffffff',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                    });
                });
        });
    }
};

$('#contabilizarBtn').on('click', function() {
    // ==========================================
    // ✅ VALIDACIÓN UNIVERSAL PARA TODAS LAS NÓMINAS
    // ==========================================
    var trabajadoresCero = [];
    var nombreCampo = getNombreCampo(tipoNomina);
    var selectorCampo = getSelectorCampo(tipoNomina);
    
    if (selectorCampo) {
        var $filas = $('#tablaNominas tbody tr');
        
        $filas.each(function() {
            var $fila = $(this);
            var $campoInput = $fila.find(selectorCampo);
            // Omitir filas ya contabilizadas (sin campo editable) en vistas mixtas
            if (!$campoInput.length) return;
            var campoValor = parseNumber($campoInput.val());
            
            if (campoValor === 0 || campoValor < 0.5) {
                var nombre = $fila.find('td:eq(2)').text().trim();
                var id = $fila.data('id');
                trabajadoresCero.push({ id: id, nombre: nombre });
            }
        });
    }
    
    if (trabajadoresCero.length > 0) {
        var listaNombres = trabajadoresCero.map(function(t) {
            return '<li class="text-danger fw-bold">' + escapeHtml(t.nombre) + '</li>';
        }).join('');
        
        Swal.fire({
            title: '<i class="fas fa-times-circle text-danger me-2"></i> No se puede contabilizar',
            html: `
                <div class="text-center">
                    <i class="fas ${getIconoCampo(tipoNomina)} fa-3x mb-3" style="color: #ef4444;"></i>
                    <p class="mb-3">Hay <strong class="text-danger">${trabajadoresCero.length}</strong> trabajador(es) con <span class="text-danger fw-bold">0 ${nombreCampo}</span> en la nómina:</p>
                    <ul class="text-start" style="display: inline-block; margin:0 auto; padding-left:1.25rem;">
                        ${listaNombres}
                    </ul>
                    <div class="p-3 mt-3 rounded" style="background: rgba(239, 68, 68, 0.1); border: 0.0625rem solid rgba(239, 68, 68, 0.25);">
                        <i class="fas fa-info-circle me-1 text-danger"></i>
                        <span class="text-danger">No se puede contabilizar una nómina con trabajadores en 0 ${nombreCampo}.</span>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-danger" id="btnEliminarTodosCero" style="border-radius: 0.5rem; padding:0.375rem 1.125rem;">
                            <i class="fas fa-user-minus me-1"></i> Eliminar todos
                        </button>
                        <button class="btn btn-sm btn-secondary" id="btnAsignarValorManual" style="border-radius: 0.5rem; padding:0.375rem 1.125rem; margin-left:0.5rem;">
                            <i class="fas fa-pen me-1"></i> Asignar ${nombreCampo} manualmente
                        </button>
                    </div>
                </div>
            `,
            icon: 'error',
            showConfirmButton: false,
            showCancelButton: false,
            background: '#1a1a2e',
            color: '#ffffff',
            width: '34.375rem',
            didOpen: function() {
                document.getElementById('btnEliminarTodosCero').addEventListener('click', function() {
                    Swal.close();
                    eliminarTodosTrabajadoresCero(trabajadoresCero);
                });
                
                document.getElementById('btnAsignarValorManual').addEventListener('click', function() {
                    Swal.close();
                    // Enfocar el primer input del campo clave
                    setTimeout(function() {
                        var $firstInput = $('#tablaNominas tbody tr ' + selectorCampo).first();
                        if ($firstInput.length) {
                            $firstInput.focus().select();
                        }
                    }, 200);
                });
            }
        });
        return;
    }
    
    // Si no hay trabajadores con valor cero, primero se revisa el cuadre de los borradores
    // del período/tipo (solo lectura) y, solo si cuadran, se muestra el modal de contabilizar.
    Swal.fire({
        title: '<i class="fas fa-scale-balanced fa-spin me-2"></i> Revisando cuadre...',
        text: 'Verificando devengado, CESS/ISIP, deducciones y neto de los borradores del período.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });

    fetch('nominas.php?action=chequear_cuadre_pendiente&ajax=1&periodo=' + encodeURIComponent(periodo) + '&tipo=' + encodeURIComponent(tipoNomina), { cache: 'no-store' })
        .then(function(r) { return r.json(); })
        .then(function(rep) {
            Swal.close();
            rep.periodo = periodo;
            rep.tipo = tipoNomina;
            if (rep && rep.filas_con_error > 0) {
                var items = '';
                var lista = rep.errores || [];
                for (var i = 0; i < lista.length && i < 50; i++) {
                    var e = lista[i];
                    items += '<li><strong>' + escapeHtml(e.trabajador) + '</strong> (' + escapeHtml(e.tipo) + '): ' + escapeHtml(e.detalle)
                        + ' &mdash; almacenado <span class="text-danger">' + escapeHtml(e.encontrado) + '</span>, calculado <span class="text-success">' + escapeHtml(e.esperado) + '</span>, diferencia <span class="text-warning">' + escapeHtml(e.diferencia) + '</span></li>';
                }
                // === VENTANA ESTILO WINDOWS (No se puede contabilizar) ===
                var ventId = 'ventanaBloqueoCuadre';
                var ventVieja = document.getElementById(ventId);
                if (ventVieja) { ventVieja.remove(); }
                window.cuadreDropData['cuadre_swal'] = { rep: rep, titulo: 'N\u00d3MINAS EN BORRADOR' };

                var ventHtml = ''
                    + '<div id="' + ventId + '" class="ventana-cuadre-overlay">'
                    + '<div class="ventana-cuadre-win">'
                    + '<div class="ventana-cuadre-titlebar">'
                    + '<i class="fas fa-ban ventana-cuadre-titlebar-icon"></i>'
                    + '<span class="ventana-cuadre-title">Cuadre de N\u00f3mina \u2014 No se puede contabilizar</span>'
                    + '<button type="button" id="btnCerrarVentanaCuadre" class="ventana-cuadre-close">&times;</button>'
                    + '</div>'
                    + '<div class="ventana-cuadre-body">'
                    + '<p class="mb-2">La revisi\u00f3n de cuadre detect\u00f3 <strong class="ventana-cuadre-danger">' + rep.filas_con_error + '</strong> borrador(es) con descuadres en este per\u00edodo (<strong>' + rep.errores_impuestos + '</strong> de impuestos y <strong>' + rep.errores_aritmetica + '</strong> de aritm\u00e9tica: devengado, deducciones o neto):</p>'
                    + '<div class="ventana-cuadre-list">'
                    + '<ul>' + items + '</ul>'
                    + (lista.length < rep.errores_total ? '<p class="ventana-cuadre-list-note">Mostrando los primeros ' + lista.length + ' de ' + rep.errores_total + ' descuadres.</p>' : '')
                    + '</div>'
                    + '<div class="ventana-cuadre-warn">'
                    + '<i class="fas fa-info-circle me-1"></i>'
                    + '<span>Puede corregir autom\u00e1ticamente los borradores con descuadre y luego contabilizar.</span>'
                    + '</div>'
                    + '</div>'
                    + '<div class="ventana-cuadre-footer">'
                    + '<button type="button" id="btnRecalcularPendientesVentana" class="ventana-cuadre-btn-primary">'
                    + '<i class="fas fa-calculator me-1"></i> Recalcular y corregir estos pendientes</button>'
                    + '<div class="ventana-cuadre-export">' + window.cuadreBotonesHtml('cuadre_swal') + '</div>'
                    + '<button type="button" id="btnEntendidoVentana" class="ventana-cuadre-btn-ghost">'
                    + '<i class="fas fa-check me-1"></i> Entendido</button>'
                    + '</div>'
                    + '</div>'
                    + '</div>';

                document.body.insertAdjacentHTML('beforeend', ventHtml);

                function cerrarVentanaCuadre() { var v = document.getElementById(ventId); if (v) { v.remove(); } }
                document.getElementById('btnCerrarVentanaCuadre').addEventListener('click', cerrarVentanaCuadre);
                document.getElementById('btnEntendidoVentana').addEventListener('click', cerrarVentanaCuadre);

                document.getElementById('btnRecalcularPendientesVentana').addEventListener('click', function() {
                    var parts = (periodo || '').split('-');
                    var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10);
                    if (!y || !m) { cerrarVentanaCuadre(); return; }
                    var ultimoDia = new Date(y, m, 0).getDate();
                    var pd = periodo + '-01';
                    var ph = y + '-' + String(m).padStart(2, '0') + '-' + String(ultimoDia).padStart(2, '0');
                    cerrarVentanaCuadre();
                    Swal.fire({
                        title: '<i class="fas fa-spinner fa-spin me-2"></i>Recalculando...',
                        text: 'Corrigiendo los borradores con descuadres',
                        allowOutsideClick: false,
                        didOpen: function() { Swal.showLoading(); },
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    fetch('nominas.php?action=corregir_pendientes&ajax=1&pd=' + encodeURIComponent(pd) + '&ph=' + encodeURIComponent(ph) + '&tipo=' + encodeURIComponent(tipoNomina), { cache: 'no-store' })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data && data.success) {
                                if (data.errores_total === 0) {
                                    Swal.fire({
                                        title: '<i class="fas fa-check-circle me-2" style="color:3;"></i>Borradores corregidos',
                                        html: 'Se corrigieron <strong>' + data.filas_corregidas + '</strong> borrador(es). El cuadre está correcto, puede continuar con la contabilización.',
                                        icon: 'success',
                                        background: '#1a1a2e',
                                        color: '#ffffff',
                                        confirmButtonText: '<i class="fas fa-check me-1"></i>Contabilizar ahora'
                                    }).then(function() {
                                        modalDescripcion = new bootstrap.Modal(document.getElementById('modalDescripcionContabilizar'));
                                        modalDescripcion.show();
                                    });
                                } else {
                                    Swal.fire({
                                        title: '<i class="fas fa-exclamation-triangle me-2"></i>Quedan descuadres',
                                        html: 'Se corrigieron <strong>' + data.filas_corregidas + '</strong> borrador(es), pero quedan <strong>' + data.errores_total + '</strong> descuadre(s). Revise y corrija antes de contabilizar.',
                                        icon: 'warning',
                                        background: '#1a1a2e',
                                        color: '#ffffff',
                                        confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                                    });
                                }
                            } else {
                                Swal.fire({
                                    title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                                    text: (data && data.message) ? data.message : 'No se pudo recalcular.',
                                    icon: 'error',
                                    background: '#1a1a2e',
                                    color: '#ffffff',
                                    confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                                });
                            }
                        })
                        .catch(function() {
                            Swal.fire({
                                title: '<i class="fas fa-wifi me-2"></i>Error',
                                text: 'No se pudo conectar para recalcular.',
                                icon: 'error',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            });
                        });
                });

                window.mostrarAlertCuadrePendiente(rep);
                return;
            }
            // Cuadre correcto: mostrar el modal de contabilizar con la descripción
            modalDescripcion = new bootstrap.Modal(document.getElementById('modalDescripcionContabilizar'));
            modalDescripcion.show();
        })
        .catch(function() {
            Swal.close();
            modalDescripcion = new bootstrap.Modal(document.getElementById('modalDescripcionContabilizar'));
            modalDescripcion.show();
        });
});

    $(document).on('input', '#observacionesCierre', function() {
        var text = $(this).val().trim();
        if (text.length > 0) {
            $('#btnConfirmarContabilizar').prop('disabled', false);
        } else {
            $('#btnConfirmarContabilizar').prop('disabled', true);
        }
    });

    $('#btnConfirmarContabilizar').on('click', function() {
        var observaciones = $('#observacionesCierre').val().trim();
        
        if (observaciones === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Descripción requerida',
                text: 'Debe escribir una descripción para la nómina antes de contabilizar.',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: '#1a1a2e',
                color: '#ffffff'
            });
            return;
        }
        
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i> Contabilizando...',
            text: 'Procesando la nómina, por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1a1a2e',
            color: '#ffffff'
        });
        
        var form = $('<form method="POST"></form>');
        form.append('<input type="hidden" name="contabilizar_nomina" value="1">');
        form.append('<input type="hidden" name="tipo_nomina" value="' + tipoNomina + '">');
        form.append('<input type="hidden" name="observaciones_cierre" value="' + encodeURIComponent(observaciones) + '">');
        $('body').append(form);
        form.submit();
    });

    $('#btnCancelarContabilizar').on('click', function() {
        $('#observacionesCierre').val('');
        $('#btnConfirmarContabilizar').prop('disabled', true);
    });


// ==========================================
// VALIDACIÓN DE CAMPOS CLAVE EN CERO PARA TODAS LAS NÓMINAS
// ==========================================
function validarCamposCero(fila) {
    var tipoNominaActual = tipoNomina;
    var id = fila.data('id');
    var nombre = fila.find('td:eq(2)').text().trim();
    var campoValor = 0;
    var nombreCampo = '';
    var icono = '';
    var mensajeAyuda = '';
    var esValido = true;
    
    // Determinar qué campo validar según el tipo de nómina
    switch(tipoNominaActual) {
        case 'automatica':
            var horasInput = fila.find('.edit-horas');
            campoValor = horasInput.length ? parseNumber(horasInput.val()) : 0;
            nombreCampo = 'horas laboradas';
            icono = 'fa-clock';
            mensajeAyuda = 'Asigne las horas trabajadas para este empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        case 'extraordinaria':
            esValido = true;
            break;
            
        case 'vacaciones':
            var diasInput = fila.find('.edit-dias');
            campoValor = diasInput.length ? parseNumber(diasInput.val()) : 0;
            nombreCampo = 'días tomados';
            icono = 'fa-umbrella-beach';
            mensajeAyuda = 'Asigne los días de vacaciones que tomará el empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        case 'bono':
            var bonoInput = fila.find('.edit-bono');
            campoValor = bonoInput.length ? parseNumber(bonoInput.val()) : 0;
            nombreCampo = 'monto del bono';
            icono = 'fa-gift';
            mensajeAyuda = 'Asigne el monto del bono para este empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        case 'ajuste':
            var ajusteInput = fila.find('.edit-bono');
            campoValor = ajusteInput.length ? parseNumber(ajusteInput.val()) : 0;
            nombreCampo = 'monto del ajuste';
            icono = 'fa-pen';
            mensajeAyuda = 'Asigne el monto del ajuste para este empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        default:
            return true;
    }
    
    // Si es válido, retornar true
    if (esValido) {
        return true;
    }
    
    // Mostrar SweetAlert con opciones
    Swal.fire({
        title: `<i class="fas fa-exclamation-triangle text-warning me-2"></i> ${nombreCampo.charAt(0).toUpperCase() + nombreCampo.slice(1)} en Cero`,
        html: `
            <div class="text-center">
                <i class="fas ${icono} fa-3x mb-3" style="color: #f59e0b;"></i>
                <p class="mb-2">El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 ${nombreCampo}</span> registradas en esta nómina.</p>
                <div class="p-3 my-2 rounded" style="background: rgba(245, 158, 11, 0.1); border: 0.0625rem solid rgba(245, 158, 11, 0.25); font-size:0.9rem;">
                    <i class="fas fa-info-circle me-1 text-warning"></i>
                    <span>Una nómina con trabajadores en <strong>0 ${nombreCampo}</strong> <span class="text-danger">NO puede ser contabilizada</span>.</span>
                </div>
                <div class="mt-3 d-flex justify-content-center gap-3">
                    <button class="btn btn-sm btn-warning" id="btnEliminarTrabajadorCero" style="border-radius: 0.5rem; padding:0.375rem 1.125rem;">
                        <i class="fas fa-user-minus me-1"></i> Eliminar de nómina
                    </button>
                    <button class="btn btn-sm btn-secondary" id="btnAsignarValorCero" style="border-radius: 0.5rem; padding:0.375rem 1.125rem;">
                        <i class="fas fa-pen me-1"></i> Asignar ${nombreCampo}
                    </button>
                </div>
                <p class="text-muted small mt-3">Si eliminas al trabajador, su registro será removido de la nómina actual.</p>
            </div>
        `,
        icon: 'warning',
        showConfirmButton: false,
        showCancelButton: false,
        background: '#1a1a2e',
        color: '#ffffff',
        width: '31.25rem',
        didOpen: function() {
            // Evento para eliminar trabajador
            document.getElementById('btnEliminarTrabajadorCero').addEventListener('click', function() {
                Swal.close();
                eliminarTrabajadorPorId(id, nombre);
            });
            
            // Evento para asignar valor (cierra el modal y enfoca el input)
            document.getElementById('btnAsignarValorCero').addEventListener('click', function() {
                Swal.close();
                setTimeout(function() {
                    var selector = '';
                    switch(tipoNominaActual) {
                        case 'automatica':
                            selector = '.edit-horas';
                            break;
                        case 'extraordinaria':
                            var $horasNorm = fila.find('.edit-horas');
                            var hNorm = parseNumber($horasNorm.val()) || 0;
                            selector = '.edit-horas';
                            break;
                        case 'vacaciones':
                            selector = '.edit-dias';
                            break;
                        case 'bono':
                            selector = '.edit-bono';
                            break;
                        case 'ajuste':
                            selector = '.edit-bono';
                            break;
                    }
                    var $input = fila.find(selector);
                    if ($input.length) {
                        $input.focus().select();
                    }
                }, 200);
            });
        }
    });
    return false;
}
// ==========================================
// OBTENER NOMBRE DEL CAMPO SEGÚN TIPO DE NÓMINA
// ==========================================
function getNombreCampo(tipo) {
    switch(tipo) {
        case 'automatica': return 'horas laboradas';
        case 'extraordinaria': return 'horas laboradas';
        case 'vacaciones': return 'días tomados';
        case 'bono': return 'monto del bono';
        case 'ajuste': return 'monto del ajuste';
        default: return 'valor';
    }
}

function getSelectorCampo(tipo) {
    switch(tipo) {
        case 'automatica':
        case 'extraordinaria':
            return '.edit-horas';
        case 'vacaciones':
            return '.edit-dias';
        case 'bono':
        case 'ajuste':
            return '.edit-bono';
        default:
            return '';
    }
}

function getIconoCampo(tipo) {
    switch(tipo) {
        case 'automatica':
        case 'extraordinaria':
            return 'fa-clock';
        case 'vacaciones':
            return 'fa-umbrella-beach';
        case 'bono':
            return 'fa-gift';
        case 'ajuste':
            return 'fa-pen';
        default:
            return 'fa-file';
    }
}

function eliminarTrabajadorPorId(id, nombre) {
    if (!id) {
        Swal.fire({
            title: 'Error',
            text: 'No se pudo identificar al trabajador.',
            icon: 'error',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    Swal.fire({
        title: '<i class="fas fa-trash-alt me-2" style="color: #ef4444;"></i> ¿Eliminar trabajador?',
        html: `
            <div class="text-center">
                <i class="fas fa-user-slash fa-3x mb-3" style="color: #ef4444;"></i>
                <p>¿Está seguro que desea eliminar de la nómina a:</p>
                <p><strong>${escapeHtml(nombre)}</strong>?</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-spin me-2"></i> Eliminando...',
                text: 'Procesando solicitud',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: '#1a1a2e',
                color: '#ffffff'
            });
            
            var form = $('<form method="POST"></form>');
            form.append('<input type="hidden" name="eliminar_nomina_individual" value="1">');
            form.append('<input type="hidden" name="id" value="' + id + '">');
            $('body').append(form);
            form.submit();
        }
    });
}

// ==========================================
// VALIDACIÓN UNIVERSAL AL CONTABILIZAR
// ==========================================
function validarTrabajadoresCeroAntesContabilizar() {
    var trabajadoresCero = [];
    var nombreCampo = '';
    var selectorCampo = '';
    var icono = '';
    var esExtraordinaria = (tipoNomina === 'extraordinaria');
    
    // Configurar según tipo de nómina
    switch(tipoNomina) {
        case 'automatica':
            nombreCampo = 'horas laboradas';
            selectorCampo = '.edit-horas';
            icono = 'fa-clock';
            break;
        case 'extraordinaria':
            nombreCampo = 'horas laboradas (normales y nocturnas)';
            icono = 'fa-clock';
            // Para extraordinaria, validamos ambos campos
            break;
        case 'vacaciones':
            nombreCampo = 'días tomados';
            selectorCampo = '.edit-dias';
            icono = 'fa-umbrella-beach';
            break;
        case 'bono':
            nombreCampo = 'monto del bono';
            selectorCampo = '.edit-bono';
            icono = 'fa-gift';
            break;
        case 'ajuste':
            nombreCampo = 'monto del ajuste';
            selectorCampo = '.edit-bono';
            icono = 'fa-pen';
            break;
        default:
            return true;
    }
    
    var $filas = $('#tablaNominas tbody tr');
    
    $filas.each(function() {
        var $fila = $(this);
        var nombre = $fila.find('td:eq(2)').text().trim();
        var id = $fila.data('id');
        var esCero = false;
        
        if (esExtraordinaria) {
            var $horasNorm = $fila.find('.edit-horas');
            var $noctT = $fila.find('.edit-noct-temprana');
            var $noctD = $fila.find('.edit-noct-tardia');
            var $dt = $fila.find('.edit-doble-turno');
            var horasNorm = $horasNorm.length ? parseNumber($horasNorm.val()) : 0;
            var noctT = $noctT.length ? parseNumber($noctT.val()) : 0;
            var noctD = $noctD.length ? parseNumber($noctD.val()) : 0;
            var dt = $dt.length ? parseNumber($dt.val()) : 0;
            
            if (horasNorm === 0 && noctT === 0 && noctD === 0 && dt === 0) {
                esCero = true;
            }
        } else {
            // Para los demás tipos: validar el campo específico
            var $campoInput = $fila.find(selectorCampo);
            var campoValor = $campoInput.length ? parseNumber($campoInput.val()) : 0;
            
            if (campoValor === 0 || campoValor < 0.5) {
                esCero = true;
            }
        }
        
        if (esCero) {
            trabajadoresCero.push({ id: id, nombre: nombre });
        }
    });
    
    return trabajadoresCero;
}

    // ==========================================
    // MODALES DE SELECCIÓN (Extraordinaria, Vacaciones, Bonos)
    // ==========================================
    
function renderExtraWorkerList(term = '') {
    var idsAuto = <?php 
        $ids = [];
        if ($existe_nomina && $tipo_nomina_activa != 'automatica') {
            $st = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde=? AND periodo_hasta=? AND tipo_nomina='automatica'");
            $st->execute([$periodo_desde, $periodo_hasta]);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        echo json_encode($ids); 
    ?>;
    var areaVal = $('#filterExtraArea').val();
    var ccVal = $('#filterExtraCC').val();
    var html = '';
    
    var filtered = trabajadores.filter(w => {
        var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
        var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
        var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
        return matchesSearch && matchesArea && matchesCC;
    });
    
    filtered.forEach(w => {
        var sel = selectedExtra.some(s => s.id == w.id);
        
        // Evaluar si el trabajador ya está en la nómina extraordinaria actual
        var yaEnNomina = idsEnNominaActual.map(Number).includes(Number(w.id));
        
        // Definir estilos y clases visuales condicionales
        var itemStyle = yaEnNomina ? 'border-left: 0.25rem solid #f59e0b; background: rgba(245, 158, 11, 0.08);' : 'border-left: 0.1875rem solid #10b981;';
        var checkboxAttr = yaEnNomina ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : (sel ? 'checked' : '');
        var avatarBg = yaEnNomina ? 'background: rgba(245, 158, 11, 0.15);' : 'background: rgba(16, 185, 129, 0.15);';
        var avatarIconColor = yaEnNomina ? 'color: #f59e0b;' : 'color: #10b981;';

        // Resolución del path de la foto
        var workerFotoUrl = '';
        if (w.foto_ruta && w.foto_ruta.trim() !== '') {
            workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
        }

        var avatarHtml = '';
        if (workerFotoUrl) {
            avatarHtml = '<img src="' + workerFotoUrl + '" style="width:100%; height:100%; object-fit: cover;" onerror="this.onerror=null; this.src=\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23f59e0b%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E\';">';
        } else {
            avatarHtml = '<i class="fas fa-user" style="' + avatarIconColor + '"></i>';
        }

        var centroCostoNombre = 'Sin CC';
        if (w.centro_costo_codigo && w.centro_costo_nombre) {
            centroCostoNombre = w.centro_costo_codigo + ' - ' + w.centro_costo_nombre;
        } else if (w.centro_costo_nombre) {
            centroCostoNombre = w.centro_costo_nombre;
        }
        
        html += `<div class="worker-item ${sel?'selected':''}" 
                    data-id="${w.id}" 
                    data-nombre="${w.nombre_completo}" 
                    data-codigo="${w.codigo}" 
                    data-sh="${w.salario_hora_ordinaria}"
                    data-salario-mensual="${w.salario_mensual}"
                    data-area="${w.nombre_area || 'Sin área'}"
                    data-centro-costo="${centroCostoNombre}"
                    data-foto-ruta="${w.foto_ruta || ''}"
                    style="${itemStyle}">
            <input type="checkbox" class="worker-checkbox" ${checkboxAttr}>
            <div class="worker-avatar" style="${avatarBg}">
                ${avatarHtml}
            </div>
            <div class="worker-info">
                <div class="worker-name">${w.codigo} - ${w.nombre_completo}</div>
                <div class="worker-detail">$${parseFloat(w.salario_hora_ordinaria).toFixed(2)}/h</div>
                <div class="worker-detail small text-muted"><i class="fas fa-building"></i> ${w.nombre_area || 'Sin área'} | <i class="fas fa-chart-pie"></i> ${centroCostoNombre}</div>
            </div>
            ${yaEnNomina ? `
                <div class="worker-dias text-end">
                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 0.0625rem solid #f59e0b; font-size:0.7rem;">
                        <i class="fas fa-exclamation-circle me-1"></i> Ya en nómina
                    </span>
                </div>
            ` : ''}
        </div>`;
    });
    $('#extraWorkerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash me-1"></i>No hay resultados</div>');
}

function updateExtraList(focusId) {
    var diasLaborables = <?php echo $dias_laborables; ?>;
    var html = selectedExtra.map(w => {
        var salarioDiario = w.salario_mensual / diasLaborables;
        var valorHoraExtra = w.sh * recargoExtraDiurna;
        var valorHoraNocturna = w.sh * recargoExtraNocturna;
        var valorDobleTurno = w.sh * recargoDobleturno;
        
        return `
            <div class="selected-worker-card" style="margin-bottom:0.9375rem; border-left: 0.1875rem solid #3b82f6;">
                <div class="selected-worker-header">
                    <div>
                        <span class="worker-name">${w.codigo} - ${w.nombre}</span>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-building me-1"></i> ${w.area} &nbsp;|&nbsp;
                            <i class="fas fa-chart-pie me-1"></i> ${w.centro_costo}
                        </div>
                    </div>
                    <button type="button" class="remove-worker" data-id="${w.id}" style="background:none; border:none; color:#ef4444;">✖</button>
                </div>
                
                <div class="row g-2 mb-3 p-2" style="background: rgba(0,0,0,0.3); border-radius: 0.625rem;">
                    <div class="col-3 text-center">
                        <small class="text-muted"><i class="fas fa-calendar-day"></i> Sal/DÍA</small>
                        <div class="fw-bold text-info">$${salarioDiario.toFixed(2)}</div>
                    </div>
                    <div class="col-3 text-center">
                        <small class="text-muted"><i class="fas fa-clock"></i> Sal/HORA</small>
                        <div class="fw-bold text-info">$${w.sh.toFixed(2)}</div>
                    </div>
                    <div class="col-3 text-center">
                        <small class="text-muted"><i class="fas fa-sun"></i> HE +50%</small>
                        <div class="fw-bold text-warning">$${valorHoraExtra.toFixed(2)}</div>
                    </div>
                    <div class="col-3 text-center">
                        <small class="text-muted"><i class="fas fa-bed"></i> Nt x${recargoExtraNocturna}</small>
                        <div class="fw-bold text-success">$${valorHoraNocturna.toFixed(2)}</div>
                    </div>
                </div>
                
                <div class="row g-2 mt-2">
                    <div class="col-md-3">
                        <label class="small text-warning"><i class="fas fa-sun me-1"></i>HE Diurnas</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm horas-input" 
                                   data-id="${w.id}" value="${w.horasExtraNormales || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#f59e0b;">
                            <span class="input-group-text bg-dark text-warning">x${recargoExtraDiurna}</span>
                        </div>
                        <small class="text-muted">$${valorHoraExtra.toFixed(2)}/h</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="small text-info"><i class="fas fa-moon me-1"></i>Nt 7-23h</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm noct-temprana-input" 
                                   data-id="${w.id}" value="${w.noctTemprana || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#3b82f6;">
                            <span class="input-group-text bg-dark text-info">x${recargoExtraNocturna}</span>
                        </div>
                        <small class="text-muted">$${valorHoraNocturna.toFixed(2)}/h</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="small text-purple"><i class="fas fa-moon me-1"></i>Nt 23-7h</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm noct-tardia-input" 
                                   data-id="${w.id}" value="${w.noctTardia || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#8b5cf6;">
                            <span class="input-group-text bg-dark" style="color:#8b5cf6;">x${recargoExtraNocturna}</span>
                        </div>
                        <small class="text-muted">$${valorHoraNocturna.toFixed(2)}/h</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="small text-success"><i class="fas fa-exchange-alt me-1"></i>Doble Turno</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm doble-turno-input" 
                                   data-id="${w.id}" value="${w.dobleTurno || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#22c55e;">
                            <span class="input-group-text bg-dark text-success">x${recargoDobleturno}</span>
                        </div>
                        <small class="text-muted">$${valorDobleTurno.toFixed(2)}/h</small>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <small class="text-muted">
                            <i class="fas fa-calculator me-1"></i>
                            HE: $${w.sh.toFixed(2)} × ${recargoExtraDiurna} | Nt 7-23h: $${w.sh.toFixed(2)} × ${recargoExtraNocturna} | Nt 23-7h: $${w.sh.toFixed(2)} × ${recargoExtraNocturna} | DT: $${w.sh.toFixed(2)} × ${recargoDobleturno}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    $('#selectedExtraList').html(html || '<em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista izquierda</em>');
    
    if (focusId) {
        setTimeout(function() {
            var $input = $('#selectedExtraList').find(`.horas-input[data-id="${focusId}"]`);
            if ($input.length) {
                $input.focus().select();
            }
        }, 50);
    }

    updateExtraTotals();
}

function updateExtraTotals() {
    var tHorasNorm = 0, tNoctT = 0, tNoctD = 0, tDT = 0, tDev = 0, tNeto = 0, val = 0;
    selectedExtra.forEach(w => {
        var hrsNorm = parseFloat(w.horasExtraNormales) || 0;
        var noctT = parseFloat(w.noctTemprana) || 0;
        var noctD = parseFloat(w.noctTardia) || 0;
        var dt = parseFloat(w.dobleTurno) || 0;
        
        if (hrsNorm > 0 || noctT > 0 || noctD > 0 || dt > 0) {
            tHorasNorm += hrsNorm;
            tNoctT += noctT;
            tNoctD += noctD;
            tDT += dt;
            
            var importeHE = (w.sh * recargoExtraDiurna) * hrsNorm;
            var importeNtT = (w.sh * recargoExtraNocturna) * noctT;
            var importeNtD = (w.sh * recargoExtraNocturna) * noctD;
            var importeDT = (w.sh * recargoDobleturno) * dt;
            var salTotal = importeHE + importeNtT + importeNtD + importeDT;
            tDev += salTotal;
            
            var contribucion = salTotal * 0.05;
            var impuesto = calcularImpuestoProgresivo(salTotal);
            tNeto += salTotal - (contribucion + impuesto);
            val++;
        }
    });
    
    if (val > 0) {
        $('#previewExtraCount').text(val);
        $('#previewExtraTotalHoras').text(tHorasNorm + tNoctT + tNoctD + tDT);
        $('#previewExtraTotalDevengado').text('$' + tDev.toFixed(2));
        $('#previewExtraTotalNeto').text('$' + tNeto.toFixed(2));
        $('#previewExtra').show();
        $('#btnGenerarExtraordinaria').prop('disabled', false);
    } else { 
        $('#previewExtra').hide();
        $('#btnGenerarExtraordinaria').prop('disabled', true);
    }
}

    $('#searchExtraWorker').on('input', function(){ renderExtraWorkerList($(this).val()); });

$(document).on('click', '#extraWorkerList .worker-item', function(){
    var id = $(this).attr('data-id');
    var nombre = $(this).attr('data-nombre');
    var fotoRuta = $(this).attr('data-foto-ruta') || '';

    // Verificar si el trabajador ya se encuentra registrado en la nómina activa actual
    if (idsEnNominaActual.map(Number).includes(Number(id))) {
		// Construir la URL de la foto apuntando a la ruta específica de trabajadores
				var fotoUrl = '';
				if (fotoRuta && fotoRuta.trim() !== '') {
					// Si la ruta ya incluye la estructura de carpetas completa
					if (fotoRuta.indexOf('assets/imagenes/trabajadores/') !== -1) {
						fotoUrl = '../' + fotoRuta;
					} else {
						// Si la base de datos almacena solo el nombre del archivo (ej. "trabajador1.png")
						fotoUrl = '../assets/imagenes/trabajadores/' + fotoRuta.split('/').pop();
					}
				} else {
					// Fallback: Silueta SVG en color amarillo/naranja
					fotoUrl = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23f59e0b%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';
				}

        Swal.fire({
            title: 'Atención',
            html: 'Este trabajador (<span class="fw-bold text-danger">' + nombre + '</span>) ya aparece en la nómina extraordinaria, si desea hacer algún cambio, deberá editarlo directo en la tabla.',
            imageUrl: fotoUrl,
            imageWidth: 90,
            imageHeight: 90,
            imageAlt: 'Foto de ' + nombre,
            customClass: {
                image: 'rounded-circle border border-warning'
            },
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return; // Detiene la ejecución y no permite seleccionarlo
    }

    var idx = selectedExtra.findIndex(s => s.id == id);
    var wasAdded = false;
    
    if(idx >= 0) {
        selectedExtra.splice(idx, 1);
    } else {
        selectedExtra.push({
            id: id,
            nombre: nombre,
            codigo: $(this).attr('data-codigo'),
            sh: parseFloat($(this).attr('data-sh')),
            salario_mensual: parseFloat($(this).attr('data-salario-mensual')),
            area: $(this).attr('data-area'),
            centro_costo: $(this).attr('data-centro-costo'),
            horasExtraNormales: '',
            noctTemprana: '',
            noctTardia: '',
            dobleTurno: ''
        });
        wasAdded = true;
    }
    renderExtraWorkerList($('#searchExtraWorker').val());
    updateExtraList(wasAdded ? id : null); // Pasa el ID del trabajador para enfocar el input si fue agregado
});

$(document).on('input', '.horas-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.horasExtraNormales = $(this).val(); 
        updateExtraTotals();
    }
});

$(document).on('input', '.noct-temprana-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.noctTemprana = $(this).val(); 
        updateExtraTotals();
    }
});

$(document).on('input', '.noct-tardia-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.noctTardia = $(this).val(); 
        updateExtraTotals();
    }
});

$(document).on('input', '.doble-turno-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.dobleTurno = $(this).val(); 
        updateExtraTotals();
    }
});

     
    $(document).on('click', '#selectedExtraList .remove-worker', function(){
        selectedExtra.splice(selectedExtra.findIndex(s=>s.id==$(this).data('id')), 1); renderExtraWorkerList($('#searchExtraWorker').val()); updateExtraList();
    });

$('#modalExtraordinaria').on('show.bs.modal', function(){ 
    selectedExtra=[]; 
    renderExtraWorkerList(); 
    updateExtraList(); 
    $('#searchExtraWorker').val('');
    
    // CORRECCIÓN: Prioriza la selección del modal anterior
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    $('#tipoDescuentoExtra').val(td);
    
    // Limpiamos la variable para futuras aperturas manuales
    window.tempSelectedDiscount = null;
});

    $('#formExtraordinaria').on('submit', function(e){
        $(this).find('input[name="trabajador_id[]"], input[name="horas_trabajadas[]"], input[name="nocturnidad_temprana_trabajadas[]"], input[name="nocturnidad_tardia_trabajadas[]"], input[name="doble_turno_trabajadas[]"]').remove();
        var valid = false;
        
        selectedExtra.forEach(w => { 
            var hNorm = parseFloat(w.horasExtraNormales) || 0;
            var noctT = parseFloat(w.noctTemprana) || 0;
            var noctD = parseFloat(w.noctTardia) || 0;
            var dt = parseFloat(w.dobleTurno) || 0;
            
            if(hNorm > 0 || noctT > 0 || noctD > 0 || dt > 0){ 
                $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}">`);
                $(this).append(`<input type="hidden" name="horas_trabajadas[]" value="${hNorm}">`);
                $(this).append(`<input type="hidden" name="nocturnidad_temprana_trabajadas[]" value="${noctT}">`);
                $(this).append(`<input type="hidden" name="nocturnidad_tardia_trabajadas[]" value="${noctD}">`);
                $(this).append(`<input type="hidden" name="doble_turno_trabajadas[]" value="${dt}">`);
                valid = true; 
            } 
        });
        
        if(!valid){ 
            e.preventDefault(); 
            Swal.fire({
                title: 'Error',
                text: 'Asigne horas a los trabajadores',
                icon: 'error',
                background: '#1a1a2e',
                color: 'white',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            }); 
            return false; 
        }
        $(this).append('<input type="hidden" name="confirmar_extraordinaria" value="1">'); 
        return true;
    });

    // Modal Vacaciones
    var rangoActivo = 'todos';

    function renderWorkerList(term = '') {
        var areaVal = $('#filterVacArea').val();
        var ccVal = $('#filterVacCC').val();
        var html = '';
        
        var filtered = trabajadores.filter(w => {
            var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
            var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
            var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
            return matchesSearch && matchesArea && matchesCC;
        });
        
        if (rangoActivo !== 'todos') {
            filtered = filtered.filter(w => {
                var diasAcum = parseFloat(w.dias_acumulados) || 0;
                switch(rangoActivo) {
                    case '1-5': return diasAcum >= 1 && diasAcum <= 5;
                    case '5-10': return diasAcum > 5 && diasAcum <= 10;
                    case '10-15': return diasAcum > 10 && diasAcum <= 15;
                    case '15-20': return diasAcum > 15 && diasAcum <= 20;
                    case '20+': return diasAcum > 20;
                    default: return true;
                }
            });
        }
        
        filtered.forEach(w => {
            var isSelected = selectedWorkers.some(s => s.id == w.id);
            var yaTieneNomina = idsConNomina.includes(w.id);
            var diasAcum = parseFloat(w.dias_acumulados) || 0;
            var disabled = diasAcum <= 0 || yaTieneNomina;
            var disabledText = diasAcum <= 0 ? 'Sin días disponibles' : (yaTieneNomina ? 'Ya tiene nómina de vacaciones en este período' : '');
            
            var badgeColor = '#10b981';
            var badgeBg = 'rgba(16, 185, 129, 0.15)';
            var icono = '<i class="fas fa-check-circle me-1"></i>';
            var mensajeAlerta = '';
            
            if (diasAcum >= 21 && diasAcum <= 24) {
                badgeColor = '#ef4444';
                badgeBg = 'rgba(239, 68, 68, 0.25)';
                icono = '<i class="fas fa-exclamation-triangle me-1"></i>';
                mensajeAlerta = '<span class="badge" style="background:#ef4444; color:white; font-size:0.6rem; margin-left:0.5rem;">¡EXCESO DE DÍAS!</span>';
            } else if (diasAcum > 20 && diasAcum < 21) {
                badgeColor = '#f97316';
                badgeBg = 'rgba(249, 115, 22, 0.2)';
                icono = '<i class="fas fa-clock me-1"></i>';
                mensajeAlerta = '<span class="badge" style="background:#f97316; color:white; font-size:0.6rem; margin-left:0.5rem;">PRÓXIMO A EXCEDER</span>';
            } else if (diasAcum > 15 && diasAcum <= 20) {
                badgeColor = '#f59e0b';
                badgeBg = 'rgba(245, 158, 11, 0.2)';
                icono = '<i class="fas fa-chart-line me-1"></i>';
            } else if (diasAcum > 10 && diasAcum <= 15) {
                badgeColor = '#eab308';
                badgeBg = 'rgba(234, 179, 8, 0.15)';
                icono = '<i class="fas fa-sun me-1"></i>';
            } else if (diasAcum > 5 && diasAcum <= 10) {
                badgeColor = '#3b82f6';
                badgeBg = 'rgba(59, 130, 246, 0.15)';
                icono = '<i class="fas fa-cloud-sun me-1"></i>';
            } else if (diasAcum >= 1 && diasAcum <= 5) {
                badgeColor = '#10b981';
                badgeBg = 'rgba(16, 185, 129, 0.1)';
                icono = '<i class="fas fa-seedling me-1"></i>';
            } else if (diasAcum <= 0) {
                badgeColor = '#6b7280';
                badgeBg = 'rgba(107, 114, 128, 0.15)';
                icono = '<i class="fas fa-ban me-1"></i>';
            }
            
            var porcentaje = Math.min(Math.roundExcel((diasAcum / 24) * 100), 100);
            var barraColor = porcentaje >= 85 ? '#ef4444' : (porcentaje >= 70 ? '#f59e0b' : '#10b981');

            // Resolución del path de la foto
            var workerFotoUrl = '';
            if (w.foto_ruta && w.foto_ruta.trim() !== '') {
                workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
            }

            var avatarHtml = '';
            if (workerFotoUrl) {
                avatarHtml = `<img src="${workerFotoUrl}" style="width:100%; height:100%; object-fit: cover;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2310b981%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">`;
            } else {
                avatarHtml = `<i class="fas fa-user" style="color: ${badgeColor};"></i>`;
            }
            
            html += `<div class="worker-item ${disabled ? 'disabled' : ''} ${isSelected ? 'selected' : ''}" 
                        data-id="${w.id}" 
                        data-dias="${diasAcum}" 
                        data-salario="${w.salario_mensual}" 
                        data-nombre="${w.nombre_completo}" 
                        data-codigo="${w.codigo}"
                        data-foto-ruta="${w.foto_ruta || ''}"
                        style="${disabled ? 'opacity:0.6;' : ''}">
                        <input type="checkbox" class="worker-checkbox" ${isSelected ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                        <div class="worker-avatar" style="background: ${badgeBg};">
                            ${avatarHtml}
                        </div>
                        <div class="worker-info">
                            <div class="worker-name">
                                ${w.codigo} - ${w.nombre_completo}
                                ${mensajeAlerta}
                            </div>
                            <div class="worker-detail">
                                <i class="fas fa-briefcase me-1"></i> ${w.cargo || 'Sin cargo'} 
                                <i class="fas fa-building ms-2 me-1"></i> ${w.nombre_area || 'Sin área'}
                            </div>
                            <div class="mt-1" style="width:100%;">
                                <div style="display: flex; justify-content: space-between; font-size:0.65rem; margin-bottom:0.125rem;">
                                    <span><i class="fas fa-calendar-alt me-1"></i> Días acumulados:</span>
                                    <span><strong style="color: ${badgeColor};">${diasAcum.toFixed(2)} / 24</strong></span>
                                </div>
                                <div style="width:100%; background: rgba(255,255,255,0.1); border-radius: 0.625rem; height:0.25rem;">
                                    <div style="width:${porcentaje}%; background: ${barraColor}; border-radius: 0.625rem; height:0.25rem;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="worker-dias">
                            <div class="dias-num" style="color: ${badgeColor}; font-weight: bold; font-size:1rem;">
                                ${icono} ${diasAcum.toFixed(2)} días
                            </div>
                            <div class="worker-detail">
                                <i class="fas fa-dollar-sign me-1"></i> ${parseFloat(w.valor_acumulado).toLocaleString('en-US', {minimumFractionDigits: 2})}
                            </div>
                        </div>
                        ${disabledText ? `<div class="worker-detail text-danger ms-2"><i class="fas fa-info-circle me-1"></i>${disabledText}</div>` : ''}
                    </div>`;
        });
        $('#workerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash fa-2x mb-2 d-block"></i>No hay resultados</div>');
    }

    $('.rango-btn').on('click', function() {
        $('.rango-btn').removeClass('active');
        $(this).addClass('active');
        rangoActivo = $(this).data('rango');
        renderWorkerList($('#searchWorker').val());
    });

function updateSelectedList(focusId) {
    var html = '';
    var tDev = 0, tDias = 0, val = 0;
    
    selectedWorkers.forEach(w => {
        var vpd = w.salario / diasLaborables;
        var dias = parseNumber(w.dias_a_pagar) || 0;
        var importe = dias * vpd;
        var res = w.dias_acumulados - dias;
        
        tDev += importe;
        tDias += dias;
        if(dias > 0 && dias <= w.dias_acumulados) val++;
        
        html += `<div class="selected-worker-card" style="background: rgba(96, 165, 250, 0.08); border-radius: 0.75rem; padding:0.75rem; margin-bottom:0.625rem; border: 0.0625rem solid rgba(96, 165, 250, 0.25);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:0.75rem;">
                        <span style="font-weight: 600; color: #60a5fa;"><i class="fas fa-user-circle me-1"></i> ${w.codigo} - ${w.nombre}</span>
                        <button type="button" class="remove-worker" data-id="${w.id}" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size:1.1rem;">&times;</button>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap:0.9375rem;">
                        <div style="display: flex; align-items: center; gap:0.5rem;">
                            <span style="color: #ccc; font-size:0.8rem;"><i class="fas fa-calendar-alt"></i> Días:</span>
                            <input type="number" class="dias-input" data-id="${w.id}" 
                                   value="${dias}" max="${w.dias_acumulados}" step="0.5" 
                                   style="width:7.5rem; background: #1e1e2e; border: 0.0625rem solid #3b82f6; border-radius: 0.5rem; padding:0.375rem 0.75rem; color: white; text-align: center;">
                            <span style="color: #aaa; font-size:0.7rem;">máx: ${w.dias_acumulados.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap:0.5rem;">
                            <span style="color: #ccc; font-size:0.8rem;"><i class="fas fa-dollar-sign"></i> Importe:</span>
                            <span class="importe-preview" style="font-weight: bold; color: 4; background: rgba(16,185,129,0.15); padding:0.25rem 0.75rem; border-radius: 0.5rem; min-width:5.625rem; display: inline-block; text-align: center;">
                                $${importe.toFixed(2)}
                            </span>
                        </div>
<div style="font-size:0.7rem; color: #888;">
    <i class="fas fa-chart-line"></i> Restantes: <span class="dias-restantes-preview" style="color: #60a5fa;">${res.toFixed(2)}</span> | Importe: <span class="importe-total-preview" style="color: 1; font-weight: bold;">$${importe.toFixed(2)}</span> (<span style="color: #aaa;">$${vpd.toFixed(2)}/día</span>)
</div>
                    </div>
                </div>`;
    });
    
    $('#selectedWorkersList').html(html || '<div style="color: #aaa; text-align: center; padding:1.25rem;"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista izquierda</div>');
    
    // Verificación defensiva antes de aplicar el foco
    if (typeof focusId !== 'undefined' && focusId) {
        setTimeout(function() {
            var $input = $('#selectedWorkersList').find(`.dias-input[data-id="${focusId}"]`);
            if ($input.length) {
                $input.focus().select();
            }
        }, 50);
    }

    if(val > 0){
        $('#previewCount').text(val);
        $('#previewTotalDias').text(tDias.toFixed(2));
        $('#previewTotalDevengado').text('$' + tDev.toFixed(2));
        $('#previewMultiple').show();
        $('#btnAgregarVacaciones').prop('disabled', false);
    } else {
        $('#previewMultiple').hide();
        $('#btnAgregarVacaciones').prop('disabled', true);
    }
}


$(document).on('input', '.dias-input', function() {
    var $input = $(this);
    var id = parseInt($input.attr('data-id')); // Se corrige .data() por .attr() para leer elementos dinámicos
    var dias = parseFloat($input.val()) || 0;
        
        var worker = selectedWorkers.find(w => w.id == id);
        if (!worker) return;
        
        if (dias > worker.dias_acumulados) {
            dias = worker.dias_acumulados;
            $input.val(dias);
        }
        if (dias < 0) dias = 0;
        
		worker.dias_a_pagar = dias;
		
		var valorPorDia = worker.salario / diasLaborables;
		var importe = dias * valorPorDia;
		
    var $card = $input.closest('.selected-worker-card');
    $card.find('.importe-preview').text('$' + importe.toFixed(2));
    
    // Calcular y actualizar visualmente los días restantes en tiempo real
    var restantes = worker.dias_acumulados - dias;
    if (restantes < 0) restantes = 0;
    $card.find('.dias-restantes-preview').text(restantes.toFixed(2));
    
    // Actualizar visualmente el importe total calculado en el texto inferior
    $card.find('.importe-total-preview').text('$' + importe.toFixed(2));
		
		var totalDevengado = 0;
        var totalDias = 0;
        var trabajadoresValidos = 0;
        
        selectedWorkers.forEach(w => {
            var d = parseFloat(w.dias_a_pagar) || 0;
            if (d > 0 && d <= w.dias_acumulados) {
                var vpd = w.salario / diasLaborables;
                totalDevengado += d * vpd;
                totalDias += d;
                trabajadoresValidos++;
            }
        });
        
        $('#previewCount').text(trabajadoresValidos);
        $('#previewTotalDias').text(totalDias.toFixed(2));
        $('#previewTotalDevengado').text('$' + totalDevengado.toFixed(2));
        
        if (trabajadoresValidos > 0) {
            $('#previewMultiple').show();
            $('#btnAgregarVacaciones').prop('disabled', false);
        } else {
            $('#previewMultiple').hide();
            $('#btnAgregarVacaciones').prop('disabled', true);
        }
    });
    
    $('#searchWorker').on('input', function(){ renderWorkerList($(this).val()); });

$(document).on('click', '#workerList .worker-item:not(.disabled)', function(){
    var id = $(this).attr('data-id'), idx = selectedWorkers.findIndex(s=>s.id==id);
    if(idx>=0) {
        selectedWorkers.splice(idx,1); 
    } else {
        selectedWorkers.push({
            id: id, 
            nombre: $(this).attr('data-nombre'), 
            codigo: $(this).attr('data-codigo'), 
            salario: parseFloat($(this).attr('data-salario')), 
            dias_acumulados: parseFloat($(this).attr('data-dias')), 
            dias_a_pagar: '' // Valor inicial para el input de días
        });
    }
    renderWorkerList($('#searchWorker').val()); 
    updateSelectedList(id); // Pasa el ID para enfocar el input de días
});

    $(document).on('click', '#selectedWorkersList .remove-worker', function(){
        selectedWorkers.splice(selectedWorkers.findIndex(s=>s.id==$(this).data('id')), 1); renderWorkerList($('#searchWorker').val()); updateSelectedList();
    });

$('#modalVacaciones').on('show.bs.modal', function(){ 
    selectedWorkers=[]; 
    renderWorkerList(); 
    updateSelectedList(); 
    $('#searchWorker').val(''); 
    
    // CORRECCIÓN: Prioriza la selección del modal anterior
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    
    // CORRECCIÓN de ID: Su HTML tiene "tipoDescuentoVac" pero el JS buscaba "tipoDiscountVac"
    $('#tipoDescuentoVac').val(td); 
    
    window.tempSelectedDiscount = null;
});

    $('#formVacaciones').on('submit', function(e){
        $(this).find('input[name="trabajador_id[]"], input[name="dias_vacaciones[]"]').remove();
        var valid = false;
        selectedWorkers.forEach(w=>{ var d=parseNumber(w.dias_a_pagar); if(d>0 && d<=w.dias_acumulados){ $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="dias_vacaciones[]" value="${d}">`); valid=true; } });
        if(!valid){ e.preventDefault(); Swal.fire({title:'Error',text:'Asigne días válidos',icon:'error', background:'#1a1a2e', color:'white', confirmButtonText:'<i class="fas fa-check me-2"></i>Entendido'}); return false; }
        return true;
    });

    // Modal Bonos
    function renderBonoWorkerList(term = '') {
        var areaVal = $('#filterBonoArea').val();
        var ccVal = $('#filterBonoCC').val();
        var html = '';
        
        var filtered = trabajadores.filter(w => {
            var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
            var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
            var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
            return matchesSearch && matchesArea && matchesCC;
        });
        
        filtered.forEach(w => {
            var sel = selectedBonos.some(s => s.id == w.id);

            // Resolución del path de la foto
            var workerFotoUrl = '';
            if (w.foto_ruta && w.foto_ruta.trim() !== '') {
                workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
            }

            var avatarHtml = '';
            if (workerFotoUrl) {
                avatarHtml = `<img src="${workerFotoUrl}" style="width:100%; height:100%; object-fit: cover;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2360a5fa%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">`;
            } else {
                avatarHtml = `<i class="fas fa-user"></i>`;
            }

            html += `<div class="worker-item ${sel?'selected':''}" data-id="${w.id}" data-nombre="${w.nombre_completo}" data-codigo="${w.codigo}" data-salario="${w.salario_mensual}" data-foto-ruta="${w.foto_ruta || ''}">
                <input type="checkbox" class="worker-checkbox" ${sel?'checked':''}>
                <div class="worker-avatar">${avatarHtml}</div>
                <div class="worker-info">
                    <div class="worker-name">${w.codigo} - ${w.nombre_completo}</div>
                    <div class="worker-detail"><i class="fas fa-wallet me-1"></i>Salario Básico: $${parseFloat(w.salario_mensual).toFixed(2)}</div>
                </div>
            </div>`;
        });
        $('#bonoWorkerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash me-1"></i>No hay resultados</div>');
    }

// MODIFICADO: Cálculo de totales y presupuesto restante
function updateBonosPreviewTotals() {
    var tDev = 0, val = 0;
    
    // Sumar el devengado de cada trabajador seleccionado
    selectedBonos.forEach(w => { 
        var m = parseFloat(w.monto) || 0; 
        if(m > 0){ tDev += m; val++; } 
    });
    
    // Obtener el monto inicial del input
    var montoInicial = parseFloat($('#montoInicialBono').val()) || 0;
    var montoRestante = montoInicial - tDev;
    
    // Formatear salidas en el resumen
    $('#previewFondoInicial').text('$' + montoInicial.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#previewTotalMonto').text('$' + tDev.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#previewMontoRestante').text('$' + montoRestante.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    
    // Alerta visual de presupuesto excedido
    if (montoRestante < 0) {
        $('#previewMontoRestante')
            .removeClass('info-value-success')
            .addClass('text-danger fw-bold')
            .css('text-shadow', '0 0 0.5rem rgba(239, 68, 68, 0.4)');
    } else {
        $('#previewMontoRestante')
            .removeClass('text-danger fw-bold')
            .addClass('info-value-success')
            .css('text-shadow', 'none');
    }
    
    // Mostrar u ocultar la tarjeta de resumen
    if(val > 0 || montoInicial > 0){
        $('#previewBonoCount').text(val); 
        $('#previewBonos').show(); 
        $('#btnGenerarBonos').prop('disabled', false);
    } else { 
        $('#previewBonos').hide(); 
        $('#btnGenerarBonos').prop('disabled', true); 
    }
}

// NUEVO: Escuchar cambios en el input del monto inicial en tiempo real
$(document).on('input change', '#montoInicialBono', function() {
    updateBonosPreviewTotals();
});

// Variable para evitar que la alerta se muestre de forma repetitiva innecesariamente
var fondoBonoAgotadoAlertado = false;

// Función para verificar la disponibilidad del fondo y alertar si se agota
function verificarFondoBonoAgotado() {
    var montoInicial = parseFloat($('#montoInicialBono').val()) || 0;
    if (montoInicial <= 0) {
        fondoBonoAgotadoAlertado = false;
        return;
    }

    var totalRepartido = 0;
    selectedBonos.forEach(w => { 
        totalRepartido += parseFloat(w.monto) || 0; 
    });

    var montoRestante = montoInicial - totalRepartido;

    // Si el monto restante es menor o igual a cero, se activa la alerta
    if (montoRestante <= 0) {
        if (!fondoBonoAgotadoAlertado) {
            fondoBonoAgotadoAlertado = true; // Se marca como alertado
            
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Fondo Agotado',
                html: `
                    <div class="text-center">
                        <p class="mb-3">El monto total asignado ha consumido o superado el fondo disponible.</p>
                        <div class="p-3 my-2 rounded" style="background: rgba(239, 68, 68, 0.1); border: 0.0625rem solid rgba(239, 68, 68, 0.25); font-size:0.9rem; line-height:1.5;">
                            <strong>Fondo Inicial:</strong> $${montoInicial.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                            <strong>Total Repartido:</strong> <span class="text-danger" style="font-weight: bold;">$${totalRepartido.toLocaleString('en-US', {minimumFractionDigits: 2})}</span><br>
                            <strong>Diferencia:</strong> <span class="text-danger" style="font-weight: bold;">$${montoRestante.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                        </div>
                        <p class="small text-muted mt-3">Por favor, redistribuya los coeficientes o montos asignados para ajustarse al presupuesto establecido.</p>
                    </div>
                `,
                icon: 'warning',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonColor: '#f59e0b',
                confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Redistribuir'
            });
        }
    } else {
        // Si el usuario reduce los montos y vuelve a haber saldo a favor, se restablece el estado de alerta
        fondoBonoAgotadoAlertado = false;
    }
}
    
function updateBonosList(focusId) {
    var html = selectedBonos.map(w => {
        if (!w.tipo) w.tipo = 'porciento'; 
        var isFijo = w.tipo === 'fijo';
        var pctVal = w.porciento !== undefined ? w.porciento : '';
        var coefVal = w.coeficiente !== undefined ? w.coeficiente : ''; // LÍNEA AGREGADA
        var montoVal = w.monto !== undefined ? w.monto : '';
        
        return `
            <div class="selected-worker-card" data-id="${w.id}">
                <div class="selected-worker-header">
                    <span class="worker-name">${w.codigo} - ${w.nombre} - Sal. Básico: $${w.salario.toFixed(2)}</span>
                    <button type="button" class="bono-item-remove" data-id="${w.id}"><i class="fas fa-times"></i></button>
                </div>
                
                <div class="row g-2 align-items-center mt-2">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input bono-chk-tipo" type="checkbox" data-id="${w.id}" id="chkFijo_${w.id}" ${isFijo ? 'checked' : ''} style="cursor:pointer;">
                            <label class="form-check-label small" for="chkFijo_${w.id}" style="cursor:pointer; color: rgba(255,255,255,0.85) !important;">
                                <i class="fas fa-coins me-1 text-warning"></i> Pago Fijo
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-8 d-flex align-items-center gap-2 flex-wrap">
                        ${!isFijo ? `
                            <div class="input-group input-group-sm" style="width:7.5rem;">
                                <span class="input-group-text bg-dark border-secondary text-white">%</span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center bono-porciento-input" 
                                       data-id="${w.id}" value="${pctVal}" step="0.1" min="0" placeholder="Bono %">
                            </div>
                            <div class="input-group input-group-sm" style="width:7.5rem;">
                                <span class="input-group-text bg-dark border-secondary text-white">Coef.</span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center bono-coeficiente-input" 
                                       data-id="${w.id}" value="${coefVal}" step="0.01" min="0" placeholder="Coef.">
                            </div>
                            <span class="badge bg-success small text-wrap text-start ms-2" style="max-width:15.625rem; line-height:1.4;">
                                Calc: <strong class="bono-calc-preview-text text-dark">$${montoVal ? parseFloat(montoVal).toFixed(2) : '0.00'}</strong> 
                                <br><small class="text-dark">(${pctVal || 0} * ${coefVal || 0}) - $${w.salario.toFixed(2)}</small>
                            </span>
                        ` : `
                            <div class="input-group input-group-sm" style="width:10rem;">
                                <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center bono-monto-fijo-input" 
                                       data-id="${w.id}" value="${montoVal}" step="0.01" min="0" placeholder="Monto Fijo">
                            </div>
                            <span class="badge bg-success text-dark small ms-2"><i class="fas fa-edit me-1"></i>Valor Fijo</span>
                        `}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    $('#bonosList').html(html || '<em class="text-white-50"><i class="fas fa-gift me-1"></i>Seleccione trabajadores de la lista</em>');
    
    if (typeof focusId !== 'undefined' && focusId) {
        setTimeout(function() {
            var $input = $('#bonosList').find(`.bono-monto-fijo-input[data-id="${focusId}"], .bono-porciento-input[data-id="${focusId}"]`);
            if ($input.length) {
                $input.focus().select();
            }
        }, 50);
    }
    
    updateBonosPreviewTotals();
}
    
    $('#searchBonoWorker').on('input', function(){ renderBonoWorkerList($(this).val()); });
    
$(document).on('click', '#bonoWorkerList .worker-item', function(){
    var id = $(this).attr('data-id'), idx = selectedBonos.findIndex(s=>s.id==id);
    if(idx>=0) {
        selectedBonos.splice(idx,1); 
    } else {
        selectedBonos.push({
            id: id, 
            nombre: $(this).attr('data-nombre'), 
            codigo: $(this).attr('data-codigo'), 
            salario: parseFloat($(this).attr('data-salario')) || 0,
            tipo: 'porciento',
            porciento: '',
			coeficiente: '',
            monto: ''
        });
    }
    renderBonoWorkerList($('#searchBonoWorker').val()); 
    updateBonosList(id); // Pasa el ID para enfocar el input de bono si fue agregado
});

    $(document).on('change', '.bono-chk-tipo', function() {
        var id = $(this).data('id');
        var isChecked = $(this).is(':checked');
        var w = selectedBonos.find(s => s.id == id);
        if (w) {
            w.tipo = isChecked ? 'fijo' : 'porciento';
            w.monto = '';
            w.porciento = '';
            updateBonosList(); 
        }
    });

$(document).on('input', '.bono-porciento-input, .bono-coeficiente-input', function() {
    var id = $(this).data('id');
    var $card = $(this).closest('.selected-worker-card');
    
    var valPct = parseFloat($card.find('.bono-porciento-input').val()) || 0;
    var valCoef = parseFloat($card.find('.bono-coeficiente-input').val()) || 0;
    
    var w = selectedBonos.find(s => s.id == id);
    if (w) {
        w.porciento = valPct;
        w.coeficiente = valCoef;
        
        // FÓRMULA SOLICITADA: (porcentaje * coeficiente) - salario_escala (mínimo de 0)
        var calculoBruto = (valPct * valCoef) - w.salario;
        w.monto = Math.max(0, Math.roundExcel(calculoBruto, 2));
        
        $card.find('.bono-calc-preview-text').text('$' + w.monto.toFixed(2));
        $card.find('small').text('(' + valPct + ' * ' + valCoef + ') - $' + w.salario.toFixed(2));
        updateBonosPreviewTotals();
    }
});

    $(document).on('input', '.bono-monto-fijo-input', function() {
        var id = $(this).data('id');
        var valMonto = parseFloat($(this).val()) || 0;
        var w = selectedBonos.find(s => s.id == id);
        if (w) {
            w.monto = valMonto;
            updateBonosPreviewTotals();
        }
    });

$(document).on('click', '.bono-item-remove', function(){
    selectedBonos.splice(selectedBonos.findIndex(s => s.id == $(this).data('id')), 1); 
    renderBonoWorkerList($('#searchBonoWorker').val()); 
    updateBonosList();
    
    // Restablecer bandera para permitir futuras alertas si es necesario
    fondoBonoAgotadoAlertado = false;
    verificarFondoBonoAgotado();
});

$('#modalBono').on('show.bs.modal', function(){ 
    selectedBonos=[]; 
    fondoBonoAgotadoAlertado = false; // <-- Restablecer la variable de control
    renderBonoWorkerList(); 
    updateBonosList(); 
    $('#searchBonoWorker').val(''); 
    $('#conceptoBono').val('');
    $('#montoInicialBono').val(''); 
    
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    $('#tipoDescuentoBono').val(td);
    
    window.tempSelectedDiscount = null;
});

$('#formBonos').on('submit', function(e){
    // Limpiar inputs previos dinámicos
    $(this).find('input[name="trabajador_id[]"], input[name="monto_bono[]"]').remove();
    
    if(!$('#conceptoBono').val().trim()){ 
        e.preventDefault(); 
        Swal.fire({
            title: 'Error', 
            text: 'Ingrese un concepto para el bono.', 
            icon: 'error', 
            background: '#1a1a2e', 
            color: 'white', 
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        }); 
        return false; 
    }

    // 1. VALIDACIÓN DEL FONDO INICIAL (Obligatorio y > 0)
    var montoInicialVal = $('#montoInicialBono').val();
    var montoInicial = parseFloat(montoInicialVal) || 0;

    if (!montoInicialVal || montoInicial <= 0) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle text-warning me-2"></i> Fondo No Especificado',
            text: 'Debe especificar el Fondo Inicial para la Distribución (monto mayor a cero).',
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        });
        return false;
    }

    // 2. VALIDACIÓN DE SELECCIÓN DE TRABAJADORES
    if (selectedBonos.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Sin Selección',
            text: 'Debe seleccionar al menos un trabajador para la distribución del bono.',
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return false;
    }

    var totalRepartido = 0;
    var incompleteWorker = null;
    var valid = false;

    // 3. VALIDACIÓN DE MONTOS INDIVIDUALES (> 0)
    selectedBonos.forEach(w => {
        var m = parseFloat(w.monto) || 0;
        if (m <= 0) {
            incompleteWorker = w.nombre;
        } else {
            $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="monto_bono[]" value="${m}">`);
            totalRepartido += m;
            valid = true;
        }
    });

    if (incompleteWorker) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto Requerido',
            html: `Por favor, asigne un monto de bono válido y mayor a cero para el trabajador:<br><strong>${escapeHtml(incompleteWorker)}</strong>`,
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Corregir'
        });
        return false;
    }

    // 4. VALIDACIÓN DE SALDO RESTANTE NEGATIVO
    var montoRestante = montoInicial - totalRepartido;
    if (montoRestante < 0) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Fondo Excedido',
            html: `
                <div class="text-center">
                    <p class="mb-3">No se puede generar el pago del bono porque el <strong>Monto Restante en fondo</strong> es negativo.</p>
                    <div class="p-3 my-2 rounded" style="background: rgba(239, 68, 68, 0.1); border: 0.0625rem solid rgba(239, 68, 68, 0.25); font-size:0.9rem; line-height:1.5;">
                        <strong>Fondo Inicial:</strong> $${montoInicial.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                        <strong>Monto Asignado:</strong> $${totalRepartido.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                        <strong>Monto Restante:</strong> <span class="text-danger" style="font-weight: bold;">$${montoRestante.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                    </div>
                    <p class="small text-muted mt-2">Por favor, reduzca los montos de la distribución antes de procesar.</p>
                </div>
            `,
            icon: 'error',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#ef4444',
            confirmButtonText: '<i class="fas fa-undo me-2"></i>Corregir Distribución'
        });
        return false;
    }

    $(this).append('<input type="hidden" name="confirmar_bono" value="1">'); 
    return true;
});

    // Selector de Consulta Rápida
    function actualizarMesesConsulta() {
        var anio = $('#consultaAnioSelect').val();
        var estado = $('#consultaEstadoSelect').val();
        var tipo = $('#consultaTipoSelect').val();
        var mesSelect = $('#consultaMesSelect');
        
        if (!anio) {
            mesSelect.html('<option value="">-- Seleccione un año --</option>');
            $('#infoNominasPeriodo').html('Seleccione un año para ver los meses con nóminas');
            return;
        }
        
        mesSelect.html('<option value="">Cargando meses...</option>');
        
        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: {
                action: 'get_meses_nominas',
                anio: anio,
                estado: estado,
                tipo: tipo,
                ajax: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.meses.length > 0) {
                    var options = '<option value="">-- Seleccione un mes --</option>';
                    var mesesConNominas = response.meses;
                    var nombresMeses = {
                        1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
                        5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
                        9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
                    };
                    
                    mesesConNominas.forEach(function(mesNum) {
                        var mesNumStr = mesNum.toString().padStart(2, '0');
                        options += `<option value="${mesNumStr}">${nombresMeses[mesNum]}</option>`;
                    });
                    mesSelect.html(options);
                    
                    var estadoTexto = estado ? (estado === 'borrador' ? 'Borrador' : 'Contabilizado') : 'todos';
                    var tipoTexto = tipo ? tipo : 'todos';
                    $('#infoNominasPeriodo').html(`<i class="fas fa-check-circle text-success me-1"></i> ${mesesConNominas.length} mes(es) con nóminas en ${anio} (Estado: ${estadoTexto}, Tipo: ${tipoTexto})`);
                } else {
                    mesSelect.html('<option value="">-- No hay nóminas en este año --</option>');
                    $('#infoNominasPeriodo').html(`<i class="fas fa-info-circle text-warning me-1"></i> No hay nóminas registradas en ${anio} con los filtros seleccionados`);
                }
            },
            error: function() {
                mesSelect.html('<option value="">-- Error al cargar meses --</option>');
                $('#infoNominasPeriodo').html('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error al cargar los meses');
            }
        });
    }

    $('#consultaAnioSelect, #consultaEstadoSelect, #consultaTipoSelect').on('change', function() {
        actualizarMesesConsulta();
    });



    if ($('#consultaAnioSelect').val()) {
        actualizarMesesConsulta();
    }

    function inicializarEstadisticas() {
        setTimeout(function() {
            actualizarEstadisticas();
            if (nominasTable) {
                nominasTable.on('draw', function() {
                    actualizarEstadisticas();
                });
            }
        }, 100);
    }
    
    inicializarEstadisticas();
});

$('#formVacaciones').on('submit', function(e) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...',
        text: 'Agregando trabajadores a la nómina de vacaciones',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });
});

if (window.location.href.indexOf('msg=vacaciones_added') > -1) {
    var urlParams = new URLSearchParams(window.location.search);
    var count = urlParams.get('count') || '0';
    var errores = urlParams.get('errores');
    
    if (errores) {
        Swal.fire({
            icon: 'warning',
            title: 'Vacaciones agregadas con advertencias',
            html: '<p>Se agregaron <strong>' + count + '</strong> trabajadores.</p><p class="text-danger">' + decodeURIComponent(errores) + '</p>',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    } else {
        Swal.fire({
            icon: 'success',
            title: '<i class="fas fa-check-circle me-2"></i> Vacaciones agregadas',
            html: '<p>Se agregaron <strong>' + count + '</strong> trabajadores a la nómina de vacaciones.</p>',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }
    
    window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]msg=vacaciones_added[^&]*/, '').replace(/[?&]count=[^&]*/, '').replace(/[?&]errores=[^&]*/, ''));
}

/*  EXPORTADORES DE LA NOMINA IMPRESA */
// =========================================================================
// PARTE 2: COMPLEMENTO DE FUNCIONES DE APOCYO PARA AGRUPACIÓN Y CONTROL
// =========================================================================

function obtenerCampoAgrupacion(alcance) {
    switch (alcance) {
        case 'area': return 'area';
        case 'centro_costo': return 'centroCosto';
        case 'categoria': return 'categoria';
        case 'escala': return 'escalaDescripcion';
        case 'tipo_contrato': return 'tipoContrato';
        case 'cargo': return 'cargo';
    }
}

function obtenerNombreAgrupacionTexto(alcance) {
    switch (alcance) {
        case 'area': return 'ÁREA';
        case 'centro_costo': return 'CENTRO DE COSTO';
        case 'categoria': return 'CATEGORÍA OCUPACIONAL';
        case 'escala': return 'ESCALA SALARIAL';
        case 'tipo_contrato': return 'TIPO DE CONTRATO';
        case 'cargo': return 'CARGO';
    }
}

function agruparTrabajadores(lista, campo) {
    let grupos = {};
    lista.forEach(t => {
        let clave = t[campo];
        if (!clave || clave === '') clave = 'Sin especificar';
        if (!grupos[clave]) grupos[clave] = [];
        grupos[clave].push(t);
    });
    return grupos;
}

function acumularTotales(objetoDestino, trabajador) {
    objetoDestino.aCobrar += trabajador.aCobrar || 0;
    objetoDestino.bono += trabajador.bono || 0;
    objetoDestino.devengado += trabajador.devengado || 0;
    objetoDestino.impS += trabajador.impS || 0;
    objetoDestino.descuentos += trabajador.descuentos || 0;        // <-- CORRECCIÓN: Agregado
    objetoDestino.retenciones += trabajador.retenciones || 0;
    objetoDestino.pagado += trabajador.pagado || 0;
    objetoDestino.vacDias += trabajador.vacDias || 0;
    objetoDestino.tiempoImp += trabajador.tiempoImp || 0;
    objetoDestino.salarioMensual += trabajador.salarioMensual || 0;  // <-- CORRECCIÓN: Agregado
    // 🔽 EXTRAORDINARIA: acumular también horas e importes de las columnas adicionales
    objetoDestino.horas += trabajador.horas || 0;
    objetoDestino.importeHE += trabajador.importeHE || 0;
    objetoDestino.noctT += trabajador.noctT || 0;
    objetoDestino.importeNtT += trabajador.importeNtT || 0;
    objetoDestino.noctD += trabajador.noctD || 0;
    objetoDestino.importeNtD += trabajador.importeNtD || 0;
    objetoDestino.dt += trabajador.dt || 0;
    objetoDestino.importeDT += trabajador.importeDT || 0;
}

// 🔽 Fila de total/subtotal para EXTRAORDINARIA: totaliza TODAS las columnas numéricas excepto Tarifa (col 5).
// data: { horas, importeHE, noctT, importeNtT, noctD, importeNtD, dt, importeDT, devengado, impS, retenciones, pagado }
// cfg: { trClass, trStyle, cellClass, cellStyle, strong, moneyPrefix }
function filaTotalExtraordinaria(label, data, cfg) {
    cfg = cfg || {};
    var cc = cfg.cellClass ? ' class="' + cfg.cellClass + '"' : '';
    var cs = cfg.cellStyle ? ' style="' + cfg.cellStyle + '"' : '';
    var st = cfg.strong ? '<strong>' : '';
    var en = cfg.strong ? '</strong>' : '';
    var mp = cfg.moneyPrefix !== undefined ? cfg.moneyPrefix : '$';
    var num = function(k) { return st + (data[k] || 0).toFixed(0) + en; };
    var mon = function(k) { return st + mp + (data[k] || 0).toFixed(2) + en; };
    return '<tr' + (cfg.trClass ? ' class="' + cfg.trClass + '"' : '') + (cfg.trStyle ? ' style="' + cfg.trStyle + '"' : '') + '>' +
        '<td colspan="4"' + cc + cs + '>' + st + label + en + '</td>' +
        '<td' + cc + cs + '>' + st + '-' + en + '</td>' +
        '<td' + cc + cs + '>' + num('horas') + '</td>' +
        '<td' + cc + cs + '>' + mon('importeHE') + '</td>' +
        '<td' + cc + cs + '>' + num('noctT') + '</td>' +
        '<td' + cc + cs + '>' + mon('importeNtT') + '</td>' +
        '<td' + cc + cs + '>' + num('noctD') + '</td>' +
        '<td' + cc + cs + '>' + mon('importeNtD') + '</td>' +
        '<td' + cc + cs + '>' + num('dt') + '</td>' +
        '<td' + cc + cs + '>' + mon('importeDT') + '</td>' +
        '<td' + cc + cs + '>' + mon('devengado') + '</td>' +
        '<td' + cc + cs + '>' + mon('impS') + '</td>' +
        '<td' + cc + cs + '>' + mon('descuentos') + '</td>' +
        '<td' + cc + cs + '>' + mon('retenciones') + '</td>' +
        '<td' + cc + cs + '>' + mon('pagado') + '</td>' +
        '<td' + cc + cs + '>' + st + '-' + en + '</td>' +
        '</tr>';
}

function generarFilaExcel(t) {
    return `
        <tr>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.codigo)}</td>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.ci)}</td>
            <td style="border:0.5pt solid #000;">${window.escapeHtml(t.nombre)}</td>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.categoriaCodigo)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.tarifaSal.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${tipoNomina === 'vacaciones' ? (t.diasTomados || 0).toFixed(2) : t.horas}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.aCobrar.toFixed(2)}</td>
            ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${t.bono.toFixed(2)}</td>`}
            ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${t.devengado.toFixed(2)}</td>`}
            <td style="text-align:right; border:0.5pt solid #000;">${t.impS.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.descuentos.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.retenciones.toFixed(2)}</td>
            <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">${t.pagado.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.vacDias.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.tiempoImp.toFixed(2)}</td>
            <td style="border:0.5pt solid #000;"></td>
        </tr>`;
}

function generarFilaPdf(t) {
    return [
        { text: t.codigo, alignment: 'center', style: 'tableCell' },
        { text: t.ci, alignment: 'center', style: 'tableCell' },
        { text: t.nombre, alignment: 'left', style: 'tableCell' },
        { text: t.categoriaCodigo, alignment: 'center', style: 'tableCell' },
        { text: t.tarifaSal.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.horas.toString(), alignment: 'right', style: 'tableCell' },
        { text: t.aCobrar.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.bono.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.devengado.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.impS.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.retenciones.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.pagado.toFixed(2), alignment: 'right', style: 'tableCellBold' },
        { text: t.vacDias.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.tiempoImp.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: '', style: 'tableCell' }
    ];
}

function generarFilaExcelAjuste(t) {
    return `
        <tr>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.codigo)}</td>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.ci)}</td>
            <td style="border:0.5pt solid #000;">${window.escapeHtml(t.nombre)}</td>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.categoriaCodigo)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.aCobrar.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.bono.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.vacDias.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.tiempoImp.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.devengado.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.impS.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.retenciones.toFixed(2)}</td>
            <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">${t.pagado.toFixed(2)}</td>
            <td style="border:0.5pt solid #000;"></td>
        </tr>`;
}

function generarFilaPdfAjuste(t) {
    return [
        { text: t.codigo, alignment: 'center', style: 'tableCell' },
        { text: t.ci, alignment: 'center', style: 'tableCell' },
        { text: t.nombre, alignment: 'left', style: 'tableCell' },
        { text: t.categoriaCodigo, alignment: 'center', style: 'tableCell' },
        { text: t.aCobrar.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.bono.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.vacDias.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.tiempoImp.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.devengado.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.impS.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.retenciones.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.pagado.toFixed(2), alignment: 'right', style: 'tableCellBold' },
        { text: '', style: 'tableCell' }
    ];
}

function construirPaginasDeNomina(trabajadores, alcance) {
    const FILAS_POR_PAGINA = 25;
    let paginas = [];
    let paginaActual = [];
    let contadorFilas = 0;
    
    // CORRECCIÓN: Inicialización de propiedades descuentos y salarioMensual para evitar undefined
    let subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };
    let totalGeneral = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };

    function cerrarPagina() {
        if (paginaActual.length === 0) return;
        paginas.push({ rows: [...paginaActual], subtotal: { ...subtotalPagina } });
        paginaActual = [];
        contadorFilas = 0;
        subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };
    }

    if (alcance === 'general') {
        trabajadores.forEach((t) => {
            if (contadorFilas >= FILAS_POR_PAGINA) cerrarPagina();
            paginaActual.push({ tipo: 'registro', data: t });
            acumularTotales(subtotalPagina, t);
            acumularTotales(totalGeneral, t);
            contadorFilas++;
        });
        if (paginaActual.length > 0) cerrarPagina();
    } else {
        let campoAgrupacion = obtenerCampoAgrupacion(alcance);
        let nombreAgrupacion = obtenerNombreAgrupacionTexto(alcance);
        let grupos = agruparTrabajadores(trabajadores, campoAgrupacion);

        Object.entries(grupos).forEach(([clave, empleados]) => {
            let subTotalGrupo = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };
            let espacioNecesario = empleados.length + 2; 

            if (paginaActual.length > 0 && (contadorFilas + espacioNecesario > FILAS_POR_PAGINA)) {
                cerrarPagina();
            }

            paginaActual.push({ tipo: 'grupo_header', titulo: clave });
            contadorFilas++;

            empleados.forEach((t) => {
                if (contadorFilas >= FILAS_POR_PAGINA) {
                    cerrarPagina();
                    paginaActual.push({ tipo: 'grupo_header', titulo: `${clave} (continuación)` });
                    contadorFilas++;
                }
                paginaActual.push({ tipo: 'registro', data: t });
                acumularTotales(subTotalGrupo, t);
                acumularTotales(subtotalPagina, t);
                acumularTotales(totalGeneral, t);
                contadorFilas++;
            });

            paginaActual.push({ tipo: 'grupo_subtotal', titulo: `TOTAL POR ${nombreAgrupacion}`, data: subTotalGrupo });
            contadorFilas++;
        });
        if (paginaActual.length > 0) cerrarPagina();
    }

    return { paginas, totalGeneral };
}

// =========================================================================
// PARTE 3: EXPORTADORES OFICIALES (EXCEL Y PDF) CON RENDERIZADO DETALLADO
// =========================================================================

function exportarExcelOficial(trabajadores, alcance, filtroNombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin text-success me-2"></i> Generando Excel...',
        html: 'Por favor, espere. Estructurando el reporte oficial <b>SC-4-06</b> en formato de hoja de cálculo.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e', color: '#ffffff'
    });

    setTimeout(function() {
        trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);
        let now = new Date();
        let day = String(now.getDate()).padStart(2, '0');
        let monthNumeric = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let hours24 = now.getHours();
        let ampm = hours24 >= 12 ? 'PM' : 'AM';
        let hours12 = hours24 % 12 || 12;
        let hoursStr = String(hours12).padStart(2, '0');
        let minutesStr = String(now.getMinutes()).padStart(2, '0');
        let secondsStr = String(now.getSeconds()).padStart(2, '0');
        
        let fechaActual12h = `${day}/${monthNumeric}/${year}`;
        let horaActual12h = `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
        
        let hhmmss = `${String(hours24).padStart(2, '0')}${minutesStr}${secondsStr}`;
        let ddmmyyyy = `${day}${monthNumeric}${year}`;
        
        let cleanTipoNomina = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        let cleanMonthName = nombreMesGlobal.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        
        let nombreArchivo = `SC-4-06_${cleanTipoNomina}-${cleanMonthName}-${anioGlobal}_${hhmmss}-${ddmmyyyy}.xls`;
        let scopeText = alcance.toUpperCase() + (filtroNombre ? ': ' + filtroNombre : '');

        let nombreRevisado = window.escapeHtml((typeof especialistaGestion !== 'undefined' ? especialistaGestion : '').toUpperCase());
        let nombreAprobado = window.escapeHtml((typeof jefeProyecto !== 'undefined' ? jefeProyecto : '').toUpperCase());
        let nombreElaborado = window.escapeHtml((typeof especialistaNominas !== 'undefined' ? especialistaNominas : '').toUpperCase());

        const esBono = (tipoNomina === 'bono');
        const esAjuste = (tipoNomina === 'ajuste');
        const esExtraominaria = (tipoNomina === 'extraordinaria');
        const mostrarConcepto = esBono || esAjuste;
        const colsCount = esBono ? 11 : (esAjuste ? 13 : (esExtraominaria ? 19 : (tipoNomina === 'vacaciones' ? 14 : 16)));
        let estructura = construirPaginasDeNomina(trabajadores, alcance);
        let htmlBody = '';

        estructura.paginas.forEach((pag, index) => {
            let numPag = index + 1;
            htmlBody += `
                <tr><td colspan="${colsCount}" style="border:none; height:0.9375rem;"></td></tr>
                <tr><td colspan="${colsCount}" class="title" style="background-color:#004b87; color:#ffffff; font-weight:bold; font-size:11pt; border:0.5pt solid #000;">PÁGINA ${numPag} de ${estructura.paginas.length}</td></tr>
            `;

            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    htmlBody += `<tr><td colspan="${colsCount}" style="background-color: var(--txt); font-weight:bold; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}</b></td></tr>`;
                } else if (row.tipo === 'registro') {
                    if (esBono) {
                        htmlBody += `
                            <tr>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.codigo)}</td>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.ci)}</td>
                                <td style="border:0.5pt solid #000;">${window.escapeHtml(row.data.nombre)}</td>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.categoriaCodigo)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.impS || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                                <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">$${(row.data.pagado || 0).toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>
                            <tr>
                                <td colspan="11" style="text-align:left; border:0.5pt solid #000;">Observación: ${window.escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                    } else if (esAjuste) {
                        htmlBody += generarFilaExcelAjuste(row.data);
                        htmlBody += `
                        <tr>
                            <td colspan="13" style="text-align:left; border:0.5pt solid #000;">Observación: ${window.escapeHtml(row.data.concepto)}</td>
                        </tr>`;
                    } else if (esExtraominaria) {
                        htmlBody += `
                            <tr>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.codigo)}</td>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.ci)}</td>
                                <td style="border:0.5pt solid #000;">${window.escapeHtml(row.data.nombre)}</td>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.categoriaCodigo)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.tarifaSal.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${(row.data.horas || 0).toFixed(0)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeHE || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${(row.data.noctT || 0).toFixed(0)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeNtT || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${(row.data.noctD || 0).toFixed(0)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeNtD || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${(row.data.dt || 0).toFixed(0)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.importeDT || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.impS || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                                <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">$${(row.data.pagado || 0).toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>`;
                    } else {
                        htmlBody += generarFilaExcel(row.data);
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        htmlBody += `
                            <tr style="background-color:#f2f2f2; font-weight:bold;">
                                <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}:</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.devengado.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.impS.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.descuentos.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.retenciones.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000; color:#004b87;"><b>$${row.data.pagado.toFixed(2)}</b></td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    } else if (esAjuste) {
                        htmlBody += `
                            <tr style="background-color:#f2f2f2; font-weight:bold;">
                                <td colspan="4" style="text-align:right; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}:</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.aCobrar.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.bono.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.devengado.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.impS.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.retenciones.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000; color:#004b87;"><b>${row.data.pagado.toFixed(2)}</b></td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    } else if (esExtraominaria) {
                        htmlBody += filaTotalExtraordinaria(window.escapeHtml(row.titulo) + ':', row.data, { trStyle: 'background-color:#f2f2f2; font-weight:bold;', cellStyle: 'text-align:right; border:0.5pt solid #000;', strong: true });
                    } else {
                        htmlBody += `
                            <tr style="background-color:#f2f2f2; font-weight:bold;">
                                <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}:</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.aCobrar.toFixed(2)}</td>
                                ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${row.data.bono.toFixed(2)}</td>`}
                                ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${row.data.devengado.toFixed(2)}</td>`}
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.impS.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.descuentos.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.retenciones.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000; color:#004b87;"><b>${row.data.pagado.toFixed(2)}</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    }
                }
            });

            // Subtotal de la página
            if (esBono) {
                htmlBody += `
                    <tr style="background-color:#fff3cd; font-weight:bold;">
                        <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.devengado.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.impS.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.descuentos.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000; color:#b45309;"><b>$${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            } else if (esAjuste) {
                htmlBody += `
                    <tr style="background-color:#fff3cd; font-weight:bold;">
                        <td colspan="4" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.aCobrar.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.bono.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.vacDias.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.tiempoImp.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.devengado.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.impS.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000; color:#b45309;"><b>${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            } else if (esExtraominaria) {
                htmlBody += filaTotalExtraordinaria('SUBTOTAL PÁGINA ' + numPag + ':', pag.subtotal, { trStyle: 'background-color:#fff3cd; font-weight:bold;', cellStyle: 'text-align:right; border:0.5pt solid #000;', strong: true });
            } else {
                htmlBody += `
                    <tr style="background-color:#fff3cd; font-weight:bold;">
                        <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.aCobrar.toFixed(2)}</td>
                        ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.bono.toFixed(2)}</td>`}
                        ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.devengado.toFixed(2)}</td>`}
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.impS.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.descuentos.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000; color:#b45309;"><b>${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.vacDias.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.tiempoImp.toFixed(2)}</td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            }
        });

        let gTot = estructura.totalGeneral;
        if (esBono) {
            htmlBody += `
                <tr style="background-color:#d9e1f2; font-weight:bold;">
                    <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.devengado.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.impS.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.descuentos.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.retenciones.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000; color:#1e3a8a;"><b>$${gTot.pagado.toFixed(2)}</b></td>
                    <td style="border:0.5pt solid #000;"></td>
                </tr>`;
        } else if (esAjuste) {
            htmlBody += `
                <tr style="background-color:#d9e1f2; font-weight:bold;">
                    <td colspan="4" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.aCobrar.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.bono.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.vacDias.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.tiempoImp.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.devengado.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.impS.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.retenciones.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000; color:#1e3a8a;"><b>${gTot.pagado.toFixed(2)}</b></td>
                    <td style="border:0.5pt solid #000;"></td>
                </tr>`;
        } else if (esExtraominaria) {
            htmlBody += filaTotalExtraordinaria('TOTAL GENERAL NOMINA:', gTot, { trStyle: 'background-color:#d9e1f2; font-weight:bold;', cellStyle: 'text-align:right; border:0.5pt solid #000;', strong: true });
        } else {
            htmlBody += `
                <tr style="background-color:#d9e1f2; font-weight:bold;">
                    <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.aCobrar.toFixed(2)}</td>
                    ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${gTot.bono.toFixed(2)}</td>`}
                    ${tipoNomina === 'vacaciones' ? '' : `<td style="text-align:right; border:0.5pt solid #000;">${gTot.devengado.toFixed(2)}</td>`}
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.impS.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.descuentos.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.retenciones.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000; color:#1e3a8a;"><b>${gTot.pagado.toFixed(2)}</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.vacDias.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.tiempoImp.toFixed(2)}</td>
                    <td style="border:0.5pt solid #000;"></td>
                </tr>`;
        }

        // Títulos de tabla Excel
        let headersExcel = esBono ? `
            <tr class="table-header">
                <td>Código</td><td>CI</td><td>Nombre y Apellidos</td><td>Cat.</td><td>S. Básico</td>
                <td>Deven.</td><td>Imp. CESS</td><td>Descuentos</td><td>Ret Total</td><td>Pagado</td><td>Firma</td>
            </tr>
        ` : esAjuste ? `
            <tr class="table-header">
                <td>Código</td><td>CI</td><td>Nombre y Apellidos</td><td>Cat.</td><td>Monto Ajuste</td>
                <td>Otros Pagos</td><td>Vac. Días</td><td>Vac. Importe</td><td>Total Deven.</td><td>Imp. CESS.</td><td>Total Ret.</td><td>NETO</td><td>Firma</td>
            </tr>
        ` : esExtraominaria ? `
            <tr class="table-header">
                <td>Código</td><td>CI</td><td>Nombre y Apellidos</td><td>Cat.</td><td>Tarf.</td>
                <td>HE/D</td><td>$/HE/D</td><td>Nt 7-23h</td><td>$/Nt 7-23h</td>
                <td>Nt 23-7h</td><td>$/Nt 23-7h</td>
                <td>D/T</td><td>$/DT</td>
                <td>Deven.</td><td>Imp. CESS.</td><td>Dsctos.</td><td>Ret. Tot.</td><td>Pagado</td><td>Firma</td>
            </tr>
        ` : `
            <tr class="table-header">
                <td>Código</td><td>CI</td><td>Nombre y Apellidos</td><td>Cat.</td><td>Tarf.</td><td>${tipoNomina === 'vacaciones' ? 'Días' : 'Horas'}</td>
                <td>A cobrar</td>${tipoNomina === 'vacaciones' ? '' : '<td>Bon.</td>'}${tipoNomina === 'vacaciones' ? '' : '<td>Deven.</td>'}<td>Imp. CESS.</td><td>Dsctos.</td><td>Ret. Tot.</td><td>Pagado</td>
                <td>Vac.</td><td>Tiem. Imp.</td><td>Firma</td>
            </tr>
        `;

        let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="utf-8">
        <!--[if gte mso 9]>
        <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>NOMINA_${cleanMonthName}-${anioGlobal}</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; font-size:9pt; }
            td, th { border: 0.5pt solid #000000; padding:0.3125rem; }
            .title { font-size:13pt; font-weight: bold; text-align: center; }
            .header-meta { background-color: #f2f2f2; font-weight: bold; font-size:8.5pt; }
            .table-header { background-color: #004B87; color: #ffffff; font-weight: bold; text-align: center; }
        </style>
        </head>
        <body>
            <table>
                <tr><td colspan="${colsCount}" class="title" style="border:none;">MODELO SC-4-06 NOMINA - ${window.escapeHtml(nombreEmpresa)}</td></tr>
                <tr>
                    <td colspan="3" class="header-meta"><b>Tipo Nómina:</b> ${window.escapeHtml(tipoNominaTexto)}</td>
                    <td colspan="2" class="header-meta"><b>Período:</b> ${periodoTexto}</td>
                    <td colspan="${esBono ? 3 : (esAjuste ? 3 : 5)}" class="header-meta"><b>Nº Nómina / No. Instrum. Pago:</b> ${window.escapeHtml(numeroNomina)}</td>
                    <td colspan="${esBono ? 3 : (esAjuste ? 5 : 7)}" rowspan="2" style="vertical-align:top; border:0.5pt solid #000; font-size:8.5pt; line-height:1.4;">
                        <b>REVISADO POR:</b> ${nombreRevisado}<br>
                        <b>APROBADO POR:</b> ${nombreAprobado}<br>
                        <b>ELABORADO POR:</b> ${nombreElaborado}<br>
                        <b>CONTABILIZADO POR:</b> _______________________
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="header-meta"><b>Alcance:</b> ${window.escapeHtml(scopeText)}</td>
                    <td colspan="2" class="header-meta">
                        <b>${esBono ? 'Monto a Distribuir: ' + '$' + montoDistribuidoGlobal.toFixed(2) : 'REEUP: ' + escapeHtml(reeup)}</b><br>
                        <b>NIT:</b> ${window.escapeHtml(nitEmpresa)}
                    </td>
                    <td colspan="${esBono ? 3 : (esAjuste ? 3 : 5)}" class="header-meta"><b>Fecha Emisión:</b> ${fechaActual12h} ${horaActual12h}</td>
                </tr>
                ${observacionesCierreGlobal ? `
                <tr>
                    <td colspan="${colsCount}" class="header-meta" style="background-color:#fff3cd; border:0.5pt solid #000;">
                        <b>Observaciones de Cierre:</b> ${window.escapeHtml(observacionesCierreGlobal)}
                    </td>
                </tr>` : ''}
                <tr><td colspan="${colsCount}" style="border:none; height:0.625rem;"></td></tr>
                ${headersExcel}
                ${htmlBody}
            </table>
        </body>
        </html>`;

        let blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        link.click();
        URL.revokeObjectURL(link.href);

        Swal.close();
        Swal.fire({ title: '¡Completado!', text: 'El archivo Excel oficial se ha descargado correctamente.', icon: 'success', timer: 1800, showConfirmButton: false, background: '#1a1a2e', color: '#ffffff' });
    }, 800);
}

// =========================================================================
// EXPORTADOR PDF OFICIAL CON PAGINACIÓN IDÉNTICA (SC-4-06) (CORREGIDO)
// =========================================================================
function exportarPdfOficial(trabajadores, alcance, filtroNombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin text-danger me-2"></i> Generando PDF...',
        html: 'Por favor, espere. Estructurando el reporte oficial <b>SC-4-06</b> en formato de hoja Carta horizontal.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });

    setTimeout(function() {
        trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);
        let now = new Date();
        
        let day = String(now.getDate()).padStart(2, '0');
        let monthNumeric = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let hours24 = now.getHours();
        let ampm = hours24 >= 12 ? 'PM' : 'AM';
        let hours12 = hours24 % 12 || 12;
        let hoursStr = String(hours12).padStart(2, '0');
        let minutesStr = String(now.getMinutes()).padStart(2, '0');
        let secondsStr = String(now.getSeconds()).padStart(2, '0');
        
        let fechaActual12h = `${day}/${monthNumeric}/${year}`;
        let horaActual12h = `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
        
        let hhmmss = `${String(hours24).padStart(2, '0')}${minutesStr}${secondsStr}`;
        let ddmmyyyy = `${day}${monthNumeric}${year}`;
        
        let cleanTipoNomina = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        let cleanMonthName = nombreMesGlobal.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        
        let nombreArchivo = `SC-4-06_${cleanTipoNomina}-${cleanMonthName}-${anioGlobal}_${hhmmss}-${ddmmyyyy}.pdf`;
        let scopeText = alcance.toUpperCase() + (filtroNombre ? ': ' + filtroNombre : '');

        let nombreRevisado = window.escapeHtml((typeof especialistaGestion !== 'undefined' ? especialistaGestion : '').toUpperCase());
        let nombreAprobado = window.escapeHtml((typeof jefeProyecto !== 'undefined' ? jefeProyecto : '').toUpperCase());
        let nombreElaborado = window.escapeHtml((typeof especialistaNominas !== 'undefined' ? especialistaNominas : '').toUpperCase());

        const esBono = (tipoNomina === 'bono');
        const esAjuste = (tipoNomina === 'ajuste');
        const esExtraominaria = (tipoNomina === 'extraordinaria');
        const mostrarConcepto = esBono || esAjuste;
        const colsCount = esBono ? 11 : (esAjuste ? 13 : (esExtraominaria ? 19 : (tipoNomina === 'vacaciones' ? 15 : 17)));
        const widthsConfig = esBono ? [35, 55, '*', 22, 55, 55, 50, 55, 55, 55, 55] : esAjuste ? [30, 45, '*', 18, 42, 45, 24, 42, 42, 38, 42, 42, 42] : esExtraominaria ? [30, 50, '*', 18, 26, 28, 28, 28, 28, 28, 28, 28, 28, 38, 38, 38, 38, 42, 42] : (tipoNomina === 'vacaciones' ? [30, 50, '*', 18, 42, 30, 24, 38, 38, 38, 38, 42, 22, 40, 42] : [30, 50, '*', 18, 42, 30, 24, 38, 30, 38, 38, 38, 38, 42, 22, 40, 42]);

        // 🔽 Fila de total/subtotal para EXTRAORDINARIA: totaliza TODAS las columnas excepto Tarifa
        function filaTotalExtraPdf(label, data, style) {
            function c(k) { return { text: (data[k] || 0).toFixed(2), alignment: 'right', style: style }; }
            function h(k) { return { text: (data[k] || 0).toFixed(0), alignment: 'right', style: style }; }
            var boldStyle = style === 'groupFooter' ? 'groupFooterBold' : (style === 'pageSubtotal' ? 'pageSubtotalBold' : 'tableFooterBold');
            return [
                { text: label, colSpan: 4, alignment: 'right', style: style }, {}, {}, {},
                { text: '-', alignment: 'right', style: style },
                h('horas'), c('importeHE'), h('noctT'), c('importeNtT'), h('noctD'), c('importeNtD'), h('dt'), c('importeDT'),
                c('devengado'), c('impS'), c('descuentos'), c('retenciones'),
                { text: (data.pagado || 0).toFixed(2), alignment: 'right', style: boldStyle },
                { text: '', style: style }
            ];
        }

        let estructura = construirPaginasDeNomina(trabajadores, alcance);
        let docContent = [];

        estructura.paginas.forEach((pag, index) => {
            let numPag = index + 1;
            let tableRows = [];

            // Cabeceras de tabla (11 columnas para Bono / 16 para el resto)
            if (esBono) {
                tableRows.push([
                    { text: 'Código', style: 'tableHeader' },
                    { text: 'CI', style: 'tableHeader' },
                    { text: 'Nombre y Apellidos', style: 'tableHeader' },
                    { text: 'Cat.', style: 'tableHeader' },
                    { text: 'S. Básico', style: 'tableHeader' },
                    { text: 'Deven.', style: 'tableHeader' },
                    { text: 'Imp. CESS', style: 'tableHeader' },
                    { text: 'Ret.', style: 'tableHeader' },
                    { text: 'Ret Total', style: 'tableHeader' },
                    { text: 'Pagado', style: 'tableHeader' },
                    { text: 'Firma', style: 'tableHeader' }
                ]);
            } else if (esAjuste) {
                tableRows.push([
                    { text: 'Código', style: 'tableHeader' },
                    { text: 'CI', style: 'tableHeader' },
                    { text: 'Nombre y Apellidos', style: 'tableHeader' },
                    { text: 'Cat.', style: 'tableHeader' },
                    { text: 'Monto Ajuste', style: 'tableHeader' },
                    { text: 'Otros Pagos', style: 'tableHeader' },
                    { text: 'Vac. Días', style: 'tableHeader' },
                    { text: 'Vac. Importe', style: 'tableHeader' },
                    { text: 'Total Deven.', style: 'tableHeader' },
                    { text: 'Imp. CESS', style: 'tableHeader' },
                    { text: 'Total Ret.', style: 'tableHeader' },
                    { text: 'NETO', style: 'tableHeader' },
                    { text: 'Firma', style: 'tableHeader' }
                ]);
            } else if (esExtraominaria) {
                tableRows.push([
                    { text: 'Código', style: 'tableHeader' },
                    { text: 'CI', style: 'tableHeader' },
                    { text: 'Nombre y Apellidos', style: 'tableHeader' },
                    { text: 'Cat.', style: 'tableHeader' },
                    { text: 'Tarf.', style: 'tableHeader' },
                    { text: 'HE/D', style: 'tableHeader' },
                    { text: '$/HE/D', style: 'tableHeader' },
                    { text: 'Nt 7-23h', style: 'tableHeader' },
                    { text: '$/Nt 7-23h', style: 'tableHeader' },
                    { text: 'Nt 23-7h', style: 'tableHeader' },
                    { text: '$/Nt 23-7h', style: 'tableHeader' },
                    { text: 'D/T', style: 'tableHeader' },
                    { text: '$/DT', style: 'tableHeader' },
                    { text: 'Deven.', style: 'tableHeader' },
                    { text: 'Imp. CESS', style: 'tableHeader' },
                    { text: 'Dsctos', style: 'tableHeader' },
                    { text: 'Ret. Tot.', style: 'tableHeader' },
                    { text: 'Pagado', style: 'tableHeader' },
                    { text: 'Firma', style: 'tableHeader' }
                ]);
            } else {
                tableRows.push([
                    { text: 'Código', style: 'tableHeader' },
                    { text: 'CI', style: 'tableHeader' },
                    { text: 'Nombre y Apellidos', style: 'tableHeader' },
                    { text: 'Cat.', style: 'tableHeader' },
                    { text: 'S. Básico', style: 'tableHeader' },
                    { text: 'Tarf.', style: 'tableHeader' },
                    { text: (tipoNomina === 'vacaciones' ? 'Días' : 'Horas'), style: 'tableHeader' },
                    { text: 'A cobrar', style: 'tableHeader' },
                    ...(tipoNomina === 'vacaciones' ? [] : [{ text: 'Bon.', style: 'tableHeader' }]),
                    ...(tipoNomina === 'vacaciones' ? [] : [{ text: 'Deven.', style: 'tableHeader' }]),
                    { text: 'Imp. CESS', style: 'tableHeader' },
                    { text: 'Dsctos', style: 'tableHeader' },
                    { text: 'Ret. Tot.', style: 'tableHeader' },
                    { text: 'Pagado', style: 'tableHeader' },
                    { text: 'Vac.', style: 'tableHeader' },
                    { text: 'Tiem. Imp.', style: 'tableHeader' },
                    { text: 'Firma', style: 'tableHeader' }
                ]);
            }

            // Filas de Datos
            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    let headerRow = [{ text: row.titulo.toUpperCase(), colSpan: colsCount, style: 'groupHeader', alignment: 'left' }];
                    for (let i = 1; i < colsCount; i++) headerRow.push({});
                    tableRows.push(headerRow);
                } else if (row.tipo === 'registro') {
                    // Obtener Salario Básico de forma segura del origen de datos local
                    var dbWorker = window.trabajadores.find(function(w) { 
                        return w.codigo.toString().trim() === row.data.codigo.toString().trim(); 
                    });
                    var salarioBasicoResuelto = dbWorker ? parseFloat(dbWorker.salario_mensual) : (row.data.salarioMensual || 0);

                    if (esBono) {
                        tableRows.push([
                            { text: row.data.codigo, alignment: 'center', style: 'tableCell' },
                            { text: row.data.ci, alignment: 'center', style: 'tableCell' },
                            { text: row.data.nombre, alignment: 'left', style: 'tableCell' },
                            { text: row.data.categoriaCodigo, alignment: 'center', style: 'tableCell' },
                            { text: salarioBasicoResuelto.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.devengado || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.impS || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.descuentos || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.retenciones || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.pagado || 0).toFixed(2), alignment: 'right', style: 'tableCellBold' },
                            { text: '', style: 'tableCell' }
                        ]);
                        tableRows.push([{ text: 'Observación: ' + (row.data.concepto || ''), colSpan: 11, alignment: 'left', style: 'tableCell' }]);
                    } else if (esAjuste) {
                        tableRows.push(generarFilaPdfAjuste(row.data));
                        tableRows.push([{ text: 'Observación: ' + (row.data.concepto || ''), colSpan: 13, alignment: 'left', style: 'tableCell' }]);
                    } else if (esExtraominaria) {
                        tableRows.push([
                            { text: row.data.codigo, alignment: 'center', style: 'tableCell' },
                            { text: row.data.ci, alignment: 'center', style: 'tableCell' },
                            { text: row.data.nombre, alignment: 'left', style: 'tableCell' },
                            { text: row.data.categoriaCodigo, alignment: 'center', style: 'tableCell' },
                            { text: row.data.tarifaSal.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.horas || 0).toFixed(0), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.importeHE || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.noctT || 0).toFixed(0), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.importeNtT || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.noctD || 0).toFixed(0), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.importeNtD || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.dt || 0).toFixed(0), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.importeDT || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.devengado || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.impS || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.descuentos || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.retenciones || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.pagado || 0).toFixed(2), alignment: 'right', style: 'tableCellBold' },
                            { text: '', style: 'tableCell' }
                        ]);
                    } else {
                        tableRows.push([
                            { text: row.data.codigo, alignment: 'center', style: 'tableCell' },
                            { text: row.data.ci, alignment: 'center', style: 'tableCell' },
                            { text: row.data.nombre, alignment: 'left', style: 'tableCell' },
                            { text: row.data.categoriaCodigo, alignment: 'center', style: 'tableCell' },
                            { text: salarioBasicoResuelto.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.tarifaSal.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (tipoNomina === 'vacaciones' ? (row.data.diasTomados || 0).toFixed(2) : row.data.horas.toString()), alignment: 'right', style: 'tableCell' },
                            { text: row.data.aCobrar.toFixed(2), alignment: 'right', style: 'tableCell' },
                            ...(tipoNomina === 'vacaciones' ? [] : [{ text: row.data.bono.toFixed(2), alignment: 'right', style: 'tableCell' }]),
                            ...(tipoNomina === 'vacaciones' ? [] : [{ text: row.data.devengado.toFixed(2), alignment: 'right', style: 'tableCell' }]),
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.descuentos || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'tableCellBold' },
                            { text: row.data.vacDias.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.tiempoImp.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: '', style: 'tableCell' }
                        ]);
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        tableRows.push([
                            { text: `${row.titulo}:`, colSpan: 5, alignment: 'right', style: 'groupFooter' },
                            {}, {}, {}, {},
                            { text: row.data.devengado.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.descuentos.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'groupFooterBold' },
                            { text: '', style: 'groupFooter' }
                        ]);
                    } else if (esAjuste) {
                        tableRows.push([
                            { text: `${row.titulo}:`, colSpan: 4, alignment: 'right', style: 'groupFooter' },
                            {}, {}, {},
                            { text: row.data.aCobrar.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.bono.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.vacDias.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.tiempoImp.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.devengado.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'groupFooterBold' },
                            { text: '', style: 'groupFooter' }
                        ]);
                    } else if (esExtraominaria) {
                        tableRows.push(filaTotalExtraPdf(row.titulo + ':', row.data, 'groupFooter'));
                    } else {
                        tableRows.push([
                            { text: `${row.titulo}:`, colSpan: 7, alignment: 'right', style: 'groupFooter' },
                            {}, {}, {}, {}, {}, {},
                            { text: row.data.aCobrar.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            ...(tipoNomina === 'vacaciones' ? [] : [{ text: row.data.bono.toFixed(2), alignment: 'right', style: 'groupFooter' }]),
                            ...(tipoNomina === 'vacaciones' ? [] : [{ text: row.data.devengado.toFixed(2), alignment: 'right', style: 'groupFooter' }]),
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: (row.data.descuentos || 0).toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'groupFooterBold' },
                            { text: row.data.vacDias.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.tiempoImp.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: '', style: 'groupFooter' }
                        ]);
                    }
                }
            });

            // Subtotal de Página
            if (esBono) {
                tableRows.push([
                    { text: `SUBTOTAL PÁGINA ${numPag}:`, colSpan: 5, alignment: 'right', style: 'pageSubtotal' },
                    {}, {}, {}, {},
                    { text: pag.subtotal.devengado.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.impS.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.descuentos.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.retenciones.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.pagado.toFixed(2), alignment: 'right', style: 'pageSubtotalBold' },
                    { text: '', style: 'pageSubtotal' }
                ]);
            } else if (esAjuste) {
                tableRows.push([
                    { text: `SUBTOTAL PÁGINA ${numPag}:`, colSpan: 4, alignment: 'right', style: 'pageSubtotal' },
                    {}, {}, {},
                    { text: pag.subtotal.aCobrar.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.bono.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.vacDias.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.tiempoImp.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.devengado.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.impS.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.retenciones.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.pagado.toFixed(2), alignment: 'right', style: 'pageSubtotalBold' },
                    { text: '', style: 'pageSubtotal' }
                ]);
            } else if (esExtraominaria) {
                tableRows.push(filaTotalExtraPdf('SUBTOTAL PÁGINA ' + numPag + ':', pag.subtotal, 'pageSubtotal'));
            } else {
                tableRows.push([
                    { text: `SUBTOTAL PÁGINA ${numPag}:`, colSpan: 7, alignment: 'right', style: 'pageSubtotal' },
                    {}, {}, {}, {}, {}, {},
                    { text: pag.subtotal.aCobrar.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    ...(tipoNomina === 'vacaciones' ? [] : [{ text: pag.subtotal.bono.toFixed(2), alignment: 'right', style: 'pageSubtotal' }]),
                    ...(tipoNomina === 'vacaciones' ? [] : [{ text: pag.subtotal.devengado.toFixed(2), alignment: 'right', style: 'pageSubtotal' }]),
                    { text: pag.subtotal.impS.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.descuentos.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.retenciones.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.pagado.toFixed(2), alignment: 'right', style: 'pageSubtotalBold' },
                    { text: pag.subtotal.vacDias.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.tiempoImp.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: '', style: 'pageSubtotal' }
                ]);
            }

            // Fila de Total General en la última página
            if (numPag === estructura.paginas.length) {
                let gTot = estructura.totalGeneral;
                if (esBono) {
                    tableRows.push([
                        { text: 'TOTAL GENERAL NOMINA:', colSpan: 5, alignment: 'right', style: 'tableFooter' },
                        {}, {}, {}, {},
                        { text: gTot.devengado.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.impS.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.descuentos.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.retenciones.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.pagado.toFixed(2), alignment: 'right', style: 'tableFooterBold' },
                        { text: '', style: 'tableFooter' }
                    ]);
                } else if (esAjuste) {
                    tableRows.push([
                        { text: 'TOTAL GENERAL NOMINA:', colSpan: 4, alignment: 'right', style: 'tableFooter' },
                        {}, {}, {},
                        { text: gTot.aCobrar.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.bono.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.vacDias.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.tiempoImp.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.devengado.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.impS.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.retenciones.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.pagado.toFixed(2), alignment: 'right', style: 'tableFooterBold' },
                        { text: '', style: 'tableFooter' }
                    ]);
                } else if (esExtraominaria) {
                    tableRows.push(filaTotalExtraPdf('TOTAL GENERAL NOMINA:', gTot, 'tableFooter'));
                } else {
                    tableRows.push([
                        { text: 'TOTAL GENERAL NOMINA:', colSpan: 7, alignment: 'right', style: 'tableFooter' },
                        {}, {}, {}, {}, {}, {},
                        { text: gTot.aCobrar.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        ...(tipoNomina === 'vacaciones' ? [] : [{ text: gTot.bono.toFixed(2), alignment: 'right', style: 'tableFooter' }]),
                        ...(tipoNomina === 'vacaciones' ? [] : [{ text: gTot.devengado.toFixed(2), alignment: 'right', style: 'tableFooter' }]),
                        { text: gTot.impS.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.descuentos.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.retenciones.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.pagado.toFixed(2), alignment: 'right', style: 'tableFooterBold' },
                        { text: gTot.vacDias.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.tiempoImp.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: '', style: 'tableFooter' }
                    ]);
                }
            }

            // Configuración del encabezado dinámico de firmas y metadatos
            let headerBlock = {
                table: {
                    widths: [45, '*', 230],
                    body: [
                        [
                            logoBase64 ? { image: logoBase64, width: 32, alignment: 'center' } : { text: '' },
                            {
                                stack: [
                                    { text: `MODELO SC-4-06 NOMINA - ${nombreEmpresa.toUpperCase()}`, fontSize: 10, bold: true },
                                    {
                                        columns: [
                                            { text: `Tipo: ${tipoNominaTexto}`, fontSize: 7, bold: true },
                                            { text: `Período: ${periodoTexto}`, fontSize: 7 },
                                            { text: `Nº Nómina: ${numeroNomina === 'Borrador' ? 'Borrador' : numeroNomina}`, fontSize: 7, bold: true }
                                        ]
                                    },
                                    {
										columns: [
											{ text: `Alcance: ${scopeText}`, fontSize: 7 },
											// 🔽 MODIFICA ESTA LÍNEA
											{ 
												text: esBono 
													? (montoDistribuidoGlobal > 0 
														? `Monto a Distribuir: $${montoDistribuidoGlobal.toFixed(2)}` 
														: 'Monto a Distribuir: (Hasta que se contabilice)') 
													: `REEUP: ${reeup}\nNIT: ${nitEmpresa}`,
												fontSize: 7,
												alignment: 'left'
											},
											{ text: `Emisión: ${fechaActual12h} ${horaActual12h}`, fontSize: 7 }
										]
                                    },
                                    observacionesCierreGlobal
                                        ? { text: `Observaciones de Cierre: ${observacionesCierreGlobal}`, fontSize: 6.5, bold: true, margin: [0, 2, 0, 0] }
                                        : { text: '', fontSize: 6.5 }
                                ],
                                margin: [5, 2, 0, 0]
                            },
                            {
                                stack: [
                                    { text: `REVISADO POR: ${nombreRevisado}`, fontSize: 6.5, bold: true },
                                    { text: `APROBADO POR: ${nombreAprobado}`, fontSize: 6.5, bold: true, margin: [0, 2, 0, 0] },
                                    { text: `ELABORADO POR: ${nombreElaborado}`, fontSize: 6.5, margin: [0, 2, 0, 0] },
                                    { text: 'CONTABILIZADO POR: _______________________', fontSize: 6.5, margin: [0, 2, 0, 0] }
                                ],
                                margin: [5, 1, 0, 0]
                            }
                        ]
                    ]
                },
                margin: [0, 0, 0, 8],
                layout: {
                    hLineWidth: function() { return 0.5; },
                    vLineWidth: function() { return 0.5; },
                    hLineColor: function() { return '#000000'; },
                    vLineColor: function() { return '#000000'; }
                }
            };

            // Mostrar el Monto a Distribuir en el Header del PDF si es Bono
            if (esBono) {
                headerBlock.table.body.push([
                    { text: 'Monto a Distribuir:', style: 'tableHeader', colSpan: 2, fillColor: '#004B87', color: '#ffffff', alignment: 'right', fontSize: 7.5 },
                    {},
                    { text: '$' + (montoDistribuidoGlobal || 0).toFixed(2), fontSize: 8, bold: true, alignment: 'left', margin: [5, 2, 0, 0] }
                ]);
            }

            docContent.push(headerBlock);
            docContent.push({
                table: {
                    headerRows: 1,
                    widths: widthsConfig,
                    body: tableRows
                },
                layout: {
                    hLineWidth: function() { return 0.5; },
                    vLineWidth: function() { return 0.5; },
                    hLineColor: function() { return '#000000'; },
                    vLineColor: function() { return '#000000'; },
                    paddingLeft: function() { return 2; }, 
                    paddingRight: function() { return 2; },
                    paddingTop: function() { return 3; }, 
                    paddingBottom: function() { return 3; }
                }
            });

            if (numPag < estructura.paginas.length) {
                docContent.push({ text: '', pageBreak: 'after', margin: [0, 0, 0, 0] });
            }
        });

        var docDefinition = {
            pageOrientation: 'landscape',
            pageSize: 'LETTER',
            pageMargins: [15, 15, 15, 30],
            footer: function(currentPage, pageCount) {
                return {
                    margin: [15, 5, 15, 0],
                    columns: [
                        { text: 'Sistema de Gestión de Nóminas - Modelo Oficial SC-4-06', fontSize: 7, alignment: 'left', color: '#555555' },
                        { text: `Página ${currentPage} de ${pageCount}`, fontSize: 7.5, alignment: 'right', bold: true }
                    ]
                };
            },
            content: docContent,
            styles: {
                tableHeader: { fontSize: 7.2, bold: true, color: '#ffffff', fillColor: '#004B87', alignment: 'center' },
                tableCell: { fontSize: 6.8, color: '#000000' },
                tableCellBold: { fontSize: 6.8, bold: true, color: '#000000' },
                groupHeader: { fontSize: 7, bold: true, color: '#000000', fillColor: '#e0e0e0', margin: [0, 1, 0, 1] },
                groupFooter: { fontSize: 6.8, bold: true, color: '#000000', fillColor: '#f9f9f9' },
                groupFooterBold: { fontSize: 6.8, bold: true, color: '#004B87', fillColor: '#f9f9f9' },
                pageSubtotal: { fontSize: 7, bold: true, color: '#000000', fillColor: '#fff3cd' },
                pageSubtotalBold: { fontSize: 7, bold: true, color: '#b45309', fillColor: '#fff3cd' },
                tableFooter: { fontSize: 7.2, bold: true, color: '#000000', fillColor: '#d9e1f2' },
                tableFooterBold: { fontSize: 7.2, bold: true, color: '#1e3a8a', fillColor: '#d9e1f2' }
            }
        };

        pdfMake.createPdf(docDefinition).download(nombreArchivo);

        Swal.close();
        Swal.fire({
            title: '¡Completado!',
            text: 'El reporte PDF oficial se ha descargado correctamente.',
            icon: 'success',
            timer: 1800,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }, 800);
}
							
							
							
							
function exportarWordOficial(trabajadores, alcance, filtroNombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin text-primary me-2"></i> Generando Word...',
        html: 'Por favor, espere. Estructurando el reporte oficial <b>SC-4-06</b> en formato Word (.doc) con paginación exacta.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });

    setTimeout(function() {
        trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);
        let now = new Date();
        
        let day = String(now.getDate()).padStart(2, '0');
        let monthNumeric = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let hours24 = now.getHours();
        let ampm = hours24 >= 12 ? 'PM' : 'AM';
        let hours12 = hours24 % 12 || 12;
        let hoursStr = String(hours12).padStart(2, '0');
        let minutesStr = String(now.getMinutes()).padStart(2, '0');
        let secondsStr = String(now.getSeconds()).padStart(2, '0');
        
        let fechaActual12h = `${day}/${monthNumeric}/${year}`;
        let horaActual12h = `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
        
        let hhmmss = `${String(hours24).padStart(2, '0')}${minutesStr}${secondsStr}`;
        let ddmmyyyy = `${day}${monthNumeric}${year}`;
        
        let cleanTipoNomina = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        let cleanMonthName = nombreMesGlobal.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        
        let nombreArchivo = `SC-4-06_${cleanTipoNomina}-${cleanMonthName}-${anioGlobal}_${hhmmss}-${ddmmyyyy}.doc`;
        let scopeText = alcance.toUpperCase() + (filtroNombre ? ': ' + filtroNombre : '');

        let nombreRevisado = escapeHtml((typeof especialistaGestion !== 'undefined' ? especialistaGestion : '').toUpperCase());
        let nombreAprobado = escapeHtml((typeof jefeProyecto !== 'undefined' ? jefeProyecto : '').toUpperCase());
        let nombreElaborado = escapeHtml((typeof especialistaNominas !== 'undefined' ? especialistaNominas : '').toUpperCase());
        let codigoMostrado = (numeroNomina === 'S/N' || numeroNomina === 'Borrador' || !numeroNomina) ? '' : escapeHtml(numeroNomina);

        let cleanLogo = logoBase64 ? logoBase64.replace(/(\r\n|\n|\r)/gm, "") : "";

        const FILAS_POR_PAGINA = 15;
        const esBono = (tipoNomina === 'bono');
        const esAjuste = (tipoNomina === 'ajuste');
        const esExtraominaria = (tipoNomina === 'extraordinaria');
        const mostrarConcepto = esBono || esAjuste;
        const colsCount = esBono ? 11 : (esAjuste ? 13 : (esExtraominaria ? 19 : (tipoNomina === 'vacaciones' ? 14 : 16)));
        
        let paginas = [];
        let paginaActual = [];
        let contadorFilas = 0;
        
        let subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };
        let totalGeneral = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };

        function cerrarPagina() {
            if (paginaActual.length === 0) return;
            paginas.push({ rows: [...paginaActual], subtotal: { ...subtotalPagina } });
            paginaActual = [];
            contadorFilas = 0;
            subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };
        }

        if (alcance === 'general') {
            trabajadores.forEach((t) => {
                if (contadorFilas >= FILAS_POR_PAGINA) cerrarPagina();
                paginaActual.push({ tipo: 'registro', data: t });
                acumularTotales(subtotalPagina, t);
                acumularTotales(totalGeneral, t);
                contadorFilas++;
            });
            if (paginaActual.length > 0) cerrarPagina();
        } else {
            let campoAgrupacion = obtenerCampoAgrupacion(alcance);
            let nombreAgrupacion = obtenerNombreAgrupacionTexto(alcance);
            let grupos = agruparTrabajadores(trabajadores, campoAgrupacion);

            Object.entries(grupos).forEach(([clave, empleados]) => {
let subTotalGrupo = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0, horas: 0, importeHE: 0, noctT: 0, importeNtT: 0, noctD: 0, importeNtD: 0, dt: 0, importeDT: 0 };
                let espacioNecesario = empleados.length + 2;

                if (paginaActual.length > 0 && (contadorFilas + espacioNecesario > FILAS_POR_PAGINA)) {
                    cerrarPagina();
                }

                paginaActual.push({ tipo: 'grupo_header', titulo: clave });
                contadorFilas++;

                empleados.forEach((t) => {
                    if (contadorFilas >= FILAS_POR_PAGINA) {
                        cerrarPagina();
                        paginaActual.push({ tipo: 'grupo_header', titulo: `${clave} (cont.)` });
                        contadorFilas++;
                    }
                    paginaActual.push({ tipo: 'registro', data: t });
                    acumularTotales(subTotalGrupo, t);
                    acumularTotales(subtotalPagina, t);
                    acumularTotales(totalGeneral, t);
                    contadorFilas++;
                });

                paginaActual.push({ tipo: 'grupo_subtotal', titulo: `TOTAL POR ${nombreAgrupacion}`, data: subTotalGrupo });
                contadorFilas++;
            });
            if (paginaActual.length > 0) cerrarPagina();
        }

        let pagesHtml = '';

        paginas.forEach((pag, index) => {
            let numPag = index + 1;
            let cuerpoHtml = '';

            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    cuerpoHtml += `<tr><td colspan="${colsCount}" style="background-color: var(--txt); font-weight:bold; border:0.5pt solid #000; font-size:7.5pt;"><b>${escapeHtml(row.titulo)}</b></td></tr>`;
                } else if (row.tipo === 'registro') {
                    if (esBono) {
                        cuerpoHtml += `
                            <tr>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.codigo)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.ci)}</td>
                                <td class="text-left" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.nombre)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.categoriaCodigo)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.devengado || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.impS || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt; font-weight:bold;">$${(row.data.pagado || 0).toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>
                            <tr>
                                <td class="text-left" colspan="11" style="border:0.5pt solid #000; font-size:7pt;">Observación: ${escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                    } else if (esAjuste) {
                        cuerpoHtml += `
                            <tr>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.codigo)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.ci)}</td>
                                <td class="text-left" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.nombre)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.categoriaCodigo)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.aCobrar.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.bono.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt; font-weight:bold;">${row.data.pagado.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>
                            <tr>
                                <td class="text-left" colspan="13" style="border:0.5pt solid #000; font-size:7pt;">Observación: ${escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                    } else if (esExtraominaria) {
                        cuerpoHtml += `
                            <tr>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.codigo)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.ci)}</td>
                                <td class="text-left" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.nombre)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.categoriaCodigo)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.tarifaSal.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.horas || 0).toFixed(0)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.importeHE || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.noctT || 0).toFixed(0)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.importeNtT || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.noctD || 0).toFixed(0)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.importeNtD || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.dt || 0).toFixed(0)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.importeDT || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.devengado || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.impS || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.retenciones || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt; font-weight:bold;">${(row.data.pagado || 0).toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>`;
                    } else {
                        cuerpoHtml += `
                            <tr>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.codigo)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.ci)}</td>
                                <td class="text-left" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.nombre)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.categoriaCodigo)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.tarifaSal.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${tipoNomina === 'vacaciones' ? (row.data.diasTomados || 0).toFixed(2) : row.data.horas}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.aCobrar.toFixed(2)}</td>
                                ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.bono.toFixed(2)}</td>`}
                                ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.devengado.toFixed(2)}</td>`}
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt; font-weight:bold;">${row.data.pagado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>`;
                        if (esAjuste) {
                            cuerpoHtml += `
                            <tr>
                                <td class="text-left" colspan="15" style="border:0.5pt solid #000; font-size:7pt;">Observación: ${escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                        }
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        cuerpoHtml += `
                            <tr style="background-color:#f9f9f9; font-weight:bold; font-size:7pt;">
                                <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>${escapeHtml(row.titulo)}:</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.descuentos.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; color:#004b87;"><b>$${row.data.pagado.toFixed(2)}</b></td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    } else if (esAjuste) {
                        cuerpoHtml += `
                            <tr style="background-color:#f9f9f9; font-weight:bold; font-size:7pt;">
                                <td colspan="4" style="text-align:right; border:0.5pt solid #000;"><b>${escapeHtml(row.titulo)}:</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.aCobrar.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.bono.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; color:#004b87;"><b>${row.data.pagado.toFixed(2)}</b></td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    } else if (esExtraominaria) {
                        cuerpoHtml += filaTotalExtraordinaria(escapeHtml(row.titulo) + ':', row.data, { trStyle: 'background-color:#f9f9f9; font-weight:bold; font-size:7pt;', cellStyle: 'text-align:right; border:0.5pt solid #000;', moneyPrefix: '' });
                    } else {
                        cuerpoHtml += `
                            <tr style="background-color:#f9f9f9; font-weight:bold; font-size:7pt;">
                                <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>${escapeHtml(row.titulo)}:</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.aCobrar.toFixed(2)}</td>
                                ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000;">${row.data.bono.toFixed(2)}</td>`}
                                ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000;">${row.data.devengado.toFixed(2)}</td>`}
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; color:#004b87;"><b>${row.data.pagado.toFixed(2)}</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    }
                }
            });

            // Subtotal de la página
            if (esBono) {
                cuerpoHtml += `
                    <tr style="background-color:#fff3cd; font-weight:bold; font-size:7pt;">
                        <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.devengado.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.impS.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.descuentos.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000; color:#b45309;"><b>$${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            } else if (esAjuste) {
                cuerpoHtml += `
                    <tr style="background-color:#fff3cd; font-weight:bold; font-size:7pt;">
                        <td colspan="4" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.aCobrar.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.bono.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.vacDias.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.tiempoImp.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.devengado.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.impS.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000; color:#b45309;"><b>${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            } else if (esExtraominaria) {
                cuerpoHtml += filaTotalExtraordinaria('SUBTOTAL PÁGINA ' + numPag + ':', pag.subtotal, { trStyle: 'background-color:#fff3cd; font-weight:bold; font-size:7pt;', cellStyle: 'text-align:right; border:0.5pt solid #000;', moneyPrefix: '' });
            } else {
                cuerpoHtml += `
                    <tr style="background-color:#fff3cd; font-weight:bold; font-size:7pt;">
                        <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.aCobrar.toFixed(2)}</td>
                        ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.bono.toFixed(2)}</td>`}
                        ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.devengado.toFixed(2)}</td>`}
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.impS.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.descuentos.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000; color:#b45309;"><b>${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.vacDias.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.tiempoImp.toFixed(2)}</td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            }

            // Totales acumulados al pie de la última página
            if (numPag === paginas.length) {
                let gTot = totalGeneral;
                if (esBono) {
                    cuerpoHtml += `
                        <tr style="background-color:#d9e1f2; font-weight:bold; font-size:7pt;">
                            <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.devengado.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.impS.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.descuentos.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.retenciones.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; color:#1e3a8a;"><b>$${gTot.pagado.toFixed(2)}</b></td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                } else if (esAjuste) {
                    cuerpoHtml += `
                        <tr style="background-color:#d9e1f2; font-weight:bold; font-size:7pt;">
                            <td colspan="4" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.aCobrar.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.bono.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.vacDias.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.tiempoImp.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.devengado.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.impS.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.retenciones.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; color:#1e3a8a;"><b>${gTot.pagado.toFixed(2)}</b></td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                } else if (esExtraominaria) {
                    cuerpoHtml += filaTotalExtraordinaria('TOTAL GENERAL NOMINA:', gTot, { trStyle: 'background-color:#d9e1f2; font-weight:bold; font-size:7pt;', cellStyle: 'text-align:right; border:0.5pt solid #000;', moneyPrefix: '' });
                } else {
                    cuerpoHtml += `
                        <tr style="background-color:#d9e1f2; font-weight:bold; font-size:7pt;">
                            <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.aCobrar.toFixed(2)}</td>
                            ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000;">${gTot.bono.toFixed(2)}</td>`}
                            ${tipoNomina === 'vacaciones' ? '' : `<td class="text-right" style="border:0.5pt solid #000;">${gTot.devengado.toFixed(2)}</td>`}
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.impS.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.descuentos.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.retenciones.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; color:#1e3a8a;"><b>${gTot.pagado.toFixed(2)}</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.vacDias.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.tiempoImp.toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                }
            }

            let breakBlock = (numPag > 1) ? '<br style="page-break-before:always; clear:both; mso-break-type:section-break" />' : '';

            // Encabezados de tabla para Word
            let headerTR = esBono ? `
                <tr style="background-color:#004b87; color:#ffffff; font-weight:bold; text-align:center;">
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">Código</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:4.0625rem;">CI</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:10.625rem;">Nombre y Apellidos</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.375rem;">Cat.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.75rem;">S. Básico</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.4375rem;">Deven.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.4375rem;">Imp. CESS</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.4375rem;">Ret.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.4375rem;">Ret Total</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.4375rem;">Pagado</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:4.0625rem;">Firma</th>
                </tr>
            ` : esExtraominaria ? `
                <tr style="background-color:#004b87; color:#ffffff; font-weight:bold; text-align:center;">
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.1875rem;">Código</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.125rem;">CI</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:6.875rem;">Nombre y Apellidos</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.375rem;">Cat.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.375rem;">Tarf.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.875rem;">HE/D</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">$/HE/D</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">Nt 7-23h</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">$/Nt 7-23h</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">Nt 23-7h</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">$/Nt 23-7h</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">DT</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.5625rem;">$/DT</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.5rem;">Deven.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.5rem;">Imp. CESS</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.5rem;">Dsctos.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.5rem;">Ret. Tot.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">Pagado</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.125rem;">Firma</th>
                </tr>
            ` : `
                <tr style="background-color:#004b87; color:#ffffff; font-weight:bold; text-align:center;">
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.1875rem;">Código</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.4375rem;">CI</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:8.125rem;">Nombre y Apellidos</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.375rem;">Cat.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.375rem;">Tarf.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.75rem;">${tipoNomina === 'vacaciones' ? 'Días' : 'Horas'}</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">A cobrar</th>
                    ${tipoNomina === 'vacaciones' ? '' : '<th style="border:0.5pt solid #000; padding:0.1875rem; width:2.1875rem;">Bon.</th>'}
                    ${tipoNomina === 'vacaciones' ? '' : '<th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">Deven.</th>'}
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">Imp. CESS</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">Dsctos.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.5rem;">Ret. Tot.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3rem;">Pagado</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:1.375rem;">Vac.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:2.8125rem;">Tiem. Imp.</th>
                    <th style="border:0.5pt solid #000; padding:0.1875rem; width:3.125rem;">Firma</th>
                </tr>
            `;

            pagesHtml += `
                ${breakBlock}
                <div class="Section1">
                    <table style="width:100%; border-collapse:collapse; margin-bottom:0.5rem;">
                        <tr>
                            <td style="border:0.5pt solid #000; padding:0.25rem; text-align:center; width:2.8125rem;">
                                ${cleanLogo ? `<img width="36" height="34" src="${cleanLogo}">` : ''}
                            </td>
                            <td style="border:0.5pt solid #000; padding:0.375rem; font-weight:bold; font-size:10.5pt; text-align:center;">
                                MODELO SC-4-06 NOMINA - ${escapeHtml(nombreEmpresa).toUpperCase()}
                            </td>
                            <td style="border:0.5pt solid #000; padding:0.375rem; font-weight:bold; text-align:center; width:8.125rem; font-size:9pt;">
                                Página ${numPag} de ${paginas.length}
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%; border-collapse:collapse; margin-bottom:0.625rem;">
                        <tr>
                            <td style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt; width:35%;">
                                <strong>Tipo Nómina:</strong> ${escapeHtml(tipoNominaTexto)}
                            </td>
                            <td style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt; width:35%;">
                                <strong>No. Instrum. Pago:</strong> ${codigoMostrado}
                            </td>
                            <td rowspan="4" style="border:0.5pt solid #000; padding:0.3125rem; font-size:7.5pt; vertical-align:top; line-height:1.3;">
                                <b>REVISADO POR:</b> ${nombreRevisado}<br>
                                <b>APROBADO POR:</b> ${nombreAprobado}<br>
                                <b>ELABORADO POR:</b> ${nombreElaborado}<br>
                                <b>CONTABILIZADO:</b> ___________________
                            </td>
                        </tr>
						<tr>
							<td style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt;">
								<strong>Alcance:</strong> ${escapeHtml(scopeText)}
							</td>
							<td style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt;">
								<strong>${esBono ? 'Monto a Distribuir: ' + (montoDistribuidoGlobal > 0 ? '$' + montoDistribuidoGlobal.toFixed(2) : '(Hasta que se contabilice)') : 'REEUP: ' + escapeHtml(reeup)}</strong><br>
								<strong>NIT:</strong> ${escapeHtml(nitEmpresa)}
							</td>
						</tr>
                        <tr>
                            <td style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt;">
                                <strong>Período:</strong> ${periodoTexto}
                            </td>
                            <td style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt;">
                                <strong>MES / AÑO:</strong> ${escapeHtml(nombreMesGlobal)} / ${escapeHtml(anioGlobal)}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border:0.5pt solid #000; padding:0.1875rem; font-size:8pt;">
                                <strong>Fecha Emisión:</strong> ${fechaActual12h} ${horaActual12h}
                            </td>
                        </tr>
						${observacionesCierreGlobal ? `
                    <tr>
                        <td colspan="3" style="border:0.5pt solid #000; padding:0.25rem; font-size:8pt; background-color: #f8fafc;">
                            <strong>Observaciones de Cierre:</strong> ${escapeHtml(observacionesCierreGlobal)}
                        </td>
                    </tr>` : ''}
                    </table>

                    <table style="width:100%; border-collapse:collapse; font-size:7.5pt; table-layout:fixed;">
                        <thead>
                            ${headerTR}
                        </thead>
                        <tbody>
                            ${cuerpoHtml}
                        </tbody>
                    </table>
                </div>`;
        });

        let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Nómina SC-4-06</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>95</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            <style>
                @page Section1 {
                    size: 11.0in 8.5in;
                    margin:0.35in 0.35in 0.35in 0.35in;
                    mso-header-margin: 0.25in;
                    mso-footer-margin: 0.25in;
                    mso-paper-source: 0;
                }
                div.Section1 { page: Section1; }
                body { font-family: Arial, sans-serif; font-size:8pt; color: #000000; }
                table { border-collapse: collapse; width:100%; margin-bottom:0.3125rem; }
                th, td { border: 0.5pt solid #000000; padding:0.125rem 0.1875rem; font-size:7.5pt; vertical-align: middle; line-height:110%; }
                .text-center { text-align: center; }
                .text-left { text-align: left; }
                .text-right { text-align: right; }
            </style>
        </head>
        <body>
            <div class="Section1">
                ${pagesHtml}
            </div>
        </body>
        </html>`;

        let blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = $.trim(nombreArchivo);
        link.click();
        URL.revokeObjectURL(link.href);

        Swal.close();
        Swal.fire({
            title: '¡Completado!',
            text: 'El reporte de Word oficial se ha descargado correctamente.',
            icon: 'success',
            timer: 1800,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }, 800);
}
// FUNCIÓN AGREGADA: Genera el formato de texto alineado para la exportación a TXT
function generarContenidoTXT(trabajadores) {
    var lines = [];
    
    // ============================================================
    // ENCABEZADO PRINCIPAL
    // ============================================================
    lines.push("==========================================================================");
    lines.push("REPORTE OFICIAL DE NÓMINA - " + nombreEmpresa.toUpperCase());
    lines.push("TIPO DE NÓMINA: " + tipoNominaTexto.toUpperCase());
    lines.push("PERÍODO: " + periodoTexto.toUpperCase());
    lines.push("NÚMERO NÓMINA: " + (numeroNomina === 'Borrador' ? 'Borrador' : numeroNomina));
    
    // 🔽 NUEVO: Mostrar Monto a Distribuir si es BONO
    var esBono = (tipoNomina === 'bono');
    var esAjuste = (tipoNomina === 'ajuste');
    var esExtraominaria = (tipoNomina === 'extraordinaria');
    var mostrarConcepto = esBono || esAjuste;
    if (esBono) {
        if (montoDistribuidoGlobal > 0) {
            lines.push("MONTO A DISTRIBUIR: $" + montoDistribuidoGlobal.toFixed(2));
        } else {
            lines.push("MONTO A DISTRIBUIR: (Hasta que se contabilice)");
        }
    }
    
    lines.push("FECHA GENERACIÓN: " + new Date().toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }));
    lines.push("==========================================================================");
    lines.push("");
    
    // ============================================================
    // ENCABEZADOS DE COLUMNAS
    // ============================================================
    var header;
    if (esBono) {
        header = padRight("COD", 6) + " | " + 
                 padRight("CI", 11) + " | " + 
                 padRight("NOMBRE Y APELLIDOS", 30) + " | " + 
                 padLeft("MONTO BONO", 14) + " | " + 
                 padLeft("NETO", 14);
    } else if (esExtraominaria) {
        header = padRight("COD", 6) + " | " + 
                 padRight("CI", 11) + " | " + 
                 padRight("NOMBRE Y APELLIDOS", 30) + " | " + 
                 padLeft("HE/D", 10) + " | " + 
                 padLeft("$/HE/D", 10) + " | " + 
                 padLeft("NT 7-23H", 8) + " | " + 
                 padLeft("$/NT 7-23H", 10) + " | " + 
                 padLeft("NT 23-7H", 8) + " | " + 
                 padLeft("$/NT 23-7H", 10) + " | " + 
                 padLeft("DT", 4) + " | " + 
                 padLeft("$/DT", 8) + " | " + 
                 padLeft("DEVENGADO", 14) + " | " + 
                 padLeft("CESS", 12) + " | " + 
                 padLeft("DSCTOS.", 12) + " | " + 
                 padLeft("RET. TOT.", 12) + " | " + 
                 padLeft("PAGADO", 14);
    } else {
        header = padRight("COD", 6) + " | " + 
                 padRight("CI", 11) + " | " + 
                 padRight("NOMBRE Y APELLIDOS", 30) + " | " + 
                 padLeft("DEVENGADO", 14) + " | " + 
                 padLeft("DEDUCC.", 14) + " | " + 
                 padLeft("NETO", 14);
    }
    
    lines.push(header);
    lines.push("-".repeat(header.length));
    
    // ============================================================
    // DATOS DE TRABAJADORES
    // ============================================================
    var totalDev = 0;
    var totalDed = 0;
    var totalCess = 0;
    var totalDescuentos = 0;
    var totalNet = 0;
    var totalBono = 0;
    var totalHoras = 0;
    var totalImporteHE = 0;
    var totalNoctT = 0;
    var totalImporteNtT = 0;
    var totalNoctD = 0;
    var totalImporteNtD = 0;
    var totalDt = 0;
    var totalImporteDT = 0;

    trabajadores.forEach(function(t) {
        totalDev += t.devengado || 0;
        totalDed += t.retenciones || 0;
        totalCess += t.impS || 0;
        totalDescuentos += t.descuentos || 0;
        totalNet += t.pagado || 0;
        totalBono += t.bono || 0;
        totalHoras += t.horas || 0;
        totalImporteHE += t.importeHE || 0;
        totalNoctT += t.noctT || 0;
        totalImporteNtT += t.importeNtT || 0;
        totalNoctD += t.noctD || 0;
        totalImporteNtD += t.importeNtD || 0;
        totalDt += t.dt || 0;
        totalImporteDT += t.importeDT || 0;

        var nombreTruncado = t.nombre.substring(0, 30);
        
        var line;
        if (esBono) {
            line = padRight(t.codigo, 6) + " | " + 
                   padRight(t.ci, 11) + " | " + 
                   padRight(nombreTruncado, 30) + " | " + 
                   padLeft("$" + (t.bono || 0).toFixed(2), 14) + " | " + 
                   padLeft("$" + (t.pagado || 0).toFixed(2), 14);
        } else if (esExtraominaria) {
            line = padRight(t.codigo, 6) + " | " + 
                   padRight(t.ci, 11) + " | " + 
                   padRight(nombreTruncado, 30) + " | " + 
                   padLeft((t.horas || 0).toFixed(0), 10) + " | " + 
                   padLeft("$" + (t.importeHE || 0).toFixed(2), 10) + " | " + 
                   padLeft((t.noctT || 0).toFixed(0), 8) + " | " + 
                   padLeft("$" + (t.importeNtT || 0).toFixed(2), 10) + " | " + 
                   padLeft((t.noctD || 0).toFixed(0), 8) + " | " + 
                   padLeft("$" + (t.importeNtD || 0).toFixed(2), 10) + " | " + 
                   padLeft((t.dt || 0).toFixed(0), 4) + " | " + 
                   padLeft("$" + (t.importeDT || 0).toFixed(2), 8) + " | " + 
                   padLeft("$" + (t.devengado || 0).toFixed(2), 14) + " | " + 
                   padLeft("$" + (t.impS || 0).toFixed(2), 12) + " | " + 
                   padLeft("$" + (t.descuentos || 0).toFixed(2), 12) + " | " + 
                   padLeft("$" + (t.retenciones || 0).toFixed(2), 12) + " | " + 
                   padLeft("$" + (t.pagado || 0).toFixed(2), 14);
        } else {
            line = padRight(t.codigo, 6) + " | " + 
                   padRight(t.ci, 11) + " | " + 
                   padRight(nombreTruncado, 30) + " | " + 
                   padLeft("$" + t.devengado.toFixed(2), 14) + " | " + 
                   padLeft("$" + t.retenciones.toFixed(2), 14) + " | " + 
                   padLeft("$" + t.pagado.toFixed(2), 14);
        }
        lines.push(line);
        // 🔽 NUEVO: Observación/concepto debajo de cada trabajador (BONO y AJUSTE)
        if (mostrarConcepto) {
            lines.push("    Observación: " + (t.concepto || 'Sin concepto'));
        }
    });
    
    // ============================================================
    // LÍNEA SEPARADORA
    // ============================================================
    lines.push("-".repeat(header.length));
    
    // ============================================================
    // TOTALES
    // ============================================================
    var totalLine;
    if (esBono) {
        totalLine = padRight("TOTAL", 6) + " | " + 
                    padRight("", 11) + " | " + 
                    padRight("TOTALES GENERALES", 30) + " | " + 
                    padLeft("$" + totalBono.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalNet.toFixed(2), 14);
    } else if (esExtraominaria) {
        totalLine = padRight("TOTAL", 6) + " | " + 
                    padRight("", 11) + " | " + 
                    padRight("TOTALES GENERALES", 30) + " | " + 
                    padLeft(totalHoras.toFixed(0), 10) + " | " + 
                    padLeft("$" + totalImporteHE.toFixed(2), 10) + " | " + 
                    padLeft(totalNoctT.toFixed(0), 8) + " | " + 
                    padLeft("$" + totalImporteNtT.toFixed(2), 10) + " | " + 
                    padLeft(totalNoctD.toFixed(0), 8) + " | " + 
                    padLeft("$" + totalImporteNtD.toFixed(2), 10) + " | " + 
                    padLeft(totalDt.toFixed(0), 4) + " | " + 
                    padLeft("$" + totalImporteDT.toFixed(2), 8) + " | " + 
                    padLeft("$" + totalDev.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalCess.toFixed(2), 12) + " | " + 
                    padLeft("$" + totalDescuentos.toFixed(2), 12) + " | " + 
                    padLeft("$" + totalDed.toFixed(2), 12) + " | " + 
                    padLeft("$" + totalNet.toFixed(2), 14);
    } else {
        totalLine = padRight("TOTAL", 6) + " | " + 
                    padRight("", 11) + " | " + 
                    padRight("TOTALES GENERALES", 30) + " | " + 
                    padLeft("$" + totalDev.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalDed.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalNet.toFixed(2), 14);
    }
    lines.push(totalLine);
    if (observacionesCierreGlobal) {
        lines.push("");
        lines.push("OBSERVACIONES DE CIERRE: " + observacionesCierreGlobal);
    }
    lines.push("==========================================================================");
    
    // ============================================================
    // PIE DE PÁGINA CON FIRMAS (SOLO PARA BONO CONTABILIZADO)
    // ============================================================
    if (esBono && montoDistribuidoGlobal > 0) {
        lines.push("");
        lines.push("FIRMAS DE RESPONSABILIDAD:");
        lines.push("-".repeat(50));
        lines.push("REVISADO POR:  " + (typeof especialistaGestion !== 'undefined' ? especialistaGestion.toUpperCase() : '___________________'));
        lines.push("APROBADO POR:  " + (typeof jefeProyecto !== 'undefined' ? jefeProyecto.toUpperCase() : '___________________'));
        lines.push("ELABORADO POR:  " + (typeof especialistaNominas !== 'undefined' ? especialistaNominas.toUpperCase() : '___________________'));
        lines.push("CONTABILIZADO: ___________________");
        lines.push("==========================================================================");
    }

    return lines.join("\r\n");

    // ============================================================
    // FUNCIONES AUXILIARES PARA ALINEACIÓN
    // ============================================================
    function padRight(str, length) {
        str = (str || "").toString();
        return str.length >= length ? str.substring(0, length) : str + " ".repeat(length - str.length);
    }
    
    function padLeft(str, length) {
        str = (str || "").toString();
        return str.length >= length ? str.substring(0, length) : " ".repeat(length - str.length) + str;
    }
}


</script>
