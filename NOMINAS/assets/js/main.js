// assets/js/main.js
function showLoading() {
    if (!$('#loadingOverlay').length) {
        $('body').append('<div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"></div></div>');
    }
    $('#loadingOverlay').fadeIn(200);
}

function hideLoading() {
    $('#loadingOverlay').fadeOut(200);
}

function formatNumber(num, decimals = 2) {
    return num.toLocaleString('es-ES', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function formatCurrency(value) {
    return '$' + formatNumber(value, 2);
}

$(document).ready(function() {
    if ($.fn.dataTable && $('.datatable').length) {
        $('.datatable').each(function() {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    language: {
                        "decimal": "",
                        "emptyTable": "No hay datos disponibles",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                        "infoFiltered": "(filtrado de _MAX_ registros totales)",
                        "infoPostFix": "",
                        "thousands": ",",
                        "lengthMenu": "Mostrar _MENU_ registros",
                        "loadingRecords": "Cargando...",
                        "processing": "Procesando...",
                        "search": "Buscar:",
                        "zeroRecords": "No se encontraron registros coincidentes",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    pageLength: 25,
                    responsive: true
                });
            }
        });
    }
    
    $('[data-tooltip]').each(function() {
        $(this).attr('title', $(this).data('tooltip'));
    });
});

