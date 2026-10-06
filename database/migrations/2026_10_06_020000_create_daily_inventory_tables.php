<?php

use App\Support\DailyInventorySchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_operations', function (Blueprint $table) {
            $table->id();
            $table->char('operation_id', 36)->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('posted_at');
        });
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->foreignId('inventory_count_operation_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('inventory_adjustment_id')->unique()->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->primary(['inventory_count_operation_id', 'item_id']);
        });
        Schema::create('item_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->char('operation_id', 36)->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name');
            $table->string('item_name');
            $table->string('item_sku', 101);
            $table->decimal('before_cost', 24, 12);
            $table->decimal('after_cost', 24, 12);
            $table->string('rationale', 1000)->nullable();
            $table->timestamp('posted_at');
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Count and cost history may not be removed from a live database.');
        }
        foreach (array_reverse(DailyInventorySchema::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
