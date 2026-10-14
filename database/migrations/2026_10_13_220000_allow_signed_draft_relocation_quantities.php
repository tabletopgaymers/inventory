<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relocation_request_lines', function (Blueprint $table) {
            $table->bigInteger('quantity')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('relocation_request_lines')->whereNull('quantity')->orWhere('quantity', '<', 0)->orWhere('quantity', '>', 4294967295)->exists()) {
            throw new RuntimeException('Requested quantities cannot be restored to unsigned required values without losing data.');
        }
        Schema::table('relocation_request_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable(false)->change();
        });
    }
};
