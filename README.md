# inventory
Web application for basic management of inventory items.

## Local development baseline

The original local baseline is authorized under planning decision D-164. Application
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

No sign-in, Microsoft credentials, inventory schema/data or imports are included.
Hosted development support is described below; no deployment has been performed by the implementation agent. Independent review remains required; commit/push do not constitute review or acceptance. Requirements and
setup evidence remain authoritative in the planning workspace:
`C:\Users\delug\Documents\ChatGPT\TG Dev Inventory\work\tasks\phase-02-local-baseline.md`.

## Hosted development: Forge

Explicit user brief, October 5, 2026: support the existing Forge hosted development
site, without executing live changes. `APP_ENV=development` is the only hosted
baseline designation; `production`, `staging` and other environments are rejected.
Never disguise the host as local. Both local/testing names and TCP restrictions
remain intact. The page identifies HOSTED DEVELOPMENT BASELINE for development.

Target: Tech for Service / meeple01 (server 794348), site 3411668,
isolated user inventorydev, custom repository git@github.com:tabletopgaymers/inventory.git,
branch main, PHP 8.5, HTTPS https://dev-inventory.tabletopgaymers.org, web directory
/public. These are user-reported administration facts, not agent server verification.
MariaDB must be 10.11.x (10.11.14 was previously reported). No Node build is needed.

### 1. Inspect before saving or deploying

In Forge, open only this site's Environment and deployment script. Inspect privately;
do not send the environment contents or secret-bearing deployment hook anywhere.
Confirm the deployed commit in Forge and the active release (`git rev-parse HEAD`
from the application release directory is safe). Existing main 8a95f53 does not
include this hosted update. The implementation changes need separate commit/push
and deployment authorization before they become available on the server.

### 2. Save the shared environment privately

Use the site's Forge environment editor. Preserve the existing APP_KEY and database
password; do not replace the whole file, regenerate a working key or reuse local
credentials. If the key is missing, arrange private generation once, for this site's
shared environment, before checks; never print its value. These settings are nonsecret:

```dotenv
APP_NAME="TG Inventory"
APP_ENV=development
APP_DEBUG=false
APP_URL=https://dev-inventory.tabletopgaymers.org
APP_MAINTENANCE_DRIVER=file
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tg_inventory_dev
DB_USERNAME=tg_inventory_dev
SESSION_DRIVER=database
SESSION_CONNECTION=mariadb
SESSION_TABLE=sessions
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_COOKIE=tg_inventory_dev_session
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=array
LOG_CHANNEL=single
LOG_LEVEL=warning
```

Privately retain/set DB_PASSWORD and APP_KEY. Remove nonempty DB_URL, DB_SOCKET and
DB_PREFIX overrides; the baseline rejects URL/socket/prefix routing. The account
must have rights only on tg_inventory_dev: SELECT/INSERT/UPDATE/DELETE for runtime,
plus CREATE/ALTER/INDEX/DROP for guarded schema preparation. An administrator should
inspect existing grants privately; readiness checks do not prove their complete scope.
No grant changes or account resets are part of agent execution. Do not enable debug.

Forge shares .env automatically and storage is already shared. Edit that shared
configuration through Forge, not a disposable release copy. Keep bootstrap/cache
release-specific. Each new release must build its own configuration cache after
shared .env is linked. Environment changes do not update an existing config cache.
Use `config:clear` on the active release when correcting its environment, then
`config:cache` once correct; both affect that release and require Jeff's action.
Use the site's PHP 8.5 binary (`$FORGE_PHP` in the deploy script), not an assumed
server-default PHP. Never use `--env=development` to mask a wrong effective APP_ENV;
commands should read the shared environment/cache normally.

### 3. Read-only schema inspection, then preparation only if needed

After the updated source is installed, run from the active application release as
inventorydev, using PHP 8.5:

```sh
php artisan baseline:schema
php artisan baseline:check
```

`baseline:schema` first validates effective configuration, connects using the
intended account, checks SELECT 1/current schema/MariaDB version, then inspects table
names, required session column names and the baseline migration record. It does not
write data or reveal credentials/table contents. It reports one of:

