// One timing policy for server-rendered and dynamically reused page banners.
const delays = {success: 3000, warning: 10000};
const states = new WeakMap();

export function syncNoticeTimer(element, {restart = false} = {}) {
    const kind = element.matches('.error') ? 'error' : element.matches('.warning') ? 'warning' : element.matches('.notice') ? 'success' : null;
    const message = [...element.childNodes]
        .filter(node => !(node instanceof Element && node.matches('.app-notice-dismiss')))
        .map(node => node.textContent).join('').trim();
    const visible = element.isConnected && !element.closest('[hidden], dialog');
    const signature = JSON.stringify([kind, message, visible]);
    const previous = states.get(element);
    if (!restart && previous?.signature === signature) return;
    clearTimeout(previous?.timer);
    const state = {signature, timer: null};
    states.set(element, state);
    if (!visible || !message || !delays[kind]) return;
    state.timer = setTimeout(() => {
        const restoreFocus = element.contains(document.activeElement);
        element.hidden = true;
        if (restoreFocus) document.getElementById('app-main')?.focus();
    }, delays[kind]);
}

export function showNotice(element, message, kind = 'success') {
    element.hidden = false;
    element.classList.toggle('error', kind === 'error');
    element.classList.toggle('warning', kind === 'warning');
    element.classList.toggle('notice', kind !== 'error' && kind !== 'warning');
    element.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    element.textContent = message;
    syncNoticeTimer(element, {restart: true});
}

// Kept for existing workflow adapters; repeated mounting does not restart timers.
export function mountNotices() {
    document.querySelectorAll('.notice, .warning, .error').forEach(element => syncNoticeTimer(element));
}
