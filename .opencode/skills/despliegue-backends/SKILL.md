---
name: despliegue-backends
description: Arquitectura del sistema de despliegue automático de backends en sei-deploy. Cubre el flujo webhook→servicio→script, configuración, cómo agregar backends, comandos de migración personalizados, y precauciones de shell escaping.
license: MIT
compatibility: Laravel 11+, PHP 8.2+
---

## What I do

Documenta la arquitectura completa del sistema de despliegue automático de backends en sei-deploy. Proporciona guías para mantener, configurar y extender el sistema de despliegue, incluyendo la integración con GitHub webhooks, la ejecución de comandos artisan personalizados y las precauciones críticas de shell escaping.

## When to use me

Triggers: deploy, despliegue, backends, webhook, deploy-backend.sh, BackendService, config/sei.php, agregar backend, comandos de migración, migrate:fresh, migrate --force, shell escaping, escapeshellarg

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
   - Ejecuta: git pull → cache clear → cache regenerate → migrate → queue restart

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
