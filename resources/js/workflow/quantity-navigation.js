const instances = new WeakMap();

export function mountQuantityNavigation(root, { resolveFields, isBusy = () => false }) {
    if (instances.has(root)) return instances.get(root);
    const keydown = event => {
        if (!['Enter', 'ArrowUp', 'ArrowDown'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.isComposing || isBusy()) return;
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) return;
        const fields = [...resolveFields(input)].filter(field => field instanceof HTMLInputElement
            && !['checkbox', 'radio', 'hidden'].includes(field.type) && !field.readOnly
            && !field.matches(':disabled') && field.getClientRects().length && getComputedStyle(field).visibility !== 'hidden');
        const index = fields.indexOf(input);
        if (index < 0) return;
        event.preventDefault();
        const next = fields[index + (event.key === 'ArrowUp' ? -1 : 1)];
        if (next) { next.focus(); next.select(); }
    };
    root.addEventListener('keydown', keydown);
    const api = { destroy() { root.removeEventListener('keydown', keydown); instances.delete(root); } };
    instances.set(root, api);
    return api;
}
