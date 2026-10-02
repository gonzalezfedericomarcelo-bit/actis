---
name: actis-app-mobile
description: Reglas y contexto sobre la aplicación móvil Flutter nativa (actis_admin_mobile).
---

# 📱 App Mobile (Flutter APK Nativa)

## 📌 ¿Qué es?
Es la aplicación móvil administrativa nativa para Android compilada en Flutter (`actis_admin_mobile/`). Permite a los administradores, médicos y personal de seguridad gestionar las operaciones del sistema (ascensores, pacientes, dashboard, seguridad) directamente desde sus teléfonos móviles.

## 🚫 LO QUE NO ES (¡No confundir!)
- **NO ES una WebApp / PWA:** Es una aplicación Flutter con vistas nativas (Material Design).
- **NO ES para Pacientes:** Los pacientes usan el Tótem Kiosco o su plataforma, no esta app de administración.

## 📜 Reglas de Oro
1. **Material Design:** Toda nueva interfaz (ej. módulo de ascensores) debe respetar el diseño nativo de Android y usar los componentes definidos en el theme (`lib/core/theme.dart`).
2. **Arquitectura:** Usa la estructura de carpetas definida: `lib/features/` (para módulos como auth, dashboard, reports), `lib/core/` (para configuraciones globales, themes, api client).
3. **API y Backend:** La aplicación no interactúa directamente con la DB. Debe consumir endpoints de PHP ubicados en la raíz o en `api_mobile/` mediante solicitudes HTTP (`api_client.dart`).
4. **Permisos y Roles:** Cada vista debe ser consciente de los roles del usuario (Admin, Médico, Seguridad).

## 📂 Archivos Clave
- Directorio principal: `actis_admin_mobile/`
- Punto de entrada: `actis_admin_mobile/lib/main.dart`
- APIs para móviles: `api_check_session_staff.php`, `api_login_staff.php`, etc.
