<?php
// reportes.php
session_start();
require_once 'includes/auth.php';
verificarAcceso('usuario');
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$tipo_reporte = isset($_GET['tipo_reporte']) ? $_GET['tipo_reporte'] : 'general';

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}

// ==================== REPORTE GENERAL ====================
if ($tipo_reporte == 'general') {
    // Resumen general del período
    $query = "SELECT 
                COUNT(*) as total_analisis,
                SUM(total_vehiculos) as total_vehiculos,
                AVG(total_vehiculos) as promedio_vehiculos,
                SUM(duracion_segundos) as total_duracion,
                COUNT(CASE WHEN estado = 'finalizada' THEN 1 END) as completados,
                COUNT(CASE WHEN estado = 'error' THEN 1 END) as errores
              FROM sesiones 
              WHERE DATE(fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

    // Análisis por tipo de fuente
    $query = "SELECT 
                tipo_fuente,
                COUNT(*) as cantidad,
                SUM(total_vehiculos) as vehiculos
              FROM sesiones 
              WHERE DATE(fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY tipo_fuente";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Análisis por hora del día
    $query = "SELECT 
                HOUR(fecha_inicio) as hora,
                AVG(total_vehiculos) as promedio
              FROM sesiones 
              WHERE DATE(fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY HOUR(fecha_inicio)
              ORDER BY hora";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $por_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Análisis por día del mes
    $query = "SELECT 
                DATE(fecha_inicio) as fecha,
                COUNT(*) as cantidad,
                SUM(total_vehiculos) as vehiculos
              FROM sesiones 
              WHERE DATE(fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY DATE(fecha_inicio)
              ORDER BY fecha";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== REPORTE DE CONGESTIÓN ====================
if ($tipo_reporte == 'congestion') {
    // Niveles de congestión en el período
    $query = "SELECT 
                rc.nivel_general,
                COUNT(*) as cantidad,
                AVG(rc.promedio_vehiculos) as promedio_vehiculos,
                MAX(rc.max_vehiculos) as max_vehiculos
              FROM resumen_congestion rc
              INNER JOIN sesiones s ON rc.id_sesion = s.id
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY rc.nivel_general
              ORDER BY FIELD(rc.nivel_general, 
                'FLUJO LIBRE', 'MODERADO', 'CONGESTIÓN ALTA', 'CONGESTIÓN SEVERA')";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Evolución diaria de la congestión
    $query = "SELECT 
                DATE(s.fecha_inicio) as fecha,
                AVG(rc.promedio_vehiculos) as promedio,
                MAX(rc.max_vehiculos) as maximo,
                MIN(rc.promedio_vehiculos) as minimo
              FROM resumen_congestion rc
              INNER JOIN sesiones s ON rc.id_sesion = s.id
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY DATE(s.fecha_inicio)
              ORDER BY fecha";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $evolucion = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 días con mayor congestión
    $query = "SELECT 
                DATE(s.fecha_inicio) as fecha,
                AVG(rc.promedio_vehiculos) as promedio,
                MAX(rc.max_vehiculos) as maximo,
                COUNT(*) as analisis
              FROM resumen_congestion rc
              INNER JOIN sesiones s ON rc.id_sesion = s.id
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY DATE(s.fecha_inicio)
              ORDER BY promedio DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $top_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== REPORTE DE VEHÍCULOS ====================
if ($tipo_reporte == 'vehiculos') {
    // Tipos de vehículos más comunes
    $query = "SELECT 
                et.tipo,
                SUM(et.cantidad) as total
              FROM estadisticas_tipo et
              INNER JOIN sesiones s ON et.id_sesion = s.id
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY et.tipo
              ORDER BY total DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $tipos_vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Colores más comunes
    $query = "SELECT 
                ec.color,
                SUM(ec.cantidad) as total
              FROM estadisticas_color ec
              INNER JOIN sesiones s ON ec.id_sesion = s.id
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
                AND ec.color != 'desconocido'
              GROUP BY ec.color
              ORDER BY total DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $colores_vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Evolución de vehículos por día
    $query = "SELECT 
                DATE(s.fecha_inicio) as fecha,
                SUM(et.cantidad) as total_vehiculos
              FROM estadisticas_tipo et
              INNER JOIN sesiones s ON et.id_sesion = s.id
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              GROUP BY DATE(s.fecha_inicio)
              ORDER BY fecha";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $evolucion_vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 análisis con más vehículos
    $query = "SELECT 
                s.id,
                s.direccion,
                s.fecha_inicio,
                s.total_vehiculos
              FROM sesiones s
              WHERE DATE(s.fecha_inicio) BETWEEN :fecha_inicio AND :fecha_fin
              ORDER BY s.total_vehiculos DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    $top_analisis = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener el color según el nivel de congestión
function getColorNivel($nivel) {
    $colores = [
        'FLUJO LIBRE' => '#27ae60',
        'MODERADO' => '#f39c12',
        'CONGESTIÓN ALTA' => '#e74c3c',
        'CONGESTIÓN SEVERA' => '#8e44ad'
    ];
    return $colores[$nivel] ?? '#64748b';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Reportes - Sistema de Detección de Congestión Vehicular</title>
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

        /* ===== FILTROS ===== */
        .filters-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
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

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 48px;
        }

        .btn-filter {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            box-shadow: var(--shadow-accent);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 48px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(243, 156, 18, 0.4);
            color: white;
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
            font-size: 24px;
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

        /* ===== TABLAS ===== */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            padding: 0 20px 20px 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-medium));
        }

        .table thead th {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:hover {
            background: var(--hover-bg);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-congestion {
            background: var(--bg-card);
            border: 1px solid;
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
            padding: 10px 20px;
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
            min-height: 44px;
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

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
            min-height: 38px;
        }

        /* ===== COLOR PATCHES ===== */
        .color-patch {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 4px;
            margin-right: 8px;
            vertical-align: middle;
        }

        /* ===== MENSAJE SIN DATOS ===== */
        .no-data-message {
            text-align: center;
            padding: 60px 20px;
        }

        .no-data-message i {
            font-size: 56px;
            color: var(--border-color);
            margin-bottom: 16px;
        }

        .no-data-message h5 {
            color: var(--text-muted);
            font-size: 18px;
            margin-bottom: 8px;
        }

        .no-data-message p {
            color: var(--text-muted);
            font-size: 14px;
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
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
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
            
            .filters-container {
                padding: 18px;
            }
            
            .row {
                margin-right: -8px;
                margin-left: -8px;
            }
            
            .col-md-4, .col-md-3, .col-md-6 {
                padding-right: 8px;
                padding-left: 8px;
                margin-bottom: 16px;
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .d-flex.align-items-end {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-filter {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                gap: 16px;
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
            
            .card-header {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-header h3 {
                font-size: 16px;
            }
            
            .chart-container {
                height: 280px;
                padding: 16px;
            }
            
            .table-container {
                padding: 0 16px 16px 16px;
            }
            
            .table thead th {
                padding: 12px 14px;
                font-size: 12px;
            }
            
            .table tbody td {
                padding: 10px 14px;
                font-size: 13px;
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
            
            .filters-container {
                padding: 16px;
            }
            
            .form-label {
                font-size: 13px;
            }
            
            .form-control {
                padding: 10px 14px;
                font-size: 13px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            
            .table {
                min-width: 600px;
            }
            
            .table thead th {
                padding: 10px 12px;
                font-size: 11px;
            }
            
            .table tbody td {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 11px;
            }
            
            .badge {
                padding: 4px 8px;
                font-size: 10px;
            }
            
            .no-data-message i {
                font-size: 48px;
            }
            
            .no-data-message h5 {
                font-size: 16px;
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
            
            .table {
                min-width: 500px;
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
            
            .table-container {
                -webkit-overflow-scrolling: touch;
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
        .me-2 {
            margin-right: 8px;
        }
        
        .mt-4 {
            margin-top: 24px;
        }
        
        .mb-3 {
            margin-bottom: 16px;
        }
        
        .p-4 {
            padding: 24px;
        }

        @media (max-width: 768px) {
            .mt-4 {
                margin-top: 20px;
            }
            
            .mb-3 {
                margin-bottom: 14px;
            }
            
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
                <a href="reportes.php" class="active">
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
                    <h2>Reportes</h2>
                    <p>Análisis estadístico del período</p>
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
            
            <!-- Filtros -->
            <div class="filters-container">
                <form method="GET" action="reportes.php" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">📅 Fecha inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" 
                                   value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">📅 Fecha fin</label>
                            <input type="date" class="form-control" name="fecha_fin" 
                                   value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">📊 Tipo de reporte</label>
                            <select class="form-control" name="tipo_reporte">
                                <option value="general" <?php echo $tipo_reporte == 'general' ? 'selected' : ''; ?>>📋 General</option>
                                <option value="congestion" <?php echo $tipo_reporte == 'congestion' ? 'selected' : ''; ?>>🚦 Congestión</option>
                                <option value="vehiculos" <?php echo $tipo_reporte == 'vehiculos' ? 'selected' : ''; ?>>🚗 Vehículos</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 text-end">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-chart-line"></i> Generar Reporte
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- ==================== REPORTE GENERAL ==================== -->
            <?php if ($tipo_reporte == 'general'): ?>
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($resumen['total_analisis'] ?? 0); ?></div>
                            <div class="stat-label">Total Análisis</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($resumen['total_vehiculos'] ?? 0); ?></div>
                            <div class="stat-label">Vehículos Detectados</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($resumen['promedio_vehiculos'] ?? 0, 1); ?></div>
                            <div class="stat-label">Promedio por Análisis</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo gmdate("H:i:s", $resumen['total_duracion'] ?? 0); ?></div>
                            <div class="stat-label">Tiempo Total</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($resumen['completados'] ?? 0); ?></div>
                            <div class="stat-label">Completados</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($resumen['errores'] ?? 0); ?></div>
                            <div class="stat-label">Errores</div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-pie"></i> Análisis por Tipo de Fuente</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="chartTipoFuente"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Promedio por Hora del Día</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="chartHora"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-alt"></i> Análisis por Día</h3>
                            </div>
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="chartDia"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-table"></i> Detalle por Tipo de Fuente</h3>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tipo de Fuente</th>
                                    <th>Cantidad de Análisis</th>
                                    <th>Total Vehículos</th>
                                    <th>Promedio por Análisis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($por_tipo as $tipo): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $icono = match($tipo['tipo_fuente']) {
                                            'camara' => '📹',
                                            'video' => '🎥',
                                            'imagen' => '🖼️',
                                            default => '📁'
                                        };
                                        echo $icono . ' ' . ucfirst($tipo['tipo_fuente']);
                                        ?>
                                    </td>
                                    <td><?php echo $tipo['cantidad']; ?></td>
                                    <td><?php echo number_format($tipo['vehiculos']); ?></td>
                                    <td><?php echo round($tipo['vehiculos'] / $tipo['cantidad'], 1); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                // Gráfico de tipos de fuente
                new Chart(document.getElementById('chartTipoFuente'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode(array_column($por_tipo, 'tipo_fuente')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($por_tipo, 'cantidad')); ?>,
                            backgroundColor: [
                                'rgba(243, 156, 18, 0.8)',
                                'rgba(52, 152, 219, 0.8)',
                                'rgba(46, 204, 113, 0.8)'
                            ],
                            borderColor: 'white',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Gráfico por hora
                const horas = <?php echo json_encode(array_column($por_hora, 'hora')); ?>;
                const promedios = <?php echo json_encode(array_column($por_hora, 'promedio')); ?>;

                new Chart(document.getElementById('chartHora'), {
                    type: 'line',
                    data: {
                        labels: horas.map(h => h + ':00'),
                        datasets: [{
                            label: 'Promedio de vehículos',
                            data: promedios,
                            borderColor: 'rgba(243, 156, 18, 1)',
                            backgroundColor: 'rgba(243, 156, 18, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

                // Gráfico por día
                const fechas = <?php echo json_encode(array_column($por_dia, 'fecha')); ?>;
                const vehiculos = <?php echo json_encode(array_column($por_dia, 'vehiculos')); ?>;

                new Chart(document.getElementById('chartDia'), {
                    type: 'bar',
                    data: {
                        labels: fechas.map(f => f.split('-').reverse().join('/')),
                        datasets: [{
                            label: 'Vehículos detectados',
                            data: vehiculos,
                            backgroundColor: 'rgba(243, 156, 18, 0.7)',
                            borderColor: 'rgba(243, 156, 18, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                </script>
            <?php endif; ?>
            
            <!-- ==================== REPORTE DE CONGESTIÓN ==================== -->
            <?php if ($tipo_reporte == 'congestion'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-pie"></i> Distribución de Niveles</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="chartNiveles"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-table"></i> Detalle por Nivel</h3>
                            </div>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Nivel</th>
                                            <th>Cantidad</th>
                                            <th>Promedio</th>
                                            <th>Máximo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($niveles as $nivel): ?>
                                        <tr>
                                            <td>
                                                <span class="color-patch" style="background-color: <?php echo getColorNivel($nivel['nivel_general']); ?>"></span>
                                                <?php echo $nivel['nivel_general']; ?>
                                            </td>
                                            <td><?php echo $nivel['cantidad']; ?></td>
                                            <td><?php echo number_format($nivel['promedio_vehiculos'], 1); ?></td>
                                            <td><?php echo $nivel['max_vehiculos']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Evolución de la Congestión</h3>
                            </div>
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="chartEvolucion"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Top 10 Días con Mayor Congestión</h3>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Promedio Vehículos</th>
                                    <th>Máximo Vehículos</th>
                                    <th>Cantidad Análisis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_dias as $dia): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($dia['fecha'])); ?></td>
                                    <td><strong><?php echo number_format($dia['promedio'], 1); ?></strong></td>
                                    <td><?php echo $dia['maximo']; ?></td>
                                    <td><?php echo $dia['analisis']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                // Gráfico de niveles de congestión
                new Chart(document.getElementById('chartNiveles'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_column($niveles, 'nivel_general')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($niveles, 'cantidad')); ?>,
                            backgroundColor: [
                                'rgba(39, 174, 96, 0.8)',
                                'rgba(243, 156, 18, 0.8)',
                                'rgba(231, 76, 60, 0.8)',
                                'rgba(142, 68, 173, 0.8)'
                            ],
                            borderColor: 'white',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Gráfico de evolución
                const fechasEvolucion = <?php echo json_encode(array_column($evolucion, 'fecha')); ?>;
                const promediosEvolucion = <?php echo json_encode(array_column($evolucion, 'promedio')); ?>;
                const maximosEvolucion = <?php echo json_encode(array_column($evolucion, 'maximo')); ?>;
                const minimosEvolucion = <?php echo json_encode(array_column($evolucion, 'minimo')); ?>;

                new Chart(document.getElementById('chartEvolucion'), {
                    type: 'line',
                    data: {
                        labels: fechasEvolucion.map(f => f.split('-').reverse().join('/')),
                        datasets: [
                            {
                                label: 'Promedio',
                                data: promediosEvolucion,
                                borderColor: 'rgba(243, 156, 18, 1)',
                                backgroundColor: 'rgba(243, 156, 18, 0.1)',
                                borderWidth: 3,
                                fill: false,
                                tension: 0.4
                            },
                            {
                                label: 'Máximo',
                                data: maximosEvolucion,
                                borderColor: 'rgba(231, 76, 60, 1)',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                fill: false,
                                tension: 0.4
                            },
                            {
                                label: 'Mínimo',
                                data: minimosEvolucion,
                                borderColor: 'rgba(39, 174, 96, 1)',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                fill: false,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                </script>
            <?php endif; ?>
            
            <!-- ==================== REPORTE DE VEHÍCULOS ==================== -->
            <?php if ($tipo_reporte == 'vehiculos'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-bar"></i> Tipos de Vehículos</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="chartTiposVehiculos"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-pie"></i> Colores de Vehículos</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="chartColoresVehiculos"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Evolución de Vehículos por Día</h3>
                            </div>
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="chartEvolucionVehiculos"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-list"></i> Detalle por Tipo</h3>
                            </div>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                            <th>Porcentaje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_tipos = array_sum(array_column($tipos_vehiculos, 'total'));
                                        foreach ($tipos_vehiculos as $tipo): 
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst($tipo['tipo']); ?></td>
                                            <td><strong><?php echo number_format($tipo['total']); ?></strong></td>
                                            <td><?php echo round(($tipo['total'] / $total_tipos) * 100, 1); ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-list"></i> Detalle por Color</h3>
                            </div>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Color</th>
                                            <th>Cantidad</th>
                                            <th>Porcentaje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_colores = array_sum(array_column($colores_vehiculos, 'total'));
                                        foreach ($colores_vehiculos as $color): 
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="color-patch" style="background-color: <?php 
                                                    if($color['color'] == 'blanco') echo '#f8f9fa';
                                                    elseif($color['color'] == 'negro') echo '#212529';
                                                    elseif($color['color'] == 'gris') echo '#6c757d';
                                                    elseif($color['color'] == 'plateado') echo '#ced4da';
                                                    elseif($color['color'] == 'rojo') echo '#dc3545';
                                                    elseif($color['color'] == 'azul') echo '#0d6efd';
                                                    elseif($color['color'] == 'verde') echo '#198754';
                                                    elseif($color['color'] == 'amarillo') echo '#ffc107';
                                                    elseif($color['color'] == 'naranja') echo '#fd7e14';
                                                    elseif($color['color'] == 'marron') echo '#8b4513';
                                                    else echo '#6c757d';
                                                ?>; border: 1px solid #ddd;"></span>
                                                <?php echo ucfirst($color['color']); ?>
                                            </td>
                                            <td><strong><?php echo number_format($color['total']); ?></strong></td>
                                            <td><?php echo round(($color['total'] / $total_colores) * 100, 1); ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Top 10 Análisis con Más Vehículos</h3>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Dirección</th>
                                    <th>Fecha</th>
                                    <th>Total Vehículos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_analisis as $analisis): ?>
                                <tr>
                                    <td><strong>#<?php echo $analisis['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($analisis['direccion']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($analisis['fecha_inicio'])); ?></td>
                                    <td><strong><?php echo number_format($analisis['total_vehiculos']); ?></strong></td>
                                    <td>
                                        <a href="ver_analisis.php?id=<?php echo $analisis['id']; ?>" 
                                           class="btn btn-primary btn-sm" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                // Gráfico de tipos de vehículos
                new Chart(document.getElementById('chartTiposVehiculos'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($tipos_vehiculos, 'tipo')); ?>,
                        datasets: [{
                            label: 'Cantidad de vehículos',
                            data: <?php echo json_encode(array_column($tipos_vehiculos, 'total')); ?>,
                            backgroundColor: 'rgba(243, 156, 18, 0.7)',
                            borderColor: 'rgba(243, 156, 18, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Gráfico de colores
                new Chart(document.getElementById('chartColoresVehiculos'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode(array_column($colores_vehiculos, 'color')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($colores_vehiculos, 'total')); ?>,
                            backgroundColor: [
                                'rgba(255, 255, 255, 0.9)',
                                'rgba(0, 0, 0, 0.9)',
                                'rgba(128, 128, 128, 0.9)',
                                'rgba(192, 192, 192, 0.9)',
                                'rgba(255, 0, 0, 0.9)',
                                'rgba(0, 0, 255, 0.9)',
                                'rgba(0, 255, 0, 0.9)',
                                'rgba(255, 255, 0, 0.9)',
                                'rgba(255, 165, 0, 0.9)',
                                'rgba(139, 69, 19, 0.9)'
                            ],
                            borderColor: 'white',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Gráfico de evolución de vehículos
                const fechasVehiculos = <?php echo json_encode(array_column($evolucion_vehiculos, 'fecha')); ?>;
                const totalesVehiculos = <?php echo json_encode(array_column($evolucion_vehiculos, 'total_vehiculos')); ?>;

                new Chart(document.getElementById('chartEvolucionVehiculos'), {
                    type: 'line',
                    data: {
                        labels: fechasVehiculos.map(f => f.split('-').reverse().join('/')),
                        datasets: [{
                            label: 'Total vehículos',
                            data: totalesVehiculos,
                            borderColor: 'rgba(243, 156, 18, 1)',
                            backgroundColor: 'rgba(243, 156, 18, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                </script>
            <?php endif; ?>
            
            <!-- Mensaje si no hay datos -->
            <?php if (($tipo_reporte == 'general' && empty($resumen['total_analisis'])) || 
                      ($tipo_reporte == 'congestion' && empty($niveles)) || 
                      ($tipo_reporte == 'vehiculos' && empty($tipos_vehiculos))): ?>
                <div class="card">
                    <div class="no-data-message">
                        <i class="fas fa-chart-line"></i>
                        <h5>No hay datos para el período seleccionado</h5>
                        <p>Intenta con un rango de fechas diferente</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Validar fechas
    $('#filterForm').on('submit', function(e) {
        const fechaInicio = $('input[name="fecha_inicio"]').val();
        const fechaFin = $('input[name="fecha_fin"]').val();
        
        if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
            e.preventDefault();
            alert('La fecha de inicio no puede ser mayor que la fecha fin');
        }
    });

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