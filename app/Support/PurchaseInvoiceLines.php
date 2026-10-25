<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stable row identity is separate from the optional catalog association. */
class PurchaseInvoiceLines
{
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
            'group_label' => $record ? ($category?->name ?? 'Catalog').' : '.($collection?->name ?? 'Items') : 'Other items',
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