- Verified schema/record: skip preparation; `baseline:check` should exit 0.
- Pending baseline migration: only after inspection and authorization, run
  `php artisan baseline:prepare --hosted`, then `baseline:check`.
- Existing schema needs inspection: stop. Preserve it; privately inspect structure
  and migration history with the administrator before deciding any repair. An existing
  sessions table with a missing migration record must not be blindly recreated.

Preparation rechecks schema, refuses unrelated tables/inconsistent baseline state,
and runs only the existing sessions migration; it never resets/drops existing data
or seeds users. No new migration was added. The explicit --hosted option prevents
accidental hosted preparation. Do not use migrate:fresh, migrate:refresh, rollback
or generic migration commands to troubleshoot this installation.

`baseline:check` checks required column presence and migration record, not complete
column types/indexes or every grant. A trusted HTTPS 200 with Database check passed,
the hosted label and current runtime versions also exercises actual session access.
Inspect cookie Secure/HttpOnly/SameSite flags without sharing values. Verify public
CSS loads and requests for /.env, /.git/config, /composer.json and /storage/logs/laravel.log
are denied (403/404). Report only safe status/versions/command exit codes. HTTP 503
alone does not distinguish pre-query configuration rejection from database failure.

### 4. Deploy script: gate a candidate release before activation

Jeff reviews/submits the script. Keep Forge's existing CREATE_RELEASE,
`cd $FORGE_RELEASE_DIRECTORY`, ACTIVATE_RELEASE and RESTART_QUEUES macros intact.
Keep Composer installation using the lockfile (no update), and no npm commands.
Remove/replace automatic generic migration steps for this baseline. After Composer
installation and the shared environment link, but before ACTIVATE_RELEASE, insert:

```sh
"$FORGE_PHP" artisan config:cache || exit 1
"$FORGE_PHP" artisan baseline:schema || exit 1
"$FORGE_PHP" artisan baseline:check || exit 1
"$FORGE_PHP" artisan view:cache || exit 1
```

For the first deployment only, if read-only inspection reports a pending migration
and Jeff authorizes it, place `"$FORGE_PHP" artisan baseline:prepare --hosted || exit 1`
between schema and readiness checks. Remove that temporary line once prepared;
future deployments should verify readiness without schema changes. Schema is shared
across releases, so zero downtime does not roll back database changes or shared .env
changes. A failed readiness gate must exit before activation. Do not run composer
check/PHPUnit on the host: they require the private local/test databases and dev tools.

### 5. Verify main push-to-deploy after readiness

Keep webhook setup pending until the site is ready and Jeff approves a deployment.
Custom Git requires a manually configured deployment hook. Jeff privately pastes the
Forge hook into GitHub Settings > Webhooks; JSON, push event only, SSL verification,
Active, separate Secret blank as described in the administration brief. Never save
or display the URL. GitHub push webhooks cover all branches. The site's configured main branch controls
which source is deployed; do not assume the direct hook filters non-main events.
Forge documents forge_deploy_branch for callers that supply the actual event branch;
a static main parameter cannot establish event filtering. If strict main-only triggers
are needed and the direct hook does not filter payloads, stop and arrange a separately
approved branch-filtering integration before enabling automatic deployment.
Do not duplicate an existing hook; inspect safely without sharing the URL.

After saving, a ping delivery alone does not verify push deployment. At a separately
authorized real main push, check GitHub's recent push delivery response and Forge's
successful deployment entry; compare the exact deployed SHA to GitHub main/active
release. Then repeat HTTPS/database/session checks. A successful webhook HTTP response
only establishes acceptance, not completed deployment. No empty test commit or replay
of a delivery is required; a replay also triggers deployment and needs authorization.

Official references: [Forge deployments](https://laravel.com/forge/docs/sites/deployments)
(shared paths, release macros and deployment hooks) and
[Laravel configuration](https://github.com/laravel/docs/blob/13.x/configuration.md)
(configuration caching). Host configuration, grants, schema, exact deployed revision
and webhook operation remain unverified until Jeff supplies safe results.
