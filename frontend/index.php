<?php
// index.php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Conteo Vehicular - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
:root {
    /* Nueva paleta de colores - Tráfico y congestión vehicular */
    --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    --secondary-gradient: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    --danger-gradient: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    --success-gradient: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    
    /* Colores sólidos */
    --primary-color: #1e3c72;
    --secondary-color: #f39c12;
    --danger-color: #e74c3c;
    --success-color: #27ae60;
    --warning-color: #f1c40f;
    
    /* Colores de tráfico */
    --traffic-low: #27ae60;
    --traffic-moderate: #f39c12;
    --traffic-high: #e74c3c;
    --traffic-heavy: #8e44ad;
    
    /* Tonos neutros */
    --text-dark: #2c3e50;
    --text-light: #ecf0f1;
    --bg-light: #f8f9fa;
    --bg-dark: #1a252f;
    --border-color: #dcdde1;
    
    /* Sombras y efectos */
    --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.15);
    --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.2);
    --shadow-traffic: 0 10px 30px rgba(231, 76, 60, 0.2);
    
    /* Bordes */
    --border-radius: 24px;
    --border-radius-sm: 14px;
    --border-radius-lg: 30px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    width: 100%;
    overflow: hidden;
}

body {
    background: linear-gradient(135deg, #0b1a2e 0%, #1a3a5c 50%, #0b1a2e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    padding: 16px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

/* Efecto de radar/tráfico animado */
body::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 30%, rgba(46, 204, 113, 0.03) 0%, transparent 30%),
        radial-gradient(circle at 80% 70%, rgba(241, 196, 15, 0.03) 0%, transparent 35%),
        radial-gradient(circle at 40% 80%, rgba(231, 76, 60, 0.03) 0%, transparent 40%),
        repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 8px);
    pointer-events: none;
    animation: radarScan 20s linear infinite;
}

@keyframes radarScan {
    0% { transform: rotate(0deg) scale(1); opacity: 0.5; }
    50% { transform: rotate(5deg) scale(1.05); opacity: 0.8; }
    100% { transform: rotate(0deg) scale(1); opacity: 0.5; }
}

.login-card {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    padding: 32px;
    width: 100%;
    max-width: 420px;
    max-height: min(600px, 90vh);
    overflow-y: auto;
    position: relative;
    animation: fadeInUp 0.6s ease-out;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-top: 4px solid var(--secondary-color);
}

/* Barra de progreso de congestión (decorativa) */
.login-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, 
        var(--traffic-low) 0%, 
        var(--traffic-moderate) 50%, 
        var(--traffic-high) 80%, 
        var(--traffic-heavy) 100%);
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    opacity: 0.3;
}

.login-card::-webkit-scrollbar {
    width: 4px;
}

.login-card::-webkit-scrollbar-track {
    background: transparent;
}

.login-card::-webkit-scrollbar-thumb {
    background: var(--secondary-color);
    border-radius: 4px;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-header {
    text-align: center;
    margin-bottom: 24px;
    position: relative;
}

/* Indicadores de tráfico animados */
.traffic-indicators {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 15px;
}

.traffic-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

.traffic-dot:nth-child(1) {
    background: var(--traffic-low);
    animation-delay: 0s;
}

.traffic-dot:nth-child(2) {
    background: var(--traffic-moderate);
    animation-delay: 0.2s;
}

.traffic-dot:nth-child(3) {
    background: var(--traffic-high);
    animation-delay: 0.4s;
}

.traffic-dot:nth-child(4) {
    background: var(--traffic-heavy);
    animation-delay: 0.6s;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.3);
        opacity: 1;
    }
}

.login-header i {
    font-size: 60px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    filter: drop-shadow(0 5px 15px rgba(30, 60, 114, 0.3));
}

.login-header h2 {
    color: var(--primary-color);
    font-weight: 700;
    font-size: clamp(20px, 5vw, 26px);
    margin-bottom: 6px;
    line-height: 1.2;
    letter-spacing: -0.5px;
}

.login-header p {
    color: #64748b;
    font-size: clamp(13px, 4vw, 15px);
    margin: 0;
}

/* Badge de estado de tráfico en tiempo real */
.live-traffic-status {
    display: inline-block;
    background: rgba(243, 156, 18, 0.1);
    padding: 5px 12px;
    border-radius: 20px;
    margin-top: 10px;
    font-size: 12px;
    color: var(--secondary-color);
    border: 1px solid rgba(243, 156, 18, 0.2);
}

.live-traffic-status i {
    font-size: 10px;
    margin-right: 5px;
    color: var(--traffic-moderate);
    -webkit-text-fill-color: var(--traffic-moderate);
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

#alertMessage {
    margin-bottom: 16px;
}

#alertMessage .alert {
    border-radius: var(--border-radius-sm);
    padding: 10px 14px;
    font-size: 14px;
    border: none;
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
    border-left: 4px solid var(--danger-color);
}

