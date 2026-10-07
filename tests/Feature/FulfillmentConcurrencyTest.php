<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\PurchaseFulfillment;
use App\Support\PurchaseRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FulfillmentConcurrencyTest extends TestCase
{
    public function test_two_receipts_serialize_current_average_and_duplicate_or_competing_confirmations_post_once(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady']);
        $directory = base_path('_private/bison-22');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $user = null;
        $item = $category = $collection = $location = $supplier = null;
        $requests = [];
        $processes = [];
        try {
            $user = User::create(['first_name' => 'Test', 'last_name' => 'Procurement', 'contact_email' => 'request-test@tabletopgaymers.org', 'contact_attested' => true]);
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'procurement']);
            $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
            $collection = DB::table('collections')->insertGetId(['name' => 'Pronoun', 'sku_prefix' => 'FCON-', 'category_id' => $category]);
            $item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $collection, 'sku_suffix' => 'A', 'sku' => 'FCON-'.Str::uuid(), 'unit_cost' => '0.2']);
            $location = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
            $supplier = DB::table('catalog_references')->insertGetId(['kind' => 'suppliers', 'name' => 'Approved Sample Supplier', 'state' => 'active']);
            DB::table('inventory_balances')->insert(['item_id' => $item, 'storage_location_id' => $location, 'quantity' => 10]);
            $invoice = fn ($unit) => ['title' => 'Approved sample purchase', 'supplier_id' => $supplier, 'receiving_location_id' => $location, 'ordered_date' => '2026-10-07', 'received_date' => '2026-10-08', 'lines' => [$item => ['quantity' => '5', 'unit' => $unit, 'cost' => $unit === '0.4' ? '2' : '4', 'fee' => '0']]];
            $service = app(PurchaseFulfillment::class);
            foreach (['0.4', '0.8', '0.4', '0.8'] as $unit) {
                $id = app(PurchaseRequests::class)->save($user->id, null, null, ['title' => 'Approved sample purchase']);
                $requests[] = $id;
                $service->work($user->id, $id, 1, $invoice($unit), 'order', 'confirm', (string) Str::uuid());
            }
            foreach ([[$requests[0], $requests[1], false, false], [$requests[2], $requests[2], true, false], [$requests[3], $requests[3], false, true]] as [$first, $second, $duplicate, $competing]) {
                $token = (string) Str::uuid();
                $gate = (string) Str::uuid();
                $firstUnit = $first === $requests[3] ? '0.8' : '0.4';
                $secondUnit = $second === $requests[1] || $second === $requests[3] ? '0.8' : '0.4';
                $a = new Process([PHP_BINARY, 'tests/fulfillment-worker.php', (string) $user->id, (string) $first, $token, json_encode($invoice($firstUnit), JSON_THROW_ON_ERROR), 'hold', $gate], base_path());
                $b = new Process([PHP_BINARY, 'tests/fulfillment-worker.php', (string) $user->id, (string) $second, $duplicate ? $token : (string) Str::uuid(), json_encode($invoice($secondUnit), JSON_THROW_ON_ERROR), 'peer', $gate], base_path());
                $processes = [$a, $b];
                foreach ($processes as $process) {
                    $process->setTimeout(45);
                }
                $a->start();
                $this->assertTrue($a->waitUntil(fn ($type, $text) => str_contains($text, 'READY')));
                $b->start();
                $a->wait();
                $b->wait();
                $this->assertTrue($a->isSuccessful(), $a->getErrorOutput());
                $this->assertTrue($b->isSuccessful(), $b->getErrorOutput());
                $this->assertStringContainsString($competing ? 'CONFLICT' : 'POSTED', $b->getOutput());
                if ($first === $requests[0]) {
                    $this->assertSame('0.400000000000', DB::table('items')->where('id', $item)->value('unit_cost'));
                    $this->assertSame(['0.266666666667', '0.400000000000'], DB::table('request_stock_entries')->where('item_id', $item)->where('entry_kind', 'purchase_receipt')->orderBy('id')->pluck('unit_cost')->all());
                }
            }
            $this->assertSame(30, (int) DB::table('inventory_balances')->where('item_id', $item)->value('quantity'));
            $this->assertSame(4, DB::table('request_stock_entries')->where('item_id', $item)->where('entry_kind', 'purchase_receipt')->count());
            $this->assertSame(0, (int) DB::table('inventory_source_balances')->where('item_id', $item)->sum('quantity'));
            $this->assertSame('0.466666666667', DB::table('items')->where('id', $item)->value('unit_cost'));
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach (['request_stock_entries', 'purchase_fulfillment_lines', 'purchase_fulfillment', 'purchase_request_notes', 'purchase_request_activity', 'purchase_request_lines'] as $table) {
                DB::table($table)->whereIn('purchase_request_id', $requests)->delete();
            }
            DB::table('purchase_requests')->whereIn('id', $requests)->delete();
            if ($item !== null) {
                DB::table('inventory_source_balances')->where('item_id', $item)->delete();
                DB::table('inventory_balances')->where('item_id', $item)->delete();
                DB::table('items')->where('id', $item)->delete();
            }
            if ($collection !== null) {
                DB::table('collections')->where('id', $collection)->delete();
            }
            if ($category !== null) {
                DB::table('categories')->where('id', $category)->delete();
            }
            if ($location !== null) {
                DB::table('storage_locations')->where('id', $location)->delete();
            }
            if ($supplier !== null) {
                DB::table('catalog_references')->where('id', $supplier)->delete();
            }
            if ($user !== null) {
                DB::table('user_roles')->where('user_id', $user->id)->delete();
                DB::table('users')->where('id', $user->id)->delete();
            }
        }
    }
}
