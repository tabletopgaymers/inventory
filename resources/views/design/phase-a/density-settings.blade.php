<x-app.table-region label="Display density spacing comparison" class="app-list-table-panel">
<table><thead><tr><th>Setting</th><th>Compact</th><th>Standard</th><th>Spacious</th></tr></thead><tbody>
@foreach([
    'panel' => 'Panel padding (current viewport)',
    '--app-density-panel-y' => 'Panel vertical padding — desktop',
    '--app-density-panel-mobile-y' => 'Panel vertical padding — narrow screen',
    'body' => 'Body font size / line height',
    '--app-density-paragraph-y' => 'Paragraph vertical margin',
    '--app-density-field-y' => 'Field vertical margin',
    '--app-density-input-y' => 'Input/select vertical padding',
    '--app-density-table-y' => 'Table-cell vertical padding',
    '--app-density-catalog-y' => 'Catalog-cell vertical padding',
    '--app-density-detail-gap' => 'Detail-list vertical gap',
    '--app-grouped-item-padding' => 'Grouped name left padding',
    'button' => 'Button padding',
    'primary' => 'Primary color',
    'page' => 'Page background',
    'surface' => 'Panel background',
] as $key => $label)
<tr><th scope="row">{{ $label }}</th>@foreach(['compact', 'standard', 'spacious'] as $density)<td data-guide-density="{{ $density }}" data-guide-measure="{{ $key }}">Loading styles…</td>@endforeach</tr>
@endforeach
</tbody></table>
</x-app.table-region>
