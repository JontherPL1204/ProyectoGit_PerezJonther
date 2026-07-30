# Reglas base configurables de N-Woffu Prime

**Fecha:** 30 de julio de 2026  
**Estado:** Propuesta base para aprobacion  
**Objetivo:** Definir reglas iniciales sencillas para el uso interno del equipo, dejando la estructura preparada para que cada regla pueda cambiarse si el producto evoluciona a SaaS.

## Principio general

N-Woffu Prime no debe tener reglas importantes escritas de forma fija en el codigo. Las reglas de vacaciones, permisos, adjuntos, anticipacion, aprobacion y visibilidad deben guardarse como configuracion por empresa, tipo de ausencia o perfil. El sistema puede nacer con valores por defecto simples, pero debe permitir cambiarlos sin reprogramar.

## Configuracion inicial recomendada

| Area | Regla base | Configurable por SaaS |
|---|---|---|
| Nombre del producto | N-Woffu Prime | Si |
| Uso inicial | Interno para el equipo | Si |
| Modelo futuro | Preparado para multiempresa/SaaS | Si |
| Periodo de vacaciones | Anual | Si |
| Dias de vacaciones base | 15 dias por empleado y periodo | Si |
| Anticipacion minima para vacaciones | 30 dias naturales antes del inicio | Si |
| Canal oficial de notificacion | Correo electronico | Si |
| Aprobacion de vacaciones | Requiere administrador | Si |
| Aprobacion de permisos medicos | Requiere administrador, salvo politica distinta | Si |
| Adjuntos medicos | Permitidos y privados | Si |
| Visibilidad de adjuntos medicos | Administrador autorizado puede verlos | Si, mediante permiso separado |
| Saldos negativos | No permitidos por defecto | Si |
| Solicitudes pendientes | Reservan saldo provisional por defecto | Si |
| Cancelacion de aprobadas | Requiere aprobacion administrativa | Si |
| Exportaciones | Base CSV, Excel opcional | Si |

## Tipos de ausencia iniciales

| Tipo | Unidad | Consume saldo | Anticipacion | Adjunto | Aprobacion |
|---|---|---:|---|---|---|
| Vacaciones | Dias | Si | 30 dias naturales | No requerido | Si |
| Permiso medico | Horas/minutos | No por defecto | Sin minimo por defecto | Opcional u obligatorio segun politica | Si |
| Asuntos personales | Dias u horas | Configurable | Configurable | No requerido por defecto | Si |
| Formacion | Dias u horas | No por defecto | Configurable | Opcional | Si |

## Reglas de saldo

- Cada empleado recibe una asignacion anual inicial de 15 dias para vacaciones.
- El saldo se calcula mediante movimientos: asignacion, consumo, devolucion, ajuste y traspaso.
- Una solicitud aprobada crea un movimiento negativo.
- Una cancelacion aceptada crea un movimiento positivo de devolucion.
- El saldo no se edita directamente desde pantalla.
- Por defecto no se permite saldo negativo.
- Los cambios manuales de saldo solo los puede hacer un administrador y deben quedar auditados.

## Reglas de anticipacion

- Para vacaciones, la regla base sera solicitar con al menos 30 dias naturales de anticipacion.
- La anticipacion debe validarse en servidor antes de guardar.
- El administrador podra registrar excepciones con comentario obligatorio.
- En modo SaaS, cada empresa podra definir otra anticipacion por tipo de ausencia.

## Reglas de aprobacion

- Las vacaciones requieren aprobacion administrativa.
- Los permisos medicos requieren aprobacion administrativa en la configuracion base.
- Rechazar una solicitud requiere comentario obligatorio.
- Aprobar, rechazar o aceptar una cancelacion debe ejecutarse en una transaccion.
- Dos administradores no deben poder aprobar o rechazar la misma solicitud dos veces.

## Reglas de documentos medicos

- Los documentos medicos se guardan en almacenamiento privado.
- El administrador puede verlos en la configuracion inicial.
- La capacidad de ver documentos medicos debe ser un permiso separado, por ejemplo `can_view_medical_attachments`.
- Cada consulta o descarga de documento medico debe registrarse en auditoria.
- Los correos y exportaciones no deben incluir enlaces permanentes a documentos medicos.

## Reglas de notificacion

- El canal oficial inicial sera el correo electronico.
- Se enviara correo al administrador cuando se cree una solicitud que requiere decision.
- Se enviara correo al usuario cuando su solicitud sea aprobada, rechazada o cuando se resuelva una cancelacion.
- Las notificaciones deben poder activarse o desactivarse por empresa en una version SaaS.

## Reglas de configuracion recomendadas

Estas reglas deberian existir como parametros editables:

| Parametro | Valor inicial |
|---|---|
| `annual_vacation_days` | 15 |
| `vacation_notice_days` | 30 |
| `allow_negative_balance` | false |
| `pending_requests_reserve_balance` | true |
| `default_notification_channel` | email |
| `admin_can_view_medical_attachments` | true |
| `medical_attachment_audit_required` | true |
| `approved_request_requires_cancel_flow` | true |

## Apartado administrativo para modificar reglas

La aplicacion debe incluir un apartado dentro del panel de administrador llamado **Configuracion de reglas**. Desde esa seccion, un administrador autorizado podra modificar manualmente las reglas de vacaciones, permisos, adjuntos, aprobaciones y notificaciones.

La estructura recomendada para este apartado queda detallada en el documento:

**estructura_admin_configuracion_reglas_n_woffu_prime**

## Pendientes de confirmacion

- Confirmar si "un mes de anticipacion" significa 30 dias naturales o el mismo dia del mes anterior.
- Definir si los 15 dias se prorratean cuando una persona entra a mitad de ano.
- Definir si existen dias acumulables o traspaso de saldo al siguiente periodo.
- Definir cuanto tiempo se conservaran los documentos medicos.
- Definir si en el futuro habra aprobadores por equipo o solo administradores generales.

## Recomendacion para aprobar ahora

Para empezar el desarrollo interno sin bloquear el futuro SaaS, recomiendo aprobar esta base:

1. Vacaciones: 15 dias anuales configurables.
2. Anticipacion: 30 dias naturales configurables.
3. Solicitudes pendientes: reservan saldo provisional.
4. Documentos medicos: privados, visibles para administrador autorizado y auditados.
5. Notificaciones: correo electronico como canal oficial inicial.
6. Reglas guardadas como configuracion por empresa y tipo de ausencia.
