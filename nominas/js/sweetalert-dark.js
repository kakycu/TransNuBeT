/* sweetalert-dark.js
 * Tema oscuro global para SweetAlert2 (estilo Windows 11).
 * - Emite la librería SweetAlert2 bajo demanda si no está cargada.
 * - Aplica colores/estilos dark por defecto a TODOS los modales.
 * - Los parámetros explícitos de cada llamada a Swal.fire() tienen prioridad.
 */
(function () {
    'use strict';

    var DARK_THEME = {
        background: '#1a1a24',
        color: '#ffffff',
        confirmButtonColor: '#0078d4',
        cancelButtonColor: '#33333f',
        reverseButtons: true,
        backdrop: 'rgba(0, 0, 0, 0.72)'
    };

    var DARK_CUSTOM_CLASS = {
        popup: 'swal-dark-popup',
        title: 'swal-dark-title',
        htmlContainer: 'swal-dark-html',
        confirmButton: 'swal-dark-confirm',
        cancelButton: 'swal-dark-cancel'
    };

    function injectDarkCss() {
        var css =
            '.swal-dark-popup{border:1px solid rgba(255,255,255,0.12) !important;border-radius:16px !important;' +
            'backdrop-filter:blur(24px) !important;font-family:"Inter","Segoe UI",sans-serif;}' +
            '.swal-dark-title{color:#ffffff !important;}' +
            '.swal-dark-html{color:#d1d5db !important;}' +
            '.swal-dark-confirm,.swal-dark-cancel{border-radius:8px !important;font-weight:600 !important;}';
        var style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
    }

    function swalBaseUrl() {
        var path = window.location.pathname || '';
        return path.indexOf('/modules/') !== -1 ? '../js/' : 'js/';
    }

    function applyTheme() {
        if (!window.Swal || typeof Swal.fire !== 'function') return;
        injectDarkCss();

        if (Swal.DEFAULT_PARAMS && typeof Swal.DEFAULT_PARAMS === 'object') {
            Swal.DEFAULT_PARAMS = Object.assign({}, DARK_THEME, Swal.DEFAULT_PARAMS);
        }

        // Envolver Swal.fire para fusionar el tema oscuro con las opciones de cada llamada
        var origFire = Swal.fire.bind(Swal);
        Swal.fire = function (arg) {
            var opts = (typeof arg === 'string') ? { title: arg } : (arg || {});
            var merged = Object.assign({}, DARK_THEME, opts);
            if (opts.customClass && typeof opts.customClass === 'object') {
                merged.customClass = Object.assign({}, DARK_CUSTOM_CLASS, opts.customClass);
            } else {
                merged.customClass = DARK_CUSTOM_CLASS;
            }
            return origFire(merged);
        };
    }

    function emitSwalAndApply() {
        var tries = 0;
        var timer = setInterval(function () {
            if (window.Swal && typeof Swal.fire === 'function') {
                clearInterval(timer);
                applyTheme();
            } else if (++tries > 300) {
                clearInterval(timer);
            }
        }, 25);
    }

    if (window.Swal && typeof Swal.fire === 'function') {
        applyTheme();
    } else {
        // SweetAlert2 aún no está cargado: emitirlo bajo demanda
        var s = document.createElement('script');
        s.src = swalBaseUrl() + 'sweetalert2.all.min.js';
        s.onload = function () { applyTheme(); };
        document.head.appendChild(s);
        emitSwalAndApply();
    }
})();
