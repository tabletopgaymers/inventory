<dialog id="catalog-archive-dialog" class="app-user-disable-dialog" aria-labelledby="catalog-archive-title" aria-describedby="catalog-archive-description">
    <h2 id="catalog-archive-title">Archive {{ $record->name }}?</h2>
    <div id="catalog-archive-description">
        <p>The record, associated records, inventory and earlier history will be retained. This changes only the selected record. Restoration returns it to Inactive; activation is a separate action.</p>
        <p data-catalog-archive-unsaved hidden>Unsaved edits will not be saved when you archive this record.</p>
    </div>
    <form method="post" action="{{ $actionBase }}/lifecycle" id="catalog-archive-form">
        @csrf
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="confirm" value="1">
        <div class="actions">
            <button type="button" id="catalog-archive-cancel" autofocus>Cancel</button>
            <button type="submit" id="catalog-archive-confirm" class="app-user-disable-confirm">Archive</button>
        </div>
    </form>
</dialog>
