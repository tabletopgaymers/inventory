const instances = new WeakMap();

// Callers retain their refresh/save policies; this module owns tab mechanics only.
export function mountTabs(root, { initialKey, queryParam = 'tab', removeQueryParams = [], onChange = () => {} } = {}) {
    if (instances.has(root)) return instances.get(root);
    const tabs = [...root.querySelectorAll('[data-workflow-tab]')];
    const panelFor = tab => [...root.querySelectorAll('[role="tabpanel"]')]
        .find(panel => panel.id === tab.getAttribute('aria-controls'));
    const available = () => tabs.filter(tab => !tab.disabled && !tab.closest('[hidden]'));
    const activate = (key, { focus = false, allowDisabled = false } = {}) => {
        const candidates = allowDisabled ? tabs.filter(tab => !tab.closest('[hidden]')) : available();
        const tab = candidates.find(candidate => candidate.dataset.workflowTab === key) || candidates[0];
        if (!tab || !panelFor(tab)) return;
        tabs.forEach(candidate => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', String(selected));
            candidate.tabIndex = selected ? 0 : -1;
            const panel = panelFor(candidate);
            if (panel) panel.hidden = !selected;
        });
        const activeKey = tab.dataset.workflowTab;
        const url = new URL(location.href);
        url.searchParams.set(queryParam, activeKey);
        removeQueryParams.forEach(parameter => { if (parameter !== queryParam) url.searchParams.delete(parameter); });
        history.replaceState(null, '', url);
        onChange(activeKey, tab);
        if (focus) tab.focus();
    };
    const click = event => {
        const tab = event.target.closest('[data-workflow-tab]');
        if (tabs.includes(tab)) activate(tab.dataset.workflowTab);
    };
    const keydown = event => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.isComposing) return;
        const candidates = available(), index = candidates.indexOf(event.target);
        if (index < 0) return;
        const next = event.key === 'ArrowRight' ? (index + 1) % candidates.length
            : event.key === 'ArrowLeft' ? (index + candidates.length - 1) % candidates.length
            : event.key === 'Home' ? 0 : event.key === 'End' ? candidates.length - 1 : null;
        if (next === null) return;
        event.preventDefault();
        activate(candidates[next].dataset.workflowTab, { focus: true });
    };
    root.addEventListener('click', click);
    root.addEventListener('keydown', keydown);
    const api = { activate, destroy() { root.removeEventListener('click', click); root.removeEventListener('keydown', keydown); instances.delete(root); } };
    instances.set(root, api);
    activate(initialKey);
    return api;
}
