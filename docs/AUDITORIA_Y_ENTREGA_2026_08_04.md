# Auditoria y entrega - N-Woffu Prime

Fecha: 2026-08-04

## 1. Auditoria del estado actual

La aplicacion usa Laravel 13, Blade, Vite, CSS propio, PostgreSQL/Supabase en produccion y pruebas con migraciones Laravel. La arquitectura esta organizada alrededor de organizaciones (`organizations`), usuarios, perfiles de empleado, departamentos, sedes, reglas de ausencia, solicitudes, adjuntos, notificaciones y eventos de auditoria.

Los permisos actuales son simples: `admin` y `user`. El permiso operativo para gestionar Equipo y Reglas es `can_manage_company_rules`; el acceso a documentos medicos usa `can_view_medical_attachments`.

## 2. Funcionalidades existentes

- Registro e inicio de sesion.
- Roles `admin` y `user`.
- Equipo con promocion de usuarios a administrador.
- Solicitudes de vacaciones, permisos medicos por dias u horas, asuntos personales y formacion.
- Calendario visual, historial, filtros, reportes y solapamientos para admin.
- Reglas editables y activables/desactivables.
- Adjuntos privados y auditoria de descargas.
- Correos con outbox, reenvio y plantillas HTML/texto.
- Separacion por organizacion mediante `organization_id`.

## 3. Problemas y ambiguedades detectadas

- El permiso "Desarrollador" no tiene matriz aprobada; no se implemento.
- "Espacios de trabajo" no se implemento como nueva entidad porque la app ya tiene `organizations` y Woffu parece estructurar empresas mediante roles, centros de trabajo, departamentos y configuracion de empresa, no necesariamente un workspace visible independiente.
- `approval_level_count` existia en interfaz, pero no existe flujo real de aprobaciones por niveles; se oculto.
- Limite mensual/anual se usa en validaciones, asi que se oculto de la interfaz pero no se eliminaron columnas ni datos.
- La zona horaria se implemento para timestamps del sistema. Las fechas de ausencia siguen siendo fechas laborales de negocio, no instantes UTC.
- La regla retroactiva solo existe como booleano. No se implemento limite de dias hacia atras sin aprobacion previa.

## 4. Plan tecnico de implementacion

1. Puestos e invitaciones de equipo.
2. Zona horaria por usuario y visualizacion local de timestamps.
3. Etiquetas visibles para eventos/correos/actividad.
4. Vista previa privada de adjuntos compatibles.
5. Reglas: crear/eliminar reglas personalizadas, ocultar controles sin efecto real y mejorar textos.
6. Selector de rango de fechas para nueva solicitud.
7. Documentar bloqueos: Desarrollador, espacios de trabajo, aprobaciones por niveles, reglas retroactivas avanzadas.

## 5. Cambios en la base de datos

Nueva migracion `2026_08_04_000001_add_team_management_features.php`:

- `users.timezone`.
- `leave_types.is_system`.
- `job_positions`.
- `employee_profile_job_position`.
- `team_invitations`.
- Puestos base cargados por organizacion existente.
- Reglas base marcadas como sistema: vacaciones, medico, personales y formacion.

No se elimino informacion existente.

## 6. Cambios en backend

- Nuevo `AdminManagementController` para Gestion, invitaciones y puestos.
- Nuevo `InvitationController` para aceptar invitaciones.
- Nuevo `ProfileController` para zona horaria.
- Nuevo `TeamInvitationService`.
- Nuevos modelos `JobPosition` y `TeamInvitation`.
- Nuevas etiquetas visibles en `NotificationLabels`.
- `AttachmentController` ahora permite preview privado de PDF/JPG/PNG.
- `AdminRuleController` permite agregar y eliminar reglas personalizadas seguras.
- `EmployeeAccountService` soporta usuarios invitados y puestos base.

## 7. Cambios en frontend

- Nuevo menu "Gestion".
- Equipo muestra puestos asignados, selector multiple y creacion rapida de puesto por integrante.
- Gestion muestra invitaciones, enlace copiable, reenvio, revocacion y puestos personalizados.
- Perfil permite cambiar zona horaria manualmente.
- Deteccion automatica de zona horaria desde navegador.
- Nueva solicitud usa selector unico de rango con Flatpickr en espanol.
- Correos ya no muestran claves tecnicas en el filtro de Evento.
- Detalle de solicitud ya no muestra "Version".
- Adjuntos muestran "Vista previa" cuando el formato es compatible.

## 8. Matriz propuesta para el permiso "Desarrollador"

Pendiente de aprobacion antes de implementar.

