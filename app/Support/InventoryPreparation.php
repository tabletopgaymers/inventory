<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InventoryPreparation
{
    /**
     * Run the guarded inventory migration after prerequisite readiness and compatibility checks pass. Development requires the hosted flag.
     *
     * Scope: Domain-specific (Setup and readiness)
     * Task: Prepare inventory schema
     * @example routes/console.php
     */
    public function prepare(bool $hosted): bool
    {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || (app()->environment('development') && ! $hosted)) {
            return false;
        }
        $state = $probe->schemaState(DB::connection());
        if (! $state['authenticationReady'] || ! $state['canPrepare']) {
            return false;
        }

        return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.InventorySchema::MIGRATION.'.php']) === 0
            && $probe->schemaState(DB::connection())['inventoryReady'];
    }

    /**
     * Seed sample inventory once in a supported environment after Inventory is ready. An existing sample marker ends the run. Existing stock and history are retained.
     *
     * Scope: Domain-specific (Setup and readiness)
     * Task: Prepare demo inventory
     * @example routes/console.php
     */
    public function demo(bool $hosted): bool
    {
        if (! app()->environment(['local', 'testing', 'development']) || (app()->environment('development') && ! $hosted)
            || ! app(BaselineProbe::class)->inspect()['ready'] || ! app(BaselineProbe::class)->schemaState(DB::connection())['inventoryReady']) {
            return false;
        }
        DB::transaction(function () {
            // Serialize this idempotent seed; never grant roles or overwrite existing stock/history.
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            if (DB::table('items')->where('sku', 'SAMPLE001')->exists()) {
                return;
            }
            $now = now('UTC');
            $category = DB::table('categories')->insertGetId(['name' => 'Sample Category', 'created_at' => $now, 'updated_at' => $now]);
            $collection = DB::table('collections')->insertGetId(['category_id' => $category, 'name' => 'Sample Collection', 'sku_prefix' => 'SAMPLE', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('items')->insert(['collection_id' => $collection, 'name' => 'Sample Item', 'sku_suffix' => '001', 'sku' => 'SAMPLE001', 'unit_cost' => '0', 'created_at' => $now, 'updated_at' => $now]);
            foreach (['Central', 'Sample Storage'] as $name) {
                if (! DB::table('storage_locations')->where('name', $name)->exists()) {
                    DB::table('storage_locations')->insert(['name' => $name, 'is_central' => $name === 'Central', 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }, 3);

        return true;
    }
}
