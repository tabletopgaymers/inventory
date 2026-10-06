<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\InventorySearch;
use App\Support\StockPosting;
use Database\Seeders\DevelopmentSampleSeeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Mockery;
use Tests\TestCase;

class DevelopmentSampleSeederTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady']);
        DB::beginTransaction();
        $this->actor = User::create(['first_name' => 'Test', 'last_name' => 'Seeder']);
        DB::table('user_roles')->insert(['user_id' => $this->actor->id, 'role' => 'manager']);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_complete_fixture_totals_audit_and_rerun_preserve_later_activity(): void
    {
        $users = DB::table('users')->get()->toJson();
        $roles = DB::table('user_roles')->get()->toJson();
        $before = DB::table('inventory_adjustments')->count();
        $seeder = app(DevelopmentSampleSeeder::class);
        $seeder->run($this->actor->id);
        $fixture = json_decode(file_get_contents(database_path('seeders/fixtures/development-samples.json')), true, flags: JSON_THROW_ON_ERROR);
        $expectedLocations = array_fill_keys($fixture['locations'], 0);
        $ids = [];
        $zero = 0;
        foreach ($fixture['categories'] as $category) {
            $categoryId = DB::table('categories')->where('name', $category['name'])->sole()->id;
            foreach ($category['collections'] as $collection) {
                $collectionId = DB::table('collections')->where(['category_id' => $categoryId, 'name' => $collection['name']])->sole()->id;
                $this->assertSame($collectionId, DB::table('catalog_names')->where(['kind' => 'collections', 'parent_id' => $categoryId, 'name' => $collection['name']])->sole()->record_id);
                foreach ($collection['items'] as $item) {
                    $record = DB::table('items')->where('sku', $collection['prefix'].$item['suffix'])->sole();
                    $this->assertSame($collectionId, $record->collection_id);
                    $this->assertSame($item['name'], $record->name);
                    $ids[] = $record->id;
                    $zero += array_sum($item['quantities']) === 0 ? 1 : 0;
                    $this->assertSame(array_sum($item['quantities']), (int) DB::table('inventory_balances')->where('item_id', $record->id)->sum('quantity'));
                    foreach ($fixture['locations'] as $index => $name) {
                        $expectedLocations[$name] += $item['quantities'][$index];
                    }
                }
            }
        }
        $this->assertCount(61, $ids);
        $this->assertSame(7, $zero);
        $this->assertSame($before + 61, DB::table('inventory_adjustments')->count());
        foreach ($expectedLocations as $name => $total) {
            $location = DB::table('storage_locations')->where('name', $name)->sole();
            $this->assertSame($total, (int) DB::table('inventory_balances')->whereIn('item_id', $ids)->where('storage_location_id', $location->id)->sum('quantity'));
        }
        $results = app(InventorySearch::class)->results(app(InventorySearch::class)->defaults())['rows'];
        foreach ($results as $row) {
            if (in_array($row['id'], $ids)) {
                $this->assertSame((int) DB::table('inventory_balances')->where('item_id', $row['id'])->sum('quantity'), $row['available']);
            }
        }
        $entry = DB::table('inventory_adjustment_entries')->where('rationale', 'like', 'Development sample v1:%')->first();
        $this->assertNotNull($entry);
        $this->assertSame('0.000000000000', $entry->unit_cost);
        $this->assertSame($users, DB::table('users')->get()->toJson());
        $this->assertSame($roles, DB::table('user_roles')->get()->toJson());
        DB::table('items')->where('id', $ids[0])->update(['name' => 'Later user edit', 'sku' => 'EDITED-SKU', 'unit_cost' => '0.123456789012']);
        DB::table('categories')->where('id', DB::table('collections')->where('id', DB::table('items')->where('id', $ids[0])->value('collection_id'))->value('category_id'))->update(['name' => 'Renamed category']);
        DB::table('inventory_balances')->where('item_id', $ids[0])->update(['quantity' => 777]);
        $snapshot = $this->snapshot();
        $seeder->run($this->actor->id);
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_existing_hierarchy_is_reconciled_cost_and_other_locations_retained(): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $collection = DB::table('collections')->insertGetId(['category_id' => $category, 'name' => 'Pronoun', 'sku_prefix' => 'RPR-']);
        $item = DB::table('items')->insertGetId(['collection_id' => $collection, 'name' => 'They/Them', 'sku_suffix' => 'THEY', 'sku' => 'RPR-THEY', 'unit_cost' => '0.123456789012']);
        $location = DB::table('storage_locations')->insertGetId(['name' => 'Extra location', 'is_central' => false]);
        DB::table('inventory_balances')->insert(['item_id' => $item, 'storage_location_id' => $location, 'quantity' => 19]);
        app(DevelopmentSampleSeeder::class)->run($this->actor->id);
        $this->assertSame($item, DB::table('items')->where('sku', 'RPR-THEY')->sole()->id);
        $this->assertSame('0.123456789012', DB::table('items')->where('id', $item)->value('unit_cost'));
        $this->assertSame(19, DB::table('inventory_balances')->where(['item_id' => $item, 'storage_location_id' => $location])->sole()->quantity);
        $this->assertSame('0.123456789012', DB::table('inventory_adjustment_entries')->join('inventory_adjustments', 'inventory_adjustments.id', '=', 'inventory_adjustment_entries.inventory_adjustment_id')->where('inventory_adjustments.item_id', $item)->value('inventory_adjustment_entries.unit_cost'));
    }

    public function test_guard_rejects_production_host_database_and_invalid_actor_before_writes(): void
    {
        $snapshot = $this->snapshot();
        foreach (['production', 'development'] as $environment) {
            $this->app->instance('env', $environment);
            $this->assertRejected();
        }
        $this->app->instance('env', 'testing');
        config(['app.url' => 'https://unrelated.test']);
        $this->assertRejected();
        config(['app.url' => 'https://tg-inventory-app.test', 'database.connections.mariadb.database' => 'unrelated']);
        $this->assertRejected();
        config(['database.connections.mariadb.database' => 'tg_inventory_test']);
        DB::table('user_roles')->where('user_id', $this->actor->id)->delete();
        $this->artisan('samples:seed', ['--actor' => (string) $this->actor->id])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $this->artisan('samples:seed', ['--actor' => '0'])->assertFailed();
        $this->artisan('samples:seed', ['--actor' => (string) $this->actor->id, '--refresh' => true])->assertFailed();
    }

    public function test_atomic_failure_rolls_back_catalog_stock_and_receipt_then_retry_succeeds(): void
    {
        $snapshot = $this->snapshot();
        $real = new StockPosting;
        $calls = 0;
        $mock = Mockery::mock(StockPosting::class);
        $mock->shouldReceive('post')->andReturnUsing(function (...$arguments) use ($real, &$calls) {
            if (++$calls === 2) {
                throw new LogicException('Synthetic stock failure');
            }

            return $real->post(...$arguments);
        });
        $this->app->instance(StockPosting::class, $mock);
        $this->artisan('samples:seed', ['--actor' => (string) $this->actor->id])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $this->app->instance(StockPosting::class, $real);
        $this->artisan('samples:seed', ['--actor' => (string) $this->actor->id])->assertSuccessful();
        $this->assertTrue(DB::table('inventory_adjustments')->where('operation_id', DevelopmentSampleSeeder::INSTALLATION)->exists());
    }

    public function test_explicit_refresh_retains_identity_cost_and_old_history_and_default_rerun_stops(): void
    {
        $seeder = app(DevelopmentSampleSeeder::class);
        $seeder->run($this->actor->id);
        $item = DB::table('items')->where('sku', 'BTN-STD-ALLY')->sole();
        $central = DB::table('storage_locations')->where('name', 'Central')->sole();
        DB::table('items')->where('id', $item->id)->update(['unit_cost' => '0.333333333333']);
        DB::table('inventory_balances')->where(['item_id' => $item->id, 'storage_location_id' => $central->id])->update(['quantity' => 777]);
        $oldHistory = DB::table('inventory_adjustment_entries')->get()->keyBy('id');
        $seeder->run($this->actor->id, refresh: true);
        $this->assertSame($item->id, DB::table('items')->where('sku', 'BTN-STD-ALLY')->sole()->id);
        $this->assertSame('0.333333333333', DB::table('items')->where('id', $item->id)->value('unit_cost'));
        $this->assertSame(200, DB::table('inventory_balances')->where(['item_id' => $item->id, 'storage_location_id' => $central->id])->sole()->quantity);
        foreach ($oldHistory as $id => $entry) {
            $this->assertEquals($entry, DB::table('inventory_adjustment_entries')->where('id', $id)->sole());
        }
        $snapshot = $this->snapshot();
        $seeder->run($this->actor->id);
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_conflicting_sku_rolls_back_without_reparenting_user_data(): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Unrelated']);
        $collection = DB::table('collections')->insertGetId(['category_id' => $category, 'name' => 'Unrelated', 'sku_prefix' => 'BTN-STD-']);
        DB::table('items')->insert(['collection_id' => $collection, 'name' => 'Unrelated', 'sku_suffix' => 'ALLY', 'sku' => 'BTN-STD-ALLY', 'unit_cost' => '0']);
        $snapshot = $this->snapshot();
        $this->assertRejected();
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_unattended_actor_reuses_bootstrap_and_revalidates_current_access_on_rerun(): void
    {
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->update(['user_id' => $this->actor->id]);
        $users = DB::table('users')->get()->toJson();
        $roles = DB::table('user_roles')->get()->toJson();
        $bootstrap = DB::table('authentication_bootstraps')->get()->toJson();
        $this->artisan('samples:seed')->assertSuccessful();
        $this->assertSame($this->actor->id, DB::table('inventory_adjustments')->where('operation_id', DevelopmentSampleSeeder::INSTALLATION)->sole()->actor_id);
        $this->assertSame($users, DB::table('users')->get()->toJson());
        $this->assertSame($roles, DB::table('user_roles')->get()->toJson());
        $this->assertSame($bootstrap, DB::table('authentication_bootstraps')->get()->toJson());
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot());
        DB::table('users')->where('id', $this->actor->id)->update(['enabled' => false]);
        $this->artisan('samples:seed')->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_missing_bootstrap_actor_does_not_create_identity_or_install_receipt(): void
    {
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->update(['user_id' => null]);
        $snapshot = $this->snapshot();
        $users = DB::table('users')->get()->toJson();
        $this->artisan('samples:seed')->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame($users, DB::table('users')->get()->toJson());
    }

    public function test_hosted_requires_explicit_exact_context_existing_baseline_and_never_refreshes(): void
    {
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed', ['--hosted' => true])->assertFailed();
        $this->app->instance('env', 'development');
        config(['app.url' => 'https://dev-inventory.tabletopgaymers.org']);
        $this->artisan('samples:seed')->assertFailed();
        // The real baseline rejects this test database for hosted development.
        $this->artisan('samples:seed', ['--hosted' => true])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldReceive('inspect')->andReturn(['ready' => true]);
        $probe->shouldReceive('schemaState')->andReturn(['catalogReady' => true]);
        $this->app->instance(BaselineProbe::class, $probe);
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->update(['user_id' => $this->actor->id]);
        $this->artisan('samples:seed', ['--hosted' => true])->assertSuccessful();
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed', ['--hosted' => true])->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot());
        $this->artisan('samples:seed', ['--hosted' => true, '--refresh' => true])->assertFailed();
        try {
            app(DevelopmentSampleSeeder::class)->run(hosted: true, refresh: true);
            $this->fail('Expected hosted refresh rejection in the seeder itself.');
        } catch (LogicException) {
            $this->assertSame($snapshot, $this->snapshot());
        }
        config(['app.url' => 'https://unrelated.test']);
        $this->artisan('samples:seed', ['--hosted' => true])->assertFailed();
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://dev-inventory.tabletopgaymers.org']);
        $this->artisan('samples:seed', ['--hosted' => true])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
    }

    private function assertRejected(): void
    {
        try {
            app(DevelopmentSampleSeeder::class)->run($this->actor->id);
            $this->fail('Expected guard rejection.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['categories', 'collections', 'items', 'storage_locations', 'catalog_names', 'inventory_balances', 'inventory_adjustments', 'inventory_adjustment_entries'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
