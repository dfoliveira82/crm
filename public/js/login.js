
const toggle=document.querySelector('[data-toggle-password]');
toggle?.addEventListener('click',()=>{const input=document.querySelector('#password');const show=input.type==='password';input.type=show?'text':'password';toggle.textContent=show?'Ocultar':'Mostrar';toggle.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');});
document.querySelector('.login-card')?.addEventListener('submit',e=>{const button=e.currentTarget.querySelector('[type=submit]');button.disabled=true;button.textContent='Validando...';});
