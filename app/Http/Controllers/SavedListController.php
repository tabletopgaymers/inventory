<?php

namespace App\Http\Controllers;

use App\Models\SavedList;
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
        $context = $request->query('reconcile');
        if ($context === null) {
            return $this->form($request, null);
        }

        $draft = $this->reconcileDraft($request, $context);
        if (isset($draft['list']) && !$this->canReturnToListCount($request)) {
            return $this->form($request, null);
        }

        $name = isset($draft['list']) ? mb_substr($draft['list']['name'], 0, 248).' - COPY' : null;
        return $this->form($request, null, $draft['displayed_items'] ?? $draft['items'], $draft['criteria']['location_id'], $context, $name);
    }

    public function edit(Request $request, int $id)
    {
        $list = SavedList::findOrFail($id);
        abort_unless($list->canEdit($request->user()), 403);

        $context = $request->query('reconcile');
        if ($context !== null) {
            $this->reconcileDraft($request, $context, $id);
            if (!$this->canReturnToListCount($request)) {
                $context = null;
            }
        }

        return $this->form($request, $list, reconcileContext: $context);
    }

    private function reconcileDraft(Request $request, mixed $context, ?int $listId = null): array
    {
        abort_unless(is_string($context), 404);
        $draft = app(LocationCountSearch::class)->draft($request, $context);
        abort_if($listId !== null && (int) ($draft['list']['id'] ?? 0) !== $listId, 404);

        return $draft;
    }

    private function canReturnToListCount(Request $request): bool
    {
        return $request->user()->hasRole('manager') || $request->user()->hasRole('admin');
    }

    private function form(Request $request, ?SavedList $list, array $itemIds = [], ?int $locationId = null, ?string $reconcileContext = null, ?string $name = null)
    {
        return response()->view('saved-lists.form', [
            'list' => $list, 'owner' => $list?->owner ?? $request->user(),
            'locations' => DB::table('storage_locations')->orderBy('name')->get(['id', 'name']),
            'itemIds' => $list?->itemIds() ?? $itemIds,
            'locationId' => $list?->storage_location_id ?? $locationId,
            'reconcileContext' => $reconcileContext,
            'name' => $list?->name ?? $name,
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
        $reconcileContext = $request->query('reconcile');
        $draft = $reconcileContext === null ? null : $this->reconcileDraft($request, $reconcileContext, $id);
        $canCount = $this->canReturnToListCount($request);
        if (isset($draft['list']) && !$canCount) {
            $draft = $reconcileContext = null;
        }

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

        if ($draft !== null && $id === null && $canCount) {
            $location = $list->storage_location_id ?? $draft['criteria']['location_id'];
            $reconcileContext = app(LocationCountSearch::class)->newDraft($request,
                ['location_id' => $location === null ? null : (int) $location],
                array_map('intval', $data['item_ids']), ['id' => $list->id, 'name' => $list->name]);
        } elseif ($draft !== null && $id !== null) {
            $request->session()->put('location_counts.'.$reconcileContext.'.list.name', $list->name);
        }

        $destination = $reconcileContext === null
            ? '/catalog/lists/'.$list->id
            : '/inventory/location-counts?context='.$reconcileContext;

        return redirect($destination)->with('success', 'List saved.');
    }

    public function copy(Request $request, int $id)
    {
        $copy = DB::transaction(function () use ($request, $id) {
            $source = SavedList::lockForUpdate()->findOrFail($id);
            abort_unless($source->canView($request->user()), 403);
            $copy = new SavedList(['name' => mb_substr($source->name, 0, 245).' (copy)', 'description' => $source->description, 'storage_location_id' => $source->storage_location_id]);
            $copy->status = $source->status;
            $copy->owner_id = $request->user()->id;
            $copy->save();
            foreach (array_chunk($source->itemIds(), 500) as $chunk) {
                DB::table('saved_list_items')->insert(array_map(fn ($item) => ['saved_list_id' => $copy->id, 'item_id' => $item], $chunk));
            }

            return $copy;
        });

        return redirect('/catalog/lists/'.$copy->id.'/edit')->with('success', 'List copied. You own this copy.');
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
