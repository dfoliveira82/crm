(() => {
    const root = document.querySelector('[data-academic-courses]');
    if (!root) return;

    const checks = [...root.querySelectorAll('[data-class-check]')];
    const selectAll = root.querySelector('[data-select-all]');
    const count = root.querySelector('[data-selected-count]');
    const previewButton = root.querySelector('[data-preview]');
    const previewPanel = root.querySelector('[data-preview-panel]');
    const message = root.querySelector('[data-preview-message]');
    const hierarchy = root.querySelector('[data-hierarchy]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const integrationEndpoint = root.dataset.integrationEndpoint || '/api/educacional/moodle/integrate';

    const selected = () => checks.filter(input => input.checked).map(input => input.value);
    const readJsonResponse = async response => {
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch {
            const start = raw.indexOf('{');
            const end = raw.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    return JSON.parse(raw.slice(start, end + 1));
                } catch {
                    // Continua para exibir a resposta textual original.
                }
            }
            const message = raw.replace(/\s+/g, ' ').trim();
            throw new Error(message || 'O servidor retornou uma resposta inválida.');
        }
    };

    const updateSelection = () => {
        const total = selected().length;
        if (count) count.textContent = total + ' selecionada' + (total === 1 ? '' : 's');
        if (previewButton) previewButton.disabled = total === 0;
        if (selectAll) {
            selectAll.checked = checks.length > 0 && total === checks.length;
            selectAll.indeterminate = total > 0 && total < checks.length;
        }
    };

    selectAll?.addEventListener('change', () => {
        checks.forEach(input => { input.checked = selectAll.checked; });
        updateSelection();
    });
    checks.forEach(input => input.addEventListener('change', updateSelection));

    root.querySelectorAll('[data-expand]').forEach(button => {
        button.addEventListener('click', () => {
            const index = button.dataset.expand;
            const detail = root.querySelector('[data-detail-row="' + index + '"]');
            const expandButton = root.querySelector('.academic-expand[data-expand="' + index + '"]');
            if (!detail) return;
            const open = detail.hidden;
            detail.hidden = !open;
            expandButton?.classList.toggle('is-open', open);
            expandButton?.setAttribute('aria-expanded', String(open));
        });
    });

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));

    const renderHierarchy = items => {
        if (!hierarchy) return;
        hierarchy.innerHTML = items.map(item => {
            const disciplines = (item.disciplines || []).map(discipline =>
                '<span><i class="fa-solid fa-book"></i> ' + escapeHtml(discipline.name) + '</span>'
            ).join('');
            return '<article class="hierarchy-class">' +
                '<strong>' + escapeHtml(item.turma) + ' · ' + escapeHtml(item.curso) + '</strong>' +
                '<div class="hierarchy-path">' + escapeHtml(item.tipo_ensino) + ' / ' + escapeHtml(item.periodo) + ' / ' + escapeHtml(item.turma) + '</div>' +
                '<div class="hierarchy-disciplines">' + disciplines + '</div>' +
                '</article>';
        }).join('');
    };

    const applyIntegrationStatuses = items => {
        items.forEach(item => {
            const row = root.querySelector('[data-class-row][data-class-id="' + item.idnumber + '"]');
            const classStatus = row?.querySelector('[data-class-status]');
            if (classStatus) {
                classStatus.textContent = item.status || 'Integrada';
                classStatus.className = 'academic-status status-' + String(item.status || 'Integrada').toLowerCase().replaceAll(' ', '-');
            }
            (item.disciplines || []).forEach(discipline => {
                const status = root.querySelector('[data-discipline-status="' + discipline.idnumber + '"]');
                if (!status) return;
                const integrated = discipline.sync_status === 'INTEGRADA';
                status.textContent = integrated ? 'Integrada' : 'Pendente';
                status.classList.toggle('is-integrated', integrated);
            });
        });
    };

    previewButton?.addEventListener('click', async () => {
        previewButton.disabled = true;
        previewButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparando...';
        try {
            const response = await fetch(integrationEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json'},
                body: JSON.stringify({classes: selected()})
            });
            const data = await readJsonResponse(response);
            if (!response.ok) throw new Error(data.message || 'Não foi possível preparar a integração.');
            if (message) {
                const summary = data.summary || {};
                const details = [
                    (summary.categories_created || 0) + ' categorias criadas',
                    (summary.categories_updated || 0) + ' categorias atualizadas',
                    (summary.courses_created || 0) + ' cursos criados',
                    (summary.courses_updated || 0) + ' cursos atualizados',
                ].join(' · ');
                message.textContent = (data.message || 'Integração concluída.') + ' ' + details + '.';
            }
            applyIntegrationStatuses(data.hierarchy || []);
            renderHierarchy(data.hierarchy || []);
            if (data.status === 'integrated') {
                window.setTimeout(() => window.location.reload(), 350);
                return;
            }
            if (previewPanel) {
                previewPanel.hidden = false;
                previewPanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }
        } catch (error) {
            window.alert(error.message);
        } finally {
            previewButton.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Integrar com Moodle';
            updateSelection();
        }
    });

    updateSelection();
})();
