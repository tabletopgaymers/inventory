// Shared save presentation; callers own dirty-state and persistence policy.
/**
 * Toggles the shared dirty class. The caller owns the dirty-state decision and save behavior.
 *
 * Scope: Shared
 * Task: Show unsaved work on the workflow save button
 * @example resources/js/relocation.js
 */
export function setSaveDirty(button, dirty) {
    button?.classList.toggle('app-save-dirty', dirty);
}
/**
 * Updates aria-busy and the nested label between Saving and Save progress. Requires the standard save label hook. The caller controls when the button is disabled.
 *
 * Scope: Shared
 * Task: Show workflow progress saving on the button
 * @example resources/js/workflow/progress-save.js
 */
export function setSaveBusy(button, busy) {
    if (!button) return;
    button.setAttribute('aria-busy', String(busy));
    button.querySelector('[data-save-label]').textContent = busy ? 'Saving…' : 'Save progress';
}
