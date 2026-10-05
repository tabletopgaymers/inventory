# inventory
Web application for basic management of inventory items.

## Local development baseline

This local-only baseline is authorized under planning decision D-164. Application
root: `D:\Sites\tg-inventory-app`; web root: its `public` directory. Intended URL:
`https://tg-inventory-app.test`. Herd HTTPS/browser and private-path protection are verified locally.

Requirements: PHP 8.5 with PDO MySQL, Composer and the existing MariaDB 10.11 service
at `127.0.0.1:3306`. Laravel is locked in `composer.lock`. Use a simple Blade page and
static `public/baseline.css`; no Node installation or asset build is required.

## Installation and private local configuration

1. Clone the application repository into an empty application directory, preserving
   existing work. Run `composer install` from that directory to install the lockfile.
2. Privately provision two unused isolated MariaDB databases with utf8mb4 and
   utf8mb4_unicode_ci: `tg_inventory_local` and `tg_inventory_test`. Create matching
   application-specific account names restricted to `127.0.0.1`, each with a separate
   random password. Grant SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX and DROP
   only on its respective schema. In SQL GRANT statements escape schema underscores
   (`tg\_inventory\_local`, `tg\_inventory\_test`) so they cannot act as wildcards.
   Root/admin is only for provisioning. Preserve occupied names and existing data;
   resolve a collision before proceeding rather than adopting/resetting them.
3. Copy `.env.example` to `.env` and `.env.testing.example` to `.env.testing` only if
   the destination does not already exist. Privately set the matching account passwords
   and generate an application key with `php artisan key:generate`, then separately
   `php artisan key:generate --env=testing`. Never paste actual environment files into
   chat or logs. Both files are ignored. APP_DEBUG must remain false.
4. Clear any local configuration cache with `php artisan config:clear`. Prepare each
   isolated database with `php artisan baseline:prepare` and
   `php artisan baseline:prepare --env=testing`. These check the expected environment,
   local address, account/database, PHP/framework/server versions and key before running
   non-destructive migrations. They may be repeated; they never reset existing data.
   Only `migrations` and `sessions` tables are needed; no users or sample identities
   are created. Do not run generic migrations against another database.
5. Keep `D:\Sites` parked in Herd. Its `tg-inventory-app` subdirectory automatically
   creates the site `tg-inventory-app.test`. Select PHP 8.5 for this site in Herd and
   enable HTTPS for exactly `tg-inventory-app.test`. Equivalent commands are
   `herd isolate 8.5 --site=tg-inventory-app` and `herd secure tg-inventory-app`.
   Serve only Laravel's `public` directory; never serve the application root.
   Verify trusted HTTPS, runtime versions and private-path protection.
6. Run `composer check`, or `php scripts/check.php`. The runner verifies installed
   package versions against the lockfile, static assets, local/test database readiness,
   PHP formatting and integration/failure tests. Exit 0 means those checks passed;
   failures return exit 1 with a safe stage diagnostic. Clear cached configuration
   before checking. PHPUnit reads `.env.testing`; it does not substitute SQLite.
   Browser HTTPS and private-path HTTP checks are separate from this command.

The current machine has dedicated accounts/passwords and application keys saved in
ignored environment files. Existing databases and root access were preserved. Local
sessions use the database with encryption and secure cookies; this is a baseline
default and does not settle future authentication/session policy. Tests use only their
own isolated database and remove their temporary session record. No reset command is
required or supplied; never use a destructive reset on the local application database.

## Baseline page and review

The page prominently identifies local development, shows successful database status
and PHP/Laravel/MariaDB versions, and includes no inventory actions or sample identities.
Unavailable service/configuration returns HTTP 503 with a safe message before session
middleware can expose connection errors. The shared stylesheet uses purple headings,
green successful status and amber unavailable status, with desktop and narrow-screen
layout and white print backgrounds; those are the current baseline presentation rules.

No sign-in, Microsoft credentials, inventory schema/data, imports or hosted deployment
are included. Independent review remains required; commit/push do not constitute review or acceptance. Requirements and
setup evidence remain authoritative in the planning workspace:
`C:\Users\delug\Documents\ChatGPT\TG Dev Inventory\work\tasks\phase-02-local-baseline.md`.
