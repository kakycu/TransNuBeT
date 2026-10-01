/* js/carpetas.js - Explorador de carpetas del sistema (Exportaciones / Descargas) */
(function () {
    'use strict';

    /* ==========================================
       CONTRASTE DEL ACENTO
       El usuario puede elegir cualquier color de acento (incluidos brillantes
       como #10b981). El texto blanco sobre él da ~2.5:1 y resulta ilegible.
       Aquí se calcula el par fondo/texto que sí cumple 4.5:1 y se expone en
       variables CSS que consume carpetas.css.
       ========================================== */
    var MIN_CONTRASTE = 4.5;

    function aRgb(color) {
        var texto = String(color).trim();

        // Hexadecimal (#rgb, #rrggbb), que es como theme_config.php define --accent, --panel...
        if (texto.charAt(0) === '#') {
            var hex = texto.slice(1);
            if (hex.length === 3) {
                hex = hex.charAt(0) + hex.charAt(0) +
                      hex.charAt(1) + hex.charAt(1) +
                      hex.charAt(2) + hex.charAt(2);
            }
            if (hex.length >= 6) {
                return {
                    r: parseInt(hex.substr(0, 2), 16) || 0,
                    g: parseInt(hex.substr(2, 2), 16) || 0,
                    b: parseInt(hex.substr(4, 2), 16) || 0
                };
            }
            return { r: 0, g: 0, b: 0 };
        }

        var m = texto.match(/-?[\d.]+/g);
        if (!m || m.length < 3) return { r: 0, g: 0, b: 0 };
        return {
            r: Math.round(parseFloat(m[0])),
            g: Math.round(parseFloat(m[1])),
            b: Math.round(parseFloat(m[2]))
        };
    }

    function luminancia(c) {
        function f(v) {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    }

    function contraste(fg, bg) {
        var l1 = luminancia(fg);
        var l2 = luminancia(bg);
        var hi = Math.max(l1, l2);
        var lo = Math.min(l1, l2);
        return (hi + 0.05) / (lo + 0.05);
    }

    function mezclar(color, objetivo, peso) {
        return {
            r: Math.round(color.r + (objetivo.r - color.r) * peso),
            g: Math.round(color.g + (objetivo.g - color.g) * peso),
            b: Math.round(color.b + (objetivo.b - color.b) * peso)
        };
    }

    var NEGRO = { r: 0, g: 0, b: 0 };
    var BLANCO = { r: 255, g: 255, b: 255 };
    var TINTA = { r: 11, g: 18, b: 32 };   // texto oscuro para acentos muy claros

    /**
     * Devuelve el color de acento con el que el texto dado alcanza MIN_CONTRASTE,
     * mezclándolo hacia negro o blanco según convenga.
     */
    function ajustarPara(acento, texto) {
        if (contraste(texto, acento) >= MIN_CONTRASTE) return acento;

        var destino = luminancia(acento) > 0.18 ? NEGRO : BLANCO;
        var bajo = 0, alto = 1, mejor = acento;
        for (var i = 0; i < 12; i++) {
            var peso = (bajo + alto) / 2;
            var prueba = mezclar(acento, destino, peso);
            if (contraste(texto, prueba) >= MIN_CONTRASTE) {
                mejor = prueba;
                alto = peso;
            } else {
                bajo = peso;
            }
        }
        return mejor;
    }

    /**
     * Igual, pero medido contra el fondo real sobre el que se pintará.
     * Si el fondo es claro se oscurece el acento; si es oscuro, se aclara.
     */
    function ajustarSobre(acento, fondo) {
        if (contraste(acento, fondo) >= MIN_CONTRASTE) return acento;

        var destino = luminancia(fondo) > 0.4 ? NEGRO : BLANCO;
        var bajo = 0, alto = 1, mejor = acento;
        for (var i = 0; i < 12; i++) {
            var peso = (bajo + alto) / 2;
            var prueba = mezclar(acento, destino, peso);
            if (contraste(prueba, fondo) >= MIN_CONTRASTE) {
                mejor = prueba;
                alto = peso;
            } else {
                bajo = peso;
            }
        }
        return mejor;
    }

    function aplicarContraste() {
        var raiz = document.documentElement;
        var estilos = getComputedStyle(raiz);
        var acento = aRgb(estilos.getPropertyValue('--accent'));
        var panel = aRgb(estilos.getPropertyValue('--panel-2') || estilos.getPropertyValue('--panel'));

        if (!panel.r && !panel.g && !panel.b) panel = NEGRO;

        // Superficie rellena: se elige el texto que mejor rinda y, si aún no
        // llega a 4.5:1, se ajusta el relleno hasta que llegue.
        var conBlanco = contraste(BLANCO, acento);
        var conTinta = contraste(TINTA, acento);
        var texto = conBlanco >= conTinta ? BLANCO : TINTA;
        var fondo = ajustarPara(acento, texto);

        // Tinta de acento para iconos y botones delineados: se mide contra el panel.
        var tinta = ajustarSobre(acento, panel);

        raiz.style.setProperty('--carpeta-accent-bg', 'rgb(' + fondo.r + ',' + fondo.g + ',' + fondo.b + ')');
        raiz.style.setProperty('--carpeta-accent-fg', 'rgb(' + texto.r + ',' + texto.g + ',' + texto.b + ')');
        raiz.style.setProperty('--carpeta-accent-ink', 'rgb(' + tinta.r + ',' + tinta.g + ',' + tinta.b + ')');
    }

    aplicarContraste();
    // El acento y el panel cambian al cambiar de tema o de color de acento.
    if (window.MutationObserver) {
        new MutationObserver(aplicarContraste).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme', 'style', 'class']
        });
    }
    window.addEventListener('storage', aplicarContraste);

    var btnAbrir = document.getElementById('btnAbrirCarpeta');
    if (btnAbrir) {
        btnAbrir.addEventListener('click', function () {
            var btn = this;
            var carpeta = btn.dataset.carpeta;

            // Apertura silenciosa: sin modales, solo se comunica el error si falla.
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Abriendo...';

            var cuerpo = new FormData();
            cuerpo.append('carpeta', carpeta);

            var restaurar = function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-folder-open me-2"></i>Abrir Carpeta';
            };

            fetch('abrir_carpeta.php', {
                method: 'POST',
                body: cuerpo,
                credentials: 'same-origin'
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    restaurar();
                    if (!data || !data.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se pudo abrir la carpeta',
                            text: (data && data.mensaje) ? data.mensaje : 'Error desconocido.',
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                    }
                })
                .catch(function () {
                    restaurar();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de comunicación',
                        text: 'No se pudo contactar el servidor.',
                        confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                    });
                });
        });
    }

    /* ==========================================
       ELIMINAR ARCHIVO (solo rol Administrador)
       ========================================== */
    var botonesEliminar = document.querySelectorAll('.btn-eliminar-archivo');

    Array.prototype.forEach.call(botonesEliminar, function (boton) {
        boton.addEventListener('click', function () {
            var btn = this;
            var carpeta = btn.dataset.carpeta;
            var nombre = btn.dataset.nombre;

Swal.fire({
    icon: 'warning',
    title: 'Confirmar eliminación',
    html: '¿Está seguro que desea eliminar el archivo: <b>' + escapar(nombre) +
          '</b>, de la carpeta: <b>' + escapar(carpeta) + '</b>?',
    showCancelButton: true,
    showCloseButton: true,
    confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
    reverseButtons: true,
    focusCancel: true,
    allowOutsideClick: false,
    allowEscapeKey: true,
    customClass: { confirmButton: 'swal2-confirm btn-confirmar-eliminar' },
    didOpen: function () {
        // X con transición suave: solo cambia de color al pasar el mouse
        var btnCerrar = Swal.getCloseButton();
        if (btnCerrar) {
            btnCerrar.style.transition = 'color 0.15s ease, opacity 0.15s ease';
            btnCerrar.style.opacity = '0.6';
            btnCerrar.style.transform = 'none';

            btnCerrar.addEventListener('mouseenter', function () {
                btnCerrar.style.transform = 'none';
                btnCerrar.style.opacity = '1';
                btnCerrar.style.color = '#d33';
            });
            btnCerrar.addEventListener('mouseleave', function () {
                btnCerrar.style.opacity = '0.6';
                btnCerrar.style.color = '';
            });
        }
    }
}).then(function (r) {
    if (!r.isConfirmed) return;

    var cuerpo = new FormData();
    cuerpo.append('carpeta', carpeta);
    cuerpo.append('archivo', nombre);

    Swal.fire({
        title: 'Eliminando...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function () { Swal.showLoading(); }
    });

    fetch('eliminar_archivo.php', {
        method: 'POST',
        body: cuerpo,
        credentials: 'same-origin'
    })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (!data || !data.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo eliminar',
                    text: (data && data.mensaje) ? data.mensaje : 'Error desconocido.',
                    confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                });
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Archivo eliminado',
                text: data.nombre,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            }).then(function () { window.location.reload(); });
        })
        .catch(function () {
            Swal.fire({
                icon: 'error',
                title: 'Error de comunicación',
                text: 'No se pudo contactar el servidor.',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
            });
        });
});
        });
    });

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : texto;
        return d.innerHTML;
    }

    /* ==========================================
       FILTRADO POR TIPO Y BÚSQUEDA DE ARCHIVOS
       ========================================== */
    var filtroExt = document.getElementById('filtroExtension');
    var buscar = document.getElementById('buscarArchivo');
    var tbody = document.getElementById('tbodyCarpetas');

    if (!tbody || (!filtroExt && !buscar)) return;

    var filas = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var sinResultados = document.getElementById('sinResultados');
    var tablaCarpetas = tbody.closest('table');
    function botonesOrden() {
        return tablaCarpetas ? tablaCarpetas.querySelectorAll('thead .th-sort') : [];
    }

    function aplicarFiltros() {
        var ext = filtroExt ? filtroExt.value.toLowerCase() : '';
        var texto = buscar ? buscar.value.trim().toLowerCase() : '';
        var visibles = 0;

        filas.forEach(function (fila) {
            var coincideExt = !ext || (fila.dataset.extension || '').toLowerCase() === ext;
            var coincideTexto = !texto || (fila.dataset.nombre || '').toLowerCase().indexOf(texto) !== -1;
            var mostrar = coincideExt && coincideTexto;
            fila.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });

        if (sinResultados) sinResultados.style.display = visibles === 0 ? '' : 'none';
    }

    /* ==========================================
       ORDENAR POR COLUMNA (Archivo / Tamaño / Modificado)
       Clic en el encabezado alterna ascendente/descendente.
       Solo reordena filas visibles; el filtro se respeta.
       ========================================== */
    var ordenActual = { campo: 'fecha', dir: 'desc' };  // coincide con el orden por defecto del PHP

    var comparadores = {
        nombre: function (a, b) {
            return (a.dataset.nombre || '').localeCompare(b.dataset.nombre || '', 'es', { sensitivity: 'base', numeric: true });
        },
        bytes: function (a, b) {
            return (parseInt(a.dataset.bytes, 10) || 0) - (parseInt(b.dataset.bytes, 10) || 0);
        },
        fecha: function (a, b) {
            return (parseInt(a.dataset.fecha, 10) || 0) - (parseInt(b.dataset.fecha, 10) || 0);
        }
    };

    function refrescarIconos() {
        botonesOrden().forEach(function (btn) {
            var icono = btn.querySelector('.th-sort-icono');
            var esActiv = btn.dataset.orden === ordenActual.campo;
            btn.classList.toggle('activa', esActiv);
            if (icono) {
                /* classList para NO perder la clase th-sort-icono del <i> */
                icono.classList.remove('fa-sort', 'fa-sort-up', 'fa-sort-down');
                icono.classList.add(esActiv
                    ? (ordenActual.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down')
                    : 'fa-sort');
            }
        });
    }

    function aplicarOrden() {
        var cmp = comparadores[ordenActual.campo];
        if (!cmp) return;
        var visibles = filas.filter(function (f) { return f.style.display !== 'none'; });
        visibles.sort(function (a, b) {
            return ordenActual.dir === 'asc' ? cmp(a, b) : -cmp(a, b);
        });
        visibles.forEach(function (fila) { tbody.appendChild(fila); });
        refrescarIconos();
    }

    botonesOrden().forEach(function (btn) {
        btn.addEventListener('click', function () {
            var campo = btn.dataset.orden;
            if (ordenActual.campo === campo) {
                ordenActual.dir = ordenActual.dir === 'asc' ? 'desc' : 'asc';
            } else {
                ordenActual = { campo: campo, dir: 'asc' };
            }
            aplicarOrden();
        });
    });

    refrescarIconos();

    if (filtroExt) filtroExt.addEventListener('change', aplicarFiltros);
    if (buscar) buscar.addEventListener('input', aplicarFiltros);
})();