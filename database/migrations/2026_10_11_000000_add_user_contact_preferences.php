<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('personal_email')->nullable();
            $table->string('mobile_number', 100)->nullable();
            $table->text('mailing_address')->nullable();
            $table->string('display_density', 16)->default('standard');
        });
    }

    public function down(): void
    {
        throw new LogicException('User contact preferences must be retained; use compatible source recovery.');
    }
};
