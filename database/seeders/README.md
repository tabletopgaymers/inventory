# Catalog seeding

## Catalog-only seed — October 20, 2026

`CatalogSeeder` reads `database/seeders/fixtures/catalog.tab`, a UTF-8 tab-delimited copy of Jeff's approved catalog file. This is the active catalog-only seeder. The default `DatabaseSeeder` remains empty. Deployments do not run this command automatically.

```powershell
php artisan catalog:seed
# An explicitly selected UTF-8 file can be used later:
php artisan catalog:seed --file="C:/path/to/catalog.tab"
```

Standard Laravel entry point is also supported: `php artisan db:seed --class=CatalogSeeder`.

Required columns: Category, Collection, Item, SkuPre, SkuPost, Sku. Each row describes an Item under its Category/Collection. Sku must equal prefix + dash + suffix (e.g. BH-TGBH). Prefixes may be shared by collections; full SKUs must be unique. Quoted fields follow standard CSV quoting with a tab delimiter. Input must be UTF-8; Unicode fractions and embedded quotation marks are preserved.

Optional columns for later enrichment: CategoryDescription, CollectionDescription, ItemDescription, Notes, Variety. Nonblank supplied values update those fields; omitted/blank values preserve existing metadata. Repeated category/collection descriptions within the file must agree. To add other attributes later, extend `CatalogSeeder` explicitly rather than silently ignoring unknown columns.

Reruns add missing records and reuse matching identities; no deletions, renames, reparenting, prefix changes or stock/cost overwrites. Existing statuses and unspecified metadata are preserved. New Items use zero/unknown unit cost. Identity conflicts, malformed rows and unsupported headers abort the transaction. Categories/Collections populate the name registry; all records receive active metadata on creation. Uses a single transaction coordinated with catalog saves through the existing bootstrap lock. Uses the application's configured MySQL/MariaDB connection without environment or database-host restrictions, including when called through db:seed. Run migrations/setup first; publishing the command does not execute it. No actor account required for this explicit CLI catalog setup.

Creates no Locations, balances, quantity history, requests, suppliers, programs, purposes, users or roles. Fresh fixture contains 13 Categories, 36 Collections and 206 Items. The source in the documentation workspace is `_data/seeder/seeder-data.tab`; after editing it, copy/export the approved UTF-8 data into the application fixture or select the source explicitly with --file. The application fixture is the default executable input, not an automatically synchronized file.


## Retired sample-data seeder — October 22, 2026

At Jeff's direction, the former development sample fixture/seeder and its `samples:seed` / `samples:reset` commands were removed, including the reset helper that depended on that fixture. Private backups preserve the removed implementation and its former instructions. Existing database data was not changed. Use `catalog:seed` for the current approved catalog.
