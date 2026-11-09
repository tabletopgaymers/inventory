const table = document.querySelector('[data-user-directory]');
const body = table?.tBodies[0];

if (body) {
    const buttons = [...table.tHead.querySelectorAll('[data-user-sort]')];
    const originalRows = [...body.rows];
    const collator = new Intl.Collator(document.documentElement.lang || undefined, {
        numeric: true,
        sensitivity: 'base',
    });

    function sortBy(button, ascending) {
        const header = button.closest('th');
        const column = header.cellIndex;
        const direction = ascending ? 1 : -1;
        const rows = [...originalRows].sort((left, right) => direction * collator.compare(
            left.cells[column].textContent.trim(),
            right.cells[column].textContent.trim(),
        ));
        body.append(...rows);

        buttons.forEach(control => {
            control.closest('th').removeAttribute('aria-sort');
            control.querySelector('span').textContent = '↕';
        });
        header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
        button.querySelector('span').textContent = ascending ? '↑' : '↓';
    }

    // Use the same collation for the initial interactive order and each toggle.
    sortBy(buttons[0], true);
    buttons.forEach(button => {
        button.addEventListener('click', () => {
            const ascending = button.closest('th').getAttribute('aria-sort') !== 'ascending';
            sortBy(button, ascending);
        });
    });
}
