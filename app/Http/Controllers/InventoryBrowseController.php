<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\InventorySearch;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryBrowseController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady'], 503, 'Inventory browsing is not ready.');
    }

    private function context(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $context = $request->session()->get('inventory_browse.'.$token);
        abort_unless(is_array($context) && $context['actor'] === (int) $request->user()->id, 404);

        return $context;
    }

    private function render(Request $request, array $pending, ?array $result = null, ?string $token = null, int $scroll = 0, ?array $submitted = null)
    {
        return view('inventory.browse', ['pending' => $pending, 'submitted' => $submitted, 'result' => $result, 'token' => $token, 'scroll' => $scroll,
            'collections' => DB::table('collections')->join('categories', 'categories.id', '=', 'collections.category_id')->select('collections.*', 'categories.name as category_name')->orderBy('categories.name')->orderBy('collections.name')->get(), 'allColumns' => app(InventorySearch::class)->columns()]);
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
        $context = $this->context($request, $token);
        $result = app(InventorySearch::class)->results($context['submitted']);
        $context['export'] = $result;
        $context['export_expires'] = now()->timestamp + 1800;
        $request->session()->put('inventory_browse.'.$token, $context);

        return $this->render($request, $context['pending'], $result, $token, $context['scroll'], $context['submitted']);
    }

    public function pending(Request $request)
    {
        $this->ready();
        $criteria = app(InventorySearch::class)->criteria($request);
        $extra = $request->validate(['context' => ['nullable', 'uuid'], 'scroll' => ['nullable', 'integer', 'min:0', 'max:10000000'], 'destination' => ['nullable', 'string', 'regex:#\A/inventory/(items|locations)/[0-9]+\z#D']]);
        try {
            $context = ! empty($extra['context']) ? $this->context($request, $extra['context']) : null;
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
        $context = $this->context($request, $token);
        abort_unless(isset($context['export']) && $context['export_expires'] >= now()->timestamp && count($context['export']['rows']) > 0, 410, 'Displayed results expired. Return to results to refresh before downloading.');
        $result = $context['export'];

        return response()->streamDownload(function () use ($result) {
            $stream = fopen('php://output', 'w');
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
        $row = app(CatalogRecords::class)->record('storage_locations', $location);
        $criteria = app(InventorySearch::class)->defaults();
        $criteria['include_inactive'] = true;
        $criteria['columns'] = ['storage:'.$location];

        return view('inventory.location', ['location' => $row, 'result' => app(InventorySearch::class)->results($criteria), 'browse' => $request->query('browse')]);
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
