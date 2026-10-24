<?php

namespace App\Http\Controllers;

use App\Support\CatalogRecords;
use App\Support\PurchaseCosting;
use App\Support\PurchaseFulfillment;
use App\Support\PurchaseProgress;
use App\Support\PurchaseRequests;
use App\Support\RequestCatalog;
use App\Support\RequestStock;
use App\Support\RequestValues;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseController
{
    use RequestPageSupport;

    public function index()
    {
        $this->ready();

        return view('purchase.index', [
            'rows' => DB::table('purchase_requests')
                ->leftJoin('users', 'users.id', '=', 'purchase_requests.owner_id')
                ->select('purchase_requests.*', 'users.first_name', 'users.last_name')
                ->orderByDesc('purchase_requests.updated_at')
                ->orderByDesc('purchase_requests.id')
                ->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->ready();

        return $this->open($request);
    }

    public function show(Request $request, int $id)
    {
        $this->ready();

        return $this->open($request, DB::table('purchase_requests')->where('id', $id)->firstOrFail());
    }

    private function open(Request $request, ?object $row = null)
    {
        $token = (string) Str::uuid();
        $lines = $row ? DB::table('purchase_request_lines')->where('purchase_request_id', $row->id)->pluck('quantity', 'item_id')->all() : [];
        if ($row && $row->status === 'Draft') {
            $saved = RequestStock::header('purchase', $row->id)['singular_request_draft'] ?? null;
            if ($saved && (int) $saved['revision'] === (int) $row->revision) $lines = $saved['lines'];
        }
        $request->session()->put('purchase_workspace.'.$token, [
            'actor' => (int) $request->user()->id, 'id' => $row?->id, 'revision' => $row?->revision,
            'title' => $row?->title ?? '', 'suggested_merchant' => $row?->suggested_merchant ?? '',
            'details' => $row?->details ?? '', 'lines' => $lines, 'invoices' => [],
        ]);
        // The record URL opens a workspace through a second redirect.
        $request->session()->keep(['success', 'error']);

        return redirect('/purchase/work/'.$token.($request->has('tab') ? '?'.http_build_query($request->only('tab', 'mode')) : ''));
    }

    private function workspace(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $work = $request->session()->get('purchase_workspace.'.$token);
        abort_unless(is_array($work) && $work['actor'] === (int) $request->user()->id && $request->user()->enabled, 404);

        return $work;
    }

    public function work(Request $request, string $token)
    {
        $this->ready();
        $work = $this->workspace($request, $token);
        $row = $work['id'] ? DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail() : null;
        $tab = $request->query('tab', 'basic');
        abort_unless(in_array($tab, ['basic', 'material', 'review', 'order', 'invoice', 'charges', 'order-review', 'receive', 'confirmation'], true), 404);
        $procurement = RequestValues::manages($request->user(), 'procurement');
        $notes = $row ? DB::table('purchase_request_notes')->where('purchase_request_id', $row->id)->orderByDesc('id')->get() : collect();
        $common = compact('work', 'row', 'token', 'tab', 'procurement', 'notes') + [
            'requesterName' => $row ? (\App\Models\User::find($row->owner_id)?->displayName() ?? 'Unknown') : $request->user()->displayName(),
            'collections' => app(RequestCatalog::class)->choices()['collections'],
            'catalogItems' => app(CatalogRecords::class)->records('items', true),
        ];
        if (in_array($tab, ['basic', 'material', 'review'], true)) {
            $values = array_replace($work, $request->old());
            $errors = $this->requestErrors($values);

            return view('purchase.work', array_replace($common, [
                'searchDefinition' => app(\App\Support\InventorySearchControls::class)->definition(['filters' => ['collections']]),
                'work' => $values, 'editable' => ! $row || app(PurchaseRequests::class)->editable($request->user(), $row),
                'canSubmit' => (! $row || $row->status === 'Draft') && app(PurchaseRequests::class)->submitter($request->user(), $row),
                'items' => app(RequestCatalog::class)->rows(array_map('intval', array_keys($values['lines'])), naturalNames: true),
                'reviewErrors' => $errors,
            ]));
        }
        if (! $row) return redirect('/purchase/work/'.$token)->with('error', 'Save Basic info before preparing an order.');
        $mode = in_array($tab, ['receive', 'confirmation'], true) || $request->query('mode') === 'receipt' ? 'receipt' : 'order';
        if ($mode === 'receipt' && ! in_array($row->status, ['Ordered', 'Shipped', 'Received'], true)) {
            return redirect('/purchase/work/'.$token.'?tab=order')->with('error', 'Record an order before receiving it.');
        }
        $service = app(PurchaseFulfillment::class);
        $orderActivity = DB::table('purchase_request_activity')->where('purchase_request_id', $row->id)->whereIn('action', ['Marked Ordered', 'Saved ordered changes'])->orderByDesc('id')->first();
        $recordedOrder = json_decode($orderActivity->current ?? '{}', true);
        $ordered = array_map(fn ($line) => $line['quantity'], $recordedOrder['lines'] ?? []);
        $editable = $procurement && in_array($row->status, $mode === 'receipt' ? ['Ordered', 'Shipped'] : ['Draft', 'Request', 'Ordered', 'Shipped'], true);
        if (! isset($work['invoices'][$mode])) {
            $saved = $editable ? $service->progressDraft((int) $request->user()->id, $row->id, $mode) : null;
            $values = $saved['values'] ?? $service->data($row->id);
            if (! $saved && $row->status !== 'Received') {
                // An unconfirmed order's old placeholder is not an actual receipt date.
                $values['received_date'] = $mode === 'receipt' ? now(\App\Support\DisplayDates::effectiveZone())->toDateString() : '';
            }
            foreach ($mode === 'receipt' ? ['ordered_date', 'received_date'] : ['ordered_date'] as $date) $values[$date] = $values[$date] ?: now(\App\Support\DisplayDates::effectiveZone())->toDateString();
            if (! $saved && in_array($row->status, ['Draft', 'Request'], true)) {
                foreach ($values['lines'] as &$line) {
                    // A request is not a zero-dollar invoice: require explicit prices.
                    $line['unit'] = ''; $line['cost'] = ''; $line['fee'] = '0.00';
                }
                unset($line);
            }
            $work['invoices'][$mode] = ['values' => $values, 'revision' => $saved['revision'] ?? $row->revision, 'operation' => (string) Str::uuid()];
            $request->session()->put('purchase_workspace.'.$token, $work);
        }
        $invoice = $work['invoices'][$mode];
        $values = array_replace($invoice['values'], $request->old());
        if ($row->status === 'Received') {
            // Receipt posting replaces the live invoice rows; the recorded order is immutable activity.
            $values = $mode === 'order' ? $this->orderValues($recordedOrder) : $service->data($row->id);
        }
        $preview = null; $reviewErrors = [];
        if (in_array($tab, ['order-review', 'confirmation', 'charges'], true)) {
            try {
                $this->invoiceComplete($values, $tab !== 'charges', $mode);
                $preview = $editable && $tab !== 'charges' ? $service->work((int) $request->user()->id, $row->id, $invoice['revision'], $values, $mode, 'review') : app(PurchaseCosting::class)->calculate($values);
                if (! $editable && $row->status === 'Received' && $mode === 'receipt') $preview = RequestStock::header('purchase', $row->id)['final_breakdown'] ?? $preview;
            } catch (ValidationException $e) {
                $reviewErrors = $e->errors();
                try {
                    $this->invoiceComplete($values, false, $mode);
                    $preview = app(PurchaseCosting::class)->calculate($values);
                } catch (ValidationException) { /* Keep entered values visible while unfinished. */ }
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 409) throw $e;
                $reviewErrors = ['revision' => ['This purchase changed. Open it again from the index to load its latest values.']];
            }
        }
        $locationStocks = DB::table('inventory_balances')->where('storage_location_id', (int) ($values['receiving_location_id'] ?: 0))->pluck('quantity', 'item_id')->all();

        if ($row->status === 'Received' && $mode === 'order' && ! $recordedOrder) $reviewErrors = ['order' => ['The recorded order snapshot is unavailable. View Confirmation for the final receipt.']];
        try { $reviewDate = \App\Support\DisplayDates::businessDate($values[$mode === 'receipt' ? 'received_date' : 'ordered_date']); }
        catch (\Throwable) { $reviewDate = '—'; }

        return view('purchase.invoice', $common + compact('mode', 'editable', 'values', 'preview', 'reviewErrors', 'locationStocks', 'ordered', 'reviewDate') + [
            'revision' => $invoice['revision'], 'requested' => DB::table('purchase_request_lines')->where('purchase_request_id', $row->id)->pluck('quantity', 'item_id')->all(),
            'items' => app(RequestCatalog::class)->rows(array_map('intval', array_keys($values['lines'])), naturalNames: true),
            'suppliers' => app(CatalogRecords::class)->records('suppliers'),
            'locations' => app(CatalogRecords::class)->records('storage_locations'),
        ]);
    }

    private function requestErrors(array $work): array
    {
        $errors = [];
        try { RequestValues::text($work['title'], 'title', 255, true); }
        catch (ValidationException $e) { $errors += $e->errors(); }
        foreach ($work['lines'] as $id => $value) {
            try { RequestValues::quantity($value, 'lines.'.$id); }
            catch (ValidationException $e) { $errors += $e->errors(); }
        }

        return $errors;
    }

    public function save(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $work = $this->workspace($request, $token);
            $data = $request->validate([
                'action' => ['nullable', 'in:save,next,add,remove,submit'], 'tab' => ['required', 'in:basic,material,review'],
                'title' => ['nullable', 'string', 'max:255'], 'suggested_merchant' => ['nullable', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:5000'],
                'lines' => ['nullable', 'array', 'max:1000'], 'lines.*' => ['nullable', 'string', 'max:30'], 'item_id' => ['nullable', 'integer'], 'remove' => ['nullable', 'integer'],
            ]);
            $action = $data['action'] ?? (isset($data['remove']) ? 'remove' : 'save');
            $values = array_replace($work, array_intersect_key($data, array_flip(['title', 'suggested_merchant', 'details'])));
            $values['lines'] = $data['lines'] ?? [];
            if ($action === 'remove') unset($values['lines'][(int) ($data['remove'] ?? $data['item_id'] ?? 0)]);
            if ($action === 'add') {
                $id = RequestValues::id($data['item_id'] ?? null, 'item_id');
                if (! $id) throw ValidationException::withMessages(['item_id' => 'Choose an item to add.']);
                RequestValues::association('items', $id, null, 'item_id', true);
                $values['lines'][$id] ??= '';
            }
            if (count($values['lines']) > 1000) throw ValidationException::withMessages(['lines' => 'Use up to 1,000 catalog items.']);
            $row = $work['id'] ? DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail() : null;
            abort_unless(! $row || app(PurchaseRequests::class)->editable($request->user(), $row), 403);
            // Add/remove and step navigation retain unfinished values in this workspace.
            if (in_array($action, ['add', 'remove'], true) || ($action === 'next' && $data['tab'] !== 'basic')) {
                $request->session()->put('purchase_workspace.'.$token, $values);

                return redirect('/purchase/work/'.$token.'?tab='.($action === 'next' ? 'review' : 'material'));
            }
            $submit = $action === 'submit';
            $lines = [];
            foreach ($values['lines'] as $id => $value) {
                $item = RequestValues::id($id, 'lines');
                if (! $item) throw ValidationException::withMessages(['lines' => 'Choose catalog items.']);
                $quantity = RequestValues::quantity($value, 'lines.'.$item, ! $submit);
                if ($quantity !== null) $lines[$item] = ['quantity' => $quantity];
            }
            $values['id'] = DB::transaction(function () use ($request, $work, $values, $lines, $submit) {
                if (! $submit && $work['id']) {
                    // Retain procurement metadata on an existing line while its quantity is unfinished.
                    $retained = DB::table('purchase_request_lines')->where('purchase_request_id', $work['id'])->pluck('quantity', 'item_id');
                    foreach ($values['lines'] as $item => $quantity) if (($quantity === null || $quantity === '') && $retained->has($item)) $lines[$item] = ['quantity' => $retained[$item]];
                }
                $id = app(PurchaseRequests::class)->saveWork((int) $request->user()->id, $work['id'], $request->input('revision'), array_intersect_key($values, array_flip(['title', 'suggested_merchant', 'details'])) + ['lines' => $lines], $submit);
                $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
                $header = RequestStock::header('purchase', $id);
                if (! $submit) {
                    foreach (array_keys($values['lines']) as $item) RequestValues::association('items', (int) $item, array_key_exists($item, $work['lines']) ? (int) $item : null, 'lines.'.$item);
                    // Blank quantities are raw working data, never silently zero units.
                    if ((int) $row->revision === (int) $work['revision']) {
                        RequestStock::changed('purchase', $row, $request->user(), 'Saved request progress', []);
                        $row->revision++;
                    }
                    $header['singular_request_draft'] = ['revision' => (int) $row->revision, 'lines' => $values['lines']];
                } else unset($header['singular_request_draft']);
                RequestStock::saveHeader('purchase', $id, $header);

                return $id;
            }, 3);
            $values['revision'] = DB::table('purchase_requests')->where('id', $values['id'])->value('revision');
            $values['invoices'] = [];
            $request->session()->put('purchase_workspace.'.$token, $values);

            return redirect('/purchase/work/'.$token.'?tab='.($action === 'next' ? 'material' : $data['tab']))->with('success', $submit ? 'Request submitted. No stock or cost changed.' : 'Progress saved.');
        });
    }

    private function orderValues(array $snapshot): array
    {
        $values = array_intersect_key($snapshot, array_flip(['title', 'supplier_id', 'receiving_location_id', 'ordered_date', 'shipped_date', 'shipping_method']));
        foreach (['other_fees', 'discount', 'tax', 'shipping'] as $field) $values[$field] = PurchaseCosting::dollars((int) ($snapshot['charges'][$field] ?? 0));
        $values['lines'] = [];
        foreach ($snapshot['lines'] ?? [] as $id => $line) $values['lines'][$id] = [
            'quantity' => (string) $line['quantity'], 'unit' => $line['unit'],
            'cost' => PurchaseCosting::dollars((int) $line['cost']), 'fee' => PurchaseCosting::dollars((int) $line['fee']), 'basis' => 'unit',
        ];

        return $values + ['title' => '', 'supplier_id' => '', 'receiving_location_id' => '', 'ordered_date' => '', 'shipped_date' => '', 'received_date' => '', 'shipping_method' => 'line_total'];
    }

    /** Advance only this actor's drafts which were based on the unchanged recorded order. */
    private function advanceProgressRevision(array $header, int $actor, int $before, int $after): array
    {
        foreach ($header['purchase_progress'][$actor] ?? [] as $mode => $draft) {
            if ((int) $draft['revision'] === $before) $header['purchase_progress'][$actor][$mode]['revision'] = $after;
        }
        if (isset($header['singular_request_draft']) && (int) $header['singular_request_draft']['revision'] === $before) $header['singular_request_draft']['revision'] = $after;

        return $header;
    }

    private function invoiceComplete(array $values, bool $details = true, string $mode = 'order'): void
    {
        $errors = [];
        $required = ['other_fees' => 'Other fees', 'discount' => 'Discount', 'tax' => 'Tax', 'shipping' => 'Shipping'];
        if ($details) $required += ['title' => 'Title', 'supplier_id' => 'Supplier', 'receiving_location_id' => 'Receiving location', 'ordered_date' => 'Ordered date'];
        foreach ($required as $field => $label) {
            if (($values[$field] ?? '') === '') $errors[$field] = ['Complete '.$label.' before confirming.'];
        }
        $names = DB::table('items')->whereIn('id', array_keys($values['lines'] ?? []))->pluck('name', 'id');
        foreach ($values['lines'] ?? [] as $id => $line) foreach (['quantity', 'unit', 'cost', 'fee'] as $field) {
            $label = $field === 'quantity' ? ($mode === 'receipt' ? 'received quantity' : 'ordered quantity') : $field;
            if (($line[$field] ?? '') === '') $errors['lines.'.$id.'.'.$field] = [($names[$id] ?? 'Item #'.$id).': enter '.$label.' before confirming.'];
        }
        if ($errors) throw ValidationException::withMessages($errors);
    }

    public function invoice(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $work = $this->workspace($request, $token);
            abort_unless($work['id'], 404);
            $request->validate(['mode' => ['required', 'in:order,receipt'], 'action' => ['nullable', 'in:save,next,add,remove,confirm,ship,cancel_receipt,recalculate'], 'tab' => ['required', 'in:order,invoice,charges,order-review,receive,confirmation']]);
            $mode = $request->input('mode');
            abort_unless(isset($work['invoices'][$mode]), 404);
            $service = app(PurchaseFulfillment::class);
            $row = DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail();
            abort_unless(RequestValues::manages($request->user(), 'procurement'), 403);
            abort_unless(in_array($row->status, $mode === 'receipt' ? ['Ordered', 'Shipped'] : ['Draft', 'Request', 'Ordered', 'Shipped'], true), 409);
            RequestValues::revision($request->input('revision'), $row);
            $action = $request->input('action') ?: ($request->filled('remove') ? 'remove' : ($request->filled('recalculate') ? 'recalculate' : 'save'));
            if ($action === 'cancel_receipt') {
                DB::transaction(function () use ($request, $row) {
                    $actor = RequestValues::actor((int) $request->user()->id);
                    abort_unless(RequestValues::manages($actor, 'procurement'), 403);
                    $locked = DB::table('purchase_requests')->where('id', $row->id)->lockForUpdate()->firstOrFail();
                    RequestValues::revision($request->input('revision'), $locked);
                    $header = RequestStock::header('purchase', $row->id);
                    unset($header['purchase_progress'][$actor->id]['receipt']);
                    $header = $this->advanceProgressRevision($header, (int) $actor->id, (int) $locked->revision, (int) $locked->revision + 1);
                    RequestStock::saveHeader('purchase', $row->id, $header);
                    RequestStock::changed('purchase', $locked, $actor, 'Discarded receipt draft', []);
                }, 3);
                unset($work['invoices']['receipt']);
                $request->session()->put('purchase_workspace.'.$token, $work);

                return redirect('/purchase/'.$row->id.'?tab=order')->with('success', 'Receipt work discarded. The recorded order is unchanged.');
            }
            $values = PurchaseProgress::invoice($request->all());
            // Preserve logical invoice order for deterministic residual-cent allocation.
            $values['lines'] = array_replace(array_intersect_key($work['invoices'][$mode]['values']['lines'], $values['lines']), $values['lines']);
            if ($action === 'remove') unset($values['lines'][(int) $request->input('remove', $request->input('item_id'))]);
            if ($action === 'add') {
                $id = RequestValues::id($request->input('item_id'), 'item_id');
                if (! $id) throw ValidationException::withMessages(['item_id' => 'Choose an item.']);
                RequestValues::association('items', $id, null, 'item_id', true);
                $values['lines'][$id] ??= ['quantity' => '', 'unit' => '', 'cost' => '', 'fee' => '0.00', 'basis' => 'unit'];
            }
            if (count($values['lines']) > 1000) throw ValidationException::withMessages(['lines' => 'Use up to 1,000 catalog invoice lines.']);
            if ($action === 'recalculate') {
                $id = RequestValues::id($request->input('recalculate', $request->input('item_id')), 'item_id');
                abort_unless(isset($values['lines'][$id]), 422);
                $line = &$values['lines'][$id];
                $quantity = RequestValues::quantity($line['quantity'], 'lines.'.$id.'.quantity');
                if ($quantity < 1) throw ValidationException::withMessages(['lines.'.$id.'.quantity' => 'Enter positive units to recalculate.']);
                if ($line['basis'] === 'cost') $line['unit'] = (string) BigDecimal::of(PurchaseCosting::dollars(PurchaseCosting::money($line['cost'], 'cost')))->dividedBy($quantity, 12, RoundingMode::HalfUp);
                else $line['cost'] = (string) BigDecimal::of(\App\Support\StockNumbers::cost($line['unit']))->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
                unset($line);
            }
            $work['invoices'][$mode]['values'] = $values;
            $request->session()->put('purchase_workspace.'.$token, $work);
            if ($action === 'confirm') {
                $this->invoiceComplete($values, true, $mode);
                $service->work((int) $request->user()->id, $row->id, $request->input('revision'), $values, $mode, 'confirm', $work['invoices'][$mode]['operation']);

                return redirect('/purchase/'.$row->id.'?tab='.($mode === 'receipt' ? 'confirmation' : 'order-review'))->with('success', $mode === 'receipt' ? 'Receipt confirmed. Stock and catalog unit costs updated.' : 'Order recorded. Held stock and catalog unit costs unchanged.');
            }
            if ($action === 'ship') {
                $service->transition((int) $request->user()->id, $row->id, $request->input('revision'), 'shipped', $values['shipped_date']);

                return redirect('/purchase/'.$row->id.'?tab=order')->with('success', 'Purchase marked Shipped.');
            }
            if ($action === 'save') {
                $saved = DB::transaction(function () use ($service, $request, $row, $values, $mode) {
                    $saved = $service->saveProgress((int) $request->user()->id, $row->id, $request->input('revision'), $values, $mode);
                    $header = RequestStock::header('purchase', $row->id);
                    $advanced = $this->advanceProgressRevision($header, (int) $request->user()->id, (int) $row->revision, (int) $saved['revision']);
                    if ($advanced !== $header) RequestStock::saveHeader('purchase', $row->id, $advanced);

                    return $saved;
                }, 3);
                foreach ($work['invoices'] as $otherMode => $draft) {
                    if ((int) $draft['revision'] === (int) $row->revision) $work['invoices'][$otherMode]['revision'] = $saved['revision'];
                }
                if ((int) $work['revision'] === (int) $row->revision) $work['revision'] = $saved['revision'];
                $request->session()->put('purchase_workspace.'.$token, $work);
            }
            $tab = $request->input('tab');
            if ($action === 'next') $tab = $mode === 'receipt' ? 'confirmation' : match ($tab) { 'order' => 'invoice', 'invoice' => 'charges', default => 'order-review' };

            return redirect('/purchase/work/'.$token.'?'.http_build_query(['tab' => $tab, 'mode' => $mode]))->with('success', $action === 'save' ? 'Progress saved. Stock and cost unchanged.' : null);
        });
    }

    public function note(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $work = $this->workspace($request, $token);
            abort_unless($work['id'], 404);
            app(PurchaseRequests::class)->note((int) $request->user()->id, $work['id'], $request->input('note'));

            return back()->with('success', 'Permanent note added.');
        });
    }

    public function transition(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $work = $this->workspace($request, $token);
            abort_unless($work['id'], 404);
            $request->validate(['action' => ['required', 'in:cancel,backward']]);
            $row = DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail();
            if ($row->status === 'Draft' && $request->input('action') === 'cancel') app(PurchaseRequests::class)->transition((int) $request->user()->id, $row->id, $request->input('revision'), 'cancel');
            else app(PurchaseFulfillment::class)->transition((int) $request->user()->id, $row->id, $request->input('revision'), $request->input('action'));

            return redirect('/purchase/'.$row->id)->with('success', 'Purchase status updated.');
        });
    }
}
