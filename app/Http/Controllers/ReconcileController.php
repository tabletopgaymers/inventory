<?php

namespace App\Http\Controllers;

use App\Models\SavedList;
use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\DailyInventoryPosting;
use App\Support\InventorySearch;
use App\Support\LocationCountSearch;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReconcileController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->runtimeState(DB::connection())['dailyInventoryReady'], 503, 'Reconciliation is not ready.');
    }

    private function access(Request $request): void
    {
        $this->ready();
        abort_unless($request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 403);
    }

    private function draft(Request $request, string $token): array
    {
        return app(LocationCountSearch::class)->draft($request, $token);
    }

    private function rows(array $draft): array
    {
        $search = app(InventorySearch::class);
        $criteria = $search->defaults();
        $criteria['include_inactive'] = true;

        return $search->results($criteria, naturalNames: true, itemIds: $draft['items'])['rows'];
    }

    private function availableLists(Request $request)
    {
        return SavedList::availableFor($request->user());
    }

    public function index(Request $request)
    {
        $this->ready();
        $canCount = $request->user()->hasRole('admin') || $request->user()->hasRole('manager');
        $criteria = null;
        $result = null;
        $token = null;
        $loadedList = null;
        $listLocation = null;
        if ($request->filled('context')) {
            $token = $request->query('context');
            abort_unless(is_string($token), 404);
            $draft = $this->draft($request, $token);
            $loadedList = $draft['list'] ?? null;
            if ($loadedList !== null) {
                $listLocation = $draft['criteria']['location_id'];
                $lastSearch = app(LocationCountSearch::class)->lastSearch($request);
                $criteria = $lastSearch === null ? null : $this->draft($request, $lastSearch)['criteria'];
                $result = $this->rows($draft);
                $loadedList['canEdit'] = SavedList::find($loadedList['id'])?->canEdit($request->user()) ?? false;
            } else {
                $criteria = $draft['criteria'];
                $result = app(LocationCountSearch::class)->rows($criteria);
                $request->session()->put('location_counts_last_search', $token);
            }
        }

        if ($token !== null && $result !== null) {
            $request->session()->put('location_counts.'.$token.'.displayed_items', array_column($result, 'id'));
        }

        return view('reconcile.search', ['criteria' => $criteria, 'rows' => $result, 'token' => $token,
            'lists' => $this->availableLists($request)->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'loadedList' => $loadedList, 'listLocation' => $listLocation, 'lastSearch' => app(LocationCountSearch::class)->lastSearch($request),
            'locations' => app(CatalogRecords::class)->records('storage_locations'),
            'searchDefinition' => app(\App\Support\InventorySearchControls::class)->definition(['filters' => ['collections'], 'defaults' => [
                'search' => old('search', $criteria['search'] ?? ''),
                'collections' => old('collections', $request->session()->has('errors') ? [] : ($criteria['collections'] ?? [])),
            ]]),
            'canCount' => $canCount]);
    }

    public function search(Request $request)
    {
        $this->ready();
        $action = $request->validate(['action' => ['required', 'in:search,show_all,reset']])['action'];
        if ($action === 'reset') {
            return to_route('reconcile.index');
        }
        $service = app(LocationCountSearch::class);
        $criteria = $service->criteria($request);
        if ($action === 'show_all') {
            $criteria['search'] = '';
        }
        try {
            $token = app(LocationCountSearch::class)->newDraft($request, $criteria, array_column($service->rows($criteria), 'id'));

            return to_route('reconcile.index', ['context' => $token]);
        } catch (QueryException) {
            return back()->withInput()->with('error', 'Search could not be completed. Your inputs are retained; try again.');
        }
    }

    public function loadList(Request $request)
    {
        $this->ready();
        $data = $request->validate(['list_id' => ['required', 'integer', 'min:1']]);
        [$list, $items] = DB::transaction(function () use ($request, $data) {
            $list = $this->availableLists($request)->lockForUpdate()->findOrFail($data['list_id']);

            return [$list, $list->itemIds()];
        });
        $lastSearch = app(LocationCountSearch::class)->lastSearch($request);
        $searchLocation = $lastSearch === null ? null : $this->draft($request, $lastSearch)['criteria']['location_id'];
        $location = $list->storage_location_id ?? $searchLocation;
        $token = app(LocationCountSearch::class)->newDraft($request, ['location_id' => $location === null ? null : (int) $location], $items,
            ['id' => $list->id, 'name' => $list->name]);

        return to_route('reconcile.index', ['context' => $token]);
    }

    public function listLocation(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);
        abort_unless(isset($draft['list']), 404);
        $data = $request->validate(['list_location_id' => ['required', 'integer', Rule::exists('storage_locations', 'id')]]);
        $location = (int) $data['list_location_id'];
        if ($draft['criteria']['location_id'] !== $location) {
            // A different location needs its own operation token and blank count inputs.
            $token = app(LocationCountSearch::class)->newDraft($request, ['location_id' => $location], $draft['items'], $draft['list']);
        }

        return to_route('reconcile.index', ['context' => $token]);
    }

    public function worksheet(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);

        if (isset($draft['list'])) {
            abort_unless($draft['criteria']['location_id'] !== null && $draft['items'] !== [], 409);
        }
        $rows = isset($draft['list']) ? $this->rows($draft) : app(LocationCountSearch::class)->rows($draft['criteria']);

        return view('reconcile.worksheet', ['location' => app(CatalogRecords::class)->record('storage_locations', $draft['criteria']['location_id']), 'rows' => $rows]);
    }

    public function enter(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        if (isset($draft['list'], $draft['result'])) {
            $nextToken = app(LocationCountSearch::class)->newDraft($request, $draft['criteria'], $draft['items'], $draft['list']);

            return to_route('reconcile.enter', ['token' => $nextToken]);
        }
        abort_if(isset($draft['result']), 409, 'This count has already been saved.');
        if (isset($draft['list'])) {
            abort_unless($draft['criteria']['location_id'] !== null && $draft['items'] !== [], 409);
        } elseif ($draft['counts'] === [] && ! $draft['reviewed']) {
            $draft['items'] = array_column(app(LocationCountSearch::class)->rows($draft['criteria']), 'id');
            $request->session()->put('location_counts.'.$token, $draft);
        }

        return view('reconcile.enter', ['draft' => $draft, 'rows' => $this->rows($draft), 'token' => $token, 'location' => app(CatalogRecords::class)->record('storage_locations', $draft['criteria']['location_id'])]);
    }

    public function review(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        abort_if(isset($draft['result']), 409);
        abort_if(isset($draft['list']) && ($draft['criteria']['location_id'] === null || $draft['items'] === []), 409);
        $data = $request->validate(['counts' => ['nullable', 'array'], 'counts.*' => ['nullable', 'string']]);
        $counts = $data['counts'] ?? [];
        foreach ($counts as $id => $value) {
            abort_unless(in_array((int) $id, $draft['items'], true) && ctype_digit((string) $id), 422);
            DailyInventoryPosting::count($value, 'counts.'.$id);
        }
        $draft['counts'] = $counts;
        $draft['reviewed'] = true;
        $draft['review_ids'] = [];
        foreach ($this->rows($draft) as $row) {
            $count = DailyInventoryPosting::count($counts[$row['id']] ?? null, 'counts.'.$row['id']);
            if ($count !== null && $count !== $row['values']['storage:'.$draft['criteria']['location_id']]) {
                $draft['review_ids'][] = $row['id'];
            }
        }
        $request->session()->put('location_counts.'.$token, $draft);

        return to_route('reconcile.review', ['token' => $token]);
    }

    public function reviewed(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        abort_unless($draft['reviewed'], 409);

        return view('reconcile.review', ['draft' => $draft, 'rows' => array_values(array_filter($this->rows($draft), fn ($row) => in_array($row['id'], $draft['review_ids'], true))), 'token' => $token, 'location' => app(CatalogRecords::class)->record('storage_locations', $draft['criteria']['location_id'])]);
    }

    public function save(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        if (isset($draft['result'])) {
            return to_route('reconcile.result', ['operation' => $draft['result']]);
        }
        abort_unless($draft['reviewed'], 409);
        $data = $request->validate(['action' => ['required', 'in:save,edit'], 'rationales' => ['nullable', 'array'], 'rationales.*' => ['nullable', 'string', 'max:1000', 'not_regex:/[\x00-\x1F\x7F]/u']]);
        $rationales = $data['rationales'] ?? [];
        foreach (array_keys($rationales) as $id) {
            abort_unless(ctype_digit((string) $id) && in_array((int) $id, $draft['review_ids'], true), 422);
        }
        $draft['rationales'] = $rationales;
        $request->session()->put('location_counts.'.$token, $draft);
        if ($data['action'] === 'edit') {
            return to_route('reconcile.enter', ['token' => $token]);
        }
        $counts = array_intersect_key($draft['counts'], array_flip($draft['review_ids']));
        try {
            $id = app(DailyInventoryPosting::class)->counts((int) $request->user()->id, $draft['criteria']['location_id'], $token, $counts, $rationales);
        } catch (QueryException) {
            return back()->withInput()->with('error', 'No count updates were saved. Your inputs are retained; try again.');
        }
        $draft['result'] = $id;
        $request->session()->put('location_counts.'.$token, $draft);

        return to_route('reconcile.result', ['operation' => $id]);
    }

    public function result(Request $request, int $operation)
    {
        $this->ready();
        $row = DB::table('inventory_count_operations')->where('id', $operation)->firstOrFail();
        $draft = $request->session()->get('location_counts.'.$row->operation_id);
        $returnUrl = route('reconcile.index', absolute: false);
        if (isset($draft['list']) && $draft['actor'] === (int) $request->user()->id
            && ($request->user()->hasRole('admin') || $request->user()->hasRole('manager'))) {
            $returnUrl = route('reconcile.index', ['context' => $row->operation_id], absolute: false);
        }

        return view('reconcile.result', ['operation' => $row, 'returnUrl' => $returnUrl, 'location' => app(CatalogRecords::class)->record('storage_locations', $row->storage_location_id), 'changes' => DB::table('inventory_count_items')->join('inventory_adjustments', 'inventory_adjustments.id', '=', 'inventory_count_items.inventory_adjustment_id')->where('inventory_count_operation_id', $operation)->orderByRaw('NATURAL_SORT_KEY(item_name)')->orderBy('item_name')->get()]);
    }
}
