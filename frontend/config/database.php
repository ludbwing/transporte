<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "trafico_vehicular";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Crear tablas de usuarios si no existen
function crearTablasUsuarios() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Tabla de usuarios
    $sql = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        nombre VARCHAR(100),
        email VARCHAR(100),
        rol VARCHAR(50) DEFAULT 'usuario',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Insertar usuario admin por defecto si no existe
    $check = $conn->query("SELECT * FROM usuarios WHERE username = 'admin'");
    if($check->rowCount() == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (username, password, nombre, email, rol) 
                VALUES ('admin', '$password', 'Administrador', 'admin@sistema.com', 'admin')";
        $conn->exec($sql);
    }
}

crearTablasUsuarios();
?>