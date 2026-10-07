<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RelocationRequests
{
    public function editable(User $actor, object $row): bool
    {
        return in_array($row->status, ['Draft', 'Requested'], true)
            && (RequestValues::manages($actor, 'manager') || ($row->status === 'Draft' && (int) $row->owner_id === (int) $actor->id));
    }

    public function fields(array $data): array
    {
        return ['title' => RequestValues::text($data['title'] ?? null, 'title', 255, true),
            'details' => RequestValues::text($data['details'] ?? null, 'details'),
            'source_location_id' => RequestValues::id($data['source_location_id'] ?? null, 'source_location_id'),
            'destination_location_id' => RequestValues::id($data['destination_location_id'] ?? null, 'destination_location_id')];
    }

    public function validate(array $data, ?object $old = null, bool $review = false): array
    {
        $fields = $this->fields($data);
        foreach (['source_location_id', 'destination_location_id'] as $field) {
            if ($fields[$field] === null) {
                throw ValidationException::withMessages([$field => 'Choose a storage location before saving or reviewing this request.']);
            }
            RequestValues::association('storage_locations', $fields[$field], $old?->{$field}, $field, $review);
        }
        $lines = $data['lines'] ?? [];
        if (! is_array($lines) || count($lines) > 1000) {
            throw ValidationException::withMessages(['lines' => 'Use up to 1,000 catalog items.']);
        }
        $existing = $old ? DB::table('relocation_request_lines')->where('relocation_request_id', $old->id)->pluck('item_id')->map(fn ($id) => (int) $id)->all() : [];
        $clean = [];
        foreach ($lines as $item => $quantity) {
            $itemId = RequestValues::id($item, 'lines');
            if (! $itemId) {
                throw ValidationException::withMessages(['lines' => 'Choose an existing catalog item.']);
            }
            RequestValues::association('items', $itemId, in_array($itemId, $existing, true) ? $itemId : null, 'lines.'.$item);
            $clean[$itemId] = RequestValues::quantity($quantity, 'lines.'.$item);
        }
        ksort($clean, SORT_NUMERIC);
        if ($review && (! $fields['source_location_id'] || ! $fields['destination_location_id'] || $fields['source_location_id'] === $fields['destination_location_id'] || $clean === [])) {
            throw ValidationException::withMessages(['review' => 'Choose two distinct active storage locations and at least one catalog item before review. Zero requested is allowed.']);
        }

        return ['fields' => $fields, 'lines' => $clean];
    }

    public function save(int $actorId, ?int $id, mixed $revision, array $data, bool $submit = false): int
    {
        return DB::transaction(function () use ($actorId, $id, $revision, $data, $submit) {
            $actor = RequestValues::actor($actorId);
            $row = $id === null ? null : DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($row) {
                abort_unless($this->editable($actor, $row), 403);
                RequestValues::revision($revision, $row);
            }
            if ($submit) {
                abort_unless(($row === null || $row->status === 'Draft') && (RequestValues::manages($actor, 'manager') || (($row === null || (int) $row->owner_id === $actorId) && RequestValues::submitsOwn($actor))), 403);
            }
            $validated = $this->validate($data, $row, $submit || $row?->status === 'Requested');
            $fields = $validated['fields'];
            $lines = $validated['lines'];
            if ($row === null) {
                $id = DB::table('relocation_requests')->insertGetId($fields + ['owner_id' => $actorId, 'status' => $submit ? 'Requested' : 'Draft', 'revision' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
                RequestValues::activity('relocation', $id, $actor, 'Created Draft', null, $fields + ['lines' => $lines]);
                if ($submit) {
                    RequestValues::activity('relocation', $id, $actor, 'Status changed', ['status' => 'Draft'], ['status' => 'Requested']);
                }
            } else {
                $previous = array_intersect_key((array) $row, $fields);
                foreach (['source_location_id', 'destination_location_id'] as $field) {
                    $previous[$field] = $previous[$field] === null ? null : (int) $previous[$field];
                }
                $oldLines = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->orderBy('item_id')->pluck('quantity', 'item_id')->map(fn ($q) => (int) $q)->all();
                if ($previous === $fields && $oldLines === $lines && ! $submit) {
                    return $id;
                }
                DB::table('relocation_requests')->where('id', $id)->update($fields + ['status' => $submit ? 'Requested' : $row->status, 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
                if ($previous !== $fields || $oldLines !== $lines) {
                    RequestValues::activity('relocation', $id, $actor, 'Saved request', $previous + ['lines' => $oldLines], $fields + ['lines' => $lines]);
                }
                if ($submit) {
                    RequestValues::activity('relocation', $id, $actor, 'Status changed', ['status' => 'Draft'], ['status' => 'Requested']);
                }
            }
            DB::table('relocation_request_lines')->where('relocation_request_id', $id)->whereNotIn('item_id', array_keys($lines))->delete();
            foreach ($lines as $item => $quantity) {
                DB::table('relocation_request_lines')->updateOrInsert(['relocation_request_id' => $id, 'item_id' => $item], ['quantity' => $quantity]);
            }

            return $id;
        }, 3);
    }

    public function transition(int $actorId, int $id, mixed $revision, string $action): void
    {
        abort_unless(in_array($action, ['return', 'cancel'], true), 422);
        DB::transaction(function () use ($actorId, $id, $revision, $action) {
            $actor = RequestValues::actor($actorId);
            $row = DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            $manager = RequestValues::manages($actor, 'manager');
            abort_unless($manager || ($action === 'cancel' && (int) $row->owner_id === $actorId && $row->status === 'Draft'), 403);
            RequestValues::revision($revision, $row);
            $next = $action === 'return' && $row->status === 'Requested' ? 'Draft' : ($action === 'cancel' && $row->status === 'Draft' ? 'Cancelled' : null);
            abort_unless($next !== null, 409, 'Return to Draft before cancellation.');
            DB::table('relocation_requests')->where('id', $id)->update(['status' => $next, 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
            RequestValues::activity('relocation', $id, $actor, 'Status changed', ['status' => $row->status], ['status' => $next]);
        }, 3);
    }

    public function title(int $actorId, int $id, mixed $revision, mixed $title): void
    {
        $title = RequestValues::text($title, 'title', 255, true);
        DB::transaction(function () use ($actorId, $id, $revision, $title) {
            $actor = RequestValues::actor($actorId);
            $row = DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->owner_id === $actorId || RequestValues::manages($actor, 'manager'), 403);
            RequestValues::revision($revision, $row);
            if ($row->title === $title) {
                return;
            }
            DB::table('relocation_requests')->where('id', $id)->update(['title' => $title, 'revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
            RequestValues::activity('relocation', $id, $actor, 'Title changed', ['title' => $row->title], ['title' => $title]);
        }, 3);
    }

    public function fulfillment(int $actorId, int $id, mixed $revision, mixed $values): void
    {
        DB::transaction(function () use ($actorId, $id, $revision, $values) {
            $actor = RequestValues::actor($actorId);
            $row = DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless(RequestValues::manages($actor, 'manager') && $row->status === 'Requested', 403);
            RequestValues::revision($revision, $row);
            $lines = DB::table('relocation_request_lines')->where('relocation_request_id', $id)->orderBy('item_id')->get()->keyBy('item_id');
            if (! is_array($values) || count($values) > 1000) {
                throw ValidationException::withMessages(['fulfillment' => 'Enter preparation quantities for saved items only.']);
            }
            $before = [];
            $after = [];
            foreach ($lines as $line) {
                $before[(int) $line->item_id] = $line->fulfillment_quantity === null ? null : (int) $line->fulfillment_quantity;
            }
            $after = $before;
            foreach ($values as $item => $value) {
                $itemId = RequestValues::id($item, 'fulfillment');
                abort_unless($itemId && $lines->has($itemId), 422);
                $after[$itemId] = RequestValues::quantity($value, 'fulfillment.'.$item, true);
            }
            if ($before === $after) {
                return;
            }
            foreach ($after as $item => $quantity) {
                DB::table('relocation_request_lines')->where('relocation_request_id', $id)->where('item_id', $item)->update(['fulfillment_quantity' => $quantity]);
            }
            DB::table('relocation_requests')->where('id', $id)->update(['revision' => $row->revision + 1, 'updated_at' => now('UTC')]);
            RequestValues::activity('relocation', $id, $actor, 'Saved fulfillment preparation', $before, $after);
        }, 3);
    }
}
