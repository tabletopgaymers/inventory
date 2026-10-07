<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class RequestCatalog
{
    public function rows(array $ids, ?int $source = null, ?int $destination = null): array
    {
        $criteria = app(InventorySearch::class)->defaults();
        $criteria['include_inactive'] = true;
        $rows = app(InventorySearch::class)->results($criteria)['rows'];
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

    public function selections(array $criteria, array $excluded, ?int $source, ?int $destination): array
    {
        $active = app(CatalogRecords::class)->records('items')->where('state', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $results = app(InventorySearch::class)->results($criteria)['rows'];
        $ids = array_values(array_filter(array_column($results, 'id'), fn ($id) => in_array($id, $active, true) && ! in_array($id, $excluded, true)));

        return $this->rows($ids, $source, $destination);
    }

    public function choices(): array
    {
        return ['locations' => app(CatalogRecords::class)->records('storage_locations'),
            'suppliers' => app(CatalogRecords::class)->records('suppliers'),
            'items' => app(CatalogRecords::class)->records('items'),
            'collections' => DB::table('collections')->join('categories', 'categories.id', '=', 'collections.category_id')->select('collections.*', 'categories.name as category_name')->orderBy('categories.name')->orderBy('collections.name')->get()];
    }
}
