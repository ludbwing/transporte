<?php
// api/upload.php - API para subir archivos
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Configuración
$upload_dir = '../uploads/';
$max_file_size = 500 * 1024 * 1024; // 500MB
$allowed_extensions = [
    'video' => ['mp4', 'avi', 'mov', 'mkv', 'webm'],
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp']
];

// Crear directorio si no existe
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar que se envió un archivo
if (!isset($_FILES['archivo'])) {
    echo json_encode(['success' => false, 'error' => 'No se envió ningún archivo']);
    exit;
}

$file = $_FILES['archivo'];
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'video';

// Verificar errores de subida
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo',
        UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión'
    ];
    
    $error_msg = isset($errors[$file['error']]) ? $errors[$file['error']] : 'Error desconocido';
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

// Verificar tamaño
if ($file['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'error' => 'El archivo excede el tamaño máximo de 500MB']);
    exit;
}

// Verificar extensión
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = $allowed_extensions[$tipo] ?? array_merge($allowed_extensions['video'], $allowed_extensions['image']);

if (!in_array($extension, $allowed)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Extensión no permitida. Extensiones válidas: ' . implode(', ', $allowed)
    ]);
    exit;
}

// Verificar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$video_mimes = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
$image_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];

if ($tipo === 'video' && !in_array($mime_type, $video_mimes)) {
    echo json_encode(['success' => false, 'error' => 'El archivo no es un video válido']);
    exit;
}

if ($tipo === 'imagen' && !in_array($mime_type, $image_mimes)) {
    echo json_encode(['success' => false, 'error' => 'El archivo no es una imagen válida']);
    exit;
}

// Generar nombre único
$timestamp = date('Ymd_His');
$nombre_limpio = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
$filename = $timestamp . '_' . $nombre_limpio;
$filepath = $upload_dir . $filename;

// Mover archivo
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    
    // Obtener información adicional según tipo
    $info = [];
    
    if ($tipo === 'video') {
        // Intentar obtener duración del video con FFmpeg
        $ffprobe_path = 'ffprobe'; // Asumiendo que está en PATH
        $cmd = "$ffprobe_path -v quiet -print_format json -show_format " . escapeshellarg($filepath);
        $output = shell_exec($cmd);
        
        if ($output) {
            $video_info = json_decode($output, true);
            if (isset($video_info['format']['duration'])) {
                $info['duracion'] = round($video_info['format']['duration']);
            }
        }
    } elseif ($tipo === 'imagen') {
        // Obtener dimensiones de la imagen
        list($width, $height) = getimagesize($filepath);
        $info['ancho'] = $width;
        $info['alto'] = $height;
    }
    
    // Registrar en base de datos
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO archivos_subidos (nombre_original, nombre_guardado, ruta, tipo, tamaño, usuario_id, metadata) 
              VALUES (:nombre_original, :nombre_guardado, :ruta, :tipo, :tamaño, :usuario_id, :metadata)";
    
    $metadata = json_encode($info);
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre_original', $file['name']);
    $stmt->bindParam(':nombre_guardado', $filename);
    $stmt->bindParam(':ruta', $filepath);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':tamaño', $file['size']);
    $stmt->bindParam(':usuario_id', $_SESSION['user_id']);
    $stmt->bindParam(':metadata', $metadata);
    
    if ($stmt->execute()) {
        $archivo_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'archivo' => [
                'id' => $archivo_id,
                'nombre' => $filename,
                'nombre_original' => $file['name'],
                'ruta' => $filepath,
                'url' => '/uploads/' . $filename,
                'tipo' => $tipo,
                'tamaño' => $file['size'],
                'tamaño_formateado' => formatBytes($file['size']),
                'info' => $info
            ],
            'mensaje' => 'Archivo subido correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'archivo' => [
                'nombre' => $filename,
                'ruta' => $filepath,
                'url' => '/uploads/' . $filename,
                'tipo' => $tipo,
                'tamaño' => $file['size'],
                'tamaño_formateado' => formatBytes($file['size'])
            ],
            'mensaje' => 'Archivo subido pero no registrado en BD'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error al mover el archivo'
    ]);
}

// Función para formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>