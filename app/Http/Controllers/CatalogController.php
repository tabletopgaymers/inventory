<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\CatalogRecords;
use App\Support\StockNumbers;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady'], 503, 'Catalog is not ready.');
    }

    public function index(Request $request, string $kind = 'categories')
    {
        $this->ready();
        $service = app(CatalogRecords::class);
        $records = $service->records($kind);
        $category = null;
        $collection = null;
        if ($kind === 'collections' && $request->filled('category')) {
            $filter = $request->validate(['category' => ['required', 'integer', 'exists:categories,id']]);
            $category = $service->record('categories', (int) $filter['category']);
            $records = $records->where('category_id', (int) $filter['category'])->values();
        }
        if ($kind === 'items' && $request->filled('collection')) {
            $filter = $request->validate(['collection' => ['required', 'integer', 'exists:collections,id']]);
            $collection = $service->record('collections', (int) $filter['collection']);
            $category = $service->record('categories', (int) $collection->category_id);
            $records = $records->where('collection_id', (int) $filter['collection'])->values();
        }

        return view('catalog.index', ['kind' => $kind, 'kinds' => CatalogRecords::KINDS, 'records' => $records, 'category' => $category, 'collection' => $collection, 'categories' => $service->records('categories'), 'canManage' => $request->user()->hasRole('admin') || $request->user()->hasRole('manager')]);
    }

    public function archive(Request $request, string $kind, int $record)
    {
        $this->ready();
        abort_unless($request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 403);
        $row = app(CatalogRecords::class)->record($kind, $record);

        return view('catalog.archive', ['kind' => $kind, 'record' => $row, 'label' => CatalogRecords::KINDS[$kind]]);
    }

    public function form(Request $request, string $kind, ?int $record = null)
    {
        $this->ready();
        abort_unless($request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 403);
        abort_unless(isset(CatalogRecords::KINDS[$kind]), 404);
        $service = app(CatalogRecords::class);
        $row = $record ? $service->record($kind, $record) : null;
        $metadata = $kind === 'items' && $record ? DB::table('item_metadata')->where('item_id', $record)->first() : null;

        return view('catalog.form', ['kind' => $kind, 'label' => CatalogRecords::KINDS[$kind], 'record' => $row, 'metadata' => $metadata, 'categories' => $service->records('categories'), 'collections' => $service->records('collections'), 'purposes' => $service->records('purposes'), 'programs' => $service->records('programs'), 'selectedPrograms' => $record && $kind === 'items' ? DB::table('item_programs')->where('item_id', $record)->pluck('program_id')->all() : []]);
    }

    private function text(bool $required = false, int $max = 255): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[\x00-\x1F\x7F]/u'];
    }

    public function save(Request $request, string $kind, ?int $record = null)
    {
        $this->ready();
        abort_unless(isset(CatalogRecords::KINDS[$kind]), 404);
        abort_unless($request->user()->hasRole('admin') || $request->user()->hasRole('manager'), 403);
        $rules = ['name' => $this->text(true), 'description' => $this->text(false, 5000), 'notes' => $this->text(false, 5000)];
        if ($kind === 'collections') {
            $rules += ['category_id' => ['required', 'integer', 'min:1'], 'sku_prefix' => ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9_-]+\z/D']];
        }
        if ($kind === 'items') {
            $rules += ['collection_id' => ['required', 'integer', 'min:1'], 'sku_suffix' => ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9_-]+\z/D'], 'variety' => $this->text(), 'purpose_id' => ['nullable', 'integer', 'min:1'], 'programs' => ['nullable', 'array'], 'programs.*' => ['integer', 'distinct', 'min:1'], 'bundle_type' => $this->text(), 'bundle_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000000']];
            $rules['bundle_type'][] = 'required_with:bundle_quantity';
            foreach (['irs_fmv', 'in_person_ask', 'online_ask'] as $field) {
                $rules[$field] = ['nullable', 'string', 'regex:/\A\d{1,12}(?:\.\d{1,12})?\z/D'];
            }
        }
        if ($kind === 'suppliers') {
            $rules += ['contact_name' => $this->text(), 'email' => ['nullable', 'string', 'max:255', 'email'], 'phone' => $this->text(), 'website' => ['nullable', 'string', 'max:255', 'url:http,https'], 'address' => $this->text(false, 5000)];
        }
        $data = $request->validate($rules);
        try {
            $id = DB::transaction(function () use ($request, $kind, $record, $data) {
                $service = app(CatalogRecords::class);
                $service->authorize((int) $request->user()->id);
                $reference = in_array($kind, ['suppliers', 'purposes', 'programs'], true);
                $table = $reference ? 'catalog_references' : $kind;
                $old = $record ? DB::table($table)->where('id', $record)->lockForUpdate()->firstOrFail() : null;
                if ($reference && $old) {
                    abort_unless($old->kind === $kind, 404);
                }
                $values = ['name' => trim($data['name']), 'updated_at' => now('UTC')];
                if ($values['name'] === '') {
                    throw ValidationException::withMessages(['name' => 'Enter a nonblank name.']);
                }
                if ($kind === 'collections') {
                    DB::table('categories')->where('id', $data['category_id'])->lockForUpdate()->firstOrFail();
                    $service->activeAssociation('categories', (int) $data['category_id'], $old ? (int) $old->category_id : null);
                    if ($old && $old->sku_prefix !== $data['sku_prefix'] && DB::table('items')->where('collection_id', $old->id)->exists()) {
                        throw ValidationException::withMessages(['sku_prefix' => 'A populated collection prefix cannot change.']);
                    }
                    $values += ['category_id' => $data['category_id'], 'sku_prefix' => $data['sku_prefix']];
                }
                if ($kind === 'items') {
                    $collection = DB::table('collections')->where('id', $data['collection_id'])->lockForUpdate()->firstOrFail();
                    $service->activeAssociation('collections', (int) $collection->id, $old ? (int) $old->collection_id : null);
                    $values += ['collection_id' => $collection->id, 'sku_suffix' => $data['sku_suffix'], 'sku' => $collection->sku_prefix.$data['sku_suffix']];
                    if (! $old) {
                        $values['unit_cost'] = '0';
                    }
                }
                if ($reference) {
                    $values['kind'] = $kind;
                    foreach (['contact_name', 'email', 'phone', 'website', 'address', 'notes'] as $field) {
                        $values[$field] = $data[$field] ?? null;
                    }
                }
                if ($old) {
                    DB::table($table)->where('id', $record)->update($values);
                    $id = $record;
                } else {
                    $values['created_at'] = now('UTC');
                    $id = DB::table($table)->insertGetId($values);
                }
                if (! $reference) {
                    $existing = DB::table('catalog_metadata')->where(['kind' => $kind, 'record_id' => $id])->first();
                    DB::table('catalog_metadata')->updateOrInsert(['kind' => $kind, 'record_id' => $id], ['state' => $existing->state ?? 'active', 'description' => $data['description'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_at' => now('UTC')]);
                    if ($kind !== 'items') {
                        DB::table('catalog_names')->updateOrInsert(['kind' => $kind, 'record_id' => $id], ['parent_id' => $kind === 'collections' ? $values['category_id'] : 0, 'name' => $values['name']]);
                    }
                }
                if ($kind === 'items') {
                    $oldMeta = DB::table('item_metadata')->where('item_id', $id)->first();
                    if (! empty($data['purpose_id'])) {
                        $service->activeAssociation('purposes', (int) $data['purpose_id'], $oldMeta?->purpose_id);
                    }
                    $existingPrograms = DB::table('item_programs')->where('item_id', $id)->pluck('program_id')->all();
                    foreach ($data['programs'] ?? [] as $program) {
                        $service->activeAssociation('programs', (int) $program, in_array((int) $program, $existingPrograms) ? (int) $program : null);
                    }
                    $meta = ['updated_at' => now('UTC')];
                    foreach (['variety', 'purpose_id', 'bundle_type', 'bundle_quantity', 'notes', 'irs_fmv', 'in_person_ask', 'online_ask'] as $field) {
                        $meta[$field] = $data[$field] ?? null;
                        if (in_array($field, ['irs_fmv', 'in_person_ask', 'online_ask']) && $meta[$field] !== null) {
                            $meta[$field] = StockNumbers::cost($meta[$field]);
                        }
                    }
                    DB::table('item_metadata')->updateOrInsert(['item_id' => $id], $meta);
                    DB::table('item_programs')->where('item_id', $id)->delete();
                    foreach ($data['programs'] ?? [] as $program) {
                        DB::table('item_programs')->insert(['item_id' => $id, 'program_id' => $program]);
                    }
                }

                return $id;
            }, 3);
        } catch (QueryException $exception) {
            $message = (int) ($exception->errorInfo[1] ?? 0) === 1062 ? 'That name or SKU already exists. Choose a unique value.' : 'The record could not be saved. Your inputs are retained; try again.';

            return back()->withInput()->with('error', $message);
        }

        return redirect($kind === 'items' ? '/inventory/items/'.$id : '/catalog/'.$kind)->with('success', 'Record saved.');
    }

    public function lifecycle(Request $request, string $kind, int $record)
    {
        $this->ready();
        $data = $request->validate(['action' => ['required', 'in:activate,inactivate,archive,restore'], 'confirm' => ['required_if:action,archive', 'accepted_if:action,archive']]);
        DB::transaction(function () use ($request, $kind, $record, $data) {
            $service = app(CatalogRecords::class);
            $service->authorize((int) $request->user()->id);
            $row = $service->record($kind, $record);
            $reference = in_array($kind, ['suppliers', 'purposes', 'programs'], true);
            DB::table($reference ? 'catalog_references' : $kind)->where('id', $record)->lockForUpdate()->firstOrFail();
            $row = $service->record($kind, $record);
            if ($kind === 'storage_locations' && $row->is_central && $data['action'] !== 'activate') {
                throw ValidationException::withMessages(['action' => 'Central must remain the distinguished active storage location.']);
            }
            abort_if(($row->state === 'archived') !== ($data['action'] === 'restore'), 422, 'Restore archived records to Inactive before activating.');
            $state = match ($data['action']) {
                'activate' => 'active', 'archive' => 'archived', default => 'inactive'
            };
            if ($reference) {
                DB::table('catalog_references')->where('id', $record)->update(['state' => $state, 'updated_at' => now('UTC')]);
            } else {
                $existing = DB::table('catalog_metadata')->where(['kind' => $kind, 'record_id' => $record])->first();
                DB::table('catalog_metadata')->updateOrInsert(['kind' => $kind, 'record_id' => $record], ['state' => $state, 'description' => $existing->description ?? null, 'notes' => $existing->notes ?? null, 'updated_at' => now('UTC')]);
            }
        }, 3);

        return back()->with('success', 'Lifecycle updated; associated records and inventory retained.');
    }
}
