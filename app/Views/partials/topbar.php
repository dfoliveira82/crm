<?php
$u = $user ?? user_data();
$userName = trim((string)($u['name'] ?? $u['full_name'] ?? $u['nome'] ?? $u['username'] ?? 'Usuário'));
$userRole = trim((string)($u['role'] ?? $u['perfil'] ?? $u['cargo'] ?? $u['funcao'] ?? $u['position'] ?? '')) ?: 'Usuário';
$parts = preg_split('/\s+/u', $userName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = '';
if ($parts !== []) {
    $first = $parts[0];
    $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
    $initials = (function_exists('mb_substr') ? mb_substr($first, 0, 1, 'UTF-8') : substr($first, 0, 1));
    if ($last !== '') $initials .= function_exists('mb_substr') ? mb_substr($last, 0, 1, 'UTF-8') : substr($last, 0, 1);
}
$initials = function_exists('mb_strtoupper') ? mb_strtoupper($initials ?: 'U', 'UTF-8') : strtoupper($initials ?: 'U');
$avatar = trim((string)($u['avatar'] ?? $u['avatar_url'] ?? $u['photo_url'] ?? $u['photo'] ?? $u['foto_url'] ?? $u['foto'] ?? ''));
$avatarDataUri = trim((string)($u['avatar_data_uri'] ?? ''));
$activeContext = App\Core\Session::get('active_context', []);
$activeContext = is_array($activeContext) ? $activeContext : [];
$topbarPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  $topbarLabels = [
      'calendar' => 'Calendário',
      'agenda' => 'Calendário',
      'ava/gestao-de-cursos' => 'Gestão de Cursos',
      'educacional/gestao-de-cursos' => 'Gestão de Cursos',
      'ava/gestao-de-turmas' => 'Gestão de Turmas',
  ];
$topbarPageLabel = $topbarLabels[$topbarPath] ?? (ucfirst($topbarPath ?: 'Dashboard'));
$avatarUrl = '';
if ($avatarDataUri !== '' && preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$#', $avatarDataUri)) $avatarUrl = $avatarDataUri;
if ($avatar !== '') {
    if (preg_match('#^https?://#i', $avatar) && filter_var($avatar, FILTER_VALIDATE_URL)) $avatarUrl = $avatar;
    elseif (!str_contains($avatar, '..')) $avatarUrl = asset(ltrim($avatar, '/'));
}
?>
<header class="topbar">
    <button class="icon-button mobile-menu" id="sidebar-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    <div class="breadcrumbs">
        <span>FADIPA</span><b aria-hidden="true">›</b>
        <strong><?= e($topbarPageLabel) ?></strong>
    </div>
    <div class="top-search" aria-hidden="true"></div>
    <div class="top-actions">
        <div class="user-menu" id="user-menu">
            <button class="user-menu__trigger" id="user-menu-trigger" type="button"
                    aria-haspopup="menu" aria-expanded="false" aria-controls="user-menu-dropdown">
                <?php if ($avatarUrl !== ''): ?>
                    <img class="user-menu__avatar" src="<?= e($avatarUrl) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="user-menu__avatar user-menu__avatar--initials" aria-hidden="true"><?= e($initials) ?></span>
                <?php endif; ?>
                <span class="user-menu__identity">
                    <strong><?= e($userName) ?></strong>
                    <small><?= e($userRole) ?></small>
                </span>
                <i class="fa-solid fa-chevron-down user-menu__chevron" aria-hidden="true"></i>
            </button>
            <div class="user-menu__dropdown" id="user-menu-dropdown" role="menu" aria-labelledby="user-menu-trigger" hidden>
                <a class="user-menu__item" href="<?= e(url('usuario/perfil')) ?>" role="menuitem" tabindex="-1">
                    <i class="fa-regular fa-user" aria-hidden="true"></i><span>Meu perfil</span>
                </a>
                <?php $availableUserContexts = App\Core\Session::get('user_contexts', []); $hasContextOptions = is_array($availableUserContexts) && (count($availableUserContexts) > 1 || !$activeContext); ?>
                <?php if ($hasContextOptions): ?>
                    <button class="user-menu__item" type="button" data-context-open role="menuitem" tabindex="-1">
                        <i class="fa-solid fa-building-columns" aria-hidden="true"></i><span>Alterar contexto</span><i class="fa-solid fa-chevron-right user-menu__item-arrow" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>
                <div class="user-menu__divider" role="separator"></div>
                <button class="user-menu__item user-menu__theme" type="button" data-theme-choice="light" role="menuitem" tabindex="-1">
                    <i class="fa-regular fa-sun" aria-hidden="true"></i><span>Light</span><i class="fa-solid fa-check user-menu__check" aria-hidden="true"></i>
                </button>
                <button class="user-menu__item user-menu__theme" type="button" data-theme-choice="dark" role="menuitem" tabindex="-1">
                    <i class="fa-regular fa-moon" aria-hidden="true"></i><span>Dark</span><i class="fa-solid fa-check user-menu__check" aria-hidden="true"></i>
                </button>
                <div class="user-menu__divider" role="separator"></div>
                <form method="post" action="<?= e(url('logout')) ?>" class="user-menu__logout-form">
                    <?= csrf_field() ?>
                    <button class="user-menu__item user-menu__logout" type="submit" role="menuitem" tabindex="-1">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
