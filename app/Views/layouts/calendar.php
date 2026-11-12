<?php
use App\Core\{Csrf, Session};
$user = $user ?? user_data();
$contextNoticeSuccess = Session::pullFlash('context_success', '');
$contextNoticeError = Session::pullFlash('context_error', '');
?><!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title>Selvar · Agenda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/user-menu.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/calendar.css')) ?>">
</head>
<body class="calendar-page">
    <div class="app-shell">
        <?php require dirname(__DIR__) . '/partials/sidebar.php'; ?>
        <button class="sidebar-backdrop" id="sidebar-backdrop" type="button" aria-label="Fechar menu lateral" tabindex="-1" hidden></button>
        <main class="main-area">
            <?php require dirname(__DIR__) . '/partials/topbar.php'; ?>
            <?php if ($contextNoticeSuccess !== '' || $contextNoticeError !== ''): ?><div class="context-global-notice <?= $contextNoticeError !== '' ? 'is-error' : 'is-success' ?>" role="status"><i class="fa-solid <?= $contextNoticeError !== '' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i><?= e($contextNoticeError !== '' ? $contextNoticeError : $contextNoticeSuccess) ?></div><?php endif; ?>
            <div class="calendar-page-content"><?= $content ?></div>
        </main>
    </div>
    <?php require dirname(__DIR__) . '/partials/user-context-modal.php'; ?>
    <div id="toast-region" aria-live="polite"></div>
    <script>
        (() => {
            const bindCreateEvent = () => {
                const root = document.querySelector('[data-calendar-root]');
                const button = root?.querySelector('[data-calendar-open-event]');
                const modal = root?.querySelector('[data-calendar-modal]');
                if (!button || !modal || button.dataset.calendarModalBound) return;
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    modal.removeAttribute('hidden');
                    root.querySelector('[name="title"]')?.focus();
                });
                root.querySelectorAll('[data-calendar-modal-close]').forEach((closeButton) => {
                    closeButton.addEventListener('click', () => modal.setAttribute('hidden', ''));
                });
                button.dataset.calendarModalBound = 'true';
            };
            bindCreateEvent();
        })();
    </script>
    <script type="module" src="<?= e(asset('js/main.js')) ?>"></script>
    <script type="module" src="<?= e(asset('js/calendar.js')) ?>?v=<?= e((string) filemtime(dirname(__DIR__, 3) . '/public/js/calendar.js')) ?>"></script>
</body>
</html>
