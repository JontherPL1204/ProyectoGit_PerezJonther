# N-Woffu Prime

Aplicacion web para gestionar vacaciones, permisos, ausencias, saldos, documentos medicos y aprobaciones.

## Estado actual

Primera base funcional en Laravel:

- Login con sesiones Laravel.
- Roles `admin` y `user`.
- Organizacion inicial preparada para futuro SaaS.
- Reglas configurables desde admin.
- Vacaciones base: 15 dias anuales.
- Anticipacion base: 30 dias naturales.
- Prorrateo activado como regla configurable.
- Traspaso activado como regla configurable.
- Documentos medicos conservados y visibles para admin autorizado.
- Solicitudes, aprobacion, rechazo, cancelacion y auditoria base.
- Bandeja interna de notificaciones por correo.
- Interfaz responsive para PC y telefono.

La documentacion funcional completa esta incluida en `docs/product-package`.

## Requisitos locales

Este workspace incluye Composer local en `../tools/composer.phar` y un `php.ini` local en `../tools/php-8.4.ini`.

PHP 8.4 fue instalado con WinGet en:

`%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`

## Ejecutar localmente

Desde esta carpeta:

```powershell
& "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -c '..\tools\php-8.4.ini' artisan migrate:fresh --seed
& "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -c '..\tools\php-8.4.ini' artisan db:seed --class=DemoDataSeeder --force
npm run build
& "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -c '..\tools\php-8.4.ini' -S 127.0.0.1:8000 -t public vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Para pruebas locales rapidas se puede usar PostgreSQL local (`127.0.0.1`, base `nwoffuprime`). Para produccion en Berserk se mantiene Supabase/PostgreSQL online.

## Usuarios de prueba

Administrador:

- Correo: `javierperezlopez1204@gmail.com`
- Contrasena local: definida en `.env` como `SEED_ADMIN_PASSWORD`

Empleado:

- Correo: `empleado@n-woffu-prime.local`
- Contrasena local: definida en `.env` como `SEED_EMPLOYEE_PASSWORD`

Cambia estas claves antes de produccion.

## Variables de entorno importantes

Produccion usa Supabase/PostgreSQL online. No usar `127.0.0.1` en Berserk.

```env
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=
DB_PASSWORD=
DB_SSLMODE=require
```

En local, si se quiere probar contra PostgreSQL instalado en la PC:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nwoffuprime
DB_USERNAME=postgres
DB_PASSWORD=
DB_SSLMODE=disable
```

Correo SMTP:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=javierperezlopez1204@gmail.com
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=javierperezlopez1204@gmail.com
MAIL_FROM_NAME="N-Woffu Prime"
```

Para Gmail normalmente se recomienda usar una clave de aplicacion, no la clave normal de la cuenta.

## Notificaciones

Las notificaciones se guardan primero en `notification_outbox`. Para enviarlas:

```powershell
& "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -c '..\tools\php-8.4.ini' artisan nwoffu:send-notifications
```

## Pruebas

```powershell
& "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -c '..\tools\php-8.4.ini' vendor\bin\phpunit
```

## Antes de produccion

- Completar credenciales de Supabase.
- Completar SMTP real.
- Cambiar claves seed/locales.
- Validar despliegue en Berserk.
- Crear usuarios reales desde administracion o seed controlado.
- Revisar politica legal/laboral de documentos medicos.
