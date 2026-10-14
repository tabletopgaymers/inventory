<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\InventorySearchControls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventorySearchGuideController
{
    private function ready(): void
    {
        $state = app(BaselineProbe::class)->schemaState(DB::connection());
        abort_unless($state['inventoryReady'] && $state['catalogReady'], 503, 'Inventory search is not ready.');
    }

    private function configuration(string $profile): array
    {
        abort_unless(in_array($profile, ['full', 'picker'], true), 404);
        if ($profile === 'full') return [];
        $central = DB::table('storage_locations')->where('is_central', true)->value('id');
        return ['filters' => ['collections', 'locations'], 'hiddenFilters' => ['locations'], 'activeOnly' => true,
            'defaults' => ['columns' => $central ? ['storage:'.$central] : []]];
    }

    public function index()
    {
        $this->ready();
        $service = app(InventorySearchControls::class);
        return view('design.phase-a.inventory-search', ['fullDefinition' => $service->definition($this->configuration('full')),
            'pickerDefinition' => $service->definition($this->configuration('picker'))]);
    }

    public function results(Request $request)
    {
        $this->ready();
        $profile = $request->validate(['profile' => ['required', 'in:full,picker']])['profile'];
        return response()->json(app(InventorySearchControls::class)->search($request, $this->configuration($profile)))
            ->header('Cache-Control', 'private, no-store');
    }
}
