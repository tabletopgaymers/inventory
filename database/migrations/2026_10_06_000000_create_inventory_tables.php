<?php

use App\Support\InventorySchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('name');
            $table->string('sku_prefix', 50);
            $table->timestamps();
        });
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('name');
            $table->string('sku_suffix', 50);
            $table->string('sku', 101)->unique();
            $table->decimal('unit_cost', 24, 12)->default(0);
            $table->timestamps();
        });
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_central')->default(false);
            $table->timestamps();
        });
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->integer('quantity');
            $table->timestamps();
            $table->unique(['item_id', 'storage_location_id']);
        });
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->char('operation_id', 36)->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name');
            $table->string('item_name');
            $table->string('item_sku', 101);
            $table->timestamp('posted_at');
        });
        Schema::create('inventory_adjustment_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('location_name');
            $table->string('description', 300);
            $table->integer('before_quantity');
            $table->integer('after_quantity');
            $table->integer('quantity_change');
            $table->decimal('unit_cost', 24, 12);
            $table->string('rationale', 1000)->nullable();
            $table->unique(['inventory_adjustment_id', 'storage_location_id'], 'adjustment_location_unique');
        });
    }

    public function down(): void
    {
        // Reversible development migration; operational source recovery never invokes down.
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Retain operational stock/history; use compatible source recovery.');
        }
        foreach (array_reverse(InventorySchema::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
