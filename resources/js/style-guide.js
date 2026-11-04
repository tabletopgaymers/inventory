import { updateStatusBadge } from './status-badges.js';
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
        window.appRequestDecision({message, origin:trigger, confirmLabel:action, title:action === 'Leave without saving' ? 'Unsaved changes' : 'Confirm action', cancelLabel:action === 'Leave without saving' ? 'Keep editing' : 'Cancel', variant:action === 'Apply setting' || action.startsWith('Grant ') ? 'primary' : 'danger', onConfirm:() => {
            const result = document.getElementById(trigger.hasAttribute('data-guide-decision') ? 'guide-decision-result' : 'guide-disable-result');
            if (result) {
                result.textContent = 'Preview confirmed. No records were changed and no navigation occurred.';
                result.hidden = false;
            }
        }});
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
    document.querySelector('[data-guide-status-choice]')?.addEventListener('change', event => {
        updateStatusBadge(document.querySelector('[data-guide-status-preview]'), event.target.value);
    });
    const outputs = [...document.querySelectorAll('[data-guide-density][data-guide-measure]')];
    const samples = new Map();
    if (outputs.length) {
        for (const density of ['compact', 'standard', 'spacious']) {
            const sample = document.createElement('div');
            sample.className = 'app-shell guide-density-probe'; sample.dataset.density = density;
            sample.setAttribute('aria-hidden', 'true'); sample.inert = true;
            const panel = document.createElement('div'); panel.className = 'app-panel';
            sample.append(panel); document.body.append(sample);
            samples.set(density, { sample, panel });
        }
    }
    const colorKeys = ['primary','page','surface','text-color','muted-color','border-color','danger-color','table-heading-color','table-group-color','--app-control-delete-color','--app-control-clear-color','--app-disabled-surface','--app-disabled-text','--app-disabled-border'];
    const colorAsHex = color => {
        if (color.startsWith('#')) return color.toLowerCase();
        const channels = color.match(/[\d.]+/g);
        if (!channels || channels.length < 3) return color;
        const rgb = channels.slice(0, 3).map(value => Math.round(Number(value)).toString(16).padStart(2, '0')).join('');
        const alpha = channels.length > 3 && Number(channels[3]) < 1
            ? Math.round(Number(channels[3]) * 255).toString(16).padStart(2, '0') : '';
        return '#' + rgb + alpha;
    };
    const updateMeasurements = () => {
        for (const output of outputs) {
            const { sample, panel } = samples.get(output.dataset.guideDensity);
            const style = getComputedStyle(sample);
            const key = output.dataset.guideMeasure;
            const token = name => style.getPropertyValue(name).trim();
            const values = {
                'font-family': token('--app-font-family'),
                'body-weight': token('--app-body-weight'),
                'page-title-weight': token('--app-page-title-weight'),
                'table-heading-weight': token('--app-table-heading-weight'),
                'table-group-weight': token('--app-table-group-weight'),
                'field-radius': token('--app-field-radius'),
                'detail-label-weight': token('--app-detail-label-weight'),
                'detail-value-style': 'Inherits body typography',
                'text-color': token('--app-text'),
                'muted-color': token('--app-muted'),
                'border-color': token('--app-border'),
                'danger-color': token('--app-danger'),
                'table-heading-color': token('--app-table-heading-surface'),
                'table-group-color': token('--app-table-group-surface'),
                'column-gap': token('--app-column-gap'),
                'flow-gap': token('--app-flow-gap'),
                'page-heading-gap': token('--app-page-heading-gap'),
                'section-heading-gap': token('--app-section-heading-gap'),
                'table-heading-gap': token('--app-table-heading-gap'),
                'column-widths': ['--app-column-min-small','--app-column-min-default','--app-column-min-wide'].map(token).join(' / '),
                'column-breakpoints': ['--breakpoint-columns-wide','--breakpoint-columns-narrow'].map(token).join(' / '),
                panel: getComputedStyle(panel).padding,
                button: token('--app-button-padding-y') + ' ' + token('--app-button-padding-x'),
                'body-size': token('--app-font-size'),
                'body-line-height': token('--app-density-line-height') + '× (' + style.lineHeight + ')',
                primary: token('--app-primary'),
                page: token('--app-page'),
                surface: token('--app-surface'),
            };
            output.textContent = key.startsWith('--') ? style.getPropertyValue(key).trim() : values[key];
            if (colorKeys.includes(key)) {
                output.textContent = colorAsHex(output.textContent);
                const swatch = document.createElement('span');
                swatch.className = `guide-color-swatch guide-color-${key}`;
                swatch.setAttribute('aria-hidden', 'true');
                output.prepend(swatch, ' ');
            }
        }
        // Fixed status and merged cells derive from the loaded preset values.
        const rows = new Set(outputs.map(output => output.closest('tr')));
        for (const row of rows) {
            const cells = [...row.querySelectorAll('[data-guide-measure]')];
            const fixed = cells.every(cell => cell.textContent === cells[0].textContent);
            cells[0].colSpan = fixed ? cells.length : 1;
            cells.forEach((cell, index) => { cell.hidden = fixed && index > 0; });
            let note = row.querySelector('[data-guide-fixed-note]');
            if (fixed && !note) {
                note = document.createElement('span'); note.dataset.guideFixedNote = '';
                note.className = 'app-field-help'; note.textContent = ' (Fixed)'; row.querySelector('th').append(note);
            }
            if (!fixed) note?.remove();
        }
    };
    updateMeasurements();
    window.addEventListener('resize', updateMeasurements);
})();
