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
