<?php
declare(strict_types=1);

$context = is_array($context ?? null) ? $context : [];
$groups = is_array($groups ?? null) ? $groups : [];
$periods = is_array($periods ?? null) ? $periods : [];
$courses = is_array($courses ?? null) ? $courses : [];
$periodId = (int) ($periodId ?? 0);
$courseId = (string) ($courseId ?? '');
$search = (string) ($search ?? '');
$loadError = (string) ($loadError ?? '');
$periodCount = count(array_unique(array_map(static fn(array $item): string => (string) $item['period_idnumber'], $groups)));
$disciplineCount = array_sum(array_map(static fn(array $item): int => (int) ($item['discipline_count'] ?? 0), $groups));
$contextLabel = trim((string) ($context['NOMEFACULDADE'] ?? $context['NOMEFILIAL'] ?? $context['FILIAL'] ?? $context['NOME'] ?? ''));
$contextLabel = $contextLabel !== '' ? $contextLabel : 'Contexto acadêmico ativo';
?>

<link rel="stylesheet" href="<?= e(asset('css/academic-courses.css')) ?>">

<section class="academic-page" data-academic-courses data-preview-endpoint="<?= e(url('api/educacional/moodle/preview')) ?>" data-integration-endpoint="<?= e(url('api/educacional/moodle/integrate')) ?>">
    <header class="academic-page-header">
        <div>
            <span class="academic-kicker"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> AVA · RM EDUCACIONAL</span>
            <h1>Gestão de Cursos</h1>
            <p>Selecione as turmas do RM que serão preparadas para integração com o Moodle.</p>
        </div>
        <div class="academic-context">
            <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
            <div><small>Contexto ativo</small><strong><?= e($contextLabel) ?></strong></div>
        </div>
    </header>

    <div class="academic-stats" aria-label="Resumo das turmas">
        <article><span class="academic-stat-icon blue"><i class="fa-solid fa-users-rectangle"></i></span><div><small>Turmas encontradas</small><strong><?= e((string) count($groups)) ?></strong></div></article>
        <article><span class="academic-stat-icon green"><i class="fa-solid fa-book-open"></i></span><div><small>Disciplinas</small><strong><?= e((string) $disciplineCount) ?></strong></div></article>
        <article><span class="academic-stat-icon amber"><i class="fa-regular fa-calendar"></i></span><div><small>Períodos letivos</small><strong><?= e((string) $periodCount) ?></strong></div></article>
    </div>

    <?php if ($loadError !== ''): ?>
        <div class="academic-alert is-error"><i class="fa-solid fa-circle-exclamation"></i><?= e($loadError) ?></div>
    <?php endif; ?>

    <section class="academic-card">
        <div class="academic-toolbar">
            <div>
                <h2>Turmas do RM</h2>
                <p>As disciplinas aparecem dentro da turma e serão criadas como cursos nativos no Moodle.</p>
            </div>
            <form class="academic-filters" method="get" action="<?= e(url('ava/gestao-de-cursos')) ?>">
                <label class="academic-period-filter"><span>Período letivo</span><select name="period" onchange="this.form.submit()"><option value="0">Todos os períodos</option><?php foreach ($periods as $period): ?><option value="<?= e((string) $period['IDPERLET']) ?>" <?= $periodId === (int) $period['IDPERLET'] ? 'selected' : '' ?>><?= e((string) ($period['CODPERLET'] ?: $period['DESCRICAO'])) ?><?= !empty($period['DESCRICAO']) && $period['DESCRICAO'] !== $period['CODPERLET'] ? ' · ' . e((string) $period['DESCRICAO']) : '' ?></option><?php endforeach; ?></select></label>
                <label class="academic-period-filter"><span>Curso</span><select name="course" onchange="this.form.submit()"><option value="">Todos os cursos</option><?php foreach ($courses as $course): ?><option value="<?= e((string) $course['CODCURSO']) ?>" <?= $courseId === (string) $course['CODCURSO'] ? 'selected' : '' ?>><?= e((string) $course['CODCURSO']) ?> · <?= e((string) $course['CURSO_NOME']) ?></option><?php endforeach; ?></select></label>
                <label class="academic-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar turma, curso ou disciplina" aria-label="Buscar"></label>
                <button class="academic-filter-button" type="submit">Filtrar</button>
                <?php if ($search !== '' || $periodId > 0 || $courseId !== ''): ?><a class="academic-clear-filters" href="<?= e(url('ava/gestao-de-cursos')) ?>">Limpar</a><?php endif; ?>
            </form>
        </div>

        <div class="academic-selection-bar">
            <label class="academic-check-label"><input type="checkbox" data-select-all> <span>Selecionar todas</span></label>
            <span class="academic-selected-count" data-selected-count>0 selecionadas</span>
            <button class="academic-primary-button" type="button" data-preview disabled><i class="fa-solid fa-cloud-arrow-up"></i> Integrar com Moodle</button>
        </div>

        <?php if ($groups === [] && $loadError === ''): ?>
            <div class="academic-empty"><i class="fa-regular fa-folder-open"></i><strong>Nenhuma turma encontrada</strong><span>Ajuste a busca ou confira o contexto acadêmico selecionado.</span></div>
        <?php else: ?>
            <div class="academic-table-wrap">
                <table class="academic-table">
                    <thead><tr><th class="check-cell"></th><th>Turma / Curso</th><th>Período letivo</th><th>Disciplinas</th><th>Status</th><th class="id-cell">Identificador RM</th><th></th></tr></thead>
                    <tbody>
                    <?php $lastSection = null; foreach ($groups as $index => $group): ?>
                        <?php $section = $group['turno_group'] ?? 'outros'; ?>
                        <?php if ($lastSection !== $section): $lastSection = $section; ?>
                            <tr class="academic-section-row"><td colspan="7"><i class="fa-solid fa-clock" aria-hidden="true"></i><?= e((string) ($group['turno_label'] ?? 'Outros turnos')) ?></td></tr>
                        <?php endif; ?>
                        <tr class="academic-class-row" data-class-row data-class-id="<?= e($group['idnumber']) ?>">
                            <td class="check-cell"><input type="checkbox" data-class-check value="<?= e($group['idnumber']) ?>" aria-label="Selecionar <?= e($group['turma']) ?>"></td>
                            <td><div class="class-title"><button type="button" class="academic-expand" data-expand="<?= e((string) $index) ?>" aria-expanded="false" aria-label="Exibir disciplinas"><i class="fa-solid fa-chevron-right"></i></button><div><strong><?= e($group['turma']) ?></strong><small><?= e($group['curso']) ?> · código <?= e($group['cod_curso']) ?></small></div></div></td>
                            <td><strong><?= e($group['periodo']) ?></strong><small><?= e($group['periodo_inicio']) ?><?= $group['periodo_fim'] !== '' ? ' a ' . e($group['periodo_fim']) : '' ?></small></td>
                            <td><span class="academic-count"><?= e((string) $group['discipline_count']) ?></span></td>
                            <td><span class="academic-status status-<?= e(strtolower(str_replace(' ', '-', $group['status']))) ?>" data-class-status><?= e($group['status']) ?></span></td>
                            <td class="id-cell"><code><?= e($group['idnumber']) ?></code></td>
                            <td><button type="button" class="academic-expand-text" data-expand="<?= e((string) $index) ?>">Ver disciplinas</button></td>
                        </tr>
                        <tr class="academic-detail-row" data-detail-row="<?= e((string) $index) ?>" hidden><td colspan="7"><div class="academic-detail"><div class="academic-detail-heading"><span>Disciplinas desta turma</span><small>Serão cursos Moodle</small></div><?php if ($group['disciplines'] === []): ?><p class="academic-muted">Nenhuma disciplina vinculada.</p><?php else: ?><div class="academic-disciplines"><?php foreach ($group['disciplines'] as $discipline): ?><div class="academic-discipline"><i class="fa-solid fa-book"></i><div><strong><?= e($discipline['name']) ?></strong><small><?= e($discipline['cod_disc']) ?></small></div><span class="academic-discipline-status <?= ($discipline['sync_status'] ?? '') === 'INTEGRADA' ? 'is-integrated' : '' ?>" data-discipline-status="<?= e($discipline['idnumber']) ?>"><?= ($discipline['sync_status'] ?? '') === 'INTEGRADA' ? 'Integrada' : 'Pendente' ?></span><code><?= e($discipline['idnumber']) ?></code></div><?php endforeach; ?></div><?php endif; ?></div></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="academic-preview" data-preview-panel hidden>
        <div><span class="academic-kicker">PRÓXIMA ETAPA</span><h2>Estrutura pronta para o Moodle</h2><p data-preview-message></p></div>
        <div class="academic-hierarchy" data-hierarchy></div>
    </section>
</section>

<script src="<?= e(asset('js/academic-courses.js')) ?>?v=<?= e((string) filemtime(dirname(__DIR__, 3) . '/public/js/academic-courses.js')) ?>" defer></script>
