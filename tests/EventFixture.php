<?php

namespace Tests;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\EventWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait EventFixture
{
    protected User $manager;

    protected User $other;

    protected User $basic;

    protected int $item;

    protected int $second;

    protected int $source;

    protected int $destination;

    protected int $eventCategory;

    protected int $eventCollection;

    protected function eventFixture(bool $transaction = true): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertSame('tg_inventory_test', DB::connection()->getConfig('username'));
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['eventsReady']);
        if ($transaction) {
            DB::beginTransaction();
        }
        foreach (['manager', 'other', 'basic'] as $name) {
            $this->{$name} = User::create(['first_name' => 'Test', 'last_name' => ucfirst($name), 'contact_email' => 'request-test@tabletopgaymers.org', 'contact_attested' => true]);
            DB::table('user_roles')->insert(['user_id' => $this->{$name}->id, 'role' => $name === 'basic' ? 'basic' : 'manager']);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $this->eventCategory = $category;
        $collection = DB::table('collections')->insertGetId(['category_id' => $category, 'name' => 'Pronoun', 'sku_prefix' => 'EHTTP-']);
        $this->eventCollection = $collection;
        $this->item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $collection, 'sku_suffix' => 'A', 'sku' => 'EHTTP-A', 'unit_cost' => '0.2']);
        $this->second = DB::table('items')->insertGetId(['name' => 'They/Them Ribbon', 'collection_id' => $collection, 'sku_suffix' => 'B', 'sku' => 'EHTTP-B', 'unit_cost' => '0']);
        $this->source = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
        $this->destination = DB::table('storage_locations')->insertGetId(['name' => 'Seattle WA', 'is_central' => false]);
        DB::table('inventory_balances')->insert(['item_id' => $this->item, 'storage_location_id' => $this->source, 'quantity' => 2000]);
    }

    protected function signInEvent(User $user): void
    {
        $this->actingAs($user)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
    }

    protected function eventPlan(int $quantity = 1000): int
    {
        return app(EventWorkflow::class)->plan($this->manager->id, null, null, ['name' => 'Approved sample event', 'initial_source_id' => $this->source, 'default_destination' => 'storage:'.$this->source,
            'supplies' => [['item_id' => $this->item, 'source' => 'storage:'.$this->source, 'quantity' => $quantity]]], (string) Str::uuid());
    }

    protected function activeEvent(int $quantity = 1000): int
    {
        $id = $this->eventPlan($quantity);
        app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', [], 'confirm', (string) Str::uuid());

        return $id;
    }

    protected function eventCounts(int $remaining = 250): array
    {
        return ['lines' => [$this->item => ['remaining' => (string) $remaining, 'allocations' => [['destination' => 'storage:'.$this->source, 'quantity' => (string) $remaining]]]]];
    }

    protected function eventInventory(): array
    {
        $result = [];
        foreach (['items', 'inventory_balances', 'inventory_source_balances', 'event_stock_entries', 'request_stock_entries', 'inventory_adjustment_entries', 'item_cost_entries'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }
}
