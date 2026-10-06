<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventorySearch
{
    public function defaults(): array
    {
        return ['search' => '', 'collections' => [], 'columns' => [], 'include_inactive' => false];
    }

    public function columns(): array
    {
        $columns = [];
        foreach (app(CatalogRecords::class)->records('storage_locations') as $location) {
            $columns['storage:'.$location->id] = ['name' => $location->name, 'kind' => 'storage', 'id' => (int) $location->id, 'state' => $location->state];
        }
        foreach (DB::table('inventory_sources')->where('kind', 'event')->where('active', true)->orderBy('name')->get() as $source) {
            $columns['event:'.$source->id] = ['name' => $source->name, 'kind' => 'event', 'id' => (int) $source->id];
        }
        $columns['transit'] = ['name' => 'In Transit', 'kind' => 'transit'];
        $columns['ordered'] = ['name' => 'Ordered', 'kind' => 'ordered'];

        return $columns;
    }

    public function criteria(Request $request): array
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'collections' => ['nullable', 'array'], 'collections.*' => ['integer', 'distinct', Rule::in(DB::table('collections')->pluck('id')->all())],
            'columns' => ['nullable', 'array'], 'columns.*' => ['string', 'distinct', Rule::in(array_keys($this->columns()))],
            'include_inactive' => ['nullable', 'boolean']]);
        $collections = array_map('intval', $data['collections'] ?? []);
        sort($collections);
        $columns = $data['columns'] ?? [];
        sort($columns);

        return ['search' => trim($data['search'] ?? ''), 'collections' => $collections, 'columns' => $columns, 'include_inactive' => (bool) ($data['include_inactive'] ?? false)];
    }

    public function remembered(int $user): array
    {
        $saved = json_decode(DB::table('inventory_preferences')->where('user_id', $user)->value('criteria') ?? '{}', true);
        if (! is_array($saved)) {
            return $this->defaults();
        }
        $validCollections = DB::table('collections')->pluck('id')->all();

        return ['search' => is_string($saved['search'] ?? null) ? mb_substr($saved['search'], 0, 255) : '',
            'collections' => array_values(array_intersect(is_array($saved['collections'] ?? null) ? $saved['collections'] : [], $validCollections)),
            'columns' => array_values(array_intersect(is_array($saved['columns'] ?? null) ? $saved['columns'] : [], array_keys($this->columns()))),
            'include_inactive' => ($saved['include_inactive'] ?? false) === true];
    }

    public function remember(int $user, array $criteria): void
    {
        DB::table('inventory_preferences')->updateOrInsert(['user_id' => $user], ['criteria' => json_encode($criteria, JSON_THROW_ON_ERROR), 'updated_at' => now('UTC')]);
    }

    public function results(array $criteria): array
    {
        $items = DB::table('items')->join('collections', 'collections.id', '=', 'items.collection_id')->join('categories', 'categories.id', '=', 'collections.category_id')
            ->leftJoin('catalog_metadata as metadata', fn ($join) => $join->on('metadata.record_id', '=', 'items.id')->where('metadata.kind', 'items'))
            ->select('items.*', 'collections.name as collection_name', 'categories.name as category_name', DB::raw("COALESCE(metadata.state, 'active') as state"));
        if ($criteria['search'] !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $criteria['search']);
            $items->where(function ($query) use ($escaped) {
                foreach (['items.name', 'collections.name', 'categories.name'] as $field) {
                    $query->orWhere($field, 'like', '%'.$escaped.'%');
                }
            });
        }
        if ($criteria['collections']) {
            $items->whereIn('items.collection_id', $criteria['collections']);
        }
        $items = $items->orderBy('categories.name')->orderBy('collections.name')->orderBy('items.name')->orderBy('items.id')->get();
        $ids = $items->pluck('id')->all();
        $balances = DB::table('inventory_balances')->whereIn('item_id', $ids)->get()->groupBy('item_id');
        $sources = DB::table('inventory_source_balances')->join('inventory_sources', 'inventory_sources.id', '=', 'inventory_source_balances.source_id')
            ->whereIn('item_id', $ids)->whereIn('kind', ['event', 'transit', 'ordered'])->where('active', true)
            ->select('inventory_source_balances.*', 'inventory_sources.kind')->get()->groupBy('item_id');
        $allColumns = $this->columns();
        $rows = [];
        foreach ($items as $item) {
            $values = array_fill_keys(array_keys($allColumns), 0);
            $hasStock = false;
            $available = 0;
            foreach ($balances[$item->id] ?? [] as $balance) {
                $values['storage:'.$balance->storage_location_id] = (int) $balance->quantity;
                $available += (int) $balance->quantity;
                $hasStock = $hasStock || (int) $balance->quantity !== 0;
            }
            foreach ($sources[$item->id] ?? [] as $balance) {
                $key = $balance->kind === 'event' ? 'event:'.$balance->source_id : $balance->kind;
                $values[$key] += (int) $balance->quantity;
                $hasStock = $hasStock || (int) $balance->quantity !== 0;
            }
            if ($item->state !== 'active' && ! $criteria['include_inactive'] && ! $hasStock) {
                continue;
            }
            $rows[] = ['id' => (int) $item->id, 'name' => $item->name, 'category' => $item->category_name, 'collection' => $item->collection_name, 'sku' => $item->sku, 'available' => $available, 'values' => $values];
        }
        $columns = array_filter($allColumns, function ($column, $key) use ($criteria, $rows) {
            if ($criteria['columns']) {
                return in_array($key, $criteria['columns'], true);
            }
            foreach ($rows as $row) {
                if ($row['values'][$key] !== 0) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);

        return ['rows' => $rows, 'columns' => $columns];
    }
}
