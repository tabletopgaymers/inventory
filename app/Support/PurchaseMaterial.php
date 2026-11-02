<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Request estimates only. This class never writes catalog, invoice or inventory data. */
class PurchaseMaterial
{
    public static function load(?int $id, array $quantities): array
    {
        $result = ['lines' => $quantities, 'estimates' => [], 'material_names' => [], 'other_lines' => []];
        $rows = $id ? DB::table('purchase_material_lines')->where('purchase_request_id', $id)->orderBy('id')->get() : collect();
        if ($rows->isNotEmpty()) {
            $result['lines'] = [];
            foreach ($rows as $row) {
                $quantity = $row->quantity === null ? '' : (string) $row->quantity;
                $unit = $row->estimated_unit_price === null ? '' : (string) BigDecimal::of($row->estimated_unit_price)->strippedOfTrailingZeros();
                if ($row->item_id) {
                    $result['lines'][$row->item_id] = $quantity;
                    $result['estimates'][$row->item_id] = $unit;
                    $result['material_names'][$row->item_id] = $row->description;
                } else $result['other_lines'][$row->line_key] = ['description' => $row->description, 'quantity' => $quantity, 'unit' => $unit];
            }
        } else {
            foreach (DB::table('items')->whereIn('id', array_keys($quantities))->get(['id','name','unit_cost']) as $item) {
                $result['material_names'][$item->id] = $item->name;
                $result['estimates'][$item->id] = BigDecimal::of($item->unit_cost)->isPositive() ? (string) BigDecimal::of($item->unit_cost)->strippedOfTrailingZeros() : '';
            }
        }

        return $result;
    }

    public static function price(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;
        $value = trim((string) $value);
        if (str_starts_with($value, '.')) $value = '0'.$value;
        if (! preg_match('/\A\d{1,12}(?:\.\d{1,12})?\z/D', $value)) {
            throw ValidationException::withMessages([$field => 'Enter a nonnegative estimated unit price, up to 12 decimal places, or leave it blank.']);
        }

        return (string) BigDecimal::of($value)->strippedOfTrailingZeros();
    }

    public static function validate(array $work): void
    {
        if (count($work['lines']) + count($work['other_lines']) > 1000) throw ValidationException::withMessages(['lines' => 'Use up to 1,000 material lines.']);
        foreach ($work['lines'] as $id => $quantity) {
            RequestValues::quantity($quantity, 'lines.'.$id, true);
            self::price($work['estimates'][$id] ?? '', 'estimates.'.$id);
        }
        foreach ($work['other_lines'] as $key => $line) {
            if (! Str::isUuid($key)) throw ValidationException::withMessages(['other_lines' => 'Invalid Other item. Reopen the request.']);
            RequestValues::quantity($line['quantity'] ?? '', 'other_lines.'.$key.'.quantity', true);
            self::price($line['unit'] ?? '', 'other_lines.'.$key.'.unit');
        }
    }

    public static function submissionErrors(array $work): array
    {
        $errors = [];
        if (count($work['lines'] ?? []) + count($work['other_lines'] ?? []) === 0) $errors['lines'] = ['Add at least one material item before submitting.'];
        foreach ($work['lines'] ?? [] as $key => $value) {
            try { RequestValues::quantity($value, 'lines.'.$key); }
            catch (ValidationException $e) { $errors += $e->errors(); }
        }
        foreach ($work['other_lines'] ?? [] as $key => $line) {
            try { RequestValues::quantity($line['quantity'] ?? null, 'other_lines.'.$key.'.quantity'); }
            catch (ValidationException $e) { $errors += $e->errors(); }
            try { RequestValues::text($line['description'] ?? null, 'other_lines.'.$key.'.description', 500, true); }
            catch (ValidationException $e) { $errors += $e->errors(); }
        }
        return $errors;
    }

    public static function save(int $id, array $work): void
    {
        $rows = [];
        foreach ($work['lines'] as $item => $quantity) {
            $rows['catalog-'.$item] = ['item_id' => (int) $item, 'description' => $work['material_names'][$item] ?? DB::table('items')->where('id', $item)->value('name'),
                'quantity' => RequestValues::quantity($quantity, 'lines.'.$item, true), 'estimated_unit_price' => self::price($work['estimates'][$item] ?? '', 'estimates.'.$item)];
        }
        foreach ($work['other_lines'] as $key => $line) {
            $rows[$key] = ['item_id' => null, 'description' => trim($line['description'] ?? ''),
                'quantity' => RequestValues::quantity($line['quantity'] ?? '', 'other_lines.'.$key.'.quantity', true), 'estimated_unit_price' => self::price($line['unit'] ?? '', 'other_lines.'.$key.'.unit')];
        }
        DB::table('purchase_material_lines')->where('purchase_request_id', $id)->whereNotIn('line_key', array_keys($rows))->delete();
        foreach ($rows as $key => $row) DB::table('purchase_material_lines')->updateOrInsert(['purchase_request_id' => $id, 'line_key' => $key], $row);
    }
}
