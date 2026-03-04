<?php
// api/analisis.php
session_start();
header('Content-Type: application/json');

error_log("=== Iniciando análisis ===");

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$upload_dir = __DIR__ . '/../uploads/';
$backend_url = 'http://127.0.0.1:5000';

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

try {
    $direccion = $_POST['direccion'] ?? '';
    $modo = $_POST['modo'] ?? '';
    $duracion = isset($_POST['duracion']) ? (int)$_POST['duracion'] : 30;
    $escala = isset($_POST['escala']) ? (float)$_POST['escala'] : 0.8;
    
    error_log("Datos: modo=$modo, direccion=$direccion");
    
    if(empty($direccion) || empty($modo)) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit;
    }
    
    $archivo_path = '';
    
    // Procesar archivo
    if(isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['archivo'];
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['mp4', 'avi', 'mov', 'mkv', 'jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Extensión no permitida']);
            exit;
        }
        
        if ($file['size'] > 500 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Archivo demasiado grande (máx 500MB)']);
            exit;
        }
        
        $timestamp = date('Ymd_His');
        $archivo_nombre = $timestamp . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
        $archivo_path = $upload_dir . $archivo_nombre;
        
        if (!move_uploaded_file($file['tmp_name'], $archivo_path)) {
            echo json_encode(['success' => false, 'error' => 'Error al guardar archivo']);
            exit;
        }
        
        error_log("Archivo guardado: $archivo_path");
    }
    
    // Guardar en BD (SOLO UNA VEZ)
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO sesiones (direccion, fecha_inicio, tipo_fuente, fuente, estado) 
              VALUES (:direccion, NOW(), :tipo_fuente, :fuente, 'procesando')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':tipo_fuente', $modo);
    $stmt->bindParam(':fuente', $archivo_path);
    $stmt->execute();
    
    $sesion_id = $db->lastInsertId();
    error_log("Sesión creada en BD: ID=$sesion_id");
    
    // Llamar a Python con el sesion_id
    $data = [
        'modo' => $modo,
        'direccion' => $direccion,
        'fuente' => $modo === 'camara' ? null : $archivo_path,
        'duracion' => $duracion,
        'escala' => $escala,
        'sesion_id' => $sesion_id
    ];
    
    $ch = curl_init($backend_url . '/api/analisis/iniciar');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo json_encode([
        'success' => true,
        'sesion_id' => $sesion_id,
        'analisis_id' => json_decode($response, true)['analisis_id'] ?? null,
        'mensaje' => 'Análisis iniciado'
    ]);
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>