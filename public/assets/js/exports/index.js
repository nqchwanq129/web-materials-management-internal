(() => {
    const menu = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const setMenu = (open) => {
        document.body.classList.toggle('menu-open', open);
        menu.setAttribute('aria-expanded', String(open));
        menu.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
    };
    menu.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
            setMenu(false);
            menu.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!sidebar.contains(event.target) && !menu.contains(event.target)) setMenu(false);
    });
    const form = document.querySelector('.import-filters');
    const buttons = [...form.querySelectorAll('button[type="submit"]')];
    buttons.forEach((button) => { button.dataset.label = button.textContent; });
    form.addEventListener('submit', () => {
        buttons.forEach((button) => {
            button.textContent = 'Đang tải…';
            button.disabled = true;
        });
    });
    window.addEventListener('pageshow', () => {
        buttons.forEach((button) => {
            button.textContent = button.dataset.label;
            button.disabled = false;
        });
    });
})();
