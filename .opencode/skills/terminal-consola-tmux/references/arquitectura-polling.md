# Arquitectura del polling

## Inyección de `__terminalConfig` en el Blade
`startUrl`/`outputUrl`/`inputUrl`/`resizeUrl`/`endUrl`/`csrfToken`; placeholder `__UUID__` reemplazado en JS.

## Ciclo del poll
- `setInterval` 500 ms.
- `fetch(outputUrl + '?offset=' + clientOffset)`.
- Renderiza `data.output`; `clientOffset = data.offset`.
- Éxito resetea contador de fallos; catch incrementa.
- Umbral 10 → `clearInterval` + `console.error`.
- `end()` → `clearInterval` idempotente.

## Input / resize / end
Vía POST con `X-CSRF-TOKEN`.

## Assets mano-mantenidos
`public/js/terminal.js` + `public/vendor/*.mjs`, sin `@vite`. Verificar con `node --check` tras editar.
