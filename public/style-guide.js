(() => {
    const origins = new WeakMap();
    document.querySelectorAll('[data-guide-open]').forEach(trigger => {
        const dialog = document.getElementById(trigger.dataset.guideOpen);
        if (!dialog) return;
        trigger.addEventListener('click', () => {
            origins.set(dialog, trigger);
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
