# inventory
Web application for basic management of inventory items.

## Phase 3 authentication candidate

D-177 authorizes this bounded development slice. Application implementation is
prepared for independent review; publication/live provider acceptance remain
separate evidence. `/` and `/users` require an enabled authenticated account;
`/profile` saves only the current user's names and organizational contact.
`/baseline` retains the safe runtime/revision diagnostic. Microsoft sign-in is
the only login method; there is no password registration/reset flow.

Prepare the existing isolated local and test schemas with `baseline:prepare`
as below, then run `authentication:prepare` and
`authentication:prepare --env=testing`. These are non-destructive forward
migrations. Incomplete or inconsistent schema stops preparation for inspection;
never repair it with a destructive reset. Tests use synthetic identity GUIDs,
blank names and explicitly synthetic contact addresses, never real fixtures.

Privately supply MICROSOFT_TENANT_ID, MICROSOFT_CLIENT_ID,
MICROSOFT_CLIENT_SECRET, MICROSOFT_REDIRECT_URI,
MICROSOFT_POST_LOGOUT_REDIRECT_URI, MICROSOFT_BOOTSTRAP_TENANT_ID and
MICROSOFT_BOOTSTRAP_OBJECT_ID. Verify the bootstrap identity in Entra first;
its tenant must equal the verified TG tenant. Configuration never derives role
authority from an email. No values belong in Git or diagnostics. Keep distinct
12-month local/hosted secrets within the approved development registration.

The exact callback is `/auth/microsoft/callback`, and sign-out return is
`/signed-out`, at `https://tg-inventory-app.test` locally and
`https://dev-inventory.tabletopgaymers.org` hosted. Only scopes openid, profile,
email and User.Read are requested. Stateful Socialite PKCE is enabled on both
requests. Claims are validated by the maintained adapter; the application
checks tenant and consistent Graph/object identity. Tokens are never persisted.

Normal authentication ends at 30 idle minutes or 12 absolute hours. Remembered
authentication uses a persistent encrypted database-session cookie with rolling
seven-day server expiry. There is no separate indefinite remember-token path.
Database session retention is seven days for both modes; middleware enforces
the shorter normal deadlines before protected access. Only explicit browser
navigation (Sec-Fetch-User) or CSRF-protected marked form activity renews timers;
automatic polling does not. A site logout destroys the current database session.
Microsoft sign-out requires its separate explicit CSRF-protected button.

Initial bootstrap grants basic access, then one Admin grant after the designated
user self-attests valid organizational contact. Consumption and audit are
transactional. Later sign-ins/profile saves never restore removed Admin access.
Role/disablement controls refresh current permissions and serialize changes
with profile/bootstrap updates. Audits have no update/delete application route.
Re-enablement, last-holder and recovery policies remain outside this slice.

### Authentication publication and compatible recovery

#### Phase 4 correction candidate — guarded publication

Retained reviewed bridge: `04c00edc22172688e9ce30196d0e74a4ba070128`,
Forge release `79443486`. Reverify it remains retained immediately before the
later hosted migration; source recovery preserves balances/history and uses the
retained release's own private configuration. Do not recover with an older
schema-incompatible source or drop data. Partial DDL stops activation and needs
inspected forward repair.

Local/testing forward setup (no database reset): `php artisan inventory:prepare`
and `php artisan inventory:prepare --env=testing`. `inventory:check` checks the
exact contract read-only. Optional `inventory:demo` installs only approved
fictional reference records in development, once, without identities/roles,
stock or history. All commands deny production; hosted preparation/demo require
`--hosted` and explicit final publication clearance. Do not run hosted commands
before independent full-candidate review and Management handoff.

The unchanged Forge entry point now checks the exact CI revision/configuration,
baseline and private provider settings, prepares authentication, prepares the
allowlisted inventory migration and approved development references, caches
configuration, verifies authentication/inventory readiness and caches views
before activation. CI prepares disposable stock schemas before required checks.
No operational down/reset/refresh commands are part of this sequence.

`/inventory/item-history` is now a working item index. Item/edit/review/save/result
and immutable grouped adjustment detail use existing session/CSRF protection.
Admin/Manager corrections serialize with existing access management and the item,
validate all storage targets, and atomically post balances/history once per
server-session-bound operation UUID. Repeated Save returns the existing result;
no-op confirms retain an operation group but create no History entries. An
absolute Set plus Adjust is the requested final value; deltas are computed from
the serialized current state without rebasing the count. Inputs/results are
bounded to ±1,000,000,000, preserving signed INT deltas; browser BigInt previews
and exact decimal cost rounding avoid floating-point loss. Cost never changes
on quantity correction. Dates are stored in UTC, displayed America/Chicago.

