<?php

namespace App\Support;

/** Read-only future Phase 5 count/cost recovery contract. No installer in checkpoint 1. */
class DailyInventorySchema
{
    public const MIGRATION = '2026_10_06_020000_create_daily_inventory_tables';

    public const TABLES = ['inventory_count_operations', 'inventory_count_items', 'item_cost_entries'];

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
        $id = ['bigint unsigned', false];
        $name = ['varchar(255)', false];
        $cost = ['decimal(24,12)', false];
        $contract = [
            'inventory_count_operations' => [
                'columns' => ['id' => $id, 'operation_id' => ['char(36)', false], 'actor_id' => $id, 'storage_location_id' => $id, 'posted_at' => ['timestamp', false]],
                'primary' => ['id'], 'unique' => ['id', 'operation_id'], 'foreign' => ['actor_id:users:id', 'storage_location_id:storage_locations:id'],
            ],
            'inventory_count_items' => [
                'columns' => ['inventory_count_operation_id' => $id, 'item_id' => $id, 'inventory_adjustment_id' => $id],
                'primary' => ['inventory_count_operation_id', 'item_id'], 'unique' => ['inventory_count_operation_id,item_id', 'inventory_adjustment_id'],
                'foreign' => ['inventory_count_operation_id:inventory_count_operations:id', 'item_id:items:id', 'inventory_adjustment_id:inventory_adjustments:id'],
            ],
            'item_cost_entries' => [
                'columns' => ['id' => $id, 'operation_id' => ['char(36)', false], 'actor_id' => $id, 'item_id' => $id, 'actor_name' => $name, 'item_name' => $name, 'item_sku' => ['varchar(101)', false], 'before_cost' => $cost, 'after_cost' => $cost, 'rationale' => ['varchar(1000)', true], 'posted_at' => ['timestamp', false]],
                'primary' => ['id'], 'unique' => ['id', 'operation_id'], 'foreign' => ['actor_id:users:id', 'item_id:items:id'],
            ],
        ];
        foreach ($contract as $table => $expected) {
            $actual = [];
            foreach ($schema->getColumns($table) as $column) {
                $actual[$column['name']] = [preg_replace('/\b(bigint|int)\(\d+\)/', '$1', strtolower($column['type'])), $column['nullable']];
                if ($column['generation'] !== null || $column['auto_increment'] !== ($column['name'] === 'id')) {
                    return ['compatible' => false, 'ready' => false];
                }
            }
            if ($actual != $expected['columns']) {
                return ['compatible' => false, 'ready' => false];
            }
            $unique = [];
            $primary = [];
            foreach ($schema->getIndexes($table) as $index) {
                if ($index['unique']) {
                    $unique[] = implode(',', $index['columns']);
                }
                if ($index['primary']) {
                    $primary = $index['columns'];
                }
            }
            sort($unique);
            sort($expected['unique']);
            if ($primary !== $expected['primary'] || $unique !== $expected['unique']) {
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
            sort($expected['foreign']);
            if ($foreign !== $expected['foreign']) {
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
}
