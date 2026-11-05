const modal = document.getElementById('user-context-modal');
if (modal) {
    const dialog = modal.querySelector('[role="dialog"]');
    const closeButtons = modal.querySelectorAll('[data-context-close]');
    let previousFocus = null;

    const open = (trigger = null) => {
        previousFocus = trigger?.closest?.('#user-menu')
            ? document.getElementById('user-menu-trigger')
            : document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-context-modal-open');
        window.setTimeout(() => dialog?.focus(), 20);
    };

    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-context-modal-open');
        previousFocus?.focus?.();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-context-open]');
        if (trigger) {
            event.preventDefault();
            open(trigger);
        }
    });

    closeButtons.forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (!modal.classList.contains('is-open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key === 'Tab' && dialog) {
            const focusable = [...dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])')];
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}
