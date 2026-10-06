<?php

namespace Database\Seeders;

use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\StockPosting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class DevelopmentSampleSeeder extends Seeder
{
    // An immutable stock operation is also the installation receipt. It commits
    // with the entire fixture, including zero-stock items; no new schema needed.
    public const INSTALLATION = 'dbb53b93-cf7b-4859-a1f3-f11b9048ba01';

    public function run(?int $actorId = null, bool $refresh = false, bool $hosted = false): void
    {
        $this->assertContext($hosted, $refresh);
        $fixture = $this->fixture();
        DB::transaction(function () use ($actorId, $refresh, $fixture) {
            $bootstrap = DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actorId ??= (int) $bootstrap->user_id;
            app(CatalogRecords::class)->authorize($actorId);
            if (! $refresh && DB::table('inventory_adjustments')->where('operation_id', self::INSTALLATION)->exists()) {
                return;
            }
            $locations = [];
            foreach ($fixture['locations'] as $name) {
                $locations[] = $this->record('storage_locations', ['name' => $name], ['is_central' => $name === 'Central']);
            }
            $allLocations = DB::table('storage_locations')->orderBy('id')->get();
            $first = true;
            foreach ($fixture['categories'] as $category) {
                $categoryId = $this->record('categories', ['name' => $category['name']]);
                foreach ($category['collections'] as $collection) {
                    $collectionId = $this->record('collections', ['category_id' => $categoryId, 'name' => $collection['name']], ['sku_prefix' => $collection['prefix']]);
                    if (DB::table('collections')->where('id', $collectionId)->value('sku_prefix') !== $collection['prefix']) {
                        throw new LogicException('Sample collection prefix conflicts; inspect before retrying.');
                    }
                    foreach ($collection['items'] as $item) {
                        $sku = $collection['prefix'].$item['suffix'];
                        $existing = DB::table('items')->where('sku', $sku)->first();
                        if ($existing && ((int) $existing->collection_id !== $collectionId || $existing->name !== $item['name'])) {
                            throw new LogicException('Sample SKU conflicts with another identity; inspect before retrying.');
                        }
                        $itemId = $this->record('items', ['sku' => $sku], ['collection_id' => $collectionId, 'name' => $item['name'], 'sku_suffix' => $item['suffix'], 'unit_cost' => '0']);
                        $current = DB::table('inventory_balances')->where('item_id', $itemId)->pluck('quantity', 'storage_location_id');
                        $rows = [];
                        foreach ($allLocations as $location) {
                            $index = array_search((int) $location->id, $locations, true);
                            $rows[$location->id] = ['set' => (string) ($index === false ? ($current[$location->id] ?? 0) : $item['quantities'][$index]), 'rationale' => 'Development sample v1: '.($refresh ? 'explicit local quantity refresh' : 'initial installation').'; illustrative inventory. Fixture revision: '.$fixture['version'].'.'];
                        }
                        app(StockPosting::class)->post($actorId, $itemId, $first && ! DB::table('inventory_adjustments')->where('operation_id', self::INSTALLATION)->exists() ? self::INSTALLATION : (string) Str::uuid(), $rows);
                        $first = false;
                    }
                }
            }
        }, 3);
    }

    public function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__.'/fixtures/development-samples.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function assertContext(bool $hosted, bool $refresh = false): void
    {
        $approvedContext = $hosted
            ? app()->environment('development') && config('app.url') === 'https://dev-inventory.tabletopgaymers.org' && ! $refresh
            : app()->environment(['local', 'testing']) && config('app.url') === 'https://tg-inventory-app.test';
        if (! $approvedContext
            || ! app(BaselineProbe::class)->inspect()['ready']
            || ! app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady']) {
            throw new LogicException('Sample installation requires an explicitly approved development baseline.');
        }
    }

    private function record(string $kind, array $identity, array $values = []): int
    {
        $matches = DB::table($kind)->where($identity)->lockForUpdate()->get();
        if ($matches->count() > 1) {
            throw new LogicException('Ambiguous sample identity; inspect before retrying.');
        }
        $id = $matches->isEmpty()
            ? (int) DB::table($kind)->insertGetId($identity + $values + ['created_at' => now('UTC'), 'updated_at' => now('UTC')])
            : (int) $matches->first()->id;
        if ($kind !== 'items') {
            $name = DB::table($kind)->where('id', $id)->value('name');
            $registry = DB::table('catalog_names')->where(['kind' => $kind, 'record_id' => $id])->first();
            if (! $registry) {
                DB::table('catalog_names')->insert(['kind' => $kind, 'record_id' => $id, 'parent_id' => $identity['category_id'] ?? 0, 'name' => $name]);
            }
        }

        return $id;
    }
}
