@extends('layout')
@section('title', $kinds[$kind])
@section('content')
<nav aria-label="Catalog sections">@foreach($kinds as $key => $label)<a href="/catalog/{{ $key }}" @if($key === $kind)aria-current="page"@endif>{{ $label }}</a>@endforeach</nav>
<div class="section-heading"><p>Retained catalog records. Status changes apply only to the selected record.</p>@if($canManage)<a class="button primary" href="/catalog/{{ $kind }}/create">Create</a>@endif</div>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Status</th><th>Details</th>@if($canManage)<th>Actions</th>@endif</tr></thead><tbody>
@forelse($records as $record)
<tr><td>@if($kind === 'items')<a href="/inventory/items/{{ $record->id }}">{{ $record->name }}</a>@elseif($kind === 'storage_locations')<a href="/inventory/locations/{{ $record->id }}">{{ $record->name }}</a>@else{{ $record->name }}@endif</td>
<td><span class="status-badge {{ $record->state }}">{{ ucfirst($record->state) }}</span></td>
<td>@if(isset($record->sku)){{ $record->sku }}@elseif(isset($record->sku_prefix))Prefix: {{ $record->sku_prefix }}@endif
@if(!empty($record->description))<p>{{ $record->description }}</p>@endif
@if(!empty($record->notes))<p>{{ $record->notes }}</p>@endif
@if($kind === 'suppliers')@foreach(['contact_name'=>'Contact','email'=>'Email','phone'=>'Phone','website'=>'Website','address'=>'Address'] as $field=>$label)@if(!empty($record->$field))<p>{{ $label }}: {{ $record->$field }}</p>@endif @endforeach @endif
@if($kind === 'categories')<a href="/catalog/collections?category={{ $record->id }}">Collections ({{ $record->child_count }})</a>@elseif($kind === 'collections')<p>{{ $record->category_name }} · Items: {{ $record->child_count }}</p>@endif</td>
@if($canManage)<td><a class="button" href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Edit</a>
<form class="lifecycle" method="post" action="/catalog/{{ $kind }}/{{ $record->id }}/lifecycle">@csrf
@if($record->state === 'archived')<button name="action" value="restore">Restore to Inactive</button>@else
<button name="action" value="{{ $record->state === 'active' ? 'inactivate' : 'activate' }}">{{ $record->state === 'active' ? 'Set Inactive' : 'Activate' }}</button>
<label class="checkbox"><input type="checkbox" name="confirm" value="1">Confirm archive of {{ $record->name }}</label><button class="discard" name="action" value="archive">Archive</button>@endif
</form></td>@endif</tr>
@empty<tr><td colspan="4">No records yet.</td></tr>@endforelse
</tbody></table></div>
@endsection
