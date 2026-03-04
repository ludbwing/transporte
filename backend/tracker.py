import math
import cv2
import numpy as np
from collections import defaultdict
import config

class TrackerVehiculos:
    def __init__(self):
        """Inicializa el sistema de tracking"""
        self.siguiente_id = 0
        self.vehiculos_activos = {}
        self.vehiculos_historicos = set()
        self.base_datos = {}  # ID -> características
        
        # Parámetros
        self.distancia_maxima = config.DISTANCIA_MAXIMA
        self.umbral_reid = config.UMBRAL_REID
        self.frames_para_olvidar = config.FRAMES_PARA_OLVIDAR
        self.umbral_reid_confianza = config.UMBRAL_REID_CONFIANZA
        
        # Estadísticas
        self.estadisticas = {
            'total_vehiculos': 0,
            'por_tipo': defaultdict(int),
            'por_color': defaultdict(int)
        }
    
    def obtener_centro(self, bbox):
        """Centro del bounding box"""
        return ((bbox[0] + bbox[2]) / 2, (bbox[1] + bbox[3]) / 2)
    
    def calcular_distancia(self, p1, p2):
        """Distancia euclidiana"""
        return math.sqrt((p2[0] - p1[0])**2 + (p2[1] - p1[1])**2)
    
    def comparar_histogramas(self, hist1, hist2):
        """Compara dos histogramas usando correlación"""
        if hist1 is None or hist2 is None:
            return 0
        
        try:
            correlacion = cv2.compareHist(hist1, hist2, cv2.HISTCMP_CORREL)
            return max(0, correlacion)
        except:
            return 0
    
    def buscar_en_base_datos(self, hist, tipo):
        """Busca en la base de datos si este vehículo ya fue visto"""
        mejor_id = None
        mejor_puntaje = 0
        
        for vid, data in self.base_datos.items():
            if 'hist' in data and data['hist'] is not None:
                puntaje = self.comparar_histogramas(hist, data['hist'])
                
                # Bonus por mismo tipo
                if data['tipo'] == tipo:
                    puntaje += 0.2
                
                if puntaje > mejor_puntaje and puntaje > self.umbral_reid_confianza:
                    mejor_puntaje = puntaje
                    mejor_id = vid
        
        return mejor_id
    
    def actualizar(self, frame, detecciones, frame_actual, detector):
        """
        Actualiza el tracking con nuevas detecciones
        Retorna lista de nuevos vehículos detectados
        """
        # Extraer características
        for det in detecciones:
            det['hist'] = detector.extraer_histograma_color(frame, det['bbox'])
            det['color'] = detector.obtener_color_dominante(frame, det['bbox'])
        
        # Marcar activos como no coincidentes
        for info in self.vehiculos_activos.values():
            info['coincidio'] = False
        
        # PRIMERO: Re-ID (buscar en base de datos)
        for det in detecciones:
            if det['hist'] is None:
                continue
            
            vid_en_bd = self.buscar_en_base_datos(det['hist'], det['tipo'])
            
            if vid_en_bd is not None:
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
        nuevas_detecciones = []
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
            nuevas_detecciones.append(det)
            self.vehiculos_historicos.add(nuevo_id)
        
        # Limpiar inactivos
        a_remover = []
        for vid, info in self.vehiculos_activos.items():
            if not info.get('coincidio', False):
                if frame_actual - info['ultimo_frame'] > self.frames_para_olvidar:
                    a_remover.append(vid)
        
        for vid in a_remover:
            if vid in self.vehiculos_activos:
                del self.vehiculos_activos[vid]
        
        # Actualizar estadísticas con nuevos vehículos
        for det in nuevas_detecciones:
            self.estadisticas['total_vehiculos'] += 1
            self.estadisticas['por_tipo'][det['tipo']] += 1
            self.estadisticas['por_color'][det['color']] += 1
            print(f"🚗 Nuevo: {det['tipo']} {det['color']} (ID:{det['id']})")
        
        return nuevas_detecciones
    
    def get_vehiculos_activos(self):
        """Retorna los vehículos activos actualmente"""
        return self.vehiculos_activos
    
    def get_estadisticas(self):
        """Retorna las estadísticas actuales"""
        return dict(self.estadisticas)