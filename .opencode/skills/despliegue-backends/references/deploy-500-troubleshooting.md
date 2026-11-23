# Troubleshooting: Deploy 500 truncado

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

`vdin/vdin-api/database/seeders/DatabaseSeeder.php:95` usa `fake()` que necesita `fakerphp/faker` (dev dependency). Con `--no-dev` se elimina y `migrate:fresh --seed` falla (solo vdin, destructivo).

**Decisión actual**: deploy sin `--no-dev` para incluir faker.

**Alternativa futura**: mover `fakerphp/faker` a `require` en vez de `require-dev`.

### 404 en /api/webhook/{repo} con slash

Si el webhook usa `org/repo` (p.ej. `moonslayers/sagem-backend`) en la URL, retorna 404 porque la ruta acepta un solo segmento. Usar solo el basename (`sagem-backend`); el org se resuelve por suffix match en DB. En bash: `${BACKEND_NAME##*/}`.

### Caché de rutas vieja tras git pull manual

Tras un `git pull` manual puede quedar caché de rutas vieja. Ejecutar `php artisan route:clear` y verificar con `php artisan route:list --path=api/webhook`.

### Template vs Backend.script DB + deploy_script_versions restore
- `Backend.script` null = shared template `scripts/deploy-backend.sh`; per-repo scripts supported with version history (`deploy_script_versions`).
### npm solo sei-deploy
- npm solo en self-deploy; backends no npm.
### HOME/.npm/_logs no-writable
- HOME/.npm/_logs no-writable → chown/XDG.
### package:discover vía composer post-autoload
- `php artisan package:discover` vía composer post-autoload.
### deploy síncrono 500 con trace BackendService
- deploy síncrono retorna 500 con trace BackendService.

### npm: command not found bajo php-fpm

Aunque `npm` exista en shell, php-fpm corre con PATH mínimo. Crear symlinks en `/usr/bin` (p.ej. `ln -s $(which node) /usr/bin/node`, `ln -s $(which npm) /usr/bin/npm`) y verificar con `sudo -u www-data env -i PATH=/usr/bin:/bin node --version`.
