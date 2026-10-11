<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Packing Worksheet · {{ $row->title }}</title><link rel="stylesheet" href="/worksheet-base.css?v=desktop-800-1"><link rel="stylesheet" href="/requests-print.css"><style>
/* Compact vertical spacing; worksheet columns and controls stay in place. */
:root { line-height: 1.25; }
.packing-worksheet main { margin: .5rem 1rem; padding: .5rem 1rem; }
.packing-worksheet h1 { line-height: 1.25; margin-bottom: .335em; }
.packing-worksheet p { margin-top: .5em; margin-bottom: .5em; }
.packing-worksheet button { padding: .25rem .8rem; }
.packing-worksheet th, .packing-worksheet td { padding: .25rem .5rem; }
.packing-worksheet td.app-grouped-item { padding-left: 1rem; }
.packing-worksheet .packing-sent { height: 1.5rem; }
@media (width < 800px) { .packing-worksheet button { min-height: 2.25rem; } }
@media print { .packing-worksheet main { margin: 0; padding: 0; } }
.packing-worksheet .app-relocation-progress { margin: 0 0 .375rem; }
.packing-worksheet .app-page-heading { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem 1rem; align-items: center; }
.packing-worksheet .app-page-heading h1 { margin: 0; }
.packing-worksheet .app-relocation-steps { display: flex; flex-wrap: wrap; gap: .25rem .5rem; padding: 0; list-style: none; }
.packing-worksheet .app-relocation-steps li + li::before { content: "→"; margin-right: .5rem; }
.packing-worksheet .app-save-progress { background: #572c83; color: white; }
@media print { .packing-worksheet .app-relocation-progress, .packing-worksheet .app-actions, [data-save-feedback] { display: none; } }
</style></head><body class="packing-worksheet"><main class="app-content">@include('requests.relocation-progress', ['progressRow' => $row, 'progressContext' => 'worksheet', 'progressReadOnly' => true, 'progressKey' => 'worksheet:'.$row->id])<div class="app-page-heading"><h1>Packing Worksheet · {{ $row->title }}</h1><div class="app-actions">@yield('relocation-save')</div></div><p>Relocation #{{ $row->id }} · {{ $row->status }} · {{ $locations->firstWhere('id',$row->source_location_id)?->name ?: 'Source not selected' }} → {{ $locations->firstWhere('id',$row->destination_location_id)?->name ?: 'Destination not selected' }}</p><p>Requested individual units and current hypothetical projections. Write actual sent units in the blank Sent column. This worksheet does not record shipment.</p><button class="no-print" data-print>Print worksheet</button><table><colgroup><col style="width:36%"><col style="width:20%"><col style="width:20%"><col style="width:12%"><col style="width:12%"></colgroup><thead><tr><th>Category / Collection / Item</th><th>Source current → projected</th><th>Destination current → projected</th><th>Requested</th><th>Sent</th></tr></thead><tbody>@include('requests.relocation-rows',['quantities'=>$lines->map(fn($line)=>$line->quantity)->all(),'packing'=>true,'columns'=>5])</tbody></table></main><script src="/requests.js?v=inline-decisions-1" defer></script></body></html>
