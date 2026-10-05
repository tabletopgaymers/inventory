<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100)->default('');
            $table->string('last_name', 100)->default('');
            $table->string('provider_email')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('contact_attested')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::create('external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('provider', 30);
            $table->uuid('tenant_id');
            $table->uuid('object_id');
            $table->unique(['provider', 'tenant_id', 'object_id']);
            $table->timestamps();
        });
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained();
            $table->string('role', 30);
            $table->primary(['user_id', 'role']);
        });
        Schema::create('authentication_bootstraps', function (Blueprint $table) {
            $table->string('key', 30)->primary();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamp('consumed_at')->nullable();
        });
        DB::table('authentication_bootstraps')->insert(['key' => 'initial-admin']);
        Schema::create('access_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('target_id')->constrained('users');
            $table->string('action', 30);
            $table->json('previous');
            $table->json('current');
            $table->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        throw new LogicException('Authentication data must be retained; use the documented compatible source recovery.');
    }
};
