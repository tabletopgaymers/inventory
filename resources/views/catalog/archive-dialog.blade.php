    <form method="post" action="{{ $actionBase }}/lifecycle{{ $purchaseReturnQuery }}" id="catalog-archive-form" hidden data-archive-title="Archive {{ $record->name }}?">
        @csrf
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="confirm" value="1">
    </form>
