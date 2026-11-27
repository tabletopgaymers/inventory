<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogRecords
{
    public const KINDS = ['categories' => 'Categories', 'collections' => 'Collections', 'items' => 'Items', 'storage_locations' => 'Locations', 'purposes' => 'Purposes', 'programs' => 'Programs', 'suppliers' => 'Suppliers'];
    public const SINGULAR = ['categories' => 'category', 'collections' => 'collection', 'items' => 'item', 'storage_locations' => 'location', 'suppliers' => 'supplier', 'purposes' => 'purpose', 'programs' => 'program'];

    /**
     * Get the detail URL prefix for a Catalog record kind. Storage locations use their Inventory detail route.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public static function viewBase(string $kind): string
    {
        return match ($kind) {
            'items' => '/catalog/items/', 'storage_locations' => '/inventory/locations/',
            default => '/catalog/'.$kind.'/',
        };
    }

    /**
     * Build the canonical detail URL from a known Catalog kind and record ID.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public static function viewUrl(string $kind, int $id): string
    {
        return self::viewBase($kind).$id;
    }

    /**
     * Build an item SKU from a collection prefix and item suffix. Use one separator after trimming trailing hyphens from the prefix.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public static function itemSku(string $prefix, string $suffix): string
    {
        return rtrim($prefix, '-').'-'.$suffix;
    }

    /**
     * Load Catalog records and their owned fields. Use natural name ordering for items when needed.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public function records(string $kind, bool $naturalNames = false)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        if (in_array($kind, ['suppliers', 'purposes', 'programs'], true)) {
            return DB::table('catalog_references')->where('kind', $kind)->orderBy('name')->get();
        }
        $query = DB::table($kind.' as record');
        if ($kind === 'storage_locations') {
            $query->orderByDesc('record.is_central');
        }

        if ($kind === 'items' && $naturalNames) $query->orderByRaw('NATURAL_SORT_KEY(record.name)');
        $rows = $query->orderBy('record.name')->orderBy('record.id')->get();
        if ($kind === 'categories') {
            $counts = DB::table('collections')->selectRaw('category_id, COUNT(*) AS children')->groupBy('category_id')->pluck('children', 'category_id');
            foreach ($rows as $row) {
                $row->child_count = (int) ($counts[$row->id] ?? 0);
            }
        }
        if ($kind === 'collections') {
            $counts = DB::table('items')->selectRaw('collection_id, COUNT(*) AS children')->groupBy('collection_id')->pluck('children', 'collection_id');
            $categories = DB::table('categories')->pluck('name', 'id');
            foreach ($rows as $row) {
                $row->child_count = (int) ($counts[$row->id] ?? 0);
                $row->category_name = $categories[$row->category_id];
            }
        }

        return $rows;
    }

    /**
     * Load one record from the supported kind and return 404 when it is absent.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public function record(string $kind, int $id): object
    {
        return $this->records($kind)->firstWhere('id', $id) ?? abort(404);
    }

    /**
     * Load items belonging to collections with the given Purpose or Program. Results are grouped in category and collection order with natural item names.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/SavedListController.php
     */
    public function itemsForClassification(string $kind, int $id)
    {
        $field = match ($kind) {
            'purposes' => 'collections.purpose_id',
            'programs' => 'collections.program_id',
        };

        return DB::table('items')
            ->join('collections', 'collections.id', '=', 'items.collection_id')
            ->join('categories', 'categories.id', '=', 'collections.category_id')
            ->where($field, $id)
            ->orderBy('categories.name')->orderBy('collections.name')->orderBy('collections.id')
            ->orderByRaw('NATURAL_SORT_KEY(items.name)')->orderBy('items.name')->orderBy('items.id')
            ->get(['items.id', 'items.name', 'items.sku', 'items.collection_id',
                'categories.name as category_name', 'collections.name as collection_name']);
    }

    /**
     * Resolve a collection’s linked Purpose and Program. Returns null for an absent reference.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/InventoryController.php
     */
    public function collectionClassification(object $collection): array
    {
        return [
            'purpose' => isset($collection->purpose_id) ? $this->record('purposes', (int) $collection->purpose_id) : null,
            'program' => isset($collection->program_id) ? $this->record('programs', (int) $collection->program_id) : null,
        ];
    }

    /**
     * Validate the classification selections on a collection being saved, preserving current associations where allowed.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public function validateCollectionClassification(array $data, ?object $existing): void
    {
        foreach (['purpose_id' => 'purposes', 'program_id' => 'programs'] as $field => $kind) {
            $id = $data[$field] ?? null;
            if ($id === null) {
                continue;
            }
            $reference = DB::table('catalog_references')->where(['id' => $id, 'kind' => $kind])->lockForUpdate()->first();
            if (! $reference || ($reference->state !== 'active' && (int) ($existing?->{$field} ?? 0) !== (int) $id)) {
                throw ValidationException::withMessages([$field => 'Choose an active '.self::SINGULAR[$kind].'.']);
            }
        }
    }

    /**
     * Lock a proposed association and validate whether it can be used. Pass the existing association when editing. Call inside the surrounding write transaction.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public function activeAssociation(string $kind, int $id, ?int $existing = null): void
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $reference = in_array($kind, ['purposes', 'programs', 'suppliers'], true);
        $owner = DB::table($reference ? 'catalog_references' : $kind)->where('id', $id);
        if ($reference) {
            $owner->where('kind', $kind);
        }
        $record = $owner->lockForUpdate()->firstOrFail();
        if ($record->state !== 'active' && $id !== $existing) {
            throw ValidationException::withMessages([$kind => 'Choose an active destination. Existing retired associations may be retained.']);
        }
    }

    /**
     * Recheck Catalog mutation access inside a write transaction using lock-protected account state. Pass the record kind and ID where the operation requires the record-specific rule.
     *
     * Scope: Domain-specific (Catalog)
     * Task: Work with catalog records
     * @example app/Http/Controllers/CatalogController.php
     */
    public function authorize(int $actor, ?string $kind = null, ?int $record = null): void
    {
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
        $user = DB::table('users')->where('id', $actor)->lockForUpdate()->first();
        $catalogManager = DB::table('user_roles')->where('user_id', $actor)->whereIn('role', ['admin', 'manager'])->exists();
        $supplierReviewer = $kind === 'suppliers' && $record
            && RequestValues::manages(\App\Models\User::findOrFail($actor), 'procurement')
            && DB::table('catalog_references')->where('id', $record)->where('kind', 'suppliers')->where('state', 'unverified')->lockForUpdate()->exists();
        abort_unless($user && $user->enabled && ($catalogManager || $supplierReviewer), 403);
    }
}
