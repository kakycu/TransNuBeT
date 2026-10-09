// includes/mantenimiento_ui_js.php - JS compartido del mantenimiento de BD.
// Se imprime dentro de una etiqueta <script>. Lo incluye user_menu.php
// (presente en todas las paginas). Cubre: diagnostico, reparacion,
// backup rapido y el overlay "Diagnostico / Reparacion" de user_menu.

if (!window.__mantBdUi) {
    window.__mantBdUi = true;

    (function inyectarEstilos() {
        if (document.getElementById('mantBdStyles')) return;
        var st = document.createElement('style');
        st.id = 'mantBdStyles';
        st.textContent = '' +
            '#diagnosticoBdResultados code, #reparacionBdResultados code {' +
            '  color: var(--txt); background: rgba(var(--accent-rgb), 0.12);' +
            '  border: 0.0625rem solid rgba(var(--accent-rgb), 0.25);' +
            '  padding: 0.05rem 0.3rem; border-radius: 0.3rem;' +
            '  font-size: 0.85em; word-break: break-all;' +
            '}' +
            '.sr-mto-overlay{' +
            '  position: fixed; inset: 0; z-index: 1075;' +
            '  background: rgba(0,0,0,.55); display: flex;' +
            '  align-items: center; justify-content: center; padding: 1rem;' +
            '}' +
            '.sr-mto{' +
            '  width: min(56rem, 100%); max-height: 92vh; overflow: auto;' +
            '  background: var(--bg); color: var(--txt);' +
            '  border: 1px solid rgba(var(--blue-soft-rgb), .2);' +
            '  border-radius: .75rem; padding: 1.5rem; position: relative;' +
            '  animation: srMtoIn .25s ease both;' +
            '}' +
            '@keyframes srMtoIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}' +
            '.sr-mto-close{' +
            '  position: absolute; top: 1rem; right: 1rem; width: 2rem; height: 2rem;' +
            '  border-radius: .5rem; border: none; background: transparent;' +
            '  color: var(--muted); cursor: pointer; display: flex;' +
            '  align-items: center; justify-content: center;' +
            '}' +
            '.sr-mto-close:hover{background: rgba(var(--blue-soft-rgb), .12); color: var(--txt);}' +
            '.sr-mto-titlebar{display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; padding-right: 2.5rem;}' +
            '.sr-mto-titlebar-icon{' +
            '  width: 2.25rem; height: 2.25rem; border-radius: .5rem; flex-shrink: 0;' +
            '  display: flex; align-items: center; justify-content: center;' +
            '  color: var(--blue); background: rgba(var(--blue-soft-rgb), .14);' +
            '}' +
            '.sr-mto-title{font-size: 1.25rem; font-weight: 700; margin: 0; line-height: 1.2;}' +
            '.sr-mto-sub{font-size: .8125rem; color: var(--muted); margin: .125rem 0 0;}' +
            '.sr-mto-checks{display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; margin-top: .75rem;}' +
            '.sr-mto-footer{' +
            '  display: flex; align-items: center; justify-content: space-between;' +
            '  gap: .75rem; flex-wrap: wrap; margin-top: 1.25rem; padding-top: 1.25rem;' +
            '  border-top: 1px solid rgba(var(--blue-soft-rgb), .15);' +
            '}' +
            '@media (prefers-reduced-motion: reduce){ .sr-mto{animation:none} }';
        document.head.appendChild(st);
    })();

    window.mantBdPrefijo = function () {
        return (window.location.pathname.indexOf('/modules/') !== -1) ? '../' : '';
    };

    window.mantBdUrl = function (accion) {
        return window.mantBdPrefijo() + 'modules/configuracion.php?ajax=' + accion;
    };

    window.escaparHtmlDiag = function (s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    function elDiag() { return document.getElementById('mto_diagnosticoBdResultados') || document.getElementById('diagnosticoBdResultados'); }
    function elRep() { return document.getElementById('mto_reparacionBdResultados') || document.getElementById('reparacionBdResultados'); }
    function elBtn(base) { return document.getElementById('mto_' + base) || document.getElementById(base); }
    function chk(base) {
        var a = document.getElementById('mto_' + base);
        var b = document.getElementById(base);
        var el = (a && a.offsetParent !== null) ? a : b;
        if (a && b && a.offsetParent !== null && b.offsetParent !== null && a === b) el = a;
        return (el ? el.checked === true : false);
    }

    function seccionDiag(titulo, icono, rgb, conteo, cuerpo) {
        if (!cuerpo) return '';
        return '<div class="mb-2 p-2 rounded small" style="background: rgba(' + rgb + ', 0.08); border: 0.0625rem solid rgba(' + rgb + ', 0.3);">' +
            '<div class="fw-semibold mb-1"><i class="fas ' + icono + ' me-1" style="color: rgb(' + rgb + ');"></i> ' + titulo +
            ' <span class="badge ms-1" style="background: rgba(' + rgb + ', 0.25); color: rgb(' + rgb + '); font-size: .68rem;">' + conteo + '</span></div>' +
            cuerpo + '</div>';
    }

    function badgeDiag(rgb, icono, texto) {
        return '<span class="badge me-1 mb-1" style="background: rgba(' + rgb + ', 0.15); color: rgb(' + rgb + '); font-size: .7rem;">' +
            '<i class="fas ' + icono + ' me-1"></i>' + texto + '</span>';
    }

    window.pintarDiagnosticoBd = function (d) {
        var esc = window.escaparHtmlDiag;
        var r = d.resumen || {};
        var html = '<div class="small text-white-50 mb-2">Base <code>' + esc(d.base || '-') + '</code> &bull; ' +
            (r.tablas || 0) + ' tablas &bull; ' + (r.fks || 0) + ' claves for\u00e1neas &bull; ' + (d.segundos || 0) + ' s</div>';

        html += '<div class="mb-2">' +
            badgeDiag('52, 211, 153', 'fa-check-circle', 'CHECK OK: ' + (r.check_ok || 0)) +
            badgeDiag('245, 158, 11', 'fa-exclamation-triangle', 'Avisos: ' + (r.check_aviso || 0)) +
            badgeDiag('239, 68, 68', 'fa-times-circle', 'Errores: ' + (r.check_error || 0)) +
            badgeDiag('96, 165, 250', 'fa-link', 'FK sin \u00edndice: ' + (r.fks_sin_indice || 0)) +
            badgeDiag('168, 85, 247', 'fa-language', 'Colaciones: ' + (r.collation_bad || 0)) +
            badgeDiag('236, 72, 153', 'fa-user-slash', 'Hu\u00e9rfanas: ' + (r.huerfanos || 0)) +
            badgeDiag('148, 163, 184', 'fa-chart-pie', 'Fragmentadas: ' + (r.fragmentadas || 0)) +
            '</div>';

        var problemas = (d.tablas || []).filter(function (t) { return t.check !== 'ok'; });
        if (problemas.length) {
            var cuerpo = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tabla</th><th>Estado</th><th>Mensaje</th></tr></thead><tbody>';
            problemas.forEach(function (t) {
                var color = t.check === 'error' ? '#ef4444' : '#f59e0b';
                cuerpo += '<tr><td><code>' + esc(t.nombre) + '</code></td>' +
                    '<td style="color: ' + color + ';">' + esc(t.check) + '</td>' +
                    '<td>' + esc(t.mensaje) + '</td></tr>';
            });
            cuerpo += '</tbody></table></div>';
            html += seccionDiag('CHECK TABLE con problemas', 'fa-database', '239, 68, 68', problemas.length, cuerpo);
        }

        var sinIndice = (d.fks || []).filter(function (f) { return !f.tiene_indice; });
        if (sinIndice.length) {
            var cuerpoIdx = '<ul class="mb-0 ps-3">';
            sinIndice.forEach(function (f) {
                cuerpoIdx += '<li><code>' + esc(f.tabla) + '.' + esc(f.columnas) + '</code> &rarr; <code>' + esc(f.padre) + '.' + esc(f.padre_cols) + '</code> (' + esc(f.constraint) + ')</li>';
            });
            cuerpoIdx += '</ul>';
            html += seccionDiag('Claves for\u00e1neas sin \u00edndice', 'fa-unlink', '96, 165, 250', sinIndice.length, cuerpoIdx);
        }

        if ((d.collations || []).length) {
            var cuerpoCol = '<ul class="mb-0 ps-3">';
            d.collations.forEach(function (f) {
                if (f.constraint && f.constraint !== '-') {
                    cuerpoCol += '<li><code>' + esc(f.tabla) + '.' + esc(f.columnas) + '</code> (' + esc(f.coll_hija || 'n/a') + ') vs <code>' + esc(f.padre) + '.' + esc(f.padre_cols) + '</code> (' + esc(f.coll_padre || 'n/a') + ')' +
                        (f.motivo ? ' &mdash; ' + esc(f.motivo) : '') + '</li>';
                } else {
                    cuerpoCol += '<li><code>' + esc(f.tabla) + '.' + esc(f.columnas) + '</code>: ' + esc(f.coll_hija || 'n/a') +
                        (f.motivo ? ' &mdash; ' + esc(f.motivo) : '') + '</li>';
                }
            });
            cuerpoCol += '</ul><div class="small mt-1"><i class="fas fa-wrench me-1"></i>Se repara con: <strong>Unificar colaciones</strong></div>';
            html += seccionDiag('Colaciones distintas en un mismo JOIN', 'fa-language', '168, 85, 247', d.collations.length, cuerpoCol);
        }

        if ((d.huerfanos || []).length) {
            var cuerpoHue = '<ul class="mb-0 ps-3">';
            d.huerfanos.forEach(function (h) {
                cuerpoHue += '<li><code>' + esc(h.tabla) + '</code> (' + esc(h.constraint) + ') sin padre en <code>' + esc(h.padre) + '</code>: <strong>' + h.cantidad + '</strong> fila(s)';
                if ((h.ejemplos || []).length) {
                    cuerpoHue += '<br><span class="small">Ej: ' + h.ejemplos.map(function (e) { return '<code>' + esc(e) + '</code>'; }).join(' ') + '</span>';
                }
                cuerpoHue += '</li>';
            });
            cuerpoHue += '</ul><div class="small mt-1"><i class="fas fa-wrench me-1"></i>Se repara con: <strong>Eliminar filas hu\u00e9rfanas</strong></div>';
            html += seccionDiag('Filas hu\u00e9rfanas (hijas sin padre)', 'fa-user-slash', '236, 72, 153', d.huerfanos.length, cuerpoHue);
        }

        var fragmentadas = (d.tablas || []).filter(function (t) { return t.libre_pct >= 20; })
            .sort(function (a, b) { return b.libre_pct - a.libre_pct; }).slice(0, 10);
        if (fragmentadas.length) {
            var cuerpoFrag = '<ul class="mb-0 ps-3">';
            fragmentadas.forEach(function (t) {
                cuerpoFrag += '<li><code>' + esc(t.nombre) + '</code>: ' + t.libre_pct + '% libre (' + esc(t.motor) + ')</li>';
            });
            cuerpoFrag += '</ul><div class="small mt-1"><i class="fas fa-wrench me-1"></i>Se repara con: <strong>Optimizar fragmentadas</strong></div>';
            html += seccionDiag('Tablas con fragmentaci\u00f3n de datos', 'fa-chart-pie', '148, 163, 184', fragmentadas.length, cuerpoFrag);
        }

        var sinProblemas = !problemas.length && !sinIndice.length && !d.collations.length && !d.huerfanos.length;
        if (sinProblemas) {
            html += '<div class="alert alert-success mb-0 py-2 small"><i class="fas fa-check-circle me-2"></i>' +
                'Sin problemas detectados: estructura, \u00edndices de claves for\u00e1neas, colaciones e integridad referencial est\u00e1n en orden.</div>';
        }
        return html;
    };

    window.pintarReparacionBd = function (d) {
        var esc = window.escaparHtmlDiag;
        var r = d.resumen || {};
        var pasos = d.pasos || {};
        var html = '<div class="small text-white-50 mb-2">Reparaci\u00f3n ejecutada en ' + (d.segundos || 0) + ' s</div>';

        html += '<div class="mb-2">' +
            badgeDiag('52, 211, 153', 'fa-wrench', 'Reparadas: ' + (r.reparadas || 0)) +
            badgeDiag('52, 211, 153', 'fa-broom', 'Optimizadas: ' + (r.optimizadas || 0)) +
            badgeDiag('52, 211, 153', 'fa-chart-simple', 'Analizadas: ' + (r.analizadas || 0)) +
            badgeDiag('96, 165, 250', 'fa-link', '\u00cdndices: ' + (r.indices_creados || 0)) +
            badgeDiag('168, 85, 247', 'fa-language', 'Colaciones: ' + (r.collations_unificadas || 0)) +
            badgeDiag('236, 72, 153', 'fa-user-slash', 'Hu\u00e9rfanos borrados: ' + (r.huerfanos_borrados || 0)) +
            badgeDiag('239, 68, 68', 'fa-times-circle', 'Fallidas: ' + (r.fallidas || 0)) +
            '</div>';

        function detalleLista(detalles) {
            if (!detalles || !detalles.length) return '';
            var out = '<ul class="mb-0 ps-3 mt-1">';
            detalles.forEach(function (x) {
                var color = x.estado === 'error' ? '#ef4444' : '#34d399';
                out += '<li style="color:' + color + ';"><code>' + esc(x.tabla) + '</code> &mdash; ' + esc(x.mensaje) + '</li>';
            });
            return out + '</ul>';
        }

        var pCheck = pasos.reparar_check;
        if (pCheck && pCheck.ejecutado) {
            var c1 = '<div class="small">' + (pCheck.sin_errores || 0) + ' tabla(s) sin errores de CHECK.</div>' + detalleLista(pCheck.detalles);
            html += seccionDiag('Reparaci\u00f3n de errores (CHECK)', 'fa-database', pCheck.fallidas ? '239, 68, 68' : '52, 211, 153', pCheck.reparadas, c1);
        }

        var pOpt = pasos.optimizar;
        if (pOpt && pOpt.ejecutado) {
            var c2 = '<div class="small">' + (pOpt.optimizadas || 0) + ' optimizada(s); ' + (pOpt.omitidas || 0) + ' sin fragmentaci\u00f3n (omitidas).</div>' + detalleLista(pOpt.detalles);
            html += seccionDiag('Optimizaci\u00f3n de tablas fragmentadas', 'fa-broom', pOpt.fallidas ? '239, 68, 68' : '52, 211, 153', pOpt.optimizadas, c2);
        }

        var pAna = pasos.analyze;
        if (pAna && pAna.ejecutado) {
            var c3 = '<div class="small">' + (pAna.analizadas || 0) + ' tabla(s) analizada(s).</div>' + detalleLista(pAna.detalles);
            html += seccionDiag('Estad\u00edsticas del optimizador (ANALYZE)', 'fa-chart-simple', pAna.fallidas ? '239, 68, 68' : '52, 211, 153', pAna.analizadas, c3);
        }

        var pIdx = pasos.indices_fk;
        if (pIdx && pIdx.ejecutado) {
            var c4 = '<div class="small">' + (pIdx.sin_faltantes || 0) + ' clave(s) for\u00e1nea(s) ya ten\u00edan \u00edndice.</div>' + detalleLista(pIdx.detalles);
            html += seccionDiag('\u00cdndices de claves for\u00e1neas', 'fa-link', pIdx.fallidos ? '239, 68, 68' : '52, 211, 153', pIdx.creados, c4);
        }

        var pCol = pasos.unificar_collations;
        if (pCol && pCol.ejecutado) {
            var c5 = '<div class="small">' + (pCol.unificadas || 0) + ' tabla(s) unificada(s) a la colaci\u00f3n mayoritaria.</div>' + detalleLista(pCol.detalles);
            html += seccionDiag('Unificaci\u00f3n de colaciones', 'fa-language', pCol.fallidas ? '239, 68, 68' : '168, 85, 247', pCol.unificadas, c5);
        }

        var pHz = pasos.borrar_huerfanos;
        if (pHz && pHz.ejecutado) {
            var c6 = '<div class="small">' + (pHz.borradas || 0) + ' fila(s) hu\u00e9rfana(s) eliminada(s).</div>' + detalleLista(pHz.detalles);
            html += seccionDiag('Eliminaci\u00f3n de filas hu\u00e9rfanas', 'fa-user-slash', (pHz.detalles || []).some(function (x) { return x.estado === 'error'; }) ? '239, 68, 68' : '236, 72, 153', pHz.borradas, c6);
        }

        if ((r.fallidas || 0) > 0 || !d.success) {
            html += '<div class="alert alert-danger mb-0 py-2 small"><i class="fas fa-times-circle me-2"></i>Reparaci\u00f3n terminada con ' + (r.fallidas || 0) + ' error(es). Revise los detalles.</div>';
        } else {
            html += '<div class="alert alert-success mb-0 py-2 small"><i class="fas fa-check-circle me-2"></i>Reparaci\u00f3n completada sin errores. El diagn\u00f3stico ya se actualiz\u00f3.</div>';
        }
        return html;
    };

    window.ejecutarDiagnosticoBd = async function () {
        var cont = elDiag();
        if (!cont) return;
        cont.style.display = 'block';
        var swalDiag = Swal.fire({
            title: 'Diagnosticando la base de datos\u2026',
            html: '<p class="mb-0">CHECK TABLE, claves for\u00e1neas, colaciones e integridad referencial.</p>' +
                  '<p class="small mb-0" style="color: var(--muted);">Solo lectura: no se modifica nada.</p>',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); },
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: 'var(--panel)',
            color: 'var(--txt)'
        });
        try {
            var r = await fetch(window.mantBdUrl('diagnostico_bd'), { credentials: 'same-origin' });
            var t = await r.text();
            var d;
            try {
                d = JSON.parse(t.replace(/^\uFEFF/, '').trim());
            } catch (e) {
                throw new Error('Respuesta no v\u00e1lida del servidor (sin permiso o sesi\u00f3n caducada)');
            }
            if (!d.success) throw new Error(d.error || 'Error desconocido');
            cont.innerHTML = window.pintarDiagnosticoBd(d);
            Swal.close();
        } catch (err) {
            var msg = String(err.message || err);
            cont.innerHTML = '<div class="alert alert-danger mb-0 py-2 small"><i class="fas fa-times-circle me-2"></i>' + window.escaparHtmlDiag(msg) + '</div>';
            Swal.fire({
                title: 'Diagn\u00f3stico fallido',
                text: msg,
                icon: 'error',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: 'var(--panel)',
                color: 'var(--txt)'
            });
        }
    };

    window.ejecutarReparacionBd = async function () {
        var cont = elRep();
        if (!cont) return;
        var opciones = {
            reparar_check: chk('repCheckErrores'),
            optimizar: chk('repOptimizar'),
            analyze: chk('repAnalizar'),
            indices_fk: chk('repIndicesFk'),
            unificar_collations: chk('repUnificarCollations'),
            borrar_huerfanos: chk('repBorrarHuerfanos')
        };
        if (!Object.values(opciones).some(Boolean)) {
            Swal.fire({ title: 'Sin acciones', text: 'Marque al menos una opci\u00f3n de reparaci\u00f3n.', icon: 'warning', confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido', background: 'var(--panel)', color: 'var(--txt)' });
            return;
        }

        var confirmar = await Swal.fire({
            title: '<i class="fas fa-triangle-exclamation me-2 text-warning"></i> Reparar base de datos',
            html: '<div class="text-start small">' +
                '<p class="mb-2">Se ejecutar\u00e1n las siguientes acciones (modifican la BD):</p>' +
                '<ul class="mb-2 ps-3">' +
                (opciones.reparar_check ? '<li><i class="fas fa-database me-1"></i>CHECK TABLE + reparaci\u00f3n de tablas con errores</li>' : '') +
                (opciones.optimizar ? '<li><i class="fas fa-broom me-1"></i>OPTIMIZE de tablas fragmentadas (&ge; 5%)</li>' : '') +
                (opciones.analyze ? '<li><i class="fas fa-chart-simple me-1"></i>ANALYZE TABLE (estad&iacute;sticas)</li>' : '') +
                (opciones.indices_fk ? '<li><i class="fas fa-link me-1"></i>ADD INDEX de &iacute;ndices FK faltantes</li>' : '') +
                (opciones.unificar_collations ? '<li><i class="fas fa-language me-1"></i>Unificar colaciones de texto a la mayoritaria</li>' : '') +
                (opciones.borrar_huerfanos ? '<li><i class="fas fa-user-slash me-1"></i>Eliminar filas hu&eacute;rfanas (hijas sin padre)</li>' : '') +
                '</ul>' +
                '<div class="form-check"><input type="checkbox" class="form-check-input" id="chkBackupConfirm">' +
                '<label class="form-check-label" for="chkBackupConfirm"><i class="fas fa-circle-check me-1"></i>Tengo un backup reciente de la base de datos</label></div>' +
                '</div>',
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-wrench me-1"></i> Reparar',
            denyButtonText: '<i class="fas fa-download me-2"></i> Salvar BD',
            cancelButtonText: '<i class="fas fa-ban me-1"></i> Cancelar',
            background: 'var(--panel)',
            color: 'var(--txt)',
            preConfirm: function () {
                if (!document.getElementById('chkBackupConfirm') || !document.getElementById('chkBackupConfirm').checked) {
                    Swal.showValidationMessage('<i class="fas fa-circle-exclamation me-1"></i> Confirme que tiene un backup antes de reparar');
                    return false;
                }
                return true;
            }
        });
        if (confirmar.isDenied) {
            if (typeof accionSalvaOrdinaria === 'function') accionSalvaOrdinaria();
            return;
        }
        if (!confirmar.isConfirmed) return;

        cont.style.display = 'block';
        var swalRep = Swal.fire({
            title: 'Reparando la base de datos\u2026',
            html: '<p class="mb-0">Ejecutando las acciones seleccionadas.</p>' +
                  '<p class="small mb-0" style="color: var(--muted);">Esto puede tardar unos segundos\u2026</p>',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); },
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: 'var(--panel)',
            color: 'var(--txt)'
        });
        try {
            var fd = new FormData();
            Object.keys(opciones).forEach(function (k) { if (opciones[k]) fd.append(k, '1'); });
            var r = await fetch(window.mantBdUrl('reparar_bd'), { method: 'POST', body: fd, credentials: 'same-origin' });
            var t = await r.text();
            var d;
            try {
                d = JSON.parse(t.replace(/^\uFEFF/, '').trim());
            } catch (e) {
                throw new Error('Respuesta no v\u00e1lida del servidor (sin permiso o sesi\u00f3n caducada)');
            }
            if (!d.success) throw new Error(d.error || 'Error desconocido');
            cont.innerHTML = window.pintarReparacionBd(d);
            Swal.close();
            await window.ejecutarDiagnosticoBd();
            if ((d.resumen || {}).fallidas > 0) {
                Swal.fire({
                    title: 'Reparaci\u00f3n con errores',
                    html: '<p>' + d.resumen.fallidas + ' acci\u00f3n(es) fallaron. Revise los detalles en el panel.</p>',
                    icon: 'error',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    background: 'var(--panel)',
                    color: 'var(--txt)'
                });
            } else {
                Swal.fire({
                    title: 'Reparaci\u00f3n completada',
                    html: '<p>La base de datos fue reparada sin errores. El diagn\u00f3stico qued\u00f3 actualizado.</p>',
                    icon: 'success',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    background: 'var(--panel)',
                    color: 'var(--txt)'
                });
            }
        } catch (err) {
            var msg = String(err.message || err);
            cont.innerHTML = '<div class="alert alert-danger mb-0 py-2 small"><i class="fas fa-times-circle me-2"></i>' + window.escaparHtmlDiag(msg) + '</div>';
            Swal.fire({ title: 'Reparaci\u00f3n fallida', text: msg, icon: 'error', confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido', background: 'var(--panel)', color: 'var(--txt)' });
        }
    };

    window.cerrarOverlayMantenimiento = function () {
        var ov = document.getElementById('srMtoOverlay');
        if (ov) ov.remove();
    };

    window.accionDiagnosticoReparar = function () {
        if (typeof Swal !== 'undefined' && typeof Swal.close === 'function') Swal.close();
        if (document.getElementById('srMtoOverlay')) return;

        var ov = document.createElement('div');
        ov.className = 'sr-mto-overlay';
        ov.id = 'srMtoOverlay';
        ov.innerHTML = '' +
            '<div class="sr-mto" role="dialog" aria-label="Diagn\u00f3stico / Reparaci\u00f3n">' +
            '  <button type="button" class="sr-mto-close" onclick="cerrarOverlayMantenimiento();" title="Cerrar" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>' +
            '  <div class="sr-mto-titlebar">' +
            '    <div class="sr-mto-titlebar-icon"><i class="fas fa-stethoscope"></i></div>' +
            '    <div>' +
            '      <h2 class="sr-mto-title">Diagn\u00f3stico / Reparaci\u00f3n</h2>' +
            '      <p class="sr-mto-sub">Revise la integridad de la base de datos y repare lo que haga falta.</p>' +
            '    </div>' +
            '  </div>' +
            '  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">' +
            '    <div><h6 class="mb-1"><i class="fas fa-stethoscope me-1" style="color: #60a5fa;"></i> Diagn\u00f3stico (solo lectura)</h6>' +
            '    <small style="color: var(--muted);">CHECK TABLE, &iacute;ndices de claves for&aacute;neas, colaciones y filas hu&eacute;rfanas</small></div>' +
            '    <button type="button" class="btn-win btn-win-primary" id="mto_btnDiagnosticarBd">' +
            '      <i class="fas fa-magnifying-glass-chart me-2"></i> Diagnosticar' +
            '    </button>' +
            '  </div>' +
            '  <div id="mto_diagnosticoBdResultados" class="mt-3" style="display: none;"></div>' +
            '  <div class="mt-3 pt-3" style="border-top: 1px solid rgba(var(--blue-soft-rgb), .15);">' +
            '    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">' +
            '      <div><h6 class="mb-1"><i class="fas fa-wrench me-1" style="color: #f59e0b;"></i> Reparar Base de Datos</h6>' +
            '      <small style="color: var(--muted);">Mantenimiento que s&iacute; modifica la BD. Se recomienda backup previo.</small></div>' +
            '      <button type="button" class="btn-win btn-win-warning" id="mto_btnRepararBd">' +
            '        <i class="fas fa-wrench me-2"></i> Reparar' +
            '      </button>' +
            '    </div>' +
            '    <div class="sr-mto-checks small">' +
            '      <div class="form-check"><input class="form-check-input" type="checkbox" id="mto_repCheckErrores" checked>' +
            '        <label class="form-check-label" for="mto_repCheckErrores"><i class="fas fa-database me-1"></i>Reparar errores (CHECK)</label></div>' +
            '      <div class="form-check"><input class="form-check-input" type="checkbox" id="mto_repOptimizar" checked>' +
            '        <label class="form-check-label" for="mto_repOptimizar"><i class="fas fa-broom me-1"></i>Optimizar fragmentadas</label></div>' +
            '      <div class="form-check"><input class="form-check-input" type="checkbox" id="mto_repAnalizar" checked>' +
            '        <label class="form-check-label" for="mto_repAnalizar"><i class="fas fa-chart-simple me-1"></i>Actualizar estad&iacute;sticas</label></div>' +
            '      <div class="form-check"><input class="form-check-input" type="checkbox" id="mto_repIndicesFk" checked>' +
            '        <label class="form-check-label" for="mto_repIndicesFk"><i class="fas fa-link me-1"></i>&Iacute;ndices de claves for&aacute;neas</label></div>' +
            '      <div class="form-check"><input class="form-check-input" type="checkbox" id="mto_repUnificarCollations" checked>' +
            '        <label class="form-check-label" for="mto_repUnificarCollations"><i class="fas fa-language me-1"></i>Unificar colaciones</label></div>' +
            '      <div class="form-check"><input class="form-check-input" type="checkbox" id="mto_repBorrarHuerfanos">' +
            '        <label class="form-check-label" for="mto_repBorrarHuerfanos"><i class="fas fa-user-slash me-1"></i>Eliminar filas hu&eacute;rfanas</label></div>' +
            '    </div>' +
            '    <div id="mto_reparacionBdResultados" class="mt-3" style="display: none;"></div>' +
            '  </div>' +
            '  <div class="sr-mto-footer">' +
            '    <button type="button" class="btn-win btn-win-success" id="mto_btnSalvarBdMantenimiento">' +
            '      <i class="fas fa-download me-2"></i> Salvar BD' +
            '    </button>' +
            '    <button type="button" class="btn-win" onclick="cerrarOverlayMantenimiento();">' +
            '      <i class="fas fa-xmark me-2"></i> Cerrar' +
            '    </button>' +
            '  </div>' +
            '</div>';
        ov.addEventListener('click', function (e) { if (e.target === ov) cerrarOverlayMantenimiento(); });
        document.body.appendChild(ov);
        window.ejecutarDiagnosticoBd();
    };

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('#btnDiagnosticarBd, #mto_btnDiagnosticarBd')) { e.preventDefault(); window.ejecutarDiagnosticoBd(); return; }
        if (t.closest('#btnRepararBd, #mto_btnRepararBd')) { e.preventDefault(); window.ejecutarReparacionBd(); return; }
        if (t.closest('#btnSalvarBdMantenimiento, #mto_btnSalvarBdMantenimiento')) {
            e.preventDefault();
            if (typeof accionSalvaOrdinaria === 'function') accionSalvaOrdinaria();
            return;
        }
    });
}
