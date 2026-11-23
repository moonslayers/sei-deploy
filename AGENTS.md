# AGENTS.md

Laravel 13 / PHP ^8.3. Webhook-driven deploy server (pulls repos, runs `deploy-backend.sh`, rsyncs frontends to `/var/www`).

## Commands

```bash
composer dev            # serve + queue:listen + pail + vite (concurrently, --kill-others)
composer test           # artisan config:clear + artisan test
php artisan test --filter=BackendServiceTest   # single suite; sqlite :memory:, no external DB
php artisan filament:assets                 # required after composer install/update and every deploy (Filament UI assets)
./vendor/bin/pint --test   # CI lint gate; `./vendor/bin/pint` to fix
cp .env.testing .env && php artisan key:generate && php artisan migrate --force  # CI setup order (.github/workflows/test.yml)
```

## Architecture (webhook → service → script)

- `routes/api.php`: `POST /api/webhook/submodule`, `POST /api/webhook/deploy`, `POST /api/webhook/{repo}` (all `verify.webhook.signature`); `GET /api/status` is public.
- `app/Http/Controllers/WebhookController.php` → `app/Services/BackendService.php` + `app/Services/GitService.php` → `scripts/deploy-backend.sh`.
- Source of truth for backends/frontends is now the DB tables `backends`/`frontends` (Filament panel at `/admin`). `config/sei.php` remains only as seeder input (`backends`, `backend_paths`, `backend_commands`, `frontends`). `Backend.script` (null = shared template `scripts/deploy-backend.sh`); per-repo scripts supported with version history (`deploy_script_versions`). Seeder in last migration (`2026_01_21_000002_seed_backend_config.php`) is idempotent and never overwrites `script`.
- Sibling-dir layout on server: backends live next to sei-deploy as `../<basename>` (monorepo: repo `../vdin`, backend `../vdin/vdin-api`); compiled frontends in `../sei-compilados`. `updateBackend($repo)` matches by suffix `/<repo>` against `sei.backends` and 400s if absent or dir missing.
- `scripts/deploy-backend.sh <backend_path> <github_user> <github_token> <repo_full> [repo_path] [migrate_cmd]`: requires `.env` in backend dir; pipeline `git pull main → composer install → cache clear/regen → migrate ($6, default `migrate --force`) → queue:restart + sleep 10`.
- New backend = add to `config/sei.php` (+ `backend_paths`/`backend_commands` if needed) and commit, clone sibling repo, set that backend's `.env`. No controller change.
- Deploy detail: see `.opencode/skills/despliegue-backends/SKILL.md` + `references/`.
- Self-deploy (`POST /api/webhook/deploy` → `GitService`): pull main → composer install → `php artisan filament:assets` → `npm ci && npm run build` → migrate → pull compiled + deploy frontends. Filament/Vite assets run only in self-deploy, not in `deploy-backend.sh` (generic backends).

## Gotchas

