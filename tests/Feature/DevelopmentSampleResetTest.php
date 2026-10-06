<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\CatalogSchema;
use App\Support\DailyInventorySchema;
use App\Support\DevelopmentSampleReset;
use App\Support\InventorySchema;
use App\Support\RequestSchema;
use App\Support\StockPosting;
use Database\Seeders\DevelopmentSampleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use Tests\TestCase;

class DevelopmentSampleResetTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['requestsReady']);
        DB::beginTransaction();
        $this->actor = User::create(['first_name' => 'Test', 'last_name' => 'Reset']);
        DB::table('user_roles')->insert(['user_id' => $this->actor->id, 'role' => 'admin']);
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->update(['user_id' => $this->actor->id]);
        app(DevelopmentSampleSeeder::class)->run();
        $this->populateDependencies();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_reset_clears_exact_business_scope_with_foreign_keys_and_preserves_accounts_and_schema(): void
    {
        $scope = array_merge(InventorySchema::TABLES, CatalogSchema::TABLES, DailyInventorySchema::TABLES, RequestSchema::TABLES);
        $this->assertEqualsCanonicalizing($scope, DevelopmentSampleReset::TABLES);
        $protected = $this->protected();
        foreach (DevelopmentSampleReset::TABLES as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertSuccessful();
        $this->assertSame($protected, $this->protected());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['requestsReady']);
        foreach (['categories' => 9, 'collections' => 21, 'items' => 61, 'storage_locations' => 5, 'inventory_adjustments' => 62, 'inventory_adjustment_entries' => 216] as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        foreach (array_merge(RequestSchema::TABLES, DailyInventorySchema::TABLES, ['catalog_references', 'catalog_metadata', 'item_metadata', 'item_programs', 'inventory_sources', 'inventory_source_balances', 'inventory_preferences']) as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(24837, (int) DB::table('inventory_balances')->sum('quantity'));
        $this->assertSame(0, DB::table('items')->where('unit_cost', '<>', '0')->count());
        $receipt = DB::table('inventory_adjustments')->orderByDesc('id')->first();
        $this->assertSame($this->actor->id, $receipt->actor_id);
        $this->assertSame('BTN-STD-ALLY', $receipt->item_sku);
        $this->assertSame(0, DB::table('inventory_adjustment_entries')->where('inventory_adjustment_id', $receipt->id)->count());
        foreach (DevelopmentSampleReset::TABLES as $table) {
            foreach (DB::connection()->getSchemaBuilder()->getForeignKeys($table) as $foreign) {
                $column = $foreign['columns'][0];
                $parent = $foreign['foreign_table'];
                $target = $foreign['foreign_columns'][0];
                $orphans = DB::table($table.' as child')->leftJoin($parent.' as parent', 'child.'.$column, '=', 'parent.'.$target)->whereNotNull('child.'.$column)->whereNull('parent.'.$target)->count();
                $this->assertSame(0, $orphans, $table.':'.$column);
            }
        }
        $fixture = json_decode(file_get_contents(database_path('seeders/fixtures/development-samples.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($fixture['categories'] as $category) {
            foreach ($category['collections'] as $collection) {
                foreach ($collection['items'] as $item) {
                    $id = DB::table('items')->where('sku', $collection['prefix'].$item['suffix'])->sole()->id;
                    foreach ($fixture['locations'] as $index => $name) {
                        $location = DB::table('storage_locations')->where('name', $name)->sole()->id;
                        $this->assertSame($item['quantities'][$index], (int) (DB::table('inventory_balances')->where(['item_id' => $id, 'storage_location_id' => $location])->value('quantity') ?? 0));
                    }
                }
            }
        }
        DB::table('items')->where('sku', 'BTN-STD-ALLY')->update(['name' => 'Later user edit', 'unit_cost' => '1.234567890123']);
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot());
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertSuccessful();
        $this->assertSame('Ally', DB::table('items')->where('sku', 'BTN-STD-ALLY')->value('name'));
        $this->assertSame('0.000000000000', DB::table('items')->where('sku', 'BTN-STD-ALLY')->value('unit_cost'));
        $this->assertSame(62, DB::table('inventory_adjustments')->count());
        $this->assertNotSame($receipt->operation_id, DB::table('inventory_adjustments')->orderByDesc('id')->value('operation_id'));
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_failure_after_deletion_rolls_back_every_original_business_and_protected_row(): void
    {
        $snapshot = $this->snapshot();
        $real = new StockPosting;
        $calls = 0;
        $mock = Mockery::mock(StockPosting::class);
        $mock->shouldReceive('post')->andReturnUsing(function (...$arguments) use ($real, &$calls) {
            if (++$calls === 2) {
                throw new LogicException('Synthetic reseed failure after deletion and first stock post');
            }

            return $real->post(...$arguments);
        });
        $this->app->instance(StockPosting::class, $mock);
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $this->app->instance(StockPosting::class, $real);
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertSuccessful();
    }

    public function test_reset_guards_confirmation_local_production_hosted_option_and_admin_access(): void
    {
        $snapshot = $this->snapshot();
        $this->artisan('samples:reset')->assertFailed();
        $this->artisan('samples:reset', ['--confirm' => 'wrong'])->assertFailed();
        foreach (['local', 'production', 'development'] as $environment) {
            $this->app->instance('env', $environment);
            $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        }
        $this->app->instance('env', 'testing');
        $this->artisan('samples:reset', ['--hosted' => true, '--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        DB::table('user_roles')->where('user_id', $this->actor->id)->update(['role' => 'manager']);
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        DB::table('user_roles')->where('user_id', $this->actor->id)->update(['role' => 'admin']);
        DB::table('users')->where('id', $this->actor->id)->update(['enabled' => false]);
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        DB::table('users')->where('id', $this->actor->id)->update(['enabled' => true]);
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_reviewed_fixture_extension_is_adopted_by_explicit_reset_without_automatic_replay(): void
    {
        $fixture = app(DevelopmentSampleSeeder::class)->fixture();
        $fixture['version'] = 'development-samples-v2-test';
        $fixture['categories'][0]['collections'][0]['items'][] = ['name' => 'Ally spare stock', 'suffix' => 'GROWTH-TEST', 'quantities' => [5, 4, 3, 2, 1]];
        $seeder = Mockery::mock(DevelopmentSampleSeeder::class)->makePartial();
        $seeder->shouldReceive('fixture')->andReturn($fixture);
        $this->app->instance(DevelopmentSampleSeeder::class, $seeder);
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot());
        $this->artisan('samples:reset', ['--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertSuccessful();
        $this->assertSame(62, DB::table('items')->count());
        $item = DB::table('items')->where('sku', 'BTN-STD-GROWTH-TEST')->sole();
        $this->assertSame(15, (int) DB::table('inventory_balances')->where('item_id', $item->id)->sum('quantity'));
        $this->assertSame(24852, (int) DB::table('inventory_balances')->sum('quantity'));
        $this->assertTrue(DB::table('inventory_adjustment_entries')->where('rationale', 'like', '%Fixture revision: development-samples-v2-test.%')->exists());
        $snapshot = $this->snapshot();
        $this->artisan('samples:seed')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_hosted_reset_branch_and_incomplete_schema_fail_closed(): void
    {
        $this->app->instance('env', 'development');
        config(['app.url' => 'https://dev-inventory.tabletopgaymers.org']);
        $snapshot = $this->snapshot();
        $this->artisan('samples:reset', ['--hosted' => true, '--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldReceive('inspect')->andReturn(['ready' => true]);
        $probe->shouldReceive('schemaState')->andReturn(['catalogReady' => true, 'requestsReady' => false]);
        $this->app->instance(BaselineProbe::class, $probe);
        $this->artisan('samples:reset', ['--hosted' => true, '--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertFailed();
        $this->assertSame($snapshot, $this->snapshot());
        $ready = Mockery::mock(BaselineProbe::class);
        $ready->shouldReceive('inspect')->andReturn(['ready' => true]);
        $ready->shouldReceive('schemaState')->andReturn(['catalogReady' => true, 'requestsReady' => true]);
        $this->app->instance(BaselineProbe::class, $ready);
        $protected = $this->protected();
        $this->artisan('samples:reset', ['--hosted' => true, '--confirm' => DevelopmentSampleReset::CONFIRMATION])->assertSuccessful();
        $this->assertSame($protected, $this->protected());
        $this->assertSame(61, DB::table('items')->count());
        $this->assertSame(5, DB::table('storage_locations')->count());
    }

    private function populateDependencies(): void
    {
        $installation = DB::table('inventory_adjustments')->where('operation_id', DevelopmentSampleSeeder::INSTALLATION)->sole();
        $item = DB::table('items')->where('id', $installation->item_id)->sole();
        $location = DB::table('storage_locations')->where('name', 'Central')->sole()->id;
        $reference = DB::table('catalog_references')->insertGetId(['kind' => 'purposes', 'name' => 'Disposable test reference', 'state' => 'active']);
        DB::table('catalog_metadata')->insert(['kind' => 'items', 'record_id' => $item->id, 'state' => 'active', 'notes' => 'Disposable test metadata']);
        DB::table('item_metadata')->insert(['item_id' => $item->id, 'purpose_id' => $reference]);
        DB::table('item_programs')->insert(['item_id' => $item->id, 'program_id' => $reference]);
        DB::table('inventory_preferences')->insert(['user_id' => $this->actor->id, 'criteria' => '{}']);
        $source = DB::table('inventory_sources')->insertGetId(['kind' => 'event', 'name' => 'Gen Con', 'active' => true]);
        DB::table('inventory_source_balances')->insert(['item_id' => $item->id, 'source_id' => $source, 'quantity' => 11]);
        $count = DB::table('inventory_count_operations')->insertGetId(['operation_id' => (string) Str::uuid(), 'actor_id' => $this->actor->id, 'storage_location_id' => $location, 'posted_at' => now('UTC')]);
        DB::table('inventory_count_items')->insert(['inventory_count_operation_id' => $count, 'item_id' => $item->id, 'inventory_adjustment_id' => $installation->id]);
        DB::table('item_cost_entries')->insert(['operation_id' => (string) Str::uuid(), 'actor_id' => $this->actor->id, 'item_id' => $item->id, 'actor_name' => 'Test Reset', 'item_name' => $item->name, 'item_sku' => $item->sku, 'before_cost' => '0', 'after_cost' => '1.234567890123', 'posted_at' => now('UTC')]);
        DB::table('items')->where('id', $item->id)->update(['unit_cost' => '1.234567890123']);
        $purchase = DB::table('purchase_requests')->insertGetId(['owner_id' => $this->actor->id, 'status' => 'draft', 'revision' => 1, 'supplier_id' => $reference, 'receiving_location_id' => $location]);
        DB::table('purchase_request_lines')->insert(['purchase_request_id' => $purchase, 'item_id' => $item->id, 'quantity' => 5]);
        DB::table('purchase_request_notes')->insert(['purchase_request_id' => $purchase, 'actor_id' => $this->actor->id, 'actor_name' => 'Test Reset', 'body' => 'Disposable development request note', 'occurred_at' => now('UTC')]);
        DB::table('purchase_request_activity')->insert(['purchase_request_id' => $purchase, 'actor_id' => $this->actor->id, 'actor_name' => 'Test Reset', 'action' => 'created', 'occurred_at' => now('UTC')]);
        $relocation = DB::table('relocation_requests')->insertGetId(['owner_id' => $this->actor->id, 'title' => 'Disposable development relocation', 'status' => 'draft', 'revision' => 1, 'source_location_id' => $location, 'destination_location_id' => $location]);
        DB::table('relocation_request_lines')->insert(['relocation_request_id' => $relocation, 'item_id' => $item->id, 'quantity' => 5]);
        DB::table('relocation_request_activity')->insert(['relocation_request_id' => $relocation, 'actor_id' => $this->actor->id, 'actor_name' => 'Test Reset', 'action' => 'created', 'occurred_at' => now('UTC')]);
    }

    private function protected(): array
    {
        return $this->snapshot(['users', 'external_identities', 'user_roles', 'authentication_bootstraps', 'access_audits', 'migrations', 'sessions']);
    }

    private function snapshot(?array $tables = null): array
    {
        $snapshot = [];
        foreach ($tables ?? DB::connection()->getSchemaBuilder()->getTableListing(null, false) as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row))->all();
            sort($rows);
            $snapshot[$table] = hash('sha256', json_encode($rows));
        }

        return $snapshot;
    }
}
