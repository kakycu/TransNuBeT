/* js/carpetas.js - Explorador de carpetas del sistema (Exportaciones / Descargas / Salvas) */
(function () {
    'use strict';

    /* ==========================================
       ACCESIBILIDAD DE LOS BOTONES DE SWEETALERT2
       La libreria inyecta aria-label="" (vacio) en confirm/cancel/deny.
       Un aria-label vacio anula el texto visible y deja el boton mudo
       para los lectores de pantalla. Tras abrir cualquier dialogo se
       rellena con el propio texto del boton.
       ========================================== */
    function parchearAriaBotonesSwal() {
        ['.swal2-confirm', '.swal2-cancel', '.swal2-deny'].forEach(function (sel) {
            Array.prototype.forEach.call(document.querySelectorAll(sel), function (boton) {
                if (boton.getAttribute('aria-label') === '') {
                    var texto = (boton.textContent || '').replace(/\s+/g, ' ').trim();
                    if (texto) { boton.setAttribute('aria-label', texto); }
                }
            });
        });
    }

    var swalFireOriginal = Swal.fire;
    Swal.fire = function () {
        var apertura = swalFireOriginal.apply(this, arguments);
        parchearAriaBotonesSwal();
        setTimeout(parchearAriaBotonesSwal, 0);
        return apertura;
    };

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
            var ruta = (btn.dataset.ruta || '').replace(/^\/+|\/+$/g, '');

            // Apertura silenciosa: sin modales, solo se comunica el error si falla.
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Abriendo...';

            var cuerpo = new FormData();
            cuerpo.append('carpeta', carpeta);
            cuerpo.append('ruta', ruta);

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
       ACTUALIZAR LISTADO (cualquier rol con permiso de ver)
       El listado lo pinta PHP, asi que actualizar es recargar la carpeta
       abierta: el boton se bloquea y gira para que no se dispare dos veces.
       ========================================== */
    var btnActualizar = document.getElementById('btnActualizarCarpeta');
    if (btnActualizar) {
        btnActualizar.addEventListener('click', function () {
            var btn = this;
            if (btn.disabled) return;

            /* Solo gira el icono: cambiar la etiqueta por "Actualizando..."
               ensancha el boton y rompe la linea de la barra mientras carga. */
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            var icono = btn.querySelector('i');
            if (icono) icono.classList.add('fa-spin');

            window.location.reload();
        });
    }

    /* ==========================================
       NAVEGACION RAPIDA: RAIZ / ATRAS / SUBIR
       ========================================== */
    var btnRaiz = document.getElementById('btnCarpetaRaiz');
    if (btnRaiz) {
        btnRaiz.addEventListener('click', function () {
            if (this.disabled) return;
            window.location.href = this.dataset.url;
        });
    }

    var btnSubir = document.getElementById('btnCarpetaSubir');
    if (btnSubir) {
        btnSubir.addEventListener('click', function () {
            if (this.disabled) return;
            window.location.href = this.dataset.url;
        });
    }

    var btnAtras = document.getElementById('btnCarpetaAtras');
    if (btnAtras) {
        btnAtras.addEventListener('click', function () {
            if (this.disabled) return;
            /* Si el historial solo tiene esta pagina (llegada directa),
               se vuelve a la raiz de la carpeta. */
            if (window.history.length > 1) {
                window.history.back();
            } else {
                var raiz = document.getElementById('btnCarpetaRaiz');
                if (raiz && raiz.dataset.url) { window.location.href = raiz.dataset.url; }
            }
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
           La ultima columna (Accion) es fija: solo iconos, no se redimensiona.
           Con seleccion multiple el listado tiene 6 columnas: la primera
           (checkbox, solo para roles que pueden eliminar) es estrecha y no
           tiene empuñe. Sin esa columna se mantienen los 5 anchos de siempre
           (los anchos guardados en localStorage solo sirven si la cantidad de
           columnas coincide: cargar() lo comprueba). */
        var ULTIMA = cols.length - 1;
        var ANCHO_ACCION = 72; // px de referencia para la columna de acciones
        var COL_CHK = cols.length === 6;
        var COL_COMPENSA = COL_CHK ? 1 : 0; // donde va la diferencia de redondeo
        var POR_DEFECTO = COL_CHK
            ? [4, 41, 10, 13, 18, 14]  // Seleccion, Archivo, Tipo, Tama\u00f1o, Modificado, Acci\u00f3n
            : [45, 10, 13, 18, 14];    // Archivo, Tipo, Tama\u00f1o, Modificado, Acci\u00f3n
        var anchos = POR_DEFECTO.slice();
        anchos.length = cols.length;

        /* ---- Todas las columnas en porcentaje, incluida la de acciones ----
           Mezclar porcentajes con una columna en px dentro de una tabla con
           table-layout:fixed hace que Chrome sume todo literalmente y la
           tabla se haga mas ancha que su contenedor (la columna de acciones
           se sale de la pantalla); Firefox reparte el sobrante y se ve bien.
           Con las cuatro columnas en porcentaje la suma es 100% en cualquier
           navegador. La de acciones se recalcula a partir de ANCHO_ACCION. */
        function pctAccion() {
            var ancho = contenedorAncho();
            if (!ancho) return ANCHO_ACCION / 1000 * 100;
            return (ANCHO_ACCION / ancho) * 100;
        }

        function contenedorAncho() {
            var caja = tabla.parentNode; // .table-responsive
            if (caja && caja.getBoundingClientRect().width) {
                return caja.getBoundingClientRect().width;
            }
            return tabla.getBoundingClientRect().width;
        }

        function aplicar() {
            var pAccion = pctAccion();
            anchos[ULTIMA] = pAccion;
            for (var i = 0; i < cols.length; i++) {
                cols[i].style.width = anchos[i] + '%';
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

            ajustarPorcentajeTotal();
        }

        /* ---- Suma exacta de 100% ----
           Las columnas se guardan en porcentaje, pero la de acciones depende
           del ancho real de la tabla. Se reescalan las otras para que las
           cuatro sumen 100% y nigun navegador tenga que repartirse el
           sobrante por su cuenta. */
        function ajustarPorcentajeTotal() {
            var pAccion = pctAccion();
            var disponible = 100 - pAccion;
            var suma = 0;
            var i;

            for (i = 0; i < ULTIMA; i++) suma += anchos[i];
            if (suma <= 0) return;

            var factor = disponible / suma;
            for (i = 0; i < ULTIMA; i++) {
                anchos[i] = Math.round((anchos[i] * factor) * 100) / 100;
            }
            anchos[ULTIMA] = Math.round(pAccion * 100) / 100;

            /* El redondeo deja algun decimal de diferencia: se compensa */
            var total = 0;
            for (i = 0; i < cols.length; i++) total += anchos[i];
            var diferencia = Math.round((100 - total) * 100) / 100;
            if (diferencia !== 0) {
                anchos[COL_COMPENSA] = Math.round((anchos[COL_COMPENSA] + diferencia) * 100) / 100;
            }
        }

        /* ---- Ancho minimo de cada columna, en px ----
           Archivo: suficiente para el icono + unas letras.
           Tipo: la extension mas larga (sin contar la carpeta).
           Tama\u00f1o: la cifra mas corta con su unidad.
           Modificado: la fecha completa, que es lo mas ancho.
           Seleccion (si existe): el checkbox centrado. */
        var MIN_PX = COL_CHK ? [36, 120, 60, 80, 150] : [120, 60, 80, 150];

        /* ---- Reparte el ancho entre las columnas ----
           La columna objetivo toma el ancho pedido; el resto se reparte
           proporcionalmente entre las demas respetando su minimo propio.
           Accion es fija. La suma siempre es exactamente 100. */
        function repartir(anchos, indice, pctIndice, anchoTabla) {
            var total = anchos.length;
            var i;

            if (indice === ULTIMA) return;

            /* Anchos fijos y minimos expresados en % de la tabla */
            var pctAccionCol = pctAccion();
            var minPct = MIN_PX.map(function (px) { return (px / anchoTabla) * 100; });
            minPct[ULTIMA] = pctAccionCol;

            /* Cuanto pueden tomar las demas sumando sus minimos */
            var reservado = pctAccionCol;
            for (i = 0; i < total; i++) {
                if (i !== indice) reservado += minPct[i];
            }
            var maximo = 100 - reservado;

            if (pctIndice > maximo) pctIndice = maximo;
            if (pctIndice < minPct[indice]) pctIndice = minPct[indice];

            /* Espacio que se reparten las demas columnas */
            var resto = 100 - pctIndice - pctAccionCol;
            var sumaResto = 0;
            for (i = 0; i < total; i++) {
                if (i !== indice && i !== ULTIMA) sumaResto += anchos[i];
            }
            if (sumaResto <= 0) sumaResto = 1;

            var nuevas = anchos.slice();
            var enMinimo = [];
            nuevas[ULTIMA] = pctAccionCol;

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

        /* ---- Al cambiar el tamaño de la ventana ----
           Los anchos estan en porcentaje, pero la columna de acciones
           necesita seguir midiendo unos 72px: se reescala al nuevo ancho. */
        var temporizadorResize = null;
        window.addEventListener('resize', function () {
            if (temporizadorResize) clearTimeout(temporizadorResize);
            temporizadorResize = setTimeout(function () {
                ajustarPorcentajeTotal();
                aplicar();
            }, 150);
        });

        /* ---- Boton "restablecer anchos" ----
           Vuelve al ancho por defecto y borra lo guardado, para no tener
           que buscar la consola cuando una columna quedo muy angosta. */
        function restablecerAnchos() {
            anchos = POR_DEFECTO.slice();
            anchos.length = cols.length;
            ajustarPorcentajeTotal();
            aplicar();
            guardar();
            anchos.forEach(function (v, i) {
                if (i !== ULTIMA) actualizarAria(i);
            });
        }

        var btnRestablecer = document.getElementById('btnRestablecerAnchos');
        if (btnRestablecer) {
            btnRestablecer.addEventListener('click', restablecerAnchos);
        }

        /* ---- Tooltips de Bootstrap en los botones de la tabla ---- */
        if (window.bootstrap && window.bootstrap.Tooltip) {
            Array.prototype.forEach.call(
                document.querySelectorAll('.carpeta-accion-icono[data-bs-toggle="tooltip"], ' +
                                         '.carpeta-restablecer-anchos[data-bs-toggle="tooltip"]'),
                function (el) { new bootstrap.Tooltip(el); }
            );
        }
    })();

    /* ==========================================
       CIERRE X DE LOS SWAL DE ESTE MODULO
       Un solo sitio para la transición suave de la X: se usa en el aviso de
       eliminación de archivo y en el de vaciado de carpeta.
       ========================================== */
    function prepararCierreSwal() {
        var btnCerrar = Swal.getCloseButton();
        if (!btnCerrar) return;

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

    /* ==========================================
       ELIMINAR ARCHIVO (solo rol Administrador)
       ========================================== */
    var botonesEliminar = document.querySelectorAll('.btn-eliminar-archivo');

    Array.prototype.forEach.call(botonesEliminar, function (boton) {
        boton.addEventListener('click', function () {
            var btn = this;
            var carpeta = btn.dataset.carpeta;
            var nombre = btn.dataset.nombre;
            var esCarpeta = btn.dataset.tipo === 'carpeta';

Swal.fire({
    icon: 'warning',
    title: esCarpeta ? 'Confirmar eliminación de carpeta' : 'Confirmar eliminación',
    html: esCarpeta
        ? '¿Está seguro que desea eliminar la carpeta: <b>' + escapar(nombre) +
          '</b> y <b>todo su contenido</b>, de la carpeta: <b>' + escapar(carpeta) + '</b>?' +
          '<br><small class="text-danger">Esta acción no se puede deshacer.</small>'
        : '¿Está seguro que desea eliminar el archivo: <b>' + escapar(nombre) +
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
    didOpen: prepararCierreSwal
}).then(function (r) {
    if (!r.isConfirmed) return;

    var cuerpo = new FormData();
    cuerpo.append('carpeta', carpeta);
    cuerpo.append('archivo', nombre);
    cuerpo.append('tipo', esCarpeta ? 'carpeta' : 'archivo');
    if (btn.dataset.ruta) { cuerpo.append('ruta', btn.dataset.ruta); }

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
            /* Parsea el JSON aunque la respuesta sea 4xx/5xx: el servidor
               responde JSON con el motivo y hay que mostrarlo, no enmascararlo
               como error de comunicacion. */
            return r.text().then(function (txt) {
                var datos = null;
                try { datos = JSON.parse(txt); } catch (e) { datos = null; }
                if (!datos) { throw new Error('HTTP ' + r.status); }
                return datos;
            });
        })
        .then(function (data) {
            if (!data || !data.success) {
                Swal.fire({
                    icon: 'error',
                    title: esCarpeta ? 'No se pudo eliminar la carpeta' : 'No se pudo eliminar el archivo',
                    text: (data && data.mensaje) ? data.mensaje : 'Error desconocido.',
                    confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                });
                return;
            }
            Swal.fire({
                icon: 'success',
                title: esCarpeta ? 'Carpeta eliminada' : 'Archivo eliminado',
                text: data.nombre,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            }).then(function () { window.location.reload(); });
        })
        .catch(function (err) {
            var esHttp = err && typeof err.message === 'string' && err.message.indexOf('HTTP ') === 0;
            Swal.fire({
                icon: 'error',
                title: esHttp ? 'Error del servidor' : 'Error de comunicaci\u00f3n',
                text: esHttp
                    ? 'El servidor respondi\u00f3 ' + err.message.slice(5) + ' y no devolvi\u00f3 JSON.'
                    : 'No se pudo contactar el servidor.',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
            });
        });
});
        });
    });

    /* ==========================================
       SELECCION MULTIPLE Y ELIMINACION EN LOTE
       La columna de checkbox solo existe si el rol puede eliminar
       (carpetas.php la renderiza condicionada a carpetas_sistema_puede('eliminar')).
       El header marca/desmarca los archivos VISIBLES (pagina + filtro actual);
       el contador y el boton tienen en cuenta TODOS los marcados, aunque
       este en otra pagina. Al terminar se recarga el listado.
       ========================================== */
    var chkSelTodo = document.getElementById('chkSelTodo');
    var btnEliminarSel = document.getElementById('btnEliminarSeleccion');
    var selCantidad = document.getElementById('selCantidad');
    var tbodySel = document.getElementById('tbodyCarpetas');

    if (chkSelTodo && btnEliminarSel && tbodySel) {
        function selArchivos() {
            return Array.prototype.slice.call(tbodySel.querySelectorAll('.chk-archivo'));
        }
        function selVisibles() {
            return selArchivos().filter(function (chk) {
                var fila = chk.closest('tr');
                return fila && fila.style.display !== 'none';
            });
        }

        function refrescarSeleccion() {
            var todos = selArchivos();
            var visibles = selVisibles();
            var marcados = todos.filter(function (chk) { return chk.checked; });
            var marcadosVisibles = visibles.filter(function (chk) { return chk.checked; });

            chkSelTodo.checked = visibles.length > 0 && marcadosVisibles.length === visibles.length;
            chkSelTodo.indeterminate = marcadosVisibles.length > 0 && marcadosVisibles.length < visibles.length;

            todos.forEach(function (chk) {
                var fila = chk.closest('tr');
                if (fila) { fila.classList.toggle('seleccionada', chk.checked); }
            });

            if (selCantidad) { selCantidad.textContent = String(marcados.length); }
            btnEliminarSel.hidden = marcados.length === 0;
        }

        tbodySel.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('chk-archivo')) {
                refrescarSeleccion();
            }
        });

        chkSelTodo.addEventListener('change', function () {
            var estado = this.checked;
            selVisibles().forEach(function (chk) { chk.checked = estado; });
            refrescarSeleccion();
        });

        btnEliminarSel.addEventListener('click', function () {
            var marcados = selArchivos().filter(function (chk) { return chk.checked; });
            if (!marcados.length) return;

            var nombres = marcados.map(function (chk) { return chk.dataset.nombre; });
            var nCarpetas = marcados.filter(function (chk) { return chk.dataset.tipo === 'carpeta'; }).length;
            var nArchivos = nombres.length - nCarpetas;
            var carpeta = document.body.getAttribute('data-carpeta') || '';
            var filaRef = marcados[0].closest('tr');
            var ruta = filaRef ? (filaRef.dataset.ruta || '') : '';

            var items = marcados.slice(0, 10).map(function (chk) {
                var esCar = chk.dataset.tipo === 'carpeta';
                return '<li><i class="fas ' + (esCar ? 'fa-folder' : 'fa-file-lines') +
                    ' me-1"></i>' + escapar(chk.dataset.nombre) +
                    (esCar ? ' <small class="text-warning">(carpeta)</small>' : '') + '</li>';
            }).join('');
            var resto = nombres.length > 10
                ? '<br><small>&hellip;y ' + (nombres.length - 10) + ' m&aacute;s</small>'
                : '';

            var partes = [];
            if (nArchivos) { partes.push(nArchivos + ' archivo' + (nArchivos === 1 ? '' : 's')); }
            if (nCarpetas) { partes.push(nCarpetas + ' carpeta' + (nCarpetas === 1 ? '' : 's')); }
            var resumen = partes.join(' y ');

            Swal.fire({
                icon: 'warning',
                title: 'Eliminar ' + resumen,
                html: 'Se eliminar&aacute;n de la carpeta <b>' + escapar(carpeta) + '</b>:' +
                      '<ul style="max-height:12rem;overflow:auto;text-align:left;padding-left:1.25rem;margin:.5rem 0">' +
                      items + '</ul>' + resto +
                      (nCarpetas
                          ? '<br><small class="text-warning"><i class="fas fa-folder me-1"></i>Las carpetas se eliminan con <b>todo su contenido</b>.</small>'
                          : '') +
                      '<br><small class="text-danger">Esta acci&oacute;n no se puede deshacer.</small>',
                showCancelButton: true,
                showCloseButton: true,
                confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>S&iacute;, eliminar (' + nombres.length + ')',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                reverseButtons: true,
                focusCancel: true,
                allowOutsideClick: false,
                allowEscapeKey: true,
                customClass: { confirmButton: 'swal2-confirm btn-confirmar-eliminar' },
                didOpen: prepararCierreSwal
            }).then(function (r) {
                if (!r.isConfirmed) return;

                var cuerpo = new FormData();
                cuerpo.append('carpeta', carpeta);
                if (ruta) { cuerpo.append('ruta', ruta); }
                nombres.forEach(function (n) { cuerpo.append('archivos[]', n); });

                Swal.fire({
                    title: 'Eliminando ' + resumen + '&hellip;',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch('eliminar_archivo.php', {
                    method: 'POST',
                    body: cuerpo,
                    credentials: 'same-origin'
                })
                    .then(function (resp) {
                        /* Mismo criterio que el borrado individual: parsear el
                           JSON aunque la respuesta no sea 2xx para mostrar el
                           motivo real del servidor. */
                        return resp.text().then(function (txt) {
                            var datos = null;
                            try { datos = JSON.parse(txt); } catch (e) { datos = null; }
                            if (!datos) { throw new Error('HTTP ' + resp.status); }
                            return datos;
                        });
                    })
                    .then(function (data) {
                        if (!data) { throw new Error('Respuesta vacia'); }

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminaci&oacute;n completada',
                                text: data.mensaje || '',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                            }).then(function () { window.location.reload(); });
                            return;
                        }

                        if (data.eliminados && data.eliminados.length) {
                            /* Parcial: algunos se borraron y otros no */
                            var fallidos = (data.fallidos || []).map(function (f) {
                                return '<li><b>' + escapar(f.archivo) + '</b> — ' + escapar(f.mensaje) + '</li>';
                            }).join('');
                            Swal.fire({
                                icon: 'warning',
                                title: 'Eliminaci&oacute;n parcial',
                                html: escapar(data.mensaje || '') +
                                      (fallidos ? '<ul style="max-height:10rem;overflow:auto;text-align:left;padding-left:1.25rem;margin:.5rem 0">' + fallidos + '</ul>' : ''),
                                confirmButtonText: '<i class="fas fa-rotate me-2"></i>Actualizar listado'
                            }).then(function () { window.location.reload(); });
                            return;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'No se pudo eliminar',
                            text: (data && data.mensaje) ? data.mensaje : 'Error desconocido.',
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                    })
                    .catch(function (err) {
                        var esHttp = err && typeof err.message === 'string' && err.message.indexOf('HTTP ') === 0;
                        Swal.fire({
                            icon: 'error',
                            title: esHttp ? 'Error del servidor' : 'Error de comunicaci&oacute;n',
                            text: esHttp
                                ? 'El servidor respondi&oacute; ' + err.message.slice(5) + ' y no devolvi&oacute; JSON.'
                                : 'No se pudo contactar el servidor.',
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                    });
            });
        });

        refrescarSeleccion();
    }

    /* ==========================================
       CREAR CARPETA (solo rol Administrador)
       Boton de la barra de navegacion (fa-folder-plus, junto a Subir).
       ========================================== */
    var btnCrearCarpeta = document.getElementById('btnCrearCarpeta');

    if (btnCrearCarpeta) {
        btnCrearCarpeta.addEventListener('click', function () {
            var btn = this;
            if (btn.disabled) { return; }

            Swal.fire({
                title: 'Crear carpeta',
                html: '<small>Se crear&aacute; en <b>' +
                      escapar(document.body.getAttribute('data-carpeta') || '') +
                      (btn.dataset.ruta ? ' &rsaquo; ' + escapar(btn.dataset.ruta) : '') +
                      '</b></small>',
                input: 'text',
                inputPlaceholder: 'Nombre de la carpeta',
                inputAttributes: { autocapitalize: 'off', spellcheck: 'false', maxlength: '150' },
                showCancelButton: true,
                showCloseButton: true,
                confirmButtonText: '<i class="fas fa-folder-plus me-2"></i>Crear',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: true,
                didOpen: prepararCierreSwal,
                inputValidator: function (valor) {
                    valor = String(valor || '').trim();
                    if (!valor) { return 'Indique el nombre de la carpeta.'; }
                    if (/[\\\/:*?"<>|]/.test(valor)) {
                        return 'Caracteres no permitidos: \\ / : * ? " < > |';
                    }
                    if (valor === '.' || valor === '..') { return 'Nombre no valido.'; }
                    if (/\.$/.test(valor) || / $/.test(valor)) {
                        return 'El nombre no puede terminar en punto ni en espacio.';
                    }
                    return undefined;
                }
            }).then(function (r) {
                if (!r.isConfirmed) return;
                var nombre = String(r.value || '').trim();

                var cuerpo = new FormData();
                cuerpo.append('carpeta', btn.dataset.carpeta || document.body.getAttribute('data-carpeta') || '');
                cuerpo.append('ruta', btn.dataset.ruta || '');
                cuerpo.append('nombre', nombre);

                Swal.fire({
                    title: 'Creando carpeta&hellip;',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch('crear_carpeta.php', {
                    method: 'POST',
                    body: cuerpo,
                    credentials: 'same-origin'
                })
                    .then(function (resp) {
                        return resp.text().then(function (txt) {
                            var datos = null;
                            try { datos = JSON.parse(txt); } catch (e) { datos = null; }
                            if (!datos) { throw new Error('HTTP ' + resp.status); }
                            return datos;
                        });
                    })
                    .then(function (datos) {
                        if (!datos || !datos.success) {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo crear la carpeta',
                                text: (datos && datos.mensaje) ? datos.mensaje : 'Error desconocido.',
                                confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                            });
                            return;
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Carpeta creada',
                            text: datos.mensaje || nombre,
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                        }).then(function () { window.location.reload(); });
                    })
                    .catch(function (err) {
                        var esHttp = err && typeof err.message === 'string' && err.message.indexOf('HTTP ') === 0;
                        Swal.fire({
                            icon: 'error',
                            title: esHttp ? 'Error del servidor' : 'Error de comunicaci\u00f3n',
                            text: esHttp
                                ? 'El servidor respondi\u00f3 ' + err.message.slice(5) + ' y no devolvi\u00f3 JSON.'
                                : 'No se pudo contactar el servidor.',
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                    });
            });
        });
    }

    /* ==========================================
       VACIAR CARPETA (roles 1 Admin, 4 Super y 5 Soft)
       Borra todo el contenido de la carpeta abierta. El aviso no se cierra
       haciendo fuera de la ventana: por ser irreversible, solo los botones.
       ========================================== */
    var btnVaciar = document.getElementById('btnVaciarCarpeta');

    if (btnVaciar) {
        btnVaciar.addEventListener('click', function () {
            var btn = this;
            var carpeta = btn.dataset.carpeta;
            var ruta = (btn.dataset.ruta || '').replace(/^\/+|\/+$/g, '');
            var titulo = btn.dataset.titulo || carpeta;
            var total = parseInt(btn.dataset.total, 10) || 0;
            var totalCarpetas = parseInt(btn.dataset.carpetas, 10) || 0;
            var tamano = btn.dataset.tamano || '';
            var nombreCompleto = ruta ? titulo + ' \u203A ' + ruta.split('/').join(' \u203A ') : titulo;

            if (!total && !totalCarpetas) return;

            Swal.fire({
                icon: 'warning',
                title: 'Vaciar carpeta',
                html: 'Se eliminará <b>todo el contenido</b> de la carpeta <b>' + escapar(nombreCompleto) +
                      '</b>: <b>' + total + '</b> archivo' + (total === 1 ? '' : 's') +
                      (tamano ? ' (' + escapar(tamano) + ')' : '') +
                      ', <b>' + totalCarpetas + '</b> carpeta' + (totalCarpetas === 1 ? '' : 's') +
                      ' y todo su contenido anidado.<br>Esta acción no se puede deshacer.',
                showCancelButton: true,
                showCloseButton: true,
                confirmButtonText: '<i class="fas fa-trash-can me-2"></i>Sí, vaciar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                reverseButtons: true,
                focusCancel: true,
                allowOutsideClick: false,
                allowEscapeKey: true,
                customClass: { confirmButton: 'swal2-confirm btn-confirmar-eliminar' },
                didOpen: prepararCierreSwal
            }).then(function (r) {
                if (!r.isConfirmed) return;

                btn.disabled = true;

                var cuerpo = new FormData();
                cuerpo.append('carpeta', carpeta);
                cuerpo.append('ruta', ruta);

                Swal.fire({
                    title: 'Vaciando la carpeta...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch('vaciar_carpeta.php', {
                    method: 'POST',
                    body: cuerpo,
                    credentials: 'same-origin'
                })
                    .then(function (resp) {
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);
                        return resp.json();
                    })
                    .then(function (data) {
                        if (!data || !data.success) {
                            btn.disabled = false;
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo vaciar la carpeta',
                                text: (data && data.mensaje) ? data.mensaje : 'Error desconocido.',
                                allowOutsideClick: false,
                                confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                            });
                            return;
                        }

                        var borrados = (parseInt(data.archivos, 10) || 0) +
                                       (parseInt(data.directorios, 10) || 0);

                        Swal.fire({
                            icon: 'success',
                            title: 'Carpeta vaciada',
                            html: escapar(nombreCompleto) + ': <b>' + borrados + '</b> elemento' +
                                  (borrados === 1 ? '' : 's') + ' eliminado' +
                                  (borrados === 1 ? '' : 's') + '.',
                            allowOutsideClick: false,
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                        }).then(function () { window.location.reload(); });
                    })
                    .catch(function () {
                        btn.disabled = false;
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de comunicación',
                            text: 'No se pudo contactar el servidor.',
                            allowOutsideClick: false,
                            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
                        });
                    });
            });
        });
    }

    /* ==========================================
       VISOR DE ARCHIVOS (.txt, .dbf y .xml)
       Click en el nombre del archivo -> ventana modal con el contenido.
       Ventana con barra de titulo estilo Windows: icono del tipo de archivo,
       nombre y boton X a la derecha para cerrar.
       Ninguno de estos avisos se cierra pulsando fuera: el contenido puede
       ser largo y un clic accidental lo perderia.
       ========================================== */
    var VISOR_ICONOS = { txt: 'fa-file-lines', dbf: 'fa-database', xml: 'fa-file-code', csv: 'fa-file-csv', zip: 'fa-file-zipper', rar: 'fa-box-archive', memo: 'fa-sticky-note' };

    /* Tamano legible para las cabeceras de los modales */
    function bytesLegibles(b) {
        b = parseInt(b, 10) || 0;
        return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB'
            : b >= 1024 ? (b / 1024).toFixed(1).replace('.', ',') + ' KB'
            : b + ' B';
    }

    /* Titulo de la ventana: Archivo: <nombre> - <tipo de archivo> */
    function visorTitulo(data) {
        var tipo = data.etiquetaFormato || String(data.tipo || '').toUpperCase();
        return 'Archivo: ' + data.nombre + ' - ' + tipo;
    }

    function visorMeta(data, extra) {
        var partes = [
            '<span class="visor-meta-item"><i class="fas fa-hard-drive"></i>' + escapar(data.bytesTexto || '') + '</span>'
        ];
        (extra || []).forEach(function (item) { partes.push(item); });
        return '<div class="visor-meta">' + partes.join('') + '</div>';
    }

    function visorError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'No se pudo mostrar el archivo',
            text: mensaje || 'Error desconocido.',
            allowOutsideClick: false,
            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar'
        });
    }

    /* ==========================================
       VENTANA PROPIA (apilada sobre el modal abierto)
       SweetAlert2 solo admite un modal a la vez: si el visor del memo se
       abriera con Swal cerraria la tabla de la DBF. El memo vive en su
       propia capa fija, con el mismo aspecto de .visor-ventana, que se
       pinta encima sin tocar el Swal de debajo.
       ========================================== */
    function cerrarVentanaPropia() {
        var capa = document.querySelector('.visor-capa');
        if (capa && capa.parentNode) {
            capa.parentNode.removeChild(capa);
        }
    }

    function ventanaPropia(opciones) {
        cerrarVentanaPropia();

        var capa = document.createElement('div');
        capa.className = 'visor-capa';
        capa.innerHTML =
            '<div class="visor-ventana visor-ventana-propia" style="width:' +
                (opciones.ancho || 'min(900px, 92vw)') + '">' +
                '<div class="visor-caption" data-tipo="' + escapar(opciones.tipo || '') + '">' +
                    '<i class="fas ' + (VISOR_ICONOS[opciones.tipo] || 'fa-file') +
                        ' visor-caption-icono" aria-hidden="true"></i>' +
                    '<span class="visor-caption-titulo">' + escapar(opciones.titulo || '') + '</span>' +
                    '<button type="button" class="visor-caption-cerrar" aria-label="Cerrar ventana" title="Cerrar">' +
                        '<i class="fas fa-xmark" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="visor-cuerpo">' +
                    (opciones.meta || '') +
                    '<div class="visor-scroll">' + opciones.cuerpo + '</div>' +
                '</div>' +
                '<div class="visor-pie">' +
                    '<button type="button" class="visor-boton visor-boton-cerrar">' +
                        '<i class="fas fa-times" aria-hidden="true"></i>Cerrar</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(capa);

        function cerrar() { cerrarVentanaPropia(); }
        capa.querySelector('.visor-caption-cerrar').addEventListener('click', cerrar);
        capa.querySelector('.visor-boton-cerrar').addEventListener('click', cerrar);

        return capa;
    }

    /* Esc cierra SOLO la ventana apilada: sin cortar el evento, el Swal de
       la DBF se cerraria y se perderia la tabla. Se captura en la fase de
       captura para que el manejador de SweetAlert nunca llegue a verlo.
       En la barra de progreso, Esc CANCELA la extraccion (mismo efecto que
       la X de la ventana). */
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && document.querySelector('.visor-capa')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (document.querySelector('.visor-capa[data-sin-cerrar]')) {
                zipProgresoCancelar();
                return;
            }
            cerrarVentanaPropia();
        }
    }, true);

    /* Abre la ventana del visor: barra de titulo + cuerpo + boton Cerrar. */
    function abrirVisor(tipo, opciones) {
        var html =
            '<div class="visor-caption" data-tipo="' + tipo + '">' +
                '<i class="fas ' + (VISOR_ICONOS[tipo] || 'fa-file') + ' visor-caption-icono" aria-hidden="true"></i>' +
                '<span class="visor-caption-titulo">' + escapar(opciones.titulo || '') + '</span>' +
                '<button type="button" class="visor-caption-cerrar" aria-label="Cerrar ventana" title="Cerrar">' +
                    '<i class="fas fa-xmark" aria-hidden="true"></i>' +
                '</button>' +
            '</div>' +
            '<div class="visor-cuerpo">' +
                opciones.meta +
                '<div class="visor-scroll">' + opciones.cuerpo + '</div>' +
            '</div>';

        Swal.fire({
            html: html,
            width: opciones.ancho || 'min(1000px, 94vw)',
            customClass: { popup: 'visor-ventana' },
            allowOutsideClick: false,
            allowEscapeKey: true,
            confirmButtonText: '<i class="fas fa-times me-2"></i>Cerrar',
            didOpen: function () {
                var cerrar = document.querySelector('.swal2-popup.visor-ventana .visor-caption-cerrar');
                if (cerrar) cerrar.addEventListener('click', function () { Swal.close(); });
            },
            didClose: function () {
                // Si la tabla se cierra, no debe quedar una ventana apilada huerfana.
                cerrarVentanaPropia();
                // Ventana de un contenido abierto desde el ZIP: se vuelve al ZIP.
                zipAlCerrar();
            }
        });
    }

    function visorTexto(data) {
        var nota = data.truncado
            ? '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
              'Se muestran los primeros 400 KB: el archivo es mas largo.</div>'
            : '';

        abrirVisor('txt', {
            titulo: visorTitulo(data),
            meta: visorMeta(data, [
                    '<span class="visor-meta-item"><i class="fas fa-align-left"></i>' +
                    data.lineas + ' l&iacute;nea' + (data.lineas === 1 ? '' : 's') + '</span>'
                ]),
            cuerpo: '<pre class="visor-txt">' + escapar(data.contenido) + '</pre>' + nota,
            ancho: 'min(1000px, 94vw)'
        });
    }

    /* Colorea etiquetas, atributos y comentarios. El texto llega escapado, asi
       que se protegen comentarios y declaraciones con marcadores para que la
       regla de atributos no pinte dentro de los span ya insertados. */
    function resaltarXml(escapado) {
        var trozos = [];
        function guardar(html) { trozos.push(html); return '\u0000' + (trozos.length - 1) + '\u0000'; }

        var h = String(escapado == null ? '' : escapado);
        h = h.replace(/(&lt;!--[\s\S]*?--&gt;)/g, function (m) { return guardar('<span class="xml-com">' + m + '</span>'); });
        h = h.replace(/(&lt;!\[CDATA\[[\s\S]*?\]\]&gt;)/g, function (m) { return guardar('<span class="xml-com">' + m + '</span>'); });
        h = h.replace(/(&lt;\?[\s\S]*?\?&gt;)/g, function (m) { return guardar('<span class="xml-dec">' + m + '</span>'); });
        h = h.replace(/([A-Za-z_][\w.:-]*)\s*=\s*("[^"]*"|'[^']*'|&quot;[\s\S]*?&quot;)/g,
            '<span class="xml-attr">$1</span>=<span class="xml-val">$2</span>');
        h = h.replace(/(&lt;\/?)([A-Za-z_][\w.:-]*)/g, '$1<span class="xml-tag">$2</span>');
        h = h.replace(/\u0000(\d+)\u0000/g, function (m, i) { return trozos[parseInt(i, 10)] || m; });
        return h;
    }

    function visorXml(data) {
        var extras = [];
        if (data.raiz) {
            extras.push('<span class="visor-meta-item"><i class="fas fa-sitemap"></i>' + escapar(data.raiz) + '</span>');
        }
        if (data.nodos) {
            extras.push('<span class="visor-meta-item"><i class="fas fa-cubes-stacked"></i>' +
                        data.nodos + ' nodo' + (data.nodos === 1 ? '' : 's') + '</span>');
        }

        var cuerpo = data.formateado
            ? '<pre class="visor-xml">' + resaltarXml(escapar(data.contenido)) + '</pre>'
            : '<pre class="visor-txt">' + escapar(data.contenido) + '</pre>';

        if (data.error) {
            cuerpo += '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
                      escapar(data.error) + '</div>';
        } else if (data.truncado) {
            cuerpo += '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
                      'Se muestran solo los primeros 400 KB del archivo.</div>';
        }

        abrirVisor('xml', {
            titulo: visorTitulo(data),
            meta: visorMeta(data, extras),
            cuerpo: cuerpo,
            ancho: 'min(1000px, 94vw)'
        });
    }

    function visorTabla(data) {
        var campos = data.campos || [];
        var filas = data.filas || [];
        var cuerpo = '';

        // Cabeceras SIEMPRE desde la definicion de campos: aunque el archivo
        // venga sin registros se pinta la tabla con los nombres de los campos
        // (si no, el thead salia "undefined" por construirse solo en la rama
        // con filas).
        var cabeceras = '<tr><th class="visor-nro">#</th>';
        campos.forEach(function (campo) {
            var tipo = { C: 'Texto', N: 'Num&eacute;rico', F: 'Decimal', D: 'Fecha', L: 'L&oacute;gico' }[campo.tipo] || campo.tipo;
            cabeceras += '<th title="' + escapar(campo.nombre) + ' &middot; ' + tipo +
                         ' &middot; ' + (parseInt(campo.largo, 10) || 0) + ' posiciones">' +
                         escapar(campo.nombre) +
                         '<span class="visor-tipo">' + tipo +
                         ' &middot; ' + (parseInt(campo.largo, 10) || 0) +
                         (parseInt(campo.decimales, 10) ? ',' + campo.decimales : '') +
                         '</span></th>';
        });
        cabeceras += '</tr>';

        filas.forEach(function (fila, indice) {
            cuerpo += '<tr><td class="visor-nro">' + (indice + 1) + '</td>';
            campos.forEach(function (campo) {
                var valor = fila[campo.nombre];
                var memo = valor === '(memo)' && fila.__reg !== undefined && fila.__reg !== null;
                if (memo) {
                    cuerpo += '<td class="visor-celda-memo">' +
                        '<button type="button" class="visor-memo" ' +
                        'data-carpeta="' + escapar(data.carpeta) + '" ' +
                        'data-archivo="' + escapar(data.nombre) + '" ' +
                        'data-reg="' + (parseInt(fila.__reg, 10) || 0) + '" ' +
                        'data-campo="' + escapar(campo.nombre) + '" ' +
                        'title="Ver el contenido de este memo">' +
                        '<i class="fas fa-sticky-note"></i>ver memo</button></td>';
                } else {
                    cuerpo += '<td>' + escapar(valor == null ? '' : valor) + '</td>';
                }
            });
            cuerpo += '</tr>';
        });

        var extras = [];
        if (data.formato) {
            extras.push('<span class="visor-meta-item" title="' + escapar(data.formato) +
                        ' &middot; generador ' + escapar(data.generador || 'desconocido') + '">' +
                        '<i class="fas fa-database"></i>' + escapar(data.formato) +
                        ' (' + escapar(data.formatoHex || '') + ')</span>');
        }
        extras.push('<span class="visor-meta-item"><i class="fas fa-table"></i>' +
            (parseInt(data.totalRegistros, 10) || 0) + ' registro' +
            ((parseInt(data.totalRegistros, 10) || 0) === 1 ? '' : 's') + '</span>');
        extras.push('<span class="visor-meta-item"><i class="fas fa-columns"></i>' +
            campos.length + ' campos</span>');
        if (data.delimitadorTexto) {
            extras.push('<span class="visor-meta-item"><i class="fas fa-comma"></i>separado por ' +
                        escapar(data.delimitadorTexto) + ' (' + escapar(data.delimitador) + ')</span>');
        }
        if (parseInt(data.preambulo, 10)) {
            extras.push('<span class="visor-meta-item" title="Lineas de titulo del informe; se muestran en la tabla">' +
                        '<i class="fas fa-file-lines"></i>' +
                        'cabecera en la l&iacute;nea ' + (parseInt(data.preambulo, 10) + 1) + '</span>');
        }
        if (data.fecha) {
            extras.push('<span class="visor-meta-item"><i class="fas fa-calendar-day"></i>' +
                        escapar(data.fecha) + '</span>');
        }
        if (parseInt(data.eliminados, 10)) {
            extras.push('<span class="visor-meta-item"><i class="fas fa-ban"></i>' +
                        data.eliminados + ' borrado' + (data.eliminados === 1 ? '' : 's') + '</span>');
        }

        var nota = data.truncado
            ? '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
              'Se muestran solo los primeros ' + filas.length + ' registros.</div>'
            : '';

        if (data.aviso) {
            nota = '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
                   escapar(data.aviso) + '</div>' + nota;
        }

        abrirVisor(data.tipo === 'csv' ? 'csv' : 'dbf', {
            titulo: visorTitulo(data),
            meta: visorMeta(data, extras),
            cuerpo: '<div class="visor-tabla"><table class="visor-tabla-tabla">' +
                    '<thead>' + cabeceras + '</thead>' +
                    '<tbody>' + cuerpo + '</tbody></table></div>' + nota,
            ancho: 'min(1100px, 96vw)'
        });
    }

    /* =========================================================
       ZIP: explorador interno estilo Winrar.
       Navegacion por carpetas (raiz, atras, ruta clicable),
       previsualizacion de lo que se puede ver, descarga de una
       entrada y extraccion a la carpeta elegida.
       ========================================================== */
    var zipEstado = null;

    /* Carpeta del ZIP en la que estamos ("" = raiz). */
    function zipRuta() { return (zipEstado && zipEstado.ruta) || ''; }

    /* Etiqueta corta del formato abierto: "ZIP" o "RAR". */
    function zipTipoTxt() {
        return (zipEstado && zipEstado.datos && zipEstado.datos.tipo === 'rar') ? 'RAR' : 'ZIP';
    }

    /* Endpoint de extraccion/descarga segun el formato abierto. */
    function zipUrlAccion() {
        return (zipEstado && zipEstado.datos && zipEstado.datos.tipo === 'rar') ? 'extraer_rar.php' : 'extraer_zip.php';
    }

    /* Entradas visibles en la carpeta actual. Si el ZIP no declara
       entradas de directorio, las carpetas se deducen de las rutas. */
    function zipVisibles() {
        var ruta = zipRuta();
        var todas = (zipEstado && zipEstado.datos.entradas) || [];
        var prefijo = ruta === '' ? '' : ruta + '/';
        var vistos = {};
        var lista = [];

        function meter(item) {
            var clave = (item.esDir ? 'd' : 'a') + '|' + item.ruta + '|' + item.nombre;
            if (vistos[clave]) { return; }
            vistos[clave] = true;
            lista.push(item);
        }

        todas.forEach(function (entrada) {
            if (entrada.ruta === ruta) { meter(entrada); return; }

            if (ruta !== '' && entrada.ruta.indexOf(prefijo) === 0) {
                var tope = entrada.ruta.substring(prefijo.length).split('/')[0];
                meter({ esDir: true, nombre: tope, ruta: ruta, indice: -1 });
                return;
            }

            if (ruta === '' && entrada.ruta !== '') {
                meter({ esDir: true, nombre: entrada.ruta.split('/')[0], ruta: '', indice: -1 });
            }
        });

        lista.sort(function (a, b) {
            if (a.esDir !== b.esDir) { return a.esDir ? -1 : 1; }
            return String(a.nombre).localeCompare(String(b.nombre), 'es');
        });

        return lista;
    }

    /* Extension de una entrada del indice (la trae el servidor; si no,
       se deduce del nombre). Sin punto -> "". */
    function zipExtensionDe(entrada) {
        if (entrada.esDir) { return ''; }
        if (entrada.extension) { return String(entrada.extension).toLowerCase(); }
        var nombre = String(entrada.nombre || '');
        var punto = nombre.lastIndexOf('.');
        if (punto <= 0 || punto === nombre.length - 1) { return ''; }
        return nombre.substring(punto + 1).toLowerCase();
    }

    /* Comparadores de orden del indice (mismo mecanismo que la tabla
       de la carpeta principal: clic en la cabecera alterna asc/desc). */
    var ZIP_ORDEN = {
        nombre: function (a, b) {
            return String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es', { sensitivity: 'base', numeric: true });
        },
        tipo: function (a, b) {
            if (!!a.esDir !== !!b.esDir) { return a.esDir ? -1 : 1; }  // carpetas primero
            return zipExtensionDe(a).localeCompare(zipExtensionDe(b), 'es', { sensitivity: 'base' });
        },
        bytes: function (a, b) { return (a.bytes || 0) - (b.bytes || 0); },
        comprimido: function (a, b) { return (a.comprimido || 0) - (b.comprimido || 0); },
        ahorro: function (a, b) { return (a.ahorro || 0) - (b.ahorro || 0); },
        fecha: function (a, b) { return (a.fecha_ts || 0) - (b.fecha_ts || 0); }
    };

    /* Aplica el orden elegido; sin campo activo deja el orden por
       defecto (carpetas primero y alfab\u00e9tico). */
    function zipOrdenar(lista) {
        if (!zipEstado || !zipEstado.orden || !zipEstado.orden.campo) { return lista; }
        var cmp = ZIP_ORDEN[zipEstado.orden.campo];
        if (!cmp) { return lista; }
        var sentido = zipEstado.orden.dir === 'desc' ? -1 : 1;
        return lista.slice().sort(function (a, b) { return sentido * cmp(a, b); });
    }

    /* Cabecera de columna ordenable del visor (icono seg\u00fan el estado). */
    function zipThOrden(campo, texto) {
        var act = !!(zipEstado && zipEstado.orden && zipEstado.orden.campo === campo);
        var icono = !act ? 'fa-sort'
            : (zipEstado.orden.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
        return '<button type="button" class="visor-zip-sort' + (act ? ' activa' : '') +
            '" data-orden="' + campo + '" title="Ordenar por ' + escapar(texto) + '">' +
            '<span>' + escapar(texto) + '</span>' +
            '<i class="fas ' + icono + ' visor-zip-sort-icono"></i></button>';
    }

    /* Ruta visible en la barra: raiz + niveles clicable (estilo Winrar). */
    function zipBarraRuta() {
        var ruta = zipRuta();
        var partes = ruta === '' ? [] : ruta.split('/');
        var cadena = (zipEstado && zipEstado.cadena) || [];
        var html = '';

        /* Raiz total: el archivo comprimido original. Si hay anidamiento,
           la barra lista la cadena de contenedores abiertos (raiz / nivel1
           / ...) y debajo, ya dentro del ultimo, las carpetas internas. */
        if (cadena.length) {
            var raiz = basenameDe(zipEstado.archivoRaiz || zipEstado.datos.nombre) || zipTipoTxt();
            html += '<button type="button" class="visor-zip-nivel visor-zip-nivel-cadena' +
                (ruta === '' && cadena.length === 0 ? ' activo' : '') +
                '" data-nivel="0" title="Ra\u00edz del archivo comprimido">' +
                '<i class="fas fa-box-archive"></i>' + escapar(raiz) + '</button>';

            cadena.forEach(function (seg, i) {
                html += '<span class="visor-zip-sep">/</span>' +
                    '<button type="button" class="visor-zip-nivel visor-zip-nivel-cadena' +
                    (i === cadena.length - 1 && ruta === '' ? ' activo' : '') +
                    '" data-nivel="' + (i + 1) + '" ' +
                    'title="Ir a ' + escapar(seg.nombre) + '">' + escapar(seg.nombre) + '</button>';
            });

            var acumuladaCadena = '';
            partes.forEach(function (parte, i) {
                acumuladaCadena = acumuladaCadena === '' ? parte : acumuladaCadena + '/' + parte;
                html += '<span class="visor-zip-sep">/</span>' +
                    '<button type="button" class="visor-zip-nivel' +
                    (i === partes.length - 1 ? ' activo' : '') +
                    '" data-ruta="' + escapar(acumuladaCadena) + '" ' +
                    'title="Ir a ' + escapar(acumuladaCadena) + '">' + escapar(parte) + '</button>';
            });

            return html;
        }

        html = '<button type="button" class="visor-zip-nivel' + (ruta === '' ? ' activo' : '') +
            '" data-ruta="" title="Raiz del archivo comprimido">' +
            '<i class="fas fa-box-archive"></i>' + escapar(basenameDe(zipEstado.datos.nombre) || zipTipoTxt()) + '</button>';

        var acumulada = '';
        partes.forEach(function (parte, i) {
            acumulada = acumulada === '' ? parte : acumulada + '/' + parte;
            html += '<span class="visor-zip-sep">/</span>' +
                '<button type="button" class="visor-zip-nivel' +
                (i === partes.length - 1 ? ' activo' : '') +
                '" data-ruta="' + escapar(acumulada) + '" ' +
                'title="Ir a ' + escapar(acumulada) + '">' + escapar(parte) + '</button>';
        });

        return html;
    }

    function zipDestinoActual() {
        return (zipEstado && zipEstado.destino) || '';
    }

    function zipPintarResultado(clase, icono, html) {
        var res = document.querySelector('.visor-zip-resultado');
        if (!res) { return; }
        res.hidden = false;
        res.className = 'visor-zip-resultado ' + clase;
        res.innerHTML = '<i class="fas ' + icono + '"></i><span>' + html + '</span>';
    }

    function zipRestablecerResultado() {
        var res = document.querySelector('.visor-zip-resultado');
        if (res) { res.hidden = true; res.className = 'visor-zip-resultado'; res.innerHTML = ''; }
    }

    function pintarZip() {
        if (!zipEstado) { return; }

        var data  = zipEstado.datos;
        var ruta  = zipRuta();
        var anidado = !!(zipEstado.cadena && zipEstado.cadena.length);
        var lista = zipOrdenar(zipVisibles());
        var nDir  = 0;
        var nArch = 0;

        lista.forEach(function (item) { if (item.esDir) { nDir++; } else { nArch++; } });

        var extras = [];
        extras.push('<span class="visor-meta-item" title="Entradas totales del archivo comprimido">' +
            '<i class="fas fa-box-archive"></i>' +
            (parseInt(data.totalEntradas, 10) || 0) + ' entrada' +
            ((parseInt(data.totalEntradas, 10) || 0) === 1 ? '' : 's') + '</span>');
        if (parseInt(data.cifradas, 10) > 0) {
            extras.push('<span class="visor-meta-item visor-meta-cifrado" ' +
                'title="Entradas protegidas con contraseña (se marca cada una con * en la lista)">' +
                '<i class="fas fa-lock"></i>' +
                (parseInt(data.cifradas, 10) || 0) + ' con contrase\u00f1a</span>');
        }
        if (ruta === '') {
            if (parseInt(data.bytesContenidos, 10)) {
                extras.push('<span class="visor-meta-item" title="Tama\u00f1o total sin comprimir">' +
                    '<i class="fas fa-expand"></i>' + bytesLegibles(data.bytesContenidos) +
                    (parseInt(data.ahorro, 10) ? ' &middot; ' + data.ahorro + '% de ahorro' : '') +
                    '</span>');
            }
        } else {
            extras.push('<span class="visor-meta-item"><i class="fas fa-folder-open"></i>' +
                nArch + ' archivo' + (nArch === 1 ? '' : 's') + ' &middot; ' +
                nDir + ' carpeta' + (nDir === 1 ? '' : 's') + '</span>');
        }

        /* --- Barra de navegacion (raiz / niveles / atras) --- */
        var nav = '<div class="visor-zip-nav">' +
            '<button type="button" class="visor-zip-cmd visor-zip-home"' +
            (ruta === '' && !anidado ? ' disabled' : '') +
            ' title="Ra\u00edz del archivo comprimido" aria-label="Ir a la ra\u00edz del ' + zipTipoTxt() + '">' +
            '<i class="fas fa-house" aria-hidden="true"></i>Ra\u00edz</button>' +
            '<button type="button" class="visor-zip-cmd visor-zip-atras"' +
            (ruta === '' && !anidado ? ' disabled' : '') +
            ' title="Volver a la carpeta anterior"><i class="fas fa-arrow-left"></i>Atr\u00e1s</button>' +
            '<button type="button" class="visor-zip-cmd visor-zip-subir"' +
            (ruta === '' && !anidado ? ' disabled' : '') +
            ' title="Subir un nivel"><i class="fas fa-arrow-up"></i>Subir</button>' +
            '<div class="visor-zip-ruta-nav">' + zipBarraRuta() + '</div>' +
            '<span class="visor-zip-ruta-txt" title="' + escapar(ruta) + '">' +
            (ruta === '' ? 'ra\u00edz' : escapar(ruta) + '/') + '</span>' +
            '</div>';

        /* --- Barra de acciones --- */
        var destinos = data.destinos || [];
        var selector = '';
        if (data.puedeExtraer) {
            selector = '<label class="visor-zip-destino-lbl" for="visorZipDestino">' +
                '<i class="fas fa-arrow-right-to-bracket"></i>Extraer en</label>' +
                '<select class="visor-zip-destino" id="visorZipDestino">' +
                '<option value=""' + (zipDestinoActual() === '' ? ' selected' : '') +
                '>MISMA CARPETA</option>';
            destinos.forEach(function (dest) {
                selector += '<option value="' + escapar(dest.clave) + '"' +
                    (dest.clave === zipDestinoActual() ? ' selected' : '') + '>' +
                    escapar(dest.titulo) + '</option>';
            });
            selector += '</select>';
        }

        var acciones = '';
        if (data.puedeExtraer) {
            acciones += '<button type="button" class="visor-zip-accion visor-zip-todo" ' +
                'title="Descomprimir todo el ' + zipTipoTxt() + ' en la carpeta elegida">' +
                '<i class="fas fa-folder-open"></i>Extraer todo</button>';
        }
        if (anidado) {
            /* Contenedor anidado: la descarga viaja por POST con la cadena de
               entradas (no hay URL directa al archivo). */
            if (data.puedeDescargar) {
                acciones += '<button type="button" class="visor-zip-accion visor-zip-bajar visor-zip-bajar-contenedor" ' +
                    'title="Descargar el ' + zipTipoTxt() + ' de este nivel">' +
                    '<i class="fas fa-download"></i>Descargar ' + zipTipoTxt() + '</button>';
            }
        } else if (data.descarga && data.puedeDescargarTodo !== false) {
            acciones += '<a class="visor-zip-accion visor-zip-bajar" download ' +
                'href="' + escapar(data.descarga) + '" title="Descargar el ' + zipTipoTxt() + ' completo">' +
                '<i class="fas fa-download"></i>Descargar ' + zipTipoTxt() + '</a>';
        }

        var barra = '<div class="visor-zip-barra">' +
            '<span class="visor-zip-barra-txt"><i class="fas fa-box-open"></i>' +
            (ruta === '' ? 'Contenido del archivo comprimido' : 'Contenido de ' + escapar(ruta) + '/') +
            '</span>' +
            '<span class="visor-zip-acciones">' + selector + acciones + '</span>' +
            '</div>' +
            '<div class="visor-zip-resultado" hidden></div>';

        /* --- Tabla de la carpeta actual --- */
        var cuerpo = '';
        if (!lista.length) {
            cuerpo += '<div class="visor-vacio"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>' +
                'Esta carpeta est\u00e1 vac\u00eda.</div>';
        } else {
            var filas = '';

            lista.forEach(function (entrada, i) {
                var esDir = !!entrada.esDir;
                var enRuta = entrada.ruta === '' ? '' : entrada.ruta + '/';

                filas += '<tr class="' + (esDir ? 'visor-zip-dir' : '') + '">' +
                    '<td class="visor-nro">' + (i + 1) + '</td>' +
                    '<td class="visor-zip-nombre">' +
                        '<i class="fas ' + (esDir ? 'fa-folder' : 'fa-file-lines') + '"></i>';

                if (esDir) {
                    filas += '<button type="button" class="visor-zip-entrar" ' +
                        'data-ruta="' + escapar((ruta === '' ? '' : ruta + '/') + entrada.nombre) + '" ' +
                        'title="Abrir ' + escapar(entrada.nombre) + '">' +
                        '<span class="visor-zip-archivo">' + escapar(entrada.nombre) + '</span>' +
                        '<i class="fas fa-chevron-right visor-zip-chevron"></i></button>';
                } else {
                    filas += '<span class="visor-zip-archivo" title="' +
                        escapar(enRuta + entrada.nombre) +
                        (entrada.cifrada ? ' (protegido con contraseña)' : '') + '">' +
                        escapar(entrada.nombre) + '</span>' +
                        (entrada.cifrada
                            ? '<span class="visor-zip-cifrado" title="Protegido con contraseña" ' +
                              'aria-hidden="true">*</span>'
                            : '');
                }

                var extZip = zipExtensionDe(entrada);

                filas += '</td>' +
                    '<td class="text-end visor-zip-tipo' +
                    (extZip ? '' : ' carpeta-sin-datos') + '">' +
                    (extZip ? escapar(extZip) : '&mdash;') + '</td>' +
                    '<td class="text-end">' + (esDir ? '&mdash;' : bytesLegibles(entrada.bytes)) + '</td>' +
                    '<td class="text-end">' + (esDir ? '&mdash;' : bytesLegibles(entrada.comprimido)) + '</td>' +
                    '<td class="text-end">' + (esDir ? '&mdash;' : entrada.ahorro + '%') + '</td>' +
                    '<td class="text-end">' + (escapar(entrada.fecha || '') || '&mdash;') + '</td>' +
                    '<td class="text-end visor-zip-celda-acciones">';

                if (esDir) {
                    filas += '<span class="visor-zip-sin-accion" title="Carpeta">' +
                            '<i class="fas fa-folder"></i></span>';
                } else {
                    var base = {
                        'data-carpeta': data.carpeta,
                        'data-archivo': data.nombre,
                        'data-indice': (parseInt(entrada.indice, 10) || 0),
                        'data-nombre': entrada.nombre
                    };
                    function attr(clave) {
                        return clave + '="' + escapar(String(base[clave])) + '" ';
                    }

                    if (entrada.previsualizable) {
                        filas += '<button type="button" class="visor-zip-btn visor-zip-ver" ' +
                            attr('data-carpeta') + attr('data-archivo') + attr('data-indice') +
                            attr('data-nombre') +
                            'title="Ver ' + escapar(entrada.nombre) + '" aria-label="Ver ' +
                            escapar(entrada.nombre) + '"><i class="fas fa-eye"></i></button>';
                    }

                    /* Un .zip/.rar del indice se puede abrir DENTRO del visor
                       (vista anidada), sin limite de profundidad. */
                    var extAbrir = zipExtensionDe(entrada);
                    var esComprimido = (extAbrir === 'zip' || extAbrir === 'rar');
                    var puedeAbrir = esComprimido;
                    if (puedeAbrir) {
                        filas += '<button type="button" class="visor-zip-btn visor-zip-abrir" ' +
                            attr('data-indice') + attr('data-nombre') +
                            'title="Abrir ' + escapar(entrada.nombre) + ' dentro del visor" aria-label="Abrir ' +
                            escapar(entrada.nombre) + '"><i class="fas fa-box-archive"></i></button>';
                    }

                    if (data.puedeDescargar) {
                        filas += '<button type="button" class="visor-zip-btn visor-zip-bajar-uno" ' +
                            attr('data-carpeta') + attr('data-archivo') + attr('data-indice') +
                            attr('data-nombre') +
                            'title="Descargar ' + escapar(entrada.nombre) + '" aria-label="Descargar ' +
                            escapar(entrada.nombre) + '"><i class="fas fa-file-arrow-down"></i></button>';
                    }
                    if (data.puedeExtraer) {
                        filas += '<button type="button" class="visor-zip-btn visor-zip-extraer-uno" ' +
                            attr('data-carpeta') + attr('data-archivo') + attr('data-indice') +
                            attr('data-nombre') +
                            'title="Extraer ' + escapar(entrada.nombre) +
                            ' a la carpeta elegida" aria-label="Extraer ' +
                            escapar(entrada.nombre) + '"><i class="fas fa-file-export"></i></button>';
                    }
                    if (!entrada.previsualizable && !puedeAbrir &&
                        !data.puedeDescargar && !data.puedeExtraer) {
                        filas += '<span class="visor-zip-sin-accion" title="Su rol no permite estas acciones">' +
                                '<i class="fas fa-lock"></i></span>';
                    }
                }

                filas += '</td></tr>';
            });

            cuerpo += '<div class="visor-tabla"><table class="visor-tabla-tabla visor-zip-tabla">' +
                '<thead><tr>' +
                    '<th class="visor-nro">#</th>' +
                    '<th>' + zipThOrden('nombre', 'Archivo') + '</th>' +
                    '<th class="text-end">' + zipThOrden('tipo', 'Tipo') + '</th>' +
                    '<th class="text-end">' + zipThOrden('bytes', 'Tama\u00f1o') + '</th>' +
                    '<th class="text-end">' + zipThOrden('comprimido', 'Comprimido') + '</th>' +
                    '<th class="text-end">' + zipThOrden('ahorro', 'Ahorro') + '</th>' +
                    '<th class="text-end">' + zipThOrden('fecha', 'Modificado') + '</th>' +
                    '<th class="text-end">Acci&oacute;n</th>' +
                '</tr></thead><tbody>' + filas + '</tbody></table></div>';

            if (data.truncado && ruta === '') {
                cuerpo += '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
                    'Se devuelven solo las primeras ' +
                    (parseInt(data.totalEntradas, 10) || lista.length) + ' entradas del ' + zipTipoTxt() + '.</div>';
            }
        }

        var notaAnidado = '';
        if (anidado) {
            notaAnidado = '<div class="visor-nota"><i class="fas fa-layer-group me-1"></i>' +
                'Vista anidada de <strong>' +
                escapar(basenameDe(zipEstado.archivoRaiz || data.nombre)) +
                '</strong>: descarga y extracci\u00f3n act\u00fan sobre este nivel.</div>';
        }

        var htmlCuerpo = nav + barra + notaAnidado + cuerpo;
        var popup = document.querySelector('.swal2-popup.visor-ventana');
        // Ojo: tras cerrar otra ventana, SweetAlert puede dejar el popup en el
        // DOM (oculto). getClientRects() sale vacio con display:none, asi que
        // solo se reutiliza si la ventana esta realmente visible.
        var contenedor = (popup && popup.getClientRects().length > 0)
            ? popup.querySelector('.visor-cuerpo')
            : null;

        if (contenedor) {
            // Ya hay una ventana abierta: solo se repinta el interior (sin
            // cerrar y reabrir, para que la navegacion sea inmediata). El
            // titulo de la cabecera hay que refrescarlo a mano: al cambiar
            // de nivel de la cadena (raiz <-> anidado) cambia el archivo.
            var captionTitulo = popup.querySelector('.visor-caption-titulo');
            if (captionTitulo) { captionTitulo.textContent = visorTitulo(data); }
            contenedor.innerHTML = visorMeta(data, extras) +
                '<div class="visor-scroll">' + htmlCuerpo + '</div>';
        } else {
            abrirVisor(data.tipo === 'rar' ? 'rar' : 'zip', {
                titulo: visorTitulo(data),
                meta: visorMeta(data, extras),
                cuerpo: htmlCuerpo,
                ancho: 'min(1100px, 96vw)'
            });
        }
    }

    function visorZip(data, cadena) {
        var previo = zipEstado;
        zipEstado = {
            datos: data,
            ruta: '',
            destino: zipDestinoDe(data),
            mostrandoContenido: false,
            saltando: false,
            password: (previo && previo.password) || null,
            orden: { campo: null, dir: 'asc' },
            /* Cadena de anidamiento: indices desde el comprimido de la raiz
               hasta el que se esta viendo. passwords[k] guarda la clave del
               comprimido del nivel k y archivoRaiz el nombre del original. */
            cadena: cadena || [],
            passwords: (previo && previo.passwords) || [],
            archivoRaiz: (previo && previo.archivoRaiz) || data.nombre || '',
            cache: (previo && previo.cache) || {}
        };
        zipEstado.cache[zipCadenaClave(zipEstado.cadena)] = data;
        pintarZip();
    }

    /* Clave de cache de un indice: los indices de la cadena unidos. */
    function zipCadenaClave(cadena) {
        return (cadena || []).map(function (seg) { return String(seg.indice); }).join(',');
    }

    /* Pide al servidor el indice del comprimido al final de la cadena
       (cadena vacia = el archivo raiz). passwords[] lleva una clave por
       nivel; password es el respaldo legado para la raiz. */
    function zipPedirIndice(cadena) {
        var cuerpo = new FormData();
        cuerpo.append('carpeta', (zipEstado.datos.carpeta) || '');
        cuerpo.append('archivo', zipEstado.archivoRaiz || zipEstado.datos.nombre || '');
        cuerpo.append('ruta', (zipEstado.datos.ruta) || '');

        if (cadena && cadena.length) {
            cadena.forEach(function (seg) {
                cuerpo.append('entradas[]', String(seg.indice));
            });
            var ps = zipEstado.passwords || [];
            var total = Math.max(ps.length, cadena.length + 1);
            for (var i = 0; i < total; i++) {
                cuerpo.append('passwords[]', ps[i] || '');
            }
        }
        if (zipEstado.password) { cuerpo.append('password', zipEstado.password); }

        return fetch('ver_archivo.php', { method: 'POST', body: cuerpo, credentials: 'same-origin' })
            .then(zipRespuesta);
    }

    /* Compone archivo (raiz) + entradas[] + passwords[] en un FormData a
       partir de la cadena actual. Objetivo opcional {indice, nombre}: la
       entrada final a previsualizar/descargar/extraer; null = el propio
       contenedor del ultimo nivel (descarga o extraccion completa). */
    function zipCuerpoCadena(cuerpo, cadena, objetivo) {
        cuerpo.append('archivo', zipEstado.archivoRaiz || zipEstado.datos.nombre || '');
        var completa = (cadena || []).slice();
        if (objetivo) {
            completa.push({ indice: objetivo.indice, nombre: objetivo.nombre || '' });
        }
        completa.forEach(function (seg) {
            cuerpo.append('entradas[]', String(seg.indice));
        });
        var ps = zipEstado.passwords || [];
        var total = Math.max(ps.length, completa.length + 1);
        for (var i = 0; i < total; i++) {
            cuerpo.append('passwords[]', ps[i] || '');
        }
    }

    /* Ejecuta una operacion encadenada y, si el servidor pide clave para un
       nivel (err.nivel), la pregunta y la guarda en passwords[nivel] antes
       de reintentar. Maximo 3 intentos por operacion. */
    function zipEjecutarCadena(preparar) {
        var intentos = 0;

        function paso() {
            return preparar().catch(function (err) {
                if (!err || !err.password || intentos >= 3) { throw err; }

                intentos++;
                var nivel = (typeof err.nivel === 'number')
                    ? err.nivel
                    : ((zipEstado && zipEstado.cadena) ? zipEstado.cadena.length : 0);
                var motivo = err.passwordIncorrecta
                    ? 'La contrase\u00f1a no es correcta.'
                    : (err.message || 'Esta entrada est\u00e1 protegida con contrase\u00f1a.');

                return zipPedirPassword(motivo).then(function (nueva) {
                    if (nueva === null) {
                        var cancelado = new Error('Operaci\u00f3n cancelada: no se introdujo la contrase\u00f1a.');
                        cancelado.cancelada = true;
                        throw cancelado;
                    }
                    if (zipEstado) {
                        zipEstado.passwords = zipEstado.passwords || [];
                        zipEstado.passwords[nivel] = nueva;
                        if (nivel === 0) { zipEstado.password = nueva; }
                    }
                    return paso();
                });
            });
        }

        return paso();
    }

    /* Navega a un nivel de la cadena (0 = indice del archivo raiz). Si el
       indice de ese nivel ya se visito, se repinta desde la cache. */
    function zipNavegarCadena(nivel) {
        if (!zipEstado) { return; }
        var cadena = (zipEstado.cadena || []).slice(0, Math.max(0, nivel));
        var clave = zipCadenaClave(cadena);
        var cacheado = zipEstado.cache[clave];

        if (cacheado) {
            visorZip(cacheado, cadena);
            return;
        }

        zipEjecutarCadena(function () {
            return zipPedirIndice(cadena);
        })
            .then(function (data) {
                data.bytesTexto = bytesLegibles(data.bytes);
                visorZip(data, cadena);
            })
            .catch(function (err) {
                if (err && err.cancelada) { zipCancelado(); return; }
                zipFallo('No se pudo abrir el nivel', (err && err.message) || 'No se pudo leer el ' + zipTipoTxt() + '.');
            });
    }

    /* Abre un .zip/.rar del indice actual como vista anidada: se anade su
       indice a la cadena y se pide el indice del comprimido resultante. */
    function zipAbrirAnidado(boton) {
        if (boton.disabled || !zipEstado) { return; }
        var restaurar = zipOcupar(boton);
        var nuevaCadena = (zipEstado.cadena || []).concat([{
            indice: parseInt(boton.dataset.indice, 10) || 0,
            nombre: boton.dataset.nombre || ''
        }]);
        var clave = zipCadenaClave(nuevaCadena);

        function mostrar(data) {
            restaurar();
            data.bytesTexto = bytesLegibles(data.bytes);
            zipEstado.cache[clave] = data;
            zipEstado.ruta = '';
            visorZip(data, nuevaCadena);
        }

        var cacheado = zipEstado.cache[clave];
        if (cacheado) { mostrar(cacheado); return; }

        zipEjecutarCadena(function () {
            return zipPedirIndice(nuevaCadena);
        })
            .then(mostrar)
            .catch(function (err) {
                restaurar();
                if (err && err.cancelada) { zipCancelado(); return; }
                zipFallo('No se pudo abrir el archivo',
                    (err && err.message) || 'No se pudo leer el ' + zipTipoTxt() + '.');
            });
    }

    /* Destino por defecto de una extraccion: MISMA CARPETA, la carpeta que
       contiene el archivo abierto en el visor (valor "" para el servidor;
       el dropdown puede cambiarlo a una carpeta del sistema). */
    function zipDestinoDe() {
        return '';
    }

    /* Se cierra una ventana del visor: o se vuelve al ZIP (si veniamos de
       ver un contenido) o se olvida el estado. */
    /* Pregunta al usuario si quiere descargar un archivo que el visor no
       soporta (xlsx, pdf, png...). Si el indice del ZIP/RAR esta abierto, la
       pregunta sustituye su ventana con las mismas flags de "abrir contenido":
       al cerrarse, zipAlCerrar devuelve la vista al indice automaticamente. */
    function preguntarDescarga(nombre, alConfirmar) {
        if (zipEstado) {
            zipEstado.mostrandoContenido = true;
            zipEstado.saltando = Date.now() + 700;
        }

        Swal.fire({
            icon: 'question',
            title: 'Archivo no compatible con el visor',
            html: '<strong>' + escapar(nombre) + '</strong> no se puede previsualizar.<br>' +
                  '\u00bfDesea descargarlo?',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-download me-2"></i>Descargar',
            cancelButtonText: '<i class="fas fa-xmark me-2"></i>Cancelar',
            customClass: { popup: 'visor-ventana visor-pregunta' },
            didClose: function () { zipAlCerrar(); }
        }).then(function (r) {
            if (r.isConfirmed && typeof alConfirmar === 'function') {
                alConfirmar();
            }
        });
    }

    function zipAlCerrar() {
        if (!zipEstado) { return; }
        // Cierre inmediato tras abrir contenido: es la ventana del ZIP siendo
        // sustituida por la del contenido (no el cierre del usuario).
        if (zipEstado.saltando && Date.now() < zipEstado.saltando) {
            zipEstado.saltando = 0;
            vigilarCierreRapido();
            return;
        }
        if (zipEstado.mostrandoContenido) {
            zipEstado.mostrandoContenido = false;
            volverAlZip(0);
            return;
        }
        zipEstado = null;
    }

    /* Red de seguridad: si ese cierre rapido no era la sustitucion (la
       ventana ha quedado sin nada visible), se vuelve al indice del ZIP. */
    function vigilarCierreRapido() {
        setTimeout(function () {
            if (!zipEstado) { return; }
            var popup = document.querySelector('.swal2-popup.visor-ventana');
            var visible = !!popup && popup.getClientRects().length > 0;
            if (!visible) { volverAlZip(0); }
        }, 900);
    }

    /* Vuelve a pintar el indice del ZIP cuando la ventana del contenido ya se
       ha ocultado del todo: si se pinta antes, el html caeria en el popup que
       se esta cerrando y la ventana quedaria en blanco. */
    function volverAlZip(reintentos) {
        if (!zipEstado) { return; }
        var popup = document.querySelector('.swal2-popup.visor-ventana');
        var visible = !!popup && popup.getClientRects().length > 0;
        if (!visible || reintentos >= 10) {
            pintarZip();
            return;
        }
        setTimeout(function () { volverAlZip(reintentos + 1); }, 60);
    }

    /* Ultimo tramo de la ruta interna del ZIP (el nombre del archivo). */
    function basenameDe(ruta) {
        var trozos = String(ruta || '').split('/');
        return trozos[trozos.length - 1] || String(ruta || '');
    }

    function zipDescargarBlob(blob, nombre) {
        var url = URL.createObjectURL(blob);
        var enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = nombre || 'archivo.zip';
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
        setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
    }

    function zipFallo(titulo, mensaje) {
        ventanaPropia({
            tipo: (zipEstado && zipEstado.datos && zipEstado.datos.tipo === 'rar') ? 'rar' : 'zip',
            titulo: titulo,
            meta: '',
            cuerpo: '<div class="visor-fallo"><i class="fas fa-triangle-exclamation"></i>' +
                    '<span>' + escapar(mensaje || 'No se pudo completar la operacion.') + '</span></div>',
            ancho: 'min(620px, 92vw)'
        });
    }

    function zipOcupar(boton) {
        var contenido = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        return function restaurar() {
            boton.disabled = false;
            boton.innerHTML = contenido;
        };
    }

    function zipLeerError(respuesta) {
        return respuesta.json().then(function (json) {
            var err = new Error((json && json.mensaje) || 'HTTP ' + respuesta.status);
            if (json && (json.requierePassword || json.passwordIncorrecta)) {
                err.password = true;
            }
            if (json && json.passwordIncorrecta) { err.passwordIncorrecta = true; }
            if (json && typeof json.nivel === 'number') { err.nivel = json.nivel; }
            throw err;
        }, function () {
            var err = new Error('HTTP ' + respuesta.status);
            throw err;
        });
    }

    /* Respuesta JSON de una operacion: solo tiene exito con success. */
    function zipRespuesta(r) {
        return r.json().then(function (json) {
            if (json && json.success) { return json; }
            var err = new Error((json && json.mensaje) || 'HTTP ' + r.status);
            if (json && (json.requierePassword || json.passwordIncorrecta)) {
                err.password = true;
            }
            if (json && json.passwordIncorrecta) { err.passwordIncorrecta = true; }
            if (json && typeof json.nivel === 'number') { err.nivel = json.nivel; }
            throw err;
        }, function () {
            throw new Error('HTTP ' + r.status);
        });
    }

    /* ============ Barra de progreso de "Extraer todo" (ZIP/RAR) ============
       El servidor emite NDJSON (lineas "inicio" / "progreso" / "fin") y se
       lee con fetch + reader en tiempo real. La ventana se apila como capa
       sobre el indice (que es un Swal): la X de la barra de titulo y Esc
       cancelan la extraccion (se corta el fetch y el servidor mata UnRAR y
       borra el temporal); el boton del pie se oculta para no cerrar sin
       cortar. */
    var zipProgresoCapa   = null;
    var zipProgresoAbort  = null;
    var zipProgresoCancelada = false;
    var zipProgresoArchivoNum = 0;

    function zipProgresoAbrir() {
        zipProgresoCerrar();

        var tipo = (zipEstado && zipEstado.datos && zipEstado.datos.tipo === 'rar') ? 'rar' : 'zip';
        var capa = ventanaPropia({
            tipo: tipo,
            titulo: 'Extrayendo ' + zipTipoTxt() + '\u2026',
            meta: '',
            cuerpo:
                '<div class="visor-progreso">' +
                    '<div class="visor-progreso-actual" id="visorProgresoActual" title="">' +
                        '<i class="fas fa-file-export visor-progreso-actual-icono" aria-hidden="true"></i>' +
                        '<span class="visor-progreso-actual-txt">Extrayendo: ' +
                            '<span class="visor-progreso-actual-nombre" id="visorProgresoActualNombre">Preparando\u2026</span>' +
                        '</span>' +
                    '</div>' +
                    '<div class="visor-progreso-archivo" id="visorProgresoArchivo" hidden></div>' +
                    '<div class="visor-progreso-barra visor-progreso-barra-archivo">' +
                        '<div class="visor-progreso-relleno" id="visorProgresoRellenoArchivo" style="width:0%"></div>' +
                    '</div>' +
                    '<div class="visor-progreso-separador" role="separator"></div>' +
                    '<div class="visor-progreso-total">' +
                        '<i class="fas fa-chart-simple" aria-hidden="true"></i>Progreso Total:' +
                    '</div>' +
                    '<div class="visor-progreso-barra">' +
                        '<div class="visor-progreso-relleno" id="visorProgresoRelleno" style="width:0%"></div>' +
                    '</div>' +
                    '<div class="visor-progreso-datos">' +
                        '<span class="visor-progreso-pct" id="visorProgresoPct">0 %</span>' +
                        '<span class="visor-progreso-detalle" id="visorProgresoDetalle"></span>' +
                    '</div>' +
                '</div>',
            ancho: 'min(620px, 92vw)'
        });

        capa.dataset.progreso = '1';
        capa.dataset.sinCerrar = '1';
        zipProgresoCapa      = capa;
        zipProgresoCancelada = false;
        zipProgresoArchivoNum = 0;

        // La X de la esquina superior derecha cancela la extraccion: se
        // reemplaza el nodo para quitar el listener de "cerrar" puro de
        // ventanaPropia, que solo quitaria la capa sin detener nada.
        var cerrarBtn = capa.querySelector('.visor-caption-cerrar');
        if (cerrarBtn) {
            var nuevaX = cerrarBtn.cloneNode(true);
            nuevaX.title = 'Cancelar la extracci\u00f3n (Esc)';
            nuevaX.setAttribute('aria-label', 'Cancelar la extracci\u00f3n');
            cerrarBtn.parentNode.replaceChild(nuevaX, cerrarBtn);
            nuevaX.addEventListener('click', zipProgresoCancelar);
        }

        // El boton del pie cerraria sin cancelar: se oculta.
        var pie = capa.querySelector('.visor-boton-cerrar');
        if (pie) { pie.style.display = 'none'; }

        return capa;
    }

    function zipProgresoCerrar() {
        if (zipProgresoCapa && zipProgresoCapa.parentNode) {
            zipProgresoCapa.parentNode.removeChild(zipProgresoCapa);
        }
        zipProgresoCapa  = null;
        zipProgresoAbort = null;
    }

    /* Cancela la extraccion en curso (X o Esc): corta el fetch; el servidor
       detecta la desconexion en la siguiente emision, mata a UnRAR, borra el
       temporal y sale. El aviso se pinta en la ventana del indice. */
    function zipProgresoCancelar() {
        if (!zipProgresoCapa) { return; }
        zipProgresoCancelada = true;
        if (zipProgresoAbort) {
            try { zipProgresoAbort.abort(); } catch (e) { /* ya cortado */ }
        }
        zipProgresoCerrar();
        zipPintarResultado('visor-zip-error', 'fa-circle-info', 'Extracci\u00f3n cancelada.');
    }

    function zipProgresoCerrar() {
        if (zipProgresoCapa && zipProgresoCapa.parentNode) {
            zipProgresoCapa.parentNode.removeChild(zipProgresoCapa);
        }
        zipProgresoCapa = null;
    }

    function zipProgresoActualizar(d) {
        var relleno = document.getElementById('visorProgresoRelleno');
        if (!relleno) { return; }

        var pct = parseInt(d.pct, 10);
        if (isNaN(pct)) {
            var totalBytes = parseInt(d.bytesTotal, 10) || 0;
            var hechos     = parseInt(d.bytes, 10) || 0;
            pct = totalBytes > 0 ? Math.floor(hechos * 100 / totalBytes) : 0;
        }
        pct = Math.max(0, Math.min(99, pct));

        relleno.style.width = pct + '%';
        var pctTxt = document.getElementById('visorProgresoPct');
        if (pctTxt) { pctTxt.textContent = pct + ' %'; }

        var partes = [];
        if (d.bytes !== undefined && parseInt(d.bytesTotal, 10) > 0) {
            partes.push(bytesLegibles(parseInt(d.bytes, 10) || 0) +
                ' de ' + bytesLegibles(parseInt(d.bytesTotal, 10)));
        }
        if (parseInt(d.total, 10) > 0) {
            partes.push(parseInt(d.total, 10) + ' archivo' + (parseInt(d.total, 10) === 1 ? '' : 's'));
        }
        var detalle = document.getElementById('visorProgresoDetalle');
        if (detalle) { detalle.textContent = partes.join(' \u00b7 '); }

        // Fila "Archivo N de M (P %)" del ejemplo pedido.
        var archivoLinea = document.getElementById('visorProgresoArchivo');
        if (archivoLinea) {
            var numArchivo = parseInt(d.archivoNum, 10);
            var totalArchivos = parseInt(d.total, 10) || 0;
            if (!isNaN(numArchivo) && totalArchivos > 0) {
                archivoLinea.hidden = false;
                archivoLinea.textContent = 'Archivo ' + numArchivo + ' de ' + totalArchivos +
                    ' (' + Math.min(100, Math.round(numArchivo * 100 / totalArchivos)) + ' %)';
            } else {
                archivoLinea.hidden = true;
            }
        }

        // Primera barra: % del fichero actual. Se llena con el y vuelve a
        // 0 sola al empezar el siguiente (server manda archivoPct; si no
        // viene, basta con que cambie el contador para reiniciarla).
        var rellenoArchivo = document.getElementById('visorProgresoRellenoArchivo');
        if (rellenoArchivo) {
            var numAhora = parseInt(d.archivoNum, 10);
            var pctArchivo = parseInt(d.archivoPct, 10);
            if (isNaN(pctArchivo)) {
                pctArchivo = (isNaN(numAhora) || numAhora !== zipProgresoArchivoNum) ? 0 : null;
            }
            if (pctArchivo !== null) {
                rellenoArchivo.style.width = Math.max(0, Math.min(100, pctArchivo)) + '%';
            }
            if (!isNaN(numAhora)) {
                zipProgresoArchivoNum = numAhora;
            }
        }

        var actual = document.getElementById('visorProgresoActualNombre');
        if (actual && d.actual) {
            actual.textContent = d.actual;
            var cinta = document.getElementById('visorProgresoActual');
            if (cinta) { cinta.title = d.actual; }
        }
    }

    function zipProgresoCompleto() {
        var relleno = document.getElementById('visorProgresoRelleno');
        if (relleno) { relleno.style.width = '100%'; }
        var pctTxt = document.getElementById('visorProgresoPct');
        if (pctTxt) { pctTxt.textContent = '100 %'; }
        setTimeout(function () { zipProgresoCerrar(); }, 350);
    }

    /* Lee la respuesta NDJSON de una extraccion con progreso: actualiza la
       barra con cada linea y devuelve la ultima ("fin"), que trae el mismo
       JSON que antes devolvia el servidor. Si falla, cierra la barra y
       relanza el error con las flags de contrasena para que
       zipEjecutarConPassword pueda volver a preguntar. */
    function zipLeerProgreso(respuesta) {
        if (!respuesta.ok || !respuesta.body || typeof respuesta.body.getReader !== 'function') {
            zipProgresoCerrar();
            return zipRespuesta(respuesta);
        }

        var lector      = respuesta.body.getReader();
        var decodificador = new TextDecoder('utf-8');
        var buffer      = '';
        var final       = null;

        function linea(texto) {
            if (!texto) { return; }
            var d;
            try { d = JSON.parse(texto); } catch (e) { return; }
            if (!d || !d.tipo) { return; }
            if (d.tipo === 'inicio' || d.tipo === 'progreso') {
                zipProgresoActualizar(d);
            } else if (d.tipo === 'fin') {
                final = d;
            }
        }

        function leer() {
            return lector.read().then(function (res) {
                if (res.done) {
                    buffer.split('\n').forEach(linea);
                    buffer = '';
                    if (final) { return final; }
                    throw new Error('La extracci\u00f3n se interrumpi\u00f3.');
                }
                buffer += decodificador.decode(res.value, { stream: true });
                var trozos = buffer.split('\n');
                buffer = trozos.pop();
                trozos.forEach(linea);
                return leer();
            });
        }

        return leer().then(function (d) {
            if (d.success) {
                zipProgresoCompleto();
                return d;
            }
            zipProgresoCerrar();
            var err = new Error(d.mensaje || 'No se pudo extraer el ' + zipTipoTxt() + '.');
            if (d.requierePassword || d.passwordIncorrecta) { err.password = true; }
            if (d.passwordIncorrecta) { err.passwordIncorrecta = true; }
            throw err;
        }, function (e) {
            zipProgresoCerrar();
            throw e;
        });
    }

    /* Pregunta la contraseña del ZIP. Devuelve el valor o null si el usuario
       cancela. Solo vive en memoria: nunca se guarda ni se manda a logs.

       Se pinta en la capa propia (.visor-capa), como el memo de la DBF:
       SweetAlert solo admite un modal a la vez y un Swal aqui cerraria la
       ventana del indice ZIP que esta debajo. Mismo aspecto que el resto
       (barra de titulo, X con su animacion de hover) y Enter acepta. */
    function zipPedirPassword(motivo) {
        return new Promise(function (resolver) {
            var resuelto = false;
            var vigila   = null;

            function terminar(valor) {
                if (resuelto) { return; }
                resuelto = true;
                if (vigila) { clearInterval(vigila); vigila = null; }
                cerrarVentanaPropia();
                resolver(valor);
            }

            cerrarVentanaPropia();

            var capa = document.createElement('div');
            capa.className = 'visor-capa';
            capa.innerHTML =
                '<div class="visor-ventana visor-ventana-propia visor-password" ' +
                        'style="width:min(480px, 94vw)" role="dialog" ' +
                        'aria-modal="true" aria-label="Contrase\u00f1a del ' + zipTipoTxt() + '">' +
                    '<div class="visor-caption" data-tipo="zip">' +
                        '<i class="fas fa-lock visor-caption-icono" aria-hidden="true"></i>' +
                        '<span class="visor-caption-titulo">Contrase\u00f1a del ' + zipTipoTxt() + '</span>' +
                        '<button type="button" class="visor-caption-cerrar" ' +
                            'aria-label="Cerrar ventana" title="Cerrar">' +
                            '<i class="fas fa-xmark" aria-hidden="true"></i>' +
                        '</button>' +
                    '</div>' +
                    '<div class="visor-cuerpo">' +
                        '<div class="visor-scroll">' +
                            '<p class="visor-password-txt">' +
                                '<i class="fas fa-key" aria-hidden="true"></i>' +
                                '<span>' + escapar(motivo || 'Este archivo est\u00e1 protegido con contrase\u00f1a.') +
                                '</span>' +
                            '</p>' +
                            '<div class="visor-password-campo">' +
                                '<i class="fas fa-lock visor-password-icono" aria-hidden="true"></i>' +
                                '<input type="password" class="visor-zip-password-input" ' +
                                    'placeholder="Contrase\u00f1a" autocomplete="off" ' +
                                    'spellcheck="false" aria-label="Contrase\u00f1a del ' + zipTipoTxt() + '">' +
                                '<button type="button" class="visor-password-ojo" ' +
                                    'title="Mostrar contrase\u00f1a" aria-label="Mostrar contrase\u00f1a">' +
                                    '<i class="fas fa-eye" aria-hidden="true"></i>' +
                                '</button>' +
                            '</div>' +
                            '<p class="visor-password-error" hidden>' +
                                '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>' +
                                '<span></span>' +
                            '</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="visor-pie">' +
                        '<button type="button" class="visor-boton visor-password-aceptar">' +
                            '<i class="fas fa-unlock-keyhole" aria-hidden="true"></i>Aceptar</button>' +
                        '<button type="button" class="visor-boton visor-password-cancelar">' +
                            '<i class="fas fa-xmark" aria-hidden="true"></i>Cancelar</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(capa);

            var campo  = capa.querySelector('.visor-zip-password-input');
            var ojo    = capa.querySelector('.visor-password-ojo');
            var error  = capa.querySelector('.visor-password-error');
            var falta  = capa.querySelector('.visor-password-error span');

            function fallo(texto) {
                if (error && falta) {
                    falta.textContent = texto;
                    error.hidden = false;
                }
                if (campo) { campo.focus(); }
            }

            function intentar() {
                var valor = campo ? String(campo.value || '') : '';
                if (valor === '') {
                    fallo('Introduzca la contrase\u00f1a.');
                    return;
                }
                terminar(valor);
            }

            capa.querySelector('.visor-caption-cerrar').addEventListener('click', function () {
                terminar(null);
            });
            capa.querySelector('.visor-password-cancelar').addEventListener('click', function () {
                terminar(null);
            });
            capa.querySelector('.visor-password-aceptar').addEventListener('click', intentar);

            if (campo) {
                campo.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        e.preventDefault();
                        intentar();
                    }
                });
                campo.addEventListener('input', function () {
                    if (error) { error.hidden = true; }
                });
                campo.focus();
            }

            if (ojo && campo) {
                ojo.addEventListener('click', function () {
                    var visible = campo.type === 'text';
                    campo.type = visible ? 'password' : 'text';
                    ojo.innerHTML = '<i class="fas ' +
                        (visible ? 'fa-eye' : 'fa-eye-slash') + '" aria-hidden="true"></i>';
                    var etiqueta = visible ? 'Mostrar contrase\u00f1a' : 'Ocultar contrase\u00f1a';
                    ojo.title = etiqueta;
                    ojo.setAttribute('aria-label', etiqueta);
                    campo.focus();
                });
            }

            /* Esc (u otra via de cierre) quita la capa sin pasar por aqui:
               se vigila para resolver null y no dejar la operacion colgada. */
            vigila = setInterval(function () {
                if (!document.querySelector('.visor-capa')) {
                    if (vigila) { clearInterval(vigila); vigila = null; }
                    if (!resuelto) {
                        resuelto = true;
                        resolver(null);
                    }
                }
            }, 120);
        });
    }

    /* Ejecuta una operacion del ZIP y, si el servidor dice que hace falta
       la contrase\u00f1a (o que no es valida), la pregunta y reintenta.
       Se guardan 3 intentos como maximo para no martillear al usuario. */
    function zipEjecutarConPassword(preparar) {
        var intentos = 0;
        var guardada = zipEstado && zipEstado.password ? zipEstado.password : null;

        function paso(password) {
            return preparar(password).catch(function (err) {
                if (!err || !err.password || intentos >= 3) { throw err; }

                intentos++;
                var motivo = err.passwordIncorrecta
                    ? 'La contrase\u00f1a no es correcta.'
                    : (err.message || 'Esta entrada est\u00e1 protegida con contrase\u00f1a.');

                return zipPedirPassword(motivo).then(function (nueva) {
                    if (nueva === null) {
                        var cancelado = new Error('Operaci\u00f3n cancelada: no se introdujo la contrase\u00f1a.');
                        cancelado.cancelada = true;
                        throw cancelado;
                    }
                    if (zipEstado) { zipEstado.password = nueva; }
                    return paso(nueva);
                });
            });
        }

        return paso(guardada);
    }

    /* Aviso de cancelacion sin cerrar la ventana del ZIP. */
    function zipCancelado() {
        zipPintarResultado('visor-zip-error', 'fa-circle-info',
            'Operaci\u00f3n cancelada: no se introdujo la contrase\u00f1a.');
    }

    /* Descarga UNA entrada del ZIP (sin tocar la ventana abierta).
       Se manda por POST: asi la contrase\u00f1a, si hace falta, no acaba en la
       barra de direcciones ni en el historial. */
    function zipDescargarEntrada(boton) {
        if (boton.disabled) { return; }
        var restaurar = zipOcupar(boton);
        var hayCadena = !!(zipEstado && zipEstado.cadena && zipEstado.cadena.length);
        var ejecutar = hayCadena ? zipEjecutarCadena : zipEjecutarConPassword;

        ejecutar(function (password) {
            var cuerpo = new FormData();
            cuerpo.append('accion', 'ver_entrada');
            cuerpo.append('carpeta', boton.dataset.carpeta || '');
            cuerpo.append('ruta', boton.dataset.ruta || (zipEstado && zipEstado.datos.ruta) || '');
            cuerpo.append('token', (zipEstado && zipEstado.datos.csrf) || '');

            if (hayCadena) {
                zipCuerpoCadena(cuerpo, zipEstado.cadena, {
                    indice: parseInt(boton.dataset.indice, 10) || 0,
                    nombre: boton.dataset.nombre || ''
                });
            } else {
                cuerpo.append('archivo', boton.dataset.archivo || '');
                cuerpo.append('entrada', boton.dataset.indice || '');
            }
            if (password) { cuerpo.append('password', password); }

            return fetch(zipUrlAccion(), { method: 'POST', body: cuerpo, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { return zipLeerError(r); }
                    return r.blob();
                });
        })
            .then(function (blob) {
                zipDescargarBlob(blob, boton.dataset.nombre);
                restaurar();
            })
            .catch(function (err) {
                restaurar();
                if (err && err.cancelada) { zipCancelado(); return; }
            zipFallo('No se pudo extraer la entrada',
                (err && err.message) || 'No se pudo leer la entrada del ' + zipTipoTxt() + '.');
            });
    }

    /* Descarga el contenedor del nivel anidado actual: la cadena entera sin
       objetivo final (el servidor devuelve el temporal de ese contenedor). */
    function zipDescargarContenedor(boton) {
        if (!zipEstado || !zipEstado.cadena || !zipEstado.cadena.length) { return; }
        var restaurar = zipOcupar(boton);

        zipEjecutarCadena(function (password) {
            var cuerpo = new FormData();
            cuerpo.append('accion', 'ver_entrada');
            cuerpo.append('carpeta', (zipEstado.datos.carpeta) || '');
            cuerpo.append('ruta', (zipEstado.datos.ruta) || '');
            cuerpo.append('token', (zipEstado.datos.csrf) || '');
            zipCuerpoCadena(cuerpo, zipEstado.cadena, null);
            if (password) { cuerpo.append('password', password); }

            return fetch(zipUrlAccion(), { method: 'POST', body: cuerpo, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { return zipLeerError(r); }
                    return r.blob();
                });
        })
            .then(function (blob) {
                zipDescargarBlob(blob, basenameDe(zipEstado.datos.nombre || 'descarga'));
                restaurar();
            })
            .catch(function (err) {
                restaurar();
                if (err && err.cancelada) { zipCancelado(); return; }
                zipFallo('No se pudo descargar el contenedor',
                    (err && err.message) || 'No se pudo leer el archivo comprimido.');
            });
    }

    /* Extrae UNA entrada a la carpeta elegida. */
    function zipExtraerEntrada(boton) {
        if (boton.disabled) { return; }
        var restaurar = zipOcupar(boton);
        var hayCadena = !!(zipEstado && zipEstado.cadena && zipEstado.cadena.length);
        var ejecutar = hayCadena ? zipEjecutarCadena : zipEjecutarConPassword;

        ejecutar(function (password) {
            var cuerpo = new FormData();
            cuerpo.append('accion', 'extraer_uno');
            cuerpo.append('carpeta', boton.dataset.carpeta || '');
            cuerpo.append('ruta', boton.dataset.ruta || (zipEstado && zipEstado.datos.ruta) || '');
            cuerpo.append('destino', zipDestinoActual());
            cuerpo.append('token', (zipEstado && zipEstado.datos.csrf) || '');

            if (hayCadena) {
                zipCuerpoCadena(cuerpo, zipEstado.cadena, {
                    indice: parseInt(boton.dataset.indice, 10) || 0,
                    nombre: boton.dataset.nombre || ''
                });
            } else {
                cuerpo.append('archivo', boton.dataset.archivo || '');
                cuerpo.append('entrada', boton.dataset.indice || '');
            }
            if (password) { cuerpo.append('password', password); }

            return fetch(zipUrlAccion(), { method: 'POST', body: cuerpo, credentials: 'same-origin' })
                .then(zipRespuesta);
        })
            .then(function (data) {
                restaurar();
                var destino = data.destino ? ' en <strong>' + escapar(data.destino) + '</strong>' : '';
                zipPintarResultado('visor-zip-ok', 'fa-circle-check',
                    '<strong>' + escapar(boton.dataset.nombre) + '</strong> extra\u00eddo' + destino +
                    ' (' + bytesLegibles(data.bytes) + ')' +
                    (data.omitidos ? ' &middot; ' + data.omitidos + ' omitido(s)' : '') +
                    '. <button type="button" class="visor-zip-recargar" ' +
                    'onclick="window.location.reload()">' +
                    '<i class="fas fa-rotate-right"></i>Actualizar lista</button>');
            })
            .catch(function (err) {
                restaurar();
                if (err && err.cancelada) { zipCancelado(); return; }
                zipPintarResultado('visor-zip-error', 'fa-triangle-exclamation',
                    escapar((err && err.message) || 'No se pudo extraer la entrada.'));
            });
    }

    /* Extrae TODO el ZIP/RAR a la carpeta elegida (en anidados: el contenedor
       del nivel actual, recorriendo la cadena hasta el). */
    function zipExtraerTodo(boton) {
        if (boton.disabled) { return; }
        var restaurar = zipOcupar(boton);
        zipRestablecerResultado();
        var hayCadena = !!(zipEstado && zipEstado.cadena && zipEstado.cadena.length);
        var ejecutar = hayCadena ? zipEjecutarCadena : zipEjecutarConPassword;

        ejecutar(function (password) {
            var cuerpo = new FormData();
            cuerpo.append('accion', 'extraer_todo');
            cuerpo.append('carpeta', boton.dataset.carpeta || (zipEstado && zipEstado.datos.carpeta) || '');
            cuerpo.append('ruta', boton.dataset.ruta || (zipEstado && zipEstado.datos.ruta) || '');
            cuerpo.append('destino', zipDestinoActual());
            cuerpo.append('token', (zipEstado && zipEstado.datos.csrf) || '');
            cuerpo.append('progreso', '1');

            if (hayCadena) {
                zipCuerpoCadena(cuerpo, zipEstado.cadena, null);
            } else {
                cuerpo.append('archivo', boton.dataset.archivo || (zipEstado && zipEstado.datos.nombre) || '');
            }
            if (password) { cuerpo.append('password', password); }

            zipProgresoAbrir();
            zipProgresoAbort = new AbortController();

            return fetch(zipUrlAccion(), { method: 'POST', body: cuerpo, credentials: 'same-origin',
                    signal: zipProgresoAbort.signal })
                .then(function (respuesta) {
                    var ct = (respuesta.headers.get('content-type') || '').toLowerCase();
                    if (ct.indexOf('x-ndjson') !== -1) {
                        return zipLeerProgreso(respuesta);
                    }
                    // Sin streaming (validacion rechazada antes de empezar):
                    // se cierra la barra y se procesa el JSON normal.
                    zipProgresoCerrar();
                    return zipRespuesta(respuesta);
                });
        })
            .then(function (data) {
                restaurar();
                var partes = data.archivos + ' archivo' + (data.archivos === 1 ? '' : 's');
                if (data.carpetas) {
                    partes += ' y ' + data.carpetas + ' carpeta' + (data.carpetas === 1 ? '' : 's');
                }
                partes += ' extra\u00eddo' + (data.archivos === 1 && !data.carpetas ? '' : 's') +
                    ' (' + bytesLegibles(data.bytes) + ')';
                if (data.destino) {
                    partes += ' en <strong>' +
                        escapar(data.destinoTitulo || data.destino) +
                        (data.subcarpeta ? ' &rsaquo; <strong>' + escapar(data.subcarpeta) + '</strong>' : '') +
                        '</strong>';
                }
                if (data.omitidos) {
                    partes += ' &middot; ' + data.omitidos + ' omitido' + (data.omitidos === 1 ? '' : 's');
                }
                zipPintarResultado('visor-zip-ok', 'fa-circle-check', partes +
                    '. <button type="button" class="visor-zip-recargar" ' +
                    'onclick="window.location.reload()">' +
                    '<i class="fas fa-rotate-right"></i>Actualizar lista</button>');
            })
            .catch(function (err) {
                restaurar();
                zipProgresoCerrar();
                // Cancelada con X/Esc: el aviso ya lo pinto zipProgresoCancelar.
                if (zipProgresoCancelada || (err && err.name === 'AbortError')) { return; }
                if (err && err.cancelada) { zipCancelado(); return; }
            zipPintarResultado('visor-zip-error', 'fa-circle-exclamation',
                escapar((err && err.message) || 'No se pudo extraer el ' + zipTipoTxt() + '.'));
            });
    }

    /* Previsualiza una entrada del ZIP con el visor normal (txt/dbf/...). */
    function zipVerEntrada(boton) {
        if (boton.disabled) { return; }
        var restaurar = zipOcupar(boton);
        var hayCadena = !!(zipEstado && zipEstado.cadena && zipEstado.cadena.length);
        var ejecutar = hayCadena ? zipEjecutarCadena : zipEjecutarConPassword;

        ejecutar(function (password) {
            var cuerpo = new FormData();
            cuerpo.append('carpeta', boton.dataset.carpeta);
            cuerpo.append('ruta', boton.dataset.ruta || (zipEstado && zipEstado.datos.ruta) || '');

            if (hayCadena) {
                /* Vista anidada: la cadena completa hasta la entrada
                   (entradas[]) con una clave por nivel (passwords[]). */
                zipCuerpoCadena(cuerpo, zipEstado.cadena, {
                    indice: parseInt(boton.dataset.indice, 10) || 0,
                    nombre: boton.dataset.nombre || ''
                });
            } else {
                cuerpo.append('archivo', boton.dataset.archivo);
                cuerpo.append('entrada', boton.dataset.indice);
            }
            if (password) { cuerpo.append('password', password); }

            return fetch('ver_archivo.php', { method: 'POST', body: cuerpo, credentials: 'same-origin' })
                .then(zipRespuesta);
        })
            .then(function (data) {
                restaurar();

                data.bytesTexto = bytesLegibles(data.bytes);
                if (zipEstado) {
                    zipEstado.mostrandoContenido = true;
                    zipEstado.saltando = Date.now() + 700;
                }

                if (data.tipo === 'txt') {
                    visorTexto(data);
                } else if (data.tipo === 'dbf' || data.tipo === 'csv') {
                    visorTabla(data);
                } else if (data.tipo === 'xml') {
                    visorXml(data);
                } else {
                    preguntarDescarga(boton.dataset.nombre || 'archivo', function () {
                        var fila = boton.closest('tr');
                        var bajar = fila ? fila.querySelector('.visor-zip-bajar-uno') : null;
                        if (bajar) { zipDescargarEntrada(bajar); }
                    });
                }
            })
            .catch(function (err) {
                restaurar();
                if (err && err.cancelada) { zipCancelado(); return; }
                zipFallo('No se pudo mostrar la entrada',
                    (err && err.message) || 'No se pudo contactar el servidor.');
            });
    }

    document.addEventListener('change', function (e) {
        var select = e.target && e.target.closest ? e.target.closest('.visor-zip-destino') : null;
        if (select && zipEstado) { zipEstado.destino = select.value; }
    });

    document.addEventListener('click', function (e) {
        /* Segmento de la cadena anidada (raiz / dwn2.rar / ...): va antes
           que el handler de carpetas porque estos botones llevan tambien
           la clase .visor-zip-nivel. */
        var nivelCadena = e.target && e.target.closest ? e.target.closest('.visor-zip-nivel-cadena') : null;
        if (nivelCadena && zipEstado) {
            var nivelElegido = parseInt(nivelCadena.dataset.nivel, 10);
            if (!isNaN(nivelElegido)) {
                if (nivelElegido === (zipEstado.cadena || []).length) {
                    zipEstado.ruta = '';
                    pintarZip();
                } else {
                    zipNavegarCadena(nivelElegido);
                }
            }
            return;
        }

        var nivel = e.target && e.target.closest ? e.target.closest('.visor-zip-nivel') : null;
        if (nivel && zipEstado) {
            zipEstado.ruta = nivel.dataset.ruta || '';
            pintarZip();
            return;
        }

        var home = e.target && e.target.closest ? e.target.closest('.visor-zip-home') : null;
        if (home && zipEstado) {
            if (zipEstado.cadena && zipEstado.cadena.length) {
                zipNavegarCadena(0);
                return;
            }
            zipEstado.ruta = '';
            pintarZip();
            return;
        }

        var atras = e.target && e.target.closest ? e.target.closest('.visor-zip-atras, .visor-zip-subir') : null;
        if (atras && zipEstado) {
            var ruta = zipRuta();
            if (ruta === '') {
                if (zipEstado.cadena && zipEstado.cadena.length) {
                    zipNavegarCadena(zipEstado.cadena.length - 1);
                }
                return;
            }
            var trozos = ruta.split('/');
            trozos.pop();
            zipEstado.ruta = trozos.join('/');
            pintarZip();
            return;
        }

        var entrar = e.target && e.target.closest ? e.target.closest('.visor-zip-entrar') : null;
        if (entrar && zipEstado) {
            zipEstado.ruta = entrar.dataset.ruta || '';
            pintarZip();
            return;
        }

        var abrir = e.target && e.target.closest ? e.target.closest('.visor-zip-abrir') : null;
        if (abrir) { zipAbrirAnidado(abrir); return; }

        var ver = e.target && e.target.closest ? e.target.closest('.visor-zip-ver') : null;
        if (ver) { zipVerEntrada(ver); return; }

        /* Descarga del contenedor del nivel anidado (barra de acciones). */
        var contDescarga = e.target && e.target.closest ? e.target.closest('.visor-zip-bajar-contenedor') : null;
        if (contDescarga) { zipDescargarContenedor(contDescarga); return; }

        var bajar = e.target && e.target.closest ? e.target.closest('.visor-zip-bajar-uno') : null;
        if (bajar) {
            /* El icono de descarga solo baja sin preguntar si el fichero si
               se puede previsualizar; si no, se pasa por la misma pregunta. */
            var filaBajar = bajar.closest('tr');
            var btnVerMismaFila = filaBajar ? filaBajar.querySelector('.visor-zip-ver') : null;
            if (btnVerMismaFila) {
                zipDescargarEntrada(bajar);
            } else {
                preguntarDescarga(bajar.dataset.nombre || 'archivo', function () {
                    zipDescargarEntrada(bajar);
                });
            }
            return;
        }

        var extraer = e.target && e.target.closest ? e.target.closest('.visor-zip-extraer-uno') : null;
        if (extraer) { zipExtraerEntrada(extraer); return; }

        var todo = e.target && e.target.closest ? e.target.closest('.visor-zip-todo') : null;
        if (todo) { zipExtraerTodo(todo); return; }

        /* Ordenar por columna del indice (Archivo / Tipo / ...) */
        var ordenar = e.target && e.target.closest ? e.target.closest('.visor-zip-sort') : null;
        if (ordenar && zipEstado) {
            var campoOrden = ordenar.dataset.orden;
            if (zipEstado.orden.campo === campoOrden) {
                zipEstado.orden.dir = zipEstado.orden.dir === 'asc' ? 'desc' : 'asc';
            } else {
                zipEstado.orden = { campo: campoOrden, dir: 'asc' };
            }
            pintarZip();
            return;
        }

        /* Clic en cualquier zona de la fila: entrar en la carpeta o abrir
           el archivo (mismo efecto que su boton Ver). */
        var filaZip = e.target && e.target.closest ? e.target.closest('.visor-zip-tabla tbody tr') : null;
        if (filaZip && zipEstado) {
            var btnEntrarFila = filaZip.querySelector('.visor-zip-entrar');
            if (btnEntrarFila) {
                zipEstado.ruta = btnEntrarFila.dataset.ruta || '';
                pintarZip();
                return;
            }
            var btnAbrirFila = filaZip.querySelector('.visor-zip-abrir');
            if (btnAbrirFila) { zipAbrirAnidado(btnAbrirFila); return; }
            var btnVerFila = filaZip.querySelector('.visor-zip-ver');
            if (btnVerFila) { zipVerEntrada(btnVerFila); return; }

            /* Archivo que el visor no soporta: preguntar si se descarga. */
            var btnBajarFila = filaZip.querySelector('.visor-zip-bajar-uno');
            if (btnBajarFila) {
                preguntarDescarga(btnBajarFila.dataset.nombre || 'archivo', function () {
                    zipDescargarEntrada(btnBajarFila);
                });
            }
        }
    });

    var botonesVer = document.querySelectorAll('.carpeta-ver-archivo');

    Array.prototype.forEach.call(botonesVer, function (boton) {
        boton.addEventListener('click', function () {
            var btn = this;
            var esRar = /\.rar$/i.test(btn.dataset.nombre || '');

            // Estado provisorio mientras carga: da el tipo al titulo de la
            // capa de contrasena y un lugar donde guardar la clave si el RAR
            // tiene las cabeceras cifradas (-hp) y el indice la necesita.
            zipEstado = {
                datos: { tipo: esRar ? 'rar' : 'zip', nombre: btn.dataset.nombre },
                ruta: '',
                destino: null,
                mostrandoContenido: false,
                saltando: 0,
                password: null,
                orden: { campo: null, dir: 'asc' },
                cadena: [],
                passwords: [],
                archivoRaiz: btn.dataset.nombre,
                cache: {}
            };
            var passwordUsada = null;

            Swal.fire({
                title: 'Leyendo el archivo...',
                html: '<small>' + escapar(btn.dataset.nombre) + '</small>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });

            zipEjecutarConPassword(function (password) {
                passwordUsada = password;
                var cuerpo = new FormData();
                cuerpo.append('carpeta', btn.dataset.carpeta);
                cuerpo.append('archivo', btn.dataset.nombre);
                if (btn.dataset.ruta) { cuerpo.append('ruta', btn.dataset.ruta); }
                if (password) { cuerpo.append('password', password); }

                return fetch('ver_archivo.php', { method: 'POST', body: cuerpo, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json().then(function (json) {
                            if (r.ok && json && json.success) { return json; }
                            var err = new Error((json && json.mensaje) || 'HTTP ' + r.status);
                            if (json && (json.requierePassword || json.passwordIncorrecta)) {
                                err.password = true;
                            }
                            if (json && json.passwordIncorrecta) { err.passwordIncorrecta = true; }
                            throw err;
                        }, function () {
                            throw new Error('HTTP ' + r.status);
                        });
                    });
            })
                .then(function (data) {
                    // Tamanos legibles para la cabecera del modal
                    data.bytesTexto = bytesLegibles(data.bytes);

                    if (data.tipo === 'txt') {
                        zipEstado = null;
                        visorTexto(data);
                    } else if (data.tipo === 'dbf' || data.tipo === 'csv') {
                        zipEstado = null;
                        visorTabla(data);
                    } else if (data.tipo === 'xml') {
                        zipEstado = null;
                        visorXml(data);
                    } else if (data.tipo === 'zip' || data.tipo === 'rar') {
                        visorZip(data);
                        if (zipEstado) {
                            zipEstado.password = passwordUsada;
                            zipEstado.passwords = passwordUsada ? [passwordUsada] : [];
                            zipEstado.archivoRaiz = btn.dataset.nombre;
                        }
                    } else {
                        zipEstado = null;
                        visorError('Tipo de archivo no soportado.');
                    }
                })
                .catch(function (err) {
                    zipEstado = null;
                    if (err && err.cancelada) {
                        // Usuario cerro la capa de contrasena: basta con
                        // quitar el "Leyendo el archivo...".
                        Swal.close();
                        return;
                    }
                    visorError((err && err.message) || 'No se pudo contactar el servidor.');
                });
        });
    });

    /* Archivos que el visor no soporta: al clicar el nombre se pregunta si
       se quieren descargar (el boton solo existe si su rol permite). */
    var botonesNoSoportado = document.querySelectorAll('.carpeta-archivo-no-soportado');
    Array.prototype.forEach.call(botonesNoSoportado, function (boton) {
        boton.addEventListener('click', function () {
            var btn = this;
            var fila = btn.closest('tr');
            var enlace = fila ? fila.querySelector('a[download]') : null;
            if (!enlace) { return; }
            preguntarDescarga(btn.dataset.nombre || 'archivo', function () {
                enlace.dataset.descargaConfirmada = '1';
                enlace.click();
            });
        });
    });

    /* La columna Accion trae un enlace de descarga directa. Si el fichero de
       esa fila no es previsualizable, ese enlace tambien debe pasar por la
       misma pregunta (evitando dos modales: el flag lo salta una vez). */
    document.addEventListener('click', function (e) {
        var enlace = e.target && e.target.closest ? e.target.closest('a[download]') : null;
        if (!enlace) { return; }
        var fila = enlace.closest('tr');
        if (!fila || !fila.querySelector('.carpeta-archivo-no-soportado')) { return; }
        if (enlace.dataset.descargaConfirmada === '1') {
            enlace.dataset.descargaConfirmada = '';
            return;
        }
        e.preventDefault();
        preguntarDescarga(fila.dataset.nombre || 'archivo', function () {
            enlace.dataset.descargaConfirmada = '1';
            enlace.click();
        });
    });

    /* ==========================================
       CAMPOS MEMO (M): el texto vive en el .dbt / .fpt y se pide al clicar.
       Se pinta en la ventana apilada para NO cerrar la tabla de la DBF.
       ========================================== */
    document.addEventListener('click', function (e) {
        var boton = e.target && e.target.closest ? e.target.closest('.visor-memo') : null;
        if (!boton) {
            return;
        }

        var registro = parseInt(boton.dataset.reg, 10) || 0;
        var nombre = boton.dataset.archivo;
        var campo = boton.dataset.campo;
        var cuerpo = new FormData();
        cuerpo.append('carpeta', boton.dataset.carpeta);
        cuerpo.append('archivo', nombre);
        cuerpo.append('registro', registro);
        cuerpo.append('campo', campo);

        var tituloMemo = 'Archivo: ' + nombre + ' - MEMO · ' + campo +
                         ' (registro ' + (registro + 1) + ')';

        ventanaPropia({
            tipo: 'memo',
            titulo: tituloMemo,
            meta: '',
            cuerpo: '<div class="visor-cargando"><i class="fas fa-spinner fa-spin me-2"></i>' +
                    'Leyendo el memo\u2026</div>',
            ancho: 'min(900px, 92vw)'
        });

        function memoError(mensaje) {
            ventanaPropia({
                tipo: 'memo',
                titulo: tituloMemo,
                meta: '',
                cuerpo: '<div class="visor-fallo"><i class="fas fa-triangle-exclamation"></i>' +
                        '<span>' + escapar(mensaje || 'No se pudo leer el memo.') + '</span></div>',
                ancho: 'min(900px, 92vw)'
            });
        }

        fetch('ver_memo.php', {
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
                    memoError(data && data.mensaje);
                    return;
                }

                var extras = [
                    '<span class="visor-meta-item"><i class="fas fa-align-left"></i>' +
                        data.lineas + ' l&iacute;nea' + (data.lineas === 1 ? '' : 's') + '</span>',
                    '<span class="visor-meta-item"><i class="fas fa-file-zipper"></i>' +
                        escapar(data.fuente || '') + '</span>'
                ];
                if (data.aviso) {
                    extras.push('<span class="visor-meta-item"><i class="fas fa-triangle-exclamation"></i>' +
                                escapar(data.aviso) + '</span>');
                }

                var cuerpoMemo = '<pre class="visor-txt">' + escapar(data.contenido) + '</pre>';
                if (data.truncado) {
                    cuerpoMemo += '<div class="visor-nota"><i class="fas fa-triangle-exclamation me-1"></i>' +
                                  'Se muestran los primeros 512 KB del memo.</div>';
                }

                ventanaPropia({
                    tipo: 'memo',
                    titulo: tituloMemo,
                    meta: visorMeta({ nombre: data.nombre, bytesTexto: bytesLegibles(data.bytes) }, extras),
                    cuerpo: cuerpoMemo,
                    ancho: 'min(900px, 92vw)'
                });
            })
            .catch(function () {
                memoError('No se pudo contactar el servidor.');
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
            var esCarpeta = fila.dataset.esCarpeta === '1';
            var coincideExt = esCarpeta || !ext || (fila.dataset.extension || '').toLowerCase() === ext;
            var coincideTexto = !texto || (fila.dataset.nombre || '').toLowerCase().indexOf(texto) !== -1;
            var mostrar = coincideExt && coincideTexto;
            /* data-filtro separa "fuera del filtro" de "fuera de la pagina":
               la paginacion solo cuenta las que no estan filtradas. */
            fila.dataset.filtro = mostrar ? '0' : '1';
            fila.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });

        if (sinResultados) sinResultados.style.display = visibles === 0 ? '' : 'none';

        pagina = 1;
        aplicarPaginacion();
    }

    /* ==========================================
       ORDENAR POR COLUMNA (Archivo / Tipo / Tama\u00f1o / Modificado)
       Clic en el encabezado alterna ascendente/descendente.
       Solo reordena filas visibles; el filtro se respeta.
       ========================================== */
    var ordenActual = { campo: 'fecha', dir: 'desc' };  // coincide con el orden por defecto del PHP

    var comparadores = {
        nombre: function (a, b) {
            return (a.dataset.nombre || '').localeCompare(b.dataset.nombre || '', 'es', { sensitivity: 'base', numeric: true });
        },
        tipo: function (a, b) {
            var carpetaA = a.dataset.esCarpeta === '1' ? 0 : 1;
            var carpetaB = b.dataset.esCarpeta === '1' ? 0 : 1;
            if (carpetaA !== carpetaB) { return carpetaA - carpetaB; }  // carpetas primero
            return (a.dataset.extension || '').localeCompare(b.dataset.extension || '', 'es', { sensitivity: 'base' });
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
        /* Se reordena TODO el listado (no solo la pagina visible) para que el
           orden se mantenga al cambiar de pagina o quitar un filtro. */
        var lista = filas.slice().sort(function (a, b) {
            return ordenActual.dir === 'asc' ? cmp(a, b) : -cmp(a, b);
        });
        lista.forEach(function (fila) { tbody.appendChild(fila); });
        refrescarIconos();
        aplicarPaginacion();
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

    /* ==========================================
       PAGINACION DEL LISTADO (dropdown arriba,
       "Mostrando X a Y de Z archivos" y botones
       de pagina al pie de la tabla).
       ========================================== */
    var CLAVE_PAGINA = 'carpetas_por_pagina';
    var selPorPagina = document.getElementById('porPagina');
    var pagInfo = document.getElementById('paginaInfo');
    var pagNum = document.getElementById('paginaActual');
    var pagBotones = Array.prototype.slice.call(document.querySelectorAll('.carpeta-pag-btn'));
    var porPagina = 10;
    var pagina = 1;

    function leerPorPagina() {
        var guardado = null;
        try { guardado = parseInt(localStorage.getItem(CLAVE_PAGINA), 10); } catch (e) { guardado = null; }
        if (guardado === 10 || guardado === 25 || guardado === 50 || guardado === 100 || guardado === 0) {
            return guardado;
        }
        return 10;
    }

    function guardarPorPagina(valor) {
        try { localStorage.setItem(CLAVE_PAGINA, String(valor)); } catch (e) { /* sin soporte */ }
    }

    function totalPaginasDe(total) {
        if (porPagina <= 0) return 1;
        return Math.max(1, Math.ceil(total / porPagina));
    }

    /* Filas que pasan el filtro, en el orden actual del DOM */
    function filasFiltradas() {
        return Array.prototype.slice.call(tbody.children).filter(function (el) {
            return el.tagName === 'TR' && el.dataset.filtro !== '1';
        });
    }

    function aplicarPaginacion() {
        var visibles = filasFiltradas();
        var total = visibles.length;
        var totalPaginas = totalPaginasDe(total);

        if (pagina > totalPaginas) pagina = totalPaginas;
        if (pagina < 1) pagina = 1;

        var inicio = porPagina > 0 ? (pagina - 1) * porPagina : 0;
        var fin = porPagina > 0 ? inicio + porPagina : total;

        visibles.forEach(function (fila, i) {
            fila.style.display = (i >= inicio && i < fin) ? '' : 'none';
        });

        var desde = total > 0 ? inicio + 1 : 0;
        var hasta = Math.min(fin, total);

        if (pagInfo) {
            pagInfo.textContent = 'Mostrando ' + desde + ' a ' + hasta + ' de ' + total +
                ' archivo' + (total === 1 ? '' : 's');
        }
        if (pagNum) {
            pagNum.textContent = 'P\u00e1gina ' + pagina + ' de ' + totalPaginas;
        }
        pagBotones.forEach(function (btn) {
            var p = btn.dataset.pag;
            var esAnterior = p === 'primera' || p === 'anterior';
            btn.disabled = esAnterior ? pagina <= 1 : pagina >= totalPaginas;
        });
    }

    pagBotones.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var totalPaginas = totalPaginasDe(filasFiltradas().length);
            var p = btn.dataset.pag;
            if (p === 'primera') pagina = 1;
            else if (p === 'anterior') pagina = Math.max(1, pagina - 1);
            else if (p === 'siguiente') pagina = Math.min(totalPaginas, pagina + 1);
            else if (p === 'ultima') pagina = totalPaginas;
            aplicarPaginacion();
        });
    });

    if (selPorPagina) {
        porPagina = leerPorPagina();
        selPorPagina.value = String(porPagina);
        selPorPagina.addEventListener('change', function () {
            var v = parseInt(this.value, 10);
            porPagina = isNaN(v) ? 10 : v;
            guardarPorPagina(porPagina);
            pagina = 1;
            aplicarPaginacion();
        });
    }

    refrescarIconos();

    if (filtroExt) filtroExt.addEventListener('change', aplicarFiltros);
    if (buscar) buscar.addEventListener('input', aplicarFiltros);

    aplicarPaginacion();
})();