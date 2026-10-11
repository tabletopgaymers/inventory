<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RelocationProgress
{
    public static function snapshot(array $draft): array
    {
        $values = [];
        foreach (['title', 'details', 'source_location_id', 'destination_location_id'] as $field) {
            $values[$field] = (string) ($draft[$field] ?? '');
        }
        $values['lines'] = array_map(fn ($value) => (string) ($value ?? ''), $draft['lines'] ?? []);
        ksort($values['lines'], SORT_NUMERIC);

        return $values;
    }

    public static function dirty(array $draft): bool
    {
        return self::snapshot($draft) !== ($draft['saved_values'] ?? self::snapshot($draft))
            || (bool) array_filter($draft['selection'] ?? [], fn ($value) => $value !== null && $value !== '');
    }

    public static function counts(array $data, string $mode): array
    {
        $field = $mode === 'shipment' ? 'sent' : 'received';
        $lines = [];
        foreach ($data['lines'] ?? [] as $id => $line) {
            $lines[$id] = (string) ($line[$field] ?? '');
        }
        ksort($lines, SORT_NUMERIC);

        return ['date' => (string) ($data[$mode === 'shipment' ? 'shipped_date' : 'received_date'] ?? ''), 'explanation' => (string) ($data['explanation'] ?? ''), 'lines' => $lines];
    }

    public static function navigation(User $actor, ?object $row, string $context, ?string $active = null, ?string $token = null, bool $readOnly = false): array
    {
        $submitted = $row && $row->status !== 'Draft';
        if ($row?->status === 'Cancelled') {
            $submitted = DB::table('relocation_request_activity')->where('relocation_request_id', $row->id)->get(['previous', 'current'])
                ->contains(function ($entry) {
                    foreach ([$entry->previous, $entry->current] as $value) {
                        if (in_array(json_decode($value ?? '{}', true)['status'] ?? null, ['Requested', 'Shipped', 'Receiving', 'Complete'], true)) {
                            return true;
                        }
                    }

                    return false;
                });
            $active = null;
        } elseif ($active === null && $row) {
            $active = match ($row->status) {
                'Draft' => 'review', 'Requested' => 'fulfillment', 'Shipped', 'Receiving' => 'receive', 'Complete' => 'complete', default => null,
            };
        }
        if ($context === 'index') {
            $active = null;
        }
        $labels = $submitted ? ['locations', 'items', 'review', 'fulfillment', 'ship', 'receive', 'complete'] : ['locations', 'items', 'review'];
        $links = [];
        $actions = [];
        if (in_array($context, ['work', 'review'], true) && $token) {
            if ($active !== 'locations') {
                $links['locations'] = '/relocations/work/'.$token;
                $actions['locations'] = 'locations';
            }
            if (in_array($active, ['review', 'fulfillment'], true)) {
                $links['items'] = '/relocations/work/'.$token.'?step=items';
                $actions['items'] = 'items';
            }
            if ($submitted && $active === 'fulfillment') {
                // Submitted intake review is a saved, read-only section, not a new submission.
                $links['review'] = '/relocations/'.$row->id.'#relocation-review';
            }
        } elseif ($row) {
            $base = '/relocations/'.$row->id;
            $links = ['locations' => $base.'#relocation-locations', 'items' => $base.'#relocation-items', 'review' => $base.'#relocation-review', 'fulfillment' => $base.'#relocation-fulfillment'];
            if ($context === 'show' && $row->status === 'Draft' && ! $readOnly && app(RelocationRequests::class)->editable($actor, $row)) {
                $links['locations'] = $base.'/edit';
                $links['items'] = $base.'/edit?step=items';
            }
            $fulfillmentReady = app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'];
            if ($fulfillmentReady && in_array($row->status, ['Shipped', 'Receiving', 'Complete'], true)) {
                $links['ship'] = $base.'/fulfillment/shipment?view=1';
            }
            if ($fulfillmentReady && $row->status === 'Complete') {
                $links['receive'] = $base.'/fulfillment/receipt?view=1';
            }
        }
        $position = array_search($active, $labels, true);

        return array_map(fn ($step) => ['step' => $step, 'active' => $step === $active,
            'href' => $position !== false && array_search($step, $labels, true) < $position ? ($links[$step] ?? null) : null,
            'action' => $actions[$step] ?? null], $labels);
    }
}
