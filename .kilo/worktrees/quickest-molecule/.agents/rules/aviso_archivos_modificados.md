---
description: Regla estricta para notificar siempre al usuario qué archivos deben subirse al servidor (hosting) tras una modificación.
---

# Regla de Notificación de Archivos Modificados

**CUÁNDO APLICAR:**
Siempre que realices una modificación en cualquier archivo del proyecto, especialmente en archivos PHP, configuraciones, o cualquier archivo del backend/API que deba ejecutarse en un servidor remoto.

**QUÉ DEBES HACER:**
Al finalizar tu respuesta al usuario, DEBES incluir una lista clara y explícita de todos los archivos que fueron creados o modificados y que **necesitan ser subidos al servidor web/hosting** para que los cambios surtan efecto.

**FORMATO REQUERIDO EN EL CHAT:**
⚠️ **Archivos a subir al servidor:**
Para que estos cambios funcionen en producción, recordá subir los siguientes archivos a tu hosting (vía FTP o tu método de despliegue habitual):
- ruta/al/archivo_modificado.php
- ruta/a/otro_archivo.php

**RAZÓN:**
El usuario realiza el despliegue manualmente (FTP). Si no le avisas qué archivos tocaste, las versiones compiladas (como el APK de Flutter) fallarán al intentar comunicarse con una API desactualizada en el servidor remoto.
