<?php

namespace App\View\Components\App;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ItemsTable extends Component
{
    public const DEFAULT_COLUMNS = [
        ['key' => 'name', 'label' => 'Item', 'role' => 'item'],
        ['key' => 'sku', 'label' => 'SKU', 'role' => 'sku'],
    ];

    public array $sections = [];

    /**
     * Supply prepared item rows in display order. Adjacent rows sharing a group key form a section.
     * Use the default Item and SKU columns or pass the complete ordered column list.
     * Integer number values use quantity formatting. Supply preformatted text when a value needs another format.
     *
     * @param iterable $rows Each row supplies item.name, optional item.sku and item.link, optional group with key, category and collection, and custom values indexed by column key.
     * @param array $columns Each column has a key and label. Its role can be item, sku, number, text, or status. For status, provide the status string in the row values. Missing values use the ordinary text placeholders. Optional link contains href and attributes. nullDisplay, blankDisplay, and zeroDisplay customize value placeholders.
     * @param string $emptyMessage Text shown when the input has no rows.
     * @param array $collectionTotals Totals keyed by group key. A supplied total displays the section row count against that total.
     * @example app/Http/Controllers/CatalogController.php
     */
    public function __construct(
        iterable $rows,
        public array $columns = self::DEFAULT_COLUMNS,
        public string $emptyMessage = 'No items to display.',
        array $collectionTotals = [],
    ) {
        foreach ($rows as $row) {
            $group = $row['group'] ?? null;
            $key = $group['key'] ?? null;
            $last = array_key_last($this->sections);
            if ($last === null || $this->sections[$last]['key'] !== $key) {
                $this->sections[] = [
                    'key' => $key,
                    'group' => $group,
                    'total' => $key === null ? null : ($collectionTotals[$key] ?? null),
                    'rows' => [],
                ];
                $last = array_key_last($this->sections);
            }
            $this->sections[$last]['rows'][] = $row;
        }
    }

    public function displayValue(array $row, array $column): mixed
    {
        $role = $column['role'] ?? 'text';
        $value = match ($role) {
            'item' => $row['item']['name'],
            'sku' => $row['item']['sku'] ?? null,
            default => $row['values'][$column['key']] ?? null,
        };

        if ($value === null) {
            return $column['nullDisplay'] ?? '';
        }
        if ($value === '') {
            return $column['blankDisplay'] ?? '';
        }
        if ($role === 'number' && is_int($value)) {
            return $value === 0 && isset($column['zeroDisplay']) ? $column['zeroDisplay'] : number_format($value);
        }

        return $value;
    }

    public function render(): View
    {
        return view('components.app.items-table');
    }
}
