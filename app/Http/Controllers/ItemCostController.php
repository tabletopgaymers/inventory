<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\DailyInventoryPosting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemCostController
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['dailyInventoryReady'], 503, 'Cost adjustment is not ready.');
    }

    private function access(Request $request): void
    {
        $this->ready();
        abort_unless($request->user()->hasRole('admin'), 403);
    }

    private function itemUrl(Request $request, int $item): string
    {
        return ($request->is('catalog/*') ? '/catalog/items/' : '/inventory/items/').$item;
    }

    private function saved(Request $request, int $item, string $message)
    {
        if ($request->expectsJson()) {
            $request->session()->flash('success', $message);
            return response()->json(['id' => $item]);
        }
        return redirect($this->itemUrl($request, $item))->with('success', $message);
    }

    public function edit(Request $request, int $item)
    {
        $this->access($request);
        $row = DB::table('items')->where('id', $item)->firstOrFail();
        $token = $request->session()->getOldInput('operation');
        $draft = is_string($token) ? $request->session()->get('cost_drafts.'.$token) : null;
        if (! is_array($draft) || $draft['actor'] !== (int) $request->user()->id || $draft['item'] !== $item || isset($draft['done'])) {
            $token = (string) Str::uuid();
            $request->session()->put('cost_drafts', [$token => ['actor' => (int) $request->user()->id, 'item' => $item]]);
        }

        return view('inventory.cost-edit', ['item' => $row, 'token' => $token, 'itemUrl' => $this->itemUrl($request, $item)]);
    }

    public function save(Request $request, int $item)
    {
        $this->access($request);
        if (is_string($request->input('unit_cost')) && preg_match('/\A\.\d{1,12}\z/D', $request->input('unit_cost'))) {
            $request->merge(['unit_cost' => '0'.$request->input('unit_cost')]);
        }
        $data = $request->validate(['operation' => ['required', 'uuid'], 'unit_cost' => ['required', 'string', 'regex:/\A\d{1,12}(?:\.\d{1,12})?\z/D'], 'rationale' => ['nullable', 'string', 'max:1000', 'not_regex:/[\x00-\x1F\x7F]/u']]);
        $draft = $request->session()->get('cost_drafts.'.$data['operation']);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id && $draft['item'] === $item, 404);
        if (isset($draft['done'])) {
            return $this->saved($request, $item, 'Cost already saved.');
        }
        try {
            $id = app(DailyInventoryPosting::class)->cost((int) $request->user()->id, $item, $data['operation'], $data['unit_cost'], $data['rationale'] ?? null);
        } catch (QueryException) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cost was not saved. Your inputs are retained; try again.'], 422);
            }
            return back()->withInput()->with('error', 'Cost was not saved. Your inputs are retained; try again.');
        }
        $draft['done'] = true;
        $draft['result'] = $id;
        $request->session()->put('cost_drafts.'.$data['operation'], $draft);

        return $this->saved($request, $item, $id ? 'Cost adjusted; quantities are unchanged.' : 'Cost unchanged; no history entry created.');
    }

    public function show(int $entry)
    {
        $this->ready();

        return view('inventory.cost-record', ['entry' => DB::table('item_cost_entries')->where('id', $entry)->firstOrFail()]);
    }
}
