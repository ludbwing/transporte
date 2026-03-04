<?php
// test_comunicacion.php
echo "<h1>🔧 Prueba de Comunicación PHP → Python</h1>";

$backend_url = 'http://127.0.0.1:5000';

// 1. Probar conexión básica
echo "<h2>1. Prueba de conexión básica</h2>";
$ch = curl_init($backend_url . '/api/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 200) {
    echo "<p style='color:green'>✅ Conexión exitosa a la API</p>";
    echo "<p>Respuesta: " . htmlspecialchars($response) . "</p>";
} else {
    echo "<p style='color:red'>❌ Error conectando a la API</p>";
    echo "<p>HTTP Code: " . $http_code . "</p>";
    echo "<p>Error: " . $curl_error . "</p>";
}

// 2. Probar envío de datos POST
echo "<h2>2. Prueba de envío POST</h2>";

$data = [
    'modo' => 'camara',
    'direccion' => 'Test Dirección',
    'duracion' => 10,
    'escala' => 0.8
];

$ch = curl_init($backend_url . '/api/analisis/iniciar');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<p>Código HTTP: " . $http_code . "</p>";

if ($response) {
    echo "<p>Respuesta: " . htmlspecialchars($response) . "</p>";
    $json = json_decode($response, true);
    if ($json && isset($json['success'])) {
        echo "<p style='color:green'>✅ POST exitoso</p>";
    } else {
        echo "<p style='color:orange'>⚠️ Respuesta recibida pero formato inesperado</p>";
    }
} else {
    echo "<p style='color:red'>❌ No se recibió respuesta</p>";
    if ($curl_error) {
        echo "<p>Error cURL: " . $curl_error . "</p>";
    }
}

// 3. Verificar configuración de PHP
echo "<h2>3. Configuración de PHP</h2>";
echo "<p><strong>allow_url_fopen:</strong> " . (ini_get('allow_url_fopen') ? '✅ On' : '❌ Off') . "</p>";
echo "<p><strong>cURL:</strong> " . (function_exists('curl_version') ? '✅ Habilitado' : '❌ No habilitado') . "</p>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "<p>Versión cURL: " . $curl_version['version'] . "</p>";
}
?>