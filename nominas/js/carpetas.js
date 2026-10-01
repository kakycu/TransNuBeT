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
       REDIMENSIONAR COLUMNAS (estilo Explorador de Windows)
       Arrastrar el borde del encabezado cambia el ancho; doble clic ajusta
       la columna al contenido. Los anchos se guardan por carpeta.
       ========================================== */
    (function inicializarColumnasRedimensionales() {
        var tabla = document.getElementById('tablaCarpetas');
        if (!tabla) return;

        var carpeta = document.body.getAttribute('data-carpeta') || 'exportaciones';
        var CLAVE = 'transnubet.carpetas.columnas.' + carpeta;
        var ANCHO_MIN = 64;      // px, ancho minimo de la columna arrastrada
        var ANCHO_MIN_OTRAS = 96; // px, ancho minimo de las columnas que se reparten
        var cols = tabla.querySelectorAll('colgroup col');
        var ths = tabla.querySelectorAll('thead th');
        if (!cols.length || !ths.length) return;

        var guia = document.createElement('div');
        guia.className = 'guia-columna';
        document.body.appendChild(guia);

        /* ---- Anchos por defecto (proporcionales, como el Explorador) ----
           La ultima columna (Accion) es fija: solo iconos, no se redimensiona. */
        var ULTIMA = cols.length - 1;
        var ANCHO_ACCION = 72; // px, ancho fijo de la columna de acciones
        var POR_DEFECTO = [53, 15, 18, 14];
        var anchos = POR_DEFECTO.slice();
        anchos.length = cols.length;

        /* Ancho fijo en px para la columna de acciones */
        cols[ULTIMA].style.width = ANCHO_ACCION + 'px';

        function aplicar() {
            for (var i = 0; i < cols.length; i++) {
                if (i === ULTIMA) {
                    cols[i].style.width = ANCHO_ACCION + 'px';
                } else {
                    cols[i].style.width = anchos[i] + '%';
                }
            }
        }

        function guardar() {
            try { localStorage.setItem(CLAVE, JSON.stringify(anchos)); } catch (e) { /* sin soporte */ }
        }

        function cargar() {
            try {
                var guardado = JSON.parse(localStorage.getItem(CLAVE));
                if (Array.isArray(guardado) && guardado.length === cols.length) {
                    anchos = guardado.map(function (v) {
                        var n = Number(v);
                        return (isFinite(n) && n > 0) ? n : 10;
                    });
                }
            } catch (e) { /* usar valores por defecto */ }

            /* La columna de acciones no se guarda: siempre es fija */
            anchos[ULTIMA] = POR_DEFECTO[ULTIMA] || 15;
        }

        /* ---- Ancho minimo de cada columna, en px ----
           Archive: suficiente para el icono + unas letras.
           Tamaño: la cifra mas corta con su unidad.
           Modificado: la fecha completa, que es lo mas ancho. */
        var MIN_PX = [120, 80, 150];

        /* ---- Reparte el ancho entre las columnas ----
           La columna objetivo toma el ancho pedido; el resto se reparte
           proporcionalmente entre las demas respetando su minimo propio.
           Accion es fija. La suma siempre es exactamente 100. */
        function repartir(anchos, indice, pctIndice, anchoTabla) {
            var total = anchos.length;
            var i;

            if (indice === ULTIMA) return;

            /* Anchos fijos y minimos expresados en % de la tabla */
            var pctAccion = (ANCHO_ACCION / anchoTabla) * 100;
            var minPct = MIN_PX.map(function (px) { return (px / anchoTabla) * 100; });
            minPct[ULTIMA] = pctAccion;

            /* Cuanto pueden tomar las demas sumando sus minimos */
            var reservado = pctAccion;
            for (i = 0; i < total; i++) {
                if (i !== indice) reservado += minPct[i];
            }
            var maximo = 100 - reservado;

            if (pctIndice > maximo) pctIndice = maximo;
            if (pctIndice < minPct[indice]) pctIndice = minPct[indice];

            /* Espacio que se reparten las demas columnas */
            var resto = 100 - pctIndice - pctAccion;
            var sumaResto = 0;
            for (i = 0; i < total; i++) {
                if (i !== indice && i !== ULTIMA) sumaResto += anchos[i];
            }
            if (sumaResto <= 0) sumaResto = 1;

            var nuevas = anchos.slice();
            var enMinimo = [];
            nuevas[ULTIMA] = pctAccion;

            for (i = 0; i < total; i++) {
                if (i === indice || i === ULTIMA) continue;
                var valor = (anchos[i] / sumaResto) * resto;
                if (valor < minPct[i]) {
                    valor = minPct[i];
                    enMinimo.push(i);
                }
                nuevas[i] = valor;
            }

            /* La holgura que sobra (o falta) va a las columnas con espacio */
            var sumaOtras = 0;
            for (i = 0; i < total; i++) {
                if (i !== indice && i !== ULTIMA) sumaOtras += nuevas[i];
            }
            var holgura = resto - sumaOtras;

            var libres = [];
            for (i = 0; i < total; i++) {
                if (i !== indice && i !== ULTIMA && enMinimo.indexOf(i) === -1) libres.push(i);
            }
            if (libres.length && Math.abs(holgura) > 0.001) {
                var cuota = holgura / libres.length;
                for (i = 0; i < libres.length; i++) nuevas[libres[i]] += cuota;
            }

            nuevas[indice] = pctIndice;

            /* Redondeo a 2 decimales; la diferencia va a la columna con
               mas holgura para no romper ni la suma ni los minimos. */
            var suma = 0;
            for (i = 0; i < total; i++) {
                nuevas[i] = Math.round(nuevas[i] * 100) / 100;
                suma += nuevas[i];
            }
            var diferencia = Math.round((100 - suma) * 100) / 100;
            if (diferencia !== 0) {
                var holgado = -1;
                var mayorHolgura = 0;
                for (i = 0; i < total; i++) {
                    if (i === indice) continue;
                    var margen = nuevas[i] - minPct[i];
                    if (margen > mayorHolgura) {
                        mayorHolgura = margen;
                        holgado = i;
                    }
                }
                if (holgado !== -1) {
                    nuevas[holgado] = Math.round((nuevas[holgado] + diferencia) * 100) / 100;
                } else {
                    nuevas[indice] = Math.round((nuevas[indice] + diferencia) * 100) / 100;
                }
            }

            for (i = 0; i < total; i++) anchos[i] = nuevas[i];
            aplicar();
        }

        /* ---- Arrastre ---- */
        var arrastre = null;
        var movido = false;
        var UMBRAL_MOVIMIENTO = 3; // px, por debajo no cuenta como arrastre

        function iniciarArrastre(evento, indice) {
            evento.preventDefault();
            evento.stopPropagation();

            var cajaTh = ths[indice].getBoundingClientRect();
            movido = false;
            arrastre = {
                indice: indice,
                xInicial: evento.clientX,
                anchoInicial: cajaTh.width,
                anchoTabla: tabla.getBoundingClientRect().width
            };

            tabla.classList.add('redimensionando');
            document.body.classList.add('redimensionando-columnas');
            guia.style.display = 'block';
            moverGuia(evento.clientX);

            document.addEventListener('mousemove', alMover);
            document.addEventListener('mouseup', alSoltar);
        }

        function moverGuia(x) {
            guia.style.left = (x - 2) + 'px';
        }

        function alMover(evento) {
            if (!arrastre) return;
            evento.preventDefault();

            var i = arrastre.indice;
            var delta = evento.clientX - arrastre.xInicial;
            if (Math.abs(delta) >= UMBRAL_MOVIMIENTO) movido = true;

            var anchoPx = arrastre.anchoInicial + delta;
            if (anchoPx < ANCHO_MIN) anchoPx = ANCHO_MIN;

            var pctPx = (anchoPx / arrastre.anchoTabla) * 100;
            var pctMin = (ANCHO_MIN / arrastre.anchoTabla) * 100;
            if (pctPx < pctMin) pctPx = pctMin;

            repartir(anchos, i, pctPx, arrastre.anchoTabla);
            moverGuia(evento.clientX);
        }

        function alSoltar() {
            document.removeEventListener('mousemove', alMover);
            document.removeEventListener('mouseup', alSoltar);
            tabla.classList.remove('redimensionando');
            document.body.classList.remove('redimensionando-columnas');
            guia.style.display = 'none';
            if (arrastre) actualizarAria(arrastre.indice);
            arrastre = null;
            /* Sin movimiento real no se guarda: un doble clic generates dos
               arrastres de cero píxeles que deformarían los anchos. */
            if (movido) guardar();
            movido = false;
        }

        /* ---- Ancho real de un elemento con su texto sin recortar ----
           scrollWidth solo sirve si el texto esta siendo recortado: si la
           columna es mas ancha, scrollWidth coincide con clientWidth y no
           dicen nada. Se mide con un clon sin limites de ancho. */
        function anchoReal(elemento) {
            if (!elemento) return 0;
            var cs = getComputedStyle(elemento);
            if (elemento.scrollWidth > elemento.clientWidth + 1) {
                return elemento.scrollWidth;
            }
            var clon = elemento.cloneNode(true);
            clon.style.position = 'absolute';
            clon.style.visibility = 'hidden';
            clon.style.left = '-9999px';
            clon.style.top = '0';
            clon.style.width = 'max-content';
            clon.style.maxWidth = 'none';
            clon.style.minWidth = '0';
            clon.style.whiteSpace = 'nowrap';
            clon.style.overflow = 'visible';
            clon.style.textOverflow = 'clip';
            document.body.appendChild(clon);
            var ancho = clon.getBoundingClientRect().width;
            document.body.removeChild(clon);
            return ancho || parseFloat(cs.fontSize) * elemento.textContent.length * 0.6;
        }

        /* ---- Doble clic: ajustar al contenido ----
           Mide la columna mas larga de TODAS las filas, tambien las que
           estan ocultas por un filtro, para que el ajuste no dependa del
           filtro que este puesto. */
        function ajustarAlContenido(indice) {
            var anchoMax = 0;
            var celdas = tabla.querySelectorAll('tbody tr');
            var i;

            for (i = 0; i < celdas.length; i++) {
                var celda = celdas[i].children[indice];
                if (!celda) continue;

                var actual;
                if (indice === 0) {
                    var nombre = celda.querySelector('.carpeta-nombre');
                    if (!nombre) continue;
                    actual = anchoReal(nombre) + 40; // icono + holguras
                } else {
                    actual = anchoReal(celda) + 16;
                }
                if (actual > anchoMax) anchoMax = actual;
            }

            if (!anchoMax) return;

            var anchoTabla = tabla.getBoundingClientRect().width;
            if (!anchoTabla) return;

            var pct = Math.min(90, (anchoMax / anchoTabla) * 100);
            repartir(anchos, indice, pct, anchoTabla);
            guardar();
            actualizarAria(indice);
        }

        /* ---- Ancho actual en porcentaje, para lectores de pantalla ---- */
        function actualizarAria(indice) {
            var grip = ths[indice].querySelector('.th-grip');
            if (!grip) return;
            grip.setAttribute('aria-valuenow', Math.round(anchos[indice]));
        }

        ths.forEach(function (th, indice) {
            var grip = th.querySelector('.th-grip');
            if (!grip) return;
            grip.addEventListener('mousedown', function (e) { iniciarArrastre(e, indice); });
            grip.addEventListener('dblclick', function (e) { e.preventDefault(); ajustarAlContenido(indice); });

            /* Accesible desde teclado: flechas mueven el borde */
            grip.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
                e.preventDefault();
                var anchoTabla = tabla.getBoundingClientRect().width;
                if (!anchoTabla) return;
                var paso = e.shiftKey ? 40 : 8;
                var anchoTh = ths[indice].getBoundingClientRect().width;
                var destino = anchoTh + (e.key === 'ArrowRight' ? paso : -paso);
                repartir(anchos, indice, (destino / anchoTabla) * 100, anchoTabla);
                guardar();
                actualizarAria(indice);
            });

            grip.tabIndex = 0;
            grip.setAttribute('role', 'separator');
            grip.setAttribute('aria-orientation', 'vertical');
            grip.setAttribute('aria-label', 'Ajustar ancho de la columna ' + th.dataset.col);
        });

        cargar();
        aplicar();
        anchos.forEach(function (v, i) {
            if (i !== ULTIMA) actualizarAria(i);
        });

        /* ---- Tooltips de Bootstrap en los iconos de accion ---- */
        if (window.bootstrap && window.bootstrap.Tooltip) {
            Array.prototype.forEach.call(
                document.querySelectorAll('.carpeta-accion-icono[data-bs-toggle="tooltip"]'),
                function (el) { new bootstrap.Tooltip(el); }
            );
        }
    })();

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