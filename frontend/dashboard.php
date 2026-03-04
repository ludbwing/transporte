<?php
// dashboard.php
session_start();
require_once 'includes/auth.php';
verificarAcceso('usuario');
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas generales
$stats = [];

// Total de análisis
$query = "SELECT COUNT(*) as total FROM sesiones";
$stmt = $db->query($query);
$stats['total_analisis'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total vehículos
$query = "SELECT SUM(total_vehiculos) as total FROM sesiones";
$stmt = $db->query($query);
$stats['total_vehiculos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Promedio congestión
$query = "SELECT AVG(promedio_vehiculos) as promedio FROM resumen_congestion";
$stmt = $db->query($query);
$stats['promedio_congestion'] = round($stmt->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 2);

// Obtener datos para gráficos
$datos_tipo = [];
$datos_color = [];

// Consultar datos reales de la base de datos
$query_tipo = "SELECT tipo, SUM(cantidad) as total 
               FROM estadisticas_tipo 
               GROUP BY tipo 
               ORDER BY total DESC";
$stmt_tipo = $db->query($query_tipo);
$datos_tipo = $stmt_tipo->fetchAll(PDO::FETCH_ASSOC);

$query_color = "SELECT color, SUM(cantidad) as total 
                FROM estadisticas_color 
                WHERE color != 'desconocido'
                GROUP BY color 
                ORDER BY total DESC 
                LIMIT 10";
$stmt_color = $db->query($query_color);
$datos_color = $stmt_color->fetchAll(PDO::FETCH_ASSOC);

// Últimas sesiones
$query = "SELECT s.*, rc.nivel_general 
          FROM sesiones s 
          LEFT JOIN resumen_congestion rc ON s.id = rc.id_sesion 
          ORDER BY s.fecha_inicio DESC 
          LIMIT 5";
$stmt = $db->query($query);
$ultimas_sesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener el ícono según el tipo de fuente
function getIconoTipo($tipo) {
    $iconos = [
        'camara' => '📹',
        'video' => '🎥',
        'imagen' => '🖼️'
    ];
    return $iconos[$tipo] ?? '📁';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dashboard - Sistema de Detección de Congestión Vehicular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== VARIABLES GLOBALES ===== */
        :root {
            /* Paleta de colores - Congestión vehicular (igual que login) */
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
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .user-info {
            flex: 1;
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
            margin-right: 14px;
            flex-shrink: 0;
        }

        .stat-icon i {
            font-size: 26px;
            color: var(--accent-primary);
        }

        .stat-content {
            flex: 1;
            min-width: 0;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary-dark);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ===== CONTENEDOR DE GRÁFICOS - UNO DEBAJO DEL OTRO ===== */
        .charts-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-bottom: 24px;
            width: 100%;
        }

        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
            width: 100%;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(243, 156, 18, 0.02);
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
            height: 400px;
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
            height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }

        .no-data-message i {
            font-size: 56px;
            color: var(--border-color);
            margin-bottom: 16px;
        }

        .no-data-message p {
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
        }

        .no-data-message small {
            color: var(--border-color);
            font-size: 13px;
            margin-top: 8px;
        }

        /* ===== TABLAS ===== */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-top: 24px;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-medium));
        }

        .table thead th {
            padding: 16px 20px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 16px 20px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .table tbody tr:hover {
            background: var(--hover-bg);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
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
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            box-shadow: var(--shadow-accent);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(243, 156, 18, 0.4);
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 12px;
        }

        /* ===== RESPONSIVE DESIGN ===== */

        /* Large Desktop */
        @media (min-width: 1400px) {
            .main-content {
                padding: 32px;
            }
            
            .chart-container {
                height: 450px;
            }
        }

        /* Desktop */
        @media (max-width: 1200px) {
            .stats-grid {
                gap: 16px;
            }
            
            .stat-value {
                font-size: 24px;
            }
        }

        /* Tablet Landscape */
        @media (max-width: 1024px) {
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-title h2 {
                font-size: 24px;
            }
            
            .chart-container {
                height: 350px;
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
            
            .charts-container {
                gap: 20px;
            }
            
            .chart-container {
                height: 320px;
                padding: 16px;
            }
            
            .card-header {
                padding: 16px 18px;
            }
            
            .card-header h3 {
                font-size: 16px;
            }
            
            .table-container {
                border-radius: var(--border-radius-sm);
            }
            
            .table thead th {
                padding: 14px 16px;
                font-size: 12px;
            }
            
            .table tbody td {
                padding: 14px 16px;
                font-size: 13px;
            }
            
            .btn {
                padding: 10px 16px;
                min-height: 44px;
            }
            
            .no-data-message {
                height: 280px;
            }
            
            .no-data-message i {
                font-size: 48px;
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
                width: 100%;
            }
            
            .user-avatar {
                align-self: flex-start;
            }
            
            .stats-grid {
                gap: 12px;
            }
            
            .stat-card {
                padding: 14px;
            }
            
            .stat-icon {
                width: 44px;
                height: 44px;
                margin-right: 12px;
            }
            
            .stat-icon i {
                font-size: 20px;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .stat-label {
                font-size: 11px;
            }
            
            .chart-container {
                height: 280px;
                padding: 12px;
            }
            
            .card-header {
                padding: 14px 16px;
            }
            
            .card-header h3 {
                font-size: 15px;
            }
            
            .btn {
                width: 100%;
            }
            
            .btn-sm {
                width: auto;
            }
            
            .badge {
                padding: 4px 10px;
                font-size: 11px;
            }
            
            .table {
                min-width: 700px;
            }
        }

        /* Móviles Muy Pequeños */
        @media (max-width: 360px) {
            .main-content {
                padding: 60px 10px 14px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .chart-container {
                height: 240px;
            }
            
            .table {
                min-width: 600px;
            }
            
            .user-menu {
                flex-direction: column;
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
            .stat-card:hover,
            .btn:hover,
            .table tbody tr:hover {
                transform: none;
            }
            
            .stat-card:active,
            .btn:active {
                transform: scale(0.98);
            }
            
            .table-responsive {
                -webkit-overflow-scrolling: touch;
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
                <a href="dashboard.php" class="active">
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
            <!-- Header -->
            <div class="content-header">
                <div class="page-title">
                    <h2>Dashboard</h2>
                    <p>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['username']); ?></strong></p>
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
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_analisis']; ?></div>
                        <div class="stat-label">Total Análisis</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_vehiculos']); ?></div>
                        <div class="stat-label">Vehículos Detectados</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['promedio_congestion']; ?></div>
                        <div class="stat-label">Promedio Congestión</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo date('d/m/Y'); ?></div>
                        <div class="stat-label">Fecha Actual</div>
                    </div>
                </div>
            </div>

            <!-- Gráficos - UNO DEBAJO DEL OTRO -->
            <div class="charts-container">
                <!-- Gráfico de Tipos -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Vehículos por Tipo</h3>
                        <i class="fas fa-car" style="color: var(--accent-primary);"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTipo"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Colores -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-palette"></i> Vehículos por Color</h3>
                        <i class="fas fa-paint-brush" style="color: var(--accent-primary);"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartColor"></canvas>
                    </div>
                </div>
            </div>

            <!-- Últimos Análisis -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Últimos Análisis</h3>
                    <a href="historial.php" class="btn btn-primary btn-sm">Ver todos</a>
                </div>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Dirección</th>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Vehículos</th>
                                    <th>Congestión</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($ultimas_sesiones)): ?>
                                    <?php foreach($ultimas_sesiones as $sesion): ?>
                                    <tr>
                                        <td>#<?php echo $sesion['id']; ?></td>
                                        <td><?php echo htmlspecialchars($sesion['direccion']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($sesion['fecha_inicio'])); ?></td>
                                        <td>
                                            <?php echo getIconoTipo($sesion['tipo_fuente']); ?>
                                            <?php echo htmlspecialchars($sesion['tipo_fuente'] ?? 'N/A'); ?>
                                        </td>
                                        <td><strong><?php echo $sesion['total_vehiculos'] ?? 0; ?></strong></td>
                                        <td>
                                            <?php 
                                            $nivel = $sesion['nivel_general'] ?? 'N/A';
                                            $clase = 'secondary';
                                            if(strpos($nivel, 'LIBRE') !== false) $clase = 'success';
                                            elseif(strpos($nivel, 'MODERADO') !== false) $clase = 'warning';
                                            elseif(strpos($nivel, 'ALTA') !== false) $clase = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $clase; ?>">
                                                <?php echo htmlspecialchars($nivel); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="ver_analisis.php?id=<?php echo $sesion['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px;">
                                            <i class="fas fa-info-circle" style="font-size: 24px; color: var(--text-muted); margin-bottom: 10px; display: block;"></i>
                                            No hay análisis registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Datos obtenidos del backend
        const datosTipo = <?php echo json_encode($datos_tipo); ?>;
        const datosColor = <?php echo json_encode($datos_color); ?>;

        // Función para generar colores
        function generarColores(cantidad) {
            const colores = [
                'rgba(243, 156, 18, 0.7)',
                'rgba(230, 126, 34, 0.7)',
                'rgba(39, 174, 96, 0.7)',
                'rgba(231, 76, 60, 0.7)',
                'rgba(142, 68, 173, 0.7)',
                'rgba(52, 152, 219, 0.7)',
                'rgba(155, 89, 182, 0.7)',
                'rgba(241, 196, 15, 0.7)',
                'rgba(46, 204, 113, 0.7)',
                'rgba(230, 126, 34, 0.7)'
            ];
            return colores.slice(0, cantidad);
        }

        // Mapa de colores para vehículos
        const mapaColoresVehiculos = {
            'blanco': 'rgba(255, 255, 255, 0.9)',
            'negro': 'rgba(0, 0, 0, 0.9)',
            'gris': 'rgba(128, 128, 128, 0.9)',
            'plateado': 'rgba(192, 192, 192, 0.9)',
            'rojo': 'rgba(231, 76, 60, 0.9)',
            'azul': 'rgba(52, 152, 219, 0.9)',
            'verde': 'rgba(39, 174, 96, 0.9)',
            'amarillo': 'rgba(241, 196, 15, 0.9)',
            'naranja': 'rgba(243, 156, 18, 0.9)',
            'marron': 'rgba(139, 69, 19, 0.9)'
        };

        // 1. GRÁFICO DE TIPOS
        if (datosTipo && datosTipo.length > 0) {
            const labels = datosTipo.map(item => item.tipo);
            const values = datosTipo.map(item => parseInt(item.total));
            const backgroundColors = generarColores(labels.length);

            new Chart(document.getElementById('chartTipo'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cantidad de Vehículos',
                        data: values,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(c => c.replace('0.7', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Distribución por Tipo' }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        } else {
            document.getElementById('chartTipo').parentNode.innerHTML = 
                '<div class="no-data-message">' +
                '<i class="fas fa-chart-bar"></i>' +
                '<p>No hay datos de tipos de vehículos</p>' +
                '<small>Realiza análisis para ver estadísticas</small>' +
                '</div>';
        }

        // 2. GRÁFICO DE COLORES
        if (datosColor && datosColor.length > 0) {
            const labels = datosColor.map(item => item.color);
            const values = datosColor.map(item => parseInt(item.total));
            
            const backgroundColors = labels.map(color => 
                mapaColoresVehiculos[color] || 'rgba(128, 128, 128, 0.7)'
            );

            new Chart(document.getElementById('chartColor'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: backgroundColors,
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: { boxWidth: 12, padding: 15 }
                        },
                        title: { display: true, text: 'Distribución por Color' }
                    }
                }
            });
        } else {
            document.getElementById('chartColor').parentNode.innerHTML = 
                '<div class="no-data-message">' +
                '<i class="fas fa-palette"></i>' +
                '<p>No hay datos de colores</p>' +
                '<small>Realiza análisis para ver estadísticas</small>' +
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

            // Cerrar sidebar al hacer clic fuera
            mainContent.addEventListener('click', function() {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Ajustar al cambiar tamaño
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