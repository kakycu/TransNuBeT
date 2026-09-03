/* Animacion de cumpleanos: globos y estrellas cayendo + SweetAlert de felicitacion
   Uso: definir window.PERFIL_CUMPLEANOS = { nombre, anios } antes de cargar este script. */
(function () {
    if (window.__birthdayAnimationCargada) return;
    window.__birthdayAnimationCargada = true;

    var cfg = window.PERFIL_CUMPLEANOS;
    if (!cfg) return;

    // Se muestra en cada carga de la página (sin guard de sesión)

    // Estilos de la animacion
    var estilo = document.createElement('style');
    estilo.textContent = [
        '#cumpleanosFondo { position: fixed; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; pointer-events: none; z-index: 2147483647; }',
        '.cumpleanos-item { position: absolute; top: -12vh; line-height: 1; will-change: transform; animation-name: cumpleanos-caer; animation-timing-function: linear; animation-iteration-count: infinite; }',
        '@keyframes cumpleanos-caer {',
        '  0%   { transform: translateY(0) translateX(0) rotate(0deg); opacity: 0; }',
        '  5%   { opacity: 1; }',
        '  25%  { transform: translateY(28vh) translateX(-35px) rotate(120deg); }',
        '  50%  { transform: translateY(56vh) translateX(30px) rotate(240deg); }',
        '  75%  { transform: translateY(84vh) translateX(-25px) rotate(360deg); }',
        '  100% { transform: translateY(118vh) translateX(20px) rotate(480deg); opacity: 0.9; }',
        '}',
        '.cumpleanos-swal-popup { border: 2px solid rgba(251, 191, 36, 0.6) !important; border-radius: 24px !important; }',
        '.cumpleanos-swal-titulo { font-size: 1.7rem; font-weight: 800; color: #fbbf24; display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }',
        '.cumpleanos-swal-titulo i { color: #f472b6; }',
        '.cumpleanos-swal-texto { color: #e2e8f0; font-size: 1.05rem; margin-top: 8px; }'
    ].join('\n');
    document.head.appendChild(estilo);

    // Contenedor en pantalla completa (por encima de todo)
    var contenedor = document.createElement('div');
    contenedor.id = 'cumpleanosFondo';
    document.body.appendChild(contenedor);

    // Elementos que caen: globos y estrellas
    var emojis = ['🎈', '🎈', '🎈', '🎈', '⭐', '⭐', '🌟', '✨', '🎉', '💫', '🎁'];
    var total = 45;
    for (var i = 0; i < total; i++) {
        var item = document.createElement('div');
        item.className = 'cumpleanos-item';
        item.textContent = emojis[Math.floor(Math.random() * emojis.length)];
        item.style.left = (Math.random() * 100) + '%';
        item.style.fontSize = (20 + Math.random() * 34) + 'px';
        item.style.animationDelay = (Math.random() * 6) + 's';
        item.style.animationDuration = (5 + Math.random() * 5) + 's';
        item.style.opacity = (0.7 + Math.random() * 0.3).toString();
        contenedor.appendChild(item);
    }

    function detener() {
        var c = document.getElementById('cumpleanosFondo');
        if (c && c.parentNode) c.parentNode.removeChild(c);
    }

    // SweetAlert bonito de felicitacion
    var nombre = cfg.nombre || '';
    var anios = parseInt(cfg.anios || 0, 10);
    var nombreLimpio = String(nombre).replace(/[<>&]/g, '');
    var subtitulo = anios > 0
        ? 'Hoy cumples <strong>' + anios + '</strong> años. Que pases un día lleno de salud, éxitos y alegrías.'
        : 'Que tengas un día lleno de alegrías y bendiciones.';

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '<div class="cumpleanos-swal-titulo"><i class="fas fa-birthday-cake"></i> ¡Feliz Cumpleaños!</div>',
            html: '<div class="cumpleanos-swal-texto">' +
                  (nombreLimpio ? '¡Muchas felicidades, <strong>' + nombreLimpio + '</strong>! 🥳' : '¡Muchas felicidades! 🥳') +
                  '<br>' + subtitulo + '</div>',
            icon: 'info',
            showConfirmButton: true,
            confirmButtonText: '<i class="fas fa-heart me-1"></i> ¡Gracias!',
            confirmButtonColor: '#f59e0b',
            background: 'linear-gradient(135deg, #1e1b4b 0%, #701a75 100%)',
            color: '#ffffff',
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: { popup: 'cumpleanos-swal-popup' }
        }).then(function () {
            detener();
        });
        // Respaldo: detener la animacion aunque no se cierre el popup
        setTimeout(detener, 25000);
    } else {
        setTimeout(detener, 9000);
    }
})();
