<?php

use App\Support\BaselineProbe;
use Illuminate\Support\Facades\Artisan;

Artisan::command('baseline:check', function () {
    $result = app(BaselineProbe::class)->inspect();
    if (! $result['ready']) {
        $this->error($result['message']);

        return 1;
    }
    $this->info('Local baseline: PHP 8.5, Laravel 13, MariaDB 10.11 and session table verified.');

    return 0;
})->purpose('Check the approved local baseline without exposing connection settings');

Artisan::command('baseline:prepare', function () {
    $result = app(BaselineProbe::class)->inspect(requireSessions: false);
    if (! $result['ready']) {
        $this->error($result['message']);

        return 1;
    }
    try {
        $status = $this->callSilent('migrate', ['--no-interaction' => true]);
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
