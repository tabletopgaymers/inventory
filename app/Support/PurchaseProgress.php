<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseProgress
{
    public static function fields(array $data, string $context): array
    {
        $names = match ($context) {
            'request', 'work' => ['title', 'suggested_merchant', 'details'],
            'planning-work' => ['title', 'suggested_merchant', 'details', 'supplier_id', 'receiving_location_id', 'planned_date', 'preparation_notes'],
            'preparation' => ['supplier_id', 'receiving_location_id', 'planned_date', 'preparation_notes'],
            default => ['title', 'supplier_id', 'receiving_location_id', 'ordered_date', 'shipped_date', 'received_date', 'other_fees', 'discount', 'tax', 'shipping', 'shipping_method'],
        };
        $fields = [];
        foreach ($names as $name) {
            $fields[$name] = (string) ($data[$name] ?? '');
        }
        if ($context !== 'request') {
            $lines = $data['lines'] ?? [];
            if (in_array($context, ['work', 'planning-work'], true)) {
                ksort($lines, SORT_NUMERIC);
            }
            foreach ($lines as $id => $line) {
                foreach ($context === 'work' ? ['quantity'] : (in_array($context, ['preparation', 'planning-work'], true) ? ['quantity', 'estimate', 'note'] : ['quantity', 'unit', 'cost', 'fee', 'basis']) as $name) {
                    $fields['lines['.$id.']['.$name.']'] = (string) ($line[$name] ?? ($name === 'basis' ? 'unit' : ''));
                }
            }
        }

        return $fields;
    }

    // A bounded raw invoice draft; complete review/confirmation keeps its existing validator.
    public static function invoice(array $data): array
    {
        $clean = [];
        foreach (['title', 'supplier_id', 'receiving_location_id', 'ordered_date', 'shipped_date', 'received_date', 'other_fees', 'discount', 'tax', 'shipping', 'shipping_method'] as $name) {
            $value = $data[$name] ?? '';
            if ($value !== null && ! is_string($value) && ! is_int($value)) {
                throw ValidationException::withMessages([$name => 'Enter a single value.']);
            }
            $value = (string) ($value ?? '');
            if (mb_strlen($value) > 255) {
                throw ValidationException::withMessages([$name => 'Use at most 255 characters.']);
            }
            $clean[$name] = $value;
        }
        if (! in_array($clean['shipping_method'], ['line_total', 'quantity'], true)) {
            throw ValidationException::withMessages(['shipping_method' => 'Choose By line total or By quantity.']);
        }
        $input = $data['lines'] ?? [];
        if (! is_array($input) || count($input) > 1000) {
            throw ValidationException::withMessages(['lines' => 'Use up to 1,000 catalog lines.']);
        }
        $clean['lines'] = [];
        foreach ($input as $id => $line) {
            $item = RequestValues::id($id, 'lines');
            if (! $item || ! is_array($line)) {
                throw ValidationException::withMessages(['lines' => 'Choose catalog items for the invoice draft.']);
            }
            foreach (['quantity', 'unit', 'cost', 'fee', 'basis'] as $name) {
                $value = $line[$name] ?? ($name === 'basis' ? 'unit' : '');
                if ($value !== null && ! is_string($value) && ! is_int($value)) {
                    throw ValidationException::withMessages(['lines.'.$item.'.'.$name => 'Enter a single value.']);
                }
                $value = (string) ($value ?? '');
                if (mb_strlen($value) > 255 || ($name === 'basis' && ! in_array($value, ['unit', 'cost'], true))) {
                    throw ValidationException::withMessages(['lines.'.$item.'.'.$name => 'Enter a valid bounded value.']);
                }
                $clean['lines'][$item][$name] = $value;
            }
        }
        if (strlen(json_encode($clean, JSON_THROW_ON_ERROR)) > 250000) {
            throw ValidationException::withMessages(['lines' => 'The invoice draft is too large.']);
        }

        return $clean;
    }

    public static function workHash(array $draft): string
    {
        return hash('sha256', json_encode(self::fields($draft['values'], 'work'), JSON_THROW_ON_ERROR));
    }

    public static function workDirty(array $draft): bool
    {
        return self::fields($draft['values'], $draft['preparation'] ? 'planning-work' : 'work') !== $draft['saved_fields'] || (bool) array_filter($draft['selection'] ?? [], fn ($value) => $value !== null && $value !== '');
    }

    public static function navigation(User $actor, ?object $row, string $active, ?string $token = null): array
    {
        $submitted = $row && $row->status !== 'Draft';
        if ($row?->status === 'Cancelled') {
            $submitted = DB::table('purchase_request_activity')->where('purchase_request_id', $row->id)->get(['previous', 'current'])->contains(function ($entry) {
                foreach ([$entry->previous, $entry->current] as $value) {
                    if (in_array(json_decode($value ?? '{}', true)['status'] ?? null, ['Request', 'Ordered', 'Shipped', 'Received'], true)) {
                        return true;
                    }
                }

                return false;
            });
            $active = '';
        }
        $steps = $submitted ? ['request', 'items', 'review', 'preparation', 'order', 'receive', 'complete']
            : array_slice(['request', 'items', 'review', 'preparation', 'order'], 0, match ($active) { 'preparation' => 4, 'order' => 5, default => 3 });
        $links = [];
        if ($row) {
            $base = '/purchases/'.$row->id;
            $links = ['request' => app(PurchaseRequests::class)->editable($actor, $row) ? $base.'/edit' : $base.'#purchase-request',
                'items' => app(PurchaseRequests::class)->editable($actor, $row) ? $base.'/edit?step=items' : $base.'#purchase-items', 'review' => $base.'#purchase-review', 'preparation' => $base.'/preparation'];
            if (app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady']) {
                $links['order'] = $base.'/fulfillment/order?view=1';
                $links['receive'] = $base.'/fulfillment/receipt?view=1';
            }
        }
        if ($token && in_array($active, ['request', 'items', 'review'], true)) {
            $links = ['request' => '/purchases/work/'.$token, 'items' => '/purchases/work/'.$token.'?step=items'];
        }
        $position = array_search($active, $steps, true);

        return array_map(fn ($step) => ['step' => $step, 'active' => $step === $active,
            'href' => $position !== false && array_search($step, $steps, true) < $position ? ($links[$step] ?? null) : null], $steps);
    }
}
