@extends('layout')
@section('title', $row->name)
@section('title-status')<span class="status-badge request-status {{ $row->status==='Planning' ? 'draft' : ($row->status==='Finalized' ? 'received' : 'active') }}">{{ $row->status }}</span>@endsection
@section('content')
<p><a href="/events">Back to events</a></p>
@if($data['venue'] ?? null)<p>Venue: {{ $data['venue'] }}</p>@endif
@if($data['notes'] ?? null)<p class="app-plain-text">{{ $data['notes'] }}</p>@endif
<p>Default leftover destination: {{ $destinations[$data['default_destination'] ?? ''] ?? 'Not selected or currently unavailable' }}</p>
<div class="actions">
@if($canManage && $row->status==='Planning')<a href="/events/{{ $row->id }}/work/plan">Edit planning</a><a class="primary" href="/events/{{ $row->id }}/work/activate">Review activation</a>@endif
@if($canManage && $row->status==='Active')<a href="/events/{{ $row->id }}/work/delivery">Record delivery</a><a class="primary" href="/events/{{ $row->id }}/work/counts">Enter counts / finalize</a>@endif
@if($row->status==='Finalized')<a class="primary" href="/events/{{ $row->id }}/report">View final report</a>@if($canManage)<a href="/events/{{ $row->id }}/work/correction">Correct report only</a>@endif @endif
</div>
@if($row->status==='Planning')
<h2>Planned supplies</h2><p>No supplies have moved. Complete the initial source, default destination and actual supplies before activation.</p>
<x-app.table-region label="Planned supplies" class="table-wrap"><table><thead><tr><th>Item</th><th>Source</th><th>Planned units</th></tr></thead><tbody>@forelse($data['supplies'] ?? [] as $supply)@php($item=$items->firstWhere('id',$supply['item_id']))<tr><th>{{ $item?->name ?: 'Unavailable item' }}<small class="app-item-sku">{{ $item?->sku }}</small></th><td>{{ $supply['source']==='external' ? 'External / unknown origin' : ($destinations[$supply['source']] ?? 'Unavailable source') }}</td><td>{{ $supply['quantity']===null ? 'Not entered' : number_format($supply['quantity']) }}</td></tr>@empty<tr><td colspan="3">No supplies entered.</td></tr>@endforelse</tbody></table></x-app.table-region>
@else
<h2>{{ $row->status==='Active' ? 'Provisional counts' : 'Original posted counts' }}</h2>
@if($row->status==='Active')<p>Blank means not counted. Saving progress moves no stock. Record any missed delivery before finalizing.</p>@else<p>Finalization cannot reopen. Later report corrections do not change these posted counts or inventory.</p>@endif
<x-app.table-region label="Event counts" class="table-wrap"><table><thead><tr><th>Item</th><th>Brought</th><th>Remaining</th><th>Leftover allocations</th></tr></thead><tbody>
@foreach($data['lines'] as $itemId=>$line)@php($item=$items->firstWhere('id',(int)$itemId))<tr><th>{{ $item?->name ?: 'Unavailable item' }}<small class="app-item-sku">{{ $item?->sku }}</small></th><td>{{ number_format($line['brought']) }}</td><td>{{ $line['remaining']==='' ? 'Not counted' : number_format((int)$line['remaining']) }}</td><td>@foreach($line['allocations'] as $allocation)<p>@if(str_starts_with($allocation['destination'],'event:'))<a href="/events/{{ substr($allocation['destination'],6) }}">{{ $destinations[$allocation['destination']] ?? $allocation['destination'] }}</a>@else{{ $destinations[$allocation['destination']] ?? $allocation['destination'] }}@endif: {{ $allocation['quantity']==='' ? 'Incomplete' : number_format((int)$allocation['quantity']) }}</p>@endforeach</td></tr>@endforeach
</tbody></table></x-app.table-region>
@endif
<h2>Activity</h2>
@foreach($incoming as $entry)<p>{{ \App\Support\DisplayDates::timestamp($entry->posted_at) }} · {{ $entry->actor_name }} · {{ $entry->description }} · {{ number_format($entry->quantity_change) }} units · <a href="/events/{{ $entry->contributor_event_id }}">Contributing event</a></p>@endforeach
@forelse($history as $operation)@php($snapshot=json_decode($operation->data,true))
<details><summary>{{ \App\Support\DisplayDates::timestamp($operation->posted_at) }} · {{ $operation->actor_name }} · {{ ucfirst($operation->action) }}</summary>
@if(in_array($operation->action,['activate','delivery']))<p>Actual movement date: {{ isset($snapshot['actual_date']) ? \App\Support\DisplayDates::businessDate($snapshot['actual_date']) : 'Unknown' }}</p>@foreach($snapshot['supplies'] as $supply)@php($item=$items->firstWhere('id',$supply['item_id']))<p>{{ $item?->name ?: 'Item #'.$supply['item_id'] }} · {{ number_format($supply['quantity']) }} units · {{ $supply['source']==='external' ? 'External / unknown origin' : ($destinations[$supply['source']] ?? $supply['source']) }}</p>@endforeach @endif
@if($operation->action==='finalize')<p>Irreversible original finalization.</p>@foreach($snapshot['report'] as $line)<p>{{ $line['item'] }} · {{ $line['sku'] }}: {{ number_format($line['brought']) }} brought, {{ number_format($line['remaining']) }} remaining, {{ number_format($line['distributed']) }} distributed.</p>@endforeach @endif
@if($operation->action==='correction')<p class="warning">Report-only correction. Original inventory, cost and stock history are unchanged.</p>@foreach($snapshot['after'] as $item=>$line)@php($before=$snapshot['before'][$item])<p>{{ $line['item'] }} · {{ $line['sku'] }}: brought {{ number_format($before['brought']) }} → {{ number_format($line['brought']) }}; remaining {{ number_format($before['remaining']) }} → {{ number_format($line['remaining']) }}; distributed {{ number_format($before['distributed']) }} → {{ number_format($line['distributed']) }}.</p>@endforeach @if($snapshot['explanation'])<p>{{ $snapshot['explanation'] }}</p>@endif @endif
</details>@empty<p>No activity recorded.</p>@endforelse
@endsection
