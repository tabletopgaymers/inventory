<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\CatalogSchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    private bool $ownsFixture = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        if (array_intersect(CatalogSchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false)) !== []) {
            $this->markTestSkipped('Pre-catalog bridge rehearsal requires empty disposable catalog schema; installed records are preserved.');
        }
        $this->assertFalse(DB::table('migrations')->where('migration', CatalogSchema::MIGRATION)->exists());
    }

    protected function tearDown(): void
    {
        if ($this->ownsFixture) {
            foreach (array_reverse(CatalogSchema::TABLES) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
            DB::table('migrations')->where('migration', CatalogSchema::MIGRATION)->delete();
        }
        parent::tearDown();
    }

    public function test_accepted_phase_four_and_complete_catalog_are_read_only_compatible(): void
    {
        $probe = app(BaselineProbe::class);
        $this->assertTrue($probe->inspect()['ready']);
        $this->assertTrue($probe->schemaState(DB::connection())['inventoryReady']);
        $this->assertFalse($probe->schemaState(DB::connection())['catalogReady']);
        $before = DB::table('inventory_adjustments')->count();
        $this->fixture();
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        $state = $probe->schemaState(DB::connection());
        $this->assertTrue($state['ready']);
        $this->assertTrue($state['inventoryReady']);
        $this->assertTrue($state['catalogReady']);
        $this->assertTrue($state['canPrepare']);
        $this->assertSame($before, DB::table('inventory_adjustments')->count());
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/\A\s*(select|show)\b/i', $query);
        }
    }

    public function test_orphan_unrecorded_partial_and_arbitrary_tables_fail_closed(): void
    {
        $this->ownsFixture = true;
        DB::table('migrations')->insert(['migration' => CatalogSchema::MIGRATION, 'batch' => 4]);
        $this->rejected();
        DB::table('migrations')->where('migration', CatalogSchema::MIGRATION)->delete();
        $this->fixture();
        DB::table('migrations')->where('migration', CatalogSchema::MIGRATION)->delete();
        $this->rejected();
        DB::table('migrations')->insert(['migration' => CatalogSchema::MIGRATION, 'batch' => 4]);
        DB::statement('DROP TABLE inventory_preferences');
        $this->rejected();
    }

    public function test_unknown_table_still_rejects_complete_contract(): void
    {
        $this->fixture();
        DB::statement('CREATE TABLE unexpected_catalog_table (id INT) ENGINE=InnoDB');
        try {
            $state = app(BaselineProbe::class)->schemaState(DB::connection());
            $this->assertFalse($state['ready']);
            $this->assertFalse($state['canPrepare']);
            $this->artisan('baseline:prepare')->assertExitCode(1);
            $this->artisan('inventory:prepare')->assertExitCode(1);
        } finally {
            DB::statement('DROP TABLE unexpected_catalog_table');
        }
    }

    public function test_wrong_money_quantity_extra_column_and_missing_unique_fail_closed(): void
    {
        $this->fixture();
        DB::statement('ALTER TABLE item_metadata MODIFY irs_fmv DECIMAL(24,3) NULL');
        $this->rejected();
        DB::statement('ALTER TABLE item_metadata MODIFY irs_fmv DECIMAL(24,12) NULL');
        DB::statement('ALTER TABLE inventory_source_balances MODIFY quantity INT UNSIGNED NOT NULL');
        $this->rejected();
        DB::statement('ALTER TABLE inventory_source_balances MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE catalog_metadata ADD unexpected INT NULL');
        $this->rejected();
        DB::statement('ALTER TABLE catalog_metadata DROP unexpected');
        DB::statement('ALTER TABLE catalog_names DROP INDEX catalog_name_unique');
        $this->rejected();
    }

    public function test_cascading_foreign_and_nontransactional_tables_fail_closed(): void
    {
        $this->fixture();
        DB::statement('ALTER TABLE item_programs DROP FOREIGN KEY program_reference');
        DB::statement('ALTER TABLE item_programs ADD CONSTRAINT program_reference FOREIGN KEY (program_id) REFERENCES catalog_references(id) ON DELETE CASCADE');
        $this->rejected();
        DB::statement('ALTER TABLE item_programs DROP FOREIGN KEY program_reference');
        DB::statement('ALTER TABLE item_programs ADD CONSTRAINT program_reference FOREIGN KEY (program_id) REFERENCES catalog_references(id)');
        DB::statement('ALTER TABLE catalog_metadata ENGINE=MyISAM');
        $this->rejected();
    }

    public function test_orphan_catalog_record_before_authentication_rejects_preparation(): void
    {
        $database = Mockery::mock();
        $schema = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $schema->shouldReceive('getTableListing')->with(null, false)->andReturn(['migrations']);
        $ledger = Mockery::mock();
        $database->shouldReceive('table')->with('migrations')->andReturn($ledger);
        foreach (['2026_10_05_000000_create_sessions_table' => false, '2026_10_06_000000_create_inventory_tables' => false, CatalogSchema::MIGRATION => true] as $migration => $exists) {
            $query = Mockery::mock();
            $ledger->shouldReceive('where')->with('migration', $migration)->andReturn($query);
            $query->shouldReceive('exists')->andReturn($exists);
        }
        $state = app(BaselineProbe::class)->schemaState($database);
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['catalogReady']);
    }

    private function rejected(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['catalogReady']);
        $this->artisan('baseline:prepare')->assertExitCode(1);
        $this->artisan('inventory:prepare')->assertExitCode(1);
    }

    private function fixture(): void
    {
        $this->ownsFixture = true;
        // Independent disposable SQL: bridge publishes no application migration.
        $id = 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $timestamps = 'created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL';
        $sql = [
            "CREATE TABLE catalog_metadata ($id, kind VARCHAR(30) NOT NULL, record_id BIGINT UNSIGNED NOT NULL, state VARCHAR(10) NOT NULL, description TEXT NULL, notes TEXT NULL, $timestamps, UNIQUE KEY(kind,record_id)) ENGINE=InnoDB",
            "CREATE TABLE catalog_names ($id, kind VARCHAR(30) NOT NULL, parent_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, record_id BIGINT UNSIGNED NOT NULL, UNIQUE KEY catalog_name_unique(kind,parent_id,name), UNIQUE KEY(kind,record_id)) ENGINE=InnoDB",
            "CREATE TABLE catalog_references ($id, kind VARCHAR(30) NOT NULL, name VARCHAR(255) NOT NULL, state VARCHAR(10) NOT NULL, contact_name VARCHAR(255) NULL, email VARCHAR(255) NULL, phone VARCHAR(255) NULL, website VARCHAR(255) NULL, address TEXT NULL, notes TEXT NULL, $timestamps, UNIQUE KEY(kind,name)) ENGINE=InnoDB",
            "CREATE TABLE item_metadata (item_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, variety VARCHAR(255) NULL, purpose_id BIGINT UNSIGNED NULL, bundle_type VARCHAR(255) NULL, bundle_quantity INT UNSIGNED NULL, irs_fmv DECIMAL(24,12) NULL, in_person_ask DECIMAL(24,12) NULL, online_ask DECIMAL(24,12) NULL, notes TEXT NULL, $timestamps, FOREIGN KEY(item_id) REFERENCES items(id), FOREIGN KEY(purpose_id) REFERENCES catalog_references(id)) ENGINE=InnoDB",
            'CREATE TABLE item_programs (item_id BIGINT UNSIGNED NOT NULL, program_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(item_id,program_id), FOREIGN KEY(item_id) REFERENCES items(id), CONSTRAINT program_reference FOREIGN KEY(program_id) REFERENCES catalog_references(id)) ENGINE=InnoDB',
            "CREATE TABLE inventory_sources ($id, kind VARCHAR(10) NOT NULL, name VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL, $timestamps, UNIQUE KEY(kind,name)) ENGINE=InnoDB",
            "CREATE TABLE inventory_source_balances ($id, item_id BIGINT UNSIGNED NOT NULL, source_id BIGINT UNSIGNED NOT NULL, quantity INT NOT NULL, $timestamps, UNIQUE KEY(item_id,source_id), FOREIGN KEY(item_id) REFERENCES items(id), FOREIGN KEY(source_id) REFERENCES inventory_sources(id)) ENGINE=InnoDB",
            "CREATE TABLE inventory_preferences (user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, criteria TEXT NOT NULL, $timestamps, FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB",
        ];
        foreach ($sql as $statement) {
            DB::statement($statement);
        }
        DB::table('migrations')->insert(['migration' => CatalogSchema::MIGRATION, 'batch' => 4]);
    }
}
