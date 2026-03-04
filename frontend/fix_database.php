<?php
// fix_database.php - Script para corregir la base de datos
require_once 'config/database.php';

echo "<h1>🔧 Corrección de Base de Datos</h1>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("<p style='color:red'>❌ Error conectando a la base de datos</p>");
}

echo "<p style='color:green'>✅ Conectado a la base de datos</p>";

// 1. Verificar columnas de la tabla sesiones
echo "<h2>1. Verificando tabla sesiones...</h2>";

$query = "DESCRIBE sesiones";
$stmt = $db->query($query);
$columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<p>Columnas actuales: " . implode(', ', $columnas) . "</p>";

if (!in_array('usuario_id', $columnas)) {
    echo "<p>⚠️ La columna 'usuario_id' no existe. Creándola...</p>";
    
    try {
        $db->exec("ALTER TABLE sesiones ADD COLUMN usuario_id INT NULL AFTER estado");
        echo "<p style='color:green'>✅ Columna usuario_id creada</p>";
        
        // Añadir foreign key
        $db->exec("ALTER TABLE sesiones ADD FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL");
        echo "<p style='color:green'>✅ Foreign key añadida</p>";
        
    } catch (Exception $e) {
        echo "<p style='color:orange'>⚠️ No se pudo crear la columna: " . $e->getMessage() . "</p>";
        echo "<p>Continuando sin la columna usuario_id...</p>";
    }
} else {
    echo "<p style='color:green'>✅ Columna usuario_id existe</p>";
}

// 2. Verificar que hay al menos un usuario
echo "<h2>2. Verificando usuarios...</h2>";

$query = "SELECT COUNT(*) as total FROM usuarios";
$stmt = $db->query($query);
$total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

if ($total_usuarios == 0) {
    echo "<p>⚠️ No hay usuarios. Creando usuario admin...</p>";
    
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $nombre = 'Administrador';
    
    $query = "INSERT INTO usuarios (username, password, nombre, rol) VALUES (?, ?, ?, 'admin')";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$username, $password, $nombre])) {
        echo "<p style='color:green'>✅ Usuario admin creado</p>";
    } else {
        echo "<p style='color:red'>❌ Error creando usuario</p>";
    }
} else {
    echo "<p style='color:green'>✅ Usuarios encontrados: $total_usuarios</p>";
}

// 3. Probar inserción sin usuario_id
echo "<h2>3. Probando inserción sin usuario_id...</h2>";

try {
    $query = "INSERT INTO sesiones (direccion, fecha_inicio, tipo_fuente, fuente, estado) 
              VALUES ('Test', NOW(), 'test', 'test', 'test')";
    $db->exec($query);
    echo "<p style='color:green'>✅ Inserción exitosa sin usuario_id</p>";
    
    // Limpiar el registro de prueba
    $db->exec("DELETE FROM sesiones WHERE direccion = 'Test'");
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error en inserción: " . $e->getMessage() . "</p>";
}

// 4. Recomendaciones finales
echo "<h2>4. Recomendaciones:</h2>";

if (!in_array('usuario_id', $columnas)) {
    echo "<p>🔧 <strong>Opción A:</strong> Ejecuta este SQL en phpMyAdmin:</p>";
    echo "<pre style='background:#f4f4f4; padding:10px;'>";
    echo "ALTER TABLE sesiones ADD COLUMN usuario_id INT NULL AFTER estado;\n";
    echo "ALTER TABLE sesiones ADD FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;";
    echo "</pre>";
    
    echo "<p>📝 <strong>Opción B:</strong> Usa el archivo api/analisis.php corregido (sin usuario_id)</p>";
} else {
    echo "<p style='color:green'>✅ Todo está correcto. Puedes usar el sistema normalmente.</p>";
}

echo "<p><a href='analisis.php' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>➡️ Ir a Nuevo Análisis</a></p>";
?>