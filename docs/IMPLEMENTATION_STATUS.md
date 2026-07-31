# Estado de implementacion

**Fecha:** 30 de julio de 2026

## Implementado

- Laravel 13 con PHP 8.4.
- Login con sesiones seguras.
- Roles `admin` y `user`.
- Organizacion inicial `N-Woffu Prime`.
- Reglas configurables desde panel admin.
- Vacaciones: 15 dias anuales configurables.
- Anticipacion: 30 dias naturales configurable.
- Prorrateo y traspaso activados como reglas configurables.
- Documentos medicos conservados y privados.
- Permiso separado `can_view_medical_attachments`.
- Permiso separado `can_manage_company_rules`.
- Solicitudes pendientes, aprobadas, rechazadas, canceladas y pendiente de cancelacion.
- Calculo de dias laborables segun jornada y calendario de festivos.
- Permisos por minutos con validacion de jornada.
- Saldos por movimientos.
- Prevencion de solapamientos.
- Aprobacion y cancelacion con transacciones.
- Auditoria de solicitudes, reglas y adjuntos.
- Adjuntos privados JPG, PNG y PDF hasta 5 MB.
- Outbox de notificaciones por correo.
- Comando `nwoffu:send-notifications`.
- Interfaz responsive para PC y telefono.

## Verificado

- `composer install`: correcto.
- `npm install`: correcto.
- `phpunit`: 15 pruebas, 182 aserciones, todo pasa.
- `npm run build`: correcto.
- `artisan route:list`: 27 rutas registradas.
- HTTP local:
  - `/login`: 200.
  - login admin: 302 esperado.
  - `/dashboard`: 200 autenticado.
  - `/admin/solicitudes`: 200 autenticado como admin.

## URL local

`http://127.0.0.1:8000`

## Usuarios seed

Admin:

- Correo definido por `.env` como `SEED_ADMIN_EMAIL`
- Contrasena definida en `.env` como `SEED_ADMIN_PASSWORD`

Empleado:

- `empleado@n-woffu-prime.local`
- Contrasena definida en `.env` como `SEED_EMPLOYEE_PASSWORD`

## Antes de subir a Berserk

- Completar `.env` real con Supabase.
- Completar `.env` real con SMTP y clave de aplicacion.
- Cambiar contrasenas seed.
- Confirmar que Berserk usa PHP 8.4 o version compatible con Laravel 13.
- Ejecutar migraciones en entorno real.
- Probar flujo con usuarios reales.

## Pendientes funcionales posteriores

- Gestion visual completa de usuarios.
- Gestion visual de jornadas y festivos.
- Informes/exportacion CSV o Excel.
- Integracion directa con Supabase Storage si se decide no usar disco local del hosting.
- PWA opcional.
