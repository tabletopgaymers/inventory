<x-workflow.steps label="Purchase steps">
@foreach($stepLabels as $key=>$label)
@if(!in_array($key,['receive','confirmation']) || in_array($row->status,['Ordered','Shipped','Received']))
<x-app.action  href="/purchase/work/{{ $token }}?tab={{ $key }}{{ in_array($key,['order','charges']) && $mode==='receipt' ? '&mode=receipt' : '' }}" :aria-current="$key === $tab ? 'page' : null">{{ $label }}</x-app.action>
@endif
@endforeach
</x-workflow.steps>
