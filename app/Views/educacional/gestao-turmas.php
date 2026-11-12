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
$disciplineCount = array_sum(array_map(static fn(array $group): int => count($group['disciplines'] ?? []), $groups));
?>
<link rel="stylesheet" href="<?= e(asset('css/academic-courses.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/academic-participants.css')) ?>">

<section class="academic-page participant-page" data-participant-page
    data-participants-endpoint="<?= e(url('api/educacional/moodle/participants')) ?>"
    data-status-endpoint="<?= e(url('api/educacional/moodle/participants/status')) ?>"
    data-integration-endpoint="<?= e(url('api/educacional/moodle/participants/integrate')) ?>">
    <header class="academic-page-header">
        <div>
            <span class="academic-kicker"><i class="fa-solid fa-users-gear"></i> AVA · RM EDUCACIONAL</span>
            <h1>Gestão de Turmas</h1>
            <p>Integre alunos e professores às disciplinas já criadas no Moodle.</p>
        </div>
        <div class="academic-context"><i class="fa-solid fa-people-group"></i>
            <div><small>Contexto ativo</small><strong><?= e((string) ($context['NOMEFACULDADE'] ?? 'Contexto acadêmico')) ?></strong></div>
        </div>
    </header>

    <div class="academic-stats">
        <article><span class="academic-stat-icon blue"><i class="fa-solid fa-layer-group"></i></span>
            <div><small>Turmas</small><strong><?= e((string) count($groups)) ?></strong></div>
        </article>
        <article><span class="academic-stat-icon green"><i class="fa-solid fa-book"></i></span>
            <div><small>Disciplinas</small><strong><?= e((string) $disciplineCount) ?></strong></div>
        </article>
        <article><span class="academic-stat-icon amber"><i class="fa-solid fa-user-check"></i></span>
            <div><small>Disciplinas a Integrar</small><strong data-participant-selected>0</strong></div>
        </article>
    </div>

    <?php if ($loadError !== ''): ?><div class="academic-alert is-error"><i class="fa-solid fa-circle-exclamation"></i><?= e($loadError) ?></div><?php endif; ?>

    <section class="academic-card">
        <div class="academic-toolbar">
            <div>
                <h2>Alunos e professores por disciplina</h2>
                <p>Selecione as disciplinas para criar/atualizar usuários e matriculá-los no Moodle.</p>
            </div>
            <form class="academic-filters" method="get" action="<?= e(url('ava/gestao-de-turmas')) ?>">
                <label class="academic-period-filter"><span>Período letivo</span><select name="period" onchange="this.form.submit()">
                        <option value="0">Todos os períodos</option><?php foreach ($periods as $period): ?><option value="<?= e((string) $period['IDPERLET']) ?>" <?= $periodId === (int) $period['IDPERLET'] ? 'selected' : '' ?>><?= e((string) ($period['CODPERLET'] ?: $period['DESCRICAO'])) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="academic-period-filter"><span>Curso</span><select name="course" onchange="this.form.submit()">
                        <option value="">Todos os cursos</option><?php foreach ($courses as $course): ?><option value="<?= e((string) $course['CODCURSO']) ?>" <?= $courseId === (string) $course['CODCURSO'] ? 'selected' : '' ?>><?= e((string) $course['CODCURSO']) ?> · <?= e((string) $course['CURSO_NOME']) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="academic-search"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= e($search) ?>" placeholder="Buscar turma ou disciplina"></label>
                <button class="academic-filter-button" type="submit">Filtrar</button>
            </form>
        </div>
        <div class="academic-selection-bar">
            <label class="academic-check-label"><input type="checkbox" data-select-all-participants> <span>Selecionar todas</span></label>
            <span class="academic-selected-count" data-participant-count>0 selecionadas</span>
            <button class="academic-primary-button" type="button" data-integrate-participants disabled><i class="fa-solid fa-user-plus"></i> Integrar participantes</button>
        </div>

        <?php if ($groups === [] && $loadError === ''): ?>
            <div class="academic-empty"><i class="fa-regular fa-folder-open"></i><strong>Nenhuma turma encontrada</strong><span>Selecione um período ou ajuste a busca.</span></div>
        <?php else: ?>
            <div class="academic-table-wrap">
                <table class="academic-table participant-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Turma / Curso</th>
                            <th>Período</th>
                            <th>Turno</th>
                            <th>Disciplinas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $lastTurnoGroup = null; foreach ($groups as $index => $group): ?>
                            <?php if ($lastTurnoGroup !== ($group['turno_group'] ?? 'outros')): $lastTurnoGroup = $group['turno_group'] ?? 'outros'; ?>
                                <tr class="participant-section-row"><td colspan="5"><i class="fa-solid fa-clock"></i> <?= e((string) ($group['turno_label'] ?? 'Outros turnos')) ?></td></tr>
                            <?php endif; ?>
                            <tr class="academic-class-row">
                                <td></td>
                                <td>
                                    <div class="class-title"><button type="button" class="academic-expand" data-participant-expand="<?= e((string) $index) ?>" aria-expanded="false"><i class="fa-solid fa-chevron-right"></i></button>
                                        <div><strong><?= e($group['turma']) ?></strong><small><?= e($group['curso']) ?></small></div>
                                    </div>
                                </td>
                                <td><?= e($group['periodo']) ?></td>
                                <td><span class="participant-turno-badge participant-turno-<?= e((string) ($group['turno_group'] ?? 'outros')) ?>"><?= e((string) ($group['turno_label'] ?? 'Outros turnos')) ?></span></td>
                                <td><span class="academic-count"><?= e((string) count($group['disciplines'])) ?></span></td>
                            </tr>
                            <tr class="academic-detail-row" data-participant-detail="<?= e((string) $index) ?>" hidden>
                                <td colspan="5">
                                    <div class="academic-detail">
                                        <div class="participant-discipline-list"><?php foreach ($group['disciplines'] as $discipline): ?><?php $integrationUrl = url('ava/gestao-de-cursos') . '?period=' . rawurlencode((string) $group['period_id']) . '&course=' . rawurlencode((string) $group['course_id']); ?><?php $isIntegrated = !empty($discipline['integrated_rm']); ?><?php $studentCount = (int) ($discipline['student_count'] ?? 0); ?><label class="participant-discipline-row <?= $isIntegrated ? 'is-integrated' : 'is-pending' ?>" data-discipline-id="<?= e((string) $discipline['id_turma_disc']) ?>" data-discipline-integrated="<?= $isIntegrated ? '1' : '0' ?>"><?php if ($isIntegrated): ?><span class="participant-integration-badge is-integrated"><i class="fa-solid fa-circle-check"></i> INTEGRADO</span><input type="checkbox" data-discipline-check value="<?= e((string) $discipline['id_turma_disc']) ?>"><?php else: ?><a class="participant-integration-badge is-pending" href="<?= e($integrationUrl) ?>"><i class="fa-solid fa-triangle-exclamation"></i> REALIZAR INTEGRAÇÃO</a><input type="checkbox" data-discipline-check value="<?= e((string) $discipline['id_turma_disc']) ?>" disabled title="Integre a disciplina no Moodle antes de selecionar"><?php endif; ?><span class="participant-discipline-name"><strong><?= e($discipline['name']) ?></strong><small><?= e($discipline['idnumber']) ?></small></span><span class="participant-count"><i class="fa-solid fa-user-graduate"></i> <?= e((string) $studentCount) ?><i class="fa-solid fa-chalkboard-user"></i> <?= e((string) ($discipline['teacher_count'] ?? 0)) ?></span><span class="participant-enrolment-status" data-discipline-status="<?= e((string) $discipline['id_turma_disc']) ?>" data-total-rm="<?= e((string) $studentCount) ?>"><?php if ($isIntegrated): ?>Abrir para consultar<?php else: ?>0 adicionados · <?= e((string) $studentCount) ?> pendentes<?php endif; ?></span></label><?php endforeach; ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <div class="academic-alert is-success participant-result" data-participant-result hidden></div>
</section>
<script src="<?= e(asset('js/academic-participants.js')) ?>?v=<?= e((string) filemtime(dirname(__DIR__, 3) . '/public/js/academic-participants.js')) ?>" defer></script>
