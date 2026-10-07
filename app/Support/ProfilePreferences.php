<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProfilePreferences
{
    public const MIGRATION = '2026_10_07_000000_add_user_time_zone';

    public function state($database, ?array $tables = null): array
    {
        $schema = $database->getSchemaBuilder();
        $tables ??= $schema->getTableListing(null, false);
        $recorded = in_array('migrations', $tables, true) && $database->table('migrations')->where('migration', self::MIGRATION)->exists();
        $column = in_array('users', $tables, true) ? collect($schema->getColumns('users'))->firstWhere('name', 'time_zone') : null;
        if (! $recorded && $column === null) {
            return ['compatible' => true, 'ready' => false];
        }
        $ready = $recorded && $column !== null && strtolower($column['type']) === 'varchar(100)' && $column['nullable'] && ! $column['auto_increment'] && in_array($column['default'], [null, 'NULL'], true) && $column['generation'] === null;

        return ['compatible' => $ready, 'ready' => $ready];
    }

    public function prepare(bool $hosted): bool
    {
        $probe = app(BaselineProbe::class);
        $database = DB::connection();
        if (! $probe->inspect()['ready'] || ! $probe->schemaState($database)['authenticationReady']
            || ! $this->state($database)['compatible'] || (app()->environment('development') && ! $hosted)) {
            return false;
        }

        return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.self::MIGRATION.'.php']) === 0
            && $this->state($database)['ready'];
    }
}
