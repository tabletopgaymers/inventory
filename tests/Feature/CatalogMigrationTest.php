<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\CatalogPreparation;
use App\Support\CatalogSchema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogMigrationTest extends TestCase
{
    public function test_clean_additive_forward_migration_matches_retained_contract_and_preserves_base(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        foreach (array_diff(CatalogSchema::TABLES, ['inventory_sources']) as $table) {
            $this->assertSame(0, DB::table($table)->count(), 'Preserve any meaningful disposable metadata; do not rehearse over it.');
        }
        $this->assertSame(['ordered', 'transit'], DB::table('inventory_sources')->orderBy('kind')->pluck('kind')->all());
        $before = [];
        foreach (['users', 'user_roles', 'categories', 'collections', 'items', 'storage_locations', 'inventory_balances', 'inventory_adjustments', 'inventory_adjustment_entries'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $this->assertSame('99f56589ebd65f0e3a9118b61cbccb7aad6c48f569c4b04c4d054c080ce5fc06', hash_file('sha256', app_path('Support/CatalogSchema.php')));
        $migration = require database_path('migrations/'.CatalogSchema::MIGRATION.'.php');
        try {
            // Only author-installed empty disposable metadata; never touch base tables.
            $migration->down();
            DB::table('migrations')->where('migration', CatalogSchema::MIGRATION)->delete();
            $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['inventoryReady']);
            $this->assertTrue(app(CatalogPreparation::class)->prepare(false));
            $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady']);
            foreach ($before as $table => $count) {
                $this->assertSame($count, DB::table($table)->count());
            }
        } finally {
            if (! DB::getSchemaBuilder()->hasTable('catalog_metadata')) {
                app(CatalogPreparation::class)->prepare(false);
            }
        }
    }
}
