<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\RequestPreparation;
use App\Support\RequestSchema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestMigrationTest extends TestCase
{
    public function test_clean_forward_request_install_matches_frozen_contract_and_preserves_existing_data(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $probe = app(BaselineProbe::class);
        if ($probe->schemaState(DB::connection())['requestsReady']) {
            $this->markTestSkipped('Installed request schema preserved; initial clean forward rehearsal retained.');
        }
        $tables = DB::getSchemaBuilder()->getTableListing(null, false);
        $this->assertSame([], array_values(array_intersect(RequestSchema::TABLES, $tables)));
        $before = [];
        foreach (array_diff($tables, ['sessions', 'migrations']) as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->assertTrue(app(RequestPreparation::class)->prepare(false));
        $this->assertTrue($probe->schemaState(DB::connection())['requestsReady']);
        $this->assertSame(['compatible' => true, 'ready' => true], app(RequestSchema::class)->state(DB::connection(), DB::getSchemaBuilder()->getTableListing(null, false)));
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);
        }
        $this->assertSame(1, DB::table('migrations')->where('migration', RequestSchema::MIGRATION)->count());
        $this->assertTrue(app(RequestPreparation::class)->prepare(false));
        $this->assertSame(1, DB::table('migrations')->where('migration', RequestSchema::MIGRATION)->count());
        foreach (RequestSchema::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }
}
