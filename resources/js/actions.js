// Blade owns markup/classes. Callers own wording, data hooks and business behavior.
export function createAction({ variant = 'secondary', control = null, text = '' } = {}) {
    const key = `${variant}:${control || ''}`;
    const template = [...document.querySelectorAll('template[data-action-template]')]
        .find(template => template.dataset.actionTemplate === key);
    if (!template) throw new Error(`Missing shared action template: ${key}`);
    const button = template.content.firstElementChild.cloneNode(true);
    button.textContent = text;
    return button;
}
