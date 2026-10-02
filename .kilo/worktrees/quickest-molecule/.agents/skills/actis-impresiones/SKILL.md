---
name: actis-impresiones
description: Reglas estrictas sobre la inmutabilidad de los tickets, JSONs y el sistema de impresión.
---

# AGENTE: MÓDULO DE IMPRESIÓN
**Objetivo de este documento:** Proveer a la IA las restricciones técnicas del sistema de impresión térmica y de ventanilla.

## 1. Arquitectura y Flujo
El sistema genera tickets físicos para los pacientes. Estos se imprimen desde diferentes fuentes:
- **Tótem:** Se imprimen tickets térmicos de forma automática al validar el DNI, leyendo los JSON del `localStorage`.
- **Ventanilla:** El operador dispara `window.open` a `imprimir_ventanilla_validacion.php` o `imprimir_ventanilla_asistencia.php`.

## 2. Archivos Críticos
- `imprimir_ventanilla_validacion.php`
- `imprimir_ventanilla_asistencia.php`
- Todos los JSON de estado y configuración asociados a las impresiones.

## 3. Reglas Estrictas para la IA (Agente Impresiones)
1. **INMUTABILIDAD ABSOLUTA:** Los formatos de impresión, los estilos CSS de los tickets, los JSON de estado y la estructura de los comprobantes **NUNCA DEBEN MODIFICARSE**. 
2. Las coordenadas, tamaños de fuente y márgenes están milimétricamente calculados para el hardware de las impresoras térmicas y láser de la clínica. Alterar un pixel puede arruinar el rollo de papel o descuadrar la firma del médico.
