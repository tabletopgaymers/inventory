import './app.js';
import './print.js';

const requestForms = [...document.querySelectorAll('[data-event-form]')];
const formValues = form => JSON.stringify([...form.elements]
    .filter(input => input.name && !input.disabled && ['INPUT', 'SELECT', 'TEXTAREA'].includes(input.tagName) && input.type !== 'hidden')
    .map(input => [input.name, input.type === 'checkbox' || input.type === 'radio' ? input.checked : input.value]));
const initialValues = new Map(requestForms.map(form => [form, formValues(form)]));
const submitting = new Set();
requestForms.forEach(form => {
    form.addEventListener('input', () => submitting.delete(form));
    form.addEventListener('change', () => submitting.delete(form));
    form.addEventListener('submit', event => { if (!event.defaultPrevented) submitting.add(form); });
});
window.appNavigationGuards?.push({
    shouldWarn: () => requestForms.some(form => !submitting.has(form) && formValues(form) !== initialValues.get(form)),
    message: 'Your changes have not been saved. Keep editing to preserve them, or leave without saving.'
});

requestForms.forEach(form => {
    const state = document.createElement('p');
    state.setAttribute('role', 'status');
    state.hidden = true;
    form.prepend(state);
    const markDirty = () => {
        state.hidden = false;
        state.textContent = 'Changes not saved. Closing or reloading may discard unsaved input; in-app links offer Keep editing or Leave without saving.';
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
});
