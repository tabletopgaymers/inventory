<div class="app-content purchase-tab-panel"><nav class="purchase-tabs" aria-label="Purchase steps">
@foreach(['basic'=>'Basic info','material'=>'Material','review'=>'Review','order'=>'Order details','invoice'=>'Order invoice','charges'=>'Order charges','order-review'=>'Order review','receive'=>'Receive','confirmation'=>'Confirmation'] as $key=>$label)
@if(!in_array($key,['receive','confirmation']) || in_array($row->status,['Ordered','Shipped','Received']))
<a class="button" href="/purchase/work/{{ $token }}?tab={{ $key }}{{ in_array($key,['order','charges']) && $mode==='receipt' ? '&mode=receipt' : '' }}" @if($key===$tab) aria-current="page" @endif>{{ $mode==='receipt' && in_array($key,['order','charges']) ? ($key==='order' ? 'Receipt details' : 'Receipt charges') : $label }}</a>
@endif
@endforeach
</nav></div>
