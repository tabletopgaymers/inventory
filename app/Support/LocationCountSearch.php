<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LocationCountSearch
{
    /**
     * Get an actor-bound count draft by UUID. Invalid or foreign tokens return 404.
     *
     * Scope: Domain-specific (Reconcile)
     * Task: Prepare location counts
     * @example app/Http/Controllers/LocationCountController.php
     */
    public function draft(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('location_counts.'.$token);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id, 404);

        return $draft;
    }

    /**
     * Find the last valid ordinary search token for this actor so a loaded list can return to it.
     *
     * Scope: Domain-specific (Reconcile)
     * Task: Prepare location counts
     * @example app/Http/Controllers/LocationCountController.php
     */
    public function lastSearch(Request $request): ?string
    {
        $token = $request->session()->get('location_counts_last_search');
        $draft = is_string($token) ? $request->session()->get('location_counts.'.$token) : null;

        return ($draft['actor'] ?? null) === (int) $request->user()->id ? $token : null;
    }

    /**
     * Capture the location criteria and item IDs in a new actor-bound draft with blank counts and review. This replaces the count-draft session map, retaining the last ordinary search only for a list-backed draft.
     *
     * Scope: Domain-specific (Reconcile)
     * Task: Prepare location counts
     * @example app/Http/Controllers/LocationCountController.php
     */
    public function newDraft(Request $request, array $criteria, array $items, ?array $list = null): string
    {
        $token = (string) Str::uuid();
        $drafts = [];
        $lastSearch = $list === null ? null : $this->lastSearch($request);
        if ($lastSearch !== null) {
            $drafts[$lastSearch] = $this->draft($request, $lastSearch);
        }
        $drafts[$token] = ['actor' => (int) $request->user()->id, 'criteria' => $criteria, 'items' => $items,
            'counts' => [], 'reviewed' => false, 'review_ids' => [], 'rationales' => []];
        if ($list === null) {
            $request->session()->put('location_counts_last_search', $token);
        } else {
            $drafts[$token]['list'] = $list;
        }
        $request->session()->put('location_counts', $drafts);

        return $token;
    }

    /**
     * Determine the default active zero-stock choice from whether the selected location is Central.
     *
     * @internal
     * @example app/Support/LocationCountSearch.php
     */
    public function zeroStockDefault(int $location): bool
    {
        return DB::table('storage_locations')->where('id', $location)->where('is_central', true)->exists();
    }

    /**
     * Validate the selected location and inventory search fields, and resolve the applicable active zero-stock choice for that location.
     *
     * Scope: Domain-specific (Reconcile)
     * Task: Prepare location counts
     * @example app/Http/Controllers/LocationCountController.php
     */
    public function criteria(Request $request): array
    {
        $location = $request->validate(['location_id' => ['required', 'integer', Rule::in(DB::table('storage_locations')->pluck('id')->all())]])['location_id'];
        $criteria = app(InventorySearch::class)->criteria($request);
        $data = $request->validate(['include_active_zero' => ['nullable', 'boolean'], 'zero_stock_location' => ['nullable', 'integer', 'min:1']]);
        if ($request->has('include_active_zero') && (! $request->has('zero_stock_location') || (int) $data['zero_stock_location'] === (int) $location)) {
            $includeZero = $request->boolean('include_active_zero');
        } else {
            $includeZero = $this->zeroStockDefault((int) $location);
        }

        return ['location_id' => (int) $location, 'search' => $criteria['search'], 'collections' => $criteria['collections'], 'include_active_zero' => $includeZero];
    }

    /**
     * Get the result rows for a count using the selected location and filters. Keep nonzero counts and include active zero-stock items when requested.
     *
     * Scope: Domain-specific (Reconcile)
     * Task: Prepare location counts
     * @example app/Http/Controllers/LocationCountController.php
     */
    public function rows(array $criteria): array
    {
        $search = $criteria + app(InventorySearch::class)->defaults();
        $search['include_inactive'] = true;
        $states = app(CatalogRecords::class)->records('items')->keyBy('id');
        $rows = app(InventorySearch::class)->results($search, naturalNames: true)['rows'];

        return array_values(array_filter($rows, fn ($row) => $row['values']['storage:'.$criteria['location_id']] !== 0
            || (($criteria['include_active_zero'] ?? $this->zeroStockDefault($criteria['location_id'])) && $states[$row['id']]->state === 'active')));
    }
}