- `composer.json` pins `config.platform.php = 8.3.0`: regenerate `composer.lock` with it in place. A lock generated on newer PHP resolves Symfony 8.x (requires PHP >= 8.4.1) and breaks `composer install` on CI/server (PHP 8.3). Detail: `.opencode/skills/despliegue-backends/references/self-deploy-composer.md`.
- Laravel 13 skeleton's `config/database.php` uses `Pdo\Mysql` (class exists only in PHP >= 8.4); production runs PHP 8.3 → fatal `Class "Pdo\Mysql" not found` (500 on every request). Keep `PDO::MYSQL_ATTR_SSL_CA`; check for PHP 8.4+ constructs when syncing skeleton configs.
- `composer install` in `deploy-backend.sh` intentionally omits `--no-dev` (`--no-interaction --prefer-dist --no-progress --optimize-autoloader` only): `vdin` seeds call `fake()`, needing `fakerphp/faker` from require-dev; `--no-dev` breaks `migrate:fresh --seed` with truncated 500. Diagnose via `dmesg | grep -i oom`, `ls -la bootstrap/cache/` (owner `www-data`), `tail storage/logs/laravel.log`.
- Shell escaping: `migrate` commands contain spaces (`migrate:fresh --seed`); always pass via `escapeshellarg()` into `exec()`/`deploy-backend.sh $6`. Never interpolate new `exec("cd ...")` args unescaped (`BackendService`, `GitService` already do this unsafely elsewhere — don't copy).
- Webhook auth: raw body signed RSA-2048/SHA256 + base64, header `X-Webhook-Signature` (or `signature` body field); JSON must include fresh `timestamp` or 401. In tests sign with `Tests\Helpers\WebhookHelper::createSignedPayload()` (`tests/keys/private_test.pem`, `json_encode(..., JSON_UNESCAPED_SLASHES)`); `PUBLIC_KEY` (base64) comes from `.env.testing`.
- `BackendService::getMigrateCommand()` is protected — tests use reflection + `Config::set('sei.backend_commands', [...])` (`tests/Unit/BackendServiceTest.php`).
- Terminal: tmux binary required; sessions `sei-<uuid>` detached; poller `PollTerminalOutput` reanudable vía `output_offset`; límite 3 sesiones por usuario; reaper `terminal:reap` (TTL 30 min, huérfanas `sei-*`). Model mutators validate `Backend.path` (relativa, sin `..`, charset) and `Frontend.name` (charset). `GitService` escapa todos los args shell vía helper `escapeshellarg`.

## Deployment Constraints

### Server prerequisites

- PHP ^8.3, Laravel 13, Composer, git, rsync, node/npm, tmux; sibling-dir layout `../<basename>` for backends and `../sei-compilados` for frontends; backend dirs must contain `.env`.
- Webhook RSA-2048/SHA256 public key configured via `PUBLIC_KEY` (base64) in `.env`.
- Reverb required for terminal panel: `php artisan reverb:start`; queue worker (database driver); env `REVERB_*`, `BROADCAST_CONNECTION=reverb`, `VITE_REVERB_*`.

### Environment variables

- sei-deploy `.env` holds `PUBLIC_KEY` (base64) for webhook verification; each backend sibling repo holds its own `.env` (DB, app keys) required by `deploy-backend.sh`.
- `.env.testing` used in CI; never commit secrets.

### Subpath serving (prod corre bajo /sei/)
- El proxy sei-apps mapea `/sei/*` → raíz del backend (`/`) y `/sei/api/*` → `/api/*`. Nunca agregues `location` a nivel raíz (`/livewire/`, `/app/`) para un solo backend: secuestra a los proyectos hermanos.
- Nunca hardcodees paths absolutos (`/admin`, `/terminal/...`, `/livewire/...`) en Blade/JS: usa `route()`/`url()`/`asset()`, que respetan el prefijo vía `URL::useOrigin(config('app.url'))` en `AppServiceProvider`.
- Livewire ignora `APP_URL` para su JS: publica sus assets (`php artisan livewire:publish --assets`, paso del self-deploy) y fija `app.asset_url` para que hereden el prefijo. Verifica con hard refresh + pestaña Network.
- El path del panel Filament se queda en `admin` (nginx ya quita `/sei`); no lo renombres con prefijo.
- `APP_URL` y `ASSET_URL` deben incluir la subruta (`https://host/sei`) + `php artisan config:clear` tras cambiarlas. `APP_DEBUG=false` en prod.

### Shell command safety

- `migrate` commands contain spaces (`migrate:fresh --seed`); always pass via `escapeshellarg()` into `exec()`/`deploy-backend.sh $6`. Never interpolate new `exec("cd ...")` args unescaped.

### Minimal repository map

- `config/sei.php` — source of truth for backends/frontends/paths/commands.
- `scripts/deploy-backend.sh` — deploy pipeline.
- `app/Services/BackendService.php`, `app/Services/GitService.php` — webhook handlers.
- `docs/configuracion-backends.md` — backend configuration guide.
- Composer install intentionally omits `--no-dev` so `fakerphp/faker` (require-dev) is available for `migrate:fresh --seed`.
