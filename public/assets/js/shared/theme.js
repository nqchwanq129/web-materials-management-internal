// Navigation support shared by the classic pages and the newer layouts.
(() => {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.menu-toggle');
    if (!sidebar || !toggle) return;
    const close = () => {
        document.body.classList.remove('menu-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Mở menu');
    };
    if (document.body.classList.contains('migrated-page')) {
        toggle.addEventListener('click', () => {
            const open = document.body.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
            close();
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) close();
    });
    window.matchMedia('(min-width: 701px)').addEventListener('change', (event) => {
        if (event.matches) close();
    });
})();
