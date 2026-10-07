<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\InventorySearch;
use App\Support\StockNumbers;
use App\Support\StockPosting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['inventoryReady'], 503, 'Inventory is not ready.');
    }

    private function correctionAccess(Request $request): void
    {
        $this->ready();
        abort_unless($request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 403);
    }

    private function item(int $id): object
    {
        return DB::table('items')->join('collections', 'collections.id', '=', 'items.collection_id')
            ->join('categories', 'categories.id', '=', 'collections.category_id')
            ->select('items.*', 'collections.name as collection_name', 'categories.name as category_name', 'categories.id as category_id')->where('items.id', $id)->firstOrFail();
    }

    private function locations(int $item): Collection
    {
        $balances = DB::table('inventory_balances')->where('item_id', $item)->pluck('quantity', 'storage_location_id');

        return DB::table('storage_locations')->orderByDesc('is_central')->orderBy('name')->orderBy('id')->get()
            ->map(function ($location) use ($balances) {
                $location->quantity = (int) ($balances[$location->id] ?? 0);

                return $location;
            });
    }

    public function index()
    {
        $this->ready();

        return view('inventory.index', ['items' => DB::table('items')->orderBy('name')->get()]);
    }

    public function show(Request $request, int $item)
    {
        $this->ready();
        $schema = app(BaselineProbe::class)->schemaState(DB::connection());
        $catalogReady = $schema['catalogReady'];
        $dailyReady = $schema['dailyInventoryReady'];
        $extraBalances = [];
        $metadata = null;
        $purpose = null;
        $programs = collect();
        $state = 'active';
        if ($catalogReady) {
            $metadata = DB::table('item_metadata')->where('item_id', $item)->first();
            $purpose = $metadata?->purpose_id ? app(CatalogRecords::class)->record('purposes', $metadata->purpose_id) : null;
            $programs = DB::table('item_programs')->join('catalog_references', 'catalog_references.id', '=', 'item_programs.program_id')->where('item_id', $item)->orderBy('name')->get();
            $state = app(CatalogRecords::class)->record('items', $item)->state;
            $criteria = app(InventorySearch::class)->defaults();
            $criteria['include_inactive'] = true;
            $results = app(InventorySearch::class)->results($criteria);
            $result = collect($results['rows'])->firstWhere('id', $item);
            foreach (app(InventorySearch::class)->columns() as $key => $column) {
                if ($column['kind'] !== 'storage') {
                    $extraBalances[] = ['name' => $column['name'], 'quantity' => $result['values'][$key] ?? 0];
                }
            }
        }
        $sort = $request->query('sort') === 'description' ? 'description' : 'posted_at';
        $history = DB::table('inventory_adjustment_entries')->join('inventory_adjustments', 'inventory_adjustments.id', '=', 'inventory_adjustment_entries.inventory_adjustment_id')
            ->where('inventory_adjustments.item_id', $item)->select('inventory_adjustment_entries.*', 'inventory_adjustments.posted_at')
            ->orderBy($sort, $sort === 'description' ? 'asc' : 'desc')->orderByDesc('inventory_adjustment_entries.id')->get();
        if ($dailyReady) {
            $costHistory = DB::table('item_cost_entries')->where('item_id', $item)->get()->map(function ($entry) {
                $entry->cost_entry_id = $entry->id;
                $entry->description = 'Adjustment: Unit Cost';
                $entry->quantity_change = null;
                $entry->unit_cost = $entry->after_cost;

                return $entry;
            });
            $history = $history->concat($costHistory)->sort(function ($a, $b) use ($sort) {
                if ($sort === 'description') {
                    $description = strnatcasecmp($a->description, $b->description);
                    if ($description !== 0) {
                        return $description;
                    }
                }

                return strcmp($b->posted_at, $a->posted_at) ?: ($b->id <=> $a->id);
            })->values();
        }
        if ($schema['fulfillmentReady']) {
            $requestHistory = DB::table('request_stock_entries')->where('item_id', $item)->get()->map(function ($entry) {
                $entry->request_url = $entry->purchase_request_id !== null ? '/purchases/'.$entry->purchase_request_id : '/relocations/'.$entry->relocation_request_id;

                return $entry;
            });
            $history = $history->concat($requestHistory)->sort(function ($a, $b) use ($sort) {
                if ($sort === 'description' && ($comparison = strnatcasecmp($a->description, $b->description)) !== 0) {
                    return $comparison;
                }

                return strcmp($b->posted_at, $a->posted_at) ?: ($b->id <=> $a->id);
            })->values();
        }

        if ($schema['eventsReady']) {
            $eventHistory = DB::table('event_stock_entries')->join('event_operations', 'event_operations.id', '=', 'event_stock_entries.operation_id')
                ->where('event_stock_entries.item_id', $item)->select('event_stock_entries.*', 'event_operations.posted_at')->get()->map(function ($entry) {
                    $entry->request_url = '/events/'.$entry->event_id;
                    $entry->entry_kind = $entry->leg === 'distribution' || $entry->leg === 'external' ? 'event_change' : 'event_transfer';

                    return $entry;
                });
            $history = $history->concat($eventHistory)->sort(function ($a, $b) use ($sort) {
                if ($sort === 'description' && ($comparison = strnatcasecmp($a->description, $b->description)) !== 0) {
                    return $comparison;
                }

                return strcmp($b->posted_at, $a->posted_at) ?: ($b->id <=> $a->id);
            })->values();
        }

        return view('inventory.item', ['item' => $this->item($item), 'locations' => $this->locations($item), 'history' => $history, 'dailyReady' => $dailyReady, 'canCost' => $request->user()->hasRole('admin'),
            'canCorrect' => $request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 'catalogReady' => $catalogReady, 'metadata' => $metadata, 'purpose' => $purpose, 'programs' => $programs, 'state' => $state, 'extraBalances' => $extraBalances, 'browse' => $request->query('browse')]);
    }

    private function draft(Request $request, int $item, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('stock_drafts.'.$token);
        abort_unless(is_array($draft) && $draft['item'] === $item && $draft['actor'] === (int) $request->user()->id, 404);

        return $draft;
    }

    public function edit(Request $request, int $item)
    {
        $this->correctionAccess($request);
        $token = $request->query('operation');
        $draft = is_string($token) ? $this->draft($request, $item, $token) : null;
        if (! $draft) {
            $token = (string) Str::uuid();
            $draft = ['item' => $item, 'actor' => (int) $request->user()->id, 'rows' => [], 'reviewed' => false];
            foreach ($this->locations($item) as $location) {
                $draft['rows'][$location->id] = ['set' => (string) $location->quantity, 'adjust' => '0', 'rationale' => ''];
            }
            $request->session()->put('stock_drafts.'.$token, $draft);
        }

        return view('inventory.edit', ['item' => $this->item($item), 'locations' => $this->locations($item), 'draft' => $draft, 'token' => $token]);
    }

    public function preview(Request $request, int $item)
    {
        $this->correctionAccess($request);
        $token = $request->input('operation');
        abort_unless(is_string($token), 422);
        $draft = $this->draft($request, $item, $token);
        abort_if(DB::table('inventory_adjustments')->where('operation_id', $token)->exists(), 409);
        $data = $request->validate(['rows' => ['required', 'array'], 'rows.*' => ['required', 'array:set,adjust,rationale'],
            'rows.*.set' => ['required'], 'rows.*.adjust' => ['nullable'], 'rows.*.rationale' => ['nullable', 'string', 'max:1000']]);
        $locations = $this->locations($item);
        $expected = array_map('strval', $locations->pluck('id')->all());
        $received = array_map('strval', array_keys($data['rows']));
        sort($expected);
        sort($received);
        abort_unless($expected === $received, 422);
        foreach ($data['rows'] as $id => $row) {
            StockNumbers::finalQuantity($row['set'], $row['adjust'] ?? '', 'rows.'.$id);
        }
        $draft['rows'] = $data['rows'];
        $draft['reviewed'] = true;
        $request->session()->put('stock_drafts.'.$token, $draft);

        return redirect('/inventory/items/'.$item.'/review/'.$token);
    }

    public function review(Request $request, int $item, string $operation)
    {
        $this->correctionAccess($request);
        $draft = $this->draft($request, $item, $operation);
        abort_unless($draft['reviewed'], 422);
        $changes = [];
        foreach ($this->locations($item) as $location) {
            abort_unless(isset($draft['rows'][$location->id]), 422);
            $row = $draft['rows'][$location->id];
            $after = StockNumbers::finalQuantity($row['set'], $row['adjust'] ?? '', 'rows.'.$location->id);
            if ($after !== $location->quantity) {
                $changes[] = ['description' => 'Adjustment: '.$location->name, 'quantity_change' => $after - $location->quantity, 'rationale' => $row['rationale'] ?? ''];
            }
        }

        return view('inventory.review', ['item' => $this->item($item), 'changes' => $changes, 'token' => $operation]);
    }

    public function save(Request $request, int $item, string $operation)
    {
        $this->correctionAccess($request);
        $draft = $this->draft($request, $item, $operation);
        abort_unless($draft['reviewed'], 422);
        try {
            $id = app(StockPosting::class)->post((int) $request->user()->id, $item, $operation, $draft['rows']);
        } catch (QueryException) {
            return back()->with('error', 'The correction could not be saved. Your review is retained; try again.');
        }

        return redirect('/inventory/adjustments/'.$id.'/result');
    }

    public function adjustment(int $adjustment, bool $result = false)
    {
        $this->ready();
        $group = DB::table('inventory_adjustments')->where('id', $adjustment)->firstOrFail();
        $entries = DB::table('inventory_adjustment_entries')->where('inventory_adjustment_id', $adjustment)->get()->sortBy('location_name')->values();
        // Historical name determines central ordering; do not rewrite snapshots from current names.
        $entries = $entries->sortBy(fn ($entry) => [$entry->location_name === 'Central' ? 0 : 1, $entry->location_name])->values();

        return view($result ? 'inventory.result' : 'inventory.adjustment', ['group' => $group, 'entries' => $entries]);
    }

    public function result(int $adjustment)
    {
        return $this->adjustment($adjustment, true);
    }
}
