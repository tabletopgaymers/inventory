<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/** Read-only adapter; configuration is supplied by a trusted caller, never by browser input. */
class InventorySearchControls
{
    public const FILTERS = ['collections', 'locations', 'events', 'transit', 'ordered', 'inactive'];

    public function definition(array $config = []): array
    {
        $filters = array_values(array_intersect(self::FILTERS, $config['filters'] ?? self::FILTERS));
        $hidden = array_values(array_intersect($filters, $config['hiddenFilters'] ?? []));
        $defaults = array_replace(app(InventorySearch::class)->defaults(), $config['defaults'] ?? []);
        $centralId = DB::table('storage_locations')->where('is_central', true)->value('id');
        $columnGroups = ['locations' => [], 'events' => [], 'transit' => [], 'ordered' => []];
        foreach (app(InventorySearch::class)->columns() as $key => $column) {
            $group = $this->group($column['kind']);
            if ($group === 'locations') $column['is_central'] = (int) $column['id'] === (int) $centralId;
            if (in_array($group, $filters, true)) $columnGroups[$group][$key] = $column;
        }
        $collections = app(CatalogRecords::class)->records('collections')->sort(fn ($a, $b) =>
            strcasecmp($a->category_name, $b->category_name) ?: strcasecmp($a->name, $b->name) ?: ($a->id <=> $b->id)
        )->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'category_id' => (int) $row->category_id, 'category' => $row->category_name])->values()->all();

        return ['filters' => $filters, 'hiddenFilters' => $hidden, 'defaults' => $defaults,
            'collections' => $collections, 'columnGroups' => $columnGroups];
    }

    private function group(string $kind): string
    {
        return match ($kind) { 'storage' => 'locations', 'event' => 'events', default => $kind };
    }

    public function search(Request $request, array $config = []): array
    {
        $definition = $this->definition($config);
        $visible = array_diff($definition['filters'], $definition['hiddenFilters']);
        $input = $request->validate(['search' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'collections' => ['nullable', 'array'], 'collections.*' => ['integer', 'distinct', Rule::in(array_column($definition['collections'], 'id'))],
            'columns' => ['nullable', 'array'], 'columns.*' => ['string', 'distinct'], 'include_inactive' => ['nullable', 'boolean']]);
        $criteria = ['search' => trim($input['search'] ?? ''), 'collections' => [], 'columns' => [], 'include_inactive' => false];
        if (in_array('collections', $definition['filters'], true)) {
            $criteria['collections'] = array_map('intval', in_array('collections', $visible, true) ? ($input['collections'] ?? []) : $definition['defaults']['collections']);
        }
        if (in_array('inactive', $definition['filters'], true)) {
            $criteria['include_inactive'] = (bool) (in_array('inactive', $visible, true) ? ($input['include_inactive'] ?? false) : $definition['defaults']['include_inactive']);
        }
        $selectable = [];
        foreach ($definition['columnGroups'] as $group => $columns) {
            foreach (array_keys($columns) as $key) {
                if (in_array($group, $visible, true)) $selectable[] = $key;
                elseif (in_array($key, $definition['defaults']['columns'], true)) $criteria['columns'][] = $key;
            }
        }
        foreach ($input['columns'] ?? [] as $key) {
            if (!in_array($key, $selectable, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['columns' => 'A selected location or source is unavailable for this search.']);
            }
            $criteria['columns'][] = $key;
        }
        sort($criteria['collections']);
        sort($criteria['columns']);
        $result = app(InventorySearch::class)->results($criteria, naturalNames: true);
        $classifications = DB::table('items')->join('collections', 'collections.id', '=', 'items.collection_id')
            ->leftJoin('catalog_metadata as metadata', fn ($join) => $join->on('metadata.record_id', '=', 'items.id')->where('metadata.kind', 'items'))
            ->whereIn('items.id', array_column($result['rows'], 'id'))->select('items.id', 'items.collection_id', 'collections.category_id', DB::raw("COALESCE(metadata.state, 'active') as state"))->get()->keyBy('id');
        $allowedKeys = [];
        foreach (app(InventorySearch::class)->columns(false) as $key => $column) {
            if (in_array($this->group($column['kind']), $definition['filters'], true)) $allowedKeys[] = $key;
        }
        $rows = [];
        foreach ($result['rows'] as $row) {
            $classification = $classifications[$row['id']];
            if (($config['activeOnly'] ?? false) && $classification->state !== 'active') continue;
            if (in_array($row['id'], array_map('intval', $config['excludeIds'] ?? []), true)) continue;
            $row += ['item_id' => $row['id'], 'category_id' => (int) $classification->category_id,
                'collection_id' => (int) $classification->collection_id, 'state' => $classification->state];
            $row['values'] = array_intersect_key($row['values'], array_flip($allowedKeys));
            $rows[] = $row;
        }
        $eventIds = Schema::hasTable('event_workflows') ? DB::table('event_workflows')->whereNotNull('source_id')->pluck('id', 'source_id')->all() : [];
        $columns = [];
        foreach ($result['columns'] as $key => $column) {
            if (!in_array($key, $allowedKeys, true)) continue;
            if (!$criteria['columns'] && !array_filter($rows, fn ($row) => ($row['values'][$key] ?? 0) !== 0)) continue;
            $column['location_id'] = $column['kind'] === 'storage' ? $column['id'] : null;
            $column['source_id'] = $column['kind'] === 'event' ? $column['id'] : null;
            $column['event_id'] = $column['kind'] === 'event' && isset($eventIds[$column['id']]) ? (int) $eventIds[$column['id']] : null;
            $columns[$key] = $column;
        }
        foreach ($rows as &$row) $row['values'] = array_intersect_key($row['values'], $columns);
        unset($row);

        return ['schema_version' => 1, 'criteria' => $criteria, 'rows' => $rows, 'columns' => (object) $columns, 'count' => count($rows)];
    }
}
