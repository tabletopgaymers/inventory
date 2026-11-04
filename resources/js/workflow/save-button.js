// Shared save presentation; callers own dirty-state and persistence policy.
export function setSaveDirty(button, dirty) {
    button?.classList.toggle('app-save-dirty', dirty);
}
export function setSaveBusy(button, busy) {
    if (!button) return;
    button.setAttribute('aria-busy', String(busy));
    button.querySelector('[data-save-label]').textContent = busy ? 'Saving…' : 'Save progress';
}
