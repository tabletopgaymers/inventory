import './app.js';
import { mountNotices } from './workflow/notices.js';
import { mountProgressSave } from './workflow/progress-save.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';
import { setSaveDirty } from './workflow/save-button.js';

const form = document.querySelector('[data-purchase-receive]');
if (form) {
    mountNotices();
    let dirty = false, generation = 0;
    const save = document.querySelector('[data-save-shortcut]');
    const navigation = mountUnsavedChanges(form, {isDirty: () => dirty});
    form.addEventListener('input', () => { dirty = true; generation++; setSaveDirty(save, true); });
    mountProgressSave(form, {feedback:document.querySelector('[data-progress-feedback]'), getGeneration:() => generation,
        onSaved:(result, {unchanged}) => {
            document.querySelectorAll('input[name="revision"]').forEach(input => { input.value = result.revision; });
            if (unchanged) { dirty = false; setSaveDirty(save, false); }
        }});
    form.addEventListener('submit', event => {
        if (event.submitter?.value === 'complete') navigation.allowDeparture();
    });
    document.querySelectorAll('form:not([data-purchase-receive])').forEach(other => other.addEventListener('submit', event => {
        if (navigation.isLeaving()) return;
        if (!other.matches('[data-confirm-transition]') && !dirty) { navigation.allowDeparture(); return; }
        event.preventDefault(); const button = event.submitter;
        navigation.confirm(() => other.requestSubmit(button), {origin:button,
            title:other.matches('[data-confirm-transition]') ? 'Cancel purchase?' : 'Unsaved changes',
            actionLabel:other.matches('[data-confirm-transition]') ? 'Cancel purchase' : 'Continue',
            text:other.matches('[data-confirm-transition]') ? 'Cancel this purchase? Its Ordered quantities will be cleared without adding stock or changing Unit Cost. Cancelled purchases cannot be reopened.' : 'This action does not save your changed Date received. Continue?'});
    }));
}
