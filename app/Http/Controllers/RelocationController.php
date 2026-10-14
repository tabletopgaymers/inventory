<?php

namespace App\Http\Controllers;

use App\Support\CatalogRecords;
use App\Support\InventorySearch;
use App\Support\RequestCatalog;
use App\Support\RequestValues;
use App\Support\RequestStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RelocationController
{
    public function create(Request $request)
    {
        $token = (string) Str::uuid();
        $central = app(CatalogRecords::class)->records('storage_locations')->first(fn ($location) => $location->is_central && $location->state === 'active');
        $request->session()->put('relocation_workspace.'.$token, [
            'actor' => (int) $request->user()->id, 'id' => null, 'revision' => null,
            'title' => '', 'source_location_id' => $central?->id, 'destination_location_id' => null, 'details' => '', 'lines' => [],
        ]);

        return redirect('/relocation/work/'.$token);
    }

    private function workspace(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $work = $request->session()->get('relocation_workspace.'.$token);
        abort_unless(is_array($work) && $work['actor'] === (int) $request->user()->id, 404);

        return $work;
    }

    public function show(Request $request, int $id)
    {
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $token = (string) Str::uuid();
        $request->session()->put('relocation_workspace.'.$token, [
            'actor' => (int) $request->user()->id, 'id' => (int) $row->id, 'revision' => (int) $row->revision,
            'title' => $row->title, 'source_location_id' => $row->source_location_id,
            'destination_location_id' => $row->destination_location_id, 'details' => $row->details,
            'lines' => DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->all(),
        ]);

        return redirect('/relocation/work/'.$token);
    }

    public function work(Request $request, string $token)
    {
        $work = $this->workspace($request, $token);
        $editable = true;
        $status = 'New';
        $submitter = RequestValues::manages($request->user(), 'manager') || RequestValues::submitsOwn($request->user());
        if ($work['id']) {
            $row = DB::table('relocation_requests')->where('id', $work['id'])->firstOrFail();
            $editable = $row->status === 'Draft' && ((int) $row->owner_id === (int) $request->user()->id || RequestValues::manages($request->user(), 'manager'));
            $status = $row->status;
        }
        $tab = $request->query('tab', $request->query('step', 'basic'));
        if (! in_array($tab, ['basic', 'material', 'review', 'fulfillment', 'tracking', 'ship', 'reconcile'], true)) {
            $tab = 'basic';
        }

        return view('relocation.work', ['work' => $work, 'token' => $token, 'editable' => $editable, 'status' => $status, 'submitter' => $submitter, 'manager' => RequestValues::manages($request->user(), 'manager'),
            'items' => app(CatalogRecords::class)->records('items'),
            'materialItems' => app(RequestCatalog::class)->rows(array_map('intval', array_keys($work['lines'] ?? [])), $work['source_location_id'] ? (int) $work['source_location_id'] : null, $work['destination_location_id'] ? (int) $work['destination_location_id'] : null),
            'fulfilled' => $work['id'] ? DB::table('relocation_request_lines')->where('relocation_request_id', $work['id'])->pluck('fulfillment_quantity', 'item_id')->all() : [],
            'addedStages' => $work['id'] ? DB::table('relocation_request_lines')->where('relocation_request_id', $work['id'])->pluck('added_stage', 'item_id')->all() : [],
            'received' => $work['id'] ? DB::table('relocation_fulfillment_lines')->where('relocation_request_id', $work['id'])->pluck('received', 'item_id')->all() : [],
            'tracking' => $work['id'] ? (json_decode(DB::table('relocation_fulfillment')->where('relocation_request_id', $work['id'])->value('data') ?? '{}', true)['tracking'] ?? []) : [],
            'collections' => app(RequestCatalog::class)->choices()['collections'],
            'locations' => app(CatalogRecords::class)->records('storage_locations')->filter(fn ($location) => $location->state === 'active' || in_array((int) $location->id, [(int) $work['source_location_id'], (int) $work['destination_location_id']], true)),
            'step' => $tab]);
    }

    public function print(Request $request, string $token)
    {
        $work = $this->workspace($request, $token);
        abort_unless($work['id'], 404);
        $row = DB::table('relocation_requests')->where('id', $work['id'])->firstOrFail();
        $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->get()->keyBy('item_id');

        return view('relocation.print', [
            'row' => $row, 'lines' => $lines,
            'items' => app(RequestCatalog::class)->rows($lines->keys()->map(fn ($id) => (int) $id)->all(), (int) $row->source_location_id, (int) $row->destination_location_id),
            'sourceName' => DB::table('storage_locations')->where('id', $row->source_location_id)->value('name'),
            'destinationName' => DB::table('storage_locations')->where('id', $row->destination_location_id)->value('name'),
            'requesterName' => \App\Models\User::find($row->owner_id)?->displayName() ?? 'Unknown',
        ]);
    }

    public function save(Request $request, string $token)
    {
        $work = $this->workspace($request, $token);
        $data = $request->validate([
            'action' => ['required', 'in:save,next,submit'], 'title' => ['required', 'string', 'max:255'],
            'source_location_id' => ['required', 'integer', 'min:1'],
            'destination_location_id' => ['required', 'integer', 'min:1', 'different:source_location_id'],
            'details' => ['nullable', 'string', 'max:5000'],
            'lines' => ['nullable', 'array', 'max:1000'],
            'lines.*' => ['nullable', 'string', 'max:30'],
        ], [
            'title.required' => 'Enter a title.',
            'source_location_id.required' => 'Choose a source location.',
            'destination_location_id.required' => 'Choose a destination location.',
            'destination_location_id.different' => 'Choose a destination different from source.',
        ]);
        $fields = ['title' => RequestValues::text($data['title'], 'title', 255, true),
            'source_location_id' => (int) $data['source_location_id'], 'destination_location_id' => (int) $data['destination_location_id'],
            'details' => RequestValues::text($data['details'] ?? null, 'details')];
        $lines = [];
        $submitting = $data['action'] === 'submit';
        foreach ($data['lines'] ?? [] as $item => $quantity) {
            $itemId = RequestValues::id($item, 'lines');
            if (! $itemId) throw ValidationException::withMessages(['lines' => 'Choose a catalog item.']);
            $value = trim((string) ($quantity ?? ''));
            if ($submitting && $value === '') {
                throw ValidationException::withMessages(['lines.'.$itemId => 'Enter a whole-number requested quantity before submitting.']);
            }
            if ($value !== '' && (! preg_match('/\A-?(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})\z/D', $value) || abs((int) str_replace(',', '', $value)) > 1000000000)) {
                throw ValidationException::withMessages(['lines.'.$itemId => 'Enter a whole-number quantity from -1,000,000,000 to 1,000,000,000, or leave blank while drafting.']);
            }
            $lines[$itemId] = $value === '' ? null : (int) str_replace(',', '', $value);
        }
        ksort($lines);
        if ($submitting && $lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one material item before submitting.']);
        }
        $saved = DB::transaction(function () use ($request, $work, $fields, $lines, $submitting) {
            $actor = RequestValues::actor((int) $request->user()->id);
            $row = $work['id'] ? DB::table('relocation_requests')->where('id', $work['id'])->lockForUpdate()->firstOrFail() : null;
            if ($row) {
                abort_unless($row->status === 'Draft' && ((int) $row->owner_id === (int) $actor->id || RequestValues::manages($actor, 'manager')), 403);
                RequestValues::revision($request->input('revision'), $row);
            }
            if ($submitting) {
                abort_unless($row && (RequestValues::manages($actor, 'manager') || ((int) $row->owner_id === (int) $actor->id && RequestValues::submitsOwn($actor))), 403);
            }
            foreach (['source_location_id', 'destination_location_id'] as $field) {
                RequestValues::association('storage_locations', $fields[$field], null, $field, true);
            }
            $oldLines = $row ? DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->orderBy('item_id')->pluck('quantity', 'item_id')->map(fn ($value) => $value === null ? null : (int) $value)->all() : [];
            foreach ($lines as $itemId => $quantity) {
                RequestValues::association('items', $itemId, array_key_exists($itemId, $oldLines) ? $itemId : null, 'lines.'.$itemId);
            }
            if (! $row) {
                $id = DB::table('relocation_requests')->insertGetId($fields + ['owner_id' => $actor->id, 'status' => 'Draft', 'revision' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
                RequestValues::activity('relocation', $id, $actor, 'Created Draft', null, $fields + ['lines' => $lines]);
            } else {
                $id = $row->id;
                $before = array_intersect_key((array) $row, $fields);
                $before['source_location_id'] = (int) $before['source_location_id'];
                $before['destination_location_id'] = (int) $before['destination_location_id'];
                if ($before !== $fields || $oldLines !== $lines || $submitting) {
                    DB::table('relocation_requests')->where('id', $id)->update($fields + ['status' => $submitting ? 'Requested' : 'Draft', 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
                    if ($before !== $fields || $oldLines !== $lines) {
                        RequestValues::activity('relocation', $id, $actor, 'Saved request', $before + ['lines' => $oldLines], $fields + ['lines' => $lines]);
                    }
                    if ($submitting) RequestValues::activity('relocation', $id, $actor, 'Status changed', ['status' => 'Draft'], ['status' => 'Requested']);
                }
            }
            DB::table('relocation_request_lines')->where('relocation_request_id', $id)->whereNotIn('item_id', array_keys($lines))->delete();
            foreach ($lines as $itemId => $quantity) {
                DB::table('relocation_request_lines')->updateOrInsert(['relocation_request_id' => $id, 'item_id' => $itemId], ['quantity' => $quantity]);
            }

            return DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        });
        $work = array_replace($work, $fields, ['id' => (int) $saved->id, 'revision' => (int) $saved->revision, 'lines' => $lines]);
        $request->session()->put('relocation_workspace.'.$token, $work);
        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'id' => $work['id'], 'revision' => $work['revision'], 'status' => $saved->status, 'values' => $fields, 'lines' => $lines]);
        }

        return redirect('/relocation/work/'.$token.($submitting ? '?tab=review' : ($data['action'] === 'next' ? '?tab=material' : '')))->with('success', $submitting ? 'Request submitted.' : 'Progress saved.');
    }

    public function search(Request $request, string $token)
    {
        $work = $this->workspace($request, $token);
        abort_unless($work['id'], 409, 'Save Basic info first.');
        $row = DB::table('relocation_requests')->where('id', $work['id'])->firstOrFail();
        abort_unless(($row->status === 'Draft' && ((int) $row->owner_id === (int) $request->user()->id || RequestValues::manages($request->user(), 'manager'))) || ($row->status === 'Accepted' && RequestValues::manages($request->user(), 'manager')), 403);
        $data = $request->validate([
            'source_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'destination_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'excluded' => ['nullable', 'array', 'max:1000'], 'excluded.*' => ['integer'],
        ]);
        $criteria = app(InventorySearch::class)->criteria($request);
        $results = app(RequestCatalog::class)->selections($criteria, array_map('intval', $data['excluded'] ?? []), (int) $data['source_location_id'], (int) $data['destination_location_id']);

        $sourceName = DB::table('storage_locations')->where('id', $data['source_location_id'])->value('name');
        $destinationName = DB::table('storage_locations')->where('id', $data['destination_location_id'])->value('name');

        return view('relocation.search-results', compact('results', 'sourceName', 'destinationName'));
    }

    public function accept(Request $request, string $token)
    {
        $work = $this->workspace($request, $token);
        abort_unless($work['id'], 409);
        $row = DB::transaction(function () use ($request, $work) {
            $actor = RequestValues::actor((int) $request->user()->id);
            abort_unless(RequestValues::manages($actor, 'manager'), 403);
            $row = DB::table('relocation_requests')->where('id', $work['id'])->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'Requested', 409);
            RequestValues::revision($request->input('revision'), $row);
            DB::table('relocation_requests')->where('id', $row->id)->update(['status' => 'Accepted', 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
            RequestValues::activity('relocation', $row->id, $actor, 'Status changed', ['status' => 'Requested'], ['status' => 'Accepted']);

            return DB::table('relocation_requests')->where('id', $row->id)->firstOrFail();
        });

        return $this->fulfillmentResponse($request, $token, $work, $row);
    }

    public function saveFulfillment(Request $request, string $token)
    {
        $work = $this->workspace($request, $token);
        abort_unless($work['id'], 409);
        $data = $request->validate(['fulfillment' => ['nullable', 'array', 'max:1000'], 'fulfillment.*' => ['nullable', 'string', 'max:30']]);
        $row = DB::transaction(function () use ($request, $work, $data) {
            $actor = RequestValues::actor((int) $request->user()->id);
            abort_unless(RequestValues::manages($actor, 'manager'), 403);
            $row = DB::table('relocation_requests')->where('id', $work['id'])->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'Accepted', 409);
            RequestValues::revision($request->input('revision'), $row);
            $existing = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->orderBy('item_id')->get()->keyBy('item_id');
            $before = [];
            foreach ($existing as $id => $line) $before[$id] = ['requested' => $line->quantity === null ? null : (int) $line->quantity, 'fulfilled' => $line->fulfillment_quantity === null ? null : (int) $line->fulfillment_quantity, 'added_stage' => $line->added_stage];
            foreach ($existing as $id => $line) {
                if ($line->added_stage !== 'fulfillment' && ! array_key_exists($id, $data['fulfillment'] ?? [])) {
                    throw ValidationException::withMessages(['fulfillment' => 'Original requested items cannot be removed during fulfillment.']);
                }
            }
            $after = [];
            foreach ($data['fulfillment'] ?? [] as $id => $quantity) {
                $itemId = RequestValues::id($id, 'fulfillment');
                abort_unless($itemId, 422);
                $line = $existing->get($itemId);
                RequestValues::association('items', $itemId, $line ? $itemId : null, 'fulfillment.'.$itemId);
                $after[$itemId] = ['requested' => $line ? ($line->quantity === null ? null : (int) $line->quantity) : 0,
                    'fulfilled' => RequestValues::quantity($quantity, 'fulfillment.'.$itemId, true),
                    'added_stage' => $line?->added_stage ?? 'fulfillment'];
            }
            ksort($after);
            if ($after !== $before) {
                DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->where('added_stage', 'fulfillment')->whereNotIn('item_id', array_keys($after))->delete();
                foreach ($after as $id => $values) DB::table('relocation_request_lines')->updateOrInsert(['relocation_request_id' => $row->id, 'item_id' => $id], ['quantity' => $values['requested'], 'fulfillment_quantity' => $values['fulfilled'], 'added_stage' => $values['added_stage']]);
                DB::table('relocation_requests')->where('id', $row->id)->update(['revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
                RequestValues::activity('relocation', $row->id, $actor, 'Saved fulfillment preparation', $before, $after);
            }

            return DB::table('relocation_requests')->where('id', $row->id)->firstOrFail();
        });

        return $this->fulfillmentResponse($request, $token, $work, $row);
    }

    private function fulfillmentResponse(Request $request, string $token, array $work, object $row)
    {
        $fields = array_intersect_key((array) $row, array_flip(['title', 'details', 'source_location_id', 'destination_location_id']));
        $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->pluck('quantity', 'item_id')->all();
        $fulfilled = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->pluck('fulfillment_quantity', 'item_id')->all();
        $addedStages = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->pluck('added_stage', 'item_id')->all();
        $request->session()->put('relocation_workspace.'.$token, array_replace($work, $fields, ['revision' => (int) $row->revision, 'lines' => $lines]));
        if ($request->expectsJson()) return response()->json(['saved' => true, 'id' => (int) $row->id, 'revision' => (int) $row->revision, 'status' => $row->status, 'values' => $fields, 'lines' => $lines, 'fulfilled' => $fulfilled, 'addedStages' => $addedStages]);

        return redirect('/relocation/work/'.$token.'?tab=fulfillment')->with('success', 'Fulfillment progress saved.');
    }

    public function ship(Request $request, string $token)
    {
        return $this->saveTracking($request, $token, true);
    }

    public function saveTracking(Request $request, string $token, bool $shipping = false)
    {
        $work = $this->workspace($request, $token);
        abort_unless($work['id'], 409);
        $data = $request->validate(['tracking' => ['nullable', 'array', 'max:50'], 'tracking.*' => ['array'],
            'tracking.*.name' => ['nullable', 'string', 'max:255'],
            'tracking.*.carrier' => ['nullable', 'string', 'in:USPS,UPS,FedEx,Other'],
            'tracking.*.number' => ['nullable', 'string', 'max:255']]);
        $tracking = [];
        foreach ($data['tracking'] ?? [] as $index => $line) {
            $carrier = $line['carrier'] ?? null;
            $number = RequestValues::text($line['number'] ?? null, 'tracking.'.$index.'.number', 255);
            if (! $carrier && $number === null) continue;
            if (! $carrier) throw ValidationException::withMessages(['tracking.'.$index.'.carrier' => 'Choose a shipper for this tracking number.']);
            $name = RequestValues::text($line['name'] ?? null, 'tracking.'.$index.'.name', 255) ?? 'Package '.(count($tracking) + 1);
            $tracking[] = ['name' => $name, 'carrier' => $carrier, 'number' => $number ?? '', 'url' => null];
        }
        $row = DB::transaction(function () use ($request, $work, $tracking, $shipping) {
            $actor = RequestValues::actor((int) $request->user()->id);
            abort_unless(RequestValues::manages($actor, 'manager'), 403);
            $row = DB::table('relocation_requests')->where('id', $work['id'])->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status, ['Accepted', 'Fulfilled', 'Received', 'Completed'], true), 409);
            if ($shipping) abort_unless($row->status === 'Accepted', 409, 'Only an Accepted request can be shipped.');
            RequestValues::revision($request->input('revision'), $row);
            $header = RequestStock::header('relocation', $row->id);
            $before = $header['tracking'] ?? [];
            if ($shipping) {
                $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->orderBy('item_id')->lockForUpdate()->get();
                if ($lines->isEmpty()) throw ValidationException::withMessages(['fulfillment' => 'Add at least one material item before shipping.']);
                $quantities = [];
                $errors = [];
                foreach ($lines as $line) {
                    try {
                        $quantities[$line->item_id] = RequestValues::quantity($line->fulfillment_quantity, 'fulfillment.'.$line->item_id);
                    } catch (ValidationException $exception) {
                        $errors += $exception->errors();
                    }
                }
                if ($errors) throw ValidationException::withMessages($errors);
                $stock = app(RequestStock::class);
                $items = $stock->items(array_keys($quantities));
                $source = DB::table('storage_locations')->where('id', $row->source_location_id)->value('name');
                $operation = (string) Str::uuid();
                foreach ($quantities as $itemId => $quantity) {
                    if ($quantity > 0) {
                        $stock->storage($itemId, (int) $row->source_location_id, -$quantity);
                        $stock->source($itemId, 'transit', $quantity);
                        $stock->entry($actor, 'relocation', (int) $row->id, $items[$itemId], $operation, 'shipment', 'Relocation: '.$source.' → In Transit', $quantity, $items[$itemId]->unit_cost);
                    }
                    DB::table('relocation_fulfillment_lines')->updateOrInsert(['relocation_request_id' => $row->id, 'item_id' => $itemId], ['sent' => $quantity]);
                }
                $header['tracking'] = $tracking;
                $header['shipment_operation'] = $operation;
                $header['shipment_actor'] = (int) $actor->id;
                $header['shipped_date'] = now()->toDateString();
                RequestStock::saveHeader('relocation', $row->id, $header);
                RequestStock::changed('relocation', $row, $actor, 'Confirmed shipment', ['fulfilled' => $quantities, 'tracking' => $tracking, 'shipped_date' => $header['shipped_date']], 'Fulfilled');

                return DB::table('relocation_requests')->where('id', $row->id)->firstOrFail();
            }
            if ($before !== $tracking) {
                $header['tracking'] = $tracking;
                RequestStock::saveHeader('relocation', $row->id, $header);
                DB::table('relocation_requests')->where('id', $row->id)->update(['revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
                RequestValues::activity('relocation', $row->id, $actor, 'Shipping information changed', ['tracking' => $before], ['tracking' => $tracking]);
            }

            return DB::table('relocation_requests')->where('id', $row->id)->firstOrFail();
        });
        $request->session()->put('relocation_workspace.'.$token, array_replace($work, ['revision' => (int) $row->revision]));
        if ($request->expectsJson()) return response()->json(['saved' => true, 'id' => (int) $row->id, 'revision' => (int) $row->revision, 'status' => $row->status, 'tracking' => $tracking]);

        return redirect('/relocation/work/'.$token.'?tab='.($shipping ? 'ship' : 'tracking'))->with('success', $shipping ? 'Request shipped. Stock moved from Source to In Transit.' : 'Tracking saved.');
    }

    public function balances(Request $request, string $token)
    {
        $this->workspace($request, $token);
        $data = $request->validate([
            'source_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'destination_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'items' => ['nullable', 'array', 'max:1000'], 'items.*' => ['integer'],
        ]);
        $rows = app(RequestCatalog::class)->rows(array_map('intval', $data['items'] ?? []), (int) $data['source_location_id'], (int) $data['destination_location_id']);

        $balances = [];
        foreach ($rows as $row) $balances[$row['id']] = ['source' => $row['source'], 'destination' => $row['destination']];

        return response()->json(['balances' => $balances]);
    }

    public function index()
    {
        $rows = DB::table('relocation_requests')
            ->leftJoin('storage_locations as source', 'source.id', '=', 'relocation_requests.source_location_id')
            ->leftJoin('storage_locations as destination', 'destination.id', '=', 'relocation_requests.destination_location_id')
            ->select('relocation_requests.*', 'source.name as source_name', 'destination.name as destination_name')
            ->orderByDesc('relocation_requests.updated_at')->orderByDesc('relocation_requests.id')->get();

        return view('relocation.index', compact('rows'));
    }
}
