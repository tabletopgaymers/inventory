<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_fulfillment_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->string('line_key', 50)->nullable();
            $table->string('description', 500)->default('');
            $table->string('group_label', 600)->default('Other items');
            $table->string('source_request_key', 50)->nullable();
        });
        DB::table('purchase_fulfillment_lines')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $item = DB::table('items')->where('id', $row->item_id)->first();
                $collection = $item ? DB::table('collections')->find($item->collection_id) : null;
                $category = $collection ? DB::table('categories')->find($collection->category_id) : null;
                DB::table('purchase_fulfillment_lines')->where('id', $row->id)->update([
                    'line_key' => (string) $row->item_id,
                    'description' => $item?->name ?? 'Catalog item '.$row->item_id,
                    'group_label' => $category && $collection ? $category->name.' : '.$collection->name : 'Catalog items',
                ]);
            }
        });
        Schema::table('purchase_fulfillment_lines', function (Blueprint $table) {
            $table->string('line_key', 50)->nullable(false)->change();
            $table->unique(['purchase_request_id', 'line_key'], 'purchase_fulfillment_line_key_unique');
        });
    }

    public function down(): void
    {
        throw new LogicException('Mixed purchase invoice history must be retained; use compatible forward recovery.');
    }
};
