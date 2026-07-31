# Guia de despliegue

## Hosting de produccion

Antes de subir la version final, verificar en el panel del hosting:

- version PHP compatible con Laravel 13, idealmente PHP 8.4 o superior;
- variables de entorno;
- HTTPS;
- conexion saliente a Supabase/PostgreSQL;
- SMTP;
- tareas programadas o alternativa para ejecutar `nwoffu:send-notifications`.

### Vercel

El proyecto incluye `vercel.json` y `api/index.php` para ejecutar Laravel como
funcion serverless con `vercel-php`. Vercel debe tener las variables secretas
configuradas en su panel o con `vercel env add`.

Importante: Vercel solo conserva archivos en disco durante la ejecucion de la
funcion. Los justificantes o documentos medicos deben moverse a un storage
persistente antes de usar la app con documentos reales. Mientras no se configure
un storage tipo S3/Supabase Storage compatible, los uploads pueden servir para
pruebas, pero no como archivo permanente.

Decision de base de datos:

- Produccion usa Supabase como PostgreSQL online.
- HeidiSQL y pgAdmin son herramientas de administracion, no alojan la base.
- PostgreSQL local (`127.0.0.1`, base `nwoffuprime`) queda solo para pruebas en la PC.
- No configurar `DB_HOST=127.0.0.1` en produccion.

## Variables requeridas

No subir credenciales reales al repositorio. Completar `.env` en el servidor.

### Supabase

```env
DB_CONNECTION=pgsql
DB_HOST=db.tu-proyecto.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=
DB_SSLMODE=require
```

### Correo

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-correo-autorizado@gmail.com
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-correo-autorizado@gmail.com
MAIL_FROM_NAME="N-Woffu Prime"
```

## Comandos de despliegue

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Tarea programada

Ejecutar cada 5 minutos:

```bash
php artisan nwoffu:send-notifications
```

## Seguridad minima

- `APP_DEBUG=false`
- `APP_ENV=production`
- HTTPS activo
- claves de usuarios seed cambiadas
- `MAIL_PASSWORD` con clave de aplicacion
- backups de base de datos y storage
