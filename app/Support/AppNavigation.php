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
            ['href' => '/catalog', 'label' => 'Catalog', 'active' => request()->is('catalog', 'catalog/*'), 'children' => $catalog],
            ...array_map(fn ($name) => ['href' => '/'.strtolower($name), 'label' => $name, 'active' => request()->is(strtolower($name), strtolower($name).'/*'), 'children' => []], ['Purchases', 'Relocations', 'Events', 'Users']),
        ];
    }
}
