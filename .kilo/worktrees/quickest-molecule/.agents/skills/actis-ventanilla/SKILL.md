---
name: actis-ventanilla
description: Reglas y contexto profundo sobre la Ventanilla de Recepción y tamp_ventanilla.php.
---

# AGENTE: VENTANILLA DE RECEPCIÓN
**Objetivo de este documento:** Proveer a la IA el contexto absoluto, técnico y funcional de cómo operan los puestos de recepción manual y su integración con IOSFA, para evitar daños al código en vivo.

## 1. Arquitectura y Flujo Principal
La ventanilla es operada por un humano (recepcionista). Su rol es validar a los pacientes usando el sistema oficial de IOSFA y luego disparar las impresiones de los tickets de la Policlínica (Ventanilla o Asistencia).

### A. Entorno Tampermonkey (`tamp_ventanilla.php`)
- **Propósito:** Inyectar una capa de UI sobre el validador oficial de IOSFA (`validador.iosfa.gob.ar/ValidadorDni`).
- **Diferencia con el Tótem:** A diferencia del Tótem (que secuestra toda la pantalla para hacer un Kiosco), `tamp_ventanilla.php` *respeta* la UI de DevExpress para que el operador pueda usarla normalmente, pero inyecta un submodal (panel verde/celeste) debajo del botón original de "Buscar Afiliado".
- **Botones Inyectados:**
  - `DESTINO DE VALIDACIÓN`: Para imprimir comprobantes de consulta.
  - `ASISTENCIA`: Para derivaciones internas.
- **Acción:** Al hacer clic en estos botones, el script extrae variables globales del DOM de IOSFA (nombre, DNI, estado, fuerza) y ejecuta un `window.open` hacia servidores locales (`imprimir_ventanilla_validacion.php`, etc.), pasándole los datos por GET.

### B. Entorno de Testeo (`test_tamp_ventanilla.php`)
- Dado que `tamp_ventanilla.php` es crítico, las mejoras experimentales se desarrollan en `test_tamp_ventanilla.php`.
- **Sandbox de Tampermonkey:** Al usar inyectores dinámicos (Loaders) en vez de instalar el script físico, surge el problema del "Sandbox". Las funciones como `abrirModalRollo()` definidas en el script no son visibles para el HTML inyectado (`onclick="abrirModalRollo()"`). 
- **Solución `unsafeWindow`:** Si el script se ejecuta de forma aislada, requiere exponer las funciones a `unsafeWindow`. Si se ejecuta mediante un Loader con `@grant none`, esto no es necesario porque corre en el contexto de la página nativa.

## 2. Archivos Críticos
- `tamp_ventanilla.php` (Producción intocable).
- `test_tamp_ventanilla.php` (Entorno seguro de pruebas).
- `recepcion_inicio.php` / `recepcion_procesar.php` (Paneles locales de los operadores).
- `imprimir_ventanilla_validacion.php` (Receptor final de los popups generados por el script).

## 3. Reglas Estrictas para la IA (Agente Ventanilla)
1. **Intocabilidad de `tamp_ventanilla.php`:** Este archivo está en vivo en los puestos físicos. **JAMÁS** modificarlo sin una orden explícita del usuario.
2. **Desarrollo en paralelo:** Toda refactorización de código UI o lógica de extracción debe hacerse SÓLO en `test_tamp_ventanilla.php`.
3. **Manejo de variables DevExpress:** El validador de IOSFA actualiza los campos usando callbacks de ASP.NET. La extracción de datos debe hacerse siempre después de que termine la animación de carga nativa.
4. **Intocabilidad del Loader de Tampermonkey:** El script de Tampermonkey usado para inyectar `test_tamp_ventanilla.php` (que contiene `@grant GM_xmlhttpRequest` y `eval(response.responseText)`) **JAMÁS** debe ser modificado. Modificarlo rompería la compatibilidad en más de 100 PCs físicas.
5. **Flujo de pruebas transparente:** Gracias al Loader mencionado, cualquier cambio hecho en `test_tamp_ventanilla.php` impacta inmediatamente en el entorno del usuario. El agente solo debe modificar este archivo de prueba y el usuario lo testeará en vivo sin necesidad de que el agente altere el Tampermonkey.
