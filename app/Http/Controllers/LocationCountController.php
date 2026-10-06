<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\DailyInventoryPosting;
use App\Support\InventorySearch;
use App\Support\LocationCountSearch;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationCountController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['dailyInventoryReady'], 503, 'Location counts are not ready.');
    }

    private function access(Request $request): void
    {
        $this->ready();
        abort_unless($request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 403);
    }

    private function draft(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('location_counts.'.$token);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id, 404);

        return $draft;
    }

    private function rows(array $draft): array
    {
        $criteria = app(InventorySearch::class)->defaults();
        $criteria['include_inactive'] = true;

        return array_values(array_filter(app(InventorySearch::class)->results($criteria)['rows'], fn ($row) => in_array($row['id'], $draft['items'], true)));
    }

    public function index(Request $request)
    {
        $this->ready();
        $saved = app(LocationCountSearch::class)->saved((int) $request->user()->id);
        $criteria = null;
        $result = null;
        $token = null;
        if ($request->filled('saved')) {
            $key = $request->query('saved');
            abort_unless(is_string($key) && isset($saved[$key]), 404);
            $criteria = app(LocationCountSearch::class)->criteria(Request::create('/', 'POST', $saved[$key]['criteria']));
            $result = app(LocationCountSearch::class)->rows($criteria);
            $token = $this->newDraft($request, $criteria, $result);
        }
        if ($request->filled('context')) {
            $token = $request->query('context');
            abort_unless(is_string($token), 404);
            $draft = $this->draft($request, $token);
            $criteria = $draft['criteria'];
            $result = app(LocationCountSearch::class)->rows($criteria);
        }

        return view('inventory.count-search', ['criteria' => $criteria, 'rows' => $result, 'token' => $token, 'saved' => $saved,
            'locations' => app(CatalogRecords::class)->records('storage_locations'),
            'collections' => DB::table('collections')->join('categories', 'categories.id', '=', 'collections.category_id')->select('collections.*', 'categories.name as category_name')->orderBy('categories.name')->orderBy('collections.name')->get(),
            'canCount' => $request->user()->hasRole('admin') || $request->user()->hasRole('manager')]);
    }

    private function newDraft(Request $request, array $criteria, array $rows): string
    {
        $token = (string) Str::uuid();
        $request->session()->put('location_counts', [$token => ['actor' => (int) $request->user()->id, 'criteria' => $criteria, 'items' => array_column($rows, 'id'), 'counts' => [], 'reviewed' => false, 'review_ids' => [], 'rationales' => []]]);

        return $token;
    }

    public function search(Request $request)
    {
        $this->ready();
        $data = $request->validate(['action' => ['required', 'in:search,show_all,reset,save'], 'name' => ['nullable', 'string', 'max:100'], 'overwrite' => ['nullable', 'boolean']]);
        if ($data['action'] === 'reset') {
            return redirect('/inventory/location-counts');
        }
        $service = app(LocationCountSearch::class);
        $criteria = $service->criteria($request);
        if ($data['action'] === 'show_all') {
            $criteria['search'] = '';
        }
        try {
            if ($data['action'] === 'save') {
                $service->save((int) $request->user()->id, $data['name'] ?? '', $criteria, (bool) ($data['overwrite'] ?? false));
            }
            $token = $this->newDraft($request, $criteria, $service->rows($criteria));

            return redirect('/inventory/location-counts?context='.$token)->with('success', $data['action'] === 'save' ? 'Personal criteria saved.' : null);
        } catch (QueryException) {
            return back()->withInput()->with('error', 'Search could not be saved. Your inputs are retained; try again.');
        }
    }

    public function worksheet(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);

        return view('inventory.count-worksheet', ['location' => app(CatalogRecords::class)->record('storage_locations', $draft['criteria']['location_id']), 'rows' => app(LocationCountSearch::class)->rows($draft['criteria'])]);
    }

    public function enter(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        abort_if(isset($draft['result']), 409, 'This count has already been saved.');
        if ($draft['counts'] === [] && ! $draft['reviewed']) {
            $draft['items'] = array_column(app(LocationCountSearch::class)->rows($draft['criteria']), 'id');
            $request->session()->put('location_counts.'.$token, $draft);
        }

        return view('inventory.count-enter', ['draft' => $draft, 'rows' => $this->rows($draft), 'token' => $token, 'location' => app(CatalogRecords::class)->record('storage_locations', $draft['criteria']['location_id'])]);
    }

    public function review(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        abort_if(isset($draft['result']), 409);
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

        return redirect('/inventory/location-counts/'.$token.'/review');
    }

    public function reviewed(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        abort_unless($draft['reviewed'], 409);

        return view('inventory.count-review', ['draft' => $draft, 'rows' => array_values(array_filter($this->rows($draft), fn ($row) => in_array($row['id'], $draft['review_ids'], true))), 'token' => $token, 'location' => app(CatalogRecords::class)->record('storage_locations', $draft['criteria']['location_id'])]);
    }

    public function save(Request $request, string $token)
    {
        $this->access($request);
        $draft = $this->draft($request, $token);
        if (isset($draft['result'])) {
            return redirect('/inventory/location-counts/results/'.$draft['result']);
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
            return redirect('/inventory/location-counts/'.$token.'/enter');
        }
        $counts = array_intersect_key($draft['counts'], array_flip($draft['review_ids']));
        try {
            $id = app(DailyInventoryPosting::class)->counts((int) $request->user()->id, $draft['criteria']['location_id'], $token, $counts, $rationales);
        } catch (QueryException) {
            return back()->withInput()->with('error', 'No count updates were saved. Your inputs are retained; try again.');
        }
        $draft['result'] = $id;
        $request->session()->put('location_counts.'.$token, $draft);

        return redirect('/inventory/location-counts/results/'.$id);
    }

    public function result(Request $request, int $operation)
    {
        $this->ready();
        $row = DB::table('inventory_count_operations')->where('id', $operation)->firstOrFail();

        return view('inventory.count-result', ['operation' => $row, 'location' => app(CatalogRecords::class)->record('storage_locations', $row->storage_location_id), 'changes' => DB::table('inventory_count_items')->join('inventory_adjustments', 'inventory_adjustments.id', '=', 'inventory_count_items.inventory_adjustment_id')->where('inventory_count_operation_id', $operation)->orderBy('item_name')->get()]);
    }
}
