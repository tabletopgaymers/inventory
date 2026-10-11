(() => {
    const drawer = document.getElementById('mobile-navigation');
    const openButton = document.getElementById('navigation-open');
    const closeButton = document.getElementById('navigation-close');
    const account = document.getElementById('account-disclosure');
    const narrow = window.matchMedia('(max-width: 63.999rem)');
    let destinationSelected = false;
    if (!drawer || typeof drawer.showModal !== 'function') return;

    document.body.classList.add('navigation-ready');
    const sync = () => {
        openButton.hidden = !narrow.matches;
        if (!narrow.matches && drawer.open) drawer.close('resize');
    };
    sync();
    narrow.addEventListener('change', sync);
    openButton.addEventListener('click', () => {
        account.open = false;
        drawer.showModal();
        openButton.setAttribute('aria-expanded', 'true');
    });
    closeButton.addEventListener('click', () => drawer.close());
    drawer.addEventListener('close', () => {
        openButton.setAttribute('aria-expanded', 'false');
        if (destinationSelected) document.getElementById('main').focus();
        else if (narrow.matches) openButton.focus();
        else document.getElementById('main').focus();
        destinationSelected = false;
    });
    drawer.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...drawer.querySelectorAll('button, a[href]')];
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        destinationSelected = true;
        drawer.close();
    }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && account.open) {
            account.open = false;
            account.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', event => {
        if (account.open && !account.contains(event.target)) account.open = false;
    });
})();
