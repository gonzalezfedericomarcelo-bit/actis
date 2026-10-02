---
name: actis-totem-dashboard
description: Reglas y contexto exclusivo del Tótem Dashboard (Kiosco para afiliados, sin impresión).
---

# 📊 Tótem Dashboard (Kiosco Afiliados)

## 📌 ¿Qué es?
Es un sistema interactivo para los afiliados. Muestra información, carteleras, campañas y opciones de autogestión **SIN EMISIÓN DE TICKETS**. Se ejecuta en pantallas informativas y quioscos secundarios. Está vinculado fuertemente al archivo `tamp88.php`.

## 🚫 LO QUE NO ES (¡No confundir!)
- **NO ES el Tótem Electrón:** Este dashboard **NO IMPRIME TICKETS**. Si la tarea habla de "papel", "impresora", "ticket", **NO ES AQUÍ**.
- **NO ES la App Móvil:** La app móvil (`actis_admin_mobile`) es para personal administrativo.

## 📜 Reglas de Oro
1. **Lectura y Navegación:** Su objetivo es informativo e interactivo para el paciente.
2. **Interactividad Web:** Es esencialmente una PWA o sitio web en pantalla completa.
3. **Mantenimiento:** Las modificaciones visuales deben respetar el diseño para pantallas grandes.

## 📂 Archivos Clave
- `dashboard_totem.php`
- `tamp88.php` (Core interactivo del tótem de autogestión)
- Directorio asociado a animaciones / frontend: `carrusel_totem/`
