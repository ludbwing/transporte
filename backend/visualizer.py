import cv2
import numpy as np
from datetime import datetime
import config

class Visualizador:
    def __init__(self, direccion=""):
        """Inicializa el visualizador"""
        self.direccion = direccion
    
    def dibujar_vehiculos(self, frame, vehiculos_activos):
        """
        Dibuja los vehículos en el frame
        """
        frame_out = frame.copy()
        
        for vid, info in vehiculos_activos.items():
            x1, y1, x2, y2 = map(int, info['bbox'])
            
            # Color del rectángulo según tipo
            color_rect = config.COLORES_TIPO.get(info['tipo'], config.COLORES_TIPO['default'])
            
            cv2.rectangle(frame_out, (x1, y1), (x2, y2), color_rect, 2)
            
            # Texto combinado: "Auto Rojo", etc.
            color_texto = info.get('color', 'desconocido').capitalize()
            tipo_texto = info.get('tipo', 'desconocido').capitalize()
            texto_completo = f"{tipo_texto} {color_texto}"
            
            cv2.putText(frame_out, texto_completo, (x1, y1-10),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.6, color_rect, 2)
        
        return frame_out
    
    def dibujar_panel_congestion(self, frame, nivel, color, descripcion, num_vehiculos, total_vehiculos):
        """
        Dibuja el panel de congestión en la parte superior
        """
        frame_out = frame.copy()
        h, w = frame_out.shape[:2]
        
        # Panel semitransparente
        overlay = frame_out.copy()
        cv2.rectangle(overlay, (10, 10), (350, 100), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.7, frame_out, 0.3, 0, frame_out)
        
        # Título
        cv2.putText(frame_out, "ANÁLISIS DE TRÁFICO", (20, 30),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        
        # Nivel de congestión
        cv2.putText(frame_out, f"Nivel: {nivel}", (20, 55),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)
        
        # Descripción y vehículos
        cv2.putText(frame_out, f"{descripcion} | Vehículos: {num_vehiculos}", (20, 80),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
        
        # Contador total en esquina
        cv2.putText(frame_out, f"Total: {total_vehiculos}", (w-150, 30),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
        
        return frame_out
    
    def dibujar_pie_de_pagina(self, frame):
        """
        Dibuja el pie de página con dirección, fecha y hora
        """
        frame_out = frame.copy()
        h, w = frame_out.shape[:2]
        
        # Fondo semitransparente
        overlay = frame_out.copy()
        cv2.rectangle(overlay, (10, h-40), (w-10, h-10), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.5, frame_out, 0.5, 0, frame_out)
        
        # Texto con dirección, fecha y hora
        fecha_hora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        texto_inferior = f"{self.direccion} | {fecha_hora}"
        cv2.putText(frame_out, texto_inferior, (20, h-15),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        
        return frame_out
    
    def dibujar_todo(self, frame, vehiculos_activos, nivel_congestion, color_congestion, 
                     descripcion, num_vehiculos, total_vehiculos):
        """
        Dibuja toda la información en el frame
        """
        frame_out = self.dibujar_vehiculos(frame, vehiculos_activos)
        frame_out = self.dibujar_panel_congestion(frame_out, nivel_congestion, color_congestion,
                                                  descripcion, num_vehiculos, total_vehiculos)
        frame_out = self.dibujar_pie_de_pagina(frame_out)
        return frame_out