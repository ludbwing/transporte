import cv2
import os
import json
from datetime import datetime
import config
from detector import DetectorVehiculos
from tracker import TrackerVehiculos
from congestion_analyzer import CongestionAnalyzer
from visualizer import Visualizador
from database import DatabaseManager

class SistemaConteoVehiculos:
    def __init__(self, fuente=0, output_dir="./resultados", escala_visualizacion=0.8, 
                 modo="video", direccion="", usar_bd=True, sesion_id_existente=None):
        """
        Sistema principal de conteo de vehículos
        """
        self.fuente = fuente
        self.escala_visualizacion = escala_visualizacion
        self.modo = modo
        self.direccion = direccion
        self.usar_bd = usar_bd
        self.sesion_id_existente = sesion_id_existente
        
        # Variables para progreso
        self.frame_actual = 0
        self.total_frames = 0
        self.progreso_actual = 0
        self.estado_analisis = "iniciando"
        
        # Crear directorio de salida
        self.output_dir = config.crear_directorio_salida(output_dir)
        
        # Inicializar componentes
        print("\n🔄 Inicializando componentes del sistema...")
        self.detector = DetectorVehiculos()
        self.tracker = TrackerVehiculos()
        self.analizador = CongestionAnalyzer()
        self.visualizador = Visualizador(direccion)
        
        # Inicializar base de datos
        self.db = None
        self.sesion_id = sesion_id_existente
        
        if usar_bd:
            try:
                print("🔄 Conectando a base de datos MySQL...")
                self.db = DatabaseManager()
                if self.db and self.db.connection:
                    if sesion_id_existente:
                        self.sesion_id = sesion_id_existente
                        print(f"✅ Usando sesión existente ID: {self.sesion_id}")
                        if hasattr(self.db, 'registrar_log'):
                            self.db.registrar_log(self.sesion_id, 'INFO', f'Continuando sesión desde PHP')
                    else:
                        self.sesion_id = self.db.iniciar_sesion(
                            direccion, 
                            modo, 
                            str(fuente)
                        )
                        if self.sesion_id:
                            print(f"✅ Base de datos conectada - Nueva sesión ID: {self.sesion_id}")
                        else:
                            print("⚠️ No se pudo iniciar sesión en BD")
                            self.usar_bd = False
                else:
                    print("⚠️ No se pudo conectar a la BD")
                    self.usar_bd = False
            except Exception as e:
                print(f"⚠️ Error conectando a BD: {e}")
                self.usar_bd = False
    
    def _determinar_nivel_general(self, promedio):
        """Determina el nivel general de congestión"""
        if promedio < config.UMBRAL_BAJO:
            return "FLUJO LIBRE"
        elif promedio < config.UMBRAL_MODERADO:
            return "MODERADO"
        elif promedio < config.UMBRAL_ALTO:
            return "CONGESTIÓN ALTA"
        else:
            return "CONGESTIÓN SEVERA"
    
    def procesar_frame(self, frame, frame_count=0):
        """
        Procesa un frame completo
        """
        # Detectar vehículos
        detecciones = self.detector.detectar(frame)
        
        # Actualizar tracking
        nuevas_detecciones = self.tracker.actualizar(frame, detecciones, frame_count, self.detector)
        
        # Obtener vehículos activos
        vehiculos_activos = self.tracker.get_vehiculos_activos()
        
        # Analizar congestión
        nivel_congestion, color_congestion, descripcion, num_vehiculos, promedio = \
            self.analizador.analizar(len(vehiculos_activos))
        
        # Guardar en BD
        if self.usar_bd and self.db and self.sesion_id:
            try:
                for vid, info in vehiculos_activos.items():
                    self.db.guardar_vehiculo(self.sesion_id, info)
                
                if frame_count % 30 == 0:
                    self.db.guardar_muestra_congestion(
                        self.sesion_id, frame_count, num_vehiculos, 
                        nivel_congestion, promedio
                    )
            except Exception as e:
                print(f"⚠️ Error guardando en BD: {e}")
        
        # Visualizar
        estadisticas = self.tracker.get_estadisticas()
        frame_out = self.visualizador.dibujar_todo(
            frame, vehiculos_activos, nivel_congestion, color_congestion,
            descripcion, num_vehiculos, estadisticas['total_vehiculos']
        )
        
        return frame_out
    
    def finalizar(self):
        """
        Finaliza el procesamiento y guarda en BD
        """
        if self.usar_bd and self.db and self.sesion_id:
            try:
                print("\n💾 Guardando datos en base de datos...")
                estadisticas = self.tracker.get_estadisticas()
                
                self.db.guardar_estadisticas(
                    self.sesion_id,
                    estadisticas['por_tipo'],
                    estadisticas['por_color']
                )
                
                resumen = self.analizador.get_resumen()
                nivel_general = self._determinar_nivel_general(resumen['promedio_vehiculos_simultaneos'])
                
                self.db.guardar_resumen_congestion(
                    self.sesion_id,
                    resumen['promedio_vehiculos_simultaneos'],
                    resumen['max_vehiculos_simultaneos'],
                    nivel_general
                )
                
                self.db.finalizar_sesion(self.sesion_id, estadisticas['total_vehiculos'])
                print("✅ Datos guardados correctamente en BD")
                
            except Exception as e:
                print(f"❌ Error guardando datos finales en BD: {e}")
            finally:
                if self.db:
                    self.db.cerrar()
    
    def procesar_video(self):
        """Procesa un archivo de video con progreso real"""
        print(f"\n🎥 Procesando video...")
        self.estado_analisis = "procesando"
        
        cap = cv2.VideoCapture(self.fuente)
        if not cap.isOpened():
            print("❌ Error: No se pudo abrir el video")
            self.estado_analisis = "error"
            return
        
        # Obtener información del video
        self.total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
        fps = int(cap.get(cv2.CAP_PROP_FPS))
        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
        
        print(f"📍 Dirección: {self.direccion}")
        print(f"📁 Fuente: {self.fuente}")
        print(f"📊 Total frames: {self.total_frames}, FPS: {fps}")
        
        # Preparar archivo de salida
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        direccion_limpia = config.limpiar_nombre_direccion(self.direccion)
        output = os.path.join(self.output_dir, f'{direccion_limpia}_{timestamp}.mp4')
        
        fourcc = cv2.VideoWriter_fourcc(*'mp4v')
        out = cv2.VideoWriter(output, fourcc, fps, (width, height))
        
        self.frame_actual = 0
        ultimo_progreso_mostrado = -1
        
        try:
            while True:
                ret, frame = cap.read()
                if not ret:
                    break
                
                # Procesar frame
                frame_proc = self.procesar_frame(frame, self.frame_actual)
                out.write(frame_proc)
                
                # Actualizar progreso
                self.frame_actual += 1
                if self.total_frames > 0:
                    self.progreso_actual = int((self.frame_actual / self.total_frames) * 100)
                    
                    # Mostrar progreso cada 5% (para debugging)
                    if self.progreso_actual % 5 == 0 and self.progreso_actual != ultimo_progreso_mostrado:
                        print(f"⏳ Progreso: {self.progreso_actual}% ({self.frame_actual}/{self.total_frames} frames)")
                        ultimo_progreso_mostrado = self.progreso_actual
                
        except Exception as e:
            print(f"❌ Error durante procesamiento: {e}")
            self.estado_analisis = "error"
            if self.usar_bd and self.db:
                self.db.registrar_log(self.sesion_id, 'ERROR', str(e))
        
        finally:
            cap.release()
            out.release()
            self.estado_analisis = "completado"
            self.progreso_actual = 100
            print(f"\n✅ Video procesado: {self.frame_actual}/{self.total_frames} frames")
            print(f"✅ Video guardado: {output}")
            self.guardar_estadisticas()
            self.mostrar_resumen()
            self.finalizar()
    
    def procesar_imagen(self, ruta):
        """Procesa una imagen"""
        print(f"\n🖼️ Procesando imagen...")
        self.estado_analisis = "procesando"
        
        frame = cv2.imread(ruta)
        if frame is None:
            print("❌ Error: No se pudo cargar la imagen")
            self.estado_analisis = "error"
            if self.usar_bd and self.db:
                self.db.registrar_log(self.sesion_id, 'ERROR', 'No se pudo cargar la imagen')
            return
        
        h, w = frame.shape[:2]
        self.total_frames = 1
        self.progreso_actual = 0
        
        try:
            print(f"📍 Dirección: {self.direccion}")
            print(f"📁 Fuente: {ruta}")
            
            frame_proc = self.procesar_frame(frame, 0)
            self.progreso_actual = 50
            
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            direccion_limpia = config.limpiar_nombre_direccion(self.direccion)
            nombre = os.path.basename(ruta)
            nombre_sin_ext = os.path.splitext(nombre)[0]
            output = os.path.join(self.output_dir, f'{direccion_limpia}_{nombre_sin_ext}_{timestamp}.jpg')
            
            cv2.imwrite(output, frame_proc)
            self.progreso_actual = 100
            print(f"✅ Imagen guardada: {output}")
            
        except Exception as e:
            print(f"❌ Error durante procesamiento: {e}")
            self.estado_analisis = "error"
            if self.usar_bd and self.db:
                self.db.registrar_log(self.sesion_id, 'ERROR', str(e))
        
        finally:
            self.estado_analisis = "completado"
            self.guardar_estadisticas()
            self.mostrar_resumen()
            self.finalizar()
    
    def procesar_camara(self):
        """Procesa cámara en tiempo real"""
        print(f"\n📹 Procesando cámara en tiempo real...")
        self.estado_analisis = "procesando"
        
        cap = cv2.VideoCapture(self.fuente)
        if not cap.isOpened():
            print("❌ Error: No se pudo abrir la cámara")
            self.estado_analisis = "error"
            return
        
        fps = int(cap.get(cv2.CAP_PROP_FPS))
        if fps == 0:
            fps = 30
        
        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
        
        print(f"📍 Dirección: {self.direccion}")
        print(f"📊 FPS: {fps}, Resolución: {width}x{height}")
        print("Presiona 'q' para salir")
        
        frame_count = 0
        ventana = '📹 Cámara en Vivo - Q:Salir'
        cv2.namedWindow(ventana, cv2.WINDOW_NORMAL)
        
        try:
            while True:
                ret, frame = cap.read()
                if not ret:
                    break
                
                frame_proc = self.procesar_frame(frame, frame_count)
                
                nuevo_w = int(width * self.escala_visualizacion)
                nuevo_h = int(height * self.escala_visualizacion)
                frame_mostrar = cv2.resize(frame_proc, (nuevo_w, nuevo_h))
                cv2.resizeWindow(ventana, nuevo_w, nuevo_h)
                cv2.imshow(ventana, frame_mostrar)
                
                if cv2.waitKey(1) & 0xFF == ord('q'):
                    print("\n⏹️ Procesamiento finalizado")
                    break
                
                frame_count += 1
                self.progreso_actual = min(95, int((frame_count % 100) / 100 * 100))
                
        except Exception as e:
            print(f"❌ Error: {e}")
            self.estado_analisis = "error"
        
        finally:
            cap.release()
            cv2.destroyAllWindows()
            self.estado_analisis = "completado"
            self.progreso_actual = 100
            self.guardar_estadisticas()
            self.mostrar_resumen()
            self.finalizar()
    
    def guardar_estadisticas(self):
        """Guarda estadísticas en archivo JSON"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        direccion_limpia = config.limpiar_nombre_direccion(self.direccion)
        archivo = os.path.join(self.output_dir, f'estadisticas_{direccion_limpia}_{timestamp}.json')
        
        tracker_stats = self.tracker.get_estadisticas()
        congestion_resumen = self.analizador.get_resumen()
        
        stats = {
            'direccion': self.direccion,
            'fecha': datetime.now().isoformat(),
            'total_vehiculos': tracker_stats['total_vehiculos'],
            'por_tipo': dict(tracker_stats['por_tipo']),
            'por_color': dict(tracker_stats['por_color']),
            'analisis_congestion': congestion_resumen
        }
        
        with open(archivo, 'w', encoding='utf-8') as f:
            json.dump(stats, f, indent=2, ensure_ascii=False)
        
        print(f"\n📊 Estadísticas guardadas en: {archivo}")
    
    def mostrar_resumen(self):
        """Muestra resumen final"""
        print("\n" + "="*60)
        print("📊 RESUMEN FINAL DEL ANÁLISIS")
        print("="*60)
        print(f"📍 Dirección: {self.direccion}")
        
        tracker_stats = self.tracker.get_estadisticas()
        print(f"🚗 Total vehículos: {tracker_stats['total_vehiculos']}")
        
        congestion_resumen = self.analizador.get_resumen()
        print(f"\n🚦 ANÁLISIS DE CONGESTIÓN:")
        print(f"   Promedio vehículos simultáneos: {congestion_resumen['promedio_vehiculos_simultaneos']}")
        print(f"   Máximo vehículos simultáneos: {congestion_resumen['max_vehiculos_simultaneos']}")
        
        prom = congestion_resumen['promedio_vehiculos_simultaneos']
        nivel_general = self._determinar_nivel_general(prom)
        
        emoji = "✅" if "LIBRE" in nivel_general else "🟡" if "MODERADO" in nivel_general else "🟠" if "ALTA" in nivel_general else "🔴"
        print(f"   Nivel general: {nivel_general} {emoji}")
        
        print("\n📋 Detalle por tipo:")
        if tracker_stats['por_tipo']:
            for t, c in tracker_stats['por_tipo'].items():
                print(f"   • {t.capitalize()}: {c}")
        
        print("\n🎨 Detalle por color:")
        if tracker_stats['por_color']:
            for col, c in tracker_stats['por_color'].items():
                if col != 'desconocido':
                    print(f"   • {col.capitalize()}: {c}")
        
        print("="*60)
        
        if self.usar_bd and self.sesion_id:
            print(f"\n💾 Datos guardados en BD - Sesión ID: {self.sesion_id}")