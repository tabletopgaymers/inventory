import { mountItemListPicker } from './item-list-picker.js';
const root = document.querySelector('[data-memory-demo]');
if (root) {
    mountItemListPicker(root);
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}
