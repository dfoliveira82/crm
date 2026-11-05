<section class="login-layout">
    <aside class="login-brand">
        <div class="brand-mark"><img src="/img/icone branco.png" height="40px" width="40px"/></div>
        <div class="brand-copy"><span>FADIPA</span><small>Sistema Gerencial</small></div>
        <div class="brand-message">
            <p class="eyebrow">SISTEMA DE GESTÃO EDUCACIONAL</p>
            <h1>Clareza para avançar.<br><em>Resultados para crescer.</em></h1>
        </div>
        <footer>© <?= date('Y') ?> FADIPA · SGE · Depto TIC</footer>
    </aside>
    <main class="login-main">
        <form class="login-card" method="post" action="<?= e(url('login')) ?>">
            <div class="mobile-brand">
                <div class="brand-mark">S</div><strong>FADIPA</strong>
            </div>
            <p class="eyebrow">BEM-VINDO DE VOLTA</p>
            <h2>Acesse sua conta</h2>
            <p class="muted">Entre com suas credenciais do TOTVS RM.</p><?php if (!empty($error)): ?><div class="form-alert" role="alert" aria-live="polite"><?= e($error) ?></div><?php endif; ?><?= csrf_field() ?><label for="username">Usuário ou e-mail</label><input id="username" name="username" type="text" autocomplete="username" required value="<?= e($oldUser ?? '') ?>"><label for="password">Senha</label>
                <div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" required><button type="button" class="password-toggle" aria-label="Mostrar senha" data-toggle-password>Mostrar</button></div>
                <div class="login-options"><label class="check-label"><input type="checkbox" name="remember" value="1"> Manter conectado</label></div><button class="btn btn-primary login-submit" type="submit">Entrar <span>→</span></button>
                <p class="login-help">Use seu acesso do TOTVS RM</p>
        </form>
    </main>
</section>