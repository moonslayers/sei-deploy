# Uso del panel Filament (/admin)

## Acceso

Crear usuario administrador:

```bash
php artisan make:filament-user
```

Login en `/admin`.

## Funcionalidades

- Dashboard de estado de backends/frontends (`RepoStatusService`) (solo lectura).
- No existen recursos CRUD de Backends/Frontends en Filament. El alta/edición de backends y frontends se hace por migración/seeder (`config/sei.php` + migración `2026_01_21_000002_seed_backend_config.php`); el panel solo muestra widgets de lectura.
- Página DeployScripts (`/admin/deploy-scripts`): edición del script por repo (plantilla vs propio, historial/restauración vía `deploy_script_versions`).
- Consola bash por backend (terminal web): endpoints en `routes/web.php` (`/terminal/{backend}/start`, `/terminal/{session}/input`, `/terminal/{session}/resize`, `DELETE /terminal/{session}`), canal Reverb privado `terminal.{sessionId}`. Requiere login.
- Terminal tmux-backed: sesiones detached `sei-<uuid>`; salida vía pipe-pane a logfile + poller reanudable `PollTerminalOutput` que broadcast-ea al canal. Límite de 3 sesiones concurrentes por usuario. Reaper `terminal:reap` (TTL 30 min, huérfanas `sei-*`). Requiere binario `tmux` en el servidor.

## Seguridad de la consola

- Los comandos se ejecutan como el usuario del web server, tras autenticación.
- Requiere Reverb + queue worker activos.
- Editor de scripts por repo (`/admin/deploy-scripts`): el script corre con el `.env` del backend y `GITHUB_TOKEN` como `$3`. Validación de shebang al guardar y al restaurar. Advertencia en UI.

## Procesos requeridos en servidor

- `php artisan reverb:start`
- `php artisan queue:work` (ya existía)
- node/npm para backends con frontend compilado (Filament assets / Vite).

## Variables de entorno nuevas

- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- Vite: `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`.
