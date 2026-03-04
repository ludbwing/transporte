import cv2
import numpy as np
from ultralytics import YOLO
from collections import defaultdict
import config

class DetectorVehiculos:
    def __init__(self, modelo_path='yolov8n.pt'):
        """Inicializa el detector YOLO"""
        print("Cargando modelo YOLOv8...")
        self.model = YOLO(modelo_path)
        self.clases_vehiculos = config.CLASES_VEHICULOS
        self.rangos_hsv = config.RANGOS_HSV
    
    def detectar(self, frame):
        """
        Detecta vehículos en el frame
        Retorna lista de detecciones con bbox, tipo, confianza
        """
        results = self.model(frame)[0]
        detecciones = []
        
        for det in results.boxes.data:
            x1, y1, x2, y2, conf, cls = det.tolist()
            
            if int(cls) in self.clases_vehiculos and conf > config.CONFIANZA_MINIMA_YOLO:
                tipo = self.clases_vehiculos[int(cls)]
                
                detecciones.append({
                    'bbox': [x1, y1, x2, y2],
                    'tipo': tipo,
                    'confianza': conf,
                    'cls': int(cls)
                })
        
        return detecciones
    
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