Pre-stock bridge DDL rehearsal tests are skipped once testing stock is installed
to preserve that dataset; their independent 04c00ed evidence remains the recovery
prerequisite. No-write orphan-ledger regressions continue running. Current tests
also rebuild only preflight-empty disposable stock tables with the actual forward
migration and verify the unchanged retained contract, authorization, snapshots,
no-op/retry/overflow, forced transaction failure and overlapping processes. Tests
never reset authentication or use real fixture identities. Local-only demo data
and source checks do not constitute hosted workflow acceptance.

#### Retained Phase 4 stock-compatible bridge

The first Stage 2 candidate changes only read-only schema compatibility. It does
not install stock tables, expose stock UI or change the Forge gate entry point.
`InventorySchema` declares seven correction-slice tables and migration identity
`2026_10_06_000000_create_inventory_tables`. The baseline accepts the existing
healthy authentication schema or the complete declared inventory schema; partial
tables, a missing/orphan migration record, wrong columns/types/nullability,
missing primary/unique keys, unsafe/missing foreign keys and non-InnoDB storage
fail closed. Unknown tables remain rejected. Signed INT quantities and historical
DECIMAL(24,12) costs are part of this compatibility contract.

Publish this bridge only after independent review and Management clearance.
Verify/retain its exact healthy release before publishing any stock migration.
Existing Stage 1/authentication recovery releases reject stock tables and cannot
serve as recovery source after that migration. The later correction release must
match this contract, add guarded forward preparation/readiness and disposable CI
stock preparation, and extend the existing authentication release gate internally.
Do not activate a partial schema or delete balances/history to recover source.
The bridge's disposable MariaDB tests create/remove only owned test fixtures;
there is deliberately no business migration or stock preparation command here.

Independent review must cover both the minimal compatibility bridge
`1dd60a4ad7a19526bd3abdf536d391dac5376a87` and the final authentication revision.
First publish/verify the bridge through existing main CI/Forge with the existing
baseline gate. It retains Phase 2 baseline behavior and accepts either the
healthy session-only schema or complete verified authentication schema. Keep
that exact healthy release retained before publishing the authentication source.
Do not use original Phase 2 `2d845bca2e93c7fc89d919e6b1af2c212dfd2d1d` as a
working recovery target after migration; it rejects authentication tables.

For the authentication candidate, after existing Composer/shared-path macros and
before ACTIVATE_RELEASE, replace the baseline gate block with:

```sh
"$FORGE_PHP" scripts/authentication-release-gate.php || exit 1
```

Retain ACTIVATE_RELEASE and RESTART_QUEUES afterward. The script checks the exact
CI revision/clean source, clears stale configuration, verifies the isolated
baseline and private provider settings, applies only the forward authentication
migration, caches configuration and checks schema/authentication/views. Any
failure stops activation; shared data is retained for inspection. A partial DDL
failure requires inspected forward repair, never rollback/drop/reset. The gate
does not prove real provider exchange or consent.

Keep whole-site Basic protection. Confirm callback compatibility with a live
browser before acceptance. Provider codes/tokens must not enter access logs:
review this site's callback logging and error-monitoring configuration; use a
query-free URI access-log format or scoped callback access logging exclusion.
Do not capture callback query strings, authorization headers or provider bodies
in evidence. Application callback failures expose only a generic restart.
Any concrete server configuration change needs the existing scoped coordination.

If activation fails, the compatible bridge remains current. If a source fault
appears after activation, the authorized site operator restores the verified
retained bridge release with its own cached configuration. Keep whole-site Basic
protection during recovery; the bridge exposes only the development baseline,
not authenticated application data. Authentication data remains untouched.
Confirm HTTPS, full bridge revision and baseline readiness; do not claim source
recovery reverses database/environment changes. A failed candidate's secret-rich
configuration cache remains private and release-local.

### Living presentation/component guide

