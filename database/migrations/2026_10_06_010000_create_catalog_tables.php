<?php

use App\Support\CatalogSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detect retained name collisions before any nontransactional MariaDB DDL.
        // Do not rename existing records or leave a partial schema for this known case.
        foreach (['categories', 'collections', 'storage_locations'] as $kind) {
            $query = DB::table($kind)->select('name')->groupBy('name');
            if ($kind === 'collections') {
                $query->addSelect('category_id')->groupBy('category_id');
            }
            if ($query->havingRaw('COUNT(*) > 1')->exists()) {
                throw new LogicException('Retained catalog names need inspection before preparation.');
            }
        }
        Schema::create('catalog_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->unsignedBigInteger('record_id');
            $table->string('state', 10)->default('active');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'record_id']);
        });
        // This registry provides collation-aware race-safe name uniqueness without
        // altering the accepted base-table/index contract. IDs remain in base tables.
        Schema::create('catalog_names', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->string('name');
            $table->unsignedBigInteger('record_id');
            $table->unique(['kind', 'parent_id', 'name']);
            $table->unique(['kind', 'record_id']);
        });
        Schema::create('catalog_references', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('name');
            $table->string('state', 10)->default('active');
            foreach (['contact_name', 'email', 'phone', 'website'] as $field) {
                $table->string($field)->nullable();
            }
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });
        Schema::create('item_metadata', function (Blueprint $table) {
            $table->foreignId('item_id')->primary()->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('variety')->nullable();
            $table->foreignId('purpose_id')->nullable()->constrained('catalog_references')->restrictOnDelete()->restrictOnUpdate();
            $table->string('bundle_type')->nullable();
            $table->unsignedInteger('bundle_quantity')->nullable();
            foreach (['irs_fmv', 'in_person_ask', 'online_ask'] as $field) {
                $table->decimal($field, 24, 12)->nullable();
            }
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('item_programs', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('program_id')->constrained('catalog_references')->restrictOnDelete()->restrictOnUpdate();
            $table->primary(['item_id', 'program_id']);
        });
        Schema::create('inventory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });
        Schema::create('inventory_source_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('source_id')->constrained('inventory_sources')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('quantity');
            $table->timestamps();
            $table->unique(['item_id', 'source_id']);
        });
        Schema::create('inventory_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->text('criteria');
            $table->timestamps();
        });
        foreach (['categories', 'collections', 'storage_locations'] as $kind) {
            foreach (DB::table($kind)->orderBy('id')->get() as $record) {
                DB::table('catalog_names')->insert(['kind' => $kind, 'record_id' => $record->id, 'parent_id' => $kind === 'collections' ? $record->category_id : 0, 'name' => $record->name]);
            }
        }
        DB::table('inventory_sources')->insert([
            ['kind' => 'transit', 'name' => 'In Transit', 'active' => true],
            ['kind' => 'ordered', 'name' => 'Ordered', 'active' => true],
        ]);
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Retain catalog metadata; use compatible source recovery.');
        }
        foreach (array_reverse(CatalogSchema::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
