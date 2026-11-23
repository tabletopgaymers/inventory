import { mountSortableTable } from './sortable-table.js';

mountSortableTable(document.querySelector('[data-user-directory]'), {buttonSelector: '[data-user-sort]'});
