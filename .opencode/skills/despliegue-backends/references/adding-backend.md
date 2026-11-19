# Cómo agregar un backend nuevo

1. Agregar `"org/repo"` al array `backends` en `config/sei.php`
2. Si es monorepo, agregar mapeo en `backend_paths` (ej: `'org/repo' => 'subdirectorio'`)
3. Si necesita comando de migración especial, agregar en `backend_commands` (opcional)
4. Clonar el repo al mismo nivel que sei-deploy
5. Configurar `.env` del backend con sus variables de entorno (ver ../../../../AGENTS.md § Deployment Constraints → Environment variables)
