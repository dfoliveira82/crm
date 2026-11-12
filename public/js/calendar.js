const root = document.querySelector('[data-calendar-root]');

if (root) {
    const viewToggle = root.querySelector('[data-calendar-view-toggle]');
    const viewMenu = root.querySelector('#calendar-view-menu');
    const createToggle = root.querySelector('[data-calendar-create-toggle]');
    const createMenu = root.querySelector('#calendar-create-menu');
    const modal = root.querySelector('[data-calendar-modal]');
    const form = root.querySelector('[data-calendar-form]');
    const viewContainer = root.querySelector('[data-calendar-view-container]');
    const currentDate = new Date(`${root.dataset.date}T12:00:00`);
    const todayDate = new Date(`${root.dataset.today}T12:00:00`);
    const currentMinutes = Number(root.dataset.currentMinutes || 615);
    let selectedView = root.dataset.view || 'day';
    let loadedEvents = [];

    const pad = value => String(value).padStart(2, '0');
    const dateKey = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const monthNames = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const monthNamesTitle = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    const weekdayNames = ['DOM.', 'SEG.', 'TER.', 'QUA.', 'QUI.', 'SEX.', 'SÁB.'];
    const viewLabels = { day: 'Dia', week: 'Semana', month: 'Mês', year: 'Ano', schedule: 'Programação', '4days': '4 dias' };

    const escapeHtml = value => String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const cloneDate = date => new Date(date.getTime());
    const startOfWeek = date => {
        const result = cloneDate(date);
        result.setDate(result.getDate() - result.getDay());
        return result;
    };
    const dateFromKey = value => new Date(`${value}T12:00:00`);
    const nextHour = value => {
        const [hours, minutes] = String(value || '10:00').split(':').map(Number);
        const date = new Date(2000, 0, 1, hours || 0, minutes || 0);
        date.setHours(date.getHours() + 1);
        return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const closeMenus = () => {
        [viewMenu, createMenu].forEach(menu => menu?.setAttribute('hidden', ''));
        viewToggle?.setAttribute('aria-expanded', 'false');
        createToggle?.setAttribute('aria-expanded', 'false');
    };
    const toggleMenu = (menu, trigger) => {
        const isOpen = !menu?.hasAttribute('hidden');
        closeMenus();
        if (!isOpen && menu) {
            menu.removeAttribute('hidden');
            trigger?.setAttribute('aria-expanded', 'true');
        }
    };

    if (viewToggle && !viewToggle.dataset.calendarDirectToggle) {
        viewToggle.addEventListener('click', event => { event.stopPropagation(); toggleMenu(viewMenu, viewToggle); });
    }
    if (createToggle && !createToggle.dataset.calendarModalBound) {
        createToggle.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); openModal('event'); });
        createToggle.dataset.calendarModalBound = 'true';
    }
    document.addEventListener('click', event => {
        if (!event.target.closest('.calendar-menu-wrap, .calendar-create-wrap')) closeMenus();
    });

    const timeLabel = hour => hour > 12 ? `${hour - 12} PM` : (hour === 12 ? '12 PM' : `${hour} AM`);
    const renderTimeAxis = () => Array.from({ length: 13 }, (_, index) => `<span>${timeLabel(index + 7)}</span>`).join('');
    const renderSlots = () => Array.from({ length: 13 }, (_, index) => {
        const hour = index + 7;
        return `<button class="calendar-slot" type="button" data-calendar-slot data-time="${pad(hour)}:00" aria-label="Criar evento às ${pad(hour)}:00"></button>`;
    }).join('');
    const renderDayColumn = (date, columnIndex = 0) => `<div class="calendar-column" data-column-date="${dateKey(date)}" style="--column-index:${columnIndex}">${renderSlots()}</div>`;
    const eventMarkup = events => events.map(event => `<article class="calendar-event" style="--event-top:${Number(event.top || 0)}px;--event-height:${Number(event.height || 48)}px"><strong>${escapeHtml(event.title || 'Evento')}</strong><span>${escapeHtml(event.time || '')}</span></article>`).join('');
    const currentLineMarkup = () => {
        const top = Math.max(0, Math.min(13 * 60, ((currentMinutes - (7 * 60)) / 60) * 60));
        return `<div class="calendar-current-time" style="top:${top}px" data-calendar-current-time><span>${pad(Math.floor(currentMinutes / 60))}:${pad(currentMinutes % 60)}</span></div>`;
    };

    const renderDayLike = (count, startDate) => {
        const dates = Array.from({ length: count }, (_, index) => {
            const date = cloneDate(startDate);
            date.setDate(date.getDate() + index);
            return date;
        });
        const columns = dates.map((date, index) => renderDayColumn(date, index)).join('');
        const headings = dates.map(date => `<div class="calendar-selected-day ${dateKey(date) === dateKey(currentDate) ? 'is-current' : ''}"><span>${weekdayNames[date.getDay()]}</span><strong>${date.getDate()}</strong></div>`).join('');
        return `<div class="calendar-view calendar-view--columns" data-view="${selectedView}" style="--calendar-columns:${count}">
            <div class="calendar-day-heading calendar-day-heading--columns"><div class="calendar-timezone-label">GMT-3<small>/ AM</small></div><div class="calendar-day-headings">${headings}</div></div>
            <div class="calendar-grid calendar-grid--columns" data-calendar-grid><div class="calendar-time-axis">${renderTimeAxis()}</div><div class="calendar-grid-content"><div class="calendar-columns-grid">${columns}</div><div class="calendar-events" data-calendar-events>${eventMarkup(loadedEvents)}</div>${currentLineMarkup()}</div></div>
        </div>`;
    };

    const renderMonth = () => {
        const monthStart = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1, 12);
        const leadingDays = monthStart.getDay();
        const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();
        const cells = Array.from({ length: 42 }, (_, index) => {
            const day = index - leadingDays + 1;
            if (day < 1 || day > daysInMonth) return '<div class="calendar-month-cell is-outside"></div>';
            const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), day, 12);
            const isToday = dateKey(date) === dateKey(todayDate);
            const isSelected = dateKey(date) === dateKey(currentDate);
            const dayEvents = loadedEvents.filter(event => String(event.start || '').slice(0, 10) === dateKey(date));
            return `<button class="calendar-month-cell ${isToday ? 'is-today' : ''} ${isSelected ? 'is-selected' : ''}" type="button" data-calendar-month-day="${dateKey(date)}"><span>${day}</span>${dayEvents.slice(0, 2).map(event => `<small>${escapeHtml(event.title || 'Evento')}</small>`).join('')}</button>`;
        }).join('');
        return `<div class="calendar-view calendar-view--month" data-view="month"><div class="calendar-month-title"><strong>${monthNamesTitle[currentDate.getMonth()]} de ${currentDate.getFullYear()}</strong><span>GMT-3</span></div><div class="calendar-month-weekdays">${['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(day => `<span>${day}</span>`).join('')}</div><div class="calendar-month-grid">${cells}</div></div>`;
    };

    const renderYear = () => {
        const year = currentDate.getFullYear();
        const months = Array.from({ length: 12 }, (_, month) => {
            const start = new Date(year, month, 1, 12);
            const leading = start.getDay();
            const total = new Date(year, month + 1, 0).getDate();
            const days = Array.from({ length: 42 }, (_, index) => {
                const day = index - leading + 1;
                if (day < 1 || day > total) return '<span class="calendar-year-day is-outside"></span>';
                const date = new Date(year, month, day, 12);
                return `<button class="calendar-year-day ${dateKey(date) === dateKey(todayDate) ? 'is-today' : ''}" type="button" data-calendar-month-day="${dateKey(date)}">${day}</button>`;
            }).join('');
            return `<section class="calendar-year-month"><h3>${monthNamesTitle[month]}</h3><div class="calendar-year-weekdays">${['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].map(day => `<span>${day}</span>`).join('')}</div><div class="calendar-year-grid">${days}</div></section>`;
        }).join('');
        return `<div class="calendar-view calendar-view--year" data-view="year"><div class="calendar-year-title"><strong>${year}</strong><span>Visão anual</span></div><div class="calendar-year-grid-layout">${months}</div></div>`;
    };

    const renderSchedule = () => {
        const rows = loadedEvents.length
            ? loadedEvents.map(event => `<article class="calendar-schedule-row"><time>${escapeHtml(event.time || '--:--')}</time><div><strong>${escapeHtml(event.title || 'Evento')}</strong><span>${escapeHtml(event.location || 'Sem local informado')}</span></div></article>`).join('')
            : '<div class="calendar-empty-state"><i class="fa-regular fa-calendar-check"></i><strong>Nenhum evento programado</strong><span>Os eventos deste período aparecerão aqui.</span></div>';
        return `<div class="calendar-view calendar-view--schedule" data-view="schedule"><div class="calendar-schedule-title"><strong>Programação</strong><span>A partir de ${currentDate.getDate()} de ${monthNames[currentDate.getMonth()]}</span></div><div class="calendar-schedule-list">${rows}</div></div>`;
    };

    const bindViewActions = () => {
        root.querySelectorAll('[data-calendar-slot]').forEach(slot => slot.addEventListener('click', () => {
            const time = form?.querySelector('[name="time"]');
            const endTime = form?.querySelector('[name="end_time"]');
            if (time) time.value = slot.dataset.time || '10:00';
            if (endTime) endTime.value = nextHour(time?.value || '10:00');
            openModal('event');
        }));
        root.querySelectorAll('[data-calendar-month-day]').forEach(button => button.addEventListener('click', () => {
            const next = dateFromKey(button.dataset.calendarMonthDay);
            if (Number.isNaN(next.valueOf())) return;
            currentDate.setTime(next.getTime());
            selectedView = 'day';
            root.querySelector('[data-calendar-view-label]').textContent = viewLabels.day;
            updateSelectedViewOption();
            updateHeader();
            renderView();
            fetchEvents();
        }));
    };
    const renderView = () => {
        if (!viewContainer) return;
        if (selectedView === 'month') viewContainer.innerHTML = renderMonth();
        else if (selectedView === 'year') viewContainer.innerHTML = renderYear();
        else if (selectedView === 'schedule') viewContainer.innerHTML = renderSchedule();
        else if (selectedView === 'week') viewContainer.innerHTML = renderDayLike(7, startOfWeek(currentDate));
        else if (selectedView === '4days') viewContainer.innerHTML = renderDayLike(4, currentDate);
        else viewContainer.innerHTML = renderDayLike(1, currentDate);
        bindViewActions();
    };

    const updateHeader = () => {
        const label = root.querySelector('[data-calendar-date-label]');
        if (label) label.textContent = selectedView === 'year' ? String(currentDate.getFullYear()) : `${currentDate.getDate()} de ${monthNames[currentDate.getMonth()]} de ${currentDate.getFullYear()}`;
        root.dataset.date = dateKey(currentDate);
        const dateInput = form?.querySelector('[name="date"]');
        if (dateInput) dateInput.value = dateKey(currentDate);
    };
    const updateSelectedViewOption = () => root.querySelectorAll('[data-calendar-view]').forEach(item => item.classList.toggle('is-selected', item.dataset.calendarView === selectedView));
    const fetchEvents = async () => {
        if (!root.dataset.eventsUrl) return;
        try {
            const response = await fetch(`${root.dataset.eventsUrl}?date=${encodeURIComponent(dateKey(currentDate))}&view=${encodeURIComponent(selectedView)}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            loadedEvents = Array.isArray(payload.items) ? payload.items : [];
            renderView();
        } catch (_) {
            // A falha de rede não impede a navegação local da agenda.
        }
    };
    const openModal = type => {
        const typeLabel = root.querySelector('[data-calendar-modal-type]');
        if (typeLabel) typeLabel.textContent = type === 'task' ? 'TAREFA' : 'EVENTO';
        modal?.removeAttribute('hidden');
        form?.querySelector('[name="title"]')?.focus();
        closeMenus();
    };

    root.querySelector('[data-calendar-prev]')?.addEventListener('click', () => {
        if (selectedView === 'month') currentDate.setMonth(currentDate.getMonth() - 1);
        else if (selectedView === 'year') currentDate.setFullYear(currentDate.getFullYear() - 1);
        else currentDate.setDate(currentDate.getDate() - (selectedView === 'week' ? 7 : (selectedView === '4days' ? 4 : 1)));
        updateHeader(); renderView(); fetchEvents();
    });
    root.querySelector('[data-calendar-next]')?.addEventListener('click', () => {
        if (selectedView === 'month') currentDate.setMonth(currentDate.getMonth() + 1);
        else if (selectedView === 'year') currentDate.setFullYear(currentDate.getFullYear() + 1);
        else currentDate.setDate(currentDate.getDate() + (selectedView === 'week' ? 7 : (selectedView === '4days' ? 4 : 1)));
        updateHeader(); renderView(); fetchEvents();
    });
    root.querySelector('[data-calendar-today]')?.addEventListener('click', () => { currentDate.setTime(todayDate.getTime()); updateHeader(); renderView(); fetchEvents(); });
    root.querySelectorAll('[data-mini-day]').forEach(button => button.addEventListener('click', () => {
        const next = dateFromKey(button.dataset.miniDay);
        if (!Number.isNaN(next.valueOf())) { currentDate.setTime(next.getTime()); updateHeader(); renderView(); fetchEvents(); }
    }));
    root.querySelectorAll('[data-calendar-view]').forEach(button => button.addEventListener('click', () => {
        selectedView = button.dataset.calendarView;
        root.querySelector('[data-calendar-view-label]').textContent = viewLabels[selectedView];
        updateSelectedViewOption();
        closeMenus();
        updateHeader(); renderView(); fetchEvents();
    }));
    root.querySelectorAll('[data-calendar-setting]').forEach(button => button.addEventListener('click', () => {
        const checked = button.getAttribute('aria-checked') === 'true';
        button.setAttribute('aria-checked', String(!checked));
        button.classList.toggle('is-disabled', !checked);
    }));
    root.querySelectorAll('[data-calendar-create]').forEach(button => button.addEventListener('click', () => openModal(button.dataset.calendarCreate)));
    root.querySelectorAll('[data-calendar-modal-close]').forEach(button => button.addEventListener('click', () => modal?.setAttribute('hidden', '')));
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            const response = await fetch(root.dataset.saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': root.dataset.csrfToken || ''
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.error || 'Não foi possível salvar o evento.');
            modal?.setAttribute('hidden', '');
            window.location.reload();
        } catch (error) {
            window.showToast?.(error.message || 'Não foi possível salvar o evento.');
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { closeMenus(); modal?.setAttribute('hidden', ''); }
        if (['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
        const shortcut = { d: 'day', w: 'week', m: 'month', y: 'year', a: 'schedule', x: '4days' }[event.key.toLowerCase()];
        if (shortcut) root.querySelector(`[data-calendar-view="${shortcut}"]`)?.click();
    });

    renderView();
    fetchEvents();
}
