<?php
// includes/menu.php
function renderSidebar() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $rol = $_SESSION['rol'] ?? 'usuario';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-traffic-light"></i>
        <h3>VehiCount</h3>
        <p><?php echo $rol === 'admin' ? 'Administrador' : 'Usuario'; ?></p>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <?php if($rol === 'admin'): ?>
        <a href="analisis.php" class="<?php echo $current_page == 'analisis.php' ? 'active' : ''; ?>">
            <i class="fas fa-video"></i>
            <span>Nuevo Análisis</span>
        </a>
        <?php endif; ?>
        
        <a href="historial.php" class="<?php echo $current_page == 'historial.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Historial</span>
        </a>
        
        <a href="reportes.php" class="<?php echo $current_page == 'reportes.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reportes</span>
        </a>
        
        <?php if($rol === 'admin'): ?>
        <a href="configuracion.php" class="<?php echo $current_page == 'configuracion.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Configuración</span>
        </a>
        <?php endif; ?>
        
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </nav>
</div>
<?php
}

function renderHeader() {
    $nombre = $_SESSION['nombre'] ?? $_SESSION['username'];
    $rol = $_SESSION['rol'] ?? 'usuario';
    $inicial = strtoupper(substr($nombre, 0, 1));
?>
<div class="content-header">
    <div class="page-title">
        <h2><?php echo $GLOBALS['page_title'] ?? 'Sistema'; ?></h2>
        <p>Bienvenido, <strong><?php echo $nombre; ?></strong> 
           <span class="badge <?php echo $rol === 'admin' ? 'bg-warning' : 'bg-info'; ?>">
               <?php echo $rol === 'admin' ? 'Administrador' : 'Usuario'; ?>
           </span>
        </p>
    </div>
    <div class="user-menu">
        <div class="user-info">
            <div class="user-name"><?php echo $nombre; ?></div>
            <div class="user-role"><?php echo $rol === 'admin' ? 'Administrador' : 'Usuario Regular'; ?></div>
        </div>
        <div class="user-avatar">
            <?php echo $inicial; ?>
        </div>
    </div>
</div>
<?php
}
?>