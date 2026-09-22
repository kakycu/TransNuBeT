/* ============================================================
   configuracion_export.js - Exportaciones de la Configuración
   ------------------------------------------------------------
   Genera en el cliente (con TODOS los datos de configuración):
     - Excel  : XLSX real (ExcelJS), con UNA hoja por sección
                (Configuración General / Rangos / Tasas).
     - PDF    : jsPDF + autoTable, con una tabla por sección.
     - Imprimir: vista HTML paginada (Página X de Y) + window.print().
   Los datos se piden a exportar_configuracion.php (formato=json).
   CSV y TXT se descargan directamente desde el servidor.
   ============================================================ */
(function () {
    'use strict';

    var CFG = window.CONFIG_EXPORT || {};
    var ENDPOINT = CFG.endpoint || 'exportar_configuracion.php';
    var TITULO = CFG.titulo || 'Configuración del Sistema';
    var SISTEMA = CFG.sistema || 'Sistema SisGesNom®';
    var EMPRESA = CFG.empresa || 'SisGesNom';

    /* Anchos por columna (índice dependiente de cada sección) */
    var ANCHOS_POR_SECCION = {
        general: [5, 26, 34, 12, 52],
        rangos: [5, 12, 12, 10, 12, 14, 40],
        tasas: [5, 24, 12, 14, 48]
    };
    var ANCHO_DEFECTO = 20;
    var ANCHO_TOTAL = 128;

    /* Línea de cabecera de los documentos */
    function titulo1() { return TITULO.toUpperCase() + ' - ' + SISTEMA; }
    function titulo2() { return 'EMPRESA/ENTIDAD: ' + EMPRESA; }

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function z(n) { return ('0' + n).slice(-2); }

    function nombreArchivo(ext) {
        var d = new Date();
        return 'configuracion_del_sistema_' + d.getFullYear() + z(d.getMonth() + 1) + z(d.getDate()) +
               '_' + z(d.getHours()) + z(d.getMinutes()) + z(d.getSeconds()) + '.' + ext;
    }

    function descargar(blob, nombre) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
    }

    function aviso(icon, titulo, texto) {
        if (window.Swal) {
            Swal.fire({
                icon: icon,
                title: titulo,
                text: texto,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            });
        } else {
            window.alert(titulo + ': ' + (texto || ''));
        }
    }

    /* ------------------------------------------------------------
       Datos
       ------------------------------------------------------------ */
    function pedir(tipo) {
        var url = ENDPOINT + '?formato=json&destino=' + encodeURIComponent(tipo);
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (d) {
                if (!d || !d.success) throw new Error((d && d.message) || 'No se pudieron obtener los datos.');
                return d;
            });
    }

    function anchosDe(seccion) {
        var list = ANCHOS_POR_SECCION[seccion.id] || [];
        var total = 0;
        var n = (seccion.columnas || []).length;
        for (var i = 0; i < n; i++) {
            total += (list[i] || ANCHO_DEFECTO);
        }
        if (!total) total = ANCHO_TOTAL;
        var pct = [];
        for (var j = 0; j < n; j++) {
            pct.push(((list[j] || ANCHO_DEFECTO) / total) * 100);
        }
        return { pct: pct, list: list, total: total };
    }

    /* Reparto proporcional para impresión (ancho fijo) */
    function colgroup(seccion) {
        var datos = anchosDe(seccion);
        return '<colgroup>' + datos.pct.map(function (w) {
            return '<col style="width:' + w.toFixed(2) + '%">';
        }).join('') + '</colgroup>';
    }

    function resumen(datos) {
        return 'Generado: ' + (datos.generado || '') +
               '   ·   Exportado por: ' + (datos.exportado_por || 'Sistema') +
               '   ·   Total de filas: ' + (datos.total || 0);
    }

    /* ------------------------------------------------------------
       EXCEL (ExcelJS) — XLSX con una hoja por sección
       ------------------------------------------------------------ */
    function exportarExcel(datos) {
        if (typeof ExcelJS === 'undefined') {
            aviso('error', 'Error', 'La librería ExcelJS no cargó.');
            return;
        }
        var secciones = datos.secciones || [];
        if (!secciones.length) {
            aviso('info', 'Sin datos', 'No hay datos de configuración para exportar.');
            return;
        }
        var AZUL = 'FF004B87';
        var GRIS = 'FFF5F8FC';

        var wb = new ExcelJS.Workbook();
        wb.creator = EMPRESA;
        wb.created = new Date();

        secciones.forEach(function (sec) {
            var ncols = (sec.columnas || []).length;
            var anchos = ANCHOS_POR_SECCION[sec.id] || [];
            var ws = wb.addWorksheet((sec.id === 'general' ? 'Config. General' : sec.id === 'rangos' ? 'Rangos Impuesto' : 'Tasas'), {
                pageSetup: {
                    orientation: 'landscape',
                    fitToPage: true,
                    fitToWidth: 1,
                    fitToHeight: 0,
                    paperSize: 1,
                    margins: { left: 0.35, right: 0.35, top: 0.6, bottom: 0.6, header: 0.3, footer: 0.3 }
                },
                properties: { tabColor: { argb: AZUL } }
            });
            ws.views = [{ state: 'frozen', ySplit: 5 }];

            for (var cw = 0; cw < ncols; cw++) {
                ws.getColumn(cw + 1).width = anchos[cw] || ANCHO_DEFECTO;
            }

            function celda(fila, col, valor, opts) {
                opts = opts || {};
                var c = ws.getCell(fila, col);
                c.value = valor;
                c.font = {
                    name: 'Arial',
                    size: opts.size || 9,
                    bold: !!opts.bold,
                    color: opts.color ? { argb: opts.color } : { argb: 'FF111111' }
                };
                c.alignment = { horizontal: opts.align || 'left', vertical: 'middle', wrapText: !!opts.wrap };
                if (opts.fill) c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: opts.fill } };
                if (opts.border) c.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
                return c;
            }

            celda(1, 1, titulo1(), { bold: true, size: 13, align: 'center', fill: AZUL, color: 'FFFFFFFF' });
            ws.mergeCells(1, 1, 1, ncols);
            ws.getRow(1).height = 24;

            celda(2, 1, titulo2(), { bold: true, size: 10, align: 'center', color: 'FF333333' });
            ws.mergeCells(2, 1, 2, ncols);

            var filaSec = 3;
            celda(filaSec, 1, sec.titulo, { bold: true, size: 10, align: 'center', color: 'FF666666' });
            ws.mergeCells(filaSec, 1, filaSec, ncols);
            filaSec++;

            celda(filaSec, 1, resumen(datos), { size: 8.5, align: 'center', color: 'FF666666' });
            ws.mergeCells(filaSec, 1, filaSec, ncols);
            var filaHeader = filaSec + 2;

            for (var hi = 0; hi < ncols; hi++) {
                celda(filaHeader, hi + 1, sec.columnas[hi], { bold: true, size: 9, align: 'center', fill: AZUL, color: 'FFFFFFFF', border: true, wrap: true });
            }
            ws.getRow(filaHeader).height = 30;

            var f = filaHeader + 1;
            (sec.filas || []).forEach(function (fila) {
                var maxLineas = 1;
                for (var ci = 0; ci < ncols; ci++) {
                    celda(f, ci + 1, fila[ci], {
                        align: ci === 0 ? 'center' : 'left',
                        size: 8.5,
                        wrap: true,
                        border: true,
                        fill: (f % 2 === 0 ? GRIS : null)
                    });
                    var txt = String(fila[ci] == null ? '' : fila[ci]);
                    var lineas = (txt.indexOf('\n') === -1)
                        ? Math.ceil(txt.length / Math.max(4, anchos[ci] || ANCHO_DEFECTO))
                        : txt.split('\n').length;
                    if (lineas > maxLineas) { maxLineas = lineas; }
                }
                ws.getRow(f).height = Math.min(Math.max(15, maxLineas * 12), 409);
                f++;
            });

            ws.autoFilter = { from: { row: filaHeader, column: 1 }, to: { row: filaHeader, column: ncols } };
            ws.pageSetup.printTitlesRow = '1:' + filaHeader;
            ws.headerFooter = {
                oddFooter: '&C&"Arial,Regular"&8Página &P de &N'
            };
        });

        wb.xlsx.writeBuffer().then(function (buffer) {
            descargar(new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), nombreArchivo('xlsx'));
        }).catch(function () {
            aviso('error', 'Error', 'No se pudo generar el archivo Excel.');
        });
    }

    /* ------------------------------------------------------------
       WORD — .doc horizontal con pie PAGE/NUMPAGES
       ------------------------------------------------------------ */
    function exportarWord(datos) {
        var secciones = datos.secciones || [];
        if (!secciones.length) {
            aviso('info', 'Sin datos', 'No hay datos de configuración para exportar.');
            return;
        }

        var cuerpo = '';
        secciones.forEach(function (sec) {
            var anchos = ANCHOS_POR_SECCION[sec.id] || [];
            var list = sec.columnas || [];
            var total = 0;
            for (var i = 0; i < list.length; i++) { total += (anchos[i] || ANCHO_DEFECTO); }
            if (!total) total = ANCHO_TOTAL;
            var anchosWord = list.map(function (_, i) {
                return ((anchos[i] || ANCHO_DEFECTO) / total * 10).toFixed(3) + 'in';
            });

            var thead = list.map(function (c, i) {
                return '<th style="width:' + anchosWord[i] + '">' + esc(c) + '</th>';
            }).join('');
            var tbody = (sec.filas || []).map(function (fila) {
                return '<tr>' + fila.map(function (v, i) {
                    return '<td style="width:' + anchosWord[i] + '">' + esc(v) + '</td>';
                }).join('') + '</tr>';
            }).join('');

            cuerpo += '<p class="sec">' + esc(sec.titulo) + '</p>' +
                '<table><colgroup>' + anchosWord.map(function (w, i) {
                    return '<col style="width:' + w + ';mso-width-source:userset">';
                }).join('') + '</colgroup>' +
                '<thead><tr>' + thead + '</tr></thead><tbody>' + tbody + '</tbody></table>';
        });

        var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" ' +
            'xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">' +
            '<head><meta charset="utf-8"><title>' + esc(TITULO) + '</title>' +
            '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom>' +
            '<w:DoNotOptimizeForBrowser/><w:PageSetup><w:Orientation>Landscape</w:Orientation>' +
            '<w:PageWidth>11in</w:PageWidth><w:PageHeight>8.5in</w:PageHeight>' +
            '<w:Margins><w:Top>0.5in</w:Top><w:Bottom>0.5in</w:Bottom><w:Left>0.5in</w:Left><w:Right>0.5in</w:Right></w:Margins>' +
            '</w:PageSetup></w:WordDocument></xml><![endif]-->' +
            '<style>' +
            '@page WordSection1 { size: 11in 8.5in; mso-page-orientation: landscape; margin: 0.5in; mso-footer: f1; }' +
            'div.WordSection1 { page: WordSection1; }' +
            'body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }' +
            'h1 { font-size: 13pt; text-align: center; margin: 0 0 2pt; }' +
            '.emp { text-align: center; font-size: 10pt; font-weight: bold; margin: 0 0 2pt; }' +
            '.sub { text-align: center; font-size: 8pt; color: #444; margin: 0 0 6pt; }' +
            '.sec { font-size: 9pt; font-weight: bold; color: #004B87; margin: 6pt 0 2pt; }' +
            'table { border-collapse: collapse; width: 10in; table-layout: fixed; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }' +
            'th, td { border: 1px solid #666; padding: 2pt 3pt; font-size: 7pt; vertical-align: top; word-wrap: break-word; word-break: break-all; overflow-wrap: anywhere; }' +
            'th { background: #004B87; color: #fff; text-align: center; }' +
            'p.MsoFooter { font-family: Arial, sans-serif; font-size: 8pt; text-align: center; }' +
            '</style></head><body><div class="WordSection1">' +
            '<h1>' + esc(titulo1()) + '</h1>' +
            '<p class="emp">' + esc(titulo2()) + '</p>' +
            '<p class="sub">' + esc(resumen(datos)) + '</p>' +
            cuerpo +
            '</div>' +
            '<div style="mso-element:footer" id="f1"><p class="MsoFooter">' +
            'Página <span style="mso-field-code:PAGE"></span> de <span style="mso-field-code:NUMPAGES"></span>' +
            '</p></div>' +
            '</body></html>';

        descargar(new Blob(['\ufeff', html], { type: 'application/msword' }), nombreArchivo('doc'));
    }

    /* ------------------------------------------------------------
       PDF (jsPDF + autoTable) con numeración de página
       ------------------------------------------------------------ */
    function exportarPDF(datos) {
        if (typeof window.jspdf === 'undefined') {
            aviso('error', 'Error', 'La librería PDF no cargó.');
            return;
        }
        try {
            var jsPDF = window.jspdf.jsPDF;
            var doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'letter' });
            var pageW = doc.internal.pageSize.getWidth();
            var pageH = doc.internal.pageSize.getHeight();
            var margen = 16;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.text(titulo1(), pageW / 2, 26, { align: 'center' });
            doc.setFontSize(9.5);
            doc.text(titulo2(), pageW / 2, 40, { align: 'center' });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7.5);
            doc.text(resumen(datos), pageW / 2, 52, { align: 'center' });

            var startY = 64;
            var usable = pageW - margen * 2;

            (datos.secciones || []).forEach(function (sec) {
                if (startY > pageH - 80) {
                    doc.addPage();
                    startY = 30;
                }
                // Encabezado de sección
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.setTextColor(0, 75, 135);
                doc.text(sec.titulo, margen, startY + 8);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                doc.setTextColor(0);

                doc.autoTable({
                    head: [(sec.columnas || [])],
                    body: (sec.filas || []),
                    startY: startY + 14,
                    theme: 'grid',
                    styles: { fontSize: 7, cellPadding: 3, overflow: 'linebreak', textColor: [0, 0, 0], lineColor: [160, 160, 160], lineWidth: 0.4 },
                    headStyles: { fillColor: [0, 75, 135], textColor: [255, 255, 255], fontStyle: 'bold', fontSize: 7.5, halign: 'center', valign: 'middle' },
                    alternateRowStyles: { fillColor: [245, 248, 252] },
                    columnStyles: (function () {
                        var anchos = ANCHOS_POR_SECCION[sec.id] || [];
                        var total = anchos.reduce(function (a, b) { return a + b; }, 0) || 1;
                        var st = {};
                        (sec.columnas || []).forEach(function (_, i) {
                            st[i] = { cellWidth: ((anchos[i] || ANCHO_DEFECTO) / total * usable) };
                        });
                        return st;
                    })(),
                    margin: { left: margen, right: margen, top: 72, bottom: 30 },
                    didDrawPage: function (data) {
                        doc.setFontSize(6.5);
                        doc.setTextColor(120);
                        doc.text(TITULO + ' · ' + EMPRESA, margen, pageH - 14);
                        doc.text('Generado: ' + (datos.generado || ''), pageW - margen, pageH - 14, { align: 'right' });
                        doc.setTextColor(0);
                    }
                });
                startY = doc.lastAutoTable.finalY + 22;
            });

            var total = doc.internal.getNumberOfPages();
            for (var i = 1; i <= total; i++) {
                doc.setPage(i);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.text('Página ' + i + ' de ' + total, pageW / 2, pageH - 14, { align: 'center' });
            }
            doc.save(nombreArchivo('pdf'));
        } catch (e) {
            aviso('error', 'Error', e.message || 'No se pudo generar el PDF.');
        }
    }

    /* ------------------------------------------------------------
       IMPRIMIR — vista HTML paginada con "Página X de Y"
       ------------------------------------------------------------ */
    function toolbarHTML() {
        return '<style>' +
            '#auto-hide-toolbar{transition:transform .3s ease}' +
            '#auto-hide-toolbar.hidden{transform:translateY(-100%)}' +
            '@media print{.no-print{display:none !important}}' +
            '</style>' +
            '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;">' +
            '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.03rem;">VISTA PREVIA DE IMPRESIÓN</span>' +
            '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">Imprimir</button>' +
            '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">Cerrar</button>' +
            '</div>' +
            '<div class="no-print" style="height:3.4375rem;"></div>' +
            '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||0,t=false;function ch(){if(!t){window.requestAnimationFrame(function(){var y=window.scrollY||document.documentElement.scrollTop||0;if(y>lastY&&y>60){tb.classList.add("hidden");}else{tb.classList.remove("hidden");}lastY=y;t=false;});t=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';
    }

    function imprimir(datos) {
        var secciones = datos.secciones || [];
        if (!secciones.length) {
            aviso('info', 'Sin datos', 'No hay datos de configuración para imprimir.');
            return;
        }

        var bloques = '';
        secciones.forEach(function (sec) {
            var filas = sec.filas || [];
            var porPagina = 26;
            var totalPag = Math.ceil(filas.length / porPagina) || 1;
            var thead = (sec.columnas || []).map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('');

            for (var p = 0; p < totalPag; p++) {
                var trozo = filas.slice(p * porPagina, (p + 1) * porPagina);
                var cuerpo = trozo.map(function (fila) {
                    return '<tr>' + fila.map(function (v) { return '<td>' + esc(v) + '</td>'; }).join('') + '</tr>';
                }).join('');
                bloques += '<div class="pg">' +
                    '<div class="h1">' + esc(titulo1()) + '</div>' +
                    '<div class="emp">' + esc(titulo2()) + '</div>' +
                    '<div class="sub">' + esc(resumen(datos)) + '</div>' +
                    '<div class="sec">' + esc(sec.titulo) + '</div>' +
                    '<table>' + colgroup(sec) + '<thead><tr>' + thead + '</tr></thead><tbody>' + cuerpo + '</tbody></table>' +
                    '<div class="foot">Página ' + (p + 1) + ' de ' + totalPag + ' (' + esc(sec.titulo) + ')</div>' +
                    '</div>';
            }
        });

        var html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">' +
            '<title>' + esc(TITULO) + '</title><style>' +
            '@page { size: letter landscape; margin: 8mm; }' +
            'body { font-family: Arial, sans-serif; color: #111; margin: 0; }' +
            '.pg { page-break-after: always; } .pg:last-child { page-break-after: auto; }' +
            '.h1 { font-size: 12pt; font-weight: bold; text-align: center; margin: 0 0 1mm; }' +
            '.emp { font-size: 10pt; font-weight: bold; text-align: center; margin: 0 0 1mm; }' +
            '.sec { font-size: 9pt; font-weight: bold; text-align: center; color: #004B87; margin: 0 0 1mm; }' +
            '.sub { font-size: 7pt; text-align: center; color: #444; margin: 0 0 1mm; }' +
            'table { border-collapse: collapse; width: 100%; margin-top: 2mm; table-layout: fixed; }' +
            'th, td { border: 1px solid #999; padding: 1.5px 3px; font-size: 7pt; vertical-align: top; word-break: break-word; overflow-wrap: anywhere; }' +
            'th { background: #004B87; color: #fff; text-align: center; }' +
            '.foot { font-size: 7pt; text-align: center; margin-top: 2mm; color: #333; }' +
            '@media print { .no-print { display: none !important; } }' +
            '</style></head><body>' +
            toolbarHTML() + bloques +
            '</body></html>';

        var w = window.open('', '_blank');
        if (!w) {
            aviso('warning', 'Ventana bloqueada', 'Permita las ventanas emergentes para imprimir.');
            return;
        }
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.focus();
    }

    /* ------------------------------------------------------------
       Comprobación de formato
       ------------------------------------------------------------ */
    function exportar(tipo) {
        if (tipo === 'csv' || tipo === 'txt') {
            var url = ENDPOINT + '?formato=' + tipo + '&destino=' + tipo;
            window.location.href = url;
            return;
        }
        pedir(tipo).then(function (datos) {
            if (tipo === 'excel') { exportarExcel(datos); return; }
            if (tipo === 'word') { exportarWord(datos); return; }
            if (tipo === 'pdf') { exportarPDF(datos); return; }
            if (tipo === 'print') { imprimir(datos); return; }
        }).catch(function (e) {
            aviso('error', 'Error de exportación', e.message || 'No se pudo completar la exportación.');
        });
    }

    window.CONFIG_EXPORT = window.CONFIG_EXPORT || {};
    window.CONFIG_EXPORT.exportar = exportar;
    window.CONFIG_EXPORT.exportarExcel = function () { exportar('excel'); };
    window.CONFIG_EXPORT.exportarWord = function () { exportar('word'); };
    window.CONFIG_EXPORT.exportarPDF = function () { exportar('pdf'); };
    window.CONFIG_EXPORT.imprimir = function () { exportar('print'); };
    window.CONFIG_EXPORT.exportarCSV = function () { exportar('csv'); };
    window.CONFIG_EXPORT.exportarTXT = function () { exportar('txt'); };

    /* Cableado de los ítems con data-export */
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-export]');
        if (!el) return;
        e.preventDefault();
        exportar(el.getAttribute('data-export'));
    });
})();