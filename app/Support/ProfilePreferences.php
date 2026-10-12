<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProfilePreferences
{
    public const MIGRATION = '2026_10_07_000000_add_user_time_zone';
    public const CONTACT_MIGRATION = '2026_10_11_000000_add_user_contact_preferences';

    public function state($database, ?array $tables = null): array
    {
        $schema = $database->getSchemaBuilder();
        $tables ??= $schema->getTableListing(null, false);
        $recorded = in_array('migrations', $tables, true) && $database->table('migrations')->where('migration', self::MIGRATION)->exists();
        $columns = in_array('users', $tables, true) ? collect($schema->getColumns('users'))->keyBy('name') : collect();
        $column = $columns->get('time_zone');
        $timeReady = $recorded && $column !== null && strtolower($column['type']) === 'varchar(100)' && $column['nullable'] && ! $column['auto_increment'] && in_array($column['default'], [null, 'NULL'], true) && $column['generation'] === null;
        $timeCompatible = (! $recorded && $column === null) || $timeReady;
        $contactRecorded = in_array('migrations', $tables, true) && $database->table('migrations')->where('migration', self::CONTACT_MIGRATION)->exists();
        $expected = ['personal_email' => 'varchar(255)', 'mobile_number' => 'varchar(100)', 'mailing_address' => 'text', 'display_density' => 'varchar(16)'];
        $contactPresent = $columns->only(array_keys($expected));
        $contactReady = $contactRecorded && $contactPresent->count() === count($expected);
        foreach ($expected as $name => $type) {
            $field = $columns->get($name);
            $default = $name === 'display_density' ? ['standard', "'standard'"] : [null, 'NULL'];
            $contactReady = $contactReady && $field !== null && strtolower($field['type']) === $type
                && $field['nullable'] === ($name !== 'display_density') && ! $field['auto_increment']
                && in_array($field['default'], $default, true) && $field['generation'] === null;
        }

        return ['compatible' => $timeCompatible && ((! $contactRecorded && $contactPresent->isEmpty()) || ($timeReady && $contactReady)),
            'ready' => $timeReady && $contactReady];
    }

    public function prepare(bool $hosted): bool
    {
        $probe = app(BaselineProbe::class);
        $database = DB::connection();
        if (! $probe->inspect()['ready'] || ! $probe->schemaState($database)['authenticationReady']
            || ! $this->state($database)['compatible'] || (app()->environment('development') && ! $hosted)) {
            return false;
        }

        foreach ([self::MIGRATION, self::CONTACT_MIGRATION] as $migration) {
            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.$migration.'.php']) !== 0) {
                return false;
            }
        }

        return $this->state($database)['ready'];
    }
}
