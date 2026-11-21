// Each footer control owns its payload and label timer; buttons retain native keyboard support.
document.querySelectorAll('[data-release-copy]').forEach(copyButton => {
    const copyStatus = copyButton.closest('[data-release-footer]')?.querySelector('[data-release-copy-status]');
    const originalLabel = [...copyButton.childNodes].map(node => node.cloneNode(true));
    let resetLabel;
    copyButton.addEventListener('click', async () => {
        if (copyStatus) copyStatus.textContent = '';
        try {
            await navigator.clipboard.writeText(copyButton.dataset.releaseCopy);
            clearTimeout(resetLabel);
            copyButton.textContent = 'Copied to clipboard';
            if (copyStatus) copyStatus.textContent = 'Copied to clipboard';
            resetLabel = setTimeout(() => {
                copyButton.replaceChildren(...originalLabel.map(node => node.cloneNode(true)));
            }, 2000);
        } catch {
            if (copyStatus) copyStatus.textContent = 'Unable to copy to clipboard. Please try again.';
        }
    });
});