| Area | Propuesta para Desarrollador |
| --- | --- |
| Gestion de usuarios | Ver usuarios, no crear/eliminar, no cambiar roles. |
| Invitaciones | No crear ni revocar invitaciones. |
| Puestos de trabajo | Ver puestos, no editar asignaciones. |
| Configuracion | Ver configuracion tecnica no sensible. |
| Reglas | Ver reglas, no modificarlas. |
| Solicitudes | Ver solo datos anonimizados o propios; no aprobar/rechazar. |
| Archivos privados | Sin acceso a documentos medicos ni adjuntos privados. |
| Correos | Ver estado tecnico de envios, no ver cuerpos con datos sensibles. |
| Integraciones | Ver estado y logs tecnicos; editar solo si se aprueba. |
| Registros tecnicos | Acceso a logs tecnicos sin datos medicos. |
| Datos sensibles | Sin acceso por defecto. |
| Edicion/eliminacion | Sin permisos destructivos por defecto. |

## 9. Investigacion sobre espacios de trabajo en Woffu

Fuentes revisadas:

- Woffu Help: estructura Woffu y clasificacion de empleados: https://woffu.my.site.com/help/s/article/estructura-woffu-una-buena-clasificacion-de-tus-empleados-as
- Woffu onboarding: roles, configuracion y carga de usuarios: https://woffu.com/es/onboarding-cliente-woffu/
- Pagina de producto Woffu: roles, calendarios, reglas, documentos y reportes: https://woffu.com/en/
- Help Woffu: departamentos y centros de trabajo: https://woffu.my.site.com/help/s/article/como-crear-departamentos-y-centros-de-trabajo

Resultado: Woffu usa una estructura de empresa con centros de trabajo, departamentos y roles. No encontre evidencia suficiente de un "workspace" como entidad visible separada equivalente a Slack/Notion. En N-Woffu Prime ya existe `organizations`; la recomendacion es mantener esa capa como separacion tecnica multiempresa y no agregar una nueva entidad de workspace hasta que el producto SaaS lo necesite.

## 10. Implementacion por modulos

- Puestos de trabajo: implementado.
- Invitaciones: implementado con token unico, hash, caducidad, reenvio, revocacion y aceptacion.
- Zona horaria: implementada para usuario, deteccion navegador y perfil manual.
- Correos/eventos: etiquetas visibles implementadas para correos y timeline.
- Equipo: asignacion multiple de puestos y creacion rapida implementadas.
- Adjuntos: preview privado implementado para PDF/JPG/PNG.
- Reglas: crear, editar, eliminar personalizadas, activar/desactivar y textos claros.
- Nueva solicitud: selector de rango implementado.

## 11. Pruebas realizadas

- `php artisan test`: 24 tests, 271 assertions.
- `vendor/bin/pint --test`: correcto.
- `npm run build`: correcto.

Cobertura nueva:

- Puestos: crear, asignar, editar y eliminar.
- Invitaciones: crear, aceptar, revocar, caducada y usada.
- Zona horaria: cambio manual y deteccion API.
- Adjuntos: descarga y vista previa.
- Reglas: crear/eliminar personalizada y activar/desactivar notificaciones.
- Permisos: acceso a Gestion protegido por backend.

## 12. Archivos modificados

Principales:

- `routes/web.php`.
- `app/Http/Controllers/AdminManagementController.php`.
- `app/Http/Controllers/InvitationController.php`.
- `app/Http/Controllers/ProfileController.php`.
- `app/Http/Controllers/AdminRuleController.php`.
- `app/Http/Controllers/AttachmentController.php`.
- `app/Models/JobPosition.php`.
- `app/Models/TeamInvitation.php`.
- `app/Services/TeamInvitationService.php`.
- `app/Support/NotificationLabels.php`.
- `resources/views/admin/management.blade.php`.
- `resources/views/admin/users.blade.php`.
- `resources/views/admin/rules.blade.php`.
- `resources/views/leave_requests/create.blade.php`.
- `resources/js/app.js`.
- `resources/css/app.css`.
- `tests/Feature/TeamManagementTest.php`.

## 13. Decisiones tecnicas tomadas

- Mantener permisos sobre `can_manage_company_rules` para no inventar un sistema nuevo sin aprobacion.
- Guardar solo hash de token de invitacion.
- No exponer URLs publicas de adjuntos; preview via ruta autenticada.
- Mantener `organizations` como separacion multiempresa.
- Ocultar limites mensual/anual y niveles de aprobacion, sin borrar datos ni columnas.
- Mantener desplegable Activa/Inactiva porque ya fue pedido explicitamente para reglas.
- Usar Flatpickr para selector de rango en lugar de calendario manual.

## 14. Funcionalidades no implementadas y motivo

- Permiso "Desarrollador": bloqueado hasta aprobar matriz.
- Espacios de trabajo visibles: bloqueado por investigacion y por existir `organizations`.
- Aprobaciones por niveles: no hay motor real; se oculto el campo.
- Limites mensual/anual: no se eliminaron porque el backend todavia los usa.
- Limite de dias retroactivos: requiere politica concreta.
- Conversion completa de horas de ausencia a UTC: requiere redisenar si las horas representan instantes reales o franjas laborales locales.
- Unificacion/eliminacion de estados historicos: requiere aprobacion para no romper filtros, correos y auditoria.
