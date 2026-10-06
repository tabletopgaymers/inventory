<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InventoryPreparation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    public function test_overlapping_saves_serialize_deltas_and_duplicate_operation_posts_once(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        // This committed subprocess fixture requires a clean, disposable stock dataset.
        $this->assertSame(0, DB::table('items')->count());
        $this->assertSame(0, DB::table('storage_locations')->count());
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Manager']);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'manager']);
        app(InventoryPreparation::class)->demo(false);
        $item = (int) DB::table('items')->where('sku', 'SAMPLE001')->value('id');
        $collection = DB::table('items')->where('id', $item)->value('collection_id');
        $category = DB::table('collections')->where('id', $collection)->value('category_id');
        $locations = DB::table('storage_locations')->pluck('id')->all();
        $processes = [];
        try {
            foreach ([[10, 20, false], [30, 30, true]] as [$first, $second, $duplicate]) {
                $token = (string) Str::uuid();
                $rows = fn ($quantity) => json_encode(array_fill_keys($locations, ['set' => (string) $quantity, 'adjust' => '', 'rationale' => '']), JSON_THROW_ON_ERROR);
                $a = new Process([PHP_BINARY, 'tests/stock-worker.php', (string) $user->id, (string) $item, $token, $rows($first), 'hold'], base_path());
                $b = new Process([PHP_BINARY, 'tests/stock-worker.php', (string) $user->id, (string) $item, $duplicate ? $token : (string) Str::uuid(), $rows($second)], base_path());
                $processes = [$a, $b];
                $a->setTimeout(20);
                $b->setTimeout(20);
                $a->start();
                $this->assertTrue($a->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
                $b->start();
                $a->wait();
                $b->wait();
                $this->assertTrue($a->isSuccessful(), $a->getErrorOutput());
                $this->assertTrue($b->isSuccessful(), $b->getErrorOutput());
            }
            $this->assertSame(3, DB::table('inventory_adjustments')->where('item_id', $item)->count());
            $groups = DB::table('inventory_adjustments')->where('item_id', $item)->orderBy('id')->get();
            foreach ($locations as $location) {
                $entries = DB::table('inventory_adjustment_entries')->where('storage_location_id', $location)->orderBy('id')->get();
                $this->assertSame([0, 10, 20], $entries->pluck('before_quantity')->map(fn ($q) => (int) $q)->all());
                $this->assertSame([10, 20, 30], $entries->pluck('after_quantity')->map(fn ($q) => (int) $q)->all());
                $this->assertSame(30, (int) $entries->sum('quantity_change'));
                $this->assertSame(30, (int) DB::table('inventory_balances')->where('item_id', $item)->where('storage_location_id', $location)->value('quantity'));
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            $ids = DB::table('inventory_adjustments')->where('item_id', $item)->pluck('id')->all();
            DB::table('inventory_adjustment_entries')->whereIn('inventory_adjustment_id', $ids)->delete();
            DB::table('inventory_adjustments')->where('item_id', $item)->delete();
            DB::table('inventory_balances')->where('item_id', $item)->delete();
            DB::table('items')->where('id', $item)->delete();
            DB::table('collections')->where('id', $collection)->delete();
            DB::table('categories')->where('id', $category)->delete();
            DB::table('storage_locations')->whereIn('id', $locations)->delete();
            DB::table('user_roles')->where('user_id', $user->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        }
    }
}
