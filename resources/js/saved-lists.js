import './app.js';
import { mountItemListPicker } from './item-list-picker.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';
import { mountSortableTable } from './sortable-table.js';

mountSortableTable(document.querySelector('[data-saved-lists]'));

const deleteForm = document.querySelector('[data-list-delete]');
if (deleteForm) {
    let approved = false, busy = false;
    const trigger = deleteForm.querySelector('button[type="submit"]');
    deleteForm.addEventListener('submit', event => {
        if (busy) { event.preventDefault(); return; }
        if (!approved) {
            event.preventDefault();
            window.appRequestDecision({title: 'Delete list?',
                message: 'This permanently deletes the list. Selections already copied into another process or list are not changed.',
                origin: trigger, confirmLabel: 'Delete list', variant: 'destructive',
                onConfirm: () => { approved = true; deleteForm.requestSubmit(trigger); }});
            return;
        }
        busy = true; trigger.disabled = true;
    });
    window.addEventListener('pageshow', () => { approved = false; busy = false; trigger.disabled = false; });
}

const form = document.querySelector('#saved-list-form');
if (form) {
    const root = form.querySelector('[data-memory-demo]');
    const itemInput = form.elements.item_ids_json;
    const location = form.elements.storage_location_id;
    const save = document.querySelector('[data-list-save]');
    const saveLabel = save.querySelector('[data-save-label]');
    const creating = form.dataset.saveMode === 'create';
    let dirty = form.dataset.saveDirty === 'true', ready = false, submitting = false;
    const guard = mountUnsavedChanges(form, {isDirty: () => dirty, isBusy: () => submitting});
    const renderSave = () => {
        save.classList.toggle('app-save-dirty', dirty);
        save.disabled = !ready || submitting;
        save.setAttribute('aria-busy', String(submitting));
        saveLabel.textContent = submitting ? (creating ? 'Creating…' : 'Saving…') : (creating ? 'Create' : 'Save');
    };
    const markDirty = () => { dirty = true; renderSave(); };
    form.addEventListener('invalid', event => {
        const panel = event.target.closest('details');
        if (panel) panel.open = true;
    }, true);
    form.addEventListener('input', event => { if (event.target.name) markDirty(); });
    form.addEventListener('change', event => { if (event.target.name) markDirty(); });
    location.disabled = true;
    let itemIds = [];
    try { const saved = JSON.parse(itemInput.value); if (Array.isArray(saved)) itemIds = saved; } catch { /* Server validation retains invalid input for correction. */ }
    mountItemListPicker(root, {
        persistSelection: false, initialItemIds: itemIds, initialLocationId: location.value,
        onChange: ({selectedItemIds, locationId}) => {
            itemInput.value = JSON.stringify(selectedItemIds);
            location.value = locationId === null ? '' : String(locationId);
            if (ready) markDirty();
        },
        onReady: () => { ready = true; form.elements.picker_ready.value = '1'; location.disabled = false; renderSave(); },
    });
    location.addEventListener('change', () => {
        const pickerLocation = root.querySelector('#memory-location');
        pickerLocation.value = location.value;
        pickerLocation.dispatchEvent(new Event('change'));
    });
    form.addEventListener('submit', event => {
        if (!ready || submitting) { event.preventDefault(); return; }
        submitting = true;
        guard.allowDeparture();
        form.inert = true;
        renderSave();
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}
