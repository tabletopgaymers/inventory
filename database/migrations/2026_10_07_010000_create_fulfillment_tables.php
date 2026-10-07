<?php

use App\Support\FulfillmentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase', 'relocation'] as $kind) {
            Schema::create($kind.'_fulfillment', function (Blueprint $table) use ($kind) {
                $table->id();
                $table->foreignId($kind.'_request_id')->unique()->constrained($kind.'_requests')->restrictOnDelete()->restrictOnUpdate();
                $table->longText('data');
            });
            Schema::create($kind.'_fulfillment_lines', function (Blueprint $table) use ($kind) {
                $table->id();
                $table->foreignId($kind.'_request_id')->constrained($kind.'_requests')->restrictOnDelete()->restrictOnUpdate();
                $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
                if ($kind === 'purchase') {
                    $table->unsignedInteger('quantity');
                    $table->decimal('unit', 24, 12);
                    $table->unsignedBigInteger('cost');
                    $table->unsignedBigInteger('fee');
                } else {
                    $table->unsignedInteger('sent')->nullable();
                    $table->unsignedInteger('received')->nullable();
                }
                $table->unique([$kind.'_request_id', 'item_id'], $kind.'_fulfillment_item_unique');
            });
        }
        Schema::create('request_stock_entries', function (Blueprint $table) {
            $table->id();
            $table->char('operation_id', 36);
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('relocation_request_id')->nullable()->constrained('relocation_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name');
            $table->string('item_name');
            $table->string('item_sku', 101);
            $table->string('entry_kind', 30);
            $table->string('description', 600);
            $table->integer('quantity_change');
            $table->decimal('unit_cost', 24, 12);
            $table->text('explanation')->nullable();
            $table->timestamp('posted_at');
            $table->unique(['operation_id', 'item_id', 'entry_kind'], 'request_stock_operation_item_kind_unique');
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Fulfillment and immutable stock history require forward source recovery.');
        }
        foreach (array_reverse(FulfillmentSchema::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
