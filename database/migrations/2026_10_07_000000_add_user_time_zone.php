<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('time_zone', 100)->nullable();
        });
    }

    public function down(): void
    {
        throw new LogicException('Profile preferences must be retained; use compatible source recovery.');
    }
};
