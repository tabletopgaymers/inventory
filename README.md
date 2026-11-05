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

Private local development may use `APP_ENV=local` with `APP_DEBUG=true` for
detailed errors. The startup check requires debug mode off in all other
environments. Keep debug pages and their configuration/request details private.

Migrations create an active `Central` storage location marked `is_central` when
the location catalog is empty. Existing location catalogs are left unchanged.
This is installation data, independent of optional catalog seeding; no stock is
created.

## Development deployment

### Frontend assets

From the application root, run `npm ci` to install locked frontend dependencies.
Prefer Herd-managed Vite for live CSS/JS development, without keeping a terminal
open. From this application directory, run `herd processes:start vite` and use
`herd processes` / `herd processes:output vite` to inspect it. Stop it with
`herd processes:stop vite`. Herd already serves PHP; do not start a duplicate
Artisan web server/full dev stack. If Vite is part of an existing dev stack,
stop that stack before starting standalone Vite.

Manual `npm run dev` is an alternative that requires its terminal to remain open;
use one method at a time. After selecting an updated Node version, verify
`node --version` and restart Vite. If an interrupted process leaves `public/hot`,
remove the stale marker only after confirming Vite is stopped. Use
`npm run build` to generate hashed assets and the manifest in ignored
`public/build/`; generated assets are built during deployment, not committed.

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

Standalone Relocation print and Inventory count worksheets use x-print.layout/header
with print.css/print.js. Count worksheets add inventory-worksheet.css, which imports
inventory-print.css. Neither standalone view loads app CSS/JS. Event reports use
their explicit event-report.css entry; events.js imports shared print.js and app.js
for the normal application page. Keep print geometry isolated.

Purchase and Relocation use singular `/purchase` and `/relocation` routes. The
old plural routes/controllers/views and their exclusive public progress, invoice
and print assets have been removed; retired URLs reach the authenticated 404.
Shared Purchase services and request/fulfillment tables still support current
workflows. Events has its own resources/js/events.js entry, replacing requests.js.
Do not restore compatibility assets or old-record-only handlers.

Remaining historical public sources are baseline.css and design/phase-a
wrapper/evaluation assets. The nested evaluation package is not the application
build; its directory also contains the ACTIVE app-input.css source, which must
remain. Font Awesome Free is locally built in its demo with its license retained;
it is not loaded site-wide.

Deployment must run `npm ci --include=dev` followed by
`npm run build` in the Forge release directory before activating the release.
Every Forge site needs these steps in its own script; remote development's script
does not update staging automatically. A deployment can finish successfully yet
serve a missing-manifest 500 if the asset build was omitted. The server needs
compatible Node/npm (local Node 24); no Vite dev server runs on
the deployed site. Never deploy public/hot. Development mode uses the same entry
points; no manual hashed-filename edits are needed.

### Existing server process

The development site is `https://dev-inventory.tabletopgaymers.org` on Forge,
using the branch selected in Forge. Forge creates a release, installs locked
Composer packages without development dependencies, installs/builds npm assets
before activation, runs `php artisan migrate --force`,
caches configuration and views, activates the release and restarts queues.
Deployment does not run custom scripts, CI, tests, seeders or resets.

On meeple01, the `node` executable is provided by NodeSource's APT `nsolid`
package. Inspect the package owner and candidate before updating; do not assume
the `nodejs` package is installed or replace the runtime incidentally. Node patch
updates are separate from application dependency updates. Verify the version as
the site deployment user and check a normal authorized asset build afterward.

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
deployed page requests, and no generated revision file is required.

When rebuilding configuration outside deployment, preserve existing injected
release metadata; it may not be stored in the private `.env`.

With `APP_ENV=local`, the footer instead reads the current checkout's Git branch
and commit on each page render, adding `modified` for staged, unstaged or
untracked changes. Ignored files such as .env, build output and private inputs do
not mark it modified. Refresh after a commit or branch switch to update the label.
A detached checkout displays `Detached HEAD`. If Git is unavailable, slow or the
checkout has no .git, recorded metadata is used without breaking the page.

### Redis connections

Redis is prepared for future selective caching. Existing defaults remain file
cache, database sessions and synchronous queues; configuring a connection does
not enable catalog caching. Database sessions are currently required by startup
validation. Local Redis is managed by Herd on `127.0.0.1:6379`, without a password,
with connection prefix `tg:inventory_local:`. Keep it loopback-only.

Remote Inventory environments have separate ACL users/passwords and key prefixes.
Store credentials only in private environment/configuration and the password
manager. The production Redis account is prepared, but the production application
has not been configured to use it. Shared/default credentials are not Inventory
application credentials.

meeple01 uses a persistent Redis ACL file. Forge's Set password action alone does
not synchronize that ACL's default-user password; follow the server's Forge Notes
and maintained Redis operating reference when rotating it. Preserve named-user
restrictions and verify fresh connections. Database-wide `FLUSHDB`/`FLUSHALL`
operations are denied to application users; future cache invalidation must respect
the application's key namespace. Do not broaden permissions just to make
`cache:clear` succeed. Do not run dated one-time setup scripts blindly.

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
