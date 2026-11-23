# Endpoints y rutas

## Rutas (middleware auth, `{session:uuid}`)
- `POST /terminal/{backend}/start` → `terminal.start`
- `POST /terminal/{session:uuid}/input` → `terminal.write`
- `POST /terminal/{session:uuid}/resize` → `terminal.resize`
- `DELETE /terminal/{session:uuid}` → `terminal.end`
- `GET /terminal/{session:uuid}/output` → `terminal.output`

## Comportamiento del controlador
- `abort_unless` ownership → 403.
- `output` lee el `.log` desde offset con `fopen`/`fseek`/`stream_get_contents`, actualiza `last_activity_at`, devuelve `{output, offset, size}`.
- Log inexistente → JSON vacío, no 404.

## Responsabilidades de TerminalService
`start`/`write`/`resize`/`end`, `pipe-pane`, `capture-pane` (seed), escaping, logging de fallos, RuntimeException solo si tmux new-session falla.

## Modelo de sesión y límites
`TerminalSession`: uuid, backend_id, user_id, last_activity_at, closed_at, output_offset, tmux_session. Límite 3 sesiones por usuario. Reaper `terminal:reap` TTL 30 min.
