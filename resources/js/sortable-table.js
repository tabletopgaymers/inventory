// Plain, ungrouped tables. Use data-sort-value for dates or formatted numbers.
/**
 * Mount client-side sorting on a plain table with one ungrouped body. Put a matching button with an indicator span in each sortable column heading.
 * The first sortable column starts ascending. Each click toggles that column’s direction and updates its indicator and aria-sort state.
 * Values are compared using the page locale with numeric sorting. For formatted values, supply sortable keys through data-sort-value on the cells.
 *
 * Scope: Shared
 * Task: Sort a table
 *
 * @param {HTMLTableElement|null} table The table to mount, if present.
 * @param {object} [options] Set buttonSelector when the heading buttons use another hook.
 * @example resources/js/user-directory.js
 */
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
