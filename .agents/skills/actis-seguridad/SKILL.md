---
name: actis-seguridad
description: Reglas y contexto sobre el módulo de Seguridad, llaves, rondas y reportes.
---

# AGENTE: SEGURIDAD
**Objetivo de este documento:** Proveer a la IA el contexto funcional sobre cómo opera el personal de seguridad y el monitoreo físico de la clínica.

## 1. Arquitectura y Flujo Principal
El módulo de seguridad es una herramienta aislada del flujo de pacientes, utilizada exclusivamente por los guardias y administradores de seguridad del edificio.

### A. Funcionalidades
- **Gestión de Llaves (`seguridad_llaves.php`):** Control de qué miembro del personal tiene acceso a qué áreas físicas.
- **Rondas y Checkpoints:** Los guardias realizan recorridos físicos escaneando códigos QR distribuidos en el edificio para validar su posición y el horario de la ronda.
- **Control de Ingreso/Egreso:** Registro del personal que entra y sale de la Policlínica.
- **Dashboard de Seguridad (`seguridad_dashboard.php`):** Un panel de control centralizado que monitorea cámaras, llaves pendientes de devolución y el estado del tótem.

## 2. Reglas Estrictas para la IA (Agente Seguridad)
1. **Aislamiento:** El módulo de seguridad no debe interferir con el Validador de IOSFA ni con el SGPS. No inyectar scripts de validación de pacientes en estas páginas.
2. **Alta Disponibilidad:** El panel de control de seguridad debe estar siempre ligero y rápido. No agregar animaciones pesadas o carruseles innecesarios que distraigan la monitorización en tiempo real.
