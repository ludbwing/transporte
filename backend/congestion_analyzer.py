from collections import deque
from datetime import datetime
import config

class CongestionAnalyzer:
    def __init__(self):
        """Inicializa el analizador de congestión"""
        self.historial_conteo = deque(maxlen=30)
        self.umbral_bajo = config.UMBRAL_BAJO
        self.umbral_moderado = config.UMBRAL_MODERADO
        self.umbral_alto = config.UMBRAL_ALTO
        self.historial_congestion = []
    
    def analizar(self, num_vehiculos):
        """
        Analiza el nivel de congestión basado en el número de vehículos activos
        Retorna: nivel, color, descripción, promedio
        """
        self.historial_conteo.append(num_vehiculos)
        
        # Calcular promedio de los últimos frames
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
        
        # Guardar en historial cada 30 frames
        if len(self.historial_conteo) % 30 == 0:
            self.historial_congestion.append({
                'timestamp': datetime.now().isoformat(),
                'nivel': nivel,
                'vehiculos': num_vehiculos,
                'promedio': round(promedio, 2)
            })
        
        return nivel, color, descripcion, num_vehiculos, promedio
    
    def get_resumen(self):
        """Obtiene resumen del análisis de congestión"""
        if not self.historial_conteo:
            return {
                'promedio': 0,
                'maximo': 0,
                'historial': []
            }
        
        promedio = sum(self.historial_conteo) / len(self.historial_conteo)
        maximo = max(self.historial_conteo)
        
        return {
            'promedio_vehiculos_simultaneos': round(promedio, 2),
            'max_vehiculos_simultaneos': maximo,
            'historial': self.historial_congestion[-10:]  # Últimos 10
        }
    
    def get_historial_conteo(self):
        """Retorna el historial de conteo"""
        return list(self.historial_conteo)