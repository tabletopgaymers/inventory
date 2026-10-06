<?php

use App\Support\RequestSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('title')->nullable();
            $table->string('suggested_merchant')->nullable();
            $table->text('details')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('revision');
            $table->foreignId('supplier_id')->nullable()->constrained('catalog_references')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('receiving_location_id')->nullable()->constrained('storage_locations')->restrictOnDelete()->restrictOnUpdate();
            $table->date('planned_date')->nullable();
            $table->text('preparation_notes')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('quantity');
            $table->decimal('estimate', 24, 12)->nullable();
            $table->text('note')->nullable();
            $table->unique(['purchase_request_id', 'item_id']);
        });
        Schema::create('purchase_request_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name');
            $table->text('body');
            $table->timestamp('occurred_at');
        });
        Schema::create('purchase_request_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $this->activity($table);
        });
        Schema::create('relocation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('title');
            $table->text('details')->nullable();
            $table->foreignId('source_location_id')->nullable()->constrained('storage_locations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('destination_location_id')->nullable()->constrained('storage_locations')->restrictOnDelete()->restrictOnUpdate();
            $table->string('status', 20);
            $table->unsignedInteger('revision');
            $table->timestamps();
        });
        Schema::create('relocation_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relocation_request_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('fulfillment_quantity')->nullable();
            $table->unique(['relocation_request_id', 'item_id']);
        });
        Schema::create('relocation_request_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relocation_request_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $this->activity($table);
        });
    }

    private function activity(Blueprint $table): void
    {
        $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
        $table->string('actor_name');
        $table->string('action', 50);
        $table->text('previous')->nullable();
        $table->text('current')->nullable();
        $table->timestamp('occurred_at');
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Requests and permanent notes/activity may not be removed from a live database.');
        }
        foreach (array_reverse(RequestSchema::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
