const ROOT = document.documentElement;
const MENU = document.getElementById('user-menu');
const TRIGGER = document.getElementById('user-menu-trigger');
const DROPDOWN = document.getElementById('user-menu-dropdown');
const THEME_KEY = 'selvar-theme';

function readTheme() {
    try {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === 'light' || saved === 'dark') return saved;
    } catch (_) {}
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme, persist = true) {
    const selected = theme === 'dark' ? 'dark' : 'light';
    ROOT.dataset.theme = selected;
    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
        const active = button.dataset.themeChoice === selected;
        button.setAttribute('aria-checked', active ? 'true' : 'false');
        button.classList.toggle('is-selected', active);
    });
    if (persist) {
        try { localStorage.setItem(THEME_KEY, selected); } catch (_) {}
    }
}

function setOpen(open, focusFirst = false) {
    if (!MENU || !TRIGGER || !DROPDOWN) return;
    MENU.classList.toggle('is-open', open);
    TRIGGER.setAttribute('aria-expanded', open ? 'true' : 'false');
    DROPDOWN.hidden = !open;
    if (open && focusFirst) DROPDOWN.querySelector('[role="menuitem"]')?.focus();
}

applyTheme(readTheme(), false);

if (MENU && TRIGGER && DROPDOWN) {
    TRIGGER.addEventListener('click', () => setOpen(TRIGGER.getAttribute('aria-expanded') !== 'true'));

    MENU.addEventListener('click', (event) => {
        if (event.target.closest('[data-context-open]')) {
            setOpen(false);
            return;
        }
        const themeButton = event.target.closest('[data-theme-choice]');
        if (!themeButton) return;
        applyTheme(themeButton.dataset.themeChoice);
        setOpen(false);
        TRIGGER.focus();
    });

    document.addEventListener('click', (event) => {
        if (!MENU.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (TRIGGER.getAttribute('aria-expanded') !== 'true') return;
        if (event.key === 'Escape') {
            event.preventDefault(); setOpen(false); TRIGGER.focus(); return;
        }
        const items = [...DROPDOWN.querySelectorAll('[role="menuitem"]')].filter((item) => !item.disabled);
        const current = items.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const delta = event.key === 'ArrowDown' ? 1 : -1;
            items[(current + delta + items.length) % items.length]?.focus();
        } else if (event.key === 'Home') {
            event.preventDefault(); items[0]?.focus();
        } else if (event.key === 'End') {
            event.preventDefault(); items.at(-1)?.focus();
        }
    });

    DROPDOWN.querySelectorAll('[role="menuitem"]').forEach((item) => {
        item.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') setOpen(false);
        });
    });
}
