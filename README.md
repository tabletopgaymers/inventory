# Inventory

Laravel inventory application for Tabletop Gaymers, using PHP 8.5 and MariaDB.
Microsoft sign-in and application roles control access to catalog, inventory,
purchase requests, relocations, fulfillment, events and reports.

## Local installation

Install the locked dependencies with `composer install`. Configure the private
`.env` file for the local database and Microsoft authentication, generate the
application key with `php artisan key:generate`, and install the schema with
`php artisan migrate`. Serve only `public/` through the configured Herd site at
`https://tg-inventory-app.test`. Never commit environment files or credentials.

Migrations create an active `Central` storage location marked `is_central` when
the location catalog is empty. Existing location catalogs are left unchanged.
This is installation data, independent of optional catalog seeding; no stock is
created.

## Development deployment

### Frontend assets

From the application root, run `npm ci` to install locked frontend dependencies.
Use `npm run dev` alongside Herd for live CSS/JS development, leaving that terminal
running; Ctrl+C stops it. If an interrupted process leaves `public/hot`, remove
that stale marker before using built assets. Use `npm run build` to generate
hashed assets and the manifest in ignored `public/build/`.

The common shell loads `resources/css/app.css` through Laravel Vite. This imports
the existing Tailwind/daisyUI theme and app rules from
`resources/design/phase-a/app-input.css`; edit source, not generated build output.
The obsolete public/app.css and unused resources/js/app.js starter were removed.
Singular Relocation and Purchase request-tab scripts use Vite entries
at resources/js/relocation.js and resources/js/purchase-request.js, importing
shared workflow tab mechanics. Purchase invoice code also uses the Vite entry
resources/js/purchase-work-invoice.js; all three use shared quantity navigation.
Domain CSS lives in resources/css/purchase.css and resources/css/relocation.css;
each domain template includes its CSS and JS entries once. Common theme CSS loads
first in the shell; domain styles retain their existing precedence. Shared
workflow modules are imported by those JS entries, not mounted a second time by
a global auto-loader.

Other public assets intentionally remain: app-shell.js, Save progress shortcuts,
inventory-search controls and unrelated page assets. Vite domain entries import
the existing inventory-search client source while their Blade component sets
script=false; classic callers still load the public script once. Do not include
both versions on one page. Vite also builds style-guide demos and locally hosted
Font Awesome Free icons; icons are not yet loaded site-wide.
The nested evaluation package is not the application build.

Standalone documents use x-print.layout/header with resources/css/print.css and
resources/js/print.js as independent Vite entries. They do not load the normal
application stylesheet. See /style-guide/workflow and its printable specimen.

Deployment must run `npm ci --include=dev` followed by
`npm run build` in the Forge release directory before activating the release.
The server needs compatible Node/npm (local Node 24); no Vite dev server runs on
the deployed site. Never deploy public/hot. Development mode uses the same entry
points; no manual hashed-filename edits are needed.

### Existing server process

The development site is `https://dev-inventory.tabletopgaymers.org` on Forge,
using the branch selected in Forge. Forge creates a release, installs locked
Composer packages without development dependencies, installs/builds npm assets
before activation, runs `php artisan migrate --force`,
caches configuration and views, activates the release and restarts queues.
Deployment does not run custom scripts, CI, tests, seeders or resets.

Keep the existing shared environment and storage configuration. Preserve business
data, immutable history and runtime authorization when deploying. Sample data
installation or reset is a separate explicitly authorized operation; see
[catalog seeding](database/seeders/README.md).

The optional `APP_REVISION` environment value can label the development footer.
No generated revision marker is required.

## Additional installations

Each installation uses its own APP_URL, database credentials and APP_KEY.
Use an HTTPS origin for APP_URL. Microsoft redirect settings must match that
origin plus /auth/microsoft/callback and /signed-out; register both URLs in
Entra. The bootstrap tenant must match the sign-in tenant, and the bootstrap
object identifies the designated initial administrator.

Environment names, hostnames, database names/users and exact server versions
are not restricted to the original development installation. Composer still
defines supported PHP/framework dependencies. Set APP_DEBUG=false and use
database sessions with SESSION_ENCRYPT=true, SESSION_SECURE_COOKIE=true,
SESSION_HTTP_ONLY=true and SESSION_SAME_SITE=lax. Deploy normally to refresh
configuration and view caches. No custom baseline command is required.
