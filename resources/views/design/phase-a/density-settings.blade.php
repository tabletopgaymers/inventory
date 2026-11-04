<x-app.table-region label="Display density spacing comparison" class="app-list-table-panel">
<x-slot:header><p>Rows marked <strong>Fixed</strong> do not change with Display Density.</p></x-slot:header>
<table><thead><tr><th>Setting</th><th>Compact</th><th>Standard</th><th>Spacious</th></tr></thead><tbody>
@foreach([
    'Panels' => [
        '--app-section-gap' => 'Panel / section gap',
        'panel' => 'Panel padding (current viewport)',
        '--app-density-panel-y' => 'Panel vertical padding — desktop',
        '--app-density-panel-mobile-y' => 'Panel vertical padding — narrow screen',
    ],
    'Columns and flowing fields' => [
        'column-widths' => 'Column minimum widths (small / default / wide)',
        'column-breakpoints' => 'Fixed-column breakpoints',
        '--breakpoint-app-navigation' => 'Application navigation breakpoint',
        'column-gap' => 'Column horizontal gap',
        'flow-gap' => 'Flowing-field horizontal gap',
    ],
    'Headings and text' => [
        'font-family' => 'Font family',
        'body-weight' => 'Body font weight',
        'page-title-weight' => 'Page title font weight',
        'body-size' => 'Body font size',
        'body-line-height' => 'Body line height',
        '--app-density-paragraph-y' => 'Paragraph vertical margin',
        'page-heading-gap' => 'Page heading row gap (vertical / horizontal)',
        'section-heading-gap' => 'Section heading row gap',
        'table-heading-gap' => 'Table toolbar row gap (vertical / horizontal)',
    ],
    'Fields and buttons' => [
        '--app-density-field-y' => 'Field vertical margin',
        '--app-density-input-y' => 'Input/select vertical padding',
        'button' => 'Button padding',
    ],
    'Tables and detail lists' => [
        'table-heading-weight' => 'Table heading font weight',
        'table-group-weight' => 'Table subheading font weight',
        '--app-density-table-y' => 'Table-cell vertical padding',
        '--app-density-catalog-y' => 'Catalog-cell vertical padding',
        '--app-grouped-item-padding' => 'Grouped name left padding',
        '--app-density-detail-gap' => 'Detail-list vertical gap',
    ],
    'Borders and corners' => [
        '--app-radius' => 'Panel corner radius',
        'field-radius' => 'Field corner radius',
    ],
    'Colors' => [
        'text-color' => 'Default text color',
        'muted-color' => 'Secondary text color',
        'border-color' => 'Panel / table border color',
        'danger-color' => 'Error / negative value text',
        'table-heading-color' => 'Table heading background',
        'table-group-color' => 'Table subheading background',
        'primary' => 'Primary color',
        'page' => 'Page background',
        'surface' => 'Panel background',
    ],
    'Detail-list typography' => [
        '--breakpoint-detail-stack' => 'Detail-list stacking breakpoint',
        'detail-label-weight' => 'Detail label font weight',
        'detail-value-style' => 'Detail value typography',
    ],
] as $topic => $settings)
<tr class="group-row"><th colspan="4" scope="rowgroup">{{ $topic }}</th></tr>
@foreach($settings as $key => $label)
<tr><th scope="row">{{ $label }}</th>@foreach(['compact', 'standard', 'spacious'] as $density)<td data-guide-density="{{ $density }}" data-guide-measure="{{ $key }}">Loading styles…</td>@endforeach</tr>
@endforeach
@endforeach
</tbody></table>
</x-app.table-region>
