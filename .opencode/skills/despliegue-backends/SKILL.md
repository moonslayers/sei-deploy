---
name: despliegue-backends
description: Arquitectura del sistema de despliegue automático de backends en sei-deploy. Cubre el flujo webhook→servicio→script, configuración, cómo agregar backends, comandos de migración personalizados, instalación de dependencias de Composer, precauciones de shell escaping, y troubleshooting de deploy 500 truncado.
license: MIT
compatibility: Laravel 11+, PHP 8.2+
---

## What I do

Documenta la arquitectura completa del sistema de despliegue automático de backends en sei-deploy. Proporciona guías para mantener, configurar y extender el sistema de despliegue, incluyendo la integración con GitHub webhooks, la ejecución de comandos artisan personalizados y las precauciones críticas de shell escaping.

## When to use me

Triggers: deploy, despliegue, backends, webhook, deploy-backend.sh, BackendService, config/sei.php, agregar backend, comandos de migración, migrate:fresh, migrate --force, shell escaping, escapeshellarg, composer, composer install, librerías, dependencias, deploy 500, webhook 500, deploy truncado, composer truncated, OOM deploy, fakerphp faker, migrate:fresh --seed vdin

## Arquitectura del Sistema de Despliegue

El sistema opera en 4 etapas:

1. **GitHub webhook** → `WebhookController::updateBackend($repoName)`
   - Recibe el payload del push y extrae el nombre del repositorio
   - Valida que el backend esté en la lista de `config('sei.backends')`

2. **Controller** → Resuelve la ruta real del backend
   - Usa `config('sei.backend_paths')` para monorepos (ej: `moonslayers/vdin` → `vdin/vdin-api`)
   - Si no hay mapeo, usa el nombre del repo como ruta

3. **BackendService::updateBackend()** → Ejecuta la lógica de despliegue
   - `pullBackend()` — ejecuta git pull
   - `executeArtisanCommands()` — ejecuta cache clear, migrate y queue restart
   - Usa `getMigrateCommand()` para obtener el comando de migración personalizado

4. **deploy-backend.sh** → Script bash ejecutado en el servidor
   - Recibe 6 parámetros: BACKEND_PATH, GITHUB_USER, GITHUB_TOKEN, REPO_FULL_NAME, REPO_PATH, MIGRATE_COMMAND
   - Ejecuta: git pull → composer install → cache clear → cache regenerate → migrate → queue restart
    - `composer install` corre **siempre** en cada deploy, en el directorio del backend (`$BACKEND_PATH`), con los flags `--no-interaction --prefer-dist --no-progress --optimize-autoloader`
   - **REQUISITO PREVIO**: el servidor debe tener Composer instalado. Si no está disponible, el deploy falla visiblemente (comportamiento deseado) para evitar desplegar código que depende de librerías no instaladas

### Archivos clave

| Archivo | Descripción |
|---------|-------------|
| `config/sei.php` | Configuración central de backends |
| `app/Http/Controllers/WebhookController.php` | Endpoint del webhook |
| `app/Services/BackendService.php` | Lógica de despliegue |
| `scripts/deploy-backend.sh` | Script bash ejecutado en el servidor |
| `docs/configuracion-backends.md` | Documentación para usuarios |

## Configuración (config/sei.php)

Tres claves de configuración:

1. **`backends`** — Lista de backends en formato `"org/repo"`. Estos son los directorios al mismo nivel que sei-deploy.
   ```php
   'backends' => [
       'moonslayers/cef',
       'moonslayers/pmr',
       'moonslayers/vdin',
       // ...
   ],
   ```

2. **`backend_paths`** — Para monorepos: mapea `"org/repo"` → subdirectorio del backend. Solo necesario cuando el backend no está en la raíz del repo.
   ```php
   'backend_paths' => [
       'moonslayers/vdin' => 'vdin/vdin-api',
   ],
   ```

3. **`backend_commands`** — (OPCIONAL) Comandos de migración personalizados por backend. Si no se especifica, usa `migrate --force`.
   ```php
   'backend_commands' => [
       'moonslayers/vdin' => 'migrate:fresh --seed',
   ],
   ```

## Cómo Agregar un Backend Nuevo

1. Agregar `"org/repo"` al array `backends` en `config/sei.php`
2. Si es monorepo, agregar mapeo en `backend_paths` (ej: `'org/repo' => 'subdirectorio'`)
3. Si necesita comando de migración especial, agregar en `backend_commands` (opcional)
4. Clonar el repo al mismo nivel que sei-deploy
5. Configurar `.env` del backend con sus variables de entorno

## ⚠️ Precauciones de Shell Escaping

**BUG CRÍTICO**: Cuando se pasan comandos con espacios a `exec()`, usar `escapeshellarg()`.

`exec()` pasa el comando a `/bin/sh -c`, que hace **word splitting**. Un valor como `"migrate:fresh --seed"` se parte en dos argumentos (`$6=migrate:fresh`, `$7=--seed`). El script `deploy-backend.sh` solo lee `$6`, por lo que `--seed` se pierde silenciosamente y nunca se ejecuta.

### Patrón correcto ✅

```php
$migrateCommand = $this->getMigrateCommand($repoFullName);
$escapedMigrateCommand = escapeshellarg($migrateCommand);
$command = "bash {$scriptPath} {$backendPath} {$githubUser} {$githubToken} {$repoFullName} {$repoPath} {$escapedMigrateCommand} 2>&1";
```

### Patrón incorrecto ❌

```php
// ❌ INCORRECTO - pierde --seed por word splitting
$command = "bash {$scriptPath} ... {$migrateCommand} 2>&1";
```

## Troubleshooting: Deploy 500 truncado

### Síntoma

Webhook retorna 500 con `Deploy script failed`. El output de composer se trunca a mitad (ej: `barryvdh/laravel-dompdf ....` sin completar). Tiempos anormales (~9s en vez de ~24s).

### Cadena de captura

`deploy-backend.sh || exit 1` → `BackendService exec 2>&1` → `Exception` → JSON 500.

No hay truncado intencional: es muerte abrupta del proceso. El script falla y el error se propaga como excepción.

### Diagnóstico (checks en orden)

1. **OOM killer**: `dmesg | grep -i oom` + `php -i | grep memory_limit`
2. **Permisos bootstrap/cache**: `ls -la bootstrap/cache/` — debe mostrar owner `www-data`
3. **Error real**: `php artisan package:discover --ansi` manual + `tail storage/logs/laravel.log`

### Quirk: vdin y faker

`database/seeders/DatabaseSeeder.php:91` usa `fake()` que necesita `fakerphp/faker` (dev dependency). Con `--no-dev` se elimina y `migrate:fresh --seed` falla (solo vdin, destructivo).

**Decisión actual**: deploy sin `--no-dev` para incluir faker.

**Alternativa futura**: mover `fakerphp/faker` a `require` en vez de `require-dev`.

## Testing

Los tests de `BackendService` usan **reflection** para probar métodos protegidos:

```php
$service = new BackendService();
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('getMigrateCommand');
$result = $method->invoke($service, $repoFullName);
```

Para mockear la configuración:

```php
Config::set('sei.backend_commands', [
    'moonslayers/vdin' => 'migrate:fresh --seed',
]);
```

Los tests están en `tests/Unit/BackendServiceTest.php`.
