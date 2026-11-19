---
name: despliegue-backends
description: "Use when working on sei-deploy auto-deploy (backends and self): webhook, BackendService, config/sei.php, deploy-backend.sh, adding backends, migration commands, composer install, composer.lock, shell escaping, or truncated deploy 500."
license: MIT
compatibility: See ../../../AGENTS.md (PHP/Laravel versions)
---

## What I do

Router for the sei-deploy backend auto-deploy system. Points to focused reference docs. Transversal rules (PHP/Laravel versions, deployment constraints) live in ../../../AGENTS.md.

## When to use me

Triggers: deploy, despliegue, backends, webhook, deploy-backend.sh, BackendService, config/sei.php, agregar backend, comandos de migración, migrate:fresh, migrate --force, shell escaping, escapeshellarg, composer, composer install, librerías, dependencias, deploy 500, webhook 500, deploy truncado, composer truncated, OOM deploy, fakerphp faker, migrate:fresh --seed vdin, self-deploy, composer.lock, platform.php, updateDeploy.

## Leer cuando

| Necesidad | Archivo |
|-----------|---------|
| Arquitectura 4 etapas + archivos clave | [architecture.md](references/architecture.md) |
| Auto-deploy de sei-deploy, paso composer install, upgrades de dependencias, fallos de lock/CI | [self-deploy-composer.md](references/self-deploy-composer.md) |
| Configuración config/sei.php | [configuration.md](references/configuration.md) |
| Agregar backend nuevo | [adding-backend.md](references/adding-backend.md) |
| Shell escaping / escapeshellarg | [shell-escaping.md](references/shell-escaping.md) |
| Deploy 500 truncado / troubleshooting | [deploy-500-troubleshooting.md](references/deploy-500-troubleshooting.md) |
| Tests BackendService | [testing.md](references/testing.md) |
| Transversal rules (versions, deploy constraints) | [AGENTS.md](../../../AGENTS.md) |
