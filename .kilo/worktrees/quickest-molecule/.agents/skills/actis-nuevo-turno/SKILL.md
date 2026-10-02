---
name: actis-nuevo-turno
description: Reglas y contexto sobre el flujo de creación de un nuevo turno desde cero (thturnobusquedaporturnost.aspx).
---

# AGENTE: NUEVO TURNO DESDE CERO
**Objetivo de este documento:** Proveer el contexto paso a paso de cómo el operador busca y selecciona un turno nuevo desde cero en el SGPS. Este documento se irá completando a medida que avancemos en el desarrollo del flujo.

## Flujo Paso a Paso

### 1. Búsqueda de Turno (Página Inicial)
- **Página:** `thturnobusquedaporturnost.aspx` (Búsqueda Por Turno)
- **Acción del Operador:** El operador ingresa a esta página (representada por el archivo de referencia `lee1`), que se encuentra vacía inicialmente. 
- **Filtros:** Aquí se completan los filtros de Centro de Atención, Servicio, Especialidad, Profesional, y las Fechas/Horas. 
- **Ejecución:** Luego de cargar los filtros, se acciona la búsqueda para que el sistema traiga los turnos disponibles en una grilla.

### 2. Selección del Turno (Grilla de Resultados)
- Tras la búsqueda, aparece una grilla con los turnos encontrados.
- Para elegir el turno deseado, el operador (o nuestro script automático) debe hacer clic en el botón de **Seleccionar** de la fila correspondiente.
- **Elemento HTML Clave (Botón Seleccionar):**
  ```html
  <img src="/ALTEA_PRO/Resources/Seleccionar.gif" id="vSELECCIONAR_ACTION_0010" border="1" alt="Seleccionar" title="Seleccionar" class="Attribute_Image_Grid" style=";border-width: 1" onfocus="gx.evt.onfocus(this, 150,'',false,'',130)" tabindex="0" data-gx-evt-control="vSELECCIONAR_ACTION" data-gx-evt="" data-gx-context="[&quot;&quot;,false]">
  ```
  *(Nota: El sufijo numérico en el `id`, como `_0010`, corresponde a la fila elegida dentro de la grilla).*

### 3. Asignación de Paciente (Pantalla de Búsqueda y Validación)
- **Página:** `thpacientebusquedavalidacion.aspx` (Búsqueda de Paciente)
- **Estado Inicial:** El operador llega a esta página (representada por el archivo de referencia `lee2`) luego de hacer clic en el botón "Seleccionar" de la pantalla anterior. 
- **Contexto:** En esta instancia, el "Turno" ya está preseleccionado (se ven los datos del turno elegido: Centro, Servicio, Profesional, Especialidad, Fecha), pero el bloque de **Datos del Paciente** está vacío.
- **Próxima Acción Esperada:** El operador debe buscar un paciente (por DNI, Afiliado o Apellido/Nombre) para validarlo y asociarlo a este turno.

### 4. Búsqueda y Selección del Paciente
- **Acción de Búsqueda:** El operador ingresa el DNI del paciente en el campo de búsqueda y presiona Enter (o hace foco fuera del campo para disparar el evento).
  - **Elemento HTML (Input Búsqueda):**
    ```html
    <input type="text" id="vBUSCAR" name="vBUSCAR" value="" class="Attribute" data-gxoldvalue="35911753" ...>
    ```
- **Selección de Resultado:** El sistema devuelve los resultados de la búsqueda en una grilla. Para confirmar y seleccionar al paciente, se debe hacer clic en el tilde verde (botón "Seleccionar").
  - **Elemento HTML (Tilde Verde de Selección):**
    ```html
    <img src="/ALTEA_PRO/Resources/Seleccionar.gif" id="vSELECCIONAR_0001" alt="Seleccionar" title="Seleccionar" class="Image" ...>
    ```
  *(Nota: El sufijo `_0001` corresponde a la fila del paciente encontrado. Por lo general, al buscar por DNI exacto, es el primer resultado).*

### 5. Confirmación de Datos del Paciente
- **Página:** `thdatospacientevalidado.aspx` (Actualización Datos de Paciente)
- **Estado Inicial:** Tras seleccionar al paciente, el sistema carga esta pantalla (representada por el archivo de referencia `lee3`), donde se muestran todos los datos personales del paciente (Nombre, Apellido, Fecha de Nacimiento, Domicilio, etc.) ya completados.
- **Acción del Operador:** El operador revisa la información y debe presionar el botón **Confirmar** para avanzar en la asignación del turno.
  - **Elemento HTML (Botón Confirmar):**
    ```html
    <input type="button" name="CONFIRMAR" id="CONFIRMAR" value="Confirmar" class="Button_Standard" ...>
    ```

### 6. Grabación Final del Turno
- **Página:** (Representada por el archivo `lee4`).
- **Estado Inicial:** Tras confirmar los datos del paciente en la validación, el sistema procesa la información (detecta coberturas como IOSFA o Fuerzas Armadas y recupera el Número de Turno que viene acarreando desde el principio).
- **Acción del Operador:** Para finalizar y asignar definitivamente el turno al paciente, el operador presiona el botón **Grabar Turno**.
  - **Elemento HTML (Botón Grabar):**
    ```html
    <input type="button" name="BTN_GRABAR2" id="BTN_GRABAR2" value="Grabar Turno" class="Button_Standard" ...>
    ```

---
**Nota:** Iremos añadiendo los siguientes pasos y pantallas a medida que mapeemos el proceso completo de asignación de turno.
