<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DailyInventoryPreparation
{
    public function prepare(bool $hosted): bool
    {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || (app()->environment('development') && ! $hosted)) {
            return false;
        }
        $state = $probe->schemaState(DB::connection());
        if (! $state['catalogReady'] || ! $state['canPrepare']) {
            return false;
        }

        return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.DailyInventorySchema::MIGRATION.'.php']) === 0
            && $probe->schemaState(DB::connection())['dailyInventoryReady'];
    }
}
