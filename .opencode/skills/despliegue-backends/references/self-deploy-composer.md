# Auto-deploy de sei-deploy (self-deploy) y composer

## Modelo mental: dos caminos de deploy

- **Backends hermanos** (`../<repo>`): webhook → `BackendService` → `scripts/deploy-backend.sh`, que corre `composer install` en el directorio del backend. Ver [architecture.md](architecture.md).
- **sei-deploy mismo**: `POST /api/webhook/deploy` → `WebhookController::updateDeploy()` → `GitService::pullMainRepo()` (git pull de main en `base_path()`) → `GitService::installMainDependencies()` (`composer install` en `base_path()`) → rsync de frontends compilados.

El self-deploy ejecuta **el código que está en disco al momento del request**: el handler que procesa un webhook es la versión anterior al pull que ese mismo webhook dispara.

## El paso composer install del self-deploy

- Comando: `composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader` en `base_path()`.
- Sin `--no-dev` (consistencia con deploy-backend.sh) y **nunca `composer update`** (el lock está trackeado; update es no determinista y el servidor tiene OOM documentados con composer).
- Escaping obligatorio (`escapeshellarg`) — ver [shell-escaping.md](shell-escaping.md).
- Si falla, lanza Exception → el webhook responde 500 → el step del GH Action falla (curl -f, exit 22).

## Limitación chicken-and-egg (causó caída de producción en la migración a Laravel 13)

Un deploy que **cambia dependencias** deja el servidor con código nuevo que no bootea con el vendor viejo (ej: Laravel 13 + vendor Laravel 12 → 500 en bootstrap). El paso `composer install` solo existe en el código nuevo, que no puede correr hasta que composer haya corrido → el pipeline **no puede auto-repararse**.

**Reparación (SSH al servidor, una sola vez):**

```bash
cd <ruta-sei-deploy>
git pull origin main
sudo -u www-data composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
php artisan config:clear
```

Verificar con `php artisan --version`. Tras la reparación, los deploys siguientes ya se auto-reparan (el código en ejecución sí tiene el paso composer).

## Trampa del lock y platform.php

`composer.json` pinnea `config.platform.php = 8.3.0`. **Regenerar `composer.lock` siempre con el pin puesto.** Un lock generado en PHP más nuevo (ej: 8.5) resuelve Symfony 8.x (requiere PHP >= 8.4.1) y `composer install` falla en CI/servidor (PHP 8.3) con "Your lock file does not contain a compatible set of packages". Síntoma: el step "Install Composer dependencies" del CI falla en ~1s.

## Mecánica del GH Action de deploy

- `.github/workflows/deploy.yml`: setup PHP → construye payload → firma RSA-SHA256 → POST a `SEI_SERVER_URL` con `curl -f`.
- Un 500 del servidor **sí** falla el step (exit 22) — pero un success del GH Action solo garantiza que el handler respondió 2xx, no que el servidor quedó sano (caso real: deploy que dejó código Laravel 13 con vendor Laravel 12 y devolvió 200).
- El runner `ubuntu-slim` puede colgarse en "Setup PHP" (~20 min observado); si pasa, re-lanzar el job.
">