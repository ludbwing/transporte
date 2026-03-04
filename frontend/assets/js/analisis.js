// assets/js/analisis.js
let analisisActivo = false;
let intervalProgress;

$(document).ready(function() {
    // Validar archivo antes de subir
    $('#archivo').on('change', function() {
        const file = this.files[0];
        if(file) {
            const tipo = $('#modo').val();
            const extension = file.name.split('.').pop().toLowerCase();
            
            if(tipo === 'video') {
                const validExtensions = ['mp4', 'avi', 'mov', 'mkv'];
                if(!validExtensions.includes(extension)) {
                    alert('Formato de video no válido. Use: MP4, AVI, MOV');
                    $(this).val('');
                }
            } else if(tipo === 'imagen') {
                const validExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if(!validExtensions.includes(extension)) {
                    alert('Formato de imagen no válido. Use: JPG, PNG, GIF');
                    $(this).val('');
                }
            }
        }
    });
});

function iniciarAnalisis() {
    if(!$('#direccion').val()) {
        alert('Por favor ingrese la dirección');
        return false;
    }
    
    if(!$('#modo').val()) {
        alert('Por favor seleccione el modo de análisis');
        return false;
    }
    
    analisisActivo = true;
    return true;
}

function cancelarAnalisis() {
    if(confirm('¿Está seguro de cancelar el análisis?')) {
        analisisActivo = false;
        clearInterval(intervalProgress);
        window.location.reload();
    }
}