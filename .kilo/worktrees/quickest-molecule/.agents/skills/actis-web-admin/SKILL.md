---
name: actis-web-admin
description: Reglas y contexto sobre el Panel de Administración Web principal en PHP.
---

# 🖥️ App Web (Admin Panel PHP)

## 📌 ¿Qué es?
Es el núcleo administrativo del sistema ACTIS, construido en PHP, HTML, CSS y JS. Aquí es donde los usuarios administrativos gestionan todo el ecosistema desde una computadora de escritorio.

## 🚫 LO QUE NO ES (¡No confundir!)
- **NO ES la App Móvil:** No usa Dart/Flutter, sino PHP.
- **NO ES la vista de Pacientes:** Es exclusivamente interna.

## 📜 Reglas de Oro
1. **Seguridad y Sesiones:** Ningún archivo debe mostrar datos si no hay una sesión activa validada.
2. **Modificaciones Críticas:** Archivos core como `index.php`, `dashboard.php`, `admin_*.php` deben ser modificados con extremo cuidado para no romper flujos administrativos.
3. **Ventanilla / Turnos:** Los administradores web controlan gran parte de estos flujos (ej. `tamp_turnos.php`, `tamp_ventanilla.php`), que tienen sus propias sub-reglas en sus respectivos skills.

## 📂 Archivos Clave
- `dashboard.php`
- `admin_modal.php`, `admin_servicios.php`, `admin_empresas.php`
- Consultas a BD directamente vía archivos PHP en la raíz.
