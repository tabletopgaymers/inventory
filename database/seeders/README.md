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

## Locations

Default file: `database/fixtures/locations.csv`. Fictional template: `database/fixtures-example/locations.csv`.

```powershell
php artisan locations:seed
php artisan locations:seed --file="C:/private/locations.csv"
```

Standard Laravel entry point: `php artisan db:seed --class=LocationSeeder`. This explicit setup import needs no actor account and uses the configured MySQL/MariaDB connection. It is not called by deployment or DatabaseSeeder. The real fixture is supplied privately, including on a server via an absolute shared-storage `--file` path. The example is illustrative, not a default fallback.

Required headers (any order): isCentral, name, description, status, addressTo, address, city, state, zipCode. Names must be unique. isCentral accepts 0/1; status is normalized to lowercase active/inactive/archived. The file must contain exactly one Central row with active status. Names/street/city/status/flag must be nonblank; recipient, description, state and postal code may be blank. Invalid encoding, headers, row shapes or values reject the complete import before any database writes.

The stored mailing address is assembled as these nonblank lines, preserving postal-code leading zeros:

```text
[addressTo]
[address]
[city] [state] [zipCode]
```

Match existing locations by name using database collation and preserve their IDs/names. Create missing base records, name-registry entries and catalog metadata. On reruns, supplied mailing addresses and nonblank descriptions update existing metadata; blank descriptions preserve existing descriptions. **Existing lifecycle statuses are preserved**; status is used when creating metadata. Identical reruns do not update timestamps or duplicate records. Locations omitted from the file remain unchanged. A conflicting Central flag, multiple existing Central locations, inactive existing Central or inconsistent name registry aborts the transaction; this importer never reassigns the protected Central designation.

Creates no inventory, quantities, ledger/history postings, costs, users or roles. The whole import is one transaction serialized with ordinary catalog saves through the existing bootstrap lock. Any write conflict rolls back the entire import. Publishing this implementation does not execute it.

## Starting stock

Default file: `database/fixtures/starting-stock.csv`. Example: `database/fixtures-example/starting-stock.csv`.

```powershell
php artisan inventory:seed
php artisan inventory:seed --file="C:/private/starting-stock.csv" --actor=123
```

Standard Laravel entry point: `php artisan db:seed --class=StartingStockSeeder`. The actor must be an existing enabled Admin/Manager; when omitted, the initial administrator is used. Catalog items and named storage locations must already exist. No users, roles, locations or catalog items are created.

CSV requires SKU and at least one existing location-name column. Headers can be reordered and must be nonblank, unpadded and unique. Every SKU resolves uniquely, including rows whose quantities are all blank. Duplicate SKUs, unknown locations, invalid quantities or malformed rows reject the import. Signed whole quantities use the existing bounds; quote thousands separators, such as "3,000". Fractions and scientific notation are rejected.

**Supplied quantities SET absolute counts.** Blank cells leave existing counts unchanged; explicit zero sets zero. Omitted locations/items are preserved. First stock records Starting inventory; later changes use audited adjustments through StockPosting. Identical reruns create no duplicate history, but if live counts changed since the previous import, rerunning resets supplied numeric cells to the CSV values. All rows post within one transaction; failure commits no partial stock changes. Existing catalog metadata and unit costs remain unchanged.

## Suppliers

The default input is `database/fixtures/suppliers.csv`. The fictional template is `database/fixtures-example/suppliers.csv`.

```powershell
php artisan suppliers:seed
php artisan suppliers:seed --file="C:/private/suppliers.csv"
```

The Laravel entry point is `php artisan db:seed --class=SupplierSeeder`. The command uses the configured MySQL/MariaDB connection and requires no actor account. For server imports, supply the real file through a private shared-storage path with `--file`.

Include these headers in any order: `name`, `status`, `website`, `contactName`, `email`, `phone`, `address`, `city`, `state`, `zip`, `notes`. Name and status require values. Status accepts `active`, `inactive`, `archived` and `unverified`, regardless of case. Names must be unique. Optional websites use HTTP or HTTPS, and supplied email addresses must be valid. The importer joins the street address and the city, state and ZIP line, skipping empty lines and keeping ZIP codes as text.

