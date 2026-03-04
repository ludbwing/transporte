# backend/test_procesamiento.py
import sys
import os
from pathlib import Path

print("🔧 Probando procesamiento de video/imagen...")
print("="*50)

try:
    from main import SistemaConteoVehiculos
    print("✅ SistemaConteoVehiculos importado")
except ImportError as e:
    print(f"❌ Error importando: {e}")
    sys.exit(1)

# Buscar un archivo de prueba
test_files = [
    "../uploads/test.mp4",
    "../uploads/test.jpg",
    "test.mp4",
    "test.jpg"
]

test_file = None
for f in test_files:
    if os.path.exists(f):
        test_file = f
        break

if test_file:
    print(f"📁 Archivo de prueba encontrado: {test_file}")
    
    try:
        sistema = SistemaConteoVehiculos(
            fuente=test_file,
            output_dir="./resultados_test",
            escala_visualizacion=0.8,
            modo="video" if test_file.endswith(('.mp4', '.avi', '.mov')) else "imagen",
            direccion="Test Dirección",
            usar_bd=False  # No usar BD para la prueba
        )
        
        print("✅ Sistema creado, procesando...")
        
        if test_file.endswith(('.mp4', '.avi', '.mov')):
            sistema.procesar_video()
        else:
            sistema.procesar_imagen(test_file)
            
        print("✅ Procesamiento completado")
        
    except Exception as e:
        print(f"❌ Error durante procesamiento: {e}")
        import traceback
        traceback.print_exc()
else:
    print("❌ No se encontró archivo de prueba")
    print("Por favor, coloca un video o imagen en la carpeta uploads/")