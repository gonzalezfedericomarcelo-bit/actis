---
name: actis-flujo-finalizacion
description: Reglas estrictas y obligatorias para finalizar CUALQUIER trabajo, despliegue FTP automático y compilación de la app móvil.
---

# 🛑 SKILL OBLIGATORIO DE FINALIZACIÓN DE TRABAJO

**CUÁNDO APLICAR:**
DEBES aplicar esta regla SIEMPRE, al final de cada modificación de código o de cualquier tarea que realices en el proyecto ACTIS, sin excepciones.

## 1. DESPLIEGUE FTP AUTOMÁTICO (Backend/Web)
No asumas que el usuario va a subir los archivos. **Es tu responsabilidad subir todo**.
- Si modificas archivos PHP, configuraciones, HTML, JS, CSS u otros archivos web/backend, **DEBES subirlos automáticamente** usando el script de despliegue.
- Comando: `node publish-ftp.js <ruta/al/archivo_modificado>` (ej. `node publish-ftp.js api_mobile/auth.php`).
- **Reporte obligatorio:** En tu respuesta al usuario, DEBES incluir un mensaje claro diciendo exactamente qué archivos subiste al servidor FTP.
- **Manejo de errores:** Si por algún motivo el comando de FTP falla, DEBES informarle al usuario inmediatamente que falló y pedirle disculpas, indicándole qué archivos debe subir manualmente.

## 2. COMPILACIÓN AUTOMÁTICA DE LA APP MÓVIL (Flutter)
No le pidas NUNCA al usuario que compile la aplicación móvil. **Tú eres quien compila.**
- Si modificas CUALQUIER archivo dentro de `actis_admin_mobile` (archivos `.dart`, `pubspec.yaml`, etc.), **DEBES compilar la aplicación**.
- Comando: `flutter build apk --release` (ejecutado dentro del directorio `actis_admin_mobile`).
- **Entrega del APK:** NUNCA le preguntes al usuario si quiere compilar. NUNCA le pidas comandos. Una vez terminada la compilación, **DEBES proveer la ruta absoluta exacta** al archivo compilado en el chat para que el usuario sepa dónde buscarlo.
  - Ejemplo de ruta a entregar: `C:\Users\HACKRO\Documents\GitHub\actis\actis_admin_mobile\build\app\outputs\flutter-apk\app-release.apk`
