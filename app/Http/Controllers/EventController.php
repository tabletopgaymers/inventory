<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use App\Support\EventValues;
use App\Support\EventWorkflow;
use App\Support\RequestCatalog;
use App\Support\RequestValues;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventController
{
    private function ready(Request $request, bool $write = false): void
    {
        abort_unless($request->user()?->enabled, 403);
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['eventsReady'], 503, 'Events need guarded preparation.');
        if ($write) {
            abort_unless(RequestValues::manages($request->user(), 'manager'), 403);
        }
    }

    private function choices(?int $self): array
    {
        $choices = app(RequestCatalog::class)->choices();
        $choices['destinations'] = [];
        foreach ($choices['locations']->where('state', 'active') as $location) {
            $choices['destinations']['storage:'.$location->id] = $location->name;
        }
        foreach (DB::table('event_workflows')->where('status', 'Active')->when($self !== null, fn ($q) => $q->where('id', '<>', $self))->orderBy('name')->get() as $event) {
            $choices['destinations']['event:'.$event->id] = $event->name.' (active event)';
        }

        return $choices;
    }

    private function draft(Request $request, string $token, ?int $id, string $mode): array
    {
        abort_unless(Str::isUuid($token), 404);
        $draft = $request->session()->get('event_work.'.$token);
        abort_unless(is_array($draft) && $draft['actor'] === (int) $request->user()->id && $draft['id'] === $id && $draft['mode'] === $mode, 404);

        return $draft;
    }

    private function base(?int $id, string $mode): string
    {
        return $id === null ? '/events/new' : '/events/'.$id.'/work/'.$mode;
    }

    public function index(Request $request)
    {
        $this->ready($request);

        return view('events.index', ['events' => DB::table('event_workflows')->orderByRaw("FIELD(status, 'Active', 'Planning', 'Finalized')")->orderBy('name')->get(), 'canManage' => RequestValues::manages($request->user(), 'manager')]);
    }

    public function show(Request $request, int $id)
    {
        $this->ready($request);
        $row = DB::table('event_workflows')->where('id', $id)->firstOrFail();
        $data = app(EventWorkflow::class)->data($id);
        $history = DB::table('event_operations')->where('event_id', $id)->orderByDesc('id')->get();
        $incoming = DB::table('event_stock_entries')->join('event_operations', 'event_operations.id', '=', 'event_stock_entries.operation_id')
            ->where('event_stock_entries.event_id', $id)->where('event_operations.event_id', '<>', $id)->select('event_stock_entries.*', 'event_operations.posted_at', 'event_operations.actor_name', 'event_operations.event_id as contributor_event_id')->orderByDesc('event_stock_entries.id')->get();

        return view('events.show', $this->choices($id) + ['row' => $row, 'data' => $data, 'history' => $history, 'incoming' => $incoming, 'canManage' => RequestValues::manages($request->user(), 'manager')]);
    }

    public function form(Request $request, ?int $id = null, string $mode = 'plan')
    {
        $this->ready($request, true);
        abort_unless(in_array($mode, ['plan', 'activate', 'delivery', 'counts', 'correction'], true), 404);
        $row = $id === null ? null : DB::table('event_workflows')->where('id', $id)->firstOrFail();
        abort_unless($row ? $row->status === ($mode === 'plan' || $mode === 'activate' ? 'Planning' : ($mode === 'correction' ? 'Finalized' : 'Active')) : $mode === 'plan', 409);
        $token = $request->query('work');
        if (is_string($token)) {
            $draft = $this->draft($request, $token, $id, $mode);
        } else {
            $token = (string) Str::uuid();
            $values = $id === null ? ['name' => '', 'venue' => '', 'notes' => '', 'initial_source_id' => '', 'default_destination' => '', 'supplies' => []] : app(EventWorkflow::class)->data($id);
            if ($mode === 'delivery') {
                $values['supplies'] = [];
            }
            if ($mode === 'correction') {
                $values['lines'] = $values['report'];
            }
            $draft = ['actor' => (int) $request->user()->id, 'id' => $id, 'mode' => $mode, 'revision' => $row?->revision, 'values' => $values, 'review' => null];
            $request->session()->put('event_work.'.$token, $draft);
        }
        if (isset($draft['result'])) {
            return redirect('/events/'.$draft['result']);
        }

        return view('events.form', $this->choices($id) + ['row' => $row, 'token' => $token, 'draft' => $draft, 'mode' => $mode, 'base' => $this->base($id, $mode)]);
    }

    public function update(Request $request, ?int $id = null, string $mode = 'plan')
    {
        $this->ready($request, true);
        $request->validate(['work' => ['required', 'uuid'], 'action' => ['required', 'in:save,review,add,remove,split,remove_split'], 'actor_id' => ['prohibited'], 'owner_id' => ['prohibited'], 'status' => ['prohibited'],
            'supplies' => ['nullable', 'array', 'max:1000'], 'lines' => ['nullable', 'array', 'max:1000']]);
        $token = $request->input('work');
        $draft = $this->draft($request, $token, $id, $mode);
        abort_if(isset($draft['result']), 409);
        $values = $request->except(['_token', '_deliberate', 'work', 'action', 'remove_index', 'split_item', 'remove_split']);
        if ($mode === 'counts' && is_array($values['lines'] ?? null)) {
            $saved = app(EventWorkflow::class)->data($id);
            foreach ($values['lines'] as $item => &$line) {
                if (is_array($line)) {
                    $line['brought'] = $saved['lines'][$item]['brought'] ?? 0;
                }
            }
            unset($line);
        }
        if ($mode === 'activate') {
            $values = $draft['values'] + ['actual_date' => $request->input('actual_date')];
            $values['actual_date'] = $request->input('actual_date');
        }
        $draft['values'] = $values;
        $draft['review'] = null;
        // Retain user values before validation and before any posting attempt.
        $request->session()->put('event_work.'.$token, $draft);
        $action = $request->input('action');
        if (in_array($action, ['add', 'remove'], true)) {
            abort_unless(in_array($mode, ['plan', 'delivery'], true), 422);
            $supplies = $values['supplies'] ?? [];
            if ($action === 'add') {
                abort_if(count($supplies) >= 1000, 422);
                $supplies[] = ['item_id' => '', 'source' => '', 'quantity' => ''];
            } else {
                unset($supplies[$request->input('remove_index')]);
            }
            $draft['values']['supplies'] = array_values($supplies);
        } elseif (in_array($action, ['split', 'remove_split'], true)) {
            abort_unless($mode === 'counts', 422);
            $item = RequestValues::id($request->input('split_item'), 'split_item');
            abort_unless(isset($draft['values']['lines'][$item]) && is_array($draft['values']['lines'][$item]), 422);
            if ($action === 'split') {
                $draft['values']['lines'][$item]['allocations'][] = ['destination' => '', 'quantity' => ''];
            } else {
                unset($draft['values']['lines'][$item]['allocations'][$request->input('remove_split')]);
            }
            $draft['values']['lines'][$item]['allocations'] = array_values($draft['values']['lines'][$item]['allocations']);
        } else {
            try {
                if ($mode === 'plan' && $action === 'save') {
                    $result = app(EventWorkflow::class)->plan((int) $request->user()->id, $id, $draft['revision'], $values, $token);
                    $draft['result'] = $result;
                    $request->session()->put('event_work.'.$token, $draft);

                    return redirect('/events/'.$result)->with('success', 'Planning saved; no stock moved.');
                }
                $workflowMode = $mode === 'counts' && $action === 'review' ? 'finalize' : $mode;
                $draft['workflowMode'] = $workflowMode;
                $draft['review'] = app(EventWorkflow::class)->work((int) $request->user()->id, $id, $draft['revision'], $workflowMode, $values, $action, $action === 'save' ? $token : null);
                if ($action === 'save') {
                    $draft['result'] = $id;
                    $request->session()->put('event_work.'.$token, $draft);

                    return redirect('/events/'.$id)->with('success', 'Provisional counts saved; no stock moved.');
                }
                $request->session()->put('event_work.'.$token, $draft);

                return redirect($this->base($id, $mode).'/review/'.$token);
            } catch (ValidationException $error) {
                return redirect($this->base($id, $mode).'?work='.$token)->withErrors($error->errors())->withInput();
            } catch (QueryException|\PDOException) {
                return redirect($this->base($id, $mode).'?work='.$token)->withInput()->with('error', 'The event could not be saved. Your entries are retained; inspect the latest saved event before retrying.');
            }
        }
        $request->session()->put('event_work.'.$token, $draft);

        return redirect($this->base($id, $mode).'?work='.$token);
    }

    public function review(Request $request, int $id, string $mode, string $token)
    {
        $this->ready($request, true);
        $draft = $this->draft($request, $token, $id, $mode);
        abort_unless(is_array($draft['review']), 422);

        return view('events.review', $this->choices($id) + ['row' => DB::table('event_workflows')->where('id', $id)->firstOrFail(), 'mode' => $mode, 'token' => $token, 'draft' => $draft, 'base' => $this->base($id, $mode)]);
    }

    public function confirm(Request $request, int $id, string $mode, string $token)
    {
        $this->ready($request, true);
        $draft = $this->draft($request, $token, $id, $mode);
        abort_unless(is_array($draft['review']), 422);
        try {
            app(EventWorkflow::class)->work((int) $request->user()->id, $id, $draft['revision'], $draft['workflowMode'], $draft['values'], 'confirm', $token);
        } catch (ValidationException $error) {
            return redirect($this->base($id, $mode).'?work='.$token)->withErrors($error->errors())->withInput();
        } catch (QueryException|\PDOException) {
            return back()->with('error', 'No event posting was completed. Your reviewed entries are retained; inspect before retrying.');
        }
        $draft['result'] = $id;
        $request->session()->put('event_work.'.$token, $draft);

        return redirect('/events/'.$id)->with('success', $draft['workflowMode'] === 'correction' ? 'Report corrected; stock, cost and original history are unchanged.' : 'Event action posted once.');
    }

    public function report(Request $request, int $id)
    {
        $this->ready($request);
        $row = DB::table('event_workflows')->where('id', $id)->firstOrFail();
        abort_unless($row->status === 'Finalized', 409, 'Provisional counts are not a final report.');
        $data = app(EventWorkflow::class)->data($id);
        $report = collect($data['report'])->sortBy(fn ($line) => [$line['category'], $line['collection'], $line['item'], $line['sku']]);
        $all = $request->boolean('all');
        if (! $all) {
            $report = $report->filter(fn ($line) => $line['distributed'] !== 0);
        }
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($row, $data, $report) {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['Event', EventValues::csv($row->name), 'Finalized', $data['finalized_at'], $data['corrected'] ? 'Corrected report' : 'Original report'], ',', '"', '');
                fputcsv($stream, ['Category', 'Collection', 'Item', 'SKU', 'Distributed'], ',', '"', '');
                foreach ($report as $line) {
                    fputcsv($stream, array_map([EventValues::class, 'csv'], [$line['category'], $line['collection'], $line['item'], $line['sku'], $line['distributed']]), ',', '"', '');
                }
                fclose($stream);
            }, 'event-'.$id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('events.report', ['row' => $row, 'data' => $data, 'report' => $report, 'all' => $all]);
    }
}
