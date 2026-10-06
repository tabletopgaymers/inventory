<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\DailyInventoryPreparation;
use App\Support\DailyInventorySchema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyInventoryMigrationTest extends TestCase
{
    public function test_exact_clean_additive_install_preserves_base_data_and_matches_published_contract(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $probe = app(BaselineProbe::class);
        $this->assertTrue($probe->schemaState(DB::connection())['dailyInventoryReady']);
        foreach (DailyInventorySchema::TABLES as $table) {
            if (DB::table($table)->count() !== 0) {
                $this->markTestSkipped('Nonempty count/cost tables preserved; destructive rehearsal is only for owned empty test install.');
            }
        }
        $hashes = [];
        foreach (['users', 'items', 'inventory_balances', 'inventory_adjustments', 'inventory_adjustment_entries'] as $table) {
            $hashes[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
        }
        $migration = require base_path('database/migrations/'.DailyInventorySchema::MIGRATION.'.php');
        try {
            $migration->down();
            DB::table('migrations')->where('migration', DailyInventorySchema::MIGRATION)->delete();
            $this->assertTrue($probe->inspect()['ready']);
            $this->assertFalse($probe->schemaState(DB::connection())['dailyInventoryReady']);
            $this->assertTrue(app(DailyInventoryPreparation::class)->prepare(false));
            $this->assertTrue($probe->schemaState(DB::connection())['dailyInventoryReady']);
            $this->artisan('daily-inventory:check')->assertExitCode(0);
            foreach ($hashes as $table => $hash) {
                $this->assertSame($hash, hash('sha256', DB::table($table)->orderBy('id')->get()->toJson()), $table.' unchanged');
            }
            foreach (DailyInventorySchema::TABLES as $table) {
                $this->assertSame(0, DB::table($table)->count());
            }
            $this->assertSame('35fc557173613fd8eecdd52c08c1190ee02528b295738e2881b7eb772b38a5a7', hash_file('sha256', app_path('Support/DailyInventorySchema.php')));
        } finally {
            if (! $probe->schemaState(DB::connection())['dailyInventoryReady']) {
                app(DailyInventoryPreparation::class)->prepare(false);
            }
        }
    }
}
