
export function initAnimations(){
 document.querySelectorAll('[data-count]').forEach(el=>{const target=Number(el.dataset.count||0);let n=0;const step=Math.max(1,target/30);const timer=setInterval(()=>{n=Math.min(target,n+step);el.textContent=Math.round(n).toLocaleString('pt-BR');if(n>=target)clearInterval(timer);},25);});
 document.querySelectorAll('.progress-track i').forEach(el=>{const width=el.style.width;el.style.width='0';requestAnimationFrame(()=>el.style.transition='width .8s ease');requestAnimationFrame(()=>el.style.width=width);});
}
