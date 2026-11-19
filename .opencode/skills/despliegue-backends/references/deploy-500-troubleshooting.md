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
