<?php
// historial.php
session_start();
require_once 'includes/auth.php';
verificarAcceso('usuario');
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener valores de filtros desde URL
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_direccion = isset($_GET['direccion']) ? $_GET['direccion'] : '';
$filtro_nivel = isset($_GET['nivel']) ? $_GET['nivel'] : '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($fecha_desde)) {
    $where_conditions[] = "DATE(s.fecha_inicio) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "DATE(s.fecha_inicio) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "s.tipo_fuente = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "s.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if (!empty($filtro_direccion)) {
    $where_conditions[] = "s.direccion LIKE :direccion";
    $params[':direccion'] = '%' . $filtro_direccion . '%';
}

if (!empty($filtro_nivel)) {
    $where_conditions[] = "rc.nivel_general = :nivel";
    $params[':nivel'] = $filtro_nivel;
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Parámetros de paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Obtener total de registros con filtros
$query = "SELECT COUNT(*) as total FROM sesiones s 
          LEFT JOIN resumen_congestion rc ON s.id = rc.id_sesion 
          $where_sql";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener sesiones con filtros y paginación
$query = "SELECT s.*, rc.nivel_general, rc.promedio_vehiculos, rc.max_vehiculos
          FROM sesiones s 
          LEFT JOIN resumen_congestion rc ON s.id = rc.id_sesion 
          $where_sql
          ORDER BY s.fecha_inicio DESC 
          LIMIT :offset, :por_pagina";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$sesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener direcciones únicas para el filtro
$query = "SELECT DISTINCT direccion FROM sesiones ORDER BY direccion";
$stmt = $db->query($query);
$direcciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para generar URL con filtros
function urlWithFilters($params = []) {
    $current_params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '') {
            unset($current_params[$key]);
        } else {
            $current_params[$key] = $value;
        }
    }
    return '?' . http_build_query($current_params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Historial - Sistema de Detección de Congestión Vehicular</title>
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

        /* ===== BOTONES DE FILTRO ===== */
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

        .btn-clear {
            background: var(--border-color);
            color: var(--text-primary);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 48px;
            text-decoration: none;
        }

        .btn-clear:hover {
            background: #d1d5db;
            color: var(--text-primary);
        }

        /* ===== FILTROS ACTIVOS ===== */
        .active-filters {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .active-filters strong {
            color: var(--text-primary);
            font-size: 14px;
            display: block;
            margin-bottom: 10px;
        }

        .filter-badge {
            background: var(--hover-bg);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            margin-right: 10px;
            margin-bottom: 10px;
            display: inline-block;
            border: 1px solid var(--border-color);
        }

        .filter-badge i {
            margin-left: 8px;
            cursor: pointer;
            color: var(--traffic-high);
            font-size: 12px;
        }

        .filter-badge i:hover {
            color: #c0392b;
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

        .card-header .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-info {
            background: rgba(52, 152, 219, 0.1);
            color: #2980b9;
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--traffic-low);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        /* ===== TABLAS ===== */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-medium));
        }

        .table thead th {
            padding: 16px 12px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px 12px;
            font-size: 13px;
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
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-congestion {
            padding: 5px 10px;
            font-size: 11px;
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

        .bg-dark {
            background: rgba(142, 68, 173, 0.1);
            color: var(--traffic-heavy);
            border: 1px solid rgba(142, 68, 173, 0.2);
        }

        .bg-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--text-muted);
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        /* ===== BOTONES ===== */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            min-height: 36px;
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
            padding: 6px 12px;
            font-size: 11px;
            min-height: 32px;
        }

        /* ===== PAGINACIÓN ===== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin: 24px 0;
            flex-wrap: wrap;
            padding: 0 10px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 10px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            transition: var(--transition);
            font-size: 13px;
            font-weight: 500;
        }

        .page-link:hover {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            border-color: transparent;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
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

        .no-data-message a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* ===== RESPONSIVE DESIGN ===== */

        /* Large Desktop */
        @media (min-width: 1400px) {
            .main-content {
                padding: 32px;
            }
            
            .table {
                min-width: 1400px;
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
            
            .filters-container {
                padding: 18px;
            }
            
            .row {
                margin: 0;
            }
            
            .col-md-3, .col-md-2, .col-md-4 {
                padding: 0 8px;
                margin-bottom: 16px;
            }
            
            .d-flex.align-items-end {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-filter, .btn-clear {
                width: 100%;
                justify-content: center;
            }
            
            .card-header {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-header h3 {
                font-size: 16px;
            }
            
            .table {
                min-width: 1000px;
            }
            
            .table thead th {
                padding: 12px 10px;
                font-size: 12px;
            }
            
            .table tbody td {
                padding: 10px;
                font-size: 12px;
            }
            
            .pagination {
                gap: 3px;
            }
            
            .page-link {
                min-width: 36px;
                height: 36px;
                font-size: 12px;
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
            
            .btn-filter, .btn-clear {
                padding: 10px 20px;
                font-size: 13px;
                min-height: 44px;
            }
            
            .card-header {
                padding: 14px 16px;
            }
            
            .card-header h3 {
                font-size: 15px;
            }
            
            .card-header .badge {
                font-size: 11px;
            }
            
            .table {
                min-width: 900px;
            }
            
            .btn-sm {
                padding: 5px 10px;
                font-size: 10px;
                min-height: 30px;
            }
            
            .filter-badge {
                font-size: 12px;
                padding: 6px 12px;
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
            
            .table {
                min-width: 800px;
            }
            
            .table thead th {
                padding: 10px 8px;
                font-size: 11px;
            }
            
            .table tbody td {
                padding: 8px;
                font-size: 11px;
            }
            
            .badge {
                padding: 4px 8px;
                font-size: 10px;
            }
            
            .pagination {
                gap: 2px;
            }
            
            .page-link {
                min-width: 32px;
                height: 32px;
                font-size: 11px;
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
            .table tbody tr:hover {
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
        
        .py-5 {
            padding-top: 48px;
            padding-bottom: 48px;
        }

        @media (max-width: 768px) {
            .mt-4 {
                margin-top: 20px;
            }
            
            .mb-3 {
                margin-bottom: 14px;
            }
            
            .py-5 {
                padding-top: 40px;
                padding-bottom: 40px;
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
                <a href="historial.php" class="active">
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
                    <h2>Historial de Análisis</h2>
                    <p>Total de registros: <strong><?php echo $total_registros; ?></strong></p>
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
                <form method="GET" action="historial.php" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">📅 Fecha desde</label>
                            <input type="date" class="form-control" name="fecha_desde" 
                                   value="<?php echo htmlspecialchars($fecha_desde); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">📅 Fecha hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" 
                                   value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">🎥 Tipo</label>
                            <select class="form-control" name="tipo">
                                <option value="">Todos</option>
                                <option value="camara" <?php echo $filtro_tipo == 'camara' ? 'selected' : ''; ?>>📹 Cámara</option>
                                <option value="video" <?php echo $filtro_tipo == 'video' ? 'selected' : ''; ?>>🎥 Video</option>
                                <option value="imagen" <?php echo $filtro_tipo == 'imagen' ? 'selected' : ''; ?>>🖼️ Imagen</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">📊 Estado</label>
                            <select class="form-control" name="estado">
                                <option value="">Todos</option>
                                <option value="activa" <?php echo $filtro_estado == 'activa' ? 'selected' : ''; ?>>🟢 Activa</option>
                                <option value="procesando" <?php echo $filtro_estado == 'procesando' ? 'selected' : ''; ?>>🟡 Procesando</option>
                                <option value="finalizada" <?php echo $filtro_estado == 'finalizada' ? 'selected' : ''; ?>>✅ Finalizada</option>
                                <option value="error" <?php echo $filtro_estado == 'error' ? 'selected' : ''; ?>>❌ Error</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">📍 Dirección</label>
                            <input type="text" class="form-control" name="direccion" 
                                   placeholder="Buscar por dirección..." 
                                   value="<?php echo htmlspecialchars($filtro_direccion); ?>"
                                   list="direcciones-list">
                            <datalist id="direcciones-list">
                                <?php foreach ($direcciones as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d['direccion']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">🚦 Nivel de congestión</label>
                            <select class="form-control" name="nivel">
                                <option value="">Todos</option>
                                <option value="FLUJO LIBRE" <?php echo $filtro_nivel == 'FLUJO LIBRE' ? 'selected' : ''; ?>>✅ Flujo Libre</option>
                                <option value="MODERADO" <?php echo $filtro_nivel == 'MODERADO' ? 'selected' : ''; ?>>🟡 Moderado</option>
                                <option value="CONGESTIÓN ALTA" <?php echo $filtro_nivel == 'CONGESTIÓN ALTA' ? 'selected' : ''; ?>>🟠 Congestión Alta</option>
                                <option value="CONGESTIÓN SEVERA" <?php echo $filtro_nivel == 'CONGESTIÓN SEVERA' ? 'selected' : ''; ?>>🔴 Congestión Severa</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn-filter me-2">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="historial.php" class="btn-clear">
                                <i class="fas fa-eraser"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
                
                <!-- Filtros activos -->
                <?php 
                $filtros_activos = array_filter([
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $fecha_hasta,
                    'tipo' => $filtro_tipo,
                    'estado' => $filtro_estado,
                    'direccion' => $filtro_direccion,
                    'nivel' => $filtro_nivel
                ]);
                
                if (!empty($filtros_activos)): 
                ?>
                <div class="active-filters">
                    <strong>Filtros activos:</strong>
                    <?php foreach ($filtros_activos as $key => $value): ?>
                        <span class="filter-badge">
                            <?php 
                            $labels = [
                                'fecha_desde' => 'Desde',
                                'fecha_hasta' => 'Hasta',
                                'tipo' => 'Tipo',
                                'estado' => 'Estado',
                                'direccion' => 'Dirección',
                                'nivel' => 'Nivel'
                            ];
                            echo $labels[$key] . ': ' . htmlspecialchars($value);
                            ?>
                            <i class="fas fa-times" onclick="removeFilter('<?php echo $key; ?>')"></i>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tabla de historial -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Listado de Análisis</h3>
                    <div>
                        <span class="badge badge-info me-2">Total: <?php echo $total_registros; ?></span>
                        <span class="badge badge-success">Página <?php echo $pagina; ?> de <?php echo $total_paginas ?: 1; ?></span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Dirección</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Tipo</th>
                                <th>Vehículos</th>
                                <th>Duración</th>
                                <th>Promedio</th>
                                <th>Máximo</th>
                                <th>Nivel</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sesiones as $sesion): ?>
                            <tr>
                                <td><strong>#<?php echo $sesion['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($sesion['direccion']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sesion['fecha_inicio'])); ?></td>
                                <td><?php echo $sesion['fecha_fin'] ? date('d/m/Y H:i', strtotime($sesion['fecha_fin'])) : '-'; ?></td>
                                <td>
                                    <?php 
                                    $iconos = [
                                        'camara' => '📹',
                                        'video' => '🎥',
                                        'imagen' => '🖼️'
                                    ];
                                    echo $iconos[$sesion['tipo_fuente']] ?? '📁';
                                    echo ' ' . ($sesion['tipo_fuente'] ?? 'N/A');
                                    ?>
                                </td>
                                <td class="text-center"><strong><?php echo $sesion['total_vehiculos'] ?? 0; ?></strong></td>
                                <td><?php echo $sesion['duracion_segundos'] ? $sesion['duracion_segundos'] . 's' : '-'; ?></td>
                                <td class="text-center"><?php echo number_format($sesion['promedio_vehiculos'] ?? 0, 1); ?></td>
                                <td class="text-center"><?php echo $sesion['max_vehiculos'] ?? 0; ?></td>
                                <td>
                                    <?php 
                                    $nivel = $sesion['nivel_general'] ?? 'N/A';
                                    $clase = '';
                                    if (strpos($nivel, 'LIBRE') !== false) $clase = 'success';
                                    elseif (strpos($nivel, 'MODERADO') !== false) $clase = 'warning';
                                    elseif (strpos($nivel, 'ALTA') !== false) $clase = 'danger';
                                    elseif (strpos($nivel, 'SEVERA') !== false) $clase = 'dark';
                                    else $clase = 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $clase; ?> badge-congestion">
                                        <?php echo $nivel; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $estado = $sesion['estado'] ?? 'desconocido';
                                    $estado_clase = '';
                                    $estado_icono = '';
                                    
                                    if ($estado == 'activa') {
                                        $estado_clase = 'success';
                                        $estado_icono = '🟢';
                                    } elseif ($estado == 'procesando') {
                                        $estado_clase = 'warning';
                                        $estado_icono = '🟡';
                                    } elseif ($estado == 'finalizada') {
                                        $estado_clase = 'secondary';
                                        $estado_icono = '✅';
                                    } elseif ($estado == 'error') {
                                        $estado_clase = 'danger';
                                        $estado_icono = '❌';
                                    } else {
                                        $estado_clase = 'light';
                                        $estado_icono = '⚪';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $estado_clase; ?>">
                                        <?php echo $estado_icono . ' ' . ucfirst($estado); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ver_analisis.php?id=<?php echo $sesion['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($sesiones)): ?>
                            <tr>
                                <td colspan="12">
                                    <div class="no-data-message">
                                        <i class="fas fa-search"></i>
                                        <h5>No se encontraron resultados</h5>
                                        <p>Intenta con otros filtros o <a href="historial.php">limpia la búsqueda</a></p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo urlWithFilters(['pagina' => $pagina-1]); ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        </li>
                        
                        <?php
                        $start = max(1, $pagina - 2);
                        $end = min($total_paginas, $pagina + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . urlWithFilters(['pagina' => 1]) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                        <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo urlWithFilters(['pagina' => $i]); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_paginas): ?>
                            <?php if ($end < $total_paginas - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo urlWithFilters(['pagina' => $total_paginas]); ?>">
                                    <?php echo $total_paginas; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo urlWithFilters(['pagina' => $pagina+1]); ?>">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Función para eliminar un filtro específico
    function removeFilter(filterName) {
        const url = new URL(window.location.href);
        url.searchParams.delete(filterName);
        url.searchParams.delete('pagina'); // Resetear paginación
        window.location.href = url.toString();
    }
    
    // Validar fechas
    $('#filterForm').on('submit', function(e) {
        const fechaDesde = $('input[name="fecha_desde"]').val();
        const fechaHasta = $('input[name="fecha_hasta"]').val();
        
        if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
            e.preventDefault();
            alert('La fecha "desde" no puede ser mayor que la fecha "hasta"');
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