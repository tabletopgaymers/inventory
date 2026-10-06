<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\InventorySchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    private bool $ownsFixture = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertSame([], array_values(array_intersect(InventorySchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false))));
        $this->assertFalse(DB::table('migrations')->where('migration', InventorySchema::MIGRATION)->exists());
    }

    protected function tearDown(): void
    {
        if ($this->ownsFixture) {
            foreach (array_reverse(InventorySchema::TABLES) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
            DB::table('migrations')->where('migration', InventorySchema::MIGRATION)->delete();
        }
        parent::tearDown();
    }

    public function test_bridge_accepts_pre_stock_and_complete_schema_without_mutating_rows(): void
    {
        $probe = app(BaselineProbe::class);
        $this->assertTrue($probe->inspect()['ready']);
        $this->assertFalse($probe->schemaState(DB::connection())['inventoryReady']);
        $this->createFixture();
        DB::table('categories')->insert(['name' => 'Sample Category']);
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        $state = $probe->schemaState(DB::connection());
        $this->assertTrue($state['ready']);
        $this->assertTrue($state['canPrepare']);
        $this->assertTrue($state['authenticationReady']);
        $this->assertTrue($state['inventoryReady']);
        $this->assertTrue($probe->inspect()['ready']);
        $this->assertSame(1, DB::table('categories')->count());
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/\A\s*(select|show)\b/i', $query);
        }
    }

    public function test_partial_or_unrecorded_stock_schema_fails_closed(): void
    {
        $this->createFixture();
        DB::table('migrations')->where('migration', InventorySchema::MIGRATION)->delete();
        $this->assertRejected();
        DB::table('migrations')->insert(['migration' => InventorySchema::MIGRATION, 'batch' => 3]);
        DB::statement('DROP TABLE inventory_adjustment_entries');
        $this->assertRejected();
    }

    public function test_migration_record_without_tables_fails_closed(): void
    {
        $this->ownsFixture = true;
        DB::table('migrations')->insert(['migration' => InventorySchema::MIGRATION, 'batch' => 3]);
        $this->assertRejected();
    }

    #[DataProvider('beforeAuthenticationStates')]
    public function test_orphan_stock_ledger_is_checked_before_authentication(array $tables, bool $sessionRecorded, bool $stockRecorded, bool $ready, bool $canPrepare): void
    {
        // Actual probe with a read-only schema/ledger double: no destructive auth DDL.
        $database = Mockery::mock();
        $schema = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $schema->shouldReceive('getTableListing')->with(null, false)->andReturn($tables);
        if (in_array('sessions', $tables, true)) {
            $schema->shouldReceive('getColumnListing')->with('sessions')->andReturn(['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity']);
        }
        if (in_array('migrations', $tables, true)) {
            $ledger = Mockery::mock();
            $session = Mockery::mock();
            $stock = Mockery::mock();
            $database->shouldReceive('table')->with('migrations')->andReturn($ledger);
            $ledger->shouldReceive('where')->with('migration', '2026_10_05_000000_create_sessions_table')->andReturn($session);
            $session->shouldReceive('exists')->once()->andReturn($sessionRecorded);
            $ledger->shouldReceive('where')->with('migration', InventorySchema::MIGRATION)->andReturn($stock);
            $stock->shouldReceive('exists')->once()->andReturn($stockRecorded);
        }
        $state = app(BaselineProbe::class)->schemaState($database);
        $this->assertSame($ready, $state['ready']);
        $this->assertSame($canPrepare, $state['canPrepare']);
        $this->assertFalse($state['authenticationReady']);
        $this->assertFalse($state['inventoryReady']);
    }

    public static function beforeAuthenticationStates(): array
    {
        return [
            'empty database permits guarded preparation' => [[], false, false, false, true],
            'empty ledger permits guarded preparation' => [['migrations'], false, false, false, true],
            'orphan stock in migration-only ledger rejects preparation' => [['migrations'], false, true, false, false],
            'orphan session and stock records reject preparation' => [['migrations'], true, true, false, false],
            'healthy session-only database remains ready' => [['migrations', 'sessions'], true, false, true, true],
            'session-only orphan stock rejects readiness and preparation' => [['migrations', 'sessions'], true, true, false, false],
            'unrecorded sessions with orphan stock reject preparation' => [['migrations', 'sessions'], false, true, false, false],
        ];
    }

    public function test_nontransactional_engine_metadata_is_rejected_with_other_contract_parts_intact(): void
    {
        $this->createFixture();
        $database = Mockery::mock();
        $database->shouldReceive('table')->with('migrations')->andReturn(DB::table('migrations'));
        $database->shouldReceive('getSchemaBuilder')->andReturn(DB::getSchemaBuilder());
        $database->shouldReceive('getDatabaseName')->andReturn(DB::connection()->getDatabaseName());
        $database->shouldReceive('select')->once()->andReturn([(object) ['name' => 'inventory_adjustment_entries', 'engine' => 'MyISAM']]);
        $state = app(InventorySchema::class)->state($database, DB::getSchemaBuilder()->getTableListing(null, false));
        $this->assertFalse($state['compatible']);
        $this->assertFalse($state['ready']);
    }

    public function test_malformed_stock_blocks_preparation_without_business_writes(): void
    {
        $this->createFixture();
        DB::table('categories')->insert(['name' => 'Sample Category']);
        DB::statement('ALTER TABLE items MODIFY unit_cost DECIMAL(24,3) NOT NULL');
        $this->artisan('authentication:prepare')->assertExitCode(1);
        $this->artisan('baseline:prepare')->assertExitCode(1);
        $this->assertSame(1, DB::table('categories')->count());
    }

    public function test_wrong_precision_unsigned_quantity_extra_column_and_missing_uniqueness_fail_closed(): void
    {
        $this->createFixture();
        DB::statement('ALTER TABLE items MODIFY unit_cost DECIMAL(24,3) NOT NULL');
        $this->assertRejected();
        DB::statement('ALTER TABLE items MODIFY unit_cost DECIMAL(24,12) NOT NULL');
        DB::statement('ALTER TABLE inventory_balances MODIFY quantity INT UNSIGNED NOT NULL');
        $this->assertRejected();
        DB::statement('ALTER TABLE inventory_balances MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE categories ADD unexpected INT NULL');
        $this->assertRejected();
        DB::statement('ALTER TABLE categories DROP unexpected');
        DB::statement('ALTER TABLE items DROP INDEX sku_unique');
        $this->assertRejected();
    }

    public function test_missing_or_cascading_reference_and_nontransactional_table_fail_closed(): void
    {
        $this->createFixture();
        DB::statement('ALTER TABLE inventory_adjustment_entries DROP FOREIGN KEY entry_location');
        $this->assertRejected();
        DB::statement('ALTER TABLE inventory_adjustment_entries ADD CONSTRAINT entry_location FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id) ON DELETE CASCADE');
        $this->assertRejected();
        DB::statement('ALTER TABLE inventory_adjustment_entries DROP FOREIGN KEY entry_location');
        DB::statement('ALTER TABLE inventory_adjustment_entries ADD CONSTRAINT entry_location FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id)');
        // A reference-free table can use a nontransactional engine without changing columns.
        DB::statement('ALTER TABLE inventory_adjustment_entries DROP FOREIGN KEY entry_location');
        DB::statement('ALTER TABLE inventory_adjustment_entries DROP FOREIGN KEY entry_adjustment');
        DB::statement('ALTER TABLE inventory_adjustment_entries ENGINE=MyISAM');
        $this->assertRejected();
    }

    private function assertRejected(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['inventoryReady']);
    }

    private function createFixture(): void
    {
        // Independent disposable SQL fixture, deliberately outside database/migrations.
        // The bridge cannot install business tables in local or hosted environments.
        $this->ownsFixture = true;
        $id = 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $timestamps = 'created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL';
        $statements = [
            "CREATE TABLE categories ($id, name VARCHAR(255) NOT NULL, $timestamps) ENGINE=InnoDB",
            "CREATE TABLE collections ($id, category_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, sku_prefix VARCHAR(50) NOT NULL, $timestamps, FOREIGN KEY (category_id) REFERENCES categories(id)) ENGINE=InnoDB",
            "CREATE TABLE items ($id, collection_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, sku_suffix VARCHAR(50) NOT NULL, sku VARCHAR(101) NOT NULL, unit_cost DECIMAL(24,12) NOT NULL, $timestamps, UNIQUE KEY sku_unique(sku), FOREIGN KEY (collection_id) REFERENCES collections(id)) ENGINE=InnoDB",
            "CREATE TABLE storage_locations ($id, name VARCHAR(255) NOT NULL, is_central TINYINT(1) NOT NULL, $timestamps) ENGINE=InnoDB",
            "CREATE TABLE inventory_balances ($id, item_id BIGINT UNSIGNED NOT NULL, storage_location_id BIGINT UNSIGNED NOT NULL, quantity INT NOT NULL, $timestamps, UNIQUE KEY balance_unique(item_id,storage_location_id), FOREIGN KEY (item_id) REFERENCES items(id), FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id)) ENGINE=InnoDB",
            "CREATE TABLE inventory_adjustments ($id, operation_id CHAR(36) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, item_id BIGINT UNSIGNED NOT NULL, actor_name VARCHAR(255) NOT NULL, item_name VARCHAR(255) NOT NULL, item_sku VARCHAR(101) NOT NULL, posted_at TIMESTAMP NOT NULL, UNIQUE KEY operation_unique(operation_id), FOREIGN KEY (actor_id) REFERENCES users(id), FOREIGN KEY (item_id) REFERENCES items(id)) ENGINE=InnoDB",
            "CREATE TABLE inventory_adjustment_entries ($id, inventory_adjustment_id BIGINT UNSIGNED NOT NULL, storage_location_id BIGINT UNSIGNED NOT NULL, location_name VARCHAR(255) NOT NULL, description VARCHAR(300) NOT NULL, before_quantity INT NOT NULL, after_quantity INT NOT NULL, quantity_change INT NOT NULL, unit_cost DECIMAL(24,12) NOT NULL, rationale VARCHAR(1000) NULL, UNIQUE KEY entry_unique(inventory_adjustment_id,storage_location_id), CONSTRAINT entry_adjustment FOREIGN KEY (inventory_adjustment_id) REFERENCES inventory_adjustments(id), CONSTRAINT entry_location FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id)) ENGINE=InnoDB",
        ];
        foreach ($statements as $statement) {
            DB::statement($statement);
        }
        DB::table('migrations')->insert(['migration' => InventorySchema::MIGRATION, 'batch' => 3]);
    }
}
