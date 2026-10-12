(() => {
    const origins = new WeakMap();
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
