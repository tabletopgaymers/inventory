<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockPosting
{
    public function post(int $actorId, int $itemId, string $operation, array $rows): int
    {
        abort_unless(Str::isUuid($operation), 422);

        return DB::transaction(function () use ($actorId, $itemId, $operation, $rows) {
            // Match access-management serialization so roles/disablement cannot race Save.
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actor = User::findOrFail($actorId);
            abort_unless($actor->enabled && ($actor->hasRole('admin') || $actor->hasRole('manager')), 403);
            $item = DB::table('items')->where('id', $itemId)->lockForUpdate()->firstOrFail();
            $previous = DB::table('inventory_adjustments')->where('operation_id', $operation)->first();
            if ($previous) {
                abort_unless((int) $previous->actor_id === $actorId && (int) $previous->item_id === $itemId, 403);

                return (int) $previous->id;
            }
            $locations = DB::table('storage_locations')->orderBy('id')->lockForUpdate()->get();
            $ids = array_map('strval', $locations->pluck('id')->all());
            $received = array_map('strval', array_keys($rows));
            sort($ids);
            sort($received);
            abort_unless($ids === $received, 422);
            $balances = DB::table('inventory_balances')->where('item_id', $itemId)->orderBy('storage_location_id')->lockForUpdate()->get()->keyBy('storage_location_id');
            $changes = [];
            foreach ($locations as $location) {
                $row = $rows[$location->id];
                $after = StockNumbers::finalQuantity($row['set'] ?? null, $row['adjust'] ?? null, 'rows.'.$location->id);
                $rationale = $row['rationale'] ?? null;
                if ($rationale !== null && (! is_string($rationale) || mb_strlen($rationale) > 1000)) {
                    throw ValidationException::withMessages(['rows' => 'Rationale must be text up to 1,000 characters.']);
                }
                $before = StockNumbers::quantity((int) ($balances->get($location->id)->quantity ?? 0), 'current_quantity');
                if ($before !== $after) {
                    $changes[] = [$location, $before, $after, $rationale];
                }
            }
            $cost = StockNumbers::cost((string) $item->unit_cost);
            $now = now('UTC')->startOfSecond();
            $group = DB::table('inventory_adjustments')->insertGetId([
                'operation_id' => $operation, 'actor_id' => $actorId, 'item_id' => $itemId,
                'actor_name' => $actor->displayName(), 'item_name' => $item->name, 'item_sku' => $item->sku, 'posted_at' => $now,
            ]);
            foreach ($changes as [$location, $before, $after, $rationale]) {
                DB::table('inventory_balances')->updateOrInsert(['item_id' => $itemId, 'storage_location_id' => $location->id], ['quantity' => $after, 'created_at' => $balances->get($location->id)->created_at ?? $now, 'updated_at' => $now]);
                DB::table('inventory_adjustment_entries')->insert([
                    'inventory_adjustment_id' => $group, 'storage_location_id' => $location->id,
                    'location_name' => $location->name, 'description' => 'Adjustment: '.$location->name,
                    'before_quantity' => $before, 'after_quantity' => $after, 'quantity_change' => $after - $before,
                    'unit_cost' => $cost, 'rationale' => $rationale === '' ? null : $rationale,
                ]);
            }

            return (int) $group;
        }, 3);
    }
}
