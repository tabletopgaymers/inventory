<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\CatalogSchema;
use App\Support\InventoryPreparation;
use App\Support\InventorySchema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryMigrationTest extends TestCase
{
    public function test_clean_forward_migration_matches_retained_bridge_without_touching_authentication(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        if (array_intersect(CatalogSchema::TABLES, DB::getSchemaBuilder()->getTableListing(null, false)) !== []) {
            $this->markTestSkipped('Accepted pre-catalog base migration rehearsal retained at e81dca9; preserve additive references.');
        }
        foreach (InventorySchema::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), 'Do not rebuild stock tables containing data.');
        }
        $users = DB::table('users')->count();
        $roles = DB::table('user_roles')->count();
        $this->assertSame('91ce8071c9ac51ae282ad2dc449703c9efd476d5d28e449d19ee6ce88b1b4cc2', hash_file('sha256', app_path('Support/InventorySchema.php')));
        $migration = require database_path('migrations/'.InventorySchema::MIGRATION.'.php');
        try {
            // Only these verified empty disposable tables, never fresh/reset the database.
            $migration->down();
            DB::table('migrations')->where('migration', InventorySchema::MIGRATION)->delete();
            $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['authenticationReady']);
            $this->assertTrue(app(InventoryPreparation::class)->prepare(false));
            $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['inventoryReady']);
            $this->assertSame($users, DB::table('users')->count());
            $this->assertSame($roles, DB::table('user_roles')->count());
        } finally {
            // Restore forward if an assertion interrupts the empty-schema rehearsal.
            if (! DB::getSchemaBuilder()->hasTable('categories')) {
                app(InventoryPreparation::class)->prepare(false);
            }
        }
    }
}
