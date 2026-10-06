<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\DailyInventorySchema;
use App\Support\RequestSchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RequestSchemaTest extends TestCase
{
    private bool $ownsFixture = false;

    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        if (array_intersect(RequestSchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false)) !== []) {
            $this->markTestSkipped('Installed request tables preserved; bridge rehearsal retained separately.');
        }
        $this->assertFalse(DB::table('migrations')->where('migration', RequestSchema::MIGRATION)->exists());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['dailyInventoryReady']);
        $this->before = $this->acceptedData();
    }

    protected function tearDown(): void
    {
        if ($this->ownsFixture) {
            foreach (array_reverse(RequestSchema::TABLES) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
            DB::table('migrations')->where('migration', RequestSchema::MIGRATION)->delete();
            $this->assertSame($this->before, $this->acceptedData());
            $state = app(BaselineProbe::class)->schemaState(DB::connection());
            $this->assertTrue($state['ready']);
            $this->assertTrue($state['canPrepare']);
            $this->assertFalse($state['requestsReady']);
        }
        parent::tearDown();
    }

    private function acceptedData(): array
    {
        $result = [];
        foreach (DB::getSchemaBuilder()->getTableListing(null, false) as $table) {
            if (in_array($table, array_merge(RequestSchema::TABLES, ['sessions', 'migrations']), true)) {
                continue;
            }
            $rows = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), DB::table($table)->get()->all());
            sort($rows);
            $result[$table] = hash('sha256', implode('\n', $rows));
        }
        ksort($result);

        return $result;
    }

    private function fixture(): void
    {
        $this->ownsFixture = true;
        $id = 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $dates = 'created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL';
        $activity = 'actor_id BIGINT UNSIGNED NOT NULL, actor_name VARCHAR(255) NOT NULL, action VARCHAR(50) NOT NULL, previous TEXT NULL, current TEXT NULL, occurred_at TIMESTAMP NOT NULL, FOREIGN KEY(actor_id) REFERENCES users(id)';
        foreach ([
            "CREATE TABLE purchase_requests ($id, owner_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NULL, suggested_merchant VARCHAR(255) NULL, details TEXT NULL, status VARCHAR(20) NOT NULL, revision INT UNSIGNED NOT NULL, supplier_id BIGINT UNSIGNED NULL, receiving_location_id BIGINT UNSIGNED NULL, planned_date DATE NULL, preparation_notes TEXT NULL, $dates, FOREIGN KEY(owner_id) REFERENCES users(id), FOREIGN KEY(supplier_id) REFERENCES catalog_references(id), FOREIGN KEY(receiving_location_id) REFERENCES storage_locations(id)) ENGINE=InnoDB",
            "CREATE TABLE purchase_request_lines ($id, purchase_request_id BIGINT UNSIGNED NOT NULL, item_id BIGINT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL, estimate DECIMAL(24,12) NULL, note TEXT NULL, UNIQUE KEY request_item(purchase_request_id,item_id), CONSTRAINT request_line_parent FOREIGN KEY(purchase_request_id) REFERENCES purchase_requests(id), FOREIGN KEY(item_id) REFERENCES items(id)) ENGINE=InnoDB",
            "CREATE TABLE purchase_request_notes ($id, purchase_request_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, actor_name VARCHAR(255) NOT NULL, body TEXT NOT NULL, occurred_at TIMESTAMP NOT NULL, FOREIGN KEY(purchase_request_id) REFERENCES purchase_requests(id), FOREIGN KEY(actor_id) REFERENCES users(id)) ENGINE=InnoDB",
            "CREATE TABLE purchase_request_activity ($id, purchase_request_id BIGINT UNSIGNED NOT NULL, $activity, FOREIGN KEY(purchase_request_id) REFERENCES purchase_requests(id)) ENGINE=InnoDB",
            "CREATE TABLE relocation_requests ($id, owner_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, details TEXT NULL, source_location_id BIGINT UNSIGNED NULL, destination_location_id BIGINT UNSIGNED NULL, status VARCHAR(20) NOT NULL, revision INT UNSIGNED NOT NULL, $dates, FOREIGN KEY(owner_id) REFERENCES users(id), FOREIGN KEY(source_location_id) REFERENCES storage_locations(id), FOREIGN KEY(destination_location_id) REFERENCES storage_locations(id)) ENGINE=InnoDB",
            "CREATE TABLE relocation_request_lines ($id, relocation_request_id BIGINT UNSIGNED NOT NULL, item_id BIGINT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL, fulfillment_quantity INT UNSIGNED NULL, UNIQUE KEY(relocation_request_id,item_id), FOREIGN KEY(relocation_request_id) REFERENCES relocation_requests(id), FOREIGN KEY(item_id) REFERENCES items(id)) ENGINE=InnoDB",
            "CREATE TABLE relocation_request_activity ($id, relocation_request_id BIGINT UNSIGNED NOT NULL, $activity, FOREIGN KEY(relocation_request_id) REFERENCES relocation_requests(id)) ENGINE=InnoDB",
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('migrations')->insert(['migration' => RequestSchema::MIGRATION, 'batch' => 6]);
    }

    private function rejected(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['requestsReady']);
        $this->artisan('daily-inventory:prepare')->assertExitCode(1);
    }

    public function test_absent_and_complete_contract_preserve_old_readiness_and_data(): void
    {
        $probe = app(BaselineProbe::class);
        $state = $probe->schemaState(DB::connection());
        $this->assertTrue($state['ready']);
        $this->assertTrue($state['canPrepare']);
        $this->assertFalse($state['requestsReady']);
        $this->fixture();
        $state = $probe->schemaState(DB::connection());
        foreach (['ready', 'canPrepare', 'authenticationReady', 'inventoryReady', 'catalogReady', 'dailyInventoryReady', 'requestsReady'] as $key) {
            $this->assertTrue($state[$key], $key);
        }
        $this->assertTrue($probe->inspect()['ready']);
        foreach (RequestSchema::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_orphan_unrecorded_and_partial_contracts_fail_closed(): void
    {
        $this->ownsFixture = true;
        DB::table('migrations')->insert(['migration' => RequestSchema::MIGRATION, 'batch' => 6]);
        $this->rejected();
        DB::table('migrations')->where('migration', RequestSchema::MIGRATION)->delete();
        $this->fixture();
        $withoutLedger = array_values(array_diff(DB::getSchemaBuilder()->getTableListing(null, false), ['migrations']));
        $this->assertSame(['compatible' => false, 'ready' => false], app(RequestSchema::class)->state(DB::connection(), $withoutLedger));
        DB::table('migrations')->where('migration', RequestSchema::MIGRATION)->delete();
        $this->rejected();
        DB::table('migrations')->insert(['migration' => RequestSchema::MIGRATION, 'batch' => 6]);
        DB::statement('DROP TABLE relocation_request_activity');
        $this->rejected();
    }

    public function test_wrong_precision_nullability_extra_columns_and_generation_fail_closed(): void
    {
        $this->fixture();
        foreach ([
            ['ALTER TABLE purchase_request_lines MODIFY estimate DECIMAL(24,3) NULL', 'ALTER TABLE purchase_request_lines MODIFY estimate DECIMAL(24,12) NULL'],
            ['ALTER TABLE relocation_requests MODIFY title VARCHAR(255) NULL', 'ALTER TABLE relocation_requests MODIFY title VARCHAR(255) NOT NULL'],
            ['ALTER TABLE relocation_request_lines MODIFY quantity INT NOT NULL', 'ALTER TABLE relocation_request_lines MODIFY quantity INT UNSIGNED NOT NULL'],
            ['ALTER TABLE purchase_requests ADD unexpected INT NULL', 'ALTER TABLE purchase_requests DROP COLUMN unexpected'],
            ['ALTER TABLE purchase_request_lines MODIFY quantity INT UNSIGNED GENERATED ALWAYS AS (1) STORED', 'ALTER TABLE purchase_request_lines MODIFY quantity INT UNSIGNED NOT NULL'],
        ] as [$break, $repair]) {
            DB::statement($break);
            $this->rejected();
            DB::statement($repair);
        }
    }

    public function test_uniqueness_missing_foreign_key_and_cascade_fail_closed(): void
    {
        $this->fixture();
        DB::statement('ALTER TABLE purchase_request_lines ADD INDEX request_parent(purchase_request_id)');
        DB::statement('ALTER TABLE purchase_request_lines DROP INDEX request_item');
        $this->rejected();
        DB::statement('ALTER TABLE purchase_request_lines ADD UNIQUE KEY request_item(purchase_request_id,item_id)');
        DB::statement('ALTER TABLE purchase_request_lines DROP FOREIGN KEY request_line_parent');
        $this->rejected();
        DB::statement('ALTER TABLE purchase_request_lines ADD CONSTRAINT request_line_parent FOREIGN KEY(purchase_request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE');
        $this->rejected();
        DB::statement('ALTER TABLE purchase_request_lines DROP FOREIGN KEY request_line_parent');
        DB::statement('ALTER TABLE purchase_request_lines ADD CONSTRAINT request_line_parent FOREIGN KEY(purchase_request_id) REFERENCES purchase_requests(id) ON UPDATE CASCADE');
        $this->rejected();
    }

    public function test_nontransactional_engine_is_rejected(): void
    {
        $this->fixture();
        $database = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn(DB::getSchemaBuilder());
        $database->shouldReceive('table')->with('migrations')->andReturn(DB::table('migrations'));
        $database->shouldReceive('getDatabaseName')->andReturn(DB::connection()->getDatabaseName());
        $database->shouldReceive('select')->andReturn([(object) ['name' => 'purchase_requests', 'engine' => 'MyISAM']]);
        $this->assertFalse(app(RequestSchema::class)->state($database, DB::getSchemaBuilder()->getTableListing(null, false))['compatible']);
    }

    public function test_missing_daily_prerequisite_and_unknown_table_fail_closed(): void
    {
        $this->fixture();
        $daily = Mockery::mock(DailyInventorySchema::class);
        $daily->shouldReceive('state')->andReturn(['compatible' => true, 'ready' => false]);
        $this->app->instance(DailyInventorySchema::class, $daily);
        $this->rejected();
        $this->app->instance(DailyInventorySchema::class, new DailyInventorySchema);
        DB::statement('CREATE TABLE unexpected_request_bridge_table (id INT) ENGINE=InnoDB');
        try {
            $this->rejected();
        } finally {
            DB::statement('DROP TABLE unexpected_request_bridge_table');
        }
    }
}
