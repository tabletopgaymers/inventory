<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequests
{
    public function editable(User $actor, object $row): bool
    {
        return in_array($row->status, ['Draft', 'Request'], true)
            && (RequestValues::manages($actor, 'procurement') || ($row->status === 'Draft' && (int) $row->owner_id === (int) $actor->id));
    }

    public function fields(array $data): array
    {
        return ['title' => RequestValues::text($data['title'] ?? null, 'title', 255),
            'suggested_merchant' => RequestValues::text($data['suggested_merchant'] ?? null, 'suggested_merchant', 255),
            'details' => RequestValues::text($data['details'] ?? null, 'details')];
    }

    public function save(int $actorId, ?int $id, mixed $revision, array $data): int
    {
        $fields = $this->fields($data);

        return DB::transaction(function () use ($actorId, $id, $revision, $fields) {
            $actor = RequestValues::actor($actorId);
            if ($id === null) {
                $id = DB::table('purchase_requests')->insertGetId($fields + ['owner_id' => $actorId, 'status' => 'Draft', 'revision' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
                RequestValues::activity('purchase', $id, $actor, 'Created Draft', null, $fields);

                return $id;
            }
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($this->editable($actor, $row), 403);
            RequestValues::revision($revision, $row);
            $previous = array_intersect_key((array) $row, $fields);
            if ($previous !== $fields) {
                DB::table('purchase_requests')->where('id', $id)->update($fields + ['revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
                RequestValues::activity('purchase', $id, $actor, 'Saved details', $previous, $fields);
            }

            return $id;
        }, 3);
    }

    public function transition(int $actorId, int $id, mixed $revision, string $action): void
    {
        abort_unless(in_array($action, ['submit', 'return', 'cancel'], true), 422);
        DB::transaction(function () use ($actorId, $id, $revision, $action) {
            $actor = RequestValues::actor($actorId);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            $manager = RequestValues::manages($actor, 'procurement');
            $owner = (int) $row->owner_id === $actorId;
            abort_unless(match ($action) {
                'submit' => $manager || ($owner && RequestValues::elevated($actor)),
                'return' => $manager,
                'cancel' => $manager || ($owner && $row->status === 'Draft'),
            }, 403);
            RequestValues::revision($revision, $row);
            $next = match ($action) {
                'submit' => $row->status === 'Draft' ? 'Request' : null,
                'return' => $row->status === 'Request' ? 'Draft' : null,
                'cancel' => in_array($row->status, ['Draft', 'Request'], true) ? 'Cancelled' : null,
            };
            abort_unless($next !== null, 409, 'This action is unavailable in the saved status.');
            DB::table('purchase_requests')->where('id', $id)->update(['status' => $next, 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
            RequestValues::activity('purchase', $id, $actor, 'Status changed', ['status' => $row->status], ['status' => $next]);
        }, 3);
    }

    public function note(int $actorId, int $id, mixed $body): void
    {
        $body = RequestValues::text($body, 'note', 5000, true);
        DB::transaction(function () use ($actorId, $id, $body) {
            $actor = RequestValues::actor($actorId);
            DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('purchase_request_notes')->insert(['purchase_request_id' => $id, 'actor_id' => $actorId, 'actor_name' => $actor->displayName(), 'body' => $body, 'occurred_at' => now('UTC')]);
            DB::table('purchase_requests')->where('id', $id)->update(['updated_at' => now('UTC')]);
        }, 3);
    }

    public function prepare(int $actorId, int $id, mixed $revision, array $data): void
    {
        DB::transaction(function () use ($actorId, $id, $revision, $data) {
            $actor = RequestValues::actor($actorId);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless(RequestValues::manages($actor, 'procurement') && in_array($row->status, ['Draft', 'Request'], true), 403);
            RequestValues::revision($revision, $row);
            $fields = ['supplier_id' => RequestValues::id($data['supplier_id'] ?? null, 'supplier_id'),
                'receiving_location_id' => RequestValues::id($data['receiving_location_id'] ?? null, 'receiving_location_id'),
                'planned_date' => RequestValues::text($data['planned_date'] ?? null, 'planned_date', 255),
                'preparation_notes' => RequestValues::text($data['preparation_notes'] ?? null, 'preparation_notes')];
            if ($fields['planned_date'] !== null && (! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $fields['planned_date']) || ! checkdate((int) substr($fields['planned_date'], 5, 2), (int) substr($fields['planned_date'], 8, 2), (int) substr($fields['planned_date'], 0, 4)))) {
                throw ValidationException::withMessages(['planned_date' => 'Enter a valid planned date.']);
            }
            RequestValues::association('suppliers', $fields['supplier_id'], $row->supplier_id, 'supplier_id');
            RequestValues::association('storage_locations', $fields['receiving_location_id'], $row->receiving_location_id, 'receiving_location_id');
            $old = DB::table('purchase_request_lines')->where('purchase_request_id', $id)->orderBy('item_id')->get()->keyBy('item_id');
            $lines = $data['lines'] ?? [];
            if (! is_array($lines) || count($lines) > 1000) {
                throw ValidationException::withMessages(['lines' => 'Use up to 1,000 catalog lines.']);
            }
            $clean = [];
            foreach ($lines as $item => $line) {
                $itemId = RequestValues::id($item, 'lines');
                if (! $itemId || ! is_array($line)) {
                    throw ValidationException::withMessages(['lines' => 'Choose a catalog item for each line.']);
                }
                RequestValues::association('items', $itemId, $old->has($itemId) ? $itemId : null, 'lines.'.$item);
                $estimate = $line['estimate'] ?? null;
                if ($estimate !== null && $estimate !== '' && (! is_string($estimate) || ! preg_match('/\A\d{1,12}(?:\.\d{1,12})?\z/D', $estimate))) {
                    throw ValidationException::withMessages(['lines.'.$item.'.estimate' => 'Enter a nonnegative exact estimate, up to 12 decimal places.']);
                }
                $clean[$itemId] = ['item_id' => $itemId, 'quantity' => RequestValues::quantity($line['quantity'] ?? null, 'lines.'.$item.'.quantity'), 'estimate' => $estimate === null || $estimate === '' ? null : StockNumbers::cost($estimate), 'note' => RequestValues::text($line['note'] ?? null, 'lines.'.$item.'.note')];
            }
            ksort($clean, SORT_NUMERIC);
            $previousLines = [];
            foreach ($old as $line) {
                $previousLines[(int) $line->item_id] = ['item_id' => (int) $line->item_id, 'quantity' => (int) $line->quantity, 'estimate' => $line->estimate, 'note' => $line->note];
            }
            $previous = array_intersect_key((array) $row, $fields);
            foreach (['supplier_id', 'receiving_location_id'] as $field) {
                $previous[$field] = $previous[$field] === null ? null : (int) $previous[$field];
            }
            if ($previous === $fields && $previousLines === $clean) {
                return;
            }
            DB::table('purchase_requests')->where('id', $id)->update($fields + ['revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
            DB::table('purchase_request_lines')->where('purchase_request_id', $id)->whereNotIn('item_id', array_keys($clean))->delete();
            foreach ($clean as $itemId => $line) {
                DB::table('purchase_request_lines')->updateOrInsert(['purchase_request_id' => $id, 'item_id' => $itemId], $line);
            }
            RequestValues::activity('purchase', $id, $actor, 'Saved preparation', $previous + ['lines' => $previousLines], $fields + ['lines' => $clean]);
        }, 3);
    }
}
