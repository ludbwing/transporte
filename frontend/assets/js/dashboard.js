// assets/js/dashboard.js
$(document).ready(function() {
    // Actualizar datos cada 30 segundos
    setInterval(actualizarDashboard, 30000);
    
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});

function actualizarDashboard() {
    $.ajax({
        url: 'api/datos_recientes.php',
        type: 'GET',
        success: function(data) {
            // Actualizar estadísticas sin recargar página
            console.log('Datos actualizados');
        }
    });
}

function verDetalle(id) {
    window.location.href = 'ver_analisis.php?id=' + id;
}