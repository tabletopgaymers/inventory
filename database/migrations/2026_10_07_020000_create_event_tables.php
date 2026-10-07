<?php

use App\Support\EventSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_workflows', function (Blueprint $table) {
            $table->id();
            // New workflows get new sources. Never adopt or backfill legacy event stock.
            $table->foreignId('source_id')->nullable()->unique()->constrained('inventory_sources')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name');
            $table->string('status', 10)->default('Planning');
            $table->unsignedInteger('revision')->default(1);
            $table->longText('data');
        });
        Schema::create('event_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('event_workflows')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('brought')->default(0);
            $table->unsignedInteger('remaining')->nullable();
            // One editable count/allocation authority; original finalization lives in operations.
            $table->longText('data');
            $table->unique(['event_id', 'item_id']);
        });
        Schema::create('event_operations', function (Blueprint $table) {
            $table->id();
            $table->char('operation_id', 36)->unique();
            $table->foreignId('event_id')->constrained('event_workflows')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name');
            $table->string('action', 30);
            $table->longText('data');
            $table->timestamp('posted_at');
        });
        Schema::create('event_stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained('event_operations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('event_id')->constrained('event_workflows')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('leg', 40);
            $table->string('item_name');
            $table->string('item_sku', 101);
            $table->string('description', 600);
            $table->integer('quantity_change');
            $table->decimal('unit_cost', 24, 12);
            $table->unique(['operation_id', 'item_id', 'leg']);
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Event contributions and immutable finalization require forward source recovery.');
        }
        foreach (array_reverse(EventSchema::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
