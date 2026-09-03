/* swalWin: SweetAlert2 con el tema oscuro del instalador (estilo Windows 11)
   Barra de titulo teal + boton X + backdrop no clickeable + timer con barra */
window.swalWin = function (opt) {
    opt = Object.assign({
        icono: 'info',
        titulo: '',
        texto: '',
        html: '',
        pie: '',
        confirmarTexto: 'Aceptar',
        confirmarIcono: 'fa-check',
        temporizador: 0
    }, opt);

    /* Mapeo de icono SweetAlert2 -> FontAwesome */
    const iconMap = {
        success: 'fa-circle-check', error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation', info: 'fa-circle-info', question: 'fa-circle-question'
    };
    const faIcon = iconMap[opt.icono] || 'fa-circle-info';

    /* Barra de titulo teal (mismo estilo que .win-titlebar del form) */
    const headerHtml =
        '<div class="swal-header-teal">' +
            '<span class="swal-h-icon"><i class="fas ' + faIcon + '"></i></span>' +
            '<span class="swal-h-title">' + (opt.titulo || '') + '</span>' +
            '<button type="button" class="swal-h-close" data-swal-close><i class="fas fa-xmark"></i></button>' +
        '</div>';

    const cfg = {
        icon: undefined,
        title: undefined,
        text: opt.texto || undefined,
        html: opt.html || undefined,
        footer: opt.pie || undefined,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#0f766e',
        cancelButtonColor: '#33333f',
        reverseButtons: true,
        backdrop: 'rgba(0, 0, 0, 0.72)',
        allowOutsideClick: false,
        timer: opt.temporizador > 0 ? opt.temporizador : undefined,
        timerProgressBar: opt.temporizador > 0,
        confirmButtonText: opt.confirmarTexto,
        showConfirmButton: true,
        customClass: {
            popup: 'swal-dark-popup',
            htmlContainer: 'swal-dark-html',
            confirmButton: 'swal-dark-confirm'
        },
        didOpen: function (p) {
            /* Inyectar barra de titulo teal */
            p.insertAdjacentHTML('afterbegin', headerHtml);

            /* Boton X cierra */
            var xBtn = p.querySelector('[data-swal-close]');
            if (xBtn) xBtn.addEventListener('click', function () { Swal.close(); });

            /* Boton confirmar con icono */
            var c = p.querySelector('.swal2-confirm');
            if (c && !c.querySelector('i'))
                c.insertAdjacentHTML('afterbegin', '<i class="fas ' + opt.confirmarIcono + '" style="margin-right:8px"></i>');
        }
    };
    return Swal.fire(cfg);
};
(function () {
    var css =
        /* ===== Popup base ===== */
        '.swal-dark-popup{' +
        'border:1px solid #334155 !important;border-radius:12px !important;' +
        'backdrop-filter:blur(20px) !important;font-family:"Inter","Segoe UI",sans-serif !important;' +
        'overflow:hidden !important;' +
        '}' +
        /* ===== Barra de titulo teal (mismo gradiente que .win-titlebar) ===== */
        '.swal-header-teal{' +
        'display:flex;align-items:center;gap:10px;' +
        'background:linear-gradient(135deg,#0f766e 0%,#134e4a 100%);' +
        'padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.1);' +
        'min-height:42px;' +
        '}' +
        '.swal-h-icon{color:#5eead4;font-size:17px;flex-shrink:0;}' +
        '.swal-h-title{flex:1;font-size:15px;font-weight:700;color:#fff;letter-spacing:.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}' +
        '.swal-h-close{' +
        'flex-shrink:0;width:30px;height:30px;border:none;border-radius:8px;' +
        'background:rgba(255,255,255,.12);color:#e2e8f0;font-size:15px;' +
        'cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:.15s;' +
        '}' +
        '.swal-h-close:hover{background:#ef4444;color:#fff;}' +
        /* Ocultar icono + titulo nativos de SweetAlert2 (ya van en la barra) */
        '.swal-dark-popup .swal2-icon{display:none !important;}' +
        '.swal-dark-popup .swal2-title{display:none !important;}' +
        /* Contenido del cuerpo */
        '.swal-dark-popup .swal2-html-container{color:#cbd5e1 !important;line-height:1.65 !important;margin:20px 26px 10px !important;}' +
        /* Boton confirmar */
        '.swal-dark-confirm{border-radius:8px !important;font-weight:600 !important;}' +
        /* Barra de tiempo teal */
        '.swal2-timer-progress-bar{background:#00000033 !important;height:4px !important;}' +
        '.swal-dark-popup .swal2-timer-progress-bar{' +
        'background:linear-gradient(90deg,#2dd4bf,#14b8a6) !important;' +
        'box-shadow:0 0 8px rgba(45,212,191,.55);' +
        '}';
    var s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
})();
