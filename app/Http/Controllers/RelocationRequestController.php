<?php

namespace App\Http\Controllers;

use App\Support\InventorySearch;
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

        return $token;
    }

    private function draft(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('relocation_work.'.$token);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id, 404);

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
        $token = $this->newWork($request, []);

        return redirect('/relocations/work/'.$token);
    }

    public function edit(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        abort_unless(app(RelocationRequests::class)->editable($request->user(), $row), 403);
        $token = $this->newWork($request, ['id' => $id, 'revision' => (int) $row->revision, 'title' => $row->title, 'details' => $row->details, 'source_location_id' => $row->source_location_id, 'destination_location_id' => $row->destination_location_id,
            'lines' => DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->map(fn ($q) => (string) $q)->all()]);

        return redirect('/relocations/work/'.$token);
    }

    public function copy(Request $request, int $id)
    {
        $this->ready();
        $this->identity($request);
        DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $token = $this->newWork($request, ['lines' => DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->map(fn ($q) => (string) $q)->all()]);

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
            'submitter' => RequestValues::manages($request->user(), 'manager') || (($row === null || (int) $row->owner_id === (int) $request->user()->id) && RequestValues::elevated($request->user()))];
    }

    public function work(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);
        if (isset($draft['result'])) {
            return redirect('/relocations/'.$draft['result']);
        }
        $this->allowed($request, $draft);

        return view('requests.relocation-work', $this->workData($request, $draft) + ['token' => $token]);
    }

    public function updateWork(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $draft = $this->draft($request, $token);
            abort_if(isset($draft['result']), 409);
            $this->allowed($request, $draft);
            $data = $request->validate(['action' => ['required', 'in:save,review,search,show_all,reset,add,remove'], 'lines' => ['nullable', 'array', 'max:1000'], 'lines.*' => ['nullable', 'string', 'max:255'], 'title' => ['nullable', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:5000'], 'source_location_id' => ['nullable', 'integer', 'min:1'], 'destination_location_id' => ['nullable', 'integer', 'min:1']]);
            $draft = array_replace($draft, array_intersect_key($data, array_flip(['title', 'details', 'source_location_id', 'destination_location_id'])));
            $draft['title'] = $data['title'] ?? '';
            $draft['details'] = $data['details'] ?? '';
            $draft['source_location_id'] = $data['source_location_id'] ?? null;
            $draft['destination_location_id'] = $data['destination_location_id'] ?? null;
            $draft['lines'] = $data['lines'] ?? [];
            $draft['reviewed'] = false;
            $request->session()->put('relocation_work.'.$token, $draft);
            $action = $data['action'];
            if (in_array($action, ['search', 'show_all', 'reset'], true)) {
                $draft['criteria'] = $action === 'reset' ? null : app(InventorySearch::class)->criteria($request);
                if ($action === 'show_all') {
                    $draft['criteria']['search'] = '';
                }
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
            if ($action === 'save') {
                $id = app(RelocationRequests::class)->save((int) $request->user()->id, $draft['id'], $draft['revision'], $draft);
                $draft['result'] = $id;
                $request->session()->put('relocation_work.'.$token, $draft);

                return redirect('/relocations/'.$id)->with('success', 'Request saved. No stock was moved.');
            }

            return redirect('/relocations/work/'.$token.($action === 'review' ? '/review' : ''));
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

    public function worksheet(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->get()->keyBy('item_id');

        return view('requests.packing-worksheet', app(RequestCatalog::class)->choices() + ['row' => $row, 'lines' => $lines,
            'rows' => app(RequestCatalog::class)->rows($lines->keys()->map(fn ($id) => (int) $id)->all(), $row->source_location_id, $row->destination_location_id)]);
    }
}
