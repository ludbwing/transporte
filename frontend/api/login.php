<?php
// api/login.php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

$response = ['success' => false, 'message' => ''];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        $response['message'] = 'Usuario y contraseña son requeridos';
        echo json_encode($response);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, password, nombre, rol FROM usuarios WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar contraseña
        if(password_verify($password, $user['password']) || $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol']; // Guardar rol en sesión
            
            $response['success'] = true;
            $response['message'] = 'Login exitoso';
            $response['rol'] = $user['rol'];
        } else {
            $response['message'] = 'Contraseña incorrecta';
        }
    } else {
        $response['message'] = 'Usuario no encontrado';
    }
}

echo json_encode($response);
?>