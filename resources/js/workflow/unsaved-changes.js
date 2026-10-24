const instances = new WeakMap();

// The caller owns dirty/save state. The shell owns the one confirmation UI.
export function mountUnsavedChanges(root, { isDirty, isBusy = () => false,
    message = 'Your changes have not been saved. Keep editing or leave without saving.' }) {
    if (instances.has(root)) return instances.get(root);
    if (!window.appNavigationGuards || !window.appRequestDecision) throw new Error('Unsaved-change handling requires the application shell.');
    let leaving = false;
    const guard = { modal: true, message, shouldWarn: () => isDirty() && !isBusy() && !leaving,
        onLeave: () => { leaving = true; } };
    window.appNavigationGuards.push(guard);
    const beforeUnload = event => {
        if (guard.shouldWarn()) { event.preventDefault(); event.returnValue = ''; }
    };
    window.addEventListener('beforeunload', beforeUnload);
    const api = {
        isLeaving: () => leaving,
        allowDeparture: () => { leaving = true; },
        confirm(proceed, { origin = document.activeElement, text = message,
            actionLabel = 'Leave without saving', title = 'Unsaved changes' } = {}) {
            window.appRequestDecision(text, () => { leaving = true; proceed(); }, origin, actionLabel, true, title);
        },
        destroy() {
            window.removeEventListener('beforeunload', beforeUnload);
            const index = window.appNavigationGuards.indexOf(guard);
            if (index >= 0) window.appNavigationGuards.splice(index, 1);
            instances.delete(root);
        }
    };
    instances.set(root, api);
    return api;
}
