# Seeders and fixture data

## Shared structure and format

Jeff adopted this standard on November 6, 2026:

| Directory | Purpose | Git policy |
| --- | --- | --- |
| `database/seeders/` | Seeder PHP classes and this operating reference | Tracked |
| `database/fixtures/` | Real input files for the selected environment | Contents ignored except the tracked `.gitignore` |
| `database/fixtures-example/` | Fictional CSV templates illustrating accepted columns | Tracked |

All fixture inputs use **comma-delimited CSV encoded as UTF-8**. A UTF-8 BOM is accepted for Excel exports. Preserve identifiers, postal codes and quantities as source text; CSV quoting handles commas, quotes and multiline fields. Do not supply tab-delimited input with a .csv extension. Catalog input previously used catalog.tab; it has been converted to catalog.csv and the reader now uses commas.

A fresh checkout contains the fixture directory's `.gitignore` and examples, but **no real data**. Copy approved inputs into `database/fixtures/` separately. Never force-add real fixture data to Git, and never silently substitute examples when real input is missing. Examples are fictional documentation, not a deployment dataset.

The default `DatabaseSeeder` remains empty. Imports are run explicitly and are not part of deployment or ordinary migrations. Each implemented command accepts `--file` to select an alternative private input. Publishing seeder code does not execute it. Run migrations before importing and confirm the target environment/database before each authorized execution.

## Server storage

Release-based deployments replace the application directory. Keep real server inputs in a private site-shared directory outside releases and outside the public web root, for example `<site>/shared/fixtures/`. Upload files manually using the site's existing secure access, restrict access to the site's deployment/runtime account, and retain backups as appropriate. Then pass the absolute path with `--file` from the active release. Do not put private uploads into a generated asset, public storage link, tracked fixture or deployment log.

For example, after a separately authorized upload and import:

```bash
php artisan catalog:seed --file="/absolute/private/site/shared/fixtures/catalog.csv"
php artisan inventory:seed --file="/absolute/private/site/shared/fixtures/starting-stock.csv" --actor=123
```

These are instructions, not automatic deploy steps. No server directories, uploads or imports were performed merely by adopting this structure. Historical Git revisions may still contain previously tracked catalog/stock data; this change removes those files from the current tree, not from repository history.

## Catalog

Default file: `database/fixtures/catalog.csv`. Example: `database/fixtures-example/catalog.csv`.

```powershell
php artisan catalog:seed
php artisan catalog:seed --file="C:/private/catalog.csv"
```

Standard Laravel entry point: `php artisan db:seed --class=CatalogSeeder`.

Required headers: Category, Collection, Item, SkuPre, SkuPost, Sku. Optional headers: CategoryDescription, CollectionDescription, ItemDescription, Notes, Variety. SKU must be prefix + dash + suffix. Full SKUs must be unique; collections may share prefixes. Nonblank repeated category/collection descriptions must agree. Unknown headers, malformed rows, duplicate/conflicting identities and invalid values reject the import.

Reruns add missing records and reuse matching identities. Nonblank supplied optional metadata updates its corresponding fields; blank/omitted optional values preserve existing values. Existing statuses and unit costs are retained. There are no deletions, renames, reparenting, stock changes or user creation. New catalog items start with zero/unknown unit cost. One transaction coordinates with ordinary catalog saves using the existing bootstrap lock. The configured MySQL/MariaDB connection is used; importing into another environment requires its own authorization.

The fictional stock example references the SKUs in the fictional catalog example. Real stock inputs must instead reference their matching real catalog. Do not mix example stock with the application's real catalog by accident.

## Starting stock

Default file: `database/fixtures/starting-stock.csv`. Example: `database/fixtures-example/starting-stock.csv`.

```powershell
php artisan inventory:seed
php artisan inventory:seed --file="C:/private/starting-stock.csv" --actor=123
```

Standard Laravel entry point: `php artisan db:seed --class=StartingStockSeeder`. The actor must be an existing enabled Admin/Manager; when omitted, the initial administrator is used. Catalog items and named storage locations must already exist. No users, roles, locations or catalog items are created.

CSV requires SKU and at least one existing location-name column. Headers can be reordered and must be nonblank, unpadded and unique. Every SKU resolves uniquely, including rows whose quantities are all blank. Duplicate SKUs, unknown locations, invalid quantities or malformed rows reject the import. Signed whole quantities use the existing bounds; quote thousands separators, such as "3,000". Fractions and scientific notation are rejected.

**Supplied quantities SET absolute counts.** Blank cells leave existing counts unchanged; explicit zero sets zero. Omitted locations/items are preserved. First stock records Starting inventory; later changes use audited adjustments through StockPosting. Identical reruns create no duplicate history, but if live counts changed since the previous import, rerunning resets supplied numeric cells to the CSV values. All rows post within one transaction; failure commits no partial stock changes. Existing catalog metadata and unit costs remain unchanged.

## Officers/users — template only

Real roster: `database/fixtures/users.csv`. Fictional template: `database/fixtures-example/users.csv`. The officer seeder/command is **not implemented yet**; no runnable user-import command is promised by this document.

The id column is the Microsoft Object ID, not the application's numeric user ID. The configured tenant supplies the matching tenant identity. Canonical roles are comma-separated lowercase basic, manager, procurement and admin. The current source also has username and individual role helper columns; these are not application profile columns. Jeff explicitly confirmed Admin for himself, Justin Nauman and TG Administrator despite their original roles cells omitting admin. Resolve that input inconsistency in the approved import behavior before execution; do not infer roles from Microsoft administrative privileges.

Names, Microsoft account email, personal email, phone and named IANA timezone can populate existing profile fields. There is no separate TG email or self-confirmation field. Assemble mailing_address from nonblank lines: displayName; address; then city, state and zipCode joined by spaces. If all address components are absent, leave mailing_address blank rather than storing the name alone. Preserve postal-code leading zeros. Missing optional fields remain blank. Existing profile edits/unrelated roles must be preserved according to the separately approved future user-import policy.

## Messages, safety and reruns

Implemented catalog/stock commands print start and success/failure messages. Success is reported after commit, with counts, including unchanged reruns. Failures return nonzero; expected validation/authorization problems are explained, while unexpected private details go to application logs. `db:seed --class=...` uses Laravel's normal exception output. Missing files produce actionable CSV/path guidance. Do not print file contents or personal details in command summaries.

Forge's missing `.forge/provision-....output` error concerns its output capture, not CSV validation; inspect execution before rerunning. Especially for stock, an unexamined rerun may reapply absolute counts.
