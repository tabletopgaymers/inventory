<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\PurchaseCosting;
use App\Support\PurchaseFulfillment;
use App\Support\RelocationFulfillment;
use App\Support\RequestCatalog;
use App\Support\RequestValues;
use App\Support\StockNumbers;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FulfillmentController
{
    use RequestPageSupport;

    private function service(string $kind)
    {
        abort_unless(in_array($kind, ['purchase', 'relocation'], true), 404);

        return app($kind === 'purchase' ? PurchaseFulfillment::class : RelocationFulfillment::class);
    }

    private function base(string $kind, int $id): string
    {
        return '/'.($kind === 'purchase' ? 'purchases' : 'relocations').'/'.$id;
    }

    private function check(Request $request, string $kind, object $row, string $mode, bool $edit = true): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503, 'Fulfillment needs guarded local preparation.');
        abort_unless(in_array($mode, $kind === 'purchase' ? ['order', 'receipt'] : ['shipment', 'receipt'], true), 404);
        if (! $edit) {
            return;
        }
        $manager = RequestValues::manages($request->user(), $kind === 'purchase' ? 'procurement' : 'manager');
        abort_unless($manager || ($kind === 'relocation' && $mode === 'receipt' && (int) $row->owner_id === (int) $request->user()->id), 403);
        abort_unless($kind === 'purchase'
            ? ($mode === 'order' ? in_array($row->status, ['Draft', 'Request', 'Ordered', 'Shipped'], true) : in_array($row->status, ['Ordered', 'Shipped'], true))
            : ($mode === 'shipment' ? $row->status === 'Requested' : in_array($row->status, ['Shipped', 'Receiving'], true)), 409);
    }

    private function draft(Request $request, string $token, string $kind, int $id): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('fulfillment_work.'.$token);
        abort_unless(is_array($draft) && $draft['kind'] === $kind && $draft['id'] === $id && $draft['actor'] === (int) $request->user()->id, 404);

        return $draft;
    }

    public function edit(Request $request, int $id, string $mode, string $kind)
    {
        $this->ready();
        $service = $this->service($kind);
        $row = DB::table($kind.'_requests')->where('id', $id)->firstOrFail();
        $readOnly = $request->boolean('view');
        $this->check($request, $kind, $row, $mode, ! $readOnly);
        $token = $request->query('work');
        if (is_string($token)) {
            $draft = $this->draft($request, $token, $kind, $id);
            if (isset($draft['result'])) {
                return redirect($this->base($kind, $id));
            }
        } else {
            $token = (string) Str::uuid();
            $draft = ['kind' => $kind, 'id' => $id, 'actor' => (int) $request->user()->id, 'revision' => (int) $row->revision, 'mode' => $mode, 'values' => $service->data($id), 'review' => null];
            $request->session()->put('fulfillment_work.'.$token, $draft);
        }

        $choices = app(RequestCatalog::class)->choices();
        $relocationLines = collect($draft['values']['lines']);
        $requestedQuantities = [];
        if ($kind === 'relocation') {
            $requestedQuantities = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->pluck('quantity', 'item_id')->all();
            $relocationLines = $relocationLines->sortBy(function ($line, $itemId) use ($choices) {
                $item = $choices['items']->firstWhere('id', (int) $itemId);
                $collection = $choices['collections']->firstWhere('id', $item?->collection_id);

                return [$collection?->category_name, $collection?->name, $item?->name, $item?->sku];
            });
        }

        return view('requests.fulfillment-edit', $choices + ['kind' => $kind, 'mode' => $mode, 'row' => $row, 'token' => $token, 'draft' => $draft, 'readOnly' => $readOnly, 'base' => $this->base($kind, $id), 'relocationLines' => $relocationLines, 'requestedQuantities' => $requestedQuantities]);
    }

    public function update(Request $request, int $id, string $mode, string $kind)
    {
        return $this->write($request, function () use ($request, $kind, $id, $mode) {
            if (! $request->filled('action')) {
                $request->merge(['action' => $request->filled('recalculate_item') ? 'recalculate' : ($request->filled('remove_item') ? 'remove' : null)]);
            }
            $request->validate(['work' => ['required', 'uuid'], 'action' => ['required', 'in:save,review,add,remove,recalculate,refresh'],
                'lines' => ['nullable', 'array', 'max:1000'], 'lines.*' => ['array'],
                'lines.*.quantity' => ['nullable', 'string', 'max:255'], 'lines.*.unit' => ['nullable', 'string', 'max:255'],
                'lines.*.cost' => ['nullable', 'string', 'max:255'], 'lines.*.fee' => ['nullable', 'string', 'max:255'],
                'lines.*.sent' => ['nullable', 'string', 'max:255'], 'lines.*.received' => ['nullable', 'string', 'max:255'],
                'title' => ['nullable', 'string', 'max:255'], 'explanation' => ['nullable', 'string', 'max:5000'],
                'ordered_date' => ['nullable', 'string', 'max:255'], 'shipped_date' => ['nullable', 'string', 'max:255'], 'received_date' => ['nullable', 'string', 'max:255'],
                'supplier_id' => ['nullable', 'integer', 'min:1'], 'receiving_location_id' => ['nullable', 'integer', 'min:1'],
                'other_fees' => ['nullable', 'string', 'max:255'], 'discount' => ['nullable', 'string', 'max:255'], 'shipping' => ['nullable', 'string', 'max:255'], 'tax' => ['nullable', 'string', 'max:255'], 'shipping_method' => ['nullable', 'string', 'max:30']]);
            $token = $request->input('work');
            $draft = $this->draft($request, $token, $kind, $id);
            abort_if(isset($draft['result']), 409);
            abort_unless($draft['mode'] === $mode, 404);
            $row = DB::table($kind.'_requests')->where('id', $id)->firstOrFail();
            $this->check($request, $kind, $row, $mode);
            $values = $request->except(['_token', '_deliberate', 'work', 'action']);
            $values['lines'] = $request->input('lines', []);
            abort_unless(is_array($values['lines']) && count($values['lines']) <= 1000, 422);
            $draft['values'] = $values;
            $draft['review'] = null;
            // Persist entered values before validation; Back/retry retains the complete work.
            $request->session()->put('fulfillment_work.'.$token, $draft);
            $action = $request->input('action');
            if ($action === 'refresh') {
                return redirect($this->base($kind, $id).'/fulfillment/'.$mode.'?work='.$token);
            }
            if (in_array($action, ['add', 'remove', 'recalculate'], true)) {
                abort_unless($kind === 'purchase', 422);
                if ($action === 'add') {
                    $item = RequestValues::id($request->input('add_item'), 'add_item');
                    abort_unless($item !== null, 422);
                    DB::transaction(fn () => RequestValues::association('items', $item, null, 'add_item'));
                    $draft['values']['lines'][$item] ??= ['quantity' => '1', 'unit' => '0', 'cost' => '0.00', 'fee' => '0.00'];
                } elseif ($action === 'remove') {
                    unset($draft['values']['lines'][$request->input('remove_item')]);
                } else {
                    $item = RequestValues::id($request->input('recalculate_item'), 'recalculate_item');
                    abort_unless($item !== null && isset($draft['values']['lines'][$item]), 422);
                    $line = &$draft['values']['lines'][$item];
                    $quantity = RequestValues::quantity($line['quantity'] ?? '', 'quantity');
                    abort_unless($quantity > 0, 422);
                    if (($line['basis'] ?? 'unit') === 'cost') {
                        $line['unit'] = (string) BigDecimal::of(PurchaseCosting::dollars(PurchaseCosting::money($line['cost'] ?? '', 'cost')))->dividedBy($quantity, 12, RoundingMode::HalfUp);
                    } else {
                        $line['cost'] = (string) BigDecimal::of(StockNumbers::cost($line['unit'] ?? ''))->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
                    }
                    unset($line);
                }
                $request->session()->put('fulfillment_work.'.$token, $draft);

                return redirect($this->base($kind, $id).'/fulfillment/'.$mode.'?work='.$token);
            }
            if ($action === 'save') {
                abort_unless($kind === 'relocation', 422);
                $this->service($kind)->work((int) $request->user()->id, $id, $draft['revision'], $draft['values'], $mode, 'save');

                return redirect($this->base($kind, $id))->with('success', 'Counts saved. No stock was moved.');
            }
            $draft['review'] = $this->service($kind)->work((int) $request->user()->id, $id, $draft['revision'], $draft['values'], $mode, 'review');
            $request->session()->put('fulfillment_work.'.$token, $draft);

            return redirect($this->base($kind, $id).'/fulfillment/'.$mode.'/review/'.$token);
        });
    }

    public function review(Request $request, int $id, string $mode, string $token, string $kind)
    {
        $this->ready();
        $draft = $this->draft($request, $token, $kind, $id);
        abort_unless($draft['mode'] === $mode && is_array($draft['review']), 409);
        $row = DB::table($kind.'_requests')->where('id', $id)->firstOrFail();
        $this->check($request, $kind, $row, $mode);

        return view('requests.fulfillment-review', app(RequestCatalog::class)->choices() + ['kind' => $kind, 'mode' => $mode, 'row' => $row, 'draft' => $draft, 'review' => $draft['review'], 'token' => $token, 'base' => $this->base($kind, $id)]);
    }

    public function confirm(Request $request, int $id, string $mode, string $token, string $kind)
    {
        return $this->write($request, function () use ($request, $kind, $id, $mode, $token) {
            $draft = $this->draft($request, $token, $kind, $id);
            if (! isset($draft['result'])) {
                abort_unless($draft['mode'] === $mode && is_array($draft['review']), 409);
                $this->service($kind)->work((int) $request->user()->id, $id, $draft['revision'], $draft['values'], $mode, 'confirm', $token);
                $draft['result'] = $id;
                $request->session()->put('fulfillment_work.'.$token, $draft);
            }

            return redirect($this->base($kind, $id))->with('success', 'Confirmed once. Inventory and immutable history were saved together.');
        });
    }

    public function tracking(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            $data = $request->validate(['tracking' => ['nullable', 'array', 'max:50']]);
            app(RelocationFulfillment::class)->tracking((int) $request->user()->id, $id, $request->input('revision'), $data['tracking'] ?? []);

            return redirect('/relocations/'.$id)->with('success', 'Shipping information saved. Stock and sent quantities are unchanged.');
        });
    }

    public function purchaseTransition(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            $data = $request->validate(['action' => ['required', 'in:shipped,backward,cancel'], 'shipped_date' => ['nullable', 'string']]);
            app(PurchaseFulfillment::class)->transition((int) $request->user()->id, $id, $request->input('revision'), $data['action'], $data['shipped_date'] ?? null);

            return redirect('/purchases/'.$id)->with('success', 'Purchase status saved.');
        });
    }
}
