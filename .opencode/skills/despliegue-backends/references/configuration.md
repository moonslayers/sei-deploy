# Configuración (config/sei.php)

Tres claves de configuración:

1. **`backends`** — Lista de backends en formato `"org/repo"`. Estos son los directorios al mismo nivel que sei-deploy.
   ```php
   'backends' => [
       'moonslayers/cef',
       'moonslayers/pmr',
       'moonslayers/vdin',
       // ...
   ],
   ```

2. **`backend_paths`** — Para monorepos: mapea `"org/repo"` → subdirectorio del backend. Solo necesario cuando el backend no está en la raíz del repo.
   ```php
   'backend_paths' => [
       'moonslayers/vdin' => 'vdin/vdin-api',
   ],
   ```

3. **`backend_commands`** — (OPCIONAL) Comandos de migración personalizados por backend. Si no se especifica, usa `migrate --force`.
   ```php
   'backend_commands' => [
       'moonslayers/vdin' => 'migrate:fresh --seed',
   ],
   ```
