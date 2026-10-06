<?php

namespace App\Http\Controllers;

class PlannedPageController extends Controller
{
    private const PAGES = [
        'catalog' => ['Catalog', 'Browse the categories, collections and items that organize the inventory.', 'Phase 5', ['Browse categories and collections', 'Find an item and open its inventory and history'], ['/inventory' => 'Inventory', '/inventory/item-history' => 'Item & History']],
        'inventory' => ['Inventory', 'Find items and see where supplies are available.', 'Phase 5', ['Search and filter inventory', 'Open an item or count supplies at a storage location'], ['/inventory/item-history' => 'Item & History', '/inventory/location-counts' => 'Location Counts', '/catalog' => 'Catalog']],
        'purchases' => ['Purchases', 'Request supplies that need to be purchased and follow their arrival.', 'Phases 6–7', ['Create and track purchase requests', 'Record ordering and receipt when those workflows are available'], ['/inventory' => 'Inventory', '/relocations' => 'Relocations']],
        'relocations' => ['Relocations', 'Request supplies from storage and follow their shipment and receipt.', 'Phases 6–7', ['Request supplies for another location', 'Track shipment and receipt when those workflows are available'], ['/inventory' => 'Inventory', '/purchases' => 'Purchases', '/events' => 'Events']],
        'events' => ['Events', 'Follow inventory sent to events and reconcile what returns.', 'Phase 8', ['View event inventory', 'Reconcile supplies after an event'], ['/relocations' => 'Relocations', '/inventory' => 'Inventory']],
        'item-history' => ['Item & History', 'Inspect an item’s quantities by location and its recorded inventory changes.', 'Phase 4 correction; remaining views in Phases 5 and 7–8', ['View storage quantities and immutable history', 'Review and save a storage correction with a permitted role'], ['/inventory' => 'Inventory', '/catalog' => 'Catalog', '/inventory/location-counts' => 'Location Counts']],
        'location-counts' => ['Location Counts', 'Count supplies at a storage location and review corrections.', 'Phase 5', ['Select items and print a count worksheet', 'Enter counts and review corrections before saving'], ['/inventory' => 'Inventory', '/inventory/item-history' => 'Item & History']],
    ];

    public function show(string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);
        [$title, $purpose, $phase, $actions, $related] = self::PAGES[$page];

        return view('planned', compact('title', 'purpose', 'phase', 'actions', 'related', 'page'));
    }
}
