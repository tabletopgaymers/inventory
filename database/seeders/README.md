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

## Starting-stock CSV — October 22, 2026

`StartingStockSeeder` reads `database/seeders/fixtures/starting-stock.csv`, copied unchanged from Jeff's UTF-8 CSV. Run separately after catalog and location setup; never runs on deployment or through the empty default DatabaseSeeder.

```powershell
php artisan inventory:seed
php artisan inventory:seed --file="C:/path/to/starting-stock.csv"
# Optional existing enabled Admin/Manager; otherwise the initial administrator:
php artisan inventory:seed --actor=1
```

Standard entry point `php artisan db:seed --class=StartingStockSeeder` uses the default file and initial administrator. The command uses the application's configured MySQL/MariaDB connection; execution on another installation requires a separate authorization. No new users/roles, items or locations are created.

CSV requires a `SKU` header and one or more existing location-name headers. Columns can be reordered, including SKU. SKUs and location headers must uniquely resolve before any counts are posted. Unknown/ambiguous SKUs or locations, duplicate rows/headers and malformed quantities reject the whole import. UTF-8 BOM is accepted; use CSV quoting around quantities with thousands separators, such as `"3,000"`. Whole signed quantities follow the application's ±1,000,000,000 bounds; fractions/scientific notation and malformed comma grouping are rejected.

Blank/whitespace cells leave existing counts unchanged. Explicit0 sets the count to0. Supplied numbers SET absolute quantities rather than adding them; omitted locations/items remain unchanged. First stock for an item is posted as Starting inventory; later changed values use ordinary audited adjustments. Both go through StockPosting with an existing enabled Admin/Manager, current unit-cost snapshots, normal posting validation and one outer transaction for the entire file. Initial posting follows the existing active-location rules. Existing unit costs, catalog metadata and other business records are untouched.

Rerunning identical counts creates no duplicate history; even all-blank rows have their SKU validated but cause no posting. **If live counts have changed since an import, rerunning resets each supplied numeric cell to the CSV value.** It does not treat a previously used file as permanently completed. A first explicit zero establishes a zero starting balance/history even when no balance exists; already-zero existing stock is unchanged.

Current fixture:206 SKU rows,5 locations (Central/Ames/Indy/Boston/Milwaukee),373 numeric cells and657 blanks, total220003 units.35 item rows contain only blanks. Usage does not require modifying the script when adding existing-location columns or item rows.
