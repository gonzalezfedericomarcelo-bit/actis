---
name: actis-totem-electron
description: Reglas y contexto exclusivo del Tótem Electrón (Emisor de Tickets físicos para el público).
---

# 🤖 Tótem Electrón (Kiosco Físico de Tickets)

## 📌 ¿Qué es?
Es la aplicación que corre en los quioscos físicos en la clínica. Su ÚNICA función principal es **emitir tickets impresos** para que los pacientes sean llamados en recepción o consultorio. Está desarrollado sobre tecnologías web embebidas en Electron (`totem_electron/`).

## 🚫 LO QUE NO ES (¡No confundir!)
- **NO ES el Dashboard de Afiliados:** Ese es el `totem_dashboard` o `tamp88.php`.
- **NO ES la App Móvil:** La app móvil (`actis_admin_mobile`) es para la administración.
- **NO ES el panel de Recepción:** Ese es `tamp_ventanilla.php`.

## 📜 Reglas de Oro
1. **Inmutabilidad de Tickets:** El formato de los tickets impresos está altamente acoplado a la impresora térmica y configuraciones de hardware locales. No alterar tamaños de papel ni márgenes sin orden explícita.
2. **APIs Exclusivas:** Utiliza `api_totem_auth.php`, `api_totem_enviar_turnos.php`, etc.
3. **Impresión Silenciosa:** Depende de configuraciones de Electron para hacer "silent printing" (`BACKUP_TICKETS_TOTEM_CENTRO.rar`, etc.).
4. **Flujo Cautivo:** La aplicación siempre debe estar en pantalla completa y no permitir al usuario salir al sistema operativo.

## 📂 Archivos Clave
- `admin_totem.php`, `admin_totem_kiosco.php`
- `totem_inicio.php`, `totem_procesar.php`, `totem_imprimir.php`
- Directorio: `totem_electron/`
