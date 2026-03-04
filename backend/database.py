import mysql.connector
from mysql.connector import Error
from datetime import datetime
import json
import os

class DatabaseManager:
    def __init__(self, host='localhost', database='trafico_vehicular', 
                 user='root', password=''):
        """
        Inicializa la conexión a la base de datos MySQL
        """
        self.host = host
        self.database = database
        self.user = user
        self.password = password
        self.connection = None
        self.cursor = None
        
        # Intentar conectar
        self.conectar()
        
        # Crear tablas si no existen
        if self.connection:
            self.crear_tablas()
    
    def conectar(self):
        """Establece conexión con la base de datos"""
        try:
            # Primero intentar conectar sin base de datos específica
            self.connection = mysql.connector.connect(
                host=self.host,
                user=self.user,
                password=self.password
            )
            self.cursor = self.connection.cursor()
            
            # Crear base de datos si no existe
            self.cursor.execute(f"CREATE DATABASE IF NOT EXISTS {self.database}")
            self.cursor.execute(f"USE {self.database}")
            
            print("✅ Conectado a MySQL correctamente")
            
        except Error as e:
            print(f"❌ Error conectando a MySQL: {e}")
            self.connection = None
            self.cursor = None
    
    def cerrar(self):
        """Cierra la conexión a la base de datos"""
        if self.cursor:
            self.cursor.close()
        if self.connection:
            self.connection.close()
            print("🔌 Conexión a MySQL cerrada")
    
    def crear_tablas(self):
        """Crea todas las tablas necesarias"""
        try:
            # Tabla de sesiones de análisis
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS sesiones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    direccion VARCHAR(255) NOT NULL,
                    fecha_inicio DATETIME NOT NULL,
                    fecha_fin DATETIME,
                    tipo_fuente VARCHAR(50),
                    fuente VARCHAR(255),
                    total_vehiculos INT DEFAULT 0,
                    duracion_segundos INT,
                    estado VARCHAR(50) DEFAULT 'activa',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            
            # Tabla de vehículos detectados
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS vehiculos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_sesion INT NOT NULL,
                    id_vehiculo INT NOT NULL,
                    tipo VARCHAR(50) NOT NULL,
                    color VARCHAR(50),
                    primer_frame INT,
                    ultimo_frame INT,
                    tiempo_aparicion DATETIME,
                    tiempo_desaparicion DATETIME,
                    tiempo_segundos INT,
                    FOREIGN KEY (id_sesion) REFERENCES sesiones(id) ON DELETE CASCADE,
                    INDEX idx_sesion (id_sesion),
                    INDEX idx_vehiculo (id_vehiculo)
                )
            """)
            
            # Tabla de estadísticas por tipo
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS estadisticas_tipo (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_sesion INT NOT NULL,
                    tipo VARCHAR(50) NOT NULL,
                    cantidad INT NOT NULL,
                    FOREIGN KEY (id_sesion) REFERENCES sesiones(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_sesion_tipo (id_sesion, tipo)
                )
            """)
            
            # Tabla de estadísticas por color
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS estadisticas_color (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_sesion INT NOT NULL,
                    color VARCHAR(50) NOT NULL,
                    cantidad INT NOT NULL,
                    FOREIGN KEY (id_sesion) REFERENCES sesiones(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_sesion_color (id_sesion, color)
                )
            """)
            
            # Tabla de muestras de congestión
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS muestras_congestion (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_sesion INT NOT NULL,
                    timestamp DATETIME NOT NULL,
                    frame INT,
                    vehiculos_simultaneos INT NOT NULL,
                    nivel_congestion VARCHAR(50),
                    promedio_vehiculos DECIMAL(5,2),
                    FOREIGN KEY (id_sesion) REFERENCES sesiones(id) ON DELETE CASCADE,
                    INDEX idx_sesion_timestamp (id_sesion, timestamp)
                )
            """)
            
            # Tabla de resumen de congestión
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS resumen_congestion (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_sesion INT NOT NULL,
                    promedio_vehiculos DECIMAL(5,2) NOT NULL,
                    max_vehiculos INT NOT NULL,
                    nivel_general VARCHAR(50),
                    FOREIGN KEY (id_sesion) REFERENCES sesiones(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_sesion (id_sesion)
                )
            """)
            
            # Tabla de logs del sistema
            self.cursor.execute("""
                CREATE TABLE IF NOT EXISTS logs_sistema (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_sesion INT,
                    nivel VARCHAR(20),
                    mensaje TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_sesion) REFERENCES sesiones(id) ON DELETE SET NULL,
                    INDEX idx_timestamp (timestamp)
                )
            """)
            
            self.connection.commit()
            print("✅ Tablas creadas/verificadas correctamente")
            
        except Error as e:
            print(f"❌ Error creando tablas: {e}")
    
    def iniciar_sesion(self, direccion, tipo_fuente, fuente):
        """
        Inicia una nueva sesión de análisis
        Retorna el ID de la sesión
        """
        try:
            query = """
                INSERT INTO sesiones (direccion, fecha_inicio, tipo_fuente, fuente, estado)
                VALUES (%s, %s, %s, %s, %s)
            """
            values = (direccion, datetime.now(), tipo_fuente, fuente, 'activa')
            
            self.cursor.execute(query, values)
            self.connection.commit()
            
            sesion_id = self.cursor.lastrowid
            print(f"📝 Sesión iniciada (ID: {sesion_id})")
            
            # Log de inicio
            self.registrar_log(sesion_id, 'INFO', f'Sesión iniciada - {direccion}')
            
            return sesion_id
            
        except Error as e:
            print(f"❌ Error iniciando sesión: {e}")
            return None
    
    def finalizar_sesion(self, sesion_id, total_vehiculos):
        """
        Finaliza una sesión de análisis
        """
        try:
            # Obtener fecha de inicio
            self.cursor.execute(
                "SELECT fecha_inicio FROM sesiones WHERE id = %s", 
                (sesion_id,)
            )
            result = self.cursor.fetchone()
            
            if result:
                fecha_inicio = result[0]
                fecha_fin = datetime.now()
                duracion = int((fecha_fin - fecha_inicio).total_seconds())
                
                query = """
                    UPDATE sesiones 
                    SET fecha_fin = %s, total_vehiculos = %s, 
                        duracion_segundos = %s, estado = %s
                    WHERE id = %s
                """
                values = (fecha_fin, total_vehiculos, duracion, 'finalizada', sesion_id)
                
                self.cursor.execute(query, values)
                self.connection.commit()
                
                print(f"✅ Sesión {sesion_id} finalizada - Duración: {duracion}s")
                self.registrar_log(sesion_id, 'INFO', f'Sesión finalizada - {total_vehiculos} vehículos')
                
        except Error as e:
            print(f"❌ Error finalizando sesión: {e}")
    
    def guardar_vehiculo(self, sesion_id, vehiculo_info):
        """
        Guarda información de un vehículo detectado
        """
        try:
            # Verificar si ya existe
            self.cursor.execute("""
                SELECT id FROM vehiculos 
                WHERE id_sesion = %s AND id_vehiculo = %s
            """, (sesion_id, vehiculo_info['id']))
            
            if self.cursor.fetchone():
                # Actualizar
                query = """
                    UPDATE vehiculos 
                    SET ultimo_frame = %s, tiempo_desaparicion = %s, 
                        tiempo_segundos = TIMESTAMPDIFF(SECOND, tiempo_aparicion, %s)
                    WHERE id_sesion = %s AND id_vehiculo = %s
                """
                values = (
                    vehiculo_info['ultimo_frame'],
                    datetime.now(),
                    datetime.now(),
                    sesion_id,
                    vehiculo_info['id']
                )
            else:
                # Insertar nuevo
                query = """
                    INSERT INTO vehiculos 
                    (id_sesion, id_vehiculo, tipo, color, primer_frame, 
                     ultimo_frame, tiempo_aparicion)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """
                values = (
                    sesion_id,
                    vehiculo_info['id'],
                    vehiculo_info['tipo'],
                    vehiculo_info['color'],
                    vehiculo_info['primer_frame'],
                    vehiculo_info['ultimo_frame'],
                    datetime.now()
                )
            
            self.cursor.execute(query, values)
            self.connection.commit()
            
        except Error as e:
            print(f"❌ Error guardando vehículo: {e}")
    
    def guardar_estadisticas(self, sesion_id, stats_por_tipo, stats_por_color):
        """
        Guarda estadísticas agregadas
        """
        try:
            # Por tipo
            for tipo, cantidad in stats_por_tipo.items():
                query = """
                    INSERT INTO estadisticas_tipo (id_sesion, tipo, cantidad)
                    VALUES (%s, %s, %s)
                    ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)
                """
                self.cursor.execute(query, (sesion_id, tipo, cantidad))
            
            # Por color
            for color, cantidad in stats_por_color.items():
                query = """
                    INSERT INTO estadisticas_color (id_sesion, color, cantidad)
                    VALUES (%s, %s, %s)
                    ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)
                """
                self.cursor.execute(query, (sesion_id, color, cantidad))
            
            self.connection.commit()
            print("📊 Estadísticas guardadas en BD")
            
        except Error as e:
            print(f"❌ Error guardando estadísticas: {e}")
    
    def guardar_muestra_congestion(self, sesion_id, frame, vehiculos, nivel, promedio):
        """
        Guarda una muestra de congestión
        """
        try:
            query = """
                INSERT INTO muestras_congestion 
                (id_sesion, timestamp, frame, vehiculos_simultaneos, nivel_congestion, promedio_vehiculos)
                VALUES (%s, %s, %s, %s, %s, %s)
            """
            values = (sesion_id, datetime.now(), frame, vehiculos, nivel, promedio)
            
            self.cursor.execute(query, values)
            self.connection.commit()
            
        except Error as e:
            print(f"❌ Error guardando muestra congestión: {e}")
    
    def guardar_resumen_congestion(self, sesion_id, promedio, maximo, nivel_general):
        """
        Guarda el resumen de congestión de la sesión
        """
        try:
            query = """
                INSERT INTO resumen_congestion (id_sesion, promedio_vehiculos, max_vehiculos, nivel_general)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                    promedio_vehiculos = VALUES(promedio_vehiculos),
                    max_vehiculos = VALUES(max_vehiculos),
                    nivel_general = VALUES(nivel_general)
            """
            values = (sesion_id, promedio, maximo, nivel_general)
            
            self.cursor.execute(query, values)
            self.connection.commit()
            
        except Error as e:
            print(f"❌ Error guardando resumen congestión: {e}")
    
    def registrar_log(self, sesion_id, nivel, mensaje):
        """
        Registra un log del sistema
        """
        try:
            query = """
                INSERT INTO logs_sistema (id_sesion, nivel, mensaje)
                VALUES (%s, %s, %s)
            """
            self.cursor.execute(query, (sesion_id, nivel, mensaje))
            self.connection.commit()
            
        except Error as e:
            print(f"❌ Error registrando log: {e}")
    
    def obtener_sesiones_activas(self):
        """
        Obtiene todas las sesiones activas
        """
        try:
            self.cursor.execute("""
                SELECT id, direccion, fecha_inicio, total_vehiculos 
                FROM sesiones 
                WHERE estado = 'activa'
                ORDER BY fecha_inicio DESC
            """)
            return self.cursor.fetchall()
        except Error as e:
            print(f"❌ Error obteniendo sesiones activas: {e}")
            return []
    
    def obtener_estadisticas_sesion(self, sesion_id):
        """
        Obtiene todas las estadísticas de una sesión
        """
        try:
            stats = {}
            
            # Información general
            self.cursor.execute("""
                SELECT * FROM sesiones WHERE id = %s
            """, (sesion_id,))
            stats['sesion'] = self.cursor.fetchone()
            
            # Vehículos por tipo
            self.cursor.execute("""
                SELECT tipo, cantidad FROM estadisticas_tipo WHERE id_sesion = %s
            """, (sesion_id,))
            stats['por_tipo'] = self.cursor.fetchall()
            
            # Vehículos por color
            self.cursor.execute("""
                SELECT color, cantidad FROM estadisticas_color WHERE id_sesion = %s
            """, (sesion_id,))
            stats['por_color'] = self.cursor.fetchall()
            
            # Resumen congestión
            self.cursor.execute("""
                SELECT * FROM resumen_congestion WHERE id_sesion = %s
            """, (sesion_id,))
            stats['congestion'] = self.cursor.fetchone()
            
            return stats
            
        except Error as e:
            print(f"❌ Error obteniendo estadísticas: {e}")
            return None
    
    def exportar_a_json(self, sesion_id, archivo_salida):
        """
        Exporta los datos de una sesión a JSON
        """
        stats = self.obtener_estadisticas_sesion(sesion_id)
        if stats:
            with open(archivo_salida, 'w', encoding='utf-8') as f:
                json.dump(stats, f, indent=2, default=str, ensure_ascii=False)
            print(f"📁 Datos exportados a {archivo_salida}")
            return True
        return False