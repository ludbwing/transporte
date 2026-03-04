<?php
// ver_analisis.php
session_start();
require_once 'includes/auth.php';
verificarAcceso('usuario');
require_once 'config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$database = new Database();
$db = $database->getConnection();

// Obtener información de la sesión (SIN usuario_id)
$query = "SELECT s.*, rc.nivel_general, rc.promedio_vehiculos, rc.max_vehiculos
          FROM sesiones s 
          LEFT JOIN resumen_congestion rc ON s.id = rc.id_sesion
          WHERE s.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$sesion = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$sesion) {
    header("Location: historial.php");
    exit();
}

// Obtener estadísticas por tipo
$query = "SELECT * FROM estadisticas_tipo WHERE id_sesion = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas por color
$query = "SELECT * FROM estadisticas_color WHERE id_sesion = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$colores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener muestras de congestión
$query = "SELECT * FROM muestras_congestion 
          WHERE id_sesion = :id 
          ORDER BY timestamp 
          LIMIT 100";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$muestras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Detalle de Análisis #<?php echo $id; ?> - Sistema de Detección de Congestión Vehicular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== VARIABLES GLOBALES - Mismos colores que el login y dashboard ===== */
        :root {
            /* Paleta de colores - Congestión vehicular */
            --primary-dark: #0b1a2e;
            --primary-medium: #1a3a5c;
            --primary-light: #2a4b7c;
            --accent-primary: #f39c12;
            --accent-secondary: #e67e22;
            --accent-dark: #c06b1a;
            
            /* Colores de tráfico */
            --traffic-low: #27ae60;
            --traffic-moderate: #f39c12;
            --traffic-high: #e74c3c;
            --traffic-heavy: #8e44ad;
            
            /* Tonos neutros */
            --bg-dark: #0b1a2e;
            --bg-light: #f0f4f8;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --hover-bg: #f8fafc;
            
            /* Dimensiones */
            --sidebar-width: 280px;
            --sidebar-width-mobile: 85%;
            --header-height: 70px;
            --border-radius: 20px;
            --border-radius-sm: 14px;
            --border-radius-xs: 10px;
            
            /* Sombras */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --shadow-accent: 0 4px 12px rgba(243, 156, 18, 0.3);
            
            /* Transiciones */
            --transition: all 0.3s ease;
        }

        /* ===== RESET Y ESTILOS BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
            -webkit-font-smoothing: antialiased;
            height: 100%;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-medium));
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.5;
            overflow-x: hidden;
            width: 100%;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        .app-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
            background: var(--bg-light);
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-medium) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
            left: 0;
            top: 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--accent-primary);
            border-radius: 4px;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            font-size: 52px;
            color: var(--accent-primary);
            margin-bottom: 12px;
            filter: drop-shadow(0 4px 8px rgba(243, 156, 18, 0.3));
        }

        .sidebar-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .sidebar-header p {
            font-size: 13px;
            opacity: 0.7;
            margin-top: 6px;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 4px solid transparent;
            font-size: 14px;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar-nav a.active {
            background: rgba(243, 156, 18, 0.15);
            color: white;
            border-left-color: var(--accent-primary);
        }

        .sidebar-nav i {
            width: 24px;
            font-size: 18px;
            margin-right: 12px;
            text-align: center;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            background: var(--bg-light);
        }

        /* ===== BOTÓN MENÚ MÓVIL ===== */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1001;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: none;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-accent);
            align-items: center;
            justify-content: center;
        }

        /* ===== HEADER ===== */
        .content-header {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 4px;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
        }

        .user-role {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: capitalize;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            flex-shrink: 0;
            border: 2px solid white;
            box-shadow: var(--shadow-accent);
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-primary);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(230, 126, 34, 0.1));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .stat-icon i {
            font-size: 26px;
            color: var(--accent-primary);
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary-dark);
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
        }

        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
            width: 100%;
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(243, 156, 18, 0.02);
            gap: 15px;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i {
            color: var(--accent-primary);
            font-size: 20px;
        }

        /* ===== CHART CONTAINERS ===== */
        .chart-container {
            padding: 20px;
            height: 350px;
            position: relative;
            width: 100%;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
            max-width: 100%;
        }

        /* ===== MENSAJE SIN DATOS ===== */
        .no-data-message {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            color: var(--text-muted);
        }

        .no-data-message i {
            font-size: 48px;
            color: var(--border-color);
            margin-bottom: 12px;
        }

        .no-data-message p {
            font-size: 14px;
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
            letter-spacing: 0.3px;
        }

        .bg-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--traffic-low);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .bg-warning {
            background: rgba(243, 156, 18, 0.1);
            color: var(--traffic-moderate);
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .bg-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--traffic-high);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .bg-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-muted);
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        /* ===== BOTONES ===== */
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            min-height: 48px;
        }

        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #d1d5db;
            transform: translateY(-2px);
            color: var(--text-primary);
        }

        /* ===== INFO ROWS ===== */
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .info-value {
            flex: 1;
            color: var(--text-secondary);
        }

        /* ===== ROW FIX ===== */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -12px;
            margin-left: -12px;
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding-right: 12px;
            padding-left: 12px;
        }

        .p-4 {
            padding: 24px;
        }

        .mt-4 {
            margin-top: 24px;
        }

        .text-center {
            text-align: center;
        }

        /* ===== RESPONSIVE DESIGN ===== */

        /* Large Desktop */
        @media (min-width: 1400px) {
            .main-content {
                padding: 32px;
            }
        }

        /* Desktop */
        @media (max-width: 1200px) {
            .main-content {
                padding: 22px;
            }
            
            .stats-grid {
                gap: 16px;
            }
        }

        /* Tablet Landscape */
        @media (max-width: 1024px) {
            .main-content {
                padding: 20px;
            }
            
            .page-title h2 {
                font-size: 24px;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-menu {
                width: 100%;
                justify-content: flex-end;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Tablet Portrait y Móviles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width-mobile);
                max-width: 300px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 70px 16px 20px;
            }
            
            .content-header {
                padding: 16px 18px;
            }
            
            .page-title h2 {
                font-size: 22px;
            }
            
            .user-menu {
                gap: 16px;
            }
            
            .user-avatar {
                width: 44px;
                height: 44px;
                font-size: 16px;
            }
            
            .stats-grid {
                gap: 14px;
                margin-bottom: 20px;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-icon {
                width: 48px;
                height: 48px;
            }
            
            .stat-icon i {
                font-size: 22px;
            }
            
            .stat-value {
                font-size: 22px;
            }
            
            .row {
                margin-right: -8px;
                margin-left: -8px;
            }
            
            .col-md-6 {
                padding-right: 8px;
                padding-left: 8px;
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 16px;
            }
            
            .card-header {
                padding: 16px 18px;
            }
            
            .card-header h3 {
                font-size: 16px;
            }
            
            .chart-container {
                height: 280px;
                padding: 16px;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-label {
                width: auto;
            }
            
            .p-4 {
                padding: 16px;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Móviles Pequeños */
        @media (max-width: 480px) {
            .main-content {
                padding: 65px 12px 16px;
            }
            
            .content-header {
                padding: 14px 16px;
            }
            
            .page-title h2 {
                font-size: 20px;
            }
            
            .page-title p {
                font-size: 12px;
            }
            
            .user-menu {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .user-info {
                text-align: left;
                width: 100%;
            }
            
            .user-avatar {
                align-self: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 14px;
            }
            
            .stat-icon {
                width: 44px;
                height: 44px;
            }
            
            .stat-icon i {
                font-size: 20px;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .stat-label {
                font-size: 12px;
            }
            
            .chart-container {
                height: 250px;
                padding: 12px;
            }
            
            .card-header {
                padding: 14px 16px;
            }
            
            .card-header h3 {
                font-size: 15px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 13px;
                min-height: 44px;
            }
            
            .no-data-message i {
                font-size: 40px;
            }
        }

        /* Móviles Muy Pequeños */
        @media (max-width: 360px) {
            .main-content {
                padding: 60px 10px 14px;
            }
            
            .page-title h2 {
                font-size: 18px;
            }
            
            .chart-container {
                height: 220px;
            }
        }

        /* Orientación Horizontal */
        @media (max-height: 600px) and (orientation: landscape) {
            .sidebar {
                overflow-y: auto;
            }
            
            .main-content {
                padding-top: 65px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-container {
                height: 250px;
            }
        }

        /* Dispositivos Táctiles */
        @media (hover: none) and (pointer: coarse) {
            .btn:hover,
            .stat-card:hover {
                transform: none;
            }
            
            .btn:active {
                transform: scale(0.98);
            }
            
            .sidebar-nav a {
                padding: 16px 24px;
            }
        }

        /* Scrollbar */
        @media (min-width: 769px) {
            ::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }
            
            ::-webkit-scrollbar-track {
                background: var(--border-color);
            }
            
            ::-webkit-scrollbar-thumb {
                background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
                border-radius: 4px;
            }
        }

        /* Utilidades */
        .me-2 {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Botón menú móvil -->
        <button class="mobile-menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-traffic-light"></i>
                <h3>VehiCount</h3>
                <p>Sistema de Conteo</p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="analisis.php">
                    <i class="fas fa-video"></i>
                    <span>Nuevo Análisis</span>
                </a>
                <?php endif; ?>
                <a href="historial.php">
                    <i class="fas fa-history"></i>
                    <span>Historial</span>
                </a>
                <a href="reportes.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
                <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="configuracion.php">
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
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <div class="page-title">
                    <h2>Detalle del Análisis #<?php echo $id; ?></h2>
                    <p><?php echo htmlspecialchars($sesion['direccion']); ?></p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['username']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($_SESSION['rol'] ?? 'Usuario'); ?></div>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr(htmlspecialchars($_SESSION['username'] ?? 'U'), 0, 1)); ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($sesion['total_vehiculos'] ?? 0); ?></div>
                        <div class="stat-label">Total Vehículos</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($sesion['promedio_vehiculos'] ?? 0, 1); ?></div>
                        <div class="stat-label">Promedio Congestión</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $sesion['max_vehiculos'] ?? 0; ?></div>
                        <div class="stat-label">Máximo Simultáneos</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $sesion['duracion_segundos'] ?? 0; ?>s</div>
                        <div class="stat-label">Duración</div>
                    </div>
                </div>
            </div>
            
            <!-- Información detallada -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Información General</h3>
                </div>
                <div class="p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">📍 Dirección:</span>
                                <span class="info-value"><?php echo htmlspecialchars($sesion['direccion']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">📅 Fecha Inicio:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($sesion['fecha_inicio'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">⏹️ Fecha Fin:</span>
                                <span class="info-value"><?php echo $sesion['fecha_fin'] ? date('d/m/Y H:i:s', strtotime($sesion['fecha_fin'])) : 'En curso'; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">🎥 Tipo Fuente:</span>
                                <span class="info-value">
                                    <?php 
                                    $icono_fuente = match($sesion['tipo_fuente'] ?? '') {
                                        'camara' => '📹',
                                        'video' => '🎥',
                                        'imagen' => '🖼️',
                                        default => '📁'
                                    };
                                    echo $icono_fuente . ' ' . ($sesion['tipo_fuente'] ?? 'N/A');
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">📁 Fuente:</span>
                                <span class="info-value"><?php echo basename($sesion['fuente'] ?? 'Cámara en vivo'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">🚦 Nivel General:</span>
                                <span class="info-value">
                                    <?php 
                                    $nivel = $sesion['nivel_general'] ?? 'N/A';
                                    $clase = '';
                                    if(strpos($nivel, 'LIBRE') !== false) $clase = 'success';
                                    elseif(strpos($nivel, 'MODERADO') !== false) $clase = 'warning';
                                    elseif(strpos($nivel, 'ALTA') !== false) $clase = 'danger';
                                    elseif(strpos($nivel, 'SEVERA') !== false) $clase = 'danger';
                                    else $clase = 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $clase; ?>"><?php echo $nivel; ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Vehículos por Tipo</h3>
                        </div>
                        <div class="chart-container" id="chartTipoContainer">
                            <canvas id="chartTipo"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-palette"></i> Vehículos por Color</h3>
                        </div>
                        <div class="chart-container" id="chartColorContainer">
                            <canvas id="chartColor"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de congestión -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Evolución de la Congestión</h3>
                </div>
                <div class="chart-container" id="chartCongestionContainer" style="height: 400px;">
                    <canvas id="chartCongestion"></canvas>
                </div>
            </div>
            
            <!-- Botón volver -->
            <div class="mt-4 text-center">
                <a href="historial.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Historial
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Datos para gráficos
        const tipos = <?php echo json_encode($tipos); ?>;
        const colores = <?php echo json_encode($colores); ?>;
        const muestras = <?php echo json_encode($muestras); ?>;
        
        // Mapa de colores para vehículos
        const colorMap = {
            'blanco': '#ffffff',
            'negro': '#000000',
            'gris': '#808080',
            'plateado': '#c0c0c0',
            'rojo': '#e74c3c',
            'azul': '#3498db',
            'verde': '#27ae60',
            'amarillo': '#f1c40f',
            'naranja': '#f39c12',
            'marron': '#8b4513'
        };
        
        // 1. Gráfico de tipos
        if(tipos && tipos.length > 0) {
            new Chart(document.getElementById('chartTipo'), {
                type: 'bar',
                data: {
                    labels: tipos.map(t => t.tipo),
                    datasets: [{
                        label: 'Cantidad',
                        data: tipos.map(t => parseInt(t.cantidad)),
                        backgroundColor: 'rgba(243, 156, 18, 0.7)',
                        borderColor: 'rgba(243, 156, 18, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        } else {
            document.getElementById('chartTipoContainer').innerHTML = 
                '<div class="no-data-message">' +
                '<i class="fas fa-chart-bar"></i>' +
                '<p>No hay datos de tipos de vehículos</p>' +
                '</div>';
        }
        
        // 2. Gráfico de colores
        if(colores && colores.length > 0) {
            const backgroundColors = colores.map(c => colorMap[c.color] || '#cccccc');
            const borderColors = colores.map(c => c.color === 'blanco' ? '#ddd' : colorMap[c.color] || '#cccccc');
            
            new Chart(document.getElementById('chartColor'), {
                type: 'doughnut',
                data: {
                    labels: colores.map(c => c.color),
                    datasets: [{
                        data: colores.map(c => parseInt(c.cantidad)),
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        } else {
            document.getElementById('chartColorContainer').innerHTML = 
                '<div class="no-data-message">' +
                '<i class="fas fa-palette"></i>' +
                '<p>No hay datos de colores</p>' +
                '</div>';
        }
        
        // 3. Gráfico de congestión
        if(muestras && muestras.length > 0) {
            new Chart(document.getElementById('chartCongestion'), {
                type: 'line',
                data: {
                    labels: muestras.map(m => new Date(m.timestamp).toLocaleTimeString()),
                    datasets: [{
                        label: 'Vehículos Simultáneos',
                        data: muestras.map(m => m.vehiculos_simultaneos),
                        borderColor: 'rgba(243, 156, 18, 1)',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(243, 156, 18, 1)',
                        pointBorderColor: 'white',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        } else {
            document.getElementById('chartCongestionContainer').innerHTML = 
                '<div class="no-data-message">' +
                '<i class="fas fa-chart-line"></i>' +
                '<p>No hay datos de congestión</p>' +
                '</div>';
        }

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');

            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            mainContent.addEventListener('click', function() {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    if(icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });
        });
    </script>
</body>
</html>