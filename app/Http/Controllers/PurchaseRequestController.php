<?php

namespace App\Http\Controllers;

use App\Support\PurchaseRequests;
use App\Support\RequestCatalog;
use App\Support\RequestValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseRequestController
{
    use RequestPageSupport;

    public function index()
    {
        $this->ready();

        return view('requests.index', ['kind' => 'purchase', 'rows' => DB::table('purchase_requests')->leftJoin('users', 'users.id', '=', 'purchase_requests.owner_id')->select('purchase_requests.*', 'users.first_name', 'users.last_name')->orderByDesc('purchase_requests.updated_at')->orderByDesc('purchase_requests.id')->get()]);
    }

    public function create(Request $request)
    {
        $this->ready();
        $token = (string) Str::uuid();
        $request->session()->put('purchase_intake.'.$token, ['actor' => (int) $request->user()->id]);

        return view('requests.purchase-edit', ['row' => null, 'token' => $token]);
    }

    public function store(Request $request)
    {
        return $this->write($request, function () use ($request) {
            $request->validate(['token' => ['required', 'uuid']]);
            $key = 'purchase_intake.'.$request->input('token');
            $draft = $request->session()->get($key);
            abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id, 404);
            if (! isset($draft['result'])) {
                $draft['result'] = app(PurchaseRequests::class)->save((int) $request->user()->id, null, null, $request->all());
                $request->session()->put($key, $draft);
            }

            return redirect('/purchases/'.$draft['result'])->with('success', 'Draft saved.');
        });
    }

    public function show(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();

        return view('requests.purchase-show', ['row' => $row, 'owner' => DB::table('users')->where('id', $row->owner_id)->first(),
            'editable' => app(PurchaseRequests::class)->editable($request->user(), $row),
            'manager' => RequestValues::manages($request->user(), 'procurement'),
            'submitter' => RequestValues::manages($request->user(), 'procurement') || ((int) $row->owner_id === (int) $request->user()->id && RequestValues::elevated($request->user())),
            'notes' => DB::table('purchase_request_notes')->where('purchase_request_id', $id)->orderByDesc('id')->get(),
            'activity' => DB::table('purchase_request_activity')->where('purchase_request_id', $id)->orderByDesc('id')->get()]);
    }

    public function edit(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();
        abort_unless(app(PurchaseRequests::class)->editable($request->user(), $row), 403);

        return view('requests.purchase-edit', ['row' => $row, 'token' => null]);
    }

    public function save(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(PurchaseRequests::class)->save((int) $request->user()->id, $id, $request->input('revision'), $request->all());

            return redirect('/purchases/'.$id)->with('success', 'Request details saved.');
        });
    }

    public function transition(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            $data = $request->validate(['action' => ['required', 'in:submit,return,cancel']]);
            app(PurchaseRequests::class)->transition((int) $request->user()->id, $id, $request->input('revision'), $data['action']);

            return redirect('/purchases/'.$id)->with('success', 'Request status saved.');
        });
    }

    public function note(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(PurchaseRequests::class)->note((int) $request->user()->id, $id, $request->input('note'));

            return redirect('/purchases/'.$id)->with('success', 'Permanent note added.');
        });
    }

    public function preparation(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();

        return view('requests.purchase-preparation', app(RequestCatalog::class)->choices() + ['row' => $row,
            'canPrepare' => RequestValues::manages($request->user(), 'procurement') && in_array($row->status, ['Draft', 'Request'], true),
            'lines' => DB::table('purchase_request_lines')->where('purchase_request_id', $id)->orderBy('item_id')->get()->keyBy('item_id')->map(fn ($line) => ['quantity' => $line->quantity, 'estimate' => $line->estimate, 'note' => $line->note])->all()]);
    }

    public function prepare(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(PurchaseRequests::class)->prepare((int) $request->user()->id, $id, $request->input('revision'), $request->all());

            return redirect('/purchases/'.$id.'/preparation')->with('success', 'Pre-order preparation saved. No order or stock was created.');
        });
    }
}
