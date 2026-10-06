<?php

namespace App\Support;

/** Read-only complete Phase 6 intake recovery contract. No installer in this bridge. */
class RequestSchema
{
    public const MIGRATION = '2026_10_06_030000_create_request_intake_tables';

    public const TABLES = ['purchase_requests', 'purchase_request_lines', 'purchase_request_notes', 'purchase_request_activity', 'relocation_requests', 'relocation_request_lines', 'relocation_request_activity'];

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
        $optionalId = ['bigint unsigned', true];
        $text = ['text', true];
        $timestamps = ['created_at' => ['timestamp', true], 'updated_at' => ['timestamp', true]];
        $activity = ['actor_id' => $id, 'actor_name' => ['varchar(255)', false], 'action' => ['varchar(50)', false], 'previous' => $text, 'current' => $text, 'occurred_at' => ['timestamp', false]];
        $contract = [
            'purchase_requests' => [
                'columns' => ['id' => $id, 'owner_id' => $id, 'title' => ['varchar(255)', true], 'suggested_merchant' => ['varchar(255)', true], 'details' => $text, 'status' => ['varchar(20)', false], 'revision' => ['int unsigned', false], 'supplier_id' => $optionalId, 'receiving_location_id' => $optionalId, 'planned_date' => ['date', true], 'preparation_notes' => $text] + $timestamps,
                'primary' => ['id'], 'unique' => ['id'], 'foreign' => ['owner_id:users:id', 'supplier_id:catalog_references:id', 'receiving_location_id:storage_locations:id'],
            ],
            'purchase_request_lines' => [
                'columns' => ['id' => $id, 'purchase_request_id' => $id, 'item_id' => $id, 'quantity' => ['int unsigned', false], 'estimate' => ['decimal(24,12)', true], 'note' => $text],
                'primary' => ['id'], 'unique' => ['id', 'purchase_request_id,item_id'], 'foreign' => ['purchase_request_id:purchase_requests:id', 'item_id:items:id'],
            ],
            'purchase_request_notes' => [
                'columns' => ['id' => $id, 'purchase_request_id' => $id, 'actor_id' => $id, 'actor_name' => ['varchar(255)', false], 'body' => ['text', false], 'occurred_at' => ['timestamp', false]],
                'primary' => ['id'], 'unique' => ['id'], 'foreign' => ['purchase_request_id:purchase_requests:id', 'actor_id:users:id'],
            ],
            'purchase_request_activity' => [
                'columns' => ['id' => $id, 'purchase_request_id' => $id] + $activity,
                'primary' => ['id'], 'unique' => ['id'], 'foreign' => ['purchase_request_id:purchase_requests:id', 'actor_id:users:id'],
            ],
            'relocation_requests' => [
                'columns' => ['id' => $id, 'owner_id' => $id, 'title' => ['varchar(255)', false], 'details' => $text, 'source_location_id' => $optionalId, 'destination_location_id' => $optionalId, 'status' => ['varchar(20)', false], 'revision' => ['int unsigned', false]] + $timestamps,
                'primary' => ['id'], 'unique' => ['id'], 'foreign' => ['owner_id:users:id', 'source_location_id:storage_locations:id', 'destination_location_id:storage_locations:id'],
            ],
            'relocation_request_lines' => [
                'columns' => ['id' => $id, 'relocation_request_id' => $id, 'item_id' => $id, 'quantity' => ['int unsigned', false], 'fulfillment_quantity' => ['int unsigned', true]],
                'primary' => ['id'], 'unique' => ['id', 'relocation_request_id,item_id'], 'foreign' => ['relocation_request_id:relocation_requests:id', 'item_id:items:id'],
            ],
            'relocation_request_activity' => [
                'columns' => ['id' => $id, 'relocation_request_id' => $id] + $activity,
                'primary' => ['id'], 'unique' => ['id'], 'foreign' => ['relocation_request_id:relocation_requests:id', 'actor_id:users:id'],
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
