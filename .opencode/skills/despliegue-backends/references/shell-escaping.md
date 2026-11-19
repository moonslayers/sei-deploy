# Precauciones de shell escaping

Caso concreto: `migrate_command` con espacios (ej. `migrate:fresh --seed`) debe pasarse con `escapeshellarg()` a `exec()` para evitar word splitting. Ver ../../../../AGENTS.md § Deployment Constraints → Shell command safety.

## Bug histórico (repo)

`BackendService::executeArtisanCommands()` arma el comando de migración y lo pasa al script `deploy-backend.sh` como parámetro `$6` (MIGRATE_COMMAND). El script invoca `exec()`/`/bin/sh -c`, que hace word splitting sobre el string sin comillas: `migrate:fresh --seed` se parte en `migrate:fresh` y `--seed` se pierde (o se interpreta como argumento suelto). El parámetro `$7` (si se añade) sufre el mismo problema.

Ejemplo mínimo (repo):

```php
// BackendService::executeArtisanCommands()
$migrate = $this->getMigrateCommand(); // "migrate:fresh --seed"
exec('bash deploy-backend.sh ' . escapeshellarg($backendPath) . ' ... ' . escapeshellarg($migrate));
```

```bash
# deploy-backend.sh
MIGRATE_COMMAND="$6"
# MAL: eval "$MIGRATE_COMMAND"  -> word splitting
# BIEN: usar el valor ya escapado o reconstruir con escapeshellarg
php artisan $MIGRATE_COMMAND   # solo si MIGRATE_COMMAND viene escapado
```

Regla transversal: ver ../../../../AGENTS.md § Deployment Constraints → Shell command safety.
