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
$adEnvironment = 'producao';
$adTargetOu = 'OU=FADIPA,OU=ALUNOS,OU=ACADEMICO,OU=FADIPA,DC=fadipa,DC=local';
$adServiceAccount = trim((string) (\App\Core\Env::get('AD_SERVICE_ACCOUNT', \App\Core\Env::get('AD_BIND_DN', 'Conta configurada no servidor'))));
$studentTotal = 0;
$studentIntegrated = 0;
foreach ($groups as $group) {
    $studentTotal += (int) ($group['students_total'] ?? 0);
    $studentIntegrated += (int) ($group['students_integrated'] ?? 0);
}
?>
<link rel="stylesheet" href="<?= e(asset('css/academic-courses.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/academic-participants.css')) ?>">

<section class="academic-page participant-page" data-ad-page data-validate-endpoint="<?= e(url('api/educacional/ad/validate')) ?>" data-confirm-endpoint="<?= e(url('api/educacional/ad/confirm')) ?>">
    <header class="academic-page-header">
        <div>
            <span class="academic-kicker"><i class="fa-solid fa-user-shield"></i> AVA · ACTIVE DIRECTORY</span>
            <h1>Integração AD</h1>
            <p>Consulta primeiro a turma e, em seguida, os alunos vinculados ao Active Directory.</p>
        </div>
        <div class="academic-context"><i class="fa-solid fa-people-group"></i>
            <div><small>Contexto ativo</small><strong><?= e((string) ($context['NOMEFACULDADE'] ?? 'Contexto acadêmico')) ?></strong></div>
        </div>
    </header>

    <div class="academic-stats">
        <article><span class="academic-stat-icon blue"><i class="fa-solid fa-layer-group"></i></span>
            <div><small>Turmas</small><strong><?= e((string) count($groups)) ?></strong></div>
        </article>
        <article><span class="academic-stat-icon green"><i class="fa-solid fa-user-graduate"></i></span>
            <div><small>Alunos</small><strong><?= e((string) $studentTotal) ?></strong></div>
        </article>
        <article><span class="academic-stat-icon amber"><i class="fa-solid fa-user-check"></i></span>
            <div><small>Integrados</small><strong><?= e((string) $studentIntegrated) ?></strong></div>
        </article>
    </div>

    <?php if ($loadError !== ''): ?>
        <div class="academic-alert is-error"><i class="fa-solid fa-circle-exclamation"></i><?= e($loadError) ?></div>
    <?php endif; ?>

    <section class="academic-card">
        <div class="academic-confirmation-panel">
            <div class="academic-toolbar">
                <div>
                    <h3>Confirmação do ambiente e destino AD</h3>
                    <p>A identidade LDAP e a OU são definidas no servidor. Nesta etapa, qualquer objeto criado permanecerá desabilitado e sem senha.</p>
                </div>
            </div>
            <div class="academic-confirmation-grid">
                <label class="academic-form-field">
                    <span>Ambiente</span>
                    <select data-ad-env disabled>
                        <option value="producao" selected>Produção</option>
                    </select>
                </label>
                <label class="academic-form-field">
                    <span>OU de destino</span>
                    <input type="text" data-ad-ou value="<?= e($adTargetOu) ?>" readonly>
                </label>
                <label class="academic-form-field">
                    <span>Conta de serviço</span>
                    <input type="text" data-ad-service value="<?= e($adServiceAccount) ?>" readonly>
                </label>
            </div>
            <div class="academic-confirmation-actions">
                <button class="academic-primary-button" type="button" data-ad-confirm><i class="fa-solid fa-shield-halved"></i> Confirmar configuração</button>
                <button class="academic-filter-button" type="button" data-ad-test><i class="fa-solid fa-plug"></i> Testar conexão LDAP</button>
                <span class="academic-note">A autenticação utiliza AD_BIND_DN e AD_BIND_PASSWORD configurados exclusivamente no servidor.</span>
            </div>
            <div class="academic-alert is-success ad-confirmation-result" data-ad-confirm-result hidden></div>
            <div class="academic-alert is-success" data-ad-test-result hidden></div>
        </div>
        <div class="academic-selection-bar">
            <label class="academic-check-label"><input type="checkbox" data-ad-select-all> <span>Selecionar pendentes</span></label>
            <span class="academic-selected-count" data-ad-count>0 selecionados</span>
            <button class="academic-primary-button" type="button" data-ad-validate disabled><i class="fa-solid fa-check-double"></i> Pré-validar AD</button>
            <button class="academic-primary-button" type="button" data-ad-create disabled><i class="fa-solid fa-user-plus"></i> Criar objeto desabilitado</button>
        </div>
        <div class="academic-alert is-success ad-validation-result" data-ad-result hidden></div>
        <div class="academic-alert is-success ad-create-result" data-ad-create-result hidden></div>
        <div class="academic-toolbar">
            <div>
                <h2>Turmas e alunos</h2>
                <p>Primeiro a turma; depois a lista de alunos com status do AD conforme <strong>SALUNOCOMPL.EMAILAD</strong>.</p>
            </div>
            <form class="academic-filters" method="get" action="<?= e(url('ava/integracao-ad')) ?>">
                <label class="academic-period-filter"><span>Período letivo</span>
                    <select name="period" onchange="this.form.submit()">
                        <option value="0">Todos os períodos</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= e((string) $period['IDPERLET']) ?>" <?= $periodId === (int) $period['IDPERLET'] ? 'selected' : '' ?>><?= e((string) ($period['CODPERLET'] ?: $period['DESCRICAO'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="academic-period-filter"><span>Curso</span>
                    <select name="course" onchange="this.form.submit()">
                        <option value="">Todos os cursos</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= e((string) $course['CODCURSO']) ?>" <?= $courseId === (string) $course['CODCURSO'] ? 'selected' : '' ?>><?= e((string) $course['CODCURSO']) ?> · <?= e((string) $course['CURSO_NOME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="academic-search"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= e($search) ?>" placeholder="Buscar turma"></label>
                <button class="academic-filter-button" type="submit">Filtrar</button>
            </form>
        </div>

        <?php if ($groups === [] && $loadError === ''): ?>
            <div class="academic-empty"><i class="fa-regular fa-folder-open"></i><strong>Nenhuma turma encontrada</strong><span>Selecione um período ou ajuste a busca.</span></div>
        <?php else: ?>
            <div class="academic-table-wrap">
                <table class="academic-table participant-table">
                    <thead>
                        <tr>
                            <th>Turma / Curso</th>
                            <th>Período</th>
                            <th>Alunos</th>
                            <th>Status AD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr class="academic-class-row">
                                <td>
                                    <div class="class-title">
                                        <div>
                                            <strong><?= e((string) ($group['turma'] ?? 'Turma')) ?></strong>
                                            <small><?= e((string) ($group['curso'] ?? 'Curso não informado')) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e((string) ($group['periodo'] ?? '')) ?></td>
                                <td><span class="academic-count"><?= e((string) ($group['students_total'] ?? 0)) ?></span></td>
                                <td>
                                    <?php $groupStatus = strtoupper((string) ($group['ad_status'] ?? 'PENDENTE')); ?>
                                    <span class="academic-status status-<?= strtolower(str_replace(' ', '-', $groupStatus)) ?>"><?= e($groupStatus) ?></span>
                                </td>
                            </tr>
                            <tr class="academic-detail-row">
                                <td colspan="4">
                                    <div class="academic-detail">
                                        <div class="participant-discipline-list">
                                            <?php if (empty($group['students'])): ?>
                                                <div class="participant-discipline-row is-pending">
                                                    <span class="participant-integration-badge is-pending"><i class="fa-solid fa-triangle-exclamation"></i> SEM ALUNOS</span>
                                                    <span class="participant-discipline-name"><strong>Turma sem alunos matriculados</strong></span>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($group['students'] as $student): ?>
                                                    <?php $studentStatus = strtoupper((string) ($student['AD_STATUS'] ?? 'PENDENTE')); ?>
                                                    <?php $isPending = $studentStatus !== 'INTEGRADO'; ?>
                                                    <div class="participant-discipline-row <?= $studentStatus === 'INTEGRADO' ? 'is-integrated' : 'is-pending' ?>">
                                                        <label class="academic-check-label" style="margin-right:12px;">
                                                            <input type="checkbox" data-student-check value="<?= e((string) ($student['RA'] ?? '')) ?>" data-turma="<?= e((string) ($group['cod_turma'] ?? '')) ?>" data-idperlet="<?= e((string) ($group['period_id'] ?? '')) ?>" data-name="<?= e((string) ($student['NOME'] ?? '')) ?>" data-emailad="<?= e((string) ($student['EMAILAD'] ?? '')) ?>" <?= $isPending ? '' : 'disabled' ?> >
                                                        </label>
                                                        <span class="participant-integration-badge <?= $studentStatus === 'INTEGRADO' ? 'is-integrated' : 'is-pending' ?>">
                                                            <i class="fa-solid <?= $studentStatus === 'INTEGRADO' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                                                            <?= e($studentStatus) ?>
                                                        </span>
                                                        <span class="participant-discipline-name">
                                                            <strong><?= e((string) ($student['NOME'] ?? 'Aluno')) ?></strong>
                                                            <small>RA: <?= e((string) ($student['RA'] ?? '')) ?></small>
                                                        </span>
                                                        <span class="participant-count">
                                                            <?= e((string) ($student['EMAIL'] ?? 'Sem e-mail')) ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<script>
