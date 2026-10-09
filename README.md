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

## Development deployment

The development site is `https://dev-inventory.tabletopgaymers.org` on Forge,
using the `main` branch. Forge creates a release, installs locked Composer
packages without development dependencies, runs `php artisan migrate --force`,
caches configuration and views, activates the release and restarts queues.
Deployment does not run custom scripts, CI, tests, seeders or resets.

Keep the existing shared environment and storage configuration. Preserve business
data, immutable history and runtime authorization when deploying. Sample data
installation or reset is a separate explicitly authorized operation; see
[development samples](database/seeders/README.md).

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