<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shared item/balance serialization for request postings and current held stock. */
class RequestStock
{
    public function items(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $items = [];
        foreach ($ids as $id) {
            $items[$id] = DB::table('items')->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('inventory_balances')->where('item_id', $id)->orderBy('storage_location_id')->lockForUpdate()->get();
            DB::table('inventory_source_balances')->where('item_id', $id)->orderBy('source_id')->lockForUpdate()->get();
        }

        return $items;
    }

    public function storage(int $item, int $location, int $delta): void
    {
        $row = DB::table('inventory_balances')->where('item_id', $item)->where('storage_location_id', $location)->lockForUpdate()->first();
        $after = StockNumbers::quantity((int) ($row?->quantity ?? 0) + $delta, 'stock');
        DB::table('inventory_balances')->updateOrInsert(['item_id' => $item, 'storage_location_id' => $location], ['quantity' => $after, 'created_at' => $row?->created_at ?? now('UTC'), 'updated_at' => now('UTC')]);
    }

    public function source(int $item, string $kind, int $delta): void
    {
        abort_unless(in_array($kind, ['ordered', 'transit'], true), 500);
        $sources = DB::table('inventory_sources')->where('kind', $kind)->where('active', true)->orderBy('id')->lockForUpdate()->get();
        abort_unless($sources->count() === 1, 409, 'The inventory source needs inspection.');
        $source = (int) $sources->first()->id;
        $row = DB::table('inventory_source_balances')->where('item_id', $item)->where('source_id', $source)->lockForUpdate()->first();
        $after = StockNumbers::quantity((int) ($row?->quantity ?? 0) + $delta, 'stock');
        DB::table('inventory_source_balances')->updateOrInsert(['item_id' => $item, 'source_id' => $source], ['quantity' => $after, 'created_at' => $row?->created_at ?? now('UTC'), 'updated_at' => now('UTC')]);
    }

    public function held(int $item): int
    {
        // MariaDB repeatable-read aggregates can retain a snapshot established
        // before waiting for the actor/item locks. Locking reads are current reads.
        return (int) DB::table('inventory_balances')->where('item_id', $item)->orderBy('storage_location_id')->lockForUpdate()->get()->sum('quantity')
            + (int) DB::table('inventory_source_balances')->join('inventory_sources', 'inventory_sources.id', '=', 'inventory_source_balances.source_id')
                ->where('item_id', $item)->where('inventory_sources.active', true)->whereIn('kind', ['event', 'transit'])->orderBy('source_id')->lockForUpdate()->get(['inventory_source_balances.quantity'])->sum('quantity');
    }

    public function entry(User $actor, string $kind, int $request, object $item, string $operation, string $entryKind, string $description, int $quantity, string $cost, ?string $explanation = null): void
    {
        DB::table('request_stock_entries')->insert(['operation_id' => $operation, $kind.'_request_id' => $request, 'item_id' => $item->id, 'actor_id' => $actor->id,
            'actor_name' => $actor->displayName(), 'item_name' => $item->name, 'item_sku' => $item->sku, 'entry_kind' => $entryKind, 'description' => $description,
            'quantity_change' => $quantity, 'unit_cost' => StockNumbers::cost($cost), 'explanation' => $explanation, 'posted_at' => now('UTC')->startOfSecond()]);
    }

    public static function date(mixed $value, string $field, bool $optional = false): ?string
    {
        $date = RequestValues::text($value, $field, 255, ! $optional);
        if ($date !== null && (! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
            throw ValidationException::withMessages([$field => 'Enter a valid calendar date.']);
        }

        return $date;
    }

    public static function header(string $kind, int $id): array
    {
        $data = DB::table($kind.'_fulfillment')->where($kind.'_request_id', $id)->lockForUpdate()->value('data');

        return $data === null ? [] : json_decode($data, true, flags: JSON_THROW_ON_ERROR);
    }

    public static function saveHeader(string $kind, int $id, array $data): void
    {
        DB::table($kind.'_fulfillment')->updateOrInsert([$kind.'_request_id' => $id], ['data' => json_encode($data, JSON_THROW_ON_ERROR)]);
    }

    public static function changed(string $kind, object $row, User $actor, string $action, array $data, ?string $status = null): void
    {
        DB::table($kind.'_requests')->where('id', $row->id)->update(['status' => $status ?? $row->status, 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
        RequestValues::activity($kind, (int) $row->id, $actor, $action, ['status' => $row->status], $data + ['status' => $status ?? $row->status]);
    }
}
