<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_attested']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Removed TG email data requires a private backup; do not silently recreate empty confirmation fields.');
    }
};
