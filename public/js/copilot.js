
export function initCopilot(api){
 const list=document.querySelector('#suggestions-list');
 list?.addEventListener('click',async e=>{const btn=e.target.closest('[data-dismiss]');if(!btn)return;const item=btn.closest('[data-id]');try{await api(`/api/copilot/suggestions/${encodeURIComponent(item.dataset.id)}`,{method:'DELETE'});item.remove();window.showToast?.('Sugestão dispensada');}catch(err){window.showToast?.(err.message);}});
 document.querySelector('#copilot-form')?.addEventListener('submit',async e=>{e.preventDefault();const input=e.currentTarget.elements.message;const message=input.value.trim();if(!message)return;input.value='';const area=document.querySelector('#copilot-replies');const user=document.createElement('p');user.textContent=`Você: ${message}`;area.append(user);try{const data=await api('/api/copilot/chat',{method:'POST',body:{message}});const reply=document.createElement('p');reply.textContent=`Copilot: ${data.reply}`;area.append(reply);}catch(err){window.showToast?.(err.message);}});
}
