# Despliegue de SEI Deploy (panel Filament /admin)

Guía para desplegar la aplicación sei-deploy en el servidor, incluyendo integración con nginx. El primer deploy se realiza manualmente; los siguientes se automatizan vía GitHub Actions + self-deploy.

## Descripción General

sei-deploy es una app Laravel 13 con panel Filament v4 en `/admin`. Recibe webhooks firmados (RSA-2048/SHA256) de los repositorios y ejecuta deploys: self-deploy de sí misma y deploys de backends/frontends hermanos. Requiere procesos persistentes (queue worker, Reverb) y tmux para la consola web.

## Requisitos Previos

### En el Servidor

- PHP ^8.3 con extensiones: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo
- Composer, git, rsync, node/npm, tmux, supervisor, cron, nginx, php8.3-fpm
- MySQL (base de datos para sei-deploy)
- Certificado TLS (certbot) para el vhost

### En el Repositorio

- Clon del repo sei-deploy en el servidor
- Repos hermanos (backends) en `../<basename>` y compilados en `../sei-compilados`
- Cada backend con su propio `.env`

## Configuración Paso a Paso

### Paso 1: Clonar y preparar el repo

```bash
cd /var/www   # o el directorio base elegido
git clone https://github.com/<org>/sei-deploy.git
cd sei-deploy
```

### Paso 2: Configurar `.env`

Copiar `.env.example` a `.env` y ajustar. Variables obligatorias:

| Variable | Valor / Nota |
|---|---|
| `APP_NAME` | Nombre de la app |
| `APP_ENV` | `local` o `production` (NO `testing`, pail no se registra en testing) |
| `APP_KEY` | generado por `key:generate` |
| `APP_DEBUG` | `false` en producción |
| `APP_URL` | `https://<dominio>` (con HTTPS) |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL. Mantener `PDO::MYSQL_ATTR_SSL_CA` (no usar `Pdo\Mysql`, incompatible con PHP 8.3) |
| `SESSION_DRIVER` | `file` (no hay migración de sessions) |
| `QUEUE_CONNECTION` | `database` |
| `BROADCAST_CONNECTION` | `reverb` (el default del skeleton es `null`, cambiarlo) |
| `CACHE_STORE` | `file` |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | credenciales Reverb |
| `REVERB_HOST` | dominio público (o IP) |
| `REVERB_PORT` | `8080` (o el expuesto) |
| `REVERB_SCHEME` | `https` si TLS termina en nginx |
| `REVERB_SERVER_HOST` | `0.0.0.0` |
| `REVERB_SERVER_PORT` | `8080` |
| `VITE_REVERB_APP_KEY` / `VITE_REVERB_HOST` / `VITE_REVERB_PORT` / `VITE_REVERB_SCHEME` | espejo de las anteriores (prefijo VITE_) |
| `PUBLIC_KEY` | RSA pública en base64 (ver docs/generacion-de-llaves.md) |
| `GITHUB_USER` / `GITHUB_TOKEN` | credenciales para pull de repos |
| `GITHUB_REPO_MAIN` / `GITHUB_REPO_SUBMODULE` | repos de self-deploy y compilados |
| `GIT_USER_NAME` / `GIT_USER_EMAIL` | identidad para commits |
| `ADMIN_EMAILS` | allowlist fail-closed del panel; sin ella nadie accede a `/admin` |
| `MAIL_*` | según necesidad |

> ⚠️ `BROADCAST_CONNECTION` por defecto es `log`/`null` en el skeleton; debe cambiarse a `reverb` para que la consola funcione.

### Paso 3: Instalar dependencias y preparar la app

```bash
composer setup
```

Esto ejecuta: `composer install`, copia `.env.example` si no existe `.env`, `key:generate`, `migrate --force`, `filament:assets`, `npm install`, `npm run build`.

### Paso 4: Crear usuario admin

```bash
php artisan make:filament-user
```

El email del usuario debe estar en `ADMIN_EMAILS`.

