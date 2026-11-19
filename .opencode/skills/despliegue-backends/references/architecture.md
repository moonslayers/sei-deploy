# Arquitectura del sistema de despliegue

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
   - **Intencionalmente sin `--no-dev`** para incluir dependencias de desarrollo (ej. fakerphp/faker) requeridas por seeders; ver [deploy-500-troubleshooting.md](deploy-500-troubleshooting.md).
    - **REQUISITO PREVIO**: ver ../../../../AGENTS.md § Deployment Constraints → Server prerequisites

### Archivos clave

Ver ../../../../AGENTS.md § Deployment Constraints → Minimal repository map.
