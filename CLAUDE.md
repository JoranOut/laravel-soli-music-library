# Laravel Soli Music Library

## Deployment

- **Always ask before deploying.** Never trigger the deploy workflow without explicit user confirmation.

The deploy script is the one from `laravel-soli-administration` with `muzitheek.soli.nl` filled in; that repo's `CLAUDE.md` explains it under "Deploy". Keep the two in step. `scripts/deploy/test-deploy-rollback.sh` is identical in both repos and runs first in the tests workflow. In short: back up the database, migrate, swap `staging` to `current`, warm caches, health-check, and roll back the code (never the database) on any failure after the swap. The deploy refuses to start while `CACHE_STORE=file` is set in the shared `.env`, or while `.deploy-state` says an earlier deploy died after the swap; inspect the server, then delete that file.

## PHP version

`.php-version` holds the PHP patch production must run at least. This app shares its Hetzner server, and that server's PHP, with `laravel-soli-administration`, so keep both pins equal; the setup is identical and documented in that repo's `CLAUDE.md` under "PHP version". In short: CI reads the pin, `scripts/check-php-version.sh` keeps Sail, `config.platform.php` and the workflows in line with it, Renovate bumps the patch, and the deploy refuses a server running an older PHP.
