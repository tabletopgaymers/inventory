# Inventory

A Laravel application for managing a catalog, tracking stock by location, and coordinating relocations, purchases, receiving, and events. It includes inventory searches, stock counts, and reports.

Originally developed by [Tabletop Gaymers](https://tabletopgaymers.org/). You can clone or fork the project and adapt it for your own organization under the [MIT license](LICENSE).

## Local installation

Use PHP 8.5, Composer, MariaDB, and a Node.js/npm version compatible with the locked frontend dependencies.

1. Clone or fork the repository. From the application root, run `composer install`.
2. Copy `.env.example` to `.env`. Set `APP_URL` to your HTTPS origin and enter the connection details for a database created for this installation. Generate an application key with `php artisan key:generate`.
3. Run `php artisan migrate` against that database. A fresh installation creates a default Central storage location without stock.
4. Configure [Microsoft sign-in](#microsoft-sign-in) for your organization and [build the frontend assets](#frontend-assets).
5. Serve the app from `public/` only. If you use Laravel Herd, link the checkout and enable HTTPS for the local site. Verify that the app opens and that `/up` responds.

Use `APP_ENV=local` for local development. Enable `APP_DEBUG=true` only on a private local installation. Keep debug mode off on hosted sites. Store `.env` and credentials outside version control and the public web root.

## Microsoft sign-in

Register a single-tenant web application in your own Microsoft Entra tenant. Use the `MICROSOFT_*` settings in `.env.example` to configure your tenant, app registration, and designated first administrator. The bootstrap tenant must match the sign-in tenant. Use the Microsoft object ID of the intended administrator.

Set the callback and sign-out URLs to your own HTTPS origin followed by `/auth/microsoft/callback` and `/signed-out`. Configure the corresponding URLs in Entra. On a new installation, the designated user signs in and saves their own profile to activate the one-time Admin assignment. Use the app's role controls for subsequent assignments.

## Frontend assets

Run `npm ci` from the application root. Use `npm run dev` for live development or `npm run build` for a standalone asset build. If Herd is already serving PHP, run only Vite for live changes. Use one Vite process per checkout.

Edit the source under `resources/`. Vite writes the hashed assets and manifest to ignored `public/build/`. Keep the local `public/hot` marker out of releases. See the [Style Guide](STYLEGUIDE.md) for reusable components and their source files.

## Hosted deployment

Serve only `public/` over HTTPS. Give each installation its own database, application key, and credentials. Keep the database session driver and secure session cookies. The defaults use file caching and synchronous jobs. Redis settings are optional for deployments that choose to use it.

On a platform such as Laravel Forge, prepare the new release before activating it:

1. Install Composer dependencies from the lockfile with `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
2. Install the frontend build dependencies with `npm ci --include=dev`, then run `npm run build`.
3. Verify the target database and backup before applying approved migrations with `php artisan migrate --force`. Prepare Laravel's configuration and view caches with `php artisan optimize`.
4. Activate the new release, restart any configured queue workers, and verify the app, its assets, and `/up`.

For the release footer, supply `APP_BRANCH` and the full `APP_REVISION` from the intended branch and actual checkout before caching configuration. Keep deployment hooks and credentials private.

### Catalog owner-field cutover

Catalog lifecycle state belongs to categories, collections, items and storage locations. Categories, collections and locations own their descriptions. Locations own their required mailing address and default to `address missing` where no address was supplied. Item Notes stay in `item_metadata`.

For an upgrade that includes the Catalog owner migrations, verify a recoverable database backup, stop web and background writes, and keep the site in maintenance until the new release passes its checks. The usual build-before-activation instructions still apply to dependency and asset preparation. Use the ordinary ordered `php artisan migrate --force` path for the database cutover. The final migration refreshes the owner values from the legacy table, verifies the copy, records aggregate counts of retired values, and removes the legacy table. Both owner migrations must be recorded and `catalog_metadata` must be absent before traffic resumes. Run `php artisan baseline:schema`, `php artisan inventory:check`, and `php artisan catalog:check` against the intended database.

`inventory:prepare` and `catalog:prepare` are read-only compatibility commands. For fresh installs use the full ordered migration chain. If the upgrade stops after any schema change, keep maintenance active and inspect the table shape and migration ledger against the backup. MariaDB may commit the schema change before the migration is recorded. A source rollback alone cannot recover retired Catalog data.

## Contributor checks

Two focused PHP checks cover Style Guide route visibility and account access,
roles, and profile behavior. Run them from the application root in a fresh,
disposable checkout with PHP 8.5, the `pdo_sqlite` and DOM extensions, and
Composer development dependencies installed (`composer install`). Keep
configuration and route caches out of this checkout.

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Feature/StyleGuideAccessTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Feature/UserAccountAccessTest.php
```

The route check requires no database. The account check creates synthetic
records in SQLite memory, mocks Microsoft sign-in, and skips Vite asset loading.
Neither check needs a project `.env`, real Microsoft credentials, MariaDB, or
seed data. Check your deployed environment separately.

## Seed data

The repository includes fictional CSV templates under `database/fixtures-example/`. Keep actual input files private and load them explicitly using the [seeder instructions](database/seeders/README.md). Confirm the intended database before importing. Keep seeding separate from deployment.
