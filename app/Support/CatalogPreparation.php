<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CatalogPreparation
{
    /**
     * Run the guarded Catalog migration for a compatible setup. The hosted flag is required for development. Returns true when the migration succeeds and readiness is confirmed.
     *
     * Scope: Domain-specific (Setup and readiness)
     * Task: Prepare catalog schema
     * @example routes/console.php
     */
    public function prepare(bool $hosted): bool
    {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || (app()->environment('development') && ! $hosted)) {
            return false;
        }
        $state = $probe->schemaState(DB::connection());
        if (! $state['inventoryReady'] || ! $state['canPrepare']) {
            return false;
        }

        return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.CatalogSchema::MIGRATION.'.php']) === 0
            && $probe->schemaState(DB::connection())['catalogReady'];
    }
}