(() => {
    const root = document.querySelector('[data-ad-page]');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const validateEndpoint = root.dataset.validateEndpoint || '/api/educacional/ad/validate';
    const confirmEndpoint = root.dataset.confirmEndpoint || '/api/educacional/ad/confirm';
    const createEndpoint = '/api/educacional/ad/integrate';
    const selectAll = root.querySelector('[data-ad-select-all]');
    const count = root.querySelector('[data-ad-count]');
    const validateButton = root.querySelector('[data-ad-validate]');
    const createButton = root.querySelector('[data-ad-create]');
    const result = root.querySelector('[data-ad-result]');
    const createResult = root.querySelector('[data-ad-create-result]');
    const confirmButton = root.querySelector('[data-ad-confirm]');
    const testButton = root.querySelector('[data-ad-test]');
    const testResult = root.querySelector('[data-ad-test-result]');
    const confirmResult = root.querySelector('[data-ad-confirm-result]');
    const envSelect = root.querySelector('[data-ad-env]');
    const ouInput = root.querySelector('[data-ad-ou]');
    const serviceInput = root.querySelector('[data-ad-service]');
    const checks = () => [...root.querySelectorAll('[data-student-check]:not(:disabled)')];

    const selectedStudents = () => checks().filter(input => input.checked).map(input => ({
        RA: input.value,
        NOME: input.dataset.name || '',
        EMAILAD: input.dataset.emailad || '',
        CODTURMA: input.dataset.turma || '',
        IDPERLET: input.dataset.idperlet || 0,
    }));

    const update = () => {
        const items = checks();
        const selected = selectedStudents();
        const total = selected.length;
        if (count) count.textContent = total + ' selecionado' + (total === 1 ? '' : 's');
        if (selectAll) {
            selectAll.checked = items.length > 0 && total === items.length;
            selectAll.indeterminate = total > 0 && total < items.length;
        }
        if (validateButton) validateButton.disabled = total === 0;
        if (createButton) createButton.disabled = total === 0;
    };

    selectAll?.addEventListener('change', () => {
        checks().forEach(input => { input.checked = selectAll.checked; });
        update();
    });

    root.querySelectorAll('[data-student-check]').forEach(input => input.addEventListener('change', update));

    confirmButton?.addEventListener('click', async () => {
        const payload = {
            environment: envSelect?.value || 'homologacao',
            ou: ouInput?.value || '',
            service_account: serviceInput?.value || '',
        };

        confirmButton.disabled = true;
        confirmButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Confirmando...';

        try {
            const response = await fetch(confirmEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível confirmar o ambiente.');

            if (confirmResult) {
                confirmResult.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Ambiente confirmado.');
                confirmResult.hidden = false;
                confirmResult.classList.add('is-success');
                confirmResult.classList.remove('is-error');
            }
        } catch (error) {
            if (confirmResult) {
                confirmResult.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + (error.message || 'Confirmação falhou.');
                confirmResult.hidden = false;
                confirmResult.classList.add('is-error');
                confirmResult.classList.remove('is-success');
            }
        } finally {
            confirmButton.innerHTML = '<i class="fa-solid fa-shield-halved"></i> Confirmar ambiente';
            confirmButton.disabled = false;
        }
    });

    testButton?.addEventListener('click', async () => {
        testButton.disabled = true;
        testButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testando...';
        try {
            const response = await fetch('/api/educacional/ad/test-connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({})
            });
            const data = await response.json();
            if (testResult) {
                testResult.textContent = data.message || 'Teste LDAP concluído.';
                testResult.hidden = false;
                testResult.classList.toggle('is-error', !response.ok);
                testResult.classList.toggle('is-success', response.ok && data.status === 'ok');
            }
        } catch (error) {
            if (testResult) {
                testResult.textContent = error.message || 'Falha ao testar LDAP.';
                testResult.hidden = false;
                testResult.classList.add('is-error');
                testResult.classList.remove('is-success');
            }
        } finally {
            testButton.disabled = false;
            testButton.innerHTML = '<i class="fa-solid fa-plug"></i> Testar conexão LDAP';
        }
    });

    createButton?.addEventListener('click', async () => {
        const students = selectedStudents();
        if (!students.length) {
            window.alert('Selecione ao menos um aluno para criar no AD.');
            return;
        }

        createButton.disabled = true;
        createButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Criando...';

        try {
            const response = await fetch(createEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ students })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível criar os usuários no AD.');

            if (createResult) {
                const summary = data.summary || {};
                const message = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Criação concluída.') + ' (' + (summary.created_disabled || 0) + ' criados desabilitados, ' + (summary.already_exists || 0) + ' já existentes, ' + summary.errors + ' erros)';
                createResult.innerHTML = message;
                if (Array.isArray(data.results) && data.results.length) {
                    const details = document.createElement('div');
                    details.className = 'ad-result-details';
                    data.results.forEach(item => {
                        const line = document.createElement('div');
                        line.textContent = `${item.RA || ''}: ${item.status || ''} — ${item.message || ''}`;
                        details.appendChild(line);
                    });
                    createResult.appendChild(details);
                }
                createResult.hidden = false;
                createResult.classList.add('is-success');
                createResult.classList.remove('is-error');
            }
        } catch (error) {
            if (createResult) {
                createResult.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + (error.message || 'Criação no AD falhou.');
                createResult.hidden = false;
                createResult.classList.add('is-error');
                createResult.classList.remove('is-success');
            }
        } finally {
            createButton.innerHTML = '<i class="fa-solid fa-user-plus"></i> Criar objeto desabilitado';
            update();
        }
    });

    validateButton?.addEventListener('click', async () => {
        const students = selectedStudents();
        if (!students.length) {
            window.alert('Selecione ao menos um aluno para pré-validar.');
            return;
        }

        validateButton.disabled = true;
        validateButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Validando...';

        try {
            const response = await fetch(validateEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ students })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível validar a seleção.');

            if (result) {
                const summary = data.summary || {};
                const message = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Validação concluída.') + ' (' + summary.valid + ' válidos, ' + summary.duplicate + ' duplicados, ' + summary.already_integrated + ' já integrados, ' + summary.invalid + ' inválidos)';
                result.innerHTML = message;
                result.hidden = false;
                result.classList.toggle('is-error', !data.safe_to_proceed);
                result.classList.toggle('is-success', !!data.safe_to_proceed);
                if (data.safe_to_proceed) {
                    createButton.disabled = false;
                }
            }
        } catch (error) {
            if (result) {
                result.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + (error.message || 'Validação falhou.');
                result.hidden = false;
                result.classList.add('is-error');
                result.classList.remove('is-success');
            }
        } finally {
            validateButton.innerHTML = '<i class="fa-solid fa-check-double"></i> Pré-validar AD';
            update();
        }
    });

    update();
})();
</script>
