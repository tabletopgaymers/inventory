function sizeSkuColumn() {
    const cells = document.querySelectorAll('.print-sku');
    if (!cells.length) return;

    let width = 0;
    for (const cell of cells) {
        const range = document.createRange();
        range.selectNodeContents(cell);
        const style = getComputedStyle(cell);
        width = Math.max(width, range.getBoundingClientRect().width
            + parseFloat(style.paddingLeft) + parseFloat(style.paddingRight)
            + parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth));
    }
    document.documentElement.style.setProperty('--app-print-sku-width', `${Math.ceil(width)}px`);
}

document.fonts.ready.then(sizeSkuColumn);
window.addEventListener('beforeprint', sizeSkuColumn);

document.querySelectorAll('[data-print-document]').forEach(button => {
    button.addEventListener('click', () => window.print());
});
