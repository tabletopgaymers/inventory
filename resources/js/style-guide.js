import { mountTabs } from './workflow/tabs.js';
import './app.js';

(() => {
    const tabForm = document.getElementById('guide-tab-form');
    if (tabForm) {
        tabForm.addEventListener('submit', event => event.preventDefault());
        const storageKey = 'style-guide:tab-form:v1';
        const carriers = ['US Postal', 'UPS', 'FedEx', 'Other'];
        const state = { status: 'New', name: '', source: '', destination: '', button_type: '', button_request: '', button_fulfilled: '', approved: false, signature: '', shippers: [] };
        const feedback = tabForm.querySelector('[data-tab-storage-feedback]');
        const storageError = () => {
            feedback.hidden = false;
            feedback.textContent = 'Browser storage is unavailable. Values remain on this page but may be lost when you leave.';
        };
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey));
            if (saved && typeof saved === 'object') {
                Object.keys(state).filter(key => !['approved', 'shippers'].includes(key)).forEach(key => {
                    if (typeof saved[key] === 'string') state[key] = saved[key];
                });
                state.approved = saved.approved === true;
                if (!['New', 'Draft', 'Requested', 'Accepted', 'Fulfilled', 'Completed'].includes(state.status)) state.status = 'New';
                if (Array.isArray(saved.shippers)) state.shippers = saved.shippers.map(line => ({
                    shipper: carriers.includes(line?.shipper) ? line.shipper : 'US Postal',
                    tracker: typeof line?.tracker === 'string' ? line.tracker : '',
                }));
            }
        } catch { storageError(); }
        const persist = () => {
            try { localStorage.setItem(storageKey, JSON.stringify(state)); }
            catch { storageError(); }
        };
        const fields = [...tabForm.querySelectorAll('[data-tab-field]')];
        const applyStatus = () => {
            const status = state.status;
            const messages = [null,
                status === 'New' ? 'Must complete basic info first' : null,
                status === 'New' ? 'Must complete basic info first' : null,
                ['New', 'Draft'].includes(status) ? 'not available' : status === 'Requested' ? 'Must Accept First' : null,
                ['New', 'Draft', 'Requested'].includes(status) ? (status === 'Requested' ? 'Not Available' : 'not available') : null];
            tabForm.querySelectorAll('[role="tabpanel"]').forEach((panel, index) => {
                const message = panel.querySelector('[data-tab-unavailable]');
                message.textContent = messages[index] || '';
                message.hidden = !messages[index];
                panel.querySelector('[data-tab-content]').hidden = Boolean(messages[index]);
                panel.querySelectorAll('input, select, button:not([data-tab-action])').forEach(control => {
                    const editable = !messages[index] && (
                        (index === 0 && ['New', 'Draft'].includes(status)) ||
                        (index === 1 && status === 'Draft' && control.dataset.tabField !== 'button_fulfilled') ||
                        (index === 2 && status === 'Draft') ||
                        (index === 4 && status === 'Accepted') ||
                        (index === 3 && status === 'Accepted' && control.dataset.tabField === 'button_fulfilled'));
                    // HTML has no readonly state for checkboxes, selects or buttons.
                    if (control.tagName === 'INPUT' && control.type !== 'checkbox') control.readOnly = !editable;
                    else control.disabled = !editable;
                });
                const action = panel.querySelector('[data-tab-action]');
                const enabled = index === 0 ? ['New', 'Draft'].includes(status)
                    : index === 1 || index === 2 ? status === 'Draft'
                    : index === 3 ? status === 'Requested'
                    : status === 'Accepted';
                action.disabled = !enabled;
            });
        };
        const sync = () => {
            fields.forEach(field => {
                if (field.type === 'checkbox') field.checked = state[field.dataset.tabField];
                else field.value = state[field.dataset.tabField];
            });
            tabForm.querySelectorAll('[data-tab-review]').forEach(output => {
                output.textContent = state[output.dataset.tabReview] || '—';
            });
            applyStatus();
        };
        fields.forEach(field => field.addEventListener(field.type === 'checkbox' || field.tagName === 'SELECT' ? 'change' : 'input', () => {
            if (field.readOnly || field.disabled) return;
            state[field.dataset.tabField] = field.type === 'checkbox' ? field.checked : field.value;
            sync();
            persist();
        }));
        const shipperList = tabForm.querySelector('[data-tab-shippers]');
        const renderShippers = () => {
            shipperList.replaceChildren();
            state.shippers.forEach((line, index) => {
                const group = tabForm.querySelector('[data-tab-shipper-template]').content.firstElementChild.cloneNode(true);
                group.querySelector('legend').textContent = `Shipper ${index + 1}`;
                const select = group.querySelector('[data-shipper-carrier]');
                select.name = `shippers[${index}][shipper]`;
                select.value = line.shipper;
                select.addEventListener('change', () => { if (state.status === 'Accepted') { line.shipper = select.value; persist(); } });
                const input = group.querySelector('[data-shipper-tracker]');
                input.name = `shippers[${index}][tracker]`;
                input.value = line.tracker;
                input.addEventListener('input', () => { if (state.status === 'Accepted') { line.tracker = input.value; persist(); } });
                const remove = group.querySelector('[data-shipper-delete]');
                remove.setAttribute('aria-label', `Delete shipper ${index + 1}`);
                remove.addEventListener('click', () => {
                    if (state.status !== 'Accepted') return;
                    state.shippers.splice(index, 1); persist(); renderShippers();
                    tabForm.querySelector('[data-tab-add-shipper]').focus();
                });
                shipperList.append(group);
            });
            applyStatus();
        };
        tabForm.querySelector('[data-tab-add-shipper]').addEventListener('click', () => {
            if (state.status !== 'Accepted') return;
            state.shippers.push({ shipper: 'US Postal', tracker: '' });
            persist(); renderShippers();
            shipperList.lastElementChild.querySelector('select').focus();
        });
        sync();
        renderShippers();
        const tabs = [...tabForm.querySelectorAll('[role="tab"]')];
        const tabUI = mountTabs(tabForm, {
            initialKey: new URL(location.href).searchParams.get('tab') || 'basic',
            onChange: (key, tab) => { tabForm.querySelector('[data-tab-section-title]').textContent = tab.textContent; }
        });
        const selectTab = selected => tabUI.activate(selected.dataset.workflowTab);
        tabForm.querySelectorAll('[data-tab-action]').forEach(button => {
            button.addEventListener('click', () => {
                if (button.disabled) return;
                switch (button.dataset.tabAction) {
                    case 'next': state.status = 'Draft'; selectTab(tabs[1]); break;
                    case 'review': selectTab(tabs[2]); break;
                    case 'submit': state.status = 'Requested'; break;
                    case 'accept': state.status = 'Accepted'; break;
                    case 'ship': state.status = 'Completed'; break;
                }
                sync();
                persist();
            });
        });

    }
    const previewDecision = trigger => {
        const action = trigger.dataset.guideDecisionLabel || trigger.dataset.guideRoleLabel || 'Disable account';
        const message = trigger.dataset.guideDecision || trigger.dataset.guideRoleMessage
            || 'Disabling an account will remove any existing roles and will prevent the user from signing in.';
        window.appRequestDecision(message, () => {
            const result = document.getElementById(trigger.hasAttribute('data-guide-decision') ? 'guide-decision-result' : 'guide-disable-result');
            if (result) {
                result.textContent = 'Preview confirmed. No records were changed and no navigation occurred.';
                result.hidden = false;
            }
        }, trigger, action, true, action === 'Leave without saving' ? 'Unsaved changes' : 'Confirm action');
    };
    document.querySelectorAll('[data-guide-decision], [data-guide-open]').forEach(trigger => {
        trigger.addEventListener('click', () => previewDecision(trigger));
    });
    const widthToggle = document.querySelector('[data-guide-width-toggle]');
    const columnPanel = document.getElementById('guide-column-panel');
    widthToggle?.addEventListener('click', () => {
        const narrow = widthToggle.getAttribute('aria-pressed') !== 'true';
        columnPanel.classList.toggle('guide-column-panel--narrow', narrow);
        widthToggle.setAttribute('aria-pressed', String(narrow));
        widthToggle.textContent = narrow ? 'Show full-width panel' : 'Show narrow panel';
    });
    const outputs = [...document.querySelectorAll('[data-guide-density][data-guide-measure]')];
    const samples = new Map();
    if (outputs.length) {
        for (const density of ['compact', 'standard', 'spacious']) {
            const sample = document.createElement('div');
            sample.className = 'app-shell guide-density-probe'; sample.dataset.density = density;
            sample.setAttribute('aria-hidden', 'true'); sample.inert = true;
            const panel = document.createElement('div'); panel.className = 'app-panel';
            const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Sample';
            panel.append(button); sample.append(panel); document.body.append(sample);
            samples.set(density, { sample, panel, button });
        }
    }
    const updateMeasurements = () => {
        for (const output of outputs) {
            const { sample, panel, button } = samples.get(output.dataset.guideDensity);
            const style = getComputedStyle(sample);
            const key = output.dataset.guideMeasure;
            const values = {
                panel: getComputedStyle(panel).padding,
                button: getComputedStyle(button).padding,
                body: style.fontSize + ' / line height ' + style.lineHeight,
                primary: style.getPropertyValue('--app-primary').trim(),
                page: style.backgroundColor,
                surface: getComputedStyle(panel).backgroundColor,
            };
            output.textContent = key.startsWith('--') ? style.getPropertyValue(key).trim() : values[key];
        }
    };
    updateMeasurements();
    window.addEventListener('resize', updateMeasurements);
})();
