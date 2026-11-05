<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use Illuminate\Support\Facades\DB;

class BrowserMemoryDemoController
{
    public function index()
    {
        return response()->view('design.phase-a.browser-memory-demo')
            ->header('Cache-Control', 'private, no-store');
    }

    public function data()
    {
        $state = app(BaselineProbe::class)->runtimeState(DB::connection());
        abort_unless($state['inventoryReady'] && $state['catalogReady'], 503, 'Catalog and inventory are not ready.');

        $catalog = DB::transaction(fn () => [
            'categories' => DB::table('categories')->orderBy('id')->get(['id', 'name']),
            'collections' => DB::table('collections')->orderBy('id')->get(['id', 'name', 'sku_prefix', 'category_id']),
            'items' => DB::table('items')->orderBy('id')->get(['id', 'name', 'sku', 'collection_id']),
            'locations' => DB::table('storage_locations')->orderBy('id')->get(['id', 'name']),
            'inventory' => DB::table('inventory_balances')->orderBy('item_id')->orderBy('storage_location_id')
                ->get(['item_id', 'storage_location_id', 'quantity']),
            'central_id' => DB::table('storage_locations')->where('is_central', true)->value('id'),
        ]);

        return response()->json(compact('catalog'))
            ->header('Cache-Control', 'private, no-store');
    }
}
