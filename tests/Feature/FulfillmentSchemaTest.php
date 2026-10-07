<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\BisonAcceptedProbe;
use App\Support\FulfillmentPreparation;
use App\Support\FulfillmentSchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class FulfillmentSchemaTest extends TestCase
{
    private bool $owns = false;

    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        if (array_intersect(FulfillmentSchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false)) !== []) {
            $this->markTestSkipped('Installed fulfillment schema preserved; absent-schema rehearsal retained separately.');
        }
        foreach (array_diff(DB::getSchemaBuilder()->getTableListing(null, false), ['migrations', 'sessions']) as $table) {
            $this->before[$table] = DB::table($table)->get()->toJson();
        }
    }

    protected function tearDown(): void
    {
        if ($this->owns) {
            foreach (array_reverse(FulfillmentSchema::TABLES) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
            DB::table('migrations')->where('migration', FulfillmentSchema::MIGRATION)->delete();
            foreach ($this->before as $table => $rows) {
                $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);
            }
            $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['requestsReady']);
        }
        parent::tearDown();
    }

    private function install(): void
    {
        $this->owns = true;
        $this->assertTrue(app(FulfillmentPreparation::class)->prepare());
    }

    private function refused(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['fulfillmentReady']);
        $this->assertFalse(app(FulfillmentPreparation::class)->prepare());
    }

    public function test_absent_and_additive_complete_contract_and_repeat_install_preserve_data(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertTrue($state['requestsReady']);
        $this->assertFalse($state['fulfillmentReady']);
        $this->install();
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady']);
        $this->assertTrue(app(FulfillmentPreparation::class)->prepare());
        $this->assertSame(1, DB::table('migrations')->where('migration', FulfillmentSchema::MIGRATION)->count());
        foreach (FulfillmentSchema::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        // The real retained accepted probe refuses this schema; the bridge is required.
        $original = shell_exec('git show 83923271ba6511d1345277f64320e553e2c3eaa4:app/Support/BaselineProbe.php');
        $this->assertIsString($original);
        $original = str_replace('class BaselineProbe', 'class BisonAcceptedProbe', $original);
        if (! class_exists('App\\Support\\BisonAcceptedProbe', false)) {
            eval(substr($original, 5));
        }
        $this->assertFalse((new BisonAcceptedProbe)->schemaState(DB::connection())['ready']);
    }

    public function test_orphan_partial_and_unrecorded_fail_closed(): void
    {
        $this->owns = true;
        DB::table('migrations')->insert(['migration' => FulfillmentSchema::MIGRATION, 'batch' => 7]);
        $this->refused();
        DB::table('migrations')->where('migration', FulfillmentSchema::MIGRATION)->delete();
        $this->install();
        DB::table('migrations')->where('migration', FulfillmentSchema::MIGRATION)->delete();
        $this->refused();
        DB::table('migrations')->insert(['migration' => FulfillmentSchema::MIGRATION, 'batch' => 7]);
        DB::statement('DROP TABLE request_stock_entries');
        $this->refused();
    }

    public function test_wrong_precision_nullability_extra_columns_and_indexes_fail_closed(): void
    {
        $this->install();
        foreach ([
            ['ALTER TABLE purchase_fulfillment_lines MODIFY unit DECIMAL(24,3) NOT NULL', 'ALTER TABLE purchase_fulfillment_lines MODIFY unit DECIMAL(24,12) NOT NULL'],
            ['ALTER TABLE relocation_fulfillment_lines MODIFY sent INT UNSIGNED NOT NULL', 'ALTER TABLE relocation_fulfillment_lines MODIFY sent INT UNSIGNED NULL'],
            ['ALTER TABLE purchase_fulfillment ADD unexpected INT NULL', 'ALTER TABLE purchase_fulfillment DROP COLUMN unexpected'],
            ['ALTER TABLE request_stock_entries ADD UNIQUE KEY unexpected(description)', 'ALTER TABLE request_stock_entries DROP INDEX unexpected'],
        ] as [$break, $repair]) {
            DB::statement($break);
            $this->refused();
            DB::statement($repair);
        }
    }

    public function test_missing_foreign_key_and_cascade_fail_closed(): void
    {
        $this->install();
        DB::statement('ALTER TABLE purchase_fulfillment_lines DROP FOREIGN KEY purchase_fulfillment_lines_item_id_foreign');
        $this->refused();
        DB::statement('ALTER TABLE purchase_fulfillment_lines ADD CONSTRAINT purchase_fulfillment_lines_item_id_foreign FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE');
        $this->refused();
    }

    public function test_nontransactional_engine_and_unknown_tables_are_rejected(): void
    {
        $this->install();
        $database = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn(DB::getSchemaBuilder());
        $database->shouldReceive('table')->with('migrations')->andReturn(DB::table('migrations'));
        $database->shouldReceive('getDatabaseName')->andReturn('tg_inventory_test');
        $database->shouldReceive('select')->andReturn([(object) ['name' => 'request_stock_entries', 'engine' => 'MyISAM']]);
        $this->assertFalse(app(FulfillmentSchema::class)->state($database, DB::getSchemaBuilder()->getTableListing(null, false))['compatible']);
        DB::statement('CREATE TABLE bison_owned_unknown (id INT) ENGINE=InnoDB');
        try {
            $this->refused();
        } finally {
            DB::statement('DROP TABLE bison_owned_unknown');
        }
    }

    public function test_failed_ddl_leaves_partial_state_refused_until_owned_fixture_cleanup(): void
    {
        $this->owns = true;
        DB::statement('CREATE TABLE purchase_fulfillment (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
        $this->refused();
    }
}
