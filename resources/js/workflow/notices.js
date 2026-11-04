// Confirmations expire; warnings and errors remain until explicitly dismissed.
export function showNotice(element, message, kind = 'success') {
    clearTimeout(element.noticeTimer);
    element.hidden = false;
    element.classList.toggle('error', kind === 'error');
    element.classList.toggle('notice', kind !== 'error');
    element.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    element.textContent = message;
    if (kind === 'success') element.noticeTimer = setTimeout(() => { element.hidden = true; }, 3000);
}
export function mountNotices() {
    document.querySelectorAll('.notice[role="status"]').forEach(element => {
        element.noticeTimer = setTimeout(() => { element.hidden = true; }, 3000);
    });
}
