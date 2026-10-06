<?php

namespace App\Support;

/** Additive Phase 5 contract; the accepted stock tables remain unchanged. */
class CatalogSchema
{
    public const MIGRATION = '2026_10_06_010000_create_catalog_tables';

    public const TABLES = ['catalog_metadata', 'catalog_names', 'catalog_references', 'item_metadata', 'item_programs', 'inventory_sources', 'inventory_source_balances', 'inventory_preferences'];

    public function state($database, array $tables): array
    {
        $present = array_intersect(self::TABLES, $tables);
        $recorded = in_array('migrations', $tables, true) && $database->table('migrations')->where('migration', self::MIGRATION)->exists();
        if ($present === []) {
            return ['compatible' => ! $recorded, 'ready' => false];
        }
        if (count($present) !== count(self::TABLES) || ! $recorded) {
            return ['compatible' => false, 'ready' => false];
        }
        $schema = $database->getSchemaBuilder();
        foreach ($this->columns() as $table => $columns) {
            $actual = [];
            foreach ($schema->getColumns($table) as $column) {
                $actual[$column['name']] = [preg_replace('/\b(bigint|int)\(\d+\)/', '$1', strtolower($column['type'])), $column['nullable']];
                if ($column['generation'] !== null || $column['auto_increment'] !== ($column['name'] === 'id')) {
                    return ['compatible' => false, 'ready' => false];
                }
            }
            if ($actual != $columns) {
                return ['compatible' => false, 'ready' => false];
            }
            $unique = [];
            foreach ($schema->getIndexes($table) as $index) {
                if ($index['unique']) {
                    $unique[] = implode(',', $index['columns']);
                }
            }
            $primary = array_values(array_filter($schema->getIndexes($table), fn ($index) => $index['primary']));
            $expectedPrimary = match ($table) {
                'item_metadata' => ['item_id'],
                'item_programs' => ['item_id', 'program_id'],
                'inventory_preferences' => ['user_id'],
                default => ['id'],
            };
            if (count($primary) !== 1 || $primary[0]['columns'] !== $expectedPrimary) {
                return ['compatible' => false, 'ready' => false];
            }
            sort($unique);
            $required = $this->unique()[$table];
            sort($required);
            if ($unique !== $required) {
                return ['compatible' => false, 'ready' => false];
            }
            $foreign = [];
            foreach ($schema->getForeignKeys($table) as $key) {
                if ($key['foreign_schema'] !== $database->getDatabaseName() || ! in_array($key['on_delete'], ['restrict', 'no action'], true) || ! in_array($key['on_update'], ['restrict', 'no action'], true)) {
                    return ['compatible' => false, 'ready' => false];
                }
                $foreign[] = implode(',', $key['columns']).':'.$key['foreign_table'].':'.implode(',', $key['foreign_columns']);
            }
            sort($foreign);
            $required = $this->foreign()[$table];
            sort($required);
            if ($foreign !== $required) {
                return ['compatible' => false, 'ready' => false];
            }
        }
        foreach ($database->select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') as $table) {
            if (in_array($table->name, self::TABLES, true) && strtolower($table->engine ?? '') !== 'innodb') {
                return ['compatible' => false, 'ready' => false];
            }
        }

        return ['compatible' => true, 'ready' => true];
    }

    private function columns(): array
    {
        $id = ['bigint unsigned', false];
        $text = ['varchar(255)', true];
        $timestamps = ['created_at' => ['timestamp', true], 'updated_at' => ['timestamp', true]];

        return [
            'catalog_metadata' => ['id' => $id, 'kind' => ['varchar(30)', false], 'record_id' => $id, 'state' => ['varchar(10)', false], 'description' => ['text', true], 'notes' => ['text', true]] + $timestamps,
            'catalog_names' => ['id' => $id, 'kind' => ['varchar(30)', false], 'parent_id' => $id, 'name' => ['varchar(255)', false], 'record_id' => $id],
            'catalog_references' => ['id' => $id, 'kind' => ['varchar(30)', false], 'name' => ['varchar(255)', false], 'state' => ['varchar(10)', false], 'contact_name' => $text, 'email' => $text, 'phone' => $text, 'website' => $text, 'address' => ['text', true], 'notes' => ['text', true]] + $timestamps,
            'item_metadata' => ['item_id' => $id, 'variety' => $text, 'purpose_id' => ['bigint unsigned', true], 'bundle_type' => $text, 'bundle_quantity' => ['int unsigned', true], 'irs_fmv' => ['decimal(24,12)', true], 'in_person_ask' => ['decimal(24,12)', true], 'online_ask' => ['decimal(24,12)', true], 'notes' => ['text', true]] + $timestamps,
            'item_programs' => ['item_id' => $id, 'program_id' => $id],
            'inventory_sources' => ['id' => $id, 'kind' => ['varchar(10)', false], 'name' => ['varchar(255)', false], 'active' => ['tinyint(1)', false]] + $timestamps,
            'inventory_source_balances' => ['id' => $id, 'item_id' => $id, 'source_id' => $id, 'quantity' => ['int', false]] + $timestamps,
            'inventory_preferences' => ['user_id' => $id, 'criteria' => ['text', false]] + $timestamps,
        ];
    }

    private function unique(): array
    {
        return ['catalog_metadata' => ['id', 'kind,record_id'], 'catalog_names' => ['id', 'kind,parent_id,name', 'kind,record_id'], 'catalog_references' => ['id', 'kind,name'], 'item_metadata' => ['item_id'], 'item_programs' => ['item_id,program_id'], 'inventory_sources' => ['id', 'kind,name'], 'inventory_source_balances' => ['id', 'item_id,source_id'], 'inventory_preferences' => ['user_id']];
    }

    private function foreign(): array
    {
        return ['catalog_metadata' => [], 'catalog_names' => [], 'catalog_references' => [], 'item_metadata' => ['item_id:items:id', 'purpose_id:catalog_references:id'], 'item_programs' => ['item_id:items:id', 'program_id:catalog_references:id'], 'inventory_sources' => [], 'inventory_source_balances' => ['item_id:items:id', 'source_id:inventory_sources:id'], 'inventory_preferences' => ['user_id:users:id']];
    }
}