Match existing suppliers by name under database collation. New suppliers receive the supplied status. Reruns update supplied contact details, mailing addresses and notes while preserving existing statuses and values where the fixture fields are blank. An identical rerun leaves timestamps alone. The command reports created, updated and unchanged counts after one transaction. Validation and name conflicts abort the import. Purchases retain their existing supplier IDs and saved snapshots.

## Purposes

The default input is `database/fixtures/purposes.csv`. The fictional template is `database/fixtures-example/purposes.csv`.

```powershell
php artisan purposes:seed
php artisan purposes:seed --file="C:/private/purposes.csv"
```

The Laravel entry point is `php artisan db:seed --class=PurposeSeeder`. Apply the Purpose details migration first. The input requires `name`, `description` and `examples` headers in any order. Name is required. Description and examples may be blank and support multiline text.

The importer matches by name within Purposes, preserving existing IDs and statuses. It creates new records as Active and updates supplied nonblank descriptions and examples on reruns. Empty fields preserve saved text. Duplicate names or invalid rows reject the import. The command reports created, updated and unchanged counts after one transaction.

## Programs

Default file: `database/fixtures/programs.csv`. Fictional template: `database/fixtures-example/programs.csv`.

```powershell
php artisan programs:seed
php artisan programs:seed --file="C:/private/programs.csv"
```

The Laravel entry point is `php artisan db:seed --class=ProgramSeeder`. Required headers are `name` and `description`. The importer creates missing programs as Active and updates nonblank descriptions on matching names. Blank descriptions preserve saved text. Existing IDs, statuses, item links and programs omitted from the file are preserved. Duplicate names and invalid rows reject the import. The command reports counts after one transaction.

## Officers/users

The default input is `database/fixtures/users.csv`. The fictional template is `database/fixtures-example/users.csv`.

```powershell
php artisan users:seed
php artisan users:seed --file="C:/private/users.csv" --actor=123
```

The Laravel entry point `php artisan db:seed --class=OfficerSeeder` uses the default file and initial administrator. Each import requires an existing enabled Admin to authorize and audit the grants. Complete initial administrator setup first, or supply the numeric user ID of another enabled Admin with `--actor`.

Match by Microsoft provider, configured `MICROSOFT_TENANT_ID`, and the Object ID in `id`. The importer creates and links accounts for new identities. `id` and `roles` are required. Quote a comma-separated role list such as `"basic,manager"`. Allowed values are `basic`, `manager`, `procurement` and `admin`. The same `roles` column controls every row. `username` and the individual role columns are reference data.

Optional columns are `displayName`, `userPrincipalName`, `givenName`, `surname`, `email`, `personalEmail`, `mobileNumber`, `address`, `city`, `state`, `zipCode` and `timeZone`. The importer fills blank profile fields from supplied values. It uses `email` as the Microsoft account email and falls back to `userPrincipalName`. It assembles the mailing address from `displayName`, street address, and a line joining city, state and ZIP. Empty lines are omitted and ZIP codes stay as text. If no address components are supplied, the mailing address stays blank. Time zones use IANA identifiers.

Reruns preserve nonblank profile fields and existing roles. The command adds missing CSV roles through the usual audited role grant service. Duplicate or invalid identities, invalid profile fields or roles, and matches to disabled accounts abort the import. The import runs in one transaction and reports counts after commit.

## Messages, safety and reruns

The catalog, location, stock, supplier, purpose, program and officer commands print start and success/failure messages. Success is reported after commit, with counts, including unchanged reruns. Failures return nonzero; expected validation/authorization problems are explained, while unexpected private details go to application logs. `db:seed --class=...` uses Laravel's normal exception output. Missing files produce actionable CSV/path guidance. Do not print file contents or personal details in command summaries.

Forge's missing `.forge/provision-....output` error concerns its output capture, not CSV validation; inspect execution before rerunning. Especially for stock, an unexamined rerun may reapply absolute counts.