`public/app.css` is the single authentication presentation stylesheet, authored
independently. Shared Blade `layout` supplies purple navigation/title, feedback,
missing-contact prompts and a development revision footer. `.primary` is the
final action; `.discard` identifies Discard/disable; `.notice`, `.warning` and
`.error` distinguish success, attention and failure with visible text. Labeled
inputs and checkbox rows support keyboard use; `.sr-only` supplies user context
for repeated directory actions. `.table-wrap` scrolls the directory on narrow
screens; essential form controls remain usable without horizontal scrolling.
Maintain these documented components with their stylesheet when changing them.

The historical Phase 2 instructions below remain foundation/setup evidence;
this section supersedes their authentication/schema exclusions for D-177 only.

## Local development baseline

The original local baseline is authorized under planning decision D-164. Application
root: `D:\Sites\tg-inventory-app`; web root: its `public` directory. Intended URL:
`https://tg-inventory-app.test`. Herd HTTPS/browser and private-path protection are verified locally.

Requirements: PHP 8.5 with PDO MySQL, Composer and the existing MariaDB 10.11 service
at `127.0.0.1:3306`. Laravel is locked in `composer.lock`. Use a simple Blade page and
static `public/baseline.css`; no Node installation or asset build is required.

## Installation and private local configuration

1. Clone the application repository into an empty application directory, preserving
   existing work. Run `composer install` from that directory to install the lockfile.
2. Privately provision two unused isolated MariaDB databases with utf8mb4 and
   utf8mb4_unicode_ci: `tg_inventory_local` and `tg_inventory_test`. Create matching
   application-specific account names restricted to `127.0.0.1`, each with a separate
   random password. Grant SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX and DROP
   only on its respective schema. In SQL GRANT statements escape schema underscores
   (`tg\_inventory\_local`, `tg\_inventory\_test`) so they cannot act as wildcards.
   Root/admin is only for provisioning. Preserve occupied names and existing data;
   resolve a collision before proceeding rather than adopting/resetting them.
3. Copy `.env.example` to `.env` and `.env.testing.example` to `.env.testing` only if
   the destination does not already exist. Privately set the matching account passwords
   and generate an application key with `php artisan key:generate`, then separately
   `php artisan key:generate --env=testing`. Never paste actual environment files into
   chat or logs. Both files are ignored. APP_DEBUG must remain false.
4. Clear any local configuration cache with `php artisan config:clear`. Prepare each
   isolated database with `php artisan baseline:prepare` and
   `php artisan baseline:prepare --env=testing`. These check the expected environment,
   local address, account/database, PHP/framework/server versions and key before running
   non-destructive migrations. They may be repeated; they never reset existing data.
   Only `migrations` and `sessions` tables are needed; no users or sample identities
   are created. Do not run generic migrations against another database.
5. Keep `D:\Sites` parked in Herd. Its `tg-inventory-app` subdirectory automatically
   creates the site `tg-inventory-app.test`. Select PHP 8.5 for this site in Herd and
   enable HTTPS for exactly `tg-inventory-app.test`. Equivalent commands are
   `herd isolate 8.5 --site=tg-inventory-app` and `herd secure tg-inventory-app`.
   Serve only Laravel's `public` directory; never serve the application root.
   Verify trusted HTTPS, runtime versions and private-path protection.
6. Run `composer check`, or `php scripts/check.php`. The runner verifies installed
   package versions against the lockfile, static assets, local/test database readiness,
   PHP formatting and integration/failure tests. Exit 0 means those checks passed;
   failures return exit 1 with a safe stage diagnostic. Clear cached configuration
   before checking. PHPUnit reads `.env.testing`; it does not substitute SQLite.
   Browser HTTPS and private-path HTTP checks are separate from this command.

The current machine has dedicated accounts/passwords and application keys saved in
ignored environment files. Existing databases and root access were preserved. Local
sessions use the database with encryption and secure cookies; this is a baseline
default and does not settle future authentication/session policy. Tests use only their
own isolated database and remove their temporary session record. No reset command is
required or supplied; never use a destructive reset on the local application database.

## Baseline page and review

The page prominently identifies local development, shows successful database status
and PHP/Laravel/MariaDB versions, and includes no inventory actions or sample identities.
Unavailable service/configuration returns HTTP 503 with a safe message before session
middleware can expose connection errors. After a successful probe, PDO/query failures
originating in Laravel's database session handler return the same safe HTTP 503
presentation through the exception handler, without ordinary raw exception reporting.
Unsuccessful new-session insert recovery also returns safe HTTP 503. A zero-row
fallback update succeeds only when the saved row matches the intended session payload;
successful concurrent-insert recovery and existing-session no-op updates remain valid.
Other exceptions retain normal reporting and rendering. The shared stylesheet uses purple headings,
green successful status and amber unavailable status, with desktop and narrow-screen
layout and white print backgrounds; those are the current baseline presentation rules.

