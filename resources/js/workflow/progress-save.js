import { setSaveBusy } from './save-button.js';
import { showNotice } from './notices.js';

// One transport/lifecycle for workflows; adapters own preparation and domain updates.
export function createProgressController(form, { onBusy = () => {} } = {}) {
    let busy = false;
    const button = [...document.querySelectorAll('[data-save-shortcut]')].find(button => button.form === form);
    document.addEventListener('click', event => {
        if (busy && event.target.closest('a[href]')) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    document.addEventListener('submit', event => {
        if (busy && event.target !== form) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    const run = async ({ endpoint = form.getAttribute('action'), payload, onSaved, onError, onFinally = () => {} }) => {
        if (busy) return null;
        const buttons = [...document.querySelectorAll('button')].filter(control => control.form === form);
        const controls = [...form.querySelectorAll('input,select,textarea'), ...buttons];
        const states = controls.map(control => control.disabled);
        let saved = null;
        busy = true;
        try {
            onBusy(true); setSaveBusy(button, true);
            controls.forEach(control => { control.disabled = true; });
            const response = await fetch(endpoint, {method:'POST', body:payload, headers:{Accept:'application/json'}});
            const result = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
            if (!response.ok || result.saved !== true) await onError(result);
            else { await onSaved(result); saved = result; }
        } catch {
            await onError({message:'Progress could not be saved. Your entered values are retained; try again.'});
        } finally {
            controls.forEach((control, index) => { control.disabled = states[index]; });
            busy = false;
            try { onBusy(false); onFinally(); }
            finally { setSaveBusy(button, false); }
        }
        return saved;
    };
    return { run, isBusy: () => busy };
}

// Purchase adapter: one progress endpoint, generation-based dirty clearing.
export function mountProgressSave(form, { onSaved, feedback, onBusy = () => {}, canSave = () => true, getGeneration = () => 0 }) {
    const controller = createProgressController(form, {onBusy});
    const saveProgress = async () => {
        if (controller.isBusy()) return;
        if (!canSave()) {
            showNotice(feedback, 'Wait for the catalog search to finish before saving.', 'warning');
            return;
        }
        const generation = getGeneration();
        const payload = new FormData(form); payload.set('action', 'save');
        const saved = await controller.run({payload,
            onSaved: result => showNotice(feedback, result.message || 'Progress saved.', 'success'),
            onError: result => showNotice(feedback, Object.values(result.errors || {}).flat().join(' ') || result.message || 'Progress could not be saved. Your entered values are retained.', 'error')
        });
        if (saved) onSaved(saved, {unchanged:generation === getGeneration()});
        return saved;
    };
    form.addEventListener('submit', event => {
        if ((event.submitter?.value || 'save') !== 'save') return;
        event.preventDefault();
        void saveProgress();
    });
    return { ...controller, save: saveProgress };
}
