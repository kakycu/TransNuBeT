/* ============================================================
   historico.js - Comportamiento del Histórico de Operaciones
   - Contador de acciones seleccionadas
   - Botón "Mostrar filtros" (colapsable)
   (El modal de detalle vive en historico.php: usa Bootstrap Modal.)
   ============================================================ */
(function () {
    'use strict';

    // ------------------------------------------------------------
    // Contador de acciones seleccionadas en los filtros
    // ------------------------------------------------------------
    var contadorAcciones = document.getElementById('accionesSel');
    var chequearAcciones = function () {
        if (!contadorAcciones) return;
        var total = document.querySelectorAll('input[name="action_type[]"]:checked').length;
        contadorAcciones.textContent = total;
    };

    if (contadorAcciones) {
        chequearAcciones();
        document.querySelectorAll('input[name="action_type[]"]').forEach(function (cb) {
            cb.addEventListener('change', chequearAcciones);
        });
    }

    // ------------------------------------------------------------
    // Filtros colapsables: refrescar flecha y mantener estado
    // ------------------------------------------------------------
    var panelFiltros = document.getElementById('filtrosPanel');
    var cabeceraFiltros = panelFiltros ? panelFiltros.parentElement.querySelector('.audit-card-header') : null;

    if (panelFiltros && cabeceraFiltros) {
        // Si hay filtros activos, dejar el panel abierto
        var hayFiltros = ['fecha_desde', 'fecha_hasta', 'usuario', 'auth_provider',
                          'module', 'status', 'ip', 'texto'].some(function (clave) {
            var inputs = document.querySelectorAll('[name="' + clave + '"]');
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].value !== '') return true;
            }
            return false;
        }) || (document.querySelectorAll('input[name="action_type[]"]:checked').length > 0);

        if (hayFiltros) {
            panelFiltros.classList.add('show');
            cabeceraFiltros.setAttribute('aria-expanded', 'true');
        }

        cabeceraFiltros.addEventListener('click', function () {
            var abierto = panelFiltros.classList.toggle('show');
            cabeceraFiltros.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        });
        // Evitar que un clic en botones/enlaces de la cabecera dispare el toggle
        cabeceraFiltros.querySelectorAll('a, .btn-win').forEach(function (el) {
            el.addEventListener('click', function (e) { e.stopPropagation(); });
        });
    }

    // ------------------------------------------------------------
    // Cerrar alerta de intentos fallidos
    // ------------------------------------------------------------
    window.cerrarAlerta = function (id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    };
})();