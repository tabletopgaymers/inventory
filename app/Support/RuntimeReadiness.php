<?php

namespace App\Support;

/** Lightweight readiness for the current application, not a structural schema audit. */
class RuntimeReadiness
{
    public function state($database): array
    {
        // Request attributes expire with the request, including in long-lived workers.
        $key = self::class.'.'.spl_object_id($database).'.'.$database->getDatabaseName();
        $request = request();
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $required = array_map(fn ($path) => basename($path, '.php'), glob(database_path('migrations/*.php')));
        $applied = $database->table('migrations')->pluck('migration')->all();
        $ready = $required !== [] && array_diff($required, $applied) === [];
        // Web routes serve the complete current application. Phased preparation
        // and compatibility decisions continue to use BaselineProbe::schemaState.
        $state = array_fill_keys([
            'ready', 'authenticationReady', 'profilePreferencesReady', 'inventoryReady',
            'catalogReady', 'dailyInventoryReady', 'requestsReady', 'fulfillmentReady', 'eventsReady',
        ], $ready);
        $request->attributes->set($key, $state);

        return $state;
    }
}
