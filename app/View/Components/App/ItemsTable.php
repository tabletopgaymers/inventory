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
