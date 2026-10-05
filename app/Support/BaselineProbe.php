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
            'message' => 'Development configuration needs attention. Run the documented baseline checks.',
            'baselineLabel' => app()->environment('development') ? 'HOSTED DEVELOPMENT BASELINE' : 'LOCAL DEVELOPMENT BASELINE',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => Application::VERSION,
            'databaseVersion' => null,
        ];
        $environment = app()->environment();
        $expectedDatabase = match ($environment) {
            'local' => 'tg_inventory_local',
            'testing' => 'tg_inventory_test',
            'development' => 'tg_inventory_dev',
            default => null,
        };
        $connection = config('database.connections.mariadb');
        $key = config('app.key', '');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if ($expectedDatabase === null
            || config('app.debug') !== false
            || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5
            || ! str_starts_with(Application::VERSION, '13.')
            || config('database.default') !== 'mariadb'
            || ($connection['host'] ?? null) !== '127.0.0.1'
            || (int) ($connection['port'] ?? 0) !== 3306
            || ! empty($connection['url']) || ! empty($connection['unix_socket'])
            || ! empty($connection['prefix'])
            || ($connection['database'] ?? null) !== $expectedDatabase
            || ($connection['username'] ?? null) !== $expectedDatabase
            || empty($connection['password'])
            || $decodedKey === false || strlen($decodedKey) !== 32
            || config('session.table') !== 'sessions'
            || ! in_array(config('session.connection'), [null, 'mariadb'], true)
            || (in_array($environment, ['local', 'development'], true) && (config('session.driver') !== 'database'
                || config('session.encrypt') !== true || config('session.secure') !== true))
            || ($environment === 'development' && (config('app.url') !== 'https://dev-inventory.tabletopgaymers.org'
                || config('session.http_only') !== true
                || ! in_array(config('session.same_site'), ['lax', 'strict'], true)
                || config('session.domain') !== null
                || config('session.path') !== '/'))) {
            return $result;
        }

        try {
            $database = DB::connection('mariadb');
            $probe = $database->selectOne('SELECT 1 AS probe, DATABASE() AS database_name, VERSION() AS version');
            if ((int) $probe->probe !== 1 || $probe->database_name !== $expectedDatabase
                || ! preg_match('/^10\.11\.\d+-MariaDB/', $probe->version)) {
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
        $known = array_diff($tables, ['migrations', 'sessions']) === [];
        $sessions = in_array('sessions', $tables, true);
        $ledger = in_array('migrations', $tables, true);
        $migration = '2026_10_05_000000_create_sessions_table';
        $recorded = $ledger && $database->table('migrations')->where('migration', $migration)->exists();
        $columns = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'];
        $compatible = $sessions && array_diff($columns, $schema->getColumnListing('sessions')) === [];

        return [
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
