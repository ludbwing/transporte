import cv2
import numpy as np
from ultralytics import YOLO
from collections import defaultdict, deque
import os
import json
from datetime import datetime
import math

class SistemaConteoVehiculosSimple:
    def __init__(self, fuente=0, output_dir="./resultados", escala_visualizacion=0.8, modo="video", direccion=""):
        """
        Sistema simplificado que muestra color del auto y dirección+fecha+hora
        """
        self.fuente = fuente
        self.output_dir = output_dir
        self.escala_visualizacion = escala_visualizacion
        self.modo = modo
        self.direccion = direccion
        
        # Crear directorio
        self.output_dir = os.path.abspath(output_dir)
        if not os.path.exists(self.output_dir):
            os.makedirs(self.output_dir)
            print(f"📁 Creado directorio: {self.output_dir}")
        
        # Cargar modelo
        print("Cargando modelo YOLOv8...")
        self.model = YOLO('yolov8n.pt')
        
        # Clases de vehículos
        self.clases_vehiculos = {
            2: 'auto',
            3: 'moto',
            5: 'bus',
            7: 'camion'
        }
        
        # Sistema de tracking simple pero efectivo
        self.siguiente_id = 0
        self.vehiculos_activos = {}
        self.vehiculos_historicos = set()
        
        # Base de datos de vehículos para Re-ID
        self.base_datos = {}  # ID -> características
        
        # Parámetros
        self.distancia_maxima = 300
        self.umbral_reid = 40  # Umbral para Re-ID
        self.frames_para_olvidar = 45
        
        # Estadísticas básicas
        self.estadisticas = {
            'total_vehiculos': 0,
            'por_tipo': defaultdict(int),
            'por_color': defaultdict(int),
            'congestion': []  # Historial de congestión
        }
        
        # RANGOS DE COLOR HSV
        self.rangos_hsv = {
            'blanco': [(0, 0, 180), (180, 40, 255)],
            'negro': [(0, 0, 0), (180, 255, 60)],
            'gris': [(0, 0, 60), (180, 40, 179)],
            'plateado': [(0, 0, 150), (180, 30, 220)],
            'rojo': [(0, 50, 50), (10, 255, 255), (160, 50, 50), (180, 255, 255)],
            'naranja': [(5, 50, 50), (20, 255, 255)],
            'amarillo': [(20, 50, 50), (35, 255, 255)],
            'verde': [(35, 40, 40), (85, 255, 255)],
            'azul': [(85, 40, 40), (130, 255, 255)]
        }
        
        # Variables para análisis de congestión
        self.historial_conteo = deque(maxlen=30)  # Últimos 30 frames
        self.umbral_bajo = 3    # Menos de 3 vehículos -> flujo libre
        self.umbral_moderado = 8 # Entre 3 y 8 -> moderado
        self.umbral_alto = 15    # Entre 8 y 15 -> alto
        # Más de 15 -> congestionado
    
    def obtener_centro(self, bbox):
        """Centro del bounding box"""
        return ((bbox[0] + bbox[2]) / 2, (bbox[1] + bbox[3]) / 2)
    
    def calcular_distancia(self, p1, p2):
        """Distancia euclidiana"""
        return math.sqrt((p2[0] - p1[0])**2 + (p2[1] - p1[1])**2)
    
    def obtener_color_dominante(self, frame, bbox):
        """
        Obtiene el color dominante del vehículo
        """
        try:
            x1, y1, x2, y2 = map(int, bbox)
            x1, y1 = max(0, x1), max(0, y1)
            x2, y2 = min(frame.shape[1], x2), min(frame.shape[0], y2)
            
            if x2 <= x1 or y2 <= y1:
                return 'desconocido'
            
            roi = frame[y1:y2, x1:x2]
            if roi.size == 0:
                return 'desconocido'
            
            # Convertir a HSV
            hsv = cv2.cvtColor(roi, cv2.COLOR_BGR2HSV)
            
            # Usar región central para evitar bordes
            h, w = hsv.shape[:2]
            cy, cx = h // 2, w // 2
            margen_x = int(w * 0.3)
            margen_y = int(h * 0.3)
            x_ini = max(0, cx - margen_x)
            x_fin = min(w, cx + margen_x)
            y_ini = max(0, cy - margen_y)
            y_fin = min(h, cy + margen_y)
            
            hsv_centro = hsv[y_ini:y_fin, x_ini:x_fin]
            
            if hsv_centro.size == 0:
                hsv_centro = hsv
            
            # Contar píxeles por color
            conteo = defaultdict(int)
            for color, rangos in self.rangos_hsv.items():
                mascara = np.zeros(hsv_centro.shape[:2], dtype=np.uint8)
                for i in range(0, len(rangos), 2):
                    bajo = np.array(rangos[i], dtype=np.uint8)
                    alto = np.array(rangos[i+1], dtype=np.uint8)
                    mascara = cv2.bitwise_or(mascara, cv2.inRange(hsv_centro, bajo, alto))
                conteo[color] = cv2.countNonZero(mascara)
            
            if sum(conteo.values()) > 0:
                color_dominante = max(conteo, key=conteo.get)
                return color_dominante
            
            return 'desconocido'
            
        except Exception as e:
            return 'desconocido'
    
    def extraer_histograma_color(self, frame, bbox):
        """
        Extrae histograma de color para Re-ID
        """
        try:
            x1, y1, x2, y2 = map(int, bbox)
            x1, y1 = max(0, x1), max(0, y1)
            x2, y2 = min(frame.shape[1], x2), min(frame.shape[0], y2)
            
            if x2 <= x1 or y2 <= y1:
                return None
            
            roi = frame[y1:y2, x1:x2]
            if roi.size == 0:
                return None
            
            hsv = cv2.cvtColor(roi, cv2.COLOR_BGR2HSV)
            
            # Histograma 3D
            hist = cv2.calcHist([hsv], [0, 1, 2], None, [8, 8, 8], [0, 180, 0, 256, 0, 256])
            cv2.normalize(hist, hist, 0, 1, cv2.NORM_MINMAX)
            
            return hist
            
        except:
            return None
    
    def comparar_histogramas(self, hist1, hist2):
        """
        Compara dos histogramas
        """
        if hist1 is None or hist2 is None:
            return 0
        
        try:
            correlacion = cv2.compareHist(hist1, hist2, cv2.HISTCMP_CORREL)
            return max(0, correlacion)
        except:
            return 0
    
    def buscar_en_base_datos(self, hist, tipo):
        """
        Busca en la base de datos si este vehículo ya fue visto
        """
        mejor_id = None
        mejor_puntaje = 0
        
        for vid, data in self.base_datos.items():
            # Comparar histogramas
            if 'hist' in data and data['hist'] is not None:
                puntaje = self.comparar_histogramas(hist, data['hist'])
                
                # Bonus por mismo tipo
                if data['tipo'] == tipo:
                    puntaje += 0.2
                
                if puntaje > mejor_puntaje and puntaje > 0.6:  # Umbral 0.6
                    mejor_puntaje = puntaje
                    mejor_id = vid
        
        return mejor_id
    
    def actualizar_tracking(self, frame, detecciones, frame_actual):
        """
        Actualiza el tracking de vehículos
        """
        nuevas_detecciones = []
        
        # Extraer características
        for det in detecciones:
            det['hist'] = self.extraer_histograma_color(frame, det['bbox'])
        
        # Marcar activos como no coincidentes
        for info in self.vehiculos_activos.values():
            info['coincidio'] = False
        
        # PRIMERO: Buscar en base de datos (Re-ID)
        for det in detecciones:
            if det['hist'] is None:
                continue
            
            vid_en_bd = self.buscar_en_base_datos(det['hist'], det['tipo'])
            
            if vid_en_bd is not None:
                # Re-identificado
                det['id'] = vid_en_bd
                
                # Actualizar base de datos
                self.base_datos[vid_en_bd]['ultimo_frame'] = frame_actual
                self.base_datos[vid_en_bd]['ultima_posicion'] = det['bbox']
                
                # Si está activo, actualizar
                if vid_en_bd in self.vehiculos_activos:
                    self.vehiculos_activos[vid_en_bd].update({
                        'bbox': det['bbox'],
                        'ultimo_frame': frame_actual,
                        'coincidio': True
                    })
                else:
                    # Reactivar
                    self.vehiculos_activos[vid_en_bd] = {
                        'id': vid_en_bd,
                        'bbox': det['bbox'],
                        'tipo': det['tipo'],
                        'color': det['color'],
                        'primer_frame': self.base_datos[vid_en_bd]['primer_frame'],
                        'ultimo_frame': frame_actual,
                        'coincidio': True
                    }
        
        # SEGUNDO: Matching con vehículos activos
        for det in detecciones:
            if 'id' in det:
                continue
            
            mejor_match = None
            mejor_distancia = float('inf')
            
            for vid, info in self.vehiculos_activos.items():
                if info.get('coincidio', False):
                    continue
                
                centro_det = self.obtener_centro(det['bbox'])
                centro_info = self.obtener_centro(info['bbox'])
                distancia = self.calcular_distancia(centro_det, centro_info)
                
                if distancia < self.distancia_maxima and distancia < mejor_distancia:
                    mejor_distancia = distancia
                    mejor_match = vid
            
            if mejor_match is not None:
                self.vehiculos_activos[mejor_match].update({
                    'bbox': det['bbox'],
                    'ultimo_frame': frame_actual,
                    'coincidio': True
                })
                det['id'] = mejor_match
                
                # Actualizar base de datos
                if mejor_match in self.base_datos:
                    self.base_datos[mejor_match]['ultimo_frame'] = frame_actual
        
        # TERCERO: Nuevos vehículos
        for det in detecciones:
            if 'id' in det:
                continue
            
            # Nuevo vehículo
            nuevo_id = self.siguiente_id
            self.siguiente_id += 1
            
            self.vehiculos_activos[nuevo_id] = {
                'id': nuevo_id,
                'bbox': det['bbox'],
                'tipo': det['tipo'],
                'color': det['color'],
                'primer_frame': frame_actual,
                'ultimo_frame': frame_actual,
                'coincidio': True
            }
            
            # Guardar en base de datos
            self.base_datos[nuevo_id] = {
                'hist': det['hist'],
                'tipo': det['tipo'],
                'primer_frame': frame_actual,
                'ultimo_frame': frame_actual,
                'ultima_posicion': det['bbox']
            }
            
            det['id'] = nuevo_id
        
        # Limpiar inactivos
        a_remover = []
        for vid, info in self.vehiculos_activos.items():
            if not info.get('coincidio', False):
                if frame_actual - info['ultimo_frame'] > self.frames_para_olvidar:
                    a_remover.append(vid)
        
        for vid in a_remover:
            if vid in self.vehiculos_activos:
                del self.vehiculos_activos[vid]
        
        # Nuevos para estadísticas
        for det in detecciones:
            if det['id'] is not None and det['id'] not in self.vehiculos_historicos:
                nuevas_detecciones.append(det)
                self.vehiculos_historicos.add(det['id'])
        
        return nuevas_detecciones
    
    def analizar_congestion(self):
        """
        Analiza el nivel de congestión basado en el número de vehículos activos
        """
        num_vehiculos = len(self.vehiculos_activos)
        self.historial_conteo.append(num_vehiculos)
        
        # Calcular promedio de los últimos frames para suavizar
        if len(self.historial_conteo) > 0:
            promedio = sum(self.historial_conteo) / len(self.historial_conteo)
        else:
            promedio = num_vehiculos
        
        # Determinar nivel de congestión
        if promedio < self.umbral_bajo:
            nivel = "FLUJO LIBRE"
            color = (0, 255, 0)  # Verde
            descripcion = "Sin congestión"
        elif promedio < self.umbral_moderado:
            nivel = "MODERADO"
            color = (0, 255, 255)  # Amarillo
            descripcion = "Tráfico ligero"
        elif promedio < self.umbral_alto:
            nivel = "CONGESTIÓN ALTA"
            color = (0, 165, 255)  # Naranja
            descripcion = "Tráfico pesado"
        else:
            nivel = "CONGESTIÓN SEVERA"
            color = (0, 0, 255)  # Rojo
            descripcion = "Tráfico colapsado"
        
        # Guardar en historial cada 30 frames (aprox 1 segundo si son 30fps)
        if len(self.historial_conteo) % 30 == 0:
            self.estadisticas['congestion'].append({
                'timestamp': datetime.now().isoformat(),
                'nivel': nivel,
                'vehiculos': num_vehiculos,
                'promedio': round(promedio, 2)
            })
        
        return nivel, color, descripcion, num_vehiculos, promedio
    
    def procesar_frame(self, frame, frame_count=0):
        """
        Procesa un frame - VERSIÓN SIMPLIFICADA
        Muestra: color + tipo de vehículo + dirección/fecha/hora + congestión
        """
        # Detección YOLO
        results = self.model(frame)[0]
        
        detecciones = []
        
        for det in results.boxes.data:
            x1, y1, x2, y2, conf, cls = det.tolist()
            
            if int(cls) in self.clases_vehiculos and conf > 0.5:
                tipo = self.clases_vehiculos[int(cls)]
                
                # Obtener color del auto
                color = self.obtener_color_dominante(frame, [x1, y1, x2, y2])
                
                detecciones.append({
                    'bbox': [x1, y1, x2, y2],
                    'tipo': tipo,
                    'color': color,
                    'confianza': conf,
                    'frame': frame_count
                })
        
        # Actualizar tracking
        nuevas = self.actualizar_tracking(frame, detecciones, frame_count)
        
        # Actualizar estadísticas
        for det in nuevas:
            self.estadisticas['total_vehiculos'] += 1
            self.estadisticas['por_tipo'][det['tipo']] += 1
            self.estadisticas['por_color'][det['color']] += 1
            print(f"🚗 Nuevo: {det['tipo']} {det['color']} (ID:{det['id']})")
        
        # Analizar congestión
        nivel_congestion, color_congestion, descripcion, num_vehiculos, promedio = self.analizar_congestion()
        
        # DIBUJAR EN EL FRAME
        frame_out = frame.copy()
        
        # 1. DIBUJAR VEHÍCULOS CON SU COLOR Y TIPO
        for vid, info in self.vehiculos_activos.items():
            x1, y1, x2, y2 = map(int, info['bbox'])
            
            # Color del rectángulo según tipo (para distinguir)
            if info['tipo'] == 'auto':
                color_rect = (0, 255, 0)  # Verde
            elif info['tipo'] == 'moto':
                color_rect = (255, 255, 0)  # Cyan
            elif info['tipo'] == 'bus':
                color_rect = (255, 0, 0)  # Azul
            elif info['tipo'] == 'camion':
                color_rect = (0, 165, 255)  # Naranja
            else:
                color_rect = (255, 255, 255)  # Blanco
            
            cv2.rectangle(frame_out, (x1, y1), (x2, y2), color_rect, 2)
            
            # MOSTRAR COLOR Y TIPO DEL VEHÍCULO
            color_texto = info.get('color', 'desconocido').capitalize()
            tipo_texto = info.get('tipo', 'desconocido').capitalize()
            
            # Crear texto combinado: "Auto Rojo" o "Moto Azul", etc.
            texto_completo = f"{tipo_texto} {color_texto}"
            
            cv2.putText(frame_out, texto_completo, (x1, y1-10),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.6, color_rect, 2)
        
        # 2. MOSTRAR INFORMACIÓN DE CONGESTIÓN (NUEVO)
        h, w = frame_out.shape[:2]
        
        # Panel de congestión en la parte superior
        overlay = frame_out.copy()
        cv2.rectangle(overlay, (10, 10), (350, 100), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.7, frame_out, 0.3, 0, frame_out)
        
        # Título del panel
        cv2.putText(frame_out, "ANÁLISIS DE TRÁFICO", (20, 30),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        
        # Nivel de congestión con color
        cv2.putText(frame_out, f"Nivel: {nivel_congestion}", (20, 55),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, color_congestion, 2)
        
        # Descripción y vehículos
        cv2.putText(frame_out, f"{descripcion} | Vehículos: {num_vehiculos}", (20, 80),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
        
        # 3. MOSTRAR DIRECCIÓN, FECHA Y HORA EN LA PARTE INFERIOR
        # Fondo semitransparente para mejor legibilidad
        overlay = frame_out.copy()
        cv2.rectangle(overlay, (10, h-40), (w-10, h-10), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.5, frame_out, 0.5, 0, frame_out)
        
        # Texto con dirección, fecha y hora
        fecha_hora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        texto_inferior = f"{self.direccion} | {fecha_hora}"
        cv2.putText(frame_out, texto_inferior, (20, h-15),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        
        # Contador en esquina superior derecha
        cv2.putText(frame_out, f"Total: {self.estadisticas['total_vehiculos']}", (w-150, 30),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
        
        return frame_out
    
    def procesar_video(self):
        """Procesa video"""
        print(f"\n🎥 Procesando video...")
        print(f"Dirección: {self.direccion}")
        
        cap = cv2.VideoCapture(self.fuente)
        if not cap.isOpened():
            print("❌ Error: No se pudo abrir el video")
            return
        
        fps = int(cap.get(cv2.CAP_PROP_FPS))
        total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
        
        print(f"📊 FPS: {fps}, Frames: {total_frames}")
        
        # Nombre archivo
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        direccion_limpia = "".join(c for c in self.direccion if c.isalnum() or c in [' ', '-', '_']).rstrip()
        direccion_limpia = direccion_limpia.replace(' ', '_')
        output = os.path.join(self.output_dir, f'{direccion_limpia}_{timestamp}.mp4')
        
        fourcc = cv2.VideoWriter_fourcc(*'mp4v')
        out = cv2.VideoWriter(output, fourcc, fps, (width, height))
        
        frame_count = 0
        ventana = 'Analisis de Vehiculos - Presiona Q para salir'
        cv2.namedWindow(ventana, cv2.WINDOW_NORMAL)
        
        while True:
            ret, frame = cap.read()
            if not ret:
                break
            
            frame_proc = self.procesar_frame(frame, frame_count)
            
            # Mostrar
            nuevo_w = int(width * self.escala_visualizacion)
            nuevo_h = int(height * self.escala_visualizacion)
            frame_mostrar = cv2.resize(frame_proc, (nuevo_w, nuevo_h))
            cv2.resizeWindow(ventana, nuevo_w, nuevo_h)
            cv2.imshow(ventana, frame_mostrar)
            
            out.write(frame_proc)
            
            if frame_count % 30 == 0:
                prog = (frame_count / total_frames) * 100
                print(f"⏳ Progreso: {prog:.1f}%")
            
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break
            
            frame_count += 1
        
        cap.release()
        out.release()
        cv2.destroyAllWindows()
        
        print(f"\n✅ Video guardado: {output}")
        self.guardar_estadisticas()
        self.mostrar_resumen()
    
    def procesar_imagen(self, ruta):
        """Procesa imagen"""
        print(f"\n🖼️ Procesando imagen...")
        print(f"Dirección: {self.direccion}")
        
        frame = cv2.imread(ruta)
        if frame is None:
            print("❌ Error: No se pudo cargar la imagen")
            return
        
        h, w = frame.shape[:2]
        frame_proc = self.procesar_frame(frame, 0)
        
        # Guardar
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        direccion_limpia = "".join(c for c in self.direccion if c.isalnum() or c in [' ', '-', '_']).rstrip()
        direccion_limpia = direccion_limpia.replace(' ', '_')
        nombre = os.path.basename(ruta)
        nombre_sin_ext = os.path.splitext(nombre)[0]
        output = os.path.join(self.output_dir, f'{direccion_limpia}_{nombre_sin_ext}_{timestamp}.jpg')
        
        cv2.imwrite(output, frame_proc)
        print(f"✅ Imagen guardada: {output}")
        
        # Mostrar
        ventana = 'Analisis de Imagen'
        cv2.namedWindow(ventana, cv2.WINDOW_NORMAL)
        nuevo_w = int(w * self.escala_visualizacion)
        nuevo_h = int(h * self.escala_visualizacion)
        frame_mostrar = cv2.resize(frame_proc, (nuevo_w, nuevo_h))
        cv2.resizeWindow(ventana, nuevo_w, nuevo_h)
        cv2.imshow(ventana, frame_mostrar)
        cv2.waitKey(0)
        cv2.destroyAllWindows()
        
        self.guardar_estadisticas()
        self.mostrar_resumen()
    
    def guardar_estadisticas(self):
        """Guarda estadísticas con análisis de congestión"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        direccion_limpia = "".join(c for c in self.direccion if c.isalnum() or c in [' ', '-', '_']).rstrip()
        direccion_limpia = direccion_limpia.replace(' ', '_')
        archivo = os.path.join(self.output_dir, f'estadisticas_{direccion_limpia}_{timestamp}.json')
        
        # Análisis de congestión promedio
        if self.historial_conteo:
            promedio_general = sum(self.historial_conteo) / len(self.historial_conteo)
            max_vehiculos = max(self.historial_conteo) if self.historial_conteo else 0
        else:
            promedio_general = 0
            max_vehiculos = 0
        
        stats = {
            'direccion': self.direccion,
            'total_vehiculos': self.estadisticas['total_vehiculos'],
            'por_tipo': dict(self.estadisticas['por_tipo']),
            'por_color': dict(self.estadisticas['por_color']),
            'analisis_congestion': {
                'promedio_vehiculos_simultaneos': round(promedio_general, 2),
                'max_vehiculos_simultaneos': max_vehiculos,
                'historial': self.estadisticas['congestion'][-10:] if self.estadisticas['congestion'] else []  # Últimos 10 registros
            },
            'timestamp': datetime.now().isoformat()
        }
        
        with open(archivo, 'w', encoding='utf-8') as f:
            json.dump(stats, f, indent=2, ensure_ascii=False)
        
        print(f"\n📊 Estadísticas guardadas en: {archivo}")
    
    def mostrar_resumen(self):
        """Muestra resumen incluyendo congestión"""
        print("\n" + "="*50)
        print("📊 RESUMEN FINAL")
        print("="*50)
        print(f"Dirección: {self.direccion}")
        print(f"Total vehículos: {self.estadisticas['total_vehiculos']}")
        
        # Análisis de congestión
        if self.historial_conteo:
            promedio = sum(self.historial_conteo) / len(self.historial_conteo)
            maximo = max(self.historial_conteo)
            print(f"\n🚦 ANÁLISIS DE CONGESTIÓN:")
            print(f"   Promedio vehículos simultáneos: {promedio:.1f}")
            print(f"   Máximo vehículos simultáneos: {maximo}")
            
            if promedio < self.umbral_bajo:
                print(f"   Nivel general: FLUJO LIBRE ✅")
            elif promedio < self.umbral_moderado:
                print(f"   Nivel general: MODERADO 🟡")
            elif promedio < self.umbral_alto:
                print(f"   Nivel general: CONGESTIÓN ALTA 🟠")
            else:
                print(f"   Nivel general: CONGESTIÓN SEVERA 🔴")
        
        print("\nPor tipo:")
        for t, c in self.estadisticas['por_tipo'].items():
            print(f"  {t}: {c}")
        print("\nPor color:")
        for col, c in self.estadisticas['por_color'].items():
            print(f"  {col}: {c}")
        print("="*50)

def main():
    print("\n" + "="*50)
    print("🚗 SISTEMA DE CONTEO DE VEHÍCULOS CON ANÁLISIS DE CONGESTIÓN")
    print("="*50)
    
    # Pedir dirección
    direccion = input("\n📍 Ingrese la dirección: ").strip()
    if not direccion:
        direccion = "Direccion_no_especificada"
        print("⚠️ Usando 'Direccion_no_especificada'")
    
    print("\nSeleccione modo:")
    print("1. 📹 Cámara web")
    print("2. 🎥 Archivo de video")
    print("3. 🖼️ Imagen")
    
    op = input("\nOpción (1-3): ").strip()
    
    out_dir = input("Directorio resultados (Enter para './resultados'): ").strip()
    if not out_dir:
        out_dir = "./resultados"
    
    escala = input("Escala visualización (0.3-2.0, Enter para 0.8): ").strip()
    escala = float(escala) if escala else 0.8
    
    if op == '1':
        sistema = SistemaConteoVehiculosSimple(0, out_dir, escala, 'camara', direccion)
        sistema.procesar_video()
    elif op == '2':
        ruta = input("Ruta del archivo de video: ").strip()
        if os.path.exists(ruta):
            sistema = SistemaConteoVehiculosSimple(ruta, out_dir, escala, 'video', direccion)
            sistema.procesar_video()
        else:
            print("❌ Archivo no encontrado")
    elif op == '3':
        ruta = input("Ruta de la imagen: ").strip()
        if os.path.exists(ruta):
            sistema = SistemaConteoVehiculosSimple(ruta, out_dir, escala, 'imagen', direccion)
            sistema.procesar_imagen(ruta)
        else:
            print("❌ Archivo no encontrado")
    else:
        print("❌ Opción no válida")

if __name__ == "__main__":
    main()