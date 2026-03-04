<?php
// api/vehiculos.php - API para obtener vehículos de una sesión
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$sesion_id = isset($_GET['sesion_id']) ? (int)$_GET['sesion_id'] : 0;

if($sesion_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de sesión no válido']);
    exit;
}

try {
    $query = "SELECT id_vehiculo, tipo, color, primer_frame, ultimo_frame,
                     tiempo_aparicion, tiempo_desaparicion, 
                     TIMESTAMPDIFF(SECOND, tiempo_aparicion, COALESCE(tiempo_desaparicion, NOW())) as duracion
              FROM vehiculos 
              WHERE id_sesion = :sesion_id
              ORDER BY tiempo_aparicion";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sesion_id', $sesion_id);
    $stmt->execute();
    
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas
    foreach($vehiculos as &$v) {
        $v['tiempo_aparicion'] = date('d/m/Y H:i:s', strtotime($v['tiempo_aparicion']));
        $v['tiempo_desaparicion'] = $v['tiempo_desaparicion'] ? date('d/m/Y H:i:s', strtotime($v['tiempo_desaparicion'])) : 'En escena';
        
        // Formatear duración
        $duracion = $v['duracion'];
        if($duracion < 60) {
            $v['duracion_formateada'] = $duracion . 's';
        } elseif($duracion < 3600) {
            $v['duracion_formateada'] = floor($duracion / 60) . 'm ' . ($duracion % 60) . 's';
        } else {
            $v['duracion_formateada'] = floor($duracion / 3600) . 'h ' . floor(($duracion % 3600) / 60) . 'm';
        }
    }
    
    echo json_encode([
        'success' => true,
        'vehiculos' => $vehiculos,
        'total' => count($vehiculos)
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>