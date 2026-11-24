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
                'current' => request()->is('catalog/'.$kind, 'catalog/'.$kind.'/*')
                    || ($kind === 'categories' && request()->is('catalog'))
                    || ($kind === 'storage_locations' && request()->is('inventory/locations/*'))];
            if ($kind === 'items') {
                $catalog[] = ['href' => '/catalog/lists', 'label' => 'Lists', 'current' => request()->is('catalog/lists', 'catalog/lists/*')];
            }
        }

        return [
            ['href' => '/inventory', 'label' => 'Inventory', 'active' => request()->is('inventory', 'inventory/*') && ! request()->is('inventory/location-counts', 'inventory/location-counts/*'), 'children' => []],
            ['href' => '/relocation', 'label' => 'Relocation Request', 'active' => request()->is('relocation', 'relocation/*'), 'children' => []],
            ['href' => '/purchase', 'label' => 'Purchase Request', 'active' => request()->is('purchase', 'purchase/*'), 'children' => []],
            ['href' => '/inventory/location-counts', 'label' => 'Reconcile', 'active' => request()->is('inventory/location-counts', 'inventory/location-counts/*'), 'children' => []],
            ['href' => '/catalog', 'label' => 'Catalog', 'active' => request()->is('catalog', 'catalog/*'), 'children' => $catalog],
            ['href' => '/users', 'label' => 'Users', 'active' => request()->is('users', 'users/*'), 'children' => []],
            ['href' => '/events', 'label' => 'Events (dev)', 'active' => request()->is('events', 'events/*'), 'children' => [], 'footer' => true],
        ];
    }
}
