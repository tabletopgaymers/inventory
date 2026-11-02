'use strict';
(() => {
    const buttons = [...document.querySelectorAll('[data-save-shortcut]')];
    if (!buttons.length) return;
    const mac = /Mac|iPhone|iPad|iPod/i.test(navigator.userAgentData?.platform || navigator.platform || '');
    buttons.forEach(button => {
        button.dataset.saveShortcutLabel = mac ? 'Save progress (Cmd+S)' : 'Save progress (Ctrl+S)';
        button.setAttribute('aria-keyshortcuts', 'Control+S Meta+S');
    });
    document.addEventListener('keydown', event => {
        if (event.defaultPrevented || event.isComposing || event.key.toLowerCase() !== 's' || !(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) return;
        const visible = buttons.filter(button => button.isConnected && !button.closest('[hidden]') && button.getClientRects().length);
        if (!visible.length) return;
        event.preventDefault();
        if (event.repeat || [...document.querySelectorAll('dialog[open], [aria-modal="true"]')].some(modal => modal.getClientRects().length)) return;
        const focusedForm = document.activeElement?.form;
        const button = visible.find(candidate => focusedForm && candidate.form === focusedForm) || visible[0];
        if (button.matches(':disabled') || button.getAttribute('aria-disabled') === 'true' || button.getAttribute('aria-busy') === 'true') return;
        button.click();
    });
})();
