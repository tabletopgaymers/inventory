<x-workflow.steps label="Purchase steps">
@foreach(['basic'=>'Basic info','material'=>'Material','review'=>'Review','order'=>'Procurement','invoice'=>'Order','charges'=>'Order charges','order-review'=>'Order review','receive'=>'Receive','confirmation'=>'Confirmation'] as $key=>$label)
@if(!in_array($key,['receive','confirmation']) || in_array($row->status,['Ordered','Shipped','Received']))
<x-app.action  href="/purchase/work/{{ $token }}?tab={{ $key }}{{ in_array($key,['order','charges']) && $mode==='receipt' ? '&mode=receipt' : '' }}" :aria-current="$key === $tab ? 'page' : null">{{ $mode==='receipt' && in_array($key,['order','charges']) ? ($key==='order' ? 'Receipt details' : 'Receipt charges') : $label }}</x-app.action>
@endif
@endforeach
</x-workflow.steps>
