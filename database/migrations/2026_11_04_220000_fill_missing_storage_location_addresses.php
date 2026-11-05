<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('storage_locations')->orderBy('id')->pluck('id') as $id) {
                $query = DB::table('catalog_metadata')->where('kind', 'storage_locations')->where('record_id', $id);
                $metadata = $query->lockForUpdate()->first();
                if ($metadata && trim((string)$metadata->mailing_address) !== '') continue;
                if ($metadata) $query->update(['mailing_address' => 'address missing', 'updated_at' => now('UTC')]);
                else DB::table('catalog_metadata')->insert([
                    'kind' => 'storage_locations', 'record_id' => $id, 'state' => 'active',
                    'mailing_address' => 'address missing', 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Data repair only: do not erase addresses, later corrections or location metadata.
    }
};
