<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_material_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('line_key', 50);
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('description', 500);
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('estimated_unit_price', 24, 12)->nullable();
            $table->unique(['purchase_request_id', 'line_key']);
            $table->unique(['purchase_request_id', 'item_id']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Purchase request material must be retained; use compatible source recovery.');
    }
};
