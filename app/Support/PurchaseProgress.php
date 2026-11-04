<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PurchaseProgress
{
    // A bounded raw invoice draft; complete review/confirmation keeps its existing validator.
    public static function invoice(array $data): array
    {
        $clean = [];
        foreach (['title', 'supplier_id', 'receiving_location_id', 'ordered_date', 'shipped_date', 'received_date', 'other_fees', 'discount', 'tax', 'shipping', 'shipping_method', ...PurchaseProcurement::FIELDS] as $name) {
            $value = $data[$name] ?? '';
            if ($value !== null && ! is_string($value) && ! is_int($value)) {
                throw ValidationException::withMessages([$name => 'Enter a single value.']);
            }
            $value = (string) ($value ?? '');
            if (mb_strlen($value) > ($name === 'delivery_address' ? 5000 : 255)) {
                throw ValidationException::withMessages([$name => 'This value is too long.']);
            }
            $clean[$name] = $value;
        }
        if (! in_array($clean['shipping_method'], ['line_total', 'quantity'], true)) {
            throw ValidationException::withMessages(['shipping_method' => 'Choose By line total or By quantity.']);
        }
        $input = $data['lines'] ?? [];
        if (! is_array($input) || count($input) > 1000) {
            throw ValidationException::withMessages(['lines' => 'Use up to 1,000 invoice lines.']);
        }
        $clean['lines'] = [];
        $clean['lines_initialized'] = true;
        if (array_key_exists('source_order_operation',$data)) {
            if (!is_string($data['source_order_operation']) || ($data['source_order_operation']!=='' && !\Illuminate\Support\Str::isUuid($data['source_order_operation']))) throw ValidationException::withMessages(['source_order_operation'=>'Reconcile this receipt with the recorded order before saving.']);
            $clean['source_order_operation']=$data['source_order_operation'];
        }
        foreach ($input as $id => $line) {
            if (!is_array($line)) throw ValidationException::withMessages(['lines' => 'Enter invoice rows.']);
            $identity = PurchaseInvoiceLines::identity($id, $line);
            $item = $identity['line_key'];
            $clean['lines'][$item] = $identity;
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
        PurchaseInvoiceLines::assertUnique($clean['lines']);
        if (strlen(json_encode($clean, JSON_THROW_ON_ERROR)) > 250000) {
            throw ValidationException::withMessages(['lines' => 'The invoice draft is too large.']);
        }

        return $clean;
    }

}
