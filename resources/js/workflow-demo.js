import { mountTabs } from './workflow/tabs.js';
import { mountQuantityNavigation } from './workflow/quantity-navigation.js';

document.querySelectorAll('[data-workflow-demo]').forEach(root => {
    const queryParam = root.dataset.workflowDemo;
    mountTabs(root, { queryParam, initialKey: new URL(location.href).searchParams.get(queryParam) || 'basic' });
});

document.querySelectorAll('[data-quantity-demo]').forEach(root => {
    mountQuantityNavigation(root, { resolveFields: input => input.matches('[data-demo-quantity]') ? root.querySelectorAll('[data-demo-quantity]') : [] });
    root.addEventListener('submit', event => event.preventDefault());
    root.querySelector('[data-demo-add-row]').addEventListener('click', () => {
        const row = root.querySelector('template').content.firstElementChild.cloneNode(true);
        const number = root.querySelectorAll('[data-demo-dynamic]').length + 1;
        row.firstElementChild.textContent = `Added row ${number}`;
        row.querySelector('input').setAttribute('aria-label', `Added quantity ${number}`);
        root.querySelector('tbody').append(row);
    });
});
