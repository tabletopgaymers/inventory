<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogRecords
{
    public const KINDS = ['categories' => 'Categories', 'collections' => 'Collections', 'items' => 'Items', 'storage_locations' => 'Storage locations', 'suppliers' => 'Suppliers', 'purposes' => 'Purposes', 'programs' => 'Programs'];

    public function records(string $kind)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        if (in_array($kind, ['suppliers', 'purposes', 'programs'], true)) {
            return DB::table('catalog_references')->where('kind', $kind)->orderBy('name')->get();
        }
        $query = DB::table($kind.' as record')->leftJoin('catalog_metadata as metadata', function ($join) use ($kind) {
            $join->on('metadata.record_id', '=', 'record.id')->where('metadata.kind', $kind);
        })->select('record.*', DB::raw("COALESCE(metadata.state, 'active') as state"), 'metadata.description', 'metadata.notes');
        if ($kind === 'storage_locations') {
            $query->orderByDesc('record.is_central');
        }

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

    public function record(string $kind, int $id): object
    {
        return $this->records($kind)->firstWhere('id', $id) ?? abort(404);
    }

    public function activeAssociation(string $kind, int $id, ?int $existing = null): void
    {
        $table = in_array($kind, ['purposes', 'programs', 'suppliers'], true) ? 'catalog_references' : $kind;
        DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
        $record = $this->record($kind, $id);
        if ($record->state !== 'active' && $id !== $existing) {
            throw ValidationException::withMessages([$kind => 'Choose an active destination. Existing retired associations may be retained.']);
        }
    }

    public function authorize(int $actor): void
    {
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
        $user = DB::table('users')->where('id', $actor)->lockForUpdate()->first();
        abort_unless($user && $user->enabled && DB::table('user_roles')->where('user_id', $actor)->whereIn('role', ['admin', 'manager'])->exists(), 403);
    }
}
