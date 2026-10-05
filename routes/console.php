<?php

use App\Support\BaselineProbe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
