import './app.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';

document.querySelectorAll('[data-native-record-form]').forEach(form => {
    const save = [...form.elements].find(control => control.hasAttribute('data-record-submit'));
    if (!save) return;

    const label = save.querySelector('[data-save-label]');
    const idleLabel = label.textContent;
    const signature = () => JSON.stringify([...new FormData(form)]);
    const baseline = signature();
    const isDirty = () => form.dataset.saveDirty === 'true' || signature() !== baseline;
    let submitting = false;
    const renderDirty = () => save.classList.toggle('app-save-dirty', isDirty());

    const guardOptions = { isDirty, isBusy: () => submitting };
    let guard = mountUnsavedChanges(form, guardOptions);
    form.addEventListener('input', renderDirty);
    form.addEventListener('change', renderDirty);
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (submitting) {
            event.preventDefault();
            return;
        }

        submitting = true;
        if (event.submitter && event.submitter !== save) return;

        form.inert = true;
        save.setAttribute('aria-busy', 'true');
        label.textContent = save.dataset.recordSubmit;
        // Let the browser collect the submitter's name and value before disabling it.
        form.addEventListener('formdata', () => { save.disabled = true; }, { once: true });
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            guard.destroy();
            guard = mountUnsavedChanges(form, guardOptions);
        }
        submitting = false;
        form.inert = false;
        save.disabled = false;
        save.setAttribute('aria-busy', 'false');
        label.textContent = idleLabel;
        renderDirty();
    });
    renderDirty();
});
