# Debug: terminal negra / sin output

Árbol de decisión.

1. Consola JS del navegador:
   - ¿Error? ¿`window.__terminalConfig.outputUrl` definido?
   - Si es `undefined` → vista Blade compilada obsoleta → `php artisan view:clear`.
2. Pestaña Network:
   - ¿Hay requests cada 500 ms a `/terminal/{uuid}/output`?
     - No hay requests → JS roto o intervalo limpiado (se llamó `end()` o hubo 10 fallos consecutivos → `console.error`).
     - 200 con `{output:""}` → el server no vuelca salida al log: revisar `storage/logs/laravel.log` (warnings de TerminalService), permisos de `storage/app/terminal` para el usuario web, `tmux -V`, `tmux ls` (existe `sei-<uuid>`?).
     - 403 → sesión de otro usuario/auth.
     - 404/500 → rutas/boot (`route:list --path=terminal`).
3. En el servidor (como www-data, NO como uguess):
   - `sudo -H -u www-data tmux -V`
   - `sudo -H -u www-data tmux ls` (socket por UID: `/tmp/tmux-33`; `Permission denied` si lo corres como uguess es esperado).
   - `sudo -H -u www-data tmux capture-pane -pt <sei-uuid>` para ver si el pane tiene output.
   - `view:clear`, `ls -la storage/app/terminal`, `tail` de `laravel.log`.
4. Rama nueva: 200 con `{output:""}` + sesión tmux existe pero `capture-pane` vacío + log 0 bytes:
   - Causa probable: `default-shell` = `/usr/sbin/nologin` mata el server tmux al arrancar → sesión "OK pero no server".
   - Validar con conf custom: `sudo -H -u www-data tmux -f /tmp/tmux-www.conf -L wwwtest new-session -d 'echo hi'` y capturar pane; si sale output, confirma nologin.
   - Fix en curso (Tarea 1): `set -g default-shell /bin/bash` en conf aislada `storage/app/terminal/` + socket `-f/-L/-S` consistente. No pedir `chsh`.
5. view-source: `__terminalConfig` debe incluir `outputUrl`/`inputUrl`/`resizeUrl`/`endUrl`.
6. Env heredado DB_*: tmux pane hereda env de php-fpm (DB_*). Sanear con `env -u DB_*` y `tmux kill-server -L sei-terminal` único.
7. Permission denied /tmp/tmux-33: socket por UID; usar socket por UID (`-S`/`-L sei-terminal`) y conf `-f` en storage/.
8. pipe-pane solo-warning: verificar effectiveUser antes de pipe-pane.