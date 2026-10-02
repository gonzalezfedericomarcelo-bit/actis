---
name: actis-totem
description: Reglas y contexto profundo sobre el Tótem de Autogestión (Kiosco) y tamp88.php.
---

# AGENTE: TÓTEM DE AUTOGESTIÓN (KIOSCO)
**Objetivo de este documento:** Proveer a la IA el contexto absoluto, técnico y funcional de cómo opera el Tótem para evitar errores de asunción al modificar código relacionado.

## 1. Arquitectura y Flujo Principal
El Tótem es un hardware físico (Kiosco) sin teclado ni mouse, operado únicamente mediante pantalla táctil. Su función es permitir que el paciente valide su DNI y obtenga un ticket impreso sin intervención humana.

El flujo se divide en dos partes sincronizadas:
1. **La interfaz local (PHP):** `totem_inicio.php` y `totem_procesar.php`.
2. **El Validador inyectado (Tampermonkey):** `tamp88.php`.

### A. Lógica Local (`totem_inicio.php`)
- **Ingreso:** El usuario tipea su DNI en un teclado numérico en pantalla.
- **Comunicación (El Puente):** No hay una API directa con IOSFA. El sistema usa `localStorage` como puente de mensajes. Cuando el usuario busca un DNI, `totem_inicio.php` guarda el DNI en `localStorage.setItem('totem_solicitar_busqueda', dni)`.
- **Pop-up Oculto:** Inmediatamente después, abre una ventana emergente hacia `validador.iosfa.gob.ar/ValidadorMejorado` con el nombre de ventana `ValidadorIOSFA`.
- **Espera Activa:** Un intervalo (polling) se queda leyendo el `localStorage` esperando que aparezca la clave `totem_codigo_iofa_final`. Cuando aparece, lee el JSON, imprime el ticket térmico automáticamente y resetea la pantalla.
### B. Autologin de Respaldo (`tamp_login_iosfa.user.js`)
- **Problema de Sesión:** Si la caché del navegador de la terminal se limpia o la sesión de IOSFA expira, la web redirige al portal oficial de login (que pide CUIT, contraseña y CAPTCHA). Si esto ocurre en el hardware del Tótem, los pacientes no pueden avanzar.
- **Solución:** `tamp_login_iosfa.user.js` es un script crítico que intercepta esta caída. Cuando detecta que el sistema cerró sesión, autocompleta el CUIT y la contraseña y fuerza el ingreso automáticamente. Todo el ecosistema de ACTIS (tanto Kiosco como Ventanilla) depende de que exista una sesión de administrador iniciada para funcionar.

### C. Inyección Tampermonkey (`tamp88.php`)
- **Propósito:** Secuestrar por completo la interfaz nativa (DevExpress) de la web de IOSFA para convertirla en un Kiosco.
- **Detección:** Este script corre en `*://validador.iosfa.gob.ar/ValidadorDni*`. Es un script MASIVO (más de 1800 líneas, ~127KB).
- **Modificaciones de Interfaz:**
  - Oculta popups nativos, alertas y "capas invisibles" de DevExpress que cuelgan el sistema táctil.
  - Maqueta un teclado compacto en pantalla calculado en porcentajes para que no se rompa la resolución.
  - Implementa botones gigantes ("Mundialistas") celestes para Asistencia y Validación.
  - Inyecta un "Carrusel" para seleccionar especialidades médicas.
- **Funciones Especiales:**
  - **Modo Dios:** Tocar 5 veces rápidas el logo de IOSFA (blanco) desbloquea la terminal o restaura el DOM si hay bloqueos horarios.
  - **Interceptor Nuclear de Errores:** Sobrescribe `window.alert` nativo. Si detecta un "Callback request failed" o "internal server error" de IOSFA, en lugar de mostrar el cartel (que trabaría la terminal táctil), fuerza un `window.location.replace` para autorecuperar el Tótem.
- **Retorno de Datos:** Una vez que el validador de IOSFA responde, `tamp88.php` captura el nombre, fuerza y estado, los empaqueta y los guarda en `localStorage.setItem('totem_codigo_iofa_final', datos)`. Esto es lo que `totem_inicio.php` estaba esperando. Finalmente, cierra el pop-up.

## 2. Archivos Críticos
- `totem_inicio.php` (Frontend y polling local).
- `tamp88.php` (Inyector masivo y UI del Validador).
- `tamp_login_iosfa.user.js` (Guardián de sesión y autologin).
- `admin_totem.php` (Panel de control físico: reseteo de pines, cantidad de papel de la impresora térmica, habilitación de terminales).

## 3. Reglas Estrictas para la IA (Agente Tótem)
1. **Intocabilidad de Tickets:** El formato de impresión térmica de los tickets NO DEBE MODIFICARSE bajo ningún concepto, a menos que el usuario lo solicite explícitamente. Las coordenadas y márgenes son exactos para el hardware.
2. **NUNCA usar `alert()` estándar en `tamp88.php`:** Un `alert` traba físicamente la terminal porque el usuario no tiene mouse para cerrarlo.
2. **Cuidado con el Polling:** No alterar los tiempos de `setInterval` en `totem_inicio.php` sin probar a fondo, ya que desincronizaría la comunicación con la ventana de Tampermonkey.
3. **Manejo del DOM de DevExpress:** En `tamp88.php` está prohibido eliminar nodos del DOM de IOSFA. Siempre se deben ocultar (`display: none !important` o mandarlos fuera de la pantalla con `position: absolute`), porque si se eliminan, el framework DevExpress crashea.
