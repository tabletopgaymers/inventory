<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\EventPreparation;
use App\Support\EventSchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class EventSchemaTest extends TestCase
{
    private bool $owns = false;

    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertSame('tg_inventory_test', DB::connection()->getConfig('username'));
        $this->assertSame('127.0.0.1', DB::connection()->getConfig('host'));
        $this->assertSame(3306, (int) DB::connection()->getConfig('port'));
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        if (array_intersect(EventSchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false)) !== []) {
            $this->markTestSkipped('Installed event schema preserved; isolated absent-schema evidence retained separately.');
        }
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady']);
        $this->before = $this->snapshot();
    }

    private function snapshot(): array
    {
        $result = [];
        $schema = DB::getSchemaBuilder();
        foreach (array_diff($schema->getTableListing(null, false), EventSchema::TABLES) as $table) {
            $query = DB::table($table);
            if ($table === 'migrations') {
                $query->where('migration', '<>', EventSchema::MIGRATION);
            }
            $rows = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $query->get()->all());
            sort($rows);
            $result[$table] = [hash('sha256', implode("\n", $rows)), $schema->getColumns($table), $schema->getIndexes($table), $schema->getForeignKeys($table)];
        }
        ksort($result);

        return $result;
    }

    protected function tearDown(): void
    {
        if ($this->owns) {
            foreach (array_reverse(EventSchema::TABLES) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
            DB::table('migrations')->where('migration', EventSchema::MIGRATION)->delete();
            $this->assertSame($this->before, $this->snapshot(), 'All existing data/schema retained');
            $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady']);
        }
        parent::tearDown();
    }

    private function install(): void
    {
        $this->owns = true;
        $this->assertTrue(app(EventPreparation::class)->prepare());
    }

    private function refused(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['eventsReady']);
        $this->assertFalse(app(EventPreparation::class)->prepare());
    }

    public function test_absent_complete_repeat_and_exact_original_schema_data_preservation(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        $this->assertTrue($state['fulfillmentReady']);
        $this->assertFalse($state['eventsReady']);
        $this->install();
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['eventsReady']);
        $this->assertTrue(app(EventPreparation::class)->prepare());
        $this->assertSame(1, DB::table('migrations')->where('migration', EventSchema::MIGRATION)->count());
        foreach (EventSchema::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame($this->before, $this->snapshot());
    }

    public function test_orphan_partial_and_unrecorded_are_refused_without_repairs(): void
    {
        $this->owns = true;
        DB::table('migrations')->insert(['migration' => EventSchema::MIGRATION, 'batch' => 8]);
        $this->refused();
        DB::table('migrations')->where('migration', EventSchema::MIGRATION)->delete();
        $this->install();
        DB::table('migrations')->where('migration', EventSchema::MIGRATION)->delete();
        $this->refused();
        DB::table('migrations')->insert(['migration' => EventSchema::MIGRATION, 'batch' => 8]);
        DB::statement('DROP TABLE event_stock_entries');
        $this->refused();
    }

    public function test_wrong_cost_precision_blank_count_nullability_extra_column_and_unique_index_are_refused(): void
    {
        $this->install();
        foreach ([
            ['ALTER TABLE event_stock_entries MODIFY unit_cost DECIMAL(24,3) NOT NULL', 'ALTER TABLE event_stock_entries MODIFY unit_cost DECIMAL(24,12) NOT NULL'],
            ['ALTER TABLE event_lines MODIFY remaining INT UNSIGNED NOT NULL', 'ALTER TABLE event_lines MODIFY remaining INT UNSIGNED NULL'],
            ['ALTER TABLE event_workflows ADD unexpected INT NULL', 'ALTER TABLE event_workflows DROP COLUMN unexpected'],
            ['ALTER TABLE event_workflows ADD UNIQUE KEY unexpected(name)', 'ALTER TABLE event_workflows DROP INDEX unexpected'],
        ] as [$break, $repair]) {
            DB::statement($break);
            $this->refused();
            DB::statement($repair);
        }
    }

    public function test_missing_foreign_key_and_cascade_are_refused(): void
    {
        $this->install();
        DB::statement('ALTER TABLE event_lines DROP FOREIGN KEY event_lines_item_id_foreign');
        $this->refused();
        DB::statement('ALTER TABLE event_lines ADD CONSTRAINT event_lines_item_id_foreign FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE');
        $this->refused();
    }

    public function test_nontransactional_engine_and_unknown_tables_are_rejected(): void
    {
        $this->install();
        $database = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn(DB::getSchemaBuilder());
        $database->shouldReceive('table')->with('migrations')->andReturn(DB::table('migrations'));
        $database->shouldReceive('getDatabaseName')->andReturn('tg_inventory_test');
        $database->shouldReceive('select')->andReturn([(object) ['name' => 'event_workflows', 'engine' => 'MyISAM']]);
        $this->assertFalse(app(EventSchema::class)->state($database, DB::getSchemaBuilder()->getTableListing(null, false))['compatible']);
        DB::statement('CREATE TABLE dolphin_owned_unknown (id INT) ENGINE=InnoDB');
        try {
            $this->refused();
        } finally {
            DB::statement('DROP TABLE dolphin_owned_unknown');
        }
    }

    public function test_failed_ddl_partial_fixture_requires_inspection(): void
    {
        $this->owns = true;
        DB::statement('CREATE TABLE event_workflows (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
        $this->refused();
    }
}
