import './app.js';

// User policy and POST forms remain here; dialog presentation is shared.
const roleForms = [...document.querySelectorAll('[data-user-role-form]')];
const disableForm = document.getElementById('user-disable-form');
const entries = roleForms.map(form => ({form, trigger:form.querySelector('[data-user-role-trigger]'),
    message:form.dataset.roleConfirmation, label:form.dataset.roleLabel,
    variant:form.dataset.roleRemoval === '1' ? 'danger' : 'primary'}));
if (disableForm) entries.push({form:disableForm, trigger:document.getElementById('user-disable-trigger'),
    message:'Disabling an account will remove any existing roles and will prevent the user from signing in.', label:'Disable account', variant:'danger'});
const enabled = entries.filter(entry => entry.trigger && !entry.trigger.disabled);
let submitting = false;
enabled.forEach(({form, trigger, message, label, variant}) => {
    let approved = false;
    const open = () => {
        if (submitting) return;
        window.appRequestDecision({title:'Are you sure?', message, origin:trigger, confirmLabel:label, variant,
            onConfirm:() => { approved = true; form.requestSubmit(); approved = false; }});
    };
    trigger.addEventListener('click', open);
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (!approved) { event.preventDefault(); open(); return; }
        submitting = true;
        enabled.forEach(entry => { entry.trigger.disabled = true; });
    });
});
window.addEventListener('pageshow', () => {
    submitting = false;
    enabled.forEach(entry => { entry.trigger.disabled = false; });
});
