(() => {
    const origins = new WeakMap();
    const decisionDialog = document.getElementById('guide-decision-dialog');
    document.querySelectorAll('[data-guide-decision]').forEach(trigger => {
        if (!decisionDialog) return;
        trigger.addEventListener('click', () => {
            origins.set(decisionDialog, trigger);
            const action = trigger.dataset.guideDecisionLabel;
            const unsaved = action === 'Leave without saving';
            decisionDialog.querySelector('#guide-decision-title').textContent = unsaved ? 'Unsaved changes' : 'Confirm action';
            decisionDialog.querySelector('#guide-decision-description').textContent = trigger.dataset.guideDecision;
            decisionDialog.querySelector('#guide-decision-cancel').textContent = unsaved ? 'Keep editing' : 'Cancel';
            const confirm = decisionDialog.querySelector('#guide-decision-confirm');
            confirm.textContent = action;
            confirm.className = unsaved ? 'app-user-disable-confirm' : 'app-user-role-grant-confirm';
            decisionDialog.showModal();
        });
    });
    document.querySelector('[data-guide-decision-confirm]')?.addEventListener('click', () => {
        const result = document.getElementById('guide-decision-result');
        result.textContent = 'Preview confirmed. No records were changed and no navigation occurred.';
        result.hidden = false;
    });
    const widthToggle = document.querySelector('[data-guide-width-toggle]');
    const columnPanel = document.getElementById('guide-column-panel');
    widthToggle?.addEventListener('click', () => {
        const narrow = widthToggle.getAttribute('aria-pressed') !== 'true';
        columnPanel.style.maxWidth = narrow ? '24rem' : '';
        widthToggle.setAttribute('aria-pressed', String(narrow));
        widthToggle.textContent = narrow ? 'Show full-width panel' : 'Show narrow panel';
    });
    document.querySelectorAll('[data-guide-open]').forEach(trigger => {
        const dialog = document.getElementById(trigger.dataset.guideOpen);
        if (!dialog) return;
        trigger.addEventListener('click', () => {
            origins.set(dialog, trigger);
            if (trigger.dataset.guideRoleLabel) {
                dialog.querySelector('#guide-role-description').textContent = trigger.dataset.guideRoleMessage;
                const confirm = dialog.querySelector('#guide-role-confirm');
                confirm.textContent = trigger.dataset.guideRoleLabel;
                confirm.className = trigger.dataset.guideRoleLabel.startsWith('Remove ') ? 'app-user-disable-confirm' : 'app-user-role-grant-confirm';
            }
            dialog.showModal();
        });
    });
    document.querySelectorAll('dialog.app-user-disable-dialog').forEach(dialog => {
        dialog.addEventListener('close', () => origins.get(dialog)?.focus());
        dialog.querySelectorAll('[data-guide-close]').forEach(button => {
            button.addEventListener('click', () => {
                dialog.close();
                if (button.hasAttribute('data-guide-preview-confirm')) {
                    const result = document.getElementById('guide-disable-result');
                    if (result) {
                        result.textContent = 'Preview confirmed. No account was changed.';
                        result.hidden = false;
                    }
                }
            });
        });
    });
    const outputs = [...document.querySelectorAll('[data-guide-density][data-guide-measure]')];
    const samples = new Map();
    if (outputs.length) {
        for (const density of ['compact', 'standard', 'spacious']) {
            const sample = document.createElement('div');
            sample.className = 'app-shell'; sample.dataset.density = density;
            sample.setAttribute('aria-hidden', 'true'); sample.inert = true;
            sample.style.cssText = 'position:fixed;left:-10000px;visibility:hidden;pointer-events:none;min-height:0;';
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
