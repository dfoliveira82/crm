
import {api} from './api.js';
import {initAnimations} from './animations.js';
import {initCopilot} from './copilot.js';
import {initBookings} from './bookings.js';
import './user-menu.js';
import './user-context.js';
import './sidebar.js';
const $=s=>document.querySelector(s);

document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();$('#global-search')?.focus();}});
window.showToast=(message)=>{const region=$('#toast-region');if(!region)return;const el=document.createElement('div');el.className='toast';el.textContent=message;region.append(el);setTimeout(()=>el.remove(),3200);};
initAnimations();initCopilot(api);initBookings(api);


// Expansão/recolhimento dos grupos da navegação lateral.
document.querySelectorAll('.nav-group-toggle').forEach((button) => {
    button.addEventListener('click', () => {
        const group = button.closest('.nav-group');
        const submenu = group?.querySelector('.nav-submenu');
        if (!group || !submenu) return;

        const willOpen = !group.classList.contains('is-open');
        document.querySelectorAll('.nav-group').forEach((otherGroup) => {
            otherGroup.classList.remove('is-open');
            const otherButton = otherGroup.querySelector('.nav-group-toggle');
            const otherMenu = otherGroup.querySelector('.nav-submenu');
            otherButton?.setAttribute('aria-expanded', 'false');
            if (otherMenu) otherMenu.hidden = true;
        });

        if (willOpen) {
            group.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
            submenu.hidden = false;
        }
    });
});
