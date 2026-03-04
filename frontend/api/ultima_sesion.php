<?php
// api/ultima_sesion.php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener la última sesión del usuario actual
    $query = "SELECT id FROM sesiones ORDER BY id DESC LIMIT 1";
    $stmt = $db->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo json_encode([
            'success' => true, 
            'sesion_id' => $row['id']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'No hay sesiones'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>