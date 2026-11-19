# AGENTS.md

Laravel 13 / PHP ^8.3. Webhook-driven deploy server (pulls repos, runs `deploy-backend.sh`, rsyncs frontends to `/var/www`).

## Commands

```bash
composer dev            # serve + queue:listen + pail + vite (concurrently, --kill-others)
composer test           # artisan config:clear + artisan test
php artisan test --filter=BackendServiceTest   # single suite; sqlite :memory:, no external DB
./vendor/bin/pint --test   # CI lint gate; `./vendor/bin/pint` to fix
cp .env.testing .env && php artisan key:generate && php artisan migrate --force  # CI setup order (.github/workflows/test.yml)
```

## Architecture (webhook → service → script)

- `routes/api.php`: `POST /api/webhook/submodule`, `POST /api/webhook/deploy`, `POST /api/webhook/{repo}` (all `verify.webhook.signature`); `GET /api/status` is public.
- `app/Http/Controllers/WebhookController.php` → `app/Services/BackendService.php` + `app/Services/GitService.php` → `scripts/deploy-backend.sh`.
- Source of truth is `config/sei.php`, not `.env`: `backends` (`org/repo`), `backend_paths` (monorepo `org/repo => subdir`, e.g. `moonslayers/vdin => vdin/vdin-api`), `backend_commands` (default `migrate --force`), `frontends` (rsynced from `../sei-compilados/<name>` to `/var/www/<name>`).
- Sibling-dir layout on server: backends live next to sei-deploy as `../<basename>` (monorepo: repo `../vdin`, backend `../vdin/vdin-api`); compiled frontends in `../sei-compilados`. `updateBackend($repo)` matches by suffix `/<repo>` against `sei.backends` and 400s if absent or dir missing.
- `scripts/deploy-backend.sh <backend_path> <github_user> <github_token> <repo_full> [repo_path] [migrate_cmd]`: requires `.env` in backend dir; pipeline `git pull main → composer install → cache clear/regen → migrate ($6, default `migrate --force`) → queue:restart + sleep 10`.
- New backend = add to `config/sei.php` (+ `backend_paths`/`backend_commands` if needed) and commit, clone sibling repo, set that backend's `.env`. No controller change.
- Deploy detail: see `.opencode/skills/despliegue-backends/SKILL.md` + `references/`.

## Gotchas

- `composer install` in `deploy-backend.sh` intentionally omits `--no-dev` (`--no-interaction --prefer-dist --no-progress --optimize-autoloader` only): `vdin` seeds call `fake()`, needing `fakerphp/faker` from require-dev; `--no-dev` breaks `migrate:fresh --seed` with truncated 500. Diagnose via `dmesg | grep -i oom`, `ls -la bootstrap/cache/` (owner `www-data`), `tail storage/logs/laravel.log`.
- Shell escaping: `migrate` commands contain spaces (`migrate:fresh --seed`); always pass via `escapeshellarg()` into `exec()`/`deploy-backend.sh $6`. Never interpolate new `exec("cd ...")` args unescaped (`BackendService`, `GitService` already do this unsafely elsewhere — don't copy).
- Webhook auth: raw body signed RSA-2048/SHA256 + base64, header `X-Webhook-Signature` (or `signature` body field); JSON must include fresh `timestamp` or 401. In tests sign with `Tests\Helpers\WebhookHelper::createSignedPayload()` (`tests/keys/private_test.pem`, `json_encode(..., JSON_UNESCAPED_SLASHES)`); `PUBLIC_KEY` (base64) comes from `.env.testing`.
- `BackendService::getMigrateCommand()` is protected — tests use reflection + `Config::set('sei.backend_commands', [...])` (`tests/Unit/BackendServiceTest.php`).

## Deployment Constraints

### Server prerequisites

- PHP ^8.3, Laravel 13, Composer, git, rsync; sibling-dir layout `../<basename>` for backends and `../sei-compilados` for frontends; backend dirs must contain `.env`.
- Webhook RSA-2048/SHA256 public key configured via `PUBLIC_KEY` (base64) in `.env`.

### Environment variables

- sei-deploy `.env` holds `PUBLIC_KEY` (base64) for webhook verification; each backend sibling repo holds its own `.env` (DB, app keys) required by `deploy-backend.sh`.
- `.env.testing` used in CI; never commit secrets.

### Shell command safety

- `migrate` commands contain spaces (`migrate:fresh --seed`); always pass via `escapeshellarg()` into `exec()`/`deploy-backend.sh $6`. Never interpolate new `exec("cd ...")` args unescaped.

### Minimal repository map

- `config/sei.php` — source of truth for backends/frontends/paths/commands.
- `scripts/deploy-backend.sh` — deploy pipeline.
- `app/Services/BackendService.php`, `app/Services/GitService.php` — webhook handlers.
- `docs/configuracion-backends.md` — backend configuration guide.
- Composer install intentionally omits `--no-dev` so `fakerphp/faker` (require-dev) is available for `migrate:fresh --seed`.
