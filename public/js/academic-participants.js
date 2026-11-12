(() => {
    const root = document.querySelector('[data-participant-page]');
    if (!root) return;

    const checks = [...root.querySelectorAll('[data-discipline-check]')];
    const selectAll = root.querySelector('[data-select-all-participants]');
    const count = root.querySelector('[data-participant-count]');
    const stat = root.querySelector('[data-participant-selected]');
    const button = root.querySelector('[data-integrate-participants]');
    const result = root.querySelector('[data-participant-result]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const endpoint = root.dataset.integrationEndpoint || '/api/educacional/moodle/participants/integrate';
    const statusEndpoint = root.dataset.statusEndpoint || '/api/educacional/moodle/participants/status';

    const selected = () => checks.filter(input => input.checked).map(input => Number(input.value));
    const update = () => {
        const total = selected().length;
        const available = checks.filter(input => !input.disabled);
        if (count) count.textContent = total + ' selecionada' + (total === 1 ? '' : 's');
        if (stat) stat.textContent = String(total);
        if (button) button.disabled = total === 0;
        if (selectAll) {
            selectAll.checked = available.length > 0 && total === available.length;
            selectAll.indeterminate = total > 0 && total < available.length;
        }
    };

    const setStatusText = (element, item) => {
        if (!element) return;
        if (item.integration_status === 'unavailable') {
            element.className = 'participant-enrolment-status is-error';
            element.textContent = 'Consulta indisponível';
            element.title = item.message || 'Não foi possível consultar as matrículas do Moodle.';
            return;
        }
        if (item.integration_status === 'course_pending') {
            element.className = 'participant-enrolment-status is-pending';
            element.textContent = '0 adicionados · ' + (item.pending ?? 0) + ' pendentes';
            return;
        }
        if (item.integration_status === 'course_not_found') {
            element.className = 'participant-enrolment-status is-error';
            element.textContent = 'Curso Moodle não encontrado';
            return;
        }
        const added = item.added ?? 0;
        const pending = item.pending ?? 0;
        element.className = 'participant-enrolment-status ' + (pending === 0 ? 'is-complete' : 'is-partial');
        element.textContent = added + ' adicionados · ' + pending + ' pendentes';
    };

    const loadStatus = async (detail, force = false) => {
        if (!detail || (!force && detail.dataset.statusLoaded === '1')) return;
        const rows = [...detail.querySelectorAll('[data-discipline-id][data-discipline-integrated="1"]')];
        rows.forEach(row => {
            const status = row.querySelector('[data-discipline-status]');
            if (status) {
                status.className = 'participant-enrolment-status is-loading';
                status.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Consultando Moodle...';
            }
        });
        if (rows.length === 0) {
            detail.dataset.statusLoaded = '1';
            return;
        }

        try {
            const response = await fetch(statusEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json'},
                body: JSON.stringify({disciplines: rows.map(row => Number(row.dataset.disciplineId))})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível consultar as matrículas.');
            const byId = new Map((data.disciplines || []).map(item => [Number(item.id_turma_disc), item]));
            rows.forEach(row => setStatusText(row.querySelector('[data-discipline-status]'), byId.get(Number(row.dataset.disciplineId)) || {
                integration_status: 'unavailable',
                message: 'A disciplina não retornou dados.'
            }));
            detail.dataset.statusLoaded = '1';
        } catch (error) {
            rows.forEach(row => {
                const status = row.querySelector('[data-discipline-status]');
                if (status) {
                    status.className = 'participant-enrolment-status is-error';
                    status.textContent = 'Consulta indisponível';
                    status.title = error.message;
                }
            });
        }
    };

    selectAll?.addEventListener('change', () => {
        checks.forEach(input => { if (!input.disabled) input.checked = selectAll.checked; });
        update();
    });
    checks.forEach(input => input.addEventListener('change', update));

    root.querySelectorAll('[data-participant-expand]').forEach(expandButton => expandButton.addEventListener('click', () => {
        const index = expandButton.dataset.participantExpand;
        const detail = root.querySelector('[data-participant-detail="' + index + '"]');
        if (!detail) return;
        const open = detail.hidden;
        detail.hidden = !open;
        expandButton.classList.toggle('is-open', open);
        expandButton.setAttribute('aria-expanded', String(open));
        if (open) loadStatus(detail);
    }));

    button?.addEventListener('click', async () => {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Integrando...';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json'},
                body: JSON.stringify({disciplines: selected()})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível integrar os participantes.');
            const summary = data.summary || {};
            if (result) {
                result.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Integração concluída.') + ' ' + (summary.users_created || 0) + ' usuários criados, ' + (summary.enrolments || 0) + ' matrículas processadas e ' + (summary.student_links_saved || 0) + ' vínculos RM atualizados.';
                result.hidden = false;
            }
            checks.forEach(input => { input.checked = false; });
            root.querySelectorAll('[data-participant-detail]:not([hidden])').forEach(detail => loadStatus(detail, true));
            update();
        } catch (error) {
            window.alert(error.message);
        } finally {
            button.innerHTML = '<i class="fa-solid fa-user-plus"></i> Integrar participantes';
            update();
        }
    });

    update();
})();
