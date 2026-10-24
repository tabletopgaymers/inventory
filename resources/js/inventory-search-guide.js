import './inventory-search-controls.js';

(() => {
    const full = document.getElementById('guide-search-full');
    const picker = document.getElementById('guide-search-picker');
    const format = document.getElementById('guide-search-format');
    const fullOutput = document.getElementById('guide-search-full-results');
    const pickerOutput = document.getElementById('guide-search-picker-results');
    let latest = null;
    const node = (tag, text, className) => {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const link = (text, href) => { const element = node('a', text); element.href = href; return element; };
    const table = (data, output, plain) => {
        const region = node('div', undefined, plain ? 'app-table-region table-wrap app-list-table-panel' : '');
        if (plain) { region.setAttribute('role', 'region'); region.setAttribute('aria-label', 'Full inventory search results'); region.tabIndex = 0; }
        const table = node('table');
        const head = node('thead'); const labels = node('tr');
        const columns = Object.entries(data.columns);
        ['Item', 'SKU', ...(plain ? ['Available'] : []), ...columns.map(([,column]) => column.name)].forEach(text => { const th = node('th', text); th.scope = 'col'; labels.append(th); });
        head.append(labels); table.append(head);
        const body = node('tbody');
        for (const row of data.rows) {
            const tr = node('tr'); const name = node('td');
            name.append(link(row.name, '/catalog/items/' + row.item_id));
            const classification = node('span', ` · ${row.category} : ${row.collection}`, 'app-field-help');
            name.append(classification); tr.append(name, node('td', row.sku));
            if (plain) tr.append(node('td', row.available.toLocaleString(), 'number'));
            columns.forEach(([key]) => tr.append(node('td', (row.values[key] || 0).toLocaleString(), 'number')));
            body.append(tr);
        }
        if (!data.rows.length) { const row = node('tr'); const cell = node('td', 'No matching items.'); cell.colSpan = labels.children.length; row.append(cell); body.append(row); }
        table.append(body); region.append(table); output.replaceChildren(region);
    };
    const render = () => {
        if (!latest) { fullOutput.replaceChildren(node('p', 'Search inventory or choose Show all.')); return; }
        if (format.value === 'table') { table(latest, fullOutput, true); return; }
        const container = node(format.value === 'list' ? 'ul' : 'div', undefined, format.value === 'cards' ? 'guide-search-cards' : '');
        for (const row of latest.rows) {
            const item = node(format.value === 'list' ? 'li' : 'article', undefined, format.value === 'cards' ? 'app-content' : '');
            item.append(link(row.name, '/catalog/items/' + row.item_id));
            const details = node('p');
            details.append(link(row.category, '/catalog/categories/' + row.category_id), document.createTextNode(' : '), link(row.collection, '/catalog/collections/' + row.collection_id));
            item.append(details, node('p', `${row.sku} · Available ${row.available.toLocaleString()}`));
            container.append(item);
        }
        if (!latest.rows.length) container.append(node(format.value === 'list' ? 'li' : 'p', 'No matching items.'));
        fullOutput.replaceChildren(container);
    };
    full.addEventListener('inventory-search:results', event => {
        latest = event.detail; render();
        document.getElementById('guide-search-json').textContent = JSON.stringify(latest, null, 2);
    });
    full.addEventListener('inventory-search:reset', () => { latest = null; render(); document.getElementById('guide-search-json').textContent = 'No search submitted.'; });
    picker.addEventListener('inventory-search:results', event => table(event.detail, pickerOutput, false));
    picker.addEventListener('inventory-search:reset', () => {
        const labels = { central: { name: 'Central' } };
        table({ rows: [], columns: labels }, pickerOutput, false);
        pickerOutput.querySelector('tbody td').textContent = 'Search inventory or choose Show all.';
    });
    format.addEventListener('change', render);
})();