### Paso 5: Permisos

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Paso 6: Procesos persistentes

Queue worker vía supervisor (ver docs/configuracion-queue-jobs.md):

```bash
php artisan queue:work --daemon
```

Reverb:

```bash
php artisan reverb:start
```

Cron para el reaper de terminales:

```cron
* * * * * cd /ruta/sei-deploy && php artisan schedule:run >> /dev/null 2>&1
```

> 📝 `terminal:reap` corre cada 5 min (TTL 30 min, huérfanas `sei-*`).

## Integración con nginx

### Vhost completo

```nginx
server {
    listen 80;
    server_name deploy.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name deploy.example.com;

    root /ruta/sei-deploy/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/deploy.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/deploy.example.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> 📝 El panel vive en `/admin` y la raíz `/` redirige ahí; no requiere config especial en nginx. `APP_URL` debe usar HTTPS para que las URLs generadas sean correctas.

## Pipeline automático (GitHub Actions)

`.github/workflows/deploy.yml` envía el webhook firmado al servidor al hacer push a `main`. El deploy real corre en el servidor vía self-deploy (`GitService`):

1. `pullMainRepo` — git pull main
2. `installMainDependencies` — composer install
3. `publishFilamentAssets` — `php artisan filament:assets`
4. `buildFrontendAssets` — `npm ci && npm run build`
5. `runMigrations` — `php artisan migrate --force`
6. `pullCompiledRepo` + `deployAllFrontends` — pull de compilados y rsync a `/var/www`

Los comandos de UI de Filament (filament:assets) ya están incluidos en este flujo.

## Verificación

Checklist post-deploy:

- [ ] `curl -I https://deploy.example.com/` redirige a `/admin`
- [ ] `/admin/login` responde 200
- [ ] Assets CSS/JS devuelven 200 (`/css/...`, `/js/...`)
- [ ] Queue worker procesando (`php artisan queue:work` activo en supervisor)
- [ ] Reverb arriba (`php artisan reverb:start`)
- [ ] Consola web funciona (abrir terminal en `/admin`)
- [ ] `php artisan schedule:run` ejecuta `terminal:reap`

## Troubleshooting Común

### "notificationsComponent is not defined" + sin estilos

**Error:** Panel sin estilos, consola JS con `notificationsComponent is not defined`.

**Causa:** Assets de Filament no publicados.

**Solución:** `php artisan filament:assets` (ya automatizado en `composer setup` y self-deploy).

### "Command pail is not defined"

**Error:** `Command "pail" is not defined`.

**Causa:** `APP_ENV=testing` heredado (pail no se registra en testing).

**Solución:** `unset APP_ENV` o usar `.env` con `APP_ENV=local`.

### Consola no muestra salida

**Error:** Terminal web no muestra salida.

**Causa:** Evento mal escuchado (broadcastAs vs listen).

**Solución:** Verificar que el listener use el nombre correcto del evento broadcast. Si reaparece tras cambios, revisar `broadcastAs` vs `listen`.

### 500 al iniciar terminal

**Error:** 500 al iniciar sesión de terminal.

**Causa:** tmux ausente o límite de 3 sesiones por usuario alcanzado.

**Solución:** Instalar tmux; cerrar sesiones existentes (ahora devuelve 422 controlado).

### Lock/composer: platform.php 8.3.0

**Error:** `composer install` falla por dependencias Symfony 8.x.

**Causa:** Lock generado en PHP > 8.3 resuelve Symfony 8.x (requiere PHP >= 8.4.1).

**Solución:** Mantener `config.platform.php = 8.3.0` en composer.json y regenerar lock con PHP 8.3. Ver `.opencode/skills/despliegue-backends/references/self-deploy-composer.md`.

## Referencias

- [configuracion-backends.md](./configuracion-backends.md)
- [panel-usage.md](./panel-usage.md)
- [configuracion-queue-jobs.md](./configuracion-queue-jobs.md)
- [generacion-de-llaves.md](./generacion-de-llaves.md)
- [README.md](./README.md)
