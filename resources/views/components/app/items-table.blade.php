{{--
Use this for a read-only item table with standard headings, collection subheadings, and an empty state. Place it inside `x-app.table-region` when it needs a panel, scrolling, or actions above or below it. Use the status column role to show the shared badge for a status string.

## Slots

The Items Table takes its content from the constructor inputs. Use the surrounding table region’s slots for supplemental content.

## Examples

See [Presentation → Shared Items Table](/style-guide/presentation#items-table) or [Location Inventory](resources/views/inventory/location.blade.php).
--}}<table {{ $attributes->class(['app-items-table']) }}>
    <colgroup>
        @foreach($columns as $column)
            <col @class(['table-quantity-column' => in_array($column['role'] ?? null, ['sku', 'number', 'status'], true)])>
        @endforeach
    </colgroup>
    <thead>
        <tr>
            @foreach($columns as $column)
                @php($role = $column['role'] ?? 'text')
                <th scope="col" @class(['app-items-table-text' => in_array($role, ['item', 'text'], true), 'number' => $role === 'number', 'app-items-table-compact' => in_array($role, ['sku', 'status'], true)])>
                    <span class="app-items-table-heading">
                        @if(isset($column['link']))
                            <a href="{{ $column['link']['href'] }}" {{ new \Illuminate\View\ComponentAttributeBag($column['link']['attributes'] ?? []) }}>{{ $column['label'] }}</a>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </span>
                </th>
            @endforeach
        </tr>
    </thead>
    @forelse($sections as $section)
        <tbody>
            @if($group = $section['group'])
                <tr class="group-row">
                    <th scope="rowgroup" colspan="{{ count($columns) }}">
                        @if($section['total'] !== null)
                            <x-app.heading-row variant="table">
                                <span>{{ $group['category'] }} : {{ $group['collection'] }}</span>
                                <span>{{ number_format(count($section['rows'])) }}/{{ number_format($section['total']) }} items</span>
                            </x-app.heading-row>
                        @else
                            {{ $group['category'] }} : {{ $group['collection'] }}
                        @endif
                    </th>
                </tr>
            @endif
            @foreach($section['rows'] as $row)
                <tr>
                    @foreach($columns as $column)
                        @php($role = $column['role'] ?? 'text')
                        <td @class(['app-items-table-text' => in_array($role, ['item', 'text'], true), 'app-grouped-item' => $role === 'item' && $group, 'number' => $role === 'number', 'app-items-table-compact' => in_array($role, ['sku', 'status'], true)])>
                            @if($role === 'item' && isset($row['item']['link']))
                                <a href="{{ $row['item']['link']['href'] }}" {{ new \Illuminate\View\ComponentAttributeBag($row['item']['link']['attributes'] ?? []) }}>{{ $displayValue($row, $column) }}</a>
                            @elseif($role === 'status' && is_string($status = $row['values'][$column['key']] ?? null) && trim($status) !== '')
                                <x-app.status-badge :status="$status" />
                            @else
                                {{ $displayValue($row, $column) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    @empty
        <tbody><tr><td colspan="{{ count($columns) }}">{{ $emptyMessage }}</td></tr></tbody>
    @endforelse
</table>
