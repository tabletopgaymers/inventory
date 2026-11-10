<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('purpose_id')->nullable()->constrained('catalog_references')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('program_id')->nullable()->constrained('catalog_references')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        throw new LogicException('Preserve collection classifications through compatible source recovery.');
    }
};
