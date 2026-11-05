
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
export async function api(path, options={}) {
  const headers = {'Accept':'application/json', ...(options.headers||{})};
  if (!['GET','HEAD'].includes((options.method||'GET').toUpperCase())) headers['X-CSRF-Token']=csrf();
  if (options.body && typeof options.body !== 'string') { headers['Content-Type']='application/json'; options.body=JSON.stringify(options.body); }
  const response = await fetch(path,{...options,headers,credentials:'same-origin'});
  if(response.status===401){location.href='/login';throw new Error('Sessão expirada');}
  const data=await response.json().catch(()=>({}));
  if(!response.ok) throw new Error(data.error||'Não foi possível concluir a operação.');
  return data;
}
