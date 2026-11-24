<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class RequestCatalog
{
    /**
     * Returns only matched item IDs, permitting inactive retained items. Adds optional source and destination storage quantities with zero for a missing balance, or null when no location was supplied.
     *
     * Scope: Domain-specific (Purchase, Relocation and Events)
     * Task: Load display rows for selected catalog items
     * @example app/Http/Controllers/RelocationController.php
     */
    public function rows(array $ids, ?int $source = null, ?int $destination = null, bool $naturalNames = false): array
    {
        $criteria = app(InventorySearch::class)->defaults();
        $criteria['include_inactive'] = true;
        $rows = app(InventorySearch::class)->results($criteria, $naturalNames)['rows'];
        $result = [];
        foreach ($rows as $row) {
            if (! in_array($row['id'], $ids, true)) {
                continue;
            }
            $row['source'] = $source === null ? null : ($row['values']['storage:'.$source] ?? 0);
            $row['destination'] = $destination === null ? null : ($row['values']['storage:'.$destination] ?? 0);
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Applies inventory search criteria, limits matches to active items, drops already selected IDs, and returns the same stock row shape as rows.
     *
     * Scope: Domain-specific (Relocation)
     * Task: Find available catalog selections
     * @example app/Http/Controllers/RelocationController.php
     */
    public function selections(array $criteria, array $excluded, ?int $source, ?int $destination, bool $naturalNames = false): array
    {
        $active = app(CatalogRecords::class)->records('items')->where('state', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $results = app(InventorySearch::class)->results($criteria)['rows'];
        $ids = array_values(array_filter(array_column($results, 'id'), fn ($id) => in_array($id, $active, true) && ! in_array($id, $excluded, true)));

        return $this->rows($ids, $source, $destination, $naturalNames);
    }

    /**
     * Keeps line values and item keys, with lines absent from the catalog result appended in their original order.
     *
     * Scope: Domain-specific (Events)
     * Task: Order event lines by catalog display order
     * @example app/Http/Controllers/EventController.php
     */
    public function sortItemLines(array $lines): array
    {
        $sorted = [];
        foreach ($this->rows(array_map('intval', array_keys($lines)), naturalNames: true) as $item) {
            $sorted[$item['id']] = $lines[$item['id']];
        }

        return $sorted + $lines;
    }

    /**
     * Returns locations, suppliers, items and collections. Applies optional natural name sorting to items. Entry points decide which records are eligible for each action.
     *
     * Scope: Domain-specific (Purchase, Relocation and Events)
     * Task: Load catalog choices for request forms
     * @example app/Http/Controllers/EventController.php
     */
    public function choices(bool $naturalNames = false): array
    {
        return ['locations' => app(CatalogRecords::class)->records('storage_locations'),
            'suppliers' => app(CatalogRecords::class)->records('suppliers'),
            'items' => app(CatalogRecords::class)->records('items', $naturalNames),
            'collections' => DB::table('collections')->join('categories', 'categories.id', '=', 'collections.category_id')->select('collections.*', 'categories.name as category_name')->orderBy('categories.name')->orderBy('collections.name')->get()];
    }
}
