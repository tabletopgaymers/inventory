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
            'message' => 'Local configuration needs attention. Run the documented local checks.',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => Application::VERSION,
            'databaseVersion' => null,
        ];
        $environment = app()->environment();
        $expectedDatabase = $environment === 'testing' ? 'tg_inventory_test' : 'tg_inventory_local';
        $connection = config('database.connections.mariadb');
        $key = config('app.key', '');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if (! in_array($environment, ['local', 'testing'], true)
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
            || ($environment === 'local' && (config('session.driver') !== 'database'
                || ! config('session.encrypt') || ! config('session.secure')))) {
            return $result;
        }

        try {
            $database = DB::connection('mariadb');
            $probe = $database->selectOne('SELECT 1 AS probe, DATABASE() AS database_name, VERSION() AS version');
            if ((int) $probe->probe !== 1 || $probe->database_name !== $expectedDatabase
                || ! preg_match('/^10\.11\.\d+-MariaDB/', $probe->version)) {
                return $result;
            }
            if ($requireSessions && ! $database->getSchemaBuilder()->hasTable('sessions')) {
                $result['message'] = 'Session setup is incomplete. Run the documented preparation step.';

                return $result;
            }
            $result['ready'] = true;
            $result['message'] = 'Database check passed.';
            $result['databaseVersion'] = explode('-MariaDB', $probe->version)[0];
        } catch (Throwable) {
            $result['message'] = 'Database check failed. Check the local service and private configuration.';
        }

        return $result;
    }
}
