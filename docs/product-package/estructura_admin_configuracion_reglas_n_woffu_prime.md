# Estructura del apartado administrativo de configuracion de reglas

**Proyecto:** N-Woffu Prime  
**Fecha:** 30 de julio de 2026  
**Estado:** Propuesta para aprobacion  
**Objetivo:** Definir como el administrador podra crear, editar y mantener manualmente las reglas del sistema desde la aplicacion.

## Principio del modulo

El sistema debe permitir que las reglas principales se modifiquen desde el panel de administrador sin cambiar codigo. Para mantener seguridad y control, no todos los administradores deberian tener necesariamente acceso a estas opciones. La configuracion de reglas debe estar protegida por un permiso especifico.

Permiso recomendado:

`can_manage_company_rules`

## Ubicacion en la aplicacion

Menu administrador:

1. Solicitudes
2. Calendario
3. Usuarios
4. Jornadas
5. Festivos
6. Tipos de ausencia
7. Saldos
8. Informes
9. **Configuracion de reglas**
10. Auditoria

## Pantalla principal: Configuracion de reglas

La pantalla debe mostrar bloques separados por area. Cada bloque tendra valores actuales, boton de editar y registro de ultima modificacion.

Bloques recomendados:

| Bloque | Que permite configurar |
|---|---|
| Reglas generales | Periodo, zona horaria, idioma, moneda si aplica y modo interno/SaaS |
| Vacaciones | Dias anuales, anticipacion minima, saldo negativo, prorrateo y traspaso |
| Permisos y ausencias | Unidad, aprobacion, duracion minima/maxima y consumo de saldo |
| Adjuntos | Tipos permitidos, tamano maximo, obligatoriedad y privacidad |
| Documentos medicos | Quien puede verlos, auditoria obligatoria y conservacion |
| Notificaciones | Canal activo, destinatarios y eventos que envian correo |
| Cancelaciones | Reglas para cancelar pendientes y aprobadas |
| Excepciones | Casos permitidos con comentario obligatorio |

## Configuracion general

Campos recomendados:

| Campo | Tipo | Valor inicial | Nota |
|---|---|---|---|
| Nombre de empresa | Texto | Empresa interna | Editable |
| Modo de uso | Selector | Interno | Opciones: interno, SaaS |
| Periodo de saldo | Selector | Anual | Futuro: mensual, trimestral, personalizado |
| Inicio del periodo anual | Fecha/mes-dia | 1 de enero | Configurable |
| Zona horaria | Selector | America/Guayaquil | Importante para auditoria y fechas |
| Idioma principal | Selector | Espanol | Preparado para SaaS |

## Reglas de vacaciones

Campos recomendados:

| Campo | Tipo | Valor inicial |
|---|---|---|
| Dias de vacaciones por periodo | Numero | 15 |
| Unidad de vacaciones | Selector | Dias |
| Anticipacion minima | Numero | 30 |
| Unidad de anticipacion | Selector | Dias naturales |
| Permitir saldo negativo | Interruptor | No |
| Reservar saldo en solicitudes pendientes | Interruptor | Si |
| Prorratear por fecha de ingreso | Interruptor | Pendiente de decision |
| Permitir traspaso al siguiente periodo | Interruptor | Pendiente de decision |
| Requiere aprobacion | Interruptor | Si |
| Permitir excepcion administrativa | Interruptor | Si |
| Comentario obligatorio en excepcion | Interruptor | Si |

Validaciones:

- Los dias de vacaciones deben ser mayores o iguales a 0.
- La anticipacion minima no puede ser negativa.
- Si se permite saldo negativo, debe existir un limite maximo configurable.
- Si se permite excepcion administrativa, el comentario debe ser obligatorio.

## Tipos de ausencia

El administrador debe poder crear y editar tipos de ausencia.

Campos recomendados:

| Campo | Tipo | Ejemplo |
|---|---|---|
| Nombre | Texto | Vacaciones |
| Codigo interno | Texto | VACATIONS |
| Estado | Activo/inactivo | Activo |
| Unidad | Selector | Dias, horas, minutos |
| Consume saldo | Si/no | Si |
| Bolsa de saldo asociada | Selector | Vacaciones |
| Requiere aprobacion | Si/no | Si |
| Requiere adjunto | Selector | No, opcional, obligatorio |
| Es documento medico | Si/no | No |
| Anticipacion minima | Numero | 30 |
| Unidad de anticipacion | Selector | Dias naturales, horas |
| Duracion minima | Numero | 1 |
| Duracion maxima | Numero | 15 |
| Permite solicitudes retroactivas | Si/no | No |
| Visible para empleados | Si/no | Si |

Regla importante:

Un tipo de ausencia usado en solicitudes historicas no debe eliminarse fisicamente. Solo debe desactivarse.

## Reglas de adjuntos

Campos recomendados:

