<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\CatalogSchema;
use App\Support\DailyInventorySchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DailyInventorySchemaTest extends TestCase
{
    private bool $ownsFixture = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        if (array_intersect(DailyInventorySchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false)) !== []) {
            $this->markTestSkipped('Installed count/cost tables preserved; pre-installer bridge rehearsal already retained.');
        }
        $this->assertFalse(DB::table('migrations')->where('migration', DailyInventorySchema::MIGRATION)->exists());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady']);
    }

    protected function tearDown(): void
    {
        if ($this->ownsFixture) {
            foreach (array_reverse(DailyInventorySchema::TABLES) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
            DB::table('migrations')->where('migration', DailyInventorySchema::MIGRATION)->delete();
        }
        parent::tearDown();
    }

    private function fixture(): void
    {
        $this->ownsFixture = true;
        $id = 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        foreach ([
            "CREATE TABLE inventory_count_operations ($id, operation_id CHAR(36) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, storage_location_id BIGINT UNSIGNED NOT NULL, posted_at TIMESTAMP NOT NULL, UNIQUE KEY(operation_id), FOREIGN KEY(actor_id) REFERENCES users(id), FOREIGN KEY(storage_location_id) REFERENCES storage_locations(id)) ENGINE=InnoDB",
            'CREATE TABLE inventory_count_items (inventory_count_operation_id BIGINT UNSIGNED NOT NULL, item_id BIGINT UNSIGNED NOT NULL, inventory_adjustment_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(inventory_count_operation_id,item_id), UNIQUE KEY(inventory_adjustment_id), FOREIGN KEY(inventory_count_operation_id) REFERENCES inventory_count_operations(id), FOREIGN KEY(item_id) REFERENCES items(id), FOREIGN KEY(inventory_adjustment_id) REFERENCES inventory_adjustments(id)) ENGINE=InnoDB',
            "CREATE TABLE item_cost_entries ($id, operation_id CHAR(36) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, item_id BIGINT UNSIGNED NOT NULL, actor_name VARCHAR(255) NOT NULL, item_name VARCHAR(255) NOT NULL, item_sku VARCHAR(101) NOT NULL, before_cost DECIMAL(24,12) NOT NULL, after_cost DECIMAL(24,12) NOT NULL, rationale VARCHAR(1000) NULL, posted_at TIMESTAMP NOT NULL, UNIQUE KEY cost_operation(operation_id), CONSTRAINT cost_actor FOREIGN KEY(actor_id) REFERENCES users(id), FOREIGN KEY(item_id) REFERENCES items(id)) ENGINE=InnoDB",
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('migrations')->insert(['migration' => DailyInventorySchema::MIGRATION, 'batch' => 5]);
    }

    private function rejected(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['dailyInventoryReady']);
        $this->artisan('catalog:prepare')->assertExitCode(1);
    }

    public function test_absent_and_complete_recorded_future_schema_are_compatible_without_business_writes(): void
    {
        $probe = app(BaselineProbe::class);
        $before = DB::table('inventory_adjustments')->count();
        $this->assertTrue($probe->inspect()['ready']);
        $this->assertFalse($probe->schemaState(DB::connection())['dailyInventoryReady']);
        $this->fixture();
        $this->assertTrue($probe->inspect()['ready']);
        $this->assertTrue($probe->schemaState(DB::connection())['dailyInventoryReady']);
        $this->assertSame($before, DB::table('inventory_adjustments')->count());
        foreach (DailyInventorySchema::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_orphan_missing_ledger_and_partial_schema_fail_closed(): void
    {
        $this->ownsFixture = true;
        DB::table('migrations')->insert(['migration' => DailyInventorySchema::MIGRATION, 'batch' => 5]);
        $this->rejected();
        DB::table('migrations')->where('migration', DailyInventorySchema::MIGRATION)->delete();
        $this->fixture();
        DB::table('migrations')->where('migration', DailyInventorySchema::MIGRATION)->delete();
        $this->rejected();
        DB::table('migrations')->insert(['migration' => DailyInventorySchema::MIGRATION, 'batch' => 5]);
        DB::statement('DROP TABLE inventory_count_items');
        $this->rejected();
    }

    public function test_malformed_precision_uniqueness_foreign_keys_and_engine_fail_closed(): void
    {
        $this->fixture();
        DB::statement('ALTER TABLE item_cost_entries MODIFY after_cost DECIMAL(24,3) NOT NULL');
        $this->rejected();
        DB::statement('ALTER TABLE item_cost_entries MODIFY after_cost DECIMAL(24,12) NOT NULL');
        DB::statement('ALTER TABLE item_cost_entries DROP INDEX cost_operation');
        $this->rejected();
        DB::statement('ALTER TABLE item_cost_entries ADD UNIQUE KEY cost_operation(operation_id)');
        DB::statement('ALTER TABLE item_cost_entries DROP FOREIGN KEY cost_actor');
        $this->rejected();
        DB::statement('ALTER TABLE item_cost_entries ADD CONSTRAINT cost_actor FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE CASCADE');
        $this->rejected();
        DB::statement('ALTER TABLE item_cost_entries DROP FOREIGN KEY cost_actor');
        DB::statement('ALTER TABLE item_cost_entries ADD CONSTRAINT cost_actor FOREIGN KEY(actor_id) REFERENCES users(id)');
        $database = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn(DB::getSchemaBuilder());
        $database->shouldReceive('table')->with('migrations')->andReturn(DB::table('migrations'));
        $database->shouldReceive('getDatabaseName')->andReturn(DB::connection()->getDatabaseName());
        $database->shouldReceive('select')->andReturn([(object) ['name' => 'item_cost_entries', 'engine' => 'MyISAM']]);
        $this->assertFalse(app(DailyInventorySchema::class)->state($database, DB::getSchemaBuilder()->getTableListing(null, false))['compatible']);
    }

    public function test_complete_future_schema_requires_catalog_prerequisite_and_unknown_table_is_rejected(): void
    {
        $this->fixture();
        $catalog = Mockery::mock(CatalogSchema::class);
        $catalog->shouldReceive('state')->andReturn(['compatible' => true, 'ready' => false]);
        $this->app->instance(CatalogSchema::class, $catalog);
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->app->instance(CatalogSchema::class, new CatalogSchema);
        DB::statement('CREATE TABLE unexpected_daily_table (id INT) ENGINE=InnoDB');
        try {
            $state = app(BaselineProbe::class)->schemaState(DB::connection());
            $this->assertFalse($state['ready']);
            $this->assertFalse($state['canPrepare']);
        } finally {
            DB::statement('DROP TABLE unexpected_daily_table');
        }
    }
}
