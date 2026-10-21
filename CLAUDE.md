# Laravel Soli Music Library

## Deployment

- **Always ask before deploying.** Never trigger the deploy workflow without explicit user confirmation.

## PHP version

`.php-version` holds the PHP patch production must run at least. This app shares its Hetzner server, and that server's PHP, with `laravel-soli-administration`, so keep both pins equal; the setup is identical and documented in that repo's `CLAUDE.md` under "PHP version". In short: CI reads the pin, `scripts/check-php-version.sh` keeps Sail, `config.platform.php` and the workflows in line with it, Renovate bumps the patch, and the deploy refuses a server running an older PHP.
