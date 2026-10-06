<?php

use App\Support\BaselineProbe;
use App\Support\CatalogPreparation;
use App\Support\DailyInventoryPreparation;
use App\Support\InventoryPreparation;
use App\Support\MicrosoftConfiguration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('daily-inventory:prepare {--hosted}', function () {
    try {
        if (! app(DailyInventoryPreparation::class)->prepare((bool) $this->option('hosted'))) {
            throw new RuntimeException;
        }
        $this->info('Count and cost schema verified.');

        return 0;
    } catch (Throwable) {
        $this->error('Count and cost preparation failed; retain data for inspection.');

        return 1;
    }
});

Artisan::command('daily-inventory:check', function () {
    try {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || ! $probe->schemaState(DB::connection())['dailyInventoryReady']) {
            throw new RuntimeException;
        }
        $this->info('Count and cost schema ready.');

        return 0;
    } catch (Throwable) {
        $this->error('Count and cost readiness failed.');

        return 1;
    }
});

Artisan::command('catalog:prepare {--hosted}', function () {
    try {
        if (! app(CatalogPreparation::class)->prepare((bool) $this->option('hosted'))) {
            throw new RuntimeException;
        }
        $this->info('Additive catalog schema verified; existing stock/history retained.');

        return 0;
    } catch (Throwable) {
        $this->error('Catalog preparation failed; retain data for inspection and forward repair. Activation must stop.');

        return 1;
    }
})->purpose('Guarded forward additive Phase 5 migration only');

Artisan::command('catalog:check', function () {
    try {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || ! $probe->schemaState(DB::connection())['catalogReady']) {
            throw new RuntimeException;
        }
        $this->info('Exact additive catalog schema and migration record verified.');

        return 0;
    } catch (Throwable) {
        $this->error('Catalog readiness failed; activation must stop.');

        return 1;
    }
})->purpose('Read-only exact catalog readiness check');

Artisan::command('inventory:prepare {--hosted}', function () {
    try {
        if (! app(InventoryPreparation::class)->prepare((bool) $this->option('hosted'))) {
            throw new RuntimeException;
        }
        $this->info('Inventory schema verified.');

        return 0;
    } catch (Throwable) {
        $this->error('Inventory preparation failed; retain data for inspection and forward repair. Activation must stop.');

        return 1;
    }
})->purpose('Guarded forward correction-slice migration only');

Artisan::command('inventory:check', function () {
    try {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || ! $probe->schemaState(DB::connection())['inventoryReady']) {
            throw new RuntimeException;
        }
        $this->info('Exact inventory schema and migration record verified.');

        return 0;
    } catch (Throwable) {
        $this->error('Inventory readiness failed; activation must stop.');

        return 1;
    }
})->purpose('Read-only exact inventory readiness check');

Artisan::command('inventory:demo {--hosted}', function () {
    try {
        if (! app(InventoryPreparation::class)->demo((bool) $this->option('hosted'))) {
            throw new RuntimeException;
        }
        $this->info('Approved synthetic development reference data is present; stock/history unchanged.');

        return 0;
    } catch (Throwable) {
        $this->error('Synthetic development preparation failed; inspect before retrying.');

        return 1;
    }
})->purpose('Idempotent development-only fictional reference records; no roles or stock writes');

Artisan::command('baseline:check', function () {
    $result = app(BaselineProbe::class)->inspect();
    if (! $result['ready']) {
        $this->error($result['message']);

        return 1;
    }
    $this->info('Development baseline: PHP 8.5, Laravel 13, MariaDB 10.11 and session table verified.');

    return 0;
})->purpose('Check the approved development baseline without exposing connection settings');

Artisan::command('baseline:prepare {--hosted : Explicitly permit hosted development preparation}', function () {
    $result = app(BaselineProbe::class)->inspect(requireSessions: false);
    if (! $result['ready']) {
        $this->error($result['message']);

        return 1;
    }
    if (app()->environment('development') && ! $this->option('hosted')) {
        $this->error('Hosted preparation requires --hosted after read-only schema inspection.');

        return 1;
    }
    try {
        $schema = app(BaselineProbe::class)->schemaState(DB::connection('mariadb'));
        if (! $schema['canPrepare']) {
            $this->error($schema['message']);

            return 1;
        }
        $status = $this->callSilent('migrate', ['--no-interaction' => true, '--force' => true, '--path' => 'database/migrations/2026_10_05_000000_create_sessions_table.php']);
    } catch (Throwable) {
        $this->error('Session preparation failed. Check the isolated database privileges and migration state.');

        return 1;
    }
    if ($status !== 0) {
        $this->error('Session preparation failed. Check the isolated database migration state.');

        return 1;
    }
    $this->info('Isolated database session setup is ready.');

    return 0;
})->purpose('Apply non-destructive baseline migrations only to the approved isolated database');

Artisan::command('baseline:schema', function () {
    $probe = app(BaselineProbe::class);
    $result = $probe->inspect(requireSessions: false);
    if (! $result['ready']) {
        $this->error($result['message']);

        return 1;
    }
    try {
        $state = $probe->schemaState(DB::connection('mariadb'));
        $this->info($state['message']);

        return $state['canPrepare'] ? 0 : 1;
    } catch (Throwable) {
        $this->error('Schema inspection failed. Check private configuration and database privileges.');

        return 1;
    }
})->purpose('Read-only inspection before any baseline preparation');

Artisan::command('authentication:prepare {--hosted}', function () {
    $probe = app(BaselineProbe::class);
    if (! $probe->inspect()['ready'] || (app()->environment('development') && ! $this->option('hosted'))) {
        $this->error('Authentication preparation requires the healthy isolated baseline and explicit hosted option.');

        return 1;
    }
    try {
        $state = $probe->schemaState(DB::connection('mariadb'));
        if (! $state['canPrepare']) {
            throw new RuntimeException;
        }
        $status = $this->callSilent('migrate', ['--no-interaction' => true, '--force' => true, '--path' => 'database/migrations/2026_10_05_010000_create_authentication_tables.php']);
        if ($status !== 0 || ! $probe->schemaState(DB::connection('mariadb'))['authenticationReady']) {
            throw new RuntimeException;
        }
        $this->info('Authentication schema verified.');

        return 0;
    } catch (Throwable) {
        $this->error('Authentication preparation failed; activation must stop. Retain data for inspection.');

        return 1;
    }
})->purpose('Apply only the approved non-destructive authentication migration');

Artisan::command('authentication:check', function () {
    try {
        if (! app(BaselineProbe::class)->inspect()['ready']
            || ! app(BaselineProbe::class)->schemaState(DB::connection('mariadb'))['authenticationReady']
            || ! app(MicrosoftConfiguration::class)->ready()) {
            throw new RuntimeException;
        }
        $this->info('Authentication schema and private development configuration verified; live provider verification is separate.');

        return 0;
    } catch (Throwable) {
        $this->error('Authentication readiness failed; activation must stop. Check private setup.');

        return 1;
    }
})->purpose('Validate private settings without printing secrets or claiming live provider exchange');

Artisan::command('authentication:configuration', function () {
    if (! app(MicrosoftConfiguration::class)->ready()) {
        $this->error('Private Microsoft configuration is incomplete or inconsistent; activation must stop.');

        return 1;
    }
    $this->info('Private Microsoft development configuration is structurally consistent. Provider registration verification is separate.');

    return 0;
})->purpose('Fail safely before migration if private provider configuration is not ready');
