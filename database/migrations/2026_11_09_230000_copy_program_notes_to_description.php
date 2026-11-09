<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('catalog_references')->where('kind', 'programs')
            ->whereNull('description')->whereNotNull('notes')
            ->update(['description' => DB::raw('notes')]);
    }

    public function down(): void
    {
        // Preserve program descriptions, including edits made after the backfill.
    }
};
