<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\InventorySearch;
use App\Support\RelocationFulfillment;
use App\Support\RelocationProgress;
use App\Support\RelocationRequests;
use App\Support\RequestCatalog;
use App\Support\RequestValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RelocationRequestController
{
    use RequestPageSupport;

    public function index()
    {
        $this->ready();

        return view('requests.index', ['kind' => 'relocation', 'rows' => DB::table('relocation_requests')->leftJoin('storage_locations as source', 'source.id', '=', 'relocation_requests.source_location_id')->leftJoin('storage_locations as destination', 'destination.id', '=', 'relocation_requests.destination_location_id')->select('relocation_requests.*', 'source.name as source_name', 'destination.name as destination_name')->orderByDesc('relocation_requests.updated_at')->orderByDesc('relocation_requests.id')->get()]);
    }

    private function newWork(Request $request, array $data): string
    {
        $token = (string) Str::uuid();
        $request->session()->put('relocation_work.'.$token, ['actor' => (int) $request->user()->id, 'id' => null, 'revision' => null, 'title' => '', 'details' => '', 'source_location_id' => null, 'destination_location_id' => null, 'lines' => [], 'criteria' => null, 'reviewed' => false] + $data);
        // Explicit overrides retain loaded edits, without accepting client ownership.
        $request->session()->put('relocation_work.'.$token, array_replace($request->session()->get('relocation_work.'.$token), $data));
        $draft = $request->session()->get('relocation_work.'.$token);
        $draft['saved_values'] = RelocationProgress::snapshot($draft);
        $request->session()->put('relocation_work.'.$token, $draft);

        return $token;
    }

    private function draft(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('relocation_work.'.$token);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id, 404);
        if (! isset($draft['saved_values'])) {
            $saved = $draft['id'] ? DB::table('relocation_requests')->where('id', $draft['id'])->firstOrFail() : null;
            $baseline = $saved ? (array) $saved : ['source_location_id' => app(CatalogRecords::class)->records('storage_locations')->first(fn ($location) => $location->is_central && $location->state === 'active')?->id];
            $baseline['lines'] = $saved ? DB::table('relocation_request_lines')->where('relocation_request_id', $saved->id)->pluck('quantity', 'item_id')->all() : [];
            $draft['saved_values'] = RelocationProgress::snapshot($baseline);
            $request->session()->put('relocation_work.'.$token, $draft);
        }

        return $draft;
    }

    private function allowed(Request $request, array $draft): void
    {
        if ($draft['id'] !== null) {
            $row = DB::table('relocation_requests')->where('id', $draft['id'])->firstOrFail();
            abort_unless(app(RelocationRequests::class)->editable($request->user(), $row), 403);
        }
    }

    public function create(Request $request)
    {
        $this->ready();
        $central = app(CatalogRecords::class)->records('storage_locations')->first(fn ($location) => $location->is_central && $location->state === 'active');
        $token = $this->newWork($request, ['source_location_id' => $central?->id]);

        return redirect('/relocations/work/'.$token);
    }

    public function edit(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        abort_unless(app(RelocationRequests::class)->editable($request->user(), $row), 403);
        $resume = $request->session()->get('relocation_resume.'.$id);
        if (is_string($resume) && is_array($request->session()->get('relocation_work.'.$resume))) {
            $working = $this->draft($request, $resume);
            if (! isset($working['result']) && $working['id'] === $id) {
                return redirect('/relocations/work/'.$resume.($request->query('step') === 'items' ? '?step=items' : ''));
            }
        }
        $token = $this->newWork($request, ['id' => $id, 'revision' => (int) $row->revision, 'title' => $row->title, 'details' => $row->details, 'source_location_id' => $row->source_location_id, 'destination_location_id' => $row->destination_location_id,
            'lines' => DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->map(fn ($q) => (string) $q)->all()]);
        $request->session()->put('relocation_resume.'.$id, $token);

        return redirect('/relocations/work/'.$token.($request->query('step') === 'items' ? '?step=items' : ''));
    }

    public function copy(Request $request, int $id)
    {
        $this->ready();
        $this->identity($request);
        DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $token = $this->newWork($request, ['lines' => DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->map(fn ($q) => (string) $q)->all()]);
        $draft = $request->session()->get('relocation_work.'.$token);
        $draft['saved_values']['lines'] = [];
        $request->session()->put('relocation_work.'.$token, $draft);

        return redirect('/relocations/work/'.$token);
    }

    private function workData(Request $request, array $draft): array
    {
        $catalog = app(RequestCatalog::class);
        $source = RequestValues::id($draft['source_location_id'], 'source_location_id');
        $destination = RequestValues::id($draft['destination_location_id'], 'destination_location_id');
        $ids = array_map('intval', array_keys($draft['lines']));
        $row = $draft['id'] ? DB::table('relocation_requests')->where('id', $draft['id'])->firstOrFail() : null;

        return $catalog->choices() + ['draft' => $draft, 'row' => $row, 'rows' => $catalog->rows($ids, $source, $destination),
            'results' => $draft['criteria'] === null ? [] : $catalog->selections($draft['criteria'], $ids, $source, $destination),
            'submitter' => RequestValues::manages($request->user(), 'manager') || (($row === null || (int) $row->owner_id === (int) $request->user()->id) && RequestValues::submitsOwn($request->user()))];
    }

    public function work(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);
        if (isset($draft['result'])) {
            return redirect('/relocations/'.$draft['result']);
        }
        $this->allowed($request, $draft);

        return view('requests.relocation-work', $this->workData($request, $draft) + ['token' => $token, 'step' => $request->query('step') === 'items' ? 'items' : 'locations']);
    }

    public function updateWork(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $draft = $this->draft($request, $token);
            abort_if(isset($draft['result']), 409);
            $this->allowed($request, $draft);
            $data = $request->validate(['action' => ['required', 'in:save,progress,review,search,show_all,reset,add,remove,items,locations'], 'lines' => ['nullable', 'array', 'max:1000'], 'lines.*' => ['nullable', 'string', 'max:255'], 'title' => ['nullable', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:5000'], 'source_location_id' => ['nullable', 'integer', 'min:1'], 'destination_location_id' => ['nullable', 'integer', 'min:1']]);
            $draft = array_replace($draft, array_intersect_key($data, array_flip(['title', 'details', 'source_location_id', 'destination_location_id'])));
            $draft['title'] = $data['title'] ?? '';
            $draft['details'] = $data['details'] ?? '';
            $draft['source_location_id'] = $data['source_location_id'] ?? null;
            $draft['destination_location_id'] = $data['destination_location_id'] ?? null;
            $draft['lines'] = $data['lines'] ?? [];
            $draft['selection'] = $request->input('selection', []);
            $wasReviewed = $draft['reviewed'];
            $draft['reviewed'] = $data['action'] === 'progress' && $request->input('progress_step') === 'review' && $wasReviewed;
            $draft['dirty'] = RelocationProgress::dirty($draft);
            $draft['version'] = ($draft['version'] ?? 0) + 1;
            $request->session()->put('relocation_work.'.$token, $draft);
            $action = $data['action'];
            if (in_array($action, ['save', 'progress', 'review'], true) && is_array($draft['selection']) && array_filter($draft['selection'], fn ($quantity) => $quantity !== null && $quantity !== '')) {
                throw ValidationException::withMessages(['selection' => 'Use Add Selected or clear pending quantities before saving or reviewing.']);
            }
            $draft['search_fields'] = ['search' => $request->input('search', ''), 'collections' => $request->input('collections', [])];
            if (in_array($action, ['search', 'show_all', 'reset'], true)) {
                if (is_array($draft['selection']) && array_filter($draft['selection'], fn ($quantity) => $quantity !== null && $quantity !== '')) {
                    throw ValidationException::withMessages(['selection' => 'Use Add Selected or clear pending quantities before changing search results.']);
                }
                $draft['criteria'] = $action === 'reset' ? null : app(InventorySearch::class)->criteria($request);
                if ($action === 'show_all') {
                    $draft['criteria']['search'] = '';
                }
                $draft['search_fields'] = $draft['criteria'] ?? ['search' => '', 'collections' => []];
            }
            if ($action === 'remove') {
                $item = RequestValues::id($request->input('remove'), 'remove');
                abort_unless($item && array_key_exists($item, $draft['lines']), 422);
                unset($draft['lines'][$item]);
            }
            if ($action === 'add') {
                $selection = $request->input('selection', []);
                if (! is_array($selection) || count($selection) > 1000) {
                    throw ValidationException::withMessages(['selection' => 'Choose displayed catalog items.']);
                }
                $allowed = array_column($this->workData($request, $draft)['results'], 'id');
                foreach ($selection as $item => $quantity) {
                    if ($quantity === null || $quantity === '') {
                        continue;
                    }
                    $itemId = RequestValues::id($item, 'selection');
                    abort_unless($itemId && in_array($itemId, $allowed, true), 422);
                    $draft['lines'][$itemId] = (string) RequestValues::quantity($quantity, 'selection.'.$item, false, true);
                    unset($draft['selection'][$item]);
                }
            }
            if ($action === 'items') {
                $old = $draft['id'] ? DB::table('relocation_requests')->where('id', $draft['id'])->firstOrFail() : null;
                app(RelocationRequests::class)->validate(array_replace($draft, ['lines' => []]), $old);
                if ((string) $draft['source_location_id'] === (string) $draft['destination_location_id']) {
                    throw ValidationException::withMessages(['destination_location_id' => 'Choose a Destination different from Source.']);
                }
            }
            if ($action === 'review') {
                DB::transaction(function () use ($request, $draft) {
                    RequestValues::actor((int) $request->user()->id);
                    $old = $draft['id'] ? DB::table('relocation_requests')->where('id', $draft['id'])->lockForUpdate()->firstOrFail() : null;
                    if ($old) {
                        RequestValues::revision($draft['revision'], $old);
                    }
                    app(RelocationRequests::class)->validate($draft, $old, true);
                });
                $draft['reviewed'] = true;
            }
            $request->session()->put('relocation_work.'.$token, $draft);
            if ($action === 'progress') {
                $step = $request->input('progress_step');
                abort_unless(in_array($step, ['locations', 'items', 'review'], true), 422);
                $old = $draft['id'] ? DB::table('relocation_requests')->where('id', $draft['id'])->firstOrFail() : null;
                if ($old) RequestValues::revision($request->input('revision'), $old);
                if ($step === 'review') {
                    abort_unless($wasReviewed && (! $old || $old->status === 'Draft'), 409);
                    app(RelocationRequests::class)->validate($draft, $old, true);
                }
                $id = app(RelocationRequests::class)->save((int) $request->user()->id, $draft['id'], $draft['revision'], $draft);
                $saved = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
                $draft = array_replace($draft, array_intersect_key((array) $saved, array_flip(['title', 'details', 'source_location_id', 'destination_location_id'])));
                $draft['lines'] = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->map(fn ($quantity) => (string) $quantity)->all();
                $draft['id'] = $id;
                $draft['revision'] = (int) $saved->revision;
                $draft['saved_values'] = RelocationProgress::snapshot($draft);
                $draft['dirty'] = false;
                $draft['reviewed'] = $step === 'review';
                $request->session()->put('relocation_work.'.$token, $draft);
                $request->session()->put('relocation_resume.'.$id, $token);
                $url = '/relocations/work/'.$token.($step === 'review' ? '/review' : ($step === 'items' ? '?step=items' : ''));

                return $request->expectsJson() ? response()->json(['saved' => true, 'version' => $draft['version'], 'id' => $id, 'status' => $saved->status, 'revision' => $draft['revision'], 'values' => RelocationProgress::snapshot($draft), 'message' => 'Progress saved. No stock was moved.']) : redirect($url)->with('success', 'Progress saved.');
            }
            if ($action === 'save') {
                $id = app(RelocationRequests::class)->save((int) $request->user()->id, $draft['id'], $draft['revision'], $draft);
                $draft['result'] = $id;
                $request->session()->put('relocation_work.'.$token, $draft);

                return redirect('/relocations/'.$id)->with('success', 'Request saved. No stock was moved.');
            }

            return redirect('/relocations/work/'.$token.($action === 'review' ? '/review' : ($action === 'locations' ? '' : '?step=items')));
        });
    }

    public function review(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);
        $this->allowed($request, $draft);
        abort_unless($draft['reviewed'], 409);

        return view('requests.relocation-review', $this->workData($request, $draft) + ['token' => $token]);
    }

    public function submit(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $draft = $this->draft($request, $token);
            if (isset($draft['result'])) {
                return redirect('/relocations/'.$draft['result']);
            }
            abort_unless($draft['reviewed'], 409);
            $id = app(RelocationRequests::class)->save((int) $request->user()->id, $draft['id'], $draft['revision'], $draft, true);
            $draft['result'] = $id;
            $request->session()->put('relocation_work.'.$token, $draft);

            return redirect('/relocations/'.$id)->with('success', 'Request submitted. No stock was moved.');
        });
    }

    public function show(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->get()->keyBy('item_id');

        return view('requests.relocation-show', app(RequestCatalog::class)->choices() + ['row' => $row, 'lines' => $lines,
            'fulfillmentReady' => app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'],
            'shipment' => app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'] ? app(RelocationFulfillment::class)->data($id) : [],
            'rows' => app(RequestCatalog::class)->rows($lines->keys()->map(fn ($id) => (int) $id)->all(), $row->source_location_id, $row->destination_location_id),
            'editable' => app(RelocationRequests::class)->editable($request->user(), $row), 'manager' => RequestValues::manages($request->user(), 'manager'),
            'owner' => DB::table('users')->where('id', $row->owner_id)->first(),
            'activity' => DB::table('relocation_request_activity')->where('relocation_request_id', $id)->orderByDesc('id')->get()]);
    }

    public function transition(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            $data = $request->validate(['action' => ['required', 'in:return,cancel']]);
            app(RelocationRequests::class)->transition((int) $request->user()->id, $id, $request->input('revision'), $data['action']);

            return redirect('/relocations/'.$id)->with('success', 'Request status saved.');
        });
    }

    public function title(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(RelocationRequests::class)->title((int) $request->user()->id, $id, $request->input('revision'), $request->input('title'));

            return redirect('/relocations/'.$id)->with('success', 'Title saved.');
        });
    }

    public function fulfillment(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(RelocationRequests::class)->fulfillment((int) $request->user()->id, $id, $request->input('revision'), $request->input('fulfillment', []));

            return redirect('/relocations/'.$id)->with('success', 'Fulfillment preparation saved. No shipment was posted.');
        });
    }

    public function progress(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            $request->validate(['progress_sections' => ['required', 'array', 'min:1', 'max:3'], 'progress_sections.*' => ['required', 'in:title,fulfillment,tracking'], 'tracking' => ['nullable', 'array', 'max:50']]);
            $saved = DB::transaction(function () use ($request, $id) {
                $actor = RequestValues::actor((int) $request->user()->id);
                $row = DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
                RequestValues::revision($request->input('revision'), $row);
                $canonical = [];
                foreach (array_unique($request->input('progress_sections')) as $section) {
                    // Each existing service rechecks its own field-specific role and lifecycle guard.
                    $revision = DB::table('relocation_requests')->where('id', $id)->value('revision');
                    if ($section === 'title') {
                        app(RelocationRequests::class)->title($actor->id, $id, $revision, $request->input('title'));
                        $canonical['title'] = ['title' => DB::table('relocation_requests')->where('id', $id)->value('title')];
                    } elseif ($section === 'fulfillment') {
                        app(RelocationRequests::class)->fulfillment($actor->id, $id, $revision, $request->input('fulfillment', []));
                        $canonical['fulfillment'] = [];
                        foreach (DB::table('relocation_request_lines')->where('relocation_request_id', $id)->get(['item_id', 'fulfillment_quantity']) as $line) {
                            $canonical['fulfillment']['fulfillment['.$line->item_id.']'] = $line->fulfillment_quantity === null ? '' : (string) $line->fulfillment_quantity;
                        }
                    } else {
                        app(RelocationFulfillment::class)->tracking($actor->id, $id, $revision, $request->input('tracking', []));
                        $tracking = app(RelocationFulfillment::class)->data($id)['tracking'];
                        $canonical['tracking'] = [];
                        foreach ($request->input('tracking', []) as $index => $unused) {
                            foreach (['carrier', 'number', 'url'] as $field) {
                                $canonical['tracking']['tracking['.$index.']['.$field.']'] = $tracking[$index][$field] ?? ($field === 'carrier' ? 'Other' : '');
                            }
                        }
                    }
                }

                return ['row' => DB::table('relocation_requests')->where('id', $id)->firstOrFail(), 'fields' => $canonical];
            }, 3);

            return $request->expectsJson() ? response()->json(['saved' => true, 'revision' => (int) $saved['row']->revision, 'fields' => $saved['fields'], 'message' => 'Progress saved. No stock was moved.']) : redirect('/relocations/'.$id)->with('success', 'Progress saved.');
        });
    }

    public function worksheet(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->get()->keyBy('item_id');

        return view('requests.packing-worksheet', app(RequestCatalog::class)->choices() + ['row' => $row, 'lines' => $lines,
            'rows' => app(RequestCatalog::class)->rows($lines->keys()->map(fn ($id) => (int) $id)->all(), $row->source_location_id, $row->destination_location_id)]);
    }
}
