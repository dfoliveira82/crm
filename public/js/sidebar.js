const shell = document.querySelector('.app-shell');
const sidebar = document.getElementById('sidebar');
const desktopToggle = document.getElementById('sidebar-collapse-toggle');
const mobileToggle = document.getElementById('sidebar-toggle');
const backdrop = document.getElementById('sidebar-backdrop');
const storageKey = 'selvar-sidebar-collapsed';
const desktopQuery = window.matchMedia('(min-width: 761px)');

if (shell && sidebar) {
    const setDesktopCollapsed = (collapsed, persist = true) => {
        shell.classList.toggle('sidebar-collapsed', collapsed);
        desktopToggle?.setAttribute('aria-expanded', String(!collapsed));
        desktopToggle?.setAttribute('aria-label', collapsed ? 'Expandir menu lateral' : 'Recolher menu lateral');
        if (desktopToggle) {
            desktopToggle.title = collapsed ? 'Expandir menu lateral' : 'Recolher menu lateral';
            const icon = desktopToggle.querySelector('i');
            if (icon) icon.className = `fa-solid ${collapsed ? 'fa-angles-right' : 'fa-angles-left'}`;
        }
        if (persist) {
            savedCollapsed = collapsed;
            try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (_) {}
        }
    };

    const closeMobile = () => {
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-mobile-open');
        backdrop?.setAttribute('hidden', '');
        mobileToggle?.setAttribute('aria-expanded', 'false');
        mobileToggle?.setAttribute('aria-label', 'Abrir menu');
        const icon = mobileToggle?.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bars';
    };

    const openMobile = () => {
        sidebar.classList.add('open');
        document.body.classList.add('sidebar-mobile-open');
        backdrop?.removeAttribute('hidden');
        mobileToggle?.setAttribute('aria-expanded', 'true');
        mobileToggle?.setAttribute('aria-label', 'Fechar menu');
        const icon = mobileToggle?.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-xmark';
        desktopToggle?.blur();
    };

    let savedCollapsed = false;
    try { savedCollapsed = localStorage.getItem(storageKey) === '1'; } catch (_) {}
    setDesktopCollapsed(desktopQuery.matches && savedCollapsed, false);

    desktopToggle?.addEventListener('click', () => {
        if (!desktopQuery.matches) return;
        setDesktopCollapsed(!shell.classList.contains('sidebar-collapsed'));
    });

    mobileToggle?.addEventListener('click', () => {
        if (desktopQuery.matches) return;
        sidebar.classList.contains('open') ? closeMobile() : openMobile();
    });
    backdrop?.addEventListener('click', closeMobile);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('open')) {
            closeMobile();
            mobileToggle?.focus();
        }
    });

    desktopQuery.addEventListener?.('change', (event) => {
        closeMobile();
        setDesktopCollapsed(event.matches && savedCollapsed, false);
    });

    sidebar.querySelectorAll('.side-nav a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktopQuery.matches) closeMobile();
        });
    });

    // Em modo recolhido, expandir a sidebar antes de abrir um submenu.
    sidebar.querySelectorAll('.nav-group-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            if (desktopQuery.matches && shell.classList.contains('sidebar-collapsed')) {
                setDesktopCollapsed(false);
            }
        }, { capture: true });
    });
}
