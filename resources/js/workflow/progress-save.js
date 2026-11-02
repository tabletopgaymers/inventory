// Transport/busy/feedback only. Callers own section values, permissions and dirty state.
export function mountProgressSave(form, { onSaved, feedback, onBusy = () => {}, canSave = () => true, getGeneration = () => 0 }) {
    let busy = false;
    document.addEventListener('click', event => {
        if (busy && event.target.closest('a[href]')) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    document.addEventListener('submit', event => {
        if (busy && event.target !== form) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    const save = async event => {
        if ((event.submitter?.value || 'save') !== 'save') return;
        event.preventDefault();
        if (busy) return;
        if (!canSave()) {
            feedback.hidden = false; feedback.classList.add('notice');
            feedback.textContent = 'Wait for the catalog search to finish before saving.';
            return;
        }
        const generation = getGeneration();
        const payload = new FormData(form);
        payload.set('action', 'save');
        const buttons = [...document.querySelectorAll('button')].filter(button => button.form === form);
        const controls = [...form.querySelectorAll('input,select,textarea'), ...buttons];
        const states = controls.map(control => control.disabled);
        busy = true; onBusy(true);
        controls.forEach(control => { control.disabled = true; });
        buttons.forEach(button => button.setAttribute('aria-busy', 'true'));
        const show = (message, failed) => {
            feedback.hidden = false;
            feedback.classList.toggle('error', failed);
            feedback.classList.toggle('notice', !failed);
            feedback.textContent = message;
            feedback.setAttribute('role', failed ? 'alert' : 'status');
        };
        let saved = null;
        try {
            const response = await fetch(form.getAttribute('action'), {method:'POST', body:payload, headers:{Accept:'application/json'}});
            const result = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
            if (!response.ok || result.saved !== true) {
                const messages = Object.values(result.errors || {}).flat();
                show(messages.join(' ') || result.message || 'Progress could not be saved. Your entered values are retained.', true);
                return;
            }
            saved = result;
            show(result.message || 'Progress saved.', false);
        } catch {
            show('Progress could not be saved. Your entered values are retained.', true);
        } finally {
            controls.forEach((control, index) => { control.disabled = states[index]; });
            buttons.forEach(button => button.removeAttribute('aria-busy'));
            busy = false; onBusy(false);
        }
        if (saved) onSaved(saved, {unchanged:generation === getGeneration()});
    };
    form.addEventListener('submit', save);
    return {isBusy: () => busy};
}
