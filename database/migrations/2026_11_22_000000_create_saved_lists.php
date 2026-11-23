<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->restrictOnDelete();
            $table->timestamps(6);
        });
        Schema::create('saved_list_items', function (Blueprint $table) {
            $table->foreignId('saved_list_id')->constrained('saved_lists')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->primary(['saved_list_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_list_items');
        Schema::dropIfExists('saved_lists');
    }
};
