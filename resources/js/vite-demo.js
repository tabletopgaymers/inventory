const demo = document.querySelector('[data-vite-demo]');
if (demo) {
    let count = 0;
    const output = demo.querySelector('[data-vite-count]');
    demo.querySelector('[data-vite-status]').textContent = 'Vite JavaScript loaded.';
    demo.querySelector('button').addEventListener('click', () => {
        output.textContent = String(++count);
    });
}
