<?php

namespace App\Support;

use App\Models\User;

class AppNavigation
{
    public static function destinations(?User $user): array
    {
        // Existing read destinations are available to every enabled signed-in user.
        // Write controls continue to use their separate server authorization.
        if (! $user?->enabled) {
            return [];
        }
        $catalog = [];
        foreach (CatalogRecords::KINDS as $kind => $label) {
            $catalog[] = ['href' => '/catalog/'.$kind, 'label' => $label,
                'current' => request()->is('catalog/'.$kind, 'catalog/'.$kind.'/*') || ($kind === 'categories' && request()->is('catalog'))];
        }

        return [
            ['href' => '/inventory', 'label' => 'Inventory', 'active' => request()->is('inventory', 'inventory/*'), 'children' => [
                ['href' => '/inventory', 'label' => 'Browse', 'current' => request()->is('inventory', 'inventory/*') && ! request()->is('inventory/location-counts', 'inventory/location-counts/*')],
                ['href' => '/inventory/location-counts', 'label' => 'Location Counts', 'current' => request()->is('inventory/location-counts', 'inventory/location-counts/*')],
            ]],
            ['href' => '/relocation', 'label' => 'Relocations NEW', 'active' => request()->is('relocation', 'relocation/*'), 'children' => []],
            ['href' => '/events', 'label' => 'Events', 'active' => request()->is('events', 'events/*'), 'children' => []],
            ['href' => '/purchase', 'label' => 'Purchases NEW', 'active' => request()->is('purchase', 'purchase/*'), 'children' => []],
            ['href' => '/catalog', 'label' => 'Catalog', 'active' => request()->is('catalog', 'catalog/*'), 'children' => $catalog],
            ['href' => '/users', 'label' => 'Users', 'active' => request()->is('users', 'users/*'), 'children' => []],
            ['href' => '/relocations', 'label' => 'Relocations OLD', 'active' => request()->is('relocations', 'relocations/*'), 'children' => []],
            ['href' => '/purchases', 'label' => 'Purchases OLD', 'active' => request()->is('purchases', 'purchases/*'), 'children' => []],
        ];
    }
}
