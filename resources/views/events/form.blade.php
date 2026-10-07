@extends('layout')
@section('title', ($row?->name ?: 'New event').' — '.(['plan'=>'Planning','activate'=>'Activation','delivery'=>'Delivery','counts'=>'Counts','correction'=>'Report correction'][$mode]))
@section('content')
@php($values=$draft['values'])
<p><a href="{{ $row ? '/events/'.$row->id : '/events' }}">Back to {{ $row ? 'event' : 'events' }}</a></p>
@if($mode==='counts')<p>Counts are shared. Blank remains uncounted; zero is an explicit count. Saving progress permits incomplete allocations and unusual counts. Finalization requires every count, remaining no greater than brought and exact leftover allocations.</p>@endif
@if($mode==='correction')<p class="warning">This changes the report only. Stock, cost, contributions and original posting stay unchanged. Use a separate inventory adjustment for an actual stock correction.</p>@endif
@if($mode==='activate')<p>Activation moves the saved planned supplies once. Review the quantities below before confirming.</p>@endif
<form method="post" action="{{ $base }}" data-request-form>@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="work" value="{{ $token }}">
@if($mode==='plan')
<label>Event name<input name="name" required maxlength="255" value="{{ $values['name'] ?? '' }}"></label>
<label>Venue (optional)<input name="venue" maxlength="255" value="{{ $values['venue'] ?? '' }}"></label>
<label>Notes (optional)<textarea name="notes" maxlength="5000">{{ $values['notes'] ?? '' }}</textarea></label>
<label>Initial storage source (required for activation)<select name="initial_source_id"><option value="">Choose initial source</option>@foreach($locations->where('state','active') as $location)<option value="{{ $location->id }}" @selected((string)$location->id===(string)($values['initial_source_id']??''))>{{ $location->name }}</option>@endforeach</select></label>
<label>Default leftover destination (required for activation)<select name="default_destination"><option value="">Choose default destination</option>@foreach($destinations as $key=>$label)<option value="{{ $key }}" @selected($key===($values['default_destination']??''))>{{ $label }}</option>@endforeach</select></label>
@endif
@if(in_array($mode,['plan','delivery']))
<h2>{{ $mode==='plan' ? 'Planned supplies' : 'Actual additional supplies' }}</h2><p>Choose known storage to move stock directly. External / unknown origin adds event stock without changing Unit Cost. Supplies already in this event are not another contribution.</p>
@foreach($values['supplies'] ?? [] as $index=>$supply)
<fieldset><legend>Supply {{ $index+1 }}</legend>
<label>Item<select name="supplies[{{ $index }}][item_id]"><option value="">Choose item</option>@foreach($items->where('state','active') as $item)<option value="{{ $item->id }}" @selected((string)$item->id===(string)($supply['item_id']??''))>{{ $item->name }} · {{ $item->sku }}</option>@endforeach</select></label>
<label>Source<select name="supplies[{{ $index }}][source]"><option value="">Choose source</option><option value="external" @selected(($supply['source']??'')==='external')>External / unknown origin</option>@foreach($locations->where('state','active') as $location)<option value="storage:{{ $location->id }}" @selected(($supply['source']??'')==='storage:'.$location->id)>{{ $location->name }}</option>@endforeach</select></label>
<label>Individual units<input name="supplies[{{ $index }}][quantity]" inputmode="numeric" value="{{ $supply['quantity'] ?? '' }}"></label>
<button name="action" value="remove" formnovalidate onclick="this.form.elements.remove_index.value='{{ $index }}'">Remove supply {{ $index+1 }}</button>
</fieldset>@endforeach
<input type="hidden" name="remove_index" value=""><button name="action" value="add" formnovalidate>Add supply</button>
@endif
@if(in_array($mode,['activate','delivery']))<label>Actual movement date (optional; unknown stays unknown)<input type="date" name="actual_date" value="{{ $values['actual_date'] ?? '' }}"></label>@endif
@if($mode==='activate')<x-app.table-region label="Activation supplies" class="table-wrap"><table><thead><tr><th>Item</th><th>Source</th><th>Units</th></tr></thead><tbody>@foreach($values['supplies'] ?? [] as $supply)@php($item=$items->firstWhere('id',$supply['item_id']))<tr><th>{{ $item?->name }}<small class="app-item-sku">{{ $item?->sku }}</small></th><td>{{ $supply['source']==='external' ? 'External / unknown origin' : ($destinations[$supply['source']] ?? 'Unavailable source') }}</td><td>{{ $supply['quantity']===null ? 'Not entered' : number_format($supply['quantity']) }}</td></tr>@endforeach</tbody></table></x-app.table-region>@endif
@if(in_array($mode,['counts','correction']))
@foreach($values['lines'] ?? [] as $itemId=>$line)@php($item=$items->firstWhere('id',(int)$itemId))
<fieldset><legend>{{ $item?->name ?: ($line['item'] ?? 'Unavailable item') }} · {{ $item?->sku ?: ($line['sku'] ?? '#'.$itemId) }}</legend>
@if($mode==='correction')<label>Report brought<input name="lines[{{ $itemId }}][brought]" inputmode="numeric" required value="{{ $line['brought'] }}"></label>@else<p>Brought: {{ number_format($line['brought']) }}</p>@endif
<label>{{ $mode==='correction' ? 'Report remaining' : 'Counted remaining (blank if not counted)' }}<input name="lines[{{ $itemId }}][remaining]" inputmode="numeric" value="{{ $line['remaining'] ?? '' }}" @required($mode==='correction')></label>
@if($mode==='counts')
@foreach($line['allocations'] ?? [] as $index=>$allocation)
<div class="event-allocation"><label>Leftover destination<select name="lines[{{ $itemId }}][allocations][{{ $index }}][destination]"><option value="">Choose destination</option>@if(($allocation['destination']??'')!=='' && !isset($destinations[$allocation['destination']]))<option value="{{ $allocation['destination'] }}" selected>Unavailable destination — choose another</option>@endif @foreach($destinations as $key=>$label)<option value="{{ $key }}" @selected($key===($allocation['destination']??''))>{{ $label }}</option>@endforeach</select></label>
<label>Allocated individual units<input name="lines[{{ $itemId }}][allocations][{{ $index }}][quantity]" inputmode="numeric" value="{{ $allocation['quantity'] ?? '' }}"></label>
<button name="action" value="remove_split" formnovalidate onclick="this.form.elements.split_item.value='{{ $itemId }}';this.form.elements.remove_split.value='{{ $index }}'">Remove destination {{ $index+1 }}</button></div>
@endforeach
<button name="action" value="split" formnovalidate onclick="this.form.elements.split_item.value='{{ $itemId }}'">Add destination for {{ $item?->name }}</button>
@endif
</fieldset>@endforeach
<input type="hidden" name="split_item" value=""><input type="hidden" name="remove_split" value="">
@endif
@if($mode==='correction')<label>Explanation (optional)<textarea name="explanation" maxlength="5000">{{ $values['explanation'] ?? '' }}</textarea></label>@endif
<div class="actions"><a class="discard" href="{{ $row ? '/events/'.$row->id : '/events' }}">Discard changes</a>@if($mode==='plan')<button class="primary" name="action" value="save" formnovalidate>Save planning</button>@else @if($mode==='counts')<button name="action" value="save" formnovalidate>Save progress</button>@endif<button class="primary" name="action" value="review">{{ $mode==='counts' ? 'Review irreversible finalization' : 'Review '.$mode }}</button>@endif</div>
</form><script src="/requests.js" defer></script>
@endsection
