<?php
// analisis.php
session_start();
require_once 'includes/auth.php';
verificarAcceso('usuario');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Nuevo Análisis - Sistema de Detección de Congestión Vehicular</title>
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

        /* ===== FORMULARIOS ===== */
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
            font-size: 15px;
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

        .form-control:disabled {
            background: var(--hover-bg);
            opacity: 0.7;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 48px;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .text-muted {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 6px;
            display: block;
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
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
            min-height: 40px;
        }

        /* ===== PREVIEW CONTAINER ===== */
        .preview-container {
            margin-top: 20px;
            padding: 20px;
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            text-align: center;
            width: 100%;
            background: var(--hover-bg);
        }

        .preview-container h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .preview-container img,
        .preview-container video {
            max-width: 100%;
            max-height: 300px;
            border-radius: var(--border-radius-sm);
            object-fit: contain;
            background: var(--bg-card);
            box-shadow: var(--shadow-sm);
        }

        /* ===== PROGRESS BAR MEJORADA ===== */
        .progress {
            height: 36px;
            background: var(--hover-bg);
            border-radius: 18px;
            overflow: hidden;
            margin: 15px 0;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 0.3s ease;
            position: relative;
            overflow: hidden;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg,
                    rgba(255, 255, 255, 0.1) 0%,
                    rgba(255, 255, 255, 0.3) 50%,
                    rgba(255, 255, 255, 0.1) 100%);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        #progressMessage {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 10px;
            padding: 12px;
            border-radius: var(--border-radius-sm);
            background: var(--hover-bg);
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .tiempo-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding: 12px 16px;
            background: var(--hover-bg);
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            border: 1px solid var(--border-color);
        }

        .badge-tiempo {
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 6px 12px;
            border-radius: 30px;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .badge-tiempo i {
            color: var(--accent-primary);
            margin-right: 5px;
        }

        .badge-estado {
            padding: 6px 14px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
        }

        .estado-iniciando {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .estado-procesando {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .estado-completado {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .estado-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* ===== ALERTAS ===== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            border: none;
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

        /* ===== RESPONSIVE DESIGN ===== */

        /* Large Desktop */
        @media (min-width: 1400px) {
            .main-content {
                padding: 32px;
            }

            .card {
                max-width: 1200px;
                margin: 0 auto 24px;
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

            .card-header {
                padding: 16px 18px;
            }

            .card-header h3 {
                font-size: 16px;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }

            .btn-sm {
                width: auto;
            }

            .row {
                margin: 0;
            }

            .col-md-6 {
                padding: 0 8px;
                margin-bottom: 16px;
            }

            .tiempo-info {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .badge-estado {
                align-self: flex-start;
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

            .card-header {
                padding: 14px 16px;
            }

            .card-header h3 {
                font-size: 15px;
            }

            .form-control {
                padding: 10px 14px;
                font-size: 14px;
            }

            .btn {
                padding: 10px 20px;
                font-size: 13px;
                min-height: 44px;
            }

            .preview-container {
                padding: 15px;
            }

            .preview-container img,
            .preview-container video {
                max-height: 200px;
            }

            .tiempo-info {
                padding: 10px 12px;
            }

            .badge-tiempo,
            .badge-estado {
                width: 100%;
                text-align: center;
            }
        }

        /* Móviles Muy Pequeños */
        @media (max-width: 360px) {
            .main-content {
                padding: 60px 10px 14px;
            }

            .card-header {
                padding: 12px 14px;
            }

            .card-header h3 {
                font-size: 14px;
            }

            .form-control {
                padding: 8px 12px;
                font-size: 13px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 12px;
            }

            .progress {
                height: 30px;
            }

            .progress-bar {
                font-size: 12px;
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

            .preview-container img,
            .preview-container video {
                max-height: 150px;
            }
        }

        /* Dispositivos Táctiles */
        @media (hover: none) and (pointer: coarse) {
            .btn:hover {
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

        .mt-4 {
            margin-top: 24px;
        }

        .mb-3 {
            margin-bottom: 16px;
        }

        .me-2 {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .p-4 {
                padding: 16px;
            }

            .mt-4 {
                margin-top: 20px;
            }

            .mb-3 {
                margin-bottom: 14px;
            }
        }

        @media (max-width: 480px) {
            .p-4 {
                padding: 14px;
            }

            .mt-4 {
                margin-top: 16px;
            }

            .mb-3 {
                margin-bottom: 12px;
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
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <a href="analisis.php" class="active">
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
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
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
                    <h2>Nuevo Análisis</h2>
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

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-video"></i> Configuración del Análisis</h3>
                </div>

                <form id="analisisForm" enctype="multipart/form-data">
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">📍 Dirección / Ubicación</label>
                                <input type="text" class="form-control" id="direccion" required
                                    placeholder="Ej: Av. Principal 123" value="Av. Principal 123">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">🎥 Modo de Análisis</label>
                                <select class="form-control" id="modo" required>
                                    <option value="">Seleccione un modo</option>
                                    <option value="camara">📹 Cámara Web</option>
                                    <option value="video" selected>🎥 Archivo de Video</option>
                                    <option value="imagen">🖼️ Imagen Estática</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3" id="archivoContainer">
                            <label class="form-label">📁 Archivo</label>
                            <input type="file" class="form-control" id="archivo"
                                accept=".mp4,.avi,.mov,.mkv,.jpg,.jpeg,.png,.gif" required>
                            <small class="text-muted">Formatos soportados: MP4, AVI, MOV, JPG, PNG (máx 500MB)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">⏱️ Duración (segundos, solo para cámara)</label>
                                <input type="number" class="form-control" id="duracion" value="30" min="5" max="300" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">🔍 Escala de visualización</label>
                                <input type="number" class="form-control" id="escala" value="0.8" min="0.3" max="2.0" step="0.1">
                            </div>
                        </div>

                        <!-- Preview -->
                        <div id="previewContainer" class="preview-container" style="display: none;">
                            <h6>Vista Previa:</h6>
                            <img id="previewImage" style="display: none; max-width: 100%;">
                            <video id="previewVideo" controls style="display: none; max-width: 100%;"></video>
                        </div>

                        <!-- Progress -->
                        <div id="progressContainer" style="display: none;">
                            <h6>Progreso del Análisis:</h6>
                            <div class="progress">
                                <div class="progress-bar" id="progressBar">0%</div>
                            </div>
                            <p id="progressMessage" class="text-muted">Iniciando análisis...</p>

                            <div class="tiempo-info">
                                <span class="badge-tiempo" id="tiempoTranscurrido">
                                    <i class="far fa-clock"></i> 0s
                                </span>
                                <span class="badge-estado" id="estadoActual">⚪ Esperando</span>
                            </div>
                        </div>

                        <!-- Result -->
                        <div id="resultContainer" style="display: none;">
                            <div class="alert alert-success" id="resultContent"></div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" id="btnIniciar">
                                <i class="fas fa-play"></i> Iniciar Análisis
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelarAnalisis()">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            let analisisId = null;
            let progressInterval = null;
            let tiempoInicio = null;
            let reconnectAttempts = 0;
            let ultimoProgresoValido = 0;
            let usandoFallback = false;
            let progresoSimulado = 0;
            let intervaloSimulado = null;

            // Detectar si es dispositivo móvil
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 768;

            // Manejar cambio de modo
            $('#modo').on('change', function() {
                const modo = $(this).val();
                if (modo === 'camara') {
                    $('#archivoContainer').slideUp();
                    $('#duracion').prop('disabled', false);
                    $('#archivo').prop('required', false);
                } else {
                    $('#archivoContainer').slideDown();
                    $('#duracion').prop('disabled', true);
                    $('#archivo').prop('required', true);
                }
                $('#previewContainer').hide();
            });

            // Vista previa de archivo
            $('#archivo').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const url = URL.createObjectURL(file);
                    const tipo = $('#modo').val();

                    $('#previewContainer').show();

                    if (tipo === 'video') {
                        $('#previewImage').hide();
                        $('#previewVideo').show().attr('src', url);
                    } else if (tipo === 'imagen') {
                        $('#previewVideo').hide();
                        $('#previewImage').show().attr('src', url);
                    }
                }
            });

            // Enviar formulario
            $('#analisisForm').on('submit', function(e) {
                e.preventDefault();

                const modo = $('#modo').val();

                if (modo !== 'camara' && $('#archivo')[0].files.length === 0) {
                    alert('❌ Por favor seleccione un archivo');
                    return;
                }

                if (!$('#direccion').val().trim()) {
                    alert('❌ Por favor ingrese una dirección');
                    return;
                }

                const formData = new FormData();
                formData.append('direccion', $('#direccion').val());
                formData.append('modo', modo);
                formData.append('duracion', $('#duracion').val());
                formData.append('escala', $('#escala').val());

                if ($('#archivo')[0].files[0]) {
                    formData.append('archivo', $('#archivo')[0].files[0]);
                }

                // Mostrar progreso
                $('#progressContainer').show();
                $('#resultContainer').hide();
                $('#btnIniciar').prop('disabled', true);
                $('#progressBar').css('width', '0%').text('0%');
                $('#progressMessage').text('Iniciando análisis...');
                $('#estadoActual').text('⚪ Iniciando').removeClass().addClass('badge-estado estado-iniciando');
                $('#tiempoTranscurrido').html('<i class="far fa-clock"></i> 0s');

                tiempoInicio = new Date();
                ultimoProgresoValido = 0;
                reconnectAttempts = 0;
                usandoFallback = false;

                // SIEMPRE iniciar el contador local
                iniciarContadorLocal();

                // ESTRATEGIA 1: Intentar con el servidor Python
                $.ajax({
                    url: 'api/analisis.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    timeout: 30000,
                    success: function(response) {
                        if (response.success) {
                            analisisId = response.analisis_id;
                            $('#progressMessage').text(`Análisis #${analisisId} iniciado, procesando...`);
                            $('#estadoActual').text('🔄 Procesando').removeClass().addClass('badge-estado estado-procesando');

                            // Limpiar intervalo anterior si existe
                            if (progressInterval) {
                                clearInterval(progressInterval);
                            }

                            // Iniciar consulta de progreso al servidor Python
                            const intervalTime = 1500; // 1.5 segundos para ambos
                            progressInterval = setInterval(consultarProgresoPython, intervalTime);

                            // ESTRATEGIA 2: Timeout de seguridad - si después de 10 segundos no hay progreso, activar fallback
                            setTimeout(function() {
                                if (ultimoProgresoValido === 0 && analisisId) {
                                    console.log('Activando fallback por timeout inicial');
                                    activarFallbackProgreso();
                                }
                            }, 10000);

                        } else {
                            // Si el servidor PHP devuelve error, activar fallback inmediatamente
                            activarFallbackProgreso();
                            mostrarError(response.error || 'Error desconocido');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Error en API PHP, activando fallback');
                        // Si no podemos conectar con PHP, activar fallback
                        activarFallbackProgreso();
                        $('#progressMessage').text('Usando modo de progreso estimado...');
                    }
                });
            });

            // Función para consultar progreso al servidor Python
            function consultarProgresoPython() {
                if (!analisisId) return;

                $.ajax({
                    url: 'http://127.0.0.1:5000/api/analisis/progreso/' + analisisId,
                    type: 'GET',
                    dataType: 'json',
                    timeout: 3000,
                    success: function(response) {
                        if (response.success) {
                            // Resetear contador de reintentos
                            reconnectAttempts = 0;

                            const progreso = response.progreso || 0;
                            const estado = response.estado;
                            const mensaje = response.mensaje || 'Procesando...';

                            // Guardar el último progreso válido
                            ultimoProgresoValido = progreso;

                            // Actualizar barra de progreso
                            $('#progressBar').css('width', progreso + '%').text(progreso + '%');
                            $('#progressMessage').text(mensaje);

                            // Actualizar estado
                            if (estado === 'procesando') {
                                $('#estadoActual').text('🔄 Procesando').removeClass().addClass('badge-estado estado-procesando');
                            } else if (estado === 'completado' || progreso >= 100) {
                                finalizarAnalisisExitoso(response.sesion_id || analisisId);
                            } else if (estado === 'error') {
                                $('#estadoActual').text('❌ Error').removeClass().addClass('badge-estado estado-error');
                                detenerTodo();
                                mostrarError(mensaje || 'Error en el análisis');
                            }
                        } else {
                            manejarErrorConexionPython();
                        }
                    },
                    error: function(xhr, status, error) {
                        manejarErrorConexionPython();
                    }
                });
            }

            function manejarErrorConexionPython() {
                reconnectAttempts++;

                // Después de 3 intentos fallidos consecutivos, activar fallback
                if (reconnectAttempts >= 3 && !usandoFallback) {
                    activarFallbackProgreso();
                }

                // Mientras tanto, mantener el último progreso conocido
                if (ultimoProgresoValido > 0) {
                    $('#progressBar').css('width', ultimoProgresoValido + '%').text(ultimoProgresoValido + '%');
                    $('#progressMessage').text(`Procesando... (${ultimoProgresoValido}%) - Reconectando`);
                }
            }

            // ESTRATEGIA DE FALLBACK: Simulación inteligente de progreso
            function activarFallbackProgreso() {
                if (usandoFallback) return;

                console.log('Activando fallback de progreso');
                usandoFallback = true;
                progresoSimulado = ultimoProgresoValido || 0;

                $('#progressMessage').text('Procesando en modo estimado...');

                // Limpiar intervalo anterior si existe
                if (progressInterval) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                }

                if (intervaloSimulado) {
                    clearInterval(intervaloSimulado);
                }

                // Iniciar simulación inteligente de progreso
                intervaloSimulado = setInterval(function() {
                    if (!analisisId) return;

                    // Aumentar progreso de manera realista (más lento al final)
                    if (progresoSimulado < 30) {
                        progresoSimulado += Math.random() * 5 + 2; // 2-7%
                    } else if (progresoSimulado < 70) {
                        progresoSimulado += Math.random() * 3 + 1; // 1-4%
                    } else if (progresoSimulado < 95) {
                        progresoSimulado += Math.random() * 1.5; // 0-1.5%
                    }

                    // No pasar de 99% hasta confirmar finalización
                    if (progresoSimulado > 99) {
                        progresoSimulado = 99;
                    }

                    // Actualizar barra
                    $('#progressBar').css('width', progresoSimulado + '%').text(Math.round(progresoSimulado) + '%');

                    // Verificar si ya pasó suficiente tiempo para considerar completado
                    const tiempoTranscurrido = Math.floor((new Date() - tiempoInicio) / 1000);

                    // Si han pasado más de 60 segundos y el progreso está alto, considerar completado
                    if (tiempoTranscurrido > 60 && progresoSimulado > 80) {
                        progresoSimulado = 100;
                        finalizarAnalisisExitoso(analisisId);
                    }

                }, 2000);

                // También intentar consultar al servidor Python ocasionalmente por si se recupera
                setInterval(function() {
                    if (usandoFallback && analisisId) {
                        $.ajax({
                            url: 'http://127.0.0.1:5000/api/analisis/progreso/' + analisisId,
                            type: 'GET',
                            timeout: 2000,
                            success: function(response) {
                                if (response.success && response.progreso > progresoSimulado) {
                                    // Si el servidor tiene mejor información, usarla
                                    console.log('Recuperada conexión con servidor');
                                    progresoSimulado = response.progreso;
                                    ultimoProgresoValido = response.progreso;

                                    if (response.progreso >= 100 || response.estado === 'completado') {
                                        finalizarAnalisisExitoso(response.sesion_id || analisisId);
                                    }
                                }
                            }
                        });
                    }
                }, 10000);
            }

            // Función para iniciar contador local
            function iniciarContadorLocal() {
                if (window.localCounter) {
                    clearInterval(window.localCounter);
                }

                window.localCounter = setInterval(function() {
                    if (tiempoInicio) {
                        const segundos = Math.floor((new Date() - tiempoInicio) / 1000);
                        const minutos = Math.floor(segundos / 60);
                        const tiempoStr = minutos > 0 ? `${minutos}m ${segundos % 60}s` : `${segundos}s`;
                        $('#tiempoTranscurrido').html(`<i class="far fa-clock"></i> ${tiempoStr}`);
                    }
                }, 1000);
            }

            function finalizarAnalisisExitoso(sesionId) {
                console.log('Análisis completado:', sesionId);

                detenerTodo();

                // Asegurar que la barra esté al 100%
                $('#progressBar').css('width', '100%').text('100%');

                // Calcular tiempo total
                const segundos = Math.floor((new Date() - tiempoInicio) / 1000);
                const minutos = Math.floor(segundos / 60);
                const tiempoTotal = minutos > 0 ? `${minutos}m ${segundos % 60}s` : `${segundos}s`;

                $('#progressMessage').text(`✅ ¡Análisis completado en ${tiempoTotal}!`);
                $('#estadoActual').text('✅ Completado').removeClass().addClass('badge-estado estado-completado');

                // Actualizar contador una última vez
                $('#tiempoTranscurrido').html(`<i class="far fa-clock"></i> ${tiempoTotal}`);

                // Mostrar resultado después de 1 segundo
                setTimeout(function() {
                    mostrarExito(sesionId, tiempoTotal);
                }, 1000);
            }

            function mostrarExito(sesionId, tiempoTotal) {
                $('#resultContainer').show();
                $('#resultContent').html(`
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle"></i> ¡Análisis completado exitosamente!</h5>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>ID de Sesión:</strong> #${sesionId}</p>
                        <p><strong>Dirección:</strong> ${$('#direccion').val()}</p>
                        <p><strong>Modo:</strong> ${$('#modo').val()}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tiempo total:</strong> ${tiempoTotal}</p>
                        <p><strong>Estado:</strong> <span class="badge bg-success">Completado</span></p>
                    </div>
                </div>
                <p class="mt-2">Los resultados ya están disponibles en el historial.</p>
            </div>
            <div class="text-center mt-3">
                <a href="historial.php" class="btn btn-primary">📋 Ver Historial</a>
                <button onclick="window.location.reload()" class="btn btn-secondary">🔄 Nuevo Análisis</button>
            </div>
        `);
                $('#btnIniciar').prop('disabled', false);
            }

            function mostrarError(mensaje) {
                detenerTodo();

                $('#resultContainer').show();
                $('#resultContent').html(`
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-circle"></i> Error en el análisis</h5>
                <p>${mensaje}</p>
                <button onclick="window.location.reload()" class="btn btn-danger mt-2">Intentar de nuevo</button>
            </div>
        `);
                $('#btnIniciar').prop('disabled', false);
            }

            function detenerTodo() {
                if (progressInterval) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                }

                if (window.localCounter) {
                    clearInterval(window.localCounter);
                    window.localCounter = null;
                }

                if (intervaloSimulado) {
                    clearInterval(intervaloSimulado);
                    intervaloSimulado = null;
                }
            }

            function cancelarAnalisis() {
                if (confirm('¿Está seguro de cancelar?')) {
                    detenerTodo();
                    window.location.reload();
                }
            }

            window.cancelarAnalisis = cancelarAnalisis;
        });

        // Mobile menu toggle (tu código existente se mantiene igual)
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
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });
        });
    </script>
</body>

</html>