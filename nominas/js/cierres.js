/* js/cierres.js - Panel de cierre de períodos de nómina (mes y año)
 *
 * Depende de: jquery, bootstrap (tooltips) y SweetAlert2, cargados en cierres.php.
 * Configuración vía window.CIERRES_CONFIG (permisos del rol, año base y mes inicial).
 *
 * El módulo nunca decide si se puede cerrar: lo pregunta al servidor con
 * verificar_mes / verificar_ano y se limita a reflejar lo que responde.
 * Las cifras se formatean en español con toLocaleString.
 */
(function () {
    'use strict';

    var CFG = window.CIERRES_CONFIG || {};

    var MES = (function () {
        return document.getElementById('grillaMeses');
    })();

    var $cargandoMes   = document.getElementById('cargandoMes');
    var $cuerpoMes     = document.getElementById('cuerpoMes');
    var $chipMes       = document.getElementById('chipEstadoMes');
    var $tituloMes     = document.getElementById('tituloMesSeleccionado');
    var $alertaMes     = document.getElementById('alertaMes');
    var $alertaMesTxt  = document.getElementById('alertaMesTexto');
    var $btnCerrarMes  = document.getElementById('btnCerrarMes');

    // El mes en pantalla no tiene nominas y se puede cerrar en cero. Lo guarda
    // la previsualizacion para que el clic del boton use el texto correcto.
    var mesEnCero = false;

    var $cargandoAnio  = document.getElementById('cargandoAnio');
    var $cuerpoAnio    = document.getElementById('cuerpoAnio');
    var $alertaAnio    = document.getElementById('alertaAnio');
    var $alertaAnioTxt = document.getElementById('alertaAnioTexto');
    var $listaMesesAnio = document.getElementById('listaMesesAnio');
    var $btnCerrarAnio = document.getElementById('btnCerrarAnio');

    var $cargandoHist  = document.getElementById('cargandoHistorial');
    var $vacioHist     = document.getElementById('vacioHistorial');
    var $tablaHistWrap = document.getElementById('tablaHistorialWrap');
    var $cuerpoHist    = document.querySelector('#tablaHistorial tbody');
    var $btnExportar   = document.getElementById('btnExportar');
    var $selAnio       = document.getElementById('selAnio');

    var ANIO     = CFG.anio || new Date().getFullYear();
    var MES_SEL  = CFG.mesInicial || 1;
    var ETIQUETAS = CFG.etiquetasTipo || {};

    /* ---------- utilidades ---------- */

    var numFmt = new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var intFmt = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 0 });

    function money(v) { return numFmt.format(Number(v) || 0); }
    /* Los importes de las tarjetas llevan el prefijo de moneda: "$ 000 000.00". */
    function moneyC(v) { return '$ ' + money(v); }
    function integer(v) { return intFmt.format(Number(v) || 0); }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fecha(v) {
        if (!v) { return ''; }
        var f = new Date(String(v).replace(' ', 'T'));
        if (isNaN(f.getTime())) { return String(v); }
        return f.toLocaleDateString('es-ES') + ' ' +
               f.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }

    /**
     * POST al propio módulo. Lanza Error con el mensaje del servidor o de red.
     */
    function pedir(accion, campos) {
        var cuerpo = new URLSearchParams();
        cuerpo.append('accion', accion);
        Object.keys(campos || {}).forEach(function (k) {
            cuerpo.append(k, campos[k] === null || campos[k] === undefined ? '' : campos[k]);
        });

        return fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: cuerpo.toString(),
            credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) { throw new Error('El servidor respondió HTTP ' + r.status + '.'); }
                return r.json();
            })
            .then(function (data) {
                if (!data) { throw new Error('Respuesta vacía del servidor.'); }
                return data;
            });
    }

    function errorRed(e) {
        Swal.fire({
            icon: 'error',
            title: 'Error de comunicación',
            text: (e && e.message) ? e.message : 'No se pudo contactar con el servidor.',
            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
        });
    }

    var ICONO = {
        cerrado:    'fa-lock',
        revertido:  'fa-unlock',
        pendiente:  'fa-clock',
        sin_nomina: 'fa-minus'
    };
    var TEXTO = {
        cerrado:    'Cerrado',
        revertido:  'Revertido',
        pendiente:  'Abierto',
        sin_nomina: 'Sin nómina'
    };

    function chipEstado(estado) {
        if (estado === 'cerrado')   { return '<i class="fas fa-lock"></i>Cerrado'; }
        if (estado === 'revertido') { return '<i class="fas fa-unlock"></i>Revertido'; }
        return '<i class="fas fa-hourglass-half"></i>Abierto';
    }

    /* ---------- selector de año ---------- */

    if ($selAnio) {
        $selAnio.addEventListener('change', function () {
            window.location.href = window.location.pathname + '?anio=' + encodeURIComponent(this.value);
        });
    }

    /* ---------- grilla de meses ---------- */

    function pintarGrilla(meses) {
        if (!MES) { return; }
        MES.querySelectorAll('.cierre-mes').forEach(function (btn) {
            var m = parseInt(btn.getAttribute('data-mes'), 10);
            var info = meses ? meses[m] : null;
            if (!info) { return; }
            btn.classList.remove('estado-cerrado', 'estado-revertido', 'estado-pendiente', 'estado-sin_nomina');
            btn.classList.add('estado-' + info.estado);
            btn.classList.toggle('activo', m === MES_SEL);
            btn.setAttribute('data-estado', info.estado);
            btn.setAttribute('title', info.etiqueta + ' ' + ANIO + ' · ' + info.estado);
            var icono = btn.querySelector('i');
            if (icono) { icono.className = 'fas ' + (ICONO[info.estado] || 'fa-minus'); }
        });
    }

    if (MES) {
        MES.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.cierre-mes');
            if (!btn) { return; }
            MES_SEL = parseInt(btn.getAttribute('data-mes'), 10);
            MES.querySelectorAll('.cierre-mes').forEach(function (b) { b.classList.remove('activo'); });
            btn.classList.add('activo');
            cargarMes();
        });
    }

    /* ---------- panel del mes ---------- */

    function pintarAlerta($el, $txt, texto, clase) {
        if (!texto) { $el.hidden = true; return; }
        $txt.textContent = texto;
        $el.className = 'cierre-alerta' + (clase ? ' ' + clase : '');
        $el.hidden = false;
    }

    function pintarDesglose(items) {
        var cont = document.getElementById('desgloseTipos');
        if (!cont) { return; }

        /* El servidor devuelve hoy UNA fila por tipo con lotes y trabajadores
           (personas distintas) ya contados. Las filas viejas venian por lote:
           en ese caso cada fila aporta 1 lote y sus trabajadores. */
        var porTipo = {};
        (items || []).forEach(function (d) {
            if (!d) { return; }
            var t = d.tipo_nomina || 'desconocido';
            if (!porTipo[t]) {
                porTipo[t] = { tipo: t, lotes: 0, trabajadores: 0, devengado: 0, neto: 0 };
            }
            porTipo[t].lotes         += (d.lotes != null && d.lotes !== '') ? (Number(d.lotes) || 0) : 1;
            porTipo[t].trabajadores += Number(d.trabajadores) || 0;
            porTipo[t].devengado     += Number(d.devengado) || 0;
            porTipo[t].neto          += Number(d.neto) || 0;
        });

        var filas = Object.keys(porTipo).map(function (k) { return porTipo[k]; })
            .filter(function (d) { return d.lotes > 0 || d.trabajadores > 0; })
            .sort(function (a, b) { return b.devengado - a.devengado; });

        if (!filas.length) {
            cont.innerHTML = '<div class="cierre-vacio"><i class="fas fa-inbox"></i>'
                + 'Este mes no tiene nóminas registradas.</div>';
            return;
        }

        var mayorTrab = filas.reduce(function (acc, d) {
            return Math.max(acc, d.trabajadores);
        }, 0) || 1;

        cont.innerHTML = '<div class="cierre-desglose">' + filas.map(function (d) {
            var pct = Math.round((d.trabajadores / mayorTrab) * 100);
            var nombre = ETIQUETAS[d.tipo] || d.tipo;
            /* Cada fila lleva al modulo de nominas del mismo mes y del mismo
             * tipo, para revisar o corregir ese lote sin volver a buscarlo. */
            var destino = 'nominas.php?periodo=' + ANIO + '-' + (MES_SEL < 10 ? '0' + MES_SEL : MES_SEL)
                + '&tipo=' + encodeURIComponent(d.tipo);
            return '<a class="cierre-desglose-fila js-ir-nomina" href="' + esc(destino) + '"'
                + ' data-tooltip="Abrir n&oacute;minas de ' + esc(nombre) + '" data-tooltip-theme="dark">'
                + '<div class="cierre-desglose-etiqueta">' + esc(nombre)
                + '<small>' + integer(d.trabajadores) + ' trabajadores · ' + integer(d.lotes)
                + (d.lotes === 1 ? ' lote' : ' lotes') + '</small></div>'
                + '<div class="cierre-desglose-barra"><span style="width:' + pct + '%"></span></div>'
                + '<div class="cierre-desglose-cifras">'
                + '<span>Devengado <b>' + money(d.devengado) + '</b></span>'
                + '<span>Neto <b>' + money(d.neto) + '</b></span>'
                + '</div>'
                + '<i class="fas fa-arrow-right js-ir-nomina-icono"></i>'
                + '</a>';
        }).join('') + '</div>';
    }

    function aplicarCierre($chip, cierre, etiqueta) {
        if (!cierre) { return; }
        var cerrado = cierre.estado === 'cerrado';
        $chip.className = 'cierre-chip ' + (cerrado ? 'cierre-chip-cerrado' : 'cierre-chip-revertido');
        $chip.innerHTML = cerrado
            ? '<i class="fas fa-lock"></i>Cerrado'
            : '<i class="fas fa-unlock"></i>Revertido';
    }

    function cargarMes() {
        if (!$cuerpoMes) { return; }

        $cargandoMes.hidden = false;
        $cuerpoMes.hidden = true;
        $tituloMes.textContent = '…';

        pedir('verificar_mes', { anio: ANIO, mes: MES_SEL })
            .then(function (d) {
                var st = d.estadisticas || {};
                $tituloMes.textContent = (d.etiqueta || '') + ' ' + ANIO;

                document.getElementById('mLotes').textContent         = integer(st.total_lotes);
                document.getElementById('mTrabajadores').textContent  = integer(st.total_trabajadores);
document.getElementById('mDevengado').textContent     = moneyC(st.total_devengado);
    document.getElementById('mDeducciones').textContent   = moneyC(st.total_deducciones);
    document.getElementById('mNeto').textContent          = moneyC(st.total_neto);
    document.getElementById('mContribucion').textContent  = moneyC(st.total_contribucion);

                pintarDesglose(d.desglose);

                var cierre = d.cierre;
                var cerrado = cierre && cierre.estado === 'cerrado';
                var revertido = cierre && cierre.estado === 'revertido';

                if (cerrado) {
                    $chipMes.className = 'cierre-chip cierre-chip-cerrado';
                    $chipMes.innerHTML = '<i class="fas fa-lock"></i>Cerrado';
                    pintarAlerta($alertaMes, $alertaMesTxt,
                        'Este mes está cerrado' + (cierre.fecha_cierre ? ' el ' + fecha(cierre.fecha_cierre) : '')
                        + ' por ' + (cierre.usuario_cierre || '—') + '. Las nóminas quedan congeladas.', 'es-ok');
                } else if (revertido) {
                    $chipMes.className = 'cierre-chip cierre-chip-revertido';
                    $chipMes.innerHTML = '<i class="fas fa-unlock"></i>Revertido';
                    pintarAlerta($alertaMes, $alertaMesTxt,
                        'El cierre de este mes fue revertido. Motivo: ' + (cierre.motivo_reapertura || 'sin especificar')
                        + ' (' + fecha(cierre.fecha_reapertura) + '). El mes puede cerrarse de nuevo.', 'es-advertencia');
                } else {
                    $chipMes.className = 'cierre-chip ' + (d.puede_cerrar ? 'cierre-chip-listo' : 'cierre-chip-pendiente');
                    $chipMes.innerHTML = d.puede_cerrar
                        ? '<i class="fas fa-circle-check"></i>Listo para cerrar'
                        : '<i class="fas fa-hourglass-half"></i>No se puede cerrar';
                    pintarAlerta($alertaMes, $alertaMesTxt, d.mensaje || '',
                        d.puede_cerrar ? 'es-ok' : 'es-advertencia');
                }

                var habilitable = CFG.puedeCerrar && !cerrado && d.puede_cerrar;
                $btnCerrarMes.disabled = !habilitable;

                /* Un mes sin nominas no tiene nada que congelar, pero cerrarlo en
                 * cero es justo lo que deja el ano completo. El boton lo dice, o
                 * el usuario cierra creyendo que hay nominas que no ve. */
                var enCero = d.cierre_en_cero && !cerrado;
                mesEnCero = enCero;
                $btnCerrarMes.innerHTML = (!CFG.puedeCerrar && !cerrado)
                    ? '<i class="fas fa-lock me-1"></i> Cerrar mes'
                    : (cerrado
                        ? '<i class="fas fa-lock me-1"></i> Mes cerrado'
                        : (enCero
                            ? '<i class="fas fa-circle-check me-1"></i> Cerrar mes en cero'
                            : '<i class="fas fa-lock me-1"></i> Cerrar mes'));
                $btnCerrarMes.setAttribute('data-tooltip', enCero
                    ? 'Este mes no tiene nóminas: se cerrará en cero'
                    : 'Cerrar el mes y congelar sus nóminas');

                $cargandoMes.hidden = true;
                $cuerpoMes.hidden = false;
            })
            .catch(function (e) {
                $cargandoMes.hidden = true;
                $cuerpoMes.hidden = false;
                $tituloMes.textContent = (MES_SEL) + ' / ' + ANIO;
                pintarAlerta($alertaMes, $alertaMesTxt, e.message, 'es-advertencia');
                $btnCerrarMes.disabled = true;
                errorRed(e);
            });
    }

    if ($btnCerrarMes) {
        $btnCerrarMes.addEventListener('click', function () {
            var tituloCierre = mesEnCero ? 'Cerrar el mes en cero' : 'Cerrar el mes';
            Swal.fire({
                icon: mesEnCero ? 'info' : 'question',
                title: tituloCierre,
                html: mesEnCero
                    ? 'Este mes no tiene nóminas registradas.<br>'
                        + 'El cierre se registrará en cero y el período quedará marcado '
                        + 'como cerrado.<br><br>'
                        + '<b>¿Desea continuar?</b>'
                    : 'Se congelarán todas las nóminas del período seleccionado.<br>'
                        + 'Después de cerrar, las nóminas de ese mes no podrán modificarse '
                        + 'sin reabrir el cierre con un motivo justificado.<br><br>'
                        + '<b>¿Desea continuar?</b>',
                showCancelButton: true,
                confirmButtonText: mesEnCero
                    ? '<i class="fas fa-circle-check me-2"></i>Sí, cerrar en cero'
                    : '<i class="fas fa-lock me-2"></i>Sí, cerrar el mes',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                reverseButtons: true,
                focusConfirm: false
            }).then(function (r) {
                if (!r.isConfirmed) { return; }
                return Swal.fire({
                    input: 'text',
                    inputPlaceholder: mesEnCero
                        ? 'Observación opcional (ej. Mes sin actividad económica)'
                        : 'Observación opcional (ej. Mes Cerrado sin problemas)',
                    inputAttributes: { 'aria-label': 'Observaciones del cierre' },
                    title: 'Observaciones del cierre',
                    showCancelButton: true,
                    confirmButtonText: mesEnCero
                        ? '<i class="fas fa-circle-check me-2"></i>Cerrar en cero'
                        : '<i class="fas fa-lock me-2"></i>Cerrar mes',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    reverseButtons: true
                });
            }).then(function (r2) {
                if (!r2 || !r2.isConfirmed) { return; }

                Swal.fire({
                    title: 'Cerrando el mes…',
                    html: '<i class="fas fa-spinner fa-spin"></i> Registrando el cierre',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                return pedir('cerrar_mes', {
                    anio: ANIO,
                    mes: MES_SEL,
                    observaciones: r2.value || ''
                }).then(function (res) {
                    Swal.closePopup();
                    if (!res.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se pudo cerrar el mes',
                            text: res.mensaje || 'Error desconocido.',
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                        return;
                    }
                    Swal.fire({
                        icon: 'success',
                        title: res.mensaje,
                        text: mesEnCero
                            ? 'El mes quedó cerrado en cero, sin nóminas que congelar.'
                            : 'Las nóminas del mes quedaron congeladas.',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
                    }).then(function () { window.location.reload(); });
                });
            }).catch(function (e) {
                Swal.closePopup();
                errorRed(e);
            });
        });
    }

    /* ---------- panel del año ---------- */

    function pintarListaMeses(meses, sin_datos, con_automatica) {
        if (!$listaMesesAnio) { return; }

        /* Solo son enlace los meses que de verdad tienen nomina automatica: llevar al
         * modulo de nominas un mes sin nomina solo abriria una vista vacia. */
        var mesAutomatico = {};
        (con_automatica || []).forEach(function (n) { mesAutomatico[Number(n)] = true; });

        var claves = Object.keys(meses || {});
        $listaMesesAnio.innerHTML = claves.map(function (k) {
            var m = meses[k];
            var mes = Number(k);
            var nombre = mesAutomatico[mes]
                ? '<a class="cierre-li-enlace" href="nominas.php?periodo=' + ANIO + '-' + (mes < 10 ? '0' + mes : mes)
                    + '&tipo=automatica" data-tooltip="Abrir la n&oacute;mina autom&aacute;tica de '
                    + esc(m.etiqueta) + ' ' + ANIO + '" data-tooltip-theme="dark">'
                    + esc(m.etiqueta) + '<i class="fas fa-arrow-right"></i></a>'
                : '<span class="cierre-li-nombre">' + esc(m.etiqueta) + '</span>';

            return '<li class="estado-' + esc(m.estado) + '">'
                + '<i class="fas ' + (ICONO[m.estado] || 'fa-minus') + '"></i>'
                + nombre
                + '<span class="cierre-li-dato">' + esc(TEXTO[m.estado] || m.estado) + '</span>'
                + '</li>';
        }).join('');

        if (sin_datos && sin_datos.length) {
            $listaMesesAnio.insertAdjacentHTML('beforeend',
                '<li class="estado-sin_nomina"><i class="fas fa-circle-info"></i>'
                + '<span class="cierre-li-nombre">Se registrarán con cero al cerrar</span>'
                + '<span class="cierre-li-dato">' + esc(sin_datos.join(', ')) + '</span></li>');
        }
    }

    function cargarAnio() {
        if (!$cuerpoAnio) { return; }

        $cargandoAnio.hidden = false;
        $cuerpoAnio.hidden = true;

        pedir('verificar_ano', { anio: ANIO })
            .then(function (d) {
                pintarListaMeses(d.meses, d.sin_datos, d.automatica);

                var cierre = d.cierre;
                var cerrado = cierre && cierre.estado === 'cerrado';

                if (cerrado) {
                    pintarAlerta($alertaAnio, $alertaAnioTxt,
                        'El año ' + ANIO + ' está cerrado' + (cierre.fecha_cierre ? ' el ' + fecha(cierre.fecha_cierre) : '')
                        + ' por ' + (cierre.usuario_cierre || '—') + '.', 'es-ok');
                } else {
                    pintarAlerta($alertaAnio, $alertaAnioTxt, d.mensaje || '',
                        d.puede_cerrar ? 'es-ok' : 'es-advertencia');
                }

                $btnCerrarAnio.disabled = !(CFG.puedeCerrar && d.puede_cerrar && !cerrado);
                $btnCerrarAnio.innerHTML = cerrado
                    ? '<i class="fas fa-lock me-1"></i> Año ' + ANIO + ' cerrado'
                    : '<i class="fas fa-calendar-check me-1"></i> Cerrar año ' + ANIO;

                $cargandoAnio.hidden = true;
                $cuerpoAnio.hidden = false;

                pintarGrilla(d.meses);
            })
            .catch(function (e) {
                $cargandoAnio.hidden = true;
                $cuerpoAnio.hidden = false;
                pintarAlerta($alertaAnio, $alertaAnioTxt, e.message, 'es-advertencia');
                $btnCerrarAnio.disabled = true;
                errorRed(e);
            });
    }

    if ($btnCerrarAnio) {
        $btnCerrarAnio.addEventListener('click', function () {
            Swal.fire({
                icon: 'question',
                title: 'Cerrar el año ' + ANIO,
                html: 'El cierre anual consolida los meses ya cerrados.<br>'
                    + 'Los meses sin nómina se registrarán con importes en cero.<br>'
                    + '<b style="color:#f59e0b">El cierre del año es definitivo:</b> una vez cerrado, '
                    + 'no se puede reabrir el año ni ninguno de sus meses.<br><br>'
                    + '<b>¿Desea continuar?</b>',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-calendar-check me-2"></i>Sí, cerrar el año',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                reverseButtons: true,
                focusConfirm: false
            }).then(function (r) {
                if (!r.isConfirmed) { return; }
                return Swal.fire({
                    input: 'text',
                    inputPlaceholder: 'Observación opcional (ej. Mes Cerrado sin problemas)',
                    inputAttributes: { 'aria-label': 'Observaciones del cierre anual' },
                    title: 'Observaciones del cierre anual',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-calendar-check me-2"></i>Cerrar año',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    reverseButtons: true
                });
            }).then(function (r2) {
                if (!r2 || !r2.isConfirmed) { return; }

                Swal.fire({
                    title: 'Cerrando el año…',
                    html: '<i class="fas fa-spinner fa-spin"></i> Consolidando los meses',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                return pedir('cerrar_ano', {
                    anio: ANIO,
                    observaciones: r2.value || ''
                }).then(function (res) {
                    Swal.closePopup();
                    if (!res.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se pudo cerrar el año',
                            text: res.mensaje || 'Error desconocido.',
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                        return;
                    }
                    Swal.fire({
                        icon: 'success',
                        title: res.mensaje,
                        text: res.sin_datos && res.sin_datos.length
                            ? 'Meses sin nómina registrados en cero: ' + res.sin_datos.join(', ') + '.'
                            : 'El año quedó consolidado.',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
                    }).then(function () { window.location.reload(); });
                });
            }).catch(function (e) {
                Swal.closePopup();
                errorRed(e);
            });
        });
    }

    /* ---------- historial ---------- */

    var HISTORIAL = [];
    var ORDEN_HIST = { columna: 'fecha_cierre', dir: 'desc' };

    /* Cada columna declara como se compara. 'periodo' y 'fecha_cierre' son
     * cadenas de fecha ordenables alfabeticamente porque van en formato
     * AAAA-MM y AAAA-MM-DD; el resto se compara numericamente o por texto. */
    function valorOrden(c, columna) {
        switch (columna) {
            case 'periodo':       return (c.anio || 0) * 100 + (c.mes || 0);
            case 'tipo':          return c.tipo === 2 ? 2 : 1;
            case 'estado':        return c.estado === 'cerrado' ? 1 : 0;
            case 'total_lotes':   return parseFloat(c.total_lotes) || 0;
            case 'total_trabajadores': return parseFloat(c.total_trabajadores) || 0;
            case 'total_devengado':    return parseFloat(c.total_devengado) || 0;
            case 'total_neto':         return parseFloat(c.total_neto) || 0;
            case 'fecha_cierre':  return String(c.fecha_cierre || '');
            default:              return '';
        }
    }

    function ordenarHistorial() {
        var col = ORDEN_HIST.columna;
        var dir = ORDEN_HIST.dir === 'asc' ? 1 : -1;
        HISTORIAL.sort(function (a, b) {
            var va = valorOrden(a, col);
            var vb = valorOrden(b, col);
            if (va < vb) return -1 * dir;
            if (va > vb) return 1 * dir;
            // Empate: se desempata por fecha para que el orden sea estable.
            return String(a.fecha_cierre || '').localeCompare(String(b.fecha_cierre || ''));
        });
    }

    function pintarIndicadoresOrden() {
        var ths = document.querySelectorAll('#tablaHistorial th.js-orden');
        Array.prototype.forEach.call(ths, function (th) {
            var activo = th.getAttribute('data-orden') === ORDEN_HIST.columna;
            var icono = th.querySelector('i.fa-sort, i.fa-sort-up, i.fa-sort-down');
            if (icono) { icono.remove(); }
            th.classList.toggle('orden-activo', activo);
            th.setAttribute('aria-sort', activo
                ? (ORDEN_HIST.dir === 'asc' ? 'ascending' : 'descending')
                : 'none');
            if (activo) {
                var i = document.createElement('i');
                i.className = 'fas ' + (ORDEN_HIST.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') + ' ms-1';
                th.appendChild(i);
            }
        });
    }

    function pintarHistorial(cierres) {
        HISTORIAL = cierres || [];

        $cargandoHist.hidden = true;
        $cuerpoHist.innerHTML = '';

        if (!HISTORIAL.length) {
            $vacioHist.hidden = false;
            $tablaHistWrap.hidden = true;
            pintarIndicadoresOrden();
            return;
        }

        $vacioHist.hidden = true;
        $tablaHistWrap.hidden = false;

        ordenarHistorial();
        pintarIndicadoresOrden();

        $cuerpoHist.innerHTML = HISTORIAL.map(function (c) {
            var nombre = c.tipo === 2 ? 'Año completo' : (c.etiqueta + ' ' + c.anio);

            var estado = c.estado === 'cerrado'
                ? '<span class="cierre-chip cierre-chip-cerrado"><i class="fas fa-lock"></i>Cerrado</span>'
                : '<span class="cierre-chip cierre-chip-revertido"><i class="fas fa-unlock"></i>Revertido</span>';

            var accion;
            if (c.estado !== 'cerrado') {
                accion = '<span class="cierre-li-dato">—</span>';
            } else if (!CFG.puedeReabrir) {
                accion = '<button type="button" class="cierre-accion-icono" disabled'
                    + ' data-bs-toggle="tooltip" data-bs-title="Reabrir (solo Administrador o Supervisor)">'
                    + '<i class="fas fa-unlock"></i></button>';
            } else if (c.reabrible === false) {
                // El cierre anual es definitivo: ni el ano ni sus meses admiten
                // reapertura. Se muestra el candado deshabilitado y el motivo,
                // en vez de un boton que el servidor va a rechazar.
                accion = '<button type="button" class="cierre-accion-icono" disabled'
                    + ' aria-disabled="true"'
                    + ' data-bs-toggle="tooltip"'
                    + ' data-bs-title="' + esc(c.motivo_no_reabrible
                        || 'Este cierre es definitivo y no se puede reabrir.') + '">'
                    + '<i class="fas fa-lock"></i></button>';
            } else {
                accion = '<button type="button" class="cierre-accion-icono js-reabrir"'
                    + ' data-tipo="' + c.tipo + '" data-anio="' + c.anio + '" data-mes="' + c.mes + '"'
                    + ' data-nombre="' + esc(nombre) + '"'
                    + ' data-bs-toggle="tooltip" data-bs-title="Reabrir este período"'
                    + ' aria-label="Reabrir ' + esc(nombre) + '">'
                    + '<i class="fas fa-unlock"></i></button>';
            }

            return '<tr>'
                + '<td><span class="cierre-periodo-nombre">' + esc(nombre) + '<small>' + esc(c.tipo_texto) + '</small></span></td>'
                + '<td>' + esc(c.tipo_texto) + '</td>'
                + '<td>' + estado + '</td>'
                + '<td class="text-end">' + integer(c.total_lotes) + '</td>'
                + '<td class="text-end">' + integer(c.total_trabajadores) + '</td>'
                + '<td class="text-end">' + money(c.total_devengado) + '</td>'
                + '<td class="text-end cierre-monto-neto">' + money(c.total_neto) + '</td>'
                + '<td>' + esc(fecha(c.fecha_cierre)) + '</td>'
                + '<td class="text-center">' + accion + '</td>'
                + '</tr>';
        }).join('');

        if (window.bootstrap && bootstrap.Tooltip) {
            $cuerpoHist.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    }

    function cargarHistorial() {
        $cargandoHist.hidden = false;
        // Se manda el año visible: si no, el listado mezcla cierres de otros
        // años y hace pensar que el año abierto ya está cerrado.
        pedir('historial', { anio: ANIO })
            .then(function (d) { pintarHistorial(d.cierres); })
            .catch(function (e) {
                $cargandoHist.hidden = true;
                $tablaHistWrap.hidden = true;
                $vacioHist.hidden = false;
                $vacioHist.innerHTML = '<i class="fas fa-triangle-exclamation"></i>No se pudo cargar el historial.';
                errorRed(e);
            });
    }

    /* ---------- reabrir ---------- */

    $cuerpoHist.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-reabrir');
        if (!btn) { return; }

        var nombre = btn.getAttribute('data-nombre');

        Swal.fire({
            icon: 'warning',
            title: 'Reabrir: ' + nombre,
            html: 'El período volverá a quedar editable.<br>'
                + 'El cierre no se borra: se conserva como <b>revertido</b> con el motivo que indiques.<br><br>'
                + '<b>El motivo es obligatorio</b> y queda registrado en la auditoría.',
            input: 'textarea',
            inputPlaceholder: 'Explique por qué se reabre este período',
            inputAttributes: { 'aria-label': 'Motivo de la reapertura', rows: 3 },
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-unlock me-2"></i>Reabrir',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            reverseButtons: true,
            inputValidator: function (v) {
                if (!v || !v.trim()) { return 'Debe indicar el motivo de la reapertura.'; }
                return null;
            }
        }).then(function (r) {
            if (!r.isConfirmed) { return; }

            Swal.fire({
                title: 'Reabriendo…',
                html: '<i class="fas fa-spinner fa-spin"></i> Actualizando el período',
                allowOutsideClick: false,
                didOpen: function () { Swal.showLoading(); }
            });

            return pedir('reabrir', {
                tipo: btn.getAttribute('data-tipo'),
                anio: btn.getAttribute('data-anio'),
                mes: btn.getAttribute('data-mes'),
                motivo: r.value
            }).then(function (res) {
                Swal.closePopup();
                if (!res.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo reabrir',
                        text: res.mensaje || 'Error desconocido.',
                        confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                    });
                    return;
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Cierre reabierto',
                    text: nombre + ' vuelve a ser editable.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
                }).then(function () { window.location.reload(); });
            });
        }).catch(function (e) {
            Swal.closePopup();
            errorRed(e);
        });
    });

    /* ---------- exportar CSV ---------- */

    if ($btnExportar) {
        $btnExportar.addEventListener('click', function () {
            if (!HISTORIAL.length) {
                Swal.fire({
                    icon: 'info',
                    title: 'Nada que exportar',
                    text: 'Todavía no hay cierres registrados.',
                    confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                });
                return;
            }

            var cabecera = ['Tipo', 'Año', 'Período', 'Estado', 'Lotes', 'Trabajadores',
                            'Devengado', 'Deducciones', 'Neto', 'Contribución',
                            'Fecha de cierre', 'Usuario', 'Observaciones',
                            'Motivo de reapertura', 'Fecha de reapertura'];

            function celda(v) {
                var s = String(v === null || v === undefined ? '' : v).replace(/"/g, '""');
                return '"' + s + '"';
            }

            var filas = HISTORIAL.map(function (c) {
                return [
                    c.tipo_texto, c.anio, c.tipo === 2 ? 'Año completo' : c.etiqueta,
                    c.estado === 'cerrado' ? 'Cerrado' : 'Revertido',
                    c.total_lotes, c.total_trabajadores,
                    c.total_devengado.toFixed(2).replace('.', ','),
                    c.total_deducciones.toFixed(2).replace('.', ','),
                    c.total_neto.toFixed(2).replace('.', ','),
                    c.total_contribucion.toFixed(2).replace('.', ','),
                    c.fecha_cierre, c.usuario_cierre, c.observaciones,
                    c.motivo_reapertura, c.fecha_reapertura
                ].map(celda).join(';');
            });

            // BOM para que Excel respete UTF-8 y los acentos
            var csv = '﻿' + cabecera.join(';') + '\r\n' + filas.join('\r\n');
            var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
            var a = document.createElement('a');
            a.href = url;
            a.download = 'cierres_nomina_' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    /* ---------- ordenamiento del historial ---------- */

    (function activarOrdenHistorial() {
        var ths = document.querySelectorAll('#tablaHistorial th.js-orden');

        function ordenarPor(columna) {
            if (ORDEN_HIST.columna === columna) {
                ORDEN_HIST.dir = ORDEN_HIST.dir === 'asc' ? 'desc' : 'asc';
            } else {
                ORDEN_HIST.columna = columna;
                // Las columnas de texto y fecha empiezan de la A a la Z; las
                // de dinero y cantidad, de menor a mayor, que es lo que se busca
                // para localizar el mes mas caro o con mas trabajadores.
                ORDEN_HIST.dir = ['tipo', 'estado', 'fecha_cierre', 'periodo'].indexOf(columna) >= 0 ? 'asc' : 'desc';
            }
            $cuerpoHist.innerHTML = '';
            pintarHistorial(HISTORIAL);
        }

        Array.prototype.forEach.call(ths, function (th) {
            var col = th.getAttribute('data-orden');
            th.addEventListener('click', function () { ordenarPor(col); });
            // El th es enfocable para poder ordenarlo con el teclado.
            th.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ' || ev.key === 'Spacebar') {
                    ev.preventDefault();
                    ordenarPor(col);
                }
            });
        });
    })();

    /* ---------- arranque ---------- */

    cargarMes();
    cargarAnio();
    cargarHistorial();

})();