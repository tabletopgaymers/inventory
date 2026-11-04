<?php

namespace App\Http\Controllers;

use App\Support\CatalogRecords;
use App\Support\PurchaseCosting;
use App\Support\PurchaseFulfillment;
use App\Support\PurchaseProgress;
use App\Support\PurchaseRequests;
use App\Support\PurchaseRequestBasics;
use App\Support\PurchaseMaterial;
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
            ...PurchaseMaterial::load($row?->id, $lines),
            ...PurchaseRequestBasics::load($row),
        ]);
        if ($row && $row->status !== 'Draft' && ($snapshot = RequestStock::header('purchase', $row->id)['submitted_request'] ?? null)) {
            $request->session()->put('purchase_workspace.'.$token, array_replace($request->session()->get('purchase_workspace.'.$token), $snapshot['values']));
        }
        // The record URL opens a workspace through a second redirect.
        $request->session()->keep(['success', 'error']);

        return redirect('/purchase/work/'.$token.($request->has('tab') ? '?'.http_build_query($request->only('tab', 'mode')) : ''));
    }

    private function workspace(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $work = $request->session()->get('purchase_workspace.'.$token);
        abort_unless(is_array($work) && $work['actor'] === (int) $request->user()->id && $request->user()->enabled, 404);

        return PurchaseRequestBasics::restoreOtherVendor($work) + ['estimates' => [], 'material_names' => [], 'other_lines' => []];
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
            $old = $request->old();
            if (($old['purchase_form'] ?? null) === 'request') {
                // Omitted collections on a submitted standard form mean intentionally empty.
                $old += ['lines' => [], 'estimates' => [], 'other_lines' => []];
                $old['material_dirty'] = true;
            }
            $immutable = $row && $row->status !== 'Draft' ? (RequestStock::header('purchase', $row->id)['submitted_request']['values'] ?? null) : null;
            // Existing workspace URLs must display the current submission, never an older Draft replay.
            $values = array_replace(PurchaseRequestBasics::defaults(), $work, $immutable ?? $old);
            if ($immutable !== null) $values['material_dirty'] = false;
            $errors = $this->requestErrors($values);

            return view('purchase.work', array_replace($common, [
                'suppliers' => app(CatalogRecords::class)->records('suppliers'),
                'locations' => app(CatalogRecords::class)->records('storage_locations'),
                'deliveryUsers' => \App\Models\User::orderBy('first_name')->orderBy('last_name')->get(),
                'requesterId' => $row?->owner_id ?? $request->user()->id,
                'searchDefinition' => app(\App\Support\InventorySearchControls::class)->definition(['filters' => ['collections']]),
                'work' => $values, 'editable' => ! $row || ($row->status === 'Draft' && app(PurchaseRequests::class)->editable($request->user(), $row)),
                'submittedLabels' => $row && $row->status !== 'Draft' ? (RequestStock::header('purchase', $row->id)['submitted_request']['labels'] ?? []) : [],
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
            $values = $saved['values'] ?? ($mode==='receipt' && $recordedOrder ? $this->orderValues($recordedOrder) : $service->data($row->id));
            if ($mode==='receipt' && !$saved && $row->status !== 'Received') $values['source_order_operation']=RequestStock::header('purchase',$row->id)['order_operation'] ?? '';
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
            if ($mode === 'order' && !isset(RequestStock::header('purchase',$row->id)['order_operation'])) {
                // Only a genuinely new workspace without a saved invoice gets the request seed.
                if (!$saved) $values['lines']=\App\Support\PurchaseInvoiceLines::requested($row->id);
                $values['lines_initialized']=true;
            }
            $work['invoices'][$mode] = ['values' => $values, 'revision' => $saved['revision'] ?? $row->revision, 'operation' => (string) Str::uuid(), 'saved_values'=>PurchaseProgress::invoice($values), 'dirty'=>false];
            $request->session()->put('purchase_workspace.'.$token, $work);
        }
        $invoice = $work['invoices'][$mode];
        if (!isset($invoice['saved_values']) && $editable) {
            $invoice['saved_values']=PurchaseProgress::invoice(($service->progressDraft((int)$request->user()->id,$row->id,$mode)['values'] ?? $invoice['values']));
            $invoice['dirty']=true;
            $work['invoices'][$mode]=$invoice;
            $request->session()->put('purchase_workspace.'.$token,$work);
        }
        if ($mode === 'order' && !isset($invoice['values']['lines_initialized']) && !isset(RequestStock::header('purchase',$row->id)['order_operation'])) {
            // Existing workspace values may include unsaved Next/add/recalculate edits, including an intentional empty set.
            foreach ($invoice['values']['lines'] as $key=>&$line) $line = array_replace(\App\Support\PurchaseInvoiceLines::identity($key,$line),$line);
            unset($line);
            $invoice['values']['lines_initialized'] = true;
            $work['invoices'][$mode]=$invoice;
            $request->session()->put('purchase_workspace.'.$token,$work);
        }
        $old = $request->old();
        if (($old['purchase_form'] ?? null) === 'invoice') {
            // A full invoice with every row removed submits no lines controls.
            $old['lines'] ??= [];
        }
        $values = array_replace($invoice['values'], $old);
        if ($mode==='receipt') $values=$this->receiptFields($values,$invoice['values']);
        $receiptStale=$mode==='receipt' && $row->status!=='Received' && ((int)$invoice['revision']!==(int)$row->revision || ($values['source_order_operation'] ?? null)!==(RequestStock::header('purchase',$row->id)['order_operation'] ?? null));
        if ($row->status === 'Received') {
            // Receipt posting replaces the live invoice rows; the recorded order is immutable activity.
            $values = $mode === 'order' ? $this->orderValues($recordedOrder) : $service->data($row->id);
        }
        $invoiceDirty=$editable && (!empty($invoice['dirty']) || ($request->old('purchase_form')==='invoice'));
        $preview = null; $reviewErrors = [];
        if (in_array($tab, ['order-review', 'confirmation', 'charges'], true)) {
            try {
                $storedFinal=$row->status==='Received' && $mode==='receipt' ? (RequestStock::header('purchase',$row->id)['final_breakdown'] ?? null) : null;
                if ($storedFinal) $preview=$storedFinal;
                elseif ($mode==='receipt' && $tab==='confirmation') {
                    if ($editable) $preview=$service->receiptReview((int)$request->user()->id,$row->id,$invoice['revision'],$values);
                    else $reviewErrors=['receipt'=>['Procurement handles receiving confirmation.']];
                } elseif ($mode === 'order') {
                    if ($row->status !== 'Received') $this->invoiceComplete($values, $tab !== 'charges', $mode);
                    $preview=$tab !== 'charges' && $editable ? $service->orderReview((int)$request->user()->id,$row->id,$invoice['revision'],$values) : app(PurchaseCosting::class)->calculate($values);
                } else {
                    $charged=$values;
                    foreach ($charged['lines'] as $key=>$line) if (RequestValues::quantity($line['quantity'],'lines.'.$key.'.quantity')===0) unset($charged['lines'][$key]);
                    $preview=app(PurchaseCosting::class)->calculate($charged);
                }
            } catch (ValidationException $e) {
                $reviewErrors = $e->errors();
                try {
                    if ($mode==='order') { $this->invoiceComplete($values, false, $mode); $preview = app(PurchaseCosting::class)->calculate($values); }
                } catch (ValidationException) { /* Keep entered values visible while unfinished. */ }
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 409) throw $e;
                $reviewErrors = ['revision' => ['This purchase changed. Open it again from the index to load its latest values.']];
            }
        }
        if ($tab === 'order-review' && $row->status === 'Draft') $reviewErrors['status']=['Submit this request before recording an order.'];
        $locationStocks = DB::table('inventory_balances')->where('storage_location_id', (int) ($values['receiving_location_id'] ?: 0))->pluck('quantity', 'item_id')->all();

        if ($row->status === 'Received' && $mode === 'order' && ! $recordedOrder) $reviewErrors = ['order' => ['The recorded order snapshot is unavailable. View Confirmation for the final receipt.']];
        try { $reviewDate = \App\Support\DisplayDates::businessDate($values[$mode === 'receipt' ? 'received_date' : 'ordered_date']); }
        catch (\Throwable) { $reviewDate = '—'; }

        return view('purchase.invoice', $common + compact('mode', 'editable', 'values', 'preview', 'reviewErrors', 'locationStocks', 'ordered', 'reviewDate','receiptStale','recordedOrder','invoiceDirty') + [
            'revision' => $invoice['revision'], 'requested' => DB::table('purchase_request_lines')->where('purchase_request_id', $row->id)->pluck('quantity', 'item_id')->all(),
            'deliveryUsers' => $procurement ? \App\Models\User::where(function ($query) use ($values) {
                $query->where('enabled',true);
                if (str_starts_with($values['delivery_choice'] ?? '', 'user:')) $query->orWhere('id',(int)substr($values['delivery_choice'],5));
            })->orderBy('first_name')->get() : collect(),
            'items' => \App\Support\PurchaseInvoiceLines::rows($values['lines']),
            'requestMaterial' => \App\Support\PurchaseInvoiceLines::requested($row->id),
            'searchDefinition' => app(\App\Support\InventorySearchControls::class)->definition(['filters'=>['collections']]),
            'suppliers' => app(CatalogRecords::class)->records('suppliers'),
            'locations' => app(CatalogRecords::class)->records('storage_locations'),
        ]);
    }

    private function requestErrors(array $work): array
    {
        $errors = [];
        try { RequestValues::text($work['title'], 'title', 255, true); }
        catch (ValidationException $e) { $errors += $e->errors(); }
        $errors += validator($work, PurchaseRequestBasics::rules($work))->errors()->toArray();
        $errors += PurchaseMaterial::submissionErrors($work);

        return $errors;
    }

    public function search(Request $request, string $token)
    {
        $this->ready();
        $work = $this->workspace($request, $token);
        abort_unless($work['id'], 409, 'Save Basic info first.');
        $row = DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail();
        $stage=$request->query('stage','request');
        if ($stage === 'request') abort_unless($row->status === 'Draft' && app(PurchaseRequests::class)->editable($request->user(), $row), 403);
        else abort_unless(in_array($stage,['order','receipt'],true) && RequestValues::manages($request->user(),'procurement') && in_array($row->status,$stage==='receipt'?['Ordered','Shipped']:['Draft','Request','Ordered','Shipped'],true),403);
        $selection = $request->validate(['live_items' => ['nullable','boolean'], 'excluded' => ['nullable','array','max:1000'], 'excluded.*' => ['integer','min:1']]);
        $data = app(\App\Support\InventorySearchControls::class)->search($request, [
            'filters' => ['collections'], 'activeOnly' => true, 'excludeIds' => !empty($selection['live_items']) ? ($selection['excluded'] ?? []) : array_keys($work['lines']),
        ]);
        $costs = DB::table('items')->whereIn('id', array_column($data['rows'], 'id'))->pluck('unit_cost', 'id');
        $destination = $stage === 'request' && preg_match('/\Alocation:([1-9][0-9]*)\z/', $work['delivery_choice'] ?? '', $match)
            ? (int) $match[1] : null;
        $data['destination_choice'] = $stage === 'request' ? ($work['delivery_choice'] ?? '') : null;
        $data['destination_location'] = $destination ? DB::table('storage_locations')->where('id', $destination)->first(['id', 'name']) : null;
        $quantities = $destination ? DB::table('inventory_balances')->where('storage_location_id', $destination)
            ->whereIn('item_id', array_column($data['rows'], 'id'))->pluck('quantity', 'item_id') : [];
        foreach ($data['rows'] as &$item) {
            $cost = BigDecimal::of($costs[$item['id']] ?? 0);
            $item['unit_price'] = $cost->isPositive() ? (string) $cost->strippedOfTrailingZeros() : '';
            if ($destination) $item['destination_quantity'] = (int) ($quantities[$item['id']] ?? 0);
        }
        unset($item);

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $work = $this->workspace($request, $token);
            $data = $request->validate([
                'action' => ['nullable', 'in:save,next,add,add_selected,add_other,remove,remove_other,submit'], 'tab' => ['required', 'in:basic,material,review'],
                'title' => ['required', 'string', 'max:255'],
                'lines' => ['nullable', 'array', 'max:1000'], 'lines.*' => ['nullable', 'string', 'max:30'], 'item_id' => ['nullable', 'integer'], 'remove' => ['nullable', 'integer'],
                'estimates' => ['nullable','array','max:1000'], 'estimates.*' => ['nullable','string','max:30'],
                'other_lines' => ['nullable','array','max:1000'], 'other_lines.*' => ['array:description,quantity,unit'],
                'other_lines.*.description' => ['nullable','string','max:500'], 'other_lines.*.quantity' => ['nullable','string','max:30'],
                'other_lines.*.unit' => ['nullable','string','max:30'], 'remove_other' => ['nullable','uuid'],
                'selected' => ['nullable','array','max:1000'], 'selected.*' => ['integer','distinct','min:1'],
                'add_quantities' => ['nullable','array','max:1000'], 'add_quantities.*' => ['nullable','string','max:30'],
            ] + PurchaseRequestBasics::rules(array_replace($request->all(), ['vendor_other_id' => $work['vendor_other_id'] ?? ''])), [], [
                'vendor_choice' => 'supplier (existing or suggested)', 'delivery_choice' => 'delivery destination',
                'vendor_name' => 'supplier name', 'delivery_name' => 'delivery name', 'delivery_address' => 'mailing address',
            ]);
            $action = $data['action'] ?? (isset($data['remove_other']) ? 'remove_other' : (isset($data['remove']) ? 'remove' : 'save'));
            $values = array_replace(PurchaseRequestBasics::defaults(), $work, array_intersect_key($data, array_flip(['title', ...PurchaseRequestBasics::FIELDS])));
            foreach (PurchaseRequestBasics::FIELDS as $field) $values[$field] = $values[$field] ?? '';
            PurchaseRequestBasics::validateChoices($values, $work);
            $values['suggested_merchant'] = PurchaseRequestBasics::vendorName($values);
            $values['lines'] = $data['lines'] ?? [];
            $values['estimates'] = $data['estimates'] ?? [];
            $values['other_lines'] = $data['other_lines'] ?? [];
            if ($action === 'remove') {
                $removed = (int) ($data['remove'] ?? $data['item_id'] ?? 0);
                unset($values['lines'][$removed], $values['estimates'][$removed], $values['material_names'][$removed]);
            }
            if ($action === 'remove_other') unset($values['other_lines'][$data['remove_other']]);
            if ($action === 'add_other') $values['other_lines'][(string) Str::uuid()] = ['description' => '', 'quantity' => '', 'unit' => ''];
            $adding = [];
            if ($action === 'add') $adding[RequestValues::id($data['item_id'] ?? null, 'item_id') ?? 0] = '';
            if ($action === 'add_selected') {
                $selected = array_map('intval', $data['selected'] ?? []);
                foreach (array_unique([...$selected, ...array_keys($data['add_quantities'] ?? [])]) as $key) {
                    $value = trim((string) ($data['add_quantities'][$key] ?? ''));
                    if ($value === '' && !in_array((int) $key, $selected, true)) continue;
                    $id = RequestValues::id($key, 'selected');
                    if (!$id) throw ValidationException::withMessages(['selected' => 'Choose catalog items.']);
                    try { $value = (string) (RequestValues::quantity($value, 'selected', true) ?? ''); }
                    catch (ValidationException) { $value = ''; }
                    $adding[$id] = $value;
                }
                if (!$adding) throw ValidationException::withMessages(['selected' => 'Check an item or enter a quantity to add.']);
            }
            foreach ($adding as $id => $quantity) {
                if (!$id) throw ValidationException::withMessages(['item_id' => 'Choose an item to add.']);
                RequestValues::association('items', $id, null, 'item_id', true);
                $values['lines'][$id] ??= $quantity;
                $catalogItem = DB::table('items')->where('id', $id)->firstOrFail();
                $values['material_names'][$id] ??= $catalogItem->name;
                $values['estimates'][$id] ??= BigDecimal::of($catalogItem->unit_cost)->isPositive() ? (string) BigDecimal::of($catalogItem->unit_cost)->strippedOfTrailingZeros() : '';
            }
            PurchaseMaterial::validate($values);
            if (count($values['lines']) > 1000) throw ValidationException::withMessages(['lines' => 'Use up to 1,000 catalog items.']);
            $row = $work['id'] ? DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail() : null;
            abort_unless(! $row || ($row->status === 'Draft' && app(PurchaseRequests::class)->editable($request->user(), $row)), 403);
            // Add/remove and step navigation retain unfinished values in this workspace.
            if (in_array($action, ['add', 'add_selected', 'remove', 'add_other', 'remove_other'], true) || ($action === 'next' && $data['tab'] !== 'basic')) {
                $values['material_dirty'] = true;
                $request->session()->put('purchase_workspace.'.$token, $values);

                return redirect('/purchase/work/'.$token.'?tab='.($action === 'next' ? 'review' : 'material'));
            }
            $submit = $action === 'submit';
            if ($submit && ($errors = $this->requestErrors($values))) throw ValidationException::withMessages($errors);
            $lines = [];
            foreach ($values['lines'] as $id => $value) {
                $item = RequestValues::id($id, 'lines');
                if (! $item) throw ValidationException::withMessages(['lines' => 'Choose catalog items.']);
                $quantity = RequestValues::quantity($value, 'lines.'.$item, ! $submit);
                if ($quantity !== null) $lines[$item] = ['quantity' => $quantity];
            }
            $saved = DB::transaction(function () use ($request, $work, $values, $lines, $submit) {
                RequestValues::actor((int) $request->user()->id);
                if ($work['id']) {
                    $locked = DB::table('purchase_requests')->where('id', $work['id'])->lockForUpdate()->firstOrFail();
                    abort_unless($locked->status === 'Draft', 403, 'Submitted request material is read-only. Return it to Draft for corrections.');
                }
                if (! $submit && $work['id']) {
                    // Retain procurement metadata on an existing line while its quantity is unfinished.
                    $retained = DB::table('purchase_request_lines')->where('purchase_request_id', $work['id'])->pluck('quantity', 'item_id');
                    foreach ($values['lines'] as $item => $quantity) if (($quantity === null || $quantity === '') && $retained->has($item)) $lines[$item] = ['quantity' => $retained[$item]];
                }
                $id = app(PurchaseRequests::class)->saveWork((int) $request->user()->id, $work['id'], $request->input('revision'), array_intersect_key($values, array_flip(['title', 'suggested_merchant', 'details'])) + ['lines' => $lines], $submit);
                $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
                $values = PurchaseRequestBasics::saveVendor($values);
                $header = RequestStock::header('purchase', $id);
                $basics = array_intersect_key($values, PurchaseRequestBasics::defaults());
                if (($header['request_basics'] ?? []) !== $basics) {
                    RequestValues::activity('purchase', $id, $request->user(), 'Saved request preferences', $header['request_basics'] ?? null, $basics);
                }
                $header['request_basics'] = $basics;
                PurchaseMaterial::save($id, $values);
                if (! $submit) {
                    foreach (array_keys($values['lines']) as $item) RequestValues::association('items', (int) $item, array_key_exists($item, $work['lines']) ? (int) $item : null, 'lines.'.$item);
                    // Blank quantities are raw working data, never silently zero units.
                    if ((int) $row->revision === (int) $work['revision']) {
                        RequestStock::changed('purchase', $row, $request->user(), 'Saved request progress', []);
                        $row->revision++;
                    }
                    $header['singular_request_draft'] = ['revision' => (int) $row->revision, 'lines' => $values['lines']];
                } else {
                    unset($header['singular_request_draft']);
                    $snapshotValues = array_intersect_key($values, array_flip(['title', ...PurchaseRequestBasics::FIELDS, 'vendor_other_id', 'vendor_other_baseline', 'lines', 'estimates', 'material_names', 'other_lines']));
                    $snapshotValues = array_replace($snapshotValues, PurchaseMaterial::load($id, []));
                    $header['submitted_request'] = ['values' => $snapshotValues, 'labels' => PurchaseRequestBasics::labels($values), 'submitted_at' => now('UTC')->toIso8601String(), 'actor' => $request->user()->displayName()];
                    $vendorId = str_starts_with($values['vendor_choice'],'supplier:') ? (int)substr($values['vendor_choice'],9) : null;
                    $header['submitted_request']['vendor_snapshot'] = \App\Support\PurchaseProcurement::vendorSnapshot($vendorId);
                    $header['submitted_request']['delivery_snapshot'] = \App\Support\PurchaseProcurement::delivery($values);
                    RequestValues::activity('purchase', $id, $request->user(), 'Submitted request snapshot', null, $header['submitted_request']);
                }
                RequestStock::saveHeader('purchase', $id, $header);

                return ['id' => $id, 'values' => $values];
            }, 3);
            $values = $saved['values'];
            $values['id'] = $saved['id'];
            $values['revision'] = DB::table('purchase_requests')->where('id', $values['id'])->value('revision');
            $values['invoices'] = [];
            $values['material_dirty'] = false;
            $request->session()->put('purchase_workspace.'.$token, $values);

            if ($request->expectsJson()) return response()->json(['saved' => true, 'id' => $values['id'], 'revision' => $values['revision'],
                'status' => DB::table('purchase_requests')->where('id', $values['id'])->value('status'), 'values' => array_intersect_key($values, array_flip(['title', ...PurchaseRequestBasics::FIELDS])),
                'other_vendor_id' => $values['vendor_other_id'] ?? '', 'message' => 'Progress saved.']);
            return redirect('/purchase/work/'.$token.'?tab='.($action === 'next' ? 'material' : $data['tab']))->with('success', $submit ? 'Request submitted. No stock or cost changed.' : 'Progress saved.');
        });
    }

    private function orderValues(array $snapshot): array
    {
        $values = array_intersect_key($snapshot, array_flip(['title', 'supplier_id', 'receiving_location_id', 'ordered_date', 'shipped_date', 'shipping_method','procurer_id','procurer_name', ...\App\Support\PurchaseProcurement::FIELDS]));
        if (!isset($values['delivery_choice']) && !empty($values['receiving_location_id'])) {
            $values['delivery_choice']='location:'.$values['receiving_location_id'];
            $values['delivery_name']='Storage location #'.$values['receiving_location_id'];
            $values['delivery_address']='';
            $values['legacy_delivery']=true;
        }
        foreach (['other_fees', 'discount', 'tax', 'shipping'] as $field) $values[$field] = PurchaseCosting::dollars((int) ($snapshot['charges'][$field] ?? 0));
        $values['lines'] = [];
        foreach ($snapshot['lines'] ?? [] as $id => $line) $values['lines'][$id] = [
            'quantity' => (string) $line['quantity'], 'unit' => $line['unit'],
            'cost' => PurchaseCosting::dollars((int) $line['cost']), 'fee' => PurchaseCosting::dollars((int) $line['fee']), 'basis' => 'unit',
        ] + array_intersect_key($line,array_flip(['item_id','description','group_label','source_request_key']));

        return $values + ['title' => '', 'supplier_id' => '', 'receiving_location_id' => '', 'ordered_date' => '', 'shipped_date' => '', 'received_date' => '', 'shipping_method' => 'line_total'];
    }

    private function receiptFields(array $input, array $trusted): array
    {
        $protected=array_fill_keys(['title','supplier_id','receiving_location_id','ordered_date','shipped_date','source_order_operation',...\App\Support\PurchaseProcurement::FIELDS],'');
        return array_replace(array_diff_key($input,$protected),array_replace($protected,array_intersect_key($trusted,$protected)));
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
        if ($details) $required += ['title' => 'Title', 'supplier_id' => 'Supplier', 'delivery_choice'=>'Delivery destination','delivery_name'=>'Delivery name','delivery_address'=>'Mailing address', 'ordered_date' => 'Ordered date'];
        foreach ($required as $field => $label) {
            if (($values[$field] ?? '') === '') $errors[$field] = ['Complete '.$label.' before confirming.'];
        }
        $names = array_map(fn($line)=>$line['description'] ?? 'Item',$values['lines'] ?? []);
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
            $request->validate(['mode' => ['required', 'in:order,receipt'], 'action' => ['nullable', 'in:save,next,add,remove,confirm,ship,cancel_receipt,recalculate,replace_vendor,reconcile_receipt,discard_order'], 'tab' => ['required', 'in:order,invoice,charges,order-review,receive,confirmation']]);
            $mode = $request->input('mode');
            abort_unless(isset($work['invoices'][$mode]), 404);
            $service = app(PurchaseFulfillment::class);
            $row = DB::table('purchase_requests')->where('id', $work['id'])->firstOrFail();
            abort_unless(RequestValues::manages($request->user(), 'procurement'), 403);
            abort_unless(in_array($row->status, $mode === 'receipt' ? ['Ordered', 'Shipped'] : ['Draft', 'Request', 'Ordered', 'Shipped'], true), 409);
            $action = $request->input('action') ?: ($request->filled('remove') ? 'remove' : ($request->filled('recalculate') ? 'recalculate' : 'save'));
            if (!in_array($action,['reconcile_receipt','cancel_receipt','discard_order'],true)) RequestValues::revision($request->input('revision'), $row);
            if ($action==='discard_order') {
                abort_unless($mode==='order',422);
                DB::transaction(function () use($request,$row) {
                    $actor=RequestValues::actor((int)$request->user()->id);
                    abort_unless(RequestValues::manages($actor,'procurement'),403);
                    $locked=DB::table('purchase_requests')->where('id',$row->id)->lockForUpdate()->firstOrFail();
                    RequestValues::revision($row->revision,$locked);
                    abort_unless(in_array($locked->status,['Draft','Request','Ordered','Shipped'],true),409);
                    $header=RequestStock::header('purchase',$row->id);unset($header['purchase_progress'][$actor->id]['order']);
                    $header=$this->advanceProgressRevision($header,(int)$actor->id,(int)$locked->revision,(int)$locked->revision+1);
                    RequestStock::saveHeader('purchase',$row->id,$header);
                    RequestStock::changed('purchase',$locked,$actor,'Discarded order draft',[]);
                },3);
                return redirect('/purchase/'.$row->id.'?tab=order')->with('success','Your order draft was discarded. The request and recorded order are unchanged.');
            }
            if ($action === 'replace_vendor') {
                abort_unless($mode==='order',422);
                $replacement = RequestValues::id($request->input('supplier_id'),'supplier_id');
                if (!$replacement) throw ValidationException::withMessages(['supplier_id'=>'Choose a replacement supplier.']);
                $outcome = DB::transaction(function () use ($request, $row, $replacement, $service) {
                    $outcome = app(\App\Support\PurchaseProcurement::class)->replace((int)$request->user()->id,$row->id,$request->input('revision'),$replacement,$request->boolean('delete_unused_vendor'));
                    $revision = DB::table('purchase_requests')->where('id',$row->id)->value('revision');
                    $service->saveProgress((int)$request->user()->id,$row->id,$revision,$request->all(),'order');
                    return $outcome;
                }, 3);
                return redirect('/purchase/'.$row->id.'?tab=order')->with('success',$outcome['removed'] ? 'Supplier replaced; unused supplier removed and its details preserved in a permanent note.' : 'Supplier replaced; original record retained'.($outcome['reason'] ? ': '.$outcome['reason'] : '').'.');
            }
            if ($action === 'cancel_receipt') {
                abort_unless($mode==='receipt',422);
                DB::transaction(function () use ($request, $row) {
                    $actor = RequestValues::actor((int) $request->user()->id);
                    abort_unless(RequestValues::manages($actor, 'procurement'), 403);
                    $locked = DB::table('purchase_requests')->where('id', $row->id)->lockForUpdate()->firstOrFail();
                    RequestValues::revision($row->revision, $locked);
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
            $payload=$request->all();
            if ($mode==='receipt') {
                $payload=$this->receiptFields($payload,$work['invoices'][$mode]['values']);
                if ($action==='reconcile_receipt') $payload['source_order_operation']=RequestStock::header('purchase',$row->id)['order_operation'] ?? '';
            }
            $values = PurchaseProgress::invoice($payload);
            if ($action==='reconcile_receipt') {
                abort_unless($mode==='receipt',422);
                $header=RequestStock::header('purchase',$row->id);
                $entry=DB::table('purchase_request_activity')->where('purchase_request_id',$row->id)->whereIn('action',['Marked Ordered','Saved ordered changes'])->orderByDesc('id')->first();
                $fresh=$this->orderValues(json_decode($entry->current ?? '{}',true));
                foreach ($fresh['lines'] as $key=>$line) if (!isset($values['lines'][$key])) $values['lines'][$key]=array_replace($line,['quantity'=>'','cost'=>'']);
                foreach (['title','supplier_id','receiving_location_id','ordered_date','shipped_date',...\App\Support\PurchaseProcurement::FIELDS] as $field) $values[$field]=$fresh[$field] ?? '';
                $values['source_order_operation']=$header['order_operation'];
                $saved=DB::transaction(function () use($service,$request,$row,$values) {
                    $saved=$service->saveProgress((int)$request->user()->id,$row->id,$row->revision,$values,'receipt');
                    $header=RequestStock::header('purchase',$row->id);
                    $advanced=$this->advanceProgressRevision($header,(int)$request->user()->id,(int)$row->revision,(int)$saved['revision']);
                    if ($advanced!==$header) RequestStock::saveHeader('purchase',$row->id,$advanced);
                    return $saved;
                },3);
                foreach ($work['invoices'] as $otherMode=>$draft) if ((int)$draft['revision']===(int)$row->revision) $work['invoices'][$otherMode]['revision']=$saved['revision'];
                if ((int)$work['revision']===(int)$row->revision) $work['revision']=$saved['revision'];
                $work['invoices']['receipt']=['values'=>$saved['values'],'revision'=>$saved['revision'],'operation'=>(string)Str::uuid(),'saved_values'=>PurchaseProgress::invoice($saved['values']),'dirty'=>false];
                $request->session()->put('purchase_workspace.'.$token,$work);
                return redirect('/purchase/work/'.$token.'?tab=receive')->with('success','Receipt values retained and linked to the updated order. Check new and removed items before confirmation.');
            }
            if ($mode==='receipt') $service->assertReceiptSource(RequestStock::header('purchase',$row->id),$values);
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
            $work['invoices'][$mode]['dirty'] = $values !== ($work['invoices'][$mode]['saved_values'] ?? []);
            $request->session()->put('purchase_workspace.'.$token, $work);
            if ($action === 'confirm') {
                if ($mode==='order') $this->invoiceComplete($values, true, $mode);
                $service->work((int) $request->user()->id, $row->id, $request->input('revision'), $values, $mode, 'confirm', $work['invoices'][$mode]['operation']);

                return redirect('/purchase/'.$row->id.'?tab='.($mode === 'receipt' ? 'confirmation' : 'order-review'))->with('success', $mode === 'receipt' ? 'Receipt confirmed. Purchase finalized.' : 'Order recorded. Held stock and catalog unit costs unchanged.');
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
                $work['invoices'][$mode]['saved_values']=PurchaseProgress::invoice($saved['values']);
                $work['invoices'][$mode]['dirty']=false;
                $request->session()->put('purchase_workspace.'.$token, $work);
                if ($request->expectsJson()) return response()->json(['saved' => true, 'id' => $row->id, 'revision' => $saved['revision'],
                    'status' => $row->status, 'values' => $saved['values'], 'message' => 'Progress saved. Stock and cost unchanged.']);
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
