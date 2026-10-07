<?php

namespace App\Support;

/** Exact additive Phase 8 contract; absent is compatible, partial is never ready. */
class EventSchema
{
    public const MIGRATION = '2026_10_07_020000_create_event_tables';

    public const TABLES = ['event_workflows', 'event_lines', 'event_operations', 'event_stock_entries'];

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
        foreach ($this->contract() as $table => $expected) {
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
            if ($primary !== ['id'] || $unique !== $expected['unique']) {
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

    public function contract(): array
    {
        $id = ['bigint unsigned', false];
        $text = ['longtext', false];

        return [
            'event_workflows' => ['columns' => ['id' => $id, 'source_id' => ['bigint unsigned', true], 'name' => ['varchar(255)', false], 'status' => ['varchar(10)', false], 'revision' => ['int unsigned', false], 'data' => $text],
                'unique' => ['id', 'source_id'], 'foreign' => ['source_id:inventory_sources:id']],
            'event_lines' => ['columns' => ['id' => $id, 'event_id' => $id, 'item_id' => $id, 'brought' => ['int unsigned', false], 'remaining' => ['int unsigned', true], 'data' => $text],
                'unique' => ['id', 'event_id,item_id'], 'foreign' => ['event_id:event_workflows:id', 'item_id:items:id']],
            'event_operations' => ['columns' => ['id' => $id, 'operation_id' => ['char(36)', false], 'event_id' => $id, 'actor_id' => $id, 'actor_name' => ['varchar(255)', false], 'action' => ['varchar(30)', false], 'data' => $text, 'posted_at' => ['timestamp', false]],
                'unique' => ['id', 'operation_id'], 'foreign' => ['event_id:event_workflows:id', 'actor_id:users:id']],
            'event_stock_entries' => ['columns' => ['id' => $id, 'operation_id' => $id, 'event_id' => $id, 'item_id' => $id, 'leg' => ['varchar(40)', false], 'item_name' => ['varchar(255)', false], 'item_sku' => ['varchar(101)', false], 'description' => ['varchar(600)', false], 'quantity_change' => ['int', false], 'unit_cost' => ['decimal(24,12)', false]],
                'unique' => ['id', 'operation_id,item_id,leg'], 'foreign' => ['operation_id:event_operations:id', 'event_id:event_workflows:id', 'item_id:items:id']],
        ];
    }
}
