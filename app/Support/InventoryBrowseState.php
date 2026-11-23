<?php

namespace App\Support;

use App\Models\SavedList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryBrowseState
{
    public function context(Request $request, mixed $token): array
    {
        abort_unless(is_string($token) && Str::isUuid($token), 404);
        $context = $request->session()->get('inventory_browse.'.$token);
        abort_unless(is_array($context) && $context['actor'] === (int) $request->user()->id, 404);

        return $context;
    }

    public function loadList(Request $request, SavedList $list, array $items, ?string $searchToken): string
    {
        $search = $searchToken === null ? null : $this->context($request, $searchToken);
        abort_if(isset($search['list']), 404);
        $criteria = app(InventorySearch::class)->defaults();
        $criteria['include_inactive'] = true;
        $token = (string) Str::uuid();
        $context = [
            'actor' => (int) $request->user()->id,
            'pending' => $search['pending'] ?? app(InventorySearch::class)->remembered((int) $request->user()->id),
            'submitted' => $criteria,
            'scroll' => 0,
            'previous_search' => $searchToken,
            'list' => $this->snapshot($list, $items),
        ];

        // Keep only the loaded list and the ordinary search that Reset can restore.
        $contexts = $searchToken === null ? [] : [$searchToken => $search];
        $request->session()->put('inventory_browse', $contexts + [$token => $context]);

        return $token;
    }

    public function refreshList(Request $request, string $token, SavedList $list, array $items): void
    {
        $context = $this->context($request, $token);
        abort_unless(($context['list']['id'] ?? null) === (int) $list->id, 404);
        $context['list'] = $this->snapshot($list, $items);
        unset($context['export'], $context['export_expires']);
        $request->session()->put('inventory_browse.'.$token, $context);
    }

    private function snapshot(SavedList $list, array $items): array
    {
        return ['id' => (int) $list->id, 'name' => $list->name, 'location_id' => $list->storage_location_id, 'items' => $items];
    }
}
