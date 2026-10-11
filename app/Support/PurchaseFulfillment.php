<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseFulfillment
{
    private function preparationLines(int $id): array
    {
        $lines = [];
        foreach (DB::table('purchase_request_lines')->where('purchase_request_id', $id)->orderBy('id')->get() as $line) {
            $lines[$line->item_id] = ['quantity' => (int) $line->quantity, 'estimate' => $line->estimate];
        }

        return $lines;
    }

    public function data(int $id): array
    {
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();
        $header = RequestStock::header('purchase', $id);
        $lines = [];
        foreach (DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->orderBy('id')->get() as $line) {
            $lines[$line->item_id] = ['quantity' => $line->quantity, 'unit' => $line->unit, 'cost' => PurchaseCosting::dollars((int) $line->cost), 'fee' => PurchaseCosting::dollars((int) $line->fee)];
        }
        if ($lines !== [] && in_array($row->status, ['Draft', 'Request'], true)) {
            // Only later preparation edits override retained actual invoice values.
            // Unchanged requested quantities must not replace distinct actual units.
            $current = $this->preparationLines($id);
            $baseline = $header['preparation_baseline'] ?? $current;
            foreach ($baseline as $item => $before) {
                if (! isset($current[$item])) {
                    unset($lines[$item]);
                }
            }
            foreach ($current as $item => $prepared) {
                $before = $baseline[$item] ?? null;
                if ($before === $prepared) {
                    continue;
                }
                $line = $lines[$item] ?? ['quantity' => $prepared['quantity'], 'unit' => '0', 'cost' => '0.00', 'fee' => '0.00'];
                if ($before === null || $before['quantity'] !== $prepared['quantity']) {
                    $line['quantity'] = $prepared['quantity'];
                }
                if ($before === null || $before['estimate'] !== $prepared['estimate']) {
                    $line['cost'] = (string) BigDecimal::of($prepared['estimate'] ?? 0)->toScale(2, RoundingMode::HalfUp);
                    $line['unit'] = $line['quantity'] > 0 ? (string) BigDecimal::of($line['cost'])->dividedBy($line['quantity'], 12, RoundingMode::HalfUp) : '0';
                } else {
                    $line['cost'] = (string) BigDecimal::of($line['unit'])->multipliedBy($line['quantity'])->toScale(2, RoundingMode::HalfUp);
                }
                $lines[$item] = $line;
            }
        }
        if ($lines === [] && ! isset($header['order_operation'])) {
            foreach (DB::table('purchase_request_lines')->where('purchase_request_id', $id)->orderBy('id')->get() as $line) {
                $cost = (string) BigDecimal::of($line->estimate ?? 0)->toScale(2, RoundingMode::HalfUp);
                $lines[$line->item_id] = ['quantity' => $line->quantity, 'unit' => $line->quantity > 0 ? (string) BigDecimal::of($cost)->dividedBy($line->quantity, 12, RoundingMode::HalfUp) : '0', 'cost' => $cost, 'fee' => '0.00'];
            }
        }

        unset($header['purchase_progress']);
        $header = array_replace($header, ['title' => $row->title ?? '', 'supplier_id' => $row->supplier_id ?? '', 'receiving_location_id' => $row->receiving_location_id ?? '']);

        return $header + ['ordered_date' => $row->planned_date ?? '',
            'shipped_date' => '', 'received_date' => '', 'shipping_method' => 'line_total', 'discount' => '0.00', 'tax' => '0.00', 'shipping' => '0.00', 'other_fees' => '0.00', 'lines' => $lines];
    }

    private function validate(object $row, array $data, bool $receipt): array
    {
        $fields = ['title' => RequestValues::text($data['title'] ?? null, 'title', 255, true),
            'supplier_id' => RequestValues::id($data['supplier_id'] ?? null, 'supplier_id'),
            'receiving_location_id' => RequestValues::id($data['receiving_location_id'] ?? null, 'receiving_location_id'),
            'ordered_date' => RequestStock::date($data['ordered_date'] ?? null, 'ordered_date'),
            'shipped_date' => RequestStock::date($data['shipped_date'] ?? null, 'shipped_date', true),
            'received_date' => RequestStock::date($data['received_date'] ?? null, 'received_date', ! $receipt)];
        if (! $fields['supplier_id'] || ! $fields['receiving_location_id']) {
            throw ValidationException::withMessages(['order' => 'Choose a supplier and receiving storage location.']);
        }
        RequestValues::association('suppliers', $fields['supplier_id'], $row->supplier_id, 'supplier_id');
        RequestValues::association('storage_locations', $fields['receiving_location_id'], $row->receiving_location_id, 'receiving_location_id');
        $costing = app(PurchaseCosting::class)->calculate($data);
        $existing = DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $row->id)->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $existing = array_unique(array_merge($existing, DB::table('purchase_request_lines')->where('purchase_request_id', $row->id)->pluck('item_id')->map(fn ($id) => (int) $id)->all()));
        foreach (array_keys($costing['lines']) as $item) {
            RequestValues::association('items', $item, in_array($item, $existing, true) ? $item : null, 'lines.'.$item);
        }

        return $fields + $costing;
    }

    public function progressDraft(int $actorId, int $id, string $mode): ?array
    {
        return RequestStock::header('purchase', $id)['purchase_progress'][$actorId][$mode] ?? null;
    }

    public function saveProgress(int $actorId, int $id, mixed $revision, array $data, string $mode): array
    {
        abort_unless(in_array($mode, ['order', 'receipt'], true), 422);

        return DB::transaction(function () use ($actorId, $id, $revision, $data, $mode) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            abort_unless(RequestValues::manages($actor, 'procurement'), 403);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($mode === 'receipt' ? in_array($row->status, ['Ordered', 'Shipped'], true) : in_array($row->status, ['Draft', 'Request', 'Ordered', 'Shipped'], true), 409);
            RequestValues::revision($revision, $row);
            $clean = PurchaseProgress::invoice($data);
            $header = RequestStock::header('purchase', $id);
            $before = $header['purchase_progress'][$actorId][$mode] ?? null;
            foreach (['supplier_id' => 'suppliers', 'receiving_location_id' => 'storage_locations'] as $field => $catalog) {
                $value = RequestValues::id($clean[$field], $field);
                $retainedValue = RequestValues::id($before['values'][$field] ?? null, $field);
                RequestValues::association($catalog, $value, $value === $retainedValue ? $retainedValue : $row->$field, $field);
            }
            foreach (['ordered_date', 'shipped_date', 'received_date'] as $field) {
                if ($clean[$field] !== '') {
                    RequestStock::date($clean[$field], $field);
                }
            }
            $retained = array_unique(array_merge(DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->pluck('item_id')->map(fn ($item) => (int) $item)->all(), DB::table('purchase_request_lines')->where('purchase_request_id', $id)->pluck('item_id')->map(fn ($item) => (int) $item)->all()));
            $retained = array_unique(array_merge($retained, array_map('intval', array_keys($before['values']['lines'] ?? []))));
            $completeCosts = $clean['lines'] !== [];
            foreach ($clean['lines'] as $item => $line) {
                RequestValues::association('items', $item, in_array($item, $retained, true) ? $item : null, 'lines.'.$item);
                foreach (['quantity', 'unit', 'cost', 'fee'] as $field) {
                    if ($line[$field] === '') {
                        $completeCosts = false;
                    } elseif ($field === 'quantity') {
                        if (RequestValues::quantity($line[$field], 'lines.'.$item.'.quantity') === 0) {
                            throw ValidationException::withMessages(['lines.'.$item.'.quantity' => 'Retained invoice lines need positive individual units. Remove absent lines.']);
                        }
                    } elseif ($field === 'unit') {
                        StockNumbers::cost($line[$field]);
                    } else {
                        PurchaseCosting::money($line[$field], 'lines.'.$item.'.'.$field);
                    }
                }
                if ($line['quantity'] !== '' && $line['unit'] !== '' && $line['cost'] !== '') {
                    $expected = BigDecimal::of($line['unit'])->multipliedBy(RequestValues::quantity($line['quantity'], 'lines.'.$item.'.quantity'))->toScale(2, RoundingMode::HalfUp);
                    if ((string) $expected !== PurchaseCosting::dollars(PurchaseCosting::money($line['cost'], 'lines.'.$item.'.cost'))) {
                        throw ValidationException::withMessages(['lines.'.$item.'.cost' => 'Cost does not equal quantity × the retained exact Unit, rounded to cents. Correct or explicitly recalculate this row.']);
                    }
                }
            }
            foreach (['other_fees', 'discount', 'tax', 'shipping'] as $field) {
                if ($clean[$field] === '') {
                    $completeCosts = false;
                } else {
                    PurchaseCosting::money($clean[$field], $field);
                }
            }
            if ($completeCosts) {
                app(PurchaseCosting::class)->calculate($clean);
            }
            if ($before && $before['values'] === $clean && (int) $before['revision'] === (int) $row->revision) {
                return $before;
            }
            $saved = ['revision' => (int) $row->revision + 1, 'values' => $clean];
            $header['purchase_progress'][$actorId][$mode] = $saved;
            if (strlen(json_encode($header['purchase_progress'], JSON_THROW_ON_ERROR)) > 5000000) {
                throw ValidationException::withMessages(['lines' => 'Saved invoice drafts exceed the available size for this purchase.']);
            }
            RequestStock::saveHeader('purchase', $id, $header);
            RequestStock::changed('purchase', $row, $actor, 'Saved invoice draft', ['progress_mode' => $mode]);

            return $saved;
        }, 3);
    }

    public function work(int $actorId, int $id, mixed $revision, array $data, string $mode, string $action, ?string $operation = null): array
    {
        abort_unless(in_array($mode, ['order', 'receipt'], true) && in_array($action, ['review', 'confirm'], true), 422);
        if ($action === 'confirm') {
            abort_unless(is_string($operation) && Str::isUuid($operation), 422);
        }

        return DB::transaction(function () use ($actorId, $id, $revision, $data, $mode, $action, $operation) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            abort_unless(RequestValues::manages($actor, 'procurement'), 403);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            $header = RequestStock::header('purchase', $id);
            if ($action === 'confirm' && ($header[$mode.'_operation'] ?? null) === $operation) {
                abort_unless(($header[$mode.'_actor'] ?? null) === $actorId, 403);

                return ['duplicate' => true];
            }
            abort_unless($mode === 'receipt' ? in_array($row->status, ['Ordered', 'Shipped'], true) : in_array($row->status, ['Draft', 'Request', 'Ordered', 'Shipped'], true), 409);
            RequestValues::revision($revision, $row);
            $clean = $this->validate($row, $data, $mode === 'receipt');
            $old = DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->orderBy('item_id')->get()->keyBy('item_id');
            $stock = app(RequestStock::class);
            $ids = array_unique(array_merge(array_keys($clean['lines']), $old->keys()->all()));
            $items = $stock->items($ids);
            $projection = [];
            foreach ($clean['lines'] as $itemId => $line) {
                $held = $stock->held($itemId);
                $projection[$itemId] = ['held' => $held, 'before_cost' => $items[$itemId]->unit_cost,
                    'after_held' => $held + $line['quantity'], 'after_cost' => PurchaseCosting::average($held, $items[$itemId]->unit_cost, $line['quantity'], $line['acquisition'])];
            }
            $clean['projection'] = $projection;
            if ($action === 'review') {
                return $clean;
            }
            $pending = in_array($row->status, ['Ordered', 'Shipped'], true);
            $supplier = DB::table('catalog_references')->where('id', $clean['supplier_id'])->value('name');
            $destination = DB::table('storage_locations')->where('id', $clean['receiving_location_id'])->value('name');
            foreach ($ids as $itemId) {
                $before = $pending ? (int) ($old->get($itemId)?->quantity ?? 0) : 0;
                $after = $mode === 'receipt' ? 0 : ($clean['lines'][$itemId]['quantity'] ?? 0);
                $delta = $after - $before;
                if ($delta !== 0) {
                    $stock->source($itemId, 'ordered', $delta);
                    $stock->entry($actor, 'purchase', $id, $items[$itemId], $operation, 'ordered', 'Purchase expectation: '.$supplier.' → '.$destination, $delta, $items[$itemId]->unit_cost);
                }
                if ($mode === 'receipt' && isset($clean['lines'][$itemId])) {
                    $line = $clean['lines'][$itemId];
                    $afterCost = $projection[$itemId]['after_cost'];
                    $stock->storage($itemId, $clean['receiving_location_id'], $line['quantity']);
                    DB::table('items')->where('id', $itemId)->update(['unit_cost' => $afterCost, 'updated_at' => now('UTC')]);
                    $stock->entry($actor, 'purchase', $id, $items[$itemId], $operation, 'purchase_receipt', 'Purchase: '.$supplier.' → '.$destination, $line['quantity'], $afterCost);
                }
            }
            // Keep explicit line order for deterministic cent tie-breaking on reload.
            DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->delete();
            foreach ($clean['lines'] as $line) {
                DB::table('purchase_fulfillment_lines')->insert(['purchase_request_id' => $id] + array_intersect_key($line, array_flip(['item_id', 'quantity', 'unit', 'cost', 'fee'])));
            }
            $saved = array_intersect_key($clean, array_flip(['title', 'supplier_id', 'receiving_location_id', 'ordered_date', 'shipped_date', 'received_date', 'shipping_method']));
            foreach ($clean['charges'] as $field => $cents) {
                $saved[$field] = PurchaseCosting::dollars($cents);
            }
            $saved[$mode.'_operation'] = $operation;
            $saved[$mode.'_actor'] = $actorId;
            $saved['preparation_baseline'] = $this->preparationLines($id);
            if ($mode === 'receipt') {
                $saved['final_breakdown'] = $clean;
            }
            // Clear only this actor's successfully confirmed mode, under the posting lock.
            unset($header['purchase_progress'][$actorId][$mode]);
            if (empty($header['purchase_progress'][$actorId])) {
                unset($header['purchase_progress'][$actorId]);
            }
            RequestStock::saveHeader('purchase', $id, array_replace($header, $saved));
            DB::table('purchase_requests')->where('id', $id)->update(['title' => $clean['title'], 'supplier_id' => $clean['supplier_id'], 'receiving_location_id' => $clean['receiving_location_id']]);
            RequestStock::changed('purchase', $row, $actor, $mode === 'receipt' ? 'Received purchase' : ($pending ? 'Saved ordered changes' : 'Marked Ordered'), $clean, $mode === 'receipt' ? 'Received' : ($row->status === 'Shipped' ? 'Shipped' : 'Ordered'));

            return $clean;
        }, 3);
    }

    public function transition(int $actorId, int $id, mixed $revision, string $action, ?string $date = null): void
    {
        abort_unless(in_array($action, ['shipped', 'backward', 'cancel'], true), 422);
        DB::transaction(function () use ($actorId, $id, $revision, $action, $date) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            abort_unless(RequestValues::manages($actor, 'procurement'), 403);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            RequestValues::revision($revision, $row);
            $next = match ($action) {
                'shipped' => $row->status === 'Ordered' ? 'Shipped' : null,
                'backward' => match ($row->status) {
                    'Request' => 'Draft', 'Ordered' => 'Request', 'Shipped' => 'Ordered', default => null
                },
                'cancel' => in_array($row->status, ['Draft', 'Request', 'Ordered', 'Shipped'], true) ? 'Cancelled' : null,
            };
            abort_unless($next !== null, 409, 'Received is final and Cancelled is terminal.');
            $header = RequestStock::header('purchase', $id);
            if ($action === 'shipped') {
                $header['shipped_date'] = RequestStock::date($date, 'shipped_date');
            }
            if (in_array($row->status, ['Ordered', 'Shipped'], true) && ! in_array($next, ['Ordered', 'Shipped'], true)) {
                $stock = app(RequestStock::class);
                $lines = DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->orderBy('item_id')->get()->keyBy('item_id');
                $items = $stock->items($lines->keys()->all());
                $operation = (string) Str::uuid();
                foreach ($lines as $itemId => $line) {
                    $stock->source($itemId, 'ordered', -(int) $line->quantity);
                    $stock->entry($actor, 'purchase', $id, $items[$itemId], $operation, 'ordered', $next === 'Cancelled' ? 'Purchase Cancelled' : 'Purchase returned to Request', -(int) $line->quantity, $items[$itemId]->unit_cost);
                }
            }
            RequestStock::saveHeader('purchase', $id, $header);
            RequestStock::changed('purchase', $row, $actor, 'Status changed', ['recorded_dates' => array_intersect_key($header, array_flip(['ordered_date', 'shipped_date', 'received_date']))], $next);
        }, 3);
    }
}
