---
name: actis-ftp
description: Regla de despliegue automático a producción (FTP)
---

# AGENTE: ACTIS FTP
**Objetivo de este documento:** Instrucciones obligatorias para cuando el agente realiza modificaciones en código fuente que deban reflejarse en el entorno de producción (en vivo).

## Instrucción de Despliegue en Vivo
Cuando el usuario te indique que subas algo "en vivo" o que despliegues tus modificaciones, **debes usar el script local `publish-ftp.js`**.

1. **Uso de la herramienta:**
   Utiliza tu herramienta `run_command` y ejecuta `node publish-ftp.js <nombre-del-archivo>` en la raíz de `actis`.
   
   Ejemplo:
   ```bash
   node publish-ftp.js admin_totem.php
   ```

2. **Soporte Múltiple:**
   Puedes subir varios archivos a la vez pasándolos como parámetros adicionales:
   ```bash
   node publish-ftp.js admin_totem.php turnos_listar.php
   ```

3. **No uses cURL para FTP:**
   El servidor Hostinger requiere una configuración de FTPS que a veces falla con cURL nativo en Windows. Confía siempre en el script `publish-ftp.js` que utiliza `basic-ftp`.
