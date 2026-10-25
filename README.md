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

The common shell loads `resources/css/app.css` and `resources/js/app.js`
through Laravel Vite. CSS imports the existing Tailwind/daisyUI theme from
`resources/design/phase-a/app-input.css`, shared workflow/panel rules, search
controls and Save progress shortcut styles. Edit resource sources, never generated
public/build assets or manual filename hashes.

The JS app entry initializes the shell then Save progress shortcuts. Active page
entries that depend on shell APIs import this same cached module explicitly;
independent module tags are not an initialization-order contract. Shared search
has one canonical resources/js/inventory-search-controls.js module and WeakMap
mount registry. Its component can emit the module once, while domain adapters
import it and use script=false. Keep one initialization path per behavior.

Catalog, user actions, Inventory, Events, Purchase, Relocation and active guide
scripts are Vite entries. Catalog form setup explicitly precedes progress baseline
capture through catalog-form.js. Domain styles remain purchase.css/relocation.css;
style-guide.css owns guide-only presentation. Static page style blocks/attributes
belong in shared or scoped resource CSS, including presentation formerly written
by guide JavaScript. Serialized JSON state remains data, not executable styling.

Standalone Relocation print uses x-print.layout/header with print.css/print.js.
Inventory count worksheets use inventory-worksheet.css (worksheet-base then
inventory-print) and independent location-counts.js. Neither imports app CSS/JS.
Event report CSS remains an explicit page entry. Keep print output isolated.

Remaining public sources are intentionally retained legacy/historical assets:
old purchase/relocation progress and invoice scripts, request-workflow.css and
requests-print.css; baseline.css and design/phase-a wrapper/evaluation assets.
Old plural routes/templates are retired references with no functional guarantee;
do not add compatibility copies for their references to moved files. Historical
archive/removal is deferred. The nested evaluation package is not the application
build; its directory also contains the ACTIVE app-input.css source, which must
remain. Font Awesome Free is locally built in its demo with its license retained;
it is not loaded site-wide.

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

The footer displays `APP_BRANCH` and a short `APP_REVISION` (the full commit SHA
is available on hover). Set these from the actual release checkout before
`artisan optimize` caches configuration, without editing the shared .env:

```bash
export APP_BRANCH="$(git branch --show-current)"
export APP_REVISION="$(git rev-parse HEAD)"
```

For a detached checkout, use the configured deployment branch as the branch
label. Installations without release metadata display `Version unavailable`;
existing non-SHA revision labels remain supported. No Git commands run during
page requests, and no generated revision file is required.

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
