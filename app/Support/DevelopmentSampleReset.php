<?php

namespace App\Support;

use App\Models\User;
use Database\Seeders\DevelopmentSampleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class DevelopmentSampleReset
{
    public const CONFIRMATION = 'development-business-data';

    // Explicit D-220 disposable-business-data allowlist, children before parents.
    // DELETE retains schema, foreign-key enforcement and monotonic record IDs.
    public const TABLES = [
        'purchase_request_lines', 'purchase_request_notes', 'purchase_request_activity',
        'relocation_request_lines', 'relocation_request_activity', 'purchase_requests', 'relocation_requests',
        'inventory_count_items', 'inventory_count_operations', 'item_cost_entries',
        'inventory_adjustment_entries', 'inventory_adjustments', 'inventory_balances',
        'inventory_source_balances', 'item_programs', 'item_metadata', 'inventory_preferences',
        'catalog_names', 'catalog_metadata', 'items', 'collections', 'categories',
        'catalog_references', 'inventory_sources', 'storage_locations',
    ];

    public function reset(?int $actorId, bool $hosted, string $confirmation): string
    {
        if ($confirmation !== self::CONFIRMATION || ! app()->environment(['testing', 'development'])) {
            throw new LogicException('Explicit reset is restricted to disposable testing or approved hosted development.');
        }
        $seeder = app(DevelopmentSampleSeeder::class);
        $seeder->assertContext($hosted);
        if (! app(BaselineProbe::class)->schemaState(DB::connection())['requestsReady']) {
            throw new LogicException('Reset requires the complete compatible transactional business schema.');
        }

        return DB::transaction(function () use ($actorId, $hosted, $seeder) {
            $bootstrap = DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actorId ??= (int) $bootstrap->user_id;
            app(CatalogRecords::class)->authorize($actorId);
            abort_unless(User::findOrFail($actorId)->hasRole('admin'), 403);
            foreach (self::TABLES as $table) {
                DB::table($table)->delete();
            }
            $seeder->run($actorId, hosted: $hosted);
            $installation = DB::table('inventory_adjustments')->where('operation_id', DevelopmentSampleSeeder::INSTALLATION)->firstOrFail();
            $rows = [];
            $balances = DB::table('inventory_balances')->where('item_id', $installation->item_id)->pluck('quantity', 'storage_location_id');
            foreach (DB::table('storage_locations')->get() as $location) {
                $rows[$location->id] = ['set' => (string) ($balances[$location->id] ?? 0)];
            }
            // Empty audited stock group identifies this explicitly approved reset.
            // Ordinary deployments never call reset; subsequent explicit requests
            // may replace this disposable business baseline again under D-221.
            $operation = (string) Str::uuid();
            app(StockPosting::class)->post($actorId, (int) $installation->item_id, $operation, $rows);

            return $operation;
        }, 3);
    }
}
