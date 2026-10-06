<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DailyInventoryPosting
{
    public static function count(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A(?:\d{1,10}|\d{1,3}(?:,\d{3})+)\z/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Enter a nonnegative whole count, with optional thousands commas. Blank skips the item.']);
        }
        $number = StockNumbers::quantity(str_replace(',', '', (string) $value), $field);
        if ($number < 0) {
            throw ValidationException::withMessages([$field => 'Count cannot be negative.']);
        }

        return $number;
    }

    public function counts(int $actor, int $location, string $operation, array $counts, array $rationales): int
    {
        abort_unless(Str::isUuid($operation), 422);

        return DB::transaction(function () use ($actor, $location, $operation, $counts, $rationales) {
            app(CatalogRecords::class)->authorize($actor);
            $previous = DB::table('inventory_count_operations')->where('operation_id', $operation)->first();
            if ($previous) {
                abort_unless((int) $previous->actor_id === $actor && (int) $previous->storage_location_id === $location, 403);

                return (int) $previous->id;
            }
            DB::table('storage_locations')->where('id', $location)->lockForUpdate()->firstOrFail();
            ksort($counts, SORT_NUMERIC);
            $now = now('UTC')->startOfSecond();
            $id = DB::table('inventory_count_operations')->insertGetId(['operation_id' => $operation, 'actor_id' => $actor, 'storage_location_id' => $location, 'posted_at' => $now]);
            foreach ($counts as $itemId => $value) {
                $after = self::count($value, 'counts.'.$itemId);
                if ($after === null) {
                    continue;
                }
                DB::table('items')->where('id', $itemId)->lockForUpdate()->firstOrFail();
                $balances = DB::table('inventory_balances')->where('item_id', $itemId)->lockForUpdate()->pluck('quantity', 'storage_location_id');
                if ((int) ($balances[$location] ?? 0) === $after) {
                    continue;
                }
                $rationale = $rationales[$itemId] ?? null;
                if ($rationale !== null && (! is_string($rationale) || mb_strlen($rationale) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $rationale))) {
                    throw ValidationException::withMessages(['rationales.'.$itemId => 'Use ordinary text up to 1,000 characters.']);
                }
                $rows = [];
                foreach (DB::table('storage_locations')->orderBy('id')->get() as $storage) {
                    $rows[$storage->id] = ['set' => (string) ($storage->id === $location ? $after : ($balances[$storage->id] ?? 0)), 'adjust' => '', 'rationale' => $storage->id === $location ? $rationale : null];
                }
                $adjustment = app(StockPosting::class)->post($actor, (int) $itemId, (string) Str::uuid(), $rows);
                DB::table('inventory_count_items')->insert(['inventory_count_operation_id' => $id, 'item_id' => $itemId, 'inventory_adjustment_id' => $adjustment]);
            }

            return $id;
        }, 3);
    }

    public function cost(int $actor, int $itemId, string $operation, string $value, ?string $rationale): ?int
    {
        abort_unless(Str::isUuid($operation), 422);
        if (! preg_match('/\A\d{1,12}(?:\.\d{1,12})?\z/D', $value)) {
            throw ValidationException::withMessages(['unit_cost' => 'Enter an exact nonnegative decimal; use explicit zero for unknown cost.']);
        }
        $after = StockNumbers::cost($value);
        if ($rationale !== null && (mb_strlen($rationale) > 1000 || preg_match('/[\x00-\x1F\x7F]/u', $rationale))) {
            throw ValidationException::withMessages(['rationale' => 'Use ordinary text up to 1,000 characters.']);
        }

        return DB::transaction(function () use ($actor, $itemId, $operation, $after, $rationale) {
            app(CatalogRecords::class)->authorize($actor);
            $user = User::findOrFail($actor);
            abort_unless($user->hasRole('admin'), 403);
            $item = DB::table('items')->where('id', $itemId)->lockForUpdate()->firstOrFail();
            $previous = DB::table('item_cost_entries')->where('operation_id', $operation)->first();
            if ($previous) {
                abort_unless((int) $previous->actor_id === $actor && (int) $previous->item_id === $itemId, 403);

                return (int) $previous->id;
            }
            $before = StockNumbers::cost($item->unit_cost);
            if ($before === $after) {
                return null;
            }
            DB::table('items')->where('id', $itemId)->update(['unit_cost' => $after, 'updated_at' => now('UTC')]);

            return DB::table('item_cost_entries')->insertGetId(['operation_id' => $operation, 'actor_id' => $actor, 'item_id' => $itemId, 'actor_name' => $user->displayName(), 'item_name' => $item->name, 'item_sku' => $item->sku, 'before_cost' => $before, 'after_cost' => $after, 'rationale' => $rationale === '' ? null : $rationale, 'posted_at' => now('UTC')->startOfSecond()]);
        }, 3);
    }
}
