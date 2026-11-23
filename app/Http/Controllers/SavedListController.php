<?php

namespace App\Http\Controllers;

use App\Models\SavedList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavedListController
{
    public function index()
    {
        $lists = SavedList::with('owner')->orderBy('id')->get();
        $counts = DB::table('saved_list_items')->selectRaw('saved_list_id, COUNT(*) AS total')->groupBy('saved_list_id')->pluck('total', 'saved_list_id');

        return view('saved-lists.index', compact('lists', 'counts') + ['locations' => DB::table('storage_locations')->pluck('name', 'id')]);
    }

    public function create(Request $request)
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, int $id)
    {
        $list = SavedList::findOrFail($id);
        abort_unless($list->canEdit($request->user()), 403);

        return $this->form($request, $list);
    }

    private function form(Request $request, ?SavedList $list)
    {
        return response()->view('saved-lists.form', [
            'list' => $list, 'owner' => $list?->owner ?? $request->user(),
            'locations' => DB::table('storage_locations')->orderBy('name')->get(['id', 'name']),
            'itemIds' => $list?->itemIds() ?? [],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $id)
    {
        $list = SavedList::with('owner')->findOrFail($id);
        $items = DB::table('items')->join('collections', 'collections.id', '=', 'items.collection_id')
            ->join('categories', 'categories.id', '=', 'collections.category_id')
            ->join('saved_list_items', 'saved_list_items.item_id', '=', 'items.id')->where('saved_list_items.saved_list_id', $id)
            ->orderBy('categories.name')->orderBy('collections.name')->orderBy('items.name')->orderBy('items.id')
            ->get(['items.id', 'items.name', 'items.sku', 'collections.id as collection_id', 'collections.name as collection_name', 'categories.name as category_name']);

        return view('saved-lists.show', [
            'list' => $list, 'items' => $items, 'canEdit' => $list->canEdit($request->user()),
            'location' => $list->storage_location_id ? DB::table('storage_locations')->where('id', $list->storage_location_id)->value('name') : null,
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $list = $id ? SavedList::findOrFail($id) : new SavedList;
        abort_if($id && ! $list->canEdit($request->user()), 403);
        $request->validate(['item_ids_json' => ['required', 'json'], 'picker_ready' => ['required', 'accepted']]);
        $request->merge(['item_ids' => json_decode($request->input('item_ids_json'), true)]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'status' => ['required', \Illuminate\Validation\Rule::in(array_keys(SavedList::STATUSES))],
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

        return redirect('/catalog/lists/'.$list->id)->with('success', 'List saved.');
    }

    public function copy(Request $request, int $id)
    {
        $copy = DB::transaction(function () use ($request, $id) {
            $source = SavedList::lockForUpdate()->findOrFail($id);
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
