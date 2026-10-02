---
name: actis-historial-paciente
description: Reglas y contexto sobre el flujo del Historial del Paciente para reprogramar o cancelar turnos.
---

# AGENTE: HISTORIAL DEL PACIENTE (REPROGRAMAR / CANCELAR TURNO)

**Objetivo:** Documentar el flujo para acceder al historial de un paciente con el fin de reprogramar o cancelar un turno existente, utilizando datos 100% reales extraídos del código provisto.

## Flujo Paso a Paso

### 1. Búsqueda de Paciente (Historial)
- **Página / URL:** `thpacientebusquedavalidacion.aspx?FtF+XOcVxPyylyUEn+cbk0s01GcRXiNkKs5zsQwymICTGeY6VRqB/oHRdMQdVGI1dY6VA18LXUseQp9a33cm6A==`
- **Archivo de referencia:** `lee5`
- **Acción de Búsqueda:** El operador ingresa el DNI del paciente en el campo de búsqueda.
  - **Elemento HTML (Input Búsqueda):**
    ```html
    <input type="text" id="vBUSCAR" name="vBUSCAR" value="" size="40" spellcheck="true" maxlength="40" class="Attribute" style="text-align:left" onfocus="gx.evt.onfocus(this, 24,'',false,'0001',0)" onchange="this.value=this.value.toUpperCase();;gx.evt.onchange(this, event)" onblur="this.value=this.value.toUpperCase();;gx.evt.onblur(this,24);" data-gx-context="[&quot;&quot;,false]" data-gxoldvalue="35911753" data-gxvalid="0" gxctrlchanged="1">
    ```
- **Selección de Resultado:** Al ingresar el DNI, aparece el resultado en la misma página. Se debe presionar el botón verde de "Seleccionar".
  - **Elemento HTML (Tilde Verde):**
    ```html
    <img src="/ALTEA_PRO/Resources/Seleccionar.gif" id="vSELECCIONAR_0001" alt="Seleccionar" title="Seleccionar" class="Image" onfocus="gx.evt.onfocus(this, 96,'',false,'',77)" tabindex="0" data-gx-evt-control="vSELECCIONAR" data-gx-evt="" data-gx-context="[&quot;&quot;,false]">
    ```

### 2. Grilla de Turnos del Paciente
- **Página / Archivo de referencia:** `leeMIRABASURAHIJODEPUTA`
- **Descripción:** Tras seleccionar al paciente, el sistema muestra una grilla con los turnos asociados al mismo (`Gridtd_thturnoContainerTbl`).
- **Datos Relevantes:** En esta grilla se puede visualizar el número de cada turno asignado dentro de un elemento `span`.
  - **Elemento HTML (Ejemplo de Número de Turno):**
    ```html
    <span class="ReadonlyAttribute_Grid" id="span_THTUNUMERO_0010" data-gx-enabled-id="THTUNUMERO_0010">   354609</span>
    ```
- **Acción a seguir:** Desde esta tabla el operador identificará el turno específico (como el 354609) que desea cancelar o reprogramar.
