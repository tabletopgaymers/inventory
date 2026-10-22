<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();

            // Installation default only: preserve every existing location catalog.
            if (DB::table('storage_locations')->exists()) {
                return;
            }

            $now = now('UTC');
            $id = DB::table('storage_locations')->insertGetId([
                'name' => 'Central', 'is_central' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('catalog_names')->insert([
                'kind' => 'storage_locations', 'parent_id' => 0, 'record_id' => $id, 'name' => 'Central',
            ]);
            DB::table('catalog_metadata')->insert([
                'kind' => 'storage_locations', 'record_id' => $id, 'state' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        throw new LogicException('Retain the Central location and any associated inventory; use compatible source recovery.');
    }
};
