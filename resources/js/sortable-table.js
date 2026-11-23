// Plain, ungrouped tables. Use data-sort-value for dates or formatted numbers.
export function mountSortableTable(table, {buttonSelector = '[data-table-sort]'} = {}) {
    const body = table?.tBodies[0];
    if (!body) return;
    const buttons = [...table.tHead.querySelectorAll(buttonSelector)];
    const originalRows = [...body.rows].filter(row => row.cells.length === table.tHead.rows[0].cells.length);
    if (!buttons.length || !originalRows.length) return;
    const collator = new Intl.Collator(document.documentElement.lang || undefined, {numeric: true, sensitivity: 'base'});
    const value = (row, column) => row.cells[column].dataset.sortValue ?? row.cells[column].textContent.trim();
    const sortBy = (button, ascending) => {
        const header = button.closest('th'), column = header.cellIndex;
        body.append(...[...originalRows].sort((left, right) => (ascending ? 1 : -1) * collator.compare(value(left, column), value(right, column))));
        buttons.forEach(control => {
            control.closest('th').removeAttribute('aria-sort');
            control.querySelector('span').textContent = '↕';
        });
        header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
        button.querySelector('span').textContent = ascending ? '↑' : '↓';
    };
    sortBy(buttons[0], true);
    buttons.forEach(button => button.addEventListener('click', () => sortBy(button, button.closest('th').getAttribute('aria-sort') !== 'ascending')));
}
