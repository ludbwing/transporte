import os
from collections import defaultdict

# Rangos de color HSV
RANGOS_HSV = {
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

# Clases de vehículos (COCO dataset)
CLASES_VEHICULOS = {
    2: 'auto',
    3: 'moto',
    5: 'bus',
    7: 'camion'
}

# Colores para tipos de vehículo (BGR)
COLORES_TIPO = {
    'auto': (0, 255, 0),      # Verde
    'moto': (255, 255, 0),    # Cyan
    'bus': (255, 0, 0),       # Azul
    'camion': (0, 165, 255),  # Naranja
    'default': (255, 255, 255) # Blanco
}

# Umbrales de congestión
UMBRAL_BAJO = 3      # Menos de 3 -> flujo libre
UMBRAL_MODERADO = 8  # Entre 3 y 8 -> moderado
UMBRAL_ALTO = 15     # Entre 8 y 15 -> alto
# Más de 15 -> congestionado

# Parámetros de tracking
DISTANCIA_MAXIMA = 300
UMBRAL_REID = 40
FRAMES_PARA_OLVIDAR = 45
UMBRAL_REID_CONFIANZA = 0.6
CONFIANZA_MINIMA_YOLO = 0.5

def crear_directorio_salida(output_dir):
    """Crea el directorio de salida si no existe"""
    output_dir = os.path.abspath(output_dir)
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
        print(f"📁 Creado directorio: {output_dir}")
    return output_dir

def limpiar_nombre_direccion(direccion):
    """Limpia la dirección para usarla en nombres de archivo"""
    direccion_limpia = "".join(c for c in direccion if c.isalnum() or c in [' ', '-', '_']).rstrip()
    return direccion_limpia.replace(' ', '_')