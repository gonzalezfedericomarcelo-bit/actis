---
name: actis-turnos
description: Reglas y contexto profundo sobre el Módulo de Turnos SGPS y tamp_turnos.php.
---

# AGENTE: TURNOS MÉDICOS (SGPS)
**Objetivo de este documento:** Proveer a la IA el contexto absoluto, técnico y funcional de cómo interactúa ACTIS con el Sistema de Gestión de Prestaciones de Salud (SGPS) para acelerar la carga de datos, sin romper el ecosistema de producción.

## 1. Arquitectura y Flujo Principal
El SGPS (`sgps.iosfa.gob.ar`) es el sistema donde los médicos y operadores registran los turnos. Para ahorrarle tipeo al operador, ACTIS inyecta un panel automatizado de extracción.

### A. Entorno Tampermonkey (`tamp_turnos.php`)
- **Doble Inyección:** Este es el único script que corre en **DOS dominios distintos**:
  1. `*://sgps.iosfa.gob.ar/*` (Pantalla del SGPS).
  2. `*://validador.iosfa.gob.ar/ValidadorDni*` (Validador IOSFA).
- **Flujo en SGPS:** 
  - Al cargar SGPS, inyecta un "Saludo Flotante" (Toast) para confirmar que el sistema ACTIS está conectado.
  - Inyecta un panel visual ("⚡ Extracción ACTIS") justo al lado del botón de Grabar Turno nativo del SGPS.
  - El operador aprieta el botón "Extraer Datos IOSFA" del panel inyectado. Esto abre la página del Validador como un pop-up (pasando el DNI limpio).
- **Flujo en Validador:**
  - El script detecta que está en el Validador. Auto-rellena el DNI, aprieta "Buscar" y lee los datos resultantes (Afiliado y Fuerza).
  - Guarda los datos usando `GM_setValue` (almacenamiento de Tampermonkey, ya que no puede usar localStorage cruzado por políticas de dominio).
  - Cierra la ventana emergente.
- **Retorno a SGPS:**
  - El script que se quedó en SGPS lee constantemente `GM_getValue`. Al detectar los datos nuevos, los auto-escribe en el campo de observaciones u otros inputs, ahorrando tiempo.

### B. Entorno de Testeo (`test_tamp_turnos.php`)
- **Problema Histórico (Bug de Cierre):** Si un operador entraba manualmente a `validador.iosfa.gob.ar` en una pestaña normal mientras tenía Tampermonkey activo, el script de turnos auto-ejecutaba la extracción y ¡le cerraba la pestaña en la cara!
- **Solución (`window.opener`):** En `test_tamp_turnos.php` se prueba una lógica fundamental: Solo auto-cerrar la ventana del validador si la condición `&& window.opener` se cumple (es decir, si fue abierta genuinamente por el SGPS como pop-up).

## 2. Archivos Críticos
- `tamp_turnos.php` (Producción intocable - El inyector de SGPS).
- `test_tamp_turnos.php` (Entorno de pruebas).
- `api_turnos_guardar.php` (API interna opcional si se cruzan datos).

## 3. Reglas Estrictas para la IA (Agente Turnos)
1. **Intocabilidad de `tamp_turnos.php`:** Este archivo está en vivo en los consultorios. **JAMÁS** modificarlo sin una orden explícita del usuario.
2. **Desarrollo en paralelo:** Toda refactorización debe hacerse en `test_tamp_turnos.php`.
3. **Manejo de Cross-Domain:** Jamás intentar cambiar `GM_setValue` por `localStorage` en este archivo, ya que `sgps` y `validador` son subdominios/dominios distintos en navegadores modernos y pueden tener bloqueos de Same-Origin Policy. `GM_setValue` es la única forma robusta de pasarse datos entre pestañas de dominios distintos.
