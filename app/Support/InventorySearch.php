<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventorySearch
{
    /**
     * Get neutral criteria for an unfiltered inventory search.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/InventoryBrowseController.php
     */
    public function defaults(): array
    {
        return ['search' => '', 'collections' => [], 'columns' => [], 'include_inactive' => false];
    }

    /**
     * List storage and available source columns by stable keys. Eligibility normally suppresses inactive empty storage locations.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/InventoryBrowseController.php
     */
    public function columns(bool $eligibleOnly = true): array
    {
        $columns = [];
        foreach (app(CatalogRecords::class)->records('storage_locations') as $location) {
            if ($eligibleOnly && $location->state !== 'active' && ! DB::table('inventory_balances')->where('storage_location_id', $location->id)->where('quantity', '<>', 0)->exists()) {
                continue;
            }
            $columns['storage:'.$location->id] = ['name' => $location->name, 'kind' => 'storage', 'id' => (int) $location->id, 'state' => $location->state];
        }
        foreach (DB::table('inventory_sources')->where('kind', 'event')->where('active', true)->orderBy('name')->get() as $source) {
            $columns['event:'.$source->id] = ['name' => $source->name, 'kind' => 'event', 'id' => (int) $source->id];
        }
        $columns['transit'] = ['name' => 'In Transit', 'kind' => 'transit'];
        $columns['ordered'] = ['name' => 'Ordered', 'kind' => 'ordered'];

        return $columns;
    }

    /**
     * Validate submitted search text, collection IDs, and column keys for Inventory.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/InventoryBrowseController.php
     */
    public function criteria(Request $request): array
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'collections' => ['nullable', 'array'], 'collections.*' => ['integer', 'distinct', Rule::in(DB::table('collections')->pluck('id')->all())],
            'columns' => ['nullable', 'array'], 'columns.*' => ['string', 'distinct', Rule::in(array_keys($this->columns()))],
            'include_inactive' => ['nullable', 'boolean']], ['columns.*.in' => 'A selected location or source is unavailable. Your saved criteria are retained; explicitly remove the unavailable selection before searching.']);
        $collections = array_map('intval', $data['collections'] ?? []);
        sort($collections);
        $columns = $data['columns'] ?? [];
        sort($columns);

        return ['search' => trim($data['search'] ?? ''), 'collections' => $collections, 'columns' => $columns, 'include_inactive' => (bool) ($data['include_inactive'] ?? false)];
    }

    /**
     * Read the user’s latest recognized browse criteria or the defaults if none can be recovered.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/InventoryBrowseController.php
     */
    public function remembered(int $user): array
    {
        $saved = json_decode(DB::table('inventory_preferences')->where('user_id', $user)->value('criteria') ?? '{}', true);
        if (! is_array($saved)) {
            return $this->defaults();
        }
        $validCollections = DB::table('collections')->pluck('id')->all();

        return ['search' => is_string($saved['search'] ?? null) ? mb_substr($saved['search'], 0, 255) : '',
            'collections' => array_values(array_intersect(is_array($saved['collections'] ?? null) ? $saved['collections'] : [], $validCollections)),
            // Preserve unavailable selections so the user can explicitly remove them.
            'columns' => array_values(array_filter(is_array($saved['columns'] ?? null) ? $saved['columns'] : [], 'is_string')),
            'include_inactive' => ($saved['include_inactive'] ?? false) === true];
    }

    /**
     * Persist the current Inventory browse criteria under the user lock.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/InventoryBrowseController.php
     */
    public function remember(int $user, array $criteria): void
    {
        DB::transaction(function () use ($user, $criteria) {
            DB::table('users')->where('id', $user)->lockForUpdate()->firstOrFail();
            DB::table('inventory_preferences')->updateOrInsert(['user_id' => $user], ['criteria' => json_encode($criteria, JSON_THROW_ON_ERROR), 'updated_at' => now('UTC')]);
        });
    }

    /**
     * Return current stock and destination-specific incoming counts for one location. Preserve explicit zero-stock and incoming-display choices when reusing these rows.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/SavedListController.php
     */
    public function locationResults(int $location, bool $includeZeroStock = false, bool $showIncoming = true): array
    {
        $criteria = $this->defaults();
        $criteria['include_inactive'] = true;
        $criteria['columns'] = ['storage:'.$location];
        $result = $this->results($criteria, naturalNames: true);

        // Source balances are global; destination totals come from posted requests,
        // not editable order drafts or partially entered receiving quantities.
        $transit = DB::table('relocation_fulfillment_lines as lines')
            ->join('relocation_requests as requests', 'requests.id', '=', 'lines.relocation_request_id')
            ->where('requests.destination_location_id', $location)
            ->whereIn('requests.status', ['Fulfilled', 'Received'])
            ->select('lines.item_id', DB::raw('SUM(lines.sent) as quantity'))
            ->groupBy('lines.item_id')->pluck('quantity', 'item_id');
        $ordered = [];
        $orders = DB::table('purchase_fulfillment as fulfillment')
            ->join('purchase_requests as requests', 'requests.id', '=', 'fulfillment.purchase_request_id')
            ->where('requests.receiving_location_id', $location)
            ->whereIn('requests.status', ['Ordered', 'Shipped'])->pluck('fulfillment.data');
        foreach ($orders as $data) {
            $header = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
            foreach ($header['ordered_quantities'] ?? [] as $item => $quantity) {
                $ordered[$item] = ($ordered[$item] ?? 0) + (int) $quantity;
            }
        }
        $result['showTransit'] = $transit->contains(fn ($quantity) => (int) $quantity > 0);
        $result['showOrdered'] = collect($ordered)->contains(fn ($quantity) => $quantity > 0);
        foreach ($result['rows'] as &$row) {
            $row['incoming_transit'] = (int) ($transit[$row['id']] ?? 0);
            $row['incoming_ordered'] = $ordered[$row['id']] ?? 0;
            $row['expected'] = $row['values']['storage:'.$location] + $row['incoming_transit'] + $row['incoming_ordered'];
        }
        unset($row);
        if (! $includeZeroStock) {
            $result['rows'] = array_values(array_filter($result['rows'], fn ($row) =>
                $row['values']['storage:'.$location] !== 0
                || ($showIncoming && ($row['incoming_transit'] !== 0 || $row['incoming_ordered'] !== 0))));
        }

        return $result;
    }

    /**
     * Get storage-balance rows for the given item IDs, grouped by item ID. Source balances are queried separately.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/CatalogController.php
     */
    public function storageBalances(array $itemIds): Collection
    {
        return DB::table('inventory_balances')->whereIntegerInRaw('item_id', $itemIds)->get()->groupBy('item_id');
    }

    /**
     * Query catalog items and stock for prepared criteria. Optional item IDs restrict membership, and natural-name mode sorts item names accordingly.
     *
     * Scope: Domain-specific (Inventory)
     * Task: Query inventory
     * @example app/Http/Controllers/InventoryBrowseController.php
     */
    public function results(array $criteria, bool $naturalNames = false, ?array $itemIds = null): array
    {
        $items = DB::table('items')->join('collections', 'collections.id', '=', 'items.collection_id')->join('categories', 'categories.id', '=', 'collections.category_id')
            ->select('items.*', 'collections.name as collection_name', 'categories.name as category_name');
        if ($itemIds !== null) {
            $items->whereIntegerInRaw('items.id', $itemIds);
        }
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
        $items->orderBy('categories.name')->orderBy('collections.name');
        if ($naturalNames) $items->orderByRaw('NATURAL_SORT_KEY(items.name)');
        $items = $items->orderBy('items.name')->orderBy('items.id')->get();
        $ids = $items->pluck('id')->all();
        $balances = $this->storageBalances($ids);
        $sources = DB::table('inventory_source_balances')->join('inventory_sources', 'inventory_sources.id', '=', 'inventory_source_balances.source_id')
            ->whereIn('item_id', $ids)->whereIn('kind', ['event', 'transit', 'ordered'])->where('active', true)
            ->select('inventory_source_balances.*', 'inventory_sources.kind')->get()->groupBy('item_id');
        $allColumns = $this->columns(false);
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
            $rows[] = ['id' => (int) $item->id, 'name' => $item->name, 'category' => $item->category_name, 'collection' => $item->collection_name, 'collection_id' => (int) $item->collection_id, 'sku' => $item->sku, 'available' => $available, 'values' => $values];
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
