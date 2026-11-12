<section class="welcome-row"><div><p class="eyebrow">BACKOFFICE</p><h1>Gestão Financeira</h1><p class="muted">Acesse as rotinas financeiras e acompanhe a preparação da integração fiscal.</p></div></section>
<section class="finance-module-grid">
    <a class="finance-module-card" href="<?= e(url('/backoffice/gestao-financeira/nfse')) ?>">
        <span class="finance-module-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
        <span class="finance-module-copy"><strong>Notas Fiscais de Serviço</strong><small>Integração NFS-e · Prefeitura de Ipatinga</small><em><i class="fa-solid fa-circle"></i> Novo módulo</em></span>
        <i class="fa-solid fa-arrow-right finance-module-arrow"></i>
    </a>
</section>
<style>
.finance-module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,340px));gap:14px;margin-top:16px}.finance-module-card{display:flex;align-items:center;gap:13px;min-width:0;padding:17px;border:1px solid var(--line);border-radius:13px;background:var(--surface);box-shadow:var(--shadow);color:var(--ink);text-decoration:none;transition:transform .18s ease,border-color .18s ease}.finance-module-card:hover{transform:translateY(-2px);border-color:var(--blue)}.finance-module-icon{display:grid;place-items:center;flex:0 0 48px;width:48px;height:48px;border-radius:12px;background:#e8efff;color:#3168d8;font-size:20px}.finance-module-copy{display:flex;flex:1;flex-direction:column;gap:5px;min-width:0}.finance-module-copy strong{font-size:12px}.finance-module-copy small{font-size:10px;color:var(--muted)}.finance-module-copy em{display:flex;align-items:center;gap:5px;font-size:9px;font-style:normal;color:#148b50}.finance-module-copy em i{font-size:6px}.finance-module-arrow{color:var(--muted);font-size:12px}
</style>
