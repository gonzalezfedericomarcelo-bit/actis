# Regla de Oro: Tótem de Validación (`electron-totem`) es de SÓLO LECTURA

Esta regla es estricta e inquebrantable.

1. **NO TOCAR `electron-totem`:** El directorio `electron-totem` (conocido como "tótem de validación" o "tótem original") ya funciona perfectamente.
2. **PROHIBIDO COMPILAR:** Queda terminantemente prohibido editar, compilar, hacer build o modificar de cualquier manera los archivos dentro de `electron-totem` o sus dependencias.
3. **SOLO REFERENCIA:** Solo puedes leer su código para tomarlo como referencia cuando tengas que implementar comportamientos similares en otros sistemas, por ejemplo, en `electron-dashboard`.
4. **OBJETIVO DE DASHBOARD:** Si el usuario solicita que algo "funcione igual que el tótem", significa que debes aplicar la lógica al "tótem dashboard" (`electron-dashboard`), no modificar el tótem original.

Esta regla protege un sistema vital en producción que valida los ingresos. Ignorarla resultará en fallas críticas del sistema.
