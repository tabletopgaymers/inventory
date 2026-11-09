<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_references', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->text('examples')->nullable();
        });

        DB::table('catalog_references')->where('kind', 'purposes')->whereNotNull('notes')
            ->update(['description' => DB::raw('notes')]);
    }

    public function down(): void
    {
        throw new LogicException('Preserve purpose descriptions and examples through compatible source recovery.');
    }
};
