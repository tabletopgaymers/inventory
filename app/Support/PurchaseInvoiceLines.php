<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Stable row identity is separate from the optional catalog association. */
class PurchaseInvoiceLines
{
    public static function requested(int $id): array
    {
        $lines = [];
        foreach (DB::table('purchase_material_lines')->where('purchase_request_id',$id)->orderBy('id')->get() as $row) {
            $unit = $row->estimated_unit_price === null ? '' : (string)BigDecimal::of($row->estimated_unit_price)->strippedOfTrailingZeros();
            $quantity = $row->quantity === null ? '' : (string)$row->quantity;
            $lines[$row->line_key] = self::identity($row->line_key,['item_id'=>$row->item_id,'description'=>$row->description,'source_request_key'=>$row->line_key])
                + ['quantity'=>$quantity,'unit'=>$unit,'cost'=>$unit !== '' && $quantity !== '' ? (string)BigDecimal::of($unit)->multipliedBy($quantity)->toScale(2,RoundingMode::HalfUp) : '', 'fee'=>'0.00','basis'=>'unit'];
        }
        if ($lines === []) {
            foreach (DB::table('purchase_request_lines')->where('purchase_request_id',$id)->orderBy('id')->get() as $row) {
                $key='catalog-'.$row->item_id;
                $cost=$row->estimate === null ? '' : (string)BigDecimal::of($row->estimate)->toScale(2,RoundingMode::HalfUp);
                $unit=$cost !== '' && $row->quantity > 0 ? (string)BigDecimal::of($cost)->dividedBy($row->quantity,12,RoundingMode::HalfUp) : '';
                $lines[$key]=self::identity($key,['item_id'=>$row->item_id,'source_request_key'=>$key]) + ['quantity'=>(string)$row->quantity,'unit'=>$unit,'cost'=>$cost,'fee'=>'0.00','basis'=>'unit'];
            }
        }
        return $lines;
    }

    public static function rows(array $lines): array
    {
        $rows=[];
        foreach ($lines as $key=>$line) {
            $identity=self::identity($key,$line);
            $rows[]=['id'=>$key,'name'=>$identity['description'],'group'=>$line['group_label'] ?? $identity['group_label'],'item_id'=>$identity['item_id']];
        }
        usort($rows,fn($a,$b)=>strnatcasecmp($a['group'],$b['group']) ?: strnatcasecmp($a['name'],$b['name']));
        return $rows;
    }

    public static function identity(int|string $key, array $line): array
    {
        $key = (string) $key;
        if (!preg_match('/\A[A-Za-z0-9-]{1,50}\z/D', $key)) {
            throw ValidationException::withMessages(['lines' => 'Invalid invoice line identity. Reopen this purchase.']);
        }
        $item = RequestValues::id(array_key_exists('item_id', $line) ? $line['item_id'] : (ctype_digit($key) ? $key : null), 'lines.'.$key.'.item_id');
        $record = $item ? DB::table('items')->where('id', $item)->first() : null;
        if ($item && !$record) throw ValidationException::withMessages(['lines.'.$key.'.item_id' => 'Choose an existing catalog item.']);
        $collection = $record ? DB::table('collections')->find($record->collection_id) : null;
        $category = $collection ? DB::table('categories')->find($collection->category_id) : null;
        return ['line_key' => $key, 'item_id' => $item,
            'description' => RequestValues::text($line['description'] ?? $record?->name ?? '', 'lines.'.$key.'.description', 500) ?? '',
            'group_label' => RequestValues::text($line['group_label'] ?? ($record ? ($category?->name ?? 'Catalog').' : '.($collection?->name ?? 'Items') : 'Other items'), 'lines.'.$key.'.group_label', 600) ?? 'Other items',
            'source_request_key' => RequestValues::text($line['source_request_key'] ?? null, 'lines.'.$key.'.source_request_key', 50)];
    }

    public static function assertUnique(array $lines): void
    {
        $seen = [];
        foreach ($lines as $key => $line) {
            if (!$line['item_id']) continue;
            if (isset($seen[$line['item_id']])) throw ValidationException::withMessages(['lines.'.$key.'.item_id' => 'Each catalog item may appear only once.']);
            $seen[$line['item_id']] = true;
        }
    }
}
