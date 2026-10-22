<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\PurchaseRequests;
use App\Support\InventorySearch;
use App\Support\PurchaseProgress;
use App\Support\RequestCatalog;
use App\Support\RequestValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController
{
    use RequestPageSupport;

    public function index()
    {
        $this->ready();

        return view('requests.index', ['kind' => 'purchase', 'fulfillmentReady' => app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 'rows' => DB::table('purchase_requests')->leftJoin('users', 'users.id', '=', 'purchase_requests.owner_id')->select('purchase_requests.*', 'users.first_name', 'users.last_name')->orderByDesc('purchase_requests.updated_at')->orderByDesc('purchase_requests.id')->get()]);
    }

    private function newWork(Request $request, ?object $row = null, bool $preparation = false): string
    {
        $token = (string) Str::uuid();
        $values = ['title' => $row?->title ?? '', 'suggested_merchant' => $row?->suggested_merchant ?? '', 'details' => $row?->details ?? '', 'lines' => []];
        if ($row) {
            foreach (DB::table('purchase_request_lines')->where('purchase_request_id', $row->id)->orderBy('item_id')->get() as $line) {
                $values['lines'][$line->item_id] = $preparation ? ['quantity' => (string) $line->quantity, 'estimate' => $line->estimate ?? '', 'note' => $line->note ?? ''] : ['quantity' => (string) $line->quantity];
            }
        }
        if ($preparation) {
            foreach (['supplier_id', 'receiving_location_id', 'planned_date', 'preparation_notes'] as $field) {
                $values[$field] = $row->$field ?? '';
            }
        }
        $draft = ['actor' => (int) $request->user()->id, 'id' => $row?->id, 'revision' => $row?->revision, 'preparation' => $preparation, 'values' => $values, 'criteria' => null, 'selection' => [], 'search_fields' => ['search' => '', 'collections' => []], 'reviewed' => false, 'version' => 0];
        $draft['saved_fields'] = PurchaseProgress::fields($values, $preparation ? 'planning-work' : 'work');
        $request->session()->put('purchase_work.'.$token, $draft);
        if ($row) {
            $request->session()->put('purchase_resume.'.$row->id.'.'.($preparation ? 'preparation' : 'request'), $token);
        }

        return $token;
    }

    private function draft(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('purchase_work.'.$token);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id && empty($draft['abandoned']), 404);

        return $draft;
    }

    private function allowed(Request $request, array $draft): ?object
    {
        abort_unless($request->user()->enabled, 403);
        $row = $draft['id'] ? DB::table('purchase_requests')->where('id', $draft['id'])->firstOrFail() : null;
        if ($draft['preparation']) {
            abort_unless($row && RequestValues::manages($request->user(), 'procurement') && in_array($row->status, ['Draft', 'Request'], true), 403);
        } elseif ($row) {
            abort_unless(app(PurchaseRequests::class)->editable($request->user(), $row), 403);
        }

        return $row;
    }

    private function workData(Request $request, array $draft): array
    {
        $row = $this->allowed($request, $draft);
        $ids = array_map('intval', array_keys($draft['values']['lines']));
        $catalog = app(RequestCatalog::class);

        return $catalog->choices(naturalNames: true) + ['draft' => $draft, 'row' => $row, 'rows' => $catalog->rows($ids, naturalNames: true),
            'results' => $draft['criteria'] === null ? [] : $catalog->selections($draft['criteria'], $ids, null, null, naturalNames: true),
            'submitter' => app(PurchaseRequests::class)->submitter($request->user(), $row)];
    }

    public function create(Request $request)
    {
        $this->ready();
        if ($request->has('work')) {
            $legacyToken = $request->query('work');
            abort_unless(is_string($legacyToken) && Str::isUuid($legacyToken), 404);
            $legacy = $request->session()->get('purchase_intake.'.$legacyToken);
            abort_unless(is_array($legacy) && $legacy['actor'] === (int) $request->user()->id, 404, 'This older intake has expired. Start a new purchase request.');
            if (isset($legacy['result'])) {
                return $this->edit($request, (int) $legacy['result']);
            }
            if (! isset($legacy['wizard'])) {
                $legacy['wizard'] = $this->newWork($request);
                $request->session()->put('purchase_intake.'.$legacyToken, $legacy);
            }

            return redirect('/purchases/work/'.$legacy['wizard'].'?legacy='.$legacyToken);
        }

        return redirect('/purchases/work/'.$this->newWork($request));
    }

    public function work(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);
        if (isset($draft['result'])) {
            return redirect('/purchases/'.$draft['result']);
        }
        $data = $this->workData($request, $draft);
        if ($request->expectsJson()) {
            return response()->json(['version' => $draft['version'], 'revision' => $draft['revision'], 'reviewed' => $draft['reviewed'], 'action_ack' => $draft['action_ack'] ?? '']);
        }

        $legacyToken = $request->query('legacy');
        $legacy = is_string($legacyToken) && Str::isUuid($legacyToken) ? $request->session()->get('purchase_intake.'.$legacyToken) : null;
        $legacyToken = is_array($legacy) && $legacy['actor'] === (int) $request->user()->id && ($legacy['wizard'] ?? null) === $token ? $legacyToken : null;

        return view($draft['preparation'] ? 'requests.purchase-preparation' : 'requests.purchase-edit', $data + ['token' => $token, 'legacyToken' => $legacyToken, 'step' => $request->query('step') === 'items' ? 'items' : 'request', 'canPrepare' => $draft['preparation'], 'lines' => $draft['values']['lines']]);
    }

    public function updateWork(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $draft = $this->draft($request, $token);
            abort_if(isset($draft['result']), 409);
            $row = $this->allowed($request, $draft);
            abort_unless((string) $request->input('work_version') === (string) $draft['version'], 409, 'This working page changed. Reload the current working page before applying older input.');
            $rules = ['action' => ['required', 'in:progress,request,items,review,search,show_all,reset,add,remove'], 'title' => ['nullable', 'string', 'max:255'], 'suggested_merchant' => ['nullable', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:5000'], 'lines' => ['nullable', 'array', 'max:1000'], 'lines.*' => ['array'], 'lines.*.quantity' => ['nullable', 'string', 'max:255'], 'selection' => ['nullable', 'array', 'max:1000'], 'selection.*' => ['nullable', 'string', 'max:255'], 'search' => ['nullable', 'string', 'max:255'], 'collections' => ['nullable', 'array', 'max:1000']];
            foreach (['supplier_id', 'receiving_location_id', 'planned_date', 'preparation_notes', 'lines.*.estimate', 'lines.*.note'] as $field) {
                $rules[$field] = $draft['preparation'] ? ['nullable', 'string', 'max:'.(str_ends_with($field, 'notes') || str_ends_with($field, '.note') ? 5000 : 255)] : ['prohibited'];
            }
            $data = $request->validate($rules);
            $names = $draft['preparation'] ? ['title', 'suggested_merchant', 'details', 'supplier_id', 'receiving_location_id', 'planned_date', 'preparation_notes'] : ['title', 'suggested_merchant', 'details'];
            foreach ($names as $field) {
                $draft['values'][$field] = $data[$field] ?? '';
            }
            $draft['values']['lines'] = $data['lines'] ?? [];
            $draft['selection'] = $data['selection'] ?? [];
            if (array_key_exists('search', $data)) {
                $draft['search_fields'] = ['search' => $data['search'] ?? '', 'collections' => $data['collections'] ?? []];
            }
            $hadReview = $draft['reviewed'];
            if (($data['action'] === 'progress') && $request->input('progress_step') === 'review') {
                abort_unless($hadReview && is_string($request->input('review_hash')) && hash_equals(PurchaseProgress::workHash($this->draft($request, $token)), $request->input('review_hash')) && PurchaseProgress::workHash($draft) === $request->input('review_hash'), 409, 'This review changed. Return to Items and review again.');
            }
            $draft['reviewed'] = false;
            $draft['version']++;
            $nonce = $request->input('action_nonce');
            $draft['action_ack'] = is_string($nonce) && Str::isUuid($nonce) ? $nonce : '';
            $request->session()->put('purchase_work.'.$token, $draft);
            try {
            $action = $data['action'];
            $pending = (bool) array_filter($draft['selection'], fn ($value) => $value !== null && $value !== '');
            if ($pending && in_array($action, ['progress', 'review', 'search', 'show_all', 'reset'], true)) {
                throw ValidationException::withMessages(['selection' => 'Use Add to purchase or clear pending quantities before saving, reviewing or changing search results.']);
            }
            if (in_array($action, ['search', 'show_all', 'reset'], true)) {
                $draft['criteria'] = $action === 'reset' ? null : app(InventorySearch::class)->criteria($request);
                if ($action === 'show_all') {
                    $draft['criteria']['search'] = '';
                }
                $draft['search_fields'] = $draft['criteria'] ?? ['search' => '', 'collections' => []];
            } elseif ($action === 'remove') {
                $item = RequestValues::id($request->input('remove'), 'remove');
                abort_unless($item && isset($draft['values']['lines'][$item]), 422);
                unset($draft['values']['lines'][$item]);
            } elseif ($action === 'add') {
                $allowed = array_column($this->workData($request, $draft)['results'], 'id');
                foreach ($draft['selection'] as $id => $quantity) {
                    if ($quantity === null || $quantity === '') {
                        continue;
                    }
                    $item = RequestValues::id($id, 'selection');
                    abort_unless($item && in_array($item, $allowed, true), 422);
                    $draft['values']['lines'][$item] = ['quantity' => (string) RequestValues::quantity($quantity, 'selection.'.$item)];
                    if ($draft['preparation']) {
                        $draft['values']['lines'][$item] += ['estimate' => '', 'note' => ''];
                    }
                    unset($draft['selection'][$id]);
                }
                abort_unless(count($draft['values']['lines']) <= 1000, 422);
            } elseif ($action === 'items') {
                app(PurchaseRequests::class)->fields($draft['values']);
            } elseif ($action === 'review') {
                abort_unless(! $draft['preparation'], 422);
                DB::transaction(function () use ($request, $draft) {
                    $actor = RequestValues::actor((int) $request->user()->id);
                    $locked = $draft['id'] ? DB::table('purchase_requests')->where('id', $draft['id'])->lockForUpdate()->firstOrFail() : null;
                    if ($locked) {
                        abort_unless(app(PurchaseRequests::class)->editable($actor, $locked), 403);
                        RequestValues::revision($draft['revision'], $locked);
                    }
                    app(PurchaseRequests::class)->fields($draft['values']);
                    app(PurchaseRequests::class)->requestLines($draft['values'], $locked);
                });
                $draft['reviewed'] = true;
            }
            $request->session()->put('purchase_work.'.$token, $draft);
            if ($action === 'progress') {
                $step = $request->input('progress_step');
                abort_unless(in_array($step, $draft['preparation'] ? ['preparation'] : ['request', 'items', 'review'], true) && ($step !== 'review' || $hadReview), 409);
                $draft = DB::transaction(function () use ($request, $draft, $step) {
                    $actor = RequestValues::actor((int) $request->user()->id);
                    $locked = $draft['id'] ? DB::table('purchase_requests')->where('id', $draft['id'])->lockForUpdate()->firstOrFail() : null;
                    if ($locked) {
                        RequestValues::revision($request->input('revision'), $locked);
                        RequestValues::revision($draft['revision'], $locked);
                    }
                    if ($draft['preparation']) {
                        app(PurchaseRequests::class)->save($actor->id, $draft['id'], $draft['revision'], $draft['values']);
                        $current = DB::table('purchase_requests')->where('id', $draft['id'])->firstOrFail();
                        app(PurchaseRequests::class)->prepare($actor->id, $draft['id'], $current->revision, $draft['values']);
                    } else {
                        $draft['id'] = app(PurchaseRequests::class)->saveWork($actor->id, $draft['id'], $draft['revision'], $draft['values']);
                    }
                    $saved = DB::table('purchase_requests')->where('id', $draft['id'])->firstOrFail();
                    foreach (array_keys($draft['values']) as $field) {
                        if ($field !== 'lines') {
                            $draft['values'][$field] = $saved->$field ?? '';
                        }
                    }
                    $draft['values']['lines'] = DB::table('purchase_request_lines')->where('purchase_request_id', $draft['id'])->orderBy('item_id')->get()->keyBy('item_id')->map(fn ($line) => $draft['preparation'] ? ['quantity' => (string) $line->quantity, 'estimate' => $line->estimate ?? '', 'note' => $line->note ?? ''] : ['quantity' => (string) $line->quantity])->all();
                    $draft['revision'] = (int) $saved->revision;
                    $draft['saved_fields'] = PurchaseProgress::fields($draft['values'], $draft['preparation'] ? 'planning-work' : 'work');
                    $draft['reviewed'] = $step === 'review';

                    return $draft;
                }, 3);
                $request->session()->put('purchase_work.'.$token, $draft);
                $request->session()->put('purchase_resume.'.$draft['id'].'.'.($draft['preparation'] ? 'preparation' : 'request'), $token);
                $message = 'Progress saved. No order or stock was created.';

                return $request->expectsJson() ? response()->json(['saved' => true, 'version' => $draft['version'], 'id' => $draft['id'], 'status' => DB::table('purchase_requests')->where('id', $draft['id'])->value('status'), 'revision' => $draft['revision'], 'fields' => $draft['saved_fields'], 'review_hash' => PurchaseProgress::workHash($draft), 'message' => $message]) : redirect('/purchases/work/'.$token.($step === 'review' ? '/review' : ($step === 'items' ? '?step=items' : '')))->with('success', $message);
            }

            return redirect('/purchases/work/'.$token.($action === 'review' ? '/review' : ($action === 'request' || $draft['preparation'] ? '' : '?step=items')));
            } catch (ValidationException $exception) {
                if ($request->expectsJson()) {
                    return response()->json(['saved' => false, 'retained' => true, 'version' => $draft['version'], 'message' => 'Progress was not saved. Correct the highlighted fields and retry.', 'errors' => $exception->errors()], 422);
                }
                throw $exception;
            } catch (\Illuminate\Database\QueryException|\PDOException $exception) {
                // A rolled-back Save leaves this already checked Review snapshot intact.
                // Retrying still checks its version, hashes, actor and saved revision.
                $draft['reviewed'] = $data['action'] === 'progress' && $hadReview && $request->input('progress_step') === 'review';
                $request->session()->put('purchase_work.'.$token, $draft);
                if ($request->expectsJson()) {
                    return response()->json(['saved' => false, 'retained' => true, 'version' => $draft['version'], 'message' => 'Progress could not be saved. Your working input is retained; retry when the connection is available.'], 503);
                }
                throw $exception;
            }
        });
    }

    public function reviewWork(Request $request, string $token)
    {
        $this->ready();
        $draft = $this->draft($request, $token);
        if (isset($draft['result'])) {
            return redirect('/purchases/'.$draft['result']);
        }
        if (! $draft['reviewed']) {
            return redirect('/purchases/work/'.$token.'?step=items');
        }
        abort_unless(! $draft['preparation'], 404);

        return view('requests.purchase-review', $this->workData($request, $draft) + ['token' => $token]);
    }

    public function finishWork(Request $request, string $token)
    {
        return $this->write($request, function () use ($request, $token) {
            $draft = $this->draft($request, $token);
            if (isset($draft['result'])) {
                return redirect('/purchases/'.$draft['result']);
            }
            $row = $this->allowed($request, $draft);
            $request->validate(['action' => ['required', 'in:submit,cancel']]);
            abort_unless(! $draft['preparation'] && (string) $request->input('work_version') === (string) $draft['version'], 409, 'The working request changed. Review it again.');
            if ($request->input('action') === 'cancel') {
                if ($row) {
                    abort_unless($row->status === 'Draft' || RequestValues::manages($request->user(), 'procurement'), 403);
                    RequestValues::revision($request->input('revision'), $row);
                    app(PurchaseRequests::class)->transition((int) $request->user()->id, $row->id, $draft['revision'], 'cancel');
                    $draft['result'] = $row->id;
                } else {
                    $draft['abandoned'] = true;
                }
            } else {
                abort_unless($draft['reviewed'] && is_string($request->input('review_hash')) && hash_equals(PurchaseProgress::workHash($draft), $request->input('review_hash')), 409, 'This review is older than the current working request. Review again.');
                if ($row) {
                    RequestValues::revision($request->input('revision'), $row);
                }
                $draft['result'] = app(PurchaseRequests::class)->saveWork((int) $request->user()->id, $draft['id'], $draft['revision'], $draft['values'], true);
            }
            $request->session()->put('purchase_work.'.$token, $draft);

            return redirect(isset($draft['result']) ? '/purchases/'.$draft['result'] : '/purchases')->with('success', $request->input('action') === 'cancel' ? 'Request cancelled. Unsaved changes were not saved.' : 'Request submitted. No order or stock was created.');
        });
    }

    // Retain old POST endpoints for existing pages without granting new fields.
    public function store(Request $request)
    {
        return $this->write($request, function () use ($request) {
            $request->validate(['token' => ['required', 'uuid']]);
            $key = 'purchase_intake.'.$request->input('token');
            $draft = $request->session()->get($key);
            abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id, 404);
            app(PurchaseRequests::class)->requestLines($request->all(), isset($draft['result']) ? DB::table('purchase_requests')->where('id', $draft['result'])->firstOrFail() : null);
            if ($request->input('action') === 'progress') {
                $result = DB::transaction(function () use ($request, $draft) {
                    $id = isset($draft['result'])
                        ? app(PurchaseRequests::class)->save((int) $request->user()->id, (int) $draft['result'], $request->input('revision'), $request->all())
                        : app(PurchaseRequests::class)->saveWork((int) $request->user()->id, null, null, $request->all());

                    return ['id' => $id, 'response' => $this->progressResponse($id, 'request')];
                }, 3);
                $draft['result'] = $result['id'];
                $request->session()->put($key, $draft);

                return $result['response'];
            }
            if (! isset($draft['result'])) {
                $draft['result'] = app(PurchaseRequests::class)->saveWork((int) $request->user()->id, null, null, $request->all());
                $request->session()->put($key, $draft);
            }

            return redirect('/purchases/'.$draft['result'])->with('success', 'Draft saved.');
        });
    }

    public function show(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();

        return view($row->status === 'Draft' ? 'requests.purchase-draft' : 'requests.purchase-show', ['row' => $row, 'owner' => DB::table('users')->where('id', $row->owner_id)->first(),
            'fulfillmentReady' => app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'],
            'editable' => app(PurchaseRequests::class)->editable($request->user(), $row),
            'manager' => RequestValues::manages($request->user(), 'procurement'),
            'submitter' => app(PurchaseRequests::class)->submitter($request->user(), $row),
            'requestRows' => app(RequestCatalog::class)->rows(DB::table('purchase_request_lines')->where('purchase_request_id', $id)->pluck('item_id')->map(fn ($value) => (int) $value)->all(), naturalNames: true),
            'requestLines' => DB::table('purchase_request_lines')->where('purchase_request_id', $id)->pluck('quantity', 'item_id')->all(),
            'notes' => DB::table('purchase_request_notes')->where('purchase_request_id', $id)->orderByDesc('id')->get(),
            'activity' => DB::table('purchase_request_activity')->where('purchase_request_id', $id)->orderByDesc('id')->get()]);
    }

    public function edit(Request $request, int $id)
    {
        $this->ready();
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();
        abort_unless(app(PurchaseRequests::class)->editable($request->user(), $row), 403);
        $resume = $request->session()->get('purchase_resume.'.$id.'.request');
        if (is_string($resume) && is_array($request->session()->get('purchase_work.'.$resume))) {
            $draft = $this->draft($request, $resume);
            if (! isset($draft['result']) && $draft['id'] === $id) {
                return redirect('/purchases/work/'.$resume.($request->query('step') === 'items' ? '?step=items' : ''));
            }
        }

        return redirect('/purchases/work/'.$this->newWork($request, $row).($request->query('step') === 'items' ? '?step=items' : ''));
    }

    private function progressResponse(int $id, string $context)
    {
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();

        return response()->json(['saved' => true, 'id' => $id, 'status' => $row->status, 'revision' => (int) $row->revision, 'fields' => PurchaseProgress::fields((array) $row, $context), 'message' => 'Progress saved.']);
    }

    public function save(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(PurchaseRequests::class)->requestLines($request->all(), DB::table('purchase_requests')->where('id', $id)->firstOrFail());
            $method = $request->has('lines') ? 'saveWork' : 'save';
            if ($request->input('action') === 'progress') {
                return DB::transaction(function () use ($request, $id, $method) {
                    app(PurchaseRequests::class)->{$method}((int) $request->user()->id, $id, $request->input('revision'), $request->all());

                    return $this->progressResponse($id, 'request');
                }, 3);
            }
            app(PurchaseRequests::class)->{$method}((int) $request->user()->id, $id, $request->input('revision'), $request->all());

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
        $canPrepare = RequestValues::manages($request->user(), 'procurement') && in_array($row->status, ['Draft', 'Request'], true);
        if ($canPrepare) {
            $resume = $request->session()->get('purchase_resume.'.$id.'.preparation');
            if (is_string($resume) && is_array($request->session()->get('purchase_work.'.$resume))) {
                $draft = $this->draft($request, $resume);
                if (! isset($draft['result'])) {
                    return redirect('/purchases/work/'.$resume);
                }
            }

            return redirect('/purchases/work/'.$this->newWork($request, $row, true));
        }
        $lines = DB::table('purchase_request_lines')->where('purchase_request_id', $id)->get()->keyBy('item_id')->map(fn ($line) => (array) $line)->all();

        return view('requests.purchase-preparation', app(RequestCatalog::class)->choices(naturalNames: true) + ['row' => $row, 'canPrepare' => false, 'token' => null, 'draft' => ['values' => (array) $row + ['lines' => $lines], 'revision' => $row->revision, 'version' => 0, 'criteria' => null, 'selection' => []], 'rows' => app(RequestCatalog::class)->rows(array_map('intval', array_keys($lines)), naturalNames: true), 'results' => []]);
    }

    public function prepare(Request $request, int $id)
    {
        return $this->write($request, function () use ($request, $id) {
            app(PurchaseRequests::class)->prepare((int) $request->user()->id, $id, $request->input('revision'), $request->all());

            return redirect('/purchases/'.$id.'/preparation')->with('success', 'Pre-order preparation saved. No order or stock was created.');
        });
    }
}
