<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationCountSearch
{
    public function criteria(Request $request): array
    {
        $location = $request->validate(['location_id' => ['required', 'integer', Rule::in(DB::table('storage_locations')->pluck('id')->all())]])['location_id'];
        $criteria = app(InventorySearch::class)->criteria($request);

        return ['location_id' => (int) $location, 'search' => $criteria['search'], 'collections' => $criteria['collections'], 'include_inactive' => $criteria['include_inactive']];
    }

    public function rows(array $criteria): array
    {
        $search = $criteria + app(InventorySearch::class)->defaults();
        $search['include_inactive'] = true;
        $states = app(CatalogRecords::class)->records('items')->keyBy('id');
        $rows = app(InventorySearch::class)->results($search)['rows'];

        return array_values(array_filter($rows, fn ($row) => $criteria['include_inactive'] || $states[$row['id']]->state === 'active' || $row['values']['storage:'.$criteria['location_id']] !== 0));
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
            $saved[$key] = ['name' => $name, 'criteria' => array_intersect_key($criteria, array_flip(['location_id', 'search', 'collections', 'include_inactive']))];
            $preferences['saved_counts'] = $saved;
            DB::table('inventory_preferences')->updateOrInsert(['user_id' => $user], ['criteria' => json_encode($preferences, JSON_THROW_ON_ERROR), 'updated_at' => now('UTC')]);
        });
    }
}
