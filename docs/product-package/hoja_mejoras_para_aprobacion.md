# Hoja de mejoras para aprobacion

**Proyecto:** N-Woffu Prime  
**Fecha:** 30 de julio de 2026  
**Objetivo:** Revisar y aprobar las mejoras necesarias antes de pasar a diseno tecnico detallado y desarrollo.

## Decisiones ya definidas

- Nombre provisional/actual del producto: **N-Woffu Prime**.
- Uso inicial: herramienta interna para el equipo.
- Evolucion prevista: preparar la base para posible SaaS multiempresa.
- Vacaciones base de la empresa actual: 15 dias por periodo anual.
- Anticipacion minima actual para vacaciones: un mes o mas.
- Canal oficial inicial de notificaciones: correo electronico.
- El administrador puede ver documentos medicos, pero esta capacidad debe ser configurable.
- Todas las reglas sensibles deben quedar parametrizadas para poder cambiarse por empresa sin reprogramar.

## Mejoras propuestas

| # | Mejora | Prioridad | Motivo | Aprobacion |
|---|--------|-----------|--------|------------|
| 1 | Definir autenticacion definitiva: PHP propio o Supabase Auth | Alta | Evita duplicar sesiones, permisos y reglas de acceso. | [ ] Aprobar |
| 2 | Crear contrato de API `/api/v1` | Alta | Define entradas, respuestas, errores y permisos antes de programar frontend y backend. | [ ] Aprobar |
| 3 | Convertir el modelo de datos en migraciones PostgreSQL | Alta | Asegura tablas, relaciones, restricciones, indices y datos iniciales verificables. | [ ] Aprobar |
| 4 | Definir reglas exactas de saldos, prorrateo, anticipos y traspasos | Alta | El saldo es el punto mas critico del producto y no debe quedar ambiguo. | [ ] Aprobar |
| 5 | Decidir si las solicitudes pendientes reservan saldo | Alta | Cambia el calculo disponible, las validaciones y la experiencia del usuario. | [ ] Aprobar |
| 6 | Anadir desglose calculado por dia u hora en cada solicitud | Media | Permite auditar por que una solicitud consume determinados dias u horas. | [ ] Aprobar |
| 7 | Separar permiso de aprobacion y permiso de ver documentos medicos | Alta | Protege datos sensibles y reduce riesgos legales y de privacidad. | [ ] Aprobar |
| 8 | Definir equipos, departamentos o ubicaciones para calendario administrativo | Media | Hace posible filtrar ausencias, revisar cobertura y preparar crecimiento multiempresa. | [ ] Aprobar |
| 9 | Usar cola o patron outbox para notificaciones | Media | Evita que una aprobacion falle o quede inconsistente por un error al enviar correo. | [ ] Aprobar |
| 10 | Preparar wireframes minimos de los flujos principales | Media | Reduce cambios caros en interfaz antes de construir pantallas definitivas. | [ ] Aprobar |

## Decisiones que conviene cerrar antes de desarrollar

- Regla exacta para "un mes de anticipacion": 30 dias naturales o mismo dia del mes anterior.
- Reglas de incorporacion a mitad de ano.
- Motivos que consumen saldo y motivos que solo registran ausencia.
- Motivos que requieren justificante obligatorio.
- Politica de conservacion y eliminacion de adjuntos.
- Formato de exportacion: CSV, Excel o ambos.

## Recomendacion

Aprobar primero las mejoras 1 a 5 y 7. Son las que afectan directamente a seguridad, permisos, calculo de saldos y consistencia de datos. Las mejoras 6, 8, 9 y 10 pueden prepararse en paralelo, pero no deberian bloquear el inicio del diseno tecnico si las reglas principales ya estan cerradas.

## Resultado esperado tras aprobacion

Una vez aprobadas estas mejoras, el siguiente paso recomendado es preparar tres documentos derivados:

1. Migraciones y modelo PostgreSQL.
2. Contrato de API.
3. Backlog tecnico por fases del MVP.
