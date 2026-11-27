<?php

namespace App\Http\Controllers;

use App\Models\SavedList;
use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\InventoryBrowseState;
use App\Support\InventorySearch;
use App\View\Components\App\ItemsTable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryBrowseController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->runtimeState(DB::connection())['catalogReady'], 503, 'Inventory browsing is not ready.');
    }

    private function render(Request $request, array $pending, ?array $result = null, ?string $token = null, array $context = [])
    {
        $loadedList = $context['list'] ?? null;
        $submitted = $loadedList === null ? ($context['submitted'] ?? null) : null;
        $service = app(InventorySearch::class);
        $columns = $service->columns();
        $known = $service->columns(false);
        foreach (array_unique(array_merge($pending['columns'], $submitted['columns'] ?? [])) as $key) {
            if (! isset($columns[$key])) {
                $columns[$key] = ($known[$key] ?? ['name' => $key, 'kind' => 'storage', 'state' => 'unavailable']) + ['unavailable' => true];
            }
        }

        $defaults = [
            'search' => old('search', $pending['search']), 'collections' => old('collections', $pending['collections']),
            'columns' => old('columns', $pending['columns']), 'include_inactive' => (bool) old('include_inactive', $pending['include_inactive']),
        ];

        return view('inventory.browse', [
            'submitted' => $submitted, 'result' => $result, 'token' => $token, 'scroll' => $context['scroll'] ?? 0,
            'lists' => SavedList::availableFor($request->user())->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'loadedList' => $loadedList,
            'canEditList' => $loadedList !== null && (SavedList::find($loadedList['id'])?->canEdit($request->user()) ?? false),
            'previousSearch' => $context['previous_search'] ?? null,
            'searchDefinition' => app(\App\Support\InventorySearchControls::class)->definition(['defaults' => $defaults, 'columns' => $columns]), 'allColumns' => $columns]);
    }

    public function index(Request $request)
    {
        $this->ready();

        return $this->render($request, app(InventorySearch::class)->remembered((int) $request->user()->id));
    }

    public function submit(Request $request)
    {
        $this->ready();
        $action = $request->validate(['action' => ['required', 'in:search,show_all,reset']])['action'];
        $service = app(InventorySearch::class);
        $criteria = $action === 'reset' ? $service->defaults() : $service->criteria($request);
        if ($action === 'show_all') {
            $criteria['search'] = '';
        }
        try {
            $service->remember((int) $request->user()->id, $criteria);
            if ($action === 'reset') {
                $request->session()->forget('inventory_browse');

                return redirect('/inventory');
            }
            $token = (string) Str::uuid();
            // Bounded actor session context. Rows are retained only as the last displayed
            // export payload; return always refreshes stock from submitted criteria.
            $request->session()->put('inventory_browse', [$token => ['actor' => (int) $request->user()->id, 'pending' => $criteria, 'submitted' => $criteria, 'scroll' => 0]]);

            return redirect('/inventory/results/'.$token);
        } catch (QueryException) {
            return back()->withInput()->with('error', 'Search settings could not be saved. Try again.');
        }
    }

    public function results(Request $request, string $token)
    {
        $this->ready();
        $context = app(InventoryBrowseState::class)->context($request, $token);
        $result = app(InventorySearch::class)->results($context['submitted'], naturalNames: true, itemIds: $context['list']['items'] ?? null);
        $context['export'] = $result;
        $context['export_expires'] = now()->getTimestamp() + 1800;
        $request->session()->put('inventory_browse.'.$token, $context);

        return $this->render($request, $context['pending'], $result, $token, $context);
    }

    public function loadList(Request $request)
    {
        $this->ready();
        $data = $request->validate(['list_id' => ['required', 'integer', 'min:1'], 'browse' => ['nullable', 'uuid']]);
        $state = app(InventoryBrowseState::class);
        $searchToken = $data['browse'] ?? null;
        if ($searchToken !== null) {
            $context = $state->context($request, $searchToken);
            $searchToken = isset($context['list']) ? $context['previous_search'] : $searchToken;
        }
        [$list, $items] = DB::transaction(function () use ($request, $data) {
            $list = SavedList::availableFor($request->user())->lockForUpdate()->findOrFail($data['list_id']);

            return [$list, $list->itemIds()];
        });
        $token = $state->loadList($request, $list, $items, $searchToken);

        return redirect('/inventory/results/'.$token);
    }

    public function pending(Request $request)
    {
        $this->ready();
        $criteria = app(InventorySearch::class)->criteria($request);
        $extra = $request->validate(['context' => ['nullable', 'uuid'], 'scroll' => ['nullable', 'integer', 'min:0', 'max:10000000'], 'destination' => ['nullable', 'string', 'regex:#\A/inventory/(items|locations)/[0-9]+\z#D']]);
        try {
            $context = ! empty($extra['context']) ? app(InventoryBrowseState::class)->context($request, $extra['context']) : null;
            abort_if(isset($context['list']), 404);
            app(InventorySearch::class)->remember((int) $request->user()->id, $criteria);
            if (! empty($extra['context'])) {
                $context['pending'] = $criteria;
                $context['scroll'] = (int) ($extra['scroll'] ?? 0);
                $request->session()->put('inventory_browse.'.$extra['context'], $context);
            }
        } catch (QueryException) {
            return response()->json(['message' => 'Search settings could not be saved. Your displayed results are unchanged.'], 503);
        }
        if (! empty($extra['destination'])) {
            return redirect($extra['destination'].'?browse='.$extra['context']);
        }

        return response()->noContent();
    }

    public function export(Request $request, string $token)
    {
        $this->ready();
        $context = app(InventoryBrowseState::class)->context($request, $token);
        abort_unless(isset($context['export']) && $context['export_expires'] >= now()->getTimestamp() && count($context['export']['rows']) > 0, 410, 'Displayed results expired. Return to results to refresh before downloading.');
        $result = $context['export'];
        $stream = fopen('php://output', 'w');
        abort_if($stream === false, 500, 'Inventory CSV output could not be opened.');

        return response()->streamDownload(function () use ($result, $stream) {
            $safeText = fn ($value) => preg_match('/\A[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
            fputcsv($stream, array_map($safeText, array_merge(['Category', 'Collection', 'Name', 'Available'], array_column($result['columns'], 'name'))), escape: '');
            foreach ($result['rows'] as $row) {
                $text = array_map($safeText, [$row['category'], $row['collection'], $row['name']]);
                $quantities = [$row['available']];
                foreach (array_keys($result['columns']) as $key) {
                    $quantities[] = $row['values'][$key];
                }
                fputcsv($stream, array_merge($text, $quantities), escape: '');
            }
            fclose($stream);
        }, 'inventory.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function location(Request $request, int $location)
    {
        $this->ready();
        $locations = app(CatalogRecords::class)->records('storage_locations');
        $row = $locations->firstWhere('id', $location) ?? abort(404);
        $includeZeroStock = $request->boolean('include_zero_stock');
        $showIncoming = $request->boolean('show_incoming', true);

        $browse = $request->query('browse');
        $browse = is_string($browse) && Str::isUuid($browse) ? $browse : null;
        $result = app(InventorySearch::class)->locationResults($location, $includeZeroStock, $showIncoming);
        $locationKey = 'storage:'.$location;
        $columns = [ItemsTable::DEFAULT_COLUMNS[0], ['key' => $locationKey, 'label' => $row->name, 'role' => 'number']];
        if ($showIncoming && $result['showTransit']) {
            $columns[] = ['key' => 'incoming_transit', 'label' => 'In Transit', 'role' => 'number'];
        }
        if ($showIncoming && $result['showOrdered']) {
            $columns[] = ['key' => 'incoming_ordered', 'label' => 'Ordered', 'role' => 'number'];
        }
        if (count($columns) > 2) {
            $columns[] = ['key' => 'expected', 'label' => 'Expected', 'role' => 'number'];
        }

        $rows = array_map(fn ($item) => [
            'item' => ['name' => $item['name'], 'link' => ['href' => '/inventory/items/'.$item['id'].($browse ? '?browse='.$browse : '')]],
            'group' => ['key' => $item['collection_id'], 'category' => $item['category'], 'collection' => $item['collection']],
            'values' => [
                $locationKey => $item['values'][$locationKey],
                'incoming_transit' => $item['incoming_transit'],
                'incoming_ordered' => $item['incoming_ordered'],
                'expected' => $item['expected'],
            ],
        ], $result['rows']);

        $createListUrl = '/catalog/lists/create?'.http_build_query([
            'location' => $location, 'include_zero_stock' => $includeZeroStock, 'show_incoming' => $showIncoming, 'browse' => $browse,
        ]);

        return view('inventory.location', [
            'location' => $row, 'locations' => $locations, 'rows' => $rows, 'columns' => $columns, 'createListUrl' => $createListUrl,
            'hasIncoming' => $result['showTransit'] || $result['showOrdered'],
            'includeZeroStock' => $includeZeroStock, 'showIncoming' => $showIncoming, 'browse' => $browse,
            'canManage' => $request->user()->hasRole('admin') || $request->user()->hasRole('manager'),
        ]);
    }

    public function classification(Request $request, string $kind, int $record)
    {
        $this->ready();
        abort_unless(in_array($kind, ['category', 'collection'], true), 404);
        $criteria = app(InventorySearch::class)->defaults();
        if ($kind === 'category') {
            DB::table('categories')->where('id', $record)->firstOrFail();
            $criteria['collections'] = DB::table('collections')->where('category_id', $record)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($criteria['collections'] === []) {
                // An empty category must not accidentally submit an unrestricted query.
                $criteria['collections'] = [0];
            }
        } else {
            DB::table('collections')->where('id', $record)->firstOrFail();
            $criteria['collections'] = [$record];
        }
        $token = (string) Str::uuid();
        app(InventorySearch::class)->remember((int) $request->user()->id, $criteria);
        $request->session()->put('inventory_browse', [$token => ['actor' => (int) $request->user()->id, 'pending' => $criteria, 'submitted' => $criteria, 'scroll' => 0]]);

        return redirect('/inventory/results/'.$token);
    }
}
