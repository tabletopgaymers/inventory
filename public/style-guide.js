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
    const updateMeasurements = () => {
        const panel = document.querySelector('.app-main > .app-content');
        const button = document.getElementById('guide-padding-button');
        const values = {
            panel: panel ? getComputedStyle(panel).padding : '',
            button: button ? getComputedStyle(button).padding : '',
            body: getComputedStyle(document.body).fontSize + ' / line height ' + getComputedStyle(document.body).lineHeight,
            primary: getComputedStyle(document.body).getPropertyValue('--app-primary').trim(),
            page: getComputedStyle(document.body).backgroundColor,
            surface: panel ? getComputedStyle(panel).backgroundColor : '',
        };
        document.querySelectorAll('[data-guide-measure]').forEach(output => {
            output.textContent = values[output.dataset.guideMeasure] || 'See the shared component';
        });
    };
    updateMeasurements();
    window.addEventListener('resize', updateMeasurements);
})();
