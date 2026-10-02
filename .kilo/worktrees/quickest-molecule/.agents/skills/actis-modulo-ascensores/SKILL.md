---
name: actis-modulo-ascensores
description: Reglas y contexto sobre el módulo de gestión de ascensores, incidencias y reportes.
---

# 🛗 Módulo de Ascensores

## 📌 ¿Qué es?
Es el módulo encargado de gestionar el estado, incidencias y mantenimientos de todos los ascensores de la clínica.

## 📜 Reglas de Oro
1. **Flujo de Incidencias:** Un ascensor puede estar "Operativo", en "Mantenimiento" o con "Falla". Al registrar una falla o mantenimiento, se debe poder asignar un técnico y registrar una fecha.
2. **APIs (Flutter vs Web):** En la web admin (`admin_ascensores.php`, `ascensor_detalle.php`), el PHP maneja HTML y lógica directamente. Para el módulo en Flutter (`actis_admin_mobile`), los datos deberán servirse preferentemente en JSON, o se crearán adaptadores en la app.
3. **Mantenimiento PDF:** Generación de órdenes y reportes de mantenimiento se gestionan mediante `ascensor_orden_pdf.php`, `ascensor_pdf.php` utilizando FPDF.

## 📂 Archivos Clave (Backend)
- `admin_ascensores.php` (Panel web principal).
- `ascensor_detalle.php`, `ascensor_estadisticas.php`
- `ascensor_crear_incidencia.php`, `mantenimiento_ascensores.php`
- `ascensor_pdf.php`, `ascensor_orden_pdf.php`
- Tablas principales esperadas (BD): `ascensores`, `incidencias_ascensor`, `mantenimientos`.
