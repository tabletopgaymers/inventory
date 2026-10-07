<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationCountSearch
{
    public function zeroStockDefault(int $location): bool
    {
        return DB::table('storage_locations')->where('id', $location)->where('is_central', true)->exists();
    }

    public function compatible(array $criteria): bool
    {
        // Old false already meant active zero-stock plus inactive non-zero stock.
        // Old true also included inactive zero-stock: the new rule cannot preserve it.
        return array_key_exists('include_active_zero', $criteria) || ! ($criteria['include_inactive'] ?? false);
    }

    public function normalize(array $criteria): array
    {
        if (! $this->compatible($criteria)) {
            throw ValidationException::withMessages(['include_active_zero' => 'This legacy saved search included inactive zero-stock items, which the new count rule excludes. Choose the new zero-stock option explicitly and save with overwrite if you want to replace these personal criteria. The saved search is unchanged.']);
        }
        if (! array_key_exists('include_active_zero', $criteria) && array_key_exists('include_inactive', $criteria)) {
            $criteria['include_active_zero'] = true;
        }
        unset($criteria['include_inactive']);

        return $criteria;
    }

    public function criteria(Request $request): array
    {
        $location = $request->validate(['location_id' => ['required', 'integer', Rule::in(DB::table('storage_locations')->pluck('id')->all())]])['location_id'];
        $criteria = app(InventorySearch::class)->criteria($request);
        $data = $request->validate(['include_active_zero' => ['nullable', 'boolean'], 'zero_stock_location' => ['nullable', 'integer', 'min:1']]);
        $legacy = $request->has('include_inactive') && ! $request->has('include_active_zero');
        if ($legacy) {
            $includeZero = $this->normalize(['include_inactive' => $request->boolean('include_inactive')])['include_active_zero'];
        } elseif ($request->has('include_active_zero') && (! $request->has('zero_stock_location') || (int) $data['zero_stock_location'] === (int) $location)) {
            $includeZero = $request->boolean('include_active_zero');
        } else {
            $includeZero = $this->zeroStockDefault((int) $location);
        }

        return ['location_id' => (int) $location, 'search' => $criteria['search'], 'collections' => $criteria['collections'], 'include_active_zero' => $includeZero];
    }

    public function rows(array $criteria): array
    {
        $criteria = $this->normalize($criteria);
        $search = $criteria + app(InventorySearch::class)->defaults();
        $search['include_inactive'] = true;
        $states = app(CatalogRecords::class)->records('items')->keyBy('id');
        $rows = app(InventorySearch::class)->results($search)['rows'];

        return array_values(array_filter($rows, fn ($row) => $row['values']['storage:'.$criteria['location_id']] !== 0
            || (($criteria['include_active_zero'] ?? $this->zeroStockDefault($criteria['location_id'])) && $states[$row['id']]->state === 'active')));
    }

    public function saved(int $user): array
    {
        $preferences = json_decode(DB::table('inventory_preferences')->where('user_id', $user)->value('criteria') ?? '{}', true);
        $saved = is_array($preferences) && is_array($preferences['saved_counts'] ?? null) ? $preferences['saved_counts'] : [];
        uasort($saved, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $saved;
    }

    public function save(int $user, string $name, array $criteria, bool $overwrite): void
    {
        $criteria = $this->normalize($criteria);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw ValidationException::withMessages(['name' => 'Enter a nonblank name up to 100 characters.']);
        }
        DB::transaction(function () use ($user, $name, $criteria, $overwrite) {
            DB::table('users')->where('id', $user)->lockForUpdate()->firstOrFail();
            $preferences = json_decode(DB::table('inventory_preferences')->where('user_id', $user)->value('criteria') ?? '{}', true);
            $preferences = is_array($preferences) ? $preferences : [];
            $saved = $this->saved($user);
            $key = mb_strtolower($name);
            if (isset($saved[$key]) && ! $overwrite) {
                throw ValidationException::withMessages(['name' => 'A personal search with that name exists. Confirm overwrite to replace its criteria.']);
            }
            // Whitelist criteria: never persist balances, results or count inputs.
            $saved[$key] = ['name' => $name, 'criteria' => array_intersect_key($criteria, array_flip(['location_id', 'search', 'collections', 'include_active_zero']))];
            $preferences['saved_counts'] = $saved;
            DB::table('inventory_preferences')->updateOrInsert(['user_id' => $user], ['criteria' => json_encode($preferences, JSON_THROW_ON_ERROR), 'updated_at' => now('UTC')]);
        });
    }
}
