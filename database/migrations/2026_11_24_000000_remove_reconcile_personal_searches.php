<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_preferences')
            ->whereRaw("JSON_CONTAINS_PATH(CASE WHEN JSON_VALID(criteria) THEN criteria END, 'one', '$.saved_counts') = 1")
            ->update(['criteria' => DB::raw("JSON_REMOVE(criteria, '$.saved_counts')")]);
    }

    public function down(): void
    {
        // Retired personal searches cannot be reconstructed.
    }
};
