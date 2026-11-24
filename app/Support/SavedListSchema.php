<?php

namespace App\Support;

class SavedListSchema
{
    public const TABLES = ['saved_lists', 'saved_list_items'];

    /**
     * Saved Lists phase audit used by BaselineProbe to assess the recorded migration and live table contract.
     *
     * @internal
     * @example app/Support/BaselineProbe.php
     */
    public function state($database, array $tables): array
    {
        $present = array_intersect(self::TABLES, $tables);
        $recorded = in_array('migrations', $tables, true) && $database->table('migrations')
            ->where('migration', '2026_11_22_000000_create_saved_lists')->exists();
        if ($present === []) {
            return ['compatible' => ! $recorded, 'ready' => false];
        }
        if (count($present) !== 2 || ! $recorded) {
            return ['compatible' => false, 'ready' => false];
        }
        $schema = $database->getSchemaBuilder();
        $hasStatus = $database->table('migrations')->where('migration', '2026_11_22_000100_add_saved_list_status')->exists();
        $hasShared = $database->table('migrations')->where('migration', '2026_11_22_000200_add_saved_list_shared')->exists();
        foreach (['saved_lists' => array_merge(['id', 'name', 'owner_id', 'description', 'storage_location_id', 'created_at', 'updated_at'], $hasStatus ? ['status'] : [], $hasShared ? ['shared'] : []),
            'saved_list_items' => ['saved_list_id', 'item_id']] as $table => $expected) {
            $actual = $schema->getColumnListing($table);
            sort($expected); sort($actual);
            if ($actual !== $expected) return ['compatible' => false, 'ready' => false];
        }

        return ['compatible' => true, 'ready' => true];
    }
}
