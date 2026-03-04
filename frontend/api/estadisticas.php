<?php
// api/estadisticas.php - API para obtener estadísticas
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'generales';
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';

try {
    switch($tipo) {
        case 'generales':
            obtenerEstadisticasGenerales($db);
            break;
        case 'por_tipo':
            obtenerEstadisticasPorTipo($db, $periodo);
            break;
        case 'por_color':
            obtenerEstadisticasPorColor($db, $periodo);
            break;
        case 'por_hora':
            obtenerEstadisticasPorHora($db);
            break;
        case 'congestion':
            obtenerEstadisticasCongestion($db, $periodo);
            break;
        case 'comparativa':
            obtenerComparativa($db);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Tipo de estadística no válido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function obtenerEstadisticasGenerales($db) {
    // Total de análisis
    $query = "SELECT COUNT(*) as total FROM sesiones";
    $stmt = $db->query($query);
    $total_analisis = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total vehículos
    $query = "SELECT SUM(total_vehiculos) as total FROM sesiones";
    $stmt = $db->query($query);
    $total_vehiculos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Promedio congestión
    $query = "SELECT AVG(promedio_vehiculos) as promedio FROM resumen_congestion";
    $stmt = $db->query($query);
    $promedio_congestion = round($stmt->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 2);
    
    // Análisis hoy
    $query = "SELECT COUNT(*) as total FROM sesiones WHERE DATE(fecha_inicio) = CURDATE()";
    $stmt = $db->query($query);
    $analisis_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Vehículos hoy
    $query = "SELECT SUM(total_vehiculos) as total FROM sesiones WHERE DATE(fecha_inicio) = CURDATE()";
    $stmt = $db->query($query);
    $vehiculos_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Por tipo de fuente
    $query = "SELECT tipo_fuente, COUNT(*) as cantidad, SUM(total_vehiculos) as vehiculos 
              FROM sesiones GROUP BY tipo_fuente";
    $stmt = $db->query($query);
    $por_fuente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Actividad reciente (últimos 7 días)
    $query = "SELECT DATE(fecha_inicio) as fecha, COUNT(*) as analisis, SUM(total_vehiculos) as vehiculos 
              FROM sesiones 
              WHERE fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(fecha_inicio)
              ORDER BY fecha";
    $stmt = $db->query($query);
    $actividad_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 direcciones más analizadas
    $query = "SELECT direccion, COUNT(*) as veces, SUM(total_vehiculos) as vehiculos 
              FROM sesiones 
              GROUP BY direccion 
              ORDER BY veces DESC 
              LIMIT 5";
    $stmt = $db->query($query);
    $top_direcciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'total_analisis' => $total_analisis,
            'total_vehiculos' => $total_vehiculos,
            'promedio_congestion' => $promedio_congestion,
            'analisis_hoy' => $analisis_hoy,
            'vehiculos_hoy' => $vehiculos_hoy,
            'por_fuente' => $por_fuente,
            'actividad_reciente' => $actividad_reciente,
            'top_direcciones' => $top_direcciones,
            'fecha_actualizacion' => date('Y-m-d H:i:s')
        ]
    ]);
}

function obtenerEstadisticasPorTipo($db, $periodo) {
    $filtro_fecha = obtenerFiltroFecha($periodo);
    
    $query = "SELECT et.tipo, SUM(et.cantidad) as total, COUNT(DISTINCT s.id) as sesiones
              FROM estadisticas_tipo et
              JOIN sesiones s ON et.id_sesion = s.id
              WHERE 1=1 $filtro_fecha
              GROUP BY et.tipo
              ORDER BY total DESC";
    
    $stmt = $db->query($query);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular porcentajes
    $total = array_sum(array_column($resultados, 'total'));
    foreach($resultados as &$item) {
        $item['porcentaje'] = $total > 0 ? round(($item['total'] / $total) * 100, 1) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'datos' => $resultados,
        'total' => $total,
        'periodo' => $periodo
    ]);
}

function obtenerEstadisticasPorColor($db, $periodo) {
    $filtro_fecha = obtenerFiltroFecha($periodo);
    
    $query = "SELECT ec.color, SUM(ec.cantidad) as total, COUNT(DISTINCT s.id) as sesiones
              FROM estadisticas_color ec
              JOIN sesiones s ON ec.id_sesion = s.id
              WHERE 1=1 $filtro_fecha AND ec.color != 'desconocido'
              GROUP BY ec.color
              ORDER BY total DESC";
    
    $stmt = $db->query($query);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapa de colores para gráficos
    $color_map = [
        'blanco' => '#ffffff',
        'negro' => '#000000',
        'gris' => '#808080',
        'plateado' => '#c0c0c0',
        'rojo' => '#ff0000',
        'azul' => '#0000ff',
        'verde' => '#00ff00',
        'amarillo' => '#ffff00',
        'naranja' => '#ffa500',
        'marron' => '#8b4513'
    ];
    
    foreach($resultados as &$item) {
        $item['color_hex'] = $color_map[$item['color']] ?? '#cccccc';
    }
    
    echo json_encode([
        'success' => true,
        'datos' => $resultados,
        'periodo' => $periodo
    ]);
}

