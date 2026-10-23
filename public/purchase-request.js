(() => {
    'use strict';
    const form = document.querySelector('#purchase-request-form');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-item-row]')];
    const quantities = rows.map(row => row.querySelector('[data-quantity]'));
    const validQuantity = value => /^\d+$/.test(value.trim()) && Number.isSafeInteger(Number(value)) && Number(value) <= 1000000000;
    let dirty = false, leaving = false, pendingHref = null;
    const dialog = document.querySelector('#purchase-leave');
    const errors = form.querySelector('[data-purchase-errors]');
    const reviewBody = form.querySelector('[data-review-items]');
    const cell = (tag, value) => { const el = document.createElement(tag); el.textContent = value; return el; };
    const updateReview = () => {
        const messages = [];
        if (!form.elements.title.value.trim()) messages.push({text:'Enter a title in Basic info.',tab:'basic',input:form.elements.title});
        form.querySelectorAll('[data-review]').forEach(el => { el.textContent = form.elements[el.dataset.review].value || '—'; });
        reviewBody.replaceChildren();
        let group = null;
        rows.forEach(row => {
            const input = row.querySelector('[data-quantity]');
            const invalid = !validQuantity(input.value);
            row.classList.toggle('purchase-invalid', invalid && form.dataset.tab === 'review');
            if (invalid) messages.push({text:`${row.dataset.name}: enter a nonnegative whole requested quantity (zero is allowed).`,tab:'material',input});
            if (group !== row.dataset.group) {
                group = row.dataset.group;
                const heading = document.createElement('tr'); heading.className = 'group-row';
                const th = cell('th', group); th.colSpan = 2; th.scope = 'rowgroup'; heading.append(th); reviewBody.append(heading);
            }
            const tr = document.createElement('tr');
            if (invalid) tr.className = 'purchase-invalid';
            const amount = cell('td', input.value.trim() === '' ? '—' : input.value); amount.className = 'quantity';
            tr.append(cell('td', row.dataset.name), amount); reviewBody.append(tr);
        });
        if (!rows.length) { const tr = document.createElement('tr'); const td = cell('td','No catalog items requested. See Request details.'); td.colSpan = 2; tr.append(td); reviewBody.append(tr); }
        errors.replaceChildren(...messages.map(message => {
            const paragraph = document.createElement('p');
            const link = cell('a',message.text); link.href = '#'+(message.input.id || 'purchase-panel-'+message.tab);
            link.addEventListener('click',event => { event.preventDefault(); showTab(message.tab); message.input.focus(); message.input.select?.(); });
            paragraph.append(link); return paragraph;
        }));
        errors.hidden = form.dataset.tab !== 'review' || !messages.length;
        form.querySelector('[data-submit-request]').disabled = form.dataset.canSubmit !== 'true' || messages.length > 0;
    };
    const showTab = tab => {
        if (!['basic','material','review'].includes(tab)) tab = 'basic';
        form.dataset.tab = tab; form.querySelector('[data-current-tab]').value = tab;
        form.querySelectorAll('[role="tabpanel"]').forEach(panel => { panel.hidden = panel.id !== `purchase-panel-${tab}`; });
        form.querySelectorAll('[data-purchase-tab]').forEach(button => { button.setAttribute('aria-selected',String(button.dataset.purchaseTab === tab)); });
        const url = new URL(location.href); url.searchParams.set('tab',tab); history.replaceState(null,'',url);
        updateReview();
    };
    form.querySelectorAll('[data-purchase-tab]').forEach(button => button.addEventListener('click',() => showTab(button.dataset.purchaseTab)));
    form.querySelector('[data-material-next]').addEventListener('click',event => { event.preventDefault(); showTab('review'); });
    const changed = event => {
        if (event.target.hasAttribute('data-item-search')) return;
        dirty = true; updateReview();
    };
    form.addEventListener('input',changed);
    form.addEventListener('change',changed);
    quantities.forEach((input,index) => input.addEventListener('keydown',event => {
        if (!['Enter','ArrowDown','ArrowUp'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || input.readOnly) return;
        event.preventDefault();
        const direction = event.key === 'ArrowUp' ? -1 : 1;
        for (let next = index + direction; next >= 0 && next < quantities.length; next += direction) {
            if (!quantities[next].readOnly && !quantities[next].disabled) { quantities[next].focus(); quantities[next].select(); break; }
        }
    }));
    form.addEventListener('submit',event => {
        if (event.submitter?.value === 'submit' && form.querySelector('[data-submit-request]').disabled) { event.preventDefault(); showTab('review'); return; }
        leaving = true;
    });
    const search = form.querySelector('[data-item-search]');
    search?.addEventListener('input',() => {
        const term = search.value.toLocaleLowerCase();
        const select = form.querySelector('[data-item-select]');
        select.querySelectorAll('optgroup').forEach(group => {
            let visible = false;
            [...group.children].forEach(option => { option.hidden = !`${group.label} ${option.textContent}`.toLocaleLowerCase().includes(term); visible ||= !option.hidden; });
            group.hidden = !visible;
        });
        if (select.selectedOptions[0]?.hidden) select.value = '';
    });
    document.addEventListener('click',event => {
        const anchor = event.target.closest('a[href]');
        if (!dirty || !anchor || anchor.target === '_blank' || event.ctrlKey || event.metaKey || event.shiftKey || anchor.getAttribute('href').startsWith('#')) return;
        if (anchor.origin !== location.origin || anchor.href !== location.href) { event.preventDefault(); pendingHref = anchor.href; dialog.showModal(); }
    });
    dialog.querySelector('[data-stay]').addEventListener('click',() => { pendingHref = null; dialog.close(); });
    dialog.querySelector('[data-leave]').addEventListener('click',() => { leaving = true; location.href = pendingHref; });
    document.querySelectorAll('[data-confirm-transition]').forEach(other => other.addEventListener('submit',event => {
        if (!window.confirm('Continue with this status change? Unsaved form changes will not be saved.')) event.preventDefault(); else leaving = true;
    }));
    document.querySelectorAll('form:not(#purchase-request-form):not([data-confirm-transition])').forEach(other => other.addEventListener('submit',event => {
        if (dirty && !window.confirm('This action does not save your unsaved request changes. Continue?')) event.preventDefault(); else leaving = true;
    }));
    window.addEventListener('beforeunload',event => { if (dirty && !leaving) { event.preventDefault(); event.returnValue = ''; } });
    showTab(new URL(location.href).searchParams.get('tab') || form.dataset.tab);
})();
