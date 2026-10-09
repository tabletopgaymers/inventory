<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

class BaselineProbe
{
    public function inspect(bool $requireSessions = true): array
    {
        $result = [
            'ready' => false,
            'message' => 'Application configuration needs attention. Check the application key, debug and session settings.',
            'baselineLabel' => app()->environment('local') ? 'LOCAL APPLICATION' : 'APPLICATION',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => Application::VERSION,
            'databaseVersion' => null,
        ];
        $key = config('app.key', '');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        // Validate security settings, not a particular site's name or server version.
        if (config('app.debug') !== false
            || ! is_string($decodedKey)
            || ! \Illuminate\Encryption\Encrypter::supported($decodedKey, config('app.cipher'))
            || config('session.table') !== 'sessions'
            || ! in_array(config('session.connection'), [null, config('database.default')], true)
            || (! app()->environment('testing') && (config('session.driver') !== 'database'
                || config('session.encrypt') !== true || config('session.secure') !== true
                || config('session.http_only') !== true
                || ! in_array(config('session.same_site'), ['lax', 'strict'], true)))) {
            return $result;
        }
        try {
            $database = DB::connection();
            $probe = $database->selectOne('SELECT 1 AS probe, VERSION() AS version');
            if ((int) $probe->probe !== 1) {
                return $result;
            }
            if ($requireSessions && ! $this->schemaState($database)['ready']) {
                $result['message'] = 'Session schema or migration state needs attention. Run the documented preparation step.';

                return $result;
            }
            $result['ready'] = true;
            $result['message'] = 'Database check passed.';
            $result['databaseVersion'] = explode('-MariaDB', $probe->version)[0];
        } catch (Throwable) {
            $result['message'] = 'Database check failed. Check the database service and private configuration.';
        }

        return $result;
    }

    public function schemaState($database): array
    {
        $schema = $database->getSchemaBuilder();
        $tables = $schema->getTableListing(null, false);
        $authentication = [
            'users' => ['id', 'first_name', 'last_name', 'provider_email', 'contact_email', 'contact_attested', 'enabled', 'created_at', 'updated_at'],
            'external_identities' => ['id', 'user_id', 'provider', 'tenant_id', 'object_id', 'created_at', 'updated_at'],
            'user_roles' => ['user_id', 'role'],
            'authentication_bootstraps' => ['key', 'user_id', 'consumed_at'],
            'access_audits' => ['id', 'actor_id', 'target_id', 'action', 'previous', 'current', 'occurred_at'],
        ];
        $known = array_diff($tables, array_merge(['migrations', 'sessions'], array_keys($authentication), InventorySchema::TABLES, CatalogSchema::TABLES, DailyInventorySchema::TABLES, RequestSchema::TABLES, FulfillmentSchema::TABLES, EventSchema::TABLES)) === [];
        $sessions = in_array('sessions', $tables, true);
        $ledger = in_array('migrations', $tables, true);
        $migration = '2026_10_05_000000_create_sessions_table';
        $recorded = $ledger && $database->table('migrations')->where('migration', $migration)->exists();
        $columns = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'];
        $compatible = $sessions && array_diff($columns, $schema->getColumnListing('sessions')) === [];
        $authPresent = array_intersect(array_keys($authentication), $tables);
        $authReady = false;
        $authCompatible = $authPresent === [];
        if (count($authPresent) === count($authentication) && $ledger) {
            $authReady = $database->table('migrations')->where('migration', '2026_10_05_010000_create_authentication_tables')->exists();
            foreach ($authentication as $table => $required) {
                $authReady = $authReady && array_diff($required, $schema->getColumnListing($table)) === [];
            }
            if ($authReady) {
                $identityUnique = false;
                foreach ($schema->getIndexes('external_identities') as $index) {
                    if ($index['unique'] && $index['columns'] === ['provider', 'tenant_id', 'object_id']) {
                        $identityUnique = true;
                    }
                }
                $authReady = $identityUnique;
            }
            $authCompatible = $authReady;
        }
        // Orphan stock migration records must also fail before authentication exists.
        $inventory = app(InventorySchema::class)->state($database, $tables);
        $catalog = app(CatalogSchema::class)->state($database, $tables);
        $daily = app(DailyInventorySchema::class)->state($database, $tables);
        $requests = app(RequestSchema::class)->state($database, $tables);
        $preferences = app(ProfilePreferences::class)->state($database, $tables);
        $fulfillment = app(FulfillmentSchema::class)->state($database, $tables);
        $events = app(EventSchema::class)->state($database, $tables);
        $known = $known && $authCompatible && $preferences['compatible'] && $inventory['compatible'] && $catalog['compatible'] && $daily['compatible'] && $requests['compatible']
            && $fulfillment['compatible'] && (! $fulfillment['ready'] || $requests['ready'])
            && $events['compatible'] && (! $events['ready'] || $fulfillment['ready'])
            && (! $inventory['ready'] || $authReady) && (! $catalog['ready'] || $inventory['ready']) && (! $daily['ready'] || $catalog['ready']) && (! $requests['ready'] || $daily['ready']);

        return [
            'authenticationReady' => $authReady,
            'profilePreferencesReady' => $authReady && $preferences['ready'],
            'inventoryReady' => $inventory['ready'],
            'catalogReady' => $catalog['ready'],
            'dailyInventoryReady' => $daily['ready'],
            'requestsReady' => $known && $recorded && $compatible && $requests['ready'] && $daily['ready'],
            'fulfillmentReady' => $known && $recorded && $compatible && $requests['ready'] && $fulfillment['ready'],
            'eventsReady' => $known && $recorded && $compatible && $fulfillment['ready'] && $events['ready'],
            'ready' => $known && $recorded && $compatible,
            'canPrepare' => $known && ((! $sessions && ! $recorded) || ($recorded && $compatible)),
            'message' => $known && $recorded && $compatible
                ? 'Baseline session schema and migration record verified.'
                : ($known && ! $sessions && ! $recorded
                    ? 'Baseline session migration is pending; guarded preparation is available.'
                    : 'Existing schema needs inspection before preparation; no changes were made.'),
        ];
    }
}
