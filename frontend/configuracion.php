<?php
// configuracion.php
session_start();
require_once 'includes/auth.php';
verificarAcceso('usuario');
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Verificar si es administrador
$es_admin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
if (!$es_admin) {
    header('Location: dashboard.php');
    exit();
}

// Procesar guardado de configuración
$mensaje = '';
$tipo_mensaje = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aquí procesarías la configuración
    $mensaje = 'Configuración guardada correctamente';
    $tipo_mensaje = 'success';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Configuración - Sistema de Detección de Congestión Vehicular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* ===== ALERTAS ===== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--traffic-low);
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--traffic-high);
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

        .card-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            background: var(--hover-bg);
        }

        /* ===== FORMULARIOS ===== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: white;
            transition: var(--transition);
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(243, 156, 18, 0.1);
        }

        /* ===== RANGO (SLIDER) ===== */
        .form-range {
            width: 100%;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            -webkit-appearance: none;
            appearance: none;
            margin: 15px 0;
        }

        .form-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: var(--shadow-accent);
        }

        .form-range::-moz-range-thumb {
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: var(--shadow-accent);
        }

        .range-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .range-value {
            font-weight: 700;
            color: var(--accent-primary);
            background: rgba(243, 156, 18, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        /* ===== BOTONES ===== */
        .btn {
            padding: 12px 24px;
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

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            box-shadow: var(--shadow-accent);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(243, 156, 18, 0.4);
            color: white;
        }

        .btn-warning {
            background: var(--traffic-moderate);
            color: white;
        }

        .btn-warning:hover {
            background: var(--accent-secondary);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--traffic-high);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .w-100 {
            width: 100%;
        }

        .mt-2 {
            margin-top: 8px;
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--traffic-low);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        /* ===== INFO CARDS ===== */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-muted);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid var(--border-color);
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
            
            .card-footer {
                padding: 14px 18px;
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
            
            .card-header {
                padding: 14px 16px;
            }
            
            .card-header h3 {
                font-size: 15px;
            }
            
            .card-footer {
                padding: 12px 16px;
            }
            
            .form-label {
                font-size: 13px;
            }
            
            .form-control {
                padding: 10px 14px;
                font-size: 13px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 13px;
                min-height: 44px;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
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
        }

        /* Orientación Horizontal */
        @media (max-height: 600px) and (orientation: landscape) {
            .sidebar {
                overflow-y: auto;
            }
            
            .main-content {
                padding-top: 65px;
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

        /* Utilidades de espaciado */
        .p-4 {
            padding: 24px;
        }
        
        .me-2 {
            margin-right: 8px;
        }
        
        .text-end {
            text-align: right;
        }

        @media (max-width: 768px) {
            .p-4 {
                padding: 16px;
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
                <a href="configuracion.php" class="active">
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
                    <h2>Configuración del Sistema</h2>
                    <p>Ajustes y preferencias</p>
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
            
            <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <i class="fas <?php echo $tipo_mensaje == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-video"></i> Configuración de Análisis</h3>
                        </div>
                        <form method="POST">
                            <div class="p-4">
                                <div class="form-group">
                                    <label class="form-label">Confianza mínima YOLO</label>
                                    <input type="range" class="form-range" min="0.1" max="0.9" step="0.1" value="0.5" id="confianza">
                                    <div class="range-labels">
                                        <span>0.1</span>
                                        <span class="range-value" id="confianza_valor">0.5</span>
                                        <span>0.9</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Distancia máxima para tracking (píxeles)</label>
                                    <input type="number" class="form-control" value="300" id="distancia_maxima" min="100" max="1000">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Frames para olvidar vehículo</label>
                                    <input type="number" class="form-control" value="45" id="frames_olvidar" min="10" max="200">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Umbral de congestión baja</label>
                                    <input type="number" class="form-control" value="3" id="umbral_bajo" min="1" max="10">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Umbral de congestión moderada</label>
                                    <input type="number" class="form-control" value="8" id="umbral_moderado" min="5" max="20">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Umbral de congestión alta</label>
                                    <input type="number" class="form-control" value="15" id="umbral_alto" min="10" max="50">
                                </div>
                            </div>
                            
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Configuración
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-database"></i> Base de Datos</h3>
                        </div>
                        <div class="p-4">
                            <div class="info-row">
                                <span class="info-label">Estado:</span>
                                <span class="info-value"><span class="badge badge-success">● Conectado</span></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Base de datos:</span>
                                <span class="info-value">trafico_vehicular</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tablas:</span>
                                <span class="info-value">8</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total sesiones:</span>
                                <span class="info-value">
                                    <?php
                                    $stmt = $db->query("SELECT COUNT(*) as total FROM sesiones");
                                    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                    echo number_format($total);
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total vehículos:</span>
                                <span class="info-value">
                                    <?php
                                    $stmt = $db->query("SELECT SUM(total_vehiculos) as total FROM sesiones");
                                    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                                    echo number_format($total);
                                    ?>
                                </span>
                            </div>
                            
                            <hr>
                            
                            <button class="btn btn-warning w-100" onclick="optimizarBD()">
                                <i class="fas fa-tools"></i> Optimizar Base de Datos
                            </button>
                            
                            <button class="btn btn-danger w-100 mt-2" onclick="respaldarBD()">
                                <i class="fas fa-database"></i> Respaldar Base de Datos
                            </button>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> Perfil de Usuario</h3>
                        </div>
                        <div class="p-4">
                            <div class="info-row">
                                <span class="info-label">Usuario:</span>
                                <span class="info-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Nombre:</span>
                                <span class="info-value"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'No especificado'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rol:</span>
                                <span class="info-value">
                                    <?php if($_SESSION['rol'] === 'admin'): ?>
                                        <span class="badge" style="background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary)); color: white;">Administrador</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--traffic-low); color: white;">Usuario</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <hr>
                            
                            <button class="btn btn-primary w-100" onclick="cambiarPassword()">
                                <i class="fas fa-key"></i> Cambiar Contraseña
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Actualizar valor del slider
        document.getElementById('confianza').addEventListener('input', function() {
            document.getElementById('confianza_valor').textContent = this.value;
        });
        
        // Funciones de ejemplo
        function optimizarBD() {
            alert('🧹 Funcionalidad de optimización en desarrollo');
        }
        
        function respaldarBD() {
            alert('💾 Funcionalidad de respaldo en desarrollo');
        }
        
        function cambiarPassword() {
            alert('🔐 Funcionalidad de cambio de contraseña en desarrollo');
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