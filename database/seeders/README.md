# Development samples

State: Development-only sample data commands. Deployment never invokes these commands.

`DevelopmentSampleSeeder` uses the approved nonprivate D-186 hierarchy copied from the planning workspace's original `sample-data.md`, with deterministic illustrative quantities. The original source is preserved. The fixture contains 9 categories, 21 collections, 61 items and the 5 supplied storage locations; 7 items have zero stock throughout. It creates no identities, roles, events, suppliers, requests or fulfillment activity.

Run through the guarded Artisan entry point. Without an actor option, reuse the existing initial-admin bootstrap account under the same access/stock lock and current enabled Admin/Manager authorization. No new identity or role is created:

```powershell
php artisan samples:seed
```

An explicitly authorized existing actor may be supplied with `--actor=<id>`. Never copy a local actor ID to a remote environment. Missing/disabled/unprivileged bootstrap actors fail safely, even on an already installed fixture; resolve the existing access condition before publication or supply a separately authorized existing actor. The default `DatabaseSeeder` remains empty.

Local/testing runs require the exact local application URL. Hosted runs require explicit `--hosted`, environment `development` and exact `https://dev-inventory.tabletopgaymers.org`; the unchanged `BaselineProbe` additionally enforces loopback MariaDB and environment-specific database/account (`tg_inventory_dev` on hosted development), private/session configuration and schema boundaries. Production, unrelated hosts/databases, wrong option/context, disabled actors and actors without a stock-posting role fail closed. Failures use sanitized command output.

The installation is one transaction serialized with catalog/access/stock writes. Existing categories and locations match by scoped name; collections match by category/name and must have the approved prefix. Items match by unique full SKU and must agree with their collection/name. Ambiguous or conflicting identities roll back for inspection. `RPR-THEY` reuses the earlier They/Them record. Existing optional metadata, current costs, non-fixture storage quantities and unrelated records remain intact. New costs are zero/unknown (`n/a`), not invented purchase costs. Required catalog name registries are populated without overwriting existing metadata.

All quantities pass through `StockPosting`, retaining immutable history and current exact costs. Rationale identifies generated sample activity. An immutable operation UUID, `dbb53b93-cf7b-4859-a1f3-f11b9048ba01`, is the atomic v1 installation receipt. Zero-stock items may have an empty adjustment group but no quantity-history entries. A default rerun with that receipt does no catalog or stock work, preserving subsequent renames, SKUs, retirement, metadata, costs and quantities. It deliberately does not repair or reinitialize later user edits. Future fixture versions need a reviewed installation strategy; editing the JSON does not automatically replay an installed version.

For an explicitly authorized local quantity refresh only:

```powershell
php artisan samples:seed --actor=4 --refresh
```

This reruns the same scoped identity reconciliation and sets only approved sample items at the five fixture locations. It retains current costs, other location quantities, unrelated records and all previous history, and fails on renamed/conflicting identities rather than silently reparenting them. It does not delete sample records or reset the database. The command prohibits refresh outside `local`;

## Deployment separation

Deployment does not install sample data or reset records. Run these commands only as separately authorized operations.

## Explicit approved development reset — D-220/D-221

Only on a direct user request or affirmative approval for that reset, run in the reviewed candidate's directory through the existing approved publisher framework/CLI access:

```sh
php artisan samples:reset --hosted --confirm=development-business-data --no-interaction
```

The command is repeatable for later approved resets, never a per-deploy step. It requires the complete compatible transactional schema, exact hosted development context/explicit flag, exact confirmation and an existing enabled **Admin** (defaults to the current initial-admin bootstrap record; optional existing Admin `--actor=<id>`). Testing allows the disposable test database with no hosted flag. Live `local` and all production/unrelated contexts are rejected; the accepted local demo is preserved. A CLI flag cannot verify human approval: the operator must obtain the requested approval before invocation. No timeout or missing response counts as approval.

`DevelopmentSampleReset::TABLES` is the explicit25-table child-before-parent deletion allowlist: all7 request tables,3 count/cost tables,7 catalog/storage/stock/history tables and8 catalog/reference/source/preference tables. It replaces disposable business records, including immutable quantity/cost history only under this reset exception. It preserves users, external identities, roles, contact confirmations, bootstrap/access audits, sessions, applied migrations, configuration/secrets and schema. Account recreation is unnecessary. `DELETE` runs with foreign keys enabled in one transaction; no truncate/drop/migrate:fresh/auto-increment reset. Existing shared bootstrap locking serializes authorized catalog/access/stock/request writes. Any deletion/reseed/audit failure rolls back the original rows, and no partial fresh baseline is committed. Auto-increment gaps may remain after rollback; IDs are not reused.

After deletion, the same seeder loads the current approved fixture and audited illustrative stock. A separate empty stock group with a generated operation UUID captures actor/time/first item and identifies this explicit reset without extra quantity-history entries. The CLI reports that UUID. Every later approved reset creates a fresh baseline/receipt; ordinary seeding continues to use its installation UUID and no-ops after installation. Reset does not offer a first-ever-only lock or silently run because fixture revision changed.

A hosted reset is a separate explicitly authorized operation. Preserve protected accounts, sessions and business history according to the command contract; no reset runs during normal deployment.

For a later approved reset, follow the same command contract. Discard stale business views afterward; monotonic IDs prevent old item/request references targeting new rows.

## Fixture revision and growth convention

Keep the original supplied source unchanged and update its converted approved fixture copy through review. `version` identifies the approved dataset revision (current `development-samples-v1`); increment it when approved names, hierarchy, locations or quantities change. Preserve full item SKUs and category/collection-scoped identities for unchanged records; repeated names in different collections are distinct. Keep existing prefixes/suffixes stable. Append reviewed items/collections/categories; location quantity vectors follow the fixture location list exactly, so location changes require reviewing every vector. Generated quantities remain explicitly illustrative; transaction rationales capture the fixture revision.

The simplest current adoption path for a changed reviewed dataset is an **explicit approved reset**: it removes the installation receipt transactionally and installs the then-current fixture, so v1 never strands future additions. Merely changing `version` or publishing a JSON change does not reset or replay installed data. Normal deployment does not invoke the seeder and preserves later edits/history. No additive importer/version-migration platform is implemented. If future additions must be applied without reset, separately review a narrow additive-only path preserving existing identities/quantities/history; do not infer it from this command.

Future real-production initialization/update from user-supplied actual data is a future scoped goal. This command remains disposable development-only; test quantities are not production opening stock and no production reset/import/history rewrite is authorized.
