/**
 * Keeps the button visible only when text is present. Clearing emits a bubbling input event, runs the callback, and returns focus to the field. The returned update method can resync programmatic changes.
 *
 * Scope: Shared
 * Task: Wire a clear control to a text field
 * @example resources/js/inventory-search-controls.js
 */
export function mountClearableInput(input, button, {onClear = () => {}} = {}) {
    if (!input || !button) return;
    const update = () => { button.hidden = input.value.length === 0; };
    input.addEventListener('input', update);
    button.addEventListener('click', () => {
        input.value = '';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        onClear();
        input.focus();
    });
    update();
    return {update};
}