No sign-in, Microsoft credentials, inventory schema/data or imports are included.
Hosted development support is described below; no deployment has been performed by the implementation agent. Independent review remains required; commit/push do not constitute review or acceptance. Requirements and
setup evidence remain authoritative in the planning workspace:
`C:\Users\delug\Documents\ChatGPT\TG Dev Inventory\work\tasks\phase-02-local-baseline.md`.

## Phase 2 development publication candidate

This source is prepared under D-169; independent review and actual hosted setup
verification are separate gates. No Phase 3 features are included. Preserve APP_KEY,
passwords, shared storage and existing databases. Target only Forge server 794348,
site 3411668, inventorydev, main, PHP 8.5, public/ and
https://dev-inventory.tabletopgaymers.org. These administration identifiers are
previous user reports; verify them before changing the site.

### Isolated automated checks

`.github/workflows/development.yml` runs locked dependencies and `composer check`
on PHP 8.5 with an ephemeral MariaDB 10.11 container. It creates only disposable
local/test schemas and synthetic CI passwords, never connects to the hosted DB and
needs no private application credentials. Pull requests and manual branch runs
check without deploying; only successful main checks request development deployment.
The deploy job has `needs: checks`, so failed checks skip it. No asset build is needed.
A hook response only acknowledges the request; verify Forge completion separately.

### Cut over the existing hook safely

After independent source review, before publishing the candidate:

1. Disable the existing direct GitHub push webhook and Forge push-to-deploy for this
   development site. Do not duplicate triggers. Preserve the existing active release.
2. Privately save the existing site's hook as the GitHub `development` environment
   secret `DEVELOPMENT_DEPLOY_HOOK`. Limit environment deployment branches to main.
   Never print the URL, add it to source or share it with PR/branch jobs. Only trusted
   maintainers can edit main/workflows/environment secrets or the Forge script.
3. Save the deployment sequence below in this site only. Preserve Forge's release
   creation, working-directory, Composer install, activation and queue macros.
   Keep installation from composer.lock; never composer update or generic migration.
4. Publish the reviewed source only after Owl releases the review gate. Confirm the
   Actions checks succeed, the deploy job requests it, Forge activates the matching
   SHA and the page displays that same full SHA. A newer main pushed during a queued
   request can cause a safe SHA mismatch; rerun the latest successful main workflow.

The CI-only private hook is the trust boundary. A holder of the hook can supply a
checked SHA; this is not cryptographic or independent GitHub attestation. Disable
all bypass triggers and control hook custody. Manual Deploy Now without the CI
parameter must fail. Do not remove the gate to work around a failed deployment.
Forge exposes custom `checked_sha` as `FORGE_VAR_CHECKED_SHA`; its
`forge_deploy_commit` is only a history label and does not select the actual source.
The gate compares the full SHA to actual clean Git HEAD before writing a release-local
ignored revision marker. It refuses missing, malformed or mismatched revisions and
source changes. The only dirty-status allowance is a real storage directory link
outside the release, its untracked link entry and deletions of the ten known tracked
storage .gitignore placeholders. Other shared paths, source modifications, staged
changes and unexpected untracked files are rejected. Confirm the actual site's
shared paths match this supported layout before publication.

After CREATE_RELEASE, `cd "$FORGE_RELEASE_DIRECTORY"`, shared environment linkage
and existing locked Composer installation, insert these lines before ACTIVATE_RELEASE:

```sh
"$FORGE_PHP" scripts/release-gate.php || exit 1
"$FORGE_PHP" artisan config:cache || exit 1
"$FORGE_PHP" artisan baseline:schema || exit 1
"$FORGE_PHP" artisan baseline:check || exit 1
"$FORGE_PHP" artisan view:cache || exit 1
```

Retain ACTIVATE_RELEASE and RESTART_QUEUES afterward. No test/development packages or
CI database are required on the host. The marker must precede config:cache because
Laravel caches it. For a clean local copy after publication, set the process variable
`FORGE_VAR_CHECKED_SHA` to its full Git HEAD, run `php scripts/release-gate.php`, unset
that variable and clear local configuration cache. A dirty copy is intentionally
unrecorded. Do not manually set a marker to claim an unverified deployed identity.

