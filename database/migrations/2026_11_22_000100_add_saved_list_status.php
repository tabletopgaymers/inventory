<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_lists', fn (Blueprint $table) => $table->string('status', 10)->default('active'));
    }

    public function down(): void
    {
        Schema::table('saved_lists', fn (Blueprint $table) => $table->dropColumn('status'));
    }
};