.form-group {
    margin-bottom: 16px;
    position: relative;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    font-size: 15px;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius-sm);
    background: white;
    transition: all 0.2s ease;
    color: var(--text-dark);
}

.form-control:focus {
    outline: none;
    border-color: var(--secondary-color);
    box-shadow: 0 0 0 4px rgba(243, 156, 18, 0.1);
}

.form-control::placeholder {
    color: #a0aec0;
    font-size: 14px;
}

/* Iconos de vehículos en inputs (opcional) */
.form-group {
    position: relative;
}

.form-group i {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
    transition: color 0.2s ease;
}

.form-control:focus + i {
    color: var(--secondary-color);
}

.btn-login {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-sm);
    padding: 14px 20px;
    width: 100%;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 8px 0 4px;
    letter-spacing: 0.3px;
    position: relative;
    overflow: hidden;
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(30, 60, 114, 0.4);
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login i {
    margin-right: 8px;
    font-size: 14px;
}

.user-demo {
    margin-top: 24px;
    padding: 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
    border-radius: var(--border-radius-sm);
    border: 1px solid rgba(30, 60, 114, 0.1);
}

.user-demo p {
    font-size: 12px;
    color: var(--primary-color);
    margin-bottom: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.user-demo p i {
    font-size: 14px;
    color: var(--secondary-color);
}

.demo-credentials {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.credential-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.credential-item:hover {
    transform: translateX(5px);
    border-left: 3px solid var(--secondary-color);
}

.credential-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.credential-info i {
    font-size: 14px;
    color: var(--primary-color);
}

.credential-info span {
    font-size: 13px;
    color: #334155;
    font-weight: 500;
}

.badge-admin, .badge-user {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.badge-admin {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
}

.badge-user {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

/* Media Queries */
@media (max-width: 480px) {
    body {
        padding: 12px;
    }
    
    .login-card {
        padding: 24px 20px;
        max-height: 95vh;
    }
    
    .login-header i {
        font-size: 48px;
    }
    
    .login-header h2 {
        font-size: 20px;
    }
    
    .traffic-dot {
        width: 10px;
        height: 10px;
    }
    
    .form-control {
        padding: 12px 14px;
        font-size: 15px;
    }
    
    .btn-login {
        padding: 12px;
    }
    
    .user-demo {
        padding: 14px;
        margin-top: 20px;
    }
    
    .credential-item {
        padding: 8px 12px;
    }
    
    .credential-info span {
        font-size: 12px;
    }
}

@media (max-width: 360px) {
    .login-card {
        padding: 20px 16px;
    }
    
    .login-header i {
        font-size: 40px;
    }
    
    .login-header h2 {
        font-size: 18px;
    }
    
    .traffic-dot {
        width: 8px;
        height: 8px;
    }
    
    .credential-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
}

/* Modo oscuro */
@media (prefers-color-scheme: dark) {
    .login-card {
        background: rgba(10, 20, 30, 0.98);
    }
    
    .login-header h2 {
        color: #ecf0f1;
    }
    
    .login-header p {
        color: #95a5a6;
    }
    
    .form-control {
        background: #1a2634;
        border-color: #2c3e50;
        color: #ecf0f1;
    }
    
    .form-control::placeholder {
        color: #7f8c8d;
    }
    
    .user-demo {
        background: linear-gradient(135deg, #1a2634 0%, #15202b 100%);
    }
    
    .credential-item {
        background: #1e2a36;
        border-color: #2c3e50;
    }
    
    .credential-info span {
        color: #bdc3c7;
    }
    
    .live-traffic-status {
        background: rgba(243, 156, 18, 0.2);
    }
}

/* Animación de tráfico en tiempo real (opcional) */
@keyframes trafficFlow {
    0% { background-position: 0% 0%; }
    100% { background-position: 200% 0%; }
}

.traffic-flow-animation {
    height: 2px;
    background: linear-gradient(90deg, 
        var(--traffic-low) 0%, 
        var(--traffic-moderate) 25%, 
        var(--traffic-high) 50%, 
        var(--traffic-heavy) 75%, 
        var(--traffic-low) 100%);
    background-size: 200% 100%;
    animation: trafficFlow 3s linear infinite;
    margin-top: 10px;
    border-radius: 2px;
    opacity: 0.5;
}
</style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-traffic-light"></i>
            <h2>Sistema de Conteo Vehicular</h2>
            <p class="text-muted">Ingresa tus credenciales</p>
        </div>
        
        <div id="alertMessage" style="display: none;"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <input type="text" class="form-control" id="username" placeholder="Usuario" required>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" id="password" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </button>
        </form>
        
        
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'api/login.php',
            type: 'POST',
            data: {
                username: $('#username').val(),
                password: $('#password').val()
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    $('#alertMessage').html('<div class="alert alert-danger">' + response.message + '</div>').show();
                }
            },
            error: function() {
                $('#alertMessage').html('<div class="alert alert-danger">Error en el servidor</div>').show();
            }
        });
    });
    </script>
</body>
</html>