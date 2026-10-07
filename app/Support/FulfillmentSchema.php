<?php

namespace App\Support;

/** Exact additive Phase 7 contract; absent is compatible, partial is never ready. */
class FulfillmentSchema
{
    public const MIGRATION = '2026_10_07_010000_create_fulfillment_tables';

    public const TABLES = ['purchase_fulfillment', 'purchase_fulfillment_lines', 'relocation_fulfillment', 'relocation_fulfillment_lines', 'request_stock_entries'];

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
        $optionalId = ['bigint unsigned', true];
        $cost = ['decimal(24,12)', false];
        $contract = [];
        foreach (['purchase', 'relocation'] as $kind) {
            $request = $kind.'_request_id';
            $contract[$kind.'_fulfillment'] = ['columns' => ['id' => $id, $request => $id, 'data' => ['longtext', false]], 'unique' => ['id', $request], 'foreign' => [$request.':'.$kind.'_requests:id']];
            $fields = $kind === 'purchase'
                ? ['quantity' => ['int unsigned', false], 'unit' => $cost, 'cost' => $id, 'fee' => $id]
                : ['sent' => ['int unsigned', true], 'received' => ['int unsigned', true]];
            $contract[$kind.'_fulfillment_lines'] = ['columns' => ['id' => $id, $request => $id, 'item_id' => $id] + $fields, 'unique' => ['id', $request.',item_id'], 'foreign' => [$request.':'.$kind.'_requests:id', 'item_id:items:id']];
        }
        $contract['request_stock_entries'] = ['columns' => ['id' => $id, 'operation_id' => ['char(36)', false], 'purchase_request_id' => $optionalId, 'relocation_request_id' => $optionalId, 'item_id' => $id, 'actor_id' => $id,
            'actor_name' => ['varchar(255)', false], 'item_name' => ['varchar(255)', false], 'item_sku' => ['varchar(101)', false], 'entry_kind' => ['varchar(30)', false], 'description' => ['varchar(600)', false], 'quantity_change' => ['int', false], 'unit_cost' => $cost, 'explanation' => ['text', true], 'posted_at' => ['timestamp', false]],
            'unique' => ['id', 'operation_id,item_id,entry_kind'], 'foreign' => ['purchase_request_id:purchase_requests:id', 'relocation_request_id:relocation_requests:id', 'item_id:items:id', 'actor_id:users:id']];

        return $contract;
    }
}
