<?php
use App\Core\Session;
$globalContexts = Session::get('user_contexts', []);
$globalContexts = is_array($globalContexts) ? $globalContexts : [];
$globalActiveContext = Session::get('active_context', []);
$globalActiveContext = is_array($globalActiveContext) ? $globalActiveContext : [];
$contextIsCurrent = static function (array $context) use ($globalActiveContext): bool {
    return (int)($globalActiveContext['CODCOLIGADA'] ?? -1) === (int)($context['CODCOLIGADA'] ?? -2)
        && (int)($globalActiveContext['CODFILIAL'] ?? -1) === (int)($context['CODFILIAL'] ?? -2)
        && (int)($globalActiveContext['CODTIPOCURSO'] ?? -1) === (int)($context['CODTIPOCURSO'] ?? -2);
};
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$contextReturnTo = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$query = parse_url($requestUri, PHP_URL_QUERY);
if (is_string($query) && $query !== '') $contextReturnTo .= '?' . $query;
if (!str_starts_with($contextReturnTo, '/') || str_starts_with($contextReturnTo, '//')) $contextReturnTo = '/';
?>
<?php if ($globalContexts): ?>
<div class="user-context-modal" id="user-context-modal" aria-hidden="true">
    <button class="user-context-backdrop" type="button" data-context-close aria-label="Fechar janela"></button>
    <section class="user-context-dialog" role="dialog" aria-modal="true" aria-labelledby="user-context-title" tabindex="-1">
        <header class="user-context-dialog-header">
            <div>
                <span class="user-context-kicker">CONFIGURAÇÃO DE ACESSO</span>
                <h2 id="user-context-title">Alterar contexto</h2>
                <p>Selecione uma das filiais e modalidades liberadas para seu usuário no RM.</p>
            </div>
            <button class="user-context-close" type="button" data-context-close aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="user-context-options">
            <?php foreach ($globalContexts as $context): ?>
                <form method="post" action="<?= e(url('usuario/contexto')) ?>" class="user-context-option-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cod_coligada" value="<?= (int)$context['CODCOLIGADA'] ?>">
                    <input type="hidden" name="cod_filial" value="<?= (int)$context['CODFILIAL'] ?>">
                    <input type="hidden" name="cod_tipo_curso" value="<?= (int)$context['CODTIPOCURSO'] ?>">
                    <input type="hidden" name="return_to" value="<?= e($contextReturnTo) ?>">
                    <button class="user-context-option <?= $contextIsCurrent($context) ? 'is-current' : '' ?>" type="submit" <?= $contextIsCurrent($context) ? 'aria-current="true" disabled' : '' ?>>
                        <span class="user-context-option-icon"><i class="fa-solid fa-building-columns"></i></span>
                        <span class="user-context-option-copy">
                            <strong><?= e($context['FILIAL'] ?? '') ?></strong>
                            <small><?= e($context['TIPOCURSO'] ?? '') ?></small>
                            <small><?= e($context['NOMEFACULDADE'] ?? '') ?></small>
                        </span>
                        <?php if ($contextIsCurrent($context)): ?>
                            <span class="user-context-current-label"><i class="fa-solid fa-check"></i> ATUAL</span>
                        <?php else: ?>
                            <i class="fa-solid fa-chevron-right user-context-option-arrow"></i>
                        <?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
        <footer class="user-context-dialog-footer"><i class="fa-solid fa-lock"></i> Somente contextos autorizados no RM podem ser selecionados.</footer>
    </section>
</div>
<?php endif; ?>
