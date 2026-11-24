{{--
Use for a Saved Lists panel with a required list selection and CSRF-protected Load action. Pass the available collection and a unique select ID. Loaded-list data selects the current list. A reset URL adds Reset.

## Slots

Use loadFields for hidden or additional controls inside the Load form. The default slot renders after it, for example for a separate location form.

## Examples

[Inventory](resources/views/inventory/browse.blade.php) and [Reconcile](resources/views/inventory/count-search.blade.php).
--}}@props(['lists', 'loadAction', 'selectId', 'loadedList' => null, 'resetUrl' => null])

<x-app.collapsible-panel title="Saved Lists" {{ $attributes->class(['app-saved-list-selector', 'app-collapsible-panel-mobile-padding']) }}>
    <form method="post" action="{{ $loadAction }}">
        @csrf
        {{ $loadFields ?? '' }}
        <div class="app-field">
            <select id="{{ $selectId }}" name="list_id" aria-label="List" required>
                <option value="">Select a list</option>
                @if($loadedList && !$lists->contains('id', $loadedList['id']))
                    <option value="{{ $loadedList['id'] }}" selected disabled>{{ $loadedList['name'] }}</option>
                @endif
                @foreach($lists as $list)<option value="{{ $list->id }}" @selected(old('list_id', $loadedList['id'] ?? null) == $list->id)>{{ $list->name }}</option>@endforeach
            </select>
        </div>
        <div class="actions">
            @if($resetUrl !== null)<x-app.action :href="$resetUrl">Reset</x-app.action>@endif
            <x-app.action type="submit">Load</x-app.action>
        </div>
    </form>
    {{ $slot }}
</x-app.collapsible-panel>
