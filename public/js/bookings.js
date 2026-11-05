
export function initBookings(api){
 document.querySelectorAll('[data-range]').forEach(btn=>btn.addEventListener('click',async()=>{document.querySelectorAll('[data-range]').forEach(b=>b.classList.toggle('active',b===btn));const chart=document.querySelector('#bookings-chart');if(!chart)return;try{const data=await api(`/api/bookings?range=${btn.dataset.range}`);chart.replaceChildren();if(!data.items?.length){const empty=document.createElement('div');empty.className='empty-state';empty.textContent='Sem dados para o período selecionado.';chart.append(empty);}}catch(err){window.showToast?.(err.message);}}));
}