function obtenerEstadisticasPorHora($db) {
    $query = "SELECT HOUR(s.fecha_inicio) as hora, 
                     COUNT(*) as analisis,
                     SUM(s.total_vehiculos) as vehiculos,
                     AVG(rc.promedio_vehiculos) as congestión_promedio
              FROM sesiones s
              LEFT JOIN resumen_congestion rc ON s.id = rc.id_sesion
              GROUP BY HOUR(s.fecha_inicio)
              ORDER BY hora";
    
    $stmt = $db->query($query);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar datos para gráfico de 24 horas
    $horas = range(0, 23);
    $datos_completos = [];
    
    foreach($horas as $hora) {
        $encontrado = false;
        foreach($resultados as $item) {
            if($item['hora'] == $hora) {
                $datos_completos[] = $item;
                $encontrado = true;
                break;
            }
        }
        if(!$encontrado) {
            $datos_completos[] = [
                'hora' => $hora,
                'analisis' => 0,
                'vehiculos' => 0,
                'congestión_promedio' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'datos' => $datos_completos
    ]);
}

function obtenerEstadisticasCongestion($db, $periodo) {
    $filtro_fecha = obtenerFiltroFecha($periodo, 's.fecha_inicio');
    
    $query = "SELECT s.id, s.direccion, s.fecha_inicio, 
                     rc.promedio_vehiculos, rc.max_vehiculos, rc.nivel_general,
                     s.total_vehiculos
              FROM sesiones s
              JOIN resumen_congestion rc ON s.id = rc.id_sesion
              WHERE 1=1 $filtro_fecha
              ORDER BY s.fecha_inicio DESC";
    
    $stmt = $db->query($query);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas de congestión
    $niveles = [
        'FLUJO LIBRE' => 0,
        'MODERADO' => 0,
        'CONGESTIÓN ALTA' => 0,
        'CONGESTIÓN SEVERA' => 0
    ];
    
    $total_promedios = 0;
    $total_maximos = 0;
    
    foreach($resultados as $item) {
        if(isset($niveles[$item['nivel_general']])) {
            $niveles[$item['nivel_general']]++;
        }
        $total_promedios += $item['promedio_vehiculos'];
        $total_maximos += $item['max_vehiculos'];
    }
    
    $cantidad = count($resultados);
    
    echo json_encode([
        'success' => true,
        'datos' => $resultados,
        'resumen' => [
            'promedio_general' => $cantidad > 0 ? round($total_promedios / $cantidad, 2) : 0,
            'maximo_general' => $cantidad > 0 ? round($total_maximos / $cantidad, 2) : 0,
            'distribucion_niveles' => $niveles,
            'total_muestras' => $cantidad
        ],
        'periodo' => $periodo
    ]);
}

function obtenerComparativa($db) {
    // Comparativa mes actual vs mes anterior
    $query_actual = "SELECT SUM(total_vehiculos) as vehiculos, COUNT(*) as analisis
                     FROM sesiones 
                     WHERE MONTH(fecha_inicio) = MONTH(CURDATE()) 
                     AND YEAR(fecha_inicio) = YEAR(CURDATE())";
    
    $stmt = $db->query($query_actual);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $query_anterior = "SELECT SUM(total_vehiculos) as vehiculos, COUNT(*) as analisis
                       FROM sesiones 
                       WHERE MONTH(fecha_inicio) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                       AND YEAR(fecha_inicio) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    
    $stmt = $db->query($query_anterior);
    $anterior = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular variaciones
    $var_vehiculos = 0;
    $var_analisis = 0;
    
    if($anterior['vehiculos'] > 0) {
        $var_vehiculos = round((($actual['vehiculos'] - $anterior['vehiculos']) / $anterior['vehiculos']) * 100, 1);
    }
    
    if($anterior['analisis'] > 0) {
        $var_analisis = round((($actual['analisis'] - $anterior['analisis']) / $anterior['analisis']) * 100, 1);
    }
    
    echo json_encode([
        'success' => true,
        'comparativa' => [
            'actual' => [
                'vehiculos' => $actual['vehiculos'] ?? 0,
                'analisis' => $actual['analisis'] ?? 0
            ],
            'anterior' => [
                'vehiculos' => $anterior['vehiculos'] ?? 0,
                'analisis' => $anterior['analisis'] ?? 0
            ],
            'variacion' => [
                'vehiculos' => $var_vehiculos,
                'analisis' => $var_analisis
            ]
        ]
    ]);
}

function obtenerFiltroFecha($periodo, $campo = 'fecha_inicio') {
    switch($periodo) {
        case 'hoy':
            return " AND DATE($campo) = CURDATE()";
        case 'semana':
            return " AND $campo >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        case 'mes':
            return " AND $campo >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        case 'trimestre':
            return " AND $campo >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        case 'año':
            return " AND $campo >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        default:
            return "";
    }
}
?>