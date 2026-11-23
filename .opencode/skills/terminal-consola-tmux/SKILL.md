---
name: terminal-consola-tmux
description: "Use when working on the tmux web terminal console (polling output, TerminalService/TerminalController, session lifecycle) or debugging a terminal that shows no output."
---

# Terminal console (tmux + polling)

## Modelo mental
Filament Console page → Blade inyecta `window.__terminalConfig` (URLs via route()/csrf_token(), respetan /sei via URL::useOrigin) → `public/js/terminal.js` (module, mano-mantenido, vite NO compila) renderiza xterm y hace polling GET a output cada 500 ms con `?offset=<clientOffset>` → controlador valida ownership y lee `storage/app/terminal/<uuid>.log` desde offset → devuelve `{output, offset, size}`. `TerminalService` administra tmux: sesión detached `sei-<uuid>`, dir/log, `pipe-pane` al log, `send-keys` con escapeshellarg, `capture-pane` (seed), resize, kill. Cliente reanuda desde offset. Sin jobs, sin colas, sin broadcasting.

## Puntos de entrada
- `app/Filament/Pages/Console.php` + `resources/views/filament/pages/console.blade.php`
- Navegación: `app/Filament/Pages/DeployScripts.php` → `Console::getUrl(['backend' => $record])`
- JS: `public/js/terminal.js`; vendor `public/vendor/xterm-6.0.0.mjs`, `public/vendor/addon-fit-0.11.0.mjs`
- Rutas: `routes/web.php` (auth): `terminal.start`, `terminal.write`, `terminal.resize`, `terminal.end`, `terminal.output`
- `app/Http/Controllers/TerminalController.php`, `app/Services/TerminalService.php`
- Modelo: `app/Models/TerminalSession.php` (uuid, backend_id, user_id, last_activity_at, closed_at, output_offset, tmux_session)
- Tests: `tests/Feature/TerminalTest.php`, `tests/Unit/TerminalServiceTest.php`
- Logs: `storage/app/terminal/<uuid>.log`

## Routing
| Tarea | Read when |
|---|---|
| Terminal negra / sin output | `references/debug-sin-output.md` |
| Polling, __terminalConfig, assets | `references/arquitectura-polling.md` |
| Rutas, controlador, service, modelo | `references/endpoints-rutas.md` |

## Reglas del dominio
- No hardcodear paths absolutos en Blade/JS: usar route()/url()/asset() (prod bajo /sei).
- `public/js/terminal.js` mano-mantenido: editar directo y verificar con `node --check`; vite NO lo compila.
- Todos los args shell pasan por escapeshellarg.
- tmux binario requerido; sesiones `sei-<uuid>` detached; límite 3 sesiones por usuario; reaper `terminal:reap` (TTL 30 min, huérfanas `sei-*`).
- `storage/app/terminal` debe ser escribible por el usuario web (www-data en prod); fallos de mkdir/file_put_contents/pipe-pane se registran con Log::warning (contexto vía effectiveUser()) y NO lanzan excepción; fallo de tmux new-session sí lanza RuntimeException.
- Poll se detiene (clearInterval) al ejecutar end() y tras 10 fallos consecutivos.
- Self-deploy NO corre view:clear: vistas Blade compiladas pueden quedar obsoletas → síntoma clásico de terminal rota.
