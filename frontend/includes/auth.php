<?php
// includes/auth.php
function verificarAcceso($rol_requerido = 'usuario') {
    if(!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
    
    // Si se requiere admin y el usuario no es admin
    if($rol_requerido === 'admin' && $_SESSION['rol'] !== 'admin') {
        header("Location: dashboard.php?error=acceso_denegado");
        exit();
    }
}

function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function esUsuario() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'usuario';
}

function menuItem($url, $icono, $texto, $roles_permitidos = ['usuario', 'admin']) {
    if(in_array($_SESSION['rol'], $roles_permitidos)) {
        $activo = (basename($_SERVER['PHP_SELF']) == $url) ? 'active' : '';
        echo "<a href='$url' class='$activo'><i class='$icono'></i><span>$texto</span></a>";
    }
}
?>