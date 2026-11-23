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
3. En el servidor:
   - `view:clear`, `ls -la storage/app/terminal`, usuario de php-fpm, `tmux -V`, `tail` de `laravel.log`.
4. view-source: `__terminalConfig` debe incluir `outputUrl`/`inputUrl`/`resizeUrl`/`endUrl`.
