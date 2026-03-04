// assets/js/historial.js
$(document).ready(function() {
    // Inicializar filtros con valores por defecto
    $('#fecha_desde').val(moment().subtract(30, 'days').format('YYYY-MM-DD'));
    $('#fecha_hasta').val(moment().format('YYYY-MM-DD'));
    
    // Event listeners para filtros
    $('#fecha_desde, #fecha_hasta, #filtro_tipo, #filtro_estado').on('change', function() {
        aplicarFiltros();
    });
});

function aplicarFiltros() {
    const params = new URLSearchParams();
    
    if($('#fecha_desde').val()) params.append('desde', $('#fecha_desde').val());
    if($('#fecha_hasta').val()) params.append('hasta', $('#fecha_hasta').val());
    if($('#filtro_tipo').val()) params.append('tipo', $('#filtro_tipo').val());
    if($('#filtro_estado').val()) params.append('estado', $('#filtro_estado').val());
    
    window.location.href = 'historial.php?' + params.toString();
}

function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'api/exportar_excel.php?' + params.toString();
}

function exportarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'api/exportar_pdf.php?' + params.toString();
}

function verDetalle(id) {
    window.location.href = 'ver_analisis.php?id=' + id;
}