### Hosted configuration and schema

Keep the existing private shared .env, APP_KEY and DB_PASSWORD. Required nonsecret
settings: APP_ENV=development, APP_DEBUG=false,
APP_URL=https://dev-inventory.tabletopgaymers.org, DB_CONNECTION=mariadb,
DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=DB_USERNAME=tg_inventory_dev.
No DB_URL, socket or prefix override. Sessions: database driver, mariadb/default
connection, sessions table, encryption, Secure, HttpOnly, SameSite=lax, path /,
null domain, and a dedicated cookie name. Cache=file, queue=sync, mail=array.
Shared .env/storage remain shared; bootstrap/cache remains release-local.

Privately verify grants only on the dedicated schema (escape underscores in SQL
GRANT targets). Runtime needs SELECT/INSERT/UPDATE/DELETE; existing guarded schema
preparation needs CREATE/ALTER/INDEX/DROP. Inspect existing grants without resetting
accounts. `baseline:schema` and `baseline:check` are read-only; their checks prove
required table/column names and migration record, not full grants/types/indexes.
Only if inspection finds a pending baseline migration and separately authorized,
`php artisan baseline:prepare --hosted` prepares it non-destructively. Inconsistent
existing schema requires inspection; never migrate:fresh/refresh/rollback on hosted.

Restrict unfinished development through this site's Forge password protection or
an existing compatible access mechanism; verify unauthenticated requests challenge
and authorized HTTPS succeeds. Do not implement application authentication here.
Later Microsoft callback access must be reassessed in its own authorized task.
Do not globally modify server security or unrelated sites. Verify authorized page
200, database success, matching revision, protected session cookie flags and
403/404 for /.env, /.git/config, /composer.json, /storage/logs/laravel.log.
Never retain credential-bearing headers, cookie values or private response bodies.

### Failure demonstration and recovery

Once the reviewed workflow and cutover are available, manually dispatch it on
**main** with `fail_check=true`. This run is otherwise eligible for deployment;
its deliberate check must fail and the dependent deploy job must visibly skip.
Retain the failed run, skipped job and unchanged active hosted SHA as evidence.
The process-only failure occurs after normal checks and before the hook job;
it modifies neither source nor the active release. Then demonstrate a successful
main run with `fail_check=false`. Branch/PR failures are supplementary controls:
they cannot establish this main dependency because they cannot deploy even when
checks succeed. A local gate simulation does not prove hosted/CI enforcement.

Before publication, record the active release directory/SHA and confirm retained
previous releases (Forge normally retains four). A pre-activation failure keeps
`current` on the previous release; inspect the safe failed stage, repair the candidate
and rerun checks. No shared environment/schema changes are included in deployment.
For a post-activation source fault, the site operator uses the site's supported
previous-release recovery or atomically restores current to a verified retained
release, checks its cached settings, and verifies HTTPS/revision/session readiness.
If exact recovery controls/access are missing, obtain them before claiming recovery
verified. Source rollback does not restore a shared database, environment or storage.

Before retaining meaningful development data, verify the existing daily Forge backup
actually includes tg_inventory_dev, record schedule/retention, obtain one successful
backup and confirm private retrieval. Existing reported schedule: 09:00 UTC, seven
days, include-future-databases; this is not newly verified configuration. Preserve
private backup storage/access. Restore only to a separately provisioned disposable
database for a recovery rehearsal; never overwrite hosted data. Do not add paid
backup services or change unrelated database backups under this task.

### Disposable test reset

Never reset local application or hosted data. For the approved throwaway
`tg_inventory_test` only, first clear local config cache and verify effective
APP_ENV=testing, loopback DB, exact tg_inventory_test schema/account and that no
valuable data exists. Run `php artisan baseline:check --env=testing` and stop on any
failure. Only then an operator may run `php artisan migrate:fresh --env=testing`
without seeders, followed by the test readiness check and `composer check`.
Do not use this as a schema troubleshooting command. A fresh clean installation
uses `baseline:prepare --env=testing`, not a reset.

References: [Forge deployments](https://laravel.com/forge/docs/sites/deployments)
(custom parameters, shared paths, release macros and retention),
[PHP setup action](https://github.com/shivammathur/setup-php), and
[MariaDB container healthcheck](https://mariadb.com/docs/server/server-management/automated-mariadb-deployment-and-administration/docker-and-mariadb/using-healthcheck-sh).
