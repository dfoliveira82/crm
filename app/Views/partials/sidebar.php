<?php
$current = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$base = trim(parse_url(url(), PHP_URL_PATH) ?? '', '/');
if ($base !== '' && str_starts_with($current, $base)) {
    $current = trim(substr($current, strlen($base)), '/');
}
$u = $user ?? user_data();
$name = $u['name'] ?? 'Usuário';
$initials = strtoupper(substr($name, 0, 1));
$sideAvatar = trim((string)($u['avatar_data_uri'] ?? ''));
if ($sideAvatar !== '' && !preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$#', $sideAvatar)) $sideAvatar = '';

$navigation = [
    ['label' => 'RH', 'icon' => 'fa-users', 'items' => [
        ['label' => 'Gestão de Ponto', 'path' => 'rh/gestao-de-ponto'],
        ['label' => 'Gestão de Folha de Pagamento', 'path' => 'rh/gestao-de-folha-de-pagamento'],
        ['label' => 'Gestão de Pessoas', 'path' => 'rh/gestao-de-pessoas'],
        ['label' => 'Gestão SESMT', 'path' => 'rh/gestao-sesmt'],
    ]],
    ['label' => 'Educacional', 'icon' => 'fa-graduation-cap', 'items' => [
        ['label' => 'Avaliação e Pesquisa', 'path' => 'educacional/avaliacao-e-pesquisa'],
        ['label' => 'Atendimentos e Aprovações', 'path' => 'educacional/atendimentos-e-aprovacoes'],
        ['label' => 'Gestão Bibliotecária', 'path' => 'educacional/gestao-bibliotecaria'],
        ['label' => 'Gestão Educacional', 'path' => 'educacional/gestao-educacional'],
        ['label' => 'Gestão de Processo Seletivo', 'path' => 'educacional/gestao-de-processo-seletivo'],
    ]],
    ['label' => 'Backoffice', 'icon' => 'fa-briefcase', 'items' => [
        ['label' => 'Gestão Contábil', 'path' => 'backoffice/gestao-contabil'],
        ['label' => 'Gestão de Compras', 'path' => 'backoffice/gestao-de-compras'],
        ['label' => 'Gestão Financeira', 'path' => 'backoffice/gestao-financeira'],
        ['label' => 'Gestão Fiscal', 'path' => 'backoffice/gestao-fiscal'],
        ['label' => 'Gestão Patrimonial', 'path' => 'backoffice/gestao-patrimonial'],
    ]],
    ['label' => 'AVA', 'icon' => 'fa-book-open', 'items' => [
        ['label' => 'Gestão de Alunos', 'path' => 'ava/gestao-de-alunos'],
        ['label' => 'Gestão de Cursos', 'path' => 'ava/gestao-de-cursos'],
        ['label' => 'Gestão de Turmas', 'path' => 'ava/gestao-de-turmas'],
        ['label' => 'Gestão de Conteúdo', 'path' => 'ava/gestao-de-conteudo'],
    ]],
    ['label' => 'Suporte', 'icon' => 'fa-headset', 'items' => [
        ['label' => 'Dashboard', 'path' => 'suporte/dashboard'],
        ['label' => 'Listar Ticket', 'path' => 'suporte/listar-ticket'],
        ['label' => 'Abrir Ticket', 'path' => 'suporte/abrir-ticket'],
        ['label' => 'Alterar Senha', 'path' => 'suporte/alterar-senha'],
        ['label' => 'Cadastros', 'path' => 'suporte/cadastros'],
    ]],
];
$expandedGroup = null;
foreach ($navigation as $index => $group) {
    foreach ($group['items'] as $item) {
        if ($current === $item['path'] || str_starts_with($current, $item['path'] . '/')) {
            $expandedGroup = $index;
            break 2;
        }
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="side-brand-row">
        <a class="side-brand" href="<?= e(url()) ?>" aria-label="SGU — Dashboard">
            <span class="side-logo"><img src="<?= e(asset('img/icone branco.png')) ?>" alt="FADIPA" height="40" width="40"></span>
            <span class="side-brand-text"><b>SGU</b><small>FADIPA / Sistema Gerencial</small></span>
        </a>
        <button class="sidebar-collapse-toggle" id="sidebar-collapse-toggle" type="button"
                aria-label="Recolher menu lateral" aria-expanded="true" aria-controls="sidebar"
                title="Recolher menu lateral">
            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
        </button>
    </div>

    <div class="nav-label">MENU PRINCIPAL</div>
    <nav class="side-nav" aria-label="Navegação principal">
        <a class="side-link <?= $current === '' ? 'active' : '' ?>" href="<?= e(url()) ?>" aria-label="Dashboard" title="Dashboard">
            <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span class="side-link-label">Dashboard</span>
        </a>
        <?php foreach ($navigation as $index => $group):
            $isOpen = $expandedGroup === $index;
            $groupId = 'sidebar-group-' . $index;
        ?>
            <section class="nav-group <?= $isOpen ? 'is-open' : '' ?>">
                <button class="side-link nav-group-toggle <?= $isOpen ? 'active' : '' ?>"
                        type="button" aria-label="<?= e($group['label']) ?>" title="<?= e($group['label']) ?>"
                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                        aria-controls="<?= e($groupId) ?>">
                    <span class="nav-icon"><i class="fa-solid <?= e($group['icon']) ?>" aria-hidden="true"></i></span>
                    <span class="side-link-label"><?= e($group['label']) ?></span>
                    <span class="nav-chevron" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
                </button>
                <div class="nav-submenu" id="<?= e($groupId) ?>" <?= $isOpen ? '' : 'hidden' ?>>
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="nav-sub-link <?= $current === $item['path'] ? 'active' : '' ?>"
                           href="<?= e(url($item['path'])) ?>">
                            <span class="nav-sub-branch" aria-hidden="true"></span>
                            <span><?= e($item['label']) ?></span>
                            <?php if ($current === $item['path']): ?><span class="nav-sub-indicator"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-spacer"></div>
    <div class="side-user">
        <?php if ($sideAvatar !== ''): ?>
            <img class="avatar avatar-photo" src="<?= e($sideAvatar) ?>" alt="" loading="lazy">
        <?php else: ?>
            <div class="avatar"><?= e($initials) ?></div>
        <?php endif; ?>
        <div class="side-user-info"><b><?= e($name) ?></b><small><?= e($u['role'] ?? '') ?></small></div>
    </div>
</aside>
