<?php

namespace App\Http\Controllers;

use App\Models\SavedList;
use App\Support\InventoryBrowseState;
use App\Support\LocationCountSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavedListController
{
    public function index(Request $request)
    {
        $query = SavedList::with('owner')->orderBy('id');
        if (! $request->user()->hasRole('admin')) {
            $query->where(fn ($query) => $query->where('owner_id', $request->user()->id)->orWhere('shared', true));
        }
        $lists = $query->get();
        $counts = DB::table('saved_list_items')->selectRaw('saved_list_id, COUNT(*) AS total')->groupBy('saved_list_id')->pluck('total', 'saved_list_id');

        return view('saved-lists.index', compact('lists', 'counts') + ['locations' => DB::table('storage_locations')->pluck('name', 'id')]);
    }

    public function create(Request $request)
    {
        $browse = $request->query('browse');
        if ($browse !== null) {
            $context = $this->inventoryDraft($request, $browse);
            abort_unless(isset($context['export']['rows']), 404);
            return $this->form($request, null, [
                'itemIds' => array_column($context['export']['rows'], 'id'),
                'locationId' => $context['list']['location_id'] ?? null,
                'name' => isset($context['list']) ? $this->copyName($context['list']['name']) : null,
            ], browseContext: $browse);
        }

        $context = $request->query('reconcile');
        if ($context === null) {
            return $this->form($request, null);
        }

        $draft = $this->reconcileDraft($request, $context);

        return $this->form($request, null, [
            'itemIds' => $draft['displayed_items'] ?? $draft['items'],
            'locationId' => $draft['criteria']['location_id'],
            'name' => isset($draft['list']) ? $this->copyName($draft['list']['name']) : null,
        ], reconcileContext: $context);
    }

    public function edit(Request $request, int $id)
    {
        $list = SavedList::findOrFail($id);
        abort_unless($list->canEdit($request->user()), 403);

        $browse = $request->query('browse');
        if ($browse !== null) {
            $this->inventoryDraft($request, $browse, $id);

            return $this->form($request, $list, browseContext: $browse);
        }

        $context = $request->query('reconcile');
        if ($context !== null) {
            $this->reconcileDraft($request, $context, $id);
        }

        return $this->form($request, $list, reconcileContext: $context);
    }

    private function inventoryDraft(Request $request, mixed $token, ?int $listId = null): array
    {
        abort_if($request->query('reconcile') !== null, 404);
        $context = app(InventoryBrowseState::class)->context($request, $token);
        abort_if($listId !== null && (int) ($context['list']['id'] ?? 0) !== $listId, 404);

        return $context;
    }

    private function reconcileDraft(Request $request, mixed $context, ?int $listId = null): array
    {
        abort_unless(is_string($context), 404);
        $draft = app(LocationCountSearch::class)->draft($request, $context);
        abort_if($listId !== null && (int) ($draft['list']['id'] ?? 0) !== $listId, 404);

        return $draft;
    }

    private function form(Request $request, ?SavedList $list, array $defaults = [], ?string $reconcileContext = null, ?string $browseContext = null)
    {
        return response()->view('saved-lists.form', [
            'list' => $list, 'owner' => $list?->owner ?? $request->user(),
            'locations' => DB::table('storage_locations')->orderBy('name')->get(['id', 'name']),
            'itemIds' => $list?->itemIds() ?? $defaults['itemIds'] ?? [],
            'locationId' => $list?->storage_location_id ?? $defaults['locationId'] ?? null,
            'reconcileContext' => $reconcileContext,
            'browseContext' => $browseContext,
            'name' => $list?->name ?? $defaults['name'] ?? null,
            'description' => $list?->description ?? $defaults['description'] ?? null,
            'status' => $list?->status ?? $defaults['status'] ?? 'active',
            'returnUrl' => $defaults['returnUrl'] ?? ($list ? '/catalog/lists/'.$list->id : '/catalog/lists'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $id)
    {
        $list = SavedList::with('owner')->findOrFail($id);
        abort_unless($list->canView($request->user()), 403);
        $items = DB::table('items')->join('collections', 'collections.id', '=', 'items.collection_id')
            ->join('categories', 'categories.id', '=', 'collections.category_id')
            ->join('saved_list_items', 'saved_list_items.item_id', '=', 'items.id')->where('saved_list_items.saved_list_id', $id)
            ->orderBy('categories.name')->orderBy('collections.name')->orderBy('items.name')->orderBy('items.id')
            ->get(['items.id', 'items.name', 'items.sku', 'collections.id as collection_id', 'collections.name as collection_name', 'categories.name as category_name']);
        $collectionTotals = DB::table('items')->whereIn('collection_id', $items->pluck('collection_id')->unique())
            ->selectRaw('collection_id, COUNT(*) AS total')->groupBy('collection_id')->pluck('total', 'collection_id');

        return view('saved-lists.show', [
            'list' => $list, 'items' => $items, 'canEdit' => $list->canEdit($request->user()),
            'collectionTotals' => $collectionTotals,
            'location' => $list->storage_location_id ? DB::table('storage_locations')->where('id', $list->storage_location_id)->value('name') : null,
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $list = $id ? SavedList::findOrFail($id) : new SavedList;
        abort_if($id && ! $list->canEdit($request->user()), 403);
        $browse = $request->query('browse');
        $inventoryDraft = $browse === null ? null : $this->inventoryDraft($request, $browse, $id);
        $reconcileContext = $request->query('reconcile');
        $draft = $reconcileContext === null ? null : $this->reconcileDraft($request, $reconcileContext, $id);

        $request->validate(['item_ids_json' => ['required', 'json'], 'picker_ready' => ['required', 'accepted']]);
        $request->merge(['item_ids' => json_decode($request->input('item_ids_json'), true)]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'status' => ['required', \Illuminate\Validation\Rule::in(array_keys(SavedList::STATUSES))],
            'shared' => ['sometimes', 'boolean'],
            'item_ids' => ['present', 'array', 'max:10000'], 'item_ids.*' => ['integer', 'distinct', 'exists:items,id'],
            'version' => $id ? ['required', 'string'] : ['nullable'],
        ]);
        $list = DB::transaction(function () use ($list, $id, $data, $request) {
            if ($id) {
                $list = SavedList::lockForUpdate()->findOrFail($id);
                abort_unless($list->canEdit($request->user()), 403);
                if ($list->getRawOriginal('updated_at') !== $data['version']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['version' => 'This list was updated by someone else. Reload the list before saving.']);
                }
            } else {
                $list->owner_id = $request->user()->id;
            }
            if (array_key_exists('shared', $data)) {
                $shared = (bool) $data['shared'];
                abort_if(! $request->user()->hasRole('admin') && $shared !== $list->shared, 403);
                $list->shared = $shared;
            }
            $list->fill(collect($data)->only(['name', 'description', 'storage_location_id', 'status'])->all());
            // Updating membership must also advance the parent timestamp.
            $list->updated_at = now();
            if (! $id) $list->created_at = $list->updated_at;
            $list->save();
            DB::table('saved_list_items')->where('saved_list_id', $list->id)->delete();
            foreach (array_chunk($data['item_ids'], 500) as $chunk) {
                DB::table('saved_list_items')->insert(array_map(fn ($item) => ['saved_list_id' => $list->id, 'item_id' => $item], $chunk));
            }

            return $list;
        });

        if ($inventoryDraft !== null) {
            $state = app(InventoryBrowseState::class);
            $items = array_map('intval', $data['item_ids']);
            if ($id === null) {
                $searchToken = isset($inventoryDraft['list']) ? $inventoryDraft['previous_search'] : $browse;
                $browse = $state->loadList($request, $list, $items, $searchToken);
            } else {
                $state->refreshList($request, $browse, $list, $items);
            }
        }

        if ($draft !== null) {
            $currentLocation = $draft['criteria']['location_id'];
            $location = $id === null
                ? ($list->storage_location_id ?? $currentLocation)
                : ($currentLocation ?? $list->storage_location_id);
            // The edited selection needs a fresh count draft so old entries cannot be applied to it.
            $reconcileContext = app(LocationCountSearch::class)->newDraft($request,
                ['location_id' => $location === null ? null : (int) $location],
                array_map('intval', $data['item_ids']), ['id' => $list->id, 'name' => $list->name]);
        }

        $destination = $reconcileContext === null
            ? '/catalog/lists/'.$list->id
            : '/inventory/location-counts?context='.$reconcileContext;
        if ($browse !== null) {
            $destination = '/inventory/results/'.$browse;
        }

        return redirect($destination)->with('success', 'List saved.');
    }

    public function copy(Request $request, int $id)
    {
        $defaults = DB::transaction(function () use ($request, $id) {
            $source = SavedList::lockForUpdate()->findOrFail($id);
            abort_unless($source->canView($request->user()), 403);

            return [
                'itemIds' => $source->itemIds(),
                'locationId' => $source->storage_location_id,
                'name' => $this->copyName($source->name),
                'description' => $source->description,
                'status' => $source->status,
                'returnUrl' => '/catalog/lists/'.$source->id,
            ];
        });

        return $this->form($request, null, $defaults);
    }

    private function copyName(string $name): string
    {
        return mb_substr($name, 0, 248).' (copy)';
    }

    public function delete(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $list = SavedList::lockForUpdate()->findOrFail($id);
            abort_unless($list->canEdit($request->user()), 403);
            $list->delete();
        });

        return redirect('/catalog/lists')->with('success', 'List deleted. Previously copied selections are unchanged.');
    }
}
