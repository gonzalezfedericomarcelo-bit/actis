---
name: actis-pacientes
description: Reglas y contexto sobre el módulo de Pacientes, Médicos y Consultorios.
---

# AGENTE: PACIENTES Y CONSULTORIOS
**Objetivo de este documento:** Proveer a la IA el contexto funcional sobre cómo operan los médicos y el personal en los consultorios.

## 1. Arquitectura y Flujo Principal
El módulo de pacientes está diseñado para su uso en las computadoras dentro de cada consultorio de la Policlínica.

### A. Uso en Consultorio
- **Hardware/Entorno:** Cada consultorio cuenta con una computadora.
- **Actores:** En estas terminales operan tanto el Médico como el Recepcionista/Asistente.
- **Funcionalidad Principal:**
  - Gestionan la recepción del paciente derivado de Ventanilla o del Tótem.
  - Utilizan planillas internas para el registro, firmas y seguimiento de la atención médica.
  - Dependen de la estabilidad de la red y del Validador de IOSFA para confirmar el estado del paciente antes de la atención.

## 2. Reglas Estrictas para la IA (Agente Pacientes)
1. **Flujo Intocable:** Las planillas y el proceso de firma/registro en consultorio son críticos para la auditoría médica. No se deben alterar los flujos de carga de datos sin confirmación manual.
2. **Prioridad de UI:** La interfaz en estas computadoras debe mantenerse clara y enfocada en la velocidad de atención para no retrasar a los médicos.
