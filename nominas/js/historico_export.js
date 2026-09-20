/* ============================================================
   historico_export.js - Exportaciones del Histórico de Operaciones
   ------------------------------------------------------------
   Genera en el cliente (con TODOS los campos y sin paginación):
     - Excel  : XLSX real (ExcelJS) en horizontal, con estilos,
                auto-filtro, panel congelado y pie "Página X de Y".
     - Word   : .doc en horizontal (mso) con pie PAGE/NUMPAGES.
     - PDF    : jsPDF + autoTable en horizontal con "Página X de Y".
     - Imprimir: vista HTML paginada (Página X de Y) + window.print().
   Los datos se piden a exportar_historico.php (formato=json) con los
   mismos filtros que la página.
   ============================================================ */
(function () {
    'use strict';

    var CFG = window.HIST_EXPORT || {};
    var ENDPOINT = CFG.endpoint || 'exportar_historico.php';
    var TITULO = CFG.titulo || 'Histórico de Operaciones';
    var SISTEMA = CFG.sistema || 'Sistema SisGesNom®';
    var EMPRESA = CFG.empresa || 'SisGesNom';

    /* Líneas de cabecera de todos los documentos */
    function titulo1() { return TITULO.toUpperCase() + ' - ' + SISTEMA; }
    function titulo2() { return 'EMPRESA/ENTIDAD: ' + EMPRESA; }

    /* ------------------------------------------------------------
       Definición de columnas (todos los campos de audit_logs)
       ------------------------------------------------------------ */
    var COLS = [
        { k: 'idx',        l: 'N°' },
        { k: 'id',         l: 'ID' },
        { k: 'user_id',    l: 'ID Usuario' },
        { k: 'username',   l: 'Usuario' },
        { k: 'rol',        l: 'Rol' },
        { k: 'nombre',     l: 'Nombre y apellidos' },
        { k: 'user_email', l: 'Email' },
        { k: 'provider',   l: 'Proveedor' },
        { k: 'action',     l: 'Acción' },
        { k: 'module',     l: 'Módulo' },
        { k: 'desc',       l: 'Descripción' },
        { k: 'details',    l: 'Detalles (JSON)' },
        { k: 'ip',         l: 'IP' },
        { k: 'ua',         l: 'Navegador (User-Agent)' },
        { k: 'method',     l: 'Método' },
        { k: 'url',        l: 'URL' },
        { k: 'status',     l: 'Estado' },
        { k: 'error',      l: 'Error' },
        { k: 'fecha',      l: 'Fecha/Hora' },
        { k: 'hash',       l: 'Hash (cadena)' }
    ];
    var ANCHOS = [5, 7, 9, 18, 12, 26, 26, 12, 24, 16, 36, 40, 15, 32, 10, 30, 10, 26, 20, 22];
    var ANCHO_TOTAL = ANCHOS.reduce(function (a, b) { return a + b; }, 0);

    /* Reparto proporcional de anchos (para tablas de ancho fijo en
       impresión: evita que las columnas largas se desborden). */
    function colgroup() {
        return '<colgroup>' + ANCHOS.map(function (w) {
            return '<col style="width:' + (w / ANCHO_TOTAL * 100).toFixed(2) + '%">';
        }).join('') + '</colgroup>';
    }

    /* Word: anchos en pulgadas sobre el ancho útil (11in - 1in de
       márgenes = 10in). Word respeta los anchos en pulgadas; con
       porcentajes tiende a autoajustar y agranda la columna con
       contenido largo (URL, JSON...). */
    function colgroupWord() {
        return '<colgroup>' + ANCHOS.map(function (w) {
            return '<col style="width:' + (w / ANCHO_TOTAL * 10).toFixed(3) + 'in;mso-width-source:userset">';
        }).join('') + '</colgroup>';
    }

    /* ------------------------------------------------------------
       Utilidades
       ------------------------------------------------------------ */
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function z(n) { return ('0' + n).slice(-2); }

    function fmtFecha(s) {
        if (!s) return '';
        var p = String(s).split(/[- :]/);
        if (p.length < 3) return String(s);
        var d = new Date(+p[0], (+p[1]) - 1, +p[2], +(p[3] || 0), +(p[4] || 0), +(p[5] || 0));
        if (isNaN(d.getTime())) return String(s);
        var h = d.getHours();
        var ampm = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12; if (h12 === 0) h12 = 12;
        return z(d.getDate()) + '/' + z(d.getMonth() + 1) + '/' + d.getFullYear() +
               ' ' + z(h12) + ':' + z(d.getMinutes()) + ':' + z(d.getSeconds()) + ' ' + ampm;
    }

    function nombreArchivo(ext) {
        var d = new Date();
        return 'historico_operaciones_' + d.getFullYear() + z(d.getMonth() + 1) + z(d.getDate()) +
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
    function queryFiltros() {
        var p = new URLSearchParams(window.location.search);
        p.delete('pagina');
        p.delete('pag');
        p.delete('registros_por_pagina');
        p.delete('exportar');
        var s = p.toString();
        return s ? ('&' + s) : '';
    }

    function pedir(destino) {
        var url = ENDPOINT + '?formato=json&destino=' + encodeURIComponent(destino) + queryFiltros();
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

    function filasDe(datos) {
        return (datos.registros || []).map(function (r, i) {
            return [
                i + 1,
                r.id,
                r.user_id == null ? '' : r.user_id,
                (r.username || '') !== '' ? r.username : 'Sistema',
                r.usuario_rol || '',
                r.usuario_nombre || '',
                r.user_email || '',
                r.auth_provider || '',
                r.action_type || '',
                r.module || '',
                r.description || '',
                r.details || '',
                r.ip_address || '',
                r.user_agent || '',
                r.request_method || '',
                r.request_url || '',
                r.status || '',
                r.error_message || '',
                fmtFecha(r.created_at),
                r.hash_chain || ''
            ];
        });
    }

    /* Operador en formato "Nombre y apellidos (Rol)" */
    function operador(datos) {
        return datos.exportado_por || datos.operador || CFG.operador || datos.usuario || 'Sistema';
    }

    function resumen(datos) {
        return 'Generado: ' + (datos.generado || fmtFecha(new Date().toISOString())) +
               '   ·   Exportado por: ' + operador(datos) +
               '   ·   Total de registros: ' + (datos.total || 0);
    }

    /* ------------------------------------------------------------
       EXCEL (ExcelJS) — XLSX horizontal con pie de página
       ------------------------------------------------------------ */
    function exportarExcel(datos) {
        if (typeof ExcelJS === 'undefined') {
            aviso('error', 'Error', 'La librería ExcelJS no cargó.');
            return;
        }
        var filas = filasDe(datos);
        var ncols = COLS.length;
        var AZUL = 'FF004B87';
        var GRIS = 'FFF5F8FC';

        var wb = new ExcelJS.Workbook();
        wb.creator = EMPRESA;
        wb.created = new Date();

        var ws = wb.addWorksheet('Histórico', {
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
            ws.getColumn(cw + 1).width = ANCHOS[cw] || 16;
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

        // Título (filas 1-3)
        celda(1, 1, titulo1(), { bold: true, size: 13, align: 'center', fill: AZUL, color: 'FFFFFFFF' });
        ws.mergeCells(1, 1, 1, ncols);
        ws.getRow(1).height = 24;

        celda(2, 1, titulo2(), { bold: true, size: 10, align: 'center', color: 'FF333333' });
        ws.mergeCells(2, 1, 2, ncols);

        celda(3, 1, resumen(datos) + '   ·   ' + (CFG.filtros || 'Sin filtros aplicados'), { size: 8.5, align: 'center', color: 'FF666666' });
        ws.mergeCells(3, 1, 3, ncols);
        ws.getRow(4).height = 6;

        // Encabezado (fila 5)
        var filaHeader = 5;
        for (var hi = 0; hi < ncols; hi++) {
            celda(filaHeader, hi + 1, COLS[hi].l, { bold: true, size: 9, align: 'center', fill: AZUL, color: 'FFFFFFFF', border: true, wrap: true });
        }
        ws.getRow(filaHeader).height = 30;

        // Datos (todas las celdas con ajuste de texto y alto calculado)
        var f = filaHeader + 1;
        filas.forEach(function (fila) {
            var maxLineas = 1;
            for (var ci = 0; ci < ncols; ci++) {
                var clave = COLS[ci].k;
                celda(f, ci + 1, fila[ci], {
                    align: (clave === 'idx' || clave === 'id' || clave === 'user_id' || clave === 'ip' || clave === 'status') ? 'center' : 'left',
                    size: 8.5,
                    wrap: true,
                    border: true,
                    fill: (f % 2 === 0 ? GRIS : null)
                });
                var txt = String(fila[ci] == null ? '' : fila[ci]);
                var lineas = (txt.indexOf('\n') === -1)
                    ? Math.ceil(txt.length / Math.max(4, ANCHOS[ci]))
                    : txt.split('\n').length;
                if (lineas > maxLineas) { maxLineas = lineas; }
            }
            ws.getRow(f).height = Math.min(Math.max(15, maxLineas * 12), 409);
            f++;
        });

        // Auto-filtro sobre el encabezado
        ws.autoFilter = { from: { row: filaHeader, column: 1 }, to: { row: filaHeader, column: ncols } };
        // Repetir encabezado y título al imprimir
        ws.pageSetup.printTitlesRow = '1:' + filaHeader;
        // Numeración de página en el pie
        ws.headerFooter = {
            oddFooter: '&C&"Arial,Regular"&8Página &P de &N'
        };

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
        var filas = filasDe(datos);
        var anchos = ANCHOS.map(function (w) { return (w / ANCHO_TOTAL * 10).toFixed(3) + 'in'; });
        var thead = COLS.map(function (c, i) {
            return '<th style="width:' + anchos[i] + '">' + esc(c.l) + '</th>';
        }).join('');
        var tbody = filas.map(function (fila) {
            return '<tr>' + fila.map(function (v, i) {
                return '<td style="width:' + anchos[i] + '">' + esc(v) + '</td>';
            }).join('') + '</tr>';
        }).join('');

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
            'table { border-collapse: collapse; width: 10in; table-layout: fixed; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }' +
            'th, td { border: 1px solid #666; padding: 2pt 3pt; font-size: 7pt; vertical-align: top; word-wrap: break-word; word-break: break-all; overflow-wrap: anywhere; }' +
            'th { background: #004B87; color: #fff; text-align: center; }' +
            'p.MsoFooter { font-family: Arial, sans-serif; font-size: 8pt; text-align: center; }' +
            '</style></head><body><div class="WordSection1">' +
            '<h1>' + esc(titulo1()) + '</h1>' +
            '<p class="emp">' + esc(titulo2()) + '</p>' +
            '<p class="sub">' + esc(resumen(datos)) + '</p>' +
            '<p class="sub">' + esc(CFG.filtros || 'Sin filtros aplicados') + '</p>' +
            '<table>' + colgroupWord() + '<thead><tr>' + thead + '</tr></thead><tbody>' + tbody + '</tbody></table>' +
            '</div>' +
            '<div style="mso-element:footer" id="f1"><p class="MsoFooter">' +
            'Página <span style="mso-field-code:PAGE"></span> de <span style="mso-field-code:NUMPAGES"></span>' +
            '</p></div>' +
            '</body></html>';

        descargar(new Blob(['\ufeff', html], { type: 'application/msword' }), nombreArchivo('doc'));
    }

    /* ------------------------------------------------------------
       PDF (jsPDF + autoTable) con numeración de página
       ---
       Con 18 columnas el PDF quedaba ilegible. Se agrupan campos
       afines (usuario+email, acción+módulo, método+URL, estado+error,
       proveedor+IP) conservando TODOS los datos, y se reparten
       anchos proporcionales reales sobre el ancho útil.
       ------------------------------------------------------------ */
    var PDF_COLS = [
        { l: 'N°',              w: 3.5, align: 'center', get: function (r, i) { return i + 1; } },
        { l: 'ID',              w: 5,   align: 'center', get: function (r) { return r.id; } },
        { l: 'Fecha / Hora',    w: 9,   align: 'left',   get: function (r) { return fmtFecha(r.created_at); } },
        { l: 'Usuario',         w: 13,  align: 'left',   get: function (r) { return (r.username || 'Sistema') + (r.user_email ? '\n' + r.user_email : ''); } },
        { l: 'Rol',             w: 7,   align: 'left',   get: function (r) { return r.usuario_rol || ''; } },
        { l: 'Nombre y apellidos', w: 12, align: 'left', get: function (r) { return r.usuario_nombre || ''; } },
        { l: 'Proveedor / IP',  w: 9,   align: 'left',   get: function (r) { return (r.auth_provider || '') + (r.ip_address ? '\n' + r.ip_address : ''); } },
        { l: 'Acción / Módulo', w: 12,  align: 'left',   get: function (r) { return (r.action_type || '') + (r.module ? '\n[' + r.module + ']' : ''); } },
        { l: 'Descripción',     w: 20,  align: 'left',   get: function (r) { return r.description || ''; } },
        { l: 'Detalles (JSON)', w: 17,  align: 'left',   get: function (r) { return r.details || ''; } },
        { l: 'Navegador',       w: 10,  align: 'left',   get: function (r) { return r.user_agent || ''; } },
        { l: 'Método / URL',    w: 13,  align: 'left',   get: function (r) { return (r.request_method || '') + (r.request_url ? '\n' + r.request_url : ''); } },
        { l: 'Estado / Error',  w: 8,   align: 'left',   get: function (r) { return (r.status || '') + (r.error_message ? '\n' + r.error_message : ''); } },
        { l: 'Hash',            w: 10,  align: 'left',   get: function (r) { return r.hash_chain || ''; } }
    ];

    function construirPDF(datos) {
        if (typeof window.jspdf === 'undefined') {
            throw new Error('La librería PDF no cargó.');
        }
        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'letter' });
        var pageW = doc.internal.pageSize.getWidth();
        var pageH = doc.internal.pageSize.getHeight();
        var regs = datos.registros || [];
        var margen = 16;

        var headers = PDF_COLS.map(function (c) { return c.l; });
        var body = regs.map(function (r, i) {
            return PDF_COLS.map(function (c) { return c.get(r, i); });
        });

        // Reparto proporcional de anchos (puntos) sobre el ancho útil
        var usable = pageW - margen * 2;
        var sumaW = PDF_COLS.reduce(function (a, c) { return a + c.w; }, 0);
        var columnStyles = {};
        PDF_COLS.forEach(function (c, i) {
            columnStyles[i] = { cellWidth: c.w / sumaW * usable, halign: c.align };
        });

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11.5);
        doc.text(titulo1(), pageW / 2, 26, { align: 'center' });
        doc.setFontSize(9.5);
        doc.text(titulo2(), pageW / 2, 39, { align: 'center' });
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.text(resumen(datos), pageW / 2, 50, { align: 'center' });
        doc.text(CFG.filtros || 'Sin filtros aplicados', pageW / 2, 60, { align: 'center' });

        doc.autoTable({
            head: [headers],
            body: body,
            startY: 68,
            theme: 'grid',
            styles: { fontSize: 6.8, cellPadding: 3, overflow: 'linebreak', textColor: [0, 0, 0], lineColor: [160, 160, 160], lineWidth: 0.4 },
            headStyles: { fillColor: [0, 75, 135], textColor: [255, 255, 255], fontStyle: 'bold', fontSize: 7, halign: 'center', valign: 'middle' },
            alternateRowStyles: { fillColor: [245, 248, 252] },
            columnStyles: columnStyles,
            margin: { left: margen, right: margen, top: 72, bottom: 30 },
            didDrawPage: function () {
                doc.setFontSize(6.5);
                doc.setTextColor(120);
                doc.text(TITULO + ' · ' + EMPRESA, margen, pageH - 14);
                doc.text('Generado: ' + (datos.generado || ''), pageW - margen, pageH - 14, { align: 'right' });
                doc.setTextColor(0);
            }
        });

        var total = doc.internal.getNumberOfPages();
        for (var i = 1; i <= total; i++) {
            doc.setPage(i);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.text('Página ' + i + ' de ' + total, pageW / 2, pageH - 14, { align: 'center' });
        }
        return doc;
    }

    function exportarPDF(datos) {
        try {
            construirPDF(datos).save(nombreArchivo('pdf'));
        } catch (e) {
            aviso('error', 'Error', e.message || 'No se pudo generar el PDF.');
        }
    }

    /* ------------------------------------------------------------
       IMPRIMIR — vista HTML paginada con "Página X de Y"
       La ventana incluye la barra de herramientas autoescondible
       (se oculta al desplazarse hacia abajo) y no se imprime.
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
        var filas = filasDe(datos);
        var porPagina = 15;
        var totalPag = Math.ceil(filas.length / porPagina) || 1;
        var thead = COLS.map(function (c) { return '<th>' + esc(c.l) + '</th>'; }).join('');

        var paginas = '';
        for (var p = 0; p < totalPag; p++) {
            var trozo = filas.slice(p * porPagina, (p + 1) * porPagina);
            var cuerpo = trozo.map(function (fila) {
                return '<tr>' + fila.map(function (v) { return '<td>' + esc(v) + '</td>'; }).join('') + '</tr>';
            }).join('');
            paginas += '<div class="pg">' +
                '<div class="h1">' + esc(titulo1()) + '</div>' +
                '<div class="emp">' + esc(titulo2()) + '</div>' +
                '<div class="sub">' + esc(resumen(datos)) + '</div>' +
                '<div class="sub">' + esc(CFG.filtros || 'Sin filtros aplicados') + '</div>' +
                '<table>' + colgroup() + '<thead><tr>' + thead + '</tr></thead><tbody>' + cuerpo + '</tbody></table>' +
                '<div class="foot">Página ' + (p + 1) + ' de ' + totalPag + '</div>' +
                '</div>';
        }

        var html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">' +
            '<title>' + esc(TITULO) + '</title><style>' +
            '@page { size: letter landscape; margin: 8mm; }' +
            'body { font-family: Arial, sans-serif; color: #111; margin: 0; }' +
            '.pg { page-break-after: always; } .pg:last-child { page-break-after: auto; }' +
            '.h1 { font-size: 12pt; font-weight: bold; text-align: center; margin: 0 0 1mm; }' +
            '.emp { font-size: 10pt; font-weight: bold; text-align: center; margin: 0 0 1mm; }' +
            '.sub { font-size: 7pt; text-align: center; color: #444; margin: 0 0 1mm; }' +
            'table { border-collapse: collapse; width: 100%; margin-top: 2mm; table-layout: fixed; }' +
            'th, td { border: 1px solid #999; padding: 1.5px 3px; font-size: 6pt; vertical-align: top; word-break: break-word; overflow-wrap: anywhere; }' +
            'th { background: #004B87; color: #fff; text-align: center; }' +
            '.foot { font-size: 7pt; text-align: center; margin-top: 2mm; color: #333; }' +
            '@media print { .no-print { display: none !important; } }' +
            '</style></head><body>' +
            toolbarHTML() + paginas +
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
       Comprobación de librerías por formato
       ------------------------------------------------------------ */
    function exportar(tipo) {
        pedir(tipo).then(function (datos) {
            if (!datos.total) {
                aviso('info', 'Sin registros', 'No hay registros para exportar con los filtros actuales.');
                return;
            }
            if (tipo === 'csv' || tipo === 'txt') {
                // Descarga directa desde el servidor (con todos los campos)
                var url = ENDPOINT + '?formato=' + tipo + '&destino=' + tipo + queryFiltros();
                window.location.href = url;
                return;
            }
            if (tipo === 'excel') { exportarExcel(datos); return; }
            if (tipo === 'word') { exportarWord(datos); return; }
            if (tipo === 'pdf') { exportarPDF(datos); return; }
            if (tipo === 'print') { imprimir(datos); return; }
        }).catch(function (e) {
            aviso('error', 'Error de exportación', e.message || 'No se pudo completar la exportación.');
        });
    }

    window.HIST_EXPORT = window.HIST_EXPORT || {};
    window.HIST_EXPORT.exportar = exportar;
    window.HIST_EXPORT.exportarExcel = function () { exportar('excel'); };
    window.HIST_EXPORT.exportarWord = function () { exportar('word'); };
    window.HIST_EXPORT.exportarPDF = function () { exportar('pdf'); };
    window.HIST_EXPORT.imprimir = function () { exportar('print'); };
    window.HIST_EXPORT.exportarCSV = function () { exportar('csv'); };
    window.HIST_EXPORT.exportarTXT = function () { exportar('txt'); };

    /* ------------------------------------------------------------
       Cableado de los ítems con data-export
       ------------------------------------------------------------ */
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-export]');
        if (!el) return;
        e.preventDefault();
        exportar(el.getAttribute('data-export'));
    });
})();
