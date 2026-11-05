<x-workflow.steps label="Purchase steps">
@foreach($stepLabels as $key=>$label)
@if($key!=='receive' || in_array($row->status,['Ordered','Shipped','Received']))
<x-app.action  href="/purchase/work/{{ $token }}?tab={{ $key }}" :aria-current="$key === $tab ? 'page' : null">{{ $label }}</x-app.action>
@endif
@endforeach
</x-workflow.steps>
