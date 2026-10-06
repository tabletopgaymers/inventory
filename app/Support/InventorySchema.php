<?php

namespace App\Support;

/** Read-only contract shared by the recovery bridge and the later correction release. */
class InventorySchema
{
    public const MIGRATION = '2026_10_06_000000_create_inventory_tables';

    public const TABLES = ['categories', 'collections', 'items', 'storage_locations', 'inventory_balances', 'inventory_adjustments', 'inventory_adjustment_entries'];

    public function state($database, array $tables): array
    {
        $present = array_intersect(self::TABLES, $tables);
        $recorded = in_array('migrations', $tables, true)
            && $database->table('migrations')->where('migration', self::MIGRATION)->exists();
        if ($present === []) {
            return ['compatible' => ! $recorded, 'ready' => false];
        }
        if (count($present) !== count(self::TABLES) || ! $recorded) {
            return ['compatible' => false, 'ready' => false];
        }
        $schema = $database->getSchemaBuilder();
        foreach ($this->contract() as $table => $expected) {
            $actual = [];
            foreach ($schema->getColumns($table) as $column) {
                $actual[$column['name']] = [
                    preg_replace('/\b(bigint|int)\(\d+\)/', '$1', strtolower($column['type'])),
                    $column['nullable'], $column['auto_increment'],
                ];
                if ($column['generation'] !== null) {
                    return ['compatible' => false, 'ready' => false];
                }
            }
            if ($actual != $expected['columns']) {
                return ['compatible' => false, 'ready' => false];
            }
            $unique = [];
            $primary = false;
            foreach ($schema->getIndexes($table) as $index) {
                $primary = $primary || ($index['primary'] && $index['columns'] === ['id']);
                if ($index['unique']) {
                    $unique[] = implode(',', $index['columns']);
                }
            }
            sort($unique);
            $requiredUnique = $expected['unique'];
            sort($requiredUnique);
            if (! $primary || $unique !== $requiredUnique) {
                return ['compatible' => false, 'ready' => false];
            }
            $foreign = [];
            foreach ($schema->getForeignKeys($table) as $key) {
                if ($key['foreign_schema'] !== $database->getDatabaseName()
                    || ! in_array($key['on_delete'], ['restrict', 'no action'], true)
                    || ! in_array($key['on_update'], ['restrict', 'no action'], true)) {
                    return ['compatible' => false, 'ready' => false];
                }
                $foreign[] = implode(',', $key['columns']).':'.$key['foreign_table'].':'.implode(',', $key['foreign_columns']);
            }
            sort($foreign);
            $requiredForeign = $expected['foreign'];
            sort($requiredForeign);
            if ($foreign !== $requiredForeign) {
                return ['compatible' => false, 'ready' => false];
            }
        }
        $engines = $database->select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        foreach ($engines as $table) {
            if (in_array($table->name, self::TABLES, true) && strtolower($table->engine ?? '') !== 'innodb') {
                return ['compatible' => false, 'ready' => false];
            }
        }

        return ['compatible' => true, 'ready' => true];
    }

    private function contract(): array
    {
        $id = ['bigint unsigned', false, true];
        $reference = ['bigint unsigned', false, false];
        $name = ['varchar(255)', false, false];
        $quantity = ['int', false, false];
        $cost = ['decimal(24,12)', false, false];
        $timestamps = ['created_at' => ['timestamp', true, false], 'updated_at' => ['timestamp', true, false]];

        return [
            'categories' => ['columns' => ['id' => $id, 'name' => $name] + $timestamps, 'unique' => ['id'], 'foreign' => []],
            'collections' => ['columns' => ['id' => $id, 'category_id' => $reference, 'name' => $name, 'sku_prefix' => ['varchar(50)', false, false]] + $timestamps, 'unique' => ['id'], 'foreign' => ['category_id:categories:id']],
            'items' => ['columns' => ['id' => $id, 'collection_id' => $reference, 'name' => $name, 'sku_suffix' => ['varchar(50)', false, false], 'sku' => ['varchar(101)', false, false], 'unit_cost' => $cost] + $timestamps, 'unique' => ['id', 'sku'], 'foreign' => ['collection_id:collections:id']],
            'storage_locations' => ['columns' => ['id' => $id, 'name' => $name, 'is_central' => ['tinyint(1)', false, false]] + $timestamps, 'unique' => ['id'], 'foreign' => []],
            'inventory_balances' => ['columns' => ['id' => $id, 'item_id' => $reference, 'storage_location_id' => $reference, 'quantity' => $quantity] + $timestamps, 'unique' => ['id', 'item_id,storage_location_id'], 'foreign' => ['item_id:items:id', 'storage_location_id:storage_locations:id']],
            'inventory_adjustments' => ['columns' => ['id' => $id, 'operation_id' => ['char(36)', false, false], 'actor_id' => $reference, 'item_id' => $reference, 'actor_name' => $name, 'item_name' => $name, 'item_sku' => ['varchar(101)', false, false], 'posted_at' => ['timestamp', false, false]], 'unique' => ['id', 'operation_id'], 'foreign' => ['actor_id:users:id', 'item_id:items:id']],
            'inventory_adjustment_entries' => ['columns' => ['id' => $id, 'inventory_adjustment_id' => $reference, 'storage_location_id' => $reference, 'location_name' => $name, 'description' => ['varchar(300)', false, false], 'before_quantity' => $quantity, 'after_quantity' => $quantity, 'quantity_change' => $quantity, 'unit_cost' => $cost, 'rationale' => ['varchar(1000)', true, false]], 'unique' => ['id', 'inventory_adjustment_id,storage_location_id'], 'foreign' => ['inventory_adjustment_id:inventory_adjustments:id', 'storage_location_id:storage_locations:id']],
        ];
    }
}
