<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RelocationFulfillment
{
    public function data(int $id): array
    {
        $row = DB::table('relocation_requests')->where('id', $id)->firstOrFail();
        $header = RequestStock::header('relocation', $id);
        $saved = DB::table('relocation_fulfillment_lines')->where('relocation_request_id', $id)->get()->keyBy('item_id');
        $lines = [];
        foreach (DB::table('relocation_request_lines')->where('relocation_request_id', $id)->orderBy('item_id')->get() as $line) {
            $lines[$line->item_id] = ['sent' => in_array($row->status, ['Draft', 'Requested'], true) ? ($line->fulfillment_quantity ?? '') : ($saved->get($line->item_id)?->sent ?? ''), 'received' => $saved->get($line->item_id)?->received ?? ''];
        }

        return $header + ['lines' => $lines, 'shipped_date' => '', 'received_date' => '', 'tracking' => [], 'explanation' => ''];
    }

    private function validate(object $row, array $data, string $mode, bool $final): array
    {
        $expected = DB::table('relocation_request_lines')->where('relocation_request_id', $row->id)->orderBy('item_id')->get()->keyBy('item_id');
        $input = $data['lines'] ?? [];
        if (! is_array($input) || count($input) > 1000 || array_diff(array_map('strval', array_keys($input)), array_map('strval', $expected->keys()->all())) !== []) {
            throw ValidationException::withMessages(['lines' => 'Use only this request’s saved catalog lines.']);
        }
        $saved = DB::table('relocation_fulfillment_lines')->where('relocation_request_id', $row->id)->get()->keyBy('item_id');
        $lines = [];
        $discrepancy = false;
        foreach ($expected as $id => $line) {
            if (isset($input[$id]) && ! is_array($input[$id])) {
                throw ValidationException::withMessages(['lines.'.$id => 'Use the sent or received count field for this saved item.']);
            }
            $value = $input[$id][$mode === 'shipment' ? 'sent' : 'received'] ?? '';
            $quantity = RequestValues::quantity($value, 'lines.'.$id, true);
            if ($mode === 'shipment') {
                $lines[$id] = ['sent' => $final ? (int) ($quantity ?? 0) : $quantity, 'received' => null];
            } else {
                $sent = $saved->get($id)?->sent;
                abort_unless($sent !== null, 409, 'Recorded shipment quantities need inspection.');
                $lines[$id] = ['sent' => (int) $sent, 'received' => $final ? (int) ($quantity ?? 0) : $quantity];
                $discrepancy = $discrepancy || (int) ($quantity ?? 0) !== (int) $sent;
            }
        }
        if ($final && $mode === 'shipment' && array_sum(array_column($lines, 'sent')) === 0) {
            throw ValidationException::withMessages(['lines' => 'Ship at least one actual unit. Sent quantities may differ from requested.']);
        }
        if (! $row->source_location_id || ! $row->destination_location_id || $row->source_location_id === $row->destination_location_id) {
            throw ValidationException::withMessages(['locations' => 'Use distinct valid source and destination storage locations.']);
        }
        foreach (['source_location_id', 'destination_location_id'] as $field) {
            RequestValues::association('storage_locations', (int) $row->{$field}, (int) $row->{$field}, $field);
        }
        $date = RequestStock::date($data[$mode === 'shipment' ? 'shipped_date' : 'received_date'] ?? null, $mode === 'shipment' ? 'shipped_date' : 'received_date', ! $final);

        return ['lines' => $lines, 'date' => $date, 'discrepancy' => $discrepancy, 'explanation' => RequestValues::text($data['explanation'] ?? null, 'explanation')];
    }

    public function work(int $actorId, int $id, mixed $revision, array $data, string $mode, string $action, ?string $operation = null): array
    {
        abort_unless(in_array($mode, ['shipment', 'receipt'], true) && in_array($action, ['save', 'review', 'confirm'], true), 422);
        if ($action === 'confirm') {
            abort_unless(is_string($operation) && Str::isUuid($operation), 422);
        }

        return DB::transaction(function () use ($actorId, $id, $revision, $data, $mode, $action, $operation) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            $row = DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            $manager = RequestValues::manages($actor, 'manager');
            abort_unless($mode === 'shipment' ? $manager : ($manager || (int) $row->owner_id === $actorId), 403);
            $header = RequestStock::header('relocation', $id);
            if ($action === 'confirm' && ($header[$mode.'_operation'] ?? null) === $operation) {
                abort_unless(($header[$mode.'_actor'] ?? null) === $actorId, 403);

                return ['duplicate' => true];
            }
            abort_unless($mode === 'shipment' ? $row->status === 'Requested' : in_array($row->status, ['Shipped', 'Receiving'], true), 409);
            RequestValues::revision($revision, $row);
            $clean = $this->validate($row, $data, $mode, $action !== 'save');
            if ($mode === 'receipt' && $action === 'confirm' && $clean['discrepancy']) {
                abort_unless($manager, 403, 'A Manager or Admin must confirm a permanent shortage or overage.');
            }
            if ($action === 'review') {
                return $clean;
            }
            if ($action === 'confirm') {
                $stock = app(RequestStock::class);
                $items = $stock->items(array_keys($clean['lines']));
                $source = DB::table('storage_locations')->where('id', $row->source_location_id)->value('name');
                $destination = DB::table('storage_locations')->where('id', $row->destination_location_id)->value('name');
                foreach ($clean['lines'] as $itemId => $line) {
                    $item = $items[$itemId];
                    $sent = (int) ($line['sent'] ?? 0);
                    if ($mode === 'shipment') {
                        if ($sent > 0) {
                            $stock->storage($itemId, (int) $row->source_location_id, -$sent);
                            $stock->source($itemId, 'transit', $sent);
                            $stock->entry($actor, 'relocation', $id, $item, $operation, 'shipment', 'Relocation: '.$source.' → In Transit', $sent, $item->unit_cost);
                        }
                    } else {
                        $received = (int) ($line['received'] ?? 0);
                        $transfer = min($sent, $received);
                        $stock->source($itemId, 'transit', -$sent);
                        $stock->storage($itemId, (int) $row->destination_location_id, $received);
                        if ($transfer > 0) {
                            $stock->entry($actor, 'relocation', $id, $item, $operation, 'receipt_transfer', 'Relocation: In Transit → '.$destination, $transfer, $item->unit_cost);
                        }
                        if ($received !== $sent) {
                            $stock->entry($actor, 'relocation', $id, $item, $operation, 'discrepancy', $received < $sent ? 'Adjustment: In Transit shortage' : 'Adjustment: '.$destination.' overage', $received - $sent, $item->unit_cost, $clean['explanation']);
                        }
                    }
                }
                $header[$mode.'_operation'] = $operation;
                $header[$mode.'_actor'] = $actorId;
            }
            foreach ($clean['lines'] as $item => $line) {
                if ($mode === 'shipment') {
                    // Both pre-shipment edit surfaces share this one authority.
                    DB::table('relocation_request_lines')->where('relocation_request_id', $id)->where('item_id', $item)->update(['fulfillment_quantity' => $line['sent']]);
                    if ($action === 'save') {
                        $line['sent'] = null; // Only confirmed sent quantities belong in final fulfillment lines.
                    }
                }
                DB::table('relocation_fulfillment_lines')->updateOrInsert(['relocation_request_id' => $id, 'item_id' => $item], $line);
            }
            $header[$mode === 'shipment' ? 'shipped_date' : 'received_date'] = $clean['date'];
            $header['explanation'] = $clean['explanation'];
            RequestStock::saveHeader('relocation', $id, $header);
            $next = $action === 'confirm' ? ($mode === 'shipment' ? 'Shipped' : 'Complete') : ($mode === 'receipt' ? 'Receiving' : 'Requested');
            RequestStock::changed('relocation', $row, $actor, $action === 'confirm' ? 'Confirmed '.$mode : 'Saved '.$mode.' counts', $clean, $next);

            return $clean;
        }, 3);
    }

    public function tracking(int $actorId, int $id, mixed $revision, array $tracking): void
    {
        if (count($tracking) > 50) {
            throw ValidationException::withMessages(['tracking' => 'Use up to 50 tracking lines.']);
        }
        $clean = [];
        foreach ($tracking as $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(['tracking' => 'Use carrier and tracking number fields.']);
            }
            $number = RequestValues::text($line['number'] ?? null, 'number', 255);
            if ($number === null) {
                continue;
            }
            $carrier = $line['carrier'] ?? '';
            if (! in_array($carrier, ['USPS', 'UPS', 'FedEx', 'DHL', 'Other'], true)) {
                throw ValidationException::withMessages(['tracking' => 'Choose a supported carrier or Other.']);
            }
            $url = $carrier === 'Other' ? RequestValues::text($line['url'] ?? null, 'tracking_url', 255) : null;
            if ($url !== null && (! filter_var($url, FILTER_VALIDATE_URL) || strtolower(parse_url($url, PHP_URL_SCHEME) ?? '') !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
                throw ValidationException::withMessages(['tracking' => 'Other tracking links must be HTTPS without embedded credentials.']);
            }
            $clean[] = ['carrier' => $carrier, 'number' => $number, 'url' => $url];
        }
        DB::transaction(function () use ($actorId, $id, $revision, $clean) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            $row = DB::table('relocation_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless(RequestValues::manages($actor, 'manager') && in_array($row->status, ['Requested', 'Shipped', 'Receiving', 'Complete'], true), 403);
            RequestValues::revision($revision, $row);
            $header = RequestStock::header('relocation', $id);
            if (($header['tracking'] ?? []) === $clean) {
                return;
            }
            $previous = $header['tracking'] ?? [];
            $header['tracking'] = $clean;
            RequestStock::saveHeader('relocation', $id, $header);
            RequestStock::changed('relocation', $row, $actor, 'Shipping information changed', ['previous_tracking' => $previous, 'tracking' => $clean]);
        }, 3);
    }

    public static function trackingUrl(array $line): ?string
    {
        $number = rawurlencode($line['number']);

        return match ($line['carrier']) {
            'USPS' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels='.$number,
            'UPS' => 'https://www.ups.com/track?tracknum='.$number,
            'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr='.$number,
            'DHL' => 'https://www.dhl.com/us-en/home/tracking.html?tracking-id='.$number,
            default => $line['url'],
        };
    }
}
