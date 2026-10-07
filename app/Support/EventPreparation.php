<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class EventPreparation
{
    public function prepare(bool $hosted = false): bool
    {
        $probe = app(BaselineProbe::class);
        if (! app()->environment(['local', 'testing', 'development'])
            || (app()->environment('development') && ! $hosted)
            || ! $probe->inspect()['ready']) {
            return false;
        }
        $state = $probe->schemaState(DB::connection());
        if (! $state['fulfillmentReady'] || ! $state['canPrepare']) {
            return false;
        }

        return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.EventSchema::MIGRATION.'.php']) === 0
            && $probe->schemaState(DB::connection())['eventsReady'];
    }
}
