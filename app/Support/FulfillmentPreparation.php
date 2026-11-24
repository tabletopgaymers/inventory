<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FulfillmentPreparation
{
    /**
     * Run the fulfillment migration for a compatible local, testing, or hosted development setup after readiness checks pass.
     *
     * Scope: Domain-specific (Setup and readiness)
     * Task: Prepare fulfillment schema
     * @example routes/console.php
     */
    public function prepare(bool $hosted = false): bool
    {
        $probe = app(BaselineProbe::class);
        if (! app()->environment(['local', 'testing', 'development'])
            || (app()->environment('development') && ! $hosted)
            || ! $probe->inspect()['ready']) {
            return false;
        }
        $state = $probe->schemaState(DB::connection());
        if (! $state['requestsReady'] || ! $state['canPrepare']) {
            return false;
        }

        return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.FulfillmentSchema::MIGRATION.'.php']) === 0
            && $probe->schemaState(DB::connection())['fulfillmentReady'];
    }
}
