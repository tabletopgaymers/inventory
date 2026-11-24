// One timing policy for server-rendered and dynamically reused page banners.
const successDelay = 3000;
const states = new WeakMap();

/**
 * Hides visible success messages after three seconds. Leaves warnings and errors visible until dismissed. An identical visible message keeps its current timer unless restart is requested.
 *
 * Scope: Shared
 * Task: Apply the shared dismissal schedule to a banner
 * @example resources/js/app-shell.js
 */
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
    if (!visible || !message || kind !== 'success') return;
    state.timer = setTimeout(() => {
        const restoreFocus = element.contains(document.activeElement);
        element.hidden = true;
        if (restoreFocus) document.getElementById('app-main')?.focus();
    }, successDelay);
}

/**
 * Sets the message, tone and announcement role before updating the success dismissal timer. Use a notice element reserved for feedback because its contents are replaced.
 *
 * Scope: Shared
 * Task: Show or replace a page notice
 * @example resources/js/relocation.js
 */
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
/**
 * Applies the shared notice timing to rendered page banners. Repeating the scan keeps an unchanged banner's running timer.
 *
 * Scope: Shared
 * Task: Synchronize existing feedback banners
 * @example resources/js/purchase-request.js
 */
export function mountNotices() {
    document.querySelectorAll('.notice, .warning, .error').forEach(element => syncNoticeTimer(element));
}
