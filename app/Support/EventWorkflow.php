<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventWorkflow
{
    private function actor(int $id): User
    {
        $actor = RequestValues::actor($id);
        abort_unless(RequestValues::manages($actor, 'manager'), 403);
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['eventsReady'], 503, 'Events need guarded preparation.');

        return $actor;
    }

    private function token(string $token): void
    {
        abort_unless(Str::isUuid($token), 422);
    }

    private function duplicate(string $token, User $actor, ?int $id, string $action): ?object
    {
        $previous = DB::table('event_operations')->where('operation_id', $token)->lockForUpdate()->first();
        if ($previous) {
            abort_unless((int) $previous->actor_id === (int) $actor->id && ($id === null || (int) $previous->event_id === $id) && $previous->action === $action, 403);
        }

        return $previous;
    }

    private function operation(string $token, User $actor, int $event, string $action, array $data): int
    {
        return (int) DB::table('event_operations')->insertGetId(['operation_id' => $token, 'event_id' => $event, 'actor_id' => $actor->id,
            'actor_name' => $actor->displayName(), 'action' => $action, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'posted_at' => now('UTC')->startOfSecond()]);
    }

    private function decode(object $row): array
    {
        return json_decode($row->data, true, flags: JSON_THROW_ON_ERROR);
    }

    private function changed(object $event, array $header, ?string $status = null): void
    {
        DB::table('event_workflows')->where('id', $event->id)->update(['data' => json_encode($header, JSON_THROW_ON_ERROR), 'revision' => (int) $event->revision + 1, 'status' => $status ?? $event->status]);
    }

    private function destination(string $key, int $self, array $lockedEvents): string
    {
        [$kind, $id] = explode(':', $key);
        $id = (int) $id;
        if ($kind === 'storage') {
            app(CatalogRecords::class)->activeAssociation('storage_locations', $id);

            return DB::table('storage_locations')->where('id', $id)->value('name');
        }
        $event = $lockedEvents[$id] ?? null;
        abort_unless($event && $id !== $self && $event->status === 'Active' && $event->source_id !== null, 409, 'Choose another currently active event.');
        $source = DB::table('inventory_sources')->where('id', $event->source_id)->lockForUpdate()->firstOrFail();
        abort_unless($source->active && $source->kind === 'event', 409, 'The receiving event source needs inspection.');

        return $event->name;
    }

    private function lockEvents(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $events = [];
        foreach ($ids as $id) {
            $events[$id] = DB::table('event_workflows')->where('id', $id)->lockForUpdate()->firstOrFail();
        }

        return $events;
    }

    private function eventIds(array $keys): array
    {
        return array_map(fn ($key) => (int) substr($key, 6), array_values(array_filter($keys, fn ($key) => str_starts_with($key, 'event:'))));
    }

    private function supplies(mixed $rows, bool $complete): array
    {
        if (! is_array($rows) || count($rows) > 1000) {
            throw ValidationException::withMessages(['supplies' => 'Use at most 1,000 supply lines.']);
        }
        $clean = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['supplies' => 'Enter an item, source and quantity for each supply.']);
            }
            $item = RequestValues::id($row['item_id'] ?? null, 'supplies.'.$index);
            $source = $row['source'] ?? '';
            $quantity = RequestValues::quantity($row['quantity'] ?? '', 'supplies.'.$index, true);
            if ($item === null && $source === '' && $quantity === null) {
                continue;
            }
            if (! is_string($source) || ($source !== '' && $source !== 'external' && ! preg_match('/\Astorage:[1-9]\d{0,17}\z/D', $source)) || ($complete && ($item === null || $source === ''))) {
                throw ValidationException::withMessages(['supplies.'.$index => 'Choose a catalog item and known storage source, or external supply.']);
            }
            if ($complete && $quantity === null) {
                throw ValidationException::withMessages(['supplies.'.$index => 'Enter actual individual units for every supply line.']);
            }
            if ($item !== null) {
                app(CatalogRecords::class)->activeAssociation('items', $item);
            }
            if ($source !== '' && $source !== 'external') {
                app(CatalogRecords::class)->activeAssociation('storage_locations', (int) substr($source, 8));
            }
            $clean[] = ['item_id' => $item, 'source' => $source, 'quantity' => $quantity];
        }
        if ($complete && array_sum(array_column($clean, 'quantity')) === 0) {
            throw ValidationException::withMessages(['supplies' => 'Record at least one positive actual supply.']);
        }

        return $clean;
    }

    public function plan(int $actorId, ?int $id, mixed $revision, array $values, string $token): int
    {
        $this->token($token);

        return DB::transaction(function () use ($actorId, $id, $revision, $values, $token) {
            $actor = $this->actor($actorId);
            if ($previous = $this->duplicate($token, $actor, $id, 'plan')) {
                return (int) $previous->event_id;
            }
            $event = $id === null ? null : DB::table('event_workflows')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($event) {
                abort_unless($event->status === 'Planning', 409);
                RequestValues::revision($revision, $event);
            }
            $name = RequestValues::text($values['name'] ?? '', 'name', 255, true);
            $initial = RequestValues::id($values['initial_source_id'] ?? null, 'initial_source_id');
            if ($initial !== null) {
                app(CatalogRecords::class)->activeAssociation('storage_locations', $initial);
            }
            $default = EventValues::destination($values['default_destination'] ?? null, 'default_destination', true);
            // Planning may retain an incomplete future destination; posting rechecks current eligibility.
            if ($default !== null) {
                $this->destination($default, $id ?? 0, $this->lockEvents($this->eventIds([$default])));
            }
            $header = ['venue' => RequestValues::text($values['venue'] ?? null, 'venue', 255), 'notes' => RequestValues::text($values['notes'] ?? null, 'notes'),
                'initial_source_id' => $initial, 'default_destination' => $default, 'supplies' => $this->supplies($values['supplies'] ?? [], false)];
            $before = $event ? ['name' => $event->name, 'data' => $this->decode($event)] : null;
            if ($event) {
                DB::table('event_workflows')->where('id', $id)->update(['name' => $name]);
                $this->changed($event, $header);
            } else {
                $id = (int) DB::table('event_workflows')->insertGetId(['name' => $name, 'status' => 'Planning', 'revision' => 1, 'data' => json_encode($header, JSON_THROW_ON_ERROR)]);
            }
            $this->operation($token, $actor, $id, 'plan', ['before' => $before, 'after' => ['name' => $name, 'data' => $header]]);

            return $id;
        }, 3);
    }

    public function data(int $id): array
    {
        $event = DB::table('event_workflows')->where('id', $id)->firstOrFail();
        $header = $this->decode($event);
        $lines = [];
        foreach (DB::table('event_lines')->where('event_id', $id)->orderBy('item_id')->get() as $line) {
            $data = $this->decode($line);
            $lines[$line->item_id] = ['brought' => (int) $line->brought, 'remaining' => $line->remaining === null ? '' : (string) $line->remaining,
                'allocations' => array_map(fn ($key, $quantity) => ['destination' => $key, 'quantity' => $quantity === null ? '' : (string) $quantity], array_keys($data['allocations'] ?? []), array_values($data['allocations'] ?? []))];
        }

        return $header + ['name' => $event->name, 'lines' => $lines];
    }

    public function work(int $actorId, int $id, mixed $revision, string $mode, array $values, string $action, ?string $token = null): array
    {
        abort_unless(in_array($mode, ['activate', 'delivery', 'counts', 'finalize', 'correction'], true) && in_array($action, ['save', 'review', 'confirm'], true), 422);
        abort_unless($mode === 'counts' ? $action === 'save' : ($mode === 'correction' ? in_array($action, ['review', 'confirm'], true) : $action !== 'save'), 422);
        if ($action !== 'review') {
            abort_unless(is_string($token), 422);
            $this->token($token);
        }

        return DB::transaction(function () use ($actorId, $id, $revision, $mode, $values, $action, $token) {
            $actor = $this->actor($actorId);
            if ($action !== 'review' && $this->duplicate($token, $actor, $id, $mode)) {
                return ['duplicate' => true];
            }
            // Global access lock precedes event locks, matching inventory/request postings.
            $event = DB::table('event_workflows')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($event->status === ($mode === 'activate' ? 'Planning' : ($mode === 'correction' ? 'Finalized' : 'Active')), 409);
            RequestValues::revision($revision, $event);
            $header = $this->decode($event);
            if (in_array($mode, ['activate', 'delivery'], true)) {
                return $this->supply($actor, $event, $header, $values, $mode, $action, $token);
            }
            if ($mode === 'correction') {
                return $this->correct($actor, $event, $header, $values, $action, $token);
            }

            return $this->count($actor, $event, $header, $values, $mode, $action, $token);
        }, 3);
    }

    private function sourceBalance(int $item, int $source, int $delta): void
    {
        $row = DB::table('inventory_source_balances')->where('item_id', $item)->where('source_id', $source)->lockForUpdate()->first();
        $quantity = StockNumbers::quantity((int) ($row?->quantity ?? 0) + $delta, 'stock');
        DB::table('inventory_source_balances')->updateOrInsert(['item_id' => $item, 'source_id' => $source], ['quantity' => $quantity, 'created_at' => $row?->created_at ?? now('UTC'), 'updated_at' => now('UTC')]);
    }

    private function entry(int $operation, int $event, object $item, string $leg, string $description, int $quantity): void
    {
        DB::table('event_stock_entries')->insert(['operation_id' => $operation, 'event_id' => $event, 'item_id' => $item->id, 'leg' => $leg,
            'item_name' => $item->name, 'item_sku' => $item->sku, 'description' => $description, 'quantity_change' => $quantity, 'unit_cost' => StockNumbers::cost((string) $item->unit_cost)]);
    }

    private function contribute(int $event, int $item, int $quantity, string $default): void
    {
        $line = DB::table('event_lines')->where('event_id', $event)->where('item_id', $item)->lockForUpdate()->first();
        $brought = RequestValues::quantity((int) ($line?->brought ?? 0) + $quantity, 'brought');
        if ($line) {
            DB::table('event_lines')->where('id', $line->id)->update(['brought' => $brought]);
        } else {
            DB::table('event_lines')->insert(['event_id' => $event, 'item_id' => $item, 'brought' => $brought, 'remaining' => null,
                'data' => json_encode(['allocations' => [$default => null]], JSON_THROW_ON_ERROR)]);
        }
    }

    private function supply(User $actor, object $event, array $header, array $values, string $mode, string $action, ?string $token): array
    {
        $supplies = $this->supplies($mode === 'activate' ? ($header['supplies'] ?? []) : ($values['supplies'] ?? []), true);
        $date = RequestStock::date($values['actual_date'] ?? null, 'actual_date', true);
        $default = EventValues::destination($header['default_destination'] ?? null, 'default_destination');
        if ($mode === 'activate') {
            $locked = $this->lockEvents($this->eventIds([$default]));
            $this->destination($default, (int) $event->id, $locked);
            $initial = RequestValues::id($header['initial_source_id'] ?? null, 'initial_source_id');
            if ($initial === null) {
                throw ValidationException::withMessages(['initial_source_id' => 'Choose the initial storage source before activation.']);
            }
            app(CatalogRecords::class)->activeAssociation('storage_locations', $initial);
        }
        $clean = ['supplies' => $supplies, 'actual_date' => $date];
        if ($action === 'review') {
            return $clean;
        }
        if ($mode === 'activate') {
            // A stable new source is created only once; legacy sources are never adopted.
            $source = (int) DB::table('inventory_sources')->insertGetId(['kind' => 'event', 'name' => mb_substr($event->name, 0, 220).' · event #'.$event->id, 'active' => true, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            DB::table('event_workflows')->where('id', $event->id)->update(['source_id' => $source]);
            $event->source_id = $source;
        }
        $source = DB::table('inventory_sources')->where('id', $event->source_id)->lockForUpdate()->firstOrFail();
        abort_unless($source->active && $source->kind === 'event', 409);
        $stock = app(RequestStock::class);
        $items = $stock->items(array_column($supplies, 'item_id'));
        $operation = $this->operation($token, $actor, (int) $event->id, $mode, $clean);
        $groups = [];
        foreach ($supplies as $line) {
            if ($line['quantity'] > 0) {
                $groups[$line['item_id']][$line['source']] = ($groups[$line['item_id']][$line['source']] ?? 0) + $line['quantity'];
            }
        }
        foreach ($groups as $itemId => $sources) {
            foreach ($sources as $key => $quantity) {
                RequestValues::quantity($quantity, 'supplies');
                $item = $items[$itemId];
                if ($key !== 'external') {
                    $location = (int) substr($key, 8);
                    $name = DB::table('storage_locations')->where('id', $location)->value('name');
                    $stock->storage($itemId, $location, -$quantity);
                    $description = 'Relocation: '.$name.' → '.$event->name;
                } else {
                    $description = 'Adjustment: '.$event->name.' external supply';
                }
                $this->sourceBalance($itemId, (int) $event->source_id, $quantity);
                $this->contribute((int) $event->id, $itemId, $quantity, $default);
                $this->entry($operation, (int) $event->id, $item, $key, $description, $quantity);
            }
        }
        $header[$mode.'_date'] = $date;
        $this->changed($event, $header, 'Active');

        return $clean;
    }

    private function count(User $actor, object $event, array $header, array $values, string $mode, string $action, ?string $token): array
    {
        $stored = DB::table('event_lines')->where('event_id', $event->id)->orderBy('item_id')->lockForUpdate()->get()->keyBy('item_id');
        $inputs = $values['lines'] ?? [];
        abort_unless(is_array($inputs) && count($inputs) <= 1000, 422);
        $expected = $stored->keys()->map(fn ($id) => (string) $id)->all();
        $received = array_map('strval', array_keys($inputs));
        sort($expected);
        sort($received);
        abort_unless($expected !== [] && $expected === $received, 422, 'Use every current event item exactly once.');
        $clean = [];
        $keys = [];
        foreach ($stored as $item => $line) {
            abort_unless(is_array($inputs[$item]), 422);
            $allocations = EventValues::allocations($inputs[$item]['allocations'] ?? [], 'lines.'.$item.'.allocations');
            $remaining = EventValues::counts($inputs[$item]['remaining'] ?? '', $allocations, (int) $line->brought, 'lines.'.$item.'.remaining', $mode === 'finalize');
            $clean[$item] = ['brought' => (int) $line->brought, 'remaining' => $remaining, 'allocations' => $allocations];
            $keys = array_merge($keys, array_keys($allocations));
        }
        $locked = $mode === 'finalize' ? $this->lockEvents(array_merge([(int) $event->id], $this->eventIds($keys))) : [];
        foreach (array_unique($keys) as $key) {
            // Provisional counts may retain an obsolete choice; finalization must recheck.
            if ($mode === 'finalize') {
                $this->destination($key, (int) $event->id, $locked);
            }
        }
        if ($action === 'review') {
            return ['lines' => $clean];
        }
        if ($mode === 'counts') {
            $before = $stored->map(fn ($line) => ['remaining' => $line->remaining, 'data' => $this->decode($line)])->all();
            foreach ($clean as $item => $line) {
                DB::table('event_lines')->where('event_id', $event->id)->where('item_id', $item)->update(['remaining' => $line['remaining'], 'data' => json_encode(['allocations' => $line['allocations']], JSON_THROW_ON_ERROR)]);
            }
            $this->operation($token, $actor, (int) $event->id, 'counts', ['before' => $before, 'after' => $clean]);
            $this->changed($event, $header);

            return ['lines' => $clean];
        }
        $stock = app(RequestStock::class);
        $items = $stock->items(array_keys($clean));
        $report = [];
        foreach ($clean as $itemId => $line) {
            $item = $items[$itemId];
            $quantity = (int) DB::table('inventory_source_balances')->where('item_id', $itemId)->where('source_id', $event->source_id)->lockForUpdate()->value('quantity');
            abort_unless($quantity === $line['brought'], 409, 'Event balance and contributed stock differ; inspect before finalization.');
            $collection = DB::table('collections')->where('id', $item->collection_id)->firstOrFail();
            $category = DB::table('categories')->where('id', $collection->category_id)->firstOrFail();
            $report[$itemId] = ['category' => $category->name, 'collection' => $collection->name, 'item' => $item->name, 'sku' => $item->sku,
                'brought' => $line['brought'], 'remaining' => $line['remaining'], 'distributed' => $line['brought'] - $line['remaining']];
        }
        $operation = $this->operation($token, $actor, (int) $event->id, 'finalize', ['lines' => $clean, 'report' => $report]);
        $receivers = [];
        foreach ($clean as $itemId => $line) {
            $item = $items[$itemId];
            $this->sourceBalance($itemId, (int) $event->source_id, -$line['brought']);
            $distributed = $line['brought'] - $line['remaining'];
            if ($distributed > 0) {
                $this->entry($operation, (int) $event->id, $item, 'distribution', 'Event: '.$event->name.' (distributed)', -$distributed);
            }
            foreach ($line['allocations'] as $key => $quantity) {
                if ($quantity === 0) {
                    continue;
                }
                [$kind, $destination] = explode(':', $key);
                $destination = (int) $destination;
                $name = $this->destination($key, (int) $event->id, $locked);
                if ($kind === 'storage') {
                    $stock->storage($itemId, $destination, $quantity);
                } else {
                    $receiver = $locked[$destination];
                    $receiverHeader = $this->decode($receiver);
                    $receiverDefault = EventValues::destination($receiverHeader['default_destination'] ?? null, 'default_destination');
                    $this->sourceBalance($itemId, (int) $receiver->source_id, $quantity);
                    $this->contribute($destination, $itemId, $quantity, $receiverDefault);
                    $this->entry($operation, $destination, $item, 'incoming:'.$destination, 'Relocation: '.$event->name.' → '.$name.' (event finalization)', $quantity);
                    $receivers[$destination] = $receiverHeader;
                }
                if ($kind === 'storage') {
                    $this->entry($operation, (int) $event->id, $item, $key, 'Event: '.$event->name.' → '.$name, $quantity);
                }
            }
        }
        foreach ($receivers as $destination => $data) {
            $this->changed($locked[$destination], $data);
        }
        foreach ($clean as $item => $line) {
            DB::table('event_lines')->where('event_id', $event->id)->where('item_id', $item)->update(['remaining' => $line['remaining'], 'data' => json_encode(['allocations' => $line['allocations']], JSON_THROW_ON_ERROR)]);
        }
        $header['report'] = $report;
        $header['finalized_at'] = now('UTC')->startOfSecond()->toIso8601String();
        $header['finalization_operation'] = $operation;
        $header['corrected'] = false;
        $this->changed($event, $header, 'Finalized');
        DB::table('inventory_sources')->where('id', $event->source_id)->update(['active' => false, 'updated_at' => now('UTC')]);

        return ['lines' => $clean, 'report' => $report];
    }

    private function correct(User $actor, object $event, array $header, array $values, string $action, ?string $token): array
    {
        $report = $header['report'] ?? [];
        $inputs = $values['lines'] ?? [];
        abort_unless(is_array($inputs), 422);
        $expected = array_map('strval', array_keys($report));
        $received = array_map('strval', array_keys($inputs));
        sort($expected);
        sort($received);
        abort_unless($expected !== [] && $expected === $received, 422);
        $before = $report;
        foreach ($report as $item => &$row) {
            abort_unless(is_array($inputs[$item]), 422);
            $brought = RequestValues::quantity($inputs[$item]['brought'] ?? null, 'lines.'.$item.'.brought');
            $remaining = RequestValues::quantity($inputs[$item]['remaining'] ?? null, 'lines.'.$item.'.remaining');
            if ($remaining > $brought) {
                throw ValidationException::withMessages(['lines.'.$item => 'Report remaining cannot exceed report brought.']);
            }
            $row['brought'] = $brought;
            $row['remaining'] = $remaining;
            $row['distributed'] = $brought - $remaining;
        }
        unset($row);
        $explanation = RequestValues::text($values['explanation'] ?? null, 'explanation');
        if ($action === 'review') {
            return ['before' => $before, 'report' => $report, 'explanation' => $explanation];
        }
        $this->operation($token, $actor, (int) $event->id, 'correction', ['before' => $before, 'after' => $report, 'explanation' => $explanation]);
        $header['report'] = $report;
        $header['corrected'] = true;
        $this->changed($event, $header);

        return ['report' => $report];
    }
}
