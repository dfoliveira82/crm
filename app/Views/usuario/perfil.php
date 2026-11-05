<?php
$profile = is_array($profile ?? null) ? $profile : [];
$contexts = is_array($contexts ?? null) ? $contexts : [];
$activeContext = is_array($activeContext ?? null) ? $activeContext : [];
$name = trim((string)($profile['name'] ?? 'Usuário')) ?: 'Usuário';
$email = trim((string)($profile['email'] ?? ''));
$code = trim((string)($profile['code'] ?? ''));
$role = trim((string)($role ?? '')) ?: 'Não informado';
$avatar = trim((string)($profile['avatar_data_uri'] ?? ''));
$parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = '';
if ($parts) {
    $initials = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1, 'UTF-8') : substr($parts[0], 0, 1);
    if (count($parts) > 1) $initials .= function_exists('mb_substr') ? mb_substr($parts[count($parts)-1], 0, 1, 'UTF-8') : substr($parts[count($parts)-1], 0, 1);
}
$initials = function_exists('mb_strtoupper') ? mb_strtoupper($initials ?: 'U', 'UTF-8') : strtoupper($initials ?: 'U');
?>
<section class="welcome-row user-profile-title-row">
    <div><p class="eyebrow">CONTA DO USUÁRIO</p><h1>Meu perfil</h1><p class="muted">Consulte seus dados e o contexto de acesso ao sistema.</p></div>
</section>

<?php if (($loadError ?? '') !== ''): ?>
    <div class="user-profile-alert user-profile-alert--warning" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($loadError) ?></span></div>
<?php endif; ?>
<?php if (($successMessage ?? '') !== ''): ?>
    <div class="user-profile-alert user-profile-alert--success" role="status"><i class="fa-solid fa-circle-check"></i><span><?= e($successMessage) ?></span></div>
<?php endif; ?>
<?php if (($errorMessage ?? '') !== ''): ?>
    <div class="user-profile-alert user-profile-alert--warning" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
<?php endif; ?>

<section class="card user-profile-card">
    <div class="user-profile-heading">
        <?php if ($avatar !== ''): ?>
            <img class="user-profile-avatar user-profile-avatar--photo" src="<?= e($avatar) ?>" alt="Foto de <?= e($name) ?>">
        <?php else: ?>
            <span class="user-profile-avatar" aria-hidden="true"><?= e($initials) ?></span>
        <?php endif; ?>
        <div class="user-profile-identity"><h2><?= e($name) ?></h2><p class="muted"><?= e($role) ?></p><span class="user-profile-source"><i class="fa-solid fa-shield-halved"></i> Dados sincronizados do TOTVS RM</span></div>
    </div>

    <div class="user-profile-section-heading"><i class="fa-regular fa-address-card"></i><h3>Dados pessoais e profissionais</h3></div>
    <div class="user-profile-fields">
        <div><small>Nome completo</small><strong><?= e($name) ?></strong></div>
        <div><small>E-mail</small><strong><?= e($email !== '' ? $email : 'Não informado no RM') ?></strong></div>
        <div><small>Cargo / perfil de acesso</small><strong><?= e($role) ?></strong></div>
        <div><small>Usuário / código RM</small><strong><?= e($code !== '' ? $code : 'Não informado') ?></strong></div>
    </div>

    <div class="user-profile-section-heading user-profile-context-heading"><i class="fa-solid fa-building-columns"></i><h3>Contexto atual</h3></div>
    <?php if ($activeContext): ?>
        <div class="user-profile-current-context">
            <div><small>Filial</small><strong><?= e($activeContext['FILIAL'] ?? '—') ?></strong></div>
            <div><small>Tipo de curso</small><strong><?= e($activeContext['TIPOCURSO'] ?? '—') ?></strong></div>
            <div><small>Instituição</small><strong><?= e($activeContext['NOMEFACULDADE'] ?? '—') ?></strong></div>
        </div>
    <?php else: ?>
        <p class="muted user-profile-empty">Nenhum contexto foi retornado para este usuário.</p>
    <?php endif; ?>
    <p class="user-profile-note">As informações são exibidas para consulta. Nome, e-mail, foto, perfis e contextos têm como fonte o cadastro e as permissões do TOTVS RM.</p>
</section>

