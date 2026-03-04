"""
api.py - API REST para el Sistema de Conteo Vehicular
"""

from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import threading
import json
import os
import sys
import time
from datetime import datetime
import mysql.connector
from pathlib import Path

# Añadir directorio actual al path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

# Importar módulos del sistema
try:
    from main import SistemaConteoVehiculos
    from database import DatabaseManager
    from config import limpiar_nombre_direccion
    print("✅ Módulos importados correctamente")
except ImportError as e:
    print(f"⚠️ Error importando módulos: {e}")

app = Flask(__name__)
CORS(app)

# Configuración
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_FOLDER = os.path.join(BASE_DIR, '..', 'uploads')
RESULTADOS_FOLDER = os.path.join(BASE_DIR, '..', 'resultados')
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(RESULTADOS_FOLDER, exist_ok=True)

print(f"📁 Uploads folder: {UPLOAD_FOLDER}")
print(f"📁 Resultados folder: {RESULTADOS_FOLDER}")

# Configuración de base de datos
DB_CONFIG = {
    'host': 'localhost',
    'database': 'trafico_vehicular',
    'user': 'root',
    'password': ''
}

class AnalisisManager:
    """Gestiona los análisis en ejecución"""
    
    def __init__(self):
        self.analisis_activos = {}
        self.contador_id = 0
        self.lock = threading.Lock()
        print("🔄 AnalisisManager inicializado")
    
    def iniciar_analisis(self, modo, direccion, fuente=None, duracion=30, escala=0.8, sesion_id=None):
        """
        Inicia un nuevo análisis en un hilo separado
        """
        with self.lock:
            analisis_id = self.contador_id
            self.contador_id += 1
        
        print(f"\n{'='*50}")
        print(f"🎯 INICIANDO ANÁLISIS #{analisis_id}")
        print(f"{'='*50}")
        print(f"📋 Modo: {modo}")
        print(f"📍 Dirección: {direccion}")
        print(f"📁 Fuente: {fuente}")
        print(f"⏱️ Duración: {duracion}s")
        print(f"🔍 Escala: {escala}")
        print(f"🆔 Sesión BD: {sesion_id}")
        
        # Crear hilo para el análisis
        hilo = threading.Thread(
            target=self._ejecutar_analisis,
            args=(analisis_id, modo, direccion, fuente, duracion, escala, sesion_id)
        )
        hilo.daemon = True
        hilo.start()
        
        with self.lock:
            self.analisis_activos[analisis_id] = {
                'id': analisis_id,
                'sesion_id': sesion_id,
                'estado': 'iniciando',
                'progreso': 0,
                'mensaje': 'Inicializando sistema...',
                'hilo': hilo,
                'modo': modo,
                'direccion': direccion,
                'fecha_inicio': datetime.now().isoformat(),
                'resultado': None
            }
        
        print(f"✅ Análisis #{analisis_id} iniciado en hilo separado")
        return analisis_id
    
    def _ejecutar_analisis(self, analisis_id, modo, direccion, fuente, duracion, escala, sesion_id):
        """
        Ejecuta el análisis (corre en hilo separado)
        """
        try:
            print(f"\n🔄 EJECUTANDO ANÁLISIS #{analisis_id}")
            
            with self.lock:
                self.analisis_activos[analisis_id]['estado'] = 'procesando'
                self.analisis_activos[analisis_id]['mensaje'] = 'Cargando modelo YOLO...'
                self.analisis_activos[analisis_id]['progreso'] = 5
            
            self._actualizar_estado_bd(sesion_id, 'procesando')
            
            # Determinar fuente real
            if modo == 'camara':
                fuente_real = 0
                print("📹 Usando cámara web")
            else:
                fuente_real = fuente if fuente and os.path.exists(fuente) else None
                print(f"🎥 Usando archivo: {fuente_real}")
                
                if not fuente_real or not os.path.exists(fuente_real):
                    error_msg = f"Archivo no encontrado: {fuente}"
                    print(f"❌ {error_msg}")
                    self._actualizar_error_bd(sesion_id, error_msg)
                    return
            
            with self.lock:
                self.analisis_activos[analisis_id]['mensaje'] = 'Inicializando sistema...'
                self.analisis_activos[analisis_id]['progreso'] = 10
            
            # Crear instancia del sistema
            print("🔄 Creando sistema de conteo...")
            sistema = SistemaConteoVehiculos(
                fuente=fuente_real,
                output_dir=RESULTADOS_FOLDER,
                escala_visualizacion=escala,
                modo=modo,
                direccion=direccion,
                usar_bd=True,
                sesion_id_existente=sesion_id
            )
            
            with self.lock:
                self.analisis_activos[analisis_id]['sistema'] = sistema
                self.analisis_activos[analisis_id]['progreso'] = 15
                self.analisis_activos[analisis_id]['mensaje'] = 'Sistema listo, procesando...'
            
            # Hilo para actualizar progreso periódicamente
            def actualizar_progreso():
                ultimo_progreso = -1
                while True:
                    time.sleep(0.5)
                    with self.lock:
                        if analisis_id not in self.analisis_activos:
                            break
                        
                        if hasattr(sistema, 'progreso_actual'):
                            progreso = sistema.progreso_actual
                            if progreso != ultimo_progreso:
                                self.analisis_activos[analisis_id]['progreso'] = progreso
                                if progreso >= 90:
                                    self.analisis_activos[analisis_id]['mensaje'] = 'Procesamiento casi completo...'
                                elif progreso >= 75:
                                    self.analisis_activos[analisis_id]['mensaje'] = 'Guardando resultados...'
                                ultimo_progreso = progreso
                        
                        if hasattr(sistema, 'estado_analisis') and sistema.estado_analisis == 'completado':
                            break
            
            hilo_progreso = threading.Thread(target=actualizar_progreso)
            hilo_progreso.daemon = True
            hilo_progreso.start()
            
            # Ejecutar según el modo
            if modo == 'camara':
                print("📹 Procesando cámara en tiempo real...")
                sistema.procesar_camara()
            elif modo == 'video':
                print("🎥 Procesando video...")
                sistema.procesar_video()
            elif modo == 'imagen':
                print("🖼️ Procesando imagen...")
                sistema.procesar_imagen(fuente_real)
            
            # Finalizar
            print("🔄 Finalizando análisis...")
            sistema.finalizar()
            
            with self.lock:
                self.analisis_activos[analisis_id]['estado'] = 'completado'
                self.analisis_activos[analisis_id]['progreso'] = 100
                self.analisis_activos[analisis_id]['mensaje'] = 'Análisis completado'
            
            self._actualizar_estado_bd(sesion_id, 'completado')
            
            print(f"✅ Análisis #{analisis_id} COMPLETADO")
            print(f"{'='*50}\n")
            
        except Exception as e:
            print(f"\n❌ ERROR en análisis #{analisis_id}: {e}")
            import traceback
            traceback.print_exc()
            
            with self.lock:
                self.analisis_activos[analisis_id]['estado'] = 'error'
                self.analisis_activos[analisis_id]['mensaje'] = f'Error: {str(e)}'
            
            self._actualizar_error_bd(sesion_id, str(e))
    
    def _actualizar_estado_bd(self, sesion_id, estado):
        """Actualiza el estado en la base de datos"""
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            cursor = conn.cursor()
            query = "UPDATE sesiones SET estado = %s WHERE id = %s"
            cursor.execute(query, (estado, sesion_id))
            conn.commit()
            cursor.close()
            conn.close()
            print(f"📝 Estado BD actualizado: {estado}")
        except Exception as e:
            print(f"⚠️ Error actualizando BD: {e}")
    
    def _actualizar_error_bd(self, sesion_id, error):
        """Registra error en la base de datos"""
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            cursor = conn.cursor()
            
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS errores_analisis (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sesion_id INT,
                    error TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            """)
            
            query = "INSERT INTO errores_analisis (sesion_id, error) VALUES (%s, %s)"
            cursor.execute(query, (sesion_id, error))
            
            conn.commit()
            cursor.close()
            conn.close()
            print(f"❌ Error registrado en BD: {error}")
        except Exception as e:
            print(f"⚠️ Error registrando error: {e}")
    
    def obtener_progreso(self, analisis_id):
        """Obtiene el progreso de un análisis"""
        with self.lock:
            if analisis_id in self.analisis_activos:
                info = self.analisis_activos[analisis_id].copy()
                info.pop('hilo', None)
                info.pop('sistema', None)
                return info
        return None

# Instancia global
analisis_manager = AnalisisManager()

@app.route('/api/health', methods=['GET'])
def health_check():
    """Verifica que la API está funcionando"""
    return jsonify({
        'status': 'ok',
        'timestamp': datetime.now().isoformat(),
        'backend': 'Sistema de Conteo Vehicular API v1.0'
    })

@app.route('/api/analisis/iniciar', methods=['POST'])
def iniciar_analisis():
    """
    Inicia un nuevo análisis
    """
    try:
        print("\n" + "="*50)
        print("📥 NUEVA SOLICITUD DE ANÁLISIS")
        print("="*50)
        
        data = request.json
        print("📦 Datos recibidos:", json.dumps(data, indent=2))
        
        modo = data.get('modo')
        direccion = data.get('direccion')
        fuente = data.get('fuente')
        duracion = int(data.get('duracion', 30))
        escala = float(data.get('escala', 0.8))
        sesion_id = data.get('sesion_id')
        
        print(f"📋 Parámetros:")
        print(f"   - Modo: {modo}")
        print(f"   - Dirección: {direccion}")
        print(f"   - Fuente: {fuente}")
        print(f"   - Duración: {duracion}")
        print(f"   - Escala: {escala}")
        print(f"   - Sesion ID: {sesion_id}")
        
        if not modo or not direccion:
            return jsonify({'success': False, 'error': 'Faltan parámetros'}), 400
        
        if modo != 'camara' and not fuente:
            return jsonify({'success': False, 'error': 'Se requiere archivo'}), 400
        
        if modo != 'camara' and not os.path.exists(fuente):
            return jsonify({'success': False, 'error': f'Archivo no encontrado: {fuente}'}), 400
        
        # Iniciar análisis
        analisis_id = analisis_manager.iniciar_analisis(
            modo=modo,
            direccion=direccion,
            fuente=fuente,
            duracion=duracion,
            escala=escala,
            sesion_id=sesion_id
        )
        
        print(f"✅ Análisis iniciado - ID: {analisis_id}")
        print("="*50 + "\n")
        
        return jsonify({
            'success': True,
            'analisis_id': analisis_id,
            'sesion_id': sesion_id,
            'mensaje': 'Análisis iniciado correctamente'
        })
        
    except Exception as e:
        print(f"❌ Error: {str(e)}")
        import traceback
        traceback.print_exc()
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/analisis/progreso/<int:analisis_id>', methods=['GET'])
def progreso_detallado(analisis_id):
    """
    Obtiene el progreso detallado de un análisis
    """
    print(f"📊 Consultando progreso para análisis #{analisis_id}")
    
    info = analisis_manager.obtener_progreso(analisis_id)
    
    if info:
        print(f"   Estado: {info['estado']}, Progreso: {info['progreso']}%, Mensaje: {info['mensaje']}")
        return jsonify({
            'success': True,
            'analisis_id': analisis_id,
            'estado': info['estado'],
            'progreso': info['progreso'],
            'mensaje': info['mensaje'],
            'timestamp': datetime.now().isoformat()
        })
    
    print(f"❌ Análisis #{analisis_id} no encontrado")
    return jsonify({'success': False, 'error': 'Análisis no encontrado'}), 404

@app.route('/api/analisis/estado/<int:analisis_id>', methods=['GET'])
def estado_analisis(analisis_id):
    """Obtiene el estado de un análisis"""
    info = analisis_manager.obtener_progreso(analisis_id)
    
    if info:
        return jsonify({'success': True, 'estado': info})
    
    return jsonify({'success': False, 'error': 'Análisis no encontrado'}), 404

@app.route('/api/analisis/activos', methods=['GET'])
def analisis_activos():
    """Lista los análisis activos"""
    activos = []
    for aid, info in analisis_manager.analisis_activos.items():
        if info['estado'] in ['iniciando', 'procesando']:
            activos.append({
                'id': aid,
                'estado': info['estado'],
                'mensaje': info['mensaje'],
                'modo': info['modo'],
                'direccion': info['direccion'],
                'progreso': info['progreso']
            })
    
    return jsonify({'success': True, 'activos': activos})

@app.route('/api/test', methods=['GET'])
def test():
    """Endpoint de prueba"""
    return jsonify({
        'success': True,
        'message': 'API funcionando correctamente',
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    print("\n" + "="*60)
    print("🚗 API DEL SISTEMA DE CONTEO VEHICULAR")
    print("="*60)
    print(f"\n📡 Servidor: http://127.0.0.1:5000")
    print(f"📁 Uploads: {UPLOAD_FOLDER}")
    print(f"📁 Resultados: {RESULTADOS_FOLDER}")
    print("\n🔧 Endpoints:")
    print("   GET  /api/health")
    print("   POST /api/analisis/iniciar")
    print("   GET  /api/analisis/progreso/<id>")
    print("   GET  /api/analisis/estado/<id>")
    print("   GET  /api/analisis/activos")
    print("   GET  /api/test")
    print("="*60)
    
    app.run(host='127.0.0.1', port=5000, debug=True, threaded=True)