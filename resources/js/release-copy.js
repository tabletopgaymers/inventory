// Shared footer version copy; native buttons provide Enter and Space support.
const copyButton = document.querySelector('[data-release-copy]');
const copyStatus = document.querySelector('[data-release-copy-status]');
const versionLabel = copyButton ? [...copyButton.childNodes].map(node => node.cloneNode(true)) : [];
let resetLabel;
copyButton?.addEventListener('click', async () => {
    if (copyStatus) copyStatus.textContent = '';
    try {
        await navigator.clipboard.writeText(copyButton.dataset.releaseCopy);
        clearTimeout(resetLabel);
        copyButton.textContent = 'Copied to clipboard';
        if (copyStatus) copyStatus.textContent = 'Copied to clipboard';
        resetLabel = setTimeout(() => { copyButton.replaceChildren(...versionLabel.map(node => node.cloneNode(true))); }, 2000);
    } catch {
        if (copyStatus) copyStatus.textContent = 'Unable to copy Git version information. Please try again.';
    }
});