| Campo | Tipo | Valor inicial |
|---|---|---|
| Permitir adjuntos | Interruptor | Si |
| Formatos permitidos | Lista | JPG, PNG, PDF |
| Tamano maximo por archivo | Numero | 5 MB |
| Cantidad maxima por solicitud | Numero | Configurable |
| Renombrado interno obligatorio | Interruptor | Si |
| Almacenamiento privado | Interruptor | Si |
| Enlaces temporales | Interruptor | Si |

Validaciones:

- No permitir tipos de archivo fuera de la lista configurada.
- No confiar en el nombre original del archivo.
- Validar MIME real, extension y tamano.

## Reglas de documentos medicos

Campos recomendados:

| Campo | Tipo | Valor inicial |
|---|---|---|
| Administrador puede ver documentos medicos | Interruptor | Si |
| Requiere permiso separado | Interruptor | Si |
| Permiso requerido | Texto/sistema | can_view_medical_attachments |
| Auditar visualizaciones | Interruptor | Si |
| Auditar descargas | Interruptor | Si |
| Incluir enlaces en correos | Interruptor | No |
| Incluir enlaces en exportaciones | Interruptor | No |
| Tiempo de conservacion | Numero + unidad | Pendiente |

Regla recomendada:

Aunque el administrador pueda ver documentos medicos, esa capacidad debe poder quitarse a ciertos administradores mediante permisos.

## Reglas de notificaciones

Canal inicial: correo electronico.

Eventos configurables:

| Evento | Destinatario inicial | Activo por defecto |
|---|---|---|
| Solicitud creada | Administrador | Si |
| Solicitud aprobada | Usuario | Si |
| Solicitud rechazada | Usuario | Si |
| Cancelacion solicitada | Administrador | Si |
| Cancelacion aceptada | Usuario | Si |
| Cancelacion rechazada | Usuario | Si |
| Recordatorio de pendientes | Administrador | Opcional |

Campos recomendados:

- Correo remitente.
- Nombre visible del remitente.
- Lista de administradores notificables.
- Activar/desactivar cada evento.
- Plantilla basica de asunto y mensaje.

## Excepciones administrativas

El sistema debe permitir excepciones controladas para casos donde la regla general no aplique.

Ejemplos:

- Aprobar vacaciones con menos de 30 dias de anticipacion.
- Registrar una ausencia retroactiva.
- Ajustar saldo manualmente.
- Permitir saldo negativo temporal.

Cada excepcion debe exigir:

- Administrador responsable.
- Comentario obligatorio.
- Fecha y hora.
- Regla omitida.
- Valor anterior y valor aplicado.
- Evento de auditoria.

## Auditoria de cambios de reglas

Cada cambio en configuracion debe crear un evento de auditoria.

Datos minimos:

| Campo | Descripcion |
|---|---|
| Entidad modificada | Empresa, tipo de ausencia, adjunto, notificacion, saldo |
| Campo modificado | Nombre tecnico del campo |
| Valor anterior | Valor antes del cambio |
| Valor nuevo | Valor despues del cambio |
| Actor | Administrador que hizo el cambio |
| Comentario | Obligatorio para cambios sensibles |
| Fecha | Momento del cambio |
| IP y agente | Cuando sea razonable |

Cambios sensibles que deben exigir comentario:

- Permitir saldo negativo.
- Cambiar dias anuales de vacaciones.
- Cambiar anticipacion minima.
- Activar o desactivar visibilidad de documentos medicos.
- Cambiar conservacion de documentos.
- Permitir solicitudes retroactivas.

## Estructura tecnica recomendada

Tablas sugeridas:

| Tabla | Finalidad |
|---|---|
| company_settings | Configuracion general por empresa |
| leave_type_rules | Reglas especificas por tipo de ausencia |
| attachment_rules | Reglas de archivos por empresa o tipo de ausencia |
| notification_rules | Eventos y destinatarios configurables |
| admin_permissions | Permisos especiales de administradores |
| rule_change_events | Auditoria de cambios de configuracion |

## Flujo de edicion recomendado

1. Administrador entra a Configuracion de reglas.
2. Selecciona el bloque que quiere editar.
3. El sistema muestra valores actuales y advertencias si el cambio afecta saldos, permisos o privacidad.
4. Administrador modifica los campos permitidos.
5. Si el cambio es sensible, debe escribir comentario.
6. El sistema valida los valores.
7. El administrador confirma el cambio.
8. El sistema guarda configuracion y crea evento de auditoria.
9. El sistema muestra mensaje de exito y fecha de ultima modificacion.

## Recomendacion para el MVP interno

Para empezar de forma sencilla, implementar primero estas secciones:

1. Reglas de vacaciones.
2. Tipos de ausencia.
3. Adjuntos y documentos medicos.
4. Notificaciones por correo.
5. Auditoria de cambios de reglas.

Las secciones de equipos, aprobadores avanzados y reglas por ubicacion pueden quedar preparadas en base de datos, pero no necesitan interfaz completa en la primera version interna